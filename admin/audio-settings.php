<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/site-settings.php';
requireAdmin();

function audioAdminFileIsSafe(string $path): bool
{
    return preg_match('#^public/uploads/audio/[a-f0-9]{32}\.(?:mp3|wav|ogg)$#', $path) === 1;
}

function prepareAudioUpload(string $field): array
{
    $upload = $_FILES[$field] ?? null;
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['present' => false, 'path' => '', 'temporary' => '', 'error' => ''];
    }
    $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        || (int) ($upload['size'] ?? 0) > 10 * 1024 * 1024) {
        return ['present' => true, 'path' => '', 'temporary' => '', 'error' => 'Audio must be 10 MB or smaller.'];
    }
    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
        return ['present' => true, 'path' => '', 'temporary' => '', 'error' => 'The uploaded audio file could not be verified.'];
    }
    $originalExtension = strtolower(pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']);
    $allowedMimes = [
        'mp3' => ['audio/mpeg', 'audio/mp3'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/wave'],
        'ogg' => ['audio/ogg', 'application/ogg'],
    ];
    if (!isset($allowedMimes[$originalExtension]) || !is_string($mime) || !in_array($mime, $allowedMimes[$originalExtension], true)) {
        return ['present' => true, 'path' => '', 'temporary' => '', 'error' => 'Upload a genuine MP3, WAV, or OGG audio file.'];
    }
    return [
        'present' => true,
        'path' => 'public/uploads/audio/' . bin2hex(random_bytes(16)) . '.' . $originalExtension,
        'temporary' => (string) $upload['tmp_name'],
        'error' => '',
    ];
}

function upsertAudioSettings(PDO $pdo, array $settings): void
{
    $find = $pdo->prepare('SELECT id FROM site_settings WHERE setting_group=:setting_group AND setting_key=:setting_key LIMIT 1');
    $update = $pdo->prepare('UPDATE site_settings SET setting_value=:setting_value,setting_type=:setting_type,updated_at=CURRENT_TIMESTAMP WHERE id=:id');
    $insert = $pdo->prepare('INSERT INTO site_settings (setting_group,setting_key,setting_value,setting_type,created_at,updated_at) VALUES (:setting_group,:setting_key,:setting_value,:setting_type,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
    $booleanKeys = ['audio_enabled', 'audio_show_button', 'audio_autoplay_attempt'];
    foreach ($settings as $key => $value) {
        $type = in_array($key, $booleanKeys, true) ? 'boolean' : ($key === 'audio_volume' ? 'number' : ($key === 'audio_file' ? 'file' : 'text'));
        $find->execute([':setting_group' => 'website_audio', ':setting_key' => $key]);
        $id = $find->fetchColumn();
        if ($id !== false) $update->execute([':setting_value' => $value, ':setting_type' => $type, ':id' => (int) $id]);
        else $insert->execute([':setting_group' => 'website_audio', ':setting_key' => $key, ':setting_value' => $value, ':setting_type' => $type]);
    }
}

$saved = getWebsiteAudioSettings();
$form = $saved;
$errors = [];
$success = isset($_GET['saved']) && $_GET['saved'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['audio_enabled'] = isset($_POST['audio_enabled']) ? '1' : '0';
    $form['audio_show_button'] = isset($_POST['audio_show_button']) ? '1' : '0';
    $form['audio_autoplay_attempt'] = isset($_POST['audio_autoplay_attempt']) ? '1' : '0';
    $form['audio_title'] = trim((string) ($_POST['audio_title'] ?? ''));
    $form['audio_volume'] = trim((string) ($_POST['audio_volume'] ?? '15'));
    $form['audio_button_position'] = trim((string) ($_POST['audio_button_position'] ?? 'bottom-right'));

    if (!csrfIsValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    }
    if ($form['audio_title'] === '' || mb_strlen($form['audio_title']) > 150) {
        $errors[] = 'Audio title is required and must not exceed 150 characters.';
    }
    $volume = filter_var($form['audio_volume'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 30]]);
    if ($volume === false) $errors[] = 'Default volume must be between 0% and 30%.';
    else $form['audio_volume'] = (string) $volume;
    if (!in_array($form['audio_button_position'], ['bottom-right', 'bottom-left'], true)) {
        $errors[] = 'Select a valid button position.';
    }

    $upload = prepareAudioUpload('audio_upload');
    if ($upload['error'] !== '') $errors[] = $upload['error'];
    $newAbsolute = '';
    if ($errors === [] && $upload['present']) {
        $newAbsolute = dirname(__DIR__) . '/' . $upload['path'];
        if (!move_uploaded_file($upload['temporary'], $newAbsolute)) $errors[] = 'The audio file could not be saved.';
        else $form['audio_file'] = $upload['path'];
    } elseif (isset($_POST['remove_audio'])) {
        $form['audio_file'] = '';
        $form['audio_enabled'] = '0';
    } else {
        $form['audio_file'] = $saved['audio_file'];
    }

    if ($form['audio_enabled'] === '1' && $form['audio_file'] === '') {
        $errors[] = 'Upload an audio file before enabling background audio.';
    }

    if ($errors === []) {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            upsertAudioSettings($pdo, $form);
            $pdo->commit();
            if ($saved['audio_file'] !== $form['audio_file'] && audioAdminFileIsSafe($saved['audio_file'])) {
                $oldAbsolute = dirname(__DIR__) . '/' . $saved['audio_file'];
                if (is_file($oldAbsolute)) unlink($oldAbsolute);
            }
            header('Location: audio-settings.php?saved=1');
            exit;
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            if ($newAbsolute !== '' && is_file($newAbsolute)) unlink($newAbsolute);
            error_log($exception->getMessage());
            $errors[] = 'Website Audio settings could not be saved.';
        }
    } elseif ($newAbsolute !== '' && is_file($newAbsolute)) {
        unlink($newAbsolute);
    }
}

$pageTitle = 'Website Audio';
require __DIR__ . '/includes/header.php';
?>
<header class="admin-header"><a class="admin-brand" href="dashboard.php">Design24 Studio Admin</a><div class="admin-user"><span>Signed in as <?= e(currentAdminName()) ?></span><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><button class="logout-button" type="submit">Logout</button></form></div></header>
<main class="admin-main">
    <div class="settings-toolbar"><a href="dashboard.php">← Dashboard</a><a href="../index.php" target="_blank" rel="noopener">View Website ↗</a></div>
    <section class="panel"><h1>Website Audio</h1><p>Manage optional, low-volume background music. Visitors always remain in control.</p>
        <?php if ($success): ?><p class="success">Website Audio settings saved successfully.</p><?php endif; ?>
        <?php if ($errors !== []): ?><div class="error"><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <section class="settings-section"><h2>Audio File</h2><div class="field"><label for="audio_title">Audio title</label><input id="audio_title" name="audio_title" value="<?= e($form['audio_title']) ?>" maxlength="150" required></div><div class="field"><label for="audio_upload">Upload audio file</label><input id="audio_upload" name="audio_upload" type="file" accept="audio/mpeg,audio/wav,audio/ogg,.mp3,.wav,.ogg"><small class="help">MP3, WAV or OGG, maximum 10 MB. Use calm, properly licensed music.</small></div>
                <?php if ($saved['audio_file'] !== '' && audioAdminFileIsSafe($saved['audio_file']) && is_file(dirname(__DIR__) . '/' . $saved['audio_file'])): ?><div class="field"><label>Current audio</label><audio controls preload="metadata" style="width:100%"><source src="../<?= e($saved['audio_file']) ?>"></audio><label style="margin-top:10px"><input name="remove_audio" type="checkbox" value="1"> Remove current audio</label></div><?php endif; ?>
            </section>
            <section class="settings-section"><h2>Playback and Button</h2><div class="settings-grid"><div class="field"><label for="audio_volume">Default volume (%)</label><input id="audio_volume" name="audio_volume" type="number" min="0" max="30" value="<?= e($form['audio_volume']) ?>" required><small class="help">15% is recommended. The safety limit is 30%.</small></div><div class="field"><label for="audio_button_position">Button position</label><select id="audio_button_position" name="audio_button_position"><option value="bottom-right"<?= $form['audio_button_position']==='bottom-right'?' selected':'' ?>>Bottom right</option><option value="bottom-left"<?= $form['audio_button_position']==='bottom-left'?' selected':'' ?>>Bottom left</option></select></div></div>
                <div class="checkbox-field"><input id="audio_enabled" name="audio_enabled" type="checkbox" value="1"<?= $form['audio_enabled']==='1'?' checked':'' ?>><label for="audio_enabled">Enable background audio</label></div>
                <div class="checkbox-field"><input id="audio_show_button" name="audio_show_button" type="checkbox" value="1"<?= $form['audio_show_button']==='1'?' checked':'' ?>><label for="audio_show_button">Show floating audio button</label></div>
                <div class="checkbox-field"><input id="audio_autoplay_attempt" name="audio_autoplay_attempt" type="checkbox" value="1"<?= $form['audio_autoplay_attempt']==='1'?' checked':'' ?>><label for="audio_autoplay_attempt">Attempt autoplay when no visitor preference exists</label></div>
                <p class="help">Browsers may block autoplay. If that happens, audio stays off without showing an error.</p>
            </section>
            <div class="form-actions"><button class="primary-button" type="submit">Save Website Audio</button><a class="secondary-admin-button" href="audio-settings.php">Cancel / Reset</a></div>
        </form>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>

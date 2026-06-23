<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/site-settings.php';
requireAdmin();

function homepageUploadedImage(string $path): bool
{
    return preg_match('#^uploads/site/hero/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#', $path) === 1;
}

function saveHomepageSettings(PDO $pdo, array $settings, array $types): void
{
    $find = $pdo->prepare('SELECT id FROM site_settings WHERE setting_group = :group_name AND setting_key = :setting_key LIMIT 1');
    $update = $pdo->prepare('UPDATE site_settings SET setting_value = :setting_value, setting_type = :setting_type, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $insert = $pdo->prepare('INSERT INTO site_settings (setting_group, setting_key, setting_value, setting_type, created_at, updated_at) VALUES (:group_name, :setting_key, :setting_value, :setting_type, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');

    foreach ($settings as $key => $value) {
        $find->execute([':group_name' => 'homepage', ':setting_key' => $key]);
        $id = $find->fetchColumn();
        if ($id !== false) {
            $update->execute([':setting_value' => $value, ':setting_type' => $types[$key] ?? 'text', ':id' => (int) $id]);
        } else {
            $insert->execute([':group_name' => 'homepage', ':setting_key' => $key, ':setting_value' => $value, ':setting_type' => $types[$key] ?? 'text']);
        }
    }
}

$defaults = homepageSettingDefaults();
$savedSettings = getHomepageSettings();
$form = $savedSettings;
$errors = [];
$success = isset($_GET['saved']) && $_GET['saved'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($defaults) as $key) {
        if ($key === 'show_service_row') {
            $form[$key] = isset($_POST[$key]) ? '1' : '0';
        } elseif ($key !== 'hero_image') {
            $form[$key] = trim((string) ($_POST[$key] ?? ''));
        }
    }

    if (!csrfIsValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    }

    $limits = [
        'hero_eyebrow' => ['Welcome text', 2, 100],
        'hero_heading_line_1' => ['Heading line 1', 2, 80],
        'hero_heading_line_2' => ['Heading line 2', 2, 80],
        'hero_heading_highlight' => ['Highlighted heading', 2, 100],
        'hero_description_1' => ['First description', 10, 600],
        'hero_description_2' => ['Second description', 10, 600],
        'hero_primary_text' => ['Primary button text', 2, 60],
        'hero_secondary_text' => ['Secondary button text', 2, 60],
        'hero_image_alt' => ['Hero image alternative text', 2, 180],
        'service_residential' => ['Residential label', 2, 50],
        'service_commercial' => ['Commercial label', 2, 50],
        'service_office' => ['Office label', 2, 50],
        'service_kitchen' => ['Kitchen label', 2, 50],
        'service_furniture' => ['Furniture label', 2, 70],
        'service_turnkey' => ['Turnkey label', 2, 70],
    ];
    foreach ($limits as $key => [$label, $minimum, $maximum]) {
        $length = mb_strlen($form[$key]);
        if ($length < $minimum || $length > $maximum) {
            $errors[] = $label . " must be between {$minimum} and {$maximum} characters.";
        }
    }
    foreach (['hero_primary_url' => 'Primary button', 'hero_secondary_url' => 'Secondary button'] as $key => $label) {
        if (safePageUrl($form[$key], '') === '') {
            $errors[] = $label . ' link must be a valid URL, site path, or #section link.';
        }
    }

    $upload = $_FILES['hero_image_upload'] ?? null;
    $hasUpload = is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $newRelativePath = '';
    $newAbsolutePath = '';
    if ($hasUpload) {
        $uploadError = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            || (int) ($upload['size'] ?? 0) > 2 * 1024 * 1024) {
            $errors[] = 'Hero image must be 2 MB or smaller.';
        } elseif ($uploadError !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
            $errors[] = 'The hero image could not be verified or uploaded.';
        } else {
            $mimeMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file((string) $upload['tmp_name']);
            if (!is_string($mime) || !isset($mimeMap[$mime]) || @getimagesize((string) $upload['tmp_name']) === false) {
                $errors[] = 'Upload a valid JPG, JPEG, PNG, or WebP hero image.';
            } else {
                $newRelativePath = 'uploads/site/hero/' . bin2hex(random_bytes(16)) . '.' . $mimeMap[$mime];
                $newAbsolutePath = dirname(__DIR__) . '/' . $newRelativePath;
            }
        }
    }

    $oldImage = $savedSettings['hero_image'];
    if ($errors === []) {
        if ($hasUpload && $newAbsolutePath !== '') {
            if (!move_uploaded_file((string) $upload['tmp_name'], $newAbsolutePath)) {
                $errors[] = 'The hero image could not be saved. The current image was kept.';
            } else {
                $form['hero_image'] = $newRelativePath;
            }
        } elseif (isset($_POST['remove_hero_image'])) {
            $form['hero_image'] = $defaults['hero_image'];
        } else {
            $form['hero_image'] = $oldImage;
        }
    }

    if ($errors === []) {
        $types = array_fill_keys(array_keys($form), 'text');
        $types['hero_image'] = 'image';
        $types['show_service_row'] = 'boolean';
        $types['hero_primary_url'] = 'url';
        $types['hero_secondary_url'] = 'url';
        try {
            $pdo = db();
            $pdo->beginTransaction();
            saveHomepageSettings($pdo, $form, $types);
            $pdo->commit();
            if ($oldImage !== $form['hero_image'] && homepageUploadedImage($oldImage)) {
                $oldAbsolute = dirname(__DIR__) . '/' . $oldImage;
                if (is_file($oldAbsolute)) unlink($oldAbsolute);
            }
            header('Location: homepage-settings.php?saved=1');
            exit;
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            if ($newAbsolutePath !== '' && is_file($newAbsolutePath)) unlink($newAbsolutePath);
            error_log($exception->getMessage());
            $errors[] = 'Homepage settings could not be saved. Existing content was kept.';
        }
    }
}

$pageTitle = 'Homepage Settings';
require __DIR__ . '/includes/header.php';
?>
<header class="admin-header"><a class="admin-brand" href="dashboard.php">Design24 Studio Admin</a><div class="admin-user"><span>Signed in as <?= e(currentAdminName()) ?></span><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><button class="logout-button" type="submit">Logout</button></form></div></header>
<main class="admin-main">
    <div class="settings-toolbar"><a href="dashboard.php">← Back to Dashboard</a><a href="../" target="_blank" rel="noopener">View Website ↗</a></div>
    <section class="panel" aria-labelledby="homepage-settings-title"><h1 id="homepage-settings-title">Homepage Settings</h1><p>Edit the current homepage hero and service row.</p>
        <?php if ($success): ?><p class="success" role="status">Homepage settings saved successfully.</p><?php endif; ?>
        <?php if ($errors !== []): ?><div class="error" role="alert"><strong>Please correct the following:</strong><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" action="homepage-settings.php" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <section class="settings-section"><h2>Hero Headings</h2><div class="field"><label for="hero_eyebrow">Welcome text *</label><input id="hero_eyebrow" name="hero_eyebrow" type="text" maxlength="100" value="<?= e($form['hero_eyebrow']) ?>" required></div><div class="settings-grid"><div class="field"><label for="hero_heading_line_1">Heading line 1 *</label><input id="hero_heading_line_1" name="hero_heading_line_1" type="text" maxlength="80" value="<?= e($form['hero_heading_line_1']) ?>" required></div><div class="field"><label for="hero_heading_line_2">Heading line 2 *</label><input id="hero_heading_line_2" name="hero_heading_line_2" type="text" maxlength="80" value="<?= e($form['hero_heading_line_2']) ?>" required></div><div class="field"><label for="hero_heading_highlight">Highlighted heading *</label><input id="hero_heading_highlight" name="hero_heading_highlight" type="text" maxlength="100" value="<?= e($form['hero_heading_highlight']) ?>" required></div></div></section>
            <section class="settings-section"><h2>Hero Description</h2><div class="field"><label for="hero_description_1">First paragraph *</label><textarea id="hero_description_1" name="hero_description_1" maxlength="600" required><?= e($form['hero_description_1']) ?></textarea></div><div class="field"><label for="hero_description_2">Second paragraph *</label><textarea id="hero_description_2" name="hero_description_2" maxlength="600" required><?= e($form['hero_description_2']) ?></textarea></div></section>
            <section class="settings-section"><h2>Hero Buttons</h2><div class="settings-grid"><div class="field"><label for="hero_primary_text">Primary button text *</label><input id="hero_primary_text" name="hero_primary_text" type="text" maxlength="60" value="<?= e($form['hero_primary_text']) ?>" required></div><div class="field"><label for="hero_primary_url">Primary button link *</label><input id="hero_primary_url" name="hero_primary_url" type="text" maxlength="500" value="<?= e($form['hero_primary_url']) ?>" required></div><div class="field"><label for="hero_secondary_text">Secondary button text *</label><input id="hero_secondary_text" name="hero_secondary_text" type="text" maxlength="60" value="<?= e($form['hero_secondary_text']) ?>" required></div><div class="field"><label for="hero_secondary_url">Secondary button link *</label><input id="hero_secondary_url" name="hero_secondary_url" type="text" maxlength="500" value="<?= e($form['hero_secondary_url']) ?>" required></div></div></section>
            <section class="settings-section"><h2>Hero Image</h2><div class="field"><label for="hero_image_alt">Image alternative text *</label><input id="hero_image_alt" name="hero_image_alt" type="text" maxlength="180" value="<?= e($form['hero_image_alt']) ?>" required></div><div class="field"><label for="hero_image_upload">Upload hero image</label><input id="hero_image_upload" name="hero_image_upload" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><small class="help">JPG, JPEG, PNG, or WebP. Maximum 2 MB.</small><img class="logo-preview" src="../<?= e($savedSettings['hero_image']) ?>" alt="Current hero image"><img class="logo-preview" id="hero-image-preview" alt="Selected hero image preview" hidden></div><?php if (homepageUploadedImage($savedSettings['hero_image'])): ?><div class="checkbox-field"><input id="remove_hero_image" name="remove_hero_image" type="checkbox" value="1"><label for="remove_hero_image">Remove uploaded image and restore the original hero image</label></div><?php endif; ?></section>
            <section class="settings-section"><h2>Service Row</h2><div class="settings-grid"><?php foreach (['service_residential' => 'Residential', 'service_commercial' => 'Commercial', 'service_office' => 'Office', 'service_kitchen' => 'Kitchen', 'service_furniture' => 'Customized Furniture', 'service_turnkey' => 'Turnkey Execution'] as $key => $label): ?><div class="field"><label for="<?= e($key) ?>"><?= e($label) ?> label *</label><input id="<?= e($key) ?>" name="<?= e($key) ?>" type="text" maxlength="70" value="<?= e($form[$key]) ?>" required></div><?php endforeach; ?></div><div class="checkbox-field"><input id="show_service_row" name="show_service_row" type="checkbox" value="1" <?= $form['show_service_row'] === '1' ? 'checked' : '' ?>><label for="show_service_row">Show service row</label></div></section>
            <div class="form-actions"><button class="primary-button" type="submit">Save Homepage Settings</button><a class="secondary-admin-button" href="homepage-settings.php">Cancel / Reset</a></div>
        </form>
    </section>
</main>
<script>
const heroImageInput = document.getElementById('hero_image_upload');
const heroImagePreview = document.getElementById('hero-image-preview');
heroImageInput.addEventListener('change', () => { const file = heroImageInput.files[0]; if (!file) { heroImagePreview.hidden = true; heroImagePreview.removeAttribute('src'); return; } heroImagePreview.src = URL.createObjectURL(file); heroImagePreview.hidden = false; });
const removeHeroImage = document.getElementById('remove_hero_image');
if (removeHeroImage) removeHeroImage.addEventListener('change', () => { if (removeHeroImage.checked && !window.confirm('Restore the original hero image?')) removeHeroImage.checked = false; });
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>

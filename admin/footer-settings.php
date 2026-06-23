<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/site-settings.php';
requireAdmin();

function footerPhoneIsValid(string $value): bool
{
    return $value !== '' && strlen($value) <= 40 && preg_match('/^[0-9+()\-\s]+$/', $value) === 1;
}

function footerUploadedPath(string $path): bool
{
    return preg_match('#^uploads/site/footer/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#', $path) === 1;
}

function prepareFooterUpload(string $field): array
{
    $upload = $_FILES[$field] ?? null;
    $result = ['present' => false, 'error' => '', 'relative' => '', 'absolute' => '', 'temporary' => ''];
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $result;
    }

    $result['present'] = true;
    $uploadError = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        || (int) ($upload['size'] ?? 0) > 2 * 1024 * 1024) {
        $result['error'] = 'must be 2 MB or smaller.';
        return $result;
    }
    if ($uploadError !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
        $result['error'] = 'could not be verified or uploaded.';
        return $result;
    }

    $mimeMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string) $upload['tmp_name']);
    if (!is_string($mime) || !isset($mimeMap[$mime]) || @getimagesize((string) $upload['tmp_name']) === false) {
        $result['error'] = 'must be a valid JPG, JPEG, PNG, or WebP image.';
        return $result;
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $mimeMap[$mime];
    $result['relative'] = 'uploads/site/footer/' . $filename;
    $result['absolute'] = dirname(__DIR__) . '/' . $result['relative'];
    $result['temporary'] = (string) $upload['tmp_name'];
    return $result;
}

function saveFooterSettings(PDO $pdo, array $settings, array $types): void
{
    $find = $pdo->prepare('SELECT id FROM site_settings WHERE setting_group = :group_name AND setting_key = :setting_key LIMIT 1');
    $update = $pdo->prepare('UPDATE site_settings SET setting_value = :setting_value, setting_type = :setting_type, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $insert = $pdo->prepare('INSERT INTO site_settings (setting_group, setting_key, setting_value, setting_type, created_at, updated_at) VALUES (:group_name, :setting_key, :setting_value, :setting_type, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');

    foreach ($settings as $key => $value) {
        $find->execute([':group_name' => 'footer', ':setting_key' => $key]);
        $id = $find->fetchColumn();
        if ($id !== false) {
            $update->execute([':setting_value' => $value, ':setting_type' => $types[$key] ?? 'text', ':id' => (int) $id]);
        } else {
            $insert->execute([':group_name' => 'footer', ':setting_key' => $key, ':setting_value' => $value, ':setting_type' => $types[$key] ?? 'text']);
        }
    }
}

$defaults = footerSettingDefaults();
$savedSettings = getFooterSettings();
$form = $savedSettings;
$errors = [];
$success = isset($_GET['saved']) && $_GET['saved'] === '1';
$booleanKeys = ['footer_show_social', 'footer_show_map', 'footer_show_cta', 'footer_show_back_to_top'];
$imageKeys = ['footer_logo', 'footer_cta_background'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($defaults) as $key) {
        if (in_array($key, $booleanKeys, true)) {
            $form[$key] = isset($_POST[$key]) ? '1' : '0';
        } elseif (!in_array($key, $imageKeys, true)) {
            $form[$key] = trim((string) ($_POST[$key] ?? ''));
        }
    }

    if (!csrfIsValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    }
    $requiredLimits = [
        'footer_logo_alt' => ['Footer logo alternative text', 2, 160],
        'footer_description' => ['Company description', 20, 800],
        'footer_address' => ['Office address', 5, 500],
        'footer_facebook_name' => ['Facebook display name', 2, 100],
        'footer_cta_heading' => ['CTA heading', 5, 180],
        'footer_cta_description' => ['CTA description', 10, 500],
        'footer_cta_consultation_text' => ['Consultation button text', 2, 60],
        'footer_cta_call_text' => ['Call button text', 2, 60],
        'footer_cta_whatsapp_text' => ['WhatsApp button text', 2, 60],
        'footer_copyright' => ['Copyright text', 5, 240],
    ];
    foreach ($requiredLimits as $key => [$label, $minimum, $maximum]) {
        $length = mb_strlen($form[$key]);
        if ($length < $minimum || $length > $maximum) {
            $errors[] = $label . " must be between {$minimum} and {$maximum} characters.";
        }
    }
    if (!footerPhoneIsValid($form['footer_phone'])) $errors[] = 'Enter a valid footer phone number.';
    if (!footerPhoneIsValid($form['footer_whatsapp'])) $errors[] = 'Enter a valid footer WhatsApp number.';
    if (!filter_var($form['footer_email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['footer_email']) > 190) $errors[] = 'Enter a valid footer email address.';

    foreach (['footer_facebook_url' => 'Facebook', 'footer_youtube_url' => 'YouTube', 'footer_instagram_url' => 'Instagram'] as $key => $label) {
        if ($form[$key] !== '' && safeExternalUrl($form[$key]) === '') $errors[] = $label . ' URL must be a complete http:// or https:// address.';
    }
    foreach (['footer_privacy_url' => 'Privacy Policy', 'footer_terms_url' => 'Terms and Conditions', 'footer_sitemap_url' => 'Sitemap', 'footer_cta_consultation_url' => 'CTA consultation'] as $key => $label) {
        if (safePageUrl($form[$key], '') === '') $errors[] = $label . ' link must be a valid URL, site path, or #section link.';
    }
    if ($form['footer_map_embed'] !== '' && safeGoogleMapSource($form['footer_map_embed']) === '') {
        $errors[] = 'Google Map must be a safe Google Maps HTTPS iframe or embed URL. Scripts and other domains are not allowed.';
    }

    $logoUpload = prepareFooterUpload('footer_logo_upload');
    $backgroundUpload = prepareFooterUpload('footer_cta_background_upload');
    if ($logoUpload['error'] !== '') $errors[] = 'Footer logo ' . $logoUpload['error'];
    if ($backgroundUpload['error'] !== '') $errors[] = 'CTA background ' . $backgroundUpload['error'];

    $oldLogo = $savedSettings['footer_logo'];
    $oldBackground = $savedSettings['footer_cta_background'];
    $movedFiles = [];

    if ($errors === []) {
        foreach ([['upload' => $logoUpload, 'key' => 'footer_logo'], ['upload' => $backgroundUpload, 'key' => 'footer_cta_background']] as $item) {
            if ($item['upload']['present']) {
                if (!move_uploaded_file($item['upload']['temporary'], $item['upload']['absolute'])) {
                    $errors[] = 'An uploaded footer image could not be saved. Existing images were kept.';
                    break;
                }
                $movedFiles[] = $item['upload']['absolute'];
                $form[$item['key']] = $item['upload']['relative'];
            }
        }
    }

    if ($errors === []) {
        if (!$logoUpload['present']) $form['footer_logo'] = isset($_POST['remove_footer_logo']) ? '' : $oldLogo;
        if (!$backgroundUpload['present']) $form['footer_cta_background'] = isset($_POST['remove_cta_background']) ? '' : $oldBackground;

        $types = array_fill_keys(array_keys($form), 'text');
        foreach ($booleanKeys as $key) $types[$key] = 'boolean';
        foreach ($imageKeys as $key) $types[$key] = 'image';
        foreach (['footer_facebook_url', 'footer_youtube_url', 'footer_instagram_url', 'footer_privacy_url', 'footer_terms_url', 'footer_sitemap_url', 'footer_cta_consultation_url'] as $key) $types[$key] = 'url';
        $types['footer_map_embed'] = 'html';

        try {
            $pdo = db();
            $pdo->beginTransaction();
            saveFooterSettings($pdo, $form, $types);
            $pdo->commit();

            foreach ([[$oldLogo, $form['footer_logo']], [$oldBackground, $form['footer_cta_background']]] as [$oldPath, $newPath]) {
                if ($oldPath !== $newPath && footerUploadedPath($oldPath)) {
                    $absolute = dirname(__DIR__) . '/' . $oldPath;
                    if (is_file($absolute)) unlink($absolute);
                }
            }
            header('Location: footer-settings.php?saved=1');
            exit;
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            foreach ($movedFiles as $file) if (is_file($file)) unlink($file);
            error_log($exception->getMessage());
            $errors[] = 'Footer settings could not be saved. Existing settings and images were kept.';
        }
    } else {
        foreach ($movedFiles as $file) if (is_file($file)) unlink($file);
    }
}

$displayLogo = $savedSettings['footer_logo'] ?: getHeaderSettings()['header_logo'];
$displayBackground = $savedSettings['footer_cta_background'];
$pageTitle = 'Footer Settings';
require __DIR__ . '/includes/header.php';
?>
<header class="admin-header"><a class="admin-brand" href="dashboard.php">Design24 Studio Admin</a><div class="admin-user"><span>Signed in as <?= e(currentAdminName()) ?></span><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><button class="logout-button" type="submit">Logout</button></form></div></header>
<main class="admin-main">
    <div class="settings-toolbar"><a href="dashboard.php">← Back to Dashboard</a><a href="../" target="_blank" rel="noopener">View Website ↗</a></div>
    <section class="panel" aria-labelledby="footer-settings-title"><h1 id="footer-settings-title">Footer Settings</h1><p>Manage the public consultation strip and footer.</p>
        <?php if ($success): ?><p class="success" role="status">Footer settings saved successfully.</p><?php endif; ?>
        <?php if ($errors !== []): ?><div class="error" role="alert"><strong>Please correct the following:</strong><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" action="footer-settings.php" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <section class="settings-section"><h2>Footer Branding</h2><div class="settings-grid"><div class="field"><label for="footer_logo_alt">Logo alternative text *</label><input id="footer_logo_alt" name="footer_logo_alt" type="text" maxlength="160" value="<?= e($form['footer_logo_alt']) ?>" required></div></div><div class="field"><label for="footer_description">Company short description *</label><textarea id="footer_description" name="footer_description" maxlength="800" required><?= e($form['footer_description']) ?></textarea></div><div class="field"><label for="footer_logo_upload">Upload footer logo</label><input id="footer_logo_upload" name="footer_logo_upload" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><small class="help">JPG, JPEG, PNG, or WebP. Maximum 2 MB.</small><strong class="help">Current logo</strong><img class="logo-preview" src="../<?= e($displayLogo) ?>" alt="Current footer logo"><img class="logo-preview" id="footer-logo-preview" alt="Selected footer logo preview" hidden></div><?php if ($savedSettings['footer_logo'] !== ''): ?><div class="checkbox-field"><input id="remove_footer_logo" name="remove_footer_logo" type="checkbox" value="1"><label for="remove_footer_logo">Remove uploaded footer logo and use the header logo</label></div><?php endif; ?></section>

            <section class="settings-section"><h2>Contact Information</h2><div class="field"><label for="footer_address">Full office address *</label><textarea id="footer_address" name="footer_address" maxlength="500" required><?= e($form['footer_address']) ?></textarea></div><div class="settings-grid"><div class="field"><label for="footer_phone">Phone number *</label><input id="footer_phone" name="footer_phone" type="text" maxlength="40" value="<?= e($form['footer_phone']) ?>" required></div><div class="field"><label for="footer_whatsapp">WhatsApp number *</label><input id="footer_whatsapp" name="footer_whatsapp" type="text" maxlength="40" value="<?= e($form['footer_whatsapp']) ?>" required></div><div class="field"><label for="footer_email">Email address *</label><input id="footer_email" name="footer_email" type="email" maxlength="190" value="<?= e($form['footer_email']) ?>" required></div><div class="field"><label for="footer_facebook_name">Facebook display name *</label><input id="footer_facebook_name" name="footer_facebook_name" type="text" maxlength="100" value="<?= e($form['footer_facebook_name']) ?>" required></div></div></section>

            <section class="settings-section"><h2>Social Media</h2><div class="settings-grid"><div class="field"><label for="footer_facebook_url">Facebook URL</label><input id="footer_facebook_url" name="footer_facebook_url" type="url" maxlength="500" value="<?= e($form['footer_facebook_url']) ?>"></div><div class="field"><label for="footer_youtube_url">YouTube URL</label><input id="footer_youtube_url" name="footer_youtube_url" type="url" maxlength="500" value="<?= e($form['footer_youtube_url']) ?>"></div><div class="field"><label for="footer_instagram_url">Instagram URL</label><input id="footer_instagram_url" name="footer_instagram_url" type="url" maxlength="500" value="<?= e($form['footer_instagram_url']) ?>"></div></div><div class="checkbox-field"><input id="footer_show_social" name="footer_show_social" type="checkbox" value="1" <?= $form['footer_show_social'] === '1' ? 'checked' : '' ?>><label for="footer_show_social">Show social icons with saved links</label></div></section>

            <section class="settings-section"><h2>Footer Links</h2><div class="settings-grid"><div class="field"><label for="footer_privacy_url">Privacy Policy URL *</label><input id="footer_privacy_url" name="footer_privacy_url" type="text" maxlength="500" value="<?= e($form['footer_privacy_url']) ?>" required></div><div class="field"><label for="footer_terms_url">Terms and Conditions URL *</label><input id="footer_terms_url" name="footer_terms_url" type="text" maxlength="500" value="<?= e($form['footer_terms_url']) ?>" required></div><div class="field"><label for="footer_sitemap_url">Sitemap URL *</label><input id="footer_sitemap_url" name="footer_sitemap_url" type="text" maxlength="500" value="<?= e($form['footer_sitemap_url']) ?>" required></div></div></section>

            <section class="settings-section"><h2>Google Map</h2><div class="field"><label for="footer_map_embed">Google Map embed code</label><textarea id="footer_map_embed" name="footer_map_embed" placeholder="Paste a Google Maps HTTPS iframe or embed URL only"><?= e($form['footer_map_embed']) ?></textarea><small class="help">Only a safe Google Maps iframe or HTTPS maps URL is accepted. Other HTML and scripts are rejected.</small></div><div class="checkbox-field"><input id="footer_show_map" name="footer_show_map" type="checkbox" value="1" <?= $form['footer_show_map'] === '1' ? 'checked' : '' ?>><label for="footer_show_map">Show Google Map</label></div></section>

            <section class="settings-section"><h2>Footer Call-to-Action</h2><div class="field"><label for="footer_cta_heading">CTA heading *</label><input id="footer_cta_heading" name="footer_cta_heading" type="text" maxlength="180" value="<?= e($form['footer_cta_heading']) ?>" required></div><div class="field"><label for="footer_cta_description">CTA description *</label><textarea id="footer_cta_description" name="footer_cta_description" maxlength="500" required><?= e($form['footer_cta_description']) ?></textarea></div><div class="settings-grid"><div class="field"><label for="footer_cta_consultation_text">Consultation button text *</label><input id="footer_cta_consultation_text" name="footer_cta_consultation_text" type="text" maxlength="60" value="<?= e($form['footer_cta_consultation_text']) ?>" required></div><div class="field"><label for="footer_cta_consultation_url">Consultation button link *</label><input id="footer_cta_consultation_url" name="footer_cta_consultation_url" type="text" maxlength="500" value="<?= e($form['footer_cta_consultation_url']) ?>" required></div><div class="field"><label for="footer_cta_call_text">Call button text *</label><input id="footer_cta_call_text" name="footer_cta_call_text" type="text" maxlength="60" value="<?= e($form['footer_cta_call_text']) ?>" required></div><div class="field"><label for="footer_cta_whatsapp_text">WhatsApp button text *</label><input id="footer_cta_whatsapp_text" name="footer_cta_whatsapp_text" type="text" maxlength="60" value="<?= e($form['footer_cta_whatsapp_text']) ?>" required></div></div><div class="field"><label for="footer_cta_background_upload">CTA background image</label><input id="footer_cta_background_upload" name="footer_cta_background_upload" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><small class="help">JPG, JPEG, PNG, or WebP. Maximum 2 MB.</small><?php if ($displayBackground !== ''): ?><img class="logo-preview" src="../<?= e($displayBackground) ?>" alt="Current CTA background"><?php endif; ?><img class="logo-preview" id="cta-background-preview" alt="Selected CTA background preview" hidden></div><?php if ($savedSettings['footer_cta_background'] !== ''): ?><div class="checkbox-field"><input id="remove_cta_background" name="remove_cta_background" type="checkbox" value="1"><label for="remove_cta_background">Remove the current CTA background image</label></div><?php endif; ?><div class="checkbox-field"><input id="footer_show_cta" name="footer_show_cta" type="checkbox" value="1" <?= $form['footer_show_cta'] === '1' ? 'checked' : '' ?>><label for="footer_show_cta">Show CTA section</label></div></section>

            <section class="settings-section"><h2>Footer Behaviour</h2><div class="field"><label for="footer_copyright">Copyright text *</label><input id="footer_copyright" name="footer_copyright" type="text" maxlength="240" value="<?= e($form['footer_copyright']) ?>" required></div><div class="checkbox-field"><input id="footer_show_back_to_top" name="footer_show_back_to_top" type="checkbox" value="1" <?= $form['footer_show_back_to_top'] === '1' ? 'checked' : '' ?>><label for="footer_show_back_to_top">Show back-to-top button</label></div></section>
            <div class="form-actions"><button class="primary-button" type="submit">Save Footer Settings</button><a class="secondary-admin-button" href="footer-settings.php">Cancel / Reset</a></div>
        </form>
    </section>
</main>
<script>
function attachImagePreview(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    input.addEventListener('change', () => {
        const file = input.files[0];
        if (!file) { preview.hidden = true; preview.removeAttribute('src'); return; }
        preview.src = URL.createObjectURL(file); preview.hidden = false;
    });
}
attachImagePreview('footer_logo_upload', 'footer-logo-preview');
attachImagePreview('footer_cta_background_upload', 'cta-background-preview');
document.querySelectorAll('#remove_footer_logo, #remove_cta_background').forEach((box) => box.addEventListener('change', () => { if (box.checked && !window.confirm('Remove this uploaded image?')) box.checked = false; }));
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>

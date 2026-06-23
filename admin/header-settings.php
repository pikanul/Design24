<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/site-settings.php';
requireAdmin();

function validPhone(string $value): bool
{
    return $value !== '' && strlen($value) <= 40 && preg_match('/^[0-9+()\-\s]+$/', $value) === 1;
}

function validOptionalExternalUrl(string $value): bool
{
    return $value === '' || safeExternalUrl($value) !== '';
}

function validPageLink(string $value): bool
{
    return $value !== '' && safePageUrl($value, '') !== '';
}

function uploadedLogoPath(string $path): bool
{
    return preg_match('#^uploads/site/logo/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#', $path) === 1;
}

function saveHeaderSettings(PDO $pdo, array $settings, array $types): void
{
    $find = $pdo->prepare(
        'SELECT id FROM site_settings WHERE setting_group = :setting_group AND setting_key = :setting_key LIMIT 1'
    );
    $update = $pdo->prepare(
        'UPDATE site_settings SET setting_value = :setting_value, setting_type = :setting_type,
         updated_at = CURRENT_TIMESTAMP WHERE id = :id'
    );
    $insert = $pdo->prepare(
        'INSERT INTO site_settings (setting_group, setting_key, setting_value, setting_type, created_at, updated_at)
         VALUES (:setting_group, :setting_key, :setting_value, :setting_type, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
    );

    foreach ($settings as $key => $value) {
        $find->execute([':setting_group' => 'header', ':setting_key' => $key]);
        $id = $find->fetchColumn();

        if ($id !== false) {
            $update->execute([
                ':setting_value' => $value,
                ':setting_type' => $types[$key] ?? 'text',
                ':id' => (int) $id,
            ]);
        } else {
            $insert->execute([
                ':setting_group' => 'header',
                ':setting_key' => $key,
                ':setting_value' => $value,
                ':setting_type' => $types[$key] ?? 'text',
            ]);
        }
    }
}

$defaults = headerSettingDefaults();
$savedSettings = getHeaderSettings();
$form = $savedSettings;
$errors = [];
$success = isset($_GET['saved']) && $_GET['saved'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($defaults) as $key) {
        if (str_starts_with($key, 'show_') || in_array($key, ['sticky_header', 'header_scroll_shadow'], true)) {
            $form[$key] = isset($_POST[$key]) ? '1' : '0';
        } elseif ($key !== 'header_logo') {
            $form[$key] = trim((string) ($_POST[$key] ?? ''));
        }
    }

    $csrf = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null;
    if (!csrfIsValid($csrf)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    }
    if (mb_strlen($form['website_name']) < 2 || mb_strlen($form['website_name']) > 100) {
        $errors[] = 'Website name must be between 2 and 100 characters.';
    }
    if (mb_strlen($form['website_tagline']) > 160) {
        $errors[] = 'Website tagline must not exceed 160 characters.';
    }
    if (mb_strlen($form['logo_alt']) < 2 || mb_strlen($form['logo_alt']) > 160) {
        $errors[] = 'Logo alternative text must be between 2 and 160 characters.';
    }
    $logoMaxWidth = filter_var($form['logo_max_width'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 100, 'max_range' => 500]]);
    if ($logoMaxWidth === false) {
        $errors[] = 'Logo maximum width must be between 100 and 500 pixels.';
    } else {
        $form['logo_max_width'] = (string) $logoMaxWidth;
    }
    if (!validPhone($form['phone'])) {
        $errors[] = 'Enter a valid phone number.';
    }
    if (!validPhone($form['whatsapp'])) {
        $errors[] = 'Enter a valid WhatsApp number.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 190) {
        $errors[] = 'Enter a valid email address.';
    }
    if (mb_strlen($form['short_location']) < 2 || mb_strlen($form['short_location']) > 160) {
        $errors[] = 'Short location must be between 2 and 160 characters.';
    }
    foreach (['facebook_url' => 'Facebook', 'youtube_url' => 'YouTube', 'instagram_url' => 'Instagram'] as $key => $label) {
        if (!validOptionalExternalUrl($form[$key])) {
            $errors[] = $label . ' URL must be a complete http:// or https:// address.';
        }
    }
    if (mb_strlen($form['consultation_button_text']) < 2 || mb_strlen($form['consultation_button_text']) > 60) {
        $errors[] = 'Consultation button text must be between 2 and 60 characters.';
    }
    if (!validPageLink($form['consultation_button_url'])) {
        $errors[] = 'Consultation link must be a valid web URL, site path, or #section link.';
    }
    if (mb_strlen($form['whatsapp_button_text']) < 2 || mb_strlen($form['whatsapp_button_text']) > 60) {
        $errors[] = 'WhatsApp button text must be between 2 and 60 characters.';
    }

    $upload = $_FILES['header_logo'] ?? null;
    $hasUpload = is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $newAbsolutePath = '';
    $newRelativePath = '';

    if ($hasUpload) {
        $uploadError = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $errors[] = 'The logo must be 2 MB or smaller.';
        } elseif ($uploadError !== UPLOAD_ERR_OK) {
            $errors[] = 'The logo upload did not complete. Please try again.';
        } elseif ((int) ($upload['size'] ?? 0) > 2 * 1024 * 1024) {
            $errors[] = 'The logo must be 2 MB or smaller.';
        } elseif (!is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
            $errors[] = 'The uploaded logo could not be verified.';
        } else {
            $mimeMap = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file((string) $upload['tmp_name']);
            $imageInfo = @getimagesize((string) $upload['tmp_name']);

            if (!is_string($mime) || !isset($mimeMap[$mime]) || $imageInfo === false) {
                $errors[] = 'Upload a valid JPG, JPEG, PNG, or WebP image.';
            } else {
                $filename = bin2hex(random_bytes(16)) . '.' . $mimeMap[$mime];
                $newRelativePath = 'uploads/site/logo/' . $filename;
                $newAbsolutePath = dirname(__DIR__) . '/' . $newRelativePath;
            }
        }
    }

    $removeLogo = isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1';

    if ($errors === []) {
        $oldLogo = $savedSettings['header_logo'];
        if ($hasUpload && $newAbsolutePath !== '') {
            if (!move_uploaded_file((string) $upload['tmp_name'], $newAbsolutePath)) {
                $errors[] = 'The new logo could not be saved. The current logo was kept.';
            } else {
                $form['header_logo'] = $newRelativePath;
            }
        } elseif ($removeLogo) {
            $form['header_logo'] = $defaults['header_logo'];
        } else {
            $form['header_logo'] = $oldLogo;
        }

        if ($errors === []) {
            $types = array_fill_keys(array_keys($form), 'text');
            foreach (['show_top_bar', 'show_social_icons', 'show_consultation_button', 'show_whatsapp_button', 'sticky_header', 'header_scroll_shadow'] as $key) {
                $types[$key] = 'boolean';
            }
            $types['logo_max_width'] = 'number';
            $types['header_logo'] = 'image';
            foreach (['facebook_url', 'youtube_url', 'instagram_url', 'consultation_button_url'] as $key) {
                $types[$key] = 'url';
            }

            try {
                $pdo = db();
                $pdo->beginTransaction();
                saveHeaderSettings($pdo, $form, $types);
                $pdo->commit();

                if (($hasUpload || $removeLogo) && uploadedLogoPath($oldLogo)) {
                    $oldAbsolutePath = dirname(__DIR__) . '/' . $oldLogo;
                    if ($oldLogo !== $form['header_logo'] && is_file($oldAbsolutePath)) {
                        unlink($oldAbsolutePath);
                    }
                }

                header('Location: header-settings.php?saved=1');
                exit;
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($newAbsolutePath !== '' && is_file($newAbsolutePath)) {
                    unlink($newAbsolutePath);
                }
                error_log($exception->getMessage());
                $errors[] = 'Settings could not be saved. The existing header remains unchanged.';
            }
        }
    }
}

$currentLogo = $savedSettings['header_logo'] ?: $defaults['header_logo'];
$pageTitle = 'Header Settings';
require __DIR__ . '/includes/header.php';
?>
<header class="admin-header">
    <a class="admin-brand" href="dashboard.php">Design24 Studio Admin</a>
    <div class="admin-user">
        <span>Signed in as <?= e(currentAdminName()) ?></span>
        <form method="post" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <button class="logout-button" type="submit">Logout</button>
        </form>
    </div>
</header>

<main class="admin-main">
    <div class="settings-toolbar">
        <a href="dashboard.php">← Back to Dashboard</a>
        <a href="../" target="_blank" rel="noopener">View Website ↗</a>
    </div>

    <section class="panel" aria-labelledby="settings-title">
        <h1 id="settings-title">Header Settings</h1>
        <p>Update the public website header. Required fields are marked with an asterisk.</p>

        <?php if ($success): ?><p class="success" role="status">Header settings saved successfully.</p><?php endif; ?>
        <?php if ($errors !== []): ?>
            <div class="error" role="alert"><strong>Please correct the following:</strong><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="post" action="header-settings.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <section class="settings-section">
                <h2>Branding</h2>
                <div class="settings-grid">
                    <div class="field"><label for="website_name">Website name *</label><input id="website_name" name="website_name" type="text" maxlength="100" value="<?= e($form['website_name']) ?>" required></div>
                    <div class="field"><label for="website_tagline">Website tagline</label><input id="website_tagline" name="website_tagline" type="text" maxlength="160" value="<?= e($form['website_tagline']) ?>"></div>
                    <div class="field"><label for="logo_alt">Logo alternative text *</label><input id="logo_alt" name="logo_alt" type="text" maxlength="160" value="<?= e($form['logo_alt']) ?>" required></div>
                    <div class="field"><label for="logo_max_width">Logo maximum width (px) *</label><input id="logo_max_width" name="logo_max_width" type="number" min="100" max="500" value="<?= e($form['logo_max_width']) ?>" required></div>
                </div>
                <div class="field">
                    <label for="header_logo">Upload header logo</label>
                    <input id="header_logo" name="header_logo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <small class="help">JPG, JPEG, PNG, or WebP. Maximum 2 MB.</small>
                    <strong class="help">Current logo</strong>
                    <img class="logo-preview" src="../<?= e($currentLogo) ?>" alt="Current header logo">
                    <img class="logo-preview" id="new-logo-preview" src="" alt="Selected logo preview" hidden>
                </div>
                <?php if (uploadedLogoPath($currentLogo)): ?>
                    <div class="checkbox-field"><input id="remove_logo" name="remove_logo" type="checkbox" value="1"><label for="remove_logo">Remove uploaded logo and return to the original website logo</label></div>
                <?php endif; ?>
            </section>

            <section class="settings-section">
                <h2>Top Contact Bar</h2>
                <div class="settings-grid">
                    <div class="field"><label for="phone">Phone number *</label><input id="phone" name="phone" type="text" maxlength="40" value="<?= e($form['phone']) ?>" required></div>
                    <div class="field"><label for="whatsapp">WhatsApp number *</label><input id="whatsapp" name="whatsapp" type="text" maxlength="40" value="<?= e($form['whatsapp']) ?>" required></div>
                    <div class="field"><label for="email">Email address *</label><input id="email" name="email" type="email" maxlength="190" value="<?= e($form['email']) ?>" required></div>
                    <div class="field"><label for="short_location">Short location *</label><input id="short_location" name="short_location" type="text" maxlength="160" value="<?= e($form['short_location']) ?>" required></div>
                </div>
                <div class="checkbox-field"><input id="show_top_bar" name="show_top_bar" type="checkbox" value="1" <?= $form['show_top_bar'] === '1' ? 'checked' : '' ?>><label for="show_top_bar">Show top contact bar</label></div>
            </section>

            <section class="settings-section">
                <h2>Social Media</h2>
                <div class="settings-grid">
                    <div class="field"><label for="facebook_url">Facebook URL</label><input id="facebook_url" name="facebook_url" type="url" maxlength="500" value="<?= e($form['facebook_url']) ?>" placeholder="https://facebook.com/..."></div>
                    <div class="field"><label for="youtube_url">YouTube URL</label><input id="youtube_url" name="youtube_url" type="url" maxlength="500" value="<?= e($form['youtube_url']) ?>" placeholder="https://youtube.com/..."></div>
                    <div class="field"><label for="instagram_url">Instagram URL</label><input id="instagram_url" name="instagram_url" type="url" maxlength="500" value="<?= e($form['instagram_url']) ?>" placeholder="https://instagram.com/..."></div>
                </div>
                <div class="checkbox-field"><input id="show_social_icons" name="show_social_icons" type="checkbox" value="1" <?= $form['show_social_icons'] === '1' ? 'checked' : '' ?>><label for="show_social_icons">Show social-media icons when URLs are saved</label></div>
            </section>

            <section class="settings-section">
                <h2>Header Buttons</h2>
                <div class="settings-grid">
                    <div class="field"><label for="consultation_button_text">Consultation button text *</label><input id="consultation_button_text" name="consultation_button_text" type="text" maxlength="60" value="<?= e($form['consultation_button_text']) ?>" required></div>
                    <div class="field"><label for="consultation_button_url">Consultation button link *</label><input id="consultation_button_url" name="consultation_button_url" type="text" maxlength="500" value="<?= e($form['consultation_button_url']) ?>" required></div>
                    <div class="field"><label for="whatsapp_button_text">WhatsApp button text *</label><input id="whatsapp_button_text" name="whatsapp_button_text" type="text" maxlength="60" value="<?= e($form['whatsapp_button_text']) ?>" required></div>
                </div>
                <div class="checkbox-field"><input id="show_consultation_button" name="show_consultation_button" type="checkbox" value="1" <?= $form['show_consultation_button'] === '1' ? 'checked' : '' ?>><label for="show_consultation_button">Show consultation button</label></div>
                <div class="checkbox-field"><input id="show_whatsapp_button" name="show_whatsapp_button" type="checkbox" value="1" <?= $form['show_whatsapp_button'] === '1' ? 'checked' : '' ?>><label for="show_whatsapp_button">Show WhatsApp button</label></div>
            </section>

            <section class="settings-section">
                <h2>Header Behaviour</h2>
                <div class="checkbox-field"><input id="sticky_header" name="sticky_header" type="checkbox" value="1" <?= $form['sticky_header'] === '1' ? 'checked' : '' ?>><label for="sticky_header">Keep the main header visible while scrolling</label></div>
                <div class="checkbox-field"><input id="header_scroll_shadow" name="header_scroll_shadow" type="checkbox" value="1" <?= $form['header_scroll_shadow'] === '1' ? 'checked' : '' ?>><label for="header_scroll_shadow">Show a soft header shadow while scrolling</label></div>
            </section>

            <div class="form-actions">
                <button class="primary-button" type="submit">Save Settings</button>
                <a class="secondary-admin-button" href="header-settings.php">Cancel / Reset</a>
            </div>
        </form>
    </section>
</main>
<script>
const logoInput = document.querySelector('#header_logo');
const logoPreview = document.querySelector('#new-logo-preview');
const removeLogo = document.querySelector('#remove_logo');

logoInput.addEventListener('change', () => {
    const file = logoInput.files[0];
    if (!file) {
        logoPreview.hidden = true;
        logoPreview.removeAttribute('src');
        return;
    }
    logoPreview.src = URL.createObjectURL(file);
    logoPreview.hidden = false;
});

if (removeLogo) {
    removeLogo.addEventListener('change', () => {
        if (removeLogo.checked && !window.confirm('Remove the uploaded logo and restore the original website logo?')) {
            removeLogo.checked = false;
        }
    });
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>

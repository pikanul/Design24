<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/site-settings.php';
requireAdmin();

$pdo = db();
$allowedTabs = ['main', 'slider', 'features', 'counters'];
$tab = in_array($_GET['tab'] ?? '', $allowedTabs, true) ? (string) $_GET['tab'] : 'main';
$errors = [];
$success = isset($_GET['saved']) && $_GET['saved'] === '1';

function aboutAdminImageIsSafe(string $path): bool
{
    return preg_match('#^public/uploads/about/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#', $path) === 1;
}

function aboutAdminDeleteImage(string $path): void
{
    if (!aboutAdminImageIsSafe($path)) return;
    $absolute = dirname(__DIR__) . '/' . $path;
    if (is_file($absolute)) unlink($absolute);
}

function aboutAdminUpload(string $field): array
{
    $upload = $_FILES[$field] ?? null;
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['present' => false, 'path' => '', 'error' => ''];
    }
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || (int) ($upload['size'] ?? 0) > 5 * 1024 * 1024
        || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
        return ['present' => true, 'path' => '', 'error' => 'Image must be a verified file of 5 MB or smaller.'];
    }
    $mimeMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']);
    if (!is_string($mime) || !isset($mimeMap[$mime]) || @getimagesize((string) $upload['tmp_name']) === false) {
        return ['present' => true, 'path' => '', 'error' => 'Upload a valid JPG, JPEG, PNG, or WebP image.'];
    }
    $relative = 'public/uploads/about/' . bin2hex(random_bytes(16)) . '.' . $mimeMap[$mime];
    $absolute = dirname(__DIR__) . '/' . $relative;
    if (!move_uploaded_file((string) $upload['tmp_name'], $absolute)) {
        return ['present' => true, 'path' => '', 'error' => 'The image could not be saved.'];
    }
    return ['present' => true, 'path' => $relative, 'error' => ''];
}

function aboutAdminLinkIsValid(string $link): bool
{
    if ($link === '') return true;
    if ($link[0] === '#') return preg_match('/^#[a-zA-Z][a-zA-Z0-9_-]*$/', $link) === 1;
    if ($link[0] === '/') return preg_match('#^/[a-zA-Z0-9_./?#=&%-]*$#', $link) === 1;
    return safeExternalUrl($link) !== '' || preg_match('#^[a-zA-Z0-9][a-zA-Z0-9_./?#=&%-]*$#', $link) === 1;
}

function aboutAdminRedirect(string $tab): void
{
    header('Location: about-settings.php?tab=' . rawurlencode($tab) . '&saved=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $postedTab = in_array($_POST['tab'] ?? '', $allowedTabs, true) ? (string) $_POST['tab'] : $tab;
    $tab = $postedTab;

    if (!csrfIsValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } else {
        try {
            if ($action === 'save_main') {
                $fields = [];
                foreach (['subtitle', 'heading', 'description', 'button_one_text', 'button_one_link', 'button_two_text', 'button_two_link'] as $field) {
                    $fields[$field] = trim((string) ($_POST[$field] ?? ''));
                }
                if (mb_strlen($fields['subtitle']) < 2 || mb_strlen($fields['subtitle']) > 120) $errors[] = 'Subtitle must be between 2 and 120 characters.';
                if (mb_strlen($fields['heading']) < 4 || mb_strlen($fields['heading']) > 220) $errors[] = 'Heading must be between 4 and 220 characters.';
                if (mb_strlen($fields['description']) < 20 || mb_strlen($fields['description']) > 2000) $errors[] = 'Description must be between 20 and 2000 characters.';
                foreach (['button_one_text', 'button_two_text'] as $field) if (mb_strlen($fields[$field]) > 100) $errors[] = 'Button text must not exceed 100 characters.';
                foreach (['button_one_link', 'button_two_link'] as $field) if (!aboutAdminLinkIsValid($fields[$field])) $errors[] = 'Enter a valid button link, such as #contact or https://example.com.';
                if ($errors === []) {
                    $id = (int) $pdo->query('SELECT id FROM about_settings ORDER BY id LIMIT 1')->fetchColumn();
                    $fields['status'] = isset($_POST['status']) ? 1 : 0;
                    if ($id > 0) {
                        $fields['id'] = $id;
                        $statement = $pdo->prepare('UPDATE about_settings SET subtitle=:subtitle,heading=:heading,description=:description,button_one_text=:button_one_text,button_one_link=:button_one_link,button_two_text=:button_two_text,button_two_link=:button_two_link,status=:status,updated_at=CURRENT_TIMESTAMP WHERE id=:id');
                    } else {
                        $statement = $pdo->prepare('INSERT INTO about_settings (subtitle,heading,description,button_one_text,button_one_link,button_two_text,button_two_link,status) VALUES (:subtitle,:heading,:description,:button_one_text,:button_one_link,:button_two_text,:button_two_link,:status)');
                    }
                    $statement->execute($fields);
                    aboutAdminRedirect('main');
                }
            }

            if ($action === 'save_slider') {
                $id = max(0, (int) ($_POST['id'] ?? 0));
                $title = trim((string) ($_POST['title'] ?? ''));
                $sortOrder = max(0, min(9999, (int) ($_POST['sort_order'] ?? 0)));
                if (mb_strlen($title) > 180) $errors[] = 'Image title must not exceed 180 characters.';
                $upload = aboutAdminUpload('image');
                if ($upload['error'] !== '') $errors[] = $upload['error'];
                $oldImage = '';
                if ($id > 0) {
                    $find = $pdo->prepare('SELECT image FROM about_slider_images WHERE id=:id');
                    $find->execute([':id' => $id]);
                    $oldImage = (string) $find->fetchColumn();
                    if ($oldImage === '') $errors[] = 'Slider image was not found.';
                } elseif (!$upload['present']) {
                    $errors[] = 'Choose an image to upload.';
                }
                if ($errors === []) {
                    $image = $upload['present'] ? $upload['path'] : $oldImage;
                    $data = [':image' => $image, ':title' => $title, ':sort_order' => $sortOrder, ':status' => isset($_POST['status']) ? 1 : 0];
                    if ($id > 0) {
                        $data[':id'] = $id;
                        $statement = $pdo->prepare('UPDATE about_slider_images SET image=:image,title=:title,sort_order=:sort_order,status=:status,updated_at=CURRENT_TIMESTAMP WHERE id=:id');
                    } else {
                        $statement = $pdo->prepare('INSERT INTO about_slider_images (image,title,sort_order,status) VALUES (:image,:title,:sort_order,:status)');
                    }
                    $statement->execute($data);
                    if ($upload['present'] && $oldImage !== '' && $oldImage !== $image) aboutAdminDeleteImage($oldImage);
                    aboutAdminRedirect('slider');
                } elseif ($upload['present'] && $upload['path'] !== '') {
                    aboutAdminDeleteImage($upload['path']);
                }
            }

            if ($action === 'delete_slider') {
                $id = max(1, (int) ($_POST['id'] ?? 0));
                $find = $pdo->prepare('SELECT image FROM about_slider_images WHERE id=:id');
                $find->execute([':id' => $id]);
                $image = (string) $find->fetchColumn();
                $pdo->prepare('DELETE FROM about_slider_images WHERE id=:id')->execute([':id' => $id]);
                aboutAdminDeleteImage($image);
                aboutAdminRedirect('slider');
            }

            if ($action === 'save_feature') {
                $id = max(0, (int) ($_POST['id'] ?? 0));
                $icon = preg_replace('/[^a-z0-9_-]/i', '', trim((string) ($_POST['icon'] ?? '')));
                $title = trim((string) ($_POST['title'] ?? ''));
                if ($icon === '' || mb_strlen($icon) > 80) $errors[] = 'Enter an icon keyword of 80 characters or fewer.';
                if ($title === '' || mb_strlen($title) > 180) $errors[] = 'Feature title is required and must not exceed 180 characters.';
                if ($errors === []) {
                    $data = [':icon' => $icon, ':title' => $title, ':sort_order' => max(0, min(9999, (int) ($_POST['sort_order'] ?? 0))), ':status' => isset($_POST['status']) ? 1 : 0];
                    if ($id > 0) {
                        $data[':id'] = $id;
                        $statement = $pdo->prepare('UPDATE about_features SET icon=:icon,title=:title,sort_order=:sort_order,status=:status,updated_at=CURRENT_TIMESTAMP WHERE id=:id');
                    } else $statement = $pdo->prepare('INSERT INTO about_features (icon,title,sort_order,status) VALUES (:icon,:title,:sort_order,:status)');
                    $statement->execute($data);
                    aboutAdminRedirect('features');
                }
            }

            if ($action === 'delete_feature') {
                $pdo->prepare('DELETE FROM about_features WHERE id=:id')->execute([':id' => max(1, (int) ($_POST['id'] ?? 0))]);
                aboutAdminRedirect('features');
            }

            if ($action === 'save_counter') {
                $id = max(0, (int) ($_POST['id'] ?? 0));
                $icon = preg_replace('/[^a-z0-9_-]/i', '', trim((string) ($_POST['icon'] ?? '')));
                $number = filter_var($_POST['number'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999999999]]);
                $suffix = trim((string) ($_POST['suffix'] ?? ''));
                $title = trim((string) ($_POST['title'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                if ($icon === '' || mb_strlen($icon) > 80) $errors[] = 'Enter an icon keyword of 80 characters or fewer.';
                if ($number === false) $errors[] = 'Counter number must be between 0 and 999,999,999.';
                if (mb_strlen($suffix) > 20) $errors[] = 'Counter suffix must not exceed 20 characters.';
                if ($title === '' || mb_strlen($title) > 180) $errors[] = 'Counter title is required and must not exceed 180 characters.';
                if (mb_strlen($description) > 300) $errors[] = 'Counter description must not exceed 300 characters.';
                if ($errors === []) {
                    $data = [':icon' => $icon, ':number' => (int) $number, ':suffix' => $suffix, ':title' => $title, ':description' => $description, ':sort_order' => max(0, min(9999, (int) ($_POST['sort_order'] ?? 0))), ':status' => isset($_POST['status']) ? 1 : 0];
                    if ($id > 0) {
                        $data[':id'] = $id;
                        $statement = $pdo->prepare('UPDATE about_counters SET icon=:icon,number=:number,suffix=:suffix,title=:title,description=:description,sort_order=:sort_order,status=:status,updated_at=CURRENT_TIMESTAMP WHERE id=:id');
                    } else $statement = $pdo->prepare('INSERT INTO about_counters (icon,number,suffix,title,description,sort_order,status) VALUES (:icon,:number,:suffix,:title,:description,:sort_order,:status)');
                    $statement->execute($data);
                    aboutAdminRedirect('counters');
                }
            }

            if ($action === 'delete_counter') {
                $pdo->prepare('DELETE FROM about_counters WHERE id=:id')->execute([':id' => max(1, (int) ($_POST['id'] ?? 0))]);
                aboutAdminRedirect('counters');
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'The About Us settings could not be saved. Please try again.';
        }
    }
}

$main = $pdo->query('SELECT * FROM about_settings ORDER BY id LIMIT 1')->fetch() ?: [
    'subtitle' => '', 'heading' => '', 'description' => '', 'button_one_text' => '', 'button_one_link' => '',
    'button_two_text' => '', 'button_two_link' => '', 'status' => 1,
];
$sliderImages = $pdo->query('SELECT * FROM about_slider_images ORDER BY sort_order,id')->fetchAll();
$features = $pdo->query('SELECT * FROM about_features ORDER BY sort_order,id')->fetchAll();
$counters = $pdo->query('SELECT * FROM about_counters ORDER BY sort_order,id')->fetchAll();
$pageTitle = 'About Us Settings';
require __DIR__ . '/includes/header.php';
?>
<style>
.about-admin-tabs{display:flex;margin:24px 0;gap:8px;overflow-x:auto}.about-admin-tabs a{flex:0 0 auto;padding:10px 15px;border:1px solid var(--line);border-radius:5px;background:#fff;color:var(--green);font-weight:700;text-decoration:none}.about-admin-tabs a.active{border-color:var(--green);background:var(--green);color:#fff}.about-admin-list{display:grid;gap:18px}.about-admin-card{padding:20px;border:1px solid var(--line);border-radius:7px;background:#fcfdfc}.about-admin-card-grid{display:grid;grid-template-columns:150px 1fr;gap:20px}.about-admin-preview{width:150px;aspect-ratio:3/2;object-fit:cover;border-radius:6px;background:#edf2ef}.about-admin-inline{display:grid;grid-template-columns:2fr 1fr;gap:14px}.about-admin-actions{display:flex;margin-top:18px;align-items:center;gap:10px;flex-wrap:wrap}.about-admin-actions .primary-button{width:auto;margin:0;padding:0 20px}.about-delete{min-height:44px;padding:0 16px;border:1px solid #a12626;border-radius:4px;background:#fff;color:#8b2020;cursor:pointer}.about-new-card{margin-bottom:24px;border-color:#b5c9c0;background:#f2f8f5}.about-image-preview{display:none;width:220px;aspect-ratio:3/2;margin-top:12px;object-fit:cover;border-radius:6px}@media(max-width:700px){.about-admin-card-grid,.about-admin-inline{grid-template-columns:1fr}.about-admin-preview{width:100%;max-width:280px}}
</style>
<header class="admin-header"><a class="admin-brand" href="dashboard.php">Design24 Studio Admin</a><div class="admin-user"><span>Signed in as <?= e(currentAdminName()) ?></span><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><button class="logout-button" type="submit">Logout</button></form></div></header>
<main class="admin-main">
    <div class="settings-toolbar"><a href="dashboard.php">← Dashboard</a><a href="../index.php#about" target="_blank" rel="noopener">View About Us ↗</a></div>
    <section class="panel">
        <h1>About Us Settings</h1><p>Manage the homepage About content, carousel, features, and animated statistics.</p>
        <?php if ($success): ?><p class="success">About Us settings saved successfully.</p><?php endif; ?>
        <?php if ($errors !== []): ?><div class="error"><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <nav class="about-admin-tabs" aria-label="About Us settings sections">
            <?php foreach (['main'=>'Main Content','slider'=>'Slider Images','features'=>'Feature Icons','counters'=>'Counter Section'] as $key=>$label): ?><a class="<?= $tab===$key?'active':'' ?>" href="?tab=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach; ?>
        </nav>

        <?php if ($tab === 'main'): ?>
        <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="save_main"><input type="hidden" name="tab" value="main">
            <div class="settings-grid"><div class="field"><label for="subtitle">About Us subtitle</label><input id="subtitle" name="subtitle" value="<?= e($main['subtitle']) ?>" required maxlength="120"></div><div class="field"><label for="heading">Main heading</label><input id="heading" name="heading" value="<?= e($main['heading']) ?>" required maxlength="220"></div></div>
            <div class="field"><label for="description">Description</label><textarea id="description" name="description" required maxlength="2000"><?= e($main['description']) ?></textarea></div>
            <div class="settings-grid"><div class="field"><label for="button_one_text">Button 1 text</label><input id="button_one_text" name="button_one_text" value="<?= e($main['button_one_text']) ?>" maxlength="100"></div><div class="field"><label for="button_one_link">Button 1 link</label><input id="button_one_link" name="button_one_link" value="<?= e($main['button_one_link']) ?>" maxlength="500"></div><div class="field"><label for="button_two_text">Button 2 text</label><input id="button_two_text" name="button_two_text" value="<?= e($main['button_two_text']) ?>" maxlength="100"></div><div class="field"><label for="button_two_link">Button 2 link</label><input id="button_two_link" name="button_two_link" value="<?= e($main['button_two_link']) ?>" maxlength="500"></div></div>
            <input name="status" type="hidden" value="1">
            <p class="help">About Us is always available at <strong>index.php#about</strong> as a dedicated page view and is not embedded in Home.</p>
            <div class="form-actions"><button class="primary-button" type="submit">Save Main Content</button></div>
        </form>
        <?php endif; ?>

        <?php if ($tab === 'slider'): ?>
        <form class="about-admin-card about-new-card" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="save_slider"><input type="hidden" name="tab" value="slider"><h2>Add Slider Image</h2><div class="settings-grid"><div class="field"><label>Image</label><input class="about-preview-input" name="image" type="file" accept="image/jpeg,image/png,image/webp" required><small class="help">JPG, PNG or WebP, up to 5 MB. Recommended 1200×800 or larger.</small><img class="about-image-preview" alt="Selected image preview"></div><div><div class="field"><label>Optional title</label><input name="title" maxlength="180"></div><div class="field"><label>Display order</label><input name="sort_order" type="number" min="0" max="9999" value="<?= count($sliderImages)+1 ?>"></div><div class="checkbox-field"><input id="new_slider_status" name="status" type="checkbox" value="1" checked><label for="new_slider_status">Active</label></div></div></div><div class="form-actions"><button class="primary-button">Upload Image</button></div></form>
        <div class="about-admin-list"><?php foreach ($sliderImages as $image): ?><article class="about-admin-card"><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="save_slider"><input type="hidden" name="tab" value="slider"><input type="hidden" name="id" value="<?= (int)$image['id'] ?>"><div class="about-admin-card-grid"><img class="about-admin-preview" src="../<?= e($image['image']) ?>" alt="<?= e($image['title'] ?: 'About slider image') ?>"><div><div class="field"><label>Title</label><input name="title" value="<?= e($image['title']) ?>" maxlength="180"></div><div class="about-admin-inline"><div class="field"><label>Replacement image</label><input class="about-preview-input" name="image" type="file" accept="image/jpeg,image/png,image/webp"><img class="about-image-preview" alt="Replacement preview"></div><div class="field"><label>Display order</label><input name="sort_order" type="number" min="0" max="9999" value="<?= (int)$image['sort_order'] ?>"></div></div><div class="checkbox-field"><input id="slider_status_<?= (int)$image['id'] ?>" name="status" type="checkbox" value="1"<?= (int)$image['status']===1?' checked':'' ?>><label for="slider_status_<?= (int)$image['id'] ?>">Active</label></div><div class="about-admin-actions"><button class="primary-button" type="submit">Save Image</button></div></div></div></form><form method="post" onsubmit="return confirm('Delete this About slider image permanently?');"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete_slider"><input type="hidden" name="tab" value="slider"><input type="hidden" name="id" value="<?= (int)$image['id'] ?>"><button class="about-delete" type="submit">Delete Image</button></form></article><?php endforeach; ?><?php if ($sliderImages===[]): ?><p>No images uploaded yet. The public section will use its local fallback image.</p><?php endif; ?></div>
        <?php endif; ?>

        <?php if ($tab === 'features'): ?>
        <form class="about-admin-card about-new-card" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="save_feature"><input type="hidden" name="tab" value="features"><h2>Add Feature</h2><div class="settings-grid"><div class="field"><label>Icon keyword</label><input name="icon" placeholder="design" maxlength="80" required><small class="help">Examples: design, quality, turnkey, planning.</small></div><div class="field"><label>Feature title</label><input name="title" maxlength="180" required></div><div class="field"><label>Display order</label><input name="sort_order" type="number" min="0" max="9999" value="<?= count($features)+1 ?>"></div></div><div class="checkbox-field"><input id="new_feature_status" name="status" type="checkbox" value="1" checked><label for="new_feature_status">Active</label></div><div class="form-actions"><button class="primary-button">Add Feature</button></div></form>
        <div class="about-admin-list"><?php foreach ($features as $feature): ?><article class="about-admin-card"><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="save_feature"><input type="hidden" name="tab" value="features"><input type="hidden" name="id" value="<?= (int)$feature['id'] ?>"><div class="settings-grid"><div class="field"><label>Icon keyword</label><input name="icon" value="<?= e($feature['icon']) ?>" maxlength="80" required></div><div class="field"><label>Feature title</label><input name="title" value="<?= e($feature['title']) ?>" maxlength="180" required></div><div class="field"><label>Display order</label><input name="sort_order" type="number" min="0" max="9999" value="<?= (int)$feature['sort_order'] ?>"></div></div><div class="checkbox-field"><input id="feature_status_<?= (int)$feature['id'] ?>" name="status" type="checkbox" value="1"<?= (int)$feature['status']===1?' checked':'' ?>><label for="feature_status_<?= (int)$feature['id'] ?>">Active</label></div><div class="about-admin-actions"><button class="primary-button">Save Feature</button></div></form><form method="post" onsubmit="return confirm('Delete this feature?');"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete_feature"><input type="hidden" name="tab" value="features"><input type="hidden" name="id" value="<?= (int)$feature['id'] ?>"><button class="about-delete">Delete</button></form></article><?php endforeach; ?></div>
        <?php endif; ?>

        <?php if ($tab === 'counters'): ?>
        <form class="about-admin-card about-new-card" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="save_counter"><input type="hidden" name="tab" value="counters"><h2>Add Counter</h2><div class="settings-grid"><div class="field"><label>Icon keyword</label><input name="icon" placeholder="projects" maxlength="80" required></div><div class="field"><label>Number</label><input name="number" type="number" min="0" max="999999999" required></div><div class="field"><label>Suffix</label><input name="suffix" placeholder="+" maxlength="20"></div><div class="field"><label>Title</label><input name="title" maxlength="180" required></div><div class="field"><label>Description</label><input name="description" maxlength="300"></div><div class="field"><label>Display order</label><input name="sort_order" type="number" min="0" max="9999" value="<?= count($counters)+1 ?>"></div></div><div class="checkbox-field"><input id="new_counter_status" name="status" type="checkbox" value="1" checked><label for="new_counter_status">Active</label></div><div class="form-actions"><button class="primary-button">Add Counter</button></div></form>
        <div class="about-admin-list"><?php foreach ($counters as $counter): ?><article class="about-admin-card"><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="save_counter"><input type="hidden" name="tab" value="counters"><input type="hidden" name="id" value="<?= (int)$counter['id'] ?>"><div class="settings-grid"><div class="field"><label>Icon keyword</label><input name="icon" value="<?= e($counter['icon']) ?>" maxlength="80" required></div><div class="field"><label>Number</label><input name="number" type="number" min="0" max="999999999" value="<?= (int)$counter['number'] ?>" required></div><div class="field"><label>Suffix</label><input name="suffix" value="<?= e($counter['suffix']) ?>" maxlength="20"></div><div class="field"><label>Title</label><input name="title" value="<?= e($counter['title']) ?>" maxlength="180" required></div><div class="field"><label>Description</label><input name="description" value="<?= e($counter['description']) ?>" maxlength="300"></div><div class="field"><label>Display order</label><input name="sort_order" type="number" min="0" max="9999" value="<?= (int)$counter['sort_order'] ?>"></div></div><div class="checkbox-field"><input id="counter_status_<?= (int)$counter['id'] ?>" name="status" type="checkbox" value="1"<?= (int)$counter['status']===1?' checked':'' ?>><label for="counter_status_<?= (int)$counter['id'] ?>">Active</label></div><div class="about-admin-actions"><button class="primary-button">Save Counter</button></div></form><form method="post" onsubmit="return confirm('Delete this counter?');"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete_counter"><input type="hidden" name="tab" value="counters"><input type="hidden" name="id" value="<?= (int)$counter['id'] ?>"><button class="about-delete">Delete</button></form></article><?php endforeach; ?></div>
        <?php endif; ?>
    </section>
</main>
<script>document.querySelectorAll('.about-preview-input').forEach(function(input){input.addEventListener('change',function(){var preview=input.parentElement.querySelector('.about-image-preview');var file=input.files&&input.files[0];if(!preview||!file)return;preview.src=URL.createObjectURL(file);preview.style.display='block';});});</script>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/site-office-data.php';
requireAdmin();

officeEnsureTables();
$pdo = db();
$errors = [];

function officeAdminUpload(string $field, string $folder, string $type): array
{
    $upload = $_FILES[$field] ?? null;
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['present' => false, 'path' => '', 'error' => ''];
    $isImage = $type === 'image';
    $maxBytes = $isImage ? 5 * 1024 * 1024 : 50 * 1024 * 1024;
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($upload['size'] ?? 0) > $maxBytes || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
        return ['present' => true, 'path' => '', 'error' => $isImage ? 'Image must be JPG, PNG, or WebP up to 5 MB.' : 'Video must be MP4 or WebM up to 50 MB.'];
    }
    $map = $isImage ? ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'] : ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']);
    if (!is_string($mime) || !isset($map[$mime]) || ($isImage && @getimagesize((string) $upload['tmp_name']) === false)) {
        return ['present' => true, 'path' => '', 'error' => $isImage ? 'Upload a valid JPG, PNG, or WebP image.' : 'Upload a valid MP4 or WebM video.'];
    }
    $folder = trim($folder, '/');
    $absoluteFolder = dirname(__DIR__) . '/' . $folder;
    if (!is_dir($absoluteFolder)) mkdir($absoluteFolder, 0755, true);
    $path = $folder . '/' . bin2hex(random_bytes(16)) . '.' . $map[$mime];
    if (!move_uploaded_file((string) $upload['tmp_name'], dirname(__DIR__) . '/' . $path)) return ['present' => true, 'path' => '', 'error' => 'The file could not be saved.'];
    return ['present' => true, 'path' => $path, 'error' => ''];
}

function officeAdminDeleteFile(string $path): void
{
    if (!officeImageIsSafe($path) && !officeVideoIsSafe($path)) return;
    $absolute = dirname(__DIR__) . '/' . $path;
    if (is_file($absolute)) unlink($absolute);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrfIsValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        $errors[] = 'Your session expired. Refresh and try again.';
    } elseif ($action === 'save_settings') {
        $settings = getOfficePageSettings();
        $heroImageUpload = officeAdminUpload('hero_image', 'public/uploads/office/hero', 'image');
        $heroVideoUpload = officeAdminUpload('hero_video', 'public/uploads/office/hero', 'video');
        if ($heroImageUpload['error'] !== '') $errors[] = $heroImageUpload['error'];
        if ($heroVideoUpload['error'] !== '') $errors[] = $heroVideoUpload['error'];
        $values = [
            'eyebrow' => trim((string) ($_POST['eyebrow'] ?? '')),
            'title' => trim((string) ($_POST['title'] ?? '')),
            'subtitle' => trim((string) ($_POST['subtitle'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'hero_image' => $heroImageUpload['present'] ? $heroImageUpload['path'] : (string) $settings['hero_image'],
            'hero_video' => $heroVideoUpload['present'] ? $heroVideoUpload['path'] : (string) $settings['hero_video'],
            'show_gallery' => isset($_POST['show_gallery']) ? '1' : '0',
        ];
        if (isset($_POST['remove_hero_image'])) $values['hero_image'] = '';
        if (isset($_POST['remove_hero_video'])) $values['hero_video'] = '';
        if ($values['title'] === '') $errors[] = 'Page title is required.';
        if ($values['subtitle'] === '') $errors[] = 'Subtitle is required.';
        if ($values['description'] === '') $errors[] = 'Description is required.';
        if ($errors === []) {
            $stmt = $pdo->prepare('INSERT INTO site_settings (setting_group, setting_key, setting_value, setting_type) VALUES (:g,:k,:v,:t) ON CONFLICT(setting_group, setting_key) DO UPDATE SET setting_value=excluded.setting_value, updated_at=CURRENT_TIMESTAMP');
            foreach ($values as $key => $value) $stmt->execute([':g' => 'office_page', ':k' => $key, ':v' => $value, ':t' => 'text']);
            if ($values['hero_image'] !== (string) $settings['hero_image']) officeAdminDeleteFile((string) $settings['hero_image']);
            if ($values['hero_video'] !== (string) $settings['hero_video']) officeAdminDeleteFile((string) $settings['hero_video']);
            $_SESSION['office_flash'] = 'Office & Factory page settings saved.';
            header('Location: office-settings.php');
            exit;
        }
        foreach ([$heroImageUpload['path'], $heroVideoUpload['path']] as $newPath) if ($newPath !== '') officeAdminDeleteFile($newPath);
    } elseif ($action === 'save_media') {
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $mediaType = (string) ($_POST['media_type'] ?? 'image');
        $title = trim((string) ($_POST['media_title'] ?? ''));
        $description = trim((string) ($_POST['media_description'] ?? ''));
        $order = filter_var($_POST['display_order'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9999]]);
        $status = isset($_POST['status']) ? 1 : 0;
        $old = ['file_path' => ''];
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT file_path FROM office_media WHERE id=:id');
            $stmt->execute([':id' => $id]);
            $old = $stmt->fetch() ?: $old;
        }
        if (!in_array($mediaType, ['image', 'video'], true)) $errors[] = 'Choose a valid media type.';
        $upload = officeAdminUpload('media_file', 'public/uploads/office/gallery', $mediaType);
        if ($upload['error'] !== '') $errors[] = $upload['error'];
        if (!$upload['present'] && $id === 0) $errors[] = 'Upload an image or video.';
        if ($order === false) $errors[] = 'Display order must be between 0 and 9999.';
        if ($errors === []) {
            $filePath = $upload['present'] ? $upload['path'] : (string) $old['file_path'];
            $params = [':media_type' => $mediaType, ':file_path' => $filePath, ':title' => $title, ':description' => $description, ':display_order' => (int) $order, ':status' => $status];
            if ($id > 0) {
                $params[':id'] = $id;
                $stmt = $pdo->prepare('UPDATE office_media SET media_type=:media_type, file_path=:file_path, title=:title, description=:description, display_order=:display_order, status=:status, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
            } else {
                $stmt = $pdo->prepare('INSERT INTO office_media (media_type, file_path, title, description, display_order, status) VALUES (:media_type, :file_path, :title, :description, :display_order, :status)');
            }
            $stmt->execute($params);
            if ($filePath !== (string) $old['file_path']) officeAdminDeleteFile((string) $old['file_path']);
            $_SESSION['office_flash'] = 'Office media saved.';
            header('Location: office-settings.php');
            exit;
        }
        if ($upload['path'] !== '') officeAdminDeleteFile($upload['path']);
    } elseif ($action === 'delete_media') {
        $id = max(1, (int) ($_POST['id'] ?? 0));
        $stmt = $pdo->prepare('SELECT file_path FROM office_media WHERE id=:id');
        $stmt->execute([':id' => $id]);
        $record = $stmt->fetch();
        if (is_array($record)) {
            $pdo->prepare('DELETE FROM office_media WHERE id=:id')->execute([':id' => $id]);
            officeAdminDeleteFile((string) $record['file_path']);
            $_SESSION['office_flash'] = 'Office media deleted.';
            header('Location: office-settings.php');
            exit;
        }
    }
}

$settings = getOfficePageSettings();
$edit = null;
if (isset($_GET['edit_media'])) {
    $stmt = $pdo->prepare('SELECT * FROM office_media WHERE id=:id');
    $stmt->execute([':id' => (int) $_GET['edit_media']]);
    $edit = $stmt->fetch();
}
$media = getOfficeMedia(false);
$mediaForm = $edit ?: ['id' => '', 'media_type' => 'image', 'file_path' => '', 'title' => '', 'description' => '', 'display_order' => count($media) + 1, 'status' => 1];
$flash = $_SESSION['office_flash'] ?? '';
unset($_SESSION['office_flash']);
$pageTitle = 'Office & Factory';
require __DIR__ . '/includes/header.php';
?>
<style>.office-admin-preview{display:block;width:220px;max-width:100%;aspect-ratio:16/9;margin-top:10px;border:1px solid var(--line);border-radius:6px;background:#fff;object-fit:cover}.office-media-list{display:grid;gap:14px;margin-top:18px}.office-media-item{display:grid;grid-template-columns:190px 1fr auto;gap:15px;align-items:center;padding:14px;border:1px solid var(--line);border-radius:7px;background:#fff}.office-media-actions{display:flex;gap:8px;flex-wrap:wrap}.office-media-actions a,.office-media-actions button{padding:8px 12px;border:1px solid var(--green);border-radius:4px;background:#fff;color:var(--green);font-weight:700;text-decoration:none;cursor:pointer}.office-media-actions button{border-color:#a12626;color:#a12626}@media(max-width:760px){.office-media-item{grid-template-columns:1fr}.office-admin-preview{width:100%}}</style>
<header class="admin-header"><a class="admin-brand" href="dashboard.php">Design24 Studio Admin</a><div class="admin-user"><span>Signed in as <?=e(currentAdminName())?></span><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><button class="logout-button">Logout</button></form></div></header>
<main class="admin-main"><div class="settings-toolbar"><a href="dashboard.php">← Dashboard</a><a href="../office.php" target="_blank">View Office &amp; Factory ↗</a></div><section class="panel"><h1>Office &amp; Factory Page</h1><p>Manage the standalone Office &amp; Factory page hero, text, images, and videos.</p><?php if($flash):?><p class="success"><?=e($flash)?></p><?php endif;?><?php if($errors):?><div class="error"><ul class="error-list"><?php foreach($errors as$error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="settings-section"><h2>Page Hero &amp; Text</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="save_settings"><div class="settings-grid"><div class="field"><label>Eyebrow</label><input name="eyebrow" value="<?=e($settings['eyebrow'])?>" maxlength="120"></div><div class="field"><label>Page Title *</label><input name="title" value="<?=e($settings['title'])?>" maxlength="220" required></div></div><div class="field"><label>Subtitle *</label><textarea name="subtitle" maxlength="500" required><?=e($settings['subtitle'])?></textarea></div><div class="field"><label>Description *</label><textarea name="description" required><?=e($settings['description'])?></textarea></div><div class="settings-grid"><div class="field"><label>Hero Image</label><input name="hero_image" type="file" accept="image/jpeg,image/png,image/webp"><?php if(officeImageIsSafe((string)$settings['hero_image'])):?><img class="office-admin-preview" src="../<?=e($settings['hero_image'])?>" alt=""><label><input type="checkbox" name="remove_hero_image"> Remove hero image</label><?php endif;?></div><div class="field"><label>Hero Video</label><input name="hero_video" type="file" accept="video/mp4,video/webm"><?php if(officeVideoIsSafe((string)$settings['hero_video'])):?><video class="office-admin-preview" src="../<?=e($settings['hero_video'])?>" muted playsinline controls preload="metadata"></video><label><input type="checkbox" name="remove_hero_video"> Remove hero video</label><?php endif;?><small class="help">Video displays over image. Image is used as poster/fallback.</small></div></div><div class="checkbox-field"><input id="show_gallery" name="show_gallery" type="checkbox" value="1"<?=settingEnabled($settings,'show_gallery')?' checked':''?>><label for="show_gallery">Show gallery section</label></div><div class="form-actions"><button class="primary-button">Save Page Settings</button></div></form></section>
<section class="settings-section"><h2><?= $edit ? 'Edit' : 'Add' ?> Gallery Media</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="save_media"><input type="hidden" name="id" value="<?=e((string)$mediaForm['id'])?>"><div class="settings-grid"><div class="field"><label>Media Type</label><select name="media_type"><option value="image"<?=$mediaForm['media_type']==='image'?' selected':''?>>Image</option><option value="video"<?=$mediaForm['media_type']==='video'?' selected':''?>>Video</option></select></div><div class="field"><label>Display Order</label><input name="display_order" type="number" min="0" max="9999" value="<?=e((string)$mediaForm['display_order'])?>"></div></div><div class="field"><label>Upload File <?= $edit ? '' : '*' ?></label><input name="media_file" type="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"><small class="help">For image: JPG/PNG/WebP max 5 MB. For video: MP4/WebM max 50 MB.</small><?php if($mediaForm['media_type']==='video'&&officeVideoIsSafe((string)$mediaForm['file_path'])):?><video class="office-admin-preview" src="../<?=e($mediaForm['file_path'])?>" controls muted playsinline preload="metadata"></video><?php elseif(officeImageIsSafe((string)$mediaForm['file_path'])):?><img class="office-admin-preview" src="../<?=e($mediaForm['file_path'])?>" alt=""><?php endif;?></div><div class="field"><label>Title</label><input name="media_title" value="<?=e((string)$mediaForm['title'])?>" maxlength="220"></div><div class="field"><label>Description</label><textarea name="media_description"><?=e((string)$mediaForm['description'])?></textarea></div><div class="checkbox-field"><input id="media_status" name="status" type="checkbox" value="1"<?=((int)$mediaForm['status']===1)?' checked':''?>><label for="media_status">Published / Active</label></div><div class="form-actions"><button class="primary-button">Save Media</button><?php if($edit):?><a class="secondary-admin-button" href="office-settings.php">Cancel</a><?php endif;?></div></form></section>
<section class="settings-section"><h2>All Gallery Media</h2><div class="office-media-list"><?php foreach($media as$item):?><article class="office-media-item"><?php if($item['media_type']==='video'&&officeVideoIsSafe((string)$item['file_path'])):?><video class="office-admin-preview" src="../<?=e($item['file_path'])?>" muted playsinline preload="metadata"></video><?php elseif(officeImageIsSafe((string)$item['file_path'])):?><img class="office-admin-preview" src="../<?=e($item['file_path'])?>" alt=""><?php else:?><div class="office-admin-preview"></div><?php endif;?><div><strong><?=e($item['title'] ?: 'Office media')?></strong><p><?=e(mb_strimwidth((string)$item['description'],0,130,'…'))?></p><small><?=e($item['media_type'])?> · <?=$item['status']?'Active':'Inactive'?> · Order <?=e((string)$item['display_order'])?></small></div><div class="office-media-actions"><a href="?edit_media=<?=(int)$item['id']?>">Edit</a><form method="post" onsubmit="return confirm('Delete this office media permanently?');"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="delete_media"><input type="hidden" name="id" value="<?=(int)$item['id']?>"><button>Delete</button></form></div></article><?php endforeach;?><?php if($media===[]):?><p>No office/factory media uploaded yet.</p><?php endif;?></div></section></section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>

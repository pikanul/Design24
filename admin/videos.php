<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/site-video-data.php';
requireAdmin();

videoGalleryEnsureTables();
$pdo = db();
$errors = [];

function adminVideoGalleryUpload(string $field, string $folder, array $mimes, int $maxBytes, string $errorLabel): array
{
    $upload = $_FILES[$field] ?? null;
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['present' => false, 'path' => '', 'error' => ''];
    }
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($upload['size'] ?? 0) > $maxBytes || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
        return ['present' => true, 'path' => '', 'error' => $errorLabel];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']);
    if (!is_string($mime) || !isset($mimes[$mime])) {
        return ['present' => true, 'path' => '', 'error' => $errorLabel];
    }
    if (strpos((string) $mime, 'image/') === 0 && @getimagesize((string) $upload['tmp_name']) === false) {
        return ['present' => true, 'path' => '', 'error' => $errorLabel];
    }
    $folder = trim($folder, '/');
    $absoluteFolder = dirname(__DIR__) . '/' . $folder;
    if (!is_dir($absoluteFolder)) mkdir($absoluteFolder, 0755, true);
    $path = $folder . '/' . bin2hex(random_bytes(16)) . '.' . $mimes[$mime];
    if (!move_uploaded_file((string) $upload['tmp_name'], dirname(__DIR__) . '/' . $path)) {
        return ['present' => true, 'path' => '', 'error' => 'The file could not be saved.'];
    }
    return ['present' => true, 'path' => $path, 'error' => ''];
}

function adminVideoGalleryDeleteFile(string $path): void
{
    if (!videoGalleryVideoIsSafe($path) && !videoGalleryImageIsSafe($path)) return;
    $absolute = dirname(__DIR__) . '/' . $path;
    if (is_file($absolute)) unlink($absolute);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrfIsValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        $errors[] = 'Your session expired. Refresh and try again.';
    } elseif ($action === 'save') {
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $videoType = (string) ($_POST['video_type'] ?? 'url');
        $videoUrl = trim((string) ($_POST['video_url'] ?? ''));
        $order = filter_var($_POST['display_order'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9999]]);
        $status = isset($_POST['status']) ? 1 : 0;
        $old = ['video_file' => '', 'thumbnail' => ''];
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT video_file, thumbnail FROM site_videos WHERE id=:id');
            $stmt->execute([':id' => $id]);
            $old = $stmt->fetch() ?: $old;
        }

        $videoUpload = adminVideoGalleryUpload('video_file', 'public/uploads/videos', ['video/mp4' => 'mp4', 'video/webm' => 'webm'], 50 * 1024 * 1024, 'Upload a valid MP4 or WebM video up to 50 MB.');
        $thumbUpload = adminVideoGalleryUpload('thumbnail', 'public/uploads/videos/thumbs', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], 5 * 1024 * 1024, 'Upload a valid JPG, PNG, or WebP thumbnail up to 5 MB.');

        if ($title === '') $errors[] = 'Video title is required.';
        if (!in_array($videoType, ['url', 'upload'], true)) $errors[] = 'Choose a valid video type.';
        if ($videoType === 'url' && videoGalleryYoutubeId($videoUrl) === '') $errors[] = 'Paste a valid YouTube video URL.';
        if ($videoType === 'upload' && !$videoUpload['present'] && (string) ($old['video_file'] ?? '') === '') $errors[] = 'Upload a video file.';
        if ($videoUpload['error'] !== '') $errors[] = $videoUpload['error'];
        if ($thumbUpload['error'] !== '') $errors[] = $thumbUpload['error'];
        if ($order === false) $errors[] = 'Display order must be between 0 and 9999.';

        if ($errors === []) {
            $videoFile = $videoUpload['present'] ? $videoUpload['path'] : (string) ($old['video_file'] ?? '');
            $thumbnail = $thumbUpload['present'] ? $thumbUpload['path'] : (string) ($old['thumbnail'] ?? '');
            if (isset($_POST['remove_thumbnail'])) $thumbnail = '';
            if ($videoType === 'url') $videoFile = '';
            if ($videoType === 'upload') $videoUrl = '';

            $params = [
                ':title' => $title,
                ':description' => $description,
                ':video_type' => $videoType,
                ':video_url' => $videoUrl,
                ':video_file' => $videoFile,
                ':thumbnail' => $thumbnail,
                ':display_order' => (int) $order,
                ':status' => $status,
            ];
            if ($id > 0) {
                $params[':id'] = $id;
                $stmt = $pdo->prepare('UPDATE site_videos SET title=:title, description=:description, video_type=:video_type, video_url=:video_url, video_file=:video_file, thumbnail=:thumbnail, display_order=:display_order, status=:status, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
            } else {
                $stmt = $pdo->prepare('INSERT INTO site_videos (title, description, video_type, video_url, video_file, thumbnail, display_order, status) VALUES (:title, :description, :video_type, :video_url, :video_file, :thumbnail, :display_order, :status)');
            }
            $stmt->execute($params);
            if ($videoFile !== (string) ($old['video_file'] ?? '')) adminVideoGalleryDeleteFile((string) ($old['video_file'] ?? ''));
            if ($thumbnail !== (string) ($old['thumbnail'] ?? '')) adminVideoGalleryDeleteFile((string) ($old['thumbnail'] ?? ''));
            $_SESSION['video_gallery_flash'] = 'Video saved successfully.';
            header('Location: videos.php');
            exit;
        }
        foreach ([$videoUpload['path'], $thumbUpload['path']] as $newPath) {
            if ($newPath !== '') adminVideoGalleryDeleteFile($newPath);
        }
    } elseif ($action === 'delete') {
        $id = max(1, (int) ($_POST['id'] ?? 0));
        $stmt = $pdo->prepare('SELECT video_file, thumbnail FROM site_videos WHERE id=:id');
        $stmt->execute([':id' => $id]);
        $record = $stmt->fetch();
        if (is_array($record)) {
            $pdo->prepare('DELETE FROM site_videos WHERE id=:id')->execute([':id' => $id]);
            adminVideoGalleryDeleteFile((string) $record['video_file']);
            adminVideoGalleryDeleteFile((string) $record['thumbnail']);
            $_SESSION['video_gallery_flash'] = 'Video deleted.';
            header('Location: videos.php');
            exit;
        }
        $errors[] = 'Video was not found.';
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM site_videos WHERE id=:id');
    $stmt->execute([':id' => (int) $_GET['edit']]);
    $edit = $stmt->fetch();
}
$videos = getVideoGalleryItems(false);
$form = $edit ?: ['id' => '', 'title' => '', 'description' => '', 'video_type' => 'url', 'video_url' => '', 'video_file' => '', 'thumbnail' => '', 'display_order' => count($videos) + 1, 'status' => 1];
$flash = $_SESSION['video_gallery_flash'] ?? '';
unset($_SESSION['video_gallery_flash']);
$pageTitle = 'Video Gallery';
require __DIR__ . '/includes/header.php';
?>
<style>.video-admin-list{display:grid;gap:14px;margin-top:18px}.video-admin-item{display:grid;grid-template-columns:190px 1fr auto;gap:15px;align-items:center;padding:14px;border:1px solid var(--line);border-radius:7px;background:#fff}.video-admin-preview{width:190px;aspect-ratio:16/9;border-radius:6px;background:#e9efec;object-fit:cover}.video-admin-actions{display:flex;gap:8px;flex-wrap:wrap}.video-admin-actions a,.video-admin-actions button{padding:8px 12px;border:1px solid var(--green);border-radius:4px;background:#fff;color:var(--green);font-weight:700;text-decoration:none;cursor:pointer}.video-admin-actions button{border-color:#a12626;color:#a12626}@media(max-width:760px){.video-admin-item{grid-template-columns:1fr}.video-admin-preview{width:100%}}</style>
<header class="admin-header"><a class="admin-brand" href="dashboard.php">Design24 Studio Admin</a><div class="admin-user"><span>Signed in as <?=e(currentAdminName())?></span><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><button class="logout-button">Logout</button></form></div></header>
<main class="admin-main"><div class="settings-toolbar"><a href="dashboard.php">← Dashboard</a><a href="../videos.php" target="_blank">View Video Gallery ↗</a></div><section class="panel"><h1>Video Gallery</h1><p>Add YouTube video links or upload MP4/WebM videos for the public video gallery.</p><?php if($flash):?><p class="success"><?=e($flash)?></p><?php endif;?><?php if($errors):?><div class="error"><ul class="error-list"><?php foreach($errors as$error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="settings-section"><h2><?= $edit ? 'Edit' : 'Add' ?> Video</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e((string)$form['id'])?>"><div class="settings-grid"><div class="field"><label>Title</label><input name="title" value="<?=e($form['title'])?>" maxlength="220" required></div><div class="field"><label>Display Order</label><input type="number" name="display_order" min="0" max="9999" value="<?=e((string)$form['display_order'])?>"></div></div><div class="field"><label>Description</label><textarea name="description"><?=e((string)$form['description'])?></textarea></div><div class="field"><label>Video Type</label><select name="video_type"><option value="url"<?=$form['video_type']==='url'?' selected':''?>>YouTube URL</option><option value="upload"<?=$form['video_type']==='upload'?' selected':''?>>Upload Video File</option></select></div><div class="field"><label>YouTube URL</label><input type="url" name="video_url" value="<?=e((string)$form['video_url'])?>" maxlength="500"><small class="help">Required when Video Type is YouTube URL.</small></div><div class="settings-grid"><div class="field"><label>Upload Video</label><input type="file" name="video_file" accept="video/mp4,video/webm"><small class="help">Required when Video Type is Upload. MP4/WebM, max 50 MB.</small><?php if(videoGalleryVideoIsSafe((string)$form['video_file'])):?><video class="video-admin-preview" src="../<?=e($form['video_file'])?>" controls muted playsinline preload="metadata"></video><?php endif;?></div><div class="field"><label>Thumbnail for Uploaded Video</label><input type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp"><small class="help">Optional poster image for uploaded videos.</small><?php if(videoGalleryImageIsSafe((string)$form['thumbnail'])):?><img class="video-admin-preview" src="../<?=e($form['thumbnail'])?>" alt=""><label><input type="checkbox" name="remove_thumbnail"> Remove thumbnail</label><?php endif;?></div></div><div class="checkbox-field"><input id="status" name="status" type="checkbox" value="1"<?=((int)$form['status']===1)?' checked':''?>><label for="status">Published / Active</label></div><div class="form-actions"><button class="primary-button">Save Video</button><?php if($edit):?><a class="secondary-admin-button" href="videos.php">Cancel</a><?php endif;?></div></form></section>
<section class="settings-section"><h2>All Videos</h2><div class="video-admin-list"><?php foreach($videos as$video):?><?php $youtubeId=videoGalleryYoutubeId((string)$video['video_url']);?><article class="video-admin-item"><?php if($youtubeId!==''):?><img class="video-admin-preview" src="https://img.youtube.com/vi/<?=e($youtubeId)?>/hqdefault.jpg" alt=""><?php elseif(videoGalleryImageIsSafe((string)$video['thumbnail'])):?><img class="video-admin-preview" src="../<?=e($video['thumbnail'])?>" alt=""><?php elseif(videoGalleryVideoIsSafe((string)$video['video_file'])):?><video class="video-admin-preview" src="../<?=e($video['video_file'])?>" muted playsinline preload="metadata"></video><?php else:?><div class="video-admin-preview"></div><?php endif;?><div><strong><?=e($video['title'])?></strong><p><?=e(mb_strimwidth((string)$video['description'],0,130,'…'))?></p><small><?=e($video['video_type'])?> · <?=$video['status']?'Active':'Inactive'?> · Order <?=e((string)$video['display_order'])?></small></div><div class="video-admin-actions"><a href="../videos.php" target="_blank">Preview</a><a href="?edit=<?=(int)$video['id']?>">Edit</a><form method="post" onsubmit="return confirm('Delete this video permanently?');"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$video['id']?>"><button>Delete</button></form></div></article><?php endforeach;?><?php if($videos===[]):?><p>No videos added yet.</p><?php endif;?></div></section></section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>

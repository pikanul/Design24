<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

function heroUploadPathIsSafe(string $type, string $path): bool
{
    $pattern = $type === 'image'
        ? '#^uploads/site/hero/images/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#'
        : '#^uploads/site/hero/videos/[a-f0-9]{32}\.(?:mp4|webm)$#';
    return preg_match($pattern, $path) === 1;
}

function phpSizeToBytes(string $value): int
{
    $value = trim($value);
    $number = (float) $value;
    $unit = strtolower(substr($value, -1));
    if ($unit === 'g') return (int) ($number * 1024 * 1024 * 1024);
    if ($unit === 'm') return (int) ($number * 1024 * 1024);
    if ($unit === 'k') return (int) ($number * 1024);
    return (int) $number;
}

function heroFlash(string $type, string $message): void
{
    $_SESSION['hero_flash'] = ['type' => $type, 'message' => $message];
}

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null;
    $action = (string) ($_POST['action'] ?? '');

    if (!csrfIsValid($csrf)) {
        $errors[] = 'Your session expired or the upload exceeded the PHP request limit. Refresh and try again.';
    } elseif ($action === 'upload') {
        $image = $_FILES['hero_image'] ?? null;
        $video = $_FILES['hero_video'] ?? null;
        $hasImage = is_array($image) && ($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $hasVideo = is_array($video) && ($video['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($hasImage === $hasVideo) {
            $errors[] = $hasImage ? 'Select either one image or one video, not both.' : 'Select one image or one video to upload.';
        } else {
            $type = $hasImage ? 'image' : 'video';
            $upload = $hasImage ? $image : $video;
            $maximum = $hasImage ? 5 * 1024 * 1024 : 50 * 1024 * 1024;
            $mimeMap = $hasImage
                ? ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']
                : ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
            $uploadError = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);

            if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                $errors[] = 'The file exceeds the current PHP upload limit of ' . ini_get('upload_max_filesize') . '. Update upload_max_filesize and post_max_size in php.ini.';
            } elseif ($uploadError !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
                $errors[] = 'The uploaded file could not be verified.';
            } elseif ((int) ($upload['size'] ?? 0) > $maximum) {
                $errors[] = $hasImage ? 'Images must be 5 MB or smaller.' : 'Videos must be 50 MB or smaller.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file((string) $upload['tmp_name']);
                if (!is_string($mime) || !isset($mimeMap[$mime])) {
                    $errors[] = $hasImage ? 'Upload a valid JPG, JPEG, PNG, or WebP image.' : 'Upload a valid MP4 or WebM video.';
                } elseif ($hasImage && @getimagesize((string) $upload['tmp_name']) === false) {
                    $errors[] = 'The uploaded image is invalid.';
                } else {
                    $folder = $hasImage ? 'uploads/site/hero/images/' : 'uploads/site/hero/videos/';
                    $relativePath = $folder . bin2hex(random_bytes(16)) . '.' . $mimeMap[$mime];
                    $absolutePath = dirname(__DIR__) . '/' . $relativePath;
                    if (!move_uploaded_file((string) $upload['tmp_name'], $absolutePath)) {
                        $errors[] = 'The uploaded media could not be saved.';
                    } else {
                        try {
                            $nextOrder = (int) $pdo->query('SELECT COALESCE(MAX(display_order), 0) + 1 FROM hero_media')->fetchColumn();
                            $insert = $pdo->prepare('INSERT INTO hero_media (media_type, file_path, display_order, status, created_at, updated_at) VALUES (:type, :path, :display_order, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
                            $insert->execute([':type' => $type, ':path' => $relativePath, ':display_order' => $nextOrder]);
                            $warning = '';
                            if ($hasImage) {
                                $dimensions = getimagesize($absolutePath);
                                if (is_array($dimensions) && ($dimensions[0] < 1920 || $dimensions[1] < 900)) {
                                    $warning = ' Warning: the image is smaller than the recommended 1920 × 900 pixels.';
                                }
                            }
                            heroFlash('success', ucfirst($type) . ' uploaded successfully.' . $warning);
                            header('Location: hero-settings.php');
                            exit;
                        } catch (Throwable $exception) {
                            if (is_file($absolutePath)) unlink($absolutePath);
                            error_log($exception->getMessage());
                            $errors[] = 'The database record could not be saved.';
                        }
                    }
                }
            }
        }
    } elseif ($action === 'update') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $displayOrder = filter_var($_POST['display_order'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9999]]);
        if ($id === false || $displayOrder === false) {
            $errors[] = 'Enter a valid display order between 0 and 9999.';
        } else {
            $update = $pdo->prepare('UPDATE hero_media SET display_order = :display_order, status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $update->execute([':display_order' => $displayOrder, ':status' => isset($_POST['status']) ? 1 : 0, ':id' => $id]);
            heroFlash('success', 'Media item updated successfully.');
            header('Location: hero-settings.php');
            exit;
        }
    } elseif ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            $errors[] = 'Invalid media item.';
        } else {
            $find = $pdo->prepare('SELECT media_type, file_path FROM hero_media WHERE id = :id LIMIT 1');
            $find->execute([':id' => $id]);
            $item = $find->fetch();
            if (!is_array($item)) {
                $errors[] = 'Media item was not found.';
            } elseif (!heroUploadPathIsSafe((string) $item['media_type'], (string) $item['file_path'])) {
                $errors[] = 'The media path is outside the approved hero upload folders.';
            } else {
                $delete = $pdo->prepare('DELETE FROM hero_media WHERE id = :id');
                $delete->execute([':id' => $id]);
                $absolutePath = dirname(__DIR__) . '/' . $item['file_path'];
                if (is_file($absolutePath)) unlink($absolutePath);
                heroFlash('success', 'Media item deleted successfully.');
                header('Location: hero-settings.php');
                exit;
            }
        }
    } else {
        $errors[] = 'Invalid action.';
    }
}

$flash = $_SESSION['hero_flash'] ?? null;
unset($_SESSION['hero_flash']);
$mediaStatement = $pdo->prepare('SELECT id, media_type, file_path, display_order, status, created_at FROM hero_media ORDER BY display_order ASC, id ASC');
$mediaStatement->execute();
$mediaItems = $mediaStatement->fetchAll();
$uploadLimit = (string) ini_get('upload_max_filesize');
$postLimit = (string) ini_get('post_max_size');
$largeUploadsEnabled = phpSizeToBytes($uploadLimit) >= 50 * 1024 * 1024
    && phpSizeToBytes($postLimit) > 50 * 1024 * 1024;
$pageTitle = 'Hero Media';
require __DIR__ . '/includes/header.php';
?>
<style>
.hero-upload-grid { display:grid; grid-template-columns:1fr 1fr; gap:22px; }
.hero-media-list { display:grid; gap:16px; margin-top:24px; }
.hero-media-item { display:grid; grid-template-columns:220px 1fr; gap:22px; padding:18px; border:1px solid var(--line); border-radius:7px; background:#fcfdfc; }
.hero-media-preview { width:220px; height:125px; overflow:hidden; border-radius:5px; background:#e7ece9; }
.hero-media-preview img,.hero-media-preview video { width:100%; height:100%; object-fit:cover; display:block; }
.hero-media-meta { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.media-type { padding:4px 8px; border-radius:20px; background:#e5f2ed; color:var(--green); font-size:.75rem; font-weight:700; text-transform:uppercase; }
.media-controls { display:flex; align-items:end; gap:12px; flex-wrap:wrap; margin-top:14px; }
.media-controls .field { width:130px; margin:0; }
.media-controls input[type=number] { min-height:40px; }
.small-button { min-height:40px; padding:0 14px; border:1px solid var(--green); border-radius:4px; background:var(--green); color:#fff; cursor:pointer; }
.delete-button { border-color:#a12626; background:#a12626; }
.upload-preview { display:none; width:100%; max-height:260px; margin-top:12px; object-fit:contain; background:#edf0ee; }
.upload-preview.visible { display:block; }
@media(max-width:700px){.hero-upload-grid,.hero-media-item{grid-template-columns:1fr}.hero-media-preview{width:100%;height:190px}}
</style>
<header class="admin-header"><a class="admin-brand" href="dashboard.php">Design24 Studio Admin</a><div class="admin-user"><span>Signed in as <?= e(currentAdminName()) ?></span><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><button class="logout-button" type="submit">Logout</button></form></div></header>
<main class="admin-main">
    <div class="settings-toolbar"><a href="dashboard.php">← Back to Dashboard</a><a href="../" target="_blank" rel="noopener">View Website ↗</a></div>
    <section class="panel"><h1>Hero Media</h1><p>Upload images or local videos for the homepage slider.</p>
        <?php if ($largeUploadsEnabled): ?>
            <p class="success"><strong>Large uploads enabled:</strong> upload_max_filesize = <?= e($uploadLimit) ?>, post_max_size = <?= e($postLimit) ?>. The server supports the 5 MB image and 50 MB video application limits.</p>
        <?php else: ?>
            <p class="error"><strong>Current PHP limits:</strong> upload_max_filesize = <?= e($uploadLimit) ?>, post_max_size = <?= e($postLimit) ?>. Increase both values in PHP 7.4’s <code>php.ini</code> and restart the server to upload files larger than <?= e($uploadLimit) ?>.</p>
        <?php endif; ?>
        <?php if (is_array($flash)): ?><p class="<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" role="status"><?= e((string) $flash['message']) ?></p><?php endif; ?>
        <?php if ($errors !== []): ?><div class="error" role="alert"><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" action="hero-settings.php" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="upload">
            <section class="settings-section"><h2>Add Hero Media</h2><p>Select one file only. Do not select both fields.</p><div class="hero-upload-grid"><div class="field"><label for="hero_image">Upload Image</label><input id="hero_image" name="hero_image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><small class="help">JPG, JPEG, PNG, WebP — application limit 5 MB.</small><img id="new-image-preview" class="upload-preview" alt="Selected image preview"></div><div class="field"><label for="hero_video">Upload Video</label><input id="hero_video" name="hero_video" type="file" accept=".mp4,.webm,video/mp4,video/webm"><small class="help">MP4 or WebM — application limit 50 MB.</small><video id="new-video-preview" class="upload-preview" muted playsinline controls></video></div></div><div class="form-actions"><button class="primary-button" type="submit">Upload Media</button></div></section>
        </form>

        <section class="settings-section"><h2>Uploaded Media</h2><?php if ($mediaItems === []): ?><p>No hero media has been uploaded.</p><?php else: ?><div class="hero-media-list"><?php foreach ($mediaItems as $item): ?>
            <article class="hero-media-item"><div class="hero-media-preview"><?php if ($item['media_type'] === 'image'): ?><img src="../<?= e($item['file_path']) ?>" alt="Hero image preview"><?php else: ?><video src="../<?= e($item['file_path']) ?>" muted playsinline controls preload="metadata"></video><?php endif; ?></div><div><div class="hero-media-meta"><span class="media-type"><?= e($item['media_type']) ?></span><span><?= (int) $item['status'] === 1 ? 'Active' : 'Inactive' ?></span></div><form class="media-controls" method="post" action="hero-settings.php"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><div class="field"><label for="order-<?= (int) $item['id'] ?>">Display order</label><input id="order-<?= (int) $item['id'] ?>" name="display_order" type="number" min="0" max="9999" value="<?= (int) $item['display_order'] ?>" required></div><div class="checkbox-field"><input id="status-<?= (int) $item['id'] ?>" name="status" type="checkbox" value="1" <?= (int) $item['status'] === 1 ? 'checked' : '' ?>><label for="status-<?= (int) $item['id'] ?>">Active</label></div><button class="small-button" type="submit">Save</button></form><form method="post" action="hero-settings.php" onsubmit="return confirm('Delete this hero media item permanently?');"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="small-button delete-button" type="submit">Delete</button></form></div></article>
        <?php endforeach; ?></div><?php endif; ?></section>
    </section>
</main>
<script>
const imageInput=document.getElementById('hero_image'),videoInput=document.getElementById('hero_video'),imagePreview=document.getElementById('new-image-preview'),videoPreview=document.getElementById('new-video-preview');
imageInput.addEventListener('change',()=>{const file=imageInput.files[0];if(!file){imagePreview.classList.remove('visible');imagePreview.removeAttribute('src');return}imagePreview.src=URL.createObjectURL(file);imagePreview.classList.add('visible')});
videoInput.addEventListener('change',()=>{const file=videoInput.files[0];if(!file){videoPreview.classList.remove('visible');videoPreview.removeAttribute('src');return}videoPreview.src=URL.createObjectURL(file);videoPreview.classList.add('visible')});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>

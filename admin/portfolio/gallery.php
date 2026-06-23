<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
requireAdmin();

$pdo = db();
$errors = [];
$projectId = max(0, (int) ($_GET['project_id'] ?? $_POST['project_id'] ?? 0));

function portfolioGalleryUploadMany(int $projectId): array
{
    $saved = [];
    $errors = [];
    if (!isset($_FILES['gallery_images']) || !is_array($_FILES['gallery_images']['name'])) {
        return [$saved, $errors];
    }
    $count = count($_FILES['gallery_images']['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $_FILES['single_gallery_image'] = [
            'name' => $_FILES['gallery_images']['name'][$i],
            'type' => $_FILES['gallery_images']['type'][$i],
            'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
            'error' => $_FILES['gallery_images']['error'][$i],
            'size' => $_FILES['gallery_images']['size'][$i],
        ];
        $upload = portfolioAdminUpload('single_gallery_image', 'public/uploads/portfolio/gallery');
        unset($_FILES['single_gallery_image']);
        if ($upload['error'] !== '') $errors[] = 'Image ' . ($i + 1) . ': ' . $upload['error'];
        if ($upload['path'] !== '') $saved[] = $upload['path'];
    }
    return [$saved, $errors];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrfIsValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        $errors[] = 'Your session expired. Refresh and try again.';
    } elseif ($action === 'upload') {
        if ($projectId <= 0) $errors[] = 'Choose a project first.';
        [$saved, $uploadErrors] = portfolioGalleryUploadMany($projectId);
        $errors = array_merge($errors, $uploadErrors);
        if ($errors === [] && $saved !== []) {
            $orderStmt = $pdo->prepare('SELECT COALESCE(MAX(display_order), 0) FROM portfolio_gallery WHERE project_id=:project_id');
            $orderStmt->execute([':project_id' => $projectId]);
            $order = (int) $orderStmt->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO portfolio_gallery (project_id, image_path, caption, display_order) VALUES (:project_id, :image_path, "", :display_order)');
            foreach ($saved as $path) {
                $order++;
                $stmt->execute([':project_id' => $projectId, ':image_path' => $path, ':display_order' => $order]);
            }
            $_SESSION['portfolio_flash'] = count($saved) . ' gallery image(s) uploaded.';
            header('Location: gallery.php?project_id=' . $projectId);
            exit;
        }
        if ($errors !== []) foreach ($saved as $path) portfolioAdminDeleteFile($path);
    } elseif ($action === 'save_gallery') {
        $captions = $_POST['caption'] ?? [];
        $orders = $_POST['display_order'] ?? [];
        if (is_array($captions) && is_array($orders)) {
            $stmt = $pdo->prepare('UPDATE portfolio_gallery SET caption=:caption, display_order=:display_order WHERE id=:id AND project_id=:project_id');
            foreach ($captions as $id => $caption) {
                $stmt->execute([':caption' => trim((string) $caption), ':display_order' => max(0, (int) ($orders[$id] ?? 0)), ':id' => (int) $id, ':project_id' => $projectId]);
            }
            $_SESSION['portfolio_flash'] = 'Gallery order and captions saved.';
            header('Location: gallery.php?project_id=' . $projectId);
            exit;
        }
    } elseif ($action === 'delete') {
        $id = max(1, (int) ($_GET['delete_image'] ?? $_POST['id'] ?? 0));
        $stmt = $pdo->prepare('SELECT image_path FROM portfolio_gallery WHERE id=:id');
        $stmt->execute([':id' => $id]);
        $path = (string) $stmt->fetchColumn();
        $pdo->prepare('DELETE FROM portfolio_gallery WHERE id=:id')->execute([':id' => $id]);
        portfolioAdminDeleteFile($path);
        $_SESSION['portfolio_flash'] = 'Gallery image deleted.';
        header('Location: gallery.php?project_id=' . $projectId);
        exit;
    } elseif ($action === 'bulk_delete') {
        $ids = $_POST['delete_ids'] ?? [];
        if (is_array($ids) && $ids !== []) {
            $select = $pdo->prepare('SELECT image_path FROM portfolio_gallery WHERE id=:id AND project_id=:project_id');
            $delete = $pdo->prepare('DELETE FROM portfolio_gallery WHERE id=:id AND project_id=:project_id');
            foreach ($ids as $id) {
                $select->execute([':id' => (int) $id, ':project_id' => $projectId]);
                $path = (string) $select->fetchColumn();
                $delete->execute([':id' => (int) $id, ':project_id' => $projectId]);
                portfolioAdminDeleteFile($path);
            }
            $_SESSION['portfolio_flash'] = 'Selected gallery images deleted.';
            header('Location: gallery.php?project_id=' . $projectId);
            exit;
        }
    }
}

$projects = $pdo->query('SELECT id, title FROM portfolio_projects ORDER BY title ASC')->fetchAll();
$gallery = [];
if ($projectId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM portfolio_gallery WHERE project_id=:project_id ORDER BY display_order ASC, id ASC');
    $stmt->execute([':project_id' => $projectId]);
    $gallery = $stmt->fetchAll();
}
$flash = $_SESSION['portfolio_flash'] ?? '';
unset($_SESSION['portfolio_flash']);
$pageTitle = 'Portfolio Gallery';
require dirname(__DIR__) . '/includes/header.php';
?>
<style>.portfolio-admin-nav{display:flex;gap:10px;flex-wrap:wrap;margin:18px 0 26px}.portfolio-admin-nav a{padding:9px 13px;border:1px solid var(--line);border-radius:4px;color:var(--green);font-weight:700;text-decoration:none}.gallery-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:18px}.gallery-card{padding:12px;border:1px solid var(--line);border-radius:7px;background:#fff}.gallery-card img{width:100%;height:160px;object-fit:cover;border-radius:6px}.gallery-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.gallery-actions button{padding:8px 10px;border:1px solid #a12626;border-radius:4px;background:#fff;color:#a12626;font-weight:700;cursor:pointer}@media(max-width:850px){.gallery-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.gallery-grid{grid-template-columns:1fr}}</style>
<header class="admin-header"><a class="admin-brand" href="../dashboard.php">Design24 Studio Admin</a><div class="admin-user"><span>Signed in as <?=e(currentAdminName())?></span><form method="post" action="../logout.php"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><button class="logout-button">Logout</button></form></div></header>
<main class="admin-main"><div class="settings-toolbar"><a href="dashboard.php">← Portfolio Dashboard</a><a href="../../portfolio.php" target="_blank">View Portfolio ↗</a></div><section class="panel"><h1>Portfolio Gallery</h1><nav class="portfolio-admin-nav"><a href="dashboard.php">Dashboard</a><a href="categories.php">Categories</a><a href="projects.php">Projects</a><a href="gallery.php">Gallery</a><a href="videos.php">Videos</a></nav><?php if($flash):?><p class="success"><?=e($flash)?></p><?php endif;?><?php if($errors):?><div class="error"><ul class="error-list"><?php foreach($errors as$error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="settings-section"><h2>Select Project</h2><form method="get"><div class="field"><label>Project</label><select name="project_id" onchange="this.form.submit()"><option value="">Choose project</option><?php foreach($projects as$project):?><option value="<?=$project['id']?>"<?=$projectId===(int)$project['id']?' selected':''?>><?=e($project['title'])?></option><?php endforeach;?></select></div></form></section>
<?php if($projectId>0):?><section class="settings-section"><h2>Upload Gallery Images</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="upload"><input type="hidden" name="project_id" value="<?=$projectId?>"><div class="field"><label>Bulk upload images</label><input type="file" name="gallery_images[]" accept="image/jpeg,image/png,image/webp" multiple><small class="help">JPG, PNG or WebP. Maximum 5 MB per image.</small></div><div class="form-actions"><button class="primary-button">Upload Images</button></div></form></section>
<section class="settings-section"><h2>Project Gallery</h2><form method="post" onsubmit="return confirm('Save gallery changes?');"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="save_gallery"><input type="hidden" name="project_id" value="<?=$projectId?>"><div class="gallery-grid"><?php foreach($gallery as$image):?><article class="gallery-card"><img src="../../<?=e($image['image_path'])?>" alt=""><label><input type="checkbox" name="delete_ids[]" value="<?=$image['id']?>"> Select for bulk delete</label><div class="field"><label>Caption</label><input name="caption[<?=$image['id']?>]" value="<?=e($image['caption'])?>"></div><div class="field"><label>Order</label><input type="number" name="display_order[<?=$image['id']?>]" value="<?=e((string)$image['display_order'])?>" min="0" max="9999"></div><div class="gallery-actions"><button type="submit" formaction="gallery.php?project_id=<?=$projectId?>&delete_image=<?=$image['id']?>" formmethod="post" name="action" value="delete" onclick="return confirm('Delete this image?');">Delete</button></div></article><?php endforeach;?></div><div class="form-actions"><button class="primary-button">Save Captions & Order</button><button class="secondary-admin-button" type="submit" name="action" value="bulk_delete" onclick="return confirm('Delete selected images?');">Bulk Delete Selected</button></div></form></section><?php endif;?></section></main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

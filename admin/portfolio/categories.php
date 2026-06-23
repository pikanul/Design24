<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
requireAdmin();

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrfIsValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        $errors[] = 'Your session expired. Refresh and try again.';
    } elseif ($action === 'save') {
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $name = trim((string) ($_POST['name'] ?? ''));
        $slugInput = trim((string) ($_POST['slug'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $parentId = (int) ($_POST['parent_id'] ?? 0);
        $order = filter_var($_POST['menu_order'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9999]]);
        if ($name === '' || mb_strlen($name) > 180) $errors[] = 'Category name is required and must be under 180 characters.';
        if (mb_strlen($description) > 2000) $errors[] = 'Description is too long.';
        if ($order === false) $errors[] = 'Menu order must be between 0 and 9999.';
        if ($parentId === $id && $id > 0) $errors[] = 'A category cannot be its own parent.';

        $old = ['hero_image' => '', 'hero_video' => ''];
        if ($id > 0) {
            $oldStmt = $pdo->prepare('SELECT hero_image, hero_video FROM portfolio_categories WHERE id=:id');
            $oldStmt->execute([':id' => $id]);
            $old = $oldStmt->fetch() ?: $old;
        }
        $heroImageUpload = portfolioAdminUpload('hero_image', 'public/uploads/portfolio/category-hero/images');
        $heroVideoUpload = portfolioAdminVideoUpload('hero_video');
        if ($heroImageUpload['error'] !== '') $errors[] = $heroImageUpload['error'];
        if ($heroVideoUpload['error'] !== '') $errors[] = $heroVideoUpload['error'];

        if ($errors === []) {
            $slug = portfolioAdminUniqueSlug($pdo, 'portfolio_categories', $slugInput !== '' ? $slugInput : $name, $id);
            $heroImage = $heroImageUpload['present'] ? $heroImageUpload['path'] : (string) $old['hero_image'];
            $heroVideo = $heroVideoUpload['present'] ? $heroVideoUpload['path'] : (string) $old['hero_video'];
            if (isset($_POST['remove_hero_image'])) $heroImage = '';
            if (isset($_POST['remove_hero_video'])) $heroVideo = '';
            $params = [':name' => $name, ':slug' => $slug, ':description' => $description, ':hero_image' => $heroImage, ':hero_video' => $heroVideo, ':parent_id' => $parentId > 0 ? $parentId : null, ':menu_order' => (int) $order, ':is_active' => isset($_POST['is_active']) ? 1 : 0];
            if ($id > 0) {
                $params[':id'] = $id;
                $stmt = $pdo->prepare('UPDATE portfolio_categories SET name=:name, slug=:slug, description=:description, hero_image=:hero_image, hero_video=:hero_video, parent_id=:parent_id, menu_order=:menu_order, is_active=:is_active, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
            } else {
                $stmt = $pdo->prepare('INSERT INTO portfolio_categories (name, slug, description, hero_image, hero_video, parent_id, menu_order, is_active) VALUES (:name, :slug, :description, :hero_image, :hero_video, :parent_id, :menu_order, :is_active)');
            }
            $stmt->execute($params);
            if ($heroImage !== $old['hero_image']) portfolioAdminDeleteFile((string) $old['hero_image']);
            if ($heroVideo !== $old['hero_video']) portfolioAdminDeleteFile((string) $old['hero_video']);
            $_SESSION['portfolio_flash'] = 'Category saved.';
            header('Location: categories.php');
            exit;
        } else {
            if ($heroImageUpload['path'] !== '') portfolioAdminDeleteFile($heroImageUpload['path']);
            if ($heroVideoUpload['path'] !== '') portfolioAdminDeleteFile($heroVideoUpload['path']);
        }
    } elseif ($action === 'toggle') {
        $id = max(1, (int) ($_POST['id'] ?? 0));
        $status = (int) ($_POST['new_status'] ?? 0) === 1 ? 1 : 0;
        $pdo->prepare('UPDATE portfolio_categories SET is_active=:status, updated_at=CURRENT_TIMESTAMP WHERE id=:id')->execute([':status' => $status, ':id' => $id]);
        $_SESSION['portfolio_flash'] = $status ? 'Category enabled.' : 'Category disabled.';
        header('Location: categories.php');
        exit;
    } elseif ($action === 'delete') {
        $id = max(1, (int) ($_POST['id'] ?? 0));
        $stmt = $pdo->prepare('SELECT (SELECT COUNT(*) FROM portfolio_categories WHERE parent_id=:id) AS child_count, (SELECT COUNT(*) FROM portfolio_projects WHERE category_id=:id) AS project_count');
        $stmt->execute([':id' => $id]);
        $counts = $stmt->fetch();
        if ((int) $counts['child_count'] > 0 || (int) $counts['project_count'] > 0) {
            $errors[] = 'This category has subcategories or projects. Disable it instead, or move/delete those items first.';
        } else {
            $pdo->prepare('DELETE FROM portfolio_categories WHERE id=:id')->execute([':id' => $id]);
            $_SESSION['portfolio_flash'] = 'Category deleted.';
            header('Location: categories.php');
            exit;
        }
    }
}

$categories = portfolioAdminCategories($pdo);
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM portfolio_categories WHERE id = :id');
    $stmt->execute([':id' => (int) $_GET['edit']]);
    $edit = $stmt->fetch();
}
$form = $edit ?: ['id' => '', 'name' => '', 'slug' => '', 'description' => '', 'hero_image' => '', 'hero_video' => '', 'parent_id' => '', 'menu_order' => count($categories) + 1, 'is_active' => 1];
$flash = $_SESSION['portfolio_flash'] ?? '';
unset($_SESSION['portfolio_flash']);

$pageTitle = 'Portfolio Categories';
require dirname(__DIR__) . '/includes/header.php';
?>
<style>.portfolio-admin-nav{display:flex;gap:10px;flex-wrap:wrap;margin:18px 0 26px}.portfolio-admin-nav a{padding:9px 13px;border:1px solid var(--line);border-radius:4px;color:var(--green);font-weight:700;text-decoration:none}.portfolio-table{width:100%;border-collapse:collapse;margin-top:18px}.portfolio-table th,.portfolio-table td{padding:11px;border-bottom:1px solid var(--line);text-align:left}.portfolio-actions{display:flex;gap:8px;flex-wrap:wrap}.portfolio-actions a,.portfolio-actions button{padding:8px 10px;border:1px solid var(--green);border-radius:4px;background:#fff;color:var(--green);font-weight:700;text-decoration:none;cursor:pointer}.portfolio-actions button.danger{border-color:#a12626;color:#a12626}.portfolio-preview{display:block;max-width:220px;max-height:130px;margin-top:10px;border:1px solid var(--line);border-radius:6px}@media(max-width:750px){.portfolio-table,.portfolio-table tbody,.portfolio-table tr,.portfolio-table td{display:block}.portfolio-table thead{display:none}.portfolio-table tr{padding:12px;border:1px solid var(--line);border-radius:6px;margin-bottom:12px}.portfolio-table td{border:0;padding:6px 0}}</style>
<header class="admin-header"><a class="admin-brand" href="../dashboard.php">Design24 Studio Admin</a><div class="admin-user"><span>Signed in as <?= e(currentAdminName()) ?></span><form method="post" action="../logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><button class="logout-button">Logout</button></form></div></header>
<main class="admin-main"><div class="settings-toolbar"><a href="dashboard.php">← Portfolio Dashboard</a><a href="../../portfolio.php" target="_blank">View Portfolio ↗</a></div><section class="panel"><h1>Portfolio Categories</h1><nav class="portfolio-admin-nav"><a href="dashboard.php">Dashboard</a><a href="categories.php">Categories</a><a href="projects.php">Projects</a><a href="gallery.php">Gallery</a><a href="videos.php">Videos</a></nav><?php if($flash):?><p class="success"><?=e($flash)?></p><?php endif;?><?php if($errors):?><div class="error"><ul class="error-list"><?php foreach($errors as$error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="settings-section"><h2><?= $edit ? 'Edit' : 'Add' ?> Category</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e((string)$form['id'])?>"><div class="settings-grid"><div class="field"><label>Category Name</label><input name="name" value="<?=e($form['name'])?>" maxlength="180" required></div><div class="field"><label>Slug</label><input name="slug" value="<?=e($form['slug'])?>" maxlength="190"><small class="help">Leave blank to generate automatically.</small></div><div class="field"><label>Parent Category</label><select name="parent_id"><?= portfolioAdminCategoryOptions($categories, $form['id'] !== '' ? (int) $form['id'] : null) ?></select><script>document.currentScript.previousElementSibling.value="<?=e((string)$form['parent_id'])?>";</script></div><div class="field"><label>Menu Order</label><input type="number" name="menu_order" min="0" max="9999" value="<?=e((string)$form['menu_order'])?>"></div></div><div class="field"><label>Description</label><textarea name="description"><?=e($form['description'])?></textarea></div><div class="settings-grid"><div class="field"><label>Hero Image</label><input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp"><small class="help">JPG, PNG, or WebP — maximum 5 MB.</small><?php if(portfolioAssetIsSafe((string)$form['hero_image'])):?><img class="portfolio-preview" src="../../<?=e($form['hero_image'])?>" alt="Current hero image"><label><input type="checkbox" name="remove_hero_image"> Remove image</label><?php endif;?></div><div class="field"><label>Hero Video</label><input type="file" name="hero_video" accept="video/mp4,video/webm"><small class="help">MP4 or WebM — maximum 50 MB. Video displays first; the image is used as its fallback.</small><?php if(portfolioVideoAssetIsSafe((string)$form['hero_video'])):?><video class="portfolio-preview" src="../../<?=e($form['hero_video'])?>" controls muted playsinline preload="metadata"></video><label><input type="checkbox" name="remove_hero_video"> Remove video</label><?php endif;?></div></div><div class="checkbox-field"><input id="is_active" type="checkbox" name="is_active" value="1"<?=((int)$form['is_active']===1)?' checked':''?>><label for="is_active">Active</label></div><div class="form-actions"><button class="primary-button">Save Category</button><?php if($edit):?><a class="secondary-admin-button" href="categories.php">Cancel</a><?php endif;?></div></form></section>
<section class="settings-section"><h2>All Categories</h2><table class="portfolio-table"><thead><tr><th>Order</th><th>Category Name</th><th>Parent</th><th>Status</th><th>Projects</th><th>Actions</th></tr></thead><tbody><?php foreach($categories as$category):?><tr><td><?=e((string)$category['menu_order'])?></td><td><strong><?=e($category['name'])?></strong><br><small><?=e($category['slug'])?></small></td><td><?=e($category['parent_name'] ?: 'Main category')?></td><td><?=((int)$category['is_active']===1)?'Active':'Inactive'?></td><td><?=e((string)$category['project_count'])?></td><td><div class="portfolio-actions"><a href="?edit=<?=$category['id']?>">Edit</a><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$category['id']?>"><input type="hidden" name="new_status" value="<?=((int)$category['is_active']===1)?'0':'1'?>"><button><?=((int)$category['is_active']===1)?'Disable':'Enable'?></button></form><form method="post" onsubmit="return confirm('Delete this category?');"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$category['id']?>"><button class="danger">Delete</button></form></div></td></tr><?php endforeach;?></tbody></table></section></section></main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

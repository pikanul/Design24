<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
requireAdmin();

$pdo = db();
$errors = [];
$categories = portfolioAdminCategories($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrfIsValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        $errors[] = 'Your session expired. Refresh and try again.';
    } elseif ($action === 'save') {
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $title = trim((string) ($_POST['title'] ?? ''));
        $slugInput = trim((string) ($_POST['slug'] ?? ''));
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $short = trim((string) ($_POST['short_description'] ?? ''));
        $full = trim((string) ($_POST['full_description'] ?? ''));
        $status = in_array((string) ($_POST['project_status'] ?? 'Completed'), ['Ongoing', 'Completed'], true) ? (string) $_POST['project_status'] : 'Completed';
        $location = trim((string) ($_POST['project_location'] ?? ''));
        $client = trim((string) ($_POST['client_name'] ?? ''));
        $year = trim((string) ($_POST['completion_year'] ?? ''));
        $area = trim((string) ($_POST['project_area'] ?? ''));
        $youtube = trim((string) ($_POST['youtube_url'] ?? ''));
        $order = filter_var($_POST['display_order'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9999]]);
        $seoTitle = trim((string) ($_POST['seo_title'] ?? ''));
        $seoDescription = trim((string) ($_POST['seo_description'] ?? ''));
        $keywords = trim((string) ($_POST['meta_keywords'] ?? ''));

        if ($title === '' || mb_strlen($title) > 220) $errors[] = 'Project title is required and must be under 220 characters.';
        if ($categoryId <= 0) $errors[] = 'Choose a category.';
        if (mb_strlen($short) > 600) $errors[] = 'Short description must be under 600 characters.';
        if (mb_strlen($full) > 8000) $errors[] = 'Full description is too long.';
        if ($order === false) $errors[] = 'Display order must be between 0 and 9999.';
        if (!portfolioAdminYoutubeIsValid($youtube)) $errors[] = 'Enter a valid YouTube URL or leave it blank.';

        $old = ['featured_image' => '', 'og_image' => ''];
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT featured_image, og_image FROM portfolio_projects WHERE id=:id');
            $stmt->execute([':id' => $id]);
            $old = $stmt->fetch() ?: $old;
        }

        $featuredUpload = portfolioAdminUpload('featured_image');
        $ogUpload = portfolioAdminUpload('og_image');
        if ($featuredUpload['error'] !== '') $errors[] = $featuredUpload['error'];
        if ($ogUpload['error'] !== '') $errors[] = $ogUpload['error'];

        if ($errors === []) {
            $featured = $featuredUpload['present'] ? $featuredUpload['path'] : (string) $old['featured_image'];
            $og = $ogUpload['present'] ? $ogUpload['path'] : (string) $old['og_image'];
            if (isset($_POST['remove_featured_image'])) $featured = '';
            if (isset($_POST['remove_og_image'])) $og = '';
            $slug = portfolioAdminUniqueSlug($pdo, 'portfolio_projects', $slugInput !== '' ? $slugInput : $title, $id);
            $params = [
                ':category_id' => $categoryId, ':title' => $title, ':slug' => $slug, ':short_description' => $short,
                ':full_description' => $full, ':featured_image' => $featured, ':project_status' => $status,
                ':project_location' => $location, ':client_name' => $client, ':completion_year' => $year,
                ':project_area' => $area, ':youtube_url' => $youtube, ':is_featured' => isset($_POST['is_featured']) ? 1 : 0,
                ':is_active' => isset($_POST['is_active']) ? 1 : 0, ':display_order' => (int) $order,
                ':seo_title' => $seoTitle, ':seo_description' => $seoDescription, ':meta_keywords' => $keywords, ':og_image' => $og,
            ];
            if ($id > 0) {
                $params[':id'] = $id;
                $sql = 'UPDATE portfolio_projects SET category_id=:category_id,title=:title,slug=:slug,short_description=:short_description,full_description=:full_description,featured_image=:featured_image,project_status=:project_status,project_location=:project_location,client_name=:client_name,completion_year=:completion_year,project_area=:project_area,youtube_url=:youtube_url,is_featured=:is_featured,is_active=:is_active,display_order=:display_order,seo_title=:seo_title,seo_description=:seo_description,meta_keywords=:meta_keywords,og_image=:og_image,updated_at=CURRENT_TIMESTAMP WHERE id=:id';
            } else {
                $sql = 'INSERT INTO portfolio_projects (category_id,title,slug,short_description,full_description,featured_image,project_status,project_location,client_name,completion_year,project_area,youtube_url,is_featured,is_active,display_order,seo_title,seo_description,meta_keywords,og_image) VALUES (:category_id,:title,:slug,:short_description,:full_description,:featured_image,:project_status,:project_location,:client_name,:completion_year,:project_area,:youtube_url,:is_featured,:is_active,:display_order,:seo_title,:seo_description,:meta_keywords,:og_image)';
            }
            $pdo->prepare($sql)->execute($params);
            if ($featured !== $old['featured_image']) portfolioAdminDeleteFile((string) $old['featured_image']);
            if ($og !== $old['og_image']) portfolioAdminDeleteFile((string) $old['og_image']);
            $_SESSION['portfolio_flash'] = 'Project saved.';
            header('Location: projects.php');
            exit;
        } else {
            if ($featuredUpload['path'] !== '') portfolioAdminDeleteFile($featuredUpload['path']);
            if ($ogUpload['path'] !== '') portfolioAdminDeleteFile($ogUpload['path']);
        }
    } elseif ($action === 'delete') {
        $id = max(1, (int) ($_POST['id'] ?? 0));
        $stmt = $pdo->prepare('SELECT featured_image, og_image FROM portfolio_projects WHERE id=:id');
        $stmt->execute([':id' => $id]);
        $project = $stmt->fetch();
        if ($project) {
            $gallery = $pdo->prepare('SELECT image_path FROM portfolio_gallery WHERE project_id=:id');
            $gallery->execute([':id' => $id]);
            $files = $gallery->fetchAll();
            $pdo->prepare('DELETE FROM portfolio_projects WHERE id=:id')->execute([':id' => $id]);
            portfolioAdminDeleteFile((string) $project['featured_image']);
            portfolioAdminDeleteFile((string) $project['og_image']);
            foreach ($files as $file) portfolioAdminDeleteFile((string) $file['image_path']);
            $_SESSION['portfolio_flash'] = 'Project deleted.';
            header('Location: projects.php');
            exit;
        }
    } elseif ($action === 'duplicate') {
        $id = max(1, (int) ($_POST['id'] ?? 0));
        $stmt = $pdo->prepare('SELECT * FROM portfolio_projects WHERE id=:id');
        $stmt->execute([':id' => $id]);
        $project = $stmt->fetch();
        if ($project) {
            $slug = portfolioAdminUniqueSlug($pdo, 'portfolio_projects', $project['slug'] . '-copy');
            $copy = $pdo->prepare('INSERT INTO portfolio_projects (category_id,title,slug,short_description,full_description,featured_image,project_status,project_location,client_name,completion_year,project_area,youtube_url,is_featured,is_active,display_order,seo_title,seo_description,meta_keywords,og_image) VALUES (:category_id,:title,:slug,:short_description,:full_description,:featured_image,:project_status,:project_location,:client_name,:completion_year,:project_area,:youtube_url,0,0,:display_order,:seo_title,:seo_description,:meta_keywords,:og_image)');
            $copy->execute([':category_id'=>$project['category_id'],':title'=>$project['title'].' Copy',':slug'=>$slug,':short_description'=>$project['short_description'],':full_description'=>$project['full_description'],':featured_image'=>'',':project_status'=>$project['project_status'],':project_location'=>$project['project_location'],':client_name'=>$project['client_name'],':completion_year'=>$project['completion_year'],':project_area'=>$project['project_area'],':youtube_url'=>$project['youtube_url'],':display_order'=>$project['display_order'] + 1,':seo_title'=>$project['seo_title'],':seo_description'=>$project['seo_description'],':meta_keywords'=>$project['meta_keywords'],':og_image'=>'']);
            $_SESSION['portfolio_flash'] = 'Project duplicated as inactive.';
            header('Location: projects.php');
            exit;
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM portfolio_projects WHERE id=:id');
    $stmt->execute([':id' => (int) $_GET['edit']]);
    $edit = $stmt->fetch();
}
$projects = $pdo->query('SELECT p.*, c.name AS category_name FROM portfolio_projects p JOIN portfolio_categories c ON c.id=p.category_id ORDER BY p.display_order ASC, p.id DESC')->fetchAll();
$form = $edit ?: ['id'=>'','category_id'=>'','title'=>'','slug'=>'','short_description'=>'','full_description'=>'','featured_image'=>'','project_status'=>'Completed','project_location'=>'','client_name'=>'','completion_year'=>'','project_area'=>'','youtube_url'=>'','is_featured'=>0,'is_active'=>1,'display_order'=>count($projects)+1,'seo_title'=>'','seo_description'=>'','meta_keywords'=>'','og_image'=>''];
$flash = $_SESSION['portfolio_flash'] ?? '';
unset($_SESSION['portfolio_flash']);
$pageTitle = 'Portfolio Projects';
require dirname(__DIR__) . '/includes/header.php';
?>
<style>.portfolio-admin-nav,.portfolio-actions{display:flex;gap:10px;flex-wrap:wrap}.portfolio-admin-nav{margin:18px 0 26px}.portfolio-admin-nav a,.portfolio-actions a,.portfolio-actions button{padding:9px 13px;border:1px solid var(--green);border-radius:4px;background:#fff;color:var(--green);font-weight:700;text-decoration:none;cursor:pointer}.portfolio-actions button.danger{border-color:#a12626;color:#a12626}.portfolio-table{width:100%;border-collapse:collapse;margin-top:18px}.portfolio-table th,.portfolio-table td{padding:11px;border-bottom:1px solid var(--line);text-align:left;vertical-align:middle}.portfolio-thumb{width:92px;height:64px;object-fit:cover;border-radius:6px;background:#eef3ef}.portfolio-preview{display:block;max-width:220px;max-height:130px;margin-top:10px;object-fit:cover;border:1px solid var(--line);border-radius:6px}@media(max-width:850px){.portfolio-table,.portfolio-table tbody,.portfolio-table tr,.portfolio-table td{display:block}.portfolio-table thead{display:none}.portfolio-table tr{padding:12px;border:1px solid var(--line);border-radius:6px;margin-bottom:12px}.portfolio-table td{border:0;padding:6px 0}}</style>
<header class="admin-header"><a class="admin-brand" href="../dashboard.php">Design24 Studio Admin</a><div class="admin-user"><span>Signed in as <?=e(currentAdminName())?></span><form method="post" action="../logout.php"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><button class="logout-button">Logout</button></form></div></header>
<main class="admin-main"><div class="settings-toolbar"><a href="dashboard.php">← Portfolio Dashboard</a><a href="../../portfolio.php" target="_blank">View Portfolio ↗</a></div><section class="panel"><h1>Portfolio Projects</h1><nav class="portfolio-admin-nav"><a href="dashboard.php">Dashboard</a><a href="categories.php">Categories</a><a href="projects.php">Projects</a><a href="gallery.php">Gallery</a><a href="videos.php">Videos</a></nav><?php if($flash):?><p class="success"><?=e($flash)?></p><?php endif;?><?php if($errors):?><div class="error"><ul class="error-list"><?php foreach($errors as$error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="settings-section"><h2><?= $edit ? 'Edit' : 'Add' ?> Project</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e((string)$form['id'])?>"><div class="settings-grid"><div class="field"><label>Project Title</label><input name="title" value="<?=e($form['title'])?>" maxlength="220" required></div><div class="field"><label>Slug</label><input name="slug" value="<?=e($form['slug'])?>" maxlength="220"><small class="help">Leave blank to generate automatically.</small></div><div class="field"><label>Category</label><select name="category_id" required><option value="">Choose category</option><?php foreach($categories as$category):?><option value="<?=$category['id']?>"<?=((int)$form['category_id']===(int)$category['id'])?' selected':''?>><?=e(($category['parent_id']?'— ':'').$category['name'])?></option><?php endforeach;?></select></div><div class="field"><label>Project Status</label><select name="project_status"><option value="Completed"<?=$form['project_status']==='Completed'?' selected':''?>>Completed</option><option value="Ongoing"<?=$form['project_status']==='Ongoing'?' selected':''?>>Ongoing</option></select></div><div class="field"><label>Location</label><input name="project_location" value="<?=e($form['project_location'])?>" maxlength="180"></div><div class="field"><label>Client Name</label><input name="client_name" value="<?=e($form['client_name'])?>" maxlength="180"></div><div class="field"><label>Completion Year</label><input name="completion_year" value="<?=e($form['completion_year'])?>" maxlength="20"></div><div class="field"><label>Project Area</label><input name="project_area" value="<?=e($form['project_area'])?>" maxlength="80"></div><div class="field"><label>YouTube Video URL</label><input type="url" name="youtube_url" value="<?=e($form['youtube_url'])?>" maxlength="500"></div><div class="field"><label>Display Order</label><input type="number" name="display_order" min="0" max="9999" value="<?=e((string)$form['display_order'])?>"></div></div><div class="field"><label>Short Description</label><textarea name="short_description" maxlength="600"><?=e($form['short_description'])?></textarea></div><div class="field"><label>Full Description</label><textarea name="full_description"><?=e($form['full_description'])?></textarea></div><div class="settings-grid"><div class="field"><label>Featured Image</label><input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp"><?php if(portfolioAssetIsSafe((string)$form['featured_image'])):?><img class="portfolio-preview" src="../../<?=e($form['featured_image'])?>" alt=""><label><input type="checkbox" name="remove_featured_image"> Remove image</label><?php endif;?></div><div class="field"><label>Open Graph Image</label><input type="file" name="og_image" accept="image/jpeg,image/png,image/webp"><?php if(portfolioAssetIsSafe((string)$form['og_image'])):?><img class="portfolio-preview" src="../../<?=e($form['og_image'])?>" alt=""><label><input type="checkbox" name="remove_og_image"> Remove image</label><?php endif;?></div></div><div class="settings-grid"><div class="field"><label>SEO Title</label><input name="seo_title" value="<?=e($form['seo_title'])?>" maxlength="220"></div><div class="field"><label>Meta Keywords</label><input name="meta_keywords" value="<?=e($form['meta_keywords'])?>" maxlength="320"></div></div><div class="field"><label>SEO Description</label><textarea name="seo_description" maxlength="320"><?=e($form['seo_description'])?></textarea></div><div class="checkbox-field"><input id="is_featured" name="is_featured" type="checkbox" value="1"<?=((int)$form['is_featured']===1)?' checked':''?>><label for="is_featured">Featured Project</label></div><div class="checkbox-field"><input id="is_active" name="is_active" type="checkbox" value="1"<?=((int)$form['is_active']===1)?' checked':''?>><label for="is_active">Published / Active</label></div><div class="form-actions"><button class="primary-button">Save Project</button><?php if($edit):?><a class="secondary-admin-button" href="projects.php">Cancel</a><?php endif;?></div></form></section>
<section class="settings-section"><h2>All Projects</h2><table class="portfolio-table"><thead><tr><th>Image</th><th>Project Title</th><th>Category</th><th>Status</th><th>Featured</th><th>Order</th><th>Actions</th></tr></thead><tbody><?php foreach($projects as$project):?><tr><td><?php if(portfolioAssetIsSafe((string)$project['featured_image'])):?><img class="portfolio-thumb" src="../../<?=e($project['featured_image'])?>" alt=""><?php else:?><span class="portfolio-thumb"></span><?php endif;?></td><td><strong><?=e($project['title'])?></strong><br><small><?=e($project['slug'])?></small></td><td><?=e($project['category_name'])?></td><td><?=e($project['project_status'])?> · <?=((int)$project['is_active']===1)?'Active':'Inactive'?></td><td><?=((int)$project['is_featured']===1)?'Yes':'No'?></td><td><?=e((string)$project['display_order'])?></td><td><div class="portfolio-actions"><a href="../../portfolio/project/<?=e($project['slug'])?>" target="_blank">View</a><a href="?edit=<?=$project['id']?>">Edit</a><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?=$project['id']?>"><button>Duplicate</button></form><form method="post" onsubmit="return confirm('Delete this project and its gallery images?');"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$project['id']?>"><button class="danger">Delete</button></form></div></td></tr><?php endforeach;?></tbody></table></section></section></main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

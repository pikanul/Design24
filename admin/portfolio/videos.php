<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
requireAdmin();

$pdo = db();
$errors = [];
$projectId = max(0, (int) ($_GET['project_id'] ?? $_POST['project_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrfIsValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        $errors[] = 'Your session expired. Refresh and try again.';
    } elseif ($action === 'save') {
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $projectId = max(1, (int) ($_POST['project_id'] ?? 0));
        $url = trim((string) ($_POST['youtube_url'] ?? ''));
        $title = trim((string) ($_POST['video_title'] ?? ''));
        $order = filter_var($_POST['display_order'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9999]]);
        if (!portfolioAdminYoutubeIsValid($url) || $url === '') $errors[] = 'Paste a valid YouTube URL.';
        if (mb_strlen($title) > 220) $errors[] = 'Video title must be under 220 characters.';
        if ($order === false) $errors[] = 'Display order must be between 0 and 9999.';
        if ($errors === []) {
            $params = [':project_id' => $projectId, ':youtube_url' => $url, ':video_title' => $title, ':display_order' => (int) $order];
            if ($id > 0) {
                $params[':id'] = $id;
                $stmt = $pdo->prepare('UPDATE portfolio_videos SET project_id=:project_id, youtube_url=:youtube_url, video_title=:video_title, display_order=:display_order WHERE id=:id');
            } else {
                $stmt = $pdo->prepare('INSERT INTO portfolio_videos (project_id, youtube_url, video_title, display_order) VALUES (:project_id, :youtube_url, :video_title, :display_order)');
            }
            $stmt->execute($params);
            $_SESSION['portfolio_flash'] = 'Video saved.';
            header('Location: videos.php?project_id=' . $projectId);
            exit;
        }
    } elseif ($action === 'delete') {
        $id = max(1, (int) ($_POST['id'] ?? 0));
        $pdo->prepare('DELETE FROM portfolio_videos WHERE id=:id')->execute([':id' => $id]);
        $_SESSION['portfolio_flash'] = 'Video deleted.';
        header('Location: videos.php?project_id=' . $projectId);
        exit;
    }
}

$projects = $pdo->query('SELECT id, title FROM portfolio_projects ORDER BY title ASC')->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM portfolio_videos WHERE id=:id');
    $stmt->execute([':id' => (int) $_GET['edit']]);
    $edit = $stmt->fetch();
    if ($edit) $projectId = (int) $edit['project_id'];
}
$videos = [];
if ($projectId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM portfolio_videos WHERE project_id=:project_id ORDER BY display_order ASC, id ASC');
    $stmt->execute([':project_id' => $projectId]);
    $videos = $stmt->fetchAll();
}
$form = $edit ?: ['id'=>'','project_id'=>$projectId,'youtube_url'=>'','video_title'=>'','display_order'=>count($videos)+1];
$flash = $_SESSION['portfolio_flash'] ?? '';
unset($_SESSION['portfolio_flash']);
$pageTitle = 'Portfolio Videos';
require dirname(__DIR__) . '/includes/header.php';
?>
<style>.portfolio-admin-nav,.portfolio-actions{display:flex;gap:10px;flex-wrap:wrap}.portfolio-admin-nav{margin:18px 0 26px}.portfolio-admin-nav a,.portfolio-actions a,.portfolio-actions button{padding:9px 13px;border:1px solid var(--green);border-radius:4px;background:#fff;color:var(--green);font-weight:700;text-decoration:none;cursor:pointer}.portfolio-actions button{border-color:#a12626;color:#a12626}.portfolio-video-list{display:grid;gap:14px;margin-top:18px}.portfolio-video-item{display:grid;grid-template-columns:180px 1fr auto;gap:14px;align-items:center;padding:14px;border:1px solid var(--line);border-radius:7px;background:#fff}.portfolio-video-item img{width:180px;height:100px;object-fit:cover;border-radius:6px}@media(max-width:750px){.portfolio-video-item{grid-template-columns:1fr}.portfolio-video-item img{width:100%;height:auto}}</style>
<header class="admin-header"><a class="admin-brand" href="../dashboard.php">Design24 Studio Admin</a><div class="admin-user"><span>Signed in as <?=e(currentAdminName())?></span><form method="post" action="../logout.php"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><button class="logout-button">Logout</button></form></div></header>
<main class="admin-main"><div class="settings-toolbar"><a href="dashboard.php">← Portfolio Dashboard</a><a href="../../portfolio.php" target="_blank">View Portfolio ↗</a></div><section class="panel"><h1>Portfolio Videos</h1><nav class="portfolio-admin-nav"><a href="dashboard.php">Dashboard</a><a href="categories.php">Categories</a><a href="projects.php">Projects</a><a href="gallery.php">Gallery</a><a href="videos.php">Videos</a></nav><?php if($flash):?><p class="success"><?=e($flash)?></p><?php endif;?><?php if($errors):?><div class="error"><ul class="error-list"><?php foreach($errors as$error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="settings-section"><h2>Select Project</h2><form method="get"><div class="field"><label>Project</label><select name="project_id" onchange="this.form.submit()"><option value="">Choose project</option><?php foreach($projects as$project):?><option value="<?=$project['id']?>"<?=$projectId===(int)$project['id']?' selected':''?>><?=e($project['title'])?></option><?php endforeach;?></select></div></form></section>
<section class="settings-section"><h2><?= $edit ? 'Edit' : 'Add' ?> YouTube Video</h2><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e((string)$form['id'])?>"><div class="settings-grid"><div class="field"><label>Project</label><select name="project_id" required><option value="">Choose project</option><?php foreach($projects as$project):?><option value="<?=$project['id']?>"<?=((int)$form['project_id']===(int)$project['id'])?' selected':''?>><?=e($project['title'])?></option><?php endforeach;?></select></div><div class="field"><label>Display Order</label><input type="number" name="display_order" min="0" max="9999" value="<?=e((string)$form['display_order'])?>"></div></div><div class="field"><label>YouTube URL</label><input type="url" name="youtube_url" value="<?=e($form['youtube_url'])?>" maxlength="500" required><small class="help">Example: https://www.youtube.com/watch?v=XXXX</small></div><div class="field"><label>Video Title</label><input name="video_title" value="<?=e($form['video_title'])?>" maxlength="220"></div><div class="form-actions"><button class="primary-button">Save Video</button><?php if($edit):?><a class="secondary-admin-button" href="videos.php?project_id=<?=$projectId?>">Cancel</a><?php endif;?></div></form></section>
<?php if($projectId>0):?><section class="settings-section"><h2>Project Videos</h2><div class="portfolio-video-list"><?php foreach($videos as$video):?><?php $videoId=youtubeVideoIdFromUrl($video['youtube_url']);?><article class="portfolio-video-item"><img src="https://img.youtube.com/vi/<?=e($videoId)?>/hqdefault.jpg" alt=""><div><strong><?=e($video['video_title'] ?: 'Project Video')?></strong><p><?=e($video['youtube_url'])?></p><small>Video ID: <?=e($videoId)?> · Order <?=e((string)$video['display_order'])?></small></div><div class="portfolio-actions"><a href="<?=e($video['youtube_url'])?>" target="_blank" rel="noopener noreferrer">Preview</a><a href="?edit=<?=$video['id']?>">Edit</a><form method="post" onsubmit="return confirm('Delete this video?');"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="project_id" value="<?=$projectId?>"><input type="hidden" name="id" value="<?=$video['id']?>"><button>Delete</button></form></div></article><?php endforeach;?></div></section><?php endif;?></section></main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
requireAdmin();

$pdo = db();
$errors = [];
$settings = getPortfolioSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfIsValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        $errors[] = 'Your session expired. Refresh and try again.';
    } else {
        $title = trim((string) ($_POST['portfolio_title'] ?? 'Portfolio'));
        $subtitle = trim((string) ($_POST['portfolio_subtitle'] ?? ''));
        $opacity = filter_var($_POST['portfolio_overlay_opacity'] ?? 45, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 90]]);
        $limit = filter_var($_POST['portfolio_featured_limit'] ?? 6, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 24]]);
        if ($title === '' || mb_strlen($title) > 180) $errors[] = 'Portfolio title is required and must be under 180 characters.';
        if (mb_strlen($subtitle) > 400) $errors[] = 'Subtitle must be under 400 characters.';
        if ($opacity === false) $errors[] = 'Overlay opacity must be between 0 and 90.';
        if ($limit === false) $errors[] = 'Featured limit must be between 1 and 24.';

        $banner = portfolioAdminUpload('portfolio_banner_image');
        if ($banner['error'] !== '') $errors[] = $banner['error'];

        if ($errors === []) {
            $newBanner = $banner['present'] ? $banner['path'] : (string) $settings['portfolio_banner_image'];
            if (isset($_POST['remove_banner'])) $newBanner = '';
            $values = [
                'portfolio_title' => $title,
                'portfolio_subtitle' => $subtitle,
                'portfolio_banner_image' => $newBanner,
                'portfolio_overlay_opacity' => (string) $opacity,
                'portfolio_featured_limit' => (string) $limit,
                'portfolio_show_featured_home' => isset($_POST['portfolio_show_featured_home']) ? '1' : '0',
                'portfolio_show_residential_home' => isset($_POST['portfolio_show_residential_home']) ? '1' : '0',
                'portfolio_show_commercial_home' => isset($_POST['portfolio_show_commercial_home']) ? '1' : '0',
                'portfolio_show_videos_home' => isset($_POST['portfolio_show_videos_home']) ? '1' : '0',
            ];
            $stmt = $pdo->prepare('INSERT INTO site_settings (setting_group, setting_key, setting_value, setting_type, updated_at) VALUES ("portfolio_page", :key, :value, "text", CURRENT_TIMESTAMP) ON CONFLICT(setting_group, setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = CURRENT_TIMESTAMP');
            foreach ($values as $key => $value) {
                $stmt->execute([':key' => $key, ':value' => $value]);
            }
            if ($banner['present'] && $settings['portfolio_banner_image'] !== '' && $settings['portfolio_banner_image'] !== $newBanner) {
                portfolioAdminDeleteFile((string) $settings['portfolio_banner_image']);
            }
            $_SESSION['portfolio_flash'] = 'Portfolio settings saved.';
            header('Location: dashboard.php');
            exit;
        } elseif ($banner['path'] !== '') {
            portfolioAdminDeleteFile($banner['path']);
        }
    }
}

$projectCount = (int) $pdo->query('SELECT COUNT(*) FROM portfolio_projects')->fetchColumn();
$categoryCount = (int) $pdo->query('SELECT COUNT(*) FROM portfolio_categories')->fetchColumn();
$galleryCount = (int) $pdo->query('SELECT COUNT(*) FROM portfolio_gallery')->fetchColumn();
$videoCount = (int) $pdo->query('SELECT COUNT(*) FROM portfolio_videos')->fetchColumn();
$flash = $_SESSION['portfolio_flash'] ?? '';
unset($_SESSION['portfolio_flash']);

$pageTitle = 'Portfolio Management';
require dirname(__DIR__) . '/includes/header.php';
?>
<style>
.portfolio-admin-nav{display:flex;gap:10px;flex-wrap:wrap;margin:18px 0 26px}.portfolio-admin-nav a{padding:9px 13px;border:1px solid var(--line);border-radius:4px;color:var(--green);font-weight:700;text-decoration:none}.portfolio-stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.portfolio-stat{padding:18px;border:1px solid var(--line);border-radius:7px;background:#fbfcfb}.portfolio-stat strong{display:block;color:var(--green);font-size:1.8rem}.portfolio-preview{max-width:280px;max-height:120px;object-fit:cover;border:1px solid var(--line);border-radius:6px}@media(max-width:750px){.portfolio-stat-grid{grid-template-columns:1fr 1fr}}@media(max-width:520px){.portfolio-stat-grid{grid-template-columns:1fr}}
</style>
<header class="admin-header"><a class="admin-brand" href="../dashboard.php">Design24 Studio Admin</a><div class="admin-user"><span>Signed in as <?= e(currentAdminName()) ?></span><form method="post" action="../logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><button class="logout-button">Logout</button></form></div></header>
<main class="admin-main">
    <div class="settings-toolbar"><a href="../dashboard.php">← Dashboard</a><a href="../../portfolio.php" target="_blank">View Portfolio ↗</a></div>
    <section class="panel">
        <h1>Portfolio Management</h1>
        <p>Manage categories, projects, galleries, videos, page banner, and homepage featured-project settings.</p>
        <nav class="portfolio-admin-nav" aria-label="Portfolio management"><a href="dashboard.php">Dashboard</a><a href="categories.php">Categories</a><a href="projects.php">Projects</a><a href="gallery.php">Gallery</a><a href="videos.php">Videos</a></nav>
        <?php if ($flash): ?><p class="success"><?= e($flash) ?></p><?php endif; ?>
        <?php if ($errors): ?><div class="error"><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <div class="portfolio-stat-grid">
            <div class="portfolio-stat"><strong><?= $categoryCount ?></strong>Categories</div>
            <div class="portfolio-stat"><strong><?= $projectCount ?></strong>Projects</div>
            <div class="portfolio-stat"><strong><?= $galleryCount ?></strong>Gallery Images</div>
            <div class="portfolio-stat"><strong><?= $videoCount ?></strong>Videos</div>
        </div>
        <section class="settings-section">
            <h2>Portfolio Page Settings</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <div class="settings-grid">
                    <div class="field"><label>Hero title</label><input name="portfolio_title" value="<?= e($settings['portfolio_title']) ?>" maxlength="180" required></div>
                    <div class="field"><label>Overlay opacity (%)</label><input name="portfolio_overlay_opacity" type="number" min="0" max="90" value="<?= e($settings['portfolio_overlay_opacity']) ?>"></div>
                    <div class="field"><label>Featured projects on homepage</label><input name="portfolio_featured_limit" type="number" min="1" max="24" value="<?= e($settings['portfolio_featured_limit']) ?>"></div>
                </div>
                <div class="field"><label>Hero subtitle</label><textarea name="portfolio_subtitle" maxlength="400"><?= e($settings['portfolio_subtitle']) ?></textarea></div>
                <div class="field"><label>Banner image</label><input type="file" name="portfolio_banner_image" accept="image/jpeg,image/png,image/webp"><small class="help">JPG, PNG, or WebP. Maximum 5 MB.</small><?php if (portfolioAssetIsSafe((string) $settings['portfolio_banner_image'])): ?><img class="portfolio-preview" src="../../<?= e($settings['portfolio_banner_image']) ?>" alt=""><label><input type="checkbox" name="remove_banner"> Remove current banner</label><?php endif; ?></div>
                <div class="checkbox-field"><input id="show_featured" name="portfolio_show_featured_home" type="checkbox" value="1"<?= settingEnabled($settings, 'portfolio_show_featured_home') ? ' checked' : '' ?>><label for="show_featured">Show featured projects on homepage later</label></div>
                <div class="checkbox-field"><input id="show_res" name="portfolio_show_residential_home" type="checkbox" value="1"<?= settingEnabled($settings, 'portfolio_show_residential_home') ? ' checked' : '' ?>><label for="show_res">Allow residential projects in homepage featured area</label></div>
                <div class="checkbox-field"><input id="show_com" name="portfolio_show_commercial_home" type="checkbox" value="1"<?= settingEnabled($settings, 'portfolio_show_commercial_home') ? ' checked' : '' ?>><label for="show_com">Allow commercial projects in homepage featured area</label></div>
                <div class="checkbox-field"><input id="show_vid" name="portfolio_show_videos_home" type="checkbox" value="1"<?= settingEnabled($settings, 'portfolio_show_videos_home') ? ' checked' : '' ?>><label for="show_vid">Allow project videos in homepage featured area</label></div>
                <div class="form-actions"><button class="primary-button">Save Settings</button></div>
            </form>
        </section>
    </section>
</main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

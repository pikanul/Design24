<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$pageTitle = 'Dashboard';
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
    <section class="panel" aria-labelledby="dashboard-title">
        <h1 id="dashboard-title">Dashboard</h1>
        <p>Welcome, <?= e(currentAdminName()) ?>.</p>

        <div class="placeholder-grid">
            <article class="placeholder">
                <h2>Website Settings</h2>
                <p>Manage public website branding and shared layout areas.</p>
                <div class="settings-links"><a href="header-settings.php">Header Settings</a><a href="footer-settings.php">Footer Settings</a><a href="audio-settings.php">Website Audio</a><a href="visitor-report.php">Visitor Analytics</a><a href="testimonials.php">Client Feedback</a></div>
            </article>
            <article class="placeholder">
                <h2>Homepage Settings</h2>
                <p>Manage the homepage hero and dynamic About Us content.</p>
                <div class="settings-links"><a href="hero-settings.php">Hero Media</a><a href="about-settings.php">About Us Settings</a></div>
            </article>
            <article class="placeholder">
                <h2>Team Management</h2>
                <p>Manage team groups, members, public Team Page content, photos, and ordering.</p>
                <div class="settings-links"><a href="team-management.php">Manage Team</a><a href="team-page-settings.php">Team Page Settings</a></div>
            </article>
            <article class="placeholder">
                <h2>Portfolio Management</h2>
                <p>Manage portfolio categories, projects, gallery images, videos, SEO, and display order.</p>
                <div class="settings-links"><a href="portfolio/dashboard.php">Dashboard</a><a href="portfolio/categories.php">Categories</a><a href="portfolio/projects.php">Projects</a><a href="portfolio/gallery.php">Gallery</a><a href="portfolio/videos.php">Videos</a></div>
            </article>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>

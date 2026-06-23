<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/site-settings.php';
$headerSettings = getHeaderSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Design24 Studio offers luxury interior design, architectural planning, visualization, furniture, and turnkey execution in Dhaka.">
    <title><?= siteEscape($headerSettings['website_name']) ?> | <?= siteEscape($headerSettings['website_tagline']) ?></title>

    <!-- Small inline favicon: no separate image or dependency needed. -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='8' fill='%23152d26'/%3E%3Cpath d='M14 14h21c11 0 18 7 18 18s-7 18-18 18H14V14zm10 10v16h10c6 0 9-3 9-8s-3-8-9-8H24z' fill='%23c9a86a'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= settingEnabled($headerSettings, 'show_top_bar') ? 'has-top-bar' : 'no-top-bar' ?>">
    <?php require __DIR__ . '/includes/site-header.php'; ?>

    <main>
        <?php require __DIR__ . '/includes/site-hero.php'; ?>
        <?php require __DIR__ . '/includes/site-about.php'; ?>
        <?php require __DIR__ . '/includes/site-testimonials.php'; ?>
        <?php require __DIR__ . '/includes/site-portfolio-featured.php'; ?>
    </main>

    <?php require __DIR__ . '/includes/site-footer.php'; ?>

    <script src="assets/js/script.js"></script>
</body>
</html>

<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/site-office-data.php';

$headerSettings = getHeaderSettings();
$officeSettings = getOfficePageSettings();
$officeMedia = getOfficeMedia(true);
$heroImage = officeImageIsSafe((string) $officeSettings['hero_image']) ? (string) $officeSettings['hero_image'] : '';
$heroVideo = officeVideoIsSafe((string) $officeSettings['hero_video']) ? (string) $officeSettings['hero_video'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= siteEscape($officeSettings['subtitle']) ?>">
    <title><?= siteEscape($officeSettings['title']) ?> | <?= siteEscape($headerSettings['website_name']) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css?v=20260623-office-page">
</head>
<body class="<?= settingEnabled($headerSettings, 'show_top_bar') ? 'has-top-bar' : 'no-top-bar' ?>">
<?php require __DIR__ . '/includes/site-header.php'; ?>
<main class="office-page" id="office">
    <section class="office-hero<?= $heroImage !== '' ? ' has-image' : '' ?><?= $heroVideo !== '' ? ' has-video' : '' ?>"<?= $heroImage !== '' ? ' style="--office-hero-image:url(\'' . siteEscape(siteAssetUrl($heroImage)) . '\')"' : '' ?>>
        <?php if ($heroVideo !== ''): ?><video class="office-hero-video" autoplay muted loop playsinline preload="metadata"<?= $heroImage !== '' ? ' poster="' . siteEscape(siteAssetUrl($heroImage)) . '"' : '' ?>><source src="<?= siteEscape(siteAssetUrl($heroVideo)) ?>" type="<?= strpos($heroVideo, '.webm') !== false ? 'video/webm' : 'video/mp4' ?>"></video><div class="office-hero-overlay" aria-hidden="true"></div><?php endif; ?>
        <div class="office-container">
            <p><?= siteEscape($officeSettings['eyebrow']) ?></p>
            <h1><?= siteEscape($officeSettings['title']) ?></h1>
            <span><?= siteEscape($officeSettings['subtitle']) ?></span>
        </div>
    </section>
    <section class="office-intro">
        <div class="office-container office-intro-grid">
            <div><p class="office-eyebrow"><?= siteEscape($officeSettings['eyebrow']) ?></p><h2>Where planning meets production</h2></div>
            <div><p><?= nl2br(siteEscape($officeSettings['description'])) ?></p><div class="office-actions"><a class="portfolio-view-button" href="/consultation-booking.php">Book a Consultation</a><a class="portfolio-outline-button" href="#contact-actions">Contact Us</a></div></div>
        </div>
    </section>
    <?php if (settingEnabled($officeSettings, 'show_gallery') && $officeMedia !== []): ?>
    <section class="office-gallery">
        <div class="office-container">
            <header class="office-section-header"><p>OUR SPACE</p><h2>Office & Factory Gallery</h2></header>
            <div class="office-gallery-grid">
                <?php foreach ($officeMedia as $item): ?>
                    <article class="office-gallery-card">
                        <div class="office-gallery-media">
                            <?php if ($item['media_type'] === 'video' && officeVideoIsSafe((string) $item['file_path'])): ?>
                                <video src="<?= siteEscape(siteAssetUrl((string) $item['file_path'])) ?>" controls preload="metadata"></video>
                            <?php elseif (officeImageIsSafe((string) $item['file_path'])): ?>
                                <img src="<?= siteEscape(siteAssetUrl((string) $item['file_path'])) ?>" alt="<?= siteEscape($item['title'] ?: 'Office and factory') ?>" loading="lazy">
                            <?php endif; ?>
                        </div>
                        <?php if ((string) $item['title'] !== '' || (string) $item['description'] !== ''): ?><div class="office-gallery-copy"><?php if ((string) $item['title'] !== ''): ?><h3><?= siteEscape($item['title']) ?></h3><?php endif; ?><?php if ((string) $item['description'] !== ''): ?><p><?= nl2br(siteEscape($item['description'])) ?></p><?php endif; ?></div><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/site-footer.php'; ?>
<script src="/assets/js/script.js"></script>
</body>
</html>

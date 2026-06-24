<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/site-video-data.php';

$headerSettings = getHeaderSettings();
$videos = getVideoGalleryItems(true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Watch Design24 Studio interior design, architecture, furniture, and project videos.">
    <title>Videos | <?= siteEscape($headerSettings['website_name']) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css?v=20260623-video-gallery">
</head>
<body class="<?= settingEnabled($headerSettings, 'show_top_bar') ? 'has-top-bar' : 'no-top-bar' ?>">
<?php require __DIR__ . '/includes/site-header.php'; ?>
<main class="video-gallery-page" id="videos">
    <section class="video-gallery-hero">
        <div class="video-gallery-container">
            <p>DESIGN24 STUDIO</p>
            <h1>Video Gallery</h1>
            <span>Explore our project walkthroughs, design stories, and studio updates.</span>
        </div>
    </section>
    <section class="video-gallery-section">
        <div class="video-gallery-container">
            <?php if ($videos === []): ?>
                <div class="video-gallery-empty">No videos have been published yet.</div>
            <?php else: ?>
                <div class="video-gallery-grid">
                    <?php foreach ($videos as $video): ?>
                        <?php $embedUrl = (string) $video['video_type'] === 'url' ? videoGalleryEmbedUrl((string) $video['video_url']) : ''; ?>
                        <article class="video-gallery-card">
                            <div class="video-gallery-frame">
                                <?php if ($embedUrl !== ''): ?>
                                    <iframe src="<?= siteEscape($embedUrl) ?>" title="<?= siteEscape($video['title']) ?>" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                                <?php elseif (videoGalleryVideoIsSafe((string) $video['video_file'])): ?>
                                    <video src="<?= siteEscape(siteAssetUrl((string) $video['video_file'])) ?>" controls preload="metadata"<?= videoGalleryImageIsSafe((string) $video['thumbnail']) ? ' poster="' . siteEscape(siteAssetUrl((string) $video['thumbnail'])) . '"' : '' ?>></video>
                                <?php else: ?>
                                    <div class="video-gallery-missing">Video unavailable</div>
                                <?php endif; ?>
                            </div>
                            <div class="video-gallery-copy">
                                <h2><?= siteEscape($video['title']) ?></h2>
                                <?php if ((string) $video['description'] !== ''): ?><p><?= nl2br(siteEscape((string) $video['description'])) ?></p><?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/site-footer.php'; ?>
<script src="/assets/js/script.js"></script>
</body>
</html>

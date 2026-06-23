<?php

declare(strict_types=1);

$heroMedia = [];
try {
    $heroStatement = db()->prepare('SELECT id, media_type, file_path FROM hero_media WHERE status = 1 ORDER BY display_order ASC, id ASC');
    $heroStatement->execute();
    foreach ($heroStatement->fetchAll() as $item) {
        $type = (string) $item['media_type'];
        $path = (string) $item['file_path'];
        $validPath = $type === 'image'
            ? preg_match('#^uploads/site/hero/images/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#', $path)
            : preg_match('#^uploads/site/hero/videos/[a-f0-9]{32}\.(?:mp4|webm)$#', $path);
        if (in_array($type, ['image', 'video'], true) && $validPath === 1 && is_file(dirname(__DIR__) . '/' . $path)) {
            $aspectRatio = '16 / 9';
            if ($type === 'image') {
                $imageSize = @getimagesize(dirname(__DIR__) . '/' . $path);
                if (is_array($imageSize) && $imageSize[0] > 0 && $imageSize[1] > 0) {
                    $aspectRatio = (int) $imageSize[0] . ' / ' . (int) $imageSize[1];
                }
            }
            $heroMedia[] = ['id' => (int) $item['id'], 'media_type' => $type, 'file_path' => $path, 'aspect_ratio' => $aspectRatio];
        }
    }
} catch (Throwable $exception) {
    error_log($exception->getMessage());
}

$heroMediaCount = count($heroMedia);
?>
<section class="hero-slider<?= $heroMediaCount === 0 ? ' hero-slider-empty' : '' ?>" data-hero-slider data-slide-count="<?= $heroMediaCount ?>" style="--hero-aspect-ratio:<?= siteEscape($heroMedia[0]['aspect_ratio'] ?? '16 / 9') ?>" aria-label="Homepage media slider">
    <?php foreach ($heroMedia as $index => $item): ?>
        <div class="hero-slide<?= $index === 0 ? ' active' : '' ?>" data-media-type="<?= siteEscape($item['media_type']) ?>" data-aspect-ratio="<?= siteEscape($item['aspect_ratio']) ?>" aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>">
            <?php if ($item['media_type'] === 'image'): ?>
                <img src="<?= siteEscape($item['file_path']) ?>" alt=""<?= $index === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?>>
            <?php else: ?>
                <video src="<?= siteEscape($item['file_path']) ?>" autoplay muted playsinline preload="metadata"></video>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if ($heroMediaCount > 1): ?>
        <button class="hero-slider-arrow hero-slider-prev" type="button" aria-label="Previous hero slide"><span aria-hidden="true">‹</span></button>
        <button class="hero-slider-arrow hero-slider-next" type="button" aria-label="Next hero slide"><span aria-hidden="true">›</span></button>
        <div class="hero-slider-dots" role="group" aria-label="Choose hero slide">
            <?php foreach ($heroMedia as $index => $item): ?><button type="button" class="hero-slider-dot<?= $index === 0 ? ' active' : '' ?>" aria-label="Show hero slide <?= $index + 1 ?>" aria-current="<?= $index === 0 ? 'true' : 'false' ?>" data-slide-index="<?= $index ?>"></button><?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

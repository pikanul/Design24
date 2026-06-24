<?php

declare(strict_types=1);

require_once __DIR__ . '/site-portfolio-data.php';

$homePortfolioProjects = getPortfolioProjects();
if ($homePortfolioProjects === []) {
    return;
}

$homePortfolioGroups = [];
foreach ($homePortfolioProjects as $item) {
    $groupKey = (string) ($item['category_slug'] ?: 'portfolio');
    if (!isset($homePortfolioGroups[$groupKey])) {
        $homePortfolioGroups[$groupKey] = [
            'name' => (string) ($item['category_name'] ?: 'Portfolio'),
            'slug' => $groupKey,
            'parent_slug' => (string) ($item['parent_category_slug'] ?? ''),
            'projects' => [],
        ];
    }
    $homePortfolioGroups[$groupKey]['projects'][] = $item;
}
?>
<section class="portfolio-browser portfolio-home-featured">
    <div class="portfolio-container">
        <div class="portfolio-category-groups" data-portfolio-grid data-initial-filter="" data-initial-status="" data-initial-type="">
            <?php foreach ($homePortfolioGroups as $group): ?>
            <section class="portfolio-category-group" data-portfolio-category="<?= siteEscape(trim($group['parent_slug'] . ' ' . $group['slug'])) ?>">
                <header class="portfolio-category-heading">
                    <div><p>Portfolio Category</p><h2><?= siteEscape($group['name']) ?></h2></div>
                    <?php if (count($group['projects']) > 1): ?><div class="portfolio-category-controls"><button type="button" data-category-previous aria-label="Previous <?= siteEscape($group['name']) ?> project">←</button><button type="button" data-category-next aria-label="Next <?= siteEscape($group['name']) ?> project">→</button></div><?php endif; ?>
                </header>
                <div class="portfolio-category-viewport">
                    <div class="portfolio-category-track" data-category-track>
                        <?php foreach ($group['projects'] as $item): ?>
                        <?php
                        $isVideoCard = ($item['project_type'] ?? '') === 'video' || $item['youtube_url'] !== '';
                        $projectUrl = $item['slug'] !== '' ? '/portfolio/project/' . $item['slug'] : '#';
                        $youtubeId = $isVideoCard ? youtubeVideoIdFromUrl((string) $item['youtube_url']) : '';
                        $fallbackImage = (string) $item['featured_image'];
                        $cardImage = $youtubeId !== '' ? 'https://img.youtube.com/vi/' . rawurlencode($youtubeId) . '/hqdefault.jpg' : siteAssetUrl($fallbackImage);
                        ?>
                        <article class="portfolio-card" data-category="<?= siteEscape(trim(($item['parent_category_slug'] ?: '') . ' ' . $item['category_slug'])) ?>" data-status="<?= siteEscape($item['project_status']) ?>" data-type="<?= $isVideoCard ? 'video' : 'image' ?>" data-order="<?= (int) ($item['display_order'] ?? $item['id'] ?? 0) ?>" data-search="<?= siteEscape(strtolower($item['title'] . ' ' . $item['category_name'] . ' ' . $item['project_location'] . ' ' . $item['client_name'] . ' ' . $item['short_description'])) ?>">
                            <?php if ($isVideoCard): ?><button class="portfolio-card-image portfolio-video-trigger" type="button" data-video-url="<?= siteEscape($item['youtube_url']) ?>" data-video-title="<?= siteEscape($item['title']) ?>"><?php else: ?><a class="portfolio-card-image" href="<?= siteEscape($projectUrl) ?>"><?php endif; ?><?php if ($youtubeId !== '' || portfolioAssetIsSafe($fallbackImage) || str_starts_with($fallbackImage, 'assets/')): ?><img src="<?= siteEscape($cardImage) ?>" alt="<?= siteEscape($item['title']) ?>" loading="lazy"<?php if ($youtubeId !== '' && $fallbackImage !== ''): ?> onerror="this.onerror=null;this.src='<?= siteEscape(siteAssetUrl($fallbackImage)) ?>';"<?php endif; ?>><?php endif; ?><span class="portfolio-card-badge"><?= siteEscape($isVideoCard ? 'Video' : $item['project_status']) ?></span><?php if ($isVideoCard): ?><i class="portfolio-video-play-icon" aria-hidden="true">▶</i><span class="portfolio-duration"><?= siteEscape($item['video_duration'] ?? '02:15') ?></span><?php endif; ?><?php if ($isVideoCard): ?></button><?php else: ?></a><?php endif; ?>
                            <div class="portfolio-card-body"><p class="portfolio-card-category"><?= siteEscape($item['category_name']) ?></p><h2><?= siteEscape($item['title']) ?></h2><p class="portfolio-location">⌖ <?= siteEscape($item['project_location'] ?: 'Design24 Studio') ?></p><p><?= siteEscape($item['short_description']) ?></p><?php if ($isVideoCard): ?><button class="portfolio-text-link portfolio-video-trigger" type="button" data-video-url="<?= siteEscape($item['youtube_url']) ?>" data-video-title="<?= siteEscape($item['title']) ?>">Watch Video →</button><?php else: ?><a class="portfolio-text-link" href="<?= siteEscape($projectUrl) ?>">View Project →</a><?php endif; ?></div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<div class="portfolio-video-modal" data-portfolio-video-modal hidden><div class="portfolio-video-modal-dialog" role="dialog" aria-modal="true" aria-label="Project video"><button class="portfolio-video-modal-close" type="button" data-close-portfolio-video aria-label="Close video">×</button><iframe title="Project video" allow="autoplay; fullscreen" allowfullscreen></iframe></div></div>

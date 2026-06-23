<?php

declare(strict_types=1);

require_once __DIR__ . '/site-portfolio-data.php';

$portfolioFeaturedSettings = getPortfolioSettings();
if (!settingEnabled($portfolioFeaturedSettings, 'portfolio_show_featured_home')) {
    return;
}

$featuredProjects = getPortfolioProjects([
    'featured' => true,
    'limit' => (int) $portfolioFeaturedSettings['portfolio_featured_limit'],
]);

if ($featuredProjects === []) {
    return;
}
?>
<section class="portfolio-browser portfolio-home-featured" id="portfolio">
    <div class="portfolio-container">
        <header class="testimonial-section-header">
            <p>FEATURED PORTFOLIO</p>
            <h2>Signature Projects</h2>
        </header>
        <div class="portfolio-grid">
            <?php foreach ($featuredProjects as $item): ?>
                <article class="portfolio-card">
                    <a class="portfolio-card-image" href="/portfolio/project/<?= siteEscape($item['slug']) ?>">
                        <?php if (portfolioAssetIsSafe((string) $item['featured_image'])): ?><img src="<?= siteEscape(siteAssetUrl($item['featured_image'])) ?>" alt="<?= siteEscape($item['title']) ?>" loading="lazy"><?php endif; ?>
                        <span><?= siteEscape($item['project_status']) ?></span>
                    </a>
                    <div class="portfolio-card-body">
                        <p class="portfolio-card-category"><?= siteEscape($item['category_name']) ?></p>
                        <h2><?= siteEscape($item['title']) ?></h2>
                        <p><?= siteEscape($item['short_description']) ?></p>
                        <a class="portfolio-view-button" href="/portfolio/project/<?= siteEscape($item['slug']) ?>">View Project</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

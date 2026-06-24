<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/site-portfolio-data.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? trim(rawurldecode($requestPath), '/') : '';
$projectSlug = isset($_GET['project']) ? trim((string) $_GET['project']) : '';
$categorySlug = isset($_GET['category']) ? trim((string) $_GET['category']) : '';

if (strpos($requestPath, 'portfolio/project/') === 0) {
    $projectSlug = trim(substr($requestPath, strlen('portfolio/project/')), '/');
} elseif (strpos($requestPath, 'portfolio/') === 0 && $requestPath !== 'portfolio') {
    $categorySlug = trim(substr($requestPath, strlen('portfolio/')), '/');
}

$settings = getPortfolioSettings();
$routeStatus = '';
$routeType = '';
if ($categorySlug === 'ongoing-projects') $routeStatus = 'Ongoing';
if ($categorySlug === 'completed-projects') $routeStatus = 'Completed';
if ($categorySlug === 'project-videos') $routeType = 'video';
$project = $projectSlug !== '' ? getPortfolioProjectBySlug($projectSlug) : null;
$isDetail = $project !== null;
$categories = getPortfolioMenuTree();
$allCategories = getPortfolioCategories(true);
$activeCategory = null;
if (!$isDetail && $categorySlug !== '') {
    foreach ($allCategories as $category) {
        if ($category['slug'] === $categorySlug) {
            $activeCategory = $category;
            break;
        }
    }
}

$pageTitle = $isDetail
    ? (($project['seo_title'] ?: $project['title']) . ' | Design24 Studio')
    : (($activeCategory['name'] ?? $settings['portfolio_title'] ?: 'Portfolio') . ' | Design24 Studio');
$pageDescription = $isDetail
    ? ($project['seo_description'] ?: $project['short_description'] ?: 'Explore Design24 Studio project details.')
    : (($activeCategory['description'] ?? '') !== '' ? $activeCategory['description'] : ($settings['portfolio_subtitle'] ?: 'Discover Design24 Studio portfolio projects.'));
$ogImage = $isDetail && portfolioAssetIsSafe((string) ($project['og_image'] ?: $project['featured_image'])) ? (string) ($project['og_image'] ?: $project['featured_image']) : '';
$headerSettings = getHeaderSettings();

$projects = [];
$gallery = [];
$videos = [];
$related = [];
if ($isDetail) {
    $gallery = getPortfolioGallery((int) $project['id']);
    $videos = getPortfolioVideos((int) $project['id']);
    if ($project['youtube_url'] !== '') {
        array_unshift($videos, ['youtube_url' => $project['youtube_url'], 'video_title' => $project['title'], 'display_order' => 0]);
    }
    $related = array_filter(getPortfolioProjects(['category_slug' => $project['category_slug'], 'limit' => 4]), static fn(array $item): bool => (int) $item['id'] !== (int) $project['id']);
} else {
    $filters = [];
    if ($categorySlug !== '' && $routeStatus === '' && $routeType === '') $filters['category_slug'] = $categorySlug;
    if ($routeStatus !== '') $filters['project_status'] = $routeStatus;
    if (isset($_GET['status']) && in_array($_GET['status'], ['Ongoing', 'Completed'], true)) $filters['project_status'] = (string) $_GET['status'];
    if (isset($_GET['search'])) $filters['search'] = trim((string) $_GET['search']);
    $projects = getPortfolioProjects($filters);
    if ($projects === []) {
        $sampleImage = 'assets/images/hero/hero-living-room.png';
        $projects = [
            ['id'=>0,'title'=>'Contemporary Residence','slug'=>'','category_name'=>'Office & Commercial Portfolio','category_slug'=>'office-commercial-portfolio','parent_category_slug'=>'','project_status'=>'Completed','project_location'=>'Gulshan, Dhaka','client_name'=>'Private Client','short_description'=>'A calm, contemporary interior designed for effortless everyday living.','featured_image'=>$sampleImage,'youtube_url'=>'','completion_year'=>'2025','project_type'=>'image'],
            ['id'=>0,'title'=>'Modern Workspace','slug'=>'','category_name'=>'Office & Commercial Portfolio','category_slug'=>'office-commercial-portfolio','parent_category_slug'=>'','project_status'=>'Completed','project_location'=>'Banani, Dhaka','client_name'=>'Studio One','short_description'=>'A high-performing office that balances focus, comfort, and brand character.','featured_image'=>$sampleImage,'youtube_url'=>'','completion_year'=>'2025','project_type'=>'image'],
            ['id'=>0,'title'=>'Residence Walkthrough','slug'=>'','category_name'=>'Project Videos','category_slug'=>'project-videos','parent_category_slug'=>'','project_status'=>'Completed','project_location'=>'Uttara, Dhaka','client_name'=>'Private Client','short_description'=>'Take a short walk through this warm, tailored family home.','featured_image'=>$sampleImage,'youtube_url'=>'https://www.youtube.com/watch?v=dQw4w9WgXcQ','completion_year'=>'2025','project_type'=>'video','video_duration'=>'02:15'],
            ['id'=>0,'title'=>'Signature Dining Space','slug'=>'','category_name'=>'Residential Portfolio','category_slug'=>'residential-portfolio','parent_category_slug'=>'','project_status'=>'Ongoing','project_location'=>'Bashundhara, Dhaka','client_name'=>'Private Client','short_description'=>'A layered dining experience with crafted details and warm materials.','featured_image'=>$sampleImage,'youtube_url'=>'','completion_year'=>'2026','project_type'=>'image'],
            ['id'=>0,'title'=>'Corporate Reception','slug'=>'','category_name'=>'Office & Commercial Portfolio','category_slug'=>'office-commercial-portfolio','parent_category_slug'=>'','project_status'=>'Completed','project_location'=>'Motijheel, Dhaka','client_name'=>'Apex Group','short_description'=>'An inviting arrival space that makes a polished first impression.','featured_image'=>$sampleImage,'youtube_url'=>'','completion_year'=>'2025','project_type'=>'image'],
            ['id'=>0,'title'=>'Project Film: The Loft','slug'=>'','category_name'=>'Project Videos','category_slug'=>'project-videos','parent_category_slug'=>'','project_status'=>'Completed','project_location'=>'Dhanmondi, Dhaka','client_name'=>'Private Client','short_description'=>'Behind the scenes of a characterful urban loft transformation.','featured_image'=>$sampleImage,'youtube_url'=>'https://www.youtube.com/watch?v=dQw4w9WgXcQ','completion_year'=>'2025','project_type'=>'video','video_duration'=>'03:42'],
            ['id'=>0,'title'=>'Boutique Retail Interior','slug'=>'','category_name'=>'Office & Commercial Portfolio','category_slug'=>'office-commercial-portfolio','parent_category_slug'=>'','project_status'=>'Ongoing','project_location'=>'Gulshan, Dhaka','client_name'=>'Atelier','short_description'=>'A tactile retail environment designed to make every product feel considered.','featured_image'=>$sampleImage,'youtube_url'=>'','completion_year'=>'2026','project_type'=>'image'],
            ['id'=>0,'title'=>'Garden-Facing Bedroom','slug'=>'','category_name'=>'Residential Portfolio','category_slug'=>'residential-portfolio','parent_category_slug'=>'','project_status'=>'Completed','project_location'=>'Baridhara, Dhaka','client_name'=>'Private Client','short_description'=>'Soft natural materials and a restrained palette create an effortless retreat.','featured_image'=>$sampleImage,'youtube_url'=>'','completion_year'=>'2025','project_type'=>'image'],
        ];
        if ($routeStatus !== '') $projects = array_values(array_filter($projects, static fn(array $item): bool => $item['project_status'] === $routeStatus));
        if ($routeType === 'video') $projects = array_values(array_filter($projects, static fn(array $item): bool => ($item['project_type'] ?? '') === 'video' || $item['youtube_url'] !== ''));
    }
}

$bannerImage = portfolioAssetIsSafe((string) $settings['portfolio_banner_image']) ? (string) $settings['portfolio_banner_image'] : '';
$categoryHeroImage = !$isDetail && $activeCategory !== null && portfolioAssetIsSafe((string) ($activeCategory['hero_image'] ?? '')) ? (string) $activeCategory['hero_image'] : '';
$categoryHeroVideo = !$isDetail && $activeCategory !== null && portfolioVideoAssetIsSafe((string) ($activeCategory['hero_video'] ?? '')) ? (string) $activeCategory['hero_video'] : '';
$heroImage = $isDetail && portfolioAssetIsSafe((string) $project['featured_image']) ? (string) $project['featured_image'] : ($categoryHeroImage ?: $bannerImage);
$projectGroups = [];
if (!$isDetail) {
    foreach ($projects as $item) {
        $groupKey = (string) ($item['category_slug'] ?: 'portfolio');
        if (!isset($projectGroups[$groupKey])) {
            $projectGroups[$groupKey] = [
                'name' => (string) ($item['category_name'] ?: 'Portfolio'),
                'slug' => $groupKey,
                'parent_slug' => (string) ($item['parent_category_slug'] ?? ''),
                'projects' => [],
            ];
        }
        $projectGroups[$groupKey]['projects'][] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= siteEscape($pageDescription) ?>">
    <?php if ($isDetail && $project['meta_keywords'] !== ''): ?><meta name="keywords" content="<?= siteEscape($project['meta_keywords']) ?>"><?php endif; ?>
    <meta property="og:title" content="<?= siteEscape($pageTitle) ?>">
    <meta property="og:description" content="<?= siteEscape($pageDescription) ?>">
    <?php if ($ogImage !== ''): ?><meta property="og:image" content="<?= siteEscape(siteAssetUrl($ogImage)) ?>"><?php endif; ?>
    <title><?= siteEscape($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css?v=20260623-two-category-columns">
    <?php if ($isDetail): ?><style>
        .portfolio-page .portfolio-hero{display:grid;min-height:360px;align-items:center;overflow:hidden;background:linear-gradient(rgba(0,48,35,.46),rgba(0,48,35,.46)),var(--portfolio-hero-image) center/cover no-repeat;color:#fff}
        .portfolio-page .portfolio-hero .portfolio-container{position:relative;z-index:1}
        .portfolio-page .portfolio-hero h1{margin:0;color:#fff;font-family:Georgia,'Times New Roman',serif;font-size:clamp(3rem,8vw,6.2rem);line-height:.95}
        .portfolio-page .portfolio-hero p{max-width:680px;margin:20px 0 0;color:#fff}
        .site-header .whatsapp-symbol{display:inline-block!important;width:18px!important;height:18px!important;max-width:18px!important;max-height:18px!important;flex:0 0 18px!important}
        a[href*="wa.me"] svg{display:inline-block!important;width:18px!important;height:18px!important;max-width:18px!important;max-height:18px!important;flex:0 0 18px!important}
    </style><?php endif; ?>
    <?php if ($isDetail): ?><script type="application/ld+json"><?= json_encode(['@context'=>'https://schema.org','@type'=>'CreativeWork','name'=>$project['title'],'description'=>$pageDescription,'url'=>'/portfolio/project/'.$project['slug'],'image'=>$ogImage !== '' ? siteAssetUrl($ogImage) : null], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script><?php endif; ?>
</head>
<body class="<?= settingEnabled($headerSettings, 'show_top_bar') ? 'has-top-bar' : 'no-top-bar' ?>">
<?php require __DIR__ . '/includes/site-header.php'; ?>
<main class="portfolio-page">
    <?php if ($isDetail): ?>
    <section class="portfolio-hero<?= $heroImage !== '' ? ' has-image' : '' ?><?= $categoryHeroVideo !== '' ? ' has-video' : '' ?>"<?= $heroImage !== '' ? ' style="--portfolio-hero-image:url(\'' . siteEscape(siteAssetUrl($heroImage)) . '\');--portfolio-overlay:' . ((int) $settings['portfolio_overlay_opacity'] / 100) . '"' : '' ?>>
        <?php if ($categoryHeroVideo !== ''): ?><video class="portfolio-hero-video" autoplay muted loop playsinline preload="metadata"<?= $categoryHeroImage !== '' ? ' poster="' . siteEscape(siteAssetUrl($categoryHeroImage)) . '"' : '' ?>><source src="<?= siteEscape(siteAssetUrl($categoryHeroVideo)) ?>" type="<?= str_ends_with($categoryHeroVideo, '.webm') ? 'video/webm' : 'video/mp4' ?>"></video><div class="portfolio-hero-video-overlay" aria-hidden="true"></div><?php endif; ?>
        <div class="portfolio-container">
            <p class="portfolio-breadcrumb"><a href="/index.php#home">Home</a> / <a href="/portfolio.php">Portfolio</a> / <?= siteEscape($project['title']) ?></p>
            <h1><?= siteEscape($project['title']) ?></h1>
            <p><?= siteEscape($project['short_description'] ?: $project['category_name']) ?></p>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!$isDetail): ?>
    <section class="portfolio-browser" id="portfolio">
        <div class="portfolio-container">
            <div class="portfolio-filter-bar" data-portfolio-filter>
                <button class="<?= $routeStatus==='' && $routeType==='' && $categorySlug==='' ? 'active' : '' ?>" type="button" data-filter="all">All</button>
                <?php foreach ($categories as $category): ?><?php if (in_array($category['slug'], ['office-commercial-portfolio'], true)): ?><button type="button" data-filter="<?= siteEscape($category['slug']) ?>"><?= siteEscape($category['name']) ?></button><?php endif; ?><?php endforeach; ?>
            </div>
            <div class="portfolio-toolbar"><label class="portfolio-search"><span class="sr-only">Search Portfolio</span><input type="search" data-portfolio-search placeholder="Search title, category, location, client..."></label><label class="portfolio-sort"><span>Sort by</span><select data-portfolio-sort><option value="latest">Latest Projects</option><option value="oldest">Oldest Projects</option><option value="completed">Completed Projects</option><option value="ongoing">Ongoing Projects</option></select></label></div>
            <div class="portfolio-category-groups" data-portfolio-grid data-initial-filter="<?= siteEscape($routeStatus==='' && $routeType==='' ? $categorySlug : '') ?>" data-initial-status="<?= siteEscape($routeStatus) ?>" data-initial-type="<?= siteEscape($routeType) ?>">
                <?php foreach ($projectGroups as $group): ?>
                <section class="portfolio-category-group" data-portfolio-category="<?= siteEscape(trim($group['parent_slug'] . ' ' . $group['slug'])) ?>">
                    <header class="portfolio-category-heading">
                        <div><p>Portfolio Category</p><h2><?= siteEscape($group['name']) ?></h2></div>
                        <?php if (count($group['projects']) > 1): ?><div class="portfolio-category-controls"><button type="button" data-category-previous aria-label="Previous <?= siteEscape($group['name']) ?> project">←</button><button type="button" data-category-next aria-label="Next <?= siteEscape($group['name']) ?> project">→</button></div><?php endif; ?>
                    </header>
                    <div class="portfolio-category-viewport">
                        <div class="portfolio-category-track" data-category-track>
                            <?php foreach ($group['projects'] as $item): ?>
                            <?php $isVideoCard = ($item['project_type'] ?? '') === 'video' || $item['youtube_url'] !== ''; $projectUrl = $item['slug'] !== '' ? '/portfolio/project/' . $item['slug'] : '#'; $youtubeId = $isVideoCard ? youtubeVideoIdFromUrl((string) $item['youtube_url']) : ''; $fallbackImage = (string) $item['featured_image']; $cardImage = $youtubeId !== '' ? 'https://img.youtube.com/vi/' . rawurlencode($youtubeId) . '/hqdefault.jpg' : siteAssetUrl($fallbackImage); ?>
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
            <p class="portfolio-empty" data-portfolio-empty hidden>No portfolio projects match your search.</p>
        </div>
    </section>
    <section class="portfolio-consultation"><div class="portfolio-container portfolio-consultation-grid"><div><p class="portfolio-eyebrow">LET’S BUILD SOMETHING AMAZING</p><h2>Have a project in mind?</h2><p>Let’s bring your ideas to life with our creativity, experience, and dedication.</p><div class="portfolio-consultation-actions"><a class="portfolio-view-button" href="/consultation-booking.php">Book a Consultation</a><a class="portfolio-outline-button" href="<?= siteEscape(whatsappUrl($headerSettings['whatsapp'])) ?>" target="_blank" rel="noopener noreferrer">WhatsApp Us</a></div></div><img src="/assets/images/about/about-fallback.png" alt="Design24 Studio consultation"></div></section>
    <?php else: ?>
    <section class="portfolio-detail">
        <div class="portfolio-container">
            <div class="portfolio-detail-grid">
                <article class="portfolio-detail-copy"><p class="portfolio-card-category"><?= siteEscape($project['category_name']) ?></p><h2>Project Overview</h2><p><?= nl2br(siteEscape($project['full_description'] ?: $project['short_description'])) ?></p></article>
                <aside class="portfolio-info-card"><h2>Project Information</h2><dl><dt>Status</dt><dd><?= siteEscape($project['project_status']) ?></dd><dt>Location</dt><dd><?= siteEscape($project['project_location'] ?: 'Not specified') ?></dd><dt>Client</dt><dd><?= siteEscape($project['client_name'] ?: 'Private Client') ?></dd><dt>Year</dt><dd><?= siteEscape($project['completion_year'] ?: '—') ?></dd><dt>Area</dt><dd><?= siteEscape($project['project_area'] ?: '—') ?></dd></dl><a class="portfolio-view-button" href="#contact-actions">Contact Us</a><a class="portfolio-outline-button" href="<?= siteEscape(whatsappUrl($headerSettings['whatsapp'])) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a></aside>
            </div>
            <?php if ($gallery): ?><section class="portfolio-gallery-section"><h2>Image Gallery</h2><div class="portfolio-masonry" data-lightbox-gallery><?php foreach ($gallery as $index => $image): ?><button type="button" data-full="<?= siteEscape(siteAssetUrl($image['image_path'])) ?>" data-caption="<?= siteEscape($image['caption']) ?>"><img src="<?= siteEscape(siteAssetUrl($image['image_path'])) ?>" alt="<?= siteEscape($image['caption'] ?: $project['title']) ?>" loading="lazy"><span><?= siteEscape($image['caption']) ?></span></button><?php endforeach; ?></div></section><?php endif; ?>
            <?php if ($videos): ?><section class="portfolio-video-section"><h2>Project Videos</h2><div class="portfolio-video-grid"><?php foreach ($videos as $video): ?><?php $embed=youtubeEmbedUrl($video['youtube_url']); if($embed==='') continue; ?><article><iframe src="<?= siteEscape($embed) ?>" title="<?= siteEscape($video['video_title'] ?: $project['title']) ?>" loading="lazy" allowfullscreen></iframe><h3><?= siteEscape($video['video_title'] ?: 'Project Video') ?></h3></article><?php endforeach; ?></div></section><?php endif; ?>
            <?php if ($related): ?><section class="portfolio-related"><h2>Related Projects</h2><div class="portfolio-grid"><?php foreach($related as$item):?><article class="portfolio-card"><a class="portfolio-card-image" href="/portfolio/project/<?=siteEscape($item['slug'])?>"><?php if(portfolioAssetIsSafe((string)$item['featured_image'])):?><img src="<?=siteEscape(siteAssetUrl($item['featured_image']))?>" alt="<?=siteEscape($item['title'])?>" loading="lazy"><?php endif;?><span><?=siteEscape($item['project_status'])?></span></a><div class="portfolio-card-body"><p class="portfolio-card-category"><?=siteEscape($item['category_name'])?></p><h2><?=siteEscape($item['title'])?></h2><a class="portfolio-view-button" href="/portfolio/project/<?=siteEscape($item['slug'])?>">View Project</a></div></article><?php endforeach;?></div></section><?php endif; ?>
            <div class="portfolio-cta"><h2>Planning a similar space?</h2><p>Talk to Design24 Studio for elegant interior design, furniture, and turnkey execution.</p><a class="portfolio-view-button" href="/consultation-booking.php">Book a Consultation</a></div>
        </div>
    </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/site-footer.php'; ?>
<div class="portfolio-video-modal" data-portfolio-video-modal hidden><div class="portfolio-video-modal-dialog" role="dialog" aria-modal="true" aria-label="Project video"><button class="portfolio-video-modal-close" type="button" data-close-portfolio-video aria-label="Close video">×</button><iframe title="Project video" allow="autoplay; fullscreen" allowfullscreen></iframe></div></div>
<div class="portfolio-lightbox" data-portfolio-lightbox hidden><button type="button" aria-label="Close gallery">×</button><img src="" alt=""><p></p></div>
<script src="/assets/js/script.js?v=20260623-category-carousel"></script>
</body>
</html>

<?php

declare(strict_types=1);

function aboutPublicImageIsSafe(string $path): bool
{
    return preg_match('#^public/uploads/about/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#', $path) === 1
        && is_file(dirname(__DIR__) . '/' . $path);
}

function aboutPublicLink(string $link): string
{
    $link = trim($link);
    if ($link === '') return '';
    if ($link[0] === '#' && preg_match('/^#[a-zA-Z][a-zA-Z0-9_-]*$/', $link) === 1) return $link;
    if ($link[0] === '/' && preg_match('#^/[a-zA-Z0-9_./?#=&%-]*$#', $link) === 1) return $link;
    if (safeExternalUrl($link) !== '') return $link;
    return preg_match('#^[a-zA-Z0-9][a-zA-Z0-9_./?#=&%-]*$#', $link) === 1 ? $link : '';
}

function aboutPublicIcon(string $icon): string
{
    $icons = [
        'design' => '<path d="M4 19.5V15l10.8-10.8a2.1 2.1 0 0 1 3 3L7 18H4Zm11.8-13.8 2.5 2.5M12 20h8"/>',
        'quality' => '<path d="m12 3 2.2 4.5 5 .7-3.6 3.5.9 5-4.5-2.4-4.5 2.4.9-5-3.6-3.5 5-.7L12 3Z"/>',
        'turnkey' => '<path d="M4 12a8 8 0 1 1 2.3 5.7M4 17v-5h5M12 8v4l3 2"/>',
        'planning' => '<path d="M5 3h14v18H5V3Zm4 4h6M9 11h6M9 15h4"/>',
        'projects' => '<path d="M4 7h16v13H4V7Zm4 0V4h8v3M8 12h8"/>',
        'experience' => '<path d="M12 3a9 9 0 1 1-9 9 9 9 0 0 1 9-9Zm0 4v5l3 2"/>',
        'clients' => '<path d="M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-1a3 3 0 1 0 0-6M2 21v-3a5 5 0 0 1 10 0v3m2 0v-2a4 4 0 0 1 7-2.5"/>',
    ];
    $path = $icons[$icon] ?? $icons['design'];
    return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
}

$about = false;
$aboutImages = [];
$aboutFeatures = [];
$aboutCounters = [];
try {
    $about = db()->query('SELECT * FROM about_settings ORDER BY id LIMIT 1')->fetch();
    $aboutImages = db()->query('SELECT * FROM about_slider_images WHERE status=1 ORDER BY sort_order,id')->fetchAll();
    $aboutFeatures = db()->query('SELECT * FROM about_features WHERE status=1 ORDER BY sort_order,id')->fetchAll();
    $aboutCounters = db()->query('SELECT * FROM about_counters WHERE status=1 ORDER BY sort_order,id')->fetchAll();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
}

if (is_array($about)):
    $aboutHomeEnabled = (int) $about['status'] === 1;
    $validImages = [];
    foreach ($aboutImages as $image) if (aboutPublicImageIsSafe((string) $image['image'])) $validImages[] = $image;
    if ($validImages === []) {
        $validImages[] = ['image' => 'assets/images/about/about-fallback.png', 'title' => 'Design24 Studio interior'];
    }
    if ($aboutFeatures === []) {
        $aboutFeatures = [
            ['icon' => 'design', 'title' => 'Creative Design'],
            ['icon' => 'quality', 'title' => 'Quality Craftsmanship'],
            ['icon' => 'turnkey', 'title' => 'Complete Turnkey Service'],
        ];
    }
    if ($aboutCounters === []) {
        $aboutCounters = [
            ['icon'=>'projects','number'=>100,'suffix'=>'+','title'=>'Projects Completed','description'=>'Residential and commercial spaces delivered.'],
            ['icon'=>'experience','number'=>8,'suffix'=>'+','title'=>'Years Experience','description'=>'Design knowledge strengthened through execution.'],
            ['icon'=>'clients','number'=>500,'suffix'=>'+','title'=>'Happy Clients','description'=>'Relationships built through attentive service.'],
            ['icon'=>'turnkey','number'=>360,'suffix'=>'°','title'=>'Turnkey Solutions','description'=>'One team from concept to completion.'],
        ];
    }
?>
<section class="about-section<?= $aboutHomeEnabled ? '' : ' about-module-home-disabled' ?>" id="about" aria-labelledby="about-heading">
    <div class="about-container about-grid">
        <div class="about-copy">
            <p class="about-kicker"><?= siteEscape($about['subtitle']) ?></p>
            <h2 id="about-heading"><?= siteEscape($about['heading']) ?></h2>
            <span class="about-title-line" aria-hidden="true"></span>
            <p class="about-description"><?= nl2br(siteEscape($about['description'])) ?></p>
            <div class="about-feature-list">
                <?php foreach ($aboutFeatures as $feature): ?>
                <div class="about-feature"><span class="about-feature-icon"><?= aboutPublicIcon((string) $feature['icon']) ?></span><strong><?= siteEscape($feature['title']) ?></strong></div>
                <?php endforeach; ?>
            </div>
            <div class="about-actions">
                <?php $buttonOneLink=aboutPublicLink((string)$about['button_one_link']); if ($about['button_one_text'] && $buttonOneLink): ?><a class="about-button about-button-primary" href="<?= siteEscape($buttonOneLink) ?>"><?= siteEscape($about['button_one_text']) ?></a><?php endif; ?>
                <?php $buttonTwoLink=aboutPublicLink((string)$about['button_two_link']); if ($about['button_two_text'] && $buttonTwoLink): ?><a class="about-button about-button-secondary" href="<?= siteEscape($buttonTwoLink) ?>"><?= siteEscape($about['button_two_text']) ?></a><?php endif; ?>
            </div>
        </div>
        <div class="about-carousel" data-about-slider data-slide-count="<?= count($validImages) ?>" aria-label="Design24 Studio interior gallery">
            <div class="about-carousel-frame">
                <?php foreach ($validImages as $index=>$image): ?><figure class="about-carousel-slide<?= $index===0?' active':'' ?>" aria-hidden="<?= $index===0?'false':'true' ?>"><img src="<?= siteEscape($image['image']) ?>" alt="<?= siteEscape($image['title'] ?: 'Design24 Studio interior') ?>" width="1200" height="800"<?= $index>0?' loading="lazy"':'' ?>></figure><?php endforeach; ?>
            </div>
            <?php if (count($validImages)>1): ?><button class="about-carousel-arrow about-carousel-prev" type="button" aria-label="Previous About Us image">‹</button><button class="about-carousel-arrow about-carousel-next" type="button" aria-label="Next About Us image">›</button><div class="about-carousel-dots" role="group" aria-label="Choose About Us image"><?php foreach($validImages as $index=>$image): ?><button class="about-carousel-dot<?= $index===0?' active':'' ?>" type="button" data-about-index="<?= $index ?>" aria-label="Show About Us image <?= $index+1 ?>" aria-current="<?= $index===0?'true':'false' ?>"></button><?php endforeach; ?></div><?php endif; ?>
        </div>
    </div>
</section>
<section class="about-counter-section<?= $aboutHomeEnabled ? '' : ' about-module-home-disabled' ?>" aria-label="Design24 Studio achievements">
    <div class="about-container about-counter-grid">
        <?php foreach ($aboutCounters as $counter): ?><?php $counterValue=max(0,(int)$counter['number']); ?><article class="about-counter-card"><span class="about-counter-icon"><?= aboutPublicIcon((string)$counter['icon']) ?></span><p class="about-counter-value"><span class="counter-number" data-target="<?= $counterValue ?>" data-duration="3000" data-suffix="<?= siteEscape($counter['suffix']) ?>">0</span></p><h3><?= siteEscape($counter['title']) ?></h3><?php if ($counter['description']): ?><p><?= siteEscape($counter['description']) ?></p><?php endif; ?></article><?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

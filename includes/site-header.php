<?php

declare(strict_types=1);

require_once __DIR__ . '/site-portfolio-data.php';

$headerSettings = isset($headerSettings) && is_array($headerSettings)
    ? $headerSettings
    : getHeaderSettings();
$currentPublicPage = basename((string) parse_url($_SERVER['SCRIPT_NAME'] ?? 'index.php', PHP_URL_PATH));
$isPublicHomepage = $currentPublicPage === 'index.php' || $currentPublicPage === '';
$homepagePrefix = $isPublicHomepage ? '' : 'index.php';

$logoPath = $headerSettings['header_logo'] ?: headerSettingDefaults()['header_logo'];
$absoluteLogoPath = dirname(__DIR__) . '/' . ltrim($logoPath, '/');
if (!is_file($absoluteLogoPath)) {
    $logoPath = headerSettingDefaults()['header_logo'];
}

$logoMaxWidth = max(100, min(500, (int) $headerSettings['logo_max_width']));
$whatsappLink = whatsappUrl($headerSettings['whatsapp']);
$phoneDigits = preg_replace('/\D+/', '', $headerSettings['phone']) ?? '';
$whatsappDigits = preg_replace('/\D+/', '', $headerSettings['whatsapp']) ?? '';
$sameContactNumber = $phoneDigits !== '' && $phoneDigits === $whatsappDigits;
$consultationLink = safePageUrl($headerSettings['consultation_button_url'], '#contact');
$socialLinks = [
    ['name' => 'Facebook', 'symbol' => 'f', 'url' => safeExternalUrl($headerSettings['facebook_url'])],
    ['name' => 'YouTube', 'symbol' => '▶', 'url' => safeExternalUrl($headerSettings['youtube_url'])],
    ['name' => 'Instagram', 'symbol' => '◎', 'url' => safeExternalUrl($headerSettings['instagram_url'])],
];
$visibleSocialLinks = array_filter($socialLinks, static fn(array $social): bool => $social['url'] !== '');
$headerClasses = ['site-header'];
if (settingEnabled($headerSettings, 'sticky_header')) {
    $headerClasses[] = 'sticky-enabled';
}
if (settingEnabled($headerSettings, 'header_scroll_shadow')) {
    $headerClasses[] = 'scroll-shadow-enabled';
}
$portfolioMenuTree = getPortfolioMenuTree();
?>
<?php if (settingEnabled($headerSettings, 'show_top_bar')): ?>
    <div class="top-bar">
        <div class="container top-bar-inner">
            <div class="contact-list">
                <?php if ($sameContactNumber): ?>
                    <a class="phone-contact" href="<?= siteEscape($whatsappLink) ?>" target="_blank" rel="noopener noreferrer">
                        <svg class="contact-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.2 1.2.4 2.5.7 3.8.7.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.7 21 3 13.3 3 3.7c0-.6.4-1 1-1h3.3c.6 0 1 .4 1 1 0 1.3.2 2.6.7 3.8.1.4 0 .8-.2 1.1l-2.2 2.2Z"/></svg>
                        <span><?= siteEscape($headerSettings['phone']) ?> (WhatsApp)</span>
                    </a>
                <?php else: ?>
                    <a class="phone-contact" href="<?= siteEscape(telephoneUrl($headerSettings['phone'])) ?>"><svg class="contact-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.2 1.2.4 2.5.7 3.8.7.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.7 21 3 13.3 3 3.7c0-.6.4-1 1-1h3.3c.6 0 1 .4 1 1 0 1.3.2 2.6.7 3.8.1.4 0 .8-.2 1.1l-2.2 2.2Z"/></svg><span><?= siteEscape($headerSettings['phone']) ?></span></a>
                    <a class="whatsapp-contact" href="<?= siteEscape($whatsappLink) ?>" target="_blank" rel="noopener noreferrer"><svg class="contact-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a9.7 9.7 0 0 0-8.4 14.5L2 22l5.7-1.5A10 10 0 1 0 12 2Zm5.7 14.1c-.2.6-1.2 1.2-1.8 1.3-.5.1-1.2.2-3.8-.9-3.2-1.3-5.2-4.6-5.4-4.8-.1-.2-1.3-1.8-1.3-3.4 0-1.6.8-2.4 1.1-2.7.3-.3.7-.4 1-.4h.7c.2 0 .5-.1.8.6l1 2.4c.1.2.1.5 0 .7l-.4.7-.6.6c-.2.2-.4.4-.2.8.2.4.9 1.5 2 2.4 1.4 1.2 2.5 1.6 2.9 1.8.4.2.6.2.8-.1l1.1-1.3c.3-.4.6-.3 1-.2l2.3 1.1c.4.2.7.3.8.5.2.2.2.7 0 1.3Z"/></svg><span><?= siteEscape($headerSettings['whatsapp']) ?> (WhatsApp)</span></a>
                <?php endif; ?>
                <a class="email-contact" href="mailto:<?= siteEscape($headerSettings['email']) ?>"><svg class="contact-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v14H3V5Zm1.8 1.8 7.2 5.5 7.2-5.5H4.8ZM19.2 17V9l-7.2 5.4L4.8 9v8h14.4Z"/></svg><span><?= siteEscape($headerSettings['email']) ?></span></a>
                <span class="location"><svg class="contact-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.3 7 13 7 13s7-7.7 7-13a7 7 0 0 0-7-7Zm0 10a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"/></svg><span><?= siteEscape($headerSettings['short_location']) ?></span></span>
            </div>
            <?php if (settingEnabled($headerSettings, 'show_social_icons') && $visibleSocialLinks !== []): ?>
                <div class="social-list" aria-label="Social media links">
                    <?php foreach ($visibleSocialLinks as $social): ?>
                        <a href="<?= siteEscape($social['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= siteEscape($social['name']) ?>">
                            <?php if ($social['name'] === 'Facebook'): ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8h3V4h-3c-3 0-5 2-5 5v2H6v4h3v7h4v-7h3l1-4h-4V9c0-.7.3-1 1-1Z"/></svg><?php endif; ?>
                            <?php if ($social['name'] === 'YouTube'): ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.6 7.1c-.2-1-1-1.8-2-2C17.8 4.6 12 4.6 12 4.6s-5.8 0-7.6.5c-1 .2-1.8 1-2 2C2 8.9 2 12 2 12s0 3.1.4 4.9c.2 1 1 1.8 2 2 1.8.5 7.6.5 7.6.5s5.8 0 7.6-.5c1-.2 1.8-1 2-2 .4-1.8.4-4.9.4-4.9s0-3.1-.4-4.9ZM10 15.3V8.7l5.7 3.3-5.7 3.3Z"/></svg><?php endif; ?>
                            <?php if ($social['name'] === 'Instagram'): ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5Zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3H7Zm10.5 1.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<header class="<?= siteEscape(implode(' ', $headerClasses)) ?>" id="home">
    <div class="container header-main">
        <a class="brand" href="<?= $isPublicHomepage ? '#home' : 'index.php' ?>" aria-label="<?= siteEscape($headerSettings['website_name']) ?> home">
            <span class="brand-logo-wrap">
                <img class="brand-logo" src="<?= siteEscape($logoPath) ?>" alt="<?= siteEscape($headerSettings['logo_alt']) ?>" style="--logo-max-width: <?= $logoMaxWidth ?>px">
                <?php if ($headerSettings['website_tagline'] !== ''): ?>
                    <small><?= siteEscape($headerSettings['website_tagline']) ?></small>
                <?php endif; ?>
            </span>
        </a>

        <button class="menu-toggle" type="button" aria-label="Open navigation" aria-controls="header-navigation" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <div class="header-navigation" id="header-navigation">
            <nav class="navigation-inner" aria-label="Main navigation">
                <ul class="main-menu">
                    <li><a<?= $isPublicHomepage ? ' class="active"' : '' ?> href="<?= $homepagePrefix ?>#home">Home</a></li>
                    <li><a href="<?= $homepagePrefix ?>#about">About Us</a></li>
                    <li class="menu-item-has-children">
                        <button class="portfolio-toggle" type="button" aria-expanded="false">Portfolio <span aria-hidden="true">⌄</span></button>
                        <ul class="sub-menu">
                            <li><a href="/portfolio.php">All Portfolio</a></li>
                            <?php foreach ($portfolioMenuTree as $portfolioCategory): ?>
                                <li><a href="/portfolio/<?= siteEscape($portfolioCategory['slug']) ?>"><?= siteEscape($portfolioCategory['name']) ?></a></li>
                                <?php foreach ($portfolioCategory['children'] as $portfolioChild): ?>
                                    <li><a href="/portfolio/<?= siteEscape($portfolioChild['slug']) ?>">— <?= siteEscape($portfolioChild['name']) ?></a></li>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <li><a href="<?= $homepagePrefix ?>#videos">Videos</a></li>
                    <li><a<?= $currentPublicPage === 'team.php' ? ' class="active"' : '' ?> href="team.php">Our Team</a></li>
                    <li><a href="<?= $homepagePrefix ?>#office">Office &amp; Factory</a></li>
                    <li><a href="#contact">Contact Us</a></li>
                </ul>
                <div class="header-actions mobile-actions">
                    <?php if (settingEnabled($headerSettings, 'show_consultation_button')): ?>
                        <a class="header-button outline-button" href="<?= siteEscape($consultationLink) ?>"><?= siteEscape($headerSettings['consultation_button_text']) ?></a>
                    <?php endif; ?>
                    <?php if (settingEnabled($headerSettings, 'show_whatsapp_button')): ?>
                        <a class="header-button gold-button" href="<?= siteEscape($whatsappLink) ?>" target="_blank" rel="noopener noreferrer"><svg class="whatsapp-symbol" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a9.7 9.7 0 0 0-8.4 14.5L2 22l5.7-1.5A10 10 0 1 0 12 2Zm5.7 14.1c-.2.6-1.2 1.2-1.8 1.3-.5.1-1.2.2-3.8-.9-3.2-1.3-5.2-4.6-5.4-4.8-.1-.2-1.3-1.8-1.3-3.4 0-1.6.8-2.4 1.1-2.7.3-.3.7-.4 1-.4h.7c.2 0 .5-.1.8.6l1 2.4c.1.2.1.5 0 .7l-.4.7-.6.6c-.2.2-.4.4-.2.8.2.4.9 1.5 2 2.4 1.4 1.2 2.5 1.6 2.9 1.8.4.2.6.2.8-.1l1.1-1.3c.3-.4.6-.3 1-.2l2.3 1.1c.4.2.7.3.8.5.2.2.2.7 0 1.3Z"/></svg><?= siteEscape($headerSettings['whatsapp_button_text']) ?></a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>

        <div class="header-actions desktop-actions">
            <?php if (settingEnabled($headerSettings, 'show_consultation_button')): ?>
                <a class="header-button outline-button" href="<?= siteEscape($consultationLink) ?>"><?= siteEscape($headerSettings['consultation_button_text']) ?></a>
            <?php endif; ?>
            <?php if (settingEnabled($headerSettings, 'show_whatsapp_button')): ?>
                <a class="header-button gold-button" href="<?= siteEscape($whatsappLink) ?>" target="_blank" rel="noopener noreferrer"><svg class="whatsapp-symbol" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a9.7 9.7 0 0 0-8.4 14.5L2 22l5.7-1.5A10 10 0 1 0 12 2Zm5.7 14.1c-.2.6-1.2 1.2-1.8 1.3-.5.1-1.2.2-3.8-.9-3.2-1.3-5.2-4.6-5.4-4.8-.1-.2-1.3-1.8-1.3-3.4 0-1.6.8-2.4 1.1-2.7.3-.3.7-.4 1-.4h.7c.2 0 .5-.1.8.6l1 2.4c.1.2.1.5 0 .7l-.4.7-.6.6c-.2.2-.4.4-.2.8.2.4.9 1.5 2 2.4 1.4 1.2 2.5 1.6 2.9 1.8.4.2.6.2.8-.1l1.1-1.3c.3-.4.6-.3 1-.2l2.3 1.1c.4.2.7.3.8.5.2.2.2.7 0 1.3Z"/></svg><?= siteEscape($headerSettings['whatsapp_button_text']) ?></a>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php

declare(strict_types=1);

require_once __DIR__ . '/site-analytics.php';
require_once __DIR__ . '/site-portfolio-data.php';

$footerSettings = isset($footerSettings) && is_array($footerSettings)
    ? $footerSettings
    : getFooterSettings();
$footerCurrentPage = basename((string) parse_url($_SERVER['SCRIPT_NAME'] ?? 'index.php', PHP_URL_PATH));
$footerHomePrefix = ($footerCurrentPage === 'index.php' || $footerCurrentPage === '') ? '' : 'index.php';
$footerHeaderSettings = isset($headerSettings) && is_array($headerSettings)
    ? $headerSettings
    : getHeaderSettings();

$footerLogo = $footerSettings['footer_logo'] ?: $footerHeaderSettings['header_logo'];
if (!is_file(dirname(__DIR__) . '/' . ltrim($footerLogo, '/'))) {
    $footerLogo = headerSettingDefaults()['header_logo'];
}
$footerWhatsApp = whatsappUrl($footerSettings['footer_whatsapp']);
$footerMapSource = safeGoogleMapSource($footerSettings['footer_map_embed']);
$footerSocials = [
    ['name' => 'Facebook', 'url' => safeExternalUrl($footerSettings['footer_facebook_url'])],
    ['name' => 'YouTube', 'url' => safeExternalUrl($footerSettings['footer_youtube_url'])],
    ['name' => 'Instagram', 'url' => safeExternalUrl($footerSettings['footer_instagram_url'])],
    ['name' => 'WhatsApp', 'url' => $footerWhatsApp !== '#' ? $footerWhatsApp : ''],
];
$footerBackground = $footerSettings['footer_cta_background'];
if ($footerBackground !== '' && !is_file(dirname(__DIR__) . '/' . ltrim($footerBackground, '/'))) {
    $footerBackground = '';
}
$websiteAudioSettings = getWebsiteAudioSettings();
$websiteAudioPath = (string) $websiteAudioSettings['audio_file'];
$websiteAudioIsSafe = preg_match('#^public/uploads/audio/[a-f0-9]{32}\.(?:mp3|wav|ogg)$#', $websiteAudioPath) === 1
    && is_file(dirname(__DIR__) . '/' . $websiteAudioPath);
$websiteAudioExtension = strtolower(pathinfo($websiteAudioPath, PATHINFO_EXTENSION));
$websiteAudioMime = ['mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg'][$websiteAudioExtension] ?? '';
$websiteAudioVolume = max(0, min(30, (int) $websiteAudioSettings['audio_volume'])) / 100;
$websiteAudioPosition = in_array($websiteAudioSettings['audio_button_position'], ['bottom-right', 'bottom-left'], true)
    ? $websiteAudioSettings['audio_button_position']
    : 'bottom-right';
$visitorAnalytics = recordPublicVisit();
$footerPortfolioMenu = getPortfolioMenuTree();
?>
<?php if (settingEnabled($footerSettings, 'footer_show_cta')): ?>
<section class="footer-cta" id="footer-cta"<?= $footerBackground !== '' ? ' style="--footer-cta-image: url(\'' . siteEscape(siteAssetUrl($footerBackground)) . '\')"' : '' ?>>
    <div class="footer-container footer-cta-inner">
        <div class="footer-cta-copy">
            <h2><?= siteEscape($footerSettings['footer_cta_heading']) ?></h2>
            <p><?= siteEscape($footerSettings['footer_cta_description']) ?></p>
        </div>
        <div class="footer-cta-actions">
            <a class="footer-cta-button footer-cta-primary" href="<?= siteEscape(safePageUrl($footerSettings['footer_cta_consultation_url'], '#contact')) ?>"><?= siteEscape($footerSettings['footer_cta_consultation_text']) ?></a>
            <a class="footer-cta-button" href="<?= siteEscape(telephoneUrl($footerSettings['footer_phone'])) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.2 1.2.4 2.5.7 3.8.7.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.7 21 3 13.3 3 3.7c0-.6.4-1 1-1h3.3c.6 0 1 .4 1 1 0 1.3.2 2.6.7 3.8.1.4 0 .8-.2 1.1l-2.2 2.2Z"/></svg><?= siteEscape($footerSettings['footer_cta_call_text']) ?></a>
            <a class="footer-cta-button" href="<?= siteEscape($footerWhatsApp) ?>" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a9.7 9.7 0 0 0-8.4 14.5L2 22l5.7-1.5A10 10 0 1 0 12 2Zm5.7 14.1c-.2.6-1.2 1.2-1.8 1.3-.5.1-1.2.2-3.8-.9-3.2-1.3-5.2-4.6-5.4-4.8-.1-.2-1.3-1.8-1.3-3.4 0-1.6.8-2.4 1.1-2.7.3-.3.7-.4 1-.4h.7c.2 0 .5-.1.8.6l1 2.4c.1.2.1.5 0 .7l-.4.7-.6.6c-.2.2-.4.4-.2.8.2.4.9 1.5 2 2.4 1.4 1.2 2.5 1.6 2.9 1.8.4.2.6.2.8-.1l1.1-1.3c.3-.4.6-.3 1-.2l2.3 1.1c.4.2.7.3.8.5.2.2.2.7 0 1.3Z"/></svg><?= siteEscape($footerSettings['footer_cta_whatsapp_text']) ?></a>
        </div>
    </div>
</section>
<?php endif; ?>

<footer class="site-footer" id="site-footer">
    <div class="footer-container">
        <div class="footer-grid">
            <section class="footer-column footer-brand-column">
                <a class="footer-logo-link" href="<?= $footerHomePrefix ?>#home"><img class="footer-logo" src="<?= siteEscape($footerLogo) ?>" alt="<?= siteEscape($footerSettings['footer_logo_alt']) ?>"></a>
                <p><?= siteEscape($footerSettings['footer_description']) ?></p>
                <?php if (settingEnabled($footerSettings, 'footer_show_social')): ?>
                <div class="footer-socials" aria-label="Footer social links">
                    <?php foreach ($footerSocials as $social): ?><?php if ($social['url'] !== ''): ?>
                    <a href="<?= siteEscape($social['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= siteEscape($social['name']) ?>">
                        <?php if ($social['name'] === 'Facebook'): ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8h3V4h-3c-3 0-5 2-5 5v2H6v4h3v7h4v-7h3l1-4h-4V9c0-.7.3-1 1-1Z"/></svg><?php endif; ?>
                        <?php if ($social['name'] === 'YouTube'): ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.6 7.1c-.2-1-1-1.8-2-2C17.8 4.6 12 4.6 12 4.6s-5.8 0-7.6.5c-1 .2-1.8 1-2 2C2 8.9 2 12 2 12s0 3.1.4 4.9c.2 1 1 1.8 2 2 1.8.5 7.6.5 7.6.5s5.8 0 7.6-.5c1-.2 1.8-1 2-2 .4-1.8.4-4.9.4-4.9s0-3.1-.4-4.9ZM10 15.3V8.7l5.7 3.3-5.7 3.3Z"/></svg><?php endif; ?>
                        <?php if ($social['name'] === 'Instagram'): ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5Zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3H7Zm10.5 1.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg><?php endif; ?>
                        <?php if ($social['name'] === 'WhatsApp'): ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a9.7 9.7 0 0 0-8.4 14.5L2 22l5.7-1.5A10 10 0 1 0 12 2Zm5.7 14.1c-.2.6-1.2 1.2-1.8 1.3-.5.1-1.2.2-3.8-.9-3.2-1.3-5.2-4.6-5.4-4.8-.1-.2-1.3-1.8-1.3-3.4 0-1.6.8-2.4 1.1-2.7.3-.3.7-.4 1-.4h.7c.2 0 .5-.1.8.6l1 2.4c.1.2.1.5 0 .7l-.4.7-.6.6c-.2.2-.4.4-.2.8.2.4.9 1.5 2 2.4 1.4 1.2 2.5 1.6 2.9 1.8.4.2.6.2.8-.1l1.1-1.3c.3-.4.6-.3 1-.2l2.3 1.1c.4.2.7.3.8.5.2.2.2.7 0 1.3Z"/></svg><?php endif; ?>
                    </a>
                    <?php endif; ?><?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>

            <section class="footer-column"><h2>Quick Links</h2><ul class="footer-links"><li><a href="<?= $footerHomePrefix ?>#home">Home</a></li><li><a href="<?= $footerHomePrefix ?>#about">About Us</a></li><li><a href="<?= $footerHomePrefix ?>#services">Services</a></li><li><a href="<?= $footerHomePrefix ?>#portfolio">Portfolio</a></li><li><a href="<?= $footerHomePrefix ?>#videos">Videos</a></li><li><a href="team.php">Our Team</a></li><li><a href="#contact">Contact Us</a></li></ul></section>
            <section class="footer-column"><h2>Portfolio</h2><ul class="footer-links"><li><a href="/portfolio.php">All Portfolio</a></li><?php foreach ($footerPortfolioMenu as $portfolioCategory): ?><li><a href="/portfolio/<?= siteEscape($portfolioCategory['slug']) ?>"><?= siteEscape($portfolioCategory['name']) ?></a></li><?php endforeach; ?></ul></section>

            <section class="footer-column footer-contact" id="contact"><h2>Contact Information</h2>
                <p><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.3 7 13 7 13s7-7.7 7-13a7 7 0 0 0-7-7Zm0 10a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"/></svg><span><?= siteEscape($footerSettings['footer_address']) ?></span></p>
                <p><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.2 1.2.4 2.5.7 3.8.7.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.7 21 3 13.3 3 3.7c0-.6.4-1 1-1h3.3c.6 0 1 .4 1 1 0 1.3.2 2.6.7 3.8.1.4 0 .8-.2 1.1l-2.2 2.2Z"/></svg><a href="<?= siteEscape(telephoneUrl($footerSettings['footer_phone'])) ?>"><?= siteEscape($footerSettings['footer_phone']) ?></a></p>
                <p><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v14H3V5Zm1.8 1.8 7.2 5.5 7.2-5.5H4.8ZM19.2 17V9l-7.2 5.4L4.8 9v8h14.4Z"/></svg><a href="mailto:<?= siteEscape($footerSettings['footer_email']) ?>"><?= siteEscape($footerSettings['footer_email']) ?></a></p>
                <p><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8h3V4h-3c-3 0-5 2-5 5v2H6v4h3v7h4v-7h3l1-4h-4V9c0-.7.3-1 1-1Z"/></svg><a href="<?= siteEscape(safeExternalUrl($footerSettings['footer_facebook_url']) ?: '#') ?>" target="_blank" rel="noopener noreferrer"><?= siteEscape($footerSettings['footer_facebook_name']) ?></a></p>
            </section>

            <section class="footer-column"><h2>Our Location</h2><div class="footer-map">
                <?php if (settingEnabled($footerSettings, 'footer_show_map') && $footerMapSource !== ''): ?><iframe src="<?= siteEscape($footerMapSource) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Design24 Studio location"></iframe>
                <?php else: ?><div class="footer-map-placeholder"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.3 7 13 7 13s7-7.7 7-13a7 7 0 0 0-7-7Zm0 10a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"/></svg><span>Map location will appear here.</span></div><?php endif; ?>
            </div></section>
        </div>

        <div class="footer-bottom"><p><?= siteEscape($footerSettings['footer_copyright']) ?> <span class="footer-visitor-total">Total Visitors: <?= number_format((int) $visitorAnalytics['total_visitors']) ?></span></p><nav aria-label="Legal links"><a href="<?= siteEscape(safePageUrl($footerSettings['footer_privacy_url'])) ?>">Privacy Policy</a><a href="<?= siteEscape(safePageUrl($footerSettings['footer_terms_url'])) ?>">Terms &amp; Conditions</a><a href="<?= siteEscape(safePageUrl($footerSettings['footer_sitemap_url'])) ?>">Sitemap</a></nav></div>
    </div>
</footer>
<?php if (settingEnabled($websiteAudioSettings, 'audio_enabled') && $websiteAudioIsSafe): ?>
<div class="site-audio-control audio-<?= siteEscape($websiteAudioPosition) ?><?= settingEnabled($websiteAudioSettings, 'audio_show_button') ? ' audio-has-button' : '' ?>" data-site-audio data-volume="<?= siteEscape((string) $websiteAudioVolume) ?>" data-autoplay-attempt="<?= settingEnabled($websiteAudioSettings, 'audio_autoplay_attempt') ? '1' : '0' ?>">
    <audio id="siteBgAudio" loop preload="metadata" aria-label="<?= siteEscape($websiteAudioSettings['audio_title']) ?>"><source src="<?= siteEscape(siteAssetUrl($websiteAudioPath)) ?>" type="<?= siteEscape($websiteAudioMime) ?>"></audio>
    <?php if (settingEnabled($websiteAudioSettings, 'audio_show_button')): ?><button id="audioToggleBtn" class="audio-toggle" type="button" aria-label="Turn background music on" title="Turn background music on"><span id="audioIcon" aria-hidden="true">🔇</span><span id="audioText">Sound Off</span></button><?php endif; ?>
</div>
<?php endif; ?>
<?php if (settingEnabled($footerSettings, 'footer_show_back_to_top')): ?><button class="back-to-top" type="button" aria-label="Back to top">↑</button><?php endif; ?>

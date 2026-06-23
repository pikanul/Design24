<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

function headerSettingDefaults(): array
{
    return [
        'header_logo' => 'assets/images/logo/logo.png',
        'logo_alt' => 'Design24 Studio',
        'logo_max_width' => '250',
        'website_name' => 'Design24 Studio',
        'website_tagline' => 'Creating Spaces, Defining Lifestyles',
        'phone' => '+880 1711-293205',
        'whatsapp' => '+880 1711-293205',
        'email' => 'design24studio2@gmail.com',
        'short_location' => 'Baridhara DOHS, Dhaka',
        'facebook_url' => '',
        'youtube_url' => '',
        'instagram_url' => '',
        'consultation_button_text' => 'Book a Consultation',
        'consultation_button_url' => '#contact',
        'whatsapp_button_text' => 'WhatsApp Us',
        'show_top_bar' => '1',
        'show_social_icons' => '1',
        'show_consultation_button' => '1',
        'show_whatsapp_button' => '1',
        'sticky_header' => '1',
        'header_scroll_shadow' => '1',
    ];
}

function getSiteSettings(string $group, array $defaults = []): array
{
    try {
        $statement = db()->prepare(
            'SELECT setting_key, setting_value FROM site_settings WHERE setting_group = :setting_group'
        );
        $statement->execute([':setting_group' => $group]);

        foreach ($statement->fetchAll() as $row) {
            if (array_key_exists($row['setting_key'], $defaults)) {
                $defaults[$row['setting_key']] = (string) ($row['setting_value'] ?? '');
            }
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
    }

    return $defaults;
}

function getHeaderSettings(): array
{
    return getSiteSettings('header', headerSettingDefaults());
}

function settingEnabled(array $settings, string $key): bool
{
    return ($settings[$key] ?? '0') === '1';
}

function siteEscape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function safeExternalUrl(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : '';
}

function safePageUrl(?string $url, string $fallback = '#'): string
{
    $url = trim((string) $url);
    if ($url !== '' && ($url[0] === '#' || $url[0] === '/')) {
        return $url;
    }

    return safeExternalUrl($url) ?: $fallback;
}

function whatsappUrl(?string $number): string
{
    $digits = preg_replace('/\D+/', '', (string) $number) ?? '';
    return $digits !== '' ? 'https://wa.me/' . $digits : '#';
}

function telephoneUrl(?string $number): string
{
    $clean = preg_replace('/[^0-9+]/', '', (string) $number) ?? '';
    return $clean !== '' ? 'tel:' . $clean : '#';
}

function siteAssetUrl(string $path): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');
    return ($basePath !== '' ? $basePath : '') . '/' . ltrim($path, '/');
}

function footerSettingDefaults(): array
{
    $header = getHeaderSettings();

    return [
        'footer_logo' => '',
        'footer_logo_alt' => 'Design24 Studio',
        'footer_description' => 'Design24 Studio creates functional, elegant, and personalized residential and commercial spaces through professional design, quality production, and complete project execution.',
        'footer_address' => 'House No. 531/2, Ground Floor, Road 11, Baridhara DOHS, Dhaka-1206, Bangladesh',
        'footer_phone' => $header['phone'],
        'footer_whatsapp' => $header['whatsapp'],
        'footer_email' => $header['email'],
        'footer_facebook_name' => 'Design24Studio',
        'footer_facebook_url' => $header['facebook_url'],
        'footer_youtube_url' => $header['youtube_url'],
        'footer_instagram_url' => $header['instagram_url'],
        'footer_show_social' => '1',
        'footer_privacy_url' => '#privacy',
        'footer_terms_url' => '#terms',
        'footer_sitemap_url' => '#sitemap',
        'footer_map_embed' => '',
        'footer_show_map' => '1',
        'footer_cta_heading' => 'Planning to Design or Renovate Your Space?',
        'footer_cta_description' => 'Talk to our professional team and let us create a functional, elegant, and personalized space for you.',
        'footer_cta_consultation_text' => 'Book a Consultation',
        'footer_cta_consultation_url' => '#contact',
        'footer_cta_call_text' => 'Call Us Now',
        'footer_cta_whatsapp_text' => 'Chat on WhatsApp',
        'footer_cta_background' => 'assets/images/hero/hero-living-room.png',
        'footer_show_cta' => '1',
        'footer_copyright' => '© 2026 Design24 Studio. All Rights Reserved.',
        'footer_show_back_to_top' => '1',
    ];
}

function getFooterSettings(): array
{
    return getSiteSettings('footer', footerSettingDefaults());
}

function homepageSettingDefaults(): array
{
    return [
        'hero_eyebrow' => 'Welcome to Design24 Studio',
        'hero_heading_line_1' => 'Transforming',
        'hero_heading_line_2' => 'Spaces Into',
        'hero_heading_highlight' => 'Timeless Experiences',
        'hero_description_1' => 'Design24 Studio provides professional interior design, architectural planning, 3D visualization, customized furniture, and complete project execution for residential and commercial spaces.',
        'hero_description_2' => 'From the initial concept to final installation, our experienced team delivers functional, elegant, and personalized spaces that reflect your lifestyle and vision.',
        'hero_primary_text' => 'Explore Our Portfolio',
        'hero_primary_url' => '#portfolio',
        'hero_secondary_text' => 'Get a Free Consultation',
        'hero_secondary_url' => '#contact',
        'hero_image' => 'assets/images/hero/hero-living-room.png',
        'hero_image_alt' => 'Luxury living room and kitchen interior',
        'service_residential' => 'Residential',
        'service_commercial' => 'Commercial',
        'service_office' => 'Office',
        'service_kitchen' => 'Kitchen',
        'service_furniture' => 'Customized Furniture',
        'service_turnkey' => 'Turnkey Execution',
        'show_service_row' => '1',
    ];
}

function getHomepageSettings(): array
{
    return getSiteSettings('homepage', homepageSettingDefaults());
}

function teamPageSettingDefaults(): array
{
    return [
        'team_hero_label' => 'OUR STRENGTH',
        'team_hero_heading' => 'Meet Our Team',
        'team_hero_description' => 'Our people bring design, management, engineering, field expertise, and factory execution together under one roof.',
        'team_hero_desktop_image' => '',
        'team_hero_mobile_image' => '',
        'team_hero_image_alt' => 'Design24 Studio team',
        'team_hero_alignment' => 'left',
        'team_show_hero' => '1',
        'team_leadership_label' => 'OUR LEADERSHIP',
        'team_leadership_title' => 'Leadership Team',
        'team_leadership_description' => 'The people guiding our studio with experience, integrity, and a shared commitment to excellent design and execution.',
        'team_show_leadership' => '1',
        'team_featured_limit' => '4',
        'team_section_heading' => 'Our People',
        'team_section_description' => 'Meet the specialists who turn ideas into thoughtful, functional, and beautifully executed spaces.',
        'team_show_filters' => '1',
        'team_show_member_count' => '1',
        'team_show_group_descriptions' => '1',
        'team_show_social_icons' => '1',
        'team_enable_profile_popup' => '1',
        'team_desktop_columns' => '4',
        'team_tablet_columns' => '2',
        'team_mobile_columns' => '1',
        'team_meta_title' => 'Our Team | Design24 Studio',
        'team_meta_description' => 'Meet the Design24 Studio management, design, field, and production professionals.',
        'team_og_image' => '',
    ];
}

function getTeamPageSettings(): array
{
    return getSiteSettings('team_page', teamPageSettingDefaults());
}

function websiteAudioSettingDefaults(): array
{
    return [
        'audio_enabled' => '0',
        'audio_file' => '',
        'audio_title' => 'Design24 Studio Background Music',
        'audio_volume' => '15',
        'audio_show_button' => '1',
        'audio_button_position' => 'bottom-right',
        'audio_autoplay_attempt' => '0',
    ];
}

function getWebsiteAudioSettings(): array
{
    return getSiteSettings('website_audio', websiteAudioSettingDefaults());
}

function safeGoogleMapSource(?string $embedCode): string
{
    $embedCode = trim((string) $embedCode);
    if ($embedCode === '' || stripos($embedCode, '<script') !== false) {
        return '';
    }

    $source = $embedCode;
    if (str_contains($embedCode, '<')) {
        if (!preg_match('/<iframe\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1/is', $embedCode, $matches)) {
            return '';
        }
        $source = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    if (!filter_var($source, FILTER_VALIDATE_URL)) {
        return '';
    }

    $scheme = strtolower((string) parse_url($source, PHP_URL_SCHEME));
    $host = strtolower((string) parse_url($source, PHP_URL_HOST));
    $path = (string) parse_url($source, PHP_URL_PATH);
    $allowedHost = $host === 'google.com' || $host === 'maps.google.com' || str_ends_with($host, '.google.com');

    return $scheme === 'https' && $allowedHost && str_starts_with($path, '/maps') ? $source : '';
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/site-settings.php';
$headerSettings = getHeaderSettings();
$teamSettings = getTeamPageSettings();

function teamImageIsSafe(string $path, string $area): bool
{
    if ($path === '') return false;
    $patterns = [
        'member' => '#^uploads/site/team/members/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#',
        'group' => '#^uploads/site/team/groups/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#',
        'page' => '#^uploads/site/team/page/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#',
    ];
    return isset($patterns[$area]) && preg_match($patterns[$area], $path) === 1 && is_file(__DIR__ . '/' . $path);
}

function teamInitials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    return $initials ?: 'D24';
}

function teamGroupIcon(string $icon): string
{
    if ($icon === 'factory') return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21V8l6 3V8l6 3V3h3v18H3Zm3-3h2v-3H6v3Zm5 0h2v-3h-2v3Zm5 0h2v-3h-2v3Z"/></svg>';
    if ($icon === 'compass') return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm3.8 6.2-2.1 5.5-5.5 2.1 2.1-5.5 5.5-2.1ZM12 11a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z"/></svg>';
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 4h6a2 2 0 0 1 2 2v2h4v12H3V8h4V6a2 2 0 0 1 2-2Zm0 4h6V6H9v2Zm2 3v2H5v5h14v-5h-6v-2h-2Z"/></svg>';
}

$groupsStatement = db()->prepare('SELECT * FROM team_groups WHERE status = 1 AND show_on_team_page = 1 ORDER BY display_order ASC, id ASC');
$groupsStatement->execute();
$groups = $groupsStatement->fetchAll();
$membersStatement = db()->prepare('SELECT m.*, g.name AS group_name, g.short_name AS group_short_name, g.slug AS group_slug, g.icon AS group_icon FROM team_members m JOIN team_groups g ON g.id = m.team_group_id WHERE m.status = 1 AND g.status = 1 AND g.show_on_team_page = 1 ORDER BY g.display_order ASC, m.display_order ASC, m.id ASC');
$membersStatement->execute();
$members = $membersStatement->fetchAll();
$membersByGroup = [];
foreach ($members as $member) $membersByGroup[(int) $member['team_group_id']][] = $member;
$featured = array_values(array_filter($members, static function (array $member): bool { return (int) $member['featured_member'] === 1; }));
$featured = array_slice($featured, 0, max(1, min(12, (int) $teamSettings['team_featured_limit'])));
$desktopHero = teamImageIsSafe($teamSettings['team_hero_desktop_image'], 'page') ? $teamSettings['team_hero_desktop_image'] : '';
$mobileHero = teamImageIsSafe($teamSettings['team_hero_mobile_image'], 'page') ? $teamSettings['team_hero_mobile_image'] : $desktopHero;
$desktopHeroRatio = '16 / 9';
$mobileHeroRatio = $desktopHeroRatio;
if ($desktopHero !== '') {
    $desktopSize = @getimagesize(__DIR__ . '/' . $desktopHero);
    if (is_array($desktopSize) && $desktopSize[0] > 0 && $desktopSize[1] > 0) {
        $desktopHeroRatio = (int) $desktopSize[0] . ' / ' . (int) $desktopSize[1];
        $mobileHeroRatio = $desktopHeroRatio;
    }
}
if ($mobileHero !== '' && $mobileHero !== $desktopHero) {
    $mobileSize = @getimagesize(__DIR__ . '/' . $mobileHero);
    if (is_array($mobileSize) && $mobileSize[0] > 0 && $mobileSize[1] > 0) {
        $mobileHeroRatio = (int) $mobileSize[0] . ' / ' . (int) $mobileSize[1];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= siteEscape($teamSettings['team_meta_description']) ?>">
    <meta property="og:title" content="<?= siteEscape($teamSettings['team_meta_title']) ?>">
    <meta property="og:description" content="<?= siteEscape($teamSettings['team_meta_description']) ?>">
    <?php if (teamImageIsSafe($teamSettings['team_og_image'], 'page')): ?><meta property="og:image" content="<?= siteEscape(siteAssetUrl($teamSettings['team_og_image'])) ?>"><?php endif; ?>
    <title><?= siteEscape($teamSettings['team_meta_title']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="team-page-body<?= settingEnabled($headerSettings, 'show_top_bar') ? ' has-top-bar' : ' no-top-bar' ?>" style="--team-desktop-columns:<?= max(2, min(6, (int) $teamSettings['team_desktop_columns'])) ?>;--team-tablet-columns:<?= max(1, min(4, (int) $teamSettings['team_tablet_columns'])) ?>;--team-mobile-columns:<?= max(1, min(2, (int) $teamSettings['team_mobile_columns'])) ?>;--team-hero-ratio:<?= siteEscape($desktopHeroRatio) ?>;--team-mobile-hero-ratio:<?= siteEscape($mobileHeroRatio) ?>">
<?php require __DIR__ . '/includes/site-header.php'; ?>
<main class="team-page-main">
    <?php if (settingEnabled($teamSettings, 'team_show_hero')): ?>
    <section class="team-hero team-align-<?= siteEscape(in_array($teamSettings['team_hero_alignment'], ['left', 'center', 'right'], true) ? $teamSettings['team_hero_alignment'] : 'left') ?>">
        <?php if ($desktopHero !== ''): ?><picture><source media="(max-width:700px)" srcset="<?= siteEscape($mobileHero) ?>"><img src="<?= siteEscape($desktopHero) ?>" alt="<?= siteEscape($teamSettings['team_hero_image_alt']) ?>" fetchpriority="high"></picture><?php endif; ?>
        <div class="team-hero-shade"></div><div class="team-page-container team-hero-inner"><div class="team-hero-copy"><p class="team-kicker"><?= siteEscape($teamSettings['team_hero_label']) ?></p><h1><?= siteEscape($teamSettings['team_hero_heading']) ?></h1><span class="team-heading-line" aria-hidden="true"></span><p><?= siteEscape($teamSettings['team_hero_description']) ?></p></div></div>
    </section>
    <?php endif; ?>

    <?php if (settingEnabled($teamSettings, 'team_show_leadership') && $featured !== []): ?>
    <section class="team-leadership"><div class="team-page-container"><header class="team-section-header"><p class="team-kicker"><?= siteEscape($teamSettings['team_leadership_label']) ?></p><h2><?= siteEscape($teamSettings['team_leadership_title']) ?></h2><p><?= siteEscape($teamSettings['team_leadership_description']) ?></p></header><div class="leadership-grid">
        <?php foreach ($featured as $member): ?><button class="leadership-card<?= settingEnabled($teamSettings, 'team_enable_profile_popup') ? ' team-profile-trigger' : '' ?>" type="button" data-member-id="<?= (int) $member['id'] ?>"><span class="leadership-photo"><?php if (teamImageIsSafe((string) $member['image'], 'member')): ?><img src="<?= siteEscape($member['image']) ?>" alt="<?= siteEscape($member['image_alt'] ?: $member['full_name']) ?>" width="160" height="200"><?php else: ?><span class="team-photo-placeholder"><?= siteEscape(teamInitials($member['full_name'])) ?></span><?php endif; ?></span><span class="leadership-info"><strong><?= siteEscape($member['full_name']) ?></strong><small><?= siteEscape($member['designation']) ?></small><em><?= siteEscape($member['group_short_name']) ?></em></span></button><?php endforeach; ?>
    </div></div></section>
    <?php endif; ?>

    <section class="team-directory"><div class="team-page-container"><header class="team-section-header team-directory-header"><h2><?= siteEscape($teamSettings['team_section_heading']) ?></h2><p><?= siteEscape($teamSettings['team_section_description']) ?></p></header>
        <?php if (settingEnabled($teamSettings, 'team_show_filters')): ?><div class="team-filter-bar" role="group" aria-label="Filter team groups"><button class="team-filter active" type="button" data-team-filter="all" aria-pressed="true">All</button><?php foreach ($groups as $group): ?><?php if ((int) $group['show_in_filters'] === 1): ?><button class="team-filter" type="button" data-team-filter="<?= siteEscape($group['slug']) ?>" aria-pressed="false"><?= siteEscape($group['short_name']) ?></button><?php endif; ?><?php endforeach; ?></div><?php endif; ?>
        <div class="team-groups-wrap"><?php foreach ($groups as $group): ?><?php $groupMembers = $membersByGroup[(int) $group['id']] ?? []; if ($groupMembers === []) continue; ?><section class="team-group-section" data-team-group="<?= siteEscape($group['slug']) ?>"><header class="team-group-header"><span class="team-group-icon"><?= teamGroupIcon((string) $group['icon']) ?></span><div><h2><?= siteEscape($group['name']) ?><?php if (settingEnabled($teamSettings, 'team_show_member_count')): ?><small><?= count($groupMembers) ?> members</small><?php endif; ?></h2><?php if (settingEnabled($teamSettings, 'team_show_group_descriptions') && $group['description'] !== ''): ?><p><?= siteEscape($group['description']) ?></p><?php endif; ?></div></header><div class="team-member-grid">
            <?php foreach ($groupMembers as $member): ?><button class="team-member-card<?= settingEnabled($teamSettings, 'team_enable_profile_popup') ? ' team-profile-trigger' : '' ?>" type="button" data-member-id="<?= (int) $member['id'] ?>"><span class="team-member-photo"><?php if (teamImageIsSafe((string) $member['image'], 'member')): ?><img src="<?= siteEscape($member['image']) ?>" alt="<?= siteEscape($member['image_alt'] ?: $member['full_name']) ?>" width="400" height="500" loading="lazy"><?php else: ?><span class="team-photo-placeholder"><?= siteEscape(teamInitials($member['full_name'])) ?></span><?php endif; ?></span><span class="team-member-info"><strong><?= siteEscape($member['full_name']) ?></strong><small><?= siteEscape($member['designation']) ?></small><em><?= siteEscape($member['group_short_name']) ?></em></span></button><?php endforeach; ?>
        </div></section><?php endforeach; ?></div>
    </div></section>
</main>

<?php if (settingEnabled($teamSettings, 'team_enable_profile_popup')): ?><div class="team-profile-modal" id="team-profile-modal" role="dialog" aria-modal="true" aria-labelledby="team-modal-name" hidden><div class="team-modal-backdrop" data-team-modal-close></div><div class="team-modal-panel" role="document"><button class="team-modal-close" type="button" data-team-modal-close aria-label="Close member profile">×</button><div id="team-modal-content"></div></div></div>
<?php foreach ($members as $member): ?><template id="team-profile-<?= (int) $member['id'] ?>"><div class="team-profile-detail"><div class="team-profile-large-photo"><?php if (teamImageIsSafe((string) $member['image'], 'member')): ?><img src="<?= siteEscape($member['image']) ?>" alt="<?= siteEscape($member['image_alt'] ?: $member['full_name']) ?>"><?php else: ?><span class="team-photo-placeholder"><?= siteEscape(teamInitials($member['full_name'])) ?></span><?php endif; ?></div><div class="team-profile-copy"><p class="team-kicker"><?= siteEscape($member['group_name']) ?></p><h2 id="team-modal-name"><?= siteEscape($member['full_name']) ?></h2><h3><?= siteEscape($member['designation']) ?></h3><?php if ($member['department']): ?><p><strong>Department:</strong> <?= siteEscape($member['department']) ?></p><?php endif; ?><?php if ($member['full_bio'] || $member['short_bio']): ?><p><?= nl2br(siteEscape($member['full_bio'] ?: $member['short_bio'])) ?></p><?php endif; ?><?php if ($member['specialization']): ?><p><strong>Specialization:</strong> <?= siteEscape($member['specialization']) ?></p><?php endif; ?><?php if ($member['joining_year']): ?><p><strong>Joining year:</strong> <?= (int) $member['joining_year'] ?></p><?php endif; ?><?php if ($member['location']): ?><p><strong>Location:</strong> <?= siteEscape($member['location']) ?></p><?php endif; ?><div class="team-profile-links"><?php if ($member['email']): ?><a href="mailto:<?= siteEscape($member['email']) ?>">Email</a><?php endif; ?><?php if ($member['phone']): ?><a href="<?= siteEscape(telephoneUrl($member['phone'])) ?>">Phone</a><?php endif; ?><?php foreach (['linkedin_url' => 'LinkedIn', 'facebook_url' => 'Facebook', 'instagram_url' => 'Instagram'] as $key => $label): ?><?php if (safeExternalUrl($member[$key])): ?><a href="<?= siteEscape($member[$key]) ?>" target="_blank" rel="noopener noreferrer"><?= $label ?></a><?php endif; ?><?php endforeach; ?></div></div></div></template><?php endforeach; ?><?php endif; ?>
<?php require __DIR__ . '/includes/site-footer.php'; ?>
<script src="assets/js/script.js"></script>
</body></html>

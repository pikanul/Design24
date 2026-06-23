<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$pdo = db();
$allowedPeriods = ['7', '30', '90', 'all'];
$period = in_array($_GET['period'] ?? '', $allowedPeriods, true) ? (string) $_GET['period'] : '30';
$timezone = new DateTimeZone('Asia/Dhaka');
$now = new DateTimeImmutable('now', $timezone);
$cutoff = $period === 'all' ? '1970-01-01 00:00:00' : $now->modify('-' . ((int) $period - 1) . ' days')->setTime(0, 0)->format('Y-m-d H:i:s');
$today = $now->format('Y-m-d');

function reportRows(PDO $pdo, string $sql, array $params = []): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function reportValue(PDO $pdo, string $sql, array $params = []): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return (int) $statement->fetchColumn();
}

$params = [':cutoff' => $cutoff];
$summary = [
    'total_visitors' => reportValue($pdo, 'SELECT COUNT(DISTINCT visitor_hash) FROM visitor_analytics'),
    'total_views' => reportValue($pdo, 'SELECT COUNT(*) FROM visitor_analytics'),
    'period_visitors' => reportValue($pdo, 'SELECT COUNT(DISTINCT visitor_hash) FROM visitor_analytics WHERE visited_at>=:cutoff', $params),
    'period_views' => reportValue($pdo, 'SELECT COUNT(*) FROM visitor_analytics WHERE visited_at>=:cutoff', $params),
    'today_visitors' => reportValue($pdo, 'SELECT COUNT(DISTINCT visitor_hash) FROM visitor_analytics WHERE substr(visited_at,1,10)=:today', [':today' => $today]),
];
$devices = reportRows($pdo, 'SELECT device_type AS label,COUNT(*) AS views,COUNT(DISTINCT visitor_hash) AS visitors FROM visitor_analytics WHERE visited_at>=:cutoff GROUP BY device_type ORDER BY views DESC', $params);
$browsers = reportRows($pdo, 'SELECT browser AS label,COUNT(*) AS views FROM visitor_analytics WHERE visited_at>=:cutoff GROUP BY browser ORDER BY views DESC LIMIT 10', $params);
$systems = reportRows($pdo, 'SELECT operating_system AS label,COUNT(*) AS views FROM visitor_analytics WHERE visited_at>=:cutoff GROUP BY operating_system ORDER BY views DESC LIMIT 10', $params);
$pages = reportRows($pdo, 'SELECT page_path AS label,COUNT(*) AS views,COUNT(DISTINCT visitor_hash) AS visitors FROM visitor_analytics WHERE visited_at>=:cutoff GROUP BY page_path ORDER BY views DESC LIMIT 10', $params);
$referrers = reportRows($pdo, 'SELECT referrer_domain AS label,COUNT(*) AS views FROM visitor_analytics WHERE visited_at>=:cutoff GROUP BY referrer_domain ORDER BY views DESC LIMIT 10', $params);
$daily = reportRows($pdo, 'SELECT substr(visited_at,1,10) AS visit_date,COUNT(*) AS views,COUNT(DISTINCT visitor_hash) AS visitors FROM visitor_analytics WHERE visited_at>=:cutoff GROUP BY substr(visited_at,1,10) ORDER BY visit_date DESC LIMIT 90', $params);
$recent = reportRows($pdo, 'SELECT page_path,device_type,browser,operating_system,referrer_domain,visited_at FROM visitor_analytics ORDER BY visited_at DESC,id DESC LIMIT 50');
$pageTitle = 'Visitor Analytics';
require __DIR__ . '/includes/header.php';
?>
<style>
.analytics-toolbar{display:flex;margin-bottom:24px;align-items:center;justify-content:space-between;gap:16px}.analytics-toolbar a{color:var(--green);font-weight:700}.analytics-filter{display:flex;align-items:center;gap:10px}.analytics-filter select{width:auto}.analytics-stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin:25px 0}.analytics-stat{padding:18px;border:1px solid var(--line);border-radius:7px;background:#fbfcfb}.analytics-stat strong{display:block;color:var(--green);font-size:1.65rem}.analytics-stat span{font-size:.78rem}.analytics-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-top:18px}.analytics-card{padding:20px;border:1px solid var(--line);border-radius:7px;background:#fff}.analytics-card h2{margin-top:0;font-size:1.1rem}.analytics-table-wrap{overflow-x:auto}.analytics-table{width:100%;border-collapse:collapse}.analytics-table th,.analytics-table td{padding:10px;border-bottom:1px solid var(--line);text-align:left;font-size:.8rem;white-space:nowrap}.analytics-table th{color:var(--green)}.analytics-note{margin-top:24px;padding:16px;border-left:4px solid #b9985f;background:#fff9ed;color:#594a2c}.analytics-wide{grid-column:1/-1}@media(max-width:900px){.analytics-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.analytics-grid{grid-template-columns:1fr}.analytics-wide{grid-column:auto}}@media(max-width:550px){.analytics-toolbar{align-items:flex-start;flex-direction:column}.analytics-stats{grid-template-columns:1fr 1fr}.analytics-stat{padding:14px}.analytics-stat strong{font-size:1.35rem}}
</style>
<header class="admin-header"><a class="admin-brand" href="dashboard.php">Design24 Studio Admin</a><div class="admin-user"><span>Signed in as <?= e(currentAdminName()) ?></span><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><button class="logout-button">Logout</button></form></div></header>
<main class="admin-main">
    <div class="analytics-toolbar"><a href="dashboard.php">← Dashboard</a><form class="analytics-filter" method="get"><label for="period">Report period</label><select id="period" name="period" onchange="this.form.submit()"><option value="7"<?= $period==='7'?' selected':'' ?>>Last 7 days</option><option value="30"<?= $period==='30'?' selected':'' ?>>Last 30 days</option><option value="90"<?= $period==='90'?' selected':'' ?>>Last 90 days</option><option value="all"<?= $period==='all'?' selected':'' ?>>All time</option></select></form></div>
    <section class="panel"><h1>Visitor Analytics</h1><p>Privacy-conscious website activity and device reporting. Times use Asia/Dhaka.</p>
        <div class="analytics-stats"><div class="analytics-stat"><strong><?= number_format($summary['total_visitors']) ?></strong><span>Total unique visitors</span></div><div class="analytics-stat"><strong><?= number_format($summary['total_views']) ?></strong><span>Total page views</span></div><div class="analytics-stat"><strong><?= number_format($summary['period_visitors']) ?></strong><span>Visitors in period</span></div><div class="analytics-stat"><strong><?= number_format($summary['period_views']) ?></strong><span>Views in period</span></div><div class="analytics-stat"><strong><?= number_format($summary['today_visitors']) ?></strong><span>Visitors today</span></div></div>
        <div class="analytics-grid">
            <section class="analytics-card"><h2>Device Types</h2><div class="analytics-table-wrap"><table class="analytics-table"><thead><tr><th>Device</th><th>Visitors</th><th>Views</th></tr></thead><tbody><?php foreach($devices as$row):?><tr><td><?= e($row['label']) ?></td><td><?= number_format((int)$row['visitors']) ?></td><td><?= number_format((int)$row['views']) ?></td></tr><?php endforeach;?><?php if($devices===[]):?><tr><td colspan="3">No data yet.</td></tr><?php endif;?></tbody></table></div></section>
            <section class="analytics-card"><h2>Popular Pages</h2><div class="analytics-table-wrap"><table class="analytics-table"><thead><tr><th>Page</th><th>Visitors</th><th>Views</th></tr></thead><tbody><?php foreach($pages as$row):?><tr><td><?= e($row['label']) ?></td><td><?= number_format((int)$row['visitors']) ?></td><td><?= number_format((int)$row['views']) ?></td></tr><?php endforeach;?><?php if($pages===[]):?><tr><td colspan="3">No data yet.</td></tr><?php endif;?></tbody></table></div></section>
            <section class="analytics-card"><h2>Browsers</h2><div class="analytics-table-wrap"><table class="analytics-table"><thead><tr><th>Browser</th><th>Views</th></tr></thead><tbody><?php foreach($browsers as$row):?><tr><td><?= e($row['label']) ?></td><td><?= number_format((int)$row['views']) ?></td></tr><?php endforeach;?><?php if($browsers===[]):?><tr><td colspan="2">No data yet.</td></tr><?php endif;?></tbody></table></div></section>
            <section class="analytics-card"><h2>Operating Systems</h2><div class="analytics-table-wrap"><table class="analytics-table"><thead><tr><th>System</th><th>Views</th></tr></thead><tbody><?php foreach($systems as$row):?><tr><td><?= e($row['label']) ?></td><td><?= number_format((int)$row['views']) ?></td></tr><?php endforeach;?><?php if($systems===[]):?><tr><td colspan="2">No data yet.</td></tr><?php endif;?></tbody></table></div></section>
            <section class="analytics-card"><h2>Referrer Domains</h2><div class="analytics-table-wrap"><table class="analytics-table"><thead><tr><th>Source</th><th>Views</th></tr></thead><tbody><?php foreach($referrers as$row):?><tr><td><?= e($row['label'] ?: 'Direct') ?></td><td><?= number_format((int)$row['views']) ?></td></tr><?php endforeach;?><?php if($referrers===[]):?><tr><td colspan="2">No data yet.</td></tr><?php endif;?></tbody></table></div></section>
            <section class="analytics-card"><h2>Daily Activity</h2><div class="analytics-table-wrap"><table class="analytics-table"><thead><tr><th>Date</th><th>Visitors</th><th>Views</th></tr></thead><tbody><?php foreach($daily as$row):?><tr><td><?= e($row['visit_date']) ?></td><td><?= number_format((int)$row['visitors']) ?></td><td><?= number_format((int)$row['views']) ?></td></tr><?php endforeach;?><?php if($daily===[]):?><tr><td colspan="3">No data yet.</td></tr><?php endif;?></tbody></table></div></section>
            <section class="analytics-card analytics-wide"><h2>Recent Visits</h2><div class="analytics-table-wrap"><table class="analytics-table"><thead><tr><th>Time</th><th>Page</th><th>Device</th><th>Browser</th><th>OS</th><th>Referrer</th></tr></thead><tbody><?php foreach($recent as$row):?><tr><td><?= e($row['visited_at']) ?></td><td><?= e($row['page_path']) ?></td><td><?= e($row['device_type']) ?></td><td><?= e($row['browser']) ?></td><td><?= e($row['operating_system']) ?></td><td><?= e($row['referrer_domain'] ?: 'Direct') ?></td></tr><?php endforeach;?><?php if($recent===[]):?><tr><td colspan="6">No visits recorded yet.</td></tr><?php endif;?></tbody></table></div></section>
        </div>
        <div class="analytics-note"><strong>Privacy note:</strong> Gender, names, raw IP addresses, exact location, and cross-site identity are not collected or inferred. Gender cannot be accurately or responsibly determined from a website visit.</div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>

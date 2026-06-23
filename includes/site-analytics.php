<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

function analyticsSecret(PDO $pdo): string
{
    $find = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_group='internal_analytics' AND setting_key='visitor_hash_secret' LIMIT 1");
    $find->execute();
    $secret = (string) $find->fetchColumn();
    if (strlen($secret) >= 32) return $secret;

    $secret = bin2hex(random_bytes(32));
    try {
        $insert = $pdo->prepare("INSERT INTO site_settings (setting_group,setting_key,setting_value,setting_type,created_at,updated_at) VALUES ('internal_analytics','visitor_hash_secret',:secret,'secret',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
        $insert->execute([':secret' => $secret]);
    } catch (Throwable $exception) {
        $find->execute();
        $existing = (string) $find->fetchColumn();
        if (strlen($existing) >= 32) return $existing;
        throw $exception;
    }
    return $secret;
}

function analyticsDeviceType(string $userAgent): string
{
    if (preg_match('/ipad|tablet|kindle|silk|playbook/i', $userAgent)) return 'Tablet';
    if (preg_match('/mobile|iphone|ipod|android.*mobile|windows phone/i', $userAgent)) return 'Mobile';
    return 'Desktop/Laptop';
}

function analyticsBrowser(string $userAgent): string
{
    if (preg_match('/Edg\//i', $userAgent)) return 'Microsoft Edge';
    if (preg_match('/OPR\/|Opera/i', $userAgent)) return 'Opera';
    if (preg_match('/Chrome\//i', $userAgent)) return 'Google Chrome';
    if (preg_match('/Safari\//i', $userAgent) && !preg_match('/Chrome\//i', $userAgent)) return 'Safari';
    if (preg_match('/Firefox\//i', $userAgent)) return 'Firefox';
    return 'Other';
}

function analyticsOperatingSystem(string $userAgent): string
{
    if (preg_match('/Windows NT/i', $userAgent)) return 'Windows';
    if (preg_match('/Android/i', $userAgent)) return 'Android';
    if (preg_match('/iPhone|iPad|iPod/i', $userAgent)) return 'iOS/iPadOS';
    if (preg_match('/Mac OS X|Macintosh/i', $userAgent)) return 'macOS';
    if (preg_match('/Linux/i', $userAgent)) return 'Linux';
    return 'Other';
}

function analyticsReferrerDomain(string $referrer): string
{
    if ($referrer === '') return 'Direct';
    $host = strtolower((string) parse_url($referrer, PHP_URL_HOST));
    if ($host === '') return 'Direct';
    return mb_substr($host, 0, 190);
}

function analyticsIsBot(string $userAgent): bool
{
    return $userAgent === '' || preg_match('/bot|crawl|spider|slurp|preview|monitor|headless|curl|wget|postman|insomnia/i', $userAgent) === 1;
}

function recordPublicVisit(): array
{
    static $result = null;
    if (is_array($result)) return $result;
    $result = ['total_visitors' => 0, 'total_page_views' => 0];

    try {
        $pdo = db();
        $userAgent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000);
        if (!analyticsIsBot($userAgent)) {
            $secret = analyticsSecret($pdo);
            $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $visitorHash = hash_hmac('sha256', $remoteAddress . '|' . $userAgent, $secret);
            $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
            $requestPath = mb_substr($requestPath !== '' ? $requestPath : '/', 0, 255);
            $visitedAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Dhaka')))->format('Y-m-d H:i:s');
            $insert = $pdo->prepare('INSERT INTO visitor_analytics (visitor_hash,page_path,device_type,browser,operating_system,referrer_domain,visited_at) VALUES (:visitor_hash,:page_path,:device_type,:browser,:operating_system,:referrer_domain,:visited_at)');
            $insert->execute([
                ':visitor_hash' => $visitorHash,
                ':page_path' => $requestPath,
                ':device_type' => analyticsDeviceType($userAgent),
                ':browser' => analyticsBrowser($userAgent),
                ':operating_system' => analyticsOperatingSystem($userAgent),
                ':referrer_domain' => analyticsReferrerDomain((string) ($_SERVER['HTTP_REFERER'] ?? '')),
                ':visited_at' => $visitedAt,
            ]);
        }
        $result['total_visitors'] = (int) $pdo->query('SELECT COUNT(DISTINCT visitor_hash) FROM visitor_analytics')->fetchColumn();
        $result['total_page_views'] = (int) $pdo->query('SELECT COUNT(*) FROM visitor_analytics')->fetchColumn();
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
    }
    return $result;
}

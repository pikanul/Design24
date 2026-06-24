<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';

const ADMIN_IDLE_TIMEOUT_SECONDS = 1800;
const ADMIN_ABSOLUTE_TIMEOUT_SECONDS = 28800;
const ADMIN_LOGIN_MAX_FAILURES = 5;
const ADMIN_LOGIN_LOCKOUT_SECONDS = 900;

/**
 * Detect HTTPS both on a normal cPanel installation and when TLS is terminated
 * by a reverse proxy. Forwarded headers are only trusted when explicitly enabled.
 */
function adminRequestIsHttps(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ((in_array($https, ['on', '1', 'true'], true))
        || (isset($_SERVER['REQUEST_SCHEME']) && strtolower((string) $_SERVER['REQUEST_SCHEME']) === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)) {
        return true;
    }

    $trustProxyHeaders = filter_var(getenv('DESIGN24_TRUST_PROXY_HEADERS') ?: false, FILTER_VALIDATE_BOOLEAN);
    if (!$trustProxyHeaders) {
        return false;
    }

    $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    if ($forwardedProto === 'https') {
        return true;
    }

    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on'
        || strtolower((string) ($_SERVER['HTTP_X_URL_SCHEME'] ?? '')) === 'https') {
        return true;
    }

    return stripos((string) ($_SERVER['HTTP_FORWARDED'] ?? ''), 'proto=https') !== false;
}

function adminTrustedProxyHeaders(): bool
{
    return filter_var(getenv('DESIGN24_TRUST_PROXY_HEADERS') ?: false, FILTER_VALIDATE_BOOLEAN);
}

function adminClientIp(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

    if (adminTrustedProxyHeaders() && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($forwarded, FILTER_VALIDATE_IP) !== false) {
            $ip = $forwarded;
        }
    }

    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
}

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('design24_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => adminRequestIsHttps(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

startAdminSession();

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfIsValid(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function destroyAdminSession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parameters = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $parameters['path'] ?? '/',
            'domain' => $parameters['domain'] ?? '',
            'secure' => (bool) ($parameters['secure'] ?? adminRequestIsHttps()),
            'httponly' => (bool) ($parameters['httponly'] ?? true),
            'samesite' => 'Strict',
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function adminSessionHasExpired(): bool
{
    if (!isset($_SESSION['admin_id']) || !is_int($_SESSION['admin_id'])) {
        return false;
    }

    $now = time();
    $startedAt = $_SESSION['admin_session_started_at'] ?? null;
    $lastActivityAt = $_SESSION['admin_last_activity_at'] ?? null;

    if (!is_int($startedAt) || !is_int($lastActivityAt)
        || $now - $startedAt >= ADMIN_ABSOLUTE_TIMEOUT_SECONDS
        || $now - $lastActivityAt >= ADMIN_IDLE_TIMEOUT_SECONDS) {
        destroyAdminSession();
        return true;
    }

    $_SESSION['admin_last_activity_at'] = $now;
    return false;
}

function adminIsLoggedIn(): bool
{
    return isset($_SESSION['admin_id'])
        && is_int($_SESSION['admin_id'])
        && !adminSessionHasExpired();
}

function requireAdmin(): void
{
    if (!adminIsLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function currentAdminName(): string
{
    return isset($_SESSION['admin_name']) && is_string($_SESSION['admin_name'])
        ? $_SESSION['admin_name']
        : 'Administrator';
}

function adminLoginLockoutRemaining(PDO $pdo, string $ipAddress, string $email): int
{
    $windowStart = date('Y-m-d H:i:s', time() - ADMIN_LOGIN_LOCKOUT_SECONDS);
    $statement = $pdo->prepare(
        "SELECT occurred_at FROM admin_login_attempts
         WHERE outcome = 'failed' AND occurred_at >= :window_start
           AND (ip_address = :ip_address OR email = :email)"
    );
    $statement->execute([
        ':window_start' => $windowStart,
        ':ip_address' => $ipAddress,
        ':email' => $email,
    ]);
    $attempts = $statement->fetchAll(PDO::FETCH_COLUMN);

    if (count($attempts) < ADMIN_LOGIN_MAX_FAILURES) {
        return 0;
    }

    $lastFailure = max(array_map(static function ($occurredAt): int {
        return strtotime((string) $occurredAt) ?: 0;
    }, $attempts));

    return max(1, ($lastFailure + ADMIN_LOGIN_LOCKOUT_SECONDS) - time());
}

/** Records email, IP, timestamp and result only. Passwords are never logged. */
function logAdminLoginAttempt(PDO $pdo, string $email, string $ipAddress, string $outcome): void
{
    $statement = $pdo->prepare(
        'INSERT INTO admin_login_attempts (email, ip_address, outcome, occurred_at)
         VALUES (:email, :ip_address, :outcome, CURRENT_TIMESTAMP)'
    );
    $statement->execute([
        ':email' => mb_substr($email, 0, 190),
        ':ip_address' => mb_substr($ipAddress, 0, 45),
        ':outcome' => $outcome,
    ]);
}

function clearSuccessfulAdminLoginFailures(PDO $pdo, string $email, string $ipAddress): void
{
    $statement = $pdo->prepare(
        "DELETE FROM admin_login_attempts
         WHERE outcome = 'failed' AND email = :email AND ip_address = :ip_address"
    );
    $statement->execute([':email' => $email, ':ip_address' => $ipAddress]);
}

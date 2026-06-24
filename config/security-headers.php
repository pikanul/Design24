<?php

declare(strict_types=1);

function design24RequestIsHttps(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if (in_array($https, ['on', '1', 'true'], true)
        || strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https'
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }

    $trustProxyHeaders = filter_var(getenv('DESIGN24_TRUST_PROXY_HEADERS') ?: false, FILTER_VALIDATE_BOOLEAN);
    if (!$trustProxyHeaders) {
        return false;
    }

    $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    return $forwardedProto === 'https'
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on'
        || strtolower((string) ($_SERVER['HTTP_X_URL_SCHEME'] ?? '')) === 'https'
        || stripos((string) ($_SERVER['HTTP_FORWARDED'] ?? ''), 'proto=https') !== false;
}

function sendDesign24SecurityHeaders(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    $https = design24RequestIsHttps();
    $csp = "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; "
        . "script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com; "
        . "style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; "
        . "media-src 'self' blob: https:; connect-src 'self' https://challenges.cloudflare.com; "
        . "frame-src 'self' https:; worker-src 'self' blob:";
    if ($https) {
        $csp .= '; upgrade-insecure-requests';
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(), usb=()');
    header('Content-Security-Policy: ' . $csp);
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

sendDesign24SecurityHeaders();

<?php

declare(strict_types=1);

/* Security router for PHP's local development server. */
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';

foreach (['/database', '/config', '/backups', '/vendor'] as $privatePath) {
    if ($requestPath === $privatePath || strpos($requestPath, $privatePath . '/') === 0) {
        http_response_code(404);
        exit('Not found.');
    }
}

$requestBasename = basename($requestPath);
if (preg_match('/(^\.env$|^composer\.(json|lock)$|^php\.ini$|\.(sqlite|sql|tar\.gz)$)/i', $requestBasename)) {
    http_response_code(404);
    exit('Not found.');
}

if ($requestPath === '/uploads/consultations' || strpos($requestPath, '/uploads/consultations/') === 0) {
    http_response_code(404);
    exit('Not found.');
}

if ($requestPath === '/testimonials' || $requestPath === '/client-feedback') {
    require __DIR__ . '/testimonials.php';
    return true;
}

if ($requestPath === '/portfolio' || strpos($requestPath, '/portfolio/') === 0) {
    require __DIR__ . '/portfolio.php';
    return true;
}

if ($requestPath === '/consultation-booking') {
    require __DIR__ . '/consultation-booking.php';
    return true;
}

if ($requestPath === '/give-feedback') {
    require __DIR__ . '/give-feedback.php';
    return true;
}

if ($requestPath === '/videos') {
    require __DIR__ . '/videos.php';
    return true;
}

if ($requestPath === '/office' || $requestPath === '/office-factory') {
    require __DIR__ . '/office.php';
    return true;
}

return false;

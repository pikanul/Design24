<?php

declare(strict_types=1);

/* Security router for PHP's local development server. */
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';

foreach (['/database', '/config', '/backups'] as $privatePath) {
    if ($requestPath === $privatePath || strpos($requestPath, $privatePath . '/') === 0) {
        http_response_code(404);
        exit('Not found.');
    }
}

if ($requestPath === '/testimonials' || $requestPath === '/client-feedback') {
    require __DIR__ . '/testimonials.php';
    return true;
}

if ($requestPath === '/portfolio' || strpos($requestPath, '/portfolio/') === 0) {
    require __DIR__ . '/portfolio.php';
    return true;
}

return false;

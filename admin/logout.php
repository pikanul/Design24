<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfIsValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
    http_response_code(405);
    exit('Invalid request.');
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $parameters = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $parameters['path'],
        'domain' => $parameters['domain'],
        'secure' => (bool) $parameters['secure'],
        'httponly' => (bool) $parameters['httponly'],
        'samesite' => 'Strict',
    ]);
}

session_destroy();
header('Location: login.php');
exit;

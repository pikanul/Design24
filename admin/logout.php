<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfIsValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
    http_response_code(405);
    exit('Invalid request.');
}

destroyAdminSession();
header('Location: login.php');
exit;

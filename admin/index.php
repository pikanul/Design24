<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

header('Location: ' . (adminIsLoggedIn() ? 'dashboard.php' : 'login.php'));
exit;

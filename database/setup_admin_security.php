<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/database.php';

try {
    $pdo = db();
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'mysql') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS admin_login_attempts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(190) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                outcome VARCHAR(20) NOT NULL,
                occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_admin_login_attempts_email_time (email, occurred_at),
                INDEX idx_admin_login_attempts_ip_time (ip_address, occurred_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS admin_login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email VARCHAR(190) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                outcome VARCHAR(20) NOT NULL,
                occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_admin_login_attempts_email_time ON admin_login_attempts (email, occurred_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_admin_login_attempts_ip_time ON admin_login_attempts (ip_address, occurred_at)');
    }

    fwrite(STDOUT, "Admin login security table is ready.\n");
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    fwrite(STDERR, "Could not create the admin login security table.\n");
    exit(1);
}

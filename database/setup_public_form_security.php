<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/config/database.php';

try {
    $pdo = db();
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS public_form_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, form_name VARCHAR(80) NOT NULL, ip_address VARCHAR(45) NOT NULL, session_hash CHAR(64) NOT NULL, event_type VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_public_form_events_ip_time (form_name, ip_address, created_at), INDEX idx_public_form_events_session_time (form_name, session_hash, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    } else {
        $pdo->exec('CREATE TABLE IF NOT EXISTS public_form_events (id INTEGER PRIMARY KEY AUTOINCREMENT, form_name VARCHAR(80) NOT NULL, ip_address VARCHAR(45) NOT NULL, session_hash CHAR(64) NOT NULL, event_type VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_public_form_events_ip_time ON public_form_events (form_name, ip_address, created_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_public_form_events_session_time ON public_form_events (form_name, session_hash, created_at)');
    }
    fwrite(STDOUT, "Public form security table is ready.\n");
} catch (Throwable $exception) { error_log($exception->getMessage()); fwrite(STDERR, "Could not create the public form security table.\n"); exit(1); }

<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/database.php';
$schema = file_get_contents(__DIR__ . '/schema.sql');
if (!is_string($schema)) throw new RuntimeException('The database schema could not be read.');
db()->exec($schema);
echo "Visitor analytics table is ready.\n";

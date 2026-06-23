<?php

declare(strict_types=1);

/*
 * Central PDO connection.
 *
 * Development uses SQLite by default. To move to MySQL later, set the
 * DB_DRIVER, DB_HOST, DB_PORT, DB_NAME, DB_USER, and DB_PASSWORD environment
 * variables. The admin pages only call db(), so they will not need rebuilding.
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');

/* Compatibility helpers for local servers still running PHP 7.4. */
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        $needleLength = strlen($needle);
        return $needleLength <= strlen($haystack)
            && substr($haystack, -$needleLength) === $needle;
    }
}

function db(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $driver = getenv('DB_DRIVER') ?: 'sqlite';

    if ($driver === 'sqlite') {
        $databasePath = dirname(__DIR__) . '/database/design24.sqlite';
        $dsn = 'sqlite:' . $databasePath;
        $username = null;
        $password = null;
    } elseif ($driver === 'mysql') {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: '';
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $username = getenv('DB_USER') ?: '';
        $password = getenv('DB_PASSWORD') ?: '';
    } else {
        throw new RuntimeException('Unsupported database driver.');
    }

    $connection = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    if ($driver === 'sqlite') {
        $connection->exec('PRAGMA foreign_keys = ON');
    }

    return $connection;
}

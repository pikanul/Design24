<?php

declare(strict_types=1);

require_once __DIR__ . '/environment.php';
require_once __DIR__ . '/security-headers.php';

/*
 * Central PDO connection.
 *
 * Development may use the bundled SQLite database when APP_ENV is not
 * production. Production must be explicit:
 *
 *   MySQL:  DB_DRIVER=mysql, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD
 *   SQLite: DB_DRIVER=sqlite, DATABASE_PATH=/absolute/private/path/design24.sqlite
 *
 * Do not put production passwords or private database paths in source code.
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$runtimeEnvironment = strtolower(trim((string) (getenv('APP_ENV') ?: 'development')));
if (in_array($runtimeEnvironment, ['production', 'prod'], true)) {
    $errorLog = trim((string) getenv('PHP_ERROR_LOG'));
    $websiteRoot = dirname(__DIR__);
    if ($errorLog === '' || $errorLog[0] !== DIRECTORY_SEPARATOR
        || strncmp($errorLog, rtrim($websiteRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, strlen(rtrim($websiteRoot, DIRECTORY_SEPARATOR)) + 1) === 0) {
        throw new RuntimeException('Production PHP error logging must use an absolute path outside the website directory.');
    }
    $logDirectory = dirname($errorLog);
    if (!is_dir($logDirectory) || !is_writable($logDirectory)) {
        throw new RuntimeException('Production PHP error log directory is unavailable.');
    }
    ini_set('error_log', $errorLog);
}

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

    $environment = strtolower(trim((string) (getenv('APP_ENV') ?: 'development')));
    $isProduction = in_array($environment, ['production', 'prod'], true);
    $driver = strtolower(trim((string) (getenv('DB_DRIVER') ?: '')));

    if ($isProduction && $driver === '') {
        throw new RuntimeException('Production database driver is not configured.');
    }

    if ($driver === '') {
        $driver = 'sqlite';
    }

    if ($driver === 'sqlite') {
        $databasePath = databaseSqlitePath($isProduction);
        $dsn = 'sqlite:' . $databasePath;
        $username = null;
        $password = null;
    } elseif ($driver === 'mysql') {
        $host = requiredDatabaseEnv('DB_HOST');
        $port = requiredDatabaseEnv('DB_PORT');
        $name = requiredDatabaseEnv('DB_NAME');
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $username = requiredDatabaseEnv('DB_USER');
        $password = requiredDatabaseEnv('DB_PASSWORD', false);
        if ($isProduction && $password === '') {
            throw new RuntimeException('Required environment variable DB_PASSWORD is missing.');
        }
    } else {
        throw new RuntimeException('Unsupported database driver.');
    }

    try {
        $connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        if ($driver === 'sqlite') {
            $connection->exec('PRAGMA foreign_keys = ON');
        }
    } catch (PDOException $exception) {
        error_log('Database connection failed: ' . $exception->getMessage());
        throw new RuntimeException('Database connection failed. Please contact the site administrator.');
    }

    return $connection;
}

function requiredDatabaseEnv(string $name, bool $trim = true): string
{
    $value = getenv($name);
    $value = is_string($value) ? ($trim ? trim($value) : $value) : '';

    if ($value === '') {
        throw new RuntimeException("Required database environment variable {$name} is missing.");
    }

    return $value;
}

function databaseSqlitePath(bool $isProduction): string
{
    $configuredPath = getenv('DATABASE_PATH');
    $configuredPath = is_string($configuredPath) ? trim($configuredPath) : '';

    if ($configuredPath === '') {
        if ($isProduction) {
            throw new RuntimeException('Production SQLite database path is not configured.');
        }

        $configuredPath = dirname(__DIR__) . '/database/design24.sqlite';
    }

    if (!str_starts_with($configuredPath, DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('SQLite DATABASE_PATH must be an absolute private filesystem path.');
    }

    $databaseDirectory = dirname($configuredPath);
    if (!is_dir($databaseDirectory)) {
        throw new RuntimeException('SQLite database directory does not exist.');
    }

    if ($isProduction && pathIsInside($configuredPath, dirname(__DIR__))) {
        throw new RuntimeException('Production SQLite database must be outside the public website folder.');
    }

    return $configuredPath;
}

function pathIsInside(string $path, string $directory): bool
{
    $resolvedDirectory = realpath($directory);
    $resolvedPath = realpath($path);

    if ($resolvedDirectory === false) {
        return false;
    }

    if ($resolvedPath === false) {
        $parent = realpath(dirname($path));
        if ($parent === false) {
            return false;
        }
        $resolvedPath = rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($path);
    }

    $resolvedDirectory = rtrim($resolvedDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return str_starts_with($resolvedPath, $resolvedDirectory);
}

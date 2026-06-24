<?php

declare(strict_types=1);

/**
 * Loads deployment variables from a private file outside the website directory.
 * Hosting-provided environment variables always take precedence.
 */
function design24LoadPrivateEnvironment(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $configuredPath = trim((string) getenv('DESIGN24_ENV_FILE'));
    $defaultPath = dirname(dirname(__DIR__)) . '/design24-private/.env';
    $path = $configuredPath !== '' ? $configuredPath : $defaultPath;
    $projectRoot = dirname(__DIR__);
    if ($path === '' || $path[0] !== DIRECTORY_SEPARATOR) {
        error_log('Design24 private environment file must use an absolute path.');
        return;
    }
    if (strncmp($path, rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, strlen(rtrim($projectRoot, DIRECTORY_SEPARATOR)) + 1) === 0) {
        error_log('Design24 private environment file must be outside the website directory.');
        return;
    }
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        error_log('Design24 private environment file could not be read.');
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $name) || getenv($name) !== false) {
            continue;
        }
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

design24LoadPrivateEnvironment();

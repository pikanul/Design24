<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/database.php';

function prompt(string $label): string
{
    $value = readline($label);
    return trim($value === false ? '' : $value);
}

function promptPassword(string $label): string
{
    fwrite(STDOUT, $label);

    if (DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec')) {
        shell_exec('stty -echo');
        $value = fgets(STDIN);
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
        return trim($value === false ? '' : $value);
    }

    $value = fgets(STDIN);
    return trim($value === false ? '' : $value);
}

try {
    $pdo = db();
    $schema = file_get_contents(__DIR__ . '/schema.sql');

    if ($schema === false) {
        throw new RuntimeException('Could not read the database schema.');
    }

    $pdo->exec($schema);

    $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($adminCount > 0) {
        fwrite(STDERR, "Setup is disabled because an administrator already exists.\n");
        exit(1);
    }

    $name = prompt('Administrator name: ');
    $email = strtolower(prompt('Administrator email: '));
    $password = promptPassword('Password (minimum 12 characters): ');
    $confirmation = promptPassword('Confirm password: ');

    $errors = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Name must be between 2 and 100 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 12) {
        $errors[] = 'Password must contain at least 12 characters.';
    }
    if ($password !== $confirmation) {
        $errors[] = 'Password confirmation does not match.';
    }

    if ($errors !== []) {
        foreach ($errors as $error) {
            fwrite(STDERR, '- ' . $error . PHP_EOL);
        }
        exit(1);
    }

    $statement = $pdo->prepare(
        'INSERT INTO admins (name, email, password, status, created_at, updated_at)
         VALUES (:name, :email, :password, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
    );
    $statement->execute([
        ':name' => $name,
        ':email' => $email,
        ':password' => password_hash($password, PASSWORD_DEFAULT),
        ':status' => 'active',
    ]);

    fwrite(STDOUT, "Administrator created successfully. This setup command is now disabled.\n");
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    fwrite(STDERR, "Setup failed. Check the PHP error log for details.\n");
    exit(1);
}

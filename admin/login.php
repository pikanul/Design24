<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (adminIsLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $csrf = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null;
    $ipAddress = adminClientIp();

    if (!csrfIsValid($csrf)) {
        $error = 'Unable to sign in. Please check your details and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $error = 'Unable to sign in. Please check your details and try again.';
    } elseif ($password === '' || strlen($password) > 4096) {
        $error = 'Unable to sign in. Please check your details and try again.';
    } else {
        try {
            $pdo = db();
            if (adminLoginLockoutRemaining($pdo, $ipAddress, $email) > 0) {
                $error = 'Too many sign-in attempts. Please try again later.';
            } else {
                $statement = $pdo->prepare(
                    'SELECT id, name, email, password, status FROM admins WHERE email = :email LIMIT 1'
                );
                $statement->execute([':email' => $email]);
                $admin = $statement->fetch();

                if (is_array($admin)
                    && $admin['status'] === 'active'
                    && password_verify($password, (string) $admin['password'])) {
                    session_regenerate_id(true);
                    $_SESSION = [
                        'admin_id' => (int) $admin['id'],
                        'admin_name' => (string) $admin['name'],
                        'admin_email' => (string) $admin['email'],
                        'admin_session_started_at' => time(),
                        'admin_last_activity_at' => time(),
                        'csrf_token' => bin2hex(random_bytes(32)),
                    ];

                    $update = $pdo->prepare(
                        'UPDATE admins SET last_login_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
                    );
                    $update->execute([':id' => (int) $admin['id']]);
                    clearSuccessfulAdminLoginFailures($pdo, $email, $ipAddress);
                    logAdminLoginAttempt($pdo, $email, $ipAddress, 'success');

                    header('Location: dashboard.php');
                    exit;
                }

                logAdminLoginAttempt($pdo, $email, $ipAddress, 'failed');
                $error = 'Unable to sign in. Please check your details and try again.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $error = 'Login is temporarily unavailable. Please try again.';
        }
    }
}

$pageTitle = 'Admin Login';
require __DIR__ . '/includes/header.php';
?>
<main class="login-shell">
    <section class="login-card" aria-labelledby="login-title">
        <h1 id="login-title">Design24 Admin</h1>
        <p>Sign in to manage the website.</p>

        <?php if ($error !== ''): ?>
            <p class="error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" action="login.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <div class="field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" maxlength="190" value="<?= e($email) ?>" autocomplete="username" required>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" maxlength="4096" autocomplete="current-password" required>
            </div>

            <button class="primary-button" type="submit">Log In</button>
        </form>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>

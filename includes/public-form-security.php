<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

const PUBLIC_FORM_IP_LIMIT = 6;
const PUBLIC_FORM_SESSION_LIMIT = 3;
const PUBLIC_FORM_RATE_WINDOW = 3600;
const PUBLIC_FORM_MAX_IMAGE_BYTES = 5242880;

/**
 * Use the same HTTPS decision as the central header and admin-session code.
 * Forwarded headers are only considered when DESIGN24_TRUST_PROXY_HEADERS is
 * explicitly enabled for a trusted proxy.
 */
function publicFormRequestIsHttps(): bool
{
    return design24RequestIsHttps();
}

/** Configure the anonymous public-form session before any session is started. */
function publicFormConfigureSessionCookies(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => publicFormRequestIsHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// consultationCsrf() can start a session before publicFormStartSession(), so
// set these defaults as soon as this helper is loaded.
publicFormConfigureSessionCookies();

function publicFormStartSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        publicFormConfigureSessionCookies();
        session_start();
    }
}

function publicFormClientIp(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $trustProxy = filter_var(getenv('DESIGN24_TRUST_PROXY_HEADERS') ?: false, FILTER_VALIDATE_BOOLEAN);
    if ($trustProxy && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($forwarded, FILTER_VALIDATE_IP) !== false) {
            $ip = $forwarded;
        }
    }

    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
}

function publicFormSessionHash(): string
{
    publicFormStartSession();
    if (!isset($_SESSION['public_form_client_id'])) {
        $_SESSION['public_form_client_id'] = bin2hex(random_bytes(32));
    }

    return hash('sha256', (string) $_SESSION['public_form_client_id']);
}

/** Logs only form metadata; never form contents, passwords, or uploaded filenames. */
function publicFormLog(PDO $pdo, string $form, string $event): void
{
    try {
        $statement = $pdo->prepare(
            'INSERT INTO public_form_events (form_name, ip_address, session_hash, event_type, created_at)
             VALUES (:form_name, :ip_address, :session_hash, :event_type, CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            ':form_name' => mb_substr($form, 0, 80),
            ':ip_address' => publicFormClientIp(),
            ':session_hash' => publicFormSessionHash(),
            ':event_type' => mb_substr($event, 0, 40),
        ]);
    } catch (Throwable $exception) {
        error_log('Public form audit log: ' . $exception->getMessage());
    }
}

function publicFormRecentEventCount(PDO $pdo, string $form, string $column, string $value): int
{
    if (!in_array($column, ['ip_address', 'session_hash'], true)) {
        throw new InvalidArgumentException('Invalid public form rate limit column.');
    }

    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM public_form_events
         WHERE form_name = :form_name AND {$column} = :value AND event_type = 'attempt'
           AND created_at >= :window_start"
    );
    $statement->execute([
        ':form_name' => $form,
        ':value' => $value,
        ':window_start' => date('Y-m-d H:i:s', time() - PUBLIC_FORM_RATE_WINDOW),
    ]);
    return (int) $statement->fetchColumn();
}

function publicFormSessionLimitReached(string $form): bool
{
    publicFormStartSession();
    $now = time();
    $attempts = $_SESSION['public_form_attempts'][$form] ?? [];
    $attempts = is_array($attempts) ? array_values(array_filter($attempts, static function ($timestamp) use ($now): bool {
        return is_int($timestamp) && $timestamp > $now - PUBLIC_FORM_RATE_WINDOW;
    })) : [];
    $_SESSION['public_form_attempts'][$form] = $attempts;

    return count($attempts) >= PUBLIC_FORM_SESSION_LIMIT;
}

function publicFormRecordSessionAttempt(string $form): void
{
    publicFormStartSession();
    $_SESSION['public_form_attempts'][$form][] = time();
}

function publicFormCaptchaEnabled(): bool
{
    return trim((string) getenv('TURNSTILE_SECRET_KEY')) !== ''
        && trim((string) getenv('TURNSTILE_SITE_KEY')) !== '';
}

function publicFormCaptchaSiteKey(): string
{
    return trim((string) getenv('TURNSTILE_SITE_KEY'));
}

function publicFormCaptchaValid(): bool
{
    $secret = trim((string) getenv('TURNSTILE_SECRET_KEY'));
    if (!publicFormCaptchaEnabled() || $secret === '') {
        return true;
    }

    $token = trim((string) ($_POST['cf-turnstile-response'] ?? ''));
    if ($token === '') {
        return false;
    }

    $payload = http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => publicFormClientIp()]);
    $response = false;
    if (function_exists('curl_init')) {
        $curl = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
        $response = curl_exec($curl);
        curl_close($curl);
    }
    if ($response === false && filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, stream_context_create([
            'http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $payload, 'timeout' => 5],
        ]));
    }

    $result = is_string($response) ? json_decode($response, true) : null;
    return is_array($result) && !empty($result['success']);
}

/** Returns a friendly error string, or an empty string when this submission may continue. */
function publicFormAbuseError(PDO $pdo, string $form, ?string $honeypot): string
{
    publicFormStartSession();
    if (trim((string) $honeypot) !== '') {
        publicFormLog($pdo, $form, 'honeypot');
        return 'We could not submit this form. Please try again.';
    }
    if (!publicFormCaptchaValid()) {
        publicFormLog($pdo, $form, 'captcha_failed');
        return 'Please complete the verification and try again.';
    }
    if (publicFormSessionLimitReached($form)) {
        publicFormLog($pdo, $form, 'session_rate_limited');
        return 'Too many requests from this browser. Please try again in an hour.';
    }

    try {
        if (publicFormRecentEventCount($pdo, $form, 'ip_address', publicFormClientIp()) >= PUBLIC_FORM_IP_LIMIT) {
            publicFormLog($pdo, $form, 'ip_rate_limited');
            return 'Too many requests from this connection. Please try again in an hour.';
        }
    } catch (Throwable $exception) {
        error_log('Public form rate limit: ' . $exception->getMessage());
    }

    publicFormRecordSessionAttempt($form);
    publicFormLog($pdo, $form, 'attempt');
    return '';
}

function publicFormEmailIsSafe(string $email): bool
{
    return !preg_match('/[\r\n]/', $email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** @return array{files: array<int, array{tmp_name:string,original_name:string,mime_type:string,extension:string,size:int}>, errors: string[]} */
function publicFormValidateImageUploads($upload, int $maxFiles, int $maxTotalBytes): array
{
    if (!is_array($upload) || !isset($upload['name'])) {
        return ['files' => [], 'errors' => []];
    }

    $names = is_array($upload['name']) ? $upload['name'] : [$upload['name']];
    $errors = is_array($upload['error'] ?? null) ? $upload['error'] : [$upload['error'] ?? UPLOAD_ERR_NO_FILE];
    $sizes = is_array($upload['size'] ?? null) ? $upload['size'] : [$upload['size'] ?? 0];
    $temporaryFiles = is_array($upload['tmp_name'] ?? null) ? $upload['tmp_name'] : [$upload['tmp_name'] ?? ''];

    if (count($names) !== count($errors) || count($names) !== count($sizes) || count($names) !== count($temporaryFiles)) {
        return ['files' => [], 'errors' => ['The upload data was invalid. Please choose the images again.']];
    }

    $files = [];
    $validationErrors = [];
    $totalBytes = 0;
    $mimeMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    foreach ($names as $index => $name) {
        $error = (int) $errors[$index];
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (count($files) >= $maxFiles) {
            $validationErrors[] = "You can upload a maximum of {$maxFiles} images.";
            break;
        }
        $size = (int) $sizes[$index];
        $temporaryFile = (string) $temporaryFiles[$index];
        if ($error !== UPLOAD_ERR_OK || $size < 1 || $size > PUBLIC_FORM_MAX_IMAGE_BYTES || !is_uploaded_file($temporaryFile)) {
            $validationErrors[] = 'Each image must be a verified JPG, PNG, or WebP file of 5 MB or smaller.';
            continue;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryFile);
        if (!is_string($mime) || !isset($mimeMap[$mime]) || @getimagesize($temporaryFile) === false) {
            $validationErrors[] = 'Upload valid JPG, PNG, or WebP images only.';
            continue;
        }
        $totalBytes += $size;
        $files[] = ['tmp_name' => $temporaryFile, 'original_name' => basename((string) $name), 'mime_type' => $mime, 'extension' => $mimeMap[$mime], 'size' => $size];
    }

    if ($totalBytes > $maxTotalBytes) {
        $validationErrors[] = 'The combined upload size is too large. Please choose fewer or smaller images.';
    }

    return ['files' => $files, 'errors' => array_values(array_unique($validationErrors))];
}

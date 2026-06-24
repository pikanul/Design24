<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once dirname(__DIR__) . '/includes/consultation.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) { http_response_code(404); exit('Not found.'); }

$statement = db()->prepare('SELECT stored_name, original_name, mime_type FROM consultation_attachments WHERE id = :id LIMIT 1');
$statement->execute([':id' => $id]);
$attachment = $statement->fetch();
$path = is_array($attachment) ? consultationAttachmentPath((string) $attachment['stored_name']) : '';
$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if ($path === '' || !in_array((string) $attachment['mime_type'], $allowed, true)) { http_response_code(404); exit('Not found.'); }

header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: attachment; filename="consultation-attachment.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($path);
exit;

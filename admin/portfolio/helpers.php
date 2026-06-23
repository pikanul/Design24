<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/site-portfolio-data.php';

function portfolioAdminSlug(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'portfolio-item';
}

function portfolioAdminUniqueSlug(PDO $pdo, string $table, string $slug, int $ignoreId = 0): string
{
    $base = portfolioAdminSlug($slug);
    $candidate = $base;
    $counter = 2;
    $sql = "SELECT id FROM {$table} WHERE slug = :slug" . ($ignoreId > 0 ? ' AND id != :id' : '') . ' LIMIT 1';
    $stmt = $pdo->prepare($sql);

    while (true) {
        $params = [':slug' => $candidate];
        if ($ignoreId > 0) {
            $params[':id'] = $ignoreId;
        }
        $stmt->execute($params);
        if (!$stmt->fetchColumn()) {
            return $candidate;
        }
        $candidate = $base . '-' . $counter;
        $counter++;
    }
}

function portfolioAdminUpload(string $field, string $folder = 'public/uploads/portfolio'): array
{
    $upload = $_FILES[$field] ?? null;
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['present' => false, 'path' => '', 'error' => ''];
    }

    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($upload['size'] ?? 0) > 5 * 1024 * 1024 || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
        return ['present' => true, 'path' => '', 'error' => 'Image must be a verified JPG, PNG, or WebP file of 5 MB or smaller.'];
    }

    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']);
    if (!is_string($mime) || !isset($map[$mime]) || @getimagesize((string) $upload['tmp_name']) === false) {
        return ['present' => true, 'path' => '', 'error' => 'Upload a valid JPG, JPEG, PNG, or WebP image.'];
    }

    $folder = trim($folder, '/');
    $absoluteFolder = dirname(__DIR__, 2) . '/' . $folder;
    if (!is_dir($absoluteFolder)) {
        mkdir($absoluteFolder, 0755, true);
    }

    $path = $folder . '/' . bin2hex(random_bytes(16)) . '.' . $map[$mime];
    if (!move_uploaded_file((string) $upload['tmp_name'], dirname(__DIR__, 2) . '/' . $path)) {
        return ['present' => true, 'path' => '', 'error' => 'The portfolio image could not be saved.'];
    }

    return ['present' => true, 'path' => $path, 'error' => ''];
}

function portfolioAdminVideoUpload(string $field, string $folder = 'public/uploads/portfolio/category-hero/videos'): array
{
    $upload = $_FILES[$field] ?? null;
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['present' => false, 'path' => '', 'error' => ''];
    }

    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($upload['size'] ?? 0) > 50 * 1024 * 1024 || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
        return ['present' => true, 'path' => '', 'error' => 'Video must be a verified MP4 or WebM file of 50 MB or smaller.'];
    }

    $map = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']);
    if (!is_string($mime) || !isset($map[$mime])) {
        return ['present' => true, 'path' => '', 'error' => 'Upload a valid MP4 or WebM video.'];
    }

    $folder = trim($folder, '/');
    $absoluteFolder = dirname(__DIR__, 2) . '/' . $folder;
    if (!is_dir($absoluteFolder)) mkdir($absoluteFolder, 0755, true);
    $path = $folder . '/' . bin2hex(random_bytes(16)) . '.' . $map[$mime];
    if (!move_uploaded_file((string) $upload['tmp_name'], dirname(__DIR__, 2) . '/' . $path)) {
        return ['present' => true, 'path' => '', 'error' => 'The category hero video could not be saved.'];
    }

    return ['present' => true, 'path' => $path, 'error' => ''];
}

function portfolioAdminDeleteFile(string $path): void
{
    if (!portfolioAssetIsSafe($path) && !portfolioVideoAssetIsSafe($path)) {
        return;
    }

    $absolute = dirname(__DIR__, 2) . '/' . $path;
    if (is_file($absolute)) {
        unlink($absolute);
    }
}

function portfolioAdminCategories(PDO $pdo): array
{
    return $pdo->query('SELECT c.*, parent.name AS parent_name, (SELECT COUNT(*) FROM portfolio_projects p WHERE p.category_id = c.id) AS project_count FROM portfolio_categories c LEFT JOIN portfolio_categories parent ON parent.id = c.parent_id ORDER BY COALESCE(c.parent_id, 0), c.menu_order ASC, c.name ASC')->fetchAll();
}

function portfolioAdminCategoryOptions(array $categories, ?int $skipId = null): string
{
    $html = '<option value="">Main category</option>';
    foreach ($categories as $category) {
        if ($skipId !== null && (int) $category['id'] === $skipId) {
            continue;
        }
        $label = ($category['parent_id'] ? '— ' : '') . $category['name'];
        $html .= '<option value="' . e((string) $category['id']) . '">' . e($label) . '</option>';
    }
    return $html;
}

function portfolioAdminYoutubeIsValid(string $url): bool
{
    return $url === '' || youtubeVideoIdFromUrl($url) !== '';
}

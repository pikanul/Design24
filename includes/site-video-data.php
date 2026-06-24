<?php

declare(strict_types=1);

require_once __DIR__ . '/site-settings.php';

function videoGalleryEnsureTables(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS site_videos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title VARCHAR(220) NOT NULL,
        description TEXT NULL,
        video_type VARCHAR(20) NOT NULL DEFAULT 'url',
        video_url VARCHAR(500) NULL,
        video_file VARCHAR(500) NULL,
        thumbnail VARCHAR(500) NULL,
        display_order INTEGER NOT NULL DEFAULT 0,
        status INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    db()->exec('CREATE INDEX IF NOT EXISTS idx_site_videos_display ON site_videos (status, display_order, id)');
}

function videoGalleryYoutubeId(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';
    $patterns = [
        '#youtu\.be/([A-Za-z0-9_-]{6,})#',
        '#youtube\.com/watch\?[^#]*v=([A-Za-z0-9_-]{6,})#',
        '#youtube\.com/embed/([A-Za-z0-9_-]{6,})#',
        '#youtube\.com/shorts/([A-Za-z0-9_-]{6,})#',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) return $matches[1];
    }
    return '';
}

function videoGalleryEmbedUrl(string $url): string
{
    $id = videoGalleryYoutubeId($url);
    return $id !== '' ? 'https://www.youtube.com/embed/' . rawurlencode($id) : '';
}

function videoGalleryVideoIsSafe(string $path): bool
{
    return preg_match('#^public/uploads/videos/[a-f0-9]{32}\.(?:mp4|webm)$#', $path) === 1
        && is_file(dirname(__DIR__) . '/' . $path);
}

function videoGalleryImageIsSafe(string $path): bool
{
    return preg_match('#^public/uploads/videos/thumbs/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#', $path) === 1
        && is_file(dirname(__DIR__) . '/' . $path);
}

function getVideoGalleryItems(bool $onlyActive = true): array
{
    videoGalleryEnsureTables();
    $where = $onlyActive ? 'WHERE status = 1' : '';
    return db()->query("SELECT * FROM site_videos {$where} ORDER BY display_order ASC, id DESC")->fetchAll();
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/site-settings.php';

function officePageDefaults(): array
{
    return [
        'eyebrow' => 'OFFICE & FACTORY',
        'title' => 'Our Office & Factory',
        'subtitle' => 'A coordinated workplace and production setup where design ideas become finished spaces.',
        'description' => 'Visit our office for consultation, planning, and project coordination. Our factory team supports custom furniture, production details, and execution quality.',
        'hero_image' => '',
        'hero_video' => '',
        'show_gallery' => '1',
    ];
}

function getOfficePageSettings(): array
{
    return getSiteSettings('office_page', officePageDefaults());
}

function officeEnsureTables(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS office_media (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        media_type VARCHAR(10) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        title VARCHAR(220) NULL,
        description TEXT NULL,
        display_order INTEGER NOT NULL DEFAULT 0,
        status INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    db()->exec('CREATE INDEX IF NOT EXISTS idx_office_media_display ON office_media (status, display_order, id)');
}

function officeImageIsSafe(string $path): bool
{
    return preg_match('#^public/uploads/office/(?:hero/|gallery/)?[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#', $path) === 1
        && is_file(dirname(__DIR__) . '/' . $path);
}

function officeVideoIsSafe(string $path): bool
{
    return preg_match('#^public/uploads/office/(?:hero/|gallery/)?[a-f0-9]{32}\.(?:mp4|webm)$#', $path) === 1
        && is_file(dirname(__DIR__) . '/' . $path);
}

function getOfficeMedia(bool $onlyActive = true): array
{
    officeEnsureTables();
    $where = $onlyActive ? 'WHERE status = 1' : '';
    return db()->query("SELECT * FROM office_media {$where} ORDER BY display_order ASC, id DESC")->fetchAll();
}

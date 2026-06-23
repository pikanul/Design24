<?php

declare(strict_types=1);

require_once __DIR__ . '/site-settings.php';

function portfolioSettingDefaults(): array
{
    return [
        'portfolio_title' => 'Portfolio',
        'portfolio_subtitle' => 'Discover our signature residential and commercial projects.',
        'portfolio_banner_image' => '',
        'portfolio_overlay_opacity' => '45',
        'portfolio_featured_limit' => '6',
        'portfolio_show_featured_home' => '0',
        'portfolio_show_residential_home' => '1',
        'portfolio_show_commercial_home' => '1',
        'portfolio_show_videos_home' => '1',
    ];
}

function getPortfolioSettings(): array
{
    return getSiteSettings('portfolio_page', portfolioSettingDefaults());
}

function portfolioTablesReady(): bool
{
    try {
        return (bool) db()->query("SELECT name FROM sqlite_master WHERE type='table' AND name='portfolio_categories'")->fetchColumn();
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        return false;
    }
}

function getPortfolioCategories(bool $activeOnly = true): array
{
    if (!portfolioTablesReady()) {
        return [];
    }

    $where = $activeOnly ? 'WHERE is_active = 1' : '';
    return db()->query("SELECT * FROM portfolio_categories {$where} ORDER BY COALESCE(parent_id, 0), menu_order ASC, name ASC")->fetchAll();
}

function getPortfolioMenuTree(): array
{
    $categories = getPortfolioCategories(true);
    $parents = [];
    $children = [];

    foreach ($categories as $category) {
        if ($category['parent_id'] === null || $category['parent_id'] === '') {
            $category['children'] = [];
            $parents[(int) $category['id']] = $category;
        } else {
            $children[(int) $category['parent_id']][] = $category;
        }
    }

    foreach ($children as $parentId => $items) {
        if (isset($parents[$parentId])) {
            $parents[$parentId]['children'] = $items;
        }
    }

    return array_values($parents);
}

function getPortfolioProjects(array $filters = []): array
{
    if (!portfolioTablesReady()) {
        return [];
    }

    $where = ['p.is_active = 1', 'c.is_active = 1'];
    $params = [];

    if (!empty($filters['category_slug'])) {
        $where[] = '(c.slug = :category_slug OR parent.slug = :category_slug)';
        $params[':category_slug'] = (string) $filters['category_slug'];
    }

    if (!empty($filters['project_status'])) {
        $where[] = 'p.project_status = :project_status';
        $params[':project_status'] = (string) $filters['project_status'];
    }

    if (!empty($filters['featured'])) {
        $where[] = 'p.is_featured = 1';
    }

    if (!empty($filters['search'])) {
        $where[] = '(p.title LIKE :search OR p.project_location LIKE :search OR p.client_name LIKE :search OR p.short_description LIKE :search OR c.name LIKE :search)';
        $params[':search'] = '%' . (string) $filters['search'] . '%';
    }

    $limitSql = '';
    if (!empty($filters['limit'])) {
        $limitSql = ' LIMIT ' . max(1, min(24, (int) $filters['limit']));
    }

    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug, parent.name AS parent_category_name, parent.slug AS parent_category_slug
            FROM portfolio_projects p
            JOIN portfolio_categories c ON c.id = p.category_id
            LEFT JOIN portfolio_categories parent ON parent.id = c.parent_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY p.display_order ASC, p.id DESC' . $limitSql;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getPortfolioProjectBySlug(string $slug): ?array
{
    if (!portfolioTablesReady()) {
        return null;
    }

    $stmt = db()->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug, parent.name AS parent_category_name, parent.slug AS parent_category_slug
        FROM portfolio_projects p
        JOIN portfolio_categories c ON c.id = p.category_id
        LEFT JOIN portfolio_categories parent ON parent.id = c.parent_id
        WHERE p.slug = :slug AND p.is_active = 1 AND c.is_active = 1 LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $project = $stmt->fetch();

    return is_array($project) ? $project : null;
}

function getPortfolioGallery(int $projectId): array
{
    if (!portfolioTablesReady()) {
        return [];
    }

    $stmt = db()->prepare('SELECT * FROM portfolio_gallery WHERE project_id = :project_id ORDER BY display_order ASC, id ASC');
    $stmt->execute([':project_id' => $projectId]);
    return $stmt->fetchAll();
}

function getPortfolioVideos(int $projectId): array
{
    if (!portfolioTablesReady()) {
        return [];
    }

    $stmt = db()->prepare('SELECT * FROM portfolio_videos WHERE project_id = :project_id ORDER BY display_order ASC, id ASC');
    $stmt->execute([':project_id' => $projectId]);
    return $stmt->fetchAll();
}

function portfolioAssetIsSafe(string $path): bool
{
    return preg_match('#^public/uploads/portfolio/(?:gallery/|category-hero/images/)?[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#', $path) === 1
        && is_file(dirname(__DIR__) . '/' . $path);
}

function portfolioVideoAssetIsSafe(string $path): bool
{
    return preg_match('#^public/uploads/portfolio/category-hero/videos/[a-f0-9]{32}\.(?:mp4|webm)$#', $path) === 1
        && is_file(dirname(__DIR__) . '/' . $path);
}

function youtubeVideoIdFromUrl(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return '';
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '');

    if (strpos($host, 'youtu.be') !== false) {
        return trim($path, '/');
    }

    if (strpos($host, 'youtube.com') !== false) {
        parse_str((string) ($parts['query'] ?? ''), $query);
        if (!empty($query['v']) && is_string($query['v'])) {
            return $query['v'];
        }
        if (preg_match('#/(embed|shorts)/([A-Za-z0-9_-]{6,})#', $path, $matches)) {
            return $matches[2];
        }
    }

    return '';
}

function youtubeEmbedUrl(string $url): string
{
    $id = youtubeVideoIdFromUrl($url);
    return $id !== '' ? 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id) : '';
}

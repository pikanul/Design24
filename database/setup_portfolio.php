<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$pdo = db();

$pdo->exec("
CREATE TABLE IF NOT EXISTS portfolio_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    description TEXT DEFAULT '',
    hero_image TEXT DEFAULT '',
    hero_video TEXT DEFAULT '',
    parent_id INTEGER DEFAULT NULL,
    menu_order INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES portfolio_categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS portfolio_projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    short_description TEXT DEFAULT '',
    full_description TEXT DEFAULT '',
    featured_image TEXT DEFAULT '',
    project_status TEXT NOT NULL DEFAULT 'Completed',
    project_location TEXT DEFAULT '',
    client_name TEXT DEFAULT '',
    completion_year TEXT DEFAULT '',
    project_area TEXT DEFAULT '',
    youtube_url TEXT DEFAULT '',
    is_featured INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    display_order INTEGER NOT NULL DEFAULT 0,
    seo_title TEXT DEFAULT '',
    seo_description TEXT DEFAULT '',
    meta_keywords TEXT DEFAULT '',
    og_image TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES portfolio_categories(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS portfolio_gallery (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    image_path TEXT NOT NULL,
    caption TEXT DEFAULT '',
    display_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES portfolio_projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS portfolio_videos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    youtube_url TEXT NOT NULL,
    video_title TEXT DEFAULT '',
    display_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES portfolio_projects(id) ON DELETE CASCADE
);
");

// Existing installations predate category hero media, so add the columns safely.
$categoryColumns = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
    ? $pdo->query('PRAGMA table_info(portfolio_categories)')->fetchAll()
    : $pdo->query('SHOW COLUMNS FROM portfolio_categories')->fetchAll();
$categoryColumnNames = array_map(static fn(array $column): string => (string) ($column['name'] ?? $column['Field'] ?? ''), $categoryColumns);
if (!in_array('hero_image', $categoryColumnNames, true)) $pdo->exec("ALTER TABLE portfolio_categories ADD COLUMN hero_image TEXT DEFAULT ''");
if (!in_array('hero_video', $categoryColumnNames, true)) $pdo->exec("ALTER TABLE portfolio_categories ADD COLUMN hero_video TEXT DEFAULT ''");

$defaults = [
    ['Residential Portfolio', 'residential-portfolio', '', null, 10],
    ['Office & Commercial Portfolio', 'office-commercial-portfolio', '', null, 20],
    ['Ongoing Projects', 'ongoing-projects', '', null, 30],
    ['Completed Projects', 'completed-projects', '', null, 40],
    ['Project Videos', 'project-videos', '', null, 50],
];

$children = [
    'residential-portfolio' => [
        ['Living Room Designs', 'living-room-designs', 10],
        ['Bedroom Interiors', 'bedroom-interiors', 20],
        ['Dining Spaces', 'dining-spaces', 30],
        ['Kitchen Designs', 'kitchen-designs', 40],
        ['Duplex Residences', 'duplex-residences', 50],
        ['Custom Furniture', 'custom-furniture', 60],
        ['3D Visualizations', '3d-visualizations', 70],
    ],
    'office-commercial-portfolio' => [
        ['Office Interiors', 'office-interiors', 10],
        ['Corporate Workspaces', 'corporate-workspaces', 20],
        ['Reception & Lobby Designs', 'reception-lobby-designs', 30],
        ['Conference Rooms', 'conference-rooms', 40],
        ['Retail & Showroom Designs', 'retail-showroom-designs', 50],
        ['Restaurant & Café Interiors', 'restaurant-cafe-interiors', 60],
        ['Healthcare & Clinic Interiors', 'healthcare-clinic-interiors', 70],
        ['Educational Spaces', 'educational-spaces', 80],
        ['Commercial Fit-Out Projects', 'commercial-fit-out-projects', 90],
    ],
];

$exists = $pdo->prepare('SELECT id FROM portfolio_categories WHERE slug = :slug LIMIT 1');
$insert = $pdo->prepare('INSERT INTO portfolio_categories (name, slug, description, parent_id, menu_order, is_active) VALUES (:name, :slug, :description, :parent_id, :menu_order, 1)');

foreach ($defaults as $category) {
    [$name, $slug, $description, $parentId, $order] = $category;
    $exists->execute([':slug' => $slug]);
    if (!$exists->fetchColumn()) {
        $insert->execute([':name' => $name, ':slug' => $slug, ':description' => $description, ':parent_id' => $parentId, ':menu_order' => $order]);
    }
}

foreach ($children as $parentSlug => $items) {
    $exists->execute([':slug' => $parentSlug]);
    $parentId = (int) $exists->fetchColumn();
    foreach ($items as $item) {
        [$name, $slug, $order] = $item;
        $exists->execute([':slug' => $slug]);
        if (!$exists->fetchColumn()) {
            $insert->execute([':name' => $name, ':slug' => $slug, ':description' => '', ':parent_id' => $parentId, ':menu_order' => $order]);
        }
    }
}

$settings = [
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
$settingExists = $pdo->prepare('SELECT id FROM site_settings WHERE setting_group = :setting_group AND setting_key = :setting_key LIMIT 1');
$settingInsert = $pdo->prepare('INSERT INTO site_settings (setting_group, setting_key, setting_value, setting_type) VALUES (:setting_group, :setting_key, :setting_value, :setting_type)');
foreach ($settings as $key => $value) {
    $settingExists->execute([':setting_group' => 'portfolio_page', ':setting_key' => $key]);
    if (!$settingExists->fetchColumn()) {
        $settingInsert->execute([':setting_group' => 'portfolio_page', ':setting_key' => $key, ':setting_value' => $value, ':setting_type' => 'text']);
    }
}

foreach ([__DIR__ . '/../public/uploads/portfolio', __DIR__ . '/../public/uploads/portfolio/gallery', __DIR__ . '/../public/uploads/portfolio/category-hero/images', __DIR__ . '/../public/uploads/portfolio/category-hero/videos'] as $directory) {
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}

echo "Portfolio tables and upload folders are ready.\n";

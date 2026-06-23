<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/database.php';

$pdo = db();
$pdo->exec((string) file_get_contents(__DIR__ . '/schema.sql'));

if ((int) $pdo->query('SELECT COUNT(*) FROM team_groups')->fetchColumn() > 0
    || (int) $pdo->query('SELECT COUNT(*) FROM team_members')->fetchColumn() > 0) {
    fwrite(STDOUT, "Team seed skipped because team data already exists.\n");
    exit;
}

$groups = [
    ['Office & Management', 'office-management', 'Office', 'Leading with vision, managing with excellence, and driving the business forward.', 'briefcase', 1],
    ['Design & Field Team', 'design-field-team', 'Field', 'Designing spaces, planning solutions, and managing projects on the field.', 'compass', 2],
    ['Factory & Production Team', 'factory-production-team', 'Factory', 'Turning designs into reality with skill, precision, and dedication.', 'factory', 3],
];

$members = [
    ['Ikramul Hossain Emon', 'Admin & Accounts Manager', 'Admin & Accounts', 'office-management', 1, 1],
    ['Md. Mahfuzur Rahman', 'AGM, Corporate Sales & Marketing', 'Corporate Sales & Marketing', 'office-management', 1, 2],
    ['Md. Shamimul Hasan Pradhan', 'Senior Assistant Manager, Corporate Sales & Marketing', 'Corporate Sales & Marketing', 'office-management', 1, 3],
    ['Tahsin Mumin Ayon', 'Assistant Manager, Corporate Sales & Marketing', 'Corporate Sales & Marketing', 'office-management', 1, 4],
    ['Muzahid Alam', 'Assistant Manager, Corporate Sales', 'Corporate Sales', 'office-management', 0, 5],
    ['Abdullah Ar Rifat', 'Senior Executive, Corporate Sales & Marketing', 'Corporate Sales & Marketing', 'office-management', 0, 6],
    ['Md. Ruhul Amin', 'Senior Executive, Corporate Sales & Marketing', 'Corporate Sales & Marketing', 'office-management', 0, 7],
    ['Ratul Islam', 'Office Support Staff', 'Office Support', 'office-management', 0, 8],
    ['Oyshi Khanom Fancy', 'Junior Architect', 'Architecture', 'design-field-team', 0, 1],
    ['Protik Saha Parba', 'Site Engineer', 'Engineering', 'design-field-team', 0, 2],
    ['Md. Rakib Hasan', 'Floor Incharge', 'Field Operations', 'design-field-team', 0, 3],
    ['Eklas Khan', 'Senior Operator', 'Production', 'factory-production-team', 0, 1],
    ['Md. Malak Miah', 'Senior Operator', 'Production', 'factory-production-team', 0, 2],
    ['Shahidul Islam', 'Senior Operator (Welding)', 'Welding', 'factory-production-team', 0, 3],
    ['Md. Tarek Moulovi', 'Senior Operator', 'Production', 'factory-production-team', 0, 4],
    ['Sojib Hossain', 'Operator', 'Production', 'factory-production-team', 0, 5],
    ['Shakib Sheikh', 'Operator', 'Production', 'factory-production-team', 0, 6],
    ['Md. Emon Ali', 'Operator', 'Production', 'factory-production-team', 0, 7],
    ['Md. Ruhul Amin', 'Assistant Operator', 'Production', 'factory-production-team', 0, 8],
    ['Md. Moktanur Zaman', 'Assistant Operator', 'Production', 'factory-production-team', 0, 9],
    ['Md. Sohel Mia', 'Assistant Operator', 'Production', 'factory-production-team', 0, 10],
    ['Md. Rana', 'Assistant Operator', 'Production', 'factory-production-team', 0, 11],
];

function seedSlug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

try {
    $pdo->beginTransaction();
    $groupInsert = $pdo->prepare('INSERT INTO team_groups (name, slug, short_name, description, icon, show_in_filters, show_on_team_page, display_order, status) VALUES (:name, :slug, :short_name, :description, :icon, 1, 1, :display_order, 1)');
    $groupIds = [];
    foreach ($groups as [$name, $slug, $shortName, $description, $icon, $displayOrder]) {
        $groupInsert->execute([':name' => $name, ':slug' => $slug, ':short_name' => $shortName, ':description' => $description, ':icon' => $icon, ':display_order' => $displayOrder]);
        $groupIds[$slug] = (int) $pdo->lastInsertId();
    }

    $memberInsert = $pdo->prepare('INSERT INTO team_members (full_name, slug, designation, department, team_group_id, featured_member, status, display_order, image_alt) VALUES (:full_name, :slug, :designation, :department, :team_group_id, :featured, 1, :display_order, :image_alt)');
    $usedSlugs = [];
    foreach ($members as [$name, $designation, $department, $groupSlug, $featured, $displayOrder]) {
        $baseSlug = seedSlug($name);
        $slug = $baseSlug;
        $suffix = 2;
        while (isset($usedSlugs[$slug])) {
            $slug = $baseSlug . '-' . $suffix++;
        }
        $usedSlugs[$slug] = true;
        $memberInsert->execute([
            ':full_name' => $name,
            ':slug' => $slug,
            ':designation' => $designation,
            ':department' => $department,
            ':team_group_id' => $groupIds[$groupSlug],
            ':featured' => $featured,
            ':display_order' => $displayOrder,
            ':image_alt' => $name,
        ]);
    }
    $pdo->commit();
    fwrite(STDOUT, "Seeded 3 team groups and 22 team members.\n");
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Team seed failed: " . $exception->getMessage() . "\n");
    exit(1);
}

<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/config/database.php';

$pdo = db();
$find = $pdo->prepare('SELECT id FROM team_groups WHERE slug=? LIMIT 1');
$find->execute(['founders-directors']);
if (!$find->fetchColumn()) {
    $pdo->prepare('INSERT INTO team_groups (name,slug,short_name,description,icon,show_in_filters,show_on_team_page,display_order,status) VALUES (?,?,?,?,?,1,1,0,1)')->execute(['Founders & Directors','founders-directors','Founders','The founders and directors guiding Design24 Studio.','briefcase']);
    echo "Founders & Directors group created.\n";
} else {
    echo "Founders & Directors group already exists.\n";
}

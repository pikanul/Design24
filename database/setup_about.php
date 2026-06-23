<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/database.php';

$pdo = db();
$schema = file_get_contents(__DIR__ . '/schema.sql');
if (!is_string($schema)) {
    throw new RuntimeException('The database schema could not be read.');
}
$pdo->exec($schema);

$pdo->beginTransaction();
try {
    if ((int) $pdo->query('SELECT COUNT(*) FROM about_settings')->fetchColumn() === 0) {
        $statement = $pdo->prepare('INSERT INTO about_settings
            (subtitle, heading, description, button_one_text, button_one_link, button_two_text, button_two_link, status)
            VALUES (:subtitle, :heading, :description, :button_one_text, :button_one_link, :button_two_text, :button_two_link, 1)');
        $statement->execute([
            ':subtitle' => 'About Design24 Studio',
            ':heading' => 'We Shape Thoughtful Spaces Around Your Life',
            ':description' => 'Design24 Studio brings interior design, custom furniture, skilled production, and turnkey execution together under one roof. We create refined, practical environments with careful planning and dependable craftsmanship.',
            ':button_one_text' => 'Discover Our Work',
            ':button_one_link' => '#portfolio',
            ':button_two_text' => 'Book a Consultation',
            ':button_two_link' => '#contact',
        ]);
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM about_features')->fetchColumn() === 0) {
        $statement = $pdo->prepare('INSERT INTO about_features (icon, title, sort_order, status) VALUES (:icon, :title, :sort_order, 1)');
        foreach ([
            ['design', 'Creative Design', 1],
            ['quality', 'Quality Craftsmanship', 2],
            ['turnkey', 'Complete Turnkey Service', 3],
        ] as $feature) {
            $statement->execute([':icon' => $feature[0], ':title' => $feature[1], ':sort_order' => $feature[2]]);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM about_counters')->fetchColumn() === 0) {
        $statement = $pdo->prepare('INSERT INTO about_counters (icon, number, suffix, title, description, sort_order, status)
            VALUES (:icon, :number, :suffix, :title, :description, :sort_order, 1)');
        foreach ([
            ['projects', 100, '+', 'Projects Completed', 'Residential and commercial spaces delivered.', 1],
            ['experience', 8, '+', 'Years Experience', 'Design knowledge strengthened through execution.', 2],
            ['clients', 500, '+', 'Happy Clients', 'Relationships built through attentive service.', 3],
            ['turnkey', 360, '°', 'Turnkey Solutions', 'One accountable team from concept to completion.', 4],
        ] as $counter) {
            $statement->execute([
                ':icon' => $counter[0], ':number' => $counter[1], ':suffix' => $counter[2],
                ':title' => $counter[3], ':description' => $counter[4], ':sort_order' => $counter[5],
            ]);
        }
    }
    $pdo->commit();
    echo "About module tables and default content are ready.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $exception;
}

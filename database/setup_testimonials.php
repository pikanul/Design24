<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/config/database.php';
$schema = file_get_contents(__DIR__ . '/schema.sql');
if (!is_string($schema)) throw new RuntimeException('The database schema could not be read.');
$pdo = db();
$pdo->exec($schema);

if ((int) $pdo->query('SELECT COUNT(*) FROM testimonials')->fetchColumn() === 0) {
    $insert = $pdo->prepare('INSERT INTO testimonials (company_name,location,short_feedback,full_feedback,rating,sort_order,status) VALUES (:company_name,:location,:short_feedback,:full_feedback,:rating,:sort_order,1)');
    $rows = [
        ['BRAC University','Dhaka','Design24 Studio delivered beyond our expectations. Their attention to detail and professionalism made the entire process smooth and enjoyable.','Design24 Studio delivered beyond our expectations. Their attention to detail, thoughtful planning, and professionalism made the entire design and execution process smooth and enjoyable.',5,1],
        ['Bashundhara Group','Dhaka','Excellent team! They understood our vision and turned it into a functional and beautiful workspace. Highly recommended.','The Design24 Studio team understood our vision and transformed it into a functional, elegant, and beautiful workspace. Communication remained clear throughout the project, and the result met our expectations.',5,2],
        ['PRAN-RFL Group','Dhaka','Very creative designs and great execution quality. They completed the project on time with great communication.','We appreciated the creative design approach, dependable execution quality, and consistent communication. The team completed the project on time and handled every stage professionally.',5,3],
        ['DBL Ceramics','Dhaka','A fantastic experience from start to finish. The team is skilled, responsive, and truly cares about client satisfaction.','A fantastic experience from start to finish. The Design24 Studio team is skilled, responsive, and attentive. Their commitment to quality and client satisfaction was clear throughout the project.',5,4],
    ];
    foreach ($rows as $row) $insert->execute([':company_name'=>$row[0],':location'=>$row[1],':short_feedback'=>$row[2],':full_feedback'=>$row[3],':rating'=>$row[4],':sort_order'=>$row[5]]);
}
echo "Testimonials table and starter content are ready.\n";

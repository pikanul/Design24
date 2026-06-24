<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/consultation.php';
consultationEnsureSchema(db());
echo "Consultation tables are ready.\n";

<?php

$path = __DIR__ . '/uploads/MKBM_JKR_L7_KEMBOJA_2026_655_11-08-2026.pdf';

if (!file_exists($path)) {
    http_response_code(404);
    exit('File not found');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline');
header('Content-Length: ' . filesize($path));

readfile($path);
exit;
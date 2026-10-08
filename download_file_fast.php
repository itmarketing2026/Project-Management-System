<?php
session_start();

/* ===============================
   ACCESS CHECK
================================ */
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    http_response_code(403);
    exit('Access denied.');
}

/* ===============================
   GET FILE
================================ */
$file = $_GET['file'] ?? '';

if ($file === '') {
    http_response_code(400);
    exit('No file specified.');
}

/*
 * IMPORTANT:
 * Untuk test ini kita hanya benarkan nama fail,
 * bukan path/folder daripada URL.
 */
$file = basename($file);

/* ===============================
   UPLOAD ROOT
================================ */
$upload_root = __DIR__ . '/uploads/';

/*
 * TEST:
 * Letakkan PDF yang nak diuji terus dalam:
 *
 * uploads/test.pdf
 *
 * Kemudian buka:
 * download_file_fast.php?file=test.pdf
 */
$path = $upload_root . $file;

/* ===============================
   FILE CHECK
================================ */
if (!is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}

/* ===============================
   MIME TYPE
================================ */
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

$mime = match ($ext) {
    'pdf' => 'application/pdf',

    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',

    'doc' => 'application/msword',

    'docx' =>
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',

    default => 'application/octet-stream'
};

/* ===============================
   HEADERS
================================ */
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');

/* ===============================
   OUTPUT FILE
================================ */
readfile($path);
exit;
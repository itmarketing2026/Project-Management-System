<?php
session_start();

// 🔒 Check login
if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

$project_id = $_GET['project_id'] ?? '';
$type = $_GET['type'] ?? '';
$file = $_GET['file'] ?? '';

// 🧼 sanitize
$project_id = (int)$project_id;
$file = basename($file);

// ❌ Validate
if (!$project_id || !$type || !$file) {
    die("Invalid request");
}

// 📂 mapping folder ikut type
$folders = [
    'sst' => 'sst/',
    'loi' => 'loi/',
    'report' => 'report/',
    'cerun' => 'cerun/'
];

if (!isset($folders[$type])) {
    die("Invalid type");
}

// 📁 path sebenar
$baseDir = __DIR__ . "/uploads/project_" . $project_id . "/";
$path = $baseDir . $folders[$type] . $file;

// ❌ check file
if (!file_exists($path)) {
    die("File not found");
}

// 📄 display
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

$mimeTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png'
];

header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
header('Content-Disposition: inline; filename="' . $file . '"');

readfile($path);
exit;
<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}

require_once 'include/msqli_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: add_weeknd_document.php");
    exit();
}

$title = trim($_POST['title'] ?? '');
$doc_date = trim($_POST['date'] ?? '');

if ($title === '' || $doc_date === '' || !isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    die("Please fill in all fields and upload a valid document.");
}

// Validate file type
$allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
$original_name = $_FILES['document']['name'];
$ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

if (!in_array($ext, $allowed_ext)) {
    die("Invalid file type.");
}

// Max size 160MB
$max_size = 160 * 1024 * 1024;
if ($_FILES['document']['size'] > $max_size) {
    die("File too large.");
}

// Prepare upload directory
$upload_dir = 'uploads/weeknd/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Sanitize filename
$safe_title = preg_replace('/[^A-Za-z0-9_\-]/', '_', $title);
$filename = 'weeknd_' . $safe_title . '_' . time() . '.' . $ext;
$destination = $upload_dir . $filename;

if (!move_uploaded_file($_FILES['document']['tmp_name'], $destination)) {
    die("Failed to upload file.");
}

// Get current user info
$created_by = $_SESSION['user_id'];
$created_by_name = $_SESSION['user_name'] ?? 'Unknown';

$stmt = $db->prepare("
    INSERT INTO weeknd_documents (title, doc_date, file_path, created_by, created_by_name)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("sssis", $title, $doc_date, $destination, $created_by, $created_by_name);
$stmt->execute();
$stmt->close();

header("Location: weeknd.php");
exit();
?>
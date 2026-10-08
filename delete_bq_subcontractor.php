<?php
session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    die("Invalid request. Please try again.");
}

$project_id = intval($_POST['project_id'] ?? 0);
$bq_id      = intval($_POST['bq_id'] ?? 0);

if (!$project_id || !$bq_id) {
    die("Invalid Request.");
}

if (!user_can_access_project($db, $_SESSION['user_id'], $project_id, $_SESSION['account_type'] ?? '')) {
    die("You do not have permission to view this project.");
}

$role = $_SESSION['account_type'] ?? '';
if (!in_array($role, ['Client Admin', 'Admin (QA/QC)'])) {
    die("You do not have permission to delete this record.");
}

$stmt = $db->prepare("SELECT id FROM bq_subcontractor WHERE id = ? AND project_id = ?");
$stmt->bind_param("ii", $bq_id, $project_id);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exists) {
    die("Record not found.");
}

$stmt_vo = $db->prepare("SELECT vo_document FROM bq_subcontractor_vo WHERE bq_subcontractor_id = ? AND vo_document IS NOT NULL AND vo_document != ''");
$stmt_vo->bind_param("i", $bq_id);
$stmt_vo->execute();
$vo_files = $stmt_vo->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_vo->close();

$upload_dir = __DIR__ . "/uploads/project_{$project_id}/bq/";
foreach ($vo_files as $vf) {
    $path = $upload_dir . $vf['vo_document'];
    if (file_exists($path)) {
        unlink($path);
    }
}

$stmt_del = $db->prepare("DELETE FROM bq_subcontractor WHERE id = ? AND project_id = ?");
$stmt_del->bind_param("ii", $bq_id, $project_id);

if ($stmt_del->execute()) {
    $stmt_del->close();
    header("Location: bq_list.php?project_id={$project_id}&msg=deleted");
    exit;
}

$stmt_del->close();
die("Database error: could not delete record.");
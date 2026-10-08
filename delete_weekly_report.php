<?php
session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

$role = strtolower(trim($_SESSION['account_type'] ?? ''));

$allowed = in_array($role, [
    'client admin',
    'admin (qa/qc)'
]);

if (!$allowed) {
    die("Access denied.");
}

$id = intval($_GET['id'] ?? 0);
$project_id = intval($_GET['project_id'] ?? 0);

if (!$id) {
    die("Invalid ID");
}

/* DELETE SUBCONTRACTORS */
$stmt = $db->prepare("
    DELETE FROM subcontractors
    WHERE report_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

/* DELETE WEEKLY REPORT */
$stmt = $db->prepare("
    DELETE FROM weekly_reports
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

/* REDIRECT BACK */
header("Location: wr.php?project_id=" . $project_id);
exit;
?>
<?php
require_once 'include/msqli_connect.php';
session_start();

// 🔒 security (role check)
$role = strtolower(trim($_SESSION['account_type'] ?? ''));
if (!in_array($role, ['client admin', 'admin (qa/qc)'])) {
    error_log("[edit_status] Access denied — role: {$role}, user_id: " . ($_SESSION['user_id'] ?? 'none'));
    die("Access denied");
}

// 📌 ambil ID
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    error_log("[edit_status] Invalid ID — raw GET id: " . ($_GET['id'] ?? 'missing'));
    die("Invalid ID");
}

// 🔍 fetch the report first to get its project_id
$stmtFetch = $db->prepare("SELECT project_id FROM weekly_reports WHERE id = ?");
$stmtFetch->bind_param("i", $id);
$stmtFetch->execute();
$report = $stmtFetch->get_result()->fetch_assoc();
$stmtFetch->close();

if (!$report) {
    error_log("[edit_status] No weekly_reports row found for id={$id}");
    die("Report not found.");
}

$project_id = (int)$report['project_id'];

if (!user_can_access_project($db, $_SESSION['user_id'], $project_id, $_SESSION['account_type'] ?? '')) {
    error_log("[edit_status] Access denied to project_id={$project_id} for user_id=" . $_SESSION['user_id']);
    die("You do not have permission to view this project.");
}

// 🔄 update status
$stmt = $db->prepare("UPDATE weekly_reports SET status='draft' WHERE id=?");
if (!$stmt) {
    error_log("[edit_status] Prepare failed: " . $db->error);
    die("Database error.");
}
$stmt->bind_param("i", $id);

if (!$stmt->execute()) {
    error_log("[edit_status] Execute failed for id={$id}: " . $stmt->error);
    die("Failed to update report status.");
}
$stmt->close();

error_log("[edit_status] Success — id={$id} set to draft by user_id=" . $_SESSION['user_id']);

// ✅ redirect balik
header("Location: edit_report.php?id=" . $id);
exit;
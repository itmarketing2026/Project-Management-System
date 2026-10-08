<?php
session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    header("Location: login.php");
    exit;
}

$user_id      = (int)($_SESSION['user_id'] ?? 0);
$account_type = $_SESSION['account_type'] ?? '';
$is_client_admin = ($account_type === 'Client Admin');
$is_qaqc         = ($account_type === 'Admin (QA/QC)');

if (!$is_client_admin && !$is_qaqc) {
    http_response_code(403);
    exit('Access denied.');
}

// Context passed from cerun.php
$post_cerun_id = (int)($_POST['cerun_id'] ?? 0);
$type          = trim($_POST['type'] ?? '');
$year          = trim($_POST['year'] ?? '');

// Helper: build redirect back to cerun.php
function backToCerun($cerun_id, $type, $year) {
    if ($cerun_id > 0) {
        return 'cerun.php?' . http_build_query([
            'cerun_id' => $cerun_id,
            'type'     => $type,
            'year'     => $year,
        ]);
    }
    return 'index.php'; // fallback when we have no context
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . backToCerun($post_cerun_id, $type, $year));
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid record.';
    header("Location: " . backToCerun($post_cerun_id, $type, $year));
    exit;
}

// Confirm record exists AND get its real cerun_id
$stmt = $db->prepare("SELECT id, cerun_id FROM letteroutpending_cerun WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$record) {
    $_SESSION['flash_error'] = 'Record not found.';
    $db->close();
    header("Location: " . backToCerun($post_cerun_id, $type, $year));
    exit;
}

// Trust the DB value over the hidden field
$cerun_id = (int)$record['cerun_id'];

$del = $db->prepare("DELETE FROM letteroutpending_cerun WHERE id = ?");
$del->bind_param("i", $id);
$success = $del->execute();
$del->close();
$db->close();

if ($success) {
    $_SESSION['flash_success'] = 'Record deleted successfully.';
} else {
    $_SESSION['flash_error'] = 'Failed to delete record.';
}

header("Location: " . backToCerun($cerun_id, $type, $year));
exit;
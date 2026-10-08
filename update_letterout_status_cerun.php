<?php
session_start();
require_once 'include/msqli_connect.php';
if (!isset($_SESSION['user_id'])) { die("Unauthorized."); }

$id     = intval($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$reason = trim($_POST['rejection_reason'] ?? '');

if (!$id || !$action) { die("Invalid request."); }

$stmt = $db->prepare("SELECT status, cerun_id, running_number FROM letteroutpending_cerun WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$pending = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pending) { die("Record not found."); }

$status   = $pending['status'];
$cerun_id = $pending['cerun_id'];
$newStatus = null;

switch ($action) {
    case 'verify_verifier':
        if ($status === 'acknowledged' || $status === 'rejected') {
            $newStatus = 'verified_verifier';
            $clear = $db->prepare("UPDATE letteroutpending_cerun SET rejection_reason = NULL WHERE id = ?");
            $clear->bind_param("i", $id);
            $clear->execute();
        }
        break;

    case 'verify_final':
        if ($status === 'verified_verifier') {
            $newStatus = 'verified_final';
            $clear = $db->prepare("UPDATE letteroutpending_cerun SET rejection_reason = NULL WHERE id = ?");
            $clear->bind_param("i", $id);
            $clear->execute();
            $upTitle = $_POST['title'] ?? null;
            $upDate  = $_POST['letter_date'] ?? null;
            $upRef   = $_POST['ack_ref'] ?? null;
            if ($upTitle) {
                $up1 = $db->prepare("UPDATE letteroutpending_cerun SET title = ? WHERE id = ?");
                $up1->bind_param("si", $upTitle, $id);
                $up1->execute();
            }
            if ($upDate && $upRef) {
                $up2 = $db->prepare("UPDATE letteroutacknowledge_cerun SET letter_date = ?, ack_ref = ? WHERE pending_id = ?");
                $up2->bind_param("ssi", $upDate, $upRef, $id);
                $up2->execute();
            }
        }
        break;

    case 'reject':
        $newStatus = 'rejected';
        $stmt_rej = $db->prepare("UPDATE letteroutpending_cerun SET status = ?, rejection_reason = ? WHERE id = ?");
        $stmt_rej->bind_param("ssi", $newStatus, $reason, $id);
        $stmt_rej->execute();
        $stmt_rej->close();
        $newStatus = null; // skip generic update
        break;

    case 'sendback_pending':
        if ($status === 'acknowledged') {
            $newStatus = 'submitted';
        }
        break;

    case 'sendback_acknowledged':
        if ($status === 'verified_verifier') {
            $newStatus = 'acknowledged';
        }
        break;
}

if ($newStatus) {
    $stmt = $db->prepare("UPDATE letteroutpending_cerun SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $newStatus, $id);
    $stmt->execute();
    $stmt->close();
}

// Folder info for redirect
$folderYear = date("Y");
$folderType = '4';
if (!empty($pending['running_number'])) {
    if (preg_match('/(\d{4})/', $pending['running_number'], $matches)) { $folderYear = $matches[0]; }
    $parts  = explode('/', $pending['running_number']);
    $prefix = $parts[0] ?? '';
    if (strpos($prefix, '6') !== false) { $folderType = '6'; }
}

// Sendback actions go back to the view page so the success notice shows
if (in_array($action, ['sendback_pending', 'sendback_acknowledged'])) {
    header("Location: viewletteroutacknowledged_cerun.php?id=$id&msg=sentback");
} else {
    header("Location: cerun.php?cerun_id=$cerun_id&year=$folderYear&type=$folderType&msg=updated");
}
exit;
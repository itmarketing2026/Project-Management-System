<?php
session_start();
require_once 'include/msqli_connect.php';
if (!isset($_SESSION['user_id'])) { die("Unauthorized."); }

$id     = intval($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
if (!$id || !$action) { die("Invalid request."); }

// Fetch record details
$stmt = $db->prepare("SELECT status, project_id, running_number FROM letter_out_pending WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$pending = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$pending) { die("Record not found."); }

$status     = $pending['status'];
$project_id = $pending['project_id'];
$newStatus  = null;

switch ($action) {
    case 'verify_verifier':
        if ($status === 'acknowledged') {
            $newStatus = 'verified_verifier';
        }
        break;

    case 'verify_final':
    if ($status === 'verified_verifier' || $status === 'acknowledged') {
        $newStatus = 'verified_final';
            $upTitle = $_POST['title'] ?? null;
            $upDate  = $_POST['letter_date'] ?? null;
            if ($upTitle) {
                $up1 = $db->prepare("UPDATE letter_out_pending SET title = ? WHERE id = ?");
                $up1->bind_param("si", $upTitle, $id);
                $up1->execute();
                $up1->close();
            }
            if ($upDate) {
                $up2 = $db->prepare("UPDATE letter_out_acknowledge SET letter_date = ? WHERE letter_out_id = ?");
                $up2->bind_param("si", $upDate, $id);
                $up2->execute();
                $up2->close();
            }
        }
        break;

    case 'reject':
        if (in_array($status, ['acknowledged', 'verified_verifier'])) {
            $newStatus = 'rejected';
            $reason = trim($_POST['rejection_reason'] ?? '');
            $upReason = $db->prepare("UPDATE letter_out_pending SET rejection_reason = ? WHERE id = ?");
            $upReason->bind_param("si", $reason, $id);
            $upReason->execute();
            $upReason->close();
        }
        break;

    // Admin QA/QC sends back from acknowledged → pending
  case 'sendback_pending':
    if (in_array(
        $status,
        ['acknowledged', 'verified_verifier', 'verified_final'],
        true
    )) {
        $newStatus = 'submitted';
    }
    break;

    // Admin QA/QC sends back from verified_verifier → acknowledged
    case 'sendback_acknowledged':
        if ($status === 'verified_verifier') {
            $newStatus = 'acknowledged';
        }
        break;
}

if ($newStatus) {
    $stmt = $db->prepare("UPDATE letter_out_pending SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $newStatus, $id);
    $stmt->execute();
    $stmt->close();
}

// Redirect back to the record view instead of list, so the sent back notice shows
if (in_array($action, ['sendback_pending', 'sendback_acknowledged'])) {
    header("Location: viewletteroutacknowledged.php?id=" . $id . "&msg=sentback");
} else {
    header("Location: letterout_list.php?project_id=" . $project_id . "&msg=updated");
}
exit;
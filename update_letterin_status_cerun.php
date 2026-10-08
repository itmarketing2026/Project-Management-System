<?php
session_start();
require_once 'include/msqli_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user_id'])) {
        die("Unauthorized access.");
    }

    $user_id   = $_SESSION['user_id'];
    $letter_id = intval($_POST['letter_id'] ?? 0);
    $cerun_id  = intval($_POST['cerun_id'] ?? 0);
    $action    = $_POST['action'] ?? '';
    $reason    = trim($_POST['rejection_reason'] ?? '');

    // GET USER INFO
   $stmt_u = $db->prepare("SELECT full_name, account_type FROM users WHERE id = ?");
$stmt_u->bind_param("i", $user_id);
$stmt_u->execute();
$stmt_u->bind_result($logged_in_name, $logged_in_account_type);
$stmt_u->fetch();
$stmt_u->close();

    // GET LETTER INFO
    $stmt_l = $db->prepare("
        SELECT verifier, type, file_path 
        FROM letterin_cerun 
        WHERE id = ?
    ");
    $stmt_l->bind_param("i", $letter_id);
    $stmt_l->execute();
    $stmt_l->bind_result($assigned_name, $currentType, $file_path);
    $stmt_l->fetch();
    $stmt_l->close();

    /*
    ============================
    DELETE LETTER
    ============================
    */
    if ($action === 'delete') {

        // ONLY QAQC & CLIENT ADMIN
if (!in_array(trim($logged_in_account_type), ['Client Admin', 'Admin (QA/QC)'])) {
	die("⚠️ Access denied.");
        }

        // DELETE FILES
        $files = json_decode($file_path, true);

        if (!empty($files) && is_array($files)) {

            foreach ($files as $file) {

                if (file_exists($file)) {
                    unlink($file);
                }

            }

        }

        // DELETE DATABASE RECORD
        $delete = $db->prepare("
            DELETE FROM letterin_cerun 
            WHERE id = ? AND cerun_id = ?
        ");
        $delete->bind_param("ii", $letter_id, $cerun_id);

        if ($delete->execute()) {

            header("Location: cerun.php?cerun_id=$cerun_id&type=" . urlencode($currentType) . "&letter=in&msg=deleted");
            exit;

        } else {

            die("Delete failed: " . $db->error);

        }

    }
	
	/*
============================
UPDATE LETTER
============================
*/
if ($action === 'update') {

    if (!in_array(trim($logged_in_account_type), ['Client Admin', 'Admin (QA/QC)'])) {
        die("⚠️ Access denied.");
    }

    $title = trim($_POST['title'] ?? '');

    $update = $db->prepare("
        UPDATE letterin_cerun
        SET title = ?
        WHERE id = ? AND cerun_id = ?
    ");

    $update->bind_param(
        "sii",
        $title,
        $letter_id,
        $cerun_id
    );

    if ($update->execute()) {

        header("Location: cerun_detail.php?cerun_id=$cerun_id&letter_id=$letter_id");
        exit;

    } else {

        die("Error: " . $db->error);

    }
}
	

    /*
    ============================
    VERIFY / REJECT
    ============================
    */
    if (trim($logged_in_name) === trim($assigned_name)) {

        if ($action === 'verify') {

            $new_status = 'verified';

            $update = $db->prepare("
                UPDATE letterin_cerun 
                SET status = ?, rejection_reason = NULL 
                WHERE id = ?
            ");

            $update->bind_param("si", $new_status, $letter_id);

        } elseif ($action === 'reject') {

            $new_status = 'rejected';

            $update = $db->prepare("
                UPDATE letterin_cerun 
                SET status = ?, rejection_reason = ? 
                WHERE id = ?
            ");

            $update->bind_param("ssi", $new_status, $reason, $letter_id);

        } else {

            die("Invalid action.");

        }

        if ($update->execute()) {

            header("Location: cerun.php?cerun_id=$cerun_id&type=" . urlencode($currentType) . "&letter=in&msg=success");
            exit;

        } else {

            die("Error: " . $db->error);

        }

    } else {

        die("⚠️ Security Error: You are not the assigned verifier for this letter.");

    }

}
?>
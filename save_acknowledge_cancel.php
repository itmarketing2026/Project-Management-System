<?php
session_start();
require_once 'include/msqli_connect.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Invalid request method.");
}

$id      = intval($_POST['id'] ?? 0);
$ackType = $_POST['ackType'] ?? '';

$title    = trim($_POST['title']    ?? '');
$agency   = trim($_POST['agency']   ?? '');
$verifier = trim($_POST['verifier'] ?? '');



if (!$id || !$ackType) {
    echo "<script>alert('Error: Missing data'); window.history.back();</script>";
    exit;
}

/* =========================
   GET PROJECT ID
========================= */
$projectQuery = $db->prepare("SELECT project_id FROM letter_out_pending WHERE id = ?");
$projectQuery->bind_param("i", $id);
$projectQuery->execute();
$projectQuery->bind_result($project_id);

if (!$projectQuery->fetch()) {
    echo "<script>alert('Project not found'); window.history.back();</script>";
    exit;
}
$projectQuery->close();

/* =========================
   UPDATE EDITABLE FIELDS
========================= */
if ($title) {
    $editStmt = $db->prepare("
        UPDATE letter_out_pending 
        SET title=?, agency=?, verifier=?
        WHERE id=?
    ");
    $editStmt->bind_param("sssi", $title, $agency, $verifier, $id);
    $editStmt->execute();
    $editStmt->close();
}

/* =====================================================
   ACKNOWLEDGE
===================================================== */
if ($ackType === 'acknowledge') {

    $letter_date  = trim($_POST['letter_date'] ?? '');
    $ack_date     = trim($_POST['ack_date'] ?? '');
    $send_mail    = trim($_POST['send_mail'] ?? '');
    $submitted_by = trim($_POST['submitted_by'] ?? '');

    if (!$letter_date || !$ack_date || !$send_mail || !$submitted_by) {
        echo "<script>alert('Please fill all required fields'); window.history.back();</script>";
        exit;
    }

    /* =========================
       CHECK EXISTING
    ========================= */
    $check = $db->prepare("SELECT id, letter_file FROM letter_out_acknowledge WHERE letter_out_id=? LIMIT 1");
    $check->bind_param("i", $id);
    $check->execute();
    $check->store_result();

    $exists = $check->num_rows > 0;

    $existing_file = '';
    if ($exists) {
        $check->bind_result($ack_id, $existing_file);
        $check->fetch();
    }
    $check->close();

    /* =========================
       FILE UPLOAD (MULTIPLE)
    ========================= */
    $uploadDir = __DIR__ . '/uploads/';
    $uploadUrl = 'uploads/';

    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $uploadedPaths  = [];

	// Document 1
	if (isset($_FILES['letter_file']) && is_array($_FILES['letter_file']['name'])) {
		foreach ($_FILES['letter_file']['name'] as $key => $name) {
			if ($_FILES['letter_file']['error'][$key] === UPLOAD_ERR_OK) {
				$tmp = $_FILES['letter_file']['tmp_name'][$key];
				$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
				$allowed = ['pdf','doc','docx'];
				if (!in_array($ext, $allowed)) continue;
				$originalName = pathinfo($name, PATHINFO_FILENAME);
				$safeName = preg_replace("/[^a-zA-Z0-9]/", "_", $originalName);
				$uploadDate = date('d-m-Y');
				$newName = $safeName . "_" . $uploadDate . "." . $ext;
				if (move_uploaded_file($tmp, $uploadDir . $newName)) {
					$uploadedPaths[] = $uploadUrl . $newName;
				}
			}
		}
	}

    /* =========================
       FINAL FILE VALUE
    ========================= */
    $letter_file  = !empty($uploadedPaths)  ? json_encode($uploadedPaths)  : $existing_file;

    /* =========================
       INSERT / UPDATE
    ========================= */
    if ($exists) {

        $stmt = $db->prepare("
            UPDATE letter_out_acknowledge 
            SET submitted_by=?, letter_date=?, ack_date=?, send_mail=?, letter_file=?
            WHERE letter_out_id=?
        ");

        $stmt->bind_param("sssssi",
            $submitted_by,
            $letter_date,
            $ack_date,
            $send_mail,
            $letter_file,
            $id
        );

    } else {

        $stmt = $db->prepare("
            INSERT INTO letter_out_acknowledge 
            (letter_out_id, submitted_by, letter_date, ack_date, send_mail, letter_file)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param("isssss",
            $id,
            $submitted_by,
            $letter_date,
            $ack_date,
            $send_mail,
            $letter_file
					
        );
    }

    if (!$stmt->execute()) {
        die("DB Error: " . $stmt->error);
    }

    $stmt->close();

    /* =========================
       UPDATE STATUS
    ========================= */
    $role   = strtolower(trim($_SESSION['account_type'] ?? ''));
    $status = ($role === 'management') ? 'verified_verifier' : 'acknowledged';

    $update = $db->prepare("UPDATE letter_out_pending SET status=? WHERE id=?");
    $update->bind_param("si", $status, $id);
    $update->execute();
    $update->close();

    $db->close();

    echo "<script>alert('Acknowledge success'); window.location.href='letterout_list.php?project_id={$project_id}';</script>";
    exit;
}

/* =====================================================
   CANCEL
===================================================== */
elseif ($ackType === 'cancel') {

    $cancel_reason = trim($_POST['cancel_reason'] ?? '');

    if ($cancel_reason === '') {
        echo "<script>alert('Please provide cancel reason'); window.history.back();</script>";
        exit;
    }

    /* =========================
       INSERT CANCEL
    ========================= */
    $stmt = $db->prepare("
        INSERT INTO letter_out_cancel (letter_out_id, cancel_reason, created_at)
        VALUES (?, ?, NOW())
    ");

    $stmt->bind_param("is", $id, $cancel_reason);

    if (!$stmt->execute()) {
        die("DB Error: " . $stmt->error);
    }

    $stmt->close();

    /* =========================
       UPDATE STATUS
    ========================= */
    $update = $db->prepare("UPDATE letter_out_pending SET status='cancelled' WHERE id=?");
    $update->bind_param("i", $id);
    $update->execute();
    $update->close();

    $db->close();

    echo "<script>alert('Letter cancelled successfully'); window.location.href='letterout_list.php?project_id={$project_id}';</script>";
    exit;
}
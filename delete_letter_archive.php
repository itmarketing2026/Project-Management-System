<?php
session_start();
require_once 'include/msqli_connect.php';

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    header("Location: login.php");
    exit;
}

/* =========================
   ROLE CHECK
========================= */
$role = strtolower(trim($_SESSION['account_type'] ?? ''));

$stmt_desig = $db->prepare("SELECT designation FROM users WHERE id = ?");
$stmt_desig->bind_param("i", $_SESSION['user_id']);
$stmt_desig->execute();
$designation = trim($stmt_desig->get_result()->fetch_assoc()['designation'] ?? '');
$stmt_desig->close();

$can_delete_archive = in_array($role, ['client admin', 'admin (qa/qc)'])
    || in_array($designation, ['Project Manager', 'Project Engineer']);

if (!$can_delete_archive) {
    die("Access denied");
}

/* =========================
   SAME-ORIGIN REFERER CHECK
   Delete is triggered by a plain GET link (by design), which means
   it could fire from a stray link in an email/chat, browser prefetch,
   or history replay. This is not a full CSRF defence, but it blocks
   the common case: the request must have been navigated to FROM a
   page on this same site (i.e. the "Delete" button itself), not from
   an external referrer or with no referrer at all.
========================= */
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$self_host = $_SERVER['HTTP_HOST'] ?? '';

if (empty($referer) || parse_url($referer, PHP_URL_HOST) !== $self_host) {
    die("Invalid request origin. Please delete this record from within the application.");
}

/* =========================
   GET ID
========================= */
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die("Invalid ID");
}

/* =========================
   GET RECORD
========================= */
$stmt = $db->prepare("
    SELECT project_id, file_path
    FROM letter_out_archive
    WHERE id=?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    die("Record not found");
}

$project_id = $row['project_id'];
$file_raw = $row['file_path'] ?? '';

/* =========================
   DELETE FILE(S)
========================= */
$files = json_decode($file_raw, true);

/* Handle old format */
if (!$files) {
    $file_raw = trim($file_raw, '"');
    $file_raw = stripslashes($file_raw);
    $files = json_decode($file_raw, true);
}

/* MULTIPLE FILES */
if (!empty($files) && is_array($files)) {
    foreach ($files as $file) {
        if (!empty($file)) {
            $local_path = str_replace(
                "https://mggroup.my/projectmanagement/",
                __DIR__ . "/",
                $file
            );
            if (file_exists($local_path)) {
                unlink($local_path);
            }
        }
    }
/* SINGLE FILE */
} elseif (!empty($file_raw)) {
    $local_path = str_replace(
        "https://mggroup.my/projectmanagement/",
        __DIR__ . "/",
        $file_raw
    );
    if (file_exists($local_path)) {
        unlink($local_path);
    }
}

/* =========================
   DELETE RECORD
========================= */
$stmt = $db->prepare("
    DELETE FROM letter_out_archive
    WHERE id=?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

$db->close();

/* =========================
   REDIRECT
========================= */
header("Location: letterout_list.php?project_id=" . $project_id . "&msg=deleted");
exit;
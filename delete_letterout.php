<?php
session_start();
require_once 'include/msqli_connect.php';

/* =========================
   LOGIN CHECK
========================= */
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    header("Location: login.php");
    exit;
}

/* =========================
   ROLE CHECK
========================= */
$user_role = $_SESSION['account_type'] ?? '';

if ($user_role !== 'Client Admin') {
    die("Access denied.");
}

/* =========================
   GET ID
========================= */
$id = intval($_GET['id'] ?? 0);

if (!$id) {
    die("Invalid record ID.");
}

/* =========================
   GET PROJECT ID
========================= */
$stmt = $db->prepare("
    SELECT project_id
    FROM letter_out_pending
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$res = $stmt->get_result();
$data = $res->fetch_assoc();

$stmt->close();

if (!$data) {
    die("Record not found.");
}

$project_id = (int)$data['project_id'];

/* =========================
   DELETE FILES FIRST
========================= */

$stmt = $db->prepare("
    SELECT letter_file
    FROM letter_out_acknowledge
    WHERE letter_out_id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$res = $stmt->get_result();
$row = $res->fetch_assoc();

$stmt->close();

if ($row && !empty($row['letter_file'])) {

    $raw = $row['letter_file'];

    $files = json_decode($raw, true);

    /* MULTIPLE FILES */
    if (is_array($files)) {

        foreach ($files as $file) {

            $filepath = __DIR__ . '/' . ltrim($file, '/');

            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }

    } else {

        /* SINGLE FILE */
        $filepath = __DIR__ . '/' . ltrim($raw, '/');

        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
}

/* =========================
   DELETE RELATED TABLES
========================= */

/* ACKNOWLEDGE */
$stmt = $db->prepare("
    DELETE FROM letter_out_acknowledge
    WHERE letter_out_id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

/* CANCEL */
$stmt = $db->prepare("
    DELETE FROM letter_out_cancel
    WHERE letter_out_id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

/* =========================
   DELETE MAIN RECORD
========================= */

$stmt = $db->prepare("
    DELETE FROM letter_out_pending
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

$db->close();

/* =========================
   REDIRECT
========================= */

header("Location: letterout_list.php?project_id=" . $project_id . "&deleted=1");
exit;
?>
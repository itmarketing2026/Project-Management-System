<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'include/msqli_connect.php';

// ✅ Session check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ✅ Request check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

// ---------- GET DATA ----------
$cerun_id        = intval($_POST['cerun_id'] ?? 0);
$type            = $_POST['type'] ?? '';
$title           = trim($_POST['title'] ?? '');
$lot             = trim($_POST['lot'] ?? '');
$ref_number      = trim($_POST['ref_number'] ?? '');
$letter_date     = $_POST['letter_date'] ?? '';
$receive_date    = $_POST['receive_date'] ?? '';
$receive_through = $_POST['receive_through'] ?? '';
$agency = $_POST['letter_from'] ?? '';

if ($agency === 'OTHER') {

    $new_agency = trim($_POST['new_agency'] ?? '');

    if (empty($new_agency)) {
        die("Please enter new agency name.");
    }

    // ✅ Check duplicate dulu
    $check = $db->prepare("SELECT id FROM cerun_agency WHERE agency_name = ? AND cerun_id = ?");
    $check->bind_param("si", $new_agency, $cerun_id);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows == 0) {
        // ✅ Insert agency baru
        $stmtA = $db->prepare("INSERT INTO cerun_agency (cerun_id, agency_name) VALUES (?, ?)");
        $stmtA->bind_param("is", $cerun_id, $new_agency);
        $stmtA->execute();
        $stmtA->close();
    }

    // 🔥 guna value baru untuk letter
    $agency = $new_agency;
}
$verifier        = $_POST['verifier'] ?? '';
$remarks         = trim($_POST['remarks'] ?? '');
$created_by = $_SESSION['user_id'];

// 🔥 FIX ENUM ('4 Year' → '4')
$type = substr($type, 0, 1);

// ---------- VALIDATION ----------
if (!$cerun_id || !$type || !$title || !$letter_date || !$receive_date || !$receive_through || !$agency) {
    die("Missing required fields.");
}

// ---------- FILE UPLOAD ----------
$file_paths = [];
$upload_dir = __DIR__ . "/uploads/cerun_letters/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if (!empty($_FILES['document']['name'][0])) {

    foreach ($_FILES['document']['error'] as $key => $error) {

        if ($error === UPLOAD_ERR_OK) {

            $original_name = basename($_FILES['document']['name'][$key]);
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

            $new_name = "cerun_" . time() . "_" . rand(1000,9999) . "." . $ext;
            $target = $upload_dir . $new_name;

            if (move_uploaded_file($_FILES['document']['tmp_name'][$key], $target)) {
                $file_paths[] = "uploads/cerun_letters/" . $new_name;
            } else {
                die("Failed to upload file: " . $original_name);
            }
        }
    }

} else {
    die("Please upload at least one document.");
}

$file_path_json = json_encode($file_paths);

$status = 'pending';

if (!empty($receive_date)) {

    $received = strtotime($receive_date);
    $cutoff   = strtotime('2026-04-13');

    if ($received < $cutoff) {
        $status = 'verified';
    }

}

// ---------- INSERT DATABASE ----------
$sql = "INSERT INTO letterin_cerun 
(cerun_id, type, lot, title, status, agency, receive_date, ref_number, letter_date, letter_from, receive_through, verifier, remarks, file_path, created_by) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $db->prepare($sql);

if (!$stmt) {
    die("SQL Error: " . $db->error);
}

$stmt->bind_param(
"isssssssssssssi",
	$cerun_id,
    $type,
    $lot,
    $title,
	$status,
    $agency,
    $receive_date,
    $ref_number,
    $letter_date,
    $agency, // letter_from (same value)
    $receive_through,
    $verifier,
    $remarks,
    $file_path_json,
	$created_by

);

// ---------- EXECUTE ----------
if ($stmt->execute()) {

    header("Location: cerun.php?cerun_id={$cerun_id}&type={$type}&letter=in&success=1");
    exit;

} else {
    die("Insert Error: " . $stmt->error);
}

// ---------- CLOSE ----------
$stmt->close();
$db->close();
?>
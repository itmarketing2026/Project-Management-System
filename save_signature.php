<?php
require_once 'include/msqli_connect.php';
session_start();

$id        = intval($_POST['id'] ?? 0);
$sign_name = $_POST['sign_name'] ?? '';
$use_saved = !empty($_POST['use_saved']);
$user_id   = $_SESSION['user_id'];

if (!$id) die("Invalid data");

// Fetch user data
$stmt_user = $db->prepare("SELECT signature, full_name FROM users WHERE id=?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();

$folder = "signature/";
if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}

if ($use_saved) {
    // use existing saved signature path
    $filename = $user_data['signature'];

} else {
    $signature = $_POST['signature'] ?? '';
    if (!$signature) die("Invalid data");

    $filename = $folder . "sign_" . time() . ".png";
    $img = str_replace('data:image/png;base64,', '', $signature);
    $img = str_replace(' ', '+', $img);
    file_put_contents($filename, base64_decode($img));

    // old signature path before updating
    $old_signature = $user_data['signature'];

    // update users table with new signature
    $stmt = $db->prepare("UPDATE users SET signature=? WHERE id=?");
    $stmt->bind_param("si", $filename, $user_id);
    $stmt->execute();

    // update old reports that had the old signature
    if (!empty($old_signature)) {
        $stmt3 = $db->prepare("UPDATE weekly_reports SET signature=? WHERE signature=?");
        $stmt3->bind_param("ss", $filename, $old_signature);
        $stmt3->execute();
    }
}

// save to current report
$stmt2 = $db->prepare("UPDATE weekly_reports SET signature=?, sign_name=? WHERE id=?");
$stmt2->bind_param("ssi", $filename, $sign_name, $id);
$stmt2->execute();

echo "success";
<?php
require_once 'include/msqli_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$id = $_GET['id'] ?? null;
if (!$id) die("User ID is missing");

$id = (int) $id;

$db->query("DELETE FROM user_project WHERE user_id = $id");
$db->query("DELETE FROM user_cerun WHERE user_id = $id");
$db->query("DELETE FROM verifier_project WHERE user_id = $id");
$db->query("DELETE FROM verifier_cerun WHERE user_id = $id");

$stmt = $db->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: userlist.php");
    exit;
} else {
    echo "❌ Error deleting user: " . $stmt->error;
}
?>
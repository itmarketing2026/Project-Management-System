<?php
session_start();
require_once 'include/msqli_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$user_role  = strtolower(trim($_SESSION['account_type'] ?? ''));
$project_id = (int)($_GET['project_id'] ?? 0);

if ($project_id <= 0) {
    die("Invalid project.");
}

/* ROLE */
$is_verifier = ($user_role == 'verifier');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Project Management System</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.workspace {
    width: 100%;
    max-width: 1200px;
    background: #fff;
    border: 3px solid #007bff;
    border-radius: 20px;
    padding: 30px;
    margin-top: 20px;
}
</style>
</head>

<body>

<?php include 'include/topbarstaff.php'; ?>

<div class="workspace container">

    <h3 class="fw-bold mb-4">📊 Dashboard Overview</h3>

</div>

</body>
</html>
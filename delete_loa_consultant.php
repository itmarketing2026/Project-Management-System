<?php
session_start();

require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* =========================================
ONLY CLIENT ADMIN & QAQC
========================================= */

$user_role =
    strtolower($_SESSION['account_type'] ?? '');

if(
    $user_role != 'client admin' &&
    $user_role != 'admin(qa/qc)'
){
    die("Access Denied.");
}

$project_id =
    intval($_GET['project_id'] ?? 0);

$loa_consultant_id =
    intval($_GET['loa_consultant_id'] ?? 0);

if(!$project_id || !$loa_consultant_id){
    die("Invalid Request.");
}

/* =========================================
GET FILE
========================================= */

$stmt = $db->prepare("
    SELECT attachment
    FROM loa_consultant
    WHERE id = ?
");

$stmt->bind_param("i", $loa_consultant_id);

$stmt->execute();

$result = $stmt->get_result();

if($result->num_rows > 0){

    $row = $result->fetch_assoc();

    if(!empty($row['attachment'])){

        $file_path =
            __DIR__ .
            "/uploads/project_" .
            $project_id .
            "/loa_consultant/" .
            $row['attachment'];

        if(file_exists($file_path)){
            unlink($file_path);
        }
    }
}

/* =========================================
DELETE LOA
========================================= */

$stmt = $db->prepare("
    DELETE FROM loa_consultant
    WHERE id = ?
");

$stmt->bind_param("i", $loa_consultant_id);

$stmt->execute();

header(
    "Location: loa_list.php?project_id=" .
    $project_id
);

exit();
?>
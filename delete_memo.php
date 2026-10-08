<?php

session_start();

require_once 'include/msqli_connect.php';

$role = strtolower(
    trim($_SESSION['account_type'] ?? '')
);

if (!in_array($role, [
    'client admin',
    'admin (qa/qc)'
])) {
    die("Access denied.");
}

$memo_id = intval($_GET['memo_id'] ?? 0);

$project_id = intval($_GET['project_id'] ?? 0);

if (!$memo_id) {
    die("Invalid memo.");
}

/* =========================================
GET FILE
========================================= */

$stmt = $db->prepare("
    SELECT file_path
    FROM memo
    WHERE id = ?
");

$stmt->bind_param("i", $memo_id);

$stmt->execute();

$data = $stmt
    ->get_result()
    ->fetch_assoc();

/* =========================================
DELETE FILE
========================================= */

if (!empty($data['file_path'])) {

    $files = json_decode(
        $data['file_path'],
        true
    );

    if (is_array($files)) {

        foreach($files as $f){

            $path = __DIR__ . "/" . $f;

            if(file_exists($path)){
                unlink($path);
            }
        }
    }
}

/* =========================================
DELETE DB
========================================= */

$del = $db->prepare("
    DELETE FROM memo
    WHERE id = ?
");

$del->bind_param(
    "i",
    $memo_id
);

$del->execute();

/* =========================================
REDIRECT
========================================= */

header(
    "Location: memo.php?project_id="
    . $project_id
);

exit();
?>
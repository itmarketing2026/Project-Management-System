<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid Request");
}

/* =========================================
GET DATA
========================================= */

$project_id = intval($_POST['project_id'] ?? 0);

$title      = trim($_POST['title'] ?? '');

$memo_date  = $_POST['memo_date'] ?? '';

$remarks    = trim($_POST['remarks'] ?? '');

$created_by = $_SESSION['user_id'];

$status     = 'active';

$created_at = date('Y-m-d H:i:s');

if (!$project_id || !$title || !$memo_date) {
    die("Missing required fields.");
}

/* =========================================
UPLOAD
========================================= */

$upload_dir =
    __DIR__ . "/uploads/memo/";

if (!is_dir($upload_dir)) {

    mkdir(
        $upload_dir,
        0777,
        true
    );
}

$file_paths = [];

if (!empty($_FILES['document']['name'][0])) {

    foreach (
        $_FILES['document']['error']
        as $key => $error
    ) {

        if ($error === UPLOAD_ERR_OK) {

            $original_name = basename(
                $_FILES['document']['name'][$key]
            );

            $new_name =
                time()
                . "_"
                . $original_name;

            $target =
                $upload_dir
                . $new_name;

            if (
                move_uploaded_file(
                    $_FILES['document']['tmp_name'][$key],
                    $target
                )
            ) {

                $file_paths[] =
                    "uploads/memo/"
                    . $new_name;
            }
        }
    }
}

$file_json = json_encode($file_paths);

/* =========================================
INSERT
========================================= */

$stmt = $db->prepare("
    INSERT INTO memo (

        project_id,
        title,
        memo_date,
        remarks,
        file_path,
        status,
        created_by,
        created_at

    ) VALUES (

        ?,?,?,?,?,?,?,?

    )
");

$stmt->bind_param(

    "isssssis",

    $project_id,
    $title,
    $memo_date,
    $remarks,
    $file_json,
    $status,
    $created_by,
    $created_at
);

if ($stmt->execute()) {

    header(
        "Location: memo.php?project_id="
        . $project_id
    );

    exit();

} else {

    die(
        "Insert Error: "
        . $stmt->error
    );
}

$stmt->close();

$db->close();

?>
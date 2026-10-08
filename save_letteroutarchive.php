<?php
session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method.');
}

// ---------- ERROR PAGE FUNCTION ----------
function showError($message, $back_url = null) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Upload Error</title>
        <style>
            body { font-family: Arial; background: #f3f4f6; display:flex; justify-content:center; align-items:center; height:100vh; }
            .box { background:#fff; padding:40px; border-radius:20px; border:2px solid red; text-align:center; }
        </style>
    </head>
    <body>
        <div class="box">
            <h2>Upload Failed</h2>
            <p><?= $message ?></p>
            <a href="<?= $back_url ?? 'javascript:history.back()' ?>">← Back</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ---------- CHECK EMPTY POST ----------
if (empty($_POST)) {
    showError("File too large.");
}

// ---------- COLLECT DATA ----------
$project_id     = (int) ($_POST['project_id'] ?? 0);
$form_id        = $_POST['form_id'] ?? '';
$running_number = $_POST['running_number'] ?? '';
$created_by     = $_POST['created_by'] ?? '';
$request_date   = $_POST['request_date'] ?? '';
$title          = $_POST['title'] ?? '';
$agency         = $_POST['agency'] ?? '';
$status         = 'archived';
$created_at     = date('Y-m-d H:i:s');
$action_type = $_POST['action_type'] ?? '';
$action = $_POST['action_type'];
$reason = '';

$agencyFromSelect = trim($_POST['agency_select'] ?? '');

/* SAVE NEW AGENCY (if Other) */
if ($agencyFromSelect === '__other__' && $agency !== '') {

    $checkStmt = $db->prepare("
        SELECT id 
        FROM project_agency
        WHERE project_id = ? 
        AND agency_name = ?
        LIMIT 1
    ");

    $checkStmt->bind_param(
        "is",
        $project_id,
        $agency
    );

    $checkStmt->execute();
    $checkStmt->store_result();

    // kalau belum ada → insert
    if ($checkStmt->num_rows === 0) {

        $insertAgency = $db->prepare("
            INSERT INTO project_agency
            (project_id, agency_name)
            VALUES (?, ?)
        ");

        $insertAgency->bind_param(
            "is",
            $project_id,
            $agency
        );

        $insertAgency->execute();
        $insertAgency->close();
    }

    $checkStmt->close();
}


if ($action === 'cancel') {
    $reason = $_POST['cancel_reason'] ?? '';
}
$back_url = "javascript:history.back()";

// ---------- VALIDATION ----------
$required = ['project_id', 'form_id', 'running_number', 'created_by', 'request_date', 'title', 'agency'];

foreach ($required as $field) {
    if (empty($_POST[$field])) {
        showError("Missing required field: <strong>{$field}</strong>", $back_url);
    }
}

// ---------- OPTIONAL: GET project_name FOR FILE NAME ----------
$stmtP = $db->prepare("SELECT project_name FROM project WHERE id = ?");
$stmtP->bind_param("i", $project_id);
$stmtP->execute();
$project_name = $stmtP->get_result()->fetch_assoc()['project_name'] ?? 'project';

// ---------- FILE UPLOAD ----------
$file_paths = [];

if ($action_type === 'acknowledge') {

    // 👉 WAJIB ADA FILE
    if (empty($_FILES['archive_file']['name'][0])) {
        showError("Please upload file (Acknowledge).");
    }

// upload logic
$upload_dir = __DIR__ . "/uploads/" . $project_id . "/archive/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

foreach ($_FILES['archive_file']['tmp_name'] as $i => $tmp) {

    if ($_FILES['archive_file']['error'][$i] === 0) {

        $ext = strtolower(pathinfo(
            $_FILES['archive_file']['name'][$i],
            PATHINFO_EXTENSION
        ));

        $project = preg_replace(
            '/[^A-Za-z0-9_-]/',
            '_',
            $project_name
        );

        $original_name = pathinfo(
            $_FILES['archive_file']['name'][$i],
            PATHINFO_FILENAME
        );

        $original_name = preg_replace(
            '/[^A-Za-z0-9_-]/',
            '_',
            $original_name
        );

        $new_name = $project . "_" . date('YmdHis') . "_" . $original_name . "." . $ext;

        $target = $upload_dir . $new_name;

        move_uploaded_file($tmp, $target);

        $file_paths[] = "https://mggroup.my/projectmanagement/uploads/" 
            . $project_id . "/archive/" . $new_name;
    }
}

} else {
    // 👉 CANCEL → TAK PAYAH FILE
    $file_paths = [];
}

$file_path = json_encode($file_paths);

// ---------- INSERT ----------
$sql = "INSERT INTO letter_out_archive
(project_id, form_id, running_number, request_date, title, agency, status, action_type, created_by, file_path, cancel_reason, created_at)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $db->prepare($sql);

$stmt->bind_param(
    "isssssssssss",
    $project_id,
    $form_id,
    $running_number,
    $request_date,
    $title,
    $agency,
    $status,
    $action_type, // 🔥 tambah sini
    $created_by,
    $file_path,
    $reason,
    $created_at
);
if ($stmt->execute()) {
    header("Location: letterout_list.php?project_id={$project_id}&success=1");
    exit;
} else {
    showError($stmt->error);
}

$stmt->close();
$db->close();
?>
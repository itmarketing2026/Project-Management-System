<?php
session_start();

require_once 'include/msqli_connect.php';

$role = strtolower(
    trim($_SESSION['account_type'] ?? '')
);

$can_delete = in_array($role, [
    'client admin',
    'admin (qa/qc)'
]);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$project_id = intval($_GET['project_id'] ?? 0);
$memo_id = intval($_GET['memo_id'] ?? 0);

if (!$project_id || !$memo_id) {
    die("Invalid Request");
}

/* =========================================
GET MEMO
========================================= */

$stmt = $db->prepare("
    SELECT *
    FROM memo
    WHERE id = ?
    AND project_id = ?
");

$stmt->bind_param(
    "ii",
    $memo_id,
    $project_id
);

$stmt->execute();

$result = $stmt->get_result();

$data = $result->fetch_assoc();

if (!$data) {
    die("Memo not found.");
}

/* =========================================
FILES
========================================= */

$files = json_decode(
    $data['file_path'],
    true
);

if (!is_array($files)) {
    $files = [];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1"
>

<title>View Memo</title>

<style>

*{
    box-sizing:border-box;
}

body{
    margin:0;
    padding:30px;
    background:#f3f4f6;
    font-family:Arial,sans-serif;
}

.container{
    width:100%;
    max-width:1000px;
    margin:0 auto;
}

.card{
    background:#fff;
    border:3px solid #007bff;
    border-radius:24px;
    padding:30px;
    box-shadow:0 5px 14px rgba(0,0,0,0.05);
}

h1{
    margin-top:0;
    color:#007bff;
    font-size:40px;
    font-weight:800;
}

.label{
    font-size:13px;
    color:#666;
    margin-bottom:6px;
    font-weight:bold;
}

.value{
    background:#f8f9fa;
    border-radius:12px;
    padding:14px;
    margin-bottom:22px;
    border:1px solid #ddd;
}

.file-box{
    border:1px solid #ddd;
    border-radius:12px;
    padding:14px;
    margin-bottom:12px;
    background:#fafafa;
}

.file-box a{
    text-decoration:none;
    color:#007bff;
    font-weight:bold;
}

.back-btn{
    display:inline-block;
    margin-bottom:20px;
    text-decoration:none;
    background:#007bff;
    color:#fff;
    padding:10px 18px;
    border-radius:10px;
    font-weight:bold;
}

.back-btn:hover{
    background:#0056b3;
}

.empty{
    color:#888;
    font-style:italic;
}

.file-preview-img{
    max-width:100%;
    border-radius:8px;
    border:1px solid #ddd;
    margin-top:10px;
    display:block;
}

.file-preview-iframe{
    width:100%;
    height:600px;
    border:1px solid #ddd;
    border-radius:8px;
    margin-top:10px;
    display:block;
}

@media(max-width:768px){

    body{
        padding:15px;
    }

    h1{
        font-size:30px;
    }

    .card{
        padding:22px;
    }

    .file-preview-iframe{
        height:400px;
    }
}

</style>

</head>

<body>

<div class="container">

<a
href="memo.php?project_id=<?= $project_id ?>"
class="back-btn"
>

← Back

</a>

<div class="card">

<h1>MEMO</h1>

<!-- TITLE -->
<div>

<div class="label">
Title
</div>

<div class="value">

<?= htmlspecialchars($data['title']) ?>

</div>

</div>

<!-- DATE -->
<div>

<div class="label">
Memo Date
</div>

<div class="value">

<?= !empty($data['memo_date'])
? date(
    'd M Y',
    strtotime($data['memo_date'])
)
: '-' ?>

</div>

</div>

<!-- REMARK -->
<div>

<div class="label">
Remarks
</div>

<div class="value">

<?= !empty($data['remarks'])
? nl2br(htmlspecialchars($data['remarks']))
: '<span class="empty">No remarks</span>' ?>

</div>

</div>

<!-- FILE -->
<div>

<div class="label">
Document
</div>

<?php if (!empty($files)): ?>

    <?php foreach($files as $f): ?>

    <?php
        /*
         * Keep the original stored relative path.
         *
         * Example:
         * project_5/memo/document.pdf
         *
         * basename() is used ONLY for displaying the filename.
         */
        $f = trim((string)$f);

        $f = str_replace('\\', '/', $f);

        $ext = strtolower(
            pathinfo($f, PATHINFO_EXTENSION)
        );

        $is_image = in_array($ext, [
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'bmp',
            'svg'
        ], true);

        $is_pdf = ($ext === 'pdf');

        // Filename for display only
        $filename = basename($f);

        // Full relative path for download_file.php
        $safeUrl =
            'download_file.php?file=' .
            urlencode($f);
    ?>

    <div class="file-box">

        <a
            href="<?= htmlspecialchars($safeUrl) ?>"
            target="_blank"
        >
            📄 <?= htmlspecialchars($filename) ?>
        </a>

        <?php if ($is_pdf): ?>

        <iframe
            src="<?= htmlspecialchars($safeUrl) ?>"
            class="file-preview-iframe"
        ></iframe>

        <?php elseif ($is_image): ?>

        <img
            src="<?= htmlspecialchars($safeUrl) ?>"
            alt="<?= htmlspecialchars($filename) ?>"
            class="file-preview-img"
        >

        <?php endif; ?>

    </div>

    <?php endforeach; ?>

<?php else: ?>

<div class="value empty">

No document uploaded

</div>

<?php endif; ?>

</div>

</div>

<?php if ($can_delete): ?>

<div style="margin-top:20px;">

    <button
        type="button"
        onclick="confirmDelete()"
        style="
            background:#dc3545;
            color:white;
            border:none;
            padding:10px 18px;
            border-radius:10px;
            font-weight:bold;
            cursor:pointer;
        "
    >
        🗑 Delete Memo

    </button>

</div>

<?php endif; ?>

</div>

<script>

function confirmDelete(){

    if(
        confirm(
            'Are you sure want to delete this memo?'
        )
    ){

        window.location.href =
            'delete_memo.php?memo_id=<?= $memo_id ?>&project_id=<?= $project_id ?>';
    }
}

</script>

</body>

</html>

<?php

$stmt->close();

$db->close();

?>
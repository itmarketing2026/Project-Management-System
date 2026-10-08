<?php
session_start();

$title = "Add Memo";

require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$project_id = intval($_GET['project_id'] ?? 0);

if (!$project_id) {
    die("Invalid Project.");
}

if (!user_can_access_project($db, $_SESSION['user_id'], $project_id, $_SESSION['account_type'] ?? '')) {
    die("You do not have permission to view this project.");
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

<title>Project Management System</title>

<style>

:root{
    --accent:#007bff;
    --max:850px;
}

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
    max-width:var(--max);
    margin:0 auto;
}

h1{
    color:var(--accent);
    text-align:center;
    font-size:42px;
    margin-bottom:30px;
    font-weight:800;
}

.card{
    background:#fff;
    border:3px solid var(--accent);
    border-radius:24px;
    padding:30px;
    box-shadow:0 5px 14px rgba(0,0,0,0.05);
}

.form-group{
    margin-bottom:22px;
}

label{
    display:block;
    margin-bottom:8px;
    font-weight:700;
    color:#333;
}

.input,
.textarea,
.file-input{
    width:100%;
    padding:14px;
    border:2px solid #007bff;
    border-radius:12px;
    font-size:15px;
}

.textarea{
    min-height:140px;
    resize:vertical;
}

.btn{
    width:100%;
    background:#007bff;
    color:#fff;
    border:none;
    padding:16px;
    border-radius:14px;
    font-size:18px;
    font-weight:700;
    cursor:pointer;
    transition:.2s;
}

.btn:hover{
    background:#0056b3;
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

small{
    color:#666;
}

@media(max-width:768px){

    body{
        padding:15px;
    }

    h1{
        font-size:32px;
    }

    .card{
        padding:22px;
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

<h1>Add Memo</h1>

<div class="card">

<form
action="save_memo.php"
method="POST"
enctype="multipart/form-data"
>

<input
type="hidden"
name="project_id"
value="<?= $project_id ?>"
>

<div class="form-group">

<label>Memo Title</label>

<input
type="text"
name="title"
class="input"
placeholder="Enter memo title"
required
>

</div>

<div class="form-group">

<label>Memo Date</label>

<input
type="date"
name="memo_date"
class="input"
required
>

</div>

<div class="form-group">

<label>Remarks</label>

<textarea
name="remarks"
class="textarea"
placeholder="Enter remarks..."
></textarea>

</div>

<div class="form-group">

<label>Upload Document</label>

<input
type="file"
name="document[]"
class="file-input"
multiple
required
>

<small>
You can upload multiple documents
</small>

</div>

<button
type="submit"
class="btn"
>

💾 Save Memo

</button>

</form>

</div>

</div>

</body>

</html>
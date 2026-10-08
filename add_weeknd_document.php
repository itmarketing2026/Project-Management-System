<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Add Document - The Weeknd</title>

<style>
:root {
    --accent: #007bff;
    --frame-max: 850px;
}
* { box-sizing: border-box; }

body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: #f3f4f6;
    font-family: Arial, sans-serif;
    padding: 24px;
}

.top-bar {
    width: 100%;
    max-width: var(--frame-max);
    display: flex;
    justify-content: flex-start;
    margin-bottom: 30px;
}

.back-btn {
    background: var(--accent);
    color: #fff;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
}

h1 {
    margin: 0 0 30px;
    font-size: 36px;
    font-weight: 800;
    color: var(--accent);
    text-align: center;
}

.panel {
    width: 100%;
    max-width: var(--frame-max);
    background: #fff;
    border: 2px solid var(--accent);
    border-radius: 18px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    padding: 40px;
}

label {
    display: block;
    font-weight: bold;
    font-size: 15px;
    margin-bottom: 8px;
    color: #222;
}

select, input[type="text"], input[type="date"] {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid var(--accent);
    border-radius: 10px;
    font-size: 15px;
    margin-bottom: 24px;
    background: #fff;
    color: #222;
}

select:focus, input:focus {
    outline: none;
    border-color: #0056b3;
}

.file-input-wrapper {
    border: 2px solid var(--accent);
    border-radius: 10px;
    padding: 10px 14px;
    margin-bottom: 6px;
}

.file-input-wrapper input[type="file"] {
    border: none;
    padding: 0;
    font-size: 14px;
}

.file-hint {
    font-size: 13px;
    color: #666;
    margin-bottom: 24px;
}

.submit-btn {
    width: 100%;
    padding: 14px;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 17px;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.2s;
}

.submit-btn:hover {
    background: #0056b3;
}
</style>
</head>

<body>

<div class="top-bar">
    <a href="weeknd.php" class="back-btn">← Back</a>
</div>

<h1>THE WEEKND</h1>

<div class="panel">
    <form action="add_weeknd_document_submit.php" method="POST" enctype="multipart/form-data">

        <label for="title">Title</label>
        <input type="text" name="title" id="title" required>

        <label for="date">Date</label>
        <input type="date" name="date" id="date" required>

        <label for="document">Upload Document</label>
        <div class="file-input-wrapper">
            <input type="file" name="document" id="document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
        </div>
        <div class="file-hint">Accepted: PDF, DOC, DOCX, JPG, PNG. Max size: 160M</div>

        <button type="submit" class="submit-btn">Submit</button>
    </form>
</div>

</body>
</html>
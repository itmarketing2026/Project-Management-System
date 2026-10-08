<?php

session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$project_id = intval($_GET['project_id'] ?? 0);

if (!$project_id) {
    die("Invalid project.");
}

/* =========================================
GET EXISTING WEEK
========================================= */

$stmt = $db->prepare("
    SELECT DISTINCT report_date
    FROM weekly_reports
    WHERE project_id = ?
    AND report_date IS NOT NULL
");

$stmt->bind_param("i", $project_id);

$stmt->execute();

$result = $stmt->get_result();

$existing = [];

while ($r = $result->fetch_assoc()) {

    if (!empty($r['report_date'])) {

        $existing[] = date(
            'Y-m-d',
            strtotime($r['report_date'])
        );
    }
}

/* =========================================
GENERATE MISSING WEEK
========================================= */

$weeks = [];

/* 🔥 START SECOND THURSDAY JAN 2026 */
$start = strtotime('second thursday of january 2026');

/* CURRENT WEEK */
$today = strtotime('thursday this week');

while ($start <= $today) {

    $week_date = date('Y-m-d', $start);

    /* ONLY SHOW MISSING WEEK */
    if (!in_array($week_date, $existing)) {

        $weeks[] = $week_date;
    }

    /* NEXT WEEK */
    $start = strtotime('+1 week', $start);
}

?>

<!DOCTYPE html>
<html>

<head>

<title>Add Previous Week</title>

<style>

body{
    font-family:Arial,sans-serif;
    background:#f3f4f6;
    padding:30px;
}

.card{
    max-width:600px;
    margin:auto;
    background:#fff;
    padding:30px;
    border-radius:20px;
    border:3px solid #007bff;
    box-shadow:0 4px 10px rgba(0,0,0,0.05);
}

h2{
    margin-top:0;
    color:#007bff;
}

select,
button{
    width:100%;
    padding:14px;
    border-radius:10px;
    margin-top:15px;
    font-size:15px;
}

select{
    border:1px solid #ccc;
}

button{
    background:#007bff;
    color:#fff;
    border:none;
    font-weight:bold;
    cursor:pointer;
}

button:hover{
    background:#0056b3;
}

</style>

</head>

<body>

<a
    href="wr.php?project_id=<?= $project_id ?>"
    style="
        display:inline-block;
        margin-bottom:20px;
        text-decoration:none;
        background:#007bff;
        color:white;
        padding:10px 18px;
        border-radius:10px;
        font-weight:bold;
    "
>

    ← Back

</a>

<div class="card">

<h2>Select Missing Week</h2>

<form action="weeklyreport.php" method="GET">

<input
    type="hidden"
    name="project_id"
    value="<?= $project_id ?>"
>

<select name="report_date" required>

<option value="">
    -- Select Week --
</option>

<?php foreach($weeks as $w): ?>

<option value="<?= $w ?>">

    Week:
    <?= date('d M Y', strtotime('-6 days', strtotime($w))) ?>

    -

    <?= date(
        'd M Y',
        strtotime($w)
    ) ?>

</option>

<?php endforeach; ?>

</select>

<button type="submit">

Continue

</button>

</form>

</div>

</body>

</html>
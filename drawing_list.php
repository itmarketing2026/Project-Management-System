<?php
session_start();

$title = "Drawing";

require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$project_id = intval($_GET['project_id'] ?? 0);

if (!$project_id) {
    die("Invalid Project.");
}

$query = "
SELECT *
FROM drawing
WHERE project_id = ?
ORDER BY created_at DESC
";

$stmt = $db->prepare($query);

$stmt->bind_param("i", $project_id);

$stmt->execute();

$result = $stmt->get_result();

$total = $result->num_rows;

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

*{
    box-sizing:border-box;
}

body{
    font-family:Arial,sans-serif;
    background:#f3f4f6;
    margin:0;
    padding:30px;
}

.main-container{
    width:100%;
    max-width:1400px;
    margin:0 auto;
}

h1{
    color:#007bff;
    font-size:48px;
    font-weight:800;
    text-align:center;
    margin-bottom:30px;
}

.section{
    background:#fff;
    border:3px solid #007bff;
    border-radius:24px;
    padding:24px;
    margin-bottom:35px;
    box-shadow:0 4px 10px rgba(0,0,0,0.05);
}

.section h2{
    color:#007bff;
    margin-top:0;
    margin-bottom:20px;
    font-size:22px;
}

.top-action{
    display:flex;
    gap:14px;
    align-items:center;
    margin-bottom:20px;
    flex-wrap:wrap;
}

.btn{
    background:#007bff;
    color:white;
    padding:12px 20px;
    border-radius:10px;
    text-decoration:none;
    font-weight:bold;
    border:none;
    cursor:pointer;
}

.btn:hover{
    background:#0056b3;
}

.search-box{
    padding:12px 16px;
    border-radius:10px;
    border:1px solid #ccc;
    min-width:300px;
}

.table-container{
    border:1px solid #ddd;
    border-radius:18px;
    overflow:hidden;
    background:white;
}

.table-info{
    text-align:right;
    padding:16px;
    color:#666;
    font-size:14px;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#007bff;
    color:white;
    padding:16px;
    text-align:left;
    font-size:15px;
}

td{
    padding:16px;
    border-bottom:1px solid #eee;
    font-size:14px;
}

tr:hover{
    background:#eef5ff;
    cursor:pointer;
}

.empty-row{
    text-align:center;
    padding:40px;
    color:#888;
}

@media(max-width:768px){

    body{
        padding:15px;
    }

    h1{
        font-size:36px;
    }

    .top-action{
        flex-direction:column;
        align-items:stretch;
    }

    .search-box{
        width:100%;
        min-width:100%;
    }

    .table-container{
        overflow-x:auto;
    }
}

</style>

</head>

<body>

<?php include 'include/topbarstaff.php'; ?>

<div class="main-container">

<h1>DRAWING</h1>

<div class="section">

<h2>Drawing Records</h2>

<div class="top-action">

<a href="add_drawing.php?project_id=<?= $project_id ?>" class="btn">
+ Add Drawing
</a>

<input
    type="text"
    class="search-box"
    id="searchInput"
    placeholder="Search Drawing..."
>

</div>

<div class="table-container">

<div class="table-info">

<?= $total ?> Record(s)

</div>

<table id="drawingTable">

<thead>

<tr>
    <th>Title</th>
    <th>Drawing No.</th>
    <th>Date</th>
</tr>

</thead>

<tbody>

<?php if ($total > 0): ?>

<?php while($row = $result->fetch_assoc()): ?>

<tr onclick=" window.location='view_drawing.php?project_id=<?= $project_id ?>&drawing_id=<?= $row['id'] ?>' ">

<td>

<strong>
<?= htmlspecialchars($row['title']) ?>
</strong>

</td>

<td>

<?= htmlspecialchars($row['drawing_no']) ?>

</td>

<td>

<?= !empty($row['drawing_date'])
? date('d/m/Y', strtotime($row['drawing_date']))
: '-' ?>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td colspan="3" class="empty-row">

No Drawing Record Found

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

</div>

<script>

const searchInput = document.getElementById("searchInput");

searchInput.addEventListener("keyup", function() {

    const filter = this.value.toLowerCase();

    const rows = document.querySelectorAll("#drawingTable tbody tr");

    rows.forEach(row => {

        const text = row.innerText.toLowerCase();

        row.style.display =
            text.includes(filter)
            ? ""
            : "none";
    });
});

</script>

</body>

</html>

<?php

$stmt->close();
$db->close();

?>
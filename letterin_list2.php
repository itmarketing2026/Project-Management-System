<?php
session_start(); 
$title = "Letters In";

require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$project_id = intval($_GET['project_id'] ?? 0);
if (!$project_id) {
    die("Invalid project selected.");
}

/* ================= CURRENT ================= */
$current_stmt = $db->prepare("
    SELECT * FROM letters_in 
    WHERE project_id = ? 
    AND received_date >= '2026-04-13'
    ORDER BY created_at DESC
");
$current_stmt->bind_param("i", $project_id);
$current_stmt->execute();
$current_result = $current_stmt->get_result();

/* ================= ARCHIVE ================= */
$archive_stmt = $db->prepare("
    SELECT * FROM letters_in 
    WHERE project_id = ? 
    AND received_date < '2026-04-13'
    ORDER BY created_at DESC
");
$archive_stmt->bind_param("i", $project_id);
$archive_stmt->execute();
$archive_result = $archive_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Project Management System</title>

<style>
:root {
    --accent: #007bff;
    --accent-hover: #0056b3;
    --bg: #f3f4f6;
    --success: #28a745;
    --danger: #dc3545;
    --warning: #ffc107;
}

body {
    margin: 0;
    font-family: "Segoe UI", Arial, sans-serif;
    background: var(--bg);
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 24px;
}

h1 {
    color: var(--accent);
    font-size: 32px;
    font-weight: 800;
    margin: 16px 0 28px;
}

.header-controls {
    width: 100%;
    max-width: 1000px;
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}

.search-box {
    flex: 1;
    max-width: 400px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 6px 10px;
}

.search-box input {
    border: none;
    outline: none;
    width: 100%;
    padding: 8px;
}

.add-btn {
    background: var(--accent);
    color: #fff;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
}

.table-container {
    width: 100%;
    max-width: 1000px;
    background: #fff;
    border: 2px solid var(--accent);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 30px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px 15px;
    border: 1px solid #e0e0e0;
}

th {
    background: var(--accent);
    color: #fff;
}

tr:hover {
    background: #eef5ff;
    cursor: pointer;
}

.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: bold;
}

.status-verified { background:#d4edda;color:#28a745; }
.status-rejected { background:#f8d7da;color:#dc3545; }
.status-pending { background:#fff3cd;color:#856404; }
</style>

<script>
function filterTable() {
    const filter = document.getElementById("searchInput").value.toLowerCase();
    const rows = document.querySelectorAll("tbody tr");

    rows.forEach(row => {
        const match = row.innerText.toLowerCase().includes(filter);
        row.style.display = match ? "" : "none";
    });
}
</script>

</head>

<body>

<?php
if ($_SESSION['account_type'] === 'Client Admin') {
    include 'include/topbar.php';
} else {
    include 'include/topbarstaff.php';
}
?>

<h1><?= htmlspecialchars($title) ?></h1>

<div class="header-controls">
    <div class="search-box">
        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search letters...">
    </div>

    <a href="addletterin.php?project_id=<?= $project_id ?>" class="add-btn">
        + Add new letter in
    </a>
</div>

<!-- ================= CURRENT ================= -->
<div class="table-container">
<h3>📌 Current Letters (<?= $current_result->num_rows ?>)</h3>

<table>
<thead>
<tr>
<th>No</th>
<th>Title</th>
<th>Received Date</th>
<th>Letter Date</th>
<th>Remarks</th>
<th>Status</th>
</tr>
</thead>

<tbody>
<?php if ($current_result->num_rows > 0):
$i=1;
while ($row = $current_result->fetch_assoc()):
$status = strtolower($row['status']);
$badge = "status-$status";
?>
<tr onclick="window.location='viewletterin.php?id=<?= $row['id'] ?>&project_id=<?= $project_id ?>'">
<td><?= $i++ ?></td>
<td><?= htmlspecialchars($row['title']) ?></td>
<td><?= date('d/m/Y', strtotime($row['received_date'])) ?></td>
<td><?= date('d/m/Y', strtotime($row['letter_date'])) ?></td>
<td><?= htmlspecialchars($row['remarks']) ?></td>
<td><span class="status-badge <?= $badge ?>"><?= ucfirst($status) ?></span></td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="6">No current data</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- ================= ARCHIVE ================= -->
<div class="table-container">
<h3 style="color:#777;">🗂️ Archive Letters (<?= $archive_result->num_rows ?>)</h3>

<table>
<thead>
<tr>
<th>No</th>
<th>Title</th>
<th>Received Date</th>
<th>Letter Date</th>
<th>Remarks</th>
<th>Status</th>
</tr>
</thead>

<tbody>
<?php if ($archive_result->num_rows > 0):
$i=1;
while ($row = $archive_result->fetch_assoc()):
$status = strtolower($row['status']);
$badge = "status-$status";
?>
<tr style="opacity:0.6;"
onclick="window.location='viewletterin.php?id=<?= $row['id'] ?>&project_id=<?= $project_id ?>'">
<td><?= $i++ ?></td>
<td><?= htmlspecialchars($row['title']) ?></td>
<td><?= date('d/m/Y', strtotime($row['received_date'])) ?></td>
<td><?= date('d/m/Y', strtotime($row['letter_date'])) ?></td>
<td><?= htmlspecialchars($row['remarks']) ?></td>
<td><span class="status-badge <?= $badge ?>"><?= ucfirst($status) ?></span></td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="6">No archive data</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

</body>
</html>

<?php
$current_stmt->close();
$archive_stmt->close();
$db->close();
?>
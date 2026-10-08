<?php
session_start();

$title = "Memo";

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

$query = "
SELECT *
FROM memo
WHERE project_id = ?
ORDER BY created_at DESC
";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();

$memos = [];
while ($row = $result->fetch_assoc()) {
    $memos[] = $row;
}
$stmt->close();

$total = count($memos);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Project Management System</title>

<style>
:root {
    --accent: #007bff;
    --frame-max: 1100px;
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
    margin-bottom: 12px;
}



.header-block {
    text-align: center;
    margin-bottom: 24px;
}

h1 {
    margin: 8px 0 6px;
    font-size: 36px;
    font-weight: 800;
    color: var(--accent);
}

.panel {
    width: 100%;
    max-width: var(--frame-max);
    background: #fff;
    border: 2px solid var(--accent);
    border-radius: 18px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    padding: 30px;
}

.panel-title {
    font-size: 22px;
    font-weight: 800;
    color: var(--accent);
    margin: 0 0 16px;
}

.panel-header {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.search-box {
    flex: 1;
    max-width: 320px;
    position: relative;
}

.search-box input {
    width: 100%;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid #ddd;
    font-size: 14px;
    color: #888;
}

.search-box input:focus {
    outline: none;
    border-color: var(--accent);
    color: #222;
}

.add-btn {
    background: var(--accent);
    color: #fff;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    font-size: 14px;
    white-space: nowrap;
}

.add-btn:hover {
    background: #0056b3;
}

.record-count {
    width: 100%;
    text-align: right;
    color: #888;
    font-size: 14px;
    margin-bottom: 8px;
}

table.doc-table {
    width: 100%;
    border-collapse: collapse;
    border-radius: 10px;
    overflow: hidden;
}

table.doc-table th {
    background: var(--accent);
    color: #fff;
    text-align: left;
    padding: 12px 16px;
    font-size: 14px;
}

table.doc-table th.sortable {
    cursor: pointer;
    user-select: none;
}

table.doc-table th.sortable:hover {
    background: #0056b3;
}

table.doc-table th.sortable .arrow {
    font-size: 11px;
    margin-left: 4px;
    opacity: 0.8;
}

table.doc-table td {
    padding: 12px 16px;
    font-size: 14px;
    border-bottom: 1px solid #eee;
    color: #333;
    vertical-align: middle;
}

table.doc-table tbody tr:last-child td {
    border-bottom: none;
}

table.doc-table tbody tr:hover {
    background: #f8f9fc;
}

table.doc-table tbody tr {
    cursor: pointer;
}

table.doc-table a.doc-link {
    color: #1f1f1f;
    font-weight: normal;
    text-decoration: none;
}

table.doc-table a.doc-link:hover {
    text-decoration: underline;
}

.no-docs {
    text-align: center;
    color: #888;
    padding: 20px;
    font-size: 14px;
}

@media (max-width: 768px) {
    body { padding: 15px; }
    h1 { font-size: 30px; }
    .panel-header { flex-direction: column; align-items: stretch; }
    .search-box { max-width: 100%; }
}
</style>
</head>

<body>

<?php include 'include/topbarstaff.php'; ?>



<div class="header-block">
    <h1>Memo</h1>
</div>

<div class="panel">
    <h2 class="panel-title">Memo Records</h2>
    <div class="panel-header">
        <a href="add_memo.php?project_id=<?= $project_id ?>" class="add-btn">+ Add Memo</a>
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search Memo...">
        </div>
    </div>

    <div class="record-count" id="recordCount"><?= $total ?> Record(s)</div>

    <?php if ($total === 0): ?>
        <div class="no-docs">No Memo Record Found</div>
    <?php else: ?>
        <table class="doc-table" id="docTable">
            <thead>
                <tr>
                    <th style="width:50px;">No</th>
                    <th>Title</th>
                    <th class="sortable" style="width:140px;" id="sortDate">Date <span class="arrow">▼</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($memos as $i => $row): ?>
                    <tr data-title="<?= strtolower(htmlspecialchars($row['title'])) ?>"
                        data-date="<?= htmlspecialchars($row['created_at']) ?>"
                        onclick="window.location='view_memo.php?project_id=<?= $project_id ?>&memo_id=<?= $row['id'] ?>'">
                        <td><?= $i + 1 ?></td>
                        <td>
                            <a class="doc-link" href="view_memo.php?project_id=<?= $project_id ?>&memo_id=<?= $row['id'] ?>">
                                <?= htmlspecialchars($row['title']) ?>
                            </a>
                        </td>
                        <td><?= !empty($row['created_at']) ? date('d/m/Y', strtotime($row['created_at'])) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="no-docs" id="noResults" style="display:none;">No matching memo found.</div>
    <?php endif; ?>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const table = document.getElementById('docTable');
const noResults = document.getElementById('noResults');
const recordCount = document.getElementById('recordCount');

function doSearch() {
    if (!searchInput || !table) return;
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const keyword = searchInput.value.toLowerCase();
    let visibleCount = 0;
    rows.forEach(row => {
        const match = row.dataset.title.includes(keyword);
        row.style.display = match ? '' : 'none';
        if (match) visibleCount++;
    });
    if (noResults) noResults.style.display = visibleCount === 0 ? 'block' : 'none';
    if (recordCount) recordCount.textContent = visibleCount + ' Record(s)';
}

if (searchInput && table) {
    searchInput.addEventListener('keyup', doSearch);
}

let dateSortAsc = false;
const sortDateHeader = document.getElementById('sortDate');
if (sortDateHeader && table) {
    sortDateHeader.addEventListener('click', function () {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));

        rows.sort((a, b) => {
            const dateA = new Date(a.dataset.date);
            const dateB = new Date(b.dataset.date);
            return dateSortAsc ? dateA - dateB : dateB - dateA;
        });

        rows.forEach(row => tbody.appendChild(row));

        dateSortAsc = !dateSortAsc;
        sortDateHeader.querySelector('.arrow').textContent = dateSortAsc ? '▲' : '▼';
    });
}
</script>

</body>
</html>
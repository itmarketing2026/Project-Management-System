<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}

require_once 'include/msqli_connect.php';

$stmt = $db->prepare("
    SELECT id, title, doc_date, file_path
    FROM weeknd_documents
    ORDER BY doc_date DESC
");
$stmt->execute();
$result = $stmt->get_result();

$documents = [];
while ($row = $result->fetch_assoc()) {
    $documents[] = $row;
}
$stmt->close();
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>The Weeknd</title>

<style>
:root {
    --accent: #007bff;
    --frame-max: 1100px;
}
*, *::before, *::after { box-sizing: border-box; }

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

.back-btn {
    background: var(--accent);
    color: #fff;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    white-space: nowrap;
}

.header-block {
    text-align: center;
    margin-bottom: 24px;
}

h1 {
    margin: 8px 0 6px;
    font-size: clamp(24px, 6vw, 36px);
    font-weight: 800;
    color: var(--accent);
}

.subtitle {
    color: #666;
    font-size: 15px;
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
    font-size: clamp(18px, 4vw, 22px);
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
    flex: 1 1 200px;
    max-width: 320px;
    min-width: 0;
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
    flex-shrink: 0;
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

/* ── Desktop table ── */
.table-wrap {
    overflow-x: auto;
}

table.doc-table {
    width: 100%;
    border-collapse: collapse;
    border-radius: 10px;
    overflow: hidden;
    min-width: 480px;
}

table.doc-table th {
    background: var(--accent);
    color: #fff;
    text-align: left;
    padding: 12px 16px;
    font-size: 14px;
    white-space: nowrap;
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

table.doc-table a.doc-link {
    color: #1f1f1f;
    font-weight: normal;
    text-decoration: none;
}

table.doc-table a.doc-link:hover {
    text-decoration: underline;
}

table.doc-table tbody tr {
    cursor: pointer;
}

.no-docs {
    text-align: center;
    color: #888;
    padding: 20px;
    font-size: 14px;
}

/* ── Mobile cards ── */
.card-list { display: none; }

.doc-card {
    border: 1px solid #dde3f0;
    border-radius: 10px;
    padding: 12px 14px;
    cursor: pointer;
    background: #fff;
    transition: box-shadow 0.15s;
    margin-bottom: 10px;
}
.doc-card:hover { box-shadow: 0 2px 8px rgba(0,123,255,.18); }

.doc-card .card-no {
    font-size: 12px;
    color: #888;
    margin-bottom: 4px;
}

.doc-card .card-title {
    font-weight: 700;
    font-size: 14px;
    color: #1f1f1f;
    margin-bottom: 6px;
    line-height: 1.4;
    word-break: break-word;
    overflow-wrap: anywhere;
    white-space: normal;
}

.doc-card .card-date {
    font-size: 12px;
    color: #666;
}

/* ── Responsive breakpoints ── */
@media (max-width: 640px) {
    body { padding: 12px; }

    .panel { padding: 16px; border-radius: 14px; }

    .panel-header { gap: 10px; }

    .search-box { max-width: 100%; }

    .add-btn { width: 100%; text-align: center; }

    .search-box { order: 2; flex: 1 1 100%; }
    .add-btn    { order: 1; flex: 1 1 100%; }

    .table-wrap, table.doc-table { display: none; }
    .card-list { display: block; }
}
</style>
</head>

<body>

<div class="top-bar">
    <a href="legaldashboard.php" class="back-btn">← Back</a>
</div>

<div class="header-block">
    <h1>THE WEEKND</h1>
</div>

<div class="panel">
    <h2 class="panel-title"> Records</h2>
    <div class="panel-header">
        <a href="add_weeknd_document.php" class="add-btn">+ Add New</a>
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search Document...">
        </div>
    </div>

    <div class="record-count" id="recordCount"><?= count($documents) ?> Record(s)</div>

    <?php if (empty($documents)): ?>
        <div class="no-docs">No documents yet.</div>
    <?php else: ?>
        <!-- Desktop table -->
        <div class="table-wrap">
        <table class="doc-table" id="docTable">
            <thead>
                <tr>
                    <th style="width:50px;">No</th>
                    <th>Document</th>
                    <th class="sortable" style="width:140px;" id="sortDate">Date <span class="arrow">▼</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $i => $doc): ?>
                    <tr data-title="<?= strtolower(htmlspecialchars($doc['title'])) ?>" data-date="<?= htmlspecialchars($doc['doc_date']) ?>" onclick="window.location.href='preview_weeknd_document.php?id=<?= $doc['id'] ?>'">
                        <td><?= $i + 1 ?></td>
                        <td>
                            <a class="doc-link" href="preview_weeknd_document.php?id=<?= $doc['id'] ?>">
                                <?= htmlspecialchars($doc['title']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars(date('Y-m-d', strtotime($doc['doc_date']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Mobile cards -->
        <div class="card-list" id="docCardList">
            <?php foreach ($documents as $i => $doc): ?>
                <div class="doc-card"
                     data-title="<?= strtolower(htmlspecialchars($doc['title'])) ?>"
                     data-date="<?= htmlspecialchars($doc['doc_date']) ?>"
                     onclick="window.location.href='preview_weeknd_document.php?id=<?= $doc['id'] ?>'">
                    <div class="card-no">#<?= $i + 1 ?></div>
                    <div class="card-title"><?= htmlspecialchars($doc['title']) ?></div>
                    <div class="card-date">📅 <?= htmlspecialchars(date('Y-m-d', strtotime($doc['doc_date']))) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="no-docs" id="noResults" style="display:none;">No matching documents found.</div>
    <?php endif; ?>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const table = document.getElementById('docTable');
const cardList = document.getElementById('docCardList');
const noResults = document.getElementById('noResults');
const recordCount = document.getElementById('recordCount');

function doSearch() {
    if (!searchInput) return;
    const keyword = searchInput.value.toLowerCase();
    let visibleCount = 0;

    if (table) {
        const rows = Array.from(table.querySelectorAll('tbody tr'));
        rows.forEach(row => {
            const match = row.dataset.title.includes(keyword);
            row.style.display = match ? '' : 'none';
        });
    }

    if (cardList) {
        const cards = Array.from(cardList.querySelectorAll('.doc-card'));
        cards.forEach(card => {
            const match = card.dataset.title.includes(keyword);
            card.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });
    }

    if (noResults) {
        noResults.style.display = visibleCount === 0 ? 'block' : 'none';
    }
    if (recordCount) {
        recordCount.textContent = visibleCount + ' Record(s)';
    }
}

if (searchInput) {
    searchInput.addEventListener('keyup', doSearch);
}

// Sort by date column (desktop table)
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
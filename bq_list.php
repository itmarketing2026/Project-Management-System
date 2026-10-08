<?php
session_start();

$title = "BQ";

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

/* Client */
$stmt_client = $db->prepare("SELECT * FROM bq_client WHERE project_id = ? ORDER BY created_at DESC");
$stmt_client->bind_param("i", $project_id);
$stmt_client->execute();
$result_client = $stmt_client->get_result();
$rows_client   = $result_client->fetch_all(MYSQLI_ASSOC);
$total_client  = count($rows_client);
$stmt_client->close();

/* Subcontractor */
$stmt_sub = $db->prepare("SELECT * FROM bq_subcontractor WHERE project_id = ? ORDER BY created_at DESC");
$stmt_sub->bind_param("i", $project_id);
$stmt_sub->execute();
$result_sub = $stmt_sub->get_result();
$rows_sub   = $result_sub->fetch_all(MYSQLI_ASSOC);
$total_sub  = count($rows_sub);
$stmt_sub->close();
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
    --radius: 16px;
}

*, *::before, *::after { box-sizing: border-box; }

body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: var(--bg);
    margin: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 16px;
}

.main-container {
    width: 100%;
    max-width: 1100px;
}

h1 {
    color: var(--accent);
    font-size: clamp(24px, 6vw, 40px);
    font-weight: 800;
    text-align: center;
    margin: 12px 0 24px;
}

/* ── Section ── */
.section {
    background: #fff;
    border: 2px solid var(--accent);
    border-radius: var(--radius);
    padding: 18px;
    margin-bottom: 28px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}

.section-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}

.section-header h2 {
    color: var(--accent);
    margin: 0;
    font-size: clamp(16px, 4vw, 20px);
    flex: 1 1 100%;
}

.btn {
    background: var(--accent);
    color: #fff;
    padding: 9px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    font-size: 13px;
    white-space: nowrap;
    flex-shrink: 0;
}
.btn:hover { background: var(--accent-hover); }

.search-input {
    flex: 1 1 180px;
    min-width: 0;
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 14px;
}

/* ── Desktop table ── */
.table-wrap {
    overflow-x: auto;
    border: 1px solid #ddd;
    border-radius: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 500px;
}

th, td {
    padding: 12px 14px;
    text-align: left;
    border-bottom: 1px solid #ddd;
    font-size: 14px;
    vertical-align: middle;
}

th {
    background: var(--accent);
    color: #fff;
    white-space: nowrap;
}

tr:nth-child(even) { background: #f9f9f9; }
tr:hover { background: #eef5ff; cursor: pointer; }

.table-info {
    font-size: 12px;
    color: #888;
    text-align: right;
    margin-bottom: 6px;
}

.empty-state {
    text-align: center;
    padding: 30px 16px;
    color: #bbb;
    font-size: 14px;
}

/* Mobile cards */
.card-list { display: none; }

.bq-card {
    border: 1px solid #dde3f0;
    border-radius: 10px;
    padding: 12px 14px;
    cursor: pointer;
    background: #fff;
    transition: box-shadow 0.15s;
}
.bq-card:hover { box-shadow: 0 2px 8px rgba(0,123,255,.2); }

.card-name   { font-weight: 700; font-size: 14px; color: #222; margin-bottom: 4px; word-break: break-word; overflow-wrap: anywhere; }
.card-amount { font-size: 13px; color: #333; margin-bottom: 6px; font-weight: 600; }
.card-meta   { display: flex; flex-wrap: wrap; gap: 8px; font-size: 12px; color: #666; }

@media (max-width: 640px) {
    body { padding: 10px; }

    .section { padding: 12px; border-radius: 12px; }

    .section-header h2 { font-size: 16px; }

    .table-wrap { display: none; }
    .card-list  { display: flex; flex-direction: column; gap: 10px; }
}
</style>
</head>
<body>

<?php include 'include/topbarstaff.php'; ?>

<div class="main-container">

    <h1>Bill of Quantities</h1>

    <!-- CLIENT -->
    <div class="section">
        <div class="section-header">
            <h2>Client BQ Records</h2>
            <a href="add_bq_client.php?project_id=<?= $project_id ?>" class="btn">+ Add BQ</a>
            <input type="text" class="search-input" id="searchClient" placeholder="Search Client BQ..." onkeyup="filterSection(this, 'clientTable', 'clientCardList')">
        </div>

        <div class="table-info"><?= $total_client ?> Record(s)</div>

        <!-- Desktop table -->
        <div class="table-wrap">
        <table id="clientTable">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Date</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows_client): foreach ($rows_client as $row): ?>
            <tr class="data-row" onclick="window.location='view_bq_client.php?project_id=<?= $project_id ?>&bq_id=<?= $row['id'] ?>'">
                <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                <td><?= !empty($row['bq_date']) ? date('d/m/Y', strtotime($row['bq_date'])) : '-' ?></td>
                <td>RM <?= number_format($row['amount'], 2) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="3"><div class="empty-state">📭 No Client BQ Record Found</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

        <!-- Mobile cards -->
        <div class="card-list" id="clientCardList">
        <?php if ($rows_client): foreach ($rows_client as $row): ?>
        <div class="bq-card" onclick="window.location='view_bq_client.php?project_id=<?= $project_id ?>&bq_id=<?= $row['id'] ?>'">
            <div class="card-name"><?= htmlspecialchars($row['name']) ?></div>
            <div class="card-amount">RM <?= number_format($row['amount'], 2) ?></div>
            <div class="card-meta">
                <span>📅 <?= !empty($row['bq_date']) ? date('d/m/Y', strtotime($row['bq_date'])) : '-' ?></span>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="empty-state">📭 No Client BQ Record Found</div>
        <?php endif; ?>
        </div>
    </div>

    <!-- SUBCONTRACTOR -->
    <div class="section">
        <div class="section-header">
            <h2>Subcontractor BQ Records</h2>
            <a href="add_bq_subcontractor.php?project_id=<?= $project_id ?>" class="btn">+ Add BQ</a>
            <input type="text" class="search-input" id="searchSub" placeholder="Search Subcontractor BQ..." onkeyup="filterSection(this, 'subTable', 'subCardList')">
        </div>

        <div class="table-info"><?= $total_sub ?> Record(s)</div>

        <!-- Desktop table -->
        <div class="table-wrap">
        <table id="subTable">
            <thead>
                <tr>
                    <th>Subcontractor</th>
                    <th>Date</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows_sub): foreach ($rows_sub as $row): ?>
            <tr class="data-row" onclick="window.location='view_bq_subcontractor.php?project_id=<?= $project_id ?>&bq_id=<?= $row['id'] ?>'">
                <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                <td><?= !empty($row['bq_date']) ? date('d/m/Y', strtotime($row['bq_date'])) : '-' ?></td>
                <td>RM <?= number_format($row['amount'], 2) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="3"><div class="empty-state">📭 No Subcontractor BQ Record Found</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

        <!-- Mobile cards -->
        <div class="card-list" id="subCardList">
        <?php if ($rows_sub): foreach ($rows_sub as $row): ?>
        <div class="bq-card" onclick="window.location='view_bq_subcontractor.php?project_id=<?= $project_id ?>&bq_id=<?= $row['id'] ?>'">
            <div class="card-name"><?= htmlspecialchars($row['name']) ?></div>
            <div class="card-amount">RM <?= number_format($row['amount'], 2) ?></div>
            <div class="card-meta">
                <span>📅 <?= !empty($row['bq_date']) ? date('d/m/Y', strtotime($row['bq_date'])) : '-' ?></span>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="empty-state">📭 No Subcontractor BQ Record Found</div>
        <?php endif; ?>
        </div>
    </div>

</div>

<script>
function filterSection(input, tableId, cardListId) {
    const filter = input.value.toLowerCase();

    const table = document.getElementById(tableId);
    if (table) {
        table.querySelectorAll('tbody tr.data-row').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
        });
    }

    const cardList = document.getElementById(cardListId);
    if (cardList) {
        cardList.querySelectorAll('.bq-card').forEach(card => {
            card.style.display = card.textContent.toLowerCase().includes(filter) ? '' : 'none';
        });
    }
}
</script>

</body>
</html>
<?php $db->close(); ?>
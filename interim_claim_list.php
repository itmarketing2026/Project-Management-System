<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

$title = "Interim Claim";

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

/* ================= Subcontractor Interim Claim ================= */
$stmt_sub = $db->prepare("
    SELECT ic.id, l.subcon_name, ic.ipc_number, ic.b_date, ic.b_certified
    FROM interim_claim ic
    LEFT JOIN loa l ON l.id = ic.loa_id
    WHERE ic.project_id = ? AND ic.loa_id IS NOT NULL
    ORDER BY ic.created_at DESC
");
$stmt_sub->bind_param("i", $project_id);
$stmt_sub->execute();
$rows_sub = $stmt_sub->get_result()->fetch_all(MYSQLI_ASSOC);
$total_sub = count($rows_sub);
$stmt_sub->close();

/* ================= Consultant Interim Claim ================= */
$stmt_con = $db->prepare("
    SELECT ic.id, l.con_name, ic.ipc_number, ic.b_date, ic.b_certified
    FROM interim_claim_consultant ic
    LEFT JOIN loa_consultant l ON l.id = ic.loa_consultant_id
    WHERE ic.project_id = ?
    ORDER BY ic.created_at DESC
");
$stmt_con->bind_param("i", $project_id);
$stmt_con->execute();
$rows_con = $stmt_con->get_result()->fetch_all(MYSQLI_ASSOC);
$total_con = count($rows_con);
$stmt_con->close();

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
    min-width: 600px;
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

/* ── Mobile cards (≤ 640px) ── */
.card-list { display: none; }

.claim-card {
    border: 1px solid #dde3f0;
    border-radius: 10px;
    padding: 12px 14px;
    cursor: pointer;
    background: #fff;
    transition: box-shadow 0.15s;
}
.claim-card:hover { box-shadow: 0 2px 8px rgba(0,123,255,.2); }

.card-name   { font-weight: 700; font-size: 14px; color: #222; margin-bottom: 4px; word-break: break-word; overflow-wrap: anywhere; }
.card-amount { font-size: 13px; color: #333; margin-bottom: 6px; font-weight: 600; }
.card-meta   { display: flex; flex-wrap: wrap; gap: 8px; font-size: 12px; color: #666; margin-bottom: 6px; }

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

    <h1>Interim Claim</h1>

    <?php if (($_GET['msg'] ?? '') === 'saved'): ?>
    <div id="toast" style="position:fixed;top:24px;right:24px;z-index:9999;background:#16a34a;color:#fff;padding:14px 24px;border-radius:12px;font-weight:700;font-size:15px;box-shadow:0 4px 16px rgba(0,0,0,0.15);opacity:1;transition:opacity 0.5s;">
        ✅ Interim Claim saved successfully.
    </div>
    <script>
        setTimeout(() => {
            const t = document.getElementById('toast');
            t.style.opacity = '0';
            setTimeout(() => t.remove(), 500);
        }, 3000);
    </script>
    <?php endif; ?>

    <!-- =====================================================
         SECTION 1 — SUBCONTRACTOR INTERIM CLAIM
         ===================================================== -->
    <div class="section">
        <div class="section-header">
            <h2>Subcontractor Interim Claim Records</h2>
            <a href="add_interim_claim.php?project_id=<?= $project_id ?>" class="btn">+ Add Interim Claim</a>
            <input type="text" class="search-input" id="searchSub" placeholder="Search Interim Claim..." onkeyup="filterSection(this, 'subTable', 'subCardList')">
        </div>

        <div class="table-info"><?= $total_sub ?> Record(s)</div>

        <!-- Desktop table -->
        <div class="table-wrap">
        <table id="subTable">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>IPC Number</th>
                    <th>Subcontractor</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows_sub): foreach ($rows_sub as $row): ?>
            <tr class="data-row" onclick="window.location='view_interim_claim.php?project_id=<?= $project_id ?>&id=<?= $row['id'] ?>'">
                <td><strong><?= !empty($row['b_date']) ? strtoupper(date('F Y', strtotime($row['b_date']))) : '-' ?></strong></td>
                <td><?= htmlspecialchars($row['ipc_number'] ?? '-') ?></td>
                <td>
                    <strong><?= htmlspecialchars($row['subcon_name'] ?? '-') ?></strong><br>
                    <small style="color:#6b7280;">RM <?= number_format($row['b_certified'] ?? 0, 2) ?></small>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="3"><div class="empty-state">📭 No subcontractor interim claims found.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

        <!-- Mobile cards -->
        <div class="card-list" id="subCardList">
        <?php if ($rows_sub): foreach ($rows_sub as $row): ?>
        <div class="claim-card" onclick="window.location='view_interim_claim.php?project_id=<?= $project_id ?>&id=<?= $row['id'] ?>'">
            <div class="card-name"><?= htmlspecialchars($row['subcon_name'] ?? '-') ?></div>
            <div class="card-amount">RM <?= number_format($row['b_certified'] ?? 0, 2) ?></div>
            <div class="card-meta">
                <span>🗓 <?= !empty($row['b_date']) ? strtoupper(date('F Y', strtotime($row['b_date']))) : '-' ?></span>
                <span>📄 IPC: <?= htmlspecialchars($row['ipc_number'] ?? '-') ?></span>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="empty-state">📭 No subcontractor interim claims found.</div>
        <?php endif; ?>
        </div>
    </div>

    <!-- =====================================================
         SECTION 2 — CONSULTANT INTERIM CLAIM
         ===================================================== -->
    <div class="section">
        <div class="section-header">
            <h2>Consultant Interim Claim Records</h2>
            <a href="addcon_interim_claim.php?project_id=<?= $project_id ?>" class="btn">+ Add Interim Claim</a>
            <input type="text" class="search-input" id="searchCon" placeholder="Search Interim Claim..." onkeyup="filterSection(this, 'conTable', 'conCardList')">
        </div>

        <div class="table-info"><?= $total_con ?> Record(s)</div>

        <!-- Desktop table -->
        <div class="table-wrap">
        <table id="conTable">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>IPC Number</th>
                    <th>Consultant</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows_con): foreach ($rows_con as $row): ?>
            <tr class="data-row" onclick="window.location='view_interim_claim_consultant.php?project_id=<?= $project_id ?>&id=<?= $row['id'] ?>'">
                <td><strong><?= !empty($row['b_date']) ? strtoupper(date('F Y', strtotime($row['b_date']))) : '-' ?></strong></td>
                <td><?= htmlspecialchars($row['ipc_number'] ?? '-') ?></td>
                <td>
                    <strong><?= htmlspecialchars($row['con_name'] ?? '-') ?></strong><br>
                    <small style="color:#6b7280;">RM <?= number_format($row['b_certified'] ?? 0, 2) ?></small>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="3"><div class="empty-state">📭 No consultant interim claims found.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

        <!-- Mobile cards -->
        <div class="card-list" id="conCardList">
        <?php if ($rows_con): foreach ($rows_con as $row): ?>
        <div class="claim-card" onclick="window.location='view_interim_claim_consultant.php?project_id=<?= $project_id ?>&id=<?= $row['id'] ?>'">
            <div class="card-name"><?= htmlspecialchars($row['con_name'] ?? '-') ?></div>
            <div class="card-amount">RM <?= number_format($row['b_certified'] ?? 0, 2) ?></div>
            <div class="card-meta">
                <span>🗓 <?= !empty($row['b_date']) ? strtoupper(date('F Y', strtotime($row['b_date']))) : '-' ?></span>
                <span>📄 IPC: <?= htmlspecialchars($row['ipc_number'] ?? '-') ?></span>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="empty-state">📭 No consultant interim claims found.</div>
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
        cardList.querySelectorAll('.claim-card').forEach(card => {
            card.style.display = card.textContent.toLowerCase().includes(filter) ? '' : 'none';
        });
    }
}
</script>

</body>
</html>
<?php
$db->close();
?>
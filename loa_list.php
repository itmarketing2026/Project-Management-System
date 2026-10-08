<?php
session_start();

$title = "LOA";

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

$current_date = new DateTime();

/* calculate balance against completion date */
function calculateLoaBalance($completion_date, DateTime $current_date) {
    if (empty($completion_date)) return ['text' => '-', 'class' => ''];

    $target_date = new DateTime($completion_date);
    $days = (int)$current_date->diff($target_date)->format('%r%a');

    if ($days > 0)   return ['text' => $days . ' days',        'class' => 'balance-positive'];
    if ($days == 0)  return ['text' => 'Today',                 'class' => 'balance-zero'];
    return ['text' => abs($days) . ' overdue', 'class' => 'balance-negative'];
}

/* ================= Subcontractor LOA ================= */
$stmt = $db->prepare("SELECT * FROM loa WHERE project_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$rows_sub = $result->fetch_all(MYSQLI_ASSOC);
$total    = count($rows_sub);
$stmt->close();

/* ================= Consultant LOA ================= */
$stmt_con = $db->prepare("SELECT * FROM loa_consultant WHERE project_id = ? ORDER BY created_at DESC");
$stmt_con->bind_param("i", $project_id);
$stmt_con->execute();
$result_con = $stmt_con->get_result();
$rows_con   = $result_con->fetch_all(MYSQLI_ASSOC);
$total_con  = count($rows_con);
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
    min-width: 800px;
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

.balance-positive { color: #28a745; font-weight: bold; }
.balance-zero     { color: #ffc107; font-weight: bold; }
.balance-negative { color: #dc3545; font-weight: bold; }

.empty-state {
    text-align: center;
    padding: 30px 16px;
    color: #bbb;
    font-size: 14px;
}

/* ── Mobile cards (≤ 640px) ── */
.card-list { display: none; }

.loa-card {
    border: 1px solid #dde3f0;
    border-radius: 10px;
    padding: 12px 14px;
    cursor: pointer;
    background: #fff;
    transition: box-shadow 0.15s;
}
.loa-card:hover { box-shadow: 0 2px 8px rgba(0,123,255,.2); }

.card-name   { font-weight: 700; font-size: 14px; color: #222; margin-bottom: 4px; word-break: break-word; overflow-wrap: anywhere; }
.card-phone  { font-size: 12px; color: #666; margin-bottom: 6px; }
.card-amount { font-size: 13px; color: #333; margin-bottom: 6px; font-weight: 600; }
.card-meta   { display: flex; flex-wrap: wrap; gap: 8px; font-size: 12px; color: #666; margin-bottom: 6px; }
.card-footer { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; }

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

    <h1>Letter Of Award</h1>

    <!-- =====================================================
         SECTION 1 — SUBCONTRACTOR LOA
         ===================================================== -->
    <div class="section">
        <div class="section-header">
            <h2>Subcontractor LOA Records</h2>
            <a href="add_loa.php?project_id=<?= $project_id ?>" class="btn">+ Add LOA</a>
            <input type="text" class="search-input" id="searchSub" placeholder="Search Subcontractor LOA..." onkeyup="filterSection(this, 'subTable', 'subCardList')">
        </div>

        <div class="table-info"><?= $total ?> Record(s)</div>

        <!-- Desktop table -->
        <div class="table-wrap">
        <table id="subTable">
            <thead>
                <tr>
                    <th>Subcontractor</th>
                    <th>Phone</th>
                    <th>Awarded Amount</th>
                    <th>Start Date</th>
                    <th>Completion Date</th>
                    <th>Duration</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows_sub): foreach ($rows_sub as $row):
                $balance = calculateLoaBalance($row['completion_date'], $current_date);
            ?>
            <tr class="data-row" onclick="window.location='view_loa.php?project_id=<?= $project_id ?>&loa_id=<?= $row['id'] ?>'">
                <td><strong><?= htmlspecialchars($row['subcon_name']) ?></strong></td>
                <td><?= htmlspecialchars($row['subcon_phone']) ?></td>
                <td>RM <?= number_format($row['awarded_amount'], 2) ?></td>
                <td><?= !empty($row['start_date']) ? date('d/m/Y', strtotime($row['start_date'])) : '-' ?></td>
                <td><?= !empty($row['completion_date']) ? date('d/m/Y', strtotime($row['completion_date'])) : '-' ?></td>
                <td><?= htmlspecialchars($row['duration_month']) ?> Month(s)</td>
                <td class="<?= $balance['class'] ?>"><?= $balance['text'] ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7"><div class="empty-state">📭 No LOA Record Found</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

        <!-- Mobile cards -->
        <div class="card-list" id="subCardList">
        <?php if ($rows_sub): foreach ($rows_sub as $row):
            $balance = calculateLoaBalance($row['completion_date'], $current_date);
        ?>
        <div class="loa-card" onclick="window.location='view_loa.php?project_id=<?= $project_id ?>&loa_id=<?= $row['id'] ?>'">
            <div class="card-name"><?= htmlspecialchars($row['subcon_name']) ?></div>
            <div class="card-phone">📞 <?= htmlspecialchars($row['subcon_phone']) ?></div>
            <div class="card-amount">RM <?= number_format($row['awarded_amount'], 2) ?></div>
            <div class="card-meta">
                <span>🟢 <?= !empty($row['start_date']) ? date('d/m/Y', strtotime($row['start_date'])) : '-' ?></span>
                <span>🏁 <?= !empty($row['completion_date']) ? date('d/m/Y', strtotime($row['completion_date'])) : '-' ?></span>
                <span>⏱ <?= htmlspecialchars($row['duration_month']) ?> mth</span>
            </div>
            <div class="card-footer">
                <span class="<?= $balance['class'] ?>">Balance: <?= $balance['text'] ?></span>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="empty-state">📭 No LOA Record Found</div>
        <?php endif; ?>
        </div>
    </div>

    <!-- =====================================================
         SECTION 2 — CONSULTANT LOA
         ===================================================== -->
    <div class="section">
        <div class="section-header">
            <h2>Consultant LOA Records</h2>
            <a href="add_loa_consultant.php?project_id=<?= $project_id ?>" class="btn">+ Add LOA</a>
            <input type="text" class="search-input" id="searchCon" placeholder="Search Consultant LOA..." onkeyup="filterSection(this, 'conTable', 'conCardList')">
        </div>

        <div class="table-info"><?= $total_con ?> Record(s)</div>

        <!-- Desktop table -->
        <div class="table-wrap">
        <table id="conTable">
            <thead>
                <tr>
                    <th>Consultant</th>
                    <th>Phone</th>
                    <th>Awarded Amount</th>
                    <th>Start Date</th>
                    <th>Completion Date</th>
                    <th>Duration</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows_con): foreach ($rows_con as $row):
                $balance = calculateLoaBalance($row['completion_date'], $current_date);
            ?>
            <tr class="data-row" onclick="window.location='view_loa_consultant.php?project_id=<?= $project_id ?>&loa_consultant_id=<?= $row['id'] ?>'">
                <td><strong><?= htmlspecialchars($row['con_name']) ?></strong></td>
                <td><?= htmlspecialchars($row['con_phone']) ?></td>
                <td>RM <?= number_format($row['awarded_amount'], 2) ?></td>
                <td><?= !empty($row['start_date']) ? date('d/m/Y', strtotime($row['start_date'])) : '-' ?></td>
                <td><?= !empty($row['completion_date']) ? date('d/m/Y', strtotime($row['completion_date'])) : '-' ?></td>
                <td><?= htmlspecialchars($row['duration_month']) ?> Month(s)</td>
                <td class="<?= $balance['class'] ?>"><?= $balance['text'] ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7"><div class="empty-state">📭 No Consultant LOA Record Found</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

        <!-- Mobile cards -->
        <div class="card-list" id="conCardList">
        <?php if ($rows_con): foreach ($rows_con as $row):
            $balance = calculateLoaBalance($row['completion_date'], $current_date);
        ?>
        <div class="loa-card" onclick="window.location='view_loa_consultant.php?project_id=<?= $project_id ?>&loa_consultant_id=<?= $row['id'] ?>'">
            <div class="card-name"><?= htmlspecialchars($row['con_name']) ?></div>
            <div class="card-phone">📞 <?= htmlspecialchars($row['con_phone']) ?></div>
            <div class="card-amount">RM <?= number_format($row['awarded_amount'], 2) ?></div>
            <div class="card-meta">
                <span>🟢 <?= !empty($row['start_date']) ? date('d/m/Y', strtotime($row['start_date'])) : '-' ?></span>
                <span>🏁 <?= !empty($row['completion_date']) ? date('d/m/Y', strtotime($row['completion_date'])) : '-' ?></span>
                <span>⏱ <?= htmlspecialchars($row['duration_month']) ?> mth</span>
            </div>
            <div class="card-footer">
                <span class="<?= $balance['class'] ?>">Balance: <?= $balance['text'] ?></span>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="empty-state">📭 No Consultant LOA Record Found</div>
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
        cardList.querySelectorAll('.loa-card').forEach(card => {
            card.style.display = card.textContent.toLowerCase().includes(filter) ? '' : 'none';
        });
    }
}
</script>

</body>
</html>
<?php $db->close(); ?>
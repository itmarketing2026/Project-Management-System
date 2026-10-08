<?php
session_start();
$title = "Weekly Report";

require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$project_id = intval($_GET['project_id'] ?? 0);
if (!$project_id) die("Invalid project selected.");

if (!user_can_access_project($db, $_SESSION['user_id'], $project_id, $_SESSION['account_type'] ?? '')) {
    die("You do not have permission to view this project.");
}

/* ================= Determine "+ Weekly Report" link ================= */
$check = $db->prepare("
    SELECT id FROM weekly_reports
    WHERE project_id = ? AND status='draft'
    LIMIT 1
");
$check->bind_param("i", $project_id);
$check->execute();
$res = $check->get_result();

if ($row = $res->fetch_assoc()) {
    $new_report_link = "edit_report.php?id=" . $row['id'];
} else {
    $new_report_link = "weeklyreport.php?project_id=" . $project_id;
}
$check->close();

/* ================= Fetch Weekly Reports ================= */
$stmt = $db->prepare("
    SELECT *
    FROM weekly_reports
    WHERE project_id = ?
    ORDER BY report_date DESC, id DESC
");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result_pending = $stmt->get_result();
$rows_pending   = $result_pending->fetch_all(MYSQLI_ASSOC);
$total_pending  = count($rows_pending);
$stmt->close();

/* Helper: balance class */
function getBalanceClass($balance) {
    if ($balance > 0)  return 'balance-positive';
    if ($balance == 0) return 'balance-zero';
    return 'balance-negative';
}

/* Helper: status badge */
function getReportBadge($status) {
    if ($status === 'draft') {
        return ['class' => 'status-draft', 'text' => '📝 Draft'];
    }
    return ['class' => 'status-submitted', 'text' => 'Submitted'];
}

/* Helper: report link based on status */
function getReportLink($row) {
    return ($row['status'] === 'draft')
        ? "edit_report.php?id=" . $row['id']
        : "view_report_detail.php?id=" . $row['id'];
}

/* Helper: week start/end range */
function getWeekRange($row) {
    $week_start = !empty($row['week_start']) ? $row['week_start'] : $row['report_date'];
    $week_end   = !empty($row['week_end'])
        ? $row['week_end']
        : date('Y-m-d', strtotime('+6 days', strtotime($week_start)));
    return [$week_start, $week_end];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?></title>

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

h1 {
    color: var(--accent);
    font-size: clamp(22px, 5vw, 32px);
    font-weight: 800;
    margin: 12px 0 20px;
    text-align: center;
}

/* ── Section ── */
.section {
    width: 100%;
    max-width: 1100px;
    background: #fff;
    border: 2px solid var(--accent);
    border-radius: var(--radius);
    padding: 18px;
    margin-bottom: 28px;
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
.btn-green { background: #28a745; }
.btn-green:hover { background: #218838; }

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
   
    overflow-y: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
}

th, td {
    padding: 10px 13px;
    text-align: left;
    border-bottom: 1px solid #ddd;
    font-size: 14px;
    vertical-align: middle;
}

th {
    background: var(--accent);
    color: #fff;
    position: sticky;
    top: 0;
    z-index: 2;
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

/* ── Status badges ── */
.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: bold;
    white-space: nowrap;
}
.status-draft     { background: #ffc107; color: #000; }
.status-submitted { background: #007bff; color: #fff; }

/* ── Balance ── */
.balance-positive { color: #28a745; font-weight: bold; }
.balance-zero     { color: #ffc107; font-weight: bold; }
.balance-negative { color: #dc3545; font-weight: bold; }

/* ── Empty state ── */
.empty-state {
    text-align: center;
    padding: 30px 16px;
    color: #bbb;
    font-size: 14px;
}

/* ── Mobile cards (≤ 640px) ── */
.card-list { display: none; }

.report-card {
    border: 1px solid #dde3f0;
    border-radius: 10px;
    padding: 12px 14px;
    cursor: pointer;
    background: #fff;
    transition: box-shadow 0.15s;
}
.report-card:hover { box-shadow: 0 2px 8px rgba(0,123,255,.2); }

.card-week   { font-weight: 700; font-size: 13px; color: var(--accent); margin-bottom: 4px; }
.card-meta   { display: flex; flex-wrap: wrap; gap: 8px; font-size: 12px; color: #666; margin-bottom: 6px; }
.card-footer { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; }

@media (max-width: 640px) {
    body { padding: 10px; }

    .section { padding: 12px; border-radius: 12px; }

    .section-header h2 { font-size: 16px; }

    .section-header .btn { flex: 1 1 100%; text-align: center; }

    .table-wrap { display: none; }
    .card-list  { display: flex; flex-direction: column; gap: 10px; }
}
</style>
</head>

<body>

<?php include 'include/topbarstaff.php'; ?>

<h1><?= htmlspecialchars($title) ?></h1>

<div class="section">
    <div class="section-header">
        <h2>Weekly Report</h2>
        <a href="<?= htmlspecialchars($new_report_link) ?>" class="btn">+ Weekly Report</a>
        <a href="add_previous_week.php?project_id=<?= $project_id ?>" class="btn btn-green">+ Add Previous Week</a>
        <input type="text" class="search-input" placeholder="Search weekly report..." onkeyup="filterSection(this)">
    </div>

    <div class="table-info"><?= $total_pending ?> Record(s)</div>

    <!-- Desktop table -->
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Week</th>
                <th>Date Submission</th>
                <th>Month</th>
                <th>Progress</th>
                <th>Status</th>
                <th>Balance</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows_pending): $i = 1; foreach ($rows_pending as $row):
            $month         = date('F Y', strtotime($row['report_date']));
            $balance_class = getBalanceClass($row['balance']);
            $badge         = getReportBadge($row['status']);
            $link          = getReportLink($row);
            [$week_start, $week_end] = getWeekRange($row);
        ?>
        <tr class="data-row" onclick="window.location='<?= $link ?>'">
            <td><?= $i++ ?></td>
            <td><?= date('d M Y', strtotime($week_start)) ?> - <?= date('d M Y', strtotime($week_end)) ?></td>
            <td><?= date('d M Y', strtotime($row['report_date'])) ?></td>
            <td><?= $month ?></td>
            <td><?= htmlspecialchars($row['physical_progress']) ?>%</td>
            <td><span class="status-badge <?= $badge['class'] ?>"><?= $badge['text'] ?></span></td>
            <td class="<?= $balance_class ?>">RM <?= number_format($row['balance'], 2) ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="7"><div class="empty-state">📭 No weekly report found.</div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Mobile cards -->
    <div class="card-list">
    <?php if ($rows_pending): foreach ($rows_pending as $row):
        $month         = date('F Y', strtotime($row['report_date']));
        $balance_class = getBalanceClass($row['balance']);
        $badge         = getReportBadge($row['status']);
        $link          = getReportLink($row);
        [$week_start, $week_end] = getWeekRange($row);
    ?>
    <div class="report-card" onclick="window.location='<?= $link ?>'">
        <div class="card-week"><?= date('d M Y', strtotime($week_start)) ?> - <?= date('d M Y', strtotime($week_end)) ?></div>
        <div class="card-meta">
            <span>📅 Submitted: <?= date('d M Y', strtotime($row['report_date'])) ?></span>
            <span>🗓 <?= $month ?></span>
            <span>📊 <?= htmlspecialchars($row['physical_progress']) ?>%</span>
        </div>
        <div class="card-footer">
            <span class="status-badge <?= $badge['class'] ?>"><?= $badge['text'] ?></span>
            <span class="<?= $balance_class ?>">RM <?= number_format($row['balance'], 2) ?></span>
        </div>
    </div>
    <?php endforeach; else: ?>
    <div class="empty-state">📭 No weekly report found.</div>
    <?php endif; ?>
    </div>
</div>

<script>
function filterSection(input) {
    const filter = input.value.toLowerCase();
    const section = input.closest('.section');

    const trs = section.querySelectorAll('tbody tr.data-row');
    trs.forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });

    const cards = section.querySelectorAll('.report-card');
    cards.forEach(card => {
        card.style.display = card.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
}
</script>

</body>
</html>
<?php $db->close(); ?>
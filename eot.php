<?php
session_start();
$title = "Extension of Time";

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

/* Helper Function */
function getEotBadge($status) {
    $status = strtolower($status ?: 'pending');
    $map = [
        'pending'  => ['class'=>'status-pending',  'text'=>'Pending'],
        'approved' => ['class'=>'status-approved', 'text'=>'Approved'],
        'rejected' => ['class'=>'status-rejected', 'text'=>'Rejected'],
    ];
    return $map[$status] ?? ['class'=>'status-pending', 'text'=>ucfirst($status)];
}

/* ================= Fetch EOT (Pending) Data ================= */
$query = "
    SELECT * FROM eot
    WHERE project_id = ?
      AND status_loss IS NULL
    ORDER BY created_at DESC
";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result_eot   = $stmt->get_result();
$rows_eot     = $result_eot->fetch_all(MYSQLI_ASSOC);
$total_pending = count($rows_eot);
$stmt->close();

/* ================= Fetch Loss & Expenses Data ================= */
$stmt2 = $db->prepare("
    SELECT * FROM eot
    WHERE project_id = ?
      AND status_loss IS NOT NULL
    ORDER BY created_at DESC
");
$stmt2->bind_param("i", $project_id);
$stmt2->execute();
$result_loss = $stmt2->get_result();
$rows_loss   = $result_loss->fetch_all(MYSQLI_ASSOC);
$total_loss  = count($rows_loss);
$stmt2->close();

$current_date = new DateTime();

/* ================= Helper: calculate balance days ================= */
function calculateEotBalance($eot_date, DateTime $current_date) {
    if (empty($eot_date)) return ['text' => '-', 'class' => ''];

    $target_date = new DateTime($eot_date);
    $interval    = $current_date->diff($target_date);
    $days        = (int)$interval->format('%r%a');

    if ($days > 0)       return ['text' => $days, 'class' => 'balance-positive'];
    if ($days === 0)     return ['text' => '0',   'class' => 'balance-zero'];
    return ['text' => $days, 'class' => 'balance-negative'];
}
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
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
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
    max-height: 500px;
    overflow-y: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
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
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: bold;
    color: #fff;
    white-space: nowrap;
    text-transform: capitalize;
}
.status-pending  { background: #6c757d; }
.status-approved { background: #28a745; }
.status-rejected { background: #dc3545; }

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

.eot-card {
    border: 1px solid #dde3f0;
    border-radius: 10px;
    padding: 12px 14px;
    cursor: pointer;
    background: #fff;
    transition: box-shadow 0.15s;
}
.eot-card:hover { box-shadow: 0 2px 8px rgba(0,123,255,.2); }

.card-rn     { font-weight: 700; font-size: 13px; color: var(--accent); margin-bottom: 4px; }
.card-title  { font-size: 13px; font-weight: 600; color: #222; margin-bottom: 6px; line-height: 1.4; word-break: break-word; overflow-wrap: anywhere; }
.card-meta   { display: flex; flex-wrap: wrap; gap: 8px; font-size: 12px; color: #666; margin-bottom: 6px; }
.card-footer { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; }
.card-amounts { font-size: 12px; color: #555; margin-bottom: 6px; }

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

<h1><?= htmlspecialchars($title) ?></h1>

<!-- =====================================================
     SECTION 1 — EOT RECORDS (PENDING)
     ===================================================== -->
<div class="section">
    <div class="section-header">
        <h2>EOT Records</h2>
        <a href="add_eot.php?project_id=<?= $project_id ?>" class="btn">+ Extension Of Time</a>
        <input type="text" class="search-input" placeholder="Search EOT records..." onkeyup="filterSection(this)">
    </div>

    <div class="table-info"><?= $total_pending ?> record(s)</div>

    <!-- Desktop table -->
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>EOT Number</th>
                <th>Reason</th>
                <th>Status</th>
                <th>EOT Date</th>
                <th>Amount Overall</th>
                <th>Amount Insurance</th>
                <th>Balance</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows_eot): foreach ($rows_eot as $row):
            $badge   = getEotBadge($row['status_eot']);
            $balance = calculateEotBalance($row['eot_date'], $current_date);
        ?>
        <tr class="data-row" onclick="window.location='add_eot.php?project_id=<?= $project_id ?>&eot_id=<?= $row['id'] ?>'">
            <td><strong><?= htmlspecialchars($row['eot_number']) ?></strong></td>
            <td><?= htmlspecialchars(mb_strimwidth($row['reason'], 0, 40, "...")) ?></td>
            <td><span class="status-badge <?= $badge['class'] ?>"><?= $badge['text'] ?></span></td>
            <td><?= $row['eot_date'] ? date('d/m/Y', strtotime($row['eot_date'])) : '—' ?></td>
            <td>RM <?= number_format($row['loss_overall_amount'] ?? 0, 2) ?></td>
            <td>RM <?= number_format($row['loss_insurance_amount'] ?? 0, 2) ?></td>
            <td class="<?= $balance['class'] ?>"><?= $balance['text'] ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="7"><div class="empty-state">📭 No Extension of Time pending found for this project.</div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Mobile cards -->
    <div class="card-list">
    <?php if ($rows_eot): foreach ($rows_eot as $row):
        $badge   = getEotBadge($row['status_eot']);
        $balance = calculateEotBalance($row['eot_date'], $current_date);
    ?>
    <div class="eot-card" onclick="window.location='add_eot.php?project_id=<?= $project_id ?>&eot_id=<?= $row['id'] ?>'">
        <div class="card-rn"><?= htmlspecialchars($row['eot_number']) ?></div>
        <div class="card-title"><?= htmlspecialchars(mb_strimwidth($row['reason'], 0, 60, "...")) ?></div>
        <div class="card-meta">
            <span>📅 <?= $row['eot_date'] ? date('d/m/Y', strtotime($row['eot_date'])) : '—' ?></span>
        </div>
        <div class="card-amounts">
            Overall: RM <?= number_format($row['loss_overall_amount'] ?? 0, 2) ?><br>
            Insurance: RM <?= number_format($row['loss_insurance_amount'] ?? 0, 2) ?>
        </div>
        <div class="card-footer">
            <span class="status-badge <?= $badge['class'] ?>"><?= $badge['text'] ?></span>
            <span class="<?= $balance['class'] ?>">Balance: <?= $balance['text'] ?></span>
        </div>
    </div>
    <?php endforeach; else: ?>
    <div class="empty-state">📭 No Extension of Time records found for this project.</div>
    <?php endif; ?>
    </div>
</div>

<!-- =====================================================
     SECTION 2 — LOSS & EXPENSES
     ===================================================== -->
<div class="section">
    <div class="section-header">
        <h2>Loss &amp; Expenses</h2>
        <input type="text" class="search-input" placeholder="Search loss & expenses..." onkeyup="filterSection(this)">
    </div>

    <div class="table-info"><?= $total_loss ?> record(s)</div>

    <!-- Desktop table -->
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>EOT Number</th>
                <th>Reason</th>
                <th>Status</th>
                <th>EOT Date</th>
                <th>Loss Status</th>
                <th>Loss Date</th>
                <th>Amount Overall</th>
                <th>Amount Insurance</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows_loss): foreach ($rows_loss as $row):
            $badge_eot  = getEotBadge($row['status_eot']);
            $badge_loss = getEotBadge($row['status_loss']);
        ?>
        <tr class="data-row" onclick="window.location='add_eot.php?project_id=<?= $project_id ?>&eot_id=<?= $row['id'] ?>'">
            <td><strong><?= htmlspecialchars($row['eot_number']) ?></strong></td>
            <td><?= htmlspecialchars(mb_strimwidth($row['reason'], 0, 40, "...")) ?></td>
            <td><span class="status-badge <?= $badge_eot['class'] ?>"><?= $badge_eot['text'] ?></span></td>
            <td><strong><?= !empty($row['eot_date']) ? date('d/m/Y', strtotime($row['eot_date'])) : '' ?></strong></td>
            <td><span class="status-badge <?= $badge_loss['class'] ?>"><?= $badge_loss['text'] ?></span></td>
            <td><?= $row['loss_overall_date'] ? date('d/m/Y', strtotime($row['loss_overall_date'])) : '—' ?></td>
            <td>RM <?= number_format($row['loss_overall_amount'] ?? 0, 2) ?></td>
            <td>RM <?= number_format($row['loss_insurance_amount'] ?? 0, 2) ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8"><div class="empty-state">📭 No Section D records found.</div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Mobile cards -->
    <div class="card-list">
    <?php if ($rows_loss): foreach ($rows_loss as $row):
        $badge_eot  = getEotBadge($row['status_eot']);
        $badge_loss = getEotBadge($row['status_loss']);
    ?>
    <div class="eot-card" onclick="window.location='add_eot.php?project_id=<?= $project_id ?>&eot_id=<?= $row['id'] ?>'">
        <div class="card-rn"><?= htmlspecialchars($row['eot_number']) ?></div>
        <div class="card-title"><?= htmlspecialchars(mb_strimwidth($row['reason'], 0, 60, "...")) ?></div>
        <div class="card-meta">
            <span>📅 EOT: <?= !empty($row['eot_date']) ? date('d/m/Y', strtotime($row['eot_date'])) : '—' ?></span>
            <span>📅 Loss: <?= $row['loss_overall_date'] ? date('d/m/Y', strtotime($row['loss_overall_date'])) : '—' ?></span>
        </div>
        <div class="card-amounts">
            Overall: RM <?= number_format($row['loss_overall_amount'] ?? 0, 2) ?><br>
            Insurance: RM <?= number_format($row['loss_insurance_amount'] ?? 0, 2) ?>
        </div>
        <div class="card-footer">
            <span class="status-badge <?= $badge_eot['class'] ?>"><?= $badge_eot['text'] ?></span>
            <span class="status-badge <?= $badge_loss['class'] ?>"><?= $badge_loss['text'] ?></span>
        </div>
    </div>
    <?php endforeach; else: ?>
    <div class="empty-state">📭 No Section D records found.</div>
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

    const cards = section.querySelectorAll('.eot-card');
    cards.forEach(card => {
        card.style.display = card.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
}
</script>

</body>
</html>
<?php $db->close(); ?>
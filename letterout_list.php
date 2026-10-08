<?php
session_start();


$title = "Letters Out";
require_once 'include/msqli_connect.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$project_id = intval($_GET['project_id'] ?? 0);
if (!$project_id) die("Invalid project selected.");
if (!user_can_access_project($db, $_SESSION['user_id'], $project_id, $_SESSION['account_type'] ?? '')) {
    die("You do not have permission to view this project.");
}
/* Helper Functions */
function getBadge($status) {
    $map = [
        'pending'           => ['class'=>'status-pending',             'text'=>'Pending'],
        'submitted'         => ['class'=>'status-submitted',           'text'=>'Submitted'],
        'acknowledged'      => ['class'=>'status-acknowledged',        'text'=>'Acknowledged'],
        'verified_verifier' => ['class'=>'status-verified-verifier',   'text'=>'Verified (Verifier)'],
        'verified_final'    => ['class'=>'status-verified-final',      'text'=>'Verified (Final)'],
        'rejected'          => ['class'=>'status-rejected',            'text'=>'Rejected'],
        'cancelled'         => ['class'=>'status-cancelled',           'text'=>'Cancelled'],
    ];
    return $map[$status] ?? ['class'=>'status-pending','text'=>ucfirst(str_replace('_',' ',$status))];
}
function calculateBalance($created_at, $startBalance = 5) {
    $daysPassed = (new DateTime())->diff(new DateTime($created_at))->days;
    return $startBalance - $daysPassed;
}
/* ================= Fetch Pending Letters ================= */
$sql_pending = "
    SELECT id, form_id, running_number, request_date, title, status, agency, created_at
    FROM letter_out_pending
    WHERE project_id = ?
      AND status IN ('pending','submitted','rejected')
    ORDER BY id DESC
";
$stmt_pending = $db->prepare($sql_pending);
if (!$stmt_pending) die("Prepare failed: " . $db->error);
$stmt_pending->bind_param("i", $project_id);
$stmt_pending->execute();
$result_pending = $stmt_pending->get_result();
$rows_pending   = $result_pending->fetch_all(MYSQLI_ASSOC);
$stmt_pending->close();
/* ================= Fetch Progress Letters ================= */
$sql_progress = "
    SELECT id, running_number, request_date, title, status
    FROM letter_out_pending
    WHERE project_id = ?
      AND status IN ('acknowledged','verified_verifier','verified_final','cancelled')
    ORDER BY id DESC
";
$stmt_progress = $db->prepare($sql_progress);
if (!$stmt_progress) die("Prepare failed: " . $db->error);
$stmt_progress->bind_param("i", $project_id);
$stmt_progress->execute();
$result_progress = $stmt_progress->get_result();
$rows_progress    = $result_progress->fetch_all(MYSQLI_ASSOC);
$stmt_progress->close();
/* ================= Fetch Archive Letters ================= */
$sql_archive = "
    SELECT id, running_number, title, agency, request_date, status, file_path
    FROM letter_out_archive
    WHERE project_id = ?
    ORDER BY YEAR(request_date) DESC, request_date DESC, running_number DESC
";
$stmt_archive = $db->prepare($sql_archive);
if (!$stmt_archive) die("Prepare failed: " . $db->error);
$stmt_archive->bind_param("i", $project_id);
$stmt_archive->execute();
$result_archive = $stmt_archive->get_result();
$rows_archive    = $result_archive->fetch_all(MYSQLI_ASSOC);
$stmt_archive->close();
/* ================= PROGRESS FILTER ================= */
$allowed_progress = ['', 'acknowledged', 'verified_verifier', 'verified_final', 'cancelled'];
$filter_progress  = strtolower(trim($_GET['pstatus'] ?? ''));
if (!in_array($filter_progress, $allowed_progress)) $filter_progress = '';
/* Status counts for filter buttons */
$prog_counts = ['acknowledged'=>0,'verified_verifier'=>0,'verified_final'=>0,'cancelled'=>0];
foreach ($rows_progress as $r) {
    if (isset($prog_counts[$r['status']])) $prog_counts[$r['status']]++;
}
$prog_total = count($rows_progress);
/* Apply filter to progress rows */
$rows_progress_filtered = $filter_progress === ''
    ? $rows_progress
    : array_filter($rows_progress, fn($r) => $r['status'] === $filter_progress);
/* ================= ARCHIVE: DUPLICATE RUNNING NUMBER CHECK ================= */
$archive_rn_counts = [];
foreach ($rows_archive as $r) {
    $rn = trim($r['running_number'] ?? '');
    if ($rn === '') continue;
    $archive_rn_counts[$rn] = ($archive_rn_counts[$rn] ?? 0) + 1;
}
function isDuplicateRN($rn, $counts) {
    $rn = trim($rn ?? '');
    return $rn !== '' && ($counts[$rn] ?? 0) > 1;
}
/* ================= ARCHIVE FILTER — BY RUNNING NUMBER GROUP (PREFIX) =================
   Running number format: PREFIX/.../YEAR/SEQ   e.g. MBSB/JKR/AIR_TAWAR/2025/499
   parseRunningNumber() is only used here to pull out the PREFIX so letters can
   be grouped into filter tags (e.g. "MBSB/OPM/DRC/AIR_TAWAR/JKR/HODT") — there
   is no missing-running-number / gap detection on this page (removed per user
   request 2026-07-30; that logic still exists in audit_letterout_rn.php).
   Letters whose running_number doesn't match the PREFIX/YEAR/SEQ pattern fall
   under a "Lain-lain" (other) bucket instead of being silently dropped.
================================================================================= */
function parseRunningNumber($rn) {
    $rn = trim($rn ?? '');
    if ($rn === '') return null;
    $parts = explode('/', $rn);
    if (count($parts) < 2) return null;
    $seqIndex = count($parts) - 1;
    if (!preg_match('/^\d+$/', $parts[$seqIndex])) return null; // last segment must be the sequence number
    // Find the nearest 4-digit year-looking segment (19xx/20xx) walking backwards
    // from the sequence — the year isn't always the segment immediately before
    // the seq. Some projects insert an extra code between them, e.g.
    // "MBSB/OPM/DRC/AIR_TAWAR/CONSULTANT/2024/GPC/005" (year, then "GPC", then seq).
    $yearIndex = null;
    for ($i = $seqIndex - 1; $i >= 0; $i--) {
        if (preg_match('/^(19|20)\d{2}$/', $parts[$i])) { $yearIndex = $i; break; }
    }
    if ($yearIndex === null) return null; // no year token found — not a recognized running number
    // Group key: same segments with the YEAR replaced by a "{YEAR}" placeholder
    // (position preserved) and the SEQ segment dropped. This way
    // ".../CONSULTANT/2024/GPC/005" and ".../CONSULTANT/2022/GPC/001" land in the
    // SAME group (year differs, everything else — including the extra "GPC" code
    // after the year — matches), while ".../2022/JLM/SEL/001" and
    // ".../2022/JLM/PER/001" correctly stay in SEPARATE groups (the segment right
    // after the year differs: SEL vs PER).
    $template = $parts;
    $template[$yearIndex] = '{YEAR}';
    array_splice($template, $seqIndex, 1);
    return ['prefix' => implode('/', $template)];
}
// Friendly display version of a group key: swap the "{YEAR}" placeholder for an
// ellipsis, since the group spans every year for that prefix (never resets).
function prettyRnGroupLabel($key) {
    return str_replace('{YEAR}', '…', $key);
}
$UNMATCHED_GROUP_KEY = '__UNMATCHED__';
$archive_group_counts = []; // prefix (or $UNMATCHED_GROUP_KEY) => count
foreach ($rows_archive as $r) {
    $parsed = parseRunningNumber($r['running_number']);
    $key = $parsed ? $parsed['prefix'] : $UNMATCHED_GROUP_KEY;
    $archive_group_counts[$key] = ($archive_group_counts[$key] ?? 0) + 1;
}
uksort($archive_group_counts, function ($a, $b) use ($UNMATCHED_GROUP_KEY) {
    if ($a === $UNMATCHED_GROUP_KEY) return 1;  // always show "Lain-lain" last
    if ($b === $UNMATCHED_GROUP_KEY) return -1;
    return strcmp($a, $b);
});
$archive_total = count($rows_archive);

/* ================= ARCHIVE FILTERS ================= */
$filter_rngroup = trim($_GET['rngroup'] ?? '');
$filter_year    = trim($_GET['ayear'] ?? '');

/* Year counts from the FULL archive dataset */
$archive_year_counts = [];
foreach ($rows_archive as $r) {
    $year = date('Y', strtotime($r['request_date']));
    if (preg_match('/^(19|20)\d{2}$/', $year)) {
        $archive_year_counts[$year] = ($archive_year_counts[$year] ?? 0) + 1;
    }
}
krsort($archive_year_counts, SORT_NUMERIC);

/* Validate selected year */
if ($filter_year !== '' && !array_key_exists($filter_year, $archive_year_counts)) {
    $filter_year = '';
}

/* Validate selected running-number group */
if ($filter_rngroup !== '' && !array_key_exists($filter_rngroup, $archive_group_counts)) {
    $filter_rngroup = '';
}

/* Apply BOTH filters to display rows only.
   Duplicate checks continue using the FULL archive dataset. */
$rows_archive_filtered = array_filter($rows_archive, function ($r) use (
    $filter_rngroup,
    $filter_year,
    $UNMATCHED_GROUP_KEY
) {
    if ($filter_year !== '') {
        $rowYear = date('Y', strtotime($r['request_date']));
        if ($rowYear !== $filter_year) {
            return false;
        }
    }

    if ($filter_rngroup !== '') {
        $parsed = parseRunningNumber($r['running_number']);
        $key = $parsed ? $parsed['prefix'] : $UNMATCHED_GROUP_KEY;
        if ($key !== $filter_rngroup) {
            return false;
        }
    }

    return true;
});
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
/* ── Notice ── */
.notice {
    background: #e6ffe6;
    border: 1px solid #00b300;
    color: #008000;
    padding: 10px 16px;
    border-radius: 10px;
    margin-bottom: 16px;
    font-weight: bold;
    text-align: center;
    width: 100%;
    max-width: 1100px;
}
.notice.danger { background:#ffe6e6; border-color:#ff0000; color:#b30000; }
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
.btn-grey  { background: #6c757d; }
.btn-grey:hover { background: #545b62; }
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
    max-height: 420px;
    overflow-y: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
    min-width: 520px;
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
/* ── Status badges (aligned with letterin.php naming) ── */
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: bold;
    color: #fff;
    white-space: nowrap;
}
.status-pending           { background: #ffc107; color: #856404; }
.status-submitted         { background: #007bff; }
.status-acknowledged      { background: #28a745; }
.status-verified-verifier { background: #17a2b8; }
.status-verified-final    { background: #6610f2; }
.status-rejected          { background: #dc3545; }
.status-cancelled         { background: #dc3545; }
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
@media (max-width: 640px) {
    body { padding: 10px; }
    .section { padding: 12px; border-radius: 12px; }
    .section-header h2 { font-size: 16px; }
    .desktop-table { display: none; }
    .card-list     { display: flex; flex-direction: column; gap: 10px; }
    .letter-card {
        border: 1px solid #dde3f0;
        border-radius: 10px;
        padding: 12px 14px;
        cursor: pointer;
        background: #fff;
        transition: box-shadow 0.15s;
    }
    .letter-card:hover { box-shadow: 0 2px 8px rgba(0,123,255,.2); }
    .card-rn     { font-weight: 700; font-size: 13px; color: #007bff; margin-bottom: 4px; }
    .card-title  { font-size: 13px; font-weight: 600; color: #222; margin-bottom: 6px; line-height: 1.4; }
    .card-meta   { display: flex; flex-wrap: wrap; gap: 8px; font-size: 12px; color: #666; margin-bottom: 6px; }
    .card-footer { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; }
    .card-agency { font-size: 12px; color: #888; }
}
@media (min-width: 641px) {
    .card-list { display: none; }
}
/* ── Filter bar (Progress section) ── */
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 14px;
    align-items: center;
}
.filter-bar span.filter-label {
    font-size: 13px;
    color: #555;
    font-weight: 600;
}
.filter-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    border: 2px solid transparent;
    transition: all 0.15s;
    cursor: pointer;
}
.filter-btn.all             { background:#e9ecef; color:#333; border-color:#dee2e6; }
.filter-btn.all:hover,
.filter-btn.all.active      { background:#343a40; color:#fff; border-color:#343a40; }
.filter-btn.acknowledged        { background:#e8f5e9; color:#28a745; border-color:#a5d6a7; }
.filter-btn.acknowledged:hover,
.filter-btn.acknowledged.active { background:#28a745; color:#fff; border-color:#28a745; }
.filter-btn.verifier        { background:#e0f7fa; color:#17a2b8; border-color:#80deea; }
.filter-btn.verifier:hover,
.filter-btn.verifier.active { background:#17a2b8; color:#fff; border-color:#17a2b8; }
.filter-btn.verified-final        { background:#ede7f6; color:#6610f2; border-color:#ce93d8; }
.filter-btn.verified-final:hover,
.filter-btn.verified-final.active { background:#6610f2; color:#fff; border-color:#6610f2; }
.filter-btn.cancelled        { background:#fdecea; color:#dc3545; border-color:#f5c6cb; }
.filter-btn.cancelled:hover,
.filter-btn.cancelled.active { background:#dc3545; color:#fff; border-color:#dc3545; }
/* ── Running-number-group pills (Archive section filter) ── */
.filter-btn.year-filter {
    background: #f3f0ff;
    color: #5b35b1;
    border-color: #cfc2ff;
}
.filter-btn.year-filter:hover,
.filter-btn.year-filter.active {
    background: #6f42c1;
    color: #fff;
    border-color: #6f42c1;
}

.filter-btn.rngroup {
    background: #eef5ff;
    color: #0056b3;
    border-color: #b6d4fe;
    max-width: 260px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.filter-btn.rngroup:hover,
.filter-btn.rngroup.active { background: #007bff; color: #fff; border-color: #007bff; }
.filter-btn.rngroup-unmatched { background:#fff3cd; color:#856404; border-color:#ffe08a; }
.filter-btn.rngroup-unmatched:hover,
.filter-btn.rngroup-unmatched.active { background:#856404; color:#fff; border-color:#856404; }
.filter-count {
    background: rgba(0,0,0,0.12);
    border-radius: 10px;
    padding: 1px 7px;
    font-size: 11px;
}
@media (max-width: 640px) {
    .filter-btn { padding: 5px 11px; font-size: 12px; }
}
/* ── Duplicate running number flag (Archive section) ── */
.dup-row { background: #fff8e1 !important; }
.dup-row:hover { background: #ffecb3 !important; }
.dup-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffe08a;
    border-radius: 6px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 700;
    margin-left: 6px;
    white-space: nowrap;
}
.letter-card.dup-row { border-color: #ffe08a; }
</style>
</head>
<body>
<?php include 'include/topbarstaff.php'; ?>
<h1><?= htmlspecialchars($title) ?></h1>
<?php if (isset($_GET['submitted'])): ?>
    <div class="notice">✅ Letter has been marked as submitted successfully.</div>
<?php elseif (isset($_GET['acknowledged'])): ?>
    <div class="notice">✅ Letter successfully acknowledged.</div>
<?php elseif (isset($_GET['cancelled'])): ?>
    <div class="notice danger">❌ Letter was cancelled.</div>
<?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
    <div class="notice">🔁 Letter status updated successfully.</div>
<?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div class="notice">🗑️ Archive record has been deleted successfully.</div>
<?php endif; ?>
<!-- =====================================================
     SECTION 1 — PENDING
     ===================================================== -->
<div class="section">
    <div class="section-header">
        <h2>Letter Out — Pending</h2>
        <a href="addletteroutpending.php?project_id=<?= $project_id ?>" class="btn">+ Add New Pending Letter</a>
        <?php if (in_array($_SESSION['account_type'], ['Client Admin','Admin (QA/QC)'])): ?>
            <a href="project_rn_settings.php?project_id=<?= $project_id ?>" class="btn btn-grey">⚙️ Running Number Settings</a>
        <?php endif; ?>
        <input type="text" class="search-input" placeholder="Search pending letters..." onkeyup="filterSection(this)">
    </div>
    <div class="table-info"><?= count($rows_pending) ?> letter(s)</div>
    <!-- Desktop table -->
    <div class="table-wrap desktop-table">
    <table>
        <thead>
            <tr>
                <th>Running Number</th>
                <th>Request Date</th>
                <th>Title</th>
                <th>Agency</th>
                <th>Status</th>
                <th>Balance</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows_pending): foreach ($rows_pending as $row):
            $displayNumber = $row['running_number'] ?: $row['form_id'];
            $badge   = getBadge($row['status']);
            $balance = calculateBalance($row['created_at']);
            $balClass = $balance > 0 ? 'balance-positive' : ($balance === 0 ? 'balance-zero' : 'balance-negative');
            $balDisp  = $balance < 0 ? '- ' . abs($balance) : $balance;
        ?>
        <tr class="data-row" onclick="window.location='viewletteroutsubmitted.php?id=<?= $row['id'] ?>'">
            <td><?= htmlspecialchars($displayNumber) ?></td>
            <td><?= date('d/m/Y', strtotime($row['request_date'])) ?></td>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= htmlspecialchars($row['agency']) ?></td>
            <td><span class="status-badge <?= $badge['class'] ?>"><?= $badge['text'] ?></span></td>
            <td class="<?= $balClass ?>"><?= $balDisp ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="6"><div class="empty-state">📭 No pending or submitted letters found.</div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    <!-- Mobile cards -->
    <div class="card-list">
    <?php if ($rows_pending): foreach ($rows_pending as $row):
        $displayNumber = $row['running_number'] ?: $row['form_id'];
        $badge   = getBadge($row['status']);
        $balance = calculateBalance($row['created_at']);
        $balClass = $balance > 0 ? 'balance-positive' : ($balance === 0 ? 'balance-zero' : 'balance-negative');
        $balDisp  = $balance < 0 ? '- ' . abs($balance) : $balance;
    ?>
    <div class="letter-card" onclick="window.location='viewletteroutsubmitted.php?id=<?= $row['id'] ?>'">
        <div class="card-rn"><?= htmlspecialchars($displayNumber) ?></div>
        <div class="card-title"><?= htmlspecialchars($row['title']) ?></div>
        <div class="card-meta">
            <span>📅 <?= date('d/m/Y', strtotime($row['request_date'])) ?></span>
            <span>🏢 <?= htmlspecialchars($row['agency']) ?></span>
        </div>
        <div class="card-footer">
            <span class="status-badge <?= $badge['class'] ?>"><?= $badge['text'] ?></span>
            <span class="<?= $balClass ?>">Balance: <?= $balDisp ?></span>
        </div>
    </div>
    <?php endforeach; else: ?>
    <div class="empty-state">📭 No pending or submitted letters found.</div>
    <?php endif; ?>
    </div>
</div>
<!-- =====================================================
     SECTION 2 — PROGRESS / ACKNOWLEDGE
     ===================================================== -->
<div class="section">
    <div class="section-header">
        <h2>Letter Out — Acknowledge / Progress</h2>
        <input type="text" class="search-input" placeholder="Search acknowledge letters..." onkeyup="filterSection(this)">
    </div>
    <!-- Filter buttons -->
    <div class="filter-bar">
        <span class="filter-label">Filter:</span>
        <a href="?project_id=<?= $project_id ?>"
           class="filter-btn all <?= $filter_progress === '' ? 'active' : '' ?>">
            All <span class="filter-count"><?= $prog_total ?></span>
        </a>
        <a href="?project_id=<?= $project_id ?>&pstatus=acknowledged"
           class="filter-btn acknowledged <?= $filter_progress === 'acknowledged' ? 'active' : '' ?>">
            ✅ Acknowledged <span class="filter-count"><?= $prog_counts['acknowledged'] ?></span>
        </a>
        <a href="?project_id=<?= $project_id ?>&pstatus=verified_verifier"
           class="filter-btn verifier <?= $filter_progress === 'verified_verifier' ? 'active' : '' ?>">
            🔵 Verified (Verifier) <span class="filter-count"><?= $prog_counts['verified_verifier'] ?></span>
        </a>
        <a href="?project_id=<?= $project_id ?>&pstatus=verified_final"
           class="filter-btn verified-final <?= $filter_progress === 'verified_final' ? 'active' : '' ?>">
            🟣 Verified (Final) <span class="filter-count"><?= $prog_counts['verified_final'] ?></span>
        </a>
        <a href="?project_id=<?= $project_id ?>&pstatus=cancelled"
           class="filter-btn cancelled <?= $filter_progress === 'cancelled' ? 'active' : '' ?>">
            ❌ Cancelled <span class="filter-count"><?= $prog_counts['cancelled'] ?></span>
        </a>
    </div>
    <div class="table-info">
        <?= count($rows_progress_filtered) ?> letter(s)
        <?php if ($filter_progress !== ''): ?>
            &mdash; filtered by <strong><?= ucfirst(str_replace('_',' ', $filter_progress)) ?></strong>
        <?php endif; ?>
    </div>
    <!-- Desktop table -->
    <div class="table-wrap desktop-table">
    <table>
        <thead>
            <tr>
                <th>Running Number</th>
                <th>Date Request</th>
                <th>Title</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows_progress_filtered): foreach ($rows_progress_filtered as $row):
            $badge = getBadge($row['status']);
        ?>
        <tr class="data-row" onclick="window.location='viewletteroutacknowledged.php?id=<?= $row['id'] ?>'">
            <td><?= htmlspecialchars($row['running_number'] ?: '-') ?></td>
            <td><?= date('d/m/Y', strtotime($row['request_date'])) ?></td>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><span class="status-badge <?= $badge['class'] ?>"><?= $badge['text'] ?></span></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="4"><div class="empty-state">📭 No letters found<?= $filter_progress !== '' ? ' for <strong>' . ucfirst(str_replace('_',' ',$filter_progress)) . '</strong>' : '' ?>.</div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    <!-- Mobile cards -->
    <div class="card-list">
    <?php if ($rows_progress_filtered): foreach ($rows_progress_filtered as $row):
        $badge = getBadge($row['status']);
    ?>
    <div class="letter-card" onclick="window.location='viewletteroutacknowledged.php?id=<?= $row['id'] ?>'">
        <div class="card-rn"><?= htmlspecialchars($row['running_number'] ?: '-') ?></div>
        <div class="card-title"><?= htmlspecialchars($row['title']) ?></div>
        <div class="card-meta">
            <span>📅 <?= date('d/m/Y', strtotime($row['request_date'])) ?></span>
        </div>
        <div class="card-footer">
            <span class="status-badge <?= $badge['class'] ?>"><?= $badge['text'] ?></span>
        </div>
    </div>
    <?php endforeach; else: ?>
    <div class="empty-state">📭 No letters found<?= $filter_progress !== '' ? ' for <strong>' . ucfirst(str_replace('_',' ',$filter_progress)) . '</strong>' : '' ?>.</div>
    <?php endif; ?>
    </div>
</div>
<!-- =====================================================
     SECTION 3 — ARCHIVE
     ===================================================== -->
<div class="section">
    <div class="section-header">
        <h2>Letter Out — Archive</h2>
        <a href="addletteroutarchive.php?project_id=<?= $project_id ?>" class="btn">+ Add New Archive Letter</a>
        <input type="text" class="search-input" placeholder="Search archived letters..." onkeyup="filterSection(this)">
    </div>
    <!-- Archive filters: Year + Running Number Group -->
    <div class="filter-bar">
        <span class="filter-label">Filter by Year:</span>
        <?php
        $yearAllUrl = '?project_id=' . $project_id;
        if ($filter_rngroup !== '') {
            $yearAllUrl .= '&rngroup=' . urlencode($filter_rngroup);
        }

        $yearAllCount = $archive_total;
        if ($filter_rngroup !== '') {
            $yearAllCount = count(array_filter($rows_archive, function ($r) use ($filter_rngroup, $UNMATCHED_GROUP_KEY) {
                $parsed = parseRunningNumber($r['running_number']);
                $key = $parsed ? $parsed['prefix'] : $UNMATCHED_GROUP_KEY;
                return $key === $filter_rngroup;
            }));
        }
        ?>
        <a href="<?= htmlspecialchars($yearAllUrl) ?>"
           class="filter-btn all <?= $filter_year === '' ? 'active' : '' ?>">
            All <span class="filter-count"><?= $yearAllCount ?></span>
        </a>

        <?php foreach ($archive_year_counts as $year => $cnt):
            $yearCount = $cnt;

            if ($filter_rngroup !== '') {
                $yearCount = 0;
                foreach ($rows_archive as $ar) {
                    $arYear = date('Y', strtotime($ar['request_date']));
                    $parsed = parseRunningNumber($ar['running_number']);
                    $arGroup = $parsed ? $parsed['prefix'] : $UNMATCHED_GROUP_KEY;

                    if ($arYear === $year && $arGroup === $filter_rngroup) {
                        $yearCount++;
                    }
                }
            }

            $yearUrl = '?project_id=' . $project_id . '&ayear=' . urlencode($year);
            if ($filter_rngroup !== '') {
                $yearUrl .= '&rngroup=' . urlencode($filter_rngroup);
            }
        ?>
        <a href="<?= htmlspecialchars($yearUrl) ?>"
           class="filter-btn year-filter <?= $filter_year === $year ? 'active' : '' ?>">
            <?= htmlspecialchars($year) ?> <span class="filter-count"><?= $yearCount ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Running Number Group filter -->
    <div class="filter-bar">
        <span class="filter-label">Filter by Running Number Group:</span>
        <?php
        $groupAllUrl = '?project_id=' . $project_id;
        if ($filter_year !== '') {
            $groupAllUrl .= '&ayear=' . urlencode($filter_year);
        }

        $groupAllCount = $archive_total;
        if ($filter_year !== '') {
            $groupAllCount = count(array_filter($rows_archive, function ($r) use ($filter_year) {
                return date('Y', strtotime($r['request_date'])) === $filter_year;
            }));
        }
        ?>
        <a href="<?= htmlspecialchars($groupAllUrl) ?>"
           class="filter-btn all <?= $filter_rngroup === '' ? 'active' : '' ?>">
            All <span class="filter-count"><?= $groupAllCount ?></span>
        </a>

        <?php foreach ($archive_group_counts as $key => $cnt):
            $isUnmatched = $key === $UNMATCHED_GROUP_KEY;
            $label = $isUnmatched ? 'Lain-lain / Format Tidak Dikenali' : prettyRnGroupLabel($key);

            $groupUrl = '?project_id=' . $project_id . '&rngroup=' . urlencode($key);
            if ($filter_year !== '') {
                $groupUrl .= '&ayear=' . urlencode($filter_year);
            }

            $groupCount = $cnt;

            if ($filter_year !== '') {
                $groupCount = 0;
                foreach ($rows_archive as $ar) {
                    $arYear = date('Y', strtotime($ar['request_date']));
                    $parsed = parseRunningNumber($ar['running_number']);
                    $arGroup = $parsed ? $parsed['prefix'] : $UNMATCHED_GROUP_KEY;

                    if ($arYear === $filter_year && $arGroup === $key) {
                        $groupCount++;
                    }
                }
            }
        ?>
        <a href="<?= htmlspecialchars($groupUrl) ?>"
           class="filter-btn rngroup <?= $filter_rngroup === $key ? 'active' : '' ?> <?= $isUnmatched ? 'rngroup-unmatched' : '' ?>"
           title="<?= htmlspecialchars($isUnmatched ? $label : $key) ?>">
            <?= htmlspecialchars($label) ?> <span class="filter-count"><?= $groupCount ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <div class="table-info">
        <?= count($rows_archive_filtered) ?> letter(s)
        <?php if ($filter_rngroup !== ''): ?>
            &mdash; filtered by group <strong><?= htmlspecialchars($filter_rngroup === $UNMATCHED_GROUP_KEY ? 'Lain-lain / Format Tidak Dikenali' : prettyRnGroupLabel($filter_rngroup)) ?></strong>
        <?php endif; ?>
        <?php
        $dup_count = 0;
        foreach ($archive_rn_counts as $c) { if ($c > 1) $dup_count += $c; }
        if ($dup_count > 0):
        ?>
            &mdash; <span style="color:#856404; font-weight:bold;">⚠️ <?= $dup_count ?> with duplicate running number</span>
            &nbsp;<a href="#" onclick="showDuplicatesOnly(this); return false;" style="font-size:12px; color:#007bff; font-weight:600;">(show duplicates only)</a>
            <a href="#" onclick="showAllArchive(this); return false;" style="display:none; font-size:12px; color:#6c757d; font-weight:600;">(show all)</a>
        <?php endif; ?>
    </div>
    <!-- Desktop table -->
    <div class="table-wrap desktop-table">
    <table>
        <thead>
            <tr>
                <th>Running Number</th>
                <th>Letter Date</th>
                <th>Title</th>
                <th>Agency</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows_archive_filtered): foreach ($rows_archive_filtered as $row):
            $is_dup = isDuplicateRN($row['running_number'], $archive_rn_counts);
            $badge  = getBadge($row['status']);
        ?>
        <tr class="data-row<?= $is_dup ? ' dup-row' : '' ?>" style="opacity:0.7;" onclick="window.location='viewletteroutarchive.php?id=<?= $row['id'] ?>'">
            <td>
                <?= htmlspecialchars($row['running_number'] ?: '-') ?>
                <?php if ($is_dup): ?>
                    <span class="dup-badge" title="This running number appears more than once in the archive">⚠️ Duplicate</span>
                <?php endif; ?>
            </td>
            <td><?= date('d/m/Y', strtotime($row['request_date'])) ?></td>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= htmlspecialchars($row['agency']) ?></td>
            <td><span class="status-badge <?= $badge['class'] ?>"><?= $badge['text'] ?></span></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="5"><div class="empty-state">📭 No archived letters found<?= $filter_year !== '' ? ' for year <strong>' . htmlspecialchars($filter_year) . '</strong>' : '' ?><?= $filter_rngroup !== '' ? ' for group <strong>' . htmlspecialchars($filter_rngroup === $UNMATCHED_GROUP_KEY ? 'Lain-lain / Format Tidak Dikenali' : prettyRnGroupLabel($filter_rngroup)) . '</strong>' : '' ?>.</div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    <!-- Mobile cards -->
    <div class="card-list">
    <?php if ($rows_archive_filtered): foreach ($rows_archive_filtered as $row):
        $is_dup = isDuplicateRN($row['running_number'], $archive_rn_counts);
        $badge  = getBadge($row['status']);
    ?>
    <div class="letter-card<?= $is_dup ? ' dup-row' : '' ?>" style="opacity:0.75;" onclick="window.location='viewletteroutarchive.php?id=<?= $row['id'] ?>'">
        <div class="card-rn">
            <?= htmlspecialchars($row['running_number'] ?: '-') ?>
            <?php if ($is_dup): ?>
                <span class="dup-badge" title="This running number appears more than once in the archive">⚠️ Duplicate</span>
            <?php endif; ?>
        </div>
        <div class="card-title"><?= htmlspecialchars($row['title']) ?></div>
        <div class="card-meta">
            <span>📅 <?= date('d/m/Y', strtotime($row['request_date'])) ?></span>
            <span>🏢 <?= htmlspecialchars($row['agency']) ?></span>
        </div>
        <div class="card-footer">
            <span class="status-badge <?= $badge['class'] ?>"><?= $badge['text'] ?></span>
        </div>
    </div>
    <?php endforeach; else: ?>
    <div class="empty-state">📭 No archived letters found<?= $filter_year !== '' ? ' for year <strong>' . htmlspecialchars($filter_year) . '</strong>' : '' ?><?= $filter_rngroup !== '' ? ' for group <strong>' . htmlspecialchars($filter_rngroup === $UNMATCHED_GROUP_KEY ? 'Lain-lain / Format Tidak Dikenali' : prettyRnGroupLabel($filter_rngroup)) . '</strong>' : '' ?>.</div>
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
    const cards = section.querySelectorAll('.letter-card');
    cards.forEach(card => {
        card.style.display = card.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
}
function showDuplicatesOnly(link) {
    const section = link.closest('.section');
    section.querySelectorAll('tbody tr.data-row').forEach(tr => {
        tr.style.display = tr.classList.contains('dup-row') ? '' : 'none';
    });
    section.querySelectorAll('.letter-card').forEach(card => {
        card.style.display = card.classList.contains('dup-row') ? '' : 'none';
    });
    link.style.display = 'none';
    const showAllLink = link.nextElementSibling;
    if (showAllLink) showAllLink.style.display = 'inline';
}
function showAllArchive(link) {
    const section = link.closest('.section');
    section.querySelectorAll('tbody tr.data-row').forEach(tr => { tr.style.display = ''; });
    section.querySelectorAll('.letter-card').forEach(card => { card.style.display = ''; });
    link.style.display = 'none';
    const showDupLink = link.previousElementSibling;
    if (showDupLink) showDupLink.style.display = 'inline';
}
</script>
</body>
</html>
<?php $db->close(); ?>
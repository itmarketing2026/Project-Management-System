<?php
session_start(); 
$title = "Letters In";

require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$flash_success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

$user_id = (int)($_SESSION['user_id'] ?? 0);
$role    = strtolower(trim($_SESSION['account_type'] ?? ''));

$is_admin_edit       = in_array($role, ['client admin', 'admin (qa/qc)']);
$is_management_role  = ($role === 'management');
$is_hra_role          = ($role === 'hra');

$project_id = intval($_GET['project_id'] ?? 0);
if (!$project_id) {
    die("Invalid project selected.");
}

/* =========================================================
   AUTHORIZATION CHECK (fixes IDOR)
   Any logged-in user could previously view ANY project's letters
   by changing ?project_id= in the URL. We now require the user to
   be assigned to the project (user_project), Management (implicit
   access to all projects), or Client Admin / Admin (QA/QC).
   ========================================================= */
$stmtP = $db->prepare("SELECT id FROM user_project WHERE user_id = ? AND project_id = ? LIMIT 1");
$stmtP->bind_param("ii", $user_id, $project_id);
$stmtP->execute();
$stmtP->store_result();
$is_assigned_to_project = $stmtP->num_rows > 0;
$stmtP->close();

$is_authorized = $is_assigned_to_project || $is_management_role || $is_hra_role || $is_admin_edit;

if (!$is_authorized) {
    http_response_code(403);
    die("You do not have permission to view this project's letters.");
}

/* ================= STATUS FILTER ================= */
$allowed_statuses = ['', 'pending', 'verified', 'rejected'];
$filter_status    = strtolower(trim($_GET['status'] ?? ''));
if (!in_array($filter_status, $allowed_statuses)) $filter_status = '';

/* ================= SEARCH FILTER ================= */
$search = trim($_GET['search'] ?? '');

/* ================= PAGINATION SETUP ================= */
define('PER_PAGE', 100);
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * PER_PAGE;

/* ================= BUILD WHERE CLAUSE ================= */
// Base condition
$where_current = "project_id = ? AND received_date >= '2026-04-13'";
$params_types  = "i";
$params_values = [$project_id];

if ($filter_status !== '') {
    $where_current  .= " AND LOWER(status) = ?";
    $params_types   .= "s";
    $params_values[] = $filter_status;
}

if ($search !== '') {
    $where_current  .= " AND (title LIKE ? OR reference_number LIKE ? OR remarks LIKE ?)";
    $params_types   .= "sss";
    $like = "%{$search}%";
    $params_values[] = $like;
    $params_values[] = $like;
    $params_values[] = $like;
}

/* ================= CURRENT — total count ================= */
$count_sql  = "SELECT COUNT(*) AS total FROM letters_in WHERE $where_current";
$count_stmt = $db->prepare($count_sql);
$count_stmt->bind_param($params_types, ...$params_values);
$count_stmt->execute();
$total_current = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages  = max(1, (int)ceil($total_current / PER_PAGE));
$current_page = min($current_page, $total_pages);
$offset       = ($current_page - 1) * PER_PAGE;

/* ================= CURRENT — paginated rows ================= */
$per_page   = PER_PAGE;
$curr_sql   = "SELECT * FROM letters_in WHERE $where_current ORDER BY created_at DESC LIMIT ? OFFSET ?";
$curr_stmt  = $db->prepare($curr_sql);
$curr_types = $params_types . "ii";
$curr_vals  = array_merge($params_values, [$per_page, $offset]);
$curr_stmt->bind_param($curr_types, ...$curr_vals);
$curr_stmt->execute();
$current_result = $curr_stmt->get_result();

/* ================= STATUS COUNTS (for badges on filter buttons) ================= */
// Status counts reflect the search term too, but not the status filter itself,
// so badge numbers stay meaningful while searching.
$sc_where  = "project_id = ? AND received_date >= '2026-04-13'";
$sc_types  = "i";
$sc_values = [$project_id];

if ($search !== '') {
    $sc_where  .= " AND (title LIKE ? OR reference_number LIKE ? OR remarks LIKE ?)";
    $sc_types  .= "sss";
    $like = "%{$search}%";
    $sc_values[] = $like;
    $sc_values[] = $like;
    $sc_values[] = $like;
}

$sc_stmt = $db->prepare("
    SELECT LOWER(status) AS s, COUNT(*) AS c
    FROM letters_in
    WHERE $sc_where
    GROUP BY LOWER(status)
");
$sc_stmt->bind_param($sc_types, ...$sc_values);
$sc_stmt->execute();
$sc_result = $sc_stmt->get_result();
$status_counts = ['pending' => 0, 'verified' => 0, 'rejected' => 0];
while ($sc = $sc_result->fetch_assoc()) {
    if (isset($status_counts[$sc['s']])) $status_counts[$sc['s']] = $sc['c'];
}
$sc_stmt->close();
$total_all = array_sum($status_counts);

/* ================= ARCHIVE — pagination setup ================= */
$archive_page   = max(1, intval($_GET['archive_page'] ?? 1));
$archive_offset = ($archive_page - 1) * PER_PAGE;

$archive_where  = "project_id = ? AND received_date < '2026-04-13'";
$archive_types  = "i";
$archive_values = [$project_id];

if ($search !== '') {
    $archive_where  .= " AND (title LIKE ? OR reference_number LIKE ? OR remarks LIKE ?)";
    $archive_types  .= "sss";
    $like = "%{$search}%";
    $archive_values[] = $like;
    $archive_values[] = $like;
    $archive_values[] = $like;
}

/* ================= ARCHIVE — total count ================= */
$archive_count_sql  = "SELECT COUNT(*) AS total FROM letters_in WHERE $archive_where";
$archive_count_stmt = $db->prepare($archive_count_sql);
$archive_count_stmt->bind_param($archive_types, ...$archive_values);
$archive_count_stmt->execute();
$total_archive = $archive_count_stmt->get_result()->fetch_assoc()['total'];
$archive_count_stmt->close();

$archive_total_pages = max(1, (int)ceil($total_archive / PER_PAGE));
$archive_page         = min($archive_page, $archive_total_pages);
$archive_offset       = ($archive_page - 1) * PER_PAGE;

/* ================= ARCHIVE — paginated rows ================= */
$archive_stmt  = $db->prepare("
    SELECT * FROM letters_in 
    WHERE $archive_where
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$archive_query_types  = $archive_types . "ii";
$archive_query_values = array_merge($archive_values, [PER_PAGE, $archive_offset]);
$archive_stmt->bind_param($archive_query_types, ...$archive_query_values);
$archive_stmt->execute();
$archive_result = $archive_stmt->get_result();

/* ================= Buffer rows ================= */
$current_rows = [];
while ($row = $current_result->fetch_assoc()) $current_rows[] = $row;

$archive_rows = [];
while ($row = $archive_result->fetch_assoc()) $archive_rows[] = $row;

/* ================= URL builder helper ================= */
function page_url($project_id, $page, $status, $search = '') {
    $s = $status !== '' ? '&status=' . urlencode($status) : '';
    $q = $search !== '' ? '&search=' . urlencode($search) : '';
    return "?project_id={$project_id}&page={$page}{$s}{$q}";
}

/* ================= View link helper (preserve status filter) ================= */
function view_url($id, $project_id, $status) {
    $s = $status !== '' ? '&status=' . urlencode($status) : '';
    return "viewletterin.php?id={$id}&project_id={$project_id}{$s}";
}

/* ================= Archive pagination URL helper ================= */
function archive_page_url($project_id, $page, $status, $search = '') {
    $s = $status !== '' ? '&status=' . urlencode($status) : '';
    $q = $search !== '' ? '&search=' . urlencode($search) : '';
    return "?project_id={$project_id}{$s}{$q}&archive_page={$page}#archive-section";
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
}

*, *::before, *::after { box-sizing: border-box; }

body {
    margin: 0;
    font-family: "Segoe UI", Arial, sans-serif;
    background: var(--bg);
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 16px;
}

h1 {
    color: var(--accent);
    font-size: clamp(22px, 5vw, 32px);
    font-weight: 800;
    margin: 16px 0 24px;
    text-align: center;
}

/* ── Header controls ── */
.header-controls {
    width: 100%;
    max-width: 1000px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.search-box {
    flex: 1 1 200px;
    min-width: 0;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 4px 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.search-box input[type="text"] {
    border: none;
    outline: none;
    width: 100%;
    padding: 8px 4px;
    font-size: 14px;
}

.search-box button {
    flex-shrink: 0;
    border: none;
    background: transparent;
    color: var(--accent);
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    padding: 6px 8px;
}
.search-box button:hover { text-decoration: underline; }

.search-clear {
    flex-shrink: 0;
    color: #888;
    font-size: 13px;
    text-decoration: none;
    padding: 6px 4px;
    white-space: nowrap;
}
.search-clear:hover { color: #dc3545; }

.add-btn {
    flex-shrink: 0;
    background: var(--accent);
    color: #fff;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    font-size: 14px;
    white-space: nowrap;
}
.add-btn:hover { background: var(--accent-hover); }

/* ── Filter bar ── */
.filter-bar {
    width: 100%;
    max-width: 1000px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 20px;
    align-items: center;
}

.filter-bar span {
    font-size: 13px;
    color: #555;
    font-weight: 600;
    margin-right: 2px;
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

/* All */
.filter-btn.all        { background:#e9ecef; color:#333; border-color:#dee2e6; }
.filter-btn.all:hover,
.filter-btn.all.active { background:#343a40; color:#fff; border-color:#343a40; }

/* Pending */
.filter-btn.pending        { background:#fff8e1; color:#856404; border-color:#ffe082; }
.filter-btn.pending:hover,
.filter-btn.pending.active { background:#ffc107; color:#fff; border-color:#ffc107; }

/* Verified */
.filter-btn.verified        { background:#e8f5e9; color:#28a745; border-color:#a5d6a7; }
.filter-btn.verified:hover,
.filter-btn.verified.active { background:#28a745; color:#fff; border-color:#28a745; }

/* Rejected */
.filter-btn.rejected        { background:#fdecea; color:#dc3545; border-color:#f5c6cb; }
.filter-btn.rejected:hover,
.filter-btn.rejected.active { background:#dc3545; color:#fff; border-color:#dc3545; }

.filter-count {
    background: rgba(0,0,0,0.12);
    border-radius: 10px;
    padding: 1px 7px;
    font-size: 11px;
}

/* ── Table container ── */
.table-container {
    width: 100%;
    max-width: 1000px;
    background: #fff;
    border: 2px solid var(--accent);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 30px;
    overflow-x: auto;
}

.table-container h3 { margin: 0 0 14px; font-size: clamp(15px, 3.5vw, 18px); }

table { width: 100%; border-collapse: collapse; min-width: 560px; }

th, td {
    padding: 11px 14px;
    border: 1px solid #e0e0e0;
    font-size: 14px;
    vertical-align: middle;
}

th { background: var(--accent); color: #fff; white-space: nowrap; }
tr:hover { background: #eef5ff; cursor: pointer; }

/* ── Status badges ── */
.status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: bold;
    white-space: nowrap;
    display: inline-block;
}
.status-verified { background:#d4edda; color:#28a745; }
.status-rejected { background:#f8d7da; color:#dc3545; }
.status-pending  { background:#fff3cd; color:#856404; }

/* ── Empty state ── */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #aaa;
}
.empty-state .icon { font-size: 40px; margin-bottom: 8px; }
.empty-state p { margin: 0; font-size: 14px; }

/* ── Pagination ── */
.pagination {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
    justify-content: center;
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid #eee;
}

.pagination a,
.pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid #ddd;
    background: #fff;
    color: var(--accent);
    transition: background 0.15s, color 0.15s;
}

.pagination a:hover          { background: var(--accent); color: #fff; border-color: var(--accent); }
.pagination .pg-active       { background: var(--accent); color: #fff; border-color: var(--accent); pointer-events: none; }
.pagination .pg-disabled     { color: #bbb; pointer-events: none; border-color: #eee; }

.page-info { font-size: 13px; color: #666; margin-top: 8px; text-align: center; }

/* ── Mobile card view ── */
@media (max-width: 600px) {
    body { padding: 12px; }

    .table-container { padding: 12px; border-radius: 12px; overflow-x: visible; }

    .desktop-table { display: none; }
    .card-list     { display: flex; flex-direction: column; gap: 10px; }

    .letter-card {
        background: #fff;
        border: 1px solid #dde3f0;
        border-radius: 10px;
        padding: 12px 14px;
        cursor: pointer;
        transition: box-shadow 0.15s;
    }
    .letter-card:hover { box-shadow: 0 2px 8px rgba(0,123,255,.18); }

    .card-title  { font-weight: 700; font-size: 14px; color: #222; margin-bottom: 4px; }
    .card-ref    { font-size: 12px; color: #555; margin-bottom: 8px; }
    .card-meta   { display: flex; flex-wrap: wrap; gap: 8px; font-size: 12px; color: #666; }
    .card-meta span { display: flex; align-items: center; gap: 3px; }
    .card-footer { margin-top: 8px; display: flex; justify-content: space-between; align-items: center; }
    .card-remarks {
        font-size: 12px; color: #888;
        max-width: 65%; white-space: nowrap;
        overflow: hidden; text-overflow: ellipsis;
    }
    .archive-card { opacity: 0.65; }

    .pagination a,
    .pagination span { min-width: 32px; height: 32px; font-size: 13px; }

    .filter-bar { gap: 6px; }
    .filter-btn  { padding: 5px 11px; font-size: 12px; }
}

@media (min-width: 601px) {
    .card-list { display: none; }
}
</style>

<script>
let searchTimer = null;
function autoSearch(input) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        input.form.submit();
    }, 500); // wait 500ms after user stops typing
}

window.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("searchInput");
    if (input && input.value) {
        input.focus();
        const len = input.value.length;
        input.setSelectionRange(len, len);
    }
});
</script>

</head>
<body>

<?php include 'include/topbarstaff.php'; ?>

<h1><?= htmlspecialchars($title) ?></h1>

<?php if ($flash_success): ?>
    <div id="flashMsg" style="
        width:100%;
        max-width:1000px;
        background:#d4edda;
        color:#155724;
        border:1px solid #c3e6cb;
        padding:14px 18px;
        border-radius:10px;
        margin-bottom:16px;
        font-weight:600;
        text-align:center;
    ">
        ✅ <?= htmlspecialchars($flash_success) ?>
    </div>
    <script>
        setTimeout(() => {
            const msg = document.getElementById('flashMsg');
            if (msg) msg.style.display = 'none';
        }, 4000);
    </script>
<?php endif; ?>

<div class="header-controls">
    <form class="search-box" method="GET" action="">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <?php if ($filter_status !== ''): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
        <?php endif; ?>
        <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search) ?>"
               placeholder="Search letters..." oninput="autoSearch(this)">
        <?php if ($search !== ''): ?>
            <a class="search-clear" href="<?= page_url($project_id, 1, $filter_status) ?>">Clear</a>
        <?php endif; ?>
    </form>
    <a href="addletterin.php?project_id=<?= $project_id ?>" class="add-btn">+ Add new letter in</a>
</div>

<!-- ── Filter buttons ── -->
<div class="filter-bar">
    <span>Filter:</span>

    <a href="<?= page_url($project_id, 1, '', $search) ?>"
       class="filter-btn all <?= $filter_status === '' ? 'active' : '' ?>">
        All <span class="filter-count"><?= $total_all ?></span>
    </a>

    <a href="<?= page_url($project_id, 1, 'pending', $search) ?>"
       class="filter-btn pending <?= $filter_status === 'pending' ? 'active' : '' ?>">
        ⏳ Pending <span class="filter-count"><?= $status_counts['pending'] ?></span>
    </a>

    <a href="<?= page_url($project_id, 1, 'verified', $search) ?>"
       class="filter-btn verified <?= $filter_status === 'verified' ? 'active' : '' ?>">
        ✅ Verified <span class="filter-count"><?= $status_counts['verified'] ?></span>
    </a>

    <a href="<?= page_url($project_id, 1, 'rejected', $search) ?>"
       class="filter-btn rejected <?= $filter_status === 'rejected' ? 'active' : '' ?>">
        ❌ Rejected <span class="filter-count"><?= $status_counts['rejected'] ?></span>
    </a>
</div>

<!-- ===================================================
     CURRENT LETTERS
     =================================================== -->
<div class="table-container">
<h3>
    📌 Current Letters
    <?php if ($filter_status !== ''): ?>
        — <span style="font-size:14px;font-weight:normal;color:#555;">
            filtered by <strong><?= htmlspecialchars(ucfirst($filter_status)) ?></strong>
          </span>
    <?php endif; ?>
    <?php if ($search !== ''): ?>
        — <span style="font-size:14px;font-weight:normal;color:#555;">
            search: "<?= htmlspecialchars($search) ?>"
          </span>
    <?php endif; ?>
    <span style="font-size:15px;color:#888;">(<?= $total_current ?>)</span>
</h3>

<?php $row_start = $offset + 1; ?>

<!-- Desktop table -->
<table class="desktop-table">
<thead>
<tr>
    <th>No</th>
    <th>Title</th>
    <th>Reference</th>
    <th>Received Date</th>
    <th>Letter Date</th>
    <th>Remarks</th>
    <th>Status</th>
</tr>
</thead>
<tbody>
<?php if ($current_rows): $i = $row_start; foreach ($current_rows as $row):
    $status = strtolower($row['status']);
    $badge  = "status-$status";
?>
<tr class="data-row" onclick="window.location='<?= view_url($row['id'], $project_id, $filter_status) ?>'">
    <td><?= $i++ ?></td>
    <td><?= htmlspecialchars($row['title']) ?></td>
    <td><?= htmlspecialchars($row['reference_number']) ?></td>
    <td><?= date('d/m/Y', strtotime($row['received_date'])) ?></td>
    <td><?= date('d/m/Y', strtotime($row['letter_date'])) ?></td>
    <td><?= htmlspecialchars($row['remarks']) ?></td>
    <td><span class="status-badge <?= htmlspecialchars($badge) ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="7">
    <div class="empty-state">
        <div class="icon">📭</div>
        <p>No letters found<?= $filter_status !== '' ? ' for status <strong>' . htmlspecialchars(ucfirst($filter_status)) . '</strong>' : '' ?><?= $search !== '' ? ' matching "' . htmlspecialchars($search) . '"' : '' ?>.</p>
    </div>
</td></tr>
<?php endif; ?>
</tbody>
</table>

<!-- Mobile cards -->
<div class="card-list">
<?php if ($current_rows): foreach ($current_rows as $row):
    $status = strtolower($row['status']);
    $badge  = "status-$status";
?>
<div class="letter-card"
     onclick="window.location='<?= view_url($row['id'], $project_id, $filter_status) ?>'">
    <div class="card-title"><?= htmlspecialchars($row['title']) ?></div>
    <div class="card-ref">Ref: <?= htmlspecialchars($row['reference_number']) ?></div>
    <div class="card-meta">
        <span>📥 <?= date('d/m/Y', strtotime($row['received_date'])) ?></span>
        <span>📄 <?= date('d/m/Y', strtotime($row['letter_date'])) ?></span>
    </div>
    <div class="card-footer">
        <span class="card-remarks"><?= htmlspecialchars($row['remarks']) ?></span>
        <span class="status-badge <?= htmlspecialchars($badge) ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
    </div>
</div>
<?php endforeach; else: ?>
<div class="empty-state">
    <div class="icon">📭</div>
    <p>No letters found<?= $filter_status !== '' ? ' for <strong>' . htmlspecialchars(ucfirst($filter_status)) . '</strong>' : '' ?><?= $search !== '' ? ' matching "' . htmlspecialchars($search) . '"' : '' ?>.</p>
</div>
<?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1):
    $range = 2;
?>
<div class="pagination">
    <?php if ($current_page > 1): ?>
        <a href="<?= page_url($project_id, $current_page - 1, $filter_status, $search) ?>">&#8592; Prev</a>
    <?php else: ?>
        <span class="pg-disabled">&#8592; Prev</span>
    <?php endif; ?>

    <?php if ($current_page - $range > 1): ?>
        <a href="<?= page_url($project_id, 1, $filter_status, $search) ?>">1</a>
        <?php if ($current_page - $range > 2): ?>
            <span class="pg-disabled">&hellip;</span>
        <?php endif; ?>
    <?php endif; ?>

    <?php for ($p = max(1, $current_page - $range); $p <= min($total_pages, $current_page + $range); $p++): ?>
        <?php if ($p === $current_page): ?>
            <span class="pg-active"><?= $p ?></span>
        <?php else: ?>
            <a href="<?= page_url($project_id, $p, $filter_status, $search) ?>"><?= $p ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($current_page + $range < $total_pages): ?>
        <?php if ($current_page + $range < $total_pages - 1): ?>
            <span class="pg-disabled">&hellip;</span>
        <?php endif; ?>
        <a href="<?= page_url($project_id, $total_pages, $filter_status, $search) ?>"><?= $total_pages ?></a>
    <?php endif; ?>

    <?php if ($current_page < $total_pages): ?>
        <a href="<?= page_url($project_id, $current_page + 1, $filter_status, $search) ?>">Next &#8594;</a>
    <?php else: ?>
        <span class="pg-disabled">Next &#8594;</span>
    <?php endif; ?>
</div>
<p class="page-info">
    Showing <?= $offset + 1 ?>–<?= min($offset + PER_PAGE, $total_current) ?> of <?= $total_current ?> letters
    &nbsp;|&nbsp; Page <?= $current_page ?> of <?= $total_pages ?>
</p>
<?php endif; ?>

</div><!-- /table-container current -->


<!-- ===================================================
     ARCHIVE LETTERS
     =================================================== -->
<div class="table-container" id="archive-section">
<h3 style="color:#777;">🗂️ Archive Letters (<?= $total_archive ?>)</h3>

<table class="desktop-table">
<thead>
<tr>
    <th>No</th><th>Title</th><th>Received Date</th><th>Letter Date</th><th>Remarks</th>
</tr>
</thead>
<tbody>
<?php if ($archive_rows): $i = $archive_offset + 1; foreach ($archive_rows as $row): ?>
<tr class="data-row" style="opacity:0.6;"
    onclick="window.location='<?= view_url($row['id'], $project_id, '') ?>'">
    <td><?= $i++ ?></td>
    <td><?= htmlspecialchars($row['title']) ?></td>
    <td><?= date('d/m/Y', strtotime($row['received_date'])) ?></td>
    <td><?= date('d/m/Y', strtotime($row['letter_date'])) ?></td>
    <td><?= htmlspecialchars($row['remarks']) ?></td>
</tr>
<?php endforeach; elseif ($total_archive == 0): ?>
<tr><td colspan="5"><div class="empty-state"><div class="icon">📭</div><p>No archive data</p></div></td></tr>
<?php else: ?>
<tr><td colspan="5"><div class="empty-state"><div class="icon">📄</div><p>No archive letters on this page. <a href="<?= archive_page_url($project_id, 1, $filter_status, $search) ?>">Go to page 1</a></p></div></td></tr>
<?php endif; ?>
</tbody>
</table>

<div class="card-list">
<?php if ($archive_rows): foreach ($archive_rows as $row): ?>
<div class="letter-card archive-card"
     onclick="window.location='<?= view_url($row['id'], $project_id, '') ?>'">
    <div class="card-title"><?= htmlspecialchars($row['title']) ?></div>
    <div class="card-meta">
        <span>📥 <?= date('d/m/Y', strtotime($row['received_date'])) ?></span>
        <span>📄 <?= date('d/m/Y', strtotime($row['letter_date'])) ?></span>
    </div>
    <div class="card-footer">
        <span class="card-remarks"><?= htmlspecialchars($row['remarks']) ?></span>
    </div>
</div>
<?php endforeach; elseif ($total_archive == 0): ?>
<div class="empty-state"><div class="icon">📭</div><p>No archive data</p></div>
<?php else: ?>
<div class="empty-state"><div class="icon">📄</div><p>No archive letters on this page. <a href="<?= archive_page_url($project_id, 1, $filter_status, $search) ?>">Go to page 1</a></p></div>
<?php endif; ?>
</div>

<!-- Archive Pagination -->
<?php if ($archive_total_pages > 1):
    $arange = 2;
?>
<div class="pagination">
    <?php if ($archive_page > 1): ?>
        <a href="<?= archive_page_url($project_id, $archive_page - 1, $filter_status, $search) ?>">&#8592; Prev</a>
    <?php else: ?>
        <span class="pg-disabled">&#8592; Prev</span>
    <?php endif; ?>

    <?php if ($archive_page - $arange > 1): ?>
        <a href="<?= archive_page_url($project_id, 1, $filter_status, $search) ?>">1</a>
        <?php if ($archive_page - $arange > 2): ?>
            <span class="pg-disabled">&hellip;</span>
        <?php endif; ?>
    <?php endif; ?>

    <?php for ($p = max(1, $archive_page - $arange); $p <= min($archive_total_pages, $archive_page + $arange); $p++): ?>
        <?php if ($p === $archive_page): ?>
            <span class="pg-active"><?= $p ?></span>
        <?php else: ?>
            <a href="<?= archive_page_url($project_id, $p, $filter_status, $search) ?>"><?= $p ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($archive_page + $arange < $archive_total_pages): ?>
        <?php if ($archive_page + $arange < $archive_total_pages - 1): ?>
            <span class="pg-disabled">&hellip;</span>
        <?php endif; ?>
        <a href="<?= archive_page_url($project_id, $archive_total_pages, $filter_status, $search) ?>"><?= $archive_total_pages ?></a>
    <?php endif; ?>

    <?php if ($archive_page < $archive_total_pages): ?>
        <a href="<?= archive_page_url($project_id, $archive_page + 1, $filter_status, $search) ?>">Next &#8594;</a>
    <?php else: ?>
        <span class="pg-disabled">Next &#8594;</span>
    <?php endif; ?>
</div>
<p class="page-info">
    Showing <?= $archive_offset + 1 ?>–<?= min($archive_offset + PER_PAGE, $total_archive) ?> of <?= $total_archive ?> archived letters
    &nbsp;|&nbsp; Page <?= $archive_page ?> of <?= $archive_total_pages ?>
</p>
<?php endif; ?>

</div><!-- /table-container archive -->

</body>
</html>

<?php
$curr_stmt->close();
$archive_stmt->close();
$db->close();
?>
<?php
session_start();
require_once 'include/msqli_connect.php';


if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];
$cerun_id = intval($_GET['cerun_id'] ?? 0);
$type = $_GET['type'] ?? null;
$yearSel = intval($_GET['year'] ?? 0);
$letterType = $_GET['letter'] ?? null;
$page = max(
    1,
    intval($_GET['page'] ?? 1)
);

$limit = 100;

$offset = ($page - 1) * $limit;
$is_simple = isset($_GET['simple']);

$role = $_SESSION['account_type'] ?? 'Staff';
$back_url = ($role === 'Client Admin' || $role === 'Management' || $role === 'HRA') ? "cerundashboard.php" : "staff_dashboard.php";

// Authorization
$stmt = ($role === 'Client Admin' || $role === 'Management' || $role === 'HRA')
    ? $db->prepare("SELECT region, symbol FROM cerun WHERE id = ?")
    : $db->prepare("SELECT c.region, c.symbol FROM cerun c JOIN user_cerun uc ON c.id = uc.cerun_id WHERE c.id = ? AND uc.user_id = ?");

if ($role === 'Client Admin' || $role === 'Management' || $role === 'HRA') $stmt->bind_param("i", $cerun_id);
else $stmt->bind_param("ii", $cerun_id, $user_id);

$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) die("Unauthorized.");
$cerunRow = $res->fetch_assoc();
$region = $cerunRow['region'];
$symbol = $cerunRow['symbol'] ?? '';
$stmt->close();

$is_simple_cerun = in_array($symbol, ['CKC', 'KL']);

function isDuplicateRN($rn, $counts) {
    $rn = trim($rn ?? '');
    return $rn !== '' && ($counts[$rn] ?? 0) > 1;
}

// CKC/KL don't use type selector — set default so queries don't break
if ($is_simple_cerun && !$type) {
    $type = '6';
}

$years = ($type === '4') ? [2018, 2019, 2020, 2021] : (($type === '6') ? [2022, 2023, 2024, 2025, 2026, 2027] : []);

$lettersIn = []; $lettersPending = []; $lettersProgress = []; $lettersArchive = [];

// 🔥 DASHBOARD DATA (default view)
$pendingCount = 0;
$latestTasks = [];

if (!$letterType && !$showing_letterout) {

    // kira pending
    $stmtDash = $db->prepare("
        SELECT COUNT(*) as total 
        FROM letterin_cerun 
        WHERE cerun_id = ? AND status = 'pending'
    ");
    $stmtDash->bind_param("i", $cerun_id);
    $stmtDash->execute();
    $pendingCount = $stmtDash->get_result()->fetch_assoc()['total'] ?? 0;

    // latest data
    $stmtLatest = $db->prepare("
	SELECT id, title, ref_number, status, verifier
        FROM letterin_cerun 
        WHERE cerun_id = ? AND status = 'pending'
        ORDER BY id DESC
        
    ");
    $stmtLatest->bind_param("i", $cerun_id);
    $stmtLatest->execute();
    $latestTasks = $stmtLatest->get_result()->fetch_all(MYSQLI_ASSOC);
}

if ($letterType === 'in') {
    if ($is_simple_cerun) {
        $docStmt = $db->prepare("
            SELECT l.id, l.letter_date, l.receive_date, l.title, l.ref_number, l.lot, u.full_name, l.status
            FROM letterin_cerun l
            LEFT JOIN users u ON l.created_by = u.id
            WHERE l.cerun_id = ?
            AND l.receive_date >= '2026-04-13'
            ORDER BY l.receive_date DESC
        ");
        $docStmt->bind_param("i", $cerun_id);
    } else {
        $docStmt = $db->prepare("
            SELECT l.id, l.letter_date, l.receive_date, l.title, l.ref_number, l.lot, u.full_name, l.status
            FROM letterin_cerun l
            LEFT JOIN users u ON l.created_by = u.id
            WHERE l.cerun_id = ?
            AND l.type = ?
            AND l.receive_date >= '2026-04-13'
            ORDER BY l.receive_date DESC
        ");
        $docStmt->bind_param("is", $cerun_id, $type);
    }
    $docStmt->execute();
    $lettersIn = $docStmt->get_result()->fetch_all(MYSQLI_ASSOC);


	
if ($is_simple_cerun) {
    $stmtArcIn = $db->prepare("
        SELECT l.id, l.letter_date, l.receive_date, l.title, l.ref_number, l.lot, u.full_name, l.status
        FROM letterin_cerun l
        LEFT JOIN users u ON l.created_by = u.id
        WHERE l.cerun_id = ?
        AND l.receive_date < '2026-04-13'
        ORDER BY l.receive_date DESC
    ");
    $stmtArcIn->bind_param("i", $cerun_id);
} else {
    $stmtArcIn = $db->prepare("
        SELECT l.id, l.letter_date, l.receive_date, l.title, l.ref_number, l.lot, u.full_name, l.status
        FROM letterin_cerun l
        LEFT JOIN users u ON l.created_by = u.id
        WHERE l.cerun_id = ?
        AND l.type = ?
        AND l.receive_date < '2026-04-13'
        ORDER BY l.receive_date DESC
    ");
    $stmtArcIn->bind_param("is", $cerun_id, $type);
}
$stmtArcIn->execute();
$lettersInArchive = $stmtArcIn->get_result()->fetch_all(MYSQLI_ASSOC);

$stmtArcIn->execute();

$lettersInArchive =
    $stmtArcIn->get_result()->fetch_all(MYSQLI_ASSOC);

} elseif ($is_simple && $is_simple_cerun) {
    // CKC / KL — no year filter
    $stmtP = $db->prepare("SELECT *, request_date as letter_date FROM letteroutpending_cerun WHERE cerun_id = ? AND status IN ('submitted','rejected') ORDER BY id DESC");
    $stmtP->bind_param("i", $cerun_id);
    $stmtP->execute();
    $lettersPending = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);

  $stmtA = $db->prepare("
SELECT
    p.*,
    p.request_date as letter_date,
    a.ack_date

FROM letteroutpending_cerun p

LEFT JOIN letteroutacknowledge_cerun a
ON p.id = a.pending_id

WHERE p.cerun_id = ?
AND p.status IN (
    'acknowledged',
    'verified_verifier',
    'verified_user',
    'verified_pm',
    'verified_final',
    'cancelled'
)

ORDER BY p.id DESC
");
    $stmtA->bind_param("i", $cerun_id);
    $stmtA->execute();
    $lettersProgress = $stmtA->get_result()->fetch_all(MYSQLI_ASSOC); 
	


$stmtArc = $db->prepare("
    SELECT *,
           request_date as letter_date
    FROM letteroutarchive_cerun
    WHERE cerun_id = ?
    AND project_year = ?
    ORDER BY request_date DESC, id DESC
");

$stmtArc->bind_param(
    "ii",
    $cerun_id,
    $yearSel
);

$stmtArc->execute();

$lettersArchive =
    $stmtArc
    ->get_result()
    ->fetch_all(MYSQLI_ASSOC);

$totalArchive = count($lettersArchive);

// Duplicate running_number check, scoped to this cerun_id + project_year
$archive_rn_counts = [];
foreach ($lettersArchive as $r) {
    $rn = trim($r['running_number'] ?? '');
    if ($rn === '') continue;
    $archive_rn_counts[$rn] = ($archive_rn_counts[$rn] ?? 0) + 1;
}

} elseif ($yearSel > 0) {
    // Normal cerun — year-based
    $regexPattern = "(\/CKC\/" . $yearSel . "\/|\/KL\/" . $yearSel . "\/|[A-Z0-9]+" . $yearSel . "\/)";

    $stmtP = $db->prepare("SELECT *, request_date as letter_date FROM letteroutpending_cerun WHERE cerun_id = ? AND running_number REGEXP ? AND status IN ('submitted','rejected') ORDER BY id DESC");
    $stmtP->bind_param("is", $cerun_id, $regexPattern);
    $stmtP->execute();
    $lettersPending = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);

   $stmtA = $db->prepare("
SELECT
    p.*,
    p.request_date as letter_date,
    a.ack_date

FROM letteroutpending_cerun p

LEFT JOIN letteroutacknowledge_cerun a
ON p.id = a.pending_id

WHERE p.cerun_id = ?
AND p.running_number REGEXP ?
AND p.status IN (
    'acknowledged',
    'verified_verifier',
    'verified_user',
    'verified_pm',
    'verified_final',
    'cancelled'
)

ORDER BY p.id DESC
");
    $stmtA->bind_param("is", $cerun_id, $regexPattern);
    $stmtA->execute();
    $lettersProgress = $stmtA->get_result()->fetch_all(MYSQLI_ASSOC); 
	

 
$stmtArc = $db->prepare("
    SELECT *,
           request_date as letter_date
    FROM letteroutarchive_cerun
    WHERE cerun_id = ?
	AND project_year = ?
    ORDER BY request_date DESC, id DESC
");

$stmtArc->bind_param(
    "ii",
    $cerun_id,
	$yearSel
);

$stmtArc->execute();

$lettersArchive =
    $stmtArc
    ->get_result()
    ->fetch_all(MYSQLI_ASSOC);

$totalArchive = count($lettersArchive);

// Duplicate running_number check, scoped to this cerun_id + project_year
$archive_rn_counts = [];
foreach ($lettersArchive as $r) {
    $rn = trim($r['running_number'] ?? '');
    if ($rn === '') continue;
    $archive_rn_counts[$rn] = ($archive_rn_counts[$rn] ?? 0) + 1;
}



}

$showing_letterout = ($is_simple && $is_simple_cerun) || ($yearSel > 0);



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --accent: #007bff;
            --accent-hover: #0056b3;
            --bg: #f3f4f6;
            --radius: 16px;
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--bg);
            font-family: "Segoe UI", Arial, sans-serif;
            color: #1e293b;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 0 32px;
        }

        /* ---- Page header (gradient banner, replaces the floating back-btn) ---- */
        .page-header {
            width: 100%;
            background: linear-gradient(135deg, #007bff, #0056b3);
            padding: 20px 0 22px;
            margin-bottom: 22px;
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
        }
        .page-header-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .page-header .back-btn {
            background: rgba(255,255,255,.15);
            color: #fff;
            padding: 9px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            white-space: nowrap;
            transition: background .15s;
        }
        .page-header .back-btn:hover { background: rgba(255,255,255,.28); color: #fff; }
        .page-header .eyebrow {
            color: rgba(255,255,255,.75);
            font-size: 11px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .page-header h1 { color: #fff; font-weight: 800; font-size: 24px; margin: 0; }

        .page-wrap { width: 100%; max-width: 1200px; padding: 0 24px; }

        /* ---- Section card (same pattern as the project-id letters list) ---- */
        .section {
            width: 100%;
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
            font-weight: 800;
            flex: 1 1 100%;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-pill {
            background: var(--accent);
            color: #fff;
            padding: 9px 18px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            font-size: 13px;
            white-space: nowrap;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            cursor: pointer;
        }
        .btn-pill:hover { background: var(--accent-hover); color: #fff; }
        .btn-pill.btn-grey { background: #6c757d; }
        .btn-pill.btn-grey:hover { background: #545b62; }
        .btn-pill.btn-dark2 { background: #1f2937; }
        .btn-pill.btn-dark2:hover { background: #111827; }

        .search-input {
            flex: 1 1 180px;
            min-width: 0;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 14px;
        }
        .search-input:focus { outline: none; border-color: var(--accent); }

        .table-info {
            font-size: 12px;
            color: #888;
            text-align: right;
            margin-bottom: 6px;
        }

        /* ---- Table ---- */
        .table-container {
            overflow-x: auto;
            border: 1px solid #ddd;
            border-radius: 10px;
            max-height: 460px;
            overflow-y: auto;
        }
        .modern-table { width: 100%; border-collapse: collapse; min-width: 560px; margin: 0; font-size: 14px; }
        .modern-table th, .modern-table td {
            padding: 10px 13px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            vertical-align: middle;
        }
        .modern-table thead th {
            background: var(--accent);
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 2;
            white-space: nowrap;
            font-size: 12px;
            text-transform: none;
            letter-spacing: normal;
            padding: 10px 13px;
        }
        .modern-table tbody tr:nth-child(even) { background: #f9f9f9; }
        .modern-table tbody tr:hover { background: #eef5ff !important; cursor: pointer; }

        /* ---- Badges (class names driven by PHP — only colours redefined here) ---- */
        .badge, .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 11px;
            color: #fff;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .bg-pending, .bg-warning   { background: #ffc107; color: #856404; }
        .bg-submitted, .bg-primary { background: #007bff; color: #fff; }
        .bg-acknowledged, .bg-success { background: #28a745; color: #fff; }
        .bg-verified               { background: #17a2b8; color: #fff; }
        .bg-final                  { background: #6610f2; color: #fff; }
        .bg-cancelled, .bg-danger, .bg-rejected { background: #dc3545; color: #fff; }
        .bg-archive                { background: #6610f2; color: #fff; }
        .bg-secondary              { background: #6c757d; color: #fff; }
        .text-dark                 { color: #856404 !important; }

        .lot-badge {
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
        }

        /* ---- Category / folder pill selectors (type-selector, year-btn) ---- */
        .type-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            max-width: 480px;
            margin: 0 auto;
        }
        .type-card {
            padding: 16px;
            text-align: center;
            border: 2px solid #dee2e6;
            border-radius: 12px;
            text-decoration: none;
            color: #333;
            transition: .15s;
            font-weight: 700;
            font-size: 14px;
            background: #e9ecef;
        }
        .type-card:hover { background: #dde2e7; color: #333; }
        .type-card.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        .year-btn {
            padding: 8px 18px;
            border-radius: 50px;
            border: 2px solid #dee2e6;
            background: #e9ecef;
            color: #333;
            text-decoration: none;
            margin: 4px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: .15s;
            font-size: 13px;
            font-weight: 700;
        }
        .year-btn:hover { background: #dde2e7; color: #333; }
        .year-btn.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        /* ---- Dashboard stat card ---- */
        .stat-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 18px;
            text-align: center;
            max-width: 220px;
        }
        .stat-card .stat-label { font-size: 12px; color: #6b7785; margin-bottom: 4px; }
        .stat-card .stat-value { font-size: 30px; font-weight: 800; color: var(--accent); }

        .empty-state { text-align: center; padding: 30px 16px; color: #bbb; font-size: 14px; }

        /* ---- Duplicate running number flag (Archive Letters) ---- */
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

        .search-group { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }

        @media (max-width: 640px) {
            .page-wrap { padding: 0 12px; }
            .section { padding: 12px; border-radius: 12px; }
        }
    </style>
</head>
<body>

    <div class="page-header">
        <div class="page-header-inner">
            <a href="<?= $back_url ?>" class="back-btn"><i class="bi bi-arrow-left"></i> Back</a>
            <div>
                <div class="eyebrow"><?= ($role === 'Client Admin') ? 'Admin Access' : 'Region' ?></div>
                <h1><?= htmlspecialchars($region) ?></h1>
            </div>
        </div>
    </div>

    <div class="page-wrap">

            <!-- hidden for CKC / KL -->
            <?php if (!$is_simple_cerun): ?>
                <div class="section">
                <h2 style="margin-bottom:14px;">1. Choose Category</h2>
                <div class="type-selector">
                    <a href="cerun.php?cerun_id=<?= $cerun_id ?>&type=4" class="type-card <?= $type == '4' ? 'active' : '' ?>">
                        <i class="bi bi-calendar4-event d-block mb-1 fs-3"></i> Cerun 2018 - 2021
                    </a>
                    <a href="cerun.php?cerun_id=<?= $cerun_id ?>&type=6" class="type-card <?= $type == '6' ? 'active' : '' ?>">
                        <i class="bi bi-calendar-range d-block mb-1 fs-3"></i> Cerun 2022 - 2027
                    </a>
                </div>
                </div>
            <?php endif; ?>

            <!-- Folder buttons -->
            <?php if ($type || $is_simple_cerun): ?>
                <div class="section">
                <h2 style="margin-bottom:14px;"><?= $is_simple_cerun ? '1.' : '2.' ?> Choose Folder</h2>
                <div class="text-center mb-1">

                    <!-- Letter In -->
                    <a href="cerun.php?cerun_id=<?= $cerun_id ?>&type=<?= $type ?>&letter=in"
                       class="year-btn <?= ($letterType === 'in') ? 'active' : '' ?>">
                        <i class="bi bi-folder2-open"></i> Letter In
                    </a>

                    <?php if ($is_simple_cerun): ?>
                        <!-- CKC / KL -->
                        <a href="cerun.php?cerun_id=<?= $cerun_id ?>&type=<?= $type ?>&simple=1"
                           class="year-btn <?= ($is_simple && !$letterType) ? 'active' : '' ?>">
                            <i class="bi bi-folder2-open"></i> Letter Out
                        </a>

                    <?php else: ?>
                        <!-- Other regions -->
                        <?php foreach ($years as $y): ?>
                            <a href="cerun.php?cerun_id=<?= $cerun_id ?>&type=<?= $type ?>&year=<?= $y ?>"
                               class="year-btn <?= ($yearSel == $y && !$letterType) ? 'active' : '' ?>">
                                <i class="bi bi-folder2-open"></i> <?= $y ?> - Letter Out
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
                </div>
            <?php endif; ?>
			
			<?php // if (!$letterType && !$showing_letterout): ?>
			
 <? if (!$type && !$letterType && !$showing_letterout): ?>
<div class="section">
<h2 class="mb-3">
    <i class="bi bi-bar-chart-line"></i>
    Dashboard Overview
</h2>

<!-- Pending Card -->
<div style="margin-bottom:20px;">
    <div class="stat-card">
        <div class="stat-label">Pending Task</div>
        <div class="stat-value">
            <?= $pendingCount ?>
        </div>
    </div>
</div>

<!-- Latest Table -->
<div class="table-container">
    <table class="modern-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Ref No</th>
				<th>Action By</th> 
                <th>Status</th>
            </tr>
        </thead>
								  <tbody>
			<?php if (!empty($latestTasks)): foreach ($latestTasks as $row): ?>
				<tr onclick="window.location='cerun_detail.php?cerun_id=<?= $cerun_id ?>&letter_id=<?= $row['id'] ?>&source=in';">

					<td><?= htmlspecialchars($row['title']) ?></td>

					<td><?= htmlspecialchars($row['ref_number']) ?></td>

					<!-- 👇 TAMBAH NI -->
					<td class="text-muted small">
						<?= htmlspecialchars($row['verifier'] ?? '-') ?>
					</td>

					<td>
						<span class="status-badge bg-pending">
							Pending
						</span>
					</td>

				</tr>
			<?php endforeach; else: ?>
				<tr>
					<td colspan="4"><div class="empty-state">No data available</div></td>
				</tr>
			<?php endif; ?>
			</tbody>
    </table>
</div>
</div>

<?php endif; ?>

            <!-- LETTER IN -->
            <?php if ($letterType === 'in'):
                $total = count($lettersIn);
                $start = $total > 0 ? 1 : 0;
            ?>
                <div class="section">
                <div class="section-header">
                    <h2><i class="bi bi-arrow-down-left-circle"></i> Letter In</h2>
                    <a href="addletterin_cerun.php?cerun_id=<?= $cerun_id ?>&type=<?= $type ?>" class="btn-pill">
                        <i class="bi bi-plus-lg"></i> Add New
                    </a>
                    <input type="text" class="search-input" placeholder="Search incoming letters..." onkeyup="filterTable(this)">
                </div>
                <div class="table-info"><?= $start . "-" . $total . " of " . $total ?></div>

                <div class="table-container">
                    <table class="modern-table">
                        <thead>
						<tr>
							<th>No</th>
							<th>Title</th>
							<th>Received Date</th>
							<th>Letter Date</th>
							<th>Ref No.</th>
							<th>Lot</th>
							<th>Status</th>
						</tr>
                        </thead>
						<tbody>
							<?php if (!empty($lettersIn)): 
								$no = 1;
								foreach ($lettersIn as $row): 

									$status = strtolower($row['status'] ?? '');

									if ($status == 'verified' || $status == 'verified_verifier') {
										$badgeClass = 'bg-success';
										$label = 'Verified';

									} elseif ($status == 'acknowledged') {
										$badgeClass = 'bg-primary';
										$label = 'Acknowledged';

									} elseif ($status == 'rejected') {
										$badgeClass = 'bg-danger';
										$label = 'Rejected';

									} else {
										$badgeClass = 'bg-warning text-dark';
										$label = 'Pending';
									}

							?> 
							<tr onclick="window.location='cerun_detail.php?cerun_id=<?= $cerun_id ?>&letter_id=<?= $row['id'] ?>&source=in';">
								<td><?= $no++ ?></td>
								<td class="fw-bold text-primary"><?= htmlspecialchars($row['title']) ?></td>
								<td><?= date('d/m/Y', strtotime($row['receive_date'])) ?></td>
								<td><?= date('d/m/Y', strtotime($row['letter_date'])) ?></td>
								<td class="text-muted small"><?= htmlspecialchars($row['ref_number']) ?></td>
								<td><span class="lot-badge"><?= htmlspecialchars($row['lot']) ?></span></td>
								<td>
									<span class="badge <?= $badgeClass ?>">
										<?= $label ?>
									</span>
								</td>
							</tr>
							<?php endforeach; else: ?>
							<tr><td colspan="7"><div class="empty-state">No incoming letters found.</div></td></tr>
							<?php endif; ?>
							</tbody>
                    </table>
                </div>
                </div>

			<div class="section">
			<h2 class="mb-3">
    <i class="bi bi-archive"></i>
    Letter In Archive
</h2>

<div class="search-group">

    <input type="text"
           class="search-input"
           placeholder="Search archive letters..."
           onkeyup="filterTable(this)">

</div>

<div class="table-container">

<table class="modern-table">

<thead>
<tr>
    <th>No</th>
    <th>Title</th>
    <th>Received Date</th>
    <th>Letter Date</th>
    <th>Ref No.</th>
    <th>Lot</th>
    <th>Status</th>
</tr>
</thead>

<tbody>

<?php if (!empty($lettersInArchive)):

    $no = 1;

    foreach ($lettersInArchive as $row):
?>

<tr onclick="
window.location='cerun_detail.php?cerun_id=<?= $cerun_id ?>&letter_id=<?= $row['id'] ?>&source=in';
">

<td><?= $no++ ?></td>

<td class="fw-bold text-dark">
	<?= htmlspecialchars($row['title']) ?>
</td>

<td>
<?= date('d/m/Y', strtotime($row['receive_date'])) ?>
</td>

<td>
<?= date('d/m/Y', strtotime($row['letter_date'])) ?>
</td>

<td class="text-muted small">
<?= htmlspecialchars($row['ref_number']) ?>
</td>

<td>
<span class="lot-badge">
<?= htmlspecialchars($row['lot']) ?>
</span>
</td>

<td>
<span class="badge bg-secondary">
Archived
</span>
</td>

</tr>

<?php endforeach; else: ?>

<tr>
<td colspan="7">
<div class="empty-state">
No archived letters found.
</div>
</td>
</tr>

<?php endif; ?>

</tbody>
</table>
</div>
</div>

            <!-- LETTER OUT -->
            <?php elseif ($showing_letterout):
                $totalP    = count($lettersPending);   $startP    = $totalP > 0 ? 1 : 0;
                $totalProg = count($lettersProgress);  $startProg = $totalProg > 0 ? 1 : 0;
                $totalArc  = count($lettersArchive);   $startArc  = $totalArc > 0 ? 1 : 0;
                $add_url   = $is_simple_cerun
                    ? "addletteroutpending_cerun.php?cerun_id={$cerun_id}&type={$type}&simple=1"
                    : "addletteroutpending_cerun.php?cerun_id={$cerun_id}&type={$type}&year={$yearSel}";
                $rn_url    = $is_simple_cerun
                    ? "cerun_rn_settings.php?cerun_id={$cerun_id}&type={$type}&simple=1"
                    : "cerun_rn_settings.php?cerun_id={$cerun_id}&type={$type}&year={$yearSel}";
                $arc_url   = $is_simple_cerun
                    ? "addletteroutarchive_cerun.php?cerun_id={$cerun_id}&type={$type}&simple=1"
                    : "addletteroutarchive_cerun.php?cerun_id={$cerun_id}&type={$type}&year={$yearSel}";
                $heading   = $is_simple_cerun ? "Submitted Letters" : "Submitted Letters ({$yearSel})";
            ?>

                <div class="section">
                <div class="section-header">
                    <h2><i class="bi bi-file-earmark-check"></i> <?= $heading ?></h2>
                    <a href="<?= $add_url ?>" class="btn-pill">
                        <i class="bi bi-plus-circle"></i> Add New
                    </a>
                    <a href="<?= $rn_url ?>" class="btn-pill btn-grey">
                        ⚙️ Running Number Settings
                    </a>
                    <input type="text" class="search-input" placeholder="Search submitted letters..." onkeyup="filterTable(this)">
                </div>
                <div class="table-info"><?= $startP . "-" . $totalP . " of " . $totalP ?></div>

                <div class="table-container">
                    <table class="modern-table">
                        <thead>
                            <tr><th>Running No.</th><th>Req. Date</th><th>Title</th><th>Agency</th><th class="text-center">Status</th><th>Balance</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($lettersPending): foreach ($lettersPending as $row):
                                $createdDate = new DateTime($row['created_at']);
                                $today = (new DateTime())->setTime(0, 0, 0);
                                $daysPassed = $today->diff($createdDate->setTime(0, 0, 0))->days;
                                $balance = 5 - $daysPassed;
                                $balDisplay = ($balance < 0) ? "- " . abs($balance) : $balance;
                            ?>
                                <tr onclick="window.location='viewletteroutsubmitted_cerun.php?id=<?= $row['id'] ?>';">
                                    <td class="fw-bold"><?= htmlspecialchars($row['running_number']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($row['letter_date'])) ?></td>
                                    <td class="text-primary fw-bold"><?= htmlspecialchars($row['title']) ?></td>
                                    <td><?= htmlspecialchars($row['agency']) ?></td>
                                    <td class="text-center">
                                        <span class="status-badge bg-<?= $row['status'] ?>">
                                            <i class="bi bi-clock-history"></i> <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold <?= $balance < 0 ? 'text-danger' : 'text-success' ?>">
                                        <i class="bi bi-calendar-event me-1"></i><?= $balDisplay ?>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6"><div class="empty-state">No submitted letters found.</div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                </div>

                <div class="section">
                <div class="section-header">
                    <h2><i class="bi bi-check2-all"></i> Acknowledge / Progress</h2>
                    <input type="text" class="search-input" placeholder="Search progress letters..." onkeyup="filterTable(this)">
                </div>
                <div class="table-info"><?= $startProg . "-" . $totalProg . " of " . $totalProg ?></div>

                <div class="table-container">
                    <table class="modern-table">
                        <thead>
                            <tr><th>Running No.</th><th>Req. Date</th><th>Ack. Date</th><th>Title</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($lettersProgress): foreach ($lettersProgress as $row):
                                $statusRaw = $row['status'];
                                $badgeClass = (strpos($statusRaw, 'verified') !== false)
                                    ? (($statusRaw === 'verified_final') ? 'bg-final' : 'bg-verified')
                                    : 'bg-' . $statusRaw;
                            ?>
                                <tr onclick="window.location='viewletteroutacknowledged_cerun.php?id=<?= $row['id'] ?>';">
                                    <td class="fw-bold text-muted"><?= htmlspecialchars($row['running_number']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($row['letter_date'])) ?></td>
									<td>
<?= !empty($row['ack_date']) 
    ? date('d/m/Y', strtotime($row['ack_date'])) 
    : '-' ?>
</td>
                            <td class="text-primary fw-bold"><?= htmlspecialchars($row['title']) ?></td>
                                    <td>
                                        <span class="status-badge <?= $badgeClass ?>">
                                            <i class="bi bi-shield-check"></i> <?= str_replace('_', ' ', $row['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="4"><div class="empty-state">No letters in progress.</div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                </div>

                <div class="section">
                <div class="section-header">
                    <h2><i class="bi bi-archive"></i> Archive Letters</h2>
                    <a href="<?= $arc_url ?>" class="btn-pill btn-dark2">
                        <i class="bi bi-file-earmark-zip"></i> Add Archive
                    </a>
                    <input type="text" class="search-input" placeholder="Search archive letters..." onkeyup="filterTable(this)">
                </div>
                <div class="table-info" style="text-align:right; margin-bottom:6px;">
                    <?= $totalArchive ?> letter(s)
                    <?php
                    $dup_count = 0;
                    foreach ($archive_rn_counts as $c) { if ($c > 1) $dup_count += $c; }
                    if ($dup_count > 0):
                    ?>
                        &mdash; <span style="color:#856404; font-weight:bold;">⚠️ <?= $dup_count ?> with duplicate running number</span>
                    <?php endif; ?>
                </div>

                <div class="table-container">
                    <table class="modern-table">
                        <thead>
                            <tr><th>Running No.</th><th>Letter Date</th><th>Title</th><th>Agency</th><th class="text-center">Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($lettersArchive)): foreach ($lettersArchive as $row):
                                $is_dup = isDuplicateRN($row['running_number'], $archive_rn_counts);
                            ?>
                                <tr class="<?= $is_dup ? 'dup-row' : '' ?>" onclick="window.location='viewletteroutarchive_cerun.php?id=<?= $row['id'] ?>';">
                                    <td class="fw-bold text-muted">
                                        <?= htmlspecialchars($row['running_number']) ?>
                                        <?php if ($is_dup): ?>
                                            <span class="dup-badge" title="This running number appears more than once">⚠️ Duplicate</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($row['letter_date'])) ?></td>
									
                                    <td class="text-primary fw-bold"><?= htmlspecialchars($row['title']) ?></td>
                                    <td><?= htmlspecialchars($row['agency']) ?></td>
                                    <td class="text-center"><span class="status-badge bg-archive">Archived</span></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="5"><div class="empty-state">No archived letters found.</div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                </div>


            <?php endif; ?>

    </div>

    <script>
    function filterTable(input) {
        const filter = input.value.toLowerCase();
        const section = input.closest('.section');
        const table = section.querySelector('table');
        if (!table || !table.tBodies[0]) return;
        const trs = table.tBodies[0].rows;
        for (let i = 0; i < trs.length; i++) {
            if (trs[i].cells.length <= 1) continue;
            const rowText = trs[i].textContent.toLowerCase();
            trs[i].style.display = rowText.includes(filter) ? '' : 'none';
        }
    }
    </script>
</body>
</html>
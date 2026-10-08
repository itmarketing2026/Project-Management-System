<?php
session_start();
require_once 'include/msqli_connect.php';


if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];
$cerun_id = intval($_GET['cerun_id'] ?? 0);
$type = $_GET['type'] ?? null;
$yearSel = intval($_GET['year'] ?? 0);
$letterType = $_GET['letter'] ?? null;
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

// CKC/KL don't use type selector — set default so queries don't break
if ($is_simple_cerun && !$type) {
    $type = '6';
}

$years = ($type === '4') ? [2018, 2019, 2020, 2021] : (($type === '6') ? [2022, 2023, 2024, 2025, 2026, 2027] : []);

$lettersIn = []; $lettersPending = []; $lettersProgress = []; $lettersArchive = [];

if ($letterType === 'in') {
    $docStmt = $db->prepare("
        SELECT l.id, l.letter_date, l.receive_date, l.title, l.ref_number, l.lot, u.full_name 
        FROM letterin_cerun l
        LEFT JOIN users u ON l.created_by = u.id
        WHERE l.cerun_id = ? AND l.type = ?
        ORDER BY l.receive_date DESC
    ");
    $docStmt->bind_param("is", $cerun_id, $type);
    $docStmt->execute();
    $lettersIn = $docStmt->get_result()->fetch_all(MYSQLI_ASSOC);

} elseif ($is_simple && $is_simple_cerun) {
    // CKC / KL — no year filter
    $stmtP = $db->prepare("SELECT *, request_date as letter_date FROM letteroutpending_cerun WHERE cerun_id = ? AND status IN ('submitted','rejected') ORDER BY id DESC");
    $stmtP->bind_param("i", $cerun_id);
    $stmtP->execute();
    $lettersPending = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);

   $stmtA = $db->prepare("SELECT *, request_date as letter_date FROM letteroutpending_cerun WHERE cerun_id = ? AND status IN ('acknowledged','verified_verifier','verified_user','verified_pm','verified_final','cancelled') ORDER BY id DESC");
    $stmtA->bind_param("i", $cerun_id);
    $stmtA->execute();
    $lettersProgress = $stmtA->get_result()->fetch_all(MYSQLI_ASSOC); 
	


    $stmtArc = $db->prepare("SELECT *, request_date as letter_date FROM letteroutarchive_cerun WHERE cerun_id = ? ORDER BY id DESC");
    $stmtArc->bind_param("i", $cerun_id);
    $stmtArc->execute();
    $lettersArchive = $stmtArc->get_result()->fetch_all(MYSQLI_ASSOC);

} elseif ($yearSel > 0) {
    // Normal cerun — year-based
    $regexPattern = "(\/CKC\/" . $yearSel . "\/|\/KL\/" . $yearSel . "\/|[A-Z0-9]+" . $yearSel . "\/)";

    $stmtP = $db->prepare("SELECT *, request_date as letter_date FROM letteroutpending_cerun WHERE cerun_id = ? AND running_number REGEXP ? AND status IN ('submitted','rejected') ORDER BY id DESC");
    $stmtP->bind_param("is", $cerun_id, $regexPattern);
    $stmtP->execute();
    $lettersPending = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmtA = $db->prepare("SELECT *, request_date as letter_date FROM letteroutpending_cerun WHERE cerun_id = ? AND running_number REGEXP ? AND status IN ('acknowledged','verified_verifier','verified_user','verified_pm','verified_final','cancelled') ORDER BY id DESC");
    $stmtA->bind_param("is", $cerun_id, $regexPattern);
    $stmtA->execute();
    $lettersProgress = $stmtA->get_result()->fetch_all(MYSQLI_ASSOC); 
	

 
$stmtArc = $db->prepare("
  SELECT *, request_date as letter_date 
  FROM letteroutarchive_cerun 
  WHERE cerun_id = ?
  AND project_year = ?
  ORDER BY request_date DESC, id DESC
");

$stmtArc->bind_param("ii", $cerun_id, $yearSel);
$stmtArc->execute();
$lettersArchive = $stmtArc->get_result()->fetch_all(MYSQLI_ASSOC);



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
        :root { --primary: #007bff; --surface: #ffffff; --background: #f4f7fa; --text-main: #1e293b; --text-muted: #64748b; }
        body { background-color: var(--background); font-family: 'Inter', sans-serif; color: var(--text-main); }
        .back-btn { position: fixed; top: 20px; left: 20px; z-index: 1000; background: var(--primary); color: #fff; padding: 10px 24px; border-radius: 12px; text-decoration: none; font-weight: 600; box-shadow: 0 4px 12px rgba(0, 123, 255, 0.25); transition: 0.3s; }
        .back-btn:hover { transform: translateX(-5px); color: white; }
        .glass-card { background: var(--surface); border-radius: 24px; border: 2px solid var(--primary); padding: 40px; margin-top: 80px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.04); }
        .type-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; max-width: 500px; margin: 0 auto 30px; }
        .type-card { padding: 18px; text-align: center; border: 2px solid #e2e8f0; border-radius: 16px; text-decoration: none; color: var(--text-muted); transition: 0.3s; font-weight: 600; }
        .type-card.active { background: var(--primary); color: #fff; border-color: var(--primary); box-shadow: 0 8px 16px rgba(0, 123, 255, 0.2); }
        .year-btn { padding: 10px 22px; border-radius: 50px; border: 1px solid #e2e8f0; background: #fff; color: var(--text-muted); text-decoration: none; margin: 5px; display: inline-block; transition: 0.2s; font-size: 0.9rem; font-weight: 500; }
        .year-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .search-group { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .search-input { flex: 1; max-width: 300px; padding: 8px 15px; border-radius: 10px; border: 1px solid #e2e8f0; outline: none; transition: 0.3s; }
        .search-input:focus { border-color: var(--primary); }
        .table-info { margin-left: auto; font-size: 0.85rem; color: var(--text-muted); font-weight: 600; white-space: nowrap; }
        .table-container { border: 1px solid #eef2f6; border-radius: 16px; overflow: hidden; margin-bottom: 40px; background: white; }
        .modern-table thead th { background: #f8fafc; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 16px; border-bottom: 2px solid #f1f5f9; }
        .modern-table tbody tr { cursor: pointer; transition: 0.2s; }
        .modern-table tbody tr:hover { background-color: #f8fbff !important; }
        .status-badge { padding: 6px 14px; border-radius: 50px; font-weight: 700; font-size: 0.72rem; color: #fff; text-transform: uppercase; display: inline-flex; align-items: center; gap: 5px; }
        .bg-pending { background: #a0522d; }
        .bg-submitted { background: #007bff; }
        .bg-acknowledged { background: #28a745; }
        .bg-verified { background: #fd7e14; }
        .bg-final { background: #198754; }
        .bg-cancelled { background: #6c757d; }
        .bg-rejected { background: #dc3545; }
        .bg-archive { background: #6610f2; }
        .lot-badge { background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 8px; font-weight: 700; font-size: 0.8rem; }
        .section-label { font-size: 0.7rem; text-transform: uppercase; color: #94a3b8; font-weight: 800; text-align: center; display: block; margin-bottom: 12px; letter-spacing: 2px; }
    </style>
</head>
<body>

    <a href="<?= $back_url ?>" class="back-btn"><i class="bi bi-arrow-left"></i> Back</a>

    <div class="container pb-5">
        <div class="glass-card mx-auto" style="max-width: 1200px;">

            <div class="text-center mb-5">
                <h6 class="text-primary fw-bold mb-1" style="letter-spacing: 3px;">
                    <?= ($role === 'Client Admin') ? 'ADMIN ACCESS' : 'REGION' ?>
                </h6>
                <h1 class="fw-bold" style="color: #0f172a;"><?= htmlspecialchars($region) ?></h1>
            </div>

            <!-- hidden for CKC / KL -->
            <?php if (!$is_simple_cerun): ?>
                <span class="section-label">1. Choose Category</span>
                <div class="type-selector">
                    <a href="cerun.php?cerun_id=<?= $cerun_id ?>&type=4" class="type-card <?= $type == '4' ? 'active' : '' ?>">
                        <i class="bi bi-calendar4-event d-block mb-1 fs-3"></i> Cerun 2018 - 2021
                    </a>
                    <a href="cerun.php?cerun_id=<?= $cerun_id ?>&type=6" class="type-card <?= $type == '6' ? 'active' : '' ?>">
                        <i class="bi bi-calendar-range d-block mb-1 fs-3"></i> Cerun 2022 - 2027
                    </a>
                </div>
            <?php endif; ?>

            <!-- Folder buttons -->
            <?php if ($type || $is_simple_cerun): ?>
                <span class="section-label"><?= $is_simple_cerun ? '1.' : '2.' ?> Choose Folder</span>
                <div class="text-center mb-4">

                    <!-- Letter In -->
                    <a href="cerun.php?cerun_id=<?= $cerun_id ?>&type=<?= $type ?>&letter=in"
                       class="year-btn <?= ($letterType === 'in') ? 'active' : '' ?>">
                        <i class="bi bi-folder2-open me-1"></i> Letter In
                    </a>

                    <?php if ($is_simple_cerun): ?>
                        <!-- CKC / KL -->
                        <a href="cerun.php?cerun_id=<?= $cerun_id ?>&type=<?= $type ?>&simple=1"
                           class="year-btn <?= ($is_simple && !$letterType) ? 'active' : '' ?>">
                            <i class="bi bi-folder2-open me-1"></i> Letter Out
                        </a>

                    <?php else: ?>
                        <!-- Other regions -->
                        <?php foreach ($years as $y): ?>
                            <a href="cerun.php?cerun_id=<?= $cerun_id ?>&type=<?= $type ?>&year=<?= $y ?>"
                               class="year-btn <?= ($yearSel == $y && !$letterType) ? 'active' : '' ?>">
                                <i class="bi bi-folder2-open me-1"></i> <?= $y ?> - Letter Out
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

            <!-- LETTER IN -->
            <?php if ($letterType === 'in'):
                $total = count($lettersIn);
                $start = $total > 0 ? 1 : 0;
            ?>
                <h4 class="fw-bold mb-3 mt-4"><i class="bi bi-arrow-down-left-circle text-primary me-2"></i>Letter In</h4>

                <div class="search-group">
                    <a href="addletterin_cerun.php?cerun_id=<?= $cerun_id ?>&type=<?= $type ?>" class="btn btn-primary px-4 rounded-pill fw-bold">
                        <i class="bi bi-plus-lg"></i> Add New
                    </a>
                    <input type="text" class="search-input" placeholder="Search incoming letters..." onkeyup="filterTable(this)">
                    <div class="table-info"><?= $start . "-" . $total . " of " . $total ?></div>
                </div>

                <div class="table-container">
                    <table class="table modern-table">
                        <thead>
                            <tr><th>Title</th><th>Received Date</th><th>Letter Date</th><th>Ref No.</th><th>Lot</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($lettersIn)): foreach ($lettersIn as $row): ?>
                                <tr onclick="window.location='cerun_detail.php?cerun_id=<?= $cerun_id ?>&letter_id=<?= $row['id'] ?>&source=in';">
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($row['title']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($row['receive_date'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($row['letter_date'])) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($row['ref_number']) ?></td>
                                    <td><span class="lot-badge"><?= htmlspecialchars($row['lot']) ?></span></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No incoming letters found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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

                <h4 class="fw-bold mb-3 mt-4">
                    <i class="bi bi-file-earmark-check text-primary me-2"></i><?= $heading ?>
                </h4>
                <div class="search-group">
                    <a href="<?= $add_url ?>" class="btn btn-primary px-4 rounded-pill fw-bold">
                        <i class="bi bi-plus-circle me-1"></i> Add New
                    </a>
                    <a href="<?= $rn_url ?>" class="btn" style="background:#6c757d; color:#fff; margin-left:8px;">
                        ⚙️ Running Number Settings
                    </a>
                    <input type="text" class="search-input" placeholder="Search submitted letters..." onkeyup="filterTable(this)">
                    <div class="table-info"><?= $startP . "-" . $totalP . " of " . $totalP ?></div>
                </div>

                <div class="table-container">
                    <table class="table modern-table">
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
                                <tr><td colspan="6" class="text-center py-5 text-muted">No submitted letters found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <h4 class="fw-bold mb-3 mt-5"><i class="bi bi-check2-all text-success me-2"></i>Acknowledge / Progress</h4>
                <div class="search-group">
                    <input type="text" class="search-input" placeholder="Search progress letters..." onkeyup="filterTable(this)">
                    <div class="table-info"><?= $startProg . "-" . $totalProg . " of " . $totalProg ?></div>
                </div>

                <div class="table-container">
                    <table class="table modern-table">
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
                                <tr><td colspan="4" class="text-center py-5 text-muted">No letters in progress.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <h4 class="fw-bold mb-3 mt-5">
                    <i class="bi bi-archive me-2" style="color:#6610f2;"></i>Archive Letters
                </h4>
                <div class="search-group">
                    <a href="<?= $arc_url ?>" class="btn btn-dark px-4 rounded-pill fw-bold">
                        <i class="bi bi-file-earmark-zip me-1"></i> Add Archive
                    </a>
                    <input type="text" class="search-input" placeholder="Search archive letters..." onkeyup="filterTable(this)">
                    <div class="table-info"><?= $startArc . "-" . $totalArc . " of " . $totalArc ?></div>
                </div>

                <div class="table-container">
                    <table class="table modern-table">
                        <thead>
                            <tr><th>Running No.</th><th>Req. Date</th><th>Title</th><th>Agency</th><th class="text-center">Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($lettersArchive)): foreach ($lettersArchive as $row): ?>
                                <tr onclick="window.location='viewletteroutarchive_cerun.php?id=<?= $row['id'] ?>';">
                                    <td class="fw-bold text-muted"><?= htmlspecialchars($row['running_number']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($row['letter_date'])) ?></td>
                                    <td class="text-primary fw-bold"><?= htmlspecialchars($row['title']) ?></td>
                                    <td><?= htmlspecialchars($row['agency']) ?></td>
                                    <td class="text-center"><span class="status-badge bg-archive">Archived</span></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No archived letters found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </div>
    </div>

    <script>
    function filterTable(input) {
        const filter = input.value.toLowerCase();
        const container = input.parentElement.nextElementSibling;
        const table = container.querySelector('table');
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
<?php
session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$title = 'Reports';

/* =========================
   WEEK LOGIC
========================= */

// KHAMIS tukar cycle weekly report

$today_day = date('N'); // 1=Mon ... 7=Sun

if ($today_day >= 4) {

    // KHAMIS - AHAD = minggu semasa
    $target_start = date('Y-m-d', strtotime('monday this week'));
    $target_end   = date('Y-m-d', strtotime('sunday this week'));

} else {

    // ISNIN - RABU = minggu lepas
    $target_start = date('Y-m-d', strtotime('monday last week'));
    $target_end   = date('Y-m-d', strtotime('sunday last week'));
}

/* =========================
   QUERY (LATEST REPORT IN TARGET WEEK)
========================= */

$query = "
SELECT 
    p.id,
    p.project_name,

    wr.id AS report_id,
    wr.report_date,
    wr.physical_progress,
    wr.pusb,
    wr.pm,
    wr.cm,
    wr.re,
    wr.signature,
    wr.status

FROM project p

LEFT JOIN weekly_reports wr 
ON wr.id = (
    SELECT MAX(id)
    FROM weekly_reports
    WHERE project_id = p.id
    AND report_date BETWEEN '$target_start' AND '$target_end'
)

WHERE p.status = 'Ongoing'

ORDER BY p.project_name ASC
";

$result = $db->query($query);
?>

<!DOCTYPE html>
<html>
<head>
<title>Weekly Report Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
body {
    background:#f4f6f9;
    font-family:'Segoe UI', Arial;
}

.card {
    border-radius:16px;
    border:none;
}

/* HEADER */
.dashboard-title {
    font-weight:600;
    font-size:20px;
}

/* TABLE */
.table {
    background:white;
    border-radius:12px;
    overflow:hidden;
}

.table thead {
    background:#1f2d3d;
    color:white;
}

.table tbody tr:hover {
    background:#f1f3f5;
}

/* PROGRESS */
.progress-red { 
    color:#dc3545; 
    font-weight:600; 
}

.progress-yellow { 
    color:#ffc107; 
    font-weight:600; 
}

.progress-green { 
    color:#28a745; 
    font-weight:600; 
}

/* HIGHLIGHT */
.row-missing {
    background-color:#ffe5e5 !important;
}

/* BADGE */
.badge-ok { 
    background:#28a745; 
}

.badge-late { 
    background:#dc3545; 
}

.badge-none { 
    background:#6c757d; 
}

/* BUTTON */
.btn-view {
    border-radius:8px;
}

@media print {

    @page {
        size: A4 portrait;
        margin: 4mm;
    }

    html,
    body {
        width: 100%;
        height: 100%;
        overflow: hidden;
    }

    body {
        background: #fff !important;
    }

    .btn,
    #searchInput {
        display: none !important;
    }

    .container {
        width: 135% !important;
        max-width: 135% !important;

        transform: scale(0.74);
        transform-origin: top left;

        padding: 0 !important;
        margin: 0 !important;
    }

    .card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }

    .dashboard-title {
        font-size: 24px;
        margin-bottom: 15px;
    }

    .table-responsive {
        overflow: visible !important;
    }

    .table {
        width: 100% !important;
        table-layout: fixed;
        font-size: 11px;
    }

    .table th,
    .table td {
        padding: 6px !important;
        white-space: normal !important;
        word-break: break-word;
        vertical-align: middle;
    }

    tr,
    td,
    th {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .table th:nth-child(1),
    .table td:nth-child(1) {
        width: 3% !important;
        text-align: center;
    }

    .table th:nth-child(2),
    .table td:nth-child(2) {
        width: 22% !important;
    }

    .table th:nth-child(3),
    .table td:nth-child(3) {
        width: 10% !important;
    }

    .table th:nth-child(4),
    .table td:nth-child(4) {
        width: 16% !important;
    }

    .table th:nth-child(5),
    .table td:nth-child(5) {
        width: 16% !important;
    }

    .table th:nth-child(6),
    .table td:nth-child(6) {
        width: 16% !important;
    }

    .table th:nth-child(7),
    .table td:nth-child(7) {
        width: 10% !important;
    }

    .table th:nth-child(8),
    .table td:nth-child(8) {
        width: 8% !important;
        text-align: center;
    }

    .table th:nth-child(9),
    .table td:nth-child(9) {
        width: 9% !important;
        text-align: center;
    }

    .table th:nth-child(10),
    .table td:nth-child(10) {
        width: 5% !important;
        text-align: center;
    }

    .row-missing {
        background: #ffdede !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

}
</style>

</head>

<body>

<?php include 'include/topbar_report.php'; ?>
	
<div class="container py-5">

<div class="card shadow p-4">

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">

    <div class="dashboard-title">
        <i class="bi bi-graph-up"></i> Weekly Report Dashboard
    </div>

    <div class="d-flex gap-2">

        <button onclick="window.print()" 
                class="btn btn-outline-dark btn-sm">
            <i class="bi bi-printer"></i> Print
        </button>

    </div>

</div>

<div class="d-flex justify-content-between align-items-center mb-3">

    <div class="mb-3">
        <input type="text" 
               id="searchInput" 
               class="form-control form-control-sm"
               placeholder="Search project / PM / CM / RE..."
               style="max-width:300px;">
    </div>

</div>

<!-- TABLE -->
<div class="table-responsive">

<table class="table table-hover align-middle">

<thead>
<tr>
    <th>#</th>
    <th>Project</th>
    <th>Company</th>
    <th>PM</th>
    <th>CM</th>
    <th>RE</th>
    <th>Date</th>
    <th>Progress</th>
    <th>Status</th>
    <th></th>
</tr>
</thead>

<tbody>

<?php $no=1; while($row = $result->fetch_assoc()): ?>

<?php

$row_class = '';
$status = '';

$progress = $row['physical_progress'] ?? 0;

/* =========================
   PROGRESS COLOR
========================= */

if ($progress < 30) {

    $progress_class = 'progress-red';

} elseif ($progress < 70) {

    $progress_class = 'progress-yellow';

} else {

    $progress_class = 'progress-green';
}

/* =========================
   REPORT STATUS
========================= */

if (!$row['report_id']) {

    $row_class = 'row-missing';
    $status = '<span class="badge badge-late">Not Updated</span>';

} else {

    // CHECK DRAFT / SIGNATURE
    if (
        empty($row['signature']) ||
        strtolower(trim($row['status'] ?? '')) === 'draft'
    ) {

        $row_class = 'row-missing';
        $status = '<span class="badge bg-warning text-dark">Draft</span>';

    }

    // COMPLETE
    else {

        $status = '<span class="badge badge-ok">Updated</span>';
    }
}

?>

<tr class="<?= $row_class ?>">

    <td><?= $no++ ?></td>

    <td class="fw-semibold">
        <?= htmlspecialchars($row['project_name']) ?>
    </td>

    <td><?= htmlspecialchars($row['pusb'] ?? '-') ?></td>

    <td><?= htmlspecialchars($row['pm'] ?? '-') ?></td>

    <td><?= htmlspecialchars($row['cm'] ?? '-') ?></td>

    <td><?= htmlspecialchars($row['re'] ?? '-') ?></td>

    <td>
        <?= $row['report_date'] 
            ? date('d M Y', strtotime($row['report_date'])) 
            : '-' ?>
    </td>

    <td class="<?= $progress_class ?>">
        <?= number_format($progress,2) ?>%
    </td>

    <td><?= $status ?></td>

    <td>

        <?php if ($row['report_id']): ?>

            <a href="viewwr.php?id=<?= (int)$row['report_id'] ?>" 
               class="btn btn-dark btn-sm btn-view">

               <i class="bi bi-eye"></i>

            </a>

        <?php else: ?>

            -

        <?php endif; ?>

    </td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

<script>
document.getElementById("searchInput").addEventListener("input", function () {

    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll("tbody tr");

    rows.forEach(row => {

        let project = row.cells[1].innerText.toLowerCase();
        let company = row.cells[2].innerText.toLowerCase();
        let pm      = row.cells[3].innerText.toLowerCase();
        let cm      = row.cells[4].innerText.toLowerCase();
        let re      = row.cells[5].innerText.toLowerCase();

        let combined = project + " " + company + " " + pm + " " + cm + " " + re;

        if (combined.includes(value)) {

            row.style.display = "";

        } else {

            row.style.display = "none";
        }

    });

});
</script>

</body>
</html>
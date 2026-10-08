<?php
require_once 'include/msqli_connect.php';


error_reporting(E_ALL);
ini_set('display_errors', 1);

$id = intval($_GET['id'] ?? 0);
if (!$id) die("Invalid ID");

$stmt = $db->prepare("
    SELECT 
        weekly_reports.*,
        project.category
    FROM weekly_reports
    LEFT JOIN project 
        ON project.id = weekly_reports.project_id
    WHERE weekly_reports.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) die("No data");

$subs = [];

$stmt2 = $db->prepare("SELECT * FROM subcontractors WHERE report_id=?");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$res2 = $stmt2->get_result();

while ($r = $res2->fetch_assoc()) {
    $subs[] = $r;
}

/* =========================
   EOT LATEST
========================= */
$project_id = $row['project_id'];

$stmt = $db->prepare("
    SELECT eot_number, eot_date
    FROM eot
    WHERE project_id = ?
    ORDER BY eot_date DESC
    LIMIT 1
");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$eot_data = $result->fetch_assoc();

$eot_number = $eot_data['eot_number'] ?? '';
$eot_date   = $eot_data['eot_date'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
<title>Weekly Report</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<style>
/* =========================
   BASE STYLE (VIEW)
========================= */
body {
    background: #f4f6f9;
    font-family: 'Segoe UI', Arial, sans-serif;
}

.card-wrapper {
    max-width: 800px;
    margin: 0 auto;
}

.card {
    border-radius: 16px;
}

/* =========================
   SIGNATURE (VIEW)
========================= */
.signature {
    margin-top: 50px;
    line-height: 1.6;
}

/* =========================
   PRINT STYLE (FINAL FIX)
========================= */
@media print {

    @page {
        size: A4;
        margin: 10mm;
    }

    html, body {
        height: 100%;
    }

    body {
        display: flex;
        flex-direction: column;
        font-family: Arial, sans-serif !important;
        font-size: 12px;
        line-height: 1.5;
        background: white;
    }

    /* FULL WIDTH PRINT */
    .container,
    .card-wrapper,
    .card {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
    }

    /* HIDE BUTTON */
    .btn {
        display: none !important;
    }

    /* 🔥 IMPORTANT: PUSH CONTENT */
    .card-wrapper {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .card {
        flex: 0 0 auto;
    }

    /* 🔥 SIGNATURE STICK BOTTOM */
    .signature {
        margin-top: auto;
        padding-top: 20px;
        page-break-inside: avoid;
    }

}
	
	.contract-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}

.contract-label {
    font-weight: 600;
    margin-right: 8px;
    min-width: 140px;
}

/* warna ikut kategori */
.label-asal { color: #0d6efd; }      /* biru */
.label-baru { color: #6f42c1; }      /* ungu */
.label-bayar { color: #198754; }     /* hijau */
.label-belum { color: #fd7e14; }     /* oren */
.label-baki { color: #dc3545; }      /* merah */
.label-wjp { color: #20c997; }       /* teal */

	@media print {

    .row {
        display: flex !important;
        flex-wrap: wrap !important;
    }

    .col-md-6 {
        width: 50% !important;
        max-width: 50% !important;
        flex: 0 0 50% !important;
    }

    .col-md-12 {
        width: 100% !important;
        max-width: 100% !important;
        flex: 0 0 100% !important;
    }

}
	.sub-contract-box {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px 20px;
    font-size: 13px;
    margin-top: 6px;
}

.sub-contract-box .item {
    display: flex;
    gap: 6px;
}

.sub-contract-box .full {
    grid-column: span 2;
}
	@media print {

    img {
        display: block !important;
        max-width: 100% !important;
        page-break-inside: avoid;
        break-inside: avoid;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .card-img-top {
        height: 220px !important;
        object-fit: cover !important;
    }

    .card {
        overflow: hidden !important;
    }

    a {
        text-decoration: none !important;
    }

}
	@media print {

    .gallery-print {
        width: 33.33% !important;
        float: left !important;
    }

}
</style>
</head>

<body>

<div class="container py-5">
<div class="card-wrapper">

<div class="card shadow p-4">

<!-- TITLE -->
<h5 class="text-center mb-3">
    <i class="bi bi-bar-chart-fill"></i> Weekly Report
</h5>

<!-- DATE -->
<?php

$report_date = $row['report_date'];

$week_start = date(
    'd/m/Y',
    strtotime('-6 days', strtotime($report_date))
);

$week_end = date(
    'd/m/Y',
    strtotime($report_date)
);

/* submit date */
$submitted_date = !empty($row['created_at'])
    ? date(
        'd/m/Y',
        strtotime($row['created_at'])
    )
    : '-';

?>

<!-- WEEK -->
<div class="mb-1 fw-bold">

    Weekly Report:

    <?= $week_start ?>

    -

    <?= $week_end ?>

</div>

<!-- SUBMISSION -->
<div class="mb-3 text-muted">

    Tarikh Submission:

    <?= $submitted_date ?>

</div>
<!-- PROJECT -->
<div class="mb-2">
    <b><?= $row['project_name'] ?></b>
</div>

<!-- TEAM -->
<div class="mb-2">
    <i class="bi bi-person-badge"></i>
    <strong></strong> <?= $row['pusb'] ?> |
    <strong>PM:</strong> <?= $row['pm'] ?> |
    <strong>CM:</strong> <?= $row['cm'] ?> |
    <strong>RE:</strong> <?= $row['re'] ?>
</div>

<!-- CONTRACT -->
<div class="mb-1">
    <i class="bi bi-flag"></i>
    <strong>Tarikh Mula Kontrak:</strong> 	<?= !empty($row['contract_start']) 
    ? date('d/m/Y', strtotime($row['contract_start'])) 
    : '-' ?>
</div>


<div class="mb-1">
    <i class="bi bi-flag"></i>
    <strong>Tarikh Siap Kontrak Asal</strong> 		<?= !empty($row['contract_end']) 
    ? date('d/m/Y', strtotime($row['contract_end'])) 
    : '-' ?>

</div>

<?php if (!empty($eot_date)) : ?>
<div class="mb-2">
    <i class="bi bi-clock-history"></i>
    <strong><?= $eot_number ? "Tarikh Siap Kontrak EOT $eot_number" : "EOT" ?>:</strong>
    <?= date('d/m/Y', strtotime($eot_date)) ?>
</div>
<?php endif; ?>

<!-- PROGRESS -->
<div class="mb-2">
    <i class="bi bi-graph-up"></i>
    <strong>Kemajuan Fizikal:</strong>

    <?= $row['physical_progress'] ?>%
    (vs <?= $row['target_progress'] ?>%) |

    <?= number_format($row['different_progress'],2) ?>%

<?php
$days = trim($row['days_status'] ?? '');
?>

<?php if ($days !== ''): ?>

    <?php

    $lower = strtolower($days);

    // ambil nombor kalau ada
    preg_match('/-?\d+/', $days, $match);
    $num = $match[0] ?? null;

    if ($num !== null) {

        $num = intval($num);

        // kalau ada keyword lambat/lewat
        if (
            strpos($lower, 'lambat') !== false ||
            strpos($lower, 'lewat') !== false
        ) {

            echo '<span class="text-danger">(' . abs($num) . ' hari lewat)</span>';

        }

        // kalau ada keyword awal
        elseif (strpos($lower, 'awal') !== false) {

            echo '<span class="text-success">(' . abs($num) . ' hari awal)</span>';

        }

        // kalau negative
        elseif ($num < 0) {

            echo '<span class="text-danger">(' . abs($num) . ' hari lewat)</span>';

        }

        // kalau positive
        elseif ($num > 0) {

            echo '<span class="text-success">(' . abs($num) . ' hari awal)</span>';

        }

        else {

            echo '<span class="text-secondary">(0 hari)</span>';
        }

    } else {

        echo '<span class="text-secondary">(' . htmlspecialchars($days) . ')</span>';
    }

    ?>

<?php else: ?>

    <span class="text-secondary">(-)</span>

<?php endif; ?>
</div>

<!-- FINANCE -->
	<?php if (strtolower(trim($row['category'] ?? '')) != 'property'): ?>

<div class="mb-2">
    <i class="bi bi-cash"></i>
	
	<div class="row">
<?php
$asal   = $row['contract_value'] ?? 0;
$baru   = $row['newcontract_value'] ?? 0;
$bayar  = $row['paid'] ?? 0;
$belum  = $row['unpaid'] ?? 0;
$baki   = $row['balance'] ?? 0;
$wjp    = $row['wjp'] ?? '';

// IMPORTANT 🔥
$showBaru = ($baru !== null && $baru !== '' && $baru > 0);
?>

  <div class="row">

    <?php if ($showBaru): ?>

        <!-- Row 1 -->
        <div class="col-md-12">
            <div class="contract-item">
                <span class="contract-label label-asal">Nilai Kontrak Asal :</span>
                <span>RM <?= number_format($asal, 2) ?></span>
            </div>
        </div>

        <!-- Row 2 -->
        <div class="col-md-6">
            <div class="contract-item">
                <span class="contract-label label-baru">Nilai Kontrak Baru</span>
                <span>RM <?= number_format($baru, 2) ?></span>
            </div>
        </div>

        <div class="col-md-6">
            <div class="contract-item">
                <span class="contract-label label-bayar">Dibayar :</span>
                <span>RM <?= number_format($bayar, 2) ?></span>
            </div>
        </div>

    <?php else: ?>

        <!-- Row 1 (combine terus) -->
        <div class="col-md-6">
            <div class="contract-item">
                <span class="contract-label label-asal">Nilai Kontrak Asal :</span>
                <span>RM <?= number_format($asal, 2) ?></span>
            </div>
        </div>

        <div class="col-md-6">
            <div class="contract-item">
                <span class="contract-label label-bayar">Dibayar :</span>
                <span>RM <?= number_format($bayar, 2) ?></span>
            </div>
        </div>

    <?php endif; ?>


    <!-- Row seterusnya (tak berubah) -->
    <div class="col-md-6">
        <div class="contract-item">
            <span class="contract-label label-belum">Belum Diterima :</span>
            <span>RM <?= number_format($belum, 2) ?></span>
        </div>
    </div>

    <div class="col-md-6">
        <div class="contract-item">
            <span class="contract-label label-baki">Baki Kontrak :</span>
            <span>RM <?= number_format($baki, 2) ?></span>
        </div>
    </div>

    <div class="col-md-12">
        <div class="contract-item">
            <span class="contract-label label-wjp">WJP :</span>
          <span>RM <?= number_format($wjp, 2) ?></span>
    <!--<span>RM <?= $wjp !== '' ? htmlspecialchars($wjp) : '-' ?></span>-->
        </div>
    </div>

</div>
	
</div>
	</div>
	<?php endif; ?>

<!-- ISSUES -->
<div class="mb-2">
    <i class="bi bi-tools"></i>
    <strong>Subkontraktor / Isu:</strong><br>

    <?php if (!empty($subs)): ?>
        <?php foreach ($subs as $s): ?>

            <div style="margin-bottom:10px; padding:8px 10px; border-left:3px solid #0d6efd; background:#f8f9fa;">

                <!-- NAME -->
                <strong><?= htmlspecialchars($s['name']) ?></strong><br>



                <!-- FINANCIAL -->
               
<?php $hasNew = !empty($s['new_contract_sub']) && $s['new_contract_sub'] > 0; ?>

<div class="sub-contract-box">

    <!-- NILAI ASAL -->
    <div class="item asal">
        <span class="label label-asal">Nilai Kontrak :</span>
        <span>RM<?= number_format($s['contract'] ?? 0, 2) ?></span>
    </div>

    <?php if ($hasNew): ?>
        <!-- NILAI BARU -->
        <div class="item new">
            <span class="label label-asal">Nilai Kontrak Baru :</span>
            <span>RM<?= number_format($s['new_contract_sub'], 2) ?></span>
        </div>
    <?php endif; ?>

    <!-- DIBAYAR -->
    <div class="item bayar <?= !$hasNew ? 'no-new' : '' ?>">
        <span class="label label-bayar">Dibayar :</span>
        <span>RM<?= number_format($s['paid'] ?? 0, 2) ?></span>
    </div>


<?php if (strtolower(trim($row['category'] ?? '')) != 'property'): ?>

    <!-- BELUM -->
    <div class="item belum">
        <span class="label label-belum">Belum Diterima :</span>
        <span>RM<?= number_format($s['unpaid'] ?? 0, 2) ?></span>
    </div>

<?php endif; ?>

    <!-- BAKI -->
    <div class="item baki">
        <span class="label label-baki">Baki Kontrak :</span>
        <span>RM<?= number_format($s['balance'] ?? 0, 2) ?></span>
    </div>

    <!-- WJP -->
    <?php if (($s['wjp'] ?? 0) > 0): ?>
    <div class="item full">
        <span class="label label-wjp">WJP :</span>
        <span>RM<?= number_format($s['wjp'], 2) ?></span>
    </div>
    <?php endif; ?>

</div>

<!-- ISSUE (turun bawah proper) -->
<?php if (!empty($s['issue'])): ?>
    <div style="margin-top:6px; font-size:13px; font-weight:500;">
        <?= nl2br(htmlspecialchars($s['issue'])) ?>
    </div>
<?php endif; ?>

            </div>

        <?php endforeach; ?>
    <?php else: ?>
        <span class="text-muted">Tiada data</span>
    <?php endif; ?>

</div>
<div class="mb-2">
    <i class="bi bi-gear"></i>
    <strong>Isu Rekabentuk &  Teknikal</strong><br>
    <?= nl2br($row['technical_issue']) ?>
</div>
<div class="mb-2">
    <i class="bi bi-file-text"></i>
    <strong>Isu Kontrak & Dokumen:</strong><br>
    <?= nl2br($row['issues']) ?>
</div>



<div class="mb-2">
    <i class="bi bi-cone-striped"></i>
    <strong>Isu di Tapak:</strong><br>
    <?= nl2br($row['site_issue']) ?>
</div>

<div class="mb-3">
    <i class="bi bi-signpost"></i>
    <strong>Isu Halatuju / Keputusan MGTM:</strong><br>
    <?= nl2br($row['mgtm']) ?>
</div>
	
	<!-- SITE GALLERY -->
<?php

$gallery = [];

$stmt_gallery = $db->prepare("
    SELECT *
    FROM weekly_report_gallery
    WHERE report_id = ?
    ORDER BY id ASC
");

$stmt_gallery->bind_param(
    "i",
    $id
);

$stmt_gallery->execute();

$res_gallery =
    $stmt_gallery->get_result();

while(
    $g =
    $res_gallery->fetch_assoc()
){

    $gallery[] = $g;
}

?>

<?php if(!empty($gallery)): ?>

<div class="mb-4">

    <i class="bi bi-images"></i>
    <strong>Site Gallery:</strong>

    <div class="row mt-3">

        <?php foreach($gallery as $g): ?>

<div class="col-md-4 mb-4 gallery-print">
            <div class="card shadow-sm border-0 h-100">

                <a
                    href="uploads/project_<?= $row['project_id'] ?>/weekly_gallery/<?= htmlspecialchars($g['image_name']) ?>"
                    target="_blank"
                >

                    <img
                        src="uploads/project_<?= $row['project_id'] ?>/weekly_gallery/<?= htmlspecialchars($g['image_name']) ?>"
                        class="card-img-top"
                        style="
                            height:220px;
                            object-fit:cover;
                        "
                    >

                </a>

                <?php if(!empty($g['caption'])): ?>

                <div class="card-body p-2">

                    <small class="text-muted">

                        <?= nl2br(htmlspecialchars($g['caption'])) ?>

                    </small>

                </div>

                <?php endif; ?>

            </div>

        </div>

        <?php endforeach; ?>

    </div>

</div>

<?php endif; ?>

<!-- SIGNATURE -->
<div class="signature">

    <strong>Disediakan Oleh:</strong><br><br>

    <?php if (!empty($row['signature'])): ?>

        <!-- SIGNATURE IMAGE -->
        <img src="<?= $row['signature'] ?>" style="height:80px;"><br>

    <?php else: ?>

        <!-- CANVAS -->
        <canvas id="signature-pad" width="300" height="100"
            style="border:1px solid #ccc;"></canvas><br>

        <button type="button" onclick="clearSign()" class="btn btn-sm btn-secondary mt-2">
            Clear
        </button>

        <button type="button" onclick="saveSign()" class="btn btn-sm btn-success mt-2">
            Save Signature
        </button>

    <?php endif; ?>

    <!-- LINE + NAME (ALWAYS SHOW) -->
    <div style="margin-top:10px;">
        ___________________________
    </div>

  <div>
    <small>
        <?= !empty(trim($row['pm'])) && $row['pm'] !== '-' && strtolower(trim($row['pm'])) !== 'tiada'
            ? htmlspecialchars($row['pm'])
            : htmlspecialchars($row['sign_name'] ?? '-') ?>
    </small>
</div>

</div>
<!-- BUTTON -->
<div class="d-flex justify-content-between mt-4">

    <a href="reports.php" 
       class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back
    </a>

    <button onclick="window.print()" class="btn btn-dark">
        <i class="bi bi-printer"></i> Print
    </button>

</div>

</div>
</div>
</div>
	<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

<script>
let signaturePad = null;

const canvas = document.getElementById('signature-pad');

if (canvas) {
    signaturePad = new SignaturePad(canvas);
}

function clearSign() {
    if (signaturePad) signaturePad.clear();
}

function saveSign() {

    if (!signaturePad || signaturePad.isEmpty()) {
        alert("Sila sign dulu");
        return;
    }

    const dataURL = signaturePad.toDataURL();

    fetch("save_signature.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: "id=<?= $row['id'] ?>&signature=" + encodeURIComponent(dataURL)
    })
    .then(res => res.text())
    .then(() => location.reload());

}
</script>
</body>
</html>
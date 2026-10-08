<?php
session_start();
require_once 'include/msqli_connect.php';

$role = strtolower(trim($_SESSION['account_type'] ?? ''));

$can_delete = in_array($role, [
    'client admin',
    'admin (qa/qc)'
]);


$project_name = '';
$prefix = '';
$project_id = $_GET['project_id'] ?? '';

$id = intval($_GET['id'] ?? 0);
if (!$id) die("Invalid ID");

$stmt = $db->prepare("SELECT * FROM weekly_reports WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$data = $res->fetch_assoc();
$subs = [];

$stmt2 = $db->prepare("SELECT * FROM subcontractors WHERE report_id=?");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$res2 = $stmt2->get_result();

while ($row = $res2->fetch_assoc()) {
    $subs[] = $row;
}

/* =========================================
GET GALLERY
========================================= */

$gallery = [];

$stmt_gallery = $db->prepare("
SELECT *
FROM weekly_report_gallery
WHERE report_id = ?
");

$stmt_gallery->bind_param(
    "i",
    $id
);

$stmt_gallery->execute();

$res_gallery =
    $stmt_gallery->get_result();

while(
    $row_gallery =
    $res_gallery->fetch_assoc()
){

    $gallery[] = $row_gallery;
}

if (!$data) die("No data");

/* 🔥 INI PENTING */
$project_id = $data['project_id'];
$project_name = $data['project_name'];
$prefix = $data['pusb'];

$days = $data['days_status'] ?? 0;

// untuk color je
$days_color = $days > 0 ? "text-success" : ($days < 0 ? "text-danger" : "");

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        body { background: #f4f6f9; }
        .card { border: none; border-radius: 16px; }
        .section-title { font-weight: 600; margin-bottom: 15px; color: #333; }
        .form-label { font-size: 14px; font-weight: 500; }
	
    body {
        background: #f4f6f9;
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 14px;
        color: #2c3e50;
    }

    .card {
        border: none;
        border-radius: 16px;
    }

    /* ===== TITLE ===== */
    h3 {
        font-weight: 600;
        letter-spacing: 0.3px;
    }

    h6 {
        font-size: 15px;
        font-weight: 600;
        color: #2c3e50;
    }

    /* ===== LABEL ===== */
    .form-label {
        font-size: 13px;
        font-weight: 500;
        color: #6c757d;
    }

    /* ===== INPUT ===== */
    .form-control,
    .form-select {
        font-size: 14px;
        border-radius: 8px;
    }

    .input-group-text {
        font-size: 13px;
    }

    /* ===== ICON ===== */
    .bi {
        font-size: 16px;
    }

    /* ===== TEXT ===== */
    .fw-medium {
        font-weight: 500 !important;
    }

    .fw-bold {
        font-weight: 600 !important;
    }

		@media print {
    body {
        background: white;
    }

    .card {
        box-shadow: none;
        border: none;
    }
}
		.input-group-text {
    font-size: 13px;
    background: #f1f3f5;
}

.form-label {
    font-size: 13px;
    color: #6c757d;
}
		.custom-upload .btn{
    border-radius:10px;
    padding:10px 18px;
    font-weight:600;
}
</style>
 
</head>

<body>

<div class="container py-5">
    <div class="card shadow-lg p-4">

        <h3 class="mb-4 text-center">📊 Weekly Report</h3>

        <form action="save_report.php" method="POST" enctype="multipart/form-data"
>

            <!-- BASIC INFO -->
           <div class="mb-4">

<div class="mb-4">

<?php

// submit date
$today_display = !empty($data['created_at'])
    ? date(
        'd M Y',
        strtotime($data['created_at'])
    )
    : date('d M Y');

// report week
$report_date =
    $data['report_date']
    ?? $_GET['report_date']
    ?? date(
        'Y-m-d',
        strtotime('thursday this week')
    );

$week_start = date(
    'd/m/Y',
    strtotime('-6 days', strtotime($report_date))
);

$week_end = date(
    'd/m/Y',
    strtotime($report_date)
);

?>

    <!-- Date Submitted -->
 <div class="mb-2">

    <label class="form-label small text-muted mb-1">
        Date Submitted
    </label>

    <div class="form-control bg-light shadow-sm mb-2">

        <?= $today_display ?>

    </div>

    <label class="form-label small text-muted mb-1">
        Weekly Report
    </label>

    <div class="form-control bg-light shadow-sm">

        <?= $week_start ?>

        -

        <?= $week_end ?>

    </div>

    <input
    type="hidden"
    name="report_date"
    value="<?= $report_date ?>"
    >

</div>

</div>

                <!-- Project Name -->
                <div>
                   
                    <input 
                        class="form-control border-0 shadow-none bg-transparent fw-semibold px-0" 
                        name="project_name" 
                        value="<?= $project_name ?>" 
                        readonly>
					<input type="hidden" name="project_id" value="<?= $project_id ?>">
					<input type="hidden" name="id" value="<?= $id ?>">
                </div>

            </div>

            <!-- TEAM -->
<div class="mb-4">

    <div class="row g-3">

        <!-- PUSB -->
        <div class="col-md-2">
            <div class="input-group">
                <span class="input-group-text">
                    <i class="bi bi-person-badge"></i>
                </span>
                <input class="form-control" name="company" value="<?= $prefix ?>" readonly>
            </div>
        </div>

        <!-- PM -->
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text">PM</span>
                <input class="form-control" name="pm"
                    value="<?= htmlspecialchars($data['pm']) ?>">
            </div>
        </div>

        <!-- CM -->
        <div class="col-md-3">
            <div class="input-group">
                <span class="input-group-text">CM</span>
                <input class="form-control" name="cm"
                    value="<?= htmlspecialchars($data['cm']) ?>">
            </div>
        </div>

        <!-- RE -->
        <div class="col-md-3">
            <div class="input-group">
                <span class="input-group-text">RE</span>
                <input class="form-control" name="re"
                    value="<?= htmlspecialchars($data['re']) ?>">
            </div>
        </div>

    </div>
</div>

            <!-- CONTRACT -->
  <div class="mb-4">

<!-- Tarikh Mula -->
<div class="row align-items-center mb-3"> 
    <div class="col-md-3 d-flex align-items-center gap-2"> 
        <i class="bi-flag-fill"></i> 
        <label class="mb-0 fw-medium">Tarikh Mula Kontrak</label> 
    </div> 
    <div class="col-md-5"> 
        <input type="date" class="form-control" name="contract_start"
        value="<?= htmlspecialchars($data['contract_start']) ?>" readonly>
    </div> 
</div>

<!-- Tarikh Akhir -->
<div class="row align-items-center mb-3">
    <div class="col-md-3 d-flex align-items-center gap-2">
        <i class="bi-flag"></i>
        <label class="mb-0 fw-medium">Tarikh Siap Kontrak Asal</label>
    </div>
    <div class="col-md-5">
        <input type="date" class="form-control" name="contract_end"
        value="<?= htmlspecialchars($data['contract_end']) ?>" readonly>
    </div>
</div>

<!-- EOT -->
<?php if (!empty($eot_date)) : ?>
<div class="row align-items-center mb-3">
    <div class="col-md-3 d-flex align-items-center gap-2">
        <i class="bi bi-clock-history text-warning"></i>
        <label class="mb-0 fw-medium">
            <?= $eot_number ? "Tarikh Siap Kontrak (EOT $eot_number)" : "Tarikh Siap Kontrak (EOT)" ?>
        </label>
    </div>
    <div class="col-md-5">
        <input type="date" class="form-control"
            name="eot"
            value="<?= htmlspecialchars($eot_date) ?>" readonly>
    </div>
</div>
<?php endif; ?>


</div>
<div class="mb-3">
<div class="row align-items-center g-1 flex-nowrap">

    <!-- Label -->
    <div class="col-auto fw-medium">
        <i class="bi bi-graph-up text-primary"></i>
        Kemajuan Fizikal
    </div>

    <!-- ACTUAL -->
    <div class="col-auto">
        <div class="input-group input-group-sm">
            <input class="form-control" 
                   name="physical_progress"
                   value="<?= $data['physical_progress'] ?>" placeholder="Actual">
            <span class="input-group-text">%</span>
        </div>
    </div>

    <!-- VS -->
    <div class="col-auto text-muted px-1">VS</div>

    <!-- TARGET -->
    <div class="col-auto">
        <div class="input-group input-group-sm">
            <input class="form-control" 
                   name="target_progress"
                   value="<?= $data['target_progress'] ?>" placeholder="Schedule">
            <span class="input-group-text">%</span>
        </div>
    </div>

    <!-- Divider -->
    <div class="col-auto text-muted px-1">|</div>

    <!-- DIFFERENT -->
    <div class="col-auto">
        <div class="input-group input-group-sm">
            <input class="form-control" 
                   name="different_progress"
                   value="<?= $data['different_progress'] ?>"
                   readonly>
            <span class="input-group-text">%</span>
        </div>
    </div>

    <!-- DAYS -->
    <div class="col-auto">
        <div class="input-group input-group-sm">
           <input class="form-control" 
                   name="days_status"
                   value="<?= $data['days_status'] ?>"
                   >
            <span class="input-group-text">Bilangan Hari</span>
        </div>
    </div>

</div>
</div>
            <!-- FINANCE -->
<div class="mb-4">

<div class="row g-3">

    <!-- KONTRAK -->
    <div class="col-md-2">
        <label class="form-label">Nilai Kontrak Asal</label>
        <div class="input-group">
            <span class="input-group-text">RM</span>
            <input class="form-control" 
                   name="contract_value"
                   value="<?= $data['contract_value'] ?>">
        </div>
    </div>
	
	  <div class="col-md-2">
        <label class="form-label">Nilai Kontrak Baru</label>
        <div class="input-group">
            <span class="input-group-text">RM</span>
            <input class="form-control" 
                   name="newcontract_value"
                   value="<?= $data['newcontract_value'] ?>">
        </div>
    </div>

    <!-- DIBAYAR -->
    <div class="col-md-2">
        <label class="form-label">Dibayar</label>
        <div class="input-group">
            <span class="input-group-text">RM</span>
            <input class="form-control" 
                   name="paid"
                   value="<?= $data['paid'] ?>">
        </div>
    </div>

    <!-- BELUM TERIMA -->
    <div class="col-md-2">
        <label class="form-label">Belum Terima</label>
        <div class="input-group">
            <span class="input-group-text">RM</span>
            <input class="form-control" 
                   name="unpaid"
                   value="<?= $data['unpaid'] ?>">
        </div>
    </div>

    <!-- WJP -->
 

    <!-- BAKI -->
    <div class="col-md-2">
        <label class="form-label">Baki Kontrak</label>
        <div class="input-group">
            <span class="input-group-text">RM</span>
            <input class="form-control"  
                   name="balance"
                   value="<?= $data['balance'] ?>">
        </div>
    </div>
	
	   <div class="col-md-2">
        <label class="form-label">WJP</label>
        <div class="input-group">
            <span class="input-group-text">RM</span>
            <input class="form-control" 
                   name="wjp"
                   value="<?= $data['wjp'] ?>">
        </div>
    </div>

</div>

</div>
<div class="mb-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold">
        <i class="bi bi-tools text-warning"></i>
        Subkontraktor / Isu
    </h6>

    <button type="button" class="btn btn-sm btn-primary" onclick="addRow()">+ Add</button>
</div>

<div id="sub-list">

<?php if (!empty($subs)): ?>

    <?php foreach ($subs as $sub): ?>

    <div class="border rounded p-3 mb-3 bg-light">

        <div class="row g-2 mb-2">
            <div class="col-md-3">
                <input class="form-control" name="sub_name[]" value="<?= htmlspecialchars($sub['name']) ?>">
				<input type="hidden" name="sub_id[]" value="<?= $sub['id'] ?>">
				<input type="hidden" name="delete_sub[]" value="0">
            </div>

            <div class="col-md-8">
    <textarea class="form-control auto-expand" 
              name="sub_issue[]"
              rows="1"
              style="overflow:hidden; resize:none;"
    ><?= htmlspecialchars($sub['issue']) ?></textarea>
</div>

            <div class="col-md-1">
                <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">✖</button>
            </div>
        </div>

        <div class="row g-3">

            <div class="col-md-2">
				 <label class="form-label">Nilai Kontrak</label>
                <div class="input-group">
                    <span class="input-group-text">RM</span>
                    <input class="form-control" name="sub_contract[]" value="<?= $sub['contract'] ?>">
                </div>
            </div>
			
			       <div class="col-md-2">
				 <label class="form-label">Nilai Kontrak Baru</label>
                <div class="input-group">
                    <span class="input-group-text">RM</span>
                    <input class="form-control" name="new_contract_sub[]" value="<?= $sub['new_contract_sub'] ?>">
                </div>
            </div>

            <div class="col-md-2">
				 <label class="form-label">Dibayar</label>
                <div class="input-group">
                    <span class="input-group-text">RM</span>
                    <input class="form-control" name="sub_paid[]" value="<?= $sub['paid'] ?>">
                </div>
            </div>

            <div class="col-md-2">
				 <label class="form-label">Belum Terima</label>
                <div class="input-group">
                    <span class="input-group-text">RM</span>
                    <input class="form-control" name="sub_unpaid[]" value="<?= $sub['unpaid'] ?>">
                </div>
            </div>

          

            <div class="col-md-2">
				<label class="form-label">Baki Kontrak</label>
                <div class="input-group">
                    <span class="input-group-text">RM</span>
                    <input class="form-control" name="sub_balance[]" value="<?= $sub['balance'] ?>">
                </div>
            </div>

			  <div class="col-md-2">
				<label class="form-label">WJP</label>
                <div class="input-group">
                    <span class="input-group-text">RM</span>
                    <input class="form-control" name="sub_wjp[]" value="<?= $sub['wjp'] ?>">
                </div>
            </div>
        </div>

    </div>

    <?php endforeach; ?>

<?php else: ?>

    <!-- 🔥 DEFAULT AUTO OPEN -->
    <div class="border rounded p-3 mb-3 bg-light">

        <div class="row g-2 mb-2">
            <div class="col-md-3">
                <input class="form-control" name="sub_name[]" placeholder="Nama Subkontraktor">
            </div>

            <div class="col-md-8">
                <textarea class="form-control" name="sub_issue[]" placeholder="Isu"></textarea>
            </div>

            <div class="col-md-1">
                <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">✖</button>
            </div>
        </div>

        <div class="row g-3">

            <div class="col-md-2">
				  <label class="form-label">Nilai Kontrak</label>
                <div class="input-group">
                    <span class="input-group-text">RM</span>
                    <input class="form-control" name="sub_contract[]">
                </div>
            </div>
			    <div class="col-md-2">
				  <label class="form-label">Nilai Kontrak Baru </label>
                <div class="input-group">
                    <span class="input-group-text">RM</span>
                    <input class="form-control" name="new_contract_sub[]">
                </div>
            </div>

            <div class="col-md-2">
				 <label class="form-label">Dibayar</label>
                <div class="input-group">
                    <span class="input-group-text">RM</span>
                    <input class="form-control" name="sub_paid[]">
                </div>
            </div>

            <div class="col-md-2">
				 <label class="form-label">Belum Terima</label>
                <div class="input-group">
                    <span class="input-group-text">RM</span>
                    <input class="form-control" name="sub_unpaid[]">
                </div>
            </div>

					  <div class="col-md-2">
				 <label class="form-label">WJP</label>
                <div class="input-group">
                    <span class="input-group-text">RM</span>
                    <input class="form-control" name="sub_wjp[]">
                </div>
            </div>
          

            <div class="col-md-2">
				<label class="form-label">Baki Kontrak</label>
                <div class="input-group">
                    <span class="input-group-text">RM</span>
                    <input class="form-control" name="sub_balance[]">
                </div>
            </div>

        </div>

    </div>

<?php endif; ?>

</div>
</div>

    <!-- Teknikal -->
    <div>
        <label class="form-label d-flex align-items-center gap-2">
            <i class="bi bi-gear text-info"></i>
            Isu Reka Bentuk & Teknikal
        </label>
        <textarea class="form-control" 
                  name="technical_issue" 
                  rows="11"
                  placeholder="Perkembangan teknikal..."><?= $data['technical_issue'] ?></textarea>
    </div>
			
			    <!-- Isu -->
    <div class="mb-3">
        <label class="form-label">Isu Kontrak & Dokumen</label>
        <textarea class="form-control" 
                  name="issue" 
                  rows="11"
                  placeholder="Nyatakan isu..."><?= $data['issues'] ?></textarea>
    </div>
    
    <!-- Site -->
    <div class="mt-4">
        <label class="form-label d-flex align-items-center gap-2">
            <i class="bi bi-cone-striped text-warning fs-5"></i>
            Isu di Tapak
        </label>
        <textarea class="form-control" 
                  name="site_issue" 
                  rows="10"><?= $data['site_issue'] ?></textarea>
    </div>
    
    <!-- MGTM -->
    <div class="mb-4 mt-3">
        <label class="form-label d-flex align-items-center gap-2">
            <i class="bi bi-signpost text-warning fs-5"></i>
            Isu Halatuju / Keputusan MGTM
        </label>
        <textarea class="form-control" 
                  name="mgtm" 
                  rows="3"
                  placeholder="Nyatakan keputusan..."><?= $data['mgtm'] ?></textarea>
    </div>


<!-- SITE GALLERY -->
<div class="mb-4 mt-4">
    <h6 class="fw-bold mb-3">
        <i class="bi bi-images text-primary"></i>
        Site Gallery
    </h6>
    <div id="gallery-list">
        <?php foreach($gallery as $g): ?>
        <div class="border rounded p-3 bg-light mb-3 gallery-item">

            <input type="hidden" name="existing_gallery_id[]" value="<?= $g['id'] ?>">

            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Change Image</label>

                    <input
                        type="file"
                        name="gallery_image[]"
                        class="form-control"
                        accept="image/*"
                        onchange="previewGallery(this)"
                    >

                    <div class="mt-3">
                        <img
                            src="uploads/project_<?= $project_id ?>/weekly_gallery/<?= htmlspecialchars($g['image_name']) ?>"
                            class="img-fluid rounded border preview-image"
                            style="max-height:220px; object-fit:cover;"
                        >
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Caption</label>
                    <input
                        type="text"
                        name="gallery_caption[]"
                        class="form-control"
                        value="<?= htmlspecialchars($g['caption']) ?>"
                    >
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-danger" onclick="removeGallery(this)">✖</button>
                </div>
            </div>

        </div>
        <?php endforeach; ?>
    </div>

    <button type="button" class="btn btn-primary btn-sm" onclick="addGallery()">
        + Add Image
    </button>
</div>
			
            <!-- SUBMIT -->
      <div class="d-flex justify-content-between mb-3">

    <!-- Left -->
  <button type="button" class="btn btn-outline-secondary" 
        onclick="window.location='wr.php?project_id=<?= $project_id ?>'">
    <i class="bi bi-arrow-left"></i> Back
</button>

    <!-- Right -->
<div>

<?php if ($can_delete): ?>

<button type="button"
        class="btn btn-danger me-2"
        onclick="confirmDelete()">
    🗑 Delete
</button>

<?php endif; ?>
        
<button type="submit" name="action" value="draft" class="btn btn-secondary me-2">
    Save as Draft
</button>

<button type="submit" name="action" value="submit" class="btn btn-primary">
    Save Report
</button>

</div>

</div>
        </form>

    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {

    // ======================
    // ELEMENTS
    // ======================
    const actual = document.querySelector('[name="physical_progress"]');
    const target = document.querySelector('[name="target_progress"]');
    const diff   = document.querySelector('[name="different_progress"]');
    const days   = document.querySelector('[name="days"]');

    // ======================
    // CALCULATE DIFFERENCE
    // ======================
    function calcDiff() {
        if (!actual || !target || !diff) return;

        let a = parseFloat(actual.value) || 0;
        let t = parseFloat(target.value) || 0;

        let d = a - t;

        // format + sign
        diff.value = (d > 0 ? "+" : "") + d.toFixed(2);

        // reset color
        diff.classList.remove("text-danger", "text-success");

        // apply color
        if (d < 0) diff.classList.add("text-danger");
        else if (d > 0) diff.classList.add("text-success");
    }

    // ======================


    // ======================
    // ADD ROW
    // ======================
    window.addRow = function () {
        let html = `
        <div class="border rounded p-3 mb-3 bg-light">

            <div class="row g-2 mb-2">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="sub_name[]" placeholder="Nama Subkontraktor">
                </div>

                <div class="col-md-7">
                    <textarea class="form-control" name="sub_issue[]" rows="2" placeholder="Isu"></textarea>
                </div>

                <div class="col-md-1">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">✖</button>
                </div>
            </div>

            <div class="row g-3 mt-1">

                <div class="col-md-2">
                    <label class="form-label">Nilai Kontrak</label>
                    <div class="input-group">
                        <span class="input-group-text">RM</span>
                        <input class="form-control" name="sub_contract[]" placeholder="0.00">
                    </div>
                </div>
				
				    <div class="col-md-2">
                    <label class="form-label">Nilai Kontrak Baru</label>
                    <div class="input-group">
                        <span class="input-group-text">RM</span>
                        <input class="form-control" name="new_contract_sub[]" placeholder="0.00">
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Dibayar</label>
                    <div class="input-group">
                        <span class="input-group-text">RM</span>
                        <input class="form-control" name="sub_paid[]" placeholder="0.00">
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Belum Terima</label>
                    <div class="input-group">
                        <span class="input-group-text">RM</span>
                        <input class="form-control" name="sub_unpaid[]" placeholder="0.00">
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">WJP</label>
                    <div class="input-group">
                        <span class="input-group-text">RM</span>
                        <input class="form-control" name="sub_wjp[]" placeholder="0.00">
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Baki Kontrak</label>
                    <div class="input-group">
                        <span class="input-group-text">RM</span>
                        <input class="form-control" name="sub_balance[]" placeholder="0.00">
                    </div>
                </div>

            </div>

        </div>`;

        document.getElementById('sub-list').insertAdjacentHTML('beforeend', html);
    };

    // ======================
    // REMOVE ROW
    // ======================
window.removeRow = function (btn) {
    const row = btn.closest('.border');

    // set delete flag = 1
    const delInput = row.querySelector('input[name="delete_sub[]"]');
    if (delInput) {
        delInput.value = 1;
        row.style.display = 'none'; // hide je
    } else {
        row.remove(); // kalau row baru
    }
};

    // ======================
    // PREVENT ENTER SUBMIT
    // ======================
    document.querySelectorAll('input, textarea').forEach(function(el) {
        el.addEventListener('keydown', function(e) {
            if (this.tagName === "INPUT" && e.key === "Enter") {
                e.preventDefault();
            }
        });
    });

    // ======================
    // EVENTS
    // ======================
    if (actual && target) {
        actual.addEventListener("input", calcDiff);
        target.addEventListener("input", calcDiff);
    }

    if (days) {
        days.addEventListener("input", formatDays);
    }

});

function autoResize(el) {
    el.style.height = "auto";
    el.style.height = el.scrollHeight + "px";
}

// masa user type
document.addEventListener("input", function(e) {
    if (e.target.classList.contains("auto-expand")) {
        autoResize(e.target);
    }
});

// masa page load (🔥 penting untuk edit mode)
window.addEventListener("load", function() {
    document.querySelectorAll(".auto-expand").forEach(el => {
        autoResize(el);
    });
});


document.addEventListener("DOMContentLoaded", function () {

    const form = document.querySelector("form");

    const draftBtn = document.querySelector('button[value="draft"]');
    const submitBtn = document.querySelector('button[value="submit"]');

    // SAVE DRAFT
    draftBtn.addEventListener("click", function () {

        form.querySelectorAll("[required]").forEach(el => {
            el.removeAttribute("required");
        });

    });

    // SAVE REPORT
    submitBtn.addEventListener("click", function () {

        // INPUT
        document.querySelector('[name="pm"]').required = true;
        document.querySelector('[name="cm"]').required = true;
        document.querySelector('[name="re"]').required = true;

        document.querySelector('[name="physical_progress"]').required = true;
        document.querySelector('[name="target_progress"]').required = true;

        document.querySelector('[name="contract_value"]').required = true;
        document.querySelector('[name="newcontract_value"]').required = true;

        document.querySelector('[name="paid"]').required = true;
        document.querySelector('[name="unpaid"]').required = true;

        document.querySelector('[name="balance"]').required = true;
        document.querySelector('[name="wjp"]').required = true;

        document.querySelector('[name="technical_issue"]').required = true;
        document.querySelector('[name="issue"]').required = true;
        document.querySelector('[name="site_issue"]').required = true;
        document.querySelector('[name="mgtm"]').required = true;

        // SUBCON
        document.querySelectorAll('[name="sub_name[]"]').forEach(el => {
            el.required = true;
        });

        document.querySelectorAll('[name="sub_issue[]"]').forEach(el => {
            el.required = true;
        });

        document.querySelectorAll('[name="sub_contract[]"]').forEach(el => {
            el.required = true;
        });

        document.querySelectorAll('[name="new_contract_sub[]"]').forEach(el => {
            el.required = true;
        });

        document.querySelectorAll('[name="sub_paid[]"]').forEach(el => {
            el.required = true;
        });

        document.querySelectorAll('[name="sub_unpaid[]"]').forEach(el => {
            el.required = true;
        });

        document.querySelectorAll('[name="sub_balance[]"]').forEach(el => {
            el.required = true;
        });

        document.querySelectorAll('[name="sub_wjp[]"]').forEach(el => {
            el.required = true;
        });

    });

});
function confirmDelete() {

    if(confirm('Are you sure want to delete this weekly report?')) {

        window.location.href =
            'delete_weekly_report.php?id=<?= $id ?>&project_id=<?= $project_id ?>';

    }

}
/* =========================================
ADD GALLERY
========================================= */

function addGallery(){
    let html = `
    <div class="border rounded p-3 bg-light mb-3 gallery-item">

        <input type="hidden" name="existing_gallery_id[]" value="0">

        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Upload Image</label>
                <input type="file" name="gallery_image[]" class="form-control"
                    accept="image/*" onchange="previewGallery(this)">
                <div class="mt-3">
                    <img src="" class="img-fluid rounded border preview-image"
                        style="display:none; max-height:220px; object-fit:cover;">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Caption</label>
                <input type="text" name="gallery_caption[]" class="form-control"
                    placeholder="Site progress / activity">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-danger" onclick="removeGallery(this)">✖</button>
            </div>
        </div>

    </div>`;

    document.getElementById('gallery-list').insertAdjacentHTML('beforeend', html);
}

/* =========================================
REMOVE GALLERY
========================================= */

function removeGallery(btn) {
    const item = btn.closest('.gallery-item');
    const existingId = item.querySelector('input[name="existing_gallery_id[]"]');
    
    if (existingId && existingId.value > 0) {
        // Add a hidden delete flag for existing images
        const delInput = document.createElement('input');
        delInput.type = 'hidden';
        delInput.name = 'delete_gallery_id[]';
        delInput.value = existingId.value;
        document.querySelector('form').appendChild(delInput);
    }
    
    item.remove();
}

/* =========================================
PREVIEW IMAGE
========================================= */

function previewGallery(input){

    if(input.files && input.files[0]){

        let reader = new FileReader();

        reader.onload = function(e){

            let preview =
                input
                .closest('.gallery-item')
                .querySelector('.preview-image');

            preview.src = e.target.result;

            preview.style.display = 'block';
        };

        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
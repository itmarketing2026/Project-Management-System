<?php
require_once 'include/msqli_connect.php';
session_start();

// =========================
// DEBUG ON (boleh padam nanti)
// =========================
ini_set('display_errors', 1);
error_reporting(E_ALL);

// =========================
// GET PROJECT ID
// =========================
$project_id = intval($_GET['project_id'] ?? 0);

if (!$project_id) {
    die("Invalid project ID");
}

if (!user_can_access_project($db, $_SESSION['user_id'], $project_id, $_SESSION['account_type'] ?? '')) {
    die("You do not have permission to view this project.");
}

// =========================
// GET PROJECT DATA
// =========================
$stmt = $db->prepare("SELECT * FROM project_register WHERE project_id=?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$res = $stmt->get_result();

$proj = $res->fetch_assoc();


// =========================
// MAP DATA (SAFE)
// =========================
$project_name = $proj['project_name'] ?? $proj['name'] ?? '';
$prefix       = $proj['pusb'] ?? $proj['company'] ?? '';

// team
$pm = $proj['pic_project_manager'] ?? '';
$cm = $proj['pic_contract_manager'] ?? '';
$re = $proj['pic_resident_engineer'] ?? '';

// tarikh
$contract_start = $proj['site_start'] ?? '';
$contract_end   = $proj['site_end'] ?? '';

// =========================
// CREATE MODE
// =========================
$id = 0;
$data = [];
$subs = [];

// =========================
// SAFE DEFAULT (avoid undefined)
// =========================
$data = array_merge([
    'physical_progress' => '',
    'target_progress' => '',
    'different_progress' => '',
    'days_status' => '',
    'contract_value' => '',
    'newcontract_value' => '',
    'paid' => '',
    'unpaid' => '',
    'balance' => '',
    'wjp' => '',
    'technical_issue' => '',
    'issues' => '',
    'site_issue' => '',
    'mgtm' => ''
], $data);

$eot = null;
$eot_count = '';

$stmt_eot = $db->prepare("
    SELECT eot_date, eot_number 
    FROM eot 
    WHERE project_id = ? 
    ORDER BY eot_number DESC 
    LIMIT 1
");

$stmt_eot->bind_param("i", $project_id);
$stmt_eot->execute();
$res_eot = $stmt_eot->get_result();

if ($row_eot = $res_eot->fetch_assoc()) {
    $eot = $row_eot['eot_date'] ?? null;
    $eot_count = $row_eot['eot_number'] ?? '';
}

$stmt_proj = $db->prepare("SELECT project_name, prefix FROM project WHERE id=?");
$stmt_proj->bind_param("i", $project_id);
$stmt_proj->execute();
$res_proj = $stmt_proj->get_result();

$row_proj = $res_proj->fetch_assoc();

// 🔥 project name dari table project
$project_name = $row_proj['project_name'] ?? '';

// 🔥 prefix full (contoh: PUSB/JKR/B32)
$prefix_full  = $row_proj['prefix'] ?? '';

// 🔥 ambil short prefix (PUSB)
$prefix = trim(explode('/', $prefix_full)[0]);

//subcon
$subs = [];

$stmt = $db->prepare("
    SELECT s.*
    FROM subcontractors s
    WHERE s.report_id = (
        SELECT id 
        FROM weekly_reports 
        WHERE project_id = ?
        ORDER BY id DESC
        LIMIT 1
    )
");

$stmt->bind_param("i", $project_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $subs[] = $row;
}

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
		
</style>
 
</head>

<body>

<div class="container py-5">
    <div class="card shadow-lg p-4">

        <h3 class="mb-4 text-center">📊 Weekly Report</h3>

        <form action="save_report.php" method="POST" enctype="multipart/form-data"
>

<!-- BASIC INFO -->
<div class="mb-2">
<?php 

$today_display = date('d M Y');

?>

<?php

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

    <!-- Project Name -->
    <div class="mb-1">
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
<div class="mb-3">

    <div class="row g-3 align-items-end">

        <!-- COMPANY -->
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Company</label>
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-light">🏢</span>
                <input class="form-control fw-semibold" 
                       name="company" 
                       value="<?= $prefix ?>" 
                       readonly>
            </div>
        </div>

        <!-- PM -->
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Project Manager</label>
            <div class="input-group shadow-sm">
                <span class="input-group-text">PM</span>
                <input class="form-control" 
                       name="pm"
                       value="<?= htmlspecialchars($pm ?? '') ?>">
            </div>
        </div>

        <!-- CM -->
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Contract Manager</label>
            <div class="input-group shadow-sm">
                <span class="input-group-text">CM</span>
                <input class="form-control" 
                       name="cm"
                       value="<?= htmlspecialchars($cm ?? '') ?>">
            </div>
        </div>

        <!-- RE -->
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Resident Engineer</label>
            <div class="input-group shadow-sm">
                <span class="input-group-text">RE</span>
                <input class="form-control" 
                       name="re"
                       value="<?= htmlspecialchars($re ?? '') ?>">
            </div>
        </div>

    </div>

</div>

            <!-- CONTRACT -->
 <div class="mb-4">

    <!-- START DATE -->
    <div class="row align-items-center mb-3 date-row">
        <div class="col-md-4 d-flex align-items-center gap-2">
            <i class="bi bi-flag-fill text-primary"></i>
            <span class="fw-medium">Tarikh Mula Kontrak</span>
        </div>

        <div class="col-md-4">
            <input type="date" 
                   class="form-control date-input"
                   name="contract_start"
                   value="<?= $contract_start ?? '' ?>" 
                   readonly>
        </div>
    </div>

    <!-- END DATE -->
    <div class="row align-items-center date-row">
        <div class="col-md-4 d-flex align-items-center gap-2">
            <i class="bi bi-flag text-secondary"></i>
            <span class="fw-medium">Tarikh Siap Kontrak Asal</span>
        </div>

        <div class="col-md-4">
            <input type="date" 
                   class="form-control date-input"
                   name="contract_end"
                   value="<?= $contract_end ?? '' ?>" 
                   readonly>
        </div>
    </div>

</div>
<div class="row align-items-center date-row mb-3 gy-2">
<?php if (!empty($eot)) : ?>
			
<div class="row align-items-center date-row">
    
    <div class="col-md-4 d-flex align-items-center gap-2">
        <i class="bi bi-clock-history text-warning"></i>
        <span class="fw-medium">
            Tarikh Siap Kontrak (EOT <?= $eot_count ?>)
        </span>
    </div>

    <div class="col-md-4">
        <input type="date" 
               class="form-control date-input"
               name="eot"
               value="<?= $eot ?>" 
               readonly>
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
                   value="<?= $data['physical_progress'] ?>"  placeholder="Actual">
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
                   value="<?= $data['target_progress'] ?>"  placeholder="Schedule">
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
   <input class="form-control" 
       name="sub_name[]" 
       value="<?= htmlspecialchars($sub['name']) ?>" 
       readonly>

				<input type="hidden" name="sub_id[]" value="<?= $sub['id'] ?>">
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

<div class="border rounded p-3 mb-3 bg-light">

    <div class="row g-2 mb-2">
        <div class="col-md-3">

            <input class="form-control" 
                   name="sub_name[]" 
                   value="" 
                   readonly>

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
            <label class="form-label">Nilai Kontrak Baru</label>
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
                  rows="10"
                  placeholder="Perkembangan teknikal..."><?= $data['technical_issue'] ?></textarea>
    </div>
			
			    <!-- Isu -->
    <div class="mb-3">
        <label class="form-label">Isu Kontrak & Dokumen</label>
        <textarea class="form-control" 
                  name="issue" 
                  rows="10"
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

    <div class="border rounded p-3 bg-light mb-3 gallery-item">

        <div class="row g-3">

            <!-- IMAGE -->
            <div class="col-md-5">

                <label class="form-label">
                    Upload Image
                </label>

                <input
                    type="file"
                    name="gallery_image[]"
                    class="form-control gallery-input"
                    accept="image/*"
                    onchange="previewGallery(this)"
                >

                <!-- PREVIEW -->
                <div class="mt-3 preview-container">

                    <img
                        src=""
                        class="img-fluid rounded border preview-image"
                        style="
                            display:none;
                            max-height:220px;
                            object-fit:cover;
                        "
                    >

                </div>

            </div>

            <!-- CAPTION -->
            <div class="col-md-6">

                <label class="form-label">
                    Caption
                </label>

                <input
                    type="text"
                    name="gallery_caption[]"
                    class="form-control"
                    placeholder="Site progress / activity"
                >

            </div>

            <!-- REMOVE -->
            <div class="col-md-1 d-flex align-items-end">

                <button
                    type="button"
                    class="btn btn-danger"
                    onclick="removeGallery(this)"
                >

                    ✖

                </button>

            </div>

        </div>

    </div>

</div>

    <button
        type="button"
        class="btn btn-primary btn-sm"
        onclick="addGallery()"
    >

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
        
<button type="submit" name="action" value="draft" class="btn btn-secondary me-2">
	
    Save as Draft
</button>
<button type="submit" 
        name="action" 
        value="submit" 
        class="btn btn-primary"
        onclick="return validateSubmit()">
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
        <input class="form-control" 
               name="sub_name[]" 
               value=""
               placeholder="Nama Subkontraktor">
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
        btn.closest('.border').remove();
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

	function validateSubmit() {

    let requiredFields = [

        'pm',
        'cm',
        're',
        'physical_progress',
        'target_progress',
        'days_status',
        'contract_value',
        'paid',
        'unpaid',
        'balance',
        'technical_issue',
        'issue',
        'site_issue',
        'mgtm'

    ];

    for (let name of requiredFields) {

        let el = document.querySelector(`[name="${name}"]`);

        if (!el || el.value.trim() === '') {

            alert('Sila lengkapkan semua maklumat sebelum submit.');

            el.focus();
            el.classList.add('border-danger');

            return false;
        }

        el.classList.remove('border-danger');
    }

    // CHECK SUBCONTRACTOR ISSUE
    let subIssues = document.querySelectorAll('[name="sub_issue[]"]');

    for (let sub of subIssues) {

        if (sub.value.trim() === '') {

            alert('Sila isi isu subkontraktor.');

            sub.focus();
            sub.classList.add('border-danger');

            return false;
        }

        sub.classList.remove('border-danger');
    }

    return true;
}
	/* =========================================
ADD GALLERY
========================================= */
function addGallery(){

    let html = `

    <div class="border rounded p-3 bg-light mb-3 gallery-item">

        <div class="row g-3">

            <div class="col-md-5">

                <label class="form-label">
                    Upload Image
                </label>

                <input
                    type="file"
                    name="gallery_image[]"
                    class="form-control gallery-input"
                    accept="image/*"
                    onchange="previewGallery(this)"
                >

                <div class="mt-3 preview-container">

                    <img
                        src=""
                        class="img-fluid rounded border preview-image"
                        style="
                            display:none;
                            max-height:220px;
                            object-fit:cover;
                        "
                    >

                </div>

            </div>

            <div class="col-md-6">

                <label class="form-label">
                    Caption
                </label>

                <input
                    type="text"
                    name="gallery_caption[]"
                    class="form-control"
                    placeholder="Site progress / activity"
                >

            </div>

            <div class="col-md-1 d-flex align-items-end">

                <button
                    type="button"
                    class="btn btn-danger"
                    onclick="removeGallery(this)"
                >

                    ✖

                </button>

            </div>

        </div>

    </div>

    `;

    document
    .getElementById('gallery-list')
    .insertAdjacentHTML('beforeend', html);
}

/* =========================================
REMOVE GALLERY
========================================= */

function removeGallery(btn){

    btn.closest('.border').remove();
}
	/* =========================================
IMAGE PREVIEW
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
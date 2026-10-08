<?php
session_start();
$title = "Project Register";
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    header("Location: login.php");
    exit;
}

$user_id    = $_SESSION['user_id'];
$full_name  = $_SESSION['full_name'] ?? '';
$role       = $_SESSION['account_type'] ?? 'Staff';
$project_id = intval($_GET['project_id'] ?? 0);
if (!$project_id) die("Invalid project selected.");

if (!user_can_access_project($db, $_SESSION['user_id'], $project_id, $_SESSION['account_type'] ?? '')) {
    die("You do not have permission to view this project.");
}

// ================= FETCH =================
$stmt = $db->prepare("SELECT * FROM project WHERE id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$base = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$base) die("Project not found.");

$stmtEOT = $db->prepare("
    SELECT eot_date
    FROM eot
    WHERE project_id = ?
    AND status_eot = 'approved'
    ORDER BY eot_date ASC
");

$stmtEOT->bind_param("i", $project_id);
$stmtEOT->execute();

$eotRows = $stmtEOT->get_result()->fetch_all(MYSQLI_ASSOC);

$stmtEOT->close();

// FETCH REGISTER
$stmt = $db->prepare("SELECT pr.*, u.full_name AS created_by_name
FROM project_register pr
LEFT JOIN users u ON pr.created_by = u.id
WHERE pr.project_id = ?
ORDER BY pr.id ASC LIMIT 1");

$stmt->bind_param("i", $project_id);
$stmt->execute();
$register = $stmt->get_result()->fetch_assoc();
$stmt->close();

$register_exists = !empty($register);

// ================= ROLE CONTROL =================
$is_admin = (
    $role === 'Client Admin' ||
    $role === 'Admin (QA/QC)'
);

$is_first_time = !$register_exists;
$is_owner = (!$register_exists || ($register['created_by'] == $user_id));

$can_edit = $is_admin || ($is_first_time && $is_owner);

// ================= PIC =================
$pic_rows = [];
if ($register_exists) {
    $stmt = $db->prepare("SELECT * FROM project_register WHERE project_id=? ORDER BY id ASC");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $pic_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ================= PROCESS =================
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if (!$can_edit) die("Access denied.");

    // ================= REGISTER =================
    if ($_POST['action'] === 'add_register') {

        $f_title   = trim($_POST['title'] ?? '');
        $f_company = trim($_POST['company'] ?? '');
        $f_contract= trim($_POST['contract_no'] ?? '');
        $f_start   = $_POST['site_start'] ?: null;
        $f_end     = $_POST['site_end'] ?: null;
        $f_period  = trim($_POST['construction_period'] ?? '');
        $raw_sum   = str_replace(',', '', $_POST['contract_sum'] ?? '');
        $f_sum     = (trim($raw_sum) !== '') ? floatval($raw_sum) : null;

        $f_pm      = trim($_POST['pic_project_manager'] ?? '');
        $f_pe      = trim($_POST['pic_project_engineer'] ?? '');
        $f_cm      = trim($_POST['pic_contract_manager'] ?? '');
        $f_re      = trim($_POST['pic_resident_engineer'] ?? '');
        $f_con     = implode(',', array_filter(array_map('trim', $_POST['consultant_name'] ?? [])));
        $f_subcons = implode(',', array_filter(array_map('trim', $_POST['subcon_name'] ?? [])));

        if (!$f_title) $errors[] = "Title is required.";

        // ================= SST =================
        $sst_filename = $register['sst_document'] ?? null;

        if (!empty($_FILES['sst_document']['name'])) {

            $allowed = ['pdf','jpg','jpeg','png','webp'];
            $original = $_FILES['sst_document']['name'];
            $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $errors[] = "Invalid SST file type.";
            } else {

                $nameOnly = pathinfo($original, PATHINFO_FILENAME);
                $safeName = preg_replace("/[^a-zA-Z0-9]/", "_", $nameOnly);

                $newFile = 'sst_' . $safeName . '_' . time() . '.' . $ext;

                $folder = 'uploads/project_' . $project_id . '/sst';

                if (!is_dir($folder)) {
                    mkdir($folder, 0755, true);
                }

                // delete lama
                if ($register_exists && !empty($register['sst_document'])) {
                    $old = 'uploads/' . $register['sst_document'];
                    if (file_exists($old)) unlink($old);
                }

                if (move_uploaded_file($_FILES['sst_document']['tmp_name'], $folder.'/'.$newFile)) {
                    $sst_filename = 'project_'.$project_id.'/sst/'.$newFile;
                } else {
                    $errors[] = "Upload failed.";
                }
            }
        }

        // ================= SAVE =================
        if (empty($errors)) {

            if ($register_exists) {

                $stmt = $db->prepare("
                    UPDATE project_register SET
                    title=?, company=?, contract_no=?, site_start=?, site_end=?,
                    construction_period=?, contract_sum=?, sst_document=?,
                    pic_project_manager=?, pic_project_engineer=?, pic_contract_manager=?, pic_resident_engineer=?,
                    pic_consultant=?, subcontractors=?
                    WHERE project_id=?
                ");

                $stmt->bind_param(
                    "ssssssdsssssssi",
                    $f_title,$f_company,$f_contract,$f_start,$f_end,
                    $f_period,$f_sum,$sst_filename,
                    $f_pm,$f_pe,$f_cm,$f_re,
                    $f_con,$f_subcons,$project_id
                );

            } else {

                $stmt = $db->prepare("
                    INSERT INTO project_register
                    (project_id,title,company,contract_no,site_start,site_end,
                     construction_period,contract_sum,sst_document,
                     pic_project_manager,pic_project_engineer,pic_contract_manager,pic_resident_engineer,
                     pic_consultant,subcontractors,created_by,created_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                ");

                $stmt->bind_param(
                    "issssssdsssssssi",
                    $project_id,$f_title,$f_company,$f_contract,$f_start,
                    $f_end,$f_period,$f_sum,$sst_filename,
                    $f_pm,$f_pe,$f_cm,$f_re,
                    $f_con,$f_subcons,$user_id
                );
            }

            if ($stmt->execute()) {
                header("Location: project_register_list.php?project_id={$project_id}&msg=register_saved");
                exit;
            }

            $errors[] = "Database error: ".$db->error;
        }
    }

    // ================= PIC =================
    if ($_POST['action'] === 'add_pic' && $register_exists) {

        $f_pm      = trim($_POST['pic_project_manager'] ?? '');
        $f_pe      = trim($_POST['pic_project_engineer'] ?? '');
        $f_cm      = trim($_POST['pic_contract_manager'] ?? '');
        $f_re      = trim($_POST['pic_resident_engineer'] ?? '');
        $f_con     = implode(',', array_filter(array_map('trim', $_POST['consultant_name'] ?? [])));
        $f_subcons = implode(',', array_filter(array_map('trim', $_POST['subcon_name'] ?? [])));

        $stmt = $db->prepare("
            INSERT INTO project_register
            (project_id,pic_project_manager,pic_project_engineer,pic_contract_manager,pic_resident_engineer,
             pic_consultant,subcontractors,created_by,created_at)
            VALUES (?,?,?,?,?,?,?,?,NOW())
        ");

        $stmt->bind_param(
            "issssssi",
            $project_id,$f_pm,$f_pe,$f_cm,$f_re,$f_con,$f_subcons,$user_id
        );

        if ($stmt->execute()) {
            header("Location: project_register_list.php?project_id={$project_id}&msg=pic_saved");
            exit;
        }

        $errors[] = "Database error: ".$db->error;
    }
}

$show_reg_form = (!$register_exists || $can_edit) && (!empty($errors) || isset($_GET['add']));
$show_pic_form = $register_exists && (isset($_GET['add_pic']) || (!empty($errors) && ($_POST['action'] ?? '') === 'add_pic'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Project Management System</title>
<style>
:root{
  --blue:#1a56db;--blue-light:#eff6ff;--blue-mid:#3b82f6;
  --slate:#1e293b;--slate-mid:#475569;--slate-light:#94a3b8;
  --border:#e2e8f0;--bg:#f8fafc;--white:#fff;
  --green:#16a34a;--green-light:#f0fdf4;--green-border:#bbf7d0;
  --red:#dc2626;--red-light:#fef2f2;--red-border:#fecaca;
  --shadow-sm:0 1px 3px rgba(0,0,0,.08);--shadow:0 4px 16px rgba(0,0,0,.08);
  --radius:12px;--radius-sm:8px
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:var(--bg);color:var(--slate);min-height:100vh}

.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:var(--radius-sm);font-size:.875rem;font-weight:600;cursor:pointer;border:none;transition:all .18s;text-decoration:none;white-space:nowrap}
.btn-primary{background:var(--blue);color:#fff}.btn-primary:hover{background:#1446b8;transform:translateY(-1px);box-shadow:0 4px 12px rgba(26,86,219,.3)}
.btn-ghost{background:transparent;color:var(--slate-mid);border:1.5px solid var(--border)}.btn-ghost:hover{background:#f1f5f9}
.btn-danger{background:var(--red-light);color:var(--red);border:1.5px solid var(--red-border)}.btn-danger:hover{background:#fee2e2}
.btn-disabled{background:#e2e8f0;color:var(--slate-light);cursor:not-allowed;border:1.5px solid var(--border);pointer-events:none}
.btn-sm{padding:5px 12px;font-size:.78rem}
.btn-icon{width:30px;height:30px;padding:0;justify-content:center;border-radius:6px}

.page-wrap{max-width:1200px;margin:0 auto;padding:28px 24px 80px; overflow-x:hidden;}

.page-header{background:#007bff;border-radius:var(--radius);padding:24px 28px;margin-bottom:20px;box-shadow:var(--shadow-sm);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.page-header-name{font-size:32px;font-weight:800;color:#fff;word-break:break-word;overflow-wrap:anywhere}

.notice{padding:12px 16px;border-radius:var(--radius-sm);margin-bottom:16px;font-size:.875rem;font-weight:500;display:flex;align-items:center;gap:8px}
.notice-success{background:var(--green-light);border:1px solid var(--green-border);color:var(--green)}
.error-box{background:var(--red-light);border:1px solid var(--red-border);border-radius:var(--radius-sm);padding:12px 16px;color:var(--red);margin-bottom:16px;font-size:.875rem}

.add-panel{background:var(--white);border:2px solid var(--blue);border-radius:var(--radius);margin-bottom:20px;box-shadow:var(--shadow);overflow:hidden;display:none}
.add-panel.open{display:block}
.add-panel-header{display:flex;align-items:center;padding:14px 24px;background:var(--blue-light);border-bottom:1px solid #bfdbfe}
.add-panel-title{font-weight:700;color:var(--blue);font-size:.95rem}
.add-panel-body{padding:24px}
.form-group{margin-bottom:16px}
.form-label{font-size:.78rem;font-weight:700;color:var(--slate-mid);text-transform:uppercase;margin-bottom:6px;display:block;letter-spacing:.4px}
.form-control{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.9rem;font-family:Arial,sans-serif;transition:border-color .15s;background:var(--white);color:var(--slate)}
.form-control:focus{outline:none;border-color:var(--blue-mid)}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-section-label{font-size:.72rem;font-weight:800;text-transform:uppercase;color:var(--blue);margin:20px 0 12px;letter-spacing:.6px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.sep{height:1px;background:var(--border);margin:20px 0}
.form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:24px;padding-top:20px;border-top:1px solid var(--border);flex-wrap:wrap}
.subcon-row{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;margin-bottom:10px}
.add-more-btn{padding:5px 10px;border:1.5px dashed var(--blue-mid);border-radius:var(--radius-sm);color:var(--blue);font-size:.78rem;font-weight:600;background:var(--blue-light);cursor:pointer;white-space:nowrap}

.section-card{background:var(--white);border:2px solid #007bff;border-radius:var(--radius);margin-bottom:20px;box-shadow:var(--shadow-sm);overflow:hidden}
.section-card-header{display:flex;align-items:center;gap:10px;padding:14px 20px;background:#007bff;cursor:pointer;user-select:none}
.section-card-title{font-weight:700;font-size:1rem;flex:1;color:#fff}
.section-card-chevron{color:#fff;font-size:.85rem;transition:transform .2s}
.section-card-header.collapsed .section-card-chevron{transform:rotate(-90deg)}

.info-table{width:100%;border-collapse:collapse}
.info-table tr{border-bottom:1px solid var(--border)}
.info-table tr:last-child{border-bottom:none}
.info-table td{padding:12px 20px;font-size:14px;vertical-align:middle;word-break:break-word;overflow-wrap:anywhere}
.info-table td.label{font-weight:600;color:var(--slate-mid);width:200px;background:#fafbfc;border-right:1px solid var(--border)}

.pic-section-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:#007bff;flex-wrap:wrap;gap:10px}
.pic-section-left{display:flex;align-items:center;gap:10px;flex-wrap:wrap}

/* ── Desktop PIC table ── */
.pic-table-wrap{overflow-x:auto}
.pic-table{width:100%;border-collapse:collapse;min-width:900px}
.pic-table thead tr{background:#f8fafc;border-bottom:2px solid var(--border)}
.pic-table thead th{padding:11px 14px;font-size:14px;font-weight:700;color:var(--slate-mid);text-transform:uppercase;letter-spacing:.4px;text-align:left;white-space:nowrap}
.pic-table tbody tr{border-bottom:1px solid var(--border);transition:background .15s;cursor:pointer}
.pic-table tbody tr:last-child{border-bottom:none}
.pic-table tbody tr:hover{background:var(--blue-light)}
.pic-table tbody td{padding:13px 14px;font-size:14px;vertical-align:middle}
.pic-table tbody td.td-no{color:var(--slate-light);font-size:.8rem;font-weight:700;width:40px}
.pic-table tbody td.td-name{font-weight:600;color:var(--slate)}
.pic-table tbody td.td-muted{color:var(--slate-mid)}

/* ── Mobile PIC cards ── */
.pic-card-list{display:none;padding:14px}
.pic-card{border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:10px;cursor:pointer;background:var(--white);transition:box-shadow .15s}
.pic-card:hover{box-shadow:0 2px 8px rgba(26,86,219,.15)}
.pic-card .pic-card-no{font-size:.75rem;color:var(--slate-light);font-weight:700;margin-bottom:4px}
.pic-card .pic-card-row{display:flex;justify-content:space-between;gap:10px;padding:4px 0;font-size:13px;border-bottom:1px dashed var(--border)}
.pic-card .pic-card-row:last-child{border-bottom:none}
.pic-card .pic-card-row .k{color:var(--slate-mid);font-weight:600;flex-shrink:0}
.pic-card .pic-card-row .v{color:var(--slate);text-align:right;word-break:break-word;overflow-wrap:anywhere}

.empty-state{text-align:center;padding:48px 24px;color:var(--slate-light)}
.empty-state .icon{font-size:2rem;margin-bottom:10px}
.empty-state p{font-size:.9rem}

@media(max-width:640px){
  .page-wrap{padding:16px 12px 60px}
  .page-header{padding:18px 20px}
  .page-header-name{font-size:24px}
  .form-grid{grid-template-columns:1fr}
  .info-table td.label{width:130px}
  .add-panel-body{padding:16px}
  .form-actions{justify-content:stretch}
  .form-actions .btn{flex:1;text-align:center;justify-content:center}

  /* Sembunyi table PIC, tukar jadi card */
  .pic-table-wrap{display:none}
  .pic-card-list{display:block}
}
</style>
</head>
<body>
<?php include 'include/topbarstaff.php'; ?>

<div class="page-wrap">

  <div class="page-header">
    <div>
      <div class="page-header-name"><?= htmlspecialchars($base['project_name'] ?? '-') ?></div>
    </div>
    <?php if($register_exists && !$can_edit): ?>
  <button class="btn btn-disabled" disabled>✓ Register Submitted</button>

<?php elseif($register_exists && $can_edit): ?>
  <button class="btn btn-primary" onclick="toggleRegForm()">✏️ Edit Register</button>

<?php else: ?>
  <button class="btn btn-primary" onclick="toggleRegForm()">+ Register Project</button>
<?php endif; ?>
  </div>

  <?php if(isset($_GET['msg'])): ?>
    <?php if($_GET['msg']==='register_saved'): ?>
      <div class="notice notice-success">✅ Project register saved successfully.</div>
    <?php elseif($_GET['msg']==='pic_saved'): ?>
      <div class="notice notice-success">✅ Person In Charge entry added successfully.</div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Register Form -->
<?php if(!$register_exists || $can_edit): ?>
	<div class="add-panel <?= $show_reg_form ? 'open' : '' ?>" id="reg-panel">
    <div class="add-panel-header">
      <div class="add-panel-title">Register Project</div>
    </div>
    <div class="add-panel-body">
      <?php if(!empty($errors)): ?>
        <div class="error-box">⚠️ <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
      <?php endif; ?>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_register">
        <div class="form-section-label">Project Details</div>
        <div class="form-group">
          <label class="form-label">Title <span style="color:var(--red)">*</span></label>
          <input class="form-control" type="text" name="title" placeholder="e.g. Menaiktaraf Laluan..." value="<?= htmlspecialchars($_POST['title'] ?? $register['title'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Company</label>
          <input class="form-control" type="text" name="company" placeholder="Company Name" value="<?= htmlspecialchars($_POST['company'] ?? $register['company'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">No. Contract</label>
          <input class="form-control" type="text" name="contract_no" placeholder="Contract Number" value="<?= htmlspecialchars($_POST['contract_no'] ?? $register['contract_no'] ?? '') ?>">
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Site Commenced On</label>
            <input class="form-control" type="date" name="site_start" value="<?= htmlspecialchars($_POST['site_start'] ?? $register['site_start'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Site Completed On</label>
            <input class="form-control" type="date" name="site_end" value="<?= htmlspecialchars($_POST['site_end'] ?? $register['site_end'] ?? '') ?>">
          </div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Construction Period</label>
            <input class="form-control" type="text" name="construction_period" placeholder="e.g. 61 MONTHS" value="<?= htmlspecialchars($_POST['construction_period'] ?? $register['construction_period'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Contract Sum (RM)</label>
            <input class="form-control" type="text" name="contract_sum" placeholder="e.g. 12450000" value="<?= htmlspecialchars($_POST['contract_sum'] ?? $register['contract_sum'] ?? '') ?>">
          </div>
        </div>
		  <div class="form-grid">
  <div class="form-group">
    <label class="form-label">SST Document</label>
    <input class="form-control" type="file" name="sst_document">
  </div>

  <div class="form-group">
    <label class="form-label">Current Document</label>

    <?php if(!empty($register['sst_document'])): ?>
	<?php
$sstPath = trim($register['sst_document'] ?? '');

$sstPath = str_replace('\\', '/', $sstPath);
$sstPath = ltrim($sstPath, '/');

if (stripos($sstPath, 'uploads/') === 0) {
    $sstPath = $sstPath;
} else {
    $sstPath = 'uploads/' . $sstPath;
}
?>

<a
    href="download_file.php?file=<?= urlencode($sstPath) ?>"
    target="_blank"
    style="
        color:var(--blue);
        font-weight:600;
        text-decoration:none;
    "
>
    📄 View Document
</a>
	<?php else: ?>
	  <div style="color:var(--slate-light);font-style:italic;">
		No document uploaded
	  </div>
	<?php endif; ?>

  </div>
</div>
		  


        <div class="sep"></div>
        <div class="form-section-label">Person In Charge</div>

        <!-- Row 1: PM + PE -->
		<div class="form-grid">
		  <div class="form-group">
			<label class="form-label">Project Manager</label>
			<input class="form-control" type="text" name="pic_project_manager"
				   placeholder="Enter Project Manager name"
				   value="<?= htmlspecialchars(
    $_POST['pic_project_manager'] 
    ?? $register['pic_project_manager'] 
    ?? ($pic_rows[0]['pic_project_manager'] ?? '')
) ?>">
		  </div>
		  <div class="form-group">
			<label class="form-label">Project Engineer</label>
			  		<input class="form-control" type="text" name="pic_project_engineer"
				   placeholder="Enter Project Engineer name"
				   value="<?= htmlspecialchars(
    $_POST['pic_project_engineer'] 
    ?? $register['pic_project_engineer'] 
    ?? ($pic_rows[0]['pic_project_engineer'] ?? '')
) ?>">

		  </div>
		</div>

		<!-- Row 2: CM + RE -->
		<div class="form-grid">
		  <div class="form-group">
			<label class="form-label">Contract Manager</label>
			<input class="form-control" type="text" name="pic_contract_manager"
				   placeholder="Enter Contract Manager name"
				   value="<?= htmlspecialchars(
    $_POST['pic_contract_manager'] 
    ?? $register['pic_contract_manager'] 
    ?? ($pic_rows[0]['pic_contract_manager'] ?? '')
) ?>">
			  
		  </div>
		  <div class="form-group">
			<label class="form-label">Resident Engineer</label>
			<input class="form-control" type="text" name="pic_resident_engineer"
				   placeholder="Enter Resident Engineer name"
				   value="<?= htmlspecialchars(
    $_POST['pic_resident_engineer'] 
    ?? $register['pic_resident_engineer'] 
    ?? ($pic_rows[0]['pic_resident_engineer'] ?? '')
) ?>">
			 
		  </div>
		</div>

        <div class="form-group">
          <div class="form-section-label" style="margin-top:0">
            <span>Sub Contractors</span>
            <button type="button" class="add-more-btn" onclick="addDynamic('subcon-list-reg','subcon_name[]','Sub contractor name...')">＋ Add</button>
          </div>
          <div id="subcon-list-reg">
            <?php
            $subcon_values = $_POST['subcon_name']
                ?? (!empty($register['subcontractors']) ? array_map('trim', explode(',', $register['subcontractors'])) : ['']);
            foreach ($subcon_values as $sv): ?>
            <div class="subcon-row">
              <input class="form-control" type="text" name="subcon_name[]" placeholder="Sub contractor name..." value="<?= htmlspecialchars($sv) ?>">
              <button type="button" class="btn btn-danger btn-icon" onclick="removeDynamic(this,'subcon-list-reg')">✕</button>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group">
          <div class="form-section-label" style="margin-top:0">
            <span>Consultants</span>
            <button type="button" class="add-more-btn" onclick="addDynamic('consultant-list-reg','consultant_name[]','Consultant name...')">＋ Add</button>
          </div>
          <div id="consultant-list-reg">
            <?php
            $consultant_values = $_POST['consultant_name']
                ?? (!empty($register['pic_consultant']) ? array_map('trim', explode(',', $register['pic_consultant'])) : ['']);
            foreach ($consultant_values as $cv): ?>
            <div class="subcon-row">
              <input class="form-control" type="text" name="consultant_name[]" placeholder="Consultant name..." value="<?= htmlspecialchars($cv) ?>">
              <button type="button" class="btn btn-danger btn-icon" onclick="removeDynamic(this,'consultant-list-reg')">✕</button>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-ghost" onclick="toggleRegForm()">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Register</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <?php if($register_exists): ?>

  <!-- Project Register Info Card -->
  <div class="section-card">
    <div class="section-card-header" onclick="toggleCard(this)">
      <div class="section-card-title">Project Register</div>
      <span class="section-card-chevron">▼</span>
    </div>
    <div class="card-collapsible" style="overflow-x:auto">
      <table class="info-table">
        <tr><td class="label">Company</td><td><?= htmlspecialchars($register['company'] ?? '-') ?></td></tr>
        <tr><td class="label">Title</td><td><?= htmlspecialchars($register['title'] ?? '-') ?></td></tr>
        <tr><td class="label">No. Contract</td><td><?= htmlspecialchars($register['contract_no'] ?? '-') ?></td></tr>
        <tr><td class="label">Site Commenced On</td><td><?= !empty($register['site_start']) ? date('d/m/Y', strtotime($register['site_start'])) : '-' ?></td></tr>
<tr>
    <td class="label">Site Completed On</td>
    <td>
        <?= !empty($register['site_end'])
            ? date('d/m/Y', strtotime($register['site_end']))
            : '-' ?>

        <?php
        $i = 1;
        foreach ($eotRows as $eot):
        ?>
            <br>
            <span style="
                display:inline-block;
                margin-top:5px;
                padding:4px 10px;
                background:#fff3cd;
                color:#856404;
                border-radius:20px;
                font-size:12px;
                font-weight:600;
            ">
                EOT <?= $i++ ?> :
                <?= date('d/m/Y', strtotime($eot['eot_date'])) ?>
            </span>
        <?php endforeach; ?>
    </td>
</tr>
        <tr><td class="label">Construction Period</td><td><?= htmlspecialchars($register['construction_period'] ?? '-') ?></td></tr>
        <tr><td class="label">Contract Sum</td><td><?= !empty($register['contract_sum']) ? 'RM ' . number_format($register['contract_sum'], 2) : '-' ?></td></tr>
        <tr>
		  <td class="label">SST Document</td>
		  <td>
			<?php if(!empty($register['sst_document'])): ?>
			 <?php
$sstPath = trim($register['sst_document'] ?? '');

$sstPath = str_replace('\\', '/', $sstPath);
$sstPath = ltrim($sstPath, '/');

if (stripos($sstPath, 'uploads/') === 0) {
    $sstPath = $sstPath;
} else {
    $sstPath = 'uploads/' . $sstPath;
}
?>

<a
    href="download_file.php?file=<?= urlencode($sstPath) ?>"
    target="_blank"
    style="
        color:var(--blue);
        font-weight:600;
        text-decoration:none;
    "
>
    📄 View SST Document
</a>
			<?php else: ?>
			  <span style="color:var(--slate-light);font-style:italic;">No document uploaded</span>
			<?php endif; ?>
		  </td>
		</tr>
      </table>
    </div>
  </div>

  <!-- Add PIC Form -->
  <div class="add-panel <?= $show_pic_form ? 'open' : '' ?>" id="pic-panel">
    <div class="add-panel-header">
      <div class="add-panel-title">Add Person In Charge</div>
    </div>
    <div class="add-panel-body">
      <?php if(!empty($errors) && ($_POST['action']??'') === 'add_pic'): ?>
        <div class="error-box">⚠️ <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="action" value="add_pic">

        <!-- Row 1: PM + PE -->
		<div class="form-grid">
		  <div class="form-group">
			<label class="form-label">Project Manager</label>
			<input class="form-control" type="text" name="pic_project_manager"
				   placeholder="Enter Project Manager name"
				   value="<?= htmlspecialchars(
    $_POST['pic_project_manager'] 
    ?? $register['pic_project_manager'] 
    ?? ($pic_rows[0]['pic_project_manager'] ?? '')
) ?>">
		  </div>
		  <div class="form-group">
			<label class="form-label">Project Engineer</label>
			<input class="form-control" type="text" name="pic_project_engineer"
				   placeholder="Enter Project Engineer name"
value="<?= htmlspecialchars(
    $_POST['pic_project_engineer'] 
    ?? $register['pic_project_engineer'] 
    ?? ($pic_rows[0]['pic_project_engineer'] ?? '')
) ?>">
		  </div>
		</div>

		<!-- Row 2: CM + RE -->
		<div class="form-grid">
		  <div class="form-group">
			<label class="form-label">Contract Manager</label>
			<input class="form-control" type="text" name="pic_contract_manager"
				   placeholder="Enter Contract Manager name"
				   value="<?= htmlspecialchars(
    $_POST['pic_contract_manager'] 
    ?? $register['pic_contract_manager'] 
    ?? ($pic_rows[0]['pic_contract_manager'] ?? '')
) ?>">
		  </div>
		  <div class="form-group">
			<label class="form-label">Resident Engineer</label>
			<input class="form-control" type="text" name="pic_resident_engineer"
				   placeholder="Enter Resident Engineer name"
				   value="<?= htmlspecialchars(
    $_POST['pic_resident_engineer'] 
    ?? $register['pic_resident_engineer'] 
    ?? ($pic_rows[0]['pic_resident_engineer'] ?? '')
) ?>">
		  </div>
		</div>

        <div class="form-group">
          <div class="form-section-label" style="margin-top:0">
            <span>Sub Contractors</span>
            <button type="button" class="add-more-btn" onclick="addDynamic('subcon-list-pic','subcon_name[]','Sub contractor name...')">＋ Add</button>
          </div>
          <div id="subcon-list-pic">
            <div class="subcon-row">
              <input class="form-control" type="text" name="subcon_name[]" placeholder="Sub contractor name...">
              <button type="button" class="btn btn-danger btn-icon" onclick="removeDynamic(this,'subcon-list-pic')">✕</button>
            </div>
          </div>
        </div>
        <div class="form-group">
          <div class="form-section-label" style="margin-top:0">
            <span>Consultants</span>
            <button type="button" class="add-more-btn" onclick="addDynamic('consultant-list-pic','consultant_name[]','Consultant name...')">＋ Add</button>
          </div>
          <div id="consultant-list-pic">
            <div class="subcon-row">
              <input class="form-control" type="text" name="consultant_name[]" placeholder="Consultant name...">
              <button type="button" class="btn btn-danger btn-icon" onclick="removeDynamic(this,'consultant-list-pic')">✕</button>
            </div>
          </div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-ghost" onclick="togglePicForm()">Cancel</button>
          <button type="submit" class="btn btn-primary">Save PIC</button>
        </div>
      </form>
    </div>
  </div>

  <!-- PIC Table -->
  <div class="section-card">
    <div class="pic-section-header">
      <div class="pic-section-left">
        <span class="section-card-title" style="color:#fff">Person In Charge</span>
        <span style="font-size:.78rem;color:#fff;font-weight:600;opacity:.8;">
          <?= count($pic_rows) ?> entr<?= count($pic_rows)!==1?'ies':'y' ?>
        </span>
      </div>
     
    </div>

    <?php if(!empty($pic_rows)): ?>
    <!-- Desktop table -->
    <div class="pic-table-wrap">
      <table class="pic-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Project Manager</th>
            <th>Project Engineer</th>
            <th>Contract Manager</th>
            <th>Resident Engineer</th>
            <th>Sub Contractor(s)</th>
            <th>Consultant(s)</th>
            <th>Date Added</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($pic_rows as $i => $row):
            $subcons = array_filter(array_map('trim', explode(',', $row['subcontractors'] ?? '')));
            $cons    = array_filter(array_map('trim', explode(',', $row['pic_consultant'] ?? '')));
          ?>
          <tr onclick="window.location.href='project_register.php?id=<?= $row['id'] ?>&project_id=<?= $project_id ?>'">
            <td class="td-no"><?= $i + 1 ?></td>
  			 <td class="td-name"><?= htmlspecialchars($row['pic_project_manager'] ?: '—') ?></td>
            <td class="td-muted"><?= htmlspecialchars($row['pic_project_engineer'] ?: '—') ?></td>
            <td class="td-muted"><?= htmlspecialchars($row['pic_contract_manager'] ?: '—') ?></td>
            <td class="td-muted"><?= htmlspecialchars($row['pic_resident_engineer'] ?: '—') ?></td>
            <td class="td-muted"><?= !empty($subcons) ? htmlspecialchars(implode(', ', $subcons)) : '—' ?></td>
            <td class="td-muted"><?= !empty($cons)    ? htmlspecialchars(implode(', ', $cons))    : '—' ?></td>
            <td class="td-muted" style="white-space:nowrap"><?= !empty($row['created_at']) ? date('d/m/Y', strtotime($row['created_at'])) : '-' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile cards -->
    <div class="pic-card-list">
      <?php foreach($pic_rows as $i => $row):
        $subcons = array_filter(array_map('trim', explode(',', $row['subcontractors'] ?? '')));
        $cons    = array_filter(array_map('trim', explode(',', $row['pic_consultant'] ?? '')));
      ?>
      <div class="pic-card" onclick="window.location.href='project_register.php?id=<?= $row['id'] ?>&project_id=<?= $project_id ?>'">
        <div class="pic-card-no">#<?= $i + 1 ?> — <?= !empty($row['created_at']) ? date('d/m/Y', strtotime($row['created_at'])) : '-' ?></div>
        <div class="pic-card-row"><span class="k">Project Manager</span><span class="v"><?= htmlspecialchars($row['pic_project_manager'] ?: '—') ?></span></div>
        <div class="pic-card-row"><span class="k">Project Engineer</span><span class="v"><?= htmlspecialchars($row['pic_project_engineer'] ?: '—') ?></span></div>
        <div class="pic-card-row"><span class="k">Contract Manager</span><span class="v"><?= htmlspecialchars($row['pic_contract_manager'] ?: '—') ?></span></div>
        <div class="pic-card-row"><span class="k">Resident Engineer</span><span class="v"><?= htmlspecialchars($row['pic_resident_engineer'] ?: '—') ?></span></div>
        <div class="pic-card-row"><span class="k">Sub Contractor(s)</span><span class="v"><?= !empty($subcons) ? htmlspecialchars(implode(', ', $subcons)) : '—' ?></span></div>
        <div class="pic-card-row"><span class="k">Consultant(s)</span><span class="v"><?= !empty($cons) ? htmlspecialchars(implode(', ', $cons)) : '—' ?></span></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <div class="empty-state">
        <div class="icon">👤</div>
        <p>No Person In Charge entries yet.</p>
        <p style="margin-top:6px;font-size:.82rem">Click <strong>+ Add PIC</strong> to get started.</p>
      </div>
    <?php endif; ?>
  </div>

  <?php endif; ?>
</div>

<script>
const regPanel = document.getElementById('reg-panel');
function toggleRegForm() {
  if (!regPanel) return;
  regPanel.classList.toggle('open');
  if (regPanel.classList.contains('open')) regPanel.scrollIntoView({ behavior:'smooth', block:'start' });
}

const picPanel = document.getElementById('pic-panel');
function togglePicForm() {
  if (!picPanel) return;
  picPanel.classList.toggle('open');
  if (picPanel.classList.contains('open')) picPanel.scrollIntoView({ behavior:'smooth', block:'nearest' });
}

function toggleCard(header) {
  const body = header.nextElementSibling;
  header.classList.toggle('collapsed');
  body.style.display = header.classList.contains('collapsed') ? 'none' : 'block';
}

function addDynamic(listId, inputName, placeholder) {
  const list = document.getElementById(listId);
  const row = document.createElement('div');
  row.className = 'subcon-row';
  row.innerHTML = `<input class="form-control" type="text" name="${inputName}" placeholder="${placeholder}">
    <button type="button" class="btn btn-danger btn-icon" onclick="removeDynamic(this,'${listId}')">✕</button>`;
  list.appendChild(row);
}
function removeDynamic(btn, listId) {
  const list = document.getElementById(listId);
  if (list.querySelectorAll('.subcon-row').length > 1) btn.closest('.subcon-row').remove();
}
	function toggleReplaceSST(){
  const box = document.getElementById("replaceSSTBox");
  box.style.display = (box.style.display === "none") ? "block" : "none";
}
</script>
</body>
</html>
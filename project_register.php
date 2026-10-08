<?php
session_start();
$title = "Project Register Detail";
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    header("Location: login.php");
    exit;
}

$user_id     = $_SESSION['user_id'];
$role        = $_SESSION['account_type'] ?? 'Staff';
$is_admin = (
    $role === 'Client Admin' ||
    $role === 'Admin (QA/QC)'
);
$register_id = intval($_GET['id'] ?? 0);
$project_id  = intval($_GET['project_id'] ?? 0);
if (!$register_id || !$project_id) die("Invalid request.");

if (!user_can_access_project($db, $_SESSION['user_id'], $project_id, $_SESSION['account_type'] ?? '')) {
    die("You do not have permission to view this project.");
}

foreach (['status_pm','status_pe','status_cm','status_re','status_subcon','status_consultant'] as $col) {
    $res = $db->query("SHOW COLUMNS FROM `project_register` LIKE '{$col}'");
    if ($res && $res->num_rows === 0) {
        $db->query("ALTER TABLE `project_register` ADD COLUMN `{$col}` ENUM('active','inactive') NOT NULL DEFAULT 'active'");
    }
}

foreach (['pic_contract_manager','pic_resident_engineer'] as $col) {
    $res = $db->query("SHOW COLUMNS FROM `project_register` LIKE '{$col}'");
    if ($res && $res->num_rows === 0) {
        $db->query("ALTER TABLE `project_register` ADD COLUMN `{$col}` VARCHAR(150) DEFAULT NULL");
    }
}

// Fetch project info
$stmt = $db->prepare("SELECT * FROM `project_register` WHERE project_id = ? ORDER BY id ASC LIMIT 1");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$base = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$base) die("Project register not found.");

// Fetch project name
$stmt = $db->prepare("SELECT * FROM `project` WHERE id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$project) die("Project not found.");

// Fetch this specific PIC row
$stmt = $db->prepare("SELECT pr.*, u.full_name AS created_by_name
                      FROM `project_register` pr
                      LEFT JOIN `users` u ON pr.created_by = u.id
                      WHERE pr.id = ? AND pr.project_id = ?");
$stmt->bind_param("ii", $register_id, $project_id);
$stmt->execute();
$pic_row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$pic_row) die("PIC record not found.");

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin) {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['deactivate_and_replace', 'add_name'])) {
        $field_map = [
            'pm'         => ['status' => 'status_pm',         'col' => 'pic_project_manager'],
            'pe'         => ['status' => 'status_pe',         'col' => 'pic_project_engineer'],
            'cm'         => ['status' => 'status_cm',         'col' => 'pic_contract_manager'],
            're'         => ['status' => 'status_re',         'col' => 'pic_resident_engineer'],
            'subcon'     => ['status' => 'status_subcon',     'col' => 'subcontractors'],
            'consultant' => ['status' => 'status_consultant', 'col' => 'pic_consultant'],
        ];

        $field_key = trim($_POST['field_key'] ?? '');
        $name      = trim($_POST['name'] ?? '');
        $rid       = intval($_POST['row_id'] ?? 0);
        $pid       = intval($_POST['project_id'] ?? 0);

        if (!isset($field_map[$field_key])) die("Invalid field key.");
        if (!$name) die("Name is required.");
        if (!$rid || !$pid) die("Invalid row or project ID.");

        $status_col = $field_map[$field_key]['status'];
        $data_col   = $field_map[$field_key]['col'];

        // Fetch current row
        $stmt = $db->prepare("SELECT `{$data_col}`, `{$status_col}` FROM `project_register` WHERE id = ?");
        $stmt->bind_param("i", $rid);
        $stmt->execute();
        $current_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$current_row) die("Could not fetch current row.");

        if ($action === 'deactivate_and_replace') {
            $old_value = (string)($current_row[$data_col] ?? '');
            if ($old_value) {
                $stmt = $db->prepare("INSERT INTO `project_register_history`
                    (`register_id`, `project_id`, `field_name`, `value`, `created_at`, `replaced_by`, `deactivated_at`)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("iisssi", $rid, $pid, $field_key, $old_value, date('Y-m-d H:i:s'), $user_id);
                $stmt->execute();
                $stmt->close();
            }
            $new_value = $name;
        } else {
            // ADD logic
            $current_value = (string)($current_row[$data_col] ?? '');
            $names = array_filter(array_map('trim', explode(',', $current_value)));
            $names[] = $name;
            $new_value = implode(', ', array_unique($names));
        }

        $new_value = preg_replace('/\s*,\s*/', ', ', trim($new_value, ', '));
        $new_value = preg_replace('/,,+/', ',', $new_value);
        $new_value = trim($new_value, ', ');

        $stmt = $db->prepare("UPDATE `project_register` SET `{$data_col}` = ?, `{$status_col}` = 'active' WHERE id = ? AND project_id = ?");
        $stmt->bind_param("sii", $new_value, $rid, $pid);
        if (!$stmt->execute()) die("Execute failed: " . $stmt->error);
        $stmt->close();

        $msg = ($action === 'deactivate_and_replace') ? 'replaced' : 'added';
        header("Location: project_register.php?id={$rid}&project_id={$pid}&msg={$msg}");
        exit;
    }
}

// Fetch history for this row
$history = ['pm' => [], 'pe' => [], 'cm' => [], 're' => [], 'subcon' => [], 'consultant' => []];
$stmt = $db->prepare("SELECT * FROM `project_register_history` WHERE register_id = ? ORDER BY created_at ASC");
$stmt->bind_param("i", $register_id);
$stmt->execute();
$hist_result = $stmt->get_result();
while ($h = $hist_result->fetch_assoc()) {
    if (isset($history[$h['field_name']])) {
        $history[$h['field_name']][] = $h;
    }
}
$stmt->close();
$db->close();

// Build current display values
$subcons = array_filter(array_map('trim', explode(',', $pic_row['subcontractors'] ?? '')));
$cons    = array_filter(array_map('trim', explode(',', $pic_row['pic_consultant'] ?? '')));

$current_fields = [
    'pm'         => ['value' => $pic_row['pic_project_manager']   ?? '', 'date' => $pic_row['created_at'] ?? ''],
    'pe'         => ['value' => $pic_row['pic_project_engineer']  ?? '', 'date' => $pic_row['created_at'] ?? ''],
    'cm'         => ['value' => $pic_row['pic_contract_manager']  ?? '', 'date' => $pic_row['created_at'] ?? ''],
    're'         => ['value' => $pic_row['pic_resident_engineer'] ?? '', 'date' => $pic_row['created_at'] ?? ''],
    'subcon'     => ['value' => implode(', ', $subcons),                  'date' => $pic_row['created_at'] ?? ''],
    'consultant' => ['value' => implode(', ', $cons),                     'date' => $pic_row['created_at'] ?? ''],
];
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
  --shadow-sm:0 1px 3px rgba(0,0,0,.08);
  --radius:12px;--radius-sm:8px
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{overflow-x:hidden}
body{font-family:Arial,sans-serif;background:var(--bg);color:var(--slate);min-height:100vh}
.page-wrap{max-width:1100px;margin:0 auto;padding:28px 24px 80px}

.page-header{background:#007bff;border-radius:var(--radius);padding:24px 28px;margin-bottom:20px;box-shadow:var(--shadow-sm);display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
.page-header-meta{font-size:14px;color:#dbeafe;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:6px}
.page-header-name{font-size:28px;font-weight:800;color:#fff;line-height:1.3}

.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:var(--radius-sm);font-size:14px;font-weight:600;cursor:pointer;border:none;transition:all .18s;text-decoration:none}
.btn-primary{background:var(--blue);color:#fff}.btn-primary:hover{background:#1446b8}
.btn-ghost{background:transparent;color:var(--slate-mid);border:1.5px solid var(--border)}.btn-ghost:hover{background:#f1f5f9}
.btn-danger{background:var(--red-light);color:var(--red);border:1.5px solid var(--red-border)}.btn-danger:hover{background:#fee2e2}
.btn-sm{padding:5px 12px;font-size:13px}
.back-btn{display:inline-block;margin-bottom:16px;background:#007bff;color:#fff;padding:10px 18px;border-radius:8px;font-weight:bold;text-decoration:none;transition:background 0.2s}
.back-btn:hover{background:#0056b3}

.notice{padding:12px 16px;border-radius:var(--radius-sm);margin-bottom:16px;font-size:14px;font-weight:500;display:flex;align-items:center;gap:8px}
.notice-success{background:var(--green-light);border:1px solid var(--green-border);color:var(--green)}

.card{background:var(--white);border:2px solid #007bff;border-radius:var(--radius);margin-bottom:20px;box-shadow:var(--shadow-sm);overflow:hidden}
.card-header{display:flex;align-items:center;padding:14px 20px;background:#007bff;cursor:pointer;user-select:none}
.card-title{font-weight:700;font-size:14px;flex:1;color:#fff}
.card-chevron{color:#fff;font-size:.85rem;transition:transform .2s}
.card-header.collapsed .card-chevron{transform:rotate(-90deg)}
.info-table{width:100%;border-collapse:collapse}
.info-table tr{border-bottom:1px solid var(--border)}
.info-table tr:last-child{border-bottom:none}
.info-table td{padding:12px 20px;font-size:14px;vertical-align:middle}
.info-table td.label{font-weight:600;color:var(--slate-mid);width:200px;background:#fafbfc;border-right:1px solid var(--border)}

.pic-card{background:var(--white);border:2px solid #007bff;border-radius:var(--radius);margin-bottom:20px;overflow:hidden;box-shadow:var(--shadow-sm)}
.pic-card-header{padding:16px 24px;background:#007bff}
.pic-card-title{font-weight:700;color:#fff;font-size:15px}

.field-section{border-bottom:1px solid var(--border)}
.field-section:last-of-type{border-bottom:none}
.field-header{display:flex;align-items:center;justify-content:space-between;padding:11px 24px;background:#f8fafc;border-bottom:1px solid var(--border)}
.field-label{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:var(--slate-mid)}

.active-row{display:flex;align-items:center;gap:16px;padding:15px 24px;flex-wrap:wrap}
.active-name{font-size:15px;font-weight:700;color:var(--slate);flex:1}
.active-since{font-size:12px;color:var(--slate-light)}
.badge-active{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;background:var(--green-light);color:var(--green);border:1px solid var(--green-border);white-space:nowrap}

.replace-box{display:none;padding:12px 24px 16px;background:#f0f7ff;border-top:1px dashed #bfdbfe}
.replace-box.open{display:block}
.replace-box-inner{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.replace-input{flex:1;padding:9px 13px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:14px;min-width:180px;font-family:Arial,sans-serif;background:var(--white)}
.replace-input:focus{outline:none;border-color:var(--blue-mid)}
.replace-hint{font-size:12px;color:#6b84ab;margin-top:8px}

.history-btn{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:var(--slate-light);background:none;border:none;cursor:pointer;padding:3px 0;transition:color .15s}
.history-btn:hover{color:var(--slate-mid)}
.hchevron{font-size:10px;transition:transform .2s;display:inline-block}

.history-timeline{display:none;padding:16px 24px 20px 24px;background:#fafbfc;border-top:1px solid var(--border)}
.history-timeline.open{display:block}
.timeline-wrap{position:relative;padding-left:20px}
.timeline-wrap::before{content:'';position:absolute;left:6px;top:8px;bottom:8px;width:2px;background:linear-gradient(to bottom, #e2e8f0, #cbd5e1);border-radius:2px}
.timeline-item{position:relative;padding:0 0 20px 20px}
.timeline-item:last-child{padding-bottom:0}
.timeline-item::before{content:'';position:absolute;left:-14px;top:6px;width:10px;height:10px;border-radius:50%;background:#fff;border:2px solid #cbd5e1;z-index:1}
.timeline-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm);padding:10px 14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.timeline-name{font-size:13px;font-weight:600;color:#94a3b8;flex:1;text-decoration:line-through;text-decoration-color:#cbd5e1}
.timeline-meta{display:flex;flex-direction:column;align-items:flex-end;gap:3px;flex-shrink:0}
.timeline-date{font-size:11px;color:#c4c9d4}
.timeline-badge{display:inline-flex;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;letter-spacing:.3px;background:#f1f5f9;color:#94a3b8;border:1px solid #e2e8f0}
.timeline-header{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.timeline-header::after{content:'';flex:1;height:1px;background:var(--border)}

.pic-meta{padding:12px 24px;background:#fafbfc;border-top:1px solid var(--border);font-size:13px;color:var(--slate-mid)}

@media(max-width:640px){
  .info-table td.label{width:130px}
  .active-row{flex-direction:column;align-items:flex-start;gap:10px}
  .timeline-card{flex-direction:column;align-items:flex-start}
  .timeline-meta{align-items:flex-start}
}
</style>
</head>
<body>
	
<div class="page-wrap">

  <a href="project_register_list.php?project_id=<?= $project_id ?>" class="back-btn">← Back</a>

  <div class="page-header">
    <div>
      <div class="page-header-name"><?= htmlspecialchars($project['project_name'] ?? '-') ?></div>
    </div>
  </div>

  <?php if(isset($_GET['msg'])): ?>
  <div class="notice notice-success">
    ✅
    <?php
    if($_GET['msg'] === 'replaced') echo 'Entry replaced successfully.';
    elseif($_GET['msg'] === 'added') echo 'Name added successfully.';
    ?>
  </div>
  <?php endif; ?>

  <!-- Project Register Info -->
  <div class="card">
    <div class="card-header" onclick="toggleCard(this)">
      <div class="card-title">Project Register</div>
      <span class="card-chevron">▼</span>
    </div>
    <div class="card-collapsible">
      <table class="info-table">
        <tr><td class="label">Company</td><td><?= htmlspecialchars($base['company'] ?? '-') ?></td></tr>
        <tr><td class="label">Title</td><td><?= htmlspecialchars($base['title'] ?? '-') ?></td></tr>
        <tr><td class="label">No. Contract</td><td><?= htmlspecialchars($base['contract_no'] ?? '-') ?></td></tr>
        <tr><td class="label">Site Commenced On</td><td><?= !empty($base['site_start']) ? date('d/m/Y', strtotime($base['site_start'])) : '-' ?></td></tr>
        <tr><td class="label">Site Completed On</td><td><?= !empty($base['site_end']) ? date('d/m/Y', strtotime($base['site_end'])) : '-' ?></td></tr>
        <tr><td class="label">Construction Period</td><td><?= htmlspecialchars($base['construction_period'] ?? '-') ?></td></tr>
        <tr><td class="label">Contract Sum</td><td><?= !empty($base['contract_sum']) ? 'RM ' . number_format($base['contract_sum'], 2) : '-' ?></td></tr>
        <tr>
          <td class="label">SST Document</td>
          <td>
            <?php if(!empty($base['sst_document'])): ?>
              <a href="download_file.php?file=<?= urlencode(basename($base['sst_document'])) ?>" target="_blank"
                 style="color:#007bff;font-weight:600;text-decoration:none;">📄 View SST Document</a>
            <?php else: ?>
              <span style="color:var(--slate-light);font-style:italic;">No document uploaded</span>
            <?php endif; ?>
          </td>
        </tr>
      </table>
    </div>
  </div>

  <!-- Person In Charge -->
  <div class="pic-card">
    <div class="pic-card-header">
      <div class="pic-card-title">Person In Charge</div>
    </div>

    <?php
    function render_field_section($label, $field_key, $current, $hist_entries, $register_id, $project_id, $is_admin, $is_multi = false) {
        $hist_id    = 'hist-'    . $field_key . '-' . $register_id;
        $add_id     = 'add-'     . $field_key . '-' . $register_id;
        $replace_id = 'replace-' . $field_key . '-' . $register_id;
        $has_hist   = !empty($hist_entries);
        $has_value  = (isset($current['value']) && $current['value'] !== '');
        $hist_display = array_reverse($hist_entries);
    ?>
    <div class="field-section">

      <!-- Field header -->
      <div class="field-header">
        <div class="field-label"><?= htmlspecialchars($label) ?></div>
        <?php if ($has_hist): ?>
          <button class="history-btn" onclick="toggleHistory('<?= $hist_id ?>', this)">
             <?= count($hist_entries) ?> previous
            <span class="hchevron">▼</span>
          </button>
        <?php endif; ?>
      </div>

      <!-- Current active entry -->
      <div class="active-row">
        <?php if ($has_value): ?>
          <div class="active-name"><?= htmlspecialchars($current['value']) ?></div>
          <span class="badge-active">● Active</span>
          <?php if ($is_admin): ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
              <?php if ($is_multi): ?>
                <button type="button" class="btn btn-sm btn-primary" onclick="toggleAdd('<?= $add_id ?>')">
                  Add Name
                </button>
              <?php endif; ?>
              <button type="button" class="btn btn-sm btn-danger" onclick="toggleReplace('<?= $replace_id ?>')">
                Deactivate
              </button>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="active-name" style="color:var(--slate-light);font-style:italic;font-weight:400">
            Not assigned
          </div>
        <?php endif; ?>
      </div>

      <!-- Add form -->
      <?php if ($is_admin && $is_multi): ?>
      <div class="replace-box" id="<?= $add_id ?>">
        <form method="POST">
          <input type="hidden" name="action" value="add_name">
          <input type="hidden" name="field_key" value="<?= htmlspecialchars($field_key) ?>">
          <input type="hidden" name="row_id" value="<?= intval($register_id) ?>">
          <input type="hidden" name="project_id" value="<?= intval($project_id) ?>">
          <div class="replace-box-inner">
            <input type="text" name="name" class="replace-input" placeholder="Enter name to add...">
            <button type="submit" class="btn btn-sm btn-primary">Confirm</button>
            <button type="button" class="btn btn-sm btn-ghost" onclick="toggleAdd('<?= $add_id ?>')">Cancel</button>
          </div>
          <div class="replace-hint">New name will be added to the existing list.</div>
        </form>
      </div>
      <?php endif; ?>

      <!-- Replace / Deactivate form -->
      <?php if ($is_admin && $has_value): ?>
      <div class="replace-box" id="<?= $replace_id ?>">
        <form method="POST">
          <input type="hidden" name="action" value="deactivate_and_replace">
          <input type="hidden" name="field_key" value="<?= htmlspecialchars($field_key) ?>">
          <input type="hidden" name="row_id" value="<?= intval($register_id) ?>">
          <input type="hidden" name="project_id" value="<?= intval($project_id) ?>">
          <div class="replace-box-inner">
            <input type="text"
                   name="name"
                   class="replace-input"
                   placeholder="<?= $is_multi ? 'Enter new comma-separated names...' : 'Enter new name...' ?>"
                   value="<?= htmlspecialchars($current['value']) ?>">
            <button type="submit" class="btn btn-sm btn-primary">Confirm</button>
            <button type="button" class="btn btn-sm btn-ghost" onclick="toggleReplace('<?= $replace_id ?>')">Cancel</button>
          </div>
          <div class="replace-hint">Current value will be archived to history.</div>
        </form>
      </div>
      <?php endif; ?>

      <!-- History timeline -->
      <?php if ($has_hist): ?>
      <div class="history-timeline" id="<?= $hist_id ?>">
        <div class="timeline-header">Change History</div>
        <div class="timeline-wrap">
          <?php foreach ($hist_display as $h): ?>
          <div class="timeline-item">
            <div class="timeline-card">
              <div class="timeline-name"><?= htmlspecialchars($h['value'] ?: '—') ?></div>
              <div class="timeline-meta">
                <?php if (!empty($h['deactivated_at'])): ?>
                  <div class="timeline-date">Deactivated <?= date('d/m/Y', strtotime($h['deactivated_at'])) ?></div>
                <?php endif; ?>
                <span class="timeline-badge">INACTIVE</span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
    <?php } ?>

    <?php render_field_section('Project Manager',   'pm',         $current_fields['pm'],         $history['pm'],         $register_id, $project_id, $is_admin, false); ?>
    <?php render_field_section('Project Engineer',  'pe',         $current_fields['pe'],         $history['pe'],         $register_id, $project_id, $is_admin, false); ?>
    <?php render_field_section('Contract Manager',  'cm',         $current_fields['cm'],         $history['cm'],         $register_id, $project_id, $is_admin, false); ?>
    <?php render_field_section('Resident Engineer', 're',         $current_fields['re'],         $history['re'],         $register_id, $project_id, $is_admin, false); ?>
    <?php render_field_section('Sub Contractor(s)', 'subcon',     $current_fields['subcon'],     $history['subcon'],     $register_id, $project_id, $is_admin, true); ?>
    <?php render_field_section('Consultant(s)',      'consultant', $current_fields['consultant'], $history['consultant'], $register_id, $project_id, $is_admin, true); ?>

    <div class="pic-meta">
      Added on <?= !empty($pic_row['created_at']) ? date('d/m/Y', strtotime($pic_row['created_at'])) : '-' ?>
      <?php if(!empty($pic_row['created_by_name'])): ?>
        &nbsp;·&nbsp; <?= htmlspecialchars($pic_row['created_by_name']) ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
function toggleCard(header) {
  const body = header.nextElementSibling;
  header.classList.toggle('collapsed');
  body.style.display = header.classList.contains('collapsed') ? 'none' : 'block';
}
function toggleReplace(id) {
  const box = document.getElementById(id);
  box.classList.toggle('open');
  if (box.classList.contains('open')) box.querySelector('input[name="name"]').focus();
}
function toggleAdd(id) {
  const box = document.getElementById(id);
  box.classList.toggle('open');
  if (box.classList.contains('open')) box.querySelector('input[name="name"]').focus();
}
function toggleHistory(id, btn) {
  const list = document.getElementById(id);
  list.classList.toggle('open');
  btn.querySelector('.hchevron').style.transform = list.classList.contains('open') ? 'rotate(180deg)' : '';
}
</script>
</body>
</html>
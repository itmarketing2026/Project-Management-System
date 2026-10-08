<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

$title = "Interim Claim";

require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$project_id    = intval($_GET['project_id'] ?? 0);
$id            = intval($_GET['id'] ?? 0);
$user_fullname = $_SESSION['full_name'] ?? '';

if (!$project_id) die("Invalid Project.");

if (!user_can_access_project($db, $_SESSION['user_id'], $project_id, $_SESSION['account_type'] ?? '')) {
    die("You do not have permission to view this project.");
}

$error  = '';
$flash  = '';
$record = null;

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM interim_claim WHERE id = ? AND project_id = ?");
    $stmt->bind_param("ii", $id, $project_id);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$record) die("Record not found.");
}

$role = $_SESSION['account_type'] ?? '';
$can_delete = in_array($role, ['Client Admin', 'Admin (QA/QC)']);

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!$can_delete) {
        die("Unauthorized.");
    }
    $del_id = intval($_POST['delete_id']);
    $stmt = $db->prepare("DELETE FROM interim_claim WHERE id = ? AND project_id = ?");
    $stmt->bind_param("ii", $del_id, $project_id);
    $stmt->execute();
    $stmt->close();
    header("Location: interim_claim_list.php?project_id={$project_id}&msg=deleted");
    exit;
}

// Subcontractor list
$subcon_list = [];
$stmt = $db->prepare("SELECT id, subcon_name FROM loa WHERE project_id = ? ORDER BY subcon_name ASC");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $subcon_list[] = $row;
}
$stmt->close();

function upload_ic_file($file_key, $prefix, $uploadDir, $allowed) {
    if (empty($_FILES[$file_key]['name'][0])) return null;
    $paths   = [];
    $maxSize = 160 * 1024 * 1024;
    foreach ($_FILES[$file_key]['name'] as $k => $name) {
        if ($_FILES[$file_key]['error'][$k] !== UPLOAD_ERR_OK) continue;
        if ($_FILES[$file_key]['size'][$k] > $maxSize) continue;
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) continue;
        $safeName = preg_replace("/[^a-zA-Z0-9]/", "_", pathinfo($name, PATHINFO_FILENAME));
        $safeName = substr($safeName, 0, 50);
        $newName  = $prefix . '_' . $safeName . '_' . date('d-m-Y') . '.' . $ext;
        if (move_uploaded_file($_FILES[$file_key]['tmp_name'][$k], $uploadDir . $newName)) {
            $paths[] = 'uploads/interim_claim/' . $newName;
        }
    }
    return !empty($paths) ? implode(',', $paths) : null;
}

function replace_ic_file($new, $existing, $uploadDir) {
    if (!$new) {
        return $existing ?? null;
    }

    if (!empty($existing)) {
        foreach (explode(',', $existing) as $old_path) {
            $old_path = trim($old_path);
            if ($old_path === '') continue;

            $full_path = __DIR__ . '/' . $old_path;
            if (file_exists($full_path)) {
                unlink($full_path);
            }
        }
    }

    return $new;
}

function clean_date($value) {
    $value = trim($value ?? '');
    if ($value === '') return null;
    // Must match YYYY-MM-DD and be a real date
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
    $parts = explode('-', $value);
    if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) return null;
    return $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Subcontractor
    $loa_id       = intval($_POST['loa_id'] ?? 0);
	$ipc_number   = trim($_POST['ipc_number'] ?? '');
    $a_amount     = $_POST['a_amount'] !== '' ? floatval($_POST['a_amount']) : null;
    $a_date 	  = clean_date($_POST['a_date'] ?? '');
    $b_total_work = $_POST['b_total_work'] !== '' ? floatval($_POST['b_total_work']) : null;
    $b_retention  = $_POST['b_retention'] !== '' ? floatval($_POST['b_retention']) : null;
    $b_certified  = $_POST['b_certified'] !== '' ? floatval($_POST['b_certified']) : null;
    $b_date       = clean_date($_POST['b_date'] ?? '');
    $c_amount     = $_POST['c_amount'] !== '' ? floatval($_POST['c_amount']) : null;
    $c_date       = clean_date($_POST['c_date'] ?? '');
    $d_status     = trim($_POST['d_status'] ?? 'non-applicable');
    $d_amount     = $_POST['d_amount'] !== '' ? floatval($_POST['d_amount']) : null;
    $d_date       = clean_date($_POST['d_date'] ?? '');
    $e_status     = trim($_POST['e_status'] ?? 'non-applicable');
    $e_amount     = $_POST['e_amount'] !== '' ? floatval($_POST['e_amount']) : null;
    $e_date       = clean_date($_POST['e_date'] ?? '');


    if (!$loa_id) {
        $error = "Subcontractor is required.";
    } else {

        $uploadDir = __DIR__ . '/uploads/interim_claim/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $allowed = ['pdf','jpg','jpeg','png','webp','doc','docx'];

        // Subcontractor files
		$b_file = replace_ic_file(upload_ic_file('b_file', 'B', $uploadDir, $allowed), $record['b_file'] ?? null, $uploadDir);
		$c_file = replace_ic_file(upload_ic_file('c_file', 'C', $uploadDir, $allowed), $record['c_file'] ?? null, $uploadDir);
		$d_file = replace_ic_file(upload_ic_file('d_file', 'D', $uploadDir, $allowed), $record['d_file'] ?? null, $uploadDir);
		$e_file = replace_ic_file(upload_ic_file('e_file', 'E', $uploadDir, $allowed), $record['e_file'] ?? null, $uploadDir);

        if ($id) {
			$stmt = $db->prepare("
				UPDATE interim_claim SET
				ipc_number=?,
				loa_id=?, a_amount=?, a_date=?,
				b_total_work=?, b_retention=?, b_certified=?, b_date=?, b_file=?,
				c_amount=?, c_date=?, c_file=?,
				d_status=?, d_amount=?, d_date=?, d_file=?,
				e_status=?, e_amount=?, e_date=?, e_file=?
				WHERE id=? AND project_id=?
			");
			$stmt->bind_param(
				"sidsdddssdsssdsssdssii",
				$ipc_number,
				$loa_id, $a_amount, $a_date,
				$b_total_work, $b_retention, $b_certified, $b_date, $b_file,
				$c_amount, $c_date, $c_file,
				$d_status, $d_amount, $d_date, $d_file,
				$e_status, $e_amount, $e_date, $e_file,
				$id, $project_id
			);
		} else {
			$stmt = $db->prepare("
				INSERT INTO interim_claim
				(project_id, ipc_number,
				 loa_id, a_amount, a_date,
				 b_total_work, b_retention, b_certified, b_date, b_file,
				 c_amount, c_date, c_file,
				 d_status, d_amount, d_date, d_file,
				 e_status, e_amount, e_date, e_file,
				 created_by, created_at)
				VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
			");
			$stmt->bind_param(
				"isidsdddssdsssdsssdsss",
				$project_id, $ipc_number,
				$loa_id, $a_amount, $a_date,
				$b_total_work, $b_retention, $b_certified, $b_date, $b_file,
				$c_amount, $c_date, $c_file,
				$d_status, $d_amount, $d_date, $d_file,
				$e_status, $e_amount, $e_date, $e_file,
				$user_fullname
			);
		}

				if ($stmt->execute()) {
					$new_id = $id ?: $stmt->insert_id;
					$stmt->close();
					header("Location: interim_claim_list.php?project_id={$project_id}&msg=saved");
					exit;
				}

				$error = "DB Error: " . $stmt->error;
				error_log("[interim_claim] DB error for user {$user_fullname}, project {$project_id}: " . $stmt->error);
				$stmt->close();
			}
		}

$flash = ($_GET['msg'] ?? '') === 'saved' ? '✅ Interim Claim saved successfully.' : '';

function file_cards($files_str) {
    if (empty($files_str)) return;
    foreach (explode(',', $files_str) as $file) {
        if (!$file) continue;
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $icon = $ext === 'pdf' ? '📕' : (in_array($ext, ['jpg','jpeg','png']) ? '🖼️' : (in_array($ext, ['doc','docx']) ? '📘' : '📄'));
        echo '<a href="' . htmlspecialchars($file) . '" target="_blank" class="file-card">';
        echo '<div class="file-icon">' . $icon . '</div>';
        echo '<div class="file-label">' . htmlspecialchars(basename($file)) . '</div>';
        echo '</a>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Project Management System</title>
<style>
:root { --accent: #007bff; --max: 850px; }
* { box-sizing: border-box; }
body { margin: 0; padding: 30px; background: #f3f4f6; font-family: Arial, sans-serif; }
.container { width: 100%; max-width: var(--max); margin: 0 auto; }
h1 { color: var(--accent); text-align: center; font-size: 42px; margin-bottom: 30px; font-weight: 800; }
.back-btn { display: inline-block; margin-bottom: 20px; text-decoration: none; background: var(--accent); color: #fff; padding: 10px 18px; border-radius: 10px; font-weight: bold; }
.back-btn:hover { background: #0056b3; }
.tabs { display: flex; gap: 0; margin-bottom: 24px; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.tab-btn { flex: 1; padding: 14px; font-size: 16px; font-weight: 700; border: none; cursor: pointer; background: #e2e8f0; color: #64748b; transition: 0.2s; }
.tab-btn.active { background: var(--accent); color: #fff; }
.tab-btn:first-child { border-radius: 14px 0 0 14px; }
.tab-btn:last-child { border-radius: 0 14px 14px 0; }
.tab-content { display: none; }
.tab-content.active { display: block; }
.card { background: #fff; border: 3px solid var(--accent); border-radius: 24px; padding: 30px; box-shadow: 0 5px 14px rgba(0,0,0,0.05); margin-bottom: 24px; }
.card h2 { color: var(--accent); font-size: 20px; font-weight: 800; margin-top: 0; margin-bottom: 22px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0; }
.form-group { margin-bottom: 20px; }
label { display: block; margin-bottom: 8px; font-weight: 700; color: #333; }
.input, select { width: 100%; padding: 14px; border: 2px solid #007bff; border-radius: 12px; font-size: 15px; font-family: Arial, sans-serif; color: #1f2937; background: #fff; transition: border-color .15s; }
.input:focus, select:focus { outline: none; box-shadow: 0 0 0 3px rgba(0,123,255,.1); }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 18px; }
.file-row { display: flex; align-items: center; gap: 10px; border: 2px dashed #93c5fd; border-radius: 12px; padding: 12px 16px; background: #f0f7ff; }
.file-row input[type=file] { flex: 1; border: none; background: transparent; font-size: 13px; padding: 0; cursor: pointer; }
.file-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; margin-bottom: 12px; }
.file-card { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 130px; border: 1.5px solid #e2e8f0; border-radius: 12px; background: #f8fafc; text-decoration: none; transition: 0.2s; padding: 12px; }
.file-card:hover { background: #e0f2fe; border-color: #38bdf8; }
.file-icon { font-size: 36px; margin-bottom: 8px; }
.file-label { font-size: 11px; text-align: center; word-break: break-word; color: #374151; }
.btn { width: 100%; background: var(--accent); color: #fff; border: none; padding: 16px; border-radius: 14px; font-size: 18px; font-weight: 700; cursor: pointer; transition: .2s; }
.btn:hover { background: #0056b3; }
.notice { padding: 13px 16px; border-radius: 10px; font-weight: 700; font-size: 14px; margin-bottom: 20px; }
.notice-success { background: #dcfce7; border: 1px solid #86efac; color: #15803d; }
.notice-error   { background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; }
.ipc-card { background: #fff; border: 3px solid var(--accent); border-radius: 24px; padding: 20px 30px; box-shadow: 0 5px 14px rgba(0,0,0,0.05); margin-bottom: 24px; display: flex; align-items: center; gap: 16px; }
.ipc-card label { font-weight: 700; color: #333; white-space: nowrap; margin: 0; }
.ipc-card input { flex: 1; padding: 12px 14px; border: 2px solid #007bff; border-radius: 10px; font-size: 15px; }
.switch-wrap { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; }
.switch { position: relative; width: 60px; height: 30px; flex-shrink: 0; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #dc2626; border-radius: 30px; transition: 0.3s; width: 60px; padding: 0; border: none; }
.slider:before { position: absolute; content: ""; height: 22px; width: 22px; left: 4px; bottom: 4px; background: white; border-radius: 50%; transition: 0.3s; }
input:checked + .slider { background: #16a34a; }
input:checked + .slider:before { transform: translateX(30px); }
.switch-label { font-weight: 700; font-size: 15px; color: #374151; }
.switch-status { font-size: 13px; font-weight: 700; color: #dc2626; }
.applicable-fields { display: none; }
.applicable-fields.show { display: block; }
@media (max-width: 640px) {
    .grid-2, .grid-3 { grid-template-columns: 1fr; }
    body { padding: 15px; }
    h1 { font-size: 32px; }
    .card { padding: 22px; }
    .ipc-card { flex-direction: column; align-items: stretch; }
}
</style>
</head>
<body>

<div class="container">

    <a href="interim_claim_list.php?project_id=<?= $project_id ?>" class="back-btn">← Back</a>
    <h1><?= $id ? 'Edit Interim Claim' : 'Add Interim Claim' ?></h1>

    <?php if ($flash): ?>
        <div class="notice notice-success"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice notice-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <!-- SUBCONTRACTOR TAB -->
        <div class="tab-content active" id="tab_sub">

            <div class="card">
				<h2>General Information</h2>
				<div class="grid-2">
					<div class="form-group">
						<label>Subcontractor Name <span style="color:red">*</span></label>
						<select name="loa_id" required>
							<option value="">-- Select Subcontractor --</option>
							<?php foreach ($subcon_list as $loa): ?>
								<option value="<?= $loa['id'] ?>" <?= ($record['loa_id'] ?? '') == $loa['id'] ? 'selected' : '' ?>>
									<?= htmlspecialchars($loa['subcon_name']) ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="form-group">
						<label>IPC Number</label>
						<input type="text" class="input" name="ipc_number" id="ipc_number_sub"
							placeholder="Enter IPC number"
							value="<?= htmlspecialchars($record['ipc_number'] ?? '') ?>"
							oninput="syncIpc(this.value)">
					</div>
				</div>
			</div>

            <div class="card">
                <h2>A. Submission of Payment Certificate (Subcontractor)</h2>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Amount (RM)</label>
                        <input type="number" name="a_amount" class="input" step="0.01" placeholder="Enter Amount"
                            value="<?= htmlspecialchars($record['a_amount'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="a_date" class="input"
                            value="<?= htmlspecialchars($record['a_date'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>B. Certified Certificate Subcontractor</h2>
                <div class="grid-3">
                    <div class="form-group">
                        <label>Total Work Done (RM)</label>
                        <input type="number" name="b_total_work" class="input" step="0.01" placeholder="0.00"
                            value="<?= htmlspecialchars($record['b_total_work'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Retention Amount (RM)</label>
                        <input type="number" name="b_retention" class="input" step="0.01" placeholder="0.00"
                            value="<?= htmlspecialchars($record['b_retention'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Certified Amount (RM)</label>
                        <input type="number" name="b_certified" class="input" step="0.01" placeholder="0.00"
                            value="<?= htmlspecialchars($record['b_certified'] ?? '') ?>">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="b_date" class="input"
                            value="<?= htmlspecialchars($record['b_date'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Attachment</label>
                    <?php if (!empty($record['b_file'])): ?>
                        <div class="file-grid"><?php file_cards($record['b_file']); ?></div>
                        <p style="font-size:13px; color:#6b7280; margin:0 0 8px;">Upload a new file to replace the one above:</p>
                    <?php endif; ?>
                    <div class="file-row">
                        <input type="file" name="b_file[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
                    </div>
                    <small style="color:#666; margin-top:6px; display:block;">PDF, JPG, PNG, DOC accepted · Max 250MB</small>
                </div>
            </div>

            <div class="card">
                <h2>C. Payment Release</h2>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Amount (RM)</label>
                        <input type="number" name="c_amount" class="input" step="0.01" placeholder="Enter Amount"
                            value="<?= htmlspecialchars($record['c_amount'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="c_date" class="input"
                            value="<?= htmlspecialchars($record['c_date'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Attachment</label>
                    <?php if (!empty($record['c_file'])): ?>
                        <div class="file-grid"><?php file_cards($record['c_file']); ?></div>
                        <p style="font-size:13px; color:#6b7280; margin:0 0 8px;">Upload a new file to replace the one above:</p>
                    <?php endif; ?>
                    <div class="file-row">
                        <input type="file" name="c_file[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
                    </div>
                    <small style="color:#666; margin-top:6px; display:block;">PDF, JPG, PNG, DOC accepted · Max 250MB</small>
                </div>
            </div>

            <div class="card">
                <h2>D. Advance Payment to Subcontractor</h2>
                <input type="hidden" name="d_status" id="d_status_input" value="<?= htmlspecialchars($record['d_status'] ?? 'non-applicable') ?>">
                <div class="switch-wrap">
                    <label class="switch">
                        <input type="checkbox" id="d_toggle"
                            <?= ($record['d_status'] ?? 'non-applicable') === 'applicable' ? 'checked' : '' ?>
                            onchange="handleToggle('d', this.checked)">
                        <span class="slider"></span>
                    </label>
                    <span class="switch-label">Status: </span>
                    <span id="d_switch_label" class="switch-status" style="color:<?= ($record['d_status'] ?? '') === 'applicable' ? '#16a34a' : '#dc2626' ?>">
                        <?= ($record['d_status'] ?? 'non-applicable') === 'applicable' ? 'Applicable' : 'Non-Applicable' ?>
                    </span>
                </div>
                <div class="applicable-fields <?= ($record['d_status'] ?? 'non-applicable') === 'applicable' ? 'show' : '' ?>" id="d_fields">
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Amount (RM)</label>
                            <input type="number" name="d_amount" class="input" step="0.01" placeholder="Enter Amount"
                                value="<?= htmlspecialchars($record['d_amount'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="d_date" class="input"
                                value="<?= htmlspecialchars($record['d_date'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Attachment</label>
                        <?php if (!empty($record['d_file'])): ?>
                            <div class="file-grid"><?php file_cards($record['d_file']); ?></div>
                            <p style="font-size:13px; color:#6b7280; margin:0 0 8px;">Upload a new file to replace the one above:</p>
                        <?php endif; ?>
                        <div class="file-row">
                            <input type="file" name="d_file[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
                        </div>
                        <small style="color:#666; margin-top:6px; display:block;">PDF, JPG, PNG, DOC accepted · Max 250MB</small>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>E. Back Charge to Subcontractor</h2>
                <input type="hidden" name="e_status" id="e_status_input" value="<?= htmlspecialchars($record['e_status'] ?? 'non-applicable') ?>">
                <div class="switch-wrap">
                    <label class="switch">
                        <input type="checkbox" id="e_toggle"
                            <?= ($record['e_status'] ?? 'non-applicable') === 'applicable' ? 'checked' : '' ?>
                            onchange="handleToggle('e', this.checked)">
                        <span class="slider"></span>
                    </label>
                    <span class="switch-label">Status: </span>
                    <span id="e_switch_label" class="switch-status" style="color:<?= ($record['e_status'] ?? '') === 'applicable' ? '#16a34a' : '#dc2626' ?>">
                        <?= ($record['e_status'] ?? 'non-applicable') === 'applicable' ? 'Applicable' : 'Non-Applicable' ?>
                    </span>
                </div>
                <div class="applicable-fields <?= ($record['e_status'] ?? 'non-applicable') === 'applicable' ? 'show' : '' ?>" id="e_fields">
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Amount (RM)</label>
                            <input type="number" name="e_amount" class="input" step="0.01" placeholder="Enter Amount"
                                value="<?= htmlspecialchars($record['e_amount'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="e_date" class="input"
                                value="<?= htmlspecialchars($record['e_date'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Attachment</label>
                        <?php if (!empty($record['e_file'])): ?>
                            <div class="file-grid"><?php file_cards($record['e_file']); ?></div>
                            <p style="font-size:13px; color:#6b7280; margin:0 0 8px;">Upload a new file to replace the one above:</p>
                        <?php endif; ?>
                        <div class="file-row">
                            <input type="file" name="e_file[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
                        </div>
                        <small style="color:#666; margin-top:6px; display:block;">PDF, JPG, PNG, DOC accepted · Max 250MB</small>
                    </div>
                </div>
            </div>

        </div>

        <button type="submit" class="btn">
            💾 <?= $id ? 'Update Interim Claim' : 'Save Interim Claim' ?>
        </button>

    </form>

</div>

<script>
function switchTab(tab, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab_' + tab).classList.add('active');
    btn.classList.add('active');
}

function handleToggle(section, isChecked) {
    const input  = document.getElementById(section + '_status_input');
    const fields = document.getElementById(section + '_fields');
    const label  = document.getElementById(section + '_switch_label');
    if (isChecked) {
        input.value = 'applicable';
        fields.classList.add('show');
        label.textContent = 'Applicable';
        label.style.color = '#16a34a';
    } else {
        input.value = 'non-applicable';
        fields.classList.remove('show');
        label.textContent = 'Non-Applicable';
        label.style.color = '#dc2626';
    }
}
</script>

</body>
</html>
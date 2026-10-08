<?php
session_start();
$title = "Add Subcontractor BQ";
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$project_id = intval($_GET['project_id'] ?? 0);
if (!$project_id) die("Invalid Project.");

if (!user_can_access_project($db, $_SESSION['user_id'], $project_id, $_SESSION['account_type'] ?? '')) {
    die("You do not have permission to view this project.");
}

$bq_id  = intval($_GET['bq_id'] ?? 0);
$is_edit = false;

/* Fetch contract sum from project_register */
$stmt_cs = $db->prepare("SELECT contract_sum FROM project_register WHERE project_id = ? ORDER BY id ASC LIMIT 1");
$stmt_cs->bind_param("i", $project_id);
$stmt_cs->execute();
$contract_sum = (float)($stmt_cs->get_result()->fetch_assoc()['contract_sum'] ?? 0);
$stmt_cs->close();

/* Defaults */
$name = ''; $bq_date = ''; $amount = '';
$document = '';
$vo_status  = 'non-applicable';
$phk_status = 'non-applicable';
$vo_rows  = [];
$phk_rows = [];

if ($bq_id > 0) {
    $stmt = $db->prepare("SELECT * FROM bq_subcontractor WHERE id = ? AND project_id = ?");
    $stmt->bind_param("ii", $bq_id, $project_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $is_edit    = true;
        $name       = $row['name'];
        $bq_date    = $row['bq_date'];
        $amount     = $row['amount'];
		$document   = $row['document'] ?? '';
        $vo_status  = $row['vo_status'] ?? 'non-applicable';
        $phk_status = $row['phk_status'] ?? 'non-applicable';

        $stmt_vo = $db->prepare("SELECT * FROM bq_subcontractor_vo WHERE bq_subcontractor_id = ? ORDER BY id ASC");
        $stmt_vo->bind_param("i", $bq_id);
        $stmt_vo->execute();
        $vo_rows = $stmt_vo->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_vo->close();

        $stmt_phk = $db->prepare("SELECT * FROM bq_subcontractor_phk WHERE bq_subcontractor_id = ? ORDER BY id ASC");
        $stmt_phk->bind_param("i", $bq_id);
        $stmt_phk->execute();
        $phk_rows = $stmt_phk->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_phk->close();
    }
}

if (empty($vo_rows))  $vo_rows  = [['vo_date'=>'','vo_amount'=>'','vo_type'=>'','vo_document'=>'','new_award_amount'=>$contract_sum]];
if (empty($phk_rows)) $phk_rows = [['phk_date'=>'','phk_amount'=>'']];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        die("Invalid request. Please try again.");
    }

    $name    = trim($_POST['name'] ?? '');
    $bq_date = $_POST['bq_date'] ?: null;
    $raw_amt = str_replace(',', '', $_POST['amount'] ?? '');
    $amount  = (trim($raw_amt) !== '') ? floatval($raw_amt) : 0;

    $vo_status  = trim($_POST['vo_status'] ?? 'non-applicable');
    $phk_status = trim($_POST['phk_status'] ?? 'non-applicable');

    if (!$name) {
        $error = "Subcontractor / BQ name is required.";
    } else {

        if ($is_edit) {
            $stmt = $db->prepare("UPDATE bq_subcontractor SET name=?, bq_date=?, amount=?, vo_status=?, phk_status=? WHERE id=? AND project_id=?");
            $stmt->bind_param("ssdssii", $name, $bq_date, $amount, $vo_status, $phk_status, $bq_id, $project_id);
            $stmt->execute();
            $stmt->close();
            $current_bq_id = $bq_id;

            $del1 = $db->prepare("DELETE FROM bq_subcontractor_vo WHERE bq_subcontractor_id = ?");
            $del1->bind_param("i", $current_bq_id);
            $del1->execute();
            $del1->close();

            $del2 = $db->prepare("DELETE FROM bq_subcontractor_phk WHERE bq_subcontractor_id = ?");
            $del2->bind_param("i", $current_bq_id);
            $del2->execute();
            $del2->close();

        } else {
            $stmt = $db->prepare("INSERT INTO bq_subcontractor (project_id, name, bq_date, amount, vo_status, phk_status, created_by, created_at) VALUES (?,?,?,?,?,?,?,NOW())");
            $stmt->bind_param("issdssi", $project_id, $name, $bq_date, $amount, $vo_status, $phk_status, $_SESSION['user_id']);
            $stmt->execute();
            $current_bq_id = $stmt->insert_id;
            $stmt->close();
        }

        /* VO ROWS */
        if ($vo_status === 'applicable') {
            $vo_dates     = $_POST['vo_date']    ?? [];
            $vo_amounts   = $_POST['vo_amount']  ?? [];
            $vo_types     = $_POST['vo_type']    ?? [];
            $vo_existing  = $_POST['vo_document_existing'] ?? [];
            $vo_doc_files = $_FILES['vo_document'] ?? null;

            $upload_dir = __DIR__ . "/uploads/project_{$project_id}/bq/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $allowed = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png'];

            foreach ($vo_dates as $i => $vd) {
                $v_date   = $vd ?: null;
                $raw_va   = str_replace(',', '', $vo_amounts[$i] ?? '');
                $v_amount = (trim($raw_va) !== '') ? floatval($raw_va) : null;
                $v_type   = trim($vo_types[$i] ?? '');

                if (!$v_date && $v_amount === null && !$v_type) continue;

                /* New Award = contract_sum +/- this VO's amount */
                $v_new_award = $contract_sum;
                if ($v_type === 'addition' && $v_amount !== null)      $v_new_award = $contract_sum + $v_amount;
                elseif ($v_type === 'subtraction' && $v_amount !== null) $v_new_award = $contract_sum - $v_amount;

                // Keep the existing file unless a new one is uploaded
                $v_document = trim($vo_existing[$i] ?? '') ?: null;

                if ($vo_doc_files && isset($vo_doc_files['error'][$i]) && $vo_doc_files['error'][$i] === UPLOAD_ERR_OK) {
                    $original = basename($vo_doc_files['name'][$i]);
                    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
                    if (in_array($ext, $allowed)) {
                        $clean_name = preg_replace("/[^a-zA-Z0-9._-]/", "_", $original);
                        $new_file = "vo_" . time() . "_{$i}_" . $clean_name;
                        if (move_uploaded_file($vo_doc_files['tmp_name'][$i], $upload_dir . $new_file)) {
                            if (!empty($v_document)) {
                                $old_path = $upload_dir . $v_document;
                                if (file_exists($old_path)) unlink($old_path);
                            }
                            $v_document = $new_file;
                        }
                    }
                }

                $ins = $db->prepare("INSERT INTO bq_subcontractor_vo (bq_subcontractor_id, vo_date, vo_amount, vo_type, vo_document, new_award_amount) VALUES (?,?,?,?,?,?)");
                $ins->bind_param("isdssd", $current_bq_id, $v_date, $v_amount, $v_type, $v_document, $v_new_award);
                $ins->execute();
                $ins->close();
            }
        }

        /* PHK ROWS */
        if ($phk_status === 'applicable') {
            $phk_dates   = $_POST['phk_date']   ?? [];
            $phk_amounts = $_POST['phk_amount'] ?? [];

            foreach ($phk_dates as $i => $pd) {
                $p_date   = $pd ?: null;
                $raw_pa   = str_replace(',', '', $phk_amounts[$i] ?? '');
                $p_amount = (trim($raw_pa) !== '') ? floatval($raw_pa) : null;

                if (!$p_date && $p_amount === null) continue;

                $ins = $db->prepare("INSERT INTO bq_subcontractor_phk (bq_subcontractor_id, phk_date, phk_amount) VALUES (?,?,?)");
                $ins->bind_param("isd", $current_bq_id, $p_date, $p_amount);
                $ins->execute();
                $ins->close();
            }
        }

        header("Location: bq_list.php?project_id={$project_id}&msg=saved");
        exit;
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
:root { --accent: #007bff; --max: 980px; }
* { box-sizing: border-box; }
body { margin: 0; padding: 30px; background: #f3f4f6; font-family: Arial, sans-serif; }
.container { width: 100%; max-width: var(--max); margin: 0 auto; }
h1 { color: var(--accent); text-align: center; font-size: 40px; margin-bottom: 30px; font-weight: 800; }
.back-btn { display: inline-block; margin-bottom: 20px; text-decoration: none; background: var(--accent); color: #fff; padding: 10px 18px; border-radius: 10px; font-weight: bold; }
.back-btn:hover { background: #0056b3; }

.card { background: #fff; border: 3px solid var(--accent); border-radius: 24px; padding: 30px; box-shadow: 0 5px 14px rgba(0,0,0,0.05); margin-bottom: 24px; }
.card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0; }
.card h2 { color: var(--accent); font-size: 20px; font-weight: 800; margin: 0; }

.form-group { margin-bottom: 20px; }
label { display: block; margin-bottom: 8px; font-weight: 700; color: #333; }
.input, select { width: 100%; padding: 14px; border: 2px solid #007bff; border-radius: 12px; font-size: 15px; font-family: Arial, sans-serif; color: #1f2937; background: #fff; }
.vo-type { appearance: none; -webkit-appearance: none; -moz-appearance: none; padding: 14px 45px 14px 14px; background-color: #fff; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 20 20'%3E%3Cpath fill='%23333' d='M5 7l5 5 5-5'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 25px center; background-size: 14px; }
.input:focus, select:focus { outline: none; box-shadow: 0 0 0 3px rgba(0,123,255,.1); }
.input[readonly] { background: #f0f7ff; font-weight: 700; color: #007bff; }

.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }

.file-row { display: flex; align-items: center; gap: 10px; border: 2px dashed #93c5fd; border-radius: 12px; padding: 12px 16px; background: #f0f7ff; }
.file-row input[type=file] { flex: 1; border: none; background: transparent; font-size: 13px; padding: 0; cursor: pointer; }
.file-link { display: inline-block; margin-top: 10px; margin-bottom: 10px; color: var(--accent); text-decoration: none; font-weight: 700; font-size: 14px; }
.file-link:hover { text-decoration: underline; }

.btn { width: 100%; background: var(--accent); color: #fff; border: none; padding: 16px; border-radius: 14px; font-size: 18px; font-weight: 700; cursor: pointer; }
.btn:hover { background: #0056b3; }

.error-box { background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; padding: 13px 16px; border-radius: 10px; font-weight: 600; font-size: 14px; margin-bottom: 20px; }

.switch-wrap { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; }
.switch { position: relative; width: 60px; height: 30px; flex-shrink: 0; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #dc2626; border-radius: 30px; transition: 0.3s; width: 60px; padding: 0; border: none; }
.slider:before { position: absolute; content: ""; height: 22px; width: 22px; left: 4px; bottom: 4px; background: white; border-radius: 50%; transition: 0.3s; }
input:checked + .slider { background: #16a34a; }
input:checked + .slider:before { transform: translateX(30px); }
.switch-label { font-weight: 700; font-size: 15px; color: #374151; }
.switch-status { font-size: 13px; font-weight: 700; }
.applicable-fields { display: none; }
.applicable-fields.show { display: block; }

.entry-block { border: 2px solid #e2e8f0; border-radius: 16px; padding: 20px; margin-bottom: 16px; position: relative; }
.entry-block-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.entry-block-title { font-weight: 800; color: #007bff; font-size: 15px; }
.remove-entry-btn { background: #fee2e2; color: #dc2626; border: none; padding: 6px 12px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; }
.remove-entry-btn:hover { background: #fecaca; }
.add-entry-btn { background: #eff6ff; color: #007bff; border: 2px dashed #93c5fd; padding: 12px; border-radius: 12px; font-weight: 700; font-size: 14px; cursor: pointer; width: 100%; margin-top: 4px; }
.add-entry-btn:hover { background: #dbeafe; }

@media (max-width: 640px) {
    .grid-2 { grid-template-columns: 1fr; }
    body { padding: 15px; }
    h1 { font-size: 30px; }
    .card { padding: 22px; }
}
</style>
</head>
<body>

<div class="container">

    <a href="bq_list.php?project_id=<?= $project_id ?>" class="back-btn">← Back</a>
    <h1><?= $is_edit ? 'Edit Subcontractor BQ' : 'Add Subcontractor BQ' ?></h1>

    <?php if ($error): ?>
        <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <!-- SUBCON BQ DETAILS -->
        <div class="card">
            <div class="card-header"><h2>Details</h2></div>

            <div class="form-group">
                <label>Subcontractor <span style="color:red">*</span></label>
                <input type="text" class="input" name="name" value="<?= htmlspecialchars($name) ?>" required>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" class="input" name="bq_date" value="<?= htmlspecialchars($bq_date ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Amount (RM)</label>
                    <input type="number" class="input" name="amount" step="0.01" placeholder="0.00" value="<?= htmlspecialchars($amount ?? '') ?>">
                </div>
            </div>
			
			<div class="form-group">
                <label>Document</label>
                <?php if (!empty($document)):
                    foreach (explode(',', $document) as $doc):
                        if (!$doc) continue;
                ?>
                    <a href="uploads/project_<?= $project_id ?>/bq/<?= htmlspecialchars($doc) ?>" target="_blank" class="file-link">📄 <?= htmlspecialchars($doc) ?></a><br>
                <?php endforeach; endif; ?>
                <div class="file-row">
                    <input type="file" name="bq_document[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                </div>
            </div>
        </div>

        <!-- VARIATION ORDER -->
        <div class="card">
            <div class="card-header"><h2>Variation Order</h2></div>

            <input type="hidden" name="vo_status" id="vo_status_input" value="<?= htmlspecialchars($vo_status) ?>">
            <div class="switch-wrap">
                <label class="switch">
                    <input type="checkbox" id="vo_toggle"
                        <?= $vo_status === 'applicable' ? 'checked' : '' ?>
                        onchange="handleToggle('vo', this.checked)">
                    <span class="slider"></span>
                </label>
                <span class="switch-label">Status: </span>
                <span id="vo_switch_label" class="switch-status" style="color:<?= $vo_status === 'applicable' ? '#16a34a' : '#dc2626' ?>">
                    <?= $vo_status === 'applicable' ? 'Applicable' : 'Non-Applicable' ?>
                </span>
            </div>

            <div class="applicable-fields <?= $vo_status === 'applicable' ? 'show' : '' ?>" id="vo_fields">

                <div id="vo-entry-list">
                    <?php foreach ($vo_rows as $i => $vo): $n = $i + 1; ?>
                    <div class="entry-block">
                        <div class="entry-block-header">
                            <span class="entry-block-title">Variation Order <?= $n ?></span>
                            <button type="button" class="remove-entry-btn" onclick="removeEntry(this, 'vo-entry-list')">✕ Remove</button>
                        </div>

                        <div class="grid-2">
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" class="input" name="vo_date[]" value="<?= htmlspecialchars($vo['vo_date'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Amount (RM)</label>
                                <input type="number" class="input vo-amount" name="vo_amount[]" step="0.01" placeholder="0.00" value="<?= htmlspecialchars($vo['vo_amount'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="grid-2">
                            <div class="form-group">
                                <label>Type</label>
                                <select class="input vo-type" name="vo_type[]">
                                    <option value="">-- Select Type --</option>
                                    <option value="addition" <?= ($vo['vo_type'] ?? '') === 'addition' ? 'selected' : '' ?>>Addition (+)</option>
                                    <option value="subtraction" <?= ($vo['vo_type'] ?? '') === 'subtraction' ? 'selected' : '' ?>>Subtraction (−)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>New Award Amount (RM)</label>
                                <input type="text" class="input new-award-display" readonly value="<?= number_format((float)($vo['new_award_amount'] ?? $contract_sum), 2) ?>">
                            </div>
                        </div>

                        <div class="form-group">
							<label>Document</label>
							<?php if (!empty($vo['vo_document'])): ?>
								<a href="uploads/project_<?= $project_id ?>/bq/<?= htmlspecialchars($vo['vo_document']) ?>" target="_blank" class="file-link">📄 View Current Document</a>
							<?php endif; ?>
							<input type="hidden" name="vo_document_existing[]" value="<?= htmlspecialchars($vo['vo_document'] ?? '') ?>">
							<div class="file-row">
								<input type="file" name="vo_document[]" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
							</div>
						</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="add-entry-btn" onclick="addVoEntry()">＋ Add Another VO</button>
            </div>
        </div>

        <!-- PHK -->
        <div class="card">
            <div class="card-header"><h2>Adjustment to Original Contract (PHK)</h2></div>

            <input type="hidden" name="phk_status" id="phk_status_input" value="<?= htmlspecialchars($phk_status) ?>">
            <div class="switch-wrap">
                <label class="switch">
                    <input type="checkbox" id="phk_toggle"
                        <?= $phk_status === 'applicable' ? 'checked' : '' ?>
                        onchange="handleToggle('phk', this.checked)">
                    <span class="slider"></span>
                </label>
                <span class="switch-label">Status: </span>
                <span id="phk_switch_label" class="switch-status" style="color:<?= $phk_status === 'applicable' ? '#16a34a' : '#dc2626' ?>">
                    <?= $phk_status === 'applicable' ? 'Applicable' : 'Non-Applicable' ?>
                </span>
            </div>

            <div class="applicable-fields <?= $phk_status === 'applicable' ? 'show' : '' ?>" id="phk_fields">
                <div id="phk-entry-list">
                    <?php foreach ($phk_rows as $i => $phk): $n = $i + 1; ?>
                    <div class="entry-block">
                        <div class="entry-block-header">
                            <span class="entry-block-title">PHK <?= $n ?></span>
                            <button type="button" class="remove-entry-btn" onclick="removeEntry(this, 'phk-entry-list')">✕ Remove</button>
                        </div>

                        <div class="grid-2">
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" class="input" name="phk_date[]" value="<?= htmlspecialchars($phk['phk_date'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Amount (RM)</label>
                                <input type="number" class="input" name="phk_amount[]" step="0.01" placeholder="0.00" value="<?= htmlspecialchars($phk['phk_amount'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="add-entry-btn" onclick="addPhkEntry()">＋ Add Another PHK</button>
            </div>
        </div>

        <button type="submit" class="btn">💾 <?= $is_edit ? 'Update' : 'Save' ?> Subcontractor BQ</button>

    </form>

</div>

<script>
const contractSum = <?= json_encode($contract_sum) ?>;
let voCount  = <?= count($vo_rows) ?>;
let phkCount = <?= count($phk_rows) ?>;

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

function addVoEntry() {
    voCount++;
    const list = document.getElementById('vo-entry-list');
    const block = document.createElement('div');
    block.className = 'entry-block';
    block.innerHTML = `
        <div class="entry-block-header">
            <span class="entry-block-title">Variation Order ${voCount}</span>
            <button type="button" class="remove-entry-btn" onclick="removeEntry(this, 'vo-entry-list')">✕ Remove</button>
        </div>
        <div class="grid-2">
            <div class="form-group">
                <label>Date</label>
                <input type="date" class="input" name="vo_date[]">
            </div>
            <div class="form-group">
                <label>Amount (RM)</label>
                <input type="number" class="input vo-amount" name="vo_amount[]" step="0.01" placeholder="0.00">
            </div>
        </div>
        <div class="grid-2">
            <div class="form-group">
                <label>Type</label>
                <select class="input vo-type" name="vo_type[]">
                    <option value="">-- Select Type --</option>
                    <option value="addition">Addition (+)</option>
                    <option value="subtraction">Subtraction (−)</option>
                </select>
            </div>
            <div class="form-group">
                <label>New Award Amount (RM)</label>
                <input type="text" class="input new-award-display" readonly value="${contractSum.toFixed(2)}">
            </div>
        </div>
        <div class="form-group">
            <label>Document</label>
            <div class="file-row">
                <input type="file" name="vo_document[]" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
            </div>
        </div>
    `;
    list.appendChild(block);
    attachVoListeners(block);
}

function addPhkEntry() {
    phkCount++;
    const list = document.getElementById('phk-entry-list');
    const block = document.createElement('div');
    block.className = 'entry-block';
    block.innerHTML = `
        <div class="entry-block-header">
            <span class="entry-block-title">PHK ${phkCount}</span>
            <button type="button" class="remove-entry-btn" onclick="removeEntry(this, 'phk-entry-list')">✕ Remove</button>
        </div>
        <div class="grid-2">
            <div class="form-group">
                <label>Date</label>
                <input type="date" class="input" name="phk_date[]">
            </div>
            <div class="form-group">
                <label>Amount (RM)</label>
                <input type="number" class="input" name="phk_amount[]" step="0.01" placeholder="0.00">
            </div>
        </div>
    `;
    list.appendChild(block);
}

function removeEntry(btn, listId) {
    const list = document.getElementById(listId);
    if (list.querySelectorAll('.entry-block').length > 1) {
        btn.closest('.entry-block').remove();
    }
}

function attachVoListeners(block) {
    const amountInput = block.querySelector('.vo-amount');
    const typeSelect   = block.querySelector('.vo-type');
    const display      = block.querySelector('.new-award-display');

    function recalc() {
        const voAmount = parseFloat(amountInput.value) || 0;
        const voType   = typeSelect.value;
        let result = contractSum;
        if (voType && voAmount !== 0) {
            result = voType === 'addition' ? contractSum + voAmount : contractSum - voAmount;
        }
        display.value = result.toFixed(2);
    }

    amountInput.addEventListener('input', recalc);
    typeSelect.addEventListener('change', recalc);
    recalc();
}

document.querySelectorAll('#vo-entry-list .entry-block').forEach(attachVoListeners);
</script>

</body>
</html>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
$title = "EOT";
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$project_id = intval($_GET['project_id'] ?? 0);
$eot_id     = intval($_GET['eot_id'] ?? 0);
if (!$project_id) die("Invalid project.");

if (!user_can_access_project($db, $_SESSION['user_id'], $project_id, $_SESSION['account_type'] ?? '')) {
    die("You do not have permission to view this project.");
}

$user_id    = $_SESSION['user_id'];
$role       = $_SESSION['account_type'] ?? '';
//$can_verify = in_array($role, ['Client Admin', 'Manager']);
if ($role === 'Client Admin') {
    $can_verify = true;
} else {
    $stmt = $db->prepare("
        SELECT 1 
        FROM verifier_project
        WHERE project_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $project_id, $user_id);
    $stmt->execute();

    $can_verify = $stmt->get_result()->num_rows > 0;
}

// Only Admin & QAQC can delete an EOT record and its files.
// Sesuaikan nilai 'Admin' / 'QAQC' dengan value sebenar dalam $_SESSION['account_type']
$can_delete = in_array($role, ['Client Admin', 'QAQC']);

$error      = '';

//  upload a single file 
function handle_upload($file_key, $prefix, $project_id) {

    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $allowed = ['pdf','jpg','jpeg','png','webp','doc','docx'];

    $original = trim($_FILES[$file_key]['name']);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

    if (!$ext || !in_array($ext, $allowed)) {
        return false;
    }

    $nameOnly = pathinfo($original, PATHINFO_FILENAME);
    $safeName = preg_replace("/[^a-zA-Z0-9]/", "_", $nameOnly);
    $safeName = substr($safeName, 0, 50);

    $fname = $prefix . '_' . $safeName . '_' . time() . '.' . $ext;

    // 🔥 folder: project_id/eot
    $project_folder = 'uploads/project_' . $project_id . '/eot';

    if (!is_dir($project_folder)) {
        mkdir($project_folder, 0755, true);
    }

    if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $project_folder . '/' . $fname)) {
        return false;
    }

    return 'project_' . $project_id . '/eot/' . $fname;
}
//multiple file
function handle_multiple_upload($file_key, $prefix, $project_id) {

    if (!isset($_FILES[$file_key])) return [];

    $files = $_FILES[$file_key];
    $uploaded_files = [];

    $allowed = ['pdf','jpg','jpeg','png','webp','doc','docx'];

    // 🔥 folder: project_id/eot
    $project_folder = 'uploads/project_' . $project_id . '/eot';

    if (!is_dir($project_folder)) {
        mkdir($project_folder, 0755, true);
    }

    for ($i = 0; $i < count($files['name']); $i++) {

        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

        $original = trim($files['name'][$i]);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if (!$ext || !in_array($ext, $allowed)) continue;

        $nameOnly = pathinfo($original, PATHINFO_FILENAME);
        $safeName = preg_replace("/[^a-zA-Z0-9]/", "_", $nameOnly);
        $safeName = substr($safeName, 0, 50);

        $fname = $prefix . '_' . $safeName . '_' . time() . '_' . $i . '.' . $ext;

        if (move_uploaded_file($files['tmp_name'][$i], $project_folder . '/' . $fname)) {
            $uploaded_files[] = 'project_' . $project_id . '/eot/' . $fname;
        }
    }

    return $uploaded_files;
}
//  Auto add verification columns if missing 
$ver_cols = [
    'verified_b'    => "VARCHAR(100) DEFAULT NULL",
    'verified_b_at' => "DATETIME DEFAULT NULL",
    'verified_c'    => "VARCHAR(100) DEFAULT NULL",
    'verified_c_at' => "DATETIME DEFAULT NULL",
    'verified_d'    => "VARCHAR(100) DEFAULT NULL",
    'verified_d_at' => "DATETIME DEFAULT NULL",
    'verified_e'    => "VARCHAR(100) DEFAULT NULL",
    'verified_e_at' => "DATETIME DEFAULT NULL",
];
foreach ($ver_cols as $col => $def) {
    $res = $db->query("SHOW COLUMNS FROM `eot` LIKE '{$col}'");
    if ($res && $res->num_rows === 0) {
        $db->query("ALTER TABLE `eot` ADD COLUMN `{$col}` {$def}");
    }
}

//  Fetch existing EOT 
$eot = null;
if ($eot_id) {
    $stmt = $db->prepare("SELECT * FROM `eot` WHERE id = ? AND project_id = ?");
    $stmt->bind_param("ii", $eot_id, $project_id);
    $stmt->execute();
    $eot = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$eot) die("EOT not found.");
}

//  Handle POST 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';

    // A
    if ($section === 'A') {
        $eot_number = trim($_POST['eot_number'] ?? '');
        $reason     = trim($_POST['reason'] ?? '');
        if (!$eot_number || !$reason) {
            $error = "EOT Number and Reason are required.";
        } else {
$loi_files = handle_multiple_upload('loi_document', 'eot_loi', $project_id);
if ($loi_files === false) {
    $error = "Invalid file for Letter of Intent.";
} else {

    if (!empty($loi_files)) {
        $loi = implode(',', $loi_files);
    } else {
        $loi = $eot['loi_document'] ?? null;
    }

    if ($eot_id) {
        $stmt = $db->prepare("UPDATE `eot` SET eot_number=?, reason=?, loi_document=? WHERE id=? AND project_id=?");
        $stmt->bind_param("sssii", $eot_number, $reason, $loi, $eot_id, $project_id);
    } else {
        $stmt = $db->prepare("INSERT INTO `eot` (project_id, eot_number, reason, loi_document, status_eot, created_by, created_at) VALUES (?,?,?,?,'pending',?,NOW())");
        $stmt->bind_param("isssi", $project_id, $eot_number, $reason, $loi, $user_id);
    }

    if ($stmt->execute()) {
        $new_id = $eot_id ?: $stmt->insert_id;
        $stmt->close();
        header("Location: add_eot.php?project_id={$project_id}&eot_id={$new_id}&msg=a_saved");
        exit;
    }

    $error = "DB error: " . $stmt->error;
    $stmt->close();
}
        }
    }

    // B
    if ($section === 'B' && $eot_id) {
        $status_eot = $_POST['status_eot'] ?? '';
        if (!in_array($status_eot, ['approved','rejected'])) {
            $error = "Please select a status.";
        } else {
            $stmt = $db->prepare("UPDATE `eot` SET status_eot=? WHERE id=? AND project_id=?");
            $stmt->bind_param("sii", $status_eot, $eot_id, $project_id);
            $stmt->execute(); $stmt->close();
            header("Location: add_eot.php?project_id={$project_id}&eot_id={$eot_id}&msg=b_saved"); exit;
        }
    }


    // C approved
 if ($section === 'C_approved' && $eot_id) {

    $date_approval = $_POST['date_approval'] ?? '';
    $eot_date      = $_POST['eot_date'] ?? '';

    if (!$date_approval || !$eot_date) {
        $error = "Date Approval and EOT Date are required.";
    } else {

        $approval_files = handle_multiple_upload('approval_letter', 'eot_approval', $project_id);


        if ($approval_files === false) {
            $error = "Invalid approval letter file.";
        } else {

            if (!empty($approval_files)) {
                $al = implode(',', $approval_files);
            } else {
                $al = $eot['approval_letter'] ?? null;
            }

            $stmt = $db->prepare("
                UPDATE `eot` 
                SET date_approval=?, eot_date=?, approval_letter=? 
                WHERE id=? AND project_id=?
            ");
            $stmt->bind_param("sssii", $date_approval, $eot_date, $al, $eot_id, $project_id);

            $stmt->execute();
            $stmt->close();

            header("Location: add_eot.php?project_id={$project_id}&eot_id={$eot_id}&msg=c_saved");
            exit;
        }
    }
}

    // C rejected
    if ($section === 'C_rejected' && $eot_id) {
        $rejected_reason = trim($_POST['rejected_reason'] ?? '');
        if (!$rejected_reason) {
            $error = "Reason is required.";
        } else {
            $rl = handle_upload('rejected_letter', 'eot_reject', $project_id);
            if ($rl === false) {
                $error = "Invalid rejection letter file.";
            } else {
                if ($rl === null && !empty($eot['rejected_letter'])) $rl = $eot['rejected_letter'];
                $stmt = $db->prepare("UPDATE `eot` SET rejected_reason=?, rejected_letter=? WHERE id=? AND project_id=?");
                $stmt->bind_param("ssii", $rejected_reason, $rl, $eot_id, $project_id);
                $stmt->execute(); $stmt->close();
                header("Location: add_eot.php?project_id={$project_id}&eot_id={$eot_id}&msg=c_saved"); exit;
            }
        }
    }

    // C verify
    if ($section === 'verify_c' && $eot_id && $can_verify) {
        $vname = $_SESSION['full_name'] ?? $role;
        $stmt  = $db->prepare("UPDATE `eot` SET verified_c=?, verified_c_at=NOW() WHERE id=? AND project_id=?");
        $stmt->bind_param("sii", $vname, $eot_id, $project_id);
        $stmt->execute(); $stmt->close();
        header("Location: add_eot.php?project_id={$project_id}&eot_id={$eot_id}&msg=verified"); exit;
    }

    // D
if ($section === 'D' && $eot_id) {

    $status_loss         = $_POST['status_loss'] ?? '';
    $loss_overall_date   = !empty($_POST['loss_overall_date']) ? $_POST['loss_overall_date'] : null;
    $loss_overall_amount = ($_POST['loss_overall_amount'] !== '') ? floatval($_POST['loss_overall_amount']) : null;

    if (!in_array($status_loss, ['approved','rejected'])) {
        $error = "Please select a Loss & Expenses status.";
    } else {

        $loss_files = handle_multiple_upload('loss_overall_letter', 'eot_loss_overall', $project_id);

        if ($loss_files === false) {
            $error = "Invalid file for Loss & Expenses.";
        } else {

            if (!empty($loss_files)) {
                $ll = implode(',', $loss_files);
            } else {
                $ll = $eot['loss_overall_letter'] ?? null;
            }

            // 🔥 kalau Not Applicable → kosongkan semua
            if ($status_loss === 'rejected') {
                $loss_overall_date = null;
                $loss_overall_amount = null;
                $ll = null;
            }

            $stmt = $db->prepare("
                UPDATE `eot` 
                SET status_loss=?, loss_overall_date=?, loss_overall_amount=?, loss_overall_letter=? 
                WHERE id=? AND project_id=?
            ");

            $stmt->bind_param("ssdsii", $status_loss, $loss_overall_date, $loss_overall_amount, $ll, $eot_id, $project_id);

            $stmt->execute();
            $stmt->close();

            header("Location: add_eot.php?project_id={$project_id}&eot_id={$eot_id}&msg=d_saved");
            exit;
        }
    }
}

    // D verify
    if ($section === 'verify_d' && $eot_id && $can_verify) {
        $vname = $_SESSION['full_name'] ?? $role;
        $stmt  = $db->prepare("UPDATE `eot` SET verified_d=?, verified_d_at=NOW() WHERE id=? AND project_id=?");
        $stmt->bind_param("sii", $vname, $eot_id, $project_id);
        $stmt->execute(); $stmt->close();
        header("Location: add_eot.php?project_id={$project_id}&eot_id={$eot_id}&msg=verified"); exit;
    }

    // E
 if ($section === 'E' && $eot_id) {

    $loss_insurance_date   = !empty($_POST['loss_insurance_date']) ? $_POST['loss_insurance_date'] : null;
    $loss_insurance_amount = ($_POST['loss_insurance_amount'] !== '') ? floatval($_POST['loss_insurance_amount']) : null;

    $insurance_files = handle_multiple_upload('loss_insurance_letter', 'eot_loss_insurance', $project_id);

    if ($insurance_files === false) {
        $error = "Invalid insurance letter file.";
    } else {

        if (!empty($insurance_files)) {
            $li = implode(',', $insurance_files);
        } else {
            $li = $eot['loss_insurance_letter'] ?? null;
        }

        $stmt = $db->prepare("
            UPDATE `eot` 
            SET loss_insurance_date=?, loss_insurance_amount=?, loss_insurance_letter=? 
            WHERE id=? AND project_id=?
        ");

        $stmt->bind_param("sdsii", 
            $loss_insurance_date, 
            $loss_insurance_amount, 
            $li, 
            $eot_id, 
            $project_id
        );

        $stmt->execute();
        $stmt->close();

        header("Location: add_eot.php?project_id={$project_id}&eot_id={$eot_id}&msg=e_saved");
        exit;
    }
}
    // E verify
    if ($section === 'verify_e' && $eot_id && $can_verify) {
        $vname = $_SESSION['full_name'] ?? $role;
        $stmt  = $db->prepare("UPDATE `eot` SET verified_e=?, verified_e_at=NOW() WHERE id=? AND project_id=?");
        $stmt->bind_param("sii", $vname, $eot_id, $project_id);
        $stmt->execute(); $stmt->close();
        header("Location: add_eot.php?project_id={$project_id}&eot_id={$eot_id}&msg=verified"); exit;
    }

    // DELETE — Admin & QAQC sahaja, padam rekod + semua fail berkaitan
    if ($section === 'delete' && $eot_id && $can_delete) {

        $file_fields = [
            'loi_document',
            'approval_letter',
            'rejected_letter',
            'loss_overall_letter',
            'loss_insurance_letter',
        ];

        foreach ($file_fields as $field) {
            if (!empty($eot[$field])) {
                $files = explode(',', $eot[$field]);
                foreach ($files as $f) {
                    $f = trim($f);
                    if ($f === '') continue;
                    $path = 'uploads/' . $f;
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }
            }
        }

        $stmt = $db->prepare("DELETE FROM `eot` WHERE id=? AND project_id=?");
        $stmt->bind_param("ii", $eot_id, $project_id);
        $stmt->execute();
        $stmt->close();

        header("Location: eot.php?project_id={$project_id}&msg=deleted");
        exit;
    }

    // Refetch after failed POST
    if ($eot_id) {
        $stmt = $db->prepare("SELECT * FROM `eot` WHERE id=? AND project_id=?");
        $stmt->bind_param("ii", $eot_id, $project_id);
        $stmt->execute();
        $eot = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$db->close();

// --- Updated Strict Verification Unlock Logic ---

$has_eot     = !empty($eot);
$status_eot  = $eot['status_eot']  ?? 'pending';
$status_loss = $eot['status_loss'] ?? null;

// Boolean flags for verification
$b_verified = !empty($eot['verified_b']);
$c_verified = !empty($eot['verified_c']);
$d_verified = !empty($eot['verified_d']);

$unlock_b = $has_eot;

$unlock_c = $has_eot && $status_eot === 'approved';

$unlock_d = $unlock_c && $c_verified && !empty($eot['date_approval']);

$unlock_e = $unlock_d && $d_verified && $status_loss === 'approved';

$msg_map = [
    'a_saved'  => '✅ Section A saved.',
    'b_saved'  => '✅ Status saved.',
    'c_saved'  => '✅ Section C saved.',
    'd_saved'  => '✅ Loss & Expenses saved.',
    'e_saved'  => '✅ Insurance submission saved.',
    'verified' => '✅ Section verified successfully.',
    'deleted'  => '🗑️ EOT record deleted.',
];
$flash = $msg_map[$_GET['msg'] ?? ''] ?? '';

// Verification 
$b_verified = !empty($eot['verified_b']);
$c_verified = !empty($eot['verified_c']);
$d_verified = !empty($eot['verified_d']);
$e_verified = !empty($eot['verified_e']);
$c_done     = !empty($eot['date_approval']) && !empty($eot['eot_date']);
$cr_done    = !empty($eot['rejected_reason']);
$e_done     = !empty($eot['loss_insurance_date']) || !empty($eot['loss_insurance_amount']);


$lock_a = !empty($eot['verified_b']);
$lock_b = !empty($eot['verified_b']);
$lock_c = !empty($eot['verified_c']);
$lock_d = !empty($eot['verified_d']);
$lock_e = !empty($eot['verified_e']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Project Management System</title>
<style>
:root {
  --accent:#007bff; --accent-dark:#0056b3;
  --border:#e2e8f0; --bg:#f3f4f6; --white:#fff;
  --radius:12px; --shadow:0 2px 8px rgba(0,0,0,.08);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:var(--bg);min-height:100vh;padding:24px;display:flex;flex-direction:column;align-items:center}
.back-btn{align-self:flex-start;margin-bottom:16px;background:var(--accent);color:#fff;padding:10px 18px;border-radius:8px;font-weight:700;text-decoration:none;font-size:14px}
.back-btn:hover{background:var(--accent-dark)}
h1{font-size:30px;font-weight:800;color:var(--accent);margin:8px 0 24px;text-align:center}
.form-wrap{width:100%;max-width:720px;display:flex;flex-direction:column;gap:16px}

/* Section card */
.sec{background:var(--white);border:2px solid var(--accent);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow)}
.sec-header{display:flex;align-items:center;gap:12px;padding:14px 20px;background:var(--accent)}
.sec-badge{width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:#fff;flex-shrink:0}
.sec-title{font-weight:700;font-size:15px;color:#fff;flex:1}
.sec-status{font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;white-space:nowrap}
.st-pending {background:rgba(255,255,255,.2);color:#fff}
.st-approved{background:#dcfce7;color:#16a34a}
.st-rejected{background:#fee2e2;color:#dc2626}
.st-done    {background:#dbeafe;color:#1d4ed8}
.st-verified{background:#dbeafe;color:#1d4ed8}
.sec-body{padding:28px;display:flex;flex-direction:column;gap:20px}

/* Locked placeholder */
.locked-card{display:flex;align-items:center;gap:14px;background:#f8fafc;border:2px dashed #d1d5db;border-radius:var(--radius);padding:16px 22px}
.locked-icon{font-size:20px;flex-shrink:0}
.locked-title{font-size:14px;font-weight:700;color:#9ca3af}
.locked-sub{font-size:12px;margin-top:3px;color:#c4c9d4}

/* Form fields */
.field{display:flex;flex-direction:column;gap:7px}
.field label{font-weight:700;font-size:13px;color:#374151;letter-spacing:.2px}
input[type=text],input[type=date],input[type=number],textarea,select{
  width:100%;padding:10px 13px;border:1.5px solid #d1d5db;border-radius:8px;
  font-size:14px;font-family:Arial,sans-serif;color:#1f2937;background:#fff;transition:border-color .15s}
input:focus,textarea:focus,select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(0,123,255,.1)}
textarea{resize:vertical}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}

/* File row */
.file-row{display:flex;align-items:center;gap:10px;border:1.5px dashed #93c5fd;border-radius:8px;padding:9px 13px;background:#f0f7ff}
.file-row input[type=file]{flex:1;border:none;background:transparent;font-size:13px;padding:0;cursor:pointer}
.opt-tag{font-size:11px;font-weight:700;color:#94a3b8;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:20px;padding:2px 8px;white-space:nowrap}
.existing-file{font-size:12px;color:#6b7280;margin-top:4px}
.existing-file a{color:var(--accent);font-weight:600;text-decoration:none}
.file-hint{font-size:11px;color:#9ca3af;margin-top:3px}

/* Radio */
.radio-group{display:flex;gap:10px;flex-wrap:wrap}
.radio-opt{display:flex;align-items:center;gap:8px;font-weight:600;font-size:14px;cursor:pointer;background:#f8fafc;border:1.5px solid var(--border);border-radius:8px;padding:10px 18px;transition:all .15s;margin:0}
.radio-opt:has(input:checked){border-color:var(--accent);background:#eff6ff;color:var(--accent)}
.radio-opt input{width:auto;padding:0;border:none;cursor:pointer}

/* Divider */
.sep{height:1px;background:var(--border)}

/* Save button */
.save-btn{background:var(--accent);color:#fff;padding:11px 20px;font-size:15px;font-weight:700;border:none;border-radius:8px;cursor:pointer;width:100%}
.save-btn:hover{background:var(--accent-dark)}

/* Verification bar */
.verify-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;background:#fffbeb;border:1.5px solid #fde68a;border-radius:10px;padding:14px 18px}
.verify-bar.verified{background:#f0fdf4;border-color:#86efac}
.vbar-left{display:flex;align-items:center;gap:10px}
.vbar-icon{font-size:20px}
.vbar-label{font-size:13px;font-weight:700;color:#92400e}
.verify-bar.verified .vbar-label{color:#15803d}
.vbar-sub{font-size:12px;color:#a16207;margin-top:2px}
.verify-bar.verified .vbar-sub{color:#166534}
.verify-btn{background:#f59e0b;color:#fff;padding:8px 18px;font-size:13px;font-weight:700;border:none;border-radius:8px;cursor:pointer;white-space:nowrap}
.verify-btn:hover{background:#d97706}
.pending-tag{font-size:12px;font-weight:600;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:6px;padding:5px 12px;white-space:nowrap}

/* Notices */
.notice{padding:13px 16px;border-radius:9px;font-weight:700;font-size:14px}
.notice-success{background:#dcfce7;border:1px solid #86efac;color:#15803d}
.notice-error  {background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c}

@media(max-width:580px){.form-grid{grid-template-columns:1fr}.sec-body{padding:20px}}
	.replace-btn {
  margin-left:10px;
  background:#f59e0b;
  color:#fff;
  border:none;
  padding:5px 10px;
  font-size:12px;
  border-radius:6px;
  cursor:pointer;
}
.replace-btn:hover {
  background:#d97706;
}
	.file-preview {
  display:flex;
  justify-content:space-between;
  align-items:center;
  background:#f8fafc;
  border:1.5px solid #e2e8f0;
  border-radius:10px;
  padding:10px 14px;
  gap:10px;
}

.file-left {
  display:flex;
  align-items:center;
  gap:8px;
  font-size:13px;
  color:#374151;
  overflow:hidden;
}

.file-name {
  max-width:260px;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

.file-actions {
  display:flex;
  gap:8px;
}

.view-btn {
  background:#e0f2fe;
  color:#0369a1;
  padding:5px 10px;
  border-radius:6px;
  font-size:12px;
  font-weight:600;
  text-decoration:none;
}

.view-btn:hover {
  background:#bae6fd;
}

.replace-btn {
  background:#f59e0b;
  color:#fff;
  border:none;
  padding:5px 10px;
  font-size:12px;
  border-radius:6px;
  cursor:pointer;
}

.replace-btn:hover {
  background:#d97706;
}
	
	.file-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 16px;
  margin-top: 10px;
}

.file-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 160px;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  background: #f8fafc;
  text-decoration: none;
  transition: 0.2s;
  padding: 15px;
}

.file-card:hover {
  background: #e0f2fe;
  border-color: #38bdf8;
}

.file-icon {
  font-size: 48px;
  margin-bottom: 10px;
}

.file-label {
  font-size: 12px;
  text-align: center;
  word-break: break-word;
}
	
	fieldset[disabled] {
  opacity: 0.65;
  pointer-events: none;
}
	fieldset {
  border: none;
  margin: 0;
  padding: 0;
  min-width: 0;
}

.delete-sec { border-color:#dc2626; }
.delete-btn {
  background:#dc2626;
  color:#fff;
  padding:11px 20px;
  font-size:14px;
  font-weight:700;
  border:none;
  border-radius:8px;
  cursor:pointer;
  width:100%;
}
.delete-btn:hover { background:#b91c1c; }
	
	
</style>
</head>
<body>

<a href="eot.php?project_id=<?= $project_id ?>" class="back-btn">← Back</a>
<h1>Extension of Time</h1>

<div class="form-wrap">

<?php if ($flash): ?>
<div class="notice notice-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="notice notice-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- A -->
<div class="sec">
  <div class="sec-header">
    <div class="sec-badge">A</div>
    <div class="sec-title">Detail Extension of Time</div>
    <span class="sec-status <?= $has_eot ? 'st-done' : 'st-pending' ?>">
      <?= $has_eot ? '✓ Saved' : 'Draft' ?>
    </span>
  </div>
  <div class="sec-body">
    <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:20px">
		 
      <input type="hidden" name="section" value="A">
      <input type="hidden" name="project_id" value="<?= $project_id ?>">
      <div class="field">
        <label>EOT Number <span style="color:#ef4444">*</span></label>
        <input type="text" name="eot_number" value="<?= htmlspecialchars($eot['eot_number'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label>Reason of EOT <span style="color:#ef4444">*</span></label>
        <textarea name="reason" rows="4" required><?= htmlspecialchars($eot['reason'] ?? '') ?></textarea>
      </div>
<div class="field">
  <label>Letter of Intent EOT Application</label>

  <?php if (!empty($eot['loi_document'])): ?>

    <div class="file-grid">

    <?php 
    $files = explode(',', $eot['loi_document']);

    foreach ($files as $file): 
        if (!$file) continue;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        // icon ikut file type
        $icon = '📄';
        if ($ext === 'pdf') $icon = '📕';
        elseif (in_array($ext, ['jpg','jpeg','png'])) $icon = '🖼️';
        elseif (in_array($ext, ['doc','docx'])) $icon = '📘';
    ?>

        <a href="download_file.php?file=<?= urlencode(trim($file)) ?>" target="_blank" class="file-card">
            <div class="file-icon"><?= $icon ?></div>
            <div class="file-label"><?= htmlspecialchars($file) ?></div>
        </a>

    <?php endforeach; ?>

    </div>

<?php if (!$lock_c): ?>
  <button type="button" onclick="showReplaceFile('uploadBox', this)" class="replace-btn" style="margin-top:10px;">
    Replace Files
  </button>
<?php endif; ?>

  <?php endif; ?>

  <div id="uploadBox" style="<?= !empty($eot['loi_document']) ? 'display:none;' : '' ?>">
    <div class="file-row">
      <input type="file" name="loi_document[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
      <span class="opt-tag">Optional</span>
    </div>
  </div>

</div>
 <?php if (!$lock_c): ?>
  <button type="submit" class="save-btn">
    <?= $has_eot ? 'Update Section A' : 'Save & Continue' ?>
  </button>
<?php endif; ?>
    </form>
  </div>
</div>

<!-- B -->
<?php if ($unlock_b): ?>
<div class="sec">
	 
  <div class="sec-header">
	 
    <div class="sec-badge">B</div>
    <div class="sec-title">Status Extension of Time</div>

    <?php if ($b_verified): ?>
      <span class="sec-status st-verified">✓ Verified</span>
    <?php elseif ($status_eot === 'approved'): ?>
      <span class="sec-status st-approved">Approved</span>
    <?php elseif ($status_eot === 'rejected'): ?>
      <span class="sec-status st-rejected">Rejected</span>
    <?php else: ?>
      <span class="sec-status st-pending">Pending</span>
    <?php endif; ?>
		     
  </div>

  <div class="sec-body">
    <!-- FORM -->
    <form method="POST" style="display:flex;flex-direction:column;gap:20px">
      <input type="hidden" name="section" value="B">
      <input type="hidden" name="project_id" value="<?= $project_id ?>">

      <div class="field">
        <label>Status <span style="color:#ef4444">*</span></label>
        <div class="radio-group">
          <label class="radio-opt">
            <input type="radio" name="status_eot" value="approved"
              <?= ($status_eot==='approved')?'checked':'' ?> required>
            Approved
          </label>

          <label class="radio-opt">
            <input type="radio" name="status_eot" value="rejected"
              <?= ($status_eot==='rejected')?'checked':'' ?>>
            Rejected
          </label>
        </div>
      </div>
<?php if (!$lock_c): ?>
      <button type="submit" class="save-btn">Save Status</button>
		<?php endif; ?>

    </form>
 
  </div>
		 
</div>
	
<?php endif; ?>

<!-- C -->
<?php if ($unlock_c): ?>
  <?php if ($status_eot === 'approved'): ?>
	
  <!-- C APPROVED -->
  <div class="sec">
    <div class="sec-header">
      <div class="sec-badge">C</div>
      <div class="sec-title">Extension of Time</div>
      <?php if ($status_eot === 'approved'): ?>
        <span class="sec-status st-verified">✓ Approved</span>
      <?php elseif ($c_done): ?>
        <span class="sec-status st-done">✓ Saved</span>
      <?php else: ?>
        <span class="sec-status st-pending">Submitted</span>
      <?php endif; ?>
    </div>
    <div class="sec-body">
      <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:20px">
        <input type="hidden" name="section" value="C_approved">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <div class="form-grid">
          <div class="field">
            <label>Date Approval <span style="color:#ef4444">*</span></label>
            <input type="date" name="date_approval"
              value="<?= htmlspecialchars($eot['date_approval'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label>EOT Date <span style="color:#ef4444">*</span></label>
            <input type="date" name="eot_date"
              value="<?= htmlspecialchars($eot['eot_date'] ?? '') ?>" required>
          </div>
        </div>
     <div class="field">
  <label>Approval Letter EOT</label>

  <?php if (!empty($eot['approval_letter'])): ?>

    <div class="file-grid">

    <?php 
    $files = explode(',', $eot['approval_letter']);

    foreach ($files as $file): 
        if (!$file) continue;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        // icon ikut file type
        $icon = '📄';
        if ($ext === 'pdf') $icon = '📕';
        elseif (in_array($ext, ['jpg','jpeg','png'])) $icon = '🖼️';
        elseif (in_array($ext, ['doc','docx'])) $icon = '📘';
    ?>

        <a href="download_file.php?file=<?= urlencode(trim($file)) ?>" target="_blank" class="file-card">
            <div class="file-icon"><?= $icon ?></div>
            <div class="file-label">
                <?= htmlspecialchars(mb_strimwidth($file, 0, 40, "...")) ?>
            </div>
        </a>

    <?php endforeach; ?>

    </div>
<?php if (!$lock_c): ?>
    <button type="button" onclick="showReplaceFile('approvalUploadBox', this)" class="replace-btn">
      Replace Files
    </button>
  <?php endif; ?>
  <?php endif; ?>

  <div id="approvalUploadBox" style="<?= !empty($eot['approval_letter']) ? 'display:none;' : '' ?>">
    <div class="file-row">
      <input type="file" name="approval_letter[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
      <span class="opt-tag">Optional</span>
    </div>
  </div>

</div><?php if (!$lock_c): ?>
        <button type="submit" class="save-btn">Save Extension of Time</button>
		    <?php endif; ?>
      </form>
      <?php if ($c_done): ?>
      <div class="sep"></div>
      <div class="verify-bar <?= $c_verified ? 'verified' : '' ?>">
        <div class="vbar-left">
          <div class="vbar-icon"><?= $c_verified ? '✅' : '🔐' ?></div>
          <div>
            <div class="vbar-label">
              <?= $c_verified ? 'Verified by PM/PE' : 'Requires PM/PE Verification' ?>
            </div>
            <div class="vbar-sub">
              <?php if ($c_verified): ?>
                <?= htmlspecialchars($eot['verified_c']) ?> ·
                <?= date('d/m/Y H:i', strtotime($eot['verified_c_at'])) ?>
              <?php else: ?>
                Must be verified by a Project Manager or Project Engineer
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php if (!$c_verified): ?>
          <?php if ($can_verify): ?>
            <form method="POST">
              <input type="hidden" name="section" value="verify_c">
              <input type="hidden" name="project_id" value="<?= $project_id ?>">
              <button type="submit" class="verify-btn">🔐 Verify Now</button>
            </form>
          <?php else: ?>
            <span class="pending-tag">⏳ Awaiting Verification</span>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
		
  <?php elseif ($status_eot === 'rejected'): ?>
  <!-- C REJECTED -->
  <div class="sec">
    <div class="sec-header">
      <div class="sec-badge">C</div>
      <div class="sec-title">Rejected Extension of Time</div>
      <?php if ($c_verified): ?>
        <span class="sec-status st-verified">✓ Verified</span>
      <?php elseif ($cr_done): ?>
        <span class="sec-status st-done">✓ Saved</span>
      <?php else: ?>
        <span class="sec-status st-pending">Submitted</span>
      <?php endif; ?>
    </div>
    <div class="sec-body">
      <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:20px">
        <input type="hidden" name="section" value="C_rejected">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <div class="field">
          <label>Reason <span style="color:#ef4444">*</span></label>
          <textarea name="rejected_reason" rows="4" required
            placeholder="Describe the reason for rejection..."><?= htmlspecialchars($eot['rejected_reason'] ?? '') ?></textarea>
        </div>
        <div class="field">
          <label>Rejection Letter</label>
          <div class="file-row">
            <input type="file" name="rejected_letter"
              accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
            <span class="opt-tag">Optional</span>
          </div>
          <?php if (!empty($eot['rejected_letter'])): ?>
            <div class="existing-file">
              Current:
              <a href="download_file.php?file=<?= urlencode(trim($eot['rejected_letter'])) ?>" target="_blank">
				📄 View file
			</a>
            </div>
          <?php endif; ?>
        </div>
        <button type="submit" class="save-btn">Save Rejection Details</button>
      </form>
      <?php if ($cr_done): ?>
      <div class="sep"></div>
      <div class="verify-bar <?= $c_verified ? 'verified' : '' ?>">
        <div class="vbar-left">
          <div class="vbar-icon"><?= $c_verified ? '✅' : '🔐' ?></div>
          <div>
            <div class="vbar-label">
              <?= $c_verified ? 'Verified by PM/PE' : 'Requires PM/PE Verification' ?>
            </div>
            <div class="vbar-sub">
              <?php if ($c_verified): ?>
                <?= htmlspecialchars($eot['verified_c']) ?> ·
                <?= date('d/m/Y H:i', strtotime($eot['verified_c_at'])) ?>
              <?php else: ?>
                Must be verified by a Project Manager or Project Engineer
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php if (!$c_verified): ?>
          <?php if ($can_verify): ?>
            <form method="POST">
              <input type="hidden" name="section" value="verify_c">
              <input type="hidden" name="project_id" value="<?= $project_id ?>">
              <button type="submit" class="verify-btn">🔐 Verify Now</button>
            </form>
          <?php else: ?>
            <span class="pending-tag">⏳ Awaiting Verification</span>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
<?php endif; ?>

<!-- D -->
<?php if ($unlock_d): ?>
<div class="sec">
  <div class="sec-header">
    <div class="sec-badge">D</div>
    <div class="sec-title">Loss &amp; Expenses — Submission Overall</div>

    <?php if ($d_verified): ?>
      <span class="sec-status st-verified">✓ Verified</span>
    <?php elseif ($status_loss === 'approved'): ?>
      <span class="sec-status st-approved">Applicable</span>
    <?php elseif ($status_loss === 'rejected'): ?>
      <span class="sec-status st-rejected">Not Applicable</span>
    <?php else: ?>
      <span class="sec-status st-pending">Pending</span>
    <?php endif; ?>
  </div>

  <div class="sec-body">
    <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:20px">
      <input type="hidden" name="section" value="D">
      <input type="hidden" name="project_id" value="<?= $project_id ?>">

      <div class="field">
        <label>Status Loss &amp; Expenses <span style="color:#ef4444">*</span></label>
        <div class="radio-group">
          <label class="radio-opt">
            <input type="radio" name="status_loss" value="approved"
              <?= ($status_loss==='approved')?'checked':'' ?> required>
            Applicable
          </label>

          <label class="radio-opt">
            <input type="radio" name="status_loss" value="rejected"
              <?= ($status_loss==='rejected')?'checked':'' ?>>
            Not Applicable
          </label>
        </div>
      </div>

      <div class="form-grid">
        <div class="field">
          <label>Date Submission</label>
          <input type="date" name="loss_overall_date"
            value="<?= htmlspecialchars($eot['loss_overall_date'] ?? '') ?>">
        </div>

        <div class="field">
          <label>Amount Submission (RM)</label>
          <input type="number" name="loss_overall_amount" step="0.01"
            placeholder="0.00"
            value="<?= htmlspecialchars($eot['loss_overall_amount'] ?? '') ?>">
        </div>
      </div>

 <div class="field">
  <label>Letter of Intent for Loss & Expenses Overall</label>

  <?php if (!empty($eot['loss_overall_letter'])): ?>

    <div class="file-grid">

    <?php 
    $files = explode(',', $eot['loss_overall_letter']);

    foreach ($files as $file): 
        if (!$file) continue;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        $icon = '📄';
        if ($ext === 'pdf') $icon = '📕';
        elseif (in_array($ext, ['jpg','jpeg','png'])) $icon = '🖼️';
        elseif (in_array($ext, ['doc','docx'])) $icon = '📘';
    ?>

        <a href="download_file.php?file=<?= urlencode(trim($file)) ?>" target="_blank" class="file-card">
            <div class="file-icon"><?= $icon ?></div>
            <div class="file-label">
                <?= htmlspecialchars(mb_strimwidth($file, 0, 40, "...")) ?>
            </div>
        </a>

    <?php endforeach; ?>

    </div>

    <button type="button" onclick="showReplaceFile('lossUploadBox', this)" class="replace-btn">
      Replace Files
    </button>

  <?php endif; ?>

  <div id="lossUploadBox" style="<?= !empty($eot['loss_overall_letter']) ? 'display:none;' : '' ?>">
    <div class="file-row">
      <input type="file" name="loss_overall_letter[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
      <span class="opt-tag">Optional</span>
    </div>
  </div>

</div>

      <button type="submit" class="save-btn">Save Loss &amp; Expenses</button>
    </form>

    <?php if ($status_loss === 'approved'): ?>
    <div class="sep"></div>

    <div class="verify-bar <?= $d_verified ? 'verified' : '' ?>">
      <div class="vbar-left">
        <div class="vbar-icon"><?= $d_verified ? '✅' : '🔐' ?></div>
        <div>
          <div class="vbar-label">
            <?= $d_verified ? 'Verified by PM/PE' : 'Requires PM/PE Verification' ?>
          </div>

          <div class="vbar-sub">
            <?php if ($d_verified): ?>
              <?= htmlspecialchars($eot['verified_d']) ?> ·
              <?= date('d/m/Y H:i', strtotime($eot['verified_d_at'])) ?>
            <?php else: ?>
              Must be verified by a Project Manager or Project Engineer
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if (!$d_verified): ?>
        <?php if ($can_verify): ?>
          <form method="POST">
            <input type="hidden" name="section" value="verify_d">
            <input type="hidden" name="project_id" value="<?= $project_id ?>">
            <button type="submit" class="verify-btn">🔐 Verify Now</button>
          </form>
        <?php else: ?>
          <span class="pending-tag">⏳ Awaiting Verification</span>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</div>
<?php endif; ?>

<?php if ($unlock_e): ?>
<div class="sec">
  <div class="sec-header">
    <div class="sec-badge">E</div>
    <div class="sec-title">Submission Loss &amp; Expenses — Insurance</div>

    <?php if ($e_verified): ?>
      <span class="sec-status st-verified">✓ Verified</span>
    <?php elseif ($e_done): ?>
      <span class="sec-status st-done">✓ Saved</span>
    <?php else: ?>
      <span class="sec-status st-pending">Submitted Once</span>
    <?php endif; ?>
  </div>

  <div class="sec-body">
    <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:20px">
      <input type="hidden" name="section" value="E">
      <input type="hidden" name="project_id" value="<?= $project_id ?>">

      <div class="form-grid">
        <div class="field">
          <label>Date Submission</label>
          <input type="date" name="loss_insurance_date"
            value="<?= htmlspecialchars($eot['loss_insurance_date'] ?? '') ?>">
        </div>

        <div class="field">
          <label>Estimated Amount (RM)</label>
          <input type="number" name="loss_insurance_amount" step="0.01"
            placeholder="0.00"
            value="<?= htmlspecialchars($eot['loss_insurance_amount'] ?? '') ?>">
        </div>
      </div>

  <div class="field">
  <label>Letter of Intent Loss & Expenses Insurance</label>

  <?php if (!empty($eot['loss_insurance_letter'])): ?>

    <div class="file-grid">

    <?php 
    $files = explode(',', $eot['loss_insurance_letter']);

    foreach ($files as $file): 
        if (!$file) continue;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        $icon = '📄';
        if ($ext === 'pdf') $icon = '📕';
        elseif (in_array($ext, ['jpg','jpeg','png'])) $icon = '🖼️';
        elseif (in_array($ext, ['doc','docx'])) $icon = '📘';
    ?>

        <a href="download_file.php?file=<?= urlencode(trim($file)) ?>" target="_blank" class="file-card">
            <div class="file-icon"><?= $icon ?></div>
            <div class="file-label">
                <?= htmlspecialchars(mb_strimwidth($file, 0, 40, "...")) ?>
            </div>
        </a>

    <?php endforeach; ?>

    </div>

    <button type="button" onclick="showReplaceFile('insuranceUploadBox', this)" class="replace-btn">
      Replace Files
    </button>

  <?php endif; ?>

  <div id="insuranceUploadBox" style="<?= !empty($eot['loss_insurance_letter']) ? 'display:none;' : '' ?>">
    <div class="file-row">
      <input type="file" name="loss_insurance_letter[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
      <span class="opt-tag">Optional</span>
    </div>
  </div>

</div>

      <button type="submit" class="save-btn">Save Insurance Submission</button>
    </form>

    <?php if ($e_done): ?>
    <div class="sep"></div>

    <div class="verify-bar <?= $e_verified ? 'verified' : '' ?>">
      <div class="vbar-left">
        <div class="vbar-icon"><?= $e_verified ? '✅' : '🔐' ?></div>
        <div>
          <div class="vbar-label">
            <?= $e_verified ? 'Verified by PM/PE' : 'Requires PM/PE Verification' ?>
          </div>

          <div class="vbar-sub">
            <?php if ($e_verified): ?>
              <?= htmlspecialchars($eot['verified_e']) ?> ·
              <?= date('d/m/Y H:i', strtotime($eot['verified_e_at'])) ?>
            <?php else: ?>
              Must be verified by a Project Manager or Project Engineer
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if (!$e_verified): ?>
        <?php if ($can_verify): ?>
          <form method="POST">
            <input type="hidden" name="section" value="verify_e">
            <input type="hidden" name="project_id" value="<?= $project_id ?>">
            <button type="submit" class="verify-btn">🔐 Verify Now</button>
          </form>
        <?php else: ?>
          <span class="pending-tag">⏳ Awaiting Verification</span>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</div>
<?php endif; ?>

<!-- DELETE (Admin & QAQC only) -->
<?php if ($can_delete && $has_eot): ?>
<div class="sec delete-sec">
  <div class="sec-header" style="background:#dc2626;">
    <div class="sec-badge">🗑️</div>
    <div class="sec-title">Padam Rekod EOT</div>
  </div>
  <div class="sec-body">
    <p style="font-size:13px;color:#6b7280;">
      Tindakan ini akan memadam rekod EOT ini beserta SEMUA fail yang dimuat naik (LOI, surat kelulusan, surat penolakan, dokumen Loss &amp; Expenses, dan dokumen insurans). Tindakan ini tidak boleh diundur.
    </p>
    <button type="button" class="delete-btn" onclick="confirmDeleteEot()">
      🗑️ Padam Rekod EOT Ini
    </button>
  </div>
</div>

<form id="deleteEotForm" method="POST" style="display:none;">
  <input type="hidden" name="section" value="delete">
  <input type="hidden" name="project_id" value="<?= $project_id ?>">
</form>
<?php endif; ?>

</div>
	<script>
function showReplaceFile(boxId, btn) {
  document.getElementById(boxId).style.display = 'block';
  if (btn) btn.style.display = 'none';
}

function confirmDeleteEot() {
  if (confirm('Padam rekod EOT ini beserta SEMUA fail yang dimuat naik? Tindakan ini tidak boleh diundur.')) {
    document.getElementById('deleteEotForm').submit();
  }
}
</script>
</body>
</html>
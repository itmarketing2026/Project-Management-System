<?php
session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    die("⚠️ You must be logged in to view this page.");
}

$user_id   = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['account_type'] ?? '';

$title = "View Letter Out - Verification Workflow";
$id = intval($_GET['id'] ?? 0);
if (!$id) die("Invalid record.");

$sql = "SELECT l.*, 
               u.full_name AS verifier_display_name, 
               c.full_name AS creator_display_name 
        FROM letteroutpending_cerun l
        LEFT JOIN users u ON l.verifier_id = u.id 
        LEFT JOIN users c ON l.created_by = c.id 
        WHERE l.id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$pending = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pending) die("No record found.");

if (!user_can_access_cerun($db, $user_id, (int)$pending['cerun_id'], $user_role)) {
    die("❌ You do not have permission to view this record.");
}

if (!$pending) die("No record found.");

$detail = null;
if (in_array($pending['status'], ['acknowledged', 'verified_verifier', 'verified_final'])) {
    $stmt2 = $db->prepare("SELECT * FROM letteroutacknowledge_cerun WHERE pending_id = ?");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $detail = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
} elseif ($pending['status'] === 'cancelled') {
    $stmt2 = $db->prepare("SELECT * FROM letteroutcancel_cerun WHERE pending_id = ?");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $detail = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
}

/* WORKFLOW LOGIC */
$can_assigned_verifier_verify = (
    $pending['status'] === 'acknowledged' &&
    $user_id == $pending['verifier_id']
);

$is_final_role = in_array($user_role, ['Admin (QA/QC)', 'HRA']);

// Admin QA/QC can send back at acknowledged stage but CANNOT verify
$can_admin_sendback_at_acknowledged = (
    $pending['status'] === 'acknowledged' &&
    $is_final_role &&
    !$can_assigned_verifier_verify
);

$can_final_verify = ($pending['status'] === 'verified_verifier' && $is_final_role);
$can_edit         = $can_final_verify;

$folderYear = '';
$folderType = '4';
if (!empty($pending['running_number'])) {
    if (preg_match('/(\d{4})/', $pending['running_number'], $matches)) { $folderYear = $matches[0]; }
    $parts  = explode('/', $pending['running_number']);
    $prefix = $parts[0];
    if (strpos($prefix, '6') !== false) { $folderType = '6'; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<title>Project Management System</title>
<style>
:root{ --accent:#007bff; --green:#28a745; --red:#dc3545; --orange:#fd7e14; --max:900px; }
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { overflow-x: hidden; max-width: 100%; }
body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f4f7fa; padding:24px; display:flex; flex-direction:column; align-items:center; }
body > *:not(#rejectModal) { width:100%; max-width:var(--max); }
.back-btn { display:inline-block; margin-bottom:16px; background:var(--accent); color:#fff; padding:10px 18px; border-radius:8px; text-decoration:none; font-weight:bold; transition:0.3s; }
.back-btn:hover { opacity:0.8; transform:translateX(-3px); }
.card { background:#fff; border-radius:16px; padding:24px; margin-bottom:28px; box-shadow:0 6px 18px rgba(0,0,0,.06); border:1px solid #eef2f6; }
.section-title { font-weight:800; color:var(--accent); border-bottom:2px solid #eef2f6; padding-bottom:10px; margin-bottom:18px; display:flex; align-items:center; gap:8px; }
.label { font-weight:700; margin-bottom:6px; color:#64748b; font-size:0.85rem; text-transform:uppercase; }
.value-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; margin-bottom:14px; color:#1e293b; width:100%; display:block; font-family:inherit; font-size:1rem; }
input.value-box { outline:none; transition:border 0.2s; background:#fff; border-color:var(--accent); }
.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
@media(max-width:640px){ .grid-2 { grid-template-columns:1fr; } }
.hero { background:linear-gradient(135deg,#1e293b,#334155); color:#fff; border-radius:18px; padding:30px; margin-bottom:26px; text-align:center; }
.hero h2 { margin:8px 0 0; font-size:32px; letter-spacing:1px; }
.btn-group { display:grid; grid-template-columns:1fr; gap:12px; margin-top:20px; }
.btn { width:100%; color:#fff; padding:16px; font-size:16px; font-weight:700; border:none; border-radius:12px; cursor:pointer; transition:0.2s; display:block; text-align:center; text-decoration:none; }
.btn-blue   { background:var(--accent); }
.btn-green  { background:var(--green); }
.btn-danger { background:var(--red); }
.btn-orange { background:var(--orange); }
.btn:hover { filter:brightness(1.1); transform:translateY(-2px); }
.notice-waiting { background:#fff; border:2px dashed #cbd5e1; color:#64748b; padding:25px; border-radius:16px; text-align:center; }
.notice-green { background:#dcfce7; border:1px solid #22c55e; color:#166534; padding:16px; border-radius:12px; font-weight:bold; text-align:center; }
.rejection-box { background:#fff5f5; border:2px solid var(--red); border-radius:12px; padding:18px 20px; margin-bottom:20px; }
.rejection-label { font-weight:800; color:var(--red); margin-bottom:8px; font-size:0.9rem; text-transform:uppercase; }
.rejection-text { color:#5a0000; font-size:1rem; line-height:1.6; white-space:pre-wrap; }

#rejectModal { display:none; position:fixed; top:0; left:0; right:0; bottom:0; width:100%; height:100%; background:rgba(0,0,0,0.55); z-index:9999; }
#rejectModal.active { display:block; }
.modal-box { position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:16px; padding:28px; width:90%; max-width:480px; box-shadow:0 12px 40px rgba(0,0,0,0.25); z-index:10000; }
.modal-box h3 { margin-bottom:8px; color:var(--red); font-size:1.2rem; }
.modal-box p { font-size:0.9rem; color:#64748b; margin-bottom:14px; }
.modal-box textarea { width:100%; border:1.5px solid #ccc; border-radius:10px; padding:12px; min-height:120px; outline:none; font-family:inherit; font-size:1rem; resize:vertical; }
.modal-actions { display:flex; gap:10px; margin-top:16px; }
.modal-actions button { flex:1; padding:12px; font-weight:700; border:none; border-radius:10px; cursor:pointer; font-size:1rem; }
.modal-cancel  { background:#e2e8f0; color:#334155; }
.modal-confirm { background:var(--red); color:#fff; }
</style>
</head>
<body>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'sentback'): ?>
    <div style="background:#e6ffe6; border:1px solid #00b300; color:#008000; padding:12px 18px; border-radius:10px; margin-bottom:16px; font-weight:bold; width:100%; max-width:var(--max);">
        ✅ Record has been sent back successfully.
    </div>
<?php endif; ?>

<a href="javascript:history.back()" class="back-btn">
    ← Back
</a>

<div class="hero">
    <small style="opacity:0.7; text-transform:uppercase; font-weight:bold;">Running Number</small>
    <h2><?= htmlspecialchars($pending['running_number']) ?></h2>
</div>

<form id="mainForm" action="update_letterout_status_cerun.php" method="POST">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="action" id="formAction" value="">
    <input type="hidden" name="rejection_reason" id="rejectionReasonField" value="">

    <div class="card">
        <div class="section-title">General Information</div>

        <?php if ($pending['status'] === 'rejected' && !empty($pending['rejection_reason'])): ?>
            <div class="rejection-box">
                <div class="rejection-label">Reason for Rejection</div>
                <div class="rejection-text"><?= htmlspecialchars($pending['rejection_reason']) ?></div>
            </div>
        <?php endif; ?>

        <div class="grid-2">
            <div>
                <div class="label">Created by</div>
                <div class="value-box"><?= htmlspecialchars($pending['creator_display_name'] ?? 'Unknown') ?></div>
            </div>
            <div>
                <div class="label">Current Status</div>
                <div class="value-box" style="text-transform:capitalize; font-weight:bold; color:<?= ($pending['status']==='rejected') ? 'var(--red)' : 'var(--accent)' ?>;">
                    <?= str_replace('_', ' ', $pending['status']) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="section-title">A. Request Details</div>
        <div class="label">Title</div>
        <?php if ($can_edit): ?>
            <input type="text" name="title" class="value-box" value="<?= htmlspecialchars($pending['title'] ?? '') ?>" required>
        <?php else: ?>
            <div class="value-box"><?= htmlspecialchars($pending['title'] ?? '-') ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <div>
                <div class="label">Request Date</div>
                <div class="value-box"><?= date('d/m/Y', strtotime($pending['created_at'])) ?></div>
            </div>
            <div>
                <div class="label">Assigned Verifier</div>
                <div class="value-box">
                    <i class="bi bi-person-check me-2 text-success"></i>
                    <?= htmlspecialchars($pending['verifier_display_name'] ?? 'N/A') ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($detail): ?>
    <div class="card">
        <div class="section-title">
            B. <?= $pending['status'] === 'cancelled' ? 'Cancellation Details' : 'Acknowledgement Details' ?>
        </div>
        <?php if ($pending['status'] !== 'cancelled'): ?>
            <div class="grid-2">
                <div>
                    <div class="label">Letter Date</div>
                    <?php if ($can_edit): ?>
                        <input type="date" name="letter_date" class="value-box" value="<?= date('Y-m-d', strtotime($detail['letter_date'])) ?>" required>
                    <?php else: ?>
                        <div class="value-box"><?= date('d/m/Y', strtotime($detail['letter_date'])) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="label">Acknowledge Date</div>
                    <?php if ($can_edit): ?>
                         <input type="date" name="ack_date" class="value-box" value="<?= date('Y-m-d', strtotime($detail['ack_date'])) ?>" required>
                    <?php else: ?>
                        <div class="value-box"><?= date('d/m/Y', strtotime($detail['ack_date'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
    <div>
                <div class="label">Delivery</div>
                <div class="value-box">
                    <i class="bi bi-person-check me-2 text-success"></i>
                    <?= htmlspecialchars($detail['mail_through'] ?? 'N/A') ?>
                </div>
            </div>
          <div class="label">Attached Document</div>

<div class="value-box">

    <?php

    $files = [];

    if (!empty($detail['letter_file'])) {

        $decoded = json_decode($detail['letter_file'], true);

        if (is_array($decoded)) {
            $files = $decoded;
        } else {
            $files = [$detail['letter_file']];
        }
    }

    ?>

    <?php if (!empty($files)): ?>

        <?php foreach ($files as $index => $fileUrl): ?>

            <?php

            /*
             * DB sekarang simpan full URL:
             *
             * https://mggroup.my/projectmanagement/uploads/...
             *
             * Kita tukarkan kepada:
             *
             * uploads/...
             */

            $filePath = trim((string)$fileUrl);

            // Kalau full URL
            if (filter_var($filePath, FILTER_VALIDATE_URL)) {

                $urlPath = parse_url($filePath, PHP_URL_PATH);

                if (!empty($urlPath)) {
                    $filePath = $urlPath;
                }
            }

            // Normalize slash
            $filePath = str_replace('\\', '/', $filePath);

            // Remove leading slash
            $filePath = ltrim($filePath, '/');

            /*
             * Kalau path masih:
             *
             * projectmanagement/uploads/...
             *
             * buang projectmanagement/
             */

            if (stripos($filePath, 'projectmanagement/') === 0) {
                $filePath = substr(
                    $filePath,
                    strlen('projectmanagement/')
                );
            }

            /*
             * Final expected:
             *
             * uploads/letterout_cerun/xxx.pdf
             */

            $safeUrl =
                'download_file.php?file=' .
                urlencode($filePath);

            $displayName = basename($filePath);

            ?>

            <a
                href="<?= htmlspecialchars($safeUrl) ?>"
                target="_blank"
                style="
                    display:block;
                    text-decoration:none;
                    font-weight:bold;
                    color:var(--accent);
                    margin-bottom:8px;
                "
            >
                📄 <?= htmlspecialchars($displayName) ?>
            </a>

        <?php endforeach; ?>

    <?php else: ?>

        <span style="color:#94a3b8; font-style:italic;">
            No document attached
        </span>

    <?php endif; ?>

</div>
        <?php else: ?>
            <div class="label">Reason for Cancellation</div>
            <div class="value-box"><?= htmlspecialchars($detail['cancel_reason'] ?? 'No reason provided.') ?></div>
            <div style="color:var(--red); font-weight:bold; text-align:center; padding:10px;">🛑 This process has been cancelled.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ACTION BUTTONS -->
    <div style="margin-bottom:80px;">
        <div class="btn-group">

            <?php if ($can_assigned_verifier_verify): ?>
                <!-- VERIFIER ACTIONS -->
                <button type="button" class="btn btn-blue" onclick="submitVerify('verify_verifier')">
                    ✅ Verify Information (As Assigned Verifier)
                </button>
                <button type="button" class="btn btn-danger" onclick="openRejectModal()">
                    ↩ Reject / Send Back
                </button>

            <?php elseif ($can_admin_sendback_at_acknowledged): ?>
                <!-- ADMIN QA/QC WATCHING — verifier hasn't acted yet -->
                <div class="notice-waiting" style="margin-bottom:0;">
                    <p style="font-size:1.1rem; margin-bottom:6px;">⏳ <strong>Waiting for Assigned Verifier</strong></p>
                    <p style="opacity:0.8; font-size:14px;">
                        The assigned verifier <strong><?= htmlspecialchars($pending['verifier_display_name'] ?? 'N/A') ?></strong>
                        has not verified yet. You may send this back to pending if needed.
                    </p>
                </div>
                <button type="button" class="btn btn-orange" onclick="submitVerify('sendback_pending')" style="margin-top:12px;">
                    ⬅ Send Back
                </button>

            <?php elseif ($can_final_verify): ?>
                <!-- ADMIN QA/QC FINAL ACTIONS -->
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:15px; border-radius:12px; margin-bottom:15px; color:#166534; font-size:0.9rem; text-align:center;">
                    <strong>Final Approval Mode:</strong> You can edit and verify this document as <strong><?= htmlspecialchars($user_role) ?></strong>.
                </div>
                <button type="button" class="btn btn-green" onclick="submitVerify('verify_final')">
                    ✅ Save &amp; Final Approve
                </button>
                <button type="button" class="btn btn-danger" onclick="openRejectModal()">
                    ↩ Reject / Send Back
                </button>

            <?php elseif ($pending['status'] === 'verified_final'): ?>
                <div class="notice-green">✅ Verification Workflow Complete</div>

            <?php elseif ($pending['status'] === 'rejected'): ?>
                <div class="notice-waiting" style="border:2px solid var(--red); color:var(--red);">
                    <p style="font-size:1.2rem; margin-bottom:5px;">❌ <strong>Rejected</strong></p>
                    <p style="opacity:0.8;">Awaiting creator to resubmit based on the reason above.</p>
                </div>

            <?php elseif ($pending['status'] === 'cancelled'): ?>
                <?php?>

            <?php else:
                $waiting_name = ($pending['status'] === 'acknowledged') ? "Assigned Verifier" : "Admin (QA/QC) or HRA";
            ?>
                <div class="notice-waiting">
                    <p style="font-size:1.2rem; margin-bottom:5px;">⏳ <strong>Waiting Phase</strong></p>
                    <p style="opacity:0.8;">Currently awaiting <strong><?= $waiting_name ?></strong> to verify the next step.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>
</form>

<div id="rejectModal">
    <div class="modal-box">
        <h3>❌ Reason for Rejection</h3>
        <p>Please provide clear feedback for the creator.</p>
        <textarea id="rejectReasonInput" placeholder="Enter rejection reason here..."></textarea>
        <div class="modal-actions">
            <button type="button" class="modal-cancel" onclick="closeRejectModal()">Cancel</button>
            <button type="button" class="modal-confirm" onclick="submitReject()">Confirm Reject</button>
        </div>
    </div>
</div>

<script>
    function submitVerify(action) {
        const labels = {
            'verify_verifier'      : 'verify as assigned verifier',
            'verify_final'         : 'final approve this record',
            'sendback_pending'     : 'send this back to Pending',
        };
        const label = labels[action] || action.replace(/_/g, ' ');
        if (confirm("Are you sure you want to " + label + "?")) {
            document.getElementById('formAction').value = action;
            document.getElementById('mainForm').submit();
        }
    }

    function openRejectModal() {
        document.getElementById('rejectModal').classList.add('active');
        document.getElementById('rejectReasonInput').focus();
    }

    function closeRejectModal() {
        document.getElementById('rejectModal').classList.remove('active');
        document.getElementById('rejectReasonInput').value = '';
    }

    function submitReject() {
        const reason = document.getElementById('rejectReasonInput').value.trim();
        if (!reason) { alert("Please provide a reason for rejection."); return; }
        document.getElementById('formAction').value = 'reject';
        document.getElementById('rejectionReasonField').value = reason;
        document.getElementById('mainForm').submit();
    }

    document.getElementById('rejectModal').addEventListener('click', function(e) {
        if (e.target === this) closeRejectModal();
    });
</script>

</body>
</html>
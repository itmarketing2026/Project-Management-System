<?php
session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    die("⚠️ You must be logged in to view this page.");
}

$user_id = $_SESSION['user_id'];
$cerun_id = intval($_GET['cerun_id'] ?? 0);
$letter_id = intval($_GET['letter_id'] ?? 0);

if (!$cerun_id) {
    die("❌ Invalid region selected.");
}

if (!user_can_access_cerun($db, $user_id, $cerun_id, $_SESSION['account_type'] ?? '')) {
    die("❌ You do not have permission to access this region.");
}

if (!$letter_id) die("Invalid record.");

$prev_id = null;
$next_id = null;

/* PREVIOUS ID */
$stmtPrev = $db->prepare("
    SELECT id 
    FROM letterin_cerun
    WHERE id < ? AND cerun_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$stmtPrev->bind_param("ii", $letter_id, $cerun_id);
$stmtPrev->execute();
$resPrev = $stmtPrev->get_result();

if ($rowPrev = $resPrev->fetch_assoc()) {
    $prev_id = $rowPrev['id'];
}

/* NEXT ID */
$stmtNext = $db->prepare("
    SELECT id 
    FROM letterin_cerun
    WHERE id > ? AND cerun_id = ?
    ORDER BY id ASC
    LIMIT 1
");

$stmtNext->bind_param("ii", $letter_id, $cerun_id);
$stmtNext->execute();
$resNext = $stmtNext->get_result();

if ($rowNext = $resNext->fetch_assoc()) {
    $next_id = $rowNext['id'];
}

if (!$letter_id) die("Invalid record.");

$sql = "SELECT l.*, u.full_name AS created_name 
FROM letterin_cerun l
LEFT JOIN users u ON l.created_by = u.id
WHERE l.id = ? AND l.cerun_id = ?";

$stmt = $db->prepare($sql);
$stmt->bind_param("ii", $letter_id, $cerun_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) die("No record found.");

$stmt_u = $db->prepare("SELECT full_name, account_type FROM users WHERE id = ?");
$stmt_u->bind_param("i", $user_id);
$stmt_u->execute();
$stmt_u->bind_result($logged_in_name, $logged_in_account_type);
$stmt_u->fetch();
$stmt_u->close();

$is_assigned_to_this_letter = (trim($data['verifier'] ?? '') === trim($logged_in_name));

$status = $data['status'] ?? 'pending';

$is_verified = ($status === 'verified');
$is_rejected = ($status === 'rejected');

// Assigned verifier
$can_verify = (!$is_verified && !$is_rejected && $is_assigned_to_this_letter);
$can_delete = in_array(
    trim($logged_in_account_type),
    ['Client Admin', 'Admin (QA/QC)']
);

$can_edit = in_array(
    trim($logged_in_account_type),
    ['Client Admin', 'Admin (QA/QC)']
);


$currentType = $data['type'] ?? ($_GET['type'] ?? '');

$totalQuery = $db->prepare("
    SELECT COUNT(*) as total
    FROM letterin_cerun
    WHERE cerun_id = ?
");

$totalQuery->bind_param("i", $cerun_id);
$totalQuery->execute();

$totalResult = $totalQuery->get_result()->fetch_assoc();
$total_records = $totalResult['total'];

/* CURRENT POSITION */
$positionQuery = $db->prepare("
    SELECT COUNT(*) as position
    FROM letterin_cerun
    WHERE cerun_id = ?
    AND id <= ?
");

$positionQuery->bind_param("ii", $cerun_id, $letter_id);
$positionQuery->execute();

$positionResult = $positionQuery->get_result()->fetch_assoc();
$current_position = $positionResult['position'];
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Project Management System</title>

<style>

:root{
    --accent:#007bff;
    --green:#28a745;
    --red:#dc3545;
    --orange:#fd7e14;
    --max:900px;
}

*{
    box-sizing:border-box;
}

body{
    font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background:#f4f7fa;
    padding:24px;
    display:flex;
    flex-direction:column;
    align-items:center;
    margin:0;
}

body>*:not(#rejectModal){
    width:100%;
    max-width:var(--max);
}

.back-btn{
    display:inline-block;
    margin-bottom:16px;
    background:var(--accent);
    color:#fff;
    padding:10px 18px;
    border-radius:8px;
    text-decoration:none;
    font-weight:bold;
    transition:0.3s;
}

.back-btn:hover{
    opacity:0.8;
    transform:translateX(-3px);
}

.card{
    background:#fff;
    border-radius:16px;
    padding:24px;
    margin-bottom:28px;
    box-shadow:0 6px 18px rgba(0,0,0,.06);
    border:1px solid #eef2f6;
}

.section-title{
    font-weight:800;
    color:var(--accent);
    border-bottom:2px solid #eef2f6;
    padding-bottom:10px;
    margin-bottom:18px;
    display:flex;
    align-items:center;
    gap:8px;
}

.label{
    font-weight:700;
    margin-bottom:6px;
    color:#64748b;
    font-size:0.85rem;
    text-transform:uppercase;
}

.value-box{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:10px;
    padding:12px 14px;
    margin-bottom:14px;
    color:#1e293b;
    width:100%;
    display:block;
    font-family:inherit;
    font-size:1rem;
}

.grid-2{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
}

@media(max-width:640px){
    .grid-2{
        grid-template-columns:1fr;
    }
}

.hero{
    background:linear-gradient(135deg,#1e293b,#334155);
    color:#fff;
    border-radius:18px;
    padding:30px;
    margin-bottom:26px;
    text-align:center;
}

.hero h2{
    margin:8px 0 0;
    font-size:32px;
    letter-spacing:1px;
}

.btn-group{
    display:grid;
    grid-template-columns:1fr;
    gap:12px;
    margin-top:20px;
}

.btn{
    width:100%;
    color:#fff;
    padding:16px;
    font-size:16px;
    font-weight:700;
    border:none;
    border-radius:12px;
    cursor:pointer;
    transition:0.2s;
    display:block;
    text-align:center;
    text-decoration:none;
}

.btn-green{
    background:var(--green);
}

.btn-danger{
    background:var(--red);
}

.btn:hover{
    filter:brightness(1.1);
    transform:translateY(-2px);
}

.notice-waiting{
    background:#fff;
    border:2px dashed #cbd5e1;
    color:#64748b;
    padding:25px;
    border-radius:16px;
    text-align:center;
}

.notice-green{
    background:#dcfce7;
    border:1px solid #22c55e;
    color:#166534;
    padding:16px;
    border-radius:12px;
    font-weight:bold;
    text-align:center;
}

/* Rejection UI */

.rejection-box{
    background:#fff5f5;
    border:2px solid var(--red);
    border-radius:12px;
    padding:18px 20px;
    margin-top:12px;
}

.rejection-label{
    font-weight:800;
    color:var(--red);
    margin-bottom:8px;
    font-size:15px;
}

.rejection-text{
    color:#5a0000;
    font-size:14px;
    line-height:1.6;
    white-space:pre-wrap;
}

#rejectModal{
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100vw;
    height:100vh;
    background:rgba(0,0,0,0.45);
    z-index:999;
}

#rejectModal.active{
    display:flex;
    align-items:center;
    justify-content:center;
}

.modal-box{
    background:#fff;
    border-radius:16px;
    padding:28px;
    width:90%;
    max-width:480px;
    box-shadow:0 12px 40px rgba(0,0,0,0.2);
}

.modal-box h3{
    margin:0 0 16px;
    color:var(--red);
    font-size:20px;
}

.modal-box textarea{
    width:100%;
    border:1.5px solid #ccc;
    border-radius:10px;
    padding:12px;
    min-height:110px;
    outline:none;
}

.modal-actions{
    display:flex;
    gap:10px;
    margin-top:16px;
}

.modal-actions button{
    flex:1;
    padding:12px;
    font-weight:700;
    border:none;
    border-radius:10px;
    cursor:pointer;
}

.modal-cancel{
    background:#e2e8f0;
}

.modal-confirm{
    background:var(--red);
    color:#fff;
}

</style>
</head>

<body>

<div style="
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-bottom:20px;
">

    <!-- BACK -->
    <a href="cerun.php?cerun_id=<?= $cerun_id ?>&type=<?= urlencode($currentType) ?>&letter=in"
       class="back-btn"
       style="margin-bottom:0;">
        ← Back
    </a>

    <!-- CENTER -->
  <div style="
    font-weight:bold;
    color:#64748b;
    font-size:14px;
">
    <?= $current_position ?> / <?= $total_records ?>
</div>

    <!-- NEXT / PREV -->
    <div style="display:flex;gap:10px;">

        <?php if ($prev_id): ?>
            <a href="cerun_detail.php?cerun_id=<?= $cerun_id ?>&letter_id=<?= $prev_id ?>&source=in"
               class="back-btn"
               style="margin-bottom:0;">
               ← Previous
            </a>
        <?php endif; ?>

        <?php if ($next_id): ?>
            <a href="cerun_detail.php?cerun_id=<?= $cerun_id ?>&letter_id=<?= $next_id ?>&source=in"
               class="back-btn"
               style="margin-bottom:0;">
               Next →
            </a>
        <?php endif; ?>

    </div>

</div>

<div class="hero">

    <small style="opacity:0.7;text-transform:uppercase;font-weight:bold;">
        Reference Number
    </small>

    <h2>
        <?= htmlspecialchars($data['ref_number'] ?: 'NO REF') ?>
    </h2>

</div>

<form id="mainForm"
      action="update_letterin_status_cerun.php"
      method="POST">

    <input type="hidden" name="letter_id" value="<?= $letter_id ?>">
    <input type="hidden" name="cerun_id" value="<?= $cerun_id ?>">
    <input type="hidden" name="action" id="formAction" value="">
    <input type="hidden" name="rejection_reason" id="rejectionReasonField" value="">

    <div class="card">

        <div class="section-title">
            General Information
        </div>

        <div class="grid-2">

            <div>
                <div class="label">Created By</div>

                <div class="value-box">
                    <?= htmlspecialchars($data['created_name'] ?? '-') ?>
                </div>
            </div>

            <div>
                <div class="label">Created At</div>

                <div class="value-box">
                    <?= htmlspecialchars($data['created_at'] ?? '-') ?>
                </div>
            </div>

        </div>

        <div class="grid-2">

            <div>
                <div class="label">Status</div>

                <div class="value-box"
                     style="text-transform:capitalize;
                            font-weight:bold;
                            color:<?= $is_rejected ? 'var(--red)' : 'var(--accent)' ?>;">

                    <?= htmlspecialchars($status) ?>

                </div>
            </div>

            <div>
                <div class="label">Verifier Assigned</div>

                <div class="value-box">
                    <?= htmlspecialchars($data['verifier'] ?? 'Project Verifier') ?>
                </div>
            </div>

        </div>

        <?php if ($is_rejected && !empty($data['rejection_reason'])): ?>

            <div class="rejection-box">

                <div class="rejection-label">
                    💬 Reason for Rejection
                </div>

                <div class="rejection-text">
                    <?= htmlspecialchars($data['rejection_reason']) ?>
                </div>

            </div>

        <?php endif; ?>

    </div>

    <div class="card">

        <div class="section-title">
            A. Letter Details
        </div>

      

        <div class="label">Letter Title</div>

<?php if ($can_edit): ?>
   <textarea name="title"
          class="value-box"
          rows="3"><?= htmlspecialchars($data['title']) ?></textarea>
<?php else: ?>
    <div class="value-box">
        <?= htmlspecialchars($data['title']) ?>
    </div>
<?php endif; ?>

        <div class="grid-2">

            <div>
                <div class="label">Letter Date</div>

                <div class="value-box">
                    <?= date('d/m/Y', strtotime($data['letter_date'])) ?>
                </div>
            </div>

            <div>
                <div class="label">Received Date</div>

                <div class="value-box">
                    <?= date('d/m/Y', strtotime($data['receive_date'] ?? $data['letter_date'])) ?>
                </div>
            </div>

        </div>

    </div>

    <div class="card">

        <div class="section-title">
            B. Document Attachment
        </div>

        <div class="label">PDF File</div>

        <div class="value-box">

			<?php
			$files = json_decode($data['file_path'], true);
			?>

			<?php if (!empty($files)): ?>

				<?php foreach ($files as $file): ?>

					<?php

$files = json_decode($data['file_path'] ?? '[]', true);

if (!is_array($files)) {
    $files = [];
}

?>

<?php if (!empty($files)): ?>

    <?php foreach ($files as $file): ?>

        <?php

        $file = trim((string)$file);

        $file = str_replace('\\', '/', $file);

        $file = ltrim($file, '/');

        $safeUrl =
            'download_file.php?file=' .
            urlencode($file);

        $displayName = basename($file);

        ?>

     

        <br>

    <?php endforeach; ?>

<?php else: ?>

    <span style="color:red;">
        No file found.
    </span>

<?php endif; ?>

					<a href="<?= htmlspecialchars($safeUrl) ?>"
					   target="_blank">
						📄 View Document
					</a><br>

				<?php endforeach; ?>

			<?php else: ?>

				<span style="color:red;">
					No file found.
				</span>

			<?php endif; ?>

		</div>

    </div>

<div style="margin-bottom:80px;">
    <div class="btn-group">

        <?php if ($can_verify): ?>

            <button type="button" class="btn btn-green" onclick="submitVerify()">
                Verify Letter Information
            </button>

            <button type="button" class="btn btn-danger" onclick="openRejectModal()">
                Resubmit Required (Reject)
            </button>

        <?php endif; ?>

        <?php if ($can_delete): ?>

            <button type="button" class="btn btn-danger" onclick="submitDelete()">
                Delete Letter
            </button>

        <?php endif; ?>
		
<?php if ($can_edit): ?>

    <button type="button"
            class="btn btn-green"
            onclick="submitUpdate()">
        💾 Save Changes
    </button>

<?php endif; ?>

        <?php if ($is_verified): ?>

            <div class="notice-green">
                ✅ Verification Complete
            </div>

        <?php endif; ?>

        <?php if ($is_rejected): ?>

            <div class="notice-waiting" style="border-color: var(--red); color: var(--red);">
                <p style="font-size: 1.2rem; margin-bottom: 5px;">
                    ❌ <strong>Rejected / Resubmit Required</strong>
                </p>

                <p style="margin:0; opacity: 0.8;">
                    The submitter must correct the document based on the reason above.
                </p>
            </div>

        <?php endif; ?>

        <?php if (
            !$can_verify &&
            !$can_delete &&
            !$is_verified &&
            !$is_rejected
        ): ?>

            <div class="notice-waiting">
                <p style="font-size: 1.2rem; margin-bottom: 5px;">
                    ⏳ <strong>Awaiting Verification</strong>
                </p>

                <p style="margin:0; opacity: 0.8;">
                    Only the assigned verifier for this cerun can complete this step.
                </p>
            </div>

        <?php endif; ?>

    </div>
</div>

</form>

<div id="rejectModal">

    <div class="modal-box">

        <h3>❌ Reason for Rejection</h3>

        <p style="font-size:14px;color:#666;">
            Please provide a reason so the submitter knows what to correct.
        </p>

        <textarea id="rejectReasonInput"
                  placeholder="Enter rejection reason..."></textarea>

        <div class="modal-actions">

            <button class="modal-cancel"
                    onclick="closeRejectModal()">
                Cancel
            </button>

            <button class="modal-confirm"
                    onclick="submitReject()">
                Confirm Reject
            </button>
			
			

        </div>

    </div>

</div>

<script>

function submitVerify() {

    if(confirm("Are you sure you want to verify this letter?")) {

        document.getElementById('formAction').value = 'verify';
        document.getElementById('mainForm').submit();

    }

}

function openRejectModal() {

    document.getElementById('rejectModal')
            .classList.add('active');

    document.getElementById('rejectReasonInput')
            .focus();

}

function closeRejectModal() {

    document.getElementById('rejectModal')
            .classList.remove('active');

    document.getElementById('rejectReasonInput').value = '';

}

function submitReject() {

    const reason =
        document.getElementById('rejectReasonInput')
                .value.trim();

    if (!reason) {

        alert('Please enter a reason for rejection.');

        document.getElementById('rejectReasonInput')
                .focus();

        return;

    }

    document.getElementById('formAction').value = 'reject';

    document.getElementById('rejectionReasonField').value = reason;

    document.getElementById('mainForm').submit();

}

function submitDelete() {

    if(confirm("Are you sure you want to delete this letter?")) {

        document.getElementById('formAction').value = 'delete';

        document.getElementById('mainForm').submit();

    }

}

document.getElementById('rejectModal')
.addEventListener('click', function (e) {

    if (e.target === this) {

        closeRejectModal();

    }

});
	
	function submitUpdate() {

    document.getElementById('formAction').value = 'update';
    document.getElementById('mainForm').submit();

}

</script>

</body>
</html>
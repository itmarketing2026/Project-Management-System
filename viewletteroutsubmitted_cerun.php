<?php
session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    header("Location: login.php");
    exit;
}

$user_id       = (int)($_SESSION['user_id'] ?? 0);
$user_fullname = $_SESSION['full_name'] ?? '';

$id = intval($_GET['id'] ?? 0);
$record = null;

if ($id > 0) {
    $sql = "SELECT l.*, 
                   u.full_name AS verifier_display_name,
                   c.full_name AS creator_name
            FROM letteroutpending_cerun l
            LEFT JOIN users u ON l.verifier_id = u.id
            LEFT JOIN users c ON l.created_by = c.id
            WHERE l.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->fetch_assoc();
    $stmt->close();
}

$db->close();

$is_client_admin = ($_SESSION['account_type'] ?? '') === 'Client Admin';
$is_qaqc = ($_SESSION['account_type'] ?? '') === 'Admin (QA/QC)';

$is_creator = (
    $record &&
    (
        (int)$record['created_by'] === $user_id
        || $is_client_admin
        || $is_qaqc
    )
);

$can_submit = (
    $is_creator &&
    in_array($record['status'], ['submitted','pending','rejected'])
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>View Letter Out</title>

<style>
:root{
  --accent:#007bff;
  --green:#28a745;
  --red:#dc3545;
  --max:900px;
}

body{
  font-family:Arial,sans-serif;
  background:linear-gradient(#f3f4f6,#eef1f5);
  padding:24px;
  display:flex;
  flex-direction:column;
  align-items:center;
}

body>*{
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
}

.hero{
  background:linear-gradient(135deg,#007bff,#0056b3);
  color:#fff;
  border-radius:18px;
  padding:22px;
  margin-bottom:26px;
}
.hero small{
  text-transform:uppercase;
  opacity:.8;
}
.hero h2{
  margin:6px 0 0;
  font-size:26px;
}

.card{
  background:#fff;
  border-radius:16px;
  padding:24px;
  margin-bottom:28px;
  box-shadow:0 6px 18px rgba(0,0,0,.06);
}

.section-title{
  font-weight:800;
  color:var(--accent);
  border-bottom:2px solid var(--accent);
  padding-bottom:6px;
  margin-bottom:18px;
}

.label{
  font-weight:700;
  margin-bottom:6px;
}

.value-box{
  background:#f9f9f9;
  border:1px solid #ddd;
  border-radius:10px;
  padding:12px 14px;
  margin-bottom:14px;
}

.grid-2{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:18px;
}

@media(max-width:640px){
  .grid-2{grid-template-columns:1fr;}
}

select, input, textarea{
  width:100%;
  padding:12px;
  border-radius:10px;
  border:1.5px solid #ccc;
  font-size:15px;
  box-sizing:border-box;
}

select:focus, input:focus, textarea:focus{
  outline:none;
  border-color:var(--accent);
  box-shadow:0 0 0 3px rgba(0,123,255,.15);
}

.field-group{
  margin-bottom:14px;
}

.btn{
  width:100%;
  background:var(--accent);
  color:#fff;
  padding:14px;
  font-size:16px;
  font-weight:800;
  border:none;
  border-radius:12px;
  cursor:pointer;
}

.card-ack{
  border-left:6px solid var(--green);
  background:#f6fffa;
}

.card-cancel{
  border-left:6px solid var(--red);
  background:#fff5f5;
}

.notice{
  background:#fff;
  border:2px dashed #ccc;
  padding:24px;
  border-radius:12px;
  text-align:center;
  color:#666;
}

.hidden{display:none;}
	.btn-danger{
  width:100%;
  background:var(--red);
  color:#fff;
  padding:14px;
  font-size:16px;
  font-weight:800;
  border:none;
  border-radius:12px;
  cursor:pointer;
  margin-top:10px;
}
</style>
</head>

<body>

<?php if($record): ?>

<a href="javascript:history.back()" class="back-btn">← Back</a>

<div class="hero">
  <small>Running Number</small>
  <h2><?= htmlspecialchars($record['running_number']) ?></h2>
  <?php if($record['status'] == 'rejected'): ?>
    <div style="margin-top:10px;background:rgba(255,255,255,0.2);padding:6px 12px;border-radius:8px;display:inline-block;">
      ❌ Rejected
    </div>
  <?php endif; ?>
</div>

<!-- GENERAL INFO -->
<div class="card">
  <div class="section-title">General Information</div>

  <div class="label">Created</div>
  <div class="value-box">
    👤 <?= htmlspecialchars($record['creator_name'] ?? 'Unknown User') ?>
    <br>
    <small style="color:#888;"><?= date('d/m/Y H:i', strtotime($record['created_at'])) ?></small>
  </div>

  <div class="label">Request Date</div>
  <div class="value-box"><?= date('d/m/Y', strtotime($record['request_date'])) ?></div>

  <div class="label">Status</div>
  <div class="value-box"><?= ucfirst(str_replace('_', ' ', $record['status'])) ?></div>
</div>

<?php if ($record['status'] == 'rejected' && !empty($record['rejection_reason'])): ?>
<div class="card card-cancel">
  <div class="section-title">Rejection Reason</div>
  <?= htmlspecialchars($record['rejection_reason']) ?>
</div>
<?php endif; ?>

<!-- LETTER DETAILS -->
<?php if ($can_submit): ?>

<form method="POST" action="save_acknowledge_cancel_cerun.php" enctype="multipart/form-data">
<input type="hidden" name="id" value="<?= $record['id'] ?>">

<div class="card">
  <div class="section-title">Letter Details</div>

  <div class="grid-2">
    <div style="grid-column: 1 / -1;" class="field-group">
      <div class="label">Title <span style="color:red">*</span></div>
      <input type="text" name="title" value="<?= htmlspecialchars($record['title']) ?>" required>
    </div>
    <div class="field-group">
      <div class="label">Agency</div>
      <input type="text" name="agency" value="<?= htmlspecialchars($record['agency']) ?>">
    </div>
    <div class="field-group">
      <div class="label">Verifier</div>
      <select name="verifier_id" id="verifierSelect">
        <option value="">-- Loading... --</option>
      </select>
    </div>
  </div>
</div>

<div class="card">
  <div class="label">Select Action *</div>
  <select id="ackType" name="ackType" required>
    <option value="">-- Select --</option>
    <option value="acknowledge">Acknowledge</option>
    <option value="cancel">Cancel</option>
  </select>
</div>

<div id="ackSection" class="card card-ack hidden">
  <div class="section-title">Acknowledge</div>

  <div class="grid-2">
    <div class="field-group">
      <div class="label">Letter Date</div>
      <input type="date" name="letter_date">
    </div>
    <div class="field-group">
      <div class="label">Acknowledge Date</div>
      <input type="date" name="ack_date">
    </div>
  </div>

  <div class="field-group">
    <div class="label">Delivery Method</div>
    <select name="send_mail">
      <option value="">-- Select --</option>
      <option value="by hand">By Hand</option>
      <option value="email">Email</option>
      <option value="post">Post</option>
    </select>
  </div>

<div class="field-group">
  <div class="label">Upload File <span style="color:red">*</span></div>
  <input 
    type="file" 
    name="letter_file[]" 
    id="letterFile"
    multiple 
    style="height:auto;padding:8px;"
  >
</div>
</div>

<div id="cancelSection" class="card card-cancel hidden">
  <div class="section-title">Cancel</div>
  <div class="field-group">
    <div class="label">Reason</div>
    <textarea name="cancel_reason" rows="4"></textarea>
  </div>
</div>

<button class="btn">Submit</button>
</form>

<?php else: ?>

<!-- READ-ONLY LETTER DETAILS for non-creator / awaiting verification -->
<div class="card">
  <div class="section-title">Letter Details</div>

  <div class="label">Title</div>
  <div class="value-box"><?= htmlspecialchars($record['title']) ?></div>

  <div class="grid-2">
    <div>
      <div class="label">Agency</div>
      <div class="value-box"><?= htmlspecialchars($record['agency']) ?></div>
    </div>
    <div>
      <div class="label">Verifier</div>
      <div class="value-box"><?= htmlspecialchars($record['verifier_display_name'] ?? '—') ?></div>
    </div>
  </div>
</div>

<?php if ($is_creator && !$can_submit): ?>
<div class="notice">
  ⏳ <strong>Awaiting Verification</strong><br>
  <small>This record has been submitted and is currently being reviewed.</small>
</div>
<?php else: ?>
<div class="notice">
  👁️ <strong>View Only</strong><br>
  <small>Only the creator of this record can submit acknowledgement or cancellation.</small>
</div>
<?php endif; ?>

<?php endif; ?>

<?php else: ?>
<p>No record found.</p>
	
<?php endif; ?>
	<?php if ($record && ($is_client_admin || $is_qaqc)): ?>
<form method="POST" action="delete_cerun.php" 
      onsubmit="return confirm('Are you sure you want to DELETE this record? This cannot be undone.');">
  <input type="hidden" name="id" value="<?= $record['id'] ?>">
  <button type="submit" class="btn-danger">🗑️ Delete Record</button>
</form>
<?php endif; ?>

<script>
// Toggle ack/cancel sections
const ackType = document.getElementById('ackType');
const ack     = document.getElementById('ackSection');
const cancel  = document.getElementById('cancelSection');

if (ackType) {
  ackType.addEventListener('change', function () {
    ack.classList.add('hidden');
    cancel.classList.add('hidden');
    if (this.value === 'acknowledge') ack.classList.remove('hidden');
    if (this.value === 'cancel')      cancel.classList.remove('hidden');
  });
}

<?php if ($can_submit): ?>
// Populate verifier dropdown
const verifierSelect    = document.getElementById('verifierSelect');
const currentVerifierId = <?= (int)($record['verifier_id'] ?? 0) ?>;
const cerunId           = <?= (int)($record['cerun_id'] ?? 0) ?>;

fetch('get_verifiers_by_cerun.php?cerun_id=' + cerunId)
  .then(res => res.json())
  .then(data => {
    verifierSelect.innerHTML = '<option value="">-- Select --</option>';
    if (!Array.isArray(data) || data.length === 0) {
      verifierSelect.innerHTML = '<option value="">No verifier available</option>';
      return;
    }
    data.forEach(v => {
      const opt = document.createElement('option');
      opt.value = v.id;
      opt.textContent = v.full_name;
      if (parseInt(v.id) === currentVerifierId) opt.selected = true;
      verifierSelect.appendChild(opt);
    });
  })
  .catch(() => {
    verifierSelect.innerHTML = '<option value="">Unable to load verifiers</option>';
  });
<?php endif; ?>
	
	const letterFile = document.getElementById('letterFile');

if (ackType) {
  ackType.addEventListener('change', function () {

    ack.classList.add('hidden');
    cancel.classList.add('hidden');

    // reset required
    if (letterFile) {
      letterFile.required = false;
    }

    if (this.value === 'acknowledge') {
      ack.classList.remove('hidden');

      // file wajib upload
      if (letterFile) {
        letterFile.required = true;
      }
    }

    if (this.value === 'cancel') {
      cancel.classList.remove('hidden');
    }
  });
}
</script>

</body>
</html>
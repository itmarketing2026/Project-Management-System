<?php
session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

$title = "Add New Archive Letter";

$project_id = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;

if ($project_id <= 0) {
  die("❌ Invalid project.");
}

$projectQuery = $db->prepare("
    SELECT id, project_name, prefix
    FROM project
    WHERE id = ?
    LIMIT 1
");
$projectQuery->bind_param("i", $project_id);
$projectQuery->execute();
$project = $projectQuery->get_result()->fetch_assoc();

if (!$project) {
  die("❌ Project not found.");
}

$title = "Add Archive Letter - " . $project['project_name'];
$createdBy = $_SESSION['full_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Project Management System</title>

<style>

:root {
  --accent: #1a73e8;
}

/* BODY */
body {
  font-family: Arial, sans-serif;
  background: #f3f4f6;
  padding: 24px;
  margin: 0;
}

/* CONTAINER */
.container {
  max-width: 1200px;
  margin: auto;
}

/* TITLE */
h1 {
  text-align: center;
  color: var(--accent);
  font-size: 38px;
  font-weight: 900;
  margin-bottom: 30px;
}

/* CARD */
.form-card {
  width: 100%;
  background: #fff;
  border: 3px solid var(--accent);
  border-radius: 25px;
  padding: 30px;
  margin-bottom: 30px;
}

/* GROUP */
.form-group {
  margin-bottom: 18px;
}

/* LABEL */
.form-group label {
  font-weight: 700;
  font-size: 15px;
  margin-bottom: 6px;
  display: block;
}

/* INPUT */
input, select {
  width: 100%;
  height: 45px;
  padding: 10px;
  border-radius: 10px;
  border: 1px solid #ccc;
  background: #f9f9f9;
  transition: 0.2s;
}

/* FOCUS EFFECT */
input:focus, select:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(26,115,232,0.2);
  background: #fff;
}

/* OTHER BOX */
#other-agency-group {
  background: #f0f7ff;
  border: 2px dashed var(--accent);
  padding: 12px;
  border-radius: 12px;
  display: none;
}

/* BUTTON */
button {
  width: 220px;
  display: block;
  margin: 25px auto 0;
  background: var(--accent);
  color: #fff;
  padding: 14px;
  border: none;
  border-radius: 12px;
  font-weight: bold;
  font-size: 16px;
  cursor: pointer;
  transition: 0.2s;
}

button:hover {
  background: #0c5ed7;
  transform: translateY(-2px);
}
	.back-btn {
  display: inline-block;
  margin-bottom: 15px;
  background: #6c757d;
  color: #fff;
  padding: 10px 16px;
  border-radius: 10px;
  text-decoration: none;
  font-weight: 600;
  transition: 0.2s;
}

.back-btn:hover {
  background: #5a6268;
  transform: translateY(-1px);
}

</style>
</head>

<body>

<div class="container">

<h1><?= htmlspecialchars($title) ?></h1>
<a href="letterout_list.php?project_id=<?= $project_id ?>" class="back-btn">
  ← Back
</a>
<form action="save_letteroutarchive.php" method="post" enctype="multipart/form-data" id="mainForm">

<!-- PROJECT -->
<div class="form-card">

  <div class="form-group">
    <label>Project</label>
    <input type="text" value="<?= htmlspecialchars($project['project_name']) ?>" readonly>
    <input type="hidden" name="project_id" value="<?= $project_id ?>">
  </div>

  <div class="form-group">
    <label>Prefix</label>
    <input type="text" value="<?= htmlspecialchars($project['prefix']) ?>" readonly>
    <input type="hidden" name="form_id" value="<?= htmlspecialchars($project['prefix']) ?>">
  </div>

  <div class="form-group">
    <label>Created By</label>
    <input type="text" value="<?= htmlspecialchars($createdBy) ?>" readonly>
    <input type="hidden" name="created_by" value="<?= htmlspecialchars($createdBy) ?>">
  </div>

</div>

<!-- LETTER -->
<div class="form-card">

  <div class="form-group">
    <label>Running Number</label>
    <input type="text" name="running_number" required>
  </div>

  <div class="form-group">
    <label>Date Letter</label>
    <input type="date" name="request_date" required>
  </div>

  <div class="form-group">
    <label>Title</label>
    <input type="text" name="title" required>
  </div>

  <!-- AGENCY -->
      <div class="form-group">
        <label>Agency</label>
        <select name="agency_select" id="agency" required>
          <option value="">Loading agencies...</option>
        </select>
      </div>

      <!-- OTHER AGENCY -->
      <div class="form-group" id="other-agency-group" style="display:none;">
        <label>Enter Agency Name</label>
        <input type="text" name="agency_other" id="agency_other"
          placeholder="Enter agency name">
      </div>

      <!-- Hidden field that carries the final agency value on submit -->
      <input type="hidden" name="agency" id="agency_final">
<div class="form-group">
  <label>Status Letter</label>
  
  <select name="action_type" id="actionType" required>
    <option value="">-- Select Action --</option>
    <option value="acknowledge">Acknowledge</option>
    <option value="cancel">Cancel</option>
  </select>
</div>

	<div class="form-group" id="fileGroup">
  <label>Upload Letter</label>
  <input type="file" name="archive_file[]" id="fileInput" multiple>
</div>
	
	<div class="form-group" id="cancelReasonGroup" style="display:none;">
  <label>Reason Cancel</label>
  <input type="text" name="cancel_reason" id="cancelReason">
</div>

  <button type="submit">Submit</button>

</div>

</form>

</div>

<script>

const projectId = <?= $project_id ?>;

// =========================
// AGENCY
// =========================

const agencySelect      = document.getElementById('agency');
const otherAgencyGroup  = document.getElementById('other-agency-group');
const agencyOtherInput  = document.getElementById('agency_other');
const agencyFinal       = document.getElementById('agency_final');

// LOAD AGENCY
fetch('get_project_agency.php?project_id=' + projectId)

  .then(res => res.json())

  .then(data => {

    agencySelect.innerHTML = '';

    // kalau takde agency
    if (!Array.isArray(data) || data.length === 0) {

      agencySelect.innerHTML =
        '<option value="">No agency registered</option>';

    } else {

      agencySelect.innerHTML =
        '<option value="">-- Select Agency --</option>';

      data.forEach(a => {

        agencySelect.innerHTML += `
          <option value="${a.agency_name}">
            ${a.agency_name}
          </option>
        `;

      });

    }

    // option OTHER
    agencySelect.innerHTML += `
      <option value="__other__">Other</option>
    `;

  })

  .catch(() => {

    agencySelect.innerHTML =
      '<option value="__other__">Other</option>';

  });

// SHOW / HIDE OTHER AGENCY
agencySelect.addEventListener('change', () => {

  if (agencySelect.value === '__other__') {

    otherAgencyGroup.style.display = 'block';
    agencyOtherInput.required = true;

  } else {

    otherAgencyGroup.style.display = 'none';
    agencyOtherInput.required = false;
    agencyOtherInput.value = '';

  }

});

// =========================
// SUBMIT
// =========================

document.getElementById('mainForm')
.addEventListener('submit', function(e) {

  // OTHER agency
  if (agencySelect.value === '__other__') {

    const customName = agencyOtherInput.value.trim();

    if (!customName) {

      e.preventDefault();

      agencyOtherInput.focus();

      alert('Please enter the agency name.');
      return;
    }

    agencyFinal.value = customName;

    agencySelect.required = false;

  } else {

    agencyFinal.value = agencySelect.value;

  }

});

// =========================
// ACTION TYPE
// =========================

const actionType  = document.getElementById('actionType');

const fileInput   = document.getElementById('fileInput');
const fileGroup   = document.getElementById('fileGroup');

const cancelGroup = document.getElementById('cancelReasonGroup');
const cancelInput = document.getElementById('cancelReason');

actionType.addEventListener('change', () => {

  // ACKNOWLEDGE
  if (actionType.value === 'acknowledge') {

    // SHOW FILE
    fileGroup.style.display = 'block';
    fileInput.required = true;

    // HIDE CANCEL
    cancelGroup.style.display = 'none';
    cancelInput.required = false;
    cancelInput.value = '';

  }

  // CANCEL
  else if (actionType.value === 'cancel') {

    // HIDE FILE
    fileGroup.style.display = 'none';
    fileInput.required = false;
    fileInput.value = '';

    // SHOW REASON
    cancelGroup.style.display = 'block';
    cancelInput.required = true;

  }

  // DEFAULT
  else {

    fileGroup.style.display = 'block';
    cancelGroup.style.display = 'none';

    fileInput.required = false;
    cancelInput.required = false;

    cancelInput.value = '';
    fileInput.value = '';

  }

});

</script>

</body>
</html>
<?php

session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {header("Location: login.php");
  exit;
}

$title = "Add New Pending Letter";

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

$title = "Add Pending Letter - " . $project['project_name'];
$createdBy = $_SESSION['full_name'];

$stmtUser = $db->prepare("SELECT account_type FROM users WHERE id = ? LIMIT 1");
$stmtUser->bind_param("i", $_SESSION['user_id']);
$stmtUser->execute();
$stmtUser->bind_result($account_type);
$stmtUser->fetch();
$stmtUser->close();
$is_management = (trim($_SESSION['account_type'] ?? '') === 'Management');

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Project Management System</title>

  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
    }

    :root {
      --accent: #007bff;
      --max: 700px;
    }

    body {
      font-family: Arial, sans-serif;
      background: #f3f4f6;
      padding: 24px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    h1 {
      color: var(--accent);
      font-weight: 800;
      margin-bottom: 24px;
    }

    .back-btn {
      align-self: flex-start;
      margin-bottom: 16px;
      background: var(--accent);
      color: #fff;
      padding: 10px 18px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: bold;
    }

    .form-card {
      width: 100%;
      max-width: var(--max);
      background: #fff;
      border: 3px solid var(--accent);
      border-radius: 20px;
      padding: 24px;
      margin-bottom: 24px;
    }

    .form-title {
      font-size: 18px;
      font-weight: 700;
      color: var(--accent);
      border-bottom: 2px solid var(--accent);
      margin-bottom: 16px;
      padding-bottom: 6px;
    }

    .form-group {
      margin-bottom: 18px;
    }

    label {
      font-weight: bold;
      display: block;
      margin-bottom: 6px;
    }

    input,
    select,
    textarea {
      width: 100%;
      padding: 10px 12px;
      border: 1.5px solid #ccc;
      border-radius: 8px;
    }

    input, select {
      height: 42px;
    }

    input[readonly] {
      background: #f0f0f0;
    }

    #other-agency-group {
      background: #f0f7ff;
      border: 1.5px dashed var(--accent);
      border-radius: 10px;
      padding: 16px;
      margin-top: -8px;
    }

    #other-agency-group label {
      color: var(--accent);
    }

    button {
      width: 50%;
      margin: 20px auto 0;
      display: block;
      background: var(--accent);
      color: #fff;
      padding: 12px;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: bold;
      cursor: pointer;
    }
  </style>
</head>

<body>

  <a href="letterout_list.php?project_id=<?= $project_id ?>" class="back-btn">← Back</a>
  <h1><?= htmlspecialchars($title) ?></h1>

  <form action="save_letteroutpending.php" method="post" style="width:100%;max-width:700px;" id="mainForm">

    <!-- PROJECT INFO -->
    <div class="form-card">
      <div class="form-group">
        <label>Project</label>
        <input type="text" value="<?= htmlspecialchars($project['project_name']) ?>" readonly>
      </div>

      <div class="form-group">
        <label>Prefix</label>
        <input type="text" value="<?= htmlspecialchars($project['prefix']) ?>" readonly>
        <input type="hidden" name="form_id" value="<?= htmlspecialchars($project['prefix']) ?>">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
      </div>

      <div class="form-group">
        <label>Created By</label>
        <input type="text" value="<?= htmlspecialchars($createdBy) ?>" readonly>
        <input type="hidden" name="created_by" value="<?= htmlspecialchars($createdBy) ?>">
      </div>
    </div>

    <!-- LETTER INFO -->
    <div class="form-card">
      <div class="form-title">A. Letter Out - Request Running Number</div>

      <div class="form-group">
        <label>Date Request Running Number</label>
        <input type="date" name="request_date" value="<?= date('Y-m-d') ?>" readonly>
      </div>

      <div class="form-group" id="backdate-reason-group" style="display:none;">
        <label>Reason for Backdated Request</label>
        <textarea name="backdate_reason" id="backdate_reason" rows="4"
          style="width:100%;padding:10px;border-radius:8px;border:1.5px solid #ccc;"
          placeholder="Please provide justification for requesting a backdated running number"></textarea>
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

      <!-- ← ADDED: Show verifier only for non-management users -->
      <?php if (!$is_management): ?>
      <div class="form-group" id="verifier-group">
        <label>Select Verifier</label>
        <select id="verifier" name="verifier_id" required>
          <option value="">Loading verifiers...</option>
        </select>
      </div>
      <?php else: ?>
        <input type="hidden" name="verifier_id" value="0">
      <?php endif; ?>

      <button type="submit">Request Running Number</button>
    </div>
  </form>

  <script>
    const projectId = <?= $project_id ?>;

    // ← ADDED: Only run verifier fetch for non-management users
    <?php if (!$is_management): ?>
    const verifierSelect = document.getElementById('verifier');
    fetch('get_verifiers_by_project.php?project_id=' + projectId)
      .then(res => res.json())
      .then(data => {
        verifierSelect.innerHTML = '';
        if (!Array.isArray(data) || data.length === 0) {
          verifierSelect.innerHTML = '<option value="">No verifier available</option>';
          return;
        }
        verifierSelect.innerHTML = '<option value="">-- Select Verifier --</option>';
        data.forEach(v => {
          verifierSelect.innerHTML += `<option value="${v.id}">${v.full_name}</option>`;
        });
      })
      .catch(() => {
        verifierSelect.innerHTML = '<option value="">Unable to load verifier</option>';
      });
    <?php endif; ?>

    //  Backdate check 
    const requestDateInput = document.querySelector('input[name="request_date"]');
    const reasonGroup      = document.getElementById('backdate-reason-group');
    const reasonInput      = document.getElementById('backdate_reason');

    requestDateInput.addEventListener('change', () => {
      const selectedDate = new Date(requestDateInput.value);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      selectedDate.setHours(0, 0, 0, 0);

      if (selectedDate < today) {
        reasonGroup.style.display = 'block';
        reasonInput.required = true;
      } else {
        reasonGroup.style.display = 'none';
        reasonInput.required = false;
        reasonInput.value = '';
      }
    });

    //  Agencies 
    const agencySelect      = document.getElementById('agency');
    const otherAgencyGroup  = document.getElementById('other-agency-group');
    const agencyOtherInput  = document.getElementById('agency_other');
    const agencyFinal       = document.getElementById('agency_final');

    fetch('get_project_agency.php?project_id=' + projectId)
      .then(res => res.json())
      .then(data => {
        agencySelect.innerHTML = '';

        if (!Array.isArray(data) || data.length === 0) {
          agencySelect.innerHTML = '<option value="">No agency registered</option>';
        } else {
          agencySelect.innerHTML = '<option value="">-- Select Agency --</option>';
          data.forEach(a => {
            agencySelect.innerHTML +=
              `<option value="${a.agency_name}">${a.agency_name}</option>`;
          });
        }

        agencySelect.innerHTML += '<option value="__other__">Other</option>';
      })
      .catch(() => {
        agencySelect.innerHTML = '<option value="__other__">Other</option>';
      });

    // Show/hide the Other text input
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

    // resolve the final agency value
    document.getElementById('mainForm').addEventListener('submit', function (e) {
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
  </script>

</body>

</html>
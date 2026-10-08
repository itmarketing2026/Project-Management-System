<?php
session_start();
require_once 'include/msqli_connect.php';


// Get project ID from URL
$project_id = intval($_GET['project_id'] ?? 0);
if ($project_id <= 0) {
    die('Invalid project ID.');
}

if (!user_can_access_project($db, $_SESSION['user_id'], $project_id, $_SESSION['account_type'] ?? '')) {
    die("You do not have permission to view this project.");
}

// Fetch project info for display
$stmt = $db->prepare("SELECT project_name FROM project WHERE id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die('Project not found.');
}
$project = $result->fetch_assoc();
$project_name = htmlspecialchars($project['project_name']);
$stmt->close();

$title = "Add Pending Letter - {$project_name}";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $title ?></title>
<style>
:root { --accent:#007bff; --frame-max:700px; }
body { margin:0; font-family:Arial,sans-serif; background:#f3f4f6; padding:24px; display:flex; flex-direction:column; align-items:center; }
h1 { margin:8px 0 24px; font-size:28px; font-weight:800; color:var(--accent); }
.back-btn { align-self:flex-start; margin-bottom:16px; background:var(--accent); color:#fff; padding:10px 18px; border-radius:8px; text-decoration:none; font-weight:bold; }
.back-btn:hover { background:#0056b3; }
.form-card { width:100%; max-width:var(--frame-max); background:#fff; border:3px solid var(--accent); border-radius:20px; padding:24px; margin-bottom:24px; }
.form-title { font-size:18px; font-weight:700; color:var(--accent); margin-bottom:16px; border-bottom:2px solid var(--accent); padding-bottom:6px; }
.form-group { margin-bottom:18px; }
label { display:block; margin-bottom:6px; font-weight:bold; color:#333; }
input, select, textarea { width:100%; box-sizing:border-box; padding:10px 12px; height:42px; border:1.5px solid #ccc; border-radius:8px; font-size:15px; font-family:inherit; transition:border-color 0.2s, box-shadow 0.2s; }
textarea { height:auto; min-height:80px; }
input:focus, select:focus, textarea:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(0,123,255,0.2); }
button { background:var(--accent); color:#fff; padding:12px 20px; border:none; border-radius:8px; font-size:16px; font-weight:bold; cursor:pointer; width:100%; height:45px; margin-top:8px; }
button:hover { background:#0056b3; }
#remarks-group, #backdate-group { opacity:0; max-height:0; overflow:hidden; transition:all 0.5s ease; }
#remarks-group.show, #backdate-group.show { opacity:1; max-height:200px; margin-top:8px; }
</style>
</head>
<body>

<?php include 'include/topbarstaff.php'; ?>

<a href="project_workspace.php?project_id=<?= $project_id ?>" class="back-btn">← Back to <?= $project_name ?> Workspace</a>
<h1><?= $title ?></h1>

<form action="save_letteroutpending.php" method="post" style="width:100%; max-width:var(--frame-max);">
  <input type="hidden" name="project_id" value="<?= $project_id ?>">

  <div class="form-card">
    <div class="form-title">Letter Out – Request Running Number</div>

    <div class="form-group">
      <label for="request_date">Date Request Running Number</label>
      <input type="date" id="request_date" name="request_date" required>
    </div>

    <div class="form-group">
      <label for="title">Title</label>
      <input type="text" id="title" name="title" placeholder="Enter letter title" required>
    </div>

    <div class="form-group">
      <label for="agency">Select Agency</label>
      <select id="agency" name="agency" required>
        <option value="">-- Select Agency --</option>
        <option value="MNDC">MNDC</option>
        <option value="HOPT">HOPT</option>
        <option value="HODT">HODT</option>
        <option value="CKE">CKE</option>
        <option value="OTHER">OTHER</option>
      </select>
    </div>

    <div class="form-group" id="remarks-group">
      <label for="remarks">Remarks (If select Other Agency)</label>
      <textarea id="remarks" name="remarks" rows="3" placeholder="Enter remarks if other agency selected"></textarea>
    </div>

    <div class="form-group" id="backdate-group">
      <label for="backdate_reason">Reason for Backdate</label>
      <textarea id="backdate_reason" name="backdate_reason" rows="2" placeholder="Enter reason for backdate"></textarea>
    </div>

    <div class="form-group">
      <label for="verifier">Select Verifier</label>
      <select id="verifier" name="verifier" required>
        <option value="">-- Select Verifier --</option>
        <option value="Rahmat Hidayat Bin Mohamed">Rahmat Hidayat Bin Mohamed</option>
        <option value="Mohd Faizal Amin Bin Ibrahim">Mohd Faizal Amin Bin Ibrahim</option>
        <option value="Wan Faiz Fikry Bin Muhd Rusli">Wan Faiz Fikry Bin Muhd Rusli</option>
      </select>
    </div>

    <button type="submit">Submit Pending Letter</button>
  </div>
</form>

<script>
const agencySelect = document.getElementById('agency');
const remarksGroup = document.getElementById('remarks-group');
const remarksField = document.getElementById('remarks');
const requestDate = document.getElementById('request_date');
const backdateGroup = document.getElementById('backdate-group');
const backdateReason = document.getElementById('backdate_reason');

agencySelect.addEventListener('change', function() {
  if (this.value === 'OTHER') {
    remarksGroup.classList.add('show');
    remarksField.setAttribute('required','required');
  } else {
    remarksGroup.classList.remove('show');
    remarksField.removeAttribute('required');
  }
});

function checkBackdate() {
    const selected = new Date(requestDate.value);
    const today = new Date();
    selected.setHours(0,0,0,0);
    today.setHours(0,0,0,0);

    if(selected < today){
        backdateGroup.classList.add('show');
        backdateReason.setAttribute('required','required');
    } else {
        backdateGroup.classList.remove('show');
        backdateReason.removeAttribute('required');
    }
}

requestDate.addEventListener('change', checkBackdate);
window.addEventListener('load', checkBackdate);
</script>

</body>
</html>

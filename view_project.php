<?php
// view_project.php — main dynamic revision display

ini_set('display_errors', 1);
error_reporting(E_ALL);

$servername = "localhost";
$username   = "gtpprotege_projectdocument";
$password   = "ProjectDocument";
$dbname     = "gtpprotege_projectdocument";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$project_id = intval($_GET['id'] ?? $_POST['project_id'] ?? 0);
if ($project_id <= 0) {
    die("Missing project parameters.");
}

// Fetch project info
$sql = "SELECT * FROM projects WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check latest revision
$sql = "SELECT MAX(revision_no) AS latest_rev FROM project_revisions WHERE project_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$latest_rev = intval(($stmt->get_result()->fetch_assoc())['latest_rev'] ?? 0);
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Project - <?= htmlspecialchars($project['project_name']); ?></title>
<style>
  body {
    font-family: "Segoe UI", Arial, sans-serif;
    margin: 40px;
    background: #f5f7fb;
    color: #333;
  }

  h1, h2 {
    text-align: center;
    color: #222;
    margin-bottom: 15px;
  }

  /* --- PROJECT DETAILS TABLE --- */
  .details-table {
    width: 80%;
    margin: 0 auto 30px auto;
    border-collapse: collapse;
    background: #ffffff;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    border-radius: 10px;
    overflow: hidden;
  }

  .details-table th, .details-table td {
    border: 1px solid #e2e6ea;
    padding: 10px 12px;
    text-align: left;
  }

  .details-table th {
    background: #007BFF;
    color: white;
    font-weight: 600;
    width: 30%;
  }

  .details-table td {
    background: #fafbff;
  }

  /* --- MAIN PROJECT TABLE (loaded dynamically) --- */
  table {
    width: 95%;
    margin: 25px auto;
    border-collapse: collapse;
    background: #ffffff;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    font-size: 14px;
    border-radius: 10px;
    overflow: hidden;
  }

  th, td {
    border: 1px solid #e0e4eb;
    padding: 10px 8px;
    text-align: center;
    vertical-align: middle;
  }

  th {
    background: #0069d9;
    color: #fff;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 13px;
  }

  tr:nth-child(even) {
    background: #f9fbff;
  }

  tr:hover {
    background: #eef6ff;
  }

  td:nth-child(2), th:nth-child(2) {
    text-align: left;
    width: 280px;
    white-space: normal;
    word-wrap: break-word;
  }

  input[type="text"] {
    width: 100%;
    border: none;
    background: transparent;
    text-align: right;
    font-family: monospace;
    font-size: 13px;
    padding: 4px 6px;
  }

  td:nth-child(2) input[type="text"] {
    text-align: left;
    font-family: Arial, sans-serif;
    font-size: 14px;
  }

  /* --- LABEL BAR --- */
  .label-bar {
    text-align: center;
    font-weight: bold;
    font-size: 16px;
    margin: 15px auto;
    padding: 10px 16px;
    border-radius: 8px;
    width: fit-content;
    display: inline-block;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
  }

  .label-blue {
    color: #0d6efd;
    background: #e7f1ff;
    border: 1px solid #bcd0ff;
  }

  .label-green {
    color: #198754;
    background: #d1e7dd;
    border: 1px solid #a3cfbb;
  }

  .label-gray {
    color: #6c757d;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
  }

  /* --- BUTTONS --- */
  .controls {
    text-align: center;
    margin-bottom: 15px;
  }

  .controls button {
    margin: 0 8px;
    padding: 10px 18px;
    font-size: 15px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s ease-in-out;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
  }

  .controls .add {
    background: #28a745;
    color: #fff;
  }

  .controls .add:hover {
    background: #218838;
    transform: translateY(-1px);
  }

  .controls .original {
    background: #6c757d;
    color: #fff;
  }

  .controls .original:hover {
    background: #5a6268;
    transform: translateY(-1px);
  }

  /* --- REVISION HISTORY TABLE --- */
  .revision-history {
    width: 60%;
    margin: 25px auto;
    border-collapse: collapse;
    background: #ffffff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    border-radius: 10px;
    overflow: hidden;
  }

  .revision-history th {
    background: #007BFF;
    color: #fff;
    padding: 10px;
  }

  .revision-history td {
    padding: 10px;
    border: 1px solid #e2e6ea;
    text-align: center;
  }

  .revision-history tr:nth-child(even) {
    background: #f9fbff;
  }

  .revision-history a {
    color: #007BFF;
    font-weight: bold;
    text-decoration: none;
  }

  .revision-history a:hover {
    text-decoration: underline;
  }

  /* --- BACK LINK --- */
  .back-link {
    text-align: center;
    margin-top: 40px;
  }

  .back-link a {
    text-decoration: none;
    color: #007BFF;
    font-weight: bold;
    font-size: 15px;
  }

  .back-link a:hover {
    text-decoration: underline;
  }
</style>

</head>
<body>

<h1>Project Details</h1>
<table class="details-table">
  <tr><th>Company Name</th><td><?=htmlspecialchars($project['company_name'])?></td></tr>
  <tr><th>Project Name</th><td><?=htmlspecialchars($project['project_name'])?></td></tr>
  <tr><th>Commencement Date</th><td><?=htmlspecialchars($project['commencement_date'])?></td></tr>
  <tr><th>Completion Date</th><td><?=htmlspecialchars($project['completion_date'])?></td></tr>
  <tr><th>Construction Period</th><td><?=htmlspecialchars($project['construction_period'])?> days</td></tr>
  <tr><th>Contract Amount (RM)</th><td>RM <?=number_format($project['contract_amount'],2)?></td></tr>
  <tr><th>EOT No.</th><td><?=htmlspecialchars($project['eot_no'])?></td></tr>
</table>

<h2>Project Cost & Profit Breakdown</h2>

<div class="controls">
  <button id="addRevisionBtn" class="add">➕ Add Revision</button>
  <?php if ($latest_rev > 0): ?>
  <button id="showOriginalBtn" class="original">📜 Show Original Data</button>
  <?php endif; ?>
</div>

<div id="revisionLabel" class="label-bar <?= $latest_rev>0?'label-blue':'label-gray' ?>">
  <?= $latest_rev>0 ? "Showing Latest Revision #$latest_rev" : "Showing Original Project Data" ?>
</div>

<div id="revisionContainer">
  <div style="text-align:center;padding:14px;color:#666;">Loading table...</div>
</div>

<!-- Revision History -->
<h2 style="margin-top:50px;text-align:center;">Revision History</h2>
<?php
$conn = new mysqli($servername, $username, $password, $dbname);
$sql = "SELECT revision_no, MIN(revision_date) AS revision_date FROM project_revisions WHERE project_id=? GROUP BY revision_no ORDER BY revision_no DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$revs = $stmt->get_result();
?>
<?php if($revs->num_rows>0): ?>
  <table style="width:60%;margin:auto;background:white;border-collapse:collapse;">
    <tr><th>Revision No.</th><th>Revision Date</th><th>Action</th></tr>
    <?php while($rev=$revs->fetch_assoc()): ?>
    <tr>
      <td><?=$rev['revision_no']?></td>
      <td><?=$rev['revision_date']?></td>
      <td><a href="#" class="view-rev-link" data-rev="<?=$rev['revision_no']?>" style="color:#007BFF;font-weight:bold;">View Revision</a></td>
    </tr>
    <?php endwhile; ?>
  </table>
<?php else: ?>
  <p style="text-align:center;">No revisions found yet.</p>
<?php endif; ?>
<?php $conn->close(); ?>

<div class="back-link"><a href="projectwip.php">⬅ Back to Project List</a></div>

<script>
function loadTable(revParam, labelText, labelClass){
  const container=document.getElementById('revisionContainer');
  const label=document.getElementById('revisionLabel');
  label.textContent=labelText;
  label.className='label-bar '+labelClass;

  fetch('view_project_table.php?project_id=<?=$project_id?>&rev='+encodeURIComponent(revParam))
    .then(r=>r.text())
    .then(html=>{
      container.innerHTML=html;

      // If loaded HTML contained script tags they were intentionally removed in table file;
      // we rely on the global revision_script.js.php that is included at the bottom of this page.
      // After insertion, run the init function if available to be safe.
      if(typeof initRevisionTable==='function'){
        const editable=(revParam==='new');
        // pass container to initRevisionTable, it will find #revisionTable inside
        initRevisionTable(container, editable);
      }
    })
    .catch(err=>{
      container.innerHTML='<p style="color:red;text-align:center;">Error: '+err.message+'</p>';
    });
}

<?php if ($latest_rev>0): ?>
  loadTable(<?= $latest_rev ?>, 'Showing Latest Revision #<?= $latest_rev ?>', 'label-blue');
<?php else: ?>
  loadTable('original', 'Showing Original Project Data', 'label-gray');
<?php endif; ?>

document.getElementById('addRevisionBtn').onclick=()=>loadTable('new','Creating New Revision','label-green');
document.querySelectorAll('.view-rev-link').forEach(a=>a.onclick=e=>{
  e.preventDefault();
  loadTable(a.dataset.rev,'Showing Revision #'+a.dataset.rev,'label-blue');
});
const showOrig=document.getElementById('showOriginalBtn');
if(showOrig) showOrig.onclick=()=>loadTable('original','Showing Original Project Data','label-gray');
</script>

<?php include 'revision_script.js.php'; ?>
</body>
</html>

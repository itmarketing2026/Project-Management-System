<?php
// view_project_table.php — FINAL (no inline JS; behavior in revision_script.js.php)
ini_set('display_errors', 1);
error_reporting(E_ALL);

$servername = "localhost";
$username   = "gtpprotege_projectdocument";
$password   = "ProjectDocument";
$dbname     = "gtpprotege_projectdocument";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("DB connection failed: " . $conn->connect_error);

$project_id = intval($_GET['project_id'] ?? 0);
if ($project_id <= 0) die("Invalid project ID.");

$rev_param = $_GET['rev'] ?? null;
$rev = ($rev_param !== 'new' && is_numeric($rev_param)) ? intval($rev_param) : null;
$editable = ($rev_param === 'new');

// --- Fetch company labels ---
$sql_labels = "SELECT label_A, label_F, label_G, label_H, label_J, label_K, label_L, label_M, label_N,
                      label_Q, label_R, label_S, label_T, label_U
               FROM project_details
               WHERE project_id = ? LIMIT 1";
$stmt_lbl = $conn->prepare($sql_labels);
$stmt_lbl->bind_param("i", $project_id);
$stmt_lbl->execute();
$labels = $stmt_lbl->get_result()->fetch_assoc() ?: [];
$stmt_lbl->close();

// --- Fetch dataset ---
if ($editable) {
  $sql_latest = "SELECT MAX(revision_no) AS latest_rev FROM project_revisions WHERE project_id = ?";
  $s = $conn->prepare($sql_latest);
  $s->bind_param("i", $project_id);
  $s->execute();
  $latest_rev = intval(($s->get_result()->fetch_assoc())['latest_rev'] ?? 0);
  $s->close();

  if ($latest_rev > 0) {
    $sql = "SELECT * FROM project_revisions WHERE project_id = ? AND revision_no = ? ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $project_id, $latest_rev);
  } else {
    $sql = "SELECT * FROM project_details WHERE project_id = ? ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $project_id);
  }
} elseif ($rev) {
  $sql = "SELECT * FROM project_revisions WHERE project_id = ? AND revision_no = ? ORDER BY id ASC";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $project_id, $rev);
} else {
  $sql = "SELECT * FROM project_details WHERE project_id = ? ORDER BY id ASC";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $project_id);
}

$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($r = $result->fetch_assoc()) $rows[] = $r;
$stmt->close();
$conn->close();
?>

<style>
/* (same CSS as before — omitted here for brevity; include your original CSS from your file) */
.table-container { width:100%; overflow-x:auto; margin:25px auto; background:#fff; border-radius:10px; box-shadow:0 3px 10px rgba(0,0,0,0.08); }
#revisionTable { width:100%; border-collapse:collapse; background:#ffffff; font-size:14px; table-layout:auto; min-width:1800px; }
#revisionTable th, #revisionTable td { border:1px solid #e0e4eb; padding:8px 10px; text-align:center; vertical-align:middle; white-space:nowrap; }
#revisionTable th { background:#0069d9; color:#fff; font-weight:600; text-transform:uppercase; font-size:13px; position:sticky; top:0; z-index:2; }
#revisionTable tr:nth-child(even) { background:#f9fbff; }
#revisionTable tr:hover { background:#eef6ff; }
#revisionTable td:nth-child(2), #revisionTable th:nth-child(2) { text-align:left; white-space:normal; word-break:break-word; min-width:320px; max-width:700px; }
#revisionTable td:nth-child(2) textarea, #revisionTable td:nth-child(2) .desc-view { width:100%; border:none; background:transparent; font-family:Arial, sans-serif; font-size:14px; text-align:left; padding:6px 8px; line-height:1.4; color:#222; box-sizing:border-box; display:block; white-space:normal; word-break:break-word; }
#revisionTable td:nth-child(2) textarea { resize:none; overflow:hidden; border-radius:4px; background-color:#f9fbff; color:#333; }
#companyRow { background-color:#e8f0ff !important; font-weight:bold; text-align:center; border-top:2px solid #007BFF; border-bottom:2px solid #007BFF; }
#revisionTable input[type="text"] { width:100%; min-width:140px; border:none; background:transparent; font-family:"Courier New", monospace; font-size:13px; text-align:right; padding:4px 6px; box-sizing:border-box; color:#222; }
#revisionTable input[readonly] { background-color:#f9fafc; color:#333; }
#revisionTable tfoot tr { background-color:#f3f6fa; font-weight:bold; color:#333; }
#revisionTable tfoot td { border-top:2px solid #007BFF; }
.deleteRowBtn { background:#dc3545; color:white; border:none; border-radius:4px; padding:4px 8px; cursor:pointer; }
.button-container { text-align:center; margin-top:15px; }
.button-container button { border:none; border-radius:6px; padding:10px 16px; font-size:15px; font-weight:500; cursor:pointer; margin:4px; color:white; }
#addRowBtn { background:#28a745; }
#saveRevisionBtn { background:#007bff; }
#addRowBtn:hover { background:#218838; }
#saveRevisionBtn:hover { background:#0056b3; }
#toast {
  visibility: hidden;
  min-width: 300px;
  background-color: #28a745;
  color: #fff;
  text-align: center;
  border-radius: 6px;
  padding: 12px 18px;
  position: fixed;
  z-index: 9999;
  left: 50%;
  bottom: 30px;
  transform: translateX(-50%);
  font-size: 15px;
  opacity: 0;
  transition: opacity 0.5s, bottom 0.5s;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}
#toast.show {
  visibility: visible;
  opacity: 1;
  bottom: 60px;
}

</style>

<div class="table-container">
<form id="revisionForm" method="POST" action="save_revision.php">
  <input type="hidden" name="project_id" id="project_id" value="<?= htmlspecialchars($project_id) ?>">

  <table id="revisionTable">
    <thead>
      <tr>
        <th rowspan="2">Bill</th>
        <th rowspan="2">Description</th>
        <th rowspan="2">Original Contract Sum (A)</th>
        <th rowspan="2">MISB / Others (B)</th>
        <th colspan="3">Project Cost (MISB)</th>
        <th rowspan="2">Awarded Amount (F)</th>
        <th rowspan="2">Original Gross Profit (G = A - B - C)</th>
        <th rowspan="2">Workdone Certified (H)</th>
        <th rowspan="2">Awarded Amount (J)</th>
        <th rowspan="2">Revised Gross Profit (K = H + J)</th>
        <th rowspan="2">Workdone (L)</th>
        <th rowspan="2">Redo / Remaining (M)</th>
        <th rowspan="2">Total Workdone (N = H + L + M)</th>
        <th rowspan="2">Balance (P = A - N)</th>
        <th rowspan="2">Awarded (Q)</th>
        <th rowspan="2">Awarded (R)</th>
        <th rowspan="2">Awarded (S)</th>
        <th rowspan="2">Awarded (T)</th>
        <th rowspan="2">Awarded (U)</th>
        <th rowspan="2">Gross Profit (V = P - Q - R - S - T - U)</th>
        <?php if ($editable): ?><th>Action</th><?php endif; ?>
      </tr>
      <tr>
        <th>Original (C)</th>
        <th>Adjustment (D)</th>
        <th>Revised (E = C + D)</th>
      </tr>
    </thead>

    <tbody>
      <?php
      if (empty($rows)) {
        echo '<tr><td colspan="'.($editable ? 23 : 22).'">No data available.</td></tr>';
      } else {
        echo "<tr id='companyRow'>";
        echo "<td></td><td>Company Name</td>";
        echo "<td>".htmlspecialchars($labels['label_A'] ?? '')."</td><td></td><td></td><td></td><td></td>";
        echo "<td>".htmlspecialchars($labels['label_F'] ?? '')."</td>";
        echo "<td>".htmlspecialchars($labels['label_G'] ?? '')."</td>";
        echo "<td>".htmlspecialchars($labels['label_H'] ?? '')."</td>";
        echo "<td>".htmlspecialchars($labels['label_J'] ?? '')."</td>";
        echo "<td>".htmlspecialchars($labels['label_K'] ?? '')."</td>";
        echo "<td>".htmlspecialchars($labels['label_L'] ?? '')."</td>";
        echo "<td>".htmlspecialchars($labels['label_M'] ?? '')."</td>";
        echo "<td>".htmlspecialchars($labels['label_N'] ?? '')."</td>";
        echo "<td></td>";
        echo "<td>".htmlspecialchars($labels['label_Q'] ?? '')."</td>";
        echo "<td>".htmlspecialchars($labels['label_R'] ?? '')."</td>";
        echo "<td>".htmlspecialchars($labels['label_S'] ?? '')."</td>";
        echo "<td>".htmlspecialchars($labels['label_T'] ?? '')."</td>";
        echo "<td>".htmlspecialchars($labels['label_U'] ?? '')."</td>";
        echo "<td></td>";
        if ($editable) echo "<td></td>";
        echo "</tr>";

        $bill_no = 1;
        foreach ($rows as $row) {
          foreach (['A','B','C','D','F','H','J','L','M','Q','R','S','T','U'] as $f)
            ${$f} = floatval($row[$f] ?? 0);
          $E = $C + $D;
          $G = $A - $B - $C;
          $K = $H + $J;
          $N = $H + $L + $M;
          $P = $A - $N;
          $V = $P - $Q - $R - $S - $T - $U;
          $vals = compact('A','B','C','D','E','F','G','H','J','K','L','M','N','P','Q','R','S','T','U','V');

          $desc_raw = $row['description'] ?? '';
          $desc = htmlspecialchars($desc_raw);

          echo "<tr class='data-row'>";
          echo "<td><input type='text' name='bill_no[]' value='{$bill_no}' readonly></td>";
          if ($editable) {
            echo "<td><textarea name='description[]' rows='1'>{$desc}</textarea></td>";
          } else {
            echo "<td><div class='desc-view'>".nl2br($desc)."</div></td>";
          }

          foreach ($vals as $key => $val) {
            $readonly = (in_array($key, ['E','G','K','N','P','V']) || !$editable) ? 'readonly' : '';
            $cls = ($editable && !$readonly) ? 'calc' : '';
            echo "<td><input type='text' name='{$key}[]' data-field='{$key}' value='".number_format($val, 2)."' class='{$cls}' {$readonly}></td>";
          }
          if ($editable) echo "<td><button type='button' class='deleteRowBtn'>🗑️</button></td>";
          echo "</tr>";
          $bill_no++;
        }
      }
      ?>
    </tbody>

    <tfoot>
      <tr>
        <td colspan="2" style="text-align:right;">TOTAL :</td>
        <?php
        $cols = ['A','B','C','D','E','F','G','H','J','K','L','M','N','P','Q','R','S','T','U','V'];
        foreach ($cols as $col) echo "<td id='total_$col'>0.00</td>";
        if ($editable) echo "<td></td>";
        ?>
      </tr>
    </tfoot>
  </table>

  <?php if ($editable): ?>
  <div class="button-container">
    <button type="button" id="addRowBtn">➕ Add Row</button>
    <button type="button" id="saveRevisionBtn">💾 Save Revision</button>
  </div>
  <?php endif; ?>
</form>
</div>

<div id="toast">✅ Revision saved successfully!</div>

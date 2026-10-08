<?php
// add_revision.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$servername = "localhost";
$username   = "gtpprotege_projectdocument";
$password   = "ProjectDocument";
$dbname     = "gtpprotege_projectdocument";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$project_id = intval($_GET['project_id'] ?? 0);
if ($project_id <= 0) {
  die("Invalid Project ID");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Revision</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 30px; background: #f9f9f9; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
    th { background-color: #eaeaea; }
    button { margin: 5px; padding: 8px 15px; border-radius: 5px; border: none; cursor: pointer; }
    #addRow { background: #28a745; color: #fff; }
    #delRow { background: #dc3545; color: #fff; }
    #saveBtn { background: #007bff; color: #fff; }
  </style>
</head>
<body>

<h2>Add Revision for Project ID: <?= $project_id ?></h2>

<form method="POST" action="save_revision.php">
  <input type="hidden" name="project_id" value="<?= $project_id ?>">

  <table id="revisionTable">
    <thead>
      <tr>
        <th>Bill No</th><th>Description</th>
        <th>A</th><th>B</th><th>C</th><th>D</th><th>E</th><th>F</th><th>G</th><th>H</th>
        <th>J</th><th>K</th><th>L</th><th>M</th><th>N</th><th>P</th><th>Q</th><th>R</th>
        <th>S</th><th>T</th><th>U</th><th>V</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><input name="bill_no[]" required></td>
        <td><input name="description[]" required></td>
        <?php for ($i = 0; $i < 20; $i++): ?>
          <td><input type="number" step="any" name="<?= chr(65 + $i) ?>[]"></td>
        <?php endfor; ?>
      </tr>
    </tbody>
  </table>

  <button type="button" id="addRow">Add Row</button>
  <button type="button" id="delRow">Delete Row</button>
  <button type="submit" id="saveBtn">Save Revision</button>
</form>

<script>
document.getElementById('addRow').addEventListener('click', () => {
  const table = document.getElementById('revisionTable').getElementsByTagName('tbody')[0];
  const newRow = table.rows[0].cloneNode(true);
  newRow.querySelectorAll('input').forEach(i => i.value = '');
  table.appendChild(newRow);
});

document.getElementById('delRow').addEventListener('click', () => {
  const table = document.getElementById('revisionTable').getElementsByTagName('tbody')[0];
  if (table.rows.length > 1) table.deleteRow(table.rows.length - 1);
});
</script>

</body>
</html>

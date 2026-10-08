<?php
// projectwip.php
$title = "Project Work In Progress";

$servername = "localhost";
$username   = "gtpprotege_projectdocument";
$password   = "ProjectDocument";
$dbname     = "gtpprotege_projectdocument";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$valid_columns = ['company_name', 'project_name', 'commencement_date'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $valid_columns) ? $_GET['sort'] : 'commencement_date';
$order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';
$new_order = $order === 'ASC' ? 'desc' : 'asc';

$sql = "SELECT id, company_name, project_name, commencement_date FROM projects ORDER BY $sort $order";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $title ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body {
    font-family: Arial, sans-serif;
    margin: 0;
    background: #f3f4f6;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 24px;
  }

  /* ---------- CONTENT ---------- */
  h1 {
    text-align: center;
    color: #007bff;
    font-size: 32px;
    font-weight: 800;
    margin: 8px 0 16px;
  }
  .button-container {
    text-align: center;
    margin: 20px;
  }
  .button-container button {
    background-color: #28a745;
    color: white;
    border: none;
    padding: 10px 20px;
    cursor: pointer;
    font-size: 16px;
    border-radius: 8px;
    transition: all 0.25s ease;
  }
  .button-container button:hover {
    background-color: #218838;
    transform: scale(1.03);
  }
  .search-box {
    text-align: right;
    margin-bottom: 10px;
    width: 80%;
  }
  .search-box input {
    padding: 8px;
    width: 250px;
    border-radius: 5px;
    border: 1px solid #ccc;
  }
  table {
    width: 80%;
    margin: 0 auto;
    border-collapse: collapse;
    background: #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    border-radius: 12px;
    overflow: hidden;
  }
  th, td {
    padding: 12px 15px;
    border: 1px solid #ccc;
    text-align: left;
  }
  th {
    background-color: #007bff;
    color: white;
    cursor: pointer;
    position: relative;
  }
  th:hover { background-color: #0056b3; }
  th a { color: white; text-decoration: none; display: block; width: 100%; }
  th.sorted-asc a::after { content: "▲"; position: absolute; right: 10px; }
  th.sorted-desc a::after { content: "▼"; position: absolute; right: 10px; }
  tr:nth-child(even) { background-color: #f2f2f2; }
  tr.clickable-row:hover {
    background-color: #d0ebff;
    cursor: pointer;
    transition: background 0.25s ease;
  }
  .no-results { text-align: center; font-style: italic; color: #777; }
</style>

<script>
function filterTable() {
  const input = document.getElementById("searchInput");
  const filter = input.value.toLowerCase();
  const table = document.getElementById("projectTable");
  const rows = table.getElementsByTagName("tr");
  let visibleCount = 0;
  for (let i = 1; i < rows.length; i++) {
    const cells = rows[i].getElementsByTagName("td");
    let match = false;
    for (let j = 0; j < cells.length; j++) {
      if (cells[j].innerText.toLowerCase().includes(filter)) {
        match = true;
        break;
      }
    }
    rows[i].style.display = match ? "" : "none";
    if (match) visibleCount++;
  }
  document.getElementById("noResults").style.display = visibleCount === 0 ? "" : "none";
}
</script>
</head>

<body>

<?php include 'include/topbar.php'; ?>

<h1><?= $title ?></h1>

<div class="button-container">
  <button onclick="window.location.href='addproject.php'">+ Add Project</button>
</div>

<div class="search-box">
  <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search project or company...">
</div>

<table id="projectTable">
  <tr>
    <th class="<?= ($sort == 'company_name' ? 'sorted-' . strtolower($order) : '') ?>">
      <a href="?sort=company_name&order=<?= $new_order ?>">Company Name</a>
    </th>
    <th class="<?= ($sort == 'project_name' ? 'sorted-' . strtolower($order) : '') ?>">
      <a href="?sort=project_name&order=<?= $new_order ?>">Project</a>
    </th>
    <th class="<?= ($sort == 'commencement_date' ? 'sorted-' . strtolower($order) : '') ?>">
      <a href="?sort=commencement_date&order=<?= $new_order ?>">Commencement Date</a>
    </th>
  </tr>

  <?php if ($result && $result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
      <tr class="clickable-row" onclick="window.location.href='view_project.php?id=<?= $row['id'] ?>'">
        <td><?= htmlspecialchars($row['company_name']) ?></td>
        <td><?= htmlspecialchars($row['project_name']) ?></td>
        <td><?= htmlspecialchars($row['commencement_date']) ?></td>
      </tr>
    <?php endwhile; ?>
  <?php else: ?>
    <tr id="noResults"><td colspan="3" class="no-results">No projects found.</td></tr>
  <?php endif; ?>
</table>

</body>
</html>
<?php $conn->close(); ?>

<?php
// get_latest_revision.php — returns latest revision number for a given project

ini_set('display_errors', 1);
error_reporting(E_ALL);

$servername = "localhost";
$username   = "gtpprotege_projectdocument";
$password   = "ProjectDocument";
$dbname     = "gtpprotege_projectdocument";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  header('Content-Type: application/json');
  echo json_encode(['error' => 'DB connection failed']);
  exit;
}

$project_id = intval($_GET['project_id'] ?? 0);
if ($project_id <= 0) {
  header('Content-Type: application/json');
  echo json_encode(['latest_rev' => 0]);
  exit;
}

$sql = "SELECT MAX(revision_no) AS latest_rev FROM project_revisions WHERE project_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$latest_rev = intval($res['latest_rev'] ?? 0);

header('Content-Type: application/json');
echo json_encode(['latest_rev' => $latest_rev]);
$conn->close();
?>

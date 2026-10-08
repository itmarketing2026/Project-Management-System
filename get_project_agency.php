<?php
require_once 'include/msqli_connect.php';

$project_id = intval($_GET['project_id'] ?? 0);

$data = [];

$stmt = $db->prepare("
  SELECT agency_name 
  FROM project_agency 
  WHERE project_id = ?
  ORDER BY agency_name ASC
");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
  $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);

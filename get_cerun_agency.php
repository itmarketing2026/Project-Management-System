<?php
require_once 'include/msqli_connect.php';

$cerun_id = isset($_GET['cerun_id']) ? (int)$_GET['cerun_id'] : 0;
if ($cerun_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $db->prepare("SELECT agency_name FROM cerun_agency WHERE cerun_id = ? ORDER BY agency_name ASC");
$stmt->bind_param("i", $cerun_id);
$stmt->execute();
$result = $stmt->get_result();

$agencies = [];
while ($row = $result->fetch_assoc()) {
    $agencies[] = ['agency_name' => $row['agency_name']];
}

header('Content-Type: application/json');
echo json_encode($agencies);
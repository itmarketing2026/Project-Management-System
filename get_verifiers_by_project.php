<?php
require_once 'include/msqli_connect.php';

header('Content-Type: application/json');

$project_id = intval($_GET['project_id'] ?? 0);

if (!$project_id) {
    echo json_encode([]);
    exit;
}

$sql = "
    SELECT DISTINCT u.id, u.full_name
    FROM users u
    INNER JOIN verifier_project vp ON vp.user_id = u.id
    WHERE vp.project_id = ?
      AND u.verifier = 1
    ORDER BY u.full_name ASC
";

$stmt = $db->prepare($sql);
$stmt->bind_param("i", $project_id);
$stmt->execute();

$result = $stmt->get_result();
$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);

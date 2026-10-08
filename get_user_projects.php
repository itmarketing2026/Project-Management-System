<?php
require_once 'include/msqli_connect.php';
$user_id = $_GET['user_id'];

$sql = "SELECT project_id FROM user_project WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$project_ids = [];
while ($row = $result->fetch_assoc()) {
    $project_ids[] = $row['project_id'];
}

echo json_encode($project_ids);
?>

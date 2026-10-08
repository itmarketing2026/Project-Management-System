<?php
require_once 'include/msqli_connect.php';

$id = $_GET['id'] ?? null;

if ($id) {
    // Start transaction
    $conn->begin_transaction();

    try {
        // 1. Delete related user_project records
        $stmt1 = $conn->prepare("DELETE FROM user_project WHERE project_id = ?");
        $stmt1->bind_param("i", $id);
        $stmt1->execute();
        $stmt1->close();

        // 2. Delete project
        $stmt2 = $conn->prepare("DELETE FROM project WHERE id = ?");
        $stmt2->bind_param("i", $id);
        $stmt2->execute();
        $stmt2->close();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        die("Delete failed");
    }
}

$conn->close();
header("Location: projectlist.php");
exit;

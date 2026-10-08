<?php
require_once 'include/msqli_connect.php';

$id = intval($_POST['id'] ?? 0);

if ($id > 0) {
    // Get record from pending
    $stmt = $db->prepare("SELECT * FROM letter_out_pending_csr WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $record = $res->fetch_assoc();
    $stmt->close();

    if (!$record) die("Record not found.");

    // Insert into submitted
    $insert = $db->prepare("
        INSERT INTO letter_out_submitted_csr
        (running_number, unit, request_date, title, agency, remarks, verifier, submitted_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->bind_param(
        "ssssssss",
        $record['running_number'],
        $record['unit'],
        $record['request_date'],
        $record['title'],
        $record['agency'],
        $record['remarks'],
        $record['verifier'],
        $record['submitted_by']
    );
    $insert->execute();
    $insert->close();

    // Remove from pending
    $delete = $db->prepare("DELETE FROM letter_out_pending_csr WHERE id=?");
    $delete->bind_param("i", $id);
    $delete->execute();
    $delete->close();

    header("Location: letteroutcsr.php?submitted=1");
    exit();
}
?>

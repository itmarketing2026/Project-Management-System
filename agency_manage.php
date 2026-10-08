<?php
require_once 'include/msqli_connect.php';

// ADD agency
if (isset($_POST['add_agency'])) {
    $project_id = $_POST['project_id'];
    $agency_name = trim($_POST['agency_name']);

    // insert agency if not exist
    $stmt = $conn->prepare("INSERT IGNORE INTO agency_master (agency_name) VALUES (?)");
    $stmt->bind_param("s", $agency_name);
    $stmt->execute();

    // get agency id
    $q = $conn->prepare("SELECT id FROM agency_master WHERE agency_name=?");
    $q->bind_param("s", $agency_name);
    $q->execute();
    $result = $q->get_result()->fetch_assoc();
    $agency_id = $result['id'];

    // link to project
    $link = $conn->prepare("
        INSERT IGNORE INTO project_agency (project_id, agency_id)
        VALUES (?, ?)
    ");
    $link->bind_param("ii", $project_id, $agency_id);
    $link->execute();
}

// REMOVE agency from project
if (isset($_GET['remove'])) {
    $project_id = $_GET['project'];
    $agency_id  = $_GET['remove'];

    $conn->query("
        DELETE FROM project_agency
        WHERE project_id='$project_id'
        AND agency_id='$agency_id'
    ");
}

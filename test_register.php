<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'include/msqli_connect.php';

$project_id = 1;
$f_title    = 'Test';
$f_company  = 'Test Co';
$f_contract = 'CON-001';
$f_start    = '2026-01-01';
$f_end      = '2026-12-31';
$f_period   = '12 MONTHS';
$f_sum      = 100000.00;
$sst_filename = null;
$f_pm       = 'Manager Name';
$f_pe       = 'Engineer Name';
$f_con      = 'Consultant Name';
$f_subcons  = 'SubCon A';
$user_id    = 1;

$stmt = $db->prepare("INSERT INTO project_register
    (project_id, title, company, contract_no, site_start, site_end,
     construction_period, contract_sum, sst_document,
     pic_project_manager, pic_project_engineer, pic_consultant,
     subcontractors, created_by, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");

if (!$stmt) {
    die("Prepare failed: " . $db->error);
}

$stmt->bind_param("issssssdssssi",
    $project_id, $f_title, $f_company, $f_contract,
    $f_start, $f_end, $f_period, $f_sum, $sst_filename,
    $f_pm, $f_pe, $f_con, $f_subcons, $user_id
);

if ($stmt->execute()) {
    echo "✅ Insert successful! ID: " . $stmt->insert_id;
} else {
    echo "❌ Execute failed: " . $stmt->error;
}

$stmt->close();
$db->close();
?>
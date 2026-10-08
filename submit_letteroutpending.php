<?php
require_once 'include/msqli_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

$letter_id = intval($_POST['id'] ?? 0);

if ($letter_id <= 0) {
    die("Invalid request.");
}

$stmt_check = $db->prepare("SELECT running_number, project_id FROM letter_out_pending WHERE id=?");
$stmt_check->bind_param("i", $letter_id);
$stmt_check->execute();
$res = $stmt_check->get_result();
$letter = $res->fetch_assoc();
$stmt_check->close();

if (!$letter) {
    die("Letter not found.");
}

if (!empty($letter['running_number'])) {
    header("Location: letterout_list.php?project_id=" . $letter['project_id']);
    exit();
}

$stmt_proj = $db->prepare("SELECT prefix FROM project WHERE id=?");
$stmt_proj->bind_param("i", $letter['project_id']);
$stmt_proj->execute();
$stmt_proj->bind_result($prefix);
$stmt_proj->fetch();
$stmt_proj->close();

if (!$prefix) die("Project prefix not found.");

$year = date('Y');
$prefix_full = $prefix . "/$year/";

$stmt_last = $db->prepare("
    SELECT running_number 
    FROM letter_out_pending 
    WHERE running_number LIKE ? 
    ORDER BY running_number DESC 
    LIMIT 1
");
$like = $prefix_full . "%";
$stmt_last->bind_param("s", $like);
$stmt_last->execute();
$res_last = $stmt_last->get_result();
$last_run = 0;
if ($row = $res_last->fetch_assoc()) {
    $last_run = intval(substr($row['running_number'], -3));
}
$stmt_last->close();

$next_run = $last_run + 1;
$new_running_number = $prefix_full . sprintf("%03d", $next_run);

$stmt_update = $db->prepare("
    UPDATE letter_out_pending 
    SET running_number=?, status='submitted' 
    WHERE id=?
");
$stmt_update->bind_param("si", $new_running_number, $letter_id);
$stmt_update->execute();
$stmt_update->close();

header("Location: letterout_list.php?project_id=" . $letter['project_id']);
exit();
?>

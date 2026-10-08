<?php
require_once 'include/msqli_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request.');
}

$project_id      = (int) ($_POST['project_id']      ?? 0);
$form_id         = trim($_POST['form_id']            ?? '');
$request_date    = trim($_POST['request_date']       ?? '');
$backdate_reason = trim($_POST['backdate_reason']    ?? '');
$title           = trim($_POST['title']              ?? '');
$verifier_id     = (int) ($_POST['verifier_id']      ?? 0);
$agency          = trim($_POST['agency']             ?? '');
$agencyFromSelect = trim($_POST['agency_select']     ?? '');
$createdBy       = trim($_POST['created_by']         ?? '');

if (
    $project_id <= 0 ||
    $form_id === '' ||
    $request_date === '' ||
    $title === '' ||
    $agency === '' ||
    $createdBy === ''
) {
    echo "<script>
        alert('Please fill in all required fields.');
        window.history.back();
    </script>";
    exit;
}

$today = date('Y-m-d');
if ($request_date < $today && $backdate_reason === '') {
    echo "<script>
        alert('Please provide a reason for the backdated request.');
        window.history.back();
    </script>";
    exit;
}

$verifier_name = '';
if ($verifier_id > 0) {
    $stmt_user = $db->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $verifier_id);
    $stmt_user->execute();
    $stmt_user->bind_result($verifier_name);
    $stmt_user->fetch();
    $stmt_user->close();

    if (!$verifier_name) {
        echo "<script>
            alert('Invalid verifier selected.');
            window.history.back();
        </script>";
        exit;
    }
}

/* SAVE NEW AGENCY (if Other) */
if ($agencyFromSelect === '__other__' && $agency !== '') {
    $checkStmt = $db->prepare("
        SELECT id FROM project_agency
        WHERE project_id = ? AND agency_name = ?
        LIMIT 1
    ");
    $checkStmt->bind_param("is", $project_id, $agency);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows === 0) {
        $insertAgency = $db->prepare("
            INSERT INTO project_agency (project_id, agency_name)
            VALUES (?, ?)
        ");
        $insertAgency->bind_param("is", $project_id, $agency);
        $insertAgency->execute();
        $insertAgency->close();
    }

    $checkStmt->close();
}

/* ===============================
   GENERATE RUNNING NUMBER
   Format: PREFIX/YEAR/XXX
   =============================== */
$year = date('Y', strtotime($request_date));
$lastNumber = 0;

$stmtRun = $db->prepare("
    SELECT running_number
    FROM letter_out_pending
    WHERE project_id = ?
      AND YEAR(request_date) = ?
      AND running_number IS NOT NULL
    ORDER BY id DESC
    LIMIT 1
");
$stmtRun->bind_param("ii", $project_id, $year);
$stmtRun->execute();
$stmtRun->bind_result($lastRunning);
$stmtRun->fetch();
$stmtRun->close();

if ($lastRunning) {
    $parts = explode('/', $lastRunning);
    $lastNumber = (int) end($parts);
}

// Fetch rn_start from project
$rnStart = 1;
$stmtRnStart = $db->prepare("SELECT rn_start FROM project WHERE id = ? LIMIT 1");
$stmtRnStart->bind_param("i", $project_id);
$stmtRnStart->execute();
$stmtRnStart->bind_result($rnStart);
$stmtRnStart->fetch();
$stmtRnStart->close();

$newNumber      = max((int)$rnStart, $lastNumber + 1);
$running_number = sprintf("%s/%s/%03d", $form_id, $year, $newNumber);

/* ===============================
   INSERT LETTER
   =============================== */
$sql = "
    INSERT INTO letter_out_pending
    (
        project_id,
        form_id,
        request_date,
        title,
        verifier_id,
        verifier,
        agency,
        running_number,
        backdate_reason,
        status,
        created_by
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', ?)
";

$stmt = $db->prepare($sql);
if (!$stmt) {
    die('Prepare failed: ' . $db->error);
}

$stmt->bind_param(
    "isssisssss",
    $project_id,
    $form_id,
    $request_date,
    $title,
    $verifier_id,
    $verifier_name,
    $agency,
    $running_number,
    $backdate_reason,
    $createdBy
);

if ($stmt->execute()) {
    echo "<script>
        alert('Letter Out saved successfully.');
        window.location.href = 'letterout_list.php?project_id={$project_id}';
    </script>";
} else {
    echo "<script>
        alert('Failed to save letter.');
        window.history.back();
    </script>";
}

$stmt->close();
$db->close();
?>
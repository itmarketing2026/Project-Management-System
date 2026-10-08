<?php
session_start();
require_once 'include/msqli_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method.');
}

//  ERROR PAGE FUNCTION 
function showError($message, $back_url = null) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Error</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 24px; }
            .error-box { background: #fff; border: 2px solid #dc3545; border-radius: 20px; padding: 40px; max-width: 500px; width: 100%; text-align: center; box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
            .error-icon { font-size: 56px; margin-bottom: 16px; }
            .error-title { color: #dc3545; font-size: 22px; font-weight: 800; margin-bottom: 12px; }
            .error-msg { color: #555; font-size: 15px; line-height: 1.6; margin-bottom: 28px; }
            .btn-back { display: inline-block; background: #007bff; color: #fff; padding: 12px 28px; border-radius: 10px; text-decoration: none; font-weight: bold; font-size: 16px; transition: 0.2s; }
            .btn-back:hover { background: #0056b3; transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class="error-box">
            <div class="error-icon">⚠️</div>
            <div class="error-title">Error</div>
            <div class="error-msg"><?= $message ?></div>
            <?php if ($back_url): ?>
                <a href="<?= htmlspecialchars($back_url) ?>" class="btn-back">← Go Back</a>
            <?php else: ?>
                <a href="javascript:history.back()" class="btn-back">← Go Back</a>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

//  CHECK IF POST IS EMPTY DUE TO FILE SIZE LIMIT 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)) {
    $max_size = ini_get('post_max_size');
    showError("File is too large. Maximum allowed size is <strong>{$max_size}</strong>.");
}

/* 1. COLLECT DATA */
$cerun_id        = (int) ($_POST['cerun_id'] ?? 0);
$type            = trim($_POST['type'] ?? '');
$year_selected   = trim($_POST['year_selected'] ?? '');
$request_date    = trim($_POST['request_date'] ?? '');
$backdate_reason = trim($_POST['backdate_reason'] ?? '');
$title           = trim($_POST['title'] ?? '');
$verifier_id     = (int) ($_POST['verifier_id'] ?? 0);
$agency          = trim($_POST['agency'] ?? '');
$submitted_by_id = (int) $_SESSION['user_id'];
$created_by      = $_SESSION['user_id'] ?? '';

$back_url = "javascript:history.back()";

/* 2. VALIDATION */
if ($cerun_id <= 0 || $request_date === '' || $title === '' || $verifier_id <= 0 || $agency === '') {
    showError("Please fill in all required fields.", $back_url);
}

$checkAgency = $db->prepare("SELECT id FROM cerun_agency WHERE cerun_id = ? AND agency_name = ?");
$checkAgency->bind_param("is", $cerun_id, $agency);
$checkAgency->execute();
$checkAgency->store_result();

if ($checkAgency->num_rows === 0) {
    $insertAgency = $db->prepare("INSERT INTO cerun_agency (cerun_id, agency_name) VALUES (?, ?)");
    $insertAgency->bind_param("is", $cerun_id, $agency);
    $insertAgency->execute();
    $insertAgency->close();
}
$checkAgency->close();

/* 3. FETCH REGION & VERIFIER NAMES */
$region_name = '';
$region_symbol_custom = '';
$stmt_c = $db->prepare("SELECT region, symbol FROM cerun WHERE id = ?");
$stmt_c->bind_param("i", $cerun_id);
$stmt_c->execute();
$stmt_c->bind_result($region_name, $region_symbol_custom);
$stmt_c->fetch();
$stmt_c->close();

$verifier_name = '';
$stmt_u = $db->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt_u->bind_param("i", $verifier_id);
$stmt_u->execute();
$stmt_u->bind_result($verifier_name);
$stmt_u->fetch();
$stmt_u->close();

if (!$verifier_name) {
    showError("Invalid verifier selected.", $back_url);
}

/* 4. GENERATE RUNNING NUMBER */
$region_symbol = !empty($region_symbol_custom) 
    ? strtoupper(trim($region_symbol_custom)) 
    : strtoupper(substr(str_replace(' ', '', $region_name), 0, 3));
$request_year  = date('Y', strtotime($request_date));
$prefix        = ($type == '6') ? "CERUN/KKR/KJ6/PUSB" : "CERUN/KKR/PUSB";

$is_hq = in_array($region_symbol, ['CKC', 'KL']);
/*$folder_marker = $is_hq ? $region_symbol : $region_symbol . $year_selected;
$search_query  = $is_hq 
    ? "%/" . $folder_marker . "/%" 
    : "%/" . $folder_marker . "/%"; 

$folder_marker = $is_hq 
    ? $region_symbol . '/' . $request_year
    : $region_symbol . $year_selected;

$search_query = "%/" . $folder_marker . "/%"; */

if ($is_hq) {

    $folder_marker = $region_symbol . '/' . $request_year;

    // cari semua HQ lama & baru
    $search_query = "%/" . $region_symbol . "/%";
	$rn_year = $request_year;

} else {

    $folder_marker = $region_symbol . $year_selected;
    $search_query  = "%/" . $folder_marker . "/%";
	$rn_year = $year_selected;
}


// Load rn_start 
$rn_start = 1;
$stmtProj = $db->prepare("SELECT rn_start FROM cerun_rn_settings WHERE cerun_id = ? AND type = ? AND year = ? LIMIT 1");
$stmtProj->bind_param("iss", $cerun_id, $type, $rn_year);
$stmtProj->execute();
$stmtProj->bind_result($rn_start);
$stmtProj->fetch();
$stmtProj->close();

$rn_start = max(1, (int)$rn_start);

$stmtRun = $db->prepare("
    SELECT running_number
    FROM letteroutpending_cerun
    WHERE cerun_id = ?
      AND running_number LIKE ?
    ORDER BY id DESC LIMIT 1
");
$stmtRun->bind_param("is", $cerun_id, $search_query);
$stmtRun->execute();
$stmtRun->bind_result($lastRunning);
$stmtRun->fetch();
$stmtRun->close();

if ($lastRunning) {
    $parts      = explode('/', $lastRunning);
    $lastNumber = (int)end($parts);
    $newNumber  = $rn_start > $lastNumber ? $rn_start : $lastNumber + 1;
} else {
    $newNumber = $rn_start;
}

   $running_number = $is_hq
 ? sprintf("%s/%s/%03d", $prefix, $folder_marker, $newNumber)
    : sprintf("%s/%s/%s/%03d", $prefix, $folder_marker, $request_year, $newNumber);
	

/* 5. INSERT INTO DATABASE */
$sql = "INSERT INTO letteroutpending_cerun 
        (cerun_id, request_date, title, verifier_id, agency, submitted_by, running_number, backdate_reason, created_by, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')";

$stmt = $db->prepare($sql);
if (!$stmt) {
    showError("Database error: " . $db->error);
}

$stmt->bind_param(
    "issisisss",
    $cerun_id,
    $request_date,
    $title,
    $verifier_id,
    $agency,
    $submitted_by_id,
    $running_number,
    $backdate_reason,
    $created_by
);

if ($stmt->execute()) {
    echo "<script>
        alert('Letter Out saved successfully.\\nNumber: $running_number');
        window.location.href = 'cerun.php?cerun_id=$cerun_id&type=$type&simple=1&upload=success';
    </script>";
} else {
    showError("Failed to save letter: " . $db->error);
}

$stmt->close();
$db->close();
?>
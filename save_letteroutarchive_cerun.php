<?php
session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method.');
}

// ---------- ERROR PAGE FUNCTION ----------
function showError($message, $back_url = null) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Upload Error</title>
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
            <div class="error-title">Upload Failed</div>
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

// ---------- CHECK IF POST IS EMPTY DUE TO FILE SIZE LIMIT ----------
if (empty($_POST)) {
    $max_size = ini_get('post_max_size');
    showError("File is too large. Maximum allowed size is <strong>{$max_size}</strong>.<br>Please upload a smaller file or contact your administrator to increase the limit.");
}

// ---------- COLLECT DATA ----------
$cerun_id       = intval($_POST['cerun_id'] ?? 0);
$type           = $_POST['type'] ?? '';
$year_selected  = $_POST['year_selected'] ?? '';
$running_number = trim($_POST['running_number'] ?? '');
$request_date   = $_POST['request_date'] ?? '';
$title          = trim($_POST['title'] ?? '');
$agency         = $_POST['agency'] ?? '';
$created_by     = $_POST['created_by'] ?? 'Staff';
$project_year = intval($_POST['year_selected'] ?? 0);

$back_url = "javascript:history.back()";

//  validation 
if ($cerun_id <= 0 || empty($running_number) || empty($title)) {
    showError("Missing required fields. Please fill in all required fields.", $back_url);
}

// other agency
if (!empty($agency) && $cerun_id > 0) {
    // Check if this specific agency name already exists for this cerun_id
    $checkAgency = $db->prepare("SELECT id FROM cerun_agency WHERE cerun_id = ? AND agency_name = ? LIMIT 1");
    $checkAgency->bind_param("is", $cerun_id, $agency);
    $checkAgency->execute();
    $result = $checkAgency->get_result();

    if ($result->num_rows === 0) {
        // Not found, so insert it as a new agency option for this cerun
        $insertAgency = $db->prepare("INSERT INTO cerun_agency (cerun_id, agency_name) VALUES (?, ?)");
        $insertAgency->bind_param("is", $cerun_id, $agency);
        $insertAgency->execute();
        $insertAgency->close();
    }
    $checkAgency->close();
}

if (!empty($year_selected) && strpos($running_number, $year_selected) === false) {
    $running_number = $running_number . " [" . $year_selected . "]";
}

$file_path = null;

if (!empty($_FILES['archive_file']['name'][0])) {
    $upload_dir = __DIR__ . "/uploads/cerun_archives/";

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $upload_errors = [
        UPLOAD_ERR_INI_SIZE   => "File too large. Maximum size allowed by server is <strong>" . ini_get('upload_max_filesize') . "</strong>.",
        UPLOAD_ERR_FORM_SIZE  => "File too large. Maximum size allowed by form exceeded.",
        UPLOAD_ERR_PARTIAL    => "File was only partially uploaded. Please try again.",
        UPLOAD_ERR_NO_TMP_DIR => "Server error: Missing temporary folder.",
        UPLOAD_ERR_CANT_WRITE => "Server error: Failed to write file to disk.",
        UPLOAD_ERR_EXTENSION  => "Server error: File upload stopped by extension.",
    ];

    $file_paths = [];

    foreach ($_FILES['archive_file']['error'] as $key => $error) {
        if ($error === UPLOAD_ERR_OK) {
            $original_name = basename($_FILES['archive_file']['name'][$key]);
            $ext           = pathinfo($original_name, PATHINFO_EXTENSION);
            $name_only     = pathinfo($original_name, PATHINFO_FILENAME);

            // Bersihkan nama asal - buang karakter yang tak selamat untuk nama fail
            $clean_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $name_only);
            $clean_name = trim($clean_name, '_');
            if ($clean_name === '') {
                $clean_name = 'file';
            }

            $timestamp    = date('Ymd_His'); // contoh: 20260708_143025
            $new_filename = $clean_name . "_" . $timestamp . "." . strtolower($ext);
            $target_file  = $upload_dir . $new_filename;

            // Elak overwrite jika nama+tarikh+masa sama (upload dalam saat yang sama)
            $counter = 1;
            while (file_exists($target_file)) {
                $new_filename = $clean_name . "_" . $timestamp . "_" . $counter . "." . strtolower($ext);
                $target_file  = $upload_dir . $new_filename;
                $counter++;
            }

            if (move_uploaded_file($_FILES['archive_file']['tmp_name'][$key], $target_file)) {
                $file_paths[] = "https://mggroup.my/projectmanagement/uploads/cerun_archives/" . $new_filename;
            } else {
                showError("Failed to upload file: <strong>" . htmlspecialchars($original_name) . "</strong>. Please try again.", $back_url);
            }
        } elseif ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        } else {
            $error_msg = $upload_errors[$error] ?? "Unknown upload error (code: {$error})";
            showError($error_msg, $back_url);
        }
    }

    if (!empty($file_paths)) {
        $file_path = json_encode($file_paths);
    }
}

// ---------- SAVE TO DATABASE ----------
$sql = "INSERT INTO letteroutarchive_cerun 
        (cerun_id, running_number, request_date, title, agency, archive_file, created_by, status,project_year) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'archived',?)";

$stmt = $db->prepare($sql);
if (!$stmt) {
    showError("Database error: " . $db->error, $back_url);
}

$stmt->bind_param(
    "issssssi",
    $cerun_id,
    $running_number,
    $request_date,
    $title,
    $agency,
    $file_path,
    $created_by,
	$project_year
);

if ($stmt->execute()) {
    header("Location: cerun.php?cerun_id=$cerun_id&type=$type&year=$year_selected&archived=1");
    exit;
} else {
    showError("Database error: " . $stmt->error, $back_url);
}

$stmt->close();
$db->close();
?>
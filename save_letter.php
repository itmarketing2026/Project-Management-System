<?php
session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

function showError($message, $back_url = null) { ?>
    <!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Upload Error</title>
    <style>* { box-sizing: border-box; margin: 0; padding: 0; } body { font-family: Arial, sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 24px; } .error-box { background: #fff; border: 2px solid #dc3545; border-radius: 20px; padding: 40px; max-width: 500px; width: 100%; text-align: center; } .error-title { color: #dc3545; font-size: 22px; font-weight: 800; margin-bottom: 12px; } .error-msg { color: #555; font-size: 15px; line-height: 1.6; margin-bottom: 28px; } .btn-back { display: inline-block; background: #007bff; color: #fff; padding: 12px 28px; border-radius: 10px; text-decoration: none; font-weight: bold; }</style>
    </head><body><div class="error-box"><div class="error-title">Upload Failed</div>
    <div class="error-msg"><?= $message ?></div>
    <?php if ($back_url): ?><a href="<?= htmlspecialchars($back_url) ?>" class="btn-back">← Go Back</a>
    <?php else: ?><a href="javascript:history.back()" class="btn-back">← Go Back</a><?php endif; ?>
    </div></body></html>
<?php exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)) {
    $max_size = ini_get('post_max_size');
    showError("File is too large. Maximum allowed size is <strong>{$max_size}</strong>.");
}

// ---------- COLLECT FORM DATA ----------
$title            = trim($_POST['title'] ?? '');
$received_date    = trim($_POST['received_date'] ?? '');
$status           = ($received_date < '2026-04-13') ? 'verified' : 'pending';
$reference_number = trim($_POST['reference_number'] ?? '');
$letter_date      = trim($_POST['letter_date'] ?? '');
$letter_from      = trim($_POST['letter_from'] ?? '');
$receive_mail     = trim($_POST['receive_mail'] ?? '');
$remarks          = trim($_POST['remarks'] ?? '');
$project_id       = intval($_POST['project_id'] ?? 0);
$select_verifier  = intval($_POST['select_verifier'] ?? 0);
$created_by       = $_SESSION['full_name'] ?? '';
$created_by_id    = (int)($_SESSION['user_id'] ?? 0);
$back_url         = "addletterin.php?project_id={$project_id}";

// ---------- VALIDATION ----------
$missing = [];
if (!$title)            $missing[] = 'title';
if (!$received_date)    $missing[] = 'received_date';
if (!$reference_number) $missing[] = 'reference_number';
if (!$letter_date)      $missing[] = 'letter_date';
if (!$letter_from)      $missing[] = 'letter_from';
if (!$receive_mail)     $missing[] = 'receive_mail';
if (!$project_id)       $missing[] = 'project_id';
if (!$select_verifier)  $missing[] = 'select_verifier';
if (!empty($missing))   showError("Missing required fields: <strong>" . implode(', ', $missing) . "</strong>", $back_url);

// ---------- UPLOAD HELPER ----------
function handleUpload($fileKey, $project_id, $back_url) {
    if (empty($_FILES[$fileKey]['name'][0])) return null;
    $base_dir   = __DIR__ . "/uploads/";
    $folder     = "project_" . $project_id . "/in/";
    $upload_dir = $base_dir . $folder;
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    $paths = [];
    foreach ($_FILES[$fileKey]['error'] as $key => $error) {
        if ($error === UPLOAD_ERR_OK) {
            $original_name = basename($_FILES[$fileKey]['name'][$key]);
            $extension     = pathinfo($original_name, PATHINFO_EXTENSION);
            $extension     = $extension ? '.' . $extension : '';
            $new_filename  = "doc_" . uniqid() . $extension;
            $file_path     = $upload_dir . $new_filename;
            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'][$key], $file_path)) {
                $paths[] = [
                    'path' => $folder . $new_filename,   // used for preview lookup
                    'name' => $original_name              // used for display
                ];
            } else {
                showError("Failed to upload file: " . htmlspecialchars($original_name), $back_url);
            }
        } elseif ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        } else {
            showError("Upload error code: {$error}", $back_url);
        }
    }
    return json_encode($paths);
}

// ---------- HANDLE BOTH UPLOADS ----------
if (empty($_FILES['document']['name'][0])) {
    showError("Please select at least one file for Document 1.", $back_url);
}

$document_path  = handleUpload('document',  $project_id, $back_url);

// ---------- SAVE TO DATABASE ----------
$sql = "INSERT INTO letters_in 
    (title, received_date, reference_number, letter_date, letter_from, receive_mail, remarks, project_id, select_verifier, document_path, created_by, created_by_id, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $db->prepare($sql);
if (!$stmt) showError("Database error: " . $db->error, $back_url);

$stmt->bind_param(
    "sssssssiissis",
    $title,
    $received_date,
    $reference_number,
    $letter_date,
    $letter_from,
    $receive_mail,
    $remarks,
    $project_id,
    $select_verifier,
    $document_path,
    $created_by,
    $created_by_id,
    $status
);

if ($stmt->execute()) {
    header("Location: letterin_list.php?project_id={$project_id}&success=1");
    exit;
} else {
    showError("Error saving letter: " . $stmt->error, $back_url);
}

$stmt->close();
$db->close();
?>
<?php
session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

$role = strtolower(trim($_SESSION['account_type'] ?? ''));
$allowed = in_array($role, [
    'client admin',
    'admin (qa/qc)'
]);
if (!$allowed) {
    die("Access denied.");
}

// --- CSRF check ---
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    die("Invalid CSRF token.");
}

$id         = intval($_POST['id'] ?? 0);
$project_id = intval($_POST['project_id'] ?? 0);
if (!$id) {
    die("Invalid ID.");
}

/*
 * Everything below can throw (mysqli throws mysqli_sql_exception on any
 * SQL error by default since PHP 8.1 — e.g. a foreign key constraint
 * blocking the DELETE). Previously nothing caught that, so it surfaced
 * to the browser as a raw HTTP 500 with no explanation. Wrapping it lets
 * us log the real reason and send the user back to the list with a
 * readable message instead of a blank error page.
 */
try {
    /* OPTIONAL — DELETE FILES */
    $stmt = $db->prepare("
        SELECT document_path
        FROM letters_in
        WHERE id = ?
    ");
    if (!$stmt) {
        throw new RuntimeException("Prepare failed (SELECT document_path): " . $db->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$result) {
        // Record already gone / never existed — nothing to delete.
        $_SESSION['flash_error'] = "Letter not found. It may have already been deleted.";
        header("Location: letterin_list.php?project_id=$project_id");
        exit;
    }

    if ($result) {
        $files = json_decode($result['document_path'] ?? '', true);
        if (is_array($files)) {
            foreach ($files as $file) {
                $fname = is_array($file) ? ($file['path'] ?? '') : $file;
                $path  = "uploads/" . basename($fname);
                if ($fname && file_exists($path)) {
                    @unlink($path);
                }
            }
        } elseif (!empty($result['document_path'])) {
            $path = "uploads/" . basename($result['document_path']);
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    /* DELETE RECORD */
    $stmt = $db->prepare("
        DELETE FROM letters_in
        WHERE id = ?
    ");
    if (!$stmt) {
        throw new RuntimeException("Prepare failed (DELETE): " . $db->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        // Query ran fine but nothing was deleted (e.g. id no longer exists).
        $_SESSION['flash_error'] = "Letter could not be deleted (no matching record).";
        header("Location: letterin_list.php?project_id=$project_id");
        exit;
    }

    $_SESSION['flash_success'] = "Letter deleted successfully.";
    header("Location: letterin_list.php?project_id=$project_id");
    exit;

} catch (\Throwable $e) {
    // Log the real cause somewhere we can actually see it.
    error_log("[delete_letterin.php] id={$id} project_id={$project_id} error: " . $e->getMessage());

    // Foreign-key constraint failures come through as errno 1451 from MySQL.
    $isFkError = (stripos($e->getMessage(), 'foreign key constraint') !== false)
        || (method_exists($e, 'getCode') && (int)$e->getCode() === 1451);

    $_SESSION['flash_error'] = $isFkError
        ? "This letter can't be deleted because it's still referenced by other records (e.g. verification/history entries). Remove those first, or ask an admin to cascade-delete it."
        : "Something went wrong while deleting this letter. The issue has been logged.";

    header("Location: letterin_list.php?project_id=$project_id");
    exit;
}
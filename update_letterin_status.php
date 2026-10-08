<?php
session_start();
require_once 'include/msqli_connect.php';
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized.");
}
/* =========================
   CSRF CHECK
   Must match the token embedded in the form by viewletterin.php.
========================= */
$posted_token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $posted_token)) {
    http_response_code(403);
    die("Invalid or expired form submission. Please refresh the page and try again.");
}
$id               = intval($_POST['id'] ?? 0);
$action           = $_POST['action'] ?? '';
$project_id       = intval($_POST['project_id'] ?? 0);
$rejection_reason = trim($_POST['rejection_reason'] ?? '');
if (!$id || !$action) {
    die("Invalid request.");
}
/* =========================
   USER ROLE
========================= */
$user_id = (int)($_SESSION['user_id'] ?? 0);
$role    = strtolower(trim($_SESSION['account_type'] ?? ''));
$is_admin_edit = in_array($role, [
    'client admin',
    'admin (qa/qc)'
]);
$is_management_role = ($role === 'management');
/* =========================
   GET CURRENT RECORD (status + ownership fields needed for auth)
   NOTE: document_path added to this SELECT — it's needed by
   handle_document_changes() below to merge/replace attachments.
========================= */
$stmt = $db->prepare("
    SELECT status, select_verifier, created_by, project_id, document_path
    FROM letters_in
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
    die("Record not found.");
}
$status = $row['status'];
/* Trust the letter's own project_id, ignore any spoofed value from the form */
$project_id = (int)$row['project_id'];
/* =========================
   AUTHORIZATION
   Mirrors the permission rules enforced in viewletterin.php:
   - verify/reject: only the assigned verifier (select_verifier)
   - update:        creator, assigned verifier, Client Admin, Admin (QA/QC)
========================= */
$is_verifier = ($row['select_verifier'] == $user_id);
$is_creator  = strtolower(trim($row['created_by'] ?? '')) === strtolower(trim($_SESSION['full_name'] ?? ''));
$authorized_for_action = false;
switch ($action) {
    case 'verify':
    case 'reject':
        $authorized_for_action = $is_verifier;
        break;
    case 'update':
        $authorized_for_action = $is_creator || $is_verifier || $is_admin_edit;
        break;
}
if (!$authorized_for_action) {
    http_response_code(403);
    die("You do not have permission to perform this action.");
}
/* =========================
   DOCUMENT REUPLOAD / REMOVE
   The "Uploaded Document" section on viewletterin.php lives inside the
   same <form>/<input type=file> as the rest of the edit fields, gated
   by the same $can_edit flag that authorizes 'update' here. Only run
   this on the 'update' action (Save Changes) — verify/reject should
   not silently also swap out attachments.
========================= */
$can_edit_docs = $is_creator || $is_verifier || $is_admin_edit;
if ($action === 'update' && $can_edit_docs) {
    handle_document_changes($db, $id, $row['document_path'] ?? '');
}
/* =========================
   COMMON INPUTS
========================= */
$upTitle          = trim($_POST['title'] ?? '');
$upDate           = trim($_POST['letter_date'] ?? '');
$received_date    = trim($_POST['received_date'] ?? '');
$reference_number = trim($_POST['reference_number'] ?? '');
$newStatus = null;
/* =========================
   ACTION
========================= */
switch ($action) {
    /* =========================
       VERIFY
    ========================= */
    case 'verify':
        if (in_array($status, ['pending', 'submitted'])) {
            $newStatus = 'verified';
            update_letter_fields($db, $id, $upTitle, $upDate, $received_date, $reference_number);
        }
        break;
    /* =========================
       REJECT
    ========================= */
    case 'reject':
        if (in_array($status, ['pending', 'submitted'])) {
            if ($rejection_reason === '') {
                die("A rejection reason is required.");
            }
            $newStatus = 'rejected';
            $up4 = $db->prepare("
                UPDATE letters_in
                SET rejection_reason = ?
                WHERE id = ?
            ");
            $up4->bind_param("si", $rejection_reason, $id);
            $up4->execute();
            $up4->close();
        }
        break;
    /* =========================
       UPDATE
       Creator/verifier may only edit while the letter is still in a
       working state (pending/submitted/rejected). Client Admin and
       Admin (QA/QC) may edit at any status, any time.
    ========================= */
    case 'update':
        if (
            in_array($status, ['pending', 'submitted', 'rejected']) ||
            $is_admin_edit
        ) {
            update_letter_fields($db, $id, $upTitle, $upDate, $received_date, $reference_number);
        }
        break;
}
/* =========================
   UPDATE STATUS
========================= */
if ($newStatus) {
    if ($newStatus === 'verified') {
        $verified_at = date('Y-m-d H:i:s');
        $stmt = $db->prepare("
            UPDATE letters_in
            SET status = ?, verified_at = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssi", $newStatus, $verified_at, $id);
    } else {
        $stmt = $db->prepare("
            UPDATE letters_in
            SET status = ?
            WHERE id = ?
        ");
        $stmt->bind_param("si", $newStatus, $id);
    }
    $stmt->execute();
    $stmt->close();
}
/* =========================
   REDIRECT
========================= */
header("Location: viewletterin.php?id=$id&project_id=$project_id&msg=updated");
exit;
/* =========================
   HELPER — combines the four optional field updates into a single
   query instead of up to four separate UPDATE statements.
========================= */
function update_letter_fields($db, $id, $title, $letter_date, $received_date, $reference_number) {
    $fields = [];
    $types  = '';
    $values = [];
    if ($title !== '') {
        $fields[] = 'title = ?';
        $types   .= 's';
        $values[] = $title;
    }
    if ($letter_date !== '') {
        $fields[] = 'letter_date = ?';
        $types   .= 's';
        $values[] = $letter_date;
    }
    if ($received_date !== '') {
        $fields[] = 'received_date = ?';
        $types   .= 's';
        $values[] = $received_date;
    }
    if ($reference_number !== '') {
        $fields[] = 'reference_number = ?';
        $types   .= 's';
        $values[] = $reference_number;
    }
    if (empty($fields)) {
        return;
    }
    $sql = "UPDATE letters_in SET " . implode(', ', $fields) . " WHERE id = ?";
    $types   .= 'i';
    $values[] = $id;
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();
}

/* =========================
   HELPER — reupload / remove attachments
   Reads the letter's current document_path JSON, applies:
     1. removals requested via remove_document[] (indexes into the
        existing array, as rendered by render_doc_item() in
        viewletterin.php)
     2. additions from new_document[] (newly uploaded files)
   then writes the merged array back to document_path.

   ADJUST $upload_dir / $public_prefix to match wherever the ORIGINAL
   upload/create flow for letters_in already stores files — this must
   stay consistent with whatever download_file.php expects, and with
   whatever path format is already sitting in document_path today
   (single string vs JSON array of {name, path}).
========================= */
function handle_document_changes($db, $id, $current_document_path_json) {
    $upload_dir     = __DIR__ . '/uploads/letters_in/'; // <-- confirm this matches your real storage path
    $public_prefix  = 'uploads/letters_in/';             // <-- must match how render_doc_item() builds links
    $allowed_ext    = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    $max_size_bytes = 20 * 1024 * 1024; // 20MB per file — adjust as needed

    $existing = json_decode($current_document_path_json ?? '', true);
    if (!is_array($existing)) {
        // Handle the legacy "single string path" format by upgrading it
        // into the array format so removal/addition indexing works.
        $existing = !empty($current_document_path_json)
            ? [['name' => basename($current_document_path_json), 'path' => $current_document_path_json]]
            : [];
    }

    $changed = false;

    /* ---- 1. Removals ---- */
    if (!empty($_POST['remove_document']) && is_array($_POST['remove_document'])) {
        $remove_indexes = array_map('intval', $_POST['remove_document']);
        foreach ($remove_indexes as $idx) {
            if (isset($existing[$idx])) {
                $entry = $existing[$idx];
                $path  = is_array($entry) ? ($entry['path'] ?? '') : $entry;
                $path  = ltrim(str_replace('\\', '/', $path), '/');
                if ($path !== '') {
                    $full = __DIR__ . '/' . $path;
                    if (is_file($full)) {
                        @unlink($full); // best-effort; DB update still proceeds if this fails
                    }
                }
                unset($existing[$idx]);
                $changed = true;
            }
        }
        $existing = array_values($existing);
    }

    /* ---- 2. New uploads ---- */
    if (!empty($_FILES['new_document']['name'][0])) {
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }
        $count = count($_FILES['new_document']['name']);
        for ($i = 0; $i < $count; $i++) {
            $error = $_FILES['new_document']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                // Skip files that failed to upload (too large, partial, etc.)
                // rather than aborting the whole save.
                continue;
            }
            $tmp_path  = $_FILES['new_document']['tmp_name'][$i];
            $orig_name = $_FILES['new_document']['name'][$i];
            $size      = $_FILES['new_document']['size'][$i] ?? 0;
            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext, true)) {
                continue; // reject disallowed file types
            }
            if ($size <= 0 || $size > $max_size_bytes) {
                continue; // reject empty/oversized files
            }
            if (!is_uploaded_file($tmp_path)) {
                continue; // basic upload-hijack guard
            }
            // Keep the original filename on disk (sanitized against path
            // traversal / unsafe characters) instead of a random name.
            // If a file with that exact name already exists in the
            // folder, append (1), (2), ... so nothing gets overwritten.
            $base_name = pathinfo($orig_name, PATHINFO_FILENAME);
            $safe_base = preg_replace('/[^A-Za-z0-9_\- ]/', '_', $base_name);
            $safe_base = trim($safe_base) !== '' ? $safe_base : 'file';
            $safe_name = $safe_base . '.' . $ext;
            $dest_path = $upload_dir . $safe_name;
            $suffix = 1;
            while (file_exists($dest_path)) {
                $safe_name = $safe_base . '(' . $suffix . ').' . $ext;
                $dest_path = $upload_dir . $safe_name;
                $suffix++;
            }
            if (move_uploaded_file($tmp_path, $dest_path)) {
                $existing[] = [
                    'name' => $orig_name,
                    'path' => $public_prefix . $safe_name,
                ];
                $changed = true;
            }
        }
    }

    if (!$changed) {
        return; // nothing to do — leave document_path untouched
    }

    $new_json = json_encode(array_values($existing));
    $upd = $db->prepare("UPDATE letters_in SET document_path = ? WHERE id = ?");
    $upd->bind_param("si", $new_json, $id);
    $upd->execute();
    $upd->close();
}
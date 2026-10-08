<?php
session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Invalid request method.");
}

$id      = intval($_POST['id'] ?? 0);
$ackType = $_POST['ackType'] ?? '';

if (!$id || !$ackType) {
    echo "<script>alert('Error: Missing record ID or action type.'); window.history.back();</script>";
    exit;
}

$stmt_info = $db->prepare("
    SELECT cerun_id, running_number 
    FROM letteroutpending_cerun 
    WHERE id = ?
");

$stmt_info->bind_param("i", $id);
$stmt_info->execute();

$res_info = $stmt_info->get_result()->fetch_assoc();

if (!$res_info) {
    echo "<script>alert('Error: Original pending record not found.'); window.history.back();</script>";
    exit;
}

$stmt_info->close();

$cerun_id = $res_info['cerun_id'];
$run_num  = $res_info['running_number'];

preg_match('/(\d{4})/', $run_num, $matches);

$folderYear = $matches[0] ?? date('Y');
$folderType = (strpos($run_num, 'KJ6') !== false) ? '6' : '4';

/* =========================
   UPDATE EDITABLE FIELDS
========================= */
$title       = trim($_POST['title'] ?? '');
$agency      = trim($_POST['agency'] ?? '');
$verifier_id = intval($_POST['verifier_id'] ?? 0);

if ($title) {

    $editStmt = $db->prepare("
        UPDATE letteroutpending_cerun 
        SET 
            title = ?, 
            agency = ?, 
            verifier_id = ?
        WHERE id = ?
    ");

    $editStmt->bind_param(
        "ssii",
        $title,
        $agency,
        $verifier_id,
        $id
    );

    $editStmt->execute();
    $editStmt->close();
}

/* =====================================================
   ACKNOWLEDGE
===================================================== */
if ($ackType === 'acknowledge') {

    $ack_ref      = trim($_POST['reference'] ?? '');
    $letter_date  = $_POST['letter_date'] ?? '';
    $ack_date     = $_POST['ack_date'] ?? '';
    $mail_through = $_POST['send_mail'] ?? '';

    /* =========================
       CHECK FILE UPLOAD
    ========================= */
    $hasUpload = false;

    if (isset($_FILES['letter_file']['name'])) {

        foreach ($_FILES['letter_file']['name'] as $name) {

            if (!empty($name)) {
                $hasUpload = true;
                break;
            }
        }
    }

    /* =========================
       CHECK EXISTING RECORD
    ========================= */
    $checkStmt = $db->prepare("
        SELECT id, letter_file
        FROM letteroutacknowledge_cerun
        WHERE pending_id = ?
        LIMIT 1
    ");

    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();

    $existing = $checkStmt->get_result()->fetch_assoc();

    $checkStmt->close();

    // First time acknowledge wajib upload file
    if (!$existing && !$hasUpload) {

        echo "
        <script>
            alert('Please upload at least one file.');
            window.history.back();
        </script>
        ";

        exit;
    }

    /* =========================
       FILE UPLOAD SETUP
    ========================= */
    $uploadDir     = __DIR__ . '/uploads/letters_cerun/';
    $uploadBaseUrl = 'https://mggroup.my/projectmanagement/uploads/letters_cerun/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $uploadedPaths = [];

    /* =========================
       HANDLE FILE UPLOAD
    ========================= */
    if (!empty($_FILES['letter_file']['error'])) {

        foreach ($_FILES['letter_file']['error'] as $key => $error) {

            if ($error === UPLOAD_ERR_OK) {

                $ext = strtolower(
                    pathinfo(
                        $_FILES['letter_file']['name'][$key],
                        PATHINFO_EXTENSION
                    )
                );

                $originalName = pathinfo($_FILES['letter_file']['name'][$key], PATHINFO_FILENAME);
				$safeName = preg_replace("/[^a-zA-Z0-9]/", "_", $originalName);
				$uploadDate = date('d-m-Y');
				$newName = $safeName . "_" . $uploadDate . "." . $ext;

                $target = $uploadDir . $newName;

                if (
                    move_uploaded_file(
                        $_FILES['letter_file']['tmp_name'][$key],
                        $target
                    )
                ) {
                    $uploadedPaths[] = $uploadBaseUrl . $newName;
                }
            }
        }
    }

    /* =========================
       DELETE OLD FILES
    ========================= */
    if (
        !empty($uploadedPaths)
        && !empty($existing['letter_file'])
    ) {

        $oldFiles = json_decode($existing['letter_file'], true);

        // kalau single file
        if (!is_array($oldFiles)) {
            $oldFiles = [$existing['letter_file']];
        }

        foreach ($oldFiles as $oldUrl) {

            $oldPath = str_replace(
                'https://mggroup.my/projectmanagement/uploads/letters_cerun/',
                $uploadDir,
                $oldUrl
            );

            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }
    }

    /* =========================
       FORMAT FILE DATA
    ========================= */
    $letter_file = !empty($uploadedPaths)
        ? (
            count($uploadedPaths) > 1
            ? json_encode($uploadedPaths)
            : $uploadedPaths[0]
        )
        : '';

    // retain old file kalau tak upload baru
    if (
        empty($letter_file)
        && !empty($existing['letter_file'])
    ) {
        $letter_file = $existing['letter_file'];
    }

    /* =========================
       UPDATE EXISTING
    ========================= */
    if ($existing) {

        $stmt = $db->prepare("
            UPDATE letteroutacknowledge_cerun
            SET
                ack_ref = ?,
                letter_date = ?,
                ack_date = ?,
                mail_through = ?,
                letter_file = ?
            WHERE pending_id = ?
        ");

        $stmt->bind_param(
            "sssssi",
            $ack_ref,
            $letter_date,
            $ack_date,
            $mail_through,
            $letter_file,
            $id
        );

    /* =========================
       INSERT NEW
    ========================= */
    } else {

        $stmt = $db->prepare("
            INSERT INTO letteroutacknowledge_cerun
            (
                pending_id,
                ack_ref,
                letter_date,
                ack_date,
                mail_through,
                letter_file
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "isssss",
            $id,
            $ack_ref,
            $letter_date,
            $ack_date,
            $mail_through,
            $letter_file
        );
    }

    if ($stmt->execute()) {

        $updatePending = $db->prepare("
            UPDATE letteroutpending_cerun
            SET status = 'acknowledged'
            WHERE id = ?
        ");

        $updatePending->bind_param("i", $id);
        $updatePending->execute();
        $updatePending->close();

        echo "
        <script>
            alert('Letter successfully acknowledged.');
            window.location.href='cerun.php?cerun_id={$cerun_id}&year={$folderYear}&type={$folderType}';
        </script>
        ";

    } else {

        echo "
        <script>
            alert('Error: " . addslashes($stmt->error) . "');
            window.history.back();
        </script>
        ";
    }

    $stmt->close();

/* =====================================================
   CANCEL
===================================================== */
} elseif ($ackType === 'cancel') {

    $cancel_reason  = trim($_POST['cancel_reason'] ?? '');
    $cancel_comment = trim($_POST['cancel_comment'] ?? '');

    if (empty($cancel_reason)) {

        echo "
        <script>
            alert('Please provide a reason for cancellation.');
            window.history.back();
        </script>
        ";

        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO letteroutcancel_cerun
        (
            pending_id,
            cancel_ref,
            cancel_reason,
            cancel_comment
        )
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isss",
        $id,
        $run_num,
        $cancel_reason,
        $cancel_comment
    );

    if ($stmt->execute()) {

        $updatePending = $db->prepare("
            UPDATE letteroutpending_cerun
            SET status = 'cancelled'
            WHERE id = ?
        ");

        $updatePending->bind_param("i", $id);
        $updatePending->execute();
        $updatePending->close();

        echo "
        <script>
            alert('Letter successfully cancelled.');
            window.location.href='cerun.php?cerun_id={$cerun_id}&year={$folderYear}&type={$folderType}';
        </script>
        ";

    } else {

        echo "
        <script>
            alert('Error: " . addslashes($stmt->error) . "');
            window.history.back();
        </script>
        ";
    }

    $stmt->close();
}

$db->close();
?>
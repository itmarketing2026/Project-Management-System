<?php
session_start();
require_once 'include/msqli_connect.php';
/* =========================
   GET ID
========================= */
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die("Invalid ID");
}
/* =========================
   GET RECORD
========================= */
$stmt = $db->prepare("
    SELECT cerun_id, archive_file, created_by
    FROM letteroutarchive_cerun
    WHERE id=?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
if (!$row) {
    die("Record not found");
}
/* =========================
   ROLE CHECK
   ADMIN / QA-QC, OR THE USER WHO CREATED THIS RECORD
   (created_by stores the creator's full_name)
========================= */
$role = strtolower(trim($_SESSION['account_type'] ?? ''));
$is_admin = in_array($role, ['client admin', 'admin (qa/qc)']);
$is_creator = !empty($_SESSION['full_name']) &&
    strcasecmp(
        trim((string)$row['created_by']),
        trim((string)$_SESSION['full_name'])
    ) === 0;
if (!$is_admin && !$is_creator) {
    die("Access denied");
}
$cerun_id = $row['cerun_id'];
$file_raw = $row['archive_file'] ?? '';
/* =========================
   DELETE FILE(S)
========================= */
$files = json_decode($file_raw, true);
if (!$files) {
    $files = [$file_raw];
}
foreach ($files as $file) {
    if (!empty($file)) {
        $local_path = str_replace(
            "https://mggroup.my/projectmanagement/",
            __DIR__ . "/",
            $file
        );
        if (file_exists($local_path)) {
            unlink($local_path);
        }
    }
}
/* =========================
   DELETE RECORD
========================= */
$stmt = $db->prepare("
    DELETE FROM letteroutarchive_cerun
    WHERE id=?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();
/* =========================
   REDIRECT
========================= */
header("Location: cerun.php?cerun_id=" . $cerun_id);
exit;
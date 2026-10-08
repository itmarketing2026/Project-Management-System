<?php
require_once 'include/msqli_connect.php';
session_start();
file_put_contents('debug_gallery.txt', print_r([
    'existing_ids' => $_POST['existing_gallery_id'] ?? [],
    'files'        => $_FILES['gallery_image'] ?? [],
    'captions'     => $_POST['gallery_caption'] ?? [],
], true));

ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

$created_by = $_SESSION['full_name'] ?? 'Unknown';

/* =========================
   HELPER
========================= */
function num($val) {
    if (!$val) return 0;
    $val = str_replace(',', '', $val);
    return is_numeric($val) ? (float)$val : 0;
}

function cleanDate($date) {
    return (!empty($date) && $date !== '0000-00-00') ? $date : null;
}

/* =========================
   GET DATA
========================= */
$id = intval($_POST['id'] ?? 0);

$action = $_POST['action'] ?? 'submit';
$status = ($action === 'draft') ? 'draft' : 'submitted';

$project_id   = intval($_POST['project_id'] ?? 0);
$project_name = $_POST['project_name'] ?? '';

$pusb = $_POST['company'] ?? '';
$pm   = $_POST['pm'] ?? '';
$cm   = $_POST['cm'] ?? '';
$re   = $_POST['re'] ?? '';

$contract_start = cleanDate($_POST['contract_start'] ?? '');
$contract_end   = cleanDate($_POST['contract_end'] ?? '');
$eot            = cleanDate($_POST['eot'] ?? '');

$physical_progress = num($_POST['physical_progress'] ?? 0);
$target_progress   = num($_POST['target_progress'] ?? 0);
$different_progress = $physical_progress - $target_progress;

$days_status = $_POST['days_status'] ?? '';

$contract_value     = num($_POST['contract_value'] ?? 0);
$newcontract_value  = num($_POST['newcontract_value'] ?? 0);
$paid               = num($_POST['paid'] ?? 0);
$unpaid             = num($_POST['unpaid'] ?? 0);
$wjp                = num($_POST['wjp'] ?? 0);
$balance            = num($_POST['balance'] ?? 0);

/* =========================
   SUBCON DATA
========================= */
$names   = $_POST['sub_name'] ?? [];
$issues  = $_POST['sub_issue'] ?? [];
$contracts = $_POST['sub_contract'] ?? [];
$new_contract_sub = $_POST['new_contract_sub'] ?? [];
$paids     = $_POST['sub_paid'] ?? [];
$unpaids   = $_POST['sub_unpaid'] ?? [];
$wjps      = $_POST['sub_wjp'] ?? [];
$balances  = $_POST['sub_balance'] ?? [];
$sub_ids   = $_POST['sub_id'] ?? [];
$delete_flags = $_POST['delete_sub'] ?? [];

/* =========================
   OTHER TEXT
========================= */
$issues_text     = $_POST['issue'] ?? '';
$technical_issue = $_POST['technical_issue'] ?? '';
$site_issue      = $_POST['site_issue'] ?? '';
$mgtm            = $_POST['mgtm'] ?? '';

$report_date =
    $_POST['report_date']
    ?? date('Y-m-d');
$week_end = $report_date;

$week_start = date(
    'Y-m-d',
    strtotime(
        '-6 days',
        strtotime($week_end)
    )
);
$created_at  = date('Y-m-d H:i:s');

/* =========================
   SAVE MAIN REPORT
========================= */

if ($id) {

    $stmt = $db->prepare("
        UPDATE weekly_reports SET
            project_name=?, report_date=?, pusb=?, pm=?, cm=?, re=?,
            contract_start=?, contract_end=?, eot=?,
            physical_progress=?, target_progress=?, different_progress=?, days_status=?,
            contract_value=?, newcontract_value=?, paid=?, unpaid=?, wjp=?, balance=?,
            issues=?, technical_issue=?, site_issue=?, mgtm=?,
            created_at=?, created_by=?, status=?
        WHERE id=?
    ");

    $stmt->bind_param(
        "ssssssssssssssssssssssssssi",
        $project_name,
        $report_date,
        $pusb,
        $pm,
        $cm,
        $re,
        $contract_start,
        $contract_end,
        $eot,
        $physical_progress,
        $target_progress,
        $different_progress,
        $days_status,
        $contract_value,
        $newcontract_value,
        $paid,
        $unpaid,
        $wjp,
        $balance,
        $issues_text,
        $technical_issue,
        $site_issue,
        $mgtm,
        $created_at,
        $created_by,
        $status,
        $id
    );

    $stmt->execute();
    $report_id = $id;

} else {
$stmt = $db->prepare("
    INSERT INTO weekly_reports (
        project_id,
        project_name,
        report_date,
        week_start,
        week_end,
        pusb,
        pm,
        cm,
        re,
        contract_start,
        contract_end,
        eot,
        physical_progress,
        target_progress,
        different_progress,
        days_status,
        contract_value,
        newcontract_value,
        paid,
        unpaid,
        wjp,
        balance,
        issues,
        technical_issue,
        site_issue,
        mgtm,
        created_at,
        created_by,
        status
    ) VALUES (
        ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
    )
");

$stmt->bind_param(
    "issssssssssssssssssssssssssss",
    $project_id,
    $project_name,
    $report_date,
    $week_start,
    $week_end,
    $pusb,
    $pm,
    $cm,
    $re,
    $contract_start,
    $contract_end,
    $eot,
    $physical_progress,
    $target_progress,
    $different_progress,
    $days_status,
    $contract_value,
    $newcontract_value,
    $paid,
    $unpaid,
    $wjp,
    $balance,
    $issues_text,
    $technical_issue,
    $site_issue,
    $mgtm,
    $created_at,
    $created_by,
    $status
);

$stmt->execute();

$report_id = $stmt->insert_id;
}

/* =========================
   SAVE SUBCON
========================= */

$stmt_update = $db->prepare("
    UPDATE subcontractors 
    SET name=?, issue=?, contract=?, paid=?, unpaid=?, wjp=?, balance=?, new_contract_sub=?
    WHERE id=?
");

$stmt_insert = $db->prepare("
    INSERT INTO subcontractors 
    (report_id, name, issue, contract, paid, unpaid, wjp, balance, new_contract_sub)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt_delete = $db->prepare("DELETE FROM subcontractors WHERE id=?");

foreach ($names as $i => $name) {

    $name  = trim($name ?? '');
    if ($name === '') continue;

    $issue = trim($issues[$i] ?? '');

    $sub_id = intval($sub_ids[$i] ?? 0);
    $delete = intval($delete_flags[$i] ?? 0);

    // DELETE
    if ($delete === 1 && $sub_id > 0) {
        $stmt_delete->bind_param("i", $sub_id);
        $stmt_delete->execute();
        continue;
    }

    // VALUES
    $contract = num($contracts[$i] ?? 0);
    $paid2    = num($paids[$i] ?? 0);
    $unpaid2  = num($unpaids[$i] ?? 0);
    $wjp2     = num($wjps[$i] ?? 0);
    $balance2 = num($balances[$i] ?? 0);
    $new_contract_value = num($new_contract_sub[$i] ?? 0);

    if ($id && $sub_id > 0) {
        // UPDATE
        $stmt_update->bind_param(
            "ssddddddi",
            $name,
            $issue,
            $contract,
            $paid2,
            $unpaid2,
            $wjp2,
            $balance2,
            $new_contract_value,
            $sub_id
        );
        $stmt_update->execute();

    } else {
        // INSERT
        $stmt_insert->bind_param(
            "issdddddd",
            $report_id,
            $name,
            $issue,
            $contract,
            $paid2,
            $unpaid2,
            $wjp2,
            $balance2,
            $new_contract_value
        );
        $stmt_insert->execute();
    }
}
/* =========================================
SITE GALLERY
========================================= */

$upload_dir = __DIR__ . "/uploads/project_" . $project_id . "/weekly_gallery/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$allowed       = ['jpg', 'jpeg', 'png', 'webp'];
$existing_ids  = $_POST['existing_gallery_id'] ?? [];
$captions      = $_POST['gallery_caption'] ?? [];

if (isset($_FILES['gallery_image'])) {

    foreach ($_FILES['gallery_image']['tmp_name'] as $key => $tmp_name) {

        $caption     = trim($captions[$key] ?? '');
        $existing_id = isset($existing_ids[$key]) ? intval($existing_ids[$key]) : 0;

        $new_file_uploaded = !empty($tmp_name) && $_FILES['gallery_image']['error'][$key] == 0;
        $clean_name = null;

        if ($new_file_uploaded) {
            $original_name = basename($_FILES['gallery_image']['name'][$key]);
            $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed)) {
                $clean_name = time() . "_" . str_replace(' ', '_', $original_name);
                move_uploaded_file($tmp_name, $upload_dir . $clean_name);
            }
        }

        if ($existing_id > 0) {

            // ===== EXISTING ROW =====
            if ($clean_name) {
                $old_stmt = $db->prepare("SELECT image_name FROM weekly_report_gallery WHERE id=?");
                $old_stmt->bind_param("i", $existing_id);
                $old_stmt->execute();
                $old_row = $old_stmt->get_result()->fetch_assoc();
                $old_stmt->close();

                if ($old_row && !empty($old_row['image_name'])) {
                    $old_path = $upload_dir . $old_row['image_name'];
                    if (file_exists($old_path)) {
                        unlink($old_path);
                    }
                }

                $stmt_g = $db->prepare("UPDATE weekly_report_gallery SET image_name=?, caption=? WHERE id=?");
                $stmt_g->bind_param("ssi", $clean_name, $caption, $existing_id);
                $stmt_g->execute();
                $stmt_g->close();

            } else {
                $stmt_g = $db->prepare("UPDATE weekly_report_gallery SET caption=? WHERE id=?");
                $stmt_g->bind_param("si", $caption, $existing_id);
                $stmt_g->execute();
                $stmt_g->close();
            }

        } else {

            if ($clean_name) {
                $stmt_g = $db->prepare("
                    INSERT INTO weekly_report_gallery
                    (report_id, project_id, report_date, image_name, caption, created_by)
                    VALUES (?,?,?,?,?,?)
                ");
                $stmt_g->bind_param(
                    "iisssi",
                    $report_id, $project_id, $report_date, $clean_name, $caption, $_SESSION['user_id']
                );
                $stmt_g->execute();
                $stmt_g->close();
            }
        }
    }
}

/* =========================================
DELETE REMOVED GALLERY ITEMS
========================================= */
if (!empty($_POST['delete_gallery_id'])) {
    $stmt_del_g = $db->prepare("SELECT image_name FROM weekly_report_gallery WHERE id=? AND report_id=?");
    $stmt_del_g2 = $db->prepare("DELETE FROM weekly_report_gallery WHERE id=? AND report_id=?");

    foreach ($_POST['delete_gallery_id'] as $del_id) {
        $del_id = intval($del_id);
        $stmt_del_g->bind_param("ii", $del_id, $report_id);
        $stmt_del_g->execute();
        $row = $stmt_del_g->get_result()->fetch_assoc();
        if ($row) {
            $path = $upload_dir . $row['image_name'];
            if (file_exists($path)) unlink($path);
        }
        $stmt_del_g2->bind_param("ii", $del_id, $report_id);
        $stmt_del_g2->execute();
    }
    $stmt_del_g->close();
    $stmt_del_g2->close();
}

/* =========================
   REDIRECT
========================= */
if ($status === 'draft') {
    header("Location: edit_report.php?id=" . $report_id);
} else {
    header("Location: view_report_detail.php?id=" . $report_id);
}
exit;
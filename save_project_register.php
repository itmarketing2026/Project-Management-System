<?php
session_start();
require_once 'include/msqli_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'msg' => 'Unauthorized']); exit;
}

$action      = $_POST['action'] ?? '';
$register_id = intval($_POST['register_id'] ?? 0);
$project_id  = intval($_POST['project_id'] ?? 0);

if (!$register_id || !$project_id) {
    echo json_encode(['success' => false, 'msg' => 'Missing ID']); exit;
}

if ($action === 'save_project') {
    $title   = trim($_POST['title'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $contract= trim($_POST['contract_no'] ?? '');
    $start   = $_POST['site_start'] ?: null;
    $end     = $_POST['site_end'] ?: null;
    $period  = trim($_POST['construction_period'] ?? '');
    $sum     = trim($_POST['contract_sum'] ?? '') ?: null;

    $stmt = $db->prepare("UPDATE project_register SET title=?, company=?, contract_no=?, site_start=?, site_end=?, construction_period=?, contract_sum=? WHERE id=? AND project_id=?");
    $stmt->bind_param("ssssssdii", $title, $company, $contract, $start, $end, $period, $sum, $register_id, $project_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
}

elseif ($action === 'save_pic') {

    $fields = [
        'pm' => ['col' => 'pic_project_manager'],
        'pe' => ['col' => 'pic_project_engineer'],
        'consultant' => ['col' => 'pic_consultant'],
        'subcon' => ['col' => 'subcontractors']
    ];

    $new_values = [
        'pm' => trim($_POST['project_manager'] ?? ''),
        'pe' => trim($_POST['project_engineer'] ?? ''),
        'consultant' => trim($_POST['consultant'] ?? ''),
        'subcon' => implode(',', array_filter(array_map('trim', $_POST['subcon_names'] ?? [])))
    ];

    foreach ($fields as $key => $f) {

        $new_val = $new_values[$key] ?? '';

        if (!$new_val) continue;

        // 🔹 Get old value
        $stmt = $db->prepare("SELECT {$f['col']}, created_at 
                      FROM project_register 
                      WHERE id=? AND project_id=?");
$stmt->bind_param("ii", $register_id, $project_id);
        $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $old_val = $old[$f['col']] ?? '';

        // 🔹 Only process if value changed
        if ($old_val !== $new_val) {

            // ✅ Insert into history
            $stmt = $db->prepare("INSERT INTO project_register_history 
                (register_id, project_id, field_name, value, replaced_by, created_at, deactivated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())");

            $created_at = $old['created_at'] ?? date('Y-m-d H:i:s');

            $stmt->bind_param("iissis",
                $register_id,
                $project_id,
                $key,
                $old_val,
                $_SESSION['user_id'],
                $created_at
            );
            $stmt->execute();
            $stmt->close();

            // ✅ Update current value
            $stmt = $db->prepare("UPDATE project_register SET {$f['col']}=? WHERE id=? AND project_id=?");
            $stmt->bind_param("sii", $new_val, $register_id, $project_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    echo json_encode(['success' => true]);
}

else {
    echo json_encode(['success' => false, 'msg' => 'Unknown action']);
}

$db->close();
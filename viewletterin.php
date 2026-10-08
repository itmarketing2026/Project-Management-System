<?php
session_start();
$title = "View Letter In";
require_once 'include/msqli_connect.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
/* =========================================================
   CSRF TOKEN
   ========================================================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
/* =========================================================
   STYLED ERROR PAGE
   ========================================================= */
function render_error(string $message, int $httpCode = 200): void {
    http_response_code($httpCode);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Project Management System</title>
        <style>
            :root { --accent:#007bff; --red:#dc3545; --max:520px; }
            * { box-sizing: border-box }
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(#f3f4f6, #eef1f5);
                margin: 0;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                align-items: center;
                color: #1e293b;
            }
            .page-header {
                width: 100%;
                background: linear-gradient(135deg, #007bff, #0056b3);
                padding: 20px 0 22px;
                margin-bottom: 22px;
                box-shadow: 0 4px 12px rgba(0,0,0,.1);
            }
            .page-header-inner { max-width: var(--max); margin: 0 auto; padding: 0 24px; }
            .page-header .eyebrow {
                color: rgba(255,255,255,.75);
                font-size: 11px;
                letter-spacing: 1.5px;
                text-transform: uppercase;
                font-weight: 700;
                margin-bottom: 4px;
            }
            .page-header h1 { color: #fff; font-weight: 800; font-size: 22px; margin: 0; }
            .error-card {
                width: 100%;
                max-width: var(--max);
                background: #fff;
                border-radius: 16px;
                padding: 32px 24px;
                margin: 0 24px 28px;
                box-shadow: 0 6px 18px rgba(0,0,0,.06);
                text-align: center;
            }
            .error-icon {
                width: 56px;
                height: 56px;
                border-radius: 50%;
                background: #fff5f5;
                border: 2px solid var(--red);
                color: var(--red);
                font-size: 26px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 16px;
            }
            .error-message { font-size: 15px; color: #475569; line-height: 1.6; margin-bottom: 22px; }
            .error-back {
                display: inline-block;
                background: var(--accent);
                color: #fff;
                text-decoration: none;
                font-weight: 700;
                font-size: 14px;
                padding: 10px 20px;
                border-radius: 8px;
            }
            .error-back:hover { background: #0056b3; }
        </style>
    </head>
    <body>
        <div class="page-header">
            <div class="page-header-inner">
                <div class="eyebrow">Letters In</div>
                <h1>Project Management System</h1>
            </div>
        </div>
        <div class="error-card">
            <div class="error-icon">!</div>
            <div class="error-message"><?= htmlspecialchars($message) ?></div>
            <a href="javascript:history.back()" class="error-back">← Go Back</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
$user_id   = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['account_type'] ?? '';
$role = strtolower(trim($user_role));
$is_admin_edit = in_array($role, [
    'client admin',
    'admin (qa/qc)'
]);
$can_delete = in_array($role, [
    'client admin',
    'admin (qa/qc)'
]);
$id         = intval($_GET['id'] ?? 0);
$project_id = intval($_GET['project_id'] ?? 0);
if (!$id) render_error("Invalid record. The letter you're looking for could not be identified.");
/* =========================================================
   FETCH LETTER
   ========================================================= */
$sql = "SELECT l.id, l.title, l.received_date, l.reference_number,
               l.letter_date, l.letter_from, l.receive_mail, l.remarks,
               l.select_verifier, l.document_path, l.created_at, l.status,
               l.rejection_reason,
               l.project_id,
               u.full_name AS verifier_name,
               l.created_by,
               l.created_by_id
        FROM letters_in l
        LEFT JOIN users u ON l.select_verifier = u.id
        WHERE l.id = ?";
$stmt = $db->prepare($sql);
if (!$stmt) {
    render_error("A system error occurred. Please try again later.");
}
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$data) render_error("No record found. This letter may have been deleted.");
$project_id = (int)$data['project_id'];
/* =========================================================
   AUTHORIZATION CHECK (fixes IDOR)
   ========================================================= */
$stmtP = $db->prepare("SELECT id FROM user_project WHERE user_id = ? AND project_id = ? LIMIT 1");
$stmtP->bind_param("ii", $user_id, $project_id);
$stmtP->execute();
$stmtP->store_result();
$is_assigned_to_project = $stmtP->num_rows > 0;
$stmtP->close();
/* =========================================================
   CREATOR CHECK — ID-based
   ========================================================= */
$is_creator = ((int)($data['created_by_id'] ?? 0) === $user_id);
$is_verifier = ($data['select_verifier'] == $user_id);
/* Management and HRA have implicit access to every project */
$is_management_role = ($role === 'management');
$is_hra_role         = ($role === 'hra');
$is_authorized = $is_creator
    || $is_verifier
    || $is_admin_edit
    || $is_assigned_to_project
    || $is_management_role
    || $is_hra_role;
if (!$is_authorized) {
    render_error("You do not have permission to view this record.", 403);
}
// --- WORKFLOW LOGIC ---
$status = $data['status'] ?? 'pending';
$is_hra        = $is_hra_role;
$is_management = $is_management_role;
/* edit logic */
$can_edit = false;
/* NORMAL EDIT — creator or assigned verifier, while letter is still
   in a working state (pending/submitted/rejected) */
if (in_array($status, ['pending', 'submitted', 'rejected'])) {
    if ($is_creator || $is_verifier) {
        $can_edit = true;
    }
}
/* ADMIN / QAQC CAN EDIT ANY STATUS, ANY TIME */
if ($is_admin_edit) {
    $can_edit = true;
}
/* verify logic — anyone actually assigned as the verifier for this
   letter (select_verifier) can verify/reject it */
$can_verify = $is_verifier
    && in_array($status, ['pending', 'submitted']);
/* =========================================================
   PREV / NEXT NAVIGATION + COUNTER ("bilangan")
   ========================================================= */
if ($project_id) {
    $navSql = "SELECT id FROM letters_in WHERE project_id = ? ORDER BY created_at ASC, id ASC";
    $stmtNav = $db->prepare($navSql);
    $stmtNav->bind_param("i", $project_id);
} else {
    $navSql = "SELECT id FROM letters_in ORDER BY created_at ASC, id ASC";
    $stmtNav = $db->prepare($navSql);
}
$stmtNav->execute();
$resNav = $stmtNav->get_result();
$navRows = [];
while ($row = $resNav->fetch_assoc()) {
    $navRows[] = ['id' => (int)$row['id']];
}
$stmtNav->close();
$allIds       = array_column($navRows, 'id');
$totalCount   = count($allIds);
$currentIndex = array_search($id, $allIds, true);
$prevId = null;
$nextId = null;
$currentPosition = null;
if ($currentIndex !== false) {
    $currentPosition = $currentIndex + 1; // 1-based for display
    if ($currentIndex > 0) {
        $prevId = $allIds[$currentIndex - 1];
    }
    if ($currentIndex < $totalCount - 1) {
        $nextId = $allIds[$currentIndex + 1];
    }
}
function nav_url($targetId, $projectId) {
    $qs = 'id=' . (int)$targetId;
    if ($projectId) {
        $qs .= '&project_id=' . (int)$projectId;
    }
    return htmlspecialchars('?' . $qs);
}
/* =========================================================
   SHORT-LIVED PRIVATE CACHE (enables hover-prefetch below)
   ========================================================= */
header('Cache-Control: private, max-age=5');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Project Management System</title>
    <style>
        :root {
            --accent: #007bff;
            --accent-light: #007bff;
            --ink: #1e293b;
            --muted: #6b7785;
            --border: #dbe1e8;
            --surface: #ffffff;
            --bg: #f3f4f6;
            --green: #28a745;
            --red: #dc3545;
            --max: 900px;
        }
        * { box-sizing: border-box }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(#f3f4f6, #eef1f5);
            padding: 0 0 32px;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0;
            color: var(--ink);
        }
        body > *:not(#rejectModal) { width: 100%; max-width: var(--max); }
        /* ---- Corporate page header ---- */
        .page-header {
            width: 100%;
            background: linear-gradient(135deg, #007bff, #0056b3);
            padding: 20px 0 22px;
            margin-bottom: 22px;
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
        }
        .page-header-inner {
            max-width: var(--max);
            margin: 0 auto;
            padding: 0 24px;
        }
        .page-header .eyebrow {
            color: rgba(255,255,255,.75);
            font-size: 11px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 4px;
        }
        h1 {
            text-align: left;
            color: #fff;
            font-weight: 800;
            font-size: 24px;
            margin: 0;
        }
        body > *:not(.page-header):not(#rejectModal) {
            padding-left: 24px;
            padding-right: 24px;
        }
        /* ---- Toolbar: Back + Prev/Next + counter, one strip ---- */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            background: #fff;
            border-radius: 12px;
            padding: 10px 14px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,.05);
        }
        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tb-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 9px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            white-space: nowrap;
            transition: background .15s;
        }
        .tb-btn:hover {
            background: #0056b3;
        }
        .tb-btn.disabled {
            background: #cbd5e1;
            color: #f1f5f9;
            pointer-events: none;
            cursor: not-allowed;
        }
        .nav-counter {
            font-weight: 700;
            color: #334155;
            font-size: 14px;
            letter-spacing: .2px;
            white-space: nowrap;
        }
        .jump-box {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f9fafb;
            border: 1px solid #dbe1e8;
            border-radius: 8px;
            padding: 5px 10px;
            white-space: nowrap;
        }
        .jump-select {
            border: none;
            background: transparent;
            font-weight: 700;
            font-size: 14px;
            color: var(--accent);
            outline: none;
            width: 64px;
            text-align: center;
            cursor: pointer;
        }
        .jump-select:focus {
            color: #0056b3;
        }
        .kbd-hint {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            margin-left: 4px;
            opacity: .55;
        }
        .kbd-hint kbd {
            font-family: inherit;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            background: #eef1f5;
            border: 1px solid #d5dbe3;
            border-radius: 4px;
            padding: 2px 6px;
            box-shadow: 0 1px 0 #d5dbe3;
        }
        @media(max-width:640px) {
            .kbd-hint { display: none; }
        }
        @media(max-width:520px) {
            .toolbar { justify-content: center; }
            .toolbar-group { justify-content: center; width: 100%; }
        }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 28px;
            box-shadow: 0 6px 18px rgba(0,0,0,.06);
        }
        .section-title {
            font-weight: 800;
            color: var(--accent);
            border-bottom: 2px solid var(--accent);
            padding-bottom: 6px;
            margin-bottom: 18px;
        }
        .label { font-weight: 700; margin-bottom: 6px; }
        .value-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 14px;
            width: 100%;
            display: block;
            font-family: inherit;
            font-size: 14px;
            color: #1e293b;
        }
        input.value-box,
        textarea.value-box {
            outline: none;
            background: #fff;
            border-color: var(--accent);
            transition: box-shadow 0.2s;
        }
        input.value-box:focus,
        textarea.value-box:focus {
            box-shadow: 0 0 0 3px rgba(0,123,255,0.15);
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        @media(max-width:640px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
        .hero {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #fff;
            border-radius: 18px;
            padding: 22px;
            margin-bottom: 26px;
        }
        .hero small {
            opacity: 0.75;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: normal;
            color: #fff;
        }
        .hero h2 { margin: 6px 0 0; font-size: 28px; font-weight: 800; color: #fff; }
        .btn {
            width: 100%;
            color: #fff;
            padding: 16px;
            font-size: 17px;
            font-weight: 700;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            margin-top: 12px;
            transition: 0.2s;
        }
        .btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn-green  { background: var(--green); }
        .btn-danger { background: var(--red); }
        .notice-waiting {
            background: #fff;
            border: 2px dashed #ccc;
            color: #888;
            padding: 28px;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 28px;
            font-size: 14px;
        }
        .rejection-box {
            background: #fff5f5;
            border: 2px solid var(--red);
            border-radius: 12px;
            padding: 18px 20px;
            margin-top: 12px;
        }
        .rejection-box .rejection-label {
            font-weight: 800;
            color: var(--red);
            margin-bottom: 8px;
            font-size: 15px;
        }
        .rejection-box .rejection-text {
            color: #5a0000;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        #rejectModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.45);
            z-index: 999;
        }
        #rejectModal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-box {
            background: #fff;
            border-radius: 16px;
            padding: 28px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
        }
        .modal-box h3 { margin: 0 0 16px; color: var(--red); font-size: 20px; }
        .modal-box p  { margin: 0 0 12px; color: #555; font-size: 14px; }
        .modal-box textarea {
            width: 100%;
            border: 1.5px solid #ccc;
            border-radius: 10px;
            padding: 12px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 110px;
            outline: none;
        }
        .modal-box textarea:focus {
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(220,53,69,0.12);
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
        }
        .modal-actions button {
            flex: 1;
            padding: 12px;
            font-size: 15px;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }
        .modal-cancel  { background: #e2e8f0; color: #333; }
        .modal-confirm { background: var(--red); color: #fff; }
        .doc-link {
            color: var(--accent);
            font-weight: bold;
            text-decoration: none;
            display: block;
            margin-bottom: 10px;
            word-break: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }
        .doc-link:hover { text-decoration: underline; }
        .doc-item {
            margin-bottom: 18px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .doc-item-main { flex: 1; min-width: 200px; }
        .doc-remove-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            color: var(--red);
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
        }
        .doc-remove-label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        .reupload-box {
            border: 2px dashed var(--accent);
            background: #f4f9ff;
            border-radius: 10px;
            padding: 14px;
            margin-top: 4px;
        }
        .reupload-box input[type="file"] {
            width: 100%;
            font-size: 13px;
        }
        .reupload-hint {
            display: block;
            color: var(--muted);
            font-size: 12px;
            margin-top: 8px;
        }
        /* Native browser PDF viewer embedded via iframe — has its
           own toolbar/zoom/scroll, same as opening the PDF directly,
           just contained in a box on the page. */
        .doc-preview-pdf-frame {
            width: 100%;
            height: 800px;
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-top: 12px;
            display: block;
        }
        @media(max-width:640px) {
            .doc-preview-pdf-frame { height: 500px; }
        }
        .doc-preview-img {
            max-width: 100%;
            border-radius: 12px;
            border: 2px solid #93c5fd;
            margin-top: 12px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="page-header-inner">
            <div class="eyebrow">Letters In</div>
            <h1><?= htmlspecialchars($title) ?></h1>
        </div>
    </div>
    <!-- TOOLBAR: Back + Prev/Next + Counter, unified -->
    <div class="toolbar">
        <div class="toolbar-group">
            <a href="<?= $project_id ? "letterin_list.php?project_id=$project_id" : "letterincsr.php" ?>" class="tb-btn">
                ← Back to List
            </a>
        </div>
        <div class="toolbar-group">
            <?php if ($prevId): ?>
                <a href="<?= nav_url($prevId, $project_id) ?>" class="tb-btn" id="prevBtn" title="Previous (←)">← Previous</a>
            <?php else: ?>
                <span class="tb-btn disabled" id="prevBtn">← Previous</span>
            <?php endif; ?>
            <?php if ($currentPosition): ?>
                <span class="jump-box" title="Pilih untuk terus ke surat berkenaan">
                    <select id="jumpSelect" class="jump-select" onchange="jumpToSelected(this)">
                        <?php foreach ($navRows as $i => $row):
                            $pos = $i + 1;
                        ?>
                            <option value="<?= $row['id'] ?>" <?= $row['id'] === $id ? 'selected' : '' ?>>
                                <?= $pos ?>/<?= $totalCount ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </span>
            <?php else: ?>
                <span class="nav-counter"><?= htmlspecialchars($totalCount) ?> rekod</span>
            <?php endif; ?>
            <?php if ($nextId): ?>
                <a href="<?= nav_url($nextId, $project_id) ?>" class="tb-btn" id="nextBtn" title="Next (→)">Next →</a>
            <?php else: ?>
                <span class="tb-btn disabled" id="nextBtn">Next →</span>
            <?php endif; ?>
            <span class="kbd-hint" title="Guna anak panah papan kekunci untuk navigasi pantas">
                <kbd>←</kbd><kbd>→</kbd>
            </span>
        </div>
    </div>
    <div class="hero">
        <small>Reference Number</small>
        <h2><?= htmlspecialchars($data['reference_number'] ?: '—') ?></h2>
    </div>
    <!-- NOTE: enctype="multipart/form-data" added so the new file input
         below can actually submit uploaded files. Required for reupload. -->
    <form id="mainForm" action="update_letterin_status.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token"       value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="id"               value="<?= $id ?>">
        <input type="hidden" name="project_id"       value="<?= $project_id ?>">
        <input type="hidden" name="action"           id="formAction" value="">
        <input type="hidden" name="rejection_reason" id="rejectionReasonField" value="">
        <!-- GENERAL INFO -->
        <div class="card">
            <div class="section-title">General Information</div>
            <div class="grid-2">
                <div>
                    <div class="label">Created By</div>
                    <div class="value-box"><?= htmlspecialchars($data['created_by'] ?? 'N/A') ?></div>
                    <div class="label">Created At</div>
                    <div class="value-box">
                        <?= $data['created_at'] ? date('d/m/Y H:i', strtotime($data['created_at'])) : '-' ?>
                    </div>
                </div>
                <div>
                    <div class="label">Current Status</div>
                    <div class="value-box" style="font-weight:bold; color:var(--accent);">
                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $status))) ?>
                    </div>
                </div>
            </div>
            <div class="label">Assigned Verifier</div>
            <div class="value-box"><?= htmlspecialchars($data['verifier_name'] ?: 'N/A') ?></div>
        </div>
        <!-- LETTER DETAILS -->
        <div class="card">
            <div class="section-title">A. Letter In - Details</div>
            <div class="label">Title</div>
            <?php if ($can_edit): ?>
                <input type="text" name="title" class="value-box"
                    value="<?= htmlspecialchars($data['title']) ?>" required>
            <?php else: ?>
                <div class="value-box"><?= htmlspecialchars($data['title']) ?></div>
            <?php endif; ?>
            <div class="grid-2">
                <div>
                    <div class="label">Received Date</div>
                    <?php if ($can_edit): ?>
                        <input type="date" name="received_date" class="value-box"
                            value="<?= $data['received_date'] ? date('Y-m-d', strtotime($data['received_date'])) : '' ?>">
                    <?php else: ?>
                        <div class="value-box">
                            <?= $data['received_date'] ? date('d/m/Y', strtotime($data['received_date'])) : '-' ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="label">Letter Date</div>
                    <?php if ($can_edit): ?>
                        <input type="date" name="letter_date" class="value-box"
                            value="<?= $data['letter_date'] ? date('Y-m-d', strtotime($data['letter_date'])) : '' ?>">
                    <?php else: ?>
                        <div class="value-box">
                            <?= $data['letter_date'] ? date('d/m/Y', strtotime($data['letter_date'])) : '-' ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="label">Reference Number</div>
            <?php if ($can_edit): ?>
                <input type="text"
                       name="reference_number"
                       class="value-box"
                       value="<?= htmlspecialchars($data['reference_number']) ?>">
            <?php else: ?>
                <div class="value-box">
                    <?= htmlspecialchars($data['reference_number']) ?>
                </div>
            <?php endif; ?>
            <div class="label">From (Agency / Company)</div>
            <div class="value-box"><?= htmlspecialchars($data['letter_from']) ?></div>
            <div class="label">Received Mail Through</div>
            <div class="value-box"><?= htmlspecialchars($data['receive_mail']) ?></div>
            <div class="label">Remarks</div>
            <div class="value-box"><?= htmlspecialchars($data['remarks'] ?: '-') ?></div>
            <!-- DOCUMENT DISPLAY — handles both JSON array and single path.
                 PDF files are embedded via native <iframe> viewer, image
                 files via <img>, shown right below the link.
                 When $can_edit is true, each existing document also gets a
                 "Remove this file" checkbox (name="remove_document[]"),
                 and a reupload box is shown below the list so new files
                 (name="new_document[]") can be attached/replaced. -->
            <div class="label">Uploaded Document</div>
            <div class="value-box">
<?php
$previewable_ext = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
function render_doc_item($entry, $label, $index = 0, $can_edit = false) {
    global $previewable_ext;
    if (is_array($entry)) {
        $file          = $entry['path'] ?? '';
        $display_name  = $entry['name'] ?? basename($file);
    } else {
        $file          = trim($entry);
        $display_name  = basename($file);
    }
    $file = str_replace('\\', '/', $file);
    $file = ltrim($file, '/');
    $display_name = strlen($display_name) > 80
        ? substr($display_name, 0, 80) . '...'
        : $display_name;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $download_url = 'download_file.php?file=' . urlencode($file);
    $is_img = in_array($ext, $previewable_ext, true) && $ext !== 'pdf';
    $is_pdf = ($ext === 'pdf');
    ?>
    <div class="doc-item">
        <div class="doc-item-main">
            <a href="<?= htmlspecialchars($download_url) ?>" target="_blank" class="doc-link">
                📄 <?= htmlspecialchars($label) ?><?= htmlspecialchars($display_name) ?>
            </a>
            <?php if ($is_pdf): ?>
                <div id="pdfPreview<?= (int)$index ?>" style="display:none;">
                    <iframe
                        data-src="<?= htmlspecialchars($download_url) ?>"
                        class="doc-preview-pdf-frame"
                        loading="lazy">
                    </iframe>
                </div>
                <button
                    type="button"
                    onclick="togglePreview(<?= (int)$index ?>)"
                    style="
                        margin-top:8px;
                        padding:8px 14px;
                        border:none;
                        border-radius:8px;
                        background:#007bff;
                        color:white;
                        font-weight:bold;
                        cursor:pointer;
                    ">
                    👁 Preview PDF
                </button>
            <?php elseif ($is_img): ?>
                <img src="<?= htmlspecialchars($download_url) ?>" class="doc-preview-img" loading="lazy">
            <?php endif; ?>
        </div>
        <?php if ($can_edit): ?>
            <label class="doc-remove-label">
                <input type="checkbox" name="remove_document[]" value="<?= (int)$index ?>">
                🗑 Remove
            </label>
        <?php endif; ?>
    </div>
    <?php
}
$files = json_decode($data['document_path'] ?? '', true);
$json_ok = json_last_error() === JSON_ERROR_NONE;
if ($json_ok && !empty($files) && is_array($files)):
    foreach ($files as $index => $file):
        render_doc_item($file, "Document " . ($index + 1) . " — ", $index, $can_edit);
    endforeach;
elseif (!empty($data['document_path'])):
    render_doc_item($data['document_path'], "", 0, $can_edit);
else: ?>
    No document uploaded
<?php endif; ?>
            </div>
            <?php if ($can_edit): ?>
                <div class="label">Reupload / Add Document</div>
                <div class="reupload-box">
                    <input type="file" name="new_document[]" multiple
                           accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
                    <small class="reupload-hint">
                        Selecting file(s) here adds them to this letter's documents — it does not remove
                        the existing ones. Tick "Remove" above on any file you want taken off first,
                        then click Save Changes.
                    </small>
                </div>
            <?php endif; ?>
        </div>
        <!-- ACTION BUTTONS -->
<?php if (in_array($status, ['pending', 'submitted'])): ?>
    <!-- VERIFIER -->
    <?php if ($can_verify): ?>
        <button type="button" class="btn btn-green" onclick="submitVerify(this)">
            ✅ Verify &amp; Approve
        </button>
        <button type="button" class="btn btn-danger" onclick="openRejectModal()">
            ❌ Reject
        </button>
    <?php endif; ?>
    <!-- SAVE -->
    <?php if ($can_edit): ?>
        <button type="submit" class="btn btn-green" onclick="return setUpdate(this)">
            💾 Save Changes
        </button>
    <?php endif; ?>
    <!-- DELETE -->
    <?php if ($can_delete): ?>
        <button type="button" class="btn btn-danger" onclick="confirmDelete()">
            🗑 Delete Letter
        </button>
    <?php endif; ?>
<?php elseif ($status === 'verified'): ?>
    <div class="card">
        <div class="section-title">✅ Verified</div>
        <p>This letter has been verified. Process completed.</p>
        <?php if ($can_edit): ?>
            <button type="submit" class="btn btn-green" onclick="return setUpdate(this)">
                💾 Save Changes
            </button>
        <?php endif; ?>
        <?php if ($can_delete): ?>
            <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                🗑 Delete Letter
            </button>
        <?php endif; ?>
    </div>
<?php elseif ($status === 'rejected'): ?>
    <div class="card">
        <div class="section-title" style="color:var(--red); border-color:var(--red);">
            ❌ Rejected
        </div>
        <?php if (!empty($data['rejection_reason'])): ?>
            <div class="rejection-box">
                <div class="rejection-label">💬 Reason for Rejection</div>
                <div class="rejection-text"><?= htmlspecialchars($data['rejection_reason']) ?></div>
            </div>
        <?php endif; ?>
        <?php if ($can_edit): ?>
            <button type="submit" class="btn btn-green" onclick="return setUpdate(this)">
                💾 Save Changes
            </button>
        <?php endif; ?>
        <?php if ($can_delete): ?>
            <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                🗑 Delete Letter
            </button>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="notice-waiting">
        <p>⏳ Waiting for verification</p>
    </div>
<?php endif; ?>
    </form>
    <!-- DELETE FORM (POST instead of a GET link, protected by CSRF token) -->
    <form id="deleteForm" action="delete_letterin.php" method="POST" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
    </form>
    <!-- REJECT MODAL -->
    <div id="rejectModal">
        <div class="modal-box">
            <h3>❌ Reason for Rejection</h3>
            <p>Please provide a reason so the submitter knows what to correct.</p>
            <textarea id="rejectReasonInput" placeholder="Enter rejection reason..."></textarea>
            <div class="modal-actions">
                <button class="modal-cancel"  onclick="closeRejectModal()">Cancel</button>
                <button class="modal-confirm" onclick="submitReject(this)">Confirm Reject</button>
            </div>
        </div>
    </div>
    <script>
        function submitVerify(btn) {
            btn.disabled = true;
            document.getElementById('formAction').value = 'verify';
            document.getElementById('mainForm').submit();
        }
        function openRejectModal() {
            document.getElementById('rejectModal').classList.add('active');
            document.getElementById('rejectReasonInput').focus();
        }
        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
            document.getElementById('rejectReasonInput').value = '';
        }
        function submitReject(btn) {
            const reason = document.getElementById('rejectReasonInput').value.trim();
            if (!reason) {
                alert('Please enter a reason for rejection.');
                document.getElementById('rejectReasonInput').focus();
                return;
            }
            btn.disabled = true;
            document.getElementById('formAction').value = 'reject';
            document.getElementById('rejectionReasonField').value = reason;
            document.getElementById('mainForm').submit();
        }
        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) closeRejectModal();
        });
      function setUpdate(btn) {
    document.getElementById('formAction').value = 'update';
    // Hantar form secara terus
    document.getElementById('mainForm').submit();
    return false;
}
        function confirmDelete() {
            if (confirm('Are you sure want to delete this letter?')) {
                document.getElementById('deleteForm').submit();
            }
        }
        /* =========================================================
           PREV/NEXT KEYBOARD SHORTCUTS + JUMP TO LETTER (DROPDOWN)
           ========================================================= */
        const letterNavProjectId = <?= (int)$project_id ?>;
        function letterNavUrl(targetId) {
            let qs = 'id=' + targetId;
            if (letterNavProjectId) qs += '&project_id=' + letterNavProjectId;
            return '?' + qs;
        }
        function jumpToSelected(selectEl) {
            const targetId = selectEl.value;
            if (!targetId) return;
            window.location.href = letterNavUrl(targetId);
        }
        document.addEventListener('keydown', function (e) {
            // Don't hijack arrow keys while user is typing/selecting in a field
            const activeTag = (document.activeElement && document.activeElement.tagName || '').toLowerCase();
            const isTyping = ['input', 'textarea', 'select'].includes(activeTag)
                || (document.activeElement && document.activeElement.isContentEditable);
            if (isTyping) return;
            // Don't hijack while the reject modal is open
            const modal = document.getElementById('rejectModal');
            if (modal && modal.classList.contains('active')) return;
            if (e.key === 'ArrowLeft') {
                const prevBtn = document.getElementById('prevBtn');
                if (prevBtn && prevBtn.tagName === 'A') {
                    e.preventDefault();
                    prevBtn.click();
                }
            } else if (e.key === 'ArrowRight') {
                const nextBtn = document.getElementById('nextBtn');
                if (nextBtn && nextBtn.tagName === 'A') {
                    e.preventDefault();
                    nextBtn.click();
                }
            }
        });
        // Hover prefetch disabled.
        // This prevents unnecessary requests when moving over Previous/Next.
function togglePreview(index) {
    const preview = document.getElementById('pdfPreview' + index);
    const iframe = preview ? preview.querySelector('iframe') : null;
    if (!preview) return;
    if (preview.style.display === 'none' || preview.style.display === '') {
        // Load the PDF only after the user clicks Preview PDF.
        // This prevents large PDFs from being downloaded during initial page load.
        if (iframe && !iframe.src) {
            iframe.src = iframe.dataset.src;
        }
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}
    </script>

</body>
</html>
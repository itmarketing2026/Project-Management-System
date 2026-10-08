<?php
session_start();

$title = "View Letter Out";

require_once 'include/msqli_connect.php';

/* =========================================================
   LOGIN CHECK
   ========================================================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* =========================================================
   USER
   ========================================================= */
$user_id       = (int)($_SESSION['user_id'] ?? 0);
$user_fullname = $_SESSION['full_name'] ?? '';
$user_role     = $_SESSION['account_type'] ?? '';

$role = strtolower(trim($user_role));

$is_admin = in_array($role, [
    'client admin',
    'admin (qa/qc)'
]);

/* =========================================================
   RECORD ID
   ========================================================= */
$id = intval($_GET['id'] ?? 0);

if (!$id) {
    render_error("Invalid record. The Letter Out you're looking for could not be identified.");
}

/* =========================================================
   FETCH LETTER OUT
   ========================================================= */
$stmt = $db->prepare("
    SELECT *
    FROM letter_out_pending
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    render_error("A system error occurred. Please try again later.");
}

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$record = $result->fetch_assoc();

$stmt->close();

if (!$record) {
    render_error("No record found. This Letter Out may have been deleted.");
}

/* =========================================================
   PROJECT
   ========================================================= */
$project_id = (int)($record['project_id'] ?? 0);

/* =========================================================
   CREATOR CHECK
   Original system stores creator as full name.
   ========================================================= */
$is_creator = (
    isset($record['created_by']) &&
    $record['created_by'] === $user_fullname
);

/* =========================================================
   SUBMIT PERMISSION
   ========================================================= */
$status = strtolower(trim($record['status'] ?? ''));

$can_submit = (
    ($is_creator || $is_admin)
    &&
    in_array($status, ['submitted', 'rejected'], true)
);

/* =========================================================
   PREVIOUS / NEXT NAVIGATION
   Same concept as Letter In.
   Navigation is within the same project where project_id exists.
   ========================================================= */

$navRows = [];

if ($project_id > 0) {

    $navSql = "
        SELECT id
        FROM letter_out_pending
        WHERE project_id = ?
          AND status = 'submitted'
        ORDER BY created_at ASC, id ASC
    ";

    $stmtNav = $db->prepare($navSql);

    if ($stmtNav) {
        $stmtNav->bind_param("i", $project_id);
        $stmtNav->execute();

        $resNav = $stmtNav->get_result();

        while ($row = $resNav->fetch_assoc()) {
            $navRows[] = [
                'id' => (int)$row['id']
            ];
        }

        $stmtNav->close();
    }

} else {

    $navSql = "
        SELECT id
        FROM letter_out_pending
        WHERE status = 'submitted'
        ORDER BY created_at ASC, id ASC
    ";

    $stmtNav = $db->prepare($navSql);

    if ($stmtNav) {

        $stmtNav->execute();

        $resNav = $stmtNav->get_result();

        while ($row = $resNav->fetch_assoc()) {
            $navRows[] = [
                'id' => (int)$row['id']
            ];
        }

        $stmtNav->close();
    }
}

$allIds       = array_column($navRows, 'id');
$totalCount   = count($allIds);
$currentIndex = array_search($id, $allIds, true);

$prevId = null;
$nextId = null;
$currentPosition = null;

if ($currentIndex !== false) {

    $currentPosition = $currentIndex + 1;

    if ($currentIndex > 0) {
        $prevId = $allIds[$currentIndex - 1];
    }

    if ($currentIndex < ($totalCount - 1)) {
        $nextId = $allIds[$currentIndex + 1];
    }
}

/* =========================================================
   NAV URL
   ========================================================= */
function nav_url($targetId, $projectId = 0)
{
    $qs = 'id=' . (int)$targetId;

    if ($projectId > 0) {
        $qs .= '&project_id=' . (int)$projectId;
    }

    return htmlspecialchars('?' . $qs);
}

/* =========================================================
   STYLED ERROR PAGE
   ========================================================= */
function render_error(string $message, int $httpCode = 200): void
{
    http_response_code($httpCode);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Project Management System</title>

        <style>

            :root {
                --accent:#007bff;
                --red:#dc3545;
                --max:520px;
            }

            * {
                box-sizing:border-box;
            }

            body {
                font-family:Arial,sans-serif;
                background:linear-gradient(#f3f4f6,#eef1f5);
                margin:0;
                min-height:100vh;
                display:flex;
                flex-direction:column;
                align-items:center;
                color:#1e293b;
            }

            .page-header {
                width:100%;
                background:linear-gradient(135deg,#007bff,#0056b3);
                padding:20px 0 22px;
                margin-bottom:22px;
                box-shadow:0 4px 12px rgba(0,0,0,.1);
            }

            .page-header-inner {
                max-width:var(--max);
                margin:0 auto;
                padding:0 24px;
            }

            .eyebrow {
                color:rgba(255,255,255,.75);
                font-size:11px;
                letter-spacing:1.5px;
                text-transform:uppercase;
                font-weight:700;
                margin-bottom:4px;
            }

            .page-header h1 {
                color:#fff;
                font-weight:800;
                font-size:22px;
                margin:0;
            }

            .error-card {
                width:100%;
                max-width:var(--max);
                background:#fff;
                border-radius:16px;
                padding:32px 24px;
                margin:0 24px 28px;
                box-shadow:0 6px 18px rgba(0,0,0,.06);
                text-align:center;
            }

            .error-icon {
                width:56px;
                height:56px;
                border-radius:50%;
                background:#fff5f5;
                border:2px solid var(--red);
                color:var(--red);
                font-size:26px;
                display:flex;
                align-items:center;
                justify-content:center;
                margin:0 auto 16px;
            }

            .error-message {
                font-size:15px;
                color:#475569;
                line-height:1.6;
                margin-bottom:22px;
            }

            .error-back {
                display:inline-block;
                background:var(--accent);
                color:#fff;
                text-decoration:none;
                font-weight:700;
                font-size:14px;
                padding:10px 20px;
                border-radius:8px;
            }

            .error-back:hover {
                background:#0056b3;
            }

        </style>
    </head>

    <body>

        <div class="page-header">
            <div class="page-header-inner">
                <div class="eyebrow">Letters Out</div>
                <h1>Project Management System</h1>
            </div>
        </div>

        <div class="error-card">

            <div class="error-icon">!</div>

            <div class="error-message">
                <?= htmlspecialchars($message) ?>
            </div>

            <a href="javascript:history.back()" class="error-back">
                ← Go Back
            </a>

        </div>

    </body>
    </html>
    <?php
    exit;
}

/* =========================================================
   SHORT CACHE
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
            --accent:#007bff;
            --ink:#1e293b;
            --muted:#6b7785;
            --border:#dbe1e8;
            --surface:#ffffff;
            --bg:#f3f4f6;
            --green:#28a745;
            --red:#dc3545;
            --max:900px;
        }

        * {
            box-sizing:border-box;
        }

        body {
            font-family:Arial,sans-serif;
            background:linear-gradient(#f3f4f6,#eef1f5);
            padding:0 0 32px;
            display:flex;
            flex-direction:column;
            align-items:center;
            margin:0;
            color:var(--ink);
        }

        body > *:not(#rejectModal) {
            width:100%;
            max-width:var(--max);
        }

        /* =====================================================
           PAGE HEADER
           ===================================================== */

        .page-header {
            width:100%;
            background:linear-gradient(135deg,#007bff,#0056b3);
            padding:20px 0 22px;
            margin-bottom:22px;
            box-shadow:0 4px 12px rgba(0,0,0,.1);
        }

        .page-header-inner {
            max-width:var(--max);
            margin:0 auto;
            padding:0 24px;
        }

        .page-header .eyebrow {
            color:rgba(255,255,255,.75);
            font-size:11px;
            letter-spacing:1.5px;
            text-transform:uppercase;
            font-weight:700;
            margin-bottom:4px;
        }

        h1 {
            text-align:left;
            color:#fff;
            font-weight:800;
            font-size:24px;
            margin:0;
        }

        body > *:not(.page-header):not(#rejectModal) {
            padding-left:24px;
            padding-right:24px;
        }

        /* =====================================================
           TOOLBAR
           ===================================================== */

        .toolbar {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            flex-wrap:wrap;

            background:#fff;
            border-radius:12px;
            padding:10px 14px;
            margin-bottom:20px;

            box-shadow:0 4px 12px rgba(0,0,0,.05);
        }

        .toolbar-group {
            display:flex;
            align-items:center;
            gap:8px;
        }

        .tb-btn {
            display:inline-flex;
            align-items:center;
            gap:6px;

            background:var(--accent);
            color:#fff;

            border:none;
            padding:9px 16px;
            border-radius:8px;

            text-decoration:none;
            font-weight:bold;
            font-size:14px;

            white-space:nowrap;
            transition:background .15s;
        }

        .tb-btn:hover {
            background:#0056b3;
        }

        .tb-btn.disabled {
            background:#cbd5e1;
            color:#f1f5f9;
            pointer-events:none;
            cursor:not-allowed;
        }

        .nav-counter {
            font-weight:700;
            color:#334155;
            font-size:14px;
            letter-spacing:.2px;
            white-space:nowrap;
        }

        .jump-box {
            display:inline-flex;
            align-items:center;
            gap:6px;

            background:#f9fafb;
            border:1px solid #dbe1e8;
            border-radius:8px;

            padding:5px 10px;
            white-space:nowrap;
        }

        .jump-select {
            border:none;
            background:transparent;

            font-weight:700;
            font-size:14px;
            color:var(--accent);

            outline:none;
            width:64px;

            text-align:center;
            cursor:pointer;
        }

        .jump-select:focus {
            color:#0056b3;
        }

        .kbd-hint {
            display:inline-flex;
            align-items:center;
            gap:3px;

            margin-left:4px;
            opacity:.55;
        }

        .kbd-hint kbd {
            font-family:inherit;
            font-size:11px;
            font-weight:700;

            color:#475569;
            background:#eef1f5;

            border:1px solid #d5dbe3;
            border-radius:4px;

            padding:2px 6px;

            box-shadow:0 1px 0 #d5dbe3;
        }

        /* =====================================================
           CARD
           ===================================================== */

        .card {
            background:#fff;
            border-radius:16px;
            padding:24px;
            margin-bottom:28px;

            box-shadow:0 6px 18px rgba(0,0,0,.06);
        }

        .section-title {
            font-weight:800;
            color:var(--accent);

            border-bottom:2px solid var(--accent);

            padding-bottom:6px;
            margin-bottom:18px;
        }

        .label {
            font-weight:700;
            margin-bottom:6px;
            color:#1e293b;
        }

        .value-box {
            background:#f9f9f9;
            border:1px solid #ddd;

            border-radius:10px;

            padding:12px 14px;
            margin-bottom:14px;

            width:100%;
            display:block;

            font-family:inherit;
            font-size:14px;
            color:#1e293b;

            word-break:break-word;
        }

        input.value-box,
        textarea.value-box {
            outline:none;
            background:#fff;
            border-color:var(--accent);
            transition:box-shadow .2s;
        }

        input.value-box:focus,
        textarea.value-box:focus {
            box-shadow:0 0 0 3px rgba(0,123,255,.15);
        }

        .grid-2 {
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:18px;
        }

        /* =====================================================
           HERO
           ===================================================== */

        .hero {
            background:linear-gradient(135deg,#007bff,#0056b3);

            color:#fff;

            border-radius:18px;

            padding:22px;
            margin-bottom:26px;
        }

        .hero small {
            opacity:.75;
            font-size:12px;

            text-transform:uppercase;
            letter-spacing:1px;
            font-weight:normal;

            color:#fff;
        }

        .hero h2 {
            margin:6px 0 0;

            font-size:28px;
            font-weight:800;

            color:#fff;

            word-break:break-word;
        }

        .status-badge {
            display:inline-block;

            margin-top:10px;

            background:rgba(255,255,255,.18);

            border-radius:8px;

            padding:6px 14px;

            font-weight:bold;
            font-size:14px;
        }

        /* =====================================================
           FORM
           ===================================================== */

        select,
        input,
        textarea {
            width:100%;

            padding:12px;

            border-radius:10px;
            border:1.5px solid #ccc;

            font-size:15px;
            font-family:inherit;
        }

        textarea {
            min-height:110px;
            resize:vertical;
        }

        select:focus,
        input:focus,
        textarea:focus {
            outline:none;

            border-color:var(--accent);

            box-shadow:0 0 0 3px rgba(0,123,255,.15);
        }

        input[type="file"] {
            height:auto;
            padding:8px;
        }

        /* =====================================================
           BUTTON
           ===================================================== */

        .btn {
            width:100%;

            color:#fff;

            padding:16px;

            font-size:17px;
            font-weight:700;

            border:none;
            border-radius:14px;

            cursor:pointer;

            margin-top:12px;

            transition:.2s;
        }

        .btn:hover {
            filter:brightness(1.1);
            transform:translateY(-1px);
        }

        .btn-green {
            background:var(--green);
        }

        .btn-danger {
            background:var(--red);
        }

        /* =====================================================
           ACKNOWLEDGE / CANCEL
           ===================================================== */

        .card-ack {
            border-left:6px solid var(--green);
            background:#f6fffa;
        }

        .card-cancel {
            border-left:6px solid var(--red);
            background:#fff5f5;
        }

        .hidden {
            display:none !important;
        }

        /* =====================================================
           REJECTION
           ===================================================== */

        .rejection-box {
            background:#fff5f5;

            border:2px solid var(--red);
            border-radius:12px;

            padding:18px 20px;
            margin-top:12px;
        }

        .rejection-label {
            font-weight:800;
            color:var(--red);

            margin-bottom:8px;
            font-size:15px;
        }

        .rejection-text {
            color:#5a0000;

            font-size:14px;
            line-height:1.6;

            white-space:pre-wrap;
        }

        /* =====================================================
           WAITING
           ===================================================== */

        .notice-waiting {
            background:#fff;

            border:2px dashed #ccc;

            color:#888;

            padding:28px;

            border-radius:16px;

            text-align:center;

            margin-bottom:28px;

            font-size:14px;
        }

        /* =====================================================
           FILE
           ===================================================== */

        .file-help {
            display:block;

            color:#666;

            font-size:13px;

            margin-top:4px;
        }

        /* =====================================================
           RESPONSIVE
           ===================================================== */

        @media(max-width:640px) {

            .grid-2 {
                grid-template-columns:1fr;
            }

            .kbd-hint {
                display:none;
            }

        }

        @media(max-width:520px) {

            .toolbar {
                justify-content:center;
            }

            .toolbar-group {
                justify-content:center;
                width:100%;
            }

            .tb-btn {
                font-size:13px;
                padding:8px 12px;
            }

        }

    </style>

</head>

<body>

<!-- =========================================================
     PAGE HEADER
     ========================================================= -->

<div class="page-header">

    <div class="page-header-inner">

        <div class="eyebrow">
            Letters Out
        </div>

        <h1>
            <?= htmlspecialchars($title) ?>
        </h1>

    </div>

</div>


<!-- =========================================================
     TOOLBAR
     ========================================================= -->

<div class="toolbar">

    <div class="toolbar-group">

        <a
            href="letterout_list.php?project_id=<?= $project_id ?>"
            class="tb-btn"
        >
            ← Back to List
        </a>

    </div>


    <div class="toolbar-group">

        <?php if ($prevId): ?>

            <a
                href="<?= nav_url($prevId, $project_id) ?>"
                class="tb-btn"
                id="prevBtn"
                title="Previous (←)"
            >
                ← Previous
            </a>

        <?php else: ?>

            <span
                class="tb-btn disabled"
                id="prevBtn"
            >
                ← Previous
            </span>

        <?php endif; ?>


        <?php if ($currentPosition): ?>

            <span
                class="jump-box"
                title="Select record"
            >

                <select
                    id="jumpSelect"
                    class="jump-select"
                    onchange="jumpToSelected(this)"
                >

                    <?php foreach ($navRows as $i => $row): ?>

                        <?php
                        $pos = $i + 1;
                        ?>

                        <option
                            value="<?= (int)$row['id'] ?>"
                            <?= ((int)$row['id'] === $id) ? 'selected' : '' ?>
                        >
                            <?= $pos ?>/<?= $totalCount ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </span>

        <?php else: ?>

            <span class="nav-counter">
                <?= $totalCount ?> records
            </span>

        <?php endif; ?>


        <?php if ($nextId): ?>

            <a
                href="<?= nav_url($nextId, $project_id) ?>"
                class="tb-btn"
                id="nextBtn"
                title="Next (→)"
            >
                Next →
            </a>

        <?php else: ?>

            <span
                class="tb-btn disabled"
                id="nextBtn"
            >
                Next →
            </span>

        <?php endif; ?>


        <span
            class="kbd-hint"
            title="Use keyboard arrow keys"
        >
            <kbd>←</kbd>
            <kbd>→</kbd>
        </span>

    </div>

</div>


<!-- =========================================================
     HERO
     ========================================================= -->

<div class="hero">

    <small>
        Running Number
    </small>

    <h2>
        <?= htmlspecialchars($record['running_number'] ?? '—') ?>
    </h2>

</div>


<!-- =========================================================
     GENERAL INFORMATION
     ========================================================= -->

<div class="card">

    <div class="section-title">
        General Information
    </div>


    <div class="grid-2">

        <div>

            <div class="label">
                Created By
            </div>

            <div class="value-box">

                <?= htmlspecialchars($record['created_by'] ?? 'N/A') ?>

            </div>


            <div class="label">
                Created At
            </div>

            <div class="value-box">

                <?php if (!empty($record['created_at'])): ?>

                    <?= date(
                        'd/m/Y H:i',
                        strtotime($record['created_at'])
                    ) ?>

                <?php else: ?>

                    -

                <?php endif; ?>

            </div>

        </div>


        <div>

            <div class="label">
                Current Status
            </div>

            <div
                class="value-box"
                style="
                    font-weight:bold;
                    color:
                    <?= $status === 'rejected'
                        ? 'var(--red)'
                        : 'var(--accent)' ?>;
                "
            >

                <?= htmlspecialchars(
                    ucfirst(
                        str_replace('_', ' ', $status ?: 'Unknown')
                    )
                ) ?>

            </div>

        </div>

    </div>


    <?php if (!empty($record['backdate_reason'])): ?>

        <div class="label">
            Backdate Reason
        </div>

        <div class="value-box">

            <?= nl2br(
                htmlspecialchars($record['backdate_reason'])
            ) ?>

        </div>

    <?php endif; ?>


    <?php if (
        $status === 'rejected'
        &&
        !empty($record['rejection_reason'])
    ): ?>

        <div class="rejection-box">

            <div class="rejection-label">
                💬 Reason for Rejection
            </div>

            <div class="rejection-text">

                <?= htmlspecialchars(
                    $record['rejection_reason']
                ) ?>

            </div>

        </div>

    <?php endif; ?>

</div>


<!-- =========================================================
     LETTER OUT DETAILS
     ========================================================= -->

<div class="card">

    <div class="section-title">
        A. Letter Out - Request Running Number
    </div>


    <div class="label">
        Date Request Running Number
    </div>

    <div class="value-box">

        <?php if (!empty($record['request_date'])): ?>

            <?= date(
                'd/m/Y',
                strtotime($record['request_date'])
            ) ?>

        <?php else: ?>

            -

        <?php endif; ?>

    </div>


    <!-- =====================================================
         EDITABLE FORM
         ===================================================== -->

    <?php if ($can_submit): ?>

        <form
            method="post"
            action="save_acknowledge_cancel.php"
            enctype="multipart/form-data"
        >

            <input
                type="hidden"
                name="id"
                value="<?= (int)$record['id'] ?>"
            >


            <!-- TITLE -->

            <div class="label">

                Title
                <span style="color:red">*</span>

            </div>

            <input
                type="text"
                name="title"
                value="<?= htmlspecialchars(
                    $record['title'] ?? ''
                ) ?>"
                required
            >


            <!-- AGENCY / VERIFIER -->

            <div
                class="grid-2"
                style="margin-top:18px;"
            >

                <div>

                    <div class="label">
                        Agency
                    </div>

                    <input
                        type="text"
                        name="agency"
                        value="<?= htmlspecialchars(
                            $record['agency'] ?? ''
                        ) ?>"
                    >

                </div>


                <div>

                    <div class="label">
                        Verifier
                    </div>

                    <select
                        name="verifier"
                        id="verifierSelect"
                    >

                        <option value="">
                            -- Select Verifier --
                        </option>

                    </select>

                </div>

            </div>


            <!-- =================================================
                 ACKNOWLEDGE / CANCEL OPTION
                 ================================================= -->

            <div
                class="card"
                style="
                    margin-top:28px;
                    margin-bottom:20px;
                    box-shadow:none;
                    border:1px solid #e2e8f0;
                "
            >

                <div class="label">

                    Letter Out - Submit Acknowledge
                    <span style="color:red">*</span>

                </div>

                <select
                    id="ackType"
                    name="ackType"
                    required
                >

                    <option value="">
                        -- Select Option --
                    </option>

                    <option value="acknowledge">
                        Acknowledge
                    </option>

                    <option value="cancel">
                        Cancel
                    </option>

                </select>

            </div>


            <!-- =================================================
                 ACKNOWLEDGE SECTION
                 ================================================= -->

            <div
                id="ackSection"
                class="card card-ack hidden"
            >

                <div class="section-title">
                    B. Letter Out - Submit Acknowledge
                </div>


                <div class="grid-2">

                    <div>

                        <div class="label">
                            Letter Date
                        </div>

                        <input
                            type="date"
                            name="letter_date"
                        >

                    </div>


                    <div>

                        <div class="label">
                            Acknowledgement Date
                        </div>

                        <input
                            type="date"
                            name="ack_date"
                        >

                    </div>

                </div>


                <div
                    class="label"
                    style="margin-top:18px;"
                >
                    Send Letter Through
                </div>

                <select name="send_mail">

                    <option value="">
                        -- Select --
                    </option>

                    <option value="By Hand">
                        By Hand
                    </option>

                    <option value="Email">
                        Email
                    </option>

                    <option value="Post">
                        Post
                    </option>

                    <option value="Other">
                        Other
                    </option>

                </select>


                <div
                    class="label"
                    style="margin-top:18px;"
                >
                    Person Submitted Letter
                </div>

                <input
                    type="text"
                    name="submitted_by"
                >


                <div
                    class="label"
                    style="margin-top:18px;"
                >
                    Upload Letter (Compulsory)
                </div>

                <input
                    type="file"
                    name="letter_file[]"
                    multiple
                    required
                >

                <small class="file-help">
                    You can select multiple files.
                    Max size:
                    <?= htmlspecialchars(
                        ini_get('upload_max_filesize')
                    ) ?>
                </small>

            </div>


            <!-- =================================================
                 CANCEL SECTION
                 ================================================= -->

            <div
                id="cancelSection"
                class="card card-cancel hidden"
            >

                <div
                    class="section-title"
                    style="
                        color:var(--red);
                        border-color:var(--red);
                    "
                >
                    Cancel Letter
                </div>


                <div class="label">
                    Reason
                </div>

                <input
                    type="text"
                    name="cancel_reason"
                >

            </div>


            <!-- =================================================
                 SUBMIT
                 ================================================= -->

            <button
                type="submit"
                class="btn btn-green"
            >
                Submit
            </button>

        </form>


    <?php else: ?>


        <!-- =================================================
             READ ONLY DETAILS
             ================================================= -->

        <div class="label">
            Title
        </div>

        <div class="value-box">

            <?= htmlspecialchars(
                $record['title'] ?? '-'
            ) ?>

        </div>


        <div class="grid-2">

            <div>

                <div class="label">
                    Agency
                </div>

                <div class="value-box">

                    <?= htmlspecialchars(
                        $record['agency'] ?? '-'
                    ) ?>

                </div>

            </div>


            <div>

                <div class="label">
                    Verifier
                </div>

                <div class="value-box">

                    <?= htmlspecialchars(
                        $record['verifier'] ?? '—'
                    ) ?>

                </div>

            </div>

        </div>


    <?php endif; ?>

</div>


<!-- =========================================================
     WAITING / VIEW ONLY
     ========================================================= -->

<?php if (!$can_submit): ?>

    <?php if ($is_creator): ?>

        <div class="notice-waiting">

            <p
                style="
                    font-size:18px;
                    margin-bottom:6px;
                "
            >
                ⏳
                <strong>
                    Awaiting Verification
                </strong>
            </p>

            <p
                style="
                    margin:0;
                    font-size:14px;
                "
            >
                This record has been submitted and is
                currently being reviewed.
                No further edits are allowed at this stage.
            </p>

        </div>

    <?php else: ?>

        <div class="notice-waiting">

            <p
                style="
                    font-size:18px;
                    margin-bottom:6px;
                "
            >
                👁️
                <strong>
                    View Only
                </strong>
            </p>

            <p
                style="
                    margin:0;
                    font-size:14px;
                "
            >
                Only the creator of this record can submit
                acknowledgement or cancellation.
            </p>

        </div>

    <?php endif; ?>

<?php endif; ?>


<!-- =========================================================
     JAVASCRIPT
     ========================================================= -->

<script>

/* =========================================================
   ACKNOWLEDGE / CANCEL TOGGLE
   ========================================================= */

(function () {

    const ackType = document.getElementById('ackType');
    const ack = document.getElementById('ackSection');
    const cancel = document.getElementById('cancelSection');

    if (!ackType || !ack || !cancel) {
        return;
    }


    function toggleSections() {
		ack.classList.add('hidden');
		cancel.classList.add('hidden');

		const fileInput = ack.querySelector('input[name="letter_file[]"]');
		if (fileInput) fileInput.required = false;

		if (ackType.value === 'acknowledge') {
			ack.classList.remove('hidden');
			if (fileInput) fileInput.required = true;
		}

		if (ackType.value === 'cancel') {
			cancel.classList.remove('hidden');
		}
	}


    ackType.addEventListener(
        'change',
        toggleSections
    );


    document.addEventListener(
        'DOMContentLoaded',
        toggleSections
    );


    window.addEventListener(
        'pageshow',
        toggleSections
    );

})();


/* =========================================================
   JUMP TO RECORD
   ========================================================= */

function jumpToSelected(select) {

    const selectedId = select.value;

    if (!selectedId) {
        return;
    }


    const projectId = <?= $project_id ?>;


    let url = '?id=' + encodeURIComponent(selectedId);


    if (projectId > 0) {

        url += '&project_id=' + encodeURIComponent(projectId);

    }


    window.location.href = url;

}


/* =========================================================
   KEYBOARD NAVIGATION
   LEFT = PREVIOUS
   RIGHT = NEXT
   ========================================================= */

document.addEventListener(
    'keydown',
    function (event) {

        /*
         * Jangan trigger navigation ketika user sedang
         * menaip dalam input / textarea / select.
         */

        const tag = (
            event.target.tagName || ''
        ).toLowerCase();


        if (
            tag === 'input' ||
            tag === 'textarea' ||
            tag === 'select'
        ) {
            return;
        }


        if (event.key === 'ArrowLeft') {

            const prevBtn =
                document.getElementById('prevBtn');

            if (
                prevBtn &&
                prevBtn.tagName === 'A'
            ) {

                window.location.href =
                    prevBtn.href;

            }

        }


        if (event.key === 'ArrowRight') {

            const nextBtn =
                document.getElementById('nextBtn');

            if (
                nextBtn &&
                nextBtn.tagName === 'A'
            ) {

                window.location.href =
                    nextBtn.href;

            }

        }

    }
);


/* =========================================================
   VERIFIER DROPDOWN
   ========================================================= */

<?php if ($can_submit): ?>

const verifierSelect =
    document.getElementById('verifierSelect');

const currentVerifier =
    <?= json_encode(
        $record['verifier'] ?? '',
        JSON_UNESCAPED_UNICODE
    ) ?>;

const projectId =
    <?= (int)$project_id ?>;


if (verifierSelect) {

    fetch(
        'get_verifiers_by_project.php?project_id='
        + encodeURIComponent(projectId)
    )

    .then(function (response) {

        if (!response.ok) {
            throw new Error(
                'Unable to load verifiers'
            );
        }

        return response.json();

    })

    .then(function (data) {

        verifierSelect.innerHTML =
            '<option value="">-- Select Verifier --</option>';


        if (
            !Array.isArray(data)
            ||
            data.length === 0
        ) {

            verifierSelect.innerHTML =
                '<option value="">No verifier available</option>';

            return;

        }


        data.forEach(function (v) {

            const opt =
                document.createElement('option');


            opt.value =
                v.full_name;


            opt.textContent =
                v.full_name;


            if (
                v.full_name === currentVerifier
            ) {

                opt.selected = true;

            }


            verifierSelect.appendChild(opt);

        });

    })

    .catch(function () {

        verifierSelect.innerHTML =
            '<option value="">Unable to load verifiers</option>';

    });

}

<?php endif; ?>

</script>


</body>
</html>
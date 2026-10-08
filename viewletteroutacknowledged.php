<?php

session_start();

$title = "View Letter Out";

require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    header("Location: login.php");
    exit;
}

$user_id   = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['account_type'] ?? '';
$role      = strtolower(trim($user_role));

$id         = intval($_GET['id'] ?? 0);
$project_id = intval($_GET['project_id'] ?? 0);

if (!$id) {
    die("Invalid record.");
}


/* =========================================================
   MAIN RECORD
========================================================= */

$sql = "
    SELECT
        l.*,
        u.full_name AS verifier_display_name,
        c.full_name AS creator_display_name
    FROM letter_out_pending l
    LEFT JOIN users u
        ON l.verifier = u.id
    LEFT JOIN users c
        ON l.created_by = c.id
    WHERE l.id = ?
";

$stmt = $db->prepare($sql);

if (!$stmt) {
    die("Database error.");
}

$stmt->bind_param("i", $id);
$stmt->execute();

$pending = $stmt->get_result()->fetch_assoc();

$stmt->close();


if (!$pending) {
    die("No record found.");
}


/* =========================================================
   PROJECT ID
========================================================= */

$project_id = (int)($pending['project_id'] ?? $project_id);


/* =========================================================
   DETAILS
========================================================= */

$detail = null;


/* ACKNOWLEDGE */

if (
    in_array(
        $pending['status'],
        [
            'acknowledged',
            'verified_verifier',
            'verified_final'
        ],
        true
    )
) {

    $stmt2 = $db->prepare("
        SELECT *
        FROM letter_out_acknowledge
        WHERE letter_out_id = ?
    ");

    if ($stmt2) {

        $stmt2->bind_param("i", $id);
        $stmt2->execute();

        $detail =
            $stmt2->get_result()->fetch_assoc();

        $stmt2->close();
    }
}


/* CANCEL */

if ($pending['status'] === 'cancelled') {

    $stmt2 = $db->prepare("
        SELECT *
        FROM letter_out_cancel
        WHERE letter_out_id = ?
    ");

    if ($stmt2) {

        $stmt2->bind_param("i", $id);
        $stmt2->execute();

        $detail =
            $stmt2->get_result()->fetch_assoc();

        $stmt2->close();
    }
}


/* =========================================================
   WORKFLOW LOGIC
========================================================= */

$stmt_name = $db->prepare("
    SELECT full_name
    FROM users
    WHERE id = ?
");

$stmt_name->bind_param(
    "i",
    $user_id
);

$stmt_name->execute();

$user_row =
    $stmt_name->get_result()->fetch_assoc();

$stmt_name->close();

$user_fullname =
    $user_row['full_name'] ?? '';


$is_final_role = in_array(
    $user_role,
    [
        'Admin (QA/QC)',
        'HRA',
        'Client Admin'
    ],
    true
);


/*
 * If no verifier assigned,
 * Management created the letter.
 */
$is_management_letter =
    empty($pending['verifier']);


/*
 * Assigned verifier
 */
$can_assigned_verifier_verify = (
    !$is_management_letter
    &&
    $pending['status'] === 'acknowledged'
    &&
    $user_fullname === $pending['verifier']
);


/*
 * Admin can send back
 */
$can_admin_sendback_at_acknowledged = (
    in_array(
        $pending['status'],
        [
            'acknowledged',
            'verified_final'
        ],
        true
    )
    &&
    $is_final_role
    &&
    !$can_assigned_verifier_verify
);


/*
 * Final verification
 */
$can_final_verify = (
    $is_final_role
    &&
    (
        $pending['status'] === 'verified_verifier'
        ||
        (
            $is_management_letter
            &&
            $pending['status'] === 'acknowledged'
        )
    )
);


/*
 * Editing allowed during final verification
 */
$can_edit = $can_final_verify;


/* =========================================================
   PREVIOUS / NEXT + COUNTER
========================================================= */

$navRows = [];


if ($project_id) {

    $navSql = "
        SELECT id
        FROM letter_out_pending
        WHERE project_id = ?
        ORDER BY created_at ASC, id ASC
    ";

    $stmtNav = $db->prepare($navSql);

    $stmtNav->bind_param(
        "i",
        $project_id
    );

} else {

    $navSql = "
        SELECT id
        FROM letter_out_pending
        ORDER BY created_at ASC, id ASC
    ";

    $stmtNav = $db->prepare($navSql);
}


$stmtNav->execute();

$resNav =
    $stmtNav->get_result();


while ($row = $resNav->fetch_assoc()) {

    $navRows[] = [
        'id' => (int)$row['id']
    ];
}


$stmtNav->close();


$allIds =
    array_column(
        $navRows,
        'id'
    );


$totalCount =
    count($allIds);


$currentIndex =
    array_search(
        $id,
        $allIds,
        true
    );


$prevId = null;
$nextId = null;
$currentPosition = null;


if ($currentIndex !== false) {

    $currentPosition =
        $currentIndex + 1;


    if ($currentIndex > 0) {

        $prevId =
            $allIds[
                $currentIndex - 1
            ];
    }


    if (
        $currentIndex
        <
        ($totalCount - 1)
    ) {

        $nextId =
            $allIds[
                $currentIndex + 1
            ];
    }
}


/* =========================================================
   NAV URL
========================================================= */

function nav_url(
    $targetId,
    $projectId
) {

    $qs =
        'id=' .
        (int)$targetId;


    if ($projectId) {

        $qs .=
            '&project_id=' .
            (int)$projectId;
    }


    return htmlspecialchars(
        '?' . $qs
    );
}


/* =========================================================
   PRIVATE CACHE
========================================================= */

header(
    'Cache-Control: private, max-age=5'
);

?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>
    Project Management System
</title>


<style>

/* =========================================================
   SAME BASE STYLE AS LETTER IN
========================================================= */

:root {

    --accent:#007bff;

    --accent-light:#007bff;

    --ink:#1e293b;

    --muted:#6b7785;

    --border:#dbe1e8;

    --surface:#ffffff;

    --bg:#f3f4f6;

    --green:#28a745;

    --red:#dc3545;

    --orange:#f59e0b;

    --max:900px;
}


* {

    box-sizing:border-box;

    margin:0;

    padding:0;
}


html,
body {

    overflow-x:hidden;
}


body {

    font-family:
        Arial,
        sans-serif;

    background:
        linear-gradient(
            #f3f4f6,
            #eef1f5
        );

    padding:
        0
        0
        32px;

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


/* =========================================================
   PAGE HEADER — SAME AS LETTER IN
========================================================= */

.page-header {

    width:100%;

    background:
        linear-gradient(
            135deg,
            #007bff,
            #0056b3
        );

    padding:
        20px
        0
        22px;

    margin-bottom:22px;

    box-shadow:
        0
        4px
        12px
        rgba(
            0,
            0,
            0,
            .1
        );
}


.page-header-inner {

    max-width:var(--max);

    margin:0 auto;

    padding:
        0
        24px;
}


.page-header .eyebrow {

    color:
        rgba(
            255,
            255,
            255,
            .75
        );

    font-size:11px;

    letter-spacing:1.5px;

    text-transform:uppercase;

    font-weight:700;

    margin-bottom:4px;
}


.page-header h1 {

    text-align:left;

    color:#fff;

    font-weight:800;

    font-size:24px;

    margin:0;
}


/* =========================================================
   BODY PADDING — SAME AS LETTER IN
========================================================= */

body > *:not(.page-header):not(#rejectModal) {

    padding-left:24px;

    padding-right:24px;
}


/* =========================================================
   TOOLBAR — SAME AS LETTER IN
========================================================= */

.toolbar {

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:12px;

    flex-wrap:wrap;

    background:#fff;

    border-radius:12px;

    padding:
        10px
        14px;

    margin-bottom:20px;

    box-shadow:
        0
        4px
        12px
        rgba(
            0,
            0,
            0,
            .05
        );
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

    background:
        var(--accent);

    color:#fff;

    border:none;

    padding:
        9px
        16px;

    border-radius:8px;

    text-decoration:none;

    font-weight:bold;

    font-size:14px;

    white-space:nowrap;

    transition:
        background
        .15s;
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

    border:
        1px solid
        #dbe1e8;

    border-radius:8px;

    padding:
        5px
        10px;

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

    border:
        1px solid
        #d5dbe3;

    border-radius:4px;

    padding:
        2px
        6px;

    box-shadow:
        0
        1px
        0
        #d5dbe3;
}


/* =========================================================
   HERO — SAME AS LETTER IN
========================================================= */

.hero {

    background:
        linear-gradient(
            135deg,
            #007bff,
            #0056b3
        );

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

    margin:
        6px
        0
        0;

    font-size:28px;

    font-weight:800;

    color:#fff;

    word-break:break-word;
}


/* =========================================================
   CARD
========================================================= */

.card {

    background:#fff;

    border-radius:16px;

    padding:24px;

    margin-bottom:28px;

    box-shadow:
        0
        6px
        18px
        rgba(
            0,
            0,
            0,
            .06
        );
}


.section-title {

    font-weight:800;

    color:var(--accent);

    border-bottom:
        2px solid
        var(--accent);

    padding-bottom:6px;

    margin-bottom:18px;
}


.label {

    font-weight:700;

    margin-bottom:6px;
}


.value-box {

    background:#f9f9f9;

    border:
        1px solid
        #ddd;

    border-radius:10px;

    padding:
        12px
        14px;

    margin-bottom:14px;

    width:100%;

    display:block;

    font-family:inherit;

    font-size:14px;

    color:#1e293b;

    line-height:1.5;

    word-break:break-word;
}


input.value-box {

    outline:none;

    background:#fff;

    border-color:var(--accent);

    transition:
        box-shadow
        .2s;
}


input.value-box:focus {

    box-shadow:
        0
        0
        0
        3px
        rgba(
            0,
            123,
            255,
            .15
        );
}


.grid-2 {

    display:grid;

    grid-template-columns:
        1fr
        1fr;

    gap:18px;
}


/* =========================================================
   BUTTONS — SAME STYLE
========================================================= */

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

    transform:
        translateY(-1px);
}


.btn:disabled {

    opacity:.6;

    cursor:not-allowed;

    transform:none;
}


.btn-blue {

    background:
        var(--accent);
}


.btn-green {

    background:
        var(--green);
}


.btn-danger {

    background:
        var(--red);
}


.btn-orange {

    background:
        var(--orange);
}


/* =========================================================
   NOTICE
========================================================= */

.notice {

    padding:
        10px
        16px;

    border-radius:8px;

    font-weight:bold;

    margin-top:10px;
}


.notice-success {

    background:#e6ffe6;

    border:
        1px solid
        #00b300;

    color:#008000;
}


.notice-red {

    background:#ffe6e6;

    border:
        1px solid
        #dc3545;

    color:#b30000;
}


.notice-waiting {

    background:#fff;

    border:
        2px dashed
        #ccc;

    color:#888;

    padding:28px;

    border-radius:16px;

    text-align:center;

    margin-bottom:28px;

    font-size:14px;
}


/* =========================================================
   REJECTION
========================================================= */

.rejection-box {

    background:#fff5f5;

    border:
        2px solid
        var(--red);

    border-radius:12px;

    padding:
        18px
        20px;

    margin-bottom:20px;
}


.rejection-label {

    font-weight:800;

    color:var(--red);

    margin-bottom:8px;

    font-size:.85rem;

    text-transform:uppercase;
}


.rejection-text {

    color:#5a0000;

    font-size:1rem;

    line-height:1.6;

    white-space:pre-wrap;
}


/* =========================================================
   FILE LIST
========================================================= */

.file-list {

    margin-top:10px;

    display:flex;

    flex-wrap:wrap;

    gap:8px;
}


.file-item {

    display:flex;

    align-items:center;

    gap:8px;

    padding:
        8px
        12px;

    background:#f1f5ff;

    border:
        1px solid
        #dbeafe;

    border-radius:10px;

    text-decoration:none;

    color:#1e40af;

    font-size:13px;

    font-weight:500;

    transition:
        all
        .2s
        ease;

    max-width:100%;
}


.file-item:hover {

    background:#2563eb;

    color:#fff;

    transform:
        translateY(-1px);

    box-shadow:
        0
        4px
        10px
        rgba(
            0,
            0,
            0,
            .1
        );
}


.file-icon {

    font-size:16px;
}


/* =========================================================
   MODAL — SAME STYLE
========================================================= */

#rejectModal {

    display:none;

    position:fixed;

    top:0;
    left:0;

    width:100vw;
    height:100vh;

    background:
        rgba(
            0,
            0,
            0,
            .45
        );

    z-index:9999;

    align-items:center;

    justify-content:center;
}


#rejectModal.active {

    display:flex;
}


.modal-box {

    background:#fff;

    border-radius:16px;

    padding:28px;

    width:100%;

    max-width:480px;

    box-shadow:
        0
        12px
        40px
        rgba(
            0,
            0,
            0,
            .2
        );
}


.modal-box h3 {

    margin:
        0
        0
        16px;

    color:var(--red);

    font-size:20px;
}


.modal-box p {

    margin:
        0
        0
        12px;

    color:#555;

    font-size:14px;
}


.modal-box textarea {

    width:100%;

    border:
        1.5px solid
        #ccc;

    border-radius:10px;

    padding:12px;

    font-size:14px;

    font-family:inherit;

    resize:vertical;

    min-height:110px;

    outline:none;
}


.modal-box textarea:focus {

    border-color:var(--red);

    box-shadow:
        0
        0
        0
        3px
        rgba(
            220,
            53,
            69,
            .12
        );
}


.modal-actions {

    display:flex;

    gap:10px;

    margin-top:16px;
}


.modal-actions button {

    flex:1;

    padding:12px;

    font-size:15px;

    font-weight:700;

    border:none;

    border-radius:10px;

    cursor:pointer;
}


.modal-cancel {

    background:#e2e8f0;

    color:#333;
}


.modal-confirm {

    background:var(--red);

    color:#fff;
}


/* =========================================================
   MOBILE
========================================================= */

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

}

</style>

</head>


<body>


<!-- =======================================================
     HEADER
======================================================= -->

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


<!-- =======================================================
     TOOLBAR
======================================================= -->

<div class="toolbar">


    <!-- BACK -->

    <div class="toolbar-group">

        <a
            href="letterout_list.php?project_id=<?= (int)$project_id ?>"
            class="tb-btn"
        >
            ← Back to List
        </a>

    </div>


    <!-- NAVIGATION -->

    <div class="toolbar-group">


        <!-- PREVIOUS -->

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


        <!-- COUNTER -->

        <?php if ($currentPosition): ?>

            <span
                class="jump-box"
                title="Pilih untuk terus ke surat berkenaan"
            >

                <select
                    id="jumpSelect"
                    class="jump-select"
                    onchange="jumpToSelected(this)"
                >

                    <?php foreach (
                        $navRows
                        as $i => $row
                    ): ?>

                        <?php
                        $pos = $i + 1;
                        ?>

                        <option
                            value="<?= (int)$row['id'] ?>"
                            <?= (
                                $row['id'] === $id
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= $pos ?>/<?= $totalCount ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </span>

        <?php else: ?>

            <span class="nav-counter">

                <?= htmlspecialchars(
                    $totalCount
                ) ?>

                rekod

            </span>

        <?php endif; ?>


        <!-- NEXT -->

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


        <!-- KEYBOARD -->

        <span
            class="kbd-hint"
            title="Guna anak panah papan kekunci untuk navigasi pantas"
        >

            <kbd>←</kbd>
            <kbd>→</kbd>

        </span>

    </div>

</div>


<!-- =======================================================
     HERO
======================================================= -->

<div class="hero">

    <small>
        Running Number
    </small>

    <h2>

        <?= htmlspecialchars(
            $pending['running_number']
            ?? '—'
        ) ?>

    </h2>

</div>


<!-- =======================================================
     SENT BACK MESSAGE
======================================================= -->

<?php if (
    ($_GET['msg'] ?? '')
    ===
    'sentback'
): ?>

    <div
        class="notice notice-success"
        style="margin-bottom:16px;"
    >

        ✅ Record has been sent back successfully.

    </div>

<?php endif; ?>


<!-- =======================================================
     MAIN FORM
======================================================= -->

<form
    id="mainForm"
    action="update_letterout_status.php"
    method="POST"
>


<input
    type="hidden"
    name="id"
    value="<?= (int)$id ?>"
>


<input
    type="hidden"
    name="action"
    id="formAction"
    value=""
>


<input
    type="hidden"
    name="rejection_reason"
    id="rejectionReasonField"
    value=""
>


<!-- =======================================================
     GENERAL INFORMATION
======================================================= -->

<div class="card">

    <div class="section-title">
        General Information
    </div>


    <!-- REJECTION -->

    <?php if (
        $pending['status'] === 'rejected'
        &&
        !empty(
            $pending['rejection_reason']
        )
    ): ?>

        <div class="rejection-box">

            <div class="rejection-label">

                💬 Reason for Rejection

            </div>

            <div class="rejection-text">

                <?= htmlspecialchars(
                    $pending['rejection_reason']
                ) ?>

            </div>

        </div>

    <?php endif; ?>


    <div class="grid-2">


        <!-- CREATED -->

        <div>

            <div class="label">
                Created By
            </div>

            <div class="value-box">

                <?= htmlspecialchars(
                    $pending['creator_display_name']
                    ??
                    $pending['created_by']
                    ??
                    'N/A'
                ) ?>

            </div>

        </div>


        <!-- CREATED AT -->

        <div>

            <div class="label">
                Created At
            </div>

            <div class="value-box">

                <?= !empty(
                    $pending['created_at']
                )
                    ? date(
                        'd/m/Y H:i',
                        strtotime(
                            $pending['created_at']
                        )
                    )
                    : '-'
                ?>

            </div>

        </div>

    </div>


    <!-- STATUS -->

    <div class="label">
        Current Status
    </div>

    <div
        class="value-box"
        style="
            font-weight:bold;
            color:
            <?= $pending['status'] === 'rejected'
                ? 'var(--red)'
                : 'var(--accent)'
            ?>;
        "
    >

        <?= htmlspecialchars(
            ucfirst(
                str_replace(
                    '_',
                    ' ',
                    $pending['status']
                )
            )
        ) ?>

    </div>


    <!-- VERIFIER -->

    <div class="label">
        Assigned Verifier
    </div>

    <div class="value-box">

        <?= htmlspecialchars(
            $pending['verifier_display_name']
            ??
            $pending['verifier']
            ??
            'N/A'
        ) ?>

    </div>

</div>


<!-- =======================================================
     SECTION A
======================================================= -->

<div class="card">

    <div class="section-title">

        A. Letter Out - Request Running Number

    </div>


    <!-- DATE REQUEST -->

    <div class="label">
        Date Request
    </div>

    <div class="value-box">

        <?= !empty(
            $pending['request_date']
        )
            ? date(
                'd/m/Y',
                strtotime(
                    $pending['request_date']
                )
            )
            : '-'
        ?>

    </div>


    <!-- TITLE -->

    <div class="label">
        Title
    </div>

    <?php if ($can_edit): ?>

        <input
            type="text"
            name="title"
            class="value-box"
            value="<?= htmlspecialchars(
                $pending['title']
                ??
                ''
            ) ?>"
            required
        >

    <?php else: ?>

        <div class="value-box">

            <?= htmlspecialchars(
                $pending['title']
                ??
                ''
            ) ?>

        </div>

    <?php endif; ?>


    <!-- AGENCY + VERIFIER -->

    <div class="grid-2">


        <div>

            <div class="label">
                Agency
            </div>

            <div class="value-box">

                <?= htmlspecialchars(
                    $pending['agency']
                    ??
                    'N/A'
                ) ?>

            </div>

        </div>


        <div>

            <div class="label">
                Verifier
            </div>

            <div class="value-box">

                <?= htmlspecialchars(
                    $pending[
                        'verifier_display_name'
                    ]
                    ??
                    $pending['verifier']
                    ??
                    'N/A'
                ) ?>

            </div>

        </div>


    </div>

</div>


<!-- =======================================================
     SECTION B
======================================================= -->

<?php if ($detail): ?>

<div class="card">

    <div class="section-title">

        B. Letter Out -
        <?= $pending['status'] === 'cancelled'
            ? 'Cancel Details'
            : 'Submit Acknowledge'
        ?>

    </div>


    <?php if (
        $pending['status']
        !==
        'cancelled'
    ): ?>


        <!-- LETTER DATE + ACKNOWLEDGEMENT -->

        <div class="grid-2">


            <div>

                <div class="label">
                    Letter Date
                </div>

                <?php if ($can_edit): ?>

                    <input
                        type="date"
                        name="letter_date"
                        class="value-box"
                        value="<?= !empty(
                            $detail['letter_date']
                        )
                            ? date(
                                'Y-m-d',
                                strtotime(
                                    $detail['letter_date']
                                )
                            )
                            : ''
                        ?>"
                        required
                    >

                <?php else: ?>

                    <div class="value-box">

                        <?= !empty(
                            $detail['letter_date']
                        )
                            ? date(
                                'd/m/Y',
                                strtotime(
                                    $detail['letter_date']
                                )
                            )
                            : '-'
                        ?>

                    </div>

                <?php endif; ?>

            </div>


            <div>

                <div class="label">
                    Acknowledgement Date
                </div>

                <div class="value-box">

                    <?= !empty(
                        $detail['ack_date']
                    )
                        ? date(
                            'd/m/Y',
                            strtotime(
                                $detail['ack_date']
                            )
                        )
                        : '-'
                    ?>

                </div>

            </div>


        </div>


        <!-- SEND MAIL -->

        <div class="label">
            Send Mail Through
        </div>

        <div class="value-box">

            <?= htmlspecialchars(
                $detail['send_mail']
                ??
                'N/A'
            ) ?>

        </div>


        <!-- SUBMITTED BY -->

        <div class="label">
            Submitted By
        </div>

        <div class="value-box">

            <?= htmlspecialchars(
                $detail['submitted_by']
                ??
                'N/A'
            ) ?>

        </div>


        <!-- DOCUMENT -->

        <div class="label">
            Document
        </div>


        <?php

        $raw =
            $detail['letter_file']
            ??
            '';

        $files =
            json_decode(
                $raw,
                true
            );

        ?>


        <?php if (empty($raw)): ?>

            <span
                style="
                    color:#999;
                "
            >
                No file
            </span>


        <?php elseif (
            is_array($files)
        ): ?>


            <div class="file-list">

                <?php foreach (
                    $files
                    as $file
                ): ?>

                    <?php

                    $name =
                        basename(
                            trim(
                                (string)$file
                            )
                        );


                    $url =
                        "download_file.php?file="
                        .
                        urlencode($name)
                        .
                        "&source=projectfiles";

                    ?>


                    <a
                        href="<?= htmlspecialchars(
                            $url
                        ) ?>"
                        target="_blank"
                        class="file-item"
                    >

                        <span class="file-icon">
                            📄
                        </span>

                        <span>
                            <?= htmlspecialchars(
                                $name
                            ) ?>
                        </span>

                    </a>

                <?php endforeach; ?>

            </div>


        <?php else: ?>


            <?php

            $name =
                basename(
                    trim(
                        (string)$raw
                    )
                );


            $url =
                "download_file.php?file="
                .
                urlencode($name)
                .
                "&source=projectfiles";

            ?>


            <div class="file-list">

                <a
                    href="<?= htmlspecialchars(
                        $url
                    ) ?>"
                    target="_blank"
                    class="file-item"
                >

                    <span class="file-icon">
                        📄
                    </span>

                    <span>
                        <?= htmlspecialchars(
                            $name
                        ) ?>
                    </span>

                </a>

            </div>


        <?php endif; ?>


    <?php else: ?>


        <!-- CANCEL DETAILS -->

        <div class="label">
            Cancel Reason
        </div>

        <div class="value-box">

            <?= htmlspecialchars(
                $detail['cancel_reason']
                ??
                '-'
            ) ?>

        </div>


        <div class="notice notice-red">

            🛑 This letter has been cancelled.

        </div>


    <?php endif; ?>

</div>

<?php endif; ?>


<!-- =======================================================
     ACTION BUTTONS
======================================================= -->


<?php if (
    $can_assigned_verifier_verify
): ?>


    <!-- VERIFIER -->

    <button
        type="button"
        class="btn btn-green"
        onclick="
            submitVerify(
                'verify_verifier'
            )
        "
    >

        ✅ Verify
        (As Assigned Verifier)

    </button>


    <button
        type="button"
        class="btn btn-danger"
        onclick="openRejectModal()"
    >

        ↩ Reject / Send Back

    </button>


<?php elseif (
    $can_admin_sendback_at_acknowledged
): ?>


    <!-- ADMIN WATCHING -->

    <div
        class="notice-waiting"
        style="margin-bottom:0;"
    >

<?php if ($pending['status'] === 'verified_final'): ?>

        <p
            style="
                font-size:18px;
                margin-bottom:6px;
            "
        >

            🔄
            <strong>
                Fully Verified
            </strong>

        </p>

        <p style="margin:0;">

            This letter has been fully verified.
            You may send this back to Pending
            if changes are required.

        </p>

    <?php else: ?>

        <p
            style="
                font-size:18px;
                margin-bottom:6px;
            "
        >

            ⏳
            <strong>
                Waiting for Assigned Verifier
            </strong>

        </p>

        <p style="margin:0;">

            The assigned verifier

            <strong>
                <?= htmlspecialchars(
                    $pending['verifier_display_name']
                    ??
                    $pending['verifier']
                    ??
                    'N/A'
                ) ?>
            </strong>

            has not verified yet.

            You may send this back to pending
            if needed.

        </p>

    <?php endif; ?>

    </div>


    <button
        type="button"
        class="btn btn-orange"
        onclick="
            submitVerify(
                'sendback_pending'
            )
        "
    >

        ⬅ Send Back

    </button>


<?php elseif (
    $can_final_verify
): ?>


    <!-- FINAL APPROVAL -->

    <button
        type="button"
        class="btn btn-green"
        onclick="
            submitVerify(
                'verify_final'
            )
        "
    >

        ✅ Save &amp; Final Approve

    </button>


    <button
        type="button"
        class="btn btn-danger"
        onclick="openRejectModal()"
    >

        ↩ Reject / Send Back

    </button>


<?php elseif (
    $pending['status']
    ===
    'verified_final'
): ?>


    <div class="card">

        <div class="section-title">

            ✅ Fully Verified

        </div>

        <p>

            This record has been fully verified.
            Process completed.

        </p>

    </div>


<?php elseif (
    $pending['status']
    ===
    'rejected'
): ?>


    <div
        class="notice-waiting"
        style="
            border-color:var(--red);
            color:var(--red);
        "
    >

        <p
            style="
                font-size:18px;
                margin-bottom:6px;
            "
        >

            ❌
            <strong>
                Rejected
            </strong>

        </p>


        <p style="margin:0;">

            Awaiting creator to resubmit
            based on the reason above.

        </p>

    </div>


<?php elseif (
    $pending['status']
    ===
    'cancelled'
): ?>


    <div class="card">

        <div class="section-title">

            🛑 Cancelled

        </div>

        <p>

            This letter has been cancelled.
            No further actions available.

        </p>

    </div>


<?php elseif (
    $pending['status'] === 'acknowledged'
): ?>

    <div class="notice-waiting">

        <p
            style="
                font-size:18px;
                margin-bottom:6px;
            "
        >

            ⏳
            <strong>
                Waiting for Assigned Verifier
            </strong>

        </p>

        <p style="margin:0;">

            The assigned verifier

            <strong>
                <?= htmlspecialchars(
                    $pending['verifier_display_name']
                    ??
                    $pending['verifier']
                    ??
                    'N/A'
                ) ?>
            </strong>

            has not verified yet.

            You may send this back to pending
            if needed.

        </p>

    </div>


    <button
        type="button"
        class="btn btn-orange"
        onclick="
            submitVerify(
                'sendback_pending'
            )
        "
    >
        ⬅ Send Back
    </button>


<?php elseif (
    $pending['status'] === 'verified_verifier'
): ?>

    <div class="notice-waiting">

        <p
            style="
                font-size:18px;
                margin-bottom:6px;
            "
        >

            ⏳
            <strong>
                Waiting for Admin (QA/QC) Verification
            </strong>

        </p>

        <p style="margin:0;">

            The assigned verifier

            <strong>
                <?= htmlspecialchars(
                    $pending['verifier_display_name']
                    ??
                    $pending['verifier']
                    ??
                    'N/A'
                ) ?>
            </strong>

            has completed the verification.

            The letter is now waiting for
            <strong>Admin (QA/QC)</strong>
            to perform the final verification.

        </p>

    </div>


    <button
        type="button"
        class="btn btn-orange"
        onclick="
            submitVerify(
                'sendback_pending'
            )
        "
    >
        ⬅ Send Back
    </button>


<?php else: ?>

    <div class="notice-waiting">

        <p
            style="
                font-size:18px;
                margin-bottom:6px;
            "
        >

            ⏳
            <strong>
                Waiting for Next Verification
            </strong>

        </p>

        <p style="margin:0;">
            Currently awaiting the next verification step.
        </p>

    </div>

<?php endif; ?>


</form>


<!-- =======================================================
     REJECT MODAL
======================================================= -->

<div id="rejectModal">

    <div class="modal-box">

        <h3>
            ❌ Reason for Rejection
        </h3>


        <p>
            Please provide clear feedback
            for the creator.
        </p>


        <textarea
            id="rejectReasonInput"
            placeholder="Enter rejection reason here..."
        ></textarea>


        <div class="modal-actions">

            <button
                type="button"
                class="modal-cancel"
                onclick="closeRejectModal()"
            >
                Cancel
            </button>


            <button
                type="button"
                class="modal-confirm"
                onclick="submitReject()"
            >
                Confirm Reject
            </button>

        </div>

    </div>

</div>


<script>

/* =========================================================
   VERIFY
========================================================= */

function submitVerify(action) {

    const labels = {

        'verify_verifier':
            'verify as assigned verifier',

        'verify_final':
            'final approve this record',

        'sendback_pending':
            'send this back to Pending'

    };


    const label =
        labels[action]
        ||
        action.replace(
            /_/g,
            ' '
        );


    if (
        confirm(
            "Are you sure you want to "
            +
            label
            +
            "?"
        )
    ) {

        document
            .getElementById(
                'formAction'
            )
            .value = action;


        document
            .getElementById(
                'mainForm'
            )
            .submit();

    }

}


/* =========================================================
   JUMP TO LETTER
========================================================= */

function jumpToSelected(selectEl) {

    const targetId =
        selectEl.value;


    if (!targetId) {
        return;
    }


    const projectId =
        <?= (int)$project_id ?>;


    let url =
        "viewletteroutacknowledged.php?id="
        +
        encodeURIComponent(
            targetId
        );


    if (projectId) {

        url +=
            "&project_id="
            +
            encodeURIComponent(
                projectId
            );
    }


    window.location.href =
        url;

}


/* =========================================================
   REJECT MODAL
========================================================= */

function openRejectModal() {

    document
        .getElementById(
            'rejectModal'
        )
        .classList
        .add('active');


    document
        .getElementById(
            'rejectReasonInput'
        )
        .focus();

}


function closeRejectModal() {

    document
        .getElementById(
            'rejectModal'
        )
        .classList
        .remove('active');


    document
        .getElementById(
            'rejectReasonInput'
        )
        .value = '';

}


function submitReject() {

    const reason =
        document
            .getElementById(
                'rejectReasonInput'
            )
            .value
            .trim();


    if (!reason) {

        alert(
            "Please provide a reason for rejection."
        );

        return;
    }


    document
        .getElementById(
            'formAction'
        )
        .value =
        'reject';


    document
        .getElementById(
            'rejectionReasonField'
        )
        .value =
        reason;


    document
        .getElementById(
            'mainForm'
        )
        .submit();

}


/* =========================================================
   CLOSE MODAL BY BACKDROP
========================================================= */

document
    .getElementById(
        'rejectModal'
    )
    .addEventListener(
        'click',
        function(e) {

            if (
                e.target === this
            ) {

                closeRejectModal();

            }

        }
    );


/* =========================================================
   KEYBOARD NAVIGATION
========================================================= */

document.addEventListener(
    'keydown',
    function(e) {

        const activeTag =
            (
                document.activeElement
                &&
                document.activeElement.tagName
                ||
                ''
            ).toLowerCase();


        const isTyping = [
            'input',
            'textarea',
            'select'
        ].includes(
            activeTag
        )
        ||
        (
            document.activeElement
            &&
            document.activeElement.isContentEditable
        );


        if (isTyping) {
            return;
        }


        const modal =
            document.getElementById(
                'rejectModal'
            );


        if (
            modal
            &&
            modal.classList.contains(
                'active'
            )
        ) {

            return;

        }


        /* LEFT = PREVIOUS */

        if (
            e.key === 'ArrowLeft'
        ) {

            const prevBtn =
                document.getElementById(
                    'prevBtn'
                );


            if (
                prevBtn
                &&
                prevBtn.tagName === 'A'
            ) {

                e.preventDefault();

                prevBtn.click();

            }

        }


        /* RIGHT = NEXT */

        else if (
            e.key === 'ArrowRight'
        ) {

            const nextBtn =
                document.getElementById(
                    'nextBtn'
                );


            if (
                nextBtn
                &&
                nextBtn.tagName === 'A'
            ) {

                e.preventDefault();

                nextBtn.click();

            }

        }

    }
);

</script>


</body>

</html>
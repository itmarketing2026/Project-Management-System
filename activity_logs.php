<?php

session_start();

date_default_timezone_set('Asia/Kuala_Lumpur');


/* ===============================
   ERROR HANDLING
================================ */

if ($_SERVER['SERVER_NAME'] === 'localhost') {

    ini_set('display_errors', 1);
    error_reporting(E_ALL);

} else {

    ini_set('display_errors', 0);
    error_reporting(0);
}


/* ===============================
   SESSION TIMEOUT
================================ */

require_once 'include/session_timeout.php';


/* ===============================
   DATABASE
================================ */

require_once 'include/msqli_connect.php';


/* ===============================
   ADMIN ACCESS
================================ */

$allowed_roles = [
    'Client Admin'
];

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['account_type']) ||
    !in_array($_SESSION['account_type'], $allowed_roles, true)
) {

    header("Location: login.php");
    exit;
}


/* ===============================
   CURRENT USER
================================ */

$current_user_name =
    $_SESSION['full_name']
    ?? $_SESSION['username']
    ?? 'Admin';


/* ===============================
   FILTER
================================ */

$user_filter =
    $_GET['user_id'] ?? '';

$action_filter =
    $_GET['action'] ?? '';

$module_filter =
    $_GET['module'] ?? '';

$date_from =
    $_GET['date_from'] ?? '';

$date_to =
    $_GET['date_to'] ?? '';

$search =
    trim($_GET['search'] ?? '');


/* ===============================
   GET USERS
================================ */

$users = [];

$userQuery = $db->query("
    SELECT
        id,
        username,
        full_name
    FROM users
    ORDER BY
        full_name ASC,
        username ASC
");

if ($userQuery) {

    while ($row = $userQuery->fetch_assoc()) {

        $users[] = $row;
    }
}


/* ===============================
   GET ACTIONS
================================ */

$actions = [];

$actionQuery = $db->query("
    SELECT DISTINCT action
    FROM activity_logs
    WHERE action IS NOT NULL
    AND action != ''
    ORDER BY action ASC
");

if ($actionQuery) {

    while ($row = $actionQuery->fetch_assoc()) {

        $actions[] =
            $row['action'];
    }
}


/* ===============================
   GET MODULES
================================ */

$modules = [];

$moduleQuery = $db->query("
    SELECT DISTINCT module
    FROM activity_logs
    WHERE module IS NOT NULL
    AND module != ''
    ORDER BY module ASC
");

if ($moduleQuery) {

    while ($row = $moduleQuery->fetch_assoc()) {

        $modules[] =
            $row['module'];
    }
}


/* ===============================
   BUILD WHERE
================================ */

$where = [];

$params = [];

$types = "";


/* USER */

if ($user_filter !== '') {

    $where[] =
        "al.user_id = ?";

    $params[] =
        (int)$user_filter;

    $types .= "i";
}


/* ACTION */

if ($action_filter !== '') {

    $where[] =
        "al.action = ?";

    $params[] =
        $action_filter;

    $types .= "s";
}


/* MODULE */

if ($module_filter !== '') {

    $where[] =
        "al.module = ?";

    $params[] =
        $module_filter;

    $types .= "s";
}


/* DATE FROM */

if ($date_from !== '') {

    $where[] =
        "al.created_at >= ?";

    $params[] =
        $date_from .
        " 00:00:00";

    $types .= "s";
}


/* DATE TO */

if ($date_to !== '') {

    $where[] =
        "al.created_at <= ?";

    $params[] =
        $date_to .
        " 23:59:59";

    $types .= "s";
}


/* SEARCH */

if ($search !== '') {

    $where[] = "
        (
            al.user_name LIKE ?
            OR u.username LIKE ?
            OR u.full_name LIKE ?
            OR al.description LIKE ?
            OR al.ip_address LIKE ?
            OR al.module LIKE ?
            OR al.action LIKE ?
        )
    ";

    $searchValue =
        "%" . $search . "%";


    for ($i = 0; $i < 7; $i++) {

        $params[] =
            $searchValue;

        $types .= "s";
    }
}


/* ===============================
   WHERE SQL
================================ */

$whereSQL = '';

if (!empty($where)) {

    $whereSQL =
        "WHERE " .
        implode(
            " AND ",
            $where
        );
}


/* ===============================
   PAGINATION
================================ */

$per_page = 25;

$page =
    isset($_GET['page'])
    ? max(
        1,
        (int)$_GET['page']
    )
    : 1;

$offset =
    ($page - 1) *
    $per_page;


/* ===============================
   COUNT
================================ */

$countSQL = "
    SELECT COUNT(*) AS total
    FROM activity_logs al
    LEFT JOIN users u
        ON u.id = al.user_id
    $whereSQL
";

$countStmt =
    $db->prepare($countSQL);


if (!empty($params)) {

    $countStmt->bind_param(
        $types,
        ...$params
    );
}


$countStmt->execute();


$countResult =
    $countStmt->get_result();


$totalRecords = 0;


if ($countResult) {

    $countRow =
        $countResult->fetch_assoc();

    $totalRecords =
        (int)$countRow['total'];
}


$countStmt->close();


$totalPages =
    max(
        1,
        (int)ceil(
            $totalRecords /
            $per_page
        )
    );


/* ===============================
   GET LOGS
================================ */

$sql = "
    SELECT
        al.id,
        al.user_id,
        al.user_name,
        u.username,
        u.full_name,
        al.action,
        al.module,
        al.record_id,
        al.description,
        al.old_values,
        al.new_values,
        al.ip_address,
        al.user_agent,
        al.created_at

    FROM activity_logs al

    LEFT JOIN users u
        ON u.id = al.user_id

    $whereSQL

    ORDER BY
        al.created_at DESC

    LIMIT ?, ?
";


$stmt =
    $db->prepare($sql);


/* Add pagination */

$queryParams =
    $params;

$queryTypes =
    $types . "ii";

$queryParams[] =
    $offset;

$queryParams[] =
    $per_page;


$stmt->bind_param(
    $queryTypes,
    ...$queryParams
);


$stmt->execute();


$result =
    $stmt->get_result();


/* ===============================
   HELPER
================================ */

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* ===============================
   ACTION CLASS
================================ */

function actionClass($action)
{

    switch ($action) {

        case 'LOGIN':
            return 'success';

        case 'LOGOUT':
            return 'secondary';

        case 'AUTO_LOGOUT':
            return 'warning';

        case 'LOGIN_FAILED':
            return 'danger';

        case 'ADD':
            return 'success';

        case 'EDIT':
            return 'info';

        case 'DELETE':
            return 'danger';

        case 'UPLOAD':
            return 'purple';

        case 'DOWNLOAD':
            return 'blue';

        case 'APPROVE':
            return 'success';

        case 'REJECT':
            return 'danger';

        default:
            return 'default';
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<title>Activity Logs</title>

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>


<style>

/* ===============================
   RESET
================================ */

* {
    box-sizing: border-box;
}


html,
body {

    margin: 0;
    padding: 0;

    min-height: 100%;
}


body {

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    background: #f7f9fc;

    color: #263238;
}


/* ===============================
   HEADER
================================ */

.header {

    height: 76px;

    background: #0874cf;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        0 30px;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,.12);
}


.header-left {

    display: flex;

    align-items: center;

    gap: 15px;
}


.header-icon {

    width: 42px;
    height: 42px;

    border-radius: 9px;

    background:
        rgba(255,255,255,.15);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 20px;
}


.header-title {

    font-size: 18px;

    font-weight: 700;
}


.header-subtitle {

    display: block;

    font-size: 10px;

    opacity: .7;

    margin-top: 2px;
}


.header-right {

    display: flex;

    align-items: center;

    gap: 18px;
}


.back-btn {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding:
        9px 15px;

    border:
        1px solid
        rgba(255,255,255,.35);

    border-radius: 6px;

    color: white;

    text-decoration: none;

    font-size: 12px;

    font-weight: 600;

    transition: .2s;
}


.back-btn:hover {

    background:
        rgba(255,255,255,.13);
}


.header-user {

    font-size: 12px;

    font-weight: 600;
}


/* ===============================
   CONTAINER
================================ */

.container {

    max-width: 1550px;

    margin:
        0 auto;

    padding:
        30px;
}


/* ===============================
   PAGE HEADER
================================ */

.page-header {

    margin-bottom: 22px;
}


.page-header h1 {

    margin: 0;

    font-size: 25px;

    color: #17324d;
}


.page-header p {

    margin:
        6px 0 0;

    font-size: 13px;

    color: #7b8794;
}


/* ===============================
   FILTER
================================ */

.filter-card {

    background: white;

    border:
        1px solid #e0e6ed;

    border-radius: 10px;

    padding: 20px;

    margin-bottom: 18px;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,.03);
}


.filter-grid {

    display: grid;

    grid-template-columns:
        repeat(6, 1fr);

    gap: 13px;
}


.field label {

    display: block;

    margin-bottom: 6px;

    font-size: 11px;

    font-weight: 700;

    color: #5c6873;
}


.field input,
.field select {

    width: 100%;

    height: 38px;

    padding:
        0 10px;

    border:
        1px solid #dce3ea;

    border-radius: 5px;

    background: white;

    font-size: 12px;

    color: #333;
}


.field input:focus,
.field select:focus {

    outline: none;

    border-color: #0874cf;

    box-shadow:
        0 0 0 2px
        rgba(8,116,207,.08);
}


/* ===============================
   BUTTONS
================================ */

.filter-buttons {

    display: flex;

    gap: 8px;

    margin-top: 15px;
}


.btn {

    height: 38px;

    padding:
        0 18px;

    border-radius: 5px;

    border: none;

    text-decoration: none;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    font-size: 12px;

    font-weight: 600;

    cursor: pointer;
}


.btn-primary {

    background: #0874cf;

    color: white;
}


.btn-primary:hover {

    background: #0768ba;
}


.btn-reset {

    background: #eef1f5;

    color: #4e5964;
}


.btn-reset:hover {

    background: #e3e7ec;
}


/* ===============================
   SUMMARY
================================ */

.summary {

    margin-bottom: 10px;

    font-size: 12px;

    color: #7b8794;
}


.summary strong {

    color: #17324d;
}


/* ===============================
   TABLE
================================ */

.table-card {

    background: white;

    border:
        1px solid #e0e6ed;

    border-radius: 10px;

    overflow: hidden;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,.03);
}


.table-wrapper {

    overflow-x: auto;
}


table {

    width: 100%;

    min-width: 1250px;

    border-collapse:
        collapse;
}


thead {

    background: #f5f8fb;
}


th {

    padding:
        13px 12px;

    border-bottom:
        1px solid #dfe5eb;

    text-align: left;

    white-space: nowrap;

    font-size: 11px;

    font-weight: 700;

    color: #596674;
}


td {

    padding:
        12px;

    border-bottom:
        1px solid #edf0f3;

    vertical-align: top;

    font-size: 12px;

    color: #3e4b56;
}


tbody tr:hover {

    background: #fbfcfe;
}


/* ===============================
   USER
================================ */

.user-name {

    color: #17324d;

    font-weight: 600;

    white-space: nowrap;
}


.username {

    display: block;

    margin-top: 3px;

    font-size: 10px;

    color: #99a2aa;
}


/* ===============================
   BADGE
================================ */

.badge {

    display: inline-block;

    padding:
        5px 9px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 700;

    white-space: nowrap;
}


.badge.success {

    background: #e7f7ee;

    color: #198754;
}


.badge.secondary {

    background: #edf0f3;

    color: #68737d;
}


.badge.warning {

    background: #fff4d6;

    color: #996c00;
}


.badge.danger {

    background: #fdebec;

    color: #b02a37;
}


.badge.info {

    background: #e8f2ff;

    color: #1769aa;
}


.badge.purple {

    background: #f1eaff;

    color: #6b42a4;
}


.badge.blue {

    background: #e8f6ff;

    color: #16709c;
}


.badge.default {

    background: #f0f2f4;

    color: #59636d;
}


/* ===============================
   DESCRIPTION
================================ */

.description {

    max-width: 350px;

    line-height: 1.45;
}


/* ===============================
   RECORD
================================ */

.record-id {

    font-family:
        Consolas,
        monospace;

    font-size: 11px;

    color: #64707b;
}


/* ===============================
   IP
================================ */

.ip {

    font-family:
        Consolas,
        monospace;

    font-size: 11px;

    white-space: nowrap;
}


/* ===============================
   DATE
================================ */

.date {

    white-space: nowrap;

    font-size: 11px;

    color: #64707b;
}


/* ===============================
   VIEW BUTTON
================================ */

.details-btn {

    padding:
        6px 11px;

    background: white;

    border:
        1px solid #d6dfe8;

    border-radius: 5px;

    color: #0874cf;

    font-size: 10px;

    font-weight: 700;

    cursor: pointer;
}


.details-btn:hover {

    background: #eef7ff;

    border-color: #9dcdf2;
}


/* ===============================
   EMPTY
================================ */

.empty {

    text-align: center;

    padding: 55px 20px;

    color: #8a949e;
}


/* ===============================
   PAGINATION
================================ */

.pagination {

    display: flex;

    justify-content: center;

    gap: 5px;

    padding: 18px;

    border-top:
        1px solid #edf0f3;
}


.pagination a {

    min-width: 34px;

    height: 34px;

    display: flex;

    align-items: center;

    justify-content: center;

    border:
        1px solid #dce3ea;

    border-radius: 5px;

    text-decoration: none;

    color: #596674;

    font-size: 11px;

    font-weight: 600;
}


.pagination a:hover {

    background: #eef7ff;

    color: #0874cf;
}


.pagination a.active {

    background: #0874cf;

    border-color: #0874cf;

    color: white;
}


/* ===============================
   MODAL
================================ */

.modal {

    display: none;

    position: fixed;

    inset: 0;

    z-index: 9999;

    background:
        rgba(20,35,50,.48);

    align-items: center;

    justify-content: center;

    padding: 20px;
}


.modal.show {

    display: flex;
}


.modal-box {

    width: 100%;

    max-width: 820px;

    max-height: 90vh;

    overflow-y: auto;

    background: white;

    border-radius: 10px;

    box-shadow:
        0 20px 55px
        rgba(0,0,0,.20);
}


.modal-header {

    height: 62px;

    padding:
        0 20px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    border-bottom:
        1px solid #e5e9ee;
}


.modal-header h3 {

    margin: 0;

    color: #17324d;

    font-size: 17px;
}


.close {

    width: 34px;
    height: 34px;

    border: none;

    background: #f3f5f7;

    border-radius: 50%;

    color: #68737d;

    font-size: 20px;

    cursor: pointer;
}


.modal-body {

    padding: 20px;
}


/* ===============================
   DETAIL GRID
================================ */

.detail-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 12px;

    margin-bottom: 20px;
}


.detail-item {

    padding: 12px;

    border:
        1px solid #e7ebef;

    border-radius: 6px;
}


.detail-label {

    margin-bottom: 5px;

    font-size: 9px;

    font-weight: 700;

    color: #8a949e;

    text-transform:
        uppercase;
}


.detail-value {

    font-size: 12px;

    color: #34424e;

    word-break: break-word;
}


.json-section {

    margin-top: 18px;
}


.json-section h4 {

    margin:
        0 0 7px;

    font-size: 12px;

    color: #17324d;
}


pre {

    margin: 0;

    padding: 12px;

    background: #f6f8fa;

    border:
        1px solid #e2e7ec;

    border-radius: 6px;

    overflow-x: auto;

    white-space: pre-wrap;

    word-break: break-word;

    font-family:
        Consolas,
        monospace;

    font-size: 11px;

    line-height: 1.5;
}


/* ===============================
   RESPONSIVE
================================ */

@media (max-width: 1100px) {

    .filter-grid {

        grid-template-columns:
            repeat(3, 1fr);
    }
}


@media (max-width: 700px) {

    .header {

        padding:
            0 15px;
    }

    .header-user {

        display: none;
    }

    .container {

        padding:
            20px 12px;
    }

    .filter-grid {

        grid-template-columns: 1fr;
    }

    .detail-grid {

        grid-template-columns: 1fr;
    }
}

</style>

</head>


<body>


<!-- ===============================
     HEADER
================================ -->

<header class="header">


    <div class="header-left">


        <div class="header-icon">
            📋
        </div>


        <div>

            <div class="header-title">
                Activity Logs
            </div>

            <span class="header-subtitle">
                Project Center / Monitoring
            </span>

        </div>


    </div>


    <div class="header-right">


        <a
            href="project_center.php"
            class="back-btn"
        >
            ← Back to Project Center
        </a>


        <div class="header-user">

            <?= h(
                $current_user_name
            ) ?>

        </div>


    </div>


</header>


<!-- ===============================
     MAIN
================================ -->

<div class="container">


    <div class="page-header">

        <h1>
            Activity Logs
        </h1>

        <p>
            Monitor user activity,
            login history and system actions.
        </p>

    </div>


    <!-- ===============================
         FILTER
    ================================= -->

    <div class="filter-card">


        <form method="GET">


            <div class="filter-grid">


                <!-- USER -->

                <div class="field">

                    <label>
                        User
                    </label>

                    <select name="user_id">

                        <option value="">
                            All Users
                        </option>


                        <?php foreach (
                            $users
                            as $user
                        ): ?>


                            <option
                                value="<?= h(
                                    $user['id']
                                ) ?>"
                                <?= (
                                    (string)$user_filter ===
                                    (string)$user['id']
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= h(
                                    $user['full_name']
                                    ?: $user['username']
                                ) ?>

                                -
                                <?= h(
                                    $user['username']
                                ) ?>

                            </option>


                        <?php endforeach; ?>


                    </select>

                </div>


                <!-- ACTION -->

                <div class="field">

                    <label>
                        Action
                    </label>

                    <select name="action">

                        <option value="">
                            All Actions
                        </option>


                        <?php foreach (
                            $actions
                            as $action
                        ): ?>


                            <option
                                value="<?= h(
                                    $action
                                ) ?>"
                                <?= (
                                    $action_filter ===
                                    $action
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= h(
                                    $action
                                ) ?>

                            </option>


                        <?php endforeach; ?>


                    </select>

                </div>


                <!-- MODULE -->

                <div class="field">

                    <label>
                        Module
                    </label>

                    <select name="module">

                        <option value="">
                            All Modules
                        </option>


                        <?php foreach (
                            $modules
                            as $module
                        ): ?>


                            <option
                                value="<?= h(
                                    $module
                                ) ?>"
                                <?= (
                                    $module_filter ===
                                    $module
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= h(
                                    $module
                                ) ?>

                            </option>


                        <?php endforeach; ?>


                    </select>

                </div>


                <!-- DATE FROM -->

                <div class="field">

                    <label>
                        Date From
                    </label>

                    <input
                        type="date"
                        name="date_from"
                        value="<?= h(
                            $date_from
                        ) ?>"
                    >

                </div>


                <!-- DATE TO -->

                <div class="field">

                    <label>
                        Date To
                    </label>

                    <input
                        type="date"
                        name="date_to"
                        value="<?= h(
                            $date_to
                        ) ?>"
                    >

                </div>


                <!-- SEARCH -->

                <div class="field">

                    <label>
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        placeholder="Search user, action, IP..."
                        value="<?= h(
                            $search
                        ) ?>"
                    >

                </div>


            </div>


            <div class="filter-buttons">


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    🔎 Search

                </button>


                <a
                    href="activity_logs.php"
                    class="btn btn-reset"
                >

                    Reset

                </a>


            </div>


        </form>


    </div>


    <!-- ===============================
         SUMMARY
    ================================= -->

    <div class="summary">

        Showing
        <strong>
            <?= number_format(
                $totalRecords
            ) ?>
        </strong>
        activity record(s)

    </div>


    <!-- ===============================
         TABLE
    ================================= -->

    <div class="table-card">


        <div class="table-wrapper">


            <table>


                <thead>

                    <tr>

                        <th>
                            Date & Time
                        </th>

                        <th>
                            User
                        </th>

                        <th>
                            Action
                        </th>

                        <th>
                            Module
                        </th>

                        <th>
                            Record ID
                        </th>

                        <th>
                            Description
                        </th>

                        <th>
                            IP Address
                        </th>

                        <th>
                            Details
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    $result &&
                    $result->num_rows > 0
                ): ?>


                    <?php while (
                        $row =
                        $result->fetch_assoc()
                    ): ?>


                        <tr>


                            <!-- DATE -->

                            <td>

                                <div class="date">

                                    <?= h(
                                        date(
                                            'd/m/Y H:i:s',
                                            strtotime(
                                                $row['created_at']
                                            )
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <!-- USER -->

                            <td>

                                <div class="user-name">

                                    <?= h(
                                        $row['user_name']
                                        ?: $row['full_name']
                                        ?: 'Unknown'
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $row['username']
                                    )
                                ): ?>

                                    <span class="username">

                                        @<?= h(
                                            $row['username']
                                        ) ?>

                                    </span>

                                <?php endif; ?>


                            </td>


                            <!-- ACTION -->

                            <td>

                                <span
                                    class="badge <?= h(
                                        actionClass(
                                            $row['action']
                                        )
                                    ) ?>"
                                >

                                    <?= h(
                                        $row['action']
                                    ) ?>

                                </span>

                            </td>


                            <!-- MODULE -->

                            <td>

                                <?= h(
                                    $row['module']
                                    ?: '-'
                                ) ?>

                            </td>


                            <!-- RECORD -->

                            <td>

                                <?php if (
                                    $row['record_id']
                                    !== null
                                ): ?>

                                    <span class="record-id">

                                        #<?= h(
                                            $row['record_id']
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <!-- DESCRIPTION -->

                            <td>

                                <div class="description">

                                    <?= h(
                                        $row['description']
                                        ?: '-'
                                    ) ?>

                                </div>

                            </td>


                            <!-- IP -->

                            <td>

                                <span class="ip">

                                    <?= h(
                                        $row['ip_address']
                                        ?: '-'
                                    ) ?>

                                </span>

                            </td>


                            <!-- DETAILS -->

                            <td>

                                <button
                                    type="button"
                                    class="details-btn"
                                    onclick='showDetails(
                                        <?= json_encode(
                                            $row,
                                            JSON_HEX_TAG |
                                            JSON_HEX_APOS |
                                            JSON_HEX_QUOT |
                                            JSON_HEX_AMP
                                        ) ?>
                                    )'
                                >

                                    View

                                </button>

                            </td>


                        </tr>


                    <?php endwhile; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="8"
                            class="empty"
                        >

                            No activity logs found.

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>


            </table>


        </div>


        <!-- ===============================
             PAGINATION
        ================================= -->

        <?php if (
            $totalPages > 1
        ): ?>


            <div class="pagination">


                <?php

                $query =
                    $_GET;

                ?>


                <?php if (
                    $page > 1
                ): ?>

                    <?php
                    $query['page'] =
                        $page - 1;
                    ?>

                    <a href="?<?= h(
                        http_build_query(
                            $query
                        )
                    ) ?>">

                        &laquo;

                    </a>

                <?php endif; ?>


                <?php

                $startPage =
                    max(
                        1,
                        $page - 2
                    );

                $endPage =
                    min(
                        $totalPages,
                        $page + 2
                    );

                ?>


                <?php for (
                    $i = $startPage;
                    $i <= $endPage;
                    $i++
                ): ?>


                    <?php

                    $query['page'] =
                        $i;

                    ?>


                    <a
                        href="?<?= h(
                            http_build_query(
                                $query
                            )
                        ) ?>"
                        class="<?= (
                            $i === $page
                        )
                            ? 'active'
                            : ''
                        ?>"
                    >

                        <?= $i ?>

                    </a>


                <?php endfor; ?>


                <?php if (
                    $page < $totalPages
                ): ?>


                    <?php

                    $query['page'] =
                        $page + 1;

                    ?>


                    <a href="?<?= h(
                        http_build_query(
                            $query
                        )
                    ) ?>">

                        &raquo;

                    </a>


                <?php endif; ?>


            </div>


        <?php endif; ?>


    </div>


</div>


<!-- ===============================
     DETAILS MODAL
================================ -->

<div
    class="modal"
    id="detailsModal"
>


    <div class="modal-box">


        <div class="modal-header">

            <h3>
                Activity Details
            </h3>


            <button
                type="button"
                class="close"
                onclick="closeDetails()"
            >

                &times;

            </button>

        </div>


        <div
            class="modal-body"
            id="modalBody"
        >

        </div>


    </div>


</div>


<script>

/* ===============================
   ESCAPE HTML
================================ */

function escapeHtml(value) {

    if (
        value === null ||
        value === undefined
    ) {

        return '-';
    }


    return String(value)
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );
}


/* ===============================
   FORMAT JSON
================================ */

function formatJson(value) {

    if (
        value === null ||
        value === '' ||
        value === undefined
    ) {

        return '-';
    }


    try {

        const parsed =
            typeof value === 'string'
                ? JSON.parse(value)
                : value;


        return escapeHtml(
            JSON.stringify(
                parsed,
                null,
                2
            )
        );


    } catch (e) {

        return escapeHtml(value);
    }
}


/* ===============================
   SHOW DETAILS
================================ */

function showDetails(row) {

    const modal =
        document.getElementById(
            'detailsModal'
        );


    const body =
        document.getElementById(
            'modalBody'
        );


    const userName =
        row.user_name ||
        row.full_name ||
        'Unknown';


    const username =
        row.username
            ? '@' + row.username
            : '-';


    body.innerHTML = `

        <div class="detail-grid">


            <div class="detail-item">

                <div class="detail-label">
                    User
                </div>

                <div class="detail-value">

                    <strong>
                        ${escapeHtml(
                            userName
                        )}
                    </strong>

                    <br>

                    <span
                        style="color:#8a949e;"
                    >

                        ${escapeHtml(
                            username
                        )}

                    </span>

                </div>

            </div>


            <div class="detail-item">

                <div class="detail-label">
                    Action
                </div>

                <div class="detail-value">

                    ${escapeHtml(
                        row.action
                    )}

                </div>

            </div>


            <div class="detail-item">

                <div class="detail-label">
                    Module
                </div>

                <div class="detail-value">

                    ${escapeHtml(
                        row.module || '-'
                    )}

                </div>

            </div>


            <div class="detail-item">

                <div class="detail-label">
                    Record ID
                </div>

                <div class="detail-value">

                    ${
                        row.record_id !== null
                        ? '#' +
                          escapeHtml(
                              row.record_id
                          )
                        : '-'
                    }

                </div>

            </div>


            <div class="detail-item">

                <div class="detail-label">
                    IP Address
                </div>

                <div class="detail-value">

                    ${escapeHtml(
                        row.ip_address || '-'
                    )}

                </div>

            </div>


            <div class="detail-item">

                <div class="detail-label">
                    Date & Time
                </div>

                <div class="detail-value">

                    ${escapeHtml(
                        row.created_at || '-'
                    )}

                </div>

            </div>


        </div>


        <div class="json-section">

            <h4>
                Description
            </h4>

            <pre>${escapeHtml(
                row.description || '-'
            )}</pre>

        </div>


        <div class="json-section">

            <h4>
                Old Values
            </h4>

            <pre>${formatJson(
                row.old_values
            )}</pre>

        </div>


        <div class="json-section">

            <h4>
                New Values
            </h4>

            <pre>${formatJson(
                row.new_values
            )}</pre>

        </div>


        <div class="json-section">

            <h4>
                Browser / Device
            </h4>

            <pre>${escapeHtml(
                row.user_agent || '-'
            )}</pre>

        </div>

    `;


    modal.classList.add(
        'show'
    );
}


/* ===============================
   CLOSE DETAILS
================================ */

function closeDetails() {

    document
        .getElementById(
            'detailsModal'
        )
        .classList.remove(
            'show'
        );
}


/* ===============================
   CLOSE WHEN CLICK OUTSIDE
================================ */

document
    .getElementById(
        'detailsModal'
    )
    .addEventListener(
        'click',
        function(event) {

            if (
                event.target === this
            ) {

                closeDetails();
            }

        }
    );


/* ===============================
   ESC KEY
================================ */

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            closeDetails();
        }

    }
);

</script>


</body>

</html>
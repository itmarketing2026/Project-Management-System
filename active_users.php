<?php

session_start();

date_default_timezone_set('Asia/Kuala_Lumpur');

require_once 'include/session_timeout.php';
require_once 'include/msqli_connect.php';


/* =========================================
   ADMIN ACCESS
========================================= */

$allowed_roles = [
    'Client Admin'
];


if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['account_type']) ||
    !in_array(
        $_SESSION['account_type'],
        $allowed_roles,
        true
    )
) {

    header("Location: login.php");

    exit;
}


/* =========================================
   CURRENT USER
========================================= */

$current_user_name =
    $_SESSION['full_name']
    ?? $_SESSION['username']
    ?? 'Admin';


/* =========================================
   AUTOMATICALLY MARK OLD SESSIONS OFFLINE
========================================= */

$db->query("
    UPDATE user_sessions
    SET is_active = 0
    WHERE last_activity < DATE_SUB(NOW(), INTERVAL 1 HOUR)
");


/* =========================================
   GET ACTIVE / ONLINE USERS
========================================= */

$sql = "

    SELECT
        us.id,
        us.user_id,
        us.session_id,
        us.ip_address,
        us.user_agent,
        us.last_activity,
        us.created_at,

        u.username,
        u.full_name,
        u.account_type,
        u.designation

    FROM user_sessions us

    LEFT JOIN users u
        ON u.id = us.user_id

    WHERE us.is_active = 1

    ORDER BY
        us.last_activity DESC
";


$result =
    $db->query($sql);


/* =========================================
   HELPER
========================================= */

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================
   STATUS
========================================= */

function getStatus($last_activity)
{
    $last =
        strtotime($last_activity);

    $diff =
        time() - $last;


    if ($diff <= 300) {

        return [
            'class' => 'active',
            'label' => 'Active'
        ];
    }


    if ($diff <= 3600) {

        return [
            'class' => 'idle',
            'label' => 'Idle'
        ];
    }


    return [
        'class' => 'offline',
        'label' => 'Offline'
    ];
}


?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>Active Users</title>


<style>

/* =========================================
   RESET
========================================= */

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


/* =========================================
   HEADER
========================================= */

.header {

    height: 76px;

    background: #0874cf;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 30px;

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

    padding: 9px 15px;

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


/* =========================================
   CONTAINER
========================================= */

.container {

    max-width: 1550px;

    margin: 0 auto;

    padding: 30px;
}


/* =========================================
   PAGE HEADER
========================================= */

.page-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 22px;
}


.page-header h1 {

    margin: 0;

    font-size: 25px;

    color: #17324d;
}


.page-header p {

    margin: 6px 0 0;

    font-size: 13px;

    color: #7b8794;
}


/* =========================================
   REFRESH
========================================= */

.refresh-btn {

    height: 38px;

    padding: 0 18px;

    border: none;

    border-radius: 5px;

    background: #0874cf;

    color: white;

    font-size: 12px;

    font-weight: 600;

    text-decoration: none;

    display: inline-flex;

    align-items: center;

    gap: 7px;
}


.refresh-btn:hover {

    background: #0768ba;
}


/* =========================================
   SUMMARY CARDS
========================================= */

.summary-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

    margin-bottom: 20px;
}


.summary-card {

    background: white;

    border:
        1px solid #e0e6ed;

    border-radius: 10px;

    padding: 18px;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,.03);
}


.summary-label {

    font-size: 11px;

    font-weight: 700;

    color: #7b8794;

    text-transform: uppercase;
}


.summary-number {

    margin-top: 7px;

    font-size: 27px;

    font-weight: 700;

    color: #17324d;
}


/* =========================================
   TABLE
========================================= */

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

    min-width: 1100px;

    border-collapse:
        collapse;
}


thead {

    background: #f5f8fb;
}


th {

    padding: 13px 12px;

    border-bottom:
        1px solid #dfe5eb;

    text-align: left;

    white-space: nowrap;

    font-size: 11px;

    font-weight: 700;

    color: #596674;
}


td {

    padding: 13px 12px;

    border-bottom:
        1px solid #edf0f3;

    vertical-align: middle;

    font-size: 12px;

    color: #3e4b56;
}


tbody tr:hover {

    background: #fbfcfe;
}


/* =========================================
   USER
========================================= */

.user-name {

    color: #17324d;

    font-weight: 600;
}


.username {

    display: block;

    margin-top: 3px;

    font-size: 10px;

    color: #99a2aa;
}


/* =========================================
   STATUS
========================================= */

.status {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 700;
}


.status-dot {

    width: 7px;

    height: 7px;

    border-radius: 50%;

    background: currentColor;
}


.status.active {

    background: #e7f7ee;

    color: #198754;
}


.status.idle {

    background: #fff4d6;

    color: #996c00;
}


.status.offline {

    background: #fdebec;

    color: #b02a37;
}


/* =========================================
   LAST ACTIVITY
========================================= */

.last-activity {

    white-space: nowrap;

    color: #596674;

    font-family:
        Consolas,
        monospace;

    font-size: 11px;
}


/* =========================================
   IP
========================================= */

.ip {

    font-family:
        Consolas,
        monospace;

    font-size: 11px;
}


/* =========================================
   EMPTY
========================================= */

.empty {

    text-align: center;

    padding: 60px 20px;

    color: #8a949e;
}


.empty-icon {

    font-size: 35px;

    margin-bottom: 10px;
}


/* =========================================
   RESPONSIVE
========================================= */

@media (max-width: 800px) {

    .header {

        padding: 0 15px;
    }


    .header-user {

        display: none;
    }


    .container {

        padding: 20px 12px;
    }


    .summary-grid {

        grid-template-columns: 1fr;
    }


    .page-header {

        align-items: flex-start;

        gap: 15px;

        flex-direction: column;
    }
}

</style>

</head>


<body>


<header class="header">

    <div class="header-left">

        <div class="header-icon">
            👥
        </div>

        <div>

            <div class="header-title">
                Active Users
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

            <?= h($current_user_name) ?>

        </div>

    </div>

</header>


<div class="container">


    <div class="page-header">

        <div>

            <h1>
                Active Users
            </h1>

            <p>
                View users currently using the PMS.
            </p>

        </div>


        <a
            href="active_users.php"
            class="refresh-btn"
        >
            ↻ Refresh
        </a>

    </div>


<?php

/* =========================================
   COUNT USERS
========================================= */

$activeCount = 0;

$idleCount = 0;

$offlineCount = 0;


/*
 * We count based on current result.
 */

if ($result) {

    $result->data_seek(0);

    while (
        $row =
        $result->fetch_assoc()
    ) {

        $status =
            getStatus(
                $row['last_activity']
            );


        if (
            $status['class']
            === 'active'
        ) {

            $activeCount++;

        } elseif (
            $status['class']
            === 'idle'
        ) {

            $idleCount++;

        } else {

            $offlineCount++;
        }
    }


    $result->data_seek(0);
}

?>


    <div class="summary-grid">


        <div class="summary-card">

            <div class="summary-label">
                Active
            </div>

            <div class="summary-number">
                <?= $activeCount ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-label">
                Idle
            </div>

            <div class="summary-number">
                <?= $idleCount ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-label">
                Total Sessions
            </div>

            <div class="summary-number">
                <?= $activeCount + $idleCount ?>
            </div>

        </div>


    </div>


    <div class="table-card">


        <div class="table-wrapper">


            <table>


                <thead>

                    <tr>

                        <th>
                            User
                        </th>

                        <th>
                            Role
                        </th>

                        <th>
                            Designation
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Last Activity
                        </th>

                        <th>
                            Login Time
                        </th>

                        <th>
                            IP Address
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


<?php

$status =
    getStatus(
        $row['last_activity']
    );

?>


                    <tr>


                        <td>

                            <div class="user-name">

                                <?= h(
                                    $row['full_name']
                                    ?: $row['username']
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


                        <td>

                            <?= h(
                                $row['account_type']
                                ?: '-'
                            ) ?>

                        </td>


                        <td>

                            <?= h(
                                $row['designation']
                                ?: '-'
                            ) ?>

                        </td>


                        <td>

                            <span
                                class="status <?= h(
                                    $status['class']
                                ) ?>"
                            >

                                <span
                                    class="status-dot"
                                ></span>

                                <?= h(
                                    $status['label']
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <span
                                class="last-activity"
                            >

                                <?= h(
                                    date(
                                        'd/m/Y H:i:s',
                                        strtotime(
                                            $row['last_activity']
                                        )
                                    )
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?= h(
                                date(
                                    'd/m/Y H:i:s',
                                    strtotime(
                                        $row['created_at']
                                    )
                                )
                            ) ?>

                        </td>


                        <td>

                            <span class="ip">

                                <?= h(
                                    $row['ip_address']
                                    ?: '-'
                                ) ?>

                            </span>

                        </td>


                    </tr>


<?php endwhile; ?>


<?php else: ?>


                    <tr>

                        <td
                            colspan="7"
                            class="empty"
                        >

                            <div class="empty-icon">
                                👥
                            </div>

                            No active users currently.

                        </td>

                    </tr>


<?php endif; ?>


                </tbody>

            </table>


        </div>


    </div>


</div>


</body>

</html>
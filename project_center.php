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
   1 HOUR INACTIVITY
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
    !in_array(
        $_SESSION['account_type'],
        $allowed_roles,
        true
    )
) {

    header("Location: login.php");
    exit;

}


/* ===============================
   CURRENT USER
================================ */

$user_name =
    $_SESSION['full_name']
    ?? $_SESSION['username']
    ?? 'Admin';

$user_role =
    $_SESSION['account_type']
    ?? 'Admin';


$user_initial = strtoupper(
    substr(
        trim($user_name),
        0,
        1
    )
);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<title>Project Center</title>

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>


<style>

/* =====================================================
   RESET
===================================================== */

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

    background: #f5f7fa;

    color: #263238;

}


/* =====================================================
   APP
===================================================== */

.app {

    min-height: 100vh;

    display: flex;

}


/* =====================================================
   SIDEBAR
===================================================== */

.sidebar {

    position: fixed;

    left: 0;
    top: 0;
    bottom: 0;

    width: 255px;

    background: #17324d;

    color: white;

    display: flex;

    flex-direction: column;

    z-index: 1000;

    box-shadow:
        3px 0 12px
        rgba(0,0,0,.08);

}


/* =====================================================
   SIDEBAR BRAND
===================================================== */

.sidebar-brand {

    height: 78px;

    padding:
        0 22px;

    display: flex;

    align-items: center;

    gap: 12px;

    border-bottom:
        1px solid
        rgba(255,255,255,.10);

}


.brand-icon {

    width: 42px;
    height: 42px;

    border-radius: 9px;

    background:
        rgba(255,255,255,.10);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 18px;

}


.brand-text {

    font-size: 17px;

    font-weight: 700;

    line-height: 1.2;

}


.brand-subtitle {

    display: block;

    margin-top: 3px;

    font-size: 10px;

    color:
        rgba(255,255,255,.55);

}


/* =====================================================
   SIDEBAR MENU
===================================================== */

.sidebar-menu {

    flex: 1;

    padding:
        20px 12px;

    overflow-y: auto;

}


.menu-section {

    margin-bottom: 22px;

}


.menu-title {

    padding:
        0 13px 8px;

    font-size: 10px;

    font-weight: 700;

    letter-spacing: .8px;

    text-transform: uppercase;

    color:
        rgba(255,255,255,.42);

}


.menu-item {

    position: relative;

    display: flex;

    align-items: center;

    gap: 12px;

    width: 100%;

    padding:
        11px 13px;

    margin-bottom: 3px;

    border-radius: 6px;

    color:
        rgba(255,255,255,.72);

    text-decoration: none;

    font-size: 13px;

    transition:
        background .2s,
        color .2s;

}


.menu-item:hover {

    background:
        rgba(255,255,255,.07);

    color: white;

}


.menu-item.active {

    background:
        rgba(255,255,255,.13);

    color: white;

    font-weight: 600;

}


.menu-item.active::before {

    content: "";

    position: absolute;

    left: 0;

    top: 7px;
    bottom: 7px;

    width: 3px;

    border-radius: 3px;

    background: #4da9ee;

}


.menu-icon {

    width: 21px;

    text-align: center;

    font-size: 15px;

    opacity: .9;

}


/* =====================================================
   SIDEBAR FOOTER
===================================================== */

.sidebar-footer {

    padding: 16px;

    border-top:
        1px solid
        rgba(255,255,255,.10);

}


.user-box {

    display: flex;

    align-items: center;

    gap: 10px;

}


.user-avatar {

    width: 38px;
    height: 38px;

    flex-shrink: 0;

    border-radius: 50%;

    background: #0874cf;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 14px;

    font-weight: 700;

}


.user-info {

    min-width: 0;

}


.user-name {

    font-size: 12px;

    font-weight: 600;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

}


.user-role {

    margin-top: 3px;

    font-size: 10px;

    color:
        rgba(255,255,255,.52);

}


/* =====================================================
   MAIN
===================================================== */

.main {

    width:
        calc(100% - 255px);

    margin-left: 255px;

    min-height: 100vh;

}


/* =====================================================
   TOPBAR
===================================================== */

.topbar {

    height: 78px;

    background: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        0 32px;

    border-bottom:
        1px solid #e4e9ee;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.04);

}


.breadcrumb {

    font-size: 13px;

    color: #8995a1;

}


.breadcrumb strong {

    color: #17324d;

}


.breadcrumb-separator {

    margin:
        0 8px;

    color: #b4bcc4;

}


.back-btn {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding:
        9px 15px;

    border:
        1px solid #d8e0e7;

    border-radius: 6px;

    background: white;

    color: #415568;

    text-decoration: none;

    font-size: 12px;

    font-weight: 600;

    transition: .2s;

}


.back-btn:hover {

    border-color: #0874cf;

    color: #0874cf;

    background: #f5faff;

}


/* =====================================================
   CONTENT
===================================================== */

.content {

    padding:
        30px 32px 40px;

}


/* =====================================================
   WELCOME BANNER
===================================================== */

.welcome-banner {

    position: relative;

    overflow: hidden;

    min-height: 150px;

    padding:
        28px 32px;

    margin-bottom: 30px;

    border-radius: 12px;

    background:
        linear-gradient(
            110deg,
            #2467ea,
            #2fa7f5
        );

    color: white;

    box-shadow:
        0 8px 24px
        rgba(36,103,234,.14);

}


.welcome-banner h2 {

    margin: 0;

    font-size: 23px;

    font-weight: 700;

}


.welcome-banner p {

    margin:
        8px 0 0;

    font-size: 13px;

    opacity: .88;

}


.banner-icon {

    position: absolute;

    right: 38px;

    top: 50%;

    transform:
        translateY(-50%);

    width: 58px;
    height: 58px;

    border-radius: 12px;

    background:
        rgba(255,255,255,.16);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 26px;

}


/* =====================================================
   PAGE HEADER
===================================================== */

.page-header {

    margin-bottom: 20px;

}


.page-header h1 {

    margin: 0;

    font-size: 24px;

    color: #17324d;

    font-weight: 700;

}


.page-header p {

    margin:
        6px 0 0;

    font-size: 13px;

    color: #7d8995;

}


/* =====================================================
   CARDS
===================================================== */

.cards {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;

}


/* =====================================================
   CARD
===================================================== */

.card {

    position: relative;

    display: block;

    min-height: 155px;

    padding:
        22px;

    background: white;

    border:
        1px solid #e1e6eb;

    border-radius: 10px;

    text-decoration: none;

    color: inherit;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.025);

    transition:
        transform .2s,
        box-shadow .2s,
        border-color .2s;

}


.card:hover {

    transform:
        translateY(-2px);

    border-color:
        #c8d7e5;

    box-shadow:
        0 8px 22px
        rgba(23,50,77,.08);

}


/* =====================================================
   CARD ICON
===================================================== */

.card-icon {

    width: 46px;
    height: 46px;

    border-radius: 10px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 19px;

    margin-bottom: 16px;

}


/* Different icon backgrounds */

.icon-user {

    background: #eaf4ff;

}


.icon-activity {

    background: #eef8f3;

}


.icon-security {

    background: #fff5e8;

}


.icon-project {

    background: #f0edff;

}


.icon-assignment {

    background: #eaf8f8;

}


.icon-system {

    background: #f1f3f5;

}


/* =====================================================
   CARD TEXT
===================================================== */

.card h3 {

    margin:
        0 0 6px;

    font-size: 15px;

    color: #17324d;

    font-weight: 700;

}


.card p {

    margin: 0;

    font-size: 12px;

    line-height: 1.55;

    color: #82909d;

}


/* =====================================================
   CARD ARROW
===================================================== */

.card-arrow {

    position: absolute;

    right: 20px;

    bottom: 20px;

    font-size: 14px;

    color: #b0bbc5;

    transition: .2s;

}


.card:hover .card-arrow {

    color: #0874cf;

    transform:
        translateX(3px);

}


/* =====================================================
   SECTION LABEL
===================================================== */

.section-label {

    margin:
        0 0 12px;

    font-size: 11px;

    font-weight: 700;

    color: #8a96a2;

    letter-spacing: .6px;

    text-transform: uppercase;

}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 1100px) {

    .cards {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media (max-width: 750px) {

    .sidebar {

        width: 215px;

    }


    .main {

        margin-left: 215px;

        width:
            calc(100% - 215px);

    }


    .content {

        padding:
            22px 18px 30px;

    }


    .topbar {

        padding:
            0 18px;

    }


    .cards {

        grid-template-columns:
            1fr;

    }


    .banner-icon {

        right: 22px;

    }

}


@media (max-width: 600px) {

    .sidebar {

        width: 190px;

    }


    .main {

        margin-left: 190px;

        width:
            calc(100% - 190px);

    }


    .brand-text {

        font-size: 14px;

    }


    .brand-subtitle {

        display: none;

    }


    .menu-item {

        font-size: 12px;

    }

}

</style>

</head>


<body>


<div class="app">


    <!-- =================================================
         SIDEBAR
    ================================================== -->

    <aside class="sidebar">


        <!-- BRAND -->

        <div class="sidebar-brand">


            <div class="brand-icon">
                ⚙
            </div>


            <div>

                <div class="brand-text">
                    Project Center
                </div>

                <span class="brand-subtitle">
                    System Administration
                </span>

            </div>


        </div>


        <!-- MENU -->

        <nav class="sidebar-menu">


            <!-- GENERAL -->

            <div class="menu-section">


                <div class="menu-title">
                    General
                </div>


                <a
                    href="project_center.php"
                    class="menu-item active"
                >

                    <span class="menu-icon">
                        ⌂
                    </span>

                    Overview

                </a>


            </div>


            <!-- USER -->

            <div class="menu-section">


                <div class="menu-title">
                    User & Access
                </div>


                <a
                    href="#"
                    class="menu-item"
                >

                    <span class="menu-icon">
                        👤
                    </span>

                    User Management

                </a>


                <a
                    href="#"
                    class="menu-item"
                >

                    <span class="menu-icon">
                        🔐
                    </span>

                    Roles & Access

                </a>


            </div>


            <!-- MONITORING -->

            <div class="menu-section">


                <div class="menu-title">
                    Monitoring
                </div>


                <a
                    href="activity_logs.php"
                    class="menu-item"
                >

                    <span class="menu-icon">
                        📋
                    </span>

                    Activity Logs

                </a>


                <a
                    href="activity_logs.php?action=LOGIN"
                    class="menu-item"
                >

                    <span class="menu-icon">
                        🔑
                    </span>

                    Login History

                </a>
				
				<a
    href="active_users.php"
    class="menu-item"
>
    Active Users
</a>


            </div>


            <!-- PROJECT -->

            <div class="menu-section">


                <div class="menu-title">
                    Project
                </div>


                <a
                    href="#"
                    class="menu-item"
                >

                    <span class="menu-icon">
                        🏗
                    </span>

                    Project Settings

                </a>


                <a
                    href="#"
                    class="menu-item"
                >

                    <span class="menu-icon">
                        👥
                    </span>

                    Project Assignment

                </a>


            </div>


            <!-- SYSTEM -->

            <div class="menu-section">


                <div class="menu-title">
                    System
                </div>


                <a
                    href="#"
                    class="menu-item"
                >

                    <span class="menu-icon">
                        ⚙
                    </span>

                    System Settings

                </a>


            </div>


        </nav>


        <!-- USER FOOTER -->

        <div class="sidebar-footer">


            <div class="user-box">


                <div class="user-avatar">

                    <?= htmlspecialchars(
                        $user_initial
                    ) ?>

                </div>


                <div class="user-info">


                    <div class="user-name">

                        <?= htmlspecialchars(
                            $user_name
                        ) ?>

                    </div>


                    <div class="user-role">

                        <?= htmlspecialchars(
                            $user_role
                        ) ?>

                    </div>


                </div>


            </div>


        </div>


    </aside>


    <!-- =================================================
         MAIN
    ================================================== -->

    <main class="main">


        <!-- TOPBAR -->

        <header class="topbar">


            <div class="breadcrumb">

                Project Center

                <span class="breadcrumb-separator">
                    /
                </span>

                <strong>
                    Overview
                </strong>

            </div>


            <a
                href="dashboard.php"
                class="back-btn"
            >

                ← Back to Dashboard

            </a>


        </header>


        <!-- CONTENT -->

        <div class="content">


            <!-- WELCOME -->

            <div class="welcome-banner">


                <h2>

                    Welcome back,
                    <?= htmlspecialchars(
                        $user_name
                    ) ?>!

                </h2>


                <p>

                    Manage system settings,
                    users, security and
                    project administration.

                </p>


                <div class="banner-icon">

                    ⚙

                </div>


            </div>


            <!-- PAGE HEADER -->

            <div class="page-header">


                <h1>
                    Project Center
                </h1>


                <p>
                    Select a section below
                    to manage your system.
                </p>


            </div>


            <!-- SECTION -->

            <div class="section-label">
                Administration
            </div>


            <!-- CARDS -->

            <div class="cards">


                <!-- USER MANAGEMENT -->

                <a
                    href="#"
                    class="card"
                >


                    <div
                        class="card-icon icon-user"
                    >

                        👤

                    </div>


                    <h3>
                        User Management
                    </h3>


                    <p>

                        Manage users,
                        account status,
                        roles and access.

                    </p>


                    <span class="card-arrow">
                        →
                    </span>


                </a>


                <!-- ACTIVITY LOG -->

                <a
                    href="activity_logs.php"
                    class="card"
                >


                    <div
                        class="card-icon icon-activity"
                    >

                        📋

                    </div>


                    <h3>
                        Activity Logs
                    </h3>


                    <p>

                        View user activity,
                        login history and
                        system actions.

                    </p>


                    <span class="card-arrow">
                        →
                    </span>


                </a>


                <!-- SECURITY -->

                <a
                    href="#"
                    class="card"
                >


                    <div
                        class="card-icon icon-security"
                    >

                        🔐

                    </div>


                    <h3>
                        Security
                    </h3>


                    <p>

                        Manage session,
                        login and security
                        settings.

                    </p>


                    <span class="card-arrow">
                        →
                    </span>


                </a>


                <!-- PROJECT SETTINGS -->

                <a
                    href="#"
                    class="card"
                >


                    <div
                        class="card-icon icon-project"
                    >

                        🏗

                    </div>


                    <h3>
                        Project Settings
                    </h3>


                    <p>

                        Configure project
                        related settings
                        and information.

                    </p>


                    <span class="card-arrow">
                        →
                    </span>


                </a>


                <!-- PROJECT ASSIGNMENT -->

                <a
                    href="#"
                    class="card"
                >


                    <div
                        class="card-icon icon-assignment"
                    >

                        👥

                    </div>


                    <h3>
                        Project Assignment
                    </h3>


                    <p>

                        Manage users assigned
                        to projects and
                        access permissions.

                    </p>


                    <span class="card-arrow">
                        →
                    </span>


                </a>


                <!-- SYSTEM SETTINGS -->

                <a
                    href="#"
                    class="card"
                >


                    <div
                        class="card-icon icon-system"
                    >

                        ⚙

                    </div>


                    <h3>
                        System Settings
                    </h3>


                    <p>

                        General configuration
                        and system preferences.

                    </p>


                    <span class="card-arrow">
                        →
                    </span>


                </a>


            </div>


        </div>


    </main>


</div>


</body>

</html>
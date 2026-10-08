<?php

/* =========================================
   AUTO PAGE TRACKER
========================================= */

if (
    !isset($_SESSION['user_id']) ||
    !isset($db)
) {
    return;
}


/* =========================================
   ONLY TRACK PAGE VIEW
   Ignore POST / AJAX
========================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    return;
}


/* =========================================
   CURRENT PAGE
========================================= */

$page_file = basename($_SERVER['PHP_SELF'] ?? '');

$page_name = pathinfo($page_file, PATHINFO_FILENAME);


/* =========================================
   IGNORE SYSTEM / AJAX / AUTH PAGES
========================================= */

$ignore_pages = [
    'login',
    'logout',
    'activity_logger',
    'page_tracker',
    'session_timeout'
];

if (in_array(strtolower($page_name), $ignore_pages, true)) {
    return;
}


/* =========================================
   PAGE NAME
========================================= */

$page_labels = [

    'dashboard'             => 'Dashboard',
    'staff_dashboard'      => 'Staff Dashboard',

    'project_center'        => 'Project Center',
    'project_register'     => 'Project Register',
    'project_details'      => 'Project Details',

    'activity_logs'         => 'Activity Logs',
    'active_users'          => 'Active Users',

    'viewletterin'          => 'View Letter In',
    'viewletterout'         => 'View Letter Out',
    'viewletteroutacknowledged' => 'View Letter Out Acknowledged',

    'users'                 => 'User Management',
    'user_management'      => 'User Management',

    'settings'              => 'Settings',
    'system_settings'       => 'System Settings'
];


/* =========================================
   USE FRIENDLY NAME IF AVAILABLE
========================================= */

$module = $page_labels[$page_name]
    ?? ucwords(
        str_replace(
            ['_', '-'],
            ' ',
            $page_name
        )
    );


/* =========================================
   RECORD ID
========================================= */

$record_id = null;

if (isset($_GET['project_id'])) {

    $record_id = (int)$_GET['project_id'];

} elseif (isset($_GET['id'])) {

    $record_id = (int)$_GET['id'];
}


/* =========================================
   DESCRIPTION
========================================= */

$description =
    'User opened ' . $module . ' page';


/* =========================================
   LOG ACTIVITY
========================================= */

logActivity(
    $db,
    'VIEW',
    $module,
    $record_id,
    $description
);
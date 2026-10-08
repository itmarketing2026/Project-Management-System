<?php

/* =========================================
   SESSION TIMEOUT + USER SESSION TRACKING
   1 HOUR OF INACTIVITY
========================================= */

$timeout_duration = 3600; // 1 hour


/* =========================================
   CHECK SESSION TIMEOUT
========================================= */

if (isset($_SESSION['LAST_ACTIVITY'])) {

    $inactive_time =
        time() - $_SESSION['LAST_ACTIVITY'];


    /* =====================================
       AUTO LOGOUT
    ===================================== */

    if ($inactive_time >= $timeout_duration) {

        /*
         * Connect database
         * untuk update user_sessions
         */
        require_once __DIR__ . '/msqli_connect.php';


        /* =================================
           MARK CURRENT SESSION INACTIVE
        ================================= */

        if (
            isset($db) &&
            isset($_SESSION['user_id'])
        ) {

            $session_id = session_id();


            $stmt = $db->prepare("
                UPDATE user_sessions
                SET
                    is_active = 0,
                    last_activity = NOW()
                WHERE session_id = ?
            ");


            if ($stmt) {

                $stmt->bind_param(
                    "s",
                    $session_id
                );

                $stmt->execute();

                $stmt->close();
            }
        }


        /* =================================
           DESTROY SESSION
        ================================= */

        session_unset();

        session_destroy();


        /* =================================
           REDIRECT LOGIN
        ================================= */

        header(
            "Location: login.php?timeout=1"
        );

        exit;
    }
}


/* =========================================
   UPDATE PHP SESSION ACTIVITY
========================================= */

$_SESSION['LAST_ACTIVITY'] = time();


/* =========================================
   TRACK USER SESSION
========================================= */

if (isset($_SESSION['user_id'])) {

    /*
     * Database connection
     */
    require_once __DIR__ . '/msqli_connect.php';


    $user_id =
        (int)$_SESSION['user_id'];


    $session_id =
        session_id();


    $ip_address =
        $_SERVER['REMOTE_ADDR']
        ?? null;


    $user_agent =
        $_SERVER['HTTP_USER_AGENT']
        ?? null;


    /* =====================================
       CHECK EXISTING SESSION
    ===================================== */

    $stmt = $db->prepare("
        SELECT id
        FROM user_sessions
        WHERE session_id = ?
        LIMIT 1
    ");


    $stmt->bind_param(
        "s",
        $session_id
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $existing =
        $result->fetch_assoc();


    $stmt->close();


    /* =====================================
       CREATE NEW SESSION
    ===================================== */

    if (!$existing) {

        $stmt = $db->prepare("
            INSERT INTO user_sessions
            (
                user_id,
                session_id,
                ip_address,
                user_agent,
                last_activity,
                created_at,
                is_active
            )
            VALUES
            (
                ?, ?, ?, ?, NOW(), NOW(), 1
            )
        ");


        if ($stmt) {

            $stmt->bind_param(
                "isss",
                $user_id,
                $session_id,
                $ip_address,
                $user_agent
            );


            $stmt->execute();

            $stmt->close();
        }


    } else {

        /* =================================
           UPDATE EXISTING SESSION
        ================================= */

        $stmt = $db->prepare("
            UPDATE user_sessions
            SET
                user_id = ?,
                last_activity = NOW(),
                ip_address = ?,
                user_agent = ?,
                is_active = 1
            WHERE session_id = ?
        ");


        if ($stmt) {

            $stmt->bind_param(
                "isss",
                $user_id,
                $ip_address,
                $user_agent,
                $session_id
            );


            $stmt->execute();

            $stmt->close();
        }
    }
}

/* =========================================
   AUTO PAGE VIEW TRACKING
========================================= */

if (
    isset($_SESSION['user_id']) &&
    $_SERVER['REQUEST_METHOD'] === 'GET'
) {

    /* Load activity logger */
    require_once __DIR__ . '/activity_logger.php';


    /* Current PHP page */
    $current_page = basename(
        $_SERVER['PHP_SELF'] ?? ''
    );


    /* Remove .php */
    $page_name = pathinfo(
        $current_page,
        PATHINFO_FILENAME
    );


    /* Pages to ignore */
    $ignored_pages = [
        'login',
        'logout',
        'session_timeout',
        'activity_logger'
    ];


    if (!in_array(
        strtolower($page_name),
        $ignored_pages,
        true
    )) {

        /* Friendly page name */

        $page_labels = [

            'dashboard'
                => 'Dashboard',

            'staff_dashboard'
                => 'Staff Dashboard',

            'infra'
                => 'Infra',

            'project_center'
                => 'Project Center',

            'project_workspace'
                => 'Project Workspace',

            'project_register'
                => 'Project Register',

            'project_register_list'
                => 'Project Register',

            'activity_logs'
                => 'Activity Logs',

            'active_users'
                => 'Active Users',

            'letterin_list'
                => 'Letters In',

            'letterout_list'
                => 'Letters Out',

            'bq_list'
                => 'BQ',

            'eot'
                => 'Extension of Time',

            'memo'
                => 'Memo',

            'loa_list'
                => 'LOA',

            'wr'
                => 'Weekly Report',

            'drawing_list'
                => 'Drawing',

            'interim_claim_list'
                => 'Interim Claim'
        ];


        $module =
            $page_labels[$page_name]
            ?? ucwords(
                str_replace(
                    ['_', '-'],
                    ' ',
                    $page_name
                )
            );


        /* Project ID / Record ID */

        $record_id = null;

        if (
            isset($_GET['project_id']) &&
            is_numeric($_GET['project_id'])
        ) {

            $record_id =
                (int)$_GET['project_id'];

        } elseif (
            isset($_GET['id']) &&
            is_numeric($_GET['id'])
        ) {

            $record_id =
                (int)$_GET['id'];
        }


        /* Prevent duplicate refresh */

        $current_url =
            $current_page .
            '?' .
            ($_SERVER['QUERY_STRING'] ?? '');


        $last_tracked_page =
            $_SESSION['LAST_PAGE_TRACKED']
            ?? '';


        if ($last_tracked_page !== $current_url) {

            logActivity(
                $db,
                'VIEW',
                $module,
                $record_id,
                'User opened ' . $module . ' page'
            );


            $_SESSION['LAST_PAGE_TRACKED'] =
                $current_url;
        }
    }
}
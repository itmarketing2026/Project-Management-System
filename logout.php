<?php

// logout.php

session_start();


/* ===============================
   DATABASE CONNECTION
================================ */

require_once 'include/msqli_connect.php';


/* ===============================
   ACTIVITY LOGGER
================================ */

require_once 'include/activity_logger.php';


/* ===============================
   LOG USER LOGOUT
================================ */

if (isset($_SESSION['user_id'])) {

    logActivity(
        $db,
        'LOGOUT',
        'Authentication',
        null,
        'User logged out'
    );
}


/* ===============================
   MARK USER SESSION INACTIVE
================================ */

if (isset($_SESSION['user_id'])) {

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


/* ===============================
   DESTROY SESSION
================================ */

session_unset();

session_destroy();


/* ===============================
   REDIRECT TO LOGIN
================================ */

header("Location: login.php");

exit;
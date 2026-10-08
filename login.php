<?php

/* =========================================================
   SECURITY HEADERS
========================================================= */

// Force HTTPS with HSTS
if (
    isset($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== 'off'
) {
    header(
        'Strict-Transport-Security: max-age=31536000'
    );
}

// Prevent MIME type sniffing
header(
    'X-Content-Type-Options: nosniff'
);

// Prevent clickjacking
header(
    'X-Frame-Options: SAMEORIGIN'
);

// Control referrer information
header(
    'Referrer-Policy: strict-origin-when-cross-origin'
);

// Disable unnecessary browser features
header(
    'Permissions-Policy: camera=(), microphone=(), geolocation=()'
);

// Content Security Policy
header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self'; " .
    "style-src 'self' 'unsafe-inline'; " .
    "img-src 'self' data:; " .
    "font-src 'self' data:; " .
    "object-src 'none'; " .
    "base-uri 'self'; " .
    "form-action 'self'; " .
    "frame-ancestors 'self';"
);


/* =========================================================
   SESSION SECURITY
========================================================= */

// Prevent JavaScript from accessing session cookie
ini_set(
    'session.cookie_httponly',
    '1'
);

// Session cookie only sent through HTTPS
ini_set(
    'session.cookie_secure',
    '1'
);

// Reduce cross-site session abuse
ini_set(
    'session.cookie_samesite',
    'Lax'
);


/* ======================
   START SESSION
========================= */

session_start();


/* ======================
   ERROR HANDLING
========================= */

if ($_SERVER['SERVER_NAME'] === 'localhost') {

    ini_set(
        'display_errors',
        1
    );

    error_reporting(E_ALL);

} else {

    ini_set(
        'display_errors',
        0
    );

    error_reporting(0);
}


/* =========================================================
   DATABASE CONNECTION
========================================================= */

require_once 'include/msqli_connect.php';


/* =========================================================
   ACTIVITY LOGGER
========================================================= */

require_once 'include/activity_logger.php';


/* =========================================================
   FIX BROKEN SESSION
========================================================= */

if (
    isset($_SESSION['user_id']) &&
    !isset($_SESSION['account_type'])
) {

    session_unset();

    session_destroy();

    session_start();
}


/* =========================================================
   REDIRECT IF ALREADY LOGGED IN
========================================================= */

if (
    isset(
        $_SESSION['user_id'],
        $_SESSION['account_type']
    )
) {

    $user_id = $_SESSION['user_id'];

    $role = $_SESSION['account_type'];


    /* =====================================================
       CHECK PROJECT ASSIGNMENT
    ===================================================== */

    $projectCheck = $db->prepare("
        SELECT 1
        FROM user_project
        WHERE user_id = ?
        LIMIT 1
    ");

    if ($projectCheck) {

        $projectCheck->bind_param(
            "i",
            $user_id
        );

        $projectCheck->execute();

        $hasProject = $projectCheck
            ->get_result()
            ->num_rows > 0;

        $projectCheck->close();

    } else {

        $hasProject = false;
    }


    /* =====================================================
       GET DESIGNATION
    ===================================================== */

    $designation =
        $_SESSION['designation'] ?? '';


    /* =====================================================
       ROLE CONTROL
    ===================================================== */

    if (
        $role === 'Client Admin' ||
        $role === 'Management' ||
        $role === 'HRA' ||
        $role === 'Admin (QA/QC)' ||
        strtolower(trim($designation)) ===
        'contract executive'
    ) {

        header(
            "Location: dashboard.php"
        );

        exit;

    } else {

        header(
            "Location: staff_dashboard.php"
        );

        exit;
    }
}


/* =========================================================
   ERROR MESSAGE
========================================================= */

$error = '';


/* =========================================================
   LOGIN PROCESS
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {


    /* =====================================================
       GET INPUT
    ===================================================== */

    $username = strtolower(
        trim(
            $_POST['username'] ?? ''
        )
    );

    $password = trim(
        $_POST['password'] ?? ''
    );


    /* =====================================================
       VALIDATE INPUT
    ===================================================== */

    if (
        $username === '' ||
        $password === ''
    ) {

        $error =
            "Please enter username and password.";

    } else {


        /* =================================================
           FIND USER
        ================================================= */

        $stmt = $db->prepare("
            SELECT *
            FROM users
            WHERE username = ?
            LIMIT 1
        ");


        if (!$stmt) {

            $error =
                "Database error.";

        } else {

            $stmt->bind_param(
                "s",
                $username
            );

            $stmt->execute();

            $result =
                $stmt->get_result();


            /* =============================================
               USER FOUND
            ============================================= */

            if (
                $result &&
                $result->num_rows === 1
            ) {

                $user =
                    $result->fetch_assoc();


                /* =========================================
                   VERIFY PASSWORD
                ========================================= */

                if (
                    password_verify(
                        $password,
                        $user['password']
                    )
                ) {


                    /* =====================================
                       SECURITY
                    ===================================== */

                    session_regenerate_id(
                        true
                    );


                    /* =====================================
                       SET SESSION
                    ===================================== */

                    $_SESSION['user_id'] =
                        $user['id'];

                    $_SESSION['username'] =
                        $user['username'];

                    $_SESSION['full_name'] =
                        $user['full_name'];

                    $_SESSION['account_type'] =
                        $user['account_type'];

                    $_SESSION['designation'] =
                        $user['designation'];

                    $_SESSION['property_access'] =
                        $user['property_access'];

                    $_SESSION['legal_access'] =
                        $user['legal_access'];

                    $_SESSION['memo_access'] =
                        $user['memo_access'] ?? 1;


                    /* =====================================
                       CREATE ACTIVE USER SESSION
                    ===================================== */

                    $session_id =
                        session_id();

                    $user_id =
                        (int)$user['id'];

                    $ip_address =
                        $_SERVER['REMOTE_ADDR']
                        ?? null;

                    $user_agent =
                        $_SERVER['HTTP_USER_AGENT']
                        ?? null;


                    /* =====================================
                       REMOVE OLD RECORD USING
                       SAME SESSION ID
                    ===================================== */

                    $stmt = $db->prepare("
                        DELETE FROM user_sessions
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


                    /* =====================================
                       INSERT CURRENT LOGIN SESSION
                    ===================================== */

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


                    /* =====================================
                       AUDIT LOG - LOGIN SUCCESS
                    ===================================== */

                    logActivity(
                        $db,
                        'LOGIN',
                        'Authentication',
                        null,
                        'User logged in successfully'
                    );


                    /* =====================================
                       CHECK PROJECT ASSIGNMENT
                    ===================================== */

                    $projectCheck = $db->prepare("
                        SELECT 1
                        FROM user_project
                        WHERE user_id = ?
                        LIMIT 1
                    ");

                    if ($projectCheck) {

                        $projectCheck->bind_param(
                            "i",
                            $user['id']
                        );

                        $projectCheck->execute();

                        $hasProject =
                            $projectCheck
                                ->get_result()
                                ->num_rows > 0;

                        $projectCheck->close();

                    } else {

                        $hasProject = false;
                    }


                    /* =====================================
                       ROLE
                    ===================================== */

                    $role =
                        $user['account_type'];


                    /* =====================================
                       DESIGNATION
                    ===================================== */

                    $designation =
                        $user['designation'] ?? '';


                    /* =====================================
                       REDIRECT
                    ===================================== */

                    if (
                        $role === 'Client Admin' ||
                        $role === 'Management' ||
                        $role === 'HRA' ||
                        $role === 'Admin (QA/QC)' ||
                        strtolower(trim($designation)) ===
                        'contract executive'
                    ) {

                        header(
                            "Location: dashboard.php"
                        );

                    } else {

                        header(
                            "Location: staff_dashboard.php"
                        );
                    }

                    exit;


                } else {


                    /* =====================================
                       AUDIT LOG - WRONG PASSWORD
                    ===================================== */

                    logActivity(
                        $db,
                        'LOGIN_FAILED',
                        'Authentication',
                        null,
                        'Invalid password for username: ' .
                        $username
                    );


                    $error =
                        "Invalid username or password.";
                }


            } else {


                /* =========================================
                   AUDIT LOG - UNKNOWN USERNAME
                ========================================= */

                logActivity(
                    $db,
                    'LOGIN_FAILED',
                    'Authentication',
                    null,
                    'Login attempt with unknown username: ' .
                    $username
                );


                $error =
                    "Invalid username or password.";
            }


            $stmt->close();
        }
    }
}

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">


    <title>
        Project Management System
    </title>


    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >


    <style>

        :root {

            --primary: #1f2d3d;

            --secondary: #3a4f7a;

            --border: #d9dee5;

            --bg: #f4f6f9;

            --text: #2c2c2c;
        }


        * {

            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                "Segoe UI",
                Arial,
                sans-serif;

            background:
                var(--bg);

            height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;
        }


        /* ===============================================
           LOGIN WRAPPER
        ================================================ */

        .login-wrapper {

            width: 100%;

            max-width: 420px;

            padding: 20px;
        }


        /* ===============================================
           LOGIN CARD
        ================================================ */

        .login-card {

            background: #fff;

            border:
                1px solid
                var(--border);

            border-radius: 6px;

            padding:
                42px 38px;

            box-shadow:
                0 12px 35px
                rgba(0, 0, 0, 0.08);
        }


        /* ===============================================
           BRAND
        ================================================ */

        .brand {

            text-align: center;

            margin-bottom: 30px;
        }


        .brand h1 {

            margin: 0;

            font-size: 22px;

            color:
                var(--primary);

            font-weight: 600;
        }


        /* ===============================================
           ERROR MESSAGE
        ================================================ */

        .error {

            background:
                #fdecea;

            color:
                #b02a2a;

            padding:
                10px 12px;

            border-radius: 4px;

            font-size: 13px;

            margin-bottom: 18px;

            border:
                1px solid
                #f5c6c2;
        }


        /* ===============================================
           FIELD
        ================================================ */

        .field {

            margin-bottom: 16px;
        }


        .field label {

            display: block;

            font-size: 13px;

            font-weight: 600;

            margin-bottom: 6px;

            color:
                var(--text);
        }


        .field input {

            width: 100%;

            padding:
                11px 12px;

            font-size: 14px;

            border:
                1px solid
                var(--border);

            border-radius: 4px;

            outline: none;

            transition:
                border-color 0.2s;
        }


        .field input:focus {

            border-color:
                var(--secondary);
        }


        /* ===============================================
           ACTIONS
        ================================================ */

        .actions {

            display: flex;

            justify-content:
                flex-end;

            align-items: center;

            margin:
                14px 0 22px;

            font-size: 13px;
        }


        .actions a {

            color:
                var(--secondary);

            text-decoration: none;
        }


        .actions a:hover {

            text-decoration: underline;
        }


        /* ===============================================
           BUTTON
        ================================================ */

        button {

            width: 100%;

            padding: 12px;

            background:
                var(--primary);

            border: none;

            color: #fff;

            font-size: 15px;

            font-weight: 600;

            border-radius: 4px;

            cursor: pointer;

            transition:
                background 0.2s;
        }


        button:hover {

            background:
                #16212f;
        }


        /* ===============================================
           FOOTER
        ================================================ */

        .footer {

            margin-top: 26px;

            text-align: center;

            font-size: 12px;

            color:
                #8a8a8a;
        }

    </style>

</head>


<body>


    <div class="login-wrapper">

        <div class="login-card">


            <!-- =========================================
                 BRAND
            ========================================== -->

            <div class="brand">

                <h1>
                    Project Management System
                </h1>

            </div>


            <!-- =========================================
                 ERROR
            ========================================== -->

            <?php if ($error): ?>

                <div class="error">

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <!-- =========================================
                 LOGIN FORM
            ========================================== -->

            <form method="POST">


                <!-- USERNAME -->

                <div class="field">

                    <label for="username">
                        Username
                    </label>

                    <input
                        id="username"
                        type="text"
                        name="username"
                        autocomplete="username"
                        value="<?= htmlspecialchars($username ?? '') ?>"
                        required
                        autofocus
                    >

                </div>


                <!-- PASSWORD -->

                <div class="field">

                    <label for="password">
                        Password
                    </label>

                    <input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <!-- FORGOT PASSWORD -->

                <div class="actions">

                    <a href="reset_password.php">
                        Forgot password?
                    </a>

                </div>


                <!-- LOGIN BUTTON -->

                <button type="submit">
                    Sign In
                </button>


            </form>


            <!-- =========================================
                 FOOTER
            ========================================== -->

            <div class="footer">

                © <?= date('Y') ?>

                Project Management System.

                All rights reserved.

            </div>


        </div>

    </div>


</body>

</html>
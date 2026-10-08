<?php

session_start();

$title = "View Letter Out - Archive";

require_once 'include/msqli_connect.php';


/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    header("Location: login.php");
    exit;
}


/* =========================================================
   JUMP-TO-POSITION
========================================================= */

if (isset($_GET['pos'], $_GET['jump_project_id'])) {

    $jumpProjectId = intval($_GET['jump_project_id']);
    $jumpPos       = max(1, intval($_GET['pos']));

    if ($jumpProjectId) {

        $stmtJump = $db->prepare("
            SELECT id
            FROM letter_out_archive
            WHERE project_id = ?
            ORDER BY id ASC
            LIMIT 1 OFFSET ?
        ");

        $offset = $jumpPos - 1;

        $stmtJump->bind_param(
            "ii",
            $jumpProjectId,
            $offset
        );

        $stmtJump->execute();

        $jumpRow = $stmtJump
            ->get_result()
            ->fetch_assoc();

        $stmtJump->close();

        if ($jumpRow) {

            header(
                "Location: viewletteroutarchive.php?id=" .
                (int)$jumpRow['id']
            );

            exit;
        }
    }
}


/* =========================================================
   CSRF TOKEN
========================================================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];


/* =========================================================
   ERROR PAGE
========================================================= */

function render_error(
    string $message,
    int $httpCode = 200
): void {

    http_response_code($httpCode);

    ?>

    <!DOCTYPE html>

    <html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1"
        >

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

                background:
                    linear-gradient(
                        #f3f4f6,
                        #eef1f5
                    );

                margin:0;

                min-height:100vh;

                display:flex;

                flex-direction:column;

                align-items:center;

                color:#1e293b;
            }

            .page-header {

                width:100%;

                background:
                    linear-gradient(
                        135deg,
                        #007bff,
                        #0056b3
                    );

                padding:20px 0 22px;

                margin-bottom:22px;

                box-shadow:
                    0 4px 12px rgba(0,0,0,.1);
            }

            .page-header-inner {

                max-width:var(--max);

                margin:0 auto;

                padding:0 24px;
            }

            .page-header .eyebrow {

                color:
                    rgba(255,255,255,.75);

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

                box-shadow:
                    0 6px 18px rgba(0,0,0,.06);

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

                <div class="eyebrow">
                    Letters Out — Archive
                </div>

                <h1>
                    Project Management System
                </h1>

            </div>

        </div>

        <div class="error-card">

            <div class="error-icon">
                !
            </div>

            <div class="error-message">
                <?= htmlspecialchars($message) ?>
            </div>

            <a
                href="javascript:history.back()"
                class="error-back"
            >
                ← Go Back
            </a>

        </div>

    </body>

    </html>

    <?php

    exit;
}


/* =========================================================
   GET RECORD ID
========================================================= */

$id = intval($_GET['id'] ?? 0);

if (!$id) {

    render_error(
        "Invalid record. The archived letter you're looking for could not be identified."
    );
}


/* =========================================================
   FETCH ARCHIVE
========================================================= */

$stmt = $db->prepare("
    SELECT *
    FROM letter_out_archive
    WHERE id = ?
");

if (!$stmt) {

    render_error(
        "A system error occurred. Please try again later."
    );
}

$stmt->bind_param(
    "i",
    $id
);

$stmt->execute();

$archive = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();


if (!$archive) {

    render_error(
        "No record found. This archived letter may have been deleted."
    );
}


/* =========================================================
   PROJECT / USER
========================================================= */

$project_id = (int)($archive['project_id'] ?? 0);

$user_id = (int)$_SESSION['user_id'];

$role = strtolower(
    trim(
        $_SESSION['account_type'] ?? ''
    )
);


/* =========================================================
   USER DESIGNATION
========================================================= */

$stmt_desig = $db->prepare("
    SELECT designation
    FROM users
    WHERE id = ?
");

$stmt_desig->bind_param(
    "i",
    $user_id
);

$stmt_desig->execute();

$designation = trim(
    $stmt_desig
        ->get_result()
        ->fetch_assoc()['designation'] ?? ''
);

$stmt_desig->close();


/* =========================================================
   PERMISSION
========================================================= */

$can_edit_archive =
    in_array(
        $role,
        [
            'client admin',
            'admin (qa/qc)'
        ]
    )
    ||
    in_array(
        $designation,
        [
            'Project Manager',
            'Project Engineer'
        ]
    );


$is_creator =
    isset($archive['created_by'])
    &&
    trim($archive['created_by'])
        ===
    trim($_SESSION['full_name'] ?? '');


/* =========================================================
   SAVE EDIT
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['save_changes'])
) {

    /* CSRF */

    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token'] ?? ''
        )
    ) {

        render_error(
            "Your session has expired or the request could not be verified. Please go back and try again.",
            400
        );
    }


    /* Permission */

    if (
        !$can_edit_archive
        &&
        !$is_creator
    ) {

        render_error(
            "You do not have permission to edit this record.",
            403
        );
    }


    /* =====================================================
       ADMIN / QA-QC / PM / PE / CREATOR
    ===================================================== */

    /*
     * Admin / QA-QC / PM / PE:
     *   - Can edit request date
     *   - Can edit title
     *   - Can replace document
     *
     * Creator:
     *   - Can edit title
     *   - Running number is handled separately below
     *   - Cannot edit request date or replace document
     */

    $title_input =
        trim(
            $_POST['title'] ?? ''
        );

    if ($can_edit_archive) {

        $request_date =
            $_POST['request_date'] ?? '';


        /* ================================================
           REPLACE FILE
        ================================================= */

        $new_file_json =
            $archive['file_path'];


        if (
            !empty(
                $_FILES['new_document']['name'][0]
            )
        ) {

            $upload_dir =
                __DIR__ .
                "/uploads/cerun_letters/";


            if (!is_dir($upload_dir)) {

                mkdir(
                    $upload_dir,
                    0777,
                    true
                );
            }


            $new_files = [];


            foreach (
                $_FILES['new_document']['error']
                as $key => $error
            ) {

                if (
                    $error === UPLOAD_ERR_OK
                ) {

                    $original_name =
                        basename(
                            $_FILES['new_document']['name'][$key]
                        );


                    $safe_name =
                        preg_replace(
                            '/[^A-Za-z0-9._-]/',
                            '_',
                            $original_name
                        );


                    $new_name =
                        time()
                        . "_"
                        .
                        $safe_name;


                    $target =
                        $upload_dir
                        .
                        $new_name;


                    if (
                        move_uploaded_file(
                            $_FILES['new_document']['tmp_name'][$key],
                            $target
                        )
                    ) {

                        $new_files[] =
                            "uploads/cerun_letters/"
                            .
                            $new_name;
                    }
                }
            }


            /* ============================================
               DELETE OLD FILES
            ============================================ */

            if (!empty($new_files)) {

                $old_files =
                    json_decode(
                        $archive['file_path'],
                        true
                    );


                if (
                    is_array($old_files)
                ) {

                    foreach (
                        $old_files
                        as $old
                    ) {

                        $old_path =
                            __DIR__
                            .
                            "/"
                            .
                            $old;


                        if (
                            file_exists($old_path)
                        ) {

                            unlink($old_path);
                        }
                    }
                }


                $new_file_json =
                    json_encode(
                        $new_files
                    );
            }
        }


        /* ================================================
           UPDATE ADMIN / QA-QC / PM / PE
        ================================================= */

        $update = $db->prepare("
            UPDATE letter_out_archive

            SET
                request_date = ?,
                title = ?,
                file_path = ?

            WHERE id = ?
        ");


        $update->bind_param(
            "sssi",
            $request_date,
            $title_input,
            $new_file_json,
            $id
        );


        $update->execute();

        $update->close();

    } elseif ($is_creator) {

        /* ================================================
           CREATOR - TITLE ONLY
        ================================================= */

        if ($title_input === '') {

            render_error(
                "Title cannot be empty.",
                400
            );
        }


        $update_creator =
            $db->prepare("
                UPDATE letter_out_archive

                SET title = ?

                WHERE id = ?
                AND created_by = ?
            ");


        $creator_name =
            trim(
                $_SESSION['full_name'] ?? ''
            );


        $update_creator->bind_param(
            "sis",
            $title_input,
            $id,
            $creator_name
        );


        $update_creator->execute();

        $update_creator->close();
    }


    /* =====================================================
       RUNNING NUMBER
    ===================================================== */

    if (
        $can_edit_archive
        ||
        $is_creator
    ) {

        $running_number =
            trim(
                $_POST['running_number'] ?? ''
            );


        if (
            $running_number === ''
        ) {

            render_error(
                "Running number cannot be empty.",
                400
            );
        }


        if ($can_edit_archive) {

            $update2 =
                $db->prepare("
                    UPDATE letter_out_archive

                    SET running_number = ?

                    WHERE id = ?
                ");


            $update2->bind_param(
                "si",
                $running_number,
                $id
            );

        } else {

            $creator_name =
                trim(
                    $_SESSION['full_name'] ?? ''
                );


            $update2 =
                $db->prepare("
                    UPDATE letter_out_archive

                    SET running_number = ?

                    WHERE id = ?
                    AND created_by = ?
                ");


            $update2->bind_param(
                "sis",
                $running_number,
                $id,
                $creator_name
            );
        }


        $update2->execute();

        $update2->close();
    }


    header(
        "Location: viewletteroutarchive.php?id=" .
        $id
    );

    exit;
}


/* =========================================================
   DOCUMENT TYPES
========================================================= */

$previewable_ext = [
    'pdf',
    'jpg',
    'jpeg',
    'png',
    'gif',
    'webp'
];


/* =========================================================
   RENDER ARCHIVE DOCUMENT
========================================================= */

function render_archive_doc_item(
    $file,
    $label
) {

    global $previewable_ext;


    /*
    |--------------------------------------------------------------------------
    | CLEAN ORIGINAL PATH
    |--------------------------------------------------------------------------
    */

    $file = trim(
        (string)$file
    );


    /*
    |--------------------------------------------------------------------------
    | Convert Windows slash
    |--------------------------------------------------------------------------
    */

    $file =
        str_replace(
            '\\',
            '/',
            $file
        );


    /*
    |--------------------------------------------------------------------------
    | URL DECODE
    |--------------------------------------------------------------------------
    */

    $file =
        urldecode($file);


    /*
    |--------------------------------------------------------------------------
    | REMOVE QUERY STRING
    |--------------------------------------------------------------------------
    */

    $file =
        explode(
            '?',
            $file,
            2
        )[0];


    /*
    |--------------------------------------------------------------------------
    | HANDLE FULL URL
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | https://mggroup.my/projectmanagement/uploads/cerun_letters/file.pdf
    |
    | becomes:
    |
    | cerun_letters/file.pdf
    |
    */

    if (
        preg_match(
            '~^https?://~i',
            $file
        )
    ) {

        $urlPath =
            parse_url(
                $file,
                PHP_URL_PATH
            );


        if (
            $urlPath
            &&
            stripos(
                $urlPath,
                '/uploads/'
            ) !== false
        ) {

            $pos =
                stripos(
                    $urlPath,
                    '/uploads/'
                );


            $file =
                substr(
                    $urlPath,
                    $pos
                    +
                    strlen('/uploads/')
                );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | REMOVE LEADING SLASH
    |--------------------------------------------------------------------------
    */

    $file =
        ltrim(
            $file,
            '/'
        );


    /*
    |--------------------------------------------------------------------------
    | REMOVE uploads/ PREFIX
    |--------------------------------------------------------------------------
    */

    if (
        stripos(
            $file,
            'uploads/'
        ) === 0
    ) {

        $relative_file =
            substr(
                $file,
                strlen('uploads/')
            );

    } else {

        $relative_file =
            $file;
    }


    /*
    |--------------------------------------------------------------------------
    | SECURITY
    |--------------------------------------------------------------------------
    */

    if (
        strpos(
            $relative_file,
            '..'
        ) !== false
    ) {

        echo '
            <div class="doc-item">
                <div class="value-box">
                    Invalid document path.
                </div>
            </div>
        ';

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | FILE NAME ONLY FOR DISPLAY
    |--------------------------------------------------------------------------
    */

    $filename =
        basename(
            $relative_file
        );


    /*
    |--------------------------------------------------------------------------
    | DISPLAY NAME
    |--------------------------------------------------------------------------
    */

    $display_name =
        strlen($filename) > 80
        ?
        substr(
            $filename,
            0,
            80
        ) . '...'
        :
        $filename;


    /*
    |--------------------------------------------------------------------------
    | EXTENSION
    |--------------------------------------------------------------------------
    */

    $ext =
        strtolower(
            pathinfo(
                $filename,
                PATHINFO_EXTENSION
            )
        );


    /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    |
    | DO NOT USE basename() HERE.
    |
    | We need the FULL relative path.
    |
    */

    $download_url =
        'download_file.php?file='
        .
        urlencode(
            $relative_file
        );


    $is_img =
        in_array(
            $ext,
            $previewable_ext,
            true
        )
        &&
        $ext !== 'pdf';


    $is_pdf =
        ($ext === 'pdf');

    ?>

    <div class="doc-item">

        <a
            href="<?= htmlspecialchars($download_url) ?>"
            target="_blank"
            class="file-item"
        >

            <span class="file-icon">
                📄
            </span>

            <?= htmlspecialchars(
                $label . $display_name
            ) ?>

        </a>


        <?php if ($is_pdf): ?>

            <!--
            PDF preview intentionally disabled.
            Opening the document uses download_file.php.
            -->

        <?php endif; ?>


        <?php if ($is_img): ?>

            <!--
            Image preview intentionally disabled.
            -->

        <?php endif; ?>

    </div>

    <?php
}


/* =========================================================
   RENDER DOCUMENTS
========================================================= */

function renderDocuments(
    $archive
) {

    $file_raw =
        $archive['file_path'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | FIRST JSON DECODE
    |--------------------------------------------------------------------------
    */

    $files =
        json_decode(
            $file_raw,
            true
        );


    /*
    |--------------------------------------------------------------------------
    | SECOND JSON DECODE
    |--------------------------------------------------------------------------
    | Handles escaped JSON
    |--------------------------------------------------------------------------
    */

    if (!$files) {

        $file_raw =
            trim(
                $file_raw,
                '"'
            );


        $file_raw =
            stripslashes(
                $file_raw
            );


        $files =
            json_decode(
                $file_raw,
                true
            );
    }


    /*
    |--------------------------------------------------------------------------
    | MULTIPLE FILES
    |--------------------------------------------------------------------------
    */

    if (
        !empty($files)
        &&
        is_array($files)
    ) {

        echo '
            <div
                style="
                    display:flex;
                    flex-direction:column;
                    gap:8px;
                "
            >
        ';


        foreach (
            $files
            as $index => $file
        ) {

            /*
             * Handle possible object format:
             *
             * {
             *   "path": "...",
             *   "name": "..."
             * }
             */

            if (
                is_array($file)
                &&
                isset($file['path'])
            ) {

                $file =
                    $file['path'];
            }


            render_archive_doc_item(
                $file,
                "Document "
                .
                ($index + 1)
                .
                " — "
            );
        }


        echo '</div>';


    } elseif (
        !empty($file_raw)
    ) {

        render_archive_doc_item(
            $file_raw,
            ""
        );


    } else {

        echo 'No document uploaded';
    }
}


/* =========================================================
   PREV / NEXT NAVIGATION
========================================================= */

const NAV_DROPDOWN_LIMIT = 300;


$prev_id = null;

$next_id = null;

$currentPosition = null;

$totalCount = 0;

$navRows = [];


if ($project_id) {

    /*
    |--------------------------------------------------------------------------
    | TOTAL COUNT
    |--------------------------------------------------------------------------
    */

    $stmtCount =
        $db->prepare("
            SELECT COUNT(*) AS cnt

            FROM letter_out_archive

            WHERE project_id = ?
        ");


    $stmtCount->bind_param(
        "i",
        $project_id
    );


    $stmtCount->execute();


    $totalCount =
        (int)(
            $stmtCount
                ->get_result()
                ->fetch_assoc()['cnt']
            ??
            0
        );


    $stmtCount->close();


    /*
    |--------------------------------------------------------------------------
    | CURRENT POSITION
    |--------------------------------------------------------------------------
    */

    $stmtPos =
        $db->prepare("
            SELECT COUNT(*) AS pos

            FROM letter_out_archive

            WHERE project_id = ?
            AND id <= ?
        ");


    $stmtPos->bind_param(
        "ii",
        $project_id,
        $id
    );


    $stmtPos->execute();


    $posRow =
        $stmtPos
            ->get_result()
            ->fetch_assoc();


    $stmtPos->close();


    $currentPosition =
        (
            $posRow
            &&
            (int)$posRow['pos'] > 0
        )
        ?
        (int)$posRow['pos']
        :
        null;


    /*
    |--------------------------------------------------------------------------
    | PREVIOUS
    |--------------------------------------------------------------------------
    */

    $stmtPrev =
        $db->prepare("
            SELECT id

            FROM letter_out_archive

            WHERE project_id = ?
            AND id < ?

            ORDER BY id DESC

            LIMIT 1
        ");


    $stmtPrev->bind_param(
        "ii",
        $project_id,
        $id
    );


    $stmtPrev->execute();


    $prevRow =
        $stmtPrev
            ->get_result()
            ->fetch_assoc();


    $stmtPrev->close();


    $prev_id =
        $prevRow
        ?
        (int)$prevRow['id']
        :
        null;


    /*
    |--------------------------------------------------------------------------
    | NEXT
    |--------------------------------------------------------------------------
    */

    $stmtNext =
        $db->prepare("
            SELECT id

            FROM letter_out_archive

            WHERE project_id = ?
            AND id > ?

            ORDER BY id ASC

            LIMIT 1
        ");


    $stmtNext->bind_param(
        "ii",
        $project_id,
        $id
    );


    $stmtNext->execute();


    $nextRow =
        $stmtNext
            ->get_result()
            ->fetch_assoc();


    $stmtNext->close();


    $next_id =
        $nextRow
        ?
        (int)$nextRow['id']
        :
        null;


    /*
    |--------------------------------------------------------------------------
    | SMALL PROJECT — DROPDOWN
    |--------------------------------------------------------------------------
    */

    if (
        $totalCount > 0
        &&
        $totalCount <= NAV_DROPDOWN_LIMIT
    ) {

        $stmtNav =
            $db->prepare("
                SELECT id

                FROM letter_out_archive

                WHERE project_id = ?

                ORDER BY id ASC
            ");


        $stmtNav->bind_param(
            "i",
            $project_id
        );


        $stmtNav->execute();


        $resNav =
            $stmtNav->get_result();


        while (
            $row =
            $resNav->fetch_assoc()
        ) {

            $navRows[] = [
                'id' => (int)$row['id']
            ];
        }


        $stmtNav->close();
    }


} else {

    /*
    |--------------------------------------------------------------------------
    | FALLBACK
    |--------------------------------------------------------------------------
    */

    $resCount =
        $db->query("
            SELECT COUNT(*) AS cnt
            FROM letter_out_archive
        ");


    $totalCount =
        (int)(
            $resCount
                ->fetch_assoc()['cnt']
            ??
            0
        );
}


/* =========================================================
   NAV URL
========================================================= */

function nav_url(
    $targetId
) {

    return htmlspecialchars(
        '?id=' . (int)$targetId
    );
}


/* =========================================================
   CACHE
========================================================= */

header(
    'Cache-Control: private, max-age=5'
);


/* =========================================================
   CLOSE DB
========================================================= */

$db->close();

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

:root {

    --accent:#007bff;

    --ink:#1e293b;

    --red:#dc3545;

    --max:900px;
}


* {
    box-sizing:border-box;
}


body {

    font-family:Arial,sans-serif;

    background:
        linear-gradient(
            #f3f4f6,
            #eef1f5
        );

    padding:0 0 32px;

    display:flex;

    flex-direction:column;

    align-items:center;

    margin:0;

    color:var(--ink);
}


body > * {

    width:100%;

    max-width:var(--max);
}


/* =========================================================
   HEADER
========================================================= */

.page-header {

    width:100%;

    background:
        linear-gradient(
            135deg,
            #007bff,
            #0056b3
        );

    padding:20px 0 22px;

    margin-bottom:22px;

    box-shadow:
        0 4px 12px rgba(0,0,0,.1);
}


.page-header-inner {

    max-width:var(--max);

    margin:0 auto;

    padding:0 24px;
}


.page-header .eyebrow {

    color:
        rgba(255,255,255,.75);

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


body > *:not(.page-header) {

    padding-left:24px;

    padding-right:24px;
}


/* =========================================================
   TOOLBAR
========================================================= */

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

    box-shadow:
        0 4px 12px rgba(0,0,0,.05);
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

    transition:
        background .15s;
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

    box-shadow:
        0 1px 0 #d5dbe3;
}


@media(max-width:640px) {

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


/* =========================================================
   CARD
========================================================= */

.card {

    background:#fff;

    border-radius:16px;

    padding:24px;

    margin-bottom:28px;

    box-shadow:
        0 6px 18px rgba(0,0,0,.06);
}


.section-title {

    font-weight:800;

    color:var(--accent);

    border-bottom:
        2px solid var(--accent);

    padding-bottom:6px;

    margin-bottom:18px;
}


.label {

    font-weight:700;

    margin-bottom:6px;

    color:#333;
}


.value-box {

    background:#f9f9f9;

    border:1px solid #ddd;

    border-radius:10px;

    padding:12px 14px;

    margin-bottom:14px;
}


.grid-2 {

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:18px;
}


@media(max-width:640px) {

    .grid-2 {

        grid-template-columns:1fr;
    }
}


/* =========================================================
   HERO
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

    text-transform:uppercase;

    letter-spacing:.8px;

    opacity:.85;
}


.hero h2 {

    margin:6px 0 0;

    font-size:28px;
}


/* =========================================================
   BUTTON
========================================================= */

.btn {

    width:100%;

    background:var(--accent);

    color:#fff;

    padding:16px;

    font-size:18px;

    font-weight:700;

    border:none;

    border-radius:14px;

    cursor:pointer;

    margin-top:12px;

    text-decoration:none;

    display:block;

    text-align:center;

    box-sizing:border-box;
}


.btn:hover {

    background:#0056b3;
}


.btn-danger {

    background:var(--red);
}


.btn-danger:hover {

    background:#b52a37;
}


/* =========================================================
   INPUT
========================================================= */

.input,
.textarea {

    width:100%;

    padding:12px;

    border:1px solid #ddd;

    border-radius:10px;

    margin-bottom:18px;

    box-sizing:border-box;

    font-family:inherit;
}


.textarea {

    min-height:120px;

    resize:vertical;
}


/* =========================================================
   FILE
========================================================= */

.file-item {

    display:flex;

    align-items:center;

    gap:8px;

    padding:8px 12px;

    background:#f1f5ff;

    border:1px solid #dbeafe;

    border-radius:10px;

    text-decoration:none;

    color:#1e40af;

    font-size:13px;

    font-weight:500;

    transition:
        all .2s ease;
}


.file-item:hover {

    background:#2563eb;

    color:#fff;

    transform:
        translateY(-1px);

    box-shadow:
        0 4px 10px rgba(0,0,0,.1);
}


.file-icon {

    font-size:16px;
}


.doc-item {

    margin-bottom:18px;
}


/* =========================================================
   PDF PREVIEW
========================================================= */

.doc-preview-pdf-frame {

    width:100%;

    height:800px;

    border:
        1px solid #dbe1e8;

    border-radius:8px;

    margin-top:12px;

    display:block;
}


@media(max-width:640px) {

    .doc-preview-pdf-frame {

        height:500px;
    }
}


.doc-preview-img {

    max-width:100%;

    border-radius:12px;

    border:
        2px solid #93c5fd;

    margin-top:12px;

    display:block;
}

</style>

</head>


<body>


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">

    <div class="page-header-inner">

        <div class="eyebrow">
            Letters Out — Archive
        </div>

        <h1>
            <?= htmlspecialchars($title) ?>
        </h1>

    </div>

</div>


<!-- =====================================================
     TOOLBAR
===================================================== -->

<div class="toolbar">


    <div class="toolbar-group">

        <a
            href="letterout_list.php?project_id=<?= (int)$project_id ?>"
            class="tb-btn"
        >
            ← Back to Letters
        </a>

    </div>


    <div class="toolbar-group">


        <?php if ($prev_id): ?>

            <a
                href="<?= nav_url($prev_id) ?>"
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


        <?php if (
            $currentPosition
            &&
            $totalCount
        ): ?>


            <?php if (!empty($navRows)): ?>

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
                        ):

                            $pos = $i + 1;

                        ?>

                            <option
                                value="<?= $row['id'] ?>"
                                <?= $row['id'] === $id ? 'selected' : '' ?>
                            >

                                <?= $pos ?>/<?= $totalCount ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </span>


            <?php else: ?>


                <form
                    method="GET"
                    class="jump-box"
                    style="gap:4px;"
                    title="Taip nombor kedudukan dan tekan Go untuk terus ke surat berkenaan"
                >

                    <input
                        type="hidden"
                        name="jump_project_id"
                        value="<?= (int)$project_id ?>"
                    >


                    <input
                        type="number"
                        name="pos"
                        min="1"
                        max="<?= $totalCount ?>"
                        value="<?= $currentPosition ?>"
                        class="jump-select"
                        style="width:56px;"
                    >


                    <span>
                        / <?= $totalCount ?>
                    </span>


                    <button
                        type="submit"
                        class="tb-btn"
                        style="
                            padding:6px 10px;
                            font-size:12px;
                        "
                    >
                        Go
                    </button>

                </form>


            <?php endif; ?>


        <?php else: ?>

            <span class="nav-counter">
                <?= htmlspecialchars($totalCount) ?>
                rekod
            </span>

        <?php endif; ?>


        <?php if ($next_id): ?>

            <a
                href="<?= nav_url($next_id) ?>"
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
            title="Guna anak panah papan kekunci untuk navigasi pantas"
        >

            <kbd>←</kbd>

            <kbd>→</kbd>

        </span>


    </div>

</div>


<!-- =====================================================
     EDIT FORM
===================================================== -->

<?php if (
    $can_edit_archive
    ||
    $is_creator
): ?>

<form
    method="POST"
    enctype="multipart/form-data"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars($csrf_token) ?>"
>

<?php endif; ?>


<!-- =====================================================
     HERO
===================================================== -->

<div class="hero">

    <small>
        Running Number
    </small>

    <h2>
        <?= htmlspecialchars(
            $archive['running_number'] ?? '-'
        ) ?>
    </h2>

</div>


<!-- =====================================================
     GENERAL
===================================================== -->

<div class="card">

    <div class="section-title">
        General
    </div>


    <div class="grid-2">


        <div>

            <div class="label">
                Created by
            </div>

            <div class="value-box">

                <?= htmlspecialchars(
                    $archive['created_by'] ?? '-'
                ) ?>

            </div>

        </div>


        <div>

            <div class="label">
                Date Created
            </div>

            <div class="value-box">

                <?= !empty($archive['created_at'])
                    ?
                    date(
                        'd/m/Y H:i',
                        strtotime(
                            $archive['created_at']
                        )
                    )
                    :
                    '-'
                ?>

            </div>

        </div>


    </div>

</div>


<!-- =====================================================
     LETTER DETAILS
===================================================== -->

<div class="card">


<?php if ($can_edit_archive): ?>


    <div class="section-title">
        Letter Details
    </div>


    <div class="label">
        Letter Date
    </div>


    <input
        type="date"
        name="request_date"
        class="input"
        value="<?= date(
            'Y-m-d',
            strtotime(
                $archive['request_date']
            )
        ) ?>"
        required
    >


    <div class="label">
        Running Number
    </div>


    <input
        type="text"
        name="running_number"
        class="input"
        value="<?= htmlspecialchars(
            $archive['running_number'] ?? ''
        ) ?>"
        required
    >


    <div class="label">
        Title
    </div>


    <textarea
        name="title"
        class="textarea"
        required
    ><?= htmlspecialchars(
        $archive['title']
    ) ?></textarea>


    <div class="grid-2">


        <div>

            <div class="label">
                Agency
            </div>

            <div class="value-box">

                <?= htmlspecialchars(
                    $archive['agency']
                ) ?>

            </div>

        </div>


        <div></div>


    </div>


    <?php if (
        ($archive['action_type'] ?? '')
        ===
        'cancel'
    ): ?>

        <div class="label">
            Cancel Reason
        </div>

        <div class="value-box">

            <?= !empty(
                $archive['cancel_reason']
            )
                ?
                htmlspecialchars(
                    $archive['cancel_reason']
                )
                :
                '-'
            ?>

        </div>

    <?php endif; ?>


    <?php if (
        ($archive['action_type'] ?? '')
        ===
        'acknowledge'
    ): ?>


        <div class="label">
            Document File
        </div>


        <div class="value-box">

            <?php
            renderDocuments($archive);
            ?>

        </div>


        <div
            class="label"
            style="margin-top:15px;"
        >
            Replace Document
        </div>


        <input
            type="file"
            name="new_document[]"
            multiple
            class="input"
        >


        <small style="color:#666;">
            Leave empty if no changes
        </small>


    <?php endif; ?>


<?php else: ?>


    <div class="section-title">
        Letter Details
    </div>


    <div class="label">
        Date Request
    </div>


    <div class="value-box">

        <?= date(
            'd/m/Y',
            strtotime(
                $archive['request_date']
            )
        ) ?>

    </div>


    <div class="label">
        Running Number
    </div>


    <?php if ($is_creator): ?>


        <input
            type="text"
            name="running_number"
            class="input"
            value="<?= htmlspecialchars(
                $archive['running_number'] ?? ''
            ) ?>"
            required
        >


    <?php else: ?>


        <div class="value-box">

            <?= htmlspecialchars(
                $archive['running_number'] ?? '-'
            ) ?>

        </div>


    <?php endif; ?>


    <div class="label">
        Title
    </div>


    <?php if ($is_creator): ?>

        <textarea
            name="title"
            class="textarea"
            required
        ><?= htmlspecialchars(
            $archive['title'] ?? ''
        ) ?></textarea>

    <?php else: ?>

        <div class="value-box">

            <?= htmlspecialchars(
                $archive['title'] ?? '-'
            ) ?>

        </div>

    <?php endif; ?>


    <div class="grid-2">


        <div>

            <div class="label">
                Agency
            </div>

            <div class="value-box">

                <?= htmlspecialchars(
                    $archive['agency']
                ) ?>

            </div>

        </div>


        <div></div>


    </div>


    <?php if (
        ($archive['action_type'] ?? '')
        ===
        'cancel'
    ): ?>


        <div class="label">
            Cancel Reason
        </div>


        <div class="value-box">

            <?= !empty(
                $archive['cancel_reason']
            )
                ?
                htmlspecialchars(
                    $archive['cancel_reason']
                )
                :
                '-'
            ?>

        </div>


    <?php endif; ?>


    <?php if (
        ($archive['action_type'] ?? '')
        ===
        'acknowledge'
    ): ?>


        <div class="label">
            Document File
        </div>


        <div class="value-box">

            <?php
            renderDocuments($archive);
            ?>

        </div>


    <?php endif; ?>


<?php endif; ?>


</div>


<!-- =====================================================
     SAVE
===================================================== -->

<?php if (
    $can_edit_archive
    ||
    $is_creator
): ?>


<div
    style="
        width:100%;
        max-width:900px;
    "
>

    <button
        type="submit"
        name="save_changes"
        value="1"
        class="btn"
    >
        💾 Save Changes
    </button>

</div>


</form>


<?php endif; ?>


<!-- =====================================================
     DELETE
===================================================== -->

<?php if ($can_edit_archive): ?>


<div
    style="
        margin-top:20px;
        width:100%;
        max-width:900px;
    "
>

    <a
        href="delete_letter_archive.php?id=<?= (int)$archive['id'] ?>"
        class="btn btn-danger"
        onclick="return confirm('Delete this archive record?')"
    >
        🗑 Delete Archive
    </a>

</div>


<?php endif; ?>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>


/* =====================================================
   JUMP TO LETTER
===================================================== */

function jumpToSelected(selectEl) {

    const targetId =
        selectEl.value;

    if (!targetId) {
        return;
    }

    window.location.href =
        '?id=' + targetId;
}


/* =====================================================
   KEYBOARD NAVIGATION
===================================================== */

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


        const isTyping =
            [
                'input',
                'textarea',
                'select'
            ].includes(activeTag)
            ||
            (
                document.activeElement
                &&
                document.activeElement.isContentEditable
            );


        if (isTyping) {
            return;
        }


        /* Previous */

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


        /* Next */

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


/* =====================================================
   HOVER PREFETCH
===================================================== */

(function () {


    const prefetched =
        new Set();


    const HOVER_DELAY =
        150;


    function schedulePrefetch(
        link
    ) {

        if (
            !link
            ||
            link.tagName !== 'A'
        ) {

            return;
        }


        const url =
            link.getAttribute(
                'href'
            );


        if (
            !url
            ||
            prefetched.has(url)
        ) {

            return;
        }


        let timer =
            setTimeout(
                function() {

                    prefetched.add(
                        url
                    );


                    fetch(
                        url,
                        {
                            credentials:
                                'same-origin'
                        }
                    )
                    .catch(
                        function() {

                            prefetched.delete(
                                url
                            );

                        }
                    );

                },
                HOVER_DELAY
            );


        link.addEventListener(
            'mouseleave',
            function onLeave() {

                clearTimeout(timer);

                link.removeEventListener(
                    'mouseleave',
                    onLeave
                );

            },
            {
                once:true
            }
        );
    }


    [
        'prevBtn',
        'nextBtn'
    ]
    .forEach(
        function(id) {

            const el =
                document.getElementById(
                    id
                );


            if (
                el
                &&
                el.tagName === 'A'
            ) {

                el.addEventListener(
                    'mouseenter',
                    function() {

                        schedulePrefetch(
                            el
                        );

                    }
                );


                el.addEventListener(
                    'touchstart',
                    function() {

                        schedulePrefetch(
                            el
                        );

                    },
                    {
                        passive:true
                    }
                );
            }

        }
    );


})();

</script>


</body>

</html>
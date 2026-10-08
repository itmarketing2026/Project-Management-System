<?php
session_start();
require_once 'include/msqli_connect.php';
/* =========================================================
   LOGIN CHECK
========================================================= */
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    header("Location: login.php");
    exit;
}
/* =========================================================
   GET ARCHIVE ID
========================================================= */
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid Archive ID.");
}
/* =========================================================
   GET ARCHIVE DATA
========================================================= */
$stmt = $db->prepare("
    SELECT
        a.*,
        c.region
    FROM letteroutarchive_cerun a
    JOIN cerun c
        ON a.cerun_id = c.id
    WHERE a.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();
if (!$data) {
    die("Archive record not found.");
}
/* =========================================================
   UPDATE DATA
   ADMIN / QA-QC ONLY
========================================================= */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_changes'])
) {
    $role = strtolower(
        trim($_SESSION['account_type'] ?? '')
    );
    if (
        in_array(
            $role,
            [
                'client admin',
                'admin (qa/qc)',
				'standard user'
            ]
        )
    ) {
        $request_date = $_POST['request_date'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $update = $db->prepare("
            UPDATE letteroutarchive_cerun
            SET
                request_date = ?,
                title = ?
            WHERE id = ?
        ");
        $update->bind_param(
            "ssi",
            $request_date,
            $title,
            $id
        );
        $update->execute();
        $update->close();
        header(
            "Location: viewletteroutarchive_cerun.php?id=" . $id
        );
        exit;
    }
}
/* =========================================================
   DETERMINE FOLDER YEAR / TYPE
========================================================= */
$folderYear = date('Y');
$folderType = '4';
if (!empty($data['running_number'])) {
    if (
        preg_match(
            '/(\d{4})/',
            $data['running_number'],
            $matches
        )
    ) {
        $folderYear = $matches[0];
    }
    $parts = explode(
        '/',
        $data['running_number']
    );
    $prefix = $parts[0];
    if (strpos($prefix, '6') !== false) {
        $folderType = '6';
    }
}
/* =========================================================
   FILE PATH HELPER
========================================================= */
/*
 * DB boleh simpan:
 *
 * 1.
 * https://mggroup.my/projectmanagement/uploads/file.pdf
 *
 * 2.
 * /projectmanagement/uploads/file.pdf
 *
 * 3.
 * uploads/file.pdf
 *
 * 4.
 * projectmanagement/uploads/file.pdf
 *
 * 5.
 * filename.pdf
 *
 * Function ini akan normalize kepada:
 *
 * uploads/...
 *
 * supaya download_file.php boleh cari secara direct.
 */
function get_download_url($fileUrl)
{
    $filePath = trim((string)$fileUrl);
    if ($filePath === '') {
        return '';
    }
    /* =====================================================
       IF FULL URL
    ===================================================== */
    if (
        filter_var(
            $filePath,
            FILTER_VALIDATE_URL
        )
    ) {
        $urlPath = parse_url(
            $filePath,
            PHP_URL_PATH
        );
        if (!empty($urlPath)) {
            $filePath = $urlPath;
        }
    }
    /* =====================================================
       NORMALIZE SLASH
    ===================================================== */
    $filePath = str_replace(
        '\\',
        '/',
        $filePath
    );
    /* =====================================================
       REMOVE LEADING SLASH
    ===================================================== */
    $filePath = ltrim(
        $filePath,
        '/'
    );
    /* =====================================================
       REMOVE PROJECTMANAGEMENT PREFIX
    ===================================================== */
    $prefix = 'projectmanagement/';
    if (
        stripos(
            $filePath,
            $prefix
        ) === 0
    ) {
        $filePath = substr(
            $filePath,
            strlen($prefix)
        );
    }
    /* =====================================================
       IF URL CONTAINS DOMAIN + PROJECTMANAGEMENT
       BUT STILL HAS EXTRA PATH
    ===================================================== */
    $pos = stripos(
        $filePath,
        'projectmanagement/'
    );
    if (
        $pos !== false &&
        $pos === 0
    ) {
        $filePath = substr(
            $filePath,
            strlen('projectmanagement/')
        );
    }
    /* =====================================================
       REMOVE DUPLICATE SLASH
    ===================================================== */
    $filePath = preg_replace(
        '#/+#',
        '/',
        $filePath
    );
    /* =====================================================
       SECURITY
       DO NOT ALLOW ../
    ===================================================== */
    if (
        strpos($filePath, '..') !== false
    ) {
        return '';
    }
    /* =====================================================
       BUILD DOWNLOAD URL
    ===================================================== */
    return 'download_file.php?file=' .
        urlencode($filePath);
}
/* =========================================================
   GET USER ROLE
========================================================= */
$role = strtolower(
    trim($_SESSION['account_type'] ?? '')
);
$isAdmin = in_array(
    $role,
    [
        'client admin',
        'admin (qa/qc)'
    ]
);
/* =========================================================
   WHO CAN DELETE
   ADMIN / QA-QC, OR THE USER WHO CREATED THIS RECORD
   (created_by stores the creator's full_name)
========================================================= */
$canDelete = $isAdmin || (
    !empty($_SESSION['full_name']) &&
    strcasecmp(
        trim((string)$data['created_by']),
        trim((string)$_SESSION['full_name'])
    ) === 0
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
    <title>Project Management System</title>
    <style>
        :root {
            --accent: #007bff;
            --green: #28a745;
            --red: #dc3545;
            --orange: #fd7e14;
            --max: 900px;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family:
                'Segoe UI',
                Tahoma,
                Geneva,
                Verdana,
                sans-serif;
            background: #f4f7fa;
            padding: 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0;
        }
        body > * {
            width: 100%;
            max-width: var(--max);
        }
        .back-btn {
            display: block;
            margin-bottom: 16px;
            background: var(--accent);
            color: #fff;
            padding: 12px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            transition: 0.3s;
            text-align: left;
            font-size: 0.9rem;
        }
        .back-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .hero {
            background:
                linear-gradient(
                    135deg,
                    #1e293b,
                    #334155
                );
            color: #fff;
            border-radius: 18px;
            padding: 30px;
            margin-bottom: 26px;
            text-align: center;
        }
        .hero h2 {
            margin: 8px 0 0;
            font-size: 28px;
            letter-spacing: 1px;
            word-break: break-all;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 28px;
            box-shadow:
                0 6px 18px
                rgba(0, 0, 0, .06);
            border: 1px solid #eef2f6;
        }
        .section-title {
            font-weight: 800;
            color: var(--accent);
            border-bottom:
                2px solid #eef2f6;
            padding-bottom: 10px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .label {
            font-weight: 700;
            margin-bottom: 6px;
            color: #64748b;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        .value-box {
            background: #f8fafc;
            border:
                1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 14px;
            color: #1e293b;
        }
        .grid-2 {
            display: grid;
            grid-template-columns:
                1fr 1fr;
            gap: 18px;
        }
        @media(max-width:640px) {
            .grid-2 {
                grid-template-columns:
                    1fr;
            }
        }
        .file-link {
            display: block;
            text-decoration: none;
            font-weight: bold;
            color: var(--accent);
            margin-bottom: 8px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #f8fafc;
            border:
                1px solid #e2e8f0;
        }
        .file-link:hover {
            background: #eef6ff;
        }
        .status-tag {
            background: var(--green);
            color: #fff;
            padding: 4px 12px;
            border-radius: 60px;
            font-size: 0.7rem;
            font-weight: 800;
            vertical-align: middle;
            margin-left: 10px;
        }
    </style>
</head>
<body>
<a
    href="javascript:history.back()"
    class="back-btn"
>
    ← Back to Folder
</a>
<!-- =====================================================
     HERO
===================================================== -->
<div class="hero">
    <small
        style="
            opacity:0.7;
            text-transform:uppercase;
            font-weight:bold;
            letter-spacing:1.5px;
        "
    >
        Archive Running Number
    </small>
    <h2>
        <?= htmlspecialchars(
            $data['running_number']
        ) ?>
    </h2>
</div>
<!-- =====================================================
     RECORD IDENTIFICATION
===================================================== -->
<div class="card">
    <div class="section-title">
        Record Identification
        <span class="status-tag">
            ARCHIVED
        </span>
    </div>
    <div class="grid-2">
        <div>
            <div class="label">
                Region
            </div>
            <div
                class="value-box"
                style="
                    font-weight:bold;
                    color:var(--accent);
                "
            >
                <?= htmlspecialchars(
                    $data['region']
                ) ?>
            </div>
        </div>
    </div>
</div>
<!-- =====================================================
     DOCUMENT CARD
===================================================== -->
<div class="card">
<?php if ($isAdmin): ?>
<form method="POST">
    <div class="section-title">
        Document Information
    </div>
    <!-- TITLE -->
    <div class="label">
        Title
    </div>
    <textarea
        name="title"
        required
        style="
            width:100%;
            min-height:120px;
            padding:14px;
            border:1px solid #e2e8f0;
            border-radius:10px;
            font-size:16px;
            resize:vertical;
            font-family:inherit;
            margin-bottom:18px;
        "
    ><?= htmlspecialchars(
        $data['title']
    ) ?></textarea>
    <div class="grid-2">
        <!-- AGENCY -->
        <div>
            <div class="label">
                Agency
            </div>
            <div class="value-box">
                <?= htmlspecialchars(
                    $data['agency']
                ) ?>
            </div>
        </div>
        <!-- LETTER DATE -->
        <div>
            <div class="label">
                Letter Date
            </div>
            <input
                type="date"
                name="request_date"
                value="<?= date(
                    'Y-m-d',
                    strtotime(
                        $data['request_date']
                    )
                ) ?>"
                style="
                    width:100%;
                    padding:12px;
                    border:1px solid #e2e8f0;
                    border-radius:10px;
                    background:#fff;
                "
                required
            >
        </div>
        <!-- ARCHIVED BY -->
        <div>
            <div class="label">
                Archived By
            </div>
            <div class="value-box">
                <?= htmlspecialchars(
                    $data['created_by']
                ) ?>
            </div>
        </div>
        <!-- ARCHIVED ON -->
        <div>
            <div class="label">
                Archived On
            </div>
            <div class="value-box">
                <?= date(
                    'd/m/Y | h:i A',
                    strtotime(
                        $data['created_at']
                    )
                ) ?>
            </div>
        </div>
    </div>
    <!-- =================================================
         ATTACHED DOCUMENT
    ================================================= -->
    <div
        class="label"
        style="margin-top:20px;"
    >
        Attached Document
    </div>
    <div class="value-box">
        <?php
        $files = [];
        if (
            !empty(
                $data['archive_file']
            )
        ) {
            $decoded =
                json_decode(
                    $data['archive_file'],
                    true
                );
            if (
                is_array($decoded)
            ) {
                $files = $decoded;
            } else {
                $files = [
                    $data['archive_file']
                ];
            }
        }
        ?>
        <?php if (!empty($files)): ?>
            <?php foreach (
                $files as $index => $fileUrl
            ): ?>
                <?php
                $downloadUrl =
                    get_download_url(
                        $fileUrl
                    );
                $filePath =
                    trim(
                        (string)$fileUrl
                    );
                $displayName =
                    basename(
                        parse_url(
                            $filePath,
                            PHP_URL_PATH
                        ) ?? $filePath
                    );
                ?>
                <?php if ($downloadUrl !== ''): ?>
                    <a
                        href="<?= htmlspecialchars(
                            $downloadUrl
                        ) ?>"
                        target="_blank"
                        class="file-link"
                    >
                        📄
                        <?= htmlspecialchars(
                            $displayName
                        ) ?>
                        <?php if (
                            count($files) > 1
                        ): ?>
                            <span
                                style="
                                    color:#64748b;
                                    font-weight:normal;
                                "
                            >
                                (
                                <?= $index + 1 ?>
                                )
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <span
                style="
                    color:#94a3b8;
                    font-style:italic;
                "
            >
                No document attached
            </span>
        <?php endif; ?>
    </div>
    <!-- SAVE -->
    <button
        type="submit"
        name="save_changes"
        value="1"
        style="
            margin-top:20px;
            background:#007bff;
            color:white;
            border:none;
            padding:14px 20px;
            border-radius:10px;
            font-weight:bold;
            cursor:pointer;
            width:100%;
            font-size:16px;
        "
    >
        💾 Save Changes
    </button>
</form>
<?php else: ?>
<!-- =====================================================
     NORMAL USER VIEW
===================================================== -->
<div class="section-title">
    Document Information
</div>
<!-- TITLE -->
<div class="label">
    Title
</div>
<div
    class="value-box"
    style="font-weight:600;"
>
    <?= htmlspecialchars(
        $data['title']
    ) ?>
</div>
<div class="grid-2">
    <!-- AGENCY -->
    <div>
        <div class="label">
            Agency
        </div>
        <div class="value-box">
            <?= htmlspecialchars(
                $data['agency']
            ) ?>
        </div>
    </div>
    <!-- LETTER DATE -->
    <div>
        <div class="label">
            Letter Date
        </div>
        <div class="value-box">
            <?= date(
                'd/m/Y',
                strtotime(
                    $data['request_date']
                )
            ) ?>
        </div>
    </div>
    <!-- ARCHIVED BY -->
    <div>
        <div class="label">
            Archived By
        </div>
        <div class="value-box">
            <?= htmlspecialchars(
                $data['created_by']
            ) ?>
        </div>
    </div>
    <!-- ARCHIVED ON -->
    <div>
        <div class="label">
            Archived On
        </div>
        <div class="value-box">
            <?= date(
                'd/m/Y | h:i A',
                strtotime(
                    $data['created_at']
                )
            ) ?>
        </div>
    </div>
</div>
<!-- =================================================
     ATTACHED DOCUMENT
================================================= -->
<div class="label">
    Attached Document
</div>
<div class="value-box">
    <?php
    $files = [];
    if (
        !empty(
            $data['archive_file']
        )
    ) {
        $decoded =
            json_decode(
                $data['archive_file'],
                true
            );
        if (
            is_array($decoded)
        ) {
            $files = $decoded;
        } else {
            $files = [
                $data['archive_file']
            ];
        }
    }
    ?>
    <?php if (!empty($files)): ?>
        <?php foreach (
            $files as $index => $fileUrl
        ): ?>
            <?php
            $downloadUrl =
                get_download_url(
                    $fileUrl
                );
            $filePath =
                trim(
                    (string)$fileUrl
                );
            $parsedPath =
                parse_url(
                    $filePath,
                    PHP_URL_PATH
                );
            $displayName =
                basename(
                    $parsedPath
                    ?: $filePath
                );
            ?>
            <?php if ($downloadUrl !== ''): ?>
                <a
                    href="<?= htmlspecialchars(
                        $downloadUrl
                    ) ?>"
                    target="_blank"
                    class="file-link"
                >
                    📄
                    <?= htmlspecialchars(
                        $displayName
                    ) ?>
                    <?php if (
                        count($files) > 1
                    ): ?>
                        <span
                            style="
                                color:#64748b;
                                font-weight:normal;
                            "
                        >
                            (
                            <?= $index + 1 ?>
                            )
                        </span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php else: ?>
        <span
            style="
                color:#94a3b8;
                font-style:italic;
            "
        >
            No document attached
        </span>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>
<!-- =====================================================
     DANGER ZONE
     ADMIN / QA-QC, OR THE RECORD'S CREATOR (BY full_name)
===================================================== -->
<?php if ($canDelete): ?>
<div class="card">
    <div
        class="section-title"
        style="color:#dc3545;"
    >
        Danger Zone
    </div>
    <a
        href="delete_archive_cerun.php?id=<?= (int)$data['id'] ?>"
        onclick="
            return confirm(
                'Delete this archive and all attached files?'
            )
        "
        style="
            display:inline-block;
            background:#dc3545;
            color:white;
            padding:12px 20px;
            border-radius:10px;
            text-decoration:none;
            font-weight:700;
        "
    >
        🗑 Delete Archive
    </a>
</div>
<?php endif; ?>
</body>
</html>
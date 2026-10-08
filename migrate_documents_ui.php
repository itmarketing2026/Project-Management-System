<?php
/**
 * Standalone admin tool: Migrate project documents into per-project ARCHIEVE folders.
 * Destination is always: uploads/project_{id}/archive/<year>/<filename> (year from request_date)
 */

session_start();

// ====================== CONFIG - EDIT THESE ======================
require_once 'include/msqli_connect.php';

$TABLE           = 'letter_out_archive';
$PROJECTS_TABLE  = 'letter_out_archive';
$PROJECTS_ID_COL = 'project_id';

$BASE_UPLOAD_PATH = 'C:/inetpub/vhosts/mggroup.my/httpdocs/projectmanagement/uploads';
$BASE_URL_PREFIX  = 'https://mggroup.my/projectmanagement/uploads';

$PATH_COLUMNS = ['file_path'];

// Fixed subfolder name inside each project folder where files should end up
$ARCHIVE_SUBFOLDER = 'archive';

$ADMIN_PASSWORD = '12345';
// ====================== END CONFIG ======================


// ---------- Login gate ----------
if (isset($_POST['login_password'])) {
    if ($_POST['login_password'] === $ADMIN_PASSWORD) {
        $_SESSION['migrate_tool_auth'] = true;
    } else {
        $loginError = 'Password salah.';
    }
}
if (isset($_GET['logout'])) unset($_SESSION['migrate_tool_auth']);
$isAuthed = !empty($_SESSION['migrate_tool_auth']);

if (!$isAuthed) { ?>
<!DOCTYPE html>
<html lang="ms">
<head><meta charset="UTF-8"><title>Login - Migration Tool</title>
<style>
body{font-family:Arial;max-width:400px;margin:80px auto;}
input{width:100%;padding:8px;margin:8px 0;box-sizing:border-box;}
button{padding:8px 16px;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer;}
.err{color:red;}
</style>
</head>
<body>
    <h2>Document Migration Tool</h2>
    <?php if (!empty($loginError)) echo "<p class='err'>" . htmlspecialchars($loginError) . "</p>"; ?>
    <form method="post">
        <label>Password Admin:</label>
        <input type="password" name="login_password" required autofocus>
        <button type="submit">Login</button>
    </form>
</body>
</html>
<?php exit; }


// ---------- Helper: resolve relative subpath from a URL/path stored in DB ----------
function resolveRelSubPath($url) {
    if (preg_match('#^https?://#i', $url)) {
        // Full URL — extract part after /uploads/
        $urlPath    = parse_url($url, PHP_URL_PATH);
        $uploadsPos = strpos($urlPath, '/uploads/');
        return ($uploadsPos !== false)
            ? substr($urlPath, $uploadsPos + strlen('/uploads/'))
            : basename($url);
    }
    // Relative path — strip leading slash, then strip leading "uploads/" if present
    $rel = ltrim($url, '/');
    if (stripos($rel, 'uploads/') === 0) {
        $rel = substr($rel, strlen('uploads/'));
    }
    return $rel;
}

// ---------- Destination is project_{id}/{ARCHIVE_SUBFOLDER}/{year}/<filename> ----------
function getRequestYear($requestDate) {
    $requestDate = trim((string)$requestDate);
    if ($requestDate === '') return null;

    try {
        $dt = new DateTime($requestDate);
        $year = (int)$dt->format('Y');
        return ($year >= 1900 && $year <= 2100) ? $year : null;
    } catch (Exception $e) {
        return null;
    }
}

function resolveDestSubPath($filename, $ARCHIVE_SUBFOLDER, $requestDate) {
    $year = getRequestYear($requestDate);
    if ($year === null) return null;
    return $ARCHIVE_SUBFOLDER . '/' . $year . '/' . $filename;
}

// ---------- Check if a URL is already inside the project's archive folder ----------
function isAlreadyMoved($url, $projectId, $ARCHIVE_SUBFOLDER) {
    $projectFolderName = "project_{$projectId}";
    return strpos($url, "/uploads/{$projectFolderName}/{$ARCHIVE_SUBFOLDER}/") !== false;
}

function isAlreadyInTargetYear($url, $projectId, $ARCHIVE_SUBFOLDER, $requestYear) {
    if ($requestYear === null) return false;
    $projectFolderName = "project_{$projectId}";
    return strpos($url, "/uploads/{$projectFolderName}/{$ARCHIVE_SUBFOLDER}/{$requestYear}/") !== false;
}


// ---------- Get all file entries for a project ----------
function getProjectFiles($db, $projectId, $BASE_UPLOAD_PATH, $PATH_COLUMNS, $TABLE, $ARCHIVE_SUBFOLDER) {
    $colsList = implode(',', $PATH_COLUMNS);
    $stmt = $db->prepare("SELECT id, project_id, request_date, running_number, title, $colsList FROM `$TABLE` WHERE project_id = ?");
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $items = [];
    foreach ($rows as $row) {
        $hasFile = false;
        foreach ($PATH_COLUMNS as $col) {
            $raw = $row[$col] ?? '';
            if (empty($raw)) continue;
            $urls = json_decode($raw, true);
            if (!is_array($urls) || empty($urls)) continue;

            $hasFile = true;
            foreach ($urls as $url) {
                $filename     = basename($url);
                $relSubPath   = resolveRelSubPath($url);
                $requestYear = getRequestYear($row['request_date'] ?? '');
                // Legacy archive/<filename> is still eligible for year-based migration.
                $alreadyMoved = isAlreadyInTargetYear($url, $projectId, $ARCHIVE_SUBFOLDER, $requestYear);
                $oldFilePath  = $BASE_UPLOAD_PATH . '/' . $relSubPath;
                $fileExists   = file_exists($oldFilePath);

                $missingReason = '';
                $folderContents = [];
                if (!$alreadyMoved && !$fileExists) {
                    $parentDir = $BASE_UPLOAD_PATH . '/' . dirname($relSubPath);
                    if (!is_dir($BASE_UPLOAD_PATH)) {
                        $missingReason = 'BASE_UPLOAD_PATH tidak wujud di server';
                    } elseif (!is_dir($parentDir)) {
                        $missingReason = 'Subfolder tidak wujud: ' . dirname($relSubPath);
                    } else {
                        $missingReason = 'Subfolder ada tapi fail tidak jumpa';
                        $allFiles = scandir($parentDir);
                        foreach ($allFiles as $f2) {
                            if ($f2 === '.' || $f2 === '..') continue;
                            $folderContents[] = $f2;
                        }
                    }
                }

                $items[] = [
                    'record_id'       => (int)$row['id'],
                    'col'             => $col,
                    'url'             => $url,
                    'filename'        => $filename,
                    'rel_sub_path'    => $relSubPath,
                    'running_number'  => $row['running_number'] ?? '',
                    'request_date'    => $row['request_date'] ?? '',
                    'request_year'    => getRequestYear($row['request_date'] ?? ''),
                    'title'           => $row['title'] ?? '',
                    'already_moved'   => $alreadyMoved,
                    'file_exists'     => $alreadyMoved ? true : $fileExists,
                    'missing_reason'  => $missingReason,
                    'folder_contents' => $folderContents,
                    'no_file'         => false,
                ];
            }
        }

        // Rekod tanpa file_path (NULL/kosong) — papar jugak dengan status Tiada Fail
        if (!$hasFile) {
            $items[] = [
                'record_id'       => (int)$row['id'],
                'col'             => '',
                'url'             => '',
                'filename'        => '',
                'rel_sub_path'    => '',
                'running_number'  => $row['running_number'] ?? '',
                'request_date'    => $row['request_date'] ?? '',
                'request_year'    => getRequestYear($row['request_date'] ?? ''),
                'title'           => $row['title'] ?? '',
                'already_moved'   => false,
                'file_exists'     => false,
                'missing_reason'  => '',
                'folder_contents' => [],
                'no_file'         => true,
            ];
        }
    }
    return $items;
}


// ---------- Get available request years for a project ----------
function getProjectYears($db, $projectId, $TABLE) {
    $stmt = $db->prepare(
        "SELECT DISTINCT YEAR(request_date) AS year
         FROM `$TABLE`
         WHERE project_id = ? AND request_date IS NOT NULL AND request_date != ''
         ORDER BY year DESC"
    );
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return array_values(array_filter(array_map(fn($r) => (int)$r['year'], $rows), fn($y) => $y >= 1900 && $y <= 2100));
}

// ---------- Normalize running number for reliable duplicate detection ----------
function normalizeRunningNumber($value) {
    $value = trim((string)$value);
    // Normalize whitespace around/between values so accidental spaces do not
    // make the same running number look like two different values.
    $value = preg_replace('/\s+/u', ' ', $value);
    return $value;
}

// ---------- NEW: Detect duplicate running_number within a single project ----------
function findDuplicateRunningNumbers($db, $projectId, $TABLE) {
    $stmt = $db->prepare("
        SELECT
            TRIM(running_number) AS running_number,
            COUNT(*) AS total,
            GROUP_CONCAT(id ORDER BY id SEPARATOR ', ') AS ids
        FROM `$TABLE`
        WHERE project_id = ?
          AND running_number IS NOT NULL
          AND TRIM(running_number) <> ''
        GROUP BY TRIM(running_number)
        HAVING COUNT(*) > 1
        ORDER BY TRIM(running_number) ASC
    ");
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ---------- NEW: Detect duplicate running_number across ALL projects (or filtered) ----------
function findAllDuplicateRunningNumbers($db, $TABLE, $filterProjectId = null) {
    if ($filterProjectId !== null) {
        $stmt = $db->prepare("
            SELECT
                project_id,
                TRIM(running_number) AS running_number,
                COUNT(*) AS total,
                GROUP_CONCAT(id ORDER BY id SEPARATOR ', ') AS ids
            FROM `$TABLE`
            WHERE running_number IS NOT NULL
              AND TRIM(running_number) <> ''
              AND project_id = ?
            GROUP BY project_id, TRIM(running_number)
            HAVING COUNT(*) > 1
            ORDER BY project_id ASC, TRIM(running_number) ASC
        ");
        $stmt->bind_param('i', $filterProjectId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    $sql = "
        SELECT
            project_id,
            TRIM(running_number) AS running_number,
            COUNT(*) AS total,
            GROUP_CONCAT(id ORDER BY id SEPARATOR ', ') AS ids
        FROM `$TABLE`
        WHERE running_number IS NOT NULL
          AND TRIM(running_number) <> ''
        GROUP BY project_id, TRIM(running_number)
        HAVING COUNT(*) > 1
        ORDER BY project_id ASC, TRIM(running_number) ASC
    ";
    $res = $db->query($sql);
    return $res->fetch_all(MYSQLI_ASSOC);
}


// ---------- Core: migrate selected files ----------
function migrateSelectedFiles($db, $projectId, $selectedKeys, $isLive, $BASE_UPLOAD_PATH, $BASE_URL_PREFIX, $PATH_COLUMNS, $TABLE, $ARCHIVE_SUBFOLDER, $targetYear = null) {
    $keySet            = array_flip($selectedKeys);
    $colsList          = implode(',', $PATH_COLUMNS);
    $projectFolderName = "project_{$projectId}";
    $projectFolderPath = $BASE_UPLOAD_PATH . '/' . $projectFolderName;

    $stmt = $db->prepare("SELECT id, project_id, request_date, $colsList FROM `$TABLE` WHERE project_id = ?");
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $log     = [];
    $summary = ['moved' => 0, 'errors' => 0, 'skipped' => 0];

    foreach ($rows as $row) {
        $rowYear = getRequestYear($row['request_date'] ?? '');
        if ($targetYear !== null && $rowYear !== $targetYear) {
            continue;
        }

        $id      = $row['id'];
        $updates = [];

        foreach ($PATH_COLUMNS as $col) {
            $rawValue = $row[$col] ?? '';
            if (empty($rawValue)) continue;
            $urls = json_decode($rawValue, true);
            if (!is_array($urls)) continue;

            $newUrls = [];
            foreach ($urls as $url) {
                $filename   = basename($url);
                $relSubPath = resolveRelSubPath($url);
                $key        = "{$id}:{$col}:{$filename}";

                if (!isset($keySet[$key])) {
                    $newUrls[] = $url;
                    $summary['skipped']++;
                    continue;
                }

                $requestDate = $row['request_date'] ?? '';
                $requestYear = getRequestYear($requestDate);
                if ($requestYear === null) {
                    $log[] = ['type' => 'error', 'text' => "id={$id}: request_date kosong atau tidak sah. Fail tidak dimigrate: {$filename}"];
                    $newUrls[] = $url;
                    $summary['errors']++;
                    continue;
                }

                // Skip only if already in the correct year folder.
                if (isAlreadyInTargetYear($url, $projectId, $ARCHIVE_SUBFOLDER, $requestYear)) {
                    $log[] = ['type' => 'skip', 'text' => "id={$id} ({$col}): {$filename} sudah berada di {$projectFolderName}/{$ARCHIVE_SUBFOLDER}/{$requestYear}/."];
                    $newUrls[] = $url;
                    $summary['skipped']++;
                    continue;
                }

                // Destination: project_{id}/archive/{year}/<filename>
                $destSubPath     = resolveDestSubPath($filename, $ARCHIVE_SUBFOLDER, $requestDate);
                $oldFilePath     = $BASE_UPLOAD_PATH . '/' . $relSubPath;
                $newFilePath     = $projectFolderPath . '/' . $destSubPath;
                $newUrl          = $BASE_URL_PREFIX . '/' . $projectFolderName . '/' . $destSubPath;
                $newSubfolderDir = dirname($newFilePath);

                if (!file_exists($oldFilePath)) {
                    $log[]     = ['type' => 'error', 'text' => "id={$id}: fail tidak jumpa: {$oldFilePath}"];
                    $newUrls[] = $url;
                    $summary['errors']++;
                    continue;
                }

                $log[] = ['type' => 'move', 'text' => "id={$id} ({$col}): {$relSubPath} → {$projectFolderName}/{$destSubPath}"];

                if ($isLive) {
                    if (file_exists($newFilePath)) {
                        $log[] = ['type' => 'error', 'text' => "id={$id}: fail sasaran sudah wujud, tidak overwrite: {$newFilePath}"];
                        $newUrls[] = $url;
                        $summary['errors']++;
                        continue;
                    }
                    if (!is_dir($newSubfolderDir)) {
                        mkdir($newSubfolderDir, 0755, true);
                        $log[] = ['type' => 'mkdir', 'text' => "Folder dicipta: " . str_replace($BASE_UPLOAD_PATH . '/', '', $newSubfolderDir)];
                    }
                    if (!rename($oldFilePath, $newFilePath)) {
                        $log[]     = ['type' => 'error', 'text' => "Gagal move fail: {$oldFilePath}"];
                        $newUrls[] = $url;
                        $summary['errors']++;
                        continue;
                    }
                }

                $newUrls[] = $newUrl;
                $summary['moved']++;
            }

            $newJson = json_encode($newUrls, JSON_UNESCAPED_SLASHES);
            if ($newJson !== $rawValue) $updates[$col] = $newJson;
        }

        if (!empty($updates)) {
            if ($isLive) {
                $setParts = $types = '';
                $values   = [];
                foreach ($updates as $col => $val) {
                    $setParts .= ($setParts ? ', ' : '') . "`$col` = ?";
                    $types    .= 's';
                    $values[]  = $val;
                }
                $types   .= 'i';
                $values[] = $id;
                $upd = $db->prepare("UPDATE `$TABLE` SET {$setParts} WHERE id = ?");
                $upd->bind_param($types, ...$values);
                $upd->execute();
                $log[] = ['type' => 'db', 'text' => "id={$id}: DB dikemaskini."];
            } else {
                foreach ($updates as $col => $val)
                    $log[] = ['type' => 'db-preview', 'text' => "id={$id} {$col} akan jadi: {$val}"];
            }
        }
    }

    return ['log' => $log, 'summary' => $summary, 'total_records' => count($rows)];
}


// ---------- Delete selected files + DB records ----------
function deleteSelectedFiles($db, $projectId, $selectedKeys, $BASE_UPLOAD_PATH, $PATH_COLUMNS, $TABLE, $ARCHIVE_SUBFOLDER) {
    $keySet = array_flip((array)$selectedKeys);
    $colsList = implode(',', $PATH_COLUMNS);

    $stmt = $db->prepare("SELECT id, project_id, $colsList FROM `$TABLE` WHERE project_id = ?");
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $log = [];
    $summary = ['deleted_files' => 0, 'deleted_records' => 0, 'updated_records' => 0, 'errors' => 0, 'skipped' => 0];

    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $updates = [];
        $deleteRecord = true;
        $recordHadSelectedFile = false;

        foreach ($PATH_COLUMNS as $col) {
            $rawValue = $row[$col] ?? '';
            if (empty($rawValue)) continue;

            $urls = json_decode($rawValue, true);
            if (!is_array($urls)) {
                $deleteRecord = false;
                continue;
            }

            $newUrls = [];

            foreach ($urls as $url) {
                $filename = basename($url);
                $key = "{$id}:{$col}:{$filename}";

                if (!isset($keySet[$key])) {
                    $newUrls[] = $url;
                    $deleteRecord = false;
                    continue;
                }

                $recordHadSelectedFile = true;

                // Resolve actual physical file location from the URL/path in DB.
                $relSubPath = resolveRelSubPath($url);
                $filePath = $BASE_UPLOAD_PATH . '/' . $relSubPath;

                // For migrated files, resolve directly from the DB URL/path.
                // This supports both the new archive/{year}/ structure and older archive/ files.
                if (isAlreadyMoved($url, $projectId, $ARCHIVE_SUBFOLDER)) {
                    $filePath = $BASE_UPLOAD_PATH . '/' . $relSubPath;
                }

                if (file_exists($filePath)) {
                    if (@unlink($filePath)) {
                        $log[] = ['type' => 'delete-file', 'text' => "id={$id} ({$col}): fail dipadam: {$filePath}"];
                        $summary['deleted_files']++;
                    } else {
                        $log[] = ['type' => 'error', 'text' => "id={$id}: gagal delete fail: {$filePath}"];
                        $summary['errors']++;
                        // Keep the URL in DB if physical delete failed.
                        $newUrls[] = $url;
                        $deleteRecord = false;
                    }
                } else {
                    // File is already missing. Remove its DB reference anyway.
                    $log[] = ['type' => 'delete-missing', 'text' => "id={$id} ({$col}): fail tidak wujud, reference DB dibuang: {$filename}"];
                }
            }

            $newJson = json_encode(array_values($newUrls), JSON_UNESCAPED_SLASHES);
            if ($newJson !== $rawValue) {
                $updates[$col] = $newJson;
            }

            if (!empty($newUrls)) {
                $deleteRecord = false;
            }
        }

        if (!$recordHadSelectedFile) {
            continue;
        }

        if ($deleteRecord) {
            $del = $db->prepare("DELETE FROM `$TABLE` WHERE id = ? AND project_id = ?");
            $del->bind_param('ii', $id, $projectId);
            if ($del->execute()) {
                $log[] = ['type' => 'delete-db', 'text' => "id={$id}: rekod DB dipadam kerana tiada fail/reference yang tinggal."];
                $summary['deleted_records']++;
            } else {
                $log[] = ['type' => 'error', 'text' => "id={$id}: gagal delete rekod DB: " . $del->error];
                $summary['errors']++;
            }
        } elseif (!empty($updates)) {
            $setParts = $types = '';
            $values = [];
            foreach ($updates as $col => $val) {
                $setParts .= ($setParts ? ', ' : '') . "`$col` = ?";
                $types .= 's';
                $values[] = $val;
            }
            $types .= 'i';
            $values[] = $id;

            $upd = $db->prepare("UPDATE `$TABLE` SET {$setParts} WHERE id = ? AND project_id = ?");
            // Rebuild bind values with project_id at the end.
            $types .= 'i';
            $values[] = $projectId;
            $upd->bind_param($types, ...$values);

            if ($upd->execute()) {
                $log[] = ['type' => 'update-db', 'text' => "id={$id}: DB dikemaskini, hanya fail yang dipilih dibuang."];
                $summary['updated_records']++;
            } else {
                $log[] = ['type' => 'error', 'text' => "id={$id}: gagal update DB: " . $upd->error];
                $summary['errors']++;
            }
        }
    }

    return ['log' => $log, 'summary' => $summary, 'total_records' => count($rows)];
}

// ---------- Audit: scan records, find missing files (optional filter by project) ----------
function runAudit($db, $BASE_UPLOAD_PATH, $PATH_COLUMNS, $TABLE, $ARCHIVE_SUBFOLDER, $filterProjectId = null) {
    $colsList = implode(',', $PATH_COLUMNS);
    if ($filterProjectId !== null) {
        $stmt = $db->prepare("SELECT id, project_id, request_date, running_number, title, $colsList FROM `$TABLE` WHERE project_id = ? ORDER BY id ASC");
        $stmt->bind_param('i', $filterProjectId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $res  = $db->query("SELECT id, project_id, request_date, running_number, title, $colsList FROM `$TABLE` ORDER BY project_id ASC, id ASC");
        $rows = $res->fetch_all(MYSQLI_ASSOC);
    }

    $missing = [];
    $totalFiles = 0;
    $totalOk = 0;

    foreach ($rows as $row) {
        $projectId = $row['project_id'];
        foreach ($PATH_COLUMNS as $col) {
            $raw = $row[$col] ?? '';
            if (empty($raw)) continue;
            $urls = json_decode($raw, true);
            if (!is_array($urls)) continue;

            foreach ($urls as $url) {
                $totalFiles++;
                $filename   = basename($url);
                $relSubPath = resolveRelSubPath($url);
                $alreadyMoved = isAlreadyMoved($url, $projectId, $ARCHIVE_SUBFOLDER);

                if ($alreadyMoved) {
                    // Check file exists in new location
                    $movedPath = $BASE_UPLOAD_PATH . '/' . $relSubPath;
                    if (file_exists($movedPath)) { $totalOk++; continue; }
                    $filePath = $movedPath;
                    $reason   = 'Sudah migrate tapi fail tiada di lokasi baru';
                } else {
                    $filePath = $BASE_UPLOAD_PATH . '/' . $relSubPath;
                    if (file_exists($filePath)) { $totalOk++; continue; }
                    $parentDir = $BASE_UPLOAD_PATH . '/' . dirname($relSubPath);
                    if (!is_dir($BASE_UPLOAD_PATH)) {
                        $reason = 'BASE_UPLOAD_PATH tidak wujud';
                    } elseif (!is_dir($parentDir)) {
                        $reason = 'Folder tidak wujud: ' . dirname($relSubPath);
                    } else {
                        $reason = 'Fail tidak jumpa dalam folder: ' . dirname($relSubPath);
                    }
                }

                $missing[] = [
                    'id'             => $row['id'],
                    'project_id'     => $projectId,
                    'running_number' => $row['running_number'] ?? '',
                    'title'          => $row['title'] ?? '',
                    'col'            => $col,
                    'filename'       => $filename,
                    'db_path'        => $relSubPath,
                    'checked_path'   => $filePath,
                    'reason'         => $reason,
                ];
            }
        }
    }

    return [
        'missing'      => $missing,
        'total_files'  => $totalFiles,
        'total_ok'     => $totalOk,
        'total_missing'=> count($missing),
    ];
}


// ---------- State ----------
$action          = $_POST['action'] ?? ($_GET['action'] ?? null);
$selectedProject = (isset($_POST['project_id']) && $_POST['project_id'] !== '') ? (int)$_POST['project_id'] : null;
$migrateYear = isset($_POST['migrate_year']) && $_POST['migrate_year'] !== '' ? (int)$_POST['migrate_year'] : null;

if (!empty($_POST['selected_files'])) {
    $_SESSION['last_selected_files']   = $_POST['selected_files'];
    $_SESSION['last_selected_project'] = $selectedProject;
}
$lastSelectedFiles = $_SESSION['last_selected_files'] ?? [];

$projectFiles             = [];
$projectYears             = [];
$result                   = null;
$auditResult              = null;
$auditProjectId           = null;
$errorMsg                 = '';
$projectsError            = '';
$showMigrateBtn           = false;
$duplicateRunningNumbers  = [];
$dupAuditResult           = null;
$dupAuditProjectId        = null;

// Run delete
if ($action === 'delete' && $selectedProject) {
    $selectedKeys = (array)($_POST['selected_files'] ?? []);
    if (!empty($selectedKeys)) {
        try {
            $result = deleteSelectedFiles($db, $selectedProject, $selectedKeys, $BASE_UPLOAD_PATH, $PATH_COLUMNS, $TABLE, $ARCHIVE_SUBFOLDER);
            $result['is_live'] = true;
            $result['selected_keys'] = $selectedKeys;
            unset($_SESSION['last_selected_files'], $_SESSION['last_selected_project']);
            $projectFiles = getProjectFiles($db, $selectedProject, $BASE_UPLOAD_PATH, $PATH_COLUMNS, $TABLE, $ARCHIVE_SUBFOLDER);
            $duplicateRunningNumbers = findDuplicateRunningNumbers($db, $selectedProject, $TABLE);
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
        }
    } else {
        $errorMsg = 'Sila pilih sekurang-kurangnya satu fail untuk delete.';
    }
}

// Run audit
if ($action === 'audit') {
    $auditProjectId = (isset($_POST['audit_project_id']) && $_POST['audit_project_id'] !== '') ? (int)$_POST['audit_project_id'] : null;
    try {
        $auditResult = runAudit($db, $BASE_UPLOAD_PATH, $PATH_COLUMNS, $TABLE, $ARCHIVE_SUBFOLDER, $auditProjectId);
    } catch (Exception $e) { $errorMsg = $e->getMessage(); }
}

// NEW: Run duplicate running_number audit
if ($action === 'audit_duplicates') {
    $dupAuditProjectId = (isset($_POST['dup_audit_project_id']) && $_POST['dup_audit_project_id'] !== '') ? (int)$_POST['dup_audit_project_id'] : null;
    try {
        $dupAuditResult = findAllDuplicateRunningNumbers($db, $TABLE, $dupAuditProjectId);
    } catch (Exception $e) { $errorMsg = $e->getMessage(); }
}

// Load project list
$projects = [];
try {
    $res = $db->query("SELECT DISTINCT `$PROJECTS_ID_COL` AS id FROM `$PROJECTS_TABLE` WHERE `$PROJECTS_ID_COL` IS NOT NULL ORDER BY `$PROJECTS_ID_COL` ASC");
    while ($p = $res->fetch_assoc()) $projects[] = $p;
} catch (Exception $e) { $projectsError = $e->getMessage(); }

// Load files for selected project
if ($selectedProject) {
    try {
        $projectFiles            = getProjectFiles($db, $selectedProject, $BASE_UPLOAD_PATH, $PATH_COLUMNS, $TABLE, $ARCHIVE_SUBFOLDER);
        $duplicateRunningNumbers = findDuplicateRunningNumbers($db, $selectedProject, $TABLE);
        $projectYears             = getProjectYears($db, $selectedProject, $TABLE);
    } catch (Exception $e) { $errorMsg = $e->getMessage(); }
}

// Run preview or migrate
if ($selectedProject && in_array($action, ['preview', 'migrate'])) {
    $selectedKeys = (array)($_POST['selected_files'] ?? $lastSelectedFiles);
    if (!empty($selectedKeys)) {
        $isLive = ($action === 'migrate');
        try {
            // Refresh duplicate list immediately before processing so the check
            // always uses the current DB state.
            $duplicateRunningNumbers = findDuplicateRunningNumbers($db, $selectedProject, $TABLE);

            $result = migrateSelectedFiles(
                $db,
                $selectedProject,
                $selectedKeys,
                $isLive,
                $BASE_UPLOAD_PATH,
                $BASE_URL_PREFIX,
                $PATH_COLUMNS,
                $TABLE,
                $ARCHIVE_SUBFOLDER,
                $migrateYear
            );
            $result['is_live']       = $isLive;
            $result['selected_keys'] = $selectedKeys;

            if ($isLive) {
                unset($_SESSION['last_selected_files'], $_SESSION['last_selected_project']);
                $projectFiles = getProjectFiles($db, $selectedProject, $BASE_UPLOAD_PATH, $PATH_COLUMNS, $TABLE, $ARCHIVE_SUBFOLDER);
                $duplicateRunningNumbers = findDuplicateRunningNumbers($db, $selectedProject, $TABLE);
            } else {
                $showMigrateBtn = true;
            }
        } catch (Exception $e) { $errorMsg = $e->getMessage(); }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<title>Document Migration Tool</title>
<style>
* { box-sizing: border-box; }
body { font-family: Arial, sans-serif; max-width: 980px; margin: 30px auto; color: #1f2937; padding: 0 16px; }
h2   { margin-bottom: 4px; }
.sub { color: #6b7280; margin-top: 0; }
.card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
select { padding: 8px 12px; font-size: 14px; border-radius: 4px; border: 1px solid #d1d5db; }
button { padding: 8px 14px; font-size: 14px; border-radius: 4px; border: none; cursor: pointer; }
.btn-primary { background: #2563eb; color: white; }
.btn-preview { background: #0891b2; color: white; }
.btn-migrate { background: #dc2626; color: white; font-weight: bold; }
.btn-delete { background: #7f1d1d; color: white; font-weight: bold; }
.btn-logout  { background: transparent; color: #6b7280; text-decoration: underline; border: none; cursor: pointer; padding: 0; font-size: 14px; }
.btn-sm      { padding: 4px 10px; font-size: 12px; }
table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
th, td { border: 1px solid #e5e7eb; padding: 7px 10px; text-align: left; vertical-align: middle; }
th { background: #f3f4f6; font-weight: 600; }
tr:hover td { background: #f0f9ff; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.badge-ok      { background: #dcfce7; color: #166534; }
.badge-moved   { background: #ede9fe; color: #5b21b6; }
.badge-missing { background: #fee2e2; color: #991b1b; }
.log-table td, .log-table th { padding: 5px 8px; }
.row-move       { background: #eff6ff; }
.row-error      { background: #fef2f2; color: #b91c1c; }
.row-warn       { background: #fffbeb; color: #92400e; }
.row-skip       { background: #f3f4f6; color: #6b7280; }
.row-mkdir      { background: #ecfdf5; color: #065f46; }
.row-db         { background: #f5f3ff; color: #5b21b6; }
.row-db-preview { background: #fdf4ff; color: #7e22ce; }
.summary     { font-weight: bold; margin-top: 10px; }
.badge-live  { background: #dc2626; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
.badge-dry   { background: #0891b2; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
.err         { color: #b91c1c; font-weight: 500; }
.select-btns { display: flex; gap: 8px; margin-bottom: 8px; align-items: center; flex-wrap: wrap; }
.actions-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 14px; padding-top: 14px; border-top: 1px solid #e5e7eb; }
.step { display: inline-block; background: #2563eb; color: #fff; border-radius: 50%; width: 22px; height: 22px; text-align: center; line-height: 22px; font-size: 12px; font-weight: bold; margin-right: 6px; }
.migrate-hint { background: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px; padding: 10px 14px; font-size: 13px; color: #92400e; margin-top: 10px; }
</style>
</head>
<body>

<h2>Document Migration Tool</h2>
<p class="sub">
    Migrate dokumen ke folder per-project (project_&lt;id&gt;/<?= htmlspecialchars($ARCHIVE_SUBFOLDER) ?>/&lt;tahun request_date&gt;/) dan kemaskini path dalam DB.
    &nbsp;|&nbsp; <a class="btn-logout" href="?logout=1">Logout</a>
</p>

<?php if (!empty($errorMsg)):    ?><p class="err">Error: <?= htmlspecialchars($errorMsg) ?></p><?php endif; ?>
<?php if (!empty($projectsError)): ?><p class="err">Gagal load project list: <?= htmlspecialchars($projectsError) ?></p><?php endif; ?>

<!-- ===== AUDIT BAR ===== -->
<div class="card" style="padding:14px 20px;background:#f0f9ff;border-color:#bae6fd;">
    <p style="margin-top:0;font-weight:600;color:#0369a1">🔍 Audit Fail</p>
    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="action" value="audit">
        <select name="audit_project_id">
            <option value="">-- Semua Project --</option>
            <?php foreach ($projects as $p): ?>
                <option value="<?= htmlspecialchars($p['id']) ?>" <?= ($auditProjectId == $p['id']) ? 'selected' : '' ?>>
                    Project ID: <?= htmlspecialchars($p['id']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" style="background:#0369a1;color:white;padding:8px 14px;border:none;border-radius:4px;cursor:pointer;font-size:13px">
            🔎 Jalankan Audit
        </button>
        <span style="color:#6b7280;font-size:12px">Scan rekod dalam DB dan detect fail yang tiada di server.</span>
    </form>
</div>

<?php if ($auditResult !== null): ?>
<div class="card">
    <p style="margin-top:0;font-weight:600">📋 Keputusan Audit
        <?php if ($auditProjectId): ?>
            <span style="font-weight:normal;color:#6b7280;font-size:13px">— Project ID: <?= htmlspecialchars($auditProjectId) ?></span>
        <?php else: ?>
            <span style="font-weight:normal;color:#6b7280;font-size:13px">— Semua Project</span>
        <?php endif; ?>
    </p>
    <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap">
        <div style="background:#dcfce7;border-radius:6px;padding:8px 16px;font-size:13px">✅ <strong><?= $auditResult['total_ok'] ?></strong> Fail Wujud</div>
        <div style="background:#fee2e2;border-radius:6px;padding:8px 16px;font-size:13px">✘ <strong><?= $auditResult['total_missing'] ?></strong> Fail Tiada di Disk</div>
        <div style="background:#f3f4f6;border-radius:6px;padding:8px 16px;font-size:13px">📄 <strong><?= $auditResult['total_files'] ?></strong> Jumlah Fail dalam DB</div>
    </div>

    <?php if (empty($auditResult['missing'])): ?>
        <p style="color:#166534;font-weight:500">✅ Semua fail dalam DB wujud di server. Tiada isu ditemui.</p>
    <?php else: ?>
        <div style="overflow-x:auto">
        <table style="width:auto;min-width:600px">
            <thead>
                <tr>
                    <th style="width:50px">ID</th>
                    <th style="width:80px">Project ID</th>
                    <th>Running Number</th>
                    <th>Tarikh Surat</th>
                    <th>Title</th>
                    <th>Nama Fail</th>
                    <th>Sebab</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // Sort by id ASC
            usort($auditResult['missing'], fn($a, $b) => $a['id'] <=> $b['id']);
            foreach ($auditResult['missing'] as $m):
            ?>
                <tr style="background:#fef2f2">
                    <td style="text-align:center"><?= htmlspecialchars($m['id']) ?></td>
                    <td style="text-align:center"><?= htmlspecialchars($m['project_id']) ?></td>
                    <td style="white-space:nowrap;font-weight:500"><?= htmlspecialchars($m['running_number']) ?></td>
                    <td><?= htmlspecialchars($m['title']) ?></td>
                    <td style="font-size:12px;color:#991b1b"><?= htmlspecialchars($m['filename']) ?></td>
                    <td><span class="badge badge-missing"><?= htmlspecialchars($m['reason']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p style="font-size:12px;color:#6b7280;margin-top:8px">
            💡 Fail yang tiada di disk kemungkinan: sudah dipadam manual, path dalam DB salah, atau belum diupload oleh user.
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>


<!-- ===== NEW: DUPLICATE RUNNING NUMBER AUDIT BAR ===== -->
<div class="card" style="padding:14px 20px;background:#fff7ed;border-color:#fed7aa;">
    <p style="margin-top:0;font-weight:600;color:#9a3412">🧬 Audit Running Number Duplicate</p>
    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="action" value="audit_duplicates">
        <select name="dup_audit_project_id">
            <option value="">-- Semua Project --</option>
            <?php foreach ($projects as $p): ?>
                <option value="<?= htmlspecialchars($p['id']) ?>" <?= ($dupAuditProjectId == $p['id']) ? 'selected' : '' ?>>
                    Project ID: <?= htmlspecialchars($p['id']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" style="background:#9a3412;color:white;padding:8px 14px;border:none;border-radius:4px;cursor:pointer;font-size:13px">
            🔎 Cari Duplicate
        </button>
        <span style="color:#6b7280;font-size:12px">Scan rekod dalam DB dan detect running_number yang berulang dalam project yang sama.</span>
    </form>
</div>

<?php if ($dupAuditResult !== null): ?>
<div class="card">
    <p style="margin-top:0;font-weight:600">📋 Keputusan Audit Duplicate
        <?php if ($dupAuditProjectId): ?>
            <span style="font-weight:normal;color:#6b7280;font-size:13px">— Project ID: <?= htmlspecialchars($dupAuditProjectId) ?></span>
        <?php else: ?>
            <span style="font-weight:normal;color:#6b7280;font-size:13px">— Semua Project</span>
        <?php endif; ?>
    </p>

    <?php if (empty($dupAuditResult)): ?>
        <p style="color:#166534;font-weight:500">✅ Tiada running_number duplicate ditemui.</p>
    <?php else: ?>
        <div style="background:#fee2e2;border-radius:6px;padding:8px 16px;font-size:13px;display:inline-block;margin-bottom:12px">
            ⚠️ <strong><?= count($dupAuditResult) ?></strong> running_number duplicate ditemui
        </div>
        <div style="overflow-x:auto">
        <table style="width:auto;min-width:600px">
            <thead>
                <tr>
                    <th style="width:80px">Project ID</th>
                    <th>Running Number</th>
                    <th style="width:100px">Bilangan</th>
                    <th>Record IDs</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($dupAuditResult as $dup): ?>
                <tr style="background:#fef2f2">
                    <td style="text-align:center"><?= htmlspecialchars($dup['project_id']) ?></td>
                    <td style="font-weight:600"><?= htmlspecialchars($dup['running_number']) ?></td>
                    <td style="text-align:center"><?= htmlspecialchars($dup['total']) ?></td>
                    <td style="color:#6b7280"><?= htmlspecialchars($dup['ids']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p style="font-size:12px;color:#6b7280;margin-top:8px">
            💡 Ini bermakna ada &gt;1 rekod berlainan yang kongsi running_number sama dalam project yang sama — kemungkinan input duplicate.
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>


<!-- ===== STEP 1: Pilih Project ===== -->
<div class="card">
    <p style="margin-top:0"><span class="step">1</span><strong>Pilih Project</strong></p>
    <form method="post">
        <input type="hidden" name="action" value="load_files">
        <select name="project_id" required>
            <option value="">-- Pilih Project --</option>
            <?php foreach ($projects as $p): ?>
                <option value="<?= htmlspecialchars($p['id']) ?>" <?= ($selectedProject == $p['id']) ? 'selected' : '' ?>>
                    Project ID: <?= htmlspecialchars($p['id']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        &nbsp;
        <button type="submit" class="btn-primary">Muatkan Fail &rarr;</button>
    </form>
</div>


<!-- ===== NEW: Duplicate running_number warning for the selected project ===== -->
<?php if ($selectedProject && !empty($duplicateRunningNumbers)): ?>
<div class="card" style="background:#fef2f2;border-color:#fecaca">
    <p style="margin-top:0;font-weight:600;color:#991b1b">
        ⚠️ Running Number Duplicate Dikesan dalam Project Ini (<?= count($duplicateRunningNumbers) ?>)
    </p>
    <table style="width:auto;min-width:400px">
        <thead>
            <tr><th>Running Number</th><th>Bilangan Rekod</th><th>Record IDs</th></tr>
        </thead>
        <tbody>
        <?php foreach ($duplicateRunningNumbers as $dup): ?>
            <tr style="background:#fff">
                <td style="font-weight:600"><?= htmlspecialchars($dup['running_number']) ?></td>
                <td style="text-align:center"><?= htmlspecialchars($dup['total']) ?></td>
                <td style="color:#6b7280"><?= htmlspecialchars($dup['ids']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="font-size:12px;color:#7f1d1d;margin-top:8px;margin-bottom:0">
        💡 Duplicate dikira berdasarkan <strong>project_id + running_number</strong> selepas whitespace dibuang. Semak rekod-rekod ini sebelum migrate.
    </p>
</div>
<?php endif; ?>


<!-- ===== STEP 2: Pilih Fail ===== -->
<?php if ($selectedProject && !empty($projectFiles)): ?>
<div class="card">
    <p style="margin-top:0"><span class="step">2</span><strong>Pilih Fail untuk Di-migrate &nbsp;<span style="color:#6b7280;font-weight:normal">(Project ID: <?= htmlspecialchars($selectedProject) ?>)</span></strong></p>

    <?php
        // Count unique record IDs (satu rekod boleh ada berbilang fail)
        $uniqueRecordIds  = array_unique(array_column($projectFiles, 'record_id'));
        $totalUniqueRecs  = count($uniqueRecordIds);

        $statBersedia   = count(array_filter($projectFiles, fn($f) => !$f['already_moved'] && $f['file_exists'] && !$f['no_file']));
        $statMoved      = count(array_filter($projectFiles, fn($f) => $f['already_moved']));
        $statMissing    = count(array_filter($projectFiles, fn($f) => !$f['already_moved'] && !$f['file_exists'] && !$f['no_file']));
        $statNoFile     = count(array_filter($projectFiles, fn($f) => $f['no_file']));
        $totalFails      = $statBersedia + $statMoved + $statMissing; // total fail entries
        $missingReasons = array_count_values(array_column(
            array_filter($projectFiles, fn($f) => !$f['already_moved'] && !$f['file_exists'] && !$f['no_file']),
            'missing_reason'
        ));

        // NEW: running_number for a duplicated record, for row highlighting below
        $dupRunningNumberSet = [];
        foreach ($duplicateRunningNumbers as $dup) {
            $dupRunningNumberSet[normalizeRunningNumber($dup['running_number'])] = true;
        }
    ?>

    <!-- Summary tally -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:13px">
        <strong>Ringkasan:</strong>
        &nbsp; Rekod DB: <strong><?= $totalUniqueRecs ?></strong>
        &nbsp;=&nbsp; Rekod ada fail: <strong><?= $totalUniqueRecs - $statNoFile ?></strong>
        &nbsp;+&nbsp; Rekod tiada fail: <strong><?= $statNoFile ?></strong>
        &nbsp;&nbsp;|&nbsp;&nbsp; Jumlah fail entries: <strong><?= $totalFails ?></strong>
        <?php if ($totalUniqueRecs !== ($totalUniqueRecs - $statNoFile) + $statNoFile): ?>
            <span style="color:#dc2626"> ⚠ Kiraan tak tally!</span>
        <?php endif; ?>
    </div>

    <div style="display:flex;gap:12px;margin-bottom:14px;flex-wrap:wrap">
        <div style="background:#dcfce7;border-radius:6px;padding:8px 14px;font-size:13px">✅ <strong><?= $statBersedia ?></strong> Fail bersedia</div>
        <div style="background:#ede9fe;border-radius:6px;padding:8px 14px;font-size:13px">✔ <strong><?= $statMoved ?></strong> Sudah dipindah</div>
        <div style="background:#fee2e2;border-radius:6px;padding:8px 14px;font-size:13px">✘ <strong><?= $statMissing ?></strong> Fail tidak jumpa</div>
        <?php if ($statNoFile > 0): ?>
        <div style="background:#f3f4f6;border-radius:6px;padding:8px 14px;font-size:13px">— <strong><?= $statNoFile ?></strong> Rekod tiada fail</div>
        <?php endif; ?>
        <?php if (!empty($duplicateRunningNumbers)): ?>
        <div style="background:#fee2e2;border:1px solid #fecaca;border-radius:6px;padding:8px 14px;font-size:13px">🧬 <strong><?= count($duplicateRunningNumbers) ?></strong> Running Number duplicate</div>
        <?php endif; ?>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:8px 14px;font-size:13px">📄 <strong><?= $totalUniqueRecs ?></strong> Jumlah rekod DB</div>
    </div>

    <?php if ($statMissing > 0): ?>
    <details style="margin-bottom:14px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:10px 14px">
        <summary style="cursor:pointer;font-weight:600;color:#92400e">🔍 Diagnostic: Kenapa <?= $statMissing ?> fail tidak jumpa?</summary>
        <ul style="margin:10px 0 0;padding-left:20px;font-size:13px;color:#78350f">
            <?php foreach ($missingReasons as $reason => $count): ?>
                <li><strong><?= $count ?> fail</strong> — <?= htmlspecialchars($reason) ?></li>
            <?php endforeach; ?>
        </ul>
    </details>
    <?php endif; ?>

    <form method="post" id="file-form">
        <input type="hidden" name="project_id" value="<?= htmlspecialchars($selectedProject) ?>">

        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px;padding:10px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;">
            <strong style="font-size:13px;color:#1e3a8a;">Tahun Migration:</strong>
            <select name="migrate_year" id="migrate-year" style="padding:7px 10px;font-size:13px;border:1px solid #93c5fd;border-radius:4px;" onchange="selectYear(this.value)">
                <option value="">-- Semua Tahun --</option>
                <?php foreach ($projectYears as $yr): ?>
                    <option value="<?= htmlspecialchars($yr) ?>" <?= ($migrateYear == $yr) ? 'selected' : '' ?>><?= htmlspecialchars($yr) ?></option>
                <?php endforeach; ?>
            </select>
            <span style="font-size:12px;color:#6b7280;">Pilih tahun untuk select fail dan migrate tahun tersebut sahaja.</span>
            <span id="year-count" style="margin-left:auto;background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;border-radius:6px;padding:6px 10px;font-size:13px;font-weight:600;white-space:nowrap;">Jumlah: 0 fail</span>
        </div>

        <div class="select-btns">
            <button type="button" class="btn-sm btn-primary" onclick="selectAll(true)">Pilih Semua</button>
            <button type="button" class="btn-sm" style="background:#e5e7eb;color:#374151" onclick="selectAll(false)">Nyahpilih Semua</button>
            <button type="button" class="btn-sm" style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0" onclick="selectPending()">Pilih Yang Belum Migrate</button>
            <span id="count-label" style="margin-left:auto;color:#6b7280;font-size:13px"></span>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:36px"><input type="checkbox" id="check-all" onchange="toggleAll(this)"></th>
                    <th style="width:40px;text-align:center">No.</th>
                    <th>Running Number</th>
                    <th>Tarikh Surat</th>
                    <th>Title</th>
                    <th>Nama Fail</th>
                    <th>Lokasi Asal (uploads/...)</th>
                    <th>Destinasi (uploads/...)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // Group by running_number — skip no_file records (shown in separate table below)
            $grouped = [];
            foreach ($projectFiles as $f) {
                if ($f['no_file']) continue;
                $normalizedRN = normalizeRunningNumber($f['running_number']);
                $grouped[$normalizedRN][] = $f;
            }
            $groupNo = 0;
            foreach ($grouped as $runningNum => $files):
                $groupNo++;
                $rowspan = count($files);
                $firstRow = true;
                $isDupGroup = isset($dupRunningNumberSet[normalizeRunningNumber($runningNum)]);
                foreach ($files as $f):
                    $key       = "{$f['record_id']}:{$f['col']}:{$f['filename']}";
                    $checked   = in_array($key, $lastSelectedFiles);
                    $canSelect = $f['file_exists'] && $f['request_year'] !== null && !$f['already_moved'];
                    if ($f['request_year'] !== null) {
                        $destSubPath = resolveDestSubPath($f['filename'], $ARCHIVE_SUBFOLDER, $f['request_date']);
                        $destDisplay = "project_{$selectedProject}/{$destSubPath}";
                    } else {
                        $destSubPath = null;
                        $destDisplay = '— request_date kosong/tidak sah';
                    }

                    if ($f['no_file']) {
                        $badge     = "<span class='badge' style='background:#f3f4f6;color:#6b7280'>— Tiada Fail</span>";
                        $canSelect = false;
                    } elseif ($f['already_moved']) {
                        $badge = "<span class='badge badge-moved'>✔ Sudah ikut tahun</span>";
                    } elseif (!$f['file_exists']) {
                        $tip = htmlspecialchars($f['missing_reason']);
                        if (!empty($f['folder_contents'])) {
                            $fileList = implode(', ', array_map('htmlspecialchars', array_slice($f['folder_contents'], 0, 5)));
                            $more     = count($f['folder_contents']) > 5 ? ' (+' . (count($f['folder_contents']) - 5) . ' lagi)' : '';
                            $badge    = "<span class='badge badge-missing'>✘ Tidak jumpa</span>"
                                      . "<br><small style='color:#6b7280'>DB: <b>" . htmlspecialchars($f['filename']) . "</b></small>"
                                      . "<br><small style='color:#6b7280'>Folder ada: {$fileList}{$more}</small>";
                        } else {
                            $badge = "<span class='badge badge-missing' title='{$tip}'>✘ {$tip} ⓘ</span>";
                        }
                    } else {
                        $isLegacyArchive = isAlreadyMoved($f['url'], $selectedProject, $ARCHIVE_SUBFOLDER);
                        if ($isLegacyArchive) {
                            $badge = "<span class='badge' style='background:#fef3c7;color:#92400e'>↻ Pernah migrate — boleh susun ikut tahun</span>";
                        } else {
                            $badge = "<span class='badge badge-ok'>✅ Bersedia</span>";
                        }
                    }

                    // Alternating group background — highlight red-ish if duplicate running_number group
                    if ($isDupGroup) {
                        $rowBg = ($groupNo % 2 === 0) ? '#fef2f2' : '#fff5f5';
                    } else {
                        $rowBg = ($groupNo % 2 === 0) ? '#fafafa' : '#ffffff';
                    }
            ?>
                <tr style="background:<?= $rowBg ?>">
                    <td style="text-align:center">
                        <input type="checkbox"
                               name="selected_files[]"
                               value="<?= htmlspecialchars($key) ?>"
                               class="file-cb <?= $canSelect ? 'pending-cb' : '' ?>" data-year="<?= htmlspecialchars($f['request_year'] ?? '') ?>"
                               <?= $checked ? 'checked' : '' ?>
                               <?= !$canSelect ? 'disabled' : '' ?>
                               onchange="updateCount()">
                    </td>
                    <?php if ($firstRow): ?>
                    <td style="text-align:center;font-weight:600;color:#6b7280;vertical-align:top;padding-top:10px" rowspan="<?= $rowspan ?>">
                        <?= $groupNo ?>
                        <?php if ($rowspan > 1): ?>
                            <br><small style="font-weight:normal;color:#9ca3af"><?= $rowspan ?> fail</small>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;font-weight:500;vertical-align:top;padding-top:10px" rowspan="<?= $rowspan ?>">
                        <?= htmlspecialchars($runningNum) ?>
                        <?php if ($isDupGroup): ?>
                            <br><span class="badge badge-missing" style="margin-top:4px">🧬 Duplicate</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;vertical-align:top;padding-top:10px" rowspan="<?= $rowspan ?>">
                        <?= htmlspecialchars($f['request_date'] ?: '—') ?>
                        <?php if ($f['request_year'] !== null): ?>
                            <br><small style="color:#6b7280">Tahun: <?= htmlspecialchars($f['request_year']) ?></small>
                        <?php else: ?>
                            <br><span class="badge badge-missing">Tarikh tak sah</span>
                        <?php endif; ?>
                    </td>
                    <td style="vertical-align:top;padding-top:10px" rowspan="<?= $rowspan ?>">
                        <?= htmlspecialchars($f['title']) ?>
                    </td>
                    <?php else: ?>
                    <?php endif; ?>
                    <td style="font-size:12px"><?= htmlspecialchars($f['filename']) ?></td>
                    <td style="color:#6b7280;font-size:12px"><?= htmlspecialchars($f['rel_sub_path']) ?></td>
                    <td style="color:#059669;font-size:12px"><?= htmlspecialchars($destDisplay) ?></td>
                    <td><?= $badge ?></td>
                </tr>
            <?php
                    $firstRow = false;
                endforeach;
            endforeach;
            ?>
            </tbody>
        </table>

        <div class="actions-bar">
            <button type="submit" name="action" value="preview" class="btn-preview"
                    onclick="return validateSelection()">
                🔍 Preview (tanpa ubah apa-apa)
            </button>

            <button type="submit" name="action" value="migrate" class="btn-migrate"
                    onclick="return validateSelection() && confirmMigrate()">
                ⚡ Migrate Terus (LIVE)
            </button>

            <button type="submit" name="action" value="delete" class="btn-delete"
                    onclick="return validateSelection() && confirm('⚠️ PASTI nak DELETE fail yang dipilih?\n\nFail fizikal akan dipadam dari server dan reference/rekod DB juga akan dipadam. Tindakan ini TIDAK boleh undo automatik.')">
                🗑 Delete File + DB
            </button>
        </div>

        <?php if ($showMigrateBtn): ?>
        <div class="migrate-hint">
            ✅ Preview siap! Semak log di bawah. Klik <strong>"⚡ Migrate Terus (LIVE)"</strong> di atas untuk teruskan dengan fail yang sama dipilih.
        </div>
        <?php endif; ?>
    </form>

    <?php
    $noFileRecords = array_filter($projectFiles, fn($f) => $f['no_file']);
    if (!empty($noFileRecords)):
    ?>
    <div style="margin-top:24px;border-top:2px solid #e5e7eb;padding-top:16px">
        <p style="margin:0 0 10px;font-weight:600;color:#6b7280">
            — Rekod Tanpa Fail <span style="font-weight:normal;font-size:13px">(<?= count($noFileRecords) ?> rekod — file_path NULL atau kosong)</span>
        </p>
        <table style="width:auto;min-width:500px">
            <thead>
                <tr>
                    <th style="width:50px;text-align:center">No.</th>
                    <th style="width:60px;text-align:center">ID</th>
                    <th>Running Number</th>
                    <th>Tarikh Surat</th>
                    <th>Title</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $noFileNo = 0;
            foreach ($noFileRecords as $f):
                $noFileNo++;
            ?>
                <tr style="background:#fafafa">
                    <td style="text-align:center;color:#9ca3af"><?= $noFileNo ?></td>
                    <td style="text-align:center;color:#9ca3af"><?= htmlspecialchars($f['record_id']) ?></td>
                    <td style="font-weight:500;white-space:nowrap"><?= htmlspecialchars($f['running_number']) ?></td>
                    <td style="color:#6b7280"><?= htmlspecialchars($f['title']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($selectedProject && empty($projectFiles)): ?>
<div class="card">
    <p style="color:#6b7280;margin:0">Tiada fail ditemui untuk Project ID <?= htmlspecialchars($selectedProject) ?>.</p>
</div>
<?php endif; ?>


<!-- ===== STEP 3: Hasil Preview / Migrate ===== -->
<?php if ($result): ?>
<div class="card">
    <p style="margin-top:0"><span class="step">3</span><strong>Keputusan</strong></p>
    <p>
        <?php if ($migrateYear): ?><span class="badge" style="background:#dbeafe;color:#1e40af">Tahun <?= htmlspecialchars($migrateYear) ?></span> &nbsp;|&nbsp;<?php endif; ?>
        Mode:
        <?= $result['is_live']
            ? '<span class="badge-live">⚡ LIVE - Perubahan Disimpan</span>'
            : '<span class="badge-dry">🔍 DRY RUN - Preview Sahaja</span>' ?>
        &nbsp;|&nbsp; Records diimbas: <strong><?= $result['total_records'] ?></strong>
    </p>

    <table class="log-table">
        <thead><tr><th>Jenis</th><th>Butiran</th></tr></thead>
        <tbody>
        <?php if (empty($result['log'])): ?>
            <tr><td colspan="2">Tiada perubahan diperlukan untuk fail yang dipilih.</td></tr>
        <?php endif; ?>
        <?php foreach ($result['log'] as $entry): ?>
            <tr class="row-<?= htmlspecialchars($entry['type']) ?>">
                <td style="white-space:nowrap"><?= htmlspecialchars(strtoupper($entry['type'])) ?></td>
                <td><?= htmlspecialchars($entry['text']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (isset($result['summary']['deleted_files'])): ?>
    <p class="summary">
        Fail dipadam: <strong><?= $result['summary']['deleted_files'] ?></strong>
        &nbsp;|&nbsp; Rekod DB dipadam: <strong><?= $result['summary']['deleted_records'] ?></strong>
        &nbsp;|&nbsp; Rekod DB dikemaskini: <strong><?= $result['summary']['updated_records'] ?></strong>
        &nbsp;|&nbsp; Error: <strong style="color:<?= $result['summary']['errors'] > 0 ? '#dc2626' : 'inherit' ?>"><?= $result['summary']['errors'] ?></strong>
    </p>
    <?php else: ?>
    <p class="summary">
        Fail <?= $result['is_live'] ? 'dipindah' : 'akan dipindah' ?>: <strong><?= $result['summary']['moved'] ?></strong>
        &nbsp;|&nbsp; Error: <strong style="color:<?= $result['summary']['errors'] > 0 ? '#dc2626' : 'inherit' ?>"><?= $result['summary']['errors'] ?></strong>
        &nbsp;|&nbsp; Langkau: <?= $result['summary']['skipped'] ?>
    </p>
    <?php endif; ?>

    <?php if ($result['is_live']): ?>
        <p style="color:#166534;font-weight:500">✅ <?= isset($result['summary']['deleted_files']) ? 'Delete selesai. Fail dan DB telah diproses.' : 'Migration selesai. Sila semak fail dan DB untuk pastikan semua betul.' ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

</body>
</html>

<script>
function updateCount() {
    const year = document.getElementById('migrate-year')?.value || '';
    const allEnabled = Array.from(document.querySelectorAll('.file-cb:not([disabled])'));
    const yearFiles = allEnabled.filter(c => year === '' || c.dataset.year === String(year));
    const yearChecked = yearFiles.filter(c => c.checked);
    const yearPending = yearFiles.filter(c => !c.checked);

    const label = document.getElementById('count-label');
    if (label) {
        label.textContent = year
            ? yearChecked.length + ' / ' + yearFiles.length + ' fail tahun ' + year + ' dipilih'
            : yearChecked.length + ' / ' + yearFiles.length + ' fail dipilih';
    }

    const yearCount = document.getElementById('year-count');
    if (yearCount) {
        if (year) {
            yearCount.innerHTML = 'Jumlah tahun <strong>' + year + '</strong>: <strong>' + yearFiles.length + '</strong> fail';
        } else {
            yearCount.innerHTML = 'Jumlah semua tahun: <strong>' + yearFiles.length + '</strong> fail';
        }
    }

    const ca = document.getElementById('check-all');
    if (ca) {
        ca.indeterminate = yearChecked.length > 0 && yearChecked.length < yearFiles.length;
        ca.checked = yearFiles.length > 0 && yearChecked.length === yearFiles.length;
    }
}
function toggleAll(cb) {
    const year = document.getElementById('migrate-year')?.value || '';
    document.querySelectorAll('.file-cb:not([disabled])').forEach(c => {
        c.checked = cb.checked && (year === '' || c.dataset.year === String(year));
    });
    updateCount();
}
function selectAll(val) {
    const year = document.getElementById('migrate-year')?.value || '';
    document.querySelectorAll('.file-cb:not([disabled])').forEach(c => {
        c.checked = val && (year === '' || c.dataset.year === String(year));
    });
    updateCount();
}
function selectPending() {
    const year = document.getElementById('migrate-year')?.value || '';
    document.querySelectorAll('.file-cb:not([disabled])').forEach(c => {
        c.checked = (year === '' || c.dataset.year === String(year));
    });
    updateCount();
}
function selectYear(year) {
    document.querySelectorAll('.file-cb:not([disabled])').forEach(c => {
        if (year === '') {
            c.checked = false;
        } else {
            c.checked = c.dataset.year === String(year);
        }
    });
    updateCount();
}

function confirmMigrate() {
    const year = document.getElementById('migrate-year')?.value || '';
    const yearText = year ? (' Tahun ' + year + '.') : ' Semua tahun.';
    return confirm('⚠️ PASTI nak migrate fail yang dipilih untuk' + yearText + '\n\nTindakan ini akan MOVE fail sebenar dan update DB. Tindakan ini TIDAK boleh undo automatik.');
}

function validateSelection() {
    const checked = document.querySelectorAll('.file-cb:not([disabled]):checked');
    if (checked.length === 0) { alert('Sila pilih sekurang-kurangnya satu fail.'); return false; }
    return true;
}
// Bila page dibuka dengan tahun yang sudah dipilih, terus auto-select fail tahun tersebut.
document.addEventListener('DOMContentLoaded', function () {
    const yearSelect = document.getElementById('migrate-year');
    if (yearSelect && yearSelect.value !== '') {
        selectYear(yearSelect.value);
    } else {
        updateCount();
    }
});
</script>
<?php
/**
 * Standalone admin tool: Migrate CERUN documents into per-cerun ARCHIVE folders.
 * Table target: letteroutarchive_cerun
 * Destination is always: uploads/cerun_{id}/archive/<year>/<filename> (year from request_date)
 *
 * Perbezaan berbanding tool asal (letter_out_archive):
 *   - ID grouping guna column `cerun_id` (bukan `project_id`)
 *   - Path fail guna column `archive_file` (text) — boleh simpan JSON array
 *     ATAU satu plain string path sahaja. Tool ni handle DUA-DUA format.
 */

// TEMP DEBUG: papar error sebenar bukan generic "page isn't working".
// Buang / set ke 0 lepas siap debug.
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// ====================== CONFIG - EDIT THESE ======================
require_once 'include/msqli_connect.php';

$TABLE           = 'letteroutarchive_cerun';
$PROJECTS_TABLE  = 'letteroutarchive_cerun';
$PROJECTS_ID_COL = 'cerun_id';

$BASE_UPLOAD_PATH = 'C:/inetpub/vhosts/mggroup.my/httpdocs/projectmanagement/uploads';
$BASE_URL_PREFIX  = 'https://mggroup.my/projectmanagement/uploads';

// Only ONE path column for this table
$PATH_COLUMNS = ['archive_file'];

// Fixed subfolder name inside each cerun folder where files should end up
$ARCHIVE_SUBFOLDER = 'archive';

// Folder prefix used on disk/URL: cerun_{id}
$FOLDER_PREFIX = 'cerun_';

// If true, when a file is missing the tool will scandir() its parent folder
// to show "what's actually in there" as a hint. This can be expensive/memory
// heavy on large folders, so it's OFF by default. Turn on only if you need
// to debug a specific missing-file case.
$ENABLE_FOLDER_DIAGNOSTIC = false;

$ADMIN_PASSWORD = '12345';
// ====================== END CONFIG ======================


// ---------- Login gate ----------
if (isset($_POST['login_password'])) {
    if ($_POST['login_password'] === $ADMIN_PASSWORD) {
        $_SESSION['migrate_tool_auth_cerun'] = true;
    } else {
        $loginError = 'Password salah.';
    }
}
if (isset($_GET['logout'])) unset($_SESSION['migrate_tool_auth_cerun']);
$isAuthed = !empty($_SESSION['migrate_tool_auth_cerun']);

if (!$isAuthed) { ?>
<!DOCTYPE html>
<html lang="ms">
<head><meta charset="UTF-8"><title>Login - Migration Tool (Cerun)</title>
<style>
body{font-family:Arial;max-width:400px;margin:80px auto;}
input{width:100%;padding:8px;margin:8px 0;box-sizing:border-box;}
button{padding:8px 16px;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer;}
.err{color:red;}
</style>
</head>
<body>
    <h2>Document Migration Tool — Cerun</h2>
    <?php if (!empty($loginError)) echo "<p class='err'>" . htmlspecialchars($loginError) . "</p>"; ?>
    <form method="post">
        <label>Password Admin:</label>
        <input type="password" name="login_password" required autofocus>
        <button type="submit">Login</button>
    </form>
</body>
</html>
<?php exit; }


// ---------- Helper: decode archive_file value into an array of paths ----------
// Handles TWO formats:
//   1) JSON array string  -> ["uploads/xxx/a.pdf","uploads/xxx/b.pdf"]
//   2) Plain single path  -> "uploads/xxx/a.pdf"
function decodeArchivePaths($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return ['paths' => [], 'was_json' => false];
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return ['paths' => array_values(array_filter($decoded, fn($v) => trim((string)$v) !== '')), 'was_json' => true];
    }
    return ['paths' => [$raw], 'was_json' => false];
}

// ---------- Helper: encode paths back into the SAME format it came from ----------
function encodeArchivePaths(array $paths, bool $wasJson) {
    $paths = array_values($paths);
    if ($wasJson) {
        return json_encode($paths, JSON_UNESCAPED_SLASHES);
    }
    // Was a plain string originally. If somehow more than 1 path ended up here
    // (shouldn't normally happen), fall back to JSON so no data is lost.
    if (count($paths) > 1) {
        return json_encode($paths, JSON_UNESCAPED_SLASHES);
    }
    return $paths[0] ?? '';
}

// ---------- Helper: resolve relative subpath from a URL/path stored in DB ----------
function resolveRelSubPath($url) {
    if (preg_match('#^https?://#i', $url)) {
        $urlPath    = parse_url($url, PHP_URL_PATH);
        $uploadsPos = strpos($urlPath, '/uploads/');
        return ($uploadsPos !== false)
            ? substr($urlPath, $uploadsPos + strlen('/uploads/'))
            : basename($url);
    }
    $rel = ltrim($url, '/');
    if (stripos($rel, 'uploads/') === 0) {
        $rel = substr($rel, strlen('uploads/'));
    }
    return $rel;
}

// ---------- Destination is cerun_{id}/{ARCHIVE_SUBFOLDER}/{year}/<filename> ----------
function getRequestYear($requestDate) {
    $raw = trim((string)$requestDate);
    if ($raw === '') return null;

    $formats = [
        'Y-m-d H:i:s', 'Y-m-d',
        'Y/m/d H:i:s', 'Y/m/d',
        'd/m/Y H:i:s', 'd/m/Y',
        'd-m-Y H:i:s', 'd-m-Y',
        'd.m.Y H:i:s', 'd.m.Y'
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $raw);
        if ($dt instanceof DateTime) {
            $errors = DateTime::getLastErrors();
            if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                $year = (int)$dt->format('Y');
                if ($year >= 1900 && $year <= 2100) return $year;
            }
        }
    }

    // Final fallback for values such as a native MySQL DATE/TIMESTAMP.
    try {
        $dt = new DateTime($raw);
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

// Legacy helper: any archive path (without checking year).
function isAlreadyMoved($url, $cerunId, $ARCHIVE_SUBFOLDER) {
    $cerunFolderName = "cerun_{$cerunId}";
    return strpos($url, "/uploads/{$cerunFolderName}/{$ARCHIVE_SUBFOLDER}/") !== false;
}

// Only true when the file is already in the correct request_date year folder.
function isAlreadyInTargetYear($url, $cerunId, $ARCHIVE_SUBFOLDER, $requestYear) {
    if ($requestYear === null) return false;
    $cerunFolderName = "cerun_{$cerunId}";
    return strpos($url, "/uploads/{$cerunFolderName}/{$ARCHIVE_SUBFOLDER}/{$requestYear}/") !== false;
}



// ---------- Get all file entries for a cerun ----------
function getCerunFiles($db, $cerunId, $BASE_UPLOAD_PATH, $PATH_COLUMNS, $TABLE, $ARCHIVE_SUBFOLDER, $FOLDER_PREFIX, $PROJECTS_ID_COL, $ENABLE_FOLDER_DIAGNOSTIC = false) {
    $colsList = implode(',', $PATH_COLUMNS);
    static $scanCache = [];

    $stmt = $db->prepare("SELECT id, `$PROJECTS_ID_COL`, request_date, running_number, title, $colsList FROM `$TABLE` WHERE `$PROJECTS_ID_COL` = ?");
    $stmt->bind_param('i', $cerunId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $items = [];

    foreach ($rows as $row) {
        $hasFile = false;

        foreach ($PATH_COLUMNS as $col) {
            $raw = $row[$col] ?? '';
            $decoded = decodeArchivePaths($raw);
            $urls = $decoded['paths'];
            if (empty($urls)) continue;

            $hasFile = true;

            foreach ($urls as $url) {
                $filename = basename($url);
                $relSubPath = resolveRelSubPath($url);
                $requestDate = $row['request_date'] ?? '';
                $requestYear = getRequestYear($requestDate);

                // Legacy archive/<filename> and archive/{other-year}/<filename>
                // remain eligible. Only the correct archive/{requestYear}/ folder is "done".
                $alreadyMoved = isAlreadyInTargetYear($url, $cerunId, $ARCHIVE_SUBFOLDER, $requestYear);

                $oldFilePath = $BASE_UPLOAD_PATH . '/' . $relSubPath;
                $fileExists = file_exists($oldFilePath);

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

                        if ($ENABLE_FOLDER_DIAGNOSTIC) {
                            if (!isset($scanCache[$parentDir])) {
                                $allFiles = @scandir($parentDir) ?: [];
                                $capped = [];
                                $total = 0;

                                foreach ($allFiles as $f2) {
                                    if ($f2 === '.' || $f2 === '..') continue;
                                    $total++;
                                    if (count($capped) < 20) $capped[] = $f2;
                                }

                                $scanCache[$parentDir] = ['items' => $capped, 'total' => $total];
                            }

                            $folderContents = $scanCache[$parentDir]['items'];
                        }
                    }
                }

                $items[] = [
                    'record_id' => (int)$row['id'],
                    'col' => $col,
                    'url' => $url,
                    'filename' => $filename,
                    'rel_sub_path' => $relSubPath,
                    'running_number' => $row['running_number'] ?? '',
                    'request_date' => $requestDate,
                    'request_year' => $requestYear,
                    'title' => $row['title'] ?? '',
                    'already_moved' => $alreadyMoved,
                    'file_exists' => $alreadyMoved ? true : $fileExists,
                    'missing_reason' => $missingReason,
                    'folder_contents' => $folderContents,
                    'no_file' => false,
                    'was_json' => $decoded['was_json'],
                ];
            }
        }

        if (!$hasFile) {
            $items[] = [
                'record_id' => (int)$row['id'],
                'col' => '',
                'url' => '',
                'filename' => '',
                'rel_sub_path' => '',
                'running_number' => $row['running_number'] ?? '',
                'request_date' => $row['request_date'] ?? '',
                'request_year' => getRequestYear($row['request_date'] ?? ''),
                'title' => $row['title'] ?? '',
                'already_moved' => false,
                'file_exists' => false,
                'missing_reason' => '',
                'folder_contents' => [],
                'no_file' => true,
                'was_json' => false,
            ];
        }
    }

    return $items;
}

// ---------- Get available request years for a cerun ----------
function getCerunYears($db, $cerunId, $TABLE, $PROJECTS_ID_COL) {
    $stmt = $db->prepare(
        "SELECT request_date
         FROM `$TABLE`
         WHERE `$PROJECTS_ID_COL` = ?
           AND request_date IS NOT NULL
           AND request_date != ''
         ORDER BY request_date DESC"
    );
    $stmt->bind_param('i', $cerunId);
    $stmt->execute();

    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $years = [];

    foreach ($rows as $row) {
        $year = getRequestYear($row['request_date'] ?? '');
        if ($year !== null) $years[$year] = true;
    }

    $years = array_keys($years);
    rsort($years, SORT_NUMERIC);
    return $years;
}

// ---------- Detect duplicate running_number within a single cerun ----------
function findDuplicateRunningNumbers($db, $cerunId, $TABLE, $PROJECTS_ID_COL) {
    $stmt = $db->prepare("
        SELECT running_number, COUNT(*) AS total, GROUP_CONCAT(id ORDER BY id) AS ids
        FROM `$TABLE`
        WHERE `$PROJECTS_ID_COL` = ? AND running_number IS NOT NULL AND running_number != ''
        GROUP BY running_number
        HAVING COUNT(*) > 1
        ORDER BY running_number ASC
    ");
    $stmt->bind_param('i', $cerunId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ---------- Detect duplicate running_number across ALL cerun (or filtered) ----------
function findAllDuplicateRunningNumbers($db, $TABLE, $PROJECTS_ID_COL, $filterCerunId = null) {
    if ($filterCerunId !== null) {
        $stmt = $db->prepare("
            SELECT `$PROJECTS_ID_COL` AS cerun_id, running_number, COUNT(*) AS total, GROUP_CONCAT(id ORDER BY id) AS ids
            FROM `$TABLE`
            WHERE running_number IS NOT NULL AND running_number != '' AND `$PROJECTS_ID_COL` = ?
            GROUP BY `$PROJECTS_ID_COL`, running_number
            HAVING COUNT(*) > 1
            ORDER BY `$PROJECTS_ID_COL` ASC, running_number ASC
        ");
        $stmt->bind_param('i', $filterCerunId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    $sql = "
        SELECT `$PROJECTS_ID_COL` AS cerun_id, running_number, COUNT(*) AS total, GROUP_CONCAT(id ORDER BY id) AS ids
        FROM `$TABLE`
        WHERE running_number IS NOT NULL AND running_number != ''
        GROUP BY `$PROJECTS_ID_COL`, running_number
        HAVING COUNT(*) > 1
        ORDER BY `$PROJECTS_ID_COL` ASC, running_number ASC
    ";
    $res = $db->query($sql);
    return $res->fetch_all(MYSQLI_ASSOC);
}


// ---------- Core: migrate selected files ----------
function migrateSelectedFiles($db, $cerunId, $selectedKeys, $isLive, $BASE_UPLOAD_PATH, $BASE_URL_PREFIX, $PATH_COLUMNS, $TABLE, $ARCHIVE_SUBFOLDER, $FOLDER_PREFIX, $PROJECTS_ID_COL, $targetYear = null) {
    $keySet = array_flip((array)$selectedKeys);
    $colsList = implode(',', $PATH_COLUMNS);

    $cerunFolderName = "{$FOLDER_PREFIX}{$cerunId}";
    $cerunFolderPath = $BASE_UPLOAD_PATH . '/' . $cerunFolderName;

    $stmt = $db->prepare("SELECT id, `$PROJECTS_ID_COL`, request_date, $colsList FROM `$TABLE` WHERE `$PROJECTS_ID_COL` = ?");
    $stmt->bind_param('i', $cerunId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $log = [];
    $summary = ['moved' => 0, 'errors' => 0, 'skipped' => 0];

    foreach ($rows as $row) {
        $rowYear = getRequestYear($row['request_date'] ?? '');
        if ($targetYear !== null && $rowYear !== $targetYear) continue;

        $id = (int)$row['id'];
        $updates = [];

        foreach ($PATH_COLUMNS as $col) {
            $rawValue = $row[$col] ?? '';
            $decoded = decodeArchivePaths($rawValue);
            $urls = $decoded['paths'];
            if (empty($urls)) continue;

            $newUrls = [];

            foreach ($urls as $url) {
                $filename = basename($url);
                $relSubPath = resolveRelSubPath($url);
                $key = "{$id}:{$col}:{$filename}";

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

                if (isAlreadyInTargetYear($url, $cerunId, $ARCHIVE_SUBFOLDER, $requestYear)) {
                    $log[] = ['type' => 'skip', 'text' => "id={$id} ({$col}): {$filename} sudah berada di {$cerunFolderName}/{$ARCHIVE_SUBFOLDER}/{$requestYear}/."];
                    $newUrls[] = $url;
                    $summary['skipped']++;
                    continue;
                }

                $destSubPath = resolveDestSubPath($filename, $ARCHIVE_SUBFOLDER, $requestDate);
                $oldFilePath = $BASE_UPLOAD_PATH . '/' . $relSubPath;
                $newFilePath = $cerunFolderPath . '/' . $destSubPath;
                $newUrl = $BASE_URL_PREFIX . '/' . $cerunFolderName . '/' . $destSubPath;
                $newSubfolderDir = dirname($newFilePath);

                if (!file_exists($oldFilePath)) {
                    $log[] = ['type' => 'error', 'text' => "id={$id}: fail tidak jumpa: {$oldFilePath}"];
                    $newUrls[] = $url;
                    $summary['errors']++;
                    continue;
                }

                $log[] = ['type' => 'move', 'text' => "id={$id} ({$col}): {$relSubPath} → {$cerunFolderName}/{$destSubPath}"];

                if ($isLive) {
                    if (file_exists($newFilePath)) {
                        $log[] = ['type' => 'error', 'text' => "id={$id}: fail sasaran sudah wujud, tidak overwrite: {$newFilePath}"];
                        $newUrls[] = $url;
                        $summary['errors']++;
                        continue;
                    }

                    if (!is_dir($newSubfolderDir) && !mkdir($newSubfolderDir, 0755, true) && !is_dir($newSubfolderDir)) {
                        $log[] = ['type' => 'error', 'text' => "Gagal cipta folder: {$newSubfolderDir}"];
                        $newUrls[] = $url;
                        $summary['errors']++;
                        continue;
                    }

                    if (!rename($oldFilePath, $newFilePath)) {
                        $log[] = ['type' => 'error', 'text' => "Gagal move fail: {$oldFilePath}"];
                        $newUrls[] = $url;
                        $summary['errors']++;
                        continue;
                    }
                }

                $newUrls[] = $newUrl;
                $summary['moved']++;
            }

            $newRawValue = encodeArchivePaths($newUrls, $decoded['was_json']);
            if ($newRawValue !== $rawValue) $updates[$col] = $newRawValue;
        }

        if (!empty($updates)) {
            if ($isLive) {
                $setParts = $types = '';
                $values = [];

                foreach ($updates as $col => $val) {
                    $setParts .= ($setParts ? ', ' : '') . "`$col` = ?";
                    $types .= 's';
                    $values[] = $val;
                }

                $types .= 'i';
                $values[] = $id;

                $upd = $db->prepare("UPDATE `$TABLE` SET {$setParts} WHERE id = ?");
                $upd->bind_param($types, ...$values);
                $upd->execute();
                $log[] = ['type' => 'db', 'text' => "id={$id}: DB dikemaskini."];
            } else {
                foreach ($updates as $col => $val) {
                    $log[] = ['type' => 'db-preview', 'text' => "id={$id} {$col} akan jadi: {$val}"];
                }
            }
        }
    }

    return ['log' => $log, 'summary' => $summary, 'total_records' => count($rows)];
}

// ---------- Audit: scan records, find missing files (optional filter by cerun) ----------
function runAudit($db, $BASE_UPLOAD_PATH, $PATH_COLUMNS, $TABLE, $ARCHIVE_SUBFOLDER, $FOLDER_PREFIX, $PROJECTS_ID_COL, $filterCerunId = null) {
    $colsList = implode(',', $PATH_COLUMNS);

    if ($filterCerunId !== null) {
        $stmt = $db->prepare("SELECT id, `$PROJECTS_ID_COL`, request_date, running_number, title, $colsList FROM `$TABLE` WHERE `$PROJECTS_ID_COL` = ? ORDER BY id ASC");
        $stmt->bind_param('i', $filterCerunId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $res = $db->query("SELECT id, `$PROJECTS_ID_COL`, request_date, running_number, title, $colsList FROM `$TABLE` ORDER BY `$PROJECTS_ID_COL` ASC, id ASC");
        $rows = $res->fetch_all(MYSQLI_ASSOC);
    }

    $missing = [];
    $totalFiles = 0;
    $totalOk = 0;

    foreach ($rows as $row) {
        $cerunId = $row[$PROJECTS_ID_COL];

        foreach ($PATH_COLUMNS as $col) {
            $raw = $row[$col] ?? '';
            $decoded = decodeArchivePaths($raw);
            $urls = $decoded['paths'];
            if (empty($urls)) continue;

            foreach ($urls as $url) {
                $totalFiles++;

                $filename = basename($url);
                $relSubPath = resolveRelSubPath($url);
                $filePath = $BASE_UPLOAD_PATH . '/' . $relSubPath;

                if (file_exists($filePath)) {
                    $totalOk++;
                    continue;
                }

                $parentDir = $BASE_UPLOAD_PATH . '/' . dirname($relSubPath);

                if (!is_dir($BASE_UPLOAD_PATH)) {
                    $reason = 'BASE_UPLOAD_PATH tidak wujud';
                } elseif (!is_dir($parentDir)) {
                    $reason = 'Folder tidak wujud: ' . dirname($relSubPath);
                } else {
                    $reason = 'Fail tidak jumpa dalam folder: ' . dirname($relSubPath);
                }

                $missing[] = [
                    'id' => $row['id'],
                    'cerun_id' => $cerunId,
                    'running_number' => $row['running_number'] ?? '',
                    'request_date' => $row['request_date'] ?? '',
                    'title' => $row['title'] ?? '',
                    'col' => $col,
                    'filename' => $filename,
                    'db_path' => $relSubPath,
                    'checked_path' => $filePath,
                    'reason' => $reason,
                ];
            }
        }
    }

    return [
        'missing' => $missing,
        'total_files' => $totalFiles,
        'total_ok' => $totalOk,
        'total_missing' => count($missing),
    ];
}


// ---------- Delete selected files + DB records ----------
function deleteSelectedFiles($db, $cerunId, $selectedKeys, $BASE_UPLOAD_PATH, $PATH_COLUMNS, $TABLE, $PROJECTS_ID_COL) {
    $keySet = array_flip((array)$selectedKeys);
    $colsList = implode(',', $PATH_COLUMNS);

    $stmt = $db->prepare("SELECT id, `$PROJECTS_ID_COL`, $colsList FROM `$TABLE` WHERE `$PROJECTS_ID_COL` = ?");
    $stmt->bind_param('i', $cerunId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $log = [];
    $summary = [
        'deleted_files' => 0,
        'deleted_records' => 0,
        'updated_records' => 0,
        'errors' => 0,
        'skipped' => 0
    ];

    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $updates = [];
        $deleteRecord = true;
        $recordHadSelectedFile = false;

        foreach ($PATH_COLUMNS as $col) {
            $rawValue = $row[$col] ?? '';
            $decoded = decodeArchivePaths($rawValue);
            $urls = $decoded['paths'];
            if (empty($urls)) continue;

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
                $relSubPath = resolveRelSubPath($url);
                $filePath = $BASE_UPLOAD_PATH . '/' . $relSubPath;

                if (file_exists($filePath)) {
                    if (@unlink($filePath)) {
                        $log[] = ['type' => 'delete-file', 'text' => "id={$id} ({$col}): fail dipadam: {$filePath}"];
                        $summary['deleted_files']++;
                    } else {
                        $log[] = ['type' => 'error', 'text' => "id={$id}: gagal delete fail: {$filePath}"];
                        $summary['errors']++;
                        $newUrls[] = $url;
                        $deleteRecord = false;
                    }
                } else {
                    $log[] = ['type' => 'delete-missing', 'text' => "id={$id} ({$col}): fail tidak wujud, reference DB dibuang: {$filename}"];
                }
            }

            $newRawValue = encodeArchivePaths($newUrls, $decoded['was_json']);
            if ($newRawValue !== $rawValue) $updates[$col] = $newRawValue;

            if (!empty($newUrls)) $deleteRecord = false;
        }

        if (!$recordHadSelectedFile) continue;

        if ($deleteRecord) {
            $del = $db->prepare("DELETE FROM `$TABLE` WHERE id = ? AND `$PROJECTS_ID_COL` = ?");
            $del->bind_param('ii', $id, $cerunId);

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

            $types .= 'ii';
            $values[] = $id;
            $values[] = $cerunId;

            $upd = $db->prepare("UPDATE `$TABLE` SET {$setParts} WHERE id = ? AND `$PROJECTS_ID_COL` = ?");
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

// ---------- State ----------
$action        = $_POST['action'] ?? ($_GET['action'] ?? null);
$selectedCerun = (isset($_POST['cerun_id']) && $_POST['cerun_id'] !== '') ? (int)$_POST['cerun_id'] : null;
$migrateYear = isset($_POST['migrate_year']) && $_POST['migrate_year'] !== '' ? (int)$_POST['migrate_year'] : null;

if (!empty($_POST['selected_files'])) {
    $_SESSION['last_selected_files_cerun'] = $_POST['selected_files'];
    $_SESSION['last_selected_cerun']       = $selectedCerun;
}
$lastSelectedFiles = $_SESSION['last_selected_files_cerun'] ?? [];

$cerunFiles              = [];
$cerunYears              = [];
$result                  = null;
$auditResult             = null;
$auditCerunId            = null;
$errorMsg                = '';
$cerunsError              = '';
$showMigrateBtn          = false;
$duplicateRunningNumbers = [];
$dupAuditResult          = null;
$dupAuditCerunId         = null;

// Run delete
if ($action === 'delete' && $selectedCerun) {
    $selectedKeys = (array)($_POST['selected_files'] ?? []);

    if (!empty($selectedKeys)) {
        try {
            $result = deleteSelectedFiles(
                $db,
                $selectedCerun,
                $selectedKeys,
                $BASE_UPLOAD_PATH,
                $PATH_COLUMNS,
                $TABLE,
                $PROJECTS_ID_COL
            );
            $result['is_live'] = true;
            $result['selected_keys'] = $selectedKeys;

            unset($_SESSION['last_selected_files_cerun'], $_SESSION['last_selected_cerun']);

            $cerunFiles = getCerunFiles(
                $db,
                $selectedCerun,
                $BASE_UPLOAD_PATH,
                $PATH_COLUMNS,
                $TABLE,
                $ARCHIVE_SUBFOLDER,
                $FOLDER_PREFIX,
                $PROJECTS_ID_COL,
                $ENABLE_FOLDER_DIAGNOSTIC
            );
            $duplicateRunningNumbers = findDuplicateRunningNumbers($db, $selectedCerun, $TABLE, $PROJECTS_ID_COL);
            $cerunYears = getCerunYears($db, $selectedCerun, $TABLE, $PROJECTS_ID_COL);
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
        }
    } else {
        $errorMsg = 'Sila pilih sekurang-kurangnya satu fail untuk delete.';
    }
}

// Run audit
if ($action === 'audit') {
    $auditCerunId = (isset($_POST['audit_cerun_id']) && $_POST['audit_cerun_id'] !== '') ? (int)$_POST['audit_cerun_id'] : null;
    try {
        $auditResult = runAudit($db, $BASE_UPLOAD_PATH, $PATH_COLUMNS, $TABLE, $ARCHIVE_SUBFOLDER, $FOLDER_PREFIX, $PROJECTS_ID_COL, $auditCerunId);
    } catch (Exception $e) { $errorMsg = $e->getMessage(); }
}

// Run duplicate running_number audit
if ($action === 'audit_duplicates') {
    $dupAuditCerunId = (isset($_POST['dup_audit_cerun_id']) && $_POST['dup_audit_cerun_id'] !== '') ? (int)$_POST['dup_audit_cerun_id'] : null;
    try {
        $dupAuditResult = findAllDuplicateRunningNumbers($db, $TABLE, $PROJECTS_ID_COL, $dupAuditCerunId);
    } catch (Exception $e) { $errorMsg = $e->getMessage(); }
}

// Load cerun list
$cerunList = [];
try {
    $res = $db->query("SELECT DISTINCT `$PROJECTS_ID_COL` AS id FROM `$PROJECTS_TABLE` WHERE `$PROJECTS_ID_COL` IS NOT NULL ORDER BY `$PROJECTS_ID_COL` ASC");
    while ($p = $res->fetch_assoc()) $cerunList[] = $p;
} catch (Exception $e) { $cerunsError = $e->getMessage(); }

// Load files for selected cerun
if ($selectedCerun) {
    try {
        $cerunFiles              = getCerunFiles($db, $selectedCerun, $BASE_UPLOAD_PATH, $PATH_COLUMNS, $TABLE, $ARCHIVE_SUBFOLDER, $FOLDER_PREFIX, $PROJECTS_ID_COL, $ENABLE_FOLDER_DIAGNOSTIC);
        $duplicateRunningNumbers = findDuplicateRunningNumbers($db, $selectedCerun, $TABLE, $PROJECTS_ID_COL);
        $cerunYears              = getCerunYears($db, $selectedCerun, $TABLE, $PROJECTS_ID_COL);
    } catch (Exception $e) { $errorMsg = $e->getMessage(); }
}

// Run preview or migrate
if ($selectedCerun && in_array($action, ['preview', 'migrate'])) {
    $selectedKeys = (array)($_POST['selected_files'] ?? $lastSelectedFiles);
    if (!empty($selectedKeys)) {
        $isLive = ($action === 'migrate');
        try {
            $result                  = migrateSelectedFiles($db, $selectedCerun, $selectedKeys, $isLive, $BASE_UPLOAD_PATH, $BASE_URL_PREFIX, $PATH_COLUMNS, $TABLE, $ARCHIVE_SUBFOLDER, $FOLDER_PREFIX, $PROJECTS_ID_COL, $migrateYear);
            $result['is_live']       = $isLive;
            $result['selected_keys'] = $selectedKeys;
            if ($isLive) {
                unset($_SESSION['last_selected_files_cerun'], $_SESSION['last_selected_cerun']);
                $cerunFiles = getCerunFiles($db, $selectedCerun, $BASE_UPLOAD_PATH, $PATH_COLUMNS, $TABLE, $ARCHIVE_SUBFOLDER, $FOLDER_PREFIX, $PROJECTS_ID_COL, $ENABLE_FOLDER_DIAGNOSTIC);
                $cerunYears = getCerunYears($db, $selectedCerun, $TABLE, $PROJECTS_ID_COL);
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
<title>Document Migration Tool — Cerun | YEAR FIX FINAL</title>
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

<h2>Document Migration Tool — Cerun</h2>
<p class="sub">
    YEAR FIX FINAL — Migrate dokumen ke folder per-cerun (<?= htmlspecialchars($FOLDER_PREFIX) ?>&lt;id&gt;/<?= htmlspecialchars($ARCHIVE_SUBFOLDER) ?>/&lt;tahun request_date&gt;/) dan kemaskini path dalam DB (table: <code><?= htmlspecialchars($TABLE) ?></code>).
    &nbsp;|&nbsp; <a class="btn-logout" href="?logout=1">Logout</a>
</p>

<?php if (!empty($errorMsg)):    ?><p class="err">Error: <?= htmlspecialchars($errorMsg) ?></p><?php endif; ?>
<?php if (!empty($cerunsError)): ?><p class="err">Gagal load senarai cerun: <?= htmlspecialchars($cerunsError) ?></p><?php endif; ?>

<!-- ===== AUDIT BAR ===== -->
<div class="card" style="padding:14px 20px;background:#f0f9ff;border-color:#bae6fd;">
    <p style="margin-top:0;font-weight:600;color:#0369a1">🔍 Audit Fail</p>
    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="action" value="audit">
        <select name="audit_cerun_id">
            <option value="">-- Semua Cerun --</option>
            <?php foreach ($cerunList as $p): ?>
                <option value="<?= htmlspecialchars($p['id']) ?>" <?= ($auditCerunId == $p['id']) ? 'selected' : '' ?>>
                    Cerun ID: <?= htmlspecialchars($p['id']) ?>
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
        <?php if ($auditCerunId): ?>
            <span style="font-weight:normal;color:#6b7280;font-size:13px">— Cerun ID: <?= htmlspecialchars($auditCerunId) ?></span>
        <?php else: ?>
            <span style="font-weight:normal;color:#6b7280;font-size:13px">— Semua Cerun</span>
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
                    <th style="width:80px">Cerun ID</th>
                    <th>Running Number</th>
                    <th>Tarikh Surat</th>
                    <th>Title</th>
                    <th>Nama Fail</th>
                    <th>Sebab</th>
                </tr>
            </thead>
            <tbody>
            <?php
            usort($auditResult['missing'], fn($a, $b) => $a['id'] <=> $b['id']);
            foreach ($auditResult['missing'] as $m):
            ?>
                <tr style="background:#fef2f2">
                    <td style="text-align:center"><?= htmlspecialchars($m['id']) ?></td>
                    <td style="text-align:center"><?= htmlspecialchars($m['cerun_id']) ?></td>
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


<!-- ===== DUPLICATE RUNNING NUMBER AUDIT BAR ===== -->
<div class="card" style="padding:14px 20px;background:#fff7ed;border-color:#fed7aa;">
    <p style="margin-top:0;font-weight:600;color:#9a3412">🧬 Audit Running Number Duplicate</p>
    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="action" value="audit_duplicates">
        <select name="dup_audit_cerun_id">
            <option value="">-- Semua Cerun --</option>
            <?php foreach ($cerunList as $p): ?>
                <option value="<?= htmlspecialchars($p['id']) ?>" <?= ($dupAuditCerunId == $p['id']) ? 'selected' : '' ?>>
                    Cerun ID: <?= htmlspecialchars($p['id']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" style="background:#9a3412;color:white;padding:8px 14px;border:none;border-radius:4px;cursor:pointer;font-size:13px">
            🔎 Cari Duplicate
        </button>
        <span style="color:#6b7280;font-size:12px">Scan rekod dalam DB dan detect running_number yang berulang dalam cerun yang sama.</span>
    </form>
</div>

<?php if ($dupAuditResult !== null): ?>
<div class="card">
    <p style="margin-top:0;font-weight:600">📋 Keputusan Audit Duplicate
        <?php if ($dupAuditCerunId): ?>
            <span style="font-weight:normal;color:#6b7280;font-size:13px">— Cerun ID: <?= htmlspecialchars($dupAuditCerunId) ?></span>
        <?php else: ?>
            <span style="font-weight:normal;color:#6b7280;font-size:13px">— Semua Cerun</span>
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
                    <th style="width:80px">Cerun ID</th>
                    <th>Running Number</th>
                    <th style="width:100px">Bilangan</th>
                    <th>Record IDs</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($dupAuditResult as $dup): ?>
                <tr style="background:#fef2f2">
                    <td style="text-align:center"><?= htmlspecialchars($dup['cerun_id']) ?></td>
                    <td style="font-weight:600"><?= htmlspecialchars($dup['running_number']) ?></td>
                    <td style="text-align:center"><?= htmlspecialchars($dup['total']) ?></td>
                    <td style="color:#6b7280"><?= htmlspecialchars($dup['ids']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p style="font-size:12px;color:#6b7280;margin-top:8px">
            💡 Ini bermakna ada &gt;1 rekod berlainan yang kongsi running_number sama dalam cerun yang sama — kemungkinan input duplicate.
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>


<!-- ===== STEP 1: Pilih Cerun ===== -->
<div class="card">
    <p style="margin-top:0"><span class="step">1</span><strong>Pilih Cerun</strong></p>
    <form method="post">
        <input type="hidden" name="action" value="load_files">
        <select name="cerun_id" required>
            <option value="">-- Pilih Cerun --</option>
            <?php foreach ($cerunList as $p): ?>
                <option value="<?= htmlspecialchars($p['id']) ?>" <?= ($selectedCerun == $p['id']) ? 'selected' : '' ?>>
                    Cerun ID: <?= htmlspecialchars($p['id']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        &nbsp;
        <button type="submit" class="btn-primary">Muatkan Fail &rarr;</button>
    </form>
</div>


<!-- ===== Duplicate running_number warning for the selected cerun ===== -->
<?php if ($selectedCerun && !empty($duplicateRunningNumbers)): ?>
<div class="card" style="background:#fef2f2;border-color:#fecaca">
    <p style="margin-top:0;font-weight:600;color:#991b1b">
        ⚠️ Running Number Duplicate Dikesan dalam Cerun Ini (<?= count($duplicateRunningNumbers) ?>)
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
        💡 Semak rekod-rekod ini sebelum migrate — kemungkinan data duplicate/input silap.
    </p>
</div>
<?php endif; ?>


<!-- ===== STEP 2: Pilih Fail ===== -->
<?php if ($selectedCerun && !empty($cerunFiles)): ?>
<div class="card">
    <p style="margin-top:0"><span class="step">2</span><strong>Pilih Fail untuk Di-migrate &nbsp;<span style="color:#6b7280;font-weight:normal">(Cerun ID: <?= htmlspecialchars($selectedCerun) ?>)</span></strong></p>

    <?php
        $uniqueRecordIds  = array_unique(array_column($cerunFiles, 'record_id'));
        $totalUniqueRecs  = count($uniqueRecordIds);

        $statBersedia   = count(array_filter($cerunFiles, fn($f) => !$f['already_moved'] && $f['file_exists'] && !$f['no_file']));
        $statMoved      = count(array_filter($cerunFiles, fn($f) => $f['already_moved']));
        $statMissing    = count(array_filter($cerunFiles, fn($f) => !$f['already_moved'] && !$f['file_exists'] && !$f['no_file']));
        $statNoFile     = count(array_filter($cerunFiles, fn($f) => $f['no_file']));
        $totalFails     = $statBersedia + $statMoved + $statMissing;
        $missingReasons = array_count_values(array_column(
            array_filter($cerunFiles, fn($f) => !$f['already_moved'] && !$f['file_exists'] && !$f['no_file']),
            'missing_reason'
        ));

        $dupRunningNumberSet = array_flip(array_column($duplicateRunningNumbers, 'running_number'));
    ?>

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
        <input type="hidden" name="cerun_id" value="<?= htmlspecialchars($selectedCerun) ?>">

        <?php
        $serverYearCount = 0;
        if ($migrateYear !== null) {
            foreach ($cerunFiles as $sf) {
                if (!$sf['no_file'] && $sf['request_year'] !== null && (int)$sf['request_year'] === (int)$migrateYear && !$sf['already_moved']) {
                    $serverYearCount++;
                }
            }
        } else {
            foreach ($cerunFiles as $sf) {
                if (!$sf['no_file'] && $sf['request_year'] !== null && !$sf['already_moved']) {
                    $serverYearCount++;
                }
            }
        }
        ?>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px;padding:10px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;">
            <strong style="font-size:13px;color:#1e3a8a;">Tahun Migration:</strong>
            <select name="migrate_year" id="migrate-year" style="padding:7px 10px;font-size:13px;border:1px solid #93c5fd;border-radius:4px;" onchange="this.form.submit()">
                <option value="">-- Semua Tahun --</option>
                <?php foreach ($cerunYears as $yr): ?>
                    <option value="<?= htmlspecialchars($yr) ?>" <?= ($migrateYear == $yr) ? 'selected' : '' ?>><?= htmlspecialchars($yr) ?></option>
                <?php endforeach; ?>
            </select>
            <span style="font-size:12px;color:#6b7280;">Pilih tahun untuk select fail dan migrate tahun tersebut sahaja.</span>
            <span id="year-count" style="margin-left:auto;background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;border-radius:6px;padding:6px 10px;font-size:13px;font-weight:600;white-space:nowrap;">Jumlah <?= $migrateYear !== null ? "tahun <strong>" . htmlspecialchars($migrateYear) . "</strong>: <strong>" . $serverYearCount . "</strong> fail" : "semua tahun: <strong>" . $serverYearCount . "</strong> fail" ?></span>
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
            $grouped = [];
            foreach ($cerunFiles as $f) {
                if ($f['no_file']) continue;
                $grouped[$f['running_number']][] = $f;
            }
            $groupNo = 0;
            foreach ($grouped as $runningNum => $files):
                $groupNo++;
                $rowspan = count($files);
                $firstRow = true;
                $isDupGroup = isset($dupRunningNumberSet[$runningNum]);
                foreach ($files as $f):
                    $key       = "{$f['record_id']}:{$f['col']}:{$f['filename']}";
                    $checkedBySession = in_array($key, $lastSelectedFiles);
                    $checkedByYear = ($migrateYear !== null && $f['request_year'] !== null && $f['file_exists'] && !$f['already_moved'] && (int)$f['request_year'] === (int)$migrateYear);
                    $checked   = $checkedByYear || $checkedBySession;
                    $canSelect = $f['request_year'] !== null && $f['file_exists'] && !$f['already_moved'];

                    if ($f['request_year'] !== null) {
                        $destSubPath = resolveDestSubPath($f['filename'], $ARCHIVE_SUBFOLDER, $f['request_date']);
                        $destDisplay = "{$FOLDER_PREFIX}{$selectedCerun}/{$destSubPath}";
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
                        // Fail fizikal tak wujud: jangan benarkan selection.
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
                        $isLegacyArchive = isAlreadyMoved($f['url'], $selectedCerun, $ARCHIVE_SUBFOLDER);
                        if ($isLegacyArchive) {
                            $badge = "<span class='badge' style='background:#fef3c7;color:#92400e'>↻ Pernah migrate — boleh susun ikut tahun</span>";
                        } else {
                            $badge = "<span class='badge badge-ok'>✅ Bersedia</span>";
                        }
                    }

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
                               class="file-cb <?= $canSelect ? 'pending-cb' : '' ?>"
                               data-year="<?= htmlspecialchars((string)($f['request_year'] ?? '')) ?>"
                               data-file-exists="<?= $f['file_exists'] ? '1' : '0' ?>"
                               data-already-moved="<?= $f['already_moved'] ? '1' : '0' ?>"
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
    $noFileRecords = array_filter($cerunFiles, fn($f) => $f['no_file']);
    if (!empty($noFileRecords)):
    ?>
    <div style="margin-top:24px;border-top:2px solid #e5e7eb;padding-top:16px">
        <p style="margin:0 0 10px;font-weight:600;color:#6b7280">
            — Rekod Tanpa Fail <span style="font-weight:normal;font-size:13px">(<?= count($noFileRecords) ?> rekod — archive_file NULL atau kosong)</span>
        </p>
        <table style="width:auto;min-width:500px">
            <thead>
                <tr>
                    <th style="width:50px;text-align:center">No.</th>
                    <th style="width:60px;text-align:center">ID</th>
                    <th>Running Number</th>
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

<?php elseif ($selectedCerun && empty($cerunFiles)): ?>
<div class="card">
    <p style="color:#6b7280;margin:0">Tiada fail ditemui untuk Cerun ID <?= htmlspecialchars($selectedCerun) ?>.</p>
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


<script>
(function () {
    function getSelectedYear() {
        const el = document.getElementById('migrate-year');
        return el ? String(el.value || '').trim() : '';
    }

    function getCheckboxYear(cb) {
        return String(cb.getAttribute('data-year') || '').trim();
    }

    function isYearMatch(cb, year) {
        if (!year) return true;
        return getCheckboxYear(cb) === String(year).trim();
    }

    window.updateCount = function () {
        const year = getSelectedYear();
        const boxes = Array.from(document.querySelectorAll('input.file-cb'));
        const yearBoxes = boxes.filter(function (cb) {
            return !cb.disabled && isYearMatch(cb, year);
        });
        const checked = yearBoxes.filter(function (cb) { return cb.checked; });

        const label = document.getElementById('count-label');
        if (label) {
            label.textContent = year
                ? checked.length + ' / ' + yearBoxes.length + ' fail tahun ' + year + ' dipilih'
                : checked.length + ' / ' + yearBoxes.length + ' fail dipilih';
        }

        const yearCount = document.getElementById('year-count');
        if (yearCount) {
            yearCount.innerHTML = year
                ? 'Jumlah tahun <strong>' + year + '</strong>: <strong>' + yearBoxes.length + '</strong> fail'
                : 'Jumlah semua tahun: <strong>' + yearBoxes.length + '</strong> fail';
        }

        const master = document.getElementById('check-all');
        if (master) {
            master.indeterminate = checked.length > 0 && checked.length < yearBoxes.length;
            master.checked = yearBoxes.length > 0 && checked.length === yearBoxes.length;
        }
    };

    window.selectYear = function (year) {
        const selected = String(year || '').trim();
        const boxes = document.querySelectorAll('input.file-cb');
        boxes.forEach(function (cb) {
            if (cb.disabled) {
                cb.checked = false;
                return;
            }
            cb.checked = selected !== '' && isYearMatch(cb, selected);
        });
        window.updateCount();
    };

    window.selectAll = function (value) {
        const year = getSelectedYear();
        document.querySelectorAll('input.file-cb').forEach(function (cb) {
            if (!cb.disabled) cb.checked = !!value && isYearMatch(cb, year);
        });
        window.updateCount();
    };

    window.selectPending = function () {
        const year = getSelectedYear();
        document.querySelectorAll('input.file-cb').forEach(function (cb) {
            if (!cb.disabled) cb.checked = isYearMatch(cb, year);
        });
        window.updateCount();
    };

    window.toggleAll = function (master) {
        window.selectAll(master.checked);
    };

    window.validateSelection = function () {
        const checked = document.querySelectorAll('input.file-cb:not([disabled]):checked');
        if (!checked.length) {
            alert('Sila pilih sekurang-kurangnya satu fail.');
            return false;
        }
        return true;
    };

    window.confirmMigrate = function () {
        const year = getSelectedYear();
        return confirm(
            '⚠️ PASTI nak migrate fail yang dipilih untuk' +
            (year ? ' Tahun ' + year + '.' : ' Semua tahun.') +
            '\n\nTindakan ini akan MOVE fail sebenar dan update DB. Tindakan ini TIDAK boleh undo automatik.'
        );
    };

    function initYearSelection() {
        const select = document.getElementById('migrate-year');
        if (select && select.value) {
            window.selectYear(select.value);
        } else {
            window.updateCount();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initYearSelection);
    } else {
        initYearSelection();
    }
})();
</script>
</body>
</html>

<?php

/* =========================================
   SESSION / AUTH
========================================= */

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    http_response_code(403);
    die("Access denied.");
}

/*
 * IMPORTANT:
 * Release PHP session lock immediately.
 */
session_write_close();


/* =========================================
   GET FILE
========================================= */

$file = trim($_GET['file'] ?? '');

if ($file === '') {
    http_response_code(400);
    die("No file specified.");
}


/* ========================================= 
   NORMALIZE PATH 
========================================= */ 
 
$file = str_replace('\\', '/', $file); 
 
/* 
 * Remove leading slash. 
 */ 
$file = ltrim($file, '/'); 


/*
 * Remove "uploads/" prefix if it already exists.
 *
 * Example:
 * uploads/project_18/sst/file.pdf
 *
 * becomes:
 * project_18/sst/file.pdf
 *
 * because $upload_root already points to:
 * /uploads/
 */
if (stripos($file, 'uploads/') === 0) {
    $file = substr($file, strlen('uploads/'));
}
 
 
/* 
 * Prevent directory traversal. 
 */ 
if ( 
    strpos($file, '../') !== false || 
    strpos($file, '..\\') !== false || 
    $file === '..' 
) { 
    http_response_code(403); 
    die("Invalid file path."); 
}

/* =========================================
   UPLOAD ROOT
========================================= */

$upload_root = __DIR__ . '/uploads/';

if (!is_dir($upload_root)) {
    http_response_code(500);
    die("Upload directory not found.");
}


/* =========================================
   DIRECT PATH
========================================= */

/*
 * IMPORTANT:
 *
 * Letter In sends:
 *
 * project_13/Fin/2/document.pdf
 *
 * So try the exact relative path first.
 *
 * This avoids scanning the entire uploads folder.
 */

$path = $upload_root . $file;

if (!is_file($path)) {

    /*
     * Some existing Letter Out records may only
     * contain the filename.
     *
     * Try filename directly in uploads.
     */

    $filename_only = basename($file);

    $direct_filename_path = $upload_root . $filename_only;

    if (is_file($direct_filename_path)) {

        $path = $direct_filename_path;

    } else {

        $path = null;
    }
}


/* =========================================
   FALLBACK CACHE
========================================= */

if ($path === null) {

    $cache_file = $upload_root . '.file_index.json';

    $file_index = [];

    if (is_file($cache_file)) {

        $json = @file_get_contents($cache_file);

        if ($json !== false && $json !== '') {

            $decoded = json_decode($json, true);

            if (is_array($decoded)) {
                $file_index = $decoded;
            }
        }
    }


    /*
     * Try exact requested path from cache.
     */
    if (isset($file_index[$file]) && is_file($file_index[$file])) {

        $path = $file_index[$file];

    } else {

        /*
         * Try filename only from cache.
         */
        $filename_only = basename($file);

        if (
            isset($file_index[$filename_only]) &&
            is_file($file_index[$filename_only])
        ) {

            $path = $file_index[$filename_only];
        }
    }
}


/* =========================================
   LAST RESORT
   RECURSIVE SEARCH
========================================= */

if ($path === null) {

    $search_name = basename($file);

    try {

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $upload_root,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $fileInfo) {

            if (!$fileInfo->isFile()) {
                continue;
            }

            if ($fileInfo->getFilename() !== $search_name) {
                continue;
            }

            $path = $fileInfo->getPathname();

            break;
        }

    } catch (Throwable $e) {

        http_response_code(500);
        die("Unable to search upload directory.");
    }


    /*
     * Save found path to cache.
     */
    if ($path !== null) {

        $cache_file = $upload_root . '.file_index.json';

        $file_index = [];

        if (is_file($cache_file)) {

            $json = @file_get_contents($cache_file);

            if ($json !== false) {

                $decoded = json_decode($json, true);

                if (is_array($decoded)) {
                    $file_index = $decoded;
                }
            }
        }

        /*
         * Save both requested path and filename.
         */
        $file_index[$file] = $path;
        $file_index[basename($file)] = $path;

        @file_put_contents(
            $cache_file,
            json_encode(
                $file_index,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ),
            LOCK_EX
        );
    }
}


/* =========================================
   FILE NOT FOUND
========================================= */

if ($path === null || !is_file($path)) {

    http_response_code(404);
    die("File not found.");
}


/* =========================================
   SECURITY CHECK
========================================= */

$real_root = realpath($upload_root);
$real_path = realpath($path);

if ($real_root === false || $real_path === false) {

    http_response_code(404);
    die("File not found.");
}


/*
 * Make sure requested file is actually inside
 * the uploads directory.
 */
$root_prefix = rtrim($real_root, DIRECTORY_SEPARATOR)
             . DIRECTORY_SEPARATOR;

if (
    strpos($real_path, $root_prefix) !== 0
) {

    http_response_code(403);
    die("Invalid file location.");
}


/* =========================================
   FILE INFORMATION
========================================= */

$ext = strtolower(
    pathinfo($real_path, PATHINFO_EXTENSION)
);

$filesize = @filesize($real_path);

if ($filesize === false) {

    http_response_code(500);
    die("Unable to read file size.");
}


/* =========================================
   MIME TYPE
========================================= */

$mime = match ($ext) {

    'pdf'
        => 'application/pdf',

    'doc'
        => 'application/msword',

    'docx'
        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',

    'jpg',
    'jpeg'
        => 'image/jpeg',

    'png'
        => 'image/png',

    'gif'
        => 'image/gif',

    'webp'
        => 'image/webp',

    default
        => 'application/octet-stream',
};


/* =========================================
   CLEAR OUTPUT BUFFER
========================================= */

while (ob_get_level() > 0) {
    @ob_end_clean();
}


/* =========================================
   HEADERS
========================================= */

header(
    'Content-Type: ' . $mime
);

header(
    'Content-Disposition: inline; filename="' .
    str_replace('"', '', basename($real_path)) .
    '"'
);

header(
    'Content-Length: ' . $filesize
);

header(
    'Cache-Control: private, max-age=3600'
);

header(
    'Accept-Ranges: bytes'
);

header(
    'X-Content-Type-Options: nosniff'
);

header(
    'X-Accel-Buffering: no'
);


/* =========================================
   OUTPUT FILE
========================================= */

$handle = @fopen($real_path, 'rb');

if ($handle === false) {

    http_response_code(500);
    die("Unable to open file.");
}

while (!feof($handle)) {

    $buffer = fread($handle, 8192);

    if ($buffer === false) {
        break;
    }

    echo $buffer;

    flush();
}

fclose($handle);

exit;
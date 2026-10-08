<?php
// ✅ Connect to MySQL (with proper database selected)
$db = new mysqli('127.0.0.1', 'projectdocument', 'ProjectDocument26', 'projectdocument');

// ✅ Check connection
if ($db->connect_error) {
    die("❌ Connection failed: " . $db->connect_error);
}

// ✅ Set UTF8 encoding
$db->set_charset('utf8mb4');

// ✅ Optional alias for backward compatibility
$conn = $db;

/* CSRF TOKEN */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function verify_csrf_token(?string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

/* =========================================================
   PROJECT ACCESS CHECK (shared, used to fix IDOR everywhere)
   Returns true if the user may access the given project —
   either because their role has implicit access to everything,
   or because they are explicitly assigned via user_project.
   ========================================================= */
function user_can_access_project(mysqli $db, int $user_id, int $project_id, string $role): bool {
    $role = strtolower(trim($role));

    $has_global_access = in_array($role, [
        'client admin',
        'admin (qa/qc)',
        'management',
        'hra'
    ]);

    if ($has_global_access) return true;

    $stmt = $db->prepare("SELECT id FROM user_project WHERE user_id = ? AND project_id = ? LIMIT 1");
    $stmt->bind_param("ii", $user_id, $project_id);
    $stmt->execute();
    $stmt->store_result();
    $is_assigned = $stmt->num_rows > 0;
    $stmt->close();

    return $is_assigned;
}

function user_can_access_cerun(mysqli $db, int $user_id, int $cerun_id, string $role): bool {
    $role = strtolower(trim($role));
    $has_global_access = in_array($role, [
        'client admin',
        'management',
        'hra'
    ]);
    if ($has_global_access) return true;
    $stmt = $db->prepare("SELECT id FROM user_cerun WHERE user_id = ? AND cerun_id = ? LIMIT 1");
    $stmt->bind_param("ii", $user_id, $cerun_id);
    $stmt->execute();
    $stmt->store_result();
    $is_assigned = $stmt->num_rows > 0;
    $stmt->close();
    return $is_assigned;
}
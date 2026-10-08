<?php
session_start();

require_once 'include/msqli_connect.php';
require_once 'include/activity_logger.php';

$token = $_GET['token'] ?? '';
if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    die('Invalid token.');
}

$stmt = $db->prepare("SELECT id, username, expires_at, used FROM sso_tokens WHERE token = ?");
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result || $result['used'] || (int)$result['expires_at'] < time()) {
    die('Token expired or already used.');
}

$upd = $db->prepare("UPDATE sso_tokens SET used = 1 WHERE token = ?");
$upd->bind_param('s', $token);
$upd->execute();
$upd->close();

$username = $result['username'];

$stmt2 = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
$stmt2->bind_param('s', $username);
$stmt2->execute();
$res2 = $stmt2->get_result();

if ($res2->num_rows !== 1) {
    die('No matching PMS account for this user.');
}

$user = $res2->fetch_assoc();
$stmt2->close();

/* ===============================
   SECURITY
================================ */
session_regenerate_id(true);

/* ===============================
   SET SESSION (matches login.php exactly)
================================ */
$_SESSION['user_id']         = $user['id'];
$_SESSION['username']        = $user['username'];
$_SESSION['full_name']       = $user['full_name'];
$_SESSION['account_type']    = $user['account_type'];
$_SESSION['designation']     = $user['designation'];
$_SESSION['property_access'] = $user['property_access'];
$_SESSION['legal_access']    = $user['legal_access'];
$_SESSION['memo_access']     = $user['memo_access'] ?? 1;

/* ===============================
   CREATE ACTIVE USER SESSION
================================ */
$session_id = session_id();
$user_id    = (int)$user['id'];
$ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

$stmt3 = $db->prepare("DELETE FROM user_sessions WHERE session_id = ?");
if ($stmt3) {
    $stmt3->bind_param("s", $session_id);
    $stmt3->execute();
    $stmt3->close();
}

$stmt4 = $db->prepare("
    INSERT INTO user_sessions
    (user_id, session_id, ip_address, user_agent, last_activity, created_at, is_active)
    VALUES (?, ?, ?, ?, NOW(), NOW(), 1)
");
if ($stmt4) {
    $stmt4->bind_param("isss", $user_id, $session_id, $ip_address, $user_agent);
    $stmt4->execute();
    $stmt4->close();
}

/* ===============================
   AUDIT LOG
================================ */
logActivity($db, 'LOGIN', 'Authentication', null, 'User logged in via SSO from MDS');

/* ========================
   ROLE-BASED REDIRECT 
=========================== */
$role        = $user['account_type'];
$designation = $user['designation'] ?? '';

if (
    $role === 'Client Admin' ||
    $role === 'Management' ||
    $role === 'HRA' ||
    $role === 'Admin (QA/QC)' ||
    strtolower(trim($designation)) === 'contract executive'
) {
    header("Location: dashboard.php");
} else {
    header("Location: staff_dashboard.php");
}
exit;
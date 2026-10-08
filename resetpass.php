<?php
session_start();
$title = "Reset Password";
require_once 'include/msqli_connect.php';

// ----------------------------
// ADMIN-ONLY ACCESS
// ----------------------------
if (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'Client Admin') {
    die("Access denied. Only admins can access this page.");
}

// ----------------------------
// CSRF TOKEN
// ----------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ----------------------------
// FETCH ALL USERS
// ----------------------------
$usernames = [];
$result = $conn->query("SELECT username FROM users ORDER BY username ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $usernames[] = $row['username'];
    }
}

$msg = "";

// ----------------------------
// HANDLE FORM SUBMIT
// ----------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $selectedUsers = $_POST['username'] ?? [];
    $newpass = trim($_POST['new_password'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        die("Invalid CSRF token");
    }

    // Validation
    if (empty($selectedUsers) || $newpass === '') {
        $msg = "Please select user(s) and enter a new password.";
    } elseif (strlen($newpass) < 6) {
        $msg = "Password must be at least 6 characters long.";
    } else {
        $hash = password_hash($newpass, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
        $updated = 0;

        foreach ($selectedUsers as $user) {
            $stmt->bind_param("ss", $hash, $user);
            $stmt->execute();
            $updated += $stmt->affected_rows;

            // Optional: log the reset
            // $conn->query("INSERT INTO password_reset_log (admin_user, target_user, timestamp) VALUES ('{$_SESSION['username']}', '$user', NOW())");
        }

        $stmt->close();
        $msg = "$updated user(s) updated successfully! <a href='login.php'>Login now</a>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Project Management System</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{--accent:#007bff}
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;display:flex;justify-content:center;align-items:center;min-height:100vh}
.box{background:#fff;border:3px solid var(--accent);border-radius:20px;padding:40px;width:450px;text-align:center;box-shadow:0 4px 10px rgba(0,0,0,0.1)}
.msg{margin-bottom:12px;font-size:14px;color:#333}
button{background:var(--accent);color:#fff;padding:12px;font-size:16px;border:none;border-radius:8px;cursor:pointer;width:100%;margin-top:10px}
.user-list{max-height:200px;overflow-y:auto;text-align:left;border:1px solid #ccc;padding:10px;border-radius:8px;margin-bottom:10px}
.user-list label{display:block;margin-bottom:4px}
input[type="password"]{width:100%;padding:12px;margin-bottom:10px;border:2px solid var(--accent);border-radius:8px;font-size:16px}
</style>
<script>
// Select / deselect all
function toggle(source) {
  checkboxes = document.getElementsByName('username[]');
  for(var i=0, n=checkboxes.length;i<n;i++) {
    checkboxes[i].checked = source.checked;
  }
}
</script>
</head>
<body>
<div class="box">
<h1><?= htmlspecialchars($title) ?></h1>
<?php if ($msg): ?>
  <div class="msg"><?= $msg ?></div>
<?php endif; ?>
<form method="post" action="">
  <div class="user-list">
    <label><input type="checkbox" onClick="toggle(this)"> <strong>Select All</strong></label>
    <?php foreach ($usernames as $u): ?>
      <label><input type="checkbox" name="username[]" value="<?= htmlspecialchars($u) ?>"> <?= htmlspecialchars($u) ?></label>
    <?php endforeach; ?>
  </div>
  <input type="password" name="new_password" placeholder="New Password (min 6 chars)" required>
  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
  <button type="submit">Reset Password</button>
</form>
<a href="dashboard.php" style="display:block; text-align:center; margin-top:16px; color:#888; font-size:13px; text-decoration:none;">← Return to Dashboard</a>
</div>
</body>
</html>
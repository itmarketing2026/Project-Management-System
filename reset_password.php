<?php
session_start();

$title = "Reset Password";

// ---------- CONFIG ----------
require_once 'include/msqli_connect.php';
// ----------------------------

$msg = "";



if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $newpass  = trim($_POST['new_password']);

    if ($username !== '' && $newpass !== '') {
        $hash = password_hash($newpass, PASSWORD_BCRYPT);

        $sql = "UPDATE users SET password = ? WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $hash, $username);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $msg = "Password updated successfully! <a href='login.php'>Login now</a>";
        } else {
            $msg = "Failed to reset password. Username not found.";
        }

        $stmt->close();
    } else {
        $msg = "Please fill in all fields.";
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
    body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;display:flex;justify-content:center;align-items:center;height:100vh}
    .box{background:#fff;border:3px solid var(--accent);border-radius:20px;padding:40px;width:350px;text-align:center;box-shadow:0 4px 10px rgba(0,0,0,0.1)}
    input{width:100%;padding:12px;margin:10px 0;border:2px solid var(--accent);border-radius:8px;font-size:16px}
    button{background:var(--accent);color:#fff;padding:12px;font-size:16px;border:none;border-radius:8px;cursor:pointer;width:100%;margin-top:10px}
    .msg{margin-top:12px;font-size:14px;color:#333}
  </style>
</head>
<body>
  <div class="box">
    <h1><?php echo htmlspecialchars($title); ?></h1>
    <?php if ($msg): ?>
      <div class="msg"><?php echo $msg; ?></div>
    <?php endif; ?>
    <form method="post" action="">
      <input type="text" name="username" placeholder="Username" required>
      <input type="password" name="new_password" placeholder="New Password" required>
      <button type="submit">Reset Password</button>
    </form>
  </div>
</body>
</html>

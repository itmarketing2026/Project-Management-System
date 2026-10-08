<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'include/msqli_connect.php';

$title = "Staff Login";
$error = "";

// Handle login
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username !== '' && $password !== '') {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();

            if (password_verify($password, $row['password'])) {
                // ✅ Allow only Standard User or Manager
                if ($row['account_type'] === 'Standard User' || $row['account_type'] === 'Manager') {
                    // Save user info in session
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['full_name'] = $row['full_name'];
                    $_SESSION['account_type'] = $row['account_type'];

                    header("Location: staff_dashboard.php");
                    exit;
                } else {
                    $error = "Access restricted — only Standard User and Manager accounts can log in.";
                }
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }
        $stmt->close();
    } else {
        $error = "Please enter username and password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($title) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root {
  --accent: #007bff;
  --accent-hover: #0056b3;
}

body {
  margin: 0;
  font-family: Arial, Helvetica, sans-serif;
  background: #f3f4f6;
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
}

.login-box {
  background: #fff;
  border: 2px solid var(--accent);
  border-radius: 16px;
  padding: 40px 30px;
  width: 360px;
  text-align: center;
  box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}

h1 {
  margin-bottom: 25px;
  font-size: 26px;
  font-weight: bold;
}

form {
  display: flex;
  flex-direction: column;
  align-items: center;
}

input {
  width: 90%;
  padding: 12px;
  margin: 8px 0;
  border: 2px solid #ccc;
  border-radius: 8px;
  font-size: 16px;
  outline: none;
  text-align: center;
  transition: border 0.2s, box-shadow 0.2s;
}

input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 6px rgba(0,123,255,0.4);
}

button {
  width: 90%;
  background: var(--accent);
  color: #fff;
  padding: 12px;
  font-size: 16px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  margin-top: 10px;
  transition: background 0.2s, transform 0.1s;
}

button:hover {
  background: var(--accent-hover);
}

button:active {
  transform: scale(0.98);
}

.admin-btn {
  background: #6c757d;
  margin-top: 8px;
}

.admin-btn:hover {
  background: #5a6268;
}

.error {
  color: red;
  margin-bottom: 12px;
  font-size: 14px;
}

.link {
  margin-top: 14px;
  font-size: 13px;
}

.link a {
  color: var(--accent);
  text-decoration: none;
}

.link a:hover {
  text-decoration: underline;
}
</style>
</head>
<body>
<div class="login-box">
    <h1><?= htmlspecialchars($title) ?></h1>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="">
        <input type="text" name="username" placeholder="Email / Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>

    <!-- Login as Admin button -->
    <form action="login.php" method="get">
        <button type="submit" class="admin-btn">Login as Admin</button>
    </form>

    <div class="link">
        <a href="reset_password.php">Forgot password?</a>
    </div>
</div>
</body>
</html>

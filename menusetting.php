<?php
// ===== SECURITY SETTINGS (must be before session_start) =====
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// ONLY enable if using HTTPS
// ini_set('session.cookie_secure', 1);

session_start();

// ===== SECURITY HEADERS =====
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer-when-downgrade");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// ===== LOGIN CHECK =====
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

// ===== TITLE =====
$title = "Document Hub";

// ===== CSRF TOKEN (future-proof for forms) =====
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>

  <style>
    * {
      box-sizing: border-box;
      font-family: "Segoe UI", Roboto, Arial, sans-serif;
    }

    body {
      margin: 0;
      background-color: #f8fafc;
    }

    /* ===== TOP NAVBAR ===== */
    .navbar {
      background-color: #0066cc;
      color: white;
      padding: 10px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }

    .navbar-left {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .menu-icon {
      font-size: 22px;
      cursor: pointer;
      background: rgba(255,255,255,0.15);
      border-radius: 6px;
      padding: 6px 10px;
      transition: 0.2s;
    }

    .menu-icon:hover {
      background: rgba(255,255,255,0.3);
    }

    .navbar-title {
      font-size: 18px;
      font-weight: 600;
    }

    .signout-btn {
      background: #ff4d4d;
      color: white;
      padding: 8px 14px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 500;
      transition: 0.3s;
    }

    .signout-btn:hover {
      background: #cc0000;
    }

    /* ===== DROPDOWN MENU ===== */
    .menu-dropdown {
      position: absolute;
      top: 60px;
      left: 20px;
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      padding: 20px 30px;
      display: none;
      z-index: 999;
      width: 450px;
    }

    .menu-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
      gap: 20px;
      justify-items: center;
    }

    .menu-item {
      text-align: center;
      cursor: pointer;
      width: 90px;
      text-decoration: none;
      color: inherit;
    }

    .menu-icon-circle {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: #e9f2ff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      margin: 0 auto 10px;
      box-shadow: 0 3px 5px rgba(0,0,0,0.1);
    }

    .menu-item p {
      font-size: 14px;
      margin: 0;
      font-weight: 500;
    }

    /* ===== SUBMENU ===== */
    .has-submenu {
      position: relative;
    }

    .submenu {
      display: none;
      position: absolute;
      left: 50%;
      transform: translateX(-50%);
      width: 160px;
      background: white;
      border-radius: 8px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      padding: 5px 0;
      z-index: 1000;
    }

    .has-submenu:hover .submenu {
      display: block;
    }

    .submenu a {
      display: block;
      padding: 8px 15px;
      font-size: 14px;
      color: #333;
      text-decoration: none;
    }

    .submenu a:hover {
      background: #0066cc;
      color: white;
    }
  </style>
</head>

<body>

<!-- NAVBAR -->
<div class="navbar">
  <div class="navbar-left">
    <div class="menu-icon" id="menuBtn">&#9776;</div>
    <div class="navbar-title">
      <?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  </div>

  <a href="logout.php" class="signout-btn">Sign Out</a>
</div>

<!-- MENU DROPDOWN -->
<div class="menu-dropdown" id="menuDropdown">
  <div class="menu-grid">

    <a href="projectdashboard.php" class="menu-item">
      <div class="menu-icon-circle">🏗️</div>
      <p>Project Center</p>
    </a>

    <div class="menu-item has-submenu">
      <div class="menu-icon-circle">👥</div>
      <p>People</p>
      <div class="submenu">
        <a href="createacc.php">➕ Create Account</a>
        <a href="userlist.php">📋 User List</a>
      </div>
    </div>

    <a href="reports.php" class="menu-item">
      <div class="menu-icon-circle">📊</div>
      <p>Reports</p>
    </a>

    <div class="menu-item has-submenu">
      <div class="menu-icon-circle">📁</div>
      <p>Project</p>
      <div class="submenu">
        <a href="createproject.php">➕ Create Project</a>
        <a href="projectlist.php">📋 Project List</a>
      </div>
    </div>

    <a href="cerun_list.php" class="menu-item">
      <div class="menu-icon-circle">⛰️</div>
      <p>Cerun</p>
    </a>

    <a href="resetpass.php" class="menu-item">
      <div class="menu-icon-circle">🔓</div>
      <p>Reset Password</p>
    </a>

  </div>
</div>

<!-- SCRIPT -->
<script>
  const menuBtn = document.getElementById('menuBtn');
  const menuDropdown = document.getElementById('menuDropdown');

  let open = false;

  menuBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    open = !open;
    menuDropdown.style.display = open ? 'block' : 'none';
  });

  document.addEventListener('click', (e) => {
    if (!menuDropdown.contains(e.target) && !menuBtn.contains(e.target)) {
      menuDropdown.style.display = 'none';
      open = false;
    }
  });
</script>

</body>
</html>
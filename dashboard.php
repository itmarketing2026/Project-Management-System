<?php
session_start();
require_once 'include/session_timeout.php';
require_once 'include/msqli_connect.php';

$title = "Document Hub";

$role = $_SESSION['account_type'] ?? '';
$user_id = $_SESSION['user_id'];
$designation = $_SESSION['designation'] ?? '';

// check project
$projectCheck = $db->prepare("SELECT 1 FROM user_project WHERE user_id=? LIMIT 1");
$projectCheck->bind_param("i", $user_id);
$projectCheck->execute();
$hasProjects = $projectCheck->get_result()->num_rows > 0;
$projectCheck->close();

// 🔥 QAQC cerun only
$is_qaqc_cerun_only = (
    stripos($role, 'QA') !== false && !$hasProjects
);

// admin or Contract Executive designation
$can_see_legal = (
    strtolower($role) === 'client admin' ||
    ($_SESSION['legal_access'] ?? 0) == 1 ||
    strtolower(trim($designation)) === 'contract executive'  
);

// admin 
$can_see_property = (
    strtolower($role) === 'client admin' ||
    ($_SESSION['property_access'] ?? 0) == 1
);

// QAQC Admin & Client Admin only — controls visibility of
// "Project Center", "People", and "Reset Password" in the menu.
// Everyone else (including regular QAQC users, other roles) can
// still see/use "Reports" and the rest of the menu as normal.
$can_see_admin_menu = in_array(strtolower(trim($role)), [
    'client admin',
    'admin (qa/qc)'
]);

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Project Management System</title>
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
      position: relative;
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
      transition: background 0.2s;
    }

    .menu-icon:hover {
      background: rgba(255,255,255,0.3);
    }

    .navbar-title {
      font-size: 18px;
      font-weight: 600;
    }

    .navbar-right {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .signout-btn {
      background: #ff4d4d;
      color: white;
      padding: 8px 14px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 500;
      transition: background 0.3s;
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
      z-index: 100;
      width: 450px;
      animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
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
      transition: transform 0.2s;
      width: 90px;
      text-decoration: none;
    }

    .menu-item:hover {
      transform: translateY(-5px);
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
      color: #0066cc;
      margin: 0 auto 10px auto;
      box-shadow: 0 3px 5px rgba(0,0,0,0.1);
    }

    .menu-item p {
      font-size: 14px;
      color: #333;
      margin: 0;
      font-weight: 500;
    }

    .has-submenu {
  position: relative;
}

  .submenu {
    display: none; 
    margin-top: 1px; 
    width: 150px; 
    background: white; 
    border-radius: 8px; 
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
    padding: 5px 0; 
    position: absolute; 
    left: 50%; 
    transform: translateX(-50%); 
  }

  .has-submenu:hover .submenu {
    display: block;
  }

  .submenu a {
    display: flex; 
    align-items: center;
    padding: 8px 15px; 
    margin: 0; 
    font-size: 14px; 
    color: #333;
    text-decoration: none;
    transition: background 0.2s, color 0.2s;
    border-radius: 0; 
  }

  .submenu a:hover {
    background: #0066cc;
    color: #fff;
  }

    /* ===== MAIN DASHBOARD ===== */
  .main-content {
	  padding: 10px 20px;
	}

	.card-container {
		display: grid;
		grid-template-columns: repeat(3, 260px);
		justify-content: center;
		gap: 40px;
		text-align: center;
		padding-bottom: 40px;
	}
	  
    .card {
		  width: 260px;
		  background: white;
		  border-radius: 15px;
		  overflow: hidden;
		  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
		  transition: transform 0.2s, box-shadow 0.2s;
		  text-decoration: none;
		  color: inherit;

		}

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 18px rgba(0,0,0,0.15);
    }

    .card img {
      width: 100%;
      height: 180px;
      object-fit: cover;
    }

    .card p {
      font-size: 18px;
      font-weight: 600;
      color: #004085;
      margin: 15px 0;
	text-align: center; 
    }

    /* Legal card accent */
    .card.legal-card p {
      color: #5a3e00;
    }

    .card.legal-card {
      border: 2px solid #c8a84b;
      box-shadow: 0 4px 12px rgba(200,168,75,0.2);
    }

    .card.legal-card:hover {
      box-shadow: 0 8px 18px rgba(200,168,75,0.35);
    }

    footer {
      text-align: center;
      padding: 15px;
      background: #f1f3f6;
      font-size: 14px;
      color: #666;
    }
	  
	  .welcome-banner {
    margin: 30px;
    padding: 30px 40px;
    border-radius: 25px;
    background: linear-gradient(135deg, #1d5fe4, #2ea8ff);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.welcome-text h2 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
}

.welcome-text p {
    margin: 8px 0 15px;
    opacity: 0.9;
}

.role-badge {
    background: rgba(255,255,255,0.2);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
}

.welcome-icon {
    font-size: 40px;
    opacity: 0.9;
}
	  
	  .task-badge{
    display:inline-flex;
    align-items:center;
    gap:8px;
    margin-top:12px;
    padding:12px 22px;
    border-radius:50px;
    text-decoration:none;
    font-weight:700;
    font-size:15px;
    color:#fff;
    background:rgba(255,255,255,0.18);
    backdrop-filter:blur(10px);
    border:1px solid rgba(255,255,255,0.25);
    transition:.3s;
}

.task-badge:hover{
    transform:translateY(-2px);
    background:rgba(255,255,255,0.28);
    color:#fff;
}

.task-badge.has-task{
    background:#ffb100;
    color:#111;
    box-shadow:0 0 20px rgba(255,177,0,.45);
}

.task-count{
    background:rgba(0,0,0,.15);
    padding:3px 10px;
    border-radius:20px;
    font-size:13px;
}
	  
	  
  </style>
</head>
<body>

  <!-- NAVBAR -->
  <div class="navbar">
    <div class="navbar-left">
      <div class="menu-icon" id="menuBtn">&#9776;</div>
      <div class="navbar-title"><?php echo $title; ?></div>
    </div>
    <div class="navbar-right">
		
      <a href="logout.php" class="signout-btn">Sign Out</a>
    </div>
  </div>

  <!-- MENU DROPDOWN -->
  <div class="menu-dropdown" id="menuDropdown">
  <div class="menu-grid">

    <?php if ($can_see_admin_menu): ?>
    <a href="project_center.php" class="menu-item">
      <div class="menu-icon-circle">🏗️</div>
      <p>Project Center</p>
    </a>
	  
	  <a href="userlist.php" class="menu-item">
      <div class="menu-icon-circle">👥</div>
      <p>People</p>
    </a>
    <?php endif; ?>

    

    <a href="reports.php" class="menu-item">
      <div class="menu-icon-circle">📊</div>
      <p>Reports</p>
    </a>

    <?php if ($can_see_admin_menu): ?>
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
	<?php endif; ?>

  </div>
</div>

	<?php
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['account_type'] ?? '';
?>

<div class="welcome-banner">
    <div class="welcome-text">
        <h2>Welcome back, <?= htmlspecialchars($full_name) ?>!</h2>
<a href="mytask.php"
   class="task-badge <?= $total_task > 0 ? 'has-task' : '' ?>">

   🔔 <?= $total_task ?> Pending Task<?= $total_task > 1 ? 's' : '' ?>

</a>

    </div>

    <div class="welcome-icon">
        📋
    </div>
</div>
  <!-- MAIN CONTENT -->
 <div class="main-content">
  <div class="card-container">

  <?php if (!$is_qaqc_cerun_only): ?>
    <!-- INFRA -->
    <a href="infradashboard.php" class="card">
      <img src="images/siputeh.jpeg">
      <p>INFRA</p>
    </a>

    <!-- RTB -->
    <a href="rtbdashboard.php" class="card">
      <img src="images/papan1.jpeg">
      <p>RTB</p>
    </a>

    <!-- CERUN NORMAL -->
    <a href="gencerundashboard.php" class="card">
      <img src="images/cerunimage.jpeg">
      <p>CERUN</p>
    </a>
	  
	
  <?php endif; ?>

  <?php if ($is_qaqc_cerun_only): ?>
    <!-- CERUN AK (QAQC ONLY) -->
    <a href="cerundashboard.php" class="card">
      <img src="images/cerunimage.jpeg">
      <p>CERUN</p>
    </a>
  <?php endif; ?>

  <?php if ($can_see_legal): ?>
    <!-- LEGAL (Admin & Contract Executive only) -->
    <a href="legaldashboard.php" class="card">
      <img src="images/legal.jpg">
      <p>LEGAL</p>
    </a>
	  
  <?php endif; ?>
	  
	    <?php if ($can_see_property): ?>
    <!-- Property (Admin & Contract Executive only) -->
	  
	     <a href="property.php" class="card">
      <img src="images/property.png">
      <p>PROPERTY</p>
    </a>
  <?php endif; ?>
	  
	    
 


  </div>
</div>

  <script>
    const menuBtn = document.getElementById('menuBtn');
    const menuDropdown = document.getElementById('menuDropdown');
    let menuVisible = false;

    menuBtn.addEventListener('click', () => {
      menuVisible = !menuVisible;
      menuDropdown.style.display = menuVisible ? 'block' : 'none';
    });

    // Hide menu when click outside
    document.addEventListener('click', (e) => {
      if (!menuDropdown.contains(e.target) && !menuBtn.contains(e.target)) {
        menuDropdown.style.display = 'none';
        menuVisible = false;
      }
    });
  </script>

</body>
</html>
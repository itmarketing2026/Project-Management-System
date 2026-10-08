<?php
// include/topbar.php
$current_project_id = $_GET['project_id'] ?? 0;
$project_name = $project['project_name'] ?? 'Project Workspace';
?>

<style>
:root {
  --accent: #007bff;
  --accent-light: #00a2ff;
  --accent-hover: #0056b3;
}
.topbar, .topbar * { font-family: "Segoe UI", Arial, sans-serif; }
body { margin:0; padding:0; }
.topbar {
  width: 100%;
  background: linear-gradient(90deg, var(--accent), var(--accent-light));
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
  display: flex;
  justify-content: center;
  align-items: center;
  position: relative;
  padding: 14px 0;
  z-index: 999;
}
.back-btn {
  position: absolute;
  left: 20px;
  background: #fff;
  color: var(--accent);
  padding: 8px 16px;
  border-radius: 8px;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.3s ease;
  font-size: 15px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.back-btn:hover { background: var(--accent); color:#fff; transform:translateX(-2px); }
.nav-links { display:flex; gap:40px; align-items:center; }
.nav-links a {
  text-decoration:none;
  font-weight:bold;
  color:#fff;
  font-size:18px;
  position:relative;
  transition: all 0.25s ease;
  padding:6px 12px;
  border-radius:8px;
}
.nav-links a::after {
  content:'';
  position:absolute;
  left:50%;
  bottom:-6px;
  width:0%;
  height:3px;
  background:#fff;
  border-radius:2px;
  transform:translateX(-50%);
  transition: all 0.25s ease;
}
.nav-links a:hover::after { width:100%; }
.nav-links a.active { background: rgba(255,255,255,0.25); }
.nav-links a.active::after { width:100%; }
@media (max-width:768px) {
  .nav-links { gap:20px; }
  .nav-links a { font-size:16px; padding:6px 8px; }
  .back-btn { left:10px; padding:6px 12px; font-size:14px; }
}
</style>

<div class="topbar">
 
 <div class="nav-links">
    <a href="project_workspace.php?project_id=<?= $current_project_id ?>" class="<?= ($title == 'Project Workspace') ? 'active' : '' ?>">Workspace</a>
	  <a href="project_register_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
      class="<?= ($title == 'Project Register') ? 'active' : '' ?>">
     Project Register
    </a>
    <a href="letterin_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
      class="<?= ($title == 'Letters In') ? 'active' : '' ?>">
      Letter In
    </a>
    <a href="letterout_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
      class="<?= ($title == 'Letters Out') ? 'active' : '' ?>">
      Letter Out
    </a>
      <a href="eot.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
      class="<?= ($title == 'Extension of Time') ? 'active' : '' ?>">
     Extension of Time
    </a>
	  <a href="wr.php?project_id=<?= $current_project_id ?>"
       class="<?= ($title == 'Weekly Report') ? 'active' : '' ?>">
       Weekly Report
    </a>
	  

</div>
</div>

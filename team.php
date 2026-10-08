<?php
session_start();

// Secure check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}

$title = "Teams";
require_once 'include/msqli_connect.php';

// detect current page (for sidebar active)
$current_page = basename($_SERVER['PHP_SELF']);

$sql_users = "SELECT COUNT(*) as total FROM users";
$result_users = $db->query($sql_users);
$row_users = $result_users->fetch_assoc();
$total_users = $row_users['total'];

// get teams (project table)
$sql_team = "
SELECT 
    p.id, 
    p.project_name,
    COUNT(up.user_id) AS total_users
FROM project p
LEFT JOIN user_project up ON p.id = up.project_id
GROUP BY p.id
ORDER BY p.project_name ASC
";

$result_team = $db->query($sql_team);

// optional (for header count - boleh remove kalau tak nak)
$total_teams = ($result_team ? $result_team->num_rows : 0);


?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<title>Project Management System</title>

<style>
:root {
    --blue: #0066cc;
    --blue-light: #eef6ff;
    --bg: #f4f8fc;
    --white: #ffffff;
    --border: #e2e8f0;
    --text: #1e293b;
    --muted: #94a3b8;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: var(--bg);
    display: flex;
    flex-direction: column;
    height: 100vh;
}

/* HEADER */
.page-header {
    background: var(--blue);
    padding: 0 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 60px;
}

.page-header h1 {
    color: #fff;
    font-size: 18px;
}

.count-pill {
    background: rgba(255,255,255,0.2);
    color: #fff;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
}

/* LAYOUT */
.main-layout {
    display: flex;
    flex: 1;
}

/* CONTENT */
.wrapper {
    flex: 1;
    padding: 24px;
    overflow-y: auto;
}

/* TEAM GRID */
.team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
}

.team-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 18px;
    cursor: pointer;
    transition: 0.2s;
}

.team-card:hover {
    border-color: var(--blue);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}

.team-title {
    font-weight: 600;
    margin-bottom: 6px;
}

.team-sub {
    font-size: 12px;
    color: var(--muted);
}
	
	.team-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.team-badge {
    background: var(--blue-light);
    color: var(--blue);
    font-size: 11px;
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 600;
}
</style>

</head>

<body>

<header class="page-header">
    <div style="display:flex; gap:12px; align-items:center;">
        <a href="dashboard.php" style="color:white;">
            <i class="bi bi-grid-3x3-gap-fill"></i>
        </a>
        <h1>Document Hub</h1>
        <span class="count-pill"><?= $total_teams ?> Teams</span>
    </div>

    <i class="bi bi-person-circle" style="color:white; font-size:22px;"></i>
</header>

<div class="main-layout">

<?php require_once 'include/sidebar.php'; ?>

<main class="wrapper">

<div class="team-grid">

<?php if ($result_team && $result_team->num_rows > 0): ?>
    <?php while ($team = $result_team->fetch_assoc()): ?>

     <div class="team-card"
     onclick="window.location.href='team_detail.php?id=<?= $team['id'] ?>'">

    <div class="team-title">
        <?= htmlspecialchars($team['project_name']) ?>
    </div>

    <div class="team-sub">
        <?= $team['total_users'] ?> Members
    </div>

</div>

    <?php endwhile; ?>
<?php else: ?>
    <p>No teams found.</p>
<?php endif; ?>

</div>

</main>
</div>

</body>
</html>

<?php $db->close(); ?>
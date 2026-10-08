<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'include/msqli_connect.php';

$current_page = basename($_SERVER['PHP_SELF']);

if (!isset($_GET['id'])) {
    die("Invalid team.");
}
$team_id = (int) $_GET['id'];

/* ADD USER */
if (isset($_POST['add_user'])) {
    $user_id = (int) $_POST['user_id'];

    $check = $db->query("
        SELECT 1 FROM user_project 
        WHERE user_id = $user_id AND project_id = $team_id
    ");

    if ($check->num_rows == 0) {
        $db->query("
            INSERT INTO user_project (user_id, project_id)
            VALUES ($user_id, $team_id)
        ");
    }

    header("Location: team_detail.php?id=$team_id");
    exit();
}

/* REMOVE USER (ADMIN ONLY) */
if (isset($_POST['remove_user_id'])) {

    if ($_SESSION['account_type'] !== 'client admin') {
        die("Unauthorized");
    }

    $remove_id = (int) $_POST['remove_user_id'];

    $db->query("
        DELETE FROM user_project 
        WHERE user_id = $remove_id 
        AND project_id = $team_id
    ");

    header("Location: team_detail.php?id=$team_id");
    exit();
}

/* TEAM INFO */
$sql_team = "SELECT project_name FROM project WHERE id = $team_id";
$result_team = $db->query($sql_team);
$team = $result_team->fetch_assoc();

if (!$team) {
    die("Team not found.");
}

/* USERS IN TEAM */
$sql_users = "
SELECT u.id, u.full_name, u.account_type
FROM user_project up
JOIN users u ON up.user_id = u.id
WHERE up.project_id = $team_id
ORDER BY u.full_name ASC
";
$result_users = $db->query($sql_users);
$team_user_count = ($result_users ? $result_users->num_rows : 0);

/* TOTAL USERS (SIDEBAR) */
$sql_total = "SELECT COUNT(*) as total FROM users";
$res_total = $db->query($sql_total);
$row_total = $res_total->fetch_assoc();
$total_users = $row_total['total'];
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
    --white: #fff;
    --border: #e2e8f0;
    --text: #1e293b;
}

body {
    font-family: "Segoe UI";
    background: var(--bg);
    margin: 0;
    display: flex;
    flex-direction: column;
    height: 100vh;
}

.page-header {
    background: var(--blue);
    padding: 0 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 60px;
}

.page-header h1 {
    color: white;
    font-size: 18px;
}

.count-pill {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
}

.main-layout {
    display: flex;
    flex: 1;
}

.wrapper {
    flex: 1;
    padding: 24px;
    overflow-y: auto;
}

.card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.user-list {
    display: grid;
    gap: 10px;
}

.user-item {
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
}

.badge {
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 6px;
    background: #eef6ff;
    color: var(--blue);
}

.btn-delete {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    width: 30px;
    height: 30px;
    border-radius: 6px;
    cursor: pointer;
}

.add-btn {
    background: var(--blue);
    color: white;
    border: none;
    padding: 8px 14px;
    border-radius: 8px;
    cursor: pointer;
}

/* MODAL UPGRADE */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(4px);
    justify-content: center;
    align-items: center;
    z-index: 999;
}

.modal-content {
    background: #fff;
    padding: 24px;
    border-radius: 16px;
    width: 360px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    animation: fadeIn 0.2s ease;
}

.modal-content h3 {
    margin-bottom: 16px;
}

.modal-content select {
    width: 100%;
    padding: 12px;
    border-radius: 10px;
    border: 1.5px solid #e2e8f0;
    margin-bottom: 18px;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.btn-cancel {
    background: #f1f5f9;
    border: none;
    padding: 8px 14px;
    border-radius: 8px;
}

.btn-submit {
    background: var(--blue);
    color: white;
    border: none;
    padding: 8px 14px;
    border-radius: 8px;
}

@keyframes fadeIn {
    from { opacity:0; transform:translateY(10px); }
    to { opacity:1; transform:translateY(0); }
}

.back-btn {
    text-decoration: none;
    color: var(--blue);
    margin-bottom: 16px;
    display: inline-block;
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
        <span class="count-pill"><?= $total_users ?> Users</span>
    </div>
</header>

<div class="main-layout">

<?php require_once 'include/sidebar.php'; ?>

<main class="wrapper">

<a href="team.php" class="back-btn">
    ← Back to Teams
</a>

<div class="card">
    <h3><?= htmlspecialchars($team['project_name']) ?></h3>
    <p>Total Members: <?= $team_user_count ?></p>
</div>

<div style="display:flex; justify-content:space-between; margin-bottom:10px;">
    <h3>Team Members</h3>
    <button onclick="openModal()" class="add-btn">+ Add Member</button>
</div>

<div class="card">

<?php if ($team_user_count > 0): ?>
<div class="user-list">

<?php while ($user = $result_users->fetch_assoc()): ?>
<div class="user-item">

<div><?= htmlspecialchars($user['full_name']) ?></div>

<div style="display:flex; gap:8px;">
<div class="badge"><?= strtoupper($user['account_type']) ?></div>

<?php if ($_SESSION['account_type'] === 'client admin'): ?>
<form method="POST">
<input type="hidden" name="remove_user_id" value="<?= $user['id'] ?>">
<button class="btn-delete">✕</button>
</form>
<?php endif; ?>

</div>
</div>
<?php endwhile; ?>

</div>
<?php else: ?>
<p>No users in this team.</p>
<?php endif; ?>

</div>

</main>
</div>

<!-- MODAL -->
<div id="addModal" class="modal">
<div class="modal-content">

<h3>Add Member</h3>

<form method="POST">
<select name="user_id" required>
<option value="">Select user...</option>

<?php
$users = $db->query("SELECT id, full_name FROM users ORDER BY full_name ASC");
while ($u = $users->fetch_assoc()):
?>
<option value="<?= $u['id'] ?>">
<?= htmlspecialchars($u['full_name']) ?>
</option>
<?php endwhile; ?>

</select>

<div class="modal-actions">
<button type="button" onclick="closeModal()" class="btn-cancel">Cancel</button>
<button type="submit" name="add_user" class="btn-submit">Add</button>
</div>

</form>

</div>
</div>

<script>
function openModal(){ document.getElementById('addModal').style.display='flex'; }
function closeModal(){ document.getElementById('addModal').style.display='none'; }

window.onclick = function(e){
    const modal = document.getElementById('addModal');
    if(e.target === modal){ modal.style.display='none'; }
}
</script>

</body>
</html>

<?php $db->close(); ?>
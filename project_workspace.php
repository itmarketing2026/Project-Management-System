<?php
require_once 'include/msqli_connect.php';
require_once 'include/auth.php';

// Get project ID from URL
$project_id = $_GET['project_id'] ?? 0;
if (!$project_id) die("Project not specified.");

// Fetch project info
$stmt = $db->prepare("SELECT * FROM project WHERE id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) die("Project not found.");
$project = $result->fetch_assoc();
$stmt->close();

$title = $project['project_name'] . " Workspace"; // dynamic title
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Project Management System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#f3f4f6; font-family: Arial,sans-serif; padding:20px; }
h1 { color:#007bff; text-align:center; margin-bottom:20px; font-weight:800; }
.workspace { background:#fff; padding:30px; border-radius:20px; max-width:1200px; margin:auto; box-shadow:0 4px 10px rgba(0,0,0,0.15); }
.placeholder { text-align:center; color:#666; padding:40px; border:2px dashed #ccc; border-radius:12px; background:#fafafa; transition:all 0.3s ease; }
.placeholder:hover { background:#eef5ff; border-color:#007bff; transform:scale(1.01); }
.btn-section { margin-top:30px; display:flex; gap:10px; flex-wrap:wrap; justify-content:center; }
</style>
</head>
<body>

<?php include 'include/topbar.php'; ?>

<div class="workspace">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p class="text-center text-muted">This is your workspace for <strong><?= htmlspecialchars($project['project_name']) ?></strong>.</p>

    <div class="btn-section">
        <a href="add_letter_pending.php?project_id=<?= $project['id'] ?>" class="btn btn-primary">Add Pending Letter</a>
        <a href="view_letters.php?project_id=<?= $project['id'] ?>" class="btn btn-secondary">View Letters</a>
        <a href="project_dashboard.php" class="btn btn-outline-dark">Back to Dashboard</a>
    </div>

    <div class="placeholder mt-4">
        <h3>Workspace Area</h3>
        <p>Drag in forms, tables, or charts here for <?= htmlspecialchars($project['project_name']) ?>.</p>
    </div>
</div>

</body>
</html>

<?php
session_start();

// Secure check: if not logged in, redirect to login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}

// Optional: regenerate session every now and then for extra security
// session_regenerate_id(true);


$title = "Project Dashboard";
require_once 'include/msqli_connect.php';

/* Fetch all projects */
$result = $db->query("
    SELECT id, project_name, prefix, image
    FROM project
");

if (!$result) {
    die("Database Error: " . $db->error);
}

// Organize projects by company
$projects_by_company = [];
while ($row = $result->fetch_assoc()) {
    $company = explode('/', $row['prefix'])[0];
    $projects_by_company[$company][] = $row;
}
ksort($projects_by_company); // optional: alphabetical
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Project Management System</title>

<style>
    :root {
        --accent: #007bff;
        --frame-max: 1400px;
    }
    * { box-sizing: border-box; }

    body {
        margin: 0;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        background: #f3f4f6;
        font-family: Arial, sans-serif;
        padding: 24px;
    }

    h1 {
        margin: 8px 0 24px;
        font-size: 36px;
        font-weight: 800;
        color: var(--accent);
        text-align: center;
    }

    .top-bar {
        width: 100%;
        display: flex;
        justify-content: flex-start;
        margin-bottom: 12px;
    }

    .back-btn {
        background: var(--accent);
        color: #fff;
        padding: 8px 16px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        transition: background 0.2s;
    }

    .back-btn:hover { background: #0056b3; }

    .company-section {
        width: 100%;
        max-width: var(--frame-max);
        margin-bottom: 40px;
    }

    .company-header {
        
        font-size: 22px;
        font-weight: bold;
        padding: 10px 16px;
        border-radius: 8px 8px 0 0;
        margin-bottom: 10px;
    }

   .grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr); 
    gap: 10px;
}

  .box {
    text-align: center;
    border: none;
    border-radius: 15px;
    padding: 0;
    background: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08); /* ✨ soft shadow */
    transition: transform 0.2s, box-shadow 0.2s;
    overflow: hidden;
}

    .box:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        cursor: pointer;
    }

    .box img {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }

    .placeholder {
        width: 100%;
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
       font-size: 22px;
        font-weight: bold;
        color: #fff;
        background: linear-gradient(135deg, #007bff, #00c6ff);
        text-align: center;
    }

    .box-content {
        padding: 12px 10px;
    }

    .box-content p {
        margin: 5px 0 0;
        font-size: 15px;
        font-weight: bold;
        color: #333;
    }

    .prefix {
        font-size: 13px;
        color: #666;
        margin-bottom: 10px;
    }

    a { text-decoration: none; color: inherit; }

    .empty {
        text-align: center;
        color: #666;
        font-size: 18px;
        padding: 20px;
    }
</style>
</head>
<body>

<div class="top-bar">
    <a href="dashboard.php" class="back-btn">← Back</a>
</div>

<h1><?= htmlspecialchars($title) ?></h1>

<?php if (empty($projects_by_company)): ?>
    <div class="empty">No projects created yet.</div>
<?php else: ?>
    <?php foreach ($projects_by_company as $company => $projects): ?>
        <div class="company-section">
            <div class="company-header"><?= htmlspecialchars($company) ?></div>
            <div class="grid">
                <?php foreach ($projects as $row): ?>
                    <a href="letterin_list.php?project_id=<?= $row['id'] ?>">
                        <div class="box">
                            <?php if (!empty($row['image'])): ?>
                                <img src="<?= htmlspecialchars($row['image']) ?>" 
                                     alt="<?= htmlspecialchars($row['project_name']) ?>">
                            <?php else: ?>
                                <div class="placeholder">
                                    <?= htmlspecialchars($row['project_name']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="box-content">
                                <p><?= htmlspecialchars($row['project_name']) ?></p>
                                <div class="prefix"><?= htmlspecialchars($row['prefix']) ?></div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
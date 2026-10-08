<?php
session_start();

// Secure check: if not logged in, redirect to login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}

$title = "CERUN";
require_once 'include/msqli_connect.php';

$stmt = $db->prepare("
    SELECT id, project_name, prefix 
    FROM project 
    WHERE category = 'CERUN'
");
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Database Error: " . $db->error);
}

$projects_by_company = [];
while ($row = $result->fetch_assoc()) {
    $company = explode('/', $row['prefix'])[0];
    $projects_by_company[$company][] = $row;
}
ksort($projects_by_company); 
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
        margin-bottom: 10px;
        color: #333;
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr); 
        gap: 15px;
    }

    .box {
        text-align: center;
        border: none;
        border-radius: 15px;
        background: #fff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        transition: transform 0.2s, box-shadow 0.2s;
        overflow: hidden;
        height: 100%;
    }

    .box:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        cursor: pointer;
    }

    .placeholder {
        width: 100%;
        height: 120px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 15px;
        font-size: 18px;
        font-weight: bold;
        color: #fff;
        background: linear-gradient(135deg, #007bff, #00c6ff);
        text-align: center;
    }

    .special-box .placeholder {
        background: linear-gradient(135deg, #1e293b, #334155);
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
        margin-top: 5px;
    }

    a { text-decoration: none; color: inherit; }

    @media (max-width: 1100px) { .grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 700px) { .grid { grid-template-columns: repeat(1, 1fr); } }
</style>
</head>
<body>

<div class="top-bar">
    <a href="dashboard.php" class="back-btn">← Back</a>
</div>

<h1>CERUN</h1>

<div class="company-section">
    <div class="company-header">Region</div>
    <div class="grid">
        <a href="cerundashboard.php">
            <div class="box special-box">
                <div class="placeholder">CERUN AK</div>
                <div class="box-content">
                  
                    <div class="prefix"> </div>
                </div>
            </div>
        </a>
    </div>
</div>

<?php if (!empty($projects_by_company)): ?>
    <?php foreach ($projects_by_company as $company => $projects): ?>
        <div class="company-section">
            <div class="grid">
                <?php foreach ($projects as $row): ?>
                    <a href="projectregister.php?project_id=<?= $row['id'] ?>"
                       class="project-card"
                       data-name="<?= strtolower(htmlspecialchars($row['project_name'])) ?>"
                       data-prefix="<?= strtolower(htmlspecialchars($row['prefix'])) ?>">
                        <div class="box">
                            <div class="placeholder">
                                <?= htmlspecialchars($row['project_name']) ?>
                            </div>
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
<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}

$title = "Property";
require_once 'include/msqli_connect.php';

$stmt = $db->prepare("
    SELECT id, project_name 
    FROM project 
    WHERE category = 'Property'
");
$stmt->execute();
$result = $stmt->get_result();

$projects = [];
while ($row = $result->fetch_assoc()) {
    $projects[] = $row;
}
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
    margin: 8px 0 10px;
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
}

/* 🔎 SEARCH */
.search-box {
    width: 100%;
    max-width: 400px;
    margin-bottom: 20px;
}

.search-box input {
    width: 100%;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 15px;
}

/* GRID */
.company-section {
    width: 100%;
    max-width: var(--frame-max);
}

.grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
}

.box {
    text-align: center;
    border-radius: 15px;
    background: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
    overflow: hidden;
}

.box:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 15px rgba(0,0,0,0.15);
}

.placeholder {
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

.box-content { padding: 12px 10px; }

.prefix {
    font-size: 13px;
    color: #666;
}

a { text-decoration: none; color: inherit; }

.empty {
    text-align: center;
    color: #666;
    font-size: 18px;
    padding: 40px;
}

@media (max-width: 1100px) {
    .grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 700px) {
    .grid { grid-template-columns: repeat(1, 1fr); }
}
</style>
</head>

<body>

<div class="top-bar">
    <a href="dashboard.php" class="back-btn">← Back</a>
</div>

<h1>Property</h1>

<div class="search-box">
    <input
        type="text"
        id="searchInput"
        placeholder="Search project name or prefix..."
    >
</div>

<?php if (empty($projects)): ?>
    <div class="empty">No Property projects found in this category.</div>
<?php else: ?>
    <div class="company-section">
        <div class="grid" id="projectGrid">

            <?php foreach ($projects as $row): ?>
                <a href="infoproject.php?project_id=<?= $row['id'] ?>"
                   class="project-card"
                   data-name="<?= strtolower($row['project_name']) ?>"
                   data-prefix="<?= strtolower($row['prefix']) ?>">

                    <div class="box">
                        <div class="placeholder">
                            <?= htmlspecialchars($row['project_name']) ?>
                        </div>

                        <div class="box-content">
                            <p><?= htmlspecialchars($row['project_name']) ?></p>
                            <div class="prefix">
                                <?= htmlspecialchars($row['prefix']) ?>
                            </div>
                        </div>
                    </div>

                </a>
            <?php endforeach; ?>

        </div>
    </div>
<?php endif; ?>

<script>

const searchInput = document.getElementById('searchInput');
const cards = document.querySelectorAll('.project-card');

searchInput.addEventListener('keyup', function() {

    const keyword = this.value.toLowerCase();

    cards.forEach(card => {

        const name = card.dataset.name;
        const prefix = card.dataset.prefix;

        if (name.includes(keyword) || prefix.includes(keyword)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }

    });

});
</script>

</body>
</html>
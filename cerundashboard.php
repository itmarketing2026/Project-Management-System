<?php
session_start();
require_once 'include/msqli_connect.php';

$title = "Cerun Dashboard";
$role = $_SESSION['account_type'] ?? '';
$user_id = $_SESSION['user_id'];

// check project
$projectCheck = $db->prepare("SELECT 1 FROM user_project WHERE user_id=? LIMIT 1");
$projectCheck->bind_param("i", $user_id);
$projectCheck->execute();
$hasProjects = $projectCheck->get_result()->num_rows > 0;
$projectCheck->close();

// QAQC cerun only
$is_qaqc_cerun_only = (stripos($role, 'QA') !== false && !$hasProjects);

$home_link = $is_qaqc_cerun_only ? "dashboard.php" : "gencerundashboard.php";


// 🔥 QUERY WITH PENDING COUNT
$query = "
    SELECT 
        c.id, 
        c.region, 
        c.category,
        COUNT(l.id) as total_pending
    FROM cerun c
    LEFT JOIN letterin_cerun l 
        ON l.cerun_id = c.id 
        AND l.status = 'pending'
    GROUP BY c.id
    ORDER BY c.category, c.region ASC
";

$result = $db->query($query);

$regions = [];
$hq = null;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $category = $row['category'];

        if ($row['region'] == 'HQ') {
            $hq = [
                "name"    => "Headquarters (HQ)",
                "link"    => "cerun.php?cerun_id=" . $row['id'],
                "image"   => "images/flags/pintasutama.jpg",
                "pending" => $row['total_pending']
            ];
        } else {
            $regions[$category][] = [
                "name"    => $row['region'],
                "link"    => "cerun.php?cerun_id=" . $row['id'],
                "image"   => "images/flags/" . strtolower(str_replace(' ', '_', $row['region'])) . ".png",
                "pending" => $row['total_pending']
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($title) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body {
    font-family: Arial, sans-serif;
    background: #f3f4f6;
    margin: 0;
    padding: 30px;
}

.back-btn {
    display: inline-block;
    margin-bottom: 20px;
    background: #007bff;
    color: #fff;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: bold;
    text-decoration: none;
}

h1 {
    text-align: center;
    color: #007bff;
    font-size: 36px;
    margin-bottom: 30px;
}

.main-container {
    max-width: 1200px;
    margin: auto;
}

/* HQ */
.hq-section {
    text-align: center;
    margin-bottom: 40px;
}

.hq-card {
    position: relative;
    display: inline-block;
    width: 360px;
    padding: 15px;
    border-radius: 16px;
    text-decoration: none;
    color: #333;
    font-weight: bold;
    transition: 0.3s;
}

.hq-card:hover {
    transform: translateY(-5px);
}

.hq-card img {
    width: 100%;
    height: 150px;
    object-fit: contain;
    border-radius: 25px;
    border: 2px solid #007bff;
}

/* Regions */
.regions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 30px;
}

.region-column h2 {
    text-align: center;
    color: #007bff;
    margin-bottom: 20px;
    border-bottom: 2px solid #007bff;
}

/* Card */
.state-card {
    position: relative;
    display: block;
    text-decoration: none;
    padding: 12px;
    margin-bottom: 16px;
    border: 2px solid #007bff;
    border-radius: 14px;
    text-align: center;
    font-weight: bold;
    color: #333;
    transition: 0.2s;
    background: #fff;
}

.state-card:hover {
    background: #eef5ff;
    transform: scale(1.03);
}

.state-card img {
    width: 100%;
    height: 120px;
    object-fit: cover;
    border-radius: 10px;
    margin-bottom: 8px;
}

/* 🔥 Pending badge */
.pending-badge {
    position: absolute;
    top: 10px;
    right: 12px;
    background: #f59e0b;
    color: white;
    font-size: 12px;
    font-weight: 700;
    padding: 5px 10px;
    border-radius: 50px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}
</style>
</head>

<body>

<a href="<?= $home_link ?>" class="back-btn">← Back</a>
<h1><?= htmlspecialchars($title) ?></h1>

<div class="main-container">

    <!-- HQ -->
    <?php if ($hq): ?>
    <div class="hq-section">
        <a href="<?= $hq['link'] ?>" class="hq-card">

            <?php if ($hq['pending'] > 0): ?>
                <span class="pending-badge"><?= $hq['pending'] ?></span>
            <?php endif; ?>

            <img src="<?= $hq['image'] ?>">
            <br><?= htmlspecialchars($hq['name']) ?>
        </a>
    </div>
    <?php endif; ?>

    <!-- Regions -->
    <div class="regions">
        <?php foreach ($regions as $regionName => $states): ?>
            <div class="region-column">
                <h2><?= htmlspecialchars($regionName) ?></h2>

                <?php foreach ($states as $state): ?>
                    <a href="<?= $state['link'] ?>" class="state-card">

                        <?php if ($state['pending'] > 0): ?>
                            <span class="pending-badge"><?= $state['pending'] ?></span>
                        <?php endif; ?>

                        <img src="<?= $state['image'] ?>" 
                             onerror="this.src='images/flags/default.png';">

                        <?= htmlspecialchars($state['name']) ?>
                    </a>
                <?php endforeach; ?>

            </div>
        <?php endforeach; ?>
    </div>

</div>

</body>
</html>
<?php
session_start();
require_once 'include/msqli_connect.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? '';
$role      = $_SESSION['account_type'] ?? 'Standard User';

if ($role === 'Client Admin' || $role === 'Management' || $role === 'HRA') {
    header("Location: dashboard.php");
    exit;
}

$projectQuery = "
    SELECT p.* FROM project p
    JOIN user_project up ON p.id = up.project_id
    WHERE up.user_id = ?
    ORDER BY p.project_name ASC
";
$projectStmt = $db->prepare($projectQuery);
$projectStmt->bind_param("i", $user_id);
$projectStmt->execute();
$projects    = $projectStmt->get_result();
$hasProjects = $projects->num_rows > 0;
$projectStmt->close();

$cerunQuery = "
    SELECT c.id, c.region, c.category 
    FROM cerun c
    JOIN user_cerun uc ON c.id = uc.cerun_id
    WHERE uc.user_id = ?
    ORDER BY c.region ASC
";
$cerunStmt = $db->prepare($cerunQuery);
$cerunStmt->bind_param("i", $user_id);
$cerunStmt->execute();
$ceruns    = $cerunStmt->get_result();
$hasCerun  = $ceruns->num_rows > 0;

// Dynamic subtitle based on what's assigned
$subtitle = '';
if ($hasProjects && $hasCerun) {
    $subtitle = "Here are your assigned projects and regions.";
} elseif ($hasProjects) {
    $subtitle = "Here are your assigned projects.";
} elseif ($hasCerun) {
    $subtitle = "Here are your assigned regions.";
} else {
    $subtitle = "No assignments yet. Contact your administrator.";
}

$regionFlags = [
    'johor'           => 'images/flags/johor.png',
    'kedah'           => 'images/flags/kedah.png',
    'kelantan'        => 'images/flags/kelantan.png',
    'kuala_lumpur'    => 'images/flags/kuala_lumpur.png',
    'melaka'          => 'images/flags/melaka.png',
    'negeri_sembilan' => 'images/flags/negeri_sembilan.png',
    'pahang'          => 'images/flags/pahang.png',
    'perak'           => 'images/flags/perak.png',
    'perlis'          => 'images/flags/perlis.png',
    'pulau_pinang'    => 'images/flags/pulau_pinang.png',
    'selangor'        => 'images/flags/selangor.png',
    'terengganu'      => 'images/flags/terengganu.png',
    'hq'              => 'images/flags/pintasutama.jpg'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Project Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue: #0057ff;
            --blue-mid: #0066cc;
            --blue-dark: #003d99;
            --blue-glow: rgba(0, 87, 255, 0.15);
            --bg: #f0f4fb;
            --surface: #ffffff;
            --border: #e4eaf4;
            --text: #0f1c35;
            --muted: #7a8bab;
            --radius: 16px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* ===== TOPBAR ===== */
        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 40px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 12px rgba(0,0,0,0.06);
        }

        .topbar-brand {
            font-size: 17px;
            font-weight: 800;
            color: var(--blue);
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .topbar-brand-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--blue);
            display: inline-block;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .logout-btn {
            background: #fee2e2;
            color: #dc2626;
            padding: 7px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            border: 1px solid #fecaca;
            transition: background 0.2s;
        }

        .logout-btn:hover { background: #fecaca; }

        /* ===== MAIN WRAPPER ===== */
        .wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 48px 32px 80px;
        }

        /* ===== HERO BANNER ===== */
        .hero {
            background: linear-gradient(130deg, var(--blue) 0%, #0099ff 100%);
            border-radius: 24px;
            padding: 40px 48px;
            margin-bottom: 48px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(0, 87, 255, 0.25);
        }

        /* Decorative circles */
        .hero::before {
            content: '';
            position: absolute;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
            right: -80px;
            top: -100px;
            pointer-events: none;
        }

        .hero::after {
            content: '';
            position: absolute;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            right: 120px;
            bottom: -60px;
            pointer-events: none;
        }

        .hero-text h1 {
            color: #fff;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 6px;
            letter-spacing: -0.4px;
        }

        .hero-text p {
            color: rgba(255,255,255,0.75);
            font-size: 14px;
            font-weight: 500;
        }

        .hero-graphic {
            flex-shrink: 0;
            width: 90px;
            height: 90px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-graphic-ring {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.25);
            position: absolute;
            animation: spin 12s linear infinite;
        }

        .hero-graphic-ring::before {
            content: '';
            position: absolute;
            width: 10px;
            height: 10px;
            background: rgba(255,255,255,0.8);
            border-radius: 50%;
            top: -5px;
            left: 50%;
            transform: translateX(-50%);
        }

        .hero-graphic-inner {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: rgba(255,255,255,0.18);
            border: 1.5px solid rgba(255,255,255,0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            backdrop-filter: blur(4px);
            z-index: 1;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }

        .role-chip {
            display: inline-block;
            background: rgba(255,255,255,0.18);
            color: #fff;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-top: 10px;
            border: 1px solid rgba(255,255,255,0.25);
            letter-spacing: 0.3px;
        }

        /* ===== SECTION HEADER ===== */
        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 18px;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.3px;
        }

        .section-count {
            background: var(--blue-glow);
            color: var(--blue);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .section-divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 44px 0;
        }

        /* ===== GRID ===== */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
        }

        @media (max-width: 1100px) { .card-grid { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 860px)  { .card-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 620px)  { .card-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 400px)  { .card-grid { grid-template-columns: 1fr; } }

        /* ===== PROJECT CARD ===== */
        .card {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            animation: fadeUp 0.4s ease both;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 28px rgba(0, 87, 255, 0.12);
            border-color: #c2d4ff;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Stagger animation */
        .card:nth-child(1)  { animation-delay: 0.05s; }
        .card:nth-child(2)  { animation-delay: 0.10s; }
        .card:nth-child(3)  { animation-delay: 0.15s; }
        .card:nth-child(4)  { animation-delay: 0.20s; }
        .card:nth-child(5)  { animation-delay: 0.25s; }
        .card:nth-child(6)  { animation-delay: 0.30s; }
        .card:nth-child(7)  { animation-delay: 0.35s; }
        .card:nth-child(8)  { animation-delay: 0.40s; }
        .card:nth-child(9)  { animation-delay: 0.45s; }
        .card:nth-child(10) { animation-delay: 0.50s; }

        /* Project card top band */
        .card-band {
            height: 100px;
            background: linear-gradient(135deg, var(--blue) 0%, #0099ff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .card-band::before {
            content: '';
            position: absolute;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
            right: -20px;
            top: -20px;
        }

        .card-band::after {
            content: '';
            position: absolute;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
            left: -10px;
            bottom: -20px;
        }

        .card-band-icon {
            font-size: 36px;
            z-index: 1;
            filter: drop-shadow(0 2px 6px rgba(0,0,0,0.15));
        }

        /* Cerun card top image */
        .card-flag {
            height: 100px;
            background: #f8faff;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
        }

        .card-flag img {
            max-height: 70px;
            max-width: 100%;
            object-fit: contain;
        }

        /* Card body */
        .card-body {
            padding: 14px 16px 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .card-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
            line-height: 1.3;
        }

        .card-sub {
            font-size: 11px;
            color: var(--muted);
            font-weight: 500;
        }

        .card-tag {
            display: inline-block;
            background: #eef3ff;
            color: var(--blue);
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            width: fit-content;
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 14px;
        }

        .empty-state h3 {
            font-size: 17px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 6px;
        }

        .empty-state p {
            color: var(--muted);
            font-size: 14px;
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
    </style>
</head>
<body>

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-brand">
            <span class="topbar-brand-dot"></span>
            Document Hub
        </div>
        <div class="topbar-right">
            <a href="logout.php" class="logout-btn">Sign Out</a>
        </div>
    </div>

    <div class="wrapper">

        <!-- HERO -->
        <div class="hero">
            <div class="hero-text">
                <h1>Welcome back, <?= htmlspecialchars($full_name) ?>!</h1>
                <p><?= htmlspecialchars($subtitle) ?></p>
                
				<a href="mytask.php" class="task-badge">
    🔔 Pending Tasks
</a>
            </div>
            <div class="hero-graphic">
                <div class="hero-graphic-ring"></div>
                <div class="hero-graphic-inner">📋</div>
            </div>
        </div>

        <?php if (!$hasProjects && !$hasCerun): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>No Access Yet</h3>
                <p>No projects or regions have been assigned to your account.<br>Please contact your administrator.</p>
            </div>
        <?php endif; ?>

        <!-- PROJECTS -->
        <?php if ($hasProjects): ?>
            <div class="section-header">
                <h2>Projects</h2>
                <span class="section-count"><?= $projects->num_rows ?> assigned</span>
            </div>
            <div class="card-grid">
                <?php while ($p = $projects->fetch_assoc()): ?>
                <a href="projectregister.php?project_id=<?= $p['id'] ?>" class="card">
                    <div class="card-band">
                        <?php 
                            echo '📂'; 
                        ?>
                    </div>
                    <div class="card-body">
                        <div class="card-name"><?= htmlspecialchars($p['project_name']) ?></div>
                        <?php if (!empty($p['project_code'])): ?>
                            <div class="card-sub"><?= htmlspecialchars($p['project_code']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($p['category'])): ?>
                            <span class="card-tag"><?= htmlspecialchars($p['category']) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <?php if ($hasProjects && $hasCerun): ?>
            <hr class="section-divider">
        <?php endif; ?>

        <!-- CERUN -->
        <?php if ($hasCerun): ?>
            <div class="section-header">
                <h2>Regions (Cerun)</h2>
                <span class="section-count"><?= $ceruns->num_rows ?> assigned</span>
            </div>
            <div class="card-grid">
                <?php while ($c = $ceruns->fetch_assoc()):
                    $key  = strtolower(str_replace(' ', '_', $c['region']));
                    $flag = $regionFlags[$key] ?? 'images/flags/default.png';
                ?>
                <a href="cerun.php?cerun_id=<?= $c['id'] ?>" class="card">
                    <div class="card-flag">
                        <img src="<?= htmlspecialchars($flag) ?>" alt="<?= htmlspecialchars($c['region']) ?>">
                    </div>
                    <div class="card-body">
                        <?php if (!empty($c['category'])): ?>
                            <span class="card-tag"><?= htmlspecialchars($c['category']) ?></span>
                        <?php endif; ?>
                        <div class="card-name"><?= htmlspecialchars($c['region']) ?></div>
                    </div>
                </a>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>
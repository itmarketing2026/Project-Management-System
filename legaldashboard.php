<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}
require_once 'include/msqli_connect.php';

$current_user_id = (int) $_SESSION['user_id'];

/* =========================================================
   1. FETCH PROJECTS FLAGGED AS LEGAL
      Restricted projects (is_restricted = 1) are only
      included if the current user has an explicit grant
      in project_access.
   ========================================================= */
$stmt = $db->prepare("
    SELECT p.id, p.project_name, p.prefix
    FROM project p
    WHERE p.is_legal = 1
      AND (
            p.is_restricted = 0
            OR EXISTS (
                  SELECT 1
                  FROM user_project up
                  WHERE up.project_id = p.id
                    AND up.user_id = ?
            )
      )
    ORDER BY p.project_name ASC
");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$projects = [];
while ($row = $result->fetch_assoc()) {
    $projects[] = $row;
}
$stmt->close();

/* =========================================================
   2. FETCH PROJECTS WITH CATEGORY = LEGAL
      EXCLUDE is_legal = 1 TO AVOID DUPLICATE
      Same restriction rule applies.
   ========================================================= */
$stmt = $db->prepare("
    SELECT p.id, p.project_name, p.prefix
    FROM project p
    WHERE p.category = 'LEGAL'
      AND (p.is_legal IS NULL OR p.is_legal != 1)
      AND (
            p.is_restricted = 0
            OR EXISTS (
                  SELECT 1
                  FROM user_project up
                  WHERE up.project_id = p.id
                    AND up.user_id = ?
            )
      )
    ORDER BY p.project_name ASC
");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$legal_category_projects = [];
while ($row = $result->fetch_assoc()) {
    $legal_category_projects[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
/>
<title>Project Management System</title>
<style>
:root {
    --accent: #4f46e5;
    --accent-light: #818cf8;
    --bg: #f4f5f9;
    --card-bg: #ffffff;
    --text-dark: #1f2330;
    --text-muted: #767c8c;
    --frame-max: 1400px;
    --radius: 18px;
}
* {
    box-sizing: border-box;
}
body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: var(--bg);
    background-image:
        radial-gradient(
            circle at 10% 0%,
            rgba(99,102,241,0.10),
            transparent 40%
        ),
        radial-gradient(
            circle at 90% 10%,
            rgba(14,165,233,0.10),
            transparent 40%
        );
    font-family: 'Segoe UI', Arial, sans-serif;
    padding: 32px 24px 60px;
    color: var(--text-dark);
}
/* =========================================================
   TOP BAR
   ========================================================= */
.top-bar {
    width: 100%;
    max-width: var(--frame-max);
    display: flex;
    justify-content: flex-start;
    margin-bottom: 18px;
}
.back-btn {
    background: var(--card-bg);
    color: var(--accent);
    padding: 10px 18px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    box-shadow:
        0 2px 8px rgba(0,0,0,0.06);
    border: 1px solid #e7e8ee;
    transition: all 0.2s ease;
}
.back-btn:hover {
    background: var(--accent);
    color: #fff;
    transform: translateX(-2px);
}
/* =========================================================
   HEADER
   ========================================================= */
.header-block {
    text-align: center;
    margin-bottom: 28px;
}
h1 {
    margin: 0;
    font-size: 38px;
    font-weight: 800;
    letter-spacing: 1px;
    background:
        linear-gradient(
            135deg,
            var(--accent),
            #0ea5e9
        );
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.subtitle {
    margin-top: 6px;
    color: var(--text-muted);
    font-size: 15px;
}
/* =========================================================
   SEARCH
   ========================================================= */
.search-box {
    width: 100%;
    max-width: 420px;
    margin: 0 auto 32px;
    position: relative;
}
.search-box input {
    width: 100%;
    padding: 13px 18px 13px 42px;
    border-radius: 12px;
    border: 1px solid #e2e3ec;
    font-size: 15px;
    background: var(--card-bg);
    box-shadow:
        0 2px 10px rgba(0,0,0,0.05);
    transition:
        border-color 0.2s,
        box-shadow 0.2s;
}
.search-box input:focus {
    outline: none;
    border-color: var(--accent-light);
    box-shadow:
        0 0 0 4px rgba(99,102,241,0.12);
}
.search-box::before {
    content: "🔍";
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 14px;
    opacity: 0.55;
}
/* =========================================================
   CATEGORY SECTIONS
   ========================================================= */
.category-section {
    width: 100%;
    max-width: var(--frame-max);
    margin-bottom: 36px;
}
.category-title {
    font-size: 15px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--text-muted);
    margin: 0 0 14px 4px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e7e8ee;
}
/* =========================================================
   GRID
   ========================================================= */
.grid {
    display: grid;
    grid-template-columns:
        repeat(5, 1fr);
    gap: 20px;
    width: 100%;
    max-width: var(--frame-max);
}
/* =========================================================
   BOX
   ========================================================= */
.box {
    text-align: center;
    border-radius: var(--radius);
    background: var(--card-bg);
    box-shadow:
        0 4px 16px rgba(20,20,50,0.06);
    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease;
    overflow: hidden;
    border: 1px solid #eef0f5;
}
.box:hover {
    transform: translateY(-6px);
    box-shadow:
        0 14px 28px rgba(20,20,60,0.14);
}
/* =========================================================
   PLACEHOLDER
   ========================================================= */
.placeholder {
    height: 110px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 15px;
    font-size: 18px;
    font-weight: 700;
    color: #fff;
    text-shadow:
        0 1px 3px rgba(0,0,0,0.15);
    position: relative;
    overflow: hidden;
}
.placeholder::after {
    content: "";
    position: absolute;
    inset: 0;
    background:
        linear-gradient(
            135deg,
            rgba(255,255,255,0.18),
            transparent 60%
        );
}
/* =========================================================
   BOX CONTENT
   ========================================================= */
.box-content {
    padding: 14px 12px 18px;
}
.box-content p {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 4px;
}
.prefix {
    font-size: 12.5px;
    color: var(--text-muted);
    min-height: 16px;
}
a {
    text-decoration: none;
    color: inherit;
    display: block;
}
/* =========================================================
   EMPTY
   ========================================================= */
.empty {
    text-align: center;
    color: var(--text-muted);
    font-size: 17px;
    padding: 60px 20px;
    background: var(--card-bg);
    border-radius: var(--radius);
    max-width: 500px;
    box-shadow:
        0 4px 16px rgba(20,20,50,0.06);
}
/* =========================================================
   NO RESULTS
   ========================================================= */
.no-results {
    grid-column: 1 / -1;
    text-align: center;
    color: var(--text-muted);
    font-size: 15px;
    padding: 30px;
    display: none;
}
/* =========================================================
   RESPONSIVE
   ========================================================= */
@media (max-width: 1100px) {
    .grid {
        grid-template-columns:
            repeat(3, 1fr);
    }
}
@media (max-width: 700px) {
    .grid {
        grid-template-columns:
            repeat(2, 1fr);
    }
    h1 {
        font-size: 30px;
    }
}
@media (max-width: 480px) {
    .grid {
        grid-template-columns:
            repeat(1, 1fr);
    }
}
</style>
</head>
<body>
<!-- =====================================================
     TOP BAR
     ===================================================== -->
<div class="top-bar">
    <a
        href="dashboard.php"
        class="back-btn"
    >
        ← Back to Dashboard
    </a>
</div>
<!-- =====================================================
     HEADER
     ===================================================== -->
<div class="header-block">
    <h1>LEGAL</h1>
    <div class="subtitle">
        Projects flagged for legal review
    </div>
</div>
<?php if (empty($projects) && empty($legal_category_projects)): ?>
    <div class="empty">
        No projects have been assigned to Legal yet.
    </div>
<?php else: ?>
    <!-- =================================================
         SEARCH
         ================================================= -->
    <div class="search-box">
        <input
            type="text"
            id="searchInput"
            placeholder="Search project name or prefix..."
        >
    </div>
    <!-- =================================================
         PROJECT GRID
         ================================================= -->
    <div
        class="grid"
        id="projectGrid"
    >
        <!-- =================================================
             MYCC
             ================================================= -->
        <a
            href="MyCC.php"
            class="project-card"
            data-name="mycc"
            data-prefix="malaysia competition commission"
        >
            <div class="box">
                <div
                    class="placeholder"
                    style="
                        background:
                        linear-gradient(
                            135deg,
                            #10b981,
                            #34d399
                        );
                    "
                >
                    MyCC
                </div>
                <div class="box-content">
                    <p>
                        MyCC
                    </p>
                    <div class="prefix">
                        Malaysia Competition Commission
                    </div>
                </div>
            </div>
        </a>
        <!-- =================================================
             PROJECTS WHERE is_legal = 1
             (includes "The Weeknd" once it is inserted into
             the project table — it is only present in
             $projects above if the logged-in user has a row
             in user_project for it, or if it is not marked
             is_restricted)
             ================================================= -->
        <?php foreach ($projects as $row):
            // "The Weeknd" has its own dedicated page instead of
            // the generic legal_project.php route.
            $project_href = ($row['project_name'] === 'The Weeknd')
                ? 'weeknd.php'
                : 'legal_project.php?project_id=' . (int)$row['id'];
        ?>
            <a
                href="<?= htmlspecialchars($project_href) ?>"
                class="project-card"
                data-name="<?= strtolower(htmlspecialchars($row['project_name'])) ?>"
                data-prefix="<?= strtolower(htmlspecialchars($row['prefix'])) ?>"
            >
                <div class="box">
                    <div
                        class="placeholder"
                        style="
                            background:
                            linear-gradient(
                                135deg,
                                #007bff,
                                #00c6ff
                            );
                        "
                    >
                        <?= htmlspecialchars($row['project_name']) ?>
                    </div>
                    <div class="box-content">
                        <p>
                            <?= htmlspecialchars($row['project_name']) ?>
                        </p>
                        <div class="prefix">
                            <?= htmlspecialchars($row['prefix']) ?: '&nbsp;' ?>
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
        <!-- =================================================
             PROJECTS WHERE category = LEGAL
             BUT is_legal != 1
             ================================================= -->
        <?php foreach ($legal_category_projects as $row): ?>
            <a
                href="legalregister.php?project_id=<?= (int)$row['id'] ?>"
                class="project-card"
                data-name="<?= strtolower(htmlspecialchars($row['project_name'])) ?>"
                data-prefix="<?= strtolower(htmlspecialchars($row['prefix'])) ?>"
            >
                <div class="box">
                    <div
                        class="placeholder"
                        style="
                            background:
                            linear-gradient(
                                135deg,
                                #007bff,
                                #00c6ff
                            );
                        "
                    >
                        <?= htmlspecialchars($row['project_name']) ?>
                    </div>
                    <div class="box-content">
                        <p>
                            <?= htmlspecialchars($row['project_name']) ?>
                        </p>
                        <div class="prefix">
                            <?= htmlspecialchars($row['prefix']) ?: '&nbsp;' ?>
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
        <!-- =================================================
             NO RESULTS
             ================================================= -->
        <div
            class="no-results"
            id="noResults"
        >
            No matching projects found.
        </div>
    </div>
    <!-- =====================================================
         SEARCH SCRIPT
         ===================================================== -->
    <script>
        const searchInput =
            document.getElementById('searchInput');
        const cards =
            document.querySelectorAll('.project-card');
        const noResults =
            document.getElementById('noResults');
        searchInput.addEventListener(
            'keyup',
            function () {
                const keyword =
                    this.value.toLowerCase();
                let visibleCount = 0;
                cards.forEach(card => {
                    const match =
                        card.dataset.name.includes(keyword) ||
                        card.dataset.prefix.includes(keyword);
                    card.style.display =
                        match
                            ? 'block'
                            : 'none';
                    if (match) {
                        visibleCount++;
                    }
                });
                noResults.style.display =
                    visibleCount === 0
                        ? 'block'
                        : 'none';
            }
        );
    </script>
<?php endif; ?>
</body>
</html>
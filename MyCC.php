<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}

require_once 'include/msqli_connect.php';

$stmt = $db->prepare("
    SELECT id, company, company_abbreviation
    FROM mycc_company
    ORDER BY id ASC
");
$stmt->execute();
$result = $stmt->get_result();

$companies = [];
while ($row = $result->fetch_assoc()) {
    $companies[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Project Management System</title>

<style>
:root {
    --accent: #28a745;
    --accent2: #85e89d;
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
    margin: 8px 0 4px;
    font-size: 36px;
    font-weight: 800;
    color: #007bff;
    text-align: center;
}

.subtitle {
    font-size: 15px;
    color: #007bff;
    margin-bottom: 20px;
    text-align: center;
}

.top-bar {
    width: 100%;
    display: flex;
    justify-content: flex-start;
    margin-bottom: 12px;
}

.back-btn {
    background: #007bff;
    color: #fff;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
}

/* SEARCH */
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
.grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    width: 100%;
    max-width: var(--frame-max);
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
    background: linear-gradient(135deg, #28a745, #85e89d);
    text-align: center;
}

.box-content { padding: 12px 10px; }

.box-content p {
    font-size: 15px;
    font-weight: 600;
    color: #333;
    margin: 0 0 4px;
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
    <a href="legaldashboard.php" class="back-btn">← Back</a>
</div>

<h1>MyCC</h1>
<p class="subtitle">Malaysia Competition Commission</p>

<?php if (empty($companies)): ?>
    <div class="empty">No companies found.</div>
<?php else: ?>

    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search company name...">
    </div>

    <div class="grid" id="companyGrid">
		<?php foreach ($companies as $row): ?>
			<a href="mycc_company.php?company_id=<?= $row['id'] ?>"
			   class="company-card"
			   data-name="<?= strtolower(htmlspecialchars($row['company'] . ' ' . $row['company_abbreviation'])) ?>">

				<div class="box">
					<div class="placeholder">
						<?= htmlspecialchars($row['company_abbreviation']) ?>
					</div>

					<div class="box-content">
						<p>
							<?= htmlspecialchars($row['company_abbreviation']) ?>
						</p>

						<small style="color:#666; display:block; margin-top:5px;">
							<?= htmlspecialchars($row['company']) ?>
						</small>
					</div>
				</div>
			</a>
		<?php endforeach; ?>
	</div>

    <script>
        const searchInput = document.getElementById('searchInput');
        const cards = document.querySelectorAll('.company-card');

        searchInput.addEventListener('keyup', function () {
            const keyword = this.value.toLowerCase();
            cards.forEach(card => {
                const match = card.dataset.name.includes(keyword);
                card.style.display = match ? 'block' : 'none';
            });
        });
    </script>

<?php endif; ?>

</body>
</html>
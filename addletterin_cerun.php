<?php
session_start();
require_once 'include/msqli_connect.php';

// Session check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$cerun_id = intval($_GET['cerun_id'] ?? 0);
$type = $_GET['type'] ?? ''; // This will be "4 Year" or "6 Year"

if (!$cerun_id) {
    die("❌ Invalid region selected.");
}

if (!user_can_access_cerun($db, $_SESSION['user_id'], $cerun_id, $_SESSION['account_type'] ?? '')) {
    die("❌ You do not have permission to access this region.");
}

// Fetch Region Info
$stmt_region = $db->prepare("SELECT region FROM cerun WHERE id = ?");
$stmt_region->bind_param("i", $cerun_id);
$stmt_region->execute();
$region_name = $stmt_region->get_result()->fetch_assoc()['region'] ?? 'Unknown Region';
$stmt_region->close();

// Fetch Agencies
$agency_query = $db->prepare("SELECT agency_name FROM cerun_agency WHERE cerun_id = ? ORDER BY agency_name ASC");
$agency_query->bind_param("i", $cerun_id);
$agency_query->execute();
$result_agencies = $agency_query->get_result();

// Fetch Verifiers
$stmt_verifiers = $db->prepare("
    SELECT u.id AS user_id, u.full_name
    FROM verifier_cerun vc
    JOIN users u ON vc.user_id = u.id
    WHERE vc.cerun_id = ?
    ORDER BY u.full_name ASC
");
$stmt_verifiers->bind_param("i", $cerun_id);
$stmt_verifiers->execute();
$result_verifiers = $stmt_verifiers->get_result();

$title = "Add Letter In (Cerun)";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Project Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --accent: #007bff; --frame-max: 750px; }
        * { box-sizing: border-box; }
        body { 
            margin: 0; min-height: 100vh; display: flex; flex-direction: column; 
            align-items: center; background: #f3f4f6; padding: 24px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .header-group { text-align: center; margin-bottom: 24px; }
        h1 { margin: 8px 0 4px; font-size: 28px; font-weight: 800; color: var(--accent); }
        .region-sub { font-size: 18px; color: #666; font-weight: 600; }
        .back-btn { 
            align-self: flex-start; margin-bottom: 16px; background: var(--accent); 
            color: #fff; padding: 10px 18px; border-radius: 8px; font-weight: bold; 
            text-decoration: none; transition: background 0.2s; display: inline-flex; align-items: center; 
        }
        .form-container { 
            width: 100%; max-width: var(--frame-max); background: #fff; 
            border: 3px solid var(--accent); border-radius: 20px; padding: 35px; 
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05); 
        }
        form { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }
        label { font-weight: bold; margin-bottom: 6px; display: block; color: #333; font-size: 14px; }
        input, select, textarea { 
            width: 100%; padding: 11px; border: 2px solid #007bff; 
            border-radius: 8px; font-size: 15px; 
        }
        .btn-submit { 
            grid-column: span 2; background: var(--accent); color: #fff; 
            padding: 14px; font-size: 18px; border: none; border-radius: 10px; 
            cursor: pointer; font-weight: bold; margin-top: 10px; transition: 0.3s;
        }
        .btn-submit:hover { background: #0056b3; transform: translateY(-2px); }
    </style>
</head>
<body>

    <a href="cerun.php?cerun_id=<?= $cerun_id ?>&type=<?= urlencode($type) ?>&letter=in" class="back-btn">
        <i class="bi bi-arrow-left me-2"></i> Back
    </a>

    <div class="header-group">
        <h1><?= htmlspecialchars($title) ?></h1>
        <div class="region-sub"><?= htmlspecialchars($region_name) ?> (Cerun <?= htmlspecialchars($type) ?>)</div>
    </div>

    <div class="form-container">
        <form action="save_letterin_cerun.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="cerun_id" value="<?= $cerun_id ?>">
            <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
		
		

            <div class="full-width">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" placeholder="Subject of the letter" required>
            </div>

            <div>
                <label for="lot">Lot Number</label>
                <input type="text" id="lot" name="lot">
            </div>

            <div>
                <label for="ref_number">Reference Number</label>
                <input type="text" id="ref_number" name="ref_number" required>
            </div>

            <div>
                <label for="letter_date">Letter Date</label>
                <input type="date" id="letter_date" name="letter_date" required>
            </div>

            <div>
                <label for="receive_date">Date Received</label>
                <input type="date" id="receive_date" name="receive_date" required>
            </div>

            <div>
                <label for="receive_through">Received Through</label>
                <select id="receive_through" name="receive_through" required>
                    <option value="">-- Select Method --</option>
                    <option value="Email">Email</option>
                    <option value="Post">Post</option>
                    <option value="Hand">Hand</option>
                    <option value="Other">Other</option>
                </select>
            </div>

			<div>
    <label for="letter_from">From (Agency/Company)</label>

    <select id="letter_from" name="letter_from" required>
        <option value="">-- Select Agency --</option>

        <?php while($a = $result_agencies->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($a['agency_name']) ?>">
                <?= htmlspecialchars($a['agency_name']) ?>
            </option>
        <?php endwhile; ?>

        <option value="OTHER">+ Add New Agency</option>
    </select>

    <!-- 🔥 TERUS BAWAH DROPDOWN -->
    <input type="text" id="newAgencyBox" name="new_agency"
        placeholder="Enter new agency"
        style="margin-top:10px; display:none;">
</div>
        



            <div>
                <label for="verifier">Select Verifier</label>
                <select id="verifier" name="verifier" required>
                    <option value="">-- Choose Verifier --</option>
                    <?php while($v = $result_verifiers->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($v['full_name']) ?>">
                            <?= htmlspecialchars($v['full_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="full-width">
				<label for="document">Upload Document</label>
				<input type="file" id="document" name="document[]" multiple required style="padding:8px;">
				<small style="color:#666; font-size:13px; margin-top:4px; display:block;">
					You can select multiple files. Max size: <?= ini_get('upload_max_filesize') ?>
				</small>
			</div>

            <div class="full-width">
                <label for="remarks">Remarks (Optional)</label>
                <textarea id="remarks" name="remarks" rows="3"></textarea>
            </div>

            <button type="submit" class="btn-submit">
                <i class="bi bi-file-earmark-arrow-down me-2"></i> Submit Letter
            </button>
        </form>
    </div>
	
<script>
const dropdown = document.getElementById('letter_from');
const input = document.getElementById('newAgencyBox');

dropdown.addEventListener('change', function() {
    if (this.value === 'OTHER') {
        input.style.display = 'block';
        input.focus();
    } else {
        input.style.display = 'none';
        input.value = '';
    }
});
</script>

</body>
</html>
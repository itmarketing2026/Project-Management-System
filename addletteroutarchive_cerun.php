<?php
session_start();
require_once 'include/msqli_connect.php';

/* 🔐 Security check */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$cerun_id = isset($_GET['cerun_id']) ? (int) $_GET['cerun_id'] : 0;
$type = $_GET['type'] ?? ''; 
$year = $_GET['year'] ?? ''; 

if ($cerun_id <= 0) {
    die("❌ Invalid Cerun selection.");
}

// 1. Fetch Cerun Region Info
$stmt = $db->prepare("SELECT region FROM cerun WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $cerun_id);
$stmt->execute();
$cerun = $stmt->get_result()->fetch_assoc();

if (!$cerun) {
    die("❌ Cerun record not found.");
}

// 2. Fetch Agencies directly via PHP (Replacing the JS Fetch)
$agency_query = $db->prepare("SELECT agency_name FROM cerun_agency WHERE cerun_id = ? ORDER BY agency_name ASC");
$agency_query->bind_param("i", $cerun_id);
$agency_query->execute();
$agencies = $agency_query->get_result();

$title_page = "Add Archive Letter - " . htmlspecialchars($cerun['region']);
$createdBy = $_SESSION['full_name'] ?? 'Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Project Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --accent: #007bff; }
        body { background: #f4f7fa; padding: 40px 20px; font-family: 'Segoe UI', sans-serif; }
        .glass-card { 
            background: #fff; 
            max-width: 700px; 
            margin: 0 auto; 
            border-radius: 24px; 
            border: 3px solid var(--accent); 
            padding: 35px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.05);
        }
        .form-title { 
            color: var(--accent); 
            font-weight: 800; 
            border-bottom: 2px solid #eee; 
            padding-bottom: 15px; 
            margin-bottom: 25px; 
        }
        label { font-weight: 600; color: #475569; margin-bottom: 8px; }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px;
            border: 2px solid #e2e8f0;
        }
        .btn-submit {
            background: var(--accent);
            color: white;
            font-weight: 700;
            padding: 14px;
            border-radius: 12px;
            width: 100%;
            margin-top: 20px;
            border: none;
            transition: 0.3s;
        }
        .btn-submit:hover { background: #0056b3; transform: translateY(-2px); }
        .back-link { 
            background: var(--accent); 
            color: #fff; 
            text-decoration: none; 
            padding: 10px 18px; 
            border-radius: 10px; 
            font-size: 14px; 
            font-weight: 600; 
            display: inline-block;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <a href="cerun.php?cerun_id=<?= $cerun_id ?>&type=<?= $type ?>&year=<?= $year ?>" class="back-link">
        <i class="bi bi-arrow-left me-2"></i> Back
    </a>
    
    <div class="glass-card">
        <h2 class="form-title">Archive Letter Out</h2>
        
        <div class="mb-4 p-3 bg-light rounded-3">
            <h5 class="mb-0 text-dark"><?= htmlspecialchars($cerun['region']) ?> (Cerun <?= htmlspecialchars($type) ?> Tahun)</h5>
            <small class="text-uppercase fw-bold text-muted">Year: <?= htmlspecialchars($year) ?> </small>
        </div>

        <form action="save_letteroutarchive_cerun.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="cerun_id" value="<?= $cerun_id ?>">
            <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
            <input type="hidden" name="year_selected" value="<?= htmlspecialchars($year) ?>">
            <input type="hidden" name="created_by" value="<?= htmlspecialchars($createdBy) ?>">

            <div class="mb-4">
                <label>Manual Running Number</label>
                <input type="text" name="running_number" class="form-control" placeholder="e.g. JKR/<?= $year ?>/001" required>
            </div>

            <div class="mb-4">
                <label>Title</label>
                <input type="text" name="title" class="form-control" placeholder="Subject of the archived letter" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <label>Date of Letter</label>
                    <input type="date" name="request_date" class="form-control" required>
                </div>

                <div class="col-md-6 mb-4">
                    <label>Agency</label>
                    <select id="agency_select" name="agency_select" class="form-select" required>
						<option value="">-- Select Agency --</option>
						<?php if ($agencies->num_rows > 0): ?>
							<?php while($a = $agencies->fetch_assoc()): ?>
								<option value="<?= htmlspecialchars($a['agency_name']) ?>">
									<?= htmlspecialchars($a['agency_name']) ?>
								</option>
							<?php endwhile; ?>
						<?php endif; ?>
						<option value="__other__">Other</option>
					</select>
					<input type="hidden" name="agency" id="agency_final">

					<div id="other-agency-group" style="display: none; margin-top: 15px; background: #f8fafc; padding: 10px; border: 1px dashed #007bff; border-radius: 8px;">
						<label class="small text-primary">Enter Agency Name</label>
						<input type="text" id="agency_other" class="form-control" placeholder="Enter agency name">
					</div>
                </div>
            </div>

            <div class="mb-4">
				<label>Upload Document</label>
				<input type="file" name="archive_file[]" multiple class="form-control" required>
				<small class="text-muted">You can select multiple files. Max size: <?= ini_get('upload_max_filesize') ?></small>
			</div>

            <button type="submit" class="btn-submit shadow-sm">
                <i class="bi bi-archive me-2"></i> Save to Archive
            </button>
        </form>
    </div>
</div>
	<script>
    const agencySelect = document.getElementById('agency_select');
    const otherAgencyGroup = document.getElementById('other-agency-group');
    const agencyOtherInput = document.getElementById('agency_other');
    const agencyFinal = document.getElementById('agency_final');
    const form = document.querySelector('form');

    // 1. Show/Hide the manual input box
    agencySelect.addEventListener('change', function() {
        if (this.value === '__other__') {
            otherAgencyGroup.style.display = 'block';
            agencyOtherInput.required = true;
        } else {
            otherAgencyGroup.style.display = 'none';
            agencyOtherInput.required = false;
            agencyOtherInput.value = '';
        }
    });

    // 2. Before submitting, decide which value to send to PHP
    form.addEventListener('submit', function(e) {
        if (agencySelect.value === '__other__') {
            agencyFinal.value = agencyOtherInput.value.trim();
        } else {
            agencyFinal.value = agencySelect.value;
        }

        // Final validation
        if (agencyFinal.value === '') {
            e.preventDefault();
            alert('Please select or enter an agency.');
        }
    });
</script>

</body>
</html>
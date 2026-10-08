<?php
session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$cerun_id = isset($_GET['cerun_id']) ? (int) $_GET['cerun_id'] : 0;
$type = $_GET['type'] ?? ''; 
$year = $_GET['year'] ?? ''; 

if ($cerun_id <= 0) {
    die("❌ Invalid Cerun access.");
}

// 1. Fetch Cerun Info
$stmt = $db->prepare("SELECT region FROM cerun WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $cerun_id);
$stmt->execute();
$cerun = $stmt->get_result()->fetch_assoc();

if (!$cerun) {
    die("❌ Cerun record not found.");
}

$agency_query = $db->prepare("SELECT agency_name FROM cerun_agency WHERE cerun_id = ? ORDER BY agency_name ASC");
$agency_query->bind_param("i", $cerun_id);
$agency_query->execute();
$agencies = $agency_query->get_result();

$verifier_sql = "
    SELECT u.id, u.full_name 
    FROM verifier_cerun vc
    JOIN users u ON vc.user_id = u.id 
    WHERE vc.cerun_id = ? 
    ORDER BY u.full_name ASC
";
$verifier_query = $db->prepare($verifier_sql);
$verifier_query->bind_param("i", $cerun_id);
$verifier_query->execute();
$verifiers = $verifier_query->get_result();

$title_page = "Add Pending Letter - " . htmlspecialchars($cerun['region']);
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
        .back-link { background: #007bff; color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 10px; font-size: 14px; font-weight: 600; transition: 0.2s; }
        .back-btn:hover { background: var(--blue-dark); transform: translateY(-1px); }
    </style>
</head>
<body>

<div class="container">
    <a href="cerun.php?cerun_id=<?= $cerun_id ?>&type=<?= $type ?>&year=<?= $year ?>" class="back-link">
        <i class="bi bi-arrow-left me-2"></i> Back
    </a>
    
    <div class="glass-card">
        <h2 class="form-title">Request Letter Out</h2>
        
        <div class="mb-4 p-3 bg-light rounded-3">
            <h5 class="mb-0 text-dark"><?= htmlspecialchars($cerun['region']) ?> (Cerun <?= $type ?> Tahun)</h5>
            <small class="text-uppercase fw-bold text-muted"> <?= $year ?> </small>
        </div>

        <form action="save_letteroutpending_cerun.php" method="POST">
            <input type="hidden" name="cerun_id" value="<?= $cerun_id ?>">
            <input type="hidden" name="type" value="<?= $type ?>">
            <input type="hidden" name="year_selected" value="<?= $year ?>">

            <div class="mb-4">
                <label>Document Title</label>
                <input type="text" name="title" class="form-control" placeholder="Subject of the letter" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <label>Request Date</label>
                    <input type="date" name="request_date" value="<?= date('Y-m-d') ?>"  id="request_date" class="form-control" readonly>
					
                </div>

                <div class="col-md-6 mb-4">
                    <label>Select Verifier</label>
                    <select name="verifier_id" class="form-select" required>
                        <option value="">-- Choose Verifier --</option>
                        <?php while($v = $verifiers->fetch_assoc()): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['full_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="mb-4" id="backdate-reason-group" style="display:none;">
                <label>Reason for Backdated Request</label>
                <textarea name="backdate_reason" id="backdate_reason" rows="4"
                style="width:100%;padding:10px;border-radius:8px;border:1.5px solid #ccc;"
                placeholder="Please provide justification for requesting a backdated running number"></textarea>
            </div>

            <div class="mb-4">
				<label>Agency</label>
				<select name="agency_select" id="agencySelect" class="form-select" required>
					<option value="">Loading agencies...</option>
				</select>
			</div>

			<div class="mb-4" id="otherAgencyGroup" style="display:none; background:#f0f7ff; border:1.5px dashed #007bff; border-radius:10px; 				padding:16px;">
				<label style="color:#007bff;">Enter Agency Name</label>
				<input type="text" name="agency_other" id="agencyOther"
					class="form-control" placeholder="Enter agency name">
			</div>
			<input type="hidden" name="agency" id="agencyFinal">

						<button type="submit" class="btn-submit shadow-sm">
							<i class="bi bi-send-check me-2"></i> Generate Running Number
						</button>
					</form>
				</div>
			</div>

<script>
    const cerunId = <?= $cerun_id ?>;

    // Backdate check 
    const dateInput   = document.getElementById('request_date');
    const reasonGroup = document.getElementById('backdate-reason-group');
    const reasonText  = document.getElementById('backdate_reason');

    dateInput.addEventListener('change', () => {
        const selected = new Date(dateInput.value).setHours(0,0,0,0);
        const today    = new Date().setHours(0,0,0,0);
        if (selected < today) {
            reasonGroup.style.display = 'block';
            reasonText.required = true;
        } else {
            reasonGroup.style.display = 'none';
            reasonText.required = false;
        }
    });

    //  Load Agencies
    const agencySelect     = document.getElementById('agencySelect');
    const otherAgencyGroup = document.getElementById('otherAgencyGroup');
    const agencyOther      = document.getElementById('agencyOther');
    const agencyFinal      = document.getElementById('agencyFinal');

    fetch('get_cerun_agency.php?cerun_id=' + cerunId)
        .then(res => res.json())
        .then(data => {
            agencySelect.innerHTML = '';
            if (!Array.isArray(data) || data.length === 0) {
                agencySelect.innerHTML = '<option value="">No agency registered</option>';
            } else {
                agencySelect.innerHTML = '<option value="">-- Select Agency --</option>';
                data.forEach(a => {
                    agencySelect.innerHTML +=
                        `<option value="${a.agency_name}">${a.agency_name}</option>`;
                });
            }
            agencySelect.innerHTML += '<option value="__other__">Other</option>';
        })
        .catch(() => {
            agencySelect.innerHTML = '<option value="__other__">Other</option>';
        });

    //  Show/hide Other input 
    agencySelect.addEventListener('change', () => {
        if (agencySelect.value === '__other__') {
            otherAgencyGroup.style.display = 'block';
            agencyOther.required = true;
        } else {
            otherAgencyGroup.style.display = 'none';
            agencyOther.required = false;
            agencyOther.value = '';
        }
    });

    // On submit — resolve final agency value 
    document.querySelector('form').addEventListener('submit', function(e) {
        if (agencySelect.value === '__other__') {
            const customName = agencyOther.value.trim();
            if (!customName) {
                e.preventDefault();
                agencyOther.focus();
                alert('Please enter the agency name.');
                return;
            }
            agencyFinal.value = customName;
            agencySelect.required = false;
        } else {
            agencyFinal.value = agencySelect.value;
        }
    });
</script>

</body>
</html>
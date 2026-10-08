<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$title = "Add LOA";

require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$project_id = intval($_GET['project_id'] ?? 0);

if (!$project_id) {
    die("Invalid Project.");
}

if (!user_can_access_project($db, $_SESSION['user_id'], $project_id, $_SESSION['account_type'] ?? '')) {
    die("You do not have permission to view this project.");
}

$loa_id = intval($_GET['loa_id'] ?? 0);

$is_edit = false;

/* SUBCONTRACTOR LIST */
$subcon_list = [];
$sql = "
    SELECT DISTINCT
        subcon_name, subcon_address, subcon_phone
    FROM loa
    WHERE subcon_name IS NOT NULL AND subcon_name != ''
    ORDER BY subcon_name ASC
";
$result_subcon = $db->query($sql);
while ($row_subcon = $result_subcon->fetch_assoc()) {
    $subcon_list[] = $row_subcon;
}


/* DEFAULT VALUES - SUBCONTRACTOR */
$subcon_name      = '';
$subcon_address   = '';
$subcon_phone     = '';
$awarded_amount   = '';
$start_date       = '';
$completion_date  = '';
$duration_month   = '';
$lad_amount       = '';
$dlp_month        = '';
$attachment       = '';
$vo_type          = '';
$vo_amount        = '';
$new_award_amount = '';
$new_award_attachment = '';


/* FETCH DATA IF EDIT */
if ($loa_id > 0) {

    $is_edit = true;

    $stmt = $db->prepare("SELECT * FROM loa WHERE id = ?");
    $stmt->bind_param("i", $loa_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $row = $result->fetch_assoc();

        // Subcontractor
        $subcon_name      = $row['subcon_name'];
        $subcon_address   = $row['subcon_address'];
        $subcon_phone     = $row['subcon_phone'];
        $awarded_amount   = $row['awarded_amount'];
        $start_date       = $row['start_date'];
        $completion_date  = $row['completion_date'];
        $duration_month   = $row['duration_month'];
        $lad_amount       = $row['lad_amount'];
        $dlp_month        = $row['dlp_month'];
        $attachment       = $row['attachment'];
        $vo_type          = $row['vo_type'] ?? '';
        $vo_amount        = $row['vo_amount'] ?? '';
        $new_award_amount = $row['new_award_amount'] ?? '';
		$new_award_attachment = $row['new_award_attachment'] ?? '';

    }
}

/* SAVE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ---------- Subcontractor ---------- */
    $subcon_name     = trim($_POST['subcon_name'] ?? '');
    $subcon_address  = trim($_POST['subcon_address'] ?? '');
    $subcon_phone    = trim($_POST['subcon_phone'] ?? '');
    $awarded_amount  = $_POST['awarded_amount'] !== '' ? floatval($_POST['awarded_amount']) : 0;
    $start_date      = trim($_POST['start_date'] ?? '') ?: null;
    $completion_date = trim($_POST['completion_date'] ?? '') ?: null;
    $lad_amount      = $_POST['lad_amount'] !== '' ? floatval($_POST['lad_amount']) : 0;
    $dlp_month       = $_POST['dlp_month'] !== '' ? intval($_POST['dlp_month']) : 0;
    $vo_type         = trim($_POST['vo_type'] ?? '');
    $vo_amount       = $_POST['vo_amount'] !== '' ? floatval($_POST['vo_amount']) : null;

    /* NEW AWARD - SUBCONTRACTOR */
    if ($vo_type === 'additional') {
        $new_award_amount = $awarded_amount + $vo_amount;
    } elseif ($vo_type === 'substraction') {
        $new_award_amount = $awarded_amount - $vo_amount;
    } else {
        $new_award_amount = $awarded_amount;
    }

    /* VALIDATE DATE (Subcontractor required) */
    if (empty($start_date) || empty($completion_date)) {
        die("Start Date and Completion Date required.");
    }

    /* AUTO CALCULATE DURATION - SUBCONTRACTOR */
    $start = new DateTime($start_date);
    $end   = new DateTime($completion_date);
    $interval = $start->diff($end);
    $duration_month = ($interval->y * 12) + $interval->m;
    if ($interval->d > 0) $duration_month++;


    /* FILE UPLOAD - SUBCONTRACTOR */
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {

        $upload_dir = __DIR__ . "/uploads/project_" . $project_id . "/loa/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_tmp      = $_FILES['attachment']['tmp_name'];
        $original_name = basename($_FILES['attachment']['name']);
        $file_ext      = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed       = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png'];

        if (in_array($file_ext, $allowed)) {
            $clean_name = str_replace(' ', '_', $original_name);
            $file_name  = "loa_" . $clean_name;
            move_uploaded_file($file_tmp, $upload_dir . $file_name);
            $attachment = $file_name;
        }
    }
	
	// FILE UPLOAD - NEW AWARD ATTACHMENT
	$new_award_attachment = $row['new_award_attachment'] ?? ''; // default for edit

	if (isset($_FILES['new_award_attachment']) && $_FILES['new_award_attachment']['error'] == 0) {
		$upload_dir = __DIR__ . "/uploads/project_" . $project_id . "/loa/";
		if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

		$file_tmp      = $_FILES['new_award_attachment']['tmp_name'];
		$original_name = basename($_FILES['new_award_attachment']['name']);
		$file_ext      = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
		$allowed       = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png'];

		if (in_array($file_ext, $allowed)) {
			$clean_name = str_replace(' ', '_', $original_name);
			$file_name  = "new_award_" . $clean_name;
			move_uploaded_file($file_tmp, $upload_dir . $file_name);
			$new_award_attachment = $file_name;
		}
	}

    /* UPDATE / INSERT */

    $block_types = "sssdssidissdds";

    if ($is_edit) {
        $stmt = $db->prepare("
            UPDATE loa SET
            subcon_name=?, subcon_address=?, subcon_phone=?,
            awarded_amount=?,
            start_date=?, completion_date=?,
            duration_month=?,
            lad_amount=?,
            dlp_month=?,
            attachment=?,
            vo_type=?, vo_amount=?, new_award_amount=?, new_award_attachment=?
            WHERE id=?
        ");
        $stmt->bind_param(
			$block_types . "i",
			$subcon_name, $subcon_address, $subcon_phone,
			$awarded_amount,
			$start_date, $completion_date,
			$duration_month,
			$lad_amount,
			$dlp_month,
			$attachment,
			$vo_type, $vo_amount, $new_award_amount, $new_award_attachment,
			$loa_id
		);
    } else {
        $stmt = $db->prepare("
            INSERT INTO loa (
            project_id,
            subcon_name, subcon_address, subcon_phone,
            awarded_amount,
            start_date, completion_date,
            duration_month,
            lad_amount, dlp_month,
            attachment,
            vo_type, vo_amount, new_award_amount, new_award_attachment,

            created_by
            ) VALUES (
                ?,
                ?,?,?,
                ?,
                ?,?,
                ?,
                ?,?,
                ?,
                ?,?,?,?,
                ?
            )
        ");
        $stmt->bind_param(
			"isssdssidissddsi",
			$project_id,
			$subcon_name, $subcon_address, $subcon_phone,
			$awarded_amount,
			$start_date, $completion_date,
			$duration_month,
			$lad_amount, $dlp_month,
			$attachment,
			$vo_type, $vo_amount, $new_award_amount,$new_award_attachment,
			$_SESSION['user_id']
		);
    }

    if ($stmt->execute()) {
        header("Location: loa_list.php?project_id=" . $project_id);
        exit();
    } else {
        echo "Error : " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= $is_edit ? 'Edit LOA' : 'Add LOA' ?></title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 30px;
            background: #f3f4f6;
            font-family: Arial, sans-serif;
            position: relative;
        }

        .main-container {
            width: 100%;
            max-width: 820px;
            margin: 0 auto;
        }

        .page-title {
            text-align: center;
            font-size: 56px;
            font-weight: 800;
            color: #007bff;
            margin-bottom: 40px;
        }

        .tabs {
            display: flex;
            gap: 0;
            margin-bottom: 24px;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .tab-btn {
            flex: 1;
            padding: 14px;
            font-size: 16px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            background: #e2e8f0;
            color: #64748b;
            transition: 0.2s;
        }
        .tab-btn.active { background: #007bff; color: #fff; }
        .tab-btn:first-child { border-radius: 14px 0 0 14px; }
        .tab-btn:last-child { border-radius: 0 14px 14px 0; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .section {
            background: white;
            border: 3px solid #007bff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 24px;
        }

        .section-title {
            background: #f8f9fa;
            padding: 20px 24px;
            font-size: 22px;
            font-weight: 700;
            border-bottom: 1px solid #ddd;
        }

        .info-table { width: 100%; border-collapse: collapse; }
        .info-table tr { border-bottom: 1px solid #eee; }
        .info-table td { padding: 20px 24px; vertical-align: top; font-size: 16px; }
        .info-table td:first-child { width: 35%; background: #fafafa; font-weight: 700; }

        input, textarea, select {
            width: 100%;
            padding: 16px;
            border: 1px solid #ccc;
            border-radius: 12px;
            font-size: 16px;
            font-family: Arial, sans-serif;
        }
        textarea { min-height: 140px; resize: vertical; }

        .button-group { margin-top: 30px; }

        .btn-save {
            width: 100%;
            background: #007bff;
            color: white;
            border: none;
            padding: 18px;
            border-radius: 14px;
            font-size: 28px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-save:hover { background: #0056b3; }

        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            background: #007bff;
            color: #fff;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: bold;
        }
        .back-btn:hover { background: #0056b3; }

        .file-link {
            display: inline-block;
            margin-top: 14px;
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }
        .file-link:hover { text-decoration: underline; }

        .file-note { margin-top: 10px; color: #666; font-size: 14px; }

        @media(max-width:768px) {
            body { padding: 15px; }
            .main-container { max-width: 100%; }
            .page-title { font-size: 42px; }
            .section-title { font-size: 20px; }
            .info-table td { display: block; width: 100%; font-size: 15px; }
            .info-table td:first-child { width: 100%; }
            input, textarea, select { font-size: 15px; }
            .btn-save { font-size: 22px; margin-bottom: 14px; }
        }
    </style>

</head>

<body>

    <div class="main-container">

        <a href="loa_list.php?project_id=<?= $project_id ?>" class="back-btn">← Back</a>

        <h1 class="page-title"><?= $is_edit ? 'Edit LOA' : 'Add LOA' ?></h1>

        <form method="POST" enctype="multipart/form-data">

            <!-- subcon tab -->
            <div class="tab-content active" id="tab_sub">

                <div class="section">
                    <div class="section-title">▼ LOA Information</div>

                    <table class="info-table">

                        <tr>
                            <td>Subcontractor Name</td>
                            <td>
                                <input type="text" name="subcon_name" id="subcon_name" list="subcon_list"
                                    value="<?= htmlspecialchars($subcon_name) ?>" required>

                                <datalist id="subcon_list">
                                    <?php foreach ($subcon_list as $subcon): ?>
                                        <option value="<?= htmlspecialchars($subcon['subcon_name']) ?>"
                                            data-address="<?= htmlspecialchars($subcon['subcon_address'] ?? '') ?>"
                                            data-phone="<?= htmlspecialchars($subcon['subcon_phone'] ?? '') ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </td>
                        </tr>

                        <tr>
                            <td>Address</td>
                            <td>
                                <textarea name="subcon_address" id="subcon_address" required><?= htmlspecialchars($subcon_address) ?></textarea>
                            </td>
                        </tr>

                        <tr>
                            <td>Phone Number</td>
                            <td>
                                <input type="text" name="subcon_phone" id="subcon_phone"
                                    value="<?= htmlspecialchars($subcon_phone) ?>" required>
                            </td>
                        </tr>

                        <tr>
                            <td>Awarded Amount (RM)</td>
                            <td>
                                <input type="number" step="0.01" name="awarded_amount"
                                    value="<?= htmlspecialchars($awarded_amount) ?>" required>
                            </td>
                        </tr>

                        <tr>
                            <td>Start Date</td>
                            <td>
                                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>
                            </td>
                        </tr>

                        <tr>
                            <td>Completion Date</td>
                            <td>
                                <input type="date" name="completion_date" value="<?= htmlspecialchars($completion_date) ?>" required>
                            </td>
                        </tr>

                        <tr>
                            <td>Project Duration (Month)</td>
                            <td>
                                <input type="text" id="duration_month"
                                    value="<?= !empty($duration_month) ? $duration_month . ' Month(s)' : '' ?>" readonly>
                            </td>
                        </tr>

                        <tr>
                            <td>LAD Amount (RM)</td>
                            <td>
                                <input type="number" step="0.01" name="lad_amount"
                                    value="<?= htmlspecialchars($lad_amount) ?>" required>
                            </td>
                        </tr>

                        <tr>
                            <td>DLP Period (Month)</td>
                            <td>
                                <input type="number" name="dlp_month" value="<?= htmlspecialchars($dlp_month) ?>" required>
                            </td>
                        </tr>

                        <tr>
                            <td>Upload Document</td>
                            <td>
                                <input type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                    <?= !$is_edit ? 'required' : '' ?>>

                                <div class="file-note">You can select file. Max size: 250M</div>

                                <?php if (!empty($attachment)): ?>
                                    <a href="uploads/project_<?= $project_id ?>/loa/<?= htmlspecialchars($attachment) ?>"
                                        target="_blank" class="file-link">View Current Document</a>
                                <?php endif; ?>
                            </td>
                        </tr>

                    </table>
                </div>

                <div class="section">
                    <div class="section-title">▼ Variation Order</div>
                    <table class="info-table">
                        <tr>
                            <td>Amount (RM)</td>
                            <td>
                                <input type="number" step="0.01" name="vo_amount" placeholder="Enter amount"
                                    value="<?= htmlspecialchars($vo_amount ?? '') ?>">
                            </td>
                        </tr>
                        <tr>
                            <td>Type</td>
                            <td>
                                <select name="vo_type">
                                    <option value="">-- Select Type --</option>
                                    <option value="additional" <?= $vo_type === 'additional' ? 'selected' : '' ?>>Additional</option>
                                    <option value="substraction" <?= $vo_type === 'substraction' ? 'selected' : '' ?>>Substraction</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="section">
                    <div class="section-title">▼ New Award</div>
                    <table class="info-table">
                        <tr>
                            <td>New Award Amount (RM)</td>
                            <td>
                                <input type="text" id="new_award_display" readonly
                                    style="background:#f0f7ff; font-weight:700; color:#007bff; font-size:18px;">
                            </td>
                        </tr>
						<tr>
                            <td>Upload Document</td>
                            <td>
                                <input type="file" name="new_award_attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                    <?= !$is_edit ? 'required' : '' ?>>

                                <div class="file-note">You can select file. Max size: 250M</div>

                                <?php if (!empty($new_award_attachment)): ?>
                                    <a href="uploads/project_<?= $project_id ?>/loa/<?= htmlspecialchars($new_award_attachment) ?>"
                                        target="_blank" class="file-link">View Current Document</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>

            </div>

            <div class="button-group">
                <button type="submit" class="btn-save"><?= $is_edit ? 'Update LOA' : 'Submit' ?></button>
            </div>

        </form>

    </div>

    <script>
        function switchTab(tab, btn) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab_' + tab).classList.add('active');
            btn.classList.add('active');
        }

        /* autofill subcon */
        document.getElementById('subcon_name').addEventListener('input', function () {
            let input = this.value;
            document.querySelectorAll('#subcon_list option').forEach(function (option) {
                if (option.value === input) {
                    document.getElementById('subcon_address').value = option.dataset.address;
                    document.getElementById('subcon_phone').value = option.dataset.phone;
                }
            });
        });

        /* AUTO CALCULATE DURATION - SUBCONTRACTOR */
        function calculateDuration() {
            let startDate = document.querySelector('[name="start_date"]').value;
            let completionDate = document.querySelector('[name="completion_date"]').value;

            if (startDate && completionDate) {
                let start = new Date(startDate);
                let end = new Date(completionDate);
                let months = (end.getFullYear() - start.getFullYear()) * 12;
                months += end.getMonth() - start.getMonth();
                if (end.getDate() > start.getDate()) months++;
                if (months < 0) months = 0;
                document.getElementById('duration_month').value = months + ' Month(s)';
            }
        }
        document.querySelector('[name="start_date"]').addEventListener('change', calculateDuration);
        document.querySelector('[name="completion_date"]').addEventListener('change', calculateDuration);
        calculateDuration();


        /* NEW AWARD CALCULATION - SUBCONTRACTOR */
        function calculateNewAward() {
            const awarded = parseFloat(document.querySelector('[name="awarded_amount"]')?.value) || 0;
            const voAmount = parseFloat(document.querySelector('[name="vo_amount"]')?.value) || 0;
            const voType = document.querySelector('[name="vo_type"]')?.value;
            const display = document.getElementById('new_award_display');
            if (!display) return;

            if (!voType || voAmount === 0) {
                display.value = awarded.toFixed(2);
                return;
            }
            let result = voType === 'additional' ? awarded + voAmount : awarded - voAmount;
            display.value = result.toFixed(2);
        }
        document.querySelector('[name="awarded_amount"]')?.addEventListener('input', calculateNewAward);
        document.querySelector('[name="vo_amount"]')?.addEventListener('input', calculateNewAward);
        document.querySelector('[name="vo_type"]')?.addEventListener('change', calculateNewAward);
        calculateNewAward();

 
    </script>

</body>

</html>
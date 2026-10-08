<?php
require_once 'include/msqli_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// GET PARAM 
$cerun_id = (int)($_GET['cerun_id'] ?? 0);
$type     = $_GET['type'] ?? '';
$year     = $_GET['year'] ?? date('Y');

if ($cerun_id <= 0) die("❌ Invalid cerun.");

$message = '';

// SAVE 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $rn_start = max(1, (int)($_POST['rn_start'] ?? 1));

    $stmt = $db->prepare("
        INSERT INTO cerun_rn_settings (cerun_id, type, year, rn_start)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE rn_start = VALUES(rn_start)
    ");

    if (!$stmt) {
        die("SQL Error: " . $db->error);
    }

    $stmt->bind_param("issi", $cerun_id, $type, $year, $rn_start);
    $stmt->execute();
    $stmt->close();

    $message = 'success';
}

// load cerun 
$stmt = $db->prepare("SELECT region, symbol FROM cerun WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $cerun_id);
$stmt->execute();
$cerun = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cerun) die("❌ Cerun not found.");

// LOAD RN START 
$stmtRN = $db->prepare("
    SELECT rn_start 
    FROM cerun_rn_settings 
    WHERE cerun_id = ? AND type = ? AND year = ?
");

$stmtRN->bind_param("iss", $cerun_id, $type, $year);
$stmtRN->execute();
$resRN = $stmtRN->get_result()->fetch_assoc();
$stmtRN->close();

$rn_start = $resRN['rn_start'] ?? 1;

// GET LAST RUNNING 
$search_query = "%/" . $year . "/%";

$stmtLast = $db->prepare("
    SELECT running_number 
    FROM letteroutpending_cerun
    WHERE cerun_id = ? AND running_number LIKE ?
    ORDER BY id DESC LIMIT 1
");

$stmtLast->bind_param("is", $cerun_id, $search_query);
$stmtLast->execute();
$stmtLast->bind_result($lastRunning);
$stmtLast->fetch();
$stmtLast->close();

$db->close();

$lastSeq = 0;
if ($lastRunning) {
    $parts   = explode('/', $lastRunning);
    $lastSeq = (int)end($parts);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Project Management System</title>

<style>
:root { --accent:#007bff; }
body {
    font-family: Arial, sans-serif;
    background:#f3f4f6;
    padding:24px;
    display:flex;
    flex-direction:column;
    align-items:center;
}
.card {
    width:100%;
    max-width:500px;
    background:#fff;
    border:3px solid var(--accent);
    border-radius:20px;
    padding:28px;
}
.card-title {
    font-weight:700;
    color:var(--accent);
    margin-bottom:15px;
}
input {
    width:100%;
    padding:10px;
    border-radius:8px;
    border:1px solid #ccc;
}
.preview-box {
    margin-top:20px;
    padding:15px;
    background:#eef5ff;
    border:2px solid var(--accent);
    border-radius:10px;
    text-align:center;
}
.btn {
    margin-top:20px;
    width:100%;
    padding:12px;
    background:var(--accent);
    color:#fff;
    border:none;
    border-radius:10px;
    font-weight:bold;
}
.success {
    background:#d4edda;
    padding:10px;
    margin-bottom:10px;
    border-radius:8px;
}
</style>
</head>

<body>

<a href="javascript:history.back()" class="back-btn">
    ← Back
</a>

<h2>Running Number Settings</h2>

<?php if ($message === 'success'): ?>
<div class="success">✅ Saved successfully</div>
<?php endif; ?>

<div class="card">

<div class="card-title">
<?= htmlspecialchars($cerun['region']) ?> — <?= htmlspecialchars($year) ?>
</div>

<form method="post" action="cerun_rn_settings.php?cerun_id=<?= $cerun_id ?>&type=<?= urlencode($type) ?>&year=<?= urlencode($year) ?>">

<label>Starting Number</label>
<input type="number" name="rn_start" id="fStart"
       value="<?= (int)$rn_start ?>" min="1">

<div class="preview-box">
    <small>Preview:</small><br>
    <strong id="preview"></strong>
</div>

<button class="btn">Save</button>

</form>
</div>

<script>
const type = "<?= addslashes($type) ?>";
const year = "<?= addslashes($year) ?>";
const region = "<?= addslashes(strtoupper(trim($cerun['symbol'] ?? substr(str_replace(' ', '', $cerun['region']), 0, 3)))) ?>";

const prefix = (type == '6') 
    ? "CERUN/KKR/KJ6/PUSB" 
    : "CERUN/KKR/PUSB";

const isHQ = (['CKC', 'KL'].includes(region));
const lastSeq = <?= $lastSeq ?>;

function buildPreview() {
    const start = parseInt(document.getElementById('fStart').value) || 1;
    const next = Math.max(start, lastSeq + 1);
    const folder = isHQ ? region : region + year;
    const seq = String(next).padStart(3, '0');

    document.getElementById('preview').textContent =
        `${prefix}/${folder}/${year}/${seq}`;
}

document.getElementById('fStart').addEventListener('input', buildPreview);
buildPreview();
</script>

</body>
</html>
<?php
require_once 'include/msqli_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit;
}

$project_id = (int)($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
if ($project_id <= 0) die("❌ Invalid project.");

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rn_start = max(1, (int)($_POST['rn_start'] ?? 1));

    $stmt = $db->prepare("UPDATE project SET rn_start = ? WHERE id = ?");
    $stmt->bind_param("ii", $rn_start, $project_id);
    $stmt->execute();
    $stmt->close();
    $message = 'success';
}

$stmt = $db->prepare("SELECT project_name, prefix, rn_start FROM project WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) die("❌ Project not found.");

// Get the latest running number already in the system for this project
$year = date('Y');
$stmtLast = $db->prepare("
    SELECT running_number FROM letter_out_pending
    WHERE project_id = ? AND YEAR(request_date) = ? AND running_number IS NOT NULL
    ORDER BY id DESC LIMIT 1
");
$stmtLast->bind_param("ii", $project_id, $year);
$stmtLast->execute();
$stmtLast->bind_result($lastRunning);
$stmtLast->fetch();
$stmtLast->close();
$db->close();

// Extract last sequence from running number if exists
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
  *, *::before, *::after { box-sizing: border-box; }
  :root { --accent: #007bff; }
  body { font-family: Arial, sans-serif; background: #f3f4f6; padding: 24px; display: flex; flex-direction: column; align-items: center; }
  .back-btn { align-self: flex-start; margin-bottom: 16px; background: var(--accent); color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: bold; }
  h1 { color: var(--accent); font-weight: 800; margin-bottom: 24px; }
  .card { width: 100%; max-width: 500px; background: #fff; border: 3px solid var(--accent); border-radius: 20px; padding: 28px; margin-bottom: 20px; }
  .card-title { font-size: 18px; font-weight: 700; color: var(--accent); border-bottom: 2px solid var(--accent); margin-bottom: 20px; padding-bottom: 6px; }
  .form-group { margin-bottom: 18px; }
  label { font-weight: bold; display: block; margin-bottom: 6px; color: #333; }
  input[type=number] { width: 100%; padding: 10px 12px; border: 1.5px solid #ccc; border-radius: 8px; height: 42px; font-size: 15px; }
  input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(0,123,255,.15); }
  .preview-box { background: #eef5ff; border: 2px solid var(--accent); border-radius: 12px; padding: 18px; text-align: center; margin: 20px 0; }
  .preview-box small { color: #666; font-size: 13px; display: block; margin-bottom: 6px; }
  .preview-box strong { font-size: 16px; color: #0056b3; letter-spacing: 1px; word-break: break-all; }
  .btn { width: 100%; background: var(--accent); color: #fff; padding: 13px; border: none; border-radius: 10px; font-size: 16px; font-weight: 800; cursor: pointer; }
  .success-msg { background: #d4edda; color: #155724; border-radius: 10px; padding: 12px 16px; margin-bottom: 18px; font-weight: bold; width: 100%; max-width: 500px; }
  .hint { font-size: 12px; color: #888; margin-top: 4px; }
</style>
</head>
<body>

<a href="letterout_list.php?project_id=<?= $project_id ?>" class="back-btn">← Back</a>
<h1>Running Number Settings</h1>

<?php if ($message === 'success'): ?>
  <div class="success-msg">✅ Settings saved! New letters will use the updated format.</div>
<?php endif; ?>

<div class="card">
  <div class="card-title">⚙️ <?= htmlspecialchars($project['project_name']) ?></div>

  <form method="post" action="project_rn_settings.php?project_id=<?= $project_id ?>">
    <div class="form-group">
      <label>Starting Sequence Number</label>
      <input type="number" name="rn_start" id="fStart" min="1"
             value="<?= (int)$project['rn_start'] ?>">
      <div class="hint">
        e.g. If your legacy system ended at 150, enter <strong>151</strong> — the first new letter will be
        <strong><?= htmlspecialchars($project['prefix']) ?>/<?= $year ?>/<?= str_pad((int)$project['rn_start'], 3, '0', STR_PAD_LEFT) ?></strong>
      </div>
    </div>

    <div class="preview-box">
      <small>First new letter will look like:</small>
      <strong id="preview"></strong>
    </div>

    <button class="btn" type="submit">Save Settings</button>
  </form>
</div>

<script>
const prefix  = "<?= addslashes($project['prefix']) ?>";
const year    = new Date().getFullYear();

function buildPreview() {
  const start = parseInt(document.getElementById('fStart').value) || 1;
  const seq = String(start).padStart(3, '0');
  document.getElementById('preview').textContent = `${prefix}/${year}/${seq}`;
}

document.getElementById('fStart').addEventListener('input', buildPreview);
buildPreview();
</script>
</body>
</html>
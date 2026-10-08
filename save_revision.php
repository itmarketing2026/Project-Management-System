<?php
// save_revision.php — FINAL FIXED VERSION
ini_set('display_errors', 1);
error_reporting(E_ALL);

// === Debug log setup ===
$logFile = __DIR__ . '/debug_submit.log';
function dbg($msg) {
    global $logFile;
    file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] $msg\n", FILE_APPEND);
}
dbg("===== Script started =====");

// === Database Connection ===
$servername = "localhost";
$username   = "gtpprotege_projectdocument";
$password   = "ProjectDocument";
$dbname     = "gtpprotege_projectdocument";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    dbg("DB connection failed: " . $conn->connect_error);
    die("<script>alert('❌ Database connection failed.');history.back();</script>");
}
dbg("Database connected.");

// === Validate project_id ===
$project_id = intval($_POST['project_id'] ?? 0);
if ($project_id <= 0) {
    dbg("Missing project_id in POST");
    die("<script>alert('❌ Missing project parameters.');history.back();</script>");
}
dbg("Project ID = {$project_id}");

// === Duplicate Submission Lock (2s) ===
$lockFile = sys_get_temp_dir() . "/revision_lock_{$project_id}.txt";
if (file_exists($lockFile)) {
    $last = intval(trim(file_get_contents($lockFile)));
    if (time() - $last < 2) {
        dbg("Duplicate submission detected (locked). Ignoring.");
        die("<script>alert('Please wait before saving again.');history.back();</script>");
    }
}
file_put_contents($lockFile, time());
dbg("Lock file updated.");

// === Next Revision Number ===
$stmt = $conn->prepare("SELECT MAX(revision_no) FROM project_revisions WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$stmt->bind_result($max_rev);
$stmt->fetch();
$stmt->close();
$next_rev = intval($max_rev) + 1;
dbg("Next revision number = {$next_rev}");

// === Prepare SQL ===
$sql = "INSERT INTO project_revisions (
    project_id, bill_no, description,
    A,B,C,D,E,F,G,H,J,K,L,M,N,P,Q,R,S,T,U,V,
    revision_no, revision_date
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    dbg("Prepare failed: " . $conn->error);
    die("<script>alert('Prepare failed: " . addslashes($conn->error) . "');history.back();</script>");
}
dbg("Prepared successfully.");

$cols = ['A','B','C','D','E','F','G','H','J','K','L','M','N','P','Q','R','S','T','U','V'];
$totalRows = count($_POST['bill_no'] ?? []);
dbg("Total rows submitted = {$totalRows}");

// === Insert Each Row ===
for ($i = 0; $i < $totalRows; $i++) {
    $bill_no     = intval($_POST['bill_no'][$i] ?? 0);
    $description = trim($_POST['description'][$i] ?? '');
    $hasValue = false;
    $values = [];

    foreach ($cols as $c) {
        $num = floatval(str_replace(',', '', $_POST[$c][$i] ?? 0));
        $values[$c] = $num;
        if ($num != 0) $hasValue = true;
    }

    if ($description === '' && !$hasValue) {
        dbg("Row {$i} skipped (empty + zero values)");
        continue;
    }

    dbg("Row {$i} description = '" . str_replace(["\r", "\n"], ['\\r', '\\n'], $description) . "' (len=" . strlen($description) . ")");

    // ✅ Correct bind_param types
    $stmt->bind_param(
        "iis" . str_repeat("d", 20) . "i",
        $project_id,
        $bill_no,
        $description,
        $values['A'], $values['B'], $values['C'], $values['D'], $values['E'],
        $values['F'], $values['G'], $values['H'], $values['J'], $values['K'],
        $values['L'], $values['M'], $values['N'], $values['P'],
        $values['Q'], $values['R'], $values['S'], $values['T'],
        $values['U'], $values['V'],
        $next_rev
    );

    if (!$stmt->execute()) {
        dbg("Row {$i} failed: " . $stmt->error);
    } else {
        dbg("Row {$i} OK (bill_no={$bill_no}, rev={$next_rev}, desc_len=" . strlen($description) . ")");
    }
}

$stmt->close();
$conn->close();
dbg("Revision #{$next_rev} saved successfully.");
dbg("===== Script ended normally =====\n");

// === Redirect Back ===
header("Location: view_project_table.php?project_id={$project_id}&saved=1&rev_no={$next_rev}");
exit;
?>

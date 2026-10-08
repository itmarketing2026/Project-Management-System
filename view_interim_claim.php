<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$project_id = intval($_GET['project_id'] ?? 0);
$id         = intval($_GET['id'] ?? 0);

if (!$project_id || !$id) die("Invalid Request.");

$stmt = $db->prepare("
    SELECT ic.*, l.subcon_name
    FROM interim_claim ic
    LEFT JOIN loa l ON l.id = ic.loa_id
    WHERE ic.id = ? AND ic.project_id = ?
");
$stmt->bind_param("ii", $id, $project_id);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$record) die("Record not found.");

$role       = $_SESSION['account_type'] ?? '';
$can_delete = in_array($role, ['Client Admin', 'Admin (QA/QC)']);

function render_files($files_str) {
    if (empty($files_str)) {
        echo '<span class="field-value muted">No Document Uploaded</span>';
        return;
    }
	
    foreach (explode(',', $files_str) as $file) {
        if (!$file) continue;
        $ext    = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $is_img = in_array($ext, ['jpg','jpeg','png','gif','webp']);
        $is_pdf = ($ext === 'pdf');
		$filename = basename($file);
		$safeUrl = 'download_file.php?file=' . urlencode($filename);
		
        echo '<a href="' . htmlspecialchars($safeUrl) . '" target="_blank" class="doc-link">📄 ' . htmlspecialchars($filename) . '</a>';
        if ($is_pdf) {
            echo '<iframe src="' . htmlspecialchars($safeUrl) . '" class="doc-preview-iframe"></iframe>';
        } elseif ($is_img) {
            echo '<img src="' . htmlspecialchars($safeUrl) . '" class="doc-preview-img">';
        }
    }
}

function fmt_amount($val) {
    return !empty($val) ? 'RM ' . number_format($val, 2) : '<span class="muted">-</span>';
}

function fmt_date($val) {
    return !empty($val) ? date('d/m/Y', strtotime($val)) : '-';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>View Interim Claim</title>
<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    padding: 30px;
    background: #f3f4f6;
    font-family: Arial, sans-serif;
    min-height: 100vh;
}

.main-container {
    max-width: 1100px;
    margin: 0 auto;
}

.page-title {
    font-size: 40px;
    font-weight: 800;
    color: #007bff;
    margin-bottom: 28px;
    text-align: center;
}

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

.section {
    background: #fff;
    border: 3px solid #007bff;
    border-radius: 24px;
    margin-bottom: 24px;
    overflow: hidden;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}

.section-title {
    padding: 16px 24px;
    font-size: 16px;
    font-weight: 700;
    color: #007bff;
    background: #f8f9fa;
    border-bottom: 2px solid #e2e8f0;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.field {
    padding: 18px 24px;
    border-bottom: 1px solid #e2e8f0;
    border-right: 1px solid #e2e8f0;
}

.field.full          { grid-column: 1 / -1; border-right: none; }
.field.no-right      { border-right: none; }
.field.no-bottom     { border-bottom: none; }

.field-label {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 5px;
    font-weight: 700;
}

.field-value {
    font-size: 15px;
    font-weight: 600;
    color: #1e293b;
}

.field-value.large {
    font-size: 22px;
    font-weight: 800;
    color: #007bff;
}

.muted {
    color: #9ca3af;
    font-style: italic;
    font-weight: 400;
    font-size: 14px;
}

.badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
}
.badge-green { background: #dcfce7; color: #15803d; }
.badge-red   { background: #fee2e2; color: #b91c1c; }

.doc-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    background: #eff6ff;
    border: 2px solid #93c5fd;
    border-radius: 10px;
    color: #1d4ed8;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 8px;
}
.doc-link:hover { background: #dbeafe; }

.doc-preview-iframe {
    width: 100%;
    height: 600px;
    border: 2px solid #93c5fd;
    border-radius: 12px;
    margin-top: 12px;
    display: block;
}

.doc-preview-img {
    max-width: 100%;
    border-radius: 12px;
    border: 2px solid #93c5fd;
    margin-top: 12px;
    display: block;
}

.btn-group {
    display: flex;
    gap: 12px;
    margin-top: 8px;
}

.btn {
    flex: 1;
    text-align: center;
    padding: 16px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 700;
    font-size: 18px;
    transition: 0.2s;
    border: none;
    cursor: pointer;
}
.btn-back   { background: #6c757d; color: #fff; }
.btn-back:hover { background: #545b62; }
.btn-edit   { background: #007bff; color: #fff; }
.btn-edit:hover { background: #0056b3; }
.btn-delete { background: #dc2626; color: #fff; }
.btn-delete:hover { background: #b91c1c; }

@media (max-width: 768px) {
    body { padding: 15px; }
    .page-title { font-size: 30px; }
    .info-grid { grid-template-columns: 1fr; }
    .field { border-right: none; }
    .btn-group { flex-direction: column; }
    .btn { font-size: 16px; }
}
</style>
</head>
<body>

<div class="main-container">

    <div class="page-title">View Interim Claim</div>

    <!-- GENERAL -->
    <div class="section">
        <div class="section-title">▼ General Information</div>
        <div class="info-grid">
            <div class="field">
                <div class="field-label">Subcontractor</div>
                <div class="field-value"><?= htmlspecialchars($record['subcon_name'] ?? '-') ?></div>
            </div>
            <div class="field no-right">
                <div class="field-label">IPC Number</div>
                <div class="field-value"><?= htmlspecialchars($record['ipc_number'] ?? '-') ?></div>
            </div>
        </div>
    </div>

    <!-- A -->
    <div class="section">
        <div class="section-title">▼ A. Submission of Payment Certificate (Subcontractor)</div>
        <div class="info-grid">
            <div class="field no-bottom">
                <div class="field-label">Amount (RM)</div>
                <div class="field-value"><?= fmt_amount($record['a_amount']) ?></div>
            </div>
            <div class="field no-bottom no-right">
                <div class="field-label">Date</div>
                <div class="field-value"><?= fmt_date($record['a_date']) ?></div>
            </div>
        </div>
    </div>

    <!-- B -->
    <div class="section">
        <div class="section-title">▼ B. Certified Certificate Subcontractor</div>
        <div class="info-grid">
            <div class="field">
                <div class="field-label">Total Work Done (RM)</div>
                <div class="field-value"><?= fmt_amount($record['b_total_work']) ?></div>
            </div>
            <div class="field">
                <div class="field-label">Retention Amount (RM)</div>
                <div class="field-value"><?= fmt_amount($record['b_retention']) ?></div>
            </div>
            <div class="field no-right">
                <div class="field-label">Certified Amount (RM)</div>
                <div class="field-value large"><?= fmt_amount($record['b_certified']) ?></div>
            </div>
            <div class="field full">
                <div class="field-label">Date</div>
                <div class="field-value"><?= fmt_date($record['b_date']) ?></div>
            </div>
            <div class="field full no-bottom">
                <div class="field-label">Attachment</div>
                <?php render_files($record['b_file']); ?>
            </div>
        </div>
    </div>

    <!-- C -->
    <div class="section">
        <div class="section-title">▼ C. Payment Release</div>
        <div class="info-grid">
            <div class="field">
                <div class="field-label">Amount (RM)</div>
                <div class="field-value"><?= fmt_amount($record['c_amount']) ?></div>
            </div>
            <div class="field no-right">
                <div class="field-label">Date</div>
                <div class="field-value"><?= fmt_date($record['c_date']) ?></div>
            </div>
            <div class="field full no-bottom">
                <div class="field-label">Attachment</div>
                <?php render_files($record['c_file']); ?>
            </div>
        </div>
    </div>

    <!-- D -->
    <div class="section">
        <div class="section-title">▼ D. Advance Payment to Subcontractor</div>
        <div class="info-grid">
            <div class="field full <?= ($record['d_status'] ?? '') !== 'applicable' ? 'no-bottom' : '' ?>">
                <div class="field-label">Status</div>
                <div class="field-value">
                    <?php if (($record['d_status'] ?? '') === 'applicable'): ?>
                        <span class="badge badge-green">Applicable</span>
                    <?php else: ?>
                        <span class="badge badge-red">Non-Applicable</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (($record['d_status'] ?? '') === 'applicable'): ?>
            <div class="field">
                <div class="field-label">Amount (RM)</div>
                <div class="field-value"><?= fmt_amount($record['d_amount']) ?></div>
            </div>
            <div class="field no-right">
                <div class="field-label">Date</div>
                <div class="field-value"><?= fmt_date($record['d_date']) ?></div>
            </div>
            <div class="field full no-bottom">
                <div class="field-label">Attachment</div>
                <?php render_files($record['d_file']); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- E -->
    <div class="section">
        <div class="section-title">▼ E. Back Charge to Subcontractor</div>
        <div class="info-grid">
            <div class="field full <?= ($record['e_status'] ?? '') !== 'applicable' ? 'no-bottom' : '' ?>">
                <div class="field-label">Status</div>
                <div class="field-value">
                    <?php if (($record['e_status'] ?? '') === 'applicable'): ?>
                        <span class="badge badge-green">Applicable</span>
                    <?php else: ?>
                        <span class="badge badge-red">Non-Applicable</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (($record['e_status'] ?? '') === 'applicable'): ?>
            <div class="field">
                <div class="field-label">Amount (RM)</div>
                <div class="field-value"><?= fmt_amount($record['e_amount']) ?></div>
            </div>
            <div class="field no-right">
                <div class="field-label">Date</div>
                <div class="field-value"><?= fmt_date($record['e_date']) ?></div>
            </div>
            <div class="field full no-bottom">
                <div class="field-label">Attachment</div>
                <?php render_files($record['e_file']); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="btn-group">
        <a href="interim_claim_list.php?project_id=<?= $project_id ?>" class="btn btn-back">← Back</a>
        <a href="add_interim_claim.php?project_id=<?= $project_id ?>&id=<?= $id ?>" class="btn btn-edit">✏️ Edit</a>
        <?php if ($can_delete): ?>
            <button type="button" class="btn btn-delete" onclick="confirmDelete()">🗑 Delete</button>
        <?php endif; ?>
    </div>

</div>

<?php if ($can_delete): ?>
<form method="POST" action="add_interim_claim.php?project_id=<?= $project_id ?>&id=<?= $id ?>" id="deleteForm">
    <input type="hidden" name="delete_id" value="<?= $id ?>">
</form>
<?php endif; ?>

<script>
function confirmDelete() {
    if (confirm('Are you sure you want to delete this Interim Claim? This cannot be undone.')) {
        document.getElementById('deleteForm').submit();
    }
}
</script>

</body>
</html>
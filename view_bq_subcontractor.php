<?php
session_start();

require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$project_id = intval($_GET['project_id'] ?? 0);
$bq_id      = intval($_GET['bq_id'] ?? 0);

if (!$project_id || !$bq_id) die("Invalid Request.");

if (!user_can_access_project($db, $_SESSION['user_id'], $project_id, $_SESSION['account_type'] ?? '')) {
    die("You do not have permission to view this project.");
}

$stmt = $db->prepare("SELECT * FROM bq_subcontractor WHERE id = ? AND project_id = ?");
$stmt->bind_param("ii", $bq_id, $project_id);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$record) die("Record not found.");

/* VO entries */
$vo_rows = [];
if (($record['vo_status'] ?? '') === 'applicable') {
    $stmt_vo = $db->prepare("SELECT * FROM bq_subcontractor_vo WHERE bq_subcontractor_id = ? ORDER BY id ASC");
    $stmt_vo->bind_param("i", $bq_id);
    $stmt_vo->execute();
    $vo_rows = $stmt_vo->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_vo->close();
}

/* PHK entries */
$phk_rows = [];
if (($record['phk_status'] ?? '') === 'applicable') {
    $stmt_phk = $db->prepare("SELECT * FROM bq_subcontractor_phk WHERE bq_subcontractor_id = ? ORDER BY id ASC");
    $stmt_phk->bind_param("i", $bq_id);
    $stmt_phk->execute();
    $phk_rows = $stmt_phk->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_phk->close();
}

$role       = $_SESSION['account_type'] ?? '';
$can_delete = in_array($role, ['Client Admin', 'Admin (QA/QC)']);

function render_file($filename, $project_id) {
    if (empty($filename)) {
        echo '<span class="field-value muted">No Document Uploaded</span>';
        return;
    }

    $ext    = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $is_img = in_array($ext, ['jpg','jpeg','png','gif','webp']);
    $is_pdf = ($ext === 'pdf');
    $safeUrl = 'download_file.php?file=' . urlencode(basename($filename));

    echo '<a href="' . htmlspecialchars($safeUrl) . '" target="_blank" class="doc-link">📄 ' . htmlspecialchars($filename) . '</a>';
    if ($is_pdf) {
        echo '<iframe src="' . htmlspecialchars($safeUrl) . '" class="doc-preview-iframe"></iframe>';
    } elseif ($is_img) {
        echo '<img src="' . htmlspecialchars($safeUrl) . '" class="doc-preview-img">';
    }
}

function fmt_amount($val) {
    return ($val !== null && $val !== '') ? 'RM ' . number_format($val, 2) : '<span class="muted">-</span>';
}

function fmt_date($val) {
    return !empty($val) ? date('d/m/Y', strtotime($val)) : '-';
}

function fmt_vo_type($type) {
    if ($type === 'addition') return '<span class="badge badge-green">Addition (+)</span>';
    if ($type === 'subtraction') return '<span class="badge badge-red">Subtraction (−)</span>';
    return '<span class="muted">-</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Project Management System</title>
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

.entry-block {
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
}
.entry-block:last-child { border-bottom: none; }
.entry-block-title {
    font-weight: 800;
    color: #007bff;
    font-size: 14px;
    margin-bottom: 14px;
}
.entry-block .info-grid {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
}

.empty-note {
    padding: 20px 24px;
    color: #9ca3af;
    font-style: italic;
    font-size: 14px;
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

    <div class="page-title">View Subcontractor Bill of Quantities</div>

    <!-- DETAILS -->
    <div class="section">
        <div class="section-title">▼ Subcontractor BQ Details</div>
        <div class="info-grid">
            <div class="field">
                <div class="field-label">Subcontractor</div>
                <div class="field-value large"><?= htmlspecialchars($record['name'] ?? '-') ?></div>
            </div>
            <div class="field">
                <div class="field-label">Date</div>
                <div class="field-value"><?= fmt_date($record['bq_date']) ?></div>
            </div>
            <div class="field no-right">
                <div class="field-label">Amount (RM)</div>
                <div class="field-value large"><?= fmt_amount($record['amount']) ?></div>
            </div>
			<div class="field full no-bottom">
                <div class="field-label">Document</div>
                <?php
                if (!empty($record['document'])) {
                    foreach (explode(',', $record['document']) as $doc) {
                        $doc = trim($doc);
                        if ($doc === '') continue;
                        render_file($doc, $project_id);
                    }
                } else {
                    render_file('', $project_id);
                }
                ?>
            </div>
        </div>
    </div>

    <!-- VARIATION ORDER -->
    <div class="section">
        <div class="section-title">▼ Variation Order</div>
        <div class="info-grid">
            <div class="field full no-bottom">
                <div class="field-label">Status</div>
                <div class="field-value">
                    <?php if (($record['vo_status'] ?? '') === 'applicable'): ?>
                        <span class="badge badge-green">Applicable</span>
                    <?php else: ?>
                        <span class="badge badge-red">Non-Applicable</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (($record['vo_status'] ?? '') === 'applicable'): ?>
            <?php if (!empty($vo_rows)): foreach ($vo_rows as $i => $vo): $n = $i + 1; ?>
            <div class="entry-block">
                <div class="entry-block-title">Variation Order <?= $n ?></div>
                <div class="info-grid" style="grid-template-columns: repeat(3, 1fr);">
                    <div class="field">
                        <div class="field-label">Date</div>
                        <div class="field-value"><?= fmt_date($vo['vo_date']) ?></div>
                    </div>
                    <div class="field">
                        <div class="field-label">Amount (RM)</div>
                        <div class="field-value large"><?= fmt_amount($vo['vo_amount']) ?></div>
                    </div>
                    <div class="field no-right">
                        <div class="field-label">Type</div>
                        <div class="field-value"><?= fmt_vo_type($vo['vo_type']) ?></div>
                    </div>
                    <div class="field full">
                        <div class="field-label">New Award Amount (RM)</div>
                        <div class="field-value large"><?= fmt_amount($vo['new_award_amount']) ?></div>
                    </div>
                    <div class="field full no-bottom">
                        <div class="field-label">Document</div>
                        <?php render_file($vo['vo_document'], $project_id); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; else: ?>
                <div class="empty-note">No VO entries recorded.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- PHK -->
    <div class="section">
        <div class="section-title">▼ Adjustment to Original Contract (PHK)</div>
        <div class="info-grid">
            <div class="field full no-bottom">
                <div class="field-label">Status</div>
                <div class="field-value">
                    <?php if (($record['phk_status'] ?? '') === 'applicable'): ?>
                        <span class="badge badge-green">Applicable</span>
                    <?php else: ?>
                        <span class="badge badge-red">Non-Applicable</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (($record['phk_status'] ?? '') === 'applicable'): ?>
            <?php if (!empty($phk_rows)): foreach ($phk_rows as $i => $phk): $n = $i + 1; ?>
            <div class="entry-block">
                <div class="entry-block-title">PHK <?= $n ?></div>
                <div class="info-grid">
                    <div class="field">
                        <div class="field-label">Date</div>
                        <div class="field-value"><?= fmt_date($phk['phk_date']) ?></div>
                    </div>
                    <div class="field no-right">
                        <div class="field-label">Amount (RM)</div>
                        <div class="field-value large"><?= fmt_amount($phk['phk_amount']) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; else: ?>
                <div class="empty-note">No PHK entries recorded.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="btn-group">
        <a href="bq_list.php?project_id=<?= $project_id ?>" class="btn btn-back">← Back</a>
        <a href="add_bq_subcontractor.php?project_id=<?= $project_id ?>&bq_id=<?= $bq_id ?>" class="btn btn-edit">✏️ Edit</a>
        <?php if ($can_delete): ?>
            <button type="button" class="btn btn-delete" onclick="confirmDelete()">🗑 Delete</button>
        <?php endif; ?>
    </div>

</div>

<?php if ($can_delete): ?>
<form method="POST" action="delete_bq_subcontractor.php" id="deleteForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="bq_id" value="<?= $bq_id ?>">
    <input type="hidden" name="project_id" value="<?= $project_id ?>">
</form>
<?php endif; ?>

<script>
function confirmDelete() {
    if (confirm('Are you sure you want to delete this Subcontractor BQ record? This cannot be undone.')) {
        document.getElementById('deleteForm').submit();
    }
}
</script>

</body>
</html>
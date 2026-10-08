<?php
session_start();

$title = "View LOA";

require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$project_id = intval($_GET['project_id'] ?? 0);
$loa_id = intval($_GET['loa_id'] ?? 0);

if (!$project_id || !$loa_id) {
  die("Invalid Request.");
}

/* =========================================
FETCH LOA
========================================= */

$stmt = $db->prepare("
    SELECT * FROM loa
    WHERE id = ?
");

$stmt->bind_param("i", $loa_id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {
  die("LOA not found.");
}

$row = $result->fetch_assoc();

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

.field.full { grid-column: 1 / -1; border-right: none; }
.field.no-border-bottom { border-bottom: none; }
.field.no-border-right { border-right: none; }

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
    line-height: 1.6;
}

.field-value.large {
    font-size: 22px;
    font-weight: 800;
    color: #007bff;
}

.field-value.muted {
    color: #9ca3af;
    font-style: italic;
    font-weight: 400;
    font-size: 14px;
}

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

.badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
}
.badge-green { background: #dcfce7; color: #15803d; }
.badge-red   { background: #fee2e2; color: #b91c1c; }

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
}
.btn-back   { background: #6c757d; color: #fff; }
.btn-back:hover { background: #545b62; }
.btn-edit   { background: #007bff; color: #fff; }
.btn-edit:hover { background: #0056b3; }
.btn-delete { background: #dc3545; color: #fff; }
.btn-delete:hover { background: #b02a37; }

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

    <div class="page-title">View LOA</div>

    <!-- LOA INFORMATION -->
    <div class="section">
        <div class="section-title">▼ LOA Information</div>
        <div class="info-grid">

            <div class="field full">
                <div class="field-label">Subcontractor Name</div>
                <div class="field-value"><?= htmlspecialchars($row['subcon_name']) ?></div>
            </div>

            <div class="field full">
                <div class="field-label">Address</div>
                <div class="field-value"><?= nl2br(htmlspecialchars($row['subcon_address'])) ?></div>
            </div>

            <div class="field">
                <div class="field-label">Phone Number</div>
                <div class="field-value"><?= htmlspecialchars($row['subcon_phone']) ?></div>
            </div>

            <div class="field no-border-right">
                <div class="field-label">Awarded Amount</div>
                <div class="field-value">RM <?= number_format($row['awarded_amount'], 2) ?></div>
            </div>

            <div class="field">
                <div class="field-label">Start Date</div>
                <div class="field-value"><?= date('d/m/Y', strtotime($row['start_date'])) ?></div>
            </div>

            <div class="field">
                <div class="field-label">Completion Date</div>
                <div class="field-value"><?= date('d/m/Y', strtotime($row['completion_date'])) ?></div>
            </div>

            <div class="field no-border-right">
                <div class="field-label">Project Duration</div>
                <div class="field-value"><?= htmlspecialchars($row['duration_month']) ?> Month(s)</div>
            </div>

            <div class="field">
                <div class="field-label">LAD Amount</div>
                <div class="field-value">RM <?= number_format($row['lad_amount'], 2) ?></div>
            </div>

            <div class="field no-border-right">
                <div class="field-label">DLP Period</div>
                <div class="field-value"><?= htmlspecialchars($row['dlp_month']) ?> Month(s)</div>
            </div>

            <div class="field full no-border-bottom">
				<div class="field-label">Document</div>
				<?php if (!empty($row['attachment'])): ?>
                    <?php
                        $att_filename = basename(trim((string)$row['attachment']));
                        $att_ext = strtolower(pathinfo($att_filename, PATHINFO_EXTENSION));
                        $att_img = in_array($att_ext, ['jpg','jpeg','png','gif','webp'], true);
                        $att_pdf = ($att_ext === 'pdf');

                        /*
                         * LOA DB stores filename only.
                         * Actual location:
                         * uploads/project_<project_id>/loa/<filename>
                         *
                         * Pass project_id + folder to download_file.php
                         * so it does NOT scan the whole uploads directory.
                         */
                        $att_url =
                            'download_file.php?file=' .
                            urlencode($att_filename) .
                            '&project_id=' .
                            $project_id .
                            '&folder=loa';
                    ?>
					<a href="<?= htmlspecialchars($att_url) ?>" target="_blank" class="doc-link">
						📄 <?= htmlspecialchars($att_filename) ?>
					</a>
					<?php if ($att_pdf): ?>
						<div
    id="loaPdfPreview"
    style="display:none;"
>
    <iframe
        data-src="<?= htmlspecialchars($att_url) ?>"
        class="doc-preview-iframe"
        loading="lazy"
    ></iframe>
</div>

<button
    type="button"
    onclick="toggleLoaPdfPreview()"
    style="
        margin-top:10px;
        padding:9px 14px;
        border:none;
        border-radius:8px;
        background:#007bff;
        color:#fff;
        font-weight:700;
        cursor:pointer;
    "
>
    👁 Preview PDF
</button>
					<?php elseif ($att_img): ?>
						<img src="<?= htmlspecialchars($att_url) ?>" class="doc-preview-img">
					<?php endif; ?>
				<?php else: ?>
					<span class="field-value muted">No document uploaded</span>
				<?php endif; ?>
			</div>

        </div>
    </div>

    <!-- VARIATION ORDER & NEW AWARD -->
    <div class="section">
        <div class="section-title">▼ Variation Order</div>
        <div class="info-grid">

            <div class="field no-border-right">
                <div class="field-label">Variation Order Amount</div>
                <div class="field-value">
                    <?= !empty($row['vo_amount']) ? 'RM ' . number_format($row['vo_amount'], 2) : '<span class="field-value muted">-</span>' ?>
                </div>
            </div>

            <div class="field full">
                <div class="field-label">New Award Amount</div>
                <div class="field-value large">
                    <?= !empty($row['new_award_amount']) ? 'RM ' . number_format($row['new_award_amount'], 2) : '<span class="field-value muted">-</span>' ?>
                </div>
            </div>

            <div class="field full no-border-bottom">
				<div class="field-label">New Award Document</div>
				<?php if (!empty($row['new_award_attachment'])): ?>
                    <?php
                        $na_filename = basename(trim((string)$row['new_award_attachment']));
                        $na_ext = strtolower(pathinfo($na_filename, PATHINFO_EXTENSION));
                        $na_img = in_array($na_ext, ['jpg','jpeg','png','gif','webp'], true);
                        $na_pdf = ($na_ext === 'pdf');

                        /*
                         * New Award DB also stores filename only.
                         * Actual location:
                         * uploads/project_<project_id>/loa/<filename>
                         */
                        $na_url =
                            'download_file.php?file=' .
                            urlencode($na_filename) .
                            '&project_id=' .
                            $project_id .
                            '&folder=loa';
                    ?>
					<a href="<?= htmlspecialchars($na_url) ?>" target="_blank" class="doc-link">
						📄 <?= htmlspecialchars($na_filename) ?>
					</a>
					<?php if ($na_pdf): ?>
						<div
    id="loaNewAwardPdfPreview"
    style="display:none;"
>
    <iframe
        data-src="<?= htmlspecialchars($na_url) ?>"
        class="doc-preview-iframe"
        loading="lazy"
    ></iframe>
</div>

<button
    type="button"
    onclick="toggleLoaNewAwardPdfPreview()"
    style="
        margin-top:10px;
        padding:9px 14px;
        border:none;
        border-radius:8px;
        background:#007bff;
        color:#fff;
        font-weight:700;
        cursor:pointer;
    "
>
    👁 Preview PDF
</button>
					<?php elseif ($na_img): ?>
						<img src="<?= htmlspecialchars($na_url) ?>" class="doc-preview-img">
					<?php endif; ?>
				<?php else: ?>
					<span class="field-value muted">No document uploaded</span>
				<?php endif; ?>
			</div>

        </div>
    </div>

    <!-- BUTTONS -->
    <div class="btn-group">
        <a href="loa_list.php?project_id=<?= $project_id ?>" class="btn btn-back">← Back</a>
        <a href="add_loa.php?project_id=<?= $project_id ?>&loa_id=<?= $loa_id ?>" class="btn btn-edit">✏️ Edit LOA</a>
        <?php
        $user_role = strtolower($_SESSION['account_type'] ?? '');
        if ($user_role == 'client admin' || $user_role == 'admin(qa/qc)'): ?>
            <a href="delete_loa.php?project_id=<?= $project_id ?>&loa_id=<?= $loa_id ?>"
                onclick="return confirm('Are you sure want to delete this LOA?');"
                class="btn btn-delete">
                🗑 Delete LOA
            </a>
        <?php endif; ?>
    </div>

</div>

<script>
function loadPdfPreview(containerId) {
    const container = document.getElementById(containerId);

    if (!container) return;

    const iframe = container.querySelector('iframe');

    if (!iframe) return;

    if (!iframe.getAttribute('src')) {
        iframe.setAttribute('src', iframe.getAttribute('data-src'));
    }

    container.style.display = 'block';
}

function toggleLoaPdfPreview() {
    const container = document.getElementById('loaPdfPreview');

    if (!container) return;

    if (container.style.display === 'none' || container.style.display === '') {
        loadPdfPreview('loaPdfPreview');
    } else {
        container.style.display = 'none';
    }
}

function toggleLoaNewAwardPdfPreview() {
    const container = document.getElementById('loaNewAwardPdfPreview');

    if (!container) return;

    if (container.style.display === 'none' || container.style.display === '') {
        loadPdfPreview('loaNewAwardPdfPreview');
    } else {
        container.style.display = 'none';
    }
}
</script>

</body>

</html>
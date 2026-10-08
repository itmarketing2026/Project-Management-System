<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}

require_once 'include/msqli_connect.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $db->prepare("
    SELECT wd.id, wd.title, wd.doc_date, wd.file_path, wd.created_at,
           u.full_name AS created_by
    FROM weeknd_documents wd
    LEFT JOIN users u ON wd.created_by = u.id
    WHERE wd.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$doc = $result->fetch_assoc();
$stmt->close();

if (!$doc) {
    die("Document not found.");
}

// Get full ordered list of ids to determine prev/next
$stmt2 = $db->prepare("SELECT id FROM weeknd_documents ORDER BY doc_date DESC, id DESC");
$stmt2->execute();
$result2 = $stmt2->get_result();
$all_ids = [];
while ($r = $result2->fetch_assoc()) {
    $all_ids[] = $r['id'];
}
$stmt2->close();

$current_index = array_search($id, $all_ids);
$prev_id = ($current_index !== false && $current_index > 0) ? $all_ids[$current_index - 1] : null;
$next_id = ($current_index !== false && $current_index < count($all_ids) - 1) ? $all_ids[$current_index + 1] : null;

$ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
$is_image = in_array($ext, ['jpg', 'jpeg', 'png']);
$is_pdf = $ext === 'pdf';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= htmlspecialchars($doc['title']) ?></title>

<style>
:root {
    --accent: #007bff;
    --frame-max: 1100px;
}
* { box-sizing: border-box; }

body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: #f3f4f6;
    font-family: Arial, sans-serif;
    padding: 24px;
}

.top-bar {
    width: 100%;
    max-width: var(--frame-max);
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.back-btn {
    background: var(--accent);
    color: #fff;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
}

.header-block {
    text-align: center;
    margin-bottom: 20px;
}

h1 {
    margin: 8px 0 6px;
    font-size: 30px;
    font-weight: 800;
    color: var(--accent);
    word-break: break-word;
}

.subtitle {
    color: #666;
    font-size: 14px;
}

.panel {
    width: 100%;
    max-width: var(--frame-max);
    background: #fff;
    border: 2px solid var(--accent);
    border-radius: 18px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    padding: 20px;
    min-height: 70vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.panel iframe {
    width: 100%;
    height: 75vh;
    border: none;
    border-radius: 10px;
}

.no-preview {
    text-align: center;
    color: #666;
    font-size: 15px;
}

.info-box {
    width: 100%;
    max-width: var(--frame-max);
    background: #fff;
    border: 2px solid var(--accent);
    border-radius: 14px;
    padding: 16px 20px;
    margin-bottom: 16px;
}

.info-heading-row {
    display: none;
}

.info-heading {
    margin: 0 0 14px;
    font-size: 14px;
    font-weight: bold;
    color: var(--accent);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #eee;
    padding-bottom: 8px;
}



.panel img {
    max-width: 100%;
    max-height: 75vh;
    border-radius: 10px;
    cursor: zoom-in;
    transition: transform 0.2s ease;
}

.panel img.zoomed {
    max-width: none;
    max-height: none;
    width: auto;
    cursor: zoom-out;
    transform: scale(1.8);
}

.panel {
    overflow: auto;
}

.zoom-hint {
    text-align: center;
    font-size: 12px;
    color: #999;
    margin-top: -10px;
    margin-bottom: 10px;
}

.info-row {
    display: flex;
    gap: 40px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.info-row:last-child {
    margin-bottom: 0;
}

.info-item label {
    display: block;
    font-size: 12px;
    font-weight: bold;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.info-item span {
    font-size: 16px;
    color: #222;
    font-weight: 600;
}

.nav-buttons {
    display: flex;
    align-items: center;
    gap: 12px;
}

.nav-position {
    font-size: 14px;
    font-weight: bold;
    color: #555;
    min-width: 50px;
    text-align: center;
}

.nav-btn {
    background: var(--accent);
    color: #fff;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    font-size: 14px;
}

.nav-btn:hover {
    background: #0056b3;
}

.nav-btn.disabled {
    background: #ccc;
    pointer-events: none;
    cursor: default;
}
</style>
</head>

<body>

<div class="top-bar">
    <a href="weeknd.php" class="back-btn">← Back</a>
    <div class="nav-buttons">
        <?php if ($prev_id): ?>
            <a href="preview_weeknd_document.php?id=<?= $prev_id ?>" class="nav-btn">← Previous</a>
        <?php else: ?>
            <span class="nav-btn disabled">← Previous</span>
        <?php endif; ?>

        <span class="nav-position"><?= $current_index + 1 ?> / <?= count($all_ids) ?></span>

        <?php if ($next_id): ?>
            <a href="preview_weeknd_document.php?id=<?= $next_id ?>" class="nav-btn">Next →</a>
        <?php else: ?>
            <span class="nav-btn disabled">Next →</span>
        <?php endif; ?>
    </div>
</div>

<div class="info-box">
    <h3 class="info-heading">General Information</h3>
    <div class="info-row">
        <div class="info-item">
            <label>Created By</label>
            <span><?= htmlspecialchars($doc['created_by']) ?></span>
        </div>
        <div class="info-item">
            <label>Date</label>
            <span><?= htmlspecialchars(date('Y-m-d H:i', strtotime($doc['created_at']))) ?></span>
        </div>
    </div>
    <div class="info-row">
        <div class="info-item">
            <label>Title</label>
            <span><?= htmlspecialchars($doc['title']) ?></span>
        </div>
        <div class="info-item">
            <label>Date</label>
            <span><?= htmlspecialchars(date('Y-m-d', strtotime($doc['doc_date']))) ?></span>
        </div>
    </div>
</div>

<div class="panel">
    <?php if ($is_pdf): ?>
        <iframe src="<?= htmlspecialchars($doc['file_path']) ?>"></iframe>
    <?php elseif ($is_image): ?>
        <img id="previewImg" src="<?= htmlspecialchars($doc['file_path']) ?>" alt="<?= htmlspecialchars($doc['title']) ?>" onclick="this.classList.toggle('zoomed')">
    <?php else: ?>
        <div class="no-preview">Preview not available for this file type.</div>
    <?php endif; ?>
</div>

<?php if ($is_image): ?>
    <div class="zoom-hint">Click image to zoom in / out</div>
<?php endif; ?>

<script>
const prevUrl = <?= $prev_id ? "'preview_weeknd_document.php?id=" . $prev_id . "'" : 'null' ?>;
const nextUrl = <?= $next_id ? "'preview_weeknd_document.php?id=" . $next_id . "'" : 'null' ?>;

document.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowLeft' && prevUrl) {
        window.location.href = prevUrl;
    } else if (e.key === 'ArrowRight' && nextUrl) {
        window.location.href = nextUrl;
    }
});
</script>

</body>
</html>
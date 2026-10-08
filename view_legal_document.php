<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}

require_once 'include/msqli_connect.php';

$role        = $_SESSION['account_type'] ?? '';
$designation = $_SESSION['designation'] ?? '';

$can_access = (
    strtolower($role) === 'client admin' ||
    strtolower(trim($designation)) === 'contract executive'
);

if (!$can_access) {
    header("Location: dashboard.php");
    exit();
}

$id         = intval($_GET['id'] ?? 0);
$project_id = intval($_GET['project_id'] ?? 0);
if ($id <= 0 || $project_id <= 0) die("Invalid request.");

// Fetch document
$stmt = $db->prepare("SELECT * FROM legal_documents WHERE id = ? AND project_id = ? LIMIT 1");
$stmt->bind_param("ii", $id, $project_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$doc) die("Document not found.");

// Fetch project name
$stmt = $db->prepare("SELECT project_name FROM project WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Project Management System</title>
<style>
  :root {
    --accent: #007bff;
    --frame-max: 700px;
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
  h1 {
    margin: 8px 0 24px;
    font-size: 32px;
    font-weight: 800;
    color: var(--accent);
    text-align: center;
  }
  .back-btn {
    align-self: flex-start;
    margin-bottom: 16px;
    background: var(--accent);
    color: #fff;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: bold;
    text-decoration: none;
  }
  .back-btn:hover { background: #0056b3; }
  .form-container {
    width: 100%;
    max-width: var(--frame-max);
    background: #fff;
    border: 3px solid var(--accent);
    border-radius: 20px;
    padding: 30px;
    display: flex;
    flex-direction: column;
    gap: 20px;
  }
  label {
    font-weight: bold;
    margin-bottom: 6px;
    display: block;
    color: #333;
  }
  .field-value {
    padding: 10px 14px;
    background: #f8f9fa;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 15px;
    color: #333;
  }
  .doc-preview {
    background: #f0f7ff;
    border: 1px solid #c8e0ff;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .doc-preview a {
    color: var(--accent);
    font-weight: 600;
    text-decoration: none;
  }
  .doc-preview a:hover { text-decoration: underline; }
  .file-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 16px;
    margin-top: 8px;
  }
  .file-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 160px;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
    text-decoration: none;
    transition: 0.2s;
    padding: 15px;
  }
  .file-card:hover {
    background: #e0f2fe;
    border-color: #38bdf8;
  }
  .file-icon {
    font-size: 48px;
    margin-bottom: 10px;
  }
  .file-label {
    font-size: 12px;
    text-align: center;
    word-break: break-word;
    color: #374151;
  }
</style>
</head>
<body>

<a href="legal_project.php?project_id=<?= $project_id ?>" class="back-btn">← Back</a>

<h1><?= htmlspecialchars($project['project_name']) ?></h1>

<div class="form-container">

  <div>
    <label>Title</label>
    <div class="field-value"><?= htmlspecialchars($doc['title']) ?></div>
  </div>

  <div>
    <label>Date</label>
    <div class="field-value"><?= date('d/m/Y', strtotime($doc['doc_date'])) ?></div>
  </div>

  <div>
    <label>Document</label>
    <?php if (!empty($doc['file_path'])): ?>
      <div class="file-grid">
        <?php
          $files = explode(',', $doc['file_path']);
          foreach ($files as $file):
            if (!trim($file)) continue;
            $file = trim($file);
            $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $icon = '📄';
            if ($ext === 'pdf') $icon = '📕';
            elseif (in_array($ext, ['jpg','jpeg','png'])) $icon = '🖼️';
            elseif (in_array($ext, ['doc','docx'])) $icon = '📘';
        ?>
        <a href="download_file.php?file=<?= urlencode(basename($file)) ?>" target="_blank" class="file-card">
          <div class="file-icon"><?= $icon ?></div>
          <div class="file-label"><?= htmlspecialchars(mb_strimwidth(basename($file), 0, 40, '...')) ?></div>
        </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div style="color:#aaa; font-size:14px; padding:10px 0;">No document uploaded.</div>
    <?php endif; ?>
  </div>

</div>

</body>
</html>
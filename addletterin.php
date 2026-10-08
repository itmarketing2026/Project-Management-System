<?php

session_start();

$title = "Add Letter In";

require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$project_id = intval($_GET['project_id'] ?? 0);
if (!$project_id) {
    die("Invalid project selected.");
}

$stmt_verifiers = $db->prepare("
    SELECT DISTINCT u.id AS user_id, u.full_name
    FROM users u
    LEFT JOIN verifier_project vp ON vp.user_id = u.id
    WHERE 
        u.id = 17
        OR (
            vp.project_id = ?
            AND (
                u.id IN (44, 57)
                OR u.designation = 'Project Manager'
            )
        )
    ORDER BY u.full_name ASC
");
$stmt_verifiers->bind_param("i", $project_id);
$stmt_verifiers->execute();
$result_verifiers = $stmt_verifiers->get_result();
$verifiers = [];
while ($row = $result_verifiers->fetch_assoc()) {
    $verifiers[] = $row;
}
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
    transition: background 0.2s;
  }
  .back-btn:hover { background: #0056b3; }
  .form-container {
    width: 100%;
    max-width: var(--frame-max);
    background: #fff;
    border: 3px solid var(--accent);
    border-radius: 20px;
    padding: 30px;
  }
  form { display: flex; flex-direction: column; gap: 20px; }
  label { font-weight: bold; margin-bottom: 6px; display: block; color: #333; }
  input, select, textarea {
    width: 100%;
    padding: 10px;
    border: 2px solid var(--accent);
    border-radius: 8px;
    font-size: 16px;
  }
  button {
    background: var(--accent);
    color: #fff;
    padding: 12px;
    font-size: 18px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: bold;
    transition: background 0.2s;
  }
  button:hover { background: #0056b3; }
</style>
</head>
<body>

<a href="letterin_list.php?project_id=<?= $project_id ?>" class="back-btn">← Back</a>

<h1><?= htmlspecialchars($title) ?></h1>

<div class="form-container">
  <form action="save_letter.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="project_id" value="<?= $project_id ?>">
	  <input type="hidden" name="created_by" value="<?= $_SESSION['user_id'] ?>">

    <div>
      <label for="title">Title</label>
      <input type="text" id="title" name="title" required>
    </div>

    <div>
      <label for="received_date">Letter Received Date</label>
      <input type="date" id="received_date" name="received_date" required>
    </div>

    <div>
      <label for="reference_number">Reference Number</label>
      <input type="text" id="reference_number" name="reference_number" required>
    </div>

    <div>
      <label for="letter_date">Letter Date</label>
      <input type="date" id="letter_date" name="letter_date" required>
    </div>

    <div>
      <label for="letter_from">From Agency</label>
      <input type="text" id="letter_from" name="letter_from" required>
    </div>

    <div>
      <label for="receive_mail">Received Mail Through</label>
      <select id="receive_mail" name="receive_mail" required>
        <option value="">-- Select Method --</option>
        <option value="By Hand">Hand Delivery</option>
        <option value="Email">Email</option>
        <option value="Pos">Pos</option>
        <option value="Others">Others</option>
      </select>
    </div>

    <div>
      <label for="remarks">Remarks</label>
      <textarea id="remarks" name="remarks" rows="3"></textarea>
    </div>

    <div>
      <label for="verifier">Verifier</label>
      <select id="verifier" name="select_verifier" required>
        <option value="">-- Select Verifier --</option>
        <?php foreach($verifiers as $verifier): ?>
          <option value="<?= htmlspecialchars($verifier['user_id']) ?>">
            <?= htmlspecialchars($verifier['full_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
		<label for="document">Upload Document</label>
		<input type="file" id="document" name="document[]" multiple required style="height:auto; padding:8px;">
		<small style="color:#666; font-size:13px; margin-top:4px; display:block;">
			You can select multiple files. Max size: <?= ini_get('upload_max_filesize') ?>
		</small>
	</div>

    <button type="submit">Submit</button>
  </form>
</div>

<?php
$stmt_verifiers->close();
$db->close();
?>
</body>
</html>

<?php
require_once 'include/msqli_connect.php';

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'];
    $projects = isset($_POST['projects']) ? $_POST['projects'] : [];

    // Reset existing access
    $conn->query("DELETE FROM user_project WHERE user_id = $user_id");

    // Insert new access
    $stmt = $conn->prepare("INSERT INTO user_project (user_id, project_id) VALUES (?, ?)");
    foreach ($projects as $project_id) {
        $stmt->bind_param("ii", $user_id, $project_id);
        $stmt->execute();
    }

    echo "<script>alert('Access updated successfully!');</script>";
}

// Fetch users and projects
$users = $conn->query("SELECT * FROM user ORDER BY username");
$projects = $conn->query("SELECT * FROM project");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Assign Project to User</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body {
    background-color: #f3f6fa;
    font-family: 'Segoe UI', sans-serif;
  }

  .container {
    max-width: 900px;
  }

  .main-card {
    background: #fff;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 6px 16px rgba(0,0,0,0.08);
  }

  .section-title {
    color: #007bff;
    font-weight: 700;
    text-align: center;
    font-size: 26px;
  }

  .project-box {
    border: 2px solid #007bff;
    border-radius: 15px;
    text-align: center;
    padding: 10px;
    background: #fff;
    transition: all 0.2s ease;
  }

  .project-box:hover {
    background: #eaf3ff;
    transform: translateY(-3px);
  }

  .project-img {
    width: 100%;
    height: 130px;
    object-fit: cover;
    border-radius: 10px;
  }

  .project-name {
    margin-top: 8px;
    font-weight: 600;
  }

  .btn-primary {
    border-radius: 10px;
  }

  .back-btn {
    border-radius: 10px;
  }
</style>
</head>
<body class="p-4">

<div class="container main-card">
  <a href="dashboard.php" class="btn btn-outline-primary mb-3 back-btn">← Back</a>

  <h3 class="section-title mb-4">Assign Project to User</h3>

  <form method="POST" action="">
    <div class="mb-3">
      <label class="form-label fw-semibold">Select User</label>
      <select name="user_id" id="userSelect" class="form-select" required onchange="loadUserProjects(this.value)">
        <option value="">-- Choose User --</option>
        <?php while ($u = $users->fetch_assoc()): ?>
          <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>

    <label class="form-label fw-semibold mb-2">Select Projects</label>
    <div id="projectList" class="row g-3">
      <?php 
        mysqli_data_seek($projects, 0);
        while ($p = $projects->fetch_assoc()): 
      ?>
        <div class="col-md-4 col-sm-6">
          <label class="project-box d-block">
            <img src="<?= $p['image_url'] ?>" class="project-img" alt="">
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="projects[]" value="<?= $p['id'] ?>" id="proj<?= $p['id'] ?>">
              <label class="project-name form-check-label" for="proj<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></label>
            </div>
          </label>
        </div>
      <?php endwhile; ?>
    </div>

    <button type="submit" class="btn btn-primary w-100 mt-4">Save Access</button>
  </form>
</div>

<script>
async function loadUserProjects(userId) {
  if (!userId) return;
  const response = await fetch('get_user_projects.php?user_id=' + userId);
  const data = await response.json();

  // Uncheck all
  document.querySelectorAll('input[name="projects[]"]').forEach(cb => cb.checked = false);

  // Check assigned ones
  data.forEach(pid => {
    const cb = document.getElementById('proj' + pid);
    if (cb) cb.checked = true;
  });
}
</script>
</body>
</html>

<?php
// createproject.php
$title = "Create New Project";
require_once 'include/msqli_connect.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $project_name = trim($_POST['project_name'] ?? '');
    $prefix = trim($_POST['prefix'] ?? '');
    $category = trim($_POST['category'] ?? '');

    // ✅ validation (PROPERTY tak wajib prefix)
    if (!$project_name || !$category || ($category !== 'PROPERTY' && !$prefix)) {
        $message = "⚠️ Please fill in all required fields.";
    } else {

        // Auto-generate Project Code (PRJ001, PRJ002, etc.)
        $result = $db->query("SELECT project_code FROM project ORDER BY id DESC LIMIT 1");
        $new_code = 'PRJ001';

        if ($result && $row = $result->fetch_assoc()) {
            if (preg_match('/PRJ(\d+)/', $row['project_code'], $matches)) {
                $new_code = 'PRJ' . str_pad(((int)$matches[1]) + 1, 3, '0', STR_PAD_LEFT);
            }
        }

        $stmt = $db->prepare("
            INSERT INTO project (project_name, project_code, prefix, category, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "ssss",
            $project_name,
            $new_code,
            $prefix,
            $category
        );

        if ($stmt->execute()) {
            header("Location: dashboard.php?created=1");
            exit;
        } else {
            $message = "❌ Database error: " . $stmt->error;
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Project Management System</title>

<style>
* { box-sizing: border-box; }

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f3f4f6;
    margin: 0;
    padding: 40px;
    display: flex;
    justify-content: center;
}

.container {
    background: #ffffff;
    padding: 35px;
    width: 100%;
    max-width: 450px;
    border-radius: 16px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
}

h2 {
    text-align: center;
    margin-top: 0;
    margin-bottom: 25px;
    color: #1e293b;
    font-weight: 800;
}

label {
    font-weight: 700;
    margin-top: 18px;
    display: block;
    color: #475569;
    font-size: 13px;
    text-transform: uppercase;
}

input[type="text"], select {
    width: 100%;
    padding: 12px 15px;
    margin-top: 8px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    font-size: 15px;
}

button {
    width: 100%;
    margin-top: 30px;
    background: #0066cc;
    color: white;
    border: none;
    padding: 14px;
    font-size: 16px;
    font-weight: 700;
    border-radius: 8px;
    cursor: pointer;
}

button:hover {
    background: #004d99;
}

.message {
    margin-bottom: 20px;
    text-align: center;
    padding: 12px;
    border-radius: 8px;
    background: #fee2e2;
    color: #991b1b;
}

.back-link {
    display: block;
    margin-top: 25px;
    text-align: center;
    text-decoration: none;
    color: #64748b;
}
</style>
</head>

<body>

<div class="container">
    <h2><?php echo $title; ?></h2>

    <?php if ($message): ?>
        <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST">

        <label>Project Name</label>
       <input type="text" name="project_name" 
       placeholder="Enter formal project name" 
       oninput="this.value = this.value.toUpperCase()" 
       required>

        <label>Category</label>
        <select name="category" id="category" required>
            <option value="" disabled selected>-- Select Type --</option>
            <option value="INFRA">🏗️ INFRA</option>
            <option value="RTB">🌊 RTB</option>
            <option value="CERUN">⛰️ CERUN</option>
            <option value="PROPERTY">🏢 PROPERTY</option>
        </select>

        <label>Prefix Code</label>
        <input type="text" name="prefix" id="prefix" placeholder="e.g. JKR/CRN/2026" required>

        <button type="submit">Create Project</button>
    </form>

    <a href="dashboard.php" class="back-link">← Return to Dashboard</a>
</div>

<script>
const category = document.getElementById('category');
const prefix = document.getElementById('prefix');

category.addEventListener('change', function () {
    if (this.value === 'PROPERTY') {
        prefix.removeAttribute('required');
        prefix.value = '';
        prefix.disabled = true;
    } else {
        prefix.setAttribute('required', 'required');
        prefix.disabled = false;
    }
});
</script>

</body>
</html>
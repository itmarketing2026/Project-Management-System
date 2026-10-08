<?php
session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

// PROCESS UPDATE
if (isset($_POST['update'])) {

    $result = $db->query("SELECT id, running_number FROM letteroutarchive_cerun");

    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $rn = $row['running_number'];
        $project_year = null;

        // Case 1: SEL2022 format
        if (preg_match('/SEL(\d{4})/', $rn, $m)) {
            $project_year = $m[1];
        }
        // Case 2: .../2024/... format
        elseif (preg_match('/\/(\d{4})\//', $rn, $m)) {
            $project_year = $m[1];
        }

        if ($project_year) {
            $stmt = $db->prepare("UPDATE letteroutarchive_cerun SET project_year=? WHERE id=?");
            $stmt->bind_param("ii", $project_year, $id);
            $stmt->execute();
        }
    }

    $msg = "✅ Update completed!";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Update Project Year</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="card shadow rounded-4 p-4">
        <h3 class="mb-3">🔧 Update Project Year (Archive)</h3>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-success"><?= $msg ?></div>
        <?php endif; ?>

        <p class="text-muted">
            This will scan all <b>running_number</b> and auto-fill <b>project_year</b>.
        </p>

        <form method="POST">
            <button type="submit" name="update" class="btn btn-primary px-4">
                Run Update
            </button>
        </form>
    </div>
</div>

</body>
</html>
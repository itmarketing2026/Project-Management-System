<?php
session_start();

// ------------------ SECURE PAGE ------------------
if (!isset($_SESSION['user_id']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}

// ------------------ ERROR HANDLING ------------------
if ($_SERVER['SERVER_NAME'] === 'localhost') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

require_once 'include/msqli_connect.php';

// ------------------ FLASH MESSAGE ------------------
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

$project_id = intval($_GET['id'] ?? 0);
if ($project_id <= 0) die("Invalid project");


// UPDATE PROJECT INFO
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_project'])
    && $_SESSION['account_type'] === 'Client Admin') {

    $project_name = trim($_POST['project_name'] ?? '');
    $prefix       = trim($_POST['prefix'] ?? '');
    $category     = trim($_POST['category'] ?? '');
    $status       = trim($_POST['status'] ?? '');
    $is_legal     = isset($_POST['is_legal']) ? 1 : 0;

    if ($project_name !== '' && $prefix !== '' && $category !== '' && $status !== '') {

        $stmt = $conn->prepare(
            "UPDATE project SET project_name=?, prefix=?, status=?, category=?, is_legal=? WHERE id=?"
        );
        $stmt->bind_param("ssssii", $project_name, $prefix, $status, $category, $is_legal, $project_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success_message'] = "✅ Project updated successfully!";
        header("Location: update_project.php?id=".$project_id);
        exit;
    }
}

// ADD AGENCY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_agency'])) {

    $agency = trim($_POST['new_agency']);

    if ($agency !== '') {
        $stmt = $conn->prepare(
            "INSERT INTO project_agency (project_id, agency_name)
             VALUES (?, ?)"
        );
        $stmt->bind_param("is", $project_id, $agency);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success_message'] = "🏢 Agency added successfully!";
    }

    header("Location: update_project.php?id=".$project_id);
    exit;
}

// DELETE AGENCY
if (isset($_GET['delete_agency'])) {

    $agency_id = intval($_GET['delete_agency']);

    $stmt = $conn->prepare(
        "DELETE FROM project_agency WHERE id=?"
    );
    $stmt->bind_param("i", $agency_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success_message'] = "🗑️ Agency removed successfully!";

    header("Location: update_project.php?id=".$project_id);
    exit;
}

// FETCH PROJECT
$stmt = $conn->prepare(
    "SELECT project_name, prefix, category, status, is_legal
     FROM project
     WHERE id=?"
);
$stmt->bind_param("i", $project_id);
$stmt->execute();

$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) die("Project not found");

// FETCH AGENCIES
$stmt = $conn->prepare(
    "SELECT id, agency_name
     FROM project_agency
     WHERE project_id=?
     ORDER BY agency_name ASC"
);
$stmt->bind_param("i", $project_id);
$stmt->execute();

$agencies = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
<title>Edit Project</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background:#f3f6fa;
    font-family:'Segoe UI',sans-serif;
}

.card {
    max-width:600px;
    margin:5% auto;
    border-radius:20px;
    box-shadow:0 8px 20px rgba(0,0,0,.08);
}

.badge-agency {
    background:#007bff;
    padding:8px 12px;
    border-radius:20px;
    font-size:13px;
}

label {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 4px;
}

</style>
</head>

<body>

<div class="card p-4">

    <h3 class="text-center fw-bold text-primary mb-4">
        Edit Project
    </h3>

    <!-- SUCCESS MESSAGE -->
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show text-center">
            <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>


    <!-- PROJECT INFO -->
    <form method="POST">

        <div class="mb-4">

            <label class="fw-semibold">Project Name</label>
            <input
                class="form-control mb-3"
                name="project_name"
                value="<?= htmlspecialchars($project['project_name']) ?>"
                <?= ($_SESSION['account_type'] !== 'Client Admin') ? 'disabled' : '' ?>
            >

            <div class="row">

                <div class="col-md-6">
                    <label class="fw-semibold">Prefix</label>
                    <input
                        class="form-control"
                        name="prefix"
                        value="<?= htmlspecialchars($project['prefix']) ?>"
                        <?= ($_SESSION['account_type'] !== 'Client Admin') ? 'disabled' : '' ?>
                    >
                </div>

                <div class="col-md-6">
                    <label class="fw-semibold">Category</label>

                    <?php if ($_SESSION['account_type'] === 'Client Admin'): ?>

                        <select name="category" class="form-control" required>
                            <option value="Infra" <?= ($project['category'] === 'Infra') ? 'selected' : '' ?>>Infra</option>
                            <option value="RTB"   <?= ($project['category'] === 'RTB')   ? 'selected' : '' ?>>RTB</option>
                            <option value="Cerun" <?= ($project['category'] === 'Cerun') ? 'selected' : '' ?>>Cerun</option>
                        </select>

                    <?php else: ?>

                        <input
                            class="form-control"
                            value="<?= htmlspecialchars($project['category']) ?>"
                            disabled
                        >

                    <?php endif; ?>
                </div>

                <div class="col-md-6 mt-3">
                    <label class="fw-semibold">Status</label>

                    <?php if ($_SESSION['account_type'] === 'Client Admin'): ?>

                        <select name="status" class="form-control" required>
                            <option value="Ongoing"   <?= ($project['status'] === 'Ongoing') ? 'selected' : '' ?>>Ongoing</option>
                            <option value="Completed" <?= ($project['status'] === 'Completed') ? 'selected' : '' ?>>Completed</option>
                        </select>

                    <?php else: ?>

                        <input
                            class="form-control"
                            value="<?= htmlspecialchars($project['status']) ?>"
                            disabled
                        >

                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- LEGAL TOGGLE -->
		<div class="mb-4">
			<div style="border-left:4px solid #007bff; padding:6px 12px; background:#f8f9fa; border-radius:0 8px 8px 0; display:inline-flex; align-items:center; gap:8px;">
				<input type="checkbox" name="is_legal" id="legalToggle"
					<?= $project['is_legal'] ? 'checked' : '' ?>
					<?= ($_SESSION['account_type'] !== 'Client Admin') ? 'disabled' : '' ?>>
				<label for="legalToggle" style="font-size:13px; font-weight:600; margin:0; cursor:pointer;">⚖️ Legal Section</label>
			</div>
		</div>

        <?php if ($_SESSION['account_type'] === 'Client Admin'): ?>
            <button type="submit" name="save_project"
                    class="btn btn-success mb-3">
                Save Project Info
            </button>
        <?php endif; ?>

    </form>

    <hr>

    <!-- PROJECT AGENCIES -->
    <h6 class="fw-bold mb-2">Project Agencies</h6>

    <div class="mb-3">

        <?php if ($agencies->num_rows > 0): ?>

            <?php while ($a = $agencies->fetch_assoc()): ?>

                <span class="badge badge-agency me-1 mb-1">
                    <?= htmlspecialchars($a['agency_name']) ?>

                    <?php if ($_SESSION['account_type'] === 'Client Admin'): ?>
                        <a href="?id=<?= $project_id ?>&delete_agency=<?= $a['id'] ?>"
                           class="text-white ms-1 text-decoration-none"
                           onclick="return confirm('Remove agency?')">×</a>
                    <?php endif; ?>

                </span>

            <?php endwhile; ?>

        <?php else: ?>

            <p class="text-muted fst-italic">
                No agency added yet.
            </p>

        <?php endif; ?>

    </div>

    <?php if ($_SESSION['account_type'] === 'Client Admin'): ?>

        <form method="POST" class="d-flex gap-2 mb-3">
            <input
                type="text"
                name="new_agency"
                class="form-control"
                placeholder="Add new agency (e.g. MNDC)"
                required
            >
            <button class="btn btn-primary">Add</button>
        </form>

    <?php endif; ?>

    <div class="mt-4 text-center">
        <a href="projectlist.php" class="btn btn-outline-secondary">
            Back to Project List
        </a>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
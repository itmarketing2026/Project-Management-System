<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'include/msqli_connect.php';

$project_id = intval($_GET['project_id'] ?? 0);
if ($project_id <= 0) die("Invalid project ID");


// =====================
// GET PROJECT INFO
// =====================
$stmt = $conn->prepare("
    SELECT project_name, prefix 
    FROM project 
    WHERE id = ?
");

if (!$stmt) {
    die("Prepare failed (project): " . $conn->error);
}

$stmt->bind_param("i", $project_id);
$stmt->execute();

$result = $stmt->get_result();
$project = $result->fetch_assoc();
$stmt->close();

if (!$project) die("Project not found");


// =====================
// ADD NEW AGENCY
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_agency'])) {

    $agency = trim($_POST['new_agency']);

    if ($agency !== '') {

        $stmt = $conn->prepare("
            INSERT INTO project_agency (project_id, agency_name)
            VALUES (?, ?)
        ");

        if (!$stmt) {
            die("Prepare failed (insert): " . $conn->error);
        }

        $stmt->bind_param("is", $project_id, $agency);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: update_projectstaff.php?project_id=".$project_id);
    exit;
}


// =====================
// DELETE AGENCY
// =====================
if (isset($_GET['delete_agency'])) {

    $agency_id = intval($_GET['delete_agency']);

    $stmt = $conn->prepare("
        DELETE FROM project_agency 
        WHERE id = ? AND project_id = ?
    ");

    if (!$stmt) {
        die("Prepare failed (delete): " . $conn->error);
    }

    $stmt->bind_param("ii", $agency_id, $project_id);
    $stmt->execute();
    $stmt->close();

    header("Location: update_projectstaff.php?project_id=".$project_id);
    exit;
}


// =====================
// GET AGENCY LIST
// =====================
$stmt = $conn->prepare("
    SELECT id, agency_name 
    FROM project_agency 
    WHERE project_id = ?
    ORDER BY agency_name ASC
");

if (!$stmt) {
    die("Prepare failed (agency list): " . $conn->error);
}

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
    max-width:650px;
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

.badge-agency a {
    color:#fff;
    margin-left:6px;
    text-decoration:none;
    font-weight:bold;
}
</style>

</head>
<body>

<div class="card p-4">

    <h3 class="text-center fw-bold text-primary mb-4">
        Edit Project
    </h3>

    <!-- PROJECT INFO -->
    <div class="mb-4">

        <label class="fw-semibold">Project Name</label>
        <input class="form-control" 
               value="<?= htmlspecialchars($project['project_name']) ?>" 
               disabled>

        <label class="fw-semibold mt-3">Prefix</label>
        <input class="form-control" 
               value="<?= htmlspecialchars($project['prefix']) ?>" 
               disabled>

    </div>


    <!-- AGENCY LIST -->
    <h6 class="fw-bold mb-2">Project Agencies</h6>

    <div class="mb-3">

        <?php if ($agencies->num_rows > 0): ?>

            <?php while ($a = $agencies->fetch_assoc()): ?>

                <span class="badge badge-agency me-1 mb-1">

                    <?= htmlspecialchars($a['agency_name']) ?>

                    <a 
                        href="?project_id=<?= $project_id ?>&delete_agency=<?= $a['id'] ?>"
                        onclick="return confirm('Remove agency?')"
                    >×</a>

                </span>

            <?php endwhile; ?>

        <?php else: ?>

            <p class="text-muted fst-italic">
                No agency added yet.
            </p>

        <?php endif; ?>

    </div>


    <!-- ADD NEW AGENCY -->
    <form method="POST" class="d-flex gap-2">

        <input 
            type="text" 
            name="new_agency"
            class="form-control"
            placeholder="Add new agency (e.g. MNDC)"
            required
        >

        <button class="btn btn-primary">
            Add
        </button>

    </form>


    <div class="mt-4 text-center">

      <a href="projectregister.php?project_id=<?= $project_id ?>" 
   class="btn btn-outline-secondary">
   Back to Project List
</a>

    </div>

</div>

</body>
</html>
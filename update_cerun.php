<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'include/msqli_connect.php';

$cerun_id = intval($_GET['id'] ?? 0);
if ($cerun_id <= 0) die("Invalid Cerun ID");

$stmt = $db->prepare("SELECT region FROM cerun WHERE id = ?");
$stmt->bind_param("i", $cerun_id);
$stmt->execute();
$cerun = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cerun) die("Region not found");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_agency'])) {
    $agency = trim($_POST['new_agency']);

    if ($agency !== '') {
        $stmt = $db->prepare("
            INSERT INTO cerun_agency (cerun_id, agency_name)
            VALUES (?, ?)
        ");
        $stmt->bind_param("is", $cerun_id, $agency);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: update_cerun.php?id=".$cerun_id);
    exit;
}

if (isset($_GET['delete_agency'])) {
    $agency_id = intval($_GET['delete_agency']);
    $stmt = $db->prepare("DELETE FROM cerun_agency WHERE id = ?");
    $stmt->bind_param("i", $agency_id);
    $stmt->execute();
    $stmt->close();
    header("Location: update_cerun.php?id=".$cerun_id);
    exit;
}

$stmt = $db->prepare("
    SELECT id, agency_name 
    FROM cerun_agency 
    WHERE cerun_id = ?
    ORDER BY agency_name ASC
");
$stmt->bind_param("i", $cerun_id);
$stmt->execute();
$agencies = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Cerun Agencies</title>
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
            border: none;
        }
        .badge-agency {
            background:#007bff;
            padding:8px 12px;
            border-radius:20px;
            font-size:13px;
            color: white;
            display: inline-block;
        }
    </style>
</head>
<body>

<div class="card p-4">
    <h3 class="text-center fw-bold text-primary mb-4">Edit Cerun</h3>

    <div class="mb-4">
        <label class="fw-semibold">Region</label>
        <input class="form-control" value="<?= htmlspecialchars($cerun['region']) ?>" disabled>
    </div>

    <h6 class="fw-bold mb-2">Cerun Agencies</h6>

    <div class="mb-3">
        <?php if ($agencies->num_rows > 0): ?>
            <?php while ($a = $agencies->fetch_assoc()): ?>
                <span class="badge badge-agency me-1 mb-1">
                    <?= htmlspecialchars($a['agency_name']) ?>
                    <a 
                        href="?id=<?= $cerun_id ?>&delete_agency=<?= $a['id'] ?>" 
                        class="text-white ms-1 text-decoration-none fw-bold"
                        onclick="return confirm('Remove agency?')"
                    >×</a>
                </span>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-muted fst-italic">No agency added yet.</p>
        <?php endif; ?>
    </div>

    <form method="POST" class="d-flex gap-2">
        <input 
            type="text" 
            name="new_agency" 
            class="form-control"
            placeholder="Add new agency (e.g. JKR)"
            required
        >
        <button class="btn btn-primary">Add</button>
    </form>

    <div class="mt-4 text-center">
        <a href="cerun_list.php" class="btn btn-outline-secondary">
            Back to Cerun List
        </a>
    </div>
</div>

</body>
</html>
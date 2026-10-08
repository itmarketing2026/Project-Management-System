<?php
session_start();
require_once 'include/msqli_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$user_role = strtolower(trim($_SESSION['account_type'] ?? ''));
$project_id = (int)($_GET['project_id'] ?? 0);

if ($project_id <= 0) {
    die("Invalid project.");
}

/* ROLE */
$is_verifier = ($user_role == 'verifier');
/* ===============================
   TASK COUNT
================================ */
$stmt1 = $db->prepare("
    SELECT 
    (
        SELECT COUNT(*) 
        FROM letter_out_pending
        WHERE status = 'acknowledged'
        AND project_id = ?
    )
    +
    (
        SELECT COUNT(*) 
        FROM letters_in
        WHERE status = 'pending'
        AND project_id = ?
    )
    AS total
");

$stmt1->bind_param("ii", $project_id, $project_id);
$stmt1->execute();

$verifier_total = $stmt1->get_result()->fetch_assoc()['total'] ?? 0;


/* ===============================
   TASK LIST
================================ */
$stmt5 = $db->prepare("
    SELECT * FROM (

        -- LETTER OUT (guna verifier_id)
        SELECT 
            l.id,
            l.running_number,
            l.title,
            l.status,
            l.created_at,
            v.full_name AS action_by,
            'OUT' AS type
        FROM letter_out_pending l
        LEFT JOIN users v ON v.id = l.verifier_id
        WHERE l.status = 'acknowledged'
        AND l.project_id = ?

        UNION ALL

        -- LETTER IN (guna select_verifier)
        SELECT 
            li.id,
            li.reference_number AS running_number,
            li.title,
            li.status,
            li.created_at,
            u.full_name AS action_by,
            'IN' AS type
        FROM letters_in li
        LEFT JOIN users u ON u.id = li.select_verifier
        WHERE li.status = 'pending'
        AND li.project_id = ?

    ) AS combined
    ORDER BY combined.created_at DESC
   
");

$stmt5->bind_param("ii", $project_id, $project_id);
$stmt5->execute();
$list = $stmt5->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Project Management System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.workspace {
    width: 100%;
    max-width: 1200px;
    background: #fff;
    border: 3px solid #007bff;
    border-radius: 20px;
    padding: 30px;
    margin-top: 20px;
}
</style>
</head>

<body>

<?php include 'include/topbarstaff.php'; ?>

<div class="workspace container">

    <h3 class="fw-bold mb-4">📊 Dashboard Overview</h3>

    <!-- CARD -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center shadow">
                <div class="card-body">
                    <h6>Pending Task</h6>
                    <h2><?= $verifier_total ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="card shadow">
        <div class="card-body">
            <h5>Latest Tasks</h5>

            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Running No</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Action By</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($list->num_rows > 0): ?>
                    <?php while($row = $list->fetch_assoc()): ?>
                    <tr>

                        <td>
                            <?= $row['type'] == 'OUT'
                                ? '<span class="badge bg-primary">OUT</span>'
                                : '<span class="badge bg-success">IN</span>' ?>
                        </td>

                        <td><?= htmlspecialchars($row['running_number'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['title'] ?? '') ?></td>

                        <td>
                        <?php if ($row['type'] == 'OUT'): ?>
                            <span class="badge bg-success">Acknowledged</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php endif; ?>
                        </td>

                        <td><?= htmlspecialchars($row['action_by'] ?? '-') ?></td>

                        <td>
                        <?php
                        if ($row['type'] == 'OUT') {
                            $link = "viewletteroutacknowledged.php?id=".$row['id']."&project_id=".$project_id;
                        } else {
                            $link = "viewletterin.php?id=".$row['id']."&project_id=".$project_id;
                        }
                        ?>
                        <a href="<?= $link ?>" class="btn btn-sm btn-primary">
                            Open
                        </a>
                        </td>

                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            No tasks found
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>

            </table>

        </div>
    </div>

</div>

</body>
</html>
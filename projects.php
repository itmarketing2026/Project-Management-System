<?php
require_once 'include/msqli_connect.php';

$result = $db->query("
    SELECT p.*, COUNT(up.user_id) total_users
    FROM project p
    LEFT JOIN user_project up ON p.id = up.project_id
    GROUP BY p.id
    ORDER BY p.project_name
");
?>

<?php include 'header.php'; ?>

<h3>All Projects</h3>

<table class="table table-bordered bg-white shadow-sm">
<thead>
<tr>
    <th>Project</th>
    <th>Code</th>
    <th>Users</th>
    <th>Action</th>
</tr>
</thead>
<tbody>
<?php while ($p = $result->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($p['project_name']) ?></td>
    <td><?= htmlspecialchars($p['project_code']) ?></td>
    <td><?= $p['total_users'] ?></td>
    <td>
        <a href="assign_project.php?project_id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">
            Assign User
        </a>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

<a href="dashboard.php" class="btn btn-secondary">Back</a>

<?php include 'footer.php'; ?>

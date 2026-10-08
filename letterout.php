<?php
session_start();

$title = "Letters Out";

require_once 'include/msqli_connect.php'; // Make sure this defines $db (mysqli 

// Grab project_id safely
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
if ($project_id <= 0) {
    die("Invalid project ID.");
}

// ---------- FETCH PENDING + SUBMITTED ----------
$query_pending = "
SELECT id, form_id, running_number, request_date, created_at, title, agency, status
FROM letter_out_pending_csr
WHERE status IN ('pending','number_assigned') AND project_id = ?
ORDER BY id DESC
";

if (!$stmt_pending = $db->prepare($query_pending)) {
    die("Prepare failed (pending): " . $db->error);
}
$stmt_pending->bind_param("i", $project_id);
$stmt_pending->execute();
$result_pending = $stmt_pending->get_result();

// ---------- FETCH PROGRESS ----------
$query_progress = "
SELECT id, running_number, request_date, title, agency, status
FROM letter_out_pending_csr
WHERE status IN ('acknowledged','verified_user','verified_admin','cancelled','rejected') AND project_id = ?
ORDER BY id DESC
";

if (!$stmt_progress = $db->prepare($query_progress)) {
    die("Prepare failed (progress): " . $db->error);
}
$stmt_progress->bind_param("i", $project_id);
$stmt_progress->execute();
$result_progress = $stmt_progress->get_result();
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Project Management System</title>
<style>
body { font-family: Arial, sans-serif; background:#f3f4f6; margin:0; padding:24px; display:flex; flex-direction:column; align-items:center; }
h1 { color:#007bff; font-size:32px; font-weight:800; margin-bottom:24px; }
.section { width:100%; max-width:1100px; background:#fff; border:3px solid #007bff; border-radius:20px; padding:20px; margin-bottom:30px; }
.section h2 { color:#007bff; margin-top:0; }
.btn { background:#007bff; color:#fff; padding:10px 18px; border-radius:8px; text-decoration:none; font-weight:bold; display:inline-block; margin-bottom:12px; }
.btn:hover { background:#0056b3; }
table { width:100%; border-collapse:collapse; }
th, td { padding:10px 12px; border:1px solid #ddd; }
th { background:#007bff; color:#fff; }
tr:nth-child(even) { background:#f9f9f9; }
tr:hover { background:#eef5ff; cursor:pointer; }
.status-badge { padding:5px 12px; border-radius:6px; font-size:13px; font-weight:bold; color:#fff; }
.badge-pending { background:#a0522d; } /* brown */
.badge-submitted { background:#007bff; } /* blue */
.badge-acknowledged { background:#28a745; } /* green */
.badge-verified-user { background:#17a2b8; } /* teal */
.badge-verified-admin { background:#6610f2; } /* purple */
.badge-cancelled { background:#6c757d; } /* gray */
.badge-rejected { background:#dc3545; } /* red */
.badge-positive { background:#28a745; }
.badge-negative { background:#dc3545; }
</style>
</head>
<body>

<?php include 'include/topbarstaff.php'; ?>

<h1><?= htmlspecialchars($title) ?></h1>

<!-- ========================= -->
<!-- PENDING SECTION -->
<!-- ========================= -->
<div class="section">
  <h2>Letter Out – Pending Acknowledge</h2>
 <a href="add_letter_pending_staff.php?project_id=<?= $project_id ?>" 
   class="btn btn-primary">
   + Add Pending Letter
</a>
  <table>
    <thead>
      <tr>
        <th>Running No / Form ID</th>
        <th>Request Date</th>
        <th>Title</th>
        <th>Agency</th>
        <th>Status</th>
        <th>Balance</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result_pending && $result_pending->num_rows > 0): ?>
        <?php while ($row = $result_pending->fetch_assoc()): ?>
          <?php
          // ===== BALANCE CALCULATION =====
          $startBalance = 5;
          $createdDate = new DateTime($row['created_at']);
          $createdDate->setTime(0,0,0);
          $today = new DateTime('today');
          $daysPassed = $createdDate->diff($today)->days;
          $balance = $startBalance - $daysPassed;
          $balanceText = $balance < 0 ? '- ' . abs($balance) : $balance;
          $balanceClass = $balance < 0 ? 'badge-negative' : 'badge-positive';

          // ===== STATUS =====
          $isSubmitted = $row['status'] === 'number_assigned';
          $statusClass = $isSubmitted ? 'badge-submitted' : 'badge-pending';
          $statusText = $isSubmitted ? 'Submitted' : 'Pending';

          $displayNumber = $row['running_number'] ?: $row['form_id'];
          $target = $isSubmitted
            ? "viewletteroutsubmittedcsr.php?id={$row['id']}"
            : "viewletteroutpendingcsr.php?id={$row['id']}";
          ?>
          <tr onclick="window.location='<?= $target ?>'">
            <td><?= htmlspecialchars($displayNumber) ?></td>
            <td><?= htmlspecialchars($row['request_date']) ?></td>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= htmlspecialchars($row['agency']) ?></td>
            <td><span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span></td>
            <td><span class="status-badge <?= $balanceClass ?>"><?= $balanceText ?></span></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="6" style="text-align:center;">No pending letters found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ========================= -->
<!-- ACKNOWLEDGE / PROGRESS SECTION -->
<!-- ========================= -->
<div class="section">
  <h2>Letter Out – Progress / Acknowledge</h2>
  <table>
    <thead>
      <tr>
        <th>Running Number</th>
        <th>Request Date</th>
        <th>Title</th>
        <th>Agency</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result_progress && $result_progress->num_rows > 0): ?>
        <?php while ($row = $result_progress->fetch_assoc()): ?>
          <?php
          $status = $row['status'];
          $badgeMap = [
            'acknowledged' => 'badge-acknowledged',
            'verified_user' => 'badge-verified-user',
            'verified_admin' => 'badge-verified-admin',
            'cancelled' => 'badge-cancelled',
            'rejected' => 'badge-rejected'
          ];
          $statusClass = $badgeMap[$status] ?? 'badge-pending';
          $target = "viewletteroutacknowledgedcsr.php?id={$row['id']}";
          ?>
          <tr onclick="window.location='<?= $target ?>'">
            <td><?= htmlspecialchars($row['running_number'] ?: '-') ?></td>
            <td><?= htmlspecialchars($row['request_date']) ?></td>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= htmlspecialchars($row['agency']) ?></td>
            <td><span class="status-badge <?= $statusClass ?>"><?= ucfirst(str_replace('_',' ',$status)) ?></span></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="5" style="text-align:center;">No acknowledge or verified letters yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

</body>
</html>

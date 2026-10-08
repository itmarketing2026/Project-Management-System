<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$full_name = trim($_SESSION['full_name'] ?? '');
$role      = strtolower(trim($_SESSION['account_type'] ?? ''));

$is_qaqc        = strpos($role, 'qa/qc') !== false;
$is_client_admin = strpos($role, 'client admin') !== false;

/* =========================
   COUNT - LETTER IN
========================= */

if ($is_client_admin) {

    $q1 = $db->query("SELECT COUNT(*) total FROM letters_in WHERE status IN ('pending','submitted')");
    $project_total = $q1->fetch_assoc()['total'];

    $q2 = $db->query("SELECT COUNT(*) total FROM letterin_cerun WHERE status IN ('pending','submitted')");
    $cerun_total = $q2->fetch_assoc()['total'];

    $letter_pending = $project_total + $cerun_total;

} elseif ($is_qaqc) {

    $q1 = $db->prepare("
        SELECT COUNT(*) total
        FROM letters_in l
        INNER JOIN user_project up ON up.project_id = l.project_id
        WHERE up.user_id = ? AND l.status IN ('pending','submitted')
    ");
    $q1->bind_param("i", $user_id);
    $q1->execute();
    $project_total = $q1->get_result()->fetch_assoc()['total'];
    $q1->close();

    $q2 = $db->prepare("
        SELECT COUNT(*) total
        FROM letterin_cerun l
        INNER JOIN user_cerun uc ON uc.cerun_id = l.cerun_id
        WHERE uc.user_id = ? AND l.status IN ('pending','submitted')
    ");
    $q2->bind_param("i", $user_id);
    $q2->execute();
    $cerun_total = $q2->get_result()->fetch_assoc()['total'];
    $q2->close();

    $letter_pending = $project_total + $cerun_total;

} else {

    $q1 = $db->prepare("
        SELECT COUNT(*) total FROM letters_in
        WHERE select_verifier = ? AND status IN ('pending','submitted')
    ");
    $q1->bind_param("i", $user_id);
    $q1->execute();
    $project_total = $q1->get_result()->fetch_assoc()['total'];
    $q1->close();

    $q2 = $db->prepare("
        SELECT COUNT(*) total FROM letterin_cerun
        WHERE verifier = ? AND status IN ('pending','submitted')
    ");
    $q2->bind_param("s", $full_name);
    $q2->execute();
    $cerun_total = $q2->get_result()->fetch_assoc()['total'];
    $q2->close();

    $letter_pending = $project_total + $cerun_total;
}


/* =========================
   COUNT - LETTER OUT
========================= */

if ($is_client_admin) {

    // PROJECT
    $q1 = $db->query("
        SELECT COUNT(*) total
        FROM letter_out_pending
        WHERE status IN ('submitted','acknowledged')
    ");
    $project_total = $q1->fetch_assoc()['total'];

    // CERUN
    $q2 = $db->query("
        SELECT COUNT(*) total
        FROM letteroutpending_cerun
        WHERE status IN ('submitted','acknowledged')
    ");
    $cerun_total = $q2->fetch_assoc()['total'];

    $letterout_pending = $project_total + $cerun_total;

} elseif ($is_qaqc) {

    // PROJECT
    $q1 = $db->prepare("
        SELECT COUNT(*) total
        FROM letter_out_pending l
        INNER JOIN user_project up
            ON up.project_id = l.project_id
        WHERE up.user_id = ?
        AND l.status IN ('submitted','acknowledged')
    ");
    $q1->bind_param("i", $user_id);
    $q1->execute();
    $project_total = $q1->get_result()->fetch_assoc()['total'];
    $q1->close();

    // CERUN
    $q2 = $db->prepare("
        SELECT COUNT(*) total
        FROM letteroutpending_cerun l
        INNER JOIN user_cerun uc
            ON uc.cerun_id = l.cerun_id
        WHERE uc.user_id = ?
        AND l.status IN ('submitted','acknowledged')
    ");
    $q2->bind_param("i", $user_id);
    $q2->execute();
    $cerun_total = $q2->get_result()->fetch_assoc()['total'];
    $q2->close();

    $letterout_pending = $project_total + $cerun_total;

} else {

    // PROJECT
    $q1 = $db->prepare("
        SELECT COUNT(*) total
        FROM letter_out_pending
        WHERE created_by = ?
        AND status IN ('submitted','acknowledged')
    ");
    $q1->bind_param("s", $full_name);
    $q1->execute();
    $project_total = $q1->get_result()->fetch_assoc()['total'];
    $q1->close();

    // CERUN
    // Task is assigned to the verifier through verifier_id.
    $q2 = $db->prepare("
        SELECT COUNT(*) total
        FROM letteroutpending_cerun
        WHERE verifier_id = ?
        AND status IN ('submitted','acknowledged')
    ");
    $q2->bind_param("i", $user_id);
    $q2->execute();
    $cerun_total = $q2->get_result()->fetch_assoc()['total'];
    $q2->close();

    $letterout_pending = $project_total + $cerun_total;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Task</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body {
    background: #f4f6f9;
    font-family: 'Segoe UI', sans-serif;
}
.page-title {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 30px;
}
.task-card {
    border: none;
    border-radius: 20px;
    padding: 28px;
    background: white;
    box-shadow: 0 5px 18px rgba(0,0,0,.05);
}
.task-icon {
    width: 58px;
    height: 58px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 16px;
}
.icon-blue  { background: #e8f1ff; color: #0d6efd; }
.icon-orange { background: #fff0e8; color: #fd7e14; }
.task-number {
    font-size: 42px;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 10px;
}
.task-title { font-size: 16px; font-weight: 600; color: #333; }
.task-desc  { font-size: 13px; color: #777; margin-top: 6px; }
.table th   { font-size: 13px; }
.table td   { vertical-align: middle; }
.badge-type { font-size: 11px; padding: 6px 10px; border-radius: 30px; }

.nav-tabs .nav-link {
    border-radius: 12px 12px 0 0;
    font-weight: 600;
    color: #555;
}
.nav-tabs .nav-link.active {
    color: #0d6efd;
    border-bottom-color: white;
}
</style>
</head>
<body>

<div class="container py-5">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="page-title">📋 My Task</div>
        
		<?php
$is_management = in_array($role, ['client admin', 'management', 'hra']);
$back_url = $is_management ? 'dashboard.php' : 'staff_dashboard.php';
?>
<a href="<?= $back_url ?>" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left"></i> Back
</a>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">

        <div class="col-md-4">
            <div class="task-card">
                <div class="task-icon icon-blue">
                    <i class="bi bi-envelope-paper"></i>
                </div>
                <div class="task-number text-primary"><?= $letter_pending ?></div>
                <div class="task-title">Letter In Pending</div>
                <div class="task-desc">Waiting for verification</div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="task-card">
                <div class="task-icon icon-orange">
                    <i class="bi bi-envelope-arrow-up"></i>
                </div>
                <div class="task-number" style="color:#fd7e14;"><?= $letterout_pending ?></div>
                <div class="task-title">Letter Out Pending</div>
                <div class="task-desc">Waiting for acknowledgement</div>
            </div>
        </div>

    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-0" id="taskTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active"
                    id="tab-letterin"
                    data-bs-toggle="tab"
                    data-bs-target="#letterInTab"
                    type="button"
                    role="tab"
                    aria-controls="letterInTab"
                    aria-selected="true">
                <i class="bi bi-envelope-paper"></i> Letter In
                <span class="badge bg-primary ms-1"><?= $letter_pending ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link"
                    id="tab-letterout"
                    data-bs-toggle="tab"
                    data-bs-target="#letterOutTab"
                    type="button"
                    role="tab"
                    aria-controls="letterOutTab"
                    aria-selected="false">
                <i class="bi bi-envelope-arrow-up"></i> Letter Out
                <span class="badge bg-warning text-dark ms-1"><?= $letterout_pending ?></span>
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="taskTabContent">

        <!-- ========================
             LETTER IN TAB
        ========================= -->
        <div class="tab-pane fade show active" id="letterInTab" role="tabpanel" aria-labelledby="tab-letterin">
        <div class="task-card" style="border-radius: 0 20px 20px 20px;">

            <h5 class="mb-4">📌 Letter In Pending List</h5>

            <div class="table-responsive">
            <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>Type</th>
                <th>Project / Cerun</th>
                <th>Title</th>
                <th>Date</th>
                <th>Status</th>
                <?php if ($is_client_admin || $is_qaqc): ?>
                    <th>Created By</th>
                    <th>Verifier</th>
                <?php endif; ?>
                <th width="120"></th>
            </tr>
            </thead>
            <tbody>

            <?php

            $rows = [];

            if ($is_client_admin) {

                $q_project = $db->query("
                    SELECT l.id, l.title, l.received_date, l.status, l.select_verifier, l.created_by,
                           l.project_id, p.project_name,
                           u.full_name AS created_by_name
                    FROM letters_in l
                    LEFT JOIN project p ON p.id = l.project_id
                    LEFT JOIN users u ON u.id = l.created_by
                    WHERE l.status IN ('pending','submitted')
                ");
                while ($r = $q_project->fetch_assoc()) {
                    $r['type'] = 'project';
                    $rows[] = $r;
                }

                $q_cerun = $db->query("
                    SELECT l.id, l.title, l.receive_date as received_date, l.status, l.verifier,
                           l.created_by, l.cerun_id, c.region,
                           u.full_name AS created_by_name
                    FROM letterin_cerun l
                    LEFT JOIN cerun c ON c.id = l.cerun_id
                    LEFT JOIN users u ON u.id = l.created_by
                    WHERE l.status IN ('pending','submitted')
                ");
                while ($r = $q_cerun->fetch_assoc()) {
                    $r['type'] = 'cerun';
                    $r['project_id']   = $r['cerun_id'];
                    $r['project_name'] = $r['region'];
                    $rows[] = $r;
                }

            } elseif ($is_qaqc) {

                $q_project = $db->prepare("
                    SELECT l.id, l.title, l.received_date, l.status, l.select_verifier, l.created_by,
                           l.project_id, p.project_name,
                           u.full_name AS created_by_name
                    FROM letters_in l
                    LEFT JOIN project p ON p.id = l.project_id
                    LEFT JOIN users u ON u.id = l.created_by
                    INNER JOIN user_project up ON up.project_id = l.project_id
                    WHERE up.user_id = ? AND l.status IN ('pending','submitted')
                ");
                $q_project->bind_param("i", $user_id);
                $q_project->execute();
                $res1 = $q_project->get_result();
                while ($r = $res1->fetch_assoc()) {
                    $r['type'] = 'project';
                    $rows[] = $r;
                }
                $q_project->close();

                $q_cerun = $db->prepare("
                    SELECT l.id, l.title, l.receive_date as received_date, l.status, l.verifier,
                           l.created_by, l.cerun_id, c.region,
                           u.full_name AS created_by_name
                    FROM letterin_cerun l
                    LEFT JOIN cerun c ON c.id = l.cerun_id
                    LEFT JOIN users u ON u.id = l.created_by
                    INNER JOIN user_cerun uc ON uc.cerun_id = l.cerun_id
                    WHERE uc.user_id = ? AND l.status IN ('pending','submitted')
                ");
                $q_cerun->bind_param("i", $user_id);
                $q_cerun->execute();
                $res2 = $q_cerun->get_result();
                while ($r = $res2->fetch_assoc()) {
                    $r['type'] = 'cerun';
                    $r['project_id']   = $r['cerun_id'];
                    $r['project_name'] = $r['region'];
                    $rows[] = $r;
                }
                $q_cerun->close();

            } else {

                $q = $db->prepare("
                    SELECT l.id, l.title, l.received_date, l.status, l.select_verifier, l.project_id, p.project_name
                    FROM letters_in l
                    LEFT JOIN project p ON p.id = l.project_id
                    WHERE l.select_verifier = ? AND l.status IN ('pending','submitted')
                ");
                $q->bind_param("i", $user_id);
                $q->execute();
                $res3 = $q->get_result();
                while ($r = $res3->fetch_assoc()) {
                    $r['type'] = 'project';
                    $rows[] = $r;
                }
                $q->close();

                $q2 = $db->prepare("
                    SELECT l.id, l.title, l.receive_date as received_date, l.status, l.verifier, l.cerun_id, c.region
                    FROM letterin_cerun l
                    LEFT JOIN cerun c ON c.id = l.cerun_id
                    WHERE l.verifier = ? AND l.status IN ('pending','submitted')
                ");
                $q2->bind_param("s", $full_name);
                $q2->execute();
                $res4 = $q2->get_result();
                while ($r = $res4->fetch_assoc()) {
                    $r['type'] = 'cerun';
                    $r['project_id']   = $r['cerun_id'];
                    $r['project_name'] = $r['region'];
                    $rows[] = $r;
                }
                $q2->close();
            }

            usort($rows, function($a, $b) {
                return strtotime($b['received_date']) - strtotime($a['received_date']);
            });

            ?>

            <?php if (!empty($rows)): ?>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <?php if ($row['type'] === 'cerun'): ?>
                            <span class="badge bg-danger badge-type">CERUN</span>
                        <?php else: ?>
                            <span class="badge bg-primary badge-type">PROJECT</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($row['project_name']) ?></strong></td>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><?= $row['received_date'] ? date('d M Y', strtotime($row['received_date'])) : '-' ?></td>
                    <td>
                        <span class="badge bg-warning text-dark">
                            <?= ucfirst($row['status']) ?>
                        </span>
                    </td>

                    <?php if ($is_client_admin || $is_qaqc): ?>
                    <td>
                        <?= htmlspecialchars(
                            $row['created_by_name']
                                ?? ($row['created_by'] ?? '-')
                        ) ?>
                    </td>
                    <td>
                        <?php
                        if ($row['type'] === 'project') {
                            $v = $row['select_verifier'] ?? '';
                            if (!empty($v)) {
                                $u = $db->query("SELECT full_name FROM users WHERE id = '" . intval($v) . "'");
                                $user = $u->fetch_assoc();
                                $u->free();
                                echo htmlspecialchars($user['full_name'] ?? '-');
                            } else {
                                echo '-';
                            }
                        } else {
                            echo htmlspecialchars($row['verifier'] ?? '-');
                        }
                        ?>
                    </td>
                    <?php endif; ?>

                    <td>
                        <?php if ($row['type'] === 'cerun'): ?>
                            <a href="viewletteroutsubmitted_cerun.php?id=<?= (int)$row['id'] ?>"
                               class="btn btn-sm btn-dark">View</a>
                        <?php else: ?>
                            <a href="viewletterin.php?id=<?= (int)$row['id'] ?>&project_id=<?= (int)$row['project_id'] ?>"
                               class="btn btn-sm btn-dark">View</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= ($is_client_admin || $is_qaqc) ? 8 : 6 ?>" class="text-center text-muted py-4">
                        No pending letter in
                    </td>
                </tr>
            <?php endif; ?>

            </tbody>
            </table>
            </div>

        </div>
        </div>

        <!-- ========================
             LETTER OUT TAB
        ========================= -->
        <div class="tab-pane fade" id="letterOutTab" role="tabpanel" aria-labelledby="tab-letterout">
        <div class="task-card" style="border-radius: 0 20px 20px 20px;">

            <h5 class="mb-4">📌 Letter Out Pending List</h5>

            <div class="table-responsive">
            <table class="table table-hover align-middle">
            <thead>
            <tr>
                 <th>Type</th>
				<th>Project / Cerun</th>
                <th>Running No</th>
                <th>Title</th>
                <th>Date</th>
                <th>Status</th>
                <?php if ($is_client_admin || $is_qaqc): ?>
                    <th>Created By</th>
                    <th>Verified By</th>
                <?php endif; ?>
                <th width="120"></th>
            </tr>
            </thead>
            <tbody>

            <?php

            $out_rows = [];

if ($is_client_admin) {

    /* PROJECT */
    $q_project = $db->query("
        SELECT
            l.id,
            l.running_number,
            l.title,
            l.request_date,
            l.status,
            l.created_by,
            l.project_id,
            p.project_name,
            'project' AS type
        FROM letter_out_pending l
        LEFT JOIN project p ON p.id = l.project_id
        WHERE l.status IN ('submitted','acknowledged')
    ");

    while ($r = $q_project->fetch_assoc()) {
        $out_rows[] = $r;
    }

    /* CERUN */
    $q_cerun = $db->query("
        SELECT
            l.id,
            l.running_number,
            l.title,
            l.request_date,
            l.status,
            l.created_by,
            l.verifier_id,
            v.full_name AS verified_by,
            l.cerun_id AS project_id,
            c.region AS project_name,
            COALESCE(u.full_name, CAST(l.created_by AS CHAR)) AS created_by_name,
            'cerun' AS type
        FROM letteroutpending_cerun l
        LEFT JOIN cerun c ON c.id = l.cerun_id
        LEFT JOIN users u ON u.id = l.created_by
        LEFT JOIN users v ON v.id = l.verifier_id
        WHERE l.status IN ('submitted','acknowledged')
    ");

    while ($r = $q_cerun->fetch_assoc()) {
        $out_rows[] = $r;
    }

} elseif ($is_qaqc) {

    /* PROJECT */
    $q_project = $db->prepare("
        SELECT
            l.id,
            l.running_number,
            l.title,
            l.request_date,
            l.status,
            l.created_by,
            l.project_id,
            p.project_name,
            'project' AS type
        FROM letter_out_pending l
        LEFT JOIN project p ON p.id = l.project_id
        INNER JOIN user_project up ON up.project_id = l.project_id
        WHERE up.user_id = ?
        AND l.status IN ('submitted','acknowledged')
    ");

    $q_project->bind_param("i", $user_id);
    $q_project->execute();
    $res1 = $q_project->get_result();

    while ($r = $res1->fetch_assoc()) {
        $out_rows[] = $r;
    }

    /* CERUN */
    $q_cerun = $db->prepare("
        SELECT
            l.id,
            l.running_number,
            l.title,
            l.request_date,
            l.status,
            l.created_by,
            l.verifier_id,
            v.full_name AS verified_by,
            l.cerun_id AS project_id,
            c.region AS project_name,
            COALESCE(u.full_name, CAST(l.created_by AS CHAR)) AS created_by_name,
            'cerun' AS type
        FROM letteroutpending_cerun l
        LEFT JOIN cerun c ON c.id = l.cerun_id
        LEFT JOIN users u ON u.id = l.created_by
        LEFT JOIN users v ON v.id = l.verifier_id
        INNER JOIN user_cerun uc ON uc.cerun_id = l.cerun_id
        WHERE uc.user_id = ?
        AND l.status IN ('submitted','acknowledged')
    ");

    $q_cerun->bind_param("i", $user_id);
    $q_cerun->execute();
    $res2 = $q_cerun->get_result();

    while ($r = $res2->fetch_assoc()) {
        $out_rows[] = $r;
    }

} else {

    /* PROJECT */
    $q_project = $db->prepare("
        SELECT
            l.id,
            l.running_number,
            l.title,
            l.request_date,
            l.status,
            l.created_by,
            l.project_id,
            p.project_name,
            'project' AS type
        FROM letter_out_pending l
        LEFT JOIN project p ON p.id = l.project_id
        WHERE l.created_by = ?
        AND l.status IN ('submitted','acknowledged')
    ");

    $q_project->bind_param("s", $full_name);
    $q_project->execute();
    $res1 = $q_project->get_result();

    while ($r = $res1->fetch_assoc()) {
        $out_rows[] = $r;
    }

    /* CERUN */
    $q_cerun = $db->prepare("
        SELECT
            l.id,
            l.running_number,
            l.title,
            l.request_date,
            l.status,
            l.created_by,
            l.verifier_id,
            v.full_name AS verified_by,
            l.cerun_id AS project_id,
            c.region AS project_name,
            COALESCE(u.full_name, CAST(l.created_by AS CHAR)) AS created_by_name,
            'cerun' AS type
        FROM letteroutpending_cerun l
        LEFT JOIN cerun c ON c.id = l.cerun_id
        LEFT JOIN users u ON u.id = l.created_by
        LEFT JOIN users v ON v.id = l.verifier_id
        WHERE l.verifier_id = ?
        AND l.status IN ('submitted','acknowledged')
    ");

    $q_cerun->bind_param("i", $user_id);
    $q_cerun->execute();
    $res2 = $q_cerun->get_result();

    while ($r = $res2->fetch_assoc()) {
        $out_rows[] = $r;
    }
}

usort($out_rows, function ($a, $b) {
    return strtotime($b['request_date']) - strtotime($a['request_date']);
});

            if (!empty($out_rows)): ?>
                <?php foreach ($out_rows as $row): ?>
                <tr>
                   <td>
<?php if ($row['type'] === 'cerun'): ?>
    <span class="badge bg-danger">CERUN</span>
<?php else: ?>
    <span class="badge bg-primary">PROJECT</span>
<?php endif; ?>
</td>

<td>
    <strong><?= htmlspecialchars($row['project_name']) ?></strong>
</td>
                    <td><?= htmlspecialchars($row['running_number']) ?></td>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><?= $row['request_date'] ? date('d M Y', strtotime($row['request_date'])) : '-' ?></td>
                    <td>
                        <span class="badge bg-warning text-dark">
                            <?= ucfirst(str_replace('_', ' ', $row['status'])) ?>
                        </span>
                    </td>
                    <?php if ($is_client_admin || $is_qaqc): ?>
                        <td>
                            <?= htmlspecialchars(
                                $row['type'] === 'cerun'
                                    ? ($row['created_by_name'] ?? $row['created_by'] ?? '-')
                                    : ($row['created_by'] ?? '-')
                            ) ?>
                        </td>
                        <td>
                            <?php if ($row['type'] === 'cerun'): ?>
                                <?= htmlspecialchars($row['verified_by'] ?? '-') ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td>
 <?php if ($row['type'] === 'cerun'): ?>
                            <?php
                            $status = strtolower(trim($row['status'] ?? ''));

                            if ($status === 'acknowledged') {
                                $cerunViewUrl = 'viewletteroutacknowledged_cerun.php?id=' . (int)$row['id'];
                            } else {
                                $cerunViewUrl = 'viewletteroutsubmitted_cerun.php?id=' . (int)$row['id'];
                            }
                            ?>
                            <a href="<?= htmlspecialchars($cerunViewUrl) ?>"
                               class="btn btn-sm btn-dark">View</a>

<?php else: ?>

<a href="letterout_list.php?project_id=<?= (int)$row['project_id'] ?>"
   class="btn btn-sm btn-dark">
   View
</a>

<?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= ($is_client_admin || $is_qaqc) ? 8 : 6 ?>" class="text-center text-muted py-4">
                        No pending letter out
                    </td>
                </tr>
            <?php endif; ?>

            </tbody>
            </table>
            </div>

        </div>
        </div>

    </div><!-- end tab-content -->

</div><!-- end container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
session_start();
require_once 'include/msqli_connect.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    header("Location: login.php");
    exit;
}
$id = $_GET['id'] ?? null;
if (!$id) die("User ID is missing");
$user = $db->prepare("SELECT * FROM users WHERE id = ?");
$user->bind_param("i", $id);
$user->execute();
$userData = $user->get_result()->fetch_assoc();
if (!$userData) die("User not found");
/* ================= DESIGNATION ================= */
$designationRes = $db->query("SELECT name FROM designation ORDER BY name ASC");
$designations = [];
while ($row = $designationRes->fetch_assoc()) {
    $designations[] = $row['name'];
}
/* ================= DATA ================= */
// Normal projects (shown in the standard Project Access box,
// which is disabled/skipped for "full access" roles).
$projectsRes = $db->query("SELECT id, project_name FROM project WHERE is_restricted = 0 OR is_restricted IS NULL ORDER BY project_name ASC");
$projects = [];
while ($row = $projectsRes->fetch_assoc()) { $projects[] = $row; }

// Restricted projects (e.g. "The Weeknd") — these need an
// explicit grant in user_project regardless of account_type,
// so they get their own always-enabled section below.
$restrictedProjectsRes = $db->query("SELECT id, project_name FROM project WHERE is_restricted = 1 ORDER BY project_name ASC");
$restrictedProjects = [];
while ($row = $restrictedProjectsRes->fetch_assoc()) { $restrictedProjects[] = $row; }

$cerunRes = $db->query("SELECT id, region, category FROM cerun ORDER BY id ASC");
$cerun_data = [];
while ($row = $cerunRes->fetch_assoc()) { $cerun_data[$row['category']][] = $row; }
function getAssignedIds($db, $query, $userId) {
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $ids = [];
    while ($row = $res->fetch_assoc()) { $ids[] = array_values($row)[0]; }
    return $ids;
}
$assignedProjects = getAssignedIds($db, "SELECT project_id FROM user_project WHERE user_id = ?", $id);
$assignedCerun    = getAssignedIds($db, "SELECT cerun_id FROM user_cerun WHERE user_id = ?", $id);
$assignedVProj    = getAssignedIds($db, "SELECT project_id FROM verifier_project WHERE user_id = ?", $id);
$assignedVCerun   = getAssignedIds($db, "SELECT cerun_id FROM verifier_cerun WHERE user_id = ?", $id);
/* ===== DEACTIVATE / ACTIVATE ===== */
if (isset($_POST['toggle_status'])) {
    $new_status = ($_POST['toggle_status'] === 'active') ? 'inactive' : 'active';
    $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute();
    header("Location: update_user.php?id=$id&updated=1");
    exit;
}
/* ================= FORM ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* ===== DESIGNATION LOGIC ===== */
    if (!empty($_POST['designation_custom'])) {
        $designation = trim($_POST['designation_custom']);
        $checkDes = $db->prepare("SELECT id FROM designation WHERE name = ?");
        $checkDes->bind_param("s", $designation);
        $checkDes->execute();
        $checkDes->store_result();
        if ($checkDes->num_rows == 0) {
            $insertDes = $db->prepare("INSERT INTO designation (name) VALUES (?)");
            $insertDes->bind_param("s", $designation);
            $insertDes->execute();
        }
    } else {
        $designation = $_POST['designation_select'] ?? $userData['designation'];
    }
    $account_type     = $_POST['account_type'] ?? $userData['account_type'];
	$full_name = trim($_POST['full_name'] ?? $userData['full_name']);
    $is_full_access   = in_array($account_type, ['Client Admin', 'Management', 'HRA','GM']);
    $can_be_verifier  = !in_array($account_type, ['Standard User', 'Client Admin']);
    $verifier_role    = ($can_be_verifier && isset($_POST['verifier'])) ? 1 : 0;

    // Module Access toggles (checkboxes — isset() means checked)
    $property_access = isset($_POST['property_access']) ? 1 : 0;
    $legal_access    = isset($_POST['legal_access']) ? 1 : 0;
    $memo_access     = isset($_POST['memo_access']) ? 1 : 0;

    $projects_access   = $_POST['projects'] ?? [];
    $restricted_projects_access = $_POST['restricted_projects'] ?? [];
    $cerun_access      = $_POST['cerun_access'] ?? [];
    $verifier_projects = $_POST['verifier_projects'] ?? [];
    $verifier_cerun    = $_POST['verifier_cerun'] ?? [];
    $db->begin_transaction();
    try {
        // ✅ UPDATE USER (WITH DESIGNATION)
        $stmt = $db->prepare("
            UPDATE users
            SET full_name = ?,
                verifier = ?,
                account_type = ?,
                designation = ?,
                property_access = ?,
                legal_access = ?,
                memo_access = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
            "sissiiii",
            $full_name,
            $verifier_role,
            $account_type,
            $designation,
            $property_access,
            $legal_access,
            $memo_access,
            $id
        );
        $stmt->execute();
        // PROJECT
        // Normal (non-restricted) projects only apply to roles
        // that are NOT "full access" — those roles are assumed
        // to already see all normal projects some other way.
        // Restricted projects (e.g. "The Weeknd") are an explicit
        // override and always apply, regardless of account_type,
        // so a Management/Client Admin/HRA/GM user can still be
        // granted access to them individually.
        $db->query("DELETE FROM user_project WHERE user_id = $id");
        $projects_to_insert = [];
        if (!$is_full_access) {
            $projects_to_insert = $projects_access;
        }
        foreach ($restricted_projects_access as $rpid) {
            if (!in_array($rpid, $projects_to_insert)) {
                $projects_to_insert[] = $rpid;
            }
        }
        foreach ($projects_to_insert as $pid) {
            $st = $db->prepare("INSERT INTO user_project (user_id, project_id) VALUES (?, ?)");
            $st->bind_param("ii", $id, $pid);
            $st->execute();
        }
        // CERUN
        $db->query("DELETE FROM user_cerun WHERE user_id = $id");
        if (!$is_full_access) {
            foreach ($cerun_access as $cid) {
                $st = $db->prepare("INSERT INTO user_cerun (user_id, cerun_id) VALUES (?, ?)");
                $st->bind_param("ii", $id, $cid);
                $st->execute();
            }
        }
        // VERIFIER
        $db->query("DELETE FROM verifier_project WHERE user_id = $id");
        $db->query("DELETE FROM verifier_cerun WHERE user_id = $id");
        if ($verifier_role) {
            foreach ($verifier_projects as $vpid) {
                $st = $db->prepare("INSERT INTO verifier_project (user_id, project_id) VALUES (?, ?)");
                $st->bind_param("ii", $id, $vpid);
                $st->execute();
            }
            foreach ($verifier_cerun as $vcid) {
                $st = $db->prepare("INSERT INTO verifier_cerun (user_id, cerun_id) VALUES (?, ?)");
                $st->bind_param("ii", $id, $vcid);
                $st->execute();
            }
        }
        $db->commit();
        header("Location: update_user.php?id=$id&updated=1");
        exit;
    } catch (Exception $e) {
        $db->rollback();
        die("Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Project Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f3f6fa; font-family:'Segoe UI',sans-serif; }
        .main-card { background:#fff; border-radius:20px; padding:30px; max-width:950px; margin:3% auto; box-shadow:0 6px 16px rgba(0,0,0,.08); }
        .project-list { border:1px solid #dee2e6; border-radius:10px; padding:12px; background:#f8f9fa; height:280px; overflow-y:auto; }
        .verifier-section { background:#fff9f0; padding:20px; border-radius:15px; border:1px solid #ffeeba; margin-top:25px; }
        .restricted-section { background:#fff5f5; padding:20px; border-radius:15px; border:1px solid #f5c6cb; margin-top:25px; }
        .module-access-section { background:#f0f6ff; padding:20px; border-radius:15px; border:1px solid #cfe2ff; margin-top:10px; margin-bottom:20px; }
        .module-toggle { background:#fff; }
        .section-header { font-size:1.1rem; border-bottom:2px solid #e9ecef; padding-bottom:8px; margin-bottom:15px; }
        .disabled-zone { opacity:0.5; pointer-events:none; filter:grayscale(0.8); }
        .sub-region, .v-sub-region { margin-left:25px; border-left:2px solid #dee2e6; padding-left:10px; }
        hr { opacity:0.1; }
    </style>
</head>
<body class="p-4">
<div class="main-card">
    <h3 class="text-center text-primary mb-4 fw-bold">Update User Permissions</h3>
    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success text-center">✅ Permissions updated and saved!</div>
    <?php endif; ?>

	<?php $isActive = ($userData['status'] ?? 'active') === 'active'; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="badge fs-6 px-3 py-2 <?= $isActive ? 'bg-success' : 'bg-secondary' ?>">
        <?= $isActive ? '🟢 Active' : '⚫ Inactive' ?>
    </span>
    <form method="POST" onsubmit="return confirm('<?= $isActive ? 'Deactivate' : 'Activate' ?> this user?')">
        <input type="hidden" name="toggle_status" value="<?= htmlspecialchars($userData['status'] ?? 'active') ?>">
        <button type="submit" class="btn <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?> fw-bold">
            <?= $isActive ? '🚫 Deactivate User' : '✅ Activate User' ?>
        </button>
    </form>
</div>
    <form method="POST">
        <!-- Hidden field to carry account_type on submit -->
        <input type="hidden" name="account_type" id="accountTypeHidden" value="<?= htmlspecialchars($userData['account_type']) ?>">
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">Full Name</label>
               <input type="text"
       name="full_name"
       class="form-control"
       value="<?= htmlspecialchars($userData['full_name']) ?>"
       required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Email</label>
                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($userData['username']) ?>" readonly>
            </div>
        </div>

		<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label fw-semibold">Designation *</label>
       <select name="designation_select" id="designationSelect" class="form-select" required>
    <option value="">-- Select Designation --</option>
    <?php foreach ($designations as $d): ?>
        <option value="<?= htmlspecialchars($d) ?>"
            <?= ($userData['designation'] == $d) ? 'selected' : '' ?>>
            <?= htmlspecialchars($d) ?>
        </option>
    <?php endforeach; ?>
    <option value="Other">➕ Others (Add New)</option>
</select>
        <input type="text" name="designation_custom" id="designationCustom"
               class="form-control mt-2 d-none"
               placeholder="Enter new designation">
    </div>
</div>
        <!-- Editable Role Select (mirrors createacc) -->
        <div class="mb-4">
            <label class="form-label fw-semibold">Account Role</label>
            <select name="account_type" id="roleSelect" class="form-select">
                <?php
                $roles = ['Standard User', 'Management', 'Manager', 'Client Admin', 'Admin (QA/QC)', 'HRA','GM'];
                foreach ($roles as $role):
                ?>
                    <option value="<?= $role ?>" <?= $userData['account_type'] === $role ? 'selected' : '' ?>><?= $role ?></option>
                <?php endforeach; ?>
            </select>
            <small id="roleHint" class="text-primary d-none mt-1">✨ Full access role. Manual selection is optional.</small>
        </div>

        <!-- =========================================================
             MODULE ACCESS — always visible, per-user override.
             Works independently of Account Role, so specific
             individuals (e.g. a Management user) can be excluded
             from a module without changing their role.
             ========================================================= -->
        <div class="module-access-section">
            <div class="section-header text-primary fw-bold">Module Access</div>
            <div class="row g-3">

                <div class="col-md-4">
                    <div class="form-check form-switch module-toggle p-3 border rounded">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="property_access" value="1"
                               <?= ($userData['property_access'] ?? 0) == 1 ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold">Property Access</label>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-check form-switch module-toggle p-3 border rounded">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="legal_access" value="1"
                               <?= ($userData['legal_access'] ?? 0) == 1 ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold">Legal Access</label>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-check form-switch module-toggle p-3 border rounded">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="memo_access" value="1"
                               <?= ($userData['memo_access'] ?? 1) == 1 ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold">Memo Access</label>
                    </div>
                </div>

            </div>
        </div>

        <!-- Access Zones -->
        <div class="row" id="accessContainer">
            <div class="col-md-6">
                <div class="section-header text-primary fw-bold">Project Access</div>
                <div class="project-list">
                    <?php foreach ($projects as $p): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="projects[]" value="<?= $p['id'] ?>"
                                <?= in_array($p['id'], $assignedProjects) ? 'checked' : '' ?>>
                            <label class="form-check-label"><?= htmlspecialchars($p['project_name']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="section-header text-primary fw-bold">Cerun Access</div>
                <div class="project-list">
                    <?php if(isset($cerun_data['HQ'])): ?>
                        <?php foreach($cerun_data['HQ'] as $hq): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="cerun_access[]" value="<?= $hq['id'] ?>"
                                <?= in_array($hq['id'], $assignedCerun) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold"><?= $hq['region'] ?></label>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php foreach ($cerun_data as $cat => $items): if($cat == 'HQ') continue;
                        $cat_id = "cat_".strtolower(preg_replace('/[^a-z0-9]/i', '', $cat));
                    ?>
                        <hr>
                        <div class="form-check">
                            <input class="form-check-input parent" type="checkbox" data-target="<?= $cat_id ?>" id="par_<?= $cat_id ?>">
                            <label class="form-check-label fw-bold text-secondary" for="par_<?= $cat_id ?>"><?= $cat ?></label>
                        </div>
                        <div class="sub-region" id="<?= $cat_id ?>" style="display:none;">
                            <?php foreach ($items as $item): ?>
                                <div class="form-check">
                                    <input class="form-check-input child <?= $cat_id ?>" type="checkbox" name="cerun_access[]"
                                        value="<?= $item['id'] ?>" <?= in_array($item['id'], $assignedCerun) ? 'checked' : '' ?>>
                                    <label class="form-check-label"><?= $item['region'] ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Restricted Access — always enabled, regardless of
             account_type/role. Use this for legal-restricted
             projects like "The Weeknd" that need an explicit
             per-user grant even for Management/Client Admin/HRA/GM. -->
        <?php if (!empty($restrictedProjects)): ?>
        <div class="restricted-section">
            <div class="section-header text-danger fw-bold">🔒 Restricted Access</div>
            <p class="text-muted small mb-3">These projects are hidden by default and only visible to users explicitly granted access here — this applies regardless of the Account Role selected above.</p>
            <div class="project-list" style="height:auto; max-height:200px;">
                <?php foreach ($restrictedProjects as $rp): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="restricted_projects[]" value="<?= $rp['id'] ?>"
                            <?= in_array($rp['id'], $assignedProjects) ? 'checked' : '' ?>>
                        <label class="form-check-label"><?= htmlspecialchars($rp['project_name']) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Verifier Section -->
        <div class="verifier-section" id="verifierContainer">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="verifier" id="verifierToggle"
                    <?= $userData['verifier'] ? 'checked' : '' ?>>
                <label class="form-check-label fw-bold text-dark">Enable Verifier Role</label>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted">Verifier Project Scope</label>
                    <div class="project-list" style="height:180px;">
                        <?php foreach ($projects as $p): ?>
                            <div class="form-check">
                                <input class="form-check-input verifier-check" type="checkbox" name="verifier_projects[]"
                                    value="<?= $p['id'] ?>" <?= in_array($p['id'], $assignedVProj) ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= htmlspecialchars($p['project_name']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted">Verifier Cerun Scope</label>
                    <div class="project-list" style="height:180px;">
                        <?php if(isset($cerun_data['HQ'])): ?>
                            <?php foreach($cerun_data['HQ'] as $hq): ?>
                            <div class="form-check">
                                <input class="form-check-input verifier-check" type="checkbox" name="verifier_cerun[]"
                                    value="<?= $hq['id'] ?>" <?= in_array($hq['id'], $assignedVCerun) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold"><?= $hq['region'] ?></label>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php foreach ($cerun_data as $cat => $items): if($cat == 'HQ') continue;
                            $v_cat_id = "v_cat_".strtolower(preg_replace('/[^a-z0-9]/i', '', $cat));
                        ?>
                            <hr>
                            <div class="form-check">
                                <input class="form-check-input verifier-check v-parent" type="checkbox" data-v-target="<?= $v_cat_id ?>" id="vpar_<?= $v_cat_id ?>">
                                <label class="form-check-label fw-bold" for="vpar_<?= $v_cat_id ?>"><?= $cat ?></label>
                            </div>
                            <div class="v-sub-region" id="<?= $v_cat_id ?>" style="display:none;">
                                <?php foreach ($items as $item): ?>
                                    <div class="form-check">
                                        <input class="form-check-input verifier-check v-child <?= $v_cat_id ?>" type="checkbox"
                                            name="verifier_cerun[]" value="<?= $item['id'] ?>"
                                            <?= in_array($item['id'], $assignedVCerun) ? 'checked' : '' ?>>
                                        <label class="form-check-label"><?= $item['region'] ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <button class="btn btn-primary w-100 mt-4 py-3 fw-bold shadow-sm">Save Changes</button>
    </form>
    <a href="userlist.php" class="d-block text-center mt-3 text-muted small text-decoration-none">← Back to User List</a>
</div>
<script>
    const roleSelect        = document.getElementById('roleSelect');
    const accessContainer   = document.getElementById('accessContainer');
    const roleHint          = document.getElementById('roleHint');
    const verifierContainer = document.getElementById('verifierContainer');
    const toggle            = document.getElementById('verifierToggle');
    const vChecks           = document.querySelectorAll('.verifier-check');

    function handleRoleChange() {
        const role = roleSelect.value;

        if (['Client Admin', 'Management', 'HRA'].includes(role)) {
            accessContainer.classList.add('disabled-zone');
            roleHint.classList.remove('d-none');
        } else {
            accessContainer.classList.remove('disabled-zone');
            roleHint.classList.add('d-none');
        }
        if (['Client Admin', 'Standard User'].includes(role)) {
            verifierContainer.style.display = 'none';
            toggle.checked = false;
        } else {
            verifierContainer.style.display = 'block';
        }
        syncVerifier();
    }
    function syncVerifier() {
        vChecks.forEach(c => {
            c.disabled = !toggle.checked;
            if (!toggle.checked) c.checked = false;
        });
        if (!toggle.checked) {
            document.querySelectorAll('.v-sub-region').forEach(el => el.style.display = 'none');
        }
    }
    document.querySelectorAll('.parent').forEach(p => {
        p.addEventListener('change', function () {
            const target = document.getElementById(this.dataset.target);
            target.style.display = this.checked ? 'block' : 'none';
            target.querySelectorAll('.child').forEach(c => c.checked = this.checked);
        });
    });
    document.querySelectorAll('.v-parent').forEach(p => {
        p.addEventListener('change', function () {
            const target = document.getElementById(this.dataset.vTarget);
            target.style.display = this.checked ? 'block' : 'none';
            target.querySelectorAll('.v-child').forEach(c => c.checked = this.checked);
        });
    });
    function initToggles() {
        document.querySelectorAll('.sub-region').forEach(region => {
            if (region.querySelectorAll('.child:checked').length > 0) {
                region.style.display = 'block';
                const parentBox = document.querySelector(`[data-target="${region.id}"]`);
                if (parentBox) parentBox.checked = true;
            }
        });
        document.querySelectorAll('.v-sub-region').forEach(vRegion => {
            if (vRegion.querySelectorAll('.v-child:checked').length > 0) {
                vRegion.style.display = 'block';
                const vParentBox = document.querySelector(`[data-v-target="${vRegion.id}"]`);
                if (vParentBox) vParentBox.checked = true;
            }
        });
        handleRoleChange(); // apply role rules on first load
    }
    roleSelect.addEventListener('change', handleRoleChange);
    toggle.addEventListener('change', syncVerifier);
    window.onload = initToggles;

    const select = document.getElementById('designationSelect');
    const custom = document.getElementById('designationCustom');
    select.addEventListener('change', function () {
        if (this.value === 'Other') {
            custom.classList.remove('d-none');
            custom.required = true;
        } else {
            custom.classList.add('d-none');
            custom.required = false;
        }
    });
</script>

</body>
</html>
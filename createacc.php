<?php
session_start();

$current_page = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['user_id']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}

require_once 'include/msqli_connect.php';
$title = "Create Account";

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!$db) {
    die("❌ Database connection failed: " . mysqli_connect_error());
}

/* ================= COUNT ================= */
$res_total = $db->query("SELECT COUNT(*) as total FROM users");
$total_users = $res_total->fetch_assoc()['total'];

/* ================= DATA ================= */
$projectsRes = $db->query("SELECT id, project_name FROM project ORDER BY project_name ASC");
$projects = [];
while ($row = $projectsRes->fetch_assoc()) { $projects[] = $row; }

$cerunRes = $db->query("SELECT id, region, category FROM cerun ORDER BY id ASC");
$cerun_data = [];
while ($row = $cerunRes->fetch_assoc()) {
    $cerun_data[$row['category']][] = $row;
}

/*  DESIGNATION  */
$designationRes = $db->query("SELECT name FROM designation ORDER BY name ASC");
$designations = [];
while ($row = $designationRes->fetch_assoc()) {
    $designations[] = $row['name'];
}

$feedback = '';

/*  FORM  */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $account_type = $_POST['account_type'] ?? 'Standard User';

    /*  DESIGNATION LOGIC  */
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
        $designation = $_POST['designation_select'] ?? '';
    }

    $can_be_verifier = ($account_type !== 'Standard User' && $account_type !== 'Client Admin');
    $verifier = ($can_be_verifier && isset($_POST['verifier'])) ? 1 : 0;

    $projects_access = $_POST['projects'] ?? [];
    $cerun_access = $_POST['cerun_access'] ?? [];
    $verifier_projects = $_POST['verifier_projects'] ?? [];
    $verifier_cerun = $_POST['verifier_cerun'] ?? [];

    $is_full_access_role = ($account_type === 'Client Admin' || $account_type === 'Management' || $account_type === 'HRA');

    if ($password !== $confirm) {
        $feedback = "<script>alert('❌ Passwords do not match');</script>";
    } 
    elseif (!$is_full_access_role && empty($projects_access) && empty($cerun_access)) {
        $feedback = "<script>alert('⚠️ Please select at least one access (Project or Cerun)');</script>";
    } 
    else {

        $check = $db->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $feedback = "<script>alert('⚠️ Email already exists!');</script>";
        } else {

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare("
                INSERT INTO users (username, full_name, designation, password, account_type, verifier)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param("sssssi", 
                $username, 
                $full_name, 
                $designation, 
                $hashedPassword, 
                $account_type, 
                $verifier
            );

            if ($stmt->execute()) {

                $user_id = $db->insert_id;

                if (!empty($projects_access)) {
                    $upStmt = $db->prepare("INSERT INTO user_project (user_id, project_id) VALUES (?, ?)");
                    foreach ($projects_access as $pid) {
                        $upStmt->bind_param("ii", $user_id, $pid);
                        $upStmt->execute();
                    }
                }

                if (!empty($cerun_access)) {
                    $caStmt = $db->prepare("INSERT INTO user_cerun (user_id, cerun_id) VALUES (?, ?)");
                    foreach ($cerun_access as $cid) {
                        $caStmt->bind_param("ii", $user_id, $cid);
                        $caStmt->execute();
                    }
                }

                if ($verifier) {
                    if (!empty($verifier_projects)) {
                        $vpStmt = $db->prepare("INSERT INTO verifier_project (user_id, project_id) VALUES (?, ?)");
                        foreach ($verifier_projects as $vpid) {
                            $vpStmt->bind_param("ii", $user_id, $vpid);
                            $vpStmt->execute();
                        }
                    }
                    if (!empty($verifier_cerun)) {
                        $vcStmt = $db->prepare("INSERT INTO verifier_cerun (user_id, cerun_id) VALUES (?, ?)");
                        foreach ($verifier_cerun as $vcid) {
                            $vcStmt->bind_param("ii", $user_id, $vcid);
                            $vcStmt->execute();
                        }
                    }
                }

                $feedback = "<script>alert('✅ User created successfully!'); window.location='createacc.php';</script>";
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<title>Project Management System</title>

<style>
:root {
    --blue: #0066cc;
    --blue-light: #eef6ff;
    --bg: #f4f8fc;
    --white: #ffffff;
    --border: #e2e8f0;
    --text: #1e293b;
    --muted: #94a3b8;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: var(--bg);
    display: flex;
    flex-direction: column;
    height: 100vh;
}

/* HEADER */
.page-header {
    background: var(--blue);
    padding: 0 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 60px;
}

.page-header h1 {
    color: #fff;
    font-size: 18px;
}

.count-pill {
    background: rgba(255,255,255,0.2);
    color: #fff;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
}

/* LAYOUT */
.main-layout {
    display: flex;
    flex: 1;
}

/* CONTENT */
.wrapper {
    flex: 1;
    padding: 24px;
    overflow-y: auto;
}

/* TEAM GRID */
.team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
}

.team-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 18px;
    cursor: pointer;
    transition: 0.2s;
}

.team-card:hover {
    border-color: var(--blue);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}

.team-title {
    font-weight: 600;
    margin-bottom: 6px;
}

.team-sub {
    font-size: 12px;
    color: var(--muted);
}
	
	.team-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.team-badge {
    background: var(--blue-light);
    color: var(--blue);
    font-size: 11px;
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 600;
}
	

body { margin:0; background:#f3f6fa; font-family:'Segoe UI'; }

.page-header {
    background:#0066cc;
    height:60px;
    display:flex;
    align-items:center;
    padding:0 24px;
    color:white;
}

.user-count-pill {
    background:rgba(255,255,255,0.2);
    padding:4px 12px;
    border-radius:20px;
    font-size:12px;
}

.main-layout { display:flex; height:calc(100vh - 60px); }

.wrapper { flex:1; padding:24px; overflow:auto; }

.main-card {
    background:#fff;
    border-radius:20px;
    padding:30px;
    max-width:950px;
    margin:auto;
    box-shadow:0 6px 16px rgba(0,0,0,.08);
}

.project-list { border:1px solid #dee2e6; border-radius:10px; padding:12px; background:#f8f9fa; height:280px; overflow-y:auto; }
.verifier-section { background:#fff9f0; padding:20px; border-radius:15px; border:1px solid #ffeeba; margin-top:25px; }
.section-header { font-size:1.1rem; border-bottom:2px solid #e9ecef; padding-bottom:8px; margin-bottom:15px; }
.disabled-zone { opacity:0.5; pointer-events:none; }
hr { opacity:0.1; }
</style>

</head>

<body>

<header class="page-header">
    <div style="display:flex; gap:12px; align-items:center;">
        <a href="dashboard.php" style="color:white;">
            <i class="bi bi-grid-3x3-gap-fill"></i>
        </a>
        <h1>Document Hub</h1>

    </div>

    <i class="bi bi-person-circle" style="color:white; font-size:22px;"></i>
</header>

<div class="main-layout">

<?php require_once 'include/sidebar.php'; ?>

<main class="wrapper">

<div class="main-card">

<h3 class="text-center text-primary mb-4 fw-bold">Create New User</h3>
<?= $feedback ?>
    <form method="POST">
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Email *</label><input type="email" name="username" class="form-control" required></div>
            <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Password *</label><input type="password" name="password" class="form-control" required></div>
            <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Confirm Password *</label><input type="password" name="confirm" class="form-control" required></div>
        </div>
		
		<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label fw-semibold">Designation *</label>

        <select name="designation_select" id="designationSelect" class="form-select" required>
            <option value="">-- Select Designation --</option>

            <?php foreach ($designations as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>">
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

        <div class="mb-4">
            <label class="form-label fw-semibold">Account Role</label>
            <select name="account_type" id="roleSelect" class="form-select">
                <option>Standard User</option>
                <option>Management</option>
                <option>Manager</option>
                <option>Client Admin</option>
                <option>Admin (QA/QC)</option>
                <option>HRA</option>
            </select>
            <small id="roleHint" class="text-primary d-none mt-1">✨ Full access role. Manual selection is optional.</small>
        </div>

        <div class="row" id="accessContainer">
            <div class="col-md-6">
                <div class="section-header text-primary fw-bold">Project Access</div>
                <div class="project-list">
                    <?php foreach ($projects as $p): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="projects[]" value="<?= $p['id'] ?>" id="p_<?= $p['id'] ?>">
                            <label class="form-check-label" for="p_<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></label>
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
                            <input class="form-check-input" type="checkbox" name="cerun_access[]" value="<?= $hq['id'] ?>" id="c_<?= $hq['id'] ?>">
                            <label class="form-check-label fw-bold" for="c_<?= $hq['id'] ?>"><?= $hq['region'] ?></label>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php foreach ($cerun_data as $cat => $items): if($cat == 'HQ') continue; ?>
                        <hr>
                        <div class="form-check">
                            <input class="form-check-input parent" type="checkbox" data-target="<?= strtolower($cat) ?>" id="cat_<?= $cat ?>">
                            <label class="form-check-label fw-bold text-secondary" for="cat_<?= $cat ?>"><?= $cat ?></label>
                        </div>
                        <div class="ms-4 sub-region" id="<?= strtolower($cat) ?>" style="display:none;">
                            <?php foreach ($items as $item): ?>
                                <div class="form-check">
                                    <input class="form-check-input child <?= strtolower($cat) ?>" type="checkbox" name="cerun_access[]" value="<?= $item['id'] ?>" id="c_<?= $item['id'] ?>">
                                    <label class="form-check-label" for="c_<?= $item['id'] ?>"><?= $item['region'] ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="verifier-section" id="verifierContainer">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="verifier" id="verifierToggle">
                <label class="form-check-label fw-bold text-dark">Enable Verifier Role</label>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted">Verifier Project Scope</label>
                    <div class="project-list" style="height: 180px;">
                        <?php foreach ($projects as $p): ?>
                            <div class="form-check">
                                <input class="form-check-input verifier-check" type="checkbox" name="verifier_projects[]" value="<?= $p['id'] ?>" id="vp_<?= $p['id'] ?>">
                                <label class="form-check-label" for="vp_<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted">Verifier Cerun Scope</label>
                    <div class="project-list" style="height: 180px;">
                        <?php if(isset($cerun_data['HQ'])): ?>
                            <?php foreach($cerun_data['HQ'] as $hq): ?>
                            <div class="form-check">
                                <input class="form-check-input verifier-check" type="checkbox" name="verifier_cerun[]" value="<?= $hq['id'] ?>" id="vc_<?= $hq['id'] ?>">
                                <label class="form-check-label fw-bold" for="vc_<?= $hq['id'] ?>"><?= $hq['region'] ?></label>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php foreach ($cerun_data as $cat => $items): if($cat == 'HQ') continue; ?>
                            <hr>
                            <div class="form-check">
                                <input class="form-check-input verifier-check v-parent" type="checkbox" data-v-target="v-<?= strtolower($cat) ?>" id="vcat_<?= $cat ?>">
                                <label class="form-check-label fw-bold" for="vcat_<?= $cat ?>"><?= $cat ?></label>
                            </div>
                            <div class="ms-4 v-sub-region" id="v-<?= strtolower($cat) ?>" style="display:none;">
                                <?php foreach ($items as $item): ?>
                                    <div class="form-check">
                                        <input class="form-check-input verifier-check v-child v-<?= strtolower($cat) ?>" type="checkbox" name="verifier_cerun[]" value="<?= $item['id'] ?>" id="vc_<?= $item['id'] ?>">
                                        <label class="form-check-label" for="vc_<?= $item['id'] ?>"><?= $item['region'] ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <button class="btn btn-primary w-100 mt-4 py-3 fw-bold shadow-sm">Create User Account</button>
    </form>

</div>

</main>
</div>

</body>
	<!-- JS SAME -->
<script>
    const roleSelect = document.getElementById('roleSelect');
    const accessContainer = document.getElementById('accessContainer');
    const roleHint = document.getElementById('roleHint');
    const verifierContainer = document.getElementById('verifierContainer');
    const toggle = document.getElementById('verifierToggle');
    const vChecks = document.querySelectorAll('.verifier-check');

    function handleRoleChange() {
        const role = roleSelect.value;
        
        if (role === 'Client Admin' || role === 'Management' || role === 'HRA') {
            accessContainer.classList.add('disabled-zone');
            roleHint.classList.remove('d-none');
        } else {
            accessContainer.classList.remove('disabled-zone');
            roleHint.classList.add('d-none');
        }

        // Verifier Logic
        if (role === 'Client Admin' || role === 'Standard User') {
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

    roleSelect.addEventListener('change', handleRoleChange);
    toggle.addEventListener('change', syncVerifier);

    handleRoleChange();
    syncVerifier();
	

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
	
</html>

<?php $db->close(); ?>
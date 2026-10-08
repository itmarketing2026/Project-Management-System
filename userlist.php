<?php
session_start();

// Secure check: if not logged in, redirect to login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}

$title = "User List";
require_once 'include/msqli_connect.php';
$current_page = basename($_SERVER['PHP_SELF']);

$sql = "
SELECT 
    u.id,
    u.full_name,
    u.account_type,
	 u.designation,
    proj.project_list AS projects,
    cer.cerun_list AS ceruns
FROM users u
LEFT JOIN (
    SELECT up.user_id, GROUP_CONCAT(DISTINCT p.project_name ORDER BY p.project_name SEPARATOR ', ') AS project_list
    FROM user_project up
    JOIN project p ON up.project_id = p.id
    GROUP BY up.user_id
) AS proj ON u.id = proj.user_id
LEFT JOIN (
    SELECT uc.user_id, GROUP_CONCAT(DISTINCT c.region ORDER BY c.region SEPARATOR ', ') AS cerun_list
    FROM user_cerun uc
    JOIN cerun c ON uc.cerun_id = c.id
    GROUP BY uc.user_id
) AS cer ON u.id = cer.user_id
ORDER BY
    CASE
        WHEN u.status = 'active' THEN 0
        ELSE 1
    END,
    u.full_name ASC
";

$result = $db->query($sql); 
$total_users = ($result ? $result->num_rows : 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <title>Project Management System</title>
    <style>
        :root {
            --blue: #0066cc;
            --blue-dark: #0052a3;
            --blue-light: #eef6ff;
            --bg: #f4f8fc;
            --white: #ffffff;
            --border: #e2e8f0;
            --text: #1e293b;
            --muted: #94a3b8;
            --sidebar-width: 260px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden; /* Prevent double scrollbar */
        }

        /* HEADER */
        .page-header {
            background: var(--blue);
            padding: 0 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            z-index: 10;
            flex-shrink: 0;
        }

        .page-header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .page-header h1 {
            color: #fff;
            font-size: 18px;
            font-weight: 700;
        }

        .user-count-pill {
            background: rgba(255,255,255,0.2);
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* MAIN LAYOUT (SIDEBAR + CONTENT) */
        .main-layout {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

 
  
      

        /* MAIN CONTENT AREA */
        .wrapper {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
            background: var(--bg);
        }

        /* TOOLBAR */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
        }

        .search-box input {
            width: 100%;
            padding: 10px 16px 10px 40px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }

        .search-box input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(0,102,204,0.1);
        }

        .filter-select {
            padding: 10px 15px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            background: var(--white);
            cursor: pointer;
            outline: none;
        }

        /* TABLE CARD */
        .table-card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .table-scroll { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        thead th {
            background: #f8fafc;
            color: #64748b;
            padding: 12px 18px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }

        tbody td {
            padding: 14px 18px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .user-name { font-weight: 600; color: var(--text); }

        /* BADGES & TAGS */
        .badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }

        .badge-standard  { background: #f1f5f9; color: #475569; }
        .badge-cadmin    { background: #ede9fe; color: #6d28d9; }
        .badge-manager   { background: #dbeafe; color: #1d4ed8; }
        .badge-qa        { background: #fef9c3; color: #854d0e; }
        .badge-hra       { background: #fce7f3; color: #9d174d; }
        .badge-mgmt      { background: #dcfce7; color: #166534; }

        .tag-all { background: #dcfce7; color: #166534; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 4px; }
        .tag-container { display: flex; flex-wrap: wrap; gap: 4px; }
        .tag { padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

        .action-btns { display: flex; gap: 8px; }
        .btn { width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: 0.2s; }
        .btn-edit { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
        .btn-delete { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .table-footer {
            padding: 12px 18px;
            background: #f8fafc;
            border-top: 1px solid var(--border);
            font-size: 12px;
            color: var(--muted);
            display: flex;
            justify-content: space-between;
        }
		
		.inactive-row{
    background:#f8fafc;
    opacity:0.65;
}

.inactive-row .user-name{
    text-decoration:line-through;
}
    </style>
</head>
<body>

    <header class="page-header">
        <div class="page-header-left">
            <a href="dashboard.php" style="color: white; text-decoration: none;"><i class="bi bi-grid-3x3-gap-fill"></i></a>
            <h1>Document Hub</h1>
            <span class="user-count-pill"><?= $total_users ?> Users</span>
        </div>
        <div class="page-header-right">
            <i class="bi bi-person-circle" style="color: white; font-size: 24px;"></i>
        </div>
    </header>

    <div class="main-layout">
        
 <?php 
require_once 'include/sidebar.php'; ?>

        <main class="wrapper">
            
            <div class="toolbar">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchInput" placeholder="Search users by name..." oninput="filterTable()">
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <select class="filter-select" id="roleFilter" onchange="filterTable()">
                        <option value="">All Roles</option>
                        <option value="Standard User">Standard User</option>
                        <option value="Client Admin">Client Admin</option>
                        <option value="Manager">Manager</option>
                        <option value="Admin (QA/QC)">Admin (QA/QC)</option>
                        <option value="HRA">HRA</option>
                        <option value="Management">Management</option>
                    </select>
                </div>
				
				
            </div>
			 

            <div class="table-card">
                <div class="table-scroll">
                    <table id="userTable">
                        <thead>
                            <tr>
                                <th style="width:50px">No</th>
                                <th>Name</th>
								 <th>Designation</th>
                                <th>Role</th>
                                <th>Projects</th>
                                <th>Cerun Regions</th>
								
                                <th style="width:100px">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if ($result && $total_users > 0): ?>
<?php $no = 1; while ($row = $result->fetch_assoc()): ?>

<?php
$type = $row['account_type'];

$badgeClass = 'badge-standard';

switch ($type) {
    case 'Client Admin':  $badgeClass = 'badge-cadmin'; break;
    case 'Manager':       $badgeClass = 'badge-manager'; break;
    case 'Admin (QA/QC)': $badgeClass = 'badge-qa'; break;
    case 'HRA':           $badgeClass = 'badge-hra'; break;
    case 'Management':    $badgeClass = 'badge-mgmt'; break;
}

$showAll = in_array($type, ['Client Admin', 'HRA', 'Management']);
?>

<tr
    class="<?= (($row['status'] ?? 'active') == 'inactive') ? 'inactive-row' : '' ?>"
    data-name="<?= strtolower(htmlspecialchars($row['full_name'])) ?>"
    data-role="<?= htmlspecialchars($type) ?>">

    <td style="color:#94a3b8;font-weight:600;">
        <?= $no++ ?>
    </td>

    <td>
        <span class="user-name">
            <?= htmlspecialchars($row['full_name']) ?>
        </span>
    </td>

    <td>
        <?= htmlspecialchars($row['designation']) ?>
    </td>

    <td>
        <span class="badge <?= $badgeClass ?>">
            <?= htmlspecialchars($type) ?>
        </span>
    </td>

    <td>
        <?php if ($showAll): ?>
            <span class="tag-all">ALL PROJECTS</span>
        <?php elseif (!empty($row['projects'])): ?>
            <div class="tag-container">
                <?php foreach (explode(', ', $row['projects']) as $project): ?>
                    <span class="tag"><?= htmlspecialchars($project) ?></span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            —
        <?php endif; ?>
    </td>

    <td>
        <?php if ($showAll): ?>
            <span class="tag-all">ALL REGIONS</span>
        <?php elseif (!empty($row['ceruns'])): ?>
            <div class="tag-container">
                <?php foreach (explode(', ', $row['ceruns']) as $cerun): ?>
                    <span class="tag"><?= htmlspecialchars($cerun) ?></span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            —
        <?php endif; ?>
    </td>

   

    <td>
        <div class="action-btns">
            <a href="update_user.php?id=<?= $row['id'] ?>"
               class="btn btn-edit">
                <i class="bi bi-pencil"></i>
            </a>

            <?php if (($row['status'] ?? 'active') == 'active'): ?>
                <a href="toggle_user_status.php?id=<?= $row['id'] ?>&status=inactive"
                   class="btn btn-delete"
                   onclick="return confirm('Deactivate this user?')">
                    <i class="bi bi-person-x"></i>
                </a>
            <?php else: ?>
                <a href="toggle_user_status.php?id=<?= $row['id'] ?>&status=active"
                   class="btn btn-edit"
                   onclick="return confirm('Activate this user?')">
                    <i class="bi bi-person-check"></i>
                </a>
            <?php endif; ?>
        </div>
    </td>
                                </tr>
                            <?php endwhile; ?>
                            
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 20px; color: #94a3b8;">
                                    No users found.
                                </td>
                            </tr>
                        <?php endif; ?> </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    <span>Manage user access and regional assignments</span>
                    <span id="footerCount">Total: <?= $total_users ?> users</span>
                </div>
            </div>
        </main>
    </div>

    <script>
        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const role = document.getElementById('roleFilter').value.toLowerCase();
            const rows = document.querySelectorAll('#tableBody tr[data-name]');
            let visible = 0;

            rows.forEach(row => {
                const name = row.getAttribute('data-name');
                const rowRole = row.getAttribute('data-role').toLowerCase();
                const matchSearch = name.includes(search);
                const matchRole = role === '' || rowRole === role;

                if (matchSearch && matchRole) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('footerCount').textContent = `Showing ${visible} of ${rows.length} users`;
        }
    </script>

</body>
</html>

<?php $db->close(); ?>

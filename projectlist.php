<?php
// projectlist.php

session_start();

$title = "Project List";

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    header("Location: login.php");
    exit;
}

require_once 'include/msqli_connect.php';

$sql = "SELECT id, project_name, prefix, category FROM project ORDER BY project_name ASC";
$result = $conn->query($sql);
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
            --blue-light: #e0f0ff;
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
            color: var(--text);
            min-height: 100vh;
        }

        /* HEADER */
        .page-header {
            background: var(--blue);
            padding: 16px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }

        .page-header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .back-btn {
            background: rgba(255,255,255,0.18);
            color: #fff;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.3);
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .back-btn:hover { background: rgba(255,255,255,0.3); }

        .page-header h1 {
            color: #fff;
            font-size: 20px;
            font-weight: 700;
        }

        .count-pill {
            background: rgba(255,255,255,0.2);
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        /* WRAPPER */
        .wrapper {
            max-width: 1000px;
            margin: 32px auto;
            padding: 0 24px;
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
            max-width: 380px;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 15px;
        }

        .search-box input {
            width: 100%;
            padding: 11px 16px 11px 40px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            background: var(--white);
            color: var(--text);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .search-box input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(0,102,204,0.1);
        }

        .search-box input::placeholder { color: var(--muted); }

        .filter-select {
            appearance: none; 
            -webkit-appearance: none; 
            -moz-appearance: none; 
            padding-right: 35px !important; 
            padding-left: 15px;
            height: 40px;
            border-radius: 12px;
            border: 1.5px solid var(--border);
            background-color: #ffffff;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23334155' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center; 
            background-size: 12px;
            
            cursor: pointer;
        }

        .filter-select:focus { 
        outline: none;
        border-color: var(--blue);
        box-shadow: 0 0 0 3px rgba(0,102,204,0.1);
    }

        .results-info {
            font-size: 13px;
            color: var(--muted);
            white-space: nowrap;
        }

        /* TABLE CARD */
        .table-card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .table-scroll { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 620px;
        }

        thead th {
            background: #f8fafd;
            color: #64748b;
            padding: 14px 18px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border-bottom: 1.5px solid var(--border);
            white-space: nowrap;
        }

        tbody td {
            padding: 15px 18px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            vertical-align: middle;
        }

        tbody tr:last-child td { border-bottom: none; }
        tbody tr { transition: background 0.15s; }
        tbody tr:hover { background: #f8fbff; }

        .no { color: var(--muted); font-size: 13px; font-weight: 600; }

        .project-name { font-weight: 600; color: var(--text); }

        .prefix-badge {
            background: #f1f5f9;
            color: #475569;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            font-family: monospace;
            letter-spacing: 0.5px;
        }

        /* ===== CATEGORY TAGS ===== */
        .tag {
            padding: 4px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .tag-infra  { background: #dbeafe; color: #1d4ed8; }
        .tag-rtb    { background: #e0f2fe; color: #0369a1; }
        .tag-cerun  { background: #f1f5f9; color: #475569; }
		.tag-property { background: #E6E6FA; color: #6b21a8; }
        .tag-other  { background: #f3f4f6; color: #6b7280; }

        .muted { color: var(--muted); font-style: italic; font-size: 13px; }

        /* ACTION BUTTONS */
        .action-btns { display: flex; gap: 6px; }

        .btn {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 15px;
            transition: 0.2s;
        }

        .btn-edit   { background: #fef3c7; color: #d97706; }
        .btn-edit:hover   { background: #fde68a; transform: scale(1.08); }
        .btn-delete { background: #fee2e2; color: #dc2626; }
        .btn-delete:hover { background: #fecaca; transform: scale(1.08); }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }

        .empty-state i { font-size: 48px; margin-bottom: 12px; display: block; }
        .empty-state p { font-size: 15px; }

        /* TABLE FOOTER */
        .table-footer {
            padding: 14px 18px;
            border-top: 1px solid var(--border);
            background: #f8fafd;
            font-size: 13px;
            color: var(--muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="page-header">
        <div class="page-header-left">
            <a href="dashboard.php" class="back-btn"><i class="bi bi-arrow-left"></i> Back</a>
            <h1><?= htmlspecialchars($title) ?></h1>
        </div>
        <span class="count-pill" id="countPill">
            <?= ($result ? $result->num_rows : 0) ?> Projects
        </span>
    </div>

    <div class="wrapper">

        <!-- TOOLBAR -->
        <div class="toolbar">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchInput" placeholder="Search by project name or prefix…" oninput="filterTable()">
            </div>
            <select class="filter-select" id="categoryFilter" onchange="filterTable()">
                <option value="">All Categories</option>
                <option value="INFRA">INFRA</option>
                <option value="RTB">RTB</option>
                <option value="CERUN">CERUN</option>
				<option value="PROPERTY">PROPERTY</option>
            </select>
            <span class="results-info" id="resultsInfo"></span>
        </div>

        <!-- TABLE -->
        <div class="table-card">
            <div class="table-scroll">
                <table id="projectTable">
                    <thead>
                        <tr>
                            <th style="width:5%">No</th>
							<th>Project Id</th>
                            <th>Project Name</th>
                            <th>Prefix</th>
                            <th>Category</th>
                            <th style="width:100px">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                                <?php
                                $cat = strtoupper(trim($row['category'] ?? ''));
                                switch ($cat) {
                                    case 'INFRA':  $tagClass = 'tag-infra';  break;
                                    case 'RTB':    $tagClass = 'tag-rtb';    break;
                                    case 'CERUN':  $tagClass = 'tag-cerun';  break;
									case 'PROPERTY': $tagClass = 'tag-property'; break;
                                    default:       $tagClass = 'tag-other';  break;
                                }
                                ?>
                                <tr data-name="<?= strtolower(htmlspecialchars($row['project_name'])) ?>"
                                    data-prefix="<?= strtolower(htmlspecialchars($row['prefix'])) ?>"
                                    data-category="<?= htmlspecialchars($cat) ?>">
                                    <td class="no"><?= $no++ ?></td>
									<td class="project-name"><?= htmlspecialchars($row['id']) ?></td>
                                    <td class="project-name"><?= htmlspecialchars($row['project_name']) ?></td>
                                    <td><span class="prefix-badge"><?= htmlspecialchars($row['prefix']) ?></span></td>
                                    <td>
                                        <?php if (!empty($cat)): ?>
                                            <span class="tag <?= $tagClass ?>"><?= htmlspecialchars($cat) ?></span>
                                        <?php else: ?>
                                            <span class="muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="update_project.php?id=<?= $row['id'] ?>" class="btn btn-edit" title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <a href="delete_project.php?id=<?= $row['id'] ?>" class="btn btn-delete" title="Delete"
                                                onclick="return confirm('Are you sure you want to delete this project?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="bi bi-folder"></i>
                                        <p>No projects found</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span>List of all registered projects</span>
                <span id="footerCount"></span>
            </div>
        </div>

    </div>

    <script>
        function filterTable() {
            const search   = document.getElementById('searchInput').value.toLowerCase();
            const category = document.getElementById('categoryFilter').value.toUpperCase();
            const rows     = document.querySelectorAll('#tableBody tr[data-name]');
            let visible    = 0;

            rows.forEach(row => {
                const name     = row.getAttribute('data-name');
                const prefix   = row.getAttribute('data-prefix');
                const cat      = row.getAttribute('data-category');
                const matchSearch   = name.includes(search) || prefix.includes(search);
                const matchCategory = category === '' || cat === category;

                if (matchSearch && matchCategory) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('resultsInfo').textContent =
                visible === rows.length ? '' : `Showing ${visible} of ${rows.length}`;
            document.getElementById('footerCount').textContent =
                `${visible} project${visible !== 1 ? 's' : ''} shown`;

            let emptyRow = document.getElementById('emptySearchRow');
            if (visible === 0 && rows.length > 0) {
                if (!emptyRow) {
                    emptyRow = document.createElement('tr');
                    emptyRow.id = 'emptySearchRow';
                    emptyRow.innerHTML = `<td colspan="5">
                        <div class="empty-state">
                            <i class="bi bi-search"></i>
                            <p>No projects match your search</p>
                        </div>
                    </td>`;
                    document.getElementById('tableBody').appendChild(emptyRow);
                }
            } else if (emptyRow) {
                emptyRow.remove();
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            const total = document.querySelectorAll('#tableBody tr[data-name]').length;
            document.getElementById('footerCount').textContent =
                `${total} project${total !== 1 ? 's' : ''} shown`;
        });
    </script>

</body>
</html>

<?php $conn->close(); ?>
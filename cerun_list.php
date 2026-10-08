<?php

session_start();

$title = "Cerun Region List";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'include/msqli_connect.php';

$regionFlags = [
    'johor'           => 'images/flags/johor.png',
    'kedah'           => 'images/flags/kedah.png',
    'kelantan'        => 'images/flags/kelantan.png',
    'kuala_lumpur'    => 'images/flags/kuala_lumpur.png',
    'melaka'          => 'images/flags/melaka.png',
    'negeri_sembilan' => 'images/flags/negeri_sembilan.png',
    'pahang'          => 'images/flags/pahang.png',
    'perak'           => 'images/flags/perak.png',
    'perlis'          => 'images/flags/perlis.png',
    'pulau_pinang'    => 'images/flags/pulau_pinang.png',
    'selangor'        => 'images/flags/selangor.png',
    'terengganu'      => 'images/flags/terengganu.png',
    'hq'              => 'images/flags/pintasutama.jpg'
];

$sql = "SELECT id, region FROM cerun ORDER BY (region LIKE '%HQ%' OR region LIKE '%Headquarters%') DESC, region ASC";
$result = $db->query($sql); 
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

        /* ===== WRAPPER ===== */
        .wrapper {
            max-width: 800px;
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
            min-width: 420px;
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
            padding: 13px 18px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            vertical-align: middle;
        }

        tbody tr:last-child td { border-bottom: none; }
        tbody tr { transition: background 0.15s; }
        tbody tr:hover { background: #f8fbff; }

        .no { color: var(--muted); font-size: 13px; font-weight: 600; }

        /* ===== REGION CELL ===== */
        .region-cell {
            display: flex;
            align-items: center;
            gap: 14px;
            font-weight: 600;
            color: var(--text);
        }

        .flag-img {
            width: 42px;
            height: 28px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            flex-shrink: 0;
        }

        /* ACTION BUTTON */
        .btn-edit {
            width: 34px;
            height: 34px;
            background: #fef3c7;
            color: #d97706;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            text-decoration: none;
            font-size: 15px;
            transition: 0.2s;
        }

        .btn-edit:hover { background: #fde68a; transform: scale(1.08); }

        /* EMPTY STATE */
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
            <?= ($result ? $result->num_rows : 0) ?> Regions
        </span>
    </div>

    <div class="wrapper">

        <!-- TOOLBAR -->
        <div class="toolbar">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchInput" placeholder="Search by region name…" oninput="filterTable()">
            </div>
            <span class="results-info" id="resultsInfo"></span>
        </div>

        <!-- TABLE -->
        <div class="table-card">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 60px;">No</th>
                            <th>Region</th>
                            <th style="width: 90px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php $no = 1; while ($row = $result->fetch_assoc()):
                                $regionName = strtolower($row['region']);
                                $key = str_replace(' ', '_', $regionName);
                                if (strpos($key, 'hq') !== false || strpos($key, 'headquarters') !== false) {
                                    $key = 'hq';
                                }
                                $imagePath = isset($regionFlags[$key]) ? $regionFlags[$key] : 'images/flags/default.png';
                            ?>
                            <tr data-name="<?= strtolower(htmlspecialchars($row['region'])) ?>">
                                <td class="no"><?= $no++ ?></td>
                                <td>
                                    <div class="region-cell">
                                        <img src="<?= htmlspecialchars($imagePath) ?>" alt="" class="flag-img">
                                        <?= htmlspecialchars($row['region']) ?>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <a href="update_cerun.php?id=<?= $row['id'] ?>" class="btn-edit" title="Edit Region">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3">
                                    <div class="empty-state">
                                        <i class="bi bi-geo-alt"></i>
                                        <p>No regions found</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span>Management of monitoring regions</span>
                <span id="footerCount"></span>
            </div>
        </div>

    </div>

    <script>
        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const rows   = document.querySelectorAll('#tableBody tr[data-name]');
            let visible  = 0;

            rows.forEach(row => {
                const name = row.getAttribute('data-name');
                if (name.includes(search)) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('resultsInfo').textContent =
                visible === rows.length ? '' : `Showing ${visible} of ${rows.length}`;
            document.getElementById('footerCount').textContent =
                `${visible} region${visible !== 1 ? 's' : ''} shown`;

            let emptyRow = document.getElementById('emptySearchRow');
            if (visible === 0 && rows.length > 0) {
                if (!emptyRow) {
                    emptyRow = document.createElement('tr');
                    emptyRow.id = 'emptySearchRow';
                    emptyRow.innerHTML = `<td colspan="3">
                        <div class="empty-state">
                            <i class="bi bi-search"></i>
                            <p>No regions match your search</p>
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
                `${total} region${total !== 1 ? 's' : ''} shown`;
        });
    </script>

</body>
</html>
<?php
session_start();
require_once 'include/msqli_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$title = 'Extension of Time';

/* =========================
   QUERY - PROJECT REGISTER
========================= */

$query_pr = "
SELECT 
    p.id,
    p.project_name,
    pr.company,
    pr.contract_no,
    pr.contract_sum,
    pr.site_start,
    pr.site_end,
    pr.pic_project_manager
FROM project p
LEFT JOIN project_register pr 
    ON pr.project_id = p.id
WHERE p.status = 'Ongoing'
ORDER BY p.project_name ASC
";

$result_pr = $db->query($query_pr);

/* =========================
   QUERY - EOT
========================= */

$query_eot = "
SELECT 
    project_id, 
    eot_date
FROM eot
ORDER BY project_id, eot_date ASC
";

$result_eot = $db->query($query_eot);

$eot_list = [];

while ($row = $result_eot->fetch_assoc()) {

    $eot_list[$row['project_id']][] = $row['eot_date'];

}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Extension of Time</title>

<!-- BOOTSTRAP -->
<link 
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" 
    rel="stylesheet"
>

<!-- BOOTSTRAP ICON -->
<link 
    rel="stylesheet" 
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>

<style>

/* =========================================================
   GENERAL
========================================================= */

body {
    background: #f4f6f9;
    font-family: 'Segoe UI', Arial, sans-serif;
}

.card {
    border-radius: 16px;
    border: none;
}

.dashboard-title {
    font-weight: 600;
    font-size: 20px;
}


/* =========================================================
   TABLE
========================================================= */

.table {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 0;
}

.table thead {
    background: #1f2d3d !important;
    color: white !important;
}

.table thead th {
    background: #1f2d3d !important;
    color: white !important;
    vertical-align: middle;
    border-bottom: none;
    font-size: 14px;
}

.table tbody tr:hover {
    background: #f1f3f5;
}

.table td {
    vertical-align: middle;
}


/* =========================================================
   HEADER SORT
========================================================= */

.header-sort {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    min-width: 100px;
}

.header-sort span {
    white-space: nowrap;
}


/* =========================================================
   SORT SELECT
========================================================= */

.sort-select {
    border: 1px solid #ced4da;
    background: white;
    color: #212529;
    border-radius: 5px;
    font-size: 11px;
    padding: 3px 5px;
    cursor: pointer;
    min-width: 55px;
    height: 27px;
}

.sort-select:hover {
    background: #f8f9fa;
}

.sort-select:focus {
    outline: none;
    border-color: #86b7fe;
    box-shadow: 0 0 0 2px rgba(13,110,253,.15);
}


/* =========================================================
   EOT BADGE
========================================================= */

.eot-badge {

    display: block;

    background: #e9ecef;

    color: #495057;

    border-radius: 6px;

    padding: 2px 8px;

    font-size: 12px;

    margin: 2px 0;

    white-space: nowrap;

}


/* =========================================================
   SEARCH
========================================================= */

#searchInput {
    max-width: 300px;
}


/* =========================================================
   PRINT
========================================================= */

@media print {

    @page {
        size: A4 landscape;
        margin: 6mm;
    }

    body {
        background: #fff !important;
    }

    .btn,
    #searchInput,
    .sort-select {
        display: none !important;
    }

    .container {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }

    .table {
        width: 100% !important;
        font-size: 10px;
        table-layout: fixed;
    }

    .table th,
    .table td {
        padding: 5px !important;
        white-space: normal !important;
        word-break: break-word;
        vertical-align: middle;
    }

    .table thead th {
        background: #1f2d3d !important;
        color: white !important;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     TOPBAR
========================================================= -->

<?php include 'include/topbar_report.php'; ?>


<div class="container py-5">

<div class="card shadow p-4">


<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="d-flex justify-content-between align-items-center mb-4">

    <div class="dashboard-title">

        <i class="bi bi-calendar-range"></i>

        LOA Project

    </div>


    <div class="d-flex gap-2">

        <button
            onclick="window.print()"
            class="btn btn-outline-dark btn-sm"
        >

            <i class="bi bi-printer"></i>

            Print

        </button>

    </div>

</div>


<!-- =========================================================
     SEARCH
========================================================= -->

<div class="mb-3">

    <input
        type="text"
        id="searchInput"
        class="form-control form-control-sm"
        placeholder="Search project / syarikat / kontrak..."
    >

</div>


<!-- =========================================================
     TABLE
========================================================= -->

<div class="table-responsive">

<table
    class="table table-hover align-middle"
    id="loaTable"
>


<!-- =========================================================
     TABLE HEADER
========================================================= -->

<thead>

<tr>

    <!-- # -->

    <th style="width: 45px;">
        #
    </th>


    <!-- PROJECT -->

    <th>

        <div class="header-sort">

            <span>
                Project
            </span>

            <select
                class="sort-select"
                onchange="sortTable(1, 'text', this.value)"
            >

                <option value="">
                    Sort
                </option>

                <option value="asc">
                    A → Z
                </option>

                <option value="desc">
                    Z → A
                </option>

            </select>

        </div>

    </th>


    <!-- COMPANY -->

    <th>

        <div class="header-sort">

            <span>
                Company
            </span>

            <select
                class="sort-select"
                onchange="sortTable(2, 'text', this.value)"
            >

                <option value="">
                    Sort
                </option>

                <option value="asc">
                    A → Z
                </option>

                <option value="desc">
                    Z → A
                </option>

            </select>

        </div>

    </th>


    <!-- CONTRACT NO -->

    <th>

        <div class="header-sort">

            <span>
                Contract No.
            </span>

            <select
                class="sort-select"
                onchange="sortTable(3, 'text', this.value)"
            >

                <option value="">
                    Sort
                </option>

                <option value="asc">
                    A → Z
                </option>

                <option value="desc">
                    Z → A
                </option>

            </select>

        </div>

    </th>


    <!-- CONTRACT SUM -->

    <th>

        <div class="header-sort">

            <span>
                Contract Sum
            </span>

            <select
                class="sort-select"
                onchange="sortTable(4, 'number', this.value)"
            >

                <option value="">
                    Sort
                </option>

                <option value="asc">
                    Low → High
                </option>

                <option value="desc">
                    High → Low
                </option>

            </select>

        </div>

    </th>


    <!-- START DATE -->

    <th>

        <div class="header-sort">

            <span>
                Start Date
            </span>

            <select
                class="sort-select"
                onchange="sortTable(5, 'date', this.value)"
            >

                <option value="">
                    Sort
                </option>

                <option value="asc">
                    Oldest → Newest
                </option>

                <option value="desc">
                    Newest → Oldest
                </option>

            </select>

        </div>

    </th>


    <!-- END DATE -->

    <th>

        <div class="header-sort">

            <span>
                End Date
            </span>

            <select
                class="sort-select"
                onchange="sortTable(6, 'date', this.value)"
            >

                <option value="">
                    Sort
                </option>

                <option value="asc">
                    Oldest → Newest
                </option>

                <option value="desc">
                    Newest → Oldest
                </option>

            </select>

        </div>

    </th>


    <!-- EOT -->

    <th>

        <div class="header-sort">

            <span>
                EOT
            </span>

            <select
                class="sort-select"
                onchange="sortTable(7, 'date', this.value)"
            >

                <option value="">
                    Sort
                </option>

                <option value="asc">
                    Earliest → Latest
                </option>

                <option value="desc">
                    Latest → Earliest
                </option>

            </select>

        </div>

    </th>


    <!-- PM -->

    <th>

        <div class="header-sort">

            <span>
                PM
            </span>

            <select
                class="sort-select"
                onchange="sortTable(8, 'text', this.value)"
            >

                <option value="">
                    Sort
                </option>

                <option value="asc">
                    A → Z
                </option>

                <option value="desc">
                    Z → A
                </option>

            </select>

        </div>

    </th>


    <!-- VIEW -->

    <th style="width: 55px;">
    </th>

</tr>

</thead>


<!-- =========================================================
     TABLE BODY
========================================================= -->

<tbody>


<?php

$no = 1;

while ($row = $result_pr->fetch_assoc()):

    $pid = $row['id'];

    $eots = $eot_list[$pid] ?? [];

?>


<tr>


    <!-- NUMBER -->

    <td class="row-number">

        <?= $no++ ?>

    </td>


    <!-- PROJECT -->

    <td class="fw-semibold">

        <?= htmlspecialchars(
            $row['project_name'] ?? '-'
        ) ?>

    </td>


    <!-- COMPANY -->

    <td>

        <?= htmlspecialchars(
            $row['company'] ?? '-'
        ) ?>

    </td>


    <!-- CONTRACT NO -->

    <td>

        <?= htmlspecialchars(
            $row['contract_no'] ?? '-'
        ) ?>

    </td>


    <!-- CONTRACT SUM -->

    <td style="white-space: nowrap;">

        <?php if (
            isset($row['contract_sum']) &&
            $row['contract_sum'] !== null &&
            $row['contract_sum'] !== ''
        ): ?>

            RM <?= number_format(
                (float)$row['contract_sum'],
                2
            ) ?>

        <?php else: ?>

            -

        <?php endif; ?>

    </td>


    <!-- START DATE -->

    <td
        data-sort-date="<?=
            !empty($row['site_start'])
                ? strtotime($row['site_start'])
                : 0
        ?>"
    >

        <?php if (!empty($row['site_start'])): ?>

            <span class="eot-badge">

                <?= date(
                    'd M Y',
                    strtotime($row['site_start'])
                ) ?>

            </span>

        <?php else: ?>

            -

        <?php endif; ?>

    </td>


    <!-- END DATE -->

    <td
        data-sort-date="<?=
            !empty($row['site_end'])
                ? strtotime($row['site_end'])
                : 0
        ?>"
    >

        <?php if (!empty($row['site_end'])): ?>

            <span class="eot-badge">

                <?= date(
                    'd M Y',
                    strtotime($row['site_end'])
                ) ?>

            </span>

        <?php else: ?>

            -

        <?php endif; ?>

    </td>


    <!-- EOT -->

    <td
        data-sort-date="<?=
            !empty($eots)
                ? strtotime($eots[0])
                : 0
        ?>"
    >

        <?php if (!empty($eots)): ?>

            <?php foreach (
                $eots as $i => $eot_date
            ): ?>

                <div class="eot-badge">

                    <?= date(
                        'd M Y',
                        strtotime($eot_date)
                    ) ?>

                    (<?= $i + 1 ?>)

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <span class="text-muted">
                -
            </span>

        <?php endif; ?>

    </td>


    <!-- PM -->

    <td>

        <?= htmlspecialchars(
            $row['pic_project_manager'] ?? '-'
        ) ?>

    </td>


    <!-- VIEW -->

    <td>

        <a
            href="project_register_list.php?project_id=<?= (int)$pid ?>"
            class="btn btn-dark btn-sm btn-view"
        >

            <i class="bi bi-eye"></i>

        </a>

    </td>


</tr>


<?php endwhile; ?>


</tbody>

</table>

</div>

</div>

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>


/* =========================================================
   SEARCH
========================================================= */

document
    .getElementById("searchInput")
    .addEventListener("input", function () {

        const value = this.value
            .toLowerCase()
            .trim();


        const rows = document.querySelectorAll(
            "#loaTable tbody tr"
        );


        rows.forEach(function (row) {

            const project =
                row.cells[1]?.innerText.toLowerCase() || "";

            const company =
                row.cells[2]?.innerText.toLowerCase() || "";

            const contract =
                row.cells[3]?.innerText.toLowerCase() || "";

            const text =
                project + " " +
                company + " " +
                contract;


            if (text.includes(value)) {

                row.style.display = "";

            } else {

                row.style.display = "none";

            }

        });


        updateRowNumbers();

    });



/* =========================================================
   SORT TABLE
========================================================= */

function sortTable(
    columnIndex,
    type,
    direction
) {

    /* Jangan buat apa-apa kalau
       user pilih "Sort" */

    if (!direction) {

        return;

    }


    const tbody =
        document.querySelector(
            "#loaTable tbody"
        );


    const rows =
        Array.from(
            tbody.querySelectorAll("tr")
        );


    rows.sort(function (a, b) {

        let valueA;
        let valueB;


        /* =========================================
           TEXT
        ========================================= */

        if (type === "text") {

            valueA =
                a.cells[columnIndex]
                    .innerText
                    .trim()
                    .toLowerCase();


            valueB =
                b.cells[columnIndex]
                    .innerText
                    .trim()
                    .toLowerCase();


            /* "-" dianggap kosong */

            if (valueA === "-") {

                valueA = "";

            }


            if (valueB === "-") {

                valueB = "";

            }


            if (direction === "asc") {

                return valueA.localeCompare(
                    valueB,
                    undefined,
                    {
                        numeric: true,
                        sensitivity: "base"
                    }
                );

            } else {

                return valueB.localeCompare(
                    valueA,
                    undefined,
                    {
                        numeric: true,
                        sensitivity: "base"
                    }
                );

            }

        }


        /* =========================================
           NUMBER
        ========================================= */

        if (type === "number") {

            valueA = parseFloat(
                a.cells[columnIndex]
                    .innerText
                    .replace(/[^\d.-]/g, "")
            );


            valueB = parseFloat(
                b.cells[columnIndex]
                    .innerText
                    .replace(/[^\d.-]/g, "")
            );


            if (isNaN(valueA)) {

                valueA = 0;

            }


            if (isNaN(valueB)) {

                valueB = 0;

            }


            if (direction === "asc") {

                return valueA - valueB;

            } else {

                return valueB - valueA;

            }

        }


        /* =========================================
           DATE
        ========================================= */

        if (type === "date") {

            valueA = parseInt(
                a.cells[columnIndex]
                    .getAttribute(
                        "data-sort-date"
                    )
            ) || 0;


            valueB = parseInt(
                b.cells[columnIndex]
                    .getAttribute(
                        "data-sort-date"
                    )
            ) || 0;


            if (direction === "asc") {

                return valueA - valueB;

            } else {

                return valueB - valueA;

            }

        }


        return 0;

    });


    /* =========================================
       PUT ROWS BACK
    ========================================= */

    rows.forEach(function (row) {

        tbody.appendChild(row);

    });


    /* =========================================
       UPDATE NUMBER
    ========================================= */

    updateRowNumbers();

}



/* =========================================================
   UPDATE #
========================================================= */

function updateRowNumbers() {

    let number = 1;


    const rows =
        document.querySelectorAll(
            "#loaTable tbody tr"
        );


    rows.forEach(function (row) {

        if (
            row.style.display !== "none"
        ) {

            const numberCell =
                row.querySelector(
                    ".row-number"
                );


            if (numberCell) {

                numberCell.innerText =
                    number++;

            }

        }

    });

}

</script>


</body>

</html>
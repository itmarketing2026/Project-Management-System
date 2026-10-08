<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}

require_once 'include/msqli_connect.php';

$role        = $_SESSION['account_type'] ?? '';
$designation = $_SESSION['designation'] ?? '';

$can_access = (
    strtolower($role) === 'client admin' ||
    strtolower(trim($designation)) === 'contract executive' ||
    strtolower($role) === 'management'
);

if (!$can_access) {
    header("Location: dashboard.php");
    exit();
}

$project_id = intval($_GET['project_id'] ?? 0);

if ($project_id <= 0) {
    die("Invalid project.");
}


/* =========================
   PROJECT
========================= */

$stmt = $db->prepare("
    SELECT
        id,
        project_name,
        prefix
    FROM project
    WHERE id = ?
    AND is_legal = 1
    LIMIT 1
");

$stmt->bind_param("i", $project_id);
$stmt->execute();

$project = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();

if (!$project) {
    die("Project not found or not a legal project.");
}


/* =========================
   SUCCESS MESSAGE
========================= */

$success = $_SESSION['success_message'] ?? '';

unset($_SESSION['success_message']);


/* =========================
   CATEGORY LIST
========================= */

$categories = [
    'Arbitration',
    'CIPAA',
    'High Court',
    'Court of Appeal'
];


/* =========================
   COURT OF APPEAL CASES
========================= */

$court_appeal_cases = [
    'B-02(C)(W)-771-052025',
    'B-02(C)(W)-772-052025'
];

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Project Management System</title>


<style>

/* =========================================================
   ROOT
========================================================= */

:root{
    --accent:#007bff;
    --accent-hover:#0056b3;
    --bg:#f3f4f6;
    --border:#e5e5e5;
    --text:#222;
    --muted:#777;
}


/* =========================================================
   GLOBAL
========================================================= */

*{
    box-sizing:border-box;
}

body{
    margin:0;
    font-family:"Segoe UI",Arial,sans-serif;
    background:var(--bg);
    display:flex;
    flex-direction:column;
    align-items:center;
    padding:24px;
    color:var(--text);
}


/* =========================================================
   TOP BAR
========================================================= */

.top-bar{
    width:100%;
    max-width:1200px;
    margin-bottom:14px;
}

.back-btn{
    display:inline-block;
    background:var(--accent);
    color:#fff;
    padding:10px 18px;
    border-radius:8px;
    text-decoration:none;
    font-weight:bold;
    transition:0.2s;
}

.back-btn:hover{
    background:var(--accent-hover);
}


/* =========================================================
   TITLE
========================================================= */

h1{
    color:var(--accent);
    font-size:34px;
    font-weight:800;
    margin:10px 0 6px;
    text-align:center;
}

.subtitle{
    color:var(--muted);
    font-size:15px;
    margin-bottom:28px;
    text-align:center;
}


/* =========================================================
   SUCCESS MESSAGE
========================================================= */

.alert-success{
    width:100%;
    max-width:1200px;
    background:#d4edda;
    color:#155724;
    padding:14px 18px;
    border-radius:10px;
    margin-bottom:20px;
    font-weight:600;
}


/* =========================================================
   MAIN TABLE CONTAINER
========================================================= */

.table-container{
    width:100%;
    max-width:1200px;
    background:#fff;
    border:2px solid var(--accent);
    border-radius:18px;
    padding:18px;
    margin-bottom:28px;
}


/* =========================================================
   CATEGORY HEADER
========================================================= */

.table-header-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:16px;
    gap:10px;
    flex-wrap:wrap;
}

.table-header-row h3{
    margin:0;
    font-size:20px;
    color:#222;
    font-weight:800;
}


/* =========================================================
   ADD BUTTON
========================================================= */

.add-btn{
    background:var(--accent);
    color:#fff;
    padding:10px 16px;
    border-radius:8px;
    text-decoration:none;
    font-size:14px;
    font-weight:700;
    transition:0.2s;
}

.add-btn:hover{
    background:var(--accent-hover);
}


/* =========================================================
   CASE SECTION
========================================================= */

.case-section{
    margin-bottom:28px;
}

.case-section:last-child{
    margin-bottom:0;
}


/* =========================================================
   CASE TITLE
========================================================= */

.case-title{
    background:#eef5ff;
    border-left:5px solid var(--accent);
    padding:13px 16px;
    margin-bottom:12px;
    border-radius:6px;
    font-size:16px;
    font-weight:800;
    color:#222;
}


/* =========================================================
   CASE COUNT
========================================================= */

.case-count{
    color:#777;
    font-size:14px;
    font-weight:400;
}


/* =========================================================
   SEARCH AREA
========================================================= */

.case-toolbar{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:12px;
    flex-wrap:wrap;
}


/* Search box */

.search-box{
    position:relative;
    flex:1;
    min-width:220px;
}

.search-box input{
    width:100%;
    height:42px;
    padding:10px 42px 10px 40px;
    border:1px solid #d7d7d7;
    border-radius:8px;
    font-size:14px;
    outline:none;
    background:#fff;
}

.search-box input:focus{
    border-color:var(--accent);
    box-shadow:0 0 0 2px rgba(0,123,255,0.08);
}


/* Search icon */

.search-icon{
    position:absolute;
    left:14px;
    top:50%;
    transform:translateY(-50%);
    color:#777;
    font-size:16px;
    pointer-events:none;
}


/* Clear button */

.clear-search{
    position:absolute;
    right:10px;
    top:50%;
    transform:translateY(-50%);
    width:25px;
    height:25px;
    border:none;
    background:transparent;
    color:#888;
    cursor:pointer;
    font-size:17px;
    display:none;
    border-radius:50%;
}

.clear-search:hover{
    background:#eee;
    color:#333;
}


/* Search information */

.search-info{
    color:#777;
    font-size:13px;
    white-space:nowrap;
}


/* =========================================================
   TABLE SCROLL
========================================================= */

.table-scroll{
    width:100%;
    max-height:470px;
    overflow-y:auto;
    overflow-x:auto;
    border:1px solid var(--border);
    border-radius:6px;
}


/* =========================================================
   TABLE
========================================================= */

table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    min-width:650px;
}

th,
td{
    padding:14px;
    border-right:1px solid var(--border);
    border-bottom:1px solid var(--border);
    text-align:left;
    vertical-align:top;
}


/* Remove right border last column */

th:last-child,
td:last-child{
    border-right:none;
}


/* =========================================================
   TABLE HEADER
========================================================= */

th{
    position:sticky;
    top:0;
    z-index:10;
    background:var(--accent);
    color:#fff;
    font-weight:700;
}


/* =========================================================
   TABLE ROW
========================================================= */

tbody tr{
    background:#fff;
}

tbody tr:hover td{
    background:#eef5ff;
}


/* =========================================================
   DOCUMENT TITLE
   NORMAL - NOT BOLD
========================================================= */

.document-title{
    font-weight:400;
    color:#222;
    margin-bottom:6px;
}


/* Make table content normal */

td{
    font-weight:400;
}


/* =========================================================
   NO DATA
========================================================= */

.no-data{
    text-align:center;
    color:#999;
    padding:28px;
    font-weight:400;
}


/* =========================================================
   EMPTY SEARCH
========================================================= */

.search-no-result{
    display:none;
    text-align:center;
    padding:30px;
    color:#999;
    font-size:14px;
}


/* =========================================================
   MOBILE
========================================================= */

@media(max-width:768px){

    body{
        padding:14px;
    }

    .table-container{
        padding:12px;
    }

    h1{
        font-size:28px;
    }

    th,
    td{
        padding:10px;
        font-size:13px;
    }

    .case-title{
        font-size:14px;
    }

    .case-toolbar{
        flex-direction:column;
        align-items:stretch;
    }

    .search-box{
        width:100%;
        min-width:0;
    }

    .search-info{
        text-align:right;
    }

    .table-scroll{
        max-height:450px;
    }

}

</style>

</head>


<body>


<!-- =======================================================
     BACK BUTTON
======================================================== -->

<div class="top-bar">

    <a href="legaldashboard.php"
       class="back-btn">

        ← Back

    </a>

</div>


<!-- =======================================================
     PAGE TITLE
======================================================== -->

<h1>
    LEGAL
</h1>


<div class="subtitle">

    <?= htmlspecialchars($project['project_name']) ?>

    <?php if (!empty($project['prefix'])): ?>

        &nbsp;·&nbsp;

        <?= htmlspecialchars($project['prefix']) ?>

    <?php endif; ?>

</div>


<!-- =======================================================
     SUCCESS MESSAGE
======================================================== -->

<?php if ($success): ?>

<div class="alert-success">

    <?= htmlspecialchars($success) ?>

</div>

<?php endif; ?>


<!-- =======================================================
     CATEGORY LOOP
======================================================== -->

<?php foreach ($categories as $category): ?>


<?php

/* =========================================================
   COURT OF APPEAL
========================================================= */

if ($category === 'Court of Appeal'):

?>

<div class="table-container">


    <!-- =====================================================
         COURT OF APPEAL HEADER
    ====================================================== -->

    <div class="table-header-row">

        <h3>

            ⚖️ Court of Appeal

        </h3>


        <a
            href="add_legal_document.php?project_id=<?= $project_id ?>"
            class="add-btn"
        >

            + Add New

        </a>

    </div>


    <!-- =====================================================
         COURT OF APPEAL CASES
    ====================================================== -->

    <?php foreach (
        $court_appeal_cases
        as $case_index => $case_reference
    ): ?>


        <?php

        /* =================================================
           GET DOCUMENTS
        ================================================= */

        $case_stmt = $db->prepare("
            SELECT
                id,
                title,
                doc_date,
                file_path,
                created_by,
                created_by_name,
                created_at
            FROM legal_documents
            WHERE project_id = ?
            AND category = 'Court of Appeal'
            AND case_reference = ?
            ORDER BY doc_date DESC, id DESC
        ");

        $case_stmt->bind_param(
            "is",
            $project_id,
            $case_reference
        );

        $case_stmt->execute();

        $case_result =
            $case_stmt->get_result();


        /* =================================================
           STORE RECORDS
        ================================================= */

        $case_documents = [];

        while (
            $row =
            $case_result->fetch_assoc()
        ) {

            $case_documents[] = $row;

        }


        $case_count =
            count($case_documents);


        /* =================================================
           UNIQUE ID
        ================================================= */

        $case_id =
            'case_' . $case_index;

        ?>


        <!-- =================================================
             CASE SECTION
        ================================================= -->

        <div
            class="case-section"
            data-case-section="<?= $case_id ?>"
        >


            <!-- =================================================
                 CASE TITLE
            ================================================= -->

            <div class="case-title">

                <?= htmlspecialchars($case_reference) ?>

                <span class="case-count">

                    (<?= $case_count ?>)

                </span>

            </div>


            <!-- =================================================
                 SEARCH TOOLBAR
            ================================================= -->

            <div class="case-toolbar">


                <div class="search-box">

                    <span class="search-icon">
                        🔍
                    </span>


                    <input
                        type="text"
                        class="case-search"
                        data-case="<?= $case_id ?>"
                        placeholder="Search document..."
                        autocomplete="off"
                    >


                    <button
                        type="button"
                        class="clear-search"
                        data-case="<?= $case_id ?>"
                        title="Clear search"
                    >

                        ×

                    </button>

                </div>


                <div
                    class="search-info"
                    id="<?= $case_id ?>_info"
                >

                    Showing
                    <?= $case_count ?>
                    document<?= $case_count == 1 ? '' : 's' ?>

                </div>


            </div>


            <!-- =================================================
                 TABLE SCROLL
            ================================================= -->

            <div class="table-scroll">


                <table>

                    <thead>

                        <tr>

                            <th style="width:70px;">

                                No

                            </th>


                            <th>

                                Document

                            </th>


                            <th style="width:140px;">

                                Date

                            </th>

                        </tr>

                    </thead>


                    <tbody
                        id="<?= $case_id ?>_body"
                    >


                    <?php if ($case_count > 0): ?>


                        <?php foreach (
                            $case_documents
                            as $index => $row
                        ): ?>


                        <tr
                            class="case-document-row"
                            data-case="<?= $case_id ?>"
                            data-title="<?= htmlspecialchars(
                                strtolower(
                                    $row['title']
                                ),
                                ENT_QUOTES
                            ) ?>"
                            onclick="window.location='view_legal_document.php?id=<?= $row['id'] ?>&project_id=<?= $project_id ?>'"
                            style="cursor:pointer;"
                        >


                            <!-- =================================
                                 NUMBER
                            ================================== -->

                            <td>

                                <?= $index + 1 ?>

                            </td>


                            <!-- =================================
                                 DOCUMENT
                            ================================== -->

                            <td>

                                <div class="document-title">

                                    <?= htmlspecialchars(
                                        $row['title']
                                    ) ?>

                                </div>

                            </td>


                            <!-- =================================
                                 DATE
                            ================================== -->

                            <td>

                                <?= date(
                                    'd/m/Y',
                                    strtotime(
                                        $row['doc_date']
                                    )
                                ) ?>

                            </td>


                        </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="3"
                                class="no-data"
                            >

                                No documents for this case.

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>

                </table>


                <!-- =================================================
                     NO SEARCH RESULT
                ================================================== -->

                <?php if ($case_count > 0): ?>

                <div
                    class="search-no-result"
                    id="<?= $case_id ?>_no_result"
                >

                    No matching documents found.

                </div>

                <?php endif; ?>


            </div>


        </div>


        <?php

        $case_stmt->close();

        ?>

    <?php endforeach; ?>


</div>


<?php

/* =========================================================
   OTHER CATEGORIES
========================================================= */

else:

?>


<?php

$docs_stmt = $db->prepare("
    SELECT
        id,
        title,
        doc_date,
        file_path,
        created_by,
        created_by_name,
        created_at
    FROM legal_documents
    WHERE project_id = ?
    AND category = ?
    ORDER BY doc_date DESC, id DESC
");

$docs_stmt->bind_param(
    "is",
    $project_id,
    $category
);

$docs_stmt->execute();

$docs_result =
    $docs_stmt->get_result();

?>


<!-- =======================================================
     OTHER CATEGORY
======================================================== -->

<div class="table-container">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="table-header-row">

        <h3>

            ⚖️
            <?= htmlspecialchars($category) ?>

            (<?= $docs_result->num_rows ?>)

        </h3>


        <?php if ($category === 'Arbitration'): ?>


        <a
            href="add_legal_document.php?project_id=<?= $project_id ?>"
            class="add-btn"
        >

            + Add New

        </a>


        <?php endif; ?>


    </div>


    <!-- =====================================================
         TABLE SCROLL
    ====================================================== -->

    <div class="table-scroll">


        <table>

            <thead>

                <tr>

                    <th style="width:70px;">

                        No

                    </th>


                    <th>

                        Document

                    </th>


                    <th style="width:140px;">

                        Date

                    </th>

                </tr>

            </thead>


            <tbody>


            <?php if ($docs_result->num_rows > 0): ?>


                <?php $i = 1; ?>


                <?php while (
                    $row =
                    $docs_result->fetch_assoc()
                ): ?>


                <tr
                    onclick="window.location='view_legal_document.php?id=<?= $row['id'] ?>&project_id=<?= $project_id ?>'"
                    style="cursor:pointer;"
                >


                    <!-- =====================================
                         NUMBER
                    ====================================== -->

                    <td>

                        <?= $i++ ?>

                    </td>


                    <!-- =====================================
                         DOCUMENT
                    ====================================== -->

                    <td>

                        <div class="document-title">

                            <?= htmlspecialchars(
                                $row['title']
                            ) ?>

                        </div>

                    </td>


                    <!-- =====================================
                         DATE
                    ====================================== -->

                    <td>

                        <?= date(
                            'd/m/Y',
                            strtotime(
                                $row['doc_date']
                            )
                        ) ?>

                    </td>


                </tr>


                <?php endwhile; ?>


            <?php else: ?>


                <tr>

                    <td
                        colspan="3"
                        class="no-data"
                    >

                        No documents yet.

                    </td>

                </tr>


            <?php endif; ?>


            </tbody>

        </table>


    </div>


</div>


<?php

$docs_stmt->close();

?>


<?php endif; ?>


<?php endforeach; ?>


<!-- =======================================================
     SEARCH JAVASCRIPT
======================================================== -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function(){

        const searchInputs =
            document.querySelectorAll(
                '.case-search'
            );


        searchInputs.forEach(
            function(input){

                const caseId =
                    input.dataset.case;


                const rows =
                    document.querySelectorAll(
                        '.case-document-row[data-case="' +
                        caseId +
                        '"]'
                    );


                const clearButton =
                    document.querySelector(
                        '.clear-search[data-case="' +
                        caseId +
                        '"]'
                    );


                const info =
                    document.getElementById(
                        caseId +
                        '_info'
                    );


                const noResult =
                    document.getElementById(
                        caseId +
                        '_no_result'
                    );


                const totalDocuments =
                    rows.length;


                /* =============================================
                   FILTER
                ============================================== */

                function filterDocuments(){

                    const keyword =
                        input.value
                        .trim()
                        .toLowerCase();


                    let visibleCount = 0;


                    rows.forEach(
                        function(row){

                            const title =
                                row.dataset.title ||
                                '';


                            if(
                                keyword === '' ||
                                title.includes(keyword)
                            ){

                                row.style.display =
                                    '';

                                visibleCount++;

                            }
                            else{

                                row.style.display =
                                    'none';

                            }

                        }
                    );


                    /* =========================================
                       INFO
                    ========================================== */

                    if(keyword === ''){

                        info.innerText =
                            'Showing ' +
                            totalDocuments +
                            ' document' +
                            (
                                totalDocuments === 1
                                ? ''
                                : 's'
                            );

                    }
                    else{

                        info.innerText =
                            'Showing ' +
                            visibleCount +
                            ' of ' +
                            totalDocuments +
                            ' documents';

                    }


                    /* =========================================
                       NO RESULT
                    ========================================== */

                    if(noResult){

                        if(
                            keyword !== '' &&
                            visibleCount === 0
                        ){

                            noResult.style.display =
                                'block';

                        }
                        else{

                            noResult.style.display =
                                'none';

                        }

                    }


                    /* =========================================
                       CLEAR BUTTON
                    ========================================== */

                    if(keyword !== ''){

                        clearButton.style.display =
                            'block';

                    }
                    else{

                        clearButton.style.display =
                            'none';

                    }

                }


                /* =============================================
                   SEARCH INPUT
                ============================================== */

                input.addEventListener(
                    'input',
                    filterDocuments
                );


                /* =============================================
                   CLEAR SEARCH
                ============================================== */

                clearButton.addEventListener(
                    'click',
                    function(){

                        input.value = '';

                        filterDocuments();

                        input.focus();

                    }
                );

            }
        );

    }
);

</script>


</body>

</html>


<?php

$db->close();

?>
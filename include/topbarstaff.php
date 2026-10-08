<?php

require_once 'include/msqli_connect.php';

$current_project_id = $_GET['project_id'] ?? 0;


/* =========================================================
   PROJECT
   ========================================================= */

$project = [];

if ($current_project_id > 0) {

    $stmt = $db->prepare("
        SELECT *
        FROM project
        WHERE id = ?
    ");

    $stmt->bind_param(
        "i",
        $current_project_id
    );

    $stmt->execute();

    $project = $stmt
        ->get_result()
        ->fetch_assoc();

}


/* =========================================================
   CATEGORY
   ========================================================= */

$project_category =
    strtoupper(
        trim(
            $project['category'] ?? ''
        )
    );

$project_name =
    $project['project_name']
    ?? 'Project Workspace';

$role = strtolower(trim($_SESSION['account_type'] ?? ''));
$designation = strtolower(trim($_SESSION['designation'] ?? ''));

$can_see_memo = (
    (
        in_array($role, ['management', 'admin (qa/qc)']) ||
        $designation === 'contract manager'
    )
    && ($_SESSION['memo_access'] ?? 1) == 1
);


?>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    rel="stylesheet"
>


<style>

:root {
    --accent: #007bff;
    --accent-light: #00a2ff;
    --accent-hover: #0056b3;
}

.topbar,
.topbar * {
    font-family: "Segoe UI", Arial, sans-serif;
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 0;
}


/* =========================================================
   TOP BAR
   ========================================================= */

.topbar {
    width: 100%;
    background: linear-gradient(
        90deg,
        var(--accent),
        var(--accent-light)
    );
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    position: sticky;
    top: 0;
    z-index: 999;
}


.topbar-inner {
    max-width: 1200px;
    margin: 0 auto;

    display: flex;
    align-items: center;

    gap: 14px;

    padding: 10px 20px;
}


/* =========================================================
   PROJECT NAME LABEL
   ========================================================= */

.project-label {
    flex-shrink: 0;

    display: flex;
    flex-direction: column;

    line-height: 1.2;

    padding-right: 14px;

    border-right: 1px solid rgba(255,255,255,0.3);

    margin-right: 4px;
}


.project-label .eyebrow {
    font-size: 10px;

    font-weight: 700;

    letter-spacing: 0.6px;

    text-transform: uppercase;

    color: rgba(255,255,255,0.7);
}


.project-label .name {
    font-size: 13px;

    font-weight: 700;

    color: #fff;

    max-width: 160px;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}


/* =========================================================
   DESKTOP NAV
   ========================================================= */

.nav-links {
    display: flex;

    gap: 4px;

    align-items: center;

    overflow-x: auto;

    scrollbar-width: none;

    flex: 1;
}

.nav-links::-webkit-scrollbar {
    display: none;
}


.nav-links a {
    flex: 1 1 0;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 6px;

    text-decoration: none;

    font-weight: 600;

    color: rgba(255,255,255,0.85);

    font-size: 14px;

    white-space: nowrap;

    padding: 8px 14px;

    border-radius: 999px;

    transition: all 0.2s ease;

    min-width: fit-content;
}


.nav-links a i {
    font-size: 15px;

    opacity: 0.85;
}


.nav-links a:hover {
    background: rgba(255,255,255,0.15);

    color: #fff;
}


.nav-links a.active {
    background: #fff;

    color: var(--accent-hover);
}


.nav-links a.active i {
    opacity: 1;
}


/* =========================================================
   HAMBURGER BUTTON
   ========================================================= */

.hamburger-btn {
    display: none;

    flex-shrink: 0;

    width: 38px;

    height: 38px;

    align-items: center;

    justify-content: center;

    background: rgba(255,255,255,0.15);

    border: none;

    border-radius: 10px;

    color: #fff;

    font-size: 18px;

    cursor: pointer;

    margin-left: auto;
}


.hamburger-btn:hover {
    background: rgba(255,255,255,0.25);
}


/* =========================================================
   MOBILE DROPDOWN PANEL
   ========================================================= */

.mobile-nav {
    display: none;

    flex-direction: column;

    background: var(--accent-hover);

    padding: 0 14px;

    gap: 2px;

    max-height: 0;

    overflow: hidden;

    transition:
        max-height 0.25s ease,
        padding 0.25s ease;
}


.mobile-nav.open {
    max-height: 600px;

    padding: 8px 14px 14px;
}


.mobile-nav a {
    display: flex;

    align-items: center;

    gap: 10px;

    text-decoration: none;

    color: rgba(255,255,255,0.9);

    font-weight: 600;

    font-size: 15px;

    padding: 12px 14px;

    border-radius: 10px;

    transition: background 0.15s ease;
}


.mobile-nav a i {
    font-size: 16px;

    width: 20px;

    text-align: center;

    opacity: 0.85;
}


.mobile-nav a:active,
.mobile-nav a:hover {
    background: rgba(255,255,255,0.12);
}


.mobile-nav a.active {
    background: #fff;

    color: var(--accent-hover);
}


.mobile-nav a.active i {
    opacity: 1;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 768px) {

    .project-label {
        display: none;
    }

    .topbar-inner {
        padding: 8px 14px;

        gap: 10px;
    }

    .nav-links {
        display: none;
    }

    .hamburger-btn {
        display: inline-flex;
    }

    .mobile-nav {
        display: flex;
    }

}

</style>


<!-- =====================================================
     TOP BAR
     ===================================================== -->

<div class="topbar">

    <div class="topbar-inner">


        <!-- PROJECT LABEL -->

        <div class="project-label">

            <span class="eyebrow">
                <?= htmlspecialchars(
                    $project_category ?: 'Project'
                ) ?>
            </span>

            <span class="name">
                <?= htmlspecialchars(
                    $project_name
                ) ?>
            </span>

        </div>


        <!-- =================================================
             DESKTOP NAV
             ================================================= -->

        <div class="nav-links">


            <!-- WORKSPACE -->

            <a
                href="project_workspace.php?project_id=<?= $current_project_id ?>"
                class="<?= ($title == 'Project Workspace') ? 'active' : '' ?>"
            >
                <i class="bi bi-grid-1x2"></i>
                Workspace
            </a>


            <!-- REGISTER -->

            <a
                href="project_register_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                class="<?= ($title == 'Project Register') ? 'active' : '' ?>"
            >
                <i class="bi bi-journal-text"></i>
                Register
            </a>


            <!-- =================================================
                 LEGAL
                 ONLY LETTER IN + LETTER OUT
                 ================================================= -->

            <?php if ($project_category == 'LEGAL'): ?>

                <a
                    href="letterin_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                    class="<?= ($title == 'Letters In') ? 'active' : '' ?>"
                >
                    <i class="bi bi-envelope-arrow-down"></i>
                    Letter In
                </a>

                <a
                    href="letterout_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                    class="<?= ($title == 'Letters Out') ? 'active' : '' ?>"
                >
                    <i class="bi bi-envelope-arrow-up"></i>
                    Letter Out
                </a>


            <!-- =================================================
                 NON-LEGAL PROJECTS
                 ================================================= -->

            <?php else: ?>


                <!-- =============================================
                     INFRA / RTB / CERUN
                     ============================================= -->

                <?php if (
                    $project_category == 'INFRA' ||
                    $project_category == 'RTB' ||
                    $project_category == 'CERUN'
                ): ?>


                    <!-- LETTER IN -->

                    <a
                        href="letterin_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                        class="<?= ($title == 'Letters In') ? 'active' : '' ?>"
                    >
                        <i class="bi bi-envelope-arrow-down"></i>
                        Letter In
                    </a>


                    <!-- LETTER OUT -->

                    <a
                        href="letterout_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                        class="<?= ($title == 'Letters Out') ? 'active' : '' ?>"
                    >
                        <i class="bi bi-envelope-arrow-up"></i>
                        Letter Out
                    </a>


                    <!-- BQ -->

                    <a
                        href="bq_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                        class="<?= ($title == 'BQ') ? 'active' : '' ?>"
                    >
                        <i class="bi bi-list-columns"></i>
                        BQ
                    </a>


                    <!-- EOT -->

                    <a
                        href="eot.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                        class="<?= ($title == 'Extension of Time') ? 'active' : '' ?>"
                    >
                        <i class="bi bi-hourglass-split"></i>
                        EOT
                    </a>


                <?php endif; ?>


                <!-- =============================================
                     MEMO
                     ============================================= -->

<?php if ($can_see_memo): ?>
<a
href="memo.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
class="<?= ($title == 'Memo') ? 'active' : '' ?>"
>
<i class="bi bi-sticky"></i>
Memo
</a>
<?php endif; ?>


                <!-- =============================================
                     LOA
                     ============================================= -->

                <a
                    href="loa_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                    class="<?= ($title == 'LOA') ? 'active' : '' ?>"
                >
                    <i class="bi bi-file-earmark-check"></i>
                    LOA
                </a>


                <!-- =============================================
                     WEEKLY REPORT
                     ============================================= -->

                <a
                    href="wr.php?project_id=<?= $current_project_id ?>"
                    class="<?= ($title == 'Weekly Report') ? 'active' : '' ?>"
                >
                    <i class="bi bi-calendar-week"></i>
                    Weekly Report
                </a>


                <!-- =============================================
                     PROPERTY - DRAWING
                     ============================================= -->

                <?php if ($project_category == 'PROPERTY'): ?>

                    <a
                        href="drawing_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                        class="<?= ($title == 'Drawing') ? 'active' : '' ?>"
                    >
                        <i class="bi bi-vector-pen"></i>
                        Drawing
                    </a>

                <?php endif; ?>


                <!-- =============================================
                     PROPERTY / PROJECT ID 1 - INTERIM CLAIM
                     ============================================= -->

                <?php if (
                    $project_category == 'PROPERTY' ||
                    $current_project_id == 1
                ): ?>

                    <a
                        href="interim_claim_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                        class="<?= ($title == 'Interim Claim') ? 'active' : '' ?>"
                    >
                        <i class="bi bi-receipt"></i>
                        Interim Claim
                    </a>

                <?php endif; ?>


            <?php endif; ?>


        </div>


        <!-- =================================================
             HAMBURGER BUTTON
             ================================================= -->

        <button
            type="button"
            class="hamburger-btn"
            onclick="toggleMobileNav()"
            aria-label="Toggle menu"
        >
            <i
                class="bi bi-list"
                id="hamburgerIcon"
            ></i>
        </button>


    </div>


    <!-- =====================================================
         MOBILE DROPDOWN NAV
         ===================================================== -->

    <div
        class="mobile-nav"
        id="mobileNav"
    >


        <!-- WORKSPACE -->

        <a
            href="project_workspace.php?project_id=<?= $current_project_id ?>"
            class="<?= ($title == 'Project Workspace') ? 'active' : '' ?>"
        >
            <i class="bi bi-grid-1x2"></i>
            Workspace
        </a>


        <!-- REGISTER -->

        <a
            href="project_register_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
            class="<?= ($title == 'Project Register') ? 'active' : '' ?>"
        >
            <i class="bi bi-journal-text"></i>
            Register
        </a>


        <!-- =================================================
             LEGAL
             ONLY LETTER IN + LETTER OUT
             ================================================= -->

        <?php if ($project_category == 'LEGAL'): ?>


            <!-- LETTER IN -->

            <a
                href="letterin_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                class="<?= ($title == 'Letters In') ? 'active' : '' ?>"
            >
                <i class="bi bi-envelope-arrow-down"></i>
                Letter In
            </a>


            <!-- LETTER OUT -->

            <a
                href="letterout_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                class="<?= ($title == 'Letters Out') ? 'active' : '' ?>"
            >
                <i class="bi bi-envelope-arrow-up"></i>
                Letter Out
            </a>


        <!-- =================================================
             NON-LEGAL PROJECTS
             ================================================= -->

        <?php else: ?>


            <!-- =============================================
                 INFRA / RTB / CERUN
                 ============================================= -->

            <?php if (
                $project_category == 'INFRA' ||
                $project_category == 'RTB' ||
                $project_category == 'CERUN'
            ): ?>


                <!-- LETTER IN -->

                <a
                    href="letterin_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                    class="<?= ($title == 'Letters In') ? 'active' : '' ?>"
                >
                    <i class="bi bi-envelope-arrow-down"></i>
                    Letter In
                </a>


                <!-- LETTER OUT -->

                <a
                    href="letterout_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                    class="<?= ($title == 'Letters Out') ? 'active' : '' ?>"
                >
                    <i class="bi bi-envelope-arrow-up"></i>
                    Letter Out
                </a>


                <!-- BQ -->

                <a
                    href="bq_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                    class="<?= ($title == 'BQ') ? 'active' : '' ?>"
                >
                    <i class="bi bi-list-columns"></i>
                    BQ
                </a>


                <!-- EXTENSION OF TIME -->

                <a
                    href="eot.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                    class="<?= ($title == 'Extension of Time') ? 'active' : '' ?>"
                >
                    <i class="bi bi-hourglass-split"></i>
                    Extension of Time
                </a>


            <?php endif; ?>


            <!-- =============================================
                 MEMO
                 ============================================= -->
<?php if ($can_see_memo): ?>
<a
href="memo.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
class="<?= ($title == 'Memo') ? 'active' : '' ?>"
>
<i class="bi bi-sticky"></i>
Memo
</a>
<?php endif; ?>

            <!-- =============================================
                 LOA
                 ============================================= -->

            <a
                href="loa_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                class="<?= ($title == 'LOA') ? 'active' : '' ?>"
            >
                <i class="bi bi-file-earmark-check"></i>
                LOA
            </a>


            <!-- =============================================
                 WEEKLY REPORT
                 ============================================= -->

            <a
                href="wr.php?project_id=<?= $current_project_id ?>"
                class="<?= ($title == 'Weekly Report') ? 'active' : '' ?>"
            >
                <i class="bi bi-calendar-week"></i>
                Weekly Report
            </a>


            <!-- =============================================
                 PROPERTY - DRAWING
                 ============================================= -->

            <?php if ($project_category == 'PROPERTY'): ?>

                <a
                    href="drawing_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                    class="<?= ($title == 'Drawing') ? 'active' : '' ?>"
                >
                    <i class="bi bi-vector-pen"></i>
                    Drawing
                </a>

            <?php endif; ?>


            <!-- =============================================
                 PROPERTY / PROJECT ID 1 - INTERIM CLAIM
                 ============================================= -->

            <?php if (
                $project_category == 'PROPERTY' ||
                $current_project_id == 1
            ): ?>

                <a
                    href="interim_claim_list.php?project_id=<?= $_GET['project_id'] ?? '' ?>"
                    class="<?= ($title == 'Interim Claim') ? 'active' : '' ?>"
                >
                    <i class="bi bi-receipt"></i>
                    Interim Claim
                </a>

            <?php endif; ?>


        <?php endif; ?>


    </div>

</div>


<!-- =====================================================
     JAVASCRIPT
     ===================================================== -->

<script>

function toggleMobileNav() {

    const nav =
        document.getElementById(
            'mobileNav'
        );

    const icon =
        document.getElementById(
            'hamburgerIcon'
        );


    nav.classList.toggle('open');


    if (
        nav.classList.contains('open')
    ) {

        icon.classList.remove(
            'bi-list'
        );

        icon.classList.add(
            'bi-x-lg'
        );

    } else {

        icon.classList.remove(
            'bi-x-lg'
        );

        icon.classList.add(
            'bi-list'
        );

    }

}

</script>
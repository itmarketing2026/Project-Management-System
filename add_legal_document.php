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
    strtolower(trim($designation)) === 'contract executive'
);

if (!$can_access) {
    header("Location: dashboard.php");
    exit();
}

$project_id = intval($_GET['project_id'] ?? 0);

if ($project_id <= 0) {
    die("Invalid project.");
}

if (!user_can_access_project(
    $db,
    $_SESSION['user_id'],
    $project_id,
    $_SESSION['account_type'] ?? ''
)) {
    die("You do not have permission to view this project.");
}

/* =========================
   PROJECT
========================= */
$stmt = $db->prepare("
    SELECT project_name
    FROM project
    WHERE id = ?
    AND is_legal = 1
    LIMIT 1
");

$stmt->bind_param("i", $project_id);
$stmt->execute();

$project = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$project) {
    die("Project not found.");
}

/* =========================
   SAVE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $category      = trim($_POST['category'] ?? '');
    $case_reference = trim($_POST['case_reference'] ?? '');
    $title         = trim($_POST['title'] ?? '');
    $doc_date      = trim($_POST['doc_date'] ?? '');

    $doc_path = '';

    $created_by      = $_SESSION['user_id'];
    $created_by_name = $_SESSION['full_name'] ?? 'Unknown';

    /*
    |--------------------------------------------------------------------------
    | CASE REFERENCE
    |--------------------------------------------------------------------------
    | Hanya diperlukan untuk Court of Appeal
    */
    if ($category !== 'Court of Appeal') {
        $case_reference = '';
    }

    if (
        $category !== '' &&
        $title !== '' &&
        $doc_date !== ''
    ) {

        /* =========================
           FILE UPLOAD
        ========================== */

        if (!empty($_FILES['document']['name'][0])) {

            $upload_dir = 'uploads/project_' . $project_id . '/legal/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $uploaded = [];
            $files    = $_FILES['document'];

            for ($i = 0; $i < count($files['name']); $i++) {

                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $original = trim($files['name'][$i]);

                $ext = strtolower(
                    pathinfo($original, PATHINFO_EXTENSION)
                );

                $nameOnly = pathinfo(
                    $original,
                    PATHINFO_FILENAME
                );

                $safeName = substr(
                    preg_replace(
                        '/[^a-zA-Z0-9]/',
                        '_',
                        $nameOnly
                    ),
                    0,
                    50
                );

                $fname =
                    'legal_' .
                    $safeName .
                    '_' .
                    time() .
                    '_' .
                    $i .
                    '.' .
                    $ext;

                if (
                    move_uploaded_file(
                        $files['tmp_name'][$i],
                        $upload_dir . $fname
                    )
                ) {
                    $uploaded[] =
                        'project_' .
                        $project_id .
                        '/legal/' .
                        $fname;
                }
            }

            $doc_path = implode(',', $uploaded);
        }

        /* =========================
           INSERT
        ========================== */

        $stmt = $db->prepare("
            INSERT INTO legal_documents
            (
                project_id,
                category,
                case_reference,
                title,
                doc_date,
                file_path,
                created_by,
                created_by_name,
                created_at
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, NOW()
            )
        ");

        if (!$stmt) {
            die(
                "Database Error: " .
                htmlspecialchars($db->error)
            );
        }

        $stmt->bind_param(
            "isssssis",
            $project_id,
            $category,
            $case_reference,
            $title,
            $doc_date,
            $doc_path,
            $created_by,
            $created_by_name
        );

        if (!$stmt->execute()) {
            die(
                "Insert Error: " .
                htmlspecialchars($stmt->error)
            );
        }

        $stmt->close();

        $_SESSION['success_message'] =
            "✅ Document added successfully!";

        header(
            "Location: legal_project.php?project_id=" .
            $project_id
        );

        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Project Management System</title>

<style>

:root{
    --accent:#007bff;
    --frame-max:700px;
}

*{
    box-sizing:border-box;
}

body{
    margin:0;
    min-height:100vh;
    display:flex;
    flex-direction:column;
    align-items:center;
    background:#f3f4f6;
    font-family:Arial,sans-serif;
    padding:24px;
}

h1{
    margin:8px 0 24px;
    font-size:32px;
    font-weight:800;
    color:var(--accent);
    text-align:center;
}

.back-btn{
    align-self:flex-start;
    margin-bottom:16px;
    background:var(--accent);
    color:#fff;
    padding:10px 18px;
    border-radius:8px;
    font-weight:bold;
    text-decoration:none;
}

.back-btn:hover{
    background:#0056b3;
}

.form-container{
    width:100%;
    max-width:var(--frame-max);
    background:#fff;
    border:3px solid var(--accent);
    border-radius:20px;
    padding:30px;
}

form{
    display:flex;
    flex-direction:column;
    gap:20px;
}

label{
    font-weight:bold;
    margin-bottom:6px;
    display:block;
    color:#333;
}

input,
select{
    width:100%;
    padding:10px;
    border:2px solid var(--accent);
    border-radius:8px;
    font-size:16px;
    background:#fff;
}

input:focus,
select:focus{
    outline:none;
    border-color:#0056b3;
}

button{
    background:var(--accent);
    color:#fff;
    padding:12px;
    font-size:18px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    font-weight:bold;
}

button:hover{
    background:#0056b3;
}

small{
    color:#666;
    font-size:13px;
    margin-top:4px;
    display:block;
}

/* =========================
   COURT OF APPEAL FIELD
========================= */

#courtAppealContainer{
    display:none;
}

</style>

</head>

<body>

<a href="legal_project.php?project_id=<?= $project_id ?>"
   class="back-btn">
    ← Back
</a>

<h1>
    <?= htmlspecialchars($project['project_name']) ?>
</h1>

<div class="form-container">

<form method="POST"
      enctype="multipart/form-data">

    <input type="hidden"
           name="project_id"
           value="<?= $project_id ?>">

    <!-- =========================
         CATEGORY
    ========================== -->

    <div>

        <label for="category">
            Category
        </label>

        <select id="category"
                name="category"
                required>

            <option value="">
                -- Select Category --
            </option>

            <option value="Arbitration">
                Arbitration
            </option>

            <option value="CIPAA">
                CIPAA
            </option>

            <option value="High Court">
                High Court
            </option>

            <option value="Court of Appeal">
                Court of Appeal
            </option>

        </select>

    </div>


    <!-- =========================
         COURT OF APPEAL CASE
    ========================== -->

    <div id="courtAppealContainer">

        <label for="case_reference">
            Court of Appeal Case
        </label>

        <select id="case_reference"
                name="case_reference">

            <option value="">
                -- Select Case --
            </option>

            <option value="B-02(C)(W)-771-052025">
                B-02(C)(W)-771-052025
            </option>

            <option value="B-02(C)(W)-772-052025">
                B-02(C)(W)-772-052025
            </option>

        </select>

    </div>


    <!-- =========================
         TITLE
    ========================== -->

    <div>

        <label for="title">
            Title
        </label>

        <input type="text"
               id="title"
               name="title"
               required>

    </div>


    <!-- =========================
         DATE
    ========================== -->

    <div>

        <label for="doc_date">
            Date
        </label>

        <input type="date"
               id="doc_date"
               name="doc_date"
               required>

    </div>


    <!-- =========================
         FILE
    ========================== -->

    <div>

        <label for="document">
            Upload Document
        </label>

        <input type="file"
               id="document"
               name="document[]"
               multiple
               accept=".pdf,.doc,.docx,.jpg,.png"
               style="height:auto;padding:8px;">

        <small>
            Accepted:
            PDF, DOC, DOCX, JPG, PNG.
            Max size:
            <?= htmlspecialchars(
                ini_get('upload_max_filesize')
            ) ?>
        </small>

    </div>


    <!-- =========================
         SUBMIT
    ========================== -->

    <button type="submit">
        Submit
    </button>

</form>

</div>


<script>

/* =========================
   COURT OF APPEAL DROPDOWN
========================= */

const category =
    document.getElementById('category');

const courtAppealContainer =
    document.getElementById('courtAppealContainer');

const caseReference =
    document.getElementById('case_reference');


function toggleCourtAppeal() {

    if (category.value === 'Court of Appeal') {

        courtAppealContainer.style.display = 'block';

        caseReference.required = true;

    } else {

        courtAppealContainer.style.display = 'none';

        caseReference.required = false;

        caseReference.value = '';

    }
}


/* Run when category changes */
category.addEventListener(
    'change',
    toggleCourtAppeal
);


/* Run when page loads */
toggleCourtAppeal();

</script>

</body>

</html>
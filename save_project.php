<?php
// save_project.php — Final Comma-Proof Version (with labels + numeric sanitizer)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ---------- DATABASE CONNECTION ----------


require_once 'include/msqli_connect.php';

// ---------- UNIVERSAL NUMBER CLEANER ----------
function clean_number($val) {
  // Remove commas, spaces, and any non-numeric except dot
  if ($val === null || $val === '') return 0.0;
  $cleaned = str_replace(',', '', $val);
  $cleaned = preg_replace('/[^\d.\-]/', '', $cleaned);
  return floatval($cleaned);
}

// ---------- COLLECT MAIN PROJECT INFO ----------
$company_name        = $_POST['company_name'];
$project_name        = $_POST['project_name'];
$commencement_date   = $_POST['commencement_date'];
$completion_date     = $_POST['completion_date'];
$construction_period = clean_number($_POST['construction_period']);
$contract_amount     = clean_number($_POST['contract_amount']);
$eot_no              = $_POST['eot_no'];

// ---------- INSERT MAIN PROJECT ----------
$sql = "INSERT INTO projects 
(company_name, project_name, commencement_date, completion_date, construction_period, contract_amount, eot_no)
VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  die("Prepare failed (projects): " . $conn->error);
}

$stmt->bind_param(
  "ssssdds",
  $company_name,
  $project_name,
  $commencement_date,
  $completion_date,
  $construction_period,
  $contract_amount,
  $eot_no
);

if (!$stmt->execute()) {
  die("Error saving project: " . $stmt->error);
}
$project_id = $stmt->insert_id;
$stmt->close();

// ---------- COLLECT DETAIL ARRAYS ----------
$bills        = $_POST['bill'] ?? [];
$descriptions = $_POST['description'] ?? [];
$A_values     = $_POST['A'] ?? [];
$B_values     = $_POST['B'] ?? [];
$C_values     = $_POST['C'] ?? [];
$D_values     = $_POST['D'] ?? [];
$F_values     = $_POST['F'] ?? [];
$H_values     = $_POST['H'] ?? [];
$J_values     = $_POST['J'] ?? [];
$L_values     = $_POST['L'] ?? [];
$M_values     = $_POST['M'] ?? [];
$Q_values     = $_POST['Q'] ?? [];
$R_values     = $_POST['R'] ?? [];
$S_values     = $_POST['S'] ?? [];
$T_values     = $_POST['T'] ?? [];
$U_values     = $_POST['U'] ?? [];

// ---------- COMPANY LABELS ----------
$label_A = $_POST['label_A'][0] ?? null;
$label_F = $_POST['label_F'][0] ?? null;
$label_G = $_POST['label_G'][0] ?? null;
$label_H = $_POST['label_H'][0] ?? null;
$label_J = $_POST['label_J'][0] ?? null;
$label_K = $_POST['label_K'][0] ?? null;
$label_L = $_POST['label_L'][0] ?? null;
$label_M = $_POST['label_M'][0] ?? null;
$label_N = $_POST['label_N'][0] ?? null;
$label_Q = $_POST['label_Q'][0] ?? null;
$label_R = $_POST['label_R'][0] ?? null;
$label_S = $_POST['label_S'][0] ?? null;
$label_T = $_POST['label_T'][0] ?? null;
$label_U = $_POST['label_U'][0] ?? null;

// ---------- INSERT PROJECT DETAILS ----------
$sql_detail = "INSERT INTO project_details 
(project_id, bill_no, description, 
label_A, label_F, label_G, label_H, label_J, label_K, label_L, label_M, label_N, label_Q, label_R, label_S, label_T, label_U,
A, B, C, D, F, H, J, L, M, Q, R, S, T, U)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt_detail = $conn->prepare($sql_detail);
if (!$stmt_detail) {
  die("Prepare failed (project_details): " . $conn->error);
}

for ($i = 0; $i < count($descriptions); $i++) {
  if (empty($descriptions[$i])) continue;

  // clean numeric values with universal cleaner
  $A = clean_number($A_values[$i] ?? 0);
  $B = clean_number($B_values[$i] ?? 0);
  $C = clean_number($C_values[$i] ?? 0);
  $D = clean_number($D_values[$i] ?? 0);
  $F = clean_number($F_values[$i] ?? 0);
  $H = clean_number($H_values[$i] ?? 0);
  $J = clean_number($J_values[$i] ?? 0);
  $L = clean_number($L_values[$i] ?? 0);
  $M = clean_number($M_values[$i] ?? 0);
  $Q = clean_number($Q_values[$i] ?? 0);
  $R = clean_number($R_values[$i] ?? 0);
  $S = clean_number($S_values[$i] ?? 0);
  $T = clean_number($T_values[$i] ?? 0);
  $U = clean_number($U_values[$i] ?? 0);

  $stmt_detail->bind_param(
    "issssssssssssssssssdddddddddddd",
    $project_id,
    $bills[$i],
    $descriptions[$i],
    $label_A,
    $label_F,
    $label_G,
    $label_H,
    $label_J,
    $label_K,
    $label_L,
    $label_M,
    $label_N,
    $label_Q,
    $label_R,
    $label_S,
    $label_T,
    $label_U,
    $A,
    $B,
    $C,
    $D,
    $F,
    $H,
    $J,
    $L,
    $M,
    $Q,
    $R,
    $S,
    $T,
    $U
  );

  if (!$stmt_detail->execute()) {
    echo "Error inserting detail row " . ($i + 1) . ": " . $stmt_detail->error . "<br>";
  }
}

$stmt_detail->close();
$conn->close();

echo "<script>
  alert('✅ Project and details saved successfully!');
  window.location.href = 'projectwip.php';
</script>";
?>

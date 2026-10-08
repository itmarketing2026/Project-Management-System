<?php
// add_project.php
$title = "Add Project with Full Cost & Profit Details";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Project Management System</title>
<style>
body {
  font-family: Arial, sans-serif;
  margin: 40px;
  background-color: #f9f9f9;
}
h1 { text-align: center; color: #333; }
form {
  width: 98%;
  margin: 0 auto;
  background: #fff;
  padding: 25px;
  border-radius: 8px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
  overflow-x: auto;
}
label {
  display: block;
  margin: 15px 0 8px;
  font-weight: bold;
  font-size: 15px;
}
input, select {
  width: 100%;
  padding: 14px;
  font-size: 16px;
  border: 1px solid #bbb;
  border-radius: 6px;
  margin-bottom: 14px;
  box-sizing: border-box;
  font-family: monospace;
}
table {
  width: 100%;
  border-collapse: collapse;
  text-align: center;
  margin-top: 20px;
  min-width: 1800px;
}
th, td {
  border: 1px solid #000;
  padding: 8px;
  font-size: 14px;
  vertical-align: middle;
}
table input {
  width: 130px;
  padding: 10px 8px;
  font-size: 15px;
  border: 1px solid #ccc;
  border-radius: 4px;
  text-align: right;
  box-sizing: border-box;
  font-family: monospace;
}
td:nth-child(2) input {
  width: 300px;
  text-align: left;
  padding: 10px;
  font-family: Arial, sans-serif;
  font-size: 15px;
}
input[readonly], input[disabled] {
  background-color: #f1f1f1;
  color: #888;
  cursor: not-allowed;
}
button {
  margin-top: 25px;
  background-color: #007BFF;
  color: white;
  border: none;
  padding: 12px 24px;
  cursor: pointer;
  font-size: 17px;
  border-radius: 6px;
}
button:hover { background-color: #0056b3; }
.add-row {
  background-color: #28a745;
  margin-left: 10px;
}
.add-row:hover { background-color: #218838; }
.delete-row {
  background-color: #dc3545;
  color: white;
  padding: 5px 10px;
  border-radius: 4px;
  cursor: pointer;
}
.delete-row:hover { background-color: #c82333; }
.back-link {
  display: block;
  text-align: center;
  margin-top: 25px;
}
.company-row td {
  background-color: #eef6ff;
}
.company-row input {
  width: 130px;
  text-align: left;
  font-size: 13px;
  padding: 5px 6px;
}
</style>
</head>
<body>
<h1><?php echo $title; ?></h1>

<form method="post" action="save_project.php">
  <label>Company Name</label>
  <input type="text" name="company_name" required>

  <label>Project Name</label>
  <input type="text" name="project_name" required>

  <label>Commencement Date</label>
  <input type="date" name="commencement_date" required>

  <label>Completion Date</label>
  <input type="date" name="completion_date" required>

  <label>Construction Period (Days)</label>
  <input type="text" class="numeric-only format-number" name="construction_period" required>

  <label>Contract Amount (RM)</label>
  <input type="text" class="numeric-only format-number" name="contract_amount" required>

  <label>EOT No</label>
  <input type="text" name="eot_no">

  <table id="detailsTable">
    <thead>
      <tr>
        <th rowspan="2">Bill</th>
        <th rowspan="2">Description</th>
        <th rowspan="2">Original Contract Sum (A)</th>
        <th rowspan="2">MISB / Others (B)</th>
        <th colspan="3">Project Cost (MISB)</th>
        <th rowspan="2">Awarded Amount (F)</th>
        <th rowspan="2">Original Gross Profit (G = A - B - C)</th>
        <th rowspan="2">Workdone Certified (H)</th>
        <th rowspan="2">Awarded Amount (J)</th>
        <th rowspan="2">Revised Gross Profit (K = H + J)</th>
        <th rowspan="2">Workdone (L)</th>
        <th rowspan="2">Redo / Remaining (M)</th>
        <th rowspan="2">Total Workdone (N = H + L + M)</th>
        <th rowspan="2">Balance (P = A - N)</th>
        <th rowspan="2">Awarded (Q)</th>
        <th rowspan="2">Awarded (R)</th>
        <th rowspan="2">Awarded (S)</th>
        <th rowspan="2">Awarded (T)</th>
        <th rowspan="2">Awarded (U)</th>
        <th rowspan="2">Gross Profit (V = P - Q - R - S - T - U)</th>
        <th rowspan="2">Action</th>
      </tr>
      <tr>
        <th>Original (C)</th>
        <th>Adjustment (D)</th>
        <th>Revised (E = C + D)</th>
      </tr>
    </thead>
    <tbody>
      <!-- One-time Company Name Row -->
      <tr class="company-row">
        <td><input type="text" readonly></td>
        <td><input type="text" readonly></td>
        <td><input type="text" name="label_A[]" placeholder="Company"></td>
        <td><input type="text" readonly></td>
        <td><input type="text" readonly></td>
        <td><input type="text" readonly></td>
        <td><input type="text" readonly></td>
        <td><input type="text" name="label_F[]" placeholder="Company"></td>
        <td><input type="text" name="label_G[]" placeholder="Company"></td>
        <td><input type="text" name="label_H[]" placeholder="Company"></td>
        <td><input type="text" name="label_J[]" placeholder="Company"></td>
        <td><input type="text" name="label_K[]" placeholder="Company"></td>
        <td><input type="text" name="label_L[]" placeholder="Company"></td>
        <td><input type="text" name="label_M[]" placeholder="Company"></td>
        <td><input type="text" name="label_N[]" placeholder="Company"></td>
        <td><input type="text" readonly></td>
        <td><input type="text" name="label_Q[]" placeholder="Company"></td>
        <td><input type="text" name="label_R[]" placeholder="Company"></td>
        <td><input type="text" name="label_S[]" placeholder="Company"></td>
        <td><input type="text" name="label_T[]" placeholder="Company"></td>
        <td><input type="text" name="label_U[]" placeholder="Company"></td>
        <td><input type="text" readonly></td>
        <td><input type="text" readonly></td>
      </tr>

      <!-- Main Data Row -->
      <tr class="data-row">
        <td><input type="text" name="bill[]" readonly></td>
        <td><input type="text" name="description[]"></td>
        <td><input type="text" class="numeric-only format-number" name="A[]"></td>
        <td><input type="text" class="numeric-only format-number" name="B[]"></td>
        <td><input type="text" class="numeric-only format-number" name="C[]"></td>
        <td><input type="text" class="numeric-only format-number" name="D[]"></td>
        <td><input type="text" name="E[]" readonly></td>
        <td><input type="text" class="numeric-only format-number" name="F[]"></td>
        <td><input type="text" name="G[]" readonly></td>
        <td><input type="text" class="numeric-only format-number" name="H[]"></td>
        <td><input type="text" class="numeric-only format-number" name="J[]"></td>
        <td><input type="text" name="K[]" readonly></td>
        <td><input type="text" class="numeric-only format-number" name="L[]"></td>
        <td><input type="text" class="numeric-only format-number" name="M[]"></td>
        <td><input type="text" name="N[]" readonly></td>
        <td><input type="text" name="P[]" readonly></td>
        <td><input type="text" class="numeric-only format-number" name="Q[]"></td>
        <td><input type="text" class="numeric-only format-number" name="R[]"></td>
        <td><input type="text" class="numeric-only format-number" name="S[]"></td>
        <td><input type="text" class="numeric-only format-number" name="T[]"></td>
        <td><input type="text" class="numeric-only format-number" name="U[]"></td>
        <td><input type="text" name="V[]" readonly></td>
        <td><span class="delete-row" onclick="deleteRow(this)">🗑</span></td>
      </tr>
    </tbody>
  </table>

  <button type="button" class="add-row" onclick="addRow()">+ Add Row</button>
  <button type="submit">💾 Save Project</button>
</form>

<div class="back-link">
  <a href="projectwip.php">⬅ Back to Project List</a>
</div>

<script>
  // --- formatting helpers (kept as before) ---
  function formatNumber(num) {
    if (num === "" || isNaN(num)) return "";
    const parts = parseFloat(num).toFixed(2).split(".");
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    return parts.join(".");
  }
  function unformatNumber(num) {
    return String(num).replace(/,/g, "");
  }

  // Enforce numeric-only characters while typing (keeps decimals)
  document.addEventListener('input', function(e) {
    const el = e.target;
    if (el.classList && el.classList.contains('numeric-only')) {
      const original = el.value;
      const cleaned = original.replace(/[^0-9.]/g, '');
      const firstDot = cleaned.indexOf('.');
      if (firstDot !== -1) {
        const before = cleaned.slice(0, firstDot + 1);
        const after = cleaned.slice(firstDot + 1).replace(/\./g, '');
        el.value = before + after;
      } else {
        el.value = cleaned;
      }
      const row = el.closest('.data-row');
      if (row) recalc(row);
    }
  });

  document.addEventListener('blur', function(e) {
    const el = e.target;
    if (el.classList && el.classList.contains('format-number')) {
      const raw = unformatNumber(el.value);
      if (raw !== "" && !isNaN(raw)) el.value = formatNumber(raw);
    }
    if (el.classList && el.classList.contains('numeric-only') && !el.classList.contains('format-number')) {
      el.value = unformatNumber(el.value);
    }
  }, true);

  function recalc(row) {
    if (!row) return;
    const val = (selectorName) => {
      const el = row.querySelector(`[name="${selectorName}[]"], [data-field="${selectorName}"]`);
      if (!el) return 0;
      const raw = unformatNumber(el.value || '');
      return parseFloat(raw) || 0;
    };

    const A = val('A');
    const B = val('B');
    const C = val('C');
    const D = val('D');
    const H = val('H');
    const J = val('J');
    const L = val('L');
    const M = val('M');
    const Q = val('Q');
    const R = val('R');
    const S = val('S');
    const T = val('T');
    const U = val('U');

    const E = C + D;
    const G = A - B - C;
    const K = H + J;
    const N = H + L + M;
    const P = A - N;
    const V = P - Q - R - S - T - U;

    const write = (name, number) => {
      let el = row.querySelector(`[name="${name}[]"]`);
      if (!el) el = row.querySelector(`[data-field="${name}"]`);
      if (!el) return;
      el.value = formatNumber(number);
    };

    write('E', E);
    write('G', G);
    write('K', K);
    write('N', N);
    write('P', P);
    write('V', V);

    updateBillNumbers();
    updateTotals();
  }

  function addRow() {
    const table = document.querySelector("#detailsTable tbody");
    const baseRow = table.querySelector(".data-row");
    const newRow = baseRow.cloneNode(true);
    newRow.classList.add('data-row');
    newRow.querySelectorAll("input").forEach(i => i.value = "");
    table.appendChild(newRow);
    updateBillNumbers();
    recalc(newRow);
  }

  function deleteRow(button) {
    const row = button.closest(".data-row");
    const tbody = row.parentNode;
    const dataRows = tbody.querySelectorAll(".data-row");
    const confirmed = confirm("⚠️ Are you sure you want to delete this row?");
    if (!confirmed) return;
    if (dataRows.length > 1) {
      row.remove();
      updateBillNumbers();
    } else {
      alert("At least one data row must remain.");
    }
  }

  function updateBillNumbers() {
    const dataRows = document.querySelectorAll(".data-row");
    dataRows.forEach((row, i) => {
      const billEl = row.querySelector('[name="bill[]"]');
      if (billEl) billEl.value = i + 1;
    });
  }

  document.addEventListener('input', function(e) {
    const el = e.target;
    const row = el.closest('.data-row');
    if (row) recalc(row);
  });

  document.addEventListener('DOMContentLoaded', function() {
    updateBillNumbers();
    document.querySelectorAll('.data-row').forEach(r => recalc(r));
    if (typeof updateTotals === 'function') updateTotals();
  });

  function updateTotals() {
    if (window.updateTotals && window.updateTotals !== updateTotals) {
      try { window.updateTotals(); return; } catch(e){ }
    }
  }

  // 🟩 Excel-style Enter key navigation
  document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form");

    form.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        const currentInput = e.target;
        const row = currentInput.closest("tr");
        const isDataCell = row && (row.classList.contains("data-row") || row.classList.contains("company-row"));

        // prevent form submit
        e.preventDefault();

        if (isDataCell) {
          const editableCells = Array.from(
            row.querySelectorAll("input:not([readonly]):not([disabled]):not([type=hidden]), select, textarea")
          );
          const currentIndex = editableCells.indexOf(currentInput);

          if (currentIndex > -1 && currentIndex + 1 < editableCells.length) {
            // Move right to next field in same row
            editableCells[currentIndex + 1].focus();
          } else {
            // Move to first editable field in next row
            const nextRow = row.nextElementSibling;
            if (nextRow && nextRow.querySelector) {
              const nextEditable = nextRow.querySelector("input:not([readonly]):not([disabled]):not([type=hidden]), select, textarea");
              if (nextEditable) nextEditable.focus();
            } else {
              // loop back to first editable input in table
              const first = form.querySelector("tbody input:not([readonly]):not([disabled]):not([type=hidden])");
              if (first) first.focus();
            }
          }
        } else {
          // fallback for header fields
          const allInputs = Array.from(form.querySelectorAll('input:not([readonly]):not([disabled]):not([type=hidden]), select, textarea'));
          const currentIndex = allInputs.indexOf(currentInput);
          if (currentIndex > -1 && currentIndex + 1 < allInputs.length) {
            allInputs[currentIndex + 1].focus();
          } else {
            allInputs[0]?.focus();
          }
        }
      }
    });
  });
</script>
</body>
</html>

<?php
/**
 * Standalone admin tool: Audit Running Number for Letter Out Archive.
 * Table target: letter_out_archive
 *
 * Sama struktur/style macam Document Migration Tool (Cerun):
 *   - Login gate dengan password admin
 *   - Pilih Project melalui dropdown (bukan ?project_id= dalam URL)
 *   - Card-based audit results
 *
 * Dua jenis audit:
 *   1. Missing Running Number  — detect gap dalam sequence (PREFIX/YEAR/SEQ)
 *   2. Duplicate Running Number — detect running_number yang berulang
 *
 * Running number format: PREFIX/.../YEAR/SEQ  e.g. MBSB/JKR/AIR_TAWAR/2025/499
 * Prefix ikut project punya running number setting (project_rn_settings),
 * jadi sequence reset setiap tahun — grouping untuk gap check ialah
 * project_id + year.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
// ====================== CONFIG - EDIT THESE ======================
require_once 'include/msqli_connect.php';
$TABLE           = 'letter_out_archive';
$TABLE_PENDING   = 'letter_out_pending'; // holds EVERY letter regardless of status (pending/
                                          // submitted/rejected/acknowledged/verified_verifier/
                                          // verified_final/cancelled) — used to tell "not yet
                                          // archived" apart from "genuinely missing" when
                                          // checking for gaps (see findMissingRunningNumbers()).
$PROJECTS_TABLE  = 'letter_out_archive'; // no separate projects table wired in here —
                                          // swap this (and the query below) to your real
                                          // `projects` table if you want project NAMES
                                          // shown instead of just the numeric ID.
$PROJECTS_ID_COL = 'project_id';
$ADMIN_PASSWORD  = '12345';
// ====================== END CONFIG ======================
// ---------- Login gate ----------
if (isset($_POST['login_password'])) {
    if ($_POST['login_password'] === $ADMIN_PASSWORD) {
        $_SESSION['audit_tool_auth_letterout'] = true;
    } else {
        $loginError = 'Password salah.';
    }
}
if (isset($_GET['logout'])) unset($_SESSION['audit_tool_auth_letterout']);
$isAuthed = !empty($_SESSION['audit_tool_auth_letterout']);
if (!$isAuthed) { ?>
<!DOCTYPE html>
<html lang="ms">
<head><meta charset="UTF-8"><title>Login - Audit Running Number</title>
<style>
body{font-family:Arial;max-width:400px;margin:80px auto;}
input{width:100%;padding:8px;margin:8px 0;box-sizing:border-box;}
button{padding:8px 16px;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer;}
.err{color:red;}
</style>
</head>
<body>
    <h2>Audit Tool — Letter Out Running Number</h2>
    <?php if (!empty($loginError)) echo "<p class='err'>" . htmlspecialchars($loginError) . "</p>"; ?>
    <form method="post">
        <label>Password Admin:</label>
        <input type="password" name="login_password" required autofocus>
        <button type="submit">Login</button>
    </form>
</body>
</html>
<?php exit; }
// ---------- Helper: strip invisible characters that plain trim() won't catch ----------
// PHP's trim() only removes " \t\n\r\0\x0B" from the very START/END of a string.
// Data copied from Word/Excel/mobile often carries invisible characters ANYWHERE
// in the string — non-breaking space (U+00A0), zero-width space/joiner (U+200B-D),
// BOM (U+FEFF), or a stray embedded newline/tab from an accidental Enter/Tab press.
// Any of these breaks the PREFIX/YEAR/SEQ regex even though the value LOOKS
// identical on screen. We strip them all out before parsing (this format has no
// legitimate whitespace in it anyway, so removing all whitespace is safe here).
function cleanRunningNumber($rn) {
    $rn = (string)$rn;
    // remove BOM, zero-width space/joiner/non-joiner, NBSP, and any whitespace
    // (space, tab, newline, etc.) anywhere in the string — not just the ends.
    $rn = preg_replace('/[\x{FEFF}\x{200B}\x{200C}\x{200D}\x{00A0}\s]+/u', '', $rn);
    return trim((string)$rn);
}
// ---------- Helper: parse a running_number into prefix/year/seq/width ----------
// Expected format: PREFIX/.../YEAR/SEQ  e.g. MBSB/JKR/AIR_TAWAR/2025/499
function parseRunningNumber($rn) {
    $rn = cleanRunningNumber($rn);
    if ($rn === '') return null;
    if (preg_match('/^(.*)\/(\d{4})\/(\d+)$/', $rn, $m)) {
        return [
            'prefix' => $m[1],
            'year'   => (int)$m[2],
            'seq'    => (int)$m[3],
            'width'  => strlen($m[3]), // preserves zero-padding, e.g. "0499" -> 4
        ];
    }
    return null; // still doesn't match after cleaning — genuinely a different format
}
// ---------- Helper: reveal invisible characters for display (diagnostic only) ----------
function revealInvisibleChars($rn) {
    $rn = (string)$rn;
    $map = [
        "\xEF\xBB\xBF"     => '[BOM]',
        "\xE2\x80\x8B"     => '[ZWSP]',   // zero-width space
        "\xE2\x80\x8C"     => '[ZWNJ]',
        "\xE2\x80\x8D"     => '[ZWJ]',
        "\xC2\xA0"         => '[NBSP]',
        "\r\n"             => '[CRLF]',
        "\n"               => '[LF]',
        "\r"               => '[CR]',
        "\t"               => '[TAB]',
    ];
    $out = strtr($rn, $map);
    return $out;
}
// ---------- Helper: group one prefix's entries as ONE continuous sequence ----------
// User confirmed (2026-07-29): the running number never resets — it's one
// continuous sequence per prefix across every year (this matches the
// reference log too: e.g. "JKR/HODT" runs 2023/001..008 straight into
// 2024/009..022 with no reset; "MBSB/JKR/AIR_TAWAR/2025/499" being a high
// number is the sequence having accumulated over several years, not a
// per-year restart). So there is no reset detection at all — every
// (project_id, prefix) group is always treated as a single continuous run
// spanning however many years it actually has records in.
function asSingleRun($entries) {
    return [$entries];
}
// ---------- Helper: fetch running-number rows from a given table ----------
function fetchRunningNumberRows($db, $table, $PROJECTS_ID_COL, $filterProjectId, $source) {
    if ($filterProjectId !== null) {
        $stmt = $db->prepare("
            SELECT id, `$PROJECTS_ID_COL` AS project_id, running_number, title, agency, request_date
            FROM `$table`
            WHERE `$PROJECTS_ID_COL` = ? AND running_number IS NOT NULL AND running_number != ''
        ");
        $stmt->bind_param('i', $filterProjectId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $res = $db->query("
            SELECT id, `$PROJECTS_ID_COL` AS project_id, running_number, title, agency, request_date
            FROM `$table`
            WHERE running_number IS NOT NULL AND running_number != ''
        ");
        $rows = $res->fetch_all(MYSQLI_ASSOC);
    }
    foreach ($rows as &$r) $r['source'] = $source;
    unset($r);
    return $rows;
}
// ---------- Helper: build the gap report from a given set of rows ----------
// Groups by project_id + PREFIX first (ignoring year), then splits each
// prefix's records into continuous "runs" via splitIntoRuns() above. Only
// flags gaps BETWEEN the smallest and largest sequence seen within a run —
// does not assume a run must start at 1. Unparsed rows are only collected
// from the 'archive' source (that's the format we actually care about
// diagnosing) even when pending rows are included in $rows for gap-filling.
function buildMissingReport($rows) {
    $prefixGroups     = []; // "project_id|prefix" => list of parsed entries
    $unparsedArchive  = [];
    foreach ($rows as $r) {
        $parsed = parseRunningNumber($r['running_number']);
        if (!$parsed) {
            if ($r['source'] === 'archive') $unparsedArchive[] = $r;
            continue;
        }
        $key = $r['project_id'] . '|' . $parsed['prefix'];
        $prefixGroups[$key][] = $parsed + ['project_id' => $r['project_id'], 'source' => $r['source']];
    }

    $report = [];
    foreach ($prefixGroups as $entries) {
        foreach (asSingleRun($entries) as $items) {
            $seqs = array_unique(array_column($items, 'seq'));
            sort($seqs, SORT_NUMERIC);
            $min   = $seqs[0];
            $max   = end($seqs);
            $width = max(array_column($items, 'width'));

            $missing = [];
            for ($n = $min; $n <= $max; $n++) {
                if (!in_array($n, $seqs, true)) $missing[] = $n;
            }
            if ($missing) {
                $years = array_unique(array_column($items, 'year'));
                sort($years, SORT_NUMERIC);
                $report[] = [
                    'project_id'    => $items[0]['project_id'],
                    'prefix'        => $items[0]['prefix'],
                    'is_multi_year' => count($years) > 1, // true = continuous run spanning years (no reset detected)
                    'years'         => $years,
                    'year_label'    => count($years) > 1 ? ($years[0] . '–' . end($years)) : (string)$years[0],
                    'width'         => $width,
                    'min'           => $min,
                    'max'           => $max,
                    'count'         => count($items),
                    'missing'       => $missing,
                ];
            }
        }
    }
    usort($report, function ($a, $b) {
        return $a['project_id'] <=> $b['project_id']
            ?: strcmp($a['prefix'], $b['prefix'])
            ?: strcmp($b['year_label'], $a['year_label']);
    });
    return ['report' => $report, 'unparsed' => $unparsedArchive];
}
function totalMissingCount($report) {
    return array_sum(array_map(fn($g) => count($g['missing']), $report));
}
// ---------- Audit: Missing Running Number (gap check) ----------
// A number missing purely from `letter_out_archive` isn't necessarily an
// error — the letter may simply not be archived yet (still pending/submitted/
// acknowledged/verified/cancelled in `letter_out_pending`). Checking archive
// gaps in isolation can't tell "never created" apart from "created but not
// archived yet", since both look identical (no row for that number). Fix:
// pull running numbers from BOTH tables and only flag a gap as genuinely
// "missing" if the number exists in NEITHER — a number sitting in pending is
// reported separately as accounted-for, not as a problem.
function findMissingRunningNumbers($db, $TABLE_ARCHIVE, $TABLE_PENDING, $PROJECTS_ID_COL, $filterProjectId = null) {
    $archiveRows = fetchRunningNumberRows($db, $TABLE_ARCHIVE, $PROJECTS_ID_COL, $filterProjectId, 'archive');
    $pendingRows = fetchRunningNumberRows($db, $TABLE_PENDING, $PROJECTS_ID_COL, $filterProjectId, 'pending');

    $archiveOnly = buildMissingReport($archiveRows);
    $combined    = buildMissingReport(array_merge($archiveRows, $pendingRows));

    $resolvedByPending = max(0, totalMissingCount($archiveOnly['report']) - totalMissingCount($combined['report']));

    return [
        'report'              => $combined['report'],
        'unparsed'            => $combined['unparsed'],
        'total_scanned'       => count($archiveRows),
        'total_pending'       => count($pendingRows),
        'resolved_by_pending' => $resolvedByPending,
    ];
}
// ---------- Audit: Duplicate Running Number ----------
function findDuplicateRunningNumbers($db, $TABLE, $PROJECTS_ID_COL, $filterProjectId = null) {
    if ($filterProjectId !== null) {
        $stmt = $db->prepare("
            SELECT `$PROJECTS_ID_COL` AS project_id, running_number, COUNT(*) AS total, GROUP_CONCAT(id ORDER BY id) AS ids
            FROM `$TABLE`
            WHERE running_number IS NOT NULL AND running_number != '' AND `$PROJECTS_ID_COL` = ?
            GROUP BY `$PROJECTS_ID_COL`, running_number
            HAVING COUNT(*) > 1
            ORDER BY `$PROJECTS_ID_COL` ASC, running_number ASC
        ");
        $stmt->bind_param('i', $filterProjectId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $sql = "
        SELECT `$PROJECTS_ID_COL` AS project_id, running_number, COUNT(*) AS total, GROUP_CONCAT(id ORDER BY id) AS ids
        FROM `$TABLE`
        WHERE running_number IS NOT NULL AND running_number != ''
        GROUP BY `$PROJECTS_ID_COL`, running_number
        HAVING COUNT(*) > 1
        ORDER BY `$PROJECTS_ID_COL` ASC, running_number ASC
    ";
    $res = $db->query($sql);
    return $res->fetch_all(MYSQLI_ASSOC);
}
// ---------- State ----------
$action = $_POST['action'] ?? ($_GET['action'] ?? null);
$missingResult    = null;
$missingProjectId = null;
$dupResult        = null;
$dupProjectId     = null;
$errorMsg         = '';
$projectsError    = '';
if ($action === 'audit_missing') {
    $missingProjectId = (isset($_POST['missing_project_id']) && $_POST['missing_project_id'] !== '') ? (int)$_POST['missing_project_id'] : null;
    try {
        $missingResult = findMissingRunningNumbers($db, $TABLE, $TABLE_PENDING, $PROJECTS_ID_COL, $missingProjectId);
    } catch (Exception $e) { $errorMsg = $e->getMessage(); }
}
if ($action === 'audit_duplicates') {
    $dupProjectId = (isset($_POST['dup_project_id']) && $_POST['dup_project_id'] !== '') ? (int)$_POST['dup_project_id'] : null;
    try {
        $dupResult = findDuplicateRunningNumbers($db, $TABLE, $PROJECTS_ID_COL, $dupProjectId);
    } catch (Exception $e) { $errorMsg = $e->getMessage(); }
}
// Load project list (distinct project_id already present in the archive table)
$projectList = [];
try {
    $res = $db->query("SELECT DISTINCT `$PROJECTS_ID_COL` AS id FROM `$PROJECTS_TABLE` WHERE `$PROJECTS_ID_COL` IS NOT NULL ORDER BY `$PROJECTS_ID_COL` ASC");
    while ($p = $res->fetch_assoc()) $projectList[] = $p;
} catch (Exception $e) { $projectsError = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<title>Audit Tool — Letter Out Running Number</title>
<style>
* { box-sizing: border-box; }
body { font-family: Arial, sans-serif; max-width: 980px; margin: 30px auto; color: #1f2937; padding: 0 16px; }
h2   { margin-bottom: 4px; }
.sub { color: #6b7280; margin-top: 0; }
.card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
select { padding: 8px 12px; font-size: 14px; border-radius: 4px; border: 1px solid #d1d5db; }
button { padding: 8px 14px; font-size: 14px; border-radius: 4px; border: none; cursor: pointer; }
.btn-logout { background: transparent; color: #6b7280; text-decoration: underline; border: none; cursor: pointer; padding: 0; font-size: 14px; }
table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
th, td { border: 1px solid #e5e7eb; padding: 7px 10px; text-align: left; vertical-align: middle; }
th { background: #f3f4f6; font-weight: 600; }
tr:hover td { background: #f0f9ff; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.badge-missing { background: #fee2e2; color: #991b1b; }
.err { color: #b91c1c; font-weight: 500; }
.missing-chip {
    display: inline-block;
    background: #fff;
    border: 1px solid #fecaca;
    color: #991b1b;
    border-radius: 6px;
    padding: 3px 9px;
    font-size: 12px;
    font-weight: 600;
    margin: 2px 4px 2px 0;
}
.year-group {
    border: 1px solid #fecaca;
    background: #fff7f7;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 12px;
}
.year-group h4 { margin: 0 0 6px; font-size: 14px; color: #991b1b; }
.year-group .meta { font-size: 12px; color: #6b7280; margin-bottom: 8px; }
</style>
</head>
<body>
<h2>Audit Tool — Letter Out Running Number</h2>
<p class="sub">
    Audit running number dalam <code><?= htmlspecialchars($TABLE) ?></code> — cari nombor yang <strong>hilang (gap)</strong> atau <strong>duplicate</strong>, ikut project.
    &nbsp;|&nbsp; <a class="btn-logout" href="?logout=1">Logout</a>
</p>
<?php if (!empty($errorMsg)):      ?><p class="err">Error: <?= htmlspecialchars($errorMsg) ?></p><?php endif; ?>
<?php if (!empty($projectsError)): ?><p class="err">Gagal load senarai project: <?= htmlspecialchars($projectsError) ?></p><?php endif; ?>
<!-- ===== MISSING RUNNING NUMBER AUDIT ===== -->
<div class="card" style="padding:14px 20px;background:#fff7f7;border-color:#fecaca;">
    <p style="margin-top:0;font-weight:600;color:#991b1b">🔍 Audit Missing Running Number (Gap)</p>
    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="action" value="audit_missing">
        <select name="missing_project_id">
            <option value="">-- Semua Project --</option>
            <?php foreach ($projectList as $p): ?>
                <option value="<?= htmlspecialchars($p['id']) ?>" <?= ($missingProjectId == $p['id']) ? 'selected' : '' ?>>
                    Project ID: <?= htmlspecialchars($p['id']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" style="background:#991b1b;color:white;padding:8px 14px;border:none;border-radius:4px;cursor:pointer;font-size:13px">
            🔎 Jalankan Audit
        </button>
        <span style="color:#6b7280;font-size:12px">Cari nombor yang tertinggal dalam sequence (kumpulan ikut project + tahun).</span>
    </form>
</div>
<?php if ($missingResult !== null): ?>
<div class="card">
    <p style="margin-top:0;font-weight:600">📋 Keputusan Audit Missing Running Number
        <?php if ($missingProjectId): ?>
            <span style="font-weight:normal;color:#6b7280;font-size:13px">— Project ID: <?= htmlspecialchars($missingProjectId) ?></span>
        <?php else: ?>
            <span style="font-weight:normal;color:#6b7280;font-size:13px">— Semua Project</span>
        <?php endif; ?>
    </p>
    <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap">
        <div style="background:#f3f4f6;border-radius:6px;padding:8px 16px;font-size:13px">📄 <strong><?= $missingResult['total_scanned'] ?></strong> Rekod diarkibkan diimbas</div>
        <div style="background:#f3f4f6;border-radius:6px;padding:8px 16px;font-size:13px">📥 <strong><?= $missingResult['total_pending'] ?></strong> Rekod Pending/Progress diimbas</div>
        <div style="background:<?= $missingResult['report'] ? '#fee2e2' : '#dcfce7' ?>;border-radius:6px;padding:8px 16px;font-size:13px">
            <?= $missingResult['report'] ? '⚠️' : '✅' ?>
            <strong><?= count($missingResult['report']) ?></strong> Kumpulan dengan gap sebenar (tiada dalam archive MAHUPUN pending)
        </div>
        <?php if ($missingResult['resolved_by_pending'] > 0): ?>
        <div style="background:#e0f2fe;border-radius:6px;padding:8px 16px;font-size:13px">
            ℹ️ <strong><?= $missingResult['resolved_by_pending'] ?></strong> nombor yang nampak "hilang" sebenarnya wujud dalam Pending/Progress (belum archive) — tidak dikira sebagai missing
        </div>
        <?php endif; ?>
        <?php if (!empty($missingResult['unparsed'])): ?>
        <div style="background:#fffbeb;border-radius:6px;padding:8px 16px;font-size:13px">ℹ️ <strong><?= count($missingResult['unparsed']) ?></strong> Format tidak dikenali</div>
        <?php endif; ?>
    </div>
    <?php if (empty($missingResult['report'])): ?>
        <p style="color:#166534;font-weight:500">✅ Tiada missing running number ditemui.</p>
    <?php else: ?>
        <?php foreach ($missingResult['report'] as $g): ?>
        <div class="year-group">
            <h4>
                Project ID: <?= htmlspecialchars($g['project_id']) ?>
                &nbsp;—&nbsp; Tahun <?= htmlspecialchars($g['year_label']) ?>
                &nbsp;—&nbsp; <?= count($g['missing']) ?> hilang
            </h4>
            <div class="meta">
                Prefix: <?= htmlspecialchars($g['prefix']) ?>
                &nbsp;|&nbsp; Sequence berterusan (tiada reset ikut tahun)
                &nbsp;|&nbsp; Julat sequence: <?= $g['min'] ?>–<?= $g['max'] ?> (<?= $g['count'] ?> rekod dalam kumpulan ini)
            </div>
            <div>
                <?php foreach ($g['missing'] as $n): ?>
                    <?php if (!$g['is_multi_year']): ?>
                        <span class="missing-chip"><?= htmlspecialchars($g['prefix']) ?>/<?= htmlspecialchars($g['year_label']) ?>/<?= str_pad($n, $g['width'], '0', STR_PAD_LEFT) ?></span>
                    <?php else: ?>
                        <span class="missing-chip" title="Sequence berterusan merentas tahun <?= htmlspecialchars($g['year_label']) ?> — tahun tepat untuk nombor ini tidak pasti"><?= htmlspecialchars($g['prefix']) ?>/&hellip;/<?= str_pad($n, $g['width'], '0', STR_PAD_LEFT) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (!empty($missingResult['unparsed'])): ?>
        <details style="margin-top:14px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:10px 14px" open>
            <summary style="cursor:pointer;font-weight:600;color:#92400e">
                ℹ️ <?= count($missingResult['unparsed']) ?> running number tidak ikut format <code>PREFIX/YEAR/SEQ</code> (dilangkau dari gap check)
            </summary>
            <p style="font-size:12px;color:#92400e;margin:0 0 10px">
                💡 Nilai ini masih gagal walaupun selepas dibuang whitespace/invisible characters (NBSP, zero-width space, BOM, tab, newline) — jadi ia memang tidak ikut format <code>PREFIX/YEAR/SEQ</code>, bukan sekadar isu "hidden character". Column <strong>Raw</strong> di bawah tunjukkan invisible characters yang dijumpai (kalau ada) sebagai token seperti <code>[NBSP]</code>, <code>[ZWSP]</code>, <code>[LF]</code>.
            </p>
            <div style="overflow-x:auto;margin-top:10px">
            <table style="width:auto;min-width:700px">
                <thead>
                    <tr>
                        <th style="width:60px">ID</th>
                        <th style="width:100px">Project ID</th>
                        <th>Running Number (raw, invisible chars ditunjukkan)</th>
                        <th>Title</th>
                        <th>Agency</th>
                        <th>Letter Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($missingResult['unparsed'] as $u): ?>
                    <tr style="background:#fffdf5">
                        <td style="text-align:center"><?= htmlspecialchars($u['id'] ?? '') ?></td>
                        <td style="text-align:center"><?= htmlspecialchars($u['project_id']) ?></td>
                        <td style="font-weight:600"><code><?= htmlspecialchars(revealInvisibleChars($u['running_number'])) ?></code></td>
                        <td><?= htmlspecialchars($u['title'] ?? '') ?></td>
                        <td><?= htmlspecialchars($u['agency'] ?? '') ?></td>
                        <td><?= !empty($u['request_date']) ? date('d/m/Y', strtotime($u['request_date'])) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </details>
    <?php endif; ?>
</div>
<?php endif; ?>
<!-- ===== DUPLICATE RUNNING NUMBER AUDIT ===== -->
<div class="card" style="padding:14px 20px;background:#fff7ed;border-color:#fed7aa;">
    <p style="margin-top:0;font-weight:600;color:#9a3412">🧬 Audit Running Number Duplicate</p>
    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="action" value="audit_duplicates">
        <select name="dup_project_id">
            <option value="">-- Semua Project --</option>
            <?php foreach ($projectList as $p): ?>
                <option value="<?= htmlspecialchars($p['id']) ?>" <?= ($dupProjectId == $p['id']) ? 'selected' : '' ?>>
                    Project ID: <?= htmlspecialchars($p['id']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" style="background:#9a3412;color:white;padding:8px 14px;border:none;border-radius:4px;cursor:pointer;font-size:13px">
            🔎 Cari Duplicate
        </button>
        <span style="color:#6b7280;font-size:12px">Scan rekod dan detect running_number yang berulang dalam project yang sama.</span>
    </form>
</div>
<?php if ($dupResult !== null): ?>
<div class="card">
    <p style="margin-top:0;font-weight:600">📋 Keputusan Audit Duplicate
        <?php if ($dupProjectId): ?>
            <span style="font-weight:normal;color:#6b7280;font-size:13px">— Project ID: <?= htmlspecialchars($dupProjectId) ?></span>
        <?php else: ?>
            <span style="font-weight:normal;color:#6b7280;font-size:13px">— Semua Project</span>
        <?php endif; ?>
    </p>
    <?php if (empty($dupResult)): ?>
        <p style="color:#166534;font-weight:500">✅ Tiada running_number duplicate ditemui.</p>
    <?php else: ?>
        <div style="background:#fee2e2;border-radius:6px;padding:8px 16px;font-size:13px;display:inline-block;margin-bottom:12px">
            ⚠️ <strong><?= count($dupResult) ?></strong> running_number duplicate ditemui
        </div>
        <div style="overflow-x:auto">
        <table style="width:auto;min-width:600px">
            <thead>
                <tr>
                    <th style="width:100px">Project ID</th>
                    <th>Running Number</th>
                    <th style="width:100px">Bilangan</th>
                    <th>Record IDs</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($dupResult as $dup): ?>
                <tr style="background:#fef2f2">
                    <td style="text-align:center"><?= htmlspecialchars($dup['project_id']) ?></td>
                    <td style="font-weight:600"><?= htmlspecialchars($dup['running_number']) ?></td>
                    <td style="text-align:center"><?= htmlspecialchars($dup['total']) ?></td>
                    <td style="color:#6b7280"><?= htmlspecialchars($dup['ids']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p style="font-size:12px;color:#6b7280;margin-top:8px">
            💡 Ini bermakna ada &gt;1 rekod berlainan yang kongsi running_number sama dalam project yang sama — kemungkinan input duplicate.
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>
</body>
</html>
<?php $db->close(); ?>
<?php
/**
 * Main Campus — Excel result export (multi-sheet, professional layout).
 * Access: vp_main (vp_publish_results) | admin | examination_head (eh_results)
 * Only exports published assessments for Main Campus (non-ILC, non-Montessori) classes.
 *
 * GET ?class_id=N  → download 3-sheet XLSX for that class
 * GET (no class)   → show class-picker UI
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/xlsx_writer.php';
require_once __DIR__ . '/../includes/result_ranking.php';
require_once __DIR__ . '/../../config/db.php';

$user = requireAuth('vp_main', 'admin', 'examination_head');
if ($user['role'] === 'vp_main')          requirePermission('vp_publish_results');
if ($user['role'] === 'examination_head') requirePermission('eh_results');

$db      = getDB();
$classId = (int)($_GET['class_id'] ?? 0);

// ── Validate class (must be main campus) ─────────────────────────────────────
$classRow = null;
if ($classId) {
    try {
        $cSt = $db->prepare(
            "SELECT id, name, grade, section
             FROM classes
             WHERE id=? AND COALESCE(wing,'main')='main' AND COALESCE(is_ilc,0)=0 AND COALESCE(is_montessori,0)=0"
        );
        $cSt->execute([$classId]);
        $classRow = $cSt->fetch();
    } catch (Exception $e) {}
}

if (!$classRow) {
    // Show class-picker UI
    try {
        $allClasses = $db->query(
            "SELECT id, name FROM classes
             WHERE COALESCE(wing,'main')='main' AND COALESCE(is_ilc,0)=0 AND COALESCE(is_montessori,0)=0
             ORDER BY grade, section, name"
        )->fetchAll();
    } catch (Exception $e) { $allClasses = []; }

    pageHead('Download Class Results — Excel', $user['role']);
    $links = match($user['role']) {
        'admin'            => getAdminLinks(),
        'examination_head' => getExamHeadLinks(),
        default            => getVpLinks(),
    };
    ?>
    <div class="portal-wrap">
    <?php sidebar($user['role'], 'result-approvals', $links, $user); ?>
    <div class="main-area">
    <?php topbar('Download Class Results — Excel', $user); ?>
    <div class="page-content">
    <?= flashHtml() ?>
    <div class="sec-card" style="max-width:480px">
      <div class="sec-head"><i class="fas fa-file-excel me-2 text-success"></i>Select Class to Export</div>
      <div class="sec-body">
        <form method="GET">
          <label class="form-label fw-semibold" style="font-size:.85rem">Class</label>
          <select name="class_id" class="form-select form-select-sm mb-3">
            <option value="">— Select a class —</option>
            <?php foreach ($allClasses as $cl): ?>
            <option value="<?= $cl['id'] ?>"><?= h($cl['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-success btn-sm">
            <i class="fas fa-download me-1"></i>Download Excel (.xlsx)
          </button>
          <a href="result-approvals.php?tab=published" class="btn btn-outline-secondary btn-sm ms-2">Cancel</a>
        </form>
      </div>
    </div>
    </div></div></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </body></html>
    <?php
    exit;
}

// ── Fetch full result data (ranking helper — published only) ──────────────────
$students = getClassResultData($db, $classId, true);

if (empty($students)) {
    setFlash('warning', 'No published results found for ' . h($classRow['name']) . '. Publish assessments first.');
    $redir = ($user['role'] === 'examination_head')
        ? '/portal/exam-head/results.php'
        : '/portal/vp/result-approvals.php?tab=published';
    redirect($redir);
}

// ── Subject list in alphabetical order ───────────────────────────────────────
$subjectOrder = [];
foreach ($students as $stu) {
    foreach (array_keys($stu['subjects']) as $sid) {
        if (!isset($subjectOrder[$sid])) {
            $subjectOrder[$sid] = $stu['subjects'][$sid]['name'];
        }
    }
}
asort($subjectOrder);

// ── Settings ──────────────────────────────────────────────────────────────────
$schoolName  = getSetting('school_name', 'Bahria Model College');
$sessionYear = getSetting('session_year', SESSION_YEAR);
$currentTerm = getSetting('current_term', '');
$exportDate  = date('d F Y');
$className   = $classRow['name'];

// ── Derived stats ─────────────────────────────────────────────────────────────
$withMarks = array_values(array_filter($students, fn($s) => $s['grand_max'] > 0));
$passCount = count(array_filter($withMarks, fn($s) => $s['grand_pct'] >= 50));
$failCount = count(array_filter($withMarks, fn($s) => $s['grand_pct'] < 50));
$avgPct    = count($withMarks) > 0
    ? round(array_sum(array_column($withMarks, 'grand_pct')) / count($withMarks), 1)
    : 0.0;
$top3 = [];
foreach ($students as $stu) {
    if ($stu['position'] !== null && $stu['position'] <= 3) {
        $top3[$stu['position']][] = $stu['name'];
    }
}
ksort($top3);

// ══════════════════════════════════════════════════════════════════════════════
// SHEET 1 — Class Results
// ══════════════════════════════════════════════════════════════════════════════
$xls = new XlsxWriter('Class Results');

// Column widths: #(5), Name(26), GR(13), Roll(10), Pos(9),
//   per subject: Max(9), Obt(9), Grade(8),
//   then: Total Max(11), Total Obt(11), %(10), Grade(9), Status(9)
$subCount  = count($subjectOrder);
$widths    = [5, 26, 13, 10, 9];
foreach ($subjectOrder as $sid => $sn) {
    $widths[] = 9;  // max marks
    $widths[] = 9;  // obtained
    $widths[] = 8;  // grade
}
$widths = array_merge($widths, [11, 11, 10, 9, 9]);

$xls->setColumnWidths($widths);
$xls->freezeRows(5);
$xls->enableAutoFilter(5);

// ── Row 1–3: Title block ──────────────────────────────────────────────────────
$termInfo = trim(($sessionYear ? 'Session: ' . $sessionYear : '') . ($currentTerm ? '  |  Term: ' . $currentTerm : ''));
$xls->addRow([['v' => $schoolName,                       's' => 'title']], 'title');
$xls->addRow([['v' => 'Class Result Report — ' . $className, 's' => 'title']], 'title');
$xls->addRow([['v' => $termInfo . '  |  Exported: ' . $exportDate, 's' => '']], '');
$xls->addBlankRow();

// ── Row 5: Sub-header — subject group labels ──────────────────────────────────
$subHeaders = ['#', 'Student Name', 'GR No.', 'Roll No.', 'Position'];
foreach ($subjectOrder as $sid => $sn) {
    // Truncate subject name to fit header
    $short = strlen($sn) > 14 ? substr($sn, 0, 12) . '..' : $sn;
    $subHeaders[] = $short . ' Max';
    $subHeaders[] = $short . ' Obt';
    $subHeaders[] = $short . ' Gr';
}
$subHeaders[] = 'Total Max';
$subHeaders[] = 'Total Obt';
$subHeaders[] = 'Pct %';
$subHeaders[] = 'Grade';
$subHeaders[] = 'Status';
$xls->addRow($subHeaders, 'header');

// ── Data rows ─────────────────────────────────────────────────────────────────
foreach ($students as $idx => $stu) {
    $pos    = $stu['position'];
    $posSty = match ($pos) { 1 => 'gold1', 2 => 'gold2', 3 => 'gold3', default => '' };
    $rowSty = ($idx % 2 === 0) ? 'data' : 'alt';
    $rSty   = ($pos !== null && $pos <= 3) ? $posSty : $rowSty;

    $posLabel   = $pos !== null ? positionLabel($pos) : '—';
    $hasMarks   = $stu['grand_max'] > 0;
    $passStatus = !$hasMarks ? '—' : ($stu['grand_pct'] >= 50 ? 'Pass' : 'Fail');

    $cells   = [];
    $cells[] = ['v' => $idx + 1,         's' => 'center'];
    $cells[] = ['v' => $stu['name'],      's' => $rSty];
    $cells[] = ['v' => $stu['gr_no'],     's' => $rSty];
    $cells[] = ['v' => $stu['roll_no'] ?? '', 's' => 'center'];
    $cells[] = ['v' => $posLabel,         's' => ($pos && $pos <= 3) ? $posSty : 'center'];

    foreach ($subjectOrder as $sid => $sn) {
        $sub = $stu['subjects'][$sid] ?? null;
        if ($sub && $sub['max'] > 0) {
            $cells[] = ['v' => (int)round($sub['max']),      's' => 'center'];
            $cells[] = ['v' => round($sub['obtained'], 1),   's' => 'center'];
            $cells[] = ['v' => $sub['grade'],                's' => 'center'];
        } else {
            $cells[] = ['v' => '—', 's' => 'warn'];
            $cells[] = ['v' => '—', 's' => 'warn'];
            $cells[] = ['v' => '—', 's' => 'warn'];
        }
    }

    $cells[] = ['v' => $hasMarks ? (int)round($stu['grand_max'])      : '—', 's' => 'center'];
    $cells[] = ['v' => $hasMarks ? round($stu['grand_obtained'], 1)   : '—', 's' => 'center'];
    $cells[] = ['v' => $hasMarks ? $stu['grand_pct'] . '%'            : '—', 's' => 'center'];
    $cells[] = ['v' => $stu['overall_grade'],                                  's' => 'center'];
    $cells[] = ['v' => $passStatus,                                            's' => ($passStatus === 'Pass' ? 'center' : ($passStatus === 'Fail' ? 'warn' : 'center'))];

    $xls->addRow($cells, $rowSty);
}

// ══════════════════════════════════════════════════════════════════════════════
// SHEET 2 — Position Holders & Rankings
// ══════════════════════════════════════════════════════════════════════════════
$xls->nextSheet('Rankings');
$xls->setColumnWidths([6, 28, 13, 10, 10, 13, 12, 10]);
$xls->freezeRows(4);

$xls->addRow([['v' => $schoolName . ' — Position Holders', 's' => 'title']], 'title');
$xls->addRow([['v' => $className . '  |  ' . $termInfo,   's' => '']], '');
$xls->addBlankRow();
$xls->addRow(['Rank', 'Student Name', 'GR No.', 'Roll No.', 'Total Obt', 'Total Max', 'Percentage', 'Grade'], 'header2');

foreach ($students as $stu) {
    if ($stu['grand_max'] == 0) continue;
    $pos  = $stu['position'];
    $sty  = match ($pos) { 1 => 'gold1', 2 => 'gold2', 3 => 'gold3', default => (($stu === reset($students) || array_search($stu, $students) % 2 === 0) ? 'data' : 'alt') };
    $xls->addRow([
        $pos !== null ? positionLabel($pos) : '—',
        $stu['name'],
        $stu['gr_no'],
        $stu['roll_no'] ?? '',
        round($stu['grand_obtained'], 1),
        (int)round($stu['grand_max']),
        $stu['grand_pct'] . '%',
        $stu['overall_grade'],
    ], $sty);
}

// ══════════════════════════════════════════════════════════════════════════════
// SHEET 3 — Class Summary
// ══════════════════════════════════════════════════════════════════════════════
$xls->nextSheet('Summary');
$xls->setColumnWidths([32, 18]);

$xls->addRow([['v' => $schoolName . ' — Class Summary', 's' => 'title']], 'title');
$xls->addRow([['v' => $className . '  |  ' . $termInfo, 's' => '']], '');
$xls->addBlankRow();
$xls->addRow([['v' => 'RESULT STATISTICS', 's' => 'footer']], 'footer');
$xls->addRow(['Total Students Enrolled',    count($students)], 'data');
$xls->addRow(['Students with Published Results', count($withMarks)], 'alt');
$xls->addRow(['Passed (≥ 50%)',             $passCount], 'data');
$xls->addRow(['Failed (< 50%)',             $failCount], 'alt');
$xls->addRow(['Class Average Percentage',   $avgPct . '%'], 'data');
$xls->addBlankRow();
$xls->addRow([['v' => 'POSITION HOLDERS', 's' => 'footer']], 'footer');
foreach ($top3 as $rank => $names) {
    $sty = match ($rank) { 1 => 'gold1', 2 => 'gold2', 3 => 'gold3', default => 'data' };
    $xls->addRow([positionLabel($rank) . ' Place', implode(', ', $names)], $sty);
}
if (empty($top3)) {
    $xls->addRow(['No ranked students yet — results may not be fully published.', ''], 'warn');
}
$xls->addBlankRow();

// Grade distribution
$xls->addRow([['v' => 'GRADE DISTRIBUTION', 's' => 'footer']], 'footer');
$gradeDist = [];
foreach ($withMarks as $stu) { $gradeDist[$stu['overall_grade']] = ($gradeDist[$stu['overall_grade']] ?? 0) + 1; }
foreach (['A++','A+','A','B++','B+','B','C','D','E','U'] as $g) {
    if (isset($gradeDist[$g])) {
        $xls->addRow(['Grade ' . $g, $gradeDist[$g] . ' student(s)'], (array_search($g, array_keys($gradeDist)) % 2 === 0 ? 'data' : 'alt'));
    }
}

// ── Download ──────────────────────────────────────────────────────────────────
$filename = 'Results_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $className) . '_' . date('Y-m-d') . '.xlsx';
logActivity($user['id'], 'result_excel_export', "Exported Excel results: class $className (ID:$classId)");
$xls->download($filename);

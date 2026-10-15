<?php
/**
 * VP Main Campus — Excel result export.
 * Access: vp_main (vp_publish_results) OR admin.
 * Only exports published assessments for Main Campus classes.
 *
 * GET  ?class_id=N  → download Excel for that class
 * GET  (no class)   → show class-picker UI (embedded in result-approvals)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/xlsx_writer.php';
require_once __DIR__ . '/../includes/result_ranking.php';
require_once __DIR__ . '/../../config/db.php';

$user = requireAuth('vp_main', 'admin', 'examination_head');
if ($user['role'] === 'vp_main')         requirePermission('vp_publish_results');
if ($user['role'] === 'examination_head') requirePermission('eh_results');

$db       = getDB();
$classId  = (int)($_GET['class_id'] ?? 0);

// ── Validate class (must be main campus) ─────────────────────────────────────
$classRow = null;
if ($classId) {
    try {
        $cSt = $db->prepare(
            "SELECT id, name, grade, section
             FROM classes
             WHERE id=? AND COALESCE(wing,'main')='main' AND is_ilc=0 AND COALESCE(is_montessori,0)=0"
        );
        $cSt->execute([$classId]);
        $classRow = $cSt->fetch();
    } catch (Exception $e) {}
}

if (!$classRow || !$classId) {
    // Show picker UI
    try {
        $allClasses = $db->query(
            "SELECT id, name FROM classes
             WHERE COALESCE(wing,'main')='main' AND is_ilc=0 AND COALESCE(is_montessori,0)=0
             ORDER BY grade, section, name"
        )->fetchAll();
    } catch (Exception $e) { $allClasses = []; }

    pageHead('Download Class Results (Excel)', 'vp_main');
    $links = ($user['role'] === 'admin') ? getAdminLinks() : getVpLinks();
    ?>
    <div class="portal-wrap">
    <?php sidebar($user['role'], 'result-approvals', $links, $user); ?>
    <div class="main-area">
    <?php topbar('Download Class Results — Excel', $user); ?>
    <div class="page-content">
    <?= flashHtml() ?>
    <div class="sec-card" style="max-width:500px">
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
            <i class="fas fa-download me-1"></i>Download Excel
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

// ── Fetch result data ─────────────────────────────────────────────────────────
$students = getClassResultData($db, $classId, true);

if (empty($students)) {
    // Nothing to export — redirect back with message
    setFlash('warning', 'No published results found for ' . h($classRow['name']) . '. Make sure results have been published first.');
    redirect('/portal/vp/result-approvals.php?tab=published');
}

// ── Collect subject list (in display order) ───────────────────────────────────
$subjectOrder = [];
foreach ($students as $stu) {
    foreach ($stu['subjects'] as $sid => $sub) {
        if (!isset($subjectOrder[$sid])) {
            $subjectOrder[$sid] = $sub['name'];
        }
    }
}
asort($subjectOrder); // alphabetical

// ── Settings ──────────────────────────────────────────────────────────────────
$schoolName  = getSetting('school_name', 'Bahria Model College');
$sessionYear = getSetting('session_year', SESSION_YEAR);
$currentTerm = getSetting('current_term', '');
$exportDate  = date('d F Y');
$className   = $classRow['name'];

// ── Top 3 ─────────────────────────────────────────────────────────────────────
$top3 = [];
foreach ($students as $stu) {
    if ($stu['position'] === null) continue;
    if ($stu['position'] <= 3) $top3[$stu['position']][] = $stu['name'];
    if (count($top3) >= 3 && max(array_keys($top3)) >= 3) break;
}
ksort($top3);

$totalStudents  = count($students);
$withMarks      = array_filter($students, fn($s) => $s['grand_max'] > 0);
$passCount      = count(array_filter($withMarks, fn($s) => $s['grand_pct'] >= 50));
$avgPct         = count($withMarks) > 0
    ? round(array_sum(array_column($withMarks, 'grand_pct')) / count($withMarks), 1)
    : 0;

// ── Build Excel ───────────────────────────────────────────────────────────────
$xls = new XlsxWriter('Results');

// Dynamic column widths: name(25), gr_no(14), roll(10), pos(8),
//   then per subject: obtained(10), grade(8),
//   then total(10), pct(8), grade(8)
$widths = [25, 14, 10, 8];
foreach ($subjectOrder as $sid => $sn) {
    $widths[] = 14; // obtained/max
    $widths[] = 8;  // grade
}
$widths[] = 12; // grand obtained
$widths[] = 10; // grand max
$widths[] = 10; // grand pct
$widths[] = 9;  // overall grade

$xls->setColumnWidths($widths);
$xls->freezeRows(5);
$xls->enableAutoFilter(5);

// Row 1: School title
$titleColSpan = 4 + count($subjectOrder) * 2 + 4;
$xls->addRow([['v' => $schoolName, 's' => 'title']], 'title');
$xls->addRow([['v' => 'Class Result Report — ' . $className, 's' => 'title']], 'title');
$termInfo = trim(($sessionYear ? 'Session: ' . $sessionYear : '') . ($currentTerm ? '  |  Term: ' . $currentTerm : ''));
$xls->addRow([['v' => $termInfo . '  |  Exported: ' . $exportDate, 's' => '']], '');
$xls->addBlankRow();

// Row 5: Headers
$headers = ['Student Name', 'GR No.', 'Roll No.', 'Position'];
foreach ($subjectOrder as $sid => $sn) {
    $headers[] = $sn . ' (Obt/Max)';
    $headers[] = $sn . ' (Grade)';
}
$headers[] = 'Total Obtained';
$headers[] = 'Total Max';
$headers[] = 'Percentage';
$headers[] = 'Overall Grade';
$xls->addRow($headers, 'header');

// Data rows
foreach ($students as $idx => $stu) {
    $pos    = $stu['position'];
    $rowSty = ($idx % 2 === 0) ? 'data' : 'alt';

    // Position style
    $posLabel = $pos !== null ? positionLabel($pos) : '—';
    $posSty   = match ($pos) {
        1 => 'gold1',
        2 => 'gold2',
        3 => 'gold3',
        default => $rowSty,
    };

    $cells   = [];
    $cells[] = ['v' => $stu['name'],   's' => ($pos && $pos <= 3) ? $posSty : $rowSty];
    $cells[] = ['v' => $stu['gr_no'],  's' => $rowSty];
    $cells[] = ['v' => $stu['roll_no'] ?? '', 's' => $rowSty];
    $cells[] = ['v' => $posLabel, 's' => $posSty];

    foreach ($subjectOrder as $sid => $sn) {
        $sub = $stu['subjects'][$sid] ?? null;
        if ($sub && $sub['max'] > 0) {
            $obtMax = round($sub['obtained'], 1) . '/' . round($sub['max'], 0);
            $cells[] = ['v' => $obtMax,     's' => $rowSty];
            $cells[] = ['v' => $sub['grade'], 's' => $rowSty];
        } else {
            $cells[] = ['v' => '—', 's' => $rowSty];
            $cells[] = ['v' => '—', 's' => $rowSty];
        }
    }

    $cells[] = ['v' => $stu['grand_max'] > 0 ? round($stu['grand_obtained'], 1) : '—', 's' => $rowSty];
    $cells[] = ['v' => $stu['grand_max'] > 0 ? round($stu['grand_max'], 0)      : '—', 's' => $rowSty];
    $cells[] = ['v' => $stu['grand_max'] > 0 ? $stu['grand_pct'] . '%'          : '—', 's' => $rowSty];
    $cells[] = ['v' => $stu['overall_grade'], 's' => $rowSty];

    $xls->addRow($cells, $rowSty);
}

// Summary rows
$xls->addBlankRow();
$xls->addRow([['v' => '── CLASS SUMMARY ──', 's' => 'footer']], 'footer');
$xls->addRow([['v' => 'Total Students:', 's' => 'bold'], ['v' => $totalStudents, 's' => 'bold']], 'data');
$xls->addRow([['v' => 'Students with Marks:', 's' => 'bold'], ['v' => count($withMarks), 's' => 'bold']], 'data');
$xls->addRow([['v' => 'Pass (≥50%):', 's' => 'bold'], ['v' => $passCount, 's' => 'bold']], 'data');
$xls->addRow([['v' => 'Class Average %:', 's' => 'bold'], ['v' => $avgPct . '%', 's' => 'bold']], 'alt');

foreach ($top3 as $rank => $names) {
    $label = positionLabel($rank) . ' Place:';
    $sty   = match ($rank) { 1 => 'gold1', 2 => 'gold2', 3 => 'gold3', default => 'data' };
    $xls->addRow([['v' => $label, 's' => $sty], ['v' => implode(', ', $names), 's' => $sty]], $sty);
}

$filename = 'Results_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $className) . '_' . date('Y-m-d') . '.xlsx';
logActivity($user['id'], 'result_excel_export', "Exported results for class $className (ID:$classId)");
$xls->download($filename);

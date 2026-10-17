<?php
/**
 * VP Montessori — Formative Assessment Excel export.
 * Exports published FA assessments for a Montessori class.
 * Access: vp_montessori (monte_result_approvals) | admin
 *
 * GET ?class_id=N  → download XLSX
 * GET (no class)   → show class-picker
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/xlsx_writer.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('vp_montessori', 'admin');
if ($user['role'] === 'vp_montessori') requirePermission('monte_result_approvals');

$db      = getDB();
$classId = (int)($_GET['class_id'] ?? 0);

// ── Validate class ────────────────────────────────────────────────────────────
$classRow = null;
if ($classId) {
    try {
        $cSt = $db->prepare("SELECT id, name FROM classes WHERE id=? AND COALESCE(is_montessori,0)=1");
        $cSt->execute([$classId]);
        $classRow = $cSt->fetch();
    } catch (Exception $e) {}
}

if (!$classRow) {
    try {
        $allClasses = $db->query(
            "SELECT id, name FROM classes WHERE COALESCE(is_montessori,0)=1 ORDER BY grade, section, name"
        )->fetchAll();
    } catch (Exception $e) { $allClasses = []; }

    pageHead('FA Export — Excel', 'vp_montessori');
    $links = ($user['role'] === 'admin') ? getAdminLinks() : getVpMontessoriLinks();
    ?>
    <div class="portal-wrap">
    <?php sidebar($user['role'], 'result-approvals', $links, $user); ?>
    <div class="main-area">
    <?php topbar('Formative Assessment Export', $user); ?>
    <div class="page-content">
    <?= flashHtml() ?>
    <div class="sec-card" style="max-width:460px">
      <div class="sec-head"><i class="fas fa-file-excel me-2 text-success"></i>Select Class</div>
      <div class="sec-body">
        <form method="GET">
          <label class="form-label fw-semibold" style="font-size:.85rem">Montessori Class</label>
          <select name="class_id" class="form-select form-select-sm mb-3">
            <option value="">— Select —</option>
            <?php foreach ($allClasses as $cl): ?>
            <option value="<?= $cl['id'] ?>"><?= h($cl['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-success btn-sm">
            <i class="fas fa-download me-1"></i>Download Excel
          </button>
        </form>
      </div>
    </div>
    </div></div></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </body></html>
    <?php exit;
}

// ── Fetch students ────────────────────────────────────────────────────────────
try {
    $stSt = $db->prepare(
        "SELECT s.id, u.name AS student_name, u.user_id AS gr_no, s.roll_no
         FROM students s JOIN users u ON s.user_id=u.id
         WHERE s.class_id=? AND s.deleted_at IS NULL ORDER BY s.roll_no, u.name"
    );
    $stSt->execute([$classId]);
    $students = $stSt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $students = []; }

if (empty($students)) {
    setFlash('warning', 'No students found for ' . h($classRow['name']) . '.');
    redirect('/portal/vp-montessori/result-approvals.php');
}

// ── Fetch published FA assessments for this class ─────────────────────────────
// Group by assessment → subject
try {
    $aSt = $db->prepare(
        "SELECT mda.id, mda.assessment_date, mda.topic, mda.criteria, mda.subject_id,
                sb.name AS subject_name, mda.teacher_id
         FROM montessori_daily_assessments mda
         JOIN subjects sb ON sb.id=mda.subject_id
         WHERE mda.class_id=? AND mda.publish_status='published'
         ORDER BY sb.name, mda.assessment_date, mda.id"
    );
    $aSt->execute([$classId]);
    $assessments = $aSt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $assessments = []; }

// ── Fetch student entries ─────────────────────────────────────────────────────
$entryMap = []; // [assessment_id][student_id] = {ratings,overall,remarks}
if (!empty($assessments)) {
    $aIds = implode(',', array_column($assessments, 'id'));
    try {
        $eSt = $db->query(
            "SELECT assessment_id, student_id, ratings, overall, remarks
             FROM montessori_daily_assessment_entries
             WHERE assessment_id IN ($aIds)"
        );
        foreach ($eSt->fetchAll(PDO::FETCH_ASSOC) as $e) {
            $entryMap[$e['assessment_id']][$e['student_id']] = $e;
        }
    } catch (Exception $e) {}
}

// ── Settings ──────────────────────────────────────────────────────────────────
$schoolName  = getSetting('school_name', 'Bahria Model College');
$sessionYear = getSetting('session_year', SESSION_YEAR);
$exportDate  = date('d F Y');
$className   = $classRow['name'];

// ── Group assessments by subject ──────────────────────────────────────────────
$bySubject = [];
foreach ($assessments as $a) {
    $bySubject[$a['subject_id']]['name'] = $a['subject_name'];
    $bySubject[$a['subject_id']]['assessments'][] = $a;
}

// ══════════════════════════════════════════════════════════════════════════════
// Build XLSX
// ══════════════════════════════════════════════════════════════════════════════
$xls = new XlsxWriter('FA Results');

// Widths: name(26), gr_no(12), roll(9), then per assessment: overall(8), remarks(18)
$widths = [26, 12, 9];
foreach ($assessments as $a) {
    $widths[] = 8;   // overall rating
    $widths[] = 18;  // remarks/note
}
$xls->setColumnWidths($widths);
$xls->freezeRows(5);
$xls->enableAutoFilter(5);

// Title block
$xls->addRow([['v' => $schoolName . ' — Montessori Campus', 's' => 'title']], 'title');
$xls->addRow([['v' => 'Formative Assessment Results — ' . $className, 's' => 'title']], 'title');
$xls->addRow([['v' => ($sessionYear ? 'Session: ' . $sessionYear : '') . '  |  Exported: ' . $exportDate, 's' => '']], '');
$xls->addBlankRow();

// Header row
$headers = ['Student Name', 'GR No.', 'Roll No.'];
foreach ($assessments as $a) {
    $subShort = strlen($a['subject_name']) > 12 ? substr($a['subject_name'], 0, 10) . '..' : $a['subject_name'];
    $dateShort = date('d M', strtotime($a['assessment_date']));
    $headers[] = $subShort . ' (' . $dateShort . ') Rating';
    $headers[] = $subShort . ' (' . $dateShort . ') Remarks';
}
$xls->addRow($headers, 'header');

// Data rows
foreach ($students as $idx => $stu) {
    $rowSty = ($idx % 2 === 0) ? 'data' : 'alt';
    $cells  = [
        ['v' => $stu['student_name'], 's' => $rowSty],
        ['v' => $stu['gr_no'],        's' => $rowSty],
        ['v' => $stu['roll_no'] ?? '', 's' => 'center'],
    ];
    foreach ($assessments as $a) {
        $entry   = $entryMap[$a['id']][$stu['id']] ?? null;
        $overall = $entry ? ($entry['overall'] ?? '—') : '—';
        $remarks = $entry ? ($entry['remarks'] ?? '') : '';
        $sty     = match($overall) {
            'AD'  => 'gold1',
            'ED'  => 'gold3',
            'EMD' => 'warn',
            default => $rowSty,
        };
        $cells[] = ['v' => $overall, 's' => $sty];
        $cells[] = ['v' => $remarks, 's' => $rowSty];
    }
    $xls->addRow($cells, $rowSty);
}

// Legend sheet
$xls->nextSheet('Legend');
$xls->setColumnWidths([20, 40]);
$xls->addRow([['v' => 'Formative Assessment Rating Legend', 's' => 'title']], 'title');
$xls->addBlankRow();
$xls->addRow(['Rating', 'Meaning'], 'header');
$xls->addRow(['AD',  'Advanced Development — student exceeds expected level'],   'gold1');
$xls->addRow(['ED',  'Expected Development — student meets expected level'],      'gold3');
$xls->addRow(['EMD', 'Emerging Development — student is developing towards level'], 'warn');
$xls->addRow(['—',   'Not assessed / No entry recorded'],                          'data');

$filename = 'FA_Results_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $className) . '_' . date('Y-m-d') . '.xlsx';
logActivity($user['id'], 'fa_excel_export', "Exported FA results: Montessori class $className (ID:$classId)");
$xls->download($filename);

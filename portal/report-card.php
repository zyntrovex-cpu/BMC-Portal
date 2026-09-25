<?php
/**
 * Official Student Report Card
 *
 * Access:
 *  student        – own report card only (no student_id param needed)
 *  admin          – any student
 *  vp_main        – any student (vp_results perm)
 *  student_affairs – any student (sa_students perm)
 *  wing_head      – any student (wh_students perm)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$user = requireAuth('admin', 'vp_main', 'student_affairs', 'student', 'wing_head');
$db   = getDB();
$role = $user['role'];

// Role-based permission check
if ($role === 'vp_main') {
    requirePermission('vp_results');
} elseif ($role === 'student_affairs') {
    requirePermission('sa_students');
} elseif ($role === 'wing_head') {
    requirePermission('wh_students');
}

// Resolve student ID
if ($role === 'student') {
    // Students always see their own report only — ignore any GET param
    $me = getStudentByUserId($user['id']);
    if (!$me) { header('Location: /portal/student/dashboard.php'); exit; }
    $studentId = (int)$me['id'];
} else {
    $studentId = (int)($_GET['student_id'] ?? 0);
    if (!$studentId) { header('Location: /portal/index.php?msg=unauthorized'); exit; }
}

// ── Fetch student record ──────────────────────────────────────
try {
    $st = $db->prepare(
        'SELECT s.*, u.name, u.email, u.user_id AS gr_no, u.status AS account_status,
                u.profile_photo, u.photo_status,
                c.name AS class_name, c.id AS class_id, c.grade AS class_grade,
                COALESCE(c.wing,"main") AS class_wing,
                h.name AS house_name, h.color AS house_color
         FROM students s
         JOIN users u ON s.user_id = u.id
         LEFT JOIN classes c ON s.class_id = c.id
         LEFT JOIN houses h ON s.house_id = h.id
         WHERE s.id = ?'
    );
    $st->execute([$studentId]);
} catch (Exception $e) {
    $st = $db->prepare(
        'SELECT s.*, u.name, u.email, u.user_id AS gr_no, u.status AS account_status,
                u.profile_photo, u.photo_status,
                c.name AS class_name, c.id AS class_id, c.grade AS class_grade,
                "main" AS class_wing,
                NULL AS house_name, NULL AS house_color
         FROM students s
         JOIN users u ON s.user_id = u.id
         LEFT JOIN classes c ON s.class_id = c.id
         WHERE s.id = ?'
    );
    $st->execute([$studentId]);
}
$student = $st->fetch();
if (!$student) {
    header('Location: /portal/index.php?msg=unauthorized');
    exit;
}

// ── Fetch assessments + marks grouped by subject ──────────────
$st = $db->prepare(
    'SELECT a.id AS assessment_id, a.name AS assessment_name, a.type, a.max_marks, a.weight, a.date,
            sb.id AS subject_id, sb.name AS subject_name, sb.code AS subject_code,
            m.marks_obtained, m.remarks
     FROM assessments a
     JOIN subjects sb ON a.subject_id = sb.id
     LEFT JOIN marks m ON m.assessment_id = a.id AND m.student_id = ?
     WHERE a.class_id = ?
     ORDER BY sb.name, a.date, a.id'
);
$st->execute([$studentId, $student['class_id']]);
$rows = $st->fetchAll();

$bySubject = [];
foreach ($rows as $r) {
    $sid = $r['subject_id'];
    $bySubject[$sid]['name'] = $r['subject_name'];
    $bySubject[$sid]['code'] = $r['subject_code'];
    $bySubject[$sid]['assessments'][] = $r;
}

// ── Compute per-subject stats & overall ──────────────────────
$subjects      = [];
$grandObtained = 0;
$grandMax      = 0;
$grandWSum     = 0;
$grandWTotal   = 0;

foreach ($bySubject as $sid => $sub) {
    $totalObtained = 0;
    $totalMax      = 0;
    $weightedSum   = 0;
    $totalWeight   = 0;

    foreach ($sub['assessments'] as $a) {
        if ($a['marks_obtained'] !== null) {
            $totalObtained += (float)$a['marks_obtained'];
            $totalMax      += (float)$a['max_marks'];
            if ($a['weight'] > 0) {
                $pct          = $a['max_marks'] > 0 ? ($a['marks_obtained'] / $a['max_marks'] * 100) : 0;
                $weightedSum += $pct * $a['weight'];
                $totalWeight += $a['weight'];
            }
        }
    }

    $overallPct = $totalWeight > 0
        ? round($weightedSum / $totalWeight, 1)
        : ($totalMax > 0 ? round($totalObtained / $totalMax * 100, 1) : 0);

    $subjects[$sid] = array_merge($sub, [
        'total_obtained' => round($totalObtained, 2),
        'total_max'      => $totalMax,
        'overall_pct'    => $overallPct,
        'grade'          => getGradeLetter($overallPct, $totalMax > 0),
    ]);

    if ($totalMax > 0) {
        $grandObtained += $totalObtained;
        $grandMax      += $totalMax;
        if ($totalWeight > 0) {
            $grandWSum   += $weightedSum;
            $grandWTotal += $totalWeight;
        }
    }
}

$grandPct   = $grandWTotal > 0
    ? round($grandWSum / $grandWTotal, 1)
    : ($grandMax > 0 ? round($grandObtained / $grandMax * 100, 1) : 0);

// ── Attendance ────────────────────────────────────────────────
$attendance = getStudentAttendanceSummary($studentId);
$attTotal   = array_sum(array_column($attendance, 'total'));
$attPresent = array_sum(array_column($attendance, 'present'));
$attPct     = $attTotal > 0 ? round($attPresent / $attTotal * 100, 1) : 0;

// ── Profile photo ─────────────────────────────────────────────
$photoUrl = null;
if ($student['photo_status'] === 'approved' && $student['profile_photo']) {
    $photoUrl = url('/portal/uploads/profile-photos/' . rawurlencode($student['profile_photo']));
}

// ── Grade helpers ─────────────────────────────────────────────
function getGradeLetter(float $pct, bool $hasMarks): string {
    if (!$hasMarks) return 'N/A';
    if ($pct >= 90) return 'A+';
    if ($pct >= 80) return 'A';
    if ($pct >= 70) return 'B';
    if ($pct >= 60) return 'C';
    if ($pct >= 50) return 'D';
    return 'F';
}
function gradeColor(string $g): string {
    return match($g) {
        'A+'    => '#14532d',
        'A'     => '#166534',
        'B'     => '#1e40af',
        'C'     => '#92400e',
        'D'     => '#7c2d12',
        'F'     => '#991b1b',
        default => '#374151',
    };
}
function gradeBg(string $g): string {
    return match($g) {
        'A+'    => '#dcfce7',
        'A'     => '#d1fae5',
        'B'     => '#dbeafe',
        'C'     => '#fef3c7',
        'D'     => '#ffedd5',
        'F'     => '#fee2e2',
        default => '#f3f4f6',
    };
}
function typeLabel(string $t): string {
    return match($t) {
        'quiz'        => 'Quiz',
        'assignment'  => 'Assignment',
        'class_test'  => 'Class Test',
        'mid_term'    => 'Mid Term',
        'final_term'  => 'Final Term',
        'practical'   => 'Practical',
        default       => ucwords(str_replace('_', ' ', $t)),
    };
}
function typeBadgeStyle(string $t): string {
    return match($t) {
        'quiz'        => 'background:#dbeafe;color:#1d4ed8',
        'assignment'  => 'background:#f1f5f9;color:#475569',
        'class_test'  => 'background:#ede9fe;color:#6d28d9',
        'mid_term'    => 'background:#fef9c3;color:#92400e',
        'final_term'  => 'background:#fee2e2;color:#991b1b',
        'practical'   => 'background:#dcfce7;color:#166534',
        default       => 'background:#f1f5f9;color:#475569',
    };
}

$overallGrade  = getGradeLetter($grandPct, $grandMax > 0);
$reportDate    = date('d F Y');
$schoolName    = getSetting('school_name', 'BMC Bin Qasim');
$schoolAddr    = getSetting('school_address', '');
$principalName = getSetting('principal_name', 'Lt. Cdr. Abu Bakar');
$sessionYear   = getSetting('session_year', date('Y') . '–' . (date('Y') + 1));
$currentTerm   = getSetting('current_term', '');
$base          = defined('BASE_URL') ? BASE_URL : '';

$backUrl = match($role) {
    'admin'           => '/portal/admin/users.php',
    'vp_main'         => '/portal/vp/students.php',
    'student_affairs' => '/portal/student-affairs/results.php',
    'wing_head'       => '/portal/wing-head/students.php',
    'student'         => '/portal/student/results.php',
    default           => '/portal/index.php',
};

// Wing label for campus display
$campusLabel = match($student['class_wing'] ?? 'main') {
    'montessori' => 'Montessori Campus',
    'ilc'        => 'ILC Campus',
    default      => 'Main Campus',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Report Card — <?= h($student['name']) ?></title>
<link rel="icon" type="image/png" href="<?= $base ?>/assets/bmc-logo.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ── Screen chrome ───────────────────────────────────────────── */
:root {
  --navy:   #0f2456;
  --gold:   #b8860b;
  --accent: #1d4ed8;
}
body {
  font-family: 'Segoe UI', Arial, sans-serif;
  background: #e8ecf0;
  color: #1e293b;
  margin: 0;
  padding: 0;
}
.screen-toolbar {
  background: var(--navy);
  color: #fff;
  padding: 10px 18px;
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  position: sticky;
  top: 0;
  z-index: 200;
  box-shadow: 0 2px 10px rgba(0,0,0,.3);
}
.screen-toolbar a, .screen-toolbar button {
  color: #fff;
  text-decoration: none;
  font-size: 13px;
  background: rgba(255,255,255,.14);
  border: 1px solid rgba(255,255,255,.28);
  border-radius: 6px;
  padding: 5px 14px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: background .15s;
}
.screen-toolbar a:hover, .screen-toolbar button:hover { background: rgba(255,255,255,.26); }
.screen-toolbar .toolbar-title {
  flex: 1;
  font-weight: 600;
  font-size: 14px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0;
}
.toolbar-badge {
  background: #fbbf24;
  color: #78350f;
  font-size: 10px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 20px;
  letter-spacing: .04em;
  border: none;
}

/* ── Page wrapper ────────────────────────────────────────────── */
.page-wrap {
  max-width: 860px;
  margin: 24px auto;
  padding: 0 16px 48px;
}

/* ── Report card shell ───────────────────────────────────────── */
.report-card {
  background: #fff;
  border: 1px solid #cbd5e1;
  box-shadow: 0 4px 20px rgba(0,0,0,.12);
}

/* ── Official letterhead ─────────────────────────────────────── */
.rc-letterhead {
  border-bottom: 4px double var(--gold);
  padding: 20px 28px 16px;
  display: flex;
  align-items: center;
  gap: 20px;
}
.rc-logo {
  width: 72px;
  height: 72px;
  object-fit: contain;
  flex-shrink: 0;
}
.rc-logo-placeholder {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: var(--navy);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
  border: 3px solid var(--gold);
}
.rc-school-block { flex: 1; min-width: 0; }
.rc-school-name {
  font-size: 1.3rem;
  font-weight: 800;
  color: var(--navy);
  letter-spacing: .02em;
  line-height: 1.2;
}
.rc-school-sub {
  font-size: .78rem;
  color: #64748b;
  margin-top: 2px;
}
.rc-campus-tag {
  display: inline-block;
  background: var(--navy);
  color: #fff;
  font-size: .68rem;
  font-weight: 600;
  padding: 2px 10px;
  border-radius: 20px;
  margin-top: 6px;
  letter-spacing: .05em;
}
.rc-doc-meta {
  text-align: right;
  flex-shrink: 0;
  font-size: .75rem;
  color: #475569;
  line-height: 1.7;
}
.rc-doc-meta .rc-doc-title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--navy);
  display: block;
  letter-spacing: .06em;
  text-transform: uppercase;
  margin-bottom: 2px;
}
.rc-doc-meta .rc-serial {
  font-size: .68rem;
  color: #94a3b8;
}

/* ── Title banner ────────────────────────────────────────────── */
.rc-title-band {
  background: var(--navy);
  color: #fff;
  text-align: center;
  padding: 8px 20px;
  font-size: .8rem;
  font-weight: 600;
  letter-spacing: .18em;
  text-transform: uppercase;
}
.rc-title-band span { color: var(--gold) !important; margin: 0 6px; font-size: .6rem; }

/* ── Student details block ───────────────────────────────────── */
.rc-student-block {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 20px;
  padding: 18px 28px;
  border-bottom: 2px solid #e2e8f0;
  align-items: start;
}
.rc-photo {
  width: 88px;
  height: 88px;
  border-radius: 4px;
  object-fit: cover;
  border: 2px solid var(--navy);
}
.rc-photo-init {
  width: 88px;
  height: 88px;
  border-radius: 4px;
  background: var(--navy);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
  border: 2px solid var(--navy);
}
.rc-detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4px 20px;
}
.rc-student-name {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--navy);
  grid-column: 1 / -1;
  margin-bottom: 6px;
  padding-bottom: 6px;
  border-bottom: 1px dashed #cbd5e1;
}
.rc-field {
  font-size: .8rem;
  color: #374151;
  padding: 2px 0;
}
.rc-field .lbl {
  font-weight: 600;
  color: #64748b;
  font-size: .72rem;
  text-transform: uppercase;
  letter-spacing: .04em;
  display: block;
  margin-bottom: 1px;
}

/* ── Result summary bar ──────────────────────────────────────── */
.rc-summary {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  border-bottom: 2px solid #e2e8f0;
}
.rc-summary-cell {
  text-align: center;
  padding: 14px 8px;
  border-right: 1px solid #e2e8f0;
}
.rc-summary-cell:last-child { border-right: none; }
.rc-sum-value {
  font-size: 1.4rem;
  font-weight: 800;
  color: var(--accent);
  line-height: 1;
}
.rc-sum-label {
  font-size: .65rem;
  color: #94a3b8;
  margin-top: 4px;
  text-transform: uppercase;
  letter-spacing: .06em;
}

/* ── Section heading ─────────────────────────────────────────── */
.rc-sec-head {
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  border-bottom: 1px solid #e2e8f0;
  padding: 8px 28px;
  font-size: .72rem;
  font-weight: 700;
  color: var(--navy);
  text-transform: uppercase;
  letter-spacing: .1em;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* ── Subject performance tables ──────────────────────────────── */
.rc-subj-block {
  padding: 12px 28px 16px;
  border-bottom: 1px solid #f1f5f9;
}
.rc-subj-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}
.rc-subj-title {
  font-size: .9rem;
  font-weight: 700;
  color: var(--navy);
}
.rc-subj-code {
  font-size: .72rem;
  color: #94a3b8;
  font-weight: 400;
}
.rc-subj-pct {
  margin-left: auto;
  font-size: .85rem;
  font-weight: 700;
  color: var(--accent);
}
.rc-table {
  width: 100%;
  font-size: .76rem;
  border-collapse: collapse;
  border: 1px solid #e2e8f0;
}
.rc-table th {
  background: var(--navy);
  color: #fff;
  padding: 6px 10px;
  text-align: left;
  font-size: .68rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .06em;
  white-space: nowrap;
}
.rc-table td {
  padding: 5px 10px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}
.rc-table tbody tr:nth-child(even) td { background: #f8fafc; }
.rc-table tfoot td {
  background: #f1f5f9;
  font-weight: 700;
  border-top: 2px solid #cbd5e1;
  font-size: .78rem;
  border-bottom: none;
}
.rc-badge {
  display: inline-block;
  padding: 1px 8px;
  border-radius: 3px;
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .03em;
}
.rc-type-badge {
  display: inline-block;
  padding: 1px 8px;
  border-radius: 20px;
  font-size: .68rem;
  font-weight: 600;
  white-space: nowrap;
}

/* ── Attendance section ──────────────────────────────────────── */
.rc-att-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: 10px;
  padding: 14px 28px;
}
.rc-att-card {
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  padding: 10px 12px;
  font-size: .77rem;
}
.rc-att-subj { font-weight: 700; color: var(--navy); margin-bottom: 6px; font-size: .8rem; }
.rc-att-row  { display: flex; justify-content: space-between; color: #64748b; margin-bottom: 2px; }
.rc-att-bar  { height: 3px; background: #e2e8f0; border-radius: 2px; margin-top: 8px; overflow: hidden; }
.rc-att-fill { height: 100%; border-radius: 2px; }

/* ── Grade scale legend ──────────────────────────────────────── */
.rc-grade-legend {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  padding: 10px 28px;
  font-size: .7rem;
  color: #64748b;
  border-top: 1px solid #e2e8f0;
}
.rc-grade-legend .gl-item {
  display: flex;
  align-items: center;
  gap: 4px;
}

/* ── Remarks + Signature ─────────────────────────────────────── */
.rc-remarks-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0;
  border-top: 2px solid #e2e8f0;
}
.rc-remarks-cell {
  padding: 14px 28px;
  font-size: .78rem;
  border-right: 1px solid #e2e8f0;
}
.rc-remarks-cell:last-child { border-right: none; }
.rc-remarks-label {
  font-size: .68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: #64748b;
  margin-bottom: 6px;
}
.rc-remarks-lines {
  border-bottom: 1px solid #94a3b8;
  min-height: 24px;
  margin-bottom: 4px;
}
.rc-sig-block {
  display: flex;
  gap: 0;
  border-top: 1px solid #e2e8f0;
}
.rc-sig-cell {
  flex: 1;
  text-align: center;
  padding: 28px 12px 14px;
  border-right: 1px solid #e2e8f0;
  font-size: .74rem;
  color: #475569;
}
.rc-sig-cell:last-child { border-right: none; }
.rc-sig-line {
  border-top: 1px solid #374151;
  padding-top: 6px;
  margin-top: 0;
}
.rc-sig-title { font-weight: 700; color: var(--navy); font-size: .8rem; }

/* ── Verification footer ─────────────────────────────────────── */
.rc-footer {
  background: var(--navy);
  color: rgba(255,255,255,.7);
  font-size: .68rem;
  text-align: center;
  padding: 8px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;
}
.rc-footer span { color: rgba(255,255,255,.5); }
.rc-footer .verified-tag {
  background: #166534;
  color: #dcfce7;
  font-size: .65rem;
  font-weight: 600;
  padding: 2px 10px;
  border-radius: 20px;
  letter-spacing: .06em;
}

/* ── Print styles ────────────────────────────────────────────── */
@media print {
  @page {
    size: A4;
    margin: 10mm 12mm;
  }
  * {
    -webkit-print-color-adjust: exact !important;
    color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  body {
    background: #fff !important;
    font-size: 10pt;
  }
  .screen-toolbar { display: none !important; }
  .page-wrap {
    padding: 0;
    max-width: 100%;
    margin: 0;
  }
  .report-card {
    border: none;
    box-shadow: none;
  }
  .rc-subj-block    { page-break-inside: avoid; }
  .rc-att-grid      { page-break-inside: avoid; }
  .rc-sig-block     { page-break-inside: avoid; }
  .rc-remarks-row   { page-break-inside: avoid; }
  .rc-letterhead    { padding: 14px 20px 10px; }
  .rc-student-block { padding: 14px 20px; }
  .rc-subj-block    { padding: 8px 20px 12px; }
  .rc-att-grid      { padding: 10px 20px; }
  .rc-sec-head      { padding: 6px 20px; }
  .rc-footer        { display: flex !important; }
}

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width: 640px) {
  .rc-letterhead    { flex-direction: column; text-align: center; padding: 16px; }
  .rc-doc-meta      { text-align: center; }
  .rc-student-block { grid-template-columns: 1fr; text-align: center; padding: 14px; }
  .rc-photo, .rc-photo-init { margin: 0 auto; }
  .rc-detail-grid   { grid-template-columns: 1fr; }
  .rc-student-name  { text-align: center; }
  .rc-summary       { grid-template-columns: repeat(3, 1fr); }
  .rc-subj-block    { padding: 10px 14px; }
  .rc-att-grid      { padding: 10px 14px; }
  .rc-sec-head      { padding: 7px 14px; }
  .rc-remarks-row   { grid-template-columns: 1fr; }
  .rc-sig-block     { flex-wrap: wrap; }
  .rc-sig-cell      { min-width: 50%; }
  .screen-toolbar .toolbar-title { display: none; }
}
</style>
</head>
<body>

<!-- Screen toolbar -->
<div class="screen-toolbar">
  <a href="<?= $base . $backUrl ?>">
    <i class="fas fa-arrow-left"></i> Back
  </a>
  <span class="toolbar-title">
    Official Report Card — <?= h($student['name']) ?>
  </span>
  <span class="toolbar-badge">RESTRICTED</span>
  <button onclick="window.print()">
    <i class="fas fa-print"></i> Print / Save PDF
  </button>
</div>

<div class="page-wrap">
<div class="report-card">

  <!-- ── Letterhead ─────────────────────────────────────────── -->
  <div class="rc-letterhead">
    <?php if (file_exists(__DIR__ . '/../assets/bmc-logo.png')): ?>
    <img class="rc-logo" src="<?= $base ?>/assets/bmc-logo.png" alt="School Logo"
         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
    <div class="rc-logo-placeholder" style="display:none">BMC</div>
    <?php else: ?>
    <div class="rc-logo-placeholder">BMC</div>
    <?php endif; ?>
    <div class="rc-school-block">
      <div class="rc-school-name"><?= h($schoolName) ?></div>
      <?php if ($schoolAddr): ?>
      <div class="rc-school-sub"><?= h($schoolAddr) ?></div>
      <?php endif; ?>
      <div class="rc-campus-tag"><?= h($campusLabel) ?></div>
    </div>
    <div class="rc-doc-meta">
      <span class="rc-doc-title">Report Card</span>
      Session: <?= h($sessionYear) ?><br>
      <?php if ($currentTerm): ?>Term: <?= h($currentTerm) ?><br><?php endif; ?>
      Date: <?= $reportDate ?>
      <div class="rc-serial">GR# <?= h($student['gr_no'] ?? '—') ?></div>
    </div>
  </div>

  <!-- ── Title band ─────────────────────────────────────────── -->
  <div class="rc-title-band">
    OFFICIAL STUDENT REPORT CARD
    <span>&#9670;</span>
    ACADEMIC SESSION <?= h($sessionYear) ?>
    <span>&#9670;</span>
    <?= h(strtoupper($campusLabel)) ?>
  </div>

  <!-- ── Student details ────────────────────────────────────── -->
  <div class="rc-student-block">
    <?php if ($photoUrl): ?>
    <img class="rc-photo" src="<?= h($photoUrl) ?>" alt="Student Photo">
    <?php else: ?>
    <div class="rc-photo-init"><?= h(mb_strtoupper(mb_substr($student['name'], 0, 2))) ?></div>
    <?php endif; ?>
    <div>
      <div class="rc-student-name"><?= h($student['name']) ?></div>
      <div class="rc-detail-grid">
        <div class="rc-field">
          <span class="lbl">GR Number / Student ID</span>
          <?= h($student['gr_no'] ?? '—') ?>
        </div>
        <div class="rc-field">
          <span class="lbl">Roll No.</span>
          <?= h($student['roll_no'] ?? '—') ?>
        </div>
        <div class="rc-field">
          <span class="lbl">Class / Section</span>
          <?= h($student['class_name'] ?? '—') ?>
        </div>
        <div class="rc-field">
          <span class="lbl">Academic Session</span>
          <?= h($sessionYear) ?>
        </div>
        <?php if (!empty($student['father_name'])): ?>
        <div class="rc-field">
          <span class="lbl">Father's Name</span>
          <?= h($student['father_name']) ?>
        </div>
        <?php elseif (!empty($student['parent_name'])): ?>
        <div class="rc-field">
          <span class="lbl">Parent / Guardian</span>
          <?= h($student['parent_name']) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($student['dob'])): ?>
        <div class="rc-field">
          <span class="lbl">Date of Birth</span>
          <?= fDate($student['dob']) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($student['gender'])): ?>
        <div class="rc-field">
          <span class="lbl">Gender</span>
          <?= ucfirst(h($student['gender'])) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($student['house_name'])): ?>
        <div class="rc-field">
          <span class="lbl">House</span>
          <span style="color:<?= h($student['house_color'] ?? '#1c3054') ?>;font-weight:700">
            <?= h($student['house_name']) ?>
          </span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Result summary ─────────────────────────────────────── -->
  <?php if ($grandMax > 0): ?>
  <div class="rc-summary">
    <div class="rc-summary-cell">
      <div class="rc-sum-value"><?= $grandPct ?>%</div>
      <div class="rc-sum-label">Overall %</div>
    </div>
    <div class="rc-summary-cell">
      <div class="rc-sum-value"
           style="color:<?= gradeColor($overallGrade) ?>;background:<?= gradeBg($overallGrade) ?>;border-radius:4px;padding:2px 10px;display:inline-block">
        <?= $overallGrade ?>
      </div>
      <div class="rc-sum-label">Grade</div>
    </div>
    <div class="rc-summary-cell">
      <div class="rc-sum-value"><?= round($grandObtained, 0) ?> / <?= round($grandMax, 0) ?></div>
      <div class="rc-sum-label">Marks Obtained</div>
    </div>
    <div class="rc-summary-cell">
      <div class="rc-sum-value" style="color:<?= $attPct >= 75 ? '#15803d' : '#dc2626' ?>">
        <?= $attPct ?>%
      </div>
      <div class="rc-sum-label">Attendance</div>
    </div>
    <div class="rc-summary-cell">
      <div class="rc-sum-value"><?= count($subjects) ?></div>
      <div class="rc-sum-label">Subjects</div>
    </div>
  </div>
  <?php else: ?>
  <div style="padding:16px 28px;background:#fffbeb;border-bottom:1px solid #fde68a;font-size:.82rem;color:#92400e">
    <i class="fas fa-info-circle me-1"></i>
    No assessment data has been recorded for this student yet.
  </div>
  <?php endif; ?>

  <!-- ── Subject-wise performance ───────────────────────────── -->
  <div class="rc-sec-head" style="margin-top:0">
    <i class="fas fa-book-open" style="color:var(--accent)"></i>
    Subject-wise Academic Performance
  </div>

  <?php if (empty($subjects)): ?>
  <div style="padding:18px 28px;color:#64748b;font-size:.84rem">
    No assessments found for this student's class.
  </div>
  <?php else: foreach ($subjects as $sub):
    $hasMarks = $sub['total_max'] > 0;
    $g        = $sub['grade'];
  ?>
  <div class="rc-subj-block">
    <div class="rc-subj-head">
      <div class="rc-subj-title">
        <?= h($sub['name']) ?>
        <span class="rc-subj-code">(<?= h($sub['code']) ?>)</span>
      </div>
      <?php if ($hasMarks): ?>
      <div class="rc-subj-pct">
        <span class="rc-badge" style="background:<?= gradeBg($g) ?>;color:<?= gradeColor($g) ?>">
          <?= $g ?>
        </span>
        &nbsp;<?= $sub['overall_pct'] ?>%
      </div>
      <?php endif; ?>
    </div>
    <div style="overflow-x:auto">
    <table class="rc-table">
      <thead>
        <tr>
          <th style="width:32%">Assessment / Exam</th>
          <th>Type</th>
          <th>Date</th>
          <th style="text-align:right">Max Marks</th>
          <th style="text-align:right">Obtained</th>
          <th style="text-align:right">%</th>
          <th style="text-align:right">Weightage</th>
          <th>Grade</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sub['assessments'] as $a):
          $hasMark = $a['marks_obtained'] !== null;
          $aPct    = ($hasMark && $a['max_marks'] > 0)
                     ? round($a['marks_obtained'] / $a['max_marks'] * 100, 1)
                     : 0;
          $aGrade  = $hasMark ? getGradeLetter($aPct, true) : '—';
        ?>
        <tr>
          <td style="font-weight:600"><?= h($a['assessment_name']) ?></td>
          <td>
            <span class="rc-type-badge" style="<?= typeBadgeStyle($a['type']) ?>">
              <?= typeLabel($a['type']) ?>
            </span>
          </td>
          <td style="white-space:nowrap"><?= fDate($a['date']) ?></td>
          <td style="text-align:right"><?= h($a['max_marks']) ?></td>
          <td style="text-align:right;font-weight:700">
            <?= $hasMark ? h($a['marks_obtained']) : '<span style="color:#94a3b8">—</span>' ?>
          </td>
          <td style="text-align:right;color:var(--accent);font-weight:600">
            <?= $hasMark ? $aPct . '%' : '<span style="color:#94a3b8">—</span>' ?>
          </td>
          <td style="text-align:right;color:#64748b">
            <?= $a['weight'] > 0 ? $a['weight'] . '%' : '<span style="color:#cbd5e1">—</span>' ?>
          </td>
          <td>
            <?php if ($hasMark): ?>
            <span class="rc-badge" style="background:<?= gradeBg($aGrade) ?>;color:<?= gradeColor($aGrade) ?>">
              <?= $aGrade ?>
            </span>
            <?php else: ?>
            <span style="color:#94a3b8">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if ($hasMarks): ?>
      <tfoot>
        <tr>
          <td colspan="3">
            <strong>Subject Total</strong>
          </td>
          <td style="text-align:right"><?= round($sub['total_max'], 0) ?></td>
          <td style="text-align:right"><?= round($sub['total_obtained'], 1) ?></td>
          <td style="text-align:right;color:var(--accent)"><?= $sub['overall_pct'] ?>%</td>
          <td></td>
          <td>
            <span class="rc-badge" style="background:<?= gradeBg($g) ?>;color:<?= gradeColor($g) ?>">
              <?= $g ?>
            </span>
          </td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
    </div>
  </div>
  <?php endforeach; endif; ?>

  <!-- ── Attendance summary ─────────────────────────────────── -->
  <?php if (!empty($attendance)): ?>
  <div class="rc-sec-head">
    <i class="fas fa-calendar-check" style="color:#16a34a"></i>
    Attendance Summary
  </div>
  <div class="rc-att-grid">
    <?php foreach ($attendance as $att):
      $subPct   = $att['total'] > 0 ? round($att['present'] / $att['total'] * 100) : 0;
      $barColor = $subPct >= 75 ? '#16a34a' : ($subPct >= 60 ? '#d97706' : '#dc2626');
    ?>
    <div class="rc-att-card">
      <div class="rc-att-subj">
        <?= h($att['subject']) ?>
        <span style="font-size:.68rem;color:#94a3b8;font-weight:400">(<?= h($att['code']) ?>)</span>
      </div>
      <div class="rc-att-row"><span>Present</span><strong style="color:#16a34a"><?= $att['present'] ?></strong></div>
      <div class="rc-att-row"><span>Absent</span><strong style="color:#dc2626"><?= $att['absent'] ?></strong></div>
      <div class="rc-att-row"><span>Leave</span><strong style="color:#d97706"><?= $att['leave'] ?></strong></div>
      <div class="rc-att-row" style="border-top:1px dashed #e2e8f0;margin-top:4px;padding-top:4px">
        <span>Total</span><strong><?= $att['total'] ?></strong>
      </div>
      <div class="rc-att-bar">
        <div class="rc-att-fill" style="width:<?= $subPct ?>%;background:<?= $barColor ?>"></div>
      </div>
      <div style="font-size:.68rem;color:#64748b;text-align:right;margin-top:3px"><?= $subPct ?>% present</div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ── Grade scale legend ─────────────────────────────────── -->
  <div class="rc-grade-legend">
    <strong style="color:#374151;margin-right:4px">Grade Scale:</strong>
    <?php foreach (['A+'=>'≥90%','A'=>'≥80%','B'=>'≥70%','C'=>'≥60%','D'=>'≥50%','F'=>'<50%'] as $gr => $rng): ?>
    <span class="gl-item">
      <span class="rc-badge" style="background:<?= gradeBg($gr) ?>;color:<?= gradeColor($gr) ?>"><?= $gr ?></span>
      <?= $rng ?>
    </span>
    <?php endforeach; ?>
    <?php if ($grandWTotal > 0): ?>
    <span style="margin-left:auto;color:#94a3b8;font-style:italic">* Overall % uses configured assessment weightage</span>
    <?php endif; ?>
  </div>

  <!-- ── Remarks ────────────────────────────────────────────── -->
  <div class="rc-remarks-row">
    <div class="rc-remarks-cell">
      <div class="rc-remarks-label">Class Teacher Remarks</div>
      <div class="rc-remarks-lines"></div>
      <div class="rc-remarks-lines" style="margin-top:6px"></div>
    </div>
    <div class="rc-remarks-cell">
      <div class="rc-remarks-label">Principal Remarks</div>
      <div class="rc-remarks-lines"></div>
      <div class="rc-remarks-lines" style="margin-top:6px"></div>
    </div>
  </div>

  <!-- ── Signatures ─────────────────────────────────────────── -->
  <div class="rc-sig-block">
    <div class="rc-sig-cell">
      <div style="height:36px"></div>
      <div class="rc-sig-line">
        <div class="rc-sig-title">Class Teacher</div>
        <div>Signature &amp; Date</div>
      </div>
    </div>
    <div class="rc-sig-cell">
      <div style="height:36px"></div>
      <div class="rc-sig-line">
        <div class="rc-sig-title">Head of Department</div>
        <div>Signature &amp; Date</div>
      </div>
    </div>
    <div class="rc-sig-cell">
      <div style="height:36px"></div>
      <div class="rc-sig-line">
        <div class="rc-sig-title"><?= h($principalName) ?></div>
        <div>Principal / Vice Principal</div>
      </div>
    </div>
    <div class="rc-sig-cell">
      <div style="height:36px"></div>
      <div class="rc-sig-line">
        <div class="rc-sig-title">Parent / Guardian</div>
        <div>Signature &amp; Date</div>
      </div>
    </div>
  </div>

  <!-- ── Footer ─────────────────────────────────────────────── -->
  <div class="rc-footer">
    <span><?= h($schoolName) ?> &nbsp;·&nbsp; <?= h($campusLabel) ?></span>
    <span class="verified-tag">OFFICIAL DOCUMENT</span>
    <span>Generated: <?= $reportDate ?> &nbsp;·&nbsp; Confidential — For Authorised Use Only</span>
  </div>

</div><!-- /report-card -->
</div><!-- /page-wrap -->

<script>
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') { /* let browser print handle it */ }
});
</script>
</body>
</html>

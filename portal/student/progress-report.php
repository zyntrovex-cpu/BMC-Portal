<?php
/**
 * Student Progress Report
 *
 * Access:
 *  • student  – own report only
 *  • teacher  – students in their assigned class(es)
 *  • admin    – any student
 *  • vp_main  – any student (vp_results perm)
 *  • student_affairs – any student (sa_students perm)
 *  • ilc_vp   – any student (ilc_results perm)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireAuth('student','teacher','admin','vp_main','student_affairs','ilc_vp');
$db   = getDB();
$role = $user['role'];

// ── Resolve which student to show ────────────────────────────────
$studentId = null;

if ($role === 'student') {
    $me = getStudentByUserId($user['id']);
    if (!$me) { header('Location: /portal/student/dashboard.php'); exit; }
    $studentId = (int)$me['id'];
} else {
    $studentId = (int)($_GET['student_id'] ?? 0);
    if (!$studentId) { header('Location: /portal/index.php?msg=unauthorized'); exit; }
}

// ── Fetch student record ──────────────────────────────────────────
$st = $db->prepare(
    'SELECT s.*, u.name, u.email, u.user_id AS login_id, u.status AS account_status,
            u.profile_photo, u.photo_status,
            c.name AS class_name, c.id AS class_id,
            h.name AS house_name, h.color AS house_color
     FROM students s
     JOIN users u ON s.user_id = u.id
     LEFT JOIN classes c ON s.class_id = c.id
     LEFT JOIN houses h ON s.house_id = h.id
     WHERE s.id = ?'
);
$st->execute([$studentId]);
$student = $st->fetch();
if (!$student) { header('Location: /portal/index.php?msg=unauthorized'); exit; }

// ── Access check for non-admin roles ─────────────────────────────
if ($role === 'teacher') {
    $teacher = getTeacherByUserId($user['id']);
    if (!$teacher) { header('Location: /portal/index.php?msg=unauthorized'); exit; }
    // Teacher may view students in classes where they teach
    $stCheck = $db->prepare(
        'SELECT 1 FROM class_subjects WHERE teacher_id = ? AND class_id = ?'
    );
    $stCheck->execute([$teacher['id'], $student['class_id']]);
    if (!$stCheck->fetch()) { header('Location: /portal/index.php?msg=unauthorized'); exit; }
} elseif ($role === 'vp_main') {
    requirePermission('vp_results');
} elseif ($role === 'student_affairs') {
    requirePermission('sa_students');
} elseif ($role === 'ilc_vp') {
    requirePermission('ilc_results');
}

// ── Fetch assessments + marks grouped by subject ──────────────────
$st = $db->prepare(
    'SELECT a.id AS assessment_id, a.title, a.type, a.max_marks, a.weight, a.date,
            sb.id AS subject_id, sb.name AS subject_name, sb.code AS subject_code,
            m.marks_obtained, m.remarks
     FROM assessments a
     JOIN subjects sb ON a.subject_id = sb.id
     LEFT JOIN marks m ON m.assessment_id = a.id AND m.student_id = ?
     WHERE a.class_id = ?
     ORDER BY sb.name, a.date'
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

// ── Compute per-subject stats & overall ──────────────────────────
$subjects     = [];
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
            $totalObtained += $a['marks_obtained'];
            $totalMax      += $a['max_marks'];
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
        'total_obtained' => $totalObtained,
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

$grandPct = $grandWTotal > 0
    ? round($grandWSum / $grandWTotal, 1)
    : ($grandMax > 0 ? round($grandObtained / $grandMax * 100, 1) : 0);

// ── Attendance ────────────────────────────────────────────────────
$attendance = getStudentAttendanceSummary($studentId);
$attTotal   = array_sum(array_column($attendance, 'total'));
$attPresent = array_sum(array_column($attendance, 'present'));
$attPct     = $attTotal > 0 ? round($attPresent / $attTotal * 100, 1) : 0;

// ── Profile photo ─────────────────────────────────────────────────
$photoUrl = null;
if ($student['photo_status'] === 'approved' && $student['profile_photo']) {
    $photoUrl = url('/portal/uploads/profile-photos/' . rawurlencode($student['profile_photo']));
}

// ── Grade helper functions ────────────────────────────────────────
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
        'A+'    => '#166534',
        'A'     => '#14532d',
        'B'     => '#1d4ed8',
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

$overallGrade = getGradeLetter($grandPct, $grandMax > 0);
$reportDate   = date('d F Y');
$schoolName   = getSetting('school_name', 'BMC Bin Qasim');
$schoolAddr   = getSetting('school_address', '');
$principalName = getSetting('principal_name', 'Lt. Cdr. Abu Bakar');
$base = defined('BASE_URL') ? BASE_URL : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Progress Report — <?= h($student['name']) ?></title>
<link rel="icon" type="image/png" href="<?= $base ?>/assets/bmc-logo.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ── Screen styles ───────────────────────────────────────── */
:root {
  --primary: #1c3054;
  --accent:  #1d4ed8;
}
body {
  font-family: 'Segoe UI', Arial, sans-serif;
  background: #f1f5f9;
  color: #1e293b;
  margin: 0;
  padding: 0;
}
.print-wrap {
  max-width: 900px;
  margin: 0 auto;
  padding: 20px 16px 40px;
}
/* Screen-only toolbar */
.screen-toolbar {
  background: var(--primary);
  color: #fff;
  padding: 10px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 8px rgba(0,0,0,.25);
}
.screen-toolbar a, .screen-toolbar button {
  color: #fff;
  text-decoration: none;
  font-size: 13px;
  background: rgba(255,255,255,.15);
  border: 1px solid rgba(255,255,255,.3);
  border-radius: 6px;
  padding: 5px 14px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: background .15s;
}
.screen-toolbar a:hover, .screen-toolbar button:hover {
  background: rgba(255,255,255,.28);
}
.screen-toolbar .title {
  flex: 1;
  font-weight: 600;
  font-size: 14px;
  min-width: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Report card ─────────────────────────────────────────── */
.report-card {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 2px 12px rgba(0,0,0,.1);
  overflow: hidden;
  margin-top: 16px;
}

/* Header band */
.rpt-header {
  background: var(--primary);
  color: #fff;
  padding: 24px 28px 20px;
  display: flex;
  align-items: center;
  gap: 20px;
}
.rpt-logo {
  width: 64px;
  height: 64px;
  object-fit: contain;
  flex-shrink: 0;
}
.rpt-school-info { flex: 1; min-width: 0; }
.rpt-school-name { font-size: 1.3rem; font-weight: 700; line-height: 1.3; }
.rpt-school-sub  { font-size: .8rem; opacity: .85; margin-top: 2px; }
.rpt-report-title {
  text-align: right;
  flex-shrink: 0;
  font-size: .8rem;
  opacity: .85;
  line-height: 1.6;
}
.rpt-report-title strong { font-size: 1rem; display: block; opacity: 1; }

/* Student info band */
.rpt-student {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 20px;
  padding: 20px 28px;
  border-bottom: 1px solid #e2e8f0;
  align-items: start;
}
.rpt-photo {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid var(--accent);
}
.rpt-photo-placeholder {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: var(--accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
}
.rpt-student-details { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 16px; }
.rpt-student-name { font-size: 1.2rem; font-weight: 700; grid-column: 1 / -1; color: var(--primary); margin-bottom: 4px; }
.rpt-field { font-size: .82rem; color: #475569; }
.rpt-field strong { color: #1e293b; font-weight: 600; }

/* Overall summary band */
.rpt-summary {
  display: flex;
  gap: 16px;
  padding: 16px 28px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  flex-wrap: wrap;
}
.rpt-stat {
  flex: 1;
  min-width: 120px;
  text-align: center;
  padding: 12px;
  background: #fff;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}
.rpt-stat-value { font-size: 1.5rem; font-weight: 700; color: var(--accent); }
.rpt-stat-label { font-size: .72rem; color: #64748b; margin-top: 2px; text-transform: uppercase; letter-spacing: .04em; }

/* Section headings */
.rpt-section-head {
  padding: 14px 28px 8px;
  font-size: .9rem;
  font-weight: 700;
  color: var(--primary);
  letter-spacing: .03em;
  text-transform: uppercase;
  border-bottom: 2px solid var(--accent);
  margin: 0 28px;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Subject tables */
.rpt-subject-block { padding: 16px 28px; border-bottom: 1px solid #f1f5f9; }
.rpt-subject-title {
  font-size: .88rem;
  font-weight: 700;
  color: var(--primary);
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.rpt-table { width: 100%; font-size: .78rem; border-collapse: collapse; }
.rpt-table th {
  background: #f1f5f9;
  color: #475569;
  font-weight: 600;
  padding: 6px 8px;
  text-align: left;
  font-size: .72rem;
  text-transform: uppercase;
  letter-spacing: .04em;
  border-bottom: 1px solid #e2e8f0;
}
.rpt-table td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.rpt-table tfoot td {
  background: #f8fafc;
  font-weight: 700;
  border-top: 2px solid #e2e8f0;
  border-bottom: none;
}
.grade-badge {
  display: inline-block;
  padding: 1px 7px;
  border-radius: 20px;
  font-size: .72rem;
  font-weight: 700;
}

/* Attendance section */
.rpt-att-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px,1fr)); gap: 10px; padding: 16px 28px; }
.rpt-att-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px;
  font-size: .8rem;
}
.rpt-att-subject { font-weight: 700; color: var(--primary); margin-bottom: 6px; font-size: .82rem; }
.rpt-att-row { display: flex; justify-content: space-between; color: #64748b; margin-bottom: 2px; }
.rpt-att-bar { height: 4px; background: #e2e8f0; border-radius: 2px; margin-top: 8px; overflow: hidden; }
.rpt-att-bar-fill { height: 100%; border-radius: 2px; }

/* Signature row */
.rpt-signature {
  display: flex;
  justify-content: space-between;
  padding: 24px 28px 20px;
  gap: 20px;
  flex-wrap: wrap;
}
.rpt-sig-box {
  flex: 1;
  min-width: 140px;
  text-align: center;
  border-top: 1px solid #94a3b8;
  padding-top: 8px;
  font-size: .78rem;
  color: #475569;
}
.rpt-sig-title { font-weight: 600; color: var(--primary); font-size: .82rem; }

/* Footer */
.rpt-footer {
  background: var(--primary);
  color: rgba(255,255,255,.7);
  font-size: .72rem;
  text-align: center;
  padding: 10px;
}

/* ── Print styles ────────────────────────────────────────── */
@media print {
  @page { size: A4; margin: 12mm 14mm; }
  * { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; print-color-adjust: exact !important; }
  body { background: #fff !important; }
  .screen-toolbar { display: none !important; }
  .print-wrap { padding: 0; max-width: 100%; }
  .report-card { box-shadow: none; border-radius: 0; margin: 0; }
  .rpt-subject-block { page-break-inside: avoid; }
  .rpt-att-grid { page-break-inside: avoid; }
  .rpt-signature { page-break-inside: avoid; }
}

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 600px) {
  .rpt-header { flex-direction: column; text-align: center; padding: 16px; }
  .rpt-report-title { text-align: center; }
  .rpt-student { grid-template-columns: 1fr; text-align: center; padding: 16px; }
  .rpt-photo, .rpt-photo-placeholder { margin: 0 auto; }
  .rpt-student-details { grid-template-columns: 1fr; }
  .rpt-student-name { text-align: center; }
  .rpt-summary { padding: 12px 16px; }
  .rpt-section-head { margin: 0 16px; padding: 10px 0 6px; }
  .rpt-subject-block { padding: 12px 16px; }
  .rpt-att-grid { padding: 12px 16px; }
  .rpt-signature { padding: 16px; }
  .screen-toolbar .title { display: none; }
}
</style>
</head>
<body>

<!-- Screen toolbar (hidden on print) -->
<div class="screen-toolbar">
  <?php
  $backUrl = match($role) {
      'student'         => '/portal/student/results.php',
      'teacher'         => '/portal/teacher/marks.php',
      'admin'           => '/portal/admin/users.php',
      'vp_main'         => '/portal/vp/students.php',
      'student_affairs' => '/portal/student-affairs/students.php',
      'ilc_vp'          => '/portal/ilc/students.php',
      default           => '/portal/index.php',
  };
  ?>
  <a href="<?= $base . $backUrl ?>">
    <i class="fas fa-arrow-left"></i> Back
  </a>
  <span class="title">Progress Report — <?= h($student['name']) ?></span>
  <button onclick="window.print()">
    <i class="fas fa-print"></i> Print / Save PDF
  </button>
</div>

<div class="print-wrap">
<div class="report-card">

  <!-- ── Report header ────────────────────────────────────── -->
  <div class="rpt-header">
    <img class="rpt-logo" src="<?= $base ?>/assets/bmc-logo.png" alt="BMC Logo" onerror="this.style.display='none'">
    <div class="rpt-school-info">
      <div class="rpt-school-name"><?= h($schoolName) ?></div>
      <?php if ($schoolAddr): ?>
      <div class="rpt-school-sub"><?= h($schoolAddr) ?></div>
      <?php endif; ?>
      <div class="rpt-school-sub" style="margin-top:4px;font-size:.72rem;opacity:.7">STUDENT PROGRESS REPORT</div>
    </div>
    <div class="rpt-report-title">
      <strong>Progress Report</strong>
      Generated: <?= $reportDate ?><br>
      Academic Year: <?= date('Y') ?>–<?= date('Y')+1 ?>
    </div>
  </div>

  <!-- ── Student info ─────────────────────────────────────── -->
  <div class="rpt-student">
    <?php if ($photoUrl): ?>
    <img class="rpt-photo" src="<?= h($photoUrl) ?>" alt="Student Photo">
    <?php else: ?>
    <div class="rpt-photo-placeholder"><?= h(mb_strtoupper(mb_substr($student['name'], 0, 2))) ?></div>
    <?php endif; ?>
    <div>
      <div class="rpt-student-name"><?= h($student['name']) ?></div>
      <div class="rpt-student-details">
        <div class="rpt-field"><strong>Roll No:</strong> <?= h($student['roll_no'] ?? '—') ?></div>
        <div class="rpt-field"><strong>Class:</strong> <?= h($student['class_name'] ?? '—') ?></div>
        <div class="rpt-field"><strong>Login ID:</strong> <?= h($student['login_id'] ?? '—') ?></div>
        <?php if (!empty($student['father_name'])): ?>
        <div class="rpt-field"><strong>Father:</strong> <?= h($student['father_name']) ?></div>
        <?php endif; ?>
        <?php if (!empty($student['house_name'])): ?>
        <div class="rpt-field">
          <strong>House:</strong>
          <span style="color:<?= h($student['house_color'] ?? '#666') ?>;font-weight:600"><?= h($student['house_name']) ?></span>
        </div>
        <?php endif; ?>
        <div class="rpt-field"><strong>Email:</strong> <?= h($student['email'] ?? '—') ?></div>
      </div>
    </div>
  </div>

  <!-- ── Overall summary ──────────────────────────────────── -->
  <?php if ($grandMax > 0): ?>
  <div class="rpt-summary">
    <div class="rpt-stat">
      <div class="rpt-stat-value"><?= $grandPct ?>%</div>
      <div class="rpt-stat-label">Overall Score</div>
    </div>
    <div class="rpt-stat">
      <div class="rpt-stat-value" style="color:<?= gradeColor($overallGrade) ?>"><?= $overallGrade ?></div>
      <div class="rpt-stat-label">Overall Grade</div>
    </div>
    <div class="rpt-stat">
      <div class="rpt-stat-value"><?= $grandObtained ?> / <?= $grandMax ?></div>
      <div class="rpt-stat-label">Total Marks</div>
    </div>
    <div class="rpt-stat">
      <div class="rpt-stat-value" style="color:<?= $attPct >= 75 ? '#15803d' : '#dc2626' ?>"><?= $attPct ?>%</div>
      <div class="rpt-stat-label">Attendance</div>
    </div>
    <div class="rpt-stat">
      <div class="rpt-stat-value"><?= count($subjects) ?></div>
      <div class="rpt-stat-label">Subjects</div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Subject-wise marks ────────────────────────────────── -->
  <div class="rpt-section-head" style="margin-top:16px">
    <i class="fas fa-book-open"></i> Subject-wise Performance
  </div>

  <?php if (empty($subjects)): ?>
  <div style="padding:20px 28px;color:#64748b;font-size:.85rem">No assessment data available yet.</div>
  <?php else: foreach ($subjects as $sub):
    $hasMarks = $sub['total_max'] > 0;
    $g = $sub['grade'];
  ?>
  <div class="rpt-subject-block">
    <div class="rpt-subject-title">
      <i class="fas fa-circle" style="font-size:.4rem;color:var(--accent)"></i>
      <?= h($sub['name']) ?>
      <span style="font-size:.72rem;color:#94a3b8;font-weight:400">(<?= h($sub['code']) ?>)</span>
      <?php if ($hasMarks): ?>
      <span class="grade-badge ms-auto" style="background:<?= gradeBg($g) ?>;color:<?= gradeColor($g) ?>"><?= $g ?></span>
      <span style="font-size:.78rem;color:var(--accent);font-weight:600"><?= $sub['overall_pct'] ?>%</span>
      <?php endif; ?>
    </div>
    <div style="overflow-x:auto">
    <table class="rpt-table">
      <thead>
        <tr>
          <th>Assessment</th>
          <th>Type</th>
          <th>Date</th>
          <th style="text-align:right">Max</th>
          <th style="text-align:right">Obtained</th>
          <th style="text-align:right">%</th>
          <th>Grade</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sub['assessments'] as $a):
          $hasMark = $a['marks_obtained'] !== null;
          $pct     = ($hasMark && $a['max_marks'] > 0) ? round($a['marks_obtained'] / $a['max_marks'] * 100, 1) : 0;
          $ag      = $hasMark ? getGradeLetter($pct, true) : '—';
        ?>
        <tr>
          <td><?= h($a['title']) ?></td>
          <td style="white-space:nowrap">
            <?php
              $typeColors = [
                'Quiz'       => 'bg:#dbeafe;color:#1d4ed8',
                'Assignment' => 'bg:#f1f5f9;color:#475569',
                'Mid Term'   => 'bg:#fef9c3;color:#92400e',
                'Final Term' => 'bg:#fee2e2;color:#991b1b',
                'Practical'  => 'bg:#dcfce7;color:#166534',
              ];
              $tc = $typeColors[$a['type']] ?? 'bg:#f1f5f9;color:#475569';
              [$bg, $clr] = array_map('trim', explode(';', str_replace(['bg:', 'color:'], '', $tc)));
            ?>
            <span style="background:<?= $bg ?>;color:<?= $clr ?>;padding:1px 7px;border-radius:20px;font-size:.7rem;font-weight:600"><?= h($a['type']) ?></span>
          </td>
          <td style="white-space:nowrap"><?= fDate($a['date']) ?></td>
          <td style="text-align:right"><?= h($a['max_marks']) ?></td>
          <td style="text-align:right;font-weight:600"><?= $hasMark ? h($a['marks_obtained']) : '<span style="color:#94a3b8">—</span>' ?></td>
          <td style="text-align:right;color:var(--accent);font-weight:600"><?= $hasMark ? $pct.'%' : '<span style="color:#94a3b8">—</span>' ?></td>
          <td>
            <?php if ($hasMark): ?>
            <span class="grade-badge" style="background:<?= gradeBg($ag) ?>;color:<?= gradeColor($ag) ?>"><?= $ag ?></span>
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
          <td colspan="3">Subject Total</td>
          <td style="text-align:right"><?= $sub['total_max'] ?></td>
          <td style="text-align:right"><?= $sub['total_obtained'] ?></td>
          <td style="text-align:right;color:var(--accent)"><?= $sub['overall_pct'] ?>%</td>
          <td>
            <span class="grade-badge" style="background:<?= gradeBg($g) ?>;color:<?= gradeColor($g) ?>"><?= $g ?></span>
          </td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
    </div>
  </div>
  <?php endforeach; endif; ?>

  <!-- ── Attendance ────────────────────────────────────────── -->
  <?php if (!empty($attendance)): ?>
  <div class="rpt-section-head" style="margin-top:8px">
    <i class="fas fa-calendar-check"></i> Attendance Summary
  </div>
  <div class="rpt-att-grid">
    <?php foreach ($attendance as $att):
      $attPctSub = $att['total'] > 0 ? round($att['present'] / $att['total'] * 100) : 0;
      $barColor  = $attPctSub >= 75 ? '#22c55e' : ($attPctSub >= 60 ? '#f59e0b' : '#ef4444');
    ?>
    <div class="rpt-att-card">
      <div class="rpt-att-subject"><?= h($att['subject']) ?> <span style="font-size:.7rem;color:#94a3b8">(<?= h($att['code']) ?>)</span></div>
      <div class="rpt-att-row"><span>Present</span><strong style="color:#16a34a"><?= $att['present'] ?></strong></div>
      <div class="rpt-att-row"><span>Absent</span><strong style="color:#dc2626"><?= $att['absent'] ?></strong></div>
      <div class="rpt-att-row"><span>Leave</span><strong style="color:#d97706"><?= $att['leave'] ?></strong></div>
      <div class="rpt-att-row" style="border-top:1px solid #e2e8f0;margin-top:4px;padding-top:4px">
        <span>Total</span><strong><?= $att['total'] ?></strong>
      </div>
      <div class="rpt-att-bar">
        <div class="rpt-att-bar-fill" style="width:<?= $attPctSub ?>%;background:<?= $barColor ?>"></div>
      </div>
      <div style="font-size:.7rem;color:#64748b;text-align:right;margin-top:2px"><?= $attPctSub ?>% present</div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ── Remarks / Signature ──────────────────────────────── -->
  <div class="rpt-section-head" style="margin-top:8px">
    <i class="fas fa-signature"></i> Signatures
  </div>
  <div class="rpt-signature">
    <div class="rpt-sig-box">
      <div style="height:40px"></div>
      <div class="rpt-sig-title">Class Teacher</div>
      <div>Signature &amp; Date</div>
    </div>
    <div class="rpt-sig-box">
      <div style="height:40px"></div>
      <div class="rpt-sig-title"><?= h($principalName) ?></div>
      <div>Principal</div>
    </div>
    <div class="rpt-sig-box">
      <div style="height:40px"></div>
      <div class="rpt-sig-title">Parent / Guardian</div>
      <div>Signature &amp; Date</div>
    </div>
  </div>

  <div class="rpt-footer">
    Generated on <?= $reportDate ?> &nbsp;·&nbsp; <?= h($schoolName) ?> Portal &nbsp;·&nbsp; Confidential
  </div>

</div><!-- /report-card -->
</div><!-- /print-wrap -->

<script>
// Keyboard shortcut: Ctrl+P already triggers print natively
// but add for completeness
document.addEventListener('keydown', function(e){
  if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
    // let native print handle it
  }
});
</script>
</body>
</html>

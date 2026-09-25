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

// ── Build per-subject assessment breakdown strings ────────────
foreach ($subjects as $sid => &$sub) {
    $parts = [];
    foreach ($sub['assessments'] as $a) {
        $obtained = $a['marks_obtained'] !== null ? $a['marks_obtained'] : '—';
        $parts[]  = h($a['assessment_name']) . ': ' . $obtained . '/' . $a['max_marks'];
    }
    $sub['breakdown'] = implode('<span class="rc-sep">·</span>', $parts);
}
unset($sub);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Report Card — <?= h($student['name']) ?></title>
<link rel="icon" type="image/png" href="<?= $base ?>/assets/bmc-logo.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ── Variables ────────────────────────────────────────────── */
:root { --navy:#0f2456; --gold:#b8860b; --accent:#1d4ed8; }

/* ── Screen chrome ───────────────────────────────────────── */
body { font-family:'Segoe UI',Arial,sans-serif; background:#e2e8f0; color:#1e293b; margin:0; padding:0; }
.toolbar {
  background:var(--navy); color:#fff; padding:9px 16px;
  display:flex; align-items:center; gap:10px; flex-wrap:wrap;
  position:sticky; top:0; z-index:200; box-shadow:0 2px 8px rgba(0,0,0,.3);
}
.toolbar a, .toolbar button {
  color:#fff; text-decoration:none; font-size:12.5px;
  background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.28);
  border-radius:5px; padding:4px 13px; cursor:pointer;
  display:inline-flex; align-items:center; gap:5px; transition:background .15s;
}
.toolbar a:hover,.toolbar button:hover { background:rgba(255,255,255,.26); }
.toolbar .ttl { flex:1; font-weight:600; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0; }
.tbadge { background:#fbbf24; color:#78350f; font-size:10px; font-weight:700; padding:2px 8px; border-radius:20px; letter-spacing:.04em; border:none; }

/* ── Page wrapper ────────────────────────────────────────── */
.pw { max-width:820px; margin:20px auto; padding:0 14px 36px; }

/* ── Card shell ──────────────────────────────────────────── */
.rc { background:#fff; border:1px solid #cbd5e1; box-shadow:0 3px 16px rgba(0,0,0,.1); }

/* ── Letterhead ──────────────────────────────────────────── */
.rc-lh {
  border-bottom:4px double var(--gold);
  padding:14px 20px 12px;
  display:flex; align-items:center; gap:14px;
}
.rc-lh img { width:62px; height:62px; object-fit:contain; flex-shrink:0; }
.rc-lh-init {
  width:62px; height:62px; border-radius:50%;
  background:var(--navy); color:#fff;
  display:flex; align-items:center; justify-content:center;
  font-size:1.3rem; font-weight:700; flex-shrink:0; border:3px solid var(--gold);
}
.rc-lh-info { flex:1; min-width:0; }
.rc-lh-name { font-size:1.2rem; font-weight:800; color:var(--navy); line-height:1.2; }
.rc-lh-sub  { font-size:.74rem; color:#64748b; margin-top:1px; }
.rc-lh-tag  { display:inline-block; background:var(--navy); color:#fff; font-size:.64rem; font-weight:600; padding:2px 9px; border-radius:20px; margin-top:5px; letter-spacing:.05em; }
.rc-lh-meta { text-align:right; flex-shrink:0; font-size:.72rem; color:#475569; line-height:1.65; }
.rc-lh-meta strong { font-size:.9rem; font-weight:800; color:var(--navy); display:block; letter-spacing:.05em; text-transform:uppercase; margin-bottom:1px; }
.rc-lh-meta small  { font-size:.64rem; color:#94a3b8; }

/* ── Title band ──────────────────────────────────────────── */
.rc-band {
  background:var(--navy); color:#fff; text-align:center;
  padding:6px 16px; font-size:.72rem; font-weight:600;
  letter-spacing:.16em; text-transform:uppercase;
}
.rc-band em { color:var(--gold); font-style:normal; margin:0 5px; font-size:.56rem; }

/* ── Student block ───────────────────────────────────────── */
.rc-stu {
  display:grid; grid-template-columns:auto 1fr;
  gap:14px; padding:12px 20px; border-bottom:2px solid #e2e8f0; align-items:start;
}
.rc-stu-photo { width:72px; height:72px; border-radius:4px; object-fit:cover; border:2px solid var(--navy); flex-shrink:0; }
.rc-stu-init  { width:72px; height:72px; border-radius:4px; background:var(--navy); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.6rem; font-weight:700; flex-shrink:0; }
.rc-stu-name  { font-size:1.05rem; font-weight:800; color:var(--navy); grid-column:1/-1; margin-bottom:5px; padding-bottom:5px; border-bottom:1px dashed #cbd5e1; }
.rc-grid3     { display:grid; grid-template-columns:repeat(3,1fr); gap:3px 14px; }
.rc-fld .lbl  { font-size:.64rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:1px; }
.rc-fld       { font-size:.76rem; color:#1e293b; }

/* ── Summary bar ─────────────────────────────────────────── */
.rc-sum { display:grid; grid-template-columns:repeat(5,1fr); border-bottom:2px solid #e2e8f0; }
.rc-sum-cell { text-align:center; padding:10px 6px; border-right:1px solid #e2e8f0; }
.rc-sum-cell:last-child { border-right:none; }
.rc-sum-val  { font-size:1.25rem; font-weight:800; color:var(--accent); line-height:1; }
.rc-sum-lbl  { font-size:.6rem; color:#94a3b8; margin-top:3px; text-transform:uppercase; letter-spacing:.06em; }

/* ── Section heading ─────────────────────────────────────── */
.rc-sh {
  background:#f1f5f9; border-top:1px solid #e2e8f0; border-bottom:2px solid var(--navy);
  padding:5px 20px; font-size:.68rem; font-weight:700; color:var(--navy);
  text-transform:uppercase; letter-spacing:.1em;
  display:flex; align-items:center; gap:7px;
}

/* ── Consolidated subject table ──────────────────────────── */
.rc-tbl {
  width:100%; border-collapse:collapse; font-size:.76rem;
}
.rc-tbl th {
  background:var(--navy); color:#fff; padding:5px 8px;
  font-size:.64rem; font-weight:600; text-transform:uppercase;
  letter-spacing:.05em; white-space:nowrap; text-align:left;
}
.rc-tbl th.r, .rc-tbl td.r { text-align:right; }
.rc-tbl td { padding:6px 8px; border-bottom:1px solid #e8ecf1; vertical-align:top; }
.rc-tbl tbody tr:nth-child(even) td { background:#f8fafc; }
.rc-tbl tfoot td {
  background:#1e3a6e; color:#fff; font-weight:700;
  padding:7px 8px; border-top:2px solid var(--navy); font-size:.78rem;
}
.rc-subj-name { font-weight:700; color:var(--navy); }
.rc-subj-code { font-size:.68rem; color:#94a3b8; font-weight:400; }
.rc-breakdown { font-size:.7rem; color:#374151; line-height:1.7; }
.rc-sep { color:#cbd5e1; margin:0 5px; user-select:none; }
.rc-badge {
  display:inline-block; padding:1px 7px; border-radius:3px;
  font-size:.67rem; font-weight:700; letter-spacing:.03em;
}
.rc-no-data { color:#94a3b8; font-style:italic; }

/* ── Attendance table ────────────────────────────────────── */
.rc-att-tbl { width:100%; border-collapse:collapse; font-size:.75rem; }
.rc-att-tbl th {
  background:#334155; color:#fff; padding:5px 8px;
  font-size:.63rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em;
}
.rc-att-tbl td { padding:5px 8px; border-bottom:1px solid #e8ecf1; }
.rc-att-tbl tbody tr:nth-child(even) td { background:#f8fafc; }
.rc-att-tbl td.pct-cell { font-weight:700; }
.rc-att-bar { height:3px; background:#e2e8f0; border-radius:2px; margin-top:3px; overflow:hidden; }
.rc-att-fill { height:100%; border-radius:2px; }

/* ── Grade legend ────────────────────────────────────────── */
.rc-legend {
  display:flex; gap:7px; flex-wrap:wrap; padding:7px 20px;
  font-size:.67rem; color:#64748b; border-top:1px solid #e2e8f0; align-items:center;
}
.rc-legend .gl { display:flex; align-items:center; gap:3px; }

/* ── Remarks ─────────────────────────────────────────────── */
.rc-rem { display:grid; grid-template-columns:1fr 1fr; border-top:2px solid #e2e8f0; }
.rc-rem-cell { padding:10px 20px; font-size:.74rem; border-right:1px solid #e2e8f0; }
.rc-rem-cell:last-child { border-right:none; }
.rc-rem-lbl { font-size:.64rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#64748b; margin-bottom:5px; }
.rc-rem-line { border-bottom:1px solid #94a3b8; min-height:20px; margin-bottom:4px; }

/* ── Signatures ──────────────────────────────────────────── */
.rc-sig { display:flex; border-top:1px solid #e2e8f0; }
.rc-sig-cell { flex:1; text-align:center; padding:22px 10px 12px; border-right:1px solid #e2e8f0; font-size:.7rem; color:#475569; }
.rc-sig-cell:last-child { border-right:none; }
.rc-sig-line { border-top:1px solid #374151; padding-top:5px; }
.rc-sig-title { font-weight:700; color:var(--navy); font-size:.76rem; }

/* ── Footer ──────────────────────────────────────────────── */
.rc-foot {
  background:var(--navy); color:rgba(255,255,255,.7);
  font-size:.64rem; padding:7px 18px;
  display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px;
}
.rc-foot .vtag { background:#166534; color:#dcfce7; font-size:.62rem; font-weight:600; padding:2px 9px; border-radius:20px; letter-spacing:.06em; }

/* ── Print ───────────────────────────────────────────────── */
@media print {
  @page { size:A4; margin:8mm 10mm; }
  * { -webkit-print-color-adjust:exact!important; color-adjust:exact!important; print-color-adjust:exact!important; }
  body { background:#fff!important; font-size:9.5pt; }
  .toolbar { display:none!important; }
  .pw { padding:0; max-width:100%; margin:0; }
  .rc { border:none; box-shadow:none; }
  .rc-sig    { page-break-inside:avoid; }
  .rc-rem    { page-break-inside:avoid; }
  .rc-legend { page-break-inside:avoid; }
  .rc-att-tbl { page-break-inside:avoid; }
}

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width:600px) {
  .rc-lh     { flex-direction:column; text-align:center; padding:12px; }
  .rc-lh-meta { text-align:center; }
  .rc-stu    { grid-template-columns:1fr; text-align:center; padding:10px; }
  .rc-stu-photo,.rc-stu-init { margin:0 auto; }
  .rc-grid3  { grid-template-columns:1fr 1fr; }
  .rc-sum    { grid-template-columns:repeat(3,1fr); }
  .rc-rem    { grid-template-columns:1fr; }
  .rc-sig    { flex-wrap:wrap; }
  .rc-sig-cell { min-width:50%; }
  .toolbar .ttl { display:none; }
}
</style>
</head>
<body>

<div class="toolbar">
  <a href="<?= $base . $backUrl ?>"><i class="fas fa-arrow-left"></i> Back</a>
  <span class="ttl">Report Card — <?= h($student['name']) ?></span>
  <span class="tbadge">RESTRICTED</span>
  <button onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button>
</div>

<div class="pw">
<div class="rc">

  <!-- Letterhead -->
  <div class="rc-lh">
    <?php if (file_exists(__DIR__ . '/../assets/bmc-logo.png')): ?>
    <img src="<?= $base ?>/assets/bmc-logo.png" alt="Logo"
         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
    <div class="rc-lh-init" style="display:none">BMC</div>
    <?php else: ?><div class="rc-lh-init">BMC</div><?php endif; ?>
    <div class="rc-lh-info">
      <div class="rc-lh-name"><?= h($schoolName) ?></div>
      <?php if ($schoolAddr): ?><div class="rc-lh-sub"><?= h($schoolAddr) ?></div><?php endif; ?>
      <div class="rc-lh-tag"><?= h($campusLabel) ?></div>
    </div>
    <div class="rc-lh-meta">
      <strong>Report Card</strong>
      Session: <?= h($sessionYear) ?><br>
      <?php if ($currentTerm): ?>Term: <?= h($currentTerm) ?><br><?php endif; ?>
      Date: <?= $reportDate ?>
      <small>GR# <?= h($student['gr_no'] ?? '—') ?></small>
    </div>
  </div>

  <!-- Title band -->
  <div class="rc-band">
    Official Student Report Card
    <em>&#9670;</em>
    Academic Session <?= h($sessionYear) ?>
    <em>&#9670;</em>
    <?= h($campusLabel) ?>
  </div>

  <!-- Student details -->
  <div class="rc-stu">
    <?php if ($photoUrl): ?>
    <img class="rc-stu-photo" src="<?= h($photoUrl) ?>" alt="Photo">
    <?php else: ?>
    <div class="rc-stu-init"><?= h(mb_strtoupper(mb_substr($student['name'], 0, 2))) ?></div>
    <?php endif; ?>
    <div>
      <div class="rc-stu-name"><?= h($student['name']) ?></div>
      <div class="rc-grid3">
        <div class="rc-fld"><span class="lbl">GR Number / Student ID</span><?= h($student['gr_no'] ?? '—') ?></div>
        <div class="rc-fld"><span class="lbl">Roll No.</span><?= h($student['roll_no'] ?? '—') ?></div>
        <div class="rc-fld"><span class="lbl">Class / Section</span><?= h($student['class_name'] ?? '—') ?></div>
        <div class="rc-fld"><span class="lbl">Academic Session</span><?= h($sessionYear) ?></div>
        <?php if (!empty($student['father_name'])): ?>
        <div class="rc-fld"><span class="lbl">Father's Name</span><?= h($student['father_name']) ?></div>
        <?php elseif (!empty($student['parent_name'])): ?>
        <div class="rc-fld"><span class="lbl">Parent / Guardian</span><?= h($student['parent_name']) ?></div>
        <?php endif; ?>
        <?php if (!empty($student['dob'])): ?>
        <div class="rc-fld"><span class="lbl">Date of Birth</span><?= fDate($student['dob']) ?></div>
        <?php endif; ?>
        <?php if (!empty($student['gender'])): ?>
        <div class="rc-fld"><span class="lbl">Gender</span><?= ucfirst(h($student['gender'])) ?></div>
        <?php endif; ?>
        <?php if (!empty($student['house_name'])): ?>
        <div class="rc-fld">
          <span class="lbl">House</span>
          <span style="color:<?= h($student['house_color'] ?? '#0f2456') ?>;font-weight:700"><?= h($student['house_name']) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Summary bar -->
  <?php if ($grandMax > 0): ?>
  <div class="rc-sum">
    <div class="rc-sum-cell">
      <div class="rc-sum-val"><?= $grandPct ?>%</div>
      <div class="rc-sum-lbl">Overall %</div>
    </div>
    <div class="rc-sum-cell">
      <div class="rc-sum-val" style="color:<?= gradeColor($overallGrade) ?>;background:<?= gradeBg($overallGrade) ?>;border-radius:4px;padding:1px 10px;display:inline-block">
        <?= $overallGrade ?>
      </div>
      <div class="rc-sum-lbl">Final Grade</div>
    </div>
    <div class="rc-sum-cell">
      <div class="rc-sum-val"><?= round($grandObtained, 0) ?>&nbsp;/&nbsp;<?= round($grandMax, 0) ?></div>
      <div class="rc-sum-lbl">Marks (Obt / Total)</div>
    </div>
    <div class="rc-sum-cell">
      <div class="rc-sum-val" style="color:<?= $attPct >= 75 ? '#15803d' : '#dc2626' ?>"><?= $attPct ?>%</div>
      <div class="rc-sum-lbl">Attendance</div>
    </div>
    <div class="rc-sum-cell">
      <div class="rc-sum-val"><?= count($subjects) ?></div>
      <div class="rc-sum-lbl">Subjects</div>
    </div>
  </div>
  <?php else: ?>
  <div style="padding:12px 20px;background:#fffbeb;border-bottom:1px solid #fde68a;font-size:.8rem;color:#92400e">
    <i class="fas fa-info-circle me-1"></i> No assessment data recorded for this student yet.
  </div>
  <?php endif; ?>

  <!-- Consolidated subject results table -->
  <div class="rc-sh">
    <i class="fas fa-book-open" style="color:var(--accent)"></i>
    Academic Performance — All Subjects
  </div>

  <?php if (empty($subjects)): ?>
  <div style="padding:14px 20px;color:#64748b;font-size:.82rem">No assessments found for this student's class.</div>
  <?php else: ?>
  <table class="rc-tbl">
    <thead>
      <tr>
        <th style="width:4%">#</th>
        <th style="width:18%">Subject</th>
        <th>Assessment Breakdown</th>
        <th class="r" style="width:8%">Obtained</th>
        <th class="r" style="width:7%">Total</th>
        <th class="r" style="width:7%">%</th>
        <th style="width:7%">Grade</th>
      </tr>
    </thead>
    <tbody>
      <?php $rowNum = 1; foreach ($subjects as $sub):
        $hasMarks = $sub['total_max'] > 0;
        $g        = $sub['grade'];
      ?>
      <tr>
        <td style="color:#94a3b8;font-size:.68rem"><?= $rowNum++ ?></td>
        <td>
          <span class="rc-subj-name"><?= h($sub['name']) ?></span><br>
          <span class="rc-subj-code"><?= h($sub['code']) ?></span>
        </td>
        <td>
          <div class="rc-breakdown">
            <?php if (empty($sub['assessments'])): ?>
            <span class="rc-no-data">No assessments</span>
            <?php else: ?>
            <?= $sub['breakdown'] ?>
            <?php endif; ?>
          </div>
        </td>
        <td class="r" style="font-weight:700">
          <?= $hasMarks ? round($sub['total_obtained'], 1) : '<span class="rc-no-data">—</span>' ?>
        </td>
        <td class="r" style="color:#64748b">
          <?= $hasMarks ? round($sub['total_max'], 0) : '—' ?>
        </td>
        <td class="r" style="color:var(--accent);font-weight:700">
          <?= $hasMarks ? $sub['overall_pct'].'%' : '—' ?>
        </td>
        <td>
          <?php if ($hasMarks): ?>
          <span class="rc-badge" style="background:<?= gradeBg($g) ?>;color:<?= gradeColor($g) ?>"><?= $g ?></span>
          <?php else: ?><span class="rc-no-data">—</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="3" style="letter-spacing:.05em">
          OVERALL RESULT
          <?php if ($grandWTotal > 0): ?>
          <span style="font-size:.64rem;opacity:.75;font-weight:400;margin-left:6px">* weighted by assessment weightage</span>
          <?php endif; ?>
        </td>
        <td class="r"><?= round($grandObtained, 0) ?></td>
        <td class="r"><?= round($grandMax, 0) ?></td>
        <td class="r"><?= $grandPct ?>%</td>
        <td>
          <span class="rc-badge" style="background:<?= gradeBg($overallGrade) ?>;color:<?= gradeColor($overallGrade) ?>;font-size:.75rem">
            <?= $overallGrade ?>
          </span>
        </td>
      </tr>
    </tfoot>
  </table>
  <?php endif; ?>

  <!-- Attendance table -->
  <?php if (!empty($attendance)): ?>
  <div class="rc-sh" style="margin-top:0">
    <i class="fas fa-calendar-check" style="color:#16a34a"></i>
    Attendance Summary
  </div>
  <table class="rc-att-tbl">
    <thead>
      <tr>
        <th>Subject</th>
        <th style="text-align:right">Present</th>
        <th style="text-align:right">Absent</th>
        <th style="text-align:right">Leave</th>
        <th style="text-align:right">Total</th>
        <th style="text-align:left;width:110px">Attendance %</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($attendance as $att):
        $subPct   = $att['total'] > 0 ? round($att['present'] / $att['total'] * 100) : 0;
        $barColor = $subPct >= 75 ? '#16a34a' : ($subPct >= 60 ? '#d97706' : '#dc2626');
      ?>
      <tr>
        <td>
          <span style="font-weight:600"><?= h($att['subject']) ?></span>
          <span style="font-size:.66rem;color:#94a3b8;margin-left:4px">(<?= h($att['code']) ?>)</span>
        </td>
        <td style="text-align:right;color:#16a34a;font-weight:600"><?= $att['present'] ?></td>
        <td style="text-align:right;color:#dc2626;font-weight:600"><?= $att['absent'] ?></td>
        <td style="text-align:right;color:#d97706;font-weight:600"><?= $att['leave'] ?></td>
        <td style="text-align:right;font-weight:600"><?= $att['total'] ?></td>
        <td class="pct-cell">
          <span style="color:<?= $barColor ?>"><?= $subPct ?>%</span>
          <div class="rc-att-bar"><div class="rc-att-fill" style="width:<?= $subPct ?>%;background:<?= $barColor ?>"></div></div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- Grade legend -->
  <div class="rc-legend">
    <strong style="color:#1e293b;margin-right:3px">Grade Scale:</strong>
    <?php foreach (['A+'=>'≥90%','A'=>'≥80%','B'=>'≥70%','C'=>'≥60%','D'=>'≥50%','F'=>'<50%'] as $gr => $rng): ?>
    <span class="gl">
      <span class="rc-badge" style="background:<?= gradeBg($gr) ?>;color:<?= gradeColor($gr) ?>"><?= $gr ?></span>
      <?= $rng ?>
    </span>
    <?php endforeach; ?>
  </div>

  <!-- Remarks -->
  <div class="rc-rem">
    <div class="rc-rem-cell">
      <div class="rc-rem-lbl">Class Teacher Remarks</div>
      <div class="rc-rem-line"></div>
      <div class="rc-rem-line" style="margin-top:5px"></div>
    </div>
    <div class="rc-rem-cell">
      <div class="rc-rem-lbl">Principal Remarks</div>
      <div class="rc-rem-line"></div>
      <div class="rc-rem-line" style="margin-top:5px"></div>
    </div>
  </div>

  <!-- Signatures -->
  <div class="rc-sig">
    <div class="rc-sig-cell">
      <div style="height:30px"></div>
      <div class="rc-sig-line">
        <div class="rc-sig-title">Class Teacher</div>
        <div>Signature &amp; Date</div>
      </div>
    </div>
    <div class="rc-sig-cell">
      <div style="height:30px"></div>
      <div class="rc-sig-line">
        <div class="rc-sig-title">Head of Department</div>
        <div>Signature &amp; Date</div>
      </div>
    </div>
    <div class="rc-sig-cell">
      <div style="height:30px"></div>
      <div class="rc-sig-line">
        <div class="rc-sig-title"><?= h($principalName) ?></div>
        <div>Principal / Vice Principal</div>
      </div>
    </div>
    <div class="rc-sig-cell">
      <div style="height:30px"></div>
      <div class="rc-sig-line">
        <div class="rc-sig-title">Parent / Guardian</div>
        <div>Signature &amp; Date</div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="rc-foot">
    <span><?= h($schoolName) ?> &nbsp;·&nbsp; <?= h($campusLabel) ?></span>
    <span class="vtag">OFFICIAL DOCUMENT</span>
    <span>Generated: <?= $reportDate ?> &nbsp;·&nbsp; Confidential — Authorised Use Only</span>
  </div>

</div><!-- /rc -->
</div><!-- /pw -->
</body>
</html>

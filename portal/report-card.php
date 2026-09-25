<?php
/**
 * Official Student Report Card
 *
 * Access:
 *  student         – own report card only (no student_id param needed)
 *  admin           – any student
 *  vp_main         – any student (vp_results perm)
 *  student_affairs – any student (sa_students perm)
 *  wing_head       – any student (wh_students perm)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$user = requireAuth('admin', 'vp_main', 'student_affairs', 'student', 'wing_head');
$db   = getDB();
$role = $user['role'];

if ($role === 'vp_main')         requirePermission('vp_results');
elseif ($role === 'student_affairs') requirePermission('sa_students');
elseif ($role === 'wing_head')   requirePermission('wh_students');

if ($role === 'student') {
    $me = getStudentByUserId($user['id']);
    if (!$me) { header('Location: /portal/student/dashboard.php'); exit; }
    $studentId = (int)$me['id'];
} else {
    $studentId = (int)($_GET['student_id'] ?? 0);
    if (!$studentId) { header('Location: /portal/index.php?msg=unauthorized'); exit; }
}

// ── Fetch student record ──────────────────────────────────
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
if (!$student) { header('Location: /portal/index.php?msg=unauthorized'); exit; }

// ── Fetch assessments + marks grouped by subject ──────────
$st = $db->prepare(
    'SELECT a.id AS assessment_id, a.name AS assessment_name, a.type,
            a.max_marks, a.weight, a.date,
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

// ── Grade helpers ─────────────────────────────────────────
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
        'A+' => '#14532d', 'A' => '#166534', 'B' => '#1e40af',
        'C'  => '#92400e', 'D' => '#7c2d12', 'F' => '#991b1b',
        default => '#374151',
    };
}
function gradeBg(string $g): string {
    return match($g) {
        'A+' => '#dcfce7', 'A' => '#d1fae5', 'B' => '#dbeafe',
        'C'  => '#fef3c7', 'D' => '#ffedd5', 'F' => '#fee2e2',
        default => '#f3f4f6',
    };
}
function gradeText(string $g): string {
    return match($g) {
        'A+' => 'Outstanding', 'A' => 'Excellent',  'B' => 'Very Good',
        'C'  => 'Good',        'D' => 'Satisfactory', 'F' => 'Fail',
        default => '',
    };
}

// ── Compute per-subject stats & overall ───────────────────
$subjects      = [];
$grandObtained = 0;
$grandMax      = 0;
$grandWSum     = 0;
$grandWTotal   = 0;

foreach ($bySubject as $sid => $sub) {
    $totalObtained = 0; $totalMax = 0; $weightedSum = 0; $totalWeight = 0;
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
        if ($totalWeight > 0) { $grandWSum += $weightedSum; $grandWTotal += $totalWeight; }
    }
}

$grandPct = $grandWTotal > 0
    ? round($grandWSum / $grandWTotal, 1)
    : ($grandMax > 0 ? round($grandObtained / $grandMax * 100, 1) : 0);

// ── Attendance ────────────────────────────────────────────
$attendance = getStudentAttendanceSummary($studentId);
$attTotal   = array_sum(array_column($attendance, 'total'));
$attPresent = array_sum(array_column($attendance, 'present'));
$attPct     = $attTotal > 0 ? round($attPresent / $attTotal * 100, 1) : 0;

// ── Profile photo ─────────────────────────────────────────
$photoUrl = null;
if ($student['photo_status'] === 'approved' && $student['profile_photo']) {
    $photoUrl = url('/portal/uploads/profile-photos/' . rawurlencode($student['profile_photo']));
}

// ── Settings ──────────────────────────────────────────────
$overallGrade  = getGradeLetter($grandPct, $grandMax > 0);
$reportDate    = date('d F Y');
$schoolName    = getSetting('school_name', 'BMC Bin Qasim');
$schoolAddr    = getSetting('school_address', '');
$principalName = getSetting('principal_name', 'Lt. Cdr. Abu Bakar');
$sessionYear   = getSetting('session_year', date('Y') . '–' . (date('Y') + 1));
$currentTerm   = getSetting('current_term', '');
$base          = defined('BASE_URL') ? BASE_URL : '';

$campusLabel = match($student['class_wing'] ?? 'main') {
    'montessori' => 'Montessori Campus',
    'ilc'        => 'ILC Campus',
    default      => 'Main Campus',
};

$backUrl = match($role) {
    'admin'           => '/portal/admin/users.php',
    'vp_main'         => '/portal/vp/students.php',
    'student_affairs' => '/portal/student-affairs/results.php',
    'wing_head'       => '/portal/wing-head/students.php',
    'student'         => '/portal/student/results.php',
    default           => '/portal/index.php',
};

// ── Build assessment chips ────────────────────────────────
foreach ($subjects as $sid => &$sub) {
    $chips = [];
    foreach ($sub['assessments'] as $a) {
        $val = $a['marks_obtained'] !== null ? (float)$a['marks_obtained'] : null;
        $disp = $val !== null
            ? ($val == floor($val) ? (int)$val : number_format($val, 1))
            : '—';
        $nil = $val === null;
        $chips[] =
            '<span class="asm'.($nil ? ' asm-nil' : '').'">' .
            '<span class="asm-n">'.h($a['assessment_name']).'</span>' .
            '<span class="asm-s">'.$disp.'<span class="asm-d">/'.(int)$a['max_marks'].'</span></span>' .
            '</span>';
    }
    $sub['chips'] = implode('', $chips);
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
/* ── Variables ──────────────────────────────────────────── */
:root {
  --navy:    #0f2456;
  --navy2:   #1a3a6b;
  --gold:    #b8860b;
  --gold2:   #d4a820;
  --gold-lt: #fdf8e1;
  --gold-bd: #e8d5a0;
  --ink:     #1e293b;
  --muted:   #64748b;
  --subtle:  #94a3b8;
  --light:   #f8fafc;
  --border:  #dde3ec;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, Helvetica, sans-serif; background: #c8d3e4; color: var(--ink); min-height: 100vh; }

/* ── Toolbar ────────────────────────────────────────────── */
.toolbar {
  background: var(--navy); color: #fff;
  padding: 10px 18px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  position: sticky; top: 0; z-index: 300; box-shadow: 0 2px 10px rgba(0,0,0,.35);
}
.toolbar a, .toolbar button {
  color: #fff; text-decoration: none; font-size: 12.5px;
  background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.24);
  border-radius: 5px; padding: 5px 14px; cursor: pointer;
  display: inline-flex; align-items: center; gap: 6px; transition: background .15s; white-space: nowrap;
}
.toolbar a:hover, .toolbar button:hover { background: rgba(255,255,255,.22); }
.toolbar .ttl { flex: 1; font-weight: 600; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
.tb-badge { background: #b45309; color: #fef3c7; font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 20px; letter-spacing: .06em; border: none; }

/* ── Page ───────────────────────────────────────────────── */
.rc-page { max-width: 860px; margin: 22px auto; padding: 0 14px 40px; }

/* ── Paper ──────────────────────────────────────────────── */
.rc-paper {
  background: #fff;
  box-shadow: 0 6px 32px rgba(15,36,86,.2), 0 1px 4px rgba(15,36,86,.1);
  position: relative; overflow: hidden;
}
.rc-paper::before {
  content: 'BMC';
  position: absolute; top: 50%; left: 50%;
  transform: translate(-50%, -50%) rotate(-25deg);
  font-size: 240px; font-weight: 900;
  color: rgba(15,36,86,.028);
  pointer-events: none; user-select: none;
  letter-spacing: .08em; z-index: 0;
}

/* ── Stripe rules ───────────────────────────────────────── */
.stripe-top, .stripe-bot { display: flex; flex-direction: column; }
.s-gold { height: 2px; background: var(--gold); }
.s-navy { height: 5px; background: var(--navy); }
.gold-rule { height: 4px; background: linear-gradient(90deg, var(--navy) 0%, var(--gold) 35%, var(--gold2) 50%, var(--gold) 65%, var(--navy) 100%); }
.gold-rule-sm { height: 2px; background: linear-gradient(90deg, transparent, var(--gold) 25%, var(--gold2) 50%, var(--gold) 75%, transparent); }

/* ── Letterhead ─────────────────────────────────────────── */
.rc-head {
  padding: 16px 24px 14px;
  display: grid; grid-template-columns: 76px 1fr auto;
  gap: 0 16px; align-items: center;
  border-bottom: 1px solid var(--border);
  position: relative; z-index: 1;
}
.rc-head-logo img { width: 76px; height: 76px; object-fit: contain; display: block; }
.rc-head-init {
  width: 76px; height: 76px; border-radius: 50%;
  background: var(--navy); color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; font-weight: 800; border: 3px solid var(--gold);
}
.rc-school-name {
  font-size: 1.3rem; font-weight: 800; color: var(--navy);
  text-transform: uppercase; letter-spacing: .03em; line-height: 1.1;
}
.rc-school-addr { font-size: .72rem; color: var(--muted); margin-top: 3px; line-height: 1.5; }
.rc-campus-pill {
  display: inline-block; background: var(--navy); color: #fff;
  font-size: .62rem; font-weight: 600; padding: 2px 12px;
  border-radius: 20px; letter-spacing: .07em; text-transform: uppercase; margin-top: 6px;
}
.rc-head-meta { text-align: right; font-size: .71rem; color: var(--muted); line-height: 1.75; white-space: nowrap; }
.rc-head-meta .doc-lbl {
  display: block; font-size: .82rem; font-weight: 800;
  color: var(--navy); text-transform: uppercase; letter-spacing: .07em; margin-bottom: 2px;
}
.rc-head-grno {
  display: inline-block; background: var(--gold-lt); border: 1px solid var(--gold-bd);
  color: #78350f; font-size: .67rem; font-weight: 700;
  padding: 2px 9px; border-radius: 3px; letter-spacing: .04em; margin-top: 3px;
}

/* ── Title band ─────────────────────────────────────────── */
.rc-title-band {
  background: var(--navy); color: #fff;
  text-align: center; padding: 9px 20px;
  position: relative; z-index: 1;
}
.rc-title-main {
  font-size: .88rem; font-weight: 700;
  letter-spacing: .2em; text-transform: uppercase;
}
.rc-title-sub { font-size: .64rem; color: rgba(255,255,255,.65); letter-spacing: .08em; margin-top: 2px; }
.dmd { color: var(--gold2); margin: 0 8px; font-size: .55rem; }

/* ── Student panel ──────────────────────────────────────── */
.rc-id {
  display: grid; grid-template-columns: 90px 1fr;
  gap: 0 20px; padding: 14px 24px 16px;
  background: #f9fafb;
  border-bottom: 2px solid var(--border);
  position: relative; z-index: 1;
}
.rc-id-photo-wrap { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.rc-id-photo {
  width: 84px; height: 100px; object-fit: cover;
  border: 2.5px solid var(--navy); border-radius: 3px;
  box-shadow: 0 2px 6px rgba(0,0,0,.14);
}
.rc-id-init {
  width: 84px; height: 100px; border-radius: 3px;
  background: linear-gradient(145deg, var(--navy) 55%, var(--navy2));
  color: #fff; display: flex; align-items: center; justify-content: center;
  font-size: 2rem; font-weight: 800; border: 2.5px solid var(--navy);
}
.rc-photo-lbl { font-size: .57rem; color: var(--subtle); text-transform: uppercase; letter-spacing: .05em; }
.rc-id-body { }
.rc-id-name {
  font-size: 1.1rem; font-weight: 800; color: var(--navy);
  padding-bottom: 7px; margin-bottom: 9px;
  border-bottom: 1.5px dashed var(--border);
}
.rc-id-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 6px 20px; }
.rc-fld-lbl {
  font-size: .58rem; font-weight: 700; color: var(--subtle);
  text-transform: uppercase; letter-spacing: .05em; display: block; margin-bottom: 1px;
}
.rc-fld-val { font-size: .76rem; font-weight: 600; color: var(--ink); display: block; }

/* ── KPI row ────────────────────────────────────────────── */
.rc-kpi {
  display: grid; grid-template-columns: repeat(4,1fr);
  border-bottom: 2px solid var(--border);
  position: relative; z-index: 1;
}
.rc-kpi-cell {
  text-align: center; padding: 12px 8px;
  border-right: 1px solid var(--border);
}
.rc-kpi-cell:last-child { border-right: none; }
.rc-kpi-ico { font-size: .8rem; margin-bottom: 4px; opacity: .55; }
.rc-kpi-val { font-size: 1.35rem; font-weight: 800; line-height: 1.1; }
.rc-kpi-sub { font-size: .62rem; color: var(--muted); margin-top: 2px; }
.rc-kpi-lbl { font-size: .57rem; color: var(--subtle); text-transform: uppercase; letter-spacing: .07em; margin-top: 4px; }

/* ── Section heading ────────────────────────────────────── */
.rc-sec {
  background: var(--navy); color: #fff;
  padding: 5px 24px; font-size: .63rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .14em;
  display: flex; align-items: center; gap: 8px;
  position: relative; z-index: 1;
}
.rc-sec i { color: var(--gold2); }
.rc-sec-alt {
  background: #1c3561; color: #fff;
  padding: 5px 24px; font-size: .63rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .14em;
  display: flex; align-items: center; gap: 8px;
  position: relative; z-index: 1;
}
.rc-sec-alt i { color: #7dd3fc; }

/* ── Subject performance table ──────────────────────────── */
.rc-tbl-wrap { overflow-x: auto; position: relative; z-index: 1; }
.rc-tbl { width: 100%; border-collapse: collapse; font-size: .75rem; }
.rc-tbl thead th {
  background: var(--navy); color: #fff;
  padding: 6px 10px; font-size: .61rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: .06em; white-space: nowrap;
  border-right: 1px solid rgba(255,255,255,.1);
}
.rc-tbl thead th:last-child { border-right: none; }
.ctr { text-align: center; }
.rgt { text-align: right; }
.rc-tbl tbody tr:nth-child(odd)  td { background: #fff; }
.rc-tbl tbody tr:nth-child(even) td { background: #f8fafc; }
.rc-tbl tbody tr:hover           td { background: #eef2ff; transition: background .12s; }
.rc-tbl td {
  padding: 7px 10px;
  border-bottom: 1px solid #e4e9f2;
  border-right: 1px solid #e4e9f2;
  vertical-align: top;
}
.rc-tbl td:last-child { border-right: none; }
.rc-tbl tfoot td {
  background: linear-gradient(90deg, #0f2456 0%, #1a3a6b 100%);
  color: #fff; font-weight: 700; font-size: .77rem;
  padding: 8px 10px;
  border-right: 1px solid rgba(255,255,255,.1);
}
.rc-tbl tfoot td:last-child { border-right: none; }

.rc-sn { font-size: .64rem; color: var(--subtle); text-align: center; padding-top: 9px !important; }
.rc-subj-nm { font-weight: 700; color: var(--navy); font-size: .78rem; }
.rc-subj-cd { font-size: .61rem; color: var(--subtle); display: block; margin-top: 2px; }

/* ── Assessment chips ───────────────────────────────────── */
.asm {
  display: inline-flex; align-items: baseline; gap: 3px;
  background: #eef2ff; border: 1px solid #c7d2fe;
  border-radius: 3px; padding: 1px 5px;
  margin: 1px 2px; white-space: nowrap;
}
.asm-nil { background: #f8fafc; border-color: #e2e8f0; }
.asm-n { font-size: .59rem; color: #4338ca; font-weight: 500; }
.asm-nil .asm-n { color: var(--subtle); }
.asm-s { font-size: .68rem; font-weight: 700; color: var(--navy); }
.asm-nil .asm-s { color: var(--subtle); font-weight: 400; }
.asm-d { font-weight: 400; color: var(--subtle); font-size: .62rem; }
.rc-no-asm { color: var(--subtle); font-style: italic; font-size: .71rem; }

/* ── Grade badge ────────────────────────────────────────── */
.rc-gr {
  display: inline-block; padding: 2px 8px; border-radius: 3px;
  font-size: .7rem; font-weight: 800; letter-spacing: .04em; min-width: 32px; text-align: center;
}

/* ── Attendance table ───────────────────────────────────── */
.rc-att { width: 100%; border-collapse: collapse; font-size: .73rem; position: relative; z-index: 1; }
.rc-att thead th {
  background: #1c3561; color: #fff;
  padding: 5px 10px; font-size: .6rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: .06em;
  border-right: 1px solid rgba(255,255,255,.1);
}
.rc-att thead th:last-child { border-right: none; }
.rc-att tbody tr:nth-child(odd)  td { background: #fff; }
.rc-att tbody tr:nth-child(even) td { background: #f8fafc; }
.rc-att td {
  padding: 5px 10px; border-bottom: 1px solid #e4e9f2;
  border-right: 1px solid #e4e9f2;
}
.rc-att td:last-child { border-right: none; }
.rc-att-bar { display: flex; align-items: center; gap: 7px; }
.rc-att-track { flex: 1; height: 5px; background: #e2e8f0; border-radius: 3px; overflow: hidden; min-width: 40px; }
.rc-att-fill  { height: 100%; border-radius: 3px; }
.rc-att-pct   { font-weight: 700; min-width: 36px; text-align: right; font-size: .71rem; }
.rc-att-tot { background: linear-gradient(90deg,#f0f4ff,#e8eeff) !important; }
.rc-att-tot td { font-weight: 700; color: var(--navy); }

/* ── Grade scale ────────────────────────────────────────── */
.rc-gscale {
  display: flex; align-items: center; flex-wrap: wrap; gap: 7px;
  padding: 8px 24px; background: #f9fafb;
  border-top: 1px solid var(--border);
  font-size: .65rem; position: relative; z-index: 1;
}
.rc-gscale strong { font-size: .63rem; color: var(--ink); text-transform: uppercase; letter-spacing: .05em; margin-right: 4px; }
.rc-gs-item { display: flex; align-items: center; gap: 4px; }
.rc-gs-rng  { color: var(--muted); font-size: .61rem; }

/* ── Remarks ────────────────────────────────────────────── */
.rc-rem {
  display: grid; grid-template-columns: 1fr 1fr;
  border-top: 2px solid var(--border);
  position: relative; z-index: 1;
}
.rc-rem-cell { padding: 10px 24px 13px; border-right: 1px solid var(--border); }
.rc-rem-cell:last-child { border-right: none; }
.rc-rem-lbl { font-size: .6rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--navy); margin-bottom: 8px; display: flex; align-items: center; gap: 5px; }
.rc-rem-line { border-bottom: 1px solid #bcc3d0; min-height: 18px; margin-bottom: 5px; }

/* ── Signatures ─────────────────────────────────────────── */
.rc-sigs {
  display: grid; grid-template-columns: repeat(4,1fr);
  border-top: 1px solid var(--border);
  position: relative; z-index: 1;
}
.rc-sig {
  text-align: center; padding: 22px 10px 12px;
  border-right: 1px solid var(--border); font-size: .68rem;
}
.rc-sig:last-child { border-right: none; }
.rc-sig-space { height: 26px; }
.rc-sig-line { border-top: 1.5px solid #374151; padding-top: 5px; margin-top: 2px; }
.rc-sig-title { font-weight: 700; color: var(--navy); font-size: .73rem; }
.rc-sig-sub   { color: var(--muted); font-size: .63rem; margin-top: 1px; }

/* ── Footer ─────────────────────────────────────────────── */
.rc-foot {
  background: var(--navy); color: rgba(255,255,255,.62);
  font-size: .62rem; padding: 7px 22px;
  display: flex; justify-content: space-between; align-items: center;
  flex-wrap: wrap; gap: 4px; position: relative; z-index: 1;
}
.rc-stamp {
  background: #166534; color: #dcfce7;
  font-size: .59rem; font-weight: 700;
  padding: 2px 11px; border-radius: 20px;
  letter-spacing: .08em; text-transform: uppercase;
}

/* ── Print ──────────────────────────────────────────────── */
@media print {
  @page { size: A4; margin: 7mm 10mm; }
  * { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; print-color-adjust: exact !important; }
  body  { background: #fff !important; }
  .toolbar { display: none !important; }
  .rc-page  { padding: 0; max-width: 100%; margin: 0; }
  .rc-paper { box-shadow: none; }
  .rc-tbl tbody tr:hover td { background: inherit !important; }
  .rc-sigs, .rc-rem, .rc-gscale, .rc-att { page-break-inside: avoid; }
}

/* ── Responsive ─────────────────────────────────────────── */
@media (max-width: 640px) {
  .rc-head      { grid-template-columns: 62px 1fr; }
  .rc-head-meta { display: none; }
  .rc-id        { grid-template-columns: 72px 1fr; }
  .rc-id-grid   { grid-template-columns: 1fr 1fr; }
  .rc-kpi       { grid-template-columns: repeat(2,1fr); }
  .rc-sigs      { grid-template-columns: repeat(2,1fr); }
  .rc-rem       { grid-template-columns: 1fr; }
  .toolbar .ttl { display: none; }
}
</style>
</head>
<body>

<!-- Toolbar -->
<div class="toolbar">
  <a href="<?= $base . $backUrl ?>"><i class="fas fa-arrow-left"></i> Back</a>
  <span class="ttl">Report Card — <?= h($student['name']) ?></span>
  <span class="tb-badge">RESTRICTED</span>
  <button onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button>
</div>

<div class="rc-page">
<div class="rc-paper">

  <!-- Top stripe -->
  <div class="s-gold"></div>
  <div class="s-navy"></div>
  <div class="s-gold"></div>

  <!-- ── Letterhead ─────────────────────────────────────── -->
  <div class="rc-head">
    <div class="rc-head-logo">
      <?php if (file_exists(__DIR__ . '/../assets/bmc-logo.png')): ?>
      <img src="<?= $base ?>/assets/bmc-logo.png" alt="Logo"
           onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
      <div class="rc-head-init" style="display:none">BMC</div>
      <?php else: ?>
      <div class="rc-head-init">BMC</div>
      <?php endif; ?>
    </div>
    <div>
      <div class="rc-school-name"><?= h($schoolName) ?></div>
      <?php if ($schoolAddr): ?><div class="rc-school-addr"><?= h($schoolAddr) ?></div><?php endif; ?>
      <span class="rc-campus-pill"><?= h($campusLabel) ?></span>
    </div>
    <div class="rc-head-meta">
      <span class="doc-lbl">Report Card</span>
      Session: <?= h($sessionYear) ?><br>
      <?php if ($currentTerm): ?>Term: <?= h($currentTerm) ?><br><?php endif; ?>
      Date: <?= $reportDate ?>
      <div class="rc-head-grno">GR# <?= h($student['gr_no'] ?? '—') ?></div>
    </div>
  </div>

  <div class="gold-rule"></div>

  <!-- ── Title band ─────────────────────────────────────── -->
  <div class="rc-title-band">
    <div class="rc-title-main">
      <span class="dmd">&#9670;</span>Academic Report Card<span class="dmd">&#9670;</span>
    </div>
    <div class="rc-title-sub">
      Academic Session <?= h($sessionYear) ?>
      <?php if ($currentTerm): ?><span class="dmd">&#9670;</span><?= h($currentTerm) ?><?php endif; ?>
      <span class="dmd">&#9670;</span><?= h($campusLabel) ?>
    </div>
  </div>

  <div class="gold-rule-sm"></div>

  <!-- ── Student identity panel ─────────────────────────── -->
  <div class="rc-id">
    <div class="rc-id-photo-wrap">
      <?php if ($photoUrl): ?>
      <img class="rc-id-photo" src="<?= h($photoUrl) ?>" alt="Photo">
      <?php else: ?>
      <div class="rc-id-init"><?= h(mb_strtoupper(mb_substr($student['name'], 0, 2))) ?></div>
      <?php endif; ?>
      <span class="rc-photo-lbl">Student Photo</span>
    </div>
    <div class="rc-id-body">
      <div class="rc-id-name"><?= h($student['name']) ?></div>
      <div class="rc-id-grid">
        <div>
          <span class="rc-fld-lbl">GR Number / Student ID</span>
          <span class="rc-fld-val"><?= h($student['gr_no'] ?? '—') ?></span>
        </div>
        <div>
          <span class="rc-fld-lbl">Roll Number</span>
          <span class="rc-fld-val"><?= h($student['roll_no'] ?? '—') ?></span>
        </div>
        <div>
          <span class="rc-fld-lbl">Class / Section</span>
          <span class="rc-fld-val"><?= h($student['class_name'] ?? '—') ?></span>
        </div>
        <div>
          <span class="rc-fld-lbl">Academic Session</span>
          <span class="rc-fld-val"><?= h($sessionYear) ?></span>
        </div>
        <?php if (!empty($student['father_name'])): ?>
        <div>
          <span class="rc-fld-lbl">Father's Name</span>
          <span class="rc-fld-val"><?= h($student['father_name']) ?></span>
        </div>
        <?php elseif (!empty($student['parent_name'])): ?>
        <div>
          <span class="rc-fld-lbl">Parent / Guardian</span>
          <span class="rc-fld-val"><?= h($student['parent_name']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($student['dob'])): ?>
        <div>
          <span class="rc-fld-lbl">Date of Birth</span>
          <span class="rc-fld-val"><?= fDate($student['dob']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($student['gender'])): ?>
        <div>
          <span class="rc-fld-lbl">Gender</span>
          <span class="rc-fld-val"><?= ucfirst(h($student['gender'])) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($student['house_name'])): ?>
        <div>
          <span class="rc-fld-lbl">House</span>
          <span class="rc-fld-val" style="color:<?= h($student['house_color'] ?? '#0f2456') ?>;font-weight:700">
            <?= h($student['house_name']) ?>
          </span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── KPI summary row ────────────────────────────────── -->
  <?php if ($grandMax > 0): ?>
  <div class="rc-kpi">
    <div class="rc-kpi-cell">
      <div class="rc-kpi-ico"><i class="fas fa-percent" style="color:var(--navy)"></i></div>
      <div class="rc-kpi-val" style="color:var(--navy)"><?= $grandPct ?>%</div>
      <div class="rc-kpi-sub"><?= round($grandObtained,0) ?> / <?= round($grandMax,0) ?> marks</div>
      <div class="rc-kpi-lbl">Overall Percentage</div>
    </div>
    <div class="rc-kpi-cell">
      <div class="rc-kpi-ico"><i class="fas fa-award" style="color:<?= gradeColor($overallGrade) ?>"></i></div>
      <div class="rc-kpi-val" style="padding:2px 0">
        <span class="rc-gr" style="background:<?= gradeBg($overallGrade) ?>;color:<?= gradeColor($overallGrade) ?>;font-size:1.2rem;padding:4px 16px">
          <?= $overallGrade ?>
        </span>
      </div>
      <div class="rc-kpi-sub"><?= gradeText($overallGrade) ?></div>
      <div class="rc-kpi-lbl">Final Grade</div>
    </div>
    <div class="rc-kpi-cell">
      <div class="rc-kpi-ico"><i class="fas fa-calendar-check" style="color:<?= $attPct >= 75 ? '#15803d' : '#dc2626' ?>"></i></div>
      <div class="rc-kpi-val" style="color:<?= $attPct >= 75 ? '#15803d' : '#dc2626' ?>"><?= $attPct ?>%</div>
      <div class="rc-kpi-sub"><?= $attPresent ?> present / <?= $attTotal ?> total</div>
      <div class="rc-kpi-lbl">Attendance</div>
    </div>
    <div class="rc-kpi-cell">
      <div class="rc-kpi-ico"><i class="fas fa-book-open" style="color:var(--muted)"></i></div>
      <div class="rc-kpi-val" style="color:var(--muted)"><?= count($subjects) ?></div>
      <div class="rc-kpi-sub">subjects enrolled</div>
      <div class="rc-kpi-lbl">Total Subjects</div>
    </div>
  </div>
  <?php else: ?>
  <div style="padding:12px 24px;background:#fffbeb;border-bottom:1px solid #fde68a;font-size:.8rem;color:#92400e;position:relative;z-index:1">
    <i class="fas fa-info-circle"></i>&nbsp; No assessment data recorded for this student yet.
  </div>
  <?php endif; ?>

  <div class="gold-rule"></div>

  <!-- ── Subject-wise performance ──────────────────────── -->
  <div class="rc-sec">
    <i class="fas fa-table-list"></i>Subject-Wise Academic Performance
  </div>

  <?php if (empty($subjects)): ?>
  <div style="padding:18px 24px;color:var(--muted);font-size:.82rem;position:relative;z-index:1">
    No assessments found for this student's class.
  </div>
  <?php else: ?>
  <div class="rc-tbl-wrap">
    <table class="rc-tbl">
      <thead>
        <tr>
          <th style="width:3%;text-align:center">#</th>
          <th style="width:16%">Subject</th>
          <th>Assessment Breakdown</th>
          <th class="rgt" style="width:8%">Obtained</th>
          <th class="rgt" style="width:7%">Total</th>
          <th class="rgt" style="width:7%">%</th>
          <th class="ctr" style="width:8%">Grade</th>
        </tr>
      </thead>
      <tbody>
        <?php $rn = 1; foreach ($subjects as $sub):
          $hm = $sub['total_max'] > 0;
          $g  = $sub['grade'];
        ?>
        <tr>
          <td class="rc-sn"><?= $rn++ ?></td>
          <td>
            <span class="rc-subj-nm"><?= h($sub['name']) ?></span>
            <?php if (!empty($sub['code'])): ?>
            <span class="rc-subj-cd"><?= h($sub['code']) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (empty($sub['assessments'])): ?>
            <span class="rc-no-asm">No assessments recorded</span>
            <?php else: ?>
            <?= $sub['chips'] ?>
            <?php endif; ?>
          </td>
          <td class="rgt" style="font-weight:700">
            <?= $hm ? round($sub['total_obtained'],1) : '<span style="color:var(--subtle)">—</span>' ?>
          </td>
          <td class="rgt" style="color:var(--muted)">
            <?= $hm ? (int)$sub['total_max'] : '—' ?>
          </td>
          <td class="rgt" style="font-weight:700;color:<?= $hm ? 'var(--navy)' : 'var(--subtle)' ?>">
            <?= $hm ? $sub['overall_pct'].'%' : '—' ?>
          </td>
          <td class="ctr">
            <?php if ($hm): ?>
            <span class="rc-gr" style="background:<?= gradeBg($g) ?>;color:<?= gradeColor($g) ?>"><?= $g ?></span>
            <?php else: ?><span style="color:var(--subtle)">—</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" style="letter-spacing:.05em">
            OVERALL RESULT
            <?php if ($grandWTotal > 0): ?>
            <span style="font-size:.61rem;opacity:.6;font-weight:400;margin-left:8px">
              &#9654; weighted average across all assessments
            </span>
            <?php endif; ?>
          </td>
          <td class="rgt"><?= round($grandObtained,0) ?></td>
          <td class="rgt"><?= round($grandMax,0) ?></td>
          <td class="rgt"><?= $grandPct ?>%</td>
          <td class="ctr">
            <span class="rc-gr" style="background:<?= gradeBg($overallGrade) ?>;color:<?= gradeColor($overallGrade) ?>;padding:3px 10px;font-size:.78rem">
              <?= $overallGrade ?>
            </span>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>

  <!-- ── Attendance ─────────────────────────────────────── -->
  <?php if (!empty($attendance)): ?>
  <div class="rc-sec-alt">
    <i class="fas fa-calendar-check"></i>Attendance Summary
  </div>
  <table class="rc-att">
    <thead>
      <tr>
        <th>Subject</th>
        <th class="rgt">Present</th>
        <th class="rgt">Absent</th>
        <th class="rgt">Leave</th>
        <th class="rgt">Total</th>
        <th style="width:140px">Attendance</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($attendance as $att):
        $sp = $att['total'] > 0 ? round($att['present'] / $att['total'] * 100) : 0;
        $bc = $sp >= 75 ? '#15803d' : ($sp >= 60 ? '#d97706' : '#dc2626');
      ?>
      <tr>
        <td>
          <span style="font-weight:600"><?= h($att['subject']) ?></span>
          <?php if (!empty($att['code'])): ?>
          <span style="font-size:.62rem;color:var(--subtle);margin-left:4px">(<?= h($att['code']) ?>)</span>
          <?php endif; ?>
        </td>
        <td class="rgt" style="color:#15803d;font-weight:600"><?= $att['present'] ?></td>
        <td class="rgt" style="color:#dc2626;font-weight:600"><?= $att['absent'] ?></td>
        <td class="rgt" style="color:#d97706;font-weight:600"><?= $att['leave'] ?></td>
        <td class="rgt" style="font-weight:500"><?= $att['total'] ?></td>
        <td>
          <div class="rc-att-bar">
            <div class="rc-att-track">
              <div class="rc-att-fill" style="width:<?= $sp ?>%;background:<?= $bc ?>"></div>
            </div>
            <span class="rc-att-pct" style="color:<?= $bc ?>"><?= $sp ?>%</span>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if ($attTotal > 0):
        $oc = $attPct >= 75 ? '#15803d' : ($attPct >= 60 ? '#d97706' : '#dc2626');
        $absTotal   = array_sum(array_column($attendance, 'absent'));
        $leaveTotal = array_sum(array_column($attendance, 'leave'));
      ?>
      <tr class="rc-att-tot">
        <td>Overall Attendance</td>
        <td class="rgt" style="color:#15803d"><?= $attPresent ?></td>
        <td class="rgt" style="color:#dc2626"><?= $absTotal ?></td>
        <td class="rgt" style="color:#d97706"><?= $leaveTotal ?></td>
        <td class="rgt"><?= $attTotal ?></td>
        <td>
          <div class="rc-att-bar">
            <div class="rc-att-track">
              <div class="rc-att-fill" style="width:<?= $attPct ?>%;background:<?= $oc ?>"></div>
            </div>
            <span class="rc-att-pct" style="color:<?= $oc ?>"><?= $attPct ?>%</span>
          </div>
        </td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- ── Grade scale ────────────────────────────────────── -->
  <div class="rc-gscale">
    <strong>Grade Scale:</strong>
    <?php foreach (['A+'=>['≥ 90%','Outstanding'],'A'=>['≥ 80%','Excellent'],'B'=>['≥ 70%','Very Good'],'C'=>['≥ 60%','Good'],'D'=>['≥ 50%','Satisfactory'],'F'=>['< 50%','Fail']] as $gr => [$rng,$desc]): ?>
    <span class="rc-gs-item">
      <span class="rc-gr" style="background:<?= gradeBg($gr) ?>;color:<?= gradeColor($gr) ?>;padding:1px 7px;font-size:.63rem"><?= $gr ?></span>
      <span class="rc-gs-rng"><?= $rng ?> &mdash; <?= $desc ?></span>
    </span>
    <?php endforeach; ?>
  </div>

  <!-- ── Remarks ────────────────────────────────────────── -->
  <div class="rc-rem">
    <div class="rc-rem-cell">
      <div class="rc-rem-lbl">
        <i class="fas fa-comment-dots" style="opacity:.45"></i>Class Teacher Remarks
      </div>
      <div class="rc-rem-line"></div>
      <div class="rc-rem-line"></div>
    </div>
    <div class="rc-rem-cell">
      <div class="rc-rem-lbl">
        <i class="fas fa-stamp" style="opacity:.45"></i>Principal Remarks
      </div>
      <div class="rc-rem-line"></div>
      <div class="rc-rem-line"></div>
    </div>
  </div>

  <!-- ── Signatures ─────────────────────────────────────── -->
  <div class="rc-sigs">
    <div class="rc-sig">
      <div class="rc-sig-space"></div>
      <div class="rc-sig-line">
        <div class="rc-sig-title">Class Teacher</div>
        <div class="rc-sig-sub">Signature &amp; Date</div>
      </div>
    </div>
    <div class="rc-sig">
      <div class="rc-sig-space"></div>
      <div class="rc-sig-line">
        <div class="rc-sig-title">Head of Department</div>
        <div class="rc-sig-sub">Signature &amp; Date</div>
      </div>
    </div>
    <div class="rc-sig">
      <div class="rc-sig-space"></div>
      <div class="rc-sig-line">
        <div class="rc-sig-title"><?= h($principalName) ?></div>
        <div class="rc-sig-sub">Principal / Vice Principal</div>
      </div>
    </div>
    <div class="rc-sig">
      <div class="rc-sig-space"></div>
      <div class="rc-sig-line">
        <div class="rc-sig-title">Parent / Guardian</div>
        <div class="rc-sig-sub">Signature &amp; Date</div>
      </div>
    </div>
  </div>

  <div class="gold-rule-sm"></div>

  <!-- ── Footer ─────────────────────────────────────────── -->
  <div class="rc-foot">
    <span><?= h($schoolName) ?> &nbsp;&bull;&nbsp; <?= h($campusLabel) ?></span>
    <span class="rc-stamp">Official Document</span>
    <span>Generated: <?= $reportDate ?> &nbsp;&bull;&nbsp; Confidential — Authorised Personnel Only</span>
  </div>

  <!-- Bottom stripe -->
  <div class="s-gold"></div>
  <div class="s-navy"></div>
  <div class="s-gold"></div>

</div><!-- /rc-paper -->
</div><!-- /rc-page -->
</body>
</html>

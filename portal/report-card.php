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
require_once __DIR__ . '/includes/result_ranking.php';

$user = requireAuth(
    'admin', 'vp_main', 'vp_montessori', 'ilc_vp',
    'teacher', 'montessori_teacher', 'ilc_teacher',
    'student_affairs', 'student', 'wing_head', 'examination_head',
    'primary_wing_head', 'secondary_wing_head', 'higher_secondary_wing_head'
);
$db   = getDB();
$role = $user['role'];

// Role-specific permission guards — teachers/VPs have portal-wide access already,
// so they do not need an extra permission check to view a report card.
if ($role === 'vp_main')              requirePermission('vp_results');
elseif ($role === 'student_affairs')  requirePermission('sa_students');
elseif ($role === 'wing_head')        requirePermission('wh_students');
elseif ($role === 'examination_head') requirePermission('eh_results');

if ($role === 'student') {
    $me = getStudentByUserId($user['id']);
    if (!$me) { redirect('/portal/student/dashboard.php'); }
    $studentId = (int)$me['id'];
} else {
    $studentId = (int)($_GET['student_id'] ?? 0);
    if (!$studentId) { redirect('/portal/index.php?msg=unauthorized'); }
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
if (!$student) { redirect('/portal/index.php?msg=unauthorized'); }

// Examination Head: block access to ILC / Montessori students (main campus only)
if ($role === 'examination_head') {
    $cWing = strtolower($student['class_wing'] ?? 'main');
    if ($cWing !== 'main') {
        redirect('/portal/exam-head/results.php');
    }
}

// ─────────────────────────────────────────────────────────────────────
// REMARKS EDIT CAPABILITIES
// Teachers edit marks.remarks for assessments they own; VPs edit
// assessments.vp_remarks; admin (Principal) can edit either but has no
// dedicated "principal remarks" field — author identities stay separate.
// ─────────────────────────────────────────────────────────────────────
$canEditTeacherRemarks = in_array($role, ['teacher', 'montessori_teacher', 'ilc_teacher', 'admin'], true);
$canEditVpRemarks      = in_array($role, ['vp_main', 'vp_montessori', 'ilc_vp', 'examination_head', 'admin'], true);
$isPrincipal           = ($role === 'admin');   // Principal browses all & edits either, but has no own-remarks field

// ─────────────────────────────────────────────────────────────────────
// POST handler for inline remark edits (teacher / VP)
// ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $aid    = (int)($_POST['assessment_id'] ?? 0);
    $remark = trim((string)($_POST['remark'] ?? ''));
    $redir  = $_SERVER['REQUEST_URI'];

    if ($action === 'save_teacher_remark' && $aid && $canEditTeacherRemarks) {
        // Verify the assessment belongs to this teacher (admin bypass)
        $ok = $isPrincipal;
        if (!$ok) {
            $teacherRow = getTeacherByUserId($user['id']);
            if ($teacherRow) {
                $chk = $db->prepare('SELECT 1 FROM assessments WHERE id=? AND teacher_id=?');
                $chk->execute([$aid, $teacherRow['id']]);
                $ok = (bool)$chk->fetch();
            }
        }
        if ($ok) {
            try {
                // Upsert into marks.remarks: update row if it exists, else insert remark-only row
                $up = $db->prepare('UPDATE marks SET remarks=? WHERE assessment_id=? AND student_id=?');
                $up->execute([$remark !== '' ? $remark : null, $aid, $studentId]);
                if ($up->rowCount() === 0) {
                    $db->prepare('INSERT INTO marks (assessment_id, student_id, marks_obtained, remarks) VALUES (?,?,NULL,?)')
                       ->execute([$aid, $studentId, $remark !== '' ? $remark : null]);
                }
                logActivity($user['id'], 'rc_teacher_remark', "Report-card teacher remark: student #$studentId assessment #$aid");
                setFlash('success', 'Teacher remark saved.');
            } catch (Exception $e) {
                setFlash('danger', 'Could not save teacher remark: ' . $e->getMessage());
            }
        } else {
            setFlash('danger', 'You are not authorised to edit remarks on this assessment.');
        }
        redirect($redir);
    }

    if ($action === 'save_vp_remark' && $aid && $canEditVpRemarks) {
        try {
            $up = $db->prepare('UPDATE assessments SET vp_remarks=? WHERE id=?');
            $up->execute([$remark !== '' ? $remark : null, $aid]);
            logActivity($user['id'], 'rc_vp_remark', "Report-card VP remark: student #$studentId assessment #$aid");
            setFlash('success', 'Vice Principal remark saved.');
        } catch (Exception $e) {
            setFlash('danger', 'Could not save remark (run migrations/report_card_vp_remarks.sql): ' . $e->getMessage());
        }
        redirect($redir);
    }

    // Unknown action or lacking permission — silently redirect
    redirect($redir);
}

$editMode = isset($_GET['edit']) && $_GET['edit'] === '1' && ($canEditTeacherRemarks || $canEditVpRemarks);

// ── Class-based routing rules ─────────────────────────────
$classGrade = (int)($student['class_grade'] ?? 99);
$classWing  = strtolower($student['class_wing'] ?? 'main');
// Montessori through Class-1 (grade < 2): no formal exams — Progress Reports only
$montessoriLowGrade = ($classWing === 'montessori' && $classGrade < 2);
if ($montessoriLowGrade && $role === 'student') {
    redirect('/portal/student/progress-report.php');
}

// ── Fetch assessments + marks grouped by subject ──────────
// Students only see published results; staff/admin see all
$pubFilter = ($role === 'student') ? " AND COALESCE(a.publish_status, 'published') = 'published'" : '';

// Optional assessment-type filter: ?type=First%20Term produces a report card
// for only that assessment type. When empty, all types are included (legacy).
$typeFilter = trim((string)($_GET['type'] ?? ''));
$typeClause = '';
$qParams    = [$studentId, $student['class_id']];
if ($typeFilter !== '') {
    $typeClause = ' AND a.type = ?';
    $qParams[]  = $typeFilter;
}

// Build list of assessment types the student has marks in (for the type picker).
try {
    $tListSt = $db->prepare(
        "SELECT DISTINCT a.type
         FROM assessments a
         LEFT JOIN marks m ON m.assessment_id = a.id AND m.student_id = ?
         WHERE a.class_id = ?$pubFilter AND m.id IS NOT NULL
         ORDER BY a.type"
    );
    $tListSt->execute([$studentId, $student['class_id']]);
    $availableTypes = array_values(array_filter($tListSt->fetchAll(PDO::FETCH_COLUMN)));
} catch (Exception $e) { $availableTypes = []; }

$st = $db->prepare(
    "SELECT a.id AS assessment_id, a.name AS assessment_name, a.type,
            a.max_marks, a.weight, a.date, a.vp_remarks,
            sb.id AS subject_id, sb.name AS subject_name, sb.code AS subject_code,
            m.marks_obtained, m.remarks
     FROM assessments a
     JOIN subjects sb ON a.subject_id = sb.id
     LEFT JOIN marks m ON m.assessment_id = a.id AND m.student_id = ?
     WHERE a.class_id = ?$pubFilter$typeClause
     ORDER BY sb.name, a.date, a.id"
);
try { $st->execute($qParams); $rows = $st->fetchAll(); }
catch (Exception $e) {
    // Fallback for installs where vp_remarks column has not been migrated yet
    $st = $db->prepare(
        "SELECT a.id AS assessment_id, a.name AS assessment_name, a.type,
                a.max_marks, a.weight, a.date, NULL AS vp_remarks,
                sb.id AS subject_id, sb.name AS subject_name, sb.code AS subject_code,
                m.marks_obtained, m.remarks
         FROM assessments a
         JOIN subjects sb ON a.subject_id = sb.id
         LEFT JOIN marks m ON m.assessment_id = a.id AND m.student_id = ?
         WHERE a.class_id = ?$pubFilter$typeClause
         ORDER BY sb.name, a.date, a.id"
    );
    $st->execute($qParams);
    $rows = $st->fetchAll();
}

$bySubject = [];
foreach ($rows as $r) {
    $sid = $r['subject_id'];
    $bySubject[$sid]['name'] = $r['subject_name'];
    $bySubject[$sid]['code'] = $r['subject_code'];
    $bySubject[$sid]['assessments'][] = $r;
}

// ── Grade helpers — IBCC Pakistan scale ──────────────────────────
// All four helpers delegate to the central rubric in config/config.php so the
// report card uses the same grades, colors and labels as every other surface.
function getGradeLetter(float $pct, bool $hasMarks): string {
    if (!$hasMarks) return 'N/A';
    return gradeLetterFor($pct);
}
function gradeColor(string $g): string { return gradeColorFor($g); }
function gradeBg(string $g): string    { return gradeBgFor($g); }
function gradeText(string $g): string  { return gradePerformanceFor($g); }

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

// ── Pass/fail per school rule: every subject ≥ 45% AND aggregate ≥ 50% ───
// $subjects here is keyed by subject_id with keys like 'overall_pct' and
// 'total_max'; map to the shape studentOverallPasses expects.
$passSubjects = [];
foreach ($subjects as $sid => $sub) {
    $passSubjects[] = [
        'max'  => (float)$sub['total_max'],
        'pct'  => (float)$sub['overall_pct'],
        'name' => $sub['name'] ?? ('Subject #'.$sid),
    ];
}
$hadMarks        = $grandMax > 0;
$studentPasses   = studentOverallPasses($passSubjects, (float)$grandPct, $hadMarks);
$passStatusLabel = studentPassStatusLabel($passSubjects, (float)$grandPct, $hadMarks);
$failedSubjects  = [];
foreach ($passSubjects as $ps) {
    if ($ps['max'] > 0 && !isSubjectPassing((float)$ps['pct'])) $failedSubjects[] = $ps['name'];
}

// ── Class position (Main Campus only, published results only) ─────────────────
$classPosition = null;
$classTotal    = null;
if (!$montessoriLowGrade && $student['class_id'] && $role !== 'student') {
    // For staff/admin: compute position based on published results
    try {
        $classPosition = getStudentPosition($db, $studentId, (int)$student['class_id']);
        // Count class size
        $csSt = $db->prepare("SELECT COUNT(*) FROM students WHERE class_id=? AND deleted_at IS NULL");
        $csSt->execute([$student['class_id']]);
        $classTotal = (int)$csSt->fetchColumn();
    } catch (Exception $e) {}
} elseif (!$montessoriLowGrade && $student['class_id'] && $role === 'student') {
    try {
        $classPosition = getStudentPosition($db, $studentId, (int)$student['class_id']);
        $csSt = $db->prepare("SELECT COUNT(*) FROM students WHERE class_id=? AND deleted_at IS NULL");
        $csSt->execute([$student['class_id']]);
        $classTotal = (int)$csSt->fetchColumn();
    } catch (Exception $e) {}
}

// ── Attendance (class-wise) ───────────────────────────────
// Attendance is recorded against the class (subject_id IS NULL on every row);
// getStudentClassAttendance returns pre-aggregated totals.
$attSummary = getStudentClassAttendance($studentId);
$attTotal   = (int)   $attSummary['total'];
$attPresent = (int)   $attSummary['present'];
$attPct     = (float) $attSummary['percent'];

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
        // Show the exact stored value — strip trailing zeros but keep decimals
        // when the mark actually has them. Prevents a 39.97-stored value from
        // being masked as "40.0" via 1-dp rounding.
        $disp = $val !== null
            ? rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.')
            : '—';
        $nil = $val === null;
        $chips[] =
            '<span class="asm'.($nil ? ' asm-nil' : '').'">' .
            '<span class="asm-n">'.h($a['assessment_name']).'</span>' .
            '<span class="asm-s">'.$disp.'<span class="asm-d">/'.rtrim(rtrim(number_format((float)$a['max_marks'],2,'.',''),'0'),'.').'</span></span>' .
            '</span>';
    }
    $sub['chips'] = implode('', $chips);
}
unset($sub);

$isWordExport = (($_GET['export'] ?? '') === 'word');
if ($isWordExport) {
    $wfname = 'ReportCard_' . preg_replace('/[^A-Za-z0-9_]/', '_', $student['name']) . '.doc';
    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="' . $wfname . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
}
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
  display: grid; grid-template-columns: repeat(5,1fr);
  border-bottom: 2px solid var(--border);
  position: relative; z-index: 1;
}
@media (max-width: 760px) { .rc-kpi { grid-template-columns: repeat(2,1fr); } }
@media print            { .rc-kpi { grid-template-columns: repeat(5,1fr); } }
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
<?php if (!$isWordExport): ?>
<div class="toolbar">
  <a href="<?= $base . $backUrl ?>"><i class="fas fa-arrow-left"></i> Back</a>
  <span class="ttl">Report Card — <?= h($student['name']) ?></span>
  <span class="tb-badge">RESTRICTED</span>
  <?php if (!empty($availableTypes)): ?>
  <form method="GET" style="display:inline-flex;align-items:center;gap:6px;margin-left:12px">
    <?php if ($role !== 'student'): ?>
    <input type="hidden" name="student_id" value="<?= $studentId ?>">
    <?php endif; ?>
    <label style="font-size:.78rem;font-weight:600;color:#374151">Assessment:</label>
    <select name="type" onchange="this.form.submit()"
            style="font-size:.8rem;padding:3px 8px;border:1px solid #d1d5db;border-radius:4px;background:#fff">
      <option value="">— All Assessments (combined) —</option>
      <?php foreach ($availableTypes as $t): ?>
      <option value="<?= h($t) ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= h($t) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php endif; ?>
  <?php
    $exportQS = ($role !== 'student' ? 'student_id='.$studentId.'&' : '')
              . ($typeFilter !== '' ? 'type='.urlencode($typeFilter).'&' : '')
              . 'export=word';
    $wordHref = url('/portal/report-card.php?' . $exportQS);
  ?>
  <a href="<?= $wordHref ?>"><i class="fas fa-file-word"></i> Word</a>
  <button onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button>
</div>
<?php
  $flashMsg = flashHtml();
  if ($flashMsg !== ''):
?>
<div class="no-print" style="max-width:960px;margin:12px auto 0;padding:0 16px">
  <?= $flashMsg ?>
</div>
<?php endif; ?>
<?php endif; ?>

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
      Date: <?= $reportDate ?>
      <div class="rc-head-grno">GR# <?= h($student['gr_no'] ?? '—') ?></div>
    </div>
  </div>

  <div class="gold-rule"></div>

  <!-- ── Title band ─────────────────────────────────────── -->
  <div class="rc-title-band">
    <div class="rc-title-main">
      <span class="dmd">&#9670;</span><?= $typeFilter !== '' ? h($typeFilter) . ' Report Card' : 'Academic Report Card' ?><span class="dmd">&#9670;</span>
    </div>
    <div class="rc-title-sub">
      Academic Session <?= h($sessionYear) ?>
      <span class="dmd">&#9670;</span><?= h($campusLabel) ?>
      <?php if ($typeFilter === ''): ?>
      <span class="dmd">&#9670;</span>Combined Results
      <?php endif; ?>
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
        <?php if ($classPosition !== null): ?>
        <div>
          <span class="rc-fld-lbl">Class Position</span>
          <span class="rc-fld-val" style="color:<?= $classPosition <= 3 ? '#b8860b' : 'var(--ink)' ?>;font-weight:700">
            <?= positionLabel($classPosition) ?><?= $classTotal ? ' / ' . $classTotal : '' ?>
          </span>
        </div>
        <?php endif; ?>
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
  <?php if ($montessoriLowGrade): ?>
  <div style="padding:14px 24px;background:#fef3c7;border-bottom:2px solid #fde68a;font-size:.82rem;color:#78350f;position:relative;z-index:1;display:flex;align-items:flex-start;gap:10px">
    <i class="fas fa-info-circle" style="margin-top:2px;flex-shrink:0"></i>
    <span>
      <strong><?= h($student['name']) ?></strong> is enrolled in <strong><?= h($student['class_name'] ?? 'Montessori') ?></strong>
      (Montessori / Class&nbsp;1), which uses the <strong>Formative Assessment &amp; Progress Report</strong> system only — no formal examinations are conducted.
      <br>Their academic progress is tracked through Progress Reports.
      <?php if (in_array($role, ['admin','vp_main','student_affairs','wing_head'])): ?>
      &nbsp;<a href="<?= url('/portal/progress-report/form.php') ?>" style="color:#78350f;font-weight:700;text-decoration:underline" target="_blank">View Progress Reports &rarr;</a>
      <?php endif; ?>
    </span>
  </div>
  <?php elseif ($grandMax > 0): ?>
  <div class="rc-kpi">
    <div class="rc-kpi-cell">
      <div class="rc-kpi-ico"><i class="fas fa-percent" style="color:var(--navy)"></i></div>
      <div class="rc-kpi-val" style="color:var(--navy)"><?= $grandPct ?>%</div>
      <div class="rc-kpi-sub"><?= round($grandObtained,2) ?> / <?= round($grandMax,2) ?> marks</div>
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
      <?php
        // Pass/Fail tile — always shown when marks exist. If the student
        // failed any subject, surface the reason so parents understand why
        // no merit position is awarded.
        $passColor = $studentPasses ? '#15803d' : '#991b1b';
        $passBg    = $studentPasses ? '#dcfce7' : '#fee2e2';
      ?>
      <div class="rc-kpi-ico"><i class="fas fa-<?= $studentPasses ? 'check-circle' : 'times-circle' ?>" style="color:<?= $passColor ?>"></i></div>
      <div class="rc-kpi-val" style="padding:2px 0">
        <span class="rc-gr" style="background:<?= $passBg ?>;color:<?= $passColor ?>;font-size:1.2rem;padding:4px 16px">
          <?= $studentPasses ? 'Pass' : 'Fail' ?>
        </span>
      </div>
      <div class="rc-kpi-sub">
        <?php if ($studentPasses): ?>
          Promoted to Next Class
        <?php elseif (!empty($failedSubjects)): ?>
          Below 45% in: <?= h(implode(', ', array_slice($failedSubjects, 0, 2))) . (count($failedSubjects) > 2 ? ' …' : '') ?>
        <?php elseif ($grandPct < AGGREGATE_PASS_PCT): ?>
          Aggregate below <?= (int)AGGREGATE_PASS_PCT ?>%
        <?php else: ?>
          &nbsp;
        <?php endif; ?>
      </div>
      <div class="rc-kpi-lbl">Result</div>
    </div>
    <div class="rc-kpi-cell">
      <?php if (!$studentPasses && $classPosition === null): ?>
      <div class="rc-kpi-ico"><i class="fas fa-ban" style="color:var(--muted)"></i></div>
      <div class="rc-kpi-val" style="color:var(--muted);font-size:1rem">—</div>
      <div class="rc-kpi-sub">Not eligible for merit position</div>
      <div class="rc-kpi-lbl">Class Position</div>
      <?php elseif ($classPosition !== null && $classPosition <= 3): ?>
      <div class="rc-kpi-ico"><i class="fas fa-trophy" style="color:#b8860b"></i></div>
      <div class="rc-kpi-val" style="color:#b8860b"><?= positionLabel($classPosition) ?></div>
      <div class="rc-kpi-sub">
        <?php if ($classPosition === 1): ?>Class Topper<?php
        elseif ($classPosition === 2): ?>Second Position<?php
        else: ?>Third Position<?php endif; ?>
      </div>
      <div class="rc-kpi-lbl">Class Position</div>
      <?php elseif ($classPosition !== null): ?>
      <div class="rc-kpi-ico"><i class="fas fa-ranking-star" style="color:var(--muted)"></i></div>
      <div class="rc-kpi-val" style="color:var(--muted)"><?= positionLabel($classPosition) ?></div>
      <div class="rc-kpi-sub"><?= $classTotal ? 'of ' . $classTotal . ' students' : 'in class' ?></div>
      <div class="rc-kpi-lbl">Class Position</div>
      <?php else: ?>
      <div class="rc-kpi-ico"><i class="fas fa-book-open" style="color:var(--muted)"></i></div>
      <div class="rc-kpi-val" style="color:var(--muted)"><?= count($subjects) ?></div>
      <div class="rc-kpi-sub">subjects enrolled</div>
      <div class="rc-kpi-lbl">Total Subjects</div>
      <?php endif; ?>
    </div>
  </div>
  <?php else: ?>
  <div style="padding:12px 24px;background:#fffbeb;border-bottom:1px solid #fde68a;font-size:.8rem;color:#92400e;position:relative;z-index:1">
    <i class="fas fa-info-circle"></i>&nbsp; No assessment data recorded for this student yet.
  </div>
  <?php endif; ?>

  <div class="gold-rule"></div>

  <!-- ── Subject-wise performance ──────────────────────── -->
  <?php if (!$montessoriLowGrade): ?>
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
          <td class="rgt"><?= round($grandObtained,2) ?></td>
          <td class="rgt"><?= round($grandMax,2) ?></td>
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

  <!-- ── Attendance (simplified: 3 figures only) ────────── -->
  <?php if ($attTotal > 0): ?>
  <div class="rc-sec-alt">
    <i class="fas fa-calendar-check"></i>Attendance Summary
  </div>
  <table class="rc-att-simple" style="width:100%;border-collapse:collapse;margin-bottom:14px">
    <tbody>
      <tr>
        <td style="width:33.33%;padding:14px 10px;text-align:center;border:1px solid #e5e7eb;background:#f8fafc">
          <div style="font-size:.72rem;letter-spacing:.6px;text-transform:uppercase;color:#64748b;font-weight:600;margin-bottom:4px">Total Working Days</div>
          <div style="font-size:1.3rem;font-weight:700;color:#1e293b"><?= $attTotal ?></div>
        </td>
        <td style="width:33.33%;padding:14px 10px;text-align:center;border:1px solid #e5e7eb;background:#f8fafc">
          <div style="font-size:.72rem;letter-spacing:.6px;text-transform:uppercase;color:#64748b;font-weight:600;margin-bottom:4px">Days Present</div>
          <div style="font-size:1.3rem;font-weight:700;color:#15803d"><?= $attPresent ?></div>
        </td>
        <td style="width:33.33%;padding:14px 10px;text-align:center;border:1px solid #e5e7eb;background:#f8fafc">
          <div style="font-size:.72rem;letter-spacing:.6px;text-transform:uppercase;color:#64748b;font-weight:600;margin-bottom:4px">Attendance %</div>
          <div style="font-size:1.3rem;font-weight:700;color:<?= $attPct >= 75 ? '#15803d' : ($attPct >= 60 ? '#d97706' : '#dc2626') ?>"><?= $attPct ?>%</div>
        </td>
      </tr>
    </tbody>
  </table>
  <?php endif; ?>

  <?php endif; /* end !$montessoriLowGrade — subject table + attendance shown only for exam-based classes */ ?>

  <!-- ── Grade scale ────────────────────────────────────── -->
  <?php if (!$montessoriLowGrade): ?>
  <div class="rc-gscale">
    <strong>Grade Scale:</strong>
    <?php foreach (gradingRubric() as [$gr, $rng, $desc, $_]): ?>
    <span class="rc-gs-item">
      <span class="rc-gr" style="background:<?= gradeBg($gr) ?>;color:<?= gradeColor($gr) ?>;padding:1px 7px;font-size:.63rem"><?= $gr ?></span>
      <span class="rc-gs-rng"><?= $rng ?> &mdash; <?= $desc ?></span>
    </span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ── Bottom sections: VP · Teacher · Parent ───────────────────────────
       Three equal columns at the bottom of the report card.
       - Vice Principal: name/title + signature space only, no remarks field.
       - Teacher: aggregated teacher remarks + signature space; editable in
         ?edit=1 mode by authorized teachers/admin via save_teacher_remark.
       - Parent: blank remarks box + signature space; no portal entry.
       Class Teacher remarks are NOT displayed anywhere else — only here. ─ -->
  <?php
    // Collect all per-assessment teacher remarks for display inside the Teacher
    // section. Edit mode exposes every (subject, assessment) pair so teachers
    // can add remarks where none exist yet.
    $teacherRemarkEntries = [];      // [assessment_id => ['subject','label','remark']]
    foreach ($bySubject as $sid => $sub) {
        foreach ($sub['assessments'] as $a) {
            $aid = (int)$a['assessment_id'];
            if ($aid === 0) continue;
            $label = trim(($a['assessment_name'] ?: $a['type']) . ($a['type'] && $a['assessment_name'] && $a['type'] !== $a['assessment_name'] ? ' ('.$a['type'].')' : ''));
            if ($editMode || !empty($a['remarks'])) {
                if (!isset($teacherRemarkEntries[$aid])) {
                    $teacherRemarkEntries[$aid] = [
                        'subject' => $sub['name'],
                        'label'   => $label,
                        'remark'  => $a['remarks'] ?? '',
                    ];
                }
            }
        }
    }
    $editQS = ($role !== 'student' ? 'student_id='.$studentId.'&' : '')
            . ($typeFilter !== '' ? 'type='.urlencode($typeFilter).'&' : '');
  ?>

  <?php if (!$isWordExport && $canEditTeacherRemarks): ?>
  <div class="no-print" style="padding:8px 14px;margin-bottom:8px;background:#fef9c3;border:1px solid #fde68a;border-radius:6px;font-size:.82rem;display:flex;gap:12px;align-items:center;justify-content:space-between">
    <div>
      <i class="fas fa-pencil-alt me-1" style="color:#854d0e"></i>
      <?php if ($editMode): ?>
        <strong>Edit mode:</strong> teacher-remark fields in the bottom Teacher section are editable. Click Save on each to persist.
      <?php else: ?>
        <strong>Need to edit teacher remarks?</strong> Switch to edit mode to add or update your remarks in the Teacher section below.
      <?php endif; ?>
    </div>
    <div>
      <?php if ($editMode): ?>
      <a href="?<?= $editQS ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times me-1"></i>Done</a>
      <?php else: ?>
      <a href="?<?= $editQS ?>edit=1" class="btn btn-sm btn-warning"><i class="fas fa-pencil-alt me-1"></i>Edit Remarks</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <style>
    .rc-bottom-sec { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-top:16px; }
    @media (max-width: 720px) { .rc-bottom-sec { grid-template-columns:1fr; } }
    .rc-bsec { border:1px solid #d1d5db; border-radius:6px; background:#fff; display:flex; flex-direction:column; min-height:170px; }
    .rc-bsec-h { background:#f3f4f6; border-bottom:1px solid #d1d5db; padding:6px 10px; font-size:.74rem;
                 font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:#1e293b;
                 border-radius:6px 6px 0 0; }
    .rc-bsec-b { padding:8px 10px; flex:1; font-size:.82rem; color:#334155; }
    .rc-bsec-sig { border-top:1px dashed #cbd5e1; padding:6px 10px; font-size:.72rem;
                   color:#475569; display:flex; flex-direction:column; align-items:center;
                   justify-content:flex-end; min-height:70px; }
    .rc-bsec-line { width:85%; border-bottom:1px solid #374151; margin-bottom:4px; min-height:22px; }
    .rc-bsec-nm { font-weight:600; color:#1e293b; }
    .rc-bsec-sub { font-size:.68rem; color:#64748b; margin-top:1px; }
    .rc-bsec-empty { color:#9ca3af; font-style:italic; font-size:.78rem; }
    .rc-bsec-row { padding:4px 0; border-bottom:1px dashed #e5e7eb; }
    .rc-bsec-row:last-child { border-bottom:none; }
    .rc-bsec-row-sub { font-weight:600; color:#1e293b; font-size:.78rem; }
    .rc-bsec-row-lbl { color:#64748b; font-size:.72rem; }
    .rc-bsec-row-txt { color:#334155; margin-top:1px; font-size:.80rem; }
  </style>

  <div class="rc-bottom-sec">
    <!-- Vice Principal: signature only — label only, no name, no sub-text -->
    <div class="rc-bsec">
      <div class="rc-bsec-h"><i class="fas fa-stamp me-1" style="opacity:.6"></i>Vice Principal</div>
      <div class="rc-bsec-b"></div>
      <div class="rc-bsec-sig">
        <div class="rc-bsec-line"></div>
        <div class="rc-bsec-nm">Vice Principal</div>
      </div>
    </div>

    <!-- Teacher: remarks (editable) + signature -->
    <div class="rc-bsec">
      <div class="rc-bsec-h"><i class="fas fa-comment-dots me-1" style="opacity:.6"></i>Teacher Remarks</div>
      <div class="rc-bsec-b">
        <?php if (!empty($teacherRemarkEntries)): ?>
          <?php foreach ($teacherRemarkEntries as $aid => $tr): ?>
          <div class="rc-bsec-row">
            <div><span class="rc-bsec-row-sub"><?= h($tr['subject']) ?></span>
              <?php if (!empty($tr['label'])): ?><span class="rc-bsec-row-lbl"> · <?= h($tr['label']) ?></span><?php endif; ?>
            </div>
            <?php if ($editMode && $canEditTeacherRemarks): ?>
            <form method="POST" class="no-print d-flex gap-1 align-items-start mt-1">
              <input type="hidden" name="action" value="save_teacher_remark">
              <input type="hidden" name="assessment_id" value="<?= (int)$aid ?>">
              <textarea name="remark" rows="2" maxlength="255" class="form-control form-control-sm" style="flex:1;font-size:.78rem"
                        placeholder="Teacher remark…"><?= h($tr['remark']) ?></textarea>
              <button type="submit" class="btn btn-sm btn-primary" style="font-size:.72rem;white-space:nowrap"><i class="fas fa-save"></i></button>
            </form>
            <?php else: ?>
            <div class="rc-bsec-row-txt"><?= $tr['remark'] !== '' ? h($tr['remark']) : '<span class="rc-bsec-empty">— no remark —</span>' ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="rc-bsec-empty">No remarks recorded by subject teachers.</div>
        <?php endif; ?>
      </div>
      <div class="rc-bsec-sig">
        <div class="rc-bsec-line"></div>
        <div class="rc-bsec-nm">Teacher</div>
      </div>
    </div>

    <!-- Parent: blank remarks space + signature (no portal entry) -->
    <div class="rc-bsec">
      <div class="rc-bsec-h"><i class="fas fa-user-friends me-1" style="opacity:.6"></i>Parent Remarks</div>
      <div class="rc-bsec-b">
        <div class="rc-rem-line" style="margin-top:6px"></div>
        <div class="rc-rem-line" style="margin-top:14px"></div>
        <div class="rc-rem-line" style="margin-top:14px"></div>
      </div>
      <div class="rc-bsec-sig">
        <div class="rc-bsec-line"></div>
        <div class="rc-bsec-nm">Parents</div>
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

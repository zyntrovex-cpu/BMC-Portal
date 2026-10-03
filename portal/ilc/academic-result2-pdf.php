<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp','admin','student_affairs','vp_main','wing_head','student');
$db   = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/ilc/academic-result2.php'); exit; }

$st = $db->prepare(
    'SELECT r.*, u.name AS student_name, u2.name AS recorder_name,
            s.roll_no, s.id AS sid, c.name AS class_name
     FROM ilc_academic_results2 r
     JOIN students s  ON s.id  = r.student_id
     JOIN users u     ON u.id  = s.user_id
     JOIN users u2    ON u2.id = r.recorded_by
     LEFT JOIN classes c ON c.id = s.class_id
     WHERE r.id = ?'
);
$st->execute([$id]);
$rec = $st->fetch();

if (!$rec) { http_response_code(404); echo '<p style="font-family:sans-serif;padding:40px">Result not found.</p>'; exit; }

if ($user['role'] === 'student') {
    $myRow = $db->prepare('SELECT id FROM students WHERE user_id=?');
    $myRow->execute([$user['id']]);
    $mine = $myRow->fetch();
    if (!$mine || (int)$mine['id'] !== (int)$rec['sid']) {
        http_response_code(403); echo '<p style="font-family:sans-serif;padding:40px">Access denied.</p>'; exit;
    }
}

$fd      = json_decode($rec['form_data'], true) ?? [];
$fdBasic = $fd['basic']    ?? [];
$fdSubj  = $fd['subjects'] ?? [];
$fdSkill = $fd['skills']   ?? [];
$fdLevel = $fd['levels']   ?? [];
$fdRem   = $fd['remarks']  ?? '';

$SKILLS_PAIRS = [
    ['motor_skills','Motor skills',                'eye_hand','Eye & hand coordination'],
    ['psycho_cognitive','Psycho cognitive',         'attention_span','Attention span / concentration'],
    ['behavior','Behavior',                         'auditory_skills','Auditory skills'],
    ['speech_improvement','Speech improvement',     'vegetative_skills','Vegetative skills'],
    ['appearance','Appearance',                     'object_use','Object use'],
    ['health_hygiene','Health & hygiene',           'linguistic_skills','Linguistic skills'],
    ['class_activity','Participation in class activity','imitation_skills','Imitation skills'],
];

$ASSESSMENT_LEVELS = [
    'exceeding' => 'Exceeding',
    'expected'  => 'Expected',
    'emerging'  => 'Emerging',
    'not_yet'   => 'Not yet',
];

function pdfGrade2(string $g): string {
    if ($g === '') return '<span style="color:#94a3b8">—</span>';
    $map = ['A+'=>'#166534','A'=>'#166534','B'=>'#1e40af','C'=>'#92400e','D'=>'#991b1b','N.A.'=>'#64748b'];
    $c   = $map[$g] ?? '#374151';
    return "<strong style='color:$c;font-size:.9rem'>$g</strong>";
}

$term    = $rec['term'];
$session = $rec['session'];
$logoBase= defined('BASE_URL') ? BASE_URL : '';

$totMax = array_sum(array_column($fdSubj, 'max_marks'));
$totObt = array_sum(array_column($fdSubj, 'obtained'));
$totPct = ($totMax > 0) ? round(($totObt / $totMax) * 100) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Progress Report — <?= h($rec['student_name']) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI',Arial,sans-serif; font-size:10pt; color:#111; background:#fff; }
@page { size:A4; margin:9mm 12mm; }
@media print {
  body { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
  .no-print { display:none; }
  .page-break { page-break-before:always; }
}
.hdr-wrap { display:flex; align-items:center; justify-content:space-between; border-bottom:2px solid #14532d; padding-bottom:6px; margin-bottom:8px; }
.hdr-logos { display:flex; gap:8px; align-items:center; }
.hdr-logos img { width:44px; height:44px; object-fit:contain; }
.hdr-center { flex:1; text-align:center; }
.hdr-school  { font-size:13pt; font-weight:800; color:#14532d; letter-spacing:.5px; }
.hdr-wing    { font-size:9pt; font-weight:700; color:#374151; letter-spacing:.3px; }
.hdr-term    { font-size:8.5pt; color:#475569; margin-top:2px; }
.stu-info    { display:flex; gap:20px; background:#f0fdf4; padding:5px 10px; border-radius:5px; margin-bottom:8px; font-size:8.5pt; }
.sec-title   { color:#fff; font-size:8pt; font-weight:700; text-transform:uppercase;
               letter-spacing:.5px; padding:3px 8px; border-radius:3px 3px 0 0; }
table { width:100%; border-collapse:collapse; margin-bottom:7px; font-size:8.5pt; }
table.sec-table { border:1px solid #cbd5e1; }
table.sec-table td, table.sec-table th { border:1px solid #cbd5e1; padding:3px 7px; vertical-align:middle; }
table.sec-table th { background:#f1f5f9; font-weight:600; }
.grade-cell { text-align:center; width:52px; }
.sig-row { display:flex; justify-content:space-between; margin-top:14px; padding-top:10px; border-top:1px solid #cbd5e1; }
.sig-box { text-align:center; min-width:110px; }
.sig-line { border-bottom:1px solid #374151; width:90px; margin:20px auto 3px; }
.sig-lbl { font-size:7.5pt; color:#475569; }
.grade-key { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:8px; font-size:7.5pt; color:#374151; }
.print-btn { position:fixed; top:16px; right:16px; background:#14532d; color:#fff; border:none; padding:8px 18px;
             border-radius:6px; cursor:pointer; font-size:13px; z-index:999; }
.remarks-box { border:1px solid #cbd5e1; border-top:none; padding:7px 10px; font-size:8.5pt; min-height:40px; background:#fafafa; }
</style>
</head>
<body>
<button class="print-btn no-print" onclick="window.print()">Print / Save PDF</button>

<!-- Header -->
<div class="hdr-wrap">
  <div class="hdr-logos">
    <img src="<?= $logoBase ?>/assets/pak-logo.png" alt="" onerror="this.style.display='none'">
  </div>
  <div class="hdr-center">
    <div class="hdr-school">BAHRIA MODEL COLLEGE BIN QASIM</div>
    <div class="hdr-wing">SPECIAL CHILDREN&rsquo;S WING — INCLUSIVE LEARNING CENTRE</div>
    <div class="hdr-term">TERM PROGRESS REPORT<?= $session?' — SESSION '.h($session):'' ?></div>
  </div>
  <div class="hdr-logos">
    <img src="<?= $logoBase ?>/assets/ilc-logo.png" alt="" onerror="this.style.display='none'">
  </div>
</div>

<!-- Student info -->
<div class="stu-info">
  <span><strong>Name:</strong> <?= h($rec['student_name']) ?></span>
  <span><strong>Class:</strong> <?= h($rec['class_name']??'—') ?></span>
  <span><strong>Roll No:</strong> <?= h($rec['roll_no']??'—') ?></span>
  <?php if (!empty($fdBasic['gr_no'])): ?>
  <span><strong>GR No:</strong> <?= h($fdBasic['gr_no']) ?></span>
  <?php endif; ?>
  <?php if (!empty($fdBasic['teacher_name'])): ?>
  <span><strong>Teacher:</strong> <?= h($fdBasic['teacher_name']) ?></span>
  <?php endif; ?>
  <?php if (!empty($fdBasic['attendance'])): ?>
  <span><strong>Attendance:</strong> <?= h($fdBasic['attendance']) ?> days</span>
  <?php endif; ?>
  <?php if (!empty($fdBasic['category'])): ?>
  <span><strong>Category:</strong> <?= h($fdBasic['category']) ?></span>
  <?php endif; ?>
  <span><strong>Term:</strong> <?= h($term) ?></span>
</div>

<!-- Grade key -->
<div class="grade-key">
  <strong>Grade Key:</strong>
  <span><strong style="color:#166534">A+</strong> Outstanding (85%+)</span>
  <span><strong style="color:#15803d">A</strong> Excellent (70%+)</span>
  <span><strong style="color:#1e40af">B</strong> Good (50%+)</span>
  <span><strong style="color:#92400e">C</strong> Satisfactory (40%+)</span>
  <span><strong style="color:#991b1b">D</strong> Needs Improvement (&lt;40%)</span>
  <span><strong style="color:#64748b">N.A.</strong> Not Assessed</span>
</div>

<!-- Academic Subjects -->
<div class="sec-title" style="background:#065f46">Academic Subjects</div>
<table class="sec-table">
  <thead><tr>
    <th style="width:38%">Subject</th>
    <th class="grade-cell">Max Marks</th>
    <th class="grade-cell">Obtained</th>
    <th class="grade-cell">Percentage</th>
    <th class="grade-cell">Grade</th>
  </tr></thead>
  <tbody>
  <?php foreach ($fdSubj as $row):
    if ($row['subject'] === '' && $row['max_marks'] === '' && $row['obtained'] === '') continue; ?>
  <tr>
    <td><?= h($row['subject']) ?></td>
    <td class="grade-cell"><?= h($row['max_marks']!=='' ? $row['max_marks'] : '—') ?></td>
    <td class="grade-cell"><?= h($row['obtained']!=='' ? $row['obtained'] : '—') ?></td>
    <td class="grade-cell"><?= $row['percentage']!=='' ? h($row['percentage']).'%' : '—' ?></td>
    <td class="grade-cell"><?= pdfGrade2($row['grade']??'') ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if ($totMax > 0): ?>
  <tr style="background:#f0fdf4;font-weight:700">
    <td>Total</td>
    <td class="grade-cell"><?= number_format($totMax, 1) ?></td>
    <td class="grade-cell"><?= number_format($totObt, 1) ?></td>
    <td class="grade-cell"><?= $totPct !== null ? $totPct.'%' : '—' ?></td>
    <td class="grade-cell"></td>
  </tr>
  <?php endif; ?>
  </tbody>
</table>

<!-- Skills Assessment -->
<div class="sec-title" style="background:#1e3a5f">Skills Assessment</div>
<table class="sec-table">
  <thead><tr>
    <th style="width:38%">Skill</th>
    <th class="grade-cell">Grade</th>
    <th style="width:38%">Skill</th>
    <th class="grade-cell">Grade</th>
  </tr></thead>
  <tbody>
  <?php foreach ($SKILLS_PAIRS as [$k1,$l1,$k2,$l2]): ?>
  <tr>
    <td><?= h($l1) ?></td>
    <td class="grade-cell"><?= pdfGrade2($fdSkill[$k1]??'') ?></td>
    <td><?= h($l2) ?></td>
    <td class="grade-cell"><?= pdfGrade2($fdSkill[$k2]??'') ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<!-- Assessment Levels -->
<div class="sec-title" style="background:#7c3aed">Assessment Levels</div>
<table class="sec-table">
  <thead><tr>
    <th style="width:60%">Level</th>
    <th class="grade-cell">Grade</th>
  </tr></thead>
  <tbody>
  <?php foreach ($ASSESSMENT_LEVELS as $lk => $ll): ?>
  <tr>
    <td><strong><?= h($ll) ?></strong></td>
    <td class="grade-cell"><?= pdfGrade2($fdLevel[$lk]??'') ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<!-- Teacher's Remarks -->
<div class="sec-title" style="background:#374151">Teacher's Remarks</div>
<div class="remarks-box"><?= h($fdRem) ?: '<span style="color:#94a3b8">—</span>' ?></div>

<!-- Signatures -->
<div class="sig-row">
  <div class="sig-box"><div class="sig-line"></div><div class="sig-lbl">Teacher's Signature</div></div>
  <div class="sig-box"><div class="sig-line"></div><div class="sig-lbl">Wing Head's Signature</div></div>
  <div class="sig-box"><div class="sig-line"></div><div class="sig-lbl">Parent's Signature</div></div>
  <div class="sig-box"><div class="sig-line"></div><div class="sig-lbl">Principal's Signature</div></div>
</div>

<script>window.addEventListener('load',function(){ window.print(); });</script>
</body></html>

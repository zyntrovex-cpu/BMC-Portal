<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp','admin','student_affairs','vp_main','wing_head','student');
$db   = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/ilc/academic-result1.php'); exit; }

$st = $db->prepare(
    'SELECT r.*, u.name AS student_name, u2.name AS recorder_name,
            s.roll_no, s.id AS sid, c.name AS class_name
     FROM ilc_academic_results r
     JOIN students s  ON s.id  = r.student_id
     JOIN users u     ON u.id  = s.user_id
     JOIN users u2    ON u2.id = r.recorded_by
     LEFT JOIN classes c ON c.id = s.class_id
     WHERE r.id = ?'
);
$st->execute([$id]);
$rec = $st->fetch();

if (!$rec) { http_response_code(404); echo '<p style="font-family:sans-serif;padding:40px">Result not found.</p>'; exit; }

// Students can only view their own
if ($user['role'] === 'student') {
    $myRow = $db->prepare('SELECT id FROM students WHERE user_id=?');
    $myRow->execute([$user['id']]);
    $mine = $myRow->fetch();
    if (!$mine || (int)$mine['id'] !== (int)$rec['sid']) {
        http_response_code(403); echo '<p style="font-family:sans-serif;padding:40px">Access denied.</p>'; exit;
    }
}

$fd      = json_decode($rec['form_data'], true) ?? [];
$fdBasic = $fd['basic']      ?? [];
$fdAtt   = $fd['attendance'] ?? [];

$SECS = [
    'personal_social'=>['title'=>'Personal & Social Development','pairs'=>true,'fields'=>[
        'takes_pride'          =>'Takes pride in own achievement',
        'adapts_new_tasks'     =>'Adapts to new tasks',
        'follows_rules'        =>'Follows rules in group games',
        'takes_turns'          =>'Takes turns',
        'contributes_class'    =>'Contributes to class stories or daily news',
        'takes_care_belongings'=>'Takes care of belongings',
        'shares_ideas'         =>'Shares ideas & materials',
        'takes_care_hygiene'   =>'Takes care of personal hygiene',
        'ability_express'      =>'Ability to express',
        'positive_relationship'=>'Displays positive relationship with peers',
        'positive_self_image'  =>'Displays positive self image',
        'shows_self_control'   =>'Shows self control',
    ]],
    'english'    =>['title'=>'English',    'fields'=>['reads_phonetically'=>'Reads phonetically','forms_letters'=>'Forms letters correctly','carries_conversation'=>'Carries on conversation','participates_roleplay'=>'Participates in role-play']],
    'urdu'       =>['title'=>'Urdu',       'fields'=>['speaks_clearly'=>'Speaks clearly','listens_attentively'=>'Listens attentively to stories','written_presentation'=>'Presentation of written work']],
    'islamiat'   =>['title'=>'Islamiat',   'fields'=>['level_interest'=>'Level of interest','retention_duas'=>'Retention of duas/surahs','comprehension'=>'Comprehension','recitation'=>'Recitation']],
    'mathematics'=>['title'=>'Mathematics','fields'=>['counts_objects'=>'Counts objects up to','recites_numbers'=>'Recites number names up to','recognizes_patterns'=>'Recognizes & repeats patterns','one_more_less'=>'Knows one more & one less']],
    'physical_ed'=>['title'=>'Physical Education','fields'=>['individual_play'=>'Participates in individual play','shows_interest'=>'Shows interest','team_games'=>'Participates in team games','displays_agility'=>'Displays agility']],
    'pbl'        =>['title'=>'Project Based Learning','fields'=>['participation'=>'Participation','content_knowledge'=>'Content knowledge','interest'=>'Interest','presentation_skills'=>'Presentation skills']],
    'story_time' =>['title'=>'Story Time', 'fields'=>['concentration_span'=>'Concentration span','oral_discussion'=>'Participates in oral discussion','recalls_sequence'=>'Recalls the story sequence','describes_pictures'=>'Describes pictures']],
    'ict'        =>['title'=>'ICT',        'fields'=>['mouse_control'=>'Mouse control skills','uses_cursor'=>'Uses cursor appropriately','follows_instructions'=>'Follows instructions']],
    'world_around_us'=>['title'=>'Knowledge & Understanding of the World Around Us','fields'=>['explores_environment'=>'Explores environment','exhibits_curiosity'=>'Exhibits curiosity','observe_investigates'=>'Observe and investigates','asks_question'=>'Asks relevant question']],
    'art'        =>['title'=>'Art',        'fields'=>['imagination'=>'Imagination','drawing_coloring'=>'Drawing/coloring','creativity'=>'Creativity','hand_craftwork'=>'Hand & craftwork']],
    'music'      =>['title'=>'Music',      'fields'=>['shows_interest'=>'Show interest','coordinates_music'=>'Coordinates with music']],
];

function pdfGrade(string $g): string {
    if ($g === '') return '<span style="color:#94a3b8">—</span>';
    $map = ['A+'=>'#166534','A'=>'#166534','B'=>'#1e40af','C'=>'#92400e','D'=>'#991b1b','N.A.'=>'#64748b'];
    $c   = $map[$g] ?? '#374151';
    return "<strong style='color:$c;font-size:.9rem'>$g</strong>";
}

$term    = $rec['term'];
$session = $rec['session'];
$logoBase= defined('BASE_URL') ? BASE_URL : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Academic Result — <?= h($rec['student_name']) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI',Arial,sans-serif; font-size:10pt; color:#111; background:#fff; }
@page { size:A4; margin:9mm 12mm; }
@media print {
  body { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
  .no-print { display:none; }
  .page-break { page-break-before:always; }
}
.hdr-wrap { display:flex; align-items:center; justify-content:space-between; border-bottom:2px solid #1e3a5f; padding-bottom:6px; margin-bottom:8px; }
.hdr-logos { display:flex; gap:8px; align-items:center; }
.hdr-logos img { width:44px; height:44px; object-fit:contain; }
.hdr-center { flex:1; text-align:center; }
.hdr-school  { font-size:13pt; font-weight:800; color:#1e3a5f; letter-spacing:.5px; }
.hdr-wing    { font-size:9pt; font-weight:700; color:#374151; letter-spacing:.3px; }
.hdr-term    { font-size:8.5pt; color:#475569; margin-top:2px; }
.stu-info    { display:flex; gap:20px; background:#eff6ff; padding:5px 10px; border-radius:5px; margin-bottom:8px; font-size:8.5pt; }
.stu-info span { }
.sec-title   { background:#1e3a5f; color:#fff; font-size:8pt; font-weight:700; text-transform:uppercase;
               letter-spacing:.5px; padding:3px 8px; border-radius:3px 3px 0 0; }
table { width:100%; border-collapse:collapse; margin-bottom:7px; font-size:8.5pt; }
table.sec-table { border:1px solid #cbd5e1; }
table.sec-table td, table.sec-table th { border:1px solid #cbd5e1; padding:3px 7px; vertical-align:middle; }
table.sec-table th { background:#f1f5f9; font-weight:600; }
.grade-cell { text-align:center; width:52px; }
.att-table td { padding:4px 8px; }
.sig-row { display:flex; justify-content:space-between; margin-top:16px; padding-top:10px; border-top:1px solid #cbd5e1; }
.sig-box { text-align:center; min-width:110px; }
.sig-line { border-bottom:1px solid #374151; width:90px; margin:20px auto 3px; }
.sig-lbl { font-size:7.5pt; color:#475569; }
.grade-key { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:8px; font-size:7.5pt; color:#374151; }
.grade-key-item strong { margin-right:2px; }
.print-btn { position:fixed; top:16px; right:16px; background:#1e3a5f; color:#fff; border:none; padding:8px 18px;
             border-radius:6px; cursor:pointer; font-size:13px; z-index:999; }
</style>
</head>
<body>
<button class="print-btn no-print" onclick="window.print()"><i class="fas fa-print me-1"></i>Print / Save PDF</button>

<!-- Header -->
<div class="hdr-wrap">
  <div class="hdr-logos">
    <img src="<?= $logoBase ?>/assets/pak-logo.png" alt="" onerror="this.style.display='none'">
  </div>
  <div class="hdr-center">
    <div class="hdr-school">BAHRIA MODEL COLLEGE BIN QASIM</div>
    <div class="hdr-wing">SPECIAL CHILDREN&rsquo;S WING — INCLUSIVE LEARNING CENTRE</div>
    <div class="hdr-term"><?= h($term) ?><?= $session?' — SESSION '.h($session):'' ?></div>
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
  <?php if (!empty($fdAtt['student_age'])): ?>
  <span><strong>Age:</strong> <?= h($fdAtt['student_age']) ?></span>
  <?php endif; ?>
  <?php if (!empty($fdAtt['class_avg_age'])): ?>
  <span><strong>Class Avg Age:</strong> <?= h($fdAtt['class_avg_age']) ?></span>
  <?php endif; ?>
</div>

<!-- Grade key -->
<div class="grade-key">
  <strong>Grade Key:</strong>
  <span><strong style="color:#166534">A+</strong> Outstanding</span>
  <span><strong style="color:#15803d">A</strong> Excellent</span>
  <span><strong style="color:#1e40af">B</strong> Good</span>
  <span><strong style="color:#92400e">C</strong> Satisfactory</span>
  <span><strong style="color:#991b1b">D</strong> Needs Improvement</span>
  <span><strong style="color:#64748b">N.A.</strong> Not Assessed</span>
</div>

<?php foreach ($SECS as $sk => $sec):
  $secFd  = $fd[$sk] ?? [];
  $isPairs= !empty($sec['pairs']);
?>
<div class="sec-title"><?= h($sec['title']) ?></div>
<?php if ($isPairs):
  $pairs = array_chunk(array_keys($sec['fields']), 2);
?>
<table class="sec-table">
  <thead><tr>
    <th>Skill / Competency</th>
    <th class="grade-cell">Grade</th>
    <th>Skill / Competency</th>
    <th class="grade-cell">Grade</th>
  </tr></thead>
  <tbody>
  <?php foreach ($pairs as $pair):
    [$k1, $k2] = array_pad($pair, 2, null); ?>
  <tr>
    <td><?= h($sec['fields'][$k1]) ?></td>
    <td class="grade-cell"><?= pdfGrade($secFd[$k1]??'') ?></td>
    <td><?= $k2 ? h($sec['fields'][$k2]) : '' ?></td>
    <td class="grade-cell"><?= $k2 ? pdfGrade($secFd[$k2]??'') : '' ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?>
<table class="sec-table">
  <thead><tr>
    <th>Skill / Competency</th>
    <th class="grade-cell">Grade</th>
  </tr></thead>
  <tbody>
  <?php foreach ($sec['fields'] as $fk => $flabel): ?>
  <tr>
    <td><?= h($flabel) ?></td>
    <td class="grade-cell"><?= pdfGrade($secFd[$fk]??'') ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php endforeach; ?>

<!-- Attendance -->
<div class="sec-title">Attendance</div>
<table class="sec-table att-table">
  <tbody>
  <tr>
    <td><strong>Total Working Days:</strong> <?= h($fdAtt['working_days']??'—') ?></td>
    <td><strong>Days Present:</strong> <?= h($fdAtt['days_present']??'—') ?></td>
    <td><strong>Days Absent:</strong> <?= h($fdAtt['days_absent']??'—') ?></td>
    <td><strong>Punctuality:</strong> <?= h($fdAtt['punctuality']??'—') ?></td>
  </tr>
  </tbody>
</table>

<!-- Signatures -->
<div class="sig-row">
  <div class="sig-box"><div class="sig-line"></div><div class="sig-lbl">Teacher's Signature</div></div>
  <div class="sig-box"><div class="sig-line"></div><div class="sig-lbl">Wing Head's Signature</div></div>
  <div class="sig-box"><div class="sig-line"></div><div class="sig-lbl">Parent's Signature</div></div>
  <div class="sig-box"><div class="sig-line"></div><div class="sig-lbl">Principal's Signature</div></div>
</div>

<script>window.addEventListener('load',function(){ window.print(); });</script>
</body></html>

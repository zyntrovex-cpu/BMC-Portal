<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp', 'admin', 'student_affairs', 'vp_main', 'wing_head', 'student');
$db   = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); echo '<p>Missing report ID.</p>'; exit; }

$st = $db->prepare(
    'SELECT r.*, s.roll_no, u_s.name AS student_name, c.name AS class_name,
            u_t.name AS therapist_name
     FROM speech_therapy_reports r
     JOIN students s ON s.id = r.student_id
     JOIN users u_s ON u_s.id = s.user_id
     JOIN classes c ON c.id = s.class_id
     JOIN users u_t ON u_t.id = r.recorded_by
     WHERE r.id = ?'
);
$st->execute([$id]);
$report = $st->fetch();
if (!$report) { http_response_code(404); echo '<p>Assessment not found.</p>'; exit; }

// Students can only access their own
if ($user['role'] === 'student') {
    $ms = $db->prepare('SELECT id FROM students WHERE user_id = ?');
    $ms->execute([$user['id']]);
    $myRow = $ms->fetch();
    if (!$myRow || (int)$myRow['id'] !== (int)$report['student_id']) {
        http_response_code(403);
        echo '<p style="font-family:sans-serif;padding:40px;color:#dc2626">Access denied.</p>';
        exit;
    }
}

$ad = (!empty($report['assessment_data'])) ? (json_decode($report['assessment_data'], true) ?? []) : [];
$s1 = $ad['s1'] ?? []; $s2 = $ad['s2'] ?? []; $s3 = $ad['s3'] ?? [];
$s4 = $ad['s4'] ?? []; $s5 = $ad['s5'] ?? []; $s6 = $ad['s6'] ?? [];
$s7 = $ad['s7'] ?? []; $s8 = $ad['s8'] ?? []; $s9 = $ad['s9'] ?? [];

$s3Labels = [
    'receptive_language'      => 'Receptive Language',
    'expressive_language'     => 'Expressive Language',
    'vocabulary'              => 'Vocabulary',
    'following_instructions'  => 'Following Instructions',
    'functional_communication'=> 'Functional Communication',
    'articulation_speech'     => 'Articulation / Speech Sounds',
    'oral_motor'              => 'Oral-Motor Skills',
    'fluency'                 => 'Fluency',
    'voice_vocal'             => 'Voice / Vocal Quality',
    'pragmatic_social'        => 'Pragmatic / Social Communication',
    'eye_contact'             => 'Eye Contact / Joint Attention',
    'imitation_turn'          => 'Imitation / Turn Taking',
];
$s5Labels = [
    'lip_closure'    => 'Lip Closure / Movement',
    'tongue_movement'=> 'Tongue Movement',
    'jaw_stability'  => 'Jaw Stability / Movement',
    'breath_control' => 'Breath Control / Blowing',
    'imitation_oral' => 'Imitation of Oral Movements',
];
$s6Labels = [
    'requesting_needs'         => 'Requesting Needs',
    'answering_questions'      => 'Answering Questions',
    'initiating_communication' => 'Initiating Communication',
    'maintaining_interaction'  => 'Maintaining Interaction',
    'gestures_aac'             => 'Use of Gestures / AAC (if applicable)',
];

function pdfChk(bool $checked): string {
    return $checked
        ? '<span style="font-family:\'Segoe UI Symbol\',Arial;color:#0891b2">☑</span>'
        : '<span style="font-family:\'Segoe UI Symbol\',Arial;color:#94a3b8">☐</span>';
}
function pdfVal(string $v): string { return h($v) ?: '<span style="color:#94a3b8;font-style:italic">—</span>'; }
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Speech &amp; Language Therapy Assessment — <?= h($report['student_name']) ?></title>
<style>
@page { size: A4; margin: 9mm 12mm; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; font-size: 9.5pt; color: #1e293b; background: #fff; }
@media print { .no-print { display: none !important; } body { background: #fff; } }

/* Toolbar */
.toolbar { position: fixed; top: 0; left: 0; right: 0; background: #0891b2; color: #fff;
           padding: 10px 20px; display: flex; align-items: center; gap: 16px; z-index: 999; }
.toolbar h2 { font-size: 1rem; flex: 1; }
.toolbar button { background: #fff; color: #0891b2; border: none; padding: 6px 16px;
                  border-radius: 6px; font-weight: 700; cursor: pointer; font-size: .88rem; }
.page-body { padding-top: 56px; }
@media print { .page-body { padding-top: 0; } }

/* Header */
.doc-header { display: flex; justify-content: space-between; align-items: center;
              border-bottom: 2.5pt solid #0891b2; padding-bottom: 8px; margin-bottom: 12px; }
.doc-header .logo-side { display: flex; align-items: center; gap: 10px; }
.doc-header img { width: 48px; height: 48px; object-fit: contain; }
.doc-header .title-block h1 { font-size: 11pt; font-weight: 800; color: #0891b2; }
.doc-header .title-block p { font-size: 7.5pt; color: #475569; }

/* Section headings */
.sec-h { background: #0891b2; color: #fff; font-size: 8.5pt; font-weight: 700;
          letter-spacing: .4px; padding: 4px 10px; border-radius: 4px;
          margin-top: 10px; margin-bottom: 7px; text-transform: uppercase; }

/* Field rows */
.field-row { display: flex; gap: 6px; margin-bottom: 5px; flex-wrap: wrap; }
.field-item { flex: 1; min-width: 140px; }
.field-label { font-size: 7pt; text-transform: uppercase; letter-spacing: .3px;
               color: #64748b; margin-bottom: 2px; }
.field-value { font-size: 9pt; border-bottom: .8pt solid #cbd5e1; padding-bottom: 2px;
               min-height: 16px; }

/* Tables */
table { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-bottom: 6px; }
th { background: #e0f2fe; border: .8pt solid #93c5fd; padding: 4px 5px;
     text-align: center; font-weight: 700; font-size: 8pt; }
td { border: .8pt solid #bfdbfe; padding: 4px 5px; vertical-align: top; }
td.area-col { font-weight: 500; width: 33%; }
td.chk-col { text-align: center; width: 12%; }
td.left-h { font-weight: 500; background: #f0f9ff; width: 30%; }

/* Radio-style option cells */
.opt-row { display: flex; gap: 14px; align-items: center; }
.opt-item { display: flex; align-items: center; gap: 3px; font-size: 8.5pt; }

/* Signature block */
.sig-row { display: flex; gap: 20px; margin-top: 14px; }
.sig-item { flex: 1; border-top: .8pt solid #334155; padding-top: 4px;
            font-size: 8pt; color: #475569; text-align: center; }

/* Confidential */
.conf-footer { text-align: center; font-size: 7pt; color: #94a3b8;
               border-top: .8pt solid #e2e8f0; margin-top: 10px; padding-top: 5px; }

.page-break { page-break-before: always; }
.pre-val { white-space: pre-wrap; font-family: Arial, sans-serif; font-size: 9pt;
           line-height: 1.4; border-bottom: .8pt solid #cbd5e1; padding-bottom: 2px; min-height: 18px; }
</style>
</head>
<body>

<div class="no-print toolbar">
  <h2>Speech &amp; Language Therapy Assessment — <?= h($report['student_name']) ?></h2>
  <button onclick="window.print()">⬇ Print / Download PDF</button>
  <button onclick="history.back()" style="background:rgba(255,255,255,.2);color:#fff">← Back</button>
</div>

<div class="page-body">

<!-- Document Header -->
<div class="doc-header">
  <div class="logo-side">
    <img src="<?= url('/assets/pak-logo.png') ?>" alt="Pak" onerror="this.style.display='none'">
    <div class="title-block">
      <h1>INCLUSIVE LEARNING CENTRE (ILC)</h1>
      <p>SPEECH &amp; LANGUAGE THERAPY ASSESSMENT FORM</p>
      <p style="color:#0891b2;font-size:7pt">Bahria Model College Bin Qasim</p>
    </div>
  </div>
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC" style="width:52px;height:52px;object-fit:contain"
       onerror="this.style.display='none'">
</div>

<!-- §1 Client Information -->
<div class="sec-h">1. Child / Client Information</div>
<div class="field-row">
  <div class="field-item" style="flex:2">
    <div class="field-label">Child / Client Name</div>
    <div class="field-value"><?= pdfVal($report['student_name']) ?></div>
  </div>
  <div class="field-item">
    <div class="field-label">Date of Birth / Age</div>
    <div class="field-value"><?= pdfVal($s1['dob_age'] ?? '') ?></div>
  </div>
  <div class="field-item">
    <div class="field-label">Gender</div>
    <div class="field-value" style="display:flex;gap:10px">
      <?php foreach (['Male','Female','Other'] as $g): ?>
      <?= pdfChk(($s1['gender'] ?? '') === $g) ?> <?= $g ?>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<div class="field-row">
  <div class="field-item">
    <div class="field-label">Date of Assessment</div>
    <div class="field-value"><?= pdfVal(!empty($s1['assessment_date']) ? date('d M Y', strtotime($s1['assessment_date'])) : '') ?></div>
  </div>
  <div class="field-item">
    <div class="field-label">Class / Grade</div>
    <div class="field-value"><?= pdfVal($report['class_name']) ?></div>
  </div>
  <div class="field-item" style="flex:2">
    <div class="field-label">Referral Reason</div>
    <div class="field-value"><?= pdfVal($s1['referral_reason'] ?? '') ?></div>
  </div>
</div>

<!-- §2 Case History -->
<div class="sec-h">2. Case History</div>
<?php
$s2Rows = [
    'presenting_concern' => 'Presenting Concern',
    'medical_history'    => 'Medical / Developmental History',
    'previous_therapy'   => 'Previous Therapy / Intervention',
    'hearing_vision'     => 'Hearing / Vision Status',
    'home_languages'     => 'Home Language(s)',
    'other_info'         => 'Other Relevant Information',
];
?>
<div class="field-row" style="flex-wrap:wrap">
<?php foreach ($s2Rows as $k => $lbl): ?>
<div class="field-item" style="min-width:220px">
  <div class="field-label"><?= $lbl ?></div>
  <div class="pre-val"><?= pdfVal($s2[$k] ?? '') ?></div>
</div>
<?php endforeach; ?>
</div>

<!-- §3 Speech & Language Assessment -->
<div class="sec-h">3. Speech &amp; Language Assessment</div>
<table>
  <thead>
    <tr>
      <th style="text-align:left;width:34%">Area</th>
      <th>Emerging</th>
      <th>Needs Support</th>
      <th>Age Appropriate</th>
      <th style="text-align:left">Remarks / Findings</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($s3Labels as $k => $lbl):
    $row = $s3[$k] ?? [];
    $rat = $row['rating'] ?? '';
  ?>
  <tr>
    <td class="area-col"><?= h($lbl) ?></td>
    <?php foreach (['Emerging','Needs Support','Age Appropriate'] as $rv): ?>
    <td class="chk-col"><?= pdfChk($rat === $rv) ?></td>
    <?php endforeach; ?>
    <td><?= h($row['remarks'] ?? '') ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<!-- §4 Articulation / Speech Sound Screening -->
<div class="sec-h">4. Articulation / Speech Sound Screening</div>
<table>
  <thead>
    <tr>
      <th style="text-align:left;width:14%">Position</th>
      <th style="text-align:left;width:28%">Sound / Word</th>
      <th style="width:14%">Correct</th>
      <th style="width:14%">Incorrect</th>
      <th style="text-align:left">Notes</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach (['initial'=>'Initial','medial'=>'Medial','final'=>'Final','blends'=>'Blends','other'=>'Other'] as $k => $lbl):
    $row = $s4[$k] ?? [];
  ?>
  <tr>
    <td class="left-h"><?= $lbl ?></td>
    <td><?= h($row['sound_word'] ?? '') ?></td>
    <td style="text-align:center"><?= h($row['correct'] ?? '') ?></td>
    <td style="text-align:center"><?= h($row['incorrect'] ?? '') ?></td>
    <td><?= h($row['notes'] ?? '') ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<!-- §5 Oral-Motor & Breathing -->
<div class="sec-h">5. Oral-Motor &amp; Breathing Observation</div>
<table>
  <thead>
    <tr>
      <th style="text-align:left;width:36%">Observation Area</th>
      <th>Adequate</th><th>Emerging</th><th>Needs Support</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($s5Labels as $k => $lbl):
    $cur = $s5[$k] ?? '';
  ?>
  <tr>
    <td class="left-h"><?= h($lbl) ?></td>
    <?php foreach (['Adequate','Emerging','Needs Support'] as $rv): ?>
    <td class="chk-col"><?= pdfChk($cur === $rv) ?></td>
    <?php endforeach; ?>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<!-- §6 Functional Communication -->
<div class="sec-h">6. Functional Communication</div>
<table>
  <thead>
    <tr>
      <th style="text-align:left;width:36%">Skill</th>
      <th>Independent</th><th>Prompted</th><th>Not Yet</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($s6Labels as $k => $lbl):
    $cur = $s6[$k] ?? '';
  ?>
  <tr>
    <td class="left-h"><?= h($lbl) ?></td>
    <?php foreach (['Independent','Prompted','Not Yet'] as $rv): ?>
    <td class="chk-col"><?= pdfChk($cur === $rv) ?></td>
    <?php endforeach; ?>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<!-- §7 Assessment Summary & Therapy Plan -->
<div class="sec-h">7. Assessment Summary &amp; Therapy Plan</div>
<div class="field-row">
  <div class="field-item">
    <div class="field-label">Strengths</div>
    <div class="pre-val"><?= pdfVal($s7['strengths'] ?? '') ?></div>
  </div>
  <div class="field-item">
    <div class="field-label">Primary Areas of Need</div>
    <div class="pre-val"><?= pdfVal($s7['primary_areas'] ?? '') ?></div>
  </div>
</div>
<div class="field-row">
  <div class="field-item" style="flex:1">
    <div class="field-label">Long-Term Goal</div>
    <div class="pre-val"><?= pdfVal($s7['long_term_goal'] ?? '') ?></div>
  </div>
</div>
<div style="margin-bottom:5px">
  <div class="field-label" style="font-size:7pt;text-transform:uppercase;letter-spacing:.3px;color:#64748b;margin-bottom:3px">Short-Term Goals</div>
  <?php foreach (($s7['short_term_goals'] ?? []) as $i => $g): ?>
  <div style="display:flex;gap:6px;margin-bottom:2px;font-size:9pt">
    <span style="color:#0891b2;font-weight:700;min-width:14px"><?= $i+1 ?>.</span>
    <span><?= pdfVal($g) ?></span>
  </div>
  <?php endforeach; ?>
</div>
<div class="field-row">
  <div class="field-item" style="flex:0 0 auto">
    <div class="field-label">Recommended Frequency</div>
    <div style="display:flex;gap:12px;align-items:center;margin-top:3px">
      <?php
      $freqOpts = ['1 session/week','2 sessions/week','3 sessions/week'];
      $curFreq = $s7['frequency'] ?? '';
      foreach ($freqOpts as $fv): ?>
      <span class="opt-item"><?= pdfChk($curFreq === $fv) ?> <?= h($fv) ?></span>
      <?php endforeach; ?>
      <span class="opt-item">
        <?= pdfChk(!in_array($curFreq, $freqOpts) && $curFreq !== '') ?> Other:
        <span style="border-bottom:.6pt solid #94a3b8;min-width:80px;display:inline-block;padding-left:3px">
          <?= h(!in_array($curFreq, $freqOpts) ? ($s7['frequency_other'] ?? $curFreq) : '') ?>
        </span>
      </span>
    </div>
  </div>
</div>

<!-- §8 Therapist Recommendations -->
<div class="sec-h">8. Therapist Recommendations</div>
<div class="pre-val" style="min-height:40px"><?= pdfVal($s8['recommendations'] ?? '') ?></div>

<!-- §9 Follow-Up / Review -->
<div class="sec-h">9. Follow-Up / Review</div>
<div class="field-row">
  <div class="field-item">
    <div class="field-label">Next Review Date</div>
    <div class="field-value"><?= pdfVal(!empty($s9['next_review_date']) ? date('d M Y', strtotime($s9['next_review_date'])) : '') ?></div>
  </div>
  <div class="field-item">
    <div class="field-label">Parent Guidance Provided</div>
    <div style="display:flex;gap:14px;margin-top:3px">
      <?php foreach (['Yes','No'] as $pv): ?>
      <span class="opt-item"><?= pdfChk(($s9['parent_guidance'] ?? '') === $pv) ?> <?= $pv ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="field-item" style="flex:2">
    <div class="field-label">Therapist Remarks</div>
    <div class="pre-val"><?= pdfVal($s9['therapist_remarks'] ?? '') ?></div>
  </div>
</div>

<!-- Signatures -->
<div class="sig-row">
  <div class="sig-item">
    Speech &amp; Language Therapist<br>
    <strong><?= h($report['therapist_name']) ?></strong>
  </div>
  <div class="sig-item">Therapist Signature</div>
  <div class="sig-item">Parent / Guardian Signature</div>
  <div class="sig-item">
    Date<br>
    <?= !empty($s1['assessment_date']) ? date('d M Y', strtotime($s1['assessment_date'])) : date('d M Y', strtotime($report['month'])) ?>
  </div>
</div>

<div class="conf-footer">
  CONFIDENTIAL — FOR PROFESSIONAL USE ONLY · ILC, Bahria Model College Bin Qasim
  · Printed: <?= date('d M Y') ?>
</div>

</div><!-- /page-body -->
</body>
</html>

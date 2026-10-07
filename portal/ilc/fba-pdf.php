<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp', 'admin', 'student_affairs', 'vp_main', 'wing_head', 'student');
$db   = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/ilc/fba.php'); exit; }

$st = $db->prepare(
    'SELECT p.*, s.id AS student_id, u.name AS student_name, u2.name AS recorder_name,
            s.roll_no, s.dob, c.name AS class_name
     FROM fba_plans p
     JOIN students s ON s.id = p.student_id
     JOIN users u    ON u.id = s.user_id
     JOIN users u2   ON u2.id = p.recorded_by
     LEFT JOIN classes c ON c.id = s.class_id
     WHERE p.id = ?'
);
$st->execute([$id]);
$plan = $st->fetch();

if (!$plan) { http_response_code(404); echo '<p style="font-family:sans-serif;padding:40px">Plan not found.</p>'; exit; }

// Students can only view their own
if ($user['role'] === 'student') {
    $myRow = $db->prepare('SELECT id FROM students WHERE user_id = ?');
    $myRow->execute([$user['id']]);
    $myRec = $myRow->fetch();
    if (!$myRec || (int)$myRec['id'] !== (int)$plan['student_id']) {
        http_response_code(403); echo '<p style="font-family:sans-serif;padding:40px">Access denied.</p>'; exit;
    }
}

$fd = json_decode($plan['form_data'], true) ?? [];
$basic    = $fd['basic']            ?? [];
$areas    = $fd['areas_of_concern'] ?? [];
$fbaRows  = $fd['fba_rows']         ?? [];
$strats   = $fd['strategies']       ?? [];
$goals    = $fd['smart_goals']      ?? [];
$dm       = $fd['daily_monitoring'] ?? [];
$pr       = $fd['progress_rating']  ?? [];
$notes    = $fd['notes']            ?? '';

$areasLabels = [
    'anger'               => 'Frequent anger / irritability',
    'negative_thinking'   => 'Negative thinking / pessimism',
    'blaming'             => 'Blaming others',
    'difficulty_criticism'=> 'Difficulty accepting criticism',
    'argumentative'       => 'Argumentative behaviour',
    'impulsivity'         => 'Impulsivity',
    'jealousy'            => 'Jealousy / insecurity',
    'controlling'         => 'Controlling behaviour',
    'social_withdrawal'   => 'Social withdrawal',
    'low_frustration'     => 'Low frustration tolerance',
    'poor_communication'  => 'Poor communication',
    'difficulty_emotions' => 'Difficulty managing emotions',
];
$stratLabels = [
    'self_awareness'         => 'Self-awareness',
    'pause_reacting'         => 'Pause before reacting',
    'cognitive_restr'        => 'Cognitive restructuring',
    'emotional_reg'          => 'Emotional regulation',
    'communication'          => 'Communication',
    'anger_management'       => 'Anger management',
    'problem_solving'        => 'Problem solving',
    'boundary_setting'       => 'Boundary setting',
    'flexibility'            => 'Flexibility',
    'positive_reinforcement' => 'Positive reinforcement',
    'social_skills'          => 'Social skills',
    'lifestyle_support'      => 'Lifestyle support',
];
$prLabels = [
    'emotional_regulation' => 'Emotional regulation',
    'anger_control'        => 'Anger control',
    'negative_thinking'    => 'Negative thinking',
    'communication'        => 'Communication',
    'impulse_control'      => 'Impulse control',
    'interpersonal'        => 'Interpersonal relationships',
    'problem_solving'      => 'Problem solving',
    'flexibility'          => 'Flexibility',
];

$dateLabel = $plan['session_date']
    ? date('d F Y', strtotime($plan['session_date']))
    : date('d F Y', strtotime($plan['created_at']));

$e  = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$nz = fn($s) => trim((string)$s) !== '' ? trim((string)$s) : '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>FBA Plan — <?= $e($plan['student_name']) ?> — <?= $e($dateLabel) ?></title>
<style>
:root { --navy:#0f2456; --amber:#92400e; --amber-lt:#fffbeb; }
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial, Helvetica, sans-serif; font-size:9.5pt; color:#1e293b; background:#f1f5f9; }

/* toolbar */
#toolbar {
  position:fixed; top:0; left:0; right:0; z-index:100;
  background:var(--amber); color:#fff; padding:10px 24px;
  display:flex; align-items:center; justify-content:space-between; gap:12px;
}
.btn-print { background:#fff; color:var(--amber); border:none; border-radius:6px;
             padding:7px 18px; font-size:10pt; cursor:pointer; font-weight:700; }
.btn-back  { background:transparent; color:rgba(255,255,255,.8);
             border:1px solid rgba(255,255,255,.4); border-radius:6px;
             padding:6px 14px; font-size:9.5pt; cursor:pointer; text-decoration:none; }

/* paper */
#report { max-width:210mm; margin:68px auto 40px; background:#fff;
          box-shadow:0 4px 24px rgba(0,0,0,.12); }

/* header */
.rpt-hdr {
  display:flex; align-items:center; gap:16px; padding:16px 24px 12px;
  border-bottom:3px solid var(--navy);
}
.rpt-hdr-logos { display:flex; align-items:center; gap:12px; }
.rpt-hdr-logos img { width:50px; height:50px; object-fit:contain; }
.rpt-hdr-text { flex:1; text-align:center; }
.rpt-hdr-inst { font-size:13pt; font-weight:700; color:var(--navy); letter-spacing:.3px; }
.rpt-hdr-college { font-size:11pt; font-weight:700; color:var(--navy); }
.rpt-title { font-size:12pt; font-weight:700; color:var(--amber); margin-top:10px;
             text-align:center; text-decoration:underline; letter-spacing:.3px; }
.rpt-purpose { font-size:8.5pt; color:#374151; padding:8px 24px; border-bottom:1px solid #e2e8f0;
               font-style:italic; }

/* body */
.rpt-body { padding:14px 24px 28px; }

/* section heading */
.sh { font-size:10pt; font-weight:700; color:var(--navy); margin:14px 0 6px;
      text-decoration:underline; }

/* info table */
.info-tbl { width:100%; border-collapse:collapse; font-size:9pt; }
.info-tbl td { border:1px solid #9ca3af; padding:4px 7px; }
.info-tbl td.lbl { font-weight:700; background:#f9fafb; width:20%; }

/* FBA table */
.fba-tbl { width:100%; border-collapse:collapse; font-size:8.5pt; }
.fba-tbl th { border:1px solid #6b7280; background:#1e3a5f; color:#fff;
              padding:4px 6px; font-size:8pt; font-weight:700; }
.fba-tbl td { border:1px solid #9ca3af; padding:5px 6px; vertical-align:top; min-height:28px; }
.fba-tbl tr:nth-child(even) td { background:#f9fafb; }

/* strat table */
.str-tbl { width:100%; border-collapse:collapse; font-size:8.5pt; }
.str-tbl th { border:1px solid #6b7280; background:#1e3a5f; color:#fff; padding:4px 8px; font-weight:700; }
.str-tbl td { border:1px solid #9ca3af; padding:5px 8px; vertical-align:top; }
.str-tbl td.ta { font-weight:700; background:#f9fafb; width:24%; }

/* checklist */
.aoc-grid { display:grid; grid-template-columns:1fr 1fr; gap:3px 24px;
            border:1px solid #9ca3af; padding:8px; }
.aoc-item { display:flex; align-items:center; gap:6px; font-size:8.5pt; }
.chk-box  { width:12px; height:12px; border:1px solid #374151; flex-shrink:0;
            display:flex; align-items:center; justify-content:center;
            font-size:9pt; font-weight:700; color:var(--navy); }

/* pr table */
.pr-tbl { width:100%; border-collapse:collapse; font-size:8.5pt; }
.pr-tbl th { border:1px solid #6b7280; background:#1e3a5f; color:#fff; padding:4px 8px; font-weight:700; }
.pr-tbl td { border:1px solid #9ca3af; padding:6px 10px; }
.pr-tbl td.area { font-weight:500; width:50%; }
.pr-tbl td.rating { text-align:center; width:25%; }

/* review bullets */
.review-list { list-style:disc; padding-left:20px; font-size:8.5pt; color:#374151; }
.review-list li { margin-bottom:4px; }

/* notes lines */
.note-line { border-bottom:1px solid #6b7280; margin-bottom:14px; height:18px; }

/* sig */
.sig-row { display:grid; grid-template-columns:1fr 1fr; gap:40px; margin-top:20px; }
.sig-line { border-top:1px solid #374151; padding-top:3px; font-size:8pt; color:#6b7280; margin-top:28px; }

/* footer */
.rpt-foot { text-align:center; font-size:7.5pt; color:#94a3b8;
            margin-top:16px; padding-top:8px; border-top:1px solid #e2e8f0; }

@media print {
  body { background:#fff; }
  #toolbar { display:none; }
  #report  { margin:0; box-shadow:none; max-width:100%; }
  @page { size:A4; margin:7mm 10mm; }
}
</style>
</head>
<body>

<div id="toolbar">
  <div>
    <?php if ($user['role'] === 'student'): ?>
    <a href="/portal/student/fba.php" class="btn-back">← Back</a>
    <?php else: ?>
    <a href="/portal/ilc/fba.php?student_id=<?= $plan['student_id'] ?>" class="btn-back">← Back</a>
    <?php endif; ?>
  </div>
  <div style="font-weight:700"><?= $e($plan['student_name']) ?> — FBA Plan — <?= $e($dateLabel) ?></div>
  <button class="btn-print" onclick="window.print()">🖨 Print / Save as PDF</button>
</div>

<div id="report">

  <!-- Header (mirroring the PDF) -->
  <div class="rpt-hdr">
    <div class="rpt-hdr-logos">
      <img src="/assets/pak-logo.png" alt="Pakistan" onerror="this.style.display='none'">
    </div>
    <div class="rpt-hdr-text">
      <div class="rpt-hdr-inst">INCLUSIVE LEARNING CENTRE (ILC)</div>
      <div class="rpt-hdr-college">BAHRIA MODEL COLLEGE</div>
    </div>
    <div class="rpt-hdr-logos">
      <img src="/assets/ilc-logo.png" alt="ILC" onerror="this.style.display='none'">
    </div>
  </div>

  <div class="rpt-title">Behavior Management Plan</div>
  <div class="rpt-purpose">
    Purpose: To identify challenging personality patterns, understand their triggers, and develop healthier
    emotional, interpersonal, and behavioural responses. This plan is intended as a structured support tool
    and is not a diagnostic instrument.
  </div>

  <div class="rpt-body">

    <!-- 1. Basic Information -->
    <div class="sh">1. Basic Information</div>
    <table class="info-tbl">
      <tr>
        <td class="lbl">Name</td>
        <td><?= $e($plan['student_name']) ?></td>
        <td class="lbl">Age</td>
        <td><?= $e($nz($basic['age'] ?? '')) ?></td>
      </tr>
      <tr>
        <td class="lbl">Date</td>
        <td><?= $e($basic['date'] ? date('d M Y', strtotime($basic['date'])) : '—') ?></td>
        <td class="lbl">Gender</td>
        <td><?= $e($nz($basic['gender'] ?? '')) ?></td>
      </tr>
      <tr>
        <td class="lbl">Occupation / Grade</td>
        <td><?= $e($nz($basic['occupation'] ?? '')) ?></td>
        <td class="lbl">Session No.</td>
        <td><?= $e($nz($basic['session_no'] ?? '')) ?></td>
      </tr>
      <tr>
        <td class="lbl">Facilitator / Therapist</td>
        <td><?= $e($plan['recorder_name']) ?></td>
        <td class="lbl">Review Date</td>
        <td><?= $e($basic['review_date'] ? date('d M Y', strtotime($basic['review_date'])) : '—') ?></td>
      </tr>
    </table>

    <!-- 2. Areas of Concern -->
    <div class="sh">2. Areas of Concern</div>
    <div class="aoc-grid">
      <?php foreach ($areasLabels as $k => $lbl): ?>
      <div class="aoc-item">
        <div class="chk-box"><?= in_array($k, $areas) ? '✓' : '' ?></div>
        <span><?= $e($lbl) ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- 3. Functional Behaviour Assessment -->
    <div class="sh">3. Functional Behaviour Assessment</div>
    <table class="fba-tbl">
      <thead>
        <tr>
          <th>Situation / Trigger</th>
          <th>Thought or feeling</th>
          <th>Behaviour</th>
          <th>Immediate Consequence</th>
          <th>Healthy Alternative</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($fbaRows): ?>
        <?php foreach ($fbaRows as $row): ?>
        <tr>
          <td><?= $e($row['trigger']     ?? '') ?></td>
          <td><?= $e($row['thought']     ?? '') ?></td>
          <td><?= $e($row['behaviour']   ?? '') ?></td>
          <td><?= $e($row['consequence'] ?? '') ?></td>
          <td><?= $e($row['healthy_alt'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php else: ?>
        <?php for ($i = 0; $i < 5; $i++): ?><tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr><?php endfor; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- 4. Strategies Plan -->
    <div class="sh">4. Strategies Plan</div>
    <table class="str-tbl">
      <thead>
        <tr><th>Target Area</th><th>Recommended Strategy</th></tr>
      </thead>
      <tbody>
        <?php foreach ($stratLabels as $k => $lbl): ?>
        <tr>
          <td class="ta"><?= $e($lbl) ?></td>
          <td><?= nl2br($e($strats[$k] ?? '')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- 5. Weekly SMART Goals -->
    <div class="sh">5. Weekly SMART Goals</div>
    <table class="fba-tbl">
      <thead>
        <tr><th>Goal</th><th>Baseline</th><th>Target</th><th>Strategy</th><th>Outcome</th></tr>
      </thead>
      <tbody>
        <?php if ($goals): ?>
        <?php foreach ($goals as $g): ?>
        <tr>
          <td><?= $e($g['goal']     ?? '') ?></td>
          <td><?= $e($g['baseline'] ?? '') ?></td>
          <td><?= $e($g['target']   ?? '') ?></td>
          <td><?= $e($g['strategy'] ?? '') ?></td>
          <td><?= $e($g['outcome']  ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php else: ?>
        <?php for ($i = 0; $i < 5; $i++): ?><tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr><?php endfor; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- 6. Daily Self-Monitoring -->
    <div class="sh">6. Daily Self-Monitoring</div>
    <table class="fba-tbl">
      <thead>
        <tr><th>Date</th><th>Trigger</th><th>Emotion (0–10)</th><th>Response</th><th>Healthy Strategy Used</th><th>Result</th></tr>
      </thead>
      <tbody>
        <?php if ($dm): ?>
        <?php foreach ($dm as $d): ?>
        <tr>
          <td><?= $e($d['date']             ? date('d/m/Y', strtotime($d['date'])) : '') ?></td>
          <td><?= $e($d['trigger']          ?? '') ?></td>
          <td><?= $e($d['emotion']          ?? '') ?></td>
          <td><?= $e($d['response']         ?? '') ?></td>
          <td><?= $e($d['healthy_strategy'] ?? '') ?></td>
          <td><?= $e($d['result']           ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php else: ?>
        <?php for ($i = 0; $i < 5; $i++): ?><tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr><?php endfor; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- 7. Progress Rating -->
    <div class="sh">7. Progress Rating</div>
    <p style="font-size:8pt;color:#374151;margin-bottom:6px">
      Rate each area from 0–4: 0 = Not observed, 1 = Severe difficulty, 2 = Moderate difficulty,
      3 = Mild difficulty, 4 = Good control.
    </p>
    <table class="pr-tbl">
      <thead>
        <tr><th>Area</th><th>Initial Rating</th><th>Review Rating</th></tr>
      </thead>
      <tbody>
        <?php foreach ($prLabels as $k => $lbl): ?>
        <tr>
          <td class="area"><?= $e($lbl) ?></td>
          <td class="rating"><?= $e($pr[$k]['initial'] ?? '') ?></td>
          <td class="rating"><?= $e($pr[$k]['review']  ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- 8. Review & Follow-Up -->
    <div class="sh">8. Review &amp; Follow-Up</div>
    <ul class="review-list">
      <li>Review progress weekly or according to the treatment schedule.</li>
      <li>Identify strategies that are effective and continue them consistently.</li>
      <li>Modify goals when progress is stable or when new concerns emerge.</li>
      <li>Use objective behavioural observations rather than labels such as 'bad' or 'difficult'.</li>
      <li>If behaviour includes threats, violence, severe impairment, self-harm, or risk to others, seek
          assessment from a qualified mental-health professional promptly.</li>
    </ul>

    <!-- 9. Notes -->
    <div class="sh">9. Notes</div>
    <?php if ($notes): ?>
    <div style="font-size:9pt;white-space:pre-wrap;border:1px solid #9ca3af;padding:8px;border-radius:4px">
      <?= $e($notes) ?>
    </div>
    <?php else: ?>
    <?php for ($i = 0; $i < 5; $i++): ?><div class="note-line"></div><?php endfor; ?>
    <?php endif; ?>

    <!-- Signatures -->
    <div class="sig-row">
      <div><div class="sig-line">Facilitator / Therapist Signature</div></div>
      <div><div class="sig-line">VP ILC / Supervisor Signature</div></div>
    </div>

    <div class="rpt-foot">
      Generated <?= date('d M Y, H:i') ?> &nbsp;·&nbsp;
      ILC — Bahria Model College, Bin Qasim &nbsp;·&nbsp;
      CONFIDENTIAL — NOT A DIAGNOSTIC INSTRUMENT
    </div>
  </div>
</div>

</body>
</html>

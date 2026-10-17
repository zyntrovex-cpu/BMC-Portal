<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../config/config.php';

// Accessible by ILC VP, admin, student_affairs, vp_main, wing_head, AND students (own only)
$user = requireAuth('ilc_vp', 'admin', 'student_affairs', 'vp_main', 'wing_head', 'student');
$db   = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/ilc/behaviour-therapy.php'); exit; }

// Fetch report with student + recorder info
$st = $db->prepare(
    'SELECT r.*, s.id AS student_id, u.name AS student_name, u2.name AS recorder_name,
            s.roll_no, s.dob, c.name AS class_name
     FROM behaviour_therapy_reports r
     JOIN students s ON s.id = r.student_id
     JOIN users u    ON u.id = s.user_id
     JOIN users u2   ON u2.id = r.recorded_by
     LEFT JOIN classes c ON c.id = s.class_id
     WHERE r.id = ?'
);
$st->execute([$id]);
$report = $st->fetch();

if (!$report) {
    http_response_code(404);
    echo '<p style="font-family:sans-serif;padding:40px">Report not found.</p>';
    exit;
}

// Students can only view their own reports
if ($user['role'] === 'student') {
    $myStudent = $db->prepare('SELECT id FROM students WHERE user_id = ?');
    $myStudent->execute([$user['id']]);
    $myStudentRow = $myStudent->fetch();
    if (!$myStudentRow || (int)$myStudentRow['id'] !== (int)$report['student_id']) {
        http_response_code(403);
        echo '<p style="font-family:sans-serif;padding:40px">Access denied.</p>';
        exit;
    }
}

// Parse ABA data
$aba = (!empty($report['aba_data'])) ? (json_decode($report['aba_data'], true) ?? []) : [];
$client      = $aba['client']           ?? [];
$assessment  = $aba['assessment']       ?? [];
$behaviors   = $aba['target_behaviors'] ?? [];
$goals       = $aba['goals']            ?? [];
$teaching    = $aba['teaching']         ?? [];
$trials      = $aba['trials']           ?? [];
$progNotes   = $aba['progress_notes']   ?? [];

$monthLabel  = date('F Y', strtotime($report['month']));
$dob         = $report['dob'] ? date('d M Y', strtotime($report['dob'])) : '—';
$age         = $client['age']       ?? '';
$diagnosis   = $client['diagnosis'] ?? ($report['disability_notes'] ?? '');

// Helper
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$nz = fn($s) => $s !== '' ? $s : '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ABA Report — <?= $e($report['student_name']) ?> — <?= $e($monthLabel) ?></title>
<style>
:root {
  --navy: #0f2456;
  --teal: #0891b2;
  --gold: #b8860b;
  --lt:   #f8fafc;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 10pt;
  color: #1e293b;
  background: #f1f5f9;
}
/* ── No-print toolbar ────────────────────── */
#toolbar {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  background: var(--navy); color: #fff; padding: 10px 24px;
  display: flex; align-items: center; justify-content: space-between;
  font-size: 11pt; gap: 12px;
}
#toolbar .tname { font-weight: 700; }
#toolbar .tmonth { opacity: .75; font-size: 9.5pt; }
.btn-print {
  background: var(--teal); color: #fff; border: none; border-radius: 6px;
  padding: 7px 18px; font-size: 10pt; cursor: pointer; font-weight: 600;
}
.btn-back {
  background: transparent; color: rgba(255,255,255,.75); border: 1px solid rgba(255,255,255,.3);
  border-radius: 6px; padding: 6px 14px; font-size: 9.5pt; cursor: pointer; text-decoration: none;
}
/* ── Report paper ────────────────────────── */
#report {
  max-width: 210mm;
  margin: 70px auto 40px;
  background: #fff;
  box-shadow: 0 4px 24px rgba(0,0,0,.12);
  padding: 0;
}
/* ── Header ──────────────────────────────── */
.rpt-header {
  background: var(--navy);
  color: #fff;
  padding: 18px 28px 14px;
  display: flex;
  align-items: center;
  gap: 16px;
}
.rpt-header img { width: 52px; height: 52px; object-fit: contain; }
.rpt-header-text { flex: 1; }
.rpt-header-sub { font-size: 7.5pt; letter-spacing: 1.2px; text-transform: uppercase; opacity: .75; }
.rpt-header-title { font-size: 14.5pt; font-weight: 700; margin: 2px 0; letter-spacing: .3px; }
.rpt-header-month { font-size: 9pt; opacity: .8; }
.gold-bar { height: 4px; background: linear-gradient(90deg, var(--gold), #d4a820, var(--gold)); }
/* ── Body ────────────────────────────────── */
.rpt-body { padding: 20px 28px 28px; }
/* ── Section ─────────────────────────────── */
.sec {
  margin-bottom: 16px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  overflow: hidden;
}
.sec-head {
  background: var(--navy);
  color: #fff;
  font-size: 9pt;
  font-weight: 700;
  padding: 5px 12px;
  letter-spacing: .6px;
  text-transform: uppercase;
}
.sec-body { padding: 10px 14px; }
/* ── Info grid ───────────────────────────── */
.info-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px 12px;
}
.info-item { font-size: 9pt; }
.info-lbl { color: #64748b; font-size: 7.5pt; text-transform: uppercase; letter-spacing: .4px; }
.info-val { font-weight: 700; color: #0f172a; }
/* ── Bullet list ─────────────────────────── */
.bullet-list { list-style: none; padding: 0; margin: 0; }
.bullet-list li { display: flex; gap: 6px; font-size: 9pt; margin-bottom: 5px; }
.bullet-list li::before { content: '•'; color: var(--teal); font-size: 11pt; line-height: 1; flex-shrink: 0; }
.nl-lbl { color: #64748b; font-size: 8pt; display: block; margin-bottom: 2px; font-weight: 600; }
/* ── ABA Table ───────────────────────────── */
.aba-table { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-top: 4px; }
.aba-table th {
  background: var(--navy); color: #fff; padding: 5px 8px; text-align: left;
  font-weight: 600; font-size: 7.5pt; text-transform: uppercase; letter-spacing: .4px;
}
.aba-table td { border: 1px solid #e2e8f0; padding: 5px 8px; vertical-align: top; }
.aba-table tr:nth-child(even) td { background: #f8fafc; }
/* ── Teaching chips ──────────────────────── */
.chip {
  display: inline-block; background: var(--navy); color: #fff;
  padding: 2px 8px; border-radius: 4px; font-size: 7.5pt;
  font-weight: 600; margin-right: 6px; letter-spacing: .3px;
}
/* ── Progress boxes ─────────────────────── */
.prog-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 5px; padding: 8px 12px; }
.prog-box + .prog-box { margin-top: 8px; }
/* ── Footer ──────────────────────────────── */
.rpt-footer {
  margin-top: 20px;
  padding-top: 12px;
  border-top: 1px solid #e2e8f0;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}
.sig-line { border-top: 1px solid #1e293b; padding-top: 4px; font-size: 8pt; color: #64748b; margin-top: 32px; }
/* ── Numbered goal row ───────────────────── */
.goal-row { display: flex; gap: 8px; font-size: 9pt; margin-bottom: 4px; }
.goal-num { font-weight: 700; color: var(--teal); flex-shrink: 0; min-width: 18px; }

/* ── Print ───────────────────────────────── */
@media print {
  body { background: #fff; }
  #toolbar { display: none; }
  #report  { margin: 0; box-shadow: none; max-width: 100%; }
  @page { size: A4; margin: 8mm 10mm; }
}
</style>
</head>
<body>

<!-- Toolbar (screen only) -->
<div id="toolbar">
  <div>
    <?php if ($user['role'] === 'student'): ?>
    <a href="/portal/student/behaviour-therapy.php" class="btn-back">← Back</a>
    <?php else: ?>
    <a href="/portal/ilc/behaviour-therapy.php?student_id=<?= $report['student_id'] ?>" class="btn-back">← Back</a>
    <?php endif; ?>
  </div>
  <div>
    <span class="tname"><?= $e($report['student_name']) ?></span>
    <span class="tmonth"> — ABA Report — <?= $e($monthLabel) ?></span>
  </div>
  <button class="btn-print" onclick="window.print()">
    🖨 Print / Save as PDF
  </button>
</div>

<!-- Report Paper -->
<div id="report">

  <!-- Header -->
  <div class="rpt-header">
    <img src="/assets/ilc-logo.png" alt="ILC" onerror="this.style.display='none'">
    <div class="rpt-header-text">
      <div class="rpt-header-sub">Bahria Model School &amp; College · Bin Qasim</div>
      <div class="rpt-header-title">ABA Therapy Program Report</div>
      <div class="rpt-header-month">Inclusive Learning Centre &nbsp;·&nbsp; <?= $e($monthLabel) ?></div>
    </div>
  </div>
  <div class="gold-bar"></div>

  <div class="rpt-body">

    <!-- 1. Client Information -->
    <div class="sec">
      <div class="sec-head">1. Client Information</div>
      <div class="sec-body">
        <div class="info-grid">
          <div class="info-item">
            <div class="info-lbl">Student Name</div>
            <div class="info-val"><?= $e($report['student_name']) ?></div>
          </div>
          <div class="info-item">
            <div class="info-lbl">Roll No. / GR</div>
            <div class="info-val"><?= $e($nz($report['roll_no'])) ?></div>
          </div>
          <div class="info-item">
            <div class="info-lbl">Class</div>
            <div class="info-val"><?= $e($nz($report['class_name'])) ?></div>
          </div>
          <div class="info-item">
            <div class="info-lbl">Age</div>
            <div class="info-val"><?= $e($nz($age)) ?></div>
          </div>
          <div class="info-item">
            <div class="info-lbl">Date of Birth</div>
            <div class="info-val"><?= $e($dob) ?></div>
          </div>
          <div class="info-item">
            <div class="info-lbl">Diagnosis</div>
            <div class="info-val"><?= $e($nz($diagnosis)) ?></div>
          </div>
          <div class="info-item">
            <div class="info-lbl">Therapist</div>
            <div class="info-val"><?= $e($report['recorder_name']) ?></div>
          </div>
          <div class="info-item">
            <div class="info-lbl">Report Month</div>
            <div class="info-val"><?= $e($monthLabel) ?></div>
          </div>
          <div class="info-item">
            <div class="info-lbl">Date Recorded</div>
            <div class="info-val"><?= date('d M Y', strtotime($report['created_at'])) ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- 2. Assessment Summary -->
    <?php if (array_filter($assessment)): ?>
    <div class="sec">
      <div class="sec-head">2. Assessment Summary</div>
      <div class="sec-body">
        <ul class="bullet-list">
          <?php if ($assessment['skill_level'] ?? ''): ?>
          <li><div><span class="nl-lbl">Current Skill Level</span><?= $e($assessment['skill_level']) ?></div></li>
          <?php endif; ?>
          <?php if ($assessment['behavior_concerns'] ?? ''): ?>
          <li><div><span class="nl-lbl">Behavior Concerns</span><?= $e($assessment['behavior_concerns']) ?></div></li>
          <?php endif; ?>
          <?php if ($assessment['strengths'] ?? ''): ?>
          <li><div><span class="nl-lbl">Strengths</span><?= $e($assessment['strengths']) ?></div></li>
          <?php endif; ?>
          <?php if ($assessment['barriers'] ?? ''): ?>
          <li><div><span class="nl-lbl">Barriers to Learning</span><?= $e($assessment['barriers']) ?></div></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>

    <!-- 3. Target Behaviors -->
    <?php $filteredBehaviors = array_filter($behaviors); if ($filteredBehaviors): ?>
    <div class="sec">
      <div class="sec-head">3. Target Behaviors</div>
      <div class="sec-body">
        <ul class="bullet-list">
          <?php foreach ($filteredBehaviors as $b): ?>
          <li><?= $e($b) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>

    <!-- 4. SMART Goals -->
    <?php $filteredGoals = array_filter($goals); if ($filteredGoals): ?>
    <div class="sec">
      <div class="sec-head">4. ABA Goals (SMART Goals)</div>
      <div class="sec-body">
        <?php foreach (array_values($filteredGoals) as $gi => $g): ?>
        <div class="goal-row">
          <span class="goal-num"><?= $gi + 1 ?>.</span>
          <span><?= $e($g) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- 5. Teaching Procedures -->
    <?php if ($teaching): ?>
    <div class="sec">
      <div class="sec-head">5. Teaching Procedures</div>
      <div class="sec-body">
        <div style="margin-bottom:8px">
          <?php if ($teaching['dtt'] ?? false): ?>
          <span class="chip">DTT</span>Discrete Trial Training
          <?php endif; ?>
          <?php if ($teaching['net'] ?? false): ?>
          &nbsp;&nbsp;<span class="chip">NET</span>Natural Environment Teaching
          <?php endif; ?>
        </div>
        <ul class="bullet-list">
          <?php if ($teaching['prompting_strategy'] ?? ''): ?>
          <li><div><span class="nl-lbl">Prompting Strategy</span><?= $e($teaching['prompting_strategy']) ?></div></li>
          <?php endif; ?>
          <?php if ($teaching['reinforcement_type'] ?? ''): ?>
          <li><div><span class="nl-lbl">Reinforcement Type</span><?= $e($teaching['reinforcement_type']) ?></div></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>

    <!-- 6 & 7. ABA Data Collection Table -->
    <?php $hasTrials = !empty(array_filter(array_column($trials, 'target'))); ?>
    <div class="sec">
      <div class="sec-head">6 &amp; 7. ABA Data Collection Table</div>
      <div class="sec-body">
        <div style="font-size:8pt;color:#64748b;margin-bottom:6px">
          Daily data recording accuracy, prompts, and mastery.
          (VP = Verbal Prompt · PP = Physical Prompt · FP = Full Physical · I = Independent)
        </div>
        <table class="aba-table">
          <thead>
            <tr>
              <th style="width:36px">Trial</th>
              <th>Target</th>
              <th style="width:90px">Prompt Level</th>
              <th style="width:80px">Response</th>
              <th style="width:100px">Reinforcement</th>
              <th>Comments</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($trials): ?>
            <?php foreach ($trials as $ti => $t): ?>
            <tr>
              <td style="text-align:center;font-weight:700"><?= $ti + 1 ?></td>
              <td><?= $e($t['target']        ?? '') ?></td>
              <td><?= $e($t['prompt_level']  ?? '') ?></td>
              <td><?= $e($t['response']      ?? '') ?></td>
              <td><?= $e($t['reinforcement'] ?? '') ?></td>
              <td><?= $e($t['comments']      ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <?php for ($ti = 1; $ti <= 4; $ti++): ?>
            <tr>
              <td style="text-align:center;font-weight:700"><?= $ti ?></td>
              <td></td><td></td><td></td><td></td><td></td>
            </tr>
            <?php endfor; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 8. Progress Notes -->
    <?php if (($progNotes['session_summary'] ?? '') || ($progNotes['next_steps'] ?? '')): ?>
    <div class="sec">
      <div class="sec-head">8. Progress Notes</div>
      <div class="sec-body">
        <?php if ($progNotes['session_summary'] ?? ''): ?>
        <div class="prog-box">
          <span class="nl-lbl">Session Summary</span>
          <?= nl2br($e($progNotes['session_summary'])) ?>
        </div>
        <?php endif; ?>
        <?php if ($progNotes['next_steps'] ?? ''): ?>
        <div class="prog-box">
          <span class="nl-lbl">Next Steps</span>
          <?= nl2br($e($progNotes['next_steps'])) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Signatures -->
    <div class="rpt-footer">
      <div>
        <div class="sig-line">Therapist Signature &amp; Name</div>
      </div>
      <div>
        <div class="sig-line">VP ILC / Supervisor Signature</div>
      </div>
    </div>

    <div style="text-align:center;margin-top:14px;font-size:7.5pt;color:#94a3b8">
      Report generated <?= date('d M Y, H:i') ?> &nbsp;·&nbsp;
      ILC — Bahria Model School &amp; College, Bin Qasim &nbsp;·&nbsp;
      CONFIDENTIAL
    </div>

  </div><!-- /rpt-body -->
</div><!-- /report -->

</body>
</html>

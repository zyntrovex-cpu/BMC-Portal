<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('student_affairs');
requirePermission('sa_admissions');
$db   = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { redirect('/portal/student-affairs/admissions.php'); }

$st = $db->prepare(
    'SELECT ar.*, ru.name AS requested_by_name, rv.name AS reviewed_by_name
     FROM admission_requests ar
     JOIN users ru ON ru.id = ar.requested_by
     LEFT JOIN users rv ON rv.id = ar.reviewed_by
     WHERE ar.id = ?'
);
$st->execute([$id]);
$req = $st->fetch();

if (!$req) {
    http_response_code(404);
    echo '<p style="font-family:sans-serif;padding:40px">Admission request not found.</p>';
    exit;
}

$migrationApplied = false;
try { $db->query('SELECT assessment_data FROM admission_requests LIMIT 0'); $migrationApplied = true; } catch (Exception $e) {}

$ad = [];
if ($migrationApplied && !empty($req['assessment_data'])) {
    $ad = json_decode($req['assessment_data'], true) ?? [];
}

$q1Items = [
    'dyslexia'   => 'Dyslexia',
    'add_adhd'   => 'ADD / ADHD',
    'asd'        => 'Autism Spectrum Disorder (ASD)',
    'dyspraxia'  => 'Dyspraxia / DCD',
    'speech'     => 'Speech / Language Disorder',
    'other'      => 'Other (specify)',
];
$q2Items = [
    'long_term_medical'   => ['label' => 'Long-term medical condition',                       'specify' => true],
    'physical_disability' => ['label' => 'Physical disability',                                'specify' => true],
    'bereavement'         => ['label' => 'Recent bereavement / personal trauma',               'specify' => false],
    'other_health'        => ['label' => 'Other significant health / behavioural condition',   'specify' => true],
];
$q3Items = [
    'edu_psychologist'   => ['label' => 'Assessment by an Educational Psychologist',           'specify' => false],
    'other_professional' => ['label' => 'Assessment by another professional',                   'specify' => true],
    'anxiety_depression' => ['label' => 'History of anxiety / depression',                     'specify' => false],
    'counseling'         => ['label' => 'Currently receiving counseling / psychotherapy',      'specify' => false],
    'self_harm'          => ['label' => 'History of self-harm',                                 'specify' => false],
    'medication'         => ['label' => 'Currently on prescribed medication',                   'specify' => true],
];
$q4Labels = [
    'standardized_testing' => 'Scored highly on standardized tests',
    'gifted_program'       => 'Previously enrolled in a Gifted Education Programme',
    'edu_psychologist'     => 'Identified by an Educational Psychologist as Gifted',
];

$logoBase = defined('BASE_URL') ? BASE_URL : '';
$schoolName = getSetting('school_name', 'BMC Bin Qasim');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admission Form — <?= h($req['student_name']) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI',Arial,sans-serif; font-size:10pt; color:#111; background:#fff; }
@page { size:A4; margin:10mm 14mm; }
@media print {
  body { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
  .no-print { display:none !important; }
}
.no-print {
  position:fixed; top:14px; right:14px; display:flex; gap:8px; z-index:999;
}
.btn-print, .btn-back {
  padding:7px 16px; border-radius:6px; cursor:pointer; font-size:12px; border:none;
  font-family:inherit; text-decoration:none; display:inline-block;
}
.btn-print { background:#1e3a5f; color:#fff; }
.btn-back  { background:#6b7280; color:#fff; }
.hdr { display:flex; align-items:center; justify-content:space-between;
       border-bottom:2px solid #1e3a5f; padding-bottom:8px; margin-bottom:10px; }
.hdr-logos img { width:46px; height:46px; object-fit:contain; }
.hdr-center { flex:1; text-align:center; }
.hdr-school  { font-size:12pt; font-weight:800; color:#1e3a5f; letter-spacing:.3px; }
.hdr-section { font-size:9pt; font-weight:700; color:#374151; margin-top:2px; }
.hdr-sub     { font-size:8.5pt; color:#475569; margin-top:1px; }
.info-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:4px 12px;
             background:#eff6ff; padding:8px 10px; border-radius:5px;
             margin-bottom:10px; border:1px solid #bfdbfe; font-size:8.5pt; }
.info-row  { display:flex; gap:4px; }
.info-row .lbl { color:#64748b; min-width:72px; }
.sec-hdr { font-size:8pt; font-weight:700; text-transform:uppercase; letter-spacing:.5px;
           background:#1e3a5f; color:#fff; padding:3px 8px; border-radius:3px 3px 0 0; margin-top:8px; }
table { width:100%; border-collapse:collapse; margin-bottom:0; font-size:8.3pt; }
table.form-tbl td, table.form-tbl th { border:1px solid #cbd5e1; padding:3px 7px; vertical-align:middle; }
table.form-tbl th { background:#f1f5f9; font-weight:600; }
.badge-yes { background:#166534; color:#fff; padding:1px 8px; border-radius:10px; font-size:7.5pt; font-weight:700; }
.badge-no  { background:#6b7280; color:#fff; padding:1px 8px; border-radius:10px; font-size:7.5pt; }
.badge-status { padding:2px 10px; border-radius:10px; font-size:8pt; font-weight:700; }
.status-pending  { background:#fef9c3; color:#854d0e; }
.status-reviewed { background:#dbeafe; color:#1e40af; }
.status-approved { background:#dcfce7; color:#166534; }
.status-rejected { background:#fee2e2; color:#991b1b; }
.notes-box { border:1px solid #cbd5e1; border-top:none; padding:6px 8px;
             font-size:8.3pt; min-height:28px; background:#fafafa; }
.sig-row  { display:flex; justify-content:space-between; margin-top:14px;
            padding-top:8px; border-top:1px solid #cbd5e1; }
.sig-box  { text-align:center; min-width:110px; }
.sig-line { border-bottom:1px solid #374151; width:90px; margin:16px auto 3px; }
.sig-lbl  { font-size:7.5pt; color:#475569; }
.disability-box { background:#f0f9ff; border:1px solid #bae6fd; border-radius:4px;
                  padding:6px 10px; margin-bottom:8px; font-size:8.3pt; }
</style>
</head>
<body>

<div class="no-print">
  <a href="<?= url('/portal/student-affairs/admissions.php') ?>" class="btn-back">← Back</a>
  <button class="btn-print" onclick="window.print()">Print / Save PDF</button>
</div>

<!-- Header -->
<div class="hdr">
  <div class="hdr-logos">
    <img src="<?= $logoBase ?>/assets/bmc-logo.png" alt="" onerror="this.style.display='none'">
  </div>
  <div class="hdr-center">
    <div class="hdr-school"><?= h(strtoupper($schoolName)) ?></div>
    <div class="hdr-section">STUDENT AFFAIRS — ADMISSION ASSESSMENT FORM</div>
    <div class="hdr-sub">ILC / Special Needs Intake Form</div>
  </div>
  <div class="hdr-logos">
    <img src="<?= $logoBase ?>/assets/bmc-logo.png" alt="" onerror="this.style.display='none'">
  </div>
</div>

<!-- Student Info -->
<div class="info-grid">
  <div class="info-row"><span class="lbl">Student Name:</span><strong><?= h($req['student_name']) ?></strong></div>
  <div class="info-row"><span class="lbl">Parent/Guardian:</span><strong><?= h($req['parent_name'] ?: '—') ?></strong></div>
  <div class="info-row"><span class="lbl">Contact:</span><strong><?= h($req['parent_phone'] ?: '—') ?></strong></div>
  <div class="info-row"><span class="lbl">Date of Birth:</span><strong><?= !empty($req['dob']) ? date('d M Y', strtotime($req['dob'])) : '—' ?></strong></div>
  <div class="info-row"><span class="lbl">Applied Class:</span><strong><?= h($req['requested_class'] ?: '—') ?></strong></div>
  <div class="info-row"><span class="lbl">Category:</span><strong><?= !empty($req['student_category']) ? strtoupper(h($req['student_category'])) : '—' ?></strong></div>
  <div class="info-row"><span class="lbl">Wing:</span><strong><?= !empty($req['wing']) ? ucfirst(h($req['wing'])) : '—' ?></strong></div>
  <div class="info-row"><span class="lbl">Status:</span>
    <span class="badge-status status-<?= h($req['status']) ?>"><?= ucfirst(h($req['status'])) ?></span>
  </div>
  <div class="info-row"><span class="lbl">Submitted:</span><strong><?= fDate($req['created_at']) ?></strong></div>
</div>

<?php if (!empty($req['disability_notes'])): ?>
<div class="disability-box">
  <strong style="font-size:8pt">Additional Notes:</strong> <?= h($req['disability_notes']) ?>
</div>
<?php endif; ?>

<?php if (!empty($ad)): ?>

<!-- Q1 -->
<div class="sec-hdr">Q1 — Diagnosed Conditions</div>
<table class="form-tbl">
  <thead><tr><th>Condition</th><th class="text-center" style="width:60px">Answer</th><th class="text-center" style="width:70px">Documents</th></tr></thead>
  <tbody>
  <?php foreach ($q1Items as $k => $lbl):
    $v   = $ad['q1'][$k] ?? [];
    $yes = ($v['ans'] ?? 'no') === 'yes';
    $hv  = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  ?>
  <tr>
    <td><?= $hv($lbl) ?><?= !empty($v['specify']) ? ' <em style="color:#64748b">('.$hv($v['specify']).')</em>' : '' ?></td>
    <td class="text-center"><?= $yes ? '<span class="badge-yes">Yes</span>' : '<span class="badge-no">No</span>' ?></td>
    <td class="text-center"><?= !empty($v['docs']) ? '&#10003;' : '—' ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<!-- Q2 -->
<div class="sec-hdr" style="margin-top:8px">Q2 — Current Circumstances / Experiences</div>
<table class="form-tbl">
  <thead><tr><th>Circumstance</th><th class="text-center" style="width:60px">Answer</th><th>Details</th></tr></thead>
  <tbody>
  <?php foreach ($q2Items as $k => $meta):
    $v   = $ad['q2'][$k] ?? [];
    $yes = ($v['ans'] ?? 'no') === 'yes';
    $spec = $v['specify'] ?? '';
    $hv  = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  ?>
  <tr>
    <td><?= $hv($meta['label']) ?></td>
    <td class="text-center"><?= $yes ? '<span class="badge-yes">Yes</span>' : '<span class="badge-no">No</span>' ?></td>
    <td><?= $spec ? $hv($spec) : '<span style="color:#9ca3af">—</span>' ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<!-- Q3 -->
<div class="sec-hdr" style="margin-top:8px">Q3 — Professional Assessment &amp; Support History</div>
<table class="form-tbl">
  <thead><tr><th>Assessment / Support</th><th class="text-center" style="width:60px">Answer</th><th>Details</th></tr></thead>
  <tbody>
  <?php foreach ($q3Items as $k => $meta):
    $v   = $ad['q3'][$k] ?? [];
    $yes = ($v['ans'] ?? 'no') === 'yes';
    $spec = $v['specify'] ?? '';
    $hv  = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  ?>
  <tr>
    <td><?= $hv($meta['label']) ?></td>
    <td class="text-center"><?= $yes ? '<span class="badge-yes">Yes</span>' : '<span class="badge-no">No</span>' ?></td>
    <td><?= $spec ? $hv($spec) : '<span style="color:#9ca3af">—</span>' ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<!-- Q4 -->
<div class="sec-hdr" style="margin-top:8px">Q4 — High Academic Ability / Gifted</div>
<table class="form-tbl">
  <thead><tr><th>Indicator</th><th class="text-center" style="width:60px">Answer</th></tr></thead>
  <tbody>
  <?php foreach ($q4Labels as $k => $lbl):
    $yes = (($ad['q4'][$k]['ans'] ?? 'no') === 'yes');
    $hv  = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  ?>
  <tr>
    <td><?= $hv($lbl) ?></td>
    <td class="text-center"><?= $yes ? '<span class="badge-yes">Yes</span>' : '<span class="badge-no">No</span>' ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php else: ?>
<div style="padding:14px;background:#fffbeb;border:1px solid #fde68a;border-radius:5px;margin-top:10px;font-size:8.5pt">
  <strong>Assessment Data:</strong> No detailed assessment form was submitted for this request.
</div>
<?php endif; ?>

<!-- Review Notes -->
<?php if (!empty($req['review_notes'])): ?>
<div class="sec-hdr" style="margin-top:8px">Review Notes</div>
<div class="notes-box"><?= h($req['review_notes']) ?></div>
<?php endif; ?>

<!-- Reviewed by -->
<?php if (!empty($req['reviewed_by_name'])): ?>
<div style="font-size:8pt;color:#64748b;margin-top:6px">
  Reviewed by <strong><?= h($req['reviewed_by_name']) ?></strong>
  <?php if ($req['reviewed_at']): ?> on <?= fDate($req['reviewed_at']) ?><?php endif; ?>
</div>
<?php endif; ?>

<!-- Signatures -->
<div class="sig-row">
  <div class="sig-box">
    <div class="sig-line"></div>
    <div class="sig-lbl">Parent / Guardian Signature</div>
  </div>
  <div class="sig-box">
    <div class="sig-line"></div>
    <div class="sig-lbl">Receiving Officer</div>
  </div>
  <div class="sig-box">
    <div class="sig-line"></div>
    <div class="sig-lbl">Student Affairs — HOD</div>
  </div>
  <div class="sig-box">
    <div class="sig-line"></div>
    <div class="sig-lbl">Vice Principal</div>
  </div>
</div>

</body>
</html>

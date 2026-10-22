<?php
/**
 * Class result — Word (.doc) download.
 * Uses the HTML-as-msword pattern consistent with the rest of the portal.
 * Access: vp_main (vp_publish_results) | admin | examination_head (eh_results)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/result_ranking.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('vp_main', 'admin', 'examination_head');
if ($user['role'] === 'vp_main')          requirePermission('vp_publish_results');
if ($user['role'] === 'examination_head') requirePermission('eh_results');

$db      = getDB();
$classId = (int)($_GET['class_id'] ?? 0);
if (!$classId) { redirect('/portal/vp/result-approvals.php?tab=published'); }

$classRow = null;
try {
    $cSt = $db->prepare(
        "SELECT id, name FROM classes
         WHERE id=? AND COALESCE(wing,'main')='main' AND COALESCE(is_ilc,0)=0 AND COALESCE(is_montessori,0)=0"
    );
    $cSt->execute([$classId]);
    $classRow = $cSt->fetch();
} catch (Exception $e) {}
if (!$classRow) { redirect('/portal/vp/result-approvals.php?tab=published'); }

$students = getClassResultData($db, $classId, true);
$subjectOrder = [];
foreach ($students as $stu) {
    foreach ($stu['subjects'] as $sid => $sub) {
        if (!isset($subjectOrder[$sid])) $subjectOrder[$sid] = $sub['name'];
    }
}
asort($subjectOrder);

$schoolName  = getSetting('school_name', 'Bahria Model College');
$sessionYear = getSetting('session_year', SESSION_YEAR);
$currentTerm = getSetting('current_term', '');
$exportDate  = date('d F Y');
$className   = $classRow['name'];

// Logo as base64
$logoPath = __DIR__ . '/../../assets/bmc-logo.png';
$logoSrc  = file_exists($logoPath)
    ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
    : '';

$withMarks = array_filter($students, fn($s) => $s['grand_max'] > 0);
$passCount = count(array_filter($withMarks, fn($s) => !empty($s['overall_pass'])));
$avgPct    = count($withMarks) > 0
    ? round(array_sum(array_column($withMarks, 'grand_pct')) / count($withMarks), 1)
    : 0.0;

$fname = 'Results_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $className) . '_' . date('Y-m-d') . '.doc';
header('Content-Type: application/msword');
header('Content-Disposition: attachment; filename="' . addslashes($fname) . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

function wGrBg(string $g): string { return gradeBgFor($g); }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Class Results — <?= h($className) ?></title>
<style>
body { font-family:Arial,Helvetica,sans-serif; font-size:11pt; margin:20pt 25pt }
table { border-collapse:collapse; width:100%; margin-bottom:14pt }
th { background:#1f3864; color:#fff; padding:5pt 6pt; font-size:9pt; text-align:center; border:1pt solid #aaa }
td { padding:4pt 6pt; font-size:9.5pt; border:1pt solid #ccc; vertical-align:middle }
tr:nth-child(even) td { background:#f8f9ff }
.hdr-table td { border:none; vertical-align:middle; padding:3pt 6pt }
.school-name { font-size:16pt; font-weight:bold; color:#1f3864; text-transform:uppercase }
.doc-title { font-size:12pt; font-weight:bold; color:#1f3864; text-align:center; padding:6pt 0;
             border-top:3pt solid #b8860b; border-bottom:3pt solid #b8860b; margin:10pt 0 }
.kpi-table td { border:1pt solid #ddd; text-align:center; padding:6pt; background:#f0f4ff }
.kpi-val { font-size:14pt; font-weight:bold; color:#1f3864 }
.kpi-lbl { font-size:8pt; color:#64748b }
.sec-hdr { background:#1f3864; color:#fff; font-weight:bold; font-size:10pt; padding:5pt 8pt; }
.pos-row-1 td { background:#fffbeb }
.pos-row-2 td { background:#f9fafb }
.pos-row-3 td { background:#fff7ed }
.pass-badge { background:#dcfce7; color:#166534; padding:1pt 5pt; border-radius:3pt; font-weight:bold; font-size:9pt }
.fail-badge { background:#fee2e2; color:#991b1b; padding:1pt 5pt; border-radius:3pt; font-weight:bold; font-size:9pt }
.footer { border-top:2pt solid #1f3864; margin-top:16pt; padding-top:6pt; font-size:8pt; color:#64748b; text-align:center }
.grade-badge { padding:1pt 5pt; border-radius:2pt; font-weight:bold; font-size:9pt }
</style>
</head>
<body>

<!-- Header -->
<table class="hdr-table" style="margin-bottom:8pt">
  <tr>
    <?php if ($logoSrc): ?>
    <td style="width:70pt"><img src="<?= $logoSrc ?>" width="60" height="60" alt="BMC"></td>
    <?php endif; ?>
    <td>
      <div class="school-name"><?= h($schoolName) ?></div>
      <div style="font-size:9pt;color:#64748b;margin-top:2pt">Main Campus</div>
    </td>
    <td style="text-align:right;font-size:9pt;color:#374151;line-height:1.8">
      <strong style="display:block;font-size:10pt;color:#1f3864">Class Result Report</strong>
      Class: <?= h($className) ?><br>
      <?= $sessionYear ? 'Session: ' . h($sessionYear) . '<br>' : '' ?>
      <?= $currentTerm ? 'Term: ' . h($currentTerm) . '<br>' : '' ?>
      Date: <?= $exportDate ?>
    </td>
  </tr>
</table>

<div class="doc-title">
  &#9670; Examination Result Report &#9670;<br>
  <span style="font-size:9pt;font-weight:normal"><?= h($className) ?><?= $sessionYear ? ' &mdash; ' . h($sessionYear) : '' ?></span>
</div>

<!-- KPI -->
<table class="kpi-table" style="margin-bottom:12pt">
  <tr>
    <td><div class="kpi-val"><?= count($students) ?></div><div class="kpi-lbl">Total Students</div></td>
    <td><div class="kpi-val" style="color:#166534"><?= $passCount ?></div><div class="kpi-lbl">Passed</div></td>
    <td><div class="kpi-val"><?= count($students) - $passCount ?></div><div class="kpi-lbl">Failed</div></td>
    <td><div class="kpi-val"><?= $avgPct ?>%</div><div class="kpi-lbl">Class Average</div></td>
    <td><div class="kpi-val"><?= count($subjectOrder) ?></div><div class="kpi-lbl">Subjects</div></td>
  </tr>
</table>

<!-- Results table -->
<div class="sec-hdr">Student-Wise Result Details</div>
<table>
  <thead>
    <tr>
      <th>#</th>
      <th style="text-align:left;min-width:120pt">Student Name</th>
      <th>Roll No</th>
      <th>Position</th>
      <?php foreach ($subjectOrder as $sid => $sn): ?>
      <th colspan="2"><?= h($sn) ?></th>
      <?php endforeach; ?>
      <th>Total<br>Obt/Max</th>
      <th>%</th>
      <th>Grade</th>
      <th>Status</th>
    </tr>
    <tr>
      <th></th><th></th><th></th><th></th>
      <?php foreach ($subjectOrder as $sid => $sn): ?><th>Obt</th><th>Max</th><?php endforeach; ?>
      <th></th><th></th><th></th><th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($students as $idx => $stu):
    $pos  = $stu['position'];
    $posC = match($pos) { 1=>'pos-row-1', 2=>'pos-row-2', 3=>'pos-row-3', default=>'' };
    $hasM = $stu['grand_max'] > 0;
    $grd  = $stu['overall_grade'];
  ?>
  <tr class="<?= $posC ?>">
    <td style="text-align:center;color:#94a3b8"><?= $idx+1 ?></td>
    <td style="font-weight:bold"><?= h($stu['name']) ?></td>
    <td style="text-align:center"><?= h($stu['roll_no'] ?? '—') ?></td>
    <td style="text-align:center;font-weight:bold">
      <?= $pos !== null ? positionLabel($pos) : '—' ?>
    </td>
    <?php foreach ($subjectOrder as $sid => $sn):
      $sub = $stu['subjects'][$sid] ?? null;
      if ($sub && $sub['max'] > 0): ?>
    <td style="text-align:center"><?= round($sub['obtained'],2) ?></td>
    <td style="text-align:center;color:#64748b"><?= round($sub['max'],2) ?></td>
    <?php else: ?><td colspan="2" style="text-align:center;color:#d97706">—</td><?php endif; ?>
    <?php endforeach; ?>
    <td style="text-align:center;font-weight:bold">
      <?= $hasM ? round($stu['grand_obtained'],2).'/'.round($stu['grand_max'],2) : '—' ?>
    </td>
    <td style="text-align:center;font-weight:bold;color:#1f3864">
      <?= $hasM ? $stu['grand_pct'].'%' : '—' ?>
    </td>
    <td style="text-align:center">
      <?php if ($hasM): ?>
      <span class="grade-badge" style="background:<?= wGrBg($grd) ?>"><?= $grd ?></span>
      <?php else: ?>—<?php endif; ?>
    </td>
    <td style="text-align:center">
      <?php if ($hasM): ?>
      <?php if (!empty($stu['overall_pass'])): ?><span class="pass-badge">Pass</span>
      <?php else: ?><span class="fail-badge" title="<?= h($stu['pass_status'] ?? 'Fail') ?>">Fail</span><?php endif; ?>
      <?php else: ?>—<?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<!-- Grade scale -->
<p style="font-size:8pt;color:#374151;margin-top:8pt">
  <strong>Grade Scale:</strong>
  A+ (90–100%) | A (80–89%) | B (70–79%) | C (60–69%) | D (50–59%) | F (&lt;50%)
</p>

<div class="footer">
  <?= h($schoolName) ?> &bull; Main Campus &bull; Class Result Report &bull;
  Generated: <?= $exportDate ?> &bull; Confidential — Authorised Personnel Only
</div>
</body>
</html>

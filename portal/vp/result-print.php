<?php
/**
 * Professional class result print/PDF view — Bahria Model College branding.
 * Access: vp_main (vp_publish_results) | admin | examination_head (eh_results)
 *
 * GET ?class_id=N  → render print-ready HTML (use browser Print → Save as PDF)
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

// ── Validate class ────────────────────────────────────────────────────────────
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

// ── Subject list ──────────────────────────────────────────────────────────────
$subjectOrder = [];
foreach ($students as $stu) {
    foreach ($stu['subjects'] as $sid => $sub) {
        if (!isset($subjectOrder[$sid])) $subjectOrder[$sid] = $sub['name'];
    }
}
asort($subjectOrder);

// ── Settings ──────────────────────────────────────────────────────────────────
$schoolName   = getSetting('school_name', 'Bahria Model College');
$schoolAddr   = getSetting('school_address', '');
$sessionYear  = getSetting('session_year', SESSION_YEAR);
$currentTerm  = getSetting('current_term', '');
$exportDate   = date('d F Y');
$className    = $classRow['name'];
$base         = defined('BASE_URL') ? BASE_URL : '';
$logoPath     = __DIR__ . '/../../assets/bmc-logo.png';
$logoSrc      = file_exists($logoPath)
    ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
    : '';

// ── Stats ─────────────────────────────────────────────────────────────────────
$withMarks = array_filter($students, fn($s) => $s['grand_max'] > 0);
$passCount = count(array_filter($withMarks, fn($s) => $s['grand_pct'] >= 50));
$avgPct    = count($withMarks) > 0
    ? round(array_sum(array_column($withMarks, 'grand_pct')) / count($withMarks), 1)
    : 0.0;

function printGradeBg(string $g): string {
    return match($g) {
        'A++','A+' => '#dcfce7', 'A' => '#bbf7d0',
        'B++','B+' => '#dbeafe', 'B' => '#e0f2fe',
        'C' => '#fef9c3', 'D' => '#ffedd5', 'E','U' => '#fee2e2',
        default => '#f3f4f6',
    };
}
function printGradeColor(string $g): string {
    return match($g) {
        'A++','A+','A' => '#14532d',
        'B++','B+','B' => '#1e3a8a',
        'C' => '#713f12', 'D' => '#7c2d12', 'E','U' => '#7f1d1d',
        default => '#374151',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Class Results — <?= h($className) ?> — <?= h($schoolName) ?></title>
<style>
/* ── Variables ─────────────────────────────────── */
:root {
  --navy:   #0f2456;
  --navy2:  #1a3a6b;
  --gold:   #b8860b;
  --gold2:  #d4a820;
  --ink:    #1e293b;
  --muted:  #64748b;
  --border: #d1d5db;
}
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0 }
body { font-family:'Segoe UI',Arial,Helvetica,sans-serif; background:#c8d3e4; color:var(--ink); font-size:12px }

/* ── Screen toolbar ────────────────────────────── */
.no-print { background:var(--navy); color:#fff; padding:10px 18px;
            display:flex; align-items:center; gap:10px; flex-wrap:wrap;
            position:sticky; top:0; z-index:200; }
.no-print a, .no-print button {
  color:#fff; font-size:12px; text-decoration:none;
  background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.3);
  border-radius:4px; padding:5px 14px; cursor:pointer;
  display:inline-flex; align-items:center; gap:6px; white-space:nowrap; }
.no-print a:hover,.no-print button:hover { background:rgba(255,255,255,.25) }
.no-print .ttl { flex:1; font-weight:700; font-size:13px }

/* ── Page wrapper ──────────────────────────────── */
.page-wrap { max-width:1100px; margin:20px auto; padding:0 14px 40px }
.paper {
  background:#fff;
  box-shadow:0 6px 28px rgba(15,36,86,.18);
  margin-bottom:28px;
}

/* ── Stripes ───────────────────────────────────── */
.stripe-gold  { height:3px; background:var(--gold) }
.stripe-navy  { height:6px; background:var(--navy) }
.gold-rule    { height:4px; background:linear-gradient(90deg,var(--navy) 0%,var(--gold) 35%,var(--gold2) 50%,var(--gold) 65%,var(--navy) 100%) }

/* ── Letterhead ────────────────────────────────── */
.rc-head {
  padding:16px 24px 14px;
  display:grid; grid-template-columns:72px 1fr auto;
  gap:0 16px; align-items:center;
  border-bottom:1px solid var(--border);
}
.rc-head img { width:72px; height:72px; object-fit:contain }
.rc-head-init { width:72px; height:72px; border-radius:50%;
  background:var(--navy); color:#fff; display:flex; align-items:center;
  justify-content:center; font-size:1.3rem; font-weight:800; border:2px solid var(--gold) }
.school-name { font-size:1.2rem; font-weight:800; color:var(--navy);
               text-transform:uppercase; letter-spacing:.03em; line-height:1.1 }
.school-addr { font-size:.71rem; color:var(--muted); margin-top:3px }
.campus-pill { display:inline-block; background:var(--navy); color:#fff;
               font-size:.6rem; font-weight:600; padding:2px 12px;
               border-radius:20px; margin-top:6px; letter-spacing:.06em; text-transform:uppercase }
.head-meta   { text-align:right; font-size:.71rem; color:var(--muted); line-height:1.8 }
.head-meta .doc-lbl { display:block; font-size:.8rem; font-weight:800; color:var(--navy);
                      text-transform:uppercase; letter-spacing:.07em; margin-bottom:2px }

/* ── Title band ────────────────────────────────── */
.title-band { background:var(--navy); color:#fff; text-align:center; padding:9px 20px }
.title-main { font-size:.87rem; font-weight:700; letter-spacing:.18em; text-transform:uppercase }
.title-sub  { font-size:.63rem; color:rgba(255,255,255,.65); margin-top:2px }
.dmd { color:var(--gold2); margin:0 8px }

/* ── KPI row ───────────────────────────────────── */
.kpi-row { display:grid; grid-template-columns:repeat(4,1fr); border-bottom:2px solid var(--border) }
.kpi-cell { text-align:center; padding:10px 8px; border-right:1px solid var(--border) }
.kpi-cell:last-child { border-right:none }
.kpi-val  { font-size:1.3rem; font-weight:800; color:var(--navy) }
.kpi-sub  { font-size:.65rem; color:var(--muted); margin-top:2px }
.kpi-lbl  { font-size:.58rem; color:#94a3b8; text-transform:uppercase; letter-spacing:.06em; margin-top:4px }

/* ── Section heading ───────────────────────────── */
.sec { background:var(--navy); color:#fff; padding:5px 20px;
       font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.12em;
       display:flex; align-items:center; gap:8px }
.sec i { color:var(--gold2) }

/* ── Result table ──────────────────────────────── */
.rt-wrap { overflow-x:auto }
.rt { width:100%; border-collapse:collapse; font-size:10.5px }
.rt thead th {
  background:var(--navy); color:#fff;
  padding:5px 8px; font-size:.58rem; font-weight:600; text-transform:uppercase;
  letter-spacing:.05em; white-space:nowrap; border-right:1px solid rgba(255,255,255,.1);
  text-align:center; position:sticky; top:0;
}
.rt thead th.left { text-align:left }
.rt thead th:last-child { border-right:none }
.rt tbody tr:nth-child(even) td { background:#f8fafc }
.rt tbody tr.gold1 td { background:#fffbeb }
.rt tbody tr.gold2 td { background:#f9fafb }
.rt tbody tr.gold3 td { background:#fffdf5 }
.rt tbody tr:hover td { background:#eef2ff }
.rt td {
  padding:5px 8px; border-bottom:1px solid #e5e7eb; border-right:1px solid #e5e7eb;
  vertical-align:middle;
}
.rt td:last-child { border-right:none }
.rt tfoot td {
  background:linear-gradient(90deg,#0f2456,#1a3a6b);
  color:#fff; font-weight:700; padding:6px 8px; border-right:1px solid rgba(255,255,255,.1);
}
.rt tfoot td:last-child { border-right:none }
.gr-badge { display:inline-block; padding:1px 7px; border-radius:3px;
            font-size:.68rem; font-weight:700; min-width:28px; text-align:center }
.pos-chip { display:inline-block; padding:1px 8px; border-radius:20px;
            font-size:.7rem; font-weight:700; }
.pos-1 { background:#fffbeb; color:#92400e; border:1px solid #fcd34d }
.pos-2 { background:#f9fafb; color:#374151; border:1px solid #d1d5db }
.pos-3 { background:#fff7ed; color:#7c2d12; border:1px solid #fed7aa }
.missing { color:#d97706; font-style:italic }
.pass-badge { background:#dcfce7; color:#166534; border-radius:3px; padding:1px 6px;
              font-size:.68rem; font-weight:700 }
.fail-badge { background:#fee2e2; color:#991b1b; border-radius:3px; padding:1px 6px;
              font-size:.68rem; font-weight:700 }

/* ── Position holders section ──────────────────── */
.pos-holder-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; padding:14px 20px }
.pos-card { border-radius:10px; padding:14px 16px; text-align:center; border:2px solid transparent }
.pos-card.p1 { background:#fffbeb; border-color:#fcd34d }
.pos-card.p2 { background:#f3f4f6; border-color:#d1d5db }
.pos-card.p3 { background:#fff7ed; border-color:#fed7aa }
.pos-trophy { font-size:1.5rem; margin-bottom:6px }
.pos-label  { font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:var(--muted); margin-bottom:4px }
.pos-name   { font-size:.88rem; font-weight:700; color:var(--navy) }
.pos-pct    { font-size:.76rem; color:var(--muted); margin-top:3px }

/* ── Grade scale ───────────────────────────────── */
.gscale { display:flex; flex-wrap:wrap; gap:6px; padding:8px 20px;
          background:#f9fafb; border-top:1px solid var(--border); font-size:.62rem; align-items:center }
.gscale strong { font-size:.62rem; text-transform:uppercase; letter-spacing:.05em }
.gs-item { display:flex; align-items:center; gap:3px }
.gs-rng { color:var(--muted); font-size:.6rem }

/* ── Footer ────────────────────────────────────── */
.rc-foot {
  background:var(--navy); color:rgba(255,255,255,.65);
  font-size:.6rem; padding:7px 20px;
  display:flex; justify-content:space-between; align-items:center;
  flex-wrap:wrap; gap:4px;
}
.rc-stamp { background:#166534; color:#dcfce7; font-size:.58rem; font-weight:700;
            padding:2px 10px; border-radius:20px; letter-spacing:.07em; text-transform:uppercase }

/* ── Print ─────────────────────────────────────── */
@media print {
  @page { size:A3 landscape; margin:8mm 10mm }
  *  { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important }
  body { background:#fff !important }
  .no-print { display:none !important }
  .page-wrap { max-width:100%; padding:0; margin:0 }
  .paper { box-shadow:none; margin-bottom:14mm }
  .rt thead th { position:static }
  .pos-holder-grid { page-break-inside:avoid }
}
</style>
</head>
<body>

<!-- Toolbar (hidden on print) -->
<div class="no-print">
  <a href="javascript:history.back()">&#8592; Back</a>
  <span class="ttl">Class Results — <?= h($className) ?></span>
  <a href="result-export.php?class_id=<?= $classId ?>">
    <i class="fas fa-file-excel"></i> Download Excel
  </a>
  <a href="result-word.php?class_id=<?= $classId ?>">
    <i class="fas fa-file-word"></i> Download Word
  </a>
  <button onclick="window.print()">
    <i class="fas fa-print"></i> Print / Save PDF
  </button>
</div>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<div class="page-wrap">
<div class="paper">

  <div class="stripe-gold"></div>
  <div class="stripe-navy"></div>
  <div class="stripe-gold"></div>

  <!-- Letterhead -->
  <div class="rc-head">
    <div>
      <?php if ($logoSrc): ?>
      <img src="<?= $logoSrc ?>" alt="BMC Logo"
           onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
      <div class="rc-head-init" style="display:none">BMC</div>
      <?php else: ?>
      <div class="rc-head-init">BMC</div>
      <?php endif; ?>
    </div>
    <div>
      <div class="school-name"><?= h($schoolName) ?></div>
      <?php if ($schoolAddr): ?><div class="school-addr"><?= h($schoolAddr) ?></div><?php endif; ?>
      <span class="campus-pill">Main Campus</span>
    </div>
    <div class="head-meta">
      <span class="doc-lbl">Class Result Report</span>
      Class: <?= h($className) ?><br>
      <?php if ($sessionYear): ?>Session: <?= h($sessionYear) ?><br><?php endif; ?>
      <?php if ($currentTerm): ?>Term: <?= h($currentTerm) ?><br><?php endif; ?>
      Date: <?= $exportDate ?>
    </div>
  </div>

  <div class="gold-rule"></div>

  <div class="title-band">
    <div class="title-main">
      <span class="dmd">◆</span>Examination Result Report<span class="dmd">◆</span>
    </div>
    <div class="title-sub">
      <?php if ($sessionYear): ?>Academic Session <?= h($sessionYear) ?><span class="dmd">◆</span><?php endif; ?>
      Main Campus — <?= h($className) ?>
    </div>
  </div>

  <div class="gold-rule"></div>

  <!-- KPI row -->
  <div class="kpi-row">
    <div class="kpi-cell">
      <div class="kpi-val"><?= count($students) ?></div>
      <div class="kpi-sub">students</div>
      <div class="kpi-lbl">Class Strength</div>
    </div>
    <div class="kpi-cell">
      <div class="kpi-val" style="color:#166534"><?= $passCount ?></div>
      <div class="kpi-sub">students passed</div>
      <div class="kpi-lbl">Pass Count</div>
    </div>
    <div class="kpi-cell">
      <div class="kpi-val" style="color:var(--navy)"><?= $avgPct ?>%</div>
      <div class="kpi-sub">average</div>
      <div class="kpi-lbl">Class Average</div>
    </div>
    <div class="kpi-cell">
      <div class="kpi-val" style="color:var(--muted)"><?= count($subjectOrder) ?></div>
      <div class="kpi-sub">subjects</div>
      <div class="kpi-lbl">Total Subjects</div>
    </div>
  </div>

  <!-- Position holders -->
  <?php if (!empty($top3)): ?>
  <div class="sec"><i class="fas fa-trophy"></i>Position Holders</div>
  <div class="pos-holder-grid">
    <?php
    $posConfig = [
      1 => ['emoji'=>'🥇','class'=>'p1','label'=>'First Position'],
      2 => ['emoji'=>'🥈','class'=>'p2','label'=>'Second Position'],
      3 => ['emoji'=>'🥉','class'=>'p3','label'=>'Third Position'],
    ];
    foreach ($posConfig as $rank => $cfg):
      if (!isset($top3[$rank])) continue;
      // Find student data
      $topStu = null;
      foreach ($students as $s) { if ($s['position'] === $rank) { $topStu = $s; break; } }
    ?>
    <div class="pos-card <?= $cfg['class'] ?>">
      <div class="pos-trophy"><?= $cfg['emoji'] ?></div>
      <div class="pos-label"><?= $cfg['label'] ?></div>
      <?php foreach ($top3[$rank] as $nm): ?>
      <div class="pos-name"><?= h($nm) ?></div>
      <?php endforeach; ?>
      <?php if ($topStu): ?>
      <div class="pos-pct"><?= $topStu['grand_pct'] ?>% &mdash; <?= $topStu['overall_grade'] ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="gold-rule"></div>
  <?php endif; ?>

  <!-- Results table -->
  <div class="sec"><i class="fas fa-table"></i>Student-Wise Result Details</div>
  <div class="rt-wrap">
    <table class="rt">
      <thead>
        <tr>
          <th>#</th>
          <th class="left">Student Name</th>
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
          <?php foreach ($subjectOrder as $sid => $sn): ?>
          <th>Obt</th><th>Max</th>
          <?php endforeach; ?>
          <th></th><th></th><th></th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($students as $idx => $stu):
        $pos    = $stu['position'];
        $posRow = match($pos) { 1=>'gold1', 2=>'gold2', 3=>'gold3', default=>'' };
        $hasM   = $stu['grand_max'] > 0;
        $grd    = $stu['overall_grade'];
      ?>
      <tr class="<?= $posRow ?>">
        <td style="text-align:center;color:#94a3b8"><?= $idx+1 ?></td>
        <td style="font-weight:600"><?= h($stu['name']) ?></td>
        <td style="text-align:center"><?= h($stu['roll_no'] ?? '—') ?></td>
        <td style="text-align:center">
          <?php if ($pos !== null && $pos <= 3): ?>
          <span class="pos-chip pos-<?= $pos ?>"><?= positionLabel($pos) ?></span>
          <?php elseif ($pos !== null): ?>
          <span style="color:#64748b"><?= positionLabel($pos) ?></span>
          <?php else: ?><span class="missing">—</span><?php endif; ?>
        </td>
        <?php foreach ($subjectOrder as $sid => $sn):
          $sub = $stu['subjects'][$sid] ?? null;
          if ($sub && $sub['max'] > 0): ?>
        <td style="text-align:center"><?= round($sub['obtained'],1) ?></td>
        <td style="text-align:center;color:#94a3b8"><?= (int)$sub['max'] ?></td>
          <?php else: ?>
        <td colspan="2" style="text-align:center"><span class="missing">—</span></td>
          <?php endif; ?>
        <?php endforeach; ?>
        <td style="text-align:center;font-weight:600">
          <?= $hasM ? round($stu['grand_obtained'],1).'/'.(int)$stu['grand_max'] : '<span class="missing">—</span>' ?>
        </td>
        <td style="text-align:center;font-weight:700;color:<?= $hasM ? 'var(--navy)' : '#9ca3af' ?>">
          <?= $hasM ? $stu['grand_pct'].'%' : '—' ?>
        </td>
        <td style="text-align:center">
          <?php if ($hasM): ?>
          <span class="gr-badge" style="background:<?= printGradeBg($grd) ?>;color:<?= printGradeColor($grd) ?>"><?= $grd ?></span>
          <?php else: ?><span class="missing">—</span><?php endif; ?>
        </td>
        <td style="text-align:center">
          <?php if ($hasM): ?>
          <?php if ($stu['grand_pct'] >= 50): ?>
          <span class="pass-badge">Pass</span>
          <?php else: ?>
          <span class="fail-badge">Fail</span>
          <?php endif; ?>
          <?php else: ?><span class="missing">—</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4">CLASS TOTALS / AVERAGES</td>
          <?php foreach ($subjectOrder as $sid => $sn):
            $subObt = 0; $subMax = 0;
            foreach ($students as $stu) {
                $sub = $stu['subjects'][$sid] ?? null;
                if ($sub && $sub['max'] > 0) { $subObt += $sub['obtained']; $subMax += $sub['max']; }
            }
          ?>
          <td style="text-align:center"><?= $subMax > 0 ? round($subObt,0) : '—' ?></td>
          <td style="text-align:center"><?= $subMax > 0 ? round($subMax,0) : '—' ?></td>
          <?php endforeach; ?>
          <td colspan="4" style="text-align:center">Avg: <?= $avgPct ?>%</td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Grade scale -->
  <div class="gscale">
    <strong>Grade Scale:</strong>
    <?php foreach (['A++'=>'95–100%','A+'=>'90–94%','A'=>'85–89%','B++'=>'80–84%','B+'=>'75–79%','B'=>'70–74%','C'=>'60–69%','D'=>'50–59%','E'=>'40–49%','U'=>'< 40%'] as $g => $r): ?>
    <span class="gs-item">
      <span class="gr-badge" style="background:<?= printGradeBg($g) ?>;color:<?= printGradeColor($g) ?>;padding:1px 6px;font-size:.6rem"><?= $g ?></span>
      <span class="gs-rng"><?= $r ?></span>
    </span>
    <?php endforeach; ?>
  </div>

  <!-- Footer -->
  <div class="rc-foot">
    <span><?= h($schoolName) ?> &bull; Main Campus</span>
    <span class="rc-stamp">Official Document</span>
    <span>Generated: <?= $exportDate ?> &bull; Confidential</span>
  </div>

  <div class="stripe-gold"></div>
  <div class="stripe-navy"></div>
  <div class="stripe-gold"></div>

</div><!-- /paper -->
</div><!-- /page-wrap -->
</body>
</html>

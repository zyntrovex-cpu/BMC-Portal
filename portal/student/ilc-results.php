<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('student');
$student = getStudentByUserId($user['id']);
$db      = getDB();

if (!$student) { setFlash('danger', 'Student profile not found.'); redirect('/portal/logout.php'); }

$tableExists = false;
$assessments = [];
try { $db->query('SELECT 1 FROM ilc_student_assessments LIMIT 1'); $tableExists = true; } catch (Exception $e) {}

if ($tableExists) {
    $st = $db->prepare(
        'SELECT * FROM ilc_student_assessments WHERE student_id = ? ORDER BY date ASC, id ASC'
    );
    $st->execute([$student['id']]);
    $assessments = $st->fetchAll();
}

// ── Calculation (same logic as main campus results.php) ───────────
$totalWeight  = 0;
$weightedSum  = 0;
$totalObt     = 0;
$totalMax     = 0;
$countScored  = 0;
$countPending = 0;

foreach ($assessments as $a) {
    if ($a['marks_obtained'] !== null) {
        $countScored++;
        $totalObt += $a['marks_obtained'];
        $totalMax += $a['max_marks'];
        if ($a['weight'] > 0) {
            $pct          = $a['max_marks'] > 0 ? ($a['marks_obtained'] / $a['max_marks'] * 100) : 0;
            $weightedSum += $pct * $a['weight'];
            $totalWeight += $a['weight'];
        }
    } else {
        $countPending++;
    }
}

$overallPct = $totalWeight > 0
    ? round($weightedSum / $totalWeight, 1)
    : ($totalMax > 0 ? round($totalObt / $totalMax * 100, 1) : 0);

$hasMarks = $totalMax > 0;

$typeColors = [
    'Quiz'       => 'bg-info text-dark',
    'Assignment' => 'bg-secondary',
    'Midterm'    => 'bg-warning text-dark',
    'Mid Term'   => 'bg-warning text-dark',
    'Final Term' => 'bg-danger',
    'Test'       => 'bg-primary',
    'Practical'  => 'bg-success',
];

pageHead('My Results', 'student');
$links = getStudentLinks();
?>
<div class="portal-wrap">
<?php sidebar('student', 'ilc-results', $links, $user); ?>
<div class="main-area">
<?php topbar('My Results', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0" style="color:#1d4ed8"><i class="fas fa-chart-bar me-2"></i>My Results</h4>
    <small class="text-muted">
      <?= h($student['name']) ?> &nbsp;·&nbsp; <?= h($student['class_name'] ?? 'ILC') ?>
      <?php if (!empty($student['roll_no'])): ?>&nbsp;·&nbsp; Roll: <?= h($student['roll_no']) ?><?php endif; ?>
    </small>
  </div>
  <a href="<?= url('/portal/student/ilc-assessments.php') ?>" class="btn btn-outline-primary btn-sm">
    <i class="fas fa-clipboard-list me-1"></i>All Assessments
  </a>
</div>

<?php if (!$tableExists || empty($assessments)): ?>
<div class="sec-card">
  <div class="sec-body text-center text-muted py-4">
    <i class="fas fa-chart-bar fa-2x mb-2 d-block opacity-25"></i>
    No assessment data available yet.
  </div>
</div>
<?php else: ?>

<!-- ── Overall Summary Card ── -->
<div class="sec-card mb-4">
  <div class="sec-head">
    <h5><i class="fas fa-award me-2" style="color:#1d4ed8"></i>Overall Performance</h5>
  </div>
  <div style="padding:20px">
    <div class="row g-3 text-center">
      <div class="col-6 col-md-3">
        <div style="background:#eff6ff;border-radius:10px;padding:16px 10px">
          <div style="font-size:1.6rem;font-weight:800;color:#1d4ed8"><?= $overallPct ?>%</div>
          <div style="font-size:.78rem;color:#64748b;margin-top:2px">Overall Percentage</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div style="background:#f0fdf4;border-radius:10px;padding:16px 10px">
          <div style="font-size:1.6rem;font-weight:800"><?= gradeHtml($overallPct) ?></div>
          <div style="font-size:.78rem;color:#64748b;margin-top:4px">Overall Grade</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div style="background:#fefce8;border-radius:10px;padding:16px 10px">
          <div style="font-size:1.6rem;font-weight:800;color:#ca8a04">
            <?= $totalObt ?> / <?= $totalMax ?>
          </div>
          <div style="font-size:.78rem;color:#64748b;margin-top:2px">Total Marks</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div style="background:#f8fafc;border-radius:10px;padding:16px 10px">
          <div style="font-size:1.6rem;font-weight:800;color:#334155">
            <?= count($assessments) ?>
          </div>
          <div style="font-size:.78rem;color:#64748b;margin-top:2px">
            Assessments
            <?php if ($countPending > 0): ?>
            <br><span style="color:#f59e0b;font-size:.72rem">(<?= $countPending ?> pending)</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Progress bar -->
    <?php if ($hasMarks): ?>
    <div style="margin-top:16px">
      <div style="display:flex;justify-content:space-between;font-size:.78rem;color:#64748b;margin-bottom:4px">
        <span>Performance</span>
        <span><?= $overallPct ?>%</span>
      </div>
      <div style="background:#e2e8f0;border-radius:99px;height:10px;overflow:hidden">
        <div style="height:100%;border-radius:99px;width:<?= min(100,$overallPct) ?>%;
                    background:<?= $overallPct>=80?'#16a34a':($overallPct>=60?'#2563eb':($overallPct>=40?'#f59e0b':'#dc2626')) ?>;
                    transition:width .6s ease"></div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Grade Scale Reference ── -->
<div class="sec-card mb-4">
  <div class="sec-head"><h5><i class="fas fa-star me-2" style="color:#1d4ed8"></i>Grade Scale</h5></div>
  <div style="padding:12px 16px;display:flex;flex-wrap:wrap;gap:8px">
    <?php
    $scale = [
        ['label'=>'A+','range'=>'90–100%','cls'=>'grade-aplus'],
        ['label'=>'A', 'range'=>'80–89%', 'cls'=>'grade-a'],
        ['label'=>'B+','range'=>'70–79%', 'cls'=>'grade-bplus'],
        ['label'=>'B', 'range'=>'60–69%', 'cls'=>'grade-b'],
        ['label'=>'C', 'range'=>'50–59%', 'cls'=>'grade-c'],
        ['label'=>'D', 'range'=>'40–49%', 'cls'=>'grade-d'],
        ['label'=>'F', 'range'=>'< 40%',  'cls'=>'grade-f'],
    ];
    foreach ($scale as $gs): ?>
    <span style="display:inline-flex;align-items:center;gap:6px;background:#f8fafc;
                 border:1px solid #e2e8f0;border-radius:6px;padding:4px 10px;font-size:.8rem">
      <span class="grade <?= $gs['cls'] ?>"><?= $gs['label'] ?></span>
      <span class="text-muted"><?= $gs['range'] ?></span>
    </span>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── Assessment Breakdown ── -->
<div class="sec-card">
  <div class="sec-head">
    <h5><i class="fas fa-table me-2" style="color:#1d4ed8"></i>Assessment Breakdown</h5>
    <?php if ($hasMarks): ?>
    <div class="d-flex align-items-center gap-2">
      <span class="fw-bold" style="font-size:13px;color:#1d4ed8"><?= $overallPct ?>%</span>
      <?= gradeHtml($overallPct) ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Type</th>
          <th>Date</th>
          <th>Max Marks</th>
          <th>Obtained</th>
          <th>Percentage</th>
          <th>Grade</th>
          <th>Weight</th>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($assessments as $a):
        $hasMark = $a['marks_obtained'] !== null;
        $pct     = ($hasMark && $a['max_marks'] > 0)
                     ? round($a['marks_obtained'] / $a['max_marks'] * 100, 1) : null;
        $tc = $typeColors[$a['type']] ?? 'bg-secondary';
      ?>
      <tr>
        <td class="fw-semibold"><?= h($a['title']) ?></td>
        <td><span class="badge <?= $tc ?>"><?= h($a['type']) ?></span></td>
        <td><?= $a['date'] ? fDate($a['date']) : '<span class="text-muted">—</span>' ?></td>
        <td><?= h($a['max_marks']) ?></td>
        <td class="fw-bold">
          <?= $hasMark ? h($a['marks_obtained']) : '<span class="text-muted">—</span>' ?>
        </td>
        <td>
          <?= $pct !== null
              ? '<span class="fw-bold" style="color:#1d4ed8">'.$pct.'%</span>'
              : '<span class="text-muted">—</span>' ?>
        </td>
        <td><?= $pct !== null ? gradeHtml($pct) : '<span class="text-muted">—</span>' ?></td>
        <td><?= $a['weight'] > 0 ? h($a['weight']).'%' : '<span class="text-muted">—</span>' ?></td>
        <td class="text-muted"><?= $a['remarks'] ? h($a['remarks']) : '—' ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <?php if ($hasMarks): ?>
      <tfoot>
        <tr>
          <td colspan="3" class="fw-bold">Total</td>
          <td class="fw-bold"><?= $totalMax ?></td>
          <td class="fw-bold"><?= $totalObt ?></td>
          <td class="fw-bold" style="color:#1d4ed8"><?= $overallPct ?>%</td>
          <td><?= gradeHtml($overallPct) ?></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

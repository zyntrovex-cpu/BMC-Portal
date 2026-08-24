<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

$user    = requireAuth('student');
$student = getStudentByUserId($user['id']);

if (!$student) {
    setFlash('danger', 'Student profile not found.');
    redirect('/logout.php');
}

$db = getDB();

// ── Fetch all assessments for this class with student marks ───────
$st = $db->prepare(
    'SELECT a.id AS assessment_id, a.title, a.type, a.max_marks, a.weight, a.date,
            sb.id AS subject_id, sb.name AS subject_name, sb.code AS subject_code,
            m.marks_obtained, m.remarks
     FROM assessments a
     JOIN subjects sb ON a.subject_id = sb.id
     LEFT JOIN marks m ON m.assessment_id = a.id AND m.student_id = ?
     WHERE a.class_id = ?
     ORDER BY sb.name, a.date'
);
$st->execute([$student['id'], $student['class_id']]);
$rows = $st->fetchAll();

// ── Group by subject ──────────────────────────────────────────────
$bySubject = [];
foreach ($rows as $r) {
    $bySubject[$r['subject_id']]['name'] = $r['subject_name'];
    $bySubject[$r['subject_id']]['code'] = $r['subject_code'];
    $bySubject[$r['subject_id']]['assessments'][] = $r;
}

// ── Page render ───────────────────────────────────────────────────
pageHead('Results', 'student');
$links = getStudentLinks();
?>
<div class="portal-wrap">
<?php sidebar('student', 'results', $links, $user); ?>
<div class="main-area">
<?php topbar('Results', $user); ?>
<div class="page-content">

<?= flashHtml() ?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0" style="color:#1d4ed8"><i class="fas fa-chart-bar me-2"></i>My Results</h4>
    <small class="text-muted"><?= h($student['name']) ?> &nbsp;&middot;&nbsp; Class: <?= h($student['class_name']) ?> &nbsp;&middot;&nbsp; Roll: <?= h($student['roll_no']) ?></small>
  </div>
</div>

<?php if (empty($bySubject)): ?>
<div class="sec-card">
  <div class="sec-body text-center text-muted py-4">
    <i class="fas fa-chart-bar fa-2x mb-2 d-block opacity-25"></i>
    No assessment data available yet.
  </div>
</div>
<?php else: foreach ($bySubject as $subjectId => $sub):
  $assessments  = $sub['assessments'];
  // Weighted overall
  $totalWeight  = 0;
  $weightedSum  = 0;
  $totalObtained = 0;
  $totalMax      = 0;
  foreach ($assessments as $a) {
      if ($a['marks_obtained'] !== null) {
          $totalObtained += $a['marks_obtained'];
          $totalMax      += $a['max_marks'];
          if ($a['weight'] > 0) {
              $pct           = $a['max_marks'] > 0 ? ($a['marks_obtained'] / $a['max_marks'] * 100) : 0;
              $weightedSum  += $pct * $a['weight'];
              $totalWeight  += $a['weight'];
          }
      }
  }
  $overallPct = $totalWeight > 0
      ? round($weightedSum / $totalWeight, 1)
      : ($totalMax > 0 ? round($totalObtained / $totalMax * 100, 1) : 0);
  $hasMarks = $totalMax > 0;
?>
<div class="sec-card">
  <div class="sec-head">
    <h5>
      <i class="fas fa-book-open me-2" style="color:#1d4ed8"></i>
      <?= h($sub['name']) ?>
      <span class="text-muted fw-normal" style="font-size:11px">(<?= h($sub['code']) ?>)</span>
    </h5>
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
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($assessments as $a):
          $hasMark = $a['marks_obtained'] !== null;
          $pct     = ($hasMark && $a['max_marks'] > 0) ? round($a['marks_obtained'] / $a['max_marks'] * 100, 1) : 0;
        ?>
        <tr>
          <td class="fw-semibold"><?= h($a['title']) ?></td>
          <td>
            <?php
              $typeColors = [
                'Quiz'       => 'bg-info text-dark',
                'Assignment' => 'bg-secondary',
                'Mid Term'   => 'bg-warning text-dark',
                'Final Term' => 'bg-danger',
                'Practical'  => 'bg-success',
              ];
              $tc = $typeColors[$a['type']] ?? 'bg-secondary';
            ?>
            <span class="badge <?= $tc ?>"><?= h($a['type']) ?></span>
          </td>
          <td><?= fDate($a['date']) ?></td>
          <td><?= h($a['max_marks']) ?></td>
          <td class="fw-bold">
            <?= $hasMark ? h($a['marks_obtained']) : '<span class="text-muted">—</span>' ?>
          </td>
          <td>
            <?php if ($hasMark): ?>
              <span class="fw-bold" style="color:#1d4ed8"><?= $pct ?>%</span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td><?= $hasMark ? gradeHtml($pct) : '<span class="text-muted">—</span>' ?></td>
          <td class="text-muted"><?= $a['remarks'] ? h($a['remarks']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if ($hasMarks): ?>
      <tfoot>
        <tr>
          <td colspan="4" class="fw-bold">Subject Total</td>
          <td class="fw-bold"><?= $totalObtained ?> / <?= $totalMax ?></td>
          <td class="fw-bold" style="color:#1d4ed8"><?= $overallPct ?>%</td>
          <td><?= gradeHtml($overallPct) ?></td>
          <td></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>
<?php endforeach; endif; ?>

</div><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /portal-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

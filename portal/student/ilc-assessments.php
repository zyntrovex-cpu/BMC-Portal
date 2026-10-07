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
        'SELECT a.* FROM ilc_student_assessments a WHERE a.student_id = ? ORDER BY a.date DESC, a.id DESC'
    );
    $st->execute([$student['id']]);
    $assessments = $st->fetchAll();
}

$typeColors = [
    'Quiz'       => 'bg-info text-dark',
    'Assignment' => 'bg-secondary',
    'Midterm'    => 'bg-warning text-dark',
    'Mid Term'   => 'bg-warning text-dark',
    'Final Term' => 'bg-danger',
    'Test'       => 'bg-primary',
    'Practical'  => 'bg-success',
];

pageHead('My Assessments', 'student');
$links = getStudentLinks();
?>
<div class="portal-wrap">
<?php sidebar('student', 'ilc-assessments', $links, $user); ?>
<div class="main-area">
<?php topbar('My Assessments', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0" style="color:#1d4ed8"><i class="fas fa-clipboard-list me-2"></i>My Assessments</h4>
    <small class="text-muted">
      <?= h($student['name']) ?> &nbsp;·&nbsp; <?= h($student['class_name'] ?? 'ILC') ?>
      <?php if (!empty($student['roll_no'])): ?>&nbsp;·&nbsp; Roll: <?= h($student['roll_no']) ?><?php endif; ?>
    </small>
  </div>
  <a href="<?= url('/portal/student/ilc-results.php') ?>" class="btn btn-primary btn-sm">
    <i class="fas fa-chart-bar me-1"></i>View Results
  </a>
</div>

<?php if (empty($assessments)): ?>
<div class="sec-card">
  <div class="sec-body text-center text-muted py-4">
    <i class="fas fa-clipboard-list fa-2x mb-2 d-block opacity-25"></i>
    No assessment data available yet. Check back after your teacher has added assessments.
  </div>
</div>
<?php else: ?>
<div class="sec-card">
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
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
      <?php foreach ($assessments as $i => $a):
        $hasMark = $a['marks_obtained'] !== null;
        $pct     = ($hasMark && $a['max_marks'] > 0)
                     ? round($a['marks_obtained'] / $a['max_marks'] * 100, 1) : null;
        $tc = $typeColors[$a['type']] ?? 'bg-secondary';
      ?>
      <tr>
        <td class="text-muted"><?= $i+1 ?></td>
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
    </table>
  </div>
</div>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

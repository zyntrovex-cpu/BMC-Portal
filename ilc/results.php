<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('ilc_vp');
requirePermission('ilc_results');
$db   = getDB();

$classId = (int)($_GET['class_id'] ?? 0);
$classes = $db->query('SELECT * FROM classes WHERE is_ilc=1 ORDER BY name')->fetchAll();

$results = [];
if ($classId) {
    $st = $db->prepare(
        'SELECT u.name, s.roll_no,
                SUM(m.marks_obtained) AS total_obtained,
                SUM(a.max_marks)      AS total_possible,
                COUNT(DISTINCT m.assessment_id) AS assessments
         FROM students s
         JOIN users u ON u.id = s.user_id
         LEFT JOIN marks m ON m.student_id = s.id
         LEFT JOIN assessments a ON a.id = m.assessment_id
         WHERE s.class_id = ?
         GROUP BY s.id, u.id ORDER BY u.name'
    );
    $st->execute([$classId]);
    $results = $st->fetchAll();
}

pageHead('ILC Results', 'ilc_vp');
$links = getIlcLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'results', $links, $user); ?>
<div class="main-area">
<?php topbar('ILC Results', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card mb-3">
  <div class="sec-card-header" style="background:linear-gradient(90deg,#0c4a6e,#0891b2);color:#fff">
    <i class="fas fa-filter me-2"></i>Filter by Class
  </div>
  <div style="padding:14px 16px">
    <form method="GET" class="d-flex gap-2 align-items-end">
      <div>
        <label class="form-label fw-semibold" style="font-size:.82rem">ILC Class</label>
        <select name="class_id" class="form-select form-select-sm" style="width:200px">
          <option value="0">Select class…</option>
          <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $classId==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-sm" style="background:#0891b2;color:#fff">View</button>
    </form>
  </div>
</div>

<?php if (empty($classes)): ?>
<div class="alert alert-info" style="font-size:.86rem">
  <i class="fas fa-info-circle me-2"></i>No ILC classes found. Classes must be marked with <code>is_ilc=1</code> in the database.
</div>
<?php elseif ($classId && !empty($results)): ?>
<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center"
       style="background:linear-gradient(90deg,#0c4a6e,#0891b2);color:#fff">
    <span><i class="fas fa-chart-bar me-2"></i>Results Summary</span>
    <small style="opacity:.8;font-size:.77rem"><?= count($results) ?> student(s)</small>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr><th>Roll No</th><th>Student</th><th>Assessments</th><th>Total Marks</th><th>Obtained</th><th>%</th></tr>
      </thead>
      <tbody>
        <?php foreach ($results as $r): ?>
        <?php $pct = $r['total_possible'] > 0 ? round($r['total_obtained'] / $r['total_possible'] * 100, 1) : 0; ?>
        <tr>
          <td><?= h($r['roll_no']) ?></td>
          <td><?= h($r['name']) ?></td>
          <td><?= (int)$r['assessments'] ?></td>
          <td><?= number_format((float)$r['total_possible'], 0) ?></td>
          <td><?= number_format((float)$r['total_obtained'], 1) ?></td>
          <td>
            <span class="badge <?= $pct>=80?'bg-success':($pct>=50?'bg-warning text-dark':'bg-danger') ?>"><?= $pct ?>%</span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php elseif ($classId): ?>
<div class="alert alert-info" style="font-size:.86rem">No results recorded for this class yet.</div>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

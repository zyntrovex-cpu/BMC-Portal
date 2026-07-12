<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('vp_main');
$db   = getDB();

$classId  = (int)($_GET['class_id'] ?? 0);
$classes  = $db->query('SELECT * FROM classes WHERE is_ilc=0 ORDER BY is_montessori, name')->fetchAll();

$results = [];
if ($classId) {
    $results = $db->prepare(
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
    $results->execute([$classId]);
    $results = $results->fetchAll();
}

pageHead('Results', 'vp_main');
$links = getVpLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('vp_main', 'results', $links, $user); ?>
<div class="main-area">
<?php topbar('Results', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-filter me-2"></i>Filter</div>
  <div style="padding:14px 16px">
    <form method="GET" class="d-flex gap-2 align-items-end">
      <div>
        <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
        <select name="class_id" class="form-select form-select-sm" style="width:160px">
          <option value="0">Select class…</option>
          <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $classId==$c['id']?'selected':'' ?>><?= h($c['name']) ?><?= $c['is_montessori']?' (Montessori)':'' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-sm btn-primary">View</button>
    </form>
  </div>
</div>

<?php if ($classId && !empty($results)): ?>
<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-chart-bar me-2"></i>Results Summary</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light"><tr><th>Roll No</th><th>Student</th><th>Assessments</th><th>Total Marks</th><th>Obtained</th><th>%</th></tr></thead>
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

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('vp_main');
requirePermission('vp_results');
$db   = getDB();

$classId  = (int)($_GET['class_id'] ?? 0);
$rcSearch = trim($_GET['rc_q'] ?? '');
$classes  = $db->query('SELECT * FROM classes WHERE is_ilc=0 ORDER BY is_montessori, name')->fetchAll();

$results = [];
if ($classId) {
    $results = $db->prepare(
        'SELECT s.id AS student_id, u.name, s.roll_no,
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

// ── Report-card student search ────────────────────────────────
$rcStudents = [];
if ($rcSearch !== '') {
    $like  = '%' . $rcSearch . '%';
    $rcSt  = $db->prepare(
        "SELECT s.id AS student_id, u.name, u.user_id AS gr_no, s.roll_no,
                c.name AS class_name
         FROM students s
         JOIN users u ON s.user_id = u.id
         LEFT JOIN classes c ON c.id = s.class_id
         WHERE u.name LIKE ? OR u.user_id LIKE ? OR s.roll_no LIKE ?
         ORDER BY c.name, u.name
         LIMIT 40"
    );
    $rcSt->execute([$like, $like, $like]);
    $rcStudents = $rcSt->fetchAll();
}

pageHead('Results', 'vp_main');
$links = getVpLinks();
?>
<div class="portal-wrap">
<?php sidebar('vp_main', 'results', $links, $user); ?>
<div class="main-area">
<?php topbar('Results', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- ── Report Card Search ───────────────────────────────────── -->
<div class="sec-card mb-3" style="border-left:4px solid #1d4ed8">
  <div class="sec-card-header d-flex align-items-center gap-2">
    <i class="fas fa-file-alt text-primary"></i>
    <span class="fw-semibold">Student Report Cards — View &amp; Download</span>
  </div>
  <div style="padding:14px 16px">
    <form method="GET" class="row g-2 align-items-end mb-3">
      <?php if ($classId): ?><input type="hidden" name="class_id" value="<?= $classId ?>"><?php endif; ?>
      <div class="col-md-5">
        <label class="form-label fw-semibold" style="font-size:.82rem">
          Search Student (Name / GR No / Roll No)
        </label>
        <input type="text" name="rc_q" value="<?= h($rcSearch) ?>"
               class="form-control form-control-sm"
               placeholder="e.g. Ahmed, GR-001, 15…">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="fas fa-search me-1"></i>Search
        </button>
        <?php if ($rcSearch): ?>
        <a href="?class_id=<?= $classId ?>" class="btn btn-sm btn-outline-secondary ms-1">Clear</a>
        <?php endif; ?>
      </div>
    </form>
    <?php if ($rcSearch !== '' && empty($rcStudents)): ?>
    <div style="font-size:.83rem;color:#64748b;padding:8px 0">
      <i class="fas fa-info-circle me-1"></i>No students found for "<strong><?= h($rcSearch) ?></strong>".
    </div>
    <?php elseif (!empty($rcStudents)): ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0" style="font-size:.82rem">
        <thead class="table-light">
          <tr><th>Name</th><th>GR No</th><th>Roll No</th><th>Class</th><th>Report Card</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rcStudents as $rs): ?>
          <tr>
            <td class="fw-semibold"><?= h($rs['name']) ?></td>
            <td><?= h($rs['gr_no'] ?: '—') ?></td>
            <td><?= h($rs['roll_no'] ?: '—') ?></td>
            <td><?= $rs['class_name'] ? '<span class="badge bg-secondary">'.h($rs['class_name']).'</span>' : '<span class="text-muted">—</span>' ?></td>
            <td>
              <a href="<?= url('/portal/report-card.php?student_id=' . $rs['student_id']) ?>"
                 class="btn btn-xs btn-primary" style="font-size:.76rem;padding:3px 12px" target="_blank">
                <i class="fas fa-file-alt me-1"></i>View / Download
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div style="font-size:.8rem;color:#94a3b8">
      <i class="fas fa-search me-1"></i>Enter a name, GR number, or roll number to find a student's report card.
    </div>
    <?php endif; ?>
  </div>
</div>

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
      <thead class="table-light"><tr><th>Roll No</th><th>Student</th><th>Assessments</th><th>Total Marks</th><th>Obtained</th><th>%</th><th>Report Card</th></tr></thead>
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
          <td>
            <a href="<?= url('/portal/report-card.php?student_id=' . $r['student_id']) ?>"
               class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 9px" target="_blank">
              <i class="fas fa-file-alt me-1"></i>Card
            </a>
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

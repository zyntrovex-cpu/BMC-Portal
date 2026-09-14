<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('vp_main');
requirePermission('vp_attendance');
$db   = getDB();

$classId     = (int)($_GET['class_id'] ?? 0);
$dateFilter  = $_GET['date'] ?? date('Y-m-d');
$classes     = $db->query('SELECT * FROM classes WHERE is_ilc=0 ORDER BY is_montessori, name')->fetchAll();

$attendance = [];
if ($classId) {
    $st = $db->prepare(
        'SELECT u.name, s.roll_no, a.status, a.date, sub.name AS subject_name
         FROM attendance a
         JOIN students s ON s.id = a.student_id
         JOIN users u    ON u.id = s.user_id
         LEFT JOIN subjects sub ON sub.id = a.subject_id
         WHERE s.class_id = ? AND a.date = ?
         ORDER BY s.roll_no'
    );
    $st->execute([$classId, $dateFilter]);
    $attendance = $st->fetchAll();
}

// Summary counts
$summary = ['P' => 0, 'A' => 0, 'L' => 0];
foreach ($attendance as $a) if (isset($summary[$a['status']])) $summary[$a['status']]++;

pageHead('Attendance', 'vp_main');
$links = getVpLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('vp_main', 'attendance', $links, $user); ?>
<div class="main-area">
<?php topbar('Attendance', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-filter me-2"></i>Filter</div>
  <div style="padding:14px 16px">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
        <select name="class_id" class="form-select form-select-sm">
          <option value="0">Select class…</option>
          <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $classId==$c['id']?'selected':'' ?>><?= h($c['name']) ?><?= $c['is_montessori']?' (Montessori)':'' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold" style="font-size:.82rem">Date</label>
        <input type="date" name="date" class="form-control form-control-sm" value="<?= h($dateFilter) ?>">
      </div>
      <div class="col-auto">
        <button class="btn btn-sm btn-primary">View</button>
      </div>
    </form>
  </div>
</div>

<?php if ($classId): ?>
<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between">
    <span><i class="fas fa-calendar-check me-2"></i>Attendance — <?= date('d M Y', strtotime($dateFilter)) ?></span>
    <div class="d-flex gap-2">
      <span class="badge bg-success"><?= $summary['P'] ?> Present</span>
      <span class="badge bg-danger"><?= $summary['A'] ?> Absent</span>
      <span class="badge bg-warning text-dark"><?= $summary['L'] ?> Leave</span>
    </div>
  </div>
  <?php if (empty($attendance)): ?>
  <div style="padding:32px;text-align:center;color:var(--t2);font-size:.85rem">No attendance records for this date.</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light"><tr><th>Roll No</th><th>Student</th><th>Subject</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($attendance as $a): ?>
        <?php $cls = match($a['status']) {'P'=>'success','A'=>'danger','L'=>'warning',default=>'secondary'}; ?>
        <tr>
          <td><?= h($a['roll_no']) ?></td>
          <td><?= h($a['name']) ?></td>
          <td><?= h($a['subject_name'] ?: '—') ?></td>
          <td><span class="badge bg-<?= $cls ?>"><?= $a['status']==='P'?'Present':($a['status']==='A'?'Absent':'Leave') ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

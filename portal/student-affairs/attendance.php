<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('student_affairs');
$db   = getDB();

$classId    = (int)($_GET['class_id'] ?? 0);
$dateFilter = $_GET['date'] ?? date('Y-m-d');
$viewMode   = $_GET['view'] ?? 'daily'; // 'daily' or 'summary'
$classes    = $db->query('SELECT * FROM classes ORDER BY name')->fetchAll();

$attendance = [];
$summary    = ['P' => 0, 'A' => 0, 'L' => 0];

if ($classId) {
    if ($viewMode === 'summary') {
        // Per-student attendance summary
        $st = $db->prepare(
            'SELECT u.name, s.roll_no,
                    COUNT(a.id) AS total,
                    SUM(a.status="P") AS present,
                    SUM(a.status="A") AS absent,
                    SUM(a.status="L") AS `leave`
             FROM students s
             JOIN users u ON u.id = s.user_id
             LEFT JOIN attendance a ON a.student_id = s.id
             WHERE s.class_id = ?
             GROUP BY s.id, u.name, s.roll_no
             ORDER BY s.roll_no'
        );
        $st->execute([$classId]);
        $attendance = $st->fetchAll();
    } else {
        $st = $db->prepare(
            'SELECT u.name, s.roll_no, a.status, a.date, sub.name AS subject_name
             FROM attendance a
             JOIN students s   ON s.id = a.student_id
             JOIN users u      ON u.id = s.user_id
             LEFT JOIN subjects sub ON sub.id = a.subject_id
             WHERE s.class_id = ? AND a.date = ?
             ORDER BY s.roll_no'
        );
        $st->execute([$classId, $dateFilter]);
        $attendance = $st->fetchAll();
        foreach ($attendance as $a) {
            if (isset($summary[$a['status']])) $summary[$a['status']]++;
        }
    }
}

pageHead('Attendance — Student Affairs', 'student_affairs');
$links = getStudentAffairsLinks();
?>
<div class="portal-wrap">
<?php sidebar('student_affairs', 'attendance', $links, $user); ?>
<div class="main-area">
<?php topbar('Attendance — Read Only', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="alert alert-info py-2 mb-3" style="font-size:.83rem">
  <i class="fas fa-eye me-2"></i><strong>View only.</strong>
  Attendance is marked by teachers and displayed here for Student Affairs reference.
</div>

<!-- Filter -->
<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-filter me-2"></i>Filter</div>
  <div style="padding:14px 16px">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
        <select name="class_id" class="form-select form-select-sm">
          <option value="0">— Select class —</option>
          <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $classId==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold" style="font-size:.82rem">View Mode</label>
        <select name="view" class="form-select form-select-sm">
          <option value="daily"   <?= $viewMode==='daily'  ?'selected':'' ?>>Daily (by date)</option>
          <option value="summary" <?= $viewMode==='summary'?'selected':'' ?>>Summary (all time)</option>
        </select>
      </div>
      <?php if ($viewMode === 'daily'): ?>
      <div class="col-md-3">
        <label class="form-label fw-semibold" style="font-size:.82rem">Date</label>
        <input type="date" name="date" class="form-control form-control-sm" value="<?= h($dateFilter) ?>">
      </div>
      <?php endif; ?>
      <div class="col-auto">
        <button class="btn btn-sm btn-primary">View</button>
      </div>
    </form>
  </div>
</div>

<?php if ($classId): ?>
<?php if ($viewMode === 'daily'): ?>

<!-- Daily view -->
<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-calendar-check me-2"></i>Attendance — <?= date('d M Y', strtotime($dateFilter)) ?></span>
    <div class="d-flex gap-2">
      <span class="badge bg-success"><?= $summary['P'] ?> Present</span>
      <span class="badge bg-danger"><?= $summary['A'] ?> Absent</span>
      <span class="badge bg-warning text-dark"><?= $summary['L'] ?> Leave</span>
    </div>
  </div>
  <?php if (empty($attendance)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2);font-size:.85rem">
    No attendance records for this date.
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.83rem">
      <thead class="table-light">
        <tr><th>Roll</th><th>Name</th><th>Subject</th><th class="text-center">Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($attendance as $a):
          $sc = match($a['status']) {'P'=>'success','A'=>'danger','L'=>'warning',default=>'secondary'};
          $sl = match($a['status']) {'P'=>'Present','A'=>'Absent','L'=>'Leave',default=>$a['status']};
        ?>
        <tr>
          <td class="fw-semibold"><?= h($a['roll_no']) ?></td>
          <td><?= h($a['name']) ?></td>
          <td><?= h($a['subject_name'] ?: '—') ?></td>
          <td class="text-center">
            <span class="badge bg-<?= $sc ?>"><?= $sl ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php else: ?>

<!-- Summary view -->
<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-chart-pie me-2"></i>Attendance Summary</div>
  <?php if (empty($attendance)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2);font-size:.85rem">
    No attendance data for this class.
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.83rem">
      <thead class="table-light">
        <tr><th>Roll</th><th>Name</th><th class="text-center">Total</th><th class="text-center">Present</th><th class="text-center">Absent</th><th class="text-center">Leave</th><th class="text-center">%</th></tr>
      </thead>
      <tbody>
        <?php foreach ($attendance as $a):
          $pct = $a['total'] > 0 ? round($a['present'] / $a['total'] * 100, 1) : 0;
          $pc  = $pct >= 75 ? 'success' : ($pct >= 50 ? 'warning' : 'danger');
        ?>
        <tr>
          <td class="fw-semibold"><?= h($a['roll_no']) ?></td>
          <td><?= h($a['name']) ?></td>
          <td class="text-center"><?= $a['total'] ?></td>
          <td class="text-center"><span class="badge bg-success"><?= $a['present'] ?></span></td>
          <td class="text-center"><span class="badge bg-danger"><?= $a['absent'] ?></span></td>
          <td class="text-center"><span class="badge bg-warning text-dark"><?= $a['leave'] ?></span></td>
          <td class="text-center">
            <span class="badge bg-<?= $pc ?>"><?= $pct ?>%</span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php endif; ?>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

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

// ── Subject-wise summary ──────────────────────────────────────────
$summary = getStudentAttendanceSummary($student['id']);

// ── Date-wise log (optionally filtered by subject) ────────────────
$filterSubjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

$subjects = getClassSubjects($student['class_id']);

if ($filterSubjectId > 0) {
    $stLog = $db->prepare(
        'SELECT a.date, a.status, sb.name AS subject_name
         FROM attendance a
         JOIN subjects sb ON a.subject_id = sb.id
         WHERE a.student_id = ? AND a.subject_id = ?
         ORDER BY a.date DESC'
    );
    $stLog->execute([$student['id'], $filterSubjectId]);
} else {
    $stLog = $db->prepare(
        'SELECT a.date, a.status, sb.name AS subject_name
         FROM attendance a
         JOIN subjects sb ON a.subject_id = sb.id
         WHERE a.student_id = ?
         ORDER BY a.date DESC'
    );
    $stLog->execute([$student['id']]);
}
$attLog = $stLog->fetchAll();

// ── Monthly chart data ────────────────────────────────────────────
$stMonthly = $db->prepare(
    'SELECT DATE_FORMAT(a.date, "%Y-%m") AS ym,
            COUNT(*) AS total,
            SUM(a.status = "P") AS present
     FROM attendance a
     WHERE a.student_id = ?
     GROUP BY ym
     ORDER BY ym'
);
$stMonthly->execute([$student['id']]);
$monthly = $stMonthly->fetchAll();

$chartLabels = [];
$chartData   = [];
foreach ($monthly as $mo) {
    $chartLabels[] = date('M Y', strtotime($mo['ym'] . '-01'));
    $chartData[]   = $mo['total'] > 0 ? round($mo['present'] / $mo['total'] * 100, 1) : 0;
}

// ── Page render ───────────────────────────────────────────────────
pageHead('Attendance', 'student');
$links = [
    ['href' => '/student/dashboard.php',  'icon' => '<i class="fas fa-home"></i>',          'label' => 'Dashboard',  'key' => 'dashboard'],
    ['href' => '/student/results.php',    'icon' => '<i class="fas fa-chart-bar"></i>',      'label' => 'Results',    'key' => 'results'],
    ['href' => '/student/attendance.php', 'icon' => '<i class="fas fa-calendar-check"></i>', 'label' => 'Attendance', 'key' => 'attendance'],
    ['href' => '/student/timetable.php',  'icon' => '<i class="fas fa-table"></i>',          'label' => 'Timetable',  'key' => 'timetable'],
    ['href' => '/student/notices.php',    'icon' => '<i class="fas fa-bell"></i>',           'label' => 'Notices',    'key' => 'notices'],
    ['href' => '/student/profile.php',    'icon' => '<i class="fas fa-user"></i>',           'label' => 'Profile',    'key' => 'profile'],
];
?>
<div class="overlay" id="overlay" onclick="this.classList.remove('show');document.getElementById('sidebar').classList.remove('open')"></div>
<?php sidebar('student', 'attendance', $links); ?>
<div class="main">
<?php topbar('Attendance', $user); ?>
<div class="content">

<?= flashHtml() ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0" style="color:#1d4ed8"><i class="fas fa-calendar-check me-2"></i>Attendance</h4>
    <small class="text-muted"><?= h($student['name']) ?> &nbsp;&middot;&nbsp; Class: <?= h($student['class_name']) ?></small>
  </div>
</div>

<!-- Subject-wise Summary -->
<div class="sec-card mb-4">
  <div class="sec-head">
    <h5><i class="fas fa-list me-2" style="color:#1d4ed8"></i>Subject-wise Summary</h5>
  </div>
  <?php
    $anyLow = false;
    foreach ($summary as $s) {
        $pct = $s['total'] > 0 ? round($s['present'] / $s['total'] * 100, 1) : 0;
        if ($pct < 75) { $anyLow = true; break; }
    }
    if ($anyLow):
  ?>
  <div class="mx-3 mt-3">
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-0" style="font-size:13px">
      <i class="fas fa-exclamation-triangle"></i>
      <span>Warning: One or more subjects have attendance below 75%. Please improve your attendance.</span>
    </div>
  </div>
  <?php endif; ?>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Subject</th>
          <th>Total Classes</th>
          <th>Present</th>
          <th>Absent</th>
          <th>Leave</th>
          <th>Attendance %</th>
          <th>Progress</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($summary)): ?>
        <tr><td colspan="7" class="text-center text-muted py-3">No attendance records found.</td></tr>
        <?php else: foreach ($summary as $s):
          $pct   = $s['total'] > 0 ? round($s['present'] / $s['total'] * 100, 1) : 0;
          $color = $pct >= 75 ? '#059669' : ($pct >= 60 ? '#d97706' : '#ef4444');
        ?>
        <tr>
          <td class="fw-semibold"><?= h($s['subject']) ?> <small class="text-muted">(<?= h($s['code']) ?>)</small></td>
          <td><?= h($s['total']) ?></td>
          <td><span style="color:#059669;font-weight:600"><?= h($s['present']) ?></span></td>
          <td><span style="color:#ef4444;font-weight:600"><?= h($s['absent']) ?></span></td>
          <td><span style="color:#d97706;font-weight:600"><?= h($s['leave']) ?></span></td>
          <td>
            <span class="fw-bold" style="color:<?= $color ?>"><?= $pct ?>%</span>
            <?php if ($pct < 75): ?>
              <span class="badge bg-danger ms-1" style="font-size:9px">Low</span>
            <?php endif; ?>
          </td>
          <td style="min-width:100px">
            <div class="att-bar-wrap">
              <div class="att-bar-fill" style="width:<?= min($pct, 100) ?>%;background:<?= $color ?>"></div>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Monthly Chart -->
<?php if (!empty($monthly)): ?>
<div class="sec-card mb-4">
  <div class="sec-head">
    <h5><i class="fas fa-chart-line me-2" style="color:#1d4ed8"></i>Monthly Attendance Trend</h5>
  </div>
  <div class="p-3" style="height:260px">
    <canvas id="attChart"></canvas>
  </div>
</div>
<?php endif; ?>

<!-- Date-wise Log -->
<div class="sec-card">
  <div class="sec-head">
    <h5><i class="fas fa-calendar me-2" style="color:#1d4ed8"></i>Attendance Log</h5>
    <form method="GET" class="d-flex align-items-center gap-2 mb-0">
      <select name="subject_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <option value="0">All Subjects</option>
        <?php foreach ($subjects as $sub): ?>
        <option value="<?= h($sub['id']) ?>" <?= $filterSubjectId === (int)$sub['id'] ? 'selected' : '' ?>>
          <?= h($sub['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Subject</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($attLog)): ?>
        <tr><td colspan="3" class="text-center text-muted py-3">No attendance records found.</td></tr>
        <?php else: foreach ($attLog as $a):
          $statusColor = $a['status'] === 'P' ? '#059669' : ($a['status'] === 'L' ? '#d97706' : '#ef4444');
          $statusLabel = $a['status'] === 'P' ? 'Present' : ($a['status'] === 'L' ? 'Leave' : 'Absent');
          $statusBg    = $a['status'] === 'P' ? '#f0fdf4' : ($a['status'] === 'L' ? '#fffbeb' : '#fef2f2');
        ?>
        <tr style="background:<?= $statusBg ?>">
          <td><?= fDate($a['date']) ?></td>
          <td class="fw-semibold"><?= h($a['subject_name']) ?></td>
          <td>
            <span class="badge" style="background:<?= $statusColor ?>; min-width:60px; text-align:center;">
              <?= $statusLabel ?>
            </span>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div><!-- /content -->
</div><!-- /main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($monthly)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('attChart').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [{
      label: 'Attendance %',
      data: <?= json_encode($chartData) ?>,
      backgroundColor: <?= json_encode(array_map(fn($v) => $v < 75 ? 'rgba(239,68,68,.7)' : 'rgba(29,78,216,.7)', $chartData)) ?>,
      borderColor: <?= json_encode(array_map(fn($v) => $v < 75 ? '#ef4444' : '#1d4ed8', $chartData)) ?>,
      borderWidth: 1,
      borderRadius: 4,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: {
        min: 0, max: 100,
        ticks: { callback: v => v + '%', font: { size: 11 } },
        grid: { color: '#edf1f7' }
      },
      x: { ticks: { font: { size: 11 } }, grid: { display: false } }
    }
  }
});
</script>
<?php endif; ?>
</body>
</html>

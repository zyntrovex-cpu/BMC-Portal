<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user  = requireAuth('finance');
$db    = getDB();
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));

$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// Class-wise summary
$classReportSt = $db->prepare(
    'SELECT c.name AS class_name, c.id AS class_id,
            COUNT(DISTINCT s.id) AS total_students,
            SUM(CASE WHEN f.paid=1 THEN 1 ELSE 0 END) AS paid,
            SUM(CASE WHEN f.paid=0 OR f.paid IS NULL THEN 1 ELSE 0 END) AS unpaid,
            SUM(CASE WHEN f.paid=1 THEN f.amount ELSE 0 END) AS collected
     FROM classes c
     JOIN students s ON s.class_id = c.id
     LEFT JOIN fees f ON f.student_id = s.id AND f.month = ? AND f.year = ?
     GROUP BY c.id, c.name
     ORDER BY c.grade, c.section'
);
$classReportSt->execute([$month, $year]);
$classReport = $classReportSt->fetchAll();

// Payment mode breakdown
$modeSt = $db->prepare(
    'SELECT payment_mode, COUNT(*) AS cnt, SUM(amount) AS total FROM fees WHERE month=? AND year=? AND paid=1 GROUP BY payment_mode'
);
$modeSt->execute([$month, $year]);
$modeReport = $modeSt->fetchAll();

// Date-wise collection
$dateSt = $db->prepare(
    'SELECT payment_date, COUNT(*) AS cnt, SUM(amount) AS total FROM fees WHERE month=? AND year=? AND paid=1 GROUP BY payment_date ORDER BY payment_date'
);
$dateSt->execute([$month, $year]);
$dateReport = $dateSt->fetchAll();

$totalCollected = array_sum(array_column($classReport, 'collected'));
$totalPaid      = array_sum(array_column($classReport, 'paid'));
$totalStudents  = array_sum(array_column($classReport, 'total_students'));

pageHead('Monthly Report', 'finance');
$links = [
    ['href'=>'/finance/dashboard.php','icon'=>'<i class="fas fa-home"></i>','label'=>'Dashboard','key'=>'dashboard'],
    ['href'=>'/finance/collection.php','icon'=>'<i class="fas fa-hand-holding-usd"></i>','label'=>'Fee Collection','key'=>'collection'],
    ['href'=>'/finance/monthly.php','icon'=>'<i class="fas fa-calendar-alt"></i>','label'=>'Monthly Report','key'=>'monthly'],
    ['href'=>'/finance/records.php','icon'=>'<i class="fas fa-file-invoice-dollar"></i>','label'=>'Fee Records','key'=>'records'],
    ['href'=>'/finance/defaulters.php','icon'=>'<i class="fas fa-exclamation-triangle"></i>','label'=>'Defaulters','key'=>'defaulters'],
    ['href'=>'/finance/reports.php','icon'=>'<i class="fas fa-chart-pie"></i>','label'=>'Reports','key'=>'reports'],
];
?>
<div class="portal-wrap">
<?php sidebar('finance', 'monthly', $links); ?>
<div class="main-area">
<?php topbar('Monthly Fee Report', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="d-flex gap-2 mb-3 align-items-end flex-wrap">
  <form method="GET" class="d-flex gap-2 align-items-end">
    <div>
      <label class="form-label fw-semibold" style="font-size:.82rem">Month</label>
      <select name="month" class="form-select form-select-sm">
        <?php foreach ($months as $mi => $mn): ?><option value="<?= $mi+1 ?>" <?= $month===$mi+1?'selected':'' ?>><?= $mn ?></option><?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label fw-semibold" style="font-size:.82rem">Year</label>
      <select name="year" class="form-select form-select-sm">
        <?php for ($y=2023;$y<=2027;$y++): ?><option value="<?= $y ?>" <?= $year===$y?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-sm btn-primary">View</button>
  </form>
  <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i>Print</button>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-val"><?= $totalStudents ?></div><div class="stat-label">Total Students</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card" style="border-color:#059669"><div class="stat-icon" style="color:#059669"><i class="fas fa-check-circle"></i></div><div class="stat-val" style="color:#059669"><?= $totalPaid ?></div><div class="stat-label">Paid</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card" style="border-color:#dc2626"><div class="stat-icon" style="color:#dc2626"><i class="fas fa-times-circle"></i></div><div class="stat-val" style="color:#dc2626"><?= $totalStudents - $totalPaid ?></div><div class="stat-label">Unpaid</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card" style="border-color:#d97706"><div class="stat-icon" style="color:#d97706"><i class="fas fa-rupee-sign"></i></div><div class="stat-val" style="color:#d97706">PKR <?= number_format($totalCollected) ?></div><div class="stat-label">Collected</div></div></div>
</div>

<!-- Class-wise table -->
<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-chalkboard me-2"></i>Class-wise Summary — <?= $months[$month-1] ?> <?= $year ?></div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.85rem">
      <thead class="table-light"><tr><th>Class</th><th>Total</th><th class="text-success">Paid</th><th class="text-danger">Unpaid</th><th>Collected (PKR)</th><th>Collection%</th></tr></thead>
      <tbody>
        <?php foreach ($classReport as $cr):
          $pct = $cr['total_students'] > 0 ? round($cr['paid']/$cr['total_students']*100) : 0;
        ?>
        <tr>
          <td class="fw-semibold"><?= h($cr['class_name']) ?></td>
          <td><?= $cr['total_students'] ?></td>
          <td class="text-success fw-semibold"><?= $cr['paid'] ?></td>
          <td class="text-danger"><?= $cr['unpaid'] ?></td>
          <td>PKR <?= number_format($cr['collected']) ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="progress flex-grow-1" style="height:6px">
                <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
              </div>
              <span style="font-size:.8rem"><?= $pct ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <tr class="table-light fw-bold">
          <td>TOTAL</td><td><?= $totalStudents ?></td><td class="text-success"><?= $totalPaid ?></td>
          <td class="text-danger"><?= $totalStudents-$totalPaid ?></td>
          <td>PKR <?= number_format($totalCollected) ?></td><td></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<div class="row g-3">
  <!-- Payment mode -->
  <div class="col-md-5">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-credit-card me-2"></i>Payment Mode Breakdown</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0" style="font-size:.85rem">
          <thead class="table-light"><tr><th>Mode</th><th>Count</th><th>Amount (PKR)</th></tr></thead>
          <tbody>
            <?php foreach ($modeReport as $mr): ?>
            <tr><td><?= h($mr['payment_mode']) ?></td><td><?= $mr['cnt'] ?></td><td><?= number_format($mr['total']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($modeReport)): ?><tr><td colspan="3" class="text-muted text-center">No data</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Date-wise -->
  <div class="col-md-7">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-calendar-day me-2"></i>Date-wise Collection</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0" style="font-size:.85rem">
          <thead class="table-light"><tr><th>Date</th><th>Payments</th><th>Amount (PKR)</th></tr></thead>
          <tbody>
            <?php foreach ($dateReport as $dr): ?>
            <tr><td><?= fDate($dr['payment_date']) ?></td><td><?= $dr['cnt'] ?></td><td><?= number_format($dr['total']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($dateReport)): ?><tr><td colspan="3" class="text-muted text-center">No payments recorded</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

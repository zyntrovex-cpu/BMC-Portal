<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('finance');
requirePermission('fee_reports');
$db   = getDB();
$year = (int)($_GET['year'] ?? date('Y'));
$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// Annual monthly summary
$annualSt = $db->prepare(
    'SELECT month, COUNT(*) AS total, SUM(paid=1) AS paid, SUM(paid=0) AS unpaid, SUM(CASE WHEN paid=1 THEN amount ELSE 0 END) AS collected
     FROM fees WHERE year = ? GROUP BY month ORDER BY month'
);
$annualSt->execute([$year]);
$annualData = [];
foreach ($annualSt->fetchAll() as $r) {
    $annualData[$r['month']] = $r;
}

// Class-wise annual
$classAnnualSt = $db->prepare(
    'SELECT c.name AS class_name, SUM(CASE WHEN f.paid=1 THEN f.amount ELSE 0 END) AS collected,
            SUM(f.paid=1) AS paid, COUNT(f.id) AS total
     FROM fees f JOIN students s ON f.student_id=s.id JOIN classes c ON s.class_id=c.id
     WHERE f.year=? GROUP BY c.id ORDER BY c.grade, c.section'
);
$classAnnualSt->execute([$year]);
$classAnnual = $classAnnualSt->fetchAll();

$totalAnnual = array_sum(array_column($classAnnual, 'collected'));

$chartLabels = json_encode($months);
$chartCollected = [];
$chartPaid = [];
$chartUnpaid = [];
for ($m=1; $m<=12; $m++) {
    $chartCollected[] = $annualData[$m]['collected'] ?? 0;
    $chartPaid[]      = $annualData[$m]['paid'] ?? 0;
    $chartUnpaid[]    = $annualData[$m]['unpaid'] ?? 0;
}

pageHead('Fee Reports', 'finance');
$links = getFinanceLinks();
?>
<div class="portal-wrap">
<?php sidebar('finance', 'reports', $links, $user); ?>
<div class="main-area">
<?php topbar('Annual Fee Reports', $user); ?>
<div class="page-content">

<div class="d-flex gap-2 mb-3 align-items-end">
  <form method="GET" class="d-flex gap-2 align-items-end">
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

<!-- Collection chart -->
<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-chart-bar me-2"></i>Monthly Collection — <?= $year ?></div>
  <div style="padding:16px;max-height:320px">
    <canvas id="collectionChart"></canvas>
  </div>
</div>

<!-- Annual table -->
<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-table me-2"></i>Monthly Summary — <?= $year ?></div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.85rem">
      <thead class="table-light"><tr><th>Month</th><th>Total Records</th><th class="text-success">Paid</th><th class="text-danger">Unpaid</th><th>Collected (PKR)</th></tr></thead>
      <tbody>
        <?php for ($m=1; $m<=12; $m++):
          $d = $annualData[$m] ?? null;
        ?>
        <tr>
          <td><?= $months[$m-1] ?></td>
          <td><?= $d ? $d['total'] : '—' ?></td>
          <td class="text-success"><?= $d ? $d['paid'] : '—' ?></td>
          <td class="text-danger"><?= $d ? $d['unpaid'] : '—' ?></td>
          <td><?= $d ? 'PKR '.number_format($d['collected']) : '—' ?></td>
        </tr>
        <?php endfor; ?>
        <tr class="table-light fw-bold">
          <td>TOTAL</td><td></td><td></td><td></td>
          <td>PKR <?= number_format($totalAnnual) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Class-wise annual -->
<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-chalkboard me-2"></i>Class-wise Annual Summary</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.85rem">
      <thead class="table-light"><tr><th>Class</th><th>Total Records</th><th class="text-success">Paid</th><th>Collected (PKR)</th></tr></thead>
      <tbody>
        <?php foreach ($classAnnual as $ca): ?>
        <tr>
          <td class="fw-semibold"><?= h($ca['class_name']) ?></td>
          <td><?= $ca['total'] ?></td>
          <td class="text-success"><?= $ca['paid'] ?></td>
          <td>PKR <?= number_format($ca['collected']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('collectionChart'), {
    type: 'bar',
    data: {
        labels: <?= $chartLabels ?>,
        datasets: [{
            label: 'Collected (PKR)',
            data: <?= json_encode($chartCollected) ?>,
            backgroundColor: 'rgba(217,119,6,0.7)',
            borderColor: '#d97706',
            borderWidth:1
        },{
            label: 'Paid Students',
            data: <?= json_encode($chartPaid) ?>,
            type: 'line',
            borderColor: '#059669',
            fill: false,
            yAxisID: 'y2',
            tension: 0.3
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: true,
        scales: { y: { beginAtZero:true }, y2: { beginAtZero:true, position:'right', grid:{drawOnChartArea:false} } }
    }
});
</script>
</body></html>

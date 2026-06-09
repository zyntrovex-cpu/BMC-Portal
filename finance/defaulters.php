<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user    = requireAuth('finance');
$db      = getDB();
$classId = (int)($_GET['class_id'] ?? 0);
$minUnpaid = max(1, (int)($_GET['min_unpaid'] ?? 1));
$year    = (int)($_GET['year'] ?? date('Y'));
$classes = getAllClasses();
$months  = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// Find defaulters: students with unpaid fees in current year
$where  = ['(f.paid = 0 OR f.paid IS NULL)', 'f.year = ?'];
$params = [$year];
if ($classId) { $where[] = 's.class_id = ?'; $params[] = $classId; }
$whereStr = implode(' AND ', $where);

$defaultersSt = $db->prepare(
    "SELECT s.id AS student_id, u.name, s.roll_no, c.name AS class_name,
            s.parent_phone, s.phone,
            GROUP_CONCAT(f.month ORDER BY f.month) AS unpaid_months,
            COUNT(f.id) AS unpaid_count,
            SUM(f.amount) AS unpaid_amount,
            (SELECT MAX(f2.payment_date) FROM fees f2 WHERE f2.student_id = s.id AND f2.paid = 1) AS last_paid
     FROM students s
     JOIN users u ON s.user_id = u.id
     JOIN classes c ON s.class_id = c.id
     JOIN fees f ON f.student_id = s.id
     WHERE $whereStr
     GROUP BY s.id
     HAVING unpaid_count >= ?
     ORDER BY unpaid_count DESC, c.name, s.roll_no"
);
$defaultersSt->execute(array_merge($params, [$minUnpaid]));
$defaulters = $defaultersSt->fetchAll();

pageHead('Defaulters', 'finance');
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
<?php sidebar('finance', 'defaulters', $links); ?>
<div class="main-area">
<?php topbar('Fee Defaulters', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<form method="GET" class="d-flex gap-2 mb-3 flex-wrap align-items-end">
  <div>
    <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
    <select name="class_id" class="form-select form-select-sm">
      <option value="">All</option>
      <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classId===$c['id']?'selected':'' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="form-label fw-semibold" style="font-size:.82rem">Year</label>
    <select name="year" class="form-select form-select-sm">
      <?php for ($y=2023;$y<=2027;$y++): ?><option value="<?= $y ?>" <?= $year===$y?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
    </select>
  </div>
  <div>
    <label class="form-label fw-semibold" style="font-size:.82rem">Min Months Unpaid</label>
    <input type="number" name="min_unpaid" class="form-control form-control-sm" style="width:80px" value="<?= $minUnpaid ?>" min="1">
  </div>
  <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-search me-1"></i>Find</button>
</form>

<?php if (!empty($defaulters)): ?>
<div class="alert alert-warning" style="font-size:.85rem;border-radius:6px">
  <i class="fas fa-exclamation-triangle me-1"></i>Found <strong><?= count($defaulters) ?></strong> defaulters with <?= $minUnpaid ?>+ unpaid months in <?= $year ?>.
</div>
<?php endif; ?>

<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Defaulters List</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light"><tr><th>Name</th><th>Roll</th><th>Class</th><th>Unpaid Months</th><th>Months</th><th>Due Amount</th><th>Last Payment</th><th>Contact</th></tr></thead>
      <tbody>
        <?php foreach ($defaulters as $d):
          $unpaidMonthLabels = array_map(fn($m) => $months[(int)$m] ?? $m, explode(',', $d['unpaid_months']));
        ?>
        <tr>
          <td class="fw-semibold"><?= h($d['name']) ?></td>
          <td><?= h($d['roll_no']) ?></td>
          <td><?= h($d['class_name']) ?></td>
          <td><span class="badge bg-danger"><?= $d['unpaid_count'] ?></span></td>
          <td style="font-size:.78rem"><?= implode(', ', $unpaidMonthLabels) ?></td>
          <td class="text-danger fw-semibold">PKR <?= number_format($d['unpaid_amount']) ?></td>
          <td><?= $d['last_paid'] ? fDate($d['last_paid']) : '<span class="text-danger">Never</span>' ?></td>
          <td style="font-size:.78rem">
            <?php if ($d['parent_phone']): ?><div><i class="fas fa-user me-1"></i><?= h($d['parent_phone']) ?></div><?php endif; ?>
            <?php if ($d['phone']): ?><div><i class="fas fa-phone me-1"></i><?= h($d['phone']) ?></div><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($defaulters)): ?><tr><td colspan="8" class="text-center text-muted py-3">No defaulters found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

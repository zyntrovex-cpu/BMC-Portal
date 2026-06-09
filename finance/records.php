<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user    = requireAuth('finance');
$db      = getDB();
$q       = trim($_GET['q']        ?? '');
$classId = (int)($_GET['class_id'] ?? 0);
$paid    = $_GET['paid'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;
$months  = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$classes = getAllClasses();

$where  = ['1=1'];
$params = [];
if ($q) { $where[] = '(u.name LIKE ? OR s.roll_no LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($classId) { $where[] = 's.class_id = ?'; $params[] = $classId; }
if ($paid === '1') { $where[] = 'f.paid = 1'; }
if ($paid === '0') { $where[] = 'f.paid = 0'; }
$whereStr = implode(' AND ', $where);

$countSt = $db->prepare("SELECT COUNT(*) FROM fees f JOIN students s ON f.student_id = s.id JOIN users u ON s.user_id = u.id JOIN classes c ON s.class_id = c.id WHERE $whereStr");
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$recordsSt = $db->prepare(
    "SELECT f.*, u.name AS student_name, s.roll_no, c.name AS class_name
     FROM fees f
     JOIN students s ON f.student_id = s.id
     JOIN users u ON s.user_id = u.id
     JOIN classes c ON s.class_id = c.id
     WHERE $whereStr
     ORDER BY f.year DESC, f.month DESC, u.name
     LIMIT $perPage OFFSET $offset"
);
$recordsSt->execute($params);
$records = $recordsSt->fetchAll();

pageHead('Fee Records', 'finance');
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
<?php sidebar('finance', 'records', $links); ?>
<div class="main-area">
<?php topbar('Fee Records', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<form method="GET" class="d-flex gap-2 mb-3 flex-wrap align-items-end">
  <div>
    <label class="form-label fw-semibold" style="font-size:.82rem">Search</label>
    <input type="text" name="q" class="form-control form-control-sm" placeholder="Student name or roll no" value="<?= h($q) ?>" style="min-width:200px">
  </div>
  <div>
    <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
    <select name="class_id" class="form-select form-select-sm">
      <option value="">All</option>
      <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classId===$c['id']?'selected':'' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="form-label fw-semibold" style="font-size:.82rem">Status</label>
    <select name="paid" class="form-select form-select-sm">
      <option value="">All</option>
      <option value="1" <?= $paid==='1'?'selected':'' ?>>Paid</option>
      <option value="0" <?= $paid==='0'?'selected':'' ?>>Unpaid</option>
    </select>
  </div>
  <button type="submit" class="btn btn-sm btn-primary">Filter</button>
  <?php if ($q||$classId||$paid!==''): ?><a href="/finance/records.php" class="btn btn-sm btn-outline-danger">Clear</a><?php endif; ?>
</form>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between">
    <span><i class="fas fa-file-invoice-dollar me-2"></i>Fee Records (<?= $total ?>)</span>
    <span style="font-size:.8rem;color:#6b7280">Page <?= $page ?>/<?= $pages ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light"><tr><th>Student</th><th>Class</th><th>Month/Year</th><th>Amount</th><th>Status</th><th>Paid Date</th><th>Mode</th><th>Remarks</th></tr></thead>
      <tbody>
        <?php foreach ($records as $r): ?>
        <tr class="<?= $r['paid'] ? 'table-success' : '' ?>">
          <td><strong><?= h($r['student_name']) ?></strong><div style="font-size:.76rem;color:#9ca3af"><?= h($r['roll_no']) ?></div></td>
          <td><?= h($r['class_name']) ?></td>
          <td><?= $months[$r['month']] ?> <?= $r['year'] ?></td>
          <td>PKR <?= number_format($r['amount']) ?></td>
          <td><?= $r['paid'] ? '<span class="badge bg-success">Paid</span>' : '<span class="badge bg-danger">Unpaid</span>' ?></td>
          <td><?= $r['payment_date'] ? fDate($r['payment_date']) : '—' ?></td>
          <td><?= h($r['payment_mode'] ?: '—') ?></td>
          <td style="font-size:.78rem"><?= h($r['remarks'] ?: '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($records)): ?><tr><td colspan="8" class="text-center text-muted py-3">No records found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="d-flex justify-content-center p-2 gap-1 flex-wrap">
    <?php for ($i=1;$i<=$pages;$i++): ?>
      <a href="?q=<?= h($q) ?>&class_id=<?= $classId ?>&paid=<?= $paid ?>&page=<?= $i ?>"
         class="btn btn-xs <?= $i===$page?'btn-primary':'btn-outline-secondary' ?>" style="font-size:.76rem;padding:2px 7px"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

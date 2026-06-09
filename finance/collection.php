<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('finance');
$db   = getDB();

$classId = (int)($_GET['class_id'] ?? 0);
$month   = (int)($_GET['month']    ?? date('n'));
$year    = (int)($_GET['year']     ?? date('Y'));

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fee'])) {
    $pClassId = (int)$_POST['class_id'];
    $pMonth   = (int)$_POST['month'];
    $pYear    = (int)$_POST['year'];

    // Get default fee from settings
    $defFee = (float)($db->query('SELECT value FROM settings WHERE key_name="fee_amount"')->fetchColumn() ?: 5000);

    foreach ($_POST['fee'] as $studentId => $data) {
        $paid     = isset($data['paid']) ? 1 : 0;
        $paidDate = $paid ? (!empty($data['paid_date']) ? $data['paid_date'] : date('Y-m-d')) : null;
        $mode     = in_array($data['mode'] ?? '', ['Cash','Bank','Online','Cheque']) ? $data['mode'] : 'Cash';
        $amount   = (float)($data['amount'] ?? $defFee);
        $remarks  = trim($data['remarks'] ?? '');

        $db->prepare(
            'INSERT INTO fees (student_id, month, year, amount, paid, payment_date, payment_mode, remarks)
             VALUES (?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE paid=VALUES(paid), payment_date=VALUES(payment_date),
             payment_mode=VALUES(payment_mode), amount=VALUES(amount), remarks=VALUES(remarks)'
        )->execute([(int)$studentId, $pMonth, $pYear, $amount, $paid, $paidDate, $mode, $remarks]);
    }

    logActivity($user['id'], 'fees_save', "Fee collection saved for class #$pClassId, {$pMonth}/{$pYear}");
    setFlash('success','Fee records saved successfully.');
    header("Location: /finance/collection.php?class_id=$pClassId&month=$pMonth&year=$pYear");
    exit;
}

$classes = getAllClasses();

$students  = [];
$feesMap   = [];
$defAmount = 5000;
if ($classId) {
    $students = getClassStudents($classId);
    // Load existing fees
    if (!empty($students)) {
        $sIds = array_column($students, 'id');
        $placeholders = implode(',', array_fill(0, count($sIds), '?'));
        $fSt = $db->prepare("SELECT * FROM fees WHERE student_id IN ($placeholders) AND month=? AND year=?");
        $fSt->execute(array_merge($sIds, [$month, $year]));
        foreach ($fSt->fetchAll() as $f) {
            $feesMap[$f['student_id']] = $f;
        }
    }
    $defAmount = (float)($db->query('SELECT value FROM settings WHERE key_name="fee_amount"')->fetchColumn() ?: 5000);
}

$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

pageHead('Fee Collection', 'finance');
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
<?php sidebar('finance', 'collection', $links); ?>
<div class="main-area">
<?php topbar('Fee Collection', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<form method="GET" class="d-flex gap-2 mb-3 flex-wrap align-items-end">
  <div>
    <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
    <select name="class_id" class="form-select form-select-sm" style="min-width:140px">
      <option value="">Select class</option>
      <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classId===$c['id']?'selected':'' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="form-label fw-semibold" style="font-size:.82rem">Month</label>
    <select name="month" class="form-select form-select-sm">
      <?php foreach ($months as $mi => $mn): ?>
        <option value="<?= $mi+1 ?>" <?= $month===$mi+1?'selected':'' ?>><?= $mn ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="form-label fw-semibold" style="font-size:.82rem">Year</label>
    <select name="year" class="form-select form-select-sm">
      <?php for ($y=2023; $y<=2027; $y++): ?><option value="<?= $y ?>" <?= $year===$y?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-sm btn-primary">Load</button>
</form>

<?php if ($classId && !empty($students)): ?>
<form method="POST" id="feeForm">
  <input type="hidden" name="class_id" value="<?= $classId ?>">
  <input type="hidden" name="month" value="<?= $month ?>">
  <input type="hidden" name="year" value="<?= $year ?>">

  <!-- Summary bar -->
  <div class="d-flex gap-3 mb-2 px-1" style="font-size:.84rem">
    <span>Total: <strong><?= count($students) ?></strong></span>
    <span class="text-success">Paid: <strong id="cntPaid">0</strong></span>
    <span class="text-danger">Unpaid: <strong id="cntUnpaid"><?= count($students) ?></strong></span>
  </div>

  <div class="sec-card">
    <div class="sec-card-header d-flex justify-content-between align-items-center">
      <span><i class="fas fa-hand-holding-usd me-2"></i>
        <?php foreach($classes as $c) if($c['id']==$classId) echo h($c['name']); ?> —
        <?= $months[$month-1] ?> <?= $year ?>
      </span>
      <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-save me-1"></i>Save All</button>
    </div>
    <div class="table-responsive">
      <table class="table mb-0" style="font-size:.83rem">
        <thead class="table-light">
          <tr>
            <th>#</th><th>Roll</th><th>Name</th>
            <th style="width:60px">Paid</th>
            <th style="width:120px">Amount</th>
            <th style="width:130px">Paid Date</th>
            <th style="width:120px">Mode</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $i => $st):
            $fee    = $feesMap[$st['id']] ?? null;
            $isPaid = $fee && $fee['paid'];
            $rowCls = $isPaid ? 'fee-paid-row' : '';
          ?>
          <tr id="row<?= $st['id'] ?>" class="<?= $rowCls ?>">
            <td class="text-muted"><?= $i+1 ?></td>
            <td><?= h($st['roll_no']) ?></td>
            <td class="fw-semibold"><?= h($st['name']) ?></td>
            <td class="text-center">
              <input type="checkbox" name="fee[<?= $st['id'] ?>][paid]" value="1"
                     class="fee-tick" id="paid<?= $st['id'] ?>"
                     <?= $isPaid ? 'checked' : '' ?>
                     onchange="toggleRow(<?= $st['id'] ?>, this.checked)">
            </td>
            <td><input type="number" name="fee[<?= $st['id'] ?>][amount]" class="form-control form-control-sm" value="<?= $fee ? $fee['amount'] : $defAmount ?>" min="0"></td>
            <td><input type="date" name="fee[<?= $st['id'] ?>][paid_date]" class="form-control form-control-sm fee-detail" id="date<?= $st['id'] ?>" value="<?= $fee ? ($fee['payment_date'] ?? '') : '' ?>"></td>
            <td>
              <select name="fee[<?= $st['id'] ?>][mode]" class="form-select form-select-sm fee-detail" id="mode<?= $st['id'] ?>">
                <?php foreach (['Cash','Bank','Online','Cheque'] as $m): ?>
                  <option <?= ($fee['payment_mode']??'Cash')===$m?'selected':'' ?>><?= $m ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="text" name="fee[<?= $st['id'] ?>][remarks]" class="form-control form-control-sm" value="<?= h($fee['remarks'] ?? '') ?>"></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="padding:12px 16px;border-top:1px solid var(--border)">
      <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Save Fee Records</button>
    </div>
  </div>
</form>
<?php elseif ($classId && empty($students)): ?>
  <div class="sec-card p-3 text-muted">No students in selected class.</div>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<style>
.fee-paid-row { background: #f0fdf4 !important; }
.fee-tick { width:18px; height:18px; cursor:pointer; accent-color:#059669; }
</style>
<script>
function toggleRow(id, paid) {
    const row = document.getElementById('row'+id);
    row.classList.toggle('fee-paid-row', paid);
    if (paid && !document.getElementById('date'+id).value)
        document.getElementById('date'+id).value = new Date().toISOString().slice(0,10);
    updateCounts();
}
function updateCounts() {
    const all  = document.querySelectorAll('.fee-tick').length;
    const paid = document.querySelectorAll('.fee-tick:checked').length;
    document.getElementById('cntPaid').textContent   = paid;
    document.getElementById('cntUnpaid').textContent = all - paid;
}
updateCounts();
</script>
</body></html>

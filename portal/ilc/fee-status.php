<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp');
$db   = getDB();

// Check table exists
$tableExists = false;
try {
    $db->query('SELECT 1 FROM ilc_fee_payments LIMIT 1');
    $tableExists = true;
} catch (Exception $e) {}

// Current month as first-of-month
$selectedMonth = $_GET['month'] ?? date('Y-m');
$monthDate     = $selectedMonth . '-01';

// ── POST handler ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action    = $_POST['action']     ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);
    $month     = $_POST['month']      ?? $selectedMonth;
    $mDate     = $month . '-01';

    if ($action === 'toggle' && $studentId) {
        // Check current status for this student+month
        $cur = $db->prepare('SELECT id, status FROM ilc_fee_payments WHERE student_id=? AND month=?');
        $cur->execute([$studentId, $mDate]);
        $row = $cur->fetch();

        if ($row) {
            $newStatus = $row['status'] === 'paid' ? 'unpaid' : 'paid';
            $paidOn    = $newStatus === 'paid' ? date('Y-m-d') : null;
            $db->prepare('UPDATE ilc_fee_payments SET status=?,paid_on=?,recorded_by=? WHERE id=?')
               ->execute([$newStatus, $paidOn, $user['id'], $row['id']]);
        } else {
            // Insert as paid (first toggle from unpaid → paid)
            $db->prepare('INSERT INTO ilc_fee_payments (student_id,month,status,paid_on,recorded_by) VALUES (?,?,?,?,?)')
               ->execute([$studentId, $mDate, 'paid', date('Y-m-d'), $user['id']]);
        }
        logActivity($user['id'], 'ilc_fee_toggle', "Toggled fee status for student #$studentId ($month)");
    }

    if ($action === 'set_amount' && $studentId) {
        $amount = (float)($_POST['amount'] ?? 0);
        $cur = $db->prepare('SELECT id FROM ilc_fee_payments WHERE student_id=? AND month=?');
        $cur->execute([$studentId, $mDate]);
        $row = $cur->fetch();
        if ($row) {
            $db->prepare('UPDATE ilc_fee_payments SET amount=?,recorded_by=? WHERE id=?')
               ->execute([$amount ?: null, $user['id'], $row['id']]);
        } else {
            $db->prepare('INSERT INTO ilc_fee_payments (student_id,month,status,amount,recorded_by) VALUES (?,?,?,?,?)')
               ->execute([$studentId, $mDate, 'unpaid', $amount ?: null, $user['id']]);
        }
        setFlash('success', 'Amount updated.');
    }

    redirect('/portal/ilc/fee-status.php?month=' . urlencode($month));
}

// ── Fetch ILC students with current month fee status ───────────────
$viewStudent = (int)($_GET['student_id'] ?? 0);

$studentsSt = $db->prepare(
    "SELECT s.id AS student_id, u.name, s.roll_no, c.name AS class_name,
            ifp.id AS fee_id, ifp.status AS fee_status, ifp.amount, ifp.paid_on
     FROM students s
     JOIN users u ON u.id = s.user_id
     JOIN classes c ON c.id = s.class_id
     LEFT JOIN ilc_fee_payments ifp ON ifp.student_id = s.id AND ifp.month = ?
     WHERE c.is_ilc = 1
     ORDER BY c.name, s.roll_no"
);
$studentsSt->execute([$tableExists ? $monthDate : '1970-01-01']);
$students = $tableExists ? $studentsSt->fetchAll() : [];

$paidCount   = 0;
$unpaidCount = 0;
foreach ($students as $s) {
    if (($s['fee_status'] ?? '') === 'paid') $paidCount++;
    else $unpaidCount++;
}

// Payment history for selected student
$history = [];
if ($viewStudent && $tableExists) {
    $hSt = $db->prepare(
        'SELECT ifp.*, u.name AS recorder_name
         FROM ilc_fee_payments ifp
         JOIN users u ON u.id = ifp.recorded_by
         WHERE ifp.student_id = ?
         ORDER BY ifp.month DESC'
    );
    $hSt->execute([$viewStudent]);
    $history = $hSt->fetchAll();
}

pageHead('Fee Status — ILC', 'ilc_vp');
$links = getIlcLinks();
?>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'fee-status', $links, $user); ?>
<div class="main-area">
<?php topbar('ILC Fee Status', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  <strong>Migration not applied.</strong> Run <code>database/migrations/ilc_features.sql</code> first. ⚠️ Back up your DB!
</div>
<?php else: ?>

<!-- ILC strip -->
<div class="d-flex align-items-center gap-3 mb-3 p-3" style="background:linear-gradient(90deg,#ecfeff,#f0fdf4);border-radius:10px;border:1px solid #a5f3fc;">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC" style="width:40px;height:40px;object-fit:contain;flex-shrink:0;">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#0891b2;letter-spacing:.8px;text-transform:uppercase">Fee Status — <?= date('F Y', strtotime($monthDate)) ?></div>
    <div style="font-size:.78rem;color:#475569">Inclusive Learning Centre · Bahria Model College Bin Qasim</div>
  </div>
</div>

<!-- Month selector + stats -->
<div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
  <form method="GET" class="d-flex gap-2 align-items-center">
    <label class="fw-semibold" style="font-size:.84rem;white-space:nowrap">Month:</label>
    <input type="month" name="month" class="form-control form-control-sm" value="<?= h($selectedMonth) ?>" style="width:160px" onchange="this.form.submit()">
  </form>
  <div class="d-flex gap-2">
    <span class="badge bg-success" style="font-size:.84rem;padding:5px 12px"><?= $paidCount ?> Paid</span>
    <span class="badge bg-danger"  style="font-size:.84rem;padding:5px 12px"><?= $unpaidCount ?> Unpaid</span>
    <span class="badge bg-secondary" style="font-size:.84rem;padding:5px 12px"><?= count($students) ?> Total</span>
  </div>
</div>

<div class="row g-3">
  <!-- Students fee table -->
  <div class="col-lg-<?= $viewStudent ? '7' : '12' ?>">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-money-bill-wave me-2"></i>Fee Status — <?= date('F Y', strtotime($monthDate)) ?></div>
      <?php if (empty($students)): ?>
      <div style="padding:40px;text-align:center;color:var(--t2)">No ILC students found.</div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.83rem">
          <thead class="table-light">
            <tr><th>Roll</th><th>Name</th><th>Class</th><th class="text-center">Status</th><th class="text-center">Amount</th><th>Paid On</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($students as $s):
              $isPaid = ($s['fee_status'] ?? '') === 'paid';
            ?>
            <tr class="<?= $viewStudent === $s['student_id'] ? 'table-active' : '' ?>">
              <td class="fw-semibold"><?= h($s['roll_no']) ?></td>
              <td><?= h($s['name']) ?></td>
              <td><span class="badge bg-secondary"><?= h($s['class_name']) ?></span></td>
              <td class="text-center">
                <span class="badge <?= $isPaid ? 'bg-success' : 'bg-danger' ?>">
                  <?= $isPaid ? 'Paid' : 'Unpaid' ?>
                </span>
              </td>
              <td class="text-center"><?= $s['amount'] ? 'Rs '.number_format($s['amount']) : '—' ?></td>
              <td style="font-size:.78rem"><?= $s['paid_on'] ? date('d M Y', strtotime($s['paid_on'])) : '—' ?></td>
              <td>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
                  <input type="hidden" name="month" value="<?= $selectedMonth ?>">
                  <button class="btn btn-xs <?= $isPaid ? 'btn-outline-danger' : 'btn-outline-success' ?> me-1"
                          title="<?= $isPaid ? 'Mark Unpaid' : 'Mark Paid' ?>">
                    <i class="fas fa-<?= $isPaid ? 'times' : 'check' ?>"></i>
                    <?= $isPaid ? 'Unpaid' : 'Paid' ?>
                  </button>
                </form>
                <a href="?month=<?= urlencode($selectedMonth) ?>&student_id=<?= $s['student_id'] ?>"
                   class="btn btn-xs btn-outline-primary" title="View history">
                  <i class="fas fa-history"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Payment history for selected student -->
  <?php if ($viewStudent): ?>
  <?php $curStu = null; foreach ($students as $s) { if ($s['student_id'] === $viewStudent) { $curStu = $s; break; } } ?>
  <div class="col-lg-5">
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-history me-2"></i>Payment History<?= $curStu ? ' — '.h($curStu['name']) : '' ?></span>
        <a href="?month=<?= urlencode($selectedMonth) ?>" class="btn btn-xs btn-outline-secondary"><i class="fas fa-times"></i></a>
      </div>
      <?php if (empty($history)): ?>
      <div style="padding:30px;text-align:center;color:var(--t2);font-size:.84rem">No payment records yet.</div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm mb-0" style="font-size:.82rem">
          <thead class="table-light"><tr><th>Month</th><th>Status</th><th>Amount</th><th>Paid On</th></tr></thead>
          <tbody>
            <?php foreach ($history as $h): ?>
            <tr>
              <td class="fw-semibold"><?= date('M Y', strtotime($h['month'])) ?></td>
              <td><span class="badge <?= $h['status']==='paid'?'bg-success':'bg-danger' ?>"><?= ucfirst($h['status']) ?></span></td>
              <td><?= $h['amount'] ? 'Rs '.number_format($h['amount']) : '—' ?></td>
              <td style="font-size:.78rem"><?= $h['paid_on'] ? date('d M Y', strtotime($h['paid_on'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

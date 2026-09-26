<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('student');
$student = getStudentByUserId($user['id']);
$db      = getDB();

if (!$student) { setFlash('danger', 'Student profile not found.'); redirect('/portal/logout.php'); }

// Determine ILC vs regular
$isIlc = false;
try {
    $st = $db->prepare(
        'SELECT COALESCE(c.is_ilc, 0) AS is_ilc
         FROM students s
         LEFT JOIN classes c ON c.id = s.class_id
         WHERE s.user_id = ? LIMIT 1'
    );
    $st->execute([$user['id']]);
    $row   = $st->fetch();
    $isIlc = $row ? (bool)(int)$row['is_ilc'] : false;
} catch (Exception $e) {}

$records   = [];
$hasTable  = false;

if ($isIlc) {
    try {
        $db->query('SELECT 1 FROM ilc_fee_payments LIMIT 0');
        $hasTable = true;
    } catch (Exception $e) {}

    if ($hasTable) {
        $st = $db->prepare(
            'SELECT month, status, amount, paid_on
             FROM ilc_fee_payments
             WHERE student_id = ?
             ORDER BY month DESC'
        );
        $st->execute([$student['id']]);
        $records = $st->fetchAll();
    }
} else {
    try {
        $db->query('SELECT 1 FROM fees LIMIT 0');
        $hasTable = true;
    } catch (Exception $e) {}

    if ($hasTable) {
        $st = $db->prepare(
            'SELECT month, year, amount, paid, payment_date, payment_mode, receipt_no
             FROM fees
             WHERE student_id = ?
             ORDER BY year DESC, month DESC'
        );
        $st->execute([$student['id']]);
        $records = $st->fetchAll();
    }
}

$monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

$unpaidCount = 0;
if ($isIlc) {
    foreach ($records as $r) { if ($r['status'] === 'unpaid') $unpaidCount++; }
} else {
    foreach ($records as $r) { if (!$r['paid']) $unpaidCount++; }
}

pageHead('Fee Status', 'student');
$links = getStudentLinks();
?>
<div class="portal-wrap">
<?php sidebar('student', 'fees', $links, $user); ?>
<div class="main-area">
<?php topbar('Fee Status', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-money-bill-wave me-2"></i>My Fee Status</span>
    <?php if ($unpaidCount > 0): ?>
    <span class="badge bg-danger" style="font-size:.76rem">
      <?= $unpaidCount ?> Unpaid
    </span>
    <?php else: ?>
    <span class="badge bg-success" style="font-size:.76rem">All Clear</span>
    <?php endif; ?>
  </div>

  <?php if (!$hasTable): ?>
  <div style="padding:48px;text-align:center;color:var(--t2);font-size:.88rem">
    <i class="fas fa-database fa-2x mb-3 d-block" style="opacity:.15"></i>
    Fee records are not yet set up. Contact the administration.
  </div>

  <?php elseif (empty($records)): ?>
  <div style="padding:48px;text-align:center;color:var(--t2);font-size:.88rem">
    <i class="fas fa-receipt fa-2x mb-3 d-block" style="opacity:.15"></i>
    No fee records found for your account.
    <div style="font-size:.78rem;margin-top:6px">Records are entered by the finance department.</div>
  </div>

  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0" style="font-size:.85rem">
      <thead style="background:#f1f5f9">
        <tr>
          <th style="padding:10px 14px">Month</th>
          <th style="padding:10px 14px">Amount</th>
          <th style="padding:10px 14px;text-align:center">Status</th>
          <th style="padding:10px 14px">Paid On</th>
          <?php if (!$isIlc): ?><th style="padding:10px 14px">Mode / Receipt</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $r): ?>
        <?php if ($isIlc): $isPaid = $r['status'] === 'paid'; ?>
        <tr style="<?= $isPaid ? '' : 'background:#fff5f5' ?>">
          <td style="padding:9px 14px;font-weight:500"><?= date('F Y', strtotime($r['month'])) ?></td>
          <td style="padding:9px 14px">
            <?= $r['amount'] !== null ? 'PKR ' . number_format((float)$r['amount'], 0) : '<span style="color:var(--t2)">—</span>' ?>
          </td>
          <td style="padding:9px 14px;text-align:center">
            <span class="badge <?= $isPaid ? 'bg-success' : 'bg-danger' ?>" style="font-size:.75rem">
              <?= $isPaid ? 'Paid' : 'Unpaid' ?>
            </span>
          </td>
          <td style="padding:9px 14px">
            <?= $r['paid_on'] ? date('d M Y', strtotime($r['paid_on'])) : '<span style="color:var(--t2)">—</span>' ?>
          </td>
        </tr>

        <?php else: $isPaid = (bool)$r['paid']; ?>
        <tr style="<?= $isPaid ? '' : 'background:#fff5f5' ?>">
          <td style="padding:9px 14px;font-weight:500">
            <?= $monthNames[(int)$r['month']] . ' ' . $r['year'] ?>
          </td>
          <td style="padding:9px 14px">
            <?= $r['amount'] !== null ? 'PKR ' . number_format((float)$r['amount'], 0) : '<span style="color:var(--t2)">—</span>' ?>
          </td>
          <td style="padding:9px 14px;text-align:center">
            <span class="badge <?= $isPaid ? 'bg-success' : 'bg-danger' ?>" style="font-size:.75rem">
              <?= $isPaid ? 'Paid' : 'Unpaid' ?>
            </span>
          </td>
          <td style="padding:9px 14px">
            <?= $r['payment_date'] ? date('d M Y', strtotime($r['payment_date'])) : '<span style="color:var(--t2)">—</span>' ?>
          </td>
          <td style="padding:9px 14px;color:var(--t2);font-size:.8rem">
            <?php
              $parts = [];
              if (!empty($r['payment_mode'])) $parts[] = ucfirst($r['payment_mode']);
              if (!empty($r['receipt_no']))   $parts[] = '<code style="font-size:.78rem">' . h($r['receipt_no']) . '</code>';
              echo $parts ? implode(' &middot; ', $parts) : '—';
            ?>
          </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($unpaidCount > 0): ?>
  <div style="padding:12px 16px;border-top:1px solid var(--border);background:#fff5f5;font-size:.8rem;color:#7f1d1d">
    <i class="fas fa-info-circle me-1"></i>
    If you have already paid, please contact your school administration or finance office to get your payment recorded.
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

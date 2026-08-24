<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';
$user = requireAuth('finance');

$db = getDB();
$curMonth = (int)date('n');
$curYear  = (int)date('Y');

// ── Stats ─────────────────────────────────────────────────────────
$totalStudents = (int)$db->query('SELECT COUNT(*) FROM students s JOIN users u ON s.user_id=u.id WHERE u.status="active"')->fetchColumn();

$stPaid = $db->prepare('SELECT COUNT(DISTINCT student_id) FROM fees WHERE month=? AND year=? AND paid=1');
$stPaid->execute([$curMonth, $curYear]);
$paidCount = (int)$stPaid->fetchColumn();

$stUnpaid = $db->prepare(
    'SELECT COUNT(*) FROM students s
     LEFT JOIN fees f ON f.student_id=s.id AND f.month=? AND f.year=?
     WHERE (f.id IS NULL OR f.paid=0)'
);
$stUnpaid->execute([$curMonth, $curYear]);
$unpaidCount = (int)$stUnpaid->fetchColumn();

$stCollected = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM fees WHERE month=? AND year=? AND paid=1');
$stCollected->execute([$curMonth, $curYear]);
$totalCollected = (float)$stCollected->fetchColumn();

// ── Class-wise fee summary ────────────────────────────────────────
$classSummary = $db->prepare(
    'SELECT c.id, c.name, c.grade, c.section,
            COUNT(s.id) AS total_students,
            SUM(CASE WHEN f.paid=1 THEN 1 ELSE 0 END) AS paid_count,
            SUM(CASE WHEN f.paid=1 THEN f.amount ELSE 0 END) AS amount_collected
     FROM classes c
     LEFT JOIN students s ON s.class_id=c.id
     LEFT JOIN fees f ON f.student_id=s.id AND f.month=? AND f.year=?
     GROUP BY c.id, c.name, c.grade, c.section
     ORDER BY c.grade, c.section'
);
$classSummary->execute([$curMonth, $curYear]);
$classFees = $classSummary->fetchAll();

// ── Defaulters (2+ consecutive unpaid months, top 5) ─────────────
// Find students who have 2+ consecutive unpaid months ending at current
$defaulters = $db->prepare(
    'SELECT s.id, u.name, s.roll_no, c.name AS class_name,
            s.parent_phone,
            COUNT(f.id) AS unpaid_months,
            (SELECT MAX(f2.payment_date) FROM fees f2 WHERE f2.student_id=s.id AND f2.paid=1) AS last_payment
     FROM students s
     JOIN users u ON s.user_id=u.id
     LEFT JOIN classes c ON s.class_id=c.id
     JOIN fees f ON f.student_id=s.id AND f.paid=0
     GROUP BY s.id, u.name, s.roll_no, c.name, s.parent_phone
     HAVING COUNT(f.id) >= 2
     ORDER BY unpaid_months DESC
     LIMIT 5'
);
$defaulters->execute();
$defaulterList = $defaulters->fetchAll();

// ── Notices ───────────────────────────────────────────────────────
$notices    = getNoticesForPortal('finance');
$topNotices = array_slice($notices, 0, 3);

$monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

// ── Page render ───────────────────────────────────────────────────
pageHead('Finance Dashboard', 'finance');
$links = getFinanceLinks();
?>
<div class="portal-wrap">
<?php sidebar('finance', 'dashboard', $links, $user); ?>
<div class="main-area">
<?php topbar('Dashboard', $user); ?>
<div class="page-content">

<?= flashHtml() ?>

<!-- Welcome Banner -->
<div class="portal-banner mb-4" style="background:linear-gradient(135deg,#92400e,#d97706);">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h5 class="mb-1 fw-bold text-white">Welcome, <?= h($user['name']) ?>!</h5>
      <small style="color:rgba(255,255,255,.82)">
        Finance Portal &nbsp;&middot;&nbsp; <?= $monthNames[$curMonth] . ' ' . $curYear ?>
        &nbsp;&middot;&nbsp; <?= date('l, d M Y') ?>
      </small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= url('/finance/collection.php') ?>" class="btn btn-light btn-sm fw-semibold">
        <i class="fas fa-hand-holding-usd me-1"></i>Collect Fee
      </a>
      <a href="<?= url('/finance/monthly.php') ?>" class="btn btn-outline-light btn-sm fw-semibold">
        <i class="fas fa-calendar-alt me-1"></i>Monthly Report
      </a>
    </div>
  </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fff7ed"><i class="fas fa-users" style="color:#d97706"></i></div>
      <div class="stat-val" style="color:#d97706"><?= $totalStudents ?></div>
      <div class="stat-lbl">Total Students</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#f0fdf4"><i class="fas fa-check-circle" style="color:#059669"></i></div>
      <div class="stat-val" style="color:#059669"><?= $paidCount ?></div>
      <div class="stat-lbl">Paid (<?= $monthNames[$curMonth] ?>)</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef2f2"><i class="fas fa-times-circle" style="color:#ef4444"></i></div>
      <div class="stat-val" style="color:#ef4444"><?= $unpaidCount ?></div>
      <div class="stat-lbl">Unpaid (<?= $monthNames[$curMonth] ?>)</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fff7ed"><i class="fas fa-rupee-sign" style="color:#d97706"></i></div>
      <div class="stat-val" style="color:#d97706">₨<?= number_format($totalCollected) ?></div>
      <div class="stat-lbl">Collected (<?= $monthNames[$curMonth] ?>)</div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="row g-2 mb-4">
  <div class="col-auto">
    <a href="<?= url('/finance/collection.php') ?>" class="btn btn-warning fw-semibold">
      <i class="fas fa-hand-holding-usd me-2"></i>Collect Fee
    </a>
  </div>
  <div class="col-auto">
    <a href="<?= url('/finance/monthly.php') ?>" class="btn btn-outline-secondary fw-semibold">
      <i class="fas fa-calendar-alt me-2"></i>Monthly Report
    </a>
  </div>
  <div class="col-auto">
    <a href="<?= url('/finance/defaulters.php') ?>" class="btn btn-outline-danger fw-semibold">
      <i class="fas fa-exclamation-triangle me-2"></i>View Defaulters
    </a>
  </div>
</div>

<div class="row g-3">
  <!-- Left column -->
  <div class="col-lg-8">

    <!-- Class-wise Fee Summary -->
    <div class="sec-card mb-3">
      <div class="sec-head">
        <h5><i class="fas fa-table me-2" style="color:#d97706"></i>Class-wise Fee Summary — <?= h($monthNames[$curMonth] . ' ' . $curYear) ?></h5>
        <a href="<?= url('/finance/collection.php') ?>" class="btn btn-sm btn-outline-warning">Collect Fee</a>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Class</th>
              <th>Total Students</th>
              <th>Paid</th>
              <th>Unpaid</th>
              <th>Amount Collected</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($classFees)): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No class data available.</td></tr>
            <?php else: foreach ($classFees as $cls):
              $paid   = (int)$cls['paid_count'];
              $total  = (int)$cls['total_students'];
              $unpaid = $total - $paid;
            ?>
            <tr>
              <td class="fw-semibold"><?= h($cls['name']) ?></td>
              <td><?= $total ?></td>
              <td><span class="badge bg-success"><?= $paid ?></span></td>
              <td>
                <?php if ($unpaid > 0): ?>
                  <span class="badge bg-danger"><?= $unpaid ?></span>
                <?php else: ?>
                  <span class="badge bg-secondary">0</span>
                <?php endif; ?>
              </td>
              <td class="fw-semibold" style="color:#d97706">₨<?= number_format((float)$cls['amount_collected']) ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
          <tfoot>
            <tr>
              <td>Total</td>
              <td><?= array_sum(array_column($classFees,'total_students')) ?></td>
              <td><?= array_sum(array_column($classFees,'paid_count')) ?></td>
              <td><?= array_sum(array_column($classFees,'total_students')) - array_sum(array_column($classFees,'paid_count')) ?></td>
              <td style="color:#d97706">₨<?= number_format(array_sum(array_column($classFees,'amount_collected'))) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Defaulters -->
    <div class="sec-card">
      <div class="sec-head">
        <h5><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Top Defaulters (2+ Unpaid Months)</h5>
        <a href="<?= url('/finance/defaulters.php') ?>" class="btn btn-sm btn-outline-danger">View All</a>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Student</th>
              <th>Class</th>
              <th>Roll No</th>
              <th>Unpaid Months</th>
              <th>Last Payment</th>
              <th>Parent Phone</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($defaulterList)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">No defaulters found.</td></tr>
            <?php else: foreach ($defaulterList as $d): ?>
            <tr>
              <td class="fw-semibold"><?= h($d['name']) ?></td>
              <td><?= h($d['class_name'] ?? '—') ?></td>
              <td><?= h($d['roll_no']) ?></td>
              <td><span class="badge bg-danger"><?= h($d['unpaid_months']) ?></span></td>
              <td><?= fDate($d['last_payment']) ?></td>
              <td><?= $d['parent_phone'] ? h($d['parent_phone']) : '<span class="text-muted">—</span>' ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /col-lg-8 -->

  <!-- Right column -->
  <div class="col-lg-4">

    <!-- Notices -->
    <div class="sec-card">
      <div class="sec-head">
        <h5><i class="fas fa-bell me-2 text-warning"></i>Latest Notices</h5>
      </div>
      <div class="p-3">
        <?php if (empty($topNotices)): ?>
          <p class="text-muted text-center mb-0" style="font-size:13px">No notices.</p>
        <?php else: foreach ($topNotices as $n):
          $pinHtml = $n['pinned'] ? '<i class="fas fa-thumbtack text-danger me-1"></i>' : '';
          $priorityColors = ['Urgent'=>'#ef4444','Important'=>'#d97706','Normal'=>'#059669'];
          $pColor = $priorityColors[$n['priority'] ?? 'Normal'] ?? '#6b7280';
        ?>
        <div style="display:flex;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)">
          <div style="width:34px;height:34px;border-radius:6px;background:#fff7ed;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas fa-bullhorn" style="color:#d97706;font-size:13px"></i>
          </div>
          <div>
            <div class="fw-semibold" style="font-size:13px"><?= $pinHtml ?><?= h($n['title']) ?></div>
            <small class="text-muted"><?= fDate($n['created_at']) ?></small>
            <span class="badge ms-1" style="background:<?= $pColor ?>;font-size:9px"><?= h(ucfirst($n['priority'] ?? 'low')) ?></span>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Monthly Overview mini-chart -->
    <div class="sec-card mt-3">
      <div class="sec-head">
        <h5><i class="fas fa-info-circle me-2" style="color:#d97706"></i>This Month</h5>
      </div>
      <div class="p-3">
        <?php
          $collectionPct = $totalStudents > 0 ? round($paidCount / $totalStudents * 100, 1) : 0;
        ?>
        <div class="d-flex justify-content-between mb-1">
          <small class="text-muted">Collection Rate</small>
          <small class="fw-bold"><?= $collectionPct ?>%</small>
        </div>
        <div class="att-bar-wrap mb-3">
          <div class="att-bar-fill" style="width:<?= $collectionPct ?>%;background:#d97706"></div>
        </div>
        <div class="d-flex flex-column gap-2">
          <div class="d-flex justify-content-between">
            <span class="text-muted" style="font-size:12px">Total Collected</span>
            <span class="fw-semibold" style="color:#d97706;font-size:12px">₨<?= number_format($totalCollected) ?></span>
          </div>
          <div class="d-flex justify-content-between">
            <span class="text-muted" style="font-size:12px">Students Paid</span>
            <span class="fw-semibold text-success" style="font-size:12px"><?= $paidCount ?></span>
          </div>
          <div class="d-flex justify-content-between">
            <span class="text-muted" style="font-size:12px">Students Unpaid</span>
            <span class="fw-semibold text-danger" style="font-size:12px"><?= $unpaidCount ?></span>
          </div>
        </div>
        <hr class="my-2">
        <a href="<?= url('/finance/reports.php') ?>" class="btn btn-warning btn-sm w-100 fw-semibold">
          <i class="fas fa-chart-pie me-1"></i>Full Reports
        </a>
      </div>
    </div>

  </div><!-- /col-lg-4 -->
</div><!-- /row -->

</div><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /portal-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

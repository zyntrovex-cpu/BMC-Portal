<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('finance');
requirePermission('fee_records');
$db      = getDB();

$q         = trim($_GET['q']          ?? '');
$classId   = (int)($_GET['class_id']   ?? 0);
$paid      = $_GET['paid']             ?? '';
$wing      = $_GET['wing']             ?? '';
$yearFrom  = (int)($_GET['year_from']  ?? 0);
$monthFrom = (int)($_GET['month_from'] ?? 0);
$yearTo    = (int)($_GET['year_to']    ?? 0);
$monthTo   = (int)($_GET['month_to']   ?? 0);
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;
$offset    = ($page - 1) * $perPage;
$months    = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$classes   = getAllClasses();
$curYear   = (int)date('Y');
$yearRange = range($curYear - 5, $curYear + 1);

// Detect wing column on classes table
$hasWingCol = false;
try { $db->query('SELECT wing FROM classes LIMIT 0'); $hasWingCol = true; } catch (Exception $e) {}

$allowedWings = ['main', 'montessori', 'ilc'];
if (!in_array($wing, $allowedWings)) $wing = '';

// Build WHERE / params (shared by both the export and the paginated view)
$where  = ['1=1'];
$params = [];
if ($q)       { $where[] = '(u.name LIKE ? OR s.roll_no LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($classId) { $where[] = 's.class_id = ?'; $params[] = $classId; }
if ($paid === '1') { $where[] = 'f.paid = 1'; }
if ($paid === '0') { $where[] = 'f.paid = 0'; }
if ($wing && $hasWingCol) { $where[] = 'c.wing = ?'; $params[] = $wing; }
if ($yearFrom) {
    $mFrom = $monthFrom ?: 1;
    $where[]  = '(f.year > ? OR (f.year = ? AND f.month >= ?))';
    $params[] = $yearFrom; $params[] = $yearFrom; $params[] = $mFrom;
}
if ($yearTo) {
    $mTo = $monthTo ?: 12;
    $where[]  = '(f.year < ? OR (f.year = ? AND f.month <= ?))';
    $params[] = $yearTo; $params[] = $yearTo; $params[] = $mTo;
}
$whereStr   = implode(' AND ', $where);
$wingSelect = $hasWingCol ? ", COALESCE(c.wing,'main') AS class_wing" : ", 'main' AS class_wing";

// ── CSV Export (runs before any HTML, then exits) ───────────────────
if (($_GET['export'] ?? '') === 'csv') {
    $exportSt = $db->prepare(
        "SELECT f.*, u.name AS student_name, s.roll_no, c.name AS class_name $wingSelect
         FROM fees f
         JOIN students s ON f.student_id = s.id
         JOIN users u    ON s.user_id    = u.id
         JOIN classes c  ON s.class_id   = c.id
         WHERE $whereStr
         ORDER BY f.year DESC, f.month DESC, u.name"
    );
    $exportSt->execute($params);
    $rows = $exportSt->fetchAll();

    $filename = 'BMC_Fee_Records_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM so Excel opens it correctly

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        '#', 'Student Name', 'GR / Roll No', 'Class', 'Campus / Wing',
        'Month', 'Year', 'Fee Amount (PKR)', 'Status',
        'Payment Date', 'Payment Mode', 'Transaction Ref', 'Remarks',
    ]);
    $i = 1;
    foreach ($rows as $r) {
        $wLabel = match($r['class_wing'] ?? 'main') {
            'montessori' => 'Montessori',
            'ilc'        => 'ILC',
            default      => 'Main Wing',
        };
        fputcsv($out, [
            $i++,
            $r['student_name'],
            $r['roll_no'],
            $r['class_name'],
            $wLabel,
            $months[$r['month']] ?? $r['month'],
            $r['year'],
            $r['amount'],
            $r['paid'] ? 'Paid' : 'Unpaid',
            $r['payment_date'] ?? '',
            $r['payment_mode'] ?? '',
            $r['transaction_ref'] ?? '',
            $r['remarks'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// ── Normal paginated view ────────────────────────────────────────────
$countSt = $db->prepare(
    "SELECT COUNT(*) FROM fees f
     JOIN students s ON f.student_id = s.id
     JOIN users u    ON s.user_id    = u.id
     JOIN classes c  ON s.class_id   = c.id
     WHERE $whereStr"
);
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$recordsSt = $db->prepare(
    "SELECT f.*, u.name AS student_name, s.roll_no, c.name AS class_name $wingSelect
     FROM fees f
     JOIN students s ON f.student_id = s.id
     JOIN users u    ON s.user_id    = u.id
     JOIN classes c  ON s.class_id   = c.id
     WHERE $whereStr
     ORDER BY f.year DESC, f.month DESC, u.name
     LIMIT $perPage OFFSET $offset"
);
$recordsSt->execute($params);
$records = $recordsSt->fetchAll();

// Build the export URL (current filters + export=csv, no page param)
$exportParams = array_filter([
    'q'          => $q,
    'class_id'   => $classId ?: null,
    'paid'       => $paid !== '' ? $paid : null,
    'wing'       => $wing ?: null,
    'year_from'  => $yearFrom ?: null,
    'month_from' => $monthFrom ?: null,
    'year_to'    => $yearTo ?: null,
    'month_to'   => $monthTo ?: null,
    'export'     => 'csv',
]);
$exportUrl = url('/portal/finance/records.php') . '?' . http_build_query($exportParams);

$hasFilters = $q || $classId || $paid !== '' || $wing || $yearFrom || $yearTo;

pageHead('Fee Records', 'finance');
$links = getFinanceLinks();
?>
<div class="portal-wrap">
<?php sidebar('finance', 'records', $links, $user); ?>
<div class="main-area">
<?php topbar('Fee Records', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- ── Filter Form ─────────────────────────────────────────────── -->
<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-filter me-2"></i>Filter Records</div>
  <div style="padding:14px">
    <form method="GET" id="filterForm">
      <div class="row g-2 align-items-end">

        <!-- Search -->
        <div class="col-md-3 col-sm-6">
          <label class="form-label fw-semibold" style="font-size:.82rem">Search</label>
          <input type="text" name="q" class="form-control form-control-sm"
                 placeholder="Student name or roll no" value="<?= h($q) ?>">
        </div>

        <!-- Campus / Wing -->
        <div class="col-md-2 col-sm-6">
          <label class="form-label fw-semibold" style="font-size:.82rem">Campus / Wing</label>
          <select name="wing" class="form-select form-select-sm">
            <option value="">All Campuses</option>
            <option value="main"        <?= $wing==='main'?'selected':'' ?>>Main Wing</option>
            <option value="montessori"  <?= $wing==='montessori'?'selected':'' ?>>Montessori</option>
            <option value="ilc"         <?= $wing==='ilc'?'selected':'' ?>>ILC</option>
          </select>
        </div>

        <!-- Class -->
        <div class="col-md-2 col-sm-6">
          <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
          <select name="class_id" class="form-select form-select-sm">
            <option value="">All Classes</option>
            <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $classId===$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Payment Status -->
        <div class="col-md-2 col-sm-6">
          <label class="form-label fw-semibold" style="font-size:.82rem">Payment Status</label>
          <select name="paid" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="1" <?= $paid==='1'?'selected':'' ?>>Paid</option>
            <option value="0" <?= $paid==='0'?'selected':'' ?>>Unpaid</option>
          </select>
        </div>

        <!-- Buttons row 1 -->
        <div class="col-md-3 col-sm-12 d-flex gap-2">
          <button type="submit" class="btn btn-sm btn-primary">
            <i class="fas fa-search me-1"></i>Filter
          </button>
          <?php if ($hasFilters): ?>
          <a href="<?= url('/portal/finance/records.php') ?>" class="btn btn-sm btn-outline-danger">
            <i class="fas fa-times me-1"></i>Clear
          </a>
          <?php endif; ?>
        </div>

      </div><!-- /row 1 -->

      <!-- Date Range Row -->
      <div class="row g-2 align-items-end mt-1">
        <div class="col-auto">
          <label class="form-label fw-semibold" style="font-size:.82rem">From</label>
          <div class="d-flex gap-1">
            <select name="month_from" class="form-select form-select-sm" style="width:90px">
              <option value="">Month</option>
              <?php foreach ($months as $mi => $ml): if (!$mi) continue; ?>
              <option value="<?= $mi ?>" <?= $monthFrom===$mi?'selected':'' ?>><?= $ml ?></option>
              <?php endforeach; ?>
            </select>
            <select name="year_from" class="form-select form-select-sm" style="width:82px">
              <option value="">Year</option>
              <?php foreach ($yearRange as $yr): ?>
              <option value="<?= $yr ?>" <?= $yearFrom===$yr?'selected':'' ?>><?= $yr ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="col-auto">
          <label class="form-label fw-semibold" style="font-size:.82rem">To</label>
          <div class="d-flex gap-1">
            <select name="month_to" class="form-select form-select-sm" style="width:90px">
              <option value="">Month</option>
              <?php foreach ($months as $mi => $ml): if (!$mi) continue; ?>
              <option value="<?= $mi ?>" <?= $monthTo===$mi?'selected':'' ?>><?= $ml ?></option>
              <?php endforeach; ?>
            </select>
            <select name="year_to" class="form-select form-select-sm" style="width:82px">
              <option value="">Year</option>
              <?php foreach ($yearRange as $yr): ?>
              <option value="<?= $yr ?>" <?= $yearTo===$yr?'selected':'' ?>><?= $yr ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="col-auto d-flex align-items-end">
          <a href="<?= h($exportUrl) ?>" class="btn btn-sm btn-success" title="Download filtered records as Excel/CSV">
            <i class="fas fa-file-excel me-1"></i>Download Fee Report
          </a>
        </div>
      </div><!-- /date range row -->

    </form>
  </div>
</div>

<!-- ── Records Table ───────────────────────────────────────────────── -->
<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-file-invoice-dollar me-2"></i>Fee Records (<?= $total ?>)</span>
    <div class="d-flex align-items-center gap-3">
      <?php if ($hasFilters): ?>
      <span style="font-size:.76rem;color:#059669;font-weight:600">
        <i class="fas fa-filter me-1"></i>Filtered
      </span>
      <?php endif; ?>
      <span style="font-size:.8rem;color:#6b7280">Page <?= $page ?>/<?= $pages ?></span>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr>
          <th>Student</th>
          <th>Class</th>
          <?php if ($hasWingCol): ?><th>Wing</th><?php endif; ?>
          <th>Month/Year</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Paid Date</th>
          <th>Mode</th>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $r): ?>
        <tr class="<?= $r['paid'] ? 'table-success' : '' ?>">
          <td>
            <strong><?= h($r['student_name']) ?></strong>
            <div style="font-size:.76rem;color:#9ca3af"><?= h($r['roll_no']) ?></div>
          </td>
          <td><?= h($r['class_name']) ?></td>
          <?php if ($hasWingCol): ?>
          <td><?php
            [$wLabel,$wColor] = match($r['class_wing'] ?? 'main') {
                'montessori' => ['Montessori','#059669'],
                'ilc'        => ['ILC','#0891b2'],
                default      => ['Main','#2563eb'],
            };
            echo '<span style="font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:20px;background:'.$wColor.';color:#fff">'.$wLabel.'</span>';
          ?></td>
          <?php endif; ?>
          <td><?= $months[$r['month']] ?> <?= $r['year'] ?></td>
          <td>PKR <?= number_format($r['amount']) ?></td>
          <td><?= $r['paid'] ? '<span class="badge bg-success">Paid</span>' : '<span class="badge bg-danger">Unpaid</span>' ?></td>
          <td><?= $r['payment_date'] ? fDate($r['payment_date']) : '—' ?></td>
          <td><?= h($r['payment_mode'] ?: '—') ?></td>
          <td style="font-size:.78rem"><?= h($r['remarks'] ?: '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($records)): ?>
        <tr><td colspan="9" class="text-center text-muted py-3">No records found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="d-flex justify-content-center p-2 gap-1 flex-wrap">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?q=<?= h($q) ?>&class_id=<?= $classId ?>&paid=<?= h($paid) ?>&wing=<?= h($wing) ?>&year_from=<?= $yearFrom ?>&month_from=<?= $monthFrom ?>&year_to=<?= $yearTo ?>&month_to=<?= $monthTo ?>&page=<?= $i ?>"
       class="btn btn-xs <?= $i===$page?'btn-primary':'btn-outline-secondary' ?>" style="font-size:.76rem;padding:2px 7px"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

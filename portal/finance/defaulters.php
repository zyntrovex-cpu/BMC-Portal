<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user     = requireAuth('finance');
requirePermission('fee_defaulters');
$db       = getDB();

$classId   = (int)($_GET['class_id']   ?? 0);
$minUnpaid = max(1, (int)($_GET['min_unpaid'] ?? 1));
$year      = (int)($_GET['year']       ?? date('Y'));
$wing      = $_GET['wing']             ?? '';
$q         = trim($_GET['q']           ?? '');
$minDue    = (int)($_GET['min_due']    ?? 0);
$monthFrom = (int)($_GET['month_from'] ?? 0);
$monthTo   = (int)($_GET['month_to']   ?? 0);
$classes   = getAllClasses();
$months    = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$curYear   = (int)date('Y');
$yearRange = range($curYear - 5, $curYear + 2);

// Detect wing column on classes
$hasWingCol = false;
try { $db->query('SELECT wing FROM classes LIMIT 0'); $hasWingCol = true; } catch (Exception $e) {}

$allowedWings = ['main', 'montessori', 'ilc'];
if (!in_array($wing, $allowedWings)) $wing = '';

// ── Build WHERE ──────────────────────────────────────────────────────
$where  = ['f.paid = 0', 'f.year = ?'];
$params = [$year];
if ($q)       { $where[] = '(u.name LIKE ? OR s.roll_no LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($classId) { $where[] = 's.class_id = ?'; $params[] = $classId; }
if ($wing && $hasWingCol) { $where[] = 'c.wing = ?'; $params[] = $wing; }
if ($monthFrom) { $where[] = 'f.month >= ?'; $params[] = $monthFrom; }
if ($monthTo)   { $where[] = 'f.month <= ?'; $params[] = $monthTo; }
$whereStr = implode(' AND ', $where);

$having       = ['unpaid_count >= ?'];
$havingParams = [$minUnpaid];
if ($minDue > 0) { $having[] = 'unpaid_amount >= ?'; $havingParams[] = $minDue; }
$havingStr = implode(' AND ', $having);

// Wing in SELECT
$wingSelect = $hasWingCol ? ", COALESCE(c.wing,'main') AS class_wing" : ", 'main' AS class_wing";

// Base query — year params for the two subqueries come first (positional binding)
$baseQuery =
    "SELECT s.id AS student_id, u.name, s.roll_no, c.name AS class_name,
            s.parent_phone, s.phone $wingSelect,
            GROUP_CONCAT(f.month ORDER BY f.month) AS unpaid_months,
            COUNT(f.id)   AS unpaid_count,
            SUM(f.amount) AS unpaid_amount,
            COALESCE((SELECT SUM(f2.amount) FROM fees f2
                      WHERE f2.student_id = s.id AND f2.year = ?), 0) AS total_fee,
            COALESCE((SELECT SUM(f2.amount) FROM fees f2
                      WHERE f2.student_id = s.id AND f2.year = ? AND f2.paid = 1), 0) AS paid_amount,
            (SELECT MAX(f2.payment_date) FROM fees f2
             WHERE f2.student_id = s.id AND f2.paid = 1) AS last_paid
     FROM students s
     JOIN users u  ON s.user_id   = u.id
     JOIN classes c ON s.class_id  = c.id
     JOIN fees f   ON f.student_id = s.id
     WHERE $whereStr
     GROUP BY s.id
     HAVING $havingStr
     ORDER BY unpaid_count DESC, c.name, s.roll_no";

// Positional order: [year for total_fee subq, year for paid_amount subq, ...WHERE params, ...HAVING params]
$allParams = array_merge([$year, $year], $params, $havingParams);

// ── CSV Export (fires before HTML output) ────────────────────────────
if (($_GET['export'] ?? '') === 'csv') {
    $exportSt = $db->prepare($baseQuery);
    $exportSt->execute($allParams);
    $rows = $exportSt->fetchAll();

    $filename = 'BMC_Defaulters_' . $year . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM — Excel opens correctly

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        '#', 'Student Name', 'GR / Roll No', 'Class', 'Campus / Wing', 'Year',
        'Unpaid Months Count', 'Unpaid Months',
        'Total Fee (Year, PKR)', 'Paid Amount (PKR)', 'Outstanding Amount (PKR)',
        'Last Payment Date', 'Parent Phone', 'Student Phone',
    ]);

    $i = 1;
    foreach ($rows as $d) {
        $wLabel = match($d['class_wing'] ?? 'main') {
            'montessori' => 'Montessori',
            'ilc'        => 'ILC',
            default      => 'Main Wing',
        };
        $unpaidLabels = implode(', ', array_map(
            fn($m) => ($months[(int)$m] ?? $m) . ' ' . $year,
            explode(',', $d['unpaid_months'])
        ));
        fputcsv($out, [
            $i++,
            $d['name'],
            $d['roll_no'],
            $d['class_name'],
            $wLabel,
            $year,
            $d['unpaid_count'],
            $unpaidLabels,
            $d['total_fee'],
            $d['paid_amount'],
            $d['unpaid_amount'],
            $d['last_paid'] ?? '',
            $d['parent_phone'] ?? '',
            $d['phone'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// ── Normal view ──────────────────────────────────────────────────────
$defaultersSt = $db->prepare($baseQuery);
$defaultersSt->execute($allParams);
$defaulters = $defaultersSt->fetchAll();

// Build the export URL carrying all current filters
$exportParams = array_filter([
    'year'       => $year,
    'class_id'   => $classId   ?: null,
    'min_unpaid' => $minUnpaid > 1 ? $minUnpaid : null,
    'wing'       => $wing      ?: null,
    'q'          => $q         ?: null,
    'min_due'    => $minDue    ?: null,
    'month_from' => $monthFrom ?: null,
    'month_to'   => $monthTo   ?: null,
    'export'     => 'csv',
], fn($v) => $v !== null && $v !== '' && $v !== false);
$exportUrl = url('/portal/finance/defaulters.php') . '?' . http_build_query($exportParams);

$hasExtraFilters = $classId || $wing || $q || $minDue || $monthFrom || $monthTo;

pageHead('Defaulters', 'finance');
$links = getFinanceLinks();
?>
<div class="portal-wrap">
<?php sidebar('finance', 'defaulters', $links, $user); ?>
<div class="main-area">
<?php topbar('Fee Defaulters', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- ── Filter Panel ───────────────────────────────────────────────── -->
<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-filter me-2"></i>Filter Defaulters</div>
  <div style="padding:14px">
    <form method="GET" id="filterForm">
      <div class="row g-2 align-items-end">

        <!-- Search -->
        <div class="col-md-3 col-sm-6">
          <label class="form-label fw-semibold" style="font-size:.82rem">Search Student</label>
          <input type="text" name="q" class="form-control form-control-sm"
                 placeholder="Name or roll no" value="<?= h($q) ?>">
        </div>

        <!-- Campus / Wing -->
        <div class="col-md-2 col-sm-6">
          <label class="form-label fw-semibold" style="font-size:.82rem">Campus / Wing</label>
          <select name="wing" class="form-select form-select-sm">
            <option value="">All Campuses</option>
            <option value="main"       <?= $wing==='main'       ?'selected':'' ?>>Main Wing</option>
            <option value="montessori" <?= $wing==='montessori' ?'selected':'' ?>>Montessori</option>
            <option value="ilc"        <?= $wing==='ilc'        ?'selected':'' ?>>ILC</option>
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

        <!-- Year -->
        <div class="col-md-1 col-sm-4">
          <label class="form-label fw-semibold" style="font-size:.82rem">Year</label>
          <select name="year" class="form-select form-select-sm">
            <?php foreach ($yearRange as $yr): ?>
            <option value="<?= $yr ?>" <?= $year===$yr?'selected':'' ?>><?= $yr ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Min Months Unpaid -->
        <div class="col-md-1 col-sm-4">
          <label class="form-label fw-semibold" style="font-size:.82rem">Min Months</label>
          <input type="number" name="min_unpaid" class="form-control form-control-sm"
                 value="<?= $minUnpaid ?>" min="1" style="width:70px">
        </div>

        <!-- Min Outstanding -->
        <div class="col-md-2 col-sm-4">
          <label class="form-label fw-semibold" style="font-size:.82rem">Min Due (PKR)</label>
          <input type="number" name="min_due" class="form-control form-control-sm"
                 value="<?= $minDue ?: '' ?>" min="0" placeholder="e.g. 5000">
        </div>

        <!-- Action buttons -->
        <div class="col-md-1 col-sm-12 d-flex gap-2">
          <button type="submit" class="btn btn-sm btn-danger w-100">
            <i class="fas fa-search me-1"></i>Find
          </button>
        </div>
      </div><!-- /row 1 -->

      <!-- Month range + Download -->
      <div class="row g-2 align-items-end mt-1">
        <div class="col-auto">
          <label class="form-label fw-semibold" style="font-size:.82rem">From Month</label>
          <select name="month_from" class="form-select form-select-sm" style="width:105px">
            <option value="">Any</option>
            <?php foreach ($months as $mi => $ml): if (!$mi) continue; ?>
            <option value="<?= $mi ?>" <?= $monthFrom===$mi?'selected':'' ?>><?= $ml ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <label class="form-label fw-semibold" style="font-size:.82rem">To Month</label>
          <select name="month_to" class="form-select form-select-sm" style="width:105px">
            <option value="">Any</option>
            <?php foreach ($months as $mi => $ml): if (!$mi) continue; ?>
            <option value="<?= $mi ?>" <?= $monthTo===$mi?'selected':'' ?>><?= $ml ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto d-flex align-items-end gap-2">
          <a href="<?= h($exportUrl) ?>" class="btn btn-sm btn-success" title="Download filtered defaulter list as Excel/CSV">
            <i class="fas fa-file-excel me-1"></i>Download Defaulter Report
          </a>
          <?php if ($hasExtraFilters || $year !== $curYear || $minUnpaid > 1): ?>
          <a href="<?= url('/portal/finance/defaulters.php') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-times me-1"></i>Clear
          </a>
          <?php endif; ?>
        </div>
      </div><!-- /month range row -->

    </form>
  </div>
</div>

<?php if (!empty($defaulters)): ?>
<div class="alert alert-warning" style="font-size:.85rem;border-radius:6px;margin-bottom:12px">
  <i class="fas fa-exclamation-triangle me-1"></i>Found <strong><?= count($defaulters) ?></strong> defaulter<?= count($defaulters)!==1?'s':'' ?> with <?= $minUnpaid ?>+ unpaid month<?= $minUnpaid!==1?'s':'' ?> in <?= $year ?><?= $minDue?" and PKR ".number_format($minDue)."+ outstanding":'' ?>.
</div>
<?php endif; ?>

<!-- ── Defaulters Table ────────────────────────────────────────────── -->
<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Defaulters List</span>
    <span style="font-size:.8rem;color:#6b7280"><?= count($defaulters) ?> result<?= count($defaulters)!==1?'s':'' ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr>
          <th>Name</th><th>Roll</th><th>Class</th>
          <?php if ($hasWingCol): ?><th>Wing</th><?php endif; ?>
          <th>Unpaid Months</th><th>Months</th><th>Due Amount</th><th>Last Payment</th><th>Contact</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($defaulters as $d):
          $unpaidMonthLabels = array_map(
              fn($m) => $months[(int)$m] ?? $m,
              explode(',', $d['unpaid_months'])
          );
        ?>
        <tr>
          <td class="fw-semibold"><?= h($d['name']) ?></td>
          <td><?= h($d['roll_no']) ?></td>
          <td><?= h($d['class_name']) ?></td>
          <?php if ($hasWingCol): ?>
          <td><?php
            [$wLabel,$wColor] = match($d['class_wing'] ?? 'main') {
                'montessori' => ['Montessori','#059669'],
                'ilc'        => ['ILC','#0891b2'],
                default      => ['Main','#2563eb'],
            };
            echo '<span style="font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:20px;background:'.$wColor.';color:#fff">'.$wLabel.'</span>';
          ?></td>
          <?php endif; ?>
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
        <?php if (empty($defaulters)): ?>
        <tr><td colspan="<?= $hasWingCol ? 9 : 8 ?>" class="text-center text-muted py-3">No defaulters found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('student_affairs');
$db   = getDB();

// ── Check whether graduation columns exist ────────────────────────
$hasGradCols = false;
try {
    $db->query('SELECT graduated_at FROM students LIMIT 0');
    $hasGradCols = true;
} catch (Exception $e) {}

// ── Filters ───────────────────────────────────────────────────────
$search    = trim($_GET['q']    ?? '');
$batchYear = trim($_GET['batch'] ?? '');
$viewId    = (int)($_GET['view'] ?? 0);

// ── Fetch alumni list ─────────────────────────────────────────────
$alumni = [];
$batches = [];
if ($hasGradCols) {
    // Distinct batch years for filter dropdown
    try {
        $batches = $db->query(
            "SELECT DISTINCT graduation_year FROM students
             WHERE graduated_at IS NOT NULL AND graduation_year IS NOT NULL
             ORDER BY graduation_year DESC"
        )->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}

    // Build main query
    $where  = ['s.graduated_at IS NOT NULL'];
    $params = [];
    if ($search !== '') {
        $like     = '%' . $search . '%';
        $where[]  = '(u.name LIKE ? OR s.roll_no LIKE ? OR u.user_id LIKE ? OR s.father_name LIKE ?)';
        $params   = array_merge($params, [$like, $like, $like, $like]);
    }
    if ($batchYear !== '') {
        $where[]  = 's.graduation_year = ?';
        $params[] = $batchYear;
    }
    $whereStr = implode(' AND ', $where);

    try {
        $st = $db->prepare(
            "SELECT s.id, s.roll_no, s.graduation_year, s.graduated_at,
                    s.father_name, s.dob, s.gender, s.phone, s.address,
                    s.parent_phone, s.parent_name, s.parent_email,
                    s.gr_no, s.cnic, s.blood_group,
                    u.name, u.user_id AS login_id, u.email, u.status
             FROM students s
             JOIN users u ON s.user_id = u.id
             WHERE $whereStr
             ORDER BY s.graduation_year DESC, u.name ASC"
        );
        $st->execute($params);
        $alumni = $st->fetchAll();
    } catch (Exception $e) {}
}

// ── Single-student profile view ───────────────────────────────────
$alumniProfile  = null;
$promoHistory   = [];
if ($viewId && $hasGradCols) {
    try {
        $pst = $db->prepare(
            "SELECT s.*, u.name, u.user_id AS login_id, u.email, u.status
             FROM students s JOIN users u ON s.user_id = u.id
             WHERE s.id = ? AND s.graduated_at IS NOT NULL"
        );
        $pst->execute([$viewId]);
        $alumniProfile = $pst->fetch();
    } catch (Exception $e) {}

    if ($alumniProfile) {
        try {
            $hst = $db->prepare(
                "SELECT * FROM student_promotion_history
                 WHERE student_id = ?
                 ORDER BY created_at ASC"
            );
            $hst->execute([$viewId]);
            $promoHistory = $hst->fetchAll();
        } catch (Exception $e) {}
    }
}

$links = getStudentAffairsLinks();
pageHead('Graduated / Alumni', 'student_affairs');
?>
<div class="portal-wrap">
<?php sidebar('student_affairs', 'alumni', $links, $user); ?>
<div class="main-area">
<?php topbar('Graduated / Alumni Students', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$hasGradCols): ?>
<!-- Migration not yet run -->
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  <strong>Setup required:</strong> The graduation columns have not been added to the database yet.
  Please ask the system administrator to run
  <code>database/migrations/promotion_history.sql</code>.
</div>
<?php elseif ($viewId && $alumniProfile): ?>

<!-- ── Profile view ───────────────────────────────────────────── -->
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="<?= url('/portal/student-affairs/alumni.php' .
      ($search ? '?q=' . urlencode($search) : '') .
      ($batchYear ? '&batch=' . urlencode($batchYear) : '')) ?>"
     class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i>Back to Alumni List
  </a>
  <h6 class="mb-0 fw-bold" style="color:var(--accent)">
    <i class="fas fa-user-graduate me-1"></i><?= h($alumniProfile['name']) ?>
  </h6>
  <span class="badge bg-primary ms-1">
    Batch <?= $alumniProfile['graduation_year'] ? h($alumniProfile['graduation_year']) : '—' ?>
  </span>
</div>

<div class="row g-3">
  <!-- Personal Info -->
  <div class="col-lg-6">
    <div class="sec-card h-100">
      <div class="sec-card-header"><i class="fas fa-id-card me-2"></i>Personal Information</div>
      <div class="sec-card-body" style="font-size:.875rem">
        <?php
        $rows = [
          ['Full Name',        $alumniProfile['name']],
          ['Roll No',          $alumniProfile['roll_no']],
          ['Login ID',         $alumniProfile['login_id']],
          ['GR No',            $alumniProfile['gr_no'] ?? null],
          ['Gender',           $alumniProfile['gender'] ? ucfirst($alumniProfile['gender']) : null],
          ['Date of Birth',    $alumniProfile['dob'] ? date('d M Y', strtotime($alumniProfile['dob'])) : null],
          ['CNIC',             $alumniProfile['cnic']  ?? null],
          ['Blood Group',      $alumniProfile['blood_group'] ?? null],
          ['Phone',            $alumniProfile['phone'] ?? null],
          ['Email',            $alumniProfile['email'] ?? null],
          ['Address',          $alumniProfile['address'] ?? null],
        ];
        foreach ($rows as [$label, $val]):
          if ($val === null || $val === '') continue;
        ?>
        <div class="d-flex border-bottom py-2 gap-2">
          <div style="min-width:140px;color:#6b7280;font-size:.82rem"><?= $label ?></div>
          <div class="fw-semibold"><?= h($val) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Parent + Graduation Info -->
  <div class="col-lg-6">
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-users me-2"></i>Parent / Guardian</div>
      <div class="sec-card-body" style="font-size:.875rem">
        <?php
        $prows = [
          ["Father's Name",  $alumniProfile['father_name']  ?? null],
          ['Parent Name',    $alumniProfile['parent_name']  ?? null],
          ['Parent Phone',   $alumniProfile['parent_phone'] ?? null],
          ['Parent Email',   $alumniProfile['parent_email'] ?? null],
        ];
        $anyP = false;
        foreach ($prows as [$label, $val]):
          if ($val === null || $val === '') continue;
          $anyP = true;
        ?>
        <div class="d-flex border-bottom py-2 gap-2">
          <div style="min-width:140px;color:#6b7280;font-size:.82rem"><?= $label ?></div>
          <div class="fw-semibold"><?= h($val) ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (!$anyP): ?>
        <p class="text-muted mb-0 py-2" style="font-size:.83rem">No parent/guardian info recorded.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-graduation-cap me-2"></i>Graduation Details</div>
      <div class="sec-card-body" style="font-size:.875rem">
        <div class="d-flex border-bottom py-2 gap-2">
          <div style="min-width:140px;color:#6b7280;font-size:.82rem">Batch / Year</div>
          <div class="fw-semibold">
            <?= $alumniProfile['graduation_year'] ? h($alumniProfile['graduation_year']) : '—' ?>
          </div>
        </div>
        <div class="d-flex border-bottom py-2 gap-2">
          <div style="min-width:140px;color:#6b7280;font-size:.82rem">Graduated On</div>
          <div class="fw-semibold">
            <?= $alumniProfile['graduated_at'] ? h(date('d M Y', strtotime($alumniProfile['graduated_at']))) : '—' ?>
          </div>
        </div>
        <div class="d-flex py-2 gap-2">
          <div style="min-width:140px;color:#6b7280;font-size:.82rem">Account Status</div>
          <div>
            <span class="badge <?= $alumniProfile['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
              <?= ucfirst($alumniProfile['status']) ?>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Promotion / Academic Journey -->
  <?php if (!empty($promoHistory)): ?>
  <div class="col-12">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-route me-2"></i>Academic Journey (Promotion History)</div>
      <div class="table-responsive">
        <table class="table table-bordered table-hover mb-0" style="font-size:.83rem">
          <thead class="table-dark">
            <tr>
              <th>Date</th>
              <th>Action</th>
              <th>From</th>
              <th>To</th>
              <th>By</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($promoHistory as $h):
              $ac = match($h['action']) {
                'promoted'    => 'bg-success',
                'demoted'     => 'bg-warning text-dark',
                'graduated'   => 'bg-primary',
                'ungraduated' => 'bg-secondary',
                default       => 'bg-secondary',
              };
            ?>
            <tr>
              <td style="white-space:nowrap"><?= h(date('d M Y', strtotime($h['created_at']))) ?></td>
              <td><span class="badge <?= $ac ?>"><?= ucfirst($h['action']) ?></span></td>
              <td><?= $h['from_class_name'] ? h($h['from_class_name']) : '<span class="text-muted">—</span>' ?></td>
              <td><?= $h['to_class_name']   ? h($h['to_class_name'])   : '<span class="text-muted">Alumni</span>' ?></td>
              <td><?= h($h['promoted_by_name']) ?></td>
              <td style="color:#6b7280"><?= $h['notes'] ? h($h['notes']) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php else: ?>

<!-- ── Alumni list view ───────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold" style="color:var(--accent)">
    <i class="fas fa-graduation-cap me-2"></i>Graduated / Alumni Students
  </h5>
  <span class="badge bg-primary" style="font-size:.82rem;padding:5px 12px">
    <?= count($alumni) ?> found
  </span>
</div>

<!-- Filters -->
<div class="sec-card mb-3">
  <div class="sec-card-body" style="padding:14px 16px">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-sm-5 col-lg-4">
        <label class="form-label fw-semibold mb-1" style="font-size:.82rem">Search</label>
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="Name, roll no, ID, father's name…"
               value="<?= h($search) ?>">
      </div>
      <div class="col-sm-3 col-lg-2">
        <label class="form-label fw-semibold mb-1" style="font-size:.82rem">Batch / Year</label>
        <select name="batch" class="form-select form-select-sm">
          <option value="">All Batches</option>
          <?php foreach ($batches as $b): ?>
          <option value="<?= h($b) ?>"<?= $batchYear === $b ? ' selected' : '' ?>>
            Batch <?= h($b) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="fas fa-search me-1"></i>Search
        </button>
        <?php if ($search !== '' || $batchYear !== ''): ?>
        <a href="<?= url('/portal/student-affairs/alumni.php') ?>" class="btn btn-sm btn-outline-secondary">
          Clear
        </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if (empty($alumni)): ?>
<div class="sec-card">
  <div class="sec-card-body text-center py-5 text-muted">
    <i class="fas fa-graduation-cap fa-3x mb-3" style="opacity:.25"></i>
    <p class="mb-1 fw-semibold">
      <?= ($search !== '' || $batchYear !== '') ? 'No alumni match your search.' : 'No graduated students on record yet.' ?>
    </p>
    <p class="mb-0" style="font-size:.83rem">
      Students are moved here when the Admin marks them as Graduated from the Class Promotion tool.
    </p>
  </div>
</div>
<?php else: ?>
<div class="sec-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-dark">
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Roll No</th>
          <th>Father's Name</th>
          <th>Phone</th>
          <th>Email</th>
          <th>Batch</th>
          <th>Graduated On</th>
          <th>Profile</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($alumni as $i => $a): ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <td class="fw-semibold"><?= h($a['name']) ?></td>
          <td><code style="font-size:.78rem"><?= h($a['roll_no']) ?></code></td>
          <td><?= ($a['father_name'] ?? '') ? h($a['father_name']) : '<span class="text-muted">—</span>' ?></td>
          <td><?= ($a['phone'] ?? '') ? h($a['phone']) : '<span class="text-muted">—</span>' ?></td>
          <td style="font-size:.8rem"><?= ($a['email'] ?? '') ? h($a['email']) : '<span class="text-muted">—</span>' ?></td>
          <td>
            <?php if ($a['graduation_year']): ?>
            <span class="badge bg-primary">Batch <?= h($a['graduation_year']) ?></span>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td style="white-space:nowrap">
            <?= h(date('d M Y', strtotime($a['graduated_at']))) ?>
          </td>
          <td>
            <a href="<?= url('/portal/student-affairs/alumni.php?view=' . $a['id']
                . ($search    ? '&q='     . urlencode($search)    : '')
                . ($batchYear ? '&batch=' . urlencode($batchYear) : '')) ?>"
               class="btn btn-sm btn-outline-primary">
              <i class="fas fa-eye me-1"></i>View
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php endif; // hasGradCols ?>

</div><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /portal-wrap -->
<?php pageFooter(); ?>

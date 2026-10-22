<?php
/**
 * Report Cards browser — Principal / Vice Principal access.
 *
 * Lets a Principal (admin) or Vice Principal (vp_main) filter the student
 * roster by wing / class / search term and open each student's existing
 * report card. The actual report card and its editable remarks still live
 * in /portal/report-card.php — this page is just the browse-and-filter UI.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('admin', 'vp_main');
if ($user['role'] === 'vp_main') requirePermission('vp_results');
$db = getDB();
$role = $user['role'];

// ── Filters ──────────────────────────────────────────────────────────────
$wingFilter  = trim((string)($_GET['wing']    ?? ''));
$classFilter = (int)($_GET['class_id'] ?? 0);
$search      = trim((string)($_GET['q']       ?? ''));

$validWings = ['main', 'montessori', 'ilc'];
if ($wingFilter !== '' && !in_array($wingFilter, $validWings, true)) $wingFilter = '';

// Build class list, filtered by wing when selected
try {
    $classWhere  = ['1=1'];
    $classParams = [];
    if ($wingFilter !== '') {
        $classWhere[]  = "COALESCE(wing,'main') = ?";
        $classParams[] = $wingFilter;
    }
    $classSql = 'SELECT id, name, COALESCE(wing,\'main\') AS wing FROM classes WHERE '
              . implode(' AND ', $classWhere) . ' ORDER BY wing, grade, section, name';
    $classSt = $db->prepare($classSql);
    $classSt->execute($classParams);
    $classes = $classSt->fetchAll();
} catch (Exception $e) { $classes = []; }

// Build student list for the active filter set
$students = [];
try {
    $where  = ['u.deleted_at IS NULL', "COALESCE(u.status,'active') = 'active'"];
    $params = [];
    if ($wingFilter !== '') {
        $where[]  = "COALESCE(c.wing,'main') = ?";
        $params[] = $wingFilter;
    }
    if ($classFilter > 0) {
        $where[]  = 's.class_id = ?';
        $params[] = $classFilter;
    }
    if ($search !== '') {
        $where[]  = '(u.name LIKE ? OR u.user_id LIKE ? OR s.roll_no LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    // Only fetch when at least one filter is set — avoids dumping the whole roster.
    if ($wingFilter !== '' || $classFilter > 0 || $search !== '') {
        $sql = "SELECT s.id, s.roll_no, u.name AS student_name, u.user_id AS gr_no,
                       c.name AS class_name, COALESCE(c.wing,'main') AS wing
                FROM students s
                JOIN users u ON u.id = s.user_id
                LEFT JOIN classes c ON c.id = s.class_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY c.wing, c.grade, c.section, u.name
                LIMIT 500";
        $st = $db->prepare($sql);
        $st->execute($params);
        $students = $st->fetchAll();
    }
} catch (Exception $e) { $students = []; }

pageHead('Report Cards', $role);
$links = ($role === 'admin') ? getAdminLinks() : getVpLinks();
?>
<div class="portal-wrap">
<?php sidebar($role, 'report-cards', $links, $user); ?>
<div class="main-area">
<?php topbar('Report Cards', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card mb-3">
  <div class="sec-head"><i class="fas fa-filter me-2"></i>Find Student Report Cards</div>
  <div class="sec-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-sm-6 col-md-3">
        <label class="form-label fw-semibold" style="font-size:.82rem">Wing / Campus</label>
        <select name="wing" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All wings</option>
          <?php foreach ($validWings as $w): ?>
          <option value="<?= h($w) ?>" <?= $wingFilter === $w ? 'selected' : '' ?>><?= h(ucfirst($w === 'main' ? 'Main Campus' : $w)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-6 col-md-4">
        <label class="form-label fw-semibold" style="font-size:.82rem">Class / Section</label>
        <select name="class_id" class="form-select form-select-sm">
          <option value="">All classes</option>
          <?php foreach ($classes as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $classFilter === (int)$c['id'] ? 'selected' : '' ?>>
            <?= h($c['name']) ?><?= $wingFilter === '' ? ' (' . h(ucfirst($c['wing'])) . ')' : '' ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-8 col-md-3">
        <label class="form-label fw-semibold" style="font-size:.82rem">Search by name / GR / roll</label>
        <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm" placeholder="e.g. Ali, 1024, 12-A">
      </div>
      <div class="col-sm-4 col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i>Find</button>
        <?php if ($wingFilter !== '' || $classFilter > 0 || $search !== ''): ?>
        <a href="report-cards.php" class="btn btn-outline-secondary btn-sm" title="Clear filters"><i class="fas fa-times"></i></a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if (empty($students) && ($wingFilter === '' && $classFilter === 0 && $search === '')): ?>
<div class="sec-card">
  <div class="sec-body text-center text-muted py-5">
    <i class="fas fa-id-card fa-3x opacity-25 mb-3 d-block"></i>
    <div class="fw-semibold">Pick a wing, class, or search to list students</div>
    <div style="font-size:.82rem;margin-top:4px">Use the filters above to browse report cards for any authorized wing or class.</div>
  </div>
</div>
<?php elseif (empty($students)): ?>
<div class="sec-card">
  <div class="sec-body text-center text-muted py-5">
    <i class="fas fa-search fa-3x opacity-25 mb-3 d-block"></i>
    <div class="fw-semibold">No students match the current filters.</div>
    <div style="font-size:.82rem;margin-top:4px">Try widening the wing filter or clearing the search term.</div>
  </div>
</div>
<?php else: ?>
<div class="sec-card">
  <div class="sec-head d-flex align-items-center justify-content-between">
    <span><i class="fas fa-id-card me-2"></i>Students <span class="badge bg-secondary ms-1" style="font-size:.68rem"><?= count($students) ?></span></span>
    <?php if (count($students) === 500): ?>
    <span class="badge bg-warning text-dark" style="font-size:.68rem">Showing first 500 — narrow filters to see more</span>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table table-hover table-sm mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr>
          <th>Student</th>
          <th>GR No</th>
          <th>Roll No</th>
          <th>Class</th>
          <th>Wing</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $s): ?>
        <tr>
          <td class="fw-semibold"><?= h($s['student_name']) ?></td>
          <td class="text-muted"><?= h($s['gr_no'] ?? '—') ?></td>
          <td class="text-muted"><?= h($s['roll_no'] ?? '—') ?></td>
          <td><?= h($s['class_name'] ?? '—') ?></td>
          <td><span class="badge bg-light text-dark border" style="font-size:.68rem"><?= h(ucfirst($s['wing'])) ?></span></td>
          <td class="text-end" style="white-space:nowrap">
            <a href="<?= url('/portal/report-card.php?student_id=' . (int)$s['id']) ?>"
               class="btn btn-sm btn-primary" title="View &amp; edit report card">
              <i class="fas fa-id-card me-1"></i>View Report Card
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="sec-card mt-3" style="border:1px solid #dbeafe;background:#eff6ff">
  <div style="padding:12px 14px;font-size:.82rem;color:#1e3a8a">
    <i class="fas fa-info-circle me-1"></i>
    <strong>Principal&nbsp;/&nbsp;Vice Principal view.</strong>
    Open a report card to view every subject, assessment and the author-separated
    teacher&nbsp;/&nbsp;Vice Principal remarks. Edit mode (available on the report card
    itself) lets authorized users amend remarks without overwriting the other author's text.
  </div>
</div>

</div><!-- /page-content --></div><!-- /main-area --></div><!-- /portal-wrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('vp_montessori');
$db   = getDB();

$search = trim($_GET['q'] ?? '');

$sql = 'SELECT u.id, u.user_id AS uid, u.name, u.email, u.status,
               t.id AS tid, t.emp_id, t.designation, t.phone, t.join_date,
               sub.name AS subject_name
        FROM users u
        LEFT JOIN teachers t ON t.user_id = u.id
        LEFT JOIN subjects sub ON sub.id = t.subject_id
        WHERE u.role = "montessori_teacher"';
$params = [];
if ($search) {
    $sql    .= ' AND (u.name LIKE ? OR t.emp_id LIKE ? OR u.user_id LIKE ?)';
    $like    = "%$search%";
    $params  = [$like, $like, $like];
}
$sql .= ' ORDER BY u.name';

$st = $db->prepare($sql);
$st->execute($params);
$teachers = $st->fetchAll();

pageHead('Montessori Teachers', 'vp_montessori');
$links = getVpMontessoriLinks();
?>
<div class="portal-wrap">
<?php sidebar('vp_montessori', 'teachers', $links, $user); ?>
<div class="main-area">
<?php topbar('Montessori Teachers', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-chalkboard-teacher me-2"></i>Montessori Teachers (<?= count($teachers) ?>)</span>
    <form method="GET" class="d-flex gap-2">
      <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm"
             placeholder="Search name / ID…" style="flex:1 1 auto;min-width:130px;max-width:210px">
      <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
      <?php if ($search): ?>
        <a href="<?= url('/portal/vp-montessori/teachers.php') ?>" class="btn btn-sm btn-outline-danger">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (empty($teachers)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2);font-size:.88rem">
    <i class="fas fa-chalkboard-teacher fa-2x mb-3 d-block" style="opacity:.2"></i>
    No montessori teachers found.
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr><th>Emp ID</th><th>Name</th><th>Subject</th><th>Designation</th><th>Phone</th><th>Join Date</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($teachers as $t): ?>
        <tr>
          <td class="fw-semibold"><?= h($t['emp_id'] ?: $t['uid']) ?></td>
          <td><?= h($t['name']) ?></td>
          <td><?= h($t['subject_name'] ?: '—') ?></td>
          <td><?= h($t['designation'] ?: '—') ?></td>
          <td><?= h($t['phone'] ?: '—') ?></td>
          <td><?= $t['join_date'] ? h(date('d M Y', strtotime($t['join_date']))) : '—' ?></td>
          <td><span class="badge <?= $t['status']==='active'?'bg-success':'bg-danger' ?>"><?= $t['status'] ?></span></td>
          <td>
            <?php if ($t['status'] === 'active'): ?>
            <form method="POST" action="<?= url('/portal/vp-montessori/view-as.php') ?>" class="d-inline">
              <input type="hidden" name="target_id" value="<?= $t['id'] ?>">
              <input type="hidden" name="return_url" value="<?= h(url('/portal/vp-montessori/teachers.php') . ($search ? '?q='.urlencode($search) : '')) ?>">
              <button class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 8px">
                <i class="fas fa-eye me-1"></i>View Portal
              </button>
            </form>
            <?php else: ?>
            <span class="text-muted" style="font-size:.78rem">Inactive</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

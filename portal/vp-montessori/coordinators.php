<?php
/**
 * Montessori Coordinators list — VP Montessori view.
 *
 * In this portal, the "Montessori Coordinator" is the users.role = 'wing_head'
 * user (see portal/admin/users.php label "Coordinator (Montessori)"). There
 * is no separate `montessori_coordinator` role — adding one would require
 * backfilling every existing coordinator account, so we reuse `wing_head`.
 *
 * This page gives the VP Montessori a dedicated screen listing every active
 * Coordinator so they appear consistently alongside the Teachers list.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('vp_montessori');
$db   = getDB();

$search = trim($_GET['q'] ?? '');

$sql = "SELECT u.id, u.user_id AS uid, u.name, u.email, u.status,
               t.id AS tid, t.emp_id, t.designation, t.phone, t.join_date,
               sub.name AS subject_name
          FROM users u
          LEFT JOIN teachers t ON t.user_id = u.id
          LEFT JOIN subjects sub ON sub.id = t.subject_id
         WHERE u.role = 'wing_head'
           AND COALESCE(u.status,'active') = 'active'";
$params = [];
if ($search) {
    $sql    .= ' AND (u.name LIKE ? OR t.emp_id LIKE ? OR u.user_id LIKE ?)';
    $like    = "%$search%";
    $params  = [$like, $like, $like];
}
$sql .= ' ORDER BY u.name';

$st = $db->prepare($sql);
$st->execute($params);
$coords = $st->fetchAll();

pageHead('Montessori Coordinators', 'vp_montessori');
$links = getVpMontessoriLinks();
?>
<div class="portal-wrap">
<?php sidebar('vp_montessori', 'coordinators', $links, $user); ?>
<div class="main-area">
<?php topbar('Montessori Coordinators', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-user-tie me-2"></i>Montessori Coordinators (<?= count($coords) ?>)</span>
    <form method="GET" class="d-flex gap-2">
      <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm"
             placeholder="Search name / ID…" style="flex:1 1 auto;min-width:130px;max-width:210px">
      <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
      <?php if ($search): ?>
        <a href="<?= url('/portal/vp-montessori/coordinators.php') ?>" class="btn btn-sm btn-outline-danger">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (empty($coords)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2);font-size:.88rem">
    <i class="fas fa-user-tie fa-2x mb-3 d-block" style="opacity:.2"></i>
    No active Montessori Coordinators found.
    <div class="mt-2" style="font-size:.78rem">
      Coordinators are users with role <code>wing_head</code>. Ask Admin to assign the role from Admin → Staff &amp; Students.
    </div>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr><th>Emp ID</th><th>Name</th><th>Subject</th><th>Designation</th><th>Phone</th><th>Join Date</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($coords as $c): ?>
        <tr>
          <td class="fw-semibold"><?= h($c['emp_id'] ?: $c['uid']) ?></td>
          <td><?= h($c['name']) ?></td>
          <td><?= h($c['subject_name'] ?: '—') ?></td>
          <td><?= h($c['designation'] ?: 'Coordinator') ?></td>
          <td><?= h($c['phone'] ?: '—') ?></td>
          <td><?= $c['join_date'] ? h(date('d M Y', strtotime($c['join_date']))) : '—' ?></td>
          <td><span class="badge <?= $c['status']==='active'?'bg-success':'bg-danger' ?>"><?= h($c['status']) ?></span></td>
          <td>
            <?php if ($c['status'] === 'active'): ?>
            <form method="POST" action="<?= url('/portal/vp-montessori/view-as.php') ?>" class="d-inline">
              <input type="hidden" name="target_id" value="<?= (int)$c['id'] ?>">
              <input type="hidden" name="return_url" value="<?= h(url('/portal/vp-montessori/coordinators.php') . ($search ? '?q='.urlencode($search) : '')) ?>">
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

<div class="sec-card mt-3" style="border:1px solid #fde68a;background:#fffbeb">
  <div style="padding:12px 14px;font-size:.82rem;color:#78350f">
    <i class="fas fa-sitemap me-1"></i>
    <strong>Montessori Wing Hierarchy.</strong>
    Vice Principal Montessori → Coordinators (role <code>wing_head</code>) → Teachers
    (role <code>montessori_teacher</code>). All three tiers are scoped to the
    Montessori wing only; Main Campus and ILC staff are not shown here.
  </div>
</div>

</div><!-- /page-content --></div><!-- /main-area --></div><!-- /portal-wrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

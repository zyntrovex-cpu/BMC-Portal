<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

$filterUser   = trim($_GET['user_id']  ?? '');
$filterAction = trim($_GET['action']   ?? '');
$filterDate   = trim($_GET['date']     ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($filterUser) { $where[] = '(u.user_id LIKE ? OR al.user_id IS NULL)'; $params[] = "%$filterUser%"; }
if ($filterAction) { $where[] = 'al.action LIKE ?'; $params[] = "%$filterAction%"; }
if ($filterDate) { $where[] = 'DATE(al.created_at) = ?'; $params[] = $filterDate; }

$whereStr = $where ? 'WHERE '.implode(' AND ', $where) : '';

$countSt = $db->prepare("SELECT COUNT(*) FROM activity_log al LEFT JOIN users u ON al.user_id = u.id $whereStr");
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$logSt = $db->prepare(
    "SELECT al.*, u.name, u.user_id AS uid, u.role
     FROM activity_log al
     LEFT JOIN users u ON al.user_id = u.id
     $whereStr
     ORDER BY al.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$logSt->execute($params);
$logs = $logSt->fetchAll();

// Teacher summary
$teacherSummarySt = $db->query(
    'SELECT u.name, u.user_id AS uid,
            MAX(CASE WHEN al.action="login" THEN al.created_at END) AS last_login,
            MAX(CASE WHEN al.action="marks_save" THEN al.created_at END) AS last_marks,
            MAX(CASE WHEN al.action="attendance_save" THEN al.created_at END) AS last_att,
            SUM(CASE WHEN al.action="marks_save" THEN 1 ELSE 0 END) AS marks_saves,
            SUM(CASE WHEN al.action="attendance_save" THEN 1 ELSE 0 END) AS att_saves
     FROM activity_log al
     JOIN users u ON al.user_id = u.id
     WHERE u.role = "teacher"
     GROUP BY u.id
     ORDER BY last_login DESC'
);
$teacherSummary = $teacherSummarySt->fetchAll();

pageHead('Activity Log', 'admin');
$links = getAdminLinks();
?>
<div class="portal-wrap">
<?php sidebar('admin', 'activity', $links, $user); ?>
<div class="main-area">
<?php topbar('Activity Log', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Teacher summary -->
<?php if (!empty($teacherSummary)): ?>
<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-chalkboard-teacher me-2"></i>Teacher Activity Summary</div>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0" style="font-size:.83rem">
      <thead class="table-light"><tr><th>Teacher</th><th>ID</th><th>Last Login</th><th>Last Marks Entry</th><th>Last Attendance</th><th>Marks Saves</th><th>Att Saves</th></tr></thead>
      <tbody>
        <?php foreach ($teacherSummary as $ts): ?>
        <tr>
          <td class="fw-semibold"><?= h($ts['name']) ?></td>
          <td><?= h($ts['uid']) ?></td>
          <td><?= $ts['last_login'] ? fDate($ts['last_login']) : '<span class="text-muted">Never</span>' ?></td>
          <td><?= $ts['last_marks'] ? fDate($ts['last_marks']) : '<span class="text-muted">—</span>' ?></td>
          <td><?= $ts['last_att'] ? fDate($ts['last_att']) : '<span class="text-muted">—</span>' ?></td>
          <td><?= $ts['marks_saves'] ?></td>
          <td><?= $ts['att_saves'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Filter -->
<form method="GET" class="sec-card mb-3 p-3 d-flex gap-2 flex-wrap align-items-end">
  <div>
    <label class="form-label fw-semibold" style="font-size:.8rem">User ID</label>
    <input type="text" name="user_id" class="form-control form-control-sm" value="<?= h($filterUser) ?>" placeholder="T001...">
  </div>
  <div>
    <label class="form-label fw-semibold" style="font-size:.8rem">Action</label>
    <input type="text" name="action" class="form-control form-control-sm" value="<?= h($filterAction) ?>" placeholder="login...">
  </div>
  <div>
    <label class="form-label fw-semibold" style="font-size:.8rem">Date</label>
    <input type="date" name="date" class="form-control form-control-sm" value="<?= h($filterDate) ?>">
  </div>
  <button type="submit" class="btn btn-sm btn-primary">Filter</button>
  <?php if ($filterUser || $filterAction || $filterDate): ?>
    <a href="<?= url('/admin/activity.php') ?>" class="btn btn-sm btn-outline-danger">Clear</a>
  <?php endif; ?>
</form>

<!-- Log table -->
<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between">
    <span><i class="fas fa-list me-2"></i>Activity Log (<?= $total ?> records)</span>
    <span style="font-size:.8rem;color:#6b7280">Page <?= $page ?> / <?= $pages ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.83rem">
      <thead class="table-light"><tr><th>Time</th><th>User</th><th>Role</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach ($logs as $l):
          $roleBadge = match($l['role'] ?? '') {'teacher'=>'success','student'=>'primary','finance'=>'warning',default=>'secondary'};
        ?>
        <tr>
          <td style="white-space:nowrap;font-size:.78rem"><?= date('d M y H:i', strtotime($l['created_at'])) ?></td>
          <td><strong><?= h($l['uid'] ?? '[deleted]') ?></strong><div style="font-size:.76rem;color:#9ca3af"><?= h($l['name'] ?? '—') ?></div></td>
          <td><span class="badge bg-<?= $roleBadge ?>"><?= $l['role'] ?? 'deleted' ?></span></td>
          <td><code style="font-size:.78rem"><?= h($l['action']) ?></code></td>
          <td style="max-width:250px;font-size:.79rem"><?= h($l['details']) ?></td>
          <td style="font-size:.76rem;color:#9ca3af"><?= h($l['ip_address']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="d-flex justify-content-center p-2 gap-1 flex-wrap">
    <?php for ($i=1;$i<=$pages;$i++): ?>
      <a href="?user_id=<?= h($filterUser) ?>&action=<?= h($filterAction) ?>&date=<?= h($filterDate) ?>&page=<?= $i ?>"
         class="btn btn-xs <?= $i===$page?'btn-primary':'btn-outline-secondary' ?>" style="font-size:.76rem;padding:2px 7px"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

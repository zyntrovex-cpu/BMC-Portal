<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

// ── Start impersonation ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['target_id'])) {
    $targetId  = (int)$_POST['target_id'];
    $returnUrl = $_POST['return_url'] ?? '';

    // Allow admin to impersonate any role except admin
    $st = $db->prepare(
        'SELECT id, user_id, name, email, role, status
         FROM users WHERE id = ? AND role != "admin" AND status = "active"'
    );
    $st->execute([$targetId]);
    $target = $st->fetch();

    if ($target) {
        $_SESSION['admin_backup']    = $_SESSION['user'];
        $_SESSION['view_as_mode']    = true;
        $_SESSION['view_as_return']  = $returnUrl ?: (BASE_URL . '/admin/view-as.php');
        $_SESSION['user']            = $target;

        $dest = match($target['role']) {
            'teacher'         => BASE_URL . '/teacher/dashboard.php',
            'ilc_vp'          => BASE_URL . '/ilc/dashboard.php',
            'student_affairs' => BASE_URL . '/student-affairs/dashboard.php',
            'finance'         => BASE_URL . '/finance/dashboard.php',
            'vp_main'         => BASE_URL . '/vp/dashboard.php',
            'wing_head'       => BASE_URL . '/wing-head/dashboard.php',
            default           => BASE_URL . '/student/dashboard.php',
        };
        header('Location: ' . $dest);
        exit;
    }
    setFlash('danger', 'User not found or cannot be impersonated.');
}

// ── Search / filter ───────────────────────────────────────────────
$search     = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? '';

$validRoles = ['student','teacher','finance','ilc_vp','student_affairs','vp_main','wing_head'];

$sql = 'SELECT u.id, u.user_id, u.name, u.role, u.email, u.status,
               c.name AS class_name
        FROM users u
        LEFT JOIN students s ON s.user_id = u.id AND u.role = "student"
        LEFT JOIN classes c  ON c.id = s.class_id
        WHERE u.role != "admin"';
$params = [];

if ($roleFilter && in_array($roleFilter, $validRoles)) {
    $sql    .= ' AND u.role = ?';
    $params[] = $roleFilter;
}
if ($search) {
    $sql    .= ' AND (u.name LIKE ? OR u.user_id LIKE ?)';
    $like    = "%$search%";
    $params[] = $like;
    $params[] = $like;
}
$sql .= ' ORDER BY FIELD(u.role,"student","teacher","finance","ilc_vp","student_affairs","vp_main","wing_head"), u.name';

$st = $db->prepare($sql);
$st->execute($params);
$viewUsers = $st->fetchAll();

$roleLabels = [
    'student'         => ['label' => 'Student',         'badge' => 'bg-primary'],
    'teacher'         => ['label' => 'Teacher',         'badge' => 'bg-success'],
    'finance'         => ['label' => 'Finance',         'badge' => 'bg-warning text-dark'],
    'ilc_vp'          => ['label' => 'ILC VP',          'badge' => 'bg-info text-dark'],
    'student_affairs' => ['label' => 'Student Affairs', 'badge' => 'bg-danger'],
    'vp_main'         => ['label' => 'VP Main',         'badge' => 'bg-dark'],
    'wing_head'       => ['label' => 'Wing Head',       'badge' => 'bg-secondary'],
];

pageHead('View As User', 'admin');
$links = getAdminLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('admin', 'viewas', $links, $user); ?>
<div class="main-area">
<?php topbar('View As User', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="alert alert-info d-flex gap-2 align-items-start" style="font-size:.86rem;border-radius:8px">
  <i class="fas fa-eye mt-1"></i>
  <div>
    <strong>Portal Preview Mode</strong> — Click <em>View Portal</em> next to any user to see exactly what they see.
    A yellow banner will appear at the top — click <strong>Exit Preview</strong> to return here.
    You can preview <em>any</em> role except Admin.
  </div>
</div>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-eye me-2"></i>All Portal Users (<?= count($viewUsers) ?>)</span>
    <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
      <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm"
             placeholder="Search name or ID…" style="width:180px">
      <select name="role" class="form-select form-select-sm" style="width:145px" onchange="this.form.submit()">
        <option value="">All Roles</option>
        <?php foreach ($roleLabels as $rv => $rl): ?>
        <option value="<?= $rv ?>" <?= $roleFilter===$rv?'selected':'' ?>><?= $rl['label'] ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
      <?php if ($search || $roleFilter): ?>
      <a href="<?= url('/admin/view-as.php') ?>" class="btn btn-sm btn-outline-danger">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (empty($viewUsers)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2)">
    <i class="fas fa-users-slash fa-2x mb-2"></i>
    <p class="mb-0">No users found.</p>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr><th>User ID</th><th>Name</th><th>Role</th><th>Class</th><th>Email</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($viewUsers as $u):
          $rl = $roleLabels[$u['role']] ?? ['label' => ucfirst($u['role']), 'badge' => 'bg-secondary'];
        ?>
        <tr>
          <td class="fw-semibold"><?= h($u['user_id']) ?></td>
          <td><?= h($u['name']) ?></td>
          <td><span class="badge <?= $rl['badge'] ?>"><?= $rl['label'] ?></span></td>
          <td><?= $u['class_name'] ? '<span class="badge bg-secondary">'.h($u['class_name']).'</span>' : '<span class="text-muted">—</span>' ?></td>
          <td><?= h($u['email'] ?: '—') ?></td>
          <td><span class="badge <?= $u['status']==='active'?'bg-success':'bg-danger' ?>"><?= $u['status'] ?></span></td>
          <td>
            <?php if ($u['status'] === 'active'): ?>
            <form method="POST" class="d-inline">
              <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="return_url" value="<?= h(url('/admin/view-as.php') . ($_SERVER['QUERY_STRING'] ? '?'.$_SERVER['QUERY_STRING'] : '')) ?>">
              <button type="submit" class="btn btn-sm btn-primary" style="font-size:.78rem;padding:3px 10px">
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

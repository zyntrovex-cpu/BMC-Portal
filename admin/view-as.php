<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

// ── Start impersonation ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['target_id'])) {
    $targetId = (int)$_POST['target_id'];

    $st = $db->prepare('SELECT id, user_id, name, email, role, status FROM users WHERE id = ? AND role IN ("student","teacher") AND status = "active"');
    $st->execute([$targetId]);
    $target = $st->fetch();

    if ($target) {
        $_SESSION['admin_backup']  = $_SESSION['user'];
        $_SESSION['view_as_mode']  = true;
        $_SESSION['user']          = $target;

        $dest = $target['role'] === 'teacher'
            ? BASE_URL . '/teacher/dashboard.php'
            : BASE_URL . '/student/dashboard.php';
        header('Location: ' . $dest);
        exit;
    }
    setFlash('danger', 'User not found or cannot be impersonated.');
}

// ── Search / filter ───────────────────────────────────────────────
$search     = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? '';

$sql = 'SELECT u.id, u.user_id, u.name, u.role, u.email, u.status,
               c.name AS class_name
        FROM users u
        LEFT JOIN students s ON s.user_id = u.id AND u.role = "student"
        LEFT JOIN classes c  ON c.id = s.class_id
        WHERE u.role IN ("student","teacher")';
$params = [];

if ($roleFilter && in_array($roleFilter, ['student','teacher'])) {
    $sql    .= ' AND u.role = ?';
    $params[] = $roleFilter;
}
if ($search) {
    $sql    .= ' AND (u.name LIKE ? OR u.user_id LIKE ?)';
    $like    = "%$search%";
    $params[] = $like;
    $params[] = $like;
}
$sql .= ' ORDER BY u.role, c.name, u.name';

$st = $db->prepare($sql);
$st->execute($params);
$viewUsers = $st->fetchAll();

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

<!-- Header info -->
<div class="alert alert-info d-flex gap-2 align-items-start" style="font-size:.86rem;border-radius:8px">
  <i class="fas fa-eye mt-1"></i>
  <div>
    <strong>Portal Preview Mode</strong> — Click <em>View Portal</em> next to any student or teacher to see exactly
    what they see when they log in. You'll see a yellow banner at the top — click <em>Exit Preview</em> to return here.
  </div>
</div>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-eye me-2"></i>Students &amp; Teachers (<?= count($viewUsers) ?>)</span>
    <!-- Search + filter -->
    <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
      <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm"
             placeholder="Search name or ID…" style="width:180px">
      <select name="role" class="form-select form-select-sm" style="width:120px" onchange="this.form.submit()">
        <option value="">All</option>
        <option value="student" <?= $roleFilter==='student'?'selected':'' ?>>Students</option>
        <option value="teacher" <?= $roleFilter==='teacher'?'selected':'' ?>>Teachers</option>
      </select>
      <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
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
        <tr>
          <th>User ID</th>
          <th>Name</th>
          <th>Role</th>
          <th>Class</th>
          <th>Email</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($viewUsers as $u): ?>
        <tr>
          <td class="fw-semibold"><?= h($u['user_id']) ?></td>
          <td><?= h($u['name']) ?></td>
          <td>
            <span class="badge <?= $u['role']==='teacher' ? 'bg-success' : 'bg-primary' ?>">
              <?= ucfirst($u['role']) ?>
            </span>
          </td>
          <td><?= $u['class_name'] ? '<span class="badge bg-secondary">'.h($u['class_name']).'</span>' : '<span class="text-muted">—</span>' ?></td>
          <td><?= h($u['email'] ?: '—') ?></td>
          <td><span class="badge <?= $u['status']==='active'?'bg-success':'bg-danger' ?>"><?= $u['status'] ?></span></td>
          <td>
            <?php if ($u['status'] === 'active'): ?>
            <form method="POST" class="d-inline">
              <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-primary" style="font-size:.78rem;padding:3px 10px">
                <i class="fas fa-eye me-1"></i>View Portal
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

</div><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /portal-wrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

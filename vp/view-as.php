<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('vp_main');
$db   = getDB();

// ── Start impersonation ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['target_id'])) {
    $targetId = (int)$_POST['target_id'];

    $st = $db->prepare(
        'SELECT id, user_id, name, email, role, status
         FROM users WHERE id = ? AND status = "active"
         AND role IN ("student","teacher","wing_head")'
    );
    $st->execute([$targetId]);
    $target = $st->fetch();

    if ($target) {
        $_SESSION['admin_backup'] = $_SESSION['user'];
        $_SESSION['view_as_mode'] = true;
        $_SESSION['user']         = $target;

        $dest = match($target['role']) {
            'teacher'   => BASE_URL . '/teacher/dashboard.php',
            'wing_head' => BASE_URL . '/wing-head/dashboard.php',
            default     => BASE_URL . '/student/dashboard.php',
        };
        header('Location: ' . $dest);
        exit;
    }
    setFlash('danger', 'User not found.');
}

// ── List all teachers, wing heads, students (Main + Montessori) ──
$search     = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? '';

$sql = 'SELECT u.id, u.user_id, u.name, u.role, u.email, u.status,
               c.name AS class_name
        FROM users u
        LEFT JOIN students s ON s.user_id = u.id AND u.role = "student"
        LEFT JOIN classes c  ON c.id = s.class_id
        WHERE u.status = "active"
          AND u.role IN ("student","teacher","wing_head")
          AND (u.role != "student" OR c.is_ilc = 0)';
$params = [];

if ($roleFilter && in_array($roleFilter, ['student','teacher','wing_head'])) {
    $sql    .= ' AND u.role = ?'; $params[] = $roleFilter;
}
if ($search) {
    $sql    .= ' AND (u.name LIKE ? OR u.user_id LIKE ?)';
    $like    = "%$search%";
    $params[] = $like; $params[] = $like;
}
$sql .= ' ORDER BY u.role, c.name, u.name';

$st = $db->prepare($sql);
$st->execute($params);
$viewUsers = $st->fetchAll();

pageHead('View As User — VP', 'vp_main');
$links = getVpLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('vp_main', 'viewas', $links, $user); ?>
<div class="main-area">
<?php topbar('View As User', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="alert alert-info d-flex gap-2" style="font-size:.86rem;border-radius:8px">
  <i class="fas fa-eye mt-1"></i>
  <div><strong>VP Portal Preview</strong> — View wing heads, teachers, or students' portals exactly as they see them. Yellow banner appears — click <em>Exit Preview</em> to return.</div>
</div>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-eye me-2"></i>Users (<?= count($viewUsers) ?>)</span>
    <form method="GET" class="d-flex gap-2 flex-wrap">
      <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm"
             placeholder="Search…" style="width:160px">
      <select name="role" class="form-select form-select-sm" style="width:140px" onchange="this.form.submit()">
        <option value="">All Roles</option>
        <option value="wing_head" <?= $roleFilter==='wing_head'?'selected':'' ?>>Wing Head</option>
        <option value="teacher"   <?= $roleFilter==='teacher'?'selected':'' ?>>Teacher</option>
        <option value="student"   <?= $roleFilter==='student'?'selected':'' ?>>Student</option>
      </select>
      <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
    </form>
  </div>

  <?php if (empty($viewUsers)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2)">No users found.</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr><th>User ID</th><th>Name</th><th>Role</th><th>Class</th><th>Email</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($viewUsers as $u): ?>
        <?php $rb = match($u['role']) {'teacher'=>'success','wing_head'=>'warning',default=>'primary'}; ?>
        <tr>
          <td class="fw-semibold"><?= h($u['user_id']) ?></td>
          <td><?= h($u['name']) ?></td>
          <td><span class="badge bg-<?= $rb ?> <?= $rb==='warning'?'text-dark':'' ?>"><?= str_replace('_',' ', ucfirst($u['role'])) ?></span></td>
          <td><?= $u['class_name'] ? '<span class="badge bg-secondary">'.h($u['class_name']).'</span>' : '<span class="text-muted">—</span>' ?></td>
          <td><?= h($u['email'] ?: '—') ?></td>
          <td>
            <form method="POST" class="d-inline">
              <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-primary" style="font-size:.78rem;padding:3px 10px">
                <i class="fas fa-eye me-1"></i>View Portal
              </button>
            </form>
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

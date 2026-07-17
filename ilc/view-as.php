<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('ilc_vp');
$db   = getDB();

// Detect whether is_ilc columns exist
$hasClassIlc   = false;
$hasTeacherIlc = false;
try { $db->query('SELECT is_ilc FROM classes  LIMIT 0'); $hasClassIlc   = true; } catch (Exception $e) {}
try { $db->query('SELECT is_ilc FROM teachers LIMIT 0'); $hasTeacherIlc = true; } catch (Exception $e) {}

// Build the ILC scope condition
if ($hasClassIlc) {
    $studentCond = '(u.role = "student" AND c.is_ilc = 1)';
    $teacherCond = $hasTeacherIlc ? '(u.role = "teacher" AND t.is_ilc = 1)' : 'u.role = "teacher"';
    $ilcWhere    = "($studentCond OR $teacherCond)";
} else {
    $ilcWhere    = '1=1'; // no filtering possible
}

// ── Start impersonation (ILC-scoped) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['target_id'])) {
    $targetId  = (int)$_POST['target_id'];
    $returnUrl = $_POST['return_url'] ?? '';

    $st = $db->prepare(
        'SELECT u.id, u.user_id, u.name, u.email, u.role, u.status
         FROM users u
         LEFT JOIN students s ON s.user_id = u.id AND u.role = "student"
         LEFT JOIN classes c  ON c.id = s.class_id
         LEFT JOIN teachers t ON t.user_id = u.id AND u.role = "teacher"
         WHERE u.id = ?
           AND u.status = "active"
           AND u.role IN ("student","teacher")
           AND ' . $ilcWhere
    );
    $st->execute([$targetId]);
    $target = $st->fetch();

    if ($target) {
        $_SESSION['admin_backup']   = $_SESSION['user'];
        $_SESSION['view_as_mode']   = true;
        $_SESSION['view_as_return'] = $returnUrl ?: (BASE_URL . '/ilc/view-as.php');
        $_SESSION['user']           = $target;

        $dest = $target['role'] === 'teacher'
            ? BASE_URL . '/teacher/dashboard.php'
            : BASE_URL . '/student/dashboard.php';
        header('Location: ' . $dest);
        exit;
    }
    setFlash('danger', 'User not found or not in ILC scope.');
}

// ── List ILC students + teachers ─────────────────────────────────
$search     = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? '';

$sql = 'SELECT u.id, u.user_id, u.name, u.role, u.email, u.status,
               c.name AS class_name
        FROM users u
        LEFT JOIN students s ON s.user_id = u.id AND u.role = "student"
        LEFT JOIN classes c  ON c.id = s.class_id
        LEFT JOIN teachers t ON t.user_id = u.id AND u.role = "teacher"
        WHERE u.status = "active"
          AND u.role IN ("student","teacher")
          AND ' . $ilcWhere;
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

$viewUsers     = [];
$queryFailed   = false;
try {
    $st = $db->prepare($sql);
    $st->execute($params);
    $viewUsers = $st->fetchAll();
} catch (PDOException $e) {
    $queryFailed = true;
}

pageHead('View As User — ILC', 'ilc_vp');
$links = getIlcLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'viewas', $links, $user); ?>
<div class="main-area">
<?php topbar('View As User', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$hasClassIlc): ?>
<div class="alert alert-warning d-flex gap-2 align-items-start" style="font-size:.86rem;border-radius:8px">
  <i class="fas fa-exclamation-triangle mt-1"></i>
  <div><strong>ILC column missing.</strong> The <code>is_ilc</code> column does not exist in <code>classes</code> yet — showing all active students &amp; teachers. Run the ILC migration to enable proper scoping.</div>
</div>
<?php endif; ?>

<div class="alert alert-info d-flex gap-2 align-items-start" style="font-size:.86rem;border-radius:8px">
  <i class="fas fa-eye mt-1"></i>
  <div><strong>ILC Portal Preview</strong> — Click <em>View Portal</em> to see a student or teacher's portal exactly as they see it. A yellow banner will appear — click <em>Exit Preview</em> to return here.<?= $hasClassIlc ? ' Only ILC students and teachers are shown.' : '' ?></div>
</div>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-eye me-2"></i>ILC Students &amp; Teachers (<?= count($viewUsers) ?>)</span>
    <form method="GET" class="d-flex gap-2 flex-wrap">
      <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm"
             placeholder="Search name / ID…" style="width:170px">
      <select name="role" class="form-select form-select-sm" style="width:120px" onchange="this.form.submit()">
        <option value="">All</option>
        <option value="student" <?= $roleFilter==='student'?'selected':'' ?>>Students</option>
        <option value="teacher" <?= $roleFilter==='teacher'?'selected':'' ?>>Teachers</option>
      </select>
      <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
    </form>
  </div>

  <?php if ($queryFailed): ?>
  <div class="p-4 text-center text-danger">
    <i class="fas fa-exclamation-circle fa-2x mb-2 d-block"></i>
    Query failed — a required column may be missing. Contact admin to run the ILC migration.
  </div>
  <?php elseif (empty($viewUsers)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2)">
    <i class="fas fa-users-slash fa-2x mb-2"></i>
    <p class="mb-0">No ILC users found. Mark classes as ILC in Admin → Classes, and teachers as ILC in Admin → Teacher Accounts.</p>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr><th>User ID</th><th>Name</th><th>Role</th><th>Class</th><th>Email</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($viewUsers as $u): ?>
        <tr>
          <td class="fw-semibold"><?= h($u['user_id']) ?></td>
          <td><?= h($u['name']) ?></td>
          <td><span class="badge <?= $u['role']==='teacher'?'bg-success':'bg-primary' ?>"><?= ucfirst($u['role']) ?></span></td>
          <td><?= $u['class_name'] ? '<span class="badge bg-secondary">'.h($u['class_name']).'</span>' : '<span class="text-muted">—</span>' ?></td>
          <td><?= h($u['email'] ?: '—') ?></td>
          <td>
            <form method="POST" class="d-inline">
              <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="return_url" value="<?= h(url('/ilc/view-as.php') . ($_SERVER['QUERY_STRING'] ? '?'.$_SERVER['QUERY_STRING'] : '')) ?>">
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

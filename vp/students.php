<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('vp_main');
$db   = getDB();

$search    = trim($_GET['q'] ?? '');
$classId   = (int)($_GET['class_id'] ?? 0);
$wingFilter = $_GET['wing'] ?? ''; // 'main' | 'montessori'

$sql = 'SELECT u.id, u.user_id, u.name, u.email, u.status,
               s.id AS student_id, s.roll_no, s.dob, s.phone,
               s.student_category, s.parent_name,
               c.name AS class_name, c.is_montessori
        FROM users u
        JOIN students s ON s.user_id = u.id
        JOIN classes c  ON c.id = s.class_id
        WHERE u.role = "student" AND c.is_ilc = 0';
$params = [];

if ($wingFilter === 'main') {
    $sql .= ' AND c.is_montessori = 0';
} elseif ($wingFilter === 'montessori') {
    $sql .= ' AND c.is_montessori = 1';
}
if ($classId) {
    $sql .= ' AND c.id = ?'; $params[] = $classId;
}
if ($search) {
    $sql    .= ' AND (u.name LIKE ? OR u.user_id LIKE ? OR s.roll_no LIKE ?)';
    $like    = "%$search%";
    $params  = array_merge($params, [$like, $like, $like]);
}
$sql .= ' ORDER BY c.is_montessori, c.name, u.name';

$st = $db->prepare($sql);
$st->execute($params);
$students = $st->fetchAll();

$classes = $db->query('SELECT * FROM classes WHERE is_ilc=0 ORDER BY is_montessori, name')->fetchAll();

pageHead('Students', 'vp_main');
$links = getVpLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('vp_main', 'students', $links, $user); ?>
<div class="main-area">
<?php topbar('Students', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-user-graduate me-2"></i>Students (<?= count($students) ?>)</span>
    <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
      <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm"
             placeholder="Search name / ID…" style="width:160px">
      <select name="class_id" class="form-select form-select-sm" style="width:130px" onchange="this.form.submit()">
        <option value="0">All classes</option>
        <?php foreach ($classes as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $classId==$c['id']?'selected':'' ?>><?= h($c['name']) ?><?= $c['is_montessori']?' (Montessori)':'' ?></option>
        <?php endforeach; ?>
      </select>
      <div class="btn-group btn-group-sm">
        <a href="?wing=" class="btn btn-outline-secondary <?= !$wingFilter?'active':'' ?>" style="font-size:.78rem">All</a>
        <a href="?wing=main" class="btn btn-outline-primary <?= $wingFilter==='main'?'active':'' ?>" style="font-size:.78rem">Main</a>
        <a href="?wing=montessori" class="btn btn-outline-warning <?= $wingFilter==='montessori'?'active':'' ?>" style="font-size:.78rem">Montessori</a>
      </div>
      <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
    </form>
  </div>

  <?php if (empty($students)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2)">No students found.</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr><th>Roll No</th><th>Name</th><th>Class</th><th>Wing</th><th>Category</th><th>Parent</th><th>Phone</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($students as $s): ?>
        <tr>
          <td class="fw-semibold"><?= h($s['roll_no']) ?></td>
          <td><?= h($s['name']) ?></td>
          <td><span class="badge bg-secondary"><?= h($s['class_name']) ?></span></td>
          <td><?= $s['is_montessori'] ? '<span class="badge bg-warning text-dark">Montessori</span>' : '<span class="badge bg-primary">Main</span>' ?></td>
          <td><?= $s['student_category'] ? '<span class="badge bg-info text-dark">'.strtoupper(h($s['student_category'])).'</span>' : '<span class="text-muted">—</span>' ?></td>
          <td><?= h($s['parent_name'] ?: '—') ?></td>
          <td><?= h($s['phone'] ?: '—') ?></td>
          <td>
            <form method="POST" action="/vp/view-as.php" class="d-inline">
              <input type="hidden" name="target_id" value="<?= $s['id'] ?>">
              <button class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 7px" title="View student portal">
                <i class="fas fa-eye"></i>
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

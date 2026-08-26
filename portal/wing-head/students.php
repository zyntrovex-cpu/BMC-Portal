<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('wing_head');
requirePermission('wh_students');
$db   = getDB();

// ── Update student category ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_category') {
    $sid = (int)$_POST['student_id'];
    $cat = in_array($_POST['category'], ['civilian','cpo','sailor','']) ? ($_POST['category'] ?: null) : null;
    $db->prepare('UPDATE students SET student_category=? WHERE id=?')->execute([$cat, $sid]);
    setFlash('success', 'Category updated.');
    redirect('/portal/wing-head/students.php' . (isset($_GET['class_id']) ? '?class_id='.(int)$_GET['class_id'] : ''));
}

$classId  = (int)($_GET['class_id'] ?? 0);
$search   = trim($_GET['q'] ?? '');
$classes  = $db->query('SELECT * FROM classes WHERE is_montessori=1 ORDER BY name')->fetchAll();

$sql = 'SELECT u.id, u.user_id, u.name, u.email, u.status,
               s.id AS student_id, s.roll_no, s.dob, s.phone,
               s.student_category, s.parent_name, s.parent_phone,
               c.name AS class_name
        FROM users u
        JOIN students s ON s.user_id = u.id
        JOIN classes c  ON c.id = s.class_id
        WHERE c.is_montessori = 1';
$params = [];
if ($classId) { $sql .= ' AND c.id = ?'; $params[] = $classId; }
if ($search) {
    $sql    .= ' AND (u.name LIKE ? OR s.roll_no LIKE ?)';
    $like    = "%$search%";
    $params[] = $like; $params[] = $like;
}
$sql .= ' ORDER BY c.name, u.name';
$st = $db->prepare($sql);
$st->execute($params);
$students = $st->fetchAll();

pageHead('Montessori Students', 'wing_head');
$links = getWingHeadLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('wing_head', 'students', $links, $user); ?>
<div class="main-area">
<?php topbar('Montessori Students', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-child me-2"></i>Montessori Students (<?= count($students) ?>)</span>
    <form method="GET" class="d-flex gap-2 flex-wrap">
      <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm"
             placeholder="Search name / roll…" style="width:160px">
      <select name="class_id" class="form-select form-select-sm" style="width:130px" onchange="this.form.submit()">
        <option value="0">All classes</option>
        <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classId==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
    </form>
  </div>

  <?php if (empty($students)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2)">No students found.</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr><th>Roll No</th><th>Name</th><th>Class</th><th>Category</th><th>Parent</th><th>Phone</th><th>Set Category</th></tr>
      </thead>
      <tbody>
        <?php foreach ($students as $s): ?>
        <tr>
          <td class="fw-semibold"><?= h($s['roll_no']) ?></td>
          <td><?= h($s['name']) ?></td>
          <td><span class="badge bg-warning text-dark"><?= h($s['class_name']) ?></span></td>
          <td>
            <?php if ($s['student_category']): ?>
            <span class="badge bg-info text-dark"><?= strtoupper(h($s['student_category'])) ?></span>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td><?= h($s['parent_name'] ?: '—') ?></td>
          <td><?= h($s['phone'] ?: '—') ?></td>
          <td>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="set_category">
              <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
              <select name="category" class="form-select form-select-sm d-inline-block" style="width:110px"
                      onchange="this.form.submit()">
                <option value="" <?= !$s['student_category']?'selected':'' ?>>Unset</option>
                <option value="civilian" <?= $s['student_category']==='civilian'?'selected':'' ?>>Civilian</option>
                <option value="cpo"      <?= $s['student_category']==='cpo'?'selected':'' ?>>CPO</option>
                <option value="sailor"   <?= $s['student_category']==='sailor'?'selected':'' ?>>Sailor</option>
              </select>
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

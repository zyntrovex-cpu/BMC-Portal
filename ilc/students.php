<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('ilc_vp');
$db   = getDB();

$catFilter = (int)($_GET['cat'] ?? 0);
$search    = trim($_GET['q'] ?? '');

$sql = 'SELECT u.id AS uid, u.user_id, u.name, u.email, u.status,
               s.id AS student_id, s.roll_no, s.dob, s.phone,
               c.name AS class_name,
               COUNT(DISTINCT sd.id) AS disability_count
        FROM users u
        JOIN students s ON s.user_id = u.id
        JOIN classes c  ON c.id = s.class_id
        LEFT JOIN student_disabilities sd ON sd.student_id = s.id
        WHERE u.role = "student" AND c.is_ilc = 1';
$params = [];

if ($search) {
    $sql    .= ' AND (u.name LIKE ? OR u.user_id LIKE ? OR s.roll_no LIKE ?)';
    $like    = "%$search%";
    $params  = array_merge($params, [$like, $like, $like]);
}
if ($catFilter) {
    $sql .= ' AND EXISTS (
        SELECT 1 FROM student_disabilities sd2
        JOIN disability_subtypes dst ON dst.id = sd2.subtype_id
        WHERE sd2.student_id = s.id AND dst.category_id = ?
    )';
    $params[] = $catFilter;
}
$sql .= ' GROUP BY u.id, s.id, c.id ORDER BY c.name, u.name';

$st = $db->prepare($sql);
$st->execute($params);
$students = $st->fetchAll();

$categories = $db->query('SELECT * FROM disability_categories ORDER BY name')->fetchAll();

pageHead('ILC Students', 'ilc_vp');
$links = getIlcLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'students', $links, $user); ?>
<div class="main-area">
<?php topbar('ILC Students', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-user-graduate me-2"></i>ILC Students (<?= count($students) ?>)</span>
    <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
      <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm"
             placeholder="Search name / ID…" style="width:170px">
      <select name="cat" class="form-select form-select-sm" style="width:190px" onchange="this.form.submit()">
        <option value="0">All disabilities</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>" <?= $catFilter==$cat['id']?'selected':'' ?>><?= h($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
      <?php if ($search || $catFilter): ?>
      <a href="<?= url('/ilc/students.php') ?>" class="btn btn-sm btn-outline-danger">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (empty($students)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2)">
    <i class="fas fa-users-slash fa-2x mb-2"></i>
    <p class="mb-0">No ILC students found<?= $search||$catFilter ? ' matching your filter' : '' ?>.</p>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr><th>Roll No</th><th>Name</th><th>Class</th><th>DOB</th><th>Phone</th><th>Disabilities</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($students as $s): ?>
        <tr>
          <td class="fw-semibold"><?= h($s['roll_no']) ?></td>
          <td><?= h($s['name']) ?></td>
          <td><span class="badge bg-secondary"><?= h($s['class_name']) ?></span></td>
          <td><?= $s['dob'] ? date('d M Y', strtotime($s['dob'])) : '—' ?></td>
          <td><?= h($s['phone'] ?: '—') ?></td>
          <td>
            <?php if ($s['disability_count']): ?>
              <span class="badge" style="background:#0891b2"><?= $s['disability_count'] ?> record<?= $s['disability_count']>1?'s':'' ?></span>
            <?php else: ?>
              <span class="text-muted" style="font-size:.78rem">None assigned</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="<?= url('/ilc/student-profile.php') ?>?id=<?= $s['student_id'] ?>" class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 8px">
              <i class="fas fa-user me-1"></i>Profile
            </a>
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

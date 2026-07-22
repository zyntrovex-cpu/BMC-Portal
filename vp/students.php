<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('vp_main');
requirePermission('vp_students');
$db   = getDB();

// Detect optional columns
$hasHouseId = false;
try { $db->query('SELECT house_id FROM students LIMIT 0'); $hasHouseId = true; } catch (Exception $e) {}

// ── POST: Assign house ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'assign_house') {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $houseId   = (int)($_POST['house_id']   ?? 0) ?: null;
    if ($studentId && $hasHouseId) {
        $db->prepare('UPDATE students SET house_id=? WHERE id=?')->execute([$houseId, $studentId]);
        logActivity($user['id'], 'house_assign', "Assigned house to student #$studentId");
        setFlash('success', 'House assignment updated.');
    }
    redirect('/vp/students.php?' . http_build_query(['q'=>$_POST['q']??'','class_id'=>$_POST['class_id']??'','wing'=>$_POST['wing']??'']));
}

$search     = trim($_GET['q']       ?? '');
$classId    = (int)($_GET['class_id'] ?? 0);
$wingFilter = $_GET['wing']         ?? '';

$houseSelect = $hasHouseId ? ', s.house_id, h.name AS house_name' : '';
$houseJoin   = $hasHouseId ? 'LEFT JOIN houses h ON h.id = s.house_id' : '';

$sql = "SELECT u.id, u.user_id, u.name, u.email, u.status,
               s.id AS student_id, s.roll_no, s.dob, s.phone,
               s.student_category, s.parent_name,
               c.name AS class_name, c.is_montessori
               $houseSelect
        FROM users u
        JOIN students s ON s.user_id = u.id
        JOIN classes c  ON c.id = s.class_id
        $houseJoin
        WHERE u.role = 'student' AND c.is_ilc = 0";
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

$students = [];
try {
    $st = $db->prepare($sql);
    $st->execute($params);
    $students = $st->fetchAll();
} catch (PDOException $e) {
    // Retry without house columns if something fails
    $st = $db->prepare("SELECT u.id, u.user_id, u.name, u.email, u.status, s.id AS student_id, s.roll_no, s.phone, s.student_category, s.parent_name, c.name AS class_name, c.is_montessori FROM users u JOIN students s ON s.user_id = u.id JOIN classes c ON c.id = s.class_id WHERE u.role='student' AND c.is_ilc=0 ORDER BY c.name, u.name");
    $st->execute();
    $students = $st->fetchAll();
}

$classes = $db->query('SELECT * FROM classes WHERE is_ilc=0 ORDER BY is_montessori, name')->fetchAll();
$houses  = [];
if ($hasHouseId) {
    try { $houses = $db->query('SELECT * FROM houses ORDER BY name')->fetchAll(); } catch (Exception $e) {}
}

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
        <option value="<?= $c['id'] ?>" <?= $classId==$c['id']?'selected':'' ?>><?= h($c['name']) ?><?= $c['is_montessori']?' (M)':'' ?></option>
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
        <tr>
          <th>Roll No</th><th>Name</th><th>Class</th><th>Wing</th><th>Category</th>
          <?php if ($hasHouseId): ?><th>House</th><?php endif; ?>
          <th>Parent</th><th>Phone</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $s): ?>
        <tr>
          <td class="fw-semibold"><?= h($s['roll_no']) ?></td>
          <td><?= h($s['name']) ?></td>
          <td><span class="badge bg-secondary"><?= h($s['class_name']) ?></span></td>
          <td><?= $s['is_montessori'] ? '<span class="badge bg-warning text-dark">Montessori</span>' : '<span class="badge bg-primary">Main</span>' ?></td>
          <td><?= $s['student_category'] ? '<span class="badge bg-info text-dark">'.strtoupper(h($s['student_category'])).'</span>' : '<span class="text-muted">—</span>' ?></td>
          <?php if ($hasHouseId): ?>
          <td>
            <?php if ($s['house_name']): ?>
              <span class="badge bg-secondary"><?= h($s['house_name']) ?></span>
            <?php else: ?>
              <span class="text-muted" style="font-size:.78rem">—</span>
            <?php endif; ?>
          </td>
          <?php endif; ?>
          <td><?= h($s['parent_name'] ?: '—') ?></td>
          <td><?= h($s['phone'] ?: '—') ?></td>
          <td class="d-flex gap-1">
            <?php if ($hasHouseId && !empty($houses)): ?>
            <button class="btn btn-xs btn-outline-warning" style="font-size:.74rem;padding:2px 7px"
                    data-bs-toggle="modal" data-bs-target="#houseModal<?= $s['student_id'] ?>"
                    title="Assign house">
              <i class="fas fa-shield-alt"></i>
            </button>
            <?php endif; ?>
            <form method="POST" action="<?= url('/vp/view-as.php') ?>" class="d-inline">
              <input type="hidden" name="target_id" value="<?= $s['id'] ?>">
              <button class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 7px" title="View portal">
                <i class="fas fa-eye"></i>
              </button>
            </form>
          </td>
        </tr>

        <?php if ($hasHouseId && !empty($houses)): ?>
        <div class="modal fade" id="houseModal<?= $s['student_id'] ?>" tabindex="-1">
          <div class="modal-dialog modal-sm">
            <div class="modal-content">
              <div class="modal-header py-2">
                <h6 class="modal-title" style="font-size:.9rem"><i class="fas fa-shield-alt me-2"></i>Assign House — <?= h($s['name']) ?></h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
              </div>
              <form method="POST">
                <input type="hidden" name="action" value="assign_house">
                <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
                <input type="hidden" name="q" value="<?= h($search) ?>">
                <input type="hidden" name="class_id" value="<?= $classId ?>">
                <input type="hidden" name="wing" value="<?= h($wingFilter) ?>">
                <div class="modal-body">
                  <select name="house_id" class="form-select">
                    <option value="">— No house —</option>
                    <?php foreach ($houses as $h): ?>
                    <option value="<?= $h['id'] ?>" <?= (($s['house_id'] ?? 0) == $h['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($h['name']) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="modal-footer py-2">
                  <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-save me-1"></i>Assign</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

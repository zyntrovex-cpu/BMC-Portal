<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('student_affairs');
requirePermission('sa_students');
$db   = getDB();

// ── POST handler ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $classId   = (int)($_POST['class_id']   ?? 0) ?: null;

    if (!$studentId) {
        setFlash('danger', 'Please select a student.');
    } else {
        $db->prepare('UPDATE students SET class_id = ? WHERE id = ?')->execute([$classId, $studentId]);
        // Get student name for log
        $nm = $db->prepare('SELECT u.name FROM students s JOIN users u ON s.user_id=u.id WHERE s.id=?');
        $nm->execute([$studentId]);
        $row = $nm->fetch();
        $sName = $row['name'] ?? "student #$studentId";
        $cName = '—';
        if ($classId) {
            $cn = $db->prepare('SELECT name FROM classes WHERE id=?');
            $cn->execute([$classId]);
            $cr = $cn->fetch();
            $cName = $cr['name'] ?? "class #$classId";
        }
        logActivity($user['id'], 'student_enroll', "Enrolled $sName into $cName");
        setFlash('success', "Enrollment saved — $sName → $cName.");
    }
    redirect('/portal/student-affairs/enroll.php');
}

// ── Filters ───────────────────────────────────────────────────────
$classFilter = (int)($_GET['class_id'] ?? 0);
$search      = trim($_GET['q'] ?? '');
$hasDeletedAt = isset(array_flip(
    $db->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN)
)['deleted_at']);

$where  = ["u.role='student'"];
if ($hasDeletedAt) $where[] = 's.deleted_at IS NULL';
$params = [];
if ($search !== '') {
    $where[]  = '(u.name LIKE ? OR s.roll_no LIKE ?)';
    $like     = "%$search%";
    $params[] = $like; $params[] = $like;
}
if ($classFilter > 0) {
    $where[]  = 's.class_id = ?';
    $params[] = $classFilter;
}
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$st = $db->prepare(
    "SELECT s.id AS student_id, s.roll_no, s.class_id,
            u.id AS uid, u.name, u.user_id AS login_id,
            c.name AS class_name
     FROM users u
     JOIN students s ON s.user_id = u.id
     LEFT JOIN classes c ON c.id = s.class_id
     $whereSQL
     ORDER BY c.name, s.roll_no
     LIMIT 150"
);
$st->execute($params);
$students = $st->fetchAll();

$classes = getAllClasses();
$unassigned = array_sum(array_map(fn($s) => is_null($s['class_id']) ? 1 : 0, $students));

pageHead('Class Enrollment — Student Affairs', 'student_affairs');
$links = getStudentAffairsLinks();
?>
<div class="portal-wrap">
<?php sidebar('student_affairs', 'enroll', $links, $user); ?>
<div class="main-area">
<?php topbar('Class Enrollment', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="row g-3 mb-3">
  <div class="col-md-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-icon" style="background:#ede9fe;color:#7c3aed"><i class="fas fa-user-graduate"></i></div>
      <div class="stat-val" style="color:#7c3aed"><?= count($students) ?></div>
      <div class="stat-lbl">Students (filtered)</div>
    </div>
  </div>
  <div class="col-md-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-user-slash"></i></div>
      <div class="stat-val" style="color:#dc2626"><?= $unassigned ?></div>
      <div class="stat-lbl">Not Enrolled</div>
    </div>
  </div>
</div>

<!-- Filter bar -->
<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-filter me-2"></i>Filter Students</div>
  <div style="padding:14px 16px">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label fw-semibold" style="font-size:.82rem">Search</label>
        <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm" placeholder="Name or roll no…">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold" style="font-size:.82rem">Filter by Current Class</label>
        <select name="class_id" class="form-select form-select-sm">
          <option value="0">All Classes</option>
          <option value="0" <?= $classFilter===0 && !isset($_GET['class_id']) ? '' : '' ?>>All</option>
          <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $classFilter===$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-sm btn-primary"><i class="fas fa-search me-1"></i>Filter</button>
        <?php if ($search || $classFilter): ?>
        <a href="<?= url('/portal/student-affairs/enroll.php') ?>" class="btn btn-sm btn-outline-danger ms-1">Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Student list with quick enroll -->
<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-chalkboard me-2"></i>Enroll Students in Classes</div>
  <?php if (empty($students)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2)">No students found.</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr>
          <th style="width:90px">Roll</th>
          <th>Name</th>
          <th style="width:160px">Current Class</th>
          <th>Assign Class</th>
          <th style="width:90px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $s): ?>
        <tr>
          <td class="fw-semibold"><?= h($s['roll_no']) ?></td>
          <td><?= h($s['name']) ?></td>
          <td>
            <?php if ($s['class_name']): ?>
              <span class="badge bg-secondary" style="font-size:.75rem"><?= h($s['class_name']) ?></span>
            <?php else: ?>
              <span class="badge bg-warning text-dark" style="font-size:.75rem">Not enrolled</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" class="d-flex gap-1 align-items-center">
              <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
              <select name="class_id" class="form-select form-select-sm" style="max-width:200px">
                <option value="">— Remove from class —</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $s['class_id']==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-xs btn-success flex-shrink-0" title="Save">
                <i class="fas fa-save"></i>
              </button>
            </form>
          </td>
          <td>
            <a href="<?= url('/portal/student-affairs/students.php') ?>?q=<?= urlencode($s['name']) ?>"
               class="btn btn-xs btn-outline-primary" title="View full profile">
              <i class="fas fa-user"></i>
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

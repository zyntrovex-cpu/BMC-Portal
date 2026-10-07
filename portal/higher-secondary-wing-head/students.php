<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('higher_secondary_wing_head');
requirePermission('wh_students');
$db   = getDB();

$gradeMin = 11; $gradeMax = 12;

$classId = (int)($_GET['class_id'] ?? 0);
$search  = trim($_GET['q'] ?? '');

$classes = [];
try {
    $cSt = $db->prepare('SELECT * FROM classes WHERE COALESCE(is_montessori,0)=0 AND COALESCE(is_ilc,0)=0 AND grade BETWEEN ? AND ? ORDER BY grade, name');
    $cSt->execute([$gradeMin,$gradeMax]);
    $classes = $cSt->fetchAll();
} catch (Exception $e) {}

$sql = "SELECT u.id, u.user_id, u.name, u.email, u.status,
               s.id AS student_id, s.roll_no, s.dob, s.phone,
               s.parent_name, s.parent_phone,
               c.name AS class_name, c.grade
        FROM users u
        JOIN students s ON s.user_id = u.id
        JOIN classes c  ON c.id = s.class_id
        WHERE COALESCE(c.is_montessori,0)=0 AND COALESCE(c.is_ilc,0)=0
          AND c.grade BETWEEN $gradeMin AND $gradeMax
          AND s.deleted_at IS NULL";
$params = [];
if ($classId) { $sql .= ' AND c.id = ?'; $params[] = $classId; }
if ($search) {
    $sql .= ' AND (u.name LIKE ? OR s.roll_no LIKE ?)';
    $like = "%$search%"; $params[] = $like; $params[] = $like;
}
$sql .= ' ORDER BY c.grade, c.name, s.roll_no';
$st = $db->prepare($sql);
$st->execute($params);
$students = $st->fetchAll();

pageHead('Students', 'higher_secondary_wing_head');
$links = getHigherSecondaryWingHeadLinks();
?>
<div class="portal-wrap">
<?php sidebar('higher_secondary_wing_head', 'students', $links, $user); ?>
<div class="main-area">
<?php topbar('Students (Classes 11–12)', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-user-graduate me-2"></i>Students — Classes 11–12 (<?= count($students) ?>)</span>
    <form method="GET" class="d-flex gap-2 flex-wrap">
      <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm"
             placeholder="Search name / roll…" style="max-width:170px">
      <select name="class_id" class="form-select form-select-sm" style="max-width:140px" onchange="this.form.submit()">
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
        <tr><th>Roll No</th><th>Name</th><th>Class</th><th>Parent</th><th>Phone</th><th>Report Card</th></tr>
      </thead>
      <tbody>
        <?php foreach ($students as $s): ?>
        <tr>
          <td class="fw-semibold"><?= h($s['roll_no']) ?></td>
          <td><?= h($s['name']) ?></td>
          <td><?= h($s['class_name']) ?></td>
          <td><?= h($s['parent_name'] ?: '—') ?></td>
          <td><?= h($s['parent_phone'] ?: $s['phone'] ?: '—') ?></td>
          <td>
            <a href="<?= url('/portal/report-card.php?student_id='.(int)$s['student_id']) ?>"
               class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 7px" target="_blank">
              <i class="fas fa-file-alt"></i>
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

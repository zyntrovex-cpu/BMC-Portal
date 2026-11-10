<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('student_affairs');
requirePermission('sa_students');
$db   = getDB();

$migrationApplied = false;
try { $db->query('SELECT deleted_at FROM students LIMIT 0'); $migrationApplied = true; } catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $migrationApplied) {
    $action    = $_POST['action']     ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);
    if ($action === 'restore' && $studentId) {
        $db->prepare('UPDATE students SET deleted_at = NULL WHERE id = ?')->execute([$studentId]);
        logActivity($user['id'], 'student_restore', "SA restored student #$studentId from X-Students");
        setFlash('success', 'Student restored to active records.');
    }
    redirect('/portal/student-affairs/x-students.php');
}

$search = trim($_GET['q'] ?? '');
$deletedStudents = [];

if ($migrationApplied) {
    $params = [];
    $cond   = 's.deleted_at IS NOT NULL';
    if ($search !== '') {
        $like    = '%' . $search . '%';
        $cond   .= ' AND (u.name LIKE ? OR u.user_id LIKE ? OR s.roll_no LIKE ?)';
        $params  = [$like, $like, $like];
    }
    $st = $db->prepare(
        "SELECT s.id AS student_id, s.deleted_at, s.roll_no,
                u.id AS uid, u.name, u.user_id AS login_id, u.email,
                c.name AS class_name,
                DATEDIFF(NOW(), s.deleted_at) AS days_deleted
         FROM students s
         JOIN users u ON s.user_id = u.id
         LEFT JOIN classes c ON c.id = s.class_id
         WHERE $cond
         ORDER BY s.deleted_at DESC
         LIMIT 300"
    );
    $st->execute($params);
    $deletedStudents = $st->fetchAll();
}

pageHead('X Students — Former Students', 'student_affairs');
$links = getStudentAffairsLinks();
?>
<div class="portal-wrap">
<?php sidebar('student_affairs', 'x-students', $links, $user); ?>
<div class="main-area">
<?php topbar('X Students — Former / Removed Students', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$migrationApplied): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  <strong>Migration not applied.</strong>
  Run <code>database/migrations/soft_delete_students.sql</code> against the database to enable this feature.
</div>
<?php else: ?>

<div class="alert alert-info py-2 mb-3" style="font-size:.83rem">
  <i class="fas fa-info-circle me-2"></i>
  These students have been removed from active records.
  Use <strong>Restore</strong> to return a student to active status.
  Permanent deletion is managed by the Administrator.
</div>

<!-- Search -->
<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-search me-2"></i>Search Former Students</div>
  <div style="padding:12px 16px">
    <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
      <input type="text" name="q" value="<?= h($search) ?>"
             class="form-control form-control-sm" style="max-width:300px"
             placeholder="Name, GR / Login ID, Roll No…" autocomplete="off">
      <button type="submit" class="btn btn-sm btn-primary">
        <i class="fas fa-search me-1"></i>Search
      </button>
      <?php if ($search): ?>
      <a href="?" class="btn btn-sm btn-outline-secondary">Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="sec-card">
  <div class="sec-card-header d-flex align-items-center gap-2">
    <i class="fas fa-user-times me-1 text-danger"></i>Former / Removed Students
    <span class="badge bg-secondary ms-1"><?= count($deletedStudents) ?></span>
    <?php if ($search): ?>
    <span class="badge bg-info ms-1" style="font-size:.72rem">Filtered: "<?= h($search) ?>"</span>
    <?php endif; ?>
  </div>

  <?php if (empty($deletedStudents)): ?>
  <div style="padding:48px;text-align:center;color:var(--t2);font-size:.85rem">
    <i class="fas fa-user-check fa-2x mb-2 d-block opacity-25"></i>
    <?= $search ? 'No former students matched your search.' : 'No removed students on record.' ?>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.83rem">
      <thead class="table-light">
        <tr>
          <th>Login / GR ID</th>
          <th>Roll No</th>
          <th>Name</th>
          <th>Last Class</th>
          <th>Email</th>
          <th>Removed On</th>
          <th style="width:90px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($deletedStudents as $s): ?>
        <tr>
          <td class="fw-semibold"><?= h($s['login_id'] ?: '—') ?></td>
          <td><?= h($s['roll_no'] ?: '—') ?></td>
          <td><?= h($s['name']) ?></td>
          <td><?= $s['class_name'] ? h($s['class_name']) : '<span class="text-muted">—</span>' ?></td>
          <td><?= $s['email'] ? h($s['email']) : '<span class="text-muted">—</span>' ?></td>
          <td style="white-space:nowrap">
            <?= fDate($s['deleted_at']) ?>
            <span class="text-muted ms-1" style="font-size:.76rem">(<?= (int)$s['days_deleted'] ?>d)</span>
          </td>
          <td>
            <form method="POST" class="d-inline"
                  onsubmit="return confirm('Restore <?= h(addslashes($s['name'])) ?> to active students?')">
              <input type="hidden" name="action" value="restore">
              <input type="hidden" name="student_id" value="<?= (int)$s['student_id'] ?>">
              <button class="btn btn-xs btn-success" title="Restore">
                <i class="fas fa-undo me-1"></i>Restore
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

<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

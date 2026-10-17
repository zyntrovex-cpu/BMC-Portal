<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

// ── Check migration applied ───────────────────────────────────────
$cols = array_flip($db->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN));
$migrationApplied = isset($cols['deleted_at']);

// ── POST handlers ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $migrationApplied) {
    $action    = $_POST['action']     ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);

    if ($action === 'restore' && $studentId) {
        $db->prepare('UPDATE students SET deleted_at = NULL WHERE id = ?')->execute([$studentId]);
        logActivity($user['id'], 'student_restore', "Restored student #$studentId from recycle bin");
        setFlash('success', 'Student restored successfully.');
    }

    if ($action === 'delete_permanent' && $studentId) {
        // Get user_id first for cascade
        $st = $db->prepare('SELECT user_id FROM students WHERE id = ?');
        $st->execute([$studentId]);
        $row = $st->fetch();
        if ($row) {
            $uid = $row['user_id'];
            $db->prepare('DELETE FROM students WHERE id = ?')->execute([$studentId]);
            $db->prepare('DELETE FROM users WHERE id = ? AND role = "student"')->execute([$uid]);
            logActivity($user['id'], 'student_perm_delete', "Permanently deleted student #$studentId (user #$uid)");
            setFlash('success', 'Student permanently deleted.');
        }
    }

    redirect('/portal/admin/recycle-bin.php');
}

// ── Fetch soft-deleted students ───────────────────────────────────
$deletedStudents = [];
if ($migrationApplied) {
    $st = $db->query(
        'SELECT s.id AS student_id, s.deleted_at, s.roll_no, s.class_id,
                u.id AS uid, u.name, u.user_id AS login_id, u.email, u.status,
                c.name AS class_name,
                DATEDIFF(NOW(), s.deleted_at) AS days_deleted
         FROM students s
         JOIN users u ON s.user_id = u.id
         LEFT JOIN classes c ON c.id = s.class_id
         WHERE s.deleted_at IS NOT NULL
         ORDER BY s.deleted_at DESC'
    );
    $deletedStudents = $st->fetchAll();
}

pageHead('Recycle Bin', 'admin');
$links = getAdminLinks();
?>
<div class="portal-wrap">
<?php sidebar('admin', 'recycle-bin', $links, $user); ?>
<div class="main-area">
<?php topbar('Recycle Bin — Deleted Students', $user); ?>
<div class="page-content" style="padding:20px 22px 32px">
<?= flashHtml() ?>

<?php if (!$migrationApplied): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  <strong>Migration not applied yet.</strong>
  Run <code>database/migrations/soft_delete_students.sql</code> against your database to enable the recycle bin.
  <br><small>⚠️ Back up your database first!</small>
</div>
<?php else: ?>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="<?= url('/portal/admin/users.php') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i>Back to Users
  </a>
  <span class="text-muted" style="font-size:.84rem">
    <?= count($deletedStudents) ?> deleted student<?= count($deletedStudents) !== 1 ? 's' : '' ?> in bin
  </span>
</div>

<?php if (empty($deletedStudents)): ?>
<div class="sec-card">
  <div style="padding:48px;text-align:center;color:var(--t2)">
    <i class="fas fa-trash fa-2x mb-2 d-block opacity-25"></i>
    <p class="mb-0">The recycle bin is empty.</p>
  </div>
</div>
<?php else: ?>
<div class="sec-card">
  <div class="sec-card-header d-flex align-items-center gap-2">
    <i class="fas fa-trash-alt me-1 text-danger"></i>Deleted Students
    <span class="badge bg-danger ms-1"><?= count($deletedStudents) ?></span>
    <span class="ms-auto text-muted" style="font-size:.76rem;font-weight:400">
      Items older than 30 days are flagged for permanent deletion
    </span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.83rem">
      <thead class="table-light">
        <tr>
          <th>Roll / ID</th>
          <th>Name</th>
          <th>Class</th>
          <th>Email</th>
          <th>Deleted</th>
          <th>Age</th>
          <th style="width:160px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($deletedStudents as $s):
          $old = $s['days_deleted'] >= 30;
        ?>
        <tr class="<?= $old ? 'table-danger' : '' ?>">
          <td class="fw-semibold"><?= h($s['roll_no'] ?: $s['login_id']) ?></td>
          <td>
            <?= h($s['name']) ?>
            <?php if ($old): ?>
              <span class="badge bg-danger ms-1" style="font-size:.68rem">30+ days</span>
            <?php endif; ?>
          </td>
          <td><?= $s['class_name'] ? h($s['class_name']) : '<span class="text-muted">—</span>' ?></td>
          <td><?= $s['email'] ? h($s['email']) : '<span class="text-muted">—</span>' ?></td>
          <td style="white-space:nowrap"><?= fDate($s['deleted_at']) ?></td>
          <td>
            <?= $s['days_deleted'] ?> day<?= $s['days_deleted'] !== '1' ? 's' : '' ?>
          </td>
          <td class="d-flex gap-1">
            <!-- Restore -->
            <form method="POST" class="d-inline"
                  onsubmit="return confirm('Restore <?= h(addslashes($s['name'])) ?>?')">
              <input type="hidden" name="action" value="restore">
              <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
              <button class="btn btn-xs btn-success" title="Restore">
                <i class="fas fa-undo me-1"></i>Restore
              </button>
            </form>
            <!-- Permanently Delete -->
            <form method="POST" class="d-inline"
                  onsubmit="return confirm('PERMANENTLY delete <?= h(addslashes($s['name'])) ?>? This CANNOT be undone.')">
              <input type="hidden" name="action" value="delete_permanent">
              <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
              <button class="btn btn-xs btn-danger" title="Delete Permanently">
                <i class="fas fa-times me-1"></i>Delete
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

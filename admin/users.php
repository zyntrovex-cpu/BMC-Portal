<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $name     = trim($_POST['name']     ?? '');
        $userId   = trim($_POST['user_id']  ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');
        $role     = $_POST['role']    ?? '';
        $status   = $_POST['status']  ?? 'active';

        if ($name && $userId && $password && in_array($role, ['student','teacher','admin','finance'])) {
            $check = $db->prepare('SELECT id FROM users WHERE user_id = ?');
            $check->execute([$userId]);
            if ($check->fetch()) {
                setFlash('danger', 'User ID already exists.');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $db->prepare('INSERT INTO users (user_id,name,email,password,role,status) VALUES (?,?,?,?,?,?)')
                   ->execute([$userId, $name, $email ?: null, $hash, $role, $status]);
                $newId = (int)$db->lastInsertId();
                if ($role === 'student') {
                    $db->prepare('INSERT INTO students (user_id, roll_no, class_id, admission_date) VALUES (?,?,?,CURDATE())')
                       ->execute([$newId, $userId, $_POST['class_id'] ?: null]);
                } elseif ($role === 'teacher') {
                    $phone    = trim($_POST['phone'] ?? '');
                    $joinDate = $_POST['join_date'] ?? date('Y-m-d');
                    $db->prepare('INSERT INTO teachers (user_id, emp_id, subject_id, qualification, phone, join_date) VALUES (?,?,?,?,?,?)')
                       ->execute([$newId, $userId, $_POST['subject_id'] ?: null, trim($_POST['qualification'] ?? ''), $phone ?: null, $joinDate]);
                }
                logActivity($user['id'], 'user_create', "Created $userId ($role)");
                setFlash('success', "User $userId created.");
            }
        } else {
            setFlash('danger', 'All required fields missing.');
        }
    }

    if ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id = ?")->execute([$id]);
        setFlash('success', 'Status updated.');
    }

    if ($action === 'delete_user') {
        $id = (int)$_POST['id'];
        if ($id !== $user['id']) {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            logActivity($user['id'], 'user_delete', "Deleted user #$id");
            setFlash('success', 'User deleted.');
        }
    }

    if ($action === 'approve_request') {
        $reqId = (int)$_POST['request_id'];
        $req = $db->prepare('SELECT * FROM profile_change_requests WHERE id = ?');
        $req->execute([$reqId]);
        $r = $req->fetch();
        if ($r && in_array($r['field'], ['phone','address','parent_name','parent_phone'])) {
            $db->prepare("UPDATE students SET {$r['field']} = ? WHERE id = ?")->execute([$r['new_value'], $r['student_id']]);
            $db->prepare("UPDATE profile_change_requests SET status='approved' WHERE id = ?")->execute([$reqId]);
            setFlash('success','Profile change approved.');
        }
    }

    if ($action === 'reject_request') {
        $reqId = (int)$_POST['request_id'];
        $db->prepare("UPDATE profile_change_requests SET status='rejected' WHERE id = ?")->execute([$reqId]);
        setFlash('success','Request rejected.');
    }

    if ($action === 'reset_password') {
        $id = (int)$_POST['id'];
        $newPass = trim($_POST['new_password'] ?? '');
        if ($newPass) {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $id]);
            setFlash('success','Password reset.');
        }
    }

    redirect('/admin/users.php' . (isset($_POST['role_filter']) ? '?role='.$_POST['role_filter'] : ''));
}

$roleFilter = $_GET['role'] ?? '';
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 20;
$offset     = ($page - 1) * $perPage;

$where  = $roleFilter ? 'WHERE role = ?' : '';
$params = $roleFilter ? [$roleFilter] : [];

$countSt = $db->prepare("SELECT COUNT(*) FROM users $where");
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();
$pages = (int)ceil($total / $perPage);

$usersSt = $db->prepare("SELECT * FROM users $where ORDER BY role, name LIMIT $perPage OFFSET $offset");
$usersSt->execute($params);
$users = $usersSt->fetchAll();

// Pending profile requests
$pendingSt = $db->prepare(
    'SELECT pcr.*, u.name AS student_name, u.user_id AS student_roll
     FROM profile_change_requests pcr
     JOIN students s ON pcr.student_id = s.id
     JOIN users u ON s.user_id = u.id
     WHERE pcr.status = "pending"
     ORDER BY pcr.created_at DESC'
);
$pendingSt->execute();
$pendingRequests = $pendingSt->fetchAll();

$classes  = getAllClasses();
$subjects = getAllSubjects();

pageHead('Users', 'admin');
$links = getAdminLinks();
?>
<div class="portal-wrap">
<?php sidebar('admin', 'users', $links, $user); ?>
<div class="main-area">
<?php topbar('User Management', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Pending profile requests -->
<?php if (!empty($pendingRequests)): ?>
<div class="sec-card mb-3 border-warning">
  <div class="sec-card-header text-warning"><i class="fas fa-clock me-2"></i>Pending Profile Change Requests (<?= count($pendingRequests) ?>)</div>
  <div class="table-responsive">
    <table class="table table-sm mb-0" style="font-size:.84rem">
      <thead class="table-light"><tr><th>Student</th><th>Field</th><th>Old Value</th><th>New Value</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($pendingRequests as $r): ?>
        <tr>
          <td><?= h($r['student_name']) ?> <span class="text-muted">(<?= h($r['student_roll']) ?>)</span></td>
          <td><?= h(ucwords(str_replace('_',' ',$r['field']))) ?></td>
          <td><?= h($r['old_value'] ?: '—') ?></td>
          <td class="fw-semibold"><?= h($r['new_value']) ?></td>
          <td><?= fDate($r['created_at']) ?></td>
          <td>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="approve_request">
              <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
              <button class="btn btn-xs btn-success" style="font-size:.75rem;padding:2px 8px">Approve</button>
            </form>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="reject_request">
              <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
              <button class="btn btn-xs btn-danger" style="font-size:.75rem;padding:2px 8px">Reject</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Add user -->
<div class="sec-card mb-3">
  <div class="sec-card-header" data-bs-toggle="collapse" data-bs-target="#addUserForm" style="cursor:pointer">
    <i class="fas fa-user-plus me-2"></i>Add New User <i class="fas fa-chevron-down ms-auto"></i>
  </div>
  <div id="addUserForm" class="collapse">
    <div style="padding:16px">
      <form method="POST" id="addUserFormEl">
        <input type="hidden" name="action" value="add_user">
        <div class="row g-2">
          <div class="col-md-3"><label class="form-label fw-semibold" style="font-size:.82rem">Full Name*</label><input type="text" name="name" class="form-control form-control-sm" required></div>
          <div class="col-md-2"><label class="form-label fw-semibold" style="font-size:.82rem">User ID*</label><input type="text" name="user_id" class="form-control form-control-sm" required></div>
          <div class="col-md-2"><label class="form-label fw-semibold" style="font-size:.82rem">Role*</label>
            <select name="role" id="roleSelect" class="form-select form-select-sm" required onchange="toggleRoleFields(this.value)">
              <option value="">Select</option>
              <option value="student">Student</option>
              <option value="teacher">Teacher</option>
              <option value="admin">Admin</option>
              <option value="finance">Finance</option>
            </select>
          </div>
          <div class="col-md-3"><label class="form-label fw-semibold" style="font-size:.82rem">Email</label><input type="email" name="email" class="form-control form-control-sm"></div>
          <div class="col-md-2"><label class="form-label fw-semibold" style="font-size:.82rem">Password*</label><input type="password" name="password" class="form-control form-control-sm" required></div>
          <div id="studentFields" class="col-md-3 d-none">
            <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
            <select name="class_id" class="form-select form-select-sm">
              <option value="">Select class</option>
              <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div id="teacherFields" class="col-md-3 d-none">
            <label class="form-label fw-semibold" style="font-size:.82rem">Subject</label>
            <select name="subject_id" class="form-select form-select-sm">
              <option value="">Select subject</option>
              <?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div id="qualFields" class="col-md-3 d-none">
            <label class="form-label fw-semibold" style="font-size:.82rem">Qualification</label>
            <input type="text" name="qualification" class="form-control form-control-sm">
          </div>
          <div id="teacherPhoneField" class="col-md-2 d-none">
            <label class="form-label fw-semibold" style="font-size:.82rem">Phone</label>
            <input type="tel" name="phone" class="form-control form-control-sm" placeholder="+92...">
          </div>
          <div id="teacherJoinDateField" class="col-md-2 d-none">
            <label class="form-label fw-semibold" style="font-size:.82rem">Join Date</label>
            <input type="date" name="join_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-sm btn-success w-100">Create User</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Users table -->
<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-users me-2"></i>All Users (<?= $total ?>)</span>
    <div class="d-flex gap-1">
      <?php foreach (['','student','teacher','admin','finance'] as $r): ?>
        <a href="?role=<?= $r ?>" class="btn btn-xs <?= $roleFilter===$r?'btn-primary':'btn-outline-secondary' ?>" style="font-size:.75rem;padding:2px 8px"><?= $r ?: 'All' ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Role</th><th>Email</th><th>Status</th><th>Last Login</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($users as $u):
          $roleBadge = match($u['role']) {'student'=>'primary','teacher'=>'success','admin'=>'purple','finance'=>'warning',default=>'secondary'};
        ?>
        <tr>
          <td class="fw-semibold"><?= h($u['user_id']) ?></td>
          <td><?= h($u['name']) ?></td>
          <td><span class="badge bg-<?= $roleBadge === 'purple' ? 'secondary' : $roleBadge ?>" style="<?= $roleBadge==='purple'?'background:#7c3aed!important':'' ?>"><?= $u['role'] ?></span></td>
          <td><?= h($u['email'] ?: '—') ?></td>
          <td><span class="badge <?= $u['status']==='active'?'bg-success':'bg-danger' ?>"><?= $u['status'] ?></span></td>
          <td style="font-size:.78rem"><?= $u['last_login'] ? fDate($u['last_login']) : '—' ?></td>
          <td>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn-xs btn-outline-<?= $u['status']==='active'?'warning':'success' ?>" style="font-size:.74rem;padding:2px 7px"><?= $u['status']==='active'?'Deactivate':'Activate' ?></button>
            </form>
            <?php if ($u['id'] !== $user['id']): ?>
            <button class="btn btn-xs btn-outline-info" style="font-size:.74rem;padding:2px 7px" data-bs-toggle="modal" data-bs-target="#resetModal" data-uid="<?= $u['id'] ?>" data-uname="<?= h($u['name']) ?>">Reset Pwd</button>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete user?')">
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn-xs btn-outline-danger" style="font-size:.74rem;padding:2px 7px">Del</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="d-flex justify-content-center p-2 gap-1">
    <?php for ($i=1;$i<=$pages;$i++): ?>
      <a href="?role=<?= $roleFilter ?>&page=<?= $i ?>" class="btn btn-xs <?= $i===$page?'btn-primary':'btn-outline-secondary' ?>" style="font-size:.78rem;padding:2px 8px"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title">Reset Password</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="id" id="resetUserId">
        <div class="modal-body">
          <p style="font-size:.84rem">Reset password for <strong id="resetUserName"></strong></p>
          <input type="password" name="new_password" class="form-control form-control-sm" placeholder="New password" required>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-sm btn-danger">Reset</button></div>
      </form>
    </div>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleRoleFields(role) {
    document.getElementById('studentFields').classList.toggle('d-none', role !== 'student');
    document.getElementById('teacherFields').classList.toggle('d-none', role !== 'teacher');
    document.getElementById('qualFields').classList.toggle('d-none', role !== 'teacher');
    document.getElementById('teacherPhoneField').classList.toggle('d-none', role !== 'teacher');
    document.getElementById('teacherJoinDateField').classList.toggle('d-none', role !== 'teacher');
}
document.getElementById('resetModal').addEventListener('show.bs.modal', e => {
    document.getElementById('resetUserId').value  = e.relatedTarget.dataset.uid;
    document.getElementById('resetUserName').textContent = e.relatedTarget.dataset.uname;
});
</script>
</body></html>

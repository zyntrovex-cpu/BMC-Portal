<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_teacher') {
        $name     = trim($_POST['name']    ?? '');
        $empId    = trim($_POST['emp_id']  ?? '');
        $email    = trim($_POST['email']   ?? '');
        $password = trim($_POST['password'] ?? '');
        $subjectId = (int)$_POST['subject_id'];
        $qual      = trim($_POST['qualification'] ?? '');

        if ($name && $empId && $password) {
            $check = $db->prepare('SELECT id FROM users WHERE user_id = ?');
            $check->execute([$empId]);
            if ($check->fetch()) {
                setFlash('danger', 'Employee ID already exists.');
            } else {
                $phone    = trim($_POST['phone'] ?? '');
                $joinDate = $_POST['join_date'] ?? date('Y-m-d');
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $db->prepare('INSERT INTO users (user_id, name, email, password, role, status) VALUES (?,?,?,?,?,?)')
                   ->execute([$empId, $name, $email ?: null, $hash, 'teacher', 'active']);
                $newId = (int)$db->lastInsertId();
                $db->prepare('INSERT INTO teachers (user_id, emp_id, subject_id, qualification, phone, join_date) VALUES (?,?,?,?,?,?)')
                   ->execute([$newId, $empId, $subjectId ?: null, $qual, $phone ?: null, $joinDate]);
                logActivity($user['id'], 'teacher_create', "Created teacher $empId");
                setFlash('success', "Teacher $name created.");
            }
        } else {
            setFlash('danger', 'Name, Employee ID and password are required.');
        }
    }

    if ($action === 'update_teacher') {
        $id    = (int)$_POST['teacher_id'];
        $phone = trim($_POST['phone'] ?? '');
        $qual  = trim($_POST['qualification'] ?? '');
        $subjectId = (int)$_POST['subject_id'];
        $db->prepare('UPDATE teachers SET phone=?, qualification=?, subject_id=? WHERE id=?')->execute([$phone, $qual, $subjectId ?: null, $id]);
        setFlash('success', 'Teacher updated.');
    }

    if ($action === 'toggle_status') {
        $id = (int)$_POST['user_id'];
        $db->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
        setFlash('success', 'Status updated.');
    }

    redirect('/admin/teachers.php');
}

// Get all teachers with details
$teachersSt = $db->query(
    'SELECT t.*, u.name, u.email, u.user_id AS uid, u.status, u.last_login,
            u.id AS users_id, sb.name AS subject_name, sb.code AS subject_code,
            (SELECT COUNT(DISTINCT cs.class_id) FROM class_subjects cs WHERE cs.teacher_id = t.id) AS class_count
     FROM teachers t
     JOIN users u ON t.user_id = u.id
     LEFT JOIN subjects sb ON t.subject_id = sb.id
     ORDER BY u.name'
);
$teachers = $teachersSt->fetchAll();
$subjects = getAllSubjects();

pageHead('Teachers', 'admin');
$links = [
    ['href'=>'/admin/dashboard.php','icon'=>'<i class="fas fa-home"></i>','label'=>'Dashboard','key'=>'dashboard'],
    ['href'=>'/admin/users.php','icon'=>'<i class="fas fa-users"></i>','label'=>'User Management','key'=>'users'],
    ['href'=>'/admin/classes.php','icon'=>'<i class="fas fa-chalkboard"></i>','label'=>'Classes & Subjects','key'=>'classes'],
    ['href'=>'/admin/teachers.php','icon'=>'<i class="fas fa-chalkboard-teacher"></i>','label'=>'Teacher Accounts','key'=>'teachers'],
    ['href'=>'/admin/notices.php','icon'=>'<i class="fas fa-bell"></i>','label'=>'Notice Board','key'=>'notices'],
    ['href'=>'/admin/timetable.php','icon'=>'<i class="fas fa-table"></i>','label'=>'Timetable','key'=>'timetable'],
    ['href'=>'/admin/results.php','icon'=>'<i class="fas fa-chart-bar"></i>','label'=>'Results','key'=>'results'],
    ['href'=>'/admin/activity.php','icon'=>'<i class="fas fa-history"></i>','label'=>'Activity Log','key'=>'activity'],
];
?>
<div class="portal-wrap">
<?php sidebar('admin', 'teachers', $links, $user); ?>
<div class="main-area">
<?php topbar('Teacher Management', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Add Teacher -->
<div class="sec-card mb-3">
  <div class="sec-card-header" data-bs-toggle="collapse" data-bs-target="#addTeacherForm" style="cursor:pointer">
    <i class="fas fa-user-plus me-2"></i>Add New Teacher <i class="fas fa-chevron-down ms-auto"></i>
  </div>
  <div id="addTeacherForm" class="collapse">
    <div style="padding:16px">
      <form method="POST">
        <input type="hidden" name="action" value="add_teacher">
        <div class="row g-2">
          <div class="col-md-3"><label class="form-label fw-semibold" style="font-size:.82rem">Full Name*</label><input type="text" name="name" class="form-control form-control-sm" required></div>
          <div class="col-md-2"><label class="form-label fw-semibold" style="font-size:.82rem">Employee ID*</label><input type="text" name="emp_id" class="form-control form-control-sm" placeholder="T007" required></div>
          <div class="col-md-3"><label class="form-label fw-semibold" style="font-size:.82rem">Email</label><input type="email" name="email" class="form-control form-control-sm"></div>
          <div class="col-md-2"><label class="form-label fw-semibold" style="font-size:.82rem">Password*</label><input type="password" name="password" class="form-control form-control-sm" required></div>
          <div class="col-md-2"><label class="form-label fw-semibold" style="font-size:.82rem">Subject</label>
            <select name="subject_id" class="form-select form-select-sm">
              <option value="">None</option>
              <?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4"><label class="form-label fw-semibold" style="font-size:.82rem">Qualification</label><input type="text" name="qualification" class="form-control form-control-sm" placeholder="e.g. M.Phil Chemistry"></div>
          <div class="col-md-2"><label class="form-label fw-semibold" style="font-size:.82rem">Phone</label><input type="tel" name="phone" class="form-control form-control-sm" placeholder="+92..."></div>
          <div class="col-md-2"><label class="form-label fw-semibold" style="font-size:.82rem">Join Date</label><input type="date" name="join_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
          <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-sm btn-success w-100">Add Teacher</button></div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Teachers table -->
<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-chalkboard-teacher me-2"></i>All Teachers (<?= count($teachers) ?>)</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light"><tr><th>Name</th><th>ID</th><th>Subject</th><th>Qualification</th><th>Classes</th><th>Status</th><th>Last Login</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($teachers as $t): ?>
        <tr>
          <td class="fw-semibold"><?= h($t['name']) ?></td>
          <td><?= h($t['uid']) ?></td>
          <td><?= h($t['subject_name'] ?? '—') ?></td>
          <td style="font-size:.8rem"><?= h($t['qualification'] ?: '—') ?></td>
          <td><?= $t['class_count'] ?></td>
          <td><span class="badge <?= $t['status']==='active'?'bg-success':'bg-danger' ?>"><?= $t['status'] ?></span></td>
          <td style="font-size:.78rem"><?= $t['last_login'] ? fDate($t['last_login']) : '—' ?></td>
          <td>
            <button class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 7px"
                    data-bs-toggle="modal" data-bs-target="#editTeacherModal"
                    data-id="<?= $t['id'] ?>" data-phone="<?= h($t['phone'] ?? '') ?>"
                    data-qual="<?= h($t['qualification'] ?? '') ?>" data-subject="<?= $t['subject_id'] ?>">Edit</button>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="user_id" value="<?= $t['users_id'] ?>">
              <button class="btn btn-xs btn-outline-<?= $t['status']==='active'?'warning':'success' ?>" style="font-size:.74rem;padding:2px 7px"><?= $t['status']==='active'?'Deactivate':'Activate' ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Edit Teacher Modal -->
<div class="modal fade" id="editTeacherModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title">Edit Teacher</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <input type="hidden" name="action" value="update_teacher">
        <input type="hidden" name="teacher_id" id="editTeacherId">
        <div class="modal-body">
          <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.85rem">Phone</label><input type="tel" name="phone" id="editPhone" class="form-control"></div>
          <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.85rem">Qualification</label><input type="text" name="qualification" id="editQual" class="form-control"></div>
          <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.85rem">Subject</label>
            <select name="subject_id" id="editSubjectId" class="form-select">
              <option value="">None</option>
              <?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-sm btn-success">Save</button></div>
      </form>
    </div>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('editTeacherModal').addEventListener('show.bs.modal', e => {
    const btn = e.relatedTarget;
    document.getElementById('editTeacherId').value = btn.dataset.id;
    document.getElementById('editPhone').value     = btn.dataset.phone;
    document.getElementById('editQual').value      = btn.dataset.qual;
    document.getElementById('editSubjectId').value = btn.dataset.subject;
});
</script>
</body></html>

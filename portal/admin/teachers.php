<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

// Detect whether wing column exists on teachers table (added by wing-migration.sql)
$hasTeacherWing = false;
try { $db->query('SELECT wing FROM teachers LIMIT 0'); $hasTeacherWing = true; } catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $allowedWings = ['main', 'montessori', 'ilc'];

    if ($action === 'add_teacher') {
        $name     = trim($_POST['name']    ?? '');
        $empId    = trim($_POST['emp_id']  ?? '');
        $email    = trim($_POST['email']   ?? '');
        $password = trim($_POST['password'] ?? '');
        $subjectId = (int)$_POST['subject_id'];
        $qual      = trim($_POST['qualification'] ?? '');
        $wing      = in_array($_POST['wing'] ?? '', $allowedWings) ? $_POST['wing'] : 'main';

        if ($name && $empId && $password) {
            $check = $db->prepare('SELECT id FROM users WHERE user_id = ?');
            $check->execute([$empId]);
            if ($check->fetch()) {
                setFlash('danger', 'Employee ID already exists.');
            } else {
                $phone    = trim($_POST['phone'] ?? '');
                $joinDate = $_POST['join_date'] ?? date('Y-m-d');
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $roleVal = ($wing === 'montessori') ? 'montessori_teacher' : 'teacher';
                $db->prepare('INSERT INTO users (user_id, name, email, password, role, status) VALUES (?,?,?,?,?,?)')
                   ->execute([$empId, $name, $email ?: null, $hash, $roleVal, 'active']);
                $newId = (int)$db->lastInsertId();
                if ($hasTeacherWing) {
                    $db->prepare('INSERT INTO teachers (user_id, emp_id, subject_id, qualification, phone, join_date, wing) VALUES (?,?,?,?,?,?,?)')
                       ->execute([$newId, $empId, $subjectId ?: null, $qual, $phone ?: null, $joinDate, $wing]);
                } else {
                    $db->prepare('INSERT INTO teachers (user_id, emp_id, subject_id, qualification, phone, join_date) VALUES (?,?,?,?,?,?)')
                       ->execute([$newId, $empId, $subjectId ?: null, $qual, $phone ?: null, $joinDate]);
                }
                logActivity($user['id'], 'teacher_create', "Created teacher $empId (wing: $wing)");
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
        $wing  = in_array($_POST['wing'] ?? '', $allowedWings) ? $_POST['wing'] : 'main';
        if ($hasTeacherWing) {
            $db->prepare('UPDATE teachers SET phone=?, qualification=?, subject_id=?, wing=? WHERE id=?')
               ->execute([$phone, $qual, $subjectId ?: null, $wing, $id]);
        } else {
            $db->prepare('UPDATE teachers SET phone=?, qualification=?, subject_id=? WHERE id=?')
               ->execute([$phone, $qual, $subjectId ?: null, $id]);
        }
        // Sync users.role with wing assignment
        $newRole = ($wing === 'montessori') ? 'montessori_teacher' : 'teacher';
        $db->prepare('UPDATE users u JOIN teachers t ON t.user_id=u.id SET u.role=? WHERE t.id=?')
           ->execute([$newRole, $id]);
        logActivity($user['id'], 'teacher_edit', "Updated teacher #$id wing → $wing");
        setFlash('success', 'Teacher updated.');
    }

    if ($action === 'toggle_status') {
        $id = (int)$_POST['user_id'];
        $db->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
        setFlash('success', 'Status updated.');
    }

    redirect('/portal/admin/teachers.php');
}

// Search term (read early so the query can use it)
$search = trim($_GET['q'] ?? '');

// Get all teachers with details
$teachers = [];
try {
    $teacherWhere  = '';
    $teacherParams = [];
    if ($search !== '') {
        $likeSearch    = '%' . $search . '%';
        $teacherWhere  = 'WHERE (u.name LIKE ? OR u.user_id LIKE ? OR u.email LIKE ?
                                 OR sb.name LIKE ? OR t.qualification LIKE ? OR t.phone LIKE ?)';
        array_push($teacherParams, $likeSearch, $likeSearch, $likeSearch,
                                   $likeSearch, $likeSearch, $likeSearch);
    }
    $teachersSt = $db->prepare(
        "SELECT t.*, u.name, u.email, u.user_id AS uid, u.status, u.last_login,
                u.id AS users_id, sb.name AS subject_name, sb.code AS subject_code,
                (SELECT COUNT(DISTINCT cs.class_id) FROM class_subjects cs WHERE cs.teacher_id = t.id) AS class_count
         FROM teachers t
         JOIN users u ON t.user_id = u.id
         LEFT JOIN subjects sb ON t.subject_id = sb.id
         $teacherWhere
         ORDER BY u.name"
    );
    $teachersSt->execute($teacherParams);
    $teachers = $teachersSt->fetchAll();
} catch (Exception $e) {}
$subjects = getAllSubjects();

pageHead('Teachers', 'admin');
$links = getAdminLinks();
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
          <div class="col-md-2"><label class="form-label fw-semibold" style="font-size:.82rem">Wing</label>
            <select name="wing" class="form-select form-select-sm">
              <option value="main">Main Wing</option>
              <option value="montessori">Montessori</option>
              <option value="ilc">ILC</option>
            </select>
          </div>
          <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-sm btn-success w-100">Add Teacher</button></div>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$wingFilter = $_GET['wing'] ?? 'all';
$allowedWingFilters = ['all', 'main', 'montessori', 'ilc'];
if (!in_array($wingFilter, $allowedWingFilters)) $wingFilter = 'all';
$filteredTeachers = ($wingFilter === 'all') ? $teachers
    : array_filter($teachers, fn($t) => ($t['wing'] ?? 'main') === $wingFilter);
$counts = ['all' => count($teachers)];
foreach (['main','montessori','ilc'] as $w)
    $counts[$w] = count(array_filter($teachers, fn($t) => ($t['wing'] ?? 'main') === $w));
?>

<?php if ($wingFilter === 'montessori'): ?>
<div class="d-flex align-items-start gap-3 mb-3 p-3"
     style="background:#fdf4ff;border:1px solid #e9d5ff;border-radius:8px;font-size:.82rem">
  <i class="fas fa-info-circle mt-1" style="color:#7c3aed"></i>
  <div>
    <strong style="color:#5b21b6">Montessori Teacher Portal</strong> —
    Montessori teachers have a dedicated portal experience:
    <strong>Assessments &amp; Marks</strong> is replaced by <strong>Progress Report</strong>,
    while all other features (Attendance, Diary, Timetable, etc.) remain unchanged.
    Montessori students in their classes similarly see <strong>Progress Report</strong> instead of Results.
  </div>
</div>
<?php endif; ?>

<!-- Teachers table -->
<div class="sec-card">
  <div class="sec-card-header" style="padding-bottom:8px">
    <!-- Title + Search row -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
      <span><i class="fas fa-chalkboard-teacher me-2"></i>Teacher Accounts (<?= count($filteredTeachers) ?>)</span>
      <form method="GET" action="" class="d-flex gap-1 align-items-center">
        <?php if ($wingFilter !== 'all'): ?><input type="hidden" name="wing" value="<?= h($wingFilter) ?>"><?php endif; ?>
        <input type="text" name="q" value="<?= h($search) ?>"
               class="form-control form-control-sm" style="width:220px;font-size:.82rem"
               placeholder="Name, ID, email, subject, phone…" autocomplete="off">
        <button type="submit" class="btn btn-sm btn-primary" style="font-size:.8rem;padding:3px 10px;white-space:nowrap">
          <i class="fas fa-search me-1"></i>Search
        </button>
        <?php if ($search !== ''): ?>
        <a href="?wing=<?= h($wingFilter) ?>"
           class="btn btn-sm btn-outline-secondary" style="font-size:.8rem;padding:3px 9px" title="Clear search">
          <i class="fas fa-times"></i>
        </a>
        <?php endif; ?>
      </form>
    </div>
    <?php if ($search !== ''): ?>
    <div style="font-size:.78rem;color:#6b7280;padding:0 0 6px">
      <i class="fas fa-search me-1" style="color:#3b82f6"></i>
      Results for <strong style="color:#1e3a5f">"<?= h($search) ?>"</strong>
      <?php if ($wingFilter !== 'all'): ?> · wing <strong><?= h($wingFilter) ?></strong><?php endif; ?>
      — <strong><?= count($filteredTeachers) ?></strong> found
      <a href="?wing=<?= h($wingFilter) ?>" class="ms-2 text-decoration-none" style="font-size:.74rem;color:#6b7280">
        <i class="fas fa-times-circle me-1"></i>Clear search
      </a>
    </div>
    <?php endif; ?>
  </div>
  <!-- Wing filter tabs -->
  <div class="px-3 pt-2 pb-0">
    <ul class="nav nav-tabs nav-tabs-sm" style="font-size:.82rem">
      <?php foreach (['all'=>'All','main'=>'Main Wing','montessori'=>'Montessori','ilc'=>'ILC'] as $w=>$label): ?>
      <li class="nav-item">
        <a class="nav-link <?= $wingFilter===$w?'active':'' ?>"
           href="?wing=<?= $w ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">
          <?= $label ?>
          <span class="badge ms-1" style="background:<?= $wingFilter===$w?'#3730a3':'#94a3b8' ?>;font-size:.68rem">
            <?= $counts[$w] ?>
          </span>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light"><tr><th>Name</th><th>ID</th><th>Wing / Type</th><th>Subject</th><th>Qualification</th><th>Classes</th><th>Status</th><th>Last Login</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($filteredTeachers as $t): ?>
        <tr>
          <td class="fw-semibold">
            <?= h($t['name']) ?>
            <?php if (($t['wing'] ?? 'main') === 'montessori'): ?>
            <span style="font-size:.68rem;font-weight:600;color:#5b21b6;margin-left:4px">Montessori</span>
            <?php endif; ?>
          </td>
          <td><?= h($t['uid']) ?></td>
          <td><?= wingBadge($t['wing'] ?? 'main') ?></td>
          <td><?= h($t['subject_name'] ?? '—') ?></td>
          <td style="font-size:.8rem"><?= h($t['qualification'] ?: '—') ?></td>
          <td><?= $t['class_count'] ?></td>
          <td><span class="badge <?= $t['status']==='active'?'bg-success':'bg-danger' ?>"><?= $t['status'] ?></span></td>
          <td style="font-size:.78rem"><?= $t['last_login'] ? fDate($t['last_login']) : '—' ?></td>
          <td>
            <button class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 7px"
                    data-bs-toggle="modal" data-bs-target="#editTeacherModal"
                    data-id="<?= $t['id'] ?>" data-phone="<?= h($t['phone'] ?? '') ?>"
                    data-qual="<?= h($t['qualification'] ?? '') ?>" data-subject="<?= $t['subject_id'] ?>"
                    data-wing="<?= h($t['wing'] ?? 'main') ?>">Edit</button>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="user_id" value="<?= $t['users_id'] ?>">
              <button class="btn btn-xs btn-outline-<?= $t['status']==='active'?'warning':'success' ?>" style="font-size:.74rem;padding:2px 7px"><?= $t['status']==='active'?'Deactivate':'Activate' ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($filteredTeachers)): ?>
        <tr><td colspan="9" class="text-center text-muted py-4" style="font-size:.84rem">
          No <?= $wingFilter!=='all'?wingLabel($wingFilter).' ':'' ?>teachers found.
        </td></tr>
        <?php endif; ?>
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
          <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.85rem">Wing</label>
            <select name="wing" id="editWing" class="form-select">
              <option value="main">Main Wing</option>
              <option value="montessori">Montessori</option>
              <option value="ilc">ILC</option>
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
    document.getElementById('editTeacherId').value  = btn.dataset.id;
    document.getElementById('editPhone').value      = btn.dataset.phone;
    document.getElementById('editQual').value       = btn.dataset.qual;
    document.getElementById('editSubjectId').value  = btn.dataset.subject;
    document.getElementById('editWing').value       = btn.dataset.wing || 'main';
});
</script>
</body></html>

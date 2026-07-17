<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('student_affairs');
requirePermission('sa_students');
$db   = getDB();

// Detect optional columns
$hasHouseId      = false;
$hasAdmDate      = false;
$hasFatherName   = false;
$hasParentEmail  = false;
try { $db->query('SELECT house_id FROM students LIMIT 0');      $hasHouseId     = true; } catch (Exception $e) {}
try { $db->query('SELECT admission_date FROM students LIMIT 0'); $hasAdmDate     = true; } catch (Exception $e) {}
try { $db->query('SELECT father_name FROM students LIMIT 0');    $hasFatherName  = true; } catch (Exception $e) {}
try { $db->query('SELECT parent_email FROM students LIMIT 0');   $hasParentEmail = true; } catch (Exception $e) {}

// ── POST handlers ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add student ───────────────────────────────────────────────
    if ($action === 'add_student') {
        $name    = trim($_POST['name']    ?? '');
        $userId  = trim($_POST['user_id'] ?? '');
        $email   = trim($_POST['email']   ?? '');
        $classId = (int)($_POST['class_id'] ?? 0) ?: null;

        if (!$name || !$userId) {
            setFlash('danger', 'Full name and User ID are required.');
        } else {
            $ck = $db->prepare('SELECT id FROM users WHERE user_id = ?');
            $ck->execute([$userId]);
            if ($ck->fetch()) {
                setFlash('danger', 'User ID already exists.');
            } else {
                $tempPass = bin2hex(random_bytes(16));
                $hash     = password_hash($tempPass, PASSWORD_BCRYPT);
                $db->prepare('INSERT INTO users (user_id,name,email,password,role,status) VALUES (?,?,?,?,?,?)')
                   ->execute([$userId, $name, $email ?: null, $hash, 'student', 'active']);
                $newId = (int)$db->lastInsertId();

                $extraCols = $hasAdmDate ? ', admission_date' : '';
                $extraVals = $hasAdmDate ? ', CURDATE()'      : '';
                $db->prepare("INSERT INTO students (user_id, roll_no, class_id$extraCols) VALUES (?,?,?$extraVals)")
                   ->execute([$newId, $userId, $classId]);

                logActivity($user['id'], 'student_add', "Added student $userId");
                setFlash('success', "Student $userId added successfully.");
            }
        }
        redirect('/student-affairs/students.php');
    }

    // ── Edit student ──────────────────────────────────────────────
    if ($action === 'edit_student') {
        $studentId  = (int)($_POST['student_id'] ?? 0);
        $name       = trim($_POST['name']         ?? '');
        $email      = trim($_POST['email']        ?? '');
        $classId    = (int)($_POST['class_id']    ?? 0) ?: null;
        $rollNo     = trim($_POST['roll_no']      ?? '');
        $gender     = $_POST['gender']            ?? '';
        $dob        = $_POST['dob']               ?? '';
        $cnic       = trim($_POST['cnic']         ?? '');
        $phone      = trim($_POST['phone']        ?? '');
        $address    = trim($_POST['address']      ?? '');
        $parentPhone = trim($_POST['parent_phone'] ?? '');
        $fatherName  = trim($_POST['father_name']  ?? '');
        $parentEmail = trim($_POST['parent_email'] ?? '');

        if (!$studentId || !$name) {
            setFlash('danger', 'Student ID and name are required.');
            redirect('/student-affairs/students.php');
        }

        // Fetch user_id for this student
        $st = $db->prepare('SELECT user_id FROM students WHERE id = ?');
        $st->execute([$studentId]);
        $row = $st->fetch();
        if (!$row) { setFlash('danger', 'Student not found.'); redirect('/student-affairs/students.php'); }
        $uid = $row['user_id'];

        $db->prepare('UPDATE users SET name=?, email=? WHERE id=?')
           ->execute([$name, $email ?: null, $uid]);

        $setParts  = ['roll_no=?', 'class_id=?', 'gender=?', 'cnic=?', 'phone=?', 'address=?', 'parent_phone=?'];
        $setVals   = [$rollNo ?: null, $classId, ($gender ?: null), ($cnic ?: null), ($phone ?: null), ($address ?: null), ($parentPhone ?: null)];
        if ($dob) { $setParts[] = 'dob=?'; $setVals[] = $dob; }
        if ($hasFatherName)  { $setParts[] = 'father_name=?';  $setVals[] = $fatherName  ?: null; }
        if ($hasParentEmail) { $setParts[] = 'parent_email=?'; $setVals[] = $parentEmail ?: null; }
        $setVals[] = $studentId;

        $db->prepare('UPDATE students SET ' . implode(',', $setParts) . ' WHERE id=?')
           ->execute($setVals);

        logActivity($user['id'], 'student_edit', "Edited student #$studentId");
        setFlash('success', 'Student updated successfully.');
        redirect('/student-affairs/students.php');
    }

    // ── Toggle status ─────────────────────────────────────────────
    if ($action === 'toggle_status') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid) {
            $db->prepare("UPDATE users SET status=IF(status='active','inactive','active') WHERE id=? AND role='student'")
               ->execute([$uid]);
            logActivity($user['id'], 'student_status', "Toggled status for user #$uid");
            setFlash('success', 'Status updated.');
        }
        redirect('/student-affairs/students.php');
    }

    // ── Delete student ────────────────────────────────────────────
    if ($action === 'delete_student') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid) {
            $db->prepare("DELETE FROM users WHERE id=? AND role='student'")->execute([$uid]);
            logActivity($user['id'], 'student_delete', "Deleted student user #$uid");
            setFlash('success', 'Student deleted.');
        }
        redirect('/student-affairs/students.php');
    }

    // ── Approve/Reject profile change request ─────────────────────
    if ($action === 'approve_request') {
        $reqId = (int)($_POST['request_id'] ?? 0);
        $st    = $db->prepare('SELECT * FROM profile_change_requests WHERE id=?');
        $st->execute([$reqId]);
        $r = $st->fetch();
        if ($r && in_array($r['field'], ['phone','address','father_name','parent_name','parent_phone','parent_email','cnic'])) {
            $db->prepare("UPDATE students SET {$r['field']}=? WHERE id=?")->execute([$r['new_value'], $r['student_id']]);
            $db->prepare("UPDATE profile_change_requests SET status='approved',reviewed_by=?,reviewed_at=NOW() WHERE id=?")
               ->execute([$user['id'], $reqId]);
            setFlash('success', 'Profile change approved.');
        }
        redirect('/student-affairs/students.php');
    }

    if ($action === 'reject_request') {
        $reqId = (int)($_POST['request_id'] ?? 0);
        if ($reqId) {
            $db->prepare("UPDATE profile_change_requests SET status='rejected',reviewed_by=?,reviewed_at=NOW() WHERE id=?")
               ->execute([$user['id'], $reqId]);
            setFlash('success', 'Request rejected.');
        }
        redirect('/student-affairs/students.php');
    }
}

// ── Filters & pagination ──────────────────────────────────────────
$search    = trim($_GET['q']        ?? '');
$classFilter = (int)($_GET['class_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 25;

$where  = ["u.role='student'"];
$params = [];
if ($search !== '') {
    $where[]  = '(u.name LIKE ? OR s.roll_no LIKE ? OR u.email LIKE ?)';
    $like     = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($classFilter > 0) {
    $where[]  = 's.class_id = ?';
    $params[] = $classFilter;
}
if ($statusFilter !== '') {
    $where[]  = 'u.status = ?';
    $params[] = $statusFilter;
}
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$fatherCol   = $hasFatherName  ? ', s.father_name'  : '';
$parentECol  = $hasParentEmail ? ', s.parent_email'  : '';
$houseCol    = $hasHouseId     ? ', h.name AS house_name' : '';
$houseJoin   = $hasHouseId     ? 'LEFT JOIN houses h ON h.id = s.house_id' : '';

$totalSt = $db->prepare("SELECT COUNT(*) FROM users u JOIN students s ON s.user_id=u.id LEFT JOIN classes c ON c.id=s.class_id $whereSQL");
$totalSt->execute($params);
$total = (int)$totalSt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$studentsSt = $db->prepare(
    "SELECT u.id AS uid, u.user_id, u.name, u.email, u.status,
            s.id AS student_id, s.roll_no, s.class_id, s.gender, s.dob, s.cnic,
            s.phone, s.address, s.parent_phone
            $fatherCol $parentECol $houseCol,
            c.name AS class_name
     FROM users u
     JOIN students s ON s.user_id = u.id
     LEFT JOIN classes c ON c.id = s.class_id
     $houseJoin
     $whereSQL
     ORDER BY c.name, s.roll_no
     LIMIT $perPage OFFSET $offset"
);
$studentsSt->execute($params);
$students = $studentsSt->fetchAll();

// Stats
$totalCount  = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$activeCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='student' AND status='active'")->fetchColumn();

// Pending profile change requests
$pendingRequests = [];
try {
    $pr = $db->prepare(
        "SELECT pcr.*, u.name AS student_name, u.user_id AS student_roll
         FROM profile_change_requests pcr
         JOIN students s ON pcr.student_id=s.id
         JOIN users u ON s.user_id=u.id
         WHERE pcr.status='pending'
         ORDER BY pcr.created_at DESC"
    );
    $pr->execute();
    $pendingRequests = $pr->fetchAll();
} catch (PDOException $e) { /* table may not exist */ }

$classes = getAllClasses();
$houses  = [];
if ($hasHouseId) {
    try { $houses = $db->query('SELECT * FROM houses ORDER BY name')->fetchAll(); } catch (Exception $e) {}
}

pageHead('Students — Student Affairs', 'student_affairs');
$links = getStudentAffairsLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('student_affairs', 'students', $links, $user); ?>
<div class="main-area">
<?php topbar('Student Management', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Stats strip -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#ede9fe;color:#7c3aed"><i class="fas fa-user-graduate"></i></div>
      <div class="stat-val" style="color:#7c3aed"><?= $totalCount ?></div>
      <div class="stat-lbl">Total Students</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#d1fae5;color:#059669"><i class="fas fa-check-circle"></i></div>
      <div class="stat-val" style="color:#059669"><?= $activeCount ?></div>
      <div class="stat-lbl">Active</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-user-slash"></i></div>
      <div class="stat-val" style="color:#dc2626"><?= $totalCount - $activeCount ?></div>
      <div class="stat-lbl">Inactive</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-clock"></i></div>
      <div class="stat-val" style="color:#d97706"><?= count($pendingRequests) ?></div>
      <div class="stat-lbl">Pending Requests</div>
    </div>
  </div>
</div>

<!-- Pending profile change requests -->
<?php if (!empty($pendingRequests)): ?>
<div class="sec-card mb-3" style="border:1px solid #fbbf24">
  <div class="sec-card-header" style="color:#92400e;background:#fffbeb">
    <i class="fas fa-clock me-2"></i>Pending Profile Change Requests (<?= count($pendingRequests) ?>)
  </div>
  <div class="table-responsive">
    <table class="table table-sm mb-0" style="font-size:.84rem">
      <thead class="table-light"><tr><th>Student</th><th>Field</th><th>Old</th><th>New</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($pendingRequests as $r): ?>
        <tr>
          <td><?= h($r['student_name']) ?> <span class="text-muted">(<?= h($r['student_roll']) ?>)</span></td>
          <td><?= h(ucwords(str_replace('_',' ',$r['field']))) ?></td>
          <td><?= h($r['old_value'] ?: '—') ?></td>
          <td class="fw-semibold"><?= h($r['new_value']) ?></td>
          <td style="font-size:.78rem"><?= fDate($r['created_at']) ?></td>
          <td>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="approve_request">
              <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
              <button class="btn btn-xs btn-success" style="font-size:.74rem;padding:2px 8px">Approve</button>
            </form>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="reject_request">
              <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
              <button class="btn btn-xs btn-danger" style="font-size:.74rem;padding:2px 8px">Reject</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Add Student (collapsible) -->
<div class="sec-card mb-3">
  <div class="sec-card-header d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#addStudentForm" style="cursor:pointer">
    <span><i class="fas fa-user-plus me-2"></i>Add New Student</span>
    <i class="fas fa-chevron-down" style="font-size:.8rem"></i>
  </div>
  <div id="addStudentForm" class="collapse">
    <div style="padding:16px">
      <form method="POST">
        <input type="hidden" name="action" value="add_student">
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label fw-semibold" style="font-size:.82rem">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control form-control-sm" required placeholder="e.g. Ahmed Khan">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">User / Roll ID <span class="text-danger">*</span></label>
            <input type="text" name="user_id" class="form-control form-control-sm" required placeholder="e.g. S-2025-001">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Email <small class="text-muted">(optional)</small></label>
            <input type="email" name="email" class="form-control form-control-sm" placeholder="student@example.com">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
            <select name="class_id" class="form-select form-select-sm">
              <option value="">— Select class —</option>
              <?php foreach ($classes as $c): ?>
              <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn btn-sm btn-success px-4">
              <i class="fas fa-plus me-1"></i>Add Student
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Student list -->
<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-user-graduate me-2"></i>All Students (<?= $total ?>)</span>
    <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
      <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm"
             placeholder="Search name, roll no, email…" style="width:200px">
      <select name="class_id" class="form-select form-select-sm" style="width:140px" onchange="this.form.submit()">
        <option value="">All Classes</option>
        <?php foreach ($classes as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $classFilter==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="form-select form-select-sm" style="width:110px" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="active"   <?= $statusFilter==='active'  ?'selected':'' ?>>Active</option>
        <option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>Inactive</option>
      </select>
      <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
      <?php if ($search || $classFilter || $statusFilter): ?>
      <a href="<?= url('/student-affairs/students.php') ?>" class="btn btn-sm btn-outline-danger">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (empty($students)): ?>
  <div style="padding:48px;text-align:center;color:var(--t2)">
    <i class="fas fa-user-graduate fa-2x mb-2 d-block opacity-25"></i>
    <p class="mb-0">No students found<?= ($search || $classFilter || $statusFilter) ? ' for the selected filters' : '' ?>.</p>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.83rem">
      <thead class="table-light">
        <tr>
          <th>Roll / ID</th>
          <th>Name</th>
          <th>Class</th>
          <th>Gender</th>
          <th>Phone</th>
          <th>Parent Phone</th>
          <th>Status</th>
          <th style="width:120px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $s): ?>
        <tr>
          <td class="fw-semibold"><?= h($s['roll_no']) ?></td>
          <td>
            <?= h($s['name']) ?>
            <?php if ($s['email']): ?><div style="font-size:.74rem;color:var(--t3)"><?= h($s['email']) ?></div><?php endif; ?>
          </td>
          <td><?= $s['class_name'] ? '<span class="badge bg-secondary">'.h($s['class_name']).'</span>' : '<span class="text-muted">—</span>' ?></td>
          <td><?= $s['gender'] ? ucfirst($s['gender']) : '—' ?></td>
          <td><?= h($s['phone'] ?: '—') ?></td>
          <td><?= h($s['parent_phone'] ?: '—') ?></td>
          <td><span class="badge <?= $s['status']==='active'?'bg-success':'bg-danger' ?>"><?= $s['status'] ?></span></td>
          <td>
            <!-- Edit -->
            <button class="btn btn-xs btn-outline-primary me-1"
                    data-bs-toggle="modal" data-bs-target="#editModal<?= $s['student_id'] ?>"
                    title="Edit">
              <i class="fas fa-edit"></i>
            </button>
            <!-- Toggle status -->
            <form method="POST" class="d-inline"
                  onsubmit="return confirm('<?= $s['status']==='active' ? 'Deactivate' : 'Activate' ?> this student?')">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="uid" value="<?= $s['uid'] ?>">
              <button class="btn btn-xs btn-outline-<?= $s['status']==='active'?'warning':'success' ?> me-1"
                      title="<?= $s['status']==='active'?'Deactivate':'Activate' ?>">
                <i class="fas fa-<?= $s['status']==='active'?'user-slash':'user-check' ?>"></i>
              </button>
            </form>
            <!-- Delete -->
            <form method="POST" class="d-inline"
                  onsubmit="return confirm('Permanently delete <?= h(addslashes($s['name'])) ?>? This cannot be undone.')">
              <input type="hidden" name="action" value="delete_student">
              <input type="hidden" name="uid" value="<?= $s['uid'] ?>">
              <button class="btn btn-xs btn-outline-danger" title="Delete">
                <i class="fas fa-trash"></i>
              </button>
            </form>
          </td>
        </tr>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal<?= $s['student_id'] ?>" tabindex="-1">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header py-2" style="background:#fdf2f8;border-bottom:1px solid #fbcfe8">
                <h6 class="modal-title" style="color:#be185d">
                  <i class="fas fa-user-edit me-2"></i>Edit Student — <?= h($s['name']) ?>
                </h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
              </div>
              <form method="POST">
                <input type="hidden" name="action" value="edit_student">
                <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
                <div class="modal-body">
                  <div class="row g-2">
                    <!-- Basic info -->
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Full Name <span class="text-danger">*</span></label>
                      <input type="text" name="name" class="form-control form-control-sm"
                             value="<?= h($s['name']) ?>" required>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Roll / User ID</label>
                      <input type="text" name="roll_no" class="form-control form-control-sm"
                             value="<?= h($s['roll_no']) ?>">
                    </div>
                    <div class="col-md-5">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Email</label>
                      <input type="email" name="email" class="form-control form-control-sm"
                             value="<?= h($s['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
                      <select name="class_id" class="form-select form-select-sm">
                        <option value="">— No class —</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $s['class_id']==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Gender</label>
                      <select name="gender" class="form-select form-select-sm">
                        <option value="">— Select —</option>
                        <option value="male"   <?= $s['gender']==='male'  ?'selected':'' ?>>Male</option>
                        <option value="female" <?= $s['gender']==='female'?'selected':'' ?>>Female</option>
                        <option value="other"  <?= $s['gender']==='other' ?'selected':'' ?>>Other</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Date of Birth</label>
                      <input type="date" name="dob" class="form-control form-control-sm"
                             value="<?= h($s['dob'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">CNIC / B-Form</label>
                      <input type="text" name="cnic" class="form-control form-control-sm"
                             value="<?= h($s['cnic'] ?? '') ?>" placeholder="00000-0000000-0">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Student Phone</label>
                      <input type="tel" name="phone" class="form-control form-control-sm"
                             value="<?= h($s['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Parent Phone</label>
                      <input type="tel" name="parent_phone" class="form-control form-control-sm"
                             value="<?= h($s['parent_phone'] ?? '') ?>">
                    </div>
                    <?php if ($hasFatherName): ?>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Father / Guardian Name</label>
                      <input type="text" name="father_name" class="form-control form-control-sm"
                             value="<?= h($s['father_name'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                    <?php if ($hasParentEmail): ?>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Parent Email</label>
                      <input type="email" name="parent_email" class="form-control form-control-sm"
                             value="<?= h($s['parent_email'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Address</label>
                      <textarea name="address" class="form-control form-control-sm" rows="2"><?= h($s['address'] ?? '') ?></textarea>
                    </div>
                  </div>
                </div>
                <div class="modal-footer py-2">
                  <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-save me-1"></i>Save Changes
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div class="d-flex justify-content-center p-2 gap-1 flex-wrap">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?q=<?= urlencode($search) ?>&class_id=<?= $classFilter ?>&status=<?= urlencode($statusFilter) ?>&page=<?= $i ?>"
       class="btn btn-xs <?= $i===$page?'btn-primary':'btn-outline-secondary' ?>" style="font-size:.78rem;padding:2px 8px"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('student_affairs');
requirePermission('sa_students');
$db   = getDB();

// Detect which columns exist in the students table (single query, forward-compatible)
$_stuCols = array_flip(
    $db->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN)
);
$hasHouseId      = isset($_stuCols['house_id']);
$hasAdmDate      = isset($_stuCols['admission_date']);
$hasFatherName   = isset($_stuCols['father_name']);
$hasParentEmail  = isset($_stuCols['parent_email']);
// New biodata columns (added by students-biodata-migration)
$hasGrNo         = isset($_stuCols['gr_no']);
$hasKuickpayId   = isset($_stuCols['kuickpay_id']);
$hasCategory     = isset($_stuCols['category']);
$hasAcadGroup    = isset($_stuCols['academic_group']);
$hasChildOrder   = isset($_stuCols['child_order']);
$hasDomicile     = isset($_stuCols['domicile']);
$hasPermAddr     = isset($_stuCols['permanent_address']);
$hasEmergPhone   = isset($_stuCols['emergency_phone']);
$hasWhatsapp     = isset($_stuCols['whatsapp_no']);
$hasReligion     = isset($_stuCols['religion']);
$hasSect         = isset($_stuCols['sect']);
$hasBloodGroup   = isset($_stuCols['blood_group']);
$hasLastSchool   = isset($_stuCols['last_school']);
$hasNationality  = isset($_stuCols['nationality']);
$hasFatherOcc    = isset($_stuCols['father_occupation']);
$hasDocsSub      = isset($_stuCols['documents_submitted']);
$hasMedicalInfo  = isset($_stuCols['medical_info']);
$hasSkills       = isset($_stuCols['skills']);
$hasSports       = isset($_stuCols['sports']);
$hasAwards       = isset($_stuCols['awards']);

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
        $studentId   = (int)($_POST['student_id']   ?? 0);
        $name        = trim($_POST['name']           ?? '');
        $email       = trim($_POST['email']          ?? '');
        $classId     = (int)($_POST['class_id']      ?? 0) ?: null;
        $rollNo      = trim($_POST['roll_no']        ?? '');
        $gender      = $_POST['gender']              ?? '';
        $dob         = $_POST['dob']                 ?? '';
        $cnic        = trim($_POST['cnic']           ?? '');
        $phone       = trim($_POST['phone']          ?? '');
        $address     = trim($_POST['address']        ?? '');
        $parentPhone = trim($_POST['parent_phone']   ?? '');
        $parentName  = trim($_POST['parent_name']    ?? '');
        $fatherName  = trim($_POST['father_name']    ?? '');
        $parentEmail = trim($_POST['parent_email']   ?? '');

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

        $houseId  = (int)($_POST['house_id'] ?? 0) ?: null;
        $setParts = ['roll_no=?','class_id=?','gender=?','cnic=?','phone=?','address=?',
                     'parent_phone=?','parent_name=?'];
        $setVals  = [
            $rollNo ?: null, $classId, ($gender ?: null), ($cnic ?: null),
            ($phone ?: null), ($address ?: null), ($parentPhone ?: null), ($parentName ?: null),
        ];
        if ($dob)               { $setParts[] = 'dob=?';                  $setVals[] = $dob; }
        if ($hasFatherName)     { $setParts[] = 'father_name=?';          $setVals[] = $fatherName  ?: null; }
        if ($hasParentEmail)    { $setParts[] = 'parent_email=?';         $setVals[] = $parentEmail ?: null; }
        if ($hasHouseId)        { $setParts[] = 'house_id=?';             $setVals[] = $houseId; }
        // New biodata columns
        if ($hasGrNo)           { $setParts[] = 'gr_no=?';                $setVals[] = trim($_POST['gr_no'] ?? '') ?: null; }
        if ($hasKuickpayId)     { $setParts[] = 'kuickpay_id=?';          $setVals[] = trim($_POST['kuickpay_id'] ?? '') ?: null; }
        if ($hasCategory)       { $setParts[] = 'category=?';             $setVals[] = trim($_POST['category'] ?? '') ?: null; }
        if ($hasAcadGroup)      { $setParts[] = 'academic_group=?';       $setVals[] = trim($_POST['academic_group'] ?? '') ?: null; }
        if ($hasChildOrder)     { $setParts[] = 'child_order=?';          $setVals[] = (trim($_POST['child_order'] ?? '') !== '') ? (int)$_POST['child_order'] : null; }
        if ($hasDomicile)       { $setParts[] = 'domicile=?';             $setVals[] = trim($_POST['domicile'] ?? '') ?: null; }
        if ($hasPermAddr)       { $setParts[] = 'permanent_address=?';    $setVals[] = trim($_POST['permanent_address'] ?? '') ?: null; }
        if ($hasEmergPhone)     { $setParts[] = 'emergency_phone=?';      $setVals[] = trim($_POST['emergency_phone'] ?? '') ?: null; }
        if ($hasWhatsapp)       { $setParts[] = 'whatsapp_no=?';          $setVals[] = trim($_POST['whatsapp_no'] ?? '') ?: null; }
        if ($hasReligion)       { $setParts[] = 'religion=?';             $setVals[] = trim($_POST['religion'] ?? '') ?: null; }
        if ($hasSect)           { $setParts[] = 'sect=?';                 $setVals[] = trim($_POST['sect'] ?? '') ?: null; }
        if ($hasBloodGroup)     { $setParts[] = 'blood_group=?';          $setVals[] = trim($_POST['blood_group'] ?? '') ?: null; }
        if ($hasLastSchool)     { $setParts[] = 'last_school=?';          $setVals[] = trim($_POST['last_school'] ?? '') ?: null; }
        if ($hasNationality)    { $setParts[] = 'nationality=?';          $setVals[] = trim($_POST['nationality'] ?? '') ?: null; }
        if ($hasFatherOcc)      { $setParts[] = 'father_occupation=?';    $setVals[] = trim($_POST['father_occupation'] ?? '') ?: null; }
        if ($hasDocsSub)        { $setParts[] = 'documents_submitted=?';  $setVals[] = trim($_POST['documents_submitted'] ?? '') ?: null; }
        if ($hasMedicalInfo)    { $setParts[] = 'medical_info=?';         $setVals[] = trim($_POST['medical_info'] ?? '') ?: null; }
        if ($hasSkills)         { $setParts[] = 'skills=?';               $setVals[] = trim($_POST['skills'] ?? '') ?: null; }
        if ($hasSports)         { $setParts[] = 'sports=?';               $setVals[] = trim($_POST['sports'] ?? '') ?: null; }
        if ($hasAwards)         { $setParts[] = 'awards=?';               $setVals[] = trim($_POST['awards'] ?? '') ?: null; }

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
        $editableFields = ['phone','address','permanent_address','father_name','parent_name',
                           'parent_phone','parent_email','cnic','whatsapp_no','emergency_phone'];
        if ($r && in_array($r['field'], $editableFields)) {
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

// Build optional column selects
$optCols = '';
if ($hasFatherName)  $optCols .= ', s.father_name';
if ($hasParentEmail) $optCols .= ', s.parent_email';
if ($hasHouseId)     $optCols .= ', s.house_id, h.name AS house_name';
if ($hasGrNo)        $optCols .= ', s.gr_no';
if ($hasKuickpayId)  $optCols .= ', s.kuickpay_id';
if ($hasCategory)    $optCols .= ', s.category';
if ($hasAcadGroup)   $optCols .= ', s.academic_group';
if ($hasChildOrder)  $optCols .= ', s.child_order';
if ($hasDomicile)    $optCols .= ', s.domicile';
if ($hasPermAddr)    $optCols .= ', s.permanent_address';
if ($hasEmergPhone)  $optCols .= ', s.emergency_phone';
if ($hasWhatsapp)    $optCols .= ', s.whatsapp_no';
if ($hasReligion)    $optCols .= ', s.religion';
if ($hasSect)        $optCols .= ', s.sect';
if ($hasBloodGroup)  $optCols .= ', s.blood_group';
if ($hasLastSchool)  $optCols .= ', s.last_school';
if ($hasNationality) $optCols .= ', s.nationality';
if ($hasFatherOcc)   $optCols .= ', s.father_occupation';
if ($hasDocsSub)     $optCols .= ', s.documents_submitted';
if ($hasMedicalInfo) $optCols .= ', s.medical_info';
if ($hasSkills)      $optCols .= ', s.skills';
if ($hasSports)      $optCols .= ', s.sports';
if ($hasAwards)      $optCols .= ', s.awards';

$houseJoin = $hasHouseId ? 'LEFT JOIN houses h ON h.id = s.house_id' : '';

$totalSt = $db->prepare("SELECT COUNT(*) FROM users u JOIN students s ON s.user_id=u.id LEFT JOIN classes c ON c.id=s.class_id $whereSQL");
$totalSt->execute($params);
$total = (int)$totalSt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$studentsSt = $db->prepare(
    "SELECT u.id AS uid, u.user_id, u.name, u.email, u.status,
            s.id AS student_id, s.roll_no, s.class_id, s.gender, s.dob, s.cnic,
            s.phone, s.address, s.parent_phone, s.parent_name
            $optCols,
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
          <div class="modal-dialog modal-xl">
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
                <div class="modal-body" style="max-height:75vh;overflow-y:auto">

                  <?php /* ── Section label helper */ $lbl = fn(string $t) => '<div class="col-12"><div class="fw-bold border-bottom mb-1 pb-1 mt-2" style="font-size:.78rem;color:#6b7280;letter-spacing:.06em;text-transform:uppercase">'.$t.'</div></div>'; ?>

                  <div class="row g-2">

                    <?= $lbl('Basic Information') ?>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Full Name <span class="text-danger">*</span></label>
                      <input type="text" name="name" class="form-control form-control-sm" value="<?= h($s['name']) ?>" required>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Roll / User ID</label>
                      <input type="text" name="roll_no" class="form-control form-control-sm" value="<?= h($s['roll_no']) ?>">
                    </div>
                    <?php if ($hasGrNo): ?>
                    <div class="col-md-2">
                      <label class="form-label fw-semibold" style="font-size:.82rem">GR No</label>
                      <input type="text" name="gr_no" class="form-control form-control-sm" value="<?= h($s['gr_no'] ?? '') ?>" placeholder="GR-2025-001">
                    </div>
                    <?php endif; ?>
                    <?php if ($hasKuickpayId): ?>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Kuickpay ID</label>
                      <input type="text" name="kuickpay_id" class="form-control form-control-sm" value="<?= h($s['kuickpay_id'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                    <div class="col-md-5">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Email</label>
                      <input type="email" name="email" class="form-control form-control-sm" value="<?= h($s['email'] ?? '') ?>">
                    </div>

                    <?= $lbl('Academic') ?>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
                      <select name="class_id" class="form-select form-select-sm">
                        <option value="">— No class —</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $s['class_id']==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <?php if ($hasAcadGroup): ?>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Academic Group</label>
                      <select name="academic_group" class="form-select form-select-sm">
                        <option value="">— Select —</option>
                        <?php foreach (['Science','Arts','Pre-Medical','Commerce','General'] as $g): ?>
                        <option <?= ($s['academic_group'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <?php endif; ?>
                    <?php if ($hasCategory): ?>
                    <div class="col-md-2">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Category</label>
                      <input type="text" name="category" class="form-control form-control-sm" value="<?= h($s['category'] ?? '') ?>" placeholder="AOB 1">
                    </div>
                    <?php endif; ?>
                    <?php if ($hasHouseId && !empty($houses)): ?>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" style="font-size:.82rem">House</label>
                      <select name="house_id" class="form-select form-select-sm">
                        <option value="">— No house —</option>
                        <?php foreach ($houses as $h): ?>
                        <option value="<?= $h['id'] ?>" <?= (($s['house_id'] ?? 0) == $h['id']) ? 'selected' : '' ?>><?= htmlspecialchars($h['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <?php endif; ?>

                    <?= $lbl('Personal Details') ?>
                    <div class="col-md-2">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Gender</label>
                      <select name="gender" class="form-select form-select-sm">
                        <option value="">— Select —</option>
                        <option value="male"   <?= $s['gender']==='male'  ?'selected':'' ?>>Male</option>
                        <option value="female" <?= $s['gender']==='female'?'selected':'' ?>>Female</option>
                        <option value="other"  <?= $s['gender']==='other' ?'selected':'' ?>>Other</option>
                      </select>
                    </div>
                    <div class="col-md-2">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Date of Birth</label>
                      <input type="date" name="dob" class="form-control form-control-sm" value="<?= h($s['dob'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" style="font-size:.82rem">CNIC / B-Form</label>
                      <input type="text" name="cnic" class="form-control form-control-sm" value="<?= h($s['cnic'] ?? '') ?>" placeholder="00000-0000000-0">
                    </div>
                    <?php if ($hasBloodGroup): ?>
                    <div class="col-md-2">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Blood Group</label>
                      <select name="blood_group" class="form-select form-select-sm">
                        <option value="">—</option>
                        <?php foreach (['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $bg): ?>
                        <option <?= ($s['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <?php endif; ?>
                    <?php if ($hasChildOrder): ?>
                    <div class="col-md-2">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Child Order</label>
                      <input type="number" name="child_order" class="form-control form-control-sm" min="1" max="20" value="<?= h($s['child_order'] ?? '') ?>" placeholder="1=first">
                    </div>
                    <?php endif; ?>
                    <?php if ($hasNationality): ?>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Nationality</label>
                      <input type="text" name="nationality" class="form-control form-control-sm" value="<?= h($s['nationality'] ?? '') ?>" placeholder="Pakistani">
                    </div>
                    <?php endif; ?>
                    <?php if ($hasDomicile): ?>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Domicile</label>
                      <input type="text" name="domicile" class="form-control form-control-sm" value="<?= h($s['domicile'] ?? '') ?>" placeholder="Punjab">
                    </div>
                    <?php endif; ?>
                    <?php if ($hasReligion): ?>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Religion</label>
                      <input type="text" name="religion" class="form-control form-control-sm" value="<?= h($s['religion'] ?? '') ?>" placeholder="Islam">
                    </div>
                    <?php endif; ?>
                    <?php if ($hasSect): ?>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Sect</label>
                      <input type="text" name="sect" class="form-control form-control-sm" value="<?= h($s['sect'] ?? '') ?>">
                    </div>
                    <?php endif; ?>

                    <?= $lbl('Contact') ?>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Student Phone</label>
                      <input type="tel" name="phone" class="form-control form-control-sm" value="<?= h($s['phone'] ?? '') ?>">
                    </div>
                    <?php if ($hasWhatsapp): ?>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" style="font-size:.82rem">WhatsApp</label>
                      <input type="tel" name="whatsapp_no" class="form-control form-control-sm" value="<?= h($s['whatsapp_no'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                    <?php if ($hasEmergPhone): ?>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Emergency Phone</label>
                      <input type="tel" name="emergency_phone" class="form-control form-control-sm" value="<?= h($s['emergency_phone'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Present Address</label>
                      <textarea name="address" class="form-control form-control-sm" rows="2"><?= h($s['address'] ?? '') ?></textarea>
                    </div>
                    <?php if ($hasPermAddr): ?>
                    <div class="col-12">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Permanent Address</label>
                      <textarea name="permanent_address" class="form-control form-control-sm" rows="2"><?= h($s['permanent_address'] ?? '') ?></textarea>
                    </div>
                    <?php endif; ?>

                    <?= $lbl('Parent / Guardian') ?>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Parent / Guardian Name</label>
                      <input type="text" name="parent_name" class="form-control form-control-sm" value="<?= h($s['parent_name'] ?? '') ?>">
                    </div>
                    <?php if ($hasFatherName): ?>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Father's Name</label>
                      <input type="text" name="father_name" class="form-control form-control-sm" value="<?= h($s['father_name'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                    <?php if ($hasFatherOcc): ?>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Father's Occupation</label>
                      <input type="text" name="father_occupation" class="form-control form-control-sm" value="<?= h($s['father_occupation'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Parent Phone</label>
                      <input type="tel" name="parent_phone" class="form-control form-control-sm" value="<?= h($s['parent_phone'] ?? '') ?>">
                    </div>
                    <?php if ($hasParentEmail): ?>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Parent Email</label>
                      <input type="email" name="parent_email" class="form-control form-control-sm" value="<?= h($s['parent_email'] ?? '') ?>">
                    </div>
                    <?php endif; ?>

                    <?= $lbl('Background & Medical') ?>
                    <?php if ($hasLastSchool): ?>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Last School Attended</label>
                      <input type="text" name="last_school" class="form-control form-control-sm" value="<?= h($s['last_school'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                    <?php if ($hasDocsSub): ?>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Documents Submitted</label>
                      <input type="text" name="documents_submitted" class="form-control form-control-sm" value="<?= h($s['documents_submitted'] ?? '') ?>" placeholder="B-Form, Report Card, Photos">
                    </div>
                    <?php endif; ?>
                    <?php if ($hasMedicalInfo): ?>
                    <div class="col-12">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Medical History</label>
                      <textarea name="medical_info" class="form-control form-control-sm" rows="2" placeholder="Chronic conditions, allergies, medications, immunizations…"><?= h($s['medical_info'] ?? '') ?></textarea>
                    </div>
                    <?php endif; ?>

                    <?= $lbl('Skills & Activities') ?>
                    <?php if ($hasSkills): ?>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Skills</label>
                      <input type="text" name="skills" class="form-control form-control-sm" value="<?= h($s['skills'] ?? '') ?>" placeholder="Drawing, Coding, …">
                    </div>
                    <?php endif; ?>
                    <?php if ($hasSports): ?>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Sports / Activities</label>
                      <input type="text" name="sports" class="form-control form-control-sm" value="<?= h($s['sports'] ?? '') ?>" placeholder="Cricket, Football, …">
                    </div>
                    <?php endif; ?>
                    <?php if ($hasAwards): ?>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Awards / Certificates</label>
                      <input type="text" name="awards" class="form-control form-control-sm" value="<?= h($s['awards'] ?? '') ?>">
                    </div>
                    <?php endif; ?>

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

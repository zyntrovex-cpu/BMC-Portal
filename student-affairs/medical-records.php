<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('student_affairs');
requirePermission('sa_medical');
$db   = getDB();

// ── Handle add / delete ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_record') {
        $studentId   = (int)$_POST['student_id'];
        $recordType  = trim($_POST['record_type'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $recordedAt  = $_POST['recorded_at'] ?? date('Y-m-d');

        if ($studentId && $recordType) {
            $db->prepare(
                'INSERT INTO medical_records (student_id, record_type, description, recorded_by, recorded_at)
                 VALUES (?,?,?,?,?)'
            )->execute([$studentId, $recordType, $description ?: null, $user['id'], $recordedAt]);
            logActivity($user['id'], 'medical_record_add', "Added medical record for student #$studentId");
            setFlash('success', 'Medical record added.');
        } else {
            setFlash('danger', 'Student and record type are required.');
        }
    }

    if ($action === 'delete_record') {
        $id = (int)$_POST['record_id'];
        $db->prepare('DELETE FROM medical_records WHERE id=?')->execute([$id]);
        setFlash('success', 'Record deleted.');
    }

    redirect('/student-affairs/medical-records.php' . (isset($_GET['student_id']) ? '?student_id='.(int)$_GET['student_id'] : ''));
}

$studentId = (int)($_GET['student_id'] ?? 0);
$search    = trim($_GET['q'] ?? '');

// All students for dropdown
$allStudents = $db->query(
    'SELECT s.id AS student_id, u.name, u.user_id AS roll, c.name AS class_name
     FROM students s
     JOIN users u ON u.id=s.user_id
     JOIN classes c ON c.id=s.class_id
     WHERE u.status="active" ORDER BY c.name, u.name'
)->fetchAll();

// Records to show
$sql = 'SELECT mr.*, u.name AS student_name, u.user_id AS roll_no, c.name AS class_name,
               ru.name AS recorded_by_name
        FROM medical_records mr
        JOIN students s  ON s.id = mr.student_id
        JOIN users u     ON u.id = s.user_id
        JOIN classes c   ON c.id = s.class_id
        JOIN users ru    ON ru.id = mr.recorded_by
        WHERE 1';
$params = [];
if ($studentId) { $sql .= ' AND mr.student_id = ?'; $params[] = $studentId; }
if ($search) {
    $sql    .= ' AND (u.name LIKE ? OR mr.record_type LIKE ?)';
    $like    = "%$search%";
    $params[] = $like; $params[] = $like;
}
$sql .= ' ORDER BY mr.recorded_at DESC, mr.created_at DESC';
$st = $db->prepare($sql);
$st->execute($params);
$records = $st->fetchAll();

// Selected student info
$selectedStudent = null;
if ($studentId) {
    $ssSt = $db->prepare('SELECT s.*, u.name, u.user_id AS roll, c.name AS class_name FROM students s JOIN users u ON u.id=s.user_id JOIN classes c ON c.id=s.class_id WHERE s.id=?');
    $ssSt->execute([$studentId]);
    $selectedStudent = $ssSt->fetch();
}

pageHead('Medical Records', 'student_affairs');
$links = getStudentAffairsLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('student_affairs', 'medical', $links, $user); ?>
<div class="main-area">
<?php topbar('Medical Records', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- ILC branding strip -->
<div class="d-flex align-items-center gap-3 mb-4 p-3" style="background:linear-gradient(90deg,#fdf2f8,#fce7f3);border-radius:10px;border:1px solid #fbcfe8;">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#be185d;letter-spacing:.8px;text-transform:uppercase">Student Affairs Office</div>
    <div style="font-size:.8rem;color:#475569">Inclusive Learning Centre &mdash; Bahria Model College Bin Qasim</div>
  </div>
</div>

<div class="row g-3">
  <!-- Add record form -->
  <div class="col-lg-4">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-plus me-2"></i>Add Medical Record</div>
      <div style="padding:16px">
        <form method="POST">
          <input type="hidden" name="action" value="add_record">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Student <span class="text-danger">*</span></label>
            <select name="student_id" class="form-select form-select-sm" required>
              <option value="">Select student…</option>
              <?php foreach ($allStudents as $s): ?>
              <option value="<?= $s['student_id'] ?>" <?= $studentId==$s['student_id']?'selected':'' ?>>
                <?= h($s['name']) ?> (<?= h($s['roll']) ?> · <?= h($s['class_name']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Record Type <span class="text-danger">*</span></label>
            <input type="text" name="record_type" class="form-control form-control-sm"
                   placeholder="e.g. Vaccination, Allergy, Physical Exam…" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Description</label>
            <textarea name="description" class="form-control form-control-sm" rows="3"
                      placeholder="Details, medications, notes…"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Date</label>
            <input type="date" name="recorded_at" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
          </div>
          <button type="submit" class="btn btn-sm btn-primary w-100">
            <i class="fas fa-plus me-1"></i>Add Record
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Records list -->
  <div class="col-lg-8">
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-notes-medical me-2"></i>Records (<?= count($records) ?>)</span>
        <form method="GET" class="d-flex gap-2">
          <select name="student_id" class="form-select form-select-sm" style="width:200px" onchange="this.form.submit()">
            <option value="0">All students</option>
            <?php foreach ($allStudents as $s): ?>
            <option value="<?= $s['student_id'] ?>" <?= $studentId==$s['student_id']?'selected':'' ?>><?= h($s['name']) ?> (<?= h($s['class_name']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm"
                 placeholder="Search…" style="width:130px">
          <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
          <?php if ($studentId||$search): ?><a href="<?= url('/student-affairs/medical-records.php') ?>" class="btn btn-sm btn-outline-danger">Clear</a><?php endif; ?>
        </form>
      </div>

      <?php if (empty($records)): ?>
      <div style="padding:40px;text-align:center;color:var(--t2);font-size:.85rem">No medical records found.</div>
      <?php else: ?>
      <?php foreach ($records as $r): ?>
      <div style="padding:14px 16px;border-bottom:1px solid var(--border)">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="fw-semibold" style="font-size:.9rem"><?= h($r['record_type']) ?></div>
            <div style="font-size:.78rem;color:var(--t2)">
              <strong><?= h($r['student_name']) ?></strong> · <?= h($r['class_name']) ?>
              · <?= date('d M Y', strtotime($r['recorded_at'])) ?>
              · Recorded by <?= h($r['recorded_by_name']) ?>
            </div>
            <?php if ($r['description']): ?>
            <div style="font-size:.82rem;margin-top:4px;color:var(--t1)"><?= nl2br(h($r['description'])) ?></div>
            <?php endif; ?>
          </div>
          <form method="POST" class="d-inline ms-2" onsubmit="return confirm('Delete this record?')">
            <input type="hidden" name="action" value="delete_record">
            <input type="hidden" name="record_id" value="<?= $r['id'] ?>">
            <button class="btn btn-xs btn-outline-danger" style="font-size:.72rem;padding:2px 7px">Del</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

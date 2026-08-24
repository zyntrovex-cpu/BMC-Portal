<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp');
$db   = getDB();

$studentId = (int)($_GET['id'] ?? 0);
if (!$studentId) { setFlash('danger','Invalid student.'); redirect('/portal/ilc/students.php'); }

// Fetch student — must be ILC class
$stSt = $db->prepare(
    'SELECT s.*, u.name, u.user_id, u.email, u.status,
            c.name AS class_name, h.name AS house_name, h.color AS house_color
     FROM students s
     JOIN users u ON u.id = s.user_id
     JOIN classes c ON c.id = s.class_id
     LEFT JOIN houses h ON h.id = s.house_id
     WHERE s.id = ? AND c.is_ilc = 1'
);
$stSt->execute([$studentId]);
$student = $stSt->fetch();
if (!$student) { setFlash('danger','Student not found in ILC.'); redirect('/portal/ilc/students.php'); }

// ── Handle all POST actions ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_disability') {
        $subtypeId = (int)($_POST['subtype_id'] ?? 0);
        $notes     = trim($_POST['notes'] ?? '');
        if ($subtypeId) {
            try {
                $db->prepare('INSERT IGNORE INTO student_disabilities (student_id, subtype_id, notes, recorded_by) VALUES (?,?,?,?)')
                   ->execute([$studentId, $subtypeId, $notes ?: null, $user['id']]);
                logActivity($user['id'], 'disability_add', "Added disability subtype #$subtypeId to student #$studentId");
                setFlash('success', 'Disability record added.');
            } catch (Exception $e) {
                setFlash('danger', 'Could not add record: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'remove_disability') {
        $disId = (int)($_POST['dis_id'] ?? 0);
        if ($disId) {
            $db->prepare('DELETE FROM student_disabilities WHERE id = ? AND student_id = ?')
               ->execute([$disId, $studentId]);
            setFlash('success', 'Record removed.');
        }
    } elseif ($action === 'update_notes') {
        $disId = (int)($_POST['dis_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        if ($disId) {
            $db->prepare('UPDATE student_disabilities SET notes = ? WHERE id = ? AND student_id = ?')
               ->execute([$notes ?: null, $disId, $studentId]);
            setFlash('success', 'Notes updated.');
        }
    } elseif ($action === 'edit_student') {
        // Biodata column flags (detected below after SHOW COLUMNS — duplicated here so
        // the handler runs inside the POST block before the redirect)
        $_sc          = array_flip($db->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN));
        $name        = trim($_POST['name']         ?? '');
        $email       = trim($_POST['email']        ?? '');
        $newClassId  = (int)($_POST['class_id']    ?? 0) ?: null;
        $rollNo      = trim($_POST['roll_no']      ?? '');
        $gender      = $_POST['gender']            ?? '';
        $dob         = $_POST['dob']               ?? '';
        $cnic        = trim($_POST['cnic']         ?? '');
        $phone       = trim($_POST['phone']        ?? '');
        $address     = trim($_POST['address']      ?? '');
        $parentPhone = trim($_POST['parent_phone'] ?? '');
        $parentName  = trim($_POST['parent_name']  ?? '');
        $fatherName  = trim($_POST['father_name']  ?? '');
        $parentEmail = trim($_POST['parent_email'] ?? '');
        $houseId     = (int)($_POST['house_id']    ?? 0) ?: null;
        if ($name) {
            $db->prepare('UPDATE users SET name=?, email=? WHERE id=?')
               ->execute([$name, $email ?: null, $student['user_id']]);
            $setParts = ['roll_no=?','class_id=?','gender=?','cnic=?','phone=?','address=?','parent_phone=?','parent_name=?'];
            $setVals  = [$rollNo ?: null, $newClassId, $gender ?: null, $cnic ?: null, $phone ?: null, $address ?: null, $parentPhone ?: null, $parentName ?: null];
            if ($dob)                       { $setParts[] = 'dob=?';                 $setVals[] = $dob; }
            if (isset($_sc['father_name'])) { $setParts[] = 'father_name=?';         $setVals[] = $fatherName  ?: null; }
            if (isset($_sc['parent_email'])){ $setParts[] = 'parent_email=?';        $setVals[] = $parentEmail ?: null; }
            if (isset($_sc['house_id']))    { $setParts[] = 'house_id=?';            $setVals[] = $houseId; }
            foreach (['gr_no','kuickpay_id','category','academic_group','domicile','permanent_address',
                      'emergency_phone','whatsapp_no','religion','sect','blood_group','last_school',
                      'nationality','father_occupation','documents_submitted','medical_info',
                      'skills','sports','awards'] as $col) {
                if (isset($_sc[$col])) { $setParts[] = "$col=?"; $setVals[] = trim($_POST[$col] ?? '') ?: null; }
            }
            if (isset($_sc['child_order'])) {
                $setParts[] = 'child_order=?';
                $setVals[]  = (trim($_POST['child_order'] ?? '') !== '') ? (int)$_POST['child_order'] : null;
            }
            $setVals[] = $studentId;
            $db->prepare('UPDATE students SET ' . implode(',', $setParts) . ' WHERE id=?')->execute($setVals);
            logActivity($user['id'], 'student_edit', "ILC VP edited student #$studentId");
            setFlash('success', 'Student biodata updated.');
        }
    }

    redirect('/portal/ilc/student-profile.php?id=' . $studentId);
}

// Existing disability records
$disRecords = $db->prepare(
    'SELECT sd.*, dc.name AS cat_name, dst.name AS subtype_name, u.name AS recorded_by_name
     FROM student_disabilities sd
     JOIN disability_subtypes dst ON dst.id = sd.subtype_id
     JOIN disability_categories dc ON dc.id = dst.category_id
     JOIN users u ON u.id = sd.recorded_by
     WHERE sd.student_id = ?
     ORDER BY dc.name, dst.name'
);
$disRecords->execute([$studentId]);
$disabilities = $disRecords->fetchAll();

// Detect biodata columns for the edit modal display
$_stuCols     = array_flip($db->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN));
$hasHouseId     = isset($_stuCols['house_id']);
$hasFatherName  = isset($_stuCols['father_name']);
$hasParentEmail = isset($_stuCols['parent_email']);
$hasGrNo        = isset($_stuCols['gr_no']);
$hasKuickpayId  = isset($_stuCols['kuickpay_id']);
$hasCategory    = isset($_stuCols['category']);
$hasAcadGroup   = isset($_stuCols['academic_group']);
$hasChildOrder  = isset($_stuCols['child_order']);
$hasDomicile    = isset($_stuCols['domicile']);
$hasPermAddr    = isset($_stuCols['permanent_address']);
$hasEmergPhone  = isset($_stuCols['emergency_phone']);
$hasWhatsapp    = isset($_stuCols['whatsapp_no']);
$hasReligion    = isset($_stuCols['religion']);
$hasSect        = isset($_stuCols['sect']);
$hasBloodGroup  = isset($_stuCols['blood_group']);
$hasLastSchool  = isset($_stuCols['last_school']);
$hasNationality = isset($_stuCols['nationality']);
$hasFatherOcc   = isset($_stuCols['father_occupation']);
$hasDocsSub     = isset($_stuCols['documents_submitted']);
$hasMedicalInfo = isset($_stuCols['medical_info']);
$hasSkills      = isset($_stuCols['skills']);
$hasSports      = isset($_stuCols['sports']);
$hasAwards      = isset($_stuCols['awards']);

// All categories + subtypes for the add form
$categories = $db->query(
    'SELECT dc.id AS cat_id, dc.name AS cat_name, dst.id AS sub_id, dst.name AS sub_name
     FROM disability_categories dc
     JOIN disability_subtypes dst ON dst.category_id = dc.id
     ORDER BY dc.name, dst.name'
)->fetchAll();

// Group subtypes by category for the select
$grouped = [];
foreach ($categories as $row) {
    $grouped[$row['cat_name']][] = $row;
}

$classes = getAllClasses();
$houses  = $db->query('SELECT id, name, color FROM houses ORDER BY name')->fetchAll();

pageHead('Student Profile — ILC', 'ilc_vp');
$links = getIlcLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'students', $links, $user); ?>
<div class="main-area">
<?php topbar('Student Profile', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="row g-3">
  <!-- Profile card -->
  <div class="col-lg-4">
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-user me-2"></i>Student Info</div>
      <div style="padding:16px">
        <div class="text-center mb-3">
          <div style="width:72px;height:72px;border-radius:50%;background:#0891b2;color:#fff;font-size:1.6rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto">
            <?= strtoupper(substr($student['name'], 0, 1)) ?>
          </div>
          <div class="fw-bold mt-2"><?= h($student['name']) ?></div>
          <div style="font-size:.8rem;color:var(--t2)"><?= h($student['user_id']) ?></div>
          <?php if ($student['house_name']): ?>
          <span class="badge mt-1" style="background:<?= h($student['house_color']) ?>"><?= h($student['house_name']) ?></span>
          <?php endif; ?>
        </div>
        <table class="table table-sm" style="font-size:.82rem">
          <tr><td class="text-muted">Roll No</td><td class="fw-semibold"><?= h($student['roll_no']) ?></td></tr>
          <tr><td class="text-muted">Class</td><td><span class="badge bg-secondary"><?= h($student['class_name']) ?></span></td></tr>
          <tr><td class="text-muted">DOB</td><td><?= $student['dob'] ? date('d M Y', strtotime($student['dob'])) : '—' ?></td></tr>
          <tr><td class="text-muted">Phone</td><td><?= h($student['phone'] ?: '—') ?></td></tr>
          <tr><td class="text-muted">Parent</td><td><?= h($student['parent_name'] ?: '—') ?></td></tr>
          <tr><td class="text-muted">Parent Ph.</td><td><?= h($student['parent_phone'] ?: '—') ?></td></tr>
          <tr><td class="text-muted">Email</td><td><?= h($student['email'] ?: '—') ?></td></tr>
        </table>
        <button class="btn btn-sm btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#editBioModal">
          <i class="fas fa-edit me-1"></i>Edit Biodata
        </button>
        <a href="<?= url('/portal/ilc/students.php') ?>" class="btn btn-sm btn-outline-secondary w-100">
          <i class="fas fa-arrow-left me-1"></i>Back to List
        </a>
      </div>
    </div>
  </div>

  <!-- Disability records -->
  <div class="col-lg-8">
    <!-- Existing records -->
    <div class="sec-card mb-3">
      <div class="sec-card-header d-flex justify-content-between">
        <span><i class="fas fa-heartbeat me-2"></i>Disability Records (<?= count($disabilities) ?>)</span>
      </div>
      <?php if (empty($disabilities)): ?>
      <div style="padding:20px;text-align:center;color:var(--t2);font-size:.85rem">No disability records assigned yet.</div>
      <?php else: ?>
      <div style="padding:12px 16px">
        <?php foreach ($disabilities as $d): ?>
        <div class="mb-3 p-3" style="background:#f7f9fb;border-radius:8px;border:1px solid var(--border)">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <span class="badge mb-1" style="background:#0891b2;font-size:.72rem"><?= h($d['cat_name']) ?></span>
              <div class="fw-semibold" style="font-size:.9rem"><?= h($d['subtype_name']) ?></div>
              <div style="font-size:.75rem;color:var(--t2)">Recorded by <?= h($d['recorded_by_name']) ?> · <?= fDate($d['created_at']) ?></div>
            </div>
            <form method="POST" class="d-inline" onsubmit="return confirm('Remove this record?')">
              <input type="hidden" name="action" value="remove_disability">
              <input type="hidden" name="dis_id" value="<?= $d['id'] ?>">
              <button class="btn btn-xs btn-outline-danger" style="font-size:.72rem;padding:2px 7px">Remove</button>
            </form>
          </div>
          <!-- Notes -->
          <form method="POST" class="mt-2 d-flex gap-2">
            <input type="hidden" name="action" value="update_notes">
            <input type="hidden" name="dis_id" value="<?= $d['id'] ?>">
            <input type="text" name="notes" class="form-control form-control-sm" style="font-size:.78rem"
                   placeholder="Notes / accommodations…" value="<?= h($d['notes'] ?? '') ?>">
            <button class="btn btn-sm btn-outline-primary" style="white-space:nowrap;font-size:.78rem">Save</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Add disability form -->
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-plus me-2"></i>Add Disability Record</div>
      <div style="padding:16px">
        <form method="POST" class="row g-2">
          <input type="hidden" name="action" value="add_disability">
          <div class="col-md-6">
            <label class="form-label fw-semibold" style="font-size:.82rem">Category → Subtype <span class="text-danger">*</span></label>
            <select name="subtype_id" class="form-select form-select-sm" required>
              <option value="">— Select subtype —</option>
              <?php foreach ($grouped as $catName => $subtypes): ?>
              <optgroup label="<?= h($catName) ?>">
                <?php foreach ($subtypes as $sub): ?>
                <option value="<?= $sub['sub_id'] ?>"><?= h($sub['sub_name']) ?></option>
                <?php endforeach; ?>
              </optgroup>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold" style="font-size:.82rem">Notes / Accommodations</label>
            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional…">
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-sm btn-primary">
              <i class="fas fa-plus me-1"></i>Add Record
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

</div></div></div>

<!-- ── Edit Biodata Modal ─────────────────────────────────────── -->
<div class="modal fade" id="editBioModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="edit_student">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Student Biodata — <?= h($student['name']) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="font-size:.84rem">

          <!-- Section 1: Account & Class -->
          <div class="mb-3 pb-2" style="border-bottom:1px solid var(--border)">
            <div class="fw-bold mb-2" style="font-size:.78rem;color:var(--t2);text-transform:uppercase;letter-spacing:.5px">Account &amp; Class</div>
            <div class="row g-2">
              <div class="col-md-4"><label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control form-control-sm" value="<?= h($student['name']) ?>" required></div>
              <div class="col-md-4"><label class="form-label fw-semibold">Email</label>
                <input type="email" name="email" class="form-control form-control-sm" value="<?= h($student['email'] ?? '') ?>"></div>
              <div class="col-md-2"><label class="form-label fw-semibold">Roll No</label>
                <input type="text" name="roll_no" class="form-control form-control-sm" value="<?= h($student['roll_no']) ?>"></div>
              <div class="col-md-2"><label class="form-label fw-semibold">Class</label>
                <select name="class_id" class="form-select form-select-sm">
                  <option value="">— none —</option>
                  <?php foreach ($classes as $c): ?>
                  <option value="<?= $c['id'] ?>" <?= ($student['class_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?><?= $c['is_ilc'] ? ' (ILC)' : ($c['is_montessori'] ? ' (Montessori)' : '') ?></option>
                  <?php endforeach; ?>
                </select></div>
            </div>
          </div>

          <!-- Section 2: Registration -->
          <?php if ($hasGrNo || $hasKuickpayId || $hasCategory || $hasAcadGroup || $hasLastSchool): ?>
          <div class="mb-3 pb-2" style="border-bottom:1px solid var(--border)">
            <div class="fw-bold mb-2" style="font-size:.78rem;color:var(--t2);text-transform:uppercase;letter-spacing:.5px">Registration</div>
            <div class="row g-2">
              <?php if ($hasGrNo): ?>
              <div class="col-md-3"><label class="form-label fw-semibold">GR No</label>
                <input type="text" name="gr_no" class="form-control form-control-sm" value="<?= h($student['gr_no'] ?? '') ?>"></div>
              <?php endif; ?>
              <?php if ($hasKuickpayId): ?>
              <div class="col-md-3"><label class="form-label fw-semibold">Kuickpay ID</label>
                <input type="text" name="kuickpay_id" class="form-control form-control-sm" value="<?= h($student['kuickpay_id'] ?? '') ?>"></div>
              <?php endif; ?>
              <?php if ($hasCategory): ?>
              <div class="col-md-3"><label class="form-label fw-semibold">Category</label>
                <select name="category" class="form-select form-select-sm">
                  <option value="">—</option>
                  <?php foreach (['Civilian','CPO','Sailor','Staff Child','Day Scholar','Boarder'] as $opt): ?>
                  <option <?= ($student['category'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select></div>
              <?php endif; ?>
              <?php if ($hasAcadGroup): ?>
              <div class="col-md-3"><label class="form-label fw-semibold">Academic Group</label>
                <select name="academic_group" class="form-select form-select-sm">
                  <option value="">—</option>
                  <?php foreach (['Pre-Medical','Pre-Engineering','Commerce','Arts','ICS','ILC'] as $opt): ?>
                  <option <?= ($student['academic_group'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select></div>
              <?php endif; ?>
              <?php if ($hasLastSchool): ?>
              <div class="col-md-6"><label class="form-label fw-semibold">Last School</label>
                <input type="text" name="last_school" class="form-control form-control-sm" value="<?= h($student['last_school'] ?? '') ?>"></div>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Section 3: Personal -->
          <div class="mb-3 pb-2" style="border-bottom:1px solid var(--border)">
            <div class="fw-bold mb-2" style="font-size:.78rem;color:var(--t2);text-transform:uppercase;letter-spacing:.5px">Personal Info</div>
            <div class="row g-2">
              <div class="col-md-2"><label class="form-label fw-semibold">DOB</label>
                <input type="date" name="dob" class="form-control form-control-sm" value="<?= h($student['dob'] ?? '') ?>"></div>
              <div class="col-md-2"><label class="form-label fw-semibold">Gender</label>
                <select name="gender" class="form-select form-select-sm">
                  <option value="">—</option>
                  <?php foreach (['male','female','other'] as $g): ?>
                  <option <?= ($student['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                  <?php endforeach; ?>
                </select></div>
              <div class="col-md-2"><label class="form-label fw-semibold">CNIC</label>
                <input type="text" name="cnic" class="form-control form-control-sm" value="<?= h($student['cnic'] ?? '') ?>"></div>
              <?php if ($hasChildOrder): ?>
              <div class="col-md-2"><label class="form-label fw-semibold">Child Order</label>
                <input type="number" name="child_order" min="1" class="form-control form-control-sm" value="<?= h($student['child_order'] ?? '') ?>"></div>
              <?php endif; ?>
              <?php if ($hasNationality): ?>
              <div class="col-md-2"><label class="form-label fw-semibold">Nationality</label>
                <input type="text" name="nationality" class="form-control form-control-sm" value="<?= h($student['nationality'] ?? '') ?>"></div>
              <?php endif; ?>
              <?php if ($hasDomicile): ?>
              <div class="col-md-2"><label class="form-label fw-semibold">Domicile</label>
                <input type="text" name="domicile" class="form-control form-control-sm" value="<?= h($student['domicile'] ?? '') ?>"></div>
              <?php endif; ?>
              <?php if ($hasReligion): ?>
              <div class="col-md-3"><label class="form-label fw-semibold">Religion</label>
                <input type="text" name="religion" class="form-control form-control-sm" value="<?= h($student['religion'] ?? '') ?>"></div>
              <?php endif; ?>
              <?php if ($hasSect): ?>
              <div class="col-md-3"><label class="form-label fw-semibold">Sect</label>
                <input type="text" name="sect" class="form-control form-control-sm" value="<?= h($student['sect'] ?? '') ?>"></div>
              <?php endif; ?>
              <?php if ($hasBloodGroup): ?>
              <div class="col-md-2"><label class="form-label fw-semibold">Blood Group</label>
                <select name="blood_group" class="form-select form-select-sm">
                  <option value="">—</option>
                  <?php foreach (['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $bg): ?>
                  <option <?= ($student['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                  <?php endforeach; ?>
                </select></div>
              <?php endif; ?>
              <?php if ($hasHouseId): ?>
              <div class="col-md-2"><label class="form-label fw-semibold">House</label>
                <select name="house_id" class="form-select form-select-sm">
                  <option value="">— none —</option>
                  <?php foreach ($houses as $h): ?>
                  <option value="<?= $h['id'] ?>" <?= ($student['house_id'] ?? null) == $h['id'] ? 'selected' : '' ?>><?= htmlspecialchars($h['name']) ?></option>
                  <?php endforeach; ?>
                </select></div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Section 4: Contact -->
          <div class="mb-3 pb-2" style="border-bottom:1px solid var(--border)">
            <div class="fw-bold mb-2" style="font-size:.78rem;color:var(--t2);text-transform:uppercase;letter-spacing:.5px">Contact</div>
            <div class="row g-2">
              <div class="col-md-3"><label class="form-label fw-semibold">Phone</label>
                <input type="text" name="phone" class="form-control form-control-sm" value="<?= h($student['phone'] ?? '') ?>"></div>
              <?php if ($hasWhatsapp): ?>
              <div class="col-md-3"><label class="form-label fw-semibold">WhatsApp No</label>
                <input type="text" name="whatsapp_no" class="form-control form-control-sm" value="<?= h($student['whatsapp_no'] ?? '') ?>"></div>
              <?php endif; ?>
              <?php if ($hasEmergPhone): ?>
              <div class="col-md-3"><label class="form-label fw-semibold">Emergency Phone</label>
                <input type="text" name="emergency_phone" class="form-control form-control-sm" value="<?= h($student['emergency_phone'] ?? '') ?>"></div>
              <?php endif; ?>
              <div class="col-md-6"><label class="form-label fw-semibold">Present Address</label>
                <input type="text" name="address" class="form-control form-control-sm" value="<?= h($student['address'] ?? '') ?>"></div>
              <?php if ($hasPermAddr): ?>
              <div class="col-md-6"><label class="form-label fw-semibold">Permanent Address</label>
                <input type="text" name="permanent_address" class="form-control form-control-sm" value="<?= h($student['permanent_address'] ?? '') ?>"></div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Section 5: Guardian -->
          <div class="mb-3 pb-2" style="border-bottom:1px solid var(--border)">
            <div class="fw-bold mb-2" style="font-size:.78rem;color:var(--t2);text-transform:uppercase;letter-spacing:.5px">Guardian / Parent</div>
            <div class="row g-2">
              <div class="col-md-3"><label class="form-label fw-semibold">Parent/Guardian Name</label>
                <input type="text" name="parent_name" class="form-control form-control-sm" value="<?= h($student['parent_name'] ?? '') ?>"></div>
              <?php if ($hasFatherName): ?>
              <div class="col-md-3"><label class="form-label fw-semibold">Father's Name</label>
                <input type="text" name="father_name" class="form-control form-control-sm" value="<?= h($student['father_name'] ?? '') ?>"></div>
              <?php endif; ?>
              <?php if ($hasFatherOcc): ?>
              <div class="col-md-3"><label class="form-label fw-semibold">Father's Occupation</label>
                <input type="text" name="father_occupation" class="form-control form-control-sm" value="<?= h($student['father_occupation'] ?? '') ?>"></div>
              <?php endif; ?>
              <div class="col-md-3"><label class="form-label fw-semibold">Parent Phone</label>
                <input type="text" name="parent_phone" class="form-control form-control-sm" value="<?= h($student['parent_phone'] ?? '') ?>"></div>
              <?php if ($hasParentEmail): ?>
              <div class="col-md-3"><label class="form-label fw-semibold">Parent Email</label>
                <input type="email" name="parent_email" class="form-control form-control-sm" value="<?= h($student['parent_email'] ?? '') ?>"></div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Section 6: Documents -->
          <?php if ($hasDocsSub || $hasMedicalInfo): ?>
          <div class="mb-3 pb-2" style="border-bottom:1px solid var(--border)">
            <div class="fw-bold mb-2" style="font-size:.78rem;color:var(--t2);text-transform:uppercase;letter-spacing:.5px">Documents &amp; Health</div>
            <div class="row g-2">
              <?php if ($hasDocsSub): ?>
              <div class="col-md-6"><label class="form-label fw-semibold">Documents Submitted</label>
                <input type="text" name="documents_submitted" class="form-control form-control-sm" placeholder="e.g. CNIC, Marksheet, TC"
                       value="<?= h($student['documents_submitted'] ?? '') ?>"></div>
              <?php endif; ?>
              <?php if ($hasMedicalInfo): ?>
              <div class="col-md-6"><label class="form-label fw-semibold">Medical Info / Conditions</label>
                <input type="text" name="medical_info" class="form-control form-control-sm" value="<?= h($student['medical_info'] ?? '') ?>"></div>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Section 7: Co-curricular -->
          <?php if ($hasSkills || $hasSports || $hasAwards): ?>
          <div class="mb-1">
            <div class="fw-bold mb-2" style="font-size:.78rem;color:var(--t2);text-transform:uppercase;letter-spacing:.5px">Co-curricular</div>
            <div class="row g-2">
              <?php if ($hasSkills): ?>
              <div class="col-md-4"><label class="form-label fw-semibold">Skills</label>
                <input type="text" name="skills" class="form-control form-control-sm" placeholder="Comma-separated"
                       value="<?= h($student['skills'] ?? '') ?>"></div>
              <?php endif; ?>
              <?php if ($hasSports): ?>
              <div class="col-md-4"><label class="form-label fw-semibold">Sports</label>
                <input type="text" name="sports" class="form-control form-control-sm" placeholder="Comma-separated"
                       value="<?= h($student['sports'] ?? '') ?>"></div>
              <?php endif; ?>
              <?php if ($hasAwards): ?>
              <div class="col-md-4"><label class="form-label fw-semibold">Awards</label>
                <input type="text" name="awards" class="form-control form-control-sm" placeholder="Comma-separated"
                       value="<?= h($student['awards'] ?? '') ?>"></div>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

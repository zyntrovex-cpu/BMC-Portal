<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('ilc_vp');
$db   = getDB();

$studentId = (int)($_GET['id'] ?? 0);
if (!$studentId) { setFlash('danger','Invalid student.'); redirect('/ilc/students.php'); }

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
if (!$student) { setFlash('danger','Student not found in ILC.'); redirect('/ilc/students.php'); }

// ── Handle add/remove disability ──────────────────────────────────
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
    }

    if ($action === 'remove_disability') {
        $disId = (int)($_POST['dis_id'] ?? 0);
        if ($disId) {
            $db->prepare('DELETE FROM student_disabilities WHERE id = ? AND student_id = ?')
               ->execute([$disId, $studentId]);
            setFlash('success', 'Record removed.');
        }
    }

    if ($action === 'update_notes') {
        $disId = (int)($_POST['dis_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        if ($disId) {
            $db->prepare('UPDATE student_disabilities SET notes = ? WHERE id = ? AND student_id = ?')
               ->execute([$notes ?: null, $disId, $studentId]);
            setFlash('success', 'Notes updated.');
        }
    }

    redirect('/ilc/student-profile.php?id=' . $studentId);
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
        <a href="/ilc/students.php" class="btn btn-sm btn-outline-secondary w-100">
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

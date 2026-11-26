<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('vp_montessori');
$db   = getDB();

// ── Upload directory for class photos ─────────────────────────────
$photoDir = __DIR__ . '/../assets/uploads/montessori-class-photos/';
if (!is_dir($photoDir)) @mkdir($photoDir, 0755, true);

// ── Lazy-create remarks table ─────────────────────────────────────
$remarksTableExists = false;
try { $db->query('SELECT 1 FROM montessori_class_remarks LIMIT 0'); $remarksTableExists = true; } catch (Exception $e) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS montessori_class_remarks (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            class_id   INT UNSIGNED NOT NULL,
            remarks    TEXT NULL,
            photos     JSON NULL,
            created_by INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_class (class_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $remarksTableExists = true;
    } catch (Exception $e2) {}
}

// ── POST handler ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']   ?? '';
    $classId = (int)($_POST['class_id'] ?? 0);

    // Validate that this class belongs to Montessori wing
    $wingOk = false;
    if ($classId) {
        $wcSt = $db->prepare('SELECT wing FROM classes WHERE id=?');
        $wcSt->execute([$classId]);
        $wcRow = $wcSt->fetch();
        $wingOk = $wcRow && $wcRow['wing'] === 'montessori';
    }

    // ── Class teacher assignment ──────────────────────────────────
    if ($action === 'assign_class_teacher' && $classId && $wingOk) {
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        if ($teacherId) {
            $db->prepare(
                'INSERT INTO class_teacher_assignments (class_id, teacher_id) VALUES (?,?)
                 ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)'
            )->execute([$classId, $teacherId]);
            logActivity($user['id'], 'monte_class_teacher', "Assigned class teacher #$teacherId to class #$classId");
            setFlash('success', 'Class teacher assigned.');
        } else {
            setFlash('danger', 'Please select a teacher.');
        }
        redirect("/portal/vp-montessori/class-assignments.php?class_id=$classId&tab=class-teacher");
    }

    if ($action === 'remove_class_teacher' && $classId && $wingOk) {
        $db->prepare('DELETE FROM class_teacher_assignments WHERE class_id=?')->execute([$classId]);
        logActivity($user['id'], 'monte_class_teacher_remove', "Removed class teacher from class #$classId");
        setFlash('success', 'Class teacher removed.');
        redirect("/portal/vp-montessori/class-assignments.php?class_id=$classId&tab=class-teacher");
    }

    // ── Subject teacher assignment ────────────────────────────────
    if ($action === 'assign_subject' && $classId && $wingOk) {
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        if ($subjectId) {
            $db->prepare(
                'INSERT INTO class_subjects (class_id, subject_id, teacher_id) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)'
            )->execute([$classId, $subjectId, $teacherId ?: null]);
            logActivity($user['id'], 'monte_subject_assign', "Assigned subject #$subjectId to class #$classId");
            setFlash('success', 'Subject / teacher assigned.');
        } else {
            setFlash('danger', 'Please select a subject.');
        }
        redirect("/portal/vp-montessori/class-assignments.php?class_id=$classId&tab=subjects");
    }

    if ($action === 'remove_subject') {
        $csId = (int)($_POST['cs_id'] ?? 0);
        if ($csId) {
            // Verify ownership
            $wcSt2 = $db->prepare('SELECT c.wing FROM class_subjects cs JOIN classes c ON cs.class_id=c.id WHERE cs.id=?');
            $wcSt2->execute([$csId]);
            $wcRow2 = $wcSt2->fetch();
            if ($wcRow2 && $wcRow2['wing'] === 'montessori') {
                $db->prepare('DELETE FROM class_subjects WHERE id=?')->execute([$csId]);
                logActivity($user['id'], 'monte_subject_remove', "Removed class_subject #$csId");
                setFlash('success', 'Subject removed.');
            }
        }
        redirect("/portal/vp-montessori/class-assignments.php?class_id=$classId&tab=subjects");
    }

    // ── Photos & Remarks ──────────────────────────────────────────
    if ($action === 'save_remarks' && $classId && $wingOk && $remarksTableExists) {
        $remarks = trim($_POST['remarks'] ?? '');

        // Handle photo uploads (up to 10)
        $photos = [];
        if (isset($_FILES['photos'])) {
            $photoAllowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
            $pf = $_FILES['photos'];
            $cnt = count((array)($pf['tmp_name'] ?? []));
            for ($pi = 0; $pi < min($cnt, 10); $pi++) {
                if (empty($pf['tmp_name'][$pi])) continue;
                $pmime = mime_content_type($pf['tmp_name'][$pi]);
                if (!isset($photoAllowed[$pmime])) continue;
                if ($pf['size'][$pi] > 5 * 1024 * 1024) continue;
                $pfn = 'mcph_' . bin2hex(random_bytes(8)) . '.' . $photoAllowed[$pmime];
                if (move_uploaded_file($pf['tmp_name'][$pi], $photoDir . $pfn)) {
                    $photos[] = $pfn;
                }
            }
        }

        if ($remarks !== '' || !empty($photos)) {
            $db->prepare(
                'INSERT INTO montessori_class_remarks (class_id, remarks, photos, created_by) VALUES (?,?,?,?)'
            )->execute([$classId, $remarks ?: null, $photos ? json_encode($photos) : null, $user['id']]);
            logActivity($user['id'], 'monte_remarks_save', "Saved remark for class #$classId with ".count($photos)." photos");
            setFlash('success', 'Remark' . (!empty($photos) ? ' and '.count($photos).' photo(s)' : '') . ' saved.');
        } else {
            setFlash('warning', 'Please enter a remark or upload at least one photo.');
        }
        redirect("/portal/vp-montessori/class-assignments.php?class_id=$classId&tab=remarks");
    }

    if ($action === 'delete_remark' && $remarksTableExists) {
        $remarkId = (int)($_POST['remark_id'] ?? 0);
        if ($remarkId) {
            $rmSt = $db->prepare('SELECT photos, class_id FROM montessori_class_remarks WHERE id=?');
            $rmSt->execute([$remarkId]);
            $rmRow = $rmSt->fetch();
            if ($rmRow) {
                if (!empty($rmRow['photos'])) {
                    foreach (json_decode($rmRow['photos'], true) ?? [] as $pfn) @unlink($photoDir . $pfn);
                }
                $db->prepare('DELETE FROM montessori_class_remarks WHERE id=?')->execute([$remarkId]);
                logActivity($user['id'], 'monte_remark_delete', "Deleted remark #$remarkId");
                setFlash('success', 'Entry deleted.');
            }
        }
        redirect("/portal/vp-montessori/class-assignments.php?class_id=$classId&tab=remarks");
    }

    if ($action === 'delete_photo') {
        $remarkId = (int)($_POST['remark_id'] ?? 0);
        $photoFn  = trim($_POST['photo_fn'] ?? '');
        if ($remarkId && $photoFn && $remarksTableExists) {
            $rmSt = $db->prepare('SELECT photos FROM montessori_class_remarks WHERE id=?');
            $rmSt->execute([$remarkId]);
            $rmRow = $rmSt->fetch();
            if ($rmRow) {
                $existingPhotos = json_decode($rmRow['photos'] ?? '[]', true) ?? [];
                $existingPhotos = array_values(array_filter($existingPhotos, fn($p) => $p !== $photoFn));
                @unlink($photoDir . $photoFn);
                $db->prepare('UPDATE montessori_class_remarks SET photos=? WHERE id=?')
                   ->execute([$existingPhotos ? json_encode($existingPhotos) : null, $remarkId]);
                setFlash('success', 'Photo deleted.');
            }
        }
        redirect("/portal/vp-montessori/class-assignments.php?class_id=$classId&tab=remarks");
    }

    redirect("/portal/vp-montessori/class-assignments.php" . ($classId ? "?class_id=$classId" : ''));
}

// ── Fetch ─────────────────────────────────────────────────────────
$classId    = (int)($_GET['class_id'] ?? 0);
$activeTab  = $_GET['tab'] ?? 'class-teacher';

// All Montessori classes
$classes = $db->query(
    'SELECT c.id, c.name, COUNT(s.id) AS student_count
     FROM classes c
     LEFT JOIN students s ON s.class_id = c.id
     WHERE c.is_montessori = 1
     GROUP BY c.id ORDER BY c.name'
)->fetchAll();

$viewClass = null;
if ($classId) {
    foreach ($classes as $c) { if ($c['id'] == $classId) { $viewClass = $c; break; } }
    if (!$viewClass) $classId = 0; // invalid / wrong wing
}

// Montessori teachers — list every active Montessori staff member
// (teachers + coordinators) by role OR wing. Previously used an INNER JOIN
// on `teachers`, which silently dropped any `montessori_teacher` user whose
// `teachers` row hadn't been backfilled yet; the backfill in
// migrations/montessori_staff_visibility.sql closes that gap but we also
// relax the query so it is immune to future drift.
$teachers = [];
try {
    $teachers = $db->query(
        "SELECT DISTINCT t.id AS teacher_id, u.name, u.id AS user_id,
                t.emp_id, u.role
           FROM users u
           LEFT JOIN teachers t ON t.user_id = u.id
          WHERE COALESCE(u.status,'active') = 'active'
            AND (
                 u.role IN ('montessori_teacher', 'wing_head')
                 OR (u.role = 'teacher' AND t.wing = 'montessori')
            )
            AND t.id IS NOT NULL     -- can only assign to users with a teachers row
          ORDER BY u.name"
    )->fetchAll();
} catch (Exception $e) { $teachers = []; }

// All subjects
$subjects = getAllSubjects();

// Current class teacher
$classTeacher = null;
if ($classId) {
    try {
        $ctSt = $db->prepare(
            'SELECT cta.teacher_id, u.name AS teacher_name
             FROM class_teacher_assignments cta
             JOIN teachers t ON t.id = cta.teacher_id
             JOIN users u ON u.id = t.user_id
             WHERE cta.class_id = ?'
        );
        $ctSt->execute([$classId]);
        $classTeacher = $ctSt->fetch() ?: null;
    } catch (Exception $e) {}
}

// Class subjects with teacher
$classSubjects = [];
if ($classId) {
    try {
        $csSt = $db->prepare(
            'SELECT cs.id AS cs_id, s.id AS subject_id, s.name AS subject_name, s.code,
                    t.id AS teacher_id, u.name AS teacher_name
             FROM class_subjects cs
             JOIN subjects s ON s.id = cs.subject_id
             LEFT JOIN teachers t ON t.id = cs.teacher_id
             LEFT JOIN users u ON u.id = t.user_id
             WHERE cs.class_id = ?
             ORDER BY s.name'
        );
        $csSt->execute([$classId]);
        $classSubjects = $csSt->fetchAll();
    } catch (Exception $e) {}
}

// Remarks & photos
$remarks = [];
if ($classId && $remarksTableExists) {
    try {
        $rmSt = $db->prepare(
            'SELECT r.*, u.name AS author_name
             FROM montessori_class_remarks r
             LEFT JOIN users u ON u.id = r.created_by
             WHERE r.class_id = ?
             ORDER BY r.created_at DESC'
        );
        $rmSt->execute([$classId]);
        $remarks = $rmSt->fetchAll();
    } catch (Exception $e) {}
}

// Subjects already assigned (for dropdown exclusion)
$assignedSubjectIds = array_column($classSubjects, 'subject_id');

pageHead('Class Assignments', 'vp_montessori');
$links = getVpMontessoriLinks();
?>
<div class="portal-wrap">
<?php sidebar('vp_montessori', 'class-assignments', $links, $user); ?>
<div class="main-area">
<?php topbar('Class Assignments', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Branding strip -->
<div class="d-flex align-items-center gap-3 mb-3 p-3"
     style="background:linear-gradient(90deg,#fff7ed,#fed7aa);border-radius:10px;border:1px solid #fdba74">
  <div style="width:40px;height:40px;border-radius:50%;background:#c2410c;color:#fff;font-size:1.2rem;
              display:flex;align-items:center;justify-content:center;flex-shrink:0">
    <i class="fas fa-school"></i>
  </div>
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#9a3412;letter-spacing:.8px;text-transform:uppercase">
      Class Assignments — Montessori Campus
    </div>
    <div style="font-size:.78rem;color:#78350f">
      Manage class teachers, subject teachers, and class photos &amp; remarks
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- ── Left: Class list ── -->
  <div class="col-lg-3">
    <div class="sec-card" style="position:sticky;top:70px">
      <div class="sec-card-header" style="background:linear-gradient(90deg,#9a3412,#c2410c);color:#fff">
        <i class="fas fa-chalkboard me-2"></i>Montessori Classes
      </div>
      <div style="padding:8px">
        <?php foreach ($classes as $c): ?>
        <a href="?class_id=<?= $c['id'] ?>&tab=<?= h($activeTab) ?>"
           class="d-flex justify-content-between align-items-center px-3 py-2 rounded mb-1 text-decoration-none
                  <?= $classId === (int)$c['id'] ? 'fw-bold' : '' ?>"
           style="font-size:.86rem;color:var(--t1);background:<?= $classId===(int)$c['id']?'#fff7ed':'transparent' ?>;
                  border:1px solid <?= $classId===(int)$c['id']?'#fdba74':'transparent' ?>">
          <span><?= h($c['name']) ?></span>
          <span class="badge" style="background:#fef3c7;color:#92400e;font-size:.68rem"><?= $c['student_count'] ?></span>
        </a>
        <?php endforeach; ?>
        <?php if (empty($classes)): ?>
        <div class="text-muted p-3" style="font-size:.83rem">No Montessori classes found.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Right: Tabs ── -->
  <div class="col-lg-9">
    <?php if (!$viewClass): ?>
    <div class="sec-card">
      <div style="padding:60px;text-align:center;color:var(--t2)">
        <i class="fas fa-chalkboard fa-2x mb-3 d-block opacity-20"></i>
        <p style="font-size:.88rem">Select a class from the left to manage assignments.</p>
      </div>
    </div>

    <?php else: ?>
    <!-- Class header -->
    <div class="d-flex align-items-center gap-3 mb-3 p-3 rounded"
         style="background:#fff7ed;border:1px solid #fed7aa">
      <div style="width:42px;height:42px;border-radius:50%;background:#c2410c;color:#fff;font-size:1.2rem;font-weight:700;
                  display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <?= strtoupper(substr($viewClass['name'], 0, 1)) ?>
      </div>
      <div>
        <div class="fw-bold" style="font-size:1rem"><?= h($viewClass['name']) ?></div>
        <div style="font-size:.78rem;color:#78350f">
          Montessori Wing &nbsp;·&nbsp; <?= $viewClass['student_count'] ?> student(s)
        </div>
      </div>
    </div>

    <!-- Tab nav -->
    <ul class="nav nav-tabs mb-0" id="assignTabs" style="border-bottom:2px solid #fdba74">
      <li class="nav-item">
        <a class="nav-link <?= $activeTab==='class-teacher'?'active':'' ?>"
           href="?class_id=<?= $classId ?>&tab=class-teacher"
           style="font-size:.85rem">
          <i class="fas fa-user-tie me-1"></i>Class Teacher
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab==='subjects'?'active':'' ?>"
           href="?class_id=<?= $classId ?>&tab=subjects"
           style="font-size:.85rem">
          <i class="fas fa-book me-1"></i>Subject Teachers
          <?php if (!empty($classSubjects)): ?>
          <span class="badge bg-secondary ms-1" style="font-size:.65rem"><?= count($classSubjects) ?></span>
          <?php endif; ?>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab==='remarks'?'active':'' ?>"
           href="?class_id=<?= $classId ?>&tab=remarks"
           style="font-size:.85rem">
          <i class="fas fa-images me-1"></i>Photos &amp; Remarks
          <?php if (!empty($remarks)): ?>
          <span class="badge bg-secondary ms-1" style="font-size:.65rem"><?= count($remarks) ?></span>
          <?php endif; ?>
        </a>
      </li>
    </ul>

    <div class="sec-card" style="border-radius:0 0 8px 8px;border-top:none">
      <div style="padding:20px">

      <?php if ($activeTab === 'class-teacher'): ?>
      <!-- ────────── TAB 1: Class Teacher ────────── -->
      <p style="font-size:.83rem;color:var(--t2);margin-bottom:16px">
        Assign one Class Teacher to <strong><?= h($viewClass['name']) ?></strong>.
        This teacher is responsible for the whole class.
      </p>

      <form method="POST" class="d-flex gap-2 flex-wrap align-items-end mb-4">
        <input type="hidden" name="action"   value="assign_class_teacher">
        <input type="hidden" name="class_id" value="<?= $classId ?>">
        <div>
          <label class="form-label fw-semibold" style="font-size:.82rem">
            Class Teacher <span class="text-danger">*</span>
          </label>
          <select name="teacher_id" class="form-select form-select-sm" required style="min-width:220px">
            <option value="">— Select Montessori teacher —</option>
            <?php foreach ($teachers as $t): ?>
            <option value="<?= $t['teacher_id'] ?>"
                    <?= ($classTeacher && $classTeacher['teacher_id'] == $t['teacher_id']) ? 'selected' : '' ?>>
              <?= h($t['name']) ?><?= $t['emp_id'] ? ' ('.$t['emp_id'].')' : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-sm btn-success">
          <i class="fas fa-user-check me-1"></i><?= $classTeacher ? 'Change Teacher' : 'Assign Teacher' ?>
        </button>
      </form>

      <?php if ($classTeacher): ?>
      <div class="d-flex align-items-center gap-3 p-3 rounded"
           style="background:#f0fdf4;border:1px solid #bbf7d0;max-width:420px">
        <div style="width:44px;height:44px;border-radius:50%;background:#15803d;color:#fff;font-size:1.1rem;
                    display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fas fa-user-tie"></i>
        </div>
        <div class="flex-grow-1">
          <div class="fw-semibold" style="font-size:.95rem"><?= h($classTeacher['teacher_name']) ?></div>
          <div style="font-size:.76rem;color:#16a34a">Class Teacher — <?= h($viewClass['name']) ?></div>
        </div>
        <form method="POST" class="d-inline" onsubmit="return confirm('Remove class teacher?')">
          <input type="hidden" name="action"   value="remove_class_teacher">
          <input type="hidden" name="class_id" value="<?= $classId ?>">
          <button class="btn btn-xs btn-outline-danger">
            <i class="fas fa-times me-1"></i>Remove
          </button>
        </form>
      </div>
      <?php else: ?>
      <div class="text-center py-4 text-muted">
        <i class="fas fa-user-slash fa-2x mb-2 d-block opacity-25"></i>
        <span style="font-size:.85rem">No class teacher assigned yet.</span>
      </div>
      <?php endif; ?>

      <?php elseif ($activeTab === 'subjects'): ?>
      <!-- ────────── TAB 2: Subject Teachers ────────── -->
      <p style="font-size:.83rem;color:var(--t2);margin-bottom:16px">
        Assign subject-specific teachers to <strong><?= h($viewClass['name']) ?></strong>.
        Multiple subjects can be linked with optional teachers.
      </p>

      <form method="POST" class="d-flex gap-2 flex-wrap align-items-end mb-4">
        <input type="hidden" name="action"   value="assign_subject">
        <input type="hidden" name="class_id" value="<?= $classId ?>">
        <div>
          <label class="form-label fw-semibold" style="font-size:.82rem">
            Subject <span class="text-danger">*</span>
          </label>
          <select name="subject_id" class="form-select form-select-sm" required style="min-width:170px">
            <option value="">— Select subject —</option>
            <?php foreach ($subjects as $s): ?>
            <option value="<?= $s['id'] ?>"
                    <?= in_array($s['id'], $assignedSubjectIds) ? 'style="color:#94a3b8"' : '' ?>>
              <?= h($s['name']) ?><?= $s['code'] ? ' ('.$s['code'].')' : '' ?>
              <?= in_array($s['id'], $assignedSubjectIds) ? ' ✓' : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label fw-semibold" style="font-size:.82rem">
            Teacher <small class="text-muted">(optional)</small>
          </label>
          <select name="teacher_id" class="form-select form-select-sm" style="min-width:200px">
            <option value="">— No teacher —</option>
            <?php foreach ($teachers as $t): ?>
            <option value="<?= $t['teacher_id'] ?>"
                    <?= ($classTeacher && $classTeacher['teacher_id'] == $t['teacher_id']) ? 'selected' : '' ?>>
              <?= h($t['name']) ?><?= $t['emp_id'] ? ' ('.$t['emp_id'].')' : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="fas fa-plus me-1"></i>Assign
        </button>
      </form>

      <?php if (empty($classSubjects)): ?>
      <div class="text-center py-4 text-muted">
        <i class="fas fa-book fa-2x mb-2 d-block opacity-25"></i>
        <span style="font-size:.85rem">No subjects assigned yet. Use the form above to add subjects.</span>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover table-sm mb-0" style="font-size:.84rem">
          <thead style="background:#f1f5f9">
            <tr>
              <th style="padding:8px 12px">Subject</th>
              <th style="padding:8px 12px">Teacher</th>
              <th style="padding:8px 12px;width:80px">Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($classSubjects as $cs): ?>
          <tr>
            <td style="padding:8px 12px">
              <span class="fw-semibold"><?= h($cs['subject_name']) ?></span>
              <?php if ($cs['code']): ?>
              <span class="text-muted" style="font-size:.76rem"> (<?= h($cs['code']) ?>)</span>
              <?php endif; ?>
            </td>
            <td style="padding:8px 12px">
              <?php if ($cs['teacher_name']): ?>
              <span class="badge" style="background:#dbeafe;color:#1e40af;font-size:.78rem">
                <i class="fas fa-user me-1"></i><?= h($cs['teacher_name']) ?>
              </span>
              <?php else: ?>
              <span class="text-muted" style="font-size:.78rem">— not assigned</span>
              <?php endif; ?>
            </td>
            <td style="padding:8px 12px;text-align:center">
              <form method="POST" class="d-inline"
                    onsubmit="return confirm('Remove this subject assignment?')">
                <input type="hidden" name="action"    value="remove_subject">
                <input type="hidden" name="class_id"  value="<?= $classId ?>">
                <input type="hidden" name="cs_id"     value="<?= $cs['cs_id'] ?>">
                <button class="btn btn-xs btn-outline-danger"><i class="fas fa-times"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <?php elseif ($activeTab === 'remarks'): ?>
      <!-- ────────── TAB 3: Photos & Remarks ────────── -->
      <?php if (!$remarksTableExists): ?>
      <div class="alert alert-warning">
        <i class="fas fa-spinner fa-spin me-2"></i>Database table initialising. Please refresh in a moment.
      </div>
      <?php else: ?>

      <form method="POST" enctype="multipart/form-data" class="mb-4 p-3 rounded"
            style="background:#fffbeb;border:1px solid #fde68a">
        <input type="hidden" name="action"   value="save_remarks">
        <input type="hidden" name="class_id" value="<?= $classId ?>">

        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.83rem">
            <i class="fas fa-comment-alt me-1 text-warning"></i>
            Remarks / Notes for <?= h($viewClass['name']) ?>
          </label>
          <textarea name="remarks" class="form-control form-control-sm" rows="3"
                    placeholder="Write your remarks, observations, or notes about this class…"></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.83rem">
            <i class="fas fa-camera me-1 text-warning"></i>
            Upload Photos <small class="text-muted">(optional, up to 10)</small>
          </label>
          <input type="file" name="photos[]" id="remarkPhotosInput"
                 class="form-control form-control-sm"
                 accept="image/jpeg,image/png,image/gif,image/webp"
                 multiple>
          <div class="form-text" style="font-size:.75rem;color:#92400e">
            JPG/PNG/GIF/WEBP · max 5 MB each · up to 10 images
          </div>
          <div id="remarkPhotoPreview" class="d-flex flex-wrap gap-2 mt-2"></div>
        </div>

        <button type="submit" class="btn btn-sm"
                style="background:#d97706;color:#fff;font-weight:600">
          <i class="fas fa-save me-1"></i>Save Remark
        </button>
      </form>

      <!-- History -->
      <?php if (empty($remarks)): ?>
      <div class="text-center py-4 text-muted">
        <i class="fas fa-comment-slash fa-2x mb-2 d-block opacity-25"></i>
        <span style="font-size:.85rem">No remarks yet for <?= h($viewClass['name']) ?>.</span>
      </div>
      <?php else: ?>
      <h6 class="fw-semibold mb-3" style="font-size:.84rem;color:var(--t2)">
        <i class="fas fa-history me-1"></i>History (<?= count($remarks) ?>)
      </h6>
      <?php foreach ($remarks as $rm):
        $rmPhotos = json_decode($rm['photos'] ?? '[]', true) ?? [];
      ?>
      <div class="mb-3 p-3 rounded" style="background:#f9fafb;border:1px solid var(--border)">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <span class="badge" style="background:#fef3c7;color:#92400e;font-size:.74rem">
              <i class="fas fa-calendar-alt me-1"></i><?= fDate($rm['created_at']) ?>
            </span>
            <span style="font-size:.75rem;color:var(--t2);margin-left:8px">
              <i class="fas fa-user me-1"></i><?= h($rm['author_name'] ?? '—') ?>
            </span>
          </div>
          <form method="POST" class="d-inline"
                onsubmit="return confirm('Delete this entry including photos?')">
            <input type="hidden" name="action"    value="delete_remark">
            <input type="hidden" name="class_id"  value="<?= $classId ?>">
            <input type="hidden" name="remark_id" value="<?= $rm['id'] ?>">
            <button class="btn btn-xs btn-outline-danger" title="Delete">
              <i class="fas fa-trash"></i>
            </button>
          </form>
        </div>

        <?php if (!empty($rm['remarks'])): ?>
        <p style="font-size:.84rem;margin:0 0 10px;white-space:pre-wrap"><?= h($rm['remarks']) ?></p>
        <?php endif; ?>

        <?php if (!empty($rmPhotos)): ?>
        <div>
          <div style="font-size:.75rem;font-weight:600;color:#92400e;margin-bottom:6px">
            <i class="fas fa-images me-1"></i>Photos (<?= count($rmPhotos) ?>)
          </div>
          <div class="d-flex flex-wrap gap-2">
          <?php foreach ($rmPhotos as $pfn): ?>
          <?php $pUrl = url('/assets/uploads/montessori-class-photos/' . h($pfn)); ?>
            <div style="position:relative;flex-shrink:0">
              <a href="<?= $pUrl ?>" target="_blank">
                <img src="<?= $pUrl ?>" alt="Photo"
                     style="width:90px;height:90px;object-fit:cover;border-radius:7px;border:1px solid var(--border)">
              </a>
              <form method="POST" class="d-inline"
                    onsubmit="return confirm('Delete this photo?')"
                    style="position:absolute;top:-5px;right:-5px">
                <input type="hidden" name="action"    value="delete_photo">
                <input type="hidden" name="class_id"  value="<?= $classId ?>">
                <input type="hidden" name="remark_id" value="<?= $rm['id'] ?>">
                <input type="hidden" name="photo_fn"  value="<?= h($pfn) ?>">
                <button type="submit"
                        style="width:20px;height:20px;padding:0;border-radius:50%;border:none;
                               background:#ef4444;color:#fff;font-size:.75rem;line-height:1;cursor:pointer"
                        title="Delete photo">×</button>
              </form>
            </div>
          <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php endif; // remarks empty ?>
      <?php endif; // remarksTableExists ?>

      <?php endif; // activeTab ?>

      </div><!-- /padding -->
    </div><!-- /sec-card -->
    <?php endif; // viewClass ?>
  </div><!-- /right col -->
</div><!-- /row -->

</div></div></div>

<style>
.nav-tabs .nav-link { color: var(--t2); border-bottom: none; }
.nav-tabs .nav-link.active {
  color: #c2410c;
  font-weight: 700;
  border-color: #fdba74 #fdba74 #fff;
  border-bottom: 2px solid #fff !important;
}
.nav-tabs .nav-link:hover { color: #c2410c; }
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Photo preview for remarks form
const remarkInput   = document.getElementById('remarkPhotosInput');
const remarkPreview = document.getElementById('remarkPhotoPreview');
if (remarkInput && remarkPreview) {
  remarkInput.addEventListener('change', function() {
    remarkPreview.innerHTML = '';
    const files = Array.from(this.files).slice(0, 10);
    files.forEach(function(file) {
      if (!file.type.startsWith('image/')) return;
      const reader = new FileReader();
      reader.onload = function(e) {
        const div = document.createElement('div');
        div.style.cssText = 'position:relative;flex-shrink:0';
        div.innerHTML = '<img src="' + e.target.result + '" alt="preview"'
          + ' style="width:75px;height:75px;object-fit:cover;border-radius:6px;border:1px solid #fde68a">';
        remarkPreview.appendChild(div);
      };
      reader.readAsDataURL(file);
    });
    if (this.files.length > 10) {
      const warn = document.createElement('div');
      warn.className = 'text-warning w-100';
      warn.style.fontSize = '.76rem';
      warn.textContent = 'Only the first 10 photos will be uploaded.';
      remarkPreview.appendChild(warn);
    }
  });
}
</script>
</body></html>

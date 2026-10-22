<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp');
$db   = getDB();

// Lazy-create table
$tableExists = false;
try { $db->query('SELECT 1 FROM ilc_teacher_class_assignments LIMIT 0'); $tableExists = true; } catch (Exception $e) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS ilc_teacher_class_assignments (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            teacher_id  INT UNSIGNED NOT NULL,
            class_id    INT UNSIGNED NOT NULL,
            assigned_by INT UNSIGNED NOT NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_teacher_class (teacher_id, class_id),
            INDEX idx_teacher (teacher_id),
            INDEX idx_class   (class_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $tableExists = true;
    } catch (Exception $e2) {}
}

// ── POST handler ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action    = $_POST['action']    ?? '';
    $teacherId = (int)($_POST['teacher_id'] ?? 0);

    if ($action === 'assign' && $teacherId) {
        $classIds = array_map('intval', (array)($_POST['class_ids'] ?? []));
        if (empty($classIds)) {
            setFlash('warning', 'Please select at least one class.');
        } else {
            $ins = $db->prepare(
                'INSERT IGNORE INTO ilc_teacher_class_assignments (teacher_id, class_id, assigned_by)
                 VALUES (?, ?, ?)'
            );
            $count = 0;
            foreach ($classIds as $cid) {
                if ($cid > 0) { $ins->execute([$teacherId, $cid, $user['id']]); $count++; }
            }
            logActivity($user['id'], 'ilc_assign_classes', "Assigned $count class(es) to teacher #$teacherId");
            setFlash('success', "$count class assignment(s) saved.");
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['assign_id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM ilc_teacher_class_assignments WHERE id=?')->execute([$id]);
            logActivity($user['id'], 'ilc_unassign_class', "Removed class assignment #$id");
            setFlash('success', 'Assignment removed.');
        }
    }

    if ($action === 'delete_all_teacher') {
        if ($teacherId) {
            $db->prepare('DELETE FROM ilc_teacher_class_assignments WHERE teacher_id=?')->execute([$teacherId]);
            logActivity($user['id'], 'ilc_unassign_all', "Removed all class assignments for teacher #$teacherId");
            setFlash('success', 'All assignments for this teacher removed.');
        }
    }

    redirect('/portal/ilc/assign-classes.php' . ($teacherId ? "?teacher_id=$teacherId" : ''));
}

// ── Fetch ─────────────────────────────────────────────────────────
$filterTeacherId = (int)($_GET['teacher_id'] ?? 0);

// ILC teachers
try {
    $teachers = $db->query(
        'SELECT t.id AS teacher_id, u.name, u.id AS user_id, t.emp_id
         FROM teachers t JOIN users u ON u.id = t.user_id
         WHERE t.is_ilc = 1 AND u.status = "active"
         ORDER BY u.name'
    )->fetchAll();
} catch (Exception $e) { $teachers = []; }

// ILC classes
try {
    $classes = $db->query(
        'SELECT id, name FROM classes WHERE is_ilc = 1 ORDER BY name'
    )->fetchAll();
} catch (Exception $e) { $classes = []; }

// All assignments (with teacher name and class name)
$assignments = [];
if ($tableExists) {
    try {
        $asSql = '
            SELECT a.id, a.teacher_id, a.class_id, a.created_at,
                   u.name AS teacher_name, t.emp_id,
                   c.name AS class_name,
                   vp.name AS assigned_by_name
            FROM ilc_teacher_class_assignments a
            JOIN teachers t ON t.id = a.teacher_id
            JOIN users u ON u.id = t.user_id
            JOIN classes c ON c.id = a.class_id
            LEFT JOIN users vp ON vp.id = a.assigned_by
        ';
        $asParams = [];
        if ($filterTeacherId) {
            $asSql .= ' WHERE a.teacher_id = ?';
            $asParams[] = $filterTeacherId;
        }
        $asSql .= ' ORDER BY u.name, c.name';
        $st = $db->prepare($asSql);
        $st->execute($asParams);
        $assignments = $st->fetchAll();
    } catch (Exception $e) { $assignments = []; }
}

// Already-assigned class IDs for the selected teacher
$assignedClassIds = [];
if ($filterTeacherId) {
    foreach ($assignments as $a) {
        if ($a['teacher_id'] == $filterTeacherId) $assignedClassIds[] = (int)$a['class_id'];
    }
}

pageHead('Assign Classes to Teachers', 'ilc_vp');
$links = getIlcLinks();
?>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'assign-classes', $links, $user); ?>
<div class="main-area">
<?php topbar('Assign Classes to Teachers', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Branding strip -->
<div class="d-flex align-items-center gap-3 mb-3 p-3"
     style="background:linear-gradient(90deg,#eff6ff,#dbeafe);border-radius:10px;border:1px solid #93c5fd">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC"
       style="width:40px;height:40px;object-fit:contain;flex-shrink:0" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#1e3a8a;letter-spacing:.8px;text-transform:uppercase">
      Assign Classes to Teachers
    </div>
    <div style="font-size:.78rem;color:#475569">Inclusive Learning Centre · Bahria Model College Bin Qasim</div>
  </div>
</div>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  <strong>Database table initialising.</strong> Please refresh the page in a moment.
</div>
<?php else: ?>

<div class="row g-3">
  <!-- ── Left: Assignment form ── -->
  <div class="col-lg-5">
    <div class="sec-card">
      <div class="sec-card-header" style="background:linear-gradient(90deg,#1e3a8a,#2563eb);color:#fff">
        <i class="fas fa-chalkboard-teacher me-2"></i>Assign Classes
      </div>
      <div style="padding:18px">
        <form method="POST">
          <input type="hidden" name="action" value="assign">

          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.83rem">
              Select Teacher <span class="text-danger">*</span>
            </label>
            <select name="teacher_id" id="teacherSelect" class="form-select form-select-sm" required
                    onchange="this.form.submit()">
              <option value="">— Choose ILC teacher —</option>
              <?php foreach ($teachers as $t): ?>
              <option value="<?= $t['teacher_id'] ?>"
                      <?= $filterTeacherId === (int)$t['teacher_id'] ? 'selected' : '' ?>>
                <?= h($t['name']) ?><?= $t['emp_id'] ? ' ('.$t['emp_id'].')' : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <?php if ($filterTeacherId): ?>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.83rem">
              Select Classes / Sections <span class="text-danger">*</span>
            </label>
            <?php if (empty($classes)): ?>
            <div class="text-muted" style="font-size:.83rem">No ILC classes found.</div>
            <?php else: ?>
            <div style="border:1px solid #e2e8f0;border-radius:6px;padding:10px 12px;max-height:260px;overflow-y:auto">
              <div class="mb-1" style="font-size:.74rem;color:#64748b">
                <label>
                  <input type="checkbox" id="selectAll" class="form-check-input me-1">
                  <strong>Select all</strong>
                </label>
              </div>
              <?php foreach ($classes as $cl): ?>
              <div class="form-check py-1" style="border-bottom:1px solid #f1f5f9">
                <input class="form-check-input class-chk" type="checkbox"
                       name="class_ids[]" value="<?= $cl['id'] ?>"
                       id="cl<?= $cl['id'] ?>"
                       <?= in_array((int)$cl['id'], $assignedClassIds) ? 'checked disabled' : '' ?>>
                <label class="form-check-label" for="cl<?= $cl['id'] ?>" style="font-size:.85rem">
                  <?= h($cl['name']) ?>
                  <?php if (in_array((int)$cl['id'], $assignedClassIds)): ?>
                  <span class="badge bg-success ms-1" style="font-size:.68rem">Already assigned</span>
                  <?php endif; ?>
                </label>
              </div>
              <?php endforeach; ?>
            </div>
            <div class="form-text mt-1" style="font-size:.74rem">
              Already-assigned classes are ticked and greyed out.
            </div>
            <?php endif; ?>
          </div>

          <input type="hidden" name="teacher_id" value="<?= $filterTeacherId ?>">
          <button type="submit" class="btn btn-primary btn-sm w-100">
            <i class="fas fa-check-circle me-1"></i>Save Assignments
          </button>
          <?php else: ?>
          <div class="alert alert-info" style="font-size:.83rem">
            <i class="fas fa-info-circle me-2"></i>Select a teacher above to assign classes.
          </div>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <!-- ── Right: Assignments table ── -->
  <div class="col-lg-7">
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center"
           style="background:linear-gradient(90deg,#1e3a8a,#2563eb);color:#fff">
        <span>
          <i class="fas fa-list me-2"></i>
          <?= $filterTeacherId ? 'Assignments for selected teacher' : 'All Class Assignments' ?>
          <span class="badge bg-light text-dark ms-2" style="font-size:.74rem"><?= count($assignments) ?></span>
        </span>
        <?php if ($filterTeacherId): ?>
        <a href="assign-classes.php" class="btn btn-xs btn-light" style="font-size:.74rem;padding:2px 8px">
          Show all
        </a>
        <?php endif; ?>
      </div>

      <?php if (empty($assignments)): ?>
      <div style="padding:50px;text-align:center;color:var(--t2)">
        <i class="fas fa-chalkboard fa-2x mb-2 d-block opacity-25"></i>
        <p style="font-size:.85rem">
          <?= $filterTeacherId ? 'No classes assigned to this teacher yet.' : 'No assignments yet. Use the form to assign classes.' ?>
        </p>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover table-sm mb-0" style="font-size:.83rem">
          <thead style="background:#f1f5f9">
            <tr>
              <th style="padding:8px 12px">Teacher</th>
              <th style="padding:8px 12px">Class / Section</th>
              <th style="padding:8px 12px">Assigned</th>
              <th style="padding:8px 12px;width:70px">Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($assignments as $a): ?>
          <tr>
            <td style="padding:7px 12px">
              <span class="fw-semibold"><?= h($a['teacher_name']) ?></span>
              <?php if ($a['emp_id']): ?>
              <span class="text-muted" style="font-size:.76rem">&nbsp;(<?= h($a['emp_id']) ?>)</span>
              <?php endif; ?>
            </td>
            <td style="padding:7px 12px">
              <span class="badge" style="background:#dbeafe;color:#1e40af;font-size:.78rem">
                <?= h($a['class_name']) ?>
              </span>
            </td>
            <td style="padding:7px 12px;font-size:.76rem;color:var(--t2)">
              <?= fDate($a['created_at']) ?>
              <?php if ($a['assigned_by_name']): ?>
              <br><span style="font-size:.72rem">by <?= h($a['assigned_by_name']) ?></span>
              <?php endif; ?>
            </td>
            <td style="padding:7px 12px;text-align:center">
              <form method="POST" class="d-inline"
                    onsubmit="return confirm('Remove this assignment?')">
                <input type="hidden" name="action"    value="delete">
                <input type="hidden" name="assign_id" value="<?= $a['id'] ?>">
                <input type="hidden" name="teacher_id" value="<?= $a['teacher_id'] ?>">
                <button class="btn btn-xs btn-outline-danger" title="Remove">
                  <i class="fas fa-times"></i>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($filterTeacherId && !empty($assignments)): ?>
      <div style="padding:10px 14px;border-top:1px solid var(--border);text-align:right">
        <form method="POST" class="d-inline"
              onsubmit="return confirm('Remove ALL class assignments for this teacher?')">
          <input type="hidden" name="action"     value="delete_all_teacher">
          <input type="hidden" name="teacher_id" value="<?= $filterTeacherId ?>">
          <button class="btn btn-xs btn-outline-danger">
            <i class="fas fa-trash me-1"></i>Remove all for this teacher
          </button>
        </form>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Select-all checkbox
const selectAll = document.getElementById('selectAll');
if (selectAll) {
  selectAll.addEventListener('change', function() {
    document.querySelectorAll('.class-chk:not(:disabled)').forEach(chk => {
      chk.checked = this.checked;
    });
  });
  // Reflect state
  const chks = document.querySelectorAll('.class-chk:not(:disabled)');
  if (chks.length > 0) {
    selectAll.checked = [...chks].every(c => c.checked);
  }
}
</script>
</body></html>

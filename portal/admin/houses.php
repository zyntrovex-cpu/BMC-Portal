<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

// Graceful check: houses table exists?
$tablesMissing = false;
try { $db->query('SELECT 1 FROM houses LIMIT 0'); } catch (PDOException $e) { $tablesMissing = true; }

// ── POST handlers ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$tablesMissing) {
    $action = $_POST['action'] ?? '';

    // ── Add house ─────────────────────────────────────────────────
    if ($action === 'add') {
        $name  = trim($_POST['name']  ?? '');
        $color = trim($_POST['color'] ?? '#3b82f6');
        if ($name === '') {
            setFlash('danger', 'House name is required.');
        } else {
            // Duplicate check
            $ck = $db->prepare('SELECT id FROM houses WHERE LOWER(name) = LOWER(?)');
            $ck->execute([$name]);
            if ($ck->fetch()) {
                setFlash('danger', "A house named \"$name\" already exists.");
            } else {
                $db->prepare('INSERT INTO houses (name, color) VALUES (?, ?)')->execute([$name, $color]);
                logActivity($user['id'], 'house_add', "Added house: $name");
                setFlash('success', "House \"$name\" added successfully.");
            }
        }
        redirect('/portal/admin/houses.php?tab=houses');
    }

    // ── Edit house ────────────────────────────────────────────────
    if ($action === 'edit') {
        $id    = (int)($_POST['id']    ?? 0);
        $name  = trim($_POST['name']   ?? '');
        $color = trim($_POST['color']  ?? '#3b82f6');
        if ($id && $name !== '') {
            // Duplicate check (exclude self)
            $ck = $db->prepare('SELECT id FROM houses WHERE LOWER(name) = LOWER(?) AND id != ?');
            $ck->execute([$name, $id]);
            if ($ck->fetch()) {
                setFlash('danger', "Another house named \"$name\" already exists.");
            } else {
                $db->prepare('UPDATE houses SET name = ?, color = ? WHERE id = ?')->execute([$name, $color, $id]);
                logActivity($user['id'], 'house_edit', "Updated house #$id: $name");
                setFlash('success', 'House updated.');
            }
        } else {
            setFlash('danger', 'Invalid data.');
        }
        redirect('/portal/admin/houses.php?tab=houses');
    }

    // ── Delete house ──────────────────────────────────────────────
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $ckSt = $db->prepare('SELECT COUNT(*) FROM students WHERE house_id = ?');
            $ckSt->execute([$id]);
            $count = (int)$ckSt->fetchColumn();
            if ($count > 0) {
                setFlash('danger', "Cannot delete: $count student(s) are assigned to this house. Reassign them first.");
            } else {
                $nSt = $db->prepare('SELECT name FROM houses WHERE id = ?');
                $nSt->execute([$id]);
                $houseName = $nSt->fetchColumn();
                $db->prepare('DELETE FROM houses WHERE id = ?')->execute([$id]);
                logActivity($user['id'], 'house_delete', "Deleted house: $houseName");
                setFlash('success', "House \"$houseName\" deleted.");
            }
        }
        redirect('/portal/admin/houses.php?tab=houses');
    }

    // ── Assign / change a student's house ─────────────────────────
    if ($action === 'assign_house') {
        $studentId  = (int)($_POST['student_id'] ?? 0);
        $houseId    = (int)($_POST['house_id']   ?? 0) ?: null;
        $returnQ    = $_POST['return_q']       ?? '';
        $returnCls  = (int)($_POST['return_class'] ?? 0);

        if (!$studentId) {
            setFlash('danger', 'Invalid student.');
        } else {
            $sSt = $db->prepare(
                'SELECT s.id, u.name AS sname FROM students s JOIN users u ON s.user_id=u.id WHERE s.id=?'
            );
            $sSt->execute([$studentId]);
            $stu = $sSt->fetch();
            if (!$stu) {
                setFlash('danger', 'Student not found.');
            } else {
                $db->prepare('UPDATE students SET house_id = ? WHERE id = ?')->execute([$houseId, $studentId]);
                $label = 'None';
                if ($houseId) {
                    $hNm = $db->prepare('SELECT name FROM houses WHERE id = ?');
                    $hNm->execute([$houseId]);
                    $label = $hNm->fetchColumn() ?: 'Unknown';
                }
                logActivity($user['id'], 'house_assign',
                    "House '{$label}' assigned to student: {$stu['sname']}");
                setFlash('success', "House updated for <strong>{$stu['sname']}</strong> → <em>$label</em>.");
            }
        }
        $qs = 'tab=assign';
        if ($returnQ !== '')  $qs .= '&q='     . urlencode($returnQ);
        if ($returnCls > 0)   $qs .= '&class=' . $returnCls;
        redirect('/portal/admin/houses.php?' . $qs);
    }
}

// ── Fetch houses (with student counts) ────────────────────────────
$houses = [];
if (!$tablesMissing) {
    try {
        $houses = $db->query(
            'SELECT h.*, COUNT(s.id) AS student_count
             FROM houses h
             LEFT JOIN students s ON s.house_id = h.id
             GROUP BY h.id
             ORDER BY h.name'
        )->fetchAll();
    } catch (PDOException $e) { $tablesMissing = true; }
}
$houseMap = [];
foreach ($houses as $h) $houseMap[(int)$h['id']] = $h;

// ── Active tab ────────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'houses';

// ── Assign tab: student search / filter ──────────────────────────
$searchStudents = [];
$searchQ        = trim($_GET['q']      ?? '');
$searchClass    = (int)($_GET['class'] ?? 0);
$filterHouse    = trim($_GET['house']  ?? '');  // '' = all, '0' = unassigned, else house id
$classes        = getAllClasses();

if ($tab === 'assign' && !$tablesMissing) {
    $where  = ['(s.deleted_at IS NULL OR s.deleted_at IS NOT NULL)'];  // all active
    $where  = [];
    $params = [];

    // Soft-delete filter (if column exists)
    try {
        $db->query('SELECT deleted_at FROM students LIMIT 0');
        $where[] = 's.deleted_at IS NULL';
    } catch (Exception $e) {}

    // Graduation filter
    try {
        $db->query('SELECT graduated_at FROM students LIMIT 0');
        $where[] = 's.graduated_at IS NULL';
    } catch (Exception $e) {}

    if ($searchQ !== '') {
        $like    = '%' . $searchQ . '%';
        $where[] = '(u.name LIKE ? OR s.roll_no LIKE ? OR u.user_id LIKE ?)';
        $params  = array_merge($params, [$like, $like, $like]);
    }
    if ($searchClass > 0) {
        $where[] = 's.class_id = ?';
        $params[] = $searchClass;
    }
    if ($filterHouse === '0') {
        $where[] = 's.house_id IS NULL';
    } elseif ($filterHouse !== '') {
        $where[] = 's.house_id = ?';
        $params[] = (int)$filterHouse;
    }

    $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $sst = $db->prepare(
            "SELECT s.id, s.roll_no, s.house_id,
                    u.name, u.user_id AS login_id,
                    c.name AS class_name, c.id AS class_id,
                    h.name AS house_name, h.color AS house_color
             FROM students s
             JOIN users u ON s.user_id = u.id
             LEFT JOIN classes c ON s.class_id = c.id
             LEFT JOIN houses  h ON s.house_id  = h.id
             $whereStr
             ORDER BY u.name
             LIMIT 200"
        );
        $sst->execute($params);
        $searchStudents = $sst->fetchAll();
    } catch (Exception $e) {}
}

pageHead('Houses', 'admin');
$links = getAdminLinks();
?>
<div class="portal-wrap">
<?php sidebar('admin', 'houses', $links, $user); ?>
<div class="main-area">
<?php topbar('Houses', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if ($tablesMissing): ?>
<div class="alert alert-warning d-flex gap-3 align-items-start">
  <i class="fas fa-database fa-lg mt-1"></i>
  <div>
    <strong>Database table missing.</strong>
    The <code>houses</code> table has not been created yet. Run the migration in phpMyAdmin.
    <pre class="mt-2 mb-0 p-2" style="background:#f8fafc;border-radius:6px;font-size:.79rem;border:1px solid #e5e7eb">USE bmc_portal;
CREATE TABLE IF NOT EXISTS houses (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  color VARCHAR(20) NOT NULL DEFAULT '#3b82f6',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
ALTER TABLE students ADD COLUMN IF NOT EXISTS house_id INT NULL,
  ADD CONSTRAINT fk_student_house FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE SET NULL;</pre>
  </div>
</div>
<?php else: ?>

<!-- ── Tabs ─────────────────────────────────────────────────────── -->
<ul class="nav nav-tabs mb-3" style="border-bottom:2px solid var(--accent20,#e0e7ff)">
  <li class="nav-item">
    <a class="nav-link<?= $tab === 'houses' ? ' active' : '' ?>"
       href="<?= url('/portal/admin/houses.php?tab=houses') ?>">
      <i class="fas fa-shield-alt me-1"></i>Manage Houses
      <span class="badge bg-secondary ms-1"><?= count($houses) ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link<?= $tab === 'assign' ? ' active' : '' ?>"
       href="<?= url('/portal/admin/houses.php?tab=assign') ?>">
      <i class="fas fa-user-tag me-1"></i>Assign to Students
    </a>
  </li>
</ul>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- TAB 1: MANAGE HOUSES                                           -->
<!-- ══════════════════════════════════════════════════════════════ -->
<?php if ($tab === 'houses'): ?>
<div class="row g-3">

  <!-- Add House -->
  <div class="col-lg-4">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-plus me-2"></i>Add New House</div>
      <div style="padding:16px">
        <form method="POST">
          <input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">House Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control form-control-sm"
                   placeholder="e.g. Allama Iqbal" required maxlength="100">
            <div class="form-text">Duplicate names are prevented automatically.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">House Color</label>
            <div class="d-flex align-items-center gap-2">
              <input type="color" name="color" class="form-control form-control-color form-control-sm"
                     value="#3b82f6" style="width:50px;height:34px;padding:2px">
              <span class="text-muted" style="font-size:.8rem">Shown as a badge throughout the portal</span>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm w-100">
            <i class="fas fa-plus me-1"></i>Add House
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Houses Table -->
  <div class="col-lg-8">
    <div class="sec-card">
      <div class="sec-card-header d-flex align-items-center justify-content-between">
        <span><i class="fas fa-shield-alt me-2"></i>All Houses</span>
        <span class="badge bg-secondary"><?= count($houses) ?></span>
      </div>
      <?php if (empty($houses)): ?>
      <div class="p-4 text-center text-muted">
        <i class="fas fa-shield-alt fa-2x mb-2 d-block" style="opacity:.2"></i>
        No houses yet. Add your first house.
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.85rem">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>House</th>
              <th>Color</th>
              <th>Students</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($houses as $i => $h): ?>
            <tr>
              <td class="text-muted"><?= $i + 1 ?></td>
              <td>
                <span style="display:inline-flex;align-items:center;gap:6px;background:<?= h($h['color']) ?>;
                       color:#fff;padding:3px 12px;border-radius:20px;font-size:.8rem;font-weight:700">
                  <i class="fas fa-shield-alt"></i><?= h($h['name']) ?>
                </span>
              </td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span style="width:18px;height:18px;border-radius:3px;background:<?= h($h['color']) ?>;
                         border:1px solid rgba(0,0,0,.15);display:inline-block"></span>
                  <code style="font-size:.76rem"><?= h($h['color']) ?></code>
                </div>
              </td>
              <td>
                <a href="<?= url('/portal/admin/houses.php?tab=assign&house=' . $h['id']) ?>"
                   class="badge text-decoration-none"
                   style="background:<?= h($h['color']) ?>;color:#fff;font-size:.78rem"
                   title="View students in this house">
                  <i class="fas fa-users me-1"></i><?= $h['student_count'] ?> student<?= $h['student_count'] != 1 ? 's' : '' ?>
                </a>
              </td>
              <td>
                <button class="btn btn-xs btn-outline-primary me-1"
                        data-bs-toggle="modal" data-bs-target="#editHouseModal<?= $h['id'] ?>"
                        title="Edit">
                  <i class="fas fa-edit"></i>
                </button>
                <?php if ($h['student_count'] == 0): ?>
                <form method="POST" class="d-inline"
                      onsubmit="return confirm('Delete house \'<?= h(addslashes($h['name'])) ?>\'? This cannot be undone.')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id"     value="<?= $h['id'] ?>">
                  <button type="submit" class="btn btn-xs btn-outline-danger" title="Delete">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
                <?php else: ?>
                <button class="btn btn-xs btn-outline-secondary" disabled
                        title="Remove all students from this house before deleting">
                  <i class="fas fa-trash"></i>
                </button>
                <?php endif; ?>
              </td>
            </tr>

            <!-- Edit modal -->
            <div class="modal fade" id="editHouseModal<?= $h['id'] ?>" tabindex="-1">
              <div class="modal-dialog modal-sm">
                <div class="modal-content">
                  <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold"><i class="fas fa-edit me-1"></i>Edit House</h6>
                    <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
                  </div>
                  <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id"     value="<?= $h['id'] ?>">
                    <div class="modal-body">
                      <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:.84rem">House Name</label>
                        <input type="text" name="name" class="form-control form-control-sm"
                               value="<?= h($h['name']) ?>" required maxlength="100">
                      </div>
                      <div>
                        <label class="form-label fw-semibold" style="font-size:.84rem">Color</label>
                        <input type="color" name="color" class="form-control form-control-color form-control-sm"
                               value="<?= h($h['color']) ?>" style="width:100%;height:34px">
                      </div>
                    </div>
                    <div class="modal-footer py-2">
                      <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-sm btn-primary">Save Changes</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- TAB 2: ASSIGN STUDENTS                                         -->
<!-- ══════════════════════════════════════════════════════════════ -->
<?php elseif ($tab === 'assign'): ?>

<!-- Filter bar -->
<div class="sec-card mb-3">
  <div class="sec-card-body" style="padding:14px 16px">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="tab" value="assign">
      <div class="col-sm-4 col-lg-3">
        <label class="form-label fw-semibold mb-1" style="font-size:.82rem">Search Student</label>
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="Name, roll no, or login ID…"
               value="<?= h($searchQ) ?>">
      </div>
      <div class="col-sm-3 col-lg-2">
        <label class="form-label fw-semibold mb-1" style="font-size:.82rem">Class</label>
        <select name="class" class="form-select form-select-sm">
          <option value="">All Classes</option>
          <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>"<?= $searchClass === (int)$c['id'] ? ' selected' : '' ?>>
            <?= h($c['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-3 col-lg-2">
        <label class="form-label fw-semibold mb-1" style="font-size:.82rem">House Filter</label>
        <select name="house" class="form-select form-select-sm">
          <option value=""<?= $filterHouse === '' ? ' selected' : '' ?>>All</option>
          <option value="0"<?= $filterHouse === '0' ? ' selected' : '' ?>>— Unassigned —</option>
          <?php foreach ($houses as $h): ?>
          <option value="<?= $h['id'] ?>"<?= $filterHouse === (string)$h['id'] ? ' selected' : '' ?>>
            <?= h($h['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="fas fa-search me-1"></i>Search
        </button>
        <?php if ($searchQ !== '' || $searchClass > 0 || $filterHouse !== ''): ?>
        <a href="<?= url('/portal/admin/houses.php?tab=assign') ?>" class="btn btn-sm btn-outline-secondary">
          Clear
        </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="sec-card">
  <div class="sec-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span><i class="fas fa-user-tag me-2"></i>Students
      <?php if ($filterHouse !== '' && $filterHouse !== '0' && isset($houseMap[(int)$filterHouse])): ?>
        — <span style="color:<?= h($houseMap[(int)$filterHouse]['color']) ?>">
            <?= h($houseMap[(int)$filterHouse]['name']) ?>
          </span>
      <?php elseif ($filterHouse === '0'): ?>
        — <span class="text-muted">Unassigned</span>
      <?php endif; ?>
    </span>
    <span class="badge bg-secondary"><?= count($searchStudents) ?> shown</span>
  </div>

  <?php if (empty($houses)): ?>
  <div class="p-4 text-center text-muted" style="font-size:.87rem">
    <i class="fas fa-exclamation-circle me-1"></i>
    No houses exist yet. Go to the <a href="?tab=houses">Manage Houses</a> tab and add houses first.
  </div>
  <?php elseif ($searchQ === '' && $searchClass === 0 && $filterHouse === ''): ?>
  <div class="p-4 text-center text-muted" style="font-size:.87rem">
    <i class="fas fa-search fa-2x d-block mb-2" style="opacity:.25"></i>
    Search for a student by name or roll number, filter by class, or filter by house above.
  </div>
  <?php elseif (empty($searchStudents)): ?>
  <div class="p-4 text-center text-muted">
    <i class="fas fa-users-slash fa-2x d-block mb-2" style="opacity:.25"></i>
    No students found matching your search.
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-dark">
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Roll No</th>
          <th>Class</th>
          <th>Current House</th>
          <th style="width:240px">Assign House</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($searchStudents as $i => $s): ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <td class="fw-semibold"><?= h($s['name']) ?></td>
          <td><code style="font-size:.78rem"><?= h($s['roll_no']) ?></code></td>
          <td><?= $s['class_name'] ? h($s['class_name']) : '<span class="text-muted">—</span>' ?></td>
          <td>
            <?php if ($s['house_name']): ?>
            <span style="display:inline-flex;align-items:center;gap:5px;background:<?= h($s['house_color']) ?>;
                   color:#fff;padding:2px 10px;border-radius:20px;font-size:.77rem;font-weight:700">
              <i class="fas fa-shield-alt"></i><?= h($s['house_name']) ?>
            </span>
            <?php else: ?>
            <span class="text-muted" style="font-size:.8rem">— None —</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" class="d-flex gap-1 align-items-center">
              <input type="hidden" name="action"       value="assign_house">
              <input type="hidden" name="student_id"   value="<?= $s['id'] ?>">
              <input type="hidden" name="return_q"     value="<?= h($searchQ) ?>">
              <input type="hidden" name="return_class" value="<?= $searchClass ?>">
              <select name="house_id" class="form-select form-select-sm" style="min-width:130px;font-size:.8rem">
                <option value="">— None —</option>
                <?php foreach ($houses as $h): ?>
                <option value="<?= $h['id'] ?>"<?= (int)$s['house_id'] === (int)$h['id'] ? ' selected' : '' ?>>
                  <?= h($h['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-sm btn-primary" style="white-space:nowrap">
                <i class="fas fa-save me-1"></i>Assign
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (count($searchStudents) >= 200): ?>
  <div class="px-3 py-2 text-muted" style="font-size:.78rem;border-top:1px solid #e5e7eb">
    <i class="fas fa-info-circle me-1"></i>Showing first 200 results. Narrow your search to see more specific results.
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php endif; // tab ?>
<?php endif; // tablesMissing ?>

</div><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /portal-wrap -->
<?php pageFooter(); ?>

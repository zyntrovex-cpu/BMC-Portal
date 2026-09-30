<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('student_affairs');
$db   = getDB();

// ── Ensure table exists ───────────────────────────────────────────
$tableExists = false;
try {
    $db->query('SELECT 1 FROM student_categories LIMIT 0');
    $tableExists = true;
} catch (Exception $e) {}

// ── POST handlers ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            setFlash('danger', 'Category name is required.');
        } elseif (mb_strlen($name) > 50) {
            setFlash('danger', 'Category name must be 50 characters or fewer.');
        } else {
            $ck = $db->prepare('SELECT id FROM student_categories WHERE name = ?');
            $ck->execute([$name]);
            if ($ck->fetch()) {
                setFlash('danger', "Category \"$name\" already exists.");
            } else {
                $maxOrder = (int)$db->query('SELECT COALESCE(MAX(sort_order),0) FROM student_categories')->fetchColumn();
                $db->prepare('INSERT INTO student_categories (name, sort_order) VALUES (?, ?)')
                   ->execute([$name, $maxOrder + 10]);
                logActivity($user['id'], 'category_add', "Added student category: $name");
                setFlash('success', "Category \"$name\" added.");
            }
        }
        redirect('/portal/student-affairs/categories.php');
    }

    if ($action === 'edit_category') {
        $id      = (int)($_POST['cat_id'] ?? 0);
        $newName = trim($_POST['name'] ?? '');
        if (!$id || $newName === '') {
            setFlash('danger', 'Category ID and name are required.');
        } elseif (mb_strlen($newName) > 50) {
            setFlash('danger', 'Category name must be 50 characters or fewer.');
        } else {
            $ck = $db->prepare('SELECT id FROM student_categories WHERE name = ? AND id <> ?');
            $ck->execute([$newName, $id]);
            if ($ck->fetch()) {
                setFlash('danger', "Another category named \"$newName\" already exists.");
            } else {
                $oldRow = $db->prepare('SELECT name FROM student_categories WHERE id=?');
                $oldRow->execute([$id]);
                $old = $oldRow->fetchColumn();
                $db->prepare('UPDATE student_categories SET name=? WHERE id=?')->execute([$newName, $id]);
                // Keep students.category in sync
                if ($old && $old !== $newName) {
                    $db->prepare("UPDATE students SET category=? WHERE category=?")->execute([$newName, $old]);
                }
                logActivity($user['id'], 'category_edit', "Renamed category: $old → $newName");
                setFlash('success', "Category renamed to \"$newName\".");
            }
        }
        redirect('/portal/student-affairs/categories.php');
    }

    if ($action === 'delete_category') {
        $id = (int)($_POST['cat_id'] ?? 0);
        if ($id) {
            $row = $db->prepare('SELECT name FROM student_categories WHERE id=?');
            $row->execute([$id]);
            $catName = $row->fetchColumn();
            if ($catName) {
                $inUse = (int)$db->prepare("SELECT COUNT(*) FROM students WHERE category=?")->execute([$catName]) ? 0 : 0;
                $stC   = $db->prepare("SELECT COUNT(*) FROM students WHERE category=?");
                $stC->execute([$catName]);
                $inUse = (int)$stC->fetchColumn();
                if ($inUse > 0) {
                    setFlash('danger', "Cannot delete \"$catName\" — $inUse student(s) are assigned to it. Reassign them first.");
                } else {
                    $db->prepare('DELETE FROM student_categories WHERE id=?')->execute([$id]);
                    logActivity($user['id'], 'category_delete', "Deleted student category: $catName");
                    setFlash('success', "Category \"$catName\" deleted.");
                }
            }
        }
        redirect('/portal/student-affairs/categories.php');
    }

    if ($action === 'assign_category') {
        $studentId   = (int)($_POST['student_id'] ?? 0);
        $categoryVal = trim($_POST['category'] ?? '');
        if ($studentId) {
            $db->prepare("UPDATE students SET category=? WHERE id=?")
               ->execute([$categoryVal !== '' ? $categoryVal : null, $studentId]);
            logActivity($user['id'], 'category_assign', "Assigned category \"$categoryVal\" to student #$studentId");
            setFlash('success', 'Category updated.');
        }
        redirect('/portal/student-affairs/categories.php' . ($_GET['back'] ?? ''));
    }
}

// ── Load categories with student counts ───────────────────────────
$categories = [];
if ($tableExists) {
    try {
        $categories = $db->query(
            "SELECT sc.id, sc.name, sc.sort_order, sc.created_at,
                    COUNT(s.id) AS student_count
             FROM student_categories sc
             LEFT JOIN students s ON s.category = sc.name
             GROUP BY sc.id, sc.name, sc.sort_order, sc.created_at
             ORDER BY sc.sort_order, sc.name"
        )->fetchAll();
    } catch (Exception $e) {}
}

// ── Category-specific student list ────────────────────────────────
$viewCat     = trim($_GET['view'] ?? '');
$catStudents = [];
if ($viewCat !== '' && $tableExists) {
    try {
        $sSt = $db->prepare(
            "SELECT s.id AS student_id, s.roll_no, u.name, c.name AS class_name,
                    u.status
             FROM students s
             JOIN users u ON s.user_id = u.id
             LEFT JOIN classes c ON s.class_id = c.id
             WHERE s.category = ?
             ORDER BY c.name, u.name"
        );
        $sSt->execute([$viewCat]);
        $catStudents = $sSt->fetchAll();
    } catch (Exception $e) {}
}

$links = getStudentAffairsLinks();
pageHead('Student Categories', 'student_affairs');
?>
<div class="portal-wrap">
<?php sidebar('student_affairs', 'categories', $links, $user); ?>
<div class="main-area">
<?php topbar('Student Categories', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  <strong>Setup required:</strong> The <code>student_categories</code> table does not exist yet.
  Run <code>database/migrations/student_categories.sql</code> in phpMyAdmin, then refresh.
</div>
<?php else: ?>

<div class="row g-3">

  <!-- ── Add Category ── -->
  <div class="col-lg-4">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-plus me-2"></i>Add New Category</div>
      <div style="padding:16px">
        <form method="POST">
          <input type="hidden" name="action" value="add_category">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">
              Category Code / Name <span class="text-danger">*</span>
            </label>
            <input type="text" name="name" class="form-control form-control-sm" required
                   placeholder="e.g. CB I, AOG II, FAC G III"
                   maxlength="50">
            <div class="form-text" style="font-size:.77rem">
              Use the exact short-code format (e.g. <strong>CB I</strong>, <strong>AOG II</strong>).
              Do not expand abbreviations.
            </div>
          </div>
          <button type="submit" class="btn btn-sm btn-success w-100">
            <i class="fas fa-plus me-1"></i>Add Category
          </button>
        </form>
      </div>
    </div>

    <!-- Stats card -->
    <div class="sec-card mt-3">
      <div class="sec-card-header"><i class="fas fa-info-circle me-2"></i>Summary</div>
      <div style="padding:14px 16px;font-size:.85rem">
        <div class="d-flex justify-content-between border-bottom py-2">
          <span style="color:#6b7280">Total Categories</span>
          <strong><?= count($categories) ?></strong>
        </div>
        <div class="d-flex justify-content-between border-bottom py-2">
          <span style="color:#6b7280">Students Assigned</span>
          <strong><?= array_sum(array_column($categories, 'student_count')) ?></strong>
        </div>
        <div class="d-flex justify-content-between py-2">
          <span style="color:#6b7280">Unassigned Students</span>
          <?php
            try {
                $unassigned = (int)$db->query(
                    "SELECT COUNT(*) FROM students WHERE category IS NULL OR category=''"
                )->fetchColumn();
            } catch (Exception $e) { $unassigned = 0; }
          ?>
          <strong><?= $unassigned ?></strong>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Category list ── -->
  <div class="col-lg-8">
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-tags me-2"></i>All Categories (<?= count($categories) ?>)</span>
        <?php if ($viewCat !== ''): ?>
        <a href="<?= url('/portal/student-affairs/categories.php') ?>" class="btn btn-xs btn-outline-secondary" style="font-size:.77rem">
          Show All
        </a>
        <?php endif; ?>
      </div>
      <?php if (empty($categories)): ?>
      <div style="padding:40px;text-align:center;color:var(--t2)">
        <i class="fas fa-tags fa-2x mb-2 d-block opacity-25"></i>
        <p class="mb-0">No categories yet. Add one using the form on the left.</p>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.84rem">
          <thead class="table-dark">
            <tr>
              <th>Category Code</th>
              <th class="text-center" style="width:100px">Students</th>
              <th style="width:180px"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $cat): ?>
            <tr <?= $viewCat === $cat['name'] ? 'style="background:#eff6ff"' : '' ?>>
              <td>
                <span class="badge" style="background:var(--accent);font-size:.82rem;padding:4px 10px">
                  <?= h($cat['name']) ?>
                </span>
              </td>
              <td class="text-center">
                <?php if ($cat['student_count'] > 0): ?>
                <a href="<?= url('/portal/student-affairs/categories.php?view=' . urlencode($cat['name'])) ?>"
                   class="badge bg-primary text-decoration-none" style="font-size:.8rem">
                  <?= $cat['student_count'] ?>
                </a>
                <?php else: ?>
                <span class="text-muted">0</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <!-- Edit -->
                <button class="btn btn-xs btn-outline-primary me-1"
                        data-bs-toggle="modal" data-bs-target="#editCatModal<?= $cat['id'] ?>"
                        title="Rename">
                  <i class="fas fa-edit"></i>
                </button>
                <!-- Delete -->
                <?php if ($cat['student_count'] == 0): ?>
                <form method="POST" class="d-inline"
                      onsubmit="return confirm('Delete category &quot;<?= h(addslashes($cat['name'])) ?>&quot;?')">
                  <input type="hidden" name="action" value="delete_category">
                  <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                  <button class="btn btn-xs btn-outline-danger" title="Delete">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
                <?php else: ?>
                <button class="btn btn-xs btn-outline-secondary" disabled title="In use — reassign students first">
                  <i class="fas fa-trash"></i>
                </button>
                <?php endif; ?>
              </td>
            </tr>

            <!-- Edit modal -->
            <div class="modal fade" id="editCatModal<?= $cat['id'] ?>" tabindex="-1">
              <div class="modal-dialog modal-sm">
                <div class="modal-content">
                  <div class="modal-header py-2">
                    <h6 class="modal-title" style="font-size:.9rem">
                      <i class="fas fa-edit me-1"></i>Rename Category
                    </h6>
                    <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
                  </div>
                  <form method="POST">
                    <input type="hidden" name="action" value="edit_category">
                    <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                    <div class="modal-body">
                      <label class="form-label fw-semibold" style="font-size:.83rem">New Name</label>
                      <input type="text" name="name" class="form-control form-control-sm"
                             value="<?= h($cat['name']) ?>" required maxlength="50">
                      <div class="form-text mt-1" style="font-size:.76rem;color:#dc2626">
                        Renaming will update all <?= $cat['student_count'] ?> assigned student(s) automatically.
                      </div>
                    </div>
                    <div class="modal-footer py-2">
                      <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-sm btn-primary">Save</button>
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

    <!-- ── Students for selected category ── -->
    <?php if ($viewCat !== ''): ?>
    <div class="sec-card mt-3">
      <div class="sec-card-header d-flex justify-content-between align-items-center">
        <span>
          <i class="fas fa-user-graduate me-2"></i>
          Students in <span class="badge ms-1" style="background:var(--accent)"><?= h($viewCat) ?></span>
          (<?= count($catStudents) ?>)
        </span>
        <a href="<?= url('/portal/student-affairs/students.php?category=' . urlencode($viewCat)) ?>"
           class="btn btn-xs btn-outline-primary" style="font-size:.77rem">
          View in Student List
        </a>
      </div>
      <?php if (empty($catStudents)): ?>
      <div style="padding:24px;text-align:center;color:var(--t2);font-size:.84rem">
        No students assigned to this category yet.
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" style="font-size:.83rem">
          <thead class="table-light">
            <tr><th>Roll No</th><th>Name</th><th>Class</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($catStudents as $cs): ?>
            <tr>
              <td><code style="font-size:.78rem"><?= h($cs['roll_no']) ?></code></td>
              <td class="fw-semibold"><?= h($cs['name']) ?></td>
              <td><?= $cs['class_name'] ? '<span class="badge bg-secondary">'.h($cs['class_name']).'</span>' : '<span class="text-muted">—</span>' ?></td>
              <td><span class="badge <?= $cs['status']==='active'?'bg-success':'bg-danger' ?>"><?= $cs['status'] ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

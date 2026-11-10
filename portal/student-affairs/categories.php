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

// Detect if students.category column exists
$hasCategoryCol = false;
try { $db->query('SELECT category FROM students LIMIT 0'); $hasCategoryCol = true; } catch (Exception $e) {}

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
                if ($old && $old !== $newName && $hasCategoryCol) {
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
                $inUse = 0;
                if ($hasCategoryCol) {
                    $stC = $db->prepare("SELECT COUNT(*) FROM students WHERE category=?");
                    $stC->execute([$catName]);
                    $inUse = (int)$stC->fetchColumn();
                }
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
        $backView    = trim($_POST['back_view'] ?? '');
        $backFind    = trim($_POST['back_find'] ?? '');
        if ($studentId && $hasCategoryCol) {
            $db->prepare("UPDATE students SET category=? WHERE id=?")
               ->execute([$categoryVal !== '' ? $categoryVal : null, $studentId]);
            logActivity($user['id'], 'category_assign',
                $categoryVal !== ''
                    ? "Set category \"$categoryVal\" for student #$studentId"
                    : "Removed category from student #$studentId"
            );
            setFlash('success', $categoryVal !== '' ? "Category set to \"$categoryVal\"." : 'Category removed.');
        }
        $qs  = [];
        if ($backView !== '') $qs[] = 'view=' . urlencode($backView);
        if ($backFind !== '') $qs[] = 'find=' . urlencode($backFind);
        redirect('/portal/student-affairs/categories.php' . ($qs ? '?' . implode('&', $qs) : ''));
    }
}

// ── Auto-sync: import any students.category values not yet in the table ──
if ($tableExists && $hasCategoryCol) {
    try {
        $db->exec("INSERT IGNORE INTO student_categories (name, sort_order)
                   SELECT DISTINCT s.category,
                          COALESCE((SELECT MAX(sort_order) FROM student_categories), 0) + 10
                   FROM students s
                   WHERE s.category IS NOT NULL AND s.category <> ''");
    } catch (Exception $e) {}
}

// ── Load categories with student counts ───────────────────────────
$categories = [];
if ($tableExists) {
    try {
        $catQ = $hasCategoryCol
            ? "SELECT sc.id, sc.name, sc.sort_order,
                      (SELECT COUNT(*) FROM students s WHERE s.category = sc.name) AS student_count
               FROM student_categories sc
               ORDER BY sc.sort_order, sc.name"
            : "SELECT id, name, sort_order, 0 AS student_count
               FROM student_categories
               ORDER BY sort_order, name";
        $categories = $db->query($catQ)->fetchAll();
    } catch (Exception $e) {}
}
$catNames = array_column($categories, 'name');

// ── Category filter (server-side, preserves other params) ─────────
$catFilter = trim($_GET['cs'] ?? '');
$displayCategories = $catFilter !== ''
    ? array_values(array_filter($categories, fn($c) => mb_stripos($c['name'], $catFilter) !== false))
    : $categories;

// ── Category-specific student drill-down ─────────────────────────
$viewCat     = trim($_GET['view'] ?? '');
$catStudents = [];
if ($viewCat !== '' && $hasCategoryCol) {
    try {
        $sSt = $db->prepare(
            "SELECT s.id AS student_id, s.roll_no, s.category, u.name, c.name AS class_name, u.status
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

// ── Student search for assignment ─────────────────────────────────
$findQuery    = trim($_GET['find'] ?? '');
$findStudents = [];
if ($findQuery !== '' && $hasCategoryCol) {
    try {
        $like = '%' . $findQuery . '%';
        $fSt  = $db->prepare(
            "SELECT s.id AS student_id, s.roll_no, s.category, u.name, c.name AS class_name
             FROM students s
             JOIN users u ON s.user_id = u.id
             LEFT JOIN classes c ON s.class_id = c.id
             WHERE (u.name LIKE ? OR s.roll_no LIKE ?)
             ORDER BY c.name, u.name
             LIMIT 30"
        );
        $fSt->execute([$like, $like]);
        $findStudents = $fSt->fetchAll();
    } catch (Exception $e) {}
}

// ── Summary stats ─────────────────────────────────────────────────
$unassigned = 0;
if ($hasCategoryCol) {
    try {
        $unassigned = (int)$db->query(
            "SELECT COUNT(*) FROM students WHERE category IS NULL OR category=''"
        )->fetchColumn();
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
  Please wait — it will be created automatically on the next page load (auto-migration).
  If it still fails, run <code>database/migrations/student_categories.sql</code> in phpMyAdmin.
</div>
<?php else: ?>

<div class="row g-3">

  <!-- ══════════════════ LEFT COLUMN ══════════════════ -->
  <div class="col-xl-4 col-lg-5">

    <!-- ── Add Category ── -->
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
                   placeholder="e.g. CB I, AOG II, FAC G III" maxlength="50">
            <div class="form-text" style="font-size:.77rem">
              Use the exact short-code format (e.g. <strong>CB I</strong>, <strong>AOG II</strong>).
            </div>
          </div>
          <button type="submit" class="btn btn-sm btn-success w-100">
            <i class="fas fa-plus me-1"></i>Add Category
          </button>
        </form>
      </div>
    </div>

    <!-- ── Assign Category to Student ── -->
    <div class="sec-card mt-3">
      <div class="sec-card-header">
        <i class="fas fa-user-tag me-2"></i>Assign / Change Category
      </div>
      <div style="padding:14px 16px">
        <?php if (!$hasCategoryCol): ?>
        <div class="text-muted" style="font-size:.83rem">
          <i class="fas fa-info-circle me-1"></i>Category column not yet available in the students table.
        </div>
        <?php else: ?>
        <!-- Student search form -->
        <form method="GET" action="" class="mb-3">
          <?php if ($viewCat !== ''): ?><input type="hidden" name="view" value="<?= h($viewCat) ?>"><?php endif; ?>
          <?php if ($catFilter !== ''): ?><input type="hidden" name="cs" value="<?= h($catFilter) ?>"><?php endif; ?>
          <div class="d-flex gap-1">
            <input type="text" name="find" value="<?= h($findQuery) ?>"
                   class="form-control form-control-sm" placeholder="Student name or roll no…"
                   autocomplete="off" style="font-size:.82rem">
            <button class="btn btn-sm btn-primary" style="white-space:nowrap;font-size:.8rem;padding:3px 10px">
              <i class="fas fa-search me-1"></i>Find
            </button>
            <?php if ($findQuery !== ''): ?>
            <a href="?<?= $viewCat ? 'view=' . urlencode($viewCat) : '' ?><?= ($viewCat && $catFilter) ? '&' : '' ?><?= $catFilter ? 'cs=' . urlencode($catFilter) : '' ?>"
               class="btn btn-sm btn-outline-secondary" style="font-size:.8rem;padding:3px 8px" title="Clear search">
              <i class="fas fa-times"></i>
            </a>
            <?php endif; ?>
          </div>
        </form>

        <?php if ($findQuery !== ''): ?>
          <?php if (empty($findStudents)): ?>
          <div style="font-size:.83rem;color:var(--t2);padding:6px 0">
            <i class="fas fa-search me-1"></i>No students found for "<?= h($findQuery) ?>".
          </div>
          <?php else: ?>
          <div style="font-size:.77rem;color:var(--t3);margin-bottom:6px">
            <?= count($findStudents) ?> student(s) found — select category and click <i class="fas fa-check" style="font-size:.68rem"></i> to assign.
          </div>
          <div style="max-height:360px;overflow-y:auto">
            <?php foreach ($findStudents as $fs): ?>
            <div style="border:1px solid var(--border);border-radius:6px;padding:8px 10px;margin-bottom:6px">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <strong style="font-size:.83rem"><?= h($fs['name']) ?></strong>
                  <div style="font-size:.74rem;color:var(--t3)">
                    <?= h($fs['roll_no']) ?><?= $fs['class_name'] ? ' · ' . h($fs['class_name']) : '' ?>
                  </div>
                </div>
                <?php if (!empty($fs['category'])): ?>
                <span class="badge" style="background:var(--accent);font-size:.7rem;white-space:nowrap"><?= h($fs['category']) ?></span>
                <?php else: ?>
                <span class="badge bg-light text-muted border" style="font-size:.7rem">None</span>
                <?php endif; ?>
              </div>
              <form method="POST" class="d-flex gap-1 align-items-center">
                <input type="hidden" name="action"    value="assign_category">
                <input type="hidden" name="student_id" value="<?= $fs['student_id'] ?>">
                <input type="hidden" name="back_view" value="<?= h($viewCat) ?>">
                <input type="hidden" name="back_find" value="<?= h($findQuery) ?>">
                <select name="category" class="form-select form-select-sm flex-grow-1" style="font-size:.78rem">
                  <option value="">— Remove / Unassign —</option>
                  <?php foreach ($catNames as $cn): ?>
                  <option value="<?= h($cn) ?>" <?= ($fs['category'] ?? '') === $cn ? 'selected' : '' ?>><?= h($cn) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-xs btn-success" style="font-size:.78rem;padding:3px 9px" title="Save">
                  <i class="fas fa-check"></i>
                </button>
              </form>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        <?php else: ?>
        <div style="font-size:.82rem;color:var(--t2)">
          Search by name or roll number above to find a student and assign or change their category.
        </div>
        <?php endif; ?>
        <?php endif; // hasCategoryCol ?>
      </div>
    </div>

    <!-- ── Summary ── -->
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
          <strong><?= $unassigned ?></strong>
        </div>
        <?php if ($unassigned > 0): ?>
        <div class="mt-1" style="font-size:.77rem">
          <a href="<?= url('/portal/student-affairs/students.php?category=__unassigned__') ?>"
             class="text-decoration-none" style="color:var(--accent)">
            <i class="fas fa-external-link-alt me-1"></i>View unassigned students
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /left col -->

  <!-- ══════════════════ RIGHT COLUMN ══════════════════ -->
  <div class="col-xl-8 col-lg-7">

    <!-- ── Category list ── -->
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-tags me-2"></i>All Categories (<?= count($categories) ?>)</span>
        <div class="d-flex gap-1 align-items-center">
          <!-- Category filter -->
          <form method="GET" action="" class="d-flex gap-1">
            <?php if ($viewCat !== ''): ?><input type="hidden" name="view" value="<?= h($viewCat) ?>"><?php endif; ?>
            <?php if ($findQuery !== ''): ?><input type="hidden" name="find" value="<?= h($findQuery) ?>"><?php endif; ?>
            <input type="text" name="cs" value="<?= h($catFilter) ?>"
                   class="form-control form-control-sm" style="width:150px;font-size:.8rem"
                   placeholder="Filter…" autocomplete="off">
            <button class="btn btn-sm btn-outline-secondary" style="font-size:.8rem;padding:3px 8px" title="Filter">
              <i class="fas fa-filter"></i>
            </button>
            <?php if ($catFilter !== ''): ?>
            <a href="?<?= $viewCat ? 'view=' . urlencode($viewCat) . '&' : '' ?><?= $findQuery ? 'find=' . urlencode($findQuery) : '' ?>"
               class="btn btn-sm btn-outline-danger" style="font-size:.8rem;padding:3px 7px" title="Clear filter">
              <i class="fas fa-times"></i>
            </a>
            <?php endif; ?>
          </form>
        </div>
      </div>

      <?php if ($catFilter !== '' && empty($displayCategories)): ?>
      <div style="padding:28px;text-align:center;color:var(--t2);font-size:.84rem">
        <i class="fas fa-search me-1"></i>No categories match "<?= h($catFilter) ?>".
        <a href="<?= url('/portal/student-affairs/categories.php') ?>" class="ms-2">Clear filter</a>
      </div>
      <?php elseif (empty($categories)): ?>
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
              <th class="text-center" style="width:90px">Students</th>
              <th style="width:160px"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($displayCategories as $cat):
              $viewLink = url('/portal/student-affairs/categories.php?view=' . urlencode($cat['name'])
                              . ($catFilter ? '&cs=' . urlencode($catFilter) : '')
                              . ($findQuery ? '&find=' . urlencode($findQuery) : ''));
            ?>
            <tr <?= $viewCat === $cat['name'] ? 'style="background:var(--accent-faint,#eff6ff)"' : '' ?>>
              <td>
                <a href="<?= $viewLink ?>" class="badge text-decoration-none"
                   style="background:var(--accent);font-size:.82rem;padding:4px 10px" title="View students in this category">
                  <?= h($cat['name']) ?>
                </a>
              </td>
              <td class="text-center">
                <?php if ($cat['student_count'] > 0): ?>
                <a href="<?= $viewLink ?>"
                   class="badge bg-primary text-decoration-none" style="font-size:.8rem">
                  <?= $cat['student_count'] ?>
                </a>
                <?php else: ?>
                <span class="text-muted">0</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <!-- View students -->
                <a href="<?= $viewLink ?>" class="btn btn-xs btn-outline-info me-1" title="View students">
                  <i class="fas fa-users"></i>
                </a>
                <!-- Rename -->
                <button class="btn btn-xs btn-outline-primary me-1"
                        data-bs-toggle="modal" data-bs-target="#editCatModal<?= $cat['id'] ?>"
                        title="Rename category">
                  <i class="fas fa-edit"></i>
                </button>
                <!-- Delete -->
                <?php if ($cat['student_count'] == 0): ?>
                <form method="POST" class="d-inline"
                      onsubmit="return confirm('Delete category &quot;<?= h(addslashes($cat['name'])) ?>&quot;?')">
                  <input type="hidden" name="action" value="delete_category">
                  <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                  <button class="btn btn-xs btn-outline-danger" title="Delete (no students assigned)">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
                <?php else: ?>
                <button class="btn btn-xs btn-outline-secondary" disabled
                        title="Cannot delete — <?= $cat['student_count'] ?> student(s) assigned. Reassign first.">
                  <i class="fas fa-trash"></i>
                </button>
                <?php endif; ?>
              </td>
            </tr>

            <!-- Rename modal -->
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
                    <input type="hidden" name="action"  value="edit_category">
                    <input type="hidden" name="cat_id"  value="<?= $cat['id'] ?>">
                    <div class="modal-body">
                      <label class="form-label fw-semibold" style="font-size:.83rem">New Name</label>
                      <input type="text" name="name" class="form-control form-control-sm"
                             value="<?= h($cat['name']) ?>" required maxlength="50">
                      <?php if ($cat['student_count'] > 0): ?>
                      <div class="form-text mt-1" style="font-size:.76rem;color:#dc2626">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Renaming will automatically update all <?= $cat['student_count'] ?> assigned student(s).
                      </div>
                      <?php endif; ?>
                    </div>
                    <div class="modal-footer py-2">
                      <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-save me-1"></i>Save
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
      <?php if ($catFilter !== ''): ?>
      <div style="padding:8px 16px;font-size:.76rem;color:var(--t3);border-top:1px solid var(--border)">
        Showing <?= count($displayCategories) ?> of <?= count($categories) ?> categories — filter active.
        <a href="?<?= $viewCat ? 'view=' . urlencode($viewCat) : '' ?>" class="ms-1">Show all</a>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div><!-- /category list card -->

    <!-- ── Student drill-down for selected category ── -->
    <?php if ($viewCat !== ''): ?>
    <div class="sec-card mt-3">
      <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
          <i class="fas fa-user-graduate me-2"></i>
          Students in
          <span class="badge ms-1" style="background:var(--accent)"><?= h($viewCat) ?></span>
          (<?= count($catStudents) ?>)
        </span>
        <div class="d-flex gap-1">
          <a href="<?= url('/portal/student-affairs/students.php?category=' . urlencode($viewCat)) ?>"
             class="btn btn-xs btn-outline-primary" style="font-size:.77rem" title="Open in Student List">
            <i class="fas fa-external-link-alt me-1"></i>Student List
          </a>
          <a href="<?= url('/portal/student-affairs/categories.php' . ($catFilter ? '?cs=' . urlencode($catFilter) : '') . ($findQuery ? ($catFilter ? '&' : '?') . 'find=' . urlencode($findQuery) : '')) ?>"
             class="btn btn-xs btn-outline-secondary" style="font-size:.77rem" title="Close drill-down">
            <i class="fas fa-times me-1"></i>Close
          </a>
        </div>
      </div>

      <?php if (!$hasCategoryCol): ?>
      <div style="padding:20px;font-size:.83rem;color:var(--t2)">
        Category column not yet available. Please refresh the page to trigger auto-migration.
      </div>
      <?php elseif (empty($catStudents)): ?>
      <div style="padding:28px;text-align:center;color:var(--t2);font-size:.84rem">
        <i class="fas fa-user-slash fa-2x mb-2 d-block opacity-25"></i>
        No students assigned to <strong><?= h($viewCat) ?></strong> yet.<br>
        <span style="font-size:.8rem">Use "Assign / Change Category" on the left to add students here.</span>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" style="font-size:.83rem">
          <thead class="table-light">
            <tr>
              <th>Roll No</th>
              <th>Name</th>
              <th>Class</th>
              <th>Status</th>
              <th style="min-width:230px">Change / Remove</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($catStudents as $cs): ?>
            <tr>
              <td><code style="font-size:.78rem"><?= h($cs['roll_no']) ?></code></td>
              <td class="fw-semibold"><?= h($cs['name']) ?></td>
              <td><?= $cs['class_name']
                    ? '<span class="badge bg-secondary">'.h($cs['class_name']).'</span>'
                    : '<span class="text-muted">—</span>' ?></td>
              <td><span class="badge <?= $cs['status']==='active'?'bg-success':'bg-danger' ?>"><?= $cs['status'] ?></span></td>
              <td>
                <form method="POST" class="d-flex gap-1 align-items-center">
                  <input type="hidden" name="action"     value="assign_category">
                  <input type="hidden" name="student_id" value="<?= $cs['student_id'] ?>">
                  <input type="hidden" name="back_view"  value="<?= h($viewCat) ?>">
                  <?php if ($findQuery !== ''): ?>
                  <input type="hidden" name="back_find"  value="<?= h($findQuery) ?>">
                  <?php endif; ?>
                  <select name="category" class="form-select form-select-sm flex-grow-1" style="font-size:.78rem">
                    <option value="">— Remove / Unassign —</option>
                    <?php foreach ($catNames as $cn): ?>
                    <option value="<?= h($cn) ?>" <?= ($cs['category'] ?? '') === $cn ? 'selected' : '' ?>>
                      <?= h($cn) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-xs btn-success" style="font-size:.77rem;padding:3px 8px"
                          title="Save change">
                    <i class="fas fa-check"></i>
                  </button>
                  <button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:.77rem;padding:3px 8px"
                          title="Remove from this category"
                          onclick="this.form.querySelector('[name=category]').value='';return true;">
                    <i class="fas fa-user-minus"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="padding:8px 16px;font-size:.75rem;color:var(--t3);border-top:1px solid var(--border)">
        <i class="fas fa-info-circle me-1"></i>
        Select a new category and click <i class="fas fa-check" style="font-size:.68rem"></i> to reassign,
        or click <i class="fas fa-user-minus" style="font-size:.68rem"></i> to remove from this category.
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div><!-- /right col -->
</div><!-- /row -->

<?php endif; ?>

</div></div></div>
<?php pageFooter(); ?>

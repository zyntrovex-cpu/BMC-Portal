<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp');
requirePermission('ilc_disabilities');
$db   = getDB();

// ── POST: add/delete category or subtype ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_category') {
        $name = trim($_POST['cat_name'] ?? '');
        if ($name) {
            try {
                $db->prepare('INSERT INTO disability_categories (name) VALUES (?)')->execute([$name]);
                logActivity($user['id'], 'disability_cat_add', "Added category: $name");
                setFlash('success', "Category "$name" added.");
            } catch (Exception $e) {
                setFlash('danger', 'Could not add category (may already exist).');
            }
        } else {
            setFlash('danger', 'Category name is required.');
        }
    }

    if ($action === 'add_subtype') {
        $catId = (int)($_POST['sub_cat_id'] ?? 0);
        $name  = trim($_POST['sub_name'] ?? '');
        if ($catId && $name) {
            try {
                $db->prepare('INSERT INTO disability_subtypes (category_id, name) VALUES (?,?)')->execute([$catId, $name]);
                logActivity($user['id'], 'disability_subtype_add', "Added subtype: $name (cat #$catId)");
                setFlash('success', "Subtype "$name" added.");
            } catch (Exception $e) {
                setFlash('danger', 'Could not add subtype (may already exist).');
            }
        } else {
            setFlash('danger', 'Category and subtype name are required.');
        }
    }

    if ($action === 'delete_category') {
        $id = (int)($_POST['cat_id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM disability_categories WHERE id = ?')->execute([$id]);
            logActivity($user['id'], 'disability_cat_delete', "Deleted category #$id");
            setFlash('success', 'Category deleted.');
        }
    }

    if ($action === 'delete_subtype') {
        $id = (int)($_POST['sub_id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM disability_subtypes WHERE id = ?')->execute([$id]);
            logActivity($user['id'], 'disability_subtype_delete', "Deleted subtype #$id");
            setFlash('success', 'Subtype deleted.');
        }
    }

    redirect('/portal/ilc/disabilities.php');
}

$catFilter = (int)($_GET['cat'] ?? 0);
$search    = trim($_GET['q'] ?? '');

$sql = 'SELECT sd.id AS dis_id, sd.notes, sd.created_at,
               u.name AS student_name, u.user_id AS student_uid,
               s.id AS student_id, s.roll_no,
               c.name AS class_name,
               dc.id AS cat_id, dc.name AS cat_name,
               dst.name AS subtype_name,
               ru.name AS recorded_by
        FROM student_disabilities sd
        JOIN students s   ON s.id   = sd.student_id
        JOIN classes c    ON c.id   = s.class_id
        JOIN users u      ON u.id   = s.user_id
        JOIN disability_subtypes dst ON dst.id = sd.subtype_id
        JOIN disability_categories dc ON dc.id = dst.category_id
        JOIN users ru     ON ru.id  = sd.recorded_by
        WHERE c.is_ilc = 1';
$params = [];

if ($catFilter) { $sql .= ' AND dc.id = ?'; $params[] = $catFilter; }
if ($search) {
    $sql    .= ' AND (u.name LIKE ? OR u.user_id LIKE ?)';
    $like    = "%$search%";
    $params[] = $like; $params[] = $like;
}
$sql .= ' ORDER BY dc.name, dst.name, u.name';

$st = $db->prepare($sql);
$st->execute($params);
$records = $st->fetchAll();

$categories    = $db->query('SELECT * FROM disability_categories ORDER BY name')->fetchAll();
$allSubtypes   = $db->query('SELECT dst.*, dc.name AS cat_name FROM disability_subtypes dst JOIN disability_categories dc ON dc.id=dst.category_id ORDER BY dc.name, dst.name')->fetchAll();

// Category counts for summary
$catCounts = $db->query(
    'SELECT dc.name, COUNT(sd.id) AS cnt
     FROM disability_categories dc
     LEFT JOIN disability_subtypes dst ON dst.category_id=dc.id
     LEFT JOIN student_disabilities sd ON sd.subtype_id=dst.id
     LEFT JOIN students s ON s.id=sd.student_id
     LEFT JOIN classes c ON c.id=s.class_id AND c.is_ilc=1
     GROUP BY dc.id, dc.name ORDER BY cnt DESC'
)->fetchAll();

pageHead('Disability Records', 'ilc_vp');
$links = getIlcLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'disabilities', $links, $user); ?>
<div class="main-area">
<?php topbar('Disability Records', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- ILC branding strip -->
<div class="d-flex align-items-center gap-3 mb-4 p-3" style="background:linear-gradient(90deg,#ecfeff,#f0fdf4);border-radius:10px;border:1px solid #a5f3fc;">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#0891b2;letter-spacing:.8px;text-transform:uppercase">Inclusive Learning Centre</div>
    <div style="font-size:.8rem;color:#475569">Bahria Model College Bin Qasim</div>
  </div>
</div>

<div class="row g-3">
  <!-- Category summary sidebar -->
  <div class="col-lg-3">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-chart-pie me-2"></i>By Category</div>
      <div style="padding:12px 16px">
        <a href="<?= url('/portal/ilc/disabilities.php') . ($search?'?q='.urlencode($search):'') ?>"
           class="d-flex justify-content-between mb-2 text-decoration-none <?= !$catFilter?'fw-bold':'' ?>" style="font-size:.84rem;color:inherit">
          <span>All Categories</span>
          <span class="badge" style="background:#0891b2"><?= count($records) ?></span>
        </a>
        <?php foreach ($catCounts as $cc): ?>
        <?php $catRow = array_filter($categories, fn($c) => $c['name']===$cc['name']); $catRow = reset($catRow); ?>
        <a href="?cat=<?= $catRow['id'] ?><?= $search?'&q='.urlencode($search):'' ?>"
           class="d-flex justify-content-between mb-2 text-decoration-none <?= $catFilter==$catRow['id']?'fw-bold':'' ?>"
           style="font-size:.82rem;color:inherit">
          <span><?= h($cc['name']) ?></span>
          <span class="badge bg-secondary"><?= $cc['cnt'] ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Records table -->
  <div class="col-lg-9">
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-heartbeat me-2"></i>Records (<?= count($records) ?>)</span>
        <form method="GET" class="d-flex gap-2">
          <?php if ($catFilter): ?><input type="hidden" name="cat" value="<?= $catFilter ?>"><?php endif; ?>
          <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm"
                 placeholder="Search student…" style="width:170px">
          <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
          <?php if ($search||$catFilter): ?><a href="<?= url('/portal/ilc/disabilities.php') ?>" class="btn btn-sm btn-outline-danger">Clear</a><?php endif; ?>
        </form>
      </div>

      <?php if (empty($records)): ?>
      <div style="padding:40px;text-align:center;color:var(--t2);font-size:.85rem">
        No disability records found. Go to a <a href="<?= url('/portal/ilc/students.php') ?>">student profile</a> to add records.
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.84rem">
          <thead class="table-light">
            <tr><th>Student</th><th>Roll No</th><th>Class</th><th>Category</th><th>Subtype</th><th>Notes</th><th>Recorded</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($records as $r): ?>
            <tr>
              <td class="fw-semibold"><?= h($r['student_name']) ?></td>
              <td><?= h($r['roll_no']) ?></td>
              <td><span class="badge bg-secondary"><?= h($r['class_name']) ?></span></td>
              <td><span class="badge" style="background:#0891b2;font-size:.72rem"><?= h($r['cat_name']) ?></span></td>
              <td><?= h($r['subtype_name']) ?></td>
              <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($r['notes']??'') ?>"><?= h($r['notes'] ?: '—') ?></td>
              <td style="font-size:.78rem"><?= fDate($r['created_at']) ?></td>
              <td>
                <a href="<?= url('/portal/ilc/student-profile.php') ?>?id=<?= $r['student_id'] ?>" class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 7px">
                  <i class="fas fa-user"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Manage Taxonomy: Categories + Subtypes -->
<div class="row g-3 mt-1">
  <div class="col-lg-6">
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-tags me-2"></i>Manage Categories</span>
        <button class="btn btn-xs btn-success" data-bs-toggle="collapse" data-bs-target="#addCatForm" style="font-size:.76rem;padding:2px 9px">
          <i class="fas fa-plus me-1"></i>Add
        </button>
      </div>
      <!-- Add category form (collapsed by default) -->
      <div id="addCatForm" class="collapse" style="padding:12px 16px;border-bottom:1px solid var(--border)">
        <form method="POST" class="d-flex gap-2">
          <input type="hidden" name="action" value="add_category">
          <input type="text" name="cat_name" class="form-control form-control-sm" placeholder="New category name…" required style="max-width:220px">
          <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-plus me-1"></i>Add</button>
        </form>
      </div>
      <div style="padding:0">
        <?php if (empty($categories)): ?>
        <div style="padding:20px;text-align:center;color:var(--t2);font-size:.84rem">No categories yet.</div>
        <?php else: ?>
        <table class="table table-sm mb-0" style="font-size:.83rem">
          <thead class="table-light"><tr><th>#</th><th>Name</th><th style="width:60px"></th></tr></thead>
          <tbody>
            <?php foreach ($categories as $cat): ?>
            <tr>
              <td class="text-muted"><?= $cat['id'] ?></td>
              <td class="fw-semibold"><?= h($cat['name']) ?></td>
              <td>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete category &quot;<?= h(addslashes($cat['name'])) ?>&quot; and ALL its subtypes?')">
                  <input type="hidden" name="action" value="delete_category">
                  <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                  <button class="btn btn-xs btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-tag me-2"></i>Manage Subtypes</span>
        <button class="btn btn-xs btn-success" data-bs-toggle="collapse" data-bs-target="#addSubForm" style="font-size:.76rem;padding:2px 9px">
          <i class="fas fa-plus me-1"></i>Add
        </button>
      </div>
      <!-- Add subtype form (collapsed by default) -->
      <div id="addSubForm" class="collapse" style="padding:12px 16px;border-bottom:1px solid var(--border)">
        <form method="POST" class="row g-2">
          <input type="hidden" name="action" value="add_subtype">
          <div class="col-5">
            <select name="sub_cat_id" class="form-select form-select-sm" required>
              <option value="">— Category —</option>
              <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= h($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-5">
            <input type="text" name="sub_name" class="form-control form-control-sm" placeholder="Subtype name…" required>
          </div>
          <div class="col-2">
            <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-plus"></i></button>
          </div>
        </form>
      </div>
      <div style="padding:0;max-height:300px;overflow-y:auto">
        <?php if (empty($allSubtypes)): ?>
        <div style="padding:20px;text-align:center;color:var(--t2);font-size:.84rem">No subtypes yet.</div>
        <?php else: ?>
        <table class="table table-sm mb-0" style="font-size:.81rem">
          <thead class="table-light"><tr><th>Category</th><th>Subtype</th><th style="width:60px"></th></tr></thead>
          <tbody>
            <?php foreach ($allSubtypes as $sub): ?>
            <tr>
              <td><span class="badge" style="background:#0891b2;font-size:.68rem"><?= h($sub['cat_name']) ?></span></td>
              <td><?= h($sub['name']) ?></td>
              <td>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete subtype &quot;<?= h(addslashes($sub['name'])) ?>&quot;?')">
                  <input type="hidden" name="action" value="delete_subtype">
                  <input type="hidden" name="sub_id" value="<?= $sub['id'] ?>">
                  <button class="btn btn-xs btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

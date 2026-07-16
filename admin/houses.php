<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

// ── POST Handlers ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { $db->query('SELECT 1 FROM houses LIMIT 0'); } catch (PDOException $e) {
        setFlash('danger', 'Houses table does not exist yet. Run houses-migration.sql first.');
        redirect('/admin/houses.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['name']  ?? '');
        $color = trim($_POST['color'] ?? '#3b82f6');
        if ($name === '') {
            setFlash('danger', 'House name is required.');
        } else {
            $db->prepare('INSERT INTO houses (name, color) VALUES (?, ?)')
               ->execute([$name, $color]);
            logActivity($user['id'], 'house_add', "Added house: $name");
            setFlash('success', "House \"$name\" added successfully.");
        }
        redirect('/admin/houses.php');
    }

    if ($action === 'edit') {
        $id    = (int)($_POST['id']    ?? 0);
        $name  = trim($_POST['name']   ?? '');
        $color = trim($_POST['color']  ?? '#3b82f6');
        if ($id && $name !== '') {
            $db->prepare('UPDATE houses SET name = ?, color = ? WHERE id = ?')
               ->execute([$name, $color, $id]);
            logActivity($user['id'], 'house_edit', "Updated house #$id: $name");
            setFlash('success', 'House updated successfully.');
        } else {
            setFlash('danger', 'Invalid data.');
        }
        redirect('/admin/houses.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            // Check if any students are assigned
            $count = (int)$db->prepare('SELECT COUNT(*) FROM students WHERE house_id = ?')
                             ->execute([$id]) ? $db->query("SELECT COUNT(*) FROM students WHERE house_id = $id")->fetchColumn() : 0;
            $st = $db->prepare('SELECT COUNT(*) FROM students WHERE house_id = ?');
            $st->execute([$id]);
            $count = (int)$st->fetchColumn();
            if ($count > 0) {
                setFlash('danger', "Cannot delete: $count student(s) are assigned to this house. Reassign them first.");
            } else {
                $nameSt = $db->prepare('SELECT name FROM houses WHERE id = ?');
                $nameSt->execute([$id]);
                $houseName = $nameSt->fetchColumn();
                $db->prepare('DELETE FROM houses WHERE id = ?')->execute([$id]);
                logActivity($user['id'], 'house_delete', "Deleted house: $houseName");
                setFlash('success', 'House deleted.');
            }
        }
        redirect('/admin/houses.php');
    }
}

// ── Fetch houses with student counts ─────────────────────────────
$houses        = [];
$tablesMissing = false;
try {
    $houses = $db->query(
        'SELECT h.*, COUNT(s.id) AS student_count
         FROM houses h
         LEFT JOIN students s ON s.house_id = h.id
         GROUP BY h.id
         ORDER BY h.name'
    )->fetchAll();
} catch (PDOException $e) {
    $tablesMissing = true;
}

pageHead('Houses', 'admin');
$links = getAdminLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('admin', 'houses', $links, $user); ?>
<div class="main-area">
<?php topbar('Houses', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if ($tablesMissing): ?>
<div class="alert alert-warning d-flex gap-3 align-items-start" style="border-radius:8px">
  <i class="fas fa-database fa-lg mt-1"></i>
  <div>
    <strong>Database table missing.</strong>
    The <code>houses</code> table has not been created yet. Run the migration below in phpMyAdmin
    (<strong>SQL</strong> tab) and then refresh this page.
    <pre class="mt-2 mb-0 p-2" style="background:#f8fafc;border-radius:6px;font-size:.8rem;border:1px solid #e5e7eb">USE bmc_portal;

CREATE TABLE IF NOT EXISTS houses (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  color VARCHAR(20) NOT NULL DEFAULT '#3b82f6',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE students ADD COLUMN IF NOT EXISTS house_id INT NULL,
  ADD CONSTRAINT fk_student_house FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE SET NULL;

INSERT IGNORE INTO houses (id, name, color) VALUES
  (1,'Allama Iqbal','#3b82f6'),(2,'Quaid-e-Azam','#22c55e'),
  (3,'Fatima Jinnah','#f97316'),(4,'Sir Syed','#a855f7');</pre>
    <div class="mt-2" style="font-size:.82rem">Or import <strong>database/houses-migration.sql</strong> from the project folder.</div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">

  <!-- Add House Form -->
  <div class="col-lg-4">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-plus me-2"></i>Add New House</div>
      <div style="padding:16px">
        <form method="POST">
          <input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">House Name</label>
            <input type="text" name="name" class="form-control form-control-sm"
                   placeholder="e.g. Allama Iqbal" required maxlength="100">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">House Color</label>
            <div class="d-flex align-items-center gap-2">
              <input type="color" name="color" class="form-control form-control-color form-control-sm"
                     value="#3b82f6" style="width:50px;height:34px;padding:2px">
              <span class="text-muted" style="font-size:.8rem">Pick a color for the house badge</span>
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
      <div class="sec-card-header">
        <span><i class="fas fa-shield-alt me-2"></i>All Houses</span>
        <span class="badge bg-secondary"><?= count($houses) ?> houses</span>
      </div>
      <?php if (empty($houses)): ?>
        <div class="p-4 text-center text-muted">
          <i class="fas fa-shield-alt fa-2x mb-2 d-block opacity-25"></i>
          No houses defined yet. Add your first house.
        </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th style="width:50px">#</th>
              <th>House</th>
              <th style="width:100px">Color</th>
              <th style="width:110px">Students</th>
              <th style="width:120px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($houses as $i => $h): ?>
            <tr>
              <td class="text-muted"><?= $i + 1 ?></td>
              <td>
                <span class="badge-house" style="background:<?= h($h['color']) ?>">
                  <i class="fas fa-shield-alt"></i> <?= h($h['name']) ?>
                </span>
              </td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:<?= h($h['color']) ?>;border:1px solid #ddd"></span>
                  <code style="font-size:.78rem"><?= h($h['color']) ?></code>
                </div>
              </td>
              <td>
                <span class="badge bg-light text-dark border">
                  <i class="fas fa-user-graduate me-1"></i><?= h($h['student_count']) ?>
                </span>
              </td>
              <td>
                <button class="btn btn-xs btn-outline-primary me-1"
                        data-bs-toggle="modal" data-bs-target="#editModal<?= $h['id'] ?>">
                  <i class="fas fa-edit"></i>
                </button>
                <?php if ($h['student_count'] == 0): ?>
                <form method="POST" class="d-inline"
                      onsubmit="return confirm('Delete house <?= h($h['name']) ?>?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $h['id'] ?>">
                  <button type="submit" class="btn btn-xs btn-outline-danger">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
                <?php else: ?>
                <button class="btn btn-xs btn-outline-secondary" disabled title="Remove students first">
                  <i class="fas fa-trash"></i>
                </button>
                <?php endif; ?>
              </td>
            </tr>

            <!-- Edit Modal for this house -->
            <div class="modal fade" id="editModal<?= $h['id'] ?>" tabindex="-1">
              <div class="modal-dialog modal-sm">
                <div class="modal-content">
                  <div class="modal-header py-2">
                    <h6 class="modal-title">Edit House</h6>
                    <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
                  </div>
                  <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?= $h['id'] ?>">
                    <div class="modal-body">
                      <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:.85rem">House Name</label>
                        <input type="text" name="name" class="form-control form-control-sm"
                               value="<?= h($h['name']) ?>" required maxlength="100">
                      </div>
                      <div class="mb-2">
                        <label class="form-label fw-semibold" style="font-size:.85rem">Color</label>
                        <input type="color" name="color" class="form-control form-control-color form-control-sm"
                               value="<?= h($h['color']) ?>" style="width:100%;height:34px;padding:2px">
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
  </div>

</div><!-- /row -->
</div><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /portal-wrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

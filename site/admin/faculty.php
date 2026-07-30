<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = requireSiteAdmin();
$db    = siteDB();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_faculty') {
        $name      = trim($_POST['name'] ?? '');
        $desig     = trim($_POST['designation'] ?? '');
        $qual      = trim($_POST['qualification'] ?? '');
        $deptId    = (int)($_POST['department_id'] ?? 0) ?: null;
        $email     = trim($_POST['email'] ?? '') ?: null;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $active    = isset($_POST['is_active']) ? 1 : 0;
        $image     = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            $mime = mime_content_type($_FILES['image']['tmp_name']);
            if (in_array($mime, ['image/jpeg','image/png','image/webp'])) {
                $ext   = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime];
                $image = 'fac_' . bin2hex(random_bytes(6)) . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], SITE_UPLOAD . 'faculty/' . $image);
            }
        }
        try {
            $db->prepare('INSERT INTO site_faculty (name,designation,qualification,department_id,email,image,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?)')
               ->execute([$name,$desig,$qual,$deptId,$email,$image,$sortOrder,$active]);
            $msg = 'Faculty member added.';
        } catch (Exception $e) { $err = $e->getMessage(); }
    }

    if ($action === 'delete_faculty') {
        $id = (int)($_POST['faculty_id'] ?? 0);
        $st = $db->prepare('SELECT image FROM site_faculty WHERE id=?');
        $st->execute([$id]);
        if ($r = $st->fetch()) { if ($r['image']) @unlink(SITE_UPLOAD . 'faculty/' . $r['image']); }
        $db->prepare('DELETE FROM site_faculty WHERE id=?')->execute([$id]);
        $msg = 'Faculty member deleted.';
    }

    if ($action === 'toggle_active') {
        $id  = (int)($_POST['faculty_id'] ?? 0);
        $cur = $db->prepare('SELECT is_active FROM site_faculty WHERE id=?');
        $cur->execute([$id]);
        $c = $cur->fetchColumn();
        $db->prepare('UPDATE site_faculty SET is_active=? WHERE id=?')->execute([$c ? 0 : 1, $id]);
        $msg = 'Status updated.';
    }

    if ($action === 'add_dept') {
        $dname = trim($_POST['dept_name'] ?? '');
        if ($dname) {
            try {
                $db->prepare('INSERT INTO site_departments (name,is_active) VALUES (?,1)')->execute([$dname]);
                $msg = 'Department added.';
            } catch (Exception $e) { $err = $e->getMessage(); }
        }
    }

    if ($action === 'delete_dept') {
        $id = (int)($_POST['dept_id'] ?? 0);
        $db->prepare('DELETE FROM site_departments WHERE id=?')->execute([$id]);
        $msg = 'Department deleted.';
    }
}

try {
    $facultyList = $db->query('SELECT f.*,d.name AS dept_name FROM site_faculty f LEFT JOIN site_departments d ON d.id=f.department_id ORDER BY f.sort_order,f.name')->fetchAll();
    $depts       = $db->query('SELECT * FROM site_departments WHERE is_active=1 ORDER BY sort_order,name')->fetchAll();
} catch (Exception $e) { $facultyList = []; $depts = []; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Faculty — BMC Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-main">
    <header class="admin-topbar">
      <div><span style="font-weight:700;color:var(--primary)">Faculty</span></div>
      <div style="font-size:.85rem;color:var(--text-2)">Welcome, <?= sh($admin['name']) ?></div>
    </header>
    <div class="admin-content">
      <?php if ($msg): ?><div class="alert alert-success auto-dismiss"><?= sh($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-danger"><?= sh($err) ?></div><?php endif; ?>

      <div class="row g-4 mb-4">
        <div class="col-lg-8">
          <div class="admin-card">
            <div class="admin-card-header" data-bs-toggle="collapse" data-bs-target="#addFacultyForm" style="cursor:pointer">
              <span><i class="fas fa-plus me-2"></i>Add Faculty Member</span>
              <i class="fas fa-chevron-down"></i>
            </div>
            <div id="addFacultyForm" class="collapse">
              <div class="admin-card-body">
                <form method="POST" enctype="multipart/form-data" class="row g-3">
                  <input type="hidden" name="action" value="add_faculty">
                  <div class="col-md-6">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Designation</label>
                    <input type="text" name="designation" class="form-control" placeholder="e.g. Lecturer Physics">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Qualification</label>
                    <input type="text" name="qualification" class="form-control" placeholder="e.g. MSc Physics">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                      <option value="">— Select —</option>
                      <?php foreach ($depts as $d): ?>
                      <option value="<?= $d['id'] ?>"><?= sh($d['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-5">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Photo</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                  </div>
                  <div class="col-12 d-flex align-items-center gap-3">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="is_active" id="facActive" checked>
                      <label class="form-check-label" for="facActive">Active</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Member</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="admin-card">
            <div class="admin-card-header"><i class="fas fa-building me-2"></i>Departments</div>
            <div class="admin-card-body">
              <form method="POST" class="d-flex gap-2 mb-3">
                <input type="hidden" name="action" value="add_dept">
                <input type="text" name="dept_name" class="form-control" placeholder="Department name" required>
                <button type="submit" class="btn btn-primary btn-sm">Add</button>
              </form>
              <?php foreach ($depts as $d): ?>
              <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size:.85rem">
                <span><?= sh($d['name']) ?></span>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete department?')">
                  <input type="hidden" name="action" value="delete_dept">
                  <input type="hidden" name="dept_id" value="<?= $d['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger py-0 px-1"><i class="fas fa-times"></i></button>
                </form>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="admin-card">
        <div class="admin-card-header"><i class="fas fa-chalkboard-teacher me-2"></i>Faculty List (<?= count($facultyList) ?>)</div>
        <div class="admin-card-body p-0">
          <div class="table-responsive">
            <table class="admin-table">
              <thead><tr><th>Photo</th><th>Name</th><th>Designation</th><th>Department</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <?php if (empty($facultyList)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No faculty yet.</td></tr>
                <?php else: foreach ($facultyList as $f): ?>
                <tr>
                  <td>
                    <?php if ($f['image']): ?>
                    <img src="<?= uploadUrl('faculty', $f['image']) ?>" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover">
                    <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center"><i class="fas fa-user-tie text-white" style="font-size:.8rem"></i></div>
                    <?php endif; ?>
                  </td>
                  <td><strong><?= sh($f['name']) ?></strong><?php if ($f['email']): ?><br><small class="text-muted"><?= sh($f['email']) ?></small><?php endif; ?></td>
                  <td><?= sh($f['designation'] ?: '—') ?><?php if ($f['qualification']): ?><br><small class="text-muted"><?= sh($f['qualification']) ?></small><?php endif; ?></td>
                  <td><?= sh($f['dept_name'] ?: '—') ?></td>
                  <td>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="action" value="toggle_active">
                      <input type="hidden" name="faculty_id" value="<?= $f['id'] ?>">
                      <button class="badge border-0 bg-<?= $f['is_active']?'success':'secondary' ?>"><?= $f['is_active']?'Active':'Inactive' ?></button>
                    </form>
                  </td>
                  <td>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')">
                      <input type="hidden" name="action" value="delete_faculty">
                      <input type="hidden" name="faculty_id" value="<?= $f['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>setTimeout(()=>{document.querySelectorAll('.auto-dismiss').forEach(e=>{e.style.opacity='0';setTimeout(()=>e.remove(),400)})},4000)</script>
</body></html>

<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = requireSiteAdmin();
$db    = siteDB();
$msg   = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name  = trim($_POST['name'] ?? '');
        $desig = trim($_POST['designation'] ?? '');
        $cont  = trim($_POST['content'] ?? '');
        $rating= min(5, max(1, (int)($_POST['rating'] ?? 5)));
        $image = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            $mime = mime_content_type($_FILES['image']['tmp_name']);
            if (in_array($mime, ['image/jpeg','image/png','image/webp'])) {
                $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime];
                $image = 'tm_' . bin2hex(random_bytes(6)) . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], SITE_UPLOAD . 'testimonials/' . $image);
            }
        }
        try {
            $db->prepare('INSERT INTO site_testimonials (name,designation,content,image,rating) VALUES (?,?,?,?,?)')->execute([$name,$desig,$cont,$image,$rating]);
            $msg = 'Testimonial added.';
        } catch (Exception $e) { $err = $e->getMessage(); }
    }
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $cur = $db->prepare('SELECT is_active FROM site_testimonials WHERE id=?'); $cur->execute([$id]);
        $c = $cur->fetchColumn();
        $db->prepare('UPDATE site_testimonials SET is_active=? WHERE id=?')->execute([$c?0:1,$id]);
        $msg = 'Status updated.';
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM site_testimonials WHERE id=?')->execute([$id]);
        $msg = 'Testimonial deleted.';
    }
}

$testimonials = $db->query('SELECT * FROM site_testimonials ORDER BY id DESC')->fetchAll();
?>
<!doctype html><html lang="en"><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Testimonials — BMC Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head><body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-main">
    <header class="admin-topbar">
      <span style="font-weight:700;color:var(--primary)">Testimonials</span>
      <div style="font-size:.85rem;color:var(--text-2)">Welcome, <?= sh($admin['name']) ?></div>
    </header>
    <div class="admin-content">
      <?php if ($msg): ?><div class="alert alert-success auto-dismiss"><?= sh($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-danger"><?= sh($err) ?></div><?php endif; ?>
      <div class="admin-card mb-4">
        <div class="admin-card-header" data-bs-toggle="collapse" data-bs-target="#addForm" style="cursor:pointer">
          <span><i class="fas fa-plus me-2"></i>Add Testimonial</span><i class="fas fa-chevron-down"></i>
        </div>
        <div id="addForm" class="collapse">
          <div class="admin-card-body">
            <form method="POST" enctype="multipart/form-data" class="row g-3">
              <input type="hidden" name="action" value="add">
              <div class="col-md-6"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label">Designation</label><input type="text" name="designation" class="form-control" placeholder="Alumni 2022 / Parent / Student"></div>
              <div class="col-12"><label class="form-label">Testimonial *</label><textarea name="content" class="form-control" rows="3" required></textarea></div>
              <div class="col-md-4"><label class="form-label">Rating</label>
                <select name="rating" class="form-select"><option value="5">⭐⭐⭐⭐⭐ (5)</option><option value="4">⭐⭐⭐⭐ (4)</option><option value="3">⭐⭐⭐ (3)</option></select>
              </div>
              <div class="col-md-4"><label class="form-label">Photo (optional)</label><input type="file" name="image" class="form-control" accept="image/*"></div>
              <div class="col-12"><button type="submit" class="btn btn-primary">Add</button></div>
            </form>
          </div>
        </div>
      </div>
      <div class="admin-card">
        <div class="admin-card-header"><i class="fas fa-quote-right me-2"></i>All Testimonials (<?= count($testimonials) ?>)</div>
        <div class="admin-card-body p-0">
          <table class="admin-table">
            <thead><tr><th>Name</th><th>Designation</th><th>Rating</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($testimonials as $t): ?>
              <tr>
                <td><strong><?= sh($t['name']) ?></strong></td>
                <td><?= sh($t['designation']) ?></td>
                <td><?php for($s=0;$s<$t['rating'];$s++) echo '⭐'; ?></td>
                <td>
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <button class="badge border-0 bg-<?= $t['is_active']?'success':'secondary' ?>"><?= $t['is_active']?'Active':'Hidden' ?></button>
                  </form>
                </td>
                <td>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')">
                    <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>setTimeout(()=>{document.querySelectorAll('.auto-dismiss').forEach(e=>{e.style.opacity='0';setTimeout(()=>e.remove(),400)})},4000)</script>
</body></html>

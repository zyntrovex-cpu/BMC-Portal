<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = requireSiteAdmin();
$db    = siteDB();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_download') {
        $title     = trim($_POST['title'] ?? '');
        $desc      = trim($_POST['description'] ?? '');
        $category  = trim($_POST['category'] ?? 'General');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $fileName  = null;
        $fileSize  = null;
        if (!empty($_FILES['file']['tmp_name'])) {
            $origName = basename($_FILES['file']['name']);
            $fileName = 'dl_' . bin2hex(random_bytes(6)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $origName);
            $fSize    = $_FILES['file']['size'];
            $fileSize = $fSize >= 1048576 ? round($fSize/1048576, 1) . ' MB' : round($fSize/1024, 0) . ' KB';
            move_uploaded_file($_FILES['file']['tmp_name'], SITE_UPLOAD . 'downloads/' . $fileName);
        }
        try {
            $db->prepare('INSERT INTO site_downloads (title,description,category,file_name,file_size,sort_order) VALUES (?,?,?,?,?,?)')
               ->execute([$title,$desc,$category,$fileName,$fileSize,$sortOrder]);
            $msg = 'Document uploaded.';
        } catch (Exception $e) { $err = $e->getMessage(); }
    }

    if ($action === 'delete_download') {
        $id = (int)($_POST['dl_id'] ?? 0);
        $st = $db->prepare('SELECT file_name FROM site_downloads WHERE id=?');
        $st->execute([$id]);
        if ($r = $st->fetch()) { if ($r['file_name']) @unlink(SITE_UPLOAD . 'downloads/' . $r['file_name']); }
        $db->prepare('DELETE FROM site_downloads WHERE id=?')->execute([$id]);
        $msg = 'Document deleted.';
    }
}

$categoryList = ['Admission Forms','Prospectus','Fee Structure','Academic Calendar','Examination','Notices','Policies','General'];
try { $downloads = $db->query('SELECT * FROM site_downloads ORDER BY category,sort_order,created_at DESC')->fetchAll(); }
catch (Exception $e) { $downloads = []; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Downloads — BMC Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-main">
    <header class="admin-topbar">
      <div><span style="font-weight:700;color:var(--primary)">Downloads</span></div>
      <div style="font-size:.85rem;color:var(--text-2)">Welcome, <?= sh($admin['name']) ?></div>
    </header>
    <div class="admin-content">
      <?php if ($msg): ?><div class="alert alert-success auto-dismiss"><?= sh($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-danger"><?= sh($err) ?></div><?php endif; ?>

      <div class="admin-card mb-4">
        <div class="admin-card-header" data-bs-toggle="collapse" data-bs-target="#addDlForm" style="cursor:pointer">
          <span><i class="fas fa-plus me-2"></i>Upload Document</span>
          <i class="fas fa-chevron-down"></i>
        </div>
        <div id="addDlForm" class="collapse">
          <div class="admin-card-body">
            <form method="POST" enctype="multipart/form-data" class="row g-3">
              <input type="hidden" name="action" value="add_download">
              <div class="col-md-7">
                <label class="form-label">Document Title *</label>
                <input type="text" name="title" class="form-control" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Category</label>
                <select name="category" class="form-select">
                  <?php foreach ($categoryList as $c): ?><option><?= $c ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="0">
              </div>
              <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
              </div>
              <div class="col-md-8">
                <label class="form-label">File * (PDF, DOC, XLS, ZIP)</label>
                <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip" required>
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-upload me-2"></i>Upload</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="admin-card">
        <div class="admin-card-header"><i class="fas fa-file-download me-2"></i>All Documents (<?= count($downloads) ?>)</div>
        <div class="admin-card-body p-0">
          <div class="table-responsive">
            <table class="admin-table">
              <thead><tr><th>Title</th><th>Category</th><th>Size</th><th>Uploaded</th><th>Actions</th></tr></thead>
              <tbody>
                <?php if (empty($downloads)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No documents yet.</td></tr>
                <?php else: foreach ($downloads as $d): ?>
                <?php $ext = strtolower(pathinfo($d['file_name'] ?? '', PATHINFO_EXTENSION)); ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <i class="fas <?= $ext==='pdf'?'fa-file-pdf text-danger':($ext==='doc'||$ext==='docx'?'fa-file-word text-primary':'fa-file-alt text-muted') ?>"></i>
                      <div>
                        <strong><?= sh($d['title']) ?></strong>
                        <?php if ($d['description']): ?><br><small class="text-muted"><?= sh(truncateText($d['description'],60)) ?></small><?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td><span class="badge bg-secondary"><?= sh($d['category']) ?></span></td>
                  <td><?= sh($d['file_size'] ?: '—') ?></td>
                  <td style="font-size:.8rem"><?= date('d M Y', strtotime($d['created_at'])) ?></td>
                  <td>
                    <?php if ($d['file_name']): ?>
                    <a href="<?= uploadUrl('downloads', $d['file_name']) ?>" class="btn btn-sm btn-outline-primary me-1" download><i class="fas fa-download"></i></a>
                    <?php endif; ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete file?')">
                      <input type="hidden" name="action" value="delete_download">
                      <input type="hidden" name="dl_id" value="<?= $d['id'] ?>">
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

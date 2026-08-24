<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = requireSiteAdmin();
$db    = siteDB();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_download') {
        $title    = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $filename = null;
        $fileSize = null;
        if (!empty($_FILES['file']['tmp_name'])) {
            $origName = basename($_FILES['file']['name']);
            $filename = 'dl_' . bin2hex(random_bytes(6)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $origName);
            $fSize    = $_FILES['file']['size'];
            $fileSize = $fSize >= 1048576 ? round($fSize/1048576, 1) . ' MB' : round($fSize/1024, 0) . ' KB';
            $dlDir    = SITE_UPLOAD . 'downloads/';
            if (!is_dir($dlDir)) { @mkdir($dlDir, 0755, true); }
            move_uploaded_file($_FILES['file']['tmp_name'], $dlDir . $filename);
        }
        if (!$title) { $err = 'Title is required.'; }
        elseif (!$filename) { $err = 'Please select a file.'; }
        else {
            try {
                $db->prepare('INSERT INTO site_downloads (title,category,filename,file_size) VALUES (?,?,?,?)')
                   ->execute([$title, $category, $filename, $fileSize]);
                $msg = 'Document uploaded successfully.';
            } catch (Exception $e) { $err = $e->getMessage(); }
        }
    }

    if ($action === 'delete_download') {
        $id = (int)($_POST['dl_id'] ?? 0);
        $st = $db->prepare('SELECT filename FROM site_downloads WHERE id=?');
        $st->execute([$id]);
        if ($r = $st->fetch()) {
            if ($r['filename']) @unlink(SITE_UPLOAD . 'downloads/' . $r['filename']);
        }
        $db->prepare('DELETE FROM site_downloads WHERE id=?')->execute([$id]);
        $msg = 'Document deleted.';
    }

    if ($action === 'toggle_active') {
        $id  = (int)($_POST['dl_id'] ?? 0);
        $cur = $db->prepare('SELECT is_active FROM site_downloads WHERE id=?');
        $cur->execute([$id]);
        $c = $cur->fetchColumn();
        $db->prepare('UPDATE site_downloads SET is_active=? WHERE id=?')->execute([$c ? 0 : 1, $id]);
        $msg = 'Status updated.';
    }
}

$categoryList = ['Admission Forms','Prospectus','Fee Structure','Academic Calendar','Examination','Notices','Policies','General'];
try {
    $downloads = $db->query('SELECT * FROM site_downloads ORDER BY category, created_at DESC')->fetchAll();
} catch (Exception $e) { $downloads = []; }

$grouped = [];
foreach ($downloads as $d) { $grouped[$d['category']][] = $d; }
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

      <!-- Upload Form -->
      <div class="admin-card mb-4">
        <div class="admin-card-header" data-bs-toggle="collapse" data-bs-target="#addDlForm" style="cursor:pointer">
          <span><i class="fas fa-plus me-2"></i>Upload Document</span>
          <i class="fas fa-chevron-down"></i>
        </div>
        <div id="addDlForm" class="collapse">
          <div class="admin-card-body">
            <form method="POST" enctype="multipart/form-data" class="row g-3">
              <input type="hidden" name="action" value="add_download">
              <div class="col-md-8">
                <label class="form-label">Document Title *</label>
                <input type="text" name="title" class="form-control" required placeholder="e.g. Admission Form 2025-26">
              </div>
              <div class="col-md-4">
                <label class="form-label">Category</label>
                <select name="category" class="form-select">
                  <?php foreach ($categoryList as $c): ?><option><?= $c ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-8">
                <label class="form-label">File * (PDF, DOC, XLS, ZIP)</label>
                <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.ppt,.pptx" required>
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-upload me-2"></i>Upload</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Documents List — grouped by category -->
      <?php if (empty($downloads)): ?>
      <div class="admin-card"><div class="admin-card-body text-center text-muted py-4">No documents yet. Upload your first document above.</div></div>
      <?php else: ?>
      <?php foreach ($grouped as $cat => $items): ?>
      <div class="admin-card mb-3">
        <div class="admin-card-header">
          <span><i class="fas fa-folder me-2"></i><?= sh($cat) ?> <span class="badge bg-secondary ms-1"><?= count($items) ?></span></span>
        </div>
        <div class="admin-card-body p-0">
          <table class="admin-table">
            <thead><tr><th>Title</th><th>Size</th><th>Uploaded</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($items as $d):
                $ext = strtolower(pathinfo($d['filename'] ?? '', PATHINFO_EXTENSION));
                $icon = match($ext) {
                  'pdf'           => 'fa-file-pdf text-danger',
                  'doc', 'docx'  => 'fa-file-word text-primary',
                  'xls', 'xlsx'  => 'fa-file-excel text-success',
                  'zip'           => 'fa-file-archive text-warning',
                  default         => 'fa-file-alt text-muted'
                };
              ?>
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <i class="fas <?= $icon ?>" style="font-size:1.1rem"></i>
                    <strong><?= sh($d['title']) ?></strong>
                  </div>
                </td>
                <td><?= sh($d['file_size'] ?: '—') ?></td>
                <td style="font-size:.8rem"><?= date('d M Y', strtotime($d['created_at'])) ?></td>
                <td>
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="dl_id" value="<?= $d['id'] ?>">
                    <button class="badge border-0 bg-<?= $d['is_active']?'success':'secondary' ?>"><?= $d['is_active']?'Active':'Hidden' ?></button>
                  </form>
                </td>
                <td>
                  <?php if ($d['filename']): ?>
                  <a href="<?= uploadUrl('downloads', $d['filename']) ?>" class="btn btn-sm btn-outline-primary me-1" target="_blank" download title="Download">
                    <i class="fas fa-download"></i>
                  </a>
                  <?php endif; ?>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Delete this document?')">
                    <input type="hidden" name="action" value="delete_download">
                    <input type="hidden" name="dl_id" value="<?= $d['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>

    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>setTimeout(()=>{document.querySelectorAll('.auto-dismiss').forEach(e=>{e.style.opacity='0';setTimeout(()=>e.remove(),400)})},4000)</script>
</body></html>

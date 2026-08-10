<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = requireSiteAdmin();
$db    = siteDB();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_notice') {
        $title    = trim($_POST['title'] ?? '');
        $content  = trim($_POST['content'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $priority = trim($_POST['priority'] ?? 'normal');
        $expires  = $_POST['expires_at'] ?: null;
        $pub      = isset($_POST['is_published']) ? 1 : 0;
        $attach   = null;
        if (!empty($_FILES['attachment']['tmp_name'])) {
            $origName   = basename($_FILES['attachment']['name']);
            $attach     = 'notice_' . bin2hex(random_bytes(6)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $origName);
            $noticeDir  = SITE_UPLOAD . 'notices/';
            if (!is_dir($noticeDir)) { @mkdir($noticeDir, 0755, true); }
            move_uploaded_file($_FILES['attachment']['tmp_name'], $noticeDir . $attach);
        }
        try {
            $db->prepare('INSERT INTO site_notices (title,content,category,priority,expires_at,attachment,is_published) VALUES (?,?,?,?,?,?,?)')
               ->execute([$title,$content,$category,$priority,$expires,$attach,$pub]);
            $msg = 'Notice added.';
        } catch (Exception $e) { $err = $e->getMessage(); }
    }

    if ($action === 'delete_notice') {
        $id = (int)($_POST['notice_id'] ?? 0);
        $st = $db->prepare('SELECT attachment FROM site_notices WHERE id=?');
        $st->execute([$id]);
        if ($r = $st->fetch()) { if ($r['attachment']) @unlink(SITE_UPLOAD . 'notices/' . $r['attachment']); }
        $db->prepare('DELETE FROM site_notices WHERE id=?')->execute([$id]);
        $msg = 'Notice deleted.';
    }

    if ($action === 'toggle_published') {
        $id  = (int)($_POST['notice_id'] ?? 0);
        $cur = $db->prepare('SELECT is_published FROM site_notices WHERE id=?');
        $cur->execute([$id]);
        $c = $cur->fetchColumn();
        $db->prepare('UPDATE site_notices SET is_published=? WHERE id=?')->execute([$c ? 0 : 1, $id]);
        $msg = 'Status updated.';
    }
}

try { $notices = $db->query('SELECT * FROM site_notices ORDER BY created_at DESC')->fetchAll(); }
catch (Exception $e) { $notices = []; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Notices — BMC Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-main">
    <header class="admin-topbar">
      <div><span style="font-weight:700;color:var(--primary)">Notices</span></div>
      <div style="font-size:.85rem;color:var(--text-2)">Welcome, <?= sh($admin['name']) ?></div>
    </header>
    <div class="admin-content">
      <?php if ($msg): ?><div class="alert alert-success auto-dismiss"><?= sh($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-danger"><?= sh($err) ?></div><?php endif; ?>

      <div class="admin-card mb-4">
        <div class="admin-card-header" data-bs-toggle="collapse" data-bs-target="#addNoticeForm" style="cursor:pointer">
          <span><i class="fas fa-plus me-2"></i>Add Notice</span>
          <i class="fas fa-chevron-down"></i>
        </div>
        <div id="addNoticeForm" class="collapse">
          <div class="admin-card-body">
            <form method="POST" enctype="multipart/form-data" class="row g-3">
              <input type="hidden" name="action" value="add_notice">
              <div class="col-md-6">
                <label class="form-label">Title *</label>
                <input type="text" name="title" class="form-control" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Category</label>
                <select name="category" class="form-select">
                  <?php foreach (['General','Academic','Examination','Admissions','Financial','Sports','Events'] as $c): ?>
                  <option><?= $c ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                  <option value="normal">Normal</option>
                  <option value="important">Important</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Body</label>
                <textarea name="content" class="form-control" rows="4"></textarea>
              </div>
              <div class="col-md-5">
                <label class="form-label">Attachment (PDF/doc)</label>
                <input type="file" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx">
              </div>
              <div class="col-md-4">
                <label class="form-label">Expires On</label>
                <input type="date" name="expires_at" class="form-control">
              </div>
              <div class="col-md-3 d-flex align-items-end pb-1">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="is_published" id="notPub" checked>
                  <label class="form-check-label" for="notPub">Published</label>
                </div>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary">Add Notice</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="admin-card">
        <div class="admin-card-header"><i class="fas fa-bell me-2"></i>All Notices (<?= count($notices) ?>)</div>
        <div class="admin-card-body p-0">
          <div class="table-responsive">
            <table class="admin-table">
              <thead><tr><th>Title</th><th>Category</th><th>Priority</th><th>Expires</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <?php if (empty($notices)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No notices yet.</td></tr>
                <?php else: foreach ($notices as $n): ?>
                <?php $pBadge = ['urgent'=>'danger','important'=>'warning','normal'=>'secondary'][$n['priority']] ?? 'secondary'; ?>
                <tr>
                  <td>
                    <strong><?= sh($n['title']) ?></strong>
                    <?php if ($n['attachment']): ?><br><small class="text-muted"><i class="fas fa-paperclip me-1"></i>Attachment</small><?php endif; ?>
                  </td>
                  <td><span class="badge bg-info text-dark"><?= sh($n['category']) ?></span></td>
                  <td><span class="badge bg-<?= $pBadge ?>"><?= ucfirst($n['priority']) ?></span></td>
                  <td style="font-size:.8rem"><?= $n['expires_at'] ? date('d M Y', strtotime($n['expires_at'])) : '—' ?></td>
                  <td>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="action" value="toggle_published">
                      <input type="hidden" name="notice_id" value="<?= $n['id'] ?>">
                      <button class="badge border-0 bg-<?= $n['is_published']?'success':'secondary' ?>"><?= $n['is_published']?'Live':'Hidden' ?></button>
                    </form>
                  </td>
                  <td>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete notice?')">
                      <input type="hidden" name="action" value="delete_notice">
                      <input type="hidden" name="notice_id" value="<?= $n['id'] ?>">
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

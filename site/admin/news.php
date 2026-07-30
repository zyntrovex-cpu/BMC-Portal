<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = requireSiteAdmin();
$db    = siteDB();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_news') {
        $title    = trim($_POST['title'] ?? '');
        $slug     = slugify($title) . '-' . time();
        $body     = trim($_POST['body'] ?? '');
        $excerpt  = trim($_POST['excerpt'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $pub      = isset($_POST['is_published']) ? 1 : 0;
        $feat     = isset($_POST['is_featured']) ? 1 : 0;
        $pubDate  = $_POST['published_at'] ?: date('Y-m-d H:i:s');
        $image    = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            $mime = mime_content_type($_FILES['image']['tmp_name']);
            if (in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'])) {
                $ext   = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime];
                $image = 'news_' . bin2hex(random_bytes(6)) . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], SITE_UPLOAD . 'news/' . $image);
            }
        }
        try {
            $db->prepare('INSERT INTO site_news (title,slug,body,excerpt,image,category,is_published,is_featured,published_at) VALUES (?,?,?,?,?,?,?,?,?)')
               ->execute([$title,$slug,$body,$excerpt,$image,$category,$pub,$feat,$pubDate]);
            $msg = 'Article added successfully.';
        } catch (Exception $e) { $err = $e->getMessage(); }
    }

    if ($action === 'delete_news') {
        $id = (int)($_POST['news_id'] ?? 0);
        $st = $db->prepare('SELECT image FROM site_news WHERE id=?');
        $st->execute([$id]);
        if ($r = $st->fetch()) { if ($r['image']) @unlink(SITE_UPLOAD . 'news/' . $r['image']); }
        $db->prepare('DELETE FROM site_news WHERE id=?')->execute([$id]);
        $msg = 'Article deleted.';
    }

    if ($action === 'toggle_published') {
        $id  = (int)($_POST['news_id'] ?? 0);
        $cur = $db->prepare('SELECT is_published FROM site_news WHERE id=?');
        $cur->execute([$id]);
        $c = $cur->fetchColumn();
        $db->prepare('UPDATE site_news SET is_published=? WHERE id=?')->execute([$c ? 0 : 1, $id]);
        $msg = 'Status updated.';
    }
}

try { $newsList = $db->query('SELECT * FROM site_news ORDER BY created_at DESC')->fetchAll(); }
catch (Exception $e) { $newsList = []; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>News — BMC Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-main">
    <header class="admin-topbar">
      <div><span style="font-weight:700;color:var(--primary)">News Articles</span></div>
      <div style="font-size:.85rem;color:var(--text-2)">Welcome, <?= sh($admin['name']) ?></div>
    </header>
    <div class="admin-content">
      <?php if ($msg): ?><div class="alert alert-success auto-dismiss"><?= sh($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-danger"><?= sh($err) ?></div><?php endif; ?>

      <div class="admin-card mb-4">
        <div class="admin-card-header" data-bs-toggle="collapse" data-bs-target="#addNewsForm" style="cursor:pointer">
          <span><i class="fas fa-plus me-2"></i>Add News Article</span>
          <i class="fas fa-chevron-down"></i>
        </div>
        <div id="addNewsForm" class="collapse">
          <div class="admin-card-body">
            <form method="POST" enctype="multipart/form-data" class="row g-3">
              <input type="hidden" name="action" value="add_news">
              <div class="col-md-8">
                <label class="form-label">Title *</label>
                <input type="text" name="title" class="form-control" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Category</label>
                <select name="category" class="form-select">
                  <?php foreach (['General','Academic','Sports','Events','Admissions','Results','Achievements'] as $c): ?>
                  <option><?= $c ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Excerpt (short summary)</label>
                <textarea name="excerpt" class="form-control" rows="2" placeholder="Brief summary shown on listing page…"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Full Article Body *</label>
                <textarea name="body" class="form-control" rows="8" required></textarea>
              </div>
              <div class="col-md-5">
                <label class="form-label">Featured Image</label>
                <input type="file" name="image" class="form-control" accept="image/*">
              </div>
              <div class="col-md-4">
                <label class="form-label">Publish Date</label>
                <input type="datetime-local" name="published_at" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
              </div>
              <div class="col-md-3 d-flex align-items-end gap-3 pb-1">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="is_published" id="nPub" checked>
                  <label class="form-check-label" for="nPub">Published</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="is_featured" id="nFeat">
                  <label class="form-check-label" for="nFeat">Featured</label>
                </div>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary">Add Article</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="admin-card">
        <div class="admin-card-header"><i class="fas fa-newspaper me-2"></i>All Articles (<?= count($newsList) ?>)</div>
        <div class="admin-card-body p-0">
          <div class="table-responsive">
            <table class="admin-table">
              <thead><tr><th>Title</th><th>Category</th><th>Published</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <?php if (empty($newsList)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No articles yet.</td></tr>
                <?php else: foreach ($newsList as $n): ?>
                <tr>
                  <td>
                    <strong><?= sh($n['title']) ?></strong>
                    <?php if ($n['is_featured']): ?><span class="badge bg-warning text-dark ms-1">Featured</span><?php endif; ?>
                    <?php if ($n['image']): ?><br><small class="text-muted"><i class="fas fa-image me-1"></i>Has image</small><?php endif; ?>
                  </td>
                  <td><span class="badge bg-secondary"><?= sh($n['category']) ?></span></td>
                  <td style="font-size:.8rem"><?= $n['published_at'] ? date('d M Y', strtotime($n['published_at'])) : '—' ?></td>
                  <td>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="action" value="toggle_published">
                      <input type="hidden" name="news_id" value="<?= $n['id'] ?>">
                      <button class="badge border-0 bg-<?= $n['is_published']?'success':'secondary' ?>"><?= $n['is_published']?'Published':'Draft' ?></button>
                    </form>
                  </td>
                  <td>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete article?')">
                      <input type="hidden" name="action" value="delete_news">
                      <input type="hidden" name="news_id" value="<?= $n['id'] ?>">
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

<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = requireSiteAdmin();
$db    = siteDB();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_slider') {
        $title     = trim($_POST['title'] ?? '');
        $subtitle  = trim($_POST['subtitle'] ?? '');
        $btnText   = trim($_POST['button_text'] ?? '');
        $btnLink   = trim($_POST['button_url'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $active    = isset($_POST['is_active']) ? 1 : 0;
        $image     = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            $mime = mime_content_type($_FILES['image']['tmp_name']);
            if (in_array($mime, ['image/jpeg','image/png','image/webp'])) {
                $ext   = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime];
                $image = 'slide_' . bin2hex(random_bytes(6)) . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], SITE_UPLOAD . 'sliders/' . $image);
            } else { $err = 'Only JPEG, PNG, or WebP images allowed.'; }
        }
        if (!$err) {
            try {
                $db->prepare('INSERT INTO site_sliders (title,subtitle,button_text,button_url,image,sort_order,is_active) VALUES (?,?,?,?,?,?,?)')
                   ->execute([$title,$subtitle,$btnText,$btnLink,$image,$sortOrder,$active]);
                $msg = 'Slide added.';
            } catch (Exception $e) { $err = $e->getMessage(); }
        }
    }

    if ($action === 'delete_slider') {
        $id = (int)($_POST['slider_id'] ?? 0);
        $st = $db->prepare('SELECT image FROM site_sliders WHERE id=?');
        $st->execute([$id]);
        if ($r = $st->fetch()) { if ($r['image']) @unlink(SITE_UPLOAD . 'sliders/' . $r['image']); }
        $db->prepare('DELETE FROM site_sliders WHERE id=?')->execute([$id]);
        $msg = 'Slide deleted.';
    }

    if ($action === 'toggle_active') {
        $id  = (int)($_POST['slider_id'] ?? 0);
        $cur = $db->prepare('SELECT is_active FROM site_sliders WHERE id=?');
        $cur->execute([$id]);
        $c = $cur->fetchColumn();
        $db->prepare('UPDATE site_sliders SET is_active=? WHERE id=?')->execute([$c ? 0 : 1, $id]);
        $msg = 'Status updated.';
    }
}

try { $sliders = $db->query('SELECT * FROM site_sliders ORDER BY sort_order, id')->fetchAll(); }
catch (Exception $e) { $sliders = []; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sliders — BMC Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-main">
    <header class="admin-topbar">
      <div><span style="font-weight:700;color:var(--primary)">Homepage Sliders</span></div>
      <div style="font-size:.85rem;color:var(--text-2)">Welcome, <?= sh($admin['name']) ?></div>
    </header>
    <div class="admin-content">
      <?php if ($msg): ?><div class="alert alert-success auto-dismiss"><?= sh($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-danger"><?= sh($err) ?></div><?php endif; ?>

      <div class="alert alert-info" style="font-size:.85rem"><i class="fas fa-info-circle me-2"></i>Recommended: <strong>1920 × 1080px</strong> landscape images. Keep to 3–5 slides.</div>

      <div class="admin-card mb-4">
        <div class="admin-card-header" data-bs-toggle="collapse" data-bs-target="#addSlideForm" style="cursor:pointer">
          <span><i class="fas fa-plus me-2"></i>Add Slide</span>
          <i class="fas fa-chevron-down"></i>
        </div>
        <div id="addSlideForm" class="collapse">
          <div class="admin-card-body">
            <form method="POST" enctype="multipart/form-data" class="row g-3">
              <input type="hidden" name="action" value="add_slider">
              <div class="col-md-8">
                <label class="form-label">Slide Title</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Excellence in Education">
              </div>
              <div class="col-md-4">
                <label class="form-label">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="<?= count($sliders) ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Subtitle</label>
                <textarea name="subtitle" class="form-control" rows="2"></textarea>
              </div>
              <div class="col-md-5">
                <label class="form-label">Button Text</label>
                <input type="text" name="button_text" class="form-control" placeholder="Apply Now">
              </div>
              <div class="col-md-7">
                <label class="form-label">Button Link</label>
                <input type="text" name="button_url" class="form-control" placeholder="/site/admissions.php">
              </div>
              <div class="col-md-8">
                <label class="form-label">Background Image * (JPEG/PNG/WebP)</label>
                <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp" required>
              </div>
              <div class="col-md-4 d-flex align-items-end pb-1 gap-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="is_active" id="slideActive" checked>
                  <label class="form-check-label" for="slideActive">Active</label>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>Add</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="admin-card">
        <div class="admin-card-header"><i class="fas fa-images me-2"></i>Slides (<?= count($sliders) ?>)</div>
        <div class="admin-card-body">
          <?php if (empty($sliders)): ?>
          <p class="text-center text-muted py-3">No slides yet. The homepage will show built-in default slides until you add one here.</p>
          <?php else: ?>
          <div class="row g-3">
            <?php foreach ($sliders as $s): ?>
            <div class="col-md-6 col-lg-4">
              <div class="card h-100" style="border:1px solid var(--border);border-radius:var(--radius);overflow:hidden">
                <?php if ($s['image']): ?>
                <div style="height:150px;overflow:hidden"><img src="<?= uploadUrl('sliders', $s['image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover"></div>
                <?php else: ?>
                <div style="height:150px;background:var(--light-2);display:flex;align-items:center;justify-content:center"><i class="fas fa-image fa-2x text-muted"></i></div>
                <?php endif; ?>
                <div style="padding:12px">
                  <div style="font-weight:700;font-size:.9rem;color:var(--primary)"><?= sh($s['title'] ?: '(No title)') ?></div>
                  <?php if ($s['subtitle']): ?><div style="font-size:.78rem;color:var(--text-2);margin-top:2px"><?= sh(truncateText($s['subtitle'],60)) ?></div><?php endif; ?>
                  <div class="d-flex gap-2 mt-2 align-items-center flex-wrap">
                    <span class="badge bg-secondary" style="font-size:.68rem">#<?= $s['sort_order'] ?></span>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="action" value="toggle_active">
                      <input type="hidden" name="slider_id" value="<?= $s['id'] ?>">
                      <button class="badge border-0 bg-<?= $s['is_active']?'success':'secondary' ?>"><?= $s['is_active']?'Active':'Hidden' ?></button>
                    </form>
                    <form method="POST" class="d-inline ms-auto" onsubmit="return confirm('Delete slide?')">
                      <input type="hidden" name="action" value="delete_slider">
                      <input type="hidden" name="slider_id" value="<?= $s['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger py-0"><i class="fas fa-trash"></i></button>
                    </form>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>setTimeout(()=>{document.querySelectorAll('.auto-dismiss').forEach(e=>{e.style.opacity='0';setTimeout(()=>e.remove(),400)})},4000)</script>
</body></html>

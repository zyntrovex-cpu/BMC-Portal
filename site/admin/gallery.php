<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = requireSiteAdmin();
$db    = siteDB();
$msg   = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_album') {
        $name = trim($_POST['name'] ?? '');
        try { $db->prepare('INSERT INTO site_albums (name,description) VALUES (?,?)')->execute([$name, trim($_POST['description'] ?? '')]); $msg = 'Album created.'; }
        catch (Exception $e) { $err = $e->getMessage(); }
    }
    if ($action === 'delete_album') {
        $id = (int)($_POST['album_id'] ?? 0);
        $photos = $db->prepare('SELECT filename FROM site_gallery WHERE album_id=?'); $photos->execute([$id]);
        foreach ($photos->fetchAll() as $p) { @unlink(SITE_UPLOAD . 'gallery/' . $p['filename']); }
        $db->prepare('DELETE FROM site_gallery WHERE album_id=?')->execute([$id]);
        $db->prepare('DELETE FROM site_albums WHERE id=?')->execute([$id]);
        $msg = 'Album deleted.';
    }
    if ($action === 'upload_photos') {
        $albumId = (int)($_POST['album_id'] ?? 0);
        $uploaded = 0;
        if (!empty($_FILES['photos']['tmp_name'])) {
            $names = (array)$_FILES['photos']['name'];
            $tmps  = (array)$_FILES['photos']['tmp_name'];
            $errs  = (array)$_FILES['photos']['error'];
            foreach ($tmps as $i => $tmp) {
                if ($errs[$i] !== UPLOAD_ERR_OK) continue;
                $mime = mime_content_type($tmp);
                if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'])) continue;
                $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime];
                $fn  = 'gal_' . bin2hex(random_bytes(6)) . '.' . $ext;
                if (move_uploaded_file($tmp, SITE_UPLOAD . 'gallery/' . $fn)) {
                    $db->prepare('INSERT INTO site_gallery (album_id,filename,title) VALUES (?,?,?)')->execute([$albumId ?: null, $fn, pathinfo($names[$i], PATHINFO_FILENAME)]);
                    $uploaded++;
                }
            }
        }
        $msg = "$uploaded photo(s) uploaded.";
    }
    if ($action === 'delete_photo') {
        $id = (int)($_POST['photo_id'] ?? 0);
        $r = $db->prepare('SELECT filename FROM site_gallery WHERE id=?'); $r->execute([$id]);
        if ($row = $r->fetch()) { @unlink(SITE_UPLOAD . 'gallery/' . $row['filename']); }
        $db->prepare('DELETE FROM site_gallery WHERE id=?')->execute([$id]);
        $msg = 'Photo deleted.';
    }
    // Add video
    if ($action === 'add_video') {
        $title = trim($_POST['vtitle'] ?? '');
        $url   = trim($_POST['vurl'] ?? '');
        $cat   = trim($_POST['vcategory'] ?? 'General');
        try { $db->prepare('INSERT INTO site_videos (title,url,category) VALUES (?,?,?)')->execute([$title,$url,$cat]); $msg = 'Video added.'; }
        catch (Exception $e) { $err = $e->getMessage(); }
    }
    if ($action === 'delete_video') {
        $db->prepare('DELETE FROM site_videos WHERE id=?')->execute([(int)($_POST['video_id']??0)]);
        $msg = 'Video deleted.';
    }
}

$albums = $db->query('SELECT a.*, COUNT(g.id) AS photo_count FROM site_albums a LEFT JOIN site_gallery g ON g.album_id=a.id GROUP BY a.id ORDER BY a.id DESC')->fetchAll();
$photos = $db->query('SELECT g.*, a.name AS album_name FROM site_gallery g LEFT JOIN site_albums a ON a.id=g.album_id ORDER BY g.created_at DESC LIMIT 50')->fetchAll();
$videos = $db->query('SELECT * FROM site_videos ORDER BY created_at DESC')->fetchAll();
?>
<!doctype html><html lang="en"><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Gallery — BMC Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head><body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-main">
    <header class="admin-topbar">
      <span style="font-weight:700;color:var(--primary)">Gallery Management</span>
      <div style="font-size:.85rem;color:var(--text-2)"><?= sh($admin['name']) ?></div>
    </header>
    <div class="admin-content">
      <?php if ($msg): ?><div class="alert alert-success auto-dismiss"><?= sh($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-danger"><?= sh($err) ?></div><?php endif; ?>

      <ul class="nav nav-tabs mb-4" id="galleryTabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-albums">Albums &amp; Photos</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-videos">Videos</a></li>
      </ul>
      <div class="tab-content">
        <!-- Albums -->
        <div class="tab-pane fade show active" id="tab-albums">
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <div class="admin-card">
                <div class="admin-card-header"><i class="fas fa-folder-plus me-2"></i>Create Album</div>
                <div class="admin-card-body">
                  <form method="POST" class="row g-2">
                    <input type="hidden" name="action" value="add_album">
                    <div class="col-8"><input type="text" name="name" class="form-control" placeholder="Album name" required></div>
                    <div class="col-4"><button class="btn btn-primary w-100">Create</button></div>
                  </form>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="admin-card">
                <div class="admin-card-header"><i class="fas fa-upload me-2"></i>Upload Photos</div>
                <div class="admin-card-body">
                  <form method="POST" enctype="multipart/form-data" class="row g-2">
                    <input type="hidden" name="action" value="upload_photos">
                    <div class="col-5">
                      <select name="album_id" class="form-select">
                        <option value="0">No Album</option>
                        <?php foreach ($albums as $a): ?><option value="<?= $a['id'] ?>"><?= sh($a['name']) ?></option><?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-4"><input type="file" name="photos[]" class="form-control" accept="image/*" multiple required></div>
                    <div class="col-3"><button class="btn btn-success w-100">Upload</button></div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- Albums list -->
          <?php if (!empty($albums)): ?>
          <div class="admin-card mb-3">
            <div class="admin-card-header"><i class="fas fa-folder me-2"></i>Albums</div>
            <div class="admin-card-body p-0">
              <table class="admin-table">
                <thead><tr><th>Album</th><th>Photos</th><th>Actions</th></tr></thead>
                <tbody>
                  <?php foreach ($albums as $a): ?>
                  <tr>
                    <td><?= sh($a['name']) ?></td>
                    <td><?= $a['photo_count'] ?> photos</td>
                    <td>
                      <form method="POST" class="d-inline" onsubmit="return confirm('Delete album and all photos?')">
                        <input type="hidden" name="action" value="delete_album">
                        <input type="hidden" name="album_id" value="<?= $a['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i> Delete Album</button>
                      </form>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php endif; ?>
          <!-- Photos grid -->
          <div class="admin-card">
            <div class="admin-card-header"><i class="fas fa-images me-2"></i>Recent Photos (<?= count($photos) ?>)</div>
            <div class="admin-card-body">
              <div class="row g-2">
                <?php foreach ($photos as $p): ?>
                <div class="col-4 col-md-2 position-relative">
                  <img src="<?= SITE_URL ?>/assets/uploads/gallery/<?= sh($p['filename']) ?>" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px">
                  <form method="POST" class="position-absolute top-0 end-0 m-1">
                    <input type="hidden" name="action" value="delete_photo">
                    <input type="hidden" name="photo_id" value="<?= $p['id'] ?>">
                    <button class="btn btn-danger btn-sm py-0 px-1" style="font-size:.7rem" onclick="return confirm('Delete?')"><i class="fas fa-times"></i></button>
                  </form>
                </div>
                <?php endforeach; ?>
                <?php if (empty($photos)): ?><p class="text-muted">No photos uploaded yet.</p><?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <!-- Videos -->
        <div class="tab-pane fade" id="tab-videos">
          <div class="admin-card mb-4">
            <div class="admin-card-header"><i class="fas fa-video me-2"></i>Add Video</div>
            <div class="admin-card-body">
              <form method="POST" class="row g-3">
                <input type="hidden" name="action" value="add_video">
                <div class="col-md-5"><label class="form-label">Title</label><input type="text" name="vtitle" class="form-control" required></div>
                <div class="col-md-5"><label class="form-label">YouTube URL</label><input type="url" name="vurl" class="form-control" placeholder="https://youtube.com/watch?v=..." required></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Add</button></div>
              </form>
            </div>
          </div>
          <div class="admin-card">
            <div class="admin-card-header"><i class="fas fa-film me-2"></i>Videos (<?= count($videos) ?>)</div>
            <div class="admin-card-body p-0">
              <table class="admin-table">
                <thead><tr><th>Title</th><th>URL</th><th>Actions</th></tr></thead>
                <tbody>
                  <?php foreach ($videos as $v): ?>
                  <tr>
                    <td><?= sh($v['title']) ?></td>
                    <td><a href="<?= sh($v['url']) ?>" target="_blank" style="font-size:.8rem"><?= sh(substr($v['url'],0,50)) ?>…</a></td>
                    <td>
                      <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')">
                        <input type="hidden" name="action" value="delete_video">
                        <input type="hidden" name="video_id" value="<?= $v['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                      </form>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(empty($videos)): ?><tr><td colspan="3" class="text-center text-muted py-3">No videos yet.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>setTimeout(()=>{document.querySelectorAll('.auto-dismiss').forEach(e=>{e.style.opacity='0';setTimeout(()=>e.remove(),400)})},4000)</script>
</body></html>

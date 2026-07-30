<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
$admin = requireSiteAdmin();
$db    = siteDB();

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_event') {
        $title   = trim($_POST['title'] ?? '');
        $slug    = preg_replace('/[^a-z0-9-]/', '', strtolower(preg_replace('/[\s_]+/', '-', $title))) . '-' . time();
        $desc    = trim($_POST['description'] ?? '');
        $eDate   = $_POST['event_date'] ?? '';
        $eTime   = $_POST['event_time'] ?: null;
        $endDate = $_POST['end_date'] ?: null;
        $venue   = trim($_POST['venue'] ?? '');
        $pub     = isset($_POST['is_published']) ? 1 : 0;
        $feat    = isset($_POST['is_featured']) ? 1 : 0;
        $image   = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            $mime = mime_content_type($_FILES['image']['tmp_name']);
            if (in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'])) {
                $ext   = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime];
                $image = 'ev_' . bin2hex(random_bytes(6)) . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], SITE_UPLOAD . 'events/' . $image);
            }
        }
        try {
            $db->prepare('INSERT INTO site_events (title,slug,description,image,event_date,event_time,end_date,venue,is_published,is_featured) VALUES (?,?,?,?,?,?,?,?,?,?)')
               ->execute([$title,$slug,$desc,$image,$eDate,$eTime,$endDate,$venue,$pub,$feat]);
            $msg = 'Event added successfully.';
        } catch (Exception $e) { $err = $e->getMessage(); }
    }

    if ($action === 'delete_event') {
        $id = (int)($_POST['event_id'] ?? 0);
        $row = $db->prepare('SELECT image FROM site_events WHERE id=?');
        $row->execute([$id]);
        if ($r = $row->fetch()) { if ($r['image']) @unlink(SITE_UPLOAD . 'events/' . $r['image']); }
        $db->prepare('DELETE FROM site_events WHERE id=?')->execute([$id]);
        $msg = 'Event deleted.';
    }

    if ($action === 'toggle_published') {
        $id  = (int)($_POST['event_id'] ?? 0);
        $cur = $db->prepare('SELECT is_published FROM site_events WHERE id=?');
        $cur->execute([$id]);
        $c = $cur->fetchColumn();
        $db->prepare('UPDATE site_events SET is_published=? WHERE id=?')->execute([$c ? 0 : 1, $id]);
        $msg = 'Status updated.';
    }
}

$events = $db->query('SELECT * FROM site_events ORDER BY event_date DESC')->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Events — BMC Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-main">
    <header class="admin-topbar">
      <div><span style="font-weight:700;color:var(--primary)">Events</span></div>
      <div style="font-size:.85rem;color:var(--text-2)">Welcome, <?= sh($admin['name']) ?></div>
    </header>
    <div class="admin-content">
      <?php if ($msg): ?><div class="alert alert-success auto-dismiss"><?= sh($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-danger"><?= sh($err) ?></div><?php endif; ?>

      <!-- Add Event -->
      <div class="admin-card mb-4">
        <div class="admin-card-header" data-bs-toggle="collapse" data-bs-target="#addEventForm" style="cursor:pointer">
          <span><i class="fas fa-plus me-2"></i>Add New Event</span>
          <i class="fas fa-chevron-down"></i>
        </div>
        <div id="addEventForm" class="collapse">
          <div class="admin-card-body">
            <form method="POST" enctype="multipart/form-data" class="row g-3">
              <input type="hidden" name="action" value="add_event">
              <div class="col-md-8">
                <label class="form-label">Event Title *</label>
                <input type="text" name="title" class="form-control" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Event Date *</label>
                <input type="date" name="event_date" class="form-control" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Start Time</label>
                <input type="time" name="event_time" class="form-control">
              </div>
              <div class="col-md-3">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">Venue</label>
                <input type="text" name="venue" class="form-control" placeholder="Main Auditorium">
              </div>
              <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4"></textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label">Event Image</label>
                <input type="file" name="image" class="form-control" accept="image/*">
              </div>
              <div class="col-md-3 d-flex align-items-end">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="is_published" id="evPub" checked>
                  <label class="form-check-label" for="evPub">Published</label>
                </div>
              </div>
              <div class="col-md-3 d-flex align-items-end">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="is_featured" id="evFeat">
                  <label class="form-check-label" for="evFeat">Featured</label>
                </div>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary">Add Event</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Events List -->
      <div class="admin-card">
        <div class="admin-card-header"><i class="fas fa-calendar-alt me-2"></i>All Events (<?= count($events) ?>)</div>
        <div class="admin-card-body p-0">
          <div class="table-responsive">
            <table class="admin-table">
              <thead><tr><th>Title</th><th>Date</th><th>Venue</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <?php if (empty($events)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No events yet.</td></tr>
                <?php else: foreach ($events as $e): ?>
                <tr>
                  <td><strong><?= sh($e['title']) ?></strong><?php if ($e['is_featured']): ?> <span class="badge bg-warning text-dark ms-1">Featured</span><?php endif; ?></td>
                  <td><?= date('d M Y', strtotime($e['event_date'])) ?><?php if ($e['event_time']): ?><br><small class="text-muted"><?= date('g:i a', strtotime($e['event_time'])) ?></small><?php endif; ?></td>
                  <td><?= sh($e['venue'] ?: '—') ?></td>
                  <td>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="action" value="toggle_published">
                      <input type="hidden" name="event_id" value="<?= $e['id'] ?>">
                      <button class="badge border-0 bg-<?= $e['is_published']?'success':'secondary' ?>"><?= $e['is_published']?'Published':'Draft' ?></button>
                    </form>
                  </td>
                  <td>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete event?')">
                      <input type="hidden" name="action" value="delete_event">
                      <input type="hidden" name="event_id" value="<?= $e['id'] ?>">
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

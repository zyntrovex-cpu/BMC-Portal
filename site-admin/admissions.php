<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = requireSiteAdmin();
$db    = siteDB();
$msg   = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_form') {
        $title = trim($_POST['title'] ?? '');
        $year  = trim($_POST['year'] ?? '');
        if (!empty($_FILES['file']['tmp_name'])) {
            $ext    = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $fn     = 'adm_' . bin2hex(random_bytes(6)) . '.' . strtolower($ext);
            $admDir = SITE_UPLOAD . 'downloads/';
            if (!is_dir($admDir)) { @mkdir($admDir, 0755, true); }
            move_uploaded_file($_FILES['file']['tmp_name'], $admDir . $fn);
            try {
                $db->prepare('INSERT INTO site_admission_forms (title,year,filename) VALUES (?,?,?)')->execute([$title,$year,$fn]);
                $msg = 'Admission form uploaded.';
            } catch (Exception $e) { $err = $e->getMessage(); }
        } else { $err = 'Please select a file.'; }
    }
    if ($action === 'delete_form') {
        $id = (int)($_POST['form_id'] ?? 0);
        $r = $db->prepare('SELECT filename FROM site_admission_forms WHERE id=?'); $r->execute([$id]);
        if ($row = $r->fetch()) { @unlink(SITE_UPLOAD . 'downloads/' . $row['filename']); }
        $db->prepare('DELETE FROM site_admission_forms WHERE id=?')->execute([$id]);
        $msg = 'Form deleted.';
    }
    if ($action === 'toggle_admission') {
        $val = ($_POST['admission_open'] ?? '0') === '1' ? '1' : '0';
        $db->prepare('INSERT INTO site_settings (`key`,`value`) VALUES ("admission_open",?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)')->execute([$val]);
        $msg = 'Admission status updated.';
    }
    if ($action === 'update_year') {
        $year = trim($_POST['admission_year'] ?? '');
        $db->prepare('INSERT INTO site_settings (`key`,`value`) VALUES ("admission_year",?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)')->execute([$year]);
        $msg = 'Admission year updated.';
    }
}

$forms = $db->query('SELECT * FROM site_admission_forms ORDER BY created_at DESC')->fetchAll();
$admOpen = getSetting('admission_open','0');
$admYear = getSetting('admission_year','2025-26');
?>
<!doctype html><html lang="en"><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admissions — BMC Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head><body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-main">
    <header class="admin-topbar">
      <span style="font-weight:700;color:var(--primary)">Admissions Management</span>
      <div style="font-size:.85rem;color:var(--text-2)">Welcome, <?= sh($admin['name']) ?></div>
    </header>
    <div class="admin-content">
      <?php if ($msg): ?><div class="alert alert-success auto-dismiss"><?= sh($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-danger"><?= sh($err) ?></div><?php endif; ?>

      <!-- Status Controls -->
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <div class="admin-card">
            <div class="admin-card-header"><i class="fas fa-toggle-on me-2"></i>Admission Status</div>
            <div class="admin-card-body">
              <p class="text-muted mb-3">Current Status: <strong class="text-<?= $admOpen==='1'?'success':'danger' ?>"><?= $admOpen==='1'?'OPEN':'CLOSED' ?></strong></p>
              <form method="POST" class="d-flex gap-2">
                <input type="hidden" name="action" value="toggle_admission">
                <select name="admission_open" class="form-select form-select-sm w-auto">
                  <option value="1" <?= $admOpen==='1'?'selected':'' ?>>Open</option>
                  <option value="0" <?= $admOpen!=='1'?'selected':'' ?>>Closed</option>
                </select>
                <button class="btn btn-sm btn-primary">Update</button>
              </form>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="admin-card">
            <div class="admin-card-header"><i class="fas fa-calendar me-2"></i>Admission Year</div>
            <div class="admin-card-body">
              <form method="POST" class="d-flex gap-2">
                <input type="hidden" name="action" value="update_year">
                <input type="text" name="admission_year" value="<?= sh($admYear) ?>" class="form-control form-control-sm" placeholder="2025-26">
                <button class="btn btn-sm btn-primary">Update</button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <!-- Upload Form -->
      <div class="admin-card mb-4">
        <div class="admin-card-header"><i class="fas fa-upload me-2"></i>Upload Admission Form</div>
        <div class="admin-card-body">
          <form method="POST" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="action" value="add_form">
            <div class="col-md-5"><label class="form-label">Form Title *</label><input type="text" name="title" class="form-control" required placeholder="Admission Form 2025-26"></div>
            <div class="col-md-3"><label class="form-label">Year</label><input type="text" name="year" class="form-control" placeholder="2025-26"></div>
            <div class="col-md-4"><label class="form-label">File (PDF/DOC) *</label><input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx" required></div>
            <div class="col-12"><button type="submit" class="btn btn-primary"><i class="fas fa-upload me-2"></i>Upload</button></div>
          </form>
        </div>
      </div>

      <!-- Forms List -->
      <div class="admin-card">
        <div class="admin-card-header"><i class="fas fa-graduation-cap me-2"></i>Admission Forms (<?= count($forms) ?>)</div>
        <div class="admin-card-body p-0">
          <table class="admin-table">
            <thead><tr><th>Title</th><th>Year</th><th>Uploaded</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (empty($forms)): ?><tr><td colspan="4" class="text-center text-muted py-4">No forms uploaded yet.</td></tr>
              <?php else: foreach ($forms as $f): ?>
              <tr>
                <td><?= sh($f['title']) ?></td>
                <td><?= sh($f['year']) ?></td>
                <td><?= date('d M Y', strtotime($f['created_at'])) ?></td>
                <td class="d-flex gap-2">
                  <a href="<?= SITE_URL ?>/assets/uploads/downloads/<?= sh($f['filename']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')">
                    <input type="hidden" name="action" value="delete_form">
                    <input type="hidden" name="form_id" value="<?= $f['id'] ?>">
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
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>setTimeout(()=>{document.querySelectorAll('.auto-dismiss').forEach(e=>{e.style.opacity='0';setTimeout(()=>e.remove(),400)})},4000)</script>
</body></html>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

// Allow admin, vp_main, student_affairs
$user = requireAuth('admin', 'vp_main', 'student_affairs');
if ($user['role'] === 'vp_main')         requirePermission('vp_calendar');
if ($user['role'] === 'student_affairs') requirePermission('sa_calendar');

$db = getDB();

// Upload directory
$uploadDir = __DIR__ . '/../assets/uploads/calendar/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// Table check
$tableExists = false;
try { $db->query('SELECT 1 FROM academic_calendars LIMIT 0'); $tableExists = true; } catch (Exception $e) {}

// ── POST Handler ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $title       = trim($_POST['title']        ?? '');
        $description = trim($_POST['description']  ?? '');
        $year        = (int)($_POST['year']         ?? date('Y'));

        if (!$title || empty($_FILES['file']['tmp_name'])) {
            setFlash('danger', 'Title and file are required.');
        } else {
            $allowed  = ['application/pdf','image/jpeg','image/png','image/gif','image/webp'];
            $mimeType = mime_content_type($_FILES['file']['tmp_name']);
            if (!in_array($mimeType, $allowed)) {
                setFlash('danger', 'Only PDF and image files are allowed.');
            } elseif ($_FILES['file']['size'] > 20 * 1024 * 1024) {
                setFlash('danger', 'File too large (max 20 MB).');
            } else {
                $ext      = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
                $filename = 'calendar_' . $year . '_' . bin2hex(random_bytes(6)) . '.' . strtolower($ext);
                if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $filename)) {
                    $db->prepare(
                        'INSERT INTO academic_calendars (title, description, filename, year, uploaded_by)
                         VALUES (?,?,?,?,?)'
                    )->execute([$title, $description ?: null, $filename, $year, $user['id']]);
                    logActivity($user['id'], 'calendar_upload', "Uploaded academic calendar: $title");
                    setFlash('success', 'Calendar uploaded successfully.');
                } else {
                    setFlash('danger', 'File upload failed. Check server permissions.');
                }
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['cal_id'] ?? 0);
        $st = $db->prepare('SELECT filename FROM academic_calendars WHERE id=?');
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) {
            @unlink($uploadDir . $row['filename']);
            $db->prepare('DELETE FROM academic_calendars WHERE id=?')->execute([$id]);
            setFlash('success', 'Calendar deleted.');
        }
    }

    redirect('/portal/admin/academic-calendar.php');
}

// Fetch calendars
$calendars = [];
if ($tableExists) {
    $calendars = $db->query(
        'SELECT ac.*, u.name AS uploader_name
         FROM academic_calendars ac
         LEFT JOIN users u ON u.id = ac.uploaded_by
         ORDER BY ac.year DESC, ac.created_at DESC'
    )->fetchAll();
}

// Determine portal/links for sidebar
$portal = $user['role'];
$links  = match($portal) {
    'vp_main'          => getVpLinks(),
    'student_affairs'  => getStudentAffairsLinks(),
    default            => getAdminLinks(),
};
$sidebarKey = match($portal) {
    'vp_main' => 'vp_main', 'student_affairs' => 'student_affairs', default => 'admin',
};

pageHead('Academic Calendar', $sidebarKey);
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar($sidebarKey, 'calendar', $links, $user); ?>
<div class="main-area">
<?php topbar('Academic Calendar', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  The <code>academic_calendars</code> table is missing. Run the features migration SQL first.
</div>
<?php else: ?>

<div class="row g-3">
  <!-- Upload form -->
  <div class="col-lg-4">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-upload me-2"></i>Upload Calendar</div>
      <div style="padding:16px">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control form-control-sm" required
                   placeholder="e.g. Academic Calendar 2025-26">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Year</label>
            <input type="number" name="year" class="form-control form-control-sm"
                   value="<?= date('Y') ?>" min="2020" max="2040">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Description</label>
            <textarea name="description" class="form-control form-control-sm" rows="2"
                      placeholder="Optional notes…"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">File <span class="text-danger">*</span></label>
            <input type="file" name="file" class="form-control form-control-sm" required
                   accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
            <div class="form-text">PDF or image, max 20 MB</div>
          </div>
          <button type="submit" class="btn btn-sm btn-success w-100">
            <i class="fas fa-upload me-1"></i>Upload
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Calendars list -->
  <div class="col-lg-8">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-calendar-week me-2"></i>Uploaded Calendars (<?= count($calendars) ?>)</div>
      <?php if (empty($calendars)): ?>
      <div style="padding:40px;text-align:center;color:var(--t2);font-size:.85rem">No calendars uploaded yet.</div>
      <?php else: ?>
      <?php foreach ($calendars as $c):
        $ext = strtolower(pathinfo($c['filename'], PATHINFO_EXTENSION));
        $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
      ?>
      <div style="padding:14px 16px;border-bottom:1px solid var(--border)">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div style="flex:1;min-width:0">
            <div class="fw-semibold" style="font-size:.9rem">
              <i class="fas fa-<?= $ext==='pdf'?'file-pdf text-danger':'image text-primary' ?> me-2"></i>
              <?= h($c['title']) ?>
              <span class="badge bg-secondary ms-1"><?= h($c['year']) ?></span>
            </div>
            <?php if ($c['description']): ?>
            <div style="font-size:.81rem;color:var(--t2);margin-top:3px"><?= h($c['description']) ?></div>
            <?php endif; ?>
            <div style="font-size:.76rem;color:var(--t3);margin-top:4px">
              Uploaded by <?= h($c['uploader_name'] ?? '—') ?> · <?= fDate($c['created_at']) ?>
            </div>
          </div>
          <div class="d-flex gap-1 flex-shrink-0">
            <a href="<?= url('/assets/uploads/calendar/' . h($c['filename'])) ?>"
               target="_blank" class="btn btn-xs btn-outline-primary"
               style="font-size:.74rem;padding:2px 8px">
              <i class="fas fa-eye me-1"></i>View
            </a>
            <a href="<?= url('/assets/uploads/calendar/' . h($c['filename'])) ?>"
               download class="btn btn-xs btn-outline-secondary"
               style="font-size:.74rem;padding:2px 8px">
              <i class="fas fa-download"></i>
            </a>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this calendar?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="cal_id" value="<?= $c['id'] ?>">
              <button class="btn btn-xs btn-outline-danger" style="font-size:.74rem;padding:2px 7px">
                <i class="fas fa-trash"></i>
              </button>
            </form>
          </div>
        </div>
        <?php if ($isImage): ?>
        <div class="mt-2">
          <img src="<?= url('/assets/uploads/calendar/' . h($c['filename'])) ?>"
               style="max-height:200px;max-width:100%;border-radius:6px;border:1px solid var(--border)" alt="calendar">
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php endif; ?>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

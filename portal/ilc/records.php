<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp');
requirePermission('ilc_records');
$db   = getDB();

$uploadDir = __DIR__ . '/../assets/uploads/ilc-records/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// Table check
$tableExists = false;
try { $db->query('SELECT 1 FROM ilc_session_records LIMIT 0'); $tableExists = true; } catch (Exception $e) {}

// ── Lazy expiry cleanup ───────────────────────────────────────────
if ($tableExists) {
    try {
        $expired = $db->query('SELECT filename FROM ilc_session_records WHERE expires_at < NOW()')->fetchAll();
        foreach ($expired as $r) { @unlink($uploadDir . $r['filename']); }
        $db->exec('DELETE FROM ilc_session_records WHERE expires_at < NOW()');
    } catch (Exception $e) {}
}

// ── POST Handler ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $title       = trim($_POST['title']       ?? '');
        $description = trim($_POST['description'] ?? '');
        $days        = max(1, min(7, (int)($_POST['days'] ?? 3)));

        if (!$title || empty($_FILES['file']['tmp_name'])) {
            setFlash('danger', 'Title and file are required.');
        } else {
            $allowedMimes = [
                'application/pdf'  => 'pdf',
                'video/mp4'        => 'mp4',
                'video/webm'       => 'webm',
                'video/quicktime'  => 'mov',
                'video/x-msvideo'  => 'avi',
                'video/ogg'        => 'ogv',
            ];
            $mime = mime_content_type($_FILES['file']['tmp_name']);
            if (!isset($allowedMimes[$mime])) {
                setFlash('danger', 'Only PDF and video files are allowed (MP4, WEBM, MOV, AVI, OGG).');
            } elseif ($_FILES['file']['size'] > 500 * 1024 * 1024) {
                setFlash('danger', 'File too large (max 500 MB).');
            } else {
                $type     = str_starts_with($mime, 'video/') ? 'video' : 'pdf';
                $ext      = $allowedMimes[$mime];
                $filename = 'ilcrec_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $expiry   = date('Y-m-d H:i:s', strtotime("+$days days"));

                if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $filename)) {
                    $db->prepare(
                        'INSERT INTO ilc_session_records (title, description, filename, file_type, uploaded_by, expires_at)
                         VALUES (?,?,?,?,?,?)'
                    )->execute([$title, $description ?: null, $filename, $type, $user['id'], $expiry]);
                    logActivity($user['id'], 'ilc_record_upload', "Uploaded ILC record: $title (expires $expiry)");
                    setFlash('success', "Record uploaded. It will auto-delete after $days day(s).");
                } else {
                    setFlash('danger', 'File upload failed. Check server write permissions.');
                }
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['rec_id'] ?? 0);
        $st = $db->prepare('SELECT filename FROM ilc_session_records WHERE id=?');
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) {
            @unlink($uploadDir . $row['filename']);
            $db->prepare('DELETE FROM ilc_session_records WHERE id=?')->execute([$id]);
            setFlash('success', 'Record deleted.');
        }
    }

    redirect('/portal/ilc/records.php');
}

// Fetch active records
$records = [];
if ($tableExists) {
    $records = $db->query(
        'SELECT r.*, u.name AS uploader_name,
                TIMESTAMPDIFF(HOUR, NOW(), r.expires_at) AS hours_left
         FROM ilc_session_records r
         LEFT JOIN users u ON u.id = r.uploaded_by
         WHERE r.expires_at > NOW()
         ORDER BY r.created_at DESC'
    )->fetchAll();
}

pageHead('ILC Session Records', 'ilc_vp');
$links = getIlcLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'records', $links, $user); ?>
<div class="main-area">
<?php topbar('Session Records', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="alert alert-info d-flex gap-2 align-items-start mb-3" style="font-size:.86rem;border-radius:8px">
  <i class="fas fa-info-circle mt-1"></i>
  <div>
    <strong>Auto-Delete Policy:</strong> Files uploaded here are <strong>automatically deleted</strong> from the server after the specified period (max 7 days). This ensures sensitive ILC session records are not retained permanently. Download any records you need to keep before they expire.
  </div>
</div>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  The <code>ilc_session_records</code> table is missing. Run the ILC features migration SQL.
</div>
<?php else: ?>

<div class="row g-3">
  <!-- Upload form -->
  <div class="col-lg-4">
    <div class="sec-card" style="position:sticky;top:70px">
      <div class="sec-card-header" style="background:linear-gradient(90deg,#0c4a6e,#0891b2);color:#fff">
        <i class="fas fa-upload me-2"></i>Upload Session Record
      </div>
      <div style="padding:16px">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control form-control-sm" required
                   placeholder="e.g. Session 3 — Ahmed Khan Therapy">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Description <small class="text-muted">(optional)</small></label>
            <textarea name="description" class="form-control form-control-sm" rows="2"
                      placeholder="Notes about this session…"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Auto-Delete After</label>
            <select name="days" class="form-select form-select-sm">
              <option value="1">1 day</option>
              <option value="2">2 days</option>
              <option value="3" selected>3 days</option>
              <option value="5">5 days</option>
              <option value="7">7 days</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">File <span class="text-danger">*</span></label>
            <input type="file" name="file" class="form-control form-control-sm" required
                   accept=".pdf,.mp4,.webm,.mov,.avi,.ogv">
            <div class="form-text mt-1" style="color:#64748b">PDF or video (MP4/WEBM/MOV/AVI) · max 500 MB</div>
          </div>
          <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:.78rem;border-radius:6px">
            <i class="fas fa-exclamation-triangle me-1"></i>
            <strong>Sensitive data notice:</strong> Files containing student medical/disability information are auto-deleted and not stored permanently.
          </div>
          <button type="submit" class="btn btn-sm w-100" style="background:#0891b2;color:#fff">
            <i class="fas fa-upload me-1"></i>Upload Record
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Records list -->
  <div class="col-lg-8">
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center"
           style="background:linear-gradient(90deg,#0c4a6e,#0891b2);color:#fff">
        <span><i class="fas fa-folder-open me-2"></i>Active Records (<?= count($records) ?>)</span>
        <small style="opacity:.8;font-size:.77rem">All records auto-delete when expired</small>
      </div>

      <?php if (empty($records)): ?>
      <div style="padding:48px;text-align:center;color:var(--t2)">
        <i class="fas fa-folder-open fa-2x mb-2 d-block opacity-25"></i>
        <p class="mb-0">No active records. Upload a session PDF or video.</p>
      </div>
      <?php else: ?>
      <?php foreach ($records as $r):
        $hoursLeft = (int)$r['hours_left'];
        $daysLeft  = floor($hoursLeft / 24);
        $hrsRem    = $hoursLeft % 24;
        $timeLabel = $daysLeft > 0 ? "{$daysLeft}d {$hrsRem}h" : "{$hoursLeft}h";
        $urgency   = $hoursLeft < 24 ? 'danger' : ($hoursLeft < 72 ? 'warning' : 'secondary');
        $isVideo   = $r['file_type'] === 'video';
        $icon      = $isVideo ? 'fa-film text-primary' : 'fa-file-pdf text-danger';
        $fileUrl   = url('/assets/uploads/ilc-records/' . h($r['filename']));
      ?>
      <div style="padding:16px;border-bottom:1px solid var(--border)">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div style="flex:1;min-width:0">
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="fas <?= $icon ?>"></i>
              <span class="fw-semibold" style="font-size:.9rem"><?= h($r['title']) ?></span>
              <span class="badge bg-<?= $r['file_type']==='video'?'primary':'danger' ?>" style="font-size:.68rem">
                <?= strtoupper($r['file_type']) ?>
              </span>
            </div>
            <?php if ($r['description']): ?>
            <div style="font-size:.81rem;color:var(--t2);margin-bottom:4px"><?= h($r['description']) ?></div>
            <?php endif; ?>
            <div style="font-size:.76rem;color:var(--t3)">
              Uploaded by <?= h($r['uploader_name'] ?? '—') ?>
              &nbsp;·&nbsp; <?= fDate($r['created_at']) ?>
              &nbsp;·&nbsp; Expires: <?= fDate($r['expires_at']) ?>
            </div>
            <div class="d-flex align-items-center gap-2 mt-2">
              <span class="badge bg-<?= $urgency ?>" style="font-size:.75rem">
                <i class="fas fa-clock me-1"></i>Deletes in <?= $timeLabel ?>
              </span>
              <!-- Progress bar showing time remaining -->
              <?php
                $totalHours = 72; // nominal reference for progress bar
                $pct = min(100, max(0, round(($totalHours - $hoursLeft) / $totalHours * 100)));
              ?>
              <div style="flex:1;height:4px;background:#e5e7eb;border-radius:4px;overflow:hidden;max-width:120px">
                <div style="height:100%;width:<?= $pct ?>%;background:<?= $urgency==='danger'?'#dc2626':($urgency==='warning'?'#d97706':'#059669') ?>;border-radius:4px"></div>
              </div>
            </div>
          </div>
          <div class="d-flex flex-column gap-1 flex-shrink-0">
            <?php if (!$isVideo): ?>
            <a href="<?= $fileUrl ?>" target="_blank"
               class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 8px">
              <i class="fas fa-eye me-1"></i>View
            </a>
            <?php endif; ?>
            <a href="<?= $fileUrl ?>" download
               class="btn btn-xs btn-outline-secondary" style="font-size:.74rem;padding:2px 8px">
              <i class="fas fa-download me-1"></i>Download
            </a>
            <form method="POST" class="d-inline" onsubmit="return confirm('Permanently delete this record now?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="rec_id" value="<?= $r['id'] ?>">
              <button class="btn btn-xs btn-outline-danger w-100" style="font-size:.74rem;padding:2px 7px">
                <i class="fas fa-trash me-1"></i>Delete
              </button>
            </form>
          </div>
        </div>
        <?php if ($isVideo): ?>
        <div class="mt-2">
          <video src="<?= $fileUrl ?>" controls style="max-width:100%;max-height:200px;border-radius:6px;border:1px solid var(--border)"></video>
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

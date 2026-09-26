<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp');
requirePermission('ilc_records');
$db = getDB();

$uploadDir = __DIR__ . '/../assets/uploads/ilc-records/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// Column availability
$tableExists     = false;
$studentColExists = false;
try { $db->query('SELECT 1 FROM ilc_session_records LIMIT 0'); $tableExists = true; } catch (Exception $e) {}
if ($tableExists) {
    try { $db->query('SELECT student_id FROM ilc_session_records LIMIT 0'); $studentColExists = true; } catch (Exception $e) {}
}

// Lazy expiry cleanup (only for records with a non-null expires_at)
if ($tableExists) {
    try {
        $expired = $db->query('SELECT filename FROM ilc_session_records WHERE expires_at IS NOT NULL AND expires_at < NOW()')->fetchAll();
        foreach ($expired as $r) { @unlink($uploadDir . $r['filename']); }
        $db->exec('DELETE FROM ilc_session_records WHERE expires_at IS NOT NULL AND expires_at < NOW()');
    } catch (Exception $e) {}
}

// ── POST handler ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action    = $_POST['action']     ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);

    if ($action === 'upload') {
        $title       = trim($_POST['title']        ?? '');
        $description = trim($_POST['description']  ?? '');
        $sessionDate = $_POST['session_date']       ?? '';
        $sessionType = trim($_POST['session_type']  ?? '');
        $daysRaw     = $_POST['days']               ?? '0';
        $days        = (int)$daysRaw;

        if (!$title || empty($_FILES['file']['tmp_name'])) {
            setFlash('danger', 'Title and file are required.');
        } elseif (!$studentId) {
            setFlash('danger', 'Please select a student first.');
        } else {
            $allowedMimes = [
                'application/pdf' => 'pdf',
                'video/mp4'       => 'mp4',
                'video/webm'      => 'webm',
                'video/quicktime' => 'mov',
                'video/x-msvideo' => 'avi',
                'video/ogg'       => 'ogv',
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
                $expiry   = $days > 0 ? date('Y-m-d H:i:s', strtotime("+$days days")) : null;
                $sdateVal = $sessionDate ?: null;

                if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $filename)) {
                    if ($studentColExists) {
                        $db->prepare(
                            'INSERT INTO ilc_session_records
                             (student_id, title, description, session_date, session_type, filename, file_type, uploaded_by, expires_at)
                             VALUES (?,?,?,?,?,?,?,?,?)'
                        )->execute([
                            $studentId, $title, $description ?: null,
                            $sdateVal, $sessionType ?: null,
                            $filename, $type, $user['id'], $expiry
                        ]);
                    } else {
                        $db->prepare(
                            'INSERT INTO ilc_session_records (title, description, filename, file_type, uploaded_by, expires_at)
                             VALUES (?,?,?,?,?,?)'
                        )->execute([$title, $description ?: null, $filename, $type, $user['id'], $expiry]);
                    }
                    $msg = $expiry ? "Record saved. Auto-deletes after $days day(s)." : 'Record saved (no auto-delete).';
                    logActivity($user['id'], 'ilc_record_upload', "Uploaded session record: $title for student #$studentId");
                    setFlash('success', $msg);
                } else {
                    setFlash('danger', 'File upload failed. Check server write permissions.');
                }
            }
        }
        redirect('/portal/ilc/records.php?student_id=' . $studentId);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['rec_id'] ?? 0);
        $st = $db->prepare('SELECT filename FROM ilc_session_records WHERE id=?');
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) {
            @unlink($uploadDir . $row['filename']);
            $db->prepare('DELETE FROM ilc_session_records WHERE id=?')->execute([$id]);
            logActivity($user['id'], 'ilc_record_delete', "Deleted session record #$id");
            setFlash('success', 'Record deleted.');
        }
        redirect('/portal/ilc/records.php?student_id=' . $studentId);
    }
}

// ── Fetch ─────────────────────────────────────────────────────────
$studentId = (int)($_GET['student_id'] ?? 0);

$students = $db->query(
    'SELECT s.id, u.name, s.roll_no, c.name AS class_name
     FROM students s JOIN users u ON u.id = s.user_id JOIN classes c ON c.id = s.class_id
     WHERE c.is_ilc = 1 ORDER BY c.name, u.name'
)->fetchAll();

$curStudent = null;
foreach ($students as $s) { if ($s['id'] === $studentId) { $curStudent = $s; break; } }

$records = [];
if ($tableExists && $studentId) {
    if ($studentColExists) {
        $st = $db->prepare(
            'SELECT r.*, u.name AS uploader_name,
                    CASE WHEN r.expires_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, NOW(), r.expires_at) ELSE NULL END AS hours_left
             FROM ilc_session_records r
             LEFT JOIN users u ON u.id = r.uploaded_by
             WHERE r.student_id = ?
               AND (r.expires_at IS NULL OR r.expires_at > NOW())
             ORDER BY r.created_at DESC'
        );
        $st->execute([$studentId]);
        $records = $st->fetchAll();
    }
}

$sessionTypes = ['Therapy Session', 'Initial Assessment', 'Progress Review', 'IEP Meeting', 'Parent Conference', 'Behavioural Session', 'Speech Therapy Session', 'Other'];

pageHead('ILC Session Records', 'ilc_vp');
$links = getIlcLinks();
?>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'records', $links, $user); ?>
<div class="main-area">
<?php topbar('Session Records', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  The <code>ilc_session_records</code> table is missing. Run the ILC features migration SQL first.
</div>
<?php elseif (!$studentColExists): ?>
<div class="alert alert-info">
  <i class="fas fa-info-circle me-2"></i>
  Run <code>database/migrations/ilc_session_records_student.sql</code> to enable per-student session records.
</div>
<?php else: ?>

<!-- ILC strip -->
<div class="d-flex align-items-center gap-3 mb-3 p-3"
     style="background:linear-gradient(90deg,#f0f9ff,#ecfeff);border-radius:10px;border:1px solid #7dd3fc;">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC"
       style="width:40px;height:40px;object-fit:contain;flex-shrink:0;" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#0c4a6e;letter-spacing:.8px;text-transform:uppercase">
      Session Records
    </div>
    <div style="font-size:.78rem;color:#475569">Inclusive Learning Centre · Bahria Model College Bin Qasim</div>
  </div>
</div>

<!-- Student selector -->
<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-user-graduate me-2"></i>Select Student</div>
  <div style="padding:14px 16px">
    <form method="GET" class="d-flex gap-2 align-items-center">
      <select name="student_id" class="form-select form-select-sm" onchange="this.form.submit()"
              style="max-width:480px">
        <option value="">— Select student to view / add records —</option>
        <?php foreach ($students as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $studentId===$s['id']?'selected':'' ?>>
          <?= h($s['name']) ?> (<?= h($s['roll_no']) ?>) — <?= h($s['class_name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <?php if ($curStudent): ?>
      <span class="badge" style="background:#0891b2;font-size:.8rem">
        <?= h($curStudent['name']) ?> · <?= h($curStudent['class_name']) ?>
      </span>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if (!$studentId): ?>
<!-- No student selected yet -->
<div class="sec-card">
  <div style="padding:60px;text-align:center;color:var(--t2)">
    <i class="fas fa-folder-open fa-2x mb-3 d-block opacity-20"></i>
    <p style="font-size:.88rem">Select a student above to view their session records or upload a new one.</p>
  </div>
</div>

<?php else: ?>
<div class="row g-3">
  <!-- ── Left: Upload form ── -->
  <div class="col-lg-4">
    <div class="sec-card" style="position:sticky;top:70px">
      <div class="sec-card-header"
           style="background:linear-gradient(90deg,#0c4a6e,#0891b2);color:#fff">
        <i class="fas fa-upload me-2"></i>Add Session Record
        <span style="font-size:.74rem;opacity:.85;margin-left:6px">
          · <?= h($curStudent['name']) ?>
        </span>
      </div>
      <div style="padding:16px">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload">
          <input type="hidden" name="student_id" value="<?= $studentId ?>">

          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.8rem">
              Session Title <span class="text-danger">*</span>
            </label>
            <input type="text" name="title" class="form-control form-control-sm" required
                   placeholder="e.g. Session 3 — Behaviour Therapy">
          </div>

          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.8rem">Session Type</label>
            <input type="text" name="session_type" class="form-control form-control-sm"
                   list="sessionTypeList" placeholder="Therapy Session…">
            <datalist id="sessionTypeList">
              <?php foreach ($sessionTypes as $t): ?>
              <option value="<?= h($t) ?>">
              <?php endforeach; ?>
            </datalist>
          </div>

          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.8rem">Session Date</label>
            <input type="date" name="session_date" class="form-control form-control-sm"
                   value="<?= date('Y-m-d') ?>">
          </div>

          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.8rem">
              Notes / Description <small class="text-muted">(optional)</small>
            </label>
            <textarea name="description" class="form-control form-control-sm" rows="2"
                      placeholder="Session notes, observations…"></textarea>
          </div>

          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.8rem">
              File <span class="text-danger">*</span>
            </label>
            <input type="file" name="file" class="form-control form-control-sm" required
                   accept=".pdf,.mp4,.webm,.mov,.avi,.ogv">
            <div class="form-text mt-1" style="color:#64748b;font-size:.76rem">
              PDF or video (MP4/WEBM/MOV/AVI) · max 500 MB
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.8rem">Auto-Delete After</label>
            <select name="days" class="form-select form-select-sm">
              <option value="0">No auto-delete (keep permanently)</option>
              <option value="7">7 days</option>
              <option value="14">14 days</option>
              <option value="30">30 days</option>
              <option value="60">60 days</option>
              <option value="90">90 days</option>
            </select>
          </div>

          <button type="submit" class="btn btn-sm w-100"
                  style="background:#0891b2;color:#fff;font-weight:600">
            <i class="fas fa-upload me-1"></i>Upload Record
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- ── Right: Records list ── -->
  <div class="col-lg-8">
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center"
           style="background:linear-gradient(90deg,#0c4a6e,#0891b2);color:#fff">
        <span>
          <i class="fas fa-folder-open me-2"></i>
          Records — <?= h($curStudent['name']) ?>
          <span class="badge bg-light text-dark ms-2" style="font-size:.76rem">
            <?= count($records) ?>
          </span>
        </span>
        <small style="opacity:.8;font-size:.75rem">Student can view these in their portal</small>
      </div>

      <?php if (empty($records)): ?>
      <div style="padding:60px;text-align:center;color:var(--t2)">
        <i class="fas fa-folder-open fa-2x mb-2 d-block opacity-25"></i>
        <p style="font-size:.85rem">No session records yet for <?= h($curStudent['name']) ?>.<br>
        Upload the first record using the form on the left.</p>
      </div>
      <?php else: ?>
      <?php foreach ($records as $r):
        $hoursLeft = $r['hours_left'];
        $noExpiry  = ($hoursLeft === null);
        $fileUrl   = url('/assets/uploads/ilc-records/' . h($r['filename']));
        $isVideo   = $r['file_type'] === 'video';
        $icon      = $isVideo ? 'fa-film' : 'fa-file-pdf';
        $iconColor = $isVideo ? '#2563eb' : '#dc2626';

        if (!$noExpiry) {
            $daysLeft = floor($hoursLeft / 24);
            $hrsRem   = $hoursLeft % 24;
            $timeLabel= $daysLeft > 0 ? "{$daysLeft}d {$hrsRem}h" : "{$hoursLeft}h";
            $urgency  = $hoursLeft < 48 ? 'danger' : ($hoursLeft < 168 ? 'warning' : 'secondary');
        }
      ?>
      <div style="padding:16px;border-bottom:1px solid var(--border)">
        <div class="d-flex justify-content-between align-items-start gap-3">
          <div style="flex:1;min-width:0">
            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
              <i class="fas <?= $icon ?>" style="color:<?= $iconColor ?>"></i>
              <span class="fw-semibold" style="font-size:.9rem"><?= h($r['title']) ?></span>
              <span class="badge <?= $isVideo ? 'bg-primary' : 'bg-danger' ?>" style="font-size:.68rem">
                <?= strtoupper($r['file_type']) ?>
              </span>
              <?php if (!empty($r['session_type'])): ?>
              <span class="badge bg-light text-dark" style="font-size:.7rem;border:1px solid #e2e8f0">
                <?= h($r['session_type']) ?>
              </span>
              <?php endif; ?>
            </div>

            <div class="d-flex gap-3 flex-wrap mb-1" style="font-size:.78rem;color:var(--t2)">
              <?php if (!empty($r['session_date'])): ?>
              <span><i class="fas fa-calendar-alt me-1"></i><?= date('d M Y', strtotime($r['session_date'])) ?></span>
              <?php endif; ?>
              <span><i class="fas fa-user me-1"></i><?= h($r['uploader_name'] ?? '—') ?></span>
              <span><i class="fas fa-clock me-1"></i>Uploaded <?= fDate($r['created_at']) ?></span>
            </div>

            <?php if ($r['description']): ?>
            <div style="font-size:.8rem;color:var(--t2);margin-bottom:4px;
                        background:#f8fafc;border-radius:5px;padding:5px 8px">
              <?= nl2br(h($r['description'])) ?>
            </div>
            <?php endif; ?>

            <?php if (!$noExpiry): ?>
            <div class="mt-1">
              <span class="badge bg-<?= $urgency ?>" style="font-size:.72rem">
                <i class="fas fa-trash-clock me-1"></i>Auto-deletes in <?= $timeLabel ?>
              </span>
            </div>
            <?php else: ?>
            <div class="mt-1">
              <span class="badge bg-success" style="font-size:.72rem">
                <i class="fas fa-infinity me-1"></i>Permanent — no auto-delete
              </span>
            </div>
            <?php endif; ?>
          </div>

          <!-- Actions -->
          <div class="d-flex flex-column gap-1 flex-shrink-0">
            <?php if (!$isVideo): ?>
            <a href="<?= $fileUrl ?>" target="_blank"
               class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 9px">
              <i class="fas fa-eye me-1"></i>View
            </a>
            <?php endif; ?>
            <a href="<?= $fileUrl ?>" download
               class="btn btn-xs btn-outline-secondary" style="font-size:.74rem;padding:2px 9px">
              <i class="fas fa-download me-1"></i>Download
            </a>
            <form method="POST" class="d-inline"
                  onsubmit="return confirm('Permanently delete this record now?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="student_id" value="<?= $studentId ?>">
              <input type="hidden" name="rec_id" value="<?= $r['id'] ?>">
              <button class="btn btn-xs btn-outline-danger w-100" style="font-size:.74rem;padding:2px 7px">
                <i class="fas fa-trash me-1"></i>Delete
              </button>
            </form>
          </div>
        </div>

        <?php if ($isVideo): ?>
        <div class="mt-2">
          <video src="<?= $fileUrl ?>" controls
                 style="max-width:100%;max-height:200px;border-radius:6px;border:1px solid var(--border)">
          </video>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; // studentId ?>

<?php endif; // tableExists / colExists ?>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

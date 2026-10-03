<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('student');
$student = getStudentByUserId($user['id']);
$db      = getDB();

if (!$student) { setFlash('danger', 'Student profile not found.'); redirect('/portal/logout.php'); }

// Table + column check
$tableExists      = false;
$studentColExists = false;
try { $db->query('SELECT 1 FROM ilc_session_records LIMIT 0'); $tableExists = true; } catch (Exception $e) {}
if ($tableExists) {
    try { $db->query('SELECT student_id FROM ilc_session_records LIMIT 0'); $studentColExists = true; } catch (Exception $e) {}
}

$records = [];
if ($tableExists && $studentColExists) {
    $st = $db->prepare(
        'SELECT r.*, u.name AS uploader_name
         FROM ilc_session_records r
         LEFT JOIN users u ON u.id = r.uploaded_by
         WHERE r.student_id = ?
           AND (r.expires_at IS NULL OR r.expires_at > NOW())
         ORDER BY r.created_at DESC'
    );
    $st->execute([$student['id']]);
    $records = $st->fetchAll();
}

$uploadDir = __DIR__ . '/../assets/uploads/ilc-records/';

pageHead('Session Records', 'student');
$links = getStudentLinks();
?>
<div class="portal-wrap">
<?php sidebar('student', 'session-records', $links, $user); ?>
<div class="main-area">
<?php topbar('Session Records', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- ILC branding strip -->
<div class="d-flex align-items-center gap-3 mb-4 p-3"
     style="background:linear-gradient(90deg,#f0f9ff,#ecfeff);border-radius:10px;border:1px solid #7dd3fc;">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC"
       style="width:44px;height:44px;object-fit:contain;flex-shrink:0;" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#0c4a6e;letter-spacing:.8px;text-transform:uppercase">
      My Session Records
    </div>
    <div style="font-size:.78rem;color:#475569">Inclusive Learning Centre · Bahria Model College Bin Qasim</div>
  </div>
</div>

<?php if (!$tableExists || !$studentColExists): ?>
<div class="sec-card">
  <div style="padding:60px;text-align:center;color:var(--t2)">
    <i class="fas fa-folder-open fa-2x mb-3 d-block" style="opacity:.15"></i>
    <div style="font-size:.88rem">Session Records are not yet set up.</div>
    <div style="font-size:.78rem;margin-top:6px">Contact your ILC administrator.</div>
  </div>
</div>

<?php elseif (empty($records)): ?>
<div class="sec-card">
  <div style="padding:60px;text-align:center;color:var(--t2)">
    <i class="fas fa-folder-open fa-2x mb-3 d-block" style="opacity:.15"></i>
    <div style="font-size:.88rem">No session records have been uploaded for your profile yet.</div>
    <div style="font-size:.78rem;margin-top:6px;color:var(--t2)">
      Records are added by your ILC teacher/therapist. Check back later.
    </div>
  </div>
</div>

<?php else: ?>
<div class="sec-card">
  <div class="sec-card-header"
       style="background:linear-gradient(90deg,#0c4a6e,#0891b2);color:#fff">
    <i class="fas fa-folder-open me-2"></i>My Session Records
    <span class="badge bg-light text-dark ms-2" style="font-size:.76rem"><?= count($records) ?></span>
  </div>

  <?php foreach ($records as $r):
    $isVideo  = $r['file_type'] === 'video';
    $icon     = $isVideo ? 'fa-film' : 'fa-file-pdf';
    $iconClr  = $isVideo ? '#2563eb' : '#dc2626';
    $fileUrl  = url('/assets/uploads/ilc-records/' . h($r['filename']));
    $fileExists = file_exists($uploadDir . $r['filename']);
  ?>
  <div style="padding:18px 20px;border-bottom:1px solid var(--border)">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">

      <!-- Record info -->
      <div style="flex:1;min-width:0">
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
          <i class="fas <?= $icon ?>" style="color:<?= $iconClr ?>;font-size:1rem"></i>
          <span class="fw-semibold" style="font-size:.92rem"><?= h($r['title']) ?></span>
          <span class="badge <?= $isVideo ? 'bg-primary' : 'bg-danger' ?>" style="font-size:.68rem">
            <?= strtoupper($r['file_type']) ?>
          </span>
          <?php if (!empty($r['session_type'])): ?>
          <span class="badge" style="background:#e0f2fe;color:#0c4a6e;font-size:.7rem;font-weight:600">
            <?= h($r['session_type']) ?>
          </span>
          <?php endif; ?>
        </div>

        <div class="d-flex flex-wrap gap-3 mb-2" style="font-size:.78rem;color:var(--t2)">
          <?php if (!empty($r['session_date'])): ?>
          <span>
            <i class="fas fa-calendar-alt me-1" style="color:#0891b2"></i>
            <strong>Session Date:</strong> <?= date('d M Y', strtotime($r['session_date'])) ?>
          </span>
          <?php endif; ?>
          <span>
            <i class="fas fa-user-md me-1" style="color:#0891b2"></i>
            <strong>Uploaded by:</strong> <?= h($r['uploader_name'] ?? '—') ?>
          </span>
          <span>
            <i class="fas fa-clock me-1" style="color:#0891b2"></i>
            <?= fDate($r['created_at']) ?>
          </span>
        </div>

        <?php if ($r['description']): ?>
        <div style="font-size:.82rem;color:#334155;background:#f0f9ff;
                    border-radius:6px;padding:7px 10px;border:1px solid #bae6fd;margin-bottom:6px">
          <?= nl2br(h($r['description'])) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($r['expires_at'])): ?>
        <div style="font-size:.74rem;color:#92400e">
          <i class="fas fa-exclamation-triangle me-1"></i>
          This record will be automatically removed on
          <?= date('d M Y', strtotime($r['expires_at'])) ?>.
          Download it before then.
        </div>
        <?php endif; ?>
      </div>

      <!-- Actions -->
      <?php if ($fileExists): ?>
      <div class="d-flex flex-column gap-2 flex-shrink-0">
        <?php if (!$isVideo): ?>
        <a href="<?= $fileUrl ?>" target="_blank"
           class="btn btn-sm" style="background:#0891b2;color:#fff;font-size:.8rem">
          <i class="fas fa-eye me-1"></i>View PDF
        </a>
        <?php endif; ?>
        <a href="<?= $fileUrl ?>" download
           class="btn btn-sm btn-outline-secondary" style="font-size:.8rem">
          <i class="fas fa-download me-1"></i>Download
        </a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Video player -->
    <?php if ($isVideo && $fileExists): ?>
    <div class="mt-3">
      <video src="<?= $fileUrl ?>" controls
             style="max-width:100%;border-radius:8px;border:1px solid var(--border);max-height:260px">
      </video>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

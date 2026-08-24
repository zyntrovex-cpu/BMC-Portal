<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('teacher');
$db   = getDB();

$tableExists = false;
try { $db->query('SELECT 1 FROM academic_calendars LIMIT 0'); $tableExists = true; } catch (Exception $e) {}

$calendars = [];
if ($tableExists) {
    $calendars = $db->query(
        'SELECT ac.*, u.name AS uploader_name FROM academic_calendars ac
         LEFT JOIN users u ON u.id = ac.uploaded_by
         ORDER BY ac.year DESC, ac.created_at DESC'
    )->fetchAll();
}

pageHead('Academic Calendar', 'teacher');
$links = getTeacherLinks();
?>
<div class="portal-wrap">
<?php sidebar('teacher', 'calendar', $links, $user); ?>
<div class="main-area">
<?php topbar('Academic Calendar', $user); ?>
<div class="page-content">

<?php if (!$tableExists || empty($calendars)): ?>
<div class="sec-card p-4 text-center text-muted">
  <i class="fas fa-calendar-week fa-2x mb-2 d-block opacity-25"></i>
  No academic calendars published yet.
</div>
<?php else: ?>
<?php foreach ($calendars as $c):
  $ext = strtolower(pathinfo($c['filename'], PATHINFO_EXTENSION));
  $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
  $isPdf   = ($ext === 'pdf');
?>
<div class="sec-card mb-3">
  <div class="sec-card-header d-flex justify-content-between align-items-center">
    <div>
      <i class="fas fa-<?= $isPdf?'file-pdf text-danger':'image text-primary' ?> me-2"></i>
      <strong><?= h($c['title']) ?></strong>
      <span class="badge bg-secondary ms-2"><?= h($c['year']) ?></span>
    </div>
    <a href="<?= url('/assets/uploads/calendar/' . h($c['filename'])) ?>"
       download class="btn btn-xs btn-outline-secondary" style="font-size:.74rem;padding:2px 8px">
      <i class="fas fa-download me-1"></i>Download
    </a>
  </div>
  <div style="padding:14px 16px">
    <?php if ($c['description']): ?>
    <p style="font-size:.86rem;color:var(--t2);margin-bottom:10px"><?= h($c['description']) ?></p>
    <?php endif; ?>
    <?php if ($isImage): ?>
    <a href="<?= url('/assets/uploads/calendar/' . h($c['filename'])) ?>" target="_blank">
      <img src="<?= url('/assets/uploads/calendar/' . h($c['filename'])) ?>"
           style="max-width:100%;max-height:500px;border-radius:6px;border:1px solid var(--border)" alt="calendar">
    </a>
    <?php elseif ($isPdf): ?>
    <a href="<?= url('/assets/uploads/calendar/' . h($c['filename'])) ?>" target="_blank"
       class="btn btn-sm btn-outline-danger">
      <i class="fas fa-file-pdf me-2"></i>Open PDF
    </a>
    <?php endif; ?>
    <div style="font-size:.75rem;color:var(--t3);margin-top:8px">
      Published by <?= h($c['uploader_name'] ?? '—') ?> · <?= fDate($c['created_at']) ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

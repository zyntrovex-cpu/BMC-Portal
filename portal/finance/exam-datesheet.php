<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('finance');
$db   = getDB();

$sheets = [];
try {
    $st = $db->query(
        "SELECT ds.*, COALESCE(ds.term,'General') AS term
         FROM exam_date_sheets ds
         WHERE ds.status = 'published'
         ORDER BY ds.academic_year DESC, ds.wing, ds.created_at DESC"
    );
    $sheets = $st->fetchAll();
} catch (Exception $e) {}

$iconMap    = ['pdf'=>'fa-file-pdf text-danger','xlsx'=>'fa-file-excel text-success','xls'=>'fa-file-excel text-success','doc'=>'fa-file-word text-primary','docx'=>'fa-file-word text-primary'];
$wingLabels = ['main'=>'Main Campus','montessori'=>'Montessori','ilc'=>'ILC','all'=>'All'];

pageHead('Exam Date Sheets', 'finance');
$links = getFinanceLinks();
?>
<div class="portal-wrap">
<?php sidebar('finance','exam-datesheet',$links,$user); ?>
<div class="main-area">
<?php topbar('Exam Date Sheets',$user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (empty($sheets)): ?>
<div class="sec-card"><div class="text-center py-5 text-muted"><i class="fas fa-calendar-times fa-2x mb-3" style="opacity:.3"></i><p class="mb-0">No exam date sheets published yet.</p></div></div>
<?php else: ?>
<?php foreach ($sheets as $ds):
  $icon    = $iconMap[$ds['file_type'] ?? ''] ?? 'fa-file text-secondary';
  $sizeFmt = $ds['file_size'] ? (($ds['file_size'] > 1048576) ? round($ds['file_size']/1048576,1).' MB' : round($ds['file_size']/1024).' KB') : '';
?>
<div class="sec-card mb-3">
  <div class="sec-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span class="fw-bold"><i class="fas fa-calendar-day me-2"></i><?= h($ds['title']) ?>
      <span class="badge bg-primary ms-1" style="font-size:.72rem"><?= h($ds['term']) ?></span>
      <span class="badge bg-secondary ms-1" style="font-size:.72rem"><?= h($wingLabels[$ds['wing']] ?? $ds['wing']) ?></span>
    </span>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <small class="text-muted">AY: <?= h($ds['academic_year']) ?></small>
      <?php if ($ds['stored_filename']): ?>
      <?php $isPdf = ($ds['file_type'] ?? '') === 'pdf'; ?>
      <a href="<?= url('/portal/api/serve-document.php') ?>?type=datesheet&id=<?= $ds['id'] ?><?= $isPdf ? '&inline=1' : '' ?>" target="_blank" class="btn btn-sm btn-outline-info">
        <i class="fas fa-eye me-1"></i>View
      </a>
      <a href="<?= url('/portal/api/serve-document.php') ?>?type=datesheet&id=<?= $ds['id'] ?>" class="btn btn-sm btn-success">
        <i class="fas fa-download me-1"></i>Download <?= strtoupper($ds['file_type'] ?? '') ?>
      </a>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($ds['notes']): ?><div class="px-3 py-2 text-muted" style="font-size:.84rem;border-bottom:1px solid #f1f5f9"><i class="fas fa-info-circle me-1"></i><?= h($ds['notes']) ?></div><?php endif; ?>
  <div style="padding:12px 16px">
    <?php if ($ds['stored_filename']): ?>
    <div class="d-flex align-items-center gap-3"><i class="fas <?= $icon ?> fa-2x opacity-75"></i><div><div class="fw-semibold" style="font-size:.88rem"><?= strtoupper($ds['file_type']??'') ?> Document</div><div style="font-size:.77rem;color:var(--t3)"><?= $sizeFmt ?></div></div></div>
    <?php else: ?><span class="text-muted" style="font-size:.84rem"><i class="fas fa-clock me-1"></i>Document not yet uploaded.</span><?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div></div></div>
<?php pageFooter(); ?>

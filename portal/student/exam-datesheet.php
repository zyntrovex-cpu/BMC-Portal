<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('student');
$student = getStudentByUserId($user['id']);
if (!$student) { setFlash('danger', 'Student record not found.'); redirect('/portal/index.php'); }

$db = getDB();

$studentWing  = 'main';
$isMontessori = false;
$classGrade   = -1;
try {
    $wst = $db->prepare('SELECT COALESCE(wing,"main") AS wing, COALESCE(grade,-1) AS grade, COALESCE(is_montessori,0) AS is_montessori FROM classes WHERE id=?');
    $wst->execute([$student['class_id']]);
    $classRow    = $wst->fetch();
    $studentWing = $classRow ? ($classRow['wing'] ?: 'main') : 'main';
    $classGrade  = $classRow ? (int)$classRow['grade'] : -1;
    $isMontessori= $classRow ? (bool)$classRow['is_montessori'] : false;
} catch (Exception $e) {}

// Montessori grade < 2 → no formal exams
if ($isMontessori && $classGrade < 2) {
    $links = getStudentLinks();
    pageHead('Exam Date Sheet', 'student');
    ?>
<div class="portal-wrap">
<?php sidebar('student','exam-datesheet',$links,$user); ?>
<div class="main-area">
<?php topbar('Exam Date Sheet',$user); ?>
<div class="page-content">
<div class="sec-card">
  <div class="sec-card-body text-center py-5">
    <i class="fas fa-child fa-3x mb-3" style="color:#f59e0b;opacity:.7"></i>
    <h5 class="fw-bold mb-2">No Formal Exams for Your Class</h5>
    <p class="text-muted mb-3">Your class uses Progress Reports and Formative Assessment rather than formal examination date sheets.</p>
    <a href="<?= url('/portal/student/progress-report.php') ?>" class="btn btn-sm btn-primary"><i class="fas fa-chart-line me-1"></i>View Progress Report</a>
  </div>
</div>
</div></div></div>
<?php pageFooter(); return; }

// Load published date sheets for student's wing (+ 'all')
$sheets = [];
try {
    $st = $db->prepare(
        "SELECT ds.*, COALESCE(ds.term,'General') AS term
         FROM exam_date_sheets ds
         WHERE ds.status = 'published' AND (ds.wing = ? OR ds.wing = 'all')
         ORDER BY ds.academic_year DESC, ds.created_at DESC"
    );
    $st->execute([$studentWing]);
    $sheets = $st->fetchAll();
} catch (Exception $e) {}

$links = getStudentLinks();
pageHead('Exam Date Sheet', 'student');
?>
<div class="portal-wrap">
<?php sidebar('student','exam-datesheet',$links,$user); ?>
<div class="main-area">
<?php topbar('Exam Date Sheet',$user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold" style="color:var(--accent)"><i class="fas fa-calendar-day me-2"></i>Exam Date Sheets</h5>
  <div class="d-flex gap-2 align-items-center">
    <span class="badge" style="background:var(--accent);font-size:.8rem;padding:6px 12px">Class: <?= h($student['class_name'] ?? '') ?></span>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i>Print</button>
  </div>
</div>

<?php if (empty($sheets)): ?>
<div class="sec-card">
  <div class="sec-card-body text-center py-5 text-muted">
    <i class="fas fa-calendar-times fa-3x mb-3" style="opacity:.3"></i>
    <p class="mb-1 fw-semibold">No exam date sheets available yet.</p>
    <p class="mb-0" style="font-size:.85rem">Check back later or ask your teacher for the exam schedule.</p>
  </div>
</div>
<?php else: ?>
<?php
$iconMap = ['pdf'=>'fa-file-pdf text-danger','xlsx'=>'fa-file-excel text-success','xls'=>'fa-file-excel text-success','doc'=>'fa-file-word text-primary','docx'=>'fa-file-word text-primary'];
foreach ($sheets as $ds):
  $icon    = $iconMap[$ds['file_type'] ?? ''] ?? 'fa-file text-secondary';
  $sizeFmt = $ds['file_size'] ? (($ds['file_size'] > 1048576) ? round($ds['file_size']/1048576,1).' MB' : round($ds['file_size']/1024).' KB') : '';
?>
<div class="sec-card mb-3">
  <div class="sec-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span class="fw-bold">
      <i class="fas fa-calendar-day me-2"></i><?= h($ds['title']) ?>
      <span class="badge bg-primary ms-1" style="font-size:.72rem"><?= h($ds['term']) ?></span>
    </span>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <small class="text-muted">AY: <?= h($ds['academic_year']) ?></small>
      <?php if ($ds['stored_filename']): ?>
      <?php $isPdf = ($ds['file_type'] ?? '') === 'pdf'; ?>
      <?php $dsBase = url('/portal/api/serve-document.php') . '?type=datesheet&id=' . (int)$ds['id']; ?>
      <a href="<?= $dsBase . ($isPdf ? '&inline=1' : '') ?>" target="_blank" class="btn btn-sm btn-outline-info">
        <i class="fas fa-eye me-1"></i>View
      </a>
      <a href="<?= $dsBase ?>"
         class="btn btn-sm btn-success">
        <i class="fas fa-download me-1"></i>Download <?= strtoupper($ds['file_type'] ?? '') ?>
        <span class="ms-1 opacity-75" style="font-size:.76rem"><?= $sizeFmt ?></span>
      </a>
      <a href="<?= $dsBase . ($isPdf ? '&inline=1' : '') ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-print me-1"></i>Print
      </a>
      <?php else: ?><span class="badge bg-secondary" style="font-size:.72rem">No file</span><?php endif; ?>
    </div>
  </div>
  <?php if ($ds['notes']): ?>
  <div class="px-3 py-2 text-muted" style="font-size:.84rem;border-bottom:1px solid #f1f5f9">
    <i class="fas fa-info-circle me-1"></i><?= h($ds['notes']) ?>
  </div>
  <?php endif; ?>
  <div style="padding:16px">
    <div class="d-flex align-items-center gap-3">
      <?php if ($ds['stored_filename']): ?>
      <i class="fas <?= $icon ?> fa-2x opacity-75"></i>
      <div>
        <div class="fw-semibold" style="font-size:.9rem"><?= strtoupper($ds['file_type'] ?? '') ?> Document</div>
        <div style="font-size:.78rem;color:var(--t3)"><?= $sizeFmt ?></div>
      </div>
      <?php else: ?>
      <div class="text-muted" style="font-size:.84rem"><i class="fas fa-clock me-1"></i>Document not yet uploaded. Please check back later.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div></div></div>
<?php pageFooter(); ?>
<style>
@media print {
  .sidebar,.topbar,.portal-header,button,.btn{display:none!important}
  .main-area{margin:0!important;padding:0!important}
  .page-content{padding:10px!important}
  .sec-card{border:1px solid #ddd!important;margin-bottom:16px!important}
}
</style>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/db.php';

$user = requireAuth('student');
$db   = getDB();

// Get this student's class info
$stRow = null;
try {
    $st = $db->prepare(
        'SELECT s.class_id, c.is_ilc, c.is_montessori, c.name AS class_name
         FROM students s JOIN classes c ON c.id=s.class_id
         WHERE s.user_id=? AND s.deleted_at IS NULL LIMIT 1'
    );
    $st->execute([$user['id']]);
    $stRow = $st->fetch();
} catch (Exception $e) {}

// Only main campus students should see this page
if ($stRow && (($stRow['is_ilc'] ?? 0) || ($stRow['is_montessori'] ?? 0))) {
    redirect('/portal/student/dashboard.php');
}

$classId = $stRow ? (int)$stRow['class_id'] : 0;

// Fetch syllabuses: those for this class or for all classes
$docs = [];
try {
    $where  = '(sd.class_id IS NULL OR sd.class_id = ?)';
    $params = [$classId];
    $st = $db->prepare(
        "SELECT sd.*, c.name AS class_name, u.name AS uploader_name
         FROM syllabus_documents sd
         LEFT JOIN classes c ON c.id = sd.class_id
         LEFT JOIN users u ON u.id = sd.uploaded_by
         WHERE $where
         ORDER BY sd.created_at DESC"
    );
    $st->execute($params);
    $docs = $st->fetchAll();
} catch (Exception $e) {}

$links = getStudentLinks();
pageHead('Syllabus', 'student');
?>
<div class="portal-wrap">
<?php sidebar('student', 'syllabus', $links, $user); ?>
<div class="main-area">
<?php topbar('Syllabus', $user); ?>
<div class="page-content">

<div class="sec-card">
  <div class="sec-card-header">
    <i class="fas fa-book me-2"></i>Syllabus Documents
    <?php if ($stRow): ?>
    <small class="text-muted fw-normal ms-2" style="font-size:.78rem">— <?= h($stRow['class_name']) ?></small>
    <?php endif; ?>
  </div>
  <?php if (empty($docs)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2);font-size:.88rem">
    <i class="fas fa-book fa-2x mb-3" style="opacity:.3"></i>
    <p class="mb-0">No syllabus documents are available yet.</p>
  </div>
  <?php else: ?>
  <div class="list-group list-group-flush">
    <?php foreach ($docs as $doc):
      $iconMap = ['pdf'=>'fa-file-pdf text-danger','xlsx'=>'fa-file-excel text-success','xls'=>'fa-file-excel text-success','doc'=>'fa-file-word text-primary','docx'=>'fa-file-word text-primary'];
      $icon    = $iconMap[$doc['file_type']] ?? 'fa-file text-secondary';
      $sizeFmt = $doc['file_size'] > 1048576 ? round($doc['file_size']/1048576,1).' MB' : round($doc['file_size']/1024).' KB';
    ?>
    <div class="list-group-item d-flex align-items-center gap-3 py-3 px-3">
      <i class="fas <?= $icon ?> fa-lg flex-shrink-0"></i>
      <div class="flex-grow-1 min-w-0">
        <div class="fw-semibold" style="font-size:.9rem"><?= h($doc['title']) ?></div>
        <div style="font-size:.78rem;color:var(--t3)">
          <?= $doc['class_name'] ? h($doc['class_name']) : 'All Classes' ?>
          &middot; <?= h($doc['academic_year']) ?>
          &middot; <?= strtoupper($doc['file_type']) ?>, <?= $sizeFmt ?>
          &middot; <?= fDate($doc['created_at']) ?>
          <?php if ($doc['notes']): ?>
          &middot; <?= h($doc['notes']) ?>
          <?php endif; ?>
        </div>
      </div>
      <a href="/portal/api/serve-document.php?type=syllabus&id=<?= $doc['id'] ?>"
         class="btn btn-sm btn-outline-primary flex-shrink-0">
        <i class="fas fa-download me-1"></i>Download
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

</div></div></div>
<?php pageFooter(); ?>

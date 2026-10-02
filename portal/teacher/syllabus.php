<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/db.php';

$user    = requireAuth('teacher', 'ilc_teacher', 'montessori_teacher', 'examination_head');
$db      = getDB();

// Determine teacher wing
$teacherWing = null;
try {
    $tw = $db->prepare('SELECT wing FROM teachers WHERE user_id=?');
    $tw->execute([$user['id']]);
    $row = $tw->fetch();
    $teacherWing = $row['wing'] ?? null;
} catch (Exception $e) {}
if (!$teacherWing) {
    if ($user['role'] === 'montessori_teacher') $teacherWing = 'montessori';
    elseif ($user['role'] === 'ilc_teacher')    $teacherWing = 'ilc';
    else                                         $teacherWing = 'main';
}

// Only main-wing teachers (and exam head) see syllabuses
if ($teacherWing !== 'main' && $user['role'] !== 'examination_head') {
    redirect('/portal/teacher/dashboard.php');
}

$docs = [];
try {
    $st = $db->prepare(
        "SELECT sd.*, c.name AS class_name, u.name AS uploader_name
         FROM syllabus_documents sd
         LEFT JOIN classes c ON c.id = sd.class_id
         JOIN users u ON u.id = sd.uploaded_by
         ORDER BY sd.created_at DESC"
    );
    $st->execute();
    $docs = $st->fetchAll();
} catch (Exception $e) {}

if ($user['role'] === 'examination_head') {
    $links = getExamHeadLinks();
    $portalRole = 'examination_head';
    $activeKey  = 'syllabus';
} else {
    $links = $user['role'] === 'montessori_teacher' ? getMonteTeacherLinks() : getTeacherLinks();
    $portalRole = $user['role'];
    $activeKey  = 'syllabus';
}

pageHead('Syllabus', $portalRole);
?>
<div class="portal-wrap">
<?php sidebar($portalRole, $activeKey, $links, $user); ?>
<div class="main-area">
<?php topbar('Syllabus', $user); ?>
<div class="page-content">

<div class="sec-card">
  <div class="sec-card-header">
    <i class="fas fa-book me-2"></i>Syllabus Documents
    <small class="text-muted fw-normal ms-2" style="font-size:.78rem">— Main Campus (Class 1–12)</small>
  </div>
  <?php if (empty($docs)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2);font-size:.88rem">
    <i class="fas fa-book fa-2x mb-3" style="opacity:.3"></i>
    <p class="mb-0">No syllabus documents are available yet.</p>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr><th>Title</th><th>Class</th><th>Year</th><th>Type</th><th>Size</th><th>Uploaded By</th><th>Date</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($docs as $doc):
          $iconMap = ['pdf'=>'fa-file-pdf text-danger','xlsx'=>'fa-file-excel text-success','xls'=>'fa-file-excel text-success','doc'=>'fa-file-word text-primary','docx'=>'fa-file-word text-primary'];
          $icon    = $iconMap[$doc['file_type']] ?? 'fa-file text-secondary';
          $sizeFmt = $doc['file_size'] > 1048576 ? round($doc['file_size']/1048576,1).' MB' : round($doc['file_size']/1024).' KB';
        ?>
        <tr>
          <td class="fw-semibold">
            <?= h($doc['title']) ?>
            <?php if ($doc['notes']): ?><div style="font-size:.75rem;color:#9ca3af"><?= h($doc['notes']) ?></div><?php endif; ?>
          </td>
          <td><?= $doc['class_name'] ? h($doc['class_name']) : '<span class="text-muted">All Classes</span>' ?></td>
          <td><?= h($doc['academic_year']) ?></td>
          <td><i class="fas <?= $icon ?> me-1"></i><?= strtoupper($doc['file_type']) ?></td>
          <td style="font-size:.8rem"><?= $sizeFmt ?></td>
          <td style="font-size:.8rem"><?= h($doc['uploader_name']) ?></td>
          <td style="font-size:.78rem;color:#6b7280"><?= fDate($doc['created_at']) ?></td>
          <td>
            <a href="/portal/api/serve-document.php?type=syllabus&id=<?= $doc['id'] ?>"
               class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 8px">
              <i class="fas fa-download me-1"></i>Download
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

</div></div></div>
<?php pageFooter(); ?>

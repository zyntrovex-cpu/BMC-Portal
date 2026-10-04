<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('teacher', 'montessori_teacher', 'ilc_teacher', 'wing_head', 'vp_montessori');
requirePermission('timetable');
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);
if (!$teacher) {
    if (!in_array($user['role'], ['wing_head', 'vp_montessori'], true)) { setFlash('danger','Teacher record not found.'); redirect('/portal/index.php'); }
    $teacher = ['id' => 0, 'subject_id' => 0, 'name' => $user['name'], 'is_ilc' => 0];
}

// Determine teacher's wing for timetable documents
$teacherWing = 'main';
try {
    if ($user['role'] === 'ilc_teacher' || (isset($teacher['is_ilc']) && $teacher['is_ilc'])) {
        $teacherWing = 'ilc';
    } elseif (in_array($user['role'], ['montessori_teacher', 'wing_head', 'vp_montessori'], true)) {
        $teacherWing = 'montessori';
    }
} catch (Exception $e) {}

$ttDocs = [];
try {
    $tdst = $db->prepare(
        "SELECT td.*, u.name AS uploader_name FROM timetable_documents td
         LEFT JOIN users u ON u.id = td.uploaded_by
         WHERE (td.wing = ? OR td.wing = 'all') AND td.status = 'active'
         ORDER BY td.created_at DESC"
    );
    $tdst->execute([$teacherWing]);
    $ttDocs = $tdst->fetchAll();
} catch (Exception $e) {}

pageHead('Timetable', $user['role']);
$links = match($user['role']) {
    'montessori_teacher' => getMonteTeacherLinks(),
    'ilc_teacher'        => getIlcTeacherLinks(),
    'wing_head'          => getWingHeadLinks(),
    'vp_montessori'      => getVpMontessoriLinks(),
    default              => getTeacherLinks(),
};
?>
<div class="portal-wrap">
<?php sidebar($user['role'], 'timetable', $links, $user); ?>
<div class="main-area">
<?php topbar('My Timetable', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (empty($ttDocs)): ?>
<div class="sec-card">
  <div class="text-center py-5 text-muted">
    <i class="fas fa-calendar-times fa-2x mb-3 d-block" style="opacity:.3"></i>
    <p class="fw-semibold mb-1">No timetable uploaded yet.</p>
    <p style="font-size:.85rem" class="mb-0">Check back later or contact administration.</p>
  </div>
</div>
<?php else: ?>
<?php foreach ($ttDocs as $doc):
  $iconMap  = ['pdf'=>'fa-file-pdf','xlsx'=>'fa-file-excel','xls'=>'fa-file-excel','doc'=>'fa-file-word','docx'=>'fa-file-word'];
  $iconClr  = ['pdf'=>'#dc2626','xlsx'=>'#16a34a','xls'=>'#16a34a','doc'=>'#2563eb','docx'=>'#2563eb'];
  $bgClr    = ['pdf'=>'#fee2e2','xlsx'=>'#dcfce7','xls'=>'#dcfce7','doc'=>'#dbeafe','docx'=>'#dbeafe'];
  $ft       = $doc['file_type'] ?? '';
  $icon     = $iconMap[$ft] ?? 'fa-file';
  $iclr     = $iconClr[$ft] ?? '#6b7280';
  $ibg      = $bgClr[$ft]   ?? '#f3f4f6';
  $isPdf    = $ft === 'pdf';
  $sizeFmt  = $doc['file_size'] ? (($doc['file_size'] > 1048576) ? round($doc['file_size']/1048576,1).' MB' : round($doc['file_size']/1024).' KB') : '';
  $baseUrl  = url('/portal/api/serve-document.php') . '?type=timetable&id=' . (int)$doc['id'];
?>
<div class="sec-card mb-3">
  <div style="padding:20px 24px">
    <div class="d-flex align-items-center gap-4 flex-wrap">
      <div style="width:60px;height:60px;border-radius:14px;background:<?= $ibg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i class="fas <?= $icon ?>" style="font-size:1.6rem;color:<?= $iclr ?>"></i>
      </div>
      <div class="flex-grow-1">
        <div class="fw-bold" style="font-size:1.05rem;color:var(--accent)"><?= h($doc['title']) ?></div>
        <div class="d-flex flex-wrap gap-3 mt-1" style="font-size:.8rem;color:var(--t2)">
          <span><i class="fas fa-calendar-alt me-1"></i><?= h($doc['academic_year']) ?></span>
          <?php if ($doc['uploader_name']): ?>
          <span><i class="fas fa-user me-1"></i>Uploaded by <?= h($doc['uploader_name']) ?></span>
          <?php endif; ?>
          <?php if ($sizeFmt): ?>
          <span><i class="fas fa-hdd me-1"></i><?= strtoupper($ft) ?> &middot; <?= $sizeFmt ?></span>
          <?php endif; ?>
          <?php if ($doc['notes']): ?>
          <span><i class="fas fa-info-circle me-1"></i><?= h($doc['notes']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="<?= $baseUrl . ($isPdf ? '&inline=1' : '') ?>" target="_blank"
           class="btn btn-sm btn-outline-info">
          <i class="fas fa-eye me-1"></i>View
        </a>
        <a href="<?= $baseUrl ?>" class="btn btn-sm btn-success">
          <i class="fas fa-download me-1"></i>Download
        </a>
        <a href="<?= $baseUrl . ($isPdf ? '&inline=1' : '') ?>" target="_blank"
           <?= !$isPdf ? 'onclick="event.preventDefault();window.location.href=\'' . $baseUrl . '\'"' : '' ?>
           class="btn btn-sm btn-outline-secondary">
          <i class="fas fa-print me-1"></i>Print
        </a>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

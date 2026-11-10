<?php
/**
 * VP Main — Exam Date Sheets (read-only view).
 *
 * Policy: only the Examination Head may upload / replace / delete / toggle
 * Main Campus exam date sheets. The VP can see every Main Campus sheet
 * on this page but cannot post new ones. Uploads happen on
 * /portal/exam-head/exam-datesheet.php.
 *
 * Wing isolation: every SELECT filters WHERE wing='main', and the
 * download endpoint (portal/api/serve-document.php) enforces the same
 * check so a Main Campus sheet cannot be retrieved via direct link
 * from the Montessori or ILC wings.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('vp_main');
$db   = getDB();
$WING = 'main';

$sheets = [];
try {
    $st = $db->prepare(
        "SELECT ds.*, u.name AS creator_name
           FROM exam_date_sheets ds
           LEFT JOIN users u ON ds.created_by = u.id
          WHERE ds.wing = ?
          ORDER BY ds.academic_year DESC, ds.created_at DESC"
    );
    $st->execute([$WING]);
    $sheets = $st->fetchAll();
} catch (\Exception $e) {}

$iconMap = ['pdf'=>'fa-file-pdf text-danger','xlsx'=>'fa-file-excel text-success','xls'=>'fa-file-excel text-success','doc'=>'fa-file-word text-primary','docx'=>'fa-file-word text-primary'];

pageHead('Exam Date Sheets', 'vp_main');
$links = getVpLinks();
?>
<div class="portal-wrap">
<?php sidebar('vp_main','exam-datesheet',$links,$user); ?>
<div class="main-area">
<?php topbar('Exam Date Sheets — Main Campus',$user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card mb-3" style="border:1px solid #dbeafe;background:#eff6ff">
  <div style="padding:12px 16px;font-size:.82rem;color:#1e3a8a">
    <i class="fas fa-info-circle me-1"></i>
    <strong>Read-only view.</strong> Main Campus exam date sheets are posted by the Examination Head.
    New sheets are uploaded from
    <a href="<?= url('/portal/exam-head/exam-datesheet.php') ?>" class="fw-semibold">Exam Head → Exam Date Sheets</a>.
  </div>
</div>

<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-calendar-day me-2"></i>Main Campus Date Sheets <span class="fw-normal opacity-75 ms-1" style="font-size:.8rem">(<?= count($sheets) ?>)</span></div>
  <?php if (empty($sheets)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2);font-size:.88rem">
    <i class="fas fa-calendar-day fa-2x mb-3" style="opacity:.3"></i>
    <p class="mb-0">No Main Campus date sheets posted yet.</p>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.83rem">
      <thead class="table-light">
        <tr><th>Title</th><th>Term</th><th>Year</th><th>Status</th><th>File</th><th>By</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($sheets as $ds):
          $icon    = $iconMap[$ds['file_type'] ?? ''] ?? 'fa-file text-secondary';
          $sizeFmt = $ds['file_size'] ? (($ds['file_size'] > 1048576) ? round($ds['file_size']/1048576,1).' MB' : round($ds['file_size']/1024).' KB') : '';
        ?>
        <tr>
          <td class="fw-semibold"><?= h($ds['title']) ?><?php if($ds['notes']):?><div style="font-size:.73rem;color:#9ca3af"><?= h($ds['notes']) ?></div><?php endif;?></td>
          <td style="font-size:.79rem"><?= h($ds['term'] ?? 'General') ?></td>
          <td><?= h($ds['academic_year']) ?></td>
          <td><?= $ds['status']==='published' ? '<span class="badge bg-success">Published</span>' : '<span class="badge bg-warning text-dark">Draft</span>' ?></td>
          <td>
            <?php if($ds['stored_filename']): ?>
              <i class="fas <?= $icon ?> me-1"></i>
              <span style="font-size:.76rem"><?= strtoupper($ds['file_type'] ?? '') ?> &middot; <?= $sizeFmt ?></span>
            <?php else: ?>
              <span class="text-muted" style="font-size:.76rem">No file</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.78rem"><?= h($ds['creator_name'] ?? '—') ?></td>
          <td style="font-size:.74rem;color:#6b7280;white-space:nowrap"><?= fDate($ds['created_at']) ?></td>
          <td>
            <?php if($ds['stored_filename']):
              $isPdf  = ($ds['file_type'] ?? '') === 'pdf';
              $dsBase = url('/portal/api/serve-document.php') . '?type=datesheet&id=' . (int)$ds['id'];
            ?>
            <a href="<?= $dsBase . ($isPdf ? '&inline=1' : '') ?>" target="_blank"
               class="btn btn-xs btn-outline-info" style="font-size:.72rem;padding:2px 7px" title="View">
              <i class="fas fa-eye me-1"></i>View
            </a>
            <a href="<?= $dsBase ?>"
               class="btn btn-xs btn-outline-primary" style="font-size:.72rem;padding:2px 7px" title="Download">
              <i class="fas fa-download"></i>
            </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

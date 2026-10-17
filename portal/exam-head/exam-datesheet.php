<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('examination_head');
requirePermission('eh_datesheet');
$db      = getDB();
$yearNow = (int)date('Y');
$selfUrl = '/portal/exam-head/exam-datesheet.php';
$WING    = 'main';

$termOptions  = ['Mid-Term', 'Final-Term', 'Unit Test 1', 'Unit Test 2', 'Annual', 'Mock Exam', 'General'];
$allowedTypes = ['pdf', 'xlsx', 'xls', 'doc', 'docx'];
$maxSize      = 10 * 1024 * 1024;
$uploadDir    = __DIR__ . '/../../uploads/documents/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

function dsFileUpload(array $file, array $allowedTypes, int $maxSize, string $uploadDir): array {
    $origName = basename($file['name']);
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes, true)) return ['error' => 'Invalid file type. Allowed: PDF, Excel, Word.'];
    if ($file['size'] > $maxSize)            return ['error' => 'File too large. Maximum 10 MB.'];
    if ($file['error'] !== UPLOAD_ERR_OK)    return ['error' => 'Upload failed.'];
    $stored = 'ds_' . uniqid('', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $stored)) return ['error' => 'Failed to save file.'];
    return ['stored' => $stored, 'original' => $origName, 'type' => $ext, 'size' => $file['size']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $title = trim($_POST['title'] ?? '');
        $term  = trim($_POST['term']  ?? 'General');
        $year  = trim($_POST['academic_year'] ?? "$yearNow-" . ($yearNow + 1));
        $notes = trim($_POST['notes'] ?? '');
        if (!in_array($term, $termOptions, true)) $term = 'General';
        if (!$title) { setFlash('danger', 'Title is required.'); redirect($selfUrl); }
        if (empty($_FILES['document']['name'])) { setFlash('danger', 'Please select a file.'); redirect($selfUrl); }
        $f = dsFileUpload($_FILES['document'], $allowedTypes, $maxSize, $uploadDir);
        if (isset($f['error'])) { setFlash('danger', $f['error']); redirect($selfUrl); }
        try {
            $db->prepare(
                'INSERT INTO exam_date_sheets (title,term,wing,academic_year,notes,original_filename,stored_filename,file_type,file_size,status,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,\'published\',?)'
            )->execute([$title, $term, $WING, $year, $notes ?: null, $f['original'], $f['stored'], $f['type'], $f['size'], $user['id']]);
        } catch (\PDOException $e) {
            @unlink($uploadDir . $f['stored']);
            setFlash('danger', 'Database error: ' . $e->getMessage());
            redirect($selfUrl);
        }
        logActivity($user['id'], 'exam_ds_upload', "Uploaded date sheet: \"$title\" ($year)");
        setFlash('success', "Date sheet \"$title\" uploaded and published.");
        redirect($selfUrl);
    }

    if ($action === 'replace_file') {
        $dsId = (int)($_POST['ds_id'] ?? 0);
        $chk  = $db->prepare('SELECT stored_filename FROM exam_date_sheets WHERE id=? AND wing=?');
        $chk->execute([$dsId, $WING]);
        $row  = $chk->fetch();
        if (!$row) { setFlash('danger', 'Not found.'); redirect($selfUrl); }
        if (empty($_FILES['document']['name'])) { setFlash('danger', 'Please select a file.'); redirect($selfUrl); }
        $f = dsFileUpload($_FILES['document'], $allowedTypes, $maxSize, $uploadDir);
        if (isset($f['error'])) { setFlash('danger', $f['error']); redirect($selfUrl); }
        if ($row['stored_filename']) { $p = $uploadDir . $row['stored_filename']; if (file_exists($p)) @unlink($p); }
        $db->prepare('UPDATE exam_date_sheets SET original_filename=?,stored_filename=?,file_type=?,file_size=? WHERE id=?')
           ->execute([$f['original'], $f['stored'], $f['type'], $f['size'], $dsId]);
        logActivity($user['id'], 'exam_ds_replace', "Replaced file for date sheet #$dsId");
        setFlash('success', 'File replaced successfully.');
        redirect($selfUrl);
    }

    if ($action === 'toggle_status') {
        $dsId = (int)($_POST['ds_id'] ?? 0);
        $cur  = $db->prepare('SELECT status, title FROM exam_date_sheets WHERE id=? AND wing=?');
        $cur->execute([$dsId, $WING]);
        $row  = $cur->fetch();
        if ($row) {
            $new = ($row['status'] === 'published') ? 'draft' : 'published';
            $db->prepare('UPDATE exam_date_sheets SET status=? WHERE id=?')->execute([$new, $dsId]);
            setFlash('success', $new === 'published' ? 'Date sheet published.' : 'Date sheet unpublished.');
        }
        redirect($selfUrl);
    }

    if ($action === 'delete') {
        $dsId = (int)($_POST['ds_id'] ?? 0);
        $cur  = $db->prepare('SELECT title, stored_filename, wing FROM exam_date_sheets WHERE id=?');
        $cur->execute([$dsId]);
        $row  = $cur->fetch();
        if ($row && $row['wing'] === $WING) {
            if ($row['stored_filename']) { $p = $uploadDir . $row['stored_filename']; if (file_exists($p)) @unlink($p); }
            $db->prepare('DELETE FROM exam_date_sheets WHERE id=?')->execute([$dsId]);
            logActivity($user['id'], 'exam_ds_delete', "Deleted: \"" . $row['title'] . '"');
            setFlash('success', 'Date sheet deleted.');
        }
        redirect($selfUrl);
    }
    redirect($selfUrl);
}

$sheets = [];
try {
    $st = $db->prepare(
        "SELECT ds.*, u.name AS creator_name FROM exam_date_sheets ds JOIN users u ON ds.created_by=u.id
         WHERE ds.wing=? ORDER BY ds.academic_year DESC, ds.created_at DESC"
    );
    $st->execute([$WING]);
    $sheets = $st->fetchAll();
} catch (\Exception $e) {}

$iconMap = ['pdf'=>'fa-file-pdf text-danger','xlsx'=>'fa-file-excel text-success','xls'=>'fa-file-excel text-success','doc'=>'fa-file-word text-primary','docx'=>'fa-file-word text-primary'];

pageHead('Exam Date Sheets', 'examination_head');
$links = getExamHeadLinks();
?>
<div class="portal-wrap">
<?php sidebar('examination_head','exam-datesheet',$links,$user); ?>
<div class="main-area">
<?php topbar('Exam Date Sheets — Main Campus',$user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Upload form -->
<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-upload me-2"></i>Upload Exam Date Sheet</div>
  <div style="padding:18px">
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label fw-semibold" style="font-size:.82rem">Title *</label>
          <input type="text" name="title" class="form-control form-control-sm" required placeholder="e.g. Mid-Term 2025-2026">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Term</label>
          <select name="term" class="form-select form-select-sm">
            <?php foreach ($termOptions as $t): ?><option value="<?= h($t) ?>"><?= h($t) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Academic Year *</label>
          <input type="text" name="academic_year" class="form-control form-control-sm" value="<?= $yearNow ?>-<?= $yearNow + 1 ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold" style="font-size:.82rem">File * <span class="fw-normal text-muted">(PDF/Excel/Word, max 10 MB)</span></label>
          <input type="file" name="document" class="form-control form-control-sm" required accept=".pdf,.xlsx,.xls,.doc,.docx">
        </div>
        <div class="col-md-1">
          <label class="form-label fw-semibold" style="font-size:.82rem">Notes</label>
          <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-upload me-1"></i>Upload &amp; Publish</button>
          <span class="text-muted ms-2" style="font-size:.78rem">The file will be immediately available to students and staff.</span>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- List -->
<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-calendar-day me-2"></i>Main Campus Exam Date Sheets <span class="fw-normal opacity-75 ms-1" style="font-size:.8rem">(<?= count($sheets) ?>)</span></div>
  <?php if (empty($sheets)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2);font-size:.88rem">
    <i class="fas fa-calendar-day fa-2x mb-3" style="opacity:.3"></i>
    <p class="mb-0">No date sheets uploaded yet. Use the form above to upload the first one.</p>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.83rem">
      <thead class="table-light">
        <tr><th>Title</th><th>Term</th><th>Year</th><th>Status</th><th>File</th><th>Uploaded By</th><th>Date</th><th style="white-space:nowrap">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($sheets as $ds):
          $icon    = $iconMap[$ds['file_type'] ?? ''] ?? 'fa-file text-secondary';
          $sizeFmt = $ds['file_size'] ? (($ds['file_size'] > 1048576) ? round($ds['file_size']/1048576,1).' MB' : round($ds['file_size']/1024).' KB') : '';
        ?>
        <tr>
          <td class="fw-semibold"><?= h($ds['title']) ?><?php if ($ds['notes']): ?><div style="font-size:.74rem;color:#9ca3af"><?= h($ds['notes']) ?></div><?php endif; ?></td>
          <td style="font-size:.8rem"><?= h($ds['term'] ?? 'General') ?></td>
          <td><?= h($ds['academic_year']) ?></td>
          <td><?= $ds['status'] === 'published' ? '<span class="badge bg-success">Published</span>' : '<span class="badge bg-warning text-dark">Draft</span>' ?></td>
          <td>
            <?php if ($ds['stored_filename']): ?>
            <div class="d-flex align-items-center gap-1">
              <i class="fas <?= $icon ?> me-1"></i>
              <span style="font-size:.77rem"><?= strtoupper($ds['file_type'] ?? '') ?> &middot; <?= $sizeFmt ?></span>
            </div>
            <?php else: ?><span class="text-muted" style="font-size:.78rem">No file</span><?php endif; ?>
          </td>
          <td style="font-size:.79rem"><?= h($ds['creator_name']) ?></td>
          <td style="font-size:.76rem;color:#6b7280;white-space:nowrap"><?= fDate($ds['created_at']) ?></td>
          <td>
            <div class="d-flex flex-wrap gap-1">
              <?php if ($ds['stored_filename']): ?>
              <?php $isPdf = ($ds['file_type'] ?? '') === 'pdf'; ?>
              <?php $dsBase = url('/portal/api/serve-document.php') . '?type=datesheet&id=' . (int)$ds['id']; ?>
              <a href="<?= $dsBase . ($isPdf ? '&inline=1' : '') ?>" target="_blank" class="btn btn-xs btn-outline-info" style="font-size:.72rem;padding:2px 7px" title="View"><i class="fas fa-eye me-1"></i>View</a>
              <a href="<?= $dsBase ?>" class="btn btn-xs btn-outline-primary" style="font-size:.72rem;padding:2px 7px" title="Download"><i class="fas fa-download"></i></a>
              <a href="<?= $dsBase . ($isPdf ? '&inline=1' : '') ?>" target="_blank" class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:2px 7px" title="Print"><i class="fas fa-print"></i></a>
              <?php endif; ?>
              <button class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:2px 7px"
                      onclick="openReplace(<?= $ds['id'] ?>,<?= htmlspecialchars(json_encode($ds['title'])) ?>)">
                <i class="fas fa-sync"></i>
              </button>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="ds_id" value="<?= $ds['id'] ?>">
                <button class="btn btn-xs <?= $ds['status']==='published'?'btn-outline-warning':'btn-outline-success' ?>"
                        style="font-size:.72rem;padding:2px 7px" title="<?= $ds['status']==='published'?'Unpublish':'Publish' ?>">
                  <i class="fas <?= $ds['status']==='published'?'fa-eye-slash':'fa-globe' ?>"></i>
                </button>
              </form>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this date sheet permanently?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="ds_id" value="<?= $ds['id'] ?>">
                <button class="btn btn-xs btn-outline-danger" style="font-size:.72rem;padding:2px 7px"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Replace File Modal -->
<div class="modal fade" id="replaceModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h6 class="modal-title"><i class="fas fa-sync me-2"></i>Replace File</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="replace_file">
      <input type="hidden" name="ds_id" id="replaceId">
      <div class="modal-body">
        <p class="text-muted mb-3" id="replaceTitle" style="font-size:.88rem"></p>
        <label class="form-label fw-semibold" style="font-size:.84rem">New File * <span class="fw-normal text-muted">(PDF/Excel/Word — max 10 MB)</span></label>
        <input type="file" name="document" class="form-control form-control-sm" required accept=".pdf,.xlsx,.xls,.doc,.docx">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-sync me-1"></i>Replace</button>
      </div>
    </form>
  </div></div>
</div>

</div></div></div>
<?php pageFooter(); ?>
<script>
function openReplace(id, title) {
    document.getElementById('replaceId').value = id;
    document.getElementById('replaceTitle').textContent = 'Replacing: ' + title;
    new bootstrap.Modal(document.getElementById('replaceModal')).show();
}
</script>

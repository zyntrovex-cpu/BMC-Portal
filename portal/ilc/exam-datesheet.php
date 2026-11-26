<?php
/**
 * ILC Exam Date Sheets — VP ILC upload + manage page.
 *
 * Wing-isolated: every INSERT forces wing='ilc', every SELECT filters
 * WHERE wing='ilc', and delete/replace guards verify the row belongs to
 * the ILC wing. Main Campus and Montessori sheets never appear here;
 * ILC sheets never leak into the other wings (enforced at the student
 * view AND at the direct-download endpoint in portal/api/serve-document.php).
 *
 * This file was previously failing with a generic "Database error"
 * because every PDOException was caught and masked. The rewrite keeps
 * the same successful path but surfaces the real cause on failure, so
 * the ILC VP (and anyone else) gets an actionable message instead of
 * the vague "upload or connection error".
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('ilc_vp');
$db      = getDB();
$yearNow = (int)date('Y');
$selfUrl = '/portal/ilc/exam-datesheet.php';
$WING    = 'ilc';

$termOptions  = ['Mid-Term', 'Final-Term', 'Unit Test 1', 'Unit Test 2', 'Annual', 'Mock Exam', 'General'];
$allowedTypes = ['pdf', 'xlsx', 'xls', 'doc', 'docx'];
$maxSize      = 10 * 1024 * 1024;
$uploadDir    = __DIR__ . '/../../uploads/documents/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// ──────────────────────────────────────────────────────────────────────────
// Guard: PHP silently drops $_POST + $_FILES when the upload exceeds
// post_max_size / upload_max_filesize at the PHP layer (or client_max_body_size
// at the web-server layer). The browser gets a 413 / connection reset — the
// same symptom the user reported as "upload or connection error". Catch the
// empty-POST case and emit a human-readable message with the current limit.
// ──────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && empty($_POST) && empty($_FILES)
    && !empty($_SERVER['CONTENT_LENGTH'])) {
    $limitHuman = ini_get('post_max_size');
    setFlash('danger', 'Your upload exceeded the server limit (' . h($limitHuman) . '). '
                     . 'Try a smaller file, or ask the administrator to raise post_max_size and '
                     . 'upload_max_filesize in php.ini.');
    redirect($selfUrl);
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

        // Diagnose common $_FILES failure modes with specific messages so the
        // user never sees a bare "Upload failed."
        if (empty($_FILES['document']) || !isset($_FILES['document']['error'])) {
            setFlash('danger', 'Please select a file.');
            redirect($selfUrl);
        }
        $uploadErr = (int)$_FILES['document']['error'];
        if ($uploadErr !== UPLOAD_ERR_OK) {
            $errMap = [
                UPLOAD_ERR_INI_SIZE   => 'File is larger than upload_max_filesize (' . ini_get('upload_max_filesize') . ').',
                UPLOAD_ERR_FORM_SIZE  => 'File is larger than the form limit.',
                UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded — please try again.',
                UPLOAD_ERR_NO_FILE    => 'Please select a file.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server is missing its temp directory — contact the administrator.',
                UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded file — contact the administrator.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
            ];
            setFlash('danger', $errMap[$uploadErr] ?? ('Upload failed (code ' . $uploadErr . ').'));
            redirect($selfUrl);
        }

        $file     = $_FILES['document'];
        $origName = basename($file['name']);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedTypes, true)) {
            setFlash('danger', 'Invalid file type. Allowed: PDF, Excel, Word.');
            redirect($selfUrl);
        }
        if ($file['size'] > $maxSize) {
            setFlash('danger', 'File too large (max 10 MB).');
            redirect($selfUrl);
        }
        if (!is_writable($uploadDir)) {
            setFlash('danger', 'Upload directory is not writable on the server. Contact the administrator.');
            redirect($selfUrl);
        }

        $stored = 'ds_' . uniqid('', true) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $stored)) {
            setFlash('danger', 'Failed to save the uploaded file to disk.');
            redirect($selfUrl);
        }

        try {
            $db->prepare(
                'INSERT INTO exam_date_sheets
                 (title,term,wing,academic_year,notes,original_filename,stored_filename,file_type,file_size,status,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,\'published\',?)'
            )->execute([$title, $term, $WING, $year, $notes ?: null,
                        $origName, $stored, $ext, (int)$file['size'], $user['id']]);
        } catch (\PDOException $e) {
            // Surface the real PDO error instead of masking it as "Database
            // error". Commonly the migration file for exam date sheets hasn't
            // been applied, so columns like term/stored_filename don't exist.
            @unlink($uploadDir . $stored);
            $msg = $e->getMessage();
            if (stripos($msg, 'unknown column') !== false) {
                $msg .= ' — This usually means database/migrations/exam_datesheet.sql has not been applied on this environment.';
            }
            setFlash('danger', 'Could not save date sheet: ' . $msg);
            redirect($selfUrl);
        }

        logActivity($user['id'], 'exam_ds_upload', "ILC VP uploaded date sheet: \"$title\" ($year)");
        setFlash('success', "Date sheet \"$title\" uploaded and published to the ILC wing.");
        redirect($selfUrl);
    }

    if ($action === 'replace_file') {
        $dsId = (int)($_POST['ds_id'] ?? 0);
        $chk  = $db->prepare('SELECT stored_filename FROM exam_date_sheets WHERE id=? AND wing=?');
        $chk->execute([$dsId, $WING]);
        $row  = $chk->fetch();
        if (!$row) { setFlash('danger', 'Not found.'); redirect($selfUrl); }
        if (empty($_FILES['document']['name'])) { setFlash('danger', 'Please select a file.'); redirect($selfUrl); }
        $file     = $_FILES['document'];
        if ((int)$file['error'] !== UPLOAD_ERR_OK) { setFlash('danger', 'Upload failed (code ' . (int)$file['error'] . ').'); redirect($selfUrl); }
        $origName = basename($file['name']);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedTypes, true)) { setFlash('danger', 'Invalid file type.'); redirect($selfUrl); }
        if ($file['size'] > $maxSize) { setFlash('danger', 'File too large.'); redirect($selfUrl); }
        if ($row['stored_filename']) { $p = $uploadDir . $row['stored_filename']; if (file_exists($p)) @unlink($p); }
        $stored = 'ds_' . uniqid('', true) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $stored)) { setFlash('danger', 'Failed to save.'); redirect($selfUrl); }
        try {
            $db->prepare('UPDATE exam_date_sheets SET original_filename=?,stored_filename=?,file_type=?,file_size=? WHERE id=? AND wing=?')
               ->execute([$origName, $stored, $ext, (int)$file['size'], $dsId, $WING]);
            setFlash('success', 'File replaced.');
        } catch (\PDOException $e) {
            @unlink($uploadDir . $stored);
            setFlash('danger', 'Could not replace file: ' . $e->getMessage());
        }
        redirect($selfUrl);
    }

    if ($action === 'toggle_status') {
        $dsId = (int)($_POST['ds_id'] ?? 0);
        $cur  = $db->prepare('SELECT status FROM exam_date_sheets WHERE id=? AND wing=?');
        $cur->execute([$dsId, $WING]);
        $row  = $cur->fetch();
        if ($row) {
            $new = ($row['status'] === 'published') ? 'draft' : 'published';
            $db->prepare('UPDATE exam_date_sheets SET status=? WHERE id=? AND wing=?')->execute([$new, $dsId, $WING]);
            setFlash('success', $new === 'published' ? 'Published.' : 'Unpublished.');
        }
        redirect($selfUrl);
    }

    if ($action === 'delete') {
        $dsId = (int)($_POST['ds_id'] ?? 0);
        $cur  = $db->prepare('SELECT title, stored_filename FROM exam_date_sheets WHERE id=? AND wing=?');
        $cur->execute([$dsId, $WING]);
        $row  = $cur->fetch();
        if ($row) {
            if ($row['stored_filename']) { $p = $uploadDir . $row['stored_filename']; if (file_exists($p)) @unlink($p); }
            $db->prepare('DELETE FROM exam_date_sheets WHERE id=? AND wing=?')->execute([$dsId, $WING]);
            setFlash('success', 'Deleted.');
        }
        redirect($selfUrl);
    }
    redirect($selfUrl);
}

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

pageHead('Exam Date Sheets', 'ilc_vp');
$links = getIlcLinks();
?>
<div class="portal-wrap">
<?php sidebar('ilc_vp','exam-datesheet',$links,$user); ?>
<div class="main-area">
<?php topbar('Exam Date Sheets — ILC',$user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-upload me-2"></i>Upload ILC Date Sheet</div>
  <div style="padding:18px">
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label fw-semibold" style="font-size:.82rem">Title *</label>
          <input type="text" name="title" class="form-control form-control-sm" required placeholder="e.g. ILC Mid-Term 2025-2026">
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
          <span class="ms-2 text-muted" style="font-size:.78rem">
            <i class="fas fa-shield-alt me-1"></i>Auto-tagged as ILC-wing only.
          </span>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-calendar-day me-2"></i>ILC Date Sheets <span class="fw-normal opacity-75 ms-1" style="font-size:.8rem">(<?= count($sheets) ?>)</span></div>
  <?php if (empty($sheets)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2);font-size:.88rem"><i class="fas fa-calendar-day fa-2x mb-3" style="opacity:.3"></i><p class="mb-0">No ILC date sheets yet.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.83rem">
      <thead class="table-light"><tr><th>Title</th><th>Term</th><th>Year</th><th>Status</th><th>File</th><th>By</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($sheets as $ds):
          $icon    = $iconMap[$ds['file_type'] ?? ''] ?? 'fa-file text-secondary';
          $sizeFmt = $ds['file_size'] ? (($ds['file_size'] > 1048576) ? round($ds['file_size']/1048576,1).' MB' : round($ds['file_size']/1024).' KB') : '';
        ?>
        <tr>
          <td class="fw-semibold"><?= h($ds['title']) ?><?php if($ds['notes']):?><div style="font-size:.73rem;color:#9ca3af"><?= h($ds['notes']) ?></div><?php endif;?></td>
          <td style="font-size:.79rem"><?= h($ds['term'] ?? 'General') ?></td>
          <td><?= h($ds['academic_year']) ?></td>
          <td><?= $ds['status']==='published'?'<span class="badge bg-success">Published</span>':'<span class="badge bg-warning text-dark">Draft</span>' ?></td>
          <td><?php if($ds['stored_filename']):?><i class="fas <?= $icon ?> me-1"></i><span style="font-size:.76rem"><?= strtoupper($ds['file_type']??'') ?> &middot; <?= $sizeFmt ?></span><?php else:?><span class="text-muted" style="font-size:.76rem">No file</span><?php endif;?></td>
          <td style="font-size:.78rem"><?= h($ds['creator_name'] ?? '—') ?></td>
          <td style="font-size:.74rem;color:#6b7280;white-space:nowrap"><?= fDate($ds['created_at']) ?></td>
          <td>
            <div class="d-flex flex-wrap gap-1">
              <?php if($ds['stored_filename']):
                $isPdf  = ($ds['file_type'] ?? '') === 'pdf';
                $dsBase = url('/portal/api/serve-document.php') . '?type=datesheet&id=' . (int)$ds['id'];
              ?>
              <a href="<?= $dsBase . ($isPdf ? '&inline=1' : '') ?>" target="_blank" class="btn btn-xs btn-outline-info" style="font-size:.72rem;padding:2px 7px" title="View"><i class="fas fa-eye me-1"></i>View</a>
              <a href="<?= $dsBase ?>" class="btn btn-xs btn-outline-primary" style="font-size:.72rem;padding:2px 7px" title="Download"><i class="fas fa-download"></i></a>
              <?php endif;?>
              <button class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:2px 7px" onclick="openReplace(<?= $ds['id'] ?>,<?= htmlspecialchars(json_encode($ds['title'])) ?>)" title="Replace file"><i class="fas fa-sync"></i></button>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="ds_id" value="<?= $ds['id'] ?>">
                <button class="btn btn-xs <?= $ds['status']==='published'?'btn-outline-warning':'btn-outline-success'?>" style="font-size:.72rem;padding:2px 7px" title="<?= $ds['status']==='published'?'Unpublish':'Publish' ?>"><i class="fas <?= $ds['status']==='published'?'fa-eye-slash':'fa-globe'?>"></i></button>
              </form>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete permanently?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="ds_id" value="<?= $ds['id'] ?>">
                <button class="btn btn-xs btn-outline-danger" style="font-size:.72rem;padding:2px 7px"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <?php endif;?>
</div>

<div class="modal fade" id="replaceModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h6 class="modal-title"><i class="fas fa-sync me-2"></i>Replace File</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="replace_file"><input type="hidden" name="ds_id" id="replaceId">
      <div class="modal-body"><p class="text-muted mb-2" id="replaceTitle" style="font-size:.88rem"></p>
        <input type="file" name="document" class="form-control form-control-sm" required accept=".pdf,.xlsx,.xls,.doc,.docx"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-sync me-1"></i>Replace</button>
      </div>
    </form>
  </div></div>
</div>

<div class="sec-card mt-3" style="border:1px solid #bae6fd;background:#f0f9ff">
  <div style="padding:12px 14px;font-size:.82rem;color:#0c4a6e">
    <i class="fas fa-shield-alt me-1"></i>
    <strong>Wing isolation.</strong> Only ILC date sheets are shown and
    managed here. Main Campus and Montessori date sheets are posted from
    their own wings and never appear in this list. ILC date sheets are
    only visible to ILC students and ILC staff.
  </div>
</div>

</div></div></div>
<?php pageFooter(); ?>
<script>function openReplace(id,t){document.getElementById('replaceId').value=id;document.getElementById('replaceTitle').textContent='Replacing: '+t;new bootstrap.Modal(document.getElementById('replaceModal')).show();}</script>

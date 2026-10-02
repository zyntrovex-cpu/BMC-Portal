<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user        = requireAuth('vp_main');
$db          = getDB();
$yearNow     = (int)date('Y');
$managerWing = 'main';

$termOptions  = ['Mid-Term', 'Final-Term', 'Unit Test 1', 'Unit Test 2', 'Annual', 'Mock Exam', 'General'];
$allowedTypes = ['pdf', 'xlsx', 'xls', 'doc', 'docx'];
$maxSize      = 10 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_ds') {
        $title = trim($_POST['title'] ?? '');
        $term  = trim($_POST['term']  ?? 'General');
        $year  = trim($_POST['academic_year'] ?? "$yearNow-" . ($yearNow + 1));
        $notes = trim($_POST['notes'] ?? '');
        if (!in_array($term, $termOptions, true)) $term = 'General';
        if (!$title) { setFlash('danger', 'Title is required.'); redirect('/portal/vp/exam-datesheet.php'); }

        $storedFilename = $originalFilename = $fileType = $fileSize = null;
        if (!empty($_FILES['document']['name'])) {
            $file     = $_FILES['document'];
            $origName = basename($file['name']);
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedTypes, true)) { setFlash('danger', 'Invalid file type.'); redirect('/portal/vp/exam-datesheet.php'); }
            if ($file['size'] > $maxSize) { setFlash('danger', 'File too large. Maximum 10 MB.'); redirect('/portal/vp/exam-datesheet.php'); }
            if ($file['error'] !== UPLOAD_ERR_OK) { setFlash('danger', 'Upload failed.'); redirect('/portal/vp/exam-datesheet.php'); }
            $storedFilename   = 'ds_' . uniqid('', true) . '.' . $ext;
            $dest             = __DIR__ . '/../../uploads/documents/' . $storedFilename;
            if (!move_uploaded_file($file['tmp_name'], $dest)) { setFlash('danger', 'Failed to save file.'); redirect('/portal/vp/exam-datesheet.php'); }
            $originalFilename = $origName; $fileType = $ext; $fileSize = $file['size'];
        }

        try {
            $db->prepare(
                'INSERT INTO exam_date_sheets (title,term,wing,academic_year,notes,original_filename,stored_filename,file_type,file_size,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([$title, $term, $managerWing, $year, $notes, $originalFilename, $storedFilename, $fileType, $fileSize, $user['id']]);
        } catch (\PDOException $e) {
            $db->prepare('INSERT INTO exam_date_sheets (title,term,wing,academic_year,notes,created_by) VALUES (?,?,?,?,?,?)')
               ->execute([$title, $term, $managerWing, $year, $notes, $user['id']]);
        }
        $newId = (int)$db->lastInsertId();
        logActivity($user['id'], 'exam_ds_create', "VP created: \"$title\" (main, $year)");
        setFlash('success', "Date sheet \"$title\" created.");
        redirect('/portal/vp/exam-datesheet.php?ds=' . $newId);
    }

    if ($action === 'update_ds') {
        $dsId  = (int)($_POST['ds_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $term  = trim($_POST['term']  ?? 'General');
        $year  = trim($_POST['academic_year'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if (!in_array($term, $termOptions, true)) $term = 'General';
        $chk   = $db->prepare('SELECT id FROM exam_date_sheets WHERE id=? AND wing=?');
        $chk->execute([$dsId, $managerWing]);
        if ($dsId && $title && $chk->fetch()) {
            try {
                $db->prepare('UPDATE exam_date_sheets SET title=?,term=?,academic_year=?,notes=? WHERE id=?')
                   ->execute([$title, $term, $year, $notes, $dsId]);
            } catch (\PDOException $e) {
                $db->prepare('UPDATE exam_date_sheets SET title=?,academic_year=?,notes=? WHERE id=?')
                   ->execute([$title, $year, $notes, $dsId]);
            }
            setFlash('success', 'Date sheet updated.');
        }
        redirect('/portal/vp/exam-datesheet.php?ds=' . $dsId);
    }

    if ($action === 'upload_file') {
        $dsId = (int)($_POST['ds_id'] ?? 0);
        $chk  = $db->prepare('SELECT id, stored_filename FROM exam_date_sheets WHERE id=? AND wing=?');
        $chk->execute([$dsId, $managerWing]);
        $row  = $chk->fetch();
        if (!$row) { setFlash('danger', 'Access denied.'); redirect('/portal/vp/exam-datesheet.php'); }

        if (empty($_FILES['document']['name'])) { setFlash('danger', 'Please select a file.'); redirect('/portal/vp/exam-datesheet.php?ds=' . $dsId); }
        $file     = $_FILES['document'];
        $origName = basename($file['name']);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedTypes, true)) { setFlash('danger', 'Invalid file type.'); redirect('/portal/vp/exam-datesheet.php?ds=' . $dsId); }
        if ($file['size'] > $maxSize) { setFlash('danger', 'File too large.'); redirect('/portal/vp/exam-datesheet.php?ds=' . $dsId); }
        if ($file['error'] !== UPLOAD_ERR_OK) { setFlash('danger', 'Upload failed.'); redirect('/portal/vp/exam-datesheet.php?ds=' . $dsId); }

        if ($row['stored_filename']) { $p = __DIR__ . '/../../uploads/documents/' . $row['stored_filename']; if (file_exists($p)) @unlink($p); }

        $storedName = 'ds_' . uniqid('', true) . '.' . $ext;
        $dest       = __DIR__ . '/../../uploads/documents/' . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $dest)) { setFlash('danger', 'Failed to save file.'); redirect('/portal/vp/exam-datesheet.php?ds=' . $dsId); }

        try {
            $db->prepare('UPDATE exam_date_sheets SET original_filename=?,stored_filename=?,file_type=?,file_size=? WHERE id=?')
               ->execute([$origName, $storedName, $ext, $file['size'], $dsId]);
        } catch (\PDOException $e) {}

        logActivity($user['id'], 'exam_ds_file_upload', "VP uploaded file for date sheet #$dsId");
        setFlash('success', 'Document uploaded successfully.');
        redirect('/portal/vp/exam-datesheet.php?ds=' . $dsId);
    }

    if ($action === 'remove_file') {
        $dsId = (int)($_POST['ds_id'] ?? 0);
        $r    = $db->prepare('SELECT stored_filename, wing FROM exam_date_sheets WHERE id=?');
        $r->execute([$dsId]);
        $row  = $r->fetch();
        if ($row && $row['wing'] === $managerWing && $row['stored_filename']) {
            $p = __DIR__ . '/../../uploads/documents/' . $row['stored_filename'];
            if (file_exists($p)) @unlink($p);
            try {
                $db->prepare('UPDATE exam_date_sheets SET original_filename=NULL,stored_filename=NULL,file_type=NULL,file_size=NULL WHERE id=?')
                   ->execute([$dsId]);
            } catch (\PDOException $e) {}
            setFlash('success', 'Document removed.');
        }
        redirect('/portal/vp/exam-datesheet.php?ds=' . $dsId);
    }

    if ($action === 'toggle_status') {
        $dsId = (int)($_POST['ds_id'] ?? 0);
        $chk  = $db->prepare('SELECT status, title FROM exam_date_sheets WHERE id=? AND wing=?');
        $chk->execute([$dsId, $managerWing]);
        $cur  = $chk->fetch();
        if ($dsId && $cur) {
            $newStatus = ($cur['status'] === 'published') ? 'draft' : 'published';
            $db->prepare('UPDATE exam_date_sheets SET status=? WHERE id=?')->execute([$newStatus, $dsId]);
            setFlash('success', $newStatus === 'published' ? 'Date sheet published.' : 'Reverted to draft.');
        }
        redirect('/portal/vp/exam-datesheet.php?ds=' . $dsId);
    }

    if ($action === 'delete_ds') {
        $dsId = (int)($_POST['ds_id'] ?? 0);
        $chk  = $db->prepare('SELECT title, stored_filename, wing FROM exam_date_sheets WHERE id=?');
        $chk->execute([$dsId]);
        $cur  = $chk->fetch();
        if ($dsId && $cur && $cur['wing'] === $managerWing) {
            if ($cur['stored_filename']) { $p = __DIR__ . '/../../uploads/documents/' . $cur['stored_filename']; if (file_exists($p)) @unlink($p); }
            $db->prepare('DELETE FROM exam_date_sheets WHERE id=?')->execute([$dsId]);
            logActivity($user['id'], 'exam_ds_delete', "VP deleted: \"" . $cur['title'] . '"');
            setFlash('success', 'Date sheet deleted.');
        }
        redirect('/portal/vp/exam-datesheet.php');
    }

    redirect('/portal/vp/exam-datesheet.php');
}

$dsId = (int)($_GET['ds'] ?? 0);
$ds   = null;
if ($dsId) {
    $r = $db->prepare('SELECT ds.*, u.name AS creator_name FROM exam_date_sheets ds JOIN users u ON ds.created_by=u.id WHERE ds.id=? AND ds.wing=?');
    $r->execute([$dsId, $managerWing]);
    $ds = $r->fetch();
    if (!$ds) { setFlash('danger', 'Date sheet not found.'); redirect('/portal/vp/exam-datesheet.php'); }
}

$listSt = $db->prepare(
    "SELECT ds.*, u.name AS creator_name
     FROM exam_date_sheets ds JOIN users u ON ds.created_by=u.id
     WHERE ds.wing=? ORDER BY ds.academic_year DESC, ds.created_at DESC"
);
$listSt->execute([$managerWing]);
$dateSheets = $listSt->fetchAll();

if (($isPrint = (bool)($_GET['print'] ?? 0)) && $dsId && $ds && $ds['stored_filename']) {
    $filePath = __DIR__ . '/../../uploads/documents/' . $ds['stored_filename'];
    if (file_exists($filePath)) {
        $ext = strtolower(pathinfo($ds['stored_filename'], PATHINFO_EXTENSION));
        $mimeMap = ['pdf'=>'application/pdf','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'xls'=>'application/vnd.ms-excel','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','doc'=>'application/msword'];
        header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . addslashes($ds['original_filename']) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

pageHead('Exam Date Sheets', 'vp_main');
$links = getVpLinks();
?>
<div class="portal-wrap">
<?php sidebar('vp_main', 'exam-datesheet', $links, $user); ?>
<div class="main-area">
<?php topbar('Exam Date Sheets — Main Wing', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if ($dsId && $ds): ?>

<div class="d-flex align-items-center mb-3 gap-2 flex-wrap">
  <a href="/portal/vp/exam-datesheet.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i>All Date Sheets
  </a>
  <span class="text-muted" style="font-size:.84rem">&rsaquo; <?= h($ds['title']) ?></span>
  <?= $ds['status'] === 'published'
    ? '<span class="badge bg-success ms-1">Published</span>'
    : '<span class="badge bg-warning text-dark ms-1">Draft</span>' ?>
  <?php if ($ds['stored_filename']): ?>
  <a href="?ds=<?= $dsId ?>&print=1" target="_blank" class="btn btn-sm btn-outline-secondary ms-auto">
    <i class="fas fa-eye me-1"></i>View / Download
  </a>
  <?php endif; ?>
</div>

<?php if ($ds['status'] === 'draft'): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3" style="font-size:.85rem">
  <i class="fas fa-eye-slash"></i>
  <span>This date sheet is in <strong>Draft</strong> status — not visible to students or staff.</span>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-info-circle me-2"></i>Date Sheet Info</div>
      <div style="padding:16px">
        <form method="POST">
          <input type="hidden" name="action" value="update_ds">
          <input type="hidden" name="ds_id" value="<?= $ds['id'] ?>">
          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Title *</label>
            <input type="text" name="title" class="form-control form-control-sm" value="<?= h($ds['title']) ?>" required>
          </div>
          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Term</label>
            <select name="term" class="form-select form-select-sm">
              <?php foreach ($termOptions as $t): ?>
              <option value="<?= h($t) ?>" <?= ($ds['term'] ?? 'General') === $t ? 'selected' : '' ?>><?= h($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Campus / Wing</label>
            <input type="text" class="form-control form-control-sm" value="Main Wing" readonly>
          </div>
          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Academic Year</label>
            <input type="text" name="academic_year" class="form-control form-control-sm"
                   value="<?= h($ds['academic_year']) ?>" placeholder="e.g. 2025-2026">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Notes</label>
            <textarea name="notes" class="form-control form-control-sm" rows="2"><?= h($ds['notes'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-save me-1"></i>Save Info</button>
        </form>
        <hr>
        <form method="POST" onsubmit="return confirm('Change publish status?')">
          <input type="hidden" name="action" value="toggle_status">
          <input type="hidden" name="ds_id" value="<?= $ds['id'] ?>">
          <?php if ($ds['status'] === 'published'): ?>
          <button type="submit" class="btn btn-sm btn-outline-warning w-100"><i class="fas fa-eye-slash me-1"></i>Revert to Draft</button>
          <?php else: ?>
          <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-globe me-1"></i>Publish Date Sheet</button>
          <?php endif; ?>
        </form>
        <p class="mt-2 text-muted" style="font-size:.75rem">Created by <?= h($ds['creator_name']) ?> — <?= fDate($ds['created_at']) ?></p>
        <hr>
        <form method="POST" onsubmit="return confirm('Delete this date sheet?')">
          <input type="hidden" name="action" value="delete_ds">
          <input type="hidden" name="ds_id" value="<?= $ds['id'] ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-trash me-1"></i>Delete Date Sheet</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-file-upload me-2"></i>Exam Schedule Document</div>
      <div style="padding:18px">
        <?php if ($ds['stored_filename']): ?>
        <div class="d-flex align-items-center gap-3 p-3 mb-3" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px">
          <?php
            $iconMap = ['pdf' => 'fa-file-pdf text-danger', 'xlsx' => 'fa-file-excel text-success', 'xls' => 'fa-file-excel text-success', 'doc' => 'fa-file-word text-primary', 'docx' => 'fa-file-word text-primary'];
            $icon    = $iconMap[$ds['file_type'] ?? ''] ?? 'fa-file text-secondary';
            $sizeFmt = ($ds['file_size'] ?? 0) > 1048576 ? round(($ds['file_size'] ?? 0)/1048576,1).' MB' : round(($ds['file_size'] ?? 0)/1024).' KB';
          ?>
          <i class="fas <?= $icon ?> fa-2x"></i>
          <div class="flex-grow-1">
            <div class="fw-semibold" style="font-size:.88rem"><?= h($ds['original_filename']) ?></div>
            <div style="font-size:.78rem;color:#6b7280"><?= strtoupper($ds['file_type'] ?? '') ?> &middot; <?= $sizeFmt ?></div>
          </div>
          <div class="d-flex gap-2">
            <a href="/portal/api/serve-document.php?type=datesheet&id=<?= $dsId ?>" class="btn btn-sm btn-success">
              <i class="fas fa-download me-1"></i>Download
            </a>
            <form method="POST" style="display:inline" onsubmit="return confirm('Remove this document?')">
              <input type="hidden" name="action" value="remove_file">
              <input type="hidden" name="ds_id" value="<?= $dsId ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="fas fa-times me-1"></i>Remove</button>
            </form>
          </div>
        </div>
        <p class="text-muted mb-3" style="font-size:.83rem"><i class="fas fa-sync me-1"></i>Upload a new file below to replace the current document.</p>
        <?php else: ?>
        <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-3" style="font-size:.85rem">
          <i class="fas fa-info-circle"></i>
          <span>No document uploaded yet. Upload an Excel, PDF, or Word file containing the exam schedule.</span>
        </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload_file">
          <input type="hidden" name="ds_id" value="<?= $dsId ?>">
          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">
              <?= $ds['stored_filename'] ? 'Replace Document' : 'Upload Document' ?> *
              <span class="text-muted fw-normal">(PDF, Excel, Word — max 10 MB)</span>
            </label>
            <input type="file" name="document" class="form-control form-control-sm" required
                   accept=".pdf,.xlsx,.xls,.doc,.docx">
          </div>
          <button type="submit" class="btn btn-sm btn-primary">
            <i class="fas fa-upload me-1"></i><?= $ds['stored_filename'] ? 'Replace File' : 'Upload File' ?>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php else: ?>

<div class="d-flex justify-content-end mb-3">
  <button class="btn btn-sm btn-success" data-bs-toggle="collapse" data-bs-target="#createForm">
    <i class="fas fa-plus me-1"></i>Create New Date Sheet
  </button>
</div>

<div class="collapse" id="createForm">
  <div class="sec-card mb-3">
    <div class="sec-card-header"><i class="fas fa-plus me-2"></i>New Exam Date Sheet — Main Wing</div>
    <div style="padding:16px">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="create_ds">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Title *</label>
            <input type="text" name="title" class="form-control form-control-sm" required placeholder="e.g. Mid-Term Exams 2025-2026">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Term</label>
            <select name="term" class="form-select form-select-sm">
              <?php foreach($termOptions as $t):?><option value="<?=h($t)?>"><?=h($t)?></option><?php endforeach;?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Academic Year</label>
            <input type="text" name="academic_year" class="form-control form-control-sm" value="<?=$yearNow?>-<?=$yearNow+1?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">File (optional)</label>
            <input type="file" name="document" class="form-control form-control-sm" accept=".pdf,.xlsx,.xls,.doc,.docx">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Notes</label>
            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional">
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i>Create</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-calendar-day me-2"></i>Main Wing Exam Date Sheets (<?=count($dateSheets)?>)</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr><th>Title</th><th>Term</th><th>Year</th><th>Status</th><th>Document</th><th>Created By</th><th>Created</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach($dateSheets as $row):?>
        <tr>
          <td class="fw-semibold"><?=h($row['title'])?><?php if($row['notes']):?><div style="font-size:.75rem;color:#9ca3af"><?=h($row['notes'])?></div><?php endif;?></td>
          <td style="font-size:.8rem"><?=h($row['term']??'General')?></td>
          <td><?=h($row['academic_year'])?></td>
          <td><?=$row['status']==='published'?'<span class="badge bg-success">Published</span>':'<span class="badge bg-warning text-dark">Draft</span>'?></td>
          <td>
            <?php if($row['stored_filename']):?>
            <a href="/portal/api/serve-document.php?type=datesheet&id=<?=$row['id']?>"
               class="btn btn-xs btn-outline-success" style="font-size:.72rem;padding:2px 7px">
              <i class="fas fa-download me-1"></i><?=strtoupper($row['file_type']??'')?>
            </a>
            <?php else:?><span class="text-muted" style="font-size:.78rem">No file</span><?php endif;?>
          </td>
          <td style="font-size:.8rem"><?=h($row['creator_name'])?></td>
          <td style="font-size:.78rem;color:#6b7280"><?=fDate($row['created_at'])?></td>
          <td style="white-space:nowrap">
            <a href="/portal/vp/exam-datesheet.php?ds=<?=$row['id']?>" class="btn btn-xs btn-primary me-1" style="font-size:.72rem;padding:2px 7px"><i class="fas fa-edit"></i> Manage</a>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this date sheet?')">
              <input type="hidden" name="action" value="delete_ds">
              <input type="hidden" name="ds_id" value="<?=$row['id']?>">
              <button class="btn btn-xs btn-outline-danger" style="font-size:.72rem;padding:2px 7px"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; if(empty($dateSheets)):?>
        <tr><td colspan="8" class="text-center text-muted py-4">No date sheets yet for Main Wing.</td></tr>
        <?php endif;?>
      </tbody>
    </table>
  </div>
</div>

<?php endif;?>
</div></div></div>
<?php pageFooter(); ?>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('examination_head');
requirePermission('eh_timetable');
$db      = getDB();
$yearNow = (int)date('Y');
$managerWing  = 'main';
$selfUrl = '/portal/exam-head/timetable.php';

$allowedTypes = ['pdf', 'xlsx', 'xls', 'doc', 'docx'];
$maxSize      = 10 * 1024 * 1024;
$uploadDir    = __DIR__ . '/../../uploads/documents/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $title = trim($_POST['title'] ?? '');
        $year  = trim($_POST['academic_year'] ?? "$yearNow-" . ($yearNow + 1));
        $notes = trim($_POST['notes'] ?? '');

        if (!$title) { setFlash('danger', 'Title is required.'); redirect($selfUrl); }
        if (empty($_FILES['document']['name'])) { setFlash('danger', 'Please select a file.'); redirect($selfUrl); }

        $file     = $_FILES['document'];
        $origName = basename($file['name']);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedTypes, true)) { setFlash('danger', 'Invalid file type. Allowed: PDF, Excel (.xlsx/.xls), Word (.doc/.docx).'); redirect($selfUrl); }
        if ($file['size'] > $maxSize) { setFlash('danger', 'File too large. Maximum 10 MB.'); redirect($selfUrl); }
        if ($file['error'] !== UPLOAD_ERR_OK) { setFlash('danger', 'Upload failed.'); redirect($selfUrl); }

        $storedName = 'tt_' . uniqid('', true) . '.' . $ext;
        $dest       = $uploadDir . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $dest)) { setFlash('danger', 'Failed to save file.'); redirect($selfUrl); }

        try {
            $db->prepare(
                'INSERT INTO timetable_documents (title, wing, academic_year, notes, original_filename, stored_filename, file_type, file_size, uploaded_by) VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([$title, $managerWing, $year, $notes, $origName, $storedName, $ext, $file['size'], $user['id']]);
        } catch (\PDOException $e) {
            @unlink($dest);
            setFlash('danger', 'Database error: ' . $e->getMessage());
            redirect($selfUrl);
        }

        // Notify main-wing students and teachers
        try {
            $stIds = $db->query(
                "SELECT u.id FROM users u
                 JOIN students s ON s.user_id=u.id
                 JOIN classes c ON c.id=s.class_id
                 WHERE COALESCE(c.is_ilc,0)=0 AND COALESCE(c.is_montessori,0)=0
                   AND s.deleted_at IS NULL AND u.status='active'"
            )->fetchAll(PDO::FETCH_COLUMN);
            foreach ($stIds as $uid) {
                createNotification((int)$uid, 'timetable_upload', "New timetable uploaded: $title");
            }
        } catch (Exception $e) {}

        logActivity($user['id'], 'timetable_doc_upload', "Exam Head uploaded timetable: \"$title\" (main, $year)");
        setFlash('success', "Timetable document \"$title\" uploaded successfully.");
        redirect($selfUrl);
    }

    if ($action === 'delete') {
        $docId = (int)($_POST['doc_id'] ?? 0);
        $r     = $db->prepare('SELECT stored_filename, title, wing FROM timetable_documents WHERE id=?');
        $r->execute([$docId]);
        $row   = $r->fetch();
        if ($row && $row['wing'] === $managerWing) {
            $p = $uploadDir . $row['stored_filename'];
            if (file_exists($p)) @unlink($p);
            $db->prepare('DELETE FROM timetable_documents WHERE id=?')->execute([$docId]);
            logActivity($user['id'], 'timetable_doc_delete', "Exam Head deleted timetable: \"" . $row['title'] . '"');
            setFlash('success', 'Document deleted.');
        }
        redirect($selfUrl);
    }

    if ($action === 'archive' || $action === 'restore') {
        $docId     = (int)($_POST['doc_id'] ?? 0);
        $newStatus = $action === 'archive' ? 'archived' : 'active';
        if ($docId) {
            $chk = $db->prepare('SELECT wing FROM timetable_documents WHERE id=?');
            $chk->execute([$docId]);
            $row = $chk->fetch();
            if (!$row || $row['wing'] !== $managerWing) { setFlash('danger', 'Access denied.'); redirect($selfUrl); }
            $db->prepare('UPDATE timetable_documents SET status=? WHERE id=?')->execute([$newStatus, $docId]);
            $label = $newStatus === 'archived' ? 'archived' : 'restored';
            logActivity($user['id'], 'timetable_doc_' . $action, "Exam Head " . ucfirst($action) . "d timetable doc #$docId");
            setFlash('success', "Document $label successfully.");
        }
        redirect($selfUrl);
    }

    if ($action === 'replace') {
        $docId = (int)($_POST['doc_id'] ?? 0);
        $chk   = $db->prepare('SELECT stored_filename, wing FROM timetable_documents WHERE id=?');
        $chk->execute([$docId]);
        $row   = $chk->fetch();
        if (!$row || $row['wing'] !== $managerWing) { setFlash('danger', 'Access denied.'); redirect($selfUrl); }

        if (empty($_FILES['document']['name'])) { setFlash('danger', 'Please select a replacement file.'); redirect($selfUrl); }
        $file     = $_FILES['document'];
        $origName = basename($file['name']);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedTypes, true)) { setFlash('danger', 'Invalid file type.'); redirect($selfUrl); }
        if ($file['size'] > $maxSize) { setFlash('danger', 'File too large.'); redirect($selfUrl); }
        if ($file['error'] !== UPLOAD_ERR_OK) { setFlash('danger', 'Upload failed.'); redirect($selfUrl); }

        $oldPath = $uploadDir . $row['stored_filename'];
        if (file_exists($oldPath)) @unlink($oldPath);

        $storedName = 'tt_' . uniqid('', true) . '.' . $ext;
        $dest       = $uploadDir . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $dest)) { setFlash('danger', 'Failed to save file.'); redirect($selfUrl); }

        $db->prepare('UPDATE timetable_documents SET original_filename=?, stored_filename=?, file_type=?, file_size=? WHERE id=?')
           ->execute([$origName, $storedName, $ext, $file['size'], $docId]);

        logActivity($user['id'], 'timetable_doc_replace', "Exam Head replaced timetable doc #$docId");
        setFlash('success', 'Document replaced successfully.');
        redirect($selfUrl);
    }

    redirect($selfUrl);
}

$docs = $db->prepare(
    "SELECT td.*, u.name AS uploader_name FROM timetable_documents td JOIN users u ON td.uploaded_by=u.id
     WHERE td.wing=? ORDER BY td.status ASC, td.created_at DESC"
);
$docs->execute([$managerWing]);
$docs = $docs->fetchAll();

pageHead('Timetable', 'examination_head');
$links = getExamHeadLinks();
?>
<div class="portal-wrap">
<?php sidebar('examination_head', 'timetable', $links, $user); ?>
<div class="main-area">
<?php topbar('Timetable — Main Campus', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-upload me-2"></i>Upload Timetable Document</div>
  <div style="padding:18px">
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label fw-semibold" style="font-size:.82rem">Title *</label>
          <input type="text" name="title" class="form-control form-control-sm" required placeholder="e.g. Main Campus Timetable 2025-2026">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Academic Year</label>
          <input type="text" name="academic_year" class="form-control form-control-sm" value="<?= $yearNow ?>-<?= $yearNow + 1 ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold" style="font-size:.82rem">File * <span class="text-muted fw-normal">(PDF, Excel, Word — max 10 MB)</span></label>
          <input type="file" name="document" class="form-control form-control-sm" required accept=".pdf,.xlsx,.xls,.doc,.docx">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Notes</label>
          <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-upload me-1"></i>Upload Document</button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="sec-card">
  <div class="sec-card-header">
    <i class="fas fa-file-alt me-2"></i>Main Campus Timetable Documents
    <span class="text-muted fw-normal">(<?= count($docs) ?>)</span>
  </div>
  <?php if (empty($docs)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2);font-size:.88rem">
    <i class="fas fa-folder-open fa-2x mb-3" style="opacity:.3"></i>
    <p class="mb-0">No timetable documents uploaded yet.</p>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light"><tr><th>Title</th><th>Year</th><th>File</th><th>Size</th><th>Uploaded By</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($docs as $doc):
          $iconMap = ['pdf'=>'fa-file-pdf','xlsx'=>'fa-file-excel','xls'=>'fa-file-excel','doc'=>'fa-file-word','docx'=>'fa-file-word'];
          $icon    = $iconMap[$doc['file_type']] ?? 'fa-file';
          $sizeFmt = $doc['file_size'] > 1048576 ? round($doc['file_size']/1048576,1).' MB' : round($doc['file_size']/1024).' KB';
        ?>
        <tr>
          <td class="fw-semibold"><?=h($doc['title'])?><?php if($doc['notes']):?><div style="font-size:.75rem;color:#9ca3af"><?=h($doc['notes'])?></div><?php endif;?></td>
          <td><?=h($doc['academic_year'])?></td>
          <td><i class="fas <?=$icon?> me-1" style="color:#2563eb"></i><span style="font-size:.8rem"><?=h($doc['original_filename'])?></span></td>
          <td style="font-size:.8rem"><?=$sizeFmt?></td>
          <td style="font-size:.8rem"><?=h($doc['uploader_name'])?></td>
          <td style="font-size:.78rem;color:#6b7280"><?=fDate($doc['created_at'])?></td>
          <td style="font-size:.8rem">
            <?php if(($doc['status']??'active')==='archived'):?>
            <span class="badge bg-secondary">Archived</span>
            <?php else:?>
            <span class="badge bg-success">Active</span>
            <?php endif;?>
          </td>
          <td style="white-space:nowrap">
            <?php $isPdf=($doc['file_type']??'')==='pdf';?>
            <a href="<?=url('/portal/api/serve-document.php')?>?type=timetable&id=<?=$doc['id']?><?=$isPdf?'&inline=1':''?>" target="_blank" class="btn btn-xs btn-outline-info me-1" style="font-size:.72rem;padding:2px 7px" title="View"><i class="fas fa-eye"></i> View</a>
            <a href="<?=url('/portal/api/serve-document.php')?>?type=timetable&id=<?=$doc['id']?>" class="btn btn-xs btn-primary me-1" style="font-size:.72rem;padding:2px 7px"><i class="fas fa-download"></i> Download</a>
            <a href="<?=url('/portal/api/serve-document.php')?>?type=timetable&id=<?=$doc['id']?><?=$isPdf?'&inline=1':''?>" target="_blank" class="btn btn-xs btn-outline-secondary me-1" style="font-size:.72rem;padding:2px 7px" title="Print"><i class="fas fa-print"></i> Print</a>
            <?php if(($doc['status']??'active')==='active'):?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Archive this document? Students will no longer see it.')">
              <input type="hidden" name="action" value="archive">
              <input type="hidden" name="doc_id" value="<?=$doc['id']?>">
              <button class="btn btn-xs btn-outline-warning me-1" style="font-size:.72rem;padding:2px 7px" title="Archive"><i class="fas fa-archive"></i></button>
            </form>
            <?php else:?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="restore">
              <input type="hidden" name="doc_id" value="<?=$doc['id']?>">
              <button class="btn btn-xs btn-outline-success me-1" style="font-size:.72rem;padding:2px 7px" title="Restore"><i class="fas fa-undo"></i></button>
            </form>
            <?php endif;?>
            <button class="btn btn-xs btn-outline-secondary me-1" style="font-size:.72rem;padding:2px 7px" onclick="openReplace(<?=$doc['id']?>,<?=htmlspecialchars(json_encode($doc['title']))?>)"><i class="fas fa-sync"></i> Replace</button>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this document?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="doc_id" value="<?=$doc['id']?>">
              <button class="btn btn-xs btn-outline-danger" style="font-size:.72rem;padding:2px 7px"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="modal fade" id="replaceModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h6 class="modal-title"><i class="fas fa-sync me-2"></i>Replace Document</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="replace">
      <input type="hidden" name="doc_id" id="replaceDocId">
      <div class="modal-body">
        <p class="text-muted mb-3" id="replaceDocTitle" style="font-size:.88rem"></p>
        <label class="form-label fw-semibold" style="font-size:.84rem">New File * <span class="text-muted fw-normal">(PDF, Excel, Word — max 10 MB)</span></label>
        <input type="file" name="document" class="form-control form-control-sm" required accept=".pdf,.xlsx,.xls,.doc,.docx">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-sync me-1"></i>Replace File</button>
      </div>
    </form>
  </div></div>
</div>

</div></div></div>
<?php pageFooter(); ?>
<script>
function openReplace(id, title) {
    document.getElementById('replaceDocId').value = id;
    document.getElementById('replaceDocTitle').textContent = 'Replacing: ' + title;
    new bootstrap.Modal(document.getElementById('replaceModal')).show();
}
</script>

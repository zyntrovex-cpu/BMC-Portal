<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('examination_head');
requirePermission('eh_syllabus');
$db      = getDB();
$yearNow = (int)date('Y');
$selfUrl = '/portal/exam-head/syllabus.php';

$allowedTypes = ['pdf', 'xlsx', 'xls', 'doc', 'docx'];
$maxSize      = 10 * 1024 * 1024;

// Main-wing classes only
$classesSt = $db->query(
    "SELECT id, name, grade, section FROM classes
     WHERE COALESCE(is_ilc,0)=0 AND COALESCE(is_montessori,0)=0
     ORDER BY grade, section"
);
$classes = $classesSt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $title   = trim($_POST['title'] ?? '');
        $classId = (int)($_POST['class_id'] ?? 0) ?: null;
        $year    = trim($_POST['academic_year'] ?? "$yearNow-" . ($yearNow + 1));
        $notes   = trim($_POST['notes'] ?? '');

        if (!$title) { setFlash('danger', 'Title is required.'); redirect($selfUrl); }
        if (empty($_FILES['document']['name'])) { setFlash('danger', 'Please select a file.'); redirect($selfUrl); }

        $file     = $_FILES['document'];
        $origName = basename($file['name']);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedTypes, true)) { setFlash('danger', 'Invalid file type. Allowed: PDF, Excel (.xlsx/.xls), Word (.doc/.docx).'); redirect($selfUrl); }
        if ($file['size'] > $maxSize) { setFlash('danger', 'File too large. Maximum 10 MB.'); redirect($selfUrl); }
        if ($file['error'] !== UPLOAD_ERR_OK) { setFlash('danger', 'Upload failed.'); redirect($selfUrl); }

        $storedName = 'syl_' . uniqid('', true) . '.' . $ext;
        $dest       = __DIR__ . '/../../uploads/documents/' . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $dest)) { setFlash('danger', 'Failed to save file.'); redirect($selfUrl); }

        $db->prepare(
            'INSERT INTO syllabus_documents (title, class_id, academic_year, notes, original_filename, stored_filename, file_type, file_size, uploaded_by)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([$title, $classId, $year, $notes, $origName, $storedName, $ext, $file['size'], $user['id']]);

        // Notify main-wing students
        try {
            $query = "SELECT u.id FROM users u
                      JOIN students s ON s.user_id=u.id
                      JOIN classes c ON c.id=s.class_id
                      WHERE COALESCE(c.is_ilc,0)=0 AND COALESCE(c.is_montessori,0)=0
                        AND s.deleted_at IS NULL AND u.status='active'";
            $params = [];
            if ($classId) {
                $query  .= ' AND s.class_id=?';
                $params[] = $classId;
            }
            $stIds = $db->prepare($query);
            $stIds->execute($params);
            foreach ($stIds->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                createNotification((int)$uid, 'syllabus_upload', "New syllabus uploaded: $title");
            }
        } catch (Exception $e) {}

        logActivity($user['id'], 'syllabus_upload', "Exam Head uploaded syllabus: \"$title\" ($year)");
        setFlash('success', "Syllabus \"$title\" uploaded successfully.");
        redirect($selfUrl);
    }

    if ($action === 'delete') {
        $docId = (int)($_POST['doc_id'] ?? 0);
        $r     = $db->prepare('SELECT stored_filename, title FROM syllabus_documents WHERE id=?');
        $r->execute([$docId]);
        $row   = $r->fetch();
        if ($row) {
            $p = __DIR__ . '/../../uploads/documents/' . $row['stored_filename'];
            if (file_exists($p)) @unlink($p);
            $db->prepare('DELETE FROM syllabus_documents WHERE id=?')->execute([$docId]);
            logActivity($user['id'], 'syllabus_delete', "Exam Head deleted syllabus: \"" . $row['title'] . '"');
            setFlash('success', 'Syllabus deleted.');
        }
        redirect($selfUrl);
    }

    if ($action === 'replace') {
        $docId = (int)($_POST['doc_id'] ?? 0);
        $chk   = $db->prepare('SELECT stored_filename FROM syllabus_documents WHERE id=?');
        $chk->execute([$docId]);
        $row   = $chk->fetch();
        if (!$row) { setFlash('danger', 'Not found.'); redirect($selfUrl); }

        if (empty($_FILES['document']['name'])) { setFlash('danger', 'Please select a file.'); redirect($selfUrl); }
        $file     = $_FILES['document'];
        $origName = basename($file['name']);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedTypes, true)) { setFlash('danger', 'Invalid file type.'); redirect($selfUrl); }
        if ($file['size'] > $maxSize) { setFlash('danger', 'File too large.'); redirect($selfUrl); }
        if ($file['error'] !== UPLOAD_ERR_OK) { setFlash('danger', 'Upload failed.'); redirect($selfUrl); }

        $oldPath = __DIR__ . '/../../uploads/documents/' . $row['stored_filename'];
        if (file_exists($oldPath)) @unlink($oldPath);

        $storedName = 'syl_' . uniqid('', true) . '.' . $ext;
        $dest       = __DIR__ . '/../../uploads/documents/' . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $dest)) { setFlash('danger', 'Failed to save file.'); redirect($selfUrl); }

        $db->prepare('UPDATE syllabus_documents SET original_filename=?, stored_filename=?, file_type=?, file_size=? WHERE id=?')
           ->execute([$origName, $storedName, $ext, $file['size'], $docId]);

        logActivity($user['id'], 'syllabus_replace', "Exam Head replaced syllabus doc #$docId");
        setFlash('success', 'Syllabus replaced successfully.');
        redirect($selfUrl);
    }

    redirect($selfUrl);
}

$docs = [];
try {
    $st = $db->prepare(
        "SELECT sd.*, c.name AS class_name, u.name AS uploader_name
         FROM syllabus_documents sd
         LEFT JOIN classes c ON c.id = sd.class_id
         LEFT JOIN users u ON u.id = sd.uploaded_by
         ORDER BY sd.created_at DESC"
    );
    $st->execute();
    $docs = $st->fetchAll();
} catch (Exception $e) {}

pageHead('Syllabus', 'examination_head');
$links = getExamHeadLinks();
?>
<div class="portal-wrap">
<?php sidebar('examination_head', 'syllabus', $links, $user); ?>
<div class="main-area">
<?php topbar('Syllabus Management', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-upload me-2"></i>Upload Syllabus Document</div>
  <div style="padding:18px">
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label fw-semibold" style="font-size:.82rem">Title *</label>
          <input type="text" name="title" class="form-control form-control-sm" required placeholder="e.g. Class 9 Biology Syllabus 2025-2026">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Class (optional)</label>
          <select name="class_id" class="form-select form-select-sm">
            <option value="">All Classes</option>
            <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Academic Year</label>
          <input type="text" name="academic_year" class="form-control form-control-sm" value="<?= $yearNow ?>-<?= $yearNow + 1 ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold" style="font-size:.82rem">File * <span class="text-muted fw-normal">(PDF, Excel, Word — max 10 MB)</span></label>
          <input type="file" name="document" class="form-control form-control-sm" required accept=".pdf,.xlsx,.xls,.doc,.docx">
        </div>
        <div class="col-md-1">
          <label class="form-label fw-semibold" style="font-size:.82rem">Notes</label>
          <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-upload me-1"></i>Upload Syllabus</button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="sec-card">
  <div class="sec-card-header">
    <i class="fas fa-book me-2"></i>Uploaded Syllabuses
    <span class="text-muted fw-normal">(<?= count($docs) ?>)</span>
  </div>
  <?php if (empty($docs)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2);font-size:.88rem">
    <i class="fas fa-book fa-2x mb-3" style="opacity:.3"></i>
    <p class="mb-0">No syllabuses uploaded yet.</p>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light"><tr><th>Title</th><th>Class</th><th>Year</th><th>File</th><th>Size</th><th>Uploaded By</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($docs as $doc):
          $iconMap = ['pdf'=>'fa-file-pdf','xlsx'=>'fa-file-excel','xls'=>'fa-file-excel','doc'=>'fa-file-word','docx'=>'fa-file-word'];
          $icon    = $iconMap[$doc['file_type']] ?? 'fa-file';
          $sizeFmt = $doc['file_size'] > 1048576 ? round($doc['file_size']/1048576,1).' MB' : round($doc['file_size']/1024).' KB';
        ?>
        <tr>
          <td class="fw-semibold"><?=h($doc['title'])?><?php if($doc['notes']):?><div style="font-size:.75rem;color:#9ca3af"><?=h($doc['notes'])?></div><?php endif;?></td>
          <td><?= $doc['class_name'] ? h($doc['class_name']) : '<span class="text-muted">All Classes</span>' ?></td>
          <td><?=h($doc['academic_year'])?></td>
          <td><i class="fas <?=$icon?> me-1" style="color:#2563eb"></i><span style="font-size:.8rem"><?=h($doc['original_filename'])?></span></td>
          <td style="font-size:.8rem"><?=$sizeFmt?></td>
          <td style="font-size:.8rem"><?=h($doc['uploader_name'])?></td>
          <td style="font-size:.78rem;color:#6b7280"><?=fDate($doc['created_at'])?></td>
          <td style="white-space:nowrap">
            <a href="/portal/api/serve-document.php?type=syllabus&id=<?=$doc['id']?>" class="btn btn-xs btn-primary me-1" style="font-size:.72rem;padding:2px 7px"><i class="fas fa-download"></i> Download</a>
            <button class="btn btn-xs btn-outline-secondary me-1" style="font-size:.72rem;padding:2px 7px" onclick="openReplace(<?=$doc['id']?>,<?=htmlspecialchars(json_encode($doc['title']))?>)"><i class="fas fa-sync"></i> Replace</button>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this syllabus?')">
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
    <div class="modal-header"><h6 class="modal-title"><i class="fas fa-sync me-2"></i>Replace Syllabus</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
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

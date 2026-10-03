<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('admin');
$db      = getDB();
$yearNow = (int)date('Y');

$allowedTypes = ['pdf', 'xlsx', 'xls', 'doc', 'docx'];
$maxSize      = 10 * 1024 * 1024; // 10 MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $title    = trim($_POST['title'] ?? '');
        $wing     = $_POST['wing'] ?? 'all';
        $year     = trim($_POST['academic_year'] ?? "$yearNow-" . ($yearNow + 1));
        $notes    = trim($_POST['notes'] ?? '');
        $allowedWings = ['main', 'montessori', 'ilc', 'all'];
        if (!in_array($wing, $allowedWings, true)) $wing = 'all';

        if (!$title) { setFlash('danger', 'Title is required.'); redirect('/portal/admin/timetable.php'); }

        if (empty($_FILES['document']['name'])) {
            setFlash('danger', 'Please select a file to upload.');
            redirect('/portal/admin/timetable.php');
        }

        $file     = $_FILES['document'];
        $origName = basename($file['name']);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedTypes, true)) {
            setFlash('danger', 'Invalid file type. Allowed: PDF, Excel (.xlsx/.xls), Word (.doc/.docx).');
            redirect('/portal/admin/timetable.php');
        }
        if ($file['size'] > $maxSize) {
            setFlash('danger', 'File too large. Maximum allowed size is 10 MB.');
            redirect('/portal/admin/timetable.php');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            setFlash('danger', 'Upload failed. Please try again.');
            redirect('/portal/admin/timetable.php');
        }

        $storedName = 'tt_' . uniqid('', true) . '.' . $ext;
        $dest       = __DIR__ . '/../../uploads/documents/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            setFlash('danger', 'Failed to save file. Please try again.');
            redirect('/portal/admin/timetable.php');
        }

        $db->prepare(
            'INSERT INTO timetable_documents (title, wing, academic_year, notes, original_filename, stored_filename, file_type, file_size, uploaded_by)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([$title, $wing, $year, $notes, $origName, $storedName, $ext, $file['size'], $user['id']]);

        logActivity($user['id'], 'timetable_doc_upload', "Uploaded timetable: \"$title\" ($wing, $year)");
        setFlash('success', "Timetable document \"$title\" uploaded successfully.");
        redirect('/portal/admin/timetable.php');
    }

    if ($action === 'delete') {
        $docId = (int)($_POST['doc_id'] ?? 0);
        if ($docId) {
            $r = $db->prepare('SELECT stored_filename, title FROM timetable_documents WHERE id=?');
            $r->execute([$docId]);
            $row = $r->fetch();
            if ($row) {
                $filePath = __DIR__ . '/../../uploads/documents/' . $row['stored_filename'];
                if (file_exists($filePath)) @unlink($filePath);
                $db->prepare('DELETE FROM timetable_documents WHERE id=?')->execute([$docId]);
                logActivity($user['id'], 'timetable_doc_delete', "Deleted timetable doc: \"" . $row['title'] . '"');
                setFlash('success', 'Document deleted.');
            }
        }
        redirect('/portal/admin/timetable.php');
    }

    if ($action === 'replace') {
        $docId = (int)($_POST['doc_id'] ?? 0);
        if (!$docId) { setFlash('danger', 'Invalid document.'); redirect('/portal/admin/timetable.php'); }

        if (empty($_FILES['document']['name'])) {
            setFlash('danger', 'Please select a replacement file.');
            redirect('/portal/admin/timetable.php');
        }

        $file     = $_FILES['document'];
        $origName = basename($file['name']);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedTypes, true)) {
            setFlash('danger', 'Invalid file type. Allowed: PDF, Excel (.xlsx/.xls), Word (.doc/.docx).');
            redirect('/portal/admin/timetable.php');
        }
        if ($file['size'] > $maxSize) {
            setFlash('danger', 'File too large. Maximum 10 MB.');
            redirect('/portal/admin/timetable.php');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            setFlash('danger', 'Upload failed. Please try again.');
            redirect('/portal/admin/timetable.php');
        }

        $old = $db->prepare('SELECT stored_filename FROM timetable_documents WHERE id=?');
        $old->execute([$docId]);
        $oldRow = $old->fetch();
        if ($oldRow) {
            $oldPath = __DIR__ . '/../../uploads/documents/' . $oldRow['stored_filename'];
            if (file_exists($oldPath)) @unlink($oldPath);
        }

        $storedName = 'tt_' . uniqid('', true) . '.' . $ext;
        $dest       = __DIR__ . '/../../uploads/documents/' . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            setFlash('danger', 'Failed to save file.');
            redirect('/portal/admin/timetable.php');
        }

        $db->prepare(
            'UPDATE timetable_documents SET original_filename=?, stored_filename=?, file_type=?, file_size=? WHERE id=?'
        )->execute([$origName, $storedName, $ext, $file['size'], $docId]);

        logActivity($user['id'], 'timetable_doc_replace', "Replaced timetable doc #$docId");
        setFlash('success', 'Document replaced successfully.');
        redirect('/portal/admin/timetable.php');
    }

    redirect('/portal/admin/timetable.php');
}

// Load documents
$docs = $db->query(
    "SELECT td.*, u.name AS uploader_name
     FROM timetable_documents td JOIN users u ON td.uploaded_by=u.id
     ORDER BY td.created_at DESC"
)->fetchAll();

pageHead('Timetable Documents', 'admin');
$links = getAdminLinks();
?>
<div class="portal-wrap">
<?php sidebar('admin', 'timetable', $links, $user); ?>
<div class="main-area">
<?php topbar('Timetable Documents', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Upload Form -->
<div class="sec-card mb-3">
  <div class="sec-card-header">
    <i class="fas fa-upload me-2"></i>Upload Timetable Document
  </div>
  <div style="padding:18px">
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label fw-semibold" style="font-size:.82rem">Title *</label>
          <input type="text" name="title" class="form-control form-control-sm" required
                 placeholder="e.g. Main Wing Timetable 2025-2026">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Campus / Wing</label>
          <select name="wing" class="form-select form-select-sm">
            <option value="all">All Campuses</option>
            <option value="main">Main Wing</option>
            <option value="montessori">Montessori</option>
            <option value="ilc">ILC</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Academic Year</label>
          <input type="text" name="academic_year" class="form-control form-control-sm"
                 value="<?= $yearNow ?>-<?= $yearNow + 1 ?>" placeholder="2025-2026">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold" style="font-size:.82rem">File * <span class="text-muted fw-normal">(PDF, Excel, Word — max 10 MB)</span></label>
          <input type="file" name="document" class="form-control form-control-sm" required
                 accept=".pdf,.xlsx,.xls,.doc,.docx">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Notes (optional)</label>
          <input type="text" name="notes" class="form-control form-control-sm" placeholder="Brief note">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-sm btn-success">
            <i class="fas fa-upload me-1"></i>Upload Document
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Document List -->
<div class="sec-card">
  <div class="sec-card-header">
    <i class="fas fa-file-alt me-2"></i>Uploaded Timetables
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
      <thead class="table-light">
        <tr>
          <th>Title</th><th>Wing</th><th>Year</th><th>File</th>
          <th>Size</th><th>Uploaded By</th><th>Date</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($docs as $doc):
          $wColor = match($doc['wing']) {
              'montessori' => '#7c3aed', 'ilc' => '#0891b2', 'main' => '#2563eb', default => '#6b7280'
          };
          $wLabel = match($doc['wing']) {
              'montessori' => 'Montessori', 'ilc' => 'ILC', 'main' => 'Main', default => 'All'
          };
          $iconMap = ['pdf' => 'fa-file-pdf', 'xlsx' => 'fa-file-excel', 'xls' => 'fa-file-excel', 'doc' => 'fa-file-word', 'docx' => 'fa-file-word'];
          $icon    = $iconMap[$doc['file_type']] ?? 'fa-file';
          $sizeFmt = $doc['file_size'] > 1048576
              ? round($doc['file_size'] / 1048576, 1) . ' MB'
              : round($doc['file_size'] / 1024, 0) . ' KB';
        ?>
        <tr>
          <td class="fw-semibold">
            <?= h($doc['title']) ?>
            <?php if ($doc['notes']): ?>
            <div style="font-size:.75rem;color:#9ca3af"><?= h($doc['notes']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <span style="font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:20px;background:<?= $wColor ?>;color:#fff">
              <?= $wLabel ?>
            </span>
          </td>
          <td><?= h($doc['academic_year']) ?></td>
          <td>
            <i class="fas <?= $icon ?> me-1" style="color:<?= $wColor ?>"></i>
            <span style="font-size:.8rem"><?= h($doc['original_filename']) ?></span>
          </td>
          <td style="font-size:.8rem"><?= $sizeFmt ?></td>
          <td style="font-size:.8rem"><?= h($doc['uploader_name']) ?></td>
          <td style="font-size:.78rem;color:#6b7280"><?= fDate($doc['created_at']) ?></td>
          <td style="white-space:nowrap">
            <?php if (($doc['file_type'] ?? '') === 'pdf'): ?>
            <a href="<?= url('/portal/api/serve-document.php') ?>?type=timetable&id=<?= $doc['id'] ?>&inline=1" target="_blank"
               class="btn btn-xs btn-outline-info me-1" style="font-size:.72rem;padding:2px 7px" title="View">
              <i class="fas fa-eye"></i> View
            </a>
            <?php endif; ?>
            <a href="<?= url('/portal/api/serve-document.php') ?>?type=timetable&id=<?= $doc['id'] ?>"
               class="btn btn-xs btn-primary me-1" style="font-size:.72rem;padding:2px 7px">
              <i class="fas fa-download"></i> Download
            </a>
            <button class="btn btn-xs btn-outline-secondary me-1" style="font-size:.72rem;padding:2px 7px"
                    onclick="openReplace(<?= $doc['id'] ?>, <?= htmlspecialchars(json_encode($doc['title'])) ?>)">
              <i class="fas fa-sync"></i> Replace
            </button>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this timetable document?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
              <button class="btn btn-xs btn-outline-danger" style="font-size:.72rem;padding:2px 7px">
                <i class="fas fa-trash"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Replace Modal -->
<div class="modal fade" id="replaceModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="fas fa-sync me-2"></i>Replace Document</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="replace">
        <input type="hidden" name="doc_id" id="replaceDocId">
        <div class="modal-body">
          <p class="text-muted mb-3" id="replaceDocTitle" style="font-size:.88rem"></p>
          <label class="form-label fw-semibold" style="font-size:.84rem">
            New File * <span class="text-muted fw-normal">(PDF, Excel, Word — max 10 MB)</span>
          </label>
          <input type="file" name="document" class="form-control form-control-sm" required
                 accept=".pdf,.xlsx,.xls,.doc,.docx">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-warning">
            <i class="fas fa-sync me-1"></i>Replace File
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openReplace(id, title) {
    document.getElementById('replaceDocId').value = id;
    document.getElementById('replaceDocTitle').textContent = 'Replacing: ' + title;
    new bootstrap.Modal(document.getElementById('replaceModal')).show();
}
</script>
</body></html>

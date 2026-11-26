<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

$user = requireAuth('admin', 'vp_main', 'examination_head');
$db   = getDB();

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if ($name && $code) {
            try {
                $db->prepare('INSERT INTO subjects (name, code) VALUES (?,?)')->execute([$name, $code]);
                logActivity($user['id'], 'subject_add', "Added subject: $name ($code)");
                setFlash('success', "Subject \"$name\" added.");
            } catch (Exception $e) {
                setFlash('danger', 'Could not add subject. Code may already be in use.');
            }
        } else {
            setFlash('danger', 'Name and code are required.');
        }
        redirect('/portal/admin/subjects.php');
    }

    if ($action === 'edit') {
        $id   = (int)($_POST['subject_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if ($id && $name && $code) {
            try {
                $db->prepare('UPDATE subjects SET name=?, code=? WHERE id=?')->execute([$name, $code, $id]);
                logActivity($user['id'], 'subject_edit', "Updated subject #$id → $name ($code)");
                setFlash('success', "Subject updated.");
            } catch (Exception $e) {
                setFlash('danger', 'Could not update subject. Code may already be in use.');
            }
        }
        redirect('/portal/admin/subjects.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['subject_id'] ?? 0);
        if ($id) {
            // Check for linked data
            $usedInMarks = (int)$db->prepare(
                'SELECT COUNT(*) FROM assessments WHERE subject_id=?'
            )->execute([$id]) ? $db->query("SELECT COUNT(*) FROM assessments WHERE subject_id=$id")->fetchColumn() : 0;
            $usedInClasses = (int)$db->query("SELECT COUNT(*) FROM class_subjects WHERE subject_id=$id")->fetchColumn();

            if ($usedInMarks > 0 || $usedInClasses > 0) {
                setFlash('danger', "Cannot delete: subject is linked to $usedInMarks assessment(s) and $usedInClasses class assignment(s). Remove those first.");
            } else {
                $db->prepare('DELETE FROM subjects WHERE id=?')->execute([$id]);
                logActivity($user['id'], 'subject_delete', "Deleted subject #$id");
                setFlash('success', 'Subject deleted.');
            }
        }
        redirect('/portal/admin/subjects.php');
    }
}

// ── Load subjects with usage counts ──────────────────────────────────────────
$subjects = $db->query(
    'SELECT s.id, s.name, s.code,
            (SELECT COUNT(DISTINCT cs.class_id) FROM class_subjects cs WHERE cs.subject_id=s.id) AS classes_count,
            (SELECT COUNT(*) FROM assessments a WHERE a.subject_id=s.id) AS assessments_count
     FROM subjects s
     ORDER BY s.name'
)->fetchAll();

// ── Edit mode ─────────────────────────────────────────────────────────────────
$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId) {
    foreach ($subjects as $s) {
        if ($s['id'] === $editId) { $editRow = $s; break; }
    }
}

// ── Page render ───────────────────────────────────────────────────────────────
pageHead('Subjects', $user['role']);
$links = match($user['role']) {
    'vp_main'          => getVpLinks(),
    'examination_head' => getExamHeadLinks(),
    default            => getAdminLinks(),
};
?>
<div class="portal-wrap">
<?php sidebar($user['role'], 'subjects', $links, $user); ?>
<div class="main-area">
<?php topbar('Subject Management', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="row g-3">

  <!-- Add / Edit Form -->
  <div class="col-md-4">
    <div class="sec-card">
      <div class="sec-card-header">
        <i class="fas fa-<?= $editRow ? 'edit' : 'plus' ?> me-2"></i>
        <?= $editRow ? 'Edit Subject' : 'Add New Subject' ?>
      </div>
      <div style="padding:16px">
        <form method="POST">
          <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
          <?php if ($editRow): ?>
          <input type="hidden" name="subject_id" value="<?= $editRow['id'] ?>">
          <?php endif; ?>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Subject Name</label>
            <input type="text" name="name" class="form-control form-control-sm"
                   placeholder="e.g. Mathematics"
                   value="<?= h($editRow['name'] ?? '') ?>" required maxlength="100">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Subject Code</label>
            <input type="text" name="code" class="form-control form-control-sm"
                   placeholder="e.g. MTH"
                   value="<?= h($editRow['code'] ?? '') ?>" required maxlength="20"
                   style="text-transform:uppercase">
            <div class="form-text" style="font-size:.78rem">Short unique code (auto-uppercased)</div>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-<?= $editRow ? 'primary' : 'success' ?> flex-grow-1">
              <i class="fas fa-<?= $editRow ? 'save' : 'plus' ?> me-1"></i>
              <?= $editRow ? 'Save Changes' : 'Add Subject' ?>
            </button>
            <?php if ($editRow): ?>
            <a href="/portal/admin/subjects.php" class="btn btn-sm btn-outline-secondary">Cancel</a>
            <?php endif; ?>
          </div>
        </form>

        <?php if (!$editRow): ?>
        <hr style="margin:16px 0;border-color:#e5e7eb">
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:10px 12px;font-size:.82rem;color:#92400e">
          <strong><i class="fas fa-info-circle me-1"></i>Biology note:</strong>
          Botany and Zoology are already added as separate subjects. The Biology subject is kept
          to preserve existing assessment records. You can assign new classes to Botany/Zoology instead.
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Subjects List -->
  <div class="col-md-8">
    <div class="sec-card">
      <div class="sec-card-header">
        <i class="fas fa-list me-2"></i>All Subjects
        <span class="badge bg-secondary ms-2" style="font-size:.7rem"><?= count($subjects) ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.84rem">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Subject Name</th>
              <th>Code</th>
              <th class="text-center">Classes</th>
              <th class="text-center">Assessments</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($subjects as $i => $s): ?>
            <tr class="<?= $editId === $s['id'] ? 'table-warning' : '' ?>">
              <td class="text-muted"><?= $i + 1 ?></td>
              <td class="fw-semibold"><?= h($s['name']) ?></td>
              <td><span class="badge bg-info text-dark" style="font-size:.76rem;font-family:monospace"><?= h($s['code']) ?></span></td>
              <td class="text-center">
                <?php if ($s['classes_count'] > 0): ?>
                  <span class="badge bg-success" style="font-size:.72rem"><?= $s['classes_count'] ?></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($s['assessments_count'] > 0): ?>
                  <span class="badge bg-primary" style="font-size:.72rem"><?= $s['assessments_count'] ?></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-end" style="white-space:nowrap">
                <a href="?edit=<?= $s['id'] ?>" class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 8px">
                  <i class="fas fa-edit"></i>
                </a>
                <?php if ($s['assessments_count'] == 0 && $s['classes_count'] == 0): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete subject &quot;<?= h($s['name']) ?>&quot;? This cannot be undone.')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="subject_id" value="<?= $s['id'] ?>">
                  <button class="btn btn-xs btn-outline-danger" style="font-size:.74rem;padding:2px 8px">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
                <?php else: ?>
                <button class="btn btn-xs btn-outline-secondary" disabled
                        style="font-size:.74rem;padding:2px 8px"
                        title="In use — cannot delete">
                  <i class="fas fa-lock"></i>
                </button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($subjects)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">No subjects yet. Add one above.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div><!-- /row -->

</div><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /portal-wrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

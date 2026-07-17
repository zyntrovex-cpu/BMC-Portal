<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('student_affairs');
requirePermission('sa_admissions');
$db   = getDB();

// ── Handle status update ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $id          = (int)($_POST['id'] ?? 0);
    $newStatus   = $_POST['status'] ?? '';
    $reviewNotes = trim($_POST['review_notes'] ?? '');

    if ($id && in_array($newStatus, ['reviewed','approved','rejected'])) {
        $db->prepare(
            'UPDATE admission_requests
             SET status=?, reviewed_by=?, review_notes=?, reviewed_at=NOW()
             WHERE id=?'
        )->execute([$newStatus, $user['id'], $reviewNotes ?: null, $id]);
        logActivity($user['id'], 'admission_review', "Set admission request #$id to $newStatus");
        setFlash('success', "Request #$id marked as $newStatus.");
    }
    redirect('/student-affairs/admissions.php');
}

$statusFilter = $_GET['status'] ?? '';
$focusId      = (int)($_GET['id'] ?? 0);

$sql    = 'SELECT ar.*, ru.name AS requested_by_name, rv.name AS reviewed_by_name
           FROM admission_requests ar
           JOIN users ru ON ru.id = ar.requested_by
           LEFT JOIN users rv ON rv.id = ar.reviewed_by
           WHERE 1';
$params = [];
if ($statusFilter) { $sql .= ' AND ar.status = ?'; $params[] = $statusFilter; }
$sql .= ' ORDER BY FIELD(ar.status,"pending","reviewed","approved","rejected"), ar.created_at DESC';

$st = $db->prepare($sql);
$st->execute($params);
$requests = $st->fetchAll();

pageHead('Admission Requests — Student Affairs', 'student_affairs');
$links = getStudentAffairsLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('student_affairs', 'admissions', $links, $user); ?>
<div class="main-area">
<?php topbar('Admission Requests', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- ILC branding strip -->
<div class="d-flex align-items-center gap-3 mb-4 p-3" style="background:linear-gradient(90deg,#fdf2f8,#fce7f3);border-radius:10px;border:1px solid #fbcfe8;">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#be185d;letter-spacing:.8px;text-transform:uppercase">Student Affairs Office</div>
    <div style="font-size:.8rem;color:#475569">Inclusive Learning Centre &mdash; Bahria Model College Bin Qasim</div>
  </div>
</div>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-file-medical-alt me-2"></i>All Admission Requests (<?= count($requests) ?>)</span>
    <div class="d-flex gap-1">
      <?php foreach ([''=>'All','pending'=>'Pending','reviewed'=>'Reviewed','approved'=>'Approved','rejected'=>'Rejected'] as $v=>$lbl): ?>
      <a href="?status=<?= $v ?>" class="btn btn-xs <?= $statusFilter===$v?'btn-primary':'btn-outline-secondary' ?>" style="font-size:.74rem;padding:2px 8px"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (empty($requests)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2);font-size:.85rem">No requests found.</div>
  <?php else: ?>
  <?php foreach ($requests as $r):
    $sc = match($r['status']) {'pending'=>'warning','reviewed'=>'info','approved'=>'success','rejected'=>'danger',default=>'secondary'};
    $highlight = $focusId == $r['id'] ? 'background:#fffbeb;' : '';
  ?>
  <div style="padding:14px 16px;border-bottom:1px solid var(--border);<?= $highlight ?>">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <div class="fw-bold"><?= h($r['student_name']) ?>
          <span class="badge bg-<?= $sc ?> ms-1"><?= $r['status'] ?></span>
        </div>
        <div style="font-size:.8rem;color:var(--t2)">
          Requested by <strong><?= h($r['requested_by_name']) ?></strong> · <?= fDate($r['created_at']) ?>
          <?php if ($r['requested_class']): ?> · Class: <strong><?= h($r['requested_class']) ?></strong><?php endif; ?>
          <?php if (!empty($r['student_category'])): ?> · <span class="badge bg-info text-dark" style="font-size:.7rem"><?= strtoupper(h($r['student_category'])) ?></span><?php endif; ?>
          <?php if (!empty($r['wing'])): ?> · Wing: <strong><?= ucfirst(h($r['wing'])) ?></strong><?php endif; ?>
        </div>
        <div class="row g-2 mt-1" style="font-size:.82rem">
          <?php if ($r['parent_name']): ?><div class="col-auto">Parent: <?= h($r['parent_name']) ?></div><?php endif; ?>
          <?php if ($r['parent_phone']): ?><div class="col-auto">Ph: <?= h($r['parent_phone']) ?></div><?php endif; ?>
          <?php if ($r['dob']): ?><div class="col-auto">DOB: <?= date('d M Y', strtotime($r['dob'])) ?></div><?php endif; ?>
        </div>
        <?php if ($r['disability_notes']): ?>
        <div class="mt-1" style="font-size:.8rem;background:#f0f9ff;border-radius:6px;padding:6px 10px;border:1px solid #bae6fd">
          <i class="fas fa-sticky-note me-1 text-muted"></i><?= h($r['disability_notes']) ?>
        </div>
        <?php endif; ?>
        <?php if ($r['reviewed_by_name']): ?>
        <div style="font-size:.78rem;color:var(--t2);margin-top:4px">
          Reviewed by <?= h($r['reviewed_by_name']) ?> on <?= $r['reviewed_at'] ? fDate($r['reviewed_at']) : '—' ?>
          <?php if ($r['review_notes']): ?> — <em><?= h($r['review_notes']) ?></em><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($r['status'] === 'pending' || $r['status'] === 'reviewed'): ?>
      <div>
        <button class="btn btn-sm btn-outline-primary" style="font-size:.78rem"
                onclick="toggleReview(<?= $r['id'] ?>)">
          <i class="fas fa-edit me-1"></i>Review
        </button>
      </div>
      <?php endif; ?>
    </div>

    <!-- Inline review form (hidden by default) -->
    <div id="review-<?= $r['id'] ?>" class="mt-2" style="display:none">
      <form method="POST">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" value="<?= $r['id'] ?>">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.78rem">Update Status</label>
            <select name="status" class="form-select form-select-sm">
              <option value="reviewed" <?= $r['status']==='reviewed'?'selected':'' ?>>Reviewed</option>
              <option value="approved" <?= $r['status']==='approved'?'selected':'' ?>>Approved</option>
              <option value="rejected" <?= $r['status']==='rejected'?'selected':'' ?>>Rejected</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold" style="font-size:.78rem">Review Notes</label>
            <input type="text" name="review_notes" class="form-control form-control-sm"
                   placeholder="Optional notes for ILC VP…" value="<?= h($r['review_notes'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <button type="submit" class="btn btn-sm btn-success w-100">Save</button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleReview(id) {
  var el = document.getElementById('review-' + id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
<?php if ($focusId): ?>
document.addEventListener('DOMContentLoaded', function(){ toggleReview(<?= $focusId ?>); });
<?php endif; ?>
</script>
</body></html>

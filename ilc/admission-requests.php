<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('ilc_vp');
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $studentName   = trim($_POST['student_name']    ?? '');
    $parentName    = trim($_POST['parent_name']     ?? '');
    $parentPhone   = trim($_POST['parent_phone']    ?? '');
    $dob           = $_POST['dob']                  ?? '';
    $reqClass      = trim($_POST['requested_class']  ?? '');
    $disNotes      = trim($_POST['disability_notes'] ?? '');
    $stuCat        = in_array($_POST['student_category']??'', ['civilian','cpo','sailor']) ? $_POST['student_category'] : null;

    if (!$studentName) {
        setFlash('danger', 'Student name is required.');
    } else {
        $db->prepare(
            'INSERT INTO admission_requests
             (student_name, parent_name, parent_phone, dob, requested_class, student_category, wing, disability_notes, requested_by)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            $studentName,
            $parentName   ?: null,
            $parentPhone  ?: null,
            $dob          ?: null,
            $reqClass     ?: null,
            $stuCat,
            'ilc',
            $disNotes     ?: null,
            $user['id'],
        ]);
        logActivity($user['id'], 'admission_request_create', "Created admission request for $studentName");
        setFlash('success', "Admission request for \"$studentName\" submitted to Student Affairs.");
    }
    redirect('/ilc/admission-requests.php');
}

$statusFilter = $_GET['status'] ?? '';
$sql    = 'SELECT ar.*, u.name AS reviewed_by_name
           FROM admission_requests ar
           LEFT JOIN users u ON u.id = ar.reviewed_by
           WHERE ar.requested_by = ?';
$params = [$user['id']];
if ($statusFilter) { $sql .= ' AND ar.status = ?'; $params[] = $statusFilter; }
$sql .= ' ORDER BY ar.created_at DESC';

$st = $db->prepare($sql);
$st->execute($params);
$requests = $st->fetchAll();

pageHead('Admission Requests', 'ilc_vp');
$links = getIlcLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'admissions', $links, $user); ?>
<div class="main-area">
<?php topbar('Admission Requests', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- ILC branding strip -->
<div class="d-flex align-items-center gap-3 mb-4 p-3" style="background:linear-gradient(90deg,#ecfeff,#f0fdf4);border-radius:10px;border:1px solid #a5f3fc;">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#0891b2;letter-spacing:.8px;text-transform:uppercase">Inclusive Learning Centre</div>
    <div style="font-size:.8rem;color:#475569">Bahria Model College Bin Qasim</div>
  </div>
</div>

<!-- New request form -->
<div class="sec-card mb-3">
  <div class="sec-card-header" data-bs-toggle="collapse" data-bs-target="#newReqForm" style="cursor:pointer">
    <i class="fas fa-plus me-2"></i>New Admission Request <i class="fas fa-chevron-down ms-auto"></i>
  </div>
  <div id="newReqForm" class="collapse">
    <div style="padding:16px">
      <form method="POST">
        <input type="hidden" name="action" value="create">
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label fw-semibold" style="font-size:.82rem">Student Name <span class="text-danger">*</span></label>
            <input type="text" name="student_name" class="form-control form-control-sm" required>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" style="font-size:.82rem">Parent Name</label>
            <input type="text" name="parent_name" class="form-control form-control-sm">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" style="font-size:.82rem">Parent Phone</label>
            <input type="tel" name="parent_phone" class="form-control form-control-sm" placeholder="03xx…">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Date of Birth</label>
            <input type="date" name="dob" class="form-control form-control-sm">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Requested Class</label>
            <input type="text" name="requested_class" class="form-control form-control-sm" placeholder="e.g. ILC-A">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Category</label>
            <select name="student_category" class="form-select form-select-sm">
              <option value="">—</option>
              <option value="civilian">Civilian</option>
              <option value="cpo">CPO</option>
              <option value="sailor">Sailor</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" style="font-size:.82rem">Disability / Special Notes</label>
            <textarea name="disability_notes" class="form-control form-control-sm" rows="2" placeholder="Brief description of student's needs…"></textarea>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-sm btn-primary">
              <i class="fas fa-paper-plane me-1"></i>Submit to Student Affairs
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Request list -->
<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-list me-2"></i>My Requests (<?= count($requests) ?>)</span>
    <div class="d-flex gap-1">
      <?php foreach ([''=>'All','pending'=>'Pending','reviewed'=>'Reviewed','approved'=>'Approved','rejected'=>'Rejected'] as $v=>$lbl): ?>
      <a href="?status=<?= $v ?>" class="btn btn-xs <?= $statusFilter===$v?'btn-primary':'btn-outline-secondary' ?>" style="font-size:.74rem;padding:2px 8px"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (empty($requests)): ?>
  <div style="padding:32px;text-align:center;color:var(--t2);font-size:.85rem">No requests found.</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr><th>#</th><th>Student Name</th><th>Parent</th><th>Phone</th><th>DOB</th><th>Req. Class</th><th>Status</th><th>Reviewed By</th><th>Date</th></tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $r): ?>
        <?php $sc = match($r['status']) {'pending'=>'warning','reviewed'=>'info','approved'=>'success','rejected'=>'danger',default=>'secondary'}; ?>
        <tr>
          <td class="text-muted"><?= $r['id'] ?></td>
          <td class="fw-semibold"><?= h($r['student_name']) ?></td>
          <td><?= h($r['parent_name'] ?: '—') ?></td>
          <td><?= h($r['parent_phone'] ?: '—') ?></td>
          <td><?= $r['dob'] ? date('d M Y', strtotime($r['dob'])) : '—' ?></td>
          <td><?= h($r['requested_class'] ?: '—') ?></td>
          <td><span class="badge bg-<?= $sc ?>"><?= $r['status'] ?></span></td>
          <td>
            <?= h($r['reviewed_by_name'] ?: '—') ?>
            <?php if ($r['review_notes']): ?>
            <br><small class="text-muted"><?= h($r['review_notes']) ?></small>
            <?php endif; ?>
          </td>
          <td style="font-size:.78rem;white-space:nowrap"><?= fDate($r['created_at']) ?></td>
        </tr>
        <?php if ($r['disability_notes']): ?>
        <tr class="table-light">
          <td></td>
          <td colspan="8" style="font-size:.78rem;color:var(--t2);padding-top:2px;padding-bottom:8px">
            <i class="fas fa-sticky-note me-1"></i><?= h($r['disability_notes']) ?>
          </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

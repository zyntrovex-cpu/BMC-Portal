<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('student_affairs');
$db   = getDB();

$pending  = (int)$db->query('SELECT COUNT(*) FROM admission_requests WHERE status="pending"')->fetchColumn();
$reviewed = (int)$db->query('SELECT COUNT(*) FROM admission_requests WHERE status="reviewed"')->fetchColumn();
$approved = (int)$db->query('SELECT COUNT(*) FROM admission_requests WHERE status="approved"')->fetchColumn();
$rejected = (int)$db->query('SELECT COUNT(*) FROM admission_requests WHERE status="rejected"')->fetchColumn();

$recent = $db->query(
    'SELECT ar.*, ru.name AS requested_by_name
     FROM admission_requests ar
     JOIN users ru ON ru.id = ar.requested_by
     ORDER BY ar.created_at DESC LIMIT 6'
)->fetchAll();

pageHead('Student Affairs Dashboard', 'student_affairs');
$links = getStudentAffairsLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('student_affairs', 'dashboard', $links, $user); ?>
<div class="main-area">
<?php topbar('Student Affairs', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-clock"></i></div>
      <div class="stat-val" style="color:#d97706"><?= $pending ?></div>
      <div class="stat-lbl">Pending</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#e0f2fe;color:#0891b2"><i class="fas fa-eye"></i></div>
      <div class="stat-val" style="color:#0891b2"><?= $reviewed ?></div>
      <div class="stat-lbl">Reviewed</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#d1fae5;color:#059669"><i class="fas fa-check-circle"></i></div>
      <div class="stat-val" style="color:#059669"><?= $approved ?></div>
      <div class="stat-lbl">Approved</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-times-circle"></i></div>
      <div class="stat-val" style="color:#dc2626"><?= $rejected ?></div>
      <div class="stat-lbl">Rejected</div>
    </div>
  </div>
</div>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between">
    <span><i class="fas fa-file-medical-alt me-2"></i>Recent Admission Requests</span>
    <a href="/student-affairs/admissions.php" class="btn btn-xs btn-outline-primary" style="font-size:.75rem;padding:2px 8px">View All</a>
  </div>
  <?php if (empty($recent)): ?>
  <div style="padding:32px;text-align:center;color:var(--t2);font-size:.85rem">No requests yet.</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light"><tr><th>Student</th><th>Requested By</th><th>Class</th><th>Date</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r):
          $sc = match($r['status']) {'pending'=>'warning','reviewed'=>'info','approved'=>'success','rejected'=>'danger',default=>'secondary'};
        ?>
        <tr>
          <td class="fw-semibold"><?= h($r['student_name']) ?></td>
          <td><?= h($r['requested_by_name']) ?></td>
          <td><?= h($r['requested_class'] ?: '—') ?></td>
          <td style="font-size:.78rem"><?= fDate($r['created_at']) ?></td>
          <td><span class="badge bg-<?= $sc ?>"><?= $r['status'] ?></span></td>
          <td><a href="/student-affairs/admissions.php?id=<?= $r['id'] ?>" class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 7px">Review</a></td>
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

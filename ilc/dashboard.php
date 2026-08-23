<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('ilc_vp');
$db   = getDB();

// Stats — all wrapped in try/catch for tables that may not exist yet
$ilcStudents = $ilcTeachers = $pendingAdmissions = $disabilityCount = 0;
$recentAdmissions = $catBreakdown = [];
try {
    $ilcStudents = (int)$db->query(
        'SELECT COUNT(*) FROM students s JOIN classes c ON c.id=s.class_id WHERE c.is_ilc=1'
    )->fetchColumn();
} catch (Exception $e) {}
try {
    $ilcTeachers = (int)$db->query('SELECT COUNT(*) FROM teachers WHERE is_ilc=1')->fetchColumn();
} catch (Exception $e) {}
try {
    $paStmt = $db->prepare('SELECT COUNT(*) FROM admission_requests WHERE status="pending" AND requested_by=?');
    $paStmt->execute([$user['id']]);
    $pendingAdmissions = (int)$paStmt->fetchColumn();
} catch (Exception $e) {}
try {
    $disabilityCount = (int)$db->query(
        'SELECT COUNT(*) FROM student_disabilities sd JOIN students s ON s.id=sd.student_id JOIN classes c ON c.id=s.class_id WHERE c.is_ilc=1'
    )->fetchColumn();
} catch (Exception $e) {}
try {
    $recentAdm = $db->prepare(
        'SELECT ar.*, u.name AS reviewed_by_name
         FROM admission_requests ar
         LEFT JOIN users u ON u.id = ar.reviewed_by
         WHERE ar.requested_by = ?
         ORDER BY ar.created_at DESC LIMIT 5'
    );
    $recentAdm->execute([$user['id']]);
    $recentAdmissions = $recentAdm->fetchAll();
} catch (Exception $e) {}
try {
    $catBreakdown = $db->query(
        'SELECT dc.name, COUNT(sd.id) AS cnt
         FROM disability_categories dc
         LEFT JOIN disability_subtypes dst ON dst.category_id=dc.id
         LEFT JOIN student_disabilities sd ON sd.subtype_id=dst.id
         LEFT JOIN students s ON s.id=sd.student_id
         LEFT JOIN classes c ON c.id=s.class_id AND c.is_ilc=1
         GROUP BY dc.id, dc.name ORDER BY cnt DESC'
    )->fetchAll();
} catch (Exception $e) {}

pageHead('ILC Dashboard', 'ilc_vp');
$links = getIlcLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'dashboard', $links, $user); ?>
<div class="main-area">
<?php topbar('ILC Dashboard', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- ILC Welcome Banner -->
<div class="portal-banner mb-4" style="background:linear-gradient(135deg,#0c4a6e,#0891b2);padding:0;overflow:hidden;position:relative;">
  <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 80% 50%,rgba(255,255,255,.08) 0%,transparent 70%);pointer-events:none;"></div>
  <div class="d-flex align-items-center gap-4 p-3 ps-4">
    <div style="width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,.95);display:flex;align-items:center;justify-content:center;flex-shrink:0;padding:6px;box-shadow:0 4px 16px rgba(0,0,0,.25);">
      <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC Logo" style="width:100%;height:100%;object-fit:contain;">
    </div>
    <div>
      <h4 class="mb-1 text-white fw-bold" style="font-size:1.1rem">Welcome, <?= h($user['name']) ?>!</h4>
      <div style="color:rgba(255,255,255,.75);font-size:.82rem">
        ILC — Inclusive Learning Centre &nbsp;&middot;&nbsp; <?= h($user['user_id']) ?> &nbsp;&middot;&nbsp; Session <?= SESSION_YEAR ?>
      </div>
    </div>
    <div class="ms-auto d-none d-md-block" style="opacity:.12;">
      <img src="<?= url('/assets/ilc-logo.png') ?>" alt="" style="height:80px;width:auto;filter:brightness(0) invert(1);">
    </div>
  </div>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#e0f2fe;color:#0891b2"><i class="fas fa-user-graduate"></i></div>
      <div class="stat-val" style="color:#0891b2"><?= $ilcStudents ?></div>
      <div class="stat-lbl">ILC Students</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#d1fae5;color:#059669"><i class="fas fa-chalkboard-teacher"></i></div>
      <div class="stat-val" style="color:#059669"><?= $ilcTeachers ?></div>
      <div class="stat-lbl">ILC Teachers</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-file-medical-alt"></i></div>
      <div class="stat-val" style="color:#d97706"><?= $pendingAdmissions ?></div>
      <div class="stat-lbl">Pending Admissions</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#ede9fe;color:#7c3aed"><i class="fas fa-heartbeat"></i></div>
      <div class="stat-val" style="color:#7c3aed"><?= $disabilityCount ?></div>
      <div class="stat-lbl">Disability Records</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Disability breakdown -->
  <div class="col-lg-5">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-heartbeat me-2"></i>Disability Categories</div>
      <div style="padding:12px 16px">
        <?php if (empty($catBreakdown)): ?>
        <p class="text-muted mb-0" style="font-size:.85rem">No records yet.</p>
        <?php else: ?>
        <?php foreach ($catBreakdown as $cat): ?>
        <div class="d-flex justify-content-between align-items-center mb-2" style="font-size:.85rem">
          <span><?= h($cat['name']) ?></span>
          <span class="badge" style="background:#0891b2"><?= $cat['cnt'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <a href="<?= url('/ilc/disabilities.php') ?>" class="btn btn-sm btn-outline-secondary w-100 mt-2" style="font-size:.8rem">View All Records</a>
      </div>
    </div>
  </div>

  <!-- Recent admission requests -->
  <div class="col-lg-7">
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between">
        <span><i class="fas fa-file-medical-alt me-2"></i>Recent Admission Requests</span>
        <a href="<?= url('/ilc/admission-requests.php') ?>" class="btn btn-xs btn-outline-primary" style="font-size:.75rem;padding:2px 8px">View All</a>
      </div>
      <?php if (empty($recentAdmissions)): ?>
      <div style="padding:24px;text-align:center;color:var(--t2);font-size:.85rem">
        No admission requests yet. <a href="<?= url('/ilc/admission-requests.php') ?>">Create one</a>.
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm mb-0" style="font-size:.82rem">
          <thead class="table-light"><tr><th>Student Name</th><th>Class</th><th>Date</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($recentAdmissions as $ar): ?>
            <tr>
              <td class="fw-semibold"><?= h($ar['student_name']) ?></td>
              <td><?= h($ar['requested_class'] ?: '—') ?></td>
              <td style="font-size:.78rem"><?= fDate($ar['created_at']) ?></td>
              <td>
                <?php
                $sc = match($ar['status']) {
                    'pending'  => 'warning',
                    'reviewed' => 'info',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    default    => 'secondary',
                };
                ?>
                <span class="badge bg-<?= $sc ?>"><?= $ar['status'] ?></span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

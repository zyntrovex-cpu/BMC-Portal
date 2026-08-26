<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('vp_main');
$db   = getDB();

// Stats
$totalTeachers = $mainStudents = $montessoriStu = $wingHeads = 0;
$classSummary  = $recentActivity = [];
try {
    $totalTeachers = (int)$db->query('SELECT COUNT(*) FROM teachers t JOIN users u ON u.id=t.user_id WHERE u.status="active"')->fetchColumn();
} catch (Exception $e) {}
try {
    $mainStudents  = (int)$db->query('SELECT COUNT(*) FROM students s JOIN classes c ON c.id=s.class_id WHERE c.is_ilc=0 AND c.is_montessori=0')->fetchColumn();
} catch (Exception $e) {}
try {
    $montessoriStu = (int)$db->query('SELECT COUNT(*) FROM students s JOIN classes c ON c.id=s.class_id WHERE c.is_montessori=1')->fetchColumn();
} catch (Exception $e) {}
try {
    $wingHeads     = (int)$db->query('SELECT COUNT(*) FROM users WHERE role="wing_head" AND status="active"')->fetchColumn();
} catch (Exception $e) {}

// Class summary
try {
    $classSummary = $db->query(
        'SELECT c.name, c.is_montessori, c.is_ilc,
                COUNT(s.id) AS student_count
         FROM classes c
         LEFT JOIN students s ON s.class_id = c.id
         WHERE c.is_ilc = 0
         GROUP BY c.id ORDER BY c.is_montessori, c.name'
    )->fetchAll();
} catch (Exception $e) {}

// Recent activity log
try {
    $recentActivity = $db->query(
        'SELECT al.*, u.name AS actor_name, u.role AS actor_role
         FROM activity_log al
         JOIN users u ON u.id = al.user_id
         ORDER BY al.created_at DESC LIMIT 8'
    )->fetchAll();
} catch (Exception $e) {}

pageHead('VP Dashboard', 'vp_main');
$links = getVpLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('vp_main', 'dashboard', $links, $user); ?>
<div class="main-area">
<?php topbar('VP Dashboard', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#e0f2fe;color:#0369a1"><i class="fas fa-chalkboard-teacher"></i></div>
      <div class="stat-val" style="color:#0369a1"><?= $totalTeachers ?></div>
      <div class="stat-lbl">Total Teachers</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#dbeafe;color:#1d4ed8"><i class="fas fa-user-graduate"></i></div>
      <div class="stat-val" style="color:#1d4ed8"><?= $mainStudents ?></div>
      <div class="stat-lbl">Main Campus Students</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef3c7;color:#c2410c"><i class="fas fa-child"></i></div>
      <div class="stat-val" style="color:#c2410c"><?= $montessoriStu ?></div>
      <div class="stat-lbl">Montessori Students</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#d1fae5;color:#059669"><i class="fas fa-sitemap"></i></div>
      <div class="stat-val" style="color:#059669"><?= $wingHeads ?></div>
      <div class="stat-lbl">Wing Heads</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Class summary -->
  <div class="col-lg-5">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-chalkboard me-2"></i>Class Summary</div>
      <div style="padding:12px 16px">
        <?php $prev = null; foreach ($classSummary as $c): ?>
        <?php if ($prev !== (bool)$c['is_montessori']): ?>
        <div class="fw-semibold mb-1 mt-2" style="font-size:.78rem;color:var(--t2);text-transform:uppercase">
          <?= $c['is_montessori'] ? 'Montessori Wing' : 'Main Campus' ?>
        </div>
        <?php $prev = (bool)$c['is_montessori']; endif; ?>
        <div class="d-flex justify-content-between align-items-center mb-2" style="font-size:.84rem">
          <span><?= h($c['name']) ?></span>
          <span class="badge <?= $c['is_montessori'] ? 'bg-warning text-dark' : 'bg-primary' ?>"><?= $c['student_count'] ?> students</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Recent activity -->
  <div class="col-lg-7">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-history me-2"></i>Recent Activity</div>
      <?php if (empty($recentActivity)): ?>
      <div style="padding:20px;text-align:center;color:var(--t2);font-size:.85rem">No activity yet.</div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm mb-0" style="font-size:.82rem">
          <thead class="table-light"><tr><th>User</th><th>Role</th><th>Action</th><th>Time</th></tr></thead>
          <tbody>
            <?php foreach ($recentActivity as $a): ?>
            <tr>
              <td><?= h($a['actor_name']) ?></td>
              <td><span class="badge bg-secondary" style="font-size:.7rem"><?= $a['actor_role'] ?></span></td>
              <td><?= h($a['details'] ?: $a['action']) ?></td>
              <td style="white-space:nowrap"><?= fDate($a['created_at']) ?></td>
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

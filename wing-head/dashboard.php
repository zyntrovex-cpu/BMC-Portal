<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('wing_head');
$db   = getDB();

$totalStudents = $totalClasses = 0;
$classSummary  = $catSummary  = [];
try {
    $totalStudents = (int)$db->query(
        'SELECT COUNT(*) FROM students s JOIN classes c ON c.id=s.class_id WHERE c.is_montessori=1'
    )->fetchColumn();
} catch (Exception $e) {}
try {
    $totalClasses = (int)$db->query('SELECT COUNT(*) FROM classes WHERE is_montessori=1')->fetchColumn();
} catch (Exception $e) {}
try {
    $classSummary = $db->query(
        'SELECT c.name, COUNT(s.id) AS cnt
         FROM classes c
         LEFT JOIN students s ON s.class_id = c.id
         WHERE c.is_montessori = 1
         GROUP BY c.id ORDER BY c.name'
    )->fetchAll();
} catch (Exception $e) {}
// Category breakdown (civilian/cpo/sailor)
try {
    $catSummary = $db->query(
        'SELECT s.student_category, COUNT(*) AS cnt
         FROM students s JOIN classes c ON c.id=s.class_id
         WHERE c.is_montessori=1
         GROUP BY s.student_category'
    )->fetchAll();
} catch (Exception $e) {}

pageHead('Wing Head Dashboard', 'wing_head');
$links = getWingHeadLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('wing_head', 'dashboard', $links, $user); ?>
<div class="main-area">
<?php topbar('Montessori Wing Head', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef3c7;color:#c2410c"><i class="fas fa-child"></i></div>
      <div class="stat-val" style="color:#c2410c"><?= $totalStudents ?></div>
      <div class="stat-lbl">Montessori Students</div>
    </div>
  </div>
  <div class="col-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-icon" style="background:#d1fae5;color:#059669"><i class="fas fa-chalkboard"></i></div>
      <div class="stat-val" style="color:#059669"><?= $totalClasses ?></div>
      <div class="stat-lbl">Classes</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-chalkboard me-2"></i>Classes</div>
      <div style="padding:12px 16px">
        <?php foreach ($classSummary as $c): ?>
        <div class="d-flex justify-content-between align-items-center mb-3" style="font-size:.86rem">
          <span class="fw-semibold"><?= h($c['name']) ?></span>
          <span class="badge" style="background:#c2410c"><?= $c['cnt'] ?> students</span>
        </div>
        <?php endforeach; ?>
        <a href="<?= url('/wing-head/classes.php') ?>" class="btn btn-sm btn-outline-secondary w-100 mt-1" style="font-size:.8rem">Manage Classes</a>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-users me-2"></i>Student Categories</div>
      <div style="padding:12px 16px">
        <?php if (empty($catSummary)): ?>
        <p class="text-muted mb-0" style="font-size:.85rem">No category data yet.</p>
        <?php else: ?>
        <?php foreach ($catSummary as $cat): ?>
        <div class="d-flex justify-content-between mb-2" style="font-size:.84rem">
          <span><?= $cat['student_category'] ? strtoupper($cat['student_category']) : 'Unassigned' ?></span>
          <span class="badge bg-secondary"><?= $cat['cnt'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <a href="<?= url('/wing-head/students.php') ?>" class="btn btn-sm btn-outline-secondary w-100 mt-2" style="font-size:.8rem">View All Students</a>
      </div>
    </div>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

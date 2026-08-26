<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('wing_head');
requirePermission('wh_classes');
$db   = getDB();

$classes = $db->query(
    'SELECT c.*, COUNT(s.id) AS student_count
     FROM classes c
     LEFT JOIN students s ON s.class_id = c.id
     WHERE c.is_montessori = 1
     GROUP BY c.id ORDER BY c.name'
)->fetchAll();

pageHead('Montessori Classes', 'wing_head');
$links = getWingHeadLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('wing_head', 'classes', $links, $user); ?>
<div class="main-area">
<?php topbar('Montessori Classes', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="row g-3">
  <?php foreach ($classes as $c): ?>
  <div class="col-sm-6 col-lg-3">
    <div class="sec-card" style="text-align:center">
      <div style="padding:28px 16px">
        <div style="width:64px;height:64px;border-radius:50%;background:#fef3c7;color:#c2410c;font-size:1.5rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
          <?= strtoupper(substr($c['name'],0,1)) ?>
        </div>
        <div class="fw-bold" style="font-size:1.05rem"><?= h($c['name']) ?></div>
        <div class="text-muted" style="font-size:.82rem">Montessori Wing</div>
        <div class="mt-3">
          <span class="badge" style="background:#c2410c;font-size:.88rem"><?= $c['student_count'] ?> students</span>
        </div>
        <a href="<?= url('/portal/wing-head/students.php') ?>?class_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary w-100 mt-3" style="font-size:.82rem">
          <i class="fas fa-users me-1"></i>View Students
        </a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($classes)): ?>
  <div class="col-12">
    <div class="alert alert-warning">No Montessori classes found. Ask an admin to add classes and mark them as Montessori in Admin → Classes.</div>
  </div>
  <?php endif; ?>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

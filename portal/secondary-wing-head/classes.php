<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('secondary_wing_head');
requirePermission('wh_classes');
$db   = getDB();

$gradeMin = 6; $gradeMax = 10;

$classes = [];
try {
    $st = $db->prepare(
        'SELECT c.*, COUNT(s.id) AS student_count
         FROM classes c
         LEFT JOIN students s ON s.class_id = c.id AND s.deleted_at IS NULL
         WHERE COALESCE(c.is_montessori,0)=0 AND COALESCE(c.is_ilc,0)=0
           AND c.grade BETWEEN ? AND ?
         GROUP BY c.id ORDER BY c.grade, c.name'
    );
    $st->execute([$gradeMin,$gradeMax]);
    $classes = $st->fetchAll();
} catch (Exception $e) {}

pageHead('Classes (6–10)', 'secondary_wing_head');
$links = getSecondaryWingHeadLinks();
?>
<div class="portal-wrap">
<?php sidebar('secondary_wing_head', 'classes', $links, $user); ?>
<div class="main-area">
<?php topbar('Classes (Grades 6–10)', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="row g-3">
  <?php foreach ($classes as $c): ?>
  <div class="col-sm-6 col-lg-3">
    <div class="sec-card" style="text-align:center">
      <div style="padding:28px 16px">
        <div style="width:64px;height:64px;border-radius:50%;background:#dbeafe;color:#1d4ed8;font-size:1.5rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
          <?= $c['grade'] ?>
        </div>
        <div class="fw-bold" style="font-size:1.05rem"><?= h($c['name']) ?></div>
        <div class="text-muted" style="font-size:.82rem">Grade <?= $c['grade'] ?> · Main Campus</div>
        <div class="mt-3">
          <span class="badge bg-primary" style="font-size:.88rem"><?= $c['student_count'] ?> students</span>
        </div>
        <a href="<?= url('/portal/secondary-wing-head/students.php') ?>?class_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary w-100 mt-3" style="font-size:.82rem">
          <i class="fas fa-users me-1"></i>View Students
        </a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($classes)): ?>
  <div class="col-12">
    <div class="alert alert-info">No classes found for grades 6–10. Ask an admin to add classes with grade numbers.</div>
  </div>
  <?php endif; ?>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

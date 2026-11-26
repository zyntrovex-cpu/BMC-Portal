<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('secondary_wing_head');
$db   = getDB();

$gradeMin = 6;
$gradeMax = 10;
$roleLabel = 'Secondary Wing Head (Classes 6–10)';

$classCount = $studentCount = $teacherCount = 0;
try {
    $classCount = (int)$db->prepare(
        'SELECT COUNT(*) FROM classes WHERE COALESCE(is_montessori,0)=0 AND COALESCE(is_ilc,0)=0 AND grade BETWEEN ? AND ?'
    )->execute([$gradeMin,$gradeMax]) ? $db->prepare(
        'SELECT COUNT(*) FROM classes WHERE COALESCE(is_montessori,0)=0 AND COALESCE(is_ilc,0)=0 AND grade BETWEEN ? AND ?'
    )->execute([$gradeMin,$gradeMax]) : 0;
    $st = $db->prepare('SELECT COUNT(*) FROM classes WHERE COALESCE(is_montessori,0)=0 AND COALESCE(is_ilc,0)=0 AND grade BETWEEN ? AND ?');
    $st->execute([$gradeMin,$gradeMax]);
    $classCount = (int)$st->fetchColumn();
} catch (Exception $e) {}
try {
    $st = $db->prepare(
        'SELECT COUNT(DISTINCT s.id) FROM students s
         JOIN classes c ON c.id=s.class_id
         WHERE COALESCE(c.is_montessori,0)=0 AND COALESCE(c.is_ilc,0)=0
           AND c.grade BETWEEN ? AND ? AND s.deleted_at IS NULL'
    );
    $st->execute([$gradeMin,$gradeMax]);
    $studentCount = (int)$st->fetchColumn();
} catch (Exception $e) {}
try {
    $st = $db->prepare(
        "SELECT COUNT(DISTINCT t.id) FROM teachers t
         JOIN class_subjects cs ON cs.teacher_id=t.id
         JOIN classes c ON c.id=cs.class_id
         WHERE COALESCE(c.is_montessori,0)=0 AND COALESCE(c.is_ilc,0)=0
           AND c.grade BETWEEN ? AND ?"
    );
    $st->execute([$gradeMin,$gradeMax]);
    $teacherCount = (int)$st->fetchColumn();
} catch (Exception $e) {}

$notices = array_slice(getNoticesForPortal('teacher'), 0, 3);

$recentActivity = [];
try {
    $stLog = $db->prepare('SELECT action, details, created_at FROM activity_log WHERE user_id=? ORDER BY created_at DESC LIMIT 8');
    $stLog->execute([$user['id']]);
    $recentActivity = $stLog->fetchAll();
} catch (Exception $e) {}

pageHead('Dashboard', 'secondary_wing_head');
$links = getSecondaryWingHeadLinks();
?>
<div class="portal-wrap">
<?php sidebar('secondary_wing_head', 'dashboard', $links, $user); ?>
<div class="main-area">
<?php topbar('Dashboard', $user); ?>
<div class="page-content">

<?= flashHtml() ?>

<!-- Welcome Banner -->
<div class="portal-banner mb-4" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);">
  <div class="d-flex align-items-center gap-3 flex-wrap">
    <?php
      $_av = _avatarHtml($user['id'], _initials($user['name']), 60);
      if (str_starts_with($_av, '<img')):
    ?>
    <?= str_replace('style="', 'style="border:3px solid rgba(255,255,255,.4);', $_av) ?>
    <?php else: ?>
    <div style="width:60px;height:60px;border-radius:50%;background:rgba(255,255,255,.2);
                display:flex;align-items:center;justify-content:center;
                font-size:1.5rem;font-weight:700;color:#fff;flex-shrink:0">
      <?= _initials($user['name']) ?>
    </div>
    <?php endif; ?>
    <div>
      <div style="font-size:1.25rem;font-weight:700;color:#fff">Welcome, <?= h(explode(' ', $user['name'])[0]) ?></div>
      <div style="color:rgba(255,255,255,.8);font-size:.88rem"><?= $roleLabel ?></div>
    </div>
  </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
  <div class="col-sm-4">
    <div class="sec-card text-center" style="padding:20px">
      <div style="font-size:2rem;font-weight:800;color:var(--accent)"><?= $classCount ?></div>
      <div style="font-size:.8rem;color:var(--t2)">Classes (Gr. <?= $gradeMin ?>–<?= $gradeMax ?>)</div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="sec-card text-center" style="padding:20px">
      <div style="font-size:2rem;font-weight:800;color:#059669"><?= $studentCount ?></div>
      <div style="font-size:.8rem;color:var(--t2)">Students</div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="sec-card text-center" style="padding:20px">
      <div style="font-size:2rem;font-weight:800;color:#d97706"><?= $teacherCount ?></div>
      <div style="font-size:.8rem;color:var(--t2)">Teachers Assigned</div>
    </div>
  </div>
</div>

<!-- Notices -->
<?php if ($notices): ?>
<div class="sec-card mb-4">
  <div class="sec-card-header"><i class="fas fa-bell me-2"></i>Recent Notices</div>
  <?php foreach ($notices as $n): ?>
  <div style="padding:12px 16px;border-bottom:1px solid var(--border)">
    <div class="fw-semibold" style="font-size:.88rem"><?= h($n['title']) ?></div>
    <div style="font-size:.78rem;color:var(--t2)"><?= fDate($n['created_at']) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Recent Activity -->
<?php if ($recentActivity): ?>
<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-history me-2"></i>Recent Activity</div>
  <?php foreach ($recentActivity as $a): ?>
  <div style="padding:10px 16px;border-bottom:1px solid var(--border);font-size:.83rem">
    <span class="fw-semibold"><?= h($a['action']) ?></span>
    <?php if ($a['details']): ?><span class="text-muted ms-2"><?= h($a['details']) ?></span><?php endif; ?>
    <span class="float-end text-muted" style="font-size:.75rem"><?= fDateTime($a['created_at']) ?></span>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

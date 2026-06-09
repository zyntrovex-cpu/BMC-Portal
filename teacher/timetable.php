<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user    = requireAuth('teacher');
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);
if (!$teacher) { setFlash('danger','Teacher record not found.'); redirect('/index.php'); }

// Get teacher's timetable
$st = $db->prepare(
    'SELECT tt.day, tt.period, tt.room,
            sb.name AS subject_name, sb.code AS subject_code,
            c.name AS class_name
     FROM timetable tt
     LEFT JOIN subjects sb ON tt.subject_id = sb.id
     LEFT JOIN classes c  ON tt.class_id    = c.id
     WHERE tt.teacher_id = ?
     ORDER BY FIELD(tt.day,"monday","tuesday","wednesday","thursday","friday"), tt.period'
);
$st->execute([$teacher['id']]);
$rows = $st->fetchAll();
$grid = [];
foreach ($rows as $r) { $grid[$r['day']][$r['period']] = $r; }

$days    = ['monday','tuesday','wednesday','thursday','friday'];
$periods = range(1, 8);

pageHead('Timetable', 'teacher');
$links = [
    ['href'=>'/teacher/dashboard.php','icon'=>'<i class="fas fa-home"></i>','label'=>'Dashboard','key'=>'dashboard'],
    ['href'=>'/teacher/marks.php','icon'=>'<i class="fas fa-pen-alt"></i>','label'=>'Marks','key'=>'marks'],
    ['href'=>'/teacher/attendance.php','icon'=>'<i class="fas fa-calendar-check"></i>','label'=>'Attendance','key'=>'attendance'],
    ['href'=>'/teacher/timetable.php','icon'=>'<i class="fas fa-table"></i>','label'=>'Timetable','key'=>'timetable'],
    ['href'=>'/teacher/notices.php','icon'=>'<i class="fas fa-bell"></i>','label'=>'Notices','key'=>'notices'],
    ['href'=>'/teacher/profile.php','icon'=>'<i class="fas fa-user"></i>','label'=>'Profile','key'=>'profile'],
];
?>
<div class="portal-wrap">
<?php sidebar('teacher', 'timetable', $links); ?>
<div class="main-area">
<?php topbar('My Timetable', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-table me-2"></i>Weekly Schedule — <?= h($teacher['name']) ?></div>
  <div class="table-responsive">
    <table class="table table-bordered mb-0" style="font-size:.84rem; min-width:700px;">
      <thead class="table-dark">
        <tr>
          <th style="width:80px">Period</th>
          <?php foreach ($days as $d): ?>
            <th class="text-center"><?= ucfirst($d) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($periods as $p): ?>
        <tr>
          <td class="fw-bold text-center" style="background:#f8fafc">P<?= $p ?></td>
          <?php foreach ($days as $d): ?>
            <?php $cell = $grid[$d][$p] ?? null; ?>
            <td class="text-center" style="vertical-align:middle;padding:8px 6px">
              <?php if ($cell): ?>
                <div class="fw-semibold" style="color:var(--accent);font-size:.83rem"><?= h($cell['class_name']) ?></div>
                <div style="font-size:.78rem;color:#6b7280"><?= h($cell['subject_name']) ?></div>
                <?php if ($cell['room']): ?>
                  <div style="font-size:.72rem;color:#9ca3af"><i class="fas fa-door-open me-1"></i><?= h($cell['room']) ?></div>
                <?php endif; ?>
              <?php else: ?>
                <span style="color:#d1d5db;font-size:.8rem">—</span>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

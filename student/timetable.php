<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user    = requireAuth('student');
$student = getStudentByUserId($user['id']);
if (!$student) { setFlash('danger','Student record not found.'); redirect('/index.php'); }

$db = getDB();

// Timetable
$timetable = getClassTimetable((int)$student['class_id']);
$days      = ['monday','tuesday','wednesday','thursday','friday'];
$periods   = range(1, 8);

pageHead('Timetable', 'student');
$links = [
    ['href'=>'/student/dashboard.php','icon'=>'<i class="fas fa-home"></i>','label'=>'Dashboard','key'=>'dashboard'],
    ['href'=>'/student/results.php','icon'=>'<i class="fas fa-chart-bar"></i>','label'=>'Results','key'=>'results'],
    ['href'=>'/student/attendance.php','icon'=>'<i class="fas fa-calendar-check"></i>','label'=>'Attendance','key'=>'attendance'],
    ['href'=>'/student/timetable.php','icon'=>'<i class="fas fa-table"></i>','label'=>'Timetable','key'=>'timetable'],
    ['href'=>'/student/notices.php','icon'=>'<i class="fas fa-bell"></i>','label'=>'Notices','key'=>'notices'],
    ['href'=>'/student/profile.php','icon'=>'<i class="fas fa-user"></i>','label'=>'Profile','key'=>'profile'],
];
?>
<div class="portal-wrap">
<?php sidebar('student', 'timetable', $links); ?>
<div class="main-area">
<?php topbar('Timetable', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card mb-3">
  <div class="sec-card-header">
    <span><i class="fas fa-table me-2"></i>Class Timetable — <?= h($student['class_name']) ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-bordered mb-0" style="font-size:.84rem; min-width:700px;">
      <thead class="table-dark">
        <tr>
          <th style="width:90px">Period</th>
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
            <?php $cell = $timetable[$d][$p] ?? null; ?>
            <td class="text-center" style="vertical-align:middle; padding:8px 6px;">
              <?php if ($cell): ?>
                <div class="fw-semibold" style="color:var(--accent);font-size:.83rem"><?= h($cell['subject_name']) ?></div>
                <div style="font-size:.75rem;color:#6b7280"><?= h($cell['subject_code']) ?></div>
                <?php if ($cell['teacher_name']): ?>
                  <div style="font-size:.72rem;color:#9ca3af"><?= h($cell['teacher_name']) ?></div>
                <?php endif; ?>
                <?php if ($cell['room']): ?>
                  <div style="font-size:.7rem;color:#d1d5db"><i class="fas fa-door-open me-1"></i><?= h($cell['room']) ?></div>
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

</div><!-- page-content -->
</div><!-- main-area -->
</div><!-- portal-wrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

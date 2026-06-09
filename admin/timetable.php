<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? 'save';
    $classId   = (int)$_POST['class_id'];
    $day       = strtolower(trim($_POST['day'] ?? ''));
    $period    = (int)$_POST['period'];
    $validDays = ['monday','tuesday','wednesday','thursday','friday'];

    if ($action === 'delete' && $classId && in_array($day, $validDays) && $period) {
        $db->prepare('DELETE FROM timetable WHERE class_id=? AND day=? AND period=?')->execute([$classId, $day, $period]);
        setFlash('success','Slot cleared.');
    } elseif ($classId && in_array($day, $validDays) && $period >= 1 && $period <= 8) {
        $subjectId = (int)$_POST['subject_id'];
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $room      = trim($_POST['room'] ?? '');
        $db->prepare('INSERT INTO timetable (class_id, subject_id, teacher_id, day, period, room) VALUES (?,?,?,?,?,?)
                      ON DUPLICATE KEY UPDATE subject_id=VALUES(subject_id), teacher_id=VALUES(teacher_id), room=VALUES(room)')
           ->execute([$classId, $subjectId, $teacherId ?: null, $day, $period, $room]);
        setFlash('success','Timetable updated.');
    }
    redirect('/admin/timetable.php?class_id='.$classId);
}

$classId  = (int)($_GET['class_id'] ?? 0);
$classes  = getAllClasses();
$subjects = getAllSubjects();

// Get all teachers with name
$teachersSt = $db->query('SELECT t.id, u.name, sb.name AS subject FROM teachers t JOIN users u ON t.user_id=u.id LEFT JOIN subjects sb ON t.subject_id=sb.id ORDER BY u.name');
$teachers   = $teachersSt->fetchAll();

$timetable  = [];
if ($classId) {
    $timetable = getClassTimetable($classId);
}

$days    = ['monday','tuesday','wednesday','thursday','friday'];
$periods = range(1, 8);

pageHead('Timetable', 'admin');
$links = [
    ['href'=>'/admin/dashboard.php','icon'=>'<i class="fas fa-home"></i>','label'=>'Dashboard','key'=>'dashboard'],
    ['href'=>'/admin/users.php','icon'=>'<i class="fas fa-users"></i>','label'=>'User Management','key'=>'users'],
    ['href'=>'/admin/classes.php','icon'=>'<i class="fas fa-chalkboard"></i>','label'=>'Classes & Subjects','key'=>'classes'],
    ['href'=>'/admin/teachers.php','icon'=>'<i class="fas fa-chalkboard-teacher"></i>','label'=>'Teacher Accounts','key'=>'teachers'],
    ['href'=>'/admin/notices.php','icon'=>'<i class="fas fa-bell"></i>','label'=>'Notice Board','key'=>'notices'],
    ['href'=>'/admin/timetable.php','icon'=>'<i class="fas fa-table"></i>','label'=>'Timetable','key'=>'timetable'],
    ['href'=>'/admin/results.php','icon'=>'<i class="fas fa-chart-bar"></i>','label'=>'Results','key'=>'results'],
    ['href'=>'/admin/activity.php','icon'=>'<i class="fas fa-history"></i>','label'=>'Activity Log','key'=>'activity'],
];
?>
<div class="portal-wrap">
<?php sidebar('admin', 'timetable', $links, $user); ?>
<div class="main-area">
<?php topbar('Timetable Management', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<form method="GET" class="d-flex gap-2 mb-3 align-items-end">
  <div>
    <label class="form-label fw-semibold" style="font-size:.82rem">Select Class</label>
    <select name="class_id" class="form-select form-select-sm" style="min-width:160px" onchange="this.form.submit()">
      <option value="">-- Select Class --</option>
      <?php foreach ($classes as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $classId===$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</form>

<?php if ($classId): ?>
<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-table me-2"></i>Timetable — <?php foreach($classes as $c) if($c['id']==$classId) echo h($c['name']); ?></div>
  <div class="table-responsive">
    <table class="table table-bordered mb-0" style="font-size:.82rem;min-width:750px">
      <thead class="table-dark">
        <tr>
          <th style="width:70px">Period</th>
          <?php foreach ($days as $d): ?><th class="text-center"><?= ucfirst($d) ?></th><?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($periods as $p): ?>
        <tr>
          <td class="fw-bold text-center" style="background:#f8fafc">P<?= $p ?></td>
          <?php foreach ($days as $d): ?>
            <?php $cell = $timetable[$d][$p] ?? null; ?>
            <td class="text-center" style="vertical-align:middle;padding:6px 4px;position:relative">
              <?php if ($cell): ?>
                <div style="font-size:.8rem;color:var(--accent);font-weight:600"><?= h($cell['subject_name']) ?></div>
                <?php if ($cell['teacher_name']): ?><div style="font-size:.72rem;color:#6b7280"><?= h($cell['teacher_name']) ?></div><?php endif; ?>
                <?php if ($cell['room']): ?><div style="font-size:.7rem;color:#9ca3af"><?= h($cell['room']) ?></div><?php endif; ?>
              <?php else: ?>
                <span style="color:#d1d5db;font-size:.76rem">Empty</span>
              <?php endif; ?>
              <div class="mt-1">
                <button class="btn btn-xs btn-outline-primary" style="font-size:.68rem;padding:1px 6px"
                        onclick="openEdit(<?= $classId ?>, '<?= $d ?>', <?= $p ?>, <?= $cell?json_encode($cell):0 ?>)">Edit</button>
                <?php if ($cell): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Clear this slot?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="class_id" value="<?= $classId ?>">
                  <input type="hidden" name="day" value="<?= $d ?>">
                  <input type="hidden" name="period" value="<?= $p ?>">
                  <button class="btn btn-xs btn-outline-danger" style="font-size:.68rem;padding:1px 6px">×</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title">Edit Timetable Slot</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" id="editForm">
        <input type="hidden" name="class_id" id="editClassId">
        <input type="hidden" name="day" id="editDay">
        <input type="hidden" name="period" id="editPeriod">
        <div class="modal-body">
          <p class="text-muted mb-3" id="editSlotLabel" style="font-size:.85rem"></p>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Subject*</label>
            <select name="subject_id" class="form-select" required>
              <?php foreach ($subjects as $s): ?>
                <option value="<?= $s['id'] ?>"><?= h($s['name']) ?> (<?= h($s['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Teacher</label>
            <select name="teacher_id" class="form-select" id="teacherSelect">
              <option value="">None</option>
              <?php foreach ($teachers as $t): ?>
                <option value="<?= $t['id'] ?>"><?= h($t['name']) ?> (<?= h($t['subject']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Room</label>
            <input type="text" name="room" class="form-control" id="editRoom" placeholder="e.g. Room 12">
          </div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-success">Save Slot</button></div>
      </form>
    </div>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEdit(classId, day, period, cell) {
    document.getElementById('editClassId').value = classId;
    document.getElementById('editDay').value = day;
    document.getElementById('editPeriod').value = period;
    document.getElementById('editSlotLabel').textContent = day.charAt(0).toUpperCase()+day.slice(1) + ', Period ' + period;
    const subjectSel = document.querySelector('select[name="subject_id"]');
    const teacherSel = document.getElementById('teacherSelect');
    if (cell && cell.subject_id) {
        subjectSel.value = cell.subject_id;
    } else {
        subjectSel.selectedIndex = 0;
    }
    if (cell && cell.teacher_id) {
        teacherSel.value = cell.teacher_id;
    } else {
        teacherSel.value = '';
    }
    document.getElementById('editRoom').value = cell && cell.room ? cell.room : '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
</body></html>

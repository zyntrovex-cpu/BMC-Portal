<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user    = requireAuth('teacher');
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);
if (!$teacher) { setFlash('danger','Teacher record not found.'); redirect('/index.php'); }

$tab       = $_GET['tab'] ?? 'take';
$classId   = (int)($_GET['class_id']   ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? $teacher['subject_id'] ?? 0);
$date      = $_GET['date'] ?? date('Y-m-d');

// Handle save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
    $pClassId   = (int)$_POST['class_id'];
    $pSubjectId = (int)$_POST['subject_id'];
    $pDate      = $_POST['date'] ?? date('Y-m-d');
    $attInput   = $_POST['attendance'] ?? [];
    $saved = 0;
    foreach ($attInput as $studentId => $status) {
        if (!in_array($status, ['P','A','L'])) continue;
        $db->prepare('INSERT INTO attendance (student_id, class_id, subject_id, date, status, teacher_id)
                      VALUES (?,?,?,?,?,?)
                      ON DUPLICATE KEY UPDATE status=VALUES(status), teacher_id=VALUES(teacher_id), class_id=VALUES(class_id)')
           ->execute([(int)$studentId, $pClassId, $pSubjectId, $pDate, $status, $teacher['id']]);
        $saved++;
    }
    logActivity($user['id'], 'attendance_save', "Attendance for class #$pClassId, date $pDate ($saved students)");
    setFlash('success', "Attendance saved for $saved students.");
    redirect("/teacher/attendance.php?tab=take&class_id=$pClassId&subject_id=$pSubjectId&date=$pDate");
}

// Get classes assigned to this teacher
$classesSt = $db->prepare('SELECT DISTINCT c.id, c.name FROM class_subjects cs JOIN classes c ON cs.class_id = c.id WHERE cs.teacher_id = ? ORDER BY c.grade, c.section');
$classesSt->execute([$teacher['id']]);
$assignedClasses = $classesSt->fetchAll();

// Get subjects assigned to this teacher across all their classes
$subjectsSt = $db->prepare('SELECT DISTINCT s.id, s.name FROM class_subjects cs JOIN subjects s ON cs.subject_id = s.id WHERE cs.teacher_id = ? ORDER BY s.name');
$subjectsSt->execute([$teacher['id']]);
$assignedSubjects = $subjectsSt->fetchAll();

// Get students if class selected
$students      = [];
$existingAttendance = [];
if ($classId && $tab === 'take') {
    $students = getClassStudents($classId);
    // Load existing attendance for this date/subject
    if ($subjectId && $date) {
        $attSt = $db->prepare('SELECT student_id, status FROM attendance WHERE class_id = ? AND subject_id = ? AND date = ?');
        $attSt->execute([$classId, $subjectId, $date]);
        foreach ($attSt->fetchAll() as $r) {
            $existingAttendance[$r['student_id']] = $r['status'];
        }
    }
}

// History tab
$history = [];
if ($tab === 'history') {
    $histSt = $db->prepare(
        'SELECT a.date, a.subject_id, sb.name AS subject_name, c.name AS class_name,
                COUNT(*) AS total,
                SUM(a.status="P") AS present,
                SUM(a.status="A") AS absent,
                SUM(a.status="L") AS `leave`
         FROM attendance a
         JOIN subjects sb ON a.subject_id = sb.id
         JOIN students st ON a.student_id = st.id
         JOIN classes c ON st.class_id = c.id
         WHERE a.teacher_id = ?
         GROUP BY a.date, a.subject_id
         ORDER BY a.date DESC, sb.name
         LIMIT 50'
    );
    $histSt->execute([$teacher['id']]);
    $history = $histSt->fetchAll();
}

pageHead('Attendance', 'teacher');
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
<?php sidebar('teacher', 'attendance', $links); ?>
<div class="main-area">
<?php topbar('Attendance', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link <?= $tab==='take'?'active':'' ?>" href="?tab=take">Take Attendance</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab==='history'?'active':'' ?>" href="?tab=history">History</a></li>
</ul>

<?php if ($tab === 'take'): ?>
<!-- Filter form -->
<form method="GET" class="sec-card mb-3" style="padding:14px 16px">
  <input type="hidden" name="tab" value="take">
  <div class="row g-2 align-items-end">
    <div class="col-sm-3">
      <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
      <select name="class_id" class="form-select form-select-sm" required>
        <option value="">Select class</option>
        <?php foreach ($assignedClasses as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $classId===$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-3">
      <label class="form-label fw-semibold" style="font-size:.82rem">Subject</label>
      <select name="subject_id" class="form-select form-select-sm" required>
        <option value="">Select subject</option>
        <?php foreach ($assignedSubjects as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $subjectId===$s['id']?'selected':'' ?>><?= h($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-3">
      <label class="form-label fw-semibold" style="font-size:.82rem">Date</label>
      <input type="date" name="date" class="form-control form-control-sm" value="<?= h($date) ?>" max="<?= date('Y-m-d') ?>">
    </div>
    <div class="col-sm-3">
      <button type="submit" class="btn btn-sm btn-primary w-100">Load Students</button>
    </div>
  </div>
</form>

<?php if ($classId && !empty($students)): ?>
<form method="POST">
  <input type="hidden" name="action" value="save_attendance">
  <input type="hidden" name="class_id" value="<?= $classId ?>">
  <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
  <input type="hidden" name="date" value="<?= h($date) ?>">

  <div class="sec-card mb-3">
    <div class="sec-card-header d-flex justify-content-between align-items-center">
      <span><i class="fas fa-users me-2"></i>Students — <?= date('d M Y', strtotime($date)) ?></span>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-xs btn-outline-success" onclick="setAll('P')" style="font-size:.76rem;padding:2px 10px">All Present</button>
        <button type="button" class="btn btn-xs btn-outline-danger"  onclick="setAll('A')" style="font-size:.76rem;padding:2px 10px">All Absent</button>
      </div>
    </div>

    <!-- Live summary -->
    <div class="d-flex gap-3 px-3 py-2 border-bottom" style="font-size:.83rem" id="attSummary">
      <span>Present: <strong id="countP" class="text-success">0</strong></span>
      <span>Absent: <strong id="countA" class="text-danger">0</strong></span>
      <span>Leave: <strong id="countL" class="text-warning">0</strong></span>
      <span>Total: <strong><?= count($students) ?></strong></span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover mb-0" style="font-size:.86rem">
        <thead class="table-light">
          <tr><th>#</th><th>Roll No</th><th>Name</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($students as $i => $st):
            $status = $existingAttendance[$st['id']] ?? 'P';
          ?>
          <tr id="row-<?= $st['id'] ?>" class="<?= $status==='A'?'table-danger':($status==='L'?'table-warning':'') ?>">
            <td class="text-muted"><?= $i+1 ?></td>
            <td><?= h($st['roll_no']) ?></td>
            <td class="fw-semibold"><?= h($st['name']) ?></td>
            <td>
              <div class="att-btns d-flex gap-1">
                <input type="radio" name="attendance[<?= $st['id'] ?>]" value="P" id="P<?= $st['id'] ?>" class="att-radio d-none" <?= $status==='P'?'checked':'' ?>>
                <input type="radio" name="attendance[<?= $st['id'] ?>]" value="A" id="A<?= $st['id'] ?>" class="att-radio d-none" <?= $status==='A'?'checked':'' ?>>
                <input type="radio" name="attendance[<?= $st['id'] ?>]" value="L" id="L<?= $st['id'] ?>" class="att-radio d-none" <?= $status==='L'?'checked':'' ?>>
                <label for="P<?= $st['id'] ?>" class="att-pill att-P <?= $status==='P'?'active':'' ?>">P</label>
                <label for="A<?= $st['id'] ?>" class="att-pill att-A <?= $status==='A'?'active':'' ?>">A</label>
                <label for="L<?= $st['id'] ?>" class="att-pill att-L <?= $status==='L'?'active':'' ?>">L</label>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="padding:12px 16px">
      <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Save Attendance</button>
    </div>
  </div>
</form>
<?php elseif ($classId && empty($students)): ?>
  <div class="sec-card p-3 text-muted">No students found in selected class.</div>
<?php endif; ?>

<?php else: ?>
<!-- History -->
<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-history me-2"></i>Attendance History</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.85rem">
      <thead class="table-light">
        <tr><th>Date</th><th>Class</th><th>Subject</th><th>Total</th><th class="text-success">P</th><th class="text-danger">A</th><th class="text-warning">L</th></tr>
      </thead>
      <tbody>
        <?php foreach ($history as $h2): ?>
        <tr>
          <td><?= fDate($h2['date']) ?></td>
          <td><?= h($h2['class_name']) ?></td>
          <td><?= h($h2['subject_name']) ?></td>
          <td><?= $h2['total'] ?></td>
          <td class="text-success fw-semibold"><?= $h2['present'] ?></td>
          <td class="text-danger fw-semibold"><?= $h2['absent'] ?></td>
          <td class="text-warning fw-semibold"><?= $h2['leave'] ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($history)): ?>
          <tr><td colspan="7" class="text-center text-muted py-3">No attendance records yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<style>
.att-pill { display:inline-flex; align-items:center; justify-content:center; width:32px; height:28px; border-radius:4px; border:1.5px solid #d1d5db; cursor:pointer; font-weight:700; font-size:.8rem; color:#6b7280; transition:all .12s; }
.att-pill.att-P.active, label[for^="P"]:has(+input:checked~label.att-P) { background:#dcfce7; border-color:#16a34a; color:#16a34a; }
.att-pill.att-A.active { background:#fee2e2; border-color:#dc2626; color:#dc2626; }
.att-pill.att-L.active { background:#fef9c3; border-color:#ca8a04; color:#ca8a04; }
</style>
<script>
function updatePills() {
    let P=0, A=0, L=0;
    document.querySelectorAll('.att-radio').forEach(radio => {
        if (radio.checked) {
            const row = document.getElementById('row-' + radio.name.match(/\d+/)[0]);
            // Remove all row highlight
            row.classList.remove('table-danger','table-warning');
            if (radio.value === 'A') row.classList.add('table-danger');
            if (radio.value === 'L') row.classList.add('table-warning');
            // Update active pill
            const sid = radio.name.match(/\[(\d+)\]/)[1];
            document.querySelectorAll(`label[for$="${sid}"]`).forEach(l => l.classList.remove('active'));
            document.querySelector(`label[for="${radio.value}${sid}"]`).classList.add('active');
            if (radio.value==='P') P++;
            if (radio.value==='A') A++;
            if (radio.value==='L') L++;
        }
    });
    document.getElementById('countP').textContent = P;
    document.getElementById('countA').textContent = A;
    document.getElementById('countL').textContent = L;
}
document.querySelectorAll('.att-pill').forEach(pill => {
    pill.addEventListener('click', () => setTimeout(updatePills, 10));
});
function setAll(val) {
    document.querySelectorAll('.att-radio').forEach(r => { if (r.value === val) r.checked = true; });
    setTimeout(updatePills, 10);
}
// Init
updatePills();
</script>
</body></html>

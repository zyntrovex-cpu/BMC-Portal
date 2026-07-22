<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user    = requireAuth('teacher');
requirePermission('attendance');
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);
if (!$teacher) { setFlash('danger','Teacher record not found.'); redirect('/index.php'); }

$tab       = $_GET['tab'] ?? 'take';
$classId   = (int)($_GET['class_id']   ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? $teacher['subject_id'] ?? 0);
$date      = $_GET['date'] ?? date('Y-m-d');

// Handle attendance edit request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_edit') {
    $reqTableExists = false;
    try { $db->query('SELECT 1 FROM attendance_edit_requests LIMIT 0'); $reqTableExists = true; } catch (Exception $e) {}

    if ($reqTableExists) {
        $studentId  = (int)$_POST['student_id'];
        $classId    = (int)$_POST['class_id'];
        $subjectId  = (int)$_POST['subject_id'];
        $reqDate    = $_POST['date']        ?? '';
        $oldStatus  = $_POST['old_status']  ?? '';
        $newStatus  = $_POST['new_status']  ?? '';
        $reason     = trim($_POST['reason'] ?? '');

        if ($studentId && $classId && $subjectId && $reqDate && $oldStatus && in_array($newStatus,['P','A','L']) && $reason) {
            // Check no duplicate pending request
            $ck = $db->prepare(
                'SELECT id FROM attendance_edit_requests
                 WHERE student_id=? AND class_id=? AND subject_id=? AND date=? AND status="pending"'
            );
            $ck->execute([$studentId, $classId, $subjectId, $reqDate]);
            if ($ck->fetch()) {
                setFlash('warning', 'A pending request already exists for this record.');
            } else {
                $db->prepare(
                    'INSERT INTO attendance_edit_requests
                     (teacher_id, student_id, class_id, subject_id, date, old_status, new_status, reason)
                     VALUES (?,?,?,?,?,?,?,?)'
                )->execute([$teacher['id'], $studentId, $classId, $subjectId, $reqDate, $oldStatus, $newStatus, $reason]);
                logActivity($user['id'], 'att_edit_request', "Requested attendance edit for student #$studentId on $reqDate");
                setFlash('success', 'Edit request submitted for VP approval.');
            }
        } else {
            setFlash('danger', 'All fields are required.');
        }
    } else {
        setFlash('warning', 'Attendance edit requests table not set up yet.');
    }
    redirect('/teacher/attendance.php?tab=requests');
}

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

// My edit requests tab
$myRequests = [];
$reqTableOk = false;
if ($tab === 'requests') {
    try {
        $db->query('SELECT 1 FROM attendance_edit_requests LIMIT 0');
        $reqTableOk = true;
        $rSt = $db->prepare(
            'SELECT aer.*, su.name AS student_name, s.roll_no,
                    c.name AS class_name, sb.name AS subject_name,
                    vu.name AS reviewed_by_name
             FROM attendance_edit_requests aer
             JOIN students s ON s.id = aer.student_id
             JOIN users su   ON su.id = s.user_id
             LEFT JOIN classes c ON c.id = aer.class_id
             LEFT JOIN subjects sb ON sb.id = aer.subject_id
             LEFT JOIN users vu ON vu.id = aer.reviewed_by
             WHERE aer.teacher_id = ?
             ORDER BY aer.created_at DESC
             LIMIT 50'
        );
        $rSt->execute([$teacher['id']]);
        $myRequests = $rSt->fetchAll();
    } catch (Exception $e) {}
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
$links = getTeacherLinks();
?>
<div class="portal-wrap">
<?php sidebar('teacher', 'attendance', $links, $user); ?>
<div class="main-area">
<?php topbar('Attendance', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link <?= $tab==='take'?'active':'' ?>" href="?tab=take">Take Attendance</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab==='history'?'active':'' ?>" href="?tab=history">History</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab==='requests'?'active':'' ?>" href="?tab=requests">Edit Requests</a></li>
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

<?php elseif ($tab === 'history'): ?>
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

<?php else: ?>
<!-- Edit Requests Tab -->
<?php if (!$reqTableOk): ?>
<div class="alert alert-warning">Attendance edit requests table not set up yet. Ask admin to run the features migration.</div>
<?php else: ?>

<!-- Request Form -->
<div class="sec-card mb-3">
  <div class="sec-card-header d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#reqForm" style="cursor:pointer">
    <span><i class="fas fa-plus me-2"></i>Submit Correction Request</span>
    <i class="fas fa-chevron-down" style="font-size:.8rem"></i>
  </div>
  <div id="reqForm" class="collapse">
    <div style="padding:16px">
      <form method="POST">
        <input type="hidden" name="action" value="request_edit">
        <div class="row g-2">
          <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Class <span class="text-danger">*</span></label>
            <select name="class_id" class="form-select form-select-sm" id="reqClassSel" required>
              <option value="">Select…</option>
              <?php foreach ($assignedClasses as $ac): ?>
              <option value="<?= $ac['id'] ?>"><?= h($ac['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Subject <span class="text-danger">*</span></label>
            <select name="subject_id" class="form-select form-select-sm" required>
              <option value="">Select…</option>
              <?php foreach ($assignedSubjects as $as): ?>
              <option value="<?= $as['id'] ?>"><?= h($as['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Date <span class="text-danger">*</span></label>
            <input type="date" name="date" class="form-control form-control-sm" max="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Student ID (db) <span class="text-danger">*</span></label>
            <input type="number" name="student_id" class="form-control form-control-sm" placeholder="Student DB id" required>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Current Status <span class="text-danger">*</span></label>
            <select name="old_status" class="form-select form-select-sm" required>
              <option value="P">Present</option>
              <option value="A">Absent</option>
              <option value="L">Leave</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Correct Status <span class="text-danger">*</span></label>
            <select name="new_status" class="form-select form-select-sm" required>
              <option value="P">Present</option>
              <option value="A">Absent</option>
              <option value="L">Leave</option>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label fw-semibold" style="font-size:.82rem">Reason <span class="text-danger">*</span></label>
            <input type="text" name="reason" class="form-control form-control-sm" required placeholder="Explain why the correction is needed…">
          </div>
          <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn btn-sm btn-primary px-4">
              <i class="fas fa-paper-plane me-1"></i>Submit Request
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- My Requests List -->
<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-list me-2"></i>My Edit Requests</div>
  <?php if (empty($myRequests)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2);font-size:.85rem">No requests submitted yet.</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr><th>Student</th><th>Class</th><th>Subject</th><th>Date</th><th>Old</th><th>New</th><th>Reason</th><th>Status</th><th>Reviewed</th></tr>
      </thead>
      <tbody>
        <?php foreach ($myRequests as $r):
          $statusClass = match($r['status']) { 'approved'=>'success','rejected'=>'danger',default=>'warning' };
          $attL = ['P'=>'Present','A'=>'Absent','L'=>'Leave'];
        ?>
        <tr>
          <td><?= h($r['student_name']) ?><div style="font-size:.74rem;color:var(--t3)"><?= h($r['roll_no']) ?></div></td>
          <td><?= h($r['class_name'] ?? '—') ?></td>
          <td><?= h($r['subject_name'] ?? '—') ?></td>
          <td><?= fDate($r['date']) ?></td>
          <td><span class="badge bg-<?= $r['old_status']==='P'?'success':($r['old_status']==='A'?'danger':'warning') ?>"><?= $attL[$r['old_status']] ?? $r['old_status'] ?></span></td>
          <td><span class="badge bg-<?= $r['new_status']==='P'?'success':($r['new_status']==='A'?'danger':'warning') ?>"><?= $attL[$r['new_status']] ?? $r['new_status'] ?></span></td>
          <td style="max-width:160px"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($r['reason']) ?>"><?= h($r['reason']) ?></div></td>
          <td><span class="badge bg-<?= $statusClass ?>"><?= ucfirst($r['status']) ?></span></td>
          <td><?= $r['reviewed_by_name'] ? h($r['reviewed_by_name']).'<br><small style="color:var(--t3)">'.fDate($r['reviewed_at']).'</small>' : '<span class="text-muted">—</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

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

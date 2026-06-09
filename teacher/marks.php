<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user    = requireAuth('teacher');
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);
if (!$teacher) { setFlash('danger','Teacher record not found.'); redirect('/index.php'); }

$tab = $_GET['tab'] ?? 'assessments';
$assessmentId = (int)($_GET['assessment_id'] ?? 0);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_assessment') {
        $classId   = (int)$_POST['class_id'];
        $subjectId = $teacher['id'] ? (int)($_POST['subject_id'] ?? $teacher['subject_id'] ?? 0) : 0;
        $title     = trim($_POST['title'] ?? '');
        $type      = $_POST['type'] ?? 'Quiz';
        $maxMarks  = (float)$_POST['max_marks'];
        $weight    = (float)$_POST['weight'];
        $date      = $_POST['date'] ?? date('Y-m-d');
        $validTypes = ['Quiz','Assignment','Mid Term','Final Term','Practical'];
        if (!in_array($type, $validTypes)) $type = 'Quiz';
        if ($classId && $subjectId && $title && $maxMarks) {
            $db->prepare('INSERT INTO assessments (class_id, subject_id, teacher_id, title, name, type, max_marks, weight, date)
                          VALUES (?,?,?,?,?,?,?,?,?)')
               ->execute([$classId, $subjectId, $teacher['id'], $title, $title, $type, $maxMarks, $weight, $date]);
            logActivity($user['id'], 'assessment_create', "Created: $title");
            setFlash('success', 'Assessment created.');
        } else {
            setFlash('danger', 'All fields required.');
        }
        redirect('/teacher/marks.php?tab=assessments');
    }

    if ($action === 'delete_assessment') {
        $id = (int)$_POST['assessment_id'];
        $check = $db->prepare('SELECT id FROM assessments WHERE id = ? AND teacher_id = ?');
        $check->execute([$id, $teacher['id']]);
        if ($check->fetch()) {
            $db->prepare('DELETE FROM marks WHERE assessment_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM assessments WHERE id = ?')->execute([$id]);
            setFlash('success','Assessment deleted.');
        }
        redirect('/teacher/marks.php?tab=assessments');
    }

    if ($action === 'save_marks') {
        $aId = (int)$_POST['assessment_id'];
        $marksInput = $_POST['marks'] ?? [];
        $saved = 0;
        foreach ($marksInput as $studentId => $row) {
            $obtained = $row['marks_obtained'] !== '' ? (float)$row['marks_obtained'] : null;
            if ($obtained === null) continue;
            $db->prepare('INSERT INTO marks (assessment_id, student_id, marks_obtained, remarks)
                          VALUES (?,?,?,?)
                          ON DUPLICATE KEY UPDATE marks_obtained=VALUES(marks_obtained), remarks=VALUES(remarks)')
               ->execute([$aId, (int)$studentId, $obtained, trim($row['remarks'] ?? '')]);
            $saved++;
        }
        logActivity($user['id'], 'marks_save', "Saved marks for assessment #$aId ($saved students)");
        setFlash('success', "Marks saved for $saved students.");
        redirect('/teacher/marks.php?tab=marks&assessment_id='.$aId);
    }
}

// Get assessments for this teacher
$assessmentsSt = $db->prepare(
    'SELECT a.*, c.name AS class_name, s.name AS subject_name,
            (SELECT COUNT(*) FROM marks m WHERE m.assessment_id = a.id) AS marks_entered
     FROM assessments a
     JOIN classes c ON a.class_id = c.id
     JOIN subjects s ON a.subject_id = s.id
     WHERE a.teacher_id = ?
     ORDER BY a.date DESC'
);
$assessmentsSt->execute([$teacher['id']]);
$assessments = $assessmentsSt->fetchAll();

// For marks entry: get assessment details and students
$currentAssessment = null;
$students          = [];
$existingMarks     = [];
if ($tab === 'marks' && $assessmentId) {
    $aSt = $db->prepare('SELECT a.*, c.name AS class_name, s.name AS subject_name FROM assessments a JOIN classes c ON a.class_id=c.id JOIN subjects s ON a.subject_id=s.id WHERE a.id = ? AND a.teacher_id = ?');
    $aSt->execute([$assessmentId, $teacher['id']]);
    $currentAssessment = $aSt->fetch();

    if ($currentAssessment) {
        $students = getClassStudents((int)$currentAssessment['class_id']);
        $mSt = $db->prepare('SELECT student_id, marks_obtained, remarks FROM marks WHERE assessment_id = ?');
        $mSt->execute([$assessmentId]);
        foreach ($mSt->fetchAll() as $m) {
            $existingMarks[$m['student_id']] = $m;
        }
    }
}

// Classes assigned to this teacher
$classesSt = $db->prepare('SELECT DISTINCT c.id, c.name FROM class_subjects cs JOIN classes c ON cs.class_id = c.id WHERE cs.teacher_id = ? ORDER BY c.grade, c.section');
$classesSt->execute([$teacher['id']]);
$assignedClasses = $classesSt->fetchAll();

pageHead('Marks', 'teacher');
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
<?php sidebar('teacher', 'marks', $links); ?>
<div class="main-area">
<?php topbar('Marks & Assessments', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link <?= $tab==='assessments'?'active':'' ?>" href="?tab=assessments">Manage Assessments</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab==='marks'?'active':'' ?>" href="?tab=marks">Enter Marks</a></li>
</ul>

<?php if ($tab === 'assessments'): ?>
<!-- Create Assessment -->
<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-plus me-2"></i>Create New Assessment</div>
  <div style="padding:16px">
    <form method="POST">
      <input type="hidden" name="action" value="create_assessment">
      <div class="row g-2">
        <div class="col-md-3">
          <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
          <select name="class_id" class="form-select form-select-sm" required>
            <option value="">Select class</option>
            <?php foreach ($assignedClasses as $c): ?>
              <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold" style="font-size:.82rem">Subject</label>
          <input type="hidden" name="subject_id" value="<?= (int)$teacher['subject_id'] ?>">
          <input type="text" class="form-control form-control-sm" value="<?= h($teacher['subject_name']) ?>" disabled>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold" style="font-size:.82rem">Title</label>
          <input type="text" name="title" class="form-control form-control-sm" placeholder="e.g. Quiz 1" required>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Type</label>
          <select name="type" class="form-select form-select-sm">
            <?php foreach (['Quiz','Assignment','Mid Term','Final Term','Practical'] as $t): ?>
              <option><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Max Marks</label>
          <input type="number" name="max_marks" class="form-control form-control-sm" min="1" max="200" required>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Weight %</label>
          <input type="number" name="weight" class="form-control form-control-sm" min="0" max="100" value="10">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold" style="font-size:.82rem">Date</label>
          <input type="date" name="date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="submit" class="btn btn-sm btn-success w-100">Create Assessment</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- List Assessments -->
<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-list me-2"></i>My Assessments</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.85rem">
      <thead class="table-light">
        <tr><th>Title</th><th>Type</th><th>Class</th><th>Subject</th><th>Max</th><th>Weight</th><th>Date</th><th>Marks</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($assessments as $a): ?>
        <tr>
          <td class="fw-semibold"><?= h($a['title']) ?></td>
          <td><span class="badge bg-secondary"><?= h($a['type']) ?></span></td>
          <td><?= h($a['class_name']) ?></td>
          <td><?= h($a['subject_name']) ?></td>
          <td><?= $a['max_marks'] ?></td>
          <td><?= $a['weight'] ?>%</td>
          <td><?= fDate($a['date']) ?></td>
          <td><?= $a['marks_entered'] ?> entered</td>
          <td>
            <a href="?tab=marks&assessment_id=<?= $a['id'] ?>" class="btn btn-xs btn-outline-primary" style="font-size:.76rem;padding:2px 8px">Enter Marks</a>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete assessment and all marks?')">
              <input type="hidden" name="action" value="delete_assessment">
              <input type="hidden" name="assessment_id" value="<?= $a['id'] ?>">
              <button class="btn btn-xs btn-outline-danger" style="font-size:.76rem;padding:2px 8px">Del</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($assessments)): ?>
        <tr><td colspan="9" class="text-center text-muted py-3">No assessments yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'marks'): ?>
<!-- Enter Marks tab -->
<?php if (!$assessmentId || !$currentAssessment): ?>
  <div class="sec-card p-4">
    <p class="text-muted mb-3">Select an assessment to enter marks:</p>
    <div class="list-group">
      <?php foreach ($assessments as $a): ?>
        <a href="?tab=marks&assessment_id=<?= $a['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between">
          <div><strong><?= h($a['title']) ?></strong> — <?= h($a['class_name']) ?> / <?= h($a['subject_name']) ?></div>
          <span class="text-muted" style="font-size:.82rem"><?= fDate($a['date']) ?> | Max: <?= $a['max_marks'] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php else: ?>
  <div class="sec-card mb-3">
    <div class="sec-card-header d-flex justify-content-between">
      <span><i class="fas fa-pen-alt me-2"></i><?= h($currentAssessment['title']) ?> — <?= h($currentAssessment['class_name']) ?> / <?= h($currentAssessment['subject_name']) ?></span>
      <span class="text-muted" style="font-size:.82rem">Max Marks: <?= $currentAssessment['max_marks'] ?> | <?= h($currentAssessment['type']) ?></span>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="save_marks">
      <input type="hidden" name="assessment_id" value="<?= $assessmentId ?>">
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.85rem">
          <thead class="table-light">
            <tr><th>#</th><th>Roll No</th><th>Name</th><th style="width:130px">Marks (/ <?= $currentAssessment['max_marks'] ?>)</th><th style="width:80px">%</th><th style="width:80px">Grade</th><th>Remarks</th></tr>
          </thead>
          <tbody id="marksBody">
            <?php foreach ($students as $i => $st):
              $existing = $existingMarks[$st['id']] ?? null;
              $obtained = $existing ? $existing['marks_obtained'] : '';
              $remarks  = $existing ? $existing['remarks'] : '';
              $pct      = ($obtained !== '' && $currentAssessment['max_marks'] > 0) ? round((float)$obtained / $currentAssessment['max_marks'] * 100, 1) : '';
              $grade    = $pct !== '' ? getGrade((float)$pct) : null;
            ?>
            <tr>
              <td class="text-muted"><?= $i+1 ?></td>
              <td><?= h($st['roll_no']) ?></td>
              <td><?= h($st['name']) ?></td>
              <td>
                <input type="number" name="marks[<?= $st['id'] ?>][marks_obtained]"
                       class="form-control form-control-sm marks-input"
                       data-max="<?= $currentAssessment['max_marks'] ?>"
                       value="<?= h($obtained) ?>"
                       min="0" max="<?= $currentAssessment['max_marks'] ?>" step="0.5"
                       oninput="calcRow(this)">
              </td>
              <td class="pct-cell"><?= $pct !== '' ? $pct.'%' : '—' ?></td>
              <td class="grade-cell"><?= $grade ? '<span class="grade '.$grade['class'].'">'.$grade['label'].'</span>' : '—' ?></td>
              <td><input type="text" name="marks[<?= $st['id'] ?>][remarks]" class="form-control form-control-sm" value="<?= h($remarks) ?>" placeholder="Optional"></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="padding:12px 16px">
        <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Save Marks</button>
        <a href="?tab=marks" class="btn btn-outline-secondary ms-2">Back</a>
      </div>
    </form>
  </div>
<?php endif; ?>
<?php endif; ?>

</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function calcRow(input) {
    const max = parseFloat(input.dataset.max) || 0;
    const val = parseFloat(input.value);
    const row = input.closest('tr');
    const pctCell   = row.querySelector('.pct-cell');
    const gradeCell = row.querySelector('.grade-cell');
    if (isNaN(val) || max === 0) {
        pctCell.textContent  = '—';
        gradeCell.innerHTML  = '—';
        return;
    }
    const pct = Math.round(val / max * 1000) / 10;
    pctCell.textContent = pct + '%';
    const g = getGrade(pct);
    gradeCell.innerHTML = '<span class="grade ' + g.cls + '">' + g.label + '</span>';
}
function getGrade(pct) {
    if (pct >= 90) return {label:'A+', cls:'grade-aplus'};
    if (pct >= 80) return {label:'A',  cls:'grade-a'};
    if (pct >= 70) return {label:'B+', cls:'grade-bplus'};
    if (pct >= 60) return {label:'B',  cls:'grade-b'};
    if (pct >= 50) return {label:'C',  cls:'grade-c'};
    if (pct >= 40) return {label:'D',  cls:'grade-d'};
    return {label:'F', cls:'grade-f'};
}
</script>
</body></html>

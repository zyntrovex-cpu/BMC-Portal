<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('examination_head');
requirePermission('eh_marks');
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);
if (!$teacher) { setFlash('danger', 'Teacher record not found. Please contact admin.'); redirect('/portal/exam-head/dashboard.php'); }

$tab          = $_GET['tab'] ?? 'assessments';
$assessmentId = (int)($_GET['assessment_id'] ?? 0);

function _gradeInfo(float $pct): array {
    if ($pct >= 90) return ['label'=>'A+','class'=>'grade-aplus'];
    if ($pct >= 80) return ['label'=>'A', 'class'=>'grade-a'];
    if ($pct >= 70) return ['label'=>'B+','class'=>'grade-bplus'];
    if ($pct >= 60) return ['label'=>'B', 'class'=>'grade-b'];
    if ($pct >= 50) return ['label'=>'C', 'class'=>'grade-c'];
    if ($pct >= 40) return ['label'=>'D', 'class'=>'grade-d'];
    return ['label'=>'F','class'=>'grade-f'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_assessment') {
        $classId   = (int)$_POST['class_id'];
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $title     = trim($_POST['title'] ?? '');
        $type      = trim($_POST['type']  ?? '');
        if ($type === '') $type = 'Quiz';
        if (strlen($type) > 100) $type = substr($type, 0, 100);
        $maxMarks  = (float)$_POST['max_marks'];
        $weight    = (float)($_POST['weight'] ?? 10);
        $date      = $_POST['date'] ?? date('Y-m-d');
        if ($classId && $subjectId && $title && $maxMarks) {
            $db->prepare(
                'INSERT INTO assessments (class_id, subject_id, teacher_id, title, name, type, max_marks, weight, date)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([$classId, $subjectId, $teacher['id'], $title, $title, $type, $maxMarks, $weight, $date]);
            logActivity($user['id'], 'assessment_create', "Exam Head created: $title");
            setFlash('success', 'Assessment created.');
        } else {
            setFlash('danger', 'All required fields must be filled.');
        }
        redirect('/portal/exam-head/marks.php?tab=assessments');
    }

    if ($action === 'delete_assessment') {
        $id  = (int)$_POST['assessment_id'];
        $chk = $db->prepare('SELECT id FROM assessments WHERE id=? AND teacher_id=?');
        $chk->execute([$id, $teacher['id']]);
        if ($chk->fetch()) {
            $db->prepare('DELETE FROM marks WHERE assessment_id=?')->execute([$id]);
            try { $db->prepare('DELETE FROM marks_permission_requests WHERE assessment_id=?')->execute([$id]); } catch (Exception $e) {}
            $db->prepare('DELETE FROM assessments WHERE id=?')->execute([$id]);
            logActivity($user['id'], 'assessment_delete', "Exam Head deleted assessment #$id");
            setFlash('success', 'Assessment deleted.');
        }
        redirect('/portal/exam-head/marks.php?tab=assessments');
    }

    if ($action === 'save_marks') {
        $aId = (int)$_POST['assessment_id'];
        // Verify exam head owns this assessment via teacher record
        $chk = $db->prepare('SELECT id FROM assessments WHERE id=? AND teacher_id=?');
        $chk->execute([$aId, $teacher['id']]);
        if (!$chk->fetch()) {
            setFlash('danger', 'Assessment not found.');
            redirect('/portal/exam-head/marks.php?tab=marks&assessment_id=' . $aId);
        }

        $marksInput = $_POST['marks'] ?? [];
        $saved = 0;
        foreach ($marksInput as $studentId => $row) {
            $obtained = ($row['marks_obtained'] !== '') ? (float)$row['marks_obtained'] : null;
            if ($obtained === null) continue;
            $db->prepare(
                'INSERT INTO marks (assessment_id, student_id, marks_obtained, remarks)
                 VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE marks_obtained=VALUES(marks_obtained), remarks=VALUES(remarks)'
            )->execute([$aId, (int)$studentId, $obtained, trim($row['remarks'] ?? '')]);
            $saved++;
        }
        logActivity($user['id'], 'marks_save', "Exam Head saved marks for assessment #$aId ($saved students)");
        setFlash('success', "Marks saved for $saved student(s).");
        redirect('/portal/exam-head/marks.php?tab=marks&assessment_id=' . $aId);
    }
}

// Assessments created by this exam head's teacher record
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

// Load current assessment + students for marks tab
$currentAssessment = null;
$students          = [];
$existingMarks     = [];

if ($tab === 'marks' && $assessmentId) {
    $aSt = $db->prepare(
        'SELECT a.*, c.name AS class_name, s.name AS subject_name
         FROM assessments a
         JOIN classes c ON a.class_id=c.id
         JOIN subjects s ON a.subject_id=s.id
         WHERE a.id=? AND a.teacher_id=?'
    );
    $aSt->execute([$assessmentId, $teacher['id']]);
    $currentAssessment = $aSt->fetch();

    if ($currentAssessment) {
        $students = getClassStudents((int)$currentAssessment['class_id']);
        $mSt = $db->prepare('SELECT student_id, marks_obtained, remarks FROM marks WHERE assessment_id=?');
        $mSt->execute([$assessmentId]);
        foreach ($mSt->fetchAll() as $m) {
            $existingMarks[$m['student_id']] = $m;
        }
    }
}

// All main campus classes and subjects
$allClasses = $db->query(
    "SELECT id, name, grade, section FROM classes
     WHERE COALESCE(is_ilc,0)=0 AND COALESCE(is_montessori,0)=0
     ORDER BY grade, section"
)->fetchAll();

$allSubjects = $db->query("SELECT id, name FROM subjects ORDER BY name")->fetchAll();

pageHead('Marks', 'examination_head');
$links = getExamHeadLinks();
?>
<div class="portal-wrap">
<?php sidebar('examination_head', 'marks', $links, $user); ?>
<div class="main-area">
<?php topbar('Assessments & Marks — Main Campus', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link <?= $tab==='assessments'?'active':'' ?>" href="?tab=assessments">Manage Assessments</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab==='marks'?'active':'' ?>" href="?tab=marks">Enter Marks</a></li>
</ul>

<?php if ($tab === 'assessments'): ?>

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
            <?php foreach ($allClasses as $c): ?>
              <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold" style="font-size:.82rem">Subject</label>
          <select name="subject_id" class="form-select form-select-sm" required>
            <option value="">Select subject</option>
            <?php foreach ($allSubjects as $s): ?>
              <option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold" style="font-size:.82rem">Title</label>
          <input type="text" name="title" class="form-control form-control-sm" placeholder="e.g. Mid-Term Exam Results" required>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Assessment Type</label>
          <input type="text" name="type" class="form-control form-control-sm" placeholder="e.g. Exam, Quiz…" maxlength="100" required>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Max Marks</label>
          <input type="number" name="max_marks" class="form-control form-control-sm" min="1" max="1000" required>
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
          <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-plus me-1"></i>Create Assessment</button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-list me-2"></i>Assessments</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr><th>Title</th><th>Type</th><th>Class</th><th>Subject</th><th>Max</th><th>Weight</th><th>Date</th><th>Marks</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($assessments as $a): ?>
        <tr>
          <td class="fw-semibold"><?= h($a['title'] ?: $a['name']) ?></td>
          <td><span class="badge bg-secondary" style="font-size:.74rem"><?= h($a['type']) ?></span></td>
          <td><?= h($a['class_name']) ?></td>
          <td><?= h($a['subject_name']) ?></td>
          <td><?= $a['max_marks'] ?></td>
          <td><?= $a['weight'] ?>%</td>
          <td style="white-space:nowrap"><?= fDate($a['date']) ?></td>
          <td><?= $a['marks_entered'] ?> entered</td>
          <td style="white-space:nowrap">
            <a href="?tab=marks&assessment_id=<?= $a['id'] ?>" class="btn btn-xs btn-outline-primary" style="font-size:.75rem;padding:2px 8px">Enter Marks</a>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this assessment and all its marks?')">
              <input type="hidden" name="action" value="delete_assessment">
              <input type="hidden" name="assessment_id" value="<?= $a['id'] ?>">
              <button class="btn btn-xs btn-outline-danger" style="font-size:.75rem;padding:2px 8px">Del</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($assessments)): ?>
        <tr><td colspan="9" class="text-center text-muted py-3">No assessments created yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'marks'): ?>

<?php if (!$assessmentId || !$currentAssessment): ?>
<div class="sec-card p-4">
  <p class="text-muted mb-3 fw-semibold">Select an assessment to enter marks:</p>
  <div class="list-group">
    <?php foreach ($assessments as $a): ?>
    <a href="?tab=marks&assessment_id=<?= $a['id'] ?>"
       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <strong><?= h($a['title'] ?: $a['name']) ?></strong>
        <span class="text-muted ms-2" style="font-size:.82rem">— <?= h($a['class_name']) ?> / <?= h($a['subject_name']) ?></span>
      </div>
      <span class="text-muted" style="font-size:.8rem"><?= fDate($a['date']) ?> &nbsp;|&nbsp; Max: <?= $a['max_marks'] ?></span>
    </a>
    <?php endforeach; ?>
    <?php if (empty($assessments)): ?>
    <div class="text-center text-muted py-4" style="font-size:.88rem">
      No assessments yet. <a href="?tab=assessments">Create one first</a>.
    </div>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>

<div class="sec-card mb-3">
  <div class="sec-card-header d-flex justify-content-between flex-wrap gap-2">
    <span>
      <i class="fas fa-clipboard-list me-2"></i>
      <?= h($currentAssessment['title'] ?: $currentAssessment['name']) ?>
      <span class="text-muted fw-normal ms-2" style="font-size:.82rem">
        <?= h($currentAssessment['class_name']) ?> / <?= h($currentAssessment['subject_name']) ?>
      </span>
    </span>
    <span class="text-muted" style="font-size:.81rem">
      <?= h($currentAssessment['type']) ?> &nbsp;|&nbsp; Max: <?= $currentAssessment['max_marks'] ?>
      &nbsp;|&nbsp; <?= fDate($currentAssessment['date']) ?>
    </span>
  </div>
  <div style="padding:10px 20px;background:#f0fdf4;border-top:2px solid #bbf7d0;display:flex;align-items:center;gap:10px">
    <i class="fas fa-check-circle" style="color:#16a34a;font-size:1.2rem"></i>
    <span class="fw-semibold" style="color:#166534;font-size:.9rem">
      <span class="badge bg-success me-2" style="font-size:.78rem">Marks Entry Enabled</span>
      Examination Head — direct marks entry
    </span>
    <span class="ms-auto"><a href="?tab=marks" class="btn btn-xs btn-outline-secondary" style="font-size:.76rem">← Back to list</a></span>
  </div>
</div>

<div class="sec-card mb-3">
  <div class="sec-card-header">
    <i class="fas fa-pen-alt me-2"></i>Enter Marks — <?= h($currentAssessment['class_name']) ?>
    <span class="text-muted fw-normal ms-2" style="font-size:.8rem"><?= count($students) ?> student(s)</span>
  </div>
  <form method="POST">
    <input type="hidden" name="action" value="save_marks">
    <input type="hidden" name="assessment_id" value="<?= $assessmentId ?>">
    <div class="table-responsive">
      <table class="table table-hover mb-0" style="font-size:.85rem">
        <thead class="table-light">
          <tr>
            <th>#</th><th>Roll No</th><th>Name</th>
            <th style="width:140px">Marks (/ <?= $currentAssessment['max_marks'] ?>)</th>
            <th style="width:70px">%</th><th style="width:70px">Grade</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $i => $st):
            $existing = $existingMarks[$st['id']] ?? null;
            $obtained = $existing ? $existing['marks_obtained'] : '';
            $remarks  = $existing ? $existing['remarks'] : '';
            $pct      = ($obtained !== '' && $currentAssessment['max_marks'] > 0)
                        ? round((float)$obtained / $currentAssessment['max_marks'] * 100, 1) : '';
            $grade    = ($pct !== '') ? _gradeInfo((float)$pct) : null;
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
            <td>
              <input type="text" name="marks[<?= $st['id'] ?>][remarks]"
                     class="form-control form-control-sm"
                     value="<?= h($remarks) ?>" placeholder="Optional">
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($students)): ?>
          <tr><td colspan="7" class="text-center text-muted py-3">No students found in this class.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if (!empty($students)): ?>
    <div style="padding:12px 16px;background:#f9fafb;border-top:1px solid #e5e7eb">
      <button type="submit" class="btn btn-success btn-sm">
        <i class="fas fa-save me-1"></i>Save Marks
      </button>
      <a href="?tab=marks" class="btn btn-outline-secondary btn-sm ms-2">← Back</a>
    </div>
    <?php endif; ?>
  </form>
</div>

<?php endif; // assessment selected ?>
<?php endif; // marks tab ?>

</div></div></div>
<script>
function calcRow(input) {
    const max = parseFloat(input.dataset.max) || 0;
    const val = parseFloat(input.value);
    const row = input.closest('tr');
    const pctCell   = row.querySelector('.pct-cell');
    const gradeCell = row.querySelector('.grade-cell');
    if (isNaN(val) || max === 0) {
        pctCell.textContent = '—';
        gradeCell.innerHTML = '—';
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
<?php pageFooter(); ?>

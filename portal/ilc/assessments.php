<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp');
$db   = getDB();

$tab          = $_GET['tab']           ?? 'assessments';
$assessmentId = (int)($_GET['assessment_id'] ?? 0);

// Table availability
$tableExists = false;
try { $db->query('SELECT 1 FROM ilc_student_assessments LIMIT 1'); $tableExists = true; } catch (Exception $e) {}

// ── POST handler ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_assessment') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $title     = trim($_POST['title']      ?? '');
        $type      = trim($_POST['type']       ?? '') ?: 'Quiz';
        $maxM      = max(0.5, (float)($_POST['max_marks'] ?? 100));
        $weight    = min(100, max(0, (float)($_POST['weight'] ?? 0)));
        $date      = $_POST['date'] ?: null;

        if ($studentId && $title) {
            $db->prepare(
                'INSERT INTO ilc_student_assessments
                 (student_id, title, type, max_marks, weight, date, created_by)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([$studentId, $title, $type, $maxM, $weight, $date, $user['id']]);
            logActivity($user['id'], 'ilc_assess_create', "Created ILC assessment '$title'");
            setFlash('success', 'Assessment created.');
        } else {
            setFlash('danger', 'Student and title are required.');
        }
        redirect('/portal/ilc/assessments.php?tab=assessments');
    }

    if ($action === 'delete_assessment') {
        $id = (int)($_POST['assessment_id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM ilc_student_assessments WHERE id=?')->execute([$id]);
            logActivity($user['id'], 'ilc_assess_delete', "Deleted ILC assessment #$id");
            setFlash('success', 'Assessment deleted.');
        }
        redirect('/portal/ilc/assessments.php?tab=assessments');
    }

    if ($action === 'save_marks') {
        $id      = (int)($_POST['assessment_id'] ?? 0);
        $obtRaw  = $_POST['marks_obtained'] ?? '';
        $remarks = trim($_POST['remarks']   ?? '');

        if ($id) {
            // Fetch max_marks for validation
            $row = $db->prepare('SELECT max_marks FROM ilc_student_assessments WHERE id=?');
            $row->execute([$id]);
            $aRow = $row->fetch();
            $maxM = $aRow ? (float)$aRow['max_marks'] : PHP_INT_MAX;

            $obtained = ($obtRaw !== '') ? min($maxM, max(0, (float)$obtRaw)) : null;
            $db->prepare(
                'UPDATE ilc_student_assessments SET marks_obtained=?, remarks=? WHERE id=?'
            )->execute([$obtained, $remarks ?: null, $id]);
            logActivity($user['id'], 'ilc_marks_save', "Saved marks for ILC assessment #$id");
            setFlash('success', 'Marks saved.');
        }
        redirect('/portal/ilc/assessments.php?tab=marks&assessment_id='.$id);
    }
}

// ── Fetch ILC students ────────────────────────────────────────────
$students = $db->query(
    'SELECT s.id, u.name, s.roll_no, c.name AS class_name
     FROM students s JOIN users u ON u.id = s.user_id JOIN classes c ON c.id = s.class_id
     WHERE c.is_ilc = 1 ORDER BY c.name, u.name'
)->fetchAll();
$studentsById = [];
foreach ($students as $s) { $studentsById[$s['id']] = $s; }

// ── Fetch all ILC assessments (for lists) ─────────────────────────
$assessments = [];
if ($tableExists) {
    $assessments = $db->query(
        'SELECT a.*, u.name AS student_name, s.roll_no
         FROM ilc_student_assessments a
         JOIN students s ON s.id = a.student_id
         JOIN users u ON u.id = s.user_id
         ORDER BY a.date DESC, a.id DESC'
    )->fetchAll();
}

// ── Current assessment for marks entry ───────────────────────────
$currentAssessment = null;
if ($tab === 'marks' && $assessmentId && $tableExists) {
    $caSt = $db->prepare(
        'SELECT a.*, u.name AS student_name, s.roll_no, c.name AS class_name
         FROM ilc_student_assessments a
         JOIN students s ON s.id = a.student_id
         JOIN users u ON u.id = s.user_id
         JOIN classes c ON c.id = s.class_id
         WHERE a.id = ?'
    );
    $caSt->execute([$assessmentId]);
    $currentAssessment = $caSt->fetch();
}

$typeColors = [
    'Quiz'       => 'bg-info text-dark',
    'Assignment' => 'bg-secondary',
    'Midterm'    => 'bg-warning text-dark',
    'Mid Term'   => 'bg-warning text-dark',
    'Final Term' => 'bg-danger',
    'Test'       => 'bg-primary',
    'Practical'  => 'bg-success',
];
$commonTypes = ['Quiz', 'Assignment', 'Midterm', 'Final Term', 'Test', 'Practical'];

pageHead('ILC Assessments', 'ilc_vp');
$links = getIlcLinks();
?>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'assessments', $links, $user); ?>
<div class="main-area">
<?php topbar('Assessments & Marks', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  <strong>Migration not applied.</strong>
  Run <code>database/migrations/ilc_student_assessments.sql</code> first. ⚠️ Back up your DB!
</div>
<?php else: ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?= $tab==='assessments'?'active':'' ?>" href="?tab=assessments">
      Manage Assessments
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab==='marks'?'active':'' ?>" href="?tab=marks">
      Enter Marks
    </a>
  </li>
</ul>

<?php if ($tab === 'assessments'): ?>
<!-- ══════════════ TAB 1: MANAGE ASSESSMENTS ══════════════ -->

<!-- Create Assessment -->
<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-plus me-2"></i>Create New Assessment</div>
  <div style="padding:16px">
    <form method="POST">
      <input type="hidden" name="action" value="create_assessment">
      <div class="row g-2">
        <div class="col-md-4">
          <label class="form-label fw-semibold" style="font-size:.82rem">Student <span class="text-danger">*</span></label>
          <select name="student_id" class="form-select form-select-sm" required>
            <option value="">Select student</option>
            <?php foreach ($students as $s): ?>
            <option value="<?= $s['id'] ?>">
              <?= h($s['name']) ?> (<?= h($s['roll_no']) ?>) — <?= h($s['class_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold" style="font-size:.82rem">Title <span class="text-danger">*</span></label>
          <input type="text" name="title" class="form-control form-control-sm"
                 placeholder="e.g. Quiz 1, Midterm…" required>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Type</label>
          <input type="text" name="type" class="form-control form-control-sm"
                 list="typeList" value="Quiz" placeholder="Quiz…">
          <datalist id="typeList">
            <?php foreach ($commonTypes as $t): ?><option value="<?= $t ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Max Marks <span class="text-danger">*</span></label>
          <input type="number" name="max_marks" class="form-control form-control-sm"
                 min="0.5" max="9999" step="0.5" required>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Weight %</label>
          <input type="number" name="weight" class="form-control form-control-sm"
                 min="0" max="100" step="0.5" value="10">
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

<!-- Assessment list -->
<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-list me-2"></i>My Assessments</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.85rem">
      <thead class="table-light">
        <tr>
          <th>Title</th>
          <th>Type</th>
          <th>Student</th>
          <th>Max</th>
          <th>Weight</th>
          <th>Date</th>
          <th>Marks</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($assessments as $a): ?>
      <tr>
        <td class="fw-semibold"><?= h($a['title']) ?></td>
        <td>
          <span class="badge <?= $typeColors[$a['type']] ?? 'bg-secondary' ?>" style="font-size:.74rem">
            <?= h($a['type']) ?>
          </span>
        </td>
        <td><?= h($a['student_name']) ?>
          <span class="text-muted" style="font-size:.78rem">(<?= h($a['roll_no']) ?>)</span>
        </td>
        <td><?= $a['max_marks'] ?></td>
        <td><?= $a['weight'] ?>%</td>
        <td><?= $a['date'] ? fDate($a['date']) : '—' ?></td>
        <td>
          <?php if ($a['marks_obtained'] !== null): ?>
          <span style="color:#16a34a;font-weight:600"><?= $a['marks_obtained'] ?> entered</span>
          <?php else: ?>
          <span class="text-muted" style="font-size:.8rem">0 entered</span>
          <?php endif; ?>
        </td>
        <td style="white-space:nowrap">
          <a href="?tab=marks&assessment_id=<?= $a['id'] ?>"
             class="btn btn-xs btn-outline-primary" style="font-size:.76rem;padding:2px 8px">
            Enter Marks
          </a>
          <form method="POST" class="d-inline"
                onsubmit="return confirm('Delete this assessment and its marks?')">
            <input type="hidden" name="action" value="delete_assessment">
            <input type="hidden" name="assessment_id" value="<?= $a['id'] ?>">
            <button class="btn btn-xs btn-outline-danger" style="font-size:.76rem;padding:2px 8px">
              Del
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($assessments)): ?>
      <tr><td colspan="8" class="text-center text-muted py-3">No assessments yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'marks'): ?>
<!-- ══════════════ TAB 2: ENTER MARKS ══════════════ -->

<?php if (!$assessmentId || !$currentAssessment): ?>
<!-- Assessment picker -->
<div class="sec-card p-4">
  <p class="text-muted mb-3">Select an assessment to enter marks:</p>
  <div class="list-group">
    <?php foreach ($assessments as $a): ?>
    <a href="?tab=marks&assessment_id=<?= $a['id'] ?>"
       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
      <div>
        <strong><?= h($a['title']) ?></strong>
        <span class="badge <?= $typeColors[$a['type']] ?? 'bg-secondary' ?> ms-2" style="font-size:.72rem">
          <?= h($a['type']) ?>
        </span>
        <span class="text-muted ms-2" style="font-size:.82rem">
          — <?= h($a['student_name']) ?> (<?= h($a['roll_no']) ?>)
        </span>
      </div>
      <span class="text-muted" style="font-size:.82rem">
        <?= $a['date'] ? fDate($a['date']) : '' ?>
        &nbsp;|&nbsp; Max: <?= $a['max_marks'] ?>
        <?php if ($a['marks_obtained'] !== null): ?>
        &nbsp;|&nbsp; <span style="color:#16a34a">Marks: <?= $a['marks_obtained'] ?></span>
        <?php endif; ?>
      </span>
    </a>
    <?php endforeach; ?>
    <?php if (empty($assessments)): ?>
    <div class="text-center text-muted py-3">No assessments found. Create one first.</div>
    <?php endif; ?>
  </div>
</div>

<?php else: // currentAssessment
  $ca     = $currentAssessment;
  $maxM   = (float)$ca['max_marks'];
  $obt    = $ca['marks_obtained'];
  $pct    = ($obt !== null && $maxM > 0) ? round($obt / $maxM * 100, 1) : null;
?>
<div class="sec-card mb-3">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span>
      <i class="fas fa-pen-alt me-2"></i>
      <strong><?= h($ca['title']) ?></strong>
      <span class="badge <?= $typeColors[$ca['type']] ?? 'bg-secondary' ?> ms-2">
        <?= h($ca['type']) ?>
      </span>
      &nbsp;·&nbsp; <?= h($ca['student_name']) ?>
      <span class="text-muted" style="font-size:.8rem">(<?= h($ca['roll_no']) ?> · <?= h($ca['class_name']) ?>)</span>
    </span>
    <span class="text-muted" style="font-size:.82rem">
      Max Marks: <strong><?= $maxM ?></strong>
      &nbsp;|&nbsp; Weight: <strong><?= $ca['weight'] ?>%</strong>
    </span>
  </div>

  <form method="POST">
    <input type="hidden" name="action" value="save_marks">
    <input type="hidden" name="assessment_id" value="<?= $assessmentId ?>">

    <div class="table-responsive">
      <table class="table table-hover mb-0" style="font-size:.85rem">
        <thead class="table-light">
          <tr>
            <th>Roll No</th>
            <th>Student Name</th>
            <th style="width:160px">Marks&nbsp;(/&nbsp;<?= $maxM ?>)</th>
            <th style="width:90px">%</th>
            <th style="width:90px">Grade</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody id="marksBody">
          <tr>
            <td><?= h($ca['roll_no']) ?></td>
            <td class="fw-semibold"><?= h($ca['student_name']) ?></td>
            <td>
              <input type="number" name="marks_obtained"
                     class="form-control form-control-sm marks-input"
                     data-max="<?= $maxM ?>"
                     value="<?= h($obt ?? '') ?>"
                     min="0" max="<?= $maxM ?>" step="0.5"
                     oninput="calcRow(this)">
            </td>
            <td class="pct-cell"><?= $pct !== null ? $pct.'%' : '—' ?></td>
            <td class="grade-cell">
              <?= $pct !== null ? '<span class="grade '.getGrade($pct)['class'].'">'.getGrade($pct)['label'].'</span>' : '—' ?>
            </td>
            <td>
              <input type="text" name="remarks" class="form-control form-control-sm"
                     value="<?= h($ca['remarks'] ?? '') ?>" placeholder="Optional">
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div style="padding:12px 16px;display:flex;gap:10px;align-items:center">
      <button type="submit" class="btn btn-success">
        <i class="fas fa-save me-1"></i>Save Marks
      </button>
      <a href="?tab=marks" class="btn btn-outline-secondary">Back</a>
    </div>
  </form>
</div>
<?php endif; ?>

<?php endif; // tab ?>

<?php endif; // tableExists ?>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function calcRow(input) {
    const max  = parseFloat(input.dataset.max) || 0;
    const val  = parseFloat(input.value);
    const row  = input.closest('tr');
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
</body></html>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp');
$db   = getDB();

// Table availability
$tableExists = false;
try { $db->query('SELECT 1 FROM ilc_student_assessments LIMIT 1'); $tableExists = true; } catch (Exception $e) {}

// ── POST handler ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action    = $_POST['action']     ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);

    if ($action === 'create' && $studentId) {
        $title    = trim($_POST['title']   ?? '');
        $type     = trim($_POST['type']    ?? '') ?: 'Quiz';
        $maxM     = max(0.5, (float)($_POST['max_marks']  ?? 100));
        $weight   = min(100, max(0, (float)($_POST['weight'] ?? 0)));
        $date     = $_POST['date'] ?: null;
        $obtRaw   = $_POST['marks_obtained'] ?? '';
        $obtained = ($obtRaw !== '') ? min($maxM, max(0, (float)$obtRaw)) : null;
        $remarks  = trim($_POST['remarks'] ?? '');
        if ($title) {
            $db->prepare(
                'INSERT INTO ilc_student_assessments
                 (student_id,title,type,max_marks,weight,marks_obtained,date,remarks,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([$studentId, $title, $type, $maxM, $weight, $obtained, $date, $remarks ?: null, $user['id']]);
            logActivity($user['id'], 'ilc_assess_create', "Created ILC assessment '$title' for student #$studentId");
            setFlash('success', 'Assessment created.');
        }
    }

    if ($action === 'update') {
        $id      = (int)($_POST['assessment_id'] ?? 0);
        $title   = trim($_POST['title']   ?? '');
        $type    = trim($_POST['type']    ?? '') ?: 'Quiz';
        $maxM    = max(0.5, (float)($_POST['max_marks']  ?? 100));
        $weight  = min(100, max(0, (float)($_POST['weight'] ?? 0)));
        $date    = $_POST['date'] ?: null;
        $obtRaw  = $_POST['marks_obtained'] ?? '';
        $obtained= ($obtRaw !== '') ? min($maxM, max(0, (float)$obtRaw)) : null;
        $remarks = trim($_POST['remarks'] ?? '');
        if ($id && $title && $studentId) {
            $db->prepare(
                'UPDATE ilc_student_assessments
                 SET title=?,type=?,max_marks=?,weight=?,marks_obtained=?,date=?,remarks=?
                 WHERE id=? AND student_id=?'
            )->execute([$title, $type, $maxM, $weight, $obtained, $date, $remarks ?: null, $id, $studentId]);
            logActivity($user['id'], 'ilc_assess_update', "Updated ILC assessment #$id");
            setFlash('success', 'Assessment updated.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['assessment_id'] ?? 0);
        if ($id && $studentId) {
            $db->prepare('DELETE FROM ilc_student_assessments WHERE id=? AND student_id=?')
               ->execute([$id, $studentId]);
            logActivity($user['id'], 'ilc_assess_delete', "Deleted ILC assessment #$id");
            setFlash('success', 'Assessment deleted.');
        }
    }

    redirect('/portal/ilc/assessments.php' . ($studentId ? "?student_id=$studentId" : ''));
}

// ── Fetch ─────────────────────────────────────────────────────────
$studentId = (int)($_GET['student_id'] ?? 0);
$editId    = (int)($_GET['edit']       ?? 0);

$students = $db->query(
    'SELECT s.id, u.name, s.roll_no, c.name AS class_name
     FROM students s JOIN users u ON u.id = s.user_id JOIN classes c ON c.id = s.class_id
     WHERE c.is_ilc = 1 ORDER BY c.name, s.roll_no'
)->fetchAll();

$assessments = [];
$curStudent  = null;
$editRow     = null;

if ($studentId) {
    foreach ($students as $s) { if ($s['id'] === $studentId) { $curStudent = $s; break; } }
    if ($tableExists) {
        $st = $db->prepare(
            'SELECT * FROM ilc_student_assessments WHERE student_id=? ORDER BY date DESC, id DESC'
        );
        $st->execute([$studentId]);
        $assessments = $st->fetchAll();
        if ($editId) {
            foreach ($assessments as $a) { if ($a['id'] == $editId) { $editRow = $a; break; } }
        }
    }
}

// Grade calculation (same thresholds as main campus)
function ilcGrade(float $pct): array {
    if ($pct >= 90) return ['label' => 'A+', 'cls' => 'grade-aplus'];
    if ($pct >= 80) return ['label' => 'A',  'cls' => 'grade-a'];
    if ($pct >= 70) return ['label' => 'B+', 'cls' => 'grade-bplus'];
    if ($pct >= 60) return ['label' => 'B',  'cls' => 'grade-b'];
    if ($pct >= 50) return ['label' => 'C',  'cls' => 'grade-c'];
    if ($pct >= 40) return ['label' => 'D',  'cls' => 'grade-d'];
    return ['label' => 'F', 'cls' => 'grade-f'];
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
<?php topbar('Student Assessments', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  <strong>Migration not applied.</strong>
  Run <code>database/migrations/ilc_student_assessments.sql</code> first. ⚠️ Back up your DB!
</div>
<?php else: ?>

<!-- ILC strip -->
<div class="d-flex align-items-center gap-3 mb-3 p-3"
     style="background:linear-gradient(90deg,#eff6ff,#f0fdf4);border-radius:10px;border:1px solid #93c5fd;">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC"
       style="width:40px;height:40px;object-fit:contain;flex-shrink:0;" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#1d4ed8;letter-spacing:.8px;text-transform:uppercase">
      Student Assessments
    </div>
    <div style="font-size:.78rem;color:#475569">Inclusive Learning Centre · Bahria Model College Bin Qasim</div>
  </div>
</div>

<div class="row g-3">
  <!-- ── Left panel: student selector + form ── -->
  <div class="col-lg-4">

    <!-- Student selector -->
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-user-graduate me-2"></i>Select Student</div>
      <div style="padding:14px 16px">
        <form method="GET">
          <select name="student_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">— Select student —</option>
            <?php foreach ($students as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $studentId===$s['id']?'selected':'' ?>>
              <?= h($s['name']) ?> (<?= h($s['roll_no']) ?>) — <?= h($s['class_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <?php if ($studentId): ?>
    <!-- Add / Edit form -->
    <div class="sec-card">
      <div class="sec-card-header" style="background:#1d4ed8;color:#fff">
        <i class="fas fa-<?= $editRow ? 'edit' : 'plus' ?> me-2"></i>
        <?= $editRow ? 'Edit Assessment' : 'Add Assessment' ?>
        <?php if ($editRow): ?>
        <a href="?student_id=<?= $studentId ?>" class="btn btn-sm btn-light ms-auto" style="font-size:.72rem">
          <i class="fas fa-times"></i>
        </a>
        <?php endif; ?>
      </div>
      <div style="padding:14px 16px">
        <form method="POST">
          <input type="hidden" name="action"     value="<?= $editRow ? 'update' : 'create' ?>">
          <input type="hidden" name="student_id" value="<?= $studentId ?>">
          <?php if ($editRow): ?>
          <input type="hidden" name="assessment_id" value="<?= $editRow['id'] ?>">
          <?php endif; ?>

          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.8rem">Title / Name <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control form-control-sm" required
                   value="<?= h($editRow['title'] ?? '') ?>" placeholder="e.g. Quiz 1, Unit Test…">
          </div>

          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.8rem">Type</label>
            <input type="text" name="type" class="form-control form-control-sm"
                   list="typeList" value="<?= h($editRow['type'] ?? 'Quiz') ?>"
                   placeholder="Quiz, Midterm, Custom…">
            <datalist id="typeList">
              <?php foreach ($commonTypes as $t): ?>
              <option value="<?= $t ?>">
              <?php endforeach; ?>
            </datalist>
          </div>

          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label fw-semibold" style="font-size:.8rem">Max Marks <span class="text-danger">*</span></label>
              <input type="number" name="max_marks" id="fMaxMarks" class="form-control form-control-sm"
                     min="0.5" max="9999" step="0.5" required
                     value="<?= h($editRow['max_marks'] ?? '100') ?>"
                     oninput="recalcForm()">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold" style="font-size:.8rem">Weight %</label>
              <input type="number" name="weight" class="form-control form-control-sm"
                     min="0" max="100" step="0.5" value="<?= h($editRow['weight'] ?? '10') ?>">
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.8rem">Date</label>
            <input type="date" name="date" class="form-control form-control-sm"
                   value="<?= h($editRow['date'] ?? date('Y-m-d')) ?>">
          </div>

          <div class="mb-1">
            <label class="form-label fw-semibold" style="font-size:.8rem">
              Marks Obtained
              <span id="fMaxLabel" style="color:#64748b;font-weight:400">
                / <?= h($editRow['max_marks'] ?? '100') ?>
              </span>
            </label>
            <input type="number" name="marks_obtained" id="fObtained" class="form-control form-control-sm"
                   min="0" step="0.5"
                   value="<?= ($editRow && $editRow['marks_obtained'] !== null) ? h($editRow['marks_obtained']) : '' ?>"
                   placeholder="Leave blank if not yet assessed"
                   oninput="recalcForm()">
          </div>
          <div id="fCalcRow" style="font-size:.78rem;color:#1d4ed8;margin-bottom:8px;min-height:18px"></div>

          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.8rem">Remarks</label>
            <input type="text" name="remarks" class="form-control form-control-sm"
                   value="<?= h($editRow['remarks'] ?? '') ?>" placeholder="Optional…">
          </div>

          <button type="submit" class="btn btn-success btn-sm w-100">
            <i class="fas fa-save me-1"></i><?= $editRow ? 'Update Assessment' : 'Add Assessment' ?>
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Right panel: assessments list ── -->
  <div class="col-lg-8">
    <?php if (!$studentId): ?>
    <div class="sec-card">
      <div style="padding:60px;text-align:center;color:var(--t2)">
        <i class="fas fa-clipboard-list fa-2x mb-3 d-block opacity-20"></i>
        Select a student on the left to view or manage their assessments.
      </div>
    </div>

    <?php elseif ($curStudent): ?>
    <?php
    // Calculate overall results
    $totalWeight = 0; $weightedSum = 0; $totalObt = 0; $totalMax = 0;
    foreach ($assessments as $a) {
        if ($a['marks_obtained'] !== null) {
            $totalObt += $a['marks_obtained'];
            $totalMax += $a['max_marks'];
            if ($a['weight'] > 0) {
                $apct = $a['max_marks'] > 0 ? ($a['marks_obtained'] / $a['max_marks'] * 100) : 0;
                $weightedSum += $apct * $a['weight'];
                $totalWeight += $a['weight'];
            }
        }
    }
    $overallPct = $totalWeight > 0
        ? round($weightedSum / $totalWeight, 1)
        : ($totalMax > 0 ? round($totalObt / $totalMax * 100, 1) : 0);
    $hasMarks = $totalMax > 0;
    $overallGrade = $hasMarks ? ilcGrade($overallPct) : null;
    ?>
    <div class="sec-card">
      <div class="sec-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span>
          <i class="fas fa-chart-bar me-2"></i><?= h($curStudent['name']) ?>
          <span class="text-muted fw-normal" style="font-size:.78rem">
            · <?= h($curStudent['class_name']) ?> · Roll <?= h($curStudent['roll_no']) ?>
          </span>
          <span class="badge bg-secondary ms-2"><?= count($assessments) ?></span>
        </span>
        <?php if ($hasMarks): ?>
        <span class="d-flex align-items-center gap-2">
          <span style="font-size:.82rem;font-weight:700;color:#1d4ed8"><?= $overallPct ?>%</span>
          <?= gradeHtml($overallPct) ?>
          <span class="text-muted" style="font-size:.75rem">(weighted overall)</span>
        </span>
        <?php endif; ?>
      </div>

      <?php if (empty($assessments)): ?>
      <div style="padding:50px;text-align:center;color:var(--t2);font-size:.85rem">
        <i class="fas fa-clipboard-list fa-2x mb-3 d-block opacity-20"></i>
        No assessments yet. Use the form on the left to add the first one.
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.84rem">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Title</th>
              <th>Type</th>
              <th>Date</th>
              <th style="text-align:center">Max</th>
              <th style="text-align:center">Obtained</th>
              <th style="text-align:center">%</th>
              <th style="text-align:center">Grade</th>
              <th style="text-align:center">Weight</th>
              <th>Remarks</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($assessments as $i => $a):
            $hasMark = $a['marks_obtained'] !== null;
            $pct     = ($hasMark && $a['max_marks'] > 0)
                         ? round($a['marks_obtained'] / $a['max_marks'] * 100, 1) : null;
            $gd      = $pct !== null ? ilcGrade($pct) : null;
            $tc      = $typeColors[$a['type']] ?? 'bg-secondary';
          ?>
          <tr>
            <td class="text-muted" style="font-size:.78rem"><?= $i+1 ?></td>
            <td class="fw-semibold"><?= h($a['title']) ?></td>
            <td><span class="badge <?= $tc ?>" style="font-size:.72rem"><?= h($a['type']) ?></span></td>
            <td style="white-space:nowrap;font-size:.8rem"><?= $a['date'] ? fDate($a['date']) : '—' ?></td>
            <td style="text-align:center"><?= $a['max_marks'] ?></td>
            <td style="text-align:center;font-weight:700">
              <?= $hasMark ? h($a['marks_obtained']) : '<span class="text-muted">—</span>' ?>
            </td>
            <td style="text-align:center">
              <?= $pct !== null ? '<span style="color:#1d4ed8;font-weight:700">'.$pct.'%</span>' : '<span class="text-muted">—</span>' ?>
            </td>
            <td style="text-align:center">
              <?= $gd ? '<span class="grade '.$gd['cls'].'">'.$gd['label'].'</span>' : '<span class="text-muted">—</span>' ?>
            </td>
            <td style="text-align:center;font-size:.8rem"><?= $a['weight'] > 0 ? $a['weight'].'%' : '—' ?></td>
            <td style="font-size:.78rem;color:var(--t2)"><?= $a['remarks'] ? h($a['remarks']) : '—' ?></td>
            <td style="white-space:nowrap">
              <a href="?student_id=<?= $studentId ?>&edit=<?= $a['id'] ?>"
                 class="btn btn-xs btn-outline-primary" style="font-size:.72rem;padding:2px 7px">
                <i class="fas fa-edit"></i>
              </a>
              <form method="POST" class="d-inline"
                    onsubmit="return confirm('Delete this assessment?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="student_id" value="<?= $studentId ?>">
                <input type="hidden" name="assessment_id" value="<?= $a['id'] ?>">
                <button class="btn btn-xs btn-outline-danger" style="font-size:.72rem;padding:2px 7px">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
          <?php if ($hasMarks): ?>
          <tfoot>
            <tr style="background:#eff6ff">
              <td colspan="4" class="fw-bold" style="font-size:.82rem">Overall Result</td>
              <td style="text-align:center;font-weight:700"><?= $totalMax ?></td>
              <td style="text-align:center;font-weight:700"><?= $totalObt ?></td>
              <td style="text-align:center;font-weight:700;color:#1d4ed8"><?= $overallPct ?>%</td>
              <td style="text-align:center"><?= gradeHtml($overallPct) ?></td>
              <td colspan="3"></td>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php endif; // tableExists ?>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function recalcForm() {
    const maxEl = document.getElementById('fMaxMarks');
    const obtEl = document.getElementById('fObtained');
    const lbl   = document.getElementById('fMaxLabel');
    const row   = document.getElementById('fCalcRow');
    if (!maxEl) return;
    const max = parseFloat(maxEl.value) || 0;
    lbl.textContent = '/ ' + (max || '?');
    const obt = obtEl.value !== '' ? parseFloat(obtEl.value) : NaN;
    if (isNaN(obt) || max <= 0) { row.textContent = ''; return; }
    const pct = Math.round(obt / max * 1000) / 10;
    const g   = getGradeLabel(pct);
    row.innerHTML = '<strong>' + pct + '%</strong> &nbsp;·&nbsp; Grade: <strong>' + g + '</strong>';
}
function getGradeLabel(pct) {
    if (pct >= 90) return 'A+';
    if (pct >= 80) return 'A';
    if (pct >= 70) return 'B+';
    if (pct >= 60) return 'B';
    if (pct >= 50) return 'C';
    if (pct >= 40) return 'D';
    return 'F';
}
// Run on load for edit mode
window.addEventListener('DOMContentLoaded', recalcForm);
</script>
</body></html>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

$classId   = (int)($_GET['class_id']   ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);
$classes   = getAllClasses();
$subjects  = getAllSubjects();

$assessments = [];
$studentResults = [];

if ($classId && $subjectId) {
    // Get assessments for class+subject
    $aSt = $db->prepare('SELECT * FROM assessments WHERE class_id = ? AND subject_id = ? ORDER BY date');
    $aSt->execute([$classId, $subjectId]);
    $assessments = $aSt->fetchAll();

    if (!empty($assessments)) {
        $students = getClassStudents($classId);
        $aIds = array_column($assessments, 'id');
        $placeholders = implode(',', array_fill(0, count($aIds), '?'));
        $marksSt = $db->prepare("SELECT assessment_id, student_id, marks_obtained FROM marks WHERE assessment_id IN ($placeholders)");
        $marksSt->execute($aIds);
        $marksMap = [];
        foreach ($marksSt->fetchAll() as $m) {
            $marksMap[$m['student_id']][$m['assessment_id']] = $m['marks_obtained'];
        }

        foreach ($students as $s) {
            $totalWeight = 0;
            $totalWeighted = 0;
            $row = ['student' => $s, 'marks' => [], 'overall_pct' => null];
            foreach ($assessments as $a) {
                $obtained = $marksMap[$s['id']][$a['id']] ?? null;
                $pct = ($obtained !== null && $a['max_marks'] > 0) ? round($obtained / $a['max_marks'] * 100, 1) : null;
                $row['marks'][$a['id']] = ['obtained' => $obtained, 'pct' => $pct];
                if ($obtained !== null) {
                    $totalWeighted += $pct * $a['weight'];
                    $totalWeight   += $a['weight'];
                }
            }
            if ($totalWeight > 0) {
                $row['overall_pct'] = round($totalWeighted / $totalWeight, 1);
            }
            $studentResults[] = $row;
        }
    }
}

pageHead('Results', 'admin');
$links = getAdminLinks();
?>
<div class="portal-wrap">
<?php sidebar('admin', 'results', $links, $user); ?>
<div class="main-area">
<?php topbar('Results Overview', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<form method="GET" class="d-flex gap-2 mb-3 align-items-end flex-wrap">
  <div>
    <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
    <select name="class_id" class="form-select form-select-sm" style="min-width:140px">
      <option value="">Select</option>
      <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classId===$c['id']?'selected':'' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="form-label fw-semibold" style="font-size:.82rem">Subject</label>
    <select name="subject_id" class="form-select form-select-sm" style="min-width:160px">
      <option value="">Select</option>
      <?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>" <?= $subjectId===$s['id']?'selected':'' ?>><?= h($s['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div><button type="submit" class="btn btn-sm btn-primary">View Results</button></div>
</form>

<?php if ($classId && $subjectId && !empty($assessments)): ?>
<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-table me-2"></i>Results</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.82rem">
      <thead class="table-light">
        <tr>
          <th>Roll</th><th>Name</th>
          <?php foreach ($assessments as $a): ?>
            <th class="text-center" style="white-space:nowrap"><?= h($a['title'] ?? '') ?><br><small><?= $a['max_marks'] ?> mks</small></th>
          <?php endforeach; ?>
          <th class="text-center">Overall%</th>
          <th class="text-center">Grade</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($studentResults as $r): ?>
        <tr>
          <td><?= h($r['student']['roll_no']) ?></td>
          <td class="fw-semibold"><?= h($r['student']['name']) ?></td>
          <?php foreach ($assessments as $a): ?>
            <?php $m = $r['marks'][$a['id']] ?? []; ?>
            <td class="text-center">
              <?php if ($m['obtained'] !== null): ?>
                <?= $m['obtained'] ?>
                <div style="font-size:.72rem;color:#6b7280"><?= $m['pct'] ?>%</div>
              <?php else: ?>—<?php endif; ?>
            </td>
          <?php endforeach; ?>
          <td class="text-center fw-bold"><?= $r['overall_pct'] !== null ? $r['overall_pct'].'%' : '—' ?></td>
          <td class="text-center"><?= $r['overall_pct'] !== null ? gradeHtml($r['overall_pct']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php elseif ($classId && $subjectId && empty($assessments)): ?>
  <div class="sec-card p-3 text-muted">No assessments found for this class/subject.</div>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

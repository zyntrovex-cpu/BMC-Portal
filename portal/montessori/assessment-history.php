<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/db.php';

$user    = requireAuth('montessori_teacher', 'wing_head', 'vp_montessori');
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);

function monteSubjectMetaH(string $name): array {
    $n = mb_strtolower($name);
    if (str_contains($n, 'english'))  return ['icon'=>'fa-book',        'bg'=>'#dbeafe','ic'=>'#1d4ed8'];
    if (str_contains($n, 'math'))     return ['icon'=>'fa-calculator',  'bg'=>'#dcfce7','ic'=>'#15803d'];
    if (str_contains($n, 'science'))  return ['icon'=>'fa-flask',       'bg'=>'#ede9fe','ic'=>'#7c3aed'];
    if (str_contains($n, 'urdu'))     return ['icon'=>'fa-language',    'bg'=>'#fef3c7','ic'=>'#b45309'];
    if (str_contains($n, 'islamic'))  return ['icon'=>'fa-mosque',      'bg'=>'#ccfbf1','ic'=>'#0d9488'];
    if (str_contains($n, 'art') || str_contains($n, 'craft'))
                                      return ['icon'=>'fa-paint-brush', 'bg'=>'#fce7f3','ic'=>'#be185d'];
    if (str_contains($n, 'general'))  return ['icon'=>'fa-globe',       'bg'=>'#e0f2fe','ic'=>'#0369a1'];
    if (str_contains($n, 'physical')) return ['icon'=>'fa-running',     'bg'=>'#ffedd5','ic'=>'#c2410c'];
    return ['icon'=>'fa-book-open','bg'=>'#f3f4f6','ic'=>'#4b5563'];
}

// Teacher's montessori-style classes (subject-based or class teacher, includes Main Campus Class 2/3)
$monteStyleCond = "(c.is_montessori=1 OR (COALESCE(c.wing,'main')='main' AND c.grade IN (2,3) AND COALESCE(c.is_ilc,0)=0))";
if ($teacher) {
    $cSt = $db->prepare(
        "SELECT DISTINCT c.id,c.name,c.grade FROM class_subjects cs
         JOIN classes c ON cs.class_id=c.id
         WHERE cs.teacher_id=? AND $monteStyleCond
         UNION
         SELECT c.id,c.name,c.grade FROM class_teacher_assignments cta
         JOIN classes c ON cta.class_id=c.id
         WHERE cta.teacher_id=? AND $monteStyleCond
         ORDER BY grade,name"
    );
    $cSt->execute([$teacher['id'],$teacher['id']]);
} else {
    $cSt = $db->prepare("SELECT id,name,grade FROM classes WHERE $monteStyleCond ORDER BY grade,name");
    $cSt->execute([]);
}
$assignedClasses = $cSt->fetchAll();

$selClassId = (int)($_GET['class_id'] ?? (empty($assignedClasses) ? 0 : $assignedClasses[0]['id']));
$selSubject = (int)($_GET['subject_id'] ?? 0);
$selStudent = (int)($_GET['student_id'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 20;

// Class info
$classInfo = null;
if ($selClassId) {
    $ci = $db->prepare('SELECT name FROM classes WHERE id=?');
    $ci->execute([$selClassId]);
    $classInfo = $ci->fetch();
}

// Subject filter list
$subjects = [];
if ($selClassId) {
    if ($teacher) {
        $sSt = $db->prepare(
            'SELECT DISTINCT s.id,s.name FROM class_subjects cs
             JOIN subjects s ON cs.subject_id=s.id
             WHERE cs.teacher_id=? AND cs.class_id=? ORDER BY s.name'
        );
        $sSt->execute([$teacher['id'],$selClassId]);
    } else {
        $sSt = $db->prepare(
            'SELECT DISTINCT s.id,s.name FROM class_subjects cs
             JOIN subjects s ON cs.subject_id=s.id
             WHERE cs.class_id=? ORDER BY s.name'
        );
        $sSt->execute([$selClassId]);
    }
    $subjects = $sSt->fetchAll();
}

// Student filter list for the selected class
$students = [];
if ($selClassId) {
    $stuSt = $db->prepare(
        'SELECT st.id, u.name FROM students st
         JOIN users u ON st.user_id=u.id
         WHERE st.class_id=? AND st.deleted_at IS NULL
         ORDER BY u.name'
    );
    $stuSt->execute([$selClassId]);
    $students = $stuSt->fetchAll();
}

// History rows
$history  = [];
$total    = 0;
$offset   = ($page - 1) * $perPage;

if ($selClassId) {
    try {
        $whereParts = ['mda.class_id=?'];
        $params     = [$selClassId];
        if ($selSubject) { $whereParts[] = 'mda.subject_id=?'; $params[] = $selSubject; }
        if ($selStudent) { $whereParts[] = 'mda.student_id=?'; $params[] = $selStudent; }
        $where = implode(' AND ', $whereParts);

        $countSt = $db->prepare("SELECT COUNT(*) FROM montessori_daily_assessments mda WHERE $where");
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $params[] = $perPage;
        $params[] = $offset;
        $dataSt = $db->prepare(
            "SELECT mda.id, mda.subject_id, mda.student_id, mda.assessment_date, mda.topic, mda.criteria,
                    s.name AS subject_name,
                    COALESCE(u2.name,'—') AS student_name,
                    t.id AS teacher_id,
                    u.name AS teacher_name
             FROM montessori_daily_assessments mda
             JOIN subjects s ON mda.subject_id=s.id
             JOIN teachers t ON mda.teacher_id=t.id
             JOIN users u ON t.user_id=u.id
             LEFT JOIN students st ON mda.student_id=st.id
             LEFT JOIN users u2 ON st.user_id=u2.id
             WHERE $where
             ORDER BY mda.assessment_date DESC, u2.name, s.name
             LIMIT ? OFFSET ?"
        );
        $dataSt->execute($params);
        $history = $dataSt->fetchAll();
    } catch (Exception $e) {}
}

$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;

$portalRole = in_array($user['role'], ['wing_head','vp_montessori']) ? $user['role'] : 'montessori_teacher';
pageHead('Formative Assessment History', $portalRole);
$links = match($user['role']) {
    'wing_head'     => getWingHeadLinks(),
    'vp_montessori' => getVpMontessoriLinks(),
    default         => getMonteTeacherLinks(),
};
?>
<div class="portal-wrap">
<?php sidebar($portalRole,'monte-assessments',$links,$user); ?>
<div class="main-area">
<?php topbar('Formative Assessment History',$user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Back + Filter bar -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <a href="<?= url('/portal/montessori/assessments.php') . ($selClassId ? '?class_id='.$selClassId : '') ?>"
     class="btn btn-sm btn-outline-secondary" style="font-size:.8rem">
    <i class="fas fa-arrow-left me-1"></i>Back to Formative Assessment
  </a>
  <h5 class="mb-0" style="font-size:1rem;font-weight:700">
    <i class="fas fa-history me-2 text-primary"></i>Assessment History
    <?php if ($classInfo): ?><span class="text-muted fw-normal" style="font-size:.85rem">&mdash; <?= h($classInfo['name']) ?></span><?php endif; ?>
  </h5>
</div>

<!-- Filter -->
<form method="GET" class="sec-card mb-3 p-3 d-flex align-items-end gap-2 flex-wrap">
  <div>
    <label class="form-label fw-semibold mb-1" style="font-size:.77rem">Class</label>
    <select name="class_id" class="form-select form-select-sm" style="min-width:150px" onchange="this.form.submit()">
      <?php foreach ($assignedClasses as $cl): ?>
      <option value="<?= $cl['id'] ?>" <?= (int)$cl['id']===$selClassId?'selected':'' ?>><?= h($cl['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="form-label fw-semibold mb-1" style="font-size:.77rem">Student</label>
    <select name="student_id" class="form-select form-select-sm" style="min-width:150px">
      <option value="0">All Students</option>
      <?php foreach ($students as $stu): ?>
      <option value="<?= $stu['id'] ?>" <?= (int)$stu['id']===$selStudent?'selected':'' ?>><?= h($stu['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="form-label fw-semibold mb-1" style="font-size:.77rem">Subject</label>
    <select name="subject_id" class="form-select form-select-sm" style="min-width:140px">
      <option value="0">All Subjects</option>
      <?php foreach ($subjects as $s): ?>
      <option value="<?= $s['id'] ?>" <?= (int)$s['id']===$selSubject?'selected':'' ?>><?= h($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-sm btn-primary" style="font-size:.78rem">
    <i class="fas fa-filter me-1"></i>Filter
  </button>
  <?php if ($selSubject || $selStudent || $page > 1): ?>
  <a href="?class_id=<?= $selClassId ?>" class="btn btn-sm btn-outline-secondary" style="font-size:.78rem">Clear</a>
  <?php endif; ?>
</form>

<?php if (!$selClassId): ?>
<div class="alert alert-info" style="font-size:.83rem"><i class="fas fa-info-circle me-2"></i>No Montessori classes found.</div>
<?php elseif (empty($history)): ?>
<div class="alert alert-secondary" style="font-size:.83rem">
  <i class="fas fa-clipboard me-2"></i>No assessment records found<?= ($selStudent||$selSubject) ? ' for the selected filters' : ' for this class' ?>.
</div>
<?php else: ?>

<div class="sec-card mb-3">
  <div class="sec-card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-table me-2"></i>Past Assessments</span>
    <span class="badge bg-primary" style="font-size:.72rem"><?= $total ?> record<?= $total!==1?'s':'' ?></span>
  </div>

  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.82rem">
      <thead>
        <tr style="background:#f8fafc">
          <th style="padding:9px 12px;font-size:.74rem;font-weight:600">Date</th>
          <th style="padding:9px 12px;font-size:.74rem;font-weight:600">Student</th>
          <th style="padding:9px 12px;font-size:.74rem;font-weight:600">Subject</th>
          <th style="padding:9px 12px;font-size:.74rem;font-weight:600">Topic</th>
          <th style="padding:9px 12px;font-size:.74rem;font-weight:600">Criteria</th>
          <?php if (!$teacher): ?><th style="padding:9px 12px;font-size:.74rem;font-weight:600">Teacher</th><?php endif; ?>
          <th style="padding:9px 12px;font-size:.74rem;font-weight:600;text-align:center">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($history as $row):
          $meta     = monteSubjectMetaH($row['subject_name']);
          $criteria = json_decode($row['criteria'], true) ?? [];
        ?>
        <tr>
          <td style="padding:9px 12px;white-space:nowrap;font-weight:600;color:#1e293b">
            <?= date('d M Y', strtotime($row['assessment_date'])) ?>
            <div style="font-size:.7rem;color:#94a3b8;font-weight:400"><?= date('l', strtotime($row['assessment_date'])) ?></div>
          </td>
          <td style="padding:9px 12px;font-weight:600;color:#374151">
            <?= h($row['student_name']) ?>
          </td>
          <td style="padding:9px 12px">
            <div class="d-flex align-items-center gap-2">
              <div style="width:28px;height:28px;border-radius:8px;background:<?= $meta['bg'] ?>;color:<?= $meta['ic'] ?>;display:flex;align-items:center;justify-content:center;font-size:.75rem;flex-shrink:0">
                <i class="fas <?= $meta['icon'] ?>"></i>
              </div>
              <span style="font-weight:600"><?= h($row['subject_name']) ?></span>
            </div>
          </td>
          <td style="padding:9px 12px;color:#475569"><?= $row['topic'] ? h($row['topic']) : '<span style="color:#94a3b8">—</span>' ?></td>
          <td style="padding:9px 12px">
            <?php if ($criteria): ?>
            <div class="d-flex flex-wrap gap-1">
              <?php foreach (array_slice($criteria, 0, 3) as $c): ?>
              <span style="font-size:.69rem;background:#f1f5f9;color:#475569;padding:2px 7px;border-radius:10px;white-space:nowrap"><?= h($c) ?></span>
              <?php endforeach; ?>
              <?php if (count($criteria) > 3): ?>
              <span style="font-size:.69rem;color:#94a3b8">+<?= count($criteria)-3 ?> more</span>
              <?php endif; ?>
            </div>
            <?php else: ?>
            <span style="color:#94a3b8">—</span>
            <?php endif; ?>
          </td>
          <?php if (!$teacher): ?>
          <td style="padding:9px 12px;font-size:.77rem;color:#475569"><?= h($row['teacher_name']) ?></td>
          <?php endif; ?>
          <td style="padding:9px 12px;text-align:center">
            <div class="d-flex gap-1 justify-content-center flex-wrap">
              <?php if ($row['student_id']): ?>
              <a href="<?= url('/portal/montessori/assessments.php') ?>?class_id=<?= $selClassId ?>&student_id=<?= $row['student_id'] ?>&subject_id=<?= $row['subject_id'] ?>&date=<?= urlencode($row['assessment_date']) ?>"
                 class="btn btn-sm btn-outline-primary" style="font-size:.72rem;padding:3px 8px"
                 title="Open assessment for this student">
                <i class="fas fa-eye me-1"></i>View / Edit
              </a>
              <?php else: ?>
              <span style="font-size:.72rem;color:#94a3b8">Legacy</span>
              <?php endif; ?>
              <a href="<?= url('/portal/montessori/assessment-print.php') ?>?id=<?= $row['id'] ?>" target="_blank"
                 class="btn btn-sm btn-outline-success" style="font-size:.72rem;padding:3px 8px"
                 title="Printable report">
                <i class="fas fa-print me-1"></i>Print
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div style="padding:12px 14px;border-top:1px solid #f1f5f9">
    <nav>
      <ul class="pagination pagination-sm mb-0 flex-wrap gap-1">
        <?php
        $baseUrl = '?class_id='.$selClassId
            .($selStudent?'&student_id='.$selStudent:'')
            .($selSubject?'&subject_id='.$selSubject:'');
        ?>
        <li class="page-item <?= $page<=1?'disabled':'' ?>">
          <a class="page-link" href="<?= $baseUrl.'&page='.($page-1) ?>">
            <i class="fas fa-chevron-left" style="font-size:.65rem"></i>
          </a>
        </li>
        <?php for ($p = max(1,$page-3); $p <= min($totalPages,$page+3); $p++): ?>
        <li class="page-item <?= $p===$page?'active':'' ?>">
          <a class="page-link" href="<?= $baseUrl.'&page='.$p ?>" style="font-size:.78rem"><?= $p ?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
          <a class="page-link" href="<?= $baseUrl.'&page='.($page+1) ?>">
            <i class="fas fa-chevron-right" style="font-size:.65rem"></i>
          </a>
        </li>
      </ul>
    </nav>
  </div>
  <?php endif; ?>

</div>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/db.php';

$user = requireAuth('student');
$db   = getDB();

// Resolve student record
$stuSt = $db->prepare(
    'SELECT s.id,s.class_id,s.roll_no,c.name AS class_name,c.is_montessori,
            COALESCE(c.wing,\'main\') AS wing, COALESCE(c.grade,0) AS grade
     FROM students s JOIN classes c ON s.class_id=c.id
     WHERE s.user_id=? AND s.deleted_at IS NULL LIMIT 1'
);
$stuSt->execute([$user['id']]);
$student = $stuSt->fetch();

// Eligible: actual Montessori class OR Main Campus Class 2/3
$isMontePortalEligible = $student && (
    $student['is_montessori'] ||
    ($student['wing'] === 'main' && in_array((int)$student['grade'], [2, 3]))
);
if (!$isMontePortalEligible) {
    setFlash('danger', 'This page is only available for Montessori-style students.');
    redirect('/portal/student/dashboard.php');
}

$studentId = (int)$student['id'];

// ── Filters ───────────────────────────────────────────────────────────────────
$filterSubject = (int)($_GET['subject_id'] ?? 0);
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 15;
$offset        = ($page - 1) * $perPage;

// Subject list for filter dropdown
$subjList = [];
try {
    $slSt = $db->prepare(
        'SELECT DISTINCT s.id,s.name
         FROM montessori_daily_assessment_entries e
         JOIN montessori_daily_assessments mda ON mda.id=e.assessment_id
         JOIN subjects s ON mda.subject_id=s.id
         WHERE e.student_id=?
         ORDER BY s.name'
    );
    $slSt->execute([$studentId]);
    $subjList = $slSt->fetchAll();
} catch (Exception $e) {}

// ── Fetch assessment entries ──────────────────────────────────────────────────
$entries    = [];
$total      = 0;

try {
    $subjCond = $filterSubject ? 'AND mda.subject_id=?' : '';
    $params   = $filterSubject ? [$studentId, $filterSubject] : [$studentId];

    $cntSt = $db->prepare(
        "SELECT COUNT(*)
         FROM montessori_daily_assessment_entries e
         JOIN montessori_daily_assessments mda ON mda.id=e.assessment_id
         WHERE e.student_id=? $subjCond"
    );
    $cntSt->execute($params);
    $total = (int)$cntSt->fetchColumn();

    $params[] = $perPage;
    $params[] = $offset;
    $dataSt = $db->prepare(
        "SELECT e.ratings, e.overall, e.remarks,
                mda.assessment_date, mda.topic, mda.criteria, mda.subject_id,
                s.name AS subject_name
         FROM montessori_daily_assessment_entries e
         JOIN montessori_daily_assessments mda ON mda.id=e.assessment_id
         JOIN subjects s ON mda.subject_id=s.id
         WHERE e.student_id=? $subjCond
         ORDER BY mda.assessment_date DESC, s.name
         LIMIT ? OFFSET ?"
    );
    $dataSt->execute($params);
    $entries = $dataSt->fetchAll();
} catch (Exception $e) {}

$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;

// ── Helpers ───────────────────────────────────────────────────────────────────
function smSubjectMeta(string $name): array {
    $n = mb_strtolower($name);
    if (str_contains($n, 'english'))  return ['icon'=>'fa-book',        'bg'=>'#dbeafe','ic'=>'#1d4ed8','bdr'=>'#bfdbfe'];
    if (str_contains($n, 'math'))     return ['icon'=>'fa-calculator',  'bg'=>'#dcfce7','ic'=>'#15803d','bdr'=>'#bbf7d0'];
    if (str_contains($n, 'science'))  return ['icon'=>'fa-flask',       'bg'=>'#ede9fe','ic'=>'#7c3aed','bdr'=>'#ddd6fe'];
    if (str_contains($n, 'urdu'))     return ['icon'=>'fa-language',    'bg'=>'#fef3c7','ic'=>'#b45309','bdr'=>'#fde68a'];
    if (str_contains($n, 'islamic'))  return ['icon'=>'fa-mosque',      'bg'=>'#ccfbf1','ic'=>'#0d9488','bdr'=>'#99f6e4'];
    if (str_contains($n, 'art') || str_contains($n, 'craft'))
                                      return ['icon'=>'fa-paint-brush', 'bg'=>'#fce7f3','ic'=>'#be185d','bdr'=>'#fbcfe8'];
    if (str_contains($n, 'general'))  return ['icon'=>'fa-globe',       'bg'=>'#e0f2fe','ic'=>'#0369a1','bdr'=>'#bae6fd'];
    if (str_contains($n, 'physical')) return ['icon'=>'fa-running',     'bg'=>'#ffedd5','ic'=>'#c2410c','bdr'=>'#fed7aa'];
    return ['icon'=>'fa-book-open','bg'=>'#f3f4f6','ic'=>'#4b5563','bdr'=>'#e5e7eb'];
}

function smRatingBadge(string $v): string {
    if ($v === 'AD')  return '<span class="sm-badge ad">AD <small>Advanced</small></span>';
    if ($v === 'ED')  return '<span class="sm-badge ed">ED <small>Expected</small></span>';
    if ($v === 'EMD') return '<span class="sm-badge emd">EMD <small>Emerging</small></span>';
    return '<span class="sm-badge none">—</span>';
}

pageHead('My Formative Assessments', 'student');
$links = getStudentLinks($user, $student);
?>
<style>
.sm-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700}
.sm-badge small{font-weight:400;font-size:.65rem;opacity:.85}
.sm-badge.ad{background:#dcfce7;color:#166534}
.sm-badge.ed{background:#fef9c3;color:#854d0e}
.sm-badge.emd{background:#fee2e2;color:#991b1b}
.sm-badge.none{background:#f3f4f6;color:#9ca3af}

.entry-card{border-radius:12px;border:1px solid #e2e8f0;background:#fff;margin-bottom:14px;overflow:hidden;transition:.15s}
.entry-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.07)}
.entry-header{padding:12px 16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;border-bottom:1px solid #f1f5f9}
.entry-body{padding:14px 16px}

.crit-table th{font-size:.73rem;font-weight:600;background:#f8fafc;padding:6px 10px;text-align:left;border-bottom:2px solid #e2e8f0;white-space:nowrap}
.crit-table td{font-size:.78rem;padding:6px 10px;vertical-align:middle;border-bottom:1px solid #f1f5f9}
.crit-table tr:last-child td{border-bottom:none}
</style>

<div class="portal-wrap">
<?php sidebar('student','montessori-assessments',$links,$user); ?>
<div class="main-area">
<?php topbar('My Formative Assessments',$user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Page header -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold" style="font-size:1.05rem">
      <i class="fas fa-clipboard-list me-2 text-primary"></i>My Formative Assessments
    </h5>
    <p class="text-muted mb-0" style="font-size:.78rem"><?= h($student['class_name']) ?></p>
  </div>
  <div style="font-size:.78rem;color:var(--t2)">
    <i class="fas fa-info-circle me-1"></i>
    Showing your Formative Assessment results
  </div>
</div>

<!-- Filter -->
<?php if (!empty($subjList)): ?>
<form method="GET" class="d-flex align-items-end gap-2 flex-wrap mb-3">
  <div>
    <label class="form-label fw-semibold mb-1" style="font-size:.76rem">Subject</label>
    <select name="subject_id" class="form-select form-select-sm" style="min-width:150px" onchange="this.form.submit()">
      <option value="0">All Subjects</option>
      <?php foreach ($subjList as $s): ?>
      <option value="<?= $s['id'] ?>" <?= (int)$s['id']===$filterSubject?'selected':'' ?>><?= h($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if ($filterSubject): ?>
  <a href="?" class="btn btn-sm btn-outline-secondary" style="font-size:.78rem">Clear</a>
  <?php endif; ?>
</form>
<?php endif; ?>

<!-- Legend -->
<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px 16px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:12px;font-size:.73rem;align-items:center">
  <span class="fw-semibold" style="color:#374151">Performance Levels:</span>
  <span class="sm-badge ad">AD <small>Advanced Development</small></span>
  <span class="sm-badge ed">ED <small>Expected Development</small></span>
  <span class="sm-badge emd">EMD <small>Emerging Development</small></span>
</div>

<?php if ($total === 0): ?>
<div class="alert alert-secondary d-flex align-items-center gap-2" style="font-size:.83rem">
  <i class="fas fa-clipboard fa-lg opacity-50"></i>
  <span>No Formative Assessment records found<?= $filterSubject ? ' for this subject' : '' ?>. Results appear here once your teacher submits an assessment.</span>
</div>
<?php else: ?>

<p style="font-size:.78rem;color:var(--t2);margin-bottom:10px">
  Showing <?= ($offset+1) ?>–<?= min($offset+$perPage,$total) ?> of <?= $total ?> assessment<?= $total!==1?'s':'' ?>
</p>

<?php foreach ($entries as $entry):
  $meta     = smSubjectMeta($entry['subject_name']);
  $criteria = json_decode($entry['criteria'], true) ?? [];
  $ratings  = json_decode($entry['ratings'],  true) ?? [];
  $overall  = $entry['overall'] ?? '';
  $remarks  = $entry['remarks'] ?? '';
?>
<div class="entry-card">
  <div class="entry-header">
    <div class="d-flex align-items-center gap-2">
      <div style="width:36px;height:36px;border-radius:10px;background:<?= $meta['bg'] ?>;color:<?= $meta['ic'] ?>;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0">
        <i class="fas <?= $meta['icon'] ?>"></i>
      </div>
      <div>
        <div style="font-weight:700;font-size:.9rem;color:#1e293b"><?= h($entry['subject_name']) ?></div>
        <?php if ($entry['topic']): ?>
        <div style="font-size:.72rem;color:#64748b"><?= h($entry['topic']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <?php if ($overall): ?>
      <div>
        <span style="font-size:.68rem;color:#94a3b8;display:block;margin-bottom:2px">Overall</span>
        <?= smRatingBadge($overall) ?>
      </div>
      <?php endif; ?>
      <div style="text-align:right">
        <div style="font-weight:700;font-size:.95rem;color:#1e293b"><?= date('d M Y', strtotime($entry['assessment_date'])) ?></div>
        <div style="font-size:.7rem;color:#94a3b8"><?= date('l', strtotime($entry['assessment_date'])) ?></div>
      </div>
    </div>
  </div>

  <div class="entry-body">
    <?php if (!empty($criteria)): ?>
    <div class="table-responsive">
      <table class="crit-table w-100">
        <thead>
          <tr>
            <th style="width:35%">Criteria</th>
            <th>Performance Level</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($criteria as $crit):
            $val = $ratings[$crit] ?? '';
          ?>
          <tr>
            <td style="font-weight:600"><?= h($crit) ?></td>
            <td><?= smRatingBadge($val) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if ($remarks): ?>
    <div style="margin-top:10px;padding:8px 12px;background:#f8fafc;border-radius:8px;font-size:.79rem">
      <span style="font-weight:600;color:#374151"><i class="fas fa-comment-alt me-1 text-primary opacity-75"></i>Teacher Remarks:</span>
      <span style="color:#475569;margin-left:4px"><?= h($remarks) ?></span>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<!-- Pagination -->
<?php if ($totalPages > 1):
  $baseUrl = '?' . ($filterSubject ? 'subject_id='.$filterSubject.'&' : '');
?>
<nav class="mt-3">
  <ul class="pagination pagination-sm mb-0 flex-wrap gap-1">
    <li class="page-item <?= $page<=1?'disabled':'' ?>">
      <a class="page-link" href="<?= $baseUrl ?>page=<?= $page-1 ?>"><i class="fas fa-chevron-left" style="font-size:.65rem"></i></a>
    </li>
    <?php for ($p = max(1,$page-3); $p <= min($totalPages,$page+3); $p++): ?>
    <li class="page-item <?= $p===$page?'active':'' ?>">
      <a class="page-link" href="<?= $baseUrl ?>page=<?= $p ?>" style="font-size:.78rem"><?= $p ?></a>
    </li>
    <?php endfor; ?>
    <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
      <a class="page-link" href="<?= $baseUrl ?>page=<?= $page+1 ?>"><i class="fas fa-chevron-right" style="font-size:.65rem"></i></a>
    </li>
  </ul>
</nav>
<?php endif; ?>

<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

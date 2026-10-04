<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/db.php';

$user    = requireAuth('montessori_teacher', 'wing_head', 'vp_montessori');
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);

// Teacher's montessori classes
if ($teacher) {
    $cSt = $db->prepare(
        'SELECT DISTINCT c.id,c.name,c.grade FROM class_subjects cs
         JOIN classes c ON cs.class_id=c.id
         WHERE cs.teacher_id=? AND c.is_montessori=1 ORDER BY c.grade,c.section'
    );
    $cSt->execute([$teacher['id']]);
} else {
    $cSt = $db->prepare('SELECT id,name,grade FROM classes WHERE is_montessori=1 ORDER BY grade,section');
    $cSt->execute([]);
}
$assignedClasses = $cSt->fetchAll();

$selClassId = (int)($_GET['class_id'] ?? (empty($assignedClasses) ? 0 : $assignedClasses[0]['id']));
$selStudent = (int)($_GET['student_id'] ?? 0);
$selFocus   = trim($_GET['focus'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 25;
$offset     = ($page - 1) * $perPage;

// Class info
$classInfo = null;
if ($selClassId) {
    $ci = $db->prepare('SELECT name FROM classes WHERE id=?');
    $ci->execute([$selClassId]); $classInfo = $ci->fetch();
}

// Student list for filter dropdown
$students = [];
if ($selClassId) {
    $stuSt = $db->prepare(
        'SELECT st.id,u.name FROM students st JOIN users u ON st.user_id=u.id
         WHERE st.class_id=? AND st.deleted_at IS NULL ORDER BY u.name'
    );
    $stuSt->execute([$selClassId]);
    $students = $stuSt->fetchAll();
}

// History rows
$history    = [];
$total      = 0;

if ($selClassId) {
    try {
        $whereParts = ['mar.class_id=?'];
        $params     = [$selClassId];
        if ($selStudent) { $whereParts[] = 'mar.student_id=?'; $params[] = $selStudent; }
        if ($selFocus)   { $whereParts[] = 'mar.subject_focus=?'; $params[] = $selFocus; }
        $where = implode(' AND ', $whereParts);

        $cntSt = $db->prepare("SELECT COUNT(*) FROM montessori_anecdotal_records mar WHERE $where");
        $cntSt->execute($params);
        $total = (int)$cntSt->fetchColumn();

        $params[] = $perPage;
        $params[] = $offset;
        $dataSt = $db->prepare(
            "SELECT mar.id, mar.student_id, mar.subject_focus, mar.record_date, mar.topic,
                    LEFT(mar.observation,200) AS obs_preview,
                    LENGTH(mar.observation) AS obs_len,
                    u.name AS student_name,
                    tu.name AS teacher_name
             FROM montessori_anecdotal_records mar
             LEFT JOIN students st ON mar.student_id=st.id
             LEFT JOIN users u ON st.user_id=u.id
             JOIN teachers t ON mar.teacher_id=t.id
             JOIN users tu ON t.user_id=tu.id
             WHERE $where
             ORDER BY mar.record_date DESC, mar.id DESC
             LIMIT ? OFFSET ?"
        );
        $dataSt->execute($params);
        $history = $dataSt->fetchAll();
    } catch (Exception $e) {}
}

// Distinct focus values for filter
$focusOptions = [];
if ($selClassId) {
    try {
        $foSt = $db->prepare(
            'SELECT DISTINCT subject_focus FROM montessori_anecdotal_records WHERE class_id=? ORDER BY subject_focus'
        );
        $foSt->execute([$selClassId]);
        $focusOptions = $foSt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}
}

$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;

$portalRole = in_array($user['role'], ['wing_head','vp_montessori']) ? $user['role'] : 'montessori_teacher';
pageHead('Anecdotal Record History', $portalRole);
$links = match($user['role']) {
    'wing_head'     => getWingHeadLinks(),
    'vp_montessori' => getVpMontessoriLinks(),
    default         => getMonteTeacherLinks(),
};
?>
<style>
.obs-preview{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;color:#475569;font-size:.79rem}
.focus-badge{display:inline-block;background:#e0f2fe;color:#0369a1;border-radius:10px;padding:2px 9px;font-size:.7rem;font-weight:600;white-space:nowrap}
</style>

<div class="portal-wrap">
<?php sidebar($portalRole,'anecdotal-records',$links,$user); ?>
<div class="main-area">
<?php topbar('Anecdotal Record History',$user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Back + title -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <a href="<?= url('/portal/montessori/anecdotal-records.php') . ($selClassId ? '?class_id='.$selClassId.($selStudent ? '&student_id='.$selStudent : '') : '') ?>"
     class="btn btn-sm btn-outline-secondary" style="font-size:.8rem">
    <i class="fas fa-arrow-left me-1"></i>Back to Anecdotal Records
  </a>
  <h5 class="mb-0" style="font-size:1rem;font-weight:700">
    <i class="fas fa-history me-2 text-primary"></i>Record History
    <?php if ($classInfo): ?><span class="text-muted fw-normal" style="font-size:.85rem">&mdash; <?= h($classInfo['name']) ?></span><?php endif; ?>
  </h5>
</div>

<!-- Filter form -->
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
    <select name="student_id" class="form-select form-select-sm" style="min-width:160px">
      <option value="0">All Students</option>
      <?php foreach ($students as $s): ?>
      <option value="<?= $s['id'] ?>" <?= (int)$s['id']===$selStudent?'selected':'' ?>><?= h($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if (!empty($focusOptions)): ?>
  <div>
    <label class="form-label fw-semibold mb-1" style="font-size:.77rem">Focus Area</label>
    <select name="focus" class="form-select form-select-sm" style="min-width:160px">
      <option value="">All Focus Areas</option>
      <?php foreach ($focusOptions as $fo): ?>
      <option value="<?= h($fo) ?>" <?= $selFocus===$fo?'selected':'' ?>><?= h($fo) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <button type="submit" class="btn btn-sm btn-primary" style="font-size:.78rem">
    <i class="fas fa-filter me-1"></i>Filter
  </button>
  <?php if ($selStudent || $selFocus || $page > 1): ?>
  <a href="?class_id=<?= $selClassId ?>" class="btn btn-sm btn-outline-secondary" style="font-size:.78rem">Clear</a>
  <?php endif; ?>
</form>

<?php if (!$selClassId): ?>
<div class="alert alert-info" style="font-size:.83rem"><i class="fas fa-info-circle me-2"></i>No Montessori classes found.</div>
<?php elseif (empty($history)): ?>
<div class="alert alert-secondary" style="font-size:.83rem">
  <i class="fas fa-clipboard me-2"></i>No anecdotal records found<?= ($selStudent||$selFocus) ? ' for the selected filters' : ' for this class' ?>.
  <a href="<?= url('/portal/montessori/anecdotal-records.php') ?>?class_id=<?= $selClassId ?>" class="alert-link ms-1">Create the first record.</a>
</div>
<?php else: ?>

<div class="sec-card mb-3">
  <div class="sec-card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-table me-2"></i>Anecdotal Records</span>
    <span class="badge bg-primary" style="font-size:.72rem"><?= $total ?> record<?= $total!==1?'s':'' ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.82rem">
      <thead>
        <tr style="background:#f8fafc">
          <th style="padding:9px 12px;font-size:.74rem;font-weight:600">Date</th>
          <th style="padding:9px 12px;font-size:.74rem;font-weight:600">Student</th>
          <th style="padding:9px 12px;font-size:.74rem;font-weight:600">Focus</th>
          <th style="padding:9px 12px;font-size:.74rem;font-weight:600">Topic / Context</th>
          <th style="padding:9px 12px;font-size:.74rem;font-weight:600">Observation</th>
          <?php if (!$teacher): ?><th style="padding:9px 12px;font-size:.74rem;font-weight:600">Teacher</th><?php endif; ?>
          <th style="padding:9px 12px;font-size:.74rem;font-weight:600;text-align:center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($history as $row): ?>
        <tr>
          <td style="padding:9px 12px;white-space:nowrap;font-weight:600;color:#1e293b;vertical-align:top">
            <?= date('d M Y', strtotime($row['record_date'])) ?>
            <div style="font-size:.7rem;color:#94a3b8;font-weight:400"><?= date('l', strtotime($row['record_date'])) ?></div>
          </td>
          <td style="padding:9px 12px;font-weight:600;color:#374151;white-space:nowrap;vertical-align:top">
            <?= h($row['student_name'] ?: '—') ?>
          </td>
          <td style="padding:9px 12px;vertical-align:top">
            <span class="focus-badge"><?= h($row['subject_focus']) ?></span>
          </td>
          <td style="padding:9px 12px;color:#475569;vertical-align:top">
            <?= $row['topic'] ? h($row['topic']) : '<span style="color:#94a3b8">—</span>' ?>
          </td>
          <td style="padding:9px 12px;vertical-align:top;max-width:280px">
            <div class="obs-preview"><?= h($row['obs_preview']) ?><?= (int)$row['obs_len']>200?'…':'' ?></div>
          </td>
          <?php if (!$teacher): ?>
          <td style="padding:9px 12px;font-size:.77rem;color:#475569;white-space:nowrap;vertical-align:top">
            <?= h($row['teacher_name']) ?>
          </td>
          <?php endif; ?>
          <td style="padding:9px 12px;text-align:center;vertical-align:top">
            <div class="d-flex gap-1 justify-content-center flex-wrap">
              <a href="<?= url('/portal/montessori/anecdotal-records.php') ?>?class_id=<?= $selClassId ?>&student_id=<?= $row['student_id'] ?>&edit=<?= $row['id'] ?>"
                 class="btn btn-sm btn-outline-primary" style="font-size:.7rem;padding:3px 8px">
                <i class="fas fa-edit me-1"></i>Edit
              </a>
              <a href="<?= url('/portal/montessori/anecdotal-print.php') ?>?id=<?= $row['id'] ?>" target="_blank"
                 class="btn btn-sm btn-outline-success" style="font-size:.7rem;padding:3px 8px">
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
  <?php if ($totalPages > 1):
    $baseUrl = '?class_id='.$selClassId
        .($selStudent ? '&student_id='.$selStudent : '')
        .($selFocus   ? '&focus='.urlencode($selFocus) : '');
  ?>
  <div style="padding:12px 14px;border-top:1px solid #f1f5f9">
    <nav>
      <ul class="pagination pagination-sm mb-0 flex-wrap gap-1">
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

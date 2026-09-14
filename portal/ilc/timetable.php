<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp');
requirePermission('ilc_timetable');
$db   = getDB();

$classId  = (int)($_GET['class_id'] ?? 0);
$classes  = $db->query('SELECT * FROM classes WHERE is_ilc=1 ORDER BY name')->fetchAll();
$days     = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
$subjects = getAllSubjects();
$teachers = $db->query(
    'SELECT t.id, u.name FROM teachers t
     JOIN users u ON u.id=t.user_id
     WHERE u.status="active" AND t.is_ilc=1
     ORDER BY u.name'
)->fetchAll();

// Fallback: if no ILC teachers, show all teachers
if (empty($teachers)) {
    $teachers = $db->query(
        'SELECT t.id, u.name FROM teachers t JOIN users u ON u.id=t.user_id WHERE u.status="active" ORDER BY u.name'
    )->fetchAll();
}

// ── Handle add/delete period ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cid    = (int)($_POST['class_id'] ?? 0);

    if ($action === 'add_period' && $cid) {
        $dayVal    = strtolower(trim($_POST['day'] ?? ''));
        $periodVal = (int)($_POST['period'] ?? 0);
        $validDays = ['monday','tuesday','wednesday','thursday','friday'];
        if (!in_array($dayVal, $validDays) || $periodVal < 1 || $periodVal > 8) {
            setFlash('danger', 'Invalid day or period number.');
            redirect('/portal/ilc/timetable.php?class_id=' . $cid);
        }
        try {
            $db->prepare(
                'INSERT INTO timetable (class_id, subject_id, teacher_id, day, period)
                 VALUES (?,?,?,?,?)'
            )->execute([
                $cid,
                $_POST['subject_id'] ?: null,
                $_POST['teacher_id'] ?: null,
                $dayVal,
                $periodVal,
            ]);
            logActivity($user['id'], 'ilc_timetable_edit', "ILC VP added period for class #$cid");
            setFlash('success', 'Period added.');
        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
    }
    if ($action === 'delete_period') {
        $db->prepare('DELETE FROM timetable WHERE id=?')->execute([(int)$_POST['period_id']]);
        setFlash('success', 'Period removed.');
    }
    redirect('/portal/ilc/timetable.php?class_id=' . $cid);
}

$timetable = [];
if ($classId) {
    $st = $db->prepare(
        'SELECT tt.*, sub.name AS subject_name, u.name AS teacher_name
         FROM timetable tt
         LEFT JOIN subjects sub ON sub.id = tt.subject_id
         LEFT JOIN teachers t   ON t.id   = tt.teacher_id
         LEFT JOIN users u      ON u.id   = t.user_id
         WHERE tt.class_id = ?
         ORDER BY FIELD(tt.day,"monday","tuesday","wednesday","thursday","friday"), tt.period'
    );
    $st->execute([$classId]);
    $timetable = $st->fetchAll();
}
$grouped = [];
foreach ($timetable as $row) $grouped[$row['day']][] = $row;

pageHead('ILC Timetable', 'ilc_vp');
$links = getIlcLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'timetable', $links, $user); ?>
<div class="main-area">
<?php topbar('ILC Timetable', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Class selector -->
<div class="sec-card mb-3">
  <div class="sec-card-header" style="background:linear-gradient(90deg,#0c4a6e,#0891b2);color:#fff">
    <i class="fas fa-filter me-2"></i>Select ILC Class
  </div>
  <div style="padding:12px 16px">
    <form method="GET" class="d-flex gap-2 align-items-center">
      <select name="class_id" class="form-select form-select-sm" style="width:200px" onchange="this.form.submit()">
        <option value="0">Select class…</option>
        <?php foreach ($classes as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $classId==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<?php if (empty($classes)): ?>
<div class="alert alert-info" style="font-size:.86rem">
  <i class="fas fa-info-circle me-2"></i>No ILC classes found. Classes must have <code>is_ilc=1</code> in the database.
</div>
<?php elseif ($classId): ?>
<!-- Add period -->
<div class="sec-card mb-3">
  <div class="sec-card-header d-flex justify-content-between align-items-center"
       style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#addPeriod">
    <span><i class="fas fa-plus me-2"></i>Add Period</span>
    <i class="fas fa-chevron-down"></i>
  </div>
  <div id="addPeriod" class="collapse">
    <div style="padding:14px 16px">
      <form method="POST" class="row g-2">
        <input type="hidden" name="action" value="add_period">
        <input type="hidden" name="class_id" value="<?= $classId ?>">
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.8rem">Day</label>
          <select name="day" class="form-select form-select-sm" required>
            <?php foreach ($days as $d): ?><option value="<?= strtolower($d) ?>"><?= $d ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label fw-semibold" style="font-size:.8rem">Period</label>
          <input type="number" name="period" class="form-control form-control-sm" min="1" max="8" value="1" required>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold" style="font-size:.8rem">Subject</label>
          <select name="subject_id" class="form-select form-select-sm">
            <option value="">—</option>
            <?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold" style="font-size:.8rem">ILC Teacher</label>
          <select name="teacher_id" class="form-select form-select-sm">
            <option value="">—</option>
            <?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= h($t['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-sm" style="background:#0891b2;color:#fff">Add Period</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Timetable grid -->
<div class="sec-card">
  <div class="sec-card-header" style="background:linear-gradient(90deg,#0c4a6e,#0891b2);color:#fff">
    <i class="fas fa-table me-2"></i>Timetable
  </div>
  <?php if (empty($timetable)): ?>
  <div style="padding:32px;text-align:center;color:var(--t2);font-size:.85rem">No periods added yet.</div>
  <?php else: ?>
  <?php foreach ($days as $day): ?>
  <?php $dayKey = strtolower($day); if (!isset($grouped[$dayKey])) continue; ?>
  <div style="padding:10px 16px 4px;font-weight:700;font-size:.8rem;color:var(--t2);text-transform:uppercase;border-top:1px solid var(--border)"><?= $day ?></div>
  <div class="table-responsive">
    <table class="table table-sm mb-0" style="font-size:.82rem">
      <thead class="table-light"><tr><th>Period</th><th>Subject</th><th>Teacher</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($grouped[$dayKey] as $p): ?>
        <tr>
          <td><?= (int)$p['period'] ?></td>
          <td><?= h($p['subject_name'] ?: '—') ?></td>
          <td><?= h($p['teacher_name'] ?: '—') ?></td>
          <td>
            <form method="POST" class="d-inline" onsubmit="return confirm('Remove this period?')">
              <input type="hidden" name="action" value="delete_period">
              <input type="hidden" name="period_id" value="<?= $p['id'] ?>">
              <input type="hidden" name="class_id" value="<?= $classId ?>">
              <button class="btn btn-xs btn-outline-danger" style="font-size:.72rem;padding:1px 6px">Del</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

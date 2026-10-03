<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('teacher', 'montessori_teacher', 'ilc_teacher', 'wing_head');
requirePermission('timetable');
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);
if (!$teacher) {
    if ($user['role'] !== 'wing_head') { setFlash('danger','Teacher record not found.'); redirect('/portal/index.php'); }
    $teacher = ['id' => 0, 'subject_id' => 0, 'name' => $user['name'], 'is_ilc' => 0];
}

// Determine teacher's wing for timetable documents
$teacherWing = 'main';
try {
    if ($user['role'] === 'ilc_teacher' || (isset($teacher['is_ilc']) && $teacher['is_ilc'])) {
        $teacherWing = 'ilc';
    } elseif ($user['role'] === 'montessori_teacher' || $user['role'] === 'wing_head') {
        $teacherWing = 'montessori';
    }
} catch (Exception $e) {}

$ttDocs = [];
try {
    $tdst = $db->prepare("SELECT td.* FROM timetable_documents td WHERE td.wing=? OR td.wing='all' ORDER BY td.created_at DESC");
    $tdst->execute([$teacherWing]);
    $ttDocs = $tdst->fetchAll();
} catch (Exception $e) {}

// Get teacher's timetable
$st = $db->prepare(
    'SELECT tt.day, tt.period, tt.room,
            sb.name AS subject_name, sb.code AS subject_code,
            c.name AS class_name
     FROM timetable tt
     LEFT JOIN subjects sb ON tt.subject_id = sb.id
     LEFT JOIN classes c  ON tt.class_id    = c.id
     WHERE tt.teacher_id = ?
     ORDER BY FIELD(tt.day,"monday","tuesday","wednesday","thursday","friday"), tt.period'
);
$st->execute([$teacher['id']]);
$rows = $st->fetchAll();
$grid = [];
foreach ($rows as $r) { $grid[$r['day']][$r['period']] = $r; }

$days    = ['monday','tuesday','wednesday','thursday','friday'];
$periods = range(1, 8);

pageHead('Timetable', $user['role']);
$links = match($user['role']) {
    'montessori_teacher' => getMonteTeacherLinks(),
    'ilc_teacher'        => getIlcTeacherLinks(),
    'wing_head'          => getWingHeadLinks(),
    default              => getTeacherLinks(),
};
?>
<div class="portal-wrap">
<?php sidebar($user['role'], 'timetable', $links, $user); ?>
<div class="main-area">
<?php topbar('My Timetable', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-table me-2"></i>Weekly Schedule — <?= h($teacher['name']) ?></div>
  <div class="table-responsive">
    <table class="table table-bordered mb-0" style="font-size:.84rem; min-width:700px;">
      <thead class="table-dark">
        <tr>
          <th style="width:80px">Period</th>
          <?php foreach ($days as $d): ?>
            <th class="text-center"><?= ucfirst($d) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($periods as $p): ?>
        <tr>
          <td class="fw-bold text-center" style="background:#f8fafc">P<?= $p ?></td>
          <?php foreach ($days as $d): ?>
            <?php $cell = $grid[$d][$p] ?? null; ?>
            <td class="text-center" style="vertical-align:middle;padding:8px 6px">
              <?php if ($cell): ?>
                <div class="fw-semibold" style="color:var(--accent);font-size:.83rem"><?= h($cell['class_name']) ?></div>
                <div style="font-size:.78rem;color:#6b7280"><?= h($cell['subject_name']) ?></div>
                <?php if ($cell['room']): ?>
                  <div style="font-size:.72rem;color:#9ca3af"><i class="fas fa-door-open me-1"></i><?= h($cell['room']) ?></div>
                <?php endif; ?>
              <?php else: ?>
                <span style="color:#d1d5db;font-size:.8rem">—</span>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</div>

<?php if (!empty($ttDocs)): ?>
<div class="sec-card mt-3">
  <div class="sec-card-header"><i class="fas fa-file-alt me-2"></i>Timetable Documents</div>
  <div style="padding:12px 16px">
    <?php foreach ($ttDocs as $doc):
      $iconMap = ['pdf' => 'fa-file-pdf text-danger', 'xlsx' => 'fa-file-excel text-success', 'xls' => 'fa-file-excel text-success', 'doc' => 'fa-file-word text-primary', 'docx' => 'fa-file-word text-primary'];
      $icon    = $iconMap[$doc['file_type']] ?? 'fa-file text-secondary';
    ?>
    <div class="d-flex align-items-center gap-3 mb-2 p-2" style="background:#f9fafb;border-radius:6px;border:1px solid #e5e7eb">
      <i class="fas <?= $icon ?> fa-lg"></i>
      <div class="flex-grow-1">
        <div class="fw-semibold" style="font-size:.86rem"><?= h($doc['title']) ?></div>
        <div style="font-size:.76rem;color:#6b7280"><?= h($doc['academic_year']) ?><?= $doc['notes'] ? ' — ' . h($doc['notes']) : '' ?></div>
      </div>
      <?php if (($doc['file_type'] ?? '') === 'pdf'): ?>
      <a href="<?= url('/portal/api/serve-document.php') ?>?type=timetable&id=<?= $doc['id'] ?>&inline=1" target="_blank"
         class="btn btn-xs btn-outline-info me-1" style="font-size:.76rem;padding:3px 10px;white-space:nowrap">
        <i class="fas fa-eye me-1"></i>View
      </a>
      <?php endif; ?>
      <a href="<?= url('/portal/api/serve-document.php') ?>?type=timetable&id=<?= $doc['id'] ?>"
         class="btn btn-xs btn-primary" style="font-size:.76rem;padding:3px 10px;white-space:nowrap">
        <i class="fas fa-download me-1"></i>Download
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

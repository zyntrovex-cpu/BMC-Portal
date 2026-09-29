<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('teacher', 'montessori_teacher', 'ilc_teacher');
$db      = getDB();

// Detect teacher wing
$teacher     = getTeacherByUserId($user['id']);
$teacherWing = $teacher['wing'] ?? 'main';

// Load published date sheets for this teacher's wing (+ 'all')
$sheets = [];
try {
    $st = $db->prepare(
        "SELECT ds.*, u.name AS created_by_name
         FROM exam_date_sheets ds
         JOIN users u ON ds.created_by = u.id
         WHERE ds.status = 'published'
           AND (ds.wing = ? OR ds.wing = 'all')
         ORDER BY ds.academic_year DESC, ds.created_at DESC"
    );
    $st->execute([$teacherWing]);
    $sheets = $st->fetchAll();
} catch (Exception $e) {}

// Fetch entries for each sheet
$entries = [];
try {
    foreach ($sheets as $sheet) {
        $est = $db->prepare(
            "SELECT e.*, c.name AS class_name
             FROM exam_date_sheet_entries e
             LEFT JOIN classes c ON c.id = e.class_id
             WHERE e.date_sheet_id = ?
             ORDER BY e.sort_order, e.exam_date, e.start_time"
        );
        $est->execute([$sheet['id']]);
        $entries[$sheet['id']] = $est->fetchAll();
    }
} catch (Exception $e) {}

$isIlc = ($user['role'] === 'ilc_teacher');
$isMonteTeacher = ($user['role'] === 'montessori_teacher');
if ($isIlc) {
    $links = getIlcLinks();
    $portal = 'ilc_vp';
} elseif ($isMonteTeacher) {
    $links = getMonteTeacherLinks();
    $portal = 'teacher';
} else {
    $links = getTeacherLinks();
    $portal = 'teacher';
}

pageHead('Exam Date Sheets', $portal);
?>
<div class="portal-wrap">
<?php sidebar($portal, 'exam-datesheet', $links, $user); ?>
<div class="main-area">
<?php topbar('Exam Date Sheets', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold" style="color:var(--accent)"><i class="fas fa-calendar-day me-2"></i>Exam Date Sheets</h5>
  <span class="badge" style="background:var(--accent);font-size:.8rem;padding:6px 12px">
    Wing: <?= h(ucfirst($teacherWing)) ?>
  </span>
</div>

<?php if (empty($sheets)): ?>
<div class="sec-card">
  <div class="sec-card-body text-center py-5 text-muted">
    <i class="fas fa-calendar-times fa-3x mb-3" style="opacity:.3"></i>
    <p class="mb-0">No published exam date sheets available at this time.</p>
  </div>
</div>
<?php else: ?>

<?php foreach ($sheets as $sheet): ?>
<?php $sheetEntries = $entries[$sheet['id']] ?? []; ?>
<div class="sec-card mb-3">
  <div class="sec-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span class="fw-bold"><i class="fas fa-calendar-day me-2"></i><?= h($sheet['title']) ?></span>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <?php
        $wl = match($sheet['wing']) { 'ilc'=>'ILC', 'montessori'=>'Montessori', 'all'=>'All Wings', default=>'Main Wing' };
        $wc = match($sheet['wing']) { 'ilc'=>'#0891b2', 'montessori'=>'#7c3aed', 'all'=>'#374151', default=>'#2563eb' };
      ?>
      <span style="font-size:.75rem;background:<?= $wc ?>;color:#fff;padding:2px 10px;border-radius:20px;font-weight:700"><?= h($wl) ?></span>
      <span class="badge bg-success">Published</span>
      <small class="text-muted">AY: <?= h($sheet['academic_year']) ?></small>
    </div>
  </div>
  <div class="sec-card-body p-0">
    <?php if ($sheet['notes']): ?>
    <div class="px-3 pt-3 pb-1 text-muted" style="font-size:.85rem"><i class="fas fa-info-circle me-1"></i><?= h($sheet['notes']) ?></div>
    <?php endif; ?>
    <?php if (empty($sheetEntries)): ?>
    <div class="text-center text-muted py-4" style="font-size:.88rem">No exam entries yet.</div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-bordered table-hover mb-0" style="font-size:.84rem;min-width:700px">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Day</th>
            <th>Start</th>
            <th>End</th>
            <th>Subject</th>
            <th>Class</th>
            <th>Venue</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php $n = 1; foreach ($sheetEntries as $e): ?>
          <tr>
            <td class="text-muted"><?= $n++ ?></td>
            <td class="fw-semibold" style="white-space:nowrap"><?= h(date('d M Y', strtotime($e['exam_date']))) ?></td>
            <td><?= h(date('l', strtotime($e['exam_date']))) ?></td>
            <td><?= h(date('h:i A', strtotime($e['start_time']))) ?></td>
            <td><?= h(date('h:i A', strtotime($e['end_time']))) ?></td>
            <td class="fw-semibold" style="color:var(--accent)"><?= h($e['subject']) ?></td>
            <td><?= $e['class_name'] ? h($e['class_name']) : '<span class="text-muted">All Classes</span>' ?></td>
            <td><?= $e['venue'] ? h($e['venue']) : '<span class="text-muted">—</span>' ?></td>
            <td style="font-size:.8rem;color:#6b7280"><?= $e['notes'] ? h($e['notes']) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
    <div class="px-3 py-2 text-muted" style="font-size:.78rem;border-top:1px solid #e5e7eb">
      Created by <?= h($sheet['created_by_name']) ?> &middot; <?= h(date('d M Y', strtotime($sheet['created_at']))) ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div><!-- page-content -->
</div><!-- main-area -->
</div><!-- portal-wrap -->
<?php pageFooter(); ?>

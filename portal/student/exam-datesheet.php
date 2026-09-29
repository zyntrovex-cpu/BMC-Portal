<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('student');
$student = getStudentByUserId($user['id']);
if (!$student) { setFlash('danger', 'Student record not found.'); redirect('/portal/index.php'); }

$db = getDB();

// Determine student wing
$studentWing = 'main';
try {
    $wst = $db->prepare('SELECT COALESCE(wing,"main") AS wing FROM classes WHERE id = ?');
    $wst->execute([$student['class_id']]);
    $studentWing = $wst->fetchColumn() ?: 'main';
} catch (Exception $e) {}

// Load published date sheets for student's wing (+ 'all')
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
    $st->execute([$studentWing]);
    $sheets = $st->fetchAll();
} catch (Exception $e) {}

// Fetch entries filtered to this student's class (or all-class entries)
$entries = [];
try {
    foreach ($sheets as $sheet) {
        $est = $db->prepare(
            "SELECT e.*, c.name AS class_name
             FROM exam_date_sheet_entries e
             LEFT JOIN classes c ON c.id = e.class_id
             WHERE e.date_sheet_id = ?
               AND (e.class_id IS NULL OR e.class_id = ?)
             ORDER BY e.sort_order, e.exam_date, e.start_time"
        );
        $est->execute([$sheet['id'], $student['class_id']]);
        $entries[$sheet['id']] = $est->fetchAll();
    }
} catch (Exception $e) {}

$links = getStudentLinks();
pageHead('Exam Date Sheets', 'student');
?>
<div class="portal-wrap">
<?php sidebar('student', 'exam-datesheet', $links, $user); ?>
<div class="main-area">
<?php topbar('Exam Date Sheets', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold" style="color:var(--accent)"><i class="fas fa-calendar-day me-2"></i>My Exam Schedule</h5>
  <span class="badge" style="background:var(--accent);font-size:.8rem;padding:6px 12px">
    Class: <?= h($student['class_name'] ?? '') ?>
  </span>
</div>

<?php if (empty($sheets)): ?>
<div class="sec-card">
  <div class="sec-card-body text-center py-5 text-muted">
    <i class="fas fa-calendar-times fa-3x mb-3" style="opacity:.3"></i>
    <p class="mb-0">No exam date sheets have been published for your class yet.</p>
  </div>
</div>
<?php else: ?>

<?php $hasAny = false; foreach ($sheets as $sheet): ?>
<?php $sheetEntries = $entries[$sheet['id']] ?? []; ?>
<?php if (empty($sheetEntries)) continue; ?>
<?php $hasAny = true; ?>
<div class="sec-card mb-3">
  <div class="sec-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span class="fw-bold"><i class="fas fa-calendar-day me-2"></i><?= h($sheet['title']) ?></span>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <span class="badge bg-success">Published</span>
      <small class="text-muted">AY: <?= h($sheet['academic_year']) ?></small>
    </div>
  </div>
  <div class="sec-card-body p-0">
    <?php if ($sheet['notes']): ?>
    <div class="px-3 pt-3 pb-1 text-muted" style="font-size:.85rem"><i class="fas fa-info-circle me-1"></i><?= h($sheet['notes']) ?></div>
    <?php endif; ?>
    <div class="table-responsive">
      <table class="table table-bordered table-hover mb-0" style="font-size:.84rem;min-width:600px">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Day</th>
            <th>Time</th>
            <th>Subject</th>
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
            <td style="white-space:nowrap">
              <?= h(date('h:i A', strtotime($e['start_time']))) ?>
              <span class="text-muted mx-1">–</span>
              <?= h(date('h:i A', strtotime($e['end_time']))) ?>
            </td>
            <td class="fw-bold" style="color:var(--accent)"><?= h($e['subject']) ?></td>
            <td><?= $e['venue'] ? h($e['venue']) : '<span class="text-muted">—</span>' ?></td>
            <td style="font-size:.8rem;color:#6b7280"><?= $e['notes'] ? h($e['notes']) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php if (!$hasAny): ?>
<div class="sec-card">
  <div class="sec-card-body text-center py-5 text-muted">
    <i class="fas fa-calendar-times fa-3x mb-3" style="opacity:.3"></i>
    <p class="mb-0">No exam entries have been published for your class yet.</p>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

</div><!-- page-content -->
</div><!-- main-area -->
</div><!-- portal-wrap -->
<?php pageFooter(); ?>

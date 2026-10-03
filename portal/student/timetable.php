<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('student');
$student = getStudentByUserId($user['id']);
if (!$student) { setFlash('danger','Student record not found.'); redirect('/portal/index.php'); }

$db = getDB();

// Determine student's wing
$wing = 'main';
try {
    $cr = $db->prepare('SELECT COALESCE(is_ilc,0) AS is_ilc, COALESCE(is_montessori,0) AS is_montessori FROM classes WHERE id=?');
    $cr->execute([(int)$student['class_id']]);
    $row  = $cr->fetch();
    $wing = $row ? ($row['is_ilc'] ? 'ilc' : ($row['is_montessori'] ? 'montessori' : 'main')) : 'main';
} catch (Exception $e) {}

// Latest active timetable document for this wing — retrieved fresh from DB every load
$doc = null;
try {
    $st = $db->prepare(
        "SELECT td.*, u.name AS uploader_name
         FROM timetable_documents td
         LEFT JOIN users u ON u.id = td.uploaded_by
         WHERE (td.wing = ? OR td.wing = 'all') AND td.status = 'active'
         ORDER BY td.created_at DESC
         LIMIT 1"
    );
    $st->execute([$wing]);
    $doc = $st->fetch() ?: null;
} catch (Exception $e) {}

pageHead('Timetable', 'student');
$links = getStudentLinks();
?>
<div class="portal-wrap">
<?php sidebar('student', 'timetable', $links, $user); ?>
<div class="main-area">
<?php topbar('Timetable', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if ($doc): ?>
<?php
  $iconMap = [
    'pdf'  => ['icon' => 'fa-file-pdf',   'color' => '#dc2626', 'bg' => '#fef2f2'],
    'xlsx' => ['icon' => 'fa-file-excel',  'color' => '#16a34a', 'bg' => '#f0fdf4'],
    'xls'  => ['icon' => 'fa-file-excel',  'color' => '#16a34a', 'bg' => '#f0fdf4'],
    'doc'  => ['icon' => 'fa-file-word',   'color' => '#2563eb', 'bg' => '#eff6ff'],
    'docx' => ['icon' => 'fa-file-word',   'color' => '#2563eb', 'bg' => '#eff6ff'],
  ];
  $fm    = $iconMap[$doc['file_type'] ?? ''] ?? ['icon' => 'fa-file', 'color' => '#6b7280', 'bg' => '#f9fafb'];
  $isPdf = ($doc['file_type'] ?? '') === 'pdf';
  $szFmt = $doc['file_size'] > 1048576
      ? round($doc['file_size'] / 1048576, 1) . ' MB'
      : round($doc['file_size'] / 1024) . ' KB';
  $baseUrl = url('/portal/api/serve-document.php') . '?type=timetable&id=' . (int)$doc['id'];
?>
<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-table me-2"></i>Class Timetable — <?= h($student['class_name']) ?></span>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary no-print">
      <i class="fas fa-print me-1"></i>Print Page
    </button>
  </div>
  <div style="padding:28px">
    <div class="d-flex align-items-start gap-4 mb-4 flex-wrap">
      <div style="width:64px;height:64px;background:<?= $fm['bg'] ?>;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i class="fas <?= $fm['icon'] ?> fa-2x" style="color:<?= $fm['color'] ?>"></i>
      </div>
      <div>
        <div class="fw-bold" style="font-size:1.1rem;color:var(--accent)"><?= h($doc['title']) ?></div>
        <div style="font-size:.83rem;color:var(--t2);margin-top:4px">
          Academic Year: <strong><?= h($doc['academic_year']) ?></strong>
          &nbsp;&middot;&nbsp; <?= strtoupper(h($doc['file_type'] ?? '')) ?>
          &nbsp;&middot;&nbsp; <?= $szFmt ?>
          <?= $doc['notes'] ? ' &nbsp;&middot;&nbsp; ' . h($doc['notes']) : '' ?>
        </div>
        <div style="font-size:.76rem;color:var(--t3);margin-top:3px">
          Uploaded on <?= fDate($doc['created_at']) ?>
          <?= $doc['uploader_name'] ? ' by ' . h($doc['uploader_name']) : '' ?>
        </div>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= $baseUrl . ($isPdf ? '&inline=1' : '') ?>" target="_blank"
         class="btn btn-outline-info no-print">
        <i class="fas fa-eye me-2"></i>View / Open
      </a>
      <a href="<?= $baseUrl ?>"
         class="btn btn-primary no-print">
        <i class="fas fa-download me-2"></i>Download
      </a>
      <a href="<?= $baseUrl ?>&inline=1" target="_blank"
         class="btn btn-outline-secondary no-print"
         onclick="<?= $isPdf ? 'return true' : "window.location.href='" . $baseUrl . "';return false" ?>">
        <i class="fas fa-print me-2"></i>Print
      </a>
    </div>
  </div>
</div>
<?php else: ?>
<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-table me-2"></i>Class Timetable — <?= h($student['class_name']) ?></div>
  <div style="padding:56px;text-align:center">
    <i class="fas fa-calendar-alt fa-3x mb-3" style="color:var(--accent);opacity:.25"></i>
    <div class="fw-semibold mb-2" style="font-size:1rem;color:var(--t1)">No timetable available yet</div>
    <div style="font-size:.86rem;color:var(--t2)">Your timetable will appear here once it is uploaded by the administration.</div>
  </div>
</div>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<style>
@media print {
  .no-print,.sidebar,.topbar,.portal-header { display: none !important; }
  .main-area { margin: 0 !important; padding: 0 !important; }
  .page-content { padding: 10px !important; }
  .sec-card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>
</body></html>

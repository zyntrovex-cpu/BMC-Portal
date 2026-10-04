<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/db.php';

$user = requireAuth('examination_head');
$db   = getDB();

// ── Stats ─────────────────────────────────────────────────────────────────────
$stats = ['students'=>0,'teachers'=>0,'datesheets'=>0,'syllabuses'=>0,'timetables'=>0,'notices'=>0,'assessments'=>0];

try {
    // Main campus students (Class 1–12: not montessori, not ILC)
    $st = $db->query(
        "SELECT COUNT(*) FROM students s
         JOIN classes c ON c.id = s.class_id
         WHERE COALESCE(c.is_ilc,0)=0 AND COALESCE(c.is_montessori,0)=0
           AND s.deleted_at IS NULL"
    );
    $stats['students'] = (int)$st->fetchColumn();
} catch (Exception $e) {}

try {
    $st = $db->query("SELECT COUNT(*) FROM teachers WHERE COALESCE(wing,'main')='main'");
    $stats['teachers'] = (int)$st->fetchColumn();
} catch (Exception $e) {}

try {
    $st = $db->query("SELECT COUNT(*) FROM exam_date_sheets WHERE wing IN ('main','all')");
    $stats['datesheets'] = (int)$st->fetchColumn();
} catch (Exception $e) {}

try {
    $st = $db->query("SELECT COUNT(*) FROM syllabus_documents");
    $stats['syllabuses'] = (int)$st->fetchColumn();
} catch (Exception $e) {}

try {
    $st = $db->query("SELECT COUNT(*) FROM timetable_documents WHERE wing IN ('main','all')");
    $stats['timetables'] = (int)$st->fetchColumn();
} catch (Exception $e) {}

try {
    $st = $db->query("SELECT COUNT(*) FROM notices WHERE FIND_IN_SET('students',audience)");
    $stats['notices'] = (int)$st->fetchColumn();
} catch (Exception $e) {}

try {
    $st = $db->query(
        "SELECT COUNT(*) FROM assessments a
         JOIN teachers t ON t.id = a.teacher_id
         WHERE COALESCE(t.wing,'main')='main'"
    );
    $stats['assessments'] = (int)$st->fetchColumn();
} catch (Exception $e) {}

// Recent syllabuses
$recentSyllabuses = [];
try {
    $st = $db->prepare(
        "SELECT sd.*, c.name AS class_name, u.name AS uploader_name
         FROM syllabus_documents sd
         LEFT JOIN classes c ON c.id = sd.class_id
         LEFT JOIN users u ON u.id = sd.uploaded_by
         ORDER BY sd.created_at DESC LIMIT 5"
    );
    $st->execute();
    $recentSyllabuses = $st->fetchAll();
} catch (Exception $e) {}

pageHead('Dashboard', 'examination_head');
$links = getExamHeadLinks();
?>
<div class="portal-wrap">
<?php sidebar('examination_head', 'dashboard', $links, $user); ?>
<div class="main-area">
<?php topbar('Examination Head Dashboard', $user); ?>
<div class="page-content">

<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-lg-3">
    <div class="stat-card" style="border-left:4px solid #2563eb">
      <div class="stat-icon" style="background:#eff6ff;color:#2563eb"><i class="fas fa-user-graduate"></i></div>
      <div class="stat-body">
        <div class="stat-num"><?= number_format($stats['students']) ?></div>
        <div class="stat-label">Main Campus Students</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg-3">
    <div class="stat-card" style="border-left:4px solid #059669">
      <div class="stat-icon" style="background:#f0fdf4;color:#059669"><i class="fas fa-chalkboard-teacher"></i></div>
      <div class="stat-body">
        <div class="stat-num"><?= number_format($stats['teachers']) ?></div>
        <div class="stat-label">Main Campus Teachers</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg-3">
    <div class="stat-card" style="border-left:4px solid #d97706">
      <div class="stat-icon" style="background:#fffbeb;color:#d97706"><i class="fas fa-calendar-day"></i></div>
      <div class="stat-body">
        <div class="stat-num"><?= number_format($stats['datesheets']) ?></div>
        <div class="stat-label">Date Sheets</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg-3">
    <div class="stat-card" style="border-left:4px solid #7c3aed">
      <div class="stat-icon" style="background:#f5f3ff;color:#7c3aed"><i class="fas fa-book"></i></div>
      <div class="stat-body">
        <div class="stat-num"><?= number_format($stats['syllabuses']) ?></div>
        <div class="stat-label">Syllabuses</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg-3">
    <div class="stat-card" style="border-left:4px solid #0891b2">
      <div class="stat-icon" style="background:#ecfeff;color:#0891b2"><i class="fas fa-table"></i></div>
      <div class="stat-body">
        <div class="stat-num"><?= number_format($stats['timetables']) ?></div>
        <div class="stat-label">Timetable Docs</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg-3">
    <div class="stat-card" style="border-left:4px solid #be185d">
      <div class="stat-icon" style="background:#fdf2f8;color:#be185d"><i class="fas fa-bell"></i></div>
      <div class="stat-body">
        <div class="stat-num"><?= number_format($stats['notices']) ?></div>
        <div class="stat-label">Published Notices</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-lg-3">
    <div class="stat-card" style="border-left:4px solid #b45309">
      <div class="stat-icon" style="background:#fffbeb;color:#b45309"><i class="fas fa-pen-alt"></i></div>
      <div class="stat-body">
        <div class="stat-num"><?= number_format($stats['assessments']) ?></div>
        <div class="stat-label">Assessments</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-book me-2"></i>Recent Syllabuses</div>
      <?php if (empty($recentSyllabuses)): ?>
      <div style="padding:24px;text-align:center;color:var(--t2);font-size:.84rem">
        <i class="fas fa-book fa-2x mb-2 d-block opacity-25"></i>No syllabuses uploaded yet.
      </div>
      <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($recentSyllabuses as $s): ?>
        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3" style="font-size:.84rem">
          <div>
            <div class="fw-semibold"><?= h($s['title']) ?></div>
            <div style="font-size:.74rem;color:var(--t3)"><?= h($s['class_name'] ?? 'All Classes') ?> · <?= h($s['academic_year']) ?></div>
          </div>
          <a href="/portal/api/serve-document.php?type=syllabus&id=<?= $s['id'] ?>" class="btn btn-outline-secondary btn-sm" target="_blank"><i class="fas fa-download"></i></a>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-md-6">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-rocket me-2"></i>Quick Actions</div>
      <div class="p-3 d-grid gap-2">
        <?php if (hasPermission('eh_datesheet')): ?>
        <a href="/portal/exam-head/exam-datesheet.php" class="btn btn-outline-warning btn-sm text-start">
          <i class="fas fa-calendar-day me-2"></i>Upload Date Sheet
        </a>
        <?php endif; ?>
        <?php if (hasPermission('eh_syllabus')): ?>
        <a href="/portal/exam-head/syllabus.php" class="btn btn-outline-primary btn-sm text-start">
          <i class="fas fa-book me-2"></i>Upload Syllabus
        </a>
        <?php endif; ?>
        <?php if (hasPermission('eh_timetable')): ?>
        <a href="/portal/exam-head/timetable.php" class="btn btn-outline-info btn-sm text-start">
          <i class="fas fa-table me-2"></i>Upload Timetable
        </a>
        <?php endif; ?>
        <?php if (hasPermission('eh_notices')): ?>
        <a href="/portal/exam-head/notices.php" class="btn btn-outline-danger btn-sm text-start">
          <i class="fas fa-bell me-2"></i>Post a Notice
        </a>
        <?php endif; ?>
        <?php if (hasPermission('eh_marks')): ?>
        <a href="/portal/exam-head/marks.php" class="btn btn-outline-success btn-sm text-start">
          <i class="fas fa-pen-alt me-2"></i>Assessments &amp; Marks
        </a>
        <?php endif; ?>
        <?php if (hasPermission('eh_results')): ?>
        <a href="/portal/exam-head/results.php" class="btn btn-outline-primary btn-sm text-start">
          <i class="fas fa-chart-bar me-2"></i>View Results &amp; Report Cards
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

</div></div></div>
<?php pageFooter(); ?>

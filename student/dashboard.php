<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

$user    = requireAuth('student');
$student = getStudentByUserId($user['id']);

if (!$student) {
    setFlash('danger', 'Student profile not found. Contact admin.');
    redirect('/logout.php');
}

$db = getDB();

// ── Stats ─────────────────────────────────────────────────────────
// Total subjects for this class
$stSubjects = $db->prepare('SELECT COUNT(*) FROM class_subjects WHERE class_id = ?');
$stSubjects->execute([$student['class_id']]);
$totalSubjects = (int)$stSubjects->fetchColumn();

// Attendance %
$attSummary = getStudentAttendanceSummary($student['id']);
$attTotal   = array_sum(array_column($attSummary, 'total'));
$attPresent = array_sum(array_column($attSummary, 'present'));
$attPct     = $attTotal > 0 ? round($attPresent / $attTotal * 100, 1) : 0;

// Pending notices
$notices = getNoticesForPortal('student');
$pendingNotices = count($notices);

// Assessments count for this class
$stAss = $db->prepare('SELECT COUNT(*) FROM assessments WHERE class_id = ?');
$stAss->execute([$student['class_id']]);
$assessmentsCount = (int)$stAss->fetchColumn();

// ── Recent attendance (last 5) ────────────────────────────────────
$stAtt = $db->prepare(
    'SELECT a.date, a.status, sb.name AS subject_name
     FROM attendance a
     JOIN subjects sb ON a.subject_id = sb.id
     WHERE a.student_id = ?
     ORDER BY a.date DESC, a.id DESC
     LIMIT 5'
);
$stAtt->execute([$student['id']]);
$recentAtt = $stAtt->fetchAll();

// ── Recent marks (last 5) ─────────────────────────────────────────
$stMarks = $db->prepare(
    'SELECT m.marks_obtained, a.title, a.type, a.max_marks, sb.name AS subject_name
     FROM marks m
     JOIN assessments a ON m.assessment_id = a.id
     JOIN subjects sb ON a.subject_id = sb.id
     WHERE m.student_id = ?
     ORDER BY m.id DESC
     LIMIT 5'
);
$stMarks->execute([$student['id']]);
$recentMarks = $stMarks->fetchAll();

// ── Upcoming assessments ──────────────────────────────────────────
$stUpcoming = $db->prepare(
    'SELECT a.title, a.type, a.date, a.max_marks, sb.name AS subject_name
     FROM assessments a
     JOIN subjects sb ON a.subject_id = sb.id
     WHERE a.class_id = ? AND a.date >= CURDATE()
     ORDER BY a.date ASC
     LIMIT 5'
);
$stUpcoming->execute([$student['class_id']]);
$upcomingAss = $stUpcoming->fetchAll();

// ── Top 3 notices ─────────────────────────────────────────────────
$topNotices = array_slice($notices, 0, 3);

// Wing / ILC detection
$studentWing     = 'main';
$ilcDisabilities = [];
try {
    $stWing = $db->prepare('SELECT wing FROM classes WHERE id = ?');
    $stWing->execute([$student['class_id']]);
    $studentWing = $stWing->fetchColumn() ?: 'main';
    if ($studentWing === 'ilc') {
        $stDis = $db->prepare(
            'SELECT dc.name AS category, dst.name AS subtype, sd.notes
             FROM student_disabilities sd
             JOIN disability_subtypes dst ON sd.subtype_id = dst.id
             JOIN disability_categories dc ON dst.category_id = dc.id
             WHERE sd.student_id = ?
             ORDER BY dc.name, dst.name'
        );
        $stDis->execute([$student['id']]);
        $ilcDisabilities = $stDis->fetchAll();
    }
} catch (Exception $e) {}

// Page render ───────────────────────────────────────────────────
pageHead('Dashboard', 'student');
$links = [
    ['href'=>'/student/dashboard.php','icon'=>'<i class="fas fa-home"></i>','label'=>'Dashboard','key'=>'dashboard'],
    ['href'=>'/student/results.php','icon'=>'<i class="fas fa-chart-bar"></i>','label'=>'My Results','key'=>'results'],
    ['href'=>'/student/attendance.php','icon'=>'<i class="fas fa-calendar-check"></i>','label'=>'Attendance','key'=>'attendance'],
    ['href'=>'/student/timetable.php','icon'=>'<i class="fas fa-table"></i>','label'=>'My Timetable','key'=>'timetable'],
    ['href'=>'/student/notices.php','icon'=>'<i class="fas fa-bell"></i>','label'=>'Notices','key'=>'notices'],
];
?>
<div class="portal-wrap">
<?php sidebar('student', 'dashboard', $links, $user); ?>
<div class="main-area">
<?php topbar('Dashboard', $user); ?>
<div class="page-content">

<?= flashHtml() ?>

<!-- Welcome Banner -->
<div class="portal-banner mb-4" style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8);">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h5 class="mb-1 fw-bold text-white">Welcome, <?= h($student['name']) ?>!</h5>
      <small style="color:rgba(255,255,255,.82)">
        Class: <?= h($student['class_name']) ?> &nbsp;&middot;&nbsp; Roll No: <?= h($student['roll_no']) ?>
        &nbsp;&middot;&nbsp; <?= date('l, d M Y') ?>
      </small>
    </div>
    <a href="/student/results.php" class="btn btn-light btn-sm fw-semibold">View Results</a>
  </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#eff6ff"><i class="fas fa-book" style="color:#1d4ed8"></i></div>
      <div class="stat-val" style="color:#1d4ed8"><?= $totalSubjects ?></div>
      <div class="stat-lbl">Total Subjects</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#f0fdf4"><i class="fas fa-calendar-check" style="color:#059669"></i></div>
      <div class="stat-val" style="color:<?= $attPct < 75 ? '#ef4444' : '#059669' ?>"><?= $attPct ?>%</div>
      <div class="stat-lbl">Attendance</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef2f2"><i class="fas fa-bell" style="color:#ef4444"></i></div>
      <div class="stat-val" style="color:#ef4444"><?= $pendingNotices ?></div>
      <div class="stat-lbl">Notices</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fdf4ff"><i class="fas fa-tasks" style="color:#7c3aed"></i></div>
      <div class="stat-val" style="color:#7c3aed"><?= $assessmentsCount ?></div>
      <div class="stat-lbl">Assessments</div>
    </div>
  </div>
</div>

<?php if ($studentWing === 'ilc'): ?>
<div class="sec-card mb-3" style="border-left:4px solid #0891b2;">
  <div class="sec-head">
    <h5><i class="fas fa-heartbeat me-2" style="color:#0891b2"></i>ILC Accommodation Profile</h5>
    <span class="badge" style="background:#ecfeff;color:#0e7490;border:1px solid #a5f3fc;font-size:.75rem">ILC Wing</span>
  </div>
  <?php if (empty($ilcDisabilities)): ?>
    <div class="p-3 text-muted" style="font-size:13px">No disability records on file. Contact the ILC office if this is incorrect.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Category</th><th>Subtype</th><th>Notes</th></tr>
        </thead>
        <tbody>
          <?php foreach ($ilcDisabilities as $d): ?>
          <tr>
            <td><span class="badge" style="background:#ecfeff;color:#0e7490"><?= h($d['category']) ?></span></td>
            <td class="fw-semibold"><?= h($d['subtype']) ?></td>
            <td class="text-muted" style="font-size:12px"><?= $d['notes'] ? h($d['notes']) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="row g-3">
  <!-- Left Column -->
  <div class="col-lg-8">

    <!-- Recent Marks -->
    <div class="sec-card">
      <div class="sec-head">
        <h5><i class="fas fa-chart-bar me-2" style="color:#1d4ed8"></i>Recent Marks</h5>
        <a href="/student/results.php" class="btn btn-sm btn-outline-primary">All Results</a>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Subject</th>
              <th>Assessment</th>
              <th>Type</th>
              <th>Obtained</th>
              <th>Max</th>
              <th>Grade</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentMarks)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">No marks recorded yet.</td></tr>
            <?php else: foreach ($recentMarks as $m):
              $pct = $m['max_marks'] > 0 ? ($m['marks_obtained'] / $m['max_marks'] * 100) : 0;
            ?>
            <tr>
              <td class="fw-semibold"><?= h($m['subject_name']) ?></td>
              <td><?= h($m['title']) ?></td>
              <td><span class="badge bg-secondary"><?= h($m['type']) ?></span></td>
              <td class="fw-bold"><?= h($m['marks_obtained']) ?></td>
              <td class="text-muted"><?= h($m['max_marks']) ?></td>
              <td><?= gradeHtml($pct) ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Upcoming Assessments -->
    <div class="sec-card">
      <div class="sec-head">
        <h5><i class="fas fa-tasks me-2" style="color:#7c3aed"></i>Upcoming Assessments</h5>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Subject</th>
              <th>Title</th>
              <th>Type</th>
              <th>Date</th>
              <th>Max Marks</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($upcomingAss)): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No upcoming assessments.</td></tr>
            <?php else: foreach ($upcomingAss as $a): ?>
            <tr>
              <td class="fw-semibold"><?= h($a['subject_name']) ?></td>
              <td><?= h($a['title']) ?></td>
              <td><span class="badge bg-primary"><?= h($a['type']) ?></span></td>
              <td><?= fDate($a['date']) ?></td>
              <td><?= h($a['max_marks']) ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /col-lg-8 -->

  <!-- Right Column -->
  <div class="col-lg-4">

    <!-- Recent Attendance -->
    <div class="sec-card mb-3">
      <div class="sec-head">
        <h5><i class="fas fa-calendar-check me-2 text-success"></i>Recent Attendance</h5>
        <a href="/student/attendance.php" class="btn btn-sm btn-outline-success">Details</a>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr><th>Date</th><th>Subject</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php if (empty($recentAtt)): ?>
            <tr><td colspan="3" class="text-center text-muted py-3">No records.</td></tr>
            <?php else: foreach ($recentAtt as $a):
              $statusColor = $a['status'] === 'P' ? '#059669' : ($a['status'] === 'L' ? '#d97706' : '#ef4444');
              $statusLabel = $a['status'] === 'P' ? 'Present' : ($a['status'] === 'L' ? 'Leave' : 'Absent');
            ?>
            <tr>
              <td><?= fDate($a['date']) ?></td>
              <td><?= h($a['subject_name']) ?></td>
              <td><span class="badge" style="background:<?= $statusColor ?>"><?= $statusLabel ?></span></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Notices -->
    <div class="sec-card">
      <div class="sec-head">
        <h5><i class="fas fa-bell me-2 text-danger"></i>Latest Notices</h5>
        <a href="/student/notices.php" class="btn btn-sm btn-outline-danger">All</a>
      </div>
      <div class="p-3">
        <?php if (empty($topNotices)): ?>
          <p class="text-muted text-center mb-0" style="font-size:13px">No notices.</p>
        <?php else: foreach ($topNotices as $n):
          $pinHtml = $n['pinned'] ? '<i class="fas fa-thumbtack text-danger me-1"></i>' : '';
          $priorityColors = ['Urgent' => '#ef4444', 'Important' => '#d97706', 'Normal' => '#059669'];
          $pColor = $priorityColors[$n['priority'] ?? 'Normal'] ?? '#6b7280';
        ?>
        <div style="display:flex;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)">
          <div style="width:34px;height:34px;border-radius:6px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas fa-bullhorn" style="color:#1d4ed8;font-size:13px"></i>
          </div>
          <div>
            <div class="fw-semibold" style="font-size:13px"><?= $pinHtml ?><?= h($n['title']) ?></div>
            <small class="text-muted"><?= fDate($n['created_at']) ?></small>
            <span class="badge ms-1" style="background:<?= $pColor ?>;font-size:9px"><?= ($n['priority'] ?? 'Normal') ?></span>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

  </div><!-- /col-lg-4 -->
</div><!-- /row -->

</div><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /portal-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

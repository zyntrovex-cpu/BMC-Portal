<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';
$user = requireAuth('admin');

$db = getDB();

// Stats
$totalStudents = (int)$db->query('SELECT COUNT(*) FROM users WHERE role="student" AND status="active"')->fetchColumn();
$totalTeachers = (int)$db->query('SELECT COUNT(*) FROM users WHERE role="teacher" AND status="active"')->fetchColumn();
$activeClasses = (int)$db->query('SELECT COUNT(*) FROM classes')->fetchColumn();
$month = (int)date('n');
$year  = (int)date('Y');
$stFees = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM fees WHERE paid=1 AND month=? AND year=?');
$stFees->execute([$month, $year]);
$feesThisMonth = (float)$stFees->fetchColumn();

// Pending profile change requests
$pendingRequests = (int)$db->query('SELECT COUNT(*) FROM profile_change_requests WHERE status="pending"')->fetchColumn();

// Recent activity (last 10)
$stActivity = $db->query(
    'SELECT al.*, u.name AS user_name, u.user_id AS login_id, u.role
     FROM activity_log al
     LEFT JOIN users u ON al.user_id = u.id
     ORDER BY al.created_at DESC
     LIMIT 10'
);
$activities = $stActivity->fetchAll();

pageHead('Admin Dashboard', 'admin');
$links = [
    ['href'=>'/admin/dashboard.php','icon'=>'<i class="fas fa-home"></i>','label'=>'Dashboard','key'=>'dashboard'],
    ['href'=>'/admin/users.php','icon'=>'<i class="fas fa-users"></i>','label'=>'User Management','key'=>'users'],
    ['href'=>'/admin/classes.php','icon'=>'<i class="fas fa-chalkboard"></i>','label'=>'Classes & Subjects','key'=>'classes'],
    ['href'=>'/admin/teachers.php','icon'=>'<i class="fas fa-chalkboard-teacher"></i>','label'=>'Teacher Accounts','key'=>'teachers'],
    ['href'=>'/admin/notices.php','icon'=>'<i class="fas fa-bell"></i>','label'=>'Notice Board','key'=>'notices'],
    ['href'=>'/admin/timetable.php','icon'=>'<i class="fas fa-table"></i>','label'=>'Timetable','key'=>'timetable'],
    ['href'=>'/admin/results.php','icon'=>'<i class="fas fa-chart-bar"></i>','label'=>'Results','key'=>'results'],
    ['href'=>'/admin/activity.php','icon'=>'<i class="fas fa-history"></i>','label'=>'Activity Log','key'=>'activity'],
];
?>
<?php
echo '<div class="portal-wrap">';
sidebar('admin', 'dashboard', $links, $user);
echo '<div class="main-area">';
topbar('Admin Dashboard', $user);
echo '<div class="page-content" style="padding:20px 22px 32px">';
echo flashHtml();
?>

<!-- Stat cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#ede9fe;color:#7c3aed"><i class="fas fa-user-graduate"></i></div>
      <div class="stat-val"><?= h($totalStudents) ?></div>
      <div class="stat-lbl">Active Students</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#d1fae5;color:#059669"><i class="fas fa-chalkboard-teacher"></i></div>
      <div class="stat-val"><?= h($totalTeachers) ?></div>
      <div class="stat-lbl">Active Teachers</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#dbeafe;color:#1d4ed8"><i class="fas fa-school"></i></div>
      <div class="stat-val"><?= h($activeClasses) ?></div>
      <div class="stat-lbl">Active Classes</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-coins"></i></div>
      <div class="stat-val">Rs <?= number_format($feesThisMonth) ?></div>
      <div class="stat-lbl">Fees Collected (<?= date('M Y') ?>)</div>
    </div>
  </div>
</div>

<?php if ($pendingRequests > 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4" role="alert">
  <i class="fas fa-clock"></i>
  <div>
    <strong><?= h($pendingRequests) ?> pending profile change request<?= $pendingRequests > 1 ? 's' : '' ?></strong> awaiting your review.
    <a href="/admin/users.php#profile-requests" class="alert-link ms-1">Review now &rarr;</a>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <!-- Quick Actions -->
  <div class="col-lg-4">
    <div class="sec-card">
      <div class="sec-head"><h5><i class="fas fa-bolt me-1" style="color:#7c3aed"></i> Quick Actions</h5></div>
      <div class="sec-body">
        <div class="row g-2">
          <div class="col-6">
            <a href="/admin/users.php" class="qa-btn">
              <i class="fas fa-users"></i><span>Manage Users</span>
            </a>
          </div>
          <div class="col-6">
            <a href="/admin/notices.php" class="qa-btn">
              <i class="fas fa-bell"></i><span>Manage Notices</span>
            </a>
          </div>
          <div class="col-6">
            <a href="/admin/classes.php" class="qa-btn">
              <i class="fas fa-chalkboard"></i><span>Manage Classes</span>
            </a>
          </div>
          <div class="col-6">
            <a href="/admin/results.php" class="qa-btn">
              <i class="fas fa-chart-bar"></i><span>View Results</span>
            </a>
          </div>
          <div class="col-6">
            <a href="/admin/timetable.php" class="qa-btn">
              <i class="fas fa-table"></i><span>Timetable</span>
            </a>
          </div>
          <div class="col-6">
            <a href="/admin/settings.php" class="qa-btn">
              <i class="fas fa-cog"></i><span>Settings</span>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Activity -->
  <div class="col-lg-8">
    <div class="sec-card">
      <div class="sec-head">
        <h5><i class="fas fa-history me-1" style="color:#7c3aed"></i> Recent Activity</h5>
        <a href="/admin/activity.php" class="btn btn-sm btn-outline-secondary">View All</a>
      </div>
      <?php if (empty($activities)): ?>
        <div class="sec-body text-muted">No activity recorded yet.</div>
      <?php else: ?>
        <?php foreach ($activities as $act): ?>
        <div class="act-item">
          <div class="act-dot"></div>
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:600"><?= h($act['user_name'] ?? 'System') ?>
              <span class="text-muted fw-normal ms-1" style="font-size:11px">(<?= h($act['login_id'] ?? '') ?>)</span>
              <span class="badge bg-secondary ms-1" style="font-size:9.5px"><?= h($act['role'] ?? '') ?></span>
            </div>
            <div style="font-size:12px;color:var(--t2)"><?= h($act['action']) ?><?= $act['details'] ? ' — '.h($act['details']) : '' ?></div>
          </div>
          <div style="font-size:11px;color:var(--t3);white-space:nowrap;flex-shrink:0">
            <?= h(date('d M, H:i', strtotime($act['created_at']))) ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
echo '</div></div></div>';
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user    = requireAuth('student');
$notices = getNoticesForPortal('student');
$cat     = $_GET['cat'] ?? '';
$q       = trim($_GET['q'] ?? '');

if ($cat) {
    $notices = array_filter($notices, fn($n) => $n['category'] === $cat);
}
if ($q) {
    $notices = array_filter($notices, fn($n) =>
        stripos($n['title'], $q) !== false || stripos($n['body'], $q) !== false
    );
}

$categories = ['General','Academic','Exam','Holiday','Finance','Emergency'];

pageHead('Notices', 'student');
$links = [
    ['href'=>'/student/dashboard.php','icon'=>'<i class="fas fa-home"></i>','label'=>'Dashboard','key'=>'dashboard'],
    ['href'=>'/student/results.php','icon'=>'<i class="fas fa-chart-bar"></i>','label'=>'Results','key'=>'results'],
    ['href'=>'/student/attendance.php','icon'=>'<i class="fas fa-calendar-check"></i>','label'=>'Attendance','key'=>'attendance'],
    ['href'=>'/student/timetable.php','icon'=>'<i class="fas fa-table"></i>','label'=>'Timetable','key'=>'timetable'],
    ['href'=>'/student/notices.php','icon'=>'<i class="fas fa-bell"></i>','label'=>'Notices','key'=>'notices'],
    ['href'=>'/student/profile.php','icon'=>'<i class="fas fa-user"></i>','label'=>'Profile','key'=>'profile'],
];
?>
<div class="portal-wrap">
<?php sidebar('student', 'notices', $links); ?>
<div class="main-area">
<?php topbar('Notices', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Search + Filter -->
<form method="GET" class="d-flex gap-2 mb-3 flex-wrap">
  <input type="text" name="q" class="form-control form-control-sm" style="max-width:260px" placeholder="Search notices..." value="<?= h($q) ?>">
  <select name="cat" class="form-select form-select-sm" style="max-width:180px" onchange="this.form.submit()">
    <option value="">All Categories</option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= h($c) ?>" <?= $cat===$c?'selected':'' ?>><?= h($c) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
  <?php if ($cat || $q): ?>
    <a href="/student/notices.php" class="btn btn-sm btn-outline-danger">Clear</a>
  <?php endif; ?>
</form>

<?php if (empty($notices)): ?>
  <div class="sec-card p-4 text-center text-muted">No notices found.</div>
<?php else: ?>
  <?php foreach ($notices as $n):
    $priorityClass = match($n['priority']) { 'Urgent'=>'danger', 'Important'=>'warning', default=>'secondary' };
    $catClass      = match($n['category']) { 'Exam'=>'primary','Academic'=>'info','Holiday'=>'success','Emergency'=>'danger','Finance'=>'warning', default=>'secondary' };
  ?>
  <div class="sec-card mb-3 <?= $n['pinned'] ? 'border-warning' : '' ?>">
    <div class="sec-card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <?php if ($n['pinned']): ?><i class="fas fa-thumbtack text-warning me-1"></i><?php endif; ?>
        <strong><?= h($n['title']) ?></strong>
      </div>
      <div class="d-flex gap-1 flex-wrap">
        <span class="badge bg-<?= $catClass ?>"><?= h($n['category']) ?></span>
        <span class="badge bg-<?= $priorityClass ?>"><?= h($n['priority']) ?></span>
      </div>
    </div>
    <div style="padding:14px 16px">
      <p style="font-size:.9rem;margin-bottom:8px;white-space:pre-line"><?= h($n['body']) ?></p>
      <div style="font-size:.78rem;color:#9ca3af">
        <i class="fas fa-user me-1"></i><?= h($n['author_name'] ?? 'Admin') ?>
        &nbsp;·&nbsp;
        <i class="fas fa-clock me-1"></i><?= fDate($n['created_at']) ?>
        <?php if ($n['expiry_date']): ?>
          &nbsp;·&nbsp;<i class="fas fa-calendar-times me-1"></i>Expires: <?= fDate($n['expiry_date']) ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

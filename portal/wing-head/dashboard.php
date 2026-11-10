<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('wing_head');
$db   = getDB();

// Stats
$teacherCount = $studentCount = $reportCount = $assessCount = 0;
try {
    $teacherCount = (int)$db->query(
        'SELECT COUNT(*) FROM users WHERE role="montessori_teacher" AND status="active"'
    )->fetchColumn();
} catch (Exception $e) {}
try {
    $studentCount = (int)$db->query(
        'SELECT COUNT(*) FROM students s JOIN classes c ON c.id=s.class_id WHERE c.is_montessori=1'
    )->fetchColumn();
} catch (Exception $e) {}
try {
    $reportCount = (int)$db->query(
        'SELECT COUNT(*) FROM progress_reports r
         JOIN students s ON s.id=r.student_id
         JOIN classes c ON c.id=s.class_id
         WHERE c.is_montessori=1'
    )->fetchColumn();
} catch (Exception $e) {}
try {
    $assessCount = (int)$db->query(
        'SELECT COUNT(*) FROM assessments a
         JOIN classes c ON c.id=a.class_id WHERE c.is_montessori=1'
    )->fetchColumn();
} catch (Exception $e) {}

// Montessori teachers list (recent)
$monteTeachers = [];
try {
    $monteTeachers = $db->query(
        'SELECT u.id, u.name, u.user_id AS uid, u.status,
                t.emp_id, t.designation, t.subject_name
         FROM users u
         LEFT JOIN teachers t ON t.user_id=u.id
         WHERE u.role="montessori_teacher" AND u.status="active"
         ORDER BY u.name LIMIT 6'
    )->fetchAll();
} catch (Exception $e) {}

// Notices
$notices = array_slice(getNoticesForPortal('teacher'), 0, 3);

// Recent activity
$recentActivity = [];
try {
    $stLog = $db->prepare(
        'SELECT action, details, created_at FROM activity_log
         WHERE user_id=? ORDER BY created_at DESC LIMIT 8'
    );
    $stLog->execute([$user['id']]);
    $recentActivity = $stLog->fetchAll();
} catch (Exception $e) {}

pageHead('Dashboard', 'wing_head');
$links = getWingHeadLinks();
?>
<div class="portal-wrap">
<?php sidebar('wing_head', 'dashboard', $links, $user); ?>
<div class="main-area">
<?php topbar('Dashboard', $user); ?>
<div class="page-content">

<?= flashHtml() ?>

<!-- Welcome Banner -->
<div class="portal-banner mb-4" style="background:linear-gradient(135deg,#059669,#047857);">
  <div class="d-flex align-items-center gap-3 flex-wrap">
    <?php
      $_av = _avatarHtml($user['id'], _initials($user['name']), 60);
      if (str_starts_with($_av, '<img')):
    ?><div style="width:60px;height:60px;border-radius:50%;overflow:hidden;flex-shrink:0;border:2px solid rgba(255,255,255,.5);box-shadow:0 2px 8px rgba(0,0,0,.25)"><?= $_av ?></div><?php
      else: ?><div style="width:60px;height:60px;border-radius:50%;background:rgba(255,255,255,.22);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;color:#fff;flex-shrink:0;border:2px solid rgba(255,255,255,.4)"><?= $_av ?></div><?php
      endif; ?>
    <div>
      <h4 class="mb-1 text-white fw-bold">Welcome, <?= h($user['name']) ?>!</h4>
      <div class="text-white opacity-75" style="font-size:13px;">
        Coordinator &mdash; Montessori Wing &nbsp;|&nbsp; Session <?= SESSION_YEAR ?>
      </div>
    </div>
  </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#dcfce7;color:#059669"><i class="fas fa-chalkboard-teacher"></i></div>
      <div class="stat-val"><?= $teacherCount ?></div>
      <div class="stat-lbl">Montessori Teachers</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#dbeafe;color:#1d4ed8"><i class="fas fa-child"></i></div>
      <div class="stat-val"><?= $studentCount ?></div>
      <div class="stat-lbl">Montessori Students</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#f3e8ff;color:#7c3aed"><i class="fas fa-file-alt"></i></div>
      <div class="stat-val"><?= $reportCount ?></div>
      <div class="stat-lbl">Progress Reports</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef9c3;color:#d97706"><i class="fas fa-clipboard-check"></i></div>
      <div class="stat-val"><?= $assessCount ?></div>
      <div class="stat-lbl">Assessments</div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="sec-card mb-4">
  <div class="sec-head">
    <h5><i class="fas fa-bolt me-2" style="color:#059669"></i>Quick Actions</h5>
  </div>
  <div class="sec-body d-flex flex-wrap gap-2">
    <a href="<?= url('/portal/progress-report/form.php') ?>" class="btn btn-success btn-sm">
      <i class="fas fa-file-alt me-1"></i>Progress Report
    </a>
    <a href="<?= url('/portal/montessori/assessments.php') ?>" class="btn btn-primary btn-sm">
      <i class="fas fa-clipboard-check me-1"></i>Formative Assessment
    </a>
    <a href="<?= url('/portal/montessori/anecdotal-records.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-sticky-note me-1"></i>Anecdotal Records
    </a>
    <a href="<?= url('/portal/wing-head/teachers.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-chalkboard-teacher me-1"></i>Montessori Teachers
    </a>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Montessori Teachers -->
  <div class="col-lg-6">
    <div class="sec-card h-100">
      <div class="sec-head">
        <h5><i class="fas fa-chalkboard-teacher me-2" style="color:#059669"></i>Montessori Teachers</h5>
        <a href="<?= url('/portal/wing-head/teachers.php') ?>" class="btn btn-xs btn-outline-secondary">View All</a>
      </div>
      <div class="sec-body p-0">
        <?php if (empty($monteTeachers)): ?>
          <div class="p-3 text-muted" style="font-size:13px">No montessori teachers found.</div>
        <?php else: ?>
          <div class="table-responsive"><table class="data-table">
            <thead><tr><th>Name</th><th>ID</th><th>Designation</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($monteTeachers as $t): ?>
              <tr>
                <td class="fw-semibold"><?= h($t['name']) ?></td>
                <td class="text-muted" style="font-size:12px"><?= h($t['uid']) ?></td>
                <td style="font-size:12px;color:var(--t2)"><?= h($t['designation'] ?: '—') ?></td>
                <td>
                  <form method="POST" action="<?= url('/portal/wing-head/view-as.php') ?>" class="d-inline">
                    <input type="hidden" name="target_id" value="<?= $t['id'] ?>">
                    <button class="btn btn-xs btn-outline-primary" style="font-size:.72rem;padding:2px 7px">
                      <i class="fas fa-eye me-1"></i>View Portal
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Activity -->
  <div class="col-lg-6">
    <div class="sec-card h-100">
      <div class="sec-head">
        <h5><i class="fas fa-history me-2" style="color:#059669"></i>Recent Activity</h5>
      </div>
      <div class="sec-body p-0">
        <?php if (empty($recentActivity)): ?>
          <div class="p-3 text-muted" style="font-size:13px">No recent activity.</div>
        <?php else: ?>
          <ul class="list-unstyled mb-0">
            <?php foreach ($recentActivity as $log): ?>
            <li class="d-flex align-items-start gap-2 px-3 py-2" style="border-bottom:1px solid #edf1f7">
              <div class="mt-1" style="width:8px;height:8px;border-radius:50%;background:#059669;flex-shrink:0"></div>
              <div style="flex:1;min-width:0">
                <div style="font-size:12.5px;font-weight:600"><?= h(ucfirst($log['action'])) ?></div>
                <?php if ($log['details']): ?>
                  <div class="text-muted" style="font-size:11.5px"><?= h($log['details']) ?></div>
                <?php endif; ?>
                <div style="font-size:11px;color:var(--t3)"><?= fDate($log['created_at']) ?></div>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Notices -->
<div class="sec-card">
  <div class="sec-head">
    <h5><i class="fas fa-bell me-2" style="color:#059669"></i>Notices</h5>
    <a href="<?= url('/portal/wing-head/notices.php') ?>" class="btn btn-xs btn-outline-secondary">All Notices</a>
  </div>
  <div class="sec-body p-0">
    <?php if (empty($notices)): ?>
      <div class="p-3 text-muted" style="font-size:13px">No active notices.</div>
    <?php else: ?>
      <?php foreach ($notices as $n): ?>
      <div class="px-3 py-2" style="border-bottom:1px solid #edf1f7">
        <div class="d-flex align-items-center gap-2 mb-1">
          <?php if ($n['pinned']): ?>
            <i class="fas fa-thumbtack" style="color:#d97706;font-size:11px"></i>
          <?php endif; ?>
          <span class="fw-semibold" style="font-size:13px"><?= h($n['title']) ?></span>
          <?php if ($n['priority'] === 'Important' || $n['priority'] === 'Urgent'): ?>
            <span class="badge bg-danger ms-auto" style="font-size:10px"><?= h($n['priority']) ?></span>
          <?php endif; ?>
        </div>
        <div class="text-muted" style="font-size:11.5px">
          <?= h($n['category'] ?? '') ?> &mdash; <?= fDate($n['created_at']) ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

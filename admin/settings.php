<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_settings';

    if ($action === 'save_settings') {
        $keys = ['school_name','session_year','fee_amount','admin_email','phone'];
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                $val = trim($_POST[$key]);
                $db->prepare('INSERT INTO settings (key_name, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)')
                   ->execute([$key, $val]);
            }
        }
        logActivity($user['id'], 'settings_update', 'Updated portal settings');
        setFlash('success','Settings saved.');
    }

    if ($action === 'change_password') {
        $current = trim($_POST['current_password'] ?? '');
        $new     = trim($_POST['new_password']     ?? '');
        $confirm = trim($_POST['confirm_password'] ?? '');
        if ($new !== $confirm) {
            setFlash('danger','Passwords do not match.');
        } elseif (strlen($new) < 6) {
            setFlash('danger','Password must be at least 6 characters.');
        } else {
            $uSt = $db->prepare('SELECT password FROM users WHERE id = ?');
            $uSt->execute([$user['id']]);
            $u = $uSt->fetch();
            if ($u && password_verify($current, $u['password'])) {
                $hash = password_hash($new, PASSWORD_BCRYPT);
                $db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $user['id']]);
                setFlash('success','Password changed.');
            } else {
                setFlash('danger','Current password incorrect.');
            }
        }
    }
    redirect('/admin/settings.php');
}

// Load settings
$settingsSt = $db->query('SELECT key_name, value FROM settings');
$settings   = [];
foreach ($settingsSt->fetchAll() as $r) {
    $settings[$r['key_name']] = $r['value'];
}

// Stats
$stats = [
    'total_users'   => $db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'total_students'=> $db->query('SELECT COUNT(*) FROM students')->fetchColumn(),
    'total_teachers'=> $db->query('SELECT COUNT(*) FROM teachers')->fetchColumn(),
    'total_classes' => $db->query('SELECT COUNT(*) FROM classes')->fetchColumn(),
    'total_notices' => $db->query('SELECT COUNT(*) FROM notices')->fetchColumn(),
    'db_size'       => 'N/A',
];

pageHead('Settings', 'admin');
$links = [
    ['href'=>'/admin/dashboard.php','icon'=>'<i class="fas fa-home"></i>','label'=>'Dashboard','key'=>'dashboard'],
    ['href'=>'/admin/users.php','icon'=>'<i class="fas fa-users"></i>','label'=>'Users','key'=>'users'],
    ['href'=>'/admin/classes.php','icon'=>'<i class="fas fa-chalkboard"></i>','label'=>'Classes','key'=>'classes'],
    ['href'=>'/admin/teachers.php','icon'=>'<i class="fas fa-chalkboard-teacher"></i>','label'=>'Teachers','key'=>'teachers'],
    ['href'=>'/admin/notices.php','icon'=>'<i class="fas fa-bell"></i>','label'=>'Notices','key'=>'notices'],
    ['href'=>'/admin/timetable.php','icon'=>'<i class="fas fa-table"></i>','label'=>'Timetable','key'=>'timetable'],
    ['href'=>'/admin/results.php','icon'=>'<i class="fas fa-chart-bar"></i>','label'=>'Results','key'=>'results'],
    ['href'=>'/admin/activity.php','icon'=>'<i class="fas fa-history"></i>','label'=>'Activity Log','key'=>'activity'],
    ['href'=>'/admin/settings.php','icon'=>'<i class="fas fa-cog"></i>','label'=>'Settings','key'=>'settings'],
];
?>
<div class="portal-wrap">
<?php sidebar('admin', 'settings', $links); ?>
<div class="main-area">
<?php topbar('Settings', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Quick stats -->
<div class="row g-3 mb-3">
  <?php foreach ([['Users',$stats['total_users'],'fas fa-users'],['Students',$stats['total_students'],'fas fa-user-graduate'],['Teachers',$stats['total_teachers'],'fas fa-chalkboard-teacher'],['Classes',$stats['total_classes'],'fas fa-chalkboard']] as [$label,$val,$icon]): ?>
  <div class="col-6 col-md-3">
    <div class="stat-card"><div class="stat-icon"><i class="<?= $icon ?>"></i></div><div class="stat-val"><?= $val ?></div><div class="stat-label"><?= $label ?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-md-7">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-cog me-2"></i>Portal Settings</div>
      <div style="padding:16px">
        <form method="POST">
          <input type="hidden" name="action" value="save_settings">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">School Name</label>
            <input type="text" name="school_name" class="form-control" value="<?= h($settings['school_name'] ?? SCHOOL_NAME) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Session Year</label>
            <input type="text" name="session_year" class="form-control" value="<?= h($settings['session_year'] ?? SESSION_YEAR) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Default Monthly Fee (PKR)</label>
            <input type="number" name="fee_amount" class="form-control" value="<?= h($settings['fee_amount'] ?? '5000') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Admin Email</label>
            <input type="email" name="admin_email" class="form-control" value="<?= h($settings['admin_email'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Contact Phone</label>
            <input type="tel" name="phone" class="form-control" value="<?= h($settings['phone'] ?? '') ?>">
          </div>
          <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Save Settings</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-5">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-lock me-2"></i>Change Password</div>
      <div style="padding:16px">
        <form method="POST">
          <input type="hidden" name="action" value="change_password">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">New Password</label>
            <input type="password" name="new_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-danger">Change Password</button>
        </form>
      </div>
    </div>

    <div class="sec-card mt-3">
      <div class="sec-card-header"><i class="fas fa-info-circle me-2"></i>System Info</div>
      <div style="padding:14px">
        <table class="table table-sm mb-0" style="font-size:.84rem">
          <tr><th style="color:#6b7280">PHP Version</th><td><?= PHP_VERSION ?></td></tr>
          <tr><th style="color:#6b7280">Total Users</th><td><?= $stats['total_users'] ?></td></tr>
          <tr><th style="color:#6b7280">Total Notices</th><td><?= $stats['total_notices'] ?></td></tr>
          <tr><th style="color:#6b7280">Session</th><td><?= SESSION_YEAR ?></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

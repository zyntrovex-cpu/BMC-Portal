<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_settings';

    if ($action === 'save_settings') {
        $keys = ['school_name','session_year','fee_amount','admin_email','phone','app_url'];
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                $db->prepare('INSERT INTO settings (key_name, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)')
                   ->execute([$key, trim($_POST[$key])]);
            }
        }
        logActivity($user['id'], 'settings_update', 'Updated portal settings');
        setFlash('success','Settings saved.');
    }

    if ($action === 'save_smtp') {
        // smtp_enabled handled separately below — do NOT include it in the loop
        // (checkbox is absent from POST when unchecked, so the loop would write '' instead of '0')
        $smtpKeys = ['smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from','smtp_from_name','app_url'];
        foreach ($smtpKeys as $key) {
            $val = trim($_POST[$key] ?? '');
            $db->prepare('INSERT INTO settings (key_name, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)')
               ->execute([$key, $val]);
        }
        // Handle enabled checkbox (absent when unchecked)
        $enabled = isset($_POST['smtp_enabled']) ? '1' : '0';
        $db->prepare('INSERT INTO settings (key_name, value) VALUES ("smtp_enabled",?) ON DUPLICATE KEY UPDATE value=VALUES(value)')
           ->execute([$enabled]);
        logActivity($user['id'], 'smtp_update', 'Updated SMTP settings');
        setFlash('success','SMTP settings saved.');
    }

    if ($action === 'test_smtp') {
        $testEmail = trim($_POST['test_email'] ?? '');
        if ($testEmail) {
            require_once __DIR__ . '/../includes/mailer.php';
            $body = mailTemplate('SMTP Test', '<p>This is a test email from BMC Portal. SMTP is configured correctly! ✅</p>');
            $ok = sendMail($testEmail, 'Test', 'BMC Portal — SMTP Test', $body);
            setFlash($ok ? 'success' : 'danger', $ok ? 'Test email sent successfully!' : 'Failed to send. Check SMTP settings.');
        }
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

// Load all settings
$settingsSt = $db->query('SELECT key_name, value FROM settings');
$settings   = [];
foreach ($settingsSt->fetchAll() as $r) {
    $settings[$r['key_name']] = $r['value'];
}

$stats = [
    'total_users'   => $db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'total_students'=> $db->query('SELECT COUNT(*) FROM students')->fetchColumn(),
    'total_teachers'=> $db->query('SELECT COUNT(*) FROM teachers')->fetchColumn(),
    'total_classes' => $db->query('SELECT COUNT(*) FROM classes')->fetchColumn(),
    'total_notices' => $db->query('SELECT COUNT(*) FROM notices')->fetchColumn(),
];

pageHead('Settings', 'admin');
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
<div class="portal-wrap">
<?php sidebar('admin', 'settings', $links, $user); ?>
<div class="main-area">
<?php topbar('Settings', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Stats -->
<div class="row g-3 mb-3">
  <?php foreach ([['Users',$stats['total_users'],'fas fa-users'],['Students',$stats['total_students'],'fas fa-user-graduate'],['Teachers',$stats['total_teachers'],'fas fa-chalkboard-teacher'],['Classes',$stats['total_classes'],'fas fa-chalkboard']] as [$label,$val,$icon]): ?>
  <div class="col-6 col-md-3">
    <div class="stat-card"><div class="stat-icon"><i class="<?= $icon ?>"></i></div><div class="stat-val"><?= $val ?></div><div class="stat-label"><?= $label ?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <!-- General settings -->
  <div class="col-md-6">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-cog me-2"></i>Portal Settings</div>
      <div style="padding:16px">
        <form method="POST">
          <input type="hidden" name="action" value="save_settings">
          <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.85rem">School Name</label>
            <input type="text" name="school_name" class="form-control" value="<?= h($settings['school_name'] ?? SCHOOL_NAME) ?>"></div>
          <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.85rem">Session Year</label>
            <input type="text" name="session_year" class="form-control" value="<?= h($settings['session_year'] ?? SESSION_YEAR) ?>"></div>
          <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.85rem">Default Monthly Fee (PKR)</label>
            <input type="number" name="fee_amount" class="form-control" value="<?= h($settings['fee_amount'] ?? '5000') ?>"></div>
          <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.85rem">Admin Email</label>
            <input type="email" name="admin_email" class="form-control" value="<?= h($settings['admin_email'] ?? '') ?>"></div>
          <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.85rem">Contact Phone</label>
            <input type="tel" name="phone" class="form-control" value="<?= h($settings['phone'] ?? '') ?>"></div>
          <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.85rem">App URL <small class="text-muted">(for emails)</small></label>
            <input type="url" name="app_url" class="form-control" value="<?= h($settings['app_url'] ?? 'http://localhost:8080') ?>" placeholder="https://portal.bmcollege.edu.pk"></div>
          <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Save Settings</button>
        </form>
      </div>
    </div>

    <!-- Change password -->
    <div class="sec-card mt-3">
      <div class="sec-card-header"><i class="fas fa-lock me-2"></i>Change Password</div>
      <div style="padding:16px">
        <form method="POST">
          <input type="hidden" name="action" value="change_password">
          <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.85rem">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
          <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.85rem">New Password</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
          <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.85rem">Confirm Password</label><input type="password" name="confirm_password" class="form-control" required></div>
          <button type="submit" class="btn btn-danger">Change Password</button>
        </form>
      </div>
    </div>
  </div>

  <!-- SMTP settings -->
  <div class="col-md-6">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-envelope me-2"></i>SMTP Email Settings</div>
      <div style="padding:16px">
        <form method="POST" id="smtpForm">
          <input type="hidden" name="action" value="save_smtp">
          <div class="mb-3 d-flex align-items-center gap-3">
            <div class="form-check form-switch mb-0">
              <input type="checkbox" name="smtp_enabled" id="smtpEnabled" class="form-check-input" value="1"
                     <?= ($settings['smtp_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold" for="smtpEnabled" style="font-size:.85rem">Enable SMTP</label>
            </div>
            <span class="badge <?= ($settings['smtp_enabled']??'0')==='1' ? 'bg-success' : 'bg-secondary' ?>" style="font-size:.72rem">
              <?= ($settings['smtp_enabled']??'0')==='1' ? 'Enabled' : 'Disabled' ?>
            </span>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-8"><label class="form-label fw-semibold" style="font-size:.82rem">SMTP Host</label>
              <input type="text" name="smtp_host" class="form-control form-control-sm" value="<?= h($settings['smtp_host'] ?? 'smtp.gmail.com') ?>" placeholder="smtp.gmail.com"></div>
            <div class="col-4"><label class="form-label fw-semibold" style="font-size:.82rem">Port</label>
              <input type="number" name="smtp_port" class="form-control form-control-sm" value="<?= h($settings['smtp_port'] ?? '587') ?>"></div>
          </div>
          <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.82rem">SMTP Username</label>
            <input type="email" name="smtp_user" class="form-control form-control-sm" value="<?= h($settings['smtp_user'] ?? '') ?>" placeholder="your@gmail.com"></div>
          <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.82rem">SMTP Password / App Password</label>
            <input type="password" name="smtp_pass" class="form-control form-control-sm" value="<?= h($settings['smtp_pass'] ?? '') ?>" placeholder="App password for Gmail"></div>
          <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.82rem">From Email</label>
            <input type="email" name="smtp_from" class="form-control form-control-sm" value="<?= h($settings['smtp_from'] ?? '') ?>" placeholder="noreply@bmcollege.edu.pk"></div>
          <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.82rem">From Name</label>
            <input type="text" name="smtp_from_name" class="form-control form-control-sm" value="<?= h($settings['smtp_from_name'] ?? 'BMC Portal') ?>"></div>
          <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save me-1"></i>Save SMTP</button>
        </form>

        <!-- Test SMTP -->
        <hr class="my-3">
        <form method="POST">
          <input type="hidden" name="action" value="test_smtp">
          <label class="form-label fw-semibold" style="font-size:.82rem">Test SMTP — Send test email</label>
          <div class="input-group input-group-sm">
            <input type="email" name="test_email" class="form-control" placeholder="test@example.com" required>
            <button type="submit" class="btn btn-outline-primary">Send Test</button>
          </div>
        </form>
      </div>
    </div>

    <!-- System info -->
    <div class="sec-card mt-3">
      <div class="sec-card-header"><i class="fas fa-info-circle me-2"></i>System Info</div>
      <div style="padding:14px">
        <table class="table table-sm mb-0" style="font-size:.84rem">
          <tr><th style="color:#6b7280">PHP Version</th><td><?= PHP_VERSION ?></td></tr>
          <tr><th style="color:#6b7280">Total Users</th><td><?= $stats['total_users'] ?></td></tr>
          <tr><th style="color:#6b7280">Total Notices</th><td><?= $stats['total_notices'] ?></td></tr>
          <tr><th style="color:#6b7280">Session</th><td><?= h($settings['session_year'] ?? SESSION_YEAR) ?></td></tr>
          <tr><th style="color:#6b7280">App URL</th><td style="font-size:.78rem"><?= h($settings['app_url'] ?? 'Not set') ?></td></tr>
          <tr><th style="color:#6b7280">SMTP Status</th><td><span class="badge <?= ($settings['smtp_enabled']??'0')==='1' ? 'bg-success' : 'bg-secondary' ?>"><?= ($settings['smtp_enabled']??'0')==='1' ? 'Enabled' : 'Disabled' ?></span></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

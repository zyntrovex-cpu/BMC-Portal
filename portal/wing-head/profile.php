<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('wing_head');
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $current = trim($_POST['current_password'] ?? '');
    $new     = trim($_POST['new_password']     ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');
    if ($new !== $confirm) {
        setFlash('danger', 'Passwords do not match.');
    } elseif (strlen($new) < 6) {
        setFlash('danger', 'Password must be at least 6 characters.');
    } else {
        $uSt = $db->prepare('SELECT password FROM users WHERE id = ?');
        $uSt->execute([$user['id']]);
        $u = $uSt->fetch();
        if ($u && password_verify($current, $u['password'])) {
            $db->prepare('UPDATE users SET password = ? WHERE id = ?')
               ->execute([password_hash($new, PASSWORD_BCRYPT), $user['id']]);
            logActivity($user['id'], 'password_change', 'Changed password');
            setFlash('success', 'Password changed successfully.');
        } else {
            setFlash('danger', 'Current password is incorrect.');
        }
    }
    redirect('/portal/wing-head/profile.php');
}

pageHead('My Profile', 'wing_head');
$links = getWingHeadLinks();
?>
<div class="portal-wrap">
<?php sidebar('wing_head', 'profile', $links, $user); ?>
<div class="main-area">
<?php topbar('My Profile', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="row g-3">
  <div class="col-md-4">
    <?php $returnUrl = '/portal/wing-head/profile.php'; include __DIR__ . '/../includes/photo-upload-widget.php'; ?>
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-id-card me-2"></i>Account Info</div>
      <div style="padding:16px">
        <table class="table table-sm mb-0" style="font-size:.86rem">
          <tr><th style="color:#6b7280;width:45%">Name</th><td><?= h($user['name']) ?></td></tr>
          <tr><th style="color:#6b7280">User ID</th><td><?= h($user['user_id']) ?></td></tr>
          <tr><th style="color:#6b7280">Email</th><td><?= h($user['email'] ?: '—') ?></td></tr>
          <tr><th style="color:#6b7280">Role</th><td>Wing Head</td></tr>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-lock me-2"></i>Change Password</div>
      <div style="padding:20px">
        <form method="POST">
          <input type="hidden" name="action" value="change_password">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">New Password</label>
            <input type="password" name="new_password" class="form-control" required minlength="6">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-danger"><i class="fas fa-key me-1"></i>Change Password</button>
        </form>
      </div>
    </div>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('examination_head');
$db   = getDB();

$uRow = $db->prepare('SELECT email, phone FROM users WHERE id = ?');
$uRow->execute([$user['id']]);
$uRow = $uRow->fetch();

// Fetch teachers record for emp_id / qualification
$tRow = null;
try {
    $st = $db->prepare('SELECT emp_id, qualification, join_date FROM teachers WHERE user_id = ?');
    $st->execute([$user['id']]);
    $tRow = $st->fetch() ?: null;
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        try {
            $db->prepare('UPDATE users SET email = ?, phone = ? WHERE id = ?')
               ->execute([$email, $phone, $user['id']]);
            $_SESSION['user']['email'] = $email;
            $uRow['email'] = $email;
            $uRow['phone'] = $phone;
            logActivity($user['id'], 'profile_update', 'Updated contact info');
            setFlash('success', 'Profile updated successfully.');
        } catch (Exception $e) {
            setFlash('danger', 'Profile update failed.');
        }
    } elseif ($action === 'change_password') {
        $current = trim($_POST['current_password'] ?? '');
        $new     = trim($_POST['new_password']     ?? '');
        $confirm = trim($_POST['confirm_password'] ?? '');
        if ($new !== $confirm) {
            setFlash('danger', 'Passwords do not match.');
        } elseif (strlen($new) < 6) {
            setFlash('danger', 'Password must be at least 6 characters.');
        } else {
            $pSt = $db->prepare('SELECT password FROM users WHERE id = ?');
            $pSt->execute([$user['id']]);
            $p = $pSt->fetch();
            if ($p && password_verify($current, $p['password'])) {
                $db->prepare('UPDATE users SET password = ? WHERE id = ?')
                   ->execute([password_hash($new, PASSWORD_BCRYPT), $user['id']]);
                logActivity($user['id'], 'password_change', 'Changed password');
                setFlash('success', 'Password changed successfully.');
            } else {
                setFlash('danger', 'Current password is incorrect.');
            }
        }
    }
    redirect('/portal/exam-head/profile.php');
}

pageHead('My Profile', 'examination_head');
$links = getExamHeadLinks();
?>
<div class="portal-wrap">
<?php sidebar('examination_head', 'profile', $links, $user); ?>
<div class="main-area">
<?php topbar('My Profile', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="row g-3">
  <div class="col-md-4">
    <?php $returnUrl = '/portal/exam-head/profile.php'; include __DIR__ . '/../includes/photo-upload-widget.php'; ?>
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-id-card me-2"></i>Account Info</div>
      <div style="padding:16px">
        <table class="table table-sm mb-0" style="font-size:.86rem">
          <tr><th style="color:#6b7280;width:45%">Name</th><td><?= h($user['name']) ?></td></tr>
          <tr><th style="color:#6b7280">User ID</th><td><?= h($user['user_id']) ?></td></tr>
          <?php if ($tRow): ?>
          <tr><th style="color:#6b7280">Emp. ID</th><td><?= h($tRow['emp_id'] ?: '—') ?></td></tr>
          <tr><th style="color:#6b7280">Qualification</th><td><?= h($tRow['qualification'] ?: '—') ?></td></tr>
          <tr><th style="color:#6b7280">Joined</th><td><?= $tRow['join_date'] ? fDate($tRow['join_date']) : '—' ?></td></tr>
          <?php endif; ?>
          <tr><th style="color:#6b7280">Email</th><td><?= h($uRow['email'] ?: '—') ?></td></tr>
          <tr><th style="color:#6b7280">Phone</th><td><?= h($uRow['phone'] ?: '—') ?></td></tr>
          <tr><th style="color:#6b7280">Role</th><td>Examination Head</td></tr>
          <tr><th style="color:#6b7280">Campus</th><td>Main Campus (Class 1–12)</td></tr>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-edit me-2"></i>Edit Profile</div>
      <div style="padding:20px">
        <form method="POST">
          <input type="hidden" name="action" value="update_profile">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Email</label>
            <input type="email" name="email" class="form-control" value="<?= h($uRow['email'] ?? '') ?>">
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold" style="font-size:.85rem">Phone</label>
            <input type="tel" name="phone" class="form-control" value="<?= h($uRow['phone'] ?? '') ?>" placeholder="e.g. 0300-1234567">
          </div>
          <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Save Changes</button>
        </form>
      </div>
    </div>

    <div class="sec-card mt-3">
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
<?php pageFooter(); ?>

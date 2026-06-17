<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user    = requireAuth('teacher');
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);
if (!$teacher) { setFlash('danger','Teacher record not found.'); redirect('/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';

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
                logActivity($user['id'], 'password_change', 'Changed password');
                setFlash('success','Password changed successfully.');
            } else {
                setFlash('danger','Current password is incorrect.');
            }
        }
    } else {
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $qual  = trim($_POST['qualification'] ?? '');
        $db->prepare('UPDATE teachers SET phone = ?, qualification = ? WHERE user_id = ?')
           ->execute([$phone, $qual, $user['id']]);
        if ($email) {
            $db->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$email, $user['id']]);
        }
        logActivity($user['id'], 'profile_update', 'Updated profile');
        setFlash('success','Profile updated successfully.');
    }
    redirect('/teacher/profile.php');
}

pageHead('My Profile', 'teacher');
$links = getTeacherLinks();
?>
<div class="portal-wrap">
<?php sidebar('teacher', 'profile', $links, $user); ?>
<div class="main-area">
<?php topbar('My Profile', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="row g-3">
  <div class="col-md-4">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-id-card me-2"></i>Teacher Information</div>
      <div style="padding:16px">
        <div class="text-center mb-3">
          <div style="width:72px;height:72px;background:var(--accent);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:2rem;font-weight:700"><?= strtoupper(substr($teacher['name'],0,1)) ?></div>
          <div class="fw-bold mt-2"><?= h($teacher['name']) ?></div>
          <div style="font-size:.82rem;color:#6b7280"><?= h($teacher['emp_id']) ?></div>
        </div>
        <table class="table table-sm mb-0" style="font-size:.86rem">
          <tr><th style="color:#6b7280;width:45%">Subject</th><td><?= h($teacher['subject_name'] ?? '—') ?></td></tr>
          <tr><th style="color:#6b7280">Code</th><td><?= h($teacher['subject_code'] ?? '—') ?></td></tr>
          <tr><th style="color:#6b7280">Email</th><td><?= h($teacher['email'] ?: '—') ?></td></tr>
          <tr><th style="color:#6b7280">Phone</th><td><?= h($teacher['phone'] ?: '—') ?></td></tr>
          <tr><th style="color:#6b7280">Qualification</th><td><?= h($teacher['qualification'] ?: '—') ?></td></tr>
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
            <label class="form-label fw-semibold" style="font-size:.85rem">Phone</label>
            <input type="tel" name="phone" class="form-control" value="<?= h($teacher['phone'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Email</label>
            <input type="email" name="email" class="form-control" value="<?= h($teacher['email'] ?? '') ?>">
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold" style="font-size:.85rem">Qualification</label>
            <input type="text" name="qualification" class="form-control" value="<?= h($teacher['qualification'] ?? '') ?>" placeholder="e.g. M.Phil Physics">
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

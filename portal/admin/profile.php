<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

// ── POST handlers ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
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
                $hash = password_hash($new, PASSWORD_BCRYPT);
                $db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $user['id']]);
                logActivity($user['id'], 'password_change', 'Admin changed password');
                setFlash('success', 'Password changed successfully.');
            } else {
                setFlash('danger', 'Current password is incorrect.');
            }
        }

    } elseif ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');

        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('danger', 'Invalid email address.');
        } else {
            if ($email) {
                $ck = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
                $ck->execute([$email, $user['id']]);
                if ($ck->fetch()) {
                    setFlash('danger', 'That email is already used by another account.');
                    redirect('/portal/admin/profile.php');
                }
            }
            $db->prepare('UPDATE users SET email = ? WHERE id = ?')
               ->execute([$email ?: null, $user['id']]);
            $_SESSION['user']['email'] = $email;
            logActivity($user['id'], 'profile_update', 'Admin updated profile info');
            setFlash('success', 'Profile updated successfully.');
        }
    }

    redirect('/portal/admin/profile.php');
}

// ── Fetch fresh user row ──────────────────────────────────────────
$row = $db->prepare('SELECT * FROM users WHERE id = ?');
$row->execute([$user['id']]);
$adminUser = $row->fetch();

// ── Photo status (fresh) ──────────────────────────────────────────
$photoStatus = $adminUser['photo_status']           ?? 'none';
$photoUrl    = getProfilePhotoUrl($user['id']);

// ── Recent activity (last 8) ─────────────────────────────────────
$actSt = $db->prepare(
    'SELECT action, details, created_at FROM activity_log
     WHERE user_id = ? ORDER BY created_at DESC LIMIT 8'
);
$actSt->execute([$user['id']]);
$activities = $actSt->fetchAll();

pageHead('My Profile', 'admin');
$links = getAdminLinks();
?>
<?php echo '<div class="portal-wrap">'; ?>
<?php sidebar('admin', 'profile', $links, $user); ?>
<div class="main-area">
<?php topbar('My Profile', $user); ?>
<div class="page-content" style="padding:20px 22px 32px">
<?= flashHtml() ?>

<div class="row g-3">

  <!-- Left column: photo + info card -->
  <div class="col-md-4">

    <?php $returnUrl = '/portal/admin/profile.php'; include __DIR__ . '/../includes/photo-upload-widget.php'; ?>

    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-id-badge me-2"></i>Account Information</div>
      <div style="padding:16px">

        <!-- Avatar + name -->
        <div class="text-center mb-3">
          <?php
            $av = _avatarHtml($user['id'], _initials($adminUser['name'] ?? ''), 80);
            if (str_starts_with($av, '<img')):
          ?>
          <div style="width:80px;height:80px;border-radius:50%;overflow:hidden;margin:0 auto 10px;border:3px solid var(--accent);box-shadow:0 4px 16px rgba(0,0,0,.12)"><?= $av ?></div>
          <?php else: ?>
          <div style="width:80px;height:80px;border-radius:50%;background:var(--accent);display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:2rem;font-weight:700;margin-bottom:10px;box-shadow:0 4px 16px rgba(0,0,0,.12)"><?= $av ?></div>
          <?php endif; ?>
          <div class="fw-bold" style="font-size:1rem"><?= h($adminUser['name']) ?></div>
          <div style="font-size:.8rem;color:#6b7280"><?= h($adminUser['user_id']) ?></div>
          <span class="badge mt-1" style="background:var(--accent);font-size:.72rem">Administrator</span>
        </div>

        <table class="table table-sm mb-0" style="font-size:.86rem">
          <tr>
            <th style="color:#6b7280;width:42%;font-weight:500">Email</th>
            <td><?= h($adminUser['email'] ?: '—') ?></td>
          </tr>
          <tr>
            <th style="color:#6b7280;font-weight:500">Status</th>
            <td><span class="badge bg-success" style="font-size:.72rem">Active</span></td>
          </tr>
          <tr>
            <th style="color:#6b7280;font-weight:500">Last Login</th>
            <td style="font-size:.8rem"><?= $adminUser['last_login'] ? fDate($adminUser['last_login']) : '—' ?></td>
          </tr>
          <tr>
            <th style="color:#6b7280;font-weight:500">Photo</th>
            <td>
              <?php
                $badge = match($photoStatus) {
                    'approved' => '<span class="badge bg-success" style="font-size:.7rem">Approved</span>',
                    'pending'  => '<span class="badge bg-warning text-dark" style="font-size:.7rem">Pending</span>',
                    'rejected' => '<span class="badge bg-danger" style="font-size:.7rem">Rejected</span>',
                    default    => '<span class="badge bg-secondary" style="font-size:.7rem">None</span>',
                };
                echo $badge;
              ?>
            </td>
          </tr>
        </table>
      </div>
    </div>

    <!-- Recent activity -->
    <?php if (!empty($activities)): ?>
    <div class="sec-card mt-3">
      <div class="sec-card-header"><i class="fas fa-history me-2"></i>Recent Activity</div>
      <div style="padding:0">
        <?php foreach ($activities as $act): ?>
        <div class="d-flex align-items-start gap-2 px-3 py-2 border-bottom" style="font-size:.82rem">
          <i class="fas fa-circle-dot mt-1 flex-shrink-0" style="font-size:.45rem;color:var(--accent);margin-top:5px"></i>
          <div style="flex:1;min-width:0">
            <div><?= h(ucwords(str_replace('_', ' ', $act['action']))) ?></div>
            <?php if ($act['details']): ?>
            <div style="color:#9ca3af;font-size:.76rem"><?= h($act['details']) ?></div>
            <?php endif; ?>
          </div>
          <div style="color:#9ca3af;font-size:.75rem;flex-shrink:0"><?= fDate($act['created_at']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /col -->

  <!-- Right column: edit forms -->
  <div class="col-md-8">

    <!-- Edit contact info -->
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-edit me-2"></i>Edit Profile</div>
      <div style="padding:20px">
        <form method="POST">
          <input type="hidden" name="action" value="update_profile">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">
              <i class="fas fa-envelope me-1 opacity-50"></i>Email Address
            </label>
            <input type="email" name="email" class="form-control"
                   value="<?= h($adminUser['email'] ?? '') ?>"
                   placeholder="admin@bmcbinqasim.com">
          </div>
          <button type="submit" class="btn btn-success btn-sm">
            <i class="fas fa-save me-1"></i>Save Changes
          </button>
        </form>
      </div>
    </div>

    <!-- Change password -->
    <div class="sec-card mt-3">
      <div class="sec-card-header"><i class="fas fa-lock me-2"></i>Change Password</div>
      <div style="padding:20px">
        <form method="POST" id="pwdForm">
          <input type="hidden" name="action" value="change_password">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">New Password</label>
            <div class="input-group">
              <input type="password" name="new_password" id="newPwd" class="form-control"
                     required minlength="6" oninput="checkStrength(this.value)">
              <button type="button" class="btn btn-outline-secondary btn-sm"
                      onclick="togglePwd('newPwd',this)" title="Show/hide">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <div id="pwdStrength" class="mt-1" style="height:4px;border-radius:2px;background:#e5e7eb;overflow:hidden">
              <div id="pwdBar" style="height:100%;width:0;transition:width .3s,background .3s"></div>
            </div>
            <div id="pwdHint" style="font-size:.74rem;color:#9ca3af;margin-top:3px">Minimum 6 characters</div>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold" style="font-size:.85rem">Confirm New Password</label>
            <input type="password" name="confirm_password" id="confPwd" class="form-control"
                   required oninput="checkMatch()">
            <div id="matchHint" style="font-size:.74rem;margin-top:3px"></div>
          </div>
          <button type="submit" class="btn btn-danger btn-sm">
            <i class="fas fa-key me-1"></i>Change Password
          </button>
        </form>
      </div>
    </div>

  </div><!-- /col -->
</div><!-- /row -->

</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd(id, btn) {
  const inp = document.getElementById(id);
  const show = inp.type === 'password';
  inp.type = show ? 'text' : 'password';
  btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}
function checkStrength(v) {
  let s = 0;
  if (v.length >= 6)  s++;
  if (v.length >= 10) s++;
  if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
  if (/\d/.test(v))   s++;
  if (/[^A-Za-z0-9]/.test(v)) s++;
  const colors = ['','#ef4444','#f59e0b','#3b82f6','#22c55e','#16a34a'];
  const labels = ['','Weak','Fair','Good','Strong','Very Strong'];
  const bar = document.getElementById('pwdBar');
  bar.style.width = (s * 20) + '%';
  bar.style.background = colors[s] || '#e5e7eb';
  document.getElementById('pwdHint').textContent = v ? labels[s] : 'Minimum 6 characters';
  document.getElementById('pwdHint').style.color = colors[s] || '#9ca3af';
}
function checkMatch() {
  const a = document.getElementById('newPwd').value;
  const b = document.getElementById('confPwd').value;
  const el = document.getElementById('matchHint');
  if (!b) { el.textContent=''; return; }
  el.textContent = a === b ? '✓ Passwords match' : '✗ Passwords do not match';
  el.style.color  = a === b ? '#22c55e' : '#ef4444';
}
</script>
</body></html>

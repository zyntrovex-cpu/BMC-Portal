<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user'])) redirect('/index.php');

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$done  = false;

$user = null;
if ($token) {
    $db = getDB();
    // Use PHP for expiry check to avoid MySQL/PHP timezone mismatch
    $st = $db->prepare('SELECT id, name, role, reset_expires FROM users WHERE reset_token = ? AND status = "active"');
    $st->execute([$token]);
    $row = $st->fetch() ?: null;
    if ($row && $row['reset_expires'] && strtotime($row['reset_expires'] . ' UTC') > time()) {
        $user = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $newPass = $_POST['password']         ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (strlen($newPass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($newPass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        $db->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')
           ->execute([$hash, $user['id']]);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Password — BMC Portal</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
  body { min-height:100vh; background:linear-gradient(135deg,#0f1f3d 0%,#1c3054 50%,#0d2847 100%); display:flex; align-items:center; justify-content:center; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; padding:16px; }
  .card { background:#fff; border-radius:16px; box-shadow:0 24px 64px rgba(0,0,0,.45); width:420px; max-width:100%; overflow:hidden; }
  .card-header { background:linear-gradient(135deg,#1c3054,#2563eb); color:#fff; padding:32px; text-align:center; }
  .card-body { padding:32px; }
  .form-label { font-size:.85rem; font-weight:600; color:#374151; }
  .form-control { border-radius:8px; border:1.5px solid #d5dde8; padding:11px 14px; }
  .form-control:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
  .btn-success { border-radius:8px; padding:12px; font-weight:700; font-size:.95rem; }
  .strength-bar { height:4px; border-radius:2px; transition:all .3s; background:#e5e7eb; }
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <div style="font-size:2.4rem;margin-bottom:8px">🔒</div>
    <h5 class="mb-1" style="font-weight:700">Reset your password</h5>
    <p class="mb-0" style="font-size:.82rem;opacity:.75">BMC Portal — <?= SESSION_YEAR ?></p>
  </div>
  <div class="card-body">

    <?php if ($done): ?>
      <div class="text-center py-2">
        <div style="font-size:3rem;color:#059669;margin-bottom:12px">✅</div>
        <h6 style="font-weight:700;margin-bottom:8px">Password changed!</h6>
        <p style="font-size:.87rem;color:#6b7280;margin-bottom:20px">Your password has been updated. You can now log in with your new password.</p>
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-success w-100">Go to login</a>
      </div>

    <?php elseif (!$token || !$user): ?>
      <div class="text-center py-2">
        <div style="font-size:3rem;margin-bottom:12px">⏰</div>
        <h6 style="font-weight:700;margin-bottom:8px">Link expired or invalid</h6>
        <p style="font-size:.87rem;color:#6b7280;margin-bottom:20px">Password reset links expire after 30 minutes. Please request a new one.</p>
        <a href="<?= BASE_URL ?>/forgot-password.php" class="btn btn-primary w-100" style="background:linear-gradient(135deg,#1c3054,#2563eb);border:none;border-radius:8px;padding:12px;font-weight:700">Request new link</a>
      </div>

    <?php else: ?>
      <?php if ($error): ?>
        <div class="alert alert-danger" style="font-size:.87rem;border-radius:8px;padding:10px 14px"><?= h($error) ?></div>
      <?php endif; ?>
      <p style="font-size:.87rem;color:#6b7280;margin-bottom:22px">Hi <strong><?= h($user['name']) ?></strong>, enter your new password below.</p>
      <form method="POST">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <div class="mb-3">
          <label class="form-label">New password</label>
          <input type="password" name="password" id="newPass" class="form-control" required minlength="6"
                 oninput="checkStrength(this.value)" placeholder="Minimum 6 characters" autofocus>
          <div class="strength-bar mt-2" id="strengthBar"></div>
          <div id="strengthLabel" style="font-size:.75rem;color:#9ca3af;margin-top:3px"></div>
        </div>
        <div class="mb-4">
          <label class="form-label">Confirm new password</label>
          <input type="password" name="password_confirm" id="confirmPass" class="form-control" required
                 oninput="checkMatch()" placeholder="Repeat password">
          <div id="matchLabel" style="font-size:.75rem;margin-top:3px"></div>
        </div>
        <button type="submit" class="btn btn-success w-100">Set new password</button>
      </form>
    <?php endif; ?>

    <?php if (!$done): ?>
    <div class="text-center mt-3">
      <a href="<?= BASE_URL ?>/index.php" style="font-size:.84rem;color:#6b7280;text-decoration:none">← Back to login</a>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
function checkStrength(val) {
  var bar=document.getElementById('strengthBar'),lbl=document.getElementById('strengthLabel'),s=0;
  if(val.length>=6)s++;if(val.length>=8)s++;if(/[A-Z]/.test(val))s++;if(/[0-9]/.test(val))s++;if(/[^A-Za-z0-9]/.test(val))s++;
  var m=[['#e5e7eb',''],['#dc2626','Weak'],['#d97706','Fair'],['#f59e0b','Good'],['#16a34a','Strong'],['#059669','Very Strong']][Math.min(s,5)];
  bar.style.background=m[0];bar.style.width=(s*20)+'%';lbl.textContent=m[1];lbl.style.color=m[0];
}
function checkMatch() {
  var p1=document.getElementById('newPass').value,p2=document.getElementById('confirmPass').value,lbl=document.getElementById('matchLabel');
  if(!p2){lbl.textContent='';return;}
  if(p1===p2){lbl.textContent='✓ Passwords match';lbl.style.color='#059669';}
  else{lbl.textContent='✗ Passwords do not match';lbl.style.color='#dc2626';}
}
</script>
</body>
</html>

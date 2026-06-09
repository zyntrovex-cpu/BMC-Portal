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

// Validate token
$user = null;
if ($token) {
    $db  = getDB();
    $st  = $db->prepare('SELECT id, name, role FROM users WHERE reset_token = ? AND reset_expires > NOW() AND status = "active"');
    $st->execute([$token]);
    $user = $st->fetch() ?: null;
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
  body { min-height:100vh; background:linear-gradient(135deg,#0f1f3d 0%,#1c3054 50%,#0d2847 100%); display:flex; align-items:center; justify-content:center; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; }
  .card { background:#fff; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,.4); width:420px; max-width:96vw; overflow:hidden; }
  .card-header { background:linear-gradient(135deg,#1c3054,#2563eb); color:#fff; padding:28px 32px; text-align:center; }
  .card-body { padding:32px; }
  .form-label { font-size:.85rem; font-weight:600; color:#374151; }
  .form-control { border-radius:6px; border:1.5px solid #d5dde8; }
  .form-control:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
  .btn-success { border-radius:8px; padding:11px; font-weight:700; }
  .strength-bar { height:4px; border-radius:2px; transition:all .3s; }
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <div style="font-size:2.5rem;margin-bottom:8px">🔑</div>
    <h5 class="mb-1">Reset Password</h5>
    <p class="mb-0" style="font-size:.82rem;opacity:.8">BMC Portal — <?= SESSION_YEAR ?></p>
  </div>
  <div class="card-body">
    <?php if ($done): ?>
      <div class="text-center py-2">
        <div style="font-size:3rem;color:#059669;margin-bottom:12px">✅</div>
        <h5>Password Reset Successfully</h5>
        <p style="font-size:.87rem;color:#6b7280">Your password has been updated. You can now log in with your new password.</p>
        <a href="/index.php" class="btn btn-success w-100 mt-2">Go to Login</a>
      </div>
    <?php elseif (!$token || !$user): ?>
      <div class="alert alert-danger" style="font-size:.87rem;border-radius:6px">
        <strong>Invalid or expired link.</strong><br>
        Password reset links expire after 30 minutes.
      </div>
      <a href="/forgot-password.php" class="btn btn-outline-primary w-100">Request New Link</a>
    <?php else: ?>
      <?php if ($error): ?>
        <div class="alert alert-danger" style="font-size:.87rem;border-radius:6px"><?= h($error) ?></div>
      <?php endif; ?>
      <p style="font-size:.87rem;color:#6b7280;margin-bottom:20px">Hi <strong><?= h($user['name']) ?></strong>, enter your new password below.</p>
      <form method="POST">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <div class="mb-3">
          <label class="form-label">New Password</label>
          <input type="password" name="password" id="newPass" class="form-control" required minlength="6"
                 oninput="checkStrength(this.value)" placeholder="Minimum 6 characters">
          <div class="strength-bar mt-1" id="strengthBar" style="background:#e5e7eb"></div>
          <div id="strengthLabel" style="font-size:.75rem;color:#9ca3af;margin-top:3px"></div>
        </div>
        <div class="mb-4">
          <label class="form-label">Confirm New Password</label>
          <input type="password" name="password_confirm" id="confirmPass" class="form-control" required
                 oninput="checkMatch()" placeholder="Repeat password">
          <div id="matchLabel" style="font-size:.75rem;margin-top:3px"></div>
        </div>
        <button type="submit" class="btn btn-success w-100">Set New Password</button>
      </form>
    <?php endif; ?>

    <?php if (!$done): ?>
    <div class="text-center mt-3">
      <a href="/index.php" style="font-size:.84rem;color:#6b7280;text-decoration:none">← Back to Login</a>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
function checkStrength(val) {
    const bar = document.getElementById('strengthBar');
    const lbl = document.getElementById('strengthLabel');
    let score = 0;
    if (val.length >= 6) score++;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const map = [[0,'#e5e7eb',''], [1,'#dc2626','Weak'], [2,'#d97706','Fair'], [3,'#f59e0b','Good'], [4,'#16a34a','Strong'], [5,'#059669','Very Strong']];
    const [, color, label] = map[Math.min(score, 5)];
    bar.style.background = color;
    bar.style.width = (score * 20) + '%';
    lbl.textContent = label;
    lbl.style.color = color;
}
function checkMatch() {
    const p1 = document.getElementById('newPass').value;
    const p2 = document.getElementById('confirmPass').value;
    const lbl = document.getElementById('matchLabel');
    if (!p2) { lbl.textContent = ''; return; }
    if (p1 === p2) { lbl.textContent = '✓ Passwords match'; lbl.style.color = '#059669'; }
    else { lbl.textContent = '✗ Passwords do not match'; lbl.style.color = '#dc2626'; }
}
</script>
</body>
</html>

<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user'])) redirect('/index.php');

$message   = '';
$error     = '';
$directLink = null; // shown on-screen when SMTP is off or fails

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email  = trim($_POST['email']   ?? '');
    $userId = trim($_POST['user_id'] ?? '');

    if (!$email && !$userId) {
        $error = 'Please enter your Email or User ID.';
    } else {
        $db = getDB();
        if ($email) {
            $st = $db->prepare('SELECT id, name, email, role FROM users WHERE email = ? AND status = "active"');
            $st->execute([$email]);
        } else {
            $st = $db->prepare('SELECT id, name, email, role FROM users WHERE user_id = ? AND status = "active"');
            $st->execute([$userId]);
        }
        $foundUser = $st->fetch();

        if ($foundUser) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 1800); // 30 minutes

            $db->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')
               ->execute([$token, $expires, $foundUser['id']]);

            // Auto-detect app URL if not set in settings
            $appUrl = getSetting('app_url', '');
            if (!$appUrl) {
                $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $appUrl  = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
            }
            $resetLink = rtrim($appUrl, '/') . '/reset-password.php?token=' . $token;

            if ($foundUser['email']) {
                $sent = sendPasswordReset($foundUser['email'], $foundUser['name'], $resetLink);
                if ($sent) {
                    $message = 'Password reset link sent to <strong>' . h($foundUser['email']) . '</strong>. Check your inbox (expires in 30 min).';
                } else {
                    // SMTP failed or not configured — show link on screen
                    $directLink = $resetLink;
                    if (smtpNotConfigured()) {
                        $error = 'SMTP email is not configured. Copy the reset link below and send it to the user manually.';
                    } else {
                        $error = 'Email could not be sent (' . h(getSmtpError()) . '). Use the reset link below instead.';
                    }
                }
            } else {
                // No email on file — show link on screen for admin to share
                $directLink = $resetLink;
                $error = 'This account has no email address. Copy the reset link below and share it with the user manually.';
            }
        } else {
            $message = 'If that account exists, a reset link has been generated.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgot Password — BMC Portal</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
  body { min-height:100vh; background:linear-gradient(135deg,#0f1f3d 0%,#1c3054 50%,#0d2847 100%); display:flex; align-items:center; justify-content:center; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; padding:16px; }
  .card { background:#fff; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,.4); width:480px; max-width:100%; overflow:hidden; }
  .card-header { background:linear-gradient(135deg,#1c3054,#2563eb); color:#fff; padding:28px 32px; text-align:center; }
  .card-body { padding:32px; }
  .form-label { font-size:.85rem; font-weight:600; color:#374151; }
  .form-control { border-radius:6px; border:1.5px solid #d5dde8; }
  .form-control:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
  .btn-primary { background:linear-gradient(135deg,#1c3054,#2563eb); border:none; border-radius:8px; padding:11px; font-weight:700; }
  .divider { display:flex; align-items:center; gap:12px; margin:16px 0; }
  .divider::before, .divider::after { content:''; flex:1; height:1px; background:#e5e7eb; }
  .divider span { font-size:.8rem; color:#9ca3af; }
  .link-box { background:#f0f9ff; border:1.5px solid #38bdf8; border-radius:8px; padding:14px 16px; }
  .link-box input { font-size:.78rem; border:1px solid #bae6fd; border-radius:6px; background:#fff; color:#0369a1; }
  .copy-btn { font-size:.78rem; padding:4px 12px; white-space:nowrap; }
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <div style="font-size:2.5rem;margin-bottom:8px">🔐</div>
    <h5 class="mb-1">Forgot Password</h5>
    <p class="mb-0" style="font-size:.82rem;opacity:.8">BMC Portal — <?= SESSION_YEAR ?></p>
  </div>
  <div class="card-body">

    <?php if ($message): ?>
      <div class="alert alert-success" style="font-size:.87rem;border-radius:6px"><?= $message ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-warning" style="font-size:.87rem;border-radius:6px">
        <i class="fas fa-exclamation-triangle me-1"></i><?= $error ?>
      </div>
    <?php endif; ?>

    <?php if ($directLink): ?>
      <div class="link-box mb-3">
        <div style="font-size:.78rem;font-weight:700;color:#0369a1;margin-bottom:8px">
          <i class="fas fa-link me-1"></i> Password Reset Link <span style="color:#64748b;font-weight:400">(expires in 30 min)</span>
        </div>
        <div class="d-flex gap-2">
          <input type="text" id="resetLinkInput" class="form-control form-control-sm" value="<?= h($directLink) ?>" readonly onclick="this.select()">
          <button class="btn btn-sm btn-primary copy-btn" onclick="copyLink()">Copy</button>
        </div>
        <div id="copyMsg" style="font-size:.75rem;color:#059669;margin-top:6px;display:none">✓ Copied to clipboard!</div>
        <div style="font-size:.75rem;color:#64748b;margin-top:6px">
          Share this link with the user so they can reset their password.
          Or <a href="<?= h($directLink) ?>" target="_blank">open it directly</a>.
        </div>
      </div>
    <?php endif; ?>

    <?php if (!$message && !$directLink): ?>
    <p style="font-size:.87rem;color:#6b7280;margin-bottom:20px">Enter your email address or User ID to generate a password reset link.</p>
    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="your@email.com">
      </div>
      <div class="divider"><span>OR</span></div>
      <div class="mb-4">
        <label class="form-label">User ID <small class="text-muted">(Roll No / T001 / ADM001 / FIN001)</small></label>
        <input type="text" name="user_id" class="form-control" placeholder="Enter your User ID">
      </div>
      <button type="submit" class="btn btn-primary w-100">Generate Reset Link</button>
    </form>
    <?php elseif (!$message): ?>
      <a href="<?= BASE_URL ?>/forgot-password.php" class="btn btn-outline-secondary w-100 mt-2">Generate Another Link</a>
    <?php endif; ?>

    <div class="text-center mt-3">
      <a href="<?= BASE_URL ?>/index.php" style="font-size:.84rem;color:#6b7280;text-decoration:none">← Back to Login</a>
    </div>
  </div>
</div>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script>
function copyLink() {
  var inp = document.getElementById('resetLinkInput');
  inp.select();
  inp.setSelectionRange(0, 99999);
  try { navigator.clipboard.writeText(inp.value); } catch(e) { document.execCommand('copy'); }
  document.getElementById('copyMsg').style.display = 'block';
  setTimeout(function(){ document.getElementById('copyMsg').style.display='none'; }, 2500);
}
</script>
</body>
</html>

<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user'])) redirect('/index.php');

$message = '';
$error   = '';

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
        $user = $st->fetch();

        if ($user && $user['email']) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 1800); // 30 minutes

            $db->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')
               ->execute([$token, $expires, $user['id']]);

            $baseUrl  = getSetting('app_url', 'http://localhost:8080');
            $resetLink = rtrim($baseUrl, '/') . '/reset-password.php?token=' . $token;

            if (sendPasswordReset($user['email'], $user['name'], $resetLink)) {
                $message = "Password reset link sent to <strong>" . h($user['email']) . "</strong>. Check your inbox (link expires in 30 min).";
            } else {
                $error = 'Could not send email. Please contact admin.';
            }
        } else {
            // Intentionally vague for security
            $message = 'If that account exists with an email, a reset link has been sent.';
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
  body { min-height:100vh; background:linear-gradient(135deg,#0f1f3d 0%,#1c3054 50%,#0d2847 100%); display:flex; align-items:center; justify-content:center; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; }
  .card { background:#fff; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,.4); width:420px; max-width:96vw; overflow:hidden; }
  .card-header { background:linear-gradient(135deg,#1c3054,#2563eb); color:#fff; padding:28px 32px; text-align:center; }
  .card-body { padding:32px; }
  .form-label { font-size:.85rem; font-weight:600; color:#374151; }
  .form-control { border-radius:6px; border:1.5px solid #d5dde8; }
  .form-control:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
  .btn-primary { background:linear-gradient(135deg,#1c3054,#2563eb); border:none; border-radius:8px; padding:11px; font-weight:700; }
  .divider { display:flex; align-items:center; gap:12px; margin:16px 0; }
  .divider::before, .divider::after { content:''; flex:1; height:1px; background:#e5e7eb; }
  .divider span { font-size:.8rem; color:#9ca3af; }
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
      <div class="alert alert-danger" style="font-size:.87rem;border-radius:6px"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if (!$message): ?>
    <p style="font-size:.87rem;color:#6b7280;margin-bottom:20px">Enter your email address or User ID and we'll send you a password reset link.</p>
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
      <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
    </form>
    <?php endif; ?>

    <div class="text-center mt-3">
      <a href="<?= BASE_URL ?>/index.php" style="font-size:.84rem;color:#6b7280;text-decoration:none">← Back to Login</a>
    </div>
  </div>
</div>
</body>
</html>

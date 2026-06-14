<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user'])) redirect('/index.php');

$sent   = false;
$error  = '';
$sentTo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = trim($_POST['user_id'] ?? '');

    if (!$userId) {
        $error = 'Please enter your User ID.';
    } else {
        $db = getDB();
        $st = $db->prepare('SELECT id, name, email, user_id FROM users WHERE user_id = ? AND status = "active"');
        $st->execute([$userId]);
        $found = $st->fetch();

        if ($found) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 1800); // 30 minutes

            $db->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')
               ->execute([$token, $expires, $found['id']]);

            $appUrl = getSetting('app_url', '');
            if (!$appUrl) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $appUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
            }
            $resetLink = rtrim($appUrl, '/') . '/reset-password.php?token=' . $token;

            if ($found['email']) {
                $body = mailTemplate('Reset Your Password',
                    "<p>Hi <strong>" . h($found['name']) . "</strong>,</p>
                    <p>We received a request to reset your BMC Portal password.</p>
                    <p>Click the button below to choose a new password. This link expires in <strong>30 minutes</strong>.</p>
                    <p><a class='btn' href='$resetLink'>Reset My Password</a></p>
                    <p style='color:#9ca3af;font-size:.85rem'>If you didn't request this, you can safely ignore this email.</p>"
                );
                $ok = sendMail($found['email'], $found['name'], 'Reset Your BMC Portal Password', $body);

                if ($ok) {
                    $sent   = true;
                    $sentTo = $found['email'];
                } else {
                    $_SESSION['pr_direct_link'] = $resetLink;
                    $_SESSION['pr_smtp_error']  = smtpNotConfigured()
                        ? 'SMTP email is not configured.'
                        : 'Email could not be sent: ' . getSmtpError();
                    redirect('/forgot-password.php?fallback=1');
                }
            } else {
                // No email on account — show link for admin
                $_SESSION['pr_direct_link'] = $resetLink;
                $_SESSION['pr_smtp_error']  = 'This account has no email address on file.';
                redirect('/forgot-password.php?fallback=1');
            }
        } else {
            // Don't reveal if account exists
            $sent = true;
        }
    }
}

$fallback   = isset($_GET['fallback']) && !empty($_SESSION['pr_direct_link']);
$directLink = $fallback ? $_SESSION['pr_direct_link'] : null;
$smtpError  = $fallback ? ($_SESSION['pr_smtp_error'] ?? '') : '';
if ($fallback) {
    unset($_SESSION['pr_direct_link'], $_SESSION['pr_smtp_error']);
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
  .card { background:#fff; border-radius:16px; box-shadow:0 24px 64px rgba(0,0,0,.45); width:440px; max-width:100%; overflow:hidden; }
  .card-header { background:linear-gradient(135deg,#1c3054,#2563eb); color:#fff; padding:32px; text-align:center; }
  .card-body { padding:32px; }
  .form-label { font-size:.85rem; font-weight:600; color:#374151; }
  .form-control { border-radius:8px; border:1.5px solid #d5dde8; padding:11px 14px; }
  .form-control:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
  .btn-primary { background:linear-gradient(135deg,#1c3054,#2563eb); border:none; border-radius:8px; padding:12px; font-weight:700; font-size:.95rem; }
  .link-box { background:#f0f9ff; border:1.5px solid #38bdf8; border-radius:10px; padding:16px; }
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <div style="font-size:2.4rem;margin-bottom:8px">🔑</div>
    <h5 class="mb-1" style="font-weight:700">Forgot your password?</h5>
    <p class="mb-0" style="font-size:.82rem;opacity:.75">BMC Portal — <?= SESSION_YEAR ?></p>
  </div>
  <div class="card-body">

    <?php if ($sent): ?>
      <div class="text-center py-2">
        <div style="font-size:3rem;margin-bottom:12px">📧</div>
        <h6 style="font-weight:700;margin-bottom:8px">Check your email</h6>
        <p style="font-size:.87rem;color:#6b7280;margin-bottom:8px">
          A password reset link has been sent to the email address on your account.
          <?php if ($sentTo): ?>
            <br><strong><?= h($sentTo) ?></strong>
          <?php endif; ?>
        </p>
        <p style="font-size:.8rem;color:#9ca3af;margin-bottom:20px">It expires in 30 minutes. Check your spam folder if you don't see it.</p>
        <hr class="my-3">
        <a href="<?= BASE_URL ?>/forgot-password.php" class="btn btn-outline-secondary w-100" style="border-radius:8px;font-weight:600">Try again</a>
      </div>

    <?php elseif ($fallback && $directLink): ?>
      <div class="alert alert-warning" style="font-size:.85rem;border-radius:8px;padding:12px 14px">
        <i class="fas fa-exclamation-triangle me-1"></i> <?= h($smtpError) ?>
        Copy the reset link below and send it to the user manually.
      </div>
      <div class="link-box mb-3">
        <div style="font-size:.75rem;font-weight:700;color:#0369a1;margin-bottom:8px">
          <i class="fas fa-link me-1"></i> Password Reset Link <span style="color:#64748b;font-weight:400">(expires in 30 min)</span>
        </div>
        <div class="d-flex gap-2">
          <input type="text" id="rl" class="form-control form-control-sm" value="<?= h($directLink) ?>" readonly onclick="this.select()" style="font-size:.78rem;color:#0369a1">
          <button class="btn btn-sm btn-primary" onclick="copyLink()" style="white-space:nowrap;border-radius:6px">Copy</button>
        </div>
        <div id="cm" style="font-size:.75rem;color:#059669;margin-top:6px;display:none">✓ Copied!</div>
        <div style="font-size:.75rem;color:#64748b;margin-top:6px">
          Or <a href="<?= h($directLink) ?>" target="_blank">open it directly</a> to reset the password now.
        </div>
      </div>
      <a href="<?= BASE_URL ?>/forgot-password.php" class="btn btn-outline-secondary w-100" style="border-radius:8px;font-weight:600">Generate another link</a>

    <?php else: ?>
      <?php if ($error): ?>
        <div class="alert alert-danger" style="font-size:.87rem;border-radius:8px;padding:10px 14px"><?= h($error) ?></div>
      <?php endif; ?>
      <p style="font-size:.87rem;color:#6b7280;margin-bottom:22px">Enter your User ID and we'll send a password reset link to your registered email address.</p>
      <form method="POST">
        <div class="mb-4">
          <label class="form-label">User ID</label>
          <input type="text" name="user_id" class="form-control" placeholder="e.g. STU001 / T001 / ADM001"
                 value="<?= h($_POST['user_id'] ?? '') ?>" required autofocus autocomplete="off">
          <div style="font-size:.78rem;color:#9ca3af;margin-top:6px">Your roll number or staff ID assigned by the college</div>
        </div>
        <button type="submit" class="btn btn-primary w-100">Send reset link</button>
      </form>
    <?php endif; ?>

    <div class="text-center mt-3">
      <a href="<?= BASE_URL ?>/index.php" style="font-size:.84rem;color:#6b7280;text-decoration:none">← Back to login</a>
    </div>
  </div>
</div>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script>
function copyLink() {
  var inp = document.getElementById('rl');
  inp.select(); inp.setSelectionRange(0,99999);
  try { navigator.clipboard.writeText(inp.value); } catch(e) { document.execCommand('copy'); }
  var cm = document.getElementById('cm');
  cm.style.display = 'block';
  setTimeout(function(){ cm.style.display='none'; }, 2500);
}
</script>
</body>
</html>

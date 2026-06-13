<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user'])) redirect('/index.php');

$step  = $_SESSION['pr_step'] ?? 1;
$error = '';
$info  = '';

// ── Step 1: Find user and generate OTP ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_step'] ?? '') === '1') {
    $email  = trim($_POST['email']   ?? '');
    $userId = trim($_POST['user_id'] ?? '');

    if (!$email && !$userId) {
        $error = 'Please enter your Email or User ID.';
        $step  = 1;
    } else {
        $db = getDB();
        if ($email) {
            $st = $db->prepare('SELECT id, name, email FROM users WHERE email = ? AND status = "active"');
            $st->execute([$email]);
        } else {
            $st = $db->prepare('SELECT id, name, email FROM users WHERE user_id = ? AND status = "active"');
            $st->execute([$userId]);
        }
        $found = $st->fetch();

        if ($found) {
            $otp     = sprintf('%06d', random_int(100000, 999999));
            $expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes
            $db->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')
               ->execute([$otp, $expires, $found['id']]);

            $_SESSION['pr_uid']      = $found['id'];
            $_SESSION['pr_step']     = 2;
            $_SESSION['pr_attempts'] = 0;

            $sent = false;
            if ($found['email']) {
                $body = mailTemplate('Password Reset Code',
                    "<p>Hi <strong>" . h($found['name']) . "</strong>,</p>
                    <p>Your BMC Portal password reset code is:</p>
                    <div class='otp'>$otp</div>
                    <p>This code expires in <strong>10 minutes</strong>. Do not share it with anyone.</p>
                    <p>If you did not request this, ignore this email — your password will not change.</p>"
                );
                $sent = sendMail($found['email'], $found['name'], 'BMC Portal — Password Reset Code', $body);
            }

            if ($sent) {
                $info = 'A 6-digit code was sent to <strong>' . h($found['email']) . '</strong>. Enter it below.';
                unset($_SESSION['pr_otp_screen']);
            } else {
                // Show OTP on screen (admin/no-SMTP fallback)
                $_SESSION['pr_otp_screen'] = $otp;
                if (!$found['email']) {
                    $info = 'This account has no email address. Share the code below with the user.';
                } elseif (smtpNotConfigured()) {
                    $info = 'SMTP is not configured — the reset code is shown below. Share it with the user.';
                } else {
                    $info = 'Email could not be sent (' . h(getSmtpError()) . '). Use the code shown below.';
                }
            }
        } else {
            // Don't reveal whether the account exists
            $_SESSION['pr_uid']      = null;
            $_SESSION['pr_step']     = 2;
            $_SESSION['pr_attempts'] = 0;
            $info = 'If that account exists, a reset code has been generated.';
        }
        $step = 2;
    }
}

// ── Step 2: Verify OTP ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_step'] ?? '') === '2') {
    $step    = 2;
    $entered = preg_replace('/\D/', '', trim($_POST['otp'] ?? ''));
    $uid     = $_SESSION['pr_uid'] ?? null;
    $_SESSION['pr_attempts'] = ($_SESSION['pr_attempts'] ?? 0) + 1;

    if ($_SESSION['pr_attempts'] > 5) {
        $error = 'Too many failed attempts. Please start over.';
        unset($_SESSION['pr_step'], $_SESSION['pr_uid'], $_SESSION['pr_otp_screen'], $_SESSION['pr_attempts']);
        $step = 1;
    } elseif (!$uid) {
        usleep(300000); // timing-safe fake delay
        $error = 'Invalid code. Please try again.';
    } else {
        $db  = getDB();
        $chk = $db->prepare('SELECT id FROM users WHERE id = ? AND reset_token = ? AND reset_expires > NOW()');
        $chk->execute([$uid, $entered]);

        if ($chk->fetch()) {
            $_SESSION['pr_step']     = 3;
            $_SESSION['pr_verified'] = true;
            unset($_SESSION['pr_otp_screen']);
            $step = 3;
        } else {
            $left  = 5 - $_SESSION['pr_attempts'];
            $error = 'Invalid or expired code.' . ($left > 0 ? " $left attempt(s) remaining." : ' Please start over.');
            if ($left <= 0) {
                unset($_SESSION['pr_step'], $_SESSION['pr_uid'], $_SESSION['pr_otp_screen'], $_SESSION['pr_attempts']);
                $step = 1;
            }
        }
    }
}

// ── Step 3: Set new password ─────────────────────────────────────────────────
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_step'] ?? '') === '3') {
    $step     = 3;
    $uid      = $_SESSION['pr_uid']      ?? null;
    $verified = $_SESSION['pr_verified'] ?? false;
    $newPass  = $_POST['password']         ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (!$uid || !$verified) {
        $error = 'Session expired. Please start over.';
        unset($_SESSION['pr_step'], $_SESSION['pr_uid'], $_SESSION['pr_verified'], $_SESSION['pr_attempts']);
        $step = 1;
    } elseif (strlen($newPass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($newPass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db   = getDB();
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        $db->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')
           ->execute([$hash, $uid]);
        unset($_SESSION['pr_step'], $_SESSION['pr_uid'], $_SESSION['pr_verified'],
              $_SESSION['pr_attempts'], $_SESSION['pr_otp_screen']);
        $done = true;
        $step = 4;
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
  .card { background:#fff; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,.4); width:460px; max-width:100%; overflow:hidden; }
  .card-header { background:linear-gradient(135deg,#1c3054,#2563eb); color:#fff; padding:28px 32px; text-align:center; }
  .card-body { padding:32px; }
  .form-label { font-size:.85rem; font-weight:600; color:#374151; }
  .form-control { border-radius:6px; border:1.5px solid #d5dde8; }
  .form-control:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
  .btn-primary { background:linear-gradient(135deg,#1c3054,#2563eb); border:none; border-radius:8px; padding:11px; font-weight:700; }
  .btn-success { border-radius:8px; padding:11px; font-weight:700; }
  .divider { display:flex; align-items:center; gap:12px; margin:16px 0; }
  .divider::before,.divider::after { content:''; flex:1; height:1px; background:#e5e7eb; }
  .divider span { font-size:.8rem; color:#9ca3af; }
  .otp-display { background:#f0f9ff; border:2px solid #38bdf8; border-radius:10px; padding:20px; text-align:center; margin-bottom:16px; }
  .otp-digits { font-size:2.6rem; font-weight:800; letter-spacing:.4em; color:#0c4a6e; font-family:'Courier New',monospace; user-select:all; }
  .otp-input { font-size:1.8rem; font-weight:700; text-align:center; letter-spacing:.35em; border:2px solid #d5dde8; border-radius:8px; padding:14px; width:100%; outline:none; transition:border .2s,box-shadow .2s; }
  .otp-input:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
  .steps { display:flex; margin-bottom:24px; }
  .step { flex:1; text-align:center; font-size:.72rem; font-weight:600; color:#9ca3af; padding:6px 2px; border-bottom:3px solid #e5e7eb; }
  .step.active { color:#2563eb; border-color:#2563eb; }
  .step.done { color:#059669; border-color:#059669; }
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <div style="font-size:2.2rem;margin-bottom:6px">🔐</div>
    <h5 class="mb-1">Forgot Password</h5>
    <p class="mb-0" style="font-size:.82rem;opacity:.8">BMC Portal — <?= SESSION_YEAR ?></p>
  </div>
  <div class="card-body">

  <?php if ($done): ?>
    <div class="text-center py-2">
      <div style="font-size:3rem;color:#059669;margin-bottom:12px">✅</div>
      <h5>Password Reset Successfully</h5>
      <p style="font-size:.87rem;color:#6b7280">Your password has been updated. You can now log in.</p>
      <a href="<?= BASE_URL ?>/index.php" class="btn btn-success w-100 mt-2">Go to Login</a>
    </div>

  <?php else: ?>
    <div class="steps">
      <div class="step <?= $step > 1 ? 'done' : ($step === 1 ? 'active' : '') ?>">1. Identify</div>
      <div class="step <?= $step > 2 ? 'done' : ($step === 2 ? 'active' : '') ?>">2. Verify Code</div>
      <div class="step <?= $step === 3 ? 'active' : '' ?>">3. New Password</div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger" style="font-size:.87rem;border-radius:6px;padding:10px 14px">
        <i class="fas fa-exclamation-circle me-1"></i><?= $error ?>
      </div>
    <?php endif; ?>
    <?php if ($info): ?>
      <div class="alert alert-info" style="font-size:.87rem;border-radius:6px;padding:10px 14px">
        <i class="fas fa-info-circle me-1"></i><?= $info ?>
      </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
    <p style="font-size:.87rem;color:#6b7280;margin-bottom:18px">Enter your email address or User ID to receive a reset code.</p>
    <form method="POST" autocomplete="off">
      <input type="hidden" name="form_step" value="1">
      <div class="mb-3">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="your@email.com" autocomplete="off">
      </div>
      <div class="divider"><span>OR</span></div>
      <div class="mb-4">
        <label class="form-label">User ID <small class="text-muted">(Roll No / T001 / ADM001)</small></label>
        <input type="text" name="user_id" class="form-control" placeholder="Enter your User ID" autocomplete="off">
      </div>
      <button type="submit" class="btn btn-primary w-100">Send Reset Code</button>
    </form>

    <?php elseif ($step === 2): ?>
    <?php if (!empty($_SESSION['pr_otp_screen'])): ?>
    <div class="otp-display">
      <div style="font-size:.72rem;font-weight:700;color:#0369a1;margin-bottom:10px;text-transform:uppercase;letter-spacing:.05em">
        <i class="fas fa-key me-1"></i> Reset Code — expires in 10 min
      </div>
      <div class="otp-digits"><?= htmlspecialchars($_SESSION['pr_otp_screen']) ?></div>
      <div style="font-size:.72rem;color:#64748b;margin-top:8px">Do not share this code with anyone</div>
    </div>
    <?php endif; ?>
    <p style="font-size:.87rem;color:#6b7280;margin-bottom:16px">Enter the 6-digit code<?= !empty($_SESSION['pr_otp_screen']) ? ' shown above' : ' sent to your email' ?>.</p>
    <form method="POST" autocomplete="off" id="otpForm">
      <input type="hidden" name="form_step" value="2">
      <div class="mb-4">
        <label class="form-label">6-Digit Code</label>
        <input type="text" name="otp" id="otpInput" class="otp-input" maxlength="6"
               inputmode="numeric" pattern="[0-9]{6}" placeholder="_ _ _ _ _ _"
               autocomplete="one-time-code" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary w-100">Verify Code</button>
    </form>
    <div class="text-center mt-2">
      <a href="<?= BASE_URL ?>/forgot-password.php" style="font-size:.82rem;color:#9ca3af;text-decoration:none">← Start over</a>
    </div>

    <?php elseif ($step === 3): ?>
    <p style="font-size:.87rem;color:#6b7280;margin-bottom:18px">Code verified. Enter your new password below.</p>
    <form method="POST" autocomplete="off">
      <input type="hidden" name="form_step" value="3">
      <div class="mb-3">
        <label class="form-label">New Password</label>
        <input type="password" name="password" id="newPass" class="form-control" required minlength="6"
               oninput="checkStrength(this.value)" placeholder="Minimum 6 characters">
        <div id="strengthBar" style="height:4px;border-radius:2px;background:#e5e7eb;margin-top:6px;transition:all .3s"></div>
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

    <div class="text-center mt-3">
      <a href="<?= BASE_URL ?>/index.php" style="font-size:.84rem;color:#6b7280;text-decoration:none">← Back to Login</a>
    </div>
  <?php endif; ?>

  </div>
</div>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script>
var otpInput = document.getElementById('otpInput');
if (otpInput) {
    otpInput.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g,'').slice(0,6);
        if (this.value.length === 6) document.getElementById('otpForm').submit();
    });
}
function checkStrength(val) {
    var bar=document.getElementById('strengthBar'), lbl=document.getElementById('strengthLabel'), score=0;
    if(val.length>=6)score++; if(val.length>=8)score++;
    if(/[A-Z]/.test(val))score++; if(/[0-9]/.test(val))score++; if(/[^A-Za-z0-9]/.test(val))score++;
    var m=[['#e5e7eb',''],['#dc2626','Weak'],['#d97706','Fair'],['#f59e0b','Good'],['#16a34a','Strong'],['#059669','Very Strong']][Math.min(score,5)];
    bar.style.background=m[0]; bar.style.width=(score*20)+'%'; lbl.textContent=m[1]; lbl.style.color=m[0];
}
function checkMatch() {
    var p1=document.getElementById('newPass').value, p2=document.getElementById('confirmPass').value, lbl=document.getElementById('matchLabel');
    if(!p2){lbl.textContent='';return;}
    if(p1===p2){lbl.textContent='✓ Passwords match';lbl.style.color='#059669';}
    else{lbl.textContent='✗ Passwords do not match';lbl.style.color='#dc2626';}
}
</script>
</body>
</html>

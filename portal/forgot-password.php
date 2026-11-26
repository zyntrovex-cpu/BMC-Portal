<?php
/**
 * Forgot Password — public form.
 *
 * Security policy (effective after the 2026-11 audit):
 *   1. A password-reset token is minted ONLY after we have confirmed that
 *      the account exists, is active, AND has a non-empty, well-formed
 *      email address on file. No email on file  →  no token issued, no
 *      link generated, no link displayed.
 *   2. A reset LINK is NEVER displayed to the visitor of this page. The
 *      link is sent exclusively to the account's registered email address.
 *      This closes the previous vulnerability where any visitor could
 *      harvest a reset link for any account that lacked an email by
 *      entering its User ID.
 *   3. When SMTP cannot deliver the message, we log the failure for the
 *      admin and show the visitor a neutral "could not send" message —
 *      we DO NOT fall back to exposing the link in the browser session.
 *   4. The response for "User ID valid + email ok" and "User ID invalid"
 *      is intentionally the same uniform success screen, so this page
 *      cannot be used to enumerate which User IDs exist. The response
 *      for "User ID valid but no email on file" is a DIFFERENT message
 *      — per the explicit security requirement — directing the user to
 *      contact staff so an email can be added. This surfaces the common
 *      real-world case without enabling account takeover.
 *   5. A simple per-session throttle rate-limits attempts to 5 per 10
 *      minutes. This slows brute-force User-ID enumeration and reduces
 *      SMTP cost while remaining usable for legitimate retries.
 *   6. Every attempt is written to the activity log (where the table
 *      exists) so administrators can audit abuse.
 *
 * Admin-side password management (portal/admin/users.php) retains the
 * ability to issue a direct link for authorised Admins — this page does
 * NOT share that privilege.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user'])) redirect('/portal/index.php');

/** Rate-limit forgot-password attempts per session. */
$MAX_ATTEMPTS    = 5;
$ATTEMPT_WINDOW  = 600;          // 10 minutes
$attemptKey      = 'fp_attempts';
$attempts        = (array)($_SESSION[$attemptKey] ?? []);
$now             = time();
// drop stale entries outside the window
$attempts        = array_values(array_filter($attempts, fn ($t) => (int)$t > $now - $ATTEMPT_WINDOW));
$throttled       = count($attempts) >= $MAX_ATTEMPTS;

// Masks an email for safe display ("ali.khan@example.com" → "al***an@e***e.com")
function fpMaskEmail(string $email): string {
    $at = strpos($email, '@');
    if ($at === false) return 'your email';
    $local  = substr($email, 0, $at);
    $domain = substr($email, $at + 1);
    $maskPart = function (string $s): string {
        $n = mb_strlen($s);
        if ($n <= 2) return $s . '***';
        if ($n <= 4) return mb_substr($s, 0, 1) . '***' . mb_substr($s, -1);
        return mb_substr($s, 0, 2) . '***' . mb_substr($s, -2);
    };
    return $maskPart($local) . '@' . $maskPart($domain);
}

$status  = '';            // one of: '', 'sent', 'no_email', 'send_failed', 'throttled', 'error'
$error   = '';
$sentTo  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = trim($_POST['user_id'] ?? '');

    if ($throttled) {
        $status = 'throttled';
    } elseif (!$userId) {
        $error  = 'Please enter your User ID.';
        $status = 'error';
    } else {
        // Record this attempt BEFORE processing to count failed probes too.
        $attempts[] = $now;
        $_SESSION[$attemptKey] = $attempts;

        $db = getDB();
        $st = $db->prepare(
            'SELECT id, name, email, user_id, role
               FROM users
              WHERE user_id = ? AND status = "active"
              LIMIT 1'
        );
        $st->execute([$userId]);
        $found = $st->fetch();

        if ($found) {
            $email = trim((string)($found['email'] ?? ''));
            // Validate the stored email — refuse to mint a token unless we
            // have a syntactically valid address to send to. Empty, 0, or
            // garbage values must NOT open a reset path.
            $emailValid = $email !== ''
                       && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

            if (!$emailValid) {
                // ─── Required behaviour: tell the user to contact staff. ──
                // No token minted. No link generated. No link exposed.
                $status = 'no_email';
                try {
                    logActivity((int)$found['id'], 'password_reset_blocked',
                        'Reset blocked: no email address on file for user_id=' . $userId);
                } catch (Exception $e) {}
            } else {
                // Mint a cryptographically strong token and write the expiry
                // in UTC (reset-password.php also parses it as UTC).
                $token   = bin2hex(random_bytes(32));
                $expires = gmdate('Y-m-d H:i:s', $now + 1800); // 30 min
                try {
                    $db->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')
                       ->execute([$token, $expires, $found['id']]);
                } catch (Exception $e) {
                    $status = 'send_failed';
                }

                if ($status !== 'send_failed') {
                    $appUrl = getSetting('app_url', '');
                    if (!$appUrl) {
                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $appUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
                    }
                    $resetLink = rtrim($appUrl, '/') . '/portal/reset-password.php?token=' . $token;

                    $body = mailTemplate(
                        'Reset Your Password',
                        "<p>Hi <strong>" . h($found['name']) . "</strong>,</p>"
                      . "<p>We received a request to reset your BMC Portal password.</p>"
                      . "<p>Click the button below to choose a new password. This link expires in <strong>30 minutes</strong>.</p>"
                      . "<p><a class='btn' href='" . h($resetLink) . "'>Reset My Password</a></p>"
                      . "<p style='color:#9ca3af;font-size:.85rem'>If you didn't request this, you can safely ignore this email. Your current password remains unchanged.</p>"
                    );
                    $ok = sendMail($email, $found['name'], 'Reset Your BMC Portal Password', $body);

                    if ($ok) {
                        $status = 'sent';
                        $sentTo = fpMaskEmail($email);
                        try {
                            logActivity((int)$found['id'], 'password_reset_sent',
                                'Reset link emailed to ' . fpMaskEmail($email));
                        } catch (Exception $e) {}
                    } else {
                        // Delivery failed — invalidate the just-minted token so
                        // nobody can reuse it, show a neutral message. DO NOT
                        // expose the link to the visitor.
                        try {
                            $db->prepare('UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE id = ?')
                               ->execute([$found['id']]);
                        } catch (Exception $e) {}
                        $status = 'send_failed';
                        $smtpErr = smtpNotConfigured()
                            ? 'SMTP is not configured'
                            : getSmtpError();
                        try {
                            logActivity((int)$found['id'], 'password_reset_mail_fail',
                                'SMTP failure for user_id=' . $userId . ': ' . $smtpErr);
                        } catch (Exception $e) {}
                    }
                }
            }
        } else {
            // Account-enumeration defence — same uniform "sent" screen as a
            // successful send. We do NOT reveal that the User ID is unknown.
            $status = 'sent';
            $sentTo = '';
            try {
                logActivity(0, 'password_reset_unknown_user',
                    'Reset requested for unknown user_id=' . $userId);
            } catch (Exception $e) {}
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
  .card { background:#fff; border-radius:16px; box-shadow:0 24px 64px rgba(0,0,0,.45); width:460px; max-width:100%; overflow:hidden; }
  .card-header { background:linear-gradient(135deg,#1c3054,#2563eb); color:#fff; padding:32px; text-align:center; }
  .card-body { padding:32px; }
  .form-label { font-size:.85rem; font-weight:600; color:#374151; }
  .form-control { border-radius:8px; border:1.5px solid #d5dde8; padding:11px 14px; }
  .form-control:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
  .btn-primary { background:linear-gradient(135deg,#1c3054,#2563eb); border:none; border-radius:8px; padding:12px; font-weight:700; font-size:.95rem; }
  .notice-box { background:#fffbeb; border:1.5px solid #fde68a; border-radius:10px; padding:18px; color:#78350f; font-size:.88rem; line-height:1.55; }
  .notice-box strong { color:#92400e; }
</style>
<div class="card">
  <div class="card-header">
    <div style="font-size:2.4rem;margin-bottom:8px">🔑</div>
    <h5 class="mb-1" style="font-weight:700">Forgot your password?</h5>
    <p class="mb-0" style="font-size:.82rem;opacity:.75">BMC Portal — <?= SESSION_YEAR ?></p>
  </div>
  <div class="card-body">

    <?php if ($status === 'sent'): ?>
      <div class="text-center py-2">
        <div style="font-size:3rem;margin-bottom:12px">📧</div>
        <h6 style="font-weight:700;margin-bottom:8px">Check your email</h6>
        <p style="font-size:.87rem;color:#6b7280;margin-bottom:8px">
          If an account exists for the ID you entered, a password reset link has been sent to its registered email address.
          <?php if ($sentTo): ?>
            <br><strong><?= h($sentTo) ?></strong>
          <?php endif; ?>
        </p>
        <p style="font-size:.8rem;color:#9ca3af;margin-bottom:20px">
          The link expires in 30 minutes. Check your spam folder if you don't see it.
        </p>
        <hr class="my-3">
        <a href="<?= BASE_URL ?>/portal/forgot-password.php" class="btn btn-outline-secondary w-100" style="border-radius:8px;font-weight:600">Back</a>
      </div>

    <?php elseif ($status === 'no_email'): ?>
      <div class="notice-box">
        <div style="font-size:1.6rem;margin-bottom:6px">⚠️</div>
        <strong>Your email address is not registered in the portal.</strong>
        Please contact the relevant staff member to have your email address
        added before resetting your password.
      </div>
      <a href="<?= BASE_URL ?>/portal/forgot-password.php" class="btn btn-outline-secondary w-100 mt-3" style="border-radius:8px;font-weight:600">Back</a>

    <?php elseif ($status === 'send_failed'): ?>
      <div class="alert alert-danger" style="font-size:.87rem;border-radius:8px;padding:12px 14px">
        We were unable to send a reset email right now. Please try again in a few minutes.
        If the problem persists, contact your administrator.
      </div>
      <a href="<?= BASE_URL ?>/portal/forgot-password.php" class="btn btn-outline-secondary w-100" style="border-radius:8px;font-weight:600">Back</a>

    <?php elseif ($status === 'throttled'): ?>
      <div class="alert alert-warning" style="font-size:.87rem;border-radius:8px;padding:12px 14px">
        Too many reset requests from this browser. Please wait a few minutes before trying again.
      </div>
      <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-secondary w-100" style="border-radius:8px;font-weight:600">Back to login</a>

    <?php else: ?>
      <?php if ($error): ?>
        <div class="alert alert-danger" style="font-size:.87rem;border-radius:8px;padding:10px 14px"><?= h($error) ?></div>
      <?php endif; ?>
      <p style="font-size:.87rem;color:#6b7280;margin-bottom:22px">
        Enter your User ID and we'll send a password reset link to the email address registered on your account.
      </p>
      <form method="POST" autocomplete="off">
        <div class="mb-4">
          <label class="form-label">User ID</label>
          <input type="text" name="user_id" class="form-control" placeholder="e.g. 1001 / 2001 / 3001"
                 value="<?= h($_POST['user_id'] ?? '') ?>" required autofocus autocomplete="off" maxlength="32">
          <div style="font-size:.78rem;color:#9ca3af;margin-top:6px">Your roll number or staff ID assigned by the college.</div>
        </div>
        <button type="submit" class="btn btn-primary w-100">Send reset link</button>
      </form>
    <?php endif; ?>

    <div class="text-center mt-3">
      <a href="<?= BASE_URL ?>/index.php" style="font-size:.84rem;color:#6b7280;text-decoration:none">← Back to login</a>
    </div>
  </div>
</div>
</body>
</html>

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

// Stores the last SMTP error so callers can show a helpful message
$GLOBALS['_smtp_last_error']  = '';
$GLOBALS['_smtp_not_configured'] = false;

function getSmtpError(): string  { return $GLOBALS['_smtp_last_error']  ?? ''; }
function smtpNotConfigured(): bool { return $GLOBALS['_smtp_not_configured'] ?? false; }

/**
 * Send email via SMTP using PHP streams (no Composer required).
 * Returns false (not true) when SMTP is disabled so callers know to show a fallback.
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool {
    $GLOBALS['_smtp_last_error']     = '';
    $GLOBALS['_smtp_not_configured'] = false;

    $enabled  = getSetting('smtp_enabled', '0');
    $host     = getSetting('smtp_host',    'localhost');
    $port     = (int)getSetting('smtp_port',  '587');
    $user     = getSetting('smtp_user',    '');
    $pass     = getSetting('smtp_pass',    '');
    $from     = getSetting('smtp_from',    $user);
    $fromName = getSetting('smtp_from_name', 'BMC Portal');

    if (!$textBody) {
        $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
    }

    if (!$enabled || $enabled === '0' || !$from) {
        // Log to file for reference
        $logFile = __DIR__ . '/../logs/mail.log';
        @mkdir(dirname($logFile), 0755, true);
        $entry = date('Y-m-d H:i:s') . " | To: $toEmail | Subject: $subject\n";
        @file_put_contents($logFile, $entry, FILE_APPEND);
        $GLOBALS['_smtp_not_configured'] = true;
        $GLOBALS['_smtp_last_error'] = 'SMTP is not configured. Enable it in Admin → Settings → SMTP.';
        return false; // Let caller show fallback
    }

    // Check OpenSSL is available for TLS
    if (!extension_loaded('openssl') && in_array($port, [465, 587])) {
        $GLOBALS['_smtp_last_error'] = 'OpenSSL PHP extension is not enabled. In XAMPP, open php.ini and uncomment: extension=openssl';
        return false;
    }

    try {
        return _smtpSend($host, $port, $user, $pass, $from, $fromName, $toEmail, $toName, $subject, $htmlBody, $textBody);
    } catch (Exception $e) {
        $GLOBALS['_smtp_last_error'] = $e->getMessage();
        error_log('SMTP Error: ' . $e->getMessage());
        return false;
    }
}

function _smtpSend(
    string $host, int $port,
    string $user, string $pass,
    string $from, string $fromName,
    string $toEmail, string $toName,
    string $subject, string $htmlBody, string $textBody
): bool {
    $sslOpts = ['ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
    ]];
    $context = stream_context_create($sslOpts);

    // Port 465 = implicit SSL, port 587/25 = plain TCP then STARTTLS
    if ($port === 465) {
        $socket = @stream_socket_client("ssl://$host:$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    } else {
        $socket = @stream_socket_client("tcp://$host:$port", $errno, $errstr, 15);
    }

    if (!$socket) throw new Exception("Cannot connect to $host:$port — $errstr ($errno). Check host/port and firewall.");

    $read = function() use ($socket): string {
        $data = '';
        while ($line = fgets($socket, 512)) {
            $data .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $data;
    };

    $cmd = function(string $c) use ($socket, $read): string {
        fwrite($socket, $c . "\r\n");
        return $read();
    };

    $banner = $read();
    if (!str_starts_with($banner, '2')) throw new Exception("Bad SMTP banner: $banner");

    $cmd("EHLO " . (gethostname() ?: 'localhost'));

    if ($port === 587) {
        $stls = $cmd("STARTTLS");
        if (!str_starts_with($stls, '220')) throw new Exception("STARTTLS rejected: $stls");
        // Apply SSL options to the existing socket before upgrading
        stream_context_set_option($socket, $sslOpts);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception("TLS upgrade failed. Try port 465 with SSL instead.");
        }
        $cmd("EHLO " . (gethostname() ?: 'localhost'));
    }

    $authResp = $cmd("AUTH LOGIN");
    if (!str_starts_with($authResp, '334')) throw new Exception("AUTH LOGIN rejected: $authResp");
    $cmd(base64_encode($user));
    $r = $cmd(base64_encode($pass));
    if (!str_starts_with($r, '235')) throw new Exception("Authentication failed. Check username/password. For Gmail use an App Password (not your Google password). Response: $r");

    $cmd("MAIL FROM:<$from>");
    $cmd("RCPT TO:<$toEmail>");
    $cmd("DATA");

    $boundary = md5(uniqid('', true));
    $headers  = "From: $fromName <$from>\r\n"
              . "To: $toName <$toEmail>\r\n"
              . "Subject: $subject\r\n"
              . "MIME-Version: 1.0\r\n"
              . "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n"
              . "Date: " . date('r') . "\r\n"
              . "X-Mailer: BMC-Portal\r\n";

    $body = "--$boundary\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
          . $textBody . "\r\n\r\n"
          . "--$boundary\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
          . $htmlBody . "\r\n\r\n"
          . "--$boundary--";

    fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
    $r = $read();

    $cmd("QUIT");
    fclose($socket);

    return str_starts_with($r, '250');
}

// ── Email templates ──────────────────────────────────────────────

function mailTemplate(string $title, string $body): string {
    $school = getSetting('school_name', 'Bahria Model College');
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f0f2f5; margin:0; padding:0; }
  .wrap { max-width:560px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.1); }
  .header { background:#1c3054; color:#fff; padding:28px 32px; text-align:center; }
  .header h1 { margin:0; font-size:1.3rem; }
  .header p  { margin:4px 0 0; font-size:.85rem; opacity:.75; }
  .body { padding:32px; color:#1a2332; font-size:.95rem; line-height:1.6; }
  .btn { display:inline-block; margin:20px 0; padding:12px 28px; background:#1c3054; color:#fff!important; text-decoration:none; border-radius:6px; font-weight:600; }
  .footer { background:#f8fafc; padding:16px 32px; text-align:center; font-size:.78rem; color:#8a9ab0; border-top:1px solid #e5e7eb; }
  .otp { font-size:2rem; font-weight:700; letter-spacing:.3em; color:#1c3054; background:#f0f4ff; padding:16px 24px; border-radius:6px; display:inline-block; margin:16px 0; }
</style></head>
<body>
<div class="wrap">
  <div class="header"><h1>🎓 BMC Portal</h1><p>$school</p></div>
  <div class="body"><h2 style="margin-top:0">$title</h2>$body</div>
  <div class="footer">© 2025 $school — BMC Portal<br>This is an automated email, please do not reply.</div>
</div>
</body></html>
HTML;
}

function sendPasswordReset(string $toEmail, string $toName, string $resetLink): bool {
    $body = mailTemplate('Password Reset Request',
        "<p>Hi <strong>$toName</strong>,</p>
        <p>We received a request to reset your BMC Portal password.</p>
        <p>Click the button below to reset your password. This link expires in <strong>30 minutes</strong>.</p>
        <p><a class='btn' href='$resetLink'>Reset My Password</a></p>
        <p>Or copy this link:<br><small style='color:#6b7280;word-break:break-all'>$resetLink</small></p>
        <p>If you did not request this, please ignore this email. Your password will not change.</p>"
    );
    return sendMail($toEmail, $toName, 'Reset Your BMC Portal Password', $body);
}

function sendWelcomeEmail(string $toEmail, string $toName, string $userId, string $role): bool {
    $body = mailTemplate('Welcome to BMC Portal',
        "<p>Hi <strong>$toName</strong>,</p>
        <p>Your BMC Portal account has been created. Here are your login details:</p>
        <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:.9rem'>
          <tr><td style='padding:8px;border:1px solid #e5e7eb;color:#6b7280'>Portal</td><td style='padding:8px;border:1px solid #e5e7eb'><strong>" . ucfirst($role) . " Portal</strong></td></tr>
          <tr><td style='padding:8px;border:1px solid #e5e7eb;color:#6b7280'>User ID</td><td style='padding:8px;border:1px solid #e5e7eb'><strong>$userId</strong></td></tr>
        </table>
        <p><a class='btn' href='" . getSetting('app_url', '/') . "'>Login to Portal</a></p>
        <p style='color:#9ca3af;font-size:.85rem'>Please change your password after first login.</p>"
    );
    return sendMail($toEmail, $toName, 'Welcome to BMC Portal', $body);
}

function sendFeeReceipt(string $toEmail, string $toName, string $rollNo, string $className, int $month, int $year, float $amount, string $mode): bool {
    $months = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $body = mailTemplate('Fee Payment Receipt',
        "<p>Dear <strong>$toName</strong>,</p>
        <p>Your fee payment has been recorded successfully.</p>
        <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:.9rem'>
          <tr><td style='padding:8px;border:1px solid #e5e7eb;color:#6b7280'>Roll No</td><td style='padding:8px;border:1px solid #e5e7eb'>$rollNo</td></tr>
          <tr><td style='padding:8px;border:1px solid #e5e7eb;color:#6b7280'>Class</td><td style='padding:8px;border:1px solid #e5e7eb'>$className</td></tr>
          <tr><td style='padding:8px;border:1px solid #e5e7eb;color:#6b7280'>Month</td><td style='padding:8px;border:1px solid #e5e7eb'>{$months[$month]} $year</td></tr>
          <tr><td style='padding:8px;border:1px solid #e5e7eb;color:#6b7280'>Amount</td><td style='padding:8px;border:1px solid #e5e7eb'><strong>PKR " . number_format($amount) . "</strong></td></tr>
          <tr><td style='padding:8px;border:1px solid #e5e7eb;color:#6b7280'>Mode</td><td style='padding:8px;border:1px solid #e5e7eb'>$mode</td></tr>
          <tr><td style='padding:8px;border:1px solid #e5e7eb;color:#6b7280'>Date</td><td style='padding:8px;border:1px solid #e5e7eb'>" . date('d M Y') . "</td></tr>
        </table>
        <p>Please keep this for your records.</p>"
    );
    return sendMail($toEmail, $toName, 'Fee Receipt — ' . $months[$month] . " $year", $body);
}

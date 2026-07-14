<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in → redirect
if (!empty($_SESSION['user'])) {
    $role = $_SESSION['user']['role'];
    $map  = [
        'student'        => '/student/dashboard.php',
        'teacher'        => '/teacher/dashboard.php',
        'admin'          => '/admin/dashboard.php',
        'finance'        => '/finance/dashboard.php',
        'ilc_vp'         => '/ilc/dashboard.php',
        'student_affairs'=> '/student-affairs/dashboard.php',
        'vp_main'        => '/vp/dashboard.php',
        'wing_head'      => '/wing-head/dashboard.php',
    ];
    redirect($map[$role] ?? '/index.php');
}

$error = '';
$msg   = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId   = trim($_POST['user_id']  ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$userId || !$password) {
        $error = 'User ID and password are required.';
    } else {
        $db = getDB();
        $st = $db->prepare('SELECT * FROM users WHERE user_id = ? AND status = "active"');
        $st->execute([$userId]);
        $user = $st->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

            $_SESSION['user'] = [
                'id'      => $user['id'],
                'user_id' => $user['user_id'],
                'name'    => $user['name'],
                'role'    => $user['role'],
                'email'   => $user['email'] ?? '',
            ];

            try {
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $db->prepare('INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?,?,?,?)')
                   ->execute([$user['id'], 'login', 'Logged in', $ip]);
            } catch (Exception $e) {}

            $map = [
                'student'        => '/student/dashboard.php',
                'teacher'        => '/teacher/dashboard.php',
                'admin'          => '/admin/dashboard.php',
                'finance'        => '/finance/dashboard.php',
                'ilc_vp'         => '/ilc/dashboard.php',
                'student_affairs'=> '/student-affairs/dashboard.php',
                'vp_main'        => '/vp/dashboard.php',
                'wing_head'      => '/wing-head/dashboard.php',
            ];
            redirect($map[$user['role']] ?? '/index.php');
        } else {
            $error = 'Invalid User ID or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — BMC Portal</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    min-height: 100vh;
    background: linear-gradient(135deg, #0f1f3d 0%, #1c3054 50%, #0d2847 100%);
    display: flex; align-items: center; justify-content: center;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  }
  .login-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    width: 420px;
    max-width: 96vw;
    overflow: hidden;
  }
  .login-header {
    background: linear-gradient(135deg, #1c3054, #2563eb);
    color: #fff;
    padding: 32px 32px 28px;
    text-align: center;
  }
  .login-header h1 { font-size: 1.4rem; font-weight: 700; margin: 0; }
  .login-header p  { font-size: 0.85rem; opacity: .8; margin: 4px 0 0; }
  .login-body { padding: 32px; }
  .form-label { font-size: .82rem; font-weight: 600; color: #374151; }
  .form-control { border-radius: 6px; border: 1.5px solid #d5dde8; font-size: .9rem; }
  .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
  .btn-login {
    width: 100%; padding: 11px;
    background: linear-gradient(135deg, #1c3054, #2563eb);
    border: none; border-radius: 8px; color: #fff; font-weight: 700;
    font-size: .95rem; cursor: pointer; transition: opacity .15s;
  }
  .btn-login:hover { opacity: .9; }
  .alert-msg { font-size: .84rem; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; }
  .alert-success-msg { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
  .alert-error-msg   { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
  .hint-box {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
    padding: 12px 14px; margin-bottom: 20px; font-size: .8rem; color: #64748b;
    line-height: 1.6;
  }
  .hint-box strong { color: #374151; }
  .footer-note { text-align: center; margin-top: 18px; font-size: .75rem; color: #8a9ab0; }
</style>
</head>
<body>
<div class="login-card">
  <div class="login-header">
    <div style="font-size:3rem; margin-bottom:8px;">🎓</div>
    <h1>BMC Portal</h1>
    <p>Bahria Model College &mdash; <?= SESSION_YEAR ?></p>
  </div>
  <div class="login-body">
    <?php if ($msg === 'logout'): ?>
      <div class="alert-msg alert-success-msg">You have been logged out successfully.</div>
    <?php elseif ($msg === 'login'): ?>
      <div class="alert-msg alert-error-msg">Please log in to continue.</div>
    <?php elseif ($msg === 'unauthorized'): ?>
      <div class="alert-msg alert-error-msg">Access denied. Insufficient permissions.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert-msg alert-error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="hint-box">
      <strong>All portals share this login.</strong><br>
      Use your assigned ID: Roll No (students), Employee ID (teachers), or portal ID (staff).
    </div>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label" for="userId">User ID</label>
        <input type="text" class="form-control" name="user_id" id="userId"
               placeholder="e.g. STU001, T001, ADM001"
               value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>" required autofocus>
      </div>
      <div class="mb-4">
        <label class="form-label" for="password">Password</label>
        <input type="password" class="form-control" name="password" id="password"
               placeholder="Enter password" required>
      </div>
      <button type="submit" class="btn-login">
        <i class="fas fa-sign-in-alt me-2"></i>Sign In
      </button>
    </form>

    <div class="text-center mt-3">
      <a href="forgot-password.php" style="font-size:.82rem;color:#6b7280;text-decoration:none">Forgot password?</a>
    </div>

    <div class="footer-note">
      BMC Portal &copy; 2025 &mdash; Bahria Model College<br>
      <small>For support contact admin</small>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in → redirect
if (!empty($_SESSION['user'])) {
    $role = $_SESSION['user']['role'];
    $map  = ['student'=>'student/dashboard.php','teacher'=>'teacher/dashboard.php','admin'=>'admin/dashboard.php','finance'=>'finance/dashboard.php'];
    header('Location: /' . ($map[$role] ?? 'index.php'));
    exit;
}

$error = '';
$msg   = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId   = trim($_POST['user_id']   ?? '');
    $password = trim($_POST['password']  ?? '');
    $role     = trim($_POST['role']      ?? '');

    if (!$userId || !$password || !$role) {
        $error = 'All fields are required.';
    } else {
        $db = getDB();
        $st = $db->prepare('SELECT * FROM users WHERE user_id = ? AND role = ? AND status = "active"');
        $st->execute([$userId, $role]);
        $user = $st->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Update last login
            $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

            // Store session
            $_SESSION['user'] = [
                'id'      => $user['id'],
                'user_id' => $user['user_id'],
                'name'    => $user['name'],
                'role'    => $user['role'],
                'email'   => $user['email'] ?? '',
            ];

            // Log activity
            try {
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $db->prepare('INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?,?,?,?)')
                   ->execute([$user['id'], 'login', 'Logged in', $ip]);
            } catch (Exception $e) {}

            $map = ['student'=>'student/dashboard.php','teacher'=>'teacher/dashboard.php','admin'=>'admin/dashboard.php','finance'=>'finance/dashboard.php'];
            header('Location: /' . $map[$role]);
            exit;
        } else {
            $error = 'Invalid credentials. Please check your ID and password.';
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
  .login-header img { width: 56px; height: 56px; border-radius: 50%; background: rgba(255,255,255,.15); padding: 8px; margin-bottom: 12px; }
  .login-header h1 { font-size: 1.4rem; font-weight: 700; margin: 0; }
  .login-header p  { font-size: 0.85rem; opacity: .8; margin: 4px 0 0; }
  .login-body { padding: 32px; }
  .role-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 20px; }
  .role-btn {
    border: 2px solid #d5dde8;
    border-radius: 8px;
    background: #f8fafc;
    padding: 10px 8px;
    cursor: pointer;
    text-align: center;
    transition: all .15s;
    display: flex; flex-direction: column; align-items: center; gap: 4px;
  }
  .role-btn span.icon { font-size: 1.4rem; }
  .role-btn span.lbl { font-size: 0.75rem; font-weight: 600; color: #4a5c70; }
  .role-btn span.idlbl { font-size: 0.68rem; color: #8a9ab0; }
  .role-btn.active[data-role="student"]  { border-color: #1d4ed8; background: #eff6ff; }
  .role-btn.active[data-role="teacher"]  { border-color: #059669; background: #f0fdf4; }
  .role-btn.active[data-role="admin"]    { border-color: #7c3aed; background: #f5f3ff; }
  .role-btn.active[data-role="finance"]  { border-color: #d97706; background: #fffbeb; }
  .role-btn:hover { border-color: #94a3b8; }
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

    <form method="POST" id="loginForm">
      <div style="margin-bottom:16px;">
        <div class="form-label mb-2">Select Portal</div>
        <div class="role-grid">
          <button type="button" class="role-btn" data-role="student">
            <span class="icon">👨‍🎓</span>
            <span class="lbl">Student</span>
            <span class="idlbl">Roll Number</span>
          </button>
          <button type="button" class="role-btn" data-role="teacher">
            <span class="icon">👨‍🏫</span>
            <span class="lbl">Teacher</span>
            <span class="idlbl">Employee ID</span>
          </button>
          <button type="button" class="role-btn" data-role="admin">
            <span class="icon">🛡️</span>
            <span class="lbl">Admin</span>
            <span class="idlbl">Admin ID</span>
          </button>
          <button type="button" class="role-btn" data-role="finance">
            <span class="icon">💰</span>
            <span class="lbl">Finance</span>
            <span class="idlbl">Finance ID</span>
          </button>
        </div>
        <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($_POST['role'] ?? '') ?>">
      </div>

      <div class="mb-3">
        <label class="form-label" for="userId" id="idLabel">ID</label>
        <input type="text" class="form-control" name="user_id" id="userId"
               placeholder="Enter your ID"
               value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>" required>
      </div>
      <div class="mb-4">
        <label class="form-label" for="password">Password</label>
        <input type="password" class="form-control" name="password" id="password"
               placeholder="Enter password" required>
      </div>
      <button type="submit" class="btn-login">Sign In</button>
    </form>

    <div class="footer-note">
      BMC Portal &copy; 2025 &mdash; Bahria Model College<br>
      <small>For support contact admin</small>
    </div>
  </div>
</div>

<script>
const idLabels = { student:'Roll Number', teacher:'Employee ID', admin:'Admin ID', finance:'Finance ID' };
const demos    = { student:'101', teacher:'T001', admin:'ADM001', finance:'FIN001' };
let selected   = '<?= htmlspecialchars($_POST['role'] ?? '') ?>';

function selectRole(role) {
  selected = role;
  document.getElementById('roleInput').value = role;
  document.getElementById('idLabel').textContent = idLabels[role];
  document.getElementById('userId').placeholder = 'Enter ' + idLabels[role];
  document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
  document.querySelector('[data-role="'+role+'"]').classList.add('active');
}

document.querySelectorAll('.role-btn').forEach(btn => {
  btn.addEventListener('click', () => selectRole(btn.dataset.role));
});

// Restore on page reload
if (selected) selectRole(selected);
</script>
</body>
</html>

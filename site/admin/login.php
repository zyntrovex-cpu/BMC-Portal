<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Already logged in — go to dashboard
if (isSiteAdmin()) {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        try {
            $db  = siteDB();
            $st  = $db->prepare('SELECT * FROM site_admins WHERE email = ? AND is_active = 1 LIMIT 1');
            $st->execute([$email]);
            $row = $st->fetch();

            if ($row && password_verify($password, $row['password'])) {
                session_regenerate_id(true);
                $_SESSION['site_admin'] = [
                    'id'    => (int)$row['id'],
                    'name'  => $row['name'],
                    'email' => $row['email'],
                    'role'  => $row['role'],
                ];
                // Record last login time
                $db->prepare('UPDATE site_admins SET last_login = NOW() WHERE id = ?')
                   ->execute([$row['id']]);
                header('Location: ' . SITE_URL . '/admin/index.php');
                exit;
            } else {
                $error = 'Invalid email or password. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'A database error occurred. Please try again later.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login — BMC</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      min-height: 100vh;
      margin: 0;
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      background: linear-gradient(135deg, #091a4a 0%, #0c2461 35%, #1a3a8f 65%, #0984e3 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
      position: relative;
      overflow-x: hidden;
    }
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      pointer-events: none;
      background:
        radial-gradient(ellipse 60% 50% at 20% 30%, rgba(9,132,227,.18) 0%, transparent 70%),
        radial-gradient(ellipse 50% 60% at 80% 70%, rgba(249,202,36,.08) 0%, transparent 70%);
    }
    body::after {
      content: '';
      position: fixed;
      inset: 0;
      pointer-events: none;
      background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23fff' fill-opacity='0.025'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .login-wrap {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 460px;
    }
    .login-card {
      background: #ffffff;
      border-radius: 24px;
      padding: 52px 48px 44px;
      box-shadow: 0 40px 100px rgba(0,0,0,.4), 0 0 0 1px rgba(255,255,255,.05);
    }
    .brand-icon {
      width: 84px; height: 84px;
      background: #fff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      box-shadow: 0 8px 28px rgba(9,132,227,.35), 0 0 0 4px rgba(255,255,255,.2);
      padding: 8px;
      overflow: hidden;
    }
    .brand-icon img { width: 100%; height: 100%; object-fit: contain; }
    .login-heading {
      text-align: center;
      font-size: 1.55rem;
      font-weight: 800;
      color: #0c2461;
      margin-bottom: 6px;
      letter-spacing: -.01em;
    }
    .login-sub {
      text-align: center;
      font-size: .84rem;
      color: #64748b;
      margin-bottom: 36px;
    }
    .form-group { margin-bottom: 22px; }
    .form-label-custom {
      display: block;
      font-size: .8rem;
      font-weight: 700;
      color: #475569;
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .input-group-custom { position: relative; }
    .input-icon {
      position: absolute;
      left: 15px; top: 50%; transform: translateY(-50%);
      color: #94a3b8;
      font-size: .9rem;
      pointer-events: none;
      z-index: 2;
    }
    .form-input {
      width: 100%;
      padding: 13px 14px 13px 44px;
      border: 1.5px solid #e2e8f0;
      border-radius: 14px;
      font-size: .93rem;
      color: #1e293b;
      background: #f8fafc;
      outline: none;
      transition: border-color .2s, box-shadow .2s, background .2s;
      appearance: none;
    }
    .form-input:focus {
      border-color: #0984e3;
      box-shadow: 0 0 0 3.5px rgba(9,132,227,.14);
      background: #fff;
    }
    .form-input::placeholder { color: #b0bec5; }
    .btn-sign-in {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      padding: 14px 20px;
      background: linear-gradient(135deg, #0c2461 0%, #0984e3 100%);
      color: #fff;
      border: none;
      border-radius: 14px;
      font-size: .97rem;
      font-weight: 700;
      cursor: pointer;
      transition: transform .18s, box-shadow .18s, opacity .18s;
      margin-top: 6px;
      letter-spacing: .01em;
    }
    .btn-sign-in:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 28px rgba(9,132,227,.38);
      opacity: .95;
    }
    .btn-sign-in:active { transform: translateY(0); }
    .error-alert {
      display: flex;
      align-items: center;
      gap: 10px;
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
      border-radius: 12px;
      padding: 12px 16px;
      font-size: .86rem;
      margin-bottom: 24px;
    }
    .hint-card {
      margin-top: 28px;
      padding: 16px 18px;
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: 14px;
      font-size: .8rem;
      color: #1e40af;
      line-height: 1.6;
    }
    .hint-card .hint-title {
      font-weight: 700;
      font-size: .78rem;
      text-transform: uppercase;
      letter-spacing: .06em;
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .back-link {
      display: block;
      text-align: center;
      margin-top: 22px;
      font-size: .83rem;
      color: rgba(255,255,255,.7);
      text-decoration: none;
      transition: color .15s;
    }
    .back-link:hover { color: #f9ca24; }
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="brand-icon">
      <img src="<?= BASE_URL ?>/assets/bmc-logo.png" alt="BMC Logo">
    </div>
    <h1 class="login-heading">BMC Admin Panel</h1>
    <p class="login-sub">Bahria Model College — Content Management</p>

    <?php if ($error): ?>
    <div class="error-alert">
      <i class="fas fa-exclamation-triangle fa-fw"></i>
      <?= sh($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off" novalidate>
      <div class="form-group">
        <label class="form-label-custom">Email Address</label>
        <div class="input-group-custom">
          <i class="fas fa-envelope input-icon"></i>
          <input type="email" name="email" class="form-input"
                 placeholder="admin@bmc.edu.pk"
                 value="<?= sh($_POST['email'] ?? '') ?>"
                 required autofocus>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label-custom">Password</label>
        <div class="input-group-custom">
          <i class="fas fa-lock input-icon"></i>
          <input type="password" name="password" class="form-input"
                 placeholder="Enter your password" required>
        </div>
      </div>
      <button type="submit" class="btn-sign-in">
        <i class="fas fa-sign-in-alt"></i> Sign In to Admin Panel
      </button>
    </form>

  </div>

  <a href="<?= SITE_URL ?>/index.php" class="back-link">
    <i class="fas fa-arrow-left me-1"></i> Back to Website
  </a>
</div>
</body>
</html>

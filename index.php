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
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>Login — BMC Portal</title>
<link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/bmc-logo.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
  html, body {
    height: 100%; width: 100%; overflow: hidden;
    background: linear-gradient(135deg, #0f1f3d 0%, #1c3054 50%, #0d2847 100%);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  }
  body { display: flex; align-items: center; justify-content: center; }

  .login-card {
    width: 400px;
    max-width: 96vw;
    max-height: 98vh;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 20px 60px rgba(0,0,0,.5), 0 0 0 1px rgba(255,255,255,.08);
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }

  /* ── Header ── */
  .login-header {
    padding: 18px 22px 14px;
    text-align: center;
    position: relative;
    overflow: hidden;
    flex-shrink: 0;
    background: var(--hdr-bg, linear-gradient(160deg,#0f1f3d 0%,#1c3054 60%,#1e3a8a 100%));
    transition: background .35s;
  }
  .login-header::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 50% -10%, rgba(255,255,255,.15) 0%, transparent 65%);
    pointer-events: none;
  }
  .logo-ring {
    width: 72px; height: 72px; border-radius: 50%;
    background: rgba(255,255,255,.96);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 10px;
    box-shadow: 0 4px 18px rgba(0,0,0,.3), 0 0 0 3px rgba(255,255,255,.18);
    padding: 6px;
    position: relative; z-index: 1;
    transition: box-shadow .3s;
  }
  .logo-ring img { width: 100%; height: 100%; object-fit: contain; }
  .login-header h1 {
    font-size: 1.15rem; font-weight: 800; color: #fff;
    letter-spacing: .3px; position: relative; z-index: 1; margin-bottom: 2px;
  }
  .login-header .sub {
    font-size: .72rem; color: rgba(255,255,255,.72);
    position: relative; z-index: 1; letter-spacing: .2px;
  }
  .wing-badge {
    display: inline-block; margin-top: 6px;
    font-size: .65rem; font-weight: 700; letter-spacing: .8px; text-transform: uppercase;
    background: rgba(255,255,255,.18); color: rgba(255,255,255,.9);
    border: 1px solid rgba(255,255,255,.3); border-radius: 20px;
    padding: 2px 10px; position: relative; z-index: 1;
    transition: opacity .3s;
  }

  /* ── Body ── */
  .login-body {
    padding: 18px 22px 16px;
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0;
  }

  .alert-msg {
    font-size: .8rem; border-radius: 6px; padding: 8px 12px;
    margin-bottom: 12px; display: flex; align-items: center; gap: 7px;
  }
  .alert-success-msg { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
  .alert-error-msg   { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }

  /* Selectors row */
  .selector-row {
    display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
    margin-bottom: 12px;
  }
  .sel-group label {
    display: block; font-size: .72rem; font-weight: 700;
    color: #6b7280; text-transform: uppercase; letter-spacing: .5px;
    margin-bottom: 4px;
  }
  .sel-group select {
    width: 100%; padding: 7px 10px; font-size: .82rem;
    border: 1.5px solid #d5dde8; border-radius: 7px;
    background: #f8fafc; color: #1e293b;
    outline: none; cursor: pointer;
    transition: border-color .2s, box-shadow .2s;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236b7280' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    padding-right: 28px;
  }
  .sel-group select:focus { border-color: var(--accent,#2563eb); box-shadow: 0 0 0 3px var(--accent-glow,rgba(37,99,235,.12)); }

  /* Divider */
  .form-divider {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 12px; font-size: .72rem; color: #9ca3af;
  }
  .form-divider::before, .form-divider::after {
    content: ''; flex: 1; height: 1px; background: #e5e7eb;
  }

  /* Inputs */
  .field-group { margin-bottom: 10px; }
  .field-group label {
    display: block; font-size: .76rem; font-weight: 600;
    color: #374151; margin-bottom: 4px;
  }
  .field-group input {
    width: 100%; padding: 9px 12px; font-size: .88rem;
    border: 1.5px solid #d5dde8; border-radius: 7px;
    outline: none; transition: border-color .2s, box-shadow .2s;
    color: #1e293b;
  }
  .field-group input:focus { border-color: var(--accent,#2563eb); box-shadow: 0 0 0 3px var(--accent-glow,rgba(37,99,235,.12)); }

  /* Button */
  .btn-login {
    width: 100%; padding: 10px;
    background: var(--btn-bg, linear-gradient(135deg,#1c3054,#2563eb));
    border: none; border-radius: 8px; color: #fff;
    font-weight: 700; font-size: .9rem;
    cursor: pointer; transition: opacity .15s, transform .1s;
    margin-top: 14px;
    display: flex; align-items: center; justify-content: center; gap: 8px;
  }
  .btn-login:hover { opacity: .88; }
  .btn-login:active { transform: scale(.98); }

  /* Footer */
  .login-footer {
    display: flex; justify-content: space-between; align-items: center;
    margin-top: 10px; padding-top: 8px;
    border-top: 1px solid #f1f5f9;
    font-size: .72rem; color: #9ca3af;
  }
  .login-footer a { color: #6b7280; text-decoration: none; }
  .login-footer a:hover { color: var(--accent,#2563eb); }

  /* Credential helper */
  .cred-toggle {
    width: 100%; margin-top: 8px;
    background: none; border: 1px dashed #d1d5db;
    border-radius: 7px; padding: 6px 10px;
    font-size: .73rem; color: #9ca3af; cursor: pointer;
    text-align: center; transition: border-color .2s, color .2s;
  }
  .cred-toggle:hover { border-color: var(--accent,#2563eb); color: var(--accent,#2563eb); }
  .cred-panel {
    display: none; margin-top: 6px;
    background: #f8fafc; border: 1px solid #e5e7eb;
    border-radius: 8px; padding: 10px 12px;
    font-size: .76rem;
  }
  .cred-panel .cred-title {
    font-size: .68rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; color: #9ca3af; margin-bottom: 6px;
  }
  .cred-row {
    display: flex; align-items: center; gap: 8px;
    padding: 5px 8px; border-radius: 5px; cursor: pointer;
    transition: background .15s; margin-bottom: 2px;
  }
  .cred-row:hover { background: #e0f2fe; }
  .cred-badge {
    font-size: .65rem; padding: 1px 6px; border-radius: 10px;
    font-weight: 700; white-space: nowrap;
    background: var(--accent,#2563eb); color: #fff;
  }
  .cred-id { font-weight: 700; color: #1e293b; font-size: .78rem; }
  .cred-name { color: #6b7280; flex: 1; }
  .cred-pass { font-size: .68rem; color: #94a3b8; font-family: monospace; }
  .cred-none { color: #9ca3af; font-size: .78rem; text-align: center; padding: 4px 0; }
</style>
</head>
<body>

<div class="login-card" id="loginCard">
  <!-- ── Header ── -->
  <div class="login-header" id="loginHeader">
    <div class="logo-ring" id="logoRing">
      <img src="<?= BASE_URL ?>/assets/bmc-logo.png" alt="BMC" id="portalLogo">
    </div>
    <h1 id="portalTitle">BMC Portal</h1>
    <div class="sub">Bahria Model College &mdash; <?= SESSION_YEAR ?></div>
    <div class="wing-badge" id="wingBadge">Main Wing</div>
  </div>

  <!-- ── Body ── -->
  <div class="login-body">

    <?php if ($msg === 'logout'): ?>
      <div class="alert-msg alert-success-msg"><i class="fas fa-check-circle"></i>Logged out successfully.</div>
    <?php elseif ($msg === 'login'): ?>
      <div class="alert-msg alert-error-msg"><i class="fas fa-lock"></i>Please log in to continue.</div>
    <?php elseif ($msg === 'unauthorized'): ?>
      <div class="alert-msg alert-error-msg"><i class="fas fa-ban"></i>Access denied. Insufficient permissions.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert-msg alert-error-msg"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Wing + Type selectors -->
    <div class="selector-row">
      <div class="sel-group">
        <label>Wing</label>
        <select id="wingSelect" name="wing" onchange="updateTheme()">
          <option value="main">Main Wing</option>
          <option value="montessori">Montessori</option>
          <option value="ilc">ILC</option>
        </select>
      </div>
      <div class="sel-group">
        <label>I am a</label>
        <select id="typeSelect" name="user_type" onchange="updateTheme()">
          <option value="student">Student</option>
          <option value="staff">Staff / Teacher</option>
        </select>
      </div>
    </div>

    <div class="form-divider">enter credentials</div>

    <form method="POST" id="loginForm">
      <input type="hidden" name="wing"      id="wingHidden"     value="main">
      <input type="hidden" name="user_type" id="userTypeHidden" value="student">

      <div class="field-group">
        <label for="userId"><i class="fas fa-id-card" style="margin-right:5px;opacity:.6"></i>User ID</label>
        <input type="text" name="user_id" id="userId"
               placeholder="e.g. STU001 · T001 · ADM001"
               value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>" required autofocus>
      </div>
      <div class="field-group">
        <label for="password"><i class="fas fa-key" style="margin-right:5px;opacity:.6"></i>Password</label>
        <input type="password" name="password" id="password"
               placeholder="Enter your password" required>
      </div>

      <button type="submit" class="btn-login" id="loginBtn">
        <i class="fas fa-sign-in-alt"></i>
        <span id="btnText">Sign In as Student</span>
      </button>
    </form>

    <!-- Credential helper -->
    <button class="cred-toggle" type="button" onclick="toggleCreds()" id="credToggle">
      <i class="fas fa-key me-1"></i>Show test accounts for this wing
    </button>
    <div class="cred-panel" id="credPanel">
      <div class="cred-title">Click any row to auto-fill &mdash; password is always <strong>student123</strong></div>
      <div id="credList"></div>
    </div>

    <div class="login-footer">
      <a href="forgot-password.php"><i class="fas fa-question-circle me-1"></i>Forgot password?</a>
      <span>&copy; <?= date('Y') ?> BMC</span>
    </div>

  </div>
</div>

<script>
const THEMES = {
  main: {
    hdr:   'linear-gradient(160deg,#0f1f3d 0%,#1c3054 60%,#1e3a8a 100%)',
    btn:   'linear-gradient(135deg,#1c3054,#2563eb)',
    accent:'#2563eb',
    glow:  'rgba(37,99,235,.13)',
    badge: 'Main Wing',
    logo:  '<?= BASE_URL ?>/assets/bmc-logo.png',
    title: 'BMC Portal',
  },
  montessori: {
    hdr:   'linear-gradient(160deg,#052e16 0%,#065f46 60%,#059669 100%)',
    btn:   'linear-gradient(135deg,#065f46,#059669)',
    accent:'#059669',
    glow:  'rgba(5,150,105,.13)',
    badge: 'Montessori',
    logo:  '<?= BASE_URL ?>/assets/bmc-logo.png',
    title: 'BMC Portal',
  },
  ilc: {
    hdr:   'linear-gradient(160deg,#0c4a6e 0%,#0369a1 60%,#0891b2 100%)',
    btn:   'linear-gradient(135deg,#0369a1,#0891b2)',
    accent:'#0891b2',
    glow:  'rgba(8,145,178,.13)',
    badge: 'ILC',
    logo:  '<?= BASE_URL ?>/assets/ilc-logo.png',
    title: 'ILC Portal',
  },
};

const TYPE_LABELS = {
  student: 'Student',
  staff:   'Staff / Teacher',
};

function updateTheme() {
  const wing = document.getElementById('wingSelect').value;
  const type = document.getElementById('typeSelect').value;
  const t    = THEMES[wing] || THEMES.main;

  // Header gradient
  document.getElementById('loginHeader').style.background = t.hdr;
  // Button gradient
  document.getElementById('loginBtn').style.background = t.btn;
  // CSS vars for focus rings
  document.documentElement.style.setProperty('--accent',      t.accent);
  document.documentElement.style.setProperty('--accent-glow', t.glow);
  document.documentElement.style.setProperty('--btn-bg',      t.btn);
  // Logo
  document.getElementById('portalLogo').src = t.logo;
  // Title & badge
  document.getElementById('portalTitle').textContent = t.title;
  document.getElementById('wingBadge').textContent   = t.badge;
  // Button label
  document.getElementById('btnText').textContent = 'Sign In as ' + TYPE_LABELS[type];
  // Hidden fields
  document.getElementById('wingHidden').value     = wing;
  document.getElementById('userTypeHidden').value = type;
  // Refresh cred list if panel is open
  if (credOpen) renderCreds();
}

// Credential data: [userId, name, role/label]
const CREDS = {
  main: {
    student: [
      ['S901',  'Zubair Ahmed',   'Student'],
      ['S902',  'Sana Qasim',     'Student'],
      ['S1001', 'Waqar Ullah',    'Student'],
    ],
    staff: [
      ['ADM001','Mr. Tariq Mehmood','Admin'],
      ['T001',  'Dr. Sarah Khan', 'Teacher'],
      ['T002',  'Mr. Hasan Ali',  'Teacher'],
      ['FIN001','Ms. Ayesha Rizvi','Finance'],
      ['VP001', 'Mr. Asad Khan',  'VP Main'],
      ['WH001', 'Ms. Rubina Akhtar','Wing Head'],
    ],
  },
  montessori: {
    student: [
      ['M101', 'Ali Raza',       'Student'],
      ['M201', 'Maryam Khalid',  'Student'],
      ['M301', 'Hamza Aziz',     'Student'],
      ['M401', 'Hira Baig',      'Student'],
    ],
    staff: [
      ['T001', 'Dr. Sarah Khan', 'Teacher'],
      ['T002', 'Mr. Hasan Ali',  'Teacher'],
      ['WH001','Ms. Rubina Akhtar','Wing Head'],
    ],
  },
  ilc: {
    student: [
      ['ILC101','Hamza Tanveer',  'ILC Student'],
      ['ILC102','Sara Baig',      'ILC Student'],
      ['ILC201','Dua Waheed',     'ILC Student'],
    ],
    staff: [
      ['ILC001','Dr. Amna Siddiqui','ILC VP'],
      ['SA001', 'Mr. Tariq Aziz', 'Student Affairs'],
      ['T007',  'Ms. Asma Riaz',  'ILC Teacher'],
    ],
  },
};

const ROLE_COLORS = {
  'Admin':'#7c3aed','Teacher':'#059669','Finance':'#d97706',
  'VP Main':'#0369a1','Wing Head':'#c2410c',
  'Student':'#1d4ed8','ILC Student':'#0891b2',
  'ILC VP':'#0891b2','Student Affairs':'#be185d','ILC Teacher':'#059669',
};

function renderCreds() {
  const wing = document.getElementById('wingSelect').value;
  const type = document.getElementById('typeSelect').value === 'student' ? 'student' : 'staff';
  const list = (CREDS[wing] || {})[type] || [];
  const el   = document.getElementById('credList');
  if (!list.length) {
    el.innerHTML = '<div class="cred-none">No test accounts for this selection.</div>';
    return;
  }
  el.innerHTML = list.map(([id, name, role]) => {
    const bg = ROLE_COLORS[role] || '#64748b';
    return `<div class="cred-row" onclick="fillCred('${id}')">
      <span class="cred-id">${id}</span>
      <span class="cred-name">${name}</span>
      <span class="cred-badge" style="background:${bg}">${role}</span>
      <span class="cred-pass">student123</span>
    </div>`;
  }).join('');
}

function fillCred(userId) {
  document.getElementById('userId').value   = userId;
  document.getElementById('password').value = 'student123';
  document.getElementById('credPanel').style.display = 'none';
  document.getElementById('credToggle').innerHTML = '<i class="fas fa-key me-1"></i>Show test accounts for this wing';
}

let credOpen = false;
function toggleCreds() {
  credOpen = !credOpen;
  const panel = document.getElementById('credPanel');
  panel.style.display = credOpen ? 'block' : 'none';
  document.getElementById('credToggle').innerHTML = credOpen
    ? '<i class="fas fa-times me-1"></i>Hide test accounts'
    : '<i class="fas fa-key me-1"></i>Show test accounts for this wing';
  if (credOpen) renderCreds();
}

// Init
updateTheme();
</script>

</body>
</html>

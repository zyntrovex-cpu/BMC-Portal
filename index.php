<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

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

// If there was a POST error, figure out which phase/type to restore
$postType = $_POST['user_type'] ?? 'student';
$postWing = $_POST['wing']      ?? 'main';
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
  height:100%; overflow:hidden;
  background: linear-gradient(135deg,#0f1f3d 0%,#1c3054 50%,#0d2847 100%);
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  display:flex; align-items:center; justify-content:center;
}

/* ── Card ── */
.login-card {
  width:400px; max-width:96vw; max-height:98vh;
  background:#fff; border-radius:16px;
  box-shadow:0 24px 64px rgba(0,0,0,.5), 0 0 0 1px rgba(255,255,255,.08);
  overflow:hidden; display:flex; flex-direction:column;
}

/* ── Header ── */
.login-header {
  padding:18px 24px 15px; text-align:center;
  position:relative; overflow:hidden; flex-shrink:0;
  background: var(--hdr, linear-gradient(160deg,#0f1f3d 0%,#1c3054 60%,#1e3a8a 100%));
  transition: background .4s ease;
}
.login-header::before {
  content:''; position:absolute; inset:0;
  background:radial-gradient(ellipse at 50% -10%,rgba(255,255,255,.15) 0%,transparent 65%);
  pointer-events:none;
}
.logo-ring {
  width:66px; height:66px; border-radius:50%;
  background:rgba(255,255,255,.96);
  display:flex; align-items:center; justify-content:center;
  margin:0 auto 9px;
  box-shadow:0 4px 18px rgba(0,0,0,.3), 0 0 0 3px rgba(255,255,255,.18);
  padding:5px; position:relative; z-index:1;
}
.logo-ring img { width:100%; height:100%; object-fit:contain; transition: opacity .3s; }
.login-header h1 { font-size:1.1rem; font-weight:800; color:#fff; position:relative; z-index:1; margin-bottom:2px; }
.login-header .sub { font-size:.7rem; color:rgba(255,255,255,.7); position:relative; z-index:1; }
.wing-badge {
  display:inline-block; margin-top:5px;
  font-size:.62rem; font-weight:700; letter-spacing:.8px; text-transform:uppercase;
  background:rgba(255,255,255,.18); color:rgba(255,255,255,.9);
  border:1px solid rgba(255,255,255,.3); border-radius:20px;
  padding:2px 10px; position:relative; z-index:1;
  transition:opacity .3s;
}

/* ── Body viewport — clips the sliding phases ── */
.card-body {
  flex:1; overflow:hidden; position:relative;
  /* height is driven by whichever phase is visible */
}

/* ── Phase animations ── */
@keyframes slideInRight {
  from { transform:translateX(40px); opacity:0; }
  to   { transform:translateX(0);    opacity:1; }
}
@keyframes slideInLeft {
  from { transform:translateX(-40px); opacity:0; }
  to   { transform:translateX(0);     opacity:1; }
}
.anim-right { animation: slideInRight .32s cubic-bezier(.4,0,.2,1) forwards; }
.anim-left  { animation: slideInLeft  .32s cubic-bezier(.4,0,.2,1) forwards; }

/* ── Phase 1 — "I am a" ── */
#phase1 { padding:24px 24px 20px; }
.phase1-title {
  text-align:center; font-size:.72rem; font-weight:700; color:#9ca3af;
  text-transform:uppercase; letter-spacing:.6px; margin-bottom:14px;
}
.type-tiles { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.type-tile {
  border:2px solid #e5e7eb; border-radius:12px;
  padding:18px 10px 16px; text-align:center; cursor:pointer;
  transition:border-color .2s, background .2s, transform .15s, box-shadow .2s;
  background:#fafafa;
}
.type-tile:hover {
  border-color: var(--accent,#2563eb);
  background:#eff6ff;
  transform:translateY(-2px);
  box-shadow:0 4px 16px rgba(37,99,235,.12);
}
.type-tile .tile-icon {
  font-size:1.8rem; margin-bottom:8px; display:block;
  color: var(--accent,#2563eb);
}
.type-tile .tile-label { font-size:.88rem; font-weight:700; color:#1e293b; }
.type-tile .tile-sub   { font-size:.7rem;  color:#9ca3af; margin-top:3px; }

/* ── Phase 2 — Wing + Credentials ── */
#phase2 { padding:18px 22px 16px; display:none; }

/* Wing tiles (student only) */
.wing-tiles { display:grid; grid-template-columns:repeat(3,1fr); gap:7px; margin-bottom:14px; }
.wing-tile {
  border:2px solid #e5e7eb; border-radius:9px;
  padding:10px 6px; text-align:center; cursor:pointer;
  transition:border-color .2s, background .2s;
  background:#fafafa;
}
.wing-tile.active { border-color: var(--accent,#2563eb); background: var(--accent-light,#eff6ff); }
.wing-tile .wt-icon { font-size:1rem; display:block; margin-bottom:4px; }
.wing-tile .wt-label { font-size:.72rem; font-weight:700; color:#374151; }

/* Section label */
.sec-label {
  font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px;
  color:#9ca3af; margin-bottom:8px; display:flex; align-items:center; gap:6px;
}
.sec-label::after { content:''; flex:1; height:1px; background:#e5e7eb; }

/* Alert */
.alert-msg { font-size:.8rem; border-radius:6px; padding:8px 12px; margin-bottom:12px; display:flex; align-items:center; gap:7px; }
.alert-success-msg { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
.alert-error-msg   { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }

/* Fields */
.field-group { margin-bottom:10px; }
.field-group label { display:block; font-size:.75rem; font-weight:600; color:#374151; margin-bottom:4px; }
.field-group input {
  width:100%; padding:9px 12px; font-size:.88rem;
  border:1.5px solid #d5dde8; border-radius:7px; outline:none;
  transition:border-color .2s, box-shadow .2s; color:#1e293b;
}
.field-group input:focus { border-color:var(--accent,#2563eb); box-shadow:0 0 0 3px var(--accent-glow,rgba(37,99,235,.12)); }

/* Buttons */
.btn-login {
  width:100%; padding:10px;
  background: var(--btn-bg, linear-gradient(135deg,#1c3054,#2563eb));
  border:none; border-radius:8px; color:#fff; font-weight:700; font-size:.9rem;
  cursor:pointer; transition:opacity .15s, transform .1s;
  display:flex; align-items:center; justify-content:center; gap:8px;
  margin-top:14px;
}
.btn-login:hover { opacity:.88; }
.btn-login:active { transform:scale(.98); }
.btn-back {
  background:none; border:none; color:#9ca3af; font-size:.78rem;
  cursor:pointer; display:flex; align-items:center; gap:5px;
  padding:0; margin-bottom:12px; transition:color .2s;
}
.btn-back:hover { color:#374151; }

/* Footer */
.login-footer {
  display:flex; justify-content:space-between; align-items:center;
  margin-top:10px; padding-top:8px;
  border-top:1px solid #f1f5f9; font-size:.72rem; color:#9ca3af;
}
.login-footer a { color:#6b7280; text-decoration:none; }
.login-footer a:hover { color:var(--accent,#2563eb); }

/* Cred helper */
.cred-toggle {
  width:100%; margin-top:8px; background:none;
  border:1px dashed #d1d5db; border-radius:7px;
  padding:5px 10px; font-size:.72rem; color:#9ca3af;
  cursor:pointer; text-align:center; transition:border-color .2s, color .2s;
}
.cred-toggle:hover { border-color:var(--accent,#2563eb); color:var(--accent,#2563eb); }
.cred-panel { display:none; margin-top:6px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; padding:9px 11px; font-size:.75rem; }
.cred-panel .cred-title { font-size:.67rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#9ca3af; margin-bottom:5px; }
.cred-row { display:flex; align-items:center; gap:7px; padding:4px 7px; border-radius:5px; cursor:pointer; transition:background .15s; margin-bottom:2px; }
.cred-row:hover { background:#e0f2fe; }
.cred-badge { font-size:.62rem; padding:1px 6px; border-radius:10px; font-weight:700; white-space:nowrap; color:#fff; }
.cred-id   { font-weight:700; color:#1e293b; font-size:.78rem; }
.cred-name { color:#6b7280; flex:1; font-size:.74rem; }
.cred-pass { font-size:.67rem; color:#94a3b8; font-family:monospace; }
</style>
</head>
<body>

<div class="login-card" id="loginCard">

  <!-- ── Header ── -->
  <div class="login-header" id="loginHeader">
    <div class="logo-ring">
      <img src="<?= BASE_URL ?>/assets/bmc-logo.png" alt="BMC" id="portalLogo">
    </div>
    <h1 id="portalTitle">BMC Portal</h1>
    <div class="sub">Bahria Model College &mdash; <?= SESSION_YEAR ?></div>
    <div class="wing-badge" id="wingBadge" style="display:none">Main Wing</div>
  </div>

  <!-- ── Body ── -->
  <div class="card-body">

    <!-- ─── Phase 1: Choose type ─── -->
    <div id="phase1">

      <?php if ($msg === 'logout'): ?>
        <div class="alert-msg alert-success-msg"><i class="fas fa-check-circle"></i>Logged out successfully.</div>
      <?php elseif ($msg === 'login'): ?>
        <div class="alert-msg alert-error-msg"><i class="fas fa-lock"></i>Please log in to continue.</div>
      <?php elseif ($msg === 'unauthorized'): ?>
        <div class="alert-msg alert-error-msg"><i class="fas fa-ban"></i>Access denied.</div>
      <?php endif; ?>

      <div class="phase1-title">Who are you?</div>

      <div class="type-tiles">
        <div class="type-tile" id="tileStudent" onclick="selectType('student')">
          <span class="tile-icon"><i class="fas fa-user-graduate"></i></span>
          <div class="tile-label">Student</div>
          <div class="tile-sub">Main · Montessori · ILC</div>
        </div>
        <div class="type-tile" id="tileStaff" onclick="selectType('staff')">
          <span class="tile-icon"><i class="fas fa-user-tie"></i></span>
          <div class="tile-label">Staff</div>
          <div class="tile-sub">Teachers · Admin · Finance</div>
        </div>
      </div>
    </div>

    <!-- ─── Phase 2: Credentials ─── -->
    <div id="phase2">

      <!-- Back button -->
      <button class="btn-back" type="button" onclick="goBack()">
        <i class="fas fa-arrow-left"></i> Back
      </button>

      <?php if ($error): ?>
        <div class="alert-msg alert-error-msg"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- Wing tiles — only for students -->
      <div id="wingSection" style="display:none">
        <div class="sec-label">Select Wing</div>
        <div class="wing-tiles">
          <div class="wing-tile active" id="wt-main"        onclick="selectWing('main')">
            <span class="wt-icon">🏫</span>
            <div class="wt-label">Main</div>
          </div>
          <div class="wing-tile"       id="wt-montessori"   onclick="selectWing('montessori')">
            <span class="wt-icon">🌱</span>
            <div class="wt-label">Montessori</div>
          </div>
          <div class="wing-tile"       id="wt-ilc"          onclick="selectWing('ilc')">
            <span class="wt-icon">🤝</span>
            <div class="wt-label">ILC</div>
          </div>
        </div>
      </div>

      <!-- Credentials form -->
      <div class="sec-label" id="credLabel">Enter Credentials</div>

      <form method="POST" id="loginForm">
        <input type="hidden" name="wing"      id="wingHidden"     value="main">
        <input type="hidden" name="user_type" id="userTypeHidden" value="student">

        <div class="field-group">
          <label for="userId"><i class="fas fa-id-card" style="margin-right:5px;opacity:.55"></i>User ID</label>
          <input type="text" name="user_id" id="userId"
                 placeholder="e.g. STU001 · T001 · ADM001"
                 value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>" required autofocus>
        </div>
        <div class="field-group">
          <label for="password"><i class="fas fa-key" style="margin-right:5px;opacity:.55"></i>Password</label>
          <input type="password" name="password" id="password"
                 placeholder="Enter your password" required>
        </div>

        <button type="submit" class="btn-login" id="loginBtn">
          <i class="fas fa-sign-in-alt"></i>
          <span id="btnText">Sign In</span>
        </button>
      </form>

      <!-- Test credentials helper -->
      <button class="cred-toggle" type="button" onclick="toggleCreds()" id="credToggle">
        <i class="fas fa-key me-1"></i>Show test accounts
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

  </div><!-- /card-body -->
</div><!-- /login-card -->

<script>
// ── Theme definitions ──────────────────────────────────────────────
const WING_THEMES = {
  main: {
    hdr:   'linear-gradient(160deg,#0f1f3d 0%,#1c3054 60%,#1e3a8a 100%)',
    btn:   'linear-gradient(135deg,#1c3054,#2563eb)',
    accent:'#2563eb', glow:'rgba(37,99,235,.13)', light:'#eff6ff',
    badge: 'Main Wing',
    logo:  '<?= BASE_URL ?>/assets/bmc-logo.png',
    title: 'BMC Portal',
  },
  montessori: {
    hdr:   'linear-gradient(160deg,#052e16 0%,#065f46 60%,#059669 100%)',
    btn:   'linear-gradient(135deg,#065f46,#059669)',
    accent:'#059669', glow:'rgba(5,150,105,.13)', light:'#f0fdf4',
    badge: 'Montessori',
    logo:  '<?= BASE_URL ?>/assets/bmc-logo.png',
    title: 'BMC Portal',
  },
  ilc: {
    hdr:   'linear-gradient(160deg,#0c4a6e 0%,#0369a1 60%,#0891b2 100%)',
    btn:   'linear-gradient(135deg,#0369a1,#0891b2)',
    accent:'#0891b2', glow:'rgba(8,145,178,.13)', light:'#ecfeff',
    badge: 'ILC',
    logo:  '<?= BASE_URL ?>/assets/ilc-logo.png',
    title: 'ILC Portal',
  },
};
const STAFF_THEME = {
  hdr:   'linear-gradient(160deg,#1e1b4b 0%,#3730a3 60%,#4f46e5 100%)',
  btn:   'linear-gradient(135deg,#3730a3,#6366f1)',
  accent:'#6366f1', glow:'rgba(99,102,241,.13)', light:'#eef2ff',
  badge: null,
  logo:  '<?= BASE_URL ?>/assets/bmc-logo.png',
  title: 'BMC Staff Portal',
};

// ── State ──────────────────────────────────────────────────────────
let selectedType = 'student';
let selectedWing = 'main';
let credOpen     = false;

// ── Apply theme ────────────────────────────────────────────────────
function applyTheme(t) {
  document.getElementById('loginHeader').style.background = t.hdr;
  document.getElementById('loginBtn').style.background    = t.btn;
  document.getElementById('portalLogo').src               = t.logo;
  document.getElementById('portalTitle').textContent      = t.title;
  document.documentElement.style.setProperty('--accent',      t.accent);
  document.documentElement.style.setProperty('--accent-glow', t.glow);
  document.documentElement.style.setProperty('--accent-light',t.light);
  document.documentElement.style.setProperty('--btn-bg',      t.btn);
  const badge = document.getElementById('wingBadge');
  if (t.badge) { badge.textContent = t.badge; badge.style.display = ''; }
  else          { badge.style.display = 'none'; }
}

// ── Phase transition ───────────────────────────────────────────────
function selectType(type) {
  selectedType = type;
  document.getElementById('userTypeHidden').value = type;

  const isStudent = type === 'student';

  // Theme
  applyTheme(isStudent ? WING_THEMES[selectedWing] : STAFF_THEME);

  // Wing section
  document.getElementById('wingSection').style.display = isStudent ? 'block' : 'none';

  // Button label
  document.getElementById('btnText').textContent = isStudent ? 'Sign In as Student' : 'Sign In as Staff';

  // Animate phase transition
  const p1 = document.getElementById('phase1');
  const p2 = document.getElementById('phase2');
  p1.style.display = 'none';
  p2.style.display = 'block';
  p2.classList.remove('anim-left');
  void p2.offsetWidth; // reflow
  p2.classList.add('anim-right');

  // Focus user ID field
  setTimeout(() => document.getElementById('userId').focus(), 340);

  // Refresh cred list
  if (credOpen) renderCreds();
}

function goBack() {
  const p1 = document.getElementById('phase1');
  const p2 = document.getElementById('phase2');
  p2.style.display = 'none';
  p1.style.display = 'block';
  p1.classList.remove('anim-right');
  void p1.offsetWidth;
  p1.classList.add('anim-left');
  // Reset header to neutral
  applyTheme(WING_THEMES.main);
  document.getElementById('wingBadge').style.display = 'none';
}

// ── Wing selection (student only) ─────────────────────────────────
function selectWing(wing) {
  selectedWing = wing;
  document.getElementById('wingHidden').value = wing;
  applyTheme(WING_THEMES[wing]);
  // Update active tile
  ['main','montessori','ilc'].forEach(w => {
    document.getElementById('wt-'+w).classList.toggle('active', w === wing);
  });
  if (credOpen) renderCreds();
}

// ── Credential helper ──────────────────────────────────────────────
const CREDS = {
  main: {
    student: [
      ['S901',  'Zubair Ahmed',     'Student'],
      ['S902',  'Sana Qasim',       'Student'],
      ['S1001', 'Waqar Ullah',      'Student'],
    ],
    staff: [
      ['ADM001','Mr. Tariq Mehmood','Admin'],
      ['T001',  'Dr. Sarah Khan',   'Teacher'],
      ['T002',  'Mr. Hasan Ali',    'Teacher'],
      ['FIN001','Ms. Ayesha Rizvi', 'Finance'],
      ['VP001', 'Mr. Asad Khan',    'VP Main'],
      ['WH001', 'Ms. Rubina Akhtar','Wing Head'],
    ],
  },
  montessori: {
    student: [
      ['M101','Ali Raza',      'Student'],
      ['M201','Maryam Khalid', 'Student'],
      ['M301','Hamza Aziz',    'Student'],
    ],
    staff: [
      ['T001','Dr. Sarah Khan','Teacher'],
      ['T002','Mr. Hasan Ali', 'Teacher'],
      ['WH001','Ms. Rubina Akhtar','Wing Head'],
    ],
  },
  ilc: {
    student: [
      ['ILC101','Hamza Tanveer', 'ILC Student'],
      ['ILC102','Sara Baig',     'ILC Student'],
      ['ILC201','Dua Waheed',    'ILC Student'],
    ],
    staff: [
      ['ILC001','Dr. Amna Siddiqui','ILC VP'],
      ['SA001', 'Mr. Tariq Aziz',  'Student Affairs'],
      ['T007',  'Ms. Asma Riaz',   'ILC Teacher'],
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
  const bucket = selectedType === 'staff' ? 'staff' : 'student';
  // For staff, pool all wings
  let list = [];
  if (selectedType === 'staff') {
    const seen = new Set();
    ['main','montessori','ilc'].forEach(w => {
      (CREDS[w].staff || []).forEach(r => { if (!seen.has(r[0])) { seen.add(r[0]); list.push(r); } });
    });
  } else {
    list = (CREDS[selectedWing] || CREDS.main)[bucket] || [];
  }
  const el = document.getElementById('credList');
  if (!list.length) { el.innerHTML = '<div style="color:#9ca3af;text-align:center;padding:4px 0">No accounts for this selection.</div>'; return; }
  el.innerHTML = list.map(([id, name, role]) =>
    `<div class="cred-row" onclick="fillCred('${id}')">
      <span class="cred-id">${id}</span>
      <span class="cred-name">${name}</span>
      <span class="cred-badge" style="background:${ROLE_COLORS[role]||'#64748b'}">${role}</span>
      <span class="cred-pass">student123</span>
    </div>`
  ).join('');
}

function fillCred(userId) {
  document.getElementById('userId').value   = userId;
  document.getElementById('password').value = 'student123';
  document.getElementById('credPanel').style.display = 'none';
  document.getElementById('credToggle').innerHTML = '<i class="fas fa-key me-1"></i>Show test accounts';
  credOpen = false;
}

function toggleCreds() {
  credOpen = !credOpen;
  document.getElementById('credPanel').style.display = credOpen ? 'block' : 'none';
  document.getElementById('credToggle').innerHTML = credOpen
    ? '<i class="fas fa-times me-1"></i>Hide test accounts'
    : '<i class="fas fa-key me-1"></i>Show test accounts';
  if (credOpen) renderCreds();
}

// ── Init ───────────────────────────────────────────────────────────
// If there was a POST error, restore the correct phase
<?php if ($error): ?>
(function() {
  const type = '<?= htmlspecialchars($postType) ?>';
  const wing = '<?= htmlspecialchars($postWing) ?>';
  selectedType = type; selectedWing = wing;
  document.getElementById('userTypeHidden').value = type;
  document.getElementById('wingHidden').value      = wing;
  const isStudent = type === 'student';
  applyTheme(isStudent ? WING_THEMES[wing] : STAFF_THEME);
  document.getElementById('wingSection').style.display = isStudent ? 'block' : 'none';
  document.getElementById('btnText').textContent = isStudent ? 'Sign In as Student' : 'Sign In as Staff';
  if (isStudent) selectWing(wing);
  document.getElementById('phase1').style.display = 'none';
  document.getElementById('phase2').style.display = 'block';
})();
<?php endif; ?>
</script>

</body>
</html>

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['user'])) {
    $role = $_SESSION['user']['role'];
    $map  = [
        'student'           => '/portal/student/dashboard.php',
        'teacher'           => '/portal/teacher/dashboard.php',
        'montessori_teacher'=> '/portal/teacher/dashboard.php',
        'ilc_teacher'       => '/portal/teacher/dashboard.php',
        'admin'             => '/portal/admin/dashboard.php',
        'finance'           => '/portal/finance/dashboard.php',
        'ilc_vp'            => '/portal/ilc/dashboard.php',
        'student_affairs'   => '/portal/student-affairs/dashboard.php',
        'vp_main'           => '/portal/vp/dashboard.php',
        'wing_head'         => '/portal/wing-head/dashboard.php',
        'vp_montessori'     => '/portal/vp-montessori/dashboard.php',
        'examination_head'  => '/portal/exam-head/dashboard.php',
    ];
    redirect($map[$role] ?? '/portal/index.php');
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

            $selectedType  = $_POST['user_type'] ?? 'student';
            $isStudentRole = $user['role'] === 'student';

            if ($selectedType === 'student' && !$isStudentRole) {
                $error = 'This ID belongs to a staff account. Please go back and use the <strong>Staff</strong> login.';
            } elseif ($selectedType === 'staff' && $isStudentRole) {
                $error = 'This ID belongs to a student account. Please go back and use the <strong>Student</strong> login.';
            } else {
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
                    'student'           => '/portal/student/dashboard.php',
                    'teacher'           => '/portal/teacher/dashboard.php',
                    'montessori_teacher'=> '/portal/teacher/dashboard.php',
                    'ilc_teacher'       => '/portal/teacher/dashboard.php',
                    'admin'             => '/portal/admin/dashboard.php',
                    'finance'           => '/portal/finance/dashboard.php',
                    'ilc_vp'            => '/portal/ilc/dashboard.php',
                    'student_affairs'   => '/portal/student-affairs/dashboard.php',
                    'vp_main'           => '/portal/vp/dashboard.php',
                    'wing_head'         => '/portal/wing-head/dashboard.php',
                    'vp_montessori'     => '/portal/vp-montessori/dashboard.php',
                    'examination_head'  => '/portal/exam-head/dashboard.php',
                ];
                redirect($map[$user['role']] ?? '/portal/index.php');
            }
        } else {
            $error = 'Invalid User ID or password.';
        }
    }
}

$postType = $_POST['user_type'] ?? 'student';
$postWing = $_POST['wing']      ?? 'main';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BMC Portal — Bahria Model College</title>
<link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/bmc-logo.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

/* ── Page foundation ── */
html, body { height:100%; overflow-x: hidden; }
body {
  min-height:100vh;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* ── Hero background ── */
.hero-bg {
  position: fixed;
  inset: 0;
  background-image: url('<?= BASE_URL ?>/assets/school-building.webp');
  background-size: cover;
  background-position: center 60%;
  background-repeat: no-repeat;
  z-index: 0;
}
.hero-bg::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(
    160deg,
    rgba(8, 22, 52, 0.88) 0%,
    rgba(12, 32, 68, 0.82) 40%,
    rgba(6, 18, 42, 0.90) 100%
  );
}

/* ── Subtle animated grain overlay ── */
.hero-bg::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
  background-size: 200px 200px;
  opacity: 0.25;
  z-index: 1;
  pointer-events: none;
}

/* ── Page scroll container ── */
.page-wrap {
  position: relative;
  z-index: 1;
  height: 100vh;
  overflow-x: hidden;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  /* No justify-content:center — flex spacers handle centering so that
     when content overflows the viewport, it stays accessible from the top */
  padding: 0 clamp(24px, 5vw, 56px);
  box-sizing: border-box;
}
/* Flex spacers: grow equally to center content; collapse when viewport is short */
.page-wrap::before,
.page-wrap::after {
  content: '';
  flex: 1 0 clamp(12px, 2.5vh, 32px);
}

/* ── School branding header ── */
.school-header {
  text-align: center;
  margin-bottom: clamp(16px, 2.8vh, 28px);
  animation: fadeDown .7s cubic-bezier(.22,.68,0,1.2) forwards;
}
.school-logo-wrap {
  width: 96px; height: 96px;
  background: rgba(255,255,255,.96);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 14px;
  padding: 9px;
  box-shadow: 0 10px 36px rgba(0,0,0,.45), 0 0 0 3px rgba(255,255,255,.2), 0 0 0 7px rgba(255,255,255,.07);
}
.school-logo-wrap img { width: 100%; height: 100%; object-fit: contain; }
.school-name {
  font-size: clamp(1.65rem, 3.2vw, 2.1rem); font-weight: 900; color: #fff;
  letter-spacing: .5px; line-height: 1.15;
  text-shadow: 0 2px 20px rgba(0,0,0,.6);
}
.school-sub {
  font-size: clamp(.83rem, 1.4vw, .95rem); color: rgba(255,255,255,.68);
  margin-top: 5px; letter-spacing: .35px;
}
.portal-welcome {
  display: inline-block;
  margin-top: 10px;
  font-size: .78rem; font-weight: 700;
  letter-spacing: 2px; text-transform: uppercase;
  color: rgba(255,255,255,.48);
  border-top: 1px solid rgba(255,255,255,.15);
  padding-top: 9px;
  width: 100%; max-width: 300px;
}
/* Hide school header when login form is active — login card has its own branded header */
body.phase2-active .school-header { display: none; }
body.phase2-active .site-footer   { display: none; }

/* ── Phase 1 — Portal selection cards ── */
#phase1 {
  width: 100%;
  max-width: 740px;
  animation: fadeUp .65s cubic-bezier(.22,.68,0,1.2) forwards;
}

.portal-cards {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: clamp(14px, 2.5vw, 24px);
}
@media (max-width: 360px) {
  .portal-cards { grid-template-columns: 1fr; }
}

.portal-card {
  position: relative;
  min-width: 0;
  background: rgba(255,255,255,.10);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(255,255,255,.18);
  border-radius: 22px;
  padding: clamp(24px, 3.8vh, 38px) clamp(18px, 3vw, 30px) clamp(20px, 3vh, 30px);
  text-align: center;
  cursor: pointer;
  transition: transform .22s cubic-bezier(.4,0,.2,1),
              box-shadow .22s ease,
              background .22s ease,
              border-color .22s ease;
  overflow: hidden;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
}
.portal-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,.35), transparent);
}
.portal-card::after {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse at 50% 0%, rgba(255,255,255,.12) 0%, transparent 65%);
  pointer-events: none;
}

.portal-card:hover {
  transform: translateY(-6px);
  background: rgba(255,255,255,.16);
  border-color: rgba(255,255,255,.30);
  box-shadow: 0 20px 60px rgba(0,0,0,.35), 0 0 0 1px rgba(255,255,255,.12);
}
.portal-card:active { transform: translateY(-2px); }

/* Card accent glow bottom border on hover */
.portal-card.card-student:hover { box-shadow: 0 20px 60px rgba(0,0,0,.35), 0 4px 0 0 #3b82f6, 0 0 0 1px rgba(59,130,246,.2); }
.portal-card.card-staff:hover   { box-shadow: 0 20px 60px rgba(0,0,0,.35), 0 4px 0 0 #8b5cf6, 0 0 0 1px rgba(139,92,246,.2); }

.card-icon-wrap {
  width: 68px; height: 68px;
  border-radius: 18px;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto clamp(12px, 1.8vh, 16px);
  position: relative; z-index: 1;
}
.card-student .card-icon-wrap { background: rgba(59,130,246,.25); box-shadow: 0 8px 24px rgba(59,130,246,.25); }
.card-staff   .card-icon-wrap { background: rgba(139,92,246,.25); box-shadow: 0 8px 24px rgba(139,92,246,.25); }

.card-icon-wrap i {
  font-size: 1.9rem;
}
.card-student .card-icon-wrap i { color: #93c5fd; }
.card-staff   .card-icon-wrap i { color: #c4b5fd; }

.card-title {
  font-size: 1.15rem; font-weight: 800; color: #fff;
  position: relative; z-index: 1; margin-bottom: 6px;
  text-shadow: 0 1px 6px rgba(0,0,0,.3);
}
.card-desc {
  font-size: .78rem; color: rgba(255,255,255,.55);
  position: relative; z-index: 1; line-height: 1.5;
  margin-bottom: clamp(12px, 2vh, 20px);
}
.card-cta {
  display: inline-flex; align-items: center; justify-content: center; gap: 7px;
  position: relative; z-index: 1;
  font-size: .8rem; font-weight: 700; letter-spacing: .4px;
  padding: 8px 22px; border-radius: 50px;
  border: 1.5px solid rgba(255,255,255,.3);
  color: #fff;
  background: rgba(255,255,255,.1);
  white-space: nowrap;
  max-width: 100%;
  transition: background .18s, border-color .18s, transform .12s;
}
.portal-card:hover .card-cta {
  background: rgba(255,255,255,.2);
  border-color: rgba(255,255,255,.45);
}
.card-student:hover .card-cta { background: rgba(59,130,246,.3); border-color: rgba(59,130,246,.6); }
.card-staff:hover   .card-cta { background: rgba(139,92,246,.3); border-color: rgba(139,92,246,.6); }

/* ── Phase 2 — Login form ── */
#phase2 {
  display: none;
  width: 100%; max-width: 460px;
}

.login-glass {
  background: rgba(255,255,255,.11);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border: 1px solid rgba(255,255,255,.20);
  border-radius: 24px;
  overflow: hidden;
  box-shadow: 0 24px 80px rgba(0,0,0,.45);
}

.login-glass-header {
  padding: clamp(14px, 2.5vh, 22px) 28px clamp(12px, 2vh, 18px);
  text-align: center;
  position: relative;
  border-bottom: 1px solid rgba(255,255,255,.1);
  background: var(--hdr, rgba(15,31,61,.6));
  transition: background .4s ease;
}
.login-glass-header::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,.4), transparent);
}

.login-hdr-logo {
  width: 60px; height: 60px;
  background: rgba(255,255,255,.95);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 9px;
  padding: 5px;
  box-shadow: 0 4px 16px rgba(0,0,0,.3), 0 0 0 2px rgba(255,255,255,.2);
}
.login-hdr-logo img { width:100%; height:100%; object-fit:contain; }
.login-hdr-title { font-size:1.15rem; font-weight:900; color:#fff; margin-bottom:2px; letter-spacing:.3px; }
.login-hdr-sub   { font-size:.74rem; color:rgba(255,255,255,.6); }
.wing-badge-login {
  display: inline-block; margin-top:8px;
  font-size:.67rem; font-weight:700; letter-spacing:.8px; text-transform:uppercase;
  background:rgba(255,255,255,.15); color:rgba(255,255,255,.9);
  border:1px solid rgba(255,255,255,.25); border-radius:20px;
  padding:3px 13px;
}

.login-glass-body { padding: clamp(16px, 2.5vh, 22px) 26px clamp(14px, 2.2vh, 22px); }

/* Back button */
.btn-back-glass {
  display: flex; align-items: center; gap: 7px;
  background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18);
  border-radius: 8px; padding: 6px 14px;
  color: rgba(255,255,255,.8); font-size:.8rem; cursor: pointer;
  transition: background .18s, color .18s;
  margin-bottom: clamp(12px, 2vh, 16px);
}
.btn-back-glass:hover { background: rgba(255,255,255,.18); color: #fff; }

/* Alert */
.alert-glass {
  border-radius: 10px; padding: 10px 14px; margin-bottom: 16px;
  display: flex; align-items: center; gap: 9px; font-size: .83rem;
}
.alert-glass-success { background: rgba(16,185,129,.15); border: 1px solid rgba(16,185,129,.3); color: #6ee7b7; }
.alert-glass-error   { background: rgba(239,68,68,.15);  border: 1px solid rgba(239,68,68,.3);  color: #fca5a5; }

/* Wing tiles in login */
.wing-label-glass {
  font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px;
  color:rgba(255,255,255,.45); margin-bottom:10px;
  display:flex; align-items:center; gap:8px;
}
.wing-label-glass::after { content:''; flex:1; height:1px; background:rgba(255,255,255,.12); }

.wing-tiles-glass { display:grid; grid-template-columns:repeat(3,1fr); gap:9px; margin-bottom:clamp(12px,2vh,18px); }
@media (max-width: 380px) {
  .wing-tiles-glass { grid-template-columns: repeat(2, 1fr); }
}
.wing-tile-g {
  border: 2px solid transparent;
  border-radius: 13px;
  padding: clamp(10px,1.8vh,14px) 8px clamp(8px,1.4vh,11px);
  text-align: center; cursor: pointer;
  user-select: none; position: relative; overflow: hidden;
  transition: transform .18s, box-shadow .18s, border-color .18s;
}
.wing-tile-g::before {
  content:''; position:absolute; inset:0; opacity:.08;
  background:radial-gradient(ellipse at 50% 0%,#fff 0%,transparent 70%);
  pointer-events:none;
}
.wing-tile-g:hover  { transform:translateY(-3px); }
.wing-tile-g:active { transform:translateY(0); }

#wt-main        { background:linear-gradient(145deg,#1c3054 0%,#2563eb 100%); border-color:#2563eb; }
#wt-montessori  { background:linear-gradient(145deg,#064e3b 0%,#059669 100%); border-color:#059669; }
#wt-ilc         { background:linear-gradient(145deg,#713f12 0%,#d97706 100%); border-color:#d97706; }

.wing-tile-g.active { box-shadow: 0 0 0 3px rgba(255,255,255,.2), 0 6px 20px rgba(0,0,0,.2); transform:translateY(-2px); }
#wt-main.active        { box-shadow: 0 0 0 3px rgba(37,99,235,.45),  0 6px 20px rgba(37,99,235,.25); }
#wt-montessori.active  { box-shadow: 0 0 0 3px rgba(5,150,105,.45),  0 6px 20px rgba(5,150,105,.25); }
#wt-ilc.active         { box-shadow: 0 0 0 3px rgba(217,119,6,.45),  0 6px 20px rgba(217,119,6,.25); }

.wing-tile-g .wt-icon  { font-size:1.7rem; display:block; margin-bottom:6px; filter:drop-shadow(0 2px 4px rgba(0,0,0,.25)); }
.wing-tile-g .wt-label { font-size:.78rem; font-weight:800; color:#fff; text-shadow:0 1px 3px rgba(0,0,0,.3); }
.wing-tile-g .wt-sub   { font-size:.63rem; color:rgba(255,255,255,.6); margin-top:2px; }

/* Cred divider */
.cred-divider-glass {
  font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px;
  color:rgba(255,255,255,.45); margin-bottom:clamp(10px,1.6vh,14px);
  display:flex; align-items:center; gap:8px;
}
.cred-divider-glass::after { content:''; flex:1; height:1px; background:rgba(255,255,255,.12); }

/* Fields */
.field-group-glass { margin-bottom: clamp(10px, 1.6vh, 14px); }
.field-group-glass label {
  display:block; font-size:.78rem; font-weight:600;
  color:rgba(255,255,255,.75); margin-bottom:5px;
}
.field-group-glass input {
  width:100%; padding:clamp(9px,1.4vh,11px) 13px; font-size:.9rem;
  background: rgba(255,255,255,.1);
  border: 1.5px solid rgba(255,255,255,.2);
  border-radius: 10px; outline: none;
  color: #fff;
  transition: border-color .2s, background .2s, box-shadow .2s;
}
.field-group-glass input::placeholder { color:rgba(255,255,255,.35); }
.field-group-glass input:focus {
  border-color: var(--accent-color, rgba(99,102,241,.8));
  background: rgba(255,255,255,.15);
  box-shadow: 0 0 0 3px var(--accent-glow, rgba(99,102,241,.2));
}

/* Login button */
.btn-login-glass {
  width:100%; padding:clamp(10px,1.7vh,13px);
  background: var(--btn-bg, linear-gradient(135deg,#3730a3,#6366f1));
  border:none; border-radius:12px; color:#fff;
  font-weight:800; font-size:.96rem;
  cursor:pointer; letter-spacing:.3px;
  transition: opacity .15s, transform .1s, box-shadow .2s;
  display:flex; align-items:center; justify-content:center; gap:9px;
  margin-top:6px;
  box-shadow: 0 4px 20px rgba(0,0,0,.3);
}
.btn-login-glass:hover  { opacity:.9; box-shadow:0 6px 28px rgba(0,0,0,.4); }
.btn-login-glass:active { transform:scale(.98); }

/* Footer */
.login-footer-glass {
  display:flex; justify-content:space-between; align-items:center;
  margin-top:clamp(10px,1.6vh,14px); padding-top:clamp(8px,1.4vh,12px);
  border-top:1px solid rgba(255,255,255,.1);
  font-size:.74rem; color:rgba(255,255,255,.4);
}
.login-footer-glass a { color:rgba(255,255,255,.5); text-decoration:none; transition:color .18s; }
.login-footer-glass a:hover { color:rgba(255,255,255,.85); }

/* Bottom site footer */
.site-footer {
  margin-top: clamp(12px, 2vh, 22px);
  text-align: center;
  font-size: .72rem;
  color: rgba(255,255,255,.28);
  animation: fadeUp .9s .3s cubic-bezier(.22,.68,0,1.2) both;
}

/* ── Animations ── */
@keyframes fadeDown {
  from { opacity:0; transform:translateY(-24px); }
  to   { opacity:1; transform:translateY(0); }
}
@keyframes fadeUp {
  from { opacity:0; transform:translateY(24px); }
  to   { opacity:1; transform:translateY(0); }
}
@keyframes slideInRight {
  from { opacity:0; transform:translateX(40px); }
  to   { opacity:1; transform:translateX(0); }
}
@keyframes slideInLeft {
  from { opacity:0; transform:translateX(-40px); }
  to   { opacity:1; transform:translateX(0); }
}
.anim-right { animation: slideInRight .3s cubic-bezier(.4,0,.2,1) forwards; }
.anim-left  { animation: slideInLeft  .3s cubic-bezier(.4,0,.2,1) forwards; }


/* ── Responsive ── */
@media (max-width: 700px) {
  #phase1 { max-width: 100%; }
  .portal-cards { gap: clamp(10px, 3vw, 16px); }
}
@media (max-width: 600px) {
  .school-logo-wrap { width:76px; height:76px; }
  .school-name { font-size:1.45rem; }
  .school-sub  { font-size:.8rem; }
  .portal-card  { padding:18px 14px 16px; border-radius:16px; }
  .card-icon-wrap { width:52px; height:52px; border-radius:13px; }
  .card-icon-wrap i { font-size:1.4rem; }
  .card-title { font-size:.9rem; }
  .card-cta   { font-size:.72rem; padding:7px 13px; }
  #phase2 { max-width:100%; }
  .login-glass { border-radius:18px; }
  .login-glass-body { padding:14px 18px 16px; }
  .wing-tile-g .wt-icon { font-size:1.4rem; }
}
@media (max-width: 430px) {
  .page-wrap { padding-left: 14px; padding-right: 14px; }
  .portal-cards { gap: 10px; }
  .portal-card  { padding:16px 10px 14px; }
  .card-cta { padding:6px 10px; font-size:.68rem; }
}
@media (max-width: 380px) {
  .card-desc { display:none; }
  .school-header { margin-bottom:14px; }
  .portal-welcome { display:none; }
  .school-logo-wrap { width:64px; height:64px; }
  .school-name { font-size:1.2rem; }
  .card-icon-wrap { width:46px; height:46px; }
  .card-icon-wrap i { font-size:1.25rem; }
}

/* Accessibility */
.portal-card:focus-visible,
.wing-tile-g:focus-visible,
.btn-back-glass:focus-visible {
  outline: 2.5px solid rgba(255,255,255,.7);
  outline-offset: 3px;
}
</style>
</head>
<body>

<!-- Hero background image -->
<div class="hero-bg" role="img" aria-label="Bahria Model College campus building"></div>

<div class="page-wrap">

  <!-- ── School branding ── -->
  <header class="school-header">
    <div class="school-logo-wrap">
      <img src="<?= BASE_URL ?>/assets/bmc-logo.png" alt="Bahria Model College Logo">
    </div>
    <div class="school-name">Bahria Model College</div>
    <div class="school-sub">Bin Qasim, Karachi &nbsp;&middot;&nbsp; <?= SESSION_YEAR ?></div>
    <div class="portal-welcome">Welcome to the School Portal</div>
  </header>

  <!-- ── Phase 1: Portal selection ── -->
  <div id="phase1">

    <?php if ($msg === 'logout'): ?>
      <div class="alert-glass alert-glass-success mb-4 justify-content-center">
        <i class="fas fa-check-circle"></i>Logged out successfully.
      </div>
    <?php elseif ($msg === 'login'): ?>
      <div class="alert-glass alert-glass-error mb-4 justify-content-center">
        <i class="fas fa-lock"></i>Please log in to continue.
      </div>
    <?php elseif ($msg === 'unauthorized'): ?>
      <div class="alert-glass alert-glass-error mb-4 justify-content-center">
        <i class="fas fa-ban"></i>Access denied. Please log in again.
      </div>
    <?php endif; ?>

    <div class="portal-cards" role="list">

      <!-- Student Portal -->
      <div class="portal-card card-student" role="listitem"
           tabindex="0" aria-label="Student Portal — sign in as a student"
           onclick="selectType('student')"
           onkeydown="if(event.key==='Enter'||event.key===' ')selectType('student')">
        <div class="card-icon-wrap">
          <i class="fas fa-user-graduate" aria-hidden="true"></i>
        </div>
        <div class="card-title">Student Portal</div>
        <div class="card-desc">Main &middot; Montessori &middot; ILC<br>Access your grades, reports &amp; schedule</div>
        <div class="card-cta">
          <i class="fas fa-arrow-right" aria-hidden="true"></i>Sign In
        </div>
      </div>

      <!-- Staff Portal -->
      <div class="portal-card card-staff" role="listitem"
           tabindex="0" aria-label="Staff Portal — sign in as a staff member"
           onclick="selectType('staff')"
           onkeydown="if(event.key==='Enter'||event.key===' ')selectType('staff')">
        <div class="card-icon-wrap">
          <i class="fas fa-user-tie" aria-hidden="true"></i>
        </div>
        <div class="card-title">Staff Portal</div>
        <div class="card-desc">Teachers &middot; Admin &middot; Finance<br>Manage classes, records &amp; reports</div>
        <div class="card-cta">
          <i class="fas fa-arrow-right" aria-hidden="true"></i>Sign In
        </div>
      </div>

    </div>
  </div>

  <!-- ── Phase 2: Credentials ── -->
  <div id="phase2">
    <div class="login-glass">

      <!-- Glass header -->
      <div class="login-glass-header" id="loginGlassHeader">
        <div class="login-hdr-logo">
          <img src="<?= BASE_URL ?>/assets/bmc-logo.png" alt="BMC" id="portalLogo">
        </div>
        <div class="login-hdr-title" id="portalTitle">BMC Portal</div>
        <div class="login-hdr-sub">Bahria Model College &mdash; <?= SESSION_YEAR ?></div>
        <div class="wing-badge-login" id="wingBadge" style="display:none">Main Wing</div>
      </div>

      <!-- Glass body -->
      <div class="login-glass-body">

        <button class="btn-back-glass" type="button" onclick="goBack()" aria-label="Back to portal selection">
          <i class="fas fa-arrow-left" aria-hidden="true"></i> Back
        </button>

        <?php if ($error): ?>
          <div class="alert-glass alert-glass-error">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <span><?= $error ?></span>
          </div>
        <?php endif; ?>

        <!-- Wing tiles — student only -->
        <div id="wingSection" style="display:none">
          <div class="wing-label-glass">Select Wing</div>
          <div class="wing-tiles-glass" role="radiogroup" aria-label="Select wing">
            <div class="wing-tile-g active" id="wt-main"
                 role="radio" aria-checked="true" tabindex="0"
                 onclick="selectWing('main')"
                 onkeydown="if(event.key==='Enter'||event.key===' ')selectWing('main')">
              <span class="wt-icon" aria-hidden="true">🏫</span>
              <div class="wt-label">Main</div>
              <div class="wt-sub">Grades 8–12</div>
            </div>
            <div class="wing-tile-g" id="wt-montessori"
                 role="radio" aria-checked="false" tabindex="0"
                 onclick="selectWing('montessori')"
                 onkeydown="if(event.key==='Enter'||event.key===' ')selectWing('montessori')">
              <span class="wt-icon" aria-hidden="true">🌱</span>
              <div class="wt-label">Montessori</div>
              <div class="wt-sub">Early Years</div>
            </div>
            <div class="wing-tile-g" id="wt-ilc"
                 role="radio" aria-checked="false" tabindex="0"
                 onclick="selectWing('ilc')"
                 onkeydown="if(event.key==='Enter'||event.key===' ')selectWing('ilc')">
              <span class="wt-icon" aria-hidden="true">🤝</span>
              <div class="wt-label">ILC</div>
              <div class="wt-sub">Language Centre</div>
            </div>
          </div>
        </div>

        <!-- Credentials form -->
        <div class="cred-divider-glass">Enter Credentials</div>

        <form method="POST" id="loginForm" novalidate>
          <input type="hidden" name="wing"      id="wingHidden"     value="main">
          <input type="hidden" name="user_type" id="userTypeHidden" value="student">

          <div class="field-group-glass">
            <label for="userId">
              <i class="fas fa-id-card" style="margin-right:5px;opacity:.6" aria-hidden="true"></i>User ID
            </label>
            <input type="text" name="user_id" id="userId"
                   placeholder="e.g. 1001"
                   value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>"
                   required autocomplete="username">
          </div>
          <div class="field-group-glass">
            <label for="password">
              <i class="fas fa-key" style="margin-right:5px;opacity:.6" aria-hidden="true"></i>Password
            </label>
            <input type="password" name="password" id="password"
                   placeholder="Enter your password"
                   required autocomplete="current-password">
          </div>

          <button type="submit" class="btn-login-glass" id="loginBtn">
            <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
            <span id="btnText">Sign In</span>
          </button>
        </form>

        <div class="login-footer-glass">
          <a href="forgot-password.php">
            <i class="fas fa-question-circle me-1" aria-hidden="true"></i>Forgot password?
          </a>
          <span>&copy; <?= date('Y') ?> BMC</span>
        </div>

      </div>
    </div>
  </div>

  <footer class="site-footer">
    Bahria Model College, Bin Qasim &nbsp;&middot;&nbsp; Secure School Portal
  </footer>

</div><!-- /page-wrap -->

<script>
// ── Theme definitions ──────────────────────────────────────────────
const WING_THEMES = {
  main: {
    hdr:         'rgba(15,31,61,.7)',
    btn:         'linear-gradient(135deg,#1c3054,#2563eb)',
    accent:      'rgba(37,99,235,.8)',
    glow:        'rgba(37,99,235,.2)',
    badge:       'Main Wing',
    logo:        '<?= BASE_URL ?>/assets/bmc-logo.png',
    title:       'BMC Portal',
  },
  montessori: {
    hdr:         'rgba(6,31,22,.75)',
    btn:         'linear-gradient(135deg,#065f46,#059669)',
    accent:      'rgba(5,150,105,.8)',
    glow:        'rgba(5,150,105,.2)',
    badge:       'Montessori',
    logo:        '<?= BASE_URL ?>/assets/bmc-logo.png',
    title:       'BMC Portal',
  },
  ilc: {
    hdr:         'rgba(12,25,60,.75)',
    btn:         'linear-gradient(135deg,#0369a1,#0891b2)',
    accent:      'rgba(8,145,178,.8)',
    glow:        'rgba(8,145,178,.2)',
    badge:       'ILC',
    logo:        '<?= BASE_URL ?>/assets/ilc-logo.png',
    title:       'ILC Portal',
  },
};
const STAFF_THEME = {
  hdr:         'rgba(20,14,70,.72)',
  btn:         'linear-gradient(135deg,#3730a3,#6366f1)',
  accent:      'rgba(99,102,241,.8)',
  glow:        'rgba(99,102,241,.2)',
  badge:       null,
  logo:        '<?= BASE_URL ?>/assets/bmc-logo.png',
  title:       'BMC Staff Portal',
};

let selectedType = 'student';
let selectedWing = 'main';

function applyTheme(t) {
  document.getElementById('loginGlassHeader').style.background = t.hdr;
  document.getElementById('loginBtn').style.background         = t.btn;
  document.getElementById('portalLogo').src                    = t.logo;
  document.getElementById('portalTitle').textContent           = t.title;
  document.documentElement.style.setProperty('--accent-color', t.accent);
  document.documentElement.style.setProperty('--accent-glow',  t.glow);
  document.documentElement.style.setProperty('--btn-bg',       t.btn);
  const badge = document.getElementById('wingBadge');
  if (t.badge) { badge.textContent = t.badge; badge.style.display = ''; }
  else          { badge.style.display = 'none'; }
}

function selectType(type) {
  selectedType = type;
  document.getElementById('userTypeHidden').value = type;
  const isStudent = type === 'student';

  applyTheme(isStudent ? WING_THEMES[selectedWing] : STAFF_THEME);
  document.getElementById('wingSection').style.display   = isStudent ? 'block' : 'none';
  document.getElementById('btnText').textContent         = isStudent ? 'Sign In as Student' : 'Sign In as Staff';

  const p1 = document.getElementById('phase1');
  const p2 = document.getElementById('phase2');
  p1.style.display = 'none';
  p2.style.display = 'block';
  p2.classList.remove('anim-left');
  void p2.offsetWidth;
  p2.classList.add('anim-right');
  document.body.classList.add('phase2-active');
  setTimeout(() => document.getElementById('userId').focus(), 340);
}

function goBack() {
  const p1 = document.getElementById('phase1');
  const p2 = document.getElementById('phase2');
  p2.style.display = 'none';
  p1.style.display = 'block';
  p1.classList.remove('anim-right');
  void p1.offsetWidth;
  p1.classList.add('anim-left');
  document.body.classList.remove('phase2-active');
}

function selectWing(wing) {
  selectedWing = wing;
  document.getElementById('wingHidden').value = wing;
  applyTheme(WING_THEMES[wing]);
  ['main','montessori','ilc'].forEach(w => {
    const el = document.getElementById('wt-'+w);
    el.classList.toggle('active', w === wing);
    el.setAttribute('aria-checked', w === wing ? 'true' : 'false');
  });
}

// ── Restore phase on POST error ────────────────────────────────────
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
  document.body.classList.add('phase2-active');
})();
<?php endif; ?>
</script>

</body>
</html>

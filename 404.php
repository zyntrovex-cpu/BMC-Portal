<?php
http_response_code(404);
$isLoggedIn = !empty($_SESSION['user'] ?? null);
if (session_status() === PHP_SESSION_NONE) session_start();
$isLoggedIn = !empty($_SESSION['user']);
$role = $_SESSION['user']['role'] ?? '';
$dashMap = ['student'=>'/portal/student/dashboard.php','teacher'=>'/portal/teacher/dashboard.php','admin'=>'/portal/admin/dashboard.php','finance'=>'/portal/finance/dashboard.php'];
$dashUrl = $dashMap[$role] ?? '/portal/index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>404 — Page Not Found | BMC Portal</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body { background: linear-gradient(135deg,#0f1f3d 0%,#1c3054 50%,#0d2847 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; }
.card-404 { background:#fff; border-radius:16px; padding:48px 40px; text-align:center; max-width:460px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,.4); }
.num { font-size:96px; font-weight:900; line-height:1; color:#1c3054; letter-spacing:-4px; }
.subtitle { font-size:18px; font-weight:700; color:#374151; margin:12px 0 8px; }
.desc { font-size:14px; color:#6b7280; margin-bottom:28px; }
</style>
</head>
<body>
<div class="card-404">
  <div class="num">404</div>
  <div class="subtitle">Page Not Found</div>
  <div class="desc">The page you're looking for doesn't exist or has been moved.</div>
  <?php if ($isLoggedIn): ?>
    <a href="<?= $dashUrl ?>" class="btn btn-primary me-2"><i class="fas fa-home me-1"></i>Go to Dashboard</a>
  <?php else: ?>
    <a href="<?= url('/portal/index.php') ?>" class="btn btn-primary"><i class="fas fa-sign-in-alt me-1"></i>Back to Login</a>
  <?php endif; ?>
</div>
</body>
</html>

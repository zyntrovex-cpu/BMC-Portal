<?php
// Shared layout helpers

function pageHead(string $title, string $portal = ''): void {
    // Load user permissions into session once per login session.
    // Skipped for admin (always full access) and when table doesn't exist yet.
    if (!empty($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') !== 'admin') {
        $uid = $_SESSION['user']['id'] ?? 0;
        if (!isset($_SESSION['_perms_uid']) || $_SESSION['_perms_uid'] !== $uid) {
            try {
                $db = getDB();
                $st = $db->prepare('SELECT permission, granted FROM user_permissions WHERE user_id = ?');
                $st->execute([$uid]);
                $rows = $st->fetchAll(PDO::FETCH_KEY_PAIR);
                $_SESSION['user_perms'] = $rows ?: null;
            } catch (Exception $e) {
                $_SESSION['user_perms'] = null; // table not created yet — full access
            }
            $_SESSION['_perms_uid'] = $uid;
        }
    }

    $accents = [
        'student'         => '#1d4ed8',
        'teacher'         => '#059669',
        'admin'           => '#7c3aed',
        'finance'         => '#d97706',
        'ilc_vp'          => '#0891b2',
        'student_affairs' => '#be185d',
        'vp_main'         => '#0369a1',
        'wing_head'       => '#c2410c',
    ];
    $accent = $accents[$portal] ?? '#1c3054';
    $base = defined('BASE_URL') ? BASE_URL : '';
    echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . htmlspecialchars($title) . ' — BMC Portal</title>
<link rel="icon" type="image/png" href="' . $base . '/assets/bmc-logo.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="' . $base . '/portal/portal.css">
<style>:root { --accent: ' . $accent . '; --accent-rgb: ' . implode(',', sscanf($accent, '#%02x%02x%02x')) . '; }</style>
</head>
<body>
';
}

function _initials(string $name): string {
    $parts = array_filter(explode(' ', trim($name)));
    $ini   = '';
    foreach (array_slice($parts, 0, 2) as $p) $ini .= strtoupper($p[0]);
    return $ini ?: '?';
}

function sidebar(string $portal, string $active, array $links, array $user = []): void {
    // Fallback to session
    if (empty($user) && !empty($_SESSION['user'])) $user = $_SESSION['user'];

    $base = defined('BASE_URL') ? BASE_URL : '';

    $portalLabels = [
        'student'         => 'Student Portal',
        'teacher'         => 'Teacher Portal',
        'admin'           => 'Admin Panel',
        'finance'         => 'Finance Portal',
        'ilc_vp'          => 'ILC VP Portal',
        'student_affairs' => 'Student Affairs',
        'vp_main'         => 'VP — Main & Montessori',
        'wing_head'       => 'Montessori Wing Head',
    ];
    $portalLabel  = $portalLabels[$portal] ?? 'Portal';

    $profileMap = [
        'student'         => ['href'=>$base.'/portal/student/profile.php',          'label'=>'My Profile', 'key'=>'profile',   'icon'=>'fas fa-user'],
        'teacher'         => ['href'=>$base.'/portal/teacher/profile.php',          'label'=>'My Profile', 'key'=>'profile',   'icon'=>'fas fa-user'],
        'admin'           => ['href'=>$base.'/portal/admin/settings.php',           'label'=>'Settings',   'key'=>'settings',  'icon'=>'fas fa-cog'],
        'finance'         => null,
        'ilc_vp'          => ['href'=>$base.'/portal/ilc/dashboard.php',            'label'=>'Dashboard',  'key'=>'dashboard', 'icon'=>'fas fa-home'],
        'student_affairs' => ['href'=>$base.'/portal/student-affairs/dashboard.php','label'=>'Dashboard',  'key'=>'dashboard', 'icon'=>'fas fa-home'],
        'vp_main'         => ['href'=>$base.'/portal/vp/dashboard.php',             'label'=>'Dashboard',  'key'=>'dashboard', 'icon'=>'fas fa-home'],
        'wing_head'       => ['href'=>$base.'/portal/wing-head/dashboard.php',      'label'=>'Dashboard',  'key'=>'dashboard', 'icon'=>'fas fa-home'],
    ];

    $userInitials = $user ? _initials($user['name'] ?? '') : '?';
    $userName     = htmlspecialchars($user['name'] ?? '');
    $roleLabels   = [
        'student'         => 'Student',
        'teacher'         => 'Teacher',
        'admin'           => 'Administrator',
        'finance'         => 'Finance Staff',
        'ilc_vp'          => 'VP — ILC',
        'student_affairs' => 'Student Affairs',
        'vp_main'         => 'VP — Main & Montessori',
        'wing_head'       => 'Wing Head',
    ];
    $userRole     = $roleLabels[$user['role'] ?? ''] ?? ucfirst($user['role'] ?? '');

    echo '<div id="sidebarOverlay" onclick="document.getElementById(\'sidebar\').classList.remove(\'open\');this.classList.remove(\'show\')"></div>';
    echo '<nav class="sidebar" id="sidebar">

  <div class="sb-brand">
    <div style="width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.95);display:flex;align-items:center;justify-content:center;flex-shrink:0;padding:3px;box-shadow:0 2px 8px rgba(0,0,0,.3);">
      <img src="' . $base . (in_array($portal, ['ilc_vp','student_affairs']) ? '/assets/ilc-logo.png' : '/assets/bmc-logo.png') . '"
           alt="' . (in_array($portal, ['ilc_vp','student_affairs']) ? 'ILC' : 'BMC') . '"
           style="width:100%;height:100%;object-fit:contain;">
    </div>
    <div>
      <div style="font-size:.88rem;font-weight:700;color:#fff;line-height:1.2">' . (in_array($portal, ['ilc_vp','student_affairs']) ? 'ILC Portal' : 'BMC Portal') . '</div>
      <div style="font-size:.7rem;color:rgba(255,255,255,.5);line-height:1.3">' . $portalLabel . '</div>
    </div>
  </div>

  <div class="sb-section-label">Main Menu</div>
  <ul class="sb-nav">';

    foreach ($links as $link) {
        if (isset($link['divider'])) {
            echo '<div class="sb-section-label">' . htmlspecialchars($link['divider']) . '</div>';
            continue;
        }
        $cls  = str_contains($active, $link['key'] ?? '') ? ' active' : '';
        $href = $base . $link['href'];
        echo '<li><a class="sb-link' . $cls . '" href="' . htmlspecialchars($href) . '" onclick="closeSidebar()">
          <span class="sb-icon">' . ($link['icon'] ?? '') . '</span>
          <span>' . htmlspecialchars($link['label']) . '</span>
        </a></li>';
    }

    // Account section
    echo '</ul>
  <div class="sb-section-label">Account</div>
  <ul class="sb-nav">';

    $profItem = $profileMap[$portal] ?? null;
    if ($profItem) {
        $pCls = ($active === $profItem['key']) ? ' active' : '';
        echo '<li><a class="sb-link' . $pCls . '" href="' . $profItem['href'] . '" onclick="closeSidebar()">
          <span class="sb-icon"><i class="' . $profItem['icon'] . '"></i></span>
          <span>' . $profItem['label'] . '</span>
        </a></li>';
    }
    echo '<li><a class="sb-link" href="' . $base . '/portal/logout.php" onclick="closeSidebar()">
        <span class="sb-icon"><i class="fas fa-sign-out-alt"></i></span>
        <span>Logout</span>
      </a></li>';

    echo '</ul>

  <div class="sb-user">
    <div class="sb-user-avatar">' . $userInitials . '</div>
    <div class="sb-user-info">
      <div class="sb-user-name">' . $userName . '</div>
      <div class="sb-user-role">' . $userRole . '</div>
    </div>
  </div>

</nav>';

    // ── Mobile bottom navigation bar (first 4 main links) ──────────
    $bottomLinks = array_values(array_filter($links, fn($l) => !isset($l['divider'])));
    $bottomLinks = array_slice($bottomLinks, 0, 4);
    // Always add profile as last if fewer than 4
    $profItem2 = $profileMap[$portal] ?? null;
    echo '<nav class="mobile-bottom-nav" id="mobileBottomNav">';
    foreach ($bottomLinks as $bl) {
        $bCls  = str_contains($active, $bl['key'] ?? '') ? ' active' : '';
        $bHref = $base . $bl['href'];
        $bIconHtml = $bl['icon'] ?? '<i class="fas fa-circle"></i>';
        $bLbl  = htmlspecialchars($bl['label']);
        echo '<a href="' . htmlspecialchars($bHref) . '" class="' . trim($bCls) . '">'
           . $bIconHtml          // already safe HTML like <i class="fas fa-home"></i>
           . '<span>' . $bLbl . '</span></a>';
    }
    // Always show hamburger as last item to open full sidebar
    echo '<a href="#" onclick="toggleSidebar();return false;">'
       . '<i class="fas fa-th-large"></i><span>More</span></a>';
    echo '</nav>';
}

function viewAsBanner(): void {
    if (empty($_SESSION['view_as_mode'])) return;
    $u    = $_SESSION['user'];
    $base        = defined('BASE_URL') ? BASE_URL : '';
    $adminRole   = $_SESSION['admin_backup']['role'] ?? 'admin';
    $exitUrl     = match($adminRole) {
        'ilc_vp'  => $base . '/portal/ilc/exit-view-as.php',
        'vp_main' => $base . '/portal/vp/exit-view-as.php',
        default   => $base . '/portal/admin/exit-view-as.php',
    };
    $roleLabelsB = ['student'=>'Student','teacher'=>'Teacher','admin'=>'Admin','finance'=>'Finance','ilc_vp'=>'ILC VP','student_affairs'=>'Student Affairs','vp_main'=>'VP Main','wing_head'=>'Wing Head'];
    $rLabel      = $roleLabelsB[$u['role'] ?? ''] ?? ucfirst(str_replace('_', ' ', $u['role'] ?? ''));
    echo '<div style="background:#f59e0b;color:#1c1c1c;padding:8px 20px;font-size:.82rem;display:flex;align-items:center;gap:12px;flex-wrap:wrap;z-index:1100;position:sticky;top:0">
      <i class="fas fa-eye"></i>
      <span>Preview Mode — viewing as <strong>' . htmlspecialchars($u['name'] ?? '') . '</strong>
            (' . htmlspecialchars($u['user_id'] ?? '') . ' / ' . $rLabel . ')</span>
      <a href="' . $exitUrl . '" class="btn btn-sm btn-dark ms-auto" style="font-size:.78rem;padding:2px 10px">
        <i class="fas fa-times me-1"></i>Exit Preview
      </a>
    </div>';
}

function topbar(string $pageTitle, array $user, string $badge = ''): void {
    $portalLabels = [
        'student'         => 'Student Portal',
        'teacher'         => 'Teacher Portal',
        'admin'           => 'Admin Panel',
        'finance'         => 'Finance Portal',
        'ilc_vp'          => 'ILC VP Portal',
        'student_affairs' => 'Student Affairs',
        'vp_main'         => 'VP — Main & Montessori',
        'wing_head'       => 'Montessori Wing Head',
    ];
    $portal       = $user['role'] ?? '';
    $portalLabel  = $portalLabels[$portal] ?? 'Portal';
    $initials     = _initials($user['name'] ?? '');
    $today        = date('D, d M Y');

    $badgeHtml = $badge
        ? '<span class="topbar-badge">' . htmlspecialchars($badge) . '</span>'
        : '';

    // Build a short mobile bottom-nav from the first 4 main links
    // (injected after page content by JS so it stays above fold)
    echo '<header class="topbar" id="topbar">
  <button class="topbar-toggle" id="sidebarToggle" onclick="toggleSidebar()" aria-label="Menu">
    <i class="fas fa-bars"></i>
  </button>

  <div class="topbar-brand">
    <div class="topbar-brand-main">BMC Portal</div>
    <div class="topbar-brand-sub">' . $portalLabel . '</div>
  </div>

  <div class="topbar-right">
    <span class="topbar-date"><i class="far fa-calendar me-1"></i>' . $today . '</span>
    ' . $badgeHtml . '
    <div class="topbar-avatar" title="' . htmlspecialchars($user['name'] ?? '') . '">' . $initials . '</div>
  </div>
</header>
<script>
function toggleSidebar(){
  document.getElementById("sidebar").classList.toggle("open");
  document.getElementById("sidebarOverlay").classList.toggle("show");
}
function closeSidebar(){
  if(window.innerWidth<=991){
    document.getElementById("sidebar").classList.remove("open");
    document.getElementById("sidebarOverlay").classList.remove("show");
  }
}
// Close sidebar on overlay click
(function(){
  var ov=document.getElementById("sidebarOverlay");
  if(ov) ov.addEventListener("click",closeSidebar);
})();
</script>';
    viewAsBanner();
}

<?php
// Shared layout helpers

function pageHead(string $title, string $portal = ''): void {
    $colors = [
        'student' => '#1d4ed8',
        'teacher' => '#059669',
        'admin'   => '#7c3aed',
        'finance' => '#d97706',
    ];
    $accent = $colors[$portal] ?? '#1c3054';
    echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . htmlspecialchars($title) . ' — BMC Portal</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/portal.css">
<style>
:root { --accent: ' . $accent . '; }
.sidebar .nav-link.active, .sidebar .nav-link:hover { background: rgba(255,255,255,.12); }
.topbar { background: var(--accent); }
.sidebar { background: var(--sb-bg); }
</style>
';
}

function sidebar(string $portal, string $active, array $links): void {
    $icons = ['student'=>'👨‍🎓','teacher'=>'👨‍🏫','admin'=>'🛡️','finance'=>'💰'];
    $portalName = ['student'=>'Student Portal','teacher'=>'Teacher Portal','admin'=>'Admin Panel','finance'=>'Finance Portal'];
    $icon = $icons[$portal] ?? '🎓';
    $name = $portalName[$portal] ?? 'BMC Portal';

    echo '<nav class="sidebar" id="sidebar">
    <div class="sb-brand">
      <span style="font-size:1.6rem">' . $icon . '</span>
      <div>
        <div class="fw-bold text-white" style="font-size:.85rem">BMC Portal</div>
        <div style="font-size:.72rem; opacity:.6; color:#fff">' . $name . '</div>
      </div>
    </div>
    <ul class="sb-nav">';

    foreach ($links as $link) {
        if (isset($link['divider'])) {
            echo '<li class="sb-divider">' . htmlspecialchars($link['divider']) . '</li>';
            continue;
        }
        $cls = (str_contains($active, $link['key'] ?? '')) ? ' active' : '';
        echo '<li><a class="sb-link' . $cls . '" href="' . htmlspecialchars($link['href']) . '">
          <span class="sb-icon">' . ($link['icon'] ?? '') . '</span>
          <span>' . htmlspecialchars($link['label']) . '</span>
        </a></li>';
    }

    echo '</ul>
    <div class="sb-footer">
      <a href="/logout.php" class="sb-link text-danger">
        <span class="sb-icon"><i class="fas fa-sign-out-alt"></i></span>
        <span>Logout</span>
      </a>
    </div>
  </nav>';
}

function topbar(string $pageTitle, array $user): void {
    echo '<header class="topbar" id="topbar">
    <button class="topbar-toggle" onclick="document.getElementById(\'sidebar\').classList.toggle(\'open\')">
      <i class="fas fa-bars"></i>
    </button>
    <span class="topbar-title">' . htmlspecialchars($pageTitle) . '</span>
    <div class="topbar-right">
      <span class="topbar-user">
        <i class="fas fa-user-circle me-1"></i>
        ' . htmlspecialchars($user['name']) . '
        <small class="ms-1 opacity-75">(' . htmlspecialchars($user['user_id']) . ')</small>
      </span>
    </div>
  </header>';
}

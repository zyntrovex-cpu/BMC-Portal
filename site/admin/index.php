<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = requireSiteAdmin();
$db    = siteDB();

// ── Stat counts ───────────────────────────────────────────────────
function statCount(PDO $db, string $sql): int {
    try { return (int) $db->query($sql)->fetchColumn(); } catch (Exception $e) { return 0; }
}

$stats = [
    ['icon' => 'fa-newspaper',         'color' => '#0984e3', 'bg' => '#eff6ff', 'label' => 'News Articles',     'value' => statCount($db, 'SELECT COUNT(*) FROM site_news'),                  'href' => 'news.php'],
    ['icon' => 'fa-calendar-alt',      'color' => '#8b5cf6', 'bg' => '#f5f3ff', 'label' => 'Events',           'value' => statCount($db, 'SELECT COUNT(*) FROM site_events'),                'href' => 'events.php'],
    ['icon' => 'fa-bell',              'color' => '#f59e0b', 'bg' => '#fffbeb', 'label' => 'Active Notices',   'value' => statCount($db, 'SELECT COUNT(*) FROM site_notices WHERE is_published=1 AND (expires_at IS NULL OR expires_at>=CURDATE())'), 'href' => 'notices.php'],
    ['icon' => 'fa-chalkboard-teacher','color' => '#10b981', 'bg' => '#ecfdf5', 'label' => 'Faculty Members',  'value' => statCount($db, 'SELECT COUNT(*) FROM site_faculty WHERE is_active=1'), 'href' => 'faculty.php'],
    ['icon' => 'fa-download',          'color' => '#6366f1', 'bg' => '#eef2ff', 'label' => 'Downloads',        'value' => statCount($db, 'SELECT COUNT(*) FROM site_downloads WHERE is_active=1'), 'href' => 'downloads.php'],
    ['icon' => 'fa-images',            'color' => '#ec4899', 'bg' => '#fdf2f8', 'label' => 'Gallery Photos',   'value' => statCount($db, 'SELECT COUNT(*) FROM site_gallery WHERE is_active=1'),  'href' => 'gallery.php'],
    ['icon' => 'fa-quote-right',       'color' => '#14b8a6', 'bg' => '#f0fdfa', 'label' => 'Testimonials',     'value' => statCount($db, 'SELECT COUNT(*) FROM site_testimonials WHERE is_active=1'), 'href' => 'testimonials.php'],
    ['icon' => 'fa-envelope',          'color' => '#ef4444', 'bg' => '#fef2f2', 'label' => 'Unread Messages',  'value' => statCount($db, 'SELECT COUNT(*) FROM site_contact_messages WHERE is_read=0'), 'href' => '#messages'],
];

// ── Recent data ───────────────────────────────────────────────────
$recentNews = [];
try {
    $recentNews = $db->query('SELECT id, title, category, is_published, published_at, created_at FROM site_news ORDER BY created_at DESC LIMIT 5')->fetchAll();
} catch (Exception $e) {}

$recentMessages = [];
try {
    $recentMessages = $db->query('SELECT id, name, email, subject, is_read, created_at FROM site_contact_messages ORDER BY created_at DESC LIMIT 5')->fetchAll();
} catch (Exception $e) {}

$recentNotices = [];
try {
    $recentNotices = $db->query("SELECT id, title, category, priority, created_at FROM site_notices WHERE is_published=1 ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard — BMC Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
  <style>
    .stat-widget { transition: transform .18s, box-shadow .18s; cursor: default; }
    .stat-widget:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.12); }
    .recent-table-wrap { overflow-x: auto; }
    .priority-badge { font-size: .7rem; padding: 2px 8px; border-radius: 99px; font-weight: 700; }
  </style>
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-main">
    <header class="admin-topbar">
      <div>
        <div style="font-weight:700;color:var(--primary);font-size:1.1rem">Dashboard</div>
        <div style="font-size:.78rem;color:var(--text-3)">Welcome back, <?= sh($admin['name']) ?></div>
      </div>
      <div style="display:flex;align-items:center;gap:12px">
        <a href="<?= SITE_URL ?>/index.php" target="_blank" class="btn btn-sm btn-outline-secondary">
          <i class="fas fa-external-link-alt me-1"></i>View Site
        </a>
        <a href="logout.php" class="btn btn-sm btn-outline-danger">
          <i class="fas fa-sign-out-alt me-1"></i>Logout
        </a>
      </div>
    </header>

    <div class="admin-content">

      <!-- Stat Widgets -->
      <div class="row g-3 mb-4">
        <?php foreach ($stats as $s): ?>
        <div class="col-6 col-md-4 col-xl-3">
          <a href="<?= sh($s['href']) ?>" style="text-decoration:none">
            <div class="stat-widget">
              <div class="stat-widget-icon" style="background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>">
                <i class="fas <?= $s['icon'] ?>"></i>
              </div>
              <div>
                <div class="stat-widget-num"><?= number_format($s['value']) ?></div>
                <div class="stat-widget-label"><?= sh($s['label']) ?></div>
              </div>
            </div>
          </a>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Bottom Row: Recent News + Recent Messages + Recent Notices -->
      <div class="row g-3">

        <!-- Recent News -->
        <div class="col-lg-4">
          <div class="admin-card h-100">
            <div class="admin-card-header">
              <span><i class="fas fa-newspaper me-2"></i>Recent News</span>
              <a href="news.php" class="btn btn-sm btn-primary" style="font-size:.76rem;padding:3px 10px">Manage</a>
            </div>
            <div class="admin-card-body p-0 recent-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($recentNews)): ?>
                  <tr><td colspan="3" class="text-center text-muted py-3">No news yet</td></tr>
                  <?php else: ?>
                  <?php foreach ($recentNews as $n): ?>
                  <tr>
                    <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= sh($n['title']) ?>">
                      <?= sh(mb_strimwidth($n['title'], 0, 35, '…')) ?>
                    </td>
                    <td><span class="badge bg-light text-secondary"><?= sh($n['category']) ?></span></td>
                    <td>
                      <?php if ($n['is_published']): ?>
                        <span class="badge bg-success">Live</span>
                      <?php else: ?>
                        <span class="badge bg-secondary">Draft</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Recent Messages -->
        <div class="col-lg-4" id="messages">
          <div class="admin-card h-100">
            <div class="admin-card-header">
              <span><i class="fas fa-envelope me-2"></i>Recent Messages</span>
              <?php $unread = statCount($db, 'SELECT COUNT(*) FROM site_contact_messages WHERE is_read=0'); ?>
              <?php if ($unread): ?>
              <span class="badge bg-danger"><?= $unread ?> new</span>
              <?php endif; ?>
            </div>
            <div class="admin-card-body p-0 recent-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Subject</th>
                    <th>When</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($recentMessages)): ?>
                  <tr><td colspan="3" class="text-center text-muted py-3">No messages yet</td></tr>
                  <?php else: ?>
                  <?php foreach ($recentMessages as $m): ?>
                  <tr style="<?= !$m['is_read'] ? 'font-weight:600' : '' ?>">
                    <td>
                      <?= sh($m['name']) ?>
                      <?php if (!$m['is_read']): ?>
                        <span class="badge bg-danger ms-1" style="font-size:.6rem">NEW</span>
                      <?php endif; ?>
                    </td>
                    <td style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                      <?= sh(mb_strimwidth($m['subject'] ?: '(no subject)', 0, 30, '…')) ?>
                    </td>
                    <td style="font-size:.8rem;color:var(--text-3);white-space:nowrap">
                      <?= date('d M', strtotime($m['created_at'])) ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Recent Notices -->
        <div class="col-lg-4">
          <div class="admin-card h-100">
            <div class="admin-card-header">
              <span><i class="fas fa-bell me-2"></i>Recent Notices</span>
              <a href="notices.php" class="btn btn-sm btn-primary" style="font-size:.76rem;padding:3px 10px">Manage</a>
            </div>
            <div class="admin-card-body p-0 recent-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Title</th>
                    <th>Priority</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($recentNotices)): ?>
                  <tr><td colspan="3" class="text-center text-muted py-3">No notices yet</td></tr>
                  <?php else: ?>
                  <?php foreach ($recentNotices as $n): ?>
                  <tr>
                    <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                      <?= sh(mb_strimwidth($n['title'], 0, 32, '…')) ?>
                    </td>
                    <td><?= priorityBadge($n['priority']) ?></td>
                    <td style="font-size:.8rem;color:var(--text-3);white-space:nowrap">
                      <?= date('d M', strtotime($n['created_at'])) ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div><!-- /row -->
    </div><!-- /admin-content -->
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

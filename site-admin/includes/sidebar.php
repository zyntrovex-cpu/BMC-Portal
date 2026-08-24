<?php
// Shared sidebar for all admin panel pages
// $admin must be set by the calling page (via requireSiteAdmin())
$currentPage = basename($_SERVER['PHP_SELF']);
function adminActive(string $file): string {
    global $currentPage;
    return $currentPage === $file ? 'active' : '';
}
?>
<aside class="admin-sidebar">
  <div class="admin-sidebar-header">
    <div style="display:flex;align-items:center;gap:10px">
      <img src="<?= BASE_URL ?>/assets/bmc-logo.png" alt="BMC Logo"
           style="width:40px;height:40px;border-radius:50%;object-fit:contain;
                  background:#fff;padding:3px;flex-shrink:0;
                  box-shadow:0 2px 8px rgba(0,0,0,.3);">
      <div>
        <div style="font-weight:800;color:#fff;font-size:.95rem">BMC Admin</div>
        <div style="font-size:.7rem;color:rgba(255,255,255,.45)">Content Management</div>
      </div>
    </div>
  </div>
  <nav class="admin-sidebar-nav">
    <div class="admin-nav-group">
      <div class="admin-nav-group-label">Main</div>
      <a href="<?= BASE_URL ?>/site-admin/index.php" class="admin-nav-link <?= adminActive('index.php') ?>"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
    </div>
    <div class="admin-nav-group">
      <div class="admin-nav-group-label">Content</div>
      <a href="<?= BASE_URL ?>/site-admin/sliders.php"      class="admin-nav-link <?= adminActive('sliders.php') ?>"><i class="fas fa-images"></i>Sliders</a>
      <a href="<?= BASE_URL ?>/site-admin/news.php"         class="admin-nav-link <?= adminActive('news.php') ?>"><i class="fas fa-newspaper"></i>News</a>
      <a href="<?= BASE_URL ?>/site-admin/events.php"       class="admin-nav-link <?= adminActive('events.php') ?>"><i class="fas fa-calendar-alt"></i>Events</a>
      <a href="<?= BASE_URL ?>/site-admin/notices.php"      class="admin-nav-link <?= adminActive('notices.php') ?>"><i class="fas fa-bell"></i>Notices</a>
      <a href="<?= BASE_URL ?>/site-admin/gallery.php"      class="admin-nav-link <?= adminActive('gallery.php') ?>"><i class="fas fa-photo-video"></i>Gallery</a>
      <a href="<?= BASE_URL ?>/site-admin/downloads.php"    class="admin-nav-link <?= adminActive('downloads.php') ?>"><i class="fas fa-download"></i>Downloads</a>
      <a href="<?= BASE_URL ?>/site-admin/testimonials.php" class="admin-nav-link <?= adminActive('testimonials.php') ?>"><i class="fas fa-quote-right"></i>Testimonials</a>
    </div>
    <div class="admin-nav-group">
      <div class="admin-nav-group-label">People</div>
      <a href="<?= BASE_URL ?>/site-admin/faculty.php"  class="admin-nav-link <?= adminActive('faculty.php') ?>"><i class="fas fa-chalkboard-teacher"></i>Faculty</a>
      <a href="<?= BASE_URL ?>/site-admin/admissions.php" class="admin-nav-link <?= adminActive('admissions.php') ?>"><i class="fas fa-graduation-cap"></i>Admissions</a>
    </div>
    <div class="admin-nav-group">
      <div class="admin-nav-group-label">System</div>
      <a href="<?= BASE_URL ?>/site-admin/settings.php" class="admin-nav-link <?= adminActive('settings.php') ?>"><i class="fas fa-cog"></i>Settings</a>
      <a href="<?= BASE_URL ?>/" target="_blank" class="admin-nav-link"><i class="fas fa-external-link-alt"></i>View Site</a>
      <a href="<?= BASE_URL ?>/site-admin/logout.php" class="admin-nav-link" style="color:rgba(255,100,100,.8)"><i class="fas fa-sign-out-alt"></i>Logout</a>
    </div>
  </nav>
  <div style="padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);margin-top:auto">
    <div style="font-size:.76rem;color:rgba(255,255,255,.4)">Logged in as</div>
    <div style="font-size:.85rem;color:rgba(255,255,255,.8);font-weight:600"><?= sh($admin['name'] ?? 'Admin') ?></div>
    <div style="font-size:.72rem;color:rgba(255,255,255,.35)"><?= sh(ucfirst(str_replace('_',' ', $admin['role'] ?? ''))) ?></div>
  </div>
</aside>

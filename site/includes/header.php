<?php
require_once __DIR__ . '/functions.php';
// $pageTitle and $pageDesc must be set before including this file
$_siteName = getSetting('site_name', 'Bahria Model College');
$_tagline  = getSetting('site_tagline', 'Shaping Leaders of Tomorrow');
$_activePage = $activePage ?? '';
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= sh($pageTitle ?? $_siteName) ?> — <?= sh($_siteName) ?></title>
  <meta name="description" content="<?= sh($pageDesc ?? getSetting('about_short')) ?>">
  <meta property="og:title" content="<?= sh($pageTitle ?? $_siteName) ?>">
  <meta property="og:site_name" content="<?= sh($_siteName) ?>">
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
  <!-- Bootstrap 5.3 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome 6 -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <!-- AOS (Animate On Scroll) -->
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <!-- Swiper -->
  <link href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" rel="stylesheet">
  <!-- GLightbox -->
  <link href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" rel="stylesheet">
  <!-- Site CSS -->
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- ══ Loading Screen ══ -->
<div id="site-loader">
  <div class="loader-inner">
    <div class="loader-logo">
      <i class="fas fa-landmark"></i>
      <span>BMC</span>
    </div>
    <div class="loader-bar"><div class="loader-bar-fill"></div></div>
  </div>
</div>

<!-- ══ Navbar ══ -->
<nav class="site-nav" id="siteNav">
  <div class="nav-topbar">
    <div class="container-xl d-flex justify-content-between align-items-center">
      <div class="topbar-left d-flex gap-3">
        <a href="mailto:<?= sh(getSetting('site_email','info@bmc.edu.pk')) ?>">
          <i class="fas fa-envelope"></i> <?= sh(getSetting('site_email','info@bmc.edu.pk')) ?>
        </a>
        <a href="tel:<?= sh(getSetting('site_phone')) ?>">
          <i class="fas fa-phone-alt"></i> <?= sh(getSetting('site_phone')) ?>
        </a>
      </div>
      <div class="topbar-right d-flex gap-2 align-items-center">
        <?php $fb = getSetting('site_facebook'); $tw = getSetting('site_twitter'); $ig = getSetting('site_instagram'); $yt = getSetting('site_youtube'); ?>
        <?php if ($fb): ?><a href="<?= sh($fb) ?>" target="_blank"><i class="fab fa-facebook-f"></i></a><?php endif; ?>
        <?php if ($tw): ?><a href="<?= sh($tw) ?>" target="_blank"><i class="fab fa-twitter"></i></a><?php endif; ?>
        <?php if ($ig): ?><a href="<?= sh($ig) ?>" target="_blank"><i class="fab fa-instagram"></i></a><?php endif; ?>
        <?php if ($yt): ?><a href="<?= sh($yt) ?>" target="_blank"><i class="fab fa-youtube"></i></a><?php endif; ?>
        <button class="theme-toggle ms-2" id="themeToggle" title="Toggle dark mode">
          <i class="fas fa-moon" id="themeIcon"></i>
        </button>
      </div>
    </div>
  </div>

  <div class="nav-main">
    <div class="container-xl d-flex justify-content-between align-items-center">
      <!-- Logo -->
      <a href="<?= SITE_URL ?>/index.php" class="site-logo">
        <div class="logo-icon"><i class="fas fa-landmark"></i></div>
        <div class="logo-text">
          <span class="logo-name">BMC</span>
          <span class="logo-sub">Bahria Model College</span>
        </div>
      </a>

      <!-- Desktop Menu -->
      <ul class="nav-links" id="navLinks">
        <li><a href="<?= SITE_URL ?>/index.php" class="<?= $_activePage==='home'?'active':'' ?>">Home</a></li>

        <li class="has-mega">
          <a href="<?= SITE_URL ?>/about.php" class="<?= in_array($_activePage,['about','history','mission','vision','principal','org','campus'])?'active':'' ?>">
            About Us <i class="fas fa-chevron-down ms-1" style="font-size:.65em"></i>
          </a>
          <div class="mega-menu">
            <div class="mega-inner">
              <div class="mega-col">
                <h6>Institution</h6>
                <a href="<?= SITE_URL ?>/about.php?tab=history"><i class="fas fa-history me-2"></i>History</a>
                <a href="<?= SITE_URL ?>/about.php?tab=intro"><i class="fas fa-university me-2"></i>Introduction</a>
                <a href="<?= SITE_URL ?>/about.php?tab=campus"><i class="fas fa-building me-2"></i>Campus</a>
                <a href="<?= SITE_URL ?>/about.php?tab=accreditation"><i class="fas fa-certificate me-2"></i>Accreditation</a>
              </div>
              <div class="mega-col">
                <h6>Our Identity</h6>
                <a href="<?= SITE_URL ?>/about.php?tab=vision"><i class="fas fa-eye me-2"></i>Vision</a>
                <a href="<?= SITE_URL ?>/about.php?tab=mission"><i class="fas fa-bullseye me-2"></i>Mission</a>
                <a href="<?= SITE_URL ?>/about.php?tab=values"><i class="fas fa-heart me-2"></i>Core Values</a>
                <a href="<?= SITE_URL ?>/about.php?tab=principal"><i class="fas fa-user-tie me-2"></i>Principal's Message</a>
              </div>
              <div class="mega-col">
                <h6>Administration</h6>
                <a href="<?= SITE_URL ?>/administration.php"><i class="fas fa-sitemap me-2"></i>Administration</a>
                <a href="<?= SITE_URL ?>/about.php?tab=org"><i class="fas fa-project-diagram me-2"></i>Org Structure</a>
                <a href="<?= SITE_URL ?>/about.php?tab=facilities"><i class="fas fa-building me-2"></i>Facilities</a>
              </div>
            </div>
          </div>
        </li>

        <li class="has-mega">
          <a href="<?= SITE_URL ?>/academics.php" class="<?= in_array($_activePage,['academics','departments','programs'])?'active':'' ?>">
            Academics <i class="fas fa-chevron-down ms-1" style="font-size:.65em"></i>
          </a>
          <div class="mega-menu">
            <div class="mega-inner">
              <div class="mega-col">
                <h6>Programs</h6>
                <a href="<?= SITE_URL ?>/academics.php?tab=departments"><i class="fas fa-university me-2"></i>Departments</a>
                <a href="<?= SITE_URL ?>/academics.php?tab=programs"><i class="fas fa-book me-2"></i>Programs</a>
                <a href="<?= SITE_URL ?>/academics.php?tab=curriculum"><i class="fas fa-list-alt me-2"></i>Curriculum</a>
              </div>
              <div class="mega-col">
                <h6>Academic Info</h6>
                <a href="<?= SITE_URL ?>/academics.php?tab=calendar"><i class="fas fa-calendar me-2"></i>Academic Calendar</a>
                <a href="<?= SITE_URL ?>/academics.php?tab=examination"><i class="fas fa-file-alt me-2"></i>Examination</a>
                <a href="<?= SITE_URL ?>/academics.php?tab=rules"><i class="fas fa-gavel me-2"></i>Rules & Regulations</a>
              </div>
              <div class="mega-col">
                <h6>Resources</h6>
                <a href="<?= SITE_URL ?>/academics.php?tab=library"><i class="fas fa-book-open me-2"></i>Library</a>
                <a href="<?= SITE_URL ?>/academics.php?tab=labs"><i class="fas fa-flask me-2"></i>Laboratories</a>
                <a href="<?= SITE_URL ?>/academics.php?tab=research"><i class="fas fa-microscope me-2"></i>Research</a>
              </div>
            </div>
          </div>
        </li>

        <li><a href="<?= SITE_URL ?>/faculty.php" class="<?= $_activePage==='faculty'?'active':'' ?>">Faculty</a></li>
        <li><a href="<?= SITE_URL ?>/administration.php" class="<?= $_activePage==='administration'?'active':'' ?>">Administration</a></li>
        <li><a href="<?= SITE_URL ?>/admissions.php" class="<?= $_activePage==='admissions'?'active':'' ?>">Admissions</a></li>

        <li class="has-dropdown">
          <a href="#">Student Portal <i class="fas fa-chevron-down ms-1" style="font-size:.65em"></i></a>
          <ul class="dropdown-menu-custom">
            <li><a href="<?= portalUrl('/student/dashboard.php') ?>"><i class="fas fa-tachometer-alt me-2"></i>Student Dashboard</a></li>
            <li><a href="<?= portalUrl('/student/results.php') ?>"><i class="fas fa-chart-bar me-2"></i>Results</a></li>
            <li><a href="<?= portalUrl('/student/attendance.php') ?>"><i class="fas fa-calendar-check me-2"></i>Attendance</a></li>
            <li><a href="<?= portalUrl('/student/timetable.php') ?>"><i class="fas fa-clock me-2"></i>Timetable</a></li>
          </ul>
        </li>

        <li><a href="<?= SITE_URL ?>/downloads.php" class="<?= $_activePage==='downloads'?'active':'' ?>">Downloads</a></li>

        <li class="has-dropdown">
          <a href="#">News <i class="fas fa-chevron-down ms-1" style="font-size:.65em"></i></a>
          <ul class="dropdown-menu-custom">
            <li><a href="<?= SITE_URL ?>/news.php"><i class="fas fa-newspaper me-2"></i>Latest News</a></li>
            <li><a href="<?= SITE_URL ?>/events.php"><i class="fas fa-calendar-alt me-2"></i>Events</a></li>
            <li><a href="<?= SITE_URL ?>/notices.php"><i class="fas fa-bell me-2"></i>Notices</a></li>
          </ul>
        </li>

        <li><a href="<?= SITE_URL ?>/gallery.php" class="<?= $_activePage==='gallery'?'active':'' ?>">Gallery</a></li>
        <li><a href="<?= SITE_URL ?>/careers.php" class="<?= $_activePage==='careers'?'active':'' ?>">Careers</a></li>
        <li><a href="<?= SITE_URL ?>/contact.php" class="<?= $_activePage==='contact'?'active':'' ?>">Contact</a></li>
      </ul>

      <!-- Right controls -->
      <div class="nav-actions">
        <a href="<?= SITE_URL ?>/search.php" class="nav-search-btn" id="searchToggle"><i class="fas fa-search"></i></a>
        <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
      </div>
    </div>
  </div>

  <!-- Search overlay -->
  <div class="search-overlay" id="searchOverlay">
    <button class="search-close" id="searchClose"><i class="fas fa-times"></i></button>
    <form action="<?= SITE_URL ?>/search.php" method="GET" class="search-form">
      <input type="text" name="q" placeholder="Search BMC…" autocomplete="off" id="searchInput">
      <button type="submit"><i class="fas fa-search"></i></button>
    </form>
  </div>
</nav>
<!-- ══ /Navbar ══ -->

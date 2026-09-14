<?php
require_once __DIR__ . '/functions.php';
// $pageTitle and $pageDesc must be set before including this file
$_siteName   = getSetting('site_name', 'Bahria Model College');
$_tagline    = getSetting('site_tagline', 'Shaping Leaders of Tomorrow');
$_activePage = $activePage ?? '';
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= sh($pageTitle ?? $_siteName) ?> — <?= sh($_siteName) ?></title>
  <meta name="description" content="<?= sh($pageDesc ?? getSetting('about_short', '')) ?>">
  <meta property="og:title"     content="<?= sh($pageTitle ?? $_siteName) ?>">
  <meta property="og:site_name" content="<?= sh($_siteName) ?>">
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/bmc-logo.png">
  <!-- Inter font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Bootstrap 5.3 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome 6.5 -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <!-- AOS -->
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
  <div class="loader-spinner"></div>
</div>

<!-- ══ Navbar ══ -->
<nav class="site-nav" id="siteNav">

  <!-- Topbar -->
  <div class="nav-topbar">
    <div class="container-xl d-flex justify-content-between align-items-center">
      <div class="topbar-left d-flex gap-3">
        <a href="mailto:<?= sh(getSetting('site_email', 'info@bmc.edu.pk')) ?>">
          <i class="fas fa-envelope me-1"></i><?= sh(getSetting('site_email', 'info@bmc.edu.pk')) ?>
        </a>
        <?php $phone = getSetting('site_phone'); if ($phone): ?>
        <span class="topbar-sep">|</span>
        <a href="tel:<?= sh($phone) ?>">
          <i class="fas fa-phone-alt me-1"></i><?= sh($phone) ?>
        </a>
        <?php endif; ?>
      </div>
      <div class="topbar-right">
        <?php
          $fb = getSetting('site_facebook');
          $tw = getSetting('site_twitter');
          $ig = getSetting('site_instagram');
          $yt = getSetting('site_youtube');
        ?>
        <?php if ($fb): ?><a href="<?= sh($fb) ?>" target="_blank" rel="noopener" title="Facebook"><i class="fab fa-facebook-f"></i></a><?php endif; ?>
        <?php if ($tw): ?><a href="<?= sh($tw) ?>" target="_blank" rel="noopener" title="Twitter"><i class="fab fa-twitter"></i></a><?php endif; ?>
        <?php if ($ig): ?><a href="<?= sh($ig) ?>" target="_blank" rel="noopener" title="Instagram"><i class="fab fa-instagram"></i></a><?php endif; ?>
        <?php if ($yt): ?><a href="<?= sh($yt) ?>" target="_blank" rel="noopener" title="YouTube"><i class="fab fa-youtube"></i></a><?php endif; ?>
        <button class="theme-toggle ms-2" id="themeToggle" title="Toggle dark mode" type="button">
          <i class="fas fa-moon" id="themeIcon"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- Main nav -->
  <div class="nav-main">
    <div class="container-xl d-flex align-items-center h-100 gap-2">

      <!-- Logo -->
      <a href="<?= SITE_URL ?>/index.php" class="site-logo me-2">
        <img src="<?= BASE_URL ?>/assets/bmc-logo.png" alt="BMC Logo" class="site-logo-img">
        <div>
          <span class="logo-name">BMC</span>
          <span class="logo-sub"><?= sh($_siteName) ?></span>
        </div>
      </a>

      <!-- Desktop nav links -->
      <ul class="nav-links" id="navLinks">

        <li>
          <a href="<?= SITE_URL ?>/index.php"
             class="<?= $_activePage === 'home' ? 'active' : '' ?>">Home</a>
        </li>

        <!-- About Us mega -->
        <li class="has-mega">
          <a href="<?= SITE_URL ?>/about.php"
             class="<?= in_array($_activePage, ['about','history','mission','vision','principal','org','campus','administration']) ? 'active' : '' ?>">
            About <i class="fas fa-chevron-down nav-caret"></i>
          </a>
          <div class="mega-menu">
            <div class="mega-inner">
              <div class="mega-col">
                <h6>Institution</h6>
                <a href="<?= SITE_URL ?>/about.php?tab=history"><i class="fas fa-history"></i>History</a>
                <a href="<?= SITE_URL ?>/about.php?tab=intro"><i class="fas fa-university"></i>Introduction</a>
                <a href="<?= SITE_URL ?>/about.php?tab=campus"><i class="fas fa-building"></i>Campus</a>
                <a href="<?= SITE_URL ?>/about.php?tab=accreditation"><i class="fas fa-certificate"></i>Accreditation</a>
                <a href="<?= SITE_URL ?>/about.php?tab=facilities"><i class="fas fa-building"></i>Facilities</a>
              </div>
              <div class="mega-col">
                <h6>Our Identity</h6>
                <a href="<?= SITE_URL ?>/about.php?tab=vision"><i class="fas fa-eye"></i>Vision</a>
                <a href="<?= SITE_URL ?>/about.php?tab=mission"><i class="fas fa-bullseye"></i>Mission</a>
                <a href="<?= SITE_URL ?>/about.php?tab=values"><i class="fas fa-heart"></i>Core Values</a>
                <a href="<?= SITE_URL ?>/about.php?tab=principal"><i class="fas fa-user-tie"></i>Principal's Message</a>
              </div>
              <div class="mega-col">
                <h6>Administration</h6>
                <a href="<?= SITE_URL ?>/administration.php"><i class="fas fa-sitemap"></i>Administration</a>
                <a href="<?= SITE_URL ?>/about.php?tab=org"><i class="fas fa-project-diagram"></i>Org Structure</a>
                <a href="<?= SITE_URL ?>/careers.php"><i class="fas fa-briefcase"></i>Careers</a>
                <a href="<?= SITE_URL ?>/contact.php"><i class="fas fa-envelope"></i>Contact Us</a>
              </div>
            </div>
          </div>
        </li>

        <!-- Academics mega -->
        <li class="has-mega">
          <a href="<?= SITE_URL ?>/academics.php"
             class="<?= in_array($_activePage, ['academics','departments','programs']) ? 'active' : '' ?>">
            Academics <i class="fas fa-chevron-down nav-caret"></i>
          </a>
          <div class="mega-menu">
            <div class="mega-inner">
              <div class="mega-col">
                <h6>Programs</h6>
                <a href="<?= SITE_URL ?>/academics.php?tab=departments"><i class="fas fa-university"></i>Departments</a>
                <a href="<?= SITE_URL ?>/academics.php?tab=programs"><i class="fas fa-book"></i>Programs</a>
                <a href="<?= SITE_URL ?>/academics.php?tab=curriculum"><i class="fas fa-list-alt"></i>Curriculum</a>
              </div>
              <div class="mega-col">
                <h6>Academic Info</h6>
                <a href="<?= SITE_URL ?>/academics.php?tab=calendar"><i class="fas fa-calendar"></i>Academic Calendar</a>
                <a href="<?= SITE_URL ?>/academics.php?tab=examination"><i class="fas fa-file-alt"></i>Examination</a>
                <a href="<?= SITE_URL ?>/academics.php?tab=rules"><i class="fas fa-gavel"></i>Rules &amp; Regulations</a>
              </div>
              <div class="mega-col">
                <h6>Resources</h6>
                <a href="<?= SITE_URL ?>/academics.php?tab=library"><i class="fas fa-book-open"></i>Library</a>
                <a href="<?= SITE_URL ?>/academics.php?tab=labs"><i class="fas fa-flask"></i>Laboratories</a>
                <a href="<?= SITE_URL ?>/academics.php?tab=research"><i class="fas fa-microscope"></i>Research</a>
              </div>
            </div>
          </div>
        </li>

        <li>
          <a href="<?= SITE_URL ?>/faculty.php"
             class="<?= $_activePage === 'faculty' ? 'active' : '' ?>">Faculty</a>
        </li>

        <li>
          <a href="<?= SITE_URL ?>/admissions.php"
             class="<?= $_activePage === 'admissions' ? 'active' : '' ?>">Admissions</a>
        </li>

        <!-- Portal link -->
        <li>
          <a href="<?= BASE_URL ?>/portal/"
             class="<?= $_activePage === 'portal' ? 'active' : '' ?>">
            Portal
          </a>
        </li>

        <!-- News dropdown -->
        <li class="has-dropdown">
          <a href="<?= SITE_URL ?>/news.php"
             class="<?= in_array($_activePage, ['news','events','notices']) ? 'active' : '' ?>">
            News <i class="fas fa-chevron-down nav-caret"></i>
          </a>
          <ul class="dropdown-menu-custom dropdown-right">
            <li><a href="<?= SITE_URL ?>/news.php"><i class="fas fa-newspaper"></i>Latest News</a></li>
            <li><a href="<?= SITE_URL ?>/events.php"><i class="fas fa-calendar-alt"></i>Events</a></li>
            <li><a href="<?= SITE_URL ?>/notices.php"><i class="fas fa-bell"></i>Notices</a></li>
          </ul>
        </li>

        <li>
          <a href="<?= SITE_URL ?>/gallery.php"
             class="<?= $_activePage === 'gallery' ? 'active' : '' ?>">Gallery</a>
        </li>

        <li>
          <a href="<?= SITE_URL ?>/downloads.php"
             class="<?= $_activePage === 'downloads' ? 'active' : '' ?>">Downloads</a>
        </li>

        <li>
          <a href="<?= SITE_URL ?>/contact.php"
             class="<?= $_activePage === 'contact' ? 'active' : '' ?>">Contact</a>
        </li>

      </ul>

      <!-- Right actions -->
      <div class="nav-actions ms-auto">
        <a href="<?= SITE_URL ?>/search.php" class="search-toggle" id="searchToggle" title="Search (Ctrl+K)">
          <i class="fas fa-search"></i>
        </a>
        <button class="hamburger" id="hamburger" type="button" aria-label="Toggle menu">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </div>

  <!-- Search Overlay -->
  <div id="searchOverlay">
    <button class="search-close" id="searchClose" type="button"><i class="fas fa-times"></i></button>
    <form action="<?= SITE_URL ?>/search.php" method="GET" class="search-form">
      <input type="text" name="q" id="searchInput"
             placeholder="Search BMC — press Enter…" autocomplete="off">
      <button type="submit"><i class="fas fa-search"></i></button>
    </form>
  </div>
</nav>
<!-- ══ /Navbar ══ -->

<!-- ══ Mobile Menu (slide-in drawer) ══ -->
<div class="mobile-menu" id="mobileMenu">
  <div class="mobile-menu-inner">

    <div class="mobile-menu-header">
      <a href="<?= SITE_URL ?>/index.php" class="d-flex align-items-center gap-2">
        <div class="logo-icon" style="width:32px;height:32px;font-size:.9rem"><i class="fas fa-landmark"></i></div>
        <span style="font-weight:700;color:var(--primary)">BMC</span>
      </a>
      <button class="mobile-menu-close" id="mobileMenuClose" type="button"><i class="fas fa-times"></i></button>
    </div>

    <ul class="mobile-nav-list">

      <li>
        <a href="<?= SITE_URL ?>/index.php" class="mobile-nav-link <?= $_activePage === 'home' ? 'active' : '' ?>">
          <i class="fas fa-home"></i>Home
        </a>
      </li>

      <li class="mobile-has-sub">
        <button class="mobile-nav-toggle" type="button">
          <span><i class="fas fa-info-circle"></i>About BMC</span>
          <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <ul class="mobile-sub-list">
          <li><a href="<?= SITE_URL ?>/about.php">Overview</a></li>
          <li><a href="<?= SITE_URL ?>/about.php?tab=history">History</a></li>
          <li><a href="<?= SITE_URL ?>/about.php?tab=vision">Vision &amp; Mission</a></li>
          <li><a href="<?= SITE_URL ?>/about.php?tab=principal">Principal's Message</a></li>
          <li><a href="<?= SITE_URL ?>/about.php?tab=campus">Campus</a></li>
          <li><a href="<?= SITE_URL ?>/administration.php">Administration</a></li>
          <li><a href="<?= SITE_URL ?>/about.php?tab=facilities">Facilities</a></li>
        </ul>
      </li>

      <li class="mobile-has-sub">
        <button class="mobile-nav-toggle" type="button">
          <span><i class="fas fa-graduation-cap"></i>Academics</span>
          <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <ul class="mobile-sub-list">
          <li><a href="<?= SITE_URL ?>/academics.php">Overview</a></li>
          <li><a href="<?= SITE_URL ?>/academics.php?tab=departments">Departments</a></li>
          <li><a href="<?= SITE_URL ?>/academics.php?tab=programs">Programs</a></li>
          <li><a href="<?= SITE_URL ?>/academics.php?tab=calendar">Academic Calendar</a></li>
          <li><a href="<?= SITE_URL ?>/academics.php?tab=examination">Examination</a></li>
          <li><a href="<?= SITE_URL ?>/academics.php?tab=library">Library</a></li>
          <li><a href="<?= SITE_URL ?>/academics.php?tab=labs">Laboratories</a></li>
        </ul>
      </li>

      <li>
        <a href="<?= SITE_URL ?>/faculty.php" class="mobile-nav-link <?= $_activePage === 'faculty' ? 'active' : '' ?>">
          <i class="fas fa-chalkboard-teacher"></i>Faculty
        </a>
      </li>

      <li>
        <a href="<?= SITE_URL ?>/admissions.php" class="mobile-nav-link <?= $_activePage === 'admissions' ? 'active' : '' ?>">
          <i class="fas fa-user-plus"></i>Admissions
        </a>
      </li>

      <li>
        <a href="<?= BASE_URL ?>/portal/" class="mobile-portal-link">
          <i class="fas fa-sign-in-alt"></i> Portal Login
        </a>
      </li>

      <li class="mobile-has-sub">
        <button class="mobile-nav-toggle" type="button">
          <span><i class="fas fa-newspaper"></i>News &amp; Events</span>
          <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <ul class="mobile-sub-list">
          <li><a href="<?= SITE_URL ?>/news.php">Latest News</a></li>
          <li><a href="<?= SITE_URL ?>/events.php">Events</a></li>
          <li><a href="<?= SITE_URL ?>/notices.php">Notices</a></li>
        </ul>
      </li>

      <li>
        <a href="<?= SITE_URL ?>/gallery.php" class="mobile-nav-link <?= $_activePage === 'gallery' ? 'active' : '' ?>">
          <i class="fas fa-images"></i>Gallery
        </a>
      </li>

      <li>
        <a href="<?= SITE_URL ?>/downloads.php" class="mobile-nav-link <?= $_activePage === 'downloads' ? 'active' : '' ?>">
          <i class="fas fa-download"></i>Downloads
        </a>
      </li>

      <li>
        <a href="<?= SITE_URL ?>/careers.php" class="mobile-nav-link <?= $_activePage === 'careers' ? 'active' : '' ?>">
          <i class="fas fa-briefcase"></i>Careers
        </a>
      </li>

      <li>
        <a href="<?= SITE_URL ?>/contact.php" class="mobile-nav-link <?= $_activePage === 'contact' ? 'active' : '' ?>">
          <i class="fas fa-envelope"></i>Contact
        </a>
      </li>

    </ul>

    <div class="mobile-menu-footer">
      <a href="<?= SITE_URL ?>/admissions.php" class="btn-primary-custom w-100 justify-content-center">
        <i class="fas fa-graduation-cap"></i> Apply for Admissions
      </a>
    </div>
  </div>
</div>
<div class="mobile-menu-overlay" id="mobileOverlay"></div>
<!-- ══ /Mobile Menu ══ -->

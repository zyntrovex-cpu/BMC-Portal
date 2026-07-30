<?php require_once __DIR__ . '/functions.php'; ?>
<!-- ══ Footer ══ -->
<footer class="site-footer">
  <div class="footer-top">
    <div class="container-xl">
      <div class="row g-4">
        <!-- Brand -->
        <div class="col-lg-4">
          <div class="footer-brand">
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="footer-logo-icon"><i class="fas fa-landmark"></i></div>
              <div>
                <div class="footer-logo-name">BMC</div>
                <div class="footer-logo-sub"><?= sh(getSetting('site_name','Bahria Model College')) ?></div>
              </div>
            </div>
            <p><?= sh(getSetting('footer_text','')) ?></p>
            <div class="footer-socials">
              <?php $fb=$_=$tw=$ig=$yt='';
                $fb=getSetting('site_facebook'); $tw=getSetting('site_twitter');
                $ig=getSetting('site_instagram'); $yt=getSetting('site_youtube'); ?>
              <?php if($fb): ?><a href="<?=sh($fb)?>" target="_blank"><i class="fab fa-facebook-f"></i></a><?php endif; ?>
              <?php if($tw): ?><a href="<?=sh($tw)?>" target="_blank"><i class="fab fa-twitter"></i></a><?php endif; ?>
              <?php if($ig): ?><a href="<?=sh($ig)?>" target="_blank"><i class="fab fa-instagram"></i></a><?php endif; ?>
              <?php if($yt): ?><a href="<?=sh($yt)?>" target="_blank"><i class="fab fa-youtube"></i></a><?php endif; ?>
            </div>
          </div>
        </div>
        <!-- Quick Links -->
        <div class="col-lg-2 col-md-4 col-6">
          <h6 class="footer-heading">Quick Links</h6>
          <ul class="footer-links">
            <li><a href="<?= SITE_URL ?>/index.php">Home</a></li>
            <li><a href="<?= SITE_URL ?>/about.php">About Us</a></li>
            <li><a href="<?= SITE_URL ?>/academics.php">Academics</a></li>
            <li><a href="<?= SITE_URL ?>/faculty.php">Faculty</a></li>
            <li><a href="<?= SITE_URL ?>/admissions.php">Admissions</a></li>
            <li><a href="<?= SITE_URL ?>/gallery.php">Gallery</a></li>
            <li><a href="<?= SITE_URL ?>/contact.php">Contact</a></li>
          </ul>
        </div>
        <!-- Student -->
        <div class="col-lg-2 col-md-4 col-6">
          <h6 class="footer-heading">Students</h6>
          <ul class="footer-links">
            <li><a href="<?= portalUrl('/student/dashboard.php') ?>">Student Portal</a></li>
            <li><a href="<?= portalUrl('/student/results.php') ?>">Results</a></li>
            <li><a href="<?= portalUrl('/student/attendance.php') ?>">Attendance</a></li>
            <li><a href="<?= portalUrl('/student/timetable.php') ?>">Timetable</a></li>
            <li><a href="<?= SITE_URL ?>/notices.php">Notices</a></li>
            <li><a href="<?= SITE_URL ?>/downloads.php">Downloads</a></li>
            <li><a href="<?= SITE_URL ?>/news.php">News</a></li>
          </ul>
        </div>
        <!-- Academics -->
        <div class="col-lg-2 col-md-4 col-6">
          <h6 class="footer-heading">Academics</h6>
          <ul class="footer-links">
            <?php foreach(getDepartments() as $d): ?>
            <li><a href="<?= SITE_URL ?>/academics.php?dept=<?= $d['id'] ?>"><?= sh($d['name']) ?></a></li>
            <?php endforeach; ?>
            <li><a href="<?= SITE_URL ?>/academics.php?tab=calendar">Academic Calendar</a></li>
          </ul>
        </div>
        <!-- Contact -->
        <div class="col-lg-2 col-md-4 col-6">
          <h6 class="footer-heading">Contact Us</h6>
          <ul class="footer-contact">
            <li><i class="fas fa-map-marker-alt"></i><?= sh(getSetting('site_address')) ?></li>
            <li><i class="fas fa-phone-alt"></i><a href="tel:<?=sh(getSetting('site_phone'))?>"><?= sh(getSetting('site_phone')) ?></a></li>
            <li><i class="fas fa-envelope"></i><a href="mailto:<?=sh(getSetting('site_email'))?>"><?= sh(getSetting('site_email')) ?></a></li>
          </ul>
          <a href="<?= SITE_URL ?>/admissions.php" class="btn-footer-cta">
            <i class="fas fa-graduation-cap me-2"></i>Apply Now
          </a>
        </div>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container-xl d-flex flex-wrap justify-content-between align-items-center gap-2">
      <p class="mb-0">© <?= date('Y') ?> <?= sh(getSetting('site_name','Bahria Model College')) ?>. All Rights Reserved.</p>
      <div class="d-flex gap-3">
        <a href="<?= SITE_URL ?>/admin/login.php">Admin</a>
        <a href="<?= SITE_URL ?>/contact.php">Privacy Policy</a>
        <a href="<?= SITE_URL ?>/search.php">Sitemap</a>
      </div>
    </div>
  </div>
</footer>
<!-- ══ Back to Top ══ -->
<button id="backToTop" title="Back to top"><i class="fas fa-chevron-up"></i></button>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body></html>

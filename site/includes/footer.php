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
                <div class="footer-logo-sub"><?= sh(getSetting('site_name', 'Bahria Model College')) ?></div>
              </div>
            </div>
            <p><?= sh(getSetting('footer_text', 'Bahria Model College is dedicated to academic excellence and the holistic development of every student.')) ?></p>
            <div class="footer-socials">
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
            </div>
            <a href="<?= SITE_URL ?>/admissions.php" class="btn-footer-cta">
              <i class="fas fa-graduation-cap"></i> Apply Now
            </a>
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

        <!-- Students -->
        <div class="col-lg-2 col-md-4 col-6">
          <h6 class="footer-heading">Students</h6>
          <ul class="footer-links">
            <li><a href="<?= BASE_URL ?>/student/dashboard.php">Student Portal</a></li>
            <li><a href="<?= BASE_URL ?>/student/results.php">Results</a></li>
            <li><a href="<?= BASE_URL ?>/student/attendance.php">Attendance</a></li>
            <li><a href="<?= BASE_URL ?>/student/timetable.php">Timetable</a></li>
            <li><a href="<?= SITE_URL ?>/notices.php">Notices</a></li>
            <li><a href="<?= SITE_URL ?>/downloads.php">Downloads</a></li>
            <li><a href="<?= SITE_URL ?>/news.php">News &amp; Events</a></li>
          </ul>
        </div>

        <!-- Academics -->
        <div class="col-lg-2 col-md-4 col-6">
          <h6 class="footer-heading">Academics</h6>
          <ul class="footer-links">
            <?php foreach (getDepartments() as $d): ?>
            <li><a href="<?= SITE_URL ?>/academics.php?dept=<?= (int)$d['id'] ?>"><?= sh($d['name']) ?></a></li>
            <?php endforeach; ?>
            <li><a href="<?= SITE_URL ?>/academics.php?tab=calendar">Academic Calendar</a></li>
          </ul>
        </div>

        <!-- Contact -->
        <div class="col-lg-2 col-md-4 col-6">
          <h6 class="footer-heading">Contact Us</h6>
          <ul class="footer-contact">
            <?php $addr = getSetting('site_address'); if ($addr): ?>
            <li><i class="fas fa-map-marker-alt"></i><span><?= sh($addr) ?></span></li>
            <?php endif; ?>
            <?php $phone = getSetting('site_phone'); if ($phone): ?>
            <li><i class="fas fa-phone-alt"></i><a href="tel:<?= sh($phone) ?>"><?= sh($phone) ?></a></li>
            <?php endif; ?>
            <?php $email = getSetting('site_email'); if ($email): ?>
            <li><i class="fas fa-envelope"></i><a href="mailto:<?= sh($email) ?>"><?= sh($email) ?></a></li>
            <?php endif; ?>
          </ul>
        </div>

      </div>
    </div>
  </div>

  <div class="footer-bottom">
    <div class="container-xl d-flex flex-wrap justify-content-between align-items-center gap-2">
      <p class="mb-0">
        &copy; <?= date('Y') ?> <?= sh(getSetting('site_name', 'Bahria Model College')) ?>. All Rights Reserved.
      </p>
      <div class="d-flex gap-3">
        <a href="<?= SITE_URL ?>/admin/login.php">Admin</a>
        <a href="<?= SITE_URL ?>/contact.php">Privacy Policy</a>
        <a href="<?= SITE_URL ?>/search.php">Sitemap</a>
      </div>
    </div>
  </div>
</footer>
<!-- ══ /Footer ══ -->

<!-- ══ Back to Top ══ -->
<button id="backToTop" title="Back to top" type="button">
  <i class="fas fa-chevron-up"></i>
</button>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>

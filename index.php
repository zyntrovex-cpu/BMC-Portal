<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle  = getSetting('site_name');
$pageDesc   = getSetting('about_short');
$activePage = 'home';
include __DIR__ . '/includes/header.php';

$sliders      = getSliders();
$stats        = getStats();
$news         = getNews(6);
$events       = getEvents(4, true);
$notices      = getNotices(8);
$faculty      = getFaculty(0, 8);
$testimonials = getTestimonials(6);
$downloads    = getDownloads('');
$photos       = getGalleryPhotos(0, 12);
$departments  = getDepartments();
$partners     = getPartners();
$videos       = getVideos(3);

$principalName    = getSetting('principal_name', 'Lt. Cdr. Abu Bakar');
$principalMsg     = getSetting('principal_message');
$principalImg     = getSetting('principal_image');
$vision           = getSetting('vision');
$mission          = getSetting('mission');
$admissionOpen    = getSetting('admission_open', '1') === '1';
?>

<!-- ══ Hero ══════════════════════════════════════════════════════ -->
<section class="hero-section">
  <div class="hero-swiper swiper">
    <div class="swiper-wrapper">
      <?php if (empty($sliders)): // default slides when DB is empty ?>
      <div class="swiper-slide hero-slide">
        <div class="hero-bg"></div>
        <div class="hero-overlay"></div>
        <div class="container-xl hero-content">
          <div class="row">
            <div class="col-lg-7">
              <div class="hero-label"><i class="fas fa-star"></i> Excellence in Education</div>
              <h1 class="hero-title">Shaping <span>Leaders</span><br>of Tomorrow</h1>
              <p class="hero-subtitle">Bahria Model College Bin Qasim is committed to delivering world-class education that nurtures intellectual curiosity, moral character, and lifelong achievement.</p>
              <div class="hero-actions">
                <a href="<?= SITE_URL ?>/admissions.php" class="btn-hero-primary"><i class="fas fa-graduation-cap"></i> Apply Now</a>
                <a href="<?= SITE_URL ?>/about.php" class="btn-hero-secondary"><i class="fas fa-play-circle"></i> Discover BMC</a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="swiper-slide hero-slide">
        <div class="hero-bg" style="background:linear-gradient(135deg,#091a4a 0%,#1a3a8f 60%,#0984e3 100%)"></div>
        <div class="hero-overlay"></div>
        <div class="container-xl hero-content">
          <div class="row">
            <div class="col-lg-7">
              <div class="hero-label"><i class="fas fa-microscope"></i> Academic Excellence</div>
              <h1 class="hero-title">World-Class<br><span>Academic Programs</span></h1>
              <p class="hero-subtitle">Explore our diverse range of science, arts, commerce, and medical programs designed to prepare you for a successful university career and beyond.</p>
              <div class="hero-actions">
                <a href="<?= SITE_URL ?>/academics.php" class="btn-hero-primary"><i class="fas fa-book-open"></i> Explore Programs</a>
                <a href="<?= SITE_URL ?>/faculty.php" class="btn-hero-secondary"><i class="fas fa-chalkboard-teacher"></i> Meet Our Faculty</a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="swiper-slide hero-slide">
        <div class="hero-bg" style="background:linear-gradient(135deg,#003d1f 0%,#006b35 60%,#00b894 100%)"></div>
        <div class="hero-overlay"></div>
        <div class="container-xl hero-content">
          <div class="row">
            <div class="col-lg-7">
              <div class="hero-label"><i class="fas fa-trophy"></i> Student Achievements</div>
              <h1 class="hero-title">Achieve <span>More</span><br>With BMC</h1>
              <p class="hero-subtitle">Our students consistently achieve top results in board examinations and go on to distinguished careers in medicine, engineering, business, and the arts.</p>
              <div class="hero-actions">
                <a href="<?= SITE_URL ?>/news.php" class="btn-hero-primary"><i class="fas fa-newspaper"></i> Latest News</a>
                <a href="<?= SITE_URL ?>/gallery.php" class="btn-hero-secondary"><i class="fas fa-images"></i> View Gallery</a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php else: foreach ($sliders as $s): ?>
      <div class="swiper-slide hero-slide">
        <?php if ($s['image']): ?>
        <img class="hero-bg-img" src="<?= uploadUrl('sliders', sh($s['image'])) ?>" alt="">
        <?php endif; ?>
        <div class="hero-bg" style="opacity:<?= $s['image']?'.4':'1' ?>"></div>
        <div class="hero-overlay"></div>
        <div class="container-xl hero-content">
          <div class="row">
            <div class="col-lg-7">
              <?php if ($s['title']): ?><h1 class="hero-title"><?= sh($s['title']) ?></h1><?php endif; ?>
              <?php if ($s['subtitle']): ?><p class="hero-subtitle"><?= sh($s['subtitle']) ?></p><?php endif; ?>
              <?php if ($s['button_text'] && $s['button_url']): ?>
              <div class="hero-actions">
                <a href="<?= sh($s['button_url']) ?>" class="btn-hero-primary"><?= sh($s['button_text']) ?></a>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
    <div class="swiper-button-next"></div>
    <div class="swiper-button-prev"></div>
    <div class="swiper-pagination"></div>
  </div>
  <!-- Floating stats bar -->
  <div class="hero-stats">
    <div class="container-xl">
      <div class="row">
        <?php foreach ($stats as $st): ?>
        <div class="col hero-stat-item">
          <div class="hero-stat-num counter-num" data-target="<?= (int)$st['value'] ?>">0</div>
          <div class="hero-stat-label"><?= sh($st['label']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ══ Admission Alert ════════════════════════════════════════════ -->
<?php if ($admissionOpen): ?>
<div class="bg-warning text-dark py-2 text-center" style="font-size:.85rem;font-weight:700">
  <i class="fas fa-bullhorn me-2"></i>
  Admissions for <?= sh(getSetting('admission_year','2025-26')) ?> are now open!
  <a href="<?= SITE_URL ?>/admissions.php" class="ms-3 btn btn-dark btn-sm py-0 px-3">Download Form</a>
</div>
<?php endif; ?>

<!-- ══ About + Principal ══════════════════════════════════════════ -->
<section class="site-section">
  <div class="container-xl">
    <div class="row g-5 align-items-center">
      <div class="col-lg-5" data-aos="fade-right">
        <div class="principal-card">
          <div class="principal-photo" style="height:340px;background:linear-gradient(135deg,var(--primary-d),var(--primary-l));display:flex;align-items:center;justify-content:center">
            <?php if ($principalImg): ?>
            <img src="<?= uploadUrl('admins', sh($principalImg)) ?>" alt="<?= sh($principalName) ?>" style="width:100%;height:100%;object-fit:cover">
            <?php else: ?>
            <i class="fas fa-user-tie" style="font-size:6rem;color:rgba(255,255,255,.3)"></i>
            <?php endif; ?>
            <div class="principal-name-badge">
              <div style="font-weight:800;font-size:1rem"><?= sh($principalName) ?></div>
              <div style="font-size:.78rem;opacity:.8">Principal, Bahria Model College Bin Qasim</div>
              <div style="font-size:.72rem;opacity:.65;letter-spacing:.03em">Pakistan Navy</div>
            </div>
          </div>
          <div style="padding:24px">
            <div class="highlight-box">
              <?= sh(truncateText($principalMsg, 260)) ?>
            </div>
            <a href="<?= SITE_URL ?>/about.php?tab=principal" class="btn-primary-custom mt-3 w-100 justify-content-center" style="text-decoration:none">
              Read Full Message <i class="fas fa-arrow-right ms-2"></i>
            </a>
          </div>
        </div>
      </div>
      <div class="col-lg-7" data-aos="fade-left">
        <div class="sec-label"><span>About BMC</span></div>
        <h2 class="sec-title mb-4" style="text-align:left">A Legacy of Excellence<br>in Pakistani Education</h2>
        <p style="color:var(--text-2);line-height:1.8;margin-bottom:20px"><?= sh(getSetting('about_short')) ?></p>
        <!-- VMV tabs -->
        <ul class="nav vmv-tabs" id="vmvTabs">
          <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#vmv-mission">Mission</a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#vmv-vision">Vision</a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#vmv-values">Values</a></li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade show active" id="vmv-mission">
            <p style="color:var(--text-2);line-height:1.7;font-size:.92rem"><?= sh($mission) ?></p>
          </div>
          <div class="tab-pane fade" id="vmv-vision">
            <p style="color:var(--text-2);line-height:1.7;font-size:.92rem"><?= sh($vision) ?></p>
          </div>
          <div class="tab-pane fade" id="vmv-values">
            <div class="row g-2 mt-1">
              <?php foreach (['Integrity','Excellence','Respect','Innovation','Collaboration','Accountability'] as $v): ?>
              <div class="col-6 col-md-4">
                <div style="display:flex;align-items:center;gap:8px;background:var(--light-2);padding:8px 12px;border-radius:8px;font-size:.84rem;font-weight:600;color:var(--primary)">
                  <i class="fas fa-check-circle" style="color:var(--secondary)"></i><?= $v ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="d-flex flex-wrap gap-3 mt-4">
          <a href="<?= SITE_URL ?>/about.php" class="btn-primary-custom" style="text-decoration:none">Learn More <i class="fas fa-arrow-right ms-1"></i></a>
          <a href="<?= SITE_URL ?>/admissions.php" class="btn-outline-custom" style="text-decoration:none">Admissions</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ Stats ══════════════════════════════════════════════════════ -->
<section class="sec-dark py-5" style="background:linear-gradient(135deg,var(--primary-d) 0%,var(--primary) 50%,var(--primary-l) 100%)">
  <div class="container-xl">
    <div class="row g-4">
      <?php foreach ($stats as $st): ?>
      <div class="col-6 col-md-3" data-aos="zoom-in" data-aos-delay="<?= 100 ?>">
        <div class="stat-card">
          <div class="stat-icon"><i class="fas <?= sh($st['icon']) ?>"></i></div>
          <div class="stat-number"><span class="counter-num" data-target="<?= (int)$st['value'] ?>">0</span><span class="stat-suffix"><?= sh($st['suffix'] ?? '+') ?></span></div>
          <div class="stat-label"><?= sh($st['label']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ Why Choose BMC ════════════════════════════════════════════ -->
<section class="site-section sec-alt">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Why Choose Us</span></div>
      <h2 class="sec-title">Why Thousands Choose BMC</h2>
      <p class="sec-subtitle">A proven environment where academic rigor meets character development and modern infrastructure.</p>
    </div>
    <div class="row g-3">
      <?php
      $whys = [
        ['icon'=>'fa-medal','title'=>'Board Top Results','desc'=>'Our students consistently achieve top positions in BIEK and federal board examinations.'],
        ['icon'=>'fa-chalkboard-teacher','title'=>'Expert Faculty','desc'=>'Highly qualified, experienced teachers committed to student success and well-being.'],
        ['icon'=>'fa-flask','title'=>'Modern Laboratories','desc'=>'State-of-the-art physics, chemistry, biology, and computer science labs.'],
        ['icon'=>'fa-book-reader','title'=>'Rich Library','desc'=>'A comprehensive library with 20,000+ volumes, digital resources, and quiet study areas.'],
        ['icon'=>'fa-shield-alt','title'=>'Safe Campus','desc'=>'A secure, disciplined, and respectful environment conducive to learning.'],
        ['icon'=>'fa-trophy','title'=>'Co-Curricular Activities','desc'=>'Sports, arts, debates, clubs, and events that develop every dimension of personality.'],
        ['icon'=>'fa-heart','title'=>'Inclusive Learning','desc'=>'Dedicated ILC for students with special needs — every learner supported and celebrated.'],
        ['icon'=>'fa-satellite-dish','title'=>'Smart Classrooms','desc'=>'Technology-enabled classrooms with projectors and digital learning tools.'],
      ];
      foreach ($whys as $i => $w): ?>
      <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="<?= ($i%4)*80 ?>">
        <div class="why-card">
          <div class="why-icon"><i class="fas <?= $w['icon'] ?>"></i></div>
          <div>
            <div class="why-title"><?= $w['title'] ?></div>
            <div class="why-desc"><?= $w['desc'] ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ Departments ═══════════════════════════════════════════════ -->
<section class="site-section">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Academics</span></div>
      <h2 class="sec-title">Our Departments &amp; Programs</h2>
      <p class="sec-subtitle">Six specialized departments offering diverse programs tailored to your academic and career goals.</p>
    </div>
    <div class="row g-4">
      <?php foreach ($departments as $i => $d): ?>
      <div class="col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= ($i%3)*100 ?>">
        <div class="dept-card">
          <div class="dept-card-icon"><i class="fas <?= sh($d['icon']) ?>"></i></div>
          <div class="dept-card-name"><?= sh($d['name']) ?></div>
          <div class="dept-card-desc"><?= sh(truncateText($d['description'],120)) ?></div>
          <div style="font-size:.78rem;color:var(--text-3);margin-bottom:10px"><i class="fas fa-users me-1"></i><?= (int)$d['faculty_count'] ?> Faculty Members</div>
          <a href="<?= SITE_URL ?>/academics.php?dept=<?= $d['id'] ?>" class="dept-card-link">Explore <i class="fas fa-arrow-right"></i></a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ Facilities ════════════════════════════════════════════════ -->
<section class="site-section sec-alt">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Campus Life</span></div>
      <h2 class="sec-title">World-Class Facilities</h2>
      <p class="sec-subtitle">Our campus provides everything students need to learn, grow, and thrive.</p>
    </div>
    <div class="row g-3">
      <?php
      $facilities = [
        ['name'=>'Science Laboratories','desc'=>'Fully equipped physics, chemistry & biology labs','img'=>'https://images.unsplash.com/photo-1564069114553-7215e1ff1890?w=600&q=80'],
        ['name'=>'Digital Library','desc'=>'20,000+ books and digital learning resources','img'=>'https://images.unsplash.com/photo-1521587760476-6c12a4b040da?w=600&q=80'],
        ['name'=>'Computer Centre','desc'=>'200+ workstations with high-speed internet','img'=>'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=600&q=80'],
        ['name'=>'Sports Complex','desc'=>'Cricket, football, basketball courts & more','img'=>'https://images.unsplash.com/photo-1551698618-1dfe5d97d256?w=600&q=80'],
        ['name'=>'Auditorium','desc'=>'600-seat auditorium for events & conferences','img'=>'https://images.unsplash.com/photo-1501504905252-473c47e087f8?w=600&q=80'],
        ['name'=>'Cafeteria','desc'=>'Hygienic canteen with diverse menu options','img'=>'https://images.unsplash.com/photo-1567521464027-f127ff144326?w=600&q=80'],
      ];
      foreach ($facilities as $i => $f): ?>
      <div class="col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= ($i%3)*80 ?>">
        <div class="facility-card">
          <img src="<?= $f['img'] ?>" alt="<?= $f['name'] ?>">
          <div class="facility-overlay">
            <div class="facility-info">
              <h5><?= $f['name'] ?></h5>
              <p><?= $f['desc'] ?></p>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-5" data-aos="fade-up">
      <a href="<?= SITE_URL ?>/about.php?tab=facilities" class="btn-outline-custom" style="text-decoration:none">View All Facilities</a>
    </div>
  </div>
</section>

<!-- ══ News ══════════════════════════════════════════════════════ -->
<section class="site-section">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Latest Updates</span></div>
      <h2 class="sec-title">News &amp; Announcements</h2>
      <p class="sec-subtitle">Stay updated with the latest news, achievements, and announcements from BMC.</p>
    </div>
    <?php if (empty($news)): ?>
    <div class="row g-4">
      <?php foreach ([['title'=>'BMC Students Achieve Board Top Positions','cat'=>'Achievement','excerpt'=>'Twenty-three students from BMC secured positions in the top 100 of BIEK examinations, a record achievement for the college.','date'=>'2025-07-15'],['title'=>'New Computer Science Lab Inaugurated','cat'=>'Infrastructure','excerpt'=>'A state-of-the-art computer science laboratory with 60 workstations and high-speed internet has been inaugurated on campus.','date'=>'2025-07-10'],['title'=>'Annual Science Exhibition 2025 Highlights','cat'=>'Events','excerpt'=>'The Annual Science Exhibition showcased over 80 projects by students from all departments, drawing praise from visiting experts.','date'=>'2025-07-05'],['title'=>'BMC Wins Inter-College Debate Championship','cat'=>'Sports & Activities','excerpt'=>'The BMC debate team emerged victorious at the Inter-College Debate Championship organized by the Board of Education.','date'=>'2025-06-28'],['title'=>'Scholarship Programme for Deserving Students','cat'=>'Admissions','excerpt'=>'BMC announces merit and need-based scholarships for the academic year 2025-26 under the Bahria Foundation scheme.','date'=>'2025-06-20'],['title'=>'Faculty Development Workshop Held','cat'=>'Faculty','excerpt'=>'A two-day professional development workshop on modern pedagogical techniques was conducted for the teaching faculty.','date'=>'2025-06-12']] as $i => $n): ?>
      <div class="col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= ($i%3)*80 ?>">
        <div class="news-card">
          <div class="news-card-img" style="background:linear-gradient(135deg,var(--primary-d),var(--primary-l));height:180px;display:flex;align-items:center;justify-content:center">
            <i class="fas fa-newspaper" style="font-size:3rem;color:rgba(255,255,255,.2)"></i>
            <div class="news-card-cat"><?= $n['cat'] ?></div>
          </div>
          <div class="news-card-body">
            <div class="news-card-meta"><span><i class="fas fa-calendar-alt me-1"></i><?= date('d M Y', strtotime($n['date'])) ?></span></div>
            <div class="news-card-title"><?= $n['title'] ?></div>
            <div class="news-card-excerpt"><?= $n['excerpt'] ?></div>
            <a href="<?= SITE_URL ?>/news.php" class="news-card-link">Read More <i class="fas fa-arrow-right"></i></a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="row g-4">
      <?php foreach ($news as $i => $n): ?>
      <div class="col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= ($i%3)*80 ?>">
        <div class="news-card">
          <div class="news-card-img">
            <?php if ($n['image']): ?>
            <img src="<?= uploadUrl('news', sh($n['image'])) ?>" alt="<?= sh($n['title']) ?>">
            <?php else: ?>
            <div style="background:linear-gradient(135deg,var(--primary-d),var(--primary-l));height:180px;display:flex;align-items:center;justify-content:center">
              <i class="fas fa-newspaper" style="font-size:3rem;color:rgba(255,255,255,.2)"></i>
            </div>
            <?php endif; ?>
            <div class="news-card-cat"><?= sh($n['category']) ?></div>
          </div>
          <div class="news-card-body">
            <div class="news-card-meta"><span><i class="fas fa-calendar-alt me-1"></i><?= siteDate($n['published_at'] ?: $n['created_at']) ?></span></div>
            <div class="news-card-title"><a href="<?= SITE_URL ?>/news.php?id=<?= $n['id'] ?>"><?= sh($n['title']) ?></a></div>
            <div class="news-card-excerpt"><?= sh(truncateText($n['excerpt'] ?: $n['content'], 120)) ?></div>
            <a href="<?= SITE_URL ?>/news.php?id=<?= $n['id'] ?>" class="news-card-link">Read More <i class="fas fa-arrow-right"></i></a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="text-center mt-5" data-aos="fade-up">
      <a href="<?= SITE_URL ?>/news.php" class="btn-primary-custom" style="text-decoration:none">All News &amp; Announcements <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
  </div>
</section>

<!-- ══ Events + Notice Board (two-column) ════════════════════════ -->
<section class="site-section sec-alt">
  <div class="container-xl">
    <div class="row g-4">
      <!-- Events -->
      <div class="col-lg-6" data-aos="fade-right">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <div class="sec-label" style="justify-content:flex-start"><span>Calendar</span></div>
            <h3 class="sec-title mb-0" style="font-size:1.6rem">Upcoming Events</h3>
          </div>
          <a href="<?= SITE_URL ?>/events.php" class="btn-outline-custom" style="text-decoration:none;font-size:.82rem;padding:7px 16px">View All</a>
        </div>
        <div class="d-flex flex-column gap-3">
          <?php if (empty($events)):
            $demoEvents = [
              ['title'=>'Annual Prize Distribution Ceremony','event_date'=>'2026-09-15','event_time'=>'09:00:00','venue'=>'Main Auditorium'],
              ['title'=>'Inter-House Sports Week','event_date'=>'2026-09-20','event_time'=>'08:00:00','venue'=>'Sports Ground'],
              ['title'=>'Science & Technology Fair','event_date'=>'2026-10-05','event_time'=>'10:00:00','venue'=>'Science Block'],
              ['title'=>'Parent-Teacher Meeting','event_date'=>'2026-10-12','event_time'=>'09:00:00','venue'=>'Main Hall'],
            ];
            foreach ($demoEvents as $e):
          ?>
          <div class="event-card">
            <div class="event-date-badge">
              <div class="event-day"><?= date('d', strtotime($e['event_date'])) ?></div>
              <div class="event-month"><?= date('M', strtotime($e['event_date'])) ?></div>
            </div>
            <div class="event-body">
              <div class="event-title"><?= $e['title'] ?></div>
              <div class="event-meta">
                <span><i class="fas fa-clock me-1"></i><?= date('g:i a', strtotime($e['event_time'])) ?></span>
                <span><i class="fas fa-map-marker-alt me-1"></i><?= $e['venue'] ?></span>
              </div>
            </div>
          </div>
          <?php endforeach;
          else: foreach ($events as $e): ?>
          <div class="event-card">
            <div class="event-date-badge">
              <div class="event-day"><?= date('d', strtotime($e['event_date'])) ?></div>
              <div class="event-month"><?= date('M', strtotime($e['event_date'])) ?></div>
            </div>
            <div class="event-body">
              <div class="event-title"><?= sh($e['title']) ?></div>
              <div class="event-meta">
                <?php if ($e['event_time']): ?><span><i class="fas fa-clock me-1"></i><?= date('g:i a', strtotime($e['event_time'])) ?></span><?php endif; ?>
                <?php if ($e['venue']): ?><span><i class="fas fa-map-marker-alt me-1"></i><?= sh($e['venue']) ?></span><?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
      <!-- Notice Board -->
      <div class="col-lg-6" data-aos="fade-left">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <div class="sec-label" style="justify-content:flex-start"><span>Updates</span></div>
            <h3 class="sec-title mb-0" style="font-size:1.6rem">Notice Board</h3>
          </div>
          <a href="<?= SITE_URL ?>/notices.php" class="btn-outline-custom" style="text-decoration:none;font-size:.82rem;padding:7px 16px">View All</a>
        </div>
        <div class="d-flex flex-column gap-2">
          <?php if (empty($notices)):
            $demoNotices = [
              ['title'=>'Date Sheet — First Year Annual Examination 2025','priority'=>'urgent','created_at'=>'2025-07-20'],
              ['title'=>'Fee Submission Deadline — August 2025','priority'=>'important','created_at'=>'2025-07-18'],
              ['title'=>'Admission Forms Available for 2025-26','priority'=>'important','created_at'=>'2025-07-15'],
              ['title'=>'Library Timings Updated for Summer','priority'=>'normal','created_at'=>'2025-07-12'],
              ['title'=>'Sports Week Schedule Announced','priority'=>'normal','created_at'=>'2025-07-10'],
              ['title'=>'Result Cards Available at Examination Office','priority'=>'normal','created_at'=>'2025-07-08'],
              ['title'=>'College Reopening After Eid Break','priority'=>'normal','created_at'=>'2025-07-05'],
            ];
            foreach ($demoNotices as $n):
          ?>
          <div class="notice-item priority-<?= $n['priority'] ?>">
            <div>
              <div class="notice-title"><?= $n['title'] ?></div>
              <div class="notice-meta"><i class="fas fa-calendar-alt me-1"></i><?= date('d M Y', strtotime($n['created_at'])) ?></div>
            </div>
            <?= priorityBadge($n['priority']) ?>
          </div>
          <?php endforeach;
          else: foreach ($notices as $n): ?>
          <div class="notice-item priority-<?= sh($n['priority']) ?>">
            <div>
              <div class="notice-title"><?= sh($n['title']) ?></div>
              <div class="notice-meta"><i class="fas fa-calendar-alt me-1"></i><?= siteDate($n['created_at']) ?></div>
            </div>
            <div style="display:flex;align-items:center;gap:6px">
              <?= priorityBadge($n['priority']) ?>
              <?php if ($n['attachment']): ?>
              <a href="<?= uploadUrl('notices', sh($n['attachment'])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.7rem"><i class="fas fa-paperclip"></i></a>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ Faculty Highlights ════════════════════════════════════════ -->
<section class="site-section">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Our Team</span></div>
      <h2 class="sec-title">Meet Our Faculty</h2>
      <p class="sec-subtitle">Dedicated educators who inspire, guide, and shape the next generation of leaders.</p>
    </div>
    <?php if (empty($faculty)): ?>
    <div class="row g-4">
      <?php foreach ([['name'=>'Dr. Aisha Siddiqui','role'=>'Head of Science Dept.','dept'=>'Science & Technology'],['name'=>'Prof. Tariq Ahmed','role'=>'Head of Commerce','dept'=>'Commerce & Management'],['name'=>'Dr. Fatima Malik','role'=>'Head of Medical Sciences','dept'=>'Medical Sciences'],['name'=>'Mr. Usman Khan','role'=>'HOD Computer Science','dept'=>'Science & Technology'],['name'=>'Ms. Sara Iqbal','role'=>'Senior Lecturer, English','dept'=>'Arts & Humanities'],['name'=>'Dr. Bilal Hassan','role'=>'Head of Mathematics','dept'=>'Science & Technology'],['name'=>'Ms. Nadia Rehman','role'=>'Senior Lecturer, Physics','dept'=>'Science & Technology'],['name'=>'Prof. Imran Ali','role'=>'Head of Arts Dept.','dept'=>'Arts & Humanities']] as $i => $f): ?>
      <div class="col-6 col-md-3" data-aos="fade-up" data-aos-delay="<?= ($i%4)*60 ?>">
        <div class="faculty-card">
          <div class="faculty-card-img">
            <div style="background:linear-gradient(135deg,#<?= ['1a3a8f','0984e3','00cec9','27ae60','e17055','6c5ce7','fd79a8','00b894'][$i] ?>,#<?= ['0984e3','00cec9','74b9ff','55efc4','fab1a0','a29bfe','fd79a8','55efc4'][$i] ?>);height:200px;display:flex;align-items:center;justify-content:center">
              <i class="fas fa-user-tie" style="font-size:3.5rem;color:rgba(255,255,255,.35)"></i>
            </div>
          </div>
          <div class="faculty-card-body">
            <div class="faculty-card-name"><?= $f['name'] ?></div>
            <div class="faculty-card-role"><?= $f['role'] ?></div>
            <div style="font-size:.73rem;color:var(--text-3);margin-top:2px"><?= $f['dept'] ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="row g-4">
      <?php foreach ($faculty as $i => $f): ?>
      <div class="col-6 col-md-3" data-aos="fade-up" data-aos-delay="<?= ($i%4)*60 ?>">
        <div class="faculty-card">
          <div class="faculty-card-img">
            <?php if ($f['image']): ?>
            <img src="<?= uploadUrl('faculty', sh($f['image'])) ?>" alt="<?= sh($f['name']) ?>">
            <?php else: ?>
            <div style="background:linear-gradient(135deg,var(--primary),var(--primary-l));height:200px;display:flex;align-items:center;justify-content:center"><i class="fas fa-user-tie" style="font-size:3.5rem;color:rgba(255,255,255,.35)"></i></div>
            <?php endif; ?>
          </div>
          <div class="faculty-card-body">
            <div class="faculty-card-name"><?= sh($f['name']) ?></div>
            <div class="faculty-card-role"><?= sh($f['designation']) ?></div>
            <div style="font-size:.73rem;color:var(--text-3);margin-top:2px"><?= sh($f['dept_name'] ?? '') ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="text-center mt-5" data-aos="fade-up">
      <a href="<?= SITE_URL ?>/faculty.php" class="btn-primary-custom" style="text-decoration:none">View All Faculty <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
  </div>
</section>

<!-- ══ Gallery ═══════════════════════════════════════════════════ -->
<?php if (!empty($photos)): ?>
<section class="site-section sec-alt">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Gallery</span></div>
      <h2 class="sec-title">Campus Life in Pictures</h2>
      <p class="sec-subtitle">A glimpse into the vibrant life at Bahria Model College.</p>
    </div>
    <div class="gallery-grid">
      <?php foreach ($photos as $p): ?>
      <div class="gallery-item" data-aos="zoom-in">
        <a href="<?= uploadUrl('gallery', sh($p['filename'])) ?>" class="glightbox" data-gallery="campus">
          <img src="<?= uploadUrl('gallery', sh($p['filename'])) ?>" alt="<?= sh($p['title']) ?>">
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-5" data-aos="fade-up">
      <a href="<?= SITE_URL ?>/gallery.php" class="btn-outline-custom" style="text-decoration:none">View Full Gallery <i class="fas fa-images ms-1"></i></a>
    </div>
  </div>
</section>
<?php else: ?>
<section class="site-section sec-alt">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Gallery</span></div>
      <h2 class="sec-title">Campus Life in Pictures</h2>
    </div>
    <div class="row g-3">
      <?php $galleryColors = ['#1a3a8f','#0984e3','#00cec9','#27ae60','#e17055','#6c5ce7','#fd79a8','#f9ca24','#00b894','#a29bfe','#fab1a0','#55efc4'];
      for ($i = 0; $i < 12; $i++): ?>
      <div class="col-6 col-md-4 col-lg-3" data-aos="zoom-in" data-aos-delay="<?= ($i%4)*50 ?>">
        <div style="background:<?= $galleryColors[$i] ?>;height:160px;border-radius:var(--radius);opacity:.7;display:flex;align-items:center;justify-content:center;overflow:hidden">
          <i class="fas fa-image" style="font-size:2.5rem;color:rgba(255,255,255,.3)"></i>
        </div>
      </div>
      <?php endfor; ?>
    </div>
    <div class="text-center mt-4" data-aos="fade-up">
      <a href="<?= SITE_URL ?>/gallery.php" class="btn-outline-custom" style="text-decoration:none">View Gallery <i class="fas fa-images ms-1"></i></a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══ Testimonials ══════════════════════════════════════════════ -->
<section class="site-section">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Testimonials</span></div>
      <h2 class="sec-title">What Our Community Says</h2>
      <p class="sec-subtitle">Hear from our students, alumni, and parents about the BMC experience.</p>
    </div>
    <div class="swiper testimonials-swiper" data-aos="fade-up">
      <div class="swiper-wrapper">
        <?php $tdata = !empty($testimonials) ? $testimonials : [['name'=>'Ahmed Khan','designation'=>'Alumni 2022','content'=>'BMC gave me the academic foundation to secure admission to NUST. The teachers dedication and the disciplined environment made all the difference.','rating'=>5],['name'=>'Sara Iqbal','designation'=>'Current Student, FSc Pre-Medical','content'=>'The labs, library, and especially the support from my science teachers have been exceptional. Im proud and grateful to be a BMC student.','rating'=>5],['name'=>'Muhammad & Zainab','designation'=>'Parents','content'=>'Our daughter has grown tremendously in both academics and character since joining BMC. The staff truly cares about every child.','rating'=>5],['name'=>'Fatima Siddiqui','designation'=>'Alumni 2020','content'=>'The values and work ethic I developed at BMC shaped my professional life. I owe so much of my success to this institution.','rating'=>5],['name'=>'Bilal Hassan','designation'=>'Alumni 2023','content'=>'Winning the Inter-College Science Competition was only possible because of the world-class labs and mentoring at BMC.','rating'=>5],['name'=>'Mrs. Kiran Naz','designation'=>'Parent','content'=>'My son transformed from a shy boy to a confident young man at BMC. The holistic development here is real and remarkable.','rating'=>5]];
        foreach ($tdata as $t): ?>
        <div class="swiper-slide">
          <div class="testimonial-card">
            <div class="star-rating">
              <?php for ($s=0;$s<(int)($t['rating']??5);$s++) echo '<i class="fas fa-star"></i>'; ?>
            </div>
            <p class="testimonial-text">"<?= sh(is_array($t) ? ($t['content']??'') : $t) ?>"</p>
            <div class="testimonial-author">
              <div class="testimonial-author-icon"><i class="fas fa-user"></i></div>
              <div>
                <div class="testimonial-name"><?= sh($t['name'] ?? '') ?></div>
                <div class="testimonial-role"><?= sh($t['designation'] ?? '') ?></div>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="swiper-pagination testimonials-pagination mt-4" style="position:static;margin-top:24px"></div>
    </div>
  </div>
</section>

<!-- ══ Downloads ═════════════════════════════════════════════════ -->
<section class="site-section sec-alt">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Resources</span></div>
      <h2 class="sec-title">Important Downloads</h2>
      <p class="sec-subtitle">Access forms, schedules, and official documents you need.</p>
    </div>
    <div class="row g-3">
      <?php
      $demoDownloads = [
        ['title'=>'Admission Form 2025-26','category'=>'Admission Forms','filename'=>''],
        ['title'=>'Prospectus 2025','category'=>'Prospectus','filename'=>''],
        ['title'=>'Fee Structure 2025-26','category'=>'Fee Structure','filename'=>''],
        ['title'=>'Academic Calendar 2025-26','category'=>'Academic Calendar','filename'=>''],
        ['title'=>'Rules & Regulations','category'=>'Policies','filename'=>''],
        ['title'=>'Examination Schedule','category'=>'Examination','filename'=>''],
      ];
      $dlist = !empty($downloads) ? array_slice($downloads, 0, 6) : $demoDownloads;
      foreach ($dlist as $i => $dl): ?>
      <div class="col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= ($i%3)*60 ?>">
        <div class="download-item">
          <div class="download-icon pdf"><i class="fas fa-file-pdf"></i></div>
          <div>
            <div class="download-name"><?= sh($dl['title']) ?></div>
            <div class="download-cat"><?= sh($dl['category']) ?></div>
          </div>
          <?php if (!empty($dl['filename'])): ?>
          <a href="<?= uploadUrl('downloads', sh($dl['filename'])) ?>" download class="download-btn"><i class="fas fa-download"></i></a>
          <?php else: ?>
          <a href="<?= SITE_URL ?>/downloads.php" class="download-btn" style="color:var(--text-3);font-size:.75rem">Soon</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-5" data-aos="fade-up">
      <a href="<?= SITE_URL ?>/downloads.php" class="btn-primary-custom" style="text-decoration:none">All Downloads <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
  </div>
</section>

<!-- ══ Clubs & Campus Life ═══════════════════════════════════════ -->
<section class="site-section">
  <div class="container-xl">
    <div class="row g-5 align-items-center">
      <div class="col-lg-5" data-aos="fade-right">
        <div class="sec-label" style="justify-content:flex-start"><span>Campus Life</span></div>
        <h2 class="sec-title" style="text-align:left">Clubs, Societies &amp; Co-Curricular Activities</h2>
        <p style="color:var(--text-2);line-height:1.8;margin-bottom:20px">BMC believes in developing the whole student. Our co-curricular programme offers diverse clubs, sports, arts, and community service opportunities that build leadership, teamwork, and social responsibility.</p>
        <a href="<?= SITE_URL ?>/about.php?tab=campus" class="btn-primary-custom" style="text-decoration:none">Explore Campus Life</a>
      </div>
      <div class="col-lg-7" data-aos="fade-left">
        <div class="row g-3">
          <?php foreach ([['icon'=>'fa-flask','name'=>'Science Club','color'=>'#0984e3','bg'=>'rgba(9,132,227,.1)'],['icon'=>'fa-laptop-code','name'=>'Coding Society','color'=>'#6c5ce7','bg'=>'rgba(108,92,231,.1)'],['icon'=>'fa-palette','name'=>'Arts & Craft','color'=>'#e17055','bg'=>'rgba(225,112,85,.1)'],['icon'=>'fa-microphone','name'=>'Debate Club','color'=>'#27ae60','bg'=>'rgba(39,174,96,.1)'],['icon'=>'fa-futbol','name'=>'Sports Council','color'=>'#f9ca24','bg'=>'rgba(249,202,36,.1)'],['icon'=>'fa-book-open','name'=>'Literary Society','color'=>'#fd79a8','bg'=>'rgba(253,121,168,.1)'],['icon'=>'fa-camera','name'=>'Photography','color'=>'#00cec9','bg'=>'rgba(0,206,201,.1)'],['icon'=>'fa-globe','name'=>'Geography Club','color'=>'#a29bfe','bg'=>'rgba(162,155,254,.1)'],['icon'=>'fa-hands-helping','name'=>'Community Service','color'=>'#00b894','bg'=>'rgba(0,184,148,.1)'],['icon'=>'fa-music','name'=>'Choir & Music','color'=>'#fdcb6e','bg'=>'rgba(253,203,110,.1)'],['icon'=>'fa-quran','name'=>'Islamic Society','color'=>'#2d3436','bg'=>'rgba(45,52,54,.07)'],['icon'=>'fa-seedling','name'=>'Eco Club','color'=>'#55efc4','bg'=>'rgba(85,239,196,.1)'],] as $i => $club): ?>
          <div class="col-4 col-md-3" data-aos="zoom-in" data-aos-delay="<?= ($i%4)*50 ?>">
            <div class="club-card">
              <div class="club-icon" style="background:<?= $club['bg'] ?>;color:<?= $club['color'] ?>"><i class="fas <?= $club['icon'] ?>"></i></div>
              <div style="font-size:.78rem;font-weight:600;color:var(--text)"><?= $club['name'] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ Partners ══════════════════════════════════════════════════ -->
<?php if (!empty($partners)): ?>
<section class="site-section sec-alt py-sm">
  <div class="container-xl">
    <div class="text-center mb-4" data-aos="fade-up">
      <div class="sec-label" style="justify-content:center"><span>Affiliations</span></div>
      <h3 style="font-family:var(--font-heading);color:var(--primary);font-weight:700">Our Partners &amp; Affiliations</h3>
    </div>
    <div class="swiper partners-swiper" data-aos="fade-up">
      <div class="swiper-wrapper">
        <?php foreach ($partners as $p): ?>
        <div class="swiper-slide">
          <div class="partner-logo">
            <?php if ($p['logo']): ?>
            <img src="<?= uploadUrl('partners', sh($p['logo'])) ?>" alt="<?= sh($p['name']) ?>">
            <?php else: ?>
            <span style="font-weight:700;color:var(--text-2);font-size:.85rem"><?= sh($p['name']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══ CTA ════════════════════════════════════════════════════════ -->
<section class="cta-section">
  <div class="container-xl text-center" style="position:relative;z-index:1" data-aos="fade-up">
    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="sec-label" style="justify-content:center;color:rgba(255,255,255,.7)"><span>Join BMC</span></div>
        <h2 style="font-family:var(--font-heading);font-size:clamp(1.8rem,4vw,2.6rem);font-weight:800;color:#fff;margin-bottom:16px">Begin Your Journey to<br>Academic Excellence</h2>
        <p style="color:rgba(255,255,255,.75);margin-bottom:32px;font-size:.95rem;line-height:1.7">Admissions for <?= sh(getSetting('admission_year','2025-26')) ?> are <?= $admissionOpen ? '<strong style="color:var(--accent)">now open</strong>' : 'coming soon' ?>. Download the admission form and take the first step toward a brighter future at BMC.</p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
          <a href="<?= SITE_URL ?>/admissions.php" class="btn-accent" style="text-decoration:none"><i class="fas fa-graduation-cap"></i> Admissions</a>
          <a href="<?= SITE_URL ?>/contact.php" class="btn-hero-secondary" style="text-decoration:none"><i class="fas fa-phone-alt"></i> Contact Us</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ Contact Info + Map ═════════════════════════════════════════ -->
<section class="site-section">
  <div class="container-xl">
    <div class="row g-5 align-items-stretch">
      <div class="col-lg-5" data-aos="fade-right">
        <div class="sec-label" style="justify-content:flex-start"><span>Find Us</span></div>
        <h2 class="sec-title" style="text-align:left;margin-bottom:24px">Get In Touch</h2>
        <div class="contact-info-card mb-4">
          <div class="contact-info-item">
            <div class="contact-info-icon"><i class="fas fa-map-marker-alt"></i></div>
            <div><div style="font-weight:700;margin-bottom:2px">Address</div><div style="opacity:.8;font-size:.9rem"><?= sh(getSetting('site_address')) ?></div></div>
          </div>
          <div class="contact-info-item">
            <div class="contact-info-icon"><i class="fas fa-phone-alt"></i></div>
            <div><div style="font-weight:700;margin-bottom:2px">Phone</div><div style="opacity:.8;font-size:.9rem"><a href="tel:<?=sh(getSetting('site_phone'))?>" style="color:inherit"><?= sh(getSetting('site_phone')) ?></a></div></div>
          </div>
          <div class="contact-info-item" style="margin-bottom:0">
            <div class="contact-info-icon"><i class="fas fa-envelope"></i></div>
            <div><div style="font-weight:700;margin-bottom:2px">Email</div><div style="opacity:.8;font-size:.9rem"><a href="mailto:<?=sh(getSetting('site_email'))?>" style="color:inherit"><?= sh(getSetting('site_email')) ?></a></div></div>
          </div>
        </div>
        <a href="<?= SITE_URL ?>/contact.php" class="btn-primary-custom" style="text-decoration:none">Send a Message <i class="fas fa-arrow-right ms-1"></i></a>
      </div>
      <div class="col-lg-7" data-aos="fade-left">
        <?php $mapEmbed = getSetting('site_map_embed'); ?>
        <?php if ($mapEmbed): ?>
        <div class="map-wrap" style="height:380px">
          <iframe src="<?= sh($mapEmbed) ?>" width="100%" height="380" style="border:0" allowfullscreen="" loading="lazy"></iframe>
        </div>
        <?php else: ?>
        <div class="map-wrap" style="height:380px;background:var(--light-2);display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--text-3)">
          <i class="fas fa-map-marked-alt fa-3x mb-3" style="opacity:.3"></i>
          <p style="font-size:.88rem">Configure Google Maps embed in Admin Panel → Settings</p>
          <a href="https://maps.google.com/?q=Bahria+Model+College+Bin+Qasim+Karachi" target="_blank" class="btn-primary-custom mt-2" style="text-decoration:none;font-size:.82rem">Open in Google Maps</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle  = getSetting('site_name');
$pageDesc   = getSetting('about_short');
$activePage = 'home';
// Mark body as homepage so navbar knows to be transparent
$bodyClass  = 'is-home';
include __DIR__ . '/includes/header.php';

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

$principalName    = getSetting('principal_name', 'Lt. Cdr. Abu Bakar');
$principalDes     = getSetting('principal_designation', 'Lieutenant Commander, Pakistan Navy');
$principalMsg     = getSetting('principal_message');
$principalImg     = getSetting('principal_image');
$vision           = getSetting('vision');
$mission          = getSetting('mission');
$aboutShort       = getSetting('about_short');
$admissionOpen    = getSetting('admission_open', '1') === '1';
$admissionYear    = getSetting('admission_year', '2025-26');
?>

<!-- ══ HERO ════════════════════════════════════════════════════════ -->
<section class="hero-new" id="home">
  <div class="hero-overlay-new"></div>
  <!-- Floating decorative shapes -->
  <div class="hero-shape hero-shape-1"></div>
  <div class="hero-shape hero-shape-2"></div>
  <div class="hero-shape hero-shape-3"></div>
  <div class="container-xl hero-content-new">
    <div class="row">
      <div class="col-lg-8 col-xl-7">
        <div class="hero-eyebrow">
          Bahria Model College Bin Qasim
        </div>
        <h1 class="hero-heading-new">
          Shaping <em>Leaders</em><br>of Tomorrow
        </h1>
        <p class="hero-sub-new">
          Empowering students through academic excellence, character development, innovation and lifelong learning.
        </p>
        <div class="hero-btns">
          <a href="<?= SITE_URL ?>/admissions.php" class="btn-hero-gold">
            <i class="fas fa-graduation-cap"></i> Apply for Admissions
          </a>
          <a href="<?= SITE_URL ?>/about.php" class="btn-hero-outline">
            <i class="fas fa-play-circle"></i> Explore BMC
          </a>
        </div>
      </div>
    </div>
  </div>
  <!-- Scroll indicator -->
  <div class="hero-scroll">
    <span>Scroll</span>
    <div class="hero-scroll-line"></div>
  </div>
</section>

<!-- ══ ADMISSION BANNER ═════════════════════════════════════════ -->
<?php if ($admissionOpen): ?>
<div class="admission-banner">
  <i class="fas fa-bullhorn"></i>
  <span>Admissions for <strong><?= sh($admissionYear) ?></strong> are now open!</span>
  <a href="<?= SITE_URL ?>/admissions.php">Apply Now <i class="fas fa-arrow-right ms-1"></i></a>
</div>
<?php endif; ?>

<!-- ══ STATS ════════════════════════════════════════════════════ -->
<section class="stats-section">
  <div class="container-xl">
    <div class="row g-4">
      <?php foreach ($stats as $i => $st): ?>
      <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
        <div class="stat-card-new">
          <div class="stat-icon-new">
            <i class="fas <?= sh($st['icon']) ?>"></i>
          </div>
          <div class="stat-number-new">
            <span class="counter-num" data-target="<?= (int)$st['value'] ?>">0</span><span class="stat-suffix-new"><?= sh($st['suffix'] ?? '+') ?></span>
          </div>
          <div class="stat-label-new"><?= sh($st['label']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ ABOUT + PRINCIPAL ════════════════════════════════════════ -->
<section class="site-section">
  <div class="container-xl">
    <div class="row g-5 align-items-center">

      <!-- Principal Card -->
      <div class="col-lg-5" data-aos="fade-right">
        <div class="principal-card">
          <div style="position:relative;height:360px;background:linear-gradient(135deg,var(--navy) 0%,var(--navy-light) 100%);display:flex;align-items:center;justify-content:center;overflow:hidden">
            <?php if ($principalImg): ?>
            <img src="<?= uploadUrl('admins', sh($principalImg)) ?>" alt="<?= sh($principalName) ?>"
                 style="width:100%;height:100%;object-fit:cover;object-position:top">
            <?php else: ?>
            <i class="fas fa-user-tie" style="font-size:7rem;color:rgba(255,255,255,.15)"></i>
            <?php endif; ?>
            <div class="principal-name-badge">
              <div style="font-weight:800;font-size:1.05rem;color:#fff"><?= sh($principalName) ?></div>
              <div style="font-size:.78rem;color:rgba(255,255,255,.7);margin-top:2px"><?= sh($principalDes) ?></div>
              <div style="font-size:.7rem;color:var(--gold);margin-top:3px;font-weight:600">Bahria Model College Bin Qasim</div>
            </div>
          </div>
          <div style="padding:28px">
            <div class="highlight-box">
              "<?= sh(truncateText($principalMsg, 240)) ?>"
            </div>
            <a href="<?= SITE_URL ?>/about.php?tab=principal" class="btn-primary-custom mt-4 w-100 justify-content-center" style="text-decoration:none">
              Read Full Message <i class="fas fa-arrow-right ms-2"></i>
            </a>
          </div>
        </div>
      </div>

      <!-- About Content -->
      <div class="col-lg-7" data-aos="fade-left">
        <div class="sec-label" style="justify-content:flex-start">
          <span>About BMC</span>
        </div>
        <h2 class="sec-title mb-4" style="text-align:left">
          A Legacy of Excellence<br>in Pakistani Education
        </h2>
        <p style="color:var(--text-2);line-height:1.85;margin-bottom:24px;font-size:.97rem"><?= sh($aboutShort) ?></p>

        <!-- Mission / Vision / Values Tabs -->
        <ul class="nav vmv-tabs" id="vmvTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <a class="nav-link active" data-bs-toggle="tab" href="#vmv-mission" role="tab">
              <i class="fas fa-bullseye me-1"></i> Mission
            </a>
          </li>
          <li class="nav-item" role="presentation">
            <a class="nav-link" data-bs-toggle="tab" href="#vmv-vision" role="tab">
              <i class="fas fa-eye me-1"></i> Vision
            </a>
          </li>
          <li class="nav-item" role="presentation">
            <a class="nav-link" data-bs-toggle="tab" href="#vmv-values" role="tab">
              <i class="fas fa-heart me-1"></i> Values
            </a>
          </li>
        </ul>
        <div class="tab-content" style="padding:20px 0 8px">
          <div class="tab-pane fade show active" id="vmv-mission" role="tabpanel">
            <p style="color:var(--text-2);line-height:1.8;font-size:.94rem"><?= sh($mission) ?></p>
          </div>
          <div class="tab-pane fade" id="vmv-vision" role="tabpanel">
            <p style="color:var(--text-2);line-height:1.8;font-size:.94rem"><?= sh($vision) ?></p>
          </div>
          <div class="tab-pane fade" id="vmv-values" role="tabpanel">
            <div class="row g-2 mt-1">
              <?php foreach (['Integrity','Excellence','Respect','Innovation','Collaboration','Accountability'] as $v): ?>
              <div class="col-6 col-md-4">
                <div style="display:flex;align-items:center;gap:8px;background:var(--off-white);padding:10px 14px;border-radius:8px;font-size:.85rem;font-weight:700;color:var(--navy);border:1px solid var(--light-2)">
                  <i class="fas fa-check-circle" style="color:var(--green)"></i><?= $v ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-3 mt-4">
          <a href="<?= SITE_URL ?>/about.php" class="btn-primary-custom" style="text-decoration:none">
            Learn More About BMC <i class="fas fa-arrow-right ms-1"></i>
          </a>
          <a href="<?= SITE_URL ?>/admissions.php" class="btn-outline-custom" style="text-decoration:none">
            Admissions
          </a>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ══ WHY CHOOSE BMC ═══════════════════════════════════════════ -->
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
        ['icon'=>'fa-medal',            'title'=>'Board Top Results',         'desc'=>'Our students consistently achieve top positions in BIEK and federal board examinations.'],
        ['icon'=>'fa-chalkboard-teacher','title'=>'Expert Faculty',            'desc'=>'Highly qualified, experienced teachers committed to student success and well-being.'],
        ['icon'=>'fa-flask',            'title'=>'Modern Laboratories',       'desc'=>'State-of-the-art physics, chemistry, biology, and computer science labs.'],
        ['icon'=>'fa-book-reader',      'title'=>'Rich Library',              'desc'=>'A comprehensive library with 20,000+ volumes, digital resources, and quiet study areas.'],
        ['icon'=>'fa-shield-alt',       'title'=>'Safe Campus',               'desc'=>'A secure, disciplined, and respectful environment conducive to learning.'],
        ['icon'=>'fa-trophy',           'title'=>'Co-Curricular Activities',  'desc'=>'Sports, arts, debates, clubs, and events that develop every dimension of personality.'],
        ['icon'=>'fa-heart',            'title'=>'Inclusive Learning',        'desc'=>'Dedicated ILC for students with special needs — every learner supported and celebrated.'],
        ['icon'=>'fa-satellite-dish',   'title'=>'Smart Classrooms',          'desc'=>'Technology-enabled classrooms with projectors and digital learning tools.'],
      ];
      foreach ($whys as $i => $w): ?>
      <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 80 ?>">
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

<!-- ══ DEPARTMENTS / PROGRAMS ═══════════════════════════════════ -->
<section class="site-section">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Academics</span></div>
      <h2 class="sec-title">Our Departments &amp; Programs</h2>
      <p class="sec-subtitle">Six specialized departments offering diverse programs tailored to your academic and career goals.</p>
    </div>
    <div class="row g-4">
      <?php foreach ($departments as $i => $d): ?>
      <div class="col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 100 ?>">
        <div class="dept-card">
          <div class="dept-card-icon"><i class="fas <?= sh($d['icon']) ?>"></i></div>
          <div class="dept-card-name"><?= sh($d['name']) ?></div>
          <div class="dept-card-desc"><?= sh(truncateText($d['description'], 120)) ?></div>
          <?php if ((int)$d['faculty_count'] > 0): ?>
          <div style="font-size:.78rem;color:var(--text-3);margin-bottom:14px">
            <i class="fas fa-users me-1"></i><?= (int)$d['faculty_count'] ?> Faculty Members
          </div>
          <?php endif; ?>
          <a href="<?= SITE_URL ?>/academics.php?dept=<?= $d['id'] ?>" class="dept-card-link">
            Explore Programs <i class="fas fa-arrow-right"></i>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-5" data-aos="fade-up">
      <a href="<?= SITE_URL ?>/academics.php" class="btn-outline-custom" style="text-decoration:none">
        Explore All Programs <i class="fas fa-arrow-right ms-1"></i>
      </a>
    </div>
  </div>
</section>

<!-- ══ CAMPUS FACILITIES ════════════════════════════════════════ -->
<section class="site-section sec-alt">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Campus Life</span></div>
      <h2 class="sec-title">World-Class Facilities</h2>
      <p class="sec-subtitle">Our campus provides everything students need to learn, grow, and thrive in an inspiring environment.</p>
    </div>
    <div class="row g-3">
      <?php
      $facilities = [
        ['name'=>'Science Laboratories', 'desc'=>'Fully equipped physics, chemistry & biology labs with modern instruments',
         'img'=>'https://images.unsplash.com/photo-1564069114553-7215e1ff1890?w=600&q=75', 'icon'=>'fa-flask'],
        ['name'=>'Digital Library',      'desc'=>'20,000+ books and digital resources in a quiet, modern setting',
         'img'=>'https://images.unsplash.com/photo-1521587760476-6c12a4b040da?w=600&q=75', 'icon'=>'fa-book-open'],
        ['name'=>'Computer Centre',      'desc'=>'200+ workstations with high-speed internet and latest software',
         'img'=>'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=600&q=75', 'icon'=>'fa-laptop'],
        ['name'=>'Sports Complex',       'desc'=>'Cricket, football, basketball courts and indoor games facilities',
         'img'=>'https://images.unsplash.com/photo-1551698618-1dfe5d97d256?w=600&q=75', 'icon'=>'fa-running'],
        ['name'=>'Auditorium',           'desc'=>'600-seat auditorium for events, conferences, and award ceremonies',
         'img'=>'https://images.unsplash.com/photo-1501504905252-473c47e087f8?w=600&q=75', 'icon'=>'fa-microphone'],
        ['name'=>'Cafeteria',            'desc'=>'Hygienic canteen serving nutritious meals and refreshments',
         'img'=>'https://images.unsplash.com/photo-1567521464027-f127ff144326?w=600&q=75', 'icon'=>'fa-utensils'],
      ];
      foreach ($facilities as $i => $f): ?>
      <div class="col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 80 ?>">
        <div class="facility-card">
          <img src="<?= $f['img'] ?>" alt="<?= $f['name'] ?>" loading="lazy">
          <div class="facility-overlay">
            <div class="facility-info">
              <i class="fas <?= $f['icon'] ?>" style="font-size:1.4rem;color:var(--gold);margin-bottom:8px;display:block"></i>
              <h5><?= $f['name'] ?></h5>
              <p><?= $f['desc'] ?></p>
              <div style="margin-top:10px;font-size:.78rem;color:rgba(255,255,255,.7);font-weight:600">
                Learn More <i class="fas fa-arrow-right ms-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-5" data-aos="fade-up">
      <a href="<?= SITE_URL ?>/about.php?tab=facilities" class="btn-outline-custom" style="text-decoration:none">
        View All Facilities <i class="fas fa-building ms-1"></i>
      </a>
    </div>
  </div>
</section>

<!-- ══ NEWS & ANNOUNCEMENTS ═════════════════════════════════════ -->
<section class="site-section">
  <div class="container-xl">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-5" data-aos="fade-up">
      <div>
        <div class="sec-label" style="justify-content:flex-start"><span>Latest Updates</span></div>
        <h2 class="sec-title mb-0">News &amp; Announcements</h2>
      </div>
      <a href="<?= SITE_URL ?>/news.php" class="btn-outline-custom" style="text-decoration:none;font-size:.85rem;padding:10px 22px">
        All News <i class="fas fa-arrow-right ms-1"></i>
      </a>
    </div>
    <div class="row g-4">
      <?php
      $newsData = !empty($news) ? $news : [
        ['id'=>0,'title'=>'BMC Students Achieve Board Top Positions','category'=>'Achievement','excerpt'=>'Twenty-three students secured top positions in BIEK examinations, a record achievement for the college.','published_at'=>'2026-07-15','created_at'=>'2026-07-15','image'=>null],
        ['id'=>0,'title'=>'New Computer Science Lab Inaugurated','category'=>'Infrastructure','excerpt'=>'A state-of-the-art CS lab with 60 workstations and high-speed internet has been inaugurated.','published_at'=>'2026-07-10','created_at'=>'2026-07-10','image'=>null],
        ['id'=>0,'title'=>'Annual Science Exhibition 2026 Highlights','category'=>'Events','excerpt'=>'The Annual Science Exhibition showcased over 80 projects, drawing praise from visiting experts.','published_at'=>'2026-07-05','created_at'=>'2026-07-05','image'=>null],
        ['id'=>0,'title'=>'BMC Wins Inter-College Debate Championship','category'=>'Activities','excerpt'=>'The BMC debate team emerged victorious at the Inter-College Championship organized by the Board.','published_at'=>'2026-06-28','created_at'=>'2026-06-28','image'=>null],
        ['id'=>0,'title'=>'Scholarship Programme for Deserving Students','category'=>'Admissions','excerpt'=>'BMC announces merit and need-based scholarships for 2025-26 under the Bahria Foundation scheme.','published_at'=>'2026-06-20','created_at'=>'2026-06-20','image'=>null],
        ['id'=>0,'title'=>'Faculty Development Workshop Held','category'=>'Faculty','excerpt'=>'A two-day professional development workshop on modern pedagogy was held for the teaching faculty.','published_at'=>'2026-06-12','created_at'=>'2026-06-12','image'=>null],
      ];
      foreach (array_slice($newsData, 0, 6) as $i => $n): ?>
      <div class="col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 80 ?>">
        <div class="news-card">
          <div class="news-card-img">
            <?php if (!empty($n['image'])): ?>
            <img src="<?= uploadUrl('news', sh($n['image'])) ?>" alt="<?= sh($n['title']) ?>" loading="lazy">
            <?php else: ?>
            <div style="height:200px;background:linear-gradient(135deg,var(--navy) 0%,var(--navy-light) 100%);display:flex;align-items:center;justify-content:center">
              <i class="fas fa-newspaper" style="font-size:3rem;color:rgba(255,255,255,.15)"></i>
            </div>
            <?php endif; ?>
            <div class="news-card-cat"><?= sh($n['category']) ?></div>
          </div>
          <div class="news-card-body">
            <div class="news-card-meta">
              <i class="fas fa-calendar-alt me-1"></i><?= siteDate($n['published_at'] ?: $n['created_at']) ?>
            </div>
            <div class="news-card-title">
              <?php if ($n['id']): ?>
              <a href="<?= SITE_URL ?>/news.php?id=<?= $n['id'] ?>"><?= sh($n['title']) ?></a>
              <?php else: ?><?= sh($n['title']) ?><?php endif; ?>
            </div>
            <div class="news-card-excerpt"><?= sh(truncateText($n['excerpt'] ?: $n['content'] ?? '', 115)) ?></div>
            <a href="<?= $n['id'] ? SITE_URL.'/news.php?id='.$n['id'] : SITE_URL.'/news.php' ?>" class="news-card-link">
              Read More <i class="fas fa-arrow-right"></i>
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ EVENTS + NOTICES (two-column) ════════════════════════════ -->
<section class="site-section sec-alt">
  <div class="container-xl">
    <div class="row g-5">

      <!-- Events -->
      <div class="col-lg-6" data-aos="fade-right">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <div class="sec-label" style="justify-content:flex-start"><span>Calendar</span></div>
            <h3 class="sec-title mb-0" style="font-size:1.7rem">Upcoming Events</h3>
          </div>
          <a href="<?= SITE_URL ?>/events.php" class="btn-outline-custom" style="text-decoration:none;font-size:.82rem;padding:8px 18px">View All</a>
        </div>
        <div class="d-flex flex-column gap-3">
          <?php
          $eventData = !empty($events) ? $events : [
            ['title'=>'Annual Prize Distribution Ceremony','event_date'=>'2026-09-15','event_time'=>'09:00:00','venue'=>'Main Auditorium'],
            ['title'=>'Inter-House Sports Week',          'event_date'=>'2026-09-20','event_time'=>'08:00:00','venue'=>'Sports Ground'],
            ['title'=>'Science & Technology Fair',        'event_date'=>'2026-10-05','event_time'=>'10:00:00','venue'=>'Science Block'],
            ['title'=>'Parent-Teacher Meeting',           'event_date'=>'2026-10-12','event_time'=>'09:00:00','venue'=>'Main Hall'],
          ];
          foreach ($eventData as $e): ?>
          <div class="event-card">
            <div class="event-date-badge">
              <div class="event-day"><?= date('d', strtotime($e['event_date'])) ?></div>
              <div class="event-month"><?= date('M', strtotime($e['event_date'])) ?></div>
            </div>
            <div class="event-body">
              <div class="event-title"><?= sh($e['title']) ?></div>
              <div class="event-meta">
                <?php if (!empty($e['event_time'])): ?>
                <span><i class="fas fa-clock me-1"></i><?= date('g:i a', strtotime($e['event_time'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($e['venue'])): ?>
                <span><i class="fas fa-map-marker-alt me-1"></i><?= sh($e['venue']) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Notice Board -->
      <div class="col-lg-6" data-aos="fade-left">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <div class="sec-label" style="justify-content:flex-start"><span>Updates</span></div>
            <h3 class="sec-title mb-0" style="font-size:1.7rem">Notice Board</h3>
          </div>
          <a href="<?= SITE_URL ?>/notices.php" class="btn-outline-custom" style="text-decoration:none;font-size:.82rem;padding:8px 18px">View All</a>
        </div>
        <div class="d-flex flex-column gap-2">
          <?php
          $noticeData = !empty($notices) ? $notices : [
            ['title'=>'Date Sheet — First Year Annual Examination 2026','priority'=>'urgent',   'created_at'=>'2026-07-20'],
            ['title'=>'Fee Submission Deadline — August 2026',          'priority'=>'important','created_at'=>'2026-07-18'],
            ['title'=>'Admission Forms Available for 2026-27',          'priority'=>'important','created_at'=>'2026-07-15'],
            ['title'=>'Library Timings Updated for Summer',             'priority'=>'normal',   'created_at'=>'2026-07-12'],
            ['title'=>'Sports Week Schedule Announced',                 'priority'=>'normal',   'created_at'=>'2026-07-10'],
            ['title'=>'Result Cards Available at Examination Office',   'priority'=>'normal',   'created_at'=>'2026-07-08'],
            ['title'=>'College Reopening After Eid Break',              'priority'=>'normal',   'created_at'=>'2026-07-05'],
          ];
          foreach ($noticeData as $n):
            $priority = $n['priority'] ?? 'normal';
          ?>
          <div class="notice-item priority-<?= sh($priority) ?>">
            <div style="flex:1;min-width:0">
              <div class="notice-title"><?= sh($n['title']) ?></div>
              <div class="notice-meta"><i class="fas fa-calendar-alt me-1"></i><?= siteDate($n['created_at']) ?></div>
            </div>
            <div style="display:flex;align-items:center;gap:6px;flex-shrink:0">
              <span class="priority-badge priority-<?= sh($priority) ?>"><?= ucfirst($priority) ?></span>
              <?php if (!empty($n['attachment'])): ?>
              <a href="<?= uploadUrl('notices', sh($n['attachment'])) ?>" target="_blank" class="download-btn" style="width:28px;height:28px;border-radius:6px;font-size:.72rem" title="Download"><i class="fas fa-paperclip"></i></a>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ══ FACULTY HIGHLIGHTS ═══════════════════════════════════════ -->
<section class="site-section">
  <div class="container-xl">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-5" data-aos="fade-up">
      <div>
        <div class="sec-label" style="justify-content:flex-start"><span>Our Team</span></div>
        <h2 class="sec-title mb-0">Meet Our Faculty</h2>
      </div>
      <a href="<?= SITE_URL ?>/faculty.php" class="btn-outline-custom" style="text-decoration:none;font-size:.85rem;padding:10px 22px">
        View All Faculty <i class="fas fa-arrow-right ms-1"></i>
      </a>
    </div>
    <div class="row g-4">
      <?php
      $facultyData = !empty($faculty) ? $faculty : [
        ['name'=>'Dr. Aisha Siddiqui',  'designation'=>'Head of Science Dept.',   'dept_name'=>'Science & Technology','image'=>null],
        ['name'=>'Prof. Tariq Ahmed',   'designation'=>'Head of Commerce',        'dept_name'=>'Commerce & Management','image'=>null],
        ['name'=>'Dr. Fatima Malik',    'designation'=>'Head of Medical Sciences','dept_name'=>'Medical Sciences',     'image'=>null],
        ['name'=>'Mr. Usman Khan',      'designation'=>'HOD Computer Science',    'dept_name'=>'Science & Technology','image'=>null],
        ['name'=>'Ms. Sara Iqbal',      'designation'=>'Senior Lecturer, English','dept_name'=>'Arts & Humanities',    'image'=>null],
        ['name'=>'Dr. Bilal Hassan',    'designation'=>'Head of Mathematics',     'dept_name'=>'Science & Technology','image'=>null],
        ['name'=>'Ms. Nadia Rehman',    'designation'=>'Senior Lecturer, Physics','dept_name'=>'Science & Technology','image'=>null],
        ['name'=>'Prof. Imran Ali',     'designation'=>'Head of Arts Dept.',      'dept_name'=>'Arts & Humanities',    'image'=>null],
      ];
      $gradients = ['#0c1b3a,#1a3054','#1e3f6f,#1d4ed8','#059669,#10b981','#7c3aed,#a855f7','#dc2626,#f97316','#0891b2,#06b6d4','#065f46,#10b981','#1e3f6f,#3b82f6'];
      foreach (array_slice($facultyData, 0, 8) as $i => $f): ?>
      <div class="col-6 col-md-4 col-lg-3" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 60 ?>">
        <div class="faculty-card">
          <div class="faculty-card-img">
            <?php if (!empty($f['image'])): ?>
            <img src="<?= uploadUrl('faculty', sh($f['image'])) ?>" alt="<?= sh($f['name']) ?>" loading="lazy">
            <?php else: ?>
            <div style="height:220px;background:linear-gradient(135deg,<?= $gradients[$i % 8] ?>);display:flex;align-items:center;justify-content:center">
              <i class="fas fa-user-tie" style="font-size:3.5rem;color:rgba(255,255,255,.2)"></i>
            </div>
            <?php endif; ?>
          </div>
          <div class="faculty-card-body">
            <div class="faculty-card-name"><?= sh($f['name']) ?></div>
            <div class="faculty-card-role"><?= sh($f['designation']) ?></div>
            <?php if (!empty($f['dept_name'])): ?>
            <div style="font-size:.72rem;color:var(--text-3);margin-top:3px"><?= sh($f['dept_name']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ GALLERY ══════════════════════════════════════════════════ -->
<?php if (!empty($photos)): ?>
<section class="site-section sec-alt">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Gallery</span></div>
      <h2 class="sec-title">Campus Life in Pictures</h2>
      <p class="sec-subtitle">A glimpse into the vibrant academic and co-curricular life at Bahria Model College.</p>
    </div>
    <div class="gallery-grid" data-aos="fade-up">
      <?php foreach ($photos as $p): ?>
      <div class="gallery-item">
        <a href="<?= uploadUrl('gallery', sh($p['filename'])) ?>" class="glightbox" data-gallery="campus" title="<?= sh($p['title'] ?? '') ?>">
          <img src="<?= uploadUrl('gallery', sh($p['filename'])) ?>" alt="<?= sh($p['title'] ?? 'BMC Campus') ?>" loading="lazy">
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-5" data-aos="fade-up">
      <a href="<?= SITE_URL ?>/gallery.php" class="btn-outline-custom" style="text-decoration:none">
        View Full Gallery <i class="fas fa-images ms-1"></i>
      </a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══ TESTIMONIALS ═════════════════════════════════════════════ -->
<section class="site-section<?= empty($photos) ? ' sec-alt' : '' ?>">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Testimonials</span></div>
      <h2 class="sec-title">What Our Community Says</h2>
      <p class="sec-subtitle">Hear from students, alumni, and parents about the transformative BMC experience.</p>
    </div>
    <div class="swiper testimonials-swiper" data-aos="fade-up">
      <div class="swiper-wrapper">
        <?php
        $tdata = !empty($testimonials) ? $testimonials : [
          ['name'=>'Ahmed Khan',       'designation'=>'Alumni, Class of 2022',          'content'=>'BMC gave me the academic foundation to secure admission to NUST. The teachers\' dedication and the disciplined environment made all the difference.','rating'=>5],
          ['name'=>'Sara Iqbal',       'designation'=>'Current Student, FSc Pre-Medical','content'=>'The labs, library, and especially the support from my science teachers have been exceptional. I\'m proud and grateful to be a BMC student.','rating'=>5],
          ['name'=>'Muhammad & Zainab','designation'=>'Parents',                         'content'=>'Our daughter has grown tremendously in both academics and character since joining BMC. The staff truly cares about every child.','rating'=>5],
          ['name'=>'Fatima Siddiqui',  'designation'=>'Alumni, Class of 2020',          'content'=>'The values and work ethic I developed at BMC shaped my professional life. I owe so much of my success to this institution.','rating'=>5],
          ['name'=>'Bilal Hassan',     'designation'=>'Alumni, Class of 2023',          'content'=>'Winning the Inter-College Science Competition was only possible because of the world-class labs and mentoring at BMC.','rating'=>5],
          ['name'=>'Mrs. Kiran Naz',   'designation'=>'Parent',                         'content'=>'My son transformed from a shy boy to a confident young man at BMC. The holistic development here is real and remarkable.','rating'=>5],
        ];
        foreach ($tdata as $t): ?>
        <div class="swiper-slide">
          <div class="testimonial-card">
            <div class="star-rating">
              <?php for ($s = 0; $s < (int)($t['rating'] ?? 5); $s++) echo '<i class="fas fa-star"></i>'; ?>
            </div>
            <p class="testimonial-text">"<?= sh($t['content'] ?? '') ?>"</p>
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
      <div class="testimonials-pagination swiper-pagination" style="position:static;margin-top:32px"></div>
    </div>
  </div>
</section>

<!-- ══ IMPORTANT DOWNLOADS ══════════════════════════════════════ -->
<section class="site-section sec-alt">
  <div class="container-xl">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-5" data-aos="fade-up">
      <div>
        <div class="sec-label" style="justify-content:flex-start"><span>Resources</span></div>
        <h2 class="sec-title mb-0">Important Downloads</h2>
      </div>
      <a href="<?= SITE_URL ?>/downloads.php" class="btn-outline-custom" style="text-decoration:none;font-size:.85rem;padding:10px 22px">
        All Downloads <i class="fas fa-arrow-right ms-1"></i>
      </a>
    </div>
    <div class="row g-3">
      <?php
      $demoDownloads = [
        ['title'=>'Admission Form 2025-26',  'category'=>'Admission Forms',  'filename'=>''],
        ['title'=>'Prospectus 2025',         'category'=>'Prospectus',       'filename'=>''],
        ['title'=>'Fee Structure 2025-26',   'category'=>'Fee Structure',    'filename'=>''],
        ['title'=>'Academic Calendar 2025-26','category'=>'Academic Calendar','filename'=>''],
        ['title'=>'Rules & Regulations',     'category'=>'Policies',         'filename'=>''],
        ['title'=>'Examination Schedule',    'category'=>'Examination',      'filename'=>''],
      ];
      $dlist = !empty($downloads) ? array_slice($downloads, 0, 6) : $demoDownloads;
      foreach ($dlist as $i => $dl): ?>
      <div class="col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 60 ?>">
        <div class="download-item">
          <div class="download-icon pdf"><i class="fas fa-file-pdf"></i></div>
          <div style="flex:1;min-width:0">
            <div class="download-name"><?= sh($dl['title']) ?></div>
            <div class="download-cat"><?= sh($dl['category']) ?></div>
          </div>
          <?php if (!empty($dl['filename'])): ?>
          <a href="<?= uploadUrl('downloads', sh($dl['filename'])) ?>" download class="download-btn" title="Download">
            <i class="fas fa-download"></i>
          </a>
          <?php else: ?>
          <a href="<?= SITE_URL ?>/downloads.php" class="download-btn" title="View downloads">
            <i class="fas fa-eye"></i>
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ CLUBS & CAMPUS LIFE ══════════════════════════════════════ -->
<section class="site-section">
  <div class="container-xl">
    <div class="row g-5 align-items-center">
      <div class="col-lg-5" data-aos="fade-right">
        <div class="sec-label" style="justify-content:flex-start"><span>Campus Life</span></div>
        <h2 class="sec-title" style="text-align:left">Clubs, Societies &amp; Co-Curricular</h2>
        <p style="color:var(--text-2);line-height:1.85;margin-bottom:24px">BMC believes in developing the whole student. Our co-curricular programme offers diverse clubs, sports, arts, and community service opportunities that build leadership and social responsibility.</p>
        <a href="<?= SITE_URL ?>/about.php?tab=campus" class="btn-primary-custom" style="text-decoration:none">
          Explore Campus Life <i class="fas fa-arrow-right ms-1"></i>
        </a>
      </div>
      <div class="col-lg-7" data-aos="fade-left">
        <div class="row g-3">
          <?php
          $clubs = [
            ['icon'=>'fa-flask',          'name'=>'Science Club',      'color'=>'#1d4ed8','bg'=>'rgba(29,78,216,.08)'],
            ['icon'=>'fa-laptop-code',    'name'=>'Coding Society',    'color'=>'#7c3aed','bg'=>'rgba(124,58,237,.08)'],
            ['icon'=>'fa-palette',        'name'=>'Arts & Craft',      'color'=>'#ea580c','bg'=>'rgba(234,88,12,.08)'],
            ['icon'=>'fa-microphone',     'name'=>'Debate Club',       'color'=>'#059669','bg'=>'rgba(5,150,105,.08)'],
            ['icon'=>'fa-futbol',         'name'=>'Sports Council',    'color'=>'#ca8a04','bg'=>'rgba(202,138,4,.08)'],
            ['icon'=>'fa-book-open',      'name'=>'Literary Society',  'color'=>'#db2777','bg'=>'rgba(219,39,119,.08)'],
            ['icon'=>'fa-camera',         'name'=>'Photography',       'color'=>'#0891b2','bg'=>'rgba(8,145,178,.08)'],
            ['icon'=>'fa-globe',          'name'=>'Geography Club',    'color'=>'#6d28d9','bg'=>'rgba(109,40,217,.08)'],
            ['icon'=>'fa-hands-helping',  'name'=>'Community Service', 'color'=>'#059669','bg'=>'rgba(5,150,105,.08)'],
            ['icon'=>'fa-music',          'name'=>'Choir & Music',     'color'=>'#b45309','bg'=>'rgba(180,83,9,.08)'],
            ['icon'=>'fa-quran',          'name'=>'Islamic Society',   'color'=>'#0f172a','bg'=>'rgba(15,23,42,.06)'],
            ['icon'=>'fa-seedling',       'name'=>'Eco Club',          'color'=>'#16a34a','bg'=>'rgba(22,163,74,.08)'],
          ];
          foreach ($clubs as $i => $club): ?>
          <div class="col-4 col-md-3" data-aos="zoom-in" data-aos-delay="<?= ($i % 4) * 50 ?>">
            <div class="club-card">
              <div class="club-icon" style="background:<?= $club['bg'] ?>;color:<?= $club['color'] ?>">
                <i class="fas <?= $club['icon'] ?>"></i>
              </div>
              <div style="font-size:.78rem;font-weight:700;color:var(--text)"><?= $club['name'] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ PARTNERS ══════════════════════════════════════════════════ -->
<?php if (!empty($partners)): ?>
<section class="site-section sec-alt py-sm">
  <div class="container-xl">
    <div class="text-center mb-4" data-aos="fade-up">
      <div class="sec-label" style="justify-content:center"><span>Affiliations</span></div>
      <h3 style="font-weight:800;color:var(--navy);font-size:1.5rem">Partners &amp; Affiliations</h3>
    </div>
    <div class="swiper partners-swiper" data-aos="fade-up">
      <div class="swiper-wrapper">
        <?php foreach ($partners as $p): ?>
        <div class="swiper-slide">
          <div class="partner-logo" style="background:var(--white);border-radius:10px;padding:18px;display:flex;align-items:center;justify-content:center;border:1px solid var(--light-2);height:90px">
            <?php if ($p['logo']): ?>
            <img src="<?= uploadUrl('partners', sh($p['logo'])) ?>" alt="<?= sh($p['name']) ?>" style="max-height:50px;object-fit:contain">
            <?php else: ?>
            <span style="font-weight:700;color:var(--text-2);font-size:.85rem;text-align:center"><?= sh($p['name']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══ CTA SECTION ══════════════════════════════════════════════ -->
<section class="cta-section">
  <div class="container-xl text-center" style="position:relative;z-index:1" data-aos="fade-up">
    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="sec-label" style="justify-content:center;color:rgba(255,255,255,.65)">
          <span style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3)">Join BMC</span>
        </div>
        <h2 style="font-size:clamp(1.8rem,4vw,2.6rem);font-weight:900;color:#fff;margin-bottom:16px;letter-spacing:-.02em">
          Begin Your Journey to<br>Academic Excellence
        </h2>
        <p style="color:rgba(255,255,255,.72);margin-bottom:36px;font-size:.97rem;line-height:1.75">
          Admissions for <strong style="color:var(--gold)"><?= sh($admissionYear) ?></strong> are <?= $admissionOpen ? '<strong style="color:var(--gold-light)">now open</strong>' : 'coming soon' ?>. Download the admission form and take the first step toward a brighter future at BMC.
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
          <a href="<?= SITE_URL ?>/admissions.php" class="btn-hero-gold" style="text-decoration:none">
            <i class="fas fa-graduation-cap"></i> Apply for Admissions
          </a>
          <a href="<?= SITE_URL ?>/contact.php" class="btn-hero-outline" style="text-decoration:none">
            <i class="fas fa-phone-alt"></i> Contact Us
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ CONTACT + MAP ════════════════════════════════════════════ -->
<section class="site-section">
  <div class="container-xl">
    <div class="row g-5 align-items-stretch">
      <div class="col-lg-5" data-aos="fade-right">
        <div class="sec-label" style="justify-content:flex-start"><span>Find Us</span></div>
        <h2 class="sec-title" style="text-align:left;margin-bottom:28px">Get In Touch</h2>
        <div class="contact-info-card mb-4">
          <div class="contact-info-item">
            <div class="contact-info-icon"><i class="fas fa-map-marker-alt"></i></div>
            <div>
              <div style="font-weight:700;color:#fff;margin-bottom:3px">Address</div>
              <div style="color:rgba(255,255,255,.65);font-size:.9rem"><?= sh(getSetting('site_address')) ?></div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="contact-info-icon"><i class="fas fa-phone-alt"></i></div>
            <div>
              <div style="font-weight:700;color:#fff;margin-bottom:3px">Phone</div>
              <div style="font-size:.9rem"><a href="tel:<?= sh(getSetting('site_phone')) ?>" style="color:rgba(255,255,255,.65)"><?= sh(getSetting('site_phone')) ?></a></div>
            </div>
          </div>
          <div class="contact-info-item" style="border-bottom:none">
            <div class="contact-info-icon"><i class="fas fa-envelope"></i></div>
            <div>
              <div style="font-weight:700;color:#fff;margin-bottom:3px">Email</div>
              <div style="font-size:.9rem"><a href="mailto:<?= sh(getSetting('site_email')) ?>" style="color:rgba(255,255,255,.65)"><?= sh(getSetting('site_email')) ?></a></div>
            </div>
          </div>
        </div>
        <a href="<?= SITE_URL ?>/contact.php" class="btn-primary-custom" style="text-decoration:none">
          Send a Message <i class="fas fa-arrow-right ms-1"></i>
        </a>
      </div>
      <div class="col-lg-7" data-aos="fade-left">
        <?php $mapEmbed = getSetting('site_map_embed'); ?>
        <?php if ($mapEmbed): ?>
        <div class="map-wrap" style="height:100%;min-height:360px">
          <iframe src="<?= sh($mapEmbed) ?>" width="100%" height="100%" style="border:0;min-height:360px" allowfullscreen="" loading="lazy"></iframe>
        </div>
        <?php else: ?>
        <div class="map-wrap" style="height:100%;min-height:360px;background:var(--light-1);display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--text-3)">
          <i class="fas fa-map-marked-alt fa-3x mb-3" style="opacity:.3"></i>
          <p style="font-size:.88rem;margin-bottom:16px">Configure Google Maps in Admin → Settings</p>
          <a href="https://maps.google.com/?q=Bahria+Model+College+Bin+Qasim+Karachi" target="_blank" class="btn-primary-custom" style="text-decoration:none;font-size:.85rem">
            <i class="fas fa-map-marker-alt me-1"></i> Open in Google Maps
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

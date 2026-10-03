<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle  = 'About Us';
$activePage = 'about';
$tab = $_GET['tab'] ?? 'intro';
include __DIR__ . '/includes/header.php';
$principalName = getSetting('principal_name', 'Lt. Cdr. Abu Bakar');
$principalMsg  = getSetting('principal_message');
$vision        = getSetting('vision');
$mission       = getSetting('mission');
?>
<!-- Page Hero -->
<div class="page-hero">
  <div class="container-xl position-relative" style="z-index:1">
    <div class="page-hero-label">Bahria Model College</div>
    <h1 class="page-hero-title">About BMC</h1>
    <p class="page-hero-subtitle">Our story, values, leadership, and campus life</p>
  </div>
</div>
<div class="breadcrumb-wrap">
  <div class="container-xl"><nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php">Home</a></li>
    <li class="breadcrumb-item active">About Us</li>
  </ol></nav></div>
</div>

<section class="site-section py-sm">
  <div class="container-xl">
    <!-- Tab navigation -->
    <ul class="nav flex-wrap gap-2 mb-4" id="aboutTabs" style="border:none">
      <?php foreach ([['intro','Introduction'],['history','History'],['vision','Vision'],['mission','Mission'],['values','Core Values'],['principal',"Principal's Message"],['facilities','Facilities'],['accreditation','Accreditation']] as [$k,$label]): ?>
      <li class="nav-item">
        <a class="gallery-filter-btn<?= $tab===$k?' active':'' ?>" href="?tab=<?= $k ?>"><?= $label ?></a>
      </li>
      <?php endforeach; ?>
    </ul>

    <!-- Introduction -->
    <?php if ($tab === 'intro'): ?>
    <div class="row g-4 align-items-start" data-aos="fade-up">
      <div class="col-lg-8">
        <h2 class="sec-title mb-3" style="text-align:left">Welcome to Bahria Model College</h2>
        <p style="color:var(--text-2);line-height:1.9;margin-bottom:16px"><?= sh(getSetting('about_short')) ?></p>
        <p style="color:var(--text-2);line-height:1.9;margin-bottom:16px">Established under the auspices of the Bahria Foundation, BMC has grown from a modest institution into one of Karachi's most respected colleges. Our faculty members are among the finest educators in the city, bringing both academic expertise and genuine passion for student development.</p>
        <p style="color:var(--text-2);line-height:1.9">We offer programs in Science, Commerce, Arts, Pre-Medical, and Pre-Engineering — all designed to prepare students for top universities and fulfilling careers. Beyond academics, BMC nurtures leadership, creativity, and social responsibility through a rich co-curricular programme.</p>
        <div class="row g-3 mt-3">
          <?php foreach ([['4500+','Students Enrolled'],['185+','Faculty Members'],['30+','Years of Excellence'],['24','Programs Offered'],['6','Departments'],['95%','Board Pass Rate']] as $s): ?>
          <div class="col-4 col-md-2">
            <div style="text-align:center;background:var(--light-2);padding:14px 8px;border-radius:var(--radius)">
              <div style="font-family:var(--font-heading);font-size:1.4rem;font-weight:800;color:var(--primary)"><?= $s[0] ?></div>
              <div style="font-size:.72rem;color:var(--text-3);margin-top:2px"><?= $s[1] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card-glass p-4">
          <h5 style="font-weight:700;color:var(--primary);margin-bottom:16px"><i class="fas fa-info-circle me-2 text-secondary"></i>Quick Facts</h5>
          <?php foreach ([['Established','1995'],['Affiliation','BIEK / Federal Board'],['Campus Area','12 Acres'],['Classes','FSc, FA, ICS, ICOM, Pre-Engg'],['Medium of Instruction','English & Urdu'],['School Type','Co-Education'],['Bahria Foundation','Yes'],['Accreditation','BIEK Karachi']] as $f): ?>
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:.86rem">
            <span style="color:var(--text-2)"><?= $f[0] ?></span>
            <span style="font-weight:600;color:var(--primary)"><?= $f[1] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- History -->
    <?php elseif ($tab === 'history'): ?>
    <div data-aos="fade-up">
      <h2 class="sec-title mb-4" style="text-align:left">History of BMC</h2>
      <p style="color:var(--text-2);line-height:1.8;margin-bottom:32px">Bahria Model College Bin Qasim was established by the Bahria Foundation as part of its mission to provide quality education to the communities around Karachi's industrial and port areas. From its founding in 1995, the college has steadily grown into a premier educational institution serving thousands of students.</p>
      <div class="timeline">
        <?php foreach ([['1995','Foundation','BMC Bin Qasim was established by the Bahria Foundation with an initial intake of 300 students in Science and Arts streams.'],['1998','First Graduates','The inaugural batch graduated with exceptional results, with several students achieving board-level positions.'],['2002','Expansion','New blocks added for Commerce and Pre-Medical programs, doubling the campus capacity.'],['2006','Computer Centre','A state-of-the-art computer laboratory with 100 workstations was inaugurated, heralding the digital era at BMC.'],['2010','ILC Established','The Inclusive Learning Centre (ILC) was established to support students with diverse learning needs — a first in the region.'],['2015','20th Anniversary','BMC celebrated 20 years of excellence. A new sports complex, library block, and auditorium were inaugurated.'],['2019','Digital Transformation','Smart classrooms, a new management portal, and a digital library were introduced.'],['2022','National Recognition','BMC received national recognition for academic excellence and inclusive education practices.'],['2025','Continuing Legacy','BMC continues to expand its programs, faculty, and infrastructure to serve the growing student community.']] as $h): ?>
        <div class="timeline-item">
          <div class="timeline-year"><?= $h[0] ?></div>
          <div class="timeline-title"><?= $h[1] ?></div>
          <div class="timeline-desc"><?= $h[2] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Vision -->
    <?php elseif ($tab === 'vision'): ?>
    <div class="row justify-content-center" data-aos="fade-up">
      <div class="col-lg-8 text-center">
        <div style="width:80px;height:80px;background:linear-gradient(135deg,var(--secondary),var(--primary-l));border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:2rem;color:#fff"><i class="fas fa-eye"></i></div>
        <h2 class="sec-title mb-4">Our Vision</h2>
        <div class="highlight-box text-center" style="font-size:1.05rem;font-style:normal;border-left:none;border-top:4px solid var(--accent);border-radius:var(--radius-lg);text-align:center">
          <?= sh($vision) ?>
        </div>
        <p style="color:var(--text-2);margin-top:24px;line-height:1.8">Our vision drives every decision we make — from curriculum design to campus infrastructure, from teacher development to student support. We aspire to produce graduates who are not only academically accomplished but also ethically grounded and socially aware.</p>
      </div>
    </div>

    <!-- Mission -->
    <?php elseif ($tab === 'mission'): ?>
    <div class="row justify-content-center" data-aos="fade-up">
      <div class="col-lg-8 text-center">
        <div style="width:80px;height:80px;background:linear-gradient(135deg,var(--accent),var(--accent-d));border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:2rem;color:var(--primary)"><i class="fas fa-bullseye"></i></div>
        <h2 class="sec-title mb-4">Our Mission</h2>
        <div class="highlight-box text-center" style="font-size:1.05rem;font-style:normal;border-left:none;border-top:4px solid var(--secondary);border-radius:var(--radius-lg);text-align:center">
          <?= sh($mission) ?>
        </div>
        <div class="row g-3 mt-4 text-start">
          <?php foreach (['Academic Excellence through rigorous, standards-based curriculum','Character Development through moral guidance and mentorship','Modern Infrastructure including smart classrooms and laboratories','Inclusive Education serving all students regardless of learning needs','Community Engagement and national service','Career Preparation for top universities and professional success'] as $m): ?>
          <div class="col-md-6">
            <div style="display:flex;gap:10px;align-items:flex-start">
              <i class="fas fa-check-circle mt-1" style="color:var(--secondary);flex-shrink:0"></i>
              <span style="color:var(--text-2);font-size:.9rem"><?= $m ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Core Values -->
    <?php elseif ($tab === 'values'): ?>
    <div data-aos="fade-up">
      <h2 class="sec-title text-center mb-2">Our Core Values</h2>
      <p class="sec-subtitle text-center mb-5">The principles that guide everything we do at BMC</p>
      <div class="row g-4">
        <?php foreach ([['fa-shield-alt','Integrity','We uphold the highest standards of honesty and ethical conduct in all our actions.','#0984e3'],['fa-medal','Excellence','We strive for the highest quality in teaching, learning, and all college activities.','#f9ca24'],['fa-hands','Respect','We value every individual and treat all members of our community with dignity.','#e17055'],['fa-heart','Compassion','We care deeply for the well-being and growth of every student, staff member, and community.','#fd79a8'],['fa-lightbulb','Innovation','We embrace new ideas and methods to continuously improve the educational experience.','#6c5ce7'],['fa-users','Collaboration','We believe in the power of working together toward shared goals.','#00cec9'],['fa-balance-scale','Accountability','We take responsibility for our actions and their impact on our community.','#27ae60'],['fa-seedling','Service','We encourage students to contribute meaningfully to their families, community, and nation.','#f9ca24'],['fa-book-open','Lifelong Learning','We instill a love of learning that extends far beyond the classroom.','#0984e3']] as $v): ?>
        <div class="col-sm-6 col-lg-4" data-aos="zoom-in">
          <div class="value-card">
            <div class="value-icon" style="background:<?= $v[3] ?>1a;color:<?= $v[3] ?>"><i class="fas <?= $v[0] ?>"></i></div>
            <div class="value-title"><?= $v[1] ?></div>
            <div class="value-desc"><?= $v[2] ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Principal's Message -->
    <?php elseif ($tab === 'principal'): ?>
    <div class="row g-4 align-items-start" data-aos="fade-up">
      <div class="col-lg-4">
        <div class="card-glass p-3 text-center">
          <div style="width:160px;height:180px;background:linear-gradient(135deg,var(--primary-d),var(--primary-l));border-radius:var(--radius-lg);margin:0 auto 16px;display:flex;align-items:center;justify-content:center">
            <i class="fas fa-user-tie" style="font-size:4rem;color:rgba(255,255,255,.3)"></i>
          </div>
          <h5 style="font-weight:800;color:var(--primary);margin-bottom:4px"><?= sh($principalName) ?></h5>
          <p style="font-size:.82rem;color:var(--text-2)">Principal, Bahria Model College Bin Qasim</p>
          <div style="font-size:.78rem;color:var(--text-3);margin-top:8px">Lieutenant Commander, Pakistan Navy<br>PN Parachutist</div>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="sec-label" style="justify-content:flex-start"><span>From The Desk</span></div>
        <h2 class="sec-title mb-4" style="text-align:left">Principal's Message</h2>
        <div class="highlight-box mb-4" style="font-size:1rem;font-style:normal">
          <?= sh($principalMsg) ?>
        </div>
        <p style="color:var(--text-2);line-height:1.8;margin-bottom:12px">At BMC, we understand that education is not merely the transfer of knowledge — it is the transformation of young lives. Our dedicated teachers are committed to inspiring every student to discover their potential and pursue it with dedication.</p>
        <p style="color:var(--text-2);line-height:1.8">I invite you to be part of the BMC family — a community of learners, thinkers, and leaders who are proud of their heritage and inspired to make a difference.</p>
        <div style="margin-top:20px;font-size:.9rem;color:var(--primary);font-weight:700">— <?= sh($principalName) ?>, Principal</div>
      </div>
    </div>

    <!-- Facilities -->
    <?php elseif ($tab === 'facilities'): ?>
    <div data-aos="fade-up">
      <h2 class="sec-title text-center mb-2">Campus Facilities</h2>
      <p class="sec-subtitle text-center mb-5">World-class infrastructure supporting your academic and personal growth</p>
      <div class="row g-4">
        <?php foreach ([['fa-flask','Science Laboratories','Fully equipped physics, chemistry, and biology laboratories with modern instruments and safety protocols. Dedicated lab assistants support hands-on practical work.','bg-primary text-white'],['fa-book-reader','Digital Library','Over 20,000 volumes, digital journals, e-books, and a quiet reading hall. The library is open 6 days a week including Saturday.','bg-success text-white'],['fa-laptop','Computer Centre','200 networked workstations with high-speed internet, licensed software, and dedicated instructors. A separate multimedia lab for creative work.','bg-info text-white'],['fa-running','Sports Complex','Cricket pitch, football ground, basketball court, and a covered gym. Regular inter-house and inter-college sports events encourage healthy competition.','bg-warning text-dark'],['fa-theater-masks','Auditorium','A 600-seat, air-conditioned auditorium with professional sound and lighting systems for events, seminars, and cultural programmes.','bg-danger text-white'],['fa-utensils','Cafeteria','A clean, hygienic cafeteria serving hot meals, snacks, and beverages. Managed under strict food safety guidelines.','bg-secondary text-white'],['fa-heartbeat','Medical Room','A first-aid facility with a trained nurse on campus, ensuring student health and safety at all times.','bg-danger text-white'],['fa-bus','Transport','Safe, GPS-tracked transport covering major routes in Bin Qasim and surrounding areas.','bg-primary text-white']] as $f): ?>
        <div class="col-sm-6 col-lg-3" data-aos="fade-up">
          <div class="card-glass p-4 text-center h-100">
            <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width:56px;height:56px;background:linear-gradient(135deg,var(--secondary),var(--primary-l));border-radius:var(--radius);color:#fff;font-size:1.3rem"><i class="fas <?= $f[0] ?>"></i></div>
            <h5 style="font-weight:700;color:var(--primary);font-size:.95rem;margin-bottom:8px"><?= $f[1] ?></h5>
            <p style="font-size:.83rem;color:var(--text-2);line-height:1.6"><?= $f[2] ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Accreditation -->
    <?php elseif ($tab === 'accreditation'): ?>
    <div data-aos="fade-up">
      <h2 class="sec-title mb-4" style="text-align:left">Accreditation &amp; Affiliations</h2>
      <div class="row g-4">
        <?php foreach ([['Board of Intermediate Education Karachi (BIEK)','BMC is formally affiliated with BIEK, ensuring our curriculum, examinations, and degree certificates meet the highest national standards.','fa-certificate','#0984e3'],['Bahria Foundation','As an institution established under the Bahria Foundation, BMC benefits from the trust, resources, and oversight of one of Pakistan\'s most respected charitable organizations.','fa-anchor','#0c2461'],['Federal Board of Intermediate and Secondary Education (FBISE)','Selected programs at BMC are also affiliated with the Federal Board, offering students flexibility in their examination choices.','fa-university','#27ae60'],['Higher Education Commission (HEC)','BMC maintains alignment with HEC guidelines to ensure seamless transition for students proceeding to university-level education.','fa-graduation-cap','#6c5ce7']] as $a): ?>
        <div class="col-lg-6">
          <div class="card-glass p-4 d-flex gap-3 h-100">
            <div style="width:50px;height:50px;background:<?= $a[2] ?>1a;border-radius:var(--radius);display:flex;align-items:center;justify-content:center;color:<?= $a[2] ?>;font-size:1.2rem;flex-shrink:0"><i class="fas <?= $a[0] ?>"></i></div>
            <div>
              <h5 style="font-weight:700;color:var(--primary);font-size:.92rem;margin-bottom:6px"><?= $a[0] ?></h5>
              <p style="font-size:.84rem;color:var(--text-2);line-height:1.6;margin:0"><?= $a[1] ?></p>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

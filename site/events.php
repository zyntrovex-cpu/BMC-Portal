<?php
require_once __DIR__ . '/includes/functions.php';

$activePage = 'events';
$pageTitle  = 'Events';
$pageDesc   = 'Explore upcoming and past events at Bahria Model College — academic functions, cultural celebrations, sports galas, and community activities.';

// ── Demo data ─────────────────────────────────────────────────────────────────
$today = date('Y-m-d');

$demoUpcoming = [
    ['id'=>1,'title'=>'Annual Prize Distribution Ceremony 2025','event_date'=>'2025-08-20','event_time'=>'09:00','venue'=>'Main Auditorium, BMC Campus','description'=>'The Annual Prize Distribution Ceremony honours outstanding students for academic excellence, co-curricular achievements, and community service. The ceremony will be graced by the Admiral Commanding Karachi and senior naval officers. Parents and families are warmly invited to celebrate the achievements of their children.','category'=>'Academic'],
    ['id'=>2,'title'=>'Intermediate Admissions Open Day 2025','event_date'=>'2025-08-10','event_time'=>'10:00','venue'=>'Reception Hall & Campus Tour','description'=>'Prospective students and their parents are invited to our open day to explore programs, meet faculty, tour the campus, and speak with current students. Detailed information about admission criteria, scholarships, and fee structure will be available. On-the-spot counselling sessions will also be conducted by our admissions team.','category'=>'Admissions'],
    ['id'=>3,'title'=>'Inter-College Science Exhibition 2025','event_date'=>'2025-09-05','event_time'=>'08:30','venue'=>'Science Block & Grounds','description'=>'BMC will host the Regional Inter-College Science Exhibition featuring over 80 student projects from 15 participating institutions. Categories include Physics, Chemistry, Biology, Environmental Science, and Computing. Industry judges and university professors will evaluate projects. Winners will advance to the national-level competition.','category'=>'Academic'],
    ['id'=>4,'title'=>'Independence Day Flag Hoisting Ceremony','event_date'=>'2025-08-14','event_time'=>'07:30','venue'=>'College Parade Ground','description'=>'Bahria Model College will celebrate Pakistan\'s 78th Independence Day with a grand flag hoisting ceremony. The event will feature a guard of honour, national anthem, patriotic speeches by students, tableaux, and a cultural programme. All students, staff, and families are invited to attend this momentous national celebration.','category'=>'National'],
    ['id'=>5,'title'=>'Career Counselling & University Fair','event_date'=>'2025-09-18','event_time'=>'09:00','venue'=>'Multi-Purpose Hall','description'=>'Leading universities from across Pakistan and abroad will participate in BMC\'s annual University Fair, offering guidance on undergraduate and postgraduate admissions. Institutions including NUST, LUMS, Aga Khan University, FAST, and several UK/Canadian universities will have information stalls. Entry is free for all intermediate-level students.','category'=>'Career'],
    ['id'=>6,'title'=>'Iqbal Day Literary & Debate Competition','event_date'=>'2025-11-09','event_time'=>'09:30','venue'=>'Main Auditorium','description'=>'Commemorating Allama Iqbal\'s birth anniversary, BMC will host a multi-round debate, nazm recitation, and speech competition open to all students. The theme this year is "Iqbal\'s Vision for Youth in the Modern World." Trophies and cash prizes will be awarded to top performers in each category.','category'=>'Cultural'],
];

$demoPast = [
    ['id'=>7,'title'=>'Annual Sports Gala 2024–25','event_date'=>'2025-05-10','event_time'=>'07:00','venue'=>'BMC Sports Complex','description'=>'Three days of athletics, cricket, football, badminton, and indoor sports drew participation from over 800 students. The event concluded with a colourful closing ceremony and prize distribution by the College Principal.','category'=>'Sports'],
    ['id'=>8,'title'=>'Blood Donation Camp — Community Service Drive','event_date'=>'2025-03-18','event_time'=>'09:00','venue'=>'Health Centre, BMC Campus','description'=>'In collaboration with Aga Khan Hospital, BMC collected 150 units of blood from students, staff, and community members. The event reinforced the college\'s commitment to social responsibility and community health.','category'=>'Community'],
    ['id'=>9,'title'=>'Parent-Teacher Meeting (Spring Term)','event_date'=>'2025-02-05','event_time'=>'09:00','venue'=>'All Classrooms & Common Areas','description'=>'The spring-term PTM saw record attendance of over 1,200 parents. Academic progress reports were discussed, and the counselling team addressed student wellness concerns. The event was praised for its transparent and supportive format.','category'=>'Academic'],
    ['id'=>10,'title'=>'Orientation Day 2024–25 New Intake','event_date'=>'2024-09-02','event_time'=>'08:00','venue'=>'Main Auditorium & Classrooms','description'=>'Over 600 new students joined BMC for the 2024–25 academic year. Orientation included a welcome address by the Principal, introduction to faculty and facilities, and an interactive session with senior students and the student council.','category'=>'Academic'],
];

// Try DB, fall back to demo
$dbUpcoming = getEvents(10, true);
$dbAll      = getEvents(50);
$dbPast     = array_values(array_filter($dbAll, fn($e) => ($e['event_date'] ?? '') < $today));

$upcomingEvents = !empty($dbUpcoming) ? $dbUpcoming : $demoUpcoming;
$pastEvents     = !empty($dbPast)     ? $dbPast     : $demoPast;

include __DIR__ . '/includes/header.php';
?>

<!-- ── Page Hero ── -->
<div class="page-hero">
  <div class="container-xl">
    <h1 class="page-hero-title" data-aos="fade-up">Events</h1>
    <p class="page-hero-sub" data-aos="fade-up" data-aos-delay="100">
      Academic, cultural, sports, and community events — there's always something happening at BMC
    </p>
  </div>
</div>
<div class="breadcrumb-wrap">
  <div class="container-xl">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php"><i class="fas fa-home me-1"></i>Home</a></li>
        <li class="breadcrumb-item active">Events</li>
      </ol>
    </nav>
  </div>
</div>

<!-- ── Tabs ── -->
<section class="site-section">
  <div class="container-xl">
    <!-- Tab buttons -->
    <div class="filter-tabs mb-5" data-aos="fade-up">
      <button class="filter-tab active" data-bs-toggle="tab" data-bs-target="#tabUpcoming">
        <i class="fas fa-calendar-alt me-2"></i>Upcoming Events
        <span class="tab-count"><?= count($upcomingEvents) ?></span>
      </button>
      <button class="filter-tab" data-bs-toggle="tab" data-bs-target="#tabPast">
        <i class="fas fa-history me-2"></i>Past Events
        <span class="tab-count"><?= count($pastEvents) ?></span>
      </button>
    </div>

    <div class="tab-content">
      <!-- Upcoming Events -->
      <div class="tab-pane show active" id="tabUpcoming">
        <?php if (empty($upcomingEvents)): ?>
          <div class="empty-state text-center py-5" data-aos="fade-up">
            <i class="fas fa-calendar-times text-muted" style="font-size:3rem"></i>
            <h5 class="mt-3 text-muted">No upcoming events scheduled at this time.</h5>
            <p class="text-muted">Check back soon or <a href="<?= SITE_URL ?>/notices.php">view notices</a> for updates.</p>
          </div>
        <?php else: ?>
        <div class="row g-4">
          <?php foreach ($upcomingEvents as $i => $e): ?>
          <div class="col-lg-6" data-aos="fade-up" data-aos-delay="<?= ($i % 2) * 80 ?>">
            <div class="event-card h-100">
              <div class="event-card-date">
                <span class="event-day"><?= date('d', strtotime($e['event_date'])) ?></span>
                <span class="event-month"><?= date('M', strtotime($e['event_date'])) ?></span>
                <span class="event-year"><?= date('Y', strtotime($e['event_date'])) ?></span>
              </div>
              <div class="event-card-body">
                <div class="d-flex flex-wrap gap-2 mb-2">
                  <?php if (!empty($e['category'])): ?>
                    <span class="badge bg-primary"><?= sh($e['category']) ?></span>
                  <?php endif; ?>
                  <span class="badge bg-success">Upcoming</span>
                </div>
                <h5 class="event-card-title"><?= sh($e['title']) ?></h5>
                <div class="event-card-meta">
                  <?php if (!empty($e['event_time'])): ?>
                    <span><i class="fas fa-clock me-1"></i><?= date('g:i A', strtotime($e['event_time'])) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($e['venue'])): ?>
                    <span><i class="fas fa-map-marker-alt me-1"></i><?= sh($e['venue']) ?></span>
                  <?php endif; ?>
                </div>
                <?php if (!empty($e['description'])): ?>
                  <p class="event-card-desc"><?= sh(truncateText($e['description'], 160)) ?></p>
                <?php endif; ?>
                <div class="event-card-footer">
                  <span class="event-days-away">
                    <?php
                      $diff = (strtotime($e['event_date']) - strtotime($today));
                      $days = (int)round($diff / 86400);
                      if ($days === 0) echo '<span class="text-success fw-semibold">Today!</span>';
                      elseif ($days === 1) echo '<span class="text-warning fw-semibold">Tomorrow</span>';
                      else echo 'In ' . $days . ' day' . ($days !== 1 ? 's' : '');
                    ?>
                  </span>
                  <button class="btn-text" onclick="this.closest('.event-card').querySelector('.event-full-desc').classList.toggle('d-none')">
                    Read more <i class="fas fa-chevron-down ms-1"></i>
                  </button>
                </div>
                <?php if (!empty($e['description'])): ?>
                  <div class="event-full-desc d-none mt-2 text-muted small">
                    <?= sh($e['description']) ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Past Events -->
      <div class="tab-pane" id="tabPast">
        <?php if (empty($pastEvents)): ?>
          <div class="empty-state text-center py-5">
            <i class="fas fa-archive text-muted" style="font-size:3rem"></i>
            <h5 class="mt-3 text-muted">No past events on record.</h5>
          </div>
        <?php else: ?>
        <div class="row g-4">
          <?php foreach ($pastEvents as $i => $e): ?>
          <div class="col-lg-6" data-aos="fade-up" data-aos-delay="<?= ($i % 2) * 80 ?>">
            <div class="event-card event-card-past h-100">
              <div class="event-card-date event-date-past">
                <span class="event-day"><?= date('d', strtotime($e['event_date'])) ?></span>
                <span class="event-month"><?= date('M', strtotime($e['event_date'])) ?></span>
                <span class="event-year"><?= date('Y', strtotime($e['event_date'])) ?></span>
              </div>
              <div class="event-card-body">
                <div class="d-flex flex-wrap gap-2 mb-2">
                  <?php if (!empty($e['category'])): ?>
                    <span class="badge bg-secondary"><?= sh($e['category']) ?></span>
                  <?php endif; ?>
                  <span class="badge bg-light text-muted">Concluded</span>
                </div>
                <h5 class="event-card-title"><?= sh($e['title']) ?></h5>
                <div class="event-card-meta">
                  <?php if (!empty($e['event_time'])): ?>
                    <span><i class="fas fa-clock me-1"></i><?= date('g:i A', strtotime($e['event_time'])) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($e['venue'])): ?>
                    <span><i class="fas fa-map-marker-alt me-1"></i><?= sh($e['venue']) ?></span>
                  <?php endif; ?>
                </div>
                <?php if (!empty($e['description'])): ?>
                  <p class="event-card-desc"><?= sh(truncateText($e['description'], 160)) ?></p>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- ── CTA ── -->
<section class="site-section sec-alt">
  <div class="container-xl text-center" data-aos="fade-up">
    <div class="section-header">
      <span class="sec-label">Stay Connected</span>
      <h2 class="sec-title">Never Miss an Event</h2>
    </div>
    <p class="text-muted mb-4">Follow BMC on social media or check our notices board for the latest event announcements and updates.</p>
    <div class="d-flex gap-3 justify-content-center flex-wrap">
      <a href="<?= SITE_URL ?>/notices.php" class="btn-primary-custom"><i class="fas fa-bell me-2"></i>View Notices</a>
      <a href="<?= SITE_URL ?>/news.php" class="btn-outline-custom"><i class="fas fa-newspaper me-2"></i>Latest News</a>
    </div>
  </div>
</section>

<script>
// Tab switching logic
document.querySelectorAll('.filter-tab[data-bs-target]').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.filter-tab[data-bs-target]').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => { p.classList.remove('show','active'); });
    this.classList.add('active');
    const target = document.querySelector(this.dataset.bsTarget);
    if (target) { target.classList.add('show','active'); }
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

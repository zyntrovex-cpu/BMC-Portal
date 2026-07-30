<?php
require_once __DIR__ . '/includes/functions.php';

$activePage = 'notices';
$pageTitle  = 'Notices';
$pageDesc   = 'Official notices, announcements, and circulars from Bahria Model College administration.';

$filterCat = trim($_GET['cat'] ?? '');
$notices   = getNotices(50, $filterCat);

// ── Demo notices (fallback when DB is empty) ──────────────────────────────────
$demoNotices = [
    ['id'=>1,'title'=>'Admission Schedule for Academic Year 2025–26','category'=>'Admissions','priority'=>'urgent','content'=>'Applications for FSc Part-I (Pre-Medical, Pre-Engineering, ICS, ICom) for the academic year 2025–26 are now open. Last date for submission of forms is 15 August 2025. No forms will be accepted after the deadline. Prospective students must submit attested copies of SSC certificate, marks sheet, CNIC/B-Form, and two passport photos along with the application form.','created_at'=>'2025-07-15','expires_at'=>'2025-08-15','attachment'=>'admission-schedule-2025.pdf'],
    ['id'=>2,'title'=>'Annual Examinations 2025 — Date Sheet Released','category'=>'Examination','priority'=>'important','content'=>'The date sheet for the Annual Examinations 2025 has been issued by the Karachi Board of Intermediate Education. All students of FSc Part-I and Part-II are advised to collect their respective date sheets from the examination branch. Practical examinations will commence from 5 June 2025. Students are required to carry their original roll number slips and college ID cards.','created_at'=>'2025-05-01','expires_at'=>null,'attachment'=>'datesheet-2025.pdf'],
    ['id'=>3,'title'=>'Fee Submission Deadline — July 2025','category'=>'Finance','priority'=>'important','content'=>'Students are reminded that the last date for fee submission for the month of July 2025 is 10 July 2025. A late fee surcharge of Rs. 200 per day will be applied after the deadline. Students may pay via online transfer, bank challan, or directly at the college accounts office. Fee receipts must be submitted to the class teacher for verification.','created_at'=>'2025-07-01','expires_at'=>'2025-07-15','attachment'=>''],
    ['id'=>4,'title'=>'Summer Vacation — College Closed 20–31 July 2025','category'=>'General','priority'=>'normal','content'=>'In accordance with the academic calendar, the college will observe summer vacation from 20 July to 31 July 2025. Regular classes will resume on 1 August 2025. Students appearing in supplementary examinations should contact the examination branch for schedules. The admin office will remain open on weekdays during vacation for urgent matters.','created_at'=>'2025-07-10','expires_at'=>null,'attachment'=>''],
    ['id'=>5,'title'=>'Scholarship Applications Open — Merit & Need-Based','category'=>'Finance','priority'=>'important','content'=>'Applications are invited from eligible students for merit scholarships and need-based financial assistance for the academic year 2025–26. Merit scholarships are available for students with 85% or above in SSC. Need-based grants are available for students from low-income families. Application forms are available from the Student Affairs Office. Last date: 25 July 2025.','created_at'=>'2025-06-20','expires_at'=>'2025-07-25','attachment'=>'scholarship-form-2025.pdf'],
    ['id'=>6,'title'=>'Karachi Board Registration — Important Deadline','category'=>'Examination','priority'=>'urgent','content'=>'All students of FSc Part-I must complete their Karachi Board registration by 30 July 2025. Incomplete registrations will attract a late fee of Rs. 500. Students are advised to verify their registration details in the examination branch. Original CNIC/B-Form and SSC original documents are mandatory. Students who have not submitted documents must do so immediately.','created_at'=>'2025-07-05','expires_at'=>'2025-07-30','attachment'=>'board-registration-checklist.pdf'],
    ['id'=>7,'title'=>'Library Extended Hours — Exam Preparation Period','category'=>'Academic','priority'=>'normal','content'=>'The college library will operate extended hours from 8:00 AM to 7:00 PM from 1 June to 30 June 2025 to support students preparing for annual examinations. Additional reference books, past papers, and study guides have been made available. Students are requested to maintain silence and follow library rules. Group study rooms can be booked at the library desk.','created_at'=>'2025-05-28','expires_at'=>'2025-06-30','attachment'=>''],
    ['id'=>8,'title'=>'Parents are Invited — Parent-Teacher Meeting, 25 July','category'=>'Events','priority'=>'normal','content'=>'The college management cordially invites parents and guardians to attend the Parent-Teacher Meeting scheduled for 25 July 2025. The meeting will be held separately for morning and evening shift students. Parents are requested to bring their child\'s report card. Feedback from parents is highly valued and helps us improve the quality of education and pastoral care.','created_at'=>'2025-07-12','expires_at'=>'2025-07-25','attachment'=>'ptm-schedule.pdf'],
    ['id'=>9,'title'=>'COVID-19 Health Protocols Update 2025','category'=>'Health','priority'=>'normal','content'=>'In line with provincial health authority guidelines, the college has updated its health and safety protocols. Students with any symptoms of illness are advised to stay home and notify their class teacher. The college nurse is available during college hours at the health centre. All staff and students are encouraged to stay updated with recommended vaccinations.','created_at'=>'2025-04-15','expires_at'=>null,'attachment'=>''],
    ['id'=>10,'title'=>'Campus Maintenance — Parking Area Closed 22 July','category'=>'General','priority'=>'normal','content'=>'The main parking area will be closed for resurfacing works on 22 July 2025. Students and staff are advised to use the alternate parking near Gate 2. Motorcycles may park in the designated area near the sports complex. The works are expected to be completed by the end of the day. We apologise for any inconvenience caused.','created_at'=>'2025-07-18','expires_at'=>'2025-07-22','attachment'=>''],
];

// Archive: notices that have expired
$today = date('Y-m-d');
$archiveDemoNotices = [
    ['id'=>101,'title'=>'End of Term Examination Results — March 2025','category'=>'Examination','priority'=>'normal','content'=>'Mid-term examination results for all classes have been compiled and are available from the examination office. Students can collect their result cards from their respective class teachers.','created_at'=>'2025-03-20','expires_at'=>'2025-04-01','attachment'=>''],
    ['id'=>102,'title'=>'Sports Gala 2025 — Participation Registration','category'=>'Sports','priority'=>'normal','content'=>'Students interested in participating in the Annual Sports Gala 2025 are required to register with the Physical Education department before 30 April 2025.','created_at'=>'2025-04-15','expires_at'=>'2025-04-30','attachment'=>''],
    ['id'=>103,'title'=>'Blood Donation Drive — Registration Open','category'=>'Community','priority'=>'normal','content'=>'The Social Welfare Club invites students aged 18 and above to register for the BMC Blood Donation Drive. The camp will be held on 18 March 2025. Registration forms available from the student affairs office.','created_at'=>'2025-03-01','expires_at'=>'2025-03-18','attachment'=>''],
];

// Use DB or demo
$activeNotices  = !empty($notices) ? $notices : $demoNotices;
$archiveNotices = $archiveDemoNotices; // always show some archive data

$categories = array_unique(array_column($activeNotices, 'category'));

include __DIR__ . '/includes/header.php';
?>

<!-- ── Page Hero ── -->
<div class="page-hero">
  <div class="container-xl">
    <h1 class="page-hero-title" data-aos="fade-up">Notices & Circulars</h1>
    <p class="page-hero-sub" data-aos="fade-up" data-aos-delay="100">
      Official announcements, circulars, and important updates from BMC administration
    </p>
  </div>
</div>
<div class="breadcrumb-wrap">
  <div class="container-xl">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php"><i class="fas fa-home me-1"></i>Home</a></li>
        <li class="breadcrumb-item active">Notices</li>
      </ol>
    </nav>
  </div>
</div>

<!-- ── Controls ── -->
<section class="site-section pb-2">
  <div class="container-xl">
    <div class="row g-3 align-items-center mb-4" data-aos="fade-up">
      <!-- Live search -->
      <div class="col-md-5">
        <div class="search-input-wrap">
          <i class="fas fa-search search-input-icon"></i>
          <input type="text" id="noticeSearch" class="form-control form-control-custom ps-5" placeholder="Search notices…" autocomplete="off">
        </div>
      </div>
      <!-- Category filter -->
      <div class="col-md-7">
        <div class="filter-tabs filter-tabs-sm">
          <a href="<?= SITE_URL ?>/notices.php" class="filter-tab <?= !$filterCat ? 'active' : '' ?>">All</a>
          <?php foreach ($categories as $c): ?>
            <a href="<?= SITE_URL ?>/notices.php?cat=<?= urlencode($c) ?>" class="filter-tab <?= $filterCat === $c ? 'active' : '' ?>"><?= sh($c) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Priority filter buttons -->
    <div class="d-flex gap-2 mb-4 flex-wrap" data-aos="fade-up" data-aos-delay="60">
      <span class="text-muted small me-2 d-flex align-items-center">Filter by priority:</span>
      <button class="priority-filter-btn active" data-priority="all">All</button>
      <button class="priority-filter-btn" data-priority="urgent">
        <span class="badge bg-danger me-1">Urgent</span>
      </button>
      <button class="priority-filter-btn" data-priority="important">
        <span class="badge bg-warning text-dark me-1">Important</span>
      </button>
      <button class="priority-filter-btn" data-priority="normal">
        <span class="badge bg-secondary me-1">Normal</span>
      </button>
    </div>
  </div>
</section>

<!-- ── Notices List ── -->
<section class="site-section pt-0">
  <div class="container-xl">
    <div id="noticesList">
      <?php if (empty($activeNotices)): ?>
        <div class="empty-state text-center py-5">
          <i class="fas fa-bell-slash text-muted" style="font-size:3rem"></i>
          <h5 class="mt-3 text-muted">No notices found.</h5>
          <p class="text-muted">There are no active notices at this time. Please check back later.</p>
        </div>
      <?php else: ?>
        <?php foreach ($activeNotices as $i => $n): ?>
        <div class="notice-row"
             data-priority="<?= sh($n['priority'] ?? 'normal') ?>"
             data-title="<?= sh(strtolower($n['title'] ?? '')) ?>"
             data-content="<?= sh(strtolower(strip_tags($n['content'] ?? ''))) ?>"
             data-aos="fade-up"
             data-aos-delay="<?= min($i * 50, 200) ?>">
          <div class="notice-card">
            <div class="notice-card-left">
              <div class="notice-priority-indicator notice-priority-<?= sh($n['priority'] ?? 'normal') ?>"></div>
            </div>
            <div class="notice-card-body">
              <div class="notice-card-header">
                <h5 class="notice-title"><?= sh($n['title'] ?? '') ?></h5>
                <div class="notice-badges d-flex gap-2 flex-wrap">
                  <?php echo priorityBadge($n['priority'] ?? 'normal'); ?>
                  <?php if (!empty($n['category'])): ?>
                    <span class="badge bg-light text-dark border"><?= sh($n['category']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="notice-meta">
                <span><i class="fas fa-calendar me-1"></i><?= siteDate($n['created_at'] ?? '') ?></span>
                <?php if (!empty($n['expires_at'])): ?>
                  <span class="text-<?= (strtotime($n['expires_at']) < strtotime($today)) ? 'danger' : 'muted' ?>">
                    <i class="fas fa-clock me-1"></i>Expires: <?= siteDate($n['expires_at']) ?>
                  </span>
                <?php endif; ?>
              </div>
              <?php if (!empty($n['content'])): ?>
                <p class="notice-excerpt"><?= sh(truncateText($n['content'], 200)) ?></p>
              <?php endif; ?>
              <?php if (!empty($n['attachment'])): ?>
                <a href="<?= uploadUrl('notices', $n['attachment']) ?>"
                   class="btn-download-sm mt-2"
                   download>
                  <i class="fas fa-paperclip me-1"></i>Download Attachment
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- No results message -->
    <div id="noticeNoResults" class="empty-state text-center py-5 d-none">
      <i class="fas fa-search text-muted" style="font-size:2.5rem"></i>
      <h5 class="mt-3 text-muted">No notices match your search.</h5>
      <button class="btn-outline-custom mt-3" onclick="clearNoticeSearch()">Clear Search</button>
    </div>
  </div>
</section>

<!-- ── Archive Section ── -->
<section class="site-section sec-alt">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <span class="sec-label"><i class="fas fa-archive me-1"></i>Archive</span>
      <h2 class="sec-title">Past Notices</h2>
    </div>
    <div class="row g-3">
      <?php foreach ($archiveNotices as $i => $an): ?>
      <div class="col-12" data-aos="fade-up" data-aos-delay="<?= $i * 60 ?>">
        <div class="notice-card notice-card-archived">
          <div class="notice-card-left">
            <div class="notice-priority-indicator notice-priority-normal"></div>
          </div>
          <div class="notice-card-body">
            <div class="notice-card-header">
              <h6 class="notice-title text-muted"><?= sh($an['title']) ?></h6>
              <div class="d-flex gap-2">
                <span class="badge bg-secondary">Archived</span>
                <?php if (!empty($an['category'])): ?>
                  <span class="badge bg-light text-muted border"><?= sh($an['category']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="notice-meta text-muted">
              <span><i class="fas fa-calendar me-1"></i>Posted: <?= siteDate($an['created_at']) ?></span>
              <?php if (!empty($an['expires_at'])): ?>
                <span><i class="fas fa-ban me-1"></i>Expired: <?= siteDate($an['expires_at']) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<script>
// Live search
const noticeSearchInput = document.getElementById('noticeSearch');
const noResults = document.getElementById('noticeNoResults');

function runNoticeSearch() {
  const q = noticeSearchInput.value.toLowerCase().trim();
  const activePriority = document.querySelector('.priority-filter-btn.active')?.dataset.priority ?? 'all';
  let visible = 0;
  document.querySelectorAll('.notice-row').forEach(row => {
    const matchText = !q || row.dataset.title.includes(q) || row.dataset.content.includes(q);
    const matchPri  = activePriority === 'all' || row.dataset.priority === activePriority;
    const show = matchText && matchPri;
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  if (noResults) noResults.classList.toggle('d-none', visible > 0);
}

function clearNoticeSearch() {
  noticeSearchInput.value = '';
  document.querySelectorAll('.priority-filter-btn').forEach(b => b.classList.remove('active'));
  document.querySelector('.priority-filter-btn[data-priority="all"]').classList.add('active');
  runNoticeSearch();
}

noticeSearchInput?.addEventListener('input', runNoticeSearch);

// Priority filter
document.querySelectorAll('.priority-filter-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.priority-filter-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    runNoticeSearch();
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

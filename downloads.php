<?php
require_once __DIR__ . '/includes/functions.php';

$activePage = 'downloads';
$pageTitle  = 'Downloads';
$pageDesc   = 'Download admission forms, prospectus, fee structure, academic calendar, examination schedules, and other important documents from Bahria Model College.';

$activeTab = trim($_GET['tab'] ?? 'all');

$allDownloads    = getDownloads();
$admissionForms  = getAdmissionForms();

// ── Demo downloads (used when DB is empty) ───────────────────────────────────
$demoDownloads = [
    // Admission Forms
    ['id'=>1,'title'=>'Admission Form — FSc Pre-Medical 2025','category'=>'Admission Forms','file_name'=>'admission-premedical-2025.pdf','file_size'=>'420 KB','created_at'=>'2025-06-01','description'=>'Application form for FSc Part-I Pre-Medical group admissions.'],
    ['id'=>2,'title'=>'Admission Form — FSc Pre-Engineering 2025','category'=>'Admission Forms','file_name'=>'admission-preeng-2025.pdf','file_size'=>'420 KB','created_at'=>'2025-06-01','description'=>'Application form for FSc Part-I Pre-Engineering group admissions.'],
    ['id'=>3,'title'=>'Admission Form — ICS / ICom 2025','category'=>'Admission Forms','file_name'=>'admission-ics-icom-2025.pdf','file_size'=>'418 KB','created_at'=>'2025-06-01','description'=>'Combined application form for ICS and ICom group admissions.'],
    // Prospectus
    ['id'=>4,'title'=>'BMC Prospectus 2025–26','category'=>'Prospectus','file_name'=>'bmc-prospectus-2025-26.pdf','file_size'=>'3.2 MB','created_at'=>'2025-05-15','description'=>'Comprehensive college prospectus covering all programs, faculty, facilities, and admission procedures.'],
    ['id'=>5,'title'=>'Academic Programs Brochure 2025','category'=>'Prospectus','file_name'=>'bmc-programs-brochure-2025.pdf','file_size'=>'1.8 MB','created_at'=>'2025-04-20','description'=>'Concise overview of all academic programs offered at BMC.'],
    // Fee Structure
    ['id'=>6,'title'=>'Fee Structure 2025–26 (Morning Shift)','category'=>'Fee Structure','file_name'=>'fee-structure-morning-2025.pdf','file_size'=>'290 KB','created_at'=>'2025-06-01','description'=>'Detailed fee schedule for the morning shift including tuition, transport, and lab fees.'],
    ['id'=>7,'title'=>'Fee Structure 2025–26 (Evening Shift)','category'=>'Fee Structure','file_name'=>'fee-structure-evening-2025.pdf','file_size'=>'290 KB','created_at'=>'2025-06-01','description'=>'Detailed fee schedule for the evening shift.'],
    ['id'=>8,'title'=>'Scholarship & Concession Policy 2025','category'=>'Fee Structure','file_name'=>'scholarship-policy-2025.pdf','file_size'=>'215 KB','created_at'=>'2025-04-01','description'=>'Criteria and application procedure for merit scholarships and need-based concessions.'],
    // Academic Calendar
    ['id'=>9,'title'=>'Academic Calendar 2025–26','category'=>'Academic Calendar','file_name'=>'academic-calendar-2025-26.pdf','file_size'=>'380 KB','created_at'=>'2025-07-01','description'=>'Full-year academic calendar including term dates, holidays, exams, and events.'],
    ['id'=>10,'title'=>'Examination Schedule — Annual Exams 2025','category'=>'Academic Calendar','file_name'=>'exam-schedule-2025.pdf','file_size'=>'265 KB','created_at'=>'2025-04-10','description'=>'Date-sheet for FSc Part-I and Part-II annual examinations 2025.'],
    // Examination
    ['id'=>11,'title'=>'Examination Rules & Regulations','category'=>'Examination','file_name'=>'exam-rules-2025.pdf','file_size'=>'310 KB','created_at'=>'2025-03-01','description'=>'Rules governing examinations, attendance, and academic conduct at BMC.'],
    ['id'=>12,'title'=>'Roll Number Slip Application Form','category'=>'Examination','file_name'=>'roll-number-form.pdf','file_size'=>'180 KB','created_at'=>'2025-03-10','description'=>'Form to apply for roll number slips for board examinations.'],
    ['id'=>13,'title'=>'Internal Assessment Criteria 2025','category'=>'Examination','file_name'=>'internal-assessment-2025.pdf','file_size'=>'225 KB','created_at'=>'2025-02-15','description'=>'Breakdown of internal marks allocation for all subjects.'],
    // Notices
    ['id'=>14,'title'=>'General Notice — Academic Integrity Policy','category'=>'Notices','file_name'=>'academic-integrity-notice.pdf','file_size'=>'195 KB','created_at'=>'2025-05-01','description'=>'Formal notice on plagiarism, cheating, and academic misconduct policies.'],
    ['id'=>15,'title'=>'Transport Route Notification 2025–26','category'=>'Notices','file_name'=>'transport-routes-2025.pdf','file_size'=>'340 KB','created_at'=>'2025-07-15','description'=>'Updated bus routes and pick-up/drop-off schedules for the new academic year.'],
    // Policies
    ['id'=>16,'title'=>'Student Code of Conduct','category'=>'Policies','file_name'=>'code-of-conduct.pdf','file_size'=>'510 KB','created_at'=>'2025-01-01','description'=>'Comprehensive student conduct handbook covering disciplinary procedures and responsibilities.'],
    ['id'=>17,'title'=>'Attendance Policy 2025','category'=>'Policies','file_name'=>'attendance-policy-2025.pdf','file_size'=>'210 KB','created_at'=>'2025-01-01','description'=>'Attendance requirements and procedure for leave applications.'],
    ['id'=>18,'title'=>'Mobile Phone & Technology Use Policy','category'=>'Policies','file_name'=>'tech-policy-2025.pdf','file_size'=>'175 KB','created_at'=>'2025-01-15','description'=>'Guidelines on acceptable use of mobile devices and technology on campus.'],
];

$categoryOrder = ['Admission Forms', 'Prospectus', 'Fee Structure', 'Academic Calendar', 'Examination', 'Notices', 'Policies'];
$categoryIcons = [
    'Admission Forms'  => 'fa-file-alt',
    'Prospectus'       => 'fa-book-open',
    'Fee Structure'    => 'fa-money-bill-wave',
    'Academic Calendar'=> 'fa-calendar-alt',
    'Examination'      => 'fa-file-signature',
    'Notices'          => 'fa-bell',
    'Policies'         => 'fa-gavel',
];
$categoryColors = [
    'Admission Forms'  => 'primary',
    'Prospectus'       => 'success',
    'Fee Structure'    => 'warning',
    'Academic Calendar'=> 'info',
    'Examination'      => 'danger',
    'Notices'          => 'secondary',
    'Policies'         => 'dark',
];

// Use DB data if available, else demo
$sourceDownloads = !empty($allDownloads) ? $allDownloads : $demoDownloads;

// Group by category
$grouped = [];
foreach ($sourceDownloads as $dl) {
    $cat = $dl['category'] ?? 'General';
    $grouped[$cat][] = $dl;
}

// Honour category order
$orderedGrouped = [];
foreach ($categoryOrder as $cat) {
    if (isset($grouped[$cat])) {
        $orderedGrouped[$cat] = $grouped[$cat];
    }
}
// Append any remaining
foreach ($grouped as $cat => $items) {
    if (!isset($orderedGrouped[$cat])) {
        $orderedGrouped[$cat] = $items;
    }
}

include __DIR__ . '/includes/header.php';
?>

<!-- ── Page Hero ── -->
<div class="page-hero">
  <div class="container-xl">
    <h1 class="page-hero-title" data-aos="fade-up">Downloads</h1>
    <p class="page-hero-sub" data-aos="fade-up" data-aos-delay="100">
      Access admission forms, prospectus, fee schedules, academic calendars, and important documents
    </p>
  </div>
</div>
<div class="breadcrumb-wrap">
  <div class="container-xl">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php"><i class="fas fa-home me-1"></i>Home</a></li>
        <li class="breadcrumb-item active">Downloads</li>
      </ol>
    </nav>
  </div>
</div>

<!-- ── Admission Forms highlight ── -->
<?php $afList = !empty($admissionForms) ? $admissionForms : ($orderedGrouped['Admission Forms'] ?? []); ?>
<?php if (!empty($afList)): ?>
<section class="site-section sec-alt">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <span class="sec-label"><i class="fas fa-graduation-cap me-1"></i>Admissions Open</span>
      <h2 class="sec-title">Admission Forms 2025–26</h2>
    </div>
    <div class="row g-3">
      <?php foreach ($afList as $i => $af): ?>
      <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 80 ?>">
        <div class="admission-form-card">
          <div class="admission-form-icon"><i class="fas fa-file-alt"></i></div>
          <div class="admission-form-info">
            <h6 class="mb-1"><?= sh($af['title'] ?? $af['name'] ?? '') ?></h6>
            <?php if (!empty($af['description'])): ?>
              <p class="text-muted small mb-2"><?= sh(truncateText($af['description'], 80)) ?></p>
            <?php endif; ?>
            <?php if (!empty($af['file_size'])): ?>
              <span class="file-size-badge"><?= sh($af['file_size']) ?></span>
            <?php endif; ?>
          </div>
          <?php $fname = $af['file_name'] ?? $af['filename'] ?? ''; ?>
          <?php if ($fname): ?>
            <a href="<?= uploadUrl('forms', $fname) ?>" class="btn-download-sm" download>
              <i class="fas fa-download me-1"></i>Download
            </a>
          <?php else: ?>
            <span class="btn-download-sm btn-download-disabled"><i class="fas fa-clock me-1"></i>Soon</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── Category filter tabs + download sections ── -->
<section class="site-section">
  <div class="container-xl">
    <!-- Category tabs -->
    <div class="filter-tabs mb-5" data-aos="fade-up">
      <button class="filter-tab active" data-cat="all">All Documents</button>
      <?php foreach ($categoryOrder as $cat): ?>
        <?php if (isset($orderedGrouped[$cat])): ?>
          <button class="filter-tab" data-cat="<?= sh(slugify($cat)) ?>">
            <i class="fas <?= $categoryIcons[$cat] ?? 'fa-file' ?> me-1"></i><?= sh($cat) ?>
          </button>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <!-- Download categories -->
    <?php foreach ($orderedGrouped as $cat => $items): ?>
    <div class="download-category" data-cat-group="<?= sh(slugify($cat)) ?>">
      <div class="download-category-header" data-aos="fade-up">
        <div class="d-flex align-items-center gap-3">
          <div class="download-cat-icon bg-<?= $categoryColors[$cat] ?? 'primary' ?>">
            <i class="fas <?= $categoryIcons[$cat] ?? 'fa-file' ?>"></i>
          </div>
          <div>
            <h4 class="mb-0"><?= sh($cat) ?></h4>
            <span class="text-muted small"><?= count($items) ?> document<?= count($items) !== 1 ? 's' : '' ?></span>
          </div>
        </div>
      </div>
      <div class="download-list" data-aos="fade-up" data-aos-delay="60">
        <?php foreach ($items as $j => $dl): ?>
        <div class="download-item">
          <div class="download-item-icon">
            <?php
            $ext = strtolower(pathinfo($dl['file_name'] ?? $dl['filename'] ?? '', PATHINFO_EXTENSION));
            $extIcon = match($ext) {
                'pdf'  => 'fa-file-pdf text-danger',
                'doc','docx' => 'fa-file-word text-primary',
                'xls','xlsx' => 'fa-file-excel text-success',
                'zip'  => 'fa-file-archive text-warning',
                default => 'fa-file-alt text-muted',
            };
            ?>
            <i class="fas <?= $extIcon ?>"></i>
          </div>
          <div class="download-item-info">
            <h6 class="download-item-title"><?= sh($dl['title'] ?? $dl['name'] ?? '') ?></h6>
            <div class="download-item-meta">
              <?php if (!empty($dl['description'])): ?>
                <span class="text-muted small"><?= sh(truncateText($dl['description'], 100)) ?></span>
              <?php endif; ?>
              <div class="d-flex gap-3 mt-1">
                <?php if (!empty($dl['file_size'])): ?>
                  <span class="file-size-badge"><?= sh($dl['file_size']) ?></span>
                <?php endif; ?>
                <?php if (!empty($dl['created_at'])): ?>
                  <span class="text-muted small"><i class="fas fa-calendar me-1"></i><?= siteDate($dl['created_at']) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="download-item-action">
            <?php $fname = $dl['file_name'] ?? $dl['filename'] ?? ''; ?>
            <?php if ($fname): ?>
              <a href="<?= uploadUrl('downloads', $fname) ?>"
                 class="btn-download"
                 download
                 title="Download <?= sh($dl['title'] ?? '') ?>">
                <i class="fas fa-download me-2"></i>Download
              </a>
            <?php else: ?>
              <span class="btn-download btn-download-disabled text-muted">
                <i class="fas fa-clock me-2"></i>Coming Soon
              </span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($orderedGrouped)): ?>
    <div class="empty-state text-center py-5" data-aos="fade-up">
      <i class="fas fa-folder-open text-muted" style="font-size:3rem"></i>
      <h5 class="mt-3 text-muted">No downloads available at this time.</h5>
      <p class="text-muted">Please check back soon or <a href="<?= SITE_URL ?>/contact.php">contact us</a> for documents.</p>
    </div>
    <?php endif; ?>

  </div>
</section>

<!-- ── Help CTA ── -->
<section class="site-section sec-alt">
  <div class="container-xl" data-aos="fade-up">
    <div class="row align-items-center g-4">
      <div class="col-lg-8">
        <h4 class="mb-2">Can't find what you're looking for?</h4>
        <p class="text-muted mb-0">Contact the college office or visit the admissions department for additional documents and forms not listed here.</p>
      </div>
      <div class="col-lg-4 text-lg-end">
        <a href="<?= SITE_URL ?>/contact.php" class="btn-primary-custom me-2">
          <i class="fas fa-envelope me-2"></i>Contact Us
        </a>
        <a href="<?= SITE_URL ?>/notices.php" class="btn-outline-custom">
          <i class="fas fa-bell me-2"></i>Notices
        </a>
      </div>
    </div>
  </div>
</section>

<script>
// Category tab filter
document.querySelectorAll('.filter-tab[data-cat]').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.filter-tab[data-cat]').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    const cat = this.dataset.cat;
    document.querySelectorAll('.download-category').forEach(section => {
      if (cat === 'all' || section.dataset.catGroup === cat) {
        section.style.display = '';
      } else {
        section.style.display = 'none';
      }
    });
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

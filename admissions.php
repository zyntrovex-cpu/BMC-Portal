<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle    = 'Admissions';
$activePage   = 'admissions';
$forms        = getAdmissionForms();
$admissionOpen = getSetting('admission_open','1') === '1';
include __DIR__ . '/includes/header.php';
?>
<div class="page-hero">
  <div class="container-xl position-relative" style="z-index:1">
    <div class="page-hero-label">Join BMC</div>
    <h1 class="page-hero-title">Admissions <?= sh(getSetting('admission_year','2025-26')) ?></h1>
    <p class="page-hero-subtitle">Take the first step towards academic excellence at Bahria Model College</p>
  </div>
</div>
<div class="breadcrumb-wrap">
  <div class="container-xl"><nav><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php">Home</a></li>
    <li class="breadcrumb-item active">Admissions</li>
  </ol></nav></div>
</div>

<section class="site-section">
  <div class="container-xl">
    <!-- Status Banner -->
    <?php if ($admissionOpen): ?>
    <div class="alert d-flex align-items-center gap-3 mb-5" style="background:linear-gradient(135deg,#00b894,#00cec9);color:#fff;border:none;border-radius:var(--radius-lg);padding:20px 24px" data-aos="fade-up">
      <i class="fas fa-check-circle fa-2x flex-shrink-0"></i>
      <div>
        <h5 style="font-weight:800;margin-bottom:2px">Admissions are OPEN for <?= sh(getSetting('admission_year','2025-26')) ?></h5>
        <p style="margin:0;opacity:.9;font-size:.9rem">Download the admission form below and submit it to the Admissions Office along with required documents.</p>
      </div>
    </div>
    <?php else: ?>
    <div class="alert d-flex align-items-center gap-3 mb-5" style="background:var(--light-2);border:2px solid var(--border);border-radius:var(--radius-lg);padding:20px 24px" data-aos="fade-up">
      <i class="fas fa-clock fa-2x flex-shrink-0" style="color:var(--text-3)"></i>
      <div>
        <h5 style="font-weight:800;color:var(--primary);margin-bottom:2px">Admissions are currently CLOSED</h5>
        <p style="margin:0;color:var(--text-2);font-size:.9rem">Admissions for the next academic year will open soon. Please check back or contact us for more information.</p>
      </div>
    </div>
    <?php endif; ?>

    <div class="row g-5">
      <div class="col-lg-8">
        <!-- Download Forms -->
        <div class="mb-5" data-aos="fade-up">
          <div class="sec-label" style="justify-content:flex-start"><span>Downloads</span></div>
          <h3 class="sec-title mb-4" style="text-align:left;font-size:1.5rem">Admission Forms</h3>
          <?php if (!empty($forms)): ?>
          <div class="d-flex flex-column gap-2">
            <?php foreach ($forms as $f): ?>
            <div class="download-item">
              <div class="download-icon pdf"><i class="fas fa-file-pdf"></i></div>
              <div>
                <div class="download-name"><?= sh($f['title']) ?></div>
                <div class="download-cat">Admission Form <?= sh($f['year']) ?></div>
              </div>
              <a href="<?= uploadUrl('downloads', sh($f['filename'])) ?>" download class="download-btn"><i class="fas fa-download me-1"></i>Download</a>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="card-glass p-4">
            <div class="download-item" style="border:2px dashed var(--border);border-radius:var(--radius)">
              <div class="download-icon pdf"><i class="fas fa-file-pdf"></i></div>
              <div>
                <div class="download-name">Admission Form 2025-26</div>
                <div class="download-cat">Will be available when admissions open</div>
              </div>
              <span class="download-btn" style="color:var(--text-3)"><i class="fas fa-clock me-1"></i>Coming Soon</span>
            </div>
            <p style="font-size:.83rem;color:var(--text-2);margin-top:12px;margin-bottom:0"><i class="fas fa-info-circle me-1 text-secondary"></i> Admission forms will be available for download once admissions open. Alternatively, collect a form from the Admissions Office on campus.</p>
          </div>
          <?php endif; ?>
        </div>

        <!-- Procedure -->
        <div class="mb-5" data-aos="fade-up">
          <div class="sec-label" style="justify-content:flex-start"><span>How to Apply</span></div>
          <h3 class="sec-title mb-4" style="text-align:left;font-size:1.5rem">Admission Procedure</h3>
          <div class="d-flex flex-column gap-3">
            <?php foreach ([['Download Form','Download the admission form from this page or collect it from the Admissions Office at the campus.'],['Fill Application','Complete all sections of the form clearly and accurately. Attach a recent passport-size photograph.'],['Attach Documents','Compile all required documents (see checklist on the right) and attach them to the completed form.'],['Submit at Office','Submit the completed form and documents to the Admissions Office (Block A, Ground Floor) during office hours.'],['Fee Submission','Pay the prescribed admission fee at the accounts office and obtain a fee receipt.'],['Await Confirmation','Successful candidates will be notified and issued a merit list position. Attend the orientation on the scheduled date.']] as $i => $step): ?>
            <div class="admission-step">
              <div class="step-num"><?= $i+1 ?></div>
              <div>
                <div class="step-title"><?= $step[0] ?></div>
                <div class="step-desc"><?= $step[1] ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- FAQ -->
        <div data-aos="fade-up">
          <div class="sec-label" style="justify-content:flex-start"><span>FAQ</span></div>
          <h3 class="sec-title mb-4" style="text-align:left;font-size:1.5rem">Frequently Asked Questions</h3>
          <div style="border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden">
            <?php foreach ([
              ['Is there an online application?', 'No. BMC does not process online applications. Admission forms must be downloaded and submitted physically at the Admissions Office.'],
              ['What is the minimum percentage for FSc admission?', 'For FSc Pre-Medical and Pre-Engineering, a minimum of 60% in Matric (SSC) is required. For FA and ICS, a minimum of 50% is required.'],
              ['Does BMC offer scholarships?', 'Yes. Merit-based and need-based scholarships are available under the Bahria Foundation scheme. Ask at the Admissions Office for details.'],
              ['Can I apply for multiple programs?', 'You must select one primary program on your application. You may indicate a preference for an alternate program if your first choice is not available.'],
              ['What subjects are required for Pre-Medical?', 'You must have studied Biology, Chemistry, Physics or Mathematics in Matric/SSC. Computer Science students may apply for ICS.'],
              ['Are there reserved seats?', 'Yes, a portion of seats are reserved for children of Bahria Foundation employees and Karachi Port area residents.'],
              ['When is the merit list announced?', 'Merit lists are typically announced within 2 weeks of the submission deadline. Results are posted on the college notice board and this website.'],
              ['What are the office hours for the Admissions Office?', 'Monday to Friday: 8:00 AM – 3:00 PM. Saturday: 8:00 AM – 12:00 PM. Closed on Sundays and public holidays.'],
            ] as $faq): ?>
            <details style="border-bottom:1px solid var(--border)">
              <summary style="padding:14px 18px;cursor:pointer;font-weight:600;color:var(--primary);font-size:.9rem"><?= $faq[0] ?></summary>
              <div style="padding:4px 18px 14px;color:var(--text-2);font-size:.87rem;line-height:1.7"><?= $faq[1] ?></div>
            </details>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="col-lg-4">
        <div style="position:sticky;top:100px">
          <!-- Eligibility -->
          <div class="card-glass p-4 mb-4" data-aos="fade-left">
            <h5 style="font-weight:800;color:var(--primary);margin-bottom:16px"><i class="fas fa-check-circle me-2 text-secondary"></i>Eligibility Criteria</h5>
            <?php foreach ([
              ['FSc Pre-Medical / Pre-Engineering','Min. 60% in Matric (SSC) with Science subjects'],
              ['FA (Arts)','Min. 50% in Matric or equivalent'],
              ['ICS (Computer Science)','Min. 55% in Matric with Mathematics'],
              ['ICOM (Commerce)','Min. 50% in Matric in any group'],
            ] as $el): ?>
            <div style="padding:10px 0;border-bottom:1px solid var(--border)">
              <div style="font-weight:700;color:var(--primary);font-size:.86rem;margin-bottom:2px"><?= $el[0] ?></div>
              <div style="color:var(--text-2);font-size:.8rem"><?= $el[1] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <!-- Required Documents -->
          <div class="card-glass p-4 mb-4" data-aos="fade-left" data-aos-delay="100">
            <h5 style="font-weight:800;color:var(--primary);margin-bottom:16px"><i class="fas fa-folder-open me-2 text-secondary"></i>Required Documents</h5>
            <?php foreach (['Completed Admission Form','Matric Certificate / DMC (Original + 2 copies)','School Leaving Certificate','National ID Card / B-Form (Copy)','Father\'s / Guardian\'s CNIC (Copy)','4 Recent Passport-Size Photos','Character Certificate from Previous Institution','Migration Certificate (if applicable)'] as $doc): ?>
            <div style="display:flex;gap:8px;padding:6px 0;font-size:.84rem;color:var(--text-2)">
              <i class="fas fa-check mt-1" style="color:var(--green);flex-shrink:0;font-size:.75rem"></i><?= $doc ?>
            </div>
            <?php endforeach; ?>
          </div>
          <!-- Important Dates -->
          <div class="card-glass p-4" data-aos="fade-left" data-aos-delay="150">
            <h5 style="font-weight:800;color:var(--primary);margin-bottom:16px"><i class="fas fa-calendar-alt me-2 text-secondary"></i>Important Dates</h5>
            <?php foreach ([['Form Distribution Starts','1 July 2025'],['Last Date to Submit','31 July 2025'],['First Merit List','5 August 2025'],['Second Merit List','12 August 2025'],['Classes Begin','20 August 2025']] as $d): ?>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:.83rem">
              <span style="color:var(--text-2)"><?= $d[0] ?></span>
              <span style="font-weight:700;color:var(--primary)"><?= $d[1] ?></span>
            </div>
            <?php endforeach; ?>
            <div style="margin-top:14px;padding:10px;background:var(--light-2);border-radius:8px;font-size:.78rem;color:var(--text-2)">
              <i class="fas fa-info-circle me-1 text-secondary"></i> Dates are indicative and subject to change. Check the notice board for updates.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Fee Structure -->
<section class="site-section sec-alt">
  <div class="container-xl" data-aos="fade-up">
    <div class="section-header">
      <div class="sec-label"><span>Fees</span></div>
      <h2 class="sec-title">Fee Structure 2025-26</h2>
      <p class="sec-subtitle">Transparent, competitive fees with scholarship opportunities available</p>
    </div>
    <div class="table-responsive">
      <table class="table table-hover" style="font-size:.88rem">
        <thead style="background:var(--primary);color:#fff">
          <tr><th>Program</th><th>Admission Fee</th><th>Monthly Tuition</th><th>Security Deposit</th><th>Annual Misc.</th></tr>
        </thead>
        <tbody>
          <?php foreach ([['FSc Pre-Medical','PKR 5,000','PKR 4,500/month','PKR 2,000 (refundable)','PKR 3,000'],['FSc Pre-Engineering','PKR 5,000','PKR 4,500/month','PKR 2,000 (refundable)','PKR 3,000'],['FA (Arts)','PKR 4,000','PKR 3,500/month','PKR 2,000 (refundable)','PKR 2,500'],['ICS (Computer Science)','PKR 5,000','PKR 4,500/month','PKR 2,000 (refundable)','PKR 3,000'],['ICOM (Commerce)','PKR 4,500','PKR 4,000/month','PKR 2,000 (refundable)','PKR 2,500']] as $row): ?>
          <tr><td><?= $row[0] ?></td><td><?= $row[1] ?></td><td><?= $row[2] ?></td><td><?= $row[3] ?></td><td><?= $row[4] ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="font-size:.8rem;color:var(--text-3);margin-top:8px"><i class="fas fa-asterisk me-1"></i>Fees are subject to annual revision. The above are indicative figures. Confirmed fees are stated on the admission form. Scholarships may reduce fees significantly.</p>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

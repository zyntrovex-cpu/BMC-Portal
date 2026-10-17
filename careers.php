<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle  = 'Careers at BMC';
$pageDesc   = 'Join the Bahria Model College team. We seek passionate educators and dedicated professionals committed to shaping the next generation of leaders.';
$activePage = 'careers';
include __DIR__ . '/includes/header.php';

$careers = getCareers();

// Demo postings used when the database returns nothing
$demoCareers = [
  [
    'id'          => 0,
    'title'       => 'Lecturer — Physics',
    'department'  => 'Science & Technology',
    'type'        => 'Full-Time',
    'deadline'    => date('Y-m-d', strtotime('+30 days')),
    'description' => 'We are seeking a qualified and experienced Lecturer in Physics to join our Science department. The successful candidate will teach FSc (Pre-Engineering) students, prepare lesson plans in line with the BIEK curriculum, conduct practical demonstrations in the physics laboratory, and participate in the academic development of the department.',
    'requirements'=> 'M.Sc. / M.Phil Physics | B.Ed preferred | 2+ years teaching experience',
    'positions'   => 1,
  ],
  [
    'id'          => 0,
    'title'       => 'Lecturer — Chemistry',
    'department'  => 'Science & Technology',
    'type'        => 'Full-Time',
    'deadline'    => date('Y-m-d', strtotime('+28 days')),
    'description' => 'BMC invites applications from suitably qualified individuals for the position of Lecturer in Chemistry. The role involves teaching FSc Pre-Medical and Pre-Engineering groups, maintaining laboratory safety standards, preparing students for Board examinations, and contributing to departmental academic activities.',
    'requirements'=> 'M.Sc. / M.Phil Chemistry | B.Ed preferred | Board examination experience an asset',
    'positions'   => 1,
  ],
  [
    'id'          => 0,
    'title'       => 'Computer Laboratory Technician',
    'department'  => 'Computer Centre',
    'type'        => 'Full-Time',
    'deadline'    => date('Y-m-d', strtotime('+21 days')),
    'description' => 'The Computer Centre requires a technically skilled Laboratory Technician to manage and maintain the college\'s 200-workstation facility. Responsibilities include hardware and software maintenance, network troubleshooting, supporting computer science classes, and ensuring all laboratory equipment is operational at all times.',
    'requirements'=> 'B.Sc. Computer Science / IT | Hardware networking certifications preferred | 1+ year experience',
    'positions'   => 1,
  ],
  [
    'id'          => 0,
    'title'       => 'Administrative Officer',
    'department'  => 'General Administration',
    'type'        => 'Full-Time',
    'deadline'    => date('Y-m-d', strtotime('+25 days')),
    'description' => 'Bahria Model College is looking for a capable Administrative Officer to support the Registrar\'s office. Duties include student record management, correspondence, scheduling of meetings and examinations, coordination with parents and external stakeholders, and general office administration.',
    'requirements'=> 'BBA / B.Com or equivalent | Proficiency in MS Office | Strong organisational and communication skills',
    'positions'   => 2,
  ],
];

$listings = !empty($careers) ? $careers : $demoCareers;
$isEmpty  = empty($careers);
?>

<!-- ══ Page Hero ══ -->
<section class="page-hero">
  <div class="deco-circle" style="width:380px;height:380px;top:-100px;right:-60px"></div>
  <div class="deco-circle" style="width:180px;height:180px;bottom:-50px;left:12%"></div>
  <div class="container-xl" style="position:relative;z-index:1">
    <div class="page-hero-label" data-aos="fade-up"><i class="fas fa-briefcase me-2"></i>Join Our Team</div>
    <h1 class="page-hero-title" data-aos="fade-up" data-aos-delay="80">Careers at BMC</h1>
    <p class="page-hero-subtitle" data-aos="fade-up" data-aos-delay="160" style="max-width:540px">We seek passionate educators and dedicated professionals who share our commitment to academic excellence and student success.</p>
    <div data-aos="fade-up" data-aos-delay="240" style="margin-top:24px;display:flex;flex-wrap:wrap;gap:16px;align-items:center">
      <div style="background:rgba(249,202,36,.18);border:1px solid rgba(249,202,36,.4);border-radius:99px;padding:7px 18px;font-size:.8rem;font-weight:700;color:var(--accent);display:inline-flex;align-items:center;gap:8px">
        <i class="fas fa-circle" style="font-size:.45rem"></i>
        <?= count($listings) ?> Open Position<?= count($listings) !== 1 ? 's' : '' ?> Available
      </div>
      <div style="color:rgba(255,255,255,.6);font-size:.82rem"><i class="fas fa-map-marker-alt me-1"></i>Bin Qasim, Karachi</div>
    </div>
  </div>
</section>

<!-- ══ Breadcrumb ══ -->
<div class="breadcrumb-wrap">
  <div class="container-xl">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Careers</li>
      </ol>
    </nav>
  </div>
</div>

<!-- ══ No Online Applications Notice ══ -->
<div style="background:linear-gradient(90deg,rgba(9,132,227,.08),rgba(9,132,227,.04));border-bottom:1px solid rgba(9,132,227,.15);padding:14px 0">
  <div class="container-xl">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <div style="width:36px;height:36px;background:rgba(9,132,227,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i class="fas fa-info-circle" style="color:var(--secondary)"></i>
      </div>
      <div style="font-size:.88rem;color:var(--text-2);line-height:1.5">
        <strong style="color:var(--primary)">Important:</strong> BMC does not accept online applications. All applications must be submitted via postal mail or in person to the Registrar's Office. See the <a href="#how-to-apply" style="color:var(--secondary);font-weight:600">How to Apply</a> section below for full instructions.
      </div>
    </div>
  </div>
</div>

<!-- ══ Current Openings ══ -->
<section class="site-section">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Current Openings</span></div>
      <h2 class="sec-title">Open Positions</h2>
      <p class="sec-subtitle">
        <?php if ($isEmpty): ?>
        The following positions are currently available at Bahria Model College.
        <?php else: ?>
        We currently have <?= count($listings) ?> position<?= count($listings) !== 1 ? 's' : '' ?> available. Review each carefully before applying.
        <?php endif; ?>
      </p>
    </div>

    <div class="row g-4">
      <?php foreach ($listings as $i => $job): ?>
      <?php
        $deadline   = $isEmpty ? $job['deadline'] : ($job['deadline'] ?? null);
        $deptName   = $isEmpty ? $job['department'] : ($job['department'] ?? 'General');
        $isUrgent   = $deadline && (strtotime($deadline) - time()) < 7 * 86400;
        $typeLabel  = $isEmpty ? $job['type'] : ($job['type'] ?? 'Full-Time');
        $desc       = $isEmpty ? $job['description'] : ($job['description'] ?? '');
        $reqs       = $isEmpty ? $job['requirements'] : ($job['requirements'] ?? '');
        $positions  = $isEmpty ? $job['positions']    : ($job['positions']   ?? 1);
      ?>
      <div class="col-lg-6" data-aos="fade-up" data-aos-delay="<?= ($i % 2) * 80 ?>">
        <div class="career-card h-100" style="display:flex;flex-direction:column;position:relative;overflow:hidden">
          <?php if ($isUrgent): ?>
          <div style="position:absolute;top:0;right:0;background:var(--red);color:#fff;font-size:.68rem;font-weight:700;letter-spacing:.06em;padding:4px 12px;border-radius:0 var(--radius) 0 var(--radius)">CLOSING SOON</div>
          <?php endif; ?>

          <!-- Card header -->
          <div style="margin-bottom:16px">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:8px">
              <h3 class="career-title" style="font-size:1.05rem;margin:0"><?= sh($job['title']) ?></h3>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
              <span class="career-dept"><i class="fas fa-building me-1"></i><?= sh($deptName) ?></span>
              <span style="color:var(--text-3);font-size:.78rem">•</span>
              <span style="font-size:.76rem;background:rgba(9,132,227,.09);color:var(--secondary);padding:3px 9px;border-radius:99px;font-weight:600"><?= sh($typeLabel) ?></span>
              <?php if ($positions > 1): ?>
              <span style="font-size:.76rem;background:rgba(39,174,96,.1);color:#1a7a40;padding:3px 9px;border-radius:99px;font-weight:600"><?= (int)$positions ?> Vacancies</span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Deadline -->
          <?php if ($deadline): ?>
          <div style="display:flex;align-items:center;gap:6px;margin-bottom:14px;padding:8px 12px;background:<?= $isUrgent?'rgba(225,112,85,.08)':'var(--light-2)' ?>;border-radius:var(--radius);border-left:3px solid <?= $isUrgent?'var(--red)':'var(--border)' ?>">
            <i class="fas fa-calendar-times" style="color:var(--red);font-size:.82rem"></i>
            <span class="career-deadline"><strong>Application Deadline:</strong> <?= siteDate($deadline) ?></span>
          </div>
          <?php endif; ?>

          <!-- Description -->
          <p style="font-size:.86rem;color:var(--text-2);line-height:1.75;flex:1;margin-bottom:16px"><?= sh(truncateText($desc, 240)) ?></p>

          <!-- Requirements -->
          <?php if ($reqs): ?>
          <div style="background:var(--light-2);border-radius:var(--radius);padding:12px 14px;margin-bottom:16px">
            <div style="font-size:.73rem;text-transform:uppercase;letter-spacing:.07em;font-weight:700;color:var(--text-3);margin-bottom:6px">Minimum Requirements</div>
            <div style="font-size:.83rem;color:var(--text-2);line-height:1.55"><?= sh($reqs) ?></div>
          </div>
          <?php endif; ?>

          <!-- Footer -->
          <div style="border-top:1px solid var(--border);padding-top:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
            <div style="font-size:.78rem;color:var(--text-3);display:flex;align-items:center;gap:6px">
              <i class="fas fa-map-marker-alt" style="color:var(--secondary)"></i> Bin Qasim, Karachi
            </div>
            <a href="#how-to-apply" style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,var(--secondary),var(--primary-l));color:#fff;padding:8px 18px;border-radius:var(--radius);font-size:.82rem;font-weight:600;text-decoration:none;transition:all .25s;box-shadow:0 3px 10px rgba(9,132,227,.25)">
              <i class="fas fa-paper-plane"></i> How to Apply
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if (!$isEmpty && empty($listings)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--text-2)">
      <i class="fas fa-briefcase" style="font-size:3rem;opacity:.25;display:block;margin-bottom:16px"></i>
      <h4 style="color:var(--primary);margin-bottom:8px">No Open Positions</h4>
      <p style="font-size:.9rem">There are no vacancies at the moment. Please check back soon or register your interest with the HR Office.</p>
    </div>
    <?php endif; ?>

  </div>
</section>

<!-- ══ How to Apply ══ -->
<section class="site-section sec-alt" id="how-to-apply">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Application Process</span></div>
      <h2 class="sec-title">How to Apply</h2>
      <p class="sec-subtitle">BMC follows a formal, postal application process. Please read these instructions carefully before preparing your application package.</p>
    </div>

    <div class="row g-4 align-items-start">
      <div class="col-lg-7" data-aos="fade-right">
        <div style="display:flex;flex-direction:column;gap:16px">

          <?php
          $steps = [
            ['fa-file-alt',       'Prepare Your Cover Letter',
             'Write a formal cover letter addressed to the Principal, Bahria Model College. State the position you are applying for clearly in the subject line. Describe your relevant experience, teaching philosophy, and why you wish to join BMC.'],
            ['fa-file-invoice',   'Compile Your Curriculum Vitae',
             'Prepare a comprehensive CV including: full name and CNIC number, educational qualifications with institutions and years, complete employment history, names of two professional references (with contact details), and your current postal address and phone number.'],
            ['fa-copy',           'Attach Supporting Documents',
             'Include attested photocopies of: all academic certificates and transcripts, CNIC, teaching registration certificate (if applicable), experience letters from previous employers, and any relevant training or certification documents.'],
            ['fa-envelope-open-text', 'Submit via Postal Mail or In Person',
             'Place all documents in a clearly labelled A4 envelope marked with the position title. Send via registered post or deliver in person to the Registrar\'s Office at BMC between 9:00 AM and 2:00 PM, Monday to Friday.'],
            ['fa-phone-alt',      'Await Shortlisting',
             'Only shortlisted candidates will be contacted for a written test and interview. If you have not been contacted within three weeks of the application deadline, consider your application unsuccessful for this round.'],
          ];
          foreach ($steps as $idx => $step):
          ?>
          <div class="admission-step" data-aos="fade-up" data-aos-delay="<?= $idx * 60 ?>">
            <div class="step-num"><?= $idx + 1 ?></div>
            <div>
              <div class="step-title"><i class="fas <?= $step[0] ?> me-2" style="color:var(--secondary)"></i><?= $step[1] ?></div>
              <div class="step-desc" style="margin-top:5px"><?= $step[2] ?></div>
            </div>
          </div>
          <?php endforeach; ?>

        </div>
      </div>

      <div class="col-lg-5" data-aos="fade-left">

        <!-- Important Note box -->
        <div style="background:linear-gradient(135deg,rgba(225,112,85,.08),rgba(225,112,85,.04));border:1.5px solid rgba(225,112,85,.3);border-radius:var(--radius-lg);padding:24px;margin-bottom:20px">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
            <div style="width:38px;height:38px;background:var(--red);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.95rem;flex-shrink:0"><i class="fas fa-exclamation-triangle"></i></div>
            <div style="font-weight:800;font-size:.95rem;color:var(--primary)">No Online Applications</div>
          </div>
          <p style="font-size:.86rem;color:var(--text-2);line-height:1.7;margin:0">Applications submitted by email, WhatsApp, or any online form will <strong>not</strong> be entertained. Only hard-copy applications received by the Registrar's Office by the stated deadline will be considered.</p>
        </div>

        <!-- Checklist -->
        <div style="background:var(--white);border-radius:var(--radius-lg);padding:24px;box-shadow:var(--shadow)">
          <div style="font-weight:700;color:var(--primary);font-family:var(--font-heading);font-size:.95rem;margin-bottom:16px"><i class="fas fa-clipboard-check me-2" style="color:var(--secondary)"></i>Application Checklist</div>
          <?php
          $checklist = [
            'Cover letter (addressed to the Principal)',
            'Curriculum Vitae (detailed)',
            'Attested copy of highest degree',
            'Attested copies of all academic certificates',
            'CNIC copy (attested)',
            'Experience letters (if applicable)',
            '2 recent passport-size photographs',
            'Teaching registration certificate (if applicable)',
          ];
          foreach ($checklist as $item): ?>
          <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);font-size:.86rem;color:var(--text-2)">
            <i class="fas fa-check-square" style="color:var(--green);margin-top:1px;flex-shrink:0"></i><?= $item ?>
          </div>
          <?php endforeach; ?>
        </div>

      </div>
    </div>
  </div>
</section>

<!-- ══ HR Contact ══ -->
<section class="site-section">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Get in Touch</span></div>
      <h2 class="sec-title">HR &amp; Registrar's Office</h2>
      <p class="sec-subtitle">For enquiries about advertised positions or to request further information, contact our HR office during working hours.</p>
    </div>

    <div class="row g-4 justify-content-center">

      <!-- Postal Address -->
      <div class="col-md-6 col-lg-4" data-aos="fade-up">
        <div class="card-glass h-100" style="padding:28px;text-align:center">
          <div style="width:56px;height:56px;background:linear-gradient(135deg,var(--primary),var(--primary-l));border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#fff;font-size:1.3rem">
            <i class="fas fa-mail-bulk"></i>
          </div>
          <h5 style="font-family:var(--font-heading);font-weight:700;color:var(--primary);margin-bottom:8px">Postal Address</h5>
          <p style="font-size:.87rem;color:var(--text-2);line-height:1.7;margin:0">
            <strong>The Registrar</strong><br>
            Bahria Model College<br>
            <?= sh(getSetting('site_address','Bin Qasim, Karachi, Pakistan')) ?>
          </p>
        </div>
      </div>

      <!-- Phone -->
      <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="80">
        <div class="card-glass h-100" style="padding:28px;text-align:center">
          <div style="width:56px;height:56px;background:linear-gradient(135deg,var(--secondary),var(--teal));border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#fff;font-size:1.3rem">
            <i class="fas fa-phone-alt"></i>
          </div>
          <h5 style="font-family:var(--font-heading);font-weight:700;color:var(--primary);margin-bottom:8px">Telephone</h5>
          <p style="font-size:.87rem;color:var(--text-2);line-height:1.7;margin:0">
            <strong>Main Reception:</strong><br>
            <a href="tel:<?= sh(getSetting('site_phone')) ?>" style="color:var(--secondary);font-weight:600"><?= sh(getSetting('site_phone','+92-21-3470-0000')) ?></a><br>
            <span style="font-size:.8rem;color:var(--text-3)">Ext. 102 — Registrar's Office</span><br>
            <span style="font-size:.8rem;color:var(--text-3)">Mon – Sat, 9:00 AM – 2:00 PM</span>
          </p>
        </div>
      </div>

      <!-- Email -->
      <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="160">
        <div class="card-glass h-100" style="padding:28px;text-align:center">
          <div style="width:56px;height:56px;background:linear-gradient(135deg,var(--accent),var(--accent-d));border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:var(--primary);font-size:1.3rem">
            <i class="fas fa-envelope"></i>
          </div>
          <h5 style="font-family:var(--font-heading);font-weight:700;color:var(--primary);margin-bottom:8px">Email Enquiries</h5>
          <p style="font-size:.87rem;color:var(--text-2);line-height:1.7;margin:0">
            <strong>HR / Registrar:</strong><br>
            <a href="mailto:registrar@bmc.edu.pk" style="color:var(--secondary);font-weight:600">registrar@bmc.edu.pk</a><br>
            <span style="font-size:.8rem;color:var(--text-3)">General queries only — do not send<br>application documents by email.</span>
          </p>
        </div>
      </div>

    </div>

    <!-- Equal Opportunity Notice -->
    <div data-aos="fade-up" style="margin-top:40px;text-align:center;padding:20px 24px;background:var(--light-2);border-radius:var(--radius-lg);max-width:680px;margin-left:auto;margin-right:auto">
      <i class="fas fa-balance-scale me-2" style="color:var(--secondary)"></i>
      <span style="font-size:.85rem;color:var(--text-2)"><strong style="color:var(--primary)">Equal Opportunity Employer.</strong> Bahria Model College is committed to equal opportunity in employment. All applicants will be considered without discrimination on the basis of gender, ethnicity, or religion.</span>
    </div>

  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

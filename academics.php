<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle  = 'Academics';
$activePage = 'academics';
$tab        = trim($_GET['tab'] ?? 'programs');
$deptId     = (int)($_GET['dept'] ?? 0);

$departments = getDepartments();
$programs    = getPrograms($deptId);

include __DIR__ . '/includes/header.php';
?>
<div class="page-hero">
  <div class="container-xl position-relative" style="z-index:1">
    <div class="page-hero-label">Academics</div>
    <h1 class="page-hero-title">Academic Excellence</h1>
    <p class="page-hero-subtitle">Rigorous, broad-based programmes designed to unlock every student's potential</p>
  </div>
</div>
<div class="breadcrumb-wrap">
  <div class="container-xl"><nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php">Home</a></li>
    <li class="breadcrumb-item active">Academics</li>
  </ol></nav></div>
</div>

<!-- Tab navigation -->
<div style="background:var(--light-2);border-bottom:1px solid var(--border);position:sticky;top:72px;z-index:100">
  <div class="container-xl">
    <div class="d-flex overflow-auto" style="gap:0;scrollbar-width:none">
      <?php
      $tabs = [
        'programs'    => ['fa-graduation-cap','Programmes'],
        'departments' => ['fa-building','Departments'],
        'calendar'    => ['fa-calendar-alt','Academic Calendar'],
        'examination' => ['fa-file-signature','Examination'],
        'library'     => ['fa-book-open','Library & Labs'],
        'rules'       => ['fa-gavel','Rules & Conduct'],
      ];
      foreach ($tabs as $key => [$icon,$label]):
        $active = $tab === $key ? 'border-bottom:3px solid var(--primary);color:var(--primary);font-weight:700' : 'color:var(--text-2)';
      ?>
      <a href="?tab=<?= $key ?>" style="padding:14px 20px;white-space:nowrap;font-size:.85rem;text-decoration:none;display:flex;align-items:center;gap:6px;<?= $active ?>">
        <i class="fas <?= $icon ?>"></i><?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<section class="site-section">
  <div class="container-xl">

  <?php if ($tab === 'programs'): ?>
  <!-- ── Programmes ── -->
  <div class="section-header" data-aos="fade-up">
    <div class="sec-label"><span>What We Offer</span></div>
    <h2 class="sec-title">Academic Programmes</h2>
    <p class="sec-subtitle">Choose from a range of intermediate programmes recognised by the Board of Intermediate Education Karachi</p>
  </div>

  <?php
  $demoPrograms = [
    ['id'=>1,'name'=>'FSc Pre-Medical','dept_name'=>'Science','code'=>'FSCPM','duration'=>'2 Years','seats'=>120,'description'=>'Prepares students for MDCAT and entry into medical/dental colleges. Core subjects: Biology, Chemistry, Physics with English, Urdu, Islamiat, and Pakistan Studies.','highlights'=>['MDCAT preparation classes','Dedicated biology labs','Regular practicals & dissections','Top-university placements'],'color'=>'#00b894'],
    ['id'=>2,'name'=>'FSc Pre-Engineering','dept_name'=>'Science','code'=>'FSCPE','duration'=>'2 Years','seats'=>120,'description'=>'Rigorous science stream for aspiring engineers and technical professionals. Core subjects: Mathematics, Physics, Chemistry.','highlights'=>['ECAT preparation support','Physics & chemistry labs','Mathematics olympiad training','Engineering university placements'],'color'=>'#0984e3'],
    ['id'=>3,'name'=>'ICS (Computer Science)','dept_name'=>'Science & IT','code'=>'ICS','duration'=>'2 Years','seats'=>80,'description'=>'Combines Computer Science with Mathematics & Statistics. Ideal for students aiming for software engineering, IT, or data science careers.','highlights'=>['Modern computer laboratory','Programming in C++ & Python','Database fundamentals','NTS/MDCAT preparation'],'color'=>'#6c5ce7'],
    ['id'=>4,'name'=>'FA (Arts)','dept_name'=>'Arts & Humanities','code'=>'FA','duration'=>'2 Years','seats'=>80,'description'=>'Broad arts curriculum covering languages, social sciences, and creative subjects. Optional subjects include Economics, Psychology, Fine Arts.','highlights'=>['Critical thinking focus','Language proficiency programmes','Media & communication electives','Social science projects'],'color'=>'#e17055'],
    ['id'=>5,'name'=>'ICOM (Commerce)','dept_name'=>'Commerce','code'=>'ICOM','duration'=>'2 Years','seats'=>80,'description'=>'Grounding in business, economics, and accounting principles. Core subjects: Accounting, Economics, Business Mathematics, Commerce.','highlights'=>['CA/ACCA foundation support','Business case studies','Accounting software training','Commerce olympiad participation'],'color'=>'#f9ca24'],
  ];
  $progList = !empty($programs) ? $programs : $demoPrograms;
  ?>

  <!-- Department filter -->
  <?php if (!empty($departments)): ?>
  <div class="d-flex flex-wrap gap-2 mb-5" data-aos="fade-up">
    <a href="?tab=programs" class="gallery-filter-btn <?= $deptId===0?'active':'' ?>">All Departments</a>
    <?php foreach ($departments as $d): ?>
    <a href="?tab=programs&dept=<?= $d['id'] ?>" class="gallery-filter-btn <?= $deptId===$d['id']?'active':'' ?>"><?= sh($d['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="row g-4">
    <?php foreach ($progList as $i => $p): ?>
    <?php $color = $p['color'] ?? ['#00b894','#0984e3','#6c5ce7','#e17055','#f9ca24'][$i%5]; ?>
    <div class="col-lg-6" data-aos="fade-up" data-aos-delay="<?= ($i%2)*80 ?>">
      <div class="card-glass h-100" style="border-left:4px solid <?= $color ?>;overflow:hidden">
        <div style="padding:24px 24px 0">
          <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
            <div>
              <span style="font-size:.73rem;font-weight:700;letter-spacing:.06em;color:<?= $color ?>;text-transform:uppercase"><?= sh($p['dept_name'] ?? '') ?></span>
              <h4 style="font-weight:800;color:var(--primary);margin:4px 0 0"><?= sh($p['name']) ?></h4>
            </div>
            <div style="text-align:right;flex-shrink:0">
              <?php if (!empty($p['code'])): ?>
              <span class="badge" style="background:<?= $color ?>20;color:<?= $color ?>;font-size:.7rem;font-weight:700"><?= sh($p['code']) ?></span>
              <?php endif; ?>
              <?php if (!empty($p['duration'])): ?>
              <div style="font-size:.76rem;color:var(--text-3);margin-top:4px"><i class="fas fa-clock me-1"></i><?= sh($p['duration']) ?></div>
              <?php endif; ?>
              <?php if (!empty($p['seats'])): ?>
              <div style="font-size:.76rem;color:var(--text-3);margin-top:2px"><i class="fas fa-users me-1"></i><?= sh($p['seats']) ?> seats</div>
              <?php endif; ?>
            </div>
          </div>
          <?php if (!empty($p['description'])): ?>
          <p style="font-size:.87rem;color:var(--text-2);line-height:1.7;margin-bottom:16px"><?= sh($p['description']) ?></p>
          <?php endif; ?>
        </div>
        <?php $highlights = $p['highlights'] ?? []; if (!empty($highlights)): ?>
        <div style="background:var(--light-2);padding:16px 24px 20px;margin-top:4px">
          <div style="font-size:.76rem;font-weight:700;color:var(--text-3);margin-bottom:10px;text-transform:uppercase;letter-spacing:.06em">Programme Highlights</div>
          <div class="row g-1">
            <?php foreach ($highlights as $h): ?>
            <div class="col-6" style="display:flex;align-items:flex-start;gap:6px;font-size:.8rem;color:var(--text-2)">
              <i class="fas fa-check-circle mt-1 flex-shrink-0" style="color:<?= $color ?>;font-size:.7rem"></i><?= sh($h) ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        <div style="padding:14px 24px;border-top:1px solid var(--border);display:flex;gap:12px">
          <a href="<?= SITE_URL ?>/admissions.php" class="btn-primary-custom" style="text-decoration:none;font-size:.82rem;padding:8px 16px">
            <i class="fas fa-graduation-cap me-1"></i>Apply Now
          </a>
          <a href="<?= SITE_URL ?>/faculty.php" class="btn-outline-custom" style="text-decoration:none;font-size:.82rem;padding:8px 16px">
            <i class="fas fa-chalkboard-teacher me-1"></i>Meet Faculty
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Accreditation note -->
  <div class="highlight-box mt-5" data-aos="fade-up">
    <div class="d-flex align-items-start gap-3">
      <i class="fas fa-certificate fa-2x flex-shrink-0" style="color:var(--accent)"></i>
      <div>
        <h6 style="font-weight:800;color:var(--primary);margin-bottom:4px">Recognised & Affiliated</h6>
        <p style="font-size:.87rem;color:var(--text-2);margin:0">All programmes at BMC are fully affiliated with the <strong>Board of Intermediate Education Karachi (BIEK)</strong> and the <strong>Bahria Foundation</strong>. Certificates are government-recognised and accepted at universities across Pakistan and abroad.</p>
      </div>
    </div>
  </div>

  <?php elseif ($tab === 'departments'): ?>
  <!-- ── Departments ── -->
  <div class="section-header" data-aos="fade-up">
    <div class="sec-label"><span>Our Structure</span></div>
    <h2 class="sec-title">Academic Departments</h2>
    <p class="sec-subtitle">Organised teaching departments staffed by qualified, experienced educators</p>
  </div>

  <?php
  $demoDepts = [
    ['id'=>1,'name'=>'Science & Technology','description'=>'Biology, Chemistry, Physics — rigorous laboratory-based teaching for pre-medical and pre-engineering students.','icon'=>'fa-flask','color'=>'#00b894','faculty_count'=>12],
    ['id'=>2,'name'=>'Mathematics','description'=>'Pure and applied mathematics for FSc Pre-Engineering, ICS, and Commerce streams.','icon'=>'fa-square-root-alt','color'=>'#0984e3','faculty_count'=>6],
    ['id'=>3,'name'=>'Computer Science','description'=>'Programming, databases, networking, and software development fundamentals for ICS students.','icon'=>'fa-laptop-code','color'=>'#6c5ce7','faculty_count'=>5],
    ['id'=>4,'name'=>'Arts & Humanities','description'=>'Urdu, English, Pakistan Studies, Islamiat, History, Economics, and Fine Arts.','icon'=>'fa-palette','color'=>'#e17055','faculty_count'=>9],
    ['id'=>5,'name'=>'Commerce & Management','description'=>'Accounting, Business Mathematics, Commercial Geography, and Economics for ICOM students.','icon'=>'fa-chart-line','color'=>'#f9ca24','faculty_count'=>7],
    ['id'=>6,'name'=>'Languages','description'=>'English and Urdu language development including literature, composition, and communication skills.','icon'=>'fa-language','color'=>'#fd79a8','faculty_count'=>6],
  ];
  $deptList = !empty($departments) ? $departments : $demoDepts;
  ?>

  <div class="row g-4">
    <?php foreach ($deptList as $i => $d): ?>
    <?php $color = $d['color'] ?? ['#00b894','#0984e3','#6c5ce7','#e17055','#f9ca24','#fd79a8'][$i%6]; ?>
    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= ($i%3)*80 ?>">
      <div class="dept-card h-100" style="border-top:3px solid <?= $color ?>">
        <div class="dept-card-icon" style="background:<?= $color ?>20;color:<?= $color ?>">
          <i class="fas <?= $d['icon'] ?? 'fa-book' ?>"></i>
        </div>
        <h5 class="dept-card-name"><?= sh($d['name']) ?></h5>
        <?php if (!empty($d['description'])): ?>
        <p class="dept-card-desc"><?= sh($d['description']) ?></p>
        <?php endif; ?>
        <div style="margin-top:auto;padding-top:12px;display:flex;align-items:center;justify-content:space-between">
          <?php if (!empty($d['faculty_count'])): ?>
          <span style="font-size:.78rem;color:var(--text-3)"><i class="fas fa-users me-1"></i><?= $d['faculty_count'] ?> Faculty</span>
          <?php endif; ?>
          <a href="<?= SITE_URL ?>/faculty.php?dept=<?= $d['id'] ?>" style="font-size:.8rem;color:<?= $color ?>;text-decoration:none;font-weight:600">Meet Faculty <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php elseif ($tab === 'calendar'): ?>
  <!-- ── Academic Calendar ── -->
  <div class="section-header" data-aos="fade-up">
    <div class="sec-label"><span>Schedule</span></div>
    <h2 class="sec-title">Academic Calendar 2025–26</h2>
    <p class="sec-subtitle">Key dates, term breaks, examinations, and important events for the academic year</p>
  </div>

  <div class="row g-4">
    <div class="col-lg-8" data-aos="fade-up">
      <?php
      $calendarItems = [
        ['August 2025',    'Session Start',                 'Classes begin for new and continuing students.',                      'success'],
        ['August 2025',    'Orientation Week',              'Induction programme for new Part-I students.',                        'primary'],
        ['September 2025', 'First Monthly Test',            'First internal assessment across all subjects.',                      'warning'],
        ['October 2025',   'Mid-Term Examinations',         'Mid-term exams for Part-I and Part-II students.',                     'danger'],
        ['November 2025',  'Annual Science & Arts Gala',    'Inter-departmental exhibitions, competitions, and talent show.',      'info'],
        ['December 2025',  'Annual Sports Day',             'Athletic events and prize distribution.',                             'success'],
        ['December 2025',  'Winter Break',                  'Academic break: 24 December 2025 – 4 January 2026.',                 'secondary'],
        ['January 2026',   'Pre-Board Examinations',        'Trial exams modelled on BIEK board pattern.',                        'danger'],
        ['February 2026',  'Parent-Teacher Meeting',        'Progress review and feedback sessions.',                              'primary'],
        ['March 2026',     'BIEK Annual Examinations',      'Board examinations for FSc Part-II (tentative).',                    'danger'],
        ['April 2026',     'Result Announcement (Part-II)', 'BIEK Part-II results expected.',                                     'success'],
        ['May 2026',       'Final Examinations (Part-I)',   'Internal annual examinations for Part-I students.',                  'warning'],
        ['June 2026',      'Summer Break & New Admissions', 'Summer vacation begins. Admissions open for 2026-27.',               'info'],
      ];
      foreach ($calendarItems as $ci):
      ?>
      <div class="d-flex gap-3 mb-3" style="background:var(--light-2);border-radius:var(--radius);padding:14px 16px;border-left:3px solid var(--<?= $ci[3] ?>)" data-aos="fade-up">
        <div style="min-width:110px;font-size:.76rem;font-weight:700;color:var(--<?= $ci[3] ?>);padding-top:2px"><?= $ci[0] ?></div>
        <div>
          <div style="font-weight:700;color:var(--primary);font-size:.9rem;margin-bottom:2px"><?= $ci[1] ?></div>
          <div style="font-size:.82rem;color:var(--text-2)"><?= $ci[2] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="col-lg-4" data-aos="fade-left">
      <div style="position:sticky;top:130px">
        <div class="card-glass p-4 mb-4">
          <h6 style="font-weight:800;color:var(--primary);margin-bottom:16px"><i class="fas fa-download me-2"></i>Download Calendar</h6>
          <p style="font-size:.84rem;color:var(--text-2);margin-bottom:16px">Full academic calendar PDF with all dates, holidays, and examination schedules.</p>
          <a href="<?= SITE_URL ?>/downloads.php?tab=academic-calendar" class="btn-primary-custom" style="text-decoration:none;display:block;text-align:center">
            <i class="fas fa-file-pdf me-2"></i>Download PDF
          </a>
        </div>
        <div class="card-glass p-4">
          <h6 style="font-weight:800;color:var(--primary);margin-bottom:14px"><i class="fas fa-info-circle me-2 text-secondary"></i>Note</h6>
          <p style="font-size:.82rem;color:var(--text-2);line-height:1.7;margin:0">Dates are indicative. Exact examination schedules are issued by the Board of Intermediate Education Karachi (BIEK) and communicated via the college notice board.</p>
        </div>
      </div>
    </div>
  </div>

  <?php elseif ($tab === 'examination'): ?>
  <!-- ── Examination ── -->
  <div class="section-header" data-aos="fade-up">
    <div class="sec-label"><span>Exams</span></div>
    <h2 class="sec-title">Examination System</h2>
    <p class="sec-subtitle">Transparent, rigorous assessment aligned with BIEK standards</p>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <!-- Assessment Structure -->
      <div class="mb-5" data-aos="fade-up">
        <h4 style="font-weight:800;color:var(--primary);margin-bottom:20px">Assessment Structure</h4>
        <div class="table-responsive">
          <table class="table table-hover" style="font-size:.87rem">
            <thead style="background:var(--primary);color:#fff">
              <tr><th>Component</th><th>Part-I</th><th>Part-II</th><th>Frequency</th></tr>
            </thead>
            <tbody>
              <?php foreach ([
                ['Monthly Tests','10 marks each','—','Monthly (best 3 of 5 counted)'],
                ['Mid-Term Exam','50 marks','—','October'],
                ['Pre-Board / Trial Exam','100 marks','100 marks','January/February'],
                ['Annual Board Exam (BIEK)','—','Full papers','March–April'],
                ['Practical / Lab Work','As per BIEK','As per BIEK','Throughout year'],
                ['Internal (Part-I Annual)','100 marks','—','May–June'],
              ] as $row): ?>
              <tr>
                <?php foreach ($row as $cell): ?><td><?= $cell ?></td><?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Rules -->
      <div class="mb-5" data-aos="fade-up">
        <h4 style="font-weight:800;color:var(--primary);margin-bottom:20px">Examination Rules</h4>
        <div class="d-flex flex-column gap-3">
          <?php foreach ([
            ['Roll Number Slip','Students must carry their official roll number slip issued by the Examination Cell to every examination.'],
            ['Attendance Requirement','A minimum of 75% class attendance is required to appear in internal examinations. Students below this threshold may be barred.'],
            ['Unfair Means','Use of unfair means results in immediate cancellation of the paper and may lead to disciplinary action and rustication.'],
            ['Result Disputes','Students may apply for re-checking of internal marks within 7 days of result announcement by submitting a written application to the Examination Cell.'],
            ['Board Registration','Part-I students are registered with BIEK in November. Registration forms and fees must be submitted by the announced deadline.'],
          ] as $rule): ?>
          <div style="background:var(--light-2);border-radius:var(--radius);padding:16px;border-left:3px solid var(--secondary)">
            <div style="font-weight:700;color:var(--primary);font-size:.9rem;margin-bottom:4px"><?= $rule[0] ?></div>
            <div style="font-size:.84rem;color:var(--text-2);line-height:1.65"><?= $rule[1] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Grading -->
      <div data-aos="fade-up">
        <h4 style="font-weight:800;color:var(--primary);margin-bottom:20px">BIEK Grading Scale</h4>
        <div class="table-responsive">
          <table class="table table-hover" style="font-size:.87rem">
            <thead style="background:var(--primary);color:#fff">
              <tr><th>Grade</th><th>Marks Range</th><th>GPA</th><th>Classification</th></tr>
            </thead>
            <tbody>
              <?php foreach ([['A+','90–100','4.0','Distinction'],['A','80–89','3.5–3.9','Excellent'],['B+','70–79','3.0–3.4','Very Good'],['B','60–69','2.5–2.9','Good'],['C','50–59','2.0–2.4','Satisfactory'],['D','40–49','1.5–1.9','Pass'],['F','Below 40','0','Fail']] as $g): ?>
              <tr><td><?= $g[0] ?></td><td><?= $g[1] ?></td><td><?= $g[2] ?></td><td><?= $g[3] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-4" data-aos="fade-left">
      <div style="position:sticky;top:130px">
        <div class="card-glass p-4 mb-4">
          <h6 style="font-weight:800;color:var(--primary);margin-bottom:14px"><i class="fas fa-building me-2 text-secondary"></i>Examination Cell</h6>
          <?php foreach ([['Location','Block A, Room 105'],['Hours','Mon–Fri: 8 AM–3 PM'],['Contact','exam@bmc.edu.pk']] as $info): ?>
          <div style="display:flex;gap:8px;padding:7px 0;border-bottom:1px solid var(--border);font-size:.83rem">
            <span style="font-weight:700;color:var(--text-2);min-width:70px"><?= $info[0] ?></span>
            <span style="color:var(--text-3)"><?= $info[1] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="card-glass p-4">
          <h6 style="font-weight:800;color:var(--primary);margin-bottom:14px"><i class="fas fa-download me-2 text-secondary"></i>Related Downloads</h6>
          <?php foreach ([['Examination Rules 2025','exam-rules-2025.pdf'],['Roll Number Slip Form','roll-number-form.pdf'],['Internal Assessment Criteria','internal-assessment-2025.pdf']] as $dl): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)">
            <span style="font-size:.82rem;color:var(--text-2)"><?= $dl[0] ?></span>
            <a href="<?= SITE_URL ?>/downloads.php" class="btn-outline-custom" style="font-size:.76rem;padding:4px 10px;text-decoration:none"><i class="fas fa-download me-1"></i>PDF</a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <?php elseif ($tab === 'library'): ?>
  <!-- ── Library & Labs ── -->
  <div class="section-header" data-aos="fade-up">
    <div class="sec-label"><span>Resources</span></div>
    <h2 class="sec-title">Library & Laboratories</h2>
    <p class="sec-subtitle">State-of-the-art facilities supporting practical and academic excellence</p>
  </div>

  <div class="row g-4 mb-5">
    <?php
    $facilities = [
      ['fa-book-open','#0984e3','Central Library','A well-stocked library with over 15,000 volumes spanning science, arts, commerce, literature, and reference materials. Quiet reading zones, group study rooms, and a periodicals section.',['15,000+ Books','Reading Zones','Digital Catalogue','Periodicals Section']],
      ['fa-flask','#00b894','Biology Laboratory','Fully equipped for practicals including microscopy, dissection, and biochemical experiments. Aligned with BIEK FSc Pre-Medical practical syllabus.',['Compound Microscopes','Dissection Kits','Chemical Reagents','Safety Equipment']],
      ['fa-atom','#6c5ce7','Physics Laboratory','Modern physics lab with apparatus for optics, mechanics, electricity, and modern physics experiments.',['Optical Benches','Multimeters','Cathode Ray Oscilloscopes','Experiment Manuals']],
      ['fa-vials','#e17055','Chemistry Laboratory','Separate labs for organic and inorganic chemistry with safety-first design and ventilation systems.',['Fume Hoods','Analytical Balances','Reagent Cabinets','Safety Stations']],
      ['fa-laptop-code','#f9ca24','Computer Laboratory','60-seat air-conditioned lab with modern PCs, high-speed internet, and licensed software for ICS students.',['60 Workstations','High-Speed Internet','Licensed Software','Printer & Scanner']],
      ['fa-language','#fd79a8','Language Resource Centre','Audio-visual room for language practice, debates, and communication workshops with multimedia tools.',['Audio-Visual Setup','Language Software','Debate Stage','Recording Studio']],
    ];
    foreach ($facilities as $i => $f):
    ?>
    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= ($i%3)*80 ?>">
      <div class="card-glass h-100" style="padding:24px">
        <div style="width:52px;height:52px;border-radius:14px;background:<?= $f[1] ?>20;display:flex;align-items:center;justify-content:center;margin-bottom:16px">
          <i class="fas <?= $f[0] ?>" style="font-size:1.4rem;color:<?= $f[1] ?>"></i>
        </div>
        <h5 style="font-weight:800;color:var(--primary);margin-bottom:8px"><?= $f[2] ?></h5>
        <p style="font-size:.84rem;color:var(--text-2);line-height:1.7;margin-bottom:14px"><?= $f[3] ?></p>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:auto">
          <?php foreach ($f[4] as $feat): ?>
          <span style="font-size:.72rem;background:<?= $f[1] ?>15;color:<?= $f[1] ?>;padding:3px 10px;border-radius:20px;font-weight:600"><?= $feat ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="highlight-box" data-aos="fade-up">
    <div class="d-flex align-items-start gap-3">
      <i class="fas fa-clock fa-2x flex-shrink-0" style="color:var(--secondary)"></i>
      <div>
        <h6 style="font-weight:800;color:var(--primary);margin-bottom:4px">Library & Lab Hours</h6>
        <p style="font-size:.87rem;color:var(--text-2);margin:0">Library: Monday–Saturday, 7:30 AM–3:30 PM. Laboratories: Open for supervised practicals during scheduled class hours. Students must present their ID card for access.</p>
      </div>
    </div>
  </div>

  <?php elseif ($tab === 'rules'): ?>
  <!-- ── Rules & Conduct ── -->
  <div class="section-header" data-aos="fade-up">
    <div class="sec-label"><span>Conduct</span></div>
    <h2 class="sec-title">Student Rules & Code of Conduct</h2>
    <p class="sec-subtitle">BMC is committed to a safe, disciplined, and respectful environment for all students and staff</p>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <?php
      $rulesGroups = [
        ['fa-id-card','Attendance & Punctuality',[
          'A minimum of 75% attendance is compulsory each term. Persistent absenteeism may result in withdrawal of candidature.',
          'Students must arrive before the morning assembly. Late arrivals require a note from a parent/guardian.',
          'Leave of absence must be applied for in writing and approved by the class teacher or HOD.',
        ]],
        ['fa-tshirt','Dress Code',[
          'Full college uniform (as specified in the Student Handbook) must be worn on all college days.',
          'Hair must be neatly kept. Boys are not permitted to wear earrings or jewellery.',
          'Uniform inspections are conducted at the gate every morning.',
        ]],
        ['fa-mobile-alt','Technology Use',[
          'Mobile phones must be switched off or in silent mode during class hours.',
          'Unauthorised use of a mobile phone during class will result in its confiscation until a parent collects it.',
          'Computer lab equipment must be used only for approved academic purposes.',
        ]],
        ['fa-hand-point-right','General Behaviour',[
          'Ragging, bullying, harassment, and fighting are strictly prohibited and may lead to immediate expulsion.',
          'Littering and vandalism of college property are punishable offences.',
          'Students must treat all college staff, faculty, and fellow students with respect and courtesy.',
          'Smoking, chewing tobacco, or carrying any intoxicant onto college premises is a zero-tolerance offence.',
        ]],
        ['fa-exclamation-triangle','Disciplinary Procedure',[
          'Minor offences are addressed by the class teacher with a warning entered in the student\'s conduct record.',
          'Repeated or serious offences are referred to the Discipline Committee.',
          'The Committee may impose fines, suspend, or expel a student depending on the severity of the violation.',
          'A parent/guardian meeting is held for any formal disciplinary action.',
        ]],
      ];
      foreach ($rulesGroups as $i => $g):
      ?>
      <div class="mb-4" data-aos="fade-up">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div style="width:40px;height:40px;border-radius:10px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fas <?= $g[0] ?>" style="color:#fff;font-size:.95rem"></i>
          </div>
          <h5 style="font-weight:800;color:var(--primary);margin:0"><?= $g[1] ?></h5>
        </div>
        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px">
          <?php foreach ($g[2] as $rule): ?>
          <li style="display:flex;gap:10px;font-size:.86rem;color:var(--text-2);line-height:1.65;background:var(--light-2);padding:12px 14px;border-radius:var(--radius)">
            <i class="fas fa-dot-circle flex-shrink-0 mt-1" style="color:var(--secondary);font-size:.7rem"></i><?= $rule ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="col-lg-4" data-aos="fade-left">
      <div style="position:sticky;top:130px">
        <div class="card-glass p-4 mb-4">
          <h6 style="font-weight:800;color:var(--primary);margin-bottom:14px"><i class="fas fa-download me-2 text-secondary"></i>Download Handbook</h6>
          <p style="font-size:.83rem;color:var(--text-2);margin-bottom:14px">Full Student Code of Conduct handbook with all rules, procedures, and disciplinary guidelines.</p>
          <a href="<?= SITE_URL ?>/downloads.php" class="btn-primary-custom" style="text-decoration:none;display:block;text-align:center">
            <i class="fas fa-file-pdf me-2"></i>Student Handbook
          </a>
        </div>
        <div class="card-glass p-4">
          <h6 style="font-weight:800;color:var(--primary);margin-bottom:14px"><i class="fas fa-phone me-2 text-secondary"></i>Discipline Committee</h6>
          <p style="font-size:.82rem;color:var(--text-2);line-height:1.7;margin:0">For conduct-related issues, contact the Discipline Committee at: <br><strong>discipline@bmc.edu.pk</strong><br>Block A, Ground Floor, Room 108.</p>
        </div>
      </div>
    </div>
  </div>

  <?php endif; ?>

  </div>
</section>

<!-- CTA -->
<section class="site-section sec-dark" data-aos="fade-up">
  <div class="container-xl text-center">
    <h2 style="font-weight:900;color:#fff;margin-bottom:12px">Ready to Begin Your Journey?</h2>
    <p style="color:rgba(255,255,255,.75);margin-bottom:32px;font-size:1.05rem">Join BMC and experience world-class intermediate education in Karachi.</p>
    <div class="d-flex flex-wrap justify-content-center gap-3">
      <a href="<?= SITE_URL ?>/admissions.php" class="btn-primary-custom" style="text-decoration:none">
        <i class="fas fa-graduation-cap me-2"></i>Apply for Admissions
      </a>
      <a href="<?= SITE_URL ?>/contact.php" class="btn-outline-custom" style="text-decoration:none;color:#fff;border-color:rgba(255,255,255,.3)">
        <i class="fas fa-envelope me-2"></i>Contact Us
      </a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

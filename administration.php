<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle  = 'Administration';
$pageDesc   = 'Meet the dedicated administrative team of Bahria Model College — Principal, Vice Principal, Registrar, and key department heads who lead with commitment and vision.';
$activePage = 'administration';
include __DIR__ . '/includes/header.php';

$principalName = getSetting('principal_name', 'Prof. Dr. Muhammad Irfan');
$principalMsg  = getSetting('principal_message', '');
$principalImg  = getSetting('principal_image');
?>

<!-- ══ Page Hero ══ -->
<section class="page-hero">
  <div class="deco-circle" style="width:420px;height:420px;top:-130px;right:-90px"></div>
  <div class="deco-circle" style="width:220px;height:220px;bottom:-70px;left:8%"></div>
  <div class="container-xl" style="position:relative;z-index:1">
    <div class="page-hero-label" data-aos="fade-up"><i class="fas fa-sitemap me-2"></i>Our Leadership</div>
    <h1 class="page-hero-title" data-aos="fade-up" data-aos-delay="80">Administration</h1>
    <p class="page-hero-subtitle" data-aos="fade-up" data-aos-delay="160" style="max-width:560px">The dedicated leadership team guiding BMC toward continued academic excellence, institutional growth, and student success.</p>
  </div>
</section>

<!-- ══ Breadcrumb ══ -->
<div class="breadcrumb-wrap">
  <div class="container-xl">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Administration</li>
      </ol>
    </nav>
  </div>
</div>

<!-- ══ Principal ══ -->
<section class="site-section">
  <div class="container-xl">
    <div class="row g-5 align-items-center">

      <!-- Portrait card -->
      <div class="col-lg-5" data-aos="fade-right">
        <div class="principal-card">
          <div class="principal-photo" style="height:380px;background:linear-gradient(135deg,var(--primary-d) 0%,var(--primary-l) 100%);display:flex;align-items:center;justify-content:center;position:relative">
            <?php if ($principalImg): ?>
            <img src="<?= uploadUrl('admins', sh($principalImg)) ?>" alt="<?= sh($principalName) ?>" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0">
            <?php else: ?>
            <i class="fas fa-user-tie" style="font-size:7rem;color:rgba(255,255,255,.18)"></i>
            <?php endif; ?>
            <div class="principal-name-badge" style="z-index:2">
              <div style="font-weight:800;font-size:1rem"><?= sh($principalName) ?></div>
              <div style="font-size:.78rem;opacity:.8">Principal, Bahria Model College</div>
            </div>
          </div>
          <div style="padding:24px">
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px">
              <span style="font-size:.74rem;background:rgba(9,132,227,.1);color:var(--secondary);padding:4px 10px;border-radius:99px;font-weight:600"><i class="fas fa-graduation-cap me-1"></i>PhD (Education)</span>
              <span style="font-size:.74rem;background:rgba(249,202,36,.14);color:#8a6800;padding:4px 10px;border-radius:99px;font-weight:600"><i class="fas fa-award me-1"></i>25+ Yrs Experience</span>
            </div>
            <div class="highlight-box">
              <?= sh(truncateText($principalMsg ?: 'Bahria Model College is committed to nurturing not just academic talent but complete human beings — curious, compassionate, and capable. Our vision extends beyond examinations to shaping citizens of character who will contribute meaningfully to society.', 285)) ?>
            </div>
            <a href="<?= SITE_URL ?>/about.php?tab=principal" class="btn-primary-custom mt-4 w-100 justify-content-center" style="text-decoration:none">
              Read Full Message <i class="fas fa-arrow-right ms-2"></i>
            </a>
          </div>
        </div>
      </div>

      <!-- Detail column -->
      <div class="col-lg-7" data-aos="fade-left">
        <div class="sec-label" style="justify-content:flex-start"><span>Office of the Principal</span></div>
        <h2 class="sec-title mb-3" style="text-align:left">Principal's Profile</h2>
        <p style="color:var(--text-2);line-height:1.85;margin-bottom:24px">Prof. Dr. Muhammad Irfan brings over 25 years of distinguished service in education to his role as Principal of Bahria Model College. His tenure has been characterised by a relentless focus on academic standards, faculty development, and student welfare — producing consistently outstanding Board results year after year.</p>
        <div class="row g-3 mb-4">
          <?php foreach ([
            ['fa-graduation-cap','Qualification',  'PhD (Education), University of Karachi'],
            ['fa-briefcase',     'Experience',     '25+ Years in Education Administration'],
            ['fa-envelope',      'Email',          'principal@bmc.edu.pk'],
            ['fa-phone-alt',     'Direct Line',    'Ext. 101 — Reception: ' . sh(getSetting('site_phone','+92-21-3470-0000'))],
          ] as $d): ?>
          <div class="col-sm-6">
            <div style="display:flex;gap:12px;align-items:flex-start;background:var(--light-2);padding:14px 16px;border-radius:var(--radius)">
              <div style="width:38px;height:38px;background:linear-gradient(135deg,var(--secondary),var(--primary-l));border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.88rem;flex-shrink:0">
                <i class="fas <?= $d[0] ?>"></i>
              </div>
              <div>
                <div style="font-size:.72rem;color:var(--text-3);text-transform:uppercase;letter-spacing:.07em;font-weight:700;margin-bottom:2px"><?= $d[1] ?></div>
                <div style="font-weight:600;color:var(--text);font-size:.87rem;line-height:1.4"><?= $d[2] ?></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="background:linear-gradient(135deg,var(--primary-d),var(--primary-l));border-radius:var(--radius-lg);padding:22px 24px;color:#fff">
          <div style="font-size:.73rem;text-transform:uppercase;letter-spacing:.1em;opacity:.65;margin-bottom:8px;font-weight:700">Office Hours</div>
          <div style="display:flex;gap:20px;flex-wrap:wrap">
            <div><i class="fas fa-calendar-week me-2" style="color:var(--accent)"></i><strong>Mon – Fri:</strong> 8:00 AM – 3:00 PM</div>
            <div><i class="fas fa-calendar me-2" style="color:var(--accent)"></i><strong>Saturday:</strong> 9:00 AM – 12:00 PM</div>
          </div>
          <div style="font-size:.8rem;opacity:.65;margin-top:8px"><i class="fas fa-info-circle me-1"></i>Appointments preferred — contact the office in advance.</div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ══ Vice Principal ══ -->
<section class="site-section sec-alt">
  <div class="container-xl">
    <div class="row g-5 align-items-center">

      <!-- Detail column (left on large screens) -->
      <div class="col-lg-7 order-2 order-lg-1" data-aos="fade-right">
        <div class="sec-label" style="justify-content:flex-start"><span>Academic Leadership</span></div>
        <h2 class="sec-title mb-3" style="text-align:left">Vice Principal</h2>
        <p style="color:var(--text-2);line-height:1.85;margin-bottom:24px">Ms. Rukhsana Parveen oversees academic operations and student affairs at BMC. With a background in curriculum development and teacher training, she ensures that academic programmes maintain the highest standards and that every student receives the individual guidance needed to realise their potential.</p>
        <div class="row g-3 mb-4">
          <?php foreach ([
            ['fa-graduation-cap','Qualification',  'M.Phil (Chemistry), FUUAST Karachi'],
            ['fa-briefcase',     'Experience',     '18 Years in Teaching & Administration'],
            ['fa-envelope',      'Email',          'viceprincpal@bmc.edu.pk'],
            ['fa-tasks',         'Responsibility', 'Academic Affairs & Student Welfare'],
          ] as $d): ?>
          <div class="col-sm-6">
            <div style="display:flex;gap:12px;align-items:flex-start;background:var(--white);padding:14px 16px;border-radius:var(--radius);box-shadow:var(--shadow-sm)">
              <div style="width:38px;height:38px;background:linear-gradient(135deg,#006b5e,#00cec9);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.88rem;flex-shrink:0">
                <i class="fas <?= $d[0] ?>"></i>
              </div>
              <div>
                <div style="font-size:.72rem;color:var(--text-3);text-transform:uppercase;letter-spacing:.07em;font-weight:700;margin-bottom:2px"><?= $d[1] ?></div>
                <div style="font-weight:600;color:var(--text);font-size:.87rem;line-height:1.4"><?= $d[2] ?></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="highlight-box">
          "My commitment is to create an academic environment where every student feels heard, valued, and motivated. Academic excellence is not just about results — it is about building habits of the mind that last a lifetime."
        </div>
      </div>

      <!-- Portrait card (right on large screens) -->
      <div class="col-lg-5 order-1 order-lg-2" data-aos="fade-left">
        <div class="principal-card">
          <div class="principal-photo" style="height:320px;background:linear-gradient(135deg,#006b5e,#00cec9);display:flex;align-items:center;justify-content:center">
            <i class="fas fa-user-circle" style="font-size:7rem;color:rgba(255,255,255,.18)"></i>
            <div class="principal-name-badge">
              <div style="font-weight:800;font-size:1rem">Ms. Rukhsana Parveen</div>
              <div style="font-size:.78rem;opacity:.8">Vice Principal, Bahria Model College</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ══ Key Administration Grid ══ -->
<section class="site-section">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Our Team</span></div>
      <h2 class="sec-title">Key Administration</h2>
      <p class="sec-subtitle">The dedicated professionals who manage the day-to-day operations and administrative functions of Bahria Model College.</p>
    </div>
    <?php
    $keyAdmin = [
      ['Prof. Dr. Muhammad Irfan', 'Principal',                'Office of the Principal',  'fa-user-tie',       'linear-gradient(135deg,#0c2461,#1a3a8f)'],
      ['Ms. Rukhsana Parveen',     'Vice Principal',           'Academic Affairs',          'fa-user-graduate',  'linear-gradient(135deg,#006b5e,#00cec9)'],
      ['Mr. Tariq Hassan Khan',    'Registrar',                'Office of the Registrar',   'fa-id-badge',       'linear-gradient(135deg,#0984e3,#74b9ff)'],
      ['Mrs. Nadia Rehman',        'Head, Examination Cell',   'Examinations',              'fa-file-alt',       'linear-gradient(135deg,#6c5ce7,#a29bfe)'],
      ['Mr. Asif Mehmood',         'Finance Officer',          'Finance & Accounts',        'fa-calculator',     'linear-gradient(135deg,#27ae60,#55efc4)'],
      ['Ms. Saira Qureshi',        'Head, Admissions Office',  'Admissions',                'fa-graduation-cap', 'linear-gradient(135deg,#e17055,#fab1a0)'],
      ['Mr. Jamil Ahmed',          'Head Librarian',           'Library Services',          'fa-book-open',      'linear-gradient(135deg,#fdcb6e,#e17055)'],
      ['Mr. Zulfiqar Ali',         'Sports Director',          'Sports & Co-Curricular',    'fa-running',        'linear-gradient(135deg,#2d3436,#636e72)'],
    ];
    ?>
    <div class="row g-4">
      <?php foreach ($keyAdmin as $i => $a): ?>
      <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 70 ?>">
        <div class="faculty-card">
          <div class="faculty-card-img">
            <div style="background:<?= $a[4] ?>;height:200px;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden">
              <i class="fas <?= $a[3] ?>" style="font-size:4.5rem;color:rgba(255,255,255,.18)"></i>
              <div class="faculty-card-overlay" style="opacity:1;background:linear-gradient(to top,rgba(0,0,0,.55) 0%,transparent 55%)"></div>
            </div>
          </div>
          <div class="faculty-card-body">
            <div class="faculty-card-name"><?= $a[0] ?></div>
            <div class="faculty-card-role" style="color:var(--secondary)"><?= $a[1] ?></div>
            <div style="font-size:.73rem;color:var(--text-3);margin-top:3px"><?= $a[2] ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ Organizational Chart ══ -->
<section class="site-section sec-alt">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Structure</span></div>
      <h2 class="sec-title">Organizational Structure</h2>
      <p class="sec-subtitle">The administrative hierarchy and lines of authority at Bahria Model College.</p>
    </div>

    <div data-aos="fade-up" data-aos-delay="100" style="overflow-x:auto;-webkit-overflow-scrolling:touch;padding-bottom:8px">
      <div style="min-width:700px;padding:8px 4px 16px">

        <style>
          .org-node {
            background:var(--white); border:2px solid var(--border);
            border-radius:var(--radius); padding:11px 16px; text-align:center;
            min-width:130px; box-shadow:var(--shadow-sm); transition:all .25s ease;
            display:inline-block;
          }
          .org-node:hover { box-shadow:var(--shadow); border-color:var(--secondary); }
          .org-node-t { font-family:var(--font-heading); font-weight:700; font-size:.82rem; line-height:1.3; }
          .org-node-n { font-size:.71rem; color:var(--text-3); margin-top:2px; }
          .org-vline  { width:2px; background:var(--border); margin:0 auto; }
          .org-hbar   { height:2px; background:var(--border); }
        </style>

        <!-- Row 0: Governing body -->
        <div style="display:flex;justify-content:center;margin-bottom:0">
          <div class="org-node" style="background:linear-gradient(135deg,#2d3436,#636e72);border-color:#636e72;color:#fff;min-width:230px">
            <div class="org-node-t"><i class="fas fa-building me-1"></i>Bahria Foundation</div>
            <div class="org-node-n" style="color:rgba(255,255,255,.65)">Governing Authority</div>
          </div>
        </div>

        <div class="org-vline" style="height:28px"></div>

        <!-- Row 1: Principal -->
        <div style="display:flex;justify-content:center;margin-bottom:0">
          <div class="org-node" style="background:linear-gradient(135deg,var(--primary-d),var(--primary-l));border-color:var(--primary-l);color:#fff;min-width:230px">
            <div class="org-node-t"><i class="fas fa-user-tie me-1"></i>Principal</div>
            <div class="org-node-n" style="color:rgba(255,255,255,.7)">Prof. Dr. Muhammad Irfan</div>
          </div>
        </div>

        <div class="org-vline" style="height:28px"></div>

        <!-- Row 2: Three direct reports side by side -->
        <div style="display:flex;justify-content:center;align-items:flex-start;gap:0">

          <!-- Finance Officer (left) -->
          <div style="display:flex;flex-direction:column;align-items:center;flex:1;max-width:200px">
            <div class="org-vline" style="height:0"></div><!-- spacer aligns top -->
            <div style="height:14px;width:2px;background:transparent"></div>
            <!-- connect line from center horizontal bar down -->
            <div style="height:14px"></div>
            <div class="org-vline" style="height:14px"></div>
            <div class="org-node" style="background:linear-gradient(135deg,#27ae60,#55efc4);border-color:#27ae60;color:#fff;width:100%;max-width:175px;font-size:.82rem">
              <div class="org-node-t"><i class="fas fa-calculator me-1"></i>Finance Officer</div>
              <div class="org-node-n" style="color:rgba(255,255,255,.7)">Mr. Asif Mehmood</div>
            </div>
          </div>

          <!-- Horizontal connector bar + VP column -->
          <div style="display:flex;flex-direction:column;align-items:center;flex:2">
            <!-- horizontal arms -->
            <div style="display:flex;align-items:center;width:100%">
              <div class="org-hbar" style="flex:1"></div>
              <div class="org-vline" style="height:28px;width:2px;flex-shrink:0"></div>
              <div class="org-hbar" style="flex:1"></div>
            </div>
            <!-- VP node -->
            <div class="org-node" style="background:linear-gradient(135deg,#006b5e,#00cec9);border-color:#00cec9;color:#fff;min-width:210px">
              <div class="org-node-t"><i class="fas fa-user-graduate me-1"></i>Vice Principal</div>
              <div class="org-node-n" style="color:rgba(255,255,255,.7)">Ms. Rukhsana Parveen</div>
            </div>
            <div class="org-vline" style="height:24px"></div>

            <!-- Row 3: Five dept heads under VP -->
            <div style="display:flex;gap:6px;justify-content:center">
              <?php foreach ([
                ['fa-id-badge',       '#0984e3', 'Registrar',     'T. H. Khan'],
                ['fa-file-alt',       '#6c5ce7', 'Exam Cell',     'N. Rehman'],
                ['fa-graduation-cap', '#e17055', 'Admissions',    'S. Qureshi'],
                ['fa-book-open',      '#a07000', 'Library',       'J. Ahmed'],
                ['fa-running',        '#636e72', 'Sports',        'Z. Ali'],
              ] as $n): ?>
              <div style="display:flex;flex-direction:column;align-items:center">
                <div class="org-vline" style="height:20px"></div>
                <div class="org-node" style="min-width:100px;max-width:112px;border-top:3px solid <?= $n[1] ?>;padding:9px 8px">
                  <div style="font-size:1.05rem;color:<?= $n[1] ?>;margin-bottom:4px"><i class="fas <?= $n[0] ?>"></i></div>
                  <div class="org-node-t" style="font-size:.76rem"><?= $n[2] ?></div>
                  <div class="org-node-n"><?= $n[3] ?></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- HODs (right) -->
          <div style="display:flex;flex-direction:column;align-items:center;flex:1;max-width:200px">
            <div style="height:14px"></div>
            <div class="org-vline" style="height:14px"></div>
            <div class="org-node" style="background:linear-gradient(135deg,#6c5ce7,#a29bfe);border-color:#6c5ce7;color:#fff;width:100%;max-width:175px">
              <div class="org-node-t"><i class="fas fa-chalkboard-teacher me-1"></i>Dept. Heads</div>
              <div class="org-node-n" style="color:rgba(255,255,255,.7)">Faculty (HODs)</div>
            </div>
          </div>

        </div><!-- /row-2 -->
      </div>
    </div>
  </div>
</section>

<!-- ══ Administrative Staff Table ══ -->
<section class="site-section">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Directory</span></div>
      <h2 class="sec-title">Administrative Staff Directory</h2>
      <p class="sec-subtitle">Complete listing of BMC's non-teaching administrative and support personnel.</p>
    </div>

    <div data-aos="fade-up" data-aos-delay="80" style="overflow-x:auto;-webkit-overflow-scrolling:touch;border-radius:var(--radius-lg);box-shadow:var(--shadow)">
      <table style="width:100%;border-collapse:collapse;background:var(--white);font-variant-numeric:tabular-nums;min-width:580px">
        <thead>
          <tr>
            <?php foreach (['#', 'Name', 'Designation', 'Department / Office', 'Ext.'] as $h): ?>
            <th style="background:var(--primary);color:#fff;padding:13px 18px;text-align:left;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap"><?= $h ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php
          $staff = [
            ['Mr. Tariq Hassan Khan',   'Registrar',                         'Office of the Registrar',  '102'],
            ['Mrs. Nadia Rehman',       'Head, Examination Cell',            'Examinations',             '103'],
            ['Mr. Asif Mehmood',        'Finance Officer',                   'Finance & Accounts',       '104'],
            ['Ms. Saira Qureshi',       'Head, Admissions Office',           'Admissions',               '105'],
            ['Mr. Jamil Ahmed',         'Head Librarian',                    'Library Services',         '106'],
            ['Mr. Zulfiqar Ali',        'Sports Director',                   'Sports & Co-Curricular',   '107'],
            ['Mr. Khalid Pervaiz',      'Senior Clerk',                      'Office of the Registrar',  '108'],
            ['Mrs. Sobia Anwar',        'Accounts Officer',                  'Finance & Accounts',       '109'],
            ['Mr. Danish Raza',         'IT Officer',                        'Computer Centre',          '110'],
            ['Mr. Usman Farooq',        'Admissions Counsellor',             'Admissions',               '111'],
            ['Mrs. Hina Malik',         'Library Assistant',                 'Library Services',         '112'],
            ['Ms. Amna Bibi',           'Data Entry Operator',               'Examination Cell',         '113'],
            ['Mr. Kashif Nawaz',        'Storekeeper',                       'General Administration',   '—'],
            ['Mr. Shahid Iqbal',        'Security Supervisor',               'Security',                 '—'],
            ['Mr. Farhan Chaudhry',     'Canteen Manager',                   'Student Services',         '—'],
          ];
          foreach ($staff as $i => $s):
            $bg = $i % 2 === 0 ? 'var(--white)' : 'var(--light-2)';
          ?>
          <tr style="background:<?= $bg ?>;border-bottom:1px solid var(--border);transition:background .15s">
            <td style="padding:11px 18px;font-size:.82rem;color:var(--text-3);width:44px"><?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></td>
            <td style="padding:11px 18px;font-weight:600;font-size:.88rem;color:var(--text);white-space:nowrap"><?= sh($s[0]) ?></td>
            <td style="padding:11px 18px;font-size:.85rem;color:var(--text-2)"><?= sh($s[1]) ?></td>
            <td style="padding:11px 18px;font-size:.85rem;color:var(--secondary);font-weight:500"><?= sh($s[2]) ?></td>
            <td style="padding:11px 18px;font-size:.85rem;color:var(--text-2);white-space:nowrap"><?= sh($s[3]) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- ══ Office Hours & Contact ══ -->
<section class="site-section sec-alt">
  <div class="container-xl">
    <div class="section-header" data-aos="fade-up">
      <div class="sec-label"><span>Information</span></div>
      <h2 class="sec-title">Office Hours &amp; Contact</h2>
      <p class="sec-subtitle">Reach the right department at the right time. All offices are on the main BMC campus.</p>
    </div>

    <div data-aos="fade-up" data-aos-delay="80" style="overflow-x:auto;-webkit-overflow-scrolling:touch;border-radius:var(--radius-lg);box-shadow:var(--shadow);margin-bottom:32px">
      <table style="width:100%;border-collapse:collapse;background:var(--white);min-width:640px">
        <thead>
          <tr>
            <?php foreach (['Department / Office', 'In-Charge', 'Mon – Fri', 'Saturday', 'Contact'] as $h): ?>
            <th style="background:var(--primary-d);color:#fff;padding:13px 18px;text-align:left;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap"><?= $h ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php
          $offices = [
            ["Principal's Office",     'Prof. Dr. Muhammad Irfan', '8:00 AM – 3:00 PM', '9:00 AM – 12:00 PM', 'Ext. 101'],
            ["Vice Principal's Office", 'Ms. Rukhsana Parveen',    '8:00 AM – 3:00 PM', '9:00 AM – 1:00 PM',  'Ext. 102'],
            ["Registrar's Office",     'Mr. Tariq Hassan Khan',    '8:00 AM – 4:00 PM', '9:00 AM – 1:00 PM',  'Ext. 102'],
            ['Examination Cell',       'Mrs. Nadia Rehman',        '8:30 AM – 3:30 PM', 'Closed',             'Ext. 103'],
            ['Finance & Accounts',     'Mr. Asif Mehmood',         '9:00 AM – 4:00 PM', 'Closed',             'Ext. 104'],
            ['Admissions Office',      'Ms. Saira Qureshi',        '8:00 AM – 3:00 PM', '9:00 AM – 1:00 PM',  'Ext. 105'],
            ['Library',                'Mr. Jamil Ahmed',          '8:00 AM – 5:00 PM', '9:00 AM – 2:00 PM',  'Ext. 106'],
            ['Sports Office',          'Mr. Zulfiqar Ali',         '9:00 AM – 3:00 PM', '9:00 AM – 12:00 PM', 'Ext. 107'],
          ];
          foreach ($offices as $i => $o):
            $bg = $i % 2 === 0 ? 'var(--white)' : 'var(--light-2)';
          ?>
          <tr style="background:<?= $bg ?>;border-bottom:1px solid var(--border)">
            <td style="padding:13px 18px;font-weight:700;font-size:.88rem;color:var(--primary);white-space:nowrap"><?= $o[0] ?></td>
            <td style="padding:13px 18px;font-size:.85rem;color:var(--text-2);white-space:nowrap"><?= $o[1] ?></td>
            <td style="padding:13px 18px;font-size:.85rem;color:var(--text);white-space:nowrap"><i class="fas fa-clock me-1" style="color:var(--secondary)"></i><?= $o[2] ?></td>
            <td style="padding:13px 18px;font-size:.85rem;white-space:nowrap;color:<?= $o[3]==='Closed'?'var(--red)':'var(--text)' ?>;font-weight:<?= $o[3]==='Closed'?'600':'400' ?>"><?= $o[3] ?></td>
            <td style="padding:13px 18px;font-size:.85rem;color:var(--secondary);font-weight:600;white-space:nowrap"><?= $o[4] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Contact cards row -->
    <div class="row g-3">
      <?php foreach ([
        ['fa-phone-alt', 'linear-gradient(135deg,var(--secondary),var(--primary-l))', '#fff',          'Main Reception',   sh(getSetting('site_phone','+92-21-3470-0000'))],
        ['fa-envelope',  'linear-gradient(135deg,var(--teal),var(--secondary))',       '#fff',          'General Email',    sh(getSetting('site_email','info@bmc.edu.pk'))],
        ['fa-map-marker-alt','linear-gradient(135deg,var(--accent),var(--accent-d))', 'var(--primary)','Campus Address',   sh(getSetting('site_address','Bin Qasim, Karachi'))],
      ] as $i => $c): ?>
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
        <div class="card-glass p-4 text-center h-100">
          <div style="width:52px;height:52px;background:<?= $c[1] ?>;border-radius:var(--radius);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;color:<?= $c[2] ?>;font-size:1.2rem">
            <i class="fas <?= $c[0] ?>"></i>
          </div>
          <div style="font-weight:700;color:var(--primary);margin-bottom:5px;font-family:var(--font-heading)"><?= $c[3] ?></div>
          <div style="font-size:.88rem;color:var(--text-2);line-height:1.5"><?= $c[4] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

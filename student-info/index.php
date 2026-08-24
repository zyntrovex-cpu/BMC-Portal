<?php
require_once __DIR__ . '/../../includes/functions.php';
// If the user is already logged in as a student, send them directly to the portal
if (!empty($_SESSION['user']['id']) && $_SESSION['user']['role'] === 'student') {
    header('Location: ' . BASE_URL . '/student/dashboard.php');
    exit;
}
// Staff / admin shortcuts
if (!empty($_SESSION['user']['id']) && in_array($_SESSION['user']['role'], ['admin','superadmin','staff','ilc_vp'])) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/config.php';
$pageTitle  = 'Student Portal';
$activePage = 'student';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-hero" style="min-height:340px">
  <div class="container-xl position-relative" style="z-index:1">
    <div class="page-hero-label">Secure Access</div>
    <h1 class="page-hero-title">Student Portal</h1>
    <p class="page-hero-subtitle">Access your results, attendance, timetable, and academic records</p>
  </div>
</div>
<div class="breadcrumb-wrap">
  <div class="container-xl"><nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php">Home</a></li>
    <li class="breadcrumb-item active">Student Portal</li>
  </ol></nav></div>
</div>

<section class="site-section">
  <div class="container-xl">
    <div class="row g-5 align-items-center">
      <!-- Login card -->
      <div class="col-lg-5" data-aos="fade-right">
        <div class="contact-form-card">
          <div style="text-align:center;margin-bottom:24px">
            <div style="width:72px;height:72px;background:linear-gradient(135deg,var(--primary),var(--secondary));border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
              <i class="fas fa-user-graduate" style="font-size:2rem;color:#fff"></i>
            </div>
            <h3 style="font-weight:800;color:var(--primary);margin-bottom:4px">Sign In</h3>
            <p style="color:var(--text-3);font-size:.88rem">Enter your credentials to access the portal</p>
          </div>
          <form method="POST" action="<?= BASE_URL ?>/login.php" class="d-flex flex-column gap-3">
            <input type="hidden" name="redirect" value="<?= BASE_URL ?>/student/dashboard.php">
            <div>
              <label class="form-label">Roll Number / Username</label>
              <input type="text" name="username" class="form-control-custom" placeholder="e.g. 2025-BMC-001" required autocomplete="username">
            </div>
            <div>
              <label class="form-label">Password</label>
              <div style="position:relative">
                <input type="password" name="password" id="portalPass" class="form-control-custom" placeholder="Enter your password" required autocomplete="current-password" style="padding-right:42px">
                <button type="button" onclick="togglePass()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-3);cursor:pointer;padding:0">
                  <i class="fas fa-eye" id="passIcon"></i>
                </button>
              </div>
            </div>
            <div class="d-flex justify-content-between align-items-center" style="font-size:.82rem">
              <label style="display:flex;align-items:center;gap:6px;color:var(--text-2);cursor:pointer">
                <input type="checkbox" name="remember" style="accent-color:var(--primary)"> Remember me
              </label>
              <a href="<?= BASE_URL ?>/forgot-password.php" style="color:var(--secondary);text-decoration:none">Forgot password?</a>
            </div>
            <button type="submit" class="btn-primary-custom" style="text-decoration:none;text-align:center">
              <i class="fas fa-sign-in-alt me-2"></i>Access Portal
            </button>
          </form>
          <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border);text-align:center">
            <p style="font-size:.82rem;color:var(--text-3);margin:0">Don't have an account? Contact the <a href="<?= SITE_URL ?>/contact.php" style="color:var(--secondary)">IT / Examination Office</a>.</p>
          </div>
        </div>
      </div>

      <!-- Portal features -->
      <div class="col-lg-7" data-aos="fade-left">
        <div class="sec-label" style="justify-content:flex-start"><span>What's Inside</span></div>
        <h2 class="sec-title" style="text-align:left;margin-bottom:8px">Your Academic Hub</h2>
        <p style="color:var(--text-2);margin-bottom:28px;font-size:.92rem">The BMC Student Portal gives you real-time access to everything you need for your academic journey, all in one secure place.</p>

        <div class="row g-3 mb-4">
          <?php
          $features = [
            ['fa-chart-bar','#0984e3','Results & Grades','View your internal test scores, mid-term results, and board exam marks as soon as they are published.'],
            ['fa-calendar-check','#00b894','Attendance Tracker','Check your attendance percentage per subject and receive alerts when it falls below the required threshold.'],
            ['fa-clock','#6c5ce7','Timetable','Access your class timetable for each semester including room and teacher details.'],
            ['fa-bell','#e17055','Notices','Receive important notices, exam schedules, and announcements directly in your portal dashboard.'],
            ['fa-file-download','#f9ca24','Downloads','Download your roll number slips, fee receipts, merit certificates, and other official documents.'],
            ['fa-user-circle','#fd79a8','My Profile','Update your contact details, view your academic history, and print your student card.'],
          ];
          foreach ($features as $i => $feat):
          ?>
          <div class="col-sm-6" data-aos="fade-up" data-aos-delay="<?= ($i%2)*60 ?>">
            <div style="background:var(--light-2);border-radius:var(--radius);padding:16px;display:flex;gap:12px;align-items:flex-start">
              <div style="width:40px;height:40px;border-radius:10px;background:<?= $feat[1] ?>20;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="fas <?= $feat[0] ?>" style="color:<?= $feat[1] ?>"></i>
              </div>
              <div>
                <div style="font-weight:700;color:var(--primary);font-size:.88rem;margin-bottom:3px"><?= $feat[2] ?></div>
                <div style="font-size:.79rem;color:var(--text-2);line-height:1.55"><?= $feat[3] ?></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Help notice -->
        <div class="highlight-box">
          <div class="d-flex align-items-start gap-3">
            <i class="fas fa-info-circle flex-shrink-0" style="color:var(--secondary);font-size:1.2rem;margin-top:2px"></i>
            <div>
              <div style="font-weight:700;color:var(--primary);margin-bottom:4px;font-size:.9rem">Need Help?</div>
              <p style="font-size:.83rem;color:var(--text-2);margin:0;line-height:1.6">Your portal credentials are issued by the Examination & IT Office. If you have not received your login details or are having difficulty signing in, visit <strong>Block A, Room 104</strong> or email <a href="mailto:itdesk@bmc.edu.pk" style="color:var(--secondary)">itdesk@bmc.edu.pk</a>.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Quick links for existing users -->
<section class="site-section sec-alt">
  <div class="container-xl" data-aos="fade-up">
    <div class="section-header">
      <div class="sec-label"><span>Quick Links</span></div>
      <h2 class="sec-title">Useful Resources</h2>
    </div>
    <div class="row g-3 justify-content-center">
      <?php foreach ([
        [SITE_URL . '/notices.php','fa-bell','#e17055','Notices','Latest announcements and circulars'],
        [SITE_URL . '/downloads.php','fa-file-download','#0984e3','Downloads','Forms, schedules, and documents'],
        [SITE_URL . '/events.php','fa-calendar-alt','#00b894','Events','Upcoming events and activities'],
        [SITE_URL . '/academics.php?tab=examination','fa-file-signature','#6c5ce7','Examination','Rules, dates, and grading'],
        [SITE_URL . '/contact.php','fa-envelope','#f9ca24','Contact','Reach the college directly'],
      ] as $link): ?>
      <div class="col-6 col-md-4 col-lg-2">
        <a href="<?= $link[0] ?>" style="text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:10px;padding:20px;background:var(--light-1);border-radius:var(--radius-lg);text-align:center;border:1px solid var(--border);transition:all .2s;color:inherit" onmouseover="this.style.borderColor='<?= $link[2] ?>'" onmouseout="this.style.borderColor='var(--border)'">
          <div style="width:48px;height:48px;border-radius:14px;background:<?= $link[2] ?>20;display:flex;align-items:center;justify-content:center">
            <i class="fas <?= $link[1] ?>" style="color:<?= $link[2] ?>;font-size:1.2rem"></i>
          </div>
          <div>
            <div style="font-weight:700;color:var(--primary);font-size:.88rem"><?= $link[3] ?></div>
            <div style="font-size:.74rem;color:var(--text-3);margin-top:2px"><?= $link[4] ?></div>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<script>
function togglePass() {
  const inp = document.getElementById('portalPass');
  const ico = document.getElementById('passIcon');
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.className = 'fas fa-eye-slash';
  } else {
    inp.type = 'password';
    ico.className = 'fas fa-eye';
  }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

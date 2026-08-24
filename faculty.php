<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle  = 'Faculty';
$activePage = 'faculty';
$deptId     = (int)($_GET['dept'] ?? 0);
$departments = getDepartments();
$faculty     = getFaculty($deptId, 100);
include __DIR__ . '/includes/header.php';
?>
<div class="page-hero">
  <div class="container-xl position-relative" style="z-index:1">
    <div class="page-hero-label">Our Educators</div>
    <h1 class="page-hero-title">Faculty Members</h1>
    <p class="page-hero-subtitle">Dedicated, qualified educators committed to your academic success</p>
  </div>
</div>
<div class="breadcrumb-wrap">
  <div class="container-xl"><nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php">Home</a></li>
    <li class="breadcrumb-item active">Faculty</li>
  </ol></nav></div>
</div>

<section class="site-section">
  <div class="container-xl">
    <!-- Department filter -->
    <div class="d-flex flex-wrap gap-2 mb-5" data-aos="fade-up">
      <a href="<?= SITE_URL ?>/faculty.php" class="gallery-filter-btn <?= $deptId===0?'active':'' ?>">All Departments</a>
      <?php foreach ($departments as $d): ?>
      <a href="<?= SITE_URL ?>/faculty.php?dept=<?= $d['id'] ?>" class="gallery-filter-btn <?= $deptId===$d['id']?'active':'' ?>"><?= sh($d['name']) ?></a>
      <?php endforeach; ?>
    </div>

    <?php if (!empty($faculty)): ?>
    <div class="row g-4">
      <?php foreach ($faculty as $i => $f): ?>
      <div class="col-6 col-md-4 col-lg-3" data-aos="fade-up" data-aos-delay="<?= ($i%4)*60 ?>">
        <div class="faculty-card">
          <div class="faculty-card-img">
            <?php if ($f['image']): ?>
            <img src="<?= uploadUrl('faculty', sh($f['image'])) ?>" alt="<?= sh($f['name']) ?>">
            <?php else: ?>
            <?php $colors = ['#1a3a8f','#0984e3','#00cec9','#27ae60','#e17055','#6c5ce7','#fd79a8','#f9ca24','#00b894','#a29bfe','#fab1a0','#55efc4']; ?>
            <div style="background:linear-gradient(135deg,<?= $colors[$i%12] ?>,<?= $colors[($i+3)%12] ?>);height:220px;display:flex;align-items:center;justify-content:center">
              <i class="fas fa-user-tie" style="font-size:4rem;color:rgba(255,255,255,.3)"></i>
            </div>
            <?php endif; ?>
          </div>
          <div class="faculty-card-body">
            <div class="faculty-card-name"><?= sh($f['name']) ?></div>
            <div class="faculty-card-role"><?= sh($f['designation']) ?></div>
            <?php if ($f['dept_name'] ?? ''): ?>
            <div style="font-size:.73rem;color:var(--text-3);margin-top:2px"><?= sh($f['dept_name']) ?></div>
            <?php endif; ?>
            <?php if ($f['qualification'] ?? ''): ?>
            <div style="font-size:.72rem;color:var(--secondary);margin-top:4px;font-weight:600"><?= sh($f['qualification']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- Placeholder faculty -->
    <div class="row g-4">
      <?php
      $placeholders = [
        ['Dr. Aisha Siddiqui','HOD Science','Science & Technology','PhD Chemistry'],
        ['Prof. Tariq Ahmed','HOD Commerce','Commerce & Management','M.Com (Gold Medalist)'],
        ['Dr. Fatima Malik','HOD Medical Sciences','Medical Sciences','MBBS, PhD'],
        ['Mr. Usman Khan','Senior Lecturer CS','Science & Technology','MS Computer Science'],
        ['Ms. Sara Iqbal','Lecturer English','Arts & Humanities','MA English Literature'],
        ['Dr. Bilal Hassan','HOD Mathematics','Science & Technology','PhD Mathematics'],
        ['Ms. Nadia Rehman','Senior Lecturer Physics','Science & Technology','MSc Physics'],
        ['Prof. Imran Ali','HOD Arts','Arts & Humanities','MA Fine Arts'],
        ['Mr. Ahmed Raza','Lecturer Biology','Science & Technology','MSc Biology'],
        ['Ms. Zainab Noor','Lecturer Urdu','Arts & Humanities','MA Urdu'],
        ['Dr. Khalid Mehmood','Head of ILC','Inclusive Learning','PhD Special Education'],
        ['Ms. Rabia Khan','Lecturer Statistics','Commerce & Management','MSc Statistics'],
        ['Mr. Faisal Shah','Senior Lecturer Economics','Commerce & Management','MA Economics'],
        ['Ms. Hira Ahmed','Lecturer Computer Science','Science & Technology','BSc CS'],
        ['Prof. Asif Iqbal','Senior Lecturer History','Arts & Humanities','MA History'],
        ['Ms. Sana Javed','Lecturer Pakistan Studies','Arts & Humanities','MA Political Science'],
      ];
      $colors2 = ['#1a3a8f','#0984e3','#00cec9','#27ae60','#e17055','#6c5ce7','#fd79a8','#f9ca24','#00b894','#a29bfe','#fab1a0','#55efc4','#0984e3','#27ae60','#e17055','#6c5ce7'];
      foreach ($placeholders as $i => $p): ?>
      <div class="col-6 col-md-4 col-lg-3" data-aos="fade-up" data-aos-delay="<?= ($i%4)*60 ?>">
        <div class="faculty-card">
          <div class="faculty-card-img">
            <div style="background:linear-gradient(135deg,<?= $colors2[$i] ?>,<?= $colors2[($i+5)%16] ?>);height:220px;display:flex;align-items:center;justify-content:center">
              <i class="fas fa-user-tie" style="font-size:4rem;color:rgba(255,255,255,.3)"></i>
            </div>
          </div>
          <div class="faculty-card-body">
            <div class="faculty-card-name"><?= $p[0] ?></div>
            <div class="faculty-card-role"><?= $p[1] ?></div>
            <div style="font-size:.73rem;color:var(--text-3);margin-top:2px"><?= $p[2] ?></div>
            <div style="font-size:.72rem;color:var(--secondary);margin-top:4px;font-weight:600"><?= $p[3] ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

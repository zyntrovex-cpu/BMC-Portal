<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle  = 'Search';
$activePage = '';
$q       = trim($_GET['q'] ?? '');
$results = [];
if (strlen($q) >= 2) {
    $results = globalSearch($q, 40);
}
include __DIR__ . '/includes/header.php';
// Group results
$grouped = [];
foreach ($results as $r) $grouped[$r['type']][] = $r;
?>
<div class="page-hero">
  <div class="container-xl position-relative" style="z-index:1">
    <h1 class="page-hero-title">Search BMC</h1>
    <form action="<?= SITE_URL ?>/search.php" method="GET" class="d-flex gap-2 mt-3" style="max-width:560px">
      <input type="text" name="q" value="<?= sh($q) ?>" class="form-control" placeholder="Search news, notices, faculty…" style="font-size:.95rem">
      <button class="btn-primary-custom" style="text-decoration:none;white-space:nowrap">Search</button>
    </form>
  </div>
</div>
<div class="breadcrumb-wrap">
  <div class="container-xl"><nav><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php">Home</a></li>
    <li class="breadcrumb-item active">Search</li>
  </ol></nav></div>
</div>

<section class="site-section">
  <div class="container-xl">
    <?php if ($q && empty($results)): ?>
    <div class="text-center py-5" data-aos="fade-up">
      <i class="fas fa-search fa-3x mb-3" style="color:var(--text-3)"></i>
      <h4 style="color:var(--text-2)">No results found for "<?= sh($q) ?>"</h4>
      <p style="color:var(--text-3);font-size:.9rem">Try different keywords or browse the sections below.</p>
      <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
        <?php foreach ([['fa-newspaper','News','/news.php'],['fa-bell','Notices','/notices.php'],['fa-calendar','Events','/events.php'],['fa-chalkboard-teacher','Faculty','/faculty.php'],['fa-download','Downloads','/downloads.php']] as $link): ?>
        <a href="<?= SITE_URL . $link[2] ?>" class="btn-outline-custom" style="text-decoration:none;font-size:.84rem"><i class="fas <?= $link[0] ?> me-2"></i><?= $link[1] ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php elseif ($q): ?>
    <div class="mb-4" data-aos="fade-up">
      <p style="color:var(--text-2);font-size:.9rem">Found <strong><?= count($results) ?></strong> result<?= count($results)!==1?'s':'' ?> for "<strong><?= sh($q) ?></strong>"</p>
    </div>
    <?php $typeLabels = ['news'=>['News Articles','fa-newspaper','secondary'],'notice'=>['Notices','fa-bell','warning'],'event'=>['Events','fa-calendar-alt','success'],'faculty'=>['Faculty','fa-user-tie','primary']];
    foreach ($typeLabels as $type => [$label,$icon,$color]): if (empty($grouped[$type])) continue; ?>
    <div class="mb-5" data-aos="fade-up">
      <h5 style="font-weight:800;color:var(--primary);margin-bottom:16px;display:flex;align-items:center;gap:8px">
        <span class="badge bg-<?= $color ?>" style="font-size:.75rem"><i class="fas <?= $icon ?> me-1"></i><?= $label ?></span>
        <span style="font-size:.88rem;font-weight:600;color:var(--text-3)">(<?= count($grouped[$type]) ?>)</span>
      </h5>
      <?php foreach ($grouped[$type] as $r):
        $href = match($r['type']) {
          'news'    => SITE_URL . '/news.php?id=' . $r['id'],
          'notice'  => SITE_URL . '/notices.php',
          'event'   => SITE_URL . '/events.php',
          'faculty' => SITE_URL . '/faculty.php',
          default   => '#'
        };
      ?>
      <div class="search-result-item" data-aos="fade-up">
        <div class="search-result-type"><?= sh($type) ?></div>
        <div class="search-result-title"><a href="<?= $href ?>"><?= sh($r['title']) ?></a></div>
        <?php if ($r['snippet']): ?><div class="search-result-snippet"><?= sh(truncateText($r['snippet'], 200)) ?></div><?php endif; ?>
        <?php if ($r['date']): ?><div style="font-size:.76rem;color:var(--text-3);margin-top:6px"><i class="fas fa-calendar-alt me-1"></i><?= siteDate($r['date']) ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <!-- Suggested searches -->
    <div class="text-center py-4" data-aos="fade-up">
      <h4 style="color:var(--text-2);margin-bottom:24px">Explore BMC</h4>
      <div class="d-flex flex-wrap justify-content-center gap-3">
        <?php foreach (['Admissions','FSc Pre-Medical','Faculty','Results','Timetable','Notices','Events','Downloads','Scholarships','Library','Contact','Careers'] as $s): ?>
        <a href="?q=<?= urlencode($s) ?>" class="gallery-filter-btn"><?= $s ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

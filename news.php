<?php
require_once __DIR__ . '/includes/functions.php';

$activePage = 'news';
$id         = (int)($_GET['id'] ?? 0);
$cat        = trim($_GET['cat'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 6;
$offset     = ($page - 1) * $perPage;

// ── Demo data fallback ────────────────────────────────────────────────────────
$demoNews = [
    ['id'=>1,'title'=>'BMC Students Secure Top Positions in Karachi Board Examinations 2025','excerpt'=>'Bahria Model College students have once again made the institution proud by securing top positions in the Karachi Board annual examinations, with three students placing in the city-wide merit list.','content'=>'Bahria Model College students have once again demonstrated their academic excellence by securing top positions in the Karachi Board of Secondary Education and Intermediate Education examinations 2025. This year, three of our students placed in the city-wide top-10 merit list. Muhammad Zain Ahmed secured 2nd position in Pre-Medical, Amna Shafiq topped the Commerce group, and Fatima Malik earned 4th position in Pre-Engineering. The Principal praised the students, faculty, and parents for their collective effort in achieving these remarkable results. The college has consistently produced board toppers over the past decade, reflecting its commitment to academic excellence.','category'=>'Academic','published_at'=>'2025-06-15','image'=>'','is_featured'=>1],
    ['id'=>2,'title'=>'New Science Laboratories Inaugurated at BMC Main Campus','excerpt'=>'State-of-the-art physics, chemistry, and biology laboratories have been inaugurated, equipped with modern instruments to enhance practical learning for our students.','content'=>'In a landmark development for Bahria Model College, the Principal Dr. Muhammad Irfan officially inaugurated three brand-new science laboratories on the main campus. The Physics Lab is equipped with advanced oscilloscopes, spectrometers, and electromagnetic induction kits. The Chemistry Lab features a fully ventilated fume hood system, modern analytical balances, and safety stations. The Biology Lab houses digital microscopes connected to display screens for class-wide observation. The new labs accommodate 30 students simultaneously and are available for both morning and evening shifts. The project was completed at a cost of Rs. 1.8 crore, funded through the college development fund.','category'=>'Infrastructure','published_at'=>'2025-05-28','image'=>'','is_featured'=>0],
    ['id'=>3,'title'=>'Annual Sports Gala 2025: BMC Celebrates Athletic Excellence','excerpt'=>'The three-day Annual Sports Gala concluded with record participation, featuring athletics, cricket, football, badminton, and indoor games for over 800 students.','content'=>'The Annual Sports Gala 2025 at Bahria Model College concluded on a high note, with participation from over 800 students across morning and evening shifts. Spread over three days, the event featured track and field athletics, cricket tournament, football league, badminton championships, table tennis, and chess competitions. The event was graced by senior naval officers and local dignitaries. Student Hamza Raza won the overall athletics championship, while Section C clinched the inter-section cricket cup. The closing ceremony featured cultural performances by students and prize distribution. Sports Director Mr. Tariq commended the enthusiasm and sportsmanship displayed throughout the gala.','category'=>'Sports','published_at'=>'2025-05-10','image'=>'','is_featured'=>0],
    ['id'=>4,'title'=>'BMC Launches Digital Library Initiative for Enhanced Learning','excerpt'=>'A new digital library platform has been launched, giving students and faculty access to thousands of e-books, research journals, and academic resources 24/7.','content'=>'Bahria Model College has taken a major step toward modernizing its educational resources by launching a comprehensive Digital Library Initiative. The platform, accessible via student portal credentials, offers access to over 15,000 e-books spanning science, humanities, commerce, and social sciences. The library is integrated with JSTOR and Springer for research journals, giving students access to current academic publications. Faculty can also upload lecture notes and reading materials through the platform. The initiative was developed in partnership with the Bahria University digital resources team. Students can access the library from home, removing the barrier of physical presence. The platform supports offline reading of downloaded resources on mobile and desktop devices.','category'=>'Technology','published_at'=>'2025-04-22','image'=>'','is_featured'=>1],
    ['id'=>5,'title'=>'Blood Donation Drive Raises 150 Units at BMC Community Event','excerpt'=>'In collaboration with the Aga Khan Hospital blood bank, BMC organized a successful blood donation camp with overwhelming support from students, staff, and parents.','content'=>'Bahria Model College hosted a large-scale Blood Donation Drive in collaboration with the Aga Khan University Hospital Blood Bank and the Pakistan Red Crescent Society. The camp, organized by the BMC Social Welfare Club, collected 150 units of blood over two days. Over 200 volunteers registered, and 168 were found medically eligible to donate. Students, teaching staff, non-teaching staff, and parents all participated enthusiastically. The drive was organized under the supervision of trained medical professionals and nurses. Certificates of appreciation were awarded to all donors by the Principal. The Social Welfare Club plans to make this an annual event, underscoring BMC's commitment to community service.','category'=>'Community','published_at'=>'2025-03-18','image'=>'','is_featured'=>0],
    ['id'=>6,'title'=>'Parent-Teacher Meeting Focuses on Student Progress and Well-Being','excerpt'=>'The bi-annual Parent-Teacher Meeting was held with strong turnout, providing families with detailed feedback on their children\'s academic performance and holistic development.','content'=>'Bahria Model College held its bi-annual Parent-Teacher Meeting (PTM) for the academic year 2024–25, with an exceptional turnout of over 1,200 parents across both shifts. The PTM focused not only on academic progress reports but also on students\' social development, extracurricular participation, and mental well-being. Subject teachers met individually with parents to discuss performance, attendance, and areas for improvement. The college's counselling team was present to address concerns related to student stress and career guidance. Several parents commended the college administration for its transparent communication and proactive pastoral care. The next PTM is scheduled after the mid-term examinations in October.','category'=>'Events','published_at'=>'2025-02-05','image'=>'','is_featured'=>0],
];

$categories = ['Academic', 'Infrastructure', 'Sports', 'Technology', 'Community', 'Events'];

// ── Single article view ───────────────────────────────────────────────────────
if ($id > 0) {
    $article = getNewsById($id);
    if (!$article) {
        foreach ($demoNews as $n) {
            if ($n['id'] === $id) { $article = $n; break; }
        }
    }
    if (!$article) {
        header('Location: ' . SITE_URL . '/news.php');
        exit;
    }
    $pageTitle = $article['title'];
    $pageDesc  = truncateText($article['excerpt'] ?? $article['content'] ?? '', 160);
    include __DIR__ . '/includes/header.php';
?>

<!-- ── Page Hero ── -->
<div class="page-hero">
  <div class="container-xl">
    <h1 class="page-hero-title" data-aos="fade-up"><?= sh($article['title']) ?></h1>
    <p class="page-hero-sub" data-aos="fade-up" data-aos-delay="100">
      <?= sh($article['excerpt'] ?? '') ?>
    </p>
  </div>
</div>
<div class="breadcrumb-wrap">
  <div class="container-xl">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php"><i class="fas fa-home me-1"></i>Home</a></li>
        <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/news.php">News</a></li>
        <li class="breadcrumb-item active"><?= sh(truncateText($article['title'], 50)) ?></li>
      </ol>
    </nav>
  </div>
</div>

<!-- ── Article ── -->
<section class="site-section">
  <div class="container-xl">
    <div class="row justify-content-center">
      <div class="col-lg-8" data-aos="fade-up">
        <div class="article-wrap">
          <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <?php if (!empty($article['category'])): ?>
              <span class="badge bg-primary"><?= sh($article['category']) ?></span>
            <?php endif; ?>
            <span class="text-muted small"><i class="fas fa-calendar me-1"></i><?= siteDate($article['published_at'] ?? $article['created_at'] ?? '') ?></span>
          </div>
          <h2 class="article-title mb-4"><?= sh($article['title']) ?></h2>
          <?php if (!empty($article['image'])): ?>
            <img src="<?= uploadUrl('news', $article['image']) ?>" alt="<?= sh($article['title']) ?>" class="img-fluid rounded-3 mb-4 w-100" style="max-height:460px;object-fit:cover;">
          <?php else: ?>
            <div class="rounded-3 mb-4 d-flex align-items-center justify-content-center" style="height:280px;background:linear-gradient(135deg,#1a3a8f 0%,#0984e3 100%);">
              <i class="fas fa-newspaper text-white" style="font-size:4rem;opacity:.4"></i>
            </div>
          <?php endif; ?>
          <div class="article-body">
            <?= nl2br(sh($article['content'] ?? '')) ?>
          </div>
          <hr class="my-4">
          <a href="<?= SITE_URL ?>/news.php" class="btn-outline-custom">
            <i class="fas fa-arrow-left me-2"></i>Back to News
          </a>
        </div>
      </div>
      <!-- Sidebar -->
      <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
        <div class="sidebar-card mb-4">
          <h6 class="sidebar-heading"><i class="fas fa-newspaper me-2"></i>Recent News</h6>
          <?php $recent = getNews(5); if (empty($recent)) $recent = array_slice($demoNews, 0, 5); ?>
          <?php foreach ($recent as $rn): ?>
            <?php if ((int)($rn['id'] ?? 0) === $id) continue; ?>
            <a href="<?= SITE_URL ?>/news.php?id=<?= (int)$rn['id'] ?>" class="sidebar-news-item">
              <span class="sidebar-news-date"><?= siteDate($rn['published_at'] ?? '') ?></span>
              <span class="sidebar-news-title"><?= sh(truncateText($rn['title'], 70)) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
        <div class="sidebar-card">
          <h6 class="sidebar-heading"><i class="fas fa-tags me-2"></i>Categories</h6>
          <?php foreach ($categories as $c): ?>
            <a href="<?= SITE_URL ?>/news.php?cat=<?= urlencode($c) ?>" class="cat-pill"><?= sh($c) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// ── List view ─────────────────────────────────────────────────────────────────
// Try DB with pagination
$dbNews = [];
$totalCount = 0;
try {
    $db = siteDB();
    $whereClause = 'WHERE is_published=1';
    $params = [];
    if ($cat) { $whereClause .= ' AND category=?'; $params[] = $cat; }
    $countSt = $db->prepare("SELECT COUNT(*) FROM site_news $whereClause");
    $countSt->execute($params);
    $totalCount = (int)$countSt->fetchColumn();

    $params[] = $perPage;
    $params[] = $offset;
    $st = $db->prepare("SELECT * FROM site_news $whereClause ORDER BY published_at DESC, created_at DESC LIMIT ? OFFSET ?");
    $st->execute($params);
    $dbNews = $st->fetchAll();
} catch (Exception $e) {}

if (!empty($dbNews)) {
    $newsList = $dbNews;
    $totalPages = max(1, (int)ceil($totalCount / $perPage));
} else {
    // Fallback to demo
    $filtered = $cat ? array_values(array_filter($demoNews, fn($n) => $n['category'] === $cat)) : $demoNews;
    $totalCount = count($filtered);
    $totalPages = max(1, (int)ceil($totalCount / $perPage));
    $newsList = array_slice($filtered, $offset, $perPage);
}

$pageTitle = $cat ? sh($cat) . ' News' : 'Latest News';
$pageDesc  = 'Stay updated with the latest news, achievements, and announcements from Bahria Model College.';
include __DIR__ . '/includes/header.php';
?>

<!-- ── Page Hero ── -->
<div class="page-hero">
  <div class="container-xl">
    <h1 class="page-hero-title" data-aos="fade-up">Latest News</h1>
    <p class="page-hero-sub" data-aos="fade-up" data-aos-delay="100">
      Stay informed with the latest achievements, events, and announcements from Bahria Model College
    </p>
  </div>
</div>
<div class="breadcrumb-wrap">
  <div class="container-xl">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php"><i class="fas fa-home me-1"></i>Home</a></li>
        <li class="breadcrumb-item active">News</li>
      </ol>
    </nav>
  </div>
</div>

<!-- ── Category Tabs ── -->
<section class="site-section pb-0">
  <div class="container-xl">
    <div class="filter-tabs" data-aos="fade-up">
      <a href="<?= SITE_URL ?>/news.php" class="filter-tab <?= !$cat ? 'active' : '' ?>">All</a>
      <?php foreach ($categories as $c): ?>
        <a href="<?= SITE_URL ?>/news.php?cat=<?= urlencode($c) ?>" class="filter-tab <?= $cat === $c ? 'active' : '' ?>"><?= sh($c) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── News Grid ── -->
<section class="site-section">
  <div class="container-xl">
    <?php if (empty($newsList)): ?>
      <div class="empty-state text-center py-5" data-aos="fade-up">
        <i class="fas fa-newspaper text-muted" style="font-size:3rem"></i>
        <h5 class="mt-3 text-muted">No news articles found<?= $cat ? ' in ' . sh($cat) : '' ?>.</h5>
        <a href="<?= SITE_URL ?>/news.php" class="btn-primary-custom mt-3">View All News</a>
      </div>
    <?php else: ?>
    <div class="row g-4">
      <?php foreach ($newsList as $i => $n): ?>
      <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 80 ?>">
        <article class="news-card h-100">
          <div class="news-card-img">
            <?php if (!empty($n['image'])): ?>
              <img src="<?= uploadUrl('news', $n['image']) ?>" alt="<?= sh($n['title']) ?>" loading="lazy">
            <?php else: ?>
              <div class="news-card-placeholder" style="background:linear-gradient(135deg,<?= ['#1a3a8f','#006b35','#6c1a8f','#8f1a1a','#1a6b8f','#8f6b1a'][$i % 6] ?> 0%,<?= ['#0984e3','#00b894','#a855f7','#e53e3e','#0bb5e0','#e0a30b'][$i % 6] ?> 100%)">
                <i class="fas fa-newspaper"></i>
              </div>
            <?php endif; ?>
            <?php if (!empty($n['category'])): ?>
              <span class="news-card-cat"><?= sh($n['category']) ?></span>
            <?php endif; ?>
          </div>
          <div class="news-card-body">
            <div class="news-card-meta">
              <i class="fas fa-calendar-alt me-1"></i><?= siteDate($n['published_at'] ?? $n['created_at'] ?? '') ?>
            </div>
            <h5 class="news-card-title">
              <a href="<?= SITE_URL ?>/news.php?id=<?= (int)$n['id'] ?>"><?= sh($n['title']) ?></a>
            </h5>
            <p class="news-card-excerpt"><?= sh(truncateText($n['excerpt'] ?? $n['content'] ?? '', 130)) ?></p>
            <a href="<?= SITE_URL ?>/news.php?id=<?= (int)$n['id'] ?>" class="news-card-link">
              Read More <i class="fas fa-arrow-right ms-1"></i>
            </a>
          </div>
        </article>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-5 d-flex justify-content-center" data-aos="fade-up">
      <ul class="pagination-custom">
        <?php if ($page > 1): ?>
          <li><a href="<?= SITE_URL ?>/news.php?page=<?= $page-1 ?><?= $cat ? '&cat='.urlencode($cat) : '' ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a></li>
        <?php endif; ?>
        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
          <li><a href="<?= SITE_URL ?>/news.php?page=<?= $p ?><?= $cat ? '&cat='.urlencode($cat) : '' ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a></li>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <li><a href="<?= SITE_URL ?>/news.php?page=<?= $page+1 ?><?= $cat ? '&cat='.urlencode($cat) : '' ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a></li>
        <?php endif; ?>
      </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

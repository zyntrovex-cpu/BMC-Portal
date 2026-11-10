<?php
require_once __DIR__ . '/includes/functions.php';

$activePage = 'gallery';
$pageTitle  = 'Gallery';
$pageDesc   = 'Explore photos and videos from Bahria Model College — campus life, academic events, sports, cultural programmes, and more.';

$albums  = getGalleryAlbums();
$albumId = (int)($_GET['album'] ?? 0);
$photos  = getGalleryPhotos($albumId, 60);
$videos  = getVideos(12);

// Demo album names for filter (if DB empty)
$demoAlbumNames = [
    1 => 'Campus & Facilities',
    2 => 'Annual Sports Gala 2025',
    3 => 'Science Exhibition 2024',
    4 => 'Prize Distribution Ceremony',
    5 => 'Independence Day Celebration',
    6 => 'Student Life & Activities',
];

// 16 gradient placeholder colours for empty gallery
$gradients = [
    'linear-gradient(135deg,#1a3a8f,#0984e3)',
    'linear-gradient(135deg,#006b35,#00b894)',
    'linear-gradient(135deg,#6c1a8f,#a855f7)',
    'linear-gradient(135deg,#8f1a1a,#e53e3e)',
    'linear-gradient(135deg,#1a6b8f,#0bb5e0)',
    'linear-gradient(135deg,#8f6b1a,#e0a30b)',
    'linear-gradient(135deg,#1a8f6b,#10dba6)',
    'linear-gradient(135deg,#3a1a8f,#7c3aed)',
    'linear-gradient(135deg,#8f3a1a,#f97316)',
    'linear-gradient(135deg,#1a8f3a,#22c55e)',
    'linear-gradient(135deg,#8f1a6b,#ec4899)',
    'linear-gradient(135deg,#0d4b8f,#3b82f6)',
    'linear-gradient(135deg,#4b8f0d,#84cc16)',
    'linear-gradient(135deg,#8f4b0d,#f59e0b)',
    'linear-gradient(135deg,#0d8f4b,#34d399)',
    'linear-gradient(135deg,#4b0d8f,#8b5cf6)',
];

$icons = ['fa-building','fa-futbol','fa-flask','fa-trophy','fa-flag','fa-users','fa-camera','fa-microscope',
          'fa-book-open','fa-music','fa-palette','fa-graduation-cap','fa-tree','fa-star','fa-award','fa-heart'];

include __DIR__ . '/includes/header.php';
?>

<!-- ── Page Hero ── -->
<div class="page-hero">
  <div class="container-xl">
    <h1 class="page-hero-title" data-aos="fade-up">Photo Gallery</h1>
    <p class="page-hero-sub" data-aos="fade-up" data-aos-delay="100">
      Moments captured — campus life, achievements, events, and the vibrant spirit of BMC
    </p>
  </div>
</div>
<div class="breadcrumb-wrap">
  <div class="container-xl">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php"><i class="fas fa-home me-1"></i>Home</a></li>
        <li class="breadcrumb-item active">Gallery</li>
      </ol>
    </nav>
  </div>
</div>

<!-- ── Main Gallery Section ── -->
<section class="site-section">
  <div class="container-xl">

    <!-- Section tabs: Photos / Videos -->
    <div class="filter-tabs mb-4" data-aos="fade-up">
      <button class="filter-tab active" id="btnPhotos"><i class="fas fa-images me-2"></i>Photos</button>
      <button class="filter-tab" id="btnVideos"><i class="fas fa-video me-2"></i>Videos</button>
    </div>

    <!-- ── Photos Panel ── -->
    <div id="photosPanel">

      <!-- Album filter buttons -->
      <?php if (!empty($albums) || !empty($photos)): ?>
      <div class="gallery-filter-row mb-4" data-aos="fade-up">
        <button class="gallery-filter-btn active" data-album="all">All Albums</button>
        <?php if (!empty($albums)): ?>
          <?php foreach ($albums as $a): ?>
            <button class="gallery-filter-btn" data-album="<?= (int)$a['id'] ?>">
              <?= sh($a['name']) ?>
              <?php if (!empty($a['photo_count'])): ?>
                <span class="filter-count"><?= (int)$a['photo_count'] ?></span>
              <?php endif; ?>
            </button>
          <?php endforeach; ?>
        <?php else: ?>
          <?php foreach ($demoAlbumNames as $aid => $aname): ?>
            <button class="gallery-filter-btn" data-album="<?= $aid ?>"><?= sh($aname) ?></button>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Photo grid -->
      <div class="gallery-grid" id="galleryGrid">
        <?php if (!empty($photos)): ?>
          <?php foreach ($photos as $i => $p): ?>
          <div class="gallery-item" data-album="<?= (int)($p['album_id'] ?? 0) ?>" data-aos="zoom-in" data-aos-delay="<?= ($i % 4) * 60 ?>">
            <a href="<?= uploadUrl('gallery', $p['filename'] ?? $p['image'] ?? '') ?>"
               class="glightbox"
               data-gallery="campus"
               data-title="<?= sh($p['caption'] ?? $p['title'] ?? $p['album_name'] ?? '') ?>"
               data-description="<?= sh($p['album_name'] ?? '') ?>">
              <img src="<?= uploadUrl('gallery', $p['filename'] ?? $p['image'] ?? '') ?>"
                   alt="<?= sh($p['caption'] ?? $p['title'] ?? '') ?>"
                   loading="lazy">
              <div class="gallery-overlay">
                <i class="fas fa-search-plus"></i>
                <?php if (!empty($p['album_name'])): ?>
                  <span class="gallery-album-label"><?= sh($p['album_name']) ?></span>
                <?php endif; ?>
              </div>
            </a>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <!-- 16 placeholder boxes when no photos exist -->
          <?php foreach ($gradients as $i => $grad): ?>
          <div class="gallery-item gallery-placeholder" data-album="<?= ($i % 6) + 1 ?>" data-aos="zoom-in" data-aos-delay="<?= ($i % 4) * 60 ?>">
            <div class="gallery-placeholder-inner" style="background:<?= $grad ?>">
              <i class="fas <?= $icons[$i] ?>"></i>
              <span><?= array_values($demoAlbumNames)[($i % 6)] ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Empty state -->
      <div id="galleryEmptyState" class="empty-state text-center py-5 d-none">
        <i class="fas fa-images text-muted" style="font-size:3rem"></i>
        <h5 class="mt-3 text-muted">No photos in this album.</h5>
      </div>

    </div><!-- /photosPanel -->

    <!-- ── Videos Panel ── -->
    <div id="videosPanel" class="d-none">
      <?php $videoList = $videos; ?>
      <?php if (!empty($videoList)): ?>
      <div class="row g-4">
        <?php foreach ($videoList as $i => $v): ?>
          <?php $ytId = getYoutubeId($v['url'] ?? $v['youtube_url'] ?? ''); ?>
          <?php if (!$ytId) continue; ?>
          <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 80 ?>">
            <div class="video-card">
              <div class="video-embed-wrap">
                <iframe src="https://www.youtube.com/embed/<?= sh($ytId) ?>?rel=0&modestbranding=1"
                        title="<?= sh($v['title'] ?? '') ?>"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                        loading="lazy"></iframe>
              </div>
              <?php if (!empty($v['title'])): ?>
              <div class="video-card-body">
                <h6 class="video-title"><?= sh($v['title']) ?></h6>
                <?php if (!empty($v['created_at'])): ?>
                  <span class="video-date text-muted small"><i class="fas fa-calendar me-1"></i><?= siteDate($v['created_at']) ?></span>
                <?php endif; ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <!-- Demo video placeholders -->
        <div class="row g-4">
          <?php
          $demoVids = [
              ['title'=>'BMC Annual Prize Distribution 2024 – Highlights', 'desc'=>'Watch the highlights from our annual prize distribution ceremony.'],
              ['title'=>'Campus Tour – Bahria Model College Bin Qasim', 'desc'=>'A walkthrough of our state-of-the-art campus facilities.'],
              ['title'=>'Annual Sports Gala 2024–25 – Best Moments', 'desc'=>'Exciting moments from three days of sports at BMC.'],
          ];
          foreach ($demoVids as $i => $dv): ?>
          <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
            <div class="video-card">
              <div class="video-embed-placeholder d-flex align-items-center justify-content-center rounded-3" style="aspect-ratio:16/9;background:linear-gradient(135deg,#1a1a2e,#16213e);">
                <div class="text-center text-white">
                  <i class="fab fa-youtube" style="font-size:3rem;opacity:.6;color:#ff0000"></i>
                  <p class="mt-2 small opacity-75">Video Coming Soon</p>
                </div>
              </div>
              <div class="video-card-body">
                <h6 class="video-title"><?= sh($dv['title']) ?></h6>
                <p class="text-muted small"><?= sh($dv['desc']) ?></p>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div><!-- /videosPanel -->

  </div>
</section>

<script>
// Photos / Videos tab toggle
document.getElementById('btnPhotos').addEventListener('click', function() {
  this.classList.add('active');
  document.getElementById('btnVideos').classList.remove('active');
  document.getElementById('photosPanel').classList.remove('d-none');
  document.getElementById('videosPanel').classList.add('d-none');
});
document.getElementById('btnVideos').addEventListener('click', function() {
  this.classList.add('active');
  document.getElementById('btnPhotos').classList.remove('active');
  document.getElementById('videosPanel').classList.remove('d-none');
  document.getElementById('photosPanel').classList.add('d-none');
});

// Album filter
document.querySelectorAll('.gallery-filter-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.gallery-filter-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    const album = this.dataset.album;
    let visible = 0;
    document.querySelectorAll('#galleryGrid .gallery-item').forEach(item => {
      const show = album === 'all' || item.dataset.album === album;
      item.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    const emptyState = document.getElementById('galleryEmptyState');
    if (emptyState) emptyState.classList.toggle('d-none', visible > 0);
  });
});

// Init GLightbox
if (typeof GLightbox !== 'undefined') {
  GLightbox({ selector: '.glightbox', touchNavigation: true, loop: true });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

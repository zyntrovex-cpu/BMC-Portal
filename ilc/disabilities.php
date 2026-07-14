<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('ilc_vp');
$db   = getDB();

$catFilter = (int)($_GET['cat'] ?? 0);
$search    = trim($_GET['q'] ?? '');

$sql = 'SELECT sd.id AS dis_id, sd.notes, sd.created_at,
               u.name AS student_name, u.user_id AS student_uid,
               s.id AS student_id, s.roll_no,
               c.name AS class_name,
               dc.id AS cat_id, dc.name AS cat_name,
               dst.name AS subtype_name,
               ru.name AS recorded_by
        FROM student_disabilities sd
        JOIN students s   ON s.id   = sd.student_id
        JOIN classes c    ON c.id   = s.class_id
        JOIN users u      ON u.id   = s.user_id
        JOIN disability_subtypes dst ON dst.id = sd.subtype_id
        JOIN disability_categories dc ON dc.id = dst.category_id
        JOIN users ru     ON ru.id  = sd.recorded_by
        WHERE c.is_ilc = 1';
$params = [];

if ($catFilter) { $sql .= ' AND dc.id = ?'; $params[] = $catFilter; }
if ($search) {
    $sql    .= ' AND (u.name LIKE ? OR u.user_id LIKE ?)';
    $like    = "%$search%";
    $params[] = $like; $params[] = $like;
}
$sql .= ' ORDER BY dc.name, dst.name, u.name';

$st = $db->prepare($sql);
$st->execute($params);
$records = $st->fetchAll();

$categories = $db->query('SELECT * FROM disability_categories ORDER BY name')->fetchAll();

// Category counts for summary
$catCounts = $db->query(
    'SELECT dc.name, COUNT(sd.id) AS cnt
     FROM disability_categories dc
     LEFT JOIN disability_subtypes dst ON dst.category_id=dc.id
     LEFT JOIN student_disabilities sd ON sd.subtype_id=dst.id
     LEFT JOIN students s ON s.id=sd.student_id
     LEFT JOIN classes c ON c.id=s.class_id AND c.is_ilc=1
     GROUP BY dc.id, dc.name ORDER BY cnt DESC'
)->fetchAll();

pageHead('Disability Records', 'ilc_vp');
$links = getIlcLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'disabilities', $links, $user); ?>
<div class="main-area">
<?php topbar('Disability Records', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- ILC branding strip -->
<div class="d-flex align-items-center gap-3 mb-4 p-3" style="background:linear-gradient(90deg,#ecfeff,#f0fdf4);border-radius:10px;border:1px solid #a5f3fc;">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#0891b2;letter-spacing:.8px;text-transform:uppercase">Inclusive Learning Centre</div>
    <div style="font-size:.8rem;color:#475569">Bahria Model College Bin Qasim</div>
  </div>
</div>

<div class="row g-3">
  <!-- Category summary sidebar -->
  <div class="col-lg-3">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-chart-pie me-2"></i>By Category</div>
      <div style="padding:12px 16px">
        <a href="<?= url('/ilc/disabilities.php') . ($search?'?q='.urlencode($search):'') ?>"
           class="d-flex justify-content-between mb-2 text-decoration-none <?= !$catFilter?'fw-bold':'' ?>" style="font-size:.84rem;color:inherit">
          <span>All Categories</span>
          <span class="badge" style="background:#0891b2"><?= count($records) ?></span>
        </a>
        <?php foreach ($catCounts as $cc): ?>
        <?php $catRow = array_filter($categories, fn($c) => $c['name']===$cc['name']); $catRow = reset($catRow); ?>
        <a href="?cat=<?= $catRow['id'] ?><?= $search?'&q='.urlencode($search):'' ?>"
           class="d-flex justify-content-between mb-2 text-decoration-none <?= $catFilter==$catRow['id']?'fw-bold':'' ?>"
           style="font-size:.82rem;color:inherit">
          <span><?= h($cc['name']) ?></span>
          <span class="badge bg-secondary"><?= $cc['cnt'] ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Records table -->
  <div class="col-lg-9">
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-heartbeat me-2"></i>Records (<?= count($records) ?>)</span>
        <form method="GET" class="d-flex gap-2">
          <?php if ($catFilter): ?><input type="hidden" name="cat" value="<?= $catFilter ?>"><?php endif; ?>
          <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm"
                 placeholder="Search student…" style="width:170px">
          <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
          <?php if ($search||$catFilter): ?><a href="<?= url('/ilc/disabilities.php') ?>" class="btn btn-sm btn-outline-danger">Clear</a><?php endif; ?>
        </form>
      </div>

      <?php if (empty($records)): ?>
      <div style="padding:40px;text-align:center;color:var(--t2);font-size:.85rem">
        No disability records found. Go to a <a href="<?= url('/ilc/students.php') ?>">student profile</a> to add records.
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.84rem">
          <thead class="table-light">
            <tr><th>Student</th><th>Roll No</th><th>Class</th><th>Category</th><th>Subtype</th><th>Notes</th><th>Recorded</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($records as $r): ?>
            <tr>
              <td class="fw-semibold"><?= h($r['student_name']) ?></td>
              <td><?= h($r['roll_no']) ?></td>
              <td><span class="badge bg-secondary"><?= h($r['class_name']) ?></span></td>
              <td><span class="badge" style="background:#0891b2;font-size:.72rem"><?= h($r['cat_name']) ?></span></td>
              <td><?= h($r['subtype_name']) ?></td>
              <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($r['notes']??'') ?>"><?= h($r['notes'] ?: '—') ?></td>
              <td style="font-size:.78rem"><?= fDate($r['created_at']) ?></td>
              <td>
                <a href="<?= url('/ilc/student-profile.php') ?>?id=<?= $r['student_id'] ?>" class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 7px">
                  <i class="fas fa-user"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

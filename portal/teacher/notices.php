<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user    = requireAuth('teacher');
requirePermission('notices');
$db      = getDB();

// ── POST: Create / Delete notice ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_notice') {
        $title    = trim($_POST['title']    ?? '');
        $body     = trim($_POST['body']     ?? '');
        $category = $_POST['category']      ?? 'General';
        $priority = $_POST['priority']      ?? 'normal';
        $audience = $_POST['audience']      ?? 'students';
        $expiry   = $_POST['expiry_date']   ?? '';

        $validCats = ['General','Academic','Exam','Holiday','Finance','Emergency'];
        $category  = in_array($category, $validCats) ? $category : 'General';
        $priority  = in_array($priority, ['normal','important','urgent']) ? $priority : 'normal';
        $audienceMap = [
            'students'         => 'students',
            'students,teachers'=> 'students,teachers',
        ];
        $audience = $audienceMap[$audience] ?? 'students';

        if (!$title || !$body) {
            setFlash('danger', 'Title and message are required.');
        } else {
            $db->prepare(
                'INSERT INTO notices (title, body, category, priority, audience, author_id, expiry_date)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([$title, $body, $category, $priority, $audience, $user['id'], $expiry ?: null]);
            logActivity($user['id'], 'notice_create', "Teacher posted notice: $title");
            setFlash('success', 'Notice posted successfully.');
        }
    }

    if ($action === 'delete_notice') {
        $id = (int)($_POST['notice_id'] ?? 0);
        // Teachers can only delete their own notices
        $db->prepare('DELETE FROM notices WHERE id=? AND author_id=?')->execute([$id, $user['id']]);
        setFlash('success', 'Notice removed.');
    }

    redirect('/teacher/notices.php');
}

$notices = getNoticesForPortal('teacher');
$cat     = $_GET['cat'] ?? '';
$q       = trim($_GET['q'] ?? '');
$tab     = $_GET['tab'] ?? 'all';

$myNotices = array_filter($notices, fn($n) => (int)($n['author_id'] ?? 0) === $user['id']);
$allNotices = $notices;

if ($tab === 'mine') $notices = $myNotices;
if ($cat) $notices = array_filter($notices, fn($n) => $n['category'] === $cat);
if ($q)   $notices = array_filter($notices, fn($n) => stripos($n['title'],$q)!==false || stripos($n['body'],$q)!==false);

$categories = ['General','Academic','Exam','Holiday','Finance','Emergency'];

pageHead('Notices', 'teacher');
$links = getTeacherLinks();
?>
<div class="portal-wrap">
<?php sidebar('teacher', 'notices', $links, $user); ?>
<div class="main-area">
<?php topbar('Notices', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Post a notice -->
<div class="sec-card mb-3">
  <div class="sec-card-header d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#postNoticeForm" style="cursor:pointer">
    <span><i class="fas fa-plus me-2"></i>Post a Notice to Students</span>
    <i class="fas fa-chevron-down" style="font-size:.8rem"></i>
  </div>
  <div id="postNoticeForm" class="collapse">
    <div style="padding:16px">
      <form method="POST">
        <input type="hidden" name="action" value="create_notice">
        <div class="row g-2">
          <div class="col-md-8">
            <label class="form-label fw-semibold" style="font-size:.82rem">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control form-control-sm" required placeholder="Notice title…">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" style="font-size:.82rem">Category</label>
            <select name="category" class="form-select form-select-sm">
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c ?>"><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold" style="font-size:.82rem">Message <span class="text-danger">*</span></label>
            <textarea name="body" class="form-control form-control-sm" rows="4" required placeholder="Write the notice details here…"></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" style="font-size:.82rem">Priority</label>
            <select name="priority" class="form-select form-select-sm">
              <option value="normal">Normal</option>
              <option value="important">Important</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" style="font-size:.82rem">Audience</label>
            <select name="audience" class="form-select form-select-sm">
              <option value="students">Students only</option>
              <option value="students,teachers">Students &amp; Teachers</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" style="font-size:.82rem">Expires On <small class="text-muted">(optional)</small></label>
            <input type="date" name="expiry_date" class="form-control form-control-sm" min="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn btn-sm btn-success px-4">
              <i class="fas fa-paper-plane me-1"></i>Post Notice
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Filter & Tab bar -->
<div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
  <div class="btn-group btn-group-sm">
    <a href="?tab=all" class="btn btn-outline-secondary <?= $tab==='all'?'active':'' ?>">All Notices</a>
    <a href="?tab=mine" class="btn btn-outline-primary <?= $tab==='mine'?'active':'' ?>">My Notices (<?= count($myNotices) ?>)</a>
  </div>
  <form method="GET" class="d-flex gap-2 ms-auto flex-wrap">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <input type="text" name="q" class="form-control form-control-sm" style="max-width:200px" placeholder="Search…" value="<?= h($q) ?>">
    <select name="cat" class="form-select form-select-sm" style="max-width:160px" onchange="this.form.submit()">
      <option value="">All Categories</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= h($c) ?>" <?= $cat===$c?'selected':'' ?>><?= h($c) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
    <?php if ($cat || $q): ?><a href="?tab=<?= h($tab) ?>" class="btn btn-sm btn-outline-danger">Clear</a><?php endif; ?>
  </form>
</div>

<?php if (empty($notices)): ?>
  <div class="sec-card p-4 text-center text-muted">No notices found.</div>
<?php else: ?>
  <?php foreach ($notices as $n):
    $priorityClass = match($n['priority']) { 'urgent'=>'danger','important'=>'warning',default=>'secondary' };
    $catClass = match($n['category']) { 'Exam'=>'primary','Academic'=>'info','Holiday'=>'success','Emergency'=>'danger','Finance'=>'warning',default=>'secondary' };
    $isOwn = (int)($n['author_id'] ?? 0) === $user['id'];
  ?>
  <div class="sec-card mb-3 <?= $n['pinned']?'border-warning':'' ?>" <?= $isOwn ? 'style="border-left:3px solid #059669"' : '' ?>>
    <div class="sec-card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <?php if($n['pinned']): ?><i class="fas fa-thumbtack text-warning me-1"></i><?php endif; ?>
        <?php if($isOwn): ?><span class="badge bg-success me-1" style="font-size:.68rem">Your post</span><?php endif; ?>
        <strong><?= h($n['title']) ?></strong>
      </div>
      <div class="d-flex gap-1 align-items-center">
        <span class="badge bg-<?= $catClass ?>"><?= h($n['category']) ?></span>
        <span class="badge bg-<?= $priorityClass ?>"><?= h(ucfirst($n['priority'])) ?></span>
        <?php if ($isOwn): ?>
        <form method="POST" class="d-inline ms-1" onsubmit="return confirm('Delete this notice?')">
          <input type="hidden" name="action" value="delete_notice">
          <input type="hidden" name="notice_id" value="<?= $n['id'] ?>">
          <button class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:1px 6px"><i class="fas fa-trash"></i></button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <div style="padding:14px 16px">
      <p style="font-size:.9rem;margin-bottom:8px;white-space:pre-line"><?= h($n['body']) ?></p>
      <div style="font-size:.78rem;color:#9ca3af">
        <i class="fas fa-user me-1"></i><?= h($n['author_name'] ?? 'Admin') ?>
        &nbsp;·&nbsp;<i class="fas fa-clock me-1"></i><?= fDate($n['created_at']) ?>
        &nbsp;·&nbsp;<i class="fas fa-users me-1"></i><?= h($n['audience'] ?? 'all') ?>
        <?php if($n['expiry_date']): ?>&nbsp;·&nbsp;Expires: <?= fDate($n['expiry_date']) ?><?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

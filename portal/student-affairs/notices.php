<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('student_affairs');
$db   = getDB();

// ── POST handlers ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        // SA can only delete their own notices
        $db->prepare('DELETE FROM notices WHERE id = ? AND author_id = ?')->execute([$id, $user['id']]);
        logActivity($user['id'], 'notice_delete', "Deleted notice #$id");
        setFlash('success', 'Notice deleted.');
        redirect('/portal/student-affairs/notices.php');
    }

    if ($action === 'toggle_pin' && $id) {
        // Only own notices
        $db->prepare('UPDATE notices SET pinned = !pinned WHERE id = ? AND author_id = ?')->execute([$id, $user['id']]);
        redirect('/portal/student-affairs/notices.php');
    }

    $title      = trim($_POST['title']       ?? '');
    $body       = trim($_POST['body']        ?? '');
    $category   = $_POST['category']         ?? 'General';
    $priority   = $_POST['priority']         ?? 'Normal';
    $audience   = $_POST['audience']         ?? [];
    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $pinned     = isset($_POST['pinned']) ? 1 : 0;

    $validCats  = ['General','Academic','Exam','Holiday','Finance','Emergency'];
    $validPrios = ['Normal','Important','Urgent'];
    if (!in_array($category, $validCats))  $category = 'General';
    if (!in_array($priority, $validPrios)) $priority = 'Normal';

    $audValid    = ['students','teachers','finance','admin'];
    $audFiltered = array_filter((array)$audience, fn($a) => in_array($a, $audValid));
    $audStr      = implode(',', $audFiltered);

    if (!$title || !$body) {
        setFlash('danger', 'Title and body are required.');
        redirect('/portal/student-affairs/notices.php');
    }

    if ($action === 'update' && $id) {
        // SA can only edit their own notices
        $db->prepare('UPDATE notices SET title=?,body=?,category=?,priority=?,audience=?,expiry_date=?,pinned=? WHERE id=? AND author_id=?')
           ->execute([$title, $body, $category, $priority, $audStr, $expiryDate, $pinned, $id, $user['id']]);
        logActivity($user['id'], 'notice_update', "Updated notice: $title");
        setFlash('success', 'Notice updated.');
    } else {
        $db->prepare('INSERT INTO notices (title,body,category,priority,audience,expiry_date,pinned,author_id) VALUES (?,?,?,?,?,?,?,?)')
           ->execute([$title, $body, $category, $priority, $audStr, $expiryDate, $pinned, $user['id']]);
        logActivity($user['id'], 'notice_create', "Created notice: $title (student_affairs)");
        setFlash('success', 'Notice posted.');
    }
    redirect('/portal/student-affairs/notices.php');
}

// ── Fetch notices posted by this user (+ all for read) ────────────
// Show SA's own notices in the edit list, plus all notices read-only view option
$myNotices = $db->prepare(
    'SELECT n.*, u.name AS author_name
     FROM notices n
     LEFT JOIN users u ON n.author_id = u.id
     WHERE n.author_id = ?
     ORDER BY n.pinned DESC, n.created_at DESC'
);
$myNotices->execute([$user['id']]);
$notices = $myNotices->fetchAll();

// Edit notice?
$editNotice = null;
if (isset($_GET['edit'])) {
    $eSt = $db->prepare('SELECT * FROM notices WHERE id = ? AND author_id = ?');
    $eSt->execute([(int)$_GET['edit'], $user['id']]);
    $editNotice = $eSt->fetch();
}

$audiences = ['students','teachers','finance','admin'];
$cats      = ['General','Academic','Exam','Holiday','Finance','Emergency'];
$prios     = ['Normal','Important','Urgent'];

pageHead('Notices — Student Affairs', 'student_affairs');
$links = getStudentAffairsLinks();
?>
<div class="portal-wrap">
<?php sidebar('student_affairs', 'notices', $links, $user); ?>
<div class="main-area">
<?php topbar('Notices', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Create / Edit form -->
<div class="sec-card mb-3">
  <div class="sec-card-header">
    <i class="fas fa-<?= $editNotice ? 'edit' : 'plus' ?> me-2"></i>
    <?= $editNotice ? 'Edit Notice' : 'Post New Notice' ?>
    <small class="text-muted ms-2" style="font-size:.76rem;font-weight:400">
      — notices are attributed to you (Student Affairs)
    </small>
  </div>
  <div style="padding:16px">
    <form method="POST">
      <input type="hidden" name="action" value="<?= $editNotice ? 'update' : 'create' ?>">
      <?php if ($editNotice): ?><input type="hidden" name="id" value="<?= $editNotice['id'] ?>"><?php endif; ?>
      <div class="row g-2 mb-2">
        <div class="col-md-6">
          <label class="form-label fw-semibold" style="font-size:.82rem">Title <span class="text-danger">*</span></label>
          <input type="text" name="title" class="form-control form-control-sm" required
                 value="<?= h($editNotice['title'] ?? '') ?>" placeholder="Notice title…">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Category</label>
          <select name="category" class="form-select form-select-sm">
            <?php foreach ($cats as $c): ?>
            <option <?= ($editNotice['category'] ?? 'General') === $c ? 'selected' : '' ?>><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Priority</label>
          <select name="priority" class="form-select form-select-sm">
            <?php foreach ($prios as $p): ?>
            <option <?= ($editNotice['priority'] ?? 'Normal') === $p ? 'selected' : '' ?>><?= $p ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Expiry Date</label>
          <input type="date" name="expiry_date" class="form-control form-control-sm"
                 value="<?= h($editNotice['expiry_date'] ?? '') ?>">
        </div>
      </div>
      <div class="mb-2">
        <label class="form-label fw-semibold" style="font-size:.82rem">Body <span class="text-danger">*</span></label>
        <textarea name="body" class="form-control" rows="3" required
                  placeholder="Write notice content here…"><?= h($editNotice['body'] ?? '') ?></textarea>
      </div>
      <div class="row g-2 mb-2 align-items-center">
        <div class="col-auto">
          <span class="fw-semibold" style="font-size:.82rem">Audience:</span>
        </div>
        <?php
          $existingAud = $editNotice ? explode(',', $editNotice['audience'] ?? 'students') : ['students'];
          foreach ($audiences as $a): ?>
          <div class="col-auto form-check ms-2 mb-0">
            <input type="checkbox" name="audience[]" value="<?= $a ?>" id="aud_<?= $a ?>" class="form-check-input"
                   <?= in_array($a, $existingAud) ? 'checked' : '' ?>>
            <label class="form-check-label" for="aud_<?= $a ?>" style="font-size:.82rem"><?= ucfirst($a) ?></label>
          </div>
        <?php endforeach; ?>
        <div class="col-auto form-check ms-3 mb-0">
          <input type="checkbox" name="pinned" id="pinned" class="form-check-input"
                 <?= ($editNotice['pinned'] ?? 0) ? 'checked' : '' ?>>
          <label class="form-check-label" for="pinned" style="font-size:.82rem">
            <i class="fas fa-thumbtack text-warning"></i> Pin
          </label>
        </div>
      </div>
      <button type="submit" class="btn btn-sm btn-success">
        <i class="fas fa-save me-1"></i><?= $editNotice ? 'Update' : 'Post' ?> Notice
      </button>
      <?php if ($editNotice): ?>
      <a href="<?= url('/portal/student-affairs/notices.php') ?>" class="btn btn-sm btn-outline-secondary ms-2">Cancel</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- My notices list -->
<div class="sec-card">
  <div class="sec-card-header">
    <i class="fas fa-list me-2"></i>My Notices (<?= count($notices) ?>)
    <small class="text-muted ms-2" style="font-size:.76rem;font-weight:400">— only notices you posted</small>
  </div>
  <?php if (empty($notices)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2);font-size:.85rem">
    <i class="fas fa-bell fa-2x mb-2 d-block opacity-25"></i>
    No notices posted yet. Use the form above to post your first notice.
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr><th>Title</th><th>Cat</th><th>Priority</th><th>Audience</th><th>Expiry</th><th>Pin</th><th>Posted</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($notices as $n):
          $pc = match($n['priority']) {'Urgent'=>'danger','Important'=>'warning',default=>'secondary'};
        ?>
        <tr>
          <td class="fw-semibold">
            <?= $n['pinned'] ? '<i class="fas fa-thumbtack text-warning me-1"></i>' : '' ?>
            <?= h($n['title']) ?>
          </td>
          <td><?= h($n['category']) ?></td>
          <td><span class="badge bg-<?= $pc ?>"><?= $n['priority'] ?></span></td>
          <td style="font-size:.78rem"><?= h($n['audience']) ?></td>
          <td><?= $n['expiry_date'] ? fDate($n['expiry_date']) : '—' ?></td>
          <td><?= $n['pinned'] ? '<i class="fas fa-thumbtack text-warning"></i>' : '—' ?></td>
          <td style="font-size:.78rem"><?= fDate($n['created_at']) ?></td>
          <td>
            <a href="?edit=<?= $n['id'] ?>" class="btn btn-xs btn-outline-primary me-1" style="font-size:.74rem;padding:2px 7px">Edit</a>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="toggle_pin">
              <input type="hidden" name="id" value="<?= $n['id'] ?>">
              <button class="btn btn-xs btn-outline-warning me-1" style="font-size:.74rem;padding:2px 7px">
                <i class="fas fa-thumbtack"></i>
              </button>
            </form>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this notice?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $n['id'] ?>">
              <button class="btn btn-xs btn-outline-danger" style="font-size:.74rem;padding:2px 7px">Del</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

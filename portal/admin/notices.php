<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        $db->prepare('DELETE FROM notices WHERE id = ?')->execute([$id]);
        logActivity($user['id'], 'notice_delete', "Deleted notice #$id");
        setFlash('success','Notice deleted.');
        redirect('/admin/notices.php');
    }

    if ($action === 'toggle_pin' && $id) {
        $db->prepare('UPDATE notices SET pinned = !pinned WHERE id = ?')->execute([$id]);
        redirect('/admin/notices.php');
    }

    $title      = trim($_POST['title']   ?? '');
    $body       = trim($_POST['body']    ?? '');
    $category   = $_POST['category']    ?? 'General';
    $priority   = $_POST['priority']    ?? 'Normal';
    $audience   = $_POST['audience']    ?? [];
    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $pinned     = isset($_POST['pinned']) ? 1 : 0;

    $validCats  = ['General','Academic','Exam','Holiday','Finance','Emergency'];
    $validPrios = ['Normal','Important','Urgent'];
    if (!in_array($category, $validCats))  $category = 'General';
    if (!in_array($priority, $validPrios)) $priority = 'Normal';

    $audValid   = ['students','teachers','finance','admin'];
    $audFiltered = array_filter((array)$audience, fn($a) => in_array($a, $audValid));
    $audStr      = implode(',', $audFiltered);

    if (!$title || !$body) {
        setFlash('danger','Title and body are required.');
        redirect('/admin/notices.php');
    }

    if ($action === 'update' && $id) {
        $db->prepare('UPDATE notices SET title=?,body=?,category=?,priority=?,audience=?,expiry_date=?,pinned=? WHERE id=?')
           ->execute([$title, $body, $category, $priority, $audStr, $expiryDate, $pinned, $id]);
        logActivity($user['id'], 'notice_update', "Updated notice: $title");
        setFlash('success','Notice updated.');
    } else {
        $db->prepare('INSERT INTO notices (title,body,category,priority,audience,expiry_date,pinned,author_id) VALUES (?,?,?,?,?,?,?,?)')
           ->execute([$title, $body, $category, $priority, $audStr, $expiryDate, $pinned, $user['id']]);
        logActivity($user['id'], 'notice_create', "Created notice: $title");
        setFlash('success','Notice posted.');
    }
    redirect('/admin/notices.php');
}

// Fetch all notices
$notices = $db->query(
    'SELECT n.*, u.name AS author_name FROM notices n LEFT JOIN users u ON n.author_id = u.id ORDER BY pinned DESC, created_at DESC'
)->fetchAll();

// Edit notice?
$editNotice = null;
if (isset($_GET['edit'])) {
    $eSt = $db->prepare('SELECT * FROM notices WHERE id = ?');
    $eSt->execute([(int)$_GET['edit']]);
    $editNotice = $eSt->fetch();
}

pageHead('Notices', 'admin');
$links = getAdminLinks();
$audiences = ['students','teachers','finance','admin'];
$cats      = ['General','Academic','Exam','Holiday','Finance','Emergency'];
$prios     = ['Normal','Important','Urgent'];
?>
<div class="portal-wrap">
<?php sidebar('admin', 'notices', $links, $user); ?>
<div class="main-area">
<?php topbar('Notice Management', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Create/Edit form -->
<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-<?= $editNotice ? 'edit' : 'plus' ?> me-2"></i><?= $editNotice ? 'Edit Notice' : 'Post New Notice' ?></div>
  <div style="padding:16px">
    <form method="POST">
      <input type="hidden" name="action" value="<?= $editNotice ? 'update' : 'create' ?>">
      <?php if ($editNotice): ?><input type="hidden" name="id" value="<?= $editNotice['id'] ?>"><?php endif; ?>
      <div class="row g-2 mb-2">
        <div class="col-md-6">
          <label class="form-label fw-semibold" style="font-size:.82rem">Title*</label>
          <input type="text" name="title" class="form-control form-control-sm" required value="<?= h($editNotice['title'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Category</label>
          <select name="category" class="form-select form-select-sm">
            <?php foreach ($cats as $c): ?><option <?= ($editNotice['category']??'General')===$c?'selected':'' ?>><?= $c ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Priority</label>
          <select name="priority" class="form-select form-select-sm">
            <?php foreach ($prios as $p): ?><option <?= ($editNotice['priority']??'Normal')===$p?'selected':'' ?>><?= $p ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Expiry Date</label>
          <input type="date" name="expiry_date" class="form-control form-control-sm" value="<?= h($editNotice['expiry_date'] ?? '') ?>">
        </div>
      </div>
      <div class="mb-2">
        <label class="form-label fw-semibold" style="font-size:.82rem">Body*</label>
        <textarea name="body" class="form-control" rows="3" required><?= h($editNotice['body'] ?? '') ?></textarea>
      </div>
      <div class="row g-2 mb-2 align-items-center">
        <div class="col-auto">
          <label class="form-label fw-semibold mb-0" style="font-size:.82rem">Audience:</label>
        </div>
        <?php
        $existingAud = $editNotice ? explode(',', $editNotice['audience'] ?? '') : $audiences;
        foreach ($audiences as $a): ?>
          <div class="col-auto form-check ms-2 mb-0">
            <input type="checkbox" name="audience[]" value="<?= $a ?>" id="aud_<?= $a ?>" class="form-check-input"
                   <?= in_array($a, $existingAud) ? 'checked' : '' ?>>
            <label class="form-check-label" for="aud_<?= $a ?>" style="font-size:.82rem"><?= ucfirst($a) ?></label>
          </div>
        <?php endforeach; ?>
        <div class="col-auto form-check ms-3 mb-0">
          <input type="checkbox" name="pinned" id="pinned" class="form-check-input" <?= ($editNotice['pinned'] ?? 0) ? 'checked' : '' ?>>
          <label class="form-check-label" for="pinned" style="font-size:.82rem"><i class="fas fa-thumbtack text-warning"></i> Pin</label>
        </div>
      </div>
      <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-save me-1"></i><?= $editNotice ? 'Update' : 'Post' ?> Notice</button>
      <?php if ($editNotice): ?><a href="<?= url('/admin/notices.php') ?>" class="btn btn-sm btn-outline-secondary ms-2">Cancel</a><?php endif; ?>
    </form>
  </div>
</div>

<!-- Notices list -->
<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-list me-2"></i>All Notices (<?= count($notices) ?>)</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light"><tr><th>Title</th><th>Cat</th><th>Priority</th><th>Audience</th><th>Expiry</th><th>Pin</th><th>Posted</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($notices as $n):
          $pc = match($n['priority']) {'Urgent'=>'danger','Important'=>'warning',default=>'secondary'};
        ?>
        <tr>
          <td class="fw-semibold"><?= h($n['title']) ?></td>
          <td><?= h($n['category']) ?></td>
          <td><span class="badge bg-<?= $pc ?>"><?= $n['priority'] ?></span></td>
          <td style="font-size:.78rem"><?= h($n['audience']) ?></td>
          <td><?= $n['expiry_date'] ? fDate($n['expiry_date']) : '—' ?></td>
          <td><?= $n['pinned'] ? '<i class="fas fa-thumbtack text-warning"></i>' : '—' ?></td>
          <td style="font-size:.78rem"><?= fDate($n['created_at']) ?></td>
          <td>
            <a href="?edit=<?= $n['id'] ?>" class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 7px">Edit</a>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="toggle_pin">
              <input type="hidden" name="id" value="<?= $n['id'] ?>">
              <button class="btn btn-xs btn-outline-warning" style="font-size:.74rem;padding:2px 7px">Pin</button>
            </form>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete notice?')">
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
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

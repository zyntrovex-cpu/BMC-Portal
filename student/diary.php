<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user    = requireAuth('student');
$db      = getDB();
$student = getStudentByUserId($user['id']);
if (!$student) { setFlash('danger','Student record not found.'); redirect('/index.php'); }

// Check table exists
$tableExists = false;
try { $db->query('SELECT 1 FROM daily_diary LIMIT 0'); $tableExists = true; } catch (Exception $e) {}

$filterDate = $_GET['date'] ?? '';
$entries    = [];
$imgsByEntry = [];

if ($tableExists && $student['class_id']) {
    $where  = 'dd.class_id = ?';
    $params = [$student['class_id']];
    if ($filterDate) { $where .= ' AND dd.date = ?'; $params[] = $filterDate; }

    $st = $db->prepare(
        "SELECT dd.*, u.name AS teacher_name
         FROM daily_diary dd
         JOIN teachers t ON t.id = dd.teacher_id
         JOIN users u    ON u.id = t.user_id
         WHERE $where
         ORDER BY dd.date DESC, dd.created_at DESC
         LIMIT 60"
    );
    $st->execute($params);
    $entries = $st->fetchAll();

    if ($entries) {
        $ids = implode(',', array_map(fn($e) => (int)$e['id'], $entries));
        $imgSt = $db->query("SELECT * FROM diary_media WHERE diary_id IN ($ids)");
        $allImgs = $imgSt->fetchAll();
        foreach ($allImgs as $img) { $imgsByEntry[$img['diary_id']][] = $img; }
    }
}

pageHead('Class Diary', 'student');
$links = getStudentLinks();
?>
<div class="portal-wrap">
<?php sidebar('student', 'diary', $links, $user); ?>
<div class="main-area">
<?php topbar('Class Diary', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$tableExists): ?>
<div class="alert alert-info">The diary feature is being set up. Check back soon.</div>
<?php elseif (!$student['class_id']): ?>
<div class="alert alert-warning">You are not assigned to a class yet.</div>
<?php else: ?>

<!-- Filter -->
<form method="GET" class="d-flex gap-2 mb-3 flex-wrap">
  <input type="date" name="date" class="form-control form-control-sm" style="max-width:170px" value="<?= h($filterDate) ?>">
  <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search me-1"></i>Filter</button>
  <?php if ($filterDate): ?><a href="<?= url('/student/diary.php') ?>" class="btn btn-sm btn-outline-danger">Clear</a><?php endif; ?>
</form>

<?php if (empty($entries)): ?>
<div class="sec-card p-4 text-center text-muted">No diary entries found.</div>
<?php else: ?>
<?php foreach ($entries as $e):
  $imgs = $imgsByEntry[$e['id']] ?? [];
?>
<div class="sec-card mb-3">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <strong><i class="fas fa-book-open me-2"></i><?= h($e['title']) ?></strong>
    <div class="d-flex gap-2 align-items-center">
      <small class="text-muted"><i class="fas fa-user me-1"></i><?= h($e['teacher_name']) ?></small>
      <span class="badge bg-secondary"><?= fDate($e['date']) ?></span>
    </div>
  </div>
  <div style="padding:14px 16px">
    <p style="font-size:.9rem;white-space:pre-line;margin-bottom:10px"><?= h($e['content']) ?></p>
    <?php if ($e['homework']): ?>
    <div class="p-2 mb-2" style="background:#fef3c7;border-radius:6px;border-left:3px solid #d97706;font-size:.86rem">
      <strong><i class="fas fa-tasks me-1 text-warning"></i>Homework / Assignment:</strong>
      <div class="mt-1" style="white-space:pre-line"><?= h($e['homework']) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($imgs): ?>
    <div class="d-flex flex-wrap gap-2 mt-2">
      <?php foreach ($imgs as $img): ?>
      <a href="<?= url('/assets/uploads/diary/' . h($img['filename'])) ?>" target="_blank">
        <img src="<?= url('/assets/uploads/diary/' . h($img['filename'])) ?>"
             style="height:90px;width:90px;object-fit:cover;border-radius:6px;border:1px solid var(--border)"
             alt="diary image" loading="lazy">
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php endif; ?>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

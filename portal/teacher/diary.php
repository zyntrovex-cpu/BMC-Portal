<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('teacher');
requirePermission('diary');
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);
if (!$teacher) { setFlash('danger','Teacher record not found.'); redirect('/portal/index.php'); }

// Check tables exist
$tableExists = false;
try { $db->query('SELECT 1 FROM daily_diary LIMIT 0'); $tableExists = true; } catch (Exception $e) {}

// Upload directory
$uploadDir = __DIR__ . '/../assets/uploads/diary/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// ── POST Handler ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_entry') {
        $classId  = (int)($_POST['class_id']    ?? 0);
        $date     = $_POST['date']              ?? date('Y-m-d');
        $title    = trim($_POST['title']        ?? '');
        $content  = trim($_POST['content']      ?? '');
        $homework = trim($_POST['homework']     ?? '');

        if (!$classId || !$title || !$content) {
            setFlash('danger', 'Class, title, and content are required.');
        } else {
            $db->prepare(
                'INSERT INTO daily_diary (teacher_id, class_id, date, title, content, homework)
                 VALUES (?,?,?,?,?,?)'
            )->execute([$teacher['id'], $classId, $date, $title, $content, $homework ?: null]);
            $diaryId = (int)$db->lastInsertId();

            // Handle image uploads
            if (!empty($_FILES['images']['name'][0])) {
                $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
                foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
                    if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    if (!in_array($_FILES['images']['type'][$i], $allowed)) continue;
                    if ($_FILES['images']['size'][$i] > 5 * 1024 * 1024) continue; // 5 MB max
                    $ext      = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                    $filename = 'diary_' . $diaryId . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                    if (move_uploaded_file($tmp, $uploadDir . $filename)) {
                        $db->prepare('INSERT INTO diary_media (diary_id, filename) VALUES (?,?)')
                           ->execute([$diaryId, $filename]);
                    }
                }
            }

            logActivity($user['id'], 'diary_add', "Added diary entry for class #$classId on $date");
            setFlash('success', 'Diary entry posted.');
        }
    }

    if ($action === 'delete_entry') {
        $id = (int)($_POST['diary_id'] ?? 0);
        if ($id) {
            // Delete associated images from disk
            $imgs = $db->prepare('SELECT filename FROM diary_media WHERE diary_id=?');
            $imgs->execute([$id]);
            foreach ($imgs->fetchAll() as $img) {
                @unlink($uploadDir . $img['filename']);
            }
            $db->prepare('DELETE FROM diary_media WHERE diary_id=?')->execute([$id]);
            $db->prepare('DELETE FROM daily_diary WHERE id=? AND teacher_id=?')->execute([$id, $teacher['id']]);
            setFlash('success', 'Entry deleted.');
        }
    }

    redirect('/portal/teacher/diary.php');
}

// Classes assigned to this teacher
$assignedClasses = $db->prepare(
    'SELECT DISTINCT c.id, c.name FROM class_subjects cs JOIN classes c ON cs.class_id=c.id
     WHERE cs.teacher_id=? ORDER BY c.grade, c.section'
);
$assignedClasses->execute([$teacher['id']]);
$classes = $assignedClasses->fetchAll();

$filterClass = (int)($_GET['class_id'] ?? 0);
$filterDate  = $_GET['date']           ?? '';

// Fetch diary entries
$entries = [];
if ($tableExists) {
    $where  = 'dd.teacher_id = ?';
    $params = [$teacher['id']];
    if ($filterClass) { $where .= ' AND dd.class_id = ?'; $params[] = $filterClass; }
    if ($filterDate)  { $where .= ' AND dd.date = ?';     $params[] = $filterDate; }

    $st = $db->prepare(
        "SELECT dd.*, c.name AS class_name
         FROM daily_diary dd
         JOIN classes c ON c.id = dd.class_id
         WHERE $where
         ORDER BY dd.date DESC, dd.created_at DESC
         LIMIT 60"
    );
    $st->execute($params);
    $entries = $st->fetchAll();

    // Fetch images for these entries
    if ($entries) {
        $ids = implode(',', array_map(fn($e) => (int)$e['id'], $entries));
        $imgSt = $db->query("SELECT * FROM diary_media WHERE diary_id IN ($ids)");
        $allImgs = $imgSt->fetchAll();
        $imgsByEntry = [];
        foreach ($allImgs as $img) { $imgsByEntry[$img['diary_id']][] = $img; }
    } else {
        $imgsByEntry = [];
    }
}

pageHead('Daily Diary', 'teacher');
$links = getTeacherLinks();
?>
<div class="portal-wrap">
<?php sidebar('teacher', 'diary', $links, $user); ?>
<div class="main-area">
<?php topbar('Daily Diary', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  The daily diary tables are not set up yet. Ask admin to run the features migration SQL.
</div>
<?php else: ?>

<div class="row g-3">
  <!-- Add entry form -->
  <div class="col-lg-4">
    <div class="sec-card" style="position:sticky;top:70px">
      <div class="sec-card-header"><i class="fas fa-pen me-2"></i>Add Diary Entry</div>
      <div style="padding:16px">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add_entry">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Class <span class="text-danger">*</span></label>
            <select name="class_id" class="form-select form-select-sm" required>
              <option value="">Select class…</option>
              <?php foreach ($classes as $c): ?>
              <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Date</label>
            <input type="date" name="date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control form-control-sm" required placeholder="e.g. Today's Lesson Summary">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Content <span class="text-danger">*</span></label>
            <textarea name="content" class="form-control form-control-sm" rows="5" required
                      placeholder="Topics covered, activities, notes…"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Homework / Assignment</label>
            <textarea name="homework" class="form-control form-control-sm" rows="2"
                      placeholder="Homework or tasks for students (optional)…"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">
              Images <small class="text-muted">(optional, max 5 × 5MB)</small>
            </label>
            <input type="file" name="images[]" class="form-control form-control-sm" multiple accept="image/*">
          </div>
          <button type="submit" class="btn btn-sm btn-success w-100">
            <i class="fas fa-paper-plane me-1"></i>Post Entry
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Entries list -->
  <div class="col-lg-8">
    <!-- Filters -->
    <form method="GET" class="d-flex gap-2 mb-3 flex-wrap">
      <select name="class_id" class="form-select form-select-sm" style="max-width:160px" onchange="this.form.submit()">
        <option value="">All classes</option>
        <?php foreach ($classes as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $filterClass==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="date" class="form-control form-control-sm" style="max-width:160px" value="<?= h($filterDate) ?>">
      <button class="btn btn-sm btn-outline-secondary">Filter</button>
      <?php if ($filterClass || $filterDate): ?><a href="<?= url('/portal/teacher/diary.php') ?>" class="btn btn-sm btn-outline-danger">Clear</a><?php endif; ?>
    </form>

    <?php if (empty($entries)): ?>
    <div class="sec-card p-4 text-center text-muted">No diary entries yet.</div>
    <?php else: ?>
    <?php foreach ($entries as $e):
      $imgs = $imgsByEntry[$e['id']] ?? [];
    ?>
    <div class="sec-card mb-3">
      <div class="sec-card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <span class="badge bg-secondary me-1"><?= h($e['class_name']) ?></span>
          <strong><?= h($e['title']) ?></strong>
        </div>
        <div class="d-flex gap-1 align-items-center">
          <small class="text-muted"><?= fDate($e['date']) ?></small>
          <form method="POST" class="d-inline ms-2" onsubmit="return confirm('Delete this entry?')">
            <input type="hidden" name="action" value="delete_entry">
            <input type="hidden" name="diary_id" value="<?= $e['id'] ?>">
            <button class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:1px 6px"><i class="fas fa-trash"></i></button>
          </form>
        </div>
      </div>
      <div style="padding:14px 16px">
        <p style="font-size:.9rem;white-space:pre-line;margin-bottom:8px"><?= h($e['content']) ?></p>
        <?php if ($e['homework']): ?>
        <div class="p-2 mb-2" style="background:#fef3c7;border-radius:6px;border-left:3px solid #d97706;font-size:.85rem">
          <strong><i class="fas fa-tasks me-1 text-warning"></i>Homework:</strong>
          <?= h($e['homework']) ?>
        </div>
        <?php endif; ?>
        <?php if ($imgs): ?>
        <div class="d-flex flex-wrap gap-2 mt-2">
          <?php foreach ($imgs as $img): ?>
          <a href="<?= url('/assets/uploads/diary/' . h($img['filename'])) ?>" target="_blank">
            <img src="<?= url('/assets/uploads/diary/' . h($img['filename'])) ?>"
                 style="height:80px;width:80px;object-fit:cover;border-radius:6px;border:1px solid var(--border)"
                 alt="diary image">
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

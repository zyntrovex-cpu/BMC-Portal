<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

$classes = getAllClasses();

$preview    = [];
$sourceId   = 0;
$targetId   = 0;
$sourceName = '';
$targetName = '';
$promoted   = 0;
$showPreview = false;

// ── POST Handler ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sourceId = (int)($_POST['source_class'] ?? 0);
    $targetId = (int)($_POST['target_class'] ?? 0);
    $confirm  = (int)($_POST['confirm'] ?? 0);

    if (!$sourceId || !$targetId) {
        setFlash('danger', 'Please select both source and target classes.');
        redirect('/admin/promote.php');
    }
    if ($sourceId === $targetId) {
        setFlash('danger', 'Source and target classes must be different.');
        redirect('/admin/promote.php');
    }

    // Get class names
    $stClass = $db->prepare('SELECT id, name FROM classes WHERE id = ?');
    $stClass->execute([$sourceId]);
    $srcClass = $stClass->fetch();
    $stClass->execute([$targetId]);
    $tgtClass = $stClass->fetch();

    if (!$srcClass || !$tgtClass) {
        setFlash('danger', 'Invalid class selection.');
        redirect('/admin/promote.php');
    }
    $sourceName = $srcClass['name'];
    $targetName = $tgtClass['name'];

    if ($confirm === 1) {
        // Do the actual promotion
        $countSt = $db->prepare('SELECT COUNT(*) FROM students WHERE class_id = ?');
        $countSt->execute([$sourceId]);
        $count = (int)$countSt->fetchColumn();

        $db->prepare('UPDATE students SET class_id = ? WHERE class_id = ?')
           ->execute([$targetId, $sourceId]);

        logActivity($user['id'], 'class_promote',
            "Promoted $count student(s) from $sourceName to $targetName");
        $promoted = $count;
        setFlash('success', "$count student(s) successfully promoted from $sourceName to $targetName.");
        redirect('/admin/promote.php');
    }

    // Preview mode — fetch students in source class
    $stPrev = $db->prepare(
        'SELECT s.roll_no, u.name, c.name AS class_name
         FROM students s
         JOIN users u ON s.user_id = u.id
         JOIN classes c ON s.class_id = c.id
         WHERE s.class_id = ?
         ORDER BY s.roll_no'
    );
    $stPrev->execute([$sourceId]);
    $preview = $stPrev->fetchAll();
    $showPreview = true;
}

pageHead('Class Promotion', 'admin');
$links = getAdminLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('admin', 'promote', $links, $user); ?>
<div class="main-area">
<?php topbar('Class Promotion', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$showPreview): ?>
<!-- Step 1: Select Classes -->
<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="sec-card">
      <div class="sec-card-header">
        <i class="fas fa-level-up-alt me-2"></i>Class Promotion Tool
      </div>
      <div style="padding:20px">
        <p class="text-muted mb-3" style="font-size:.88rem">
          Use this tool at the end of a session to move all students from one class to the next.
          All students in the source class will be moved to the target class.
        </p>
        <form method="POST">
          <div class="mb-3">
            <label class="form-label fw-semibold">Source Class <span class="text-danger">*</span></label>
            <select name="source_class" class="form-select form-select-sm" required>
              <option value="">— Select source class —</option>
              <?php foreach ($classes as $c): ?>
              <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Students will be moved FROM this class.</div>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold">Target Class <span class="text-danger">*</span></label>
            <select name="target_class" class="form-select form-select-sm" required>
              <option value="">— Select target class —</option>
              <?php foreach ($classes as $c): ?>
              <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Students will be moved TO this class.</div>
          </div>
          <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-eye me-1"></i>Preview Students
          </button>
        </form>
      </div>
    </div>

    <div class="alert alert-warning" role="alert" style="font-size:.84rem">
      <i class="fas fa-exclamation-triangle me-1"></i>
      <strong>Important:</strong> This action will move ALL students from the source class to the target class.
      This cannot be undone automatically. Make sure to take a database backup before proceeding.
    </div>
  </div>
</div>

<?php else: ?>
<!-- Step 2: Preview & Confirm -->
<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="sec-card">
      <div class="sec-card-header">
        <span><i class="fas fa-users me-2"></i>Preview — Students to be Promoted</span>
        <span class="badge bg-primary"><?= count($preview) ?> students</span>
      </div>
      <div style="padding:12px 16px 6px">
        <div class="alert alert-info mb-3" style="font-size:.84rem">
          <i class="fas fa-info-circle me-1"></i>
          <strong><?= count($preview) ?> student(s)</strong> will be moved from
          <strong><?= h($sourceName) ?></strong> &rarr; <strong><?= h($targetName) ?></strong>.
          Review the list below before confirming.
        </div>
      </div>
      <?php if (empty($preview)): ?>
      <div style="padding:24px;text-align:center;color:var(--t2)">
        <i class="fas fa-users-slash fa-2x mb-2"></i>
        <p class="mb-0">No students found in <strong><?= h($sourceName) ?></strong>.</p>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Student Name</th>
              <th>Roll No</th>
              <th>Current Class</th>
              <th>Will Move To</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($preview as $i => $s): ?>
            <tr>
              <td class="text-muted"><?= $i + 1 ?></td>
              <td><strong><?= h($s['name']) ?></strong></td>
              <td><code><?= h($s['roll_no']) ?></code></td>
              <td><span class="badge bg-secondary"><?= h($s['class_name']) ?></span></td>
              <td><span class="badge bg-success"><?= h($targetName) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
      <div style="padding:16px;border-top:1px solid var(--border);background:#f7f9fb">
        <div class="d-flex gap-2 justify-content-between">
          <a href="<?= url('/admin/promote.php') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Go Back
          </a>
          <form method="POST">
            <input type="hidden" name="source_class" value="<?= $sourceId ?>">
            <input type="hidden" name="target_class" value="<?= $targetId ?>">
            <input type="hidden" name="confirm" value="1">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Confirm: Move <?= count($preview) ?> student(s) from <?= h($sourceName) ?> to <?= h($targetName) ?>?')">
              <i class="fas fa-level-up-alt me-1"></i>Confirm Promotion (<?= count($preview) ?> students)
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

</div><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /portal-wrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

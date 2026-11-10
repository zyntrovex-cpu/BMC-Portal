<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('admin');
$db      = getDB();
$classes = getAllClasses();

// Class lookup map
$classMap = [];
foreach ($classes as $c) $classMap[(int)$c['id']] = $c;

// ── Helper: write a promotion history row ────────────────────────────
function recordPromoHistory(PDO $db, int $studentId, string $studentName, string $rollNo,
    ?int $fromId, ?string $fromName, ?int $toId, ?string $toName,
    string $action, int $byId, string $byName, string $notes = ''): void
{
    try {
        $db->prepare(
            "INSERT INTO student_promotion_history
             (student_id, student_name, roll_no,
              from_class_id, from_class_name, to_class_id, to_class_name,
              action, promoted_by, promoted_by_name, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([$studentId, $studentName, $rollNo,
                    $fromId, $fromName, $toId, $toName,
                    $action, $byId, $byName, $notes]);
    } catch (Exception $e) {}
}

// ── POST: all write actions ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Promote selected students ─────────────────────────────────────
    if ($action === 'promote') {
        $sourceId   = (int)($_POST['source_class'] ?? 0);
        $targetId   = (int)($_POST['target_class'] ?? 0);
        $rawIds     = (array)($_POST['student_ids'] ?? []);
        $studentIds = array_values(array_filter(array_map('intval', $rawIds)));

        if (!$sourceId || !$targetId) {
            setFlash('danger', 'Source and target class are required.');
            redirect('/portal/admin/promote.php');
        }
        if ($sourceId === $targetId) {
            setFlash('danger', 'Source and target class must be different.');
            redirect('/portal/admin/promote.php');
        }
        if (empty($studentIds)) {
            setFlash('warning', 'No students selected. Please tick at least one student.');
            redirect('/portal/admin/promote.php');
        }

        $src = $classMap[$sourceId] ?? null;
        $tgt = $classMap[$targetId] ?? null;
        if (!$src || !$tgt) {
            setFlash('danger', 'Invalid class selection.');
            redirect('/portal/admin/promote.php');
        }

        // Only promote students actually in the source class (server-side guard)
        $ph  = implode(',', array_fill(0, count($studentIds), '?'));
        $sst = $db->prepare(
            "SELECT s.id, s.roll_no, u.name
             FROM students s JOIN users u ON s.user_id = u.id
             WHERE s.class_id = ?
               AND s.id IN ($ph)
               AND (s.deleted_at IS NULL)
               AND (s.graduated_at IS NULL)"
        );
        $sst->execute(array_merge([$sourceId], $studentIds));
        $toPromote = $sst->fetchAll();

        if (empty($toPromote)) {
            setFlash('warning', 'No eligible students found to promote.');
            redirect('/portal/admin/promote.php');
        }

        $db->beginTransaction();
        try {
            foreach ($toPromote as $s) {
                $db->prepare('UPDATE students SET class_id = ? WHERE id = ?')
                   ->execute([$targetId, $s['id']]);
                recordPromoHistory($db, $s['id'], $s['name'], $s['roll_no'],
                    $sourceId, $src['name'], $targetId, $tgt['name'],
                    'promoted', $user['id'], $user['name']);
            }
            $db->commit();
            $n = count($toPromote);
            logActivity($user['id'], 'class_promote',
                "Promoted $n student(s) from {$src['name']} to {$tgt['name']}");
            setFlash('success', "$n student(s) promoted from <strong>{$src['name']}</strong> to <strong>{$tgt['name']}</strong>.");
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('danger', 'Promotion failed. Please try again.');
        }
        redirect('/portal/admin/promote.php?tab=history');
    }

    // ── Graduate selected students ────────────────────────────────────
    if ($action === 'graduate') {
        $sourceId   = (int)($_POST['source_class'] ?? 0);
        $rawIds     = (array)($_POST['student_ids'] ?? []);
        $studentIds = array_values(array_filter(array_map('intval', $rawIds)));
        $gradYear   = trim($_POST['graduation_year'] ?? date('Y'));

        if (!$sourceId || empty($studentIds)) {
            setFlash('danger', 'Please select a class and at least one student.');
            redirect('/portal/admin/promote.php');
        }

        $src = $classMap[$sourceId] ?? null;
        if (!$src) {
            setFlash('danger', 'Invalid class.');
            redirect('/portal/admin/promote.php');
        }

        $ph  = implode(',', array_fill(0, count($studentIds), '?'));
        $sst = $db->prepare(
            "SELECT s.id, s.roll_no, u.id AS uid, u.name
             FROM students s JOIN users u ON s.user_id = u.id
             WHERE s.class_id = ?
               AND s.id IN ($ph)
               AND (s.deleted_at IS NULL)
               AND (s.graduated_at IS NULL)"
        );
        $sst->execute(array_merge([$sourceId], $studentIds));
        $toGrad = $sst->fetchAll();

        if (empty($toGrad)) {
            setFlash('warning', 'No eligible students found to graduate.');
            redirect('/portal/admin/promote.php');
        }

        $db->beginTransaction();
        try {
            foreach ($toGrad as $s) {
                $db->prepare(
                    'UPDATE students SET graduated_at = NOW(), graduation_year = ?, class_id = NULL WHERE id = ?'
                )->execute([$gradYear, $s['id']]);
                $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")
                   ->execute([$s['uid']]);
                recordPromoHistory($db, $s['id'], $s['name'], $s['roll_no'],
                    $sourceId, $src['name'], null, null,
                    'graduated', $user['id'], $user['name'], "Batch $gradYear");
            }
            $db->commit();
            $n = count($toGrad);
            logActivity($user['id'], 'graduation',
                "Graduated $n student(s) from {$src['name']}, Batch $gradYear");
            setFlash('success', "$n student(s) successfully marked as <strong>Graduated — Batch $gradYear</strong>.");
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('danger', 'Graduation failed: ' . $e->getMessage());
        }
        redirect('/portal/admin/promote.php?tab=alumni');
    }

    // ── Demote: reverse a promotion using history record ──────────────
    if ($action === 'demote') {
        $histId = (int)($_POST['history_id'] ?? 0);
        if (!$histId) {
            setFlash('danger', 'Invalid history record.');
            redirect('/portal/admin/promote.php?tab=history');
        }

        $hst = $db->prepare(
            "SELECT h.*, s.class_id AS cur_class
             FROM student_promotion_history h
             JOIN students s ON s.id = h.student_id
             WHERE h.id = ? AND h.action = 'promoted'"
        );
        $hst->execute([$histId]);
        $h = $hst->fetch();

        if (!$h) {
            setFlash('danger', 'Promotion record not found.');
            redirect('/portal/admin/promote.php?tab=history');
        }
        if ((int)$h['cur_class'] !== (int)$h['to_class_id']) {
            setFlash('warning',
                "Cannot auto-demote: {$h['student_name']}'s class has changed since this promotion. " .
                "Use the student profile to set their class manually.");
            redirect('/portal/admin/promote.php?tab=history');
        }

        $db->prepare('UPDATE students SET class_id = ? WHERE id = ?')
           ->execute([$h['from_class_id'], $h['student_id']]);
        recordPromoHistory($db, $h['student_id'], $h['student_name'], $h['roll_no'],
            $h['to_class_id'], $h['to_class_name'],
            $h['from_class_id'], $h['from_class_name'],
            'demoted', $user['id'], $user['name'], "Reversed promotion #$histId");
        logActivity($user['id'], 'class_demote',
            "Demoted {$h['student_name']} from {$h['to_class_name']} back to {$h['from_class_name']}");
        setFlash('success', "<strong>{$h['student_name']}</strong> demoted from {$h['to_class_name']} back to {$h['from_class_name']}.");
        redirect('/portal/admin/promote.php?tab=history');
    }

    // ── Ungraduate: restore a graduated student ───────────────────────
    if ($action === 'ungraduate') {
        $studentId  = (int)($_POST['student_id']       ?? 0);
        $restoreId  = (int)($_POST['restore_class_id'] ?? 0);

        if (!$studentId) {
            setFlash('danger', 'Invalid student.');
            redirect('/portal/admin/promote.php?tab=alumni');
        }

        $sst = $db->prepare(
            "SELECT s.id, s.roll_no, u.id AS uid, u.name
             FROM students s JOIN users u ON s.user_id = u.id
             WHERE s.id = ? AND s.graduated_at IS NOT NULL"
        );
        $sst->execute([$studentId]);
        $s = $sst->fetch();
        if (!$s) {
            setFlash('danger', 'Graduated student not found.');
            redirect('/portal/admin/promote.php?tab=alumni');
        }

        $db->prepare(
            'UPDATE students SET graduated_at = NULL, graduation_year = NULL, class_id = ? WHERE id = ?'
        )->execute([$restoreId ?: null, $studentId]);
        $db->prepare("UPDATE users SET status = 'active' WHERE id = ?")
           ->execute([$s['uid']]);

        $restoreName = $restoreId ? ($classMap[$restoreId]['name'] ?? 'Unknown') : 'No Class';
        recordPromoHistory($db, $studentId, $s['name'], $s['roll_no'],
            null, 'Alumni/Graduated', $restoreId ?: null, $restoreName,
            'ungraduated', $user['id'], $user['name']);
        logActivity($user['id'], 'ungraduation',
            "Reversed graduation for {$s['name']}, restored to $restoreName");
        setFlash('success', "<strong>{$s['name']}</strong> restored to active student status (Class: $restoreName).");
        redirect('/portal/admin/promote.php?tab=alumni');
    }

    // ── Load students for preview (step 2 — no redirect) ──────────────
    // Falls through to display section below
}

// ── GET / Display ─────────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'promote';

// Step-2 state (from POST preview action)
$showStep2   = false;
$step2Source = 0;
$step2Students = [];
$isGradMode  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'preview') {
    $step2Source = (int)($_POST['source_class'] ?? 0);
    $srcCls      = $classMap[$step2Source] ?? null;
    if ($srcCls) {
        try {
            $sst = $db->prepare(
                "SELECT s.id, s.roll_no, u.name, u.user_id AS login_id, u.status AS user_status
                 FROM students s JOIN users u ON s.user_id = u.id
                 WHERE s.class_id = ?
                   AND (s.deleted_at IS NULL)
                   AND (s.graduated_at IS NULL)
                 ORDER BY s.roll_no"
            );
            $sst->execute([$step2Source]);
            $step2Students = $sst->fetchAll();
        } catch (Exception $e) {
            // graduated_at column may not exist yet — fall back without filter
            try {
                $sst = $db->prepare(
                    "SELECT s.id, s.roll_no, u.name, u.user_id AS login_id, u.status AS user_status
                     FROM students s JOIN users u ON s.user_id = u.id
                     WHERE s.class_id = ?
                       AND (s.deleted_at IS NULL)
                     ORDER BY s.roll_no"
                );
                $sst->execute([$step2Source]);
                $step2Students = $sst->fetchAll();
            } catch (Exception $e2) {}
        }
        $isGradMode = ((int)($srcCls['grade'] ?? 0) >= 12);
        $showStep2  = true;
        $tab        = 'promote';
    }
}

// Promotion history
$history = [];
$historyPage  = max(1, (int)($_GET['hpage'] ?? 1));
$historyLimit = 50;
$historyOffset = ($historyPage - 1) * $historyLimit;
$historyTotal  = 0;
if ($tab === 'history') {
    try {
        $historyTotal = (int)$db->query("SELECT COUNT(*) FROM student_promotion_history")->fetchColumn();
        $history = $db->query(
            "SELECT h.*, s.class_id AS cur_class_id
             FROM student_promotion_history h
             JOIN students s ON s.id = h.student_id
             ORDER BY h.created_at DESC
             LIMIT $historyLimit OFFSET $historyOffset"
        )->fetchAll();
    } catch (Exception $e) {}
}

// Alumni
$alumni      = [];
$alumniSearch = trim($_GET['q'] ?? '');
if ($tab === 'alumni') {
    try {
        if ($alumniSearch !== '') {
            $like = '%' . $alumniSearch . '%';
            $ast  = $db->prepare(
                "SELECT s.id, s.roll_no, s.graduated_at, s.graduation_year,
                        u.name, u.user_id AS login_id, u.email, u.status
                 FROM students s JOIN users u ON s.user_id = u.id
                 WHERE s.graduated_at IS NOT NULL
                   AND (u.name LIKE ? OR s.roll_no LIKE ? OR u.user_id LIKE ?
                        OR s.graduation_year LIKE ?)
                 ORDER BY s.graduated_at DESC"
            );
            $ast->execute([$like, $like, $like, $like]);
        } else {
            $ast = $db->query(
                "SELECT s.id, s.roll_no, s.graduated_at, s.graduation_year,
                        u.name, u.user_id AS login_id, u.email, u.status
                 FROM students s JOIN users u ON s.user_id = u.id
                 WHERE s.graduated_at IS NOT NULL
                 ORDER BY s.graduated_at DESC"
            );
        }
        $alumni = $ast->fetchAll();
    } catch (Exception $e) {}
}

$maxGrade = 0;
foreach ($classes as $c) { if ((int)$c['grade'] > $maxGrade) $maxGrade = (int)$c['grade']; }

pageHead('Class Promotion', 'admin');
$links = getAdminLinks();
?>
<div class="portal-wrap">
<?php sidebar('admin', 'promote', $links, $user); ?>
<div class="main-area">
<?php topbar('Class Promotion', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- ── Tabs ─────────────────────────────────────────────────────── -->
<ul class="nav nav-tabs mb-3" style="border-bottom:2px solid var(--accent20,#e0e7ff)">
  <li class="nav-item">
    <a class="nav-link<?= $tab === 'promote' ? ' active' : '' ?>"
       href="<?= url('/portal/admin/promote.php') ?>">
      <i class="fas fa-level-up-alt me-1"></i>Promote / Graduate
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link<?= $tab === 'history' ? ' active' : '' ?>"
       href="<?= url('/portal/admin/promote.php?tab=history') ?>">
      <i class="fas fa-history me-1"></i>Promotion History
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link<?= $tab === 'alumni' ? ' active' : '' ?>"
       href="<?= url('/portal/admin/promote.php?tab=alumni') ?>">
      <i class="fas fa-graduation-cap me-1"></i>Graduated / Alumni
    </a>
  </li>
</ul>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- TAB: PROMOTE / GRADUATE                                        -->
<!-- ══════════════════════════════════════════════════════════════ -->
<?php if ($tab === 'promote'): ?>

<?php if (!$showStep2): ?>
<!-- Step 1 — Select Source Class -->
<div class="row justify-content-center">
  <div class="col-lg-5">
    <div class="sec-card">
      <div class="sec-card-header">
        <i class="fas fa-level-up-alt me-2"></i>Step 1 — Select Source Class
      </div>
      <div style="padding:20px">
        <p class="text-muted mb-3" style="font-size:.87rem">
          Choose the class whose students you want to promote or graduate.
          You will see the full student list and select individuals on the next step.
        </p>
        <form method="POST">
          <input type="hidden" name="action" value="preview">
          <div class="mb-4">
            <label class="form-label fw-semibold">Source Class <span class="text-danger">*</span></label>
            <select name="source_class" class="form-select" required>
              <option value="">— Select class —</option>
              <?php foreach ($classes as $c): ?>
              <option value="<?= $c['id'] ?>"><?= h($c['name']) ?>
                <?php if ((int)$c['grade'] >= $maxGrade): ?> (Final Class — Graduation)<?php endif; ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">
              The final class (Grade <?= $maxGrade ?>) will enter Graduation mode instead of normal promotion.
            </div>
          </div>
          <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-users me-1"></i>Load Student List
          </button>
        </form>
      </div>
    </div>
    <div class="alert alert-info mt-3" style="font-size:.83rem">
      <i class="fas fa-info-circle me-2"></i>
      <strong>Individual promotion:</strong> Only ticked students will be moved.
      Un-ticked students stay in their current class. Every action is logged
      and can be reversed from the <em>Promotion History</em> tab.
    </div>
  </div>
</div>

<?php else: /* Step 2 */ ?>
<?php
    $srcCls  = $classMap[$step2Source];
    $srcName = $srcCls['name'];
    $targetClasses = array_filter($classes, fn($c) => (int)$c['id'] !== $step2Source);
?>
<!-- Step 2 — <?= $isGradMode ? 'Graduate' : 'Promote' ?> -->
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="<?= url('/portal/admin/promote.php') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i>Back
  </a>
  <h6 class="mb-0 fw-bold" style="color:var(--accent)">
    <?php if ($isGradMode): ?>
      <i class="fas fa-graduation-cap me-1"></i>Graduate Students — <span class="text-muted fw-normal"><?= h($srcName) ?></span>
    <?php else: ?>
      <i class="fas fa-level-up-alt me-1"></i>Promote Students — <span class="text-muted fw-normal"><?= h($srcName) ?></span>
    <?php endif; ?>
  </h6>
  <span class="badge bg-secondary ms-1"><?= count($step2Students) ?> students</span>
</div>

<form method="POST" id="promoForm">
  <input type="hidden" name="action"       value="<?= $isGradMode ? 'graduate' : 'promote' ?>">
  <input type="hidden" name="source_class" value="<?= $step2Source ?>">

  <div class="row g-3">
    <!-- Left: options -->
    <div class="col-lg-4">
      <div class="sec-card h-100">
        <div class="sec-card-header">
          <i class="fas fa-cog me-2"></i><?= $isGradMode ? 'Graduation Details' : 'Promotion Details' ?>
        </div>
        <div style="padding:16px">
          <?php if ($isGradMode): ?>
            <div class="alert alert-warning" style="font-size:.83rem">
              <i class="fas fa-graduation-cap me-1"></i>
              <strong><?= h($srcName) ?></strong> is the final class. Selected students will be
              marked as <strong>Graduated/Alumni</strong> and removed from active class lists.
              Their profile, results, attendance, and fee records are preserved.
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Graduation / Batch Year <span class="text-danger">*</span></label>
              <input type="text" name="graduation_year" class="form-control"
                     value="<?= date('Y') ?>" placeholder="e.g. <?= date('Y') ?>" required>
            </div>
          <?php else: ?>
            <div class="mb-3">
              <label class="form-label fw-semibold">Promote To (Target Class) <span class="text-danger">*</span></label>
              <select name="target_class" class="form-select" required>
                <option value="">— Select target class —</option>
                <?php foreach ($targetClasses as $c): ?>
                <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="alert alert-info" style="font-size:.82rem">
              <i class="fas fa-info-circle me-1"></i>
              Only selected students will be moved. Unselected students remain in
              <strong><?= h($srcName) ?></strong>.
            </div>
          <?php endif; ?>

          <div class="d-grid mt-3">
            <button type="submit" class="btn btn-<?= $isGradMode ? 'warning' : 'primary' ?> fw-semibold"
              onclick="return confirmPromo(this)">
              <i class="fas fa-<?= $isGradMode ? 'graduation-cap' : 'level-up-alt' ?> me-1"></i>
              <?= $isGradMode ? 'Mark as Graduated' : 'Promote Selected' ?>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Right: student list -->
    <div class="col-lg-8">
      <div class="sec-card">
        <div class="sec-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
          <span><i class="fas fa-users me-2"></i>Students in <?= h($srcName) ?></span>
          <?php if (!empty($step2Students)): ?>
          <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll(true)">
              <i class="fas fa-check-square me-1"></i>Select All
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAll(false)">
              <i class="fas fa-square me-1"></i>Deselect All
            </button>
            <span class="badge bg-primary" id="selCount">0 selected</span>
          </div>
          <?php endif; ?>
        </div>

        <?php if (empty($step2Students)): ?>
        <div class="text-center text-muted py-5">
          <i class="fas fa-users-slash fa-3x mb-2" style="opacity:.3"></i>
          <p class="mb-0">No active students found in <strong><?= h($srcName) ?></strong>.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0" style="font-size:.85rem">
            <thead class="table-dark">
              <tr>
                <th style="width:42px">
                  <input type="checkbox" id="chkAll" class="form-check-input"
                         onchange="selectAll(this.checked)" title="Toggle all">
                </th>
                <th>#</th>
                <th>Name</th>
                <th>Roll No</th>
                <th>Login ID</th>
              </tr>
            </thead>
            <tbody id="studentTable">
              <?php foreach ($step2Students as $i => $s): ?>
              <tr class="student-row">
                <td>
                  <input type="checkbox" name="student_ids[]" value="<?= $s['id'] ?>"
                         class="form-check-input stu-chk" onchange="updateCount()">
                </td>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td class="fw-semibold"><?= h($s['name']) ?></td>
                <td><code><?= h($s['roll_no']) ?></code></td>
                <td class="text-muted" style="font-size:.8rem"><?= h($s['login_id']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div><!-- /row -->
</form>

<script>
function selectAll(checked) {
  document.querySelectorAll('.stu-chk').forEach(c => c.checked = checked);
  const hdr = document.getElementById('chkAll');
  if (hdr) hdr.checked = checked;
  updateCount();
}
function updateCount() {
  const n = document.querySelectorAll('.stu-chk:checked').length;
  const el = document.getElementById('selCount');
  if (el) el.textContent = n + ' selected';
  const hdr = document.getElementById('chkAll');
  const all = document.querySelectorAll('.stu-chk').length;
  if (hdr) hdr.indeterminate = (n > 0 && n < all);
  if (hdr && n === all && all > 0) hdr.checked = true;
  if (hdr && n === 0) hdr.checked = false;
}
function confirmPromo(btn) {
  const n = document.querySelectorAll('.stu-chk:checked').length;
  if (n === 0) { alert('Please select at least one student.'); return false; }
  const isGrad = <?= $isGradMode ? 'true' : 'false' ?>;
  const src    = <?= json_encode($srcName) ?>;
  const msg    = isGrad
    ? `Graduate ${n} student(s) from ${src}? They will be moved to Alumni status.`
    : `Promote ${n} student(s) from ${src}?`;
  return confirm(msg);
}
// Init count
document.addEventListener('DOMContentLoaded', updateCount);
</script>
<?php endif; // showStep2 ?>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- TAB: PROMOTION HISTORY                                         -->
<!-- ══════════════════════════════════════════════════════════════ -->
<?php elseif ($tab === 'history'): ?>

<div class="sec-card">
  <div class="sec-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span><i class="fas fa-history me-2"></i>Promotion History</span>
    <small class="text-muted"><?= $historyTotal ?> total records</small>
  </div>

  <?php if (empty($history)): ?>
  <div class="text-center text-muted py-5">
    <i class="fas fa-history fa-3x mb-2" style="opacity:.25"></i>
    <p class="mb-0">No promotion history yet.</p>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.83rem">
      <thead class="table-dark">
        <tr>
          <th>Date / Time</th>
          <th>Student</th>
          <th>Roll No</th>
          <th>Action</th>
          <th>From</th>
          <th>To</th>
          <th>By</th>
          <th>Notes</th>
          <th>Reverse</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($history as $h):
          $aColor = match($h['action']) {
            'promoted'    => 'bg-success',
            'demoted'     => 'bg-warning text-dark',
            'graduated'   => 'bg-primary',
            'ungraduated' => 'bg-secondary',
            default       => 'bg-secondary',
          };
          $canDemote = ($h['action'] === 'promoted'
            && isset($h['cur_class_id'])
            && (int)$h['cur_class_id'] === (int)$h['to_class_id']);
        ?>
        <tr>
          <td style="white-space:nowrap">
            <?= h(date('d M Y', strtotime($h['created_at']))) ?><br>
            <small class="text-muted"><?= h(date('h:i A', strtotime($h['created_at']))) ?></small>
          </td>
          <td class="fw-semibold"><?= h($h['student_name']) ?></td>
          <td><code style="font-size:.78rem"><?= h($h['roll_no']) ?></code></td>
          <td><span class="badge <?= $aColor ?>"><?= ucfirst($h['action']) ?></span></td>
          <td><?= $h['from_class_name'] ? h($h['from_class_name']) : '<span class="text-muted">—</span>' ?></td>
          <td><?= $h['to_class_name']   ? h($h['to_class_name'])   : '<span class="text-muted">Alumni</span>' ?></td>
          <td style="font-size:.8rem"><?= h($h['promoted_by_name']) ?></td>
          <td style="font-size:.78rem;color:#6b7280"><?= $h['notes'] ? h($h['notes']) : '—' ?></td>
          <td>
            <?php if ($canDemote): ?>
            <form method="POST" style="display:inline"
                  onsubmit="return confirm('Demote <?= h(addslashes($h['student_name'])) ?> from <?= h(addslashes($h['to_class_name'])) ?> back to <?= h(addslashes($h['from_class_name'])) ?>?')">
              <input type="hidden" name="action"     value="demote">
              <input type="hidden" name="history_id" value="<?= $h['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-warning" title="Reverse this promotion">
                <i class="fas fa-undo me-1"></i>Demote
              </button>
            </form>
            <?php else: ?>
            <span class="text-muted" style="font-size:.78rem">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($historyTotal > $historyLimit): ?>
  <div class="p-3 d-flex gap-2">
    <?php if ($historyPage > 1): ?>
    <a href="?tab=history&hpage=<?= $historyPage - 1 ?>" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-chevron-left me-1"></i>Previous
    </a>
    <?php endif; ?>
    <span class="text-muted my-auto" style="font-size:.83rem">
      Page <?= $historyPage ?> of <?= ceil($historyTotal / $historyLimit) ?>
    </span>
    <?php if ($historyPage * $historyLimit < $historyTotal): ?>
    <a href="?tab=history&hpage=<?= $historyPage + 1 ?>" class="btn btn-sm btn-outline-secondary ms-1">
      Next <i class="fas fa-chevron-right ms-1"></i>
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php endif; // history empty ?>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- TAB: GRADUATED / ALUMNI                                        -->
<!-- ══════════════════════════════════════════════════════════════ -->
<?php elseif ($tab === 'alumni'): ?>

<div class="sec-card">
  <div class="sec-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span><i class="fas fa-graduation-cap me-2"></i>Graduated / Alumni Students</span>
    <span class="badge bg-primary"><?= count($alumni) ?> found</span>
  </div>
  <div style="padding:12px 16px 0">
    <form method="GET" class="d-flex gap-2 mb-3">
      <input type="hidden" name="tab" value="alumni">
      <input type="text" name="q" class="form-control form-control-sm" style="max-width:280px"
             placeholder="Search by name, roll no, batch…" value="<?= h($alumniSearch) ?>">
      <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fas fa-search me-1"></i>Search</button>
      <?php if ($alumniSearch): ?>
      <a href="?tab=alumni" class="btn btn-sm btn-outline-secondary">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (empty($alumni)): ?>
  <div class="text-center text-muted py-5">
    <i class="fas fa-graduation-cap fa-3x mb-2" style="opacity:.25"></i>
    <p class="mb-0"><?= $alumniSearch ? 'No alumni match your search.' : 'No graduated students yet.' ?></p>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-dark">
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Roll No</th>
          <th>Login ID</th>
          <th>Batch / Year</th>
          <th>Graduated On</th>
          <th>Email</th>
          <th>Restore to Class</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($alumni as $i => $a): ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <td class="fw-semibold"><?= h($a['name']) ?></td>
          <td><code style="font-size:.78rem"><?= h($a['roll_no']) ?></code></td>
          <td class="text-muted" style="font-size:.8rem"><?= h($a['login_id']) ?></td>
          <td>
            <?php if ($a['graduation_year']): ?>
            <span class="badge bg-primary">Batch <?= h($a['graduation_year']) ?></span>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td style="white-space:nowrap"><?= h(date('d M Y', strtotime($a['graduated_at']))) ?></td>
          <td style="font-size:.8rem"><?= $a['email'] ? h($a['email']) : '<span class="text-muted">—</span>' ?></td>
          <td>
            <form method="POST" class="d-flex gap-1 align-items-center"
                  onsubmit="return confirm('Restore <?= h(addslashes($a['name'])) ?> to active status?')">
              <input type="hidden" name="action"     value="ungraduate">
              <input type="hidden" name="student_id" value="<?= $a['id'] ?>">
              <select name="restore_class_id" class="form-select form-select-sm" style="min-width:130px;font-size:.78rem">
                <option value="">— No Class —</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-sm btn-outline-success" title="Restore to active">
                <i class="fas fa-undo me-1"></i>Restore
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php endif; // tab ?>

</div><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /portal-wrap -->
<?php pageFooter(); ?>

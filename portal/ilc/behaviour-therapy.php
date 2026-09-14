<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp');
$db   = getDB();

// Check table exists
$tableExists = false;
try {
    $db->query('SELECT 1 FROM behaviour_therapy_reports LIMIT 1');
    $tableExists = true;
} catch (Exception $e) {}

// ── POST handler ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action    = $_POST['action']     ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);
    $month     = $_POST['month']      ?? '';  // YYYY-MM
    $monthDate = $month ? $month . '-01' : null;

    if (in_array($action, ['create', 'update']) && $studentId && $monthDate) {
        $notes    = trim($_POST['therapist_notes']  ?? '');
        $progress = trim($_POST['progress_summary'] ?? '');
        $goals    = trim($_POST['goals_next_month'] ?? '');

        if ($action === 'update') {
            $id = (int)($_POST['report_id'] ?? 0);
            $db->prepare('UPDATE behaviour_therapy_reports SET therapist_notes=?,progress_summary=?,goals_next_month=?,recorded_by=? WHERE id=?')
               ->execute([$notes ?: null, $progress ?: null, $goals ?: null, $user['id'], $id]);
            logActivity($user['id'], 'btherapy_update', "Updated behaviour therapy report #$id");
            setFlash('success', 'Report updated.');
        } else {
            $db->prepare('INSERT INTO behaviour_therapy_reports (student_id,month,therapist_notes,progress_summary,goals_next_month,recorded_by) VALUES (?,?,?,?,?,?)
                          ON DUPLICATE KEY UPDATE therapist_notes=VALUES(therapist_notes),progress_summary=VALUES(progress_summary),goals_next_month=VALUES(goals_next_month),recorded_by=VALUES(recorded_by)')
               ->execute([$studentId, $monthDate, $notes ?: null, $progress ?: null, $goals ?: null, $user['id']]);
            logActivity($user['id'], 'btherapy_create', "Saved behaviour therapy report for student #$studentId ($month)");
            setFlash('success', 'Report saved.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['report_id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM behaviour_therapy_reports WHERE id=?')->execute([$id]);
            logActivity($user['id'], 'btherapy_delete', "Deleted behaviour therapy report #$id");
            setFlash('success', 'Report deleted.');
        }
    }

    $redir = '/portal/ilc/behaviour-therapy.php' . ($studentId ? "?student_id=$studentId" : '');
    redirect($redir);
}

// ── Fetch ILC students ────────────────────────────────────────────
$studentId  = (int)($_GET['student_id'] ?? 0);
$editId     = (int)($_GET['edit']       ?? 0);
$students   = $db->query(
    'SELECT s.id, u.name, s.roll_no, c.name AS class_name
     FROM students s
     JOIN users u ON u.id = s.user_id
     JOIN classes c ON c.id = s.class_id
     WHERE c.is_ilc = 1
     ORDER BY c.name, s.roll_no'
)->fetchAll();

$reports   = [];
$editReport = null;
if ($studentId && $tableExists) {
    $st = $db->prepare(
        'SELECT r.*, u.name AS recorder_name
         FROM behaviour_therapy_reports r
         JOIN users u ON u.id = r.recorded_by
         WHERE r.student_id = ?
         ORDER BY r.month DESC'
    );
    $st->execute([$studentId]);
    $reports = $st->fetchAll();

    if ($editId) {
        foreach ($reports as $r) {
            if ($r['id'] == $editId) { $editReport = $r; break; }
        }
    }
}

$curStudent = null;
if ($studentId) {
    foreach ($students as $s) { if ($s['id'] === $studentId) { $curStudent = $s; break; } }
}

pageHead('Behaviour Therapy — ILC', 'ilc_vp');
$links = getIlcLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'behaviour-therapy', $links, $user); ?>
<div class="main-area">
<?php topbar('Behaviour Therapy Reports', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  <strong>Migration not applied.</strong> Run <code>database/migrations/ilc_features.sql</code> first. ⚠️ Back up your DB!
</div>
<?php else: ?>

<!-- ILC strip -->
<div class="d-flex align-items-center gap-3 mb-3 p-3" style="background:linear-gradient(90deg,#ecfeff,#f0fdf4);border-radius:10px;border:1px solid #a5f3fc;">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC" style="width:40px;height:40px;object-fit:contain;flex-shrink:0;">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#0891b2;letter-spacing:.8px;text-transform:uppercase">Behaviour Therapy — Monthly Reports</div>
    <div style="font-size:.78rem;color:#475569">Inclusive Learning Centre · Bahria Model College Bin Qasim</div>
  </div>
</div>

<div class="row g-3">
  <!-- Student selector + report form -->
  <div class="col-lg-5">
    <!-- Student selector -->
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-user-graduate me-2"></i>Select Student</div>
      <div style="padding:14px 16px">
        <form method="GET" class="d-flex gap-2">
          <select name="student_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">— Select student —</option>
            <?php foreach ($students as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $studentId===$s['id']?'selected':'' ?>>
              <?= h($s['name']) ?> (<?= h($s['roll_no']) ?>) — <?= h($s['class_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <!-- Add / Edit report form -->
    <?php if ($studentId): ?>
    <div class="sec-card">
      <div class="sec-card-header">
        <i class="fas fa-<?= $editReport ? 'edit' : 'plus' ?> me-2"></i>
        <?= $editReport ? 'Edit Report — ' . date('M Y', strtotime($editReport['month'])) : 'Add Monthly Report' ?>
      </div>
      <div style="padding:16px">
        <form method="POST">
          <input type="hidden" name="action" value="<?= $editReport ? 'update' : 'create' ?>">
          <input type="hidden" name="student_id" value="<?= $studentId ?>">
          <?php if ($editReport): ?>
          <input type="hidden" name="report_id" value="<?= $editReport['id'] ?>">
          <input type="hidden" name="month" value="<?= substr($editReport['month'], 0, 7) ?>">
          <?php else: ?>
          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Month <span class="text-danger">*</span></label>
            <input type="month" name="month" class="form-control form-control-sm" required
                   value="<?= date('Y-m') ?>">
          </div>
          <?php endif; ?>
          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Therapist Notes</label>
            <textarea name="therapist_notes" class="form-control form-control-sm" rows="3"
                      placeholder="Observations, sessions conducted…"><?= h($editReport['therapist_notes'] ?? '') ?></textarea>
          </div>
          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Progress Summary</label>
            <textarea name="progress_summary" class="form-control form-control-sm" rows="3"
                      placeholder="Progress made this month…"><?= h($editReport['progress_summary'] ?? '') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Goals for Next Month</label>
            <textarea name="goals_next_month" class="form-control form-control-sm" rows="2"
                      placeholder="Target goals…"><?= h($editReport['goals_next_month'] ?? '') ?></textarea>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-success">
              <i class="fas fa-save me-1"></i><?= $editReport ? 'Update' : 'Save Report' ?>
            </button>
            <?php if ($editReport): ?>
            <a href="?student_id=<?= $studentId ?>" class="btn btn-sm btn-outline-secondary">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Reports list -->
  <div class="col-lg-7">
    <?php if ($studentId && $curStudent): ?>
    <div class="sec-card">
      <div class="sec-card-header">
        <i class="fas fa-brain me-2"></i>Reports — <?= h($curStudent['name']) ?>
        <span class="badge bg-secondary ms-2"><?= count($reports) ?></span>
      </div>
      <?php if (empty($reports)): ?>
      <div style="padding:36px;text-align:center;color:var(--t2);font-size:.85rem">
        No reports yet. Use the form to add the first report.
      </div>
      <?php else: ?>
      <div style="padding:12px 16px">
        <?php foreach ($reports as $r): ?>
        <div class="mb-3 p-3" style="background:#f7f9fb;border-radius:8px;border:1px solid var(--border)">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <span class="badge" style="background:#0891b2;font-size:.75rem"><?= date('F Y', strtotime($r['month'])) ?></span>
              <span style="font-size:.75rem;color:var(--t2);margin-left:8px">Recorded by <?= h($r['recorder_name']) ?> · <?= fDate($r['created_at']) ?></span>
            </div>
            <div class="d-flex gap-1">
              <a href="?student_id=<?= $studentId ?>&edit=<?= $r['id'] ?>" class="btn btn-xs btn-outline-primary"><i class="fas fa-edit"></i></a>
              <form method="POST" class="d-inline" onsubmit="return confirm('Delete this report?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="student_id" value="<?= $studentId ?>">
                <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </div>
          <?php if ($r['therapist_notes']): ?>
          <div style="font-size:.82rem;margin-bottom:6px"><strong>Notes:</strong> <?= nl2br(h($r['therapist_notes'])) ?></div>
          <?php endif; ?>
          <?php if ($r['progress_summary']): ?>
          <div style="font-size:.82rem;margin-bottom:6px"><strong>Progress:</strong> <?= nl2br(h($r['progress_summary'])) ?></div>
          <?php endif; ?>
          <?php if ($r['goals_next_month']): ?>
          <div style="font-size:.82rem;color:#0369a1"><strong>Next month goals:</strong> <?= nl2br(h($r['goals_next_month'])) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php elseif (!$studentId): ?>
    <div class="sec-card">
      <div style="padding:60px;text-align:center;color:var(--t2)">
        <i class="fas fa-brain fa-2x mb-3 d-block opacity-20"></i>
        Select a student on the left to view or add therapy reports.
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

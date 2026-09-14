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
    $db->query('SELECT 1 FROM ilc_assessments LIMIT 1');
    $tableExists = true;
} catch (Exception $e) {}

$assessmentTypes = ['Initial Intake', 'Quarterly Review', 'Annual', 'Progress Check', 'Exit Assessment'];

// ── POST handler ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action    = $_POST['action']     ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);

    if (in_array($action, ['create', 'update']) && $studentId) {
        $date    = $_POST['assessment_date'] ?? date('Y-m-d');
        $type    = $_POST['assessment_type'] ?? 'Initial Intake';
        if (!in_array($type, $assessmentTypes)) $type = 'Initial Intake';
        $strengths  = trim($_POST['strengths']       ?? '');
        $challenges = trim($_POST['challenges']      ?? '');
        $recs       = trim($_POST['recommendations'] ?? '');

        if ($action === 'update') {
            $id = (int)($_POST['assessment_id'] ?? 0);
            $db->prepare('UPDATE ilc_assessments SET assessment_date=?,assessment_type=?,strengths=?,challenges=?,recommendations=?,conducted_by=? WHERE id=?')
               ->execute([$date, $type, $strengths ?: null, $challenges ?: null, $recs ?: null, $user['id'], $id]);
            logActivity($user['id'], 'ilc_assessment_update', "Updated assessment #$id");
            setFlash('success', 'Assessment updated.');
        } else {
            $db->prepare('INSERT INTO ilc_assessments (student_id,assessment_date,assessment_type,strengths,challenges,recommendations,conducted_by) VALUES (?,?,?,?,?,?,?)')
               ->execute([$studentId, $date, $type, $strengths ?: null, $challenges ?: null, $recs ?: null, $user['id']]);
            logActivity($user['id'], 'ilc_assessment_create', "Added assessment for student #$studentId");
            setFlash('success', 'Assessment saved.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['assessment_id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM ilc_assessments WHERE id=?')->execute([$id]);
            logActivity($user['id'], 'ilc_assessment_delete', "Deleted assessment #$id");
            setFlash('success', 'Assessment deleted.');
        }
    }

    $redir = '/portal/ilc/assessments.php' . ($studentId ? "?student_id=$studentId" : '');
    redirect($redir);
}

// ── Fetch ─────────────────────────────────────────────────────────
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

$assessments = [];
$editRecord  = null;
if ($studentId && $tableExists) {
    $st = $db->prepare(
        'SELECT a.*, u.name AS conductor_name
         FROM ilc_assessments a
         JOIN users u ON u.id = a.conducted_by
         WHERE a.student_id = ?
         ORDER BY a.assessment_date DESC'
    );
    $st->execute([$studentId]);
    $assessments = $st->fetchAll();

    if ($editId) {
        foreach ($assessments as $a) {
            if ($a['id'] == $editId) { $editRecord = $a; break; }
        }
    }
}

$curStudent = null;
if ($studentId) {
    foreach ($students as $s) { if ($s['id'] === $studentId) { $curStudent = $s; break; } }
}

pageHead('Assessments — ILC', 'ilc_vp');
$links = getIlcLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'assessments', $links, $user); ?>
<div class="main-area">
<?php topbar('Student Assessments', $user); ?>
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
    <div style="font-size:.72rem;font-weight:700;color:#0891b2;letter-spacing:.8px;text-transform:uppercase">Student Assessments</div>
    <div style="font-size:.78rem;color:#475569">Inclusive Learning Centre · Bahria Model College Bin Qasim</div>
  </div>
</div>

<div class="row g-3">
  <!-- Student selector + form -->
  <div class="col-lg-5">
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-user-graduate me-2"></i>Select Student</div>
      <div style="padding:14px 16px">
        <form method="GET">
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

    <?php if ($studentId): ?>
    <div class="sec-card">
      <div class="sec-card-header">
        <i class="fas fa-<?= $editRecord ? 'edit' : 'plus' ?> me-2"></i>
        <?= $editRecord ? 'Edit Assessment' : 'New Assessment' ?>
      </div>
      <div style="padding:16px">
        <form method="POST">
          <input type="hidden" name="action" value="<?= $editRecord ? 'update' : 'create' ?>">
          <input type="hidden" name="student_id" value="<?= $studentId ?>">
          <?php if ($editRecord): ?>
          <input type="hidden" name="assessment_id" value="<?= $editRecord['id'] ?>">
          <?php endif; ?>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label fw-semibold" style="font-size:.82rem">Date <span class="text-danger">*</span></label>
              <input type="date" name="assessment_date" class="form-control form-control-sm" required
                     value="<?= h($editRecord['assessment_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold" style="font-size:.82rem">Type</label>
              <select name="assessment_type" class="form-select form-select-sm">
                <?php foreach ($assessmentTypes as $t): ?>
                <option <?= ($editRecord['assessment_type'] ?? 'Initial Intake') === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Strengths</label>
            <textarea name="strengths" class="form-control form-control-sm" rows="2"
                      placeholder="Student's strengths observed…"><?= h($editRecord['strengths'] ?? '') ?></textarea>
          </div>
          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Challenges</label>
            <textarea name="challenges" class="form-control form-control-sm" rows="2"
                      placeholder="Areas requiring support…"><?= h($editRecord['challenges'] ?? '') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Recommendations</label>
            <textarea name="recommendations" class="form-control form-control-sm" rows="2"
                      placeholder="Recommended interventions / actions…"><?= h($editRecord['recommendations'] ?? '') ?></textarea>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-success">
              <i class="fas fa-save me-1"></i><?= $editRecord ? 'Update' : 'Save' ?>
            </button>
            <?php if ($editRecord): ?>
            <a href="?student_id=<?= $studentId ?>" class="btn btn-sm btn-outline-secondary">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Assessments list -->
  <div class="col-lg-7">
    <?php if ($studentId && $curStudent): ?>
    <div class="sec-card">
      <div class="sec-card-header">
        <i class="fas fa-clipboard-list me-2"></i>Assessments — <?= h($curStudent['name']) ?>
        <span class="badge bg-secondary ms-2"><?= count($assessments) ?></span>
      </div>
      <?php if (empty($assessments)): ?>
      <div style="padding:36px;text-align:center;color:var(--t2);font-size:.85rem">
        No assessments yet. Use the form to add the first assessment.
      </div>
      <?php else: ?>
      <div style="padding:12px 16px">
        <?php foreach ($assessments as $a): ?>
        <div class="mb-3 p-3" style="background:#f7f9fb;border-radius:8px;border:1px solid var(--border)">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <span class="badge" style="background:#0891b2;font-size:.75rem"><?= h($a['assessment_type']) ?></span>
              <span class="ms-2 fw-semibold" style="font-size:.84rem"><?= date('d M Y', strtotime($a['assessment_date'])) ?></span>
              <div style="font-size:.74rem;color:var(--t2)">Conducted by <?= h($a['conductor_name']) ?> · <?= fDate($a['created_at']) ?></div>
            </div>
            <div class="d-flex gap-1">
              <a href="?student_id=<?= $studentId ?>&edit=<?= $a['id'] ?>" class="btn btn-xs btn-outline-primary"><i class="fas fa-edit"></i></a>
              <form method="POST" class="d-inline" onsubmit="return confirm('Delete this assessment?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="student_id" value="<?= $studentId ?>">
                <input type="hidden" name="assessment_id" value="<?= $a['id'] ?>">
                <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </div>
          <?php if ($a['strengths']): ?>
          <div style="font-size:.82rem;margin-bottom:5px"><strong class="text-success">Strengths:</strong> <?= nl2br(h($a['strengths'])) ?></div>
          <?php endif; ?>
          <?php if ($a['challenges']): ?>
          <div style="font-size:.82rem;margin-bottom:5px"><strong class="text-danger">Challenges:</strong> <?= nl2br(h($a['challenges'])) ?></div>
          <?php endif; ?>
          <?php if ($a['recommendations']): ?>
          <div style="font-size:.82rem"><strong class="text-primary">Recommendations:</strong> <?= nl2br(h($a['recommendations'])) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="sec-card">
      <div style="padding:60px;text-align:center;color:var(--t2)">
        <i class="fas fa-clipboard-list fa-2x mb-3 d-block opacity-20"></i>
        Select a student on the left to view or add assessments.
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

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
try { $db->query('SELECT 1 FROM student_complaints LIMIT 0'); $tableExists = true; } catch (Exception $e) {}

// ── POST: Submit complaint ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $subject   = trim($_POST['subject']     ?? '');
    $message   = trim($_POST['message']     ?? '');

    if (!$teacherId || !$subject || !$message) {
        setFlash('danger', 'All fields are required.');
    } else {
        $db->prepare(
            'INSERT INTO student_complaints (student_id, teacher_id, subject, message, status)
             VALUES (?,?,?,?,"pending")'
        )->execute([$student['id'], $teacherId, $subject, $message]);
        logActivity($user['id'], 'complaint_submit', "Submitted complaint to teacher #$teacherId");
        setFlash('success', 'Complaint submitted successfully.');
    }
    redirect('/student/complaints.php');
}

// Fetch teachers
$teachers = $db->query(
    'SELECT u.id, u.name FROM users u WHERE u.role="teacher" AND u.status="active" ORDER BY u.name'
)->fetchAll();

// My complaints
$complaints = [];
if ($tableExists) {
    $st = $db->prepare(
        'SELECT sc.*, u.name AS teacher_name
         FROM student_complaints sc
         JOIN users u ON u.id = sc.teacher_id
         WHERE sc.student_id = ?
         ORDER BY sc.created_at DESC'
    );
    $st->execute([$student['id']]);
    $complaints = $st->fetchAll();
}

pageHead('My Complaints', 'student');
$links = getStudentLinks();
?>
<div class="portal-wrap">
<?php sidebar('student', 'complaints', $links, $user); ?>
<div class="main-area">
<?php topbar('Complaints', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  The complaints system is not yet set up. Please ask the administrator to run the features migration.
</div>
<?php else: ?>

<div class="row g-3">
  <!-- Submit form -->
  <div class="col-lg-4">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-plus me-2"></i>Submit a Complaint</div>
      <div style="padding:16px">
        <form method="POST">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">To Teacher <span class="text-danger">*</span></label>
            <select name="teacher_id" class="form-select form-select-sm" required>
              <option value="">Select teacher…</option>
              <?php foreach ($teachers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= h($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Subject <span class="text-danger">*</span></label>
            <input type="text" name="subject" class="form-control form-control-sm" required placeholder="Brief summary of your complaint">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Message <span class="text-danger">*</span></label>
            <textarea name="message" class="form-control form-control-sm" rows="5" required
                      placeholder="Describe the issue in detail…"></textarea>
          </div>
          <button type="submit" class="btn btn-sm btn-primary w-100">
            <i class="fas fa-paper-plane me-1"></i>Submit Complaint
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- My complaints -->
  <div class="col-lg-8">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-list me-2"></i>My Complaints (<?= count($complaints) ?>)</div>
      <?php if (empty($complaints)): ?>
      <div style="padding:40px;text-align:center;color:var(--t2);font-size:.85rem">No complaints submitted yet.</div>
      <?php else: ?>
      <?php foreach ($complaints as $c):
        $statusClass = match($c['status']) { 'resolved'=>'success','reviewed'=>'info','pending'=>'warning',default=>'secondary' };
      ?>
      <div style="padding:14px 16px;border-bottom:1px solid var(--border)">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div style="flex:1;min-width:0">
            <div class="fw-semibold" style="font-size:.9rem"><?= h($c['subject']) ?></div>
            <div style="font-size:.78rem;color:var(--t2)">
              To: <strong><?= h($c['teacher_name']) ?></strong>
              &nbsp;·&nbsp; <?= fDate($c['created_at']) ?>
            </div>
            <div style="font-size:.84rem;margin-top:6px;color:var(--t1);white-space:pre-line"><?= h($c['message']) ?></div>
            <?php if ($c['teacher_response']): ?>
            <div class="mt-2 p-2" style="background:#f0f9ff;border-radius:6px;border-left:3px solid #0891b2;font-size:.84rem">
              <strong style="font-size:.75rem;color:#0891b2;text-transform:uppercase">Teacher Response:</strong><br>
              <?= h($c['teacher_response']) ?>
            </div>
            <?php endif; ?>
          </div>
          <span class="badge bg-<?= $statusClass ?> flex-shrink-0"><?= ucfirst($c['status']) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

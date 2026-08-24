<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('teacher');
requirePermission('complaints');
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);
if (!$teacher) { setFlash('danger','Teacher record not found.'); redirect('/portal/index.php'); }

// Check table exists
$tableExists = false;
try { $db->query('SELECT 1 FROM student_complaints LIMIT 0'); $tableExists = true; } catch (Exception $e) {}

// ── POST: Respond to complaint ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action = $_POST['action'] ?? '';

    if ($action === 'respond') {
        $id       = (int)($_POST['complaint_id'] ?? 0);
        $response = trim($_POST['response']       ?? '');
        $status   = $_POST['status']              ?? 'reviewed';
        if (!in_array($status, ['reviewed','resolved'])) $status = 'reviewed';
        if ($id && $response) {
            $db->prepare(
                'UPDATE student_complaints SET teacher_response=?, status=?, responded_at=NOW()
                 WHERE id=? AND teacher_id=?'
            )->execute([$response, $status, $id, $user['id']]);
            setFlash('success', 'Response submitted.');
        }
    }
    redirect('/portal/teacher/complaints.php');
}

// Fetch complaints addressed to this teacher
$complaints = [];
$filter     = $_GET['status'] ?? '';
if ($tableExists) {
    $where  = 'sc.teacher_id = ?';
    $params = [$user['id']];
    if ($filter && in_array($filter, ['pending','reviewed','resolved'])) {
        $where  .= ' AND sc.status = ?';
        $params[] = $filter;
    }
    $st = $db->prepare(
        "SELECT sc.*, u.name AS student_name, s.roll_no,
                c.name AS class_name
         FROM student_complaints sc
         JOIN students s  ON s.id = sc.student_id
         JOIN users u     ON u.id = s.user_id
         LEFT JOIN classes c ON c.id = s.class_id
         WHERE $where
         ORDER BY sc.created_at DESC"
    );
    $st->execute($params);
    $complaints = $st->fetchAll();
}

// Print mode — render a single complaint as a printable page
if (isset($_GET['print']) && $tableExists) {
    $pid = (int)$_GET['print'];
    $pSt = $db->prepare(
        'SELECT sc.*, u.name AS student_name, s.roll_no, c.name AS class_name, tu.name AS teacher_name
         FROM student_complaints sc
         JOIN students s ON s.id = sc.student_id
         JOIN users u ON u.id = s.user_id
         LEFT JOIN classes c ON c.id = s.class_id
         JOIN users tu ON tu.id = sc.teacher_id
         WHERE sc.id=? AND sc.teacher_id=?'
    );
    $pSt->execute([$pid, $user['id']]);
    $pc = $pSt->fetch();
    if ($pc):
?><!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Complaint #<?= $pc['id'] ?></title>
<style>
  body { font-family: Arial, sans-serif; font-size: 13px; color: #111; margin: 30px; }
  h2 { color: #1a1a1a; border-bottom: 2px solid #333; padding-bottom: 6px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  td { padding: 6px 8px; border: 1px solid #ccc; }
  th { padding: 6px 8px; background: #f3f4f6; border: 1px solid #ccc; text-align: left; }
  .section-title { font-weight: bold; margin: 16px 0 6px; font-size: 12px; text-transform: uppercase; color: #555; }
  .box { background: #f9fafb; border: 1px solid #ddd; border-radius: 4px; padding: 10px; margin-bottom: 12px; white-space: pre-wrap; }
  @media print { button { display: none; } }
</style>
</head>
<body>
<div style="text-align:center;margin-bottom:20px">
  <h2>Bahria Model College Bin Qasim</h2>
  <div style="font-size:14px;color:#555">Student Complaint — Official Record</div>
</div>
<table>
  <tr><th style="width:30%">Complaint #</th><td><?= $pc['id'] ?></td></tr>
  <tr><th>Student</th><td><?= h($pc['student_name']) ?> (<?= h($pc['roll_no']) ?>)</td></tr>
  <tr><th>Class</th><td><?= h($pc['class_name'] ?? '—') ?></td></tr>
  <tr><th>Addressed To</th><td><?= h($pc['teacher_name']) ?></td></tr>
  <tr><th>Subject</th><td><?= h($pc['subject']) ?></td></tr>
  <tr><th>Status</th><td><?= ucfirst($pc['status']) ?></td></tr>
  <tr><th>Date Submitted</th><td><?= fDateTime($pc['created_at']) ?></td></tr>
  <?php if ($pc['responded_at']): ?>
  <tr><th>Date Responded</th><td><?= fDateTime($pc['responded_at']) ?></td></tr>
  <?php endif; ?>
</table>
<div class="section-title">Complaint Message</div>
<div class="box"><?= h($pc['message']) ?></div>
<?php if ($pc['teacher_response']): ?>
<div class="section-title">Teacher Response</div>
<div class="box"><?= h($pc['teacher_response']) ?></div>
<?php endif; ?>
<div style="margin-top:40px;font-size:11px;color:#888;text-align:center">
  Printed on <?= date('d M Y H:i') ?> &mdash; BMC Portal
</div>
<script>window.print();</script>
</body></html>
<?php
    exit;
    endif;
}

pageHead('Student Complaints', 'teacher');
$links = getTeacherLinks();
?>
<div class="portal-wrap">
<?php sidebar('teacher', 'complaints', $links, $user); ?>
<div class="main-area">
<?php topbar('Student Complaints', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  The complaints system is not set up yet. Ask admin to run the features migration SQL.
</div>
<?php else: ?>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-comment-alt me-2"></i>Complaints Addressed to You (<?= count($complaints) ?>)</span>
    <div class="d-flex gap-2">
      <div class="btn-group btn-group-sm">
        <a href="?" class="btn btn-outline-secondary <?= !$filter?'active':'' ?>">All</a>
        <a href="?status=pending" class="btn btn-outline-warning <?= $filter==='pending'?'active':'' ?>">Pending</a>
        <a href="?status=reviewed" class="btn btn-outline-info <?= $filter==='reviewed'?'active':'' ?>">Reviewed</a>
        <a href="?status=resolved" class="btn btn-outline-success <?= $filter==='resolved'?'active':'' ?>">Resolved</a>
      </div>
    </div>
  </div>

  <?php if (empty($complaints)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2);font-size:.85rem">
    <i class="fas fa-check-circle fa-2x mb-2 d-block text-success opacity-50"></i>
    No complaints found.
  </div>
  <?php else: ?>
  <?php foreach ($complaints as $c):
    $statusClass = match($c['status']) { 'resolved'=>'success','reviewed'=>'info',default=>'warning' };
  ?>
  <div style="padding:16px;border-bottom:1px solid var(--border)">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div style="flex:1;min-width:0">
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="fw-semibold" style="font-size:.9rem"><?= h($c['subject']) ?></span>
          <span class="badge bg-<?= $statusClass ?>" style="font-size:.7rem"><?= ucfirst($c['status']) ?></span>
        </div>
        <div style="font-size:.78rem;color:var(--t2)">
          From: <strong><?= h($c['student_name']) ?></strong>
          (<?= h($c['roll_no']) ?>)
          · Class: <?= h($c['class_name'] ?? '—') ?>
          · <?= fDate($c['created_at']) ?>
        </div>
        <div style="font-size:.84rem;margin-top:8px;padding:10px;background:#f8fafc;border-radius:6px;border-left:3px solid #e2e8f0;white-space:pre-line">
          <?= h($c['message']) ?>
        </div>
        <?php if ($c['teacher_response']): ?>
        <div style="font-size:.84rem;margin-top:6px;padding:10px;background:#f0fdf4;border-radius:6px;border-left:3px solid #059669">
          <strong style="font-size:.74rem;color:#059669">Your Response:</strong><br><?= h($c['teacher_response']) ?>
        </div>
        <?php endif; ?>
        <?php if ($c['status'] === 'pending' || $c['status'] === 'reviewed'): ?>
        <button class="btn btn-xs btn-outline-primary mt-2" style="font-size:.78rem"
                data-bs-toggle="collapse" data-bs-target="#respondForm<?= $c['id'] ?>">
          <i class="fas fa-reply me-1"></i>Respond
        </button>
        <div class="collapse mt-2" id="respondForm<?= $c['id'] ?>">
          <form method="POST">
            <input type="hidden" name="action" value="respond">
            <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
            <textarea name="response" class="form-control form-control-sm mb-2" rows="3"
                      placeholder="Write your response…" required><?= h($c['teacher_response'] ?? '') ?></textarea>
            <div class="d-flex gap-2">
              <select name="status" class="form-select form-select-sm" style="width:auto">
                <option value="reviewed">Mark as Reviewed</option>
                <option value="resolved">Mark as Resolved</option>
              </select>
              <button type="submit" class="btn btn-sm btn-success">Submit Response</button>
            </div>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <div class="d-flex gap-1 flex-shrink-0">
        <a href="?print=<?= $c['id'] ?>" target="_blank" class="btn btn-xs btn-outline-secondary"
           style="font-size:.74rem;padding:2px 8px" title="Print/PDF">
          <i class="fas fa-print"></i> PDF
        </a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php endif; ?>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

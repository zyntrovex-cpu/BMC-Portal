<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('vp_main');
requirePermission('vp_att_requests');
$db   = getDB();

// Check table exists
$tableExists = false;
try { $db->query('SELECT 1 FROM attendance_edit_requests LIMIT 0'); $tableExists = true; } catch (Exception $e) {}

// ── POST: Approve / Reject ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['request_id'] ?? 0);

    if (($action === 'approve' || $action === 'reject') && $id) {
        if ($action === 'approve') {
            // Fetch request details
            $st = $db->prepare('SELECT * FROM attendance_edit_requests WHERE id=? AND status="pending"');
            $st->execute([$id]);
            $req = $st->fetch();
            if ($req) {
                // Update attendance record
                $db->prepare(
                    'UPDATE attendance SET status=?
                     WHERE student_id=? AND subject_id=? AND date=?'
                )->execute([$req['new_status'], $req['student_id'], $req['subject_id'], $req['date']]);

                $db->prepare(
                    'UPDATE attendance_edit_requests SET status="approved", reviewed_by=?, reviewed_at=NOW() WHERE id=?'
                )->execute([$user['id'], $id]);
                logActivity($user['id'], 'att_req_approve', "Approved attendance edit request #$id");
                setFlash('success', 'Request approved and attendance updated.');
            }
        } else {
            $db->prepare(
                'UPDATE attendance_edit_requests SET status="rejected", reviewed_by=?, reviewed_at=NOW() WHERE id=?'
            )->execute([$user['id'], $id]);
            logActivity($user['id'], 'att_req_reject', "Rejected attendance edit request #$id");
            setFlash('success', 'Request rejected.');
        }
    }
    redirect('/vp/attendance-requests.php');
}

// Fetch requests
$requests = [];
$filter   = $_GET['status'] ?? 'pending';
if ($tableExists) {
    $where  = '1';
    $params = [];
    if (in_array($filter, ['pending','approved','rejected'])) {
        $where  = 'aer.status = ?';
        $params[] = $filter;
    }
    $st = $db->prepare(
        "SELECT aer.*,
                su.name AS student_name, s.roll_no,
                c.name AS class_name,
                sb.name AS subject_name,
                tu.name AS teacher_name
         FROM attendance_edit_requests aer
         JOIN students s  ON s.id  = aer.student_id
         JOIN users su    ON su.id = s.user_id
         LEFT JOIN classes c  ON c.id  = aer.class_id
         LEFT JOIN subjects sb ON sb.id = aer.subject_id
         JOIN teachers t  ON t.id  = aer.teacher_id
         JOIN users tu    ON tu.id = t.user_id
         WHERE $where
         ORDER BY aer.created_at DESC
         LIMIT 100"
    );
    $st->execute($params);
    $requests = $st->fetchAll();
}

$pendingCount = 0;
if ($tableExists) {
    try {
        $pendingCount = (int)$db->query('SELECT COUNT(*) FROM attendance_edit_requests WHERE status="pending"')->fetchColumn();
    } catch (Exception $e) {}
}

pageHead('Attendance Edit Requests', 'vp_main');
$links = getVpLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('vp_main', 'att_requests', $links, $user); ?>
<div class="main-area">
<?php topbar('Attendance Edit Requests', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  The attendance edit requests table is not set up. Ask admin to run the features migration SQL.
</div>
<?php else: ?>

<?php if ($pendingCount > 0): ?>
<div class="alert alert-warning d-flex gap-2 align-items-center" style="border-radius:8px;font-size:.87rem">
  <i class="fas fa-clock"></i>
  <span><strong><?= $pendingCount ?></strong> pending attendance edit request<?= $pendingCount>1?'s':'' ?> awaiting review.</span>
</div>
<?php endif; ?>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-edit me-2"></i>Attendance Edit Requests</span>
    <div class="btn-group btn-group-sm">
      <a href="?status=pending"  class="btn btn-outline-warning <?= $filter==='pending'?'active':'' ?>">Pending (<?= $pendingCount ?>)</a>
      <a href="?status=approved" class="btn btn-outline-success <?= $filter==='approved'?'active':'' ?>">Approved</a>
      <a href="?status=rejected" class="btn btn-outline-danger <?= $filter==='rejected'?'active':'' ?>">Rejected</a>
      <a href="?"                class="btn btn-outline-secondary <?= $filter===''?'active':'' ?>">All</a>
    </div>
  </div>

  <?php if (empty($requests)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2);font-size:.85rem">
    <i class="fas fa-check-circle fa-2x mb-2 d-block text-success opacity-50"></i>
    No requests found.
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr>
          <th>Student</th>
          <th>Class</th>
          <th>Subject</th>
          <th>Date</th>
          <th>Old</th>
          <th>New</th>
          <th>Teacher</th>
          <th>Reason</th>
          <th>Status</th>
          <?php if ($filter === 'pending' || $filter === ''): ?><th></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $r):
          $statusClass = match($r['status']) { 'approved'=>'success','rejected'=>'danger',default=>'warning' };
          $attLabels   = ['P'=>'Present','A'=>'Absent','L'=>'Leave'];
        ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= h($r['student_name']) ?></div>
            <div style="font-size:.74rem;color:var(--t3)"><?= h($r['roll_no']) ?></div>
          </td>
          <td><?= h($r['class_name'] ?? '—') ?></td>
          <td><?= h($r['subject_name'] ?? '—') ?></td>
          <td><?= fDate($r['date']) ?></td>
          <td><span class="badge bg-<?= $r['old_status']==='P'?'success':($r['old_status']==='A'?'danger':'warning') ?>"><?= h($attLabels[$r['old_status']] ?? $r['old_status']) ?></span></td>
          <td><span class="badge bg-<?= $r['new_status']==='P'?'success':($r['new_status']==='A'?'danger':'warning') ?>"><?= h($attLabels[$r['new_status']] ?? $r['new_status']) ?></span></td>
          <td><?= h($r['teacher_name']) ?></td>
          <td style="max-width:180px">
            <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($r['reason']) ?>">
              <?= h($r['reason']) ?>
            </div>
          </td>
          <td><span class="badge bg-<?= $statusClass ?>"><?= ucfirst($r['status']) ?></span></td>
          <?php if ($filter === 'pending' || $filter === ''): ?>
          <td>
            <?php if ($r['status'] === 'pending'): ?>
            <div class="d-flex gap-1">
              <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                <button class="btn btn-xs btn-success" style="font-size:.74rem;padding:2px 8px">
                  <i class="fas fa-check"></i> Approve
                </button>
              </form>
              <form method="POST" class="d-inline" onsubmit="return confirm('Reject this request?')">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                <button class="btn btn-xs btn-danger" style="font-size:.74rem;padding:2px 8px">
                  <i class="fas fa-times"></i> Reject
                </button>
              </form>
            </div>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php endif; ?>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

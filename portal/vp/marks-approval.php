<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/db.php';

$user = requireAuth('vp_main');
requirePermission('vp_marks_approval');
$db   = getDB();

$WING = 'main';

$tableExists = false;
try { $db->query('SELECT 1 FROM marks_permission_requests LIMIT 0'); $tableExists = true; } catch (Exception $e) {}

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action = $_POST['action'] ?? '';
    $reqId  = (int)($_POST['request_id'] ?? 0);

    if ($reqId && in_array($action, ['approve_request','reject_request'], true)) {
        // Verify request belongs to a main-wing teacher
        $stCheck = $db->prepare(
            "SELECT mpr.*, t.user_id AS teacher_user_id
             FROM marks_permission_requests mpr
             JOIN teachers t ON t.id = mpr.teacher_id
             WHERE mpr.id = ? AND t.wing = ?"
        );
        $stCheck->execute([$reqId, $WING]);
        $reqRow = $stCheck->fetch();

        if ($reqRow) {
            if ($action === 'approve_request') {
                $db->prepare(
                    "UPDATE marks_permission_requests SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?"
                )->execute([$user['id'], $reqId]);

                createNotification(
                    (int)$reqRow['teacher_user_id'],
                    'marks_approved',
                    'Your marks entry request has been approved.',
                    $reqId,
                    'marks_permission_request'
                );
                logActivity($user['id'], 'marks_permission_approved', "Approved marks permission request #$reqId");
                setFlash('success', 'Permission approved. Teacher can now enter marks.');
            } else {
                $reason = trim($_POST['rejection_reason'] ?? '');
                if (!$reason) {
                    setFlash('danger', 'Rejection reason is required.');
                } else {
                    $db->prepare(
                        "UPDATE marks_permission_requests SET status='rejected', reviewed_by=?, reviewed_at=NOW(), rejection_reason=? WHERE id=?"
                    )->execute([$user['id'], $reason, $reqId]);

                    createNotification(
                        (int)$reqRow['teacher_user_id'],
                        'marks_rejected',
                        'Your marks entry request was rejected: ' . $reason,
                        $reqId,
                        'marks_permission_request'
                    );
                    logActivity($user['id'], 'marks_permission_rejected', "Rejected marks permission request #$reqId: $reason");
                    setFlash('success', 'Permission rejected.');
                }
            }
        } else {
            setFlash('danger', 'Request not found or not authorized.');
        }
    }
    redirect('/portal/vp/marks-approval.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
}

// ── Fetch requests ────────────────────────────────────────────────────────────
$filter   = $_GET['status'] ?? 'pending';
$requests = [];
$counts   = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'all' => 0];

if ($tableExists) {
    foreach (['pending','approved','rejected'] as $s) {
        $st = $db->prepare(
            "SELECT COUNT(*) FROM marks_permission_requests mpr
             JOIN teachers t ON t.id = mpr.teacher_id
             WHERE t.wing = ? AND mpr.status = ?"
        );
        $st->execute([$WING, $s]);
        $counts[$s] = (int)$st->fetchColumn();
    }
    $counts['all'] = $counts['pending'] + $counts['approved'] + $counts['rejected'];

    $where  = 't.wing = ?';
    $params = [$WING];
    if (in_array($filter, ['pending','approved','rejected'], true)) {
        $where  .= ' AND mpr.status = ?';
        $params[] = $filter;
    }

    $st = $db->prepare(
        "SELECT mpr.*,
                tu.name  AS teacher_name,
                t.emp_id,
                c.name   AS class_name,
                s.name   AS subject_name,
                a.title  AS assessment_title,
                a.type   AS assessment_type,
                a.total_marks
         FROM marks_permission_requests mpr
         JOIN teachers  t  ON t.id  = mpr.teacher_id
         JOIN users     tu ON tu.id = t.user_id
         JOIN assessments a  ON a.id  = mpr.assessment_id
         JOIN subjects   s  ON s.id  = a.subject_id
         JOIN classes    c  ON c.id  = a.class_id
         WHERE $where
         ORDER BY mpr.created_at DESC
         LIMIT 200"
    );
    $st->execute($params);
    $requests = $st->fetchAll();
}

pageHead('Marks Approval', 'vp_main');
$links = getVpLinks();
?>
<div class="portal-wrap">
<?php sidebar('vp_main', 'marks-approval', $links, $user); ?>
<div class="main-area">
<?php topbar('Marks Entry Approval', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  The marks permission requests table is not yet set up. It will be created automatically on next page load.
</div>
<?php else: ?>

<?php if ($counts['pending'] > 0): ?>
<div class="alert alert-warning d-flex gap-2 align-items-center" style="border-radius:8px;font-size:.87rem">
  <i class="fas fa-clock"></i>
  <strong><?= $counts['pending'] ?></strong>&nbsp;pending marks entry request<?= $counts['pending']>1?'s':'' ?> awaiting your review.
</div>
<?php endif; ?>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-shield-check me-2"></i>Marks Entry Requests — Main Wing</span>
    <div class="btn-group btn-group-sm">
      <a href="?status=pending"  class="btn btn-outline-warning <?= $filter==='pending'?'active':'' ?>">Pending (<?= $counts['pending'] ?>)</a>
      <a href="?status=approved" class="btn btn-outline-success <?= $filter==='approved'?'active':'' ?>">Approved (<?= $counts['approved'] ?>)</a>
      <a href="?status=rejected" class="btn btn-outline-danger  <?= $filter==='rejected'?'active':'' ?>">Rejected (<?= $counts['rejected'] ?>)</a>
      <a href="?status=all"      class="btn btn-outline-secondary <?= $filter==='all'?'active':'' ?>">All (<?= $counts['all'] ?>)</a>
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
          <th>Teacher</th>
          <th>Class</th>
          <th>Subject</th>
          <th>Assessment</th>
          <th>Year</th>
          <th>Requested</th>
          <th>Reason</th>
          <th>Status</th>
          <?php if (in_array($filter, ['pending','all'], true)): ?><th></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $r):
          $statusClass = match($r['status']) { 'approved'=>'success','rejected'=>'danger',default=>'warning' };
        ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= h($r['teacher_name']) ?></div>
            <?php if ($r['emp_id']): ?>
            <div style="font-size:.73rem;color:var(--t3)"><?= h($r['emp_id']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= h($r['class_name']) ?></td>
          <td><?= h($r['subject_name']) ?></td>
          <td>
            <div><?= h($r['assessment_title']) ?></div>
            <div style="font-size:.73rem;color:var(--t3)"><?= h($r['assessment_type']) ?> · <?= h($r['total_marks']) ?> marks</div>
          </td>
          <td><?= h($r['academic_year']) ?></td>
          <td style="white-space:nowrap"><?= fDate($r['created_at']) ?></td>
          <td style="max-width:200px">
            <?php if ($r['request_reason']): ?>
            <span title="<?= h($r['request_reason']) ?>"><?= h(mb_strimwidth($r['request_reason'], 0, 60, '…')) ?></span>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            <?php if ($r['status'] === 'rejected' && $r['rejection_reason']): ?>
            <div style="font-size:.73rem;color:var(--danger,#dc3545)" class="mt-1">
              <i class="fas fa-times-circle me-1"></i><?= h(mb_strimwidth($r['rejection_reason'], 0, 80, '…')) ?>
            </div>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-<?= $statusClass ?>"><?= ucfirst($r['status']) ?></span></td>
          <?php if (in_array($filter, ['pending','all'], true)): ?>
          <td>
            <?php if ($r['status'] === 'pending'): ?>
            <div class="d-flex gap-1">
              <form method="post" onsubmit="return confirm('Approve this request?')">
                <input type="hidden" name="action" value="approve_request">
                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                <button class="btn btn-success btn-sm">Approve</button>
              </form>
              <button class="btn btn-danger btn-sm" onclick="openReject(<?= $r['id'] ?>,<?= htmlspecialchars(json_encode($r['teacher_name'])) ?>)">Reject</button>
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
</div><!-- page-content -->
</div><!-- main-area -->
</div><!-- portal-wrap -->

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="rejectForm">
        <input type="hidden" name="action" value="reject_request">
        <input type="hidden" name="request_id" id="rejectReqId">
        <div class="modal-header">
          <h5 class="modal-title">Reject Request</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2" style="font-size:.87rem">Rejecting request for: <strong id="rejectTeacherName"></strong></p>
          <label class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
          <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Explain why this request is being rejected…"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger btn-sm">Reject</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function openReject(id, name) {
  document.getElementById('rejectReqId').value = id;
  document.getElementById('rejectTeacherName').textContent = name;
  document.querySelector('#rejectModal textarea').value = '';
  new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>
<?php pageFooter(); ?>

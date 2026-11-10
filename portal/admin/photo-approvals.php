<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

// Fetch all pending photos, plus recently processed (last 30 days) for history
$pendingSt = $db->prepare(
    "SELECT u.id, u.user_id, u.name, u.role, u.profile_photo, u.photo_status,
            u.photo_rejection_reason, u.photo_reviewed_at,
            rv.name AS reviewed_by_name
     FROM users u
     LEFT JOIN users rv ON rv.id = u.photo_reviewed_by
     WHERE u.photo_status = 'pending'
     ORDER BY u.id ASC"
);
$pendingSt->execute();
$pending = $pendingSt->fetchAll();

$histSt = $db->prepare(
    "SELECT u.id, u.user_id, u.name, u.role, u.profile_photo, u.photo_status,
            u.photo_rejection_reason, u.photo_reviewed_at,
            rv.name AS reviewed_by_name
     FROM users u
     LEFT JOIN users rv ON rv.id = u.photo_reviewed_by
     WHERE u.photo_status IN ('approved','rejected')
       AND u.photo_reviewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     ORDER BY u.photo_reviewed_at DESC
     LIMIT 50"
);
$histSt->execute();
$history = $histSt->fetchAll();

$roleLabels = [
    'student'         => 'Student',
    'teacher'         => 'Teacher',
    'admin'           => 'Admin',
    'finance'         => 'Finance',
    'ilc_vp'          => 'ILC VP',
    'student_affairs' => 'Student Affairs',
    'vp_main'         => 'VP Main',
    'wing_head'       => 'Wing Head',
];

pageHead('Photo Approvals', 'admin');
$links = getAdminLinks();
?>
<div class="portal-wrap">
<?php sidebar('admin', 'photo-approvals', $links, $user); ?>
<div class="main-area">
<?php topbar('Photo Approvals', $user, count($pending) ? count($pending) . ' pending' : ''); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="d-flex align-items-center gap-3 mb-4">
  <div>
    <h4 style="font-weight:700;margin-bottom:2px">Profile Photo Approvals</h4>
    <p style="font-size:.85rem;color:var(--t2);margin:0">
      Review submitted photos before they appear on user profiles.
      Only photos with a plain white or blue background are accepted.
    </p>
  </div>
</div>

<?php if (empty($pending)): ?>
<div class="sec-card" style="padding:40px;text-align:center">
  <i class="fas fa-check-circle fa-3x" style="color:#10b981;margin-bottom:16px"></i>
  <h5 style="font-weight:700">No pending photos</h5>
  <p style="color:var(--t2);font-size:.88rem">All profile photos have been reviewed. Check back later.</p>
</div>
<?php else: ?>
<div class="sec-card mb-4">
  <div class="sec-card-header">
    <i class="fas fa-clock me-2"></i>
    Pending Review
    <span class="badge bg-warning text-dark ms-2"><?= count($pending) ?></span>
  </div>
  <div style="padding:16px">
    <div class="row g-3">
      <?php foreach ($pending as $p):
        $photoUrl = url('/portal/uploads/profile-photos/' . rawurlencode($p['profile_photo']));
        $roleLabel = $roleLabels[$p['role']] ?? ucfirst($p['role']);
      ?>
      <div class="col-sm-6 col-lg-4 col-xl-3">
        <div class="sec-card" style="padding:0;overflow:hidden;border:1px solid var(--border)">
          <!-- Photo preview -->
          <div style="width:100%;aspect-ratio:1;background:#f1f5f9;display:flex;align-items:center;justify-content:center;overflow:hidden">
            <img src="<?= h($photoUrl) ?>" alt="<?= h($p['name']) ?>"
                 style="width:100%;height:100%;object-fit:cover"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <div style="display:none;flex-direction:column;align-items:center;gap:8px;color:var(--t3)">
              <i class="fas fa-image fa-2x"></i>
              <span style="font-size:.75rem">Preview unavailable</span>
            </div>
          </div>
          <!-- User info -->
          <div style="padding:12px">
            <div style="font-weight:700;font-size:.92rem"><?= h($p['name']) ?></div>
            <div style="font-size:.78rem;color:var(--t2);margin-bottom:10px">
              <?= h($p['user_id']) ?> &bull; <?= h($roleLabel) ?>
            </div>
            <!-- Approve -->
            <form method="POST" action="<?= url('/portal/api/photo-action.php') ?>" class="mb-2">
              <input type="hidden" name="action"  value="approve">
              <input type="hidden" name="user_id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn btn-sm btn-success w-100"
                      onclick="return confirm('Approve this photo for <?= h(addslashes($p['name'])) ?>?')">
                <i class="fas fa-check me-1"></i> Approve
              </button>
            </form>
            <!-- Reject with reason -->
            <form method="POST" action="<?= url('/portal/api/photo-action.php') ?>">
              <input type="hidden" name="action"  value="reject">
              <input type="hidden" name="user_id" value="<?= $p['id'] ?>">
              <div class="input-group input-group-sm">
                <input type="text" name="reason" class="form-control" required
                       placeholder="Rejection reason…"
                       style="font-size:.78rem">
                <button type="submit" class="btn btn-danger"
                        onclick="return this.form.reason.value.trim() || (alert('Enter a rejection reason.'), false)">
                  <i class="fas fa-times"></i>
                </button>
              </div>
              <div style="font-size:.7rem;color:var(--t3);margin-top:3px">
                e.g. "wrong background", "not front-facing", "low quality"
              </div>
            </form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($history)): ?>
<div class="sec-card">
  <div class="sec-card-header" data-bs-toggle="collapse" data-bs-target="#histSection" style="cursor:pointer">
    <i class="fas fa-history me-2"></i>
    Recently Processed (last 30 days)
    <i class="fas fa-chevron-down ms-auto"></i>
  </div>
  <div id="histSection" class="collapse">
    <div class="table-responsive">
      <table class="table table-sm mb-0" style="font-size:.83rem">
        <thead class="table-light">
          <tr>
            <th>Photo</th><th>User</th><th>Role</th><th>Status</th><th>Note</th><th>Reviewed</th><th>By</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $h): ?>
          <tr>
            <td style="width:48px">
              <?php if ($h['photo_status'] === 'approved' && $h['profile_photo']): ?>
              <img src="<?= h(url('/portal/uploads/profile-photos/' . rawurlencode($h['profile_photo']))) ?>"
                   style="width:40px;height:40px;object-fit:cover;border-radius:50%;border:1px solid var(--border)"
                   alt="">
              <?php else: ?>
              <div style="width:40px;height:40px;border-radius:50%;background:var(--border);display:flex;align-items:center;justify-content:center">
                <i class="fas fa-ban" style="font-size:.8rem;color:var(--t3)"></i>
              </div>
              <?php endif; ?>
            </td>
            <td><?= h($h['name']) ?> <span class="text-muted">(<?= h($h['user_id']) ?>)</span></td>
            <td><?= h($roleLabels[$h['role']] ?? $h['role']) ?></td>
            <td>
              <?php if ($h['photo_status'] === 'approved'): ?>
              <span class="badge bg-success">Approved</span>
              <?php else: ?>
              <span class="badge bg-danger">Rejected</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--t2)"><?= $h['photo_rejection_reason'] ? h($h['photo_rejection_reason']) : '—' ?></td>
            <td><?= $h['photo_reviewed_at'] ? date('d M Y', strtotime($h['photo_reviewed_at'])) : '—' ?></td>
            <td><?= h($h['reviewed_by_name'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

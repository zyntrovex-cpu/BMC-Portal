<?php
/**
 * Reusable profile photo upload widget.
 * Include this file inside a portal-authenticated page.
 *
 * Expects: $user (the current session user array), $returnUrl (string).
 * Queries current photo status from DB fresh.
 */
$_pwUserId  = $user['id'] ?? 0;
$_pwReturn  = $returnUrl ?? '/portal/index.php';

// Fetch fresh photo state from DB
$_pwStatus  = 'none';
$_pwReason  = null;
$_pwImgUrl  = null;
try {
    $_pwDb = getDB();
    $_pwSt = $_pwDb->prepare('SELECT profile_photo, photo_status, photo_rejection_reason FROM users WHERE id = ?');
    $_pwSt->execute([$_pwUserId]);
    $_pwRow = $_pwSt->fetch();
    if ($_pwRow) {
        $_pwStatus = $_pwRow['photo_status'];
        $_pwReason = $_pwRow['photo_rejection_reason'];
        if ($_pwRow['profile_photo']) {
            $_pwImgUrl = url('/portal/uploads/profile-photos/' . rawurlencode($_pwRow['profile_photo']));
        }
    }
} catch (Exception $e) {}
?>
<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-camera me-2"></i>Profile Photo</div>
  <div style="padding:20px">

    <?php if ($_pwStatus === 'approved' && $_pwImgUrl): ?>
    <!-- Approved photo -->
    <div class="d-flex align-items-start gap-4 mb-3">
      <img src="<?= h($_pwImgUrl) ?>" alt="Profile Photo"
           style="width:100px;height:100px;object-fit:cover;border-radius:50%;border:3px solid #10b981;flex-shrink:0">
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="badge bg-success"><i class="fas fa-check me-1"></i>Approved</span>
        </div>
        <p style="font-size:.83rem;color:var(--t2);margin:0 0 10px">
          Your photo is live and visible on your profile. You can replace it at any time.
        </p>
      </div>
    </div>

    <?php elseif ($_pwStatus === 'pending' && $_pwImgUrl): ?>
    <!-- Pending review -->
    <div class="d-flex align-items-start gap-4 mb-3">
      <div style="position:relative;flex-shrink:0">
        <img src="<?= h($_pwImgUrl) ?>" alt="Profile Photo"
             style="width:100px;height:100px;object-fit:cover;border-radius:50%;border:3px solid #f59e0b;opacity:.7">
        <div style="position:absolute;bottom:0;right:0;background:#f59e0b;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center">
          <i class="fas fa-clock" style="font-size:.65rem;color:#fff"></i>
        </div>
      </div>
      <div>
        <span class="badge bg-warning text-dark mb-1"><i class="fas fa-clock me-1"></i>Under Review</span>
        <p style="font-size:.83rem;color:var(--t2);margin:0">
          Your photo has been submitted and is awaiting admin approval.
          It won't appear on your profile until approved.
        </p>
      </div>
    </div>

    <?php elseif ($_pwStatus === 'rejected'): ?>
    <!-- Rejected -->
    <div class="alert alert-danger d-flex gap-3 align-items-start" style="font-size:.85rem;border-radius:8px">
      <i class="fas fa-times-circle mt-1"></i>
      <div>
        <strong>Photo rejected</strong>
        <?php if ($_pwReason): ?>
        — <?= h($_pwReason) ?>
        <?php endif; ?>
        <br>Please upload a new photo that meets the requirements below.
      </div>
    </div>

    <?php endif; ?>

    <!-- Requirements notice -->
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:.83rem">
      <div style="font-weight:700;color:#1d4ed8;margin-bottom:6px"><i class="fas fa-info-circle me-1"></i>Photo Requirements</div>
      <p style="margin:0;color:#1e40af;line-height:1.6">
        Photo requirements: front-facing, plain <strong>white or blue</strong> background only.
        No other backgrounds, filters, or side angles.
        Your photo will be reviewed before it appears on your profile.
      </p>
    </div>

    <!-- Upload form -->
    <form method="POST"
          action="<?= url('/portal/api/upload-photo.php') ?>"
          enctype="multipart/form-data">
      <input type="hidden" name="return_url" value="<?= h($_pwReturn) ?>">

      <div class="mb-3">
        <label class="form-label fw-semibold" style="font-size:.85rem">
          <?= ($_pwStatus === 'approved') ? 'Replace Photo' : 'Upload Photo' ?>
        </label>
        <input type="file" name="profile_photo" class="form-control form-control-sm"
               accept=".jpg,.jpeg,.png" required>
        <div style="font-size:.75rem;color:var(--t2);margin-top:4px">
          JPG or PNG only &bull; Max 2 MB &bull; Minimum 300×300 px
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-sm">
        <i class="fas fa-upload me-1"></i>Submit for Review
      </button>
    </form>

  </div>
</div>

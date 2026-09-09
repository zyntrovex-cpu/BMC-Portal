<?php
/**
 * Admin: approve or reject a pending profile photo.
 * POST-only, admin role required.
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$admin = requireAuth('admin');
$db    = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/portal/admin/photo-approvals.php');
}

$action    = $_POST['action']    ?? '';
$targetId  = (int)($_POST['user_id'] ?? 0);
$reason    = trim($_POST['reason'] ?? '');

if (!$targetId || !in_array($action, ['approve', 'reject'], true)) {
    setFlash('danger', 'Invalid request.');
    redirect('/portal/admin/photo-approvals.php');
}

// Fetch the pending photo record
$st = $db->prepare("SELECT id, profile_photo, photo_status FROM users WHERE id = ? AND photo_status = 'pending'");
$st->execute([$targetId]);
$target = $st->fetch();

if (!$target) {
    setFlash('warning', 'Photo not found or already processed.');
    redirect('/portal/admin/photo-approvals.php');
}

if ($action === 'approve') {
    $db->prepare(
        "UPDATE users SET photo_status = 'approved',
         photo_reviewed_by = ?, photo_reviewed_at = NOW(),
         photo_rejection_reason = NULL
         WHERE id = ?"
    )->execute([$admin['id'], $targetId]);

    logActivity($admin['id'], 'photo_approve', "Approved photo for user #{$targetId}");
    setFlash('success', 'Photo approved — it will now appear on the user\'s profile.');

} elseif ($action === 'reject') {
    if (!$reason) {
        setFlash('danger', 'Please provide a rejection reason.');
        redirect('/portal/admin/photo-approvals.php');
    }

    // Delete the actual file
    $photoFile = __DIR__ . '/../uploads/profile-photos/' . $target['profile_photo'];
    if ($target['profile_photo'] && is_file($photoFile)) {
        @unlink($photoFile);
    }

    $db->prepare(
        "UPDATE users SET photo_status = 'rejected', profile_photo = NULL,
         photo_rejection_reason = ?, photo_reviewed_by = ?, photo_reviewed_at = NOW()
         WHERE id = ?"
    )->execute([$reason, $admin['id'], $targetId]);

    logActivity($admin['id'], 'photo_reject', "Rejected photo for user #{$targetId}: {$reason}");
    setFlash('success', 'Photo rejected. The user will see the reason on their profile and can re-upload.');
}

redirect('/portal/admin/photo-approvals.php');

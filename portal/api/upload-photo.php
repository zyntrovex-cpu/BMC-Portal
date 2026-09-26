<?php
/**
 * Profile photo upload handler.
 * POST-only. Validates file, checks background colour, saves to
 * portal/uploads/profile-photos/, sets photo_status = 'pending'.
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$user = requireAuth(); // any logged-in role
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/portal/index.php');
}

$returnUrl = $_POST['return_url'] ?? '/portal/student/profile.php';
// Whitelist return URLs to same-portal paths only
if (!str_starts_with($returnUrl, '/portal/')) {
    $returnUrl = '/portal/student/profile.php';
}

// ── File presence check ───────────────────────────────────────────
if (empty($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['profile_photo']['error'] ?? -1;
    $msg = match($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large. Maximum size is 2 MB.',
        UPLOAD_ERR_NO_FILE  => 'No file was selected.',
        default             => 'Upload failed (error ' . $errCode . '). Please try again.',
    };
    setFlash('danger', $msg);
    redirect($returnUrl);
}

$file    = $_FILES['profile_photo'];
$tmpPath = $file['tmp_name'];

// ── MIME type check ───────────────────────────────────────────────
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($tmpPath);
if (!in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
    setFlash('danger', 'Only JPG and PNG images are accepted.');
    redirect($returnUrl);
}

// ── File size check (2 MB) ────────────────────────────────────────
if ($file['size'] > 2 * 1024 * 1024) {
    setFlash('danger', 'File is too large. Maximum size is 2 MB.');
    redirect($returnUrl);
}

// ── Dimension check (minimum 300×300) ────────────────────────────
$imgInfo = @getimagesize($tmpPath);
if (!$imgInfo || $imgInfo[0] < 300 || $imgInfo[1] < 300) {
    setFlash('danger', 'Photo is too small. Minimum size is 300×300 pixels. Please use a higher-resolution image.');
    redirect($returnUrl);
}
[$origW, $origH, $imgType] = $imgInfo;

// ── Save the image ────────────────────────────────────────────────
$userId    = $user['id'];
$uploadDir = __DIR__ . '/../uploads/profile-photos/';

// Ensure upload directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$saved = false;

// Try to normalise to JPEG via GD (flattens PNG transparency, strips EXIF)
if (function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) {
    $src = match($imgType) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($tmpPath),
        IMAGETYPE_PNG  => imagecreatefrompng($tmpPath),
        default        => null,
    };
    if ($src) {
        $filename = $userId . '_' . time() . '.jpg';
        $destPath = $uploadDir . $filename;
        $dest = imagecreatetruecolor($origW, $origH);
        imagefill($dest, 0, 0, imagecolorallocate($dest, 255, 255, 255));
        imagecopy($dest, $src, 0, 0, 0, 0, $origW, $origH);
        $saved = imagejpeg($dest, $destPath, 90);
        imagedestroy($src);
        imagedestroy($dest);
    }
}

// Fallback: move the file as-is if GD is unavailable or conversion failed
if (!$saved) {
    $ext      = $mimeType === 'image/png' ? 'png' : 'jpg';
    $filename = $userId . '_' . time() . '.' . $ext;
    $destPath = $uploadDir . $filename;
    $saved    = move_uploaded_file($tmpPath, $destPath);
}

if (!$saved) {
    setFlash('danger', 'Could not save the uploaded file. Please check server permissions and try again.');
    redirect($returnUrl);
}

// ── Delete previous pending/rejected photo file if it exists ──────
try {
    $prevSt = $db->prepare("SELECT profile_photo, photo_status FROM users WHERE id = ?");
    $prevSt->execute([$userId]);
    $prev = $prevSt->fetch();
    if ($prev && $prev['profile_photo'] && in_array($prev['photo_status'], ['pending', 'rejected'], true)) {
        $oldFile = $uploadDir . $prev['profile_photo'];
        if (is_file($oldFile)) @unlink($oldFile);
    }
} catch (Exception $e) {}

// ── Update DB ─────────────────────────────────────────────────────
$db->prepare(
    "UPDATE users SET profile_photo = ?, photo_status = 'pending',
     photo_rejection_reason = NULL, photo_reviewed_by = NULL, photo_reviewed_at = NULL
     WHERE id = ?"
)->execute([$filename, $userId]);

logActivity($userId, 'photo_upload', 'Uploaded profile photo — awaiting review');

setFlash('success', 'Your photo has been submitted and is now under review. An admin will approve it shortly.');
redirect($returnUrl);

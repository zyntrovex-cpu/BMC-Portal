<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../config/config.php';

// Any authenticated user may download documents
$user = requireAuth(
    'admin', 'vp_main', 'ilc_vp', 'wing_head', 'finance',
    'student_affairs', 'teacher', 'montessori_teacher', 'ilc_teacher',
    'student'
);

$type     = $_GET['type'] ?? '';   // 'timetable' or 'datesheet'
$id       = (int)($_GET['id'] ?? 0);
$db       = getDB();

if (!$id || !in_array($type, ['timetable', 'datesheet'], true)) {
    http_response_code(400);
    exit('Invalid request.');
}

$storedFilename   = null;
$originalFilename = null;

if ($type === 'timetable') {
    $st = $db->prepare('SELECT stored_filename, original_filename FROM timetable_documents WHERE id=?');
    $st->execute([$id]);
    $row = $st->fetch();
    if ($row) {
        $storedFilename   = $row['stored_filename'];
        $originalFilename = $row['original_filename'];
    }
} elseif ($type === 'datesheet') {
    $st = $db->prepare(
        "SELECT stored_filename, original_filename, status
         FROM exam_date_sheets WHERE id=?"
    );
    $st->execute([$id]);
    $row = $st->fetch();
    if ($row && $row['stored_filename']) {
        // Students only see published sheets
        if ($user['role'] === 'student' && $row['status'] !== 'published') {
            http_response_code(403);
            exit('Document not available.');
        }
        $storedFilename   = $row['stored_filename'];
        $originalFilename = $row['original_filename'];
    }
}

if (!$storedFilename) {
    http_response_code(404);
    exit('Document not found.');
}

$filePath = __DIR__ . '/../../uploads/documents/' . $storedFilename;
if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    exit('File not found on server.');
}

$ext = strtolower(pathinfo($storedFilename, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf'  => 'application/pdf',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls'  => 'application/vnd.ms-excel',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc'  => 'application/msword',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . addslashes($originalFilename) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0');
readfile($filePath);
exit;

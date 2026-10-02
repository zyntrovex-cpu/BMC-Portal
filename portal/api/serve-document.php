<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../config/config.php';

// Any authenticated user may download documents
$user = requireAuth(
    'admin', 'vp_main', 'ilc_vp', 'wing_head', 'finance',
    'student_affairs', 'teacher', 'montessori_teacher', 'ilc_teacher',
    'student', 'examination_head'
);

$type     = $_GET['type'] ?? '';   // 'timetable', 'datesheet', or 'syllabus'
$id       = (int)($_GET['id'] ?? 0);
$db       = getDB();

if (!$id || !in_array($type, ['timetable', 'datesheet', 'syllabus', 'admission_attachment'], true)) {
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
} elseif ($type === 'admission_attachment') {
    try {
        $st = $db->prepare(
            "SELECT ara.stored_filename, ara.original_filename, ara.request_id
             FROM admission_request_attachments ara WHERE ara.id=?"
        );
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) {
            if ($user['role'] === 'ilc_vp') {
                $chk = $db->prepare('SELECT id FROM admission_requests WHERE id=? AND requested_by=?');
                $chk->execute([$row['request_id'], $user['id']]);
                if (!$chk->fetch()) { http_response_code(403); exit('Access denied.'); }
            } elseif (!in_array($user['role'], ['student_affairs', 'admin'], true)) {
                http_response_code(403); exit('Access denied.');
            }
            $storedFilename   = $row['stored_filename'];
            $originalFilename = $row['original_filename'];
        }
    } catch (Exception $e) {
        http_response_code(404); exit('Attachment table not available.');
    }
} elseif ($type === 'syllabus') {
    try {
        $st = $db->prepare('SELECT sd.stored_filename, sd.original_filename, sd.class_id FROM syllabus_documents sd WHERE sd.id=?');
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) {
            // Students may only download syllabus for their own class
            if ($user['role'] === 'student') {
                $stStu = $db->prepare('SELECT class_id FROM students WHERE user_id=?');
                $stStu->execute([$user['id']]);
                $stuRow = $stStu->fetch();
                if (!$stuRow || ($row['class_id'] !== null && (int)$stuRow['class_id'] !== (int)$row['class_id'])) {
                    http_response_code(403);
                    exit('Document not available for your class.');
                }
            }
            $storedFilename   = $row['stored_filename'];
            $originalFilename = $row['original_filename'];
        }
    } catch (Exception $e) {
        http_response_code(404);
        exit('Syllabus documents table not available.');
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

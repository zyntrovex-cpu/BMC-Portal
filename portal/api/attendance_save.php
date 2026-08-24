<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

$user = requireAuth('teacher', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$data      = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$classId   = (int)($data['class_id']   ?? 0);
$subjectId = (int)($data['subject_id'] ?? 0);
$date      = $data['date'] ?? date('Y-m-d');
$records   = $data['attendance'] ?? [];

if (!$classId || !$subjectId || empty($records)) {
    jsonResponse(['error' => 'class_id, subject_id and attendance array required'], 400);
}

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    jsonResponse(['error' => 'Invalid date format'], 400);
}

$db = getDB();

// Get teacher id
$teacherSt = $db->prepare('SELECT id FROM teachers WHERE user_id = ?');
$teacherSt->execute([$user['id']]);
$teacher   = $teacherSt->fetch();
$teacherId = $teacher ? $teacher['id'] : null;

$saved = 0;
foreach ($records as $studentId => $status) {
    if (!in_array($status, ['P', 'A', 'L'])) continue;
    $db->prepare(
        'INSERT INTO attendance (student_id, subject_id, date, status, teacher_id)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status), teacher_id = VALUES(teacher_id)'
    )->execute([(int)$studentId, $subjectId, $date, $status, $teacherId]);
    $saved++;
}

logActivity($user['id'], 'attendance_save', "Attendance for class #$classId, subject #$subjectId, date $date ($saved records)");

jsonResponse(['success' => true, 'saved' => $saved]);

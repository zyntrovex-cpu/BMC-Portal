<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$user = requireAuth('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$data      = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action    = $data['action']    ?? 'save';
$classId   = (int)($data['class_id']   ?? 0);
$subjectId = (int)($data['subject_id'] ?? 0);
$teacherId = (int)($data['teacher_id'] ?? 0);
$day       = strtolower($data['day']   ?? '');
$period    = (int)($data['period']     ?? 0);
$room      = trim($data['room']        ?? '');

$validDays = ['monday','tuesday','wednesday','thursday','friday'];

if ($action === 'delete') {
    if (!$classId || !$day || !$period) jsonResponse(['error' => 'class_id, day, period required'], 400);
    if (!in_array($day, $validDays)) jsonResponse(['error' => 'Invalid day'], 400);
    getDB()->prepare('DELETE FROM timetable WHERE class_id = ? AND day = ? AND period = ?')
           ->execute([$classId, $day, $period]);
    logActivity($user['id'], 'timetable_delete', "Cleared $day P$period for class #$classId");
    jsonResponse(['success' => true]);
}

if (!$classId || !$subjectId || !$day || !$period) {
    jsonResponse(['error' => 'class_id, subject_id, day and period required'], 400);
}
if (!in_array($day, $validDays)) jsonResponse(['error' => 'Invalid day'], 400);
if ($period < 1 || $period > 8) jsonResponse(['error' => 'Period must be 1-8'], 400);

$db = getDB();
$db->prepare(
    'INSERT INTO timetable (class_id, subject_id, teacher_id, day, period, room)
     VALUES (?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE subject_id=VALUES(subject_id), teacher_id=VALUES(teacher_id), room=VALUES(room)'
)->execute([$classId, $subjectId, $teacherId ?: null, $day, $period, $room]);

logActivity($user['id'], 'timetable_save', "Set timetable: class #$classId $day P$period");
jsonResponse(['success' => true]);

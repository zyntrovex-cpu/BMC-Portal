<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

$user = requireAuth('teacher', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$data   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $data['action'] ?? 'create';
$db     = getDB();

// Get teacher id for this user
$teacherSt = $db->prepare('SELECT id FROM teachers WHERE user_id = ?');
$teacherSt->execute([$user['id']]);
$teacher   = $teacherSt->fetch();

if ($action === 'delete') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'id required'], 400);

    // Verify ownership
    if ($user['role'] === 'teacher' && $teacher) {
        $check = $db->prepare('SELECT id FROM assessments WHERE id = ? AND teacher_id = ?');
        $check->execute([$id, $teacher['id']]);
        if (!$check->fetch()) jsonResponse(['error' => 'Unauthorized'], 403);
    }

    $db->prepare('DELETE FROM marks WHERE assessment_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM assessments WHERE id = ?')->execute([$id]);
    logActivity($user['id'], 'assessment_delete', "Deleted assessment #$id");
    jsonResponse(['success' => true]);
}

$classId   = (int)($data['class_id']   ?? 0);
$subjectId = (int)($data['subject_id'] ?? 0);
$title     = trim($data['title']    ?? '');
$type      = $data['type']          ?? 'Quiz';
$maxMarks  = (float)($data['max_marks'] ?? 0);
$weight    = (float)($data['weight']    ?? 0);
$date      = $data['date'] ?? date('Y-m-d');

$validTypes = ['Quiz','Assignment','Mid Term','Final Term','Practical'];
if (!in_array($type, $validTypes)) $type = 'Quiz';

if (!$classId || !$subjectId || !$title || !$maxMarks) {
    jsonResponse(['error' => 'class_id, subject_id, title and max_marks required'], 400);
}

$teacherId = $teacher ? $teacher['id'] : null;

$id = (int)($data['id'] ?? 0);
if ($action === 'update' && $id) {
    $db->prepare(
        'UPDATE assessments SET class_id=?, subject_id=?, title=?, type=?, max_marks=?, weight=?, date=? WHERE id=?'
    )->execute([$classId, $subjectId, $title, $type, $maxMarks, $weight, $date, $id]);
    logActivity($user['id'], 'assessment_update', "Updated assessment #$id: $title");
    jsonResponse(['success' => true, 'id' => $id]);
} else {
    $db->prepare(
        'INSERT INTO assessments (class_id, subject_id, teacher_id, title, type, max_marks, weight, date)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([$classId, $subjectId, $teacherId, $title, $type, $maxMarks, $weight, $date]);
    $newId = (int)$db->lastInsertId();
    logActivity($user['id'], 'assessment_create', "Created assessment #$newId: $title");
    jsonResponse(['success' => true, 'id' => $newId]);
}

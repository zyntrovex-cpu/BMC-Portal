<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

$user = requireAuth('teacher', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$data         = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$assessmentId = (int)($data['assessment_id'] ?? 0);
$marksData    = $data['marks'] ?? [];

if (!$assessmentId || empty($marksData)) {
    jsonResponse(['error' => 'assessment_id and marks array required'], 400);
}

$db = getDB();

// Verify assessment belongs to this teacher (if teacher role)
if ($user['role'] === 'teacher') {
    $st = $db->prepare('SELECT id FROM assessments a JOIN teachers t ON a.teacher_id = t.id WHERE a.id = ? AND t.user_id = ?');
    $st->execute([$assessmentId, $user['id']]);
    if (!$st->fetch()) jsonResponse(['error' => 'Unauthorized'], 403);
}

$saved = 0;
foreach ($marksData as $studentId => $row) {
    $obtained = ($row['marks_obtained'] !== '' && $row['marks_obtained'] !== null)
                ? (float)$row['marks_obtained'] : null;
    $remarks  = trim($row['remarks'] ?? '');

    if ($obtained === null) continue;

    $db->prepare(
        'INSERT INTO marks (assessment_id, student_id, marks_obtained, remarks)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE marks_obtained = VALUES(marks_obtained), remarks = VALUES(remarks)'
    )->execute([$assessmentId, (int)$studentId, $obtained, $remarks]);
    $saved++;
}

logActivity($user['id'], 'marks_save', "Saved marks for assessment #$assessmentId ($saved students)");

jsonResponse(['success' => true, 'saved' => $saved]);

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireAuth('teacher', 'admin', 'examination_head');

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

// Get teacher record
$teacherSt = $db->prepare('SELECT id FROM teachers WHERE user_id = ?');
$teacherSt->execute([$user['id']]);
$teacherRow = $teacherSt->fetch();

// Verify assessment belongs to this teacher (if teacher role)
if (in_array($user['role'], ['teacher','montessori_teacher','ilc_teacher','examination_head'], true)) {
    $st = $db->prepare('SELECT id FROM assessments a JOIN teachers t ON a.teacher_id = t.id WHERE a.id = ? AND t.user_id = ?');
    $st->execute([$assessmentId, $user['id']]);
    if (!$st->fetch()) jsonResponse(['error' => 'Unauthorized'], 403);

    // Backend marks-entry permission check (skipped for examination_head — they have admin-level authority)
    if ($teacherRow && $user['role'] !== 'examination_head') {
        try {
            $permSt = $db->prepare(
                "SELECT id FROM marks_permission_requests
                 WHERE teacher_id=? AND assessment_id=? AND status='approved'"
            );
            $permSt->execute([$teacherRow['id'], $assessmentId]);
            if (!$permSt->fetch()) {
                jsonResponse(['error' => 'Marks entry permission not approved for this assessment. Please request permission first.'], 403);
            }
        } catch (Exception $e) {
            // Table not created yet — allow (graceful degradation during deployment)
        }
    }
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

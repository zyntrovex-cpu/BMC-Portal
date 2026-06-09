<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$user = requireAuth('student', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$data   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $data['action'] ?? 'request';
$db     = getDB();

if ($action === 'approve' || $action === 'reject') {
    requireAuth('admin'); // Only admin can approve/reject
    $requestId = (int)($data['request_id'] ?? 0);
    if (!$requestId) jsonResponse(['error' => 'request_id required'], 400);

    $st = $db->prepare('SELECT * FROM profile_change_requests WHERE id = ?');
    $st->execute([$requestId]);
    $req = $st->fetch();
    if (!$req) jsonResponse(['error' => 'Request not found'], 404);

    if ($action === 'approve') {
        $allowedFields = ['phone','address','parent_name','parent_phone'];
        if (!in_array($req['field'], $allowedFields)) {
            jsonResponse(['error' => 'Invalid field'], 400);
        }
        $db->prepare("UPDATE students SET {$req['field']} = ? WHERE id = ?")
           ->execute([$req['new_value'], $req['student_id']]);
        $db->prepare("UPDATE profile_change_requests SET status = 'approved' WHERE id = ?")
           ->execute([$requestId]);
        logActivity($user['id'], 'profile_approve', "Approved profile change #$requestId");
    } else {
        $db->prepare("UPDATE profile_change_requests SET status = 'rejected' WHERE id = ?")
           ->execute([$requestId]);
        logActivity($user['id'], 'profile_reject', "Rejected profile change #$requestId");
    }

    jsonResponse(['success' => true]);
}

// Student making a change request
if ($user['role'] !== 'student') jsonResponse(['error' => 'Only students can request changes'], 403);

$st = $db->prepare('SELECT id FROM students WHERE user_id = ?');
$st->execute([$user['id']]);
$student = $st->fetch();
if (!$student) jsonResponse(['error' => 'Student record not found'], 404);

$studentId  = $student['id'];
$fieldName  = $data['field_name']  ?? '';
$newValue   = trim($data['new_value'] ?? '');

$allowedFields = ['phone','address','parent_name','parent_phone'];
if (!in_array($fieldName, $allowedFields)) {
    jsonResponse(['error' => 'Field not allowed for edit'], 400);
}
if (!$newValue) {
    jsonResponse(['error' => 'new_value required'], 400);
}

// Get old value
$st = $db->prepare("SELECT $fieldName FROM students WHERE id = ?");
$st->execute([$studentId]);
$row = $st->fetch();
$oldValue = $row[$fieldName] ?? '';

// Check no pending request for same field
$st = $db->prepare("SELECT id FROM profile_change_requests WHERE student_id = ? AND field = ? AND status = 'pending'");
$st->execute([$studentId, $fieldName]);
if ($st->fetch()) {
    jsonResponse(['error' => 'A pending request for this field already exists'], 409);
}

$db->prepare(
    'INSERT INTO profile_change_requests (student_id, field, old_value, new_value, status) VALUES (?,?,?,?,?)'
)->execute([$studentId, $fieldName, $oldValue, $newValue, 'pending']);

logActivity($user['id'], 'profile_change_request', "Requested change: $fieldName");

jsonResponse(['success' => true, 'message' => 'Change request submitted for admin approval']);

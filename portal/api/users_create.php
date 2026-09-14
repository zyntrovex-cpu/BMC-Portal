<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

$user = requireAuth('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$data     = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action   = $data['action'] ?? 'create';

$db = getDB();

if ($action === 'toggle_status') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'id required'], 400);
    $db->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id = ?")->execute([$id]);
    jsonResponse(['success' => true]);
}

if ($action === 'delete') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'id required'], 400);
    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    logActivity($user['id'], 'user_delete', "Deleted user #$id");
    jsonResponse(['success' => true]);
}

// Create user
$name     = trim($data['name']    ?? '');
$userId   = trim($data['user_id'] ?? '');
$email    = trim($data['email']   ?? '');
$password = trim($data['password'] ?? '');
$role     = $data['role']   ?? '';
$status   = $data['status'] ?? 'active';

if (!$name || !$userId || !$password || !$role) {
    jsonResponse(['error' => 'name, user_id, password and role are required'], 400);
}

$validRoles = ['student','teacher','admin','finance'];
if (!in_array($role, $validRoles)) jsonResponse(['error' => 'Invalid role'], 400);

// Check if user_id already exists
$existing = $db->prepare('SELECT id FROM users WHERE user_id = ?');
$existing->execute([$userId]);
if ($existing->fetch()) jsonResponse(['error' => 'User ID already exists'], 409);

$hash = password_hash($password, PASSWORD_BCRYPT);

try {
    $db->prepare(
        'INSERT INTO users (user_id, name, email, password, role, status) VALUES (?,?,?,?,?,?)'
    )->execute([$userId, $name, $email ?: null, $hash, $role, $status]);
    $newUserId = (int)$db->lastInsertId();

    // Create role-specific record
    if ($role === 'student') {
        $classId = (int)($data['class_id'] ?? 0);
        $rollNo  = $data['roll_no'] ?? $userId;
        $db->prepare(
            'INSERT INTO students (user_id, roll_no, class_id, admission_date) VALUES (?,?,?,CURDATE())'
        )->execute([$newUserId, $rollNo, $classId ?: null]);
    } elseif ($role === 'teacher') {
        $subjectId     = (int)($data['subject_id'] ?? 0);
        $qualification = trim($data['qualification'] ?? '');
        $empId         = $userId;
        $db->prepare(
            'INSERT INTO teachers (user_id, emp_id, subject_id, qualification) VALUES (?,?,?,?)'
        )->execute([$newUserId, $empId, $subjectId ?: null, $qualification]);
    }

    logActivity($user['id'], 'user_create', "Created user $userId ($role)");
    jsonResponse(['success' => true, 'id' => $newUserId]);
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}

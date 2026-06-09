<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true) ?? [];
$userId   = trim($data['user_id']  ?? $_POST['user_id']  ?? '');
$password = trim($data['password'] ?? $_POST['password'] ?? '');
$role     = trim($data['role']     ?? $_POST['role']     ?? '');

if (!$userId || !$password || !$role) {
    http_response_code(400);
    echo json_encode(['error' => 'user_id, password and role are required']);
    exit;
}

$db = getDB();
$st = $db->prepare('SELECT * FROM users WHERE user_id = ? AND role = ? AND status = "active"');
$st->execute([$userId, $role]);
$user = $st->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
    exit;
}

$db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

$_SESSION['user'] = [
    'id'      => $user['id'],
    'user_id' => $user['user_id'],
    'name'    => $user['name'],
    'role'    => $user['role'],
    'email'   => $user['email'] ?? '',
];

$map = ['student'=>'/student/dashboard.php','teacher'=>'/teacher/dashboard.php','admin'=>'/admin/dashboard.php','finance'=>'/finance/dashboard.php'];

echo json_encode([
    'success'  => true,
    'user'     => ['id'=>$user['id'],'name'=>$user['name'],'role'=>$user['role'],'user_id'=>$user['user_id']],
    'redirect' => $map[$user['role']] ?? '/',
]);

<?php
require_once __DIR__ . '/../../config/db.php';
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

session_regenerate_id(true);   // Prevent session fixation
$_SESSION['user'] = [
    'id'      => $user['id'],
    'user_id' => $user['user_id'],
    'name'    => $user['name'],
    'role'    => $user['role'],
    'email'   => $user['email'] ?? '',
];

$map = [
    'student'            => '/portal/student/dashboard.php',
    'teacher'            => '/portal/teacher/dashboard.php',
    'montessori_teacher' => '/portal/teacher/dashboard.php',
    'ilc_teacher'        => '/portal/teacher/dashboard.php',
    'admin'              => '/portal/admin/dashboard.php',
    'finance'            => '/portal/finance/dashboard.php',
    'ilc_vp'             => '/portal/ilc/dashboard.php',
    'student_affairs'    => '/portal/student-affairs/dashboard.php',
    'vp_main'            => '/portal/vp/dashboard.php',
    'wing_head'          => '/portal/wing-head/dashboard.php',
    'examination_head'   => '/portal/exam-head/dashboard.php',
];

echo json_encode([
    'success'  => true,
    'user'     => ['id'=>$user['id'],'name'=>$user['name'],'role'=>$user['role'],'user_id'=>$user['user_id']],
    'redirect' => $map[$user['role']] ?? '/',
]);

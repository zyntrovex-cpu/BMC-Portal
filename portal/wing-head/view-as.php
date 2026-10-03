<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('wing_head');
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['target_id'])) {
    $targetId  = (int)$_POST['target_id'];
    $returnUrl = $_POST['return_url'] ?? '';

    $st = $db->prepare(
        'SELECT id, user_id, name, email, role, status
         FROM users WHERE id = ? AND role = "montessori_teacher" AND status = "active"'
    );
    $st->execute([$targetId]);
    $target = $st->fetch();

    if ($target) {
        $_SESSION['admin_backup']   = $_SESSION['user'];
        $_SESSION['view_as_mode']   = true;
        $_SESSION['view_as_return'] = $returnUrl ?: (BASE_URL . '/portal/wing-head/teachers.php');
        $_SESSION['user']           = $target;

        header('Location: ' . BASE_URL . '/portal/teacher/dashboard.php');
        exit;
    }
    setFlash('danger', 'Teacher not found or not accessible.');
}

redirect('/portal/wing-head/teachers.php');

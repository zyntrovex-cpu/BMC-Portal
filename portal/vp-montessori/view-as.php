<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('vp_montessori');
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['target_id'])) {
    $targetId  = (int)$_POST['target_id'];
    $returnUrl = $_POST['return_url'] ?? '';

    // VP Montessori may View-As any Montessori staff member:
    //   wing_head          (Coordinator)
    //   montessori_teacher (Teacher)
    //   teacher with teachers.wing='montessori' (legacy / un-migrated role)
    $st = $db->prepare(
        "SELECT u.id, u.user_id, u.name, u.email, u.role, u.status
           FROM users u
           LEFT JOIN teachers t ON t.user_id = u.id
          WHERE u.id = ?
            AND COALESCE(u.status,'active') = 'active'
            AND (u.role IN ('montessori_teacher', 'wing_head')
                 OR (u.role = 'teacher' AND t.wing = 'montessori'))
          LIMIT 1"
    );
    $st->execute([$targetId]);
    $target = $st->fetch();

    if ($target) {
        $_SESSION['admin_backup']   = $_SESSION['user'];
        $_SESSION['view_as_mode']   = true;
        $_SESSION['view_as_return'] = $returnUrl ?: (BASE_URL . '/portal/vp-montessori/dashboard.php');
        $_SESSION['user']           = $target;

        // Route to the correct dashboard for the target role so the sidebar
        // and nav match what that user normally sees.
        $dest = match ($target['role']) {
            'wing_head'          => '/portal/wing-head/dashboard.php',
            'montessori_teacher' => '/portal/teacher/dashboard.php',
            default              => '/portal/teacher/dashboard.php',
        };
        header('Location: ' . BASE_URL . $dest);
        exit;
    }
    setFlash('danger', 'Target staff member not found or not accessible.');
}

redirect('/portal/vp-montessori/dashboard.php');

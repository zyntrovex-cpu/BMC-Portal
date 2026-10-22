<?php
/**
 * Student search API — returns JSON for the warnings student-picker.
 * GET ?q=search_term  (min 2 chars)
 * Accessible to any staff role that can issue warnings.
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Any authenticated staff role may call this
$allowedRoles = ['admin','teacher','montessori_teacher','ilc_teacher','vp_main','wing_head','student_affairs'];
$sess = $_SESSION['user'] ?? [];
if (empty($sess['role']) || !in_array($sess['role'], $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$db   = getDB();
$like = '%' . $q . '%';

// Build active-student filter (graceful for missing columns)
$extraWhere = '';
try { $db->query('SELECT deleted_at FROM students LIMIT 0');   $extraWhere .= ' AND s.deleted_at IS NULL';   } catch (Exception $e) {}
try { $db->query('SELECT graduated_at FROM students LIMIT 0'); $extraWhere .= ' AND s.graduated_at IS NULL'; } catch (Exception $e) {}

try {
    $st = $db->prepare(
        "SELECT s.id, s.roll_no, u.name, u.user_id AS login_id,
                c.name AS class_name, h.name AS house_name, h.color AS house_color
         FROM students s
         JOIN users u    ON s.user_id  = u.id
         LEFT JOIN classes c ON s.class_id = c.id
         LEFT JOIN houses  h ON s.house_id  = h.id
         WHERE (u.name LIKE ? OR s.roll_no LIKE ? OR u.user_id LIKE ?)
           AND u.status = 'active'
           $extraWhere
         ORDER BY u.name
         LIMIT 20"
    );
    $st->execute([$like, $like, $like]);
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed']);
}

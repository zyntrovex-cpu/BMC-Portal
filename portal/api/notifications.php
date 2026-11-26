<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireAuth('teacher','montessori_teacher','ilc_teacher','vp_main','wing_head','ilc_vp','admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$data   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $data['action'] ?? '';
$db     = getDB();

if ($action === 'read_all') {
    try {
        $db->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$user['id']]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed'], 500);
    }
} elseif ($action === 'read_one') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'id required'], 400);
    try {
        $db->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([$id, $user['id']]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed'], 500);
    }
} else {
    jsonResponse(['error' => 'Unknown action'], 400);
}

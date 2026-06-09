<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$user = requireAuth('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$data     = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action   = $data['action'] ?? 'create';
$id       = (int)($data['id'] ?? 0);

if ($action === 'delete') {
    if (!$id) jsonResponse(['error' => 'id required'], 400);
    getDB()->prepare('DELETE FROM notices WHERE id = ?')->execute([$id]);
    logActivity($user['id'], 'notice_delete', "Deleted notice #$id");
    jsonResponse(['success' => true]);
}

if ($action === 'toggle_pin') {
    if (!$id) jsonResponse(['error' => 'id required'], 400);
    getDB()->prepare('UPDATE notices SET pinned = !pinned WHERE id = ?')->execute([$id]);
    jsonResponse(['success' => true]);
}

// create or update
$title      = trim($data['title']    ?? '');
$body       = trim($data['body']     ?? '');
$category   = $data['category']      ?? 'General';
$priority   = $data['priority']      ?? 'Normal';
$audience   = $data['audience']      ?? [];
$expiryDate = !empty($data['expiry_date']) ? $data['expiry_date'] : null;
$pinned     = isset($data['pinned']) ? 1 : 0;

if (!$title || !$body) {
    jsonResponse(['error' => 'title and body required'], 400);
}

$validCategories = ['General','Academic','Exam','Holiday','Finance','Emergency'];
$validPriorities = ['Normal','Important','Urgent'];
if (!in_array($category, $validCategories)) $category = 'General';
if (!in_array($priority, $validPriorities)) $priority = 'Normal';

$audienceStr = is_array($audience) ? implode(',', array_filter($audience, fn($a) => in_array($a, ['students','teachers','finance','admin']))) : '';

$db = getDB();

if ($action === 'update' && $id) {
    $db->prepare(
        'UPDATE notices SET title=?, body=?, category=?, priority=?, audience=?, expiry_date=?, pinned=? WHERE id=?'
    )->execute([$title, $body, $category, $priority, $audienceStr, $expiryDate, $pinned, $id]);
    logActivity($user['id'], 'notice_update', "Updated notice #$id: $title");
    jsonResponse(['success' => true, 'id' => $id]);
} else {
    $db->prepare(
        'INSERT INTO notices (title, body, category, priority, audience, expiry_date, pinned, author_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$title, $body, $category, $priority, $audienceStr, $expiryDate, $pinned, $user['id']]);
    $newId = (int)$db->lastInsertId();
    logActivity($user['id'], 'notice_create', "Created notice #$newId: $title");
    jsonResponse(['success' => true, 'id' => $newId]);
}

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$user = requireAuth('finance', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$data    = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$month   = (int)($data['month'] ?? 0);
$year    = (int)($data['year']  ?? 0);
$fees    = $data['fees'] ?? [];

if (!$month || !$year || empty($fees)) {
    jsonResponse(['error' => 'month, year and fees array required'], 400);
}

$db    = getDB();
$saved = 0;

foreach ($fees as $studentId => $row) {
    $paid     = isset($row['paid']) ? 1 : 0;
    $paidDate = ($paid && !empty($row['paid_date'])) ? $row['paid_date'] : ($paid ? date('Y-m-d') : null);
    $mode     = in_array($row['mode'] ?? '', ['Cash','Bank','Online','Cheque']) ? $row['mode'] : 'Cash';
    $amount   = (float)($row['amount'] ?? 0);
    $remarks  = trim($row['remarks'] ?? '');

    $db->prepare(
        'INSERT INTO fees (student_id, month, year, amount, paid, payment_date, payment_mode, remarks)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE paid=VALUES(paid), payment_date=VALUES(payment_date),
         payment_mode=VALUES(payment_mode), amount=VALUES(amount), remarks=VALUES(remarks)'
    )->execute([(int)$studentId, $month, $year, $amount, $paid, $paidDate, $mode, $remarks]);
    $saved++;
}

logActivity($user['id'], 'fees_save', "Fee records saved for {$month}/{$year} ($saved students)");

jsonResponse(['success' => true, 'saved' => $saved]);

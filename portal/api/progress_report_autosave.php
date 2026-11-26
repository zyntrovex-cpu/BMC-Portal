<?php
/**
 * Progress Report autosave endpoint.
 *
 * Teachers / VPs / wing_heads POST the current form state while editing a
 * progress report; we upsert a `draft` row keyed by
 *   (student_id, term, session, reported_by)
 * so there is AT MOST ONE draft per teacher-student-term combination — no
 * duplicates, even if the autosave timer fires many times.
 *
 * A row is considered a DRAFT when progress_reports.status = 'draft'. When
 * the teacher clicks Submit on the form, the normal POST handler in
 * progress-report/form.php runs INSERT/UPDATE with status='submitted'
 * (handled separately), so autosave never promotes a draft to final.
 *
 * Request: POST JSON { student_id, term, session, basic{...}, subjects[...],
 *                       remarks, narrative, result_id? }
 * Response: { ok: true, id: <row id>, saved_at: 'YYYY-MM-DD HH:MM:SS' }
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = requireAuth('montessori_teacher', 'teacher', 'vp_main', 'wing_head', 'vp_montessori');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) $body = $_POST;

$studentId = (int)($body['student_id'] ?? 0);
if ($studentId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'student_id required']);
    exit;
}

$db = getDB();

// Confirm the teacher is authorised to touch this student. We reuse the
// permission check the full form already enforces — a direct join against
// class_subjects/students-via-teacher is the cheapest path.
try {
    $allowed = false;
    if (in_array($user['role'], ['vp_main', 'vp_montessori', 'wing_head'], true)) {
        // VPs / wing heads can autosave for any student in their scope — the
        // full form.php check runs on submit.
        $allowed = true;
    } else {
        $teacher = getTeacherByUserId($user['id']);
        if ($teacher) {
            $chk = $db->prepare(
                'SELECT 1 FROM students s
                   JOIN class_subjects cs ON cs.class_id = s.class_id
                  WHERE s.id = ? AND cs.teacher_id = ? LIMIT 1'
            );
            $chk->execute([$studentId, (int)$teacher['id']]);
            $allowed = (bool)$chk->fetch();
        }
    }
    if (!$allowed) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Not authorised for this student']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Authorisation check failed']);
    exit;
}

// Build form_data in the exact same shape as form.php's manual save path.
$basic = [
    'term'          => trim((string)($body['basic']['term']          ?? $body['basic_term']          ?? 'Final Term')),
    'session'       => trim((string)($body['basic']['session']       ?? $body['basic_session']       ?? '')),
    'attendance'    => trim((string)($body['basic']['attendance']    ?? $body['basic_attendance']    ?? '')),
    'date_of_issue' => trim((string)($body['basic']['date_of_issue'] ?? $body['basic_date_of_issue'] ?? '')),
];
$subjects  = $body['subjects'] ?? [];
if (is_string($subjects)) { $subjects = json_decode($subjects, true) ?: []; }
if (!is_array($subjects)) $subjects = [];
$remarks   = trim((string)($body['remarks']   ?? ''));
$narrative = trim((string)($body['narrative'] ?? ''));

$fd   = ['basic' => $basic, 'subjects' => $subjects, 'remarks' => $remarks, 'narrative' => $narrative];
$json = json_encode($fd, JSON_UNESCAPED_UNICODE);
$term = $basic['term']; $session = $basic['session'];

// Does the DB have the status column? Fall back to a non-status save so the
// page still works pre-migration.
$hasStatus = false;
try { $db->query('SELECT status FROM progress_reports LIMIT 0'); $hasStatus = true; } catch (Exception $e) {}

try {
    // Prefer updating the exact row the client is editing (result_id).
    $resultId = (int)($body['result_id'] ?? 0);
    if ($resultId > 0) {
        if ($hasStatus) {
            $db->prepare(
                "UPDATE progress_reports
                    SET term=?, session=?, form_data=?, reported_by=?, status='draft', updated_at=NOW()
                  WHERE id=? AND student_id=? AND reported_by=? AND status='draft'"
            )->execute([$term, $session, $json, $user['id'], $resultId, $studentId, $user['id']]);
        }
        // If nothing was updated (wrong row, or already submitted) fall through
        // to the upsert below.
    }

    // Find-or-create the per (student, term, session, author) DRAFT row.
    if ($hasStatus) {
        $find = $db->prepare(
            "SELECT id FROM progress_reports
              WHERE student_id=? AND term=? AND COALESCE(session,'')=? AND reported_by=? AND status='draft'
              LIMIT 1"
        );
        $find->execute([$studentId, $term, $session, $user['id']]);
    } else {
        $find = $db->prepare(
            "SELECT id FROM progress_reports
              WHERE student_id=? AND term=? AND COALESCE(session,'')=? AND reported_by=?
              ORDER BY id DESC LIMIT 1"
        );
        $find->execute([$studentId, $term, $session, $user['id']]);
    }
    $existingId = (int)($find->fetchColumn() ?: 0);

    if ($existingId > 0) {
        if ($hasStatus) {
            $db->prepare(
                "UPDATE progress_reports
                    SET form_data=?, status='draft', updated_at=NOW()
                  WHERE id=?"
            )->execute([$json, $existingId]);
        } else {
            $db->prepare(
                "UPDATE progress_reports SET form_data=?, updated_at=NOW() WHERE id=?"
            )->execute([$json, $existingId]);
        }
        $savedId = $existingId;
    } else {
        if ($hasStatus) {
            $db->prepare(
                "INSERT INTO progress_reports (student_id, term, session, form_data, reported_by, status)
                 VALUES (?,?,?,?,?,'draft')"
            )->execute([$studentId, $term, $session, $json, $user['id']]);
        } else {
            $db->prepare(
                'INSERT INTO progress_reports (student_id, term, session, form_data, reported_by)
                 VALUES (?,?,?,?,?)'
            )->execute([$studentId, $term, $session, $json, $user['id']]);
        }
        $savedId = (int)$db->lastInsertId();
    }

    echo json_encode([
        'ok'       => true,
        'id'       => $savedId,
        'status'   => 'draft',
        'saved_at' => date('Y-m-d H:i:s'),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Save failed: ' . $e->getMessage()]);
}

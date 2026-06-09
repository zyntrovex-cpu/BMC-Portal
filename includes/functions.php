<?php
require_once __DIR__ . '/../config/db.php';

// ── HTML escape shorthand ────────────────────────────────────────
function h(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// ── Redirect helper ──────────────────────────────────────────────
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

// ── JSON response for API endpoints ─────────────────────────────
function jsonResponse(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// ── Get all classes ──────────────────────────────────────────────
function getAllClasses(): array {
    return getDB()->query('SELECT * FROM classes ORDER BY grade, section')->fetchAll();
}

// ── Get all subjects ─────────────────────────────────────────────
function getAllSubjects(): array {
    return getDB()->query('SELECT * FROM subjects ORDER BY name')->fetchAll();
}

// ── Get subjects for a class ─────────────────────────────────────
function getClassSubjects(int $classId): array {
    $st = getDB()->prepare(
        'SELECT s.*, t.emp_id, u.name AS teacher_name
         FROM class_subjects cs
         JOIN subjects s ON cs.subject_id = s.id
         LEFT JOIN teachers t ON cs.teacher_id = t.id
         LEFT JOIN users u ON t.user_id = u.id
         WHERE cs.class_id = ?
         ORDER BY s.name'
    );
    $st->execute([$classId]);
    return $st->fetchAll();
}

// ── Get students in a class ──────────────────────────────────────
function getClassStudents(int $classId): array {
    $st = getDB()->prepare(
        'SELECT s.*, u.name, u.status, u.user_id AS roll_no_login
         FROM students s
         JOIN users u ON s.user_id = u.id
         WHERE s.class_id = ?
         ORDER BY s.roll_no'
    );
    $st->execute([$classId]);
    return $st->fetchAll();
}

// ── Get student record by user.id ────────────────────────────────
function getStudentByUserId(int $userId): ?array {
    $st = getDB()->prepare(
        'SELECT s.*, u.name, u.email, u.user_id AS login_id, c.name AS class_name
         FROM students s
         JOIN users u ON s.user_id = u.id
         LEFT JOIN classes c ON s.class_id = c.id
         WHERE s.user_id = ?'
    );
    $st->execute([$userId]);
    return $st->fetch() ?: null;
}

// ── Get teacher record by user.id ────────────────────────────────
function getTeacherByUserId(int $userId): ?array {
    $st = getDB()->prepare(
        'SELECT t.*, u.name, u.email, u.user_id AS emp_id_login, sb.name AS subject_name, sb.code AS subject_code
         FROM teachers t
         JOIN users u ON t.user_id = u.id
         LEFT JOIN subjects sb ON t.subject_id = sb.id
         WHERE t.user_id = ?'
    );
    $st->execute([$userId]);
    return $st->fetch() ?: null;
}

// ── Attendance summary for a student (per subject) ───────────────
function getStudentAttendanceSummary(int $studentId): array {
    $st = getDB()->prepare(
        'SELECT sb.name AS subject, sb.code,
                COUNT(*) AS total,
                SUM(a.status="P") AS present,
                SUM(a.status="A") AS absent,
                SUM(a.status="L") AS `leave`
         FROM attendance a
         JOIN subjects sb ON a.subject_id = sb.id
         WHERE a.student_id = ?
         GROUP BY sb.id, sb.name, sb.code
         ORDER BY sb.name'
    );
    $st->execute([$studentId]);
    return $st->fetchAll();
}

// ── Timetable for a class ────────────────────────────────────────
function getClassTimetable(int $classId): array {
    $st = getDB()->prepare(
        'SELECT tt.day, tt.period, tt.room,
                sb.name AS subject_name, sb.code AS subject_code,
                u.name AS teacher_name
         FROM timetable tt
         LEFT JOIN subjects sb ON tt.subject_id = sb.id
         LEFT JOIN teachers tc ON tt.teacher_id = tc.id
         LEFT JOIN users u ON tc.user_id = u.id
         WHERE tt.class_id = ?
         ORDER BY FIELD(tt.day,"monday","tuesday","wednesday","thursday","friday"), tt.period'
    );
    $st->execute([$classId]);
    $rows = $st->fetchAll();
    // Index by [day][period]
    $grid = [];
    foreach ($rows as $r) {
        $grid[$r['day']][$r['period']] = $r;
    }
    return $grid;
}

// ── Notices for a portal ─────────────────────────────────────────
function getNoticesForPortal(string $portal): array {
    // portal: student, teacher, finance, admin
    $audienceMap = [
        'student' => ['students'],
        'teacher' => ['teachers', 'admin'],
        'finance' => ['finance'],
        'admin'   => ['students', 'teachers', 'finance', 'admin'],
    ];
    $audiences = $audienceMap[$portal] ?? ['students'];

    // Build FIND_IN_SET conditions
    $conditions = array_map(fn($a) => "FIND_IN_SET(?, audience)", $audiences);
    $sql = 'SELECT n.*, u.name AS author_name
            FROM notices n
            LEFT JOIN users u ON n.author_id = u.id
            WHERE (' . implode(' OR ', $conditions) . ')
              AND (expiry_date IS NULL OR expiry_date >= CURDATE())
            ORDER BY pinned DESC, created_at DESC';

    $st = getDB()->prepare($sql);
    $st->execute($audiences);
    return $st->fetchAll();
}

// ── Format date nicely ───────────────────────────────────────────
function fDate(?string $d): string {
    if (!$d) return '—';
    return date('d M Y', strtotime($d));
}

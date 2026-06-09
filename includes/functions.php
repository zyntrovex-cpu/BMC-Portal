<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

function h(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never {
    // Prepend BASE_URL for site-relative paths so subdirectory installs work
    if (str_starts_with($url, '/') && defined('BASE_URL') && BASE_URL !== '') {
        $url = BASE_URL . $url;
    }
    header('Location: ' . $url);
    exit;
}

// Returns a URL with BASE_URL prepended (for use in HTML href/src attributes)
function url(string $path): string {
    return (defined('BASE_URL') ? BASE_URL : '') . $path;
}

function jsonResponse(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function getAllClasses(): array {
    return getDB()->query('SELECT * FROM classes ORDER BY grade, section')->fetchAll();
}

function getAllSubjects(): array {
    return getDB()->query('SELECT * FROM subjects ORDER BY name')->fetchAll();
}

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

function getTeacherByUserId(int $userId): ?array {
    $st = getDB()->prepare(
        'SELECT t.*, u.name, u.email, u.user_id AS emp_id_login, sb.name AS subject_name, sb.code AS subject_code
         FROM teachers t
         JOIN users u ON t.user_id = u.id
         LEFT JOIN subjects sb ON t.subject_id = sb.id
         WHERE t.user_id = ?'
    );
    $st->execute([$userId]);
    $teacher = $st->fetch() ?: null;
    if ($teacher) {
        $subSt = getDB()->prepare('SELECT DISTINCT s.id, s.name FROM class_subjects cs JOIN subjects s ON cs.subject_id=s.id WHERE cs.teacher_id = ? ORDER BY s.name');
        $subSt->execute([$teacher['id']]);
        $teacher['assigned_subjects'] = $subSt->fetchAll();
    }
    return $teacher;
}

function getStudentAttendanceSummary(int $studentId): array {
    $st = getDB()->prepare(
        'SELECT sb.name AS subject, sb.code,
                COALESCE(COUNT(a.id), 0) AS total,
                COALESCE(SUM(a.status="P"), 0) AS present,
                COALESCE(SUM(a.status="A"), 0) AS absent,
                COALESCE(SUM(a.status="L"), 0) AS `leave`
         FROM attendance a
         JOIN subjects sb ON a.subject_id = sb.id
         WHERE a.student_id = ?
         GROUP BY sb.id, sb.name, sb.code
         ORDER BY sb.name'
    );
    $st->execute([$studentId]);
    return $st->fetchAll();
}

function getClassTimetable(int $classId): array {
    $st = getDB()->prepare(
        'SELECT tt.day, tt.period, tt.room, tt.subject_id, tt.teacher_id,
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
    $grid = [];
    foreach ($rows as $r) {
        $grid[$r['day']][$r['period']] = $r;
    }
    return $grid;
}

// audience column is a SET type; FIND_IN_SET works for SET and CSV alike
function getNoticesForPortal(string $portal): array {
    $map = ['student'=>'students','teacher'=>'teachers','finance'=>'finance','admin'=>'admin'];
    $audience = $map[$portal] ?? 'students';
    $st = getDB()->prepare(
        'SELECT n.*, u.name AS author_name
         FROM notices n
         LEFT JOIN users u ON n.author_id = u.id
         WHERE FIND_IN_SET(?, audience)
           AND (expiry_date IS NULL OR expiry_date >= CURDATE())
         ORDER BY pinned DESC, created_at DESC'
    );
    $st->execute([$audience]);
    return $st->fetchAll();
}

function getSetting(string $key, string $default = ''): string {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    $st = getDB()->prepare('SELECT value FROM settings WHERE key_name = ?');
    $st->execute([$key]);
    $row = $st->fetch();
    $cache[$key] = $row ? (string)$row['value'] : $default;
    return $cache[$key];
}

function fDate(?string $d): string {
    if (!$d) return '—';
    return date('d M Y', strtotime($d));
}

function fDateTime(?string $d): string {
    if (!$d) return '—';
    return date('d M Y H:i', strtotime($d));
}

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

// ── Admin sidebar links ───────────────────────────────────────────
function getAdminLinks(): array {
    return [
        ['href'=>'/admin/dashboard.php',       'icon'=>'<i class="fas fa-home"></i>',               'label'=>'Dashboard',         'key'=>'dashboard'],
        ['href'=>'/admin/users.php',            'icon'=>'<i class="fas fa-users"></i>',              'label'=>'User Management',   'key'=>'users'],
        ['href'=>'/admin/import-students.php',  'icon'=>'<i class="fas fa-file-import"></i>',        'label'=>'Import Students',   'key'=>'import'],
        ['href'=>'/admin/classes.php',          'icon'=>'<i class="fas fa-chalkboard"></i>',         'label'=>'Classes & Subjects','key'=>'classes'],
        ['href'=>'/admin/teachers.php',         'icon'=>'<i class="fas fa-chalkboard-teacher"></i>', 'label'=>'Teacher Accounts',  'key'=>'teachers'],
        ['href'=>'/admin/promote.php',          'icon'=>'<i class="fas fa-level-up-alt"></i>',       'label'=>'Class Promotion',   'key'=>'promote'],
        ['href'=>'/admin/houses.php',           'icon'=>'<i class="fas fa-shield-alt"></i>',         'label'=>'Houses',            'key'=>'houses'],
        ['href'=>'/admin/warnings.php',         'icon'=>'<i class="fas fa-exclamation-triangle"></i>','label'=>'Student Warnings', 'key'=>'warnings'],
        ['href'=>'/admin/notices.php',          'icon'=>'<i class="fas fa-bell"></i>',               'label'=>'Notice Board',      'key'=>'notices'],
        ['href'=>'/admin/timetable.php',        'icon'=>'<i class="fas fa-table"></i>',              'label'=>'Timetable',         'key'=>'timetable'],
        ['href'=>'/admin/results.php',          'icon'=>'<i class="fas fa-chart-bar"></i>',          'label'=>'Results',           'key'=>'results'],
        ['href'=>'/admin/view-as.php',           'icon'=>'<i class="fas fa-eye"></i>',                'label'=>'View As User',      'key'=>'viewas'],
        ['href'=>'/admin/activity.php',         'icon'=>'<i class="fas fa-history"></i>',            'label'=>'Activity Log',      'key'=>'activity'],
        ['href'=>'/admin/settings.php',         'icon'=>'<i class="fas fa-cog"></i>',               'label'=>'Settings',           'key'=>'settings'],
    ];
}

function getIlcLinks(): array {
    return [
        ['href'=>'/ilc/dashboard.php',          'icon'=>'<i class="fas fa-home"></i>',               'label'=>'Dashboard',          'key'=>'dashboard'],
        ['href'=>'/ilc/students.php',            'icon'=>'<i class="fas fa-user-graduate"></i>',      'label'=>'ILC Students',       'key'=>'students'],
        ['href'=>'/ilc/teachers.php',            'icon'=>'<i class="fas fa-chalkboard-teacher"></i>', 'label'=>'ILC Teachers',       'key'=>'teachers'],
        ['href'=>'/ilc/disabilities.php',        'icon'=>'<i class="fas fa-heartbeat"></i>',          'label'=>'Disability Records', 'key'=>'disabilities'],
        ['href'=>'/ilc/admission-requests.php',  'icon'=>'<i class="fas fa-file-medical-alt"></i>',   'label'=>'Admission Requests', 'key'=>'admissions'],
        ['href'=>'/ilc/view-as.php',             'icon'=>'<i class="fas fa-eye"></i>',                'label'=>'View As User',       'key'=>'viewas'],
    ];
}

function getStudentAffairsLinks(): array {
    return [
        ['href'=>'/student-affairs/dashboard.php',       'icon'=>'<i class="fas fa-home"></i>',             'label'=>'Dashboard',          'key'=>'dashboard'],
        ['href'=>'/student-affairs/students.php',        'icon'=>'<i class="fas fa-user-graduate"></i>',    'label'=>'Students',           'key'=>'students'],
        ['href'=>'/student-affairs/admissions.php',      'icon'=>'<i class="fas fa-file-medical-alt"></i>', 'label'=>'Admission Requests', 'key'=>'admissions'],
        ['href'=>'/student-affairs/medical-records.php', 'icon'=>'<i class="fas fa-notes-medical"></i>',    'label'=>'Medical Records',    'key'=>'medical'],
    ];
}

function getVpLinks(): array {
    return [
        ['href'=>'/vp/dashboard.php',  'icon'=>'<i class="fas fa-home"></i>',               'label'=>'Dashboard',    'key'=>'dashboard'],
        ['href'=>'/vp/teachers.php',   'icon'=>'<i class="fas fa-chalkboard-teacher"></i>', 'label'=>'Teachers',     'key'=>'teachers'],
        ['href'=>'/vp/students.php',   'icon'=>'<i class="fas fa-user-graduate"></i>',      'label'=>'Students',     'key'=>'students'],
        ['href'=>'/vp/attendance.php', 'icon'=>'<i class="fas fa-calendar-check"></i>',     'label'=>'Attendance',   'key'=>'attendance'],
        ['href'=>'/vp/results.php',    'icon'=>'<i class="fas fa-chart-bar"></i>',          'label'=>'Results',      'key'=>'results'],
        ['href'=>'/vp/timetable.php',  'icon'=>'<i class="fas fa-table"></i>',              'label'=>'Timetable',    'key'=>'timetable'],
        ['href'=>'/vp/view-as.php',    'icon'=>'<i class="fas fa-eye"></i>',                'label'=>'View As User', 'key'=>'viewas'],
    ];
}

function getWingHeadLinks(): array {
    return [
        ['href'=>'/wing-head/dashboard.php', 'icon'=>'<i class="fas fa-home"></i>',          'label'=>'Dashboard', 'key'=>'dashboard'],
        ['href'=>'/wing-head/students.php',  'icon'=>'<i class="fas fa-user-graduate"></i>', 'label'=>'Students',  'key'=>'students'],
        ['href'=>'/wing-head/classes.php',   'icon'=>'<i class="fas fa-chalkboard"></i>',    'label'=>'Classes',   'key'=>'classes'],
    ];
}

// ── Teacher sidebar links ─────────────────────────────────────────
function getTeacherLinks(): array {
    return [
        ['href'=>'/teacher/dashboard.php',  'icon'=>'<i class="fas fa-home"></i>',               'label'=>'Dashboard',          'key'=>'dashboard'],
        ['href'=>'/teacher/marks.php',      'icon'=>'<i class="fas fa-pen-alt"></i>',             'label'=>'Assessments & Marks','key'=>'marks'],
        ['href'=>'/teacher/attendance.php', 'icon'=>'<i class="fas fa-calendar-check"></i>',      'label'=>'Attendance',         'key'=>'attendance'],
        ['href'=>'/teacher/timetable.php',  'icon'=>'<i class="fas fa-table"></i>',               'label'=>'My Timetable',       'key'=>'timetable'],
        ['href'=>'/teacher/notices.php',    'icon'=>'<i class="fas fa-bell"></i>',                'label'=>'Notices',            'key'=>'notices'],
        ['href'=>'/admin/warnings.php',     'icon'=>'<i class="fas fa-exclamation-triangle"></i>','label'=>'Student Warnings',   'key'=>'warnings'],
    ];
}

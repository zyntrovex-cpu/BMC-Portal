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

// ── Permission definitions ────────────────────────────────────────
function getRolePermissions(string $role): array {
    static $map = [
        'teacher' => [
            'marks'       => ['label' => 'Assessments & Marks', 'icon' => 'fa-pen-alt'],
            'attendance'  => ['label' => 'Mark Attendance',     'icon' => 'fa-calendar-check'],
            'timetable'   => ['label' => 'View Timetable',      'icon' => 'fa-table'],
            'notices'     => ['label' => 'Post Notices',        'icon' => 'fa-bell'],
            'warnings'    => ['label' => 'Issue Warnings',      'icon' => 'fa-exclamation-triangle'],
            'diary'       => ['label' => 'Daily Diary',         'icon' => 'fa-book-open'],
            'complaints'  => ['label' => 'View Complaints',     'icon' => 'fa-comment-alt'],
        ],
        'finance' => [
            'fee_collection' => ['label' => 'Fee Collection',  'icon' => 'fa-hand-holding-usd'],
            'fee_monthly'    => ['label' => 'Monthly Report',  'icon' => 'fa-calendar-alt'],
            'fee_records'    => ['label' => 'Fee Records',     'icon' => 'fa-file-invoice-dollar'],
            'fee_defaulters' => ['label' => 'Defaulters',      'icon' => 'fa-exclamation-triangle'],
            'fee_reports'    => ['label' => 'Fee Reports',     'icon' => 'fa-chart-pie'],
        ],
        'ilc_vp' => [
            'ilc_students'     => ['label' => 'ILC Students',         'icon' => 'fa-user-graduate'],
            'ilc_teachers'     => ['label' => 'ILC Teachers',         'icon' => 'fa-chalkboard-teacher'],
            'ilc_disabilities' => ['label' => 'Disability Records',   'icon' => 'fa-heartbeat'],
            'ilc_admissions'   => ['label' => 'Admission Requests',   'icon' => 'fa-file-medical-alt'],
            'ilc_records'      => ['label' => 'Session Records',      'icon' => 'fa-folder-open'],
            'ilc_attendance'   => ['label' => 'ILC Attendance',       'icon' => 'fa-calendar-check'],
            'ilc_results'      => ['label' => 'ILC Results',          'icon' => 'fa-chart-bar'],
            'ilc_timetable'    => ['label' => 'ILC Timetable',        'icon' => 'fa-table'],
            'ilc_viewas'       => ['label' => 'View As User',         'icon' => 'fa-eye'],
        ],
        'student_affairs' => [
            'sa_students'   => ['label' => 'Student Management',  'icon' => 'fa-user-graduate'],
            'sa_admissions' => ['label' => 'Admission Requests',  'icon' => 'fa-file-medical-alt'],
            'sa_medical'    => ['label' => 'Medical Records',     'icon' => 'fa-notes-medical'],
            'sa_calendar'   => ['label' => 'Academic Calendar',   'icon' => 'fa-calendar-week'],
        ],
        'vp_main' => [
            'vp_teachers'      => ['label' => 'Teachers',              'icon' => 'fa-chalkboard-teacher'],
            'vp_students'      => ['label' => 'Students',              'icon' => 'fa-user-graduate'],
            'vp_attendance'    => ['label' => 'Attendance',            'icon' => 'fa-calendar-check'],
            'vp_att_requests'  => ['label' => 'Attendance Requests',   'icon' => 'fa-edit'],
            'vp_results'       => ['label' => 'Results',               'icon' => 'fa-chart-bar'],
            'vp_timetable'     => ['label' => 'Timetable',             'icon' => 'fa-table'],
            'vp_calendar'      => ['label' => 'Academic Calendar',     'icon' => 'fa-calendar-week'],
            'vp_viewas'        => ['label' => 'View As User',          'icon' => 'fa-eye'],
        ],
        'wing_head' => [
            'wh_students' => ['label' => 'Students', 'icon' => 'fa-user-graduate'],
            'wh_classes'  => ['label' => 'Classes',  'icon' => 'fa-chalkboard'],
        ],
    ];
    return $map[$role] ?? [];
}

// Returns true if current user has the given permission.
// Admins always pass. If no permission records exist in DB, access is granted.
function hasPermission(string $perm): bool {
    if (($_SESSION['user']['role'] ?? '') === 'admin') return true;
    $perms = $_SESSION['user_perms'] ?? null;
    if ($perms === null) return true;
    return (bool)($perms[$perm] ?? true);
}

// Redirects to unauthorized page if permission is missing.
function requirePermission(string $perm): void {
    if (!hasPermission($perm)) {
        $base = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base . '/index.php?msg=unauthorized');
        exit;
    }
}

// ── Student sidebar links ─────────────────────────────────────────
function getStudentLinks(): array {
    return [
        ['href'=>'/student/dashboard.php',   'icon'=>'<i class="fas fa-home"></i>',            'label'=>'Dashboard',    'key'=>'dashboard'],
        ['href'=>'/student/results.php',     'icon'=>'<i class="fas fa-chart-bar"></i>',       'label'=>'My Results',   'key'=>'results'],
        ['href'=>'/student/attendance.php',  'icon'=>'<i class="fas fa-calendar-check"></i>',  'label'=>'Attendance',   'key'=>'attendance'],
        ['href'=>'/student/timetable.php',   'icon'=>'<i class="fas fa-table"></i>',           'label'=>'Timetable',    'key'=>'timetable'],
        ['href'=>'/student/notices.php',     'icon'=>'<i class="fas fa-bell"></i>',            'label'=>'Notices',      'key'=>'notices'],
        ['href'=>'/student/diary.php',       'icon'=>'<i class="fas fa-book-open"></i>',       'label'=>'Class Diary',  'key'=>'diary'],
        ['href'=>'/student/complaints.php',  'icon'=>'<i class="fas fa-comment-alt"></i>',     'label'=>'Complaints',   'key'=>'complaints'],
        ['href'=>'/student/calendar.php',    'icon'=>'<i class="fas fa-calendar-week"></i>',   'label'=>'Calendar',     'key'=>'calendar'],
        ['href'=>'/student/profile.php',     'icon'=>'<i class="fas fa-user"></i>',            'label'=>'My Profile',   'key'=>'profile'],
    ];
}

// ── Admin sidebar links ───────────────────────────────────────────
function getAdminLinks(): array {
    return [
        ['href'=>'/admin/dashboard.php',          'icon'=>'<i class="fas fa-home"></i>',               'label'=>'Dashboard',         'key'=>'dashboard'],
        ['href'=>'/admin/users.php',              'icon'=>'<i class="fas fa-users"></i>',              'label'=>'Staff & Students',  'key'=>'users'],
        ['href'=>'/admin/import-students.php',    'icon'=>'<i class="fas fa-file-import"></i>',        'label'=>'Import Students',   'key'=>'import'],
        ['href'=>'/admin/classes.php',            'icon'=>'<i class="fas fa-chalkboard"></i>',         'label'=>'Classes & Subjects','key'=>'classes'],
        ['href'=>'/admin/teachers.php',           'icon'=>'<i class="fas fa-chalkboard-teacher"></i>', 'label'=>'Teacher Accounts',  'key'=>'teachers'],
        ['href'=>'/admin/promote.php',            'icon'=>'<i class="fas fa-level-up-alt"></i>',       'label'=>'Class Promotion',   'key'=>'promote'],
        ['href'=>'/admin/houses.php',             'icon'=>'<i class="fas fa-shield-alt"></i>',         'label'=>'Houses',            'key'=>'houses'],
        ['href'=>'/admin/warnings.php',           'icon'=>'<i class="fas fa-exclamation-triangle"></i>','label'=>'Student Warnings', 'key'=>'warnings'],
        ['href'=>'/admin/notices.php',            'icon'=>'<i class="fas fa-bell"></i>',               'label'=>'Notice Board',      'key'=>'notices'],
        ['href'=>'/admin/academic-calendar.php',  'icon'=>'<i class="fas fa-calendar-week"></i>',      'label'=>'Academic Calendar', 'key'=>'calendar'],
        ['href'=>'/admin/timetable.php',          'icon'=>'<i class="fas fa-table"></i>',              'label'=>'Timetable',         'key'=>'timetable'],
        ['href'=>'/admin/results.php',            'icon'=>'<i class="fas fa-chart-bar"></i>',          'label'=>'Results',           'key'=>'results'],
        ['href'=>'/admin/view-as.php',            'icon'=>'<i class="fas fa-eye"></i>',                'label'=>'View As User',      'key'=>'viewas'],
        ['href'=>'/admin/activity.php',           'icon'=>'<i class="fas fa-history"></i>',            'label'=>'Activity Log',      'key'=>'activity'],
        ['href'=>'/admin/settings.php',           'icon'=>'<i class="fas fa-cog"></i>',               'label'=>'Settings',           'key'=>'settings'],
    ];
}

// ── Teacher sidebar links (permission-filtered) ───────────────────
function getTeacherLinks(): array {
    return array_values(array_filter([
        ['href'=>'/teacher/dashboard.php',  'icon'=>'<i class="fas fa-home"></i>',                'label'=>'Dashboard',           'key'=>'dashboard'],
        hasPermission('marks')      ? ['href'=>'/teacher/marks.php',      'icon'=>'<i class="fas fa-pen-alt"></i>',              'label'=>'Assessments & Marks', 'key'=>'marks']       : null,
        hasPermission('attendance') ? ['href'=>'/teacher/attendance.php', 'icon'=>'<i class="fas fa-calendar-check"></i>',       'label'=>'Attendance',          'key'=>'attendance']  : null,
        hasPermission('timetable')  ? ['href'=>'/teacher/timetable.php',  'icon'=>'<i class="fas fa-table"></i>',                'label'=>'My Timetable',        'key'=>'timetable']   : null,
        hasPermission('diary')      ? ['href'=>'/teacher/diary.php',      'icon'=>'<i class="fas fa-book-open"></i>',            'label'=>'Daily Diary',         'key'=>'diary']       : null,
        hasPermission('notices')    ? ['href'=>'/teacher/notices.php',    'icon'=>'<i class="fas fa-bell"></i>',                 'label'=>'Notices',             'key'=>'notices']     : null,
        hasPermission('warnings')   ? ['href'=>'/admin/warnings.php',     'icon'=>'<i class="fas fa-exclamation-triangle"></i>', 'label'=>'Student Warnings',    'key'=>'warnings']    : null,
        hasPermission('complaints') ? ['href'=>'/teacher/complaints.php', 'icon'=>'<i class="fas fa-comment-alt"></i>',         'label'=>'Complaints',          'key'=>'complaints']  : null,
    ]));
}

// ── Finance sidebar links (permission-filtered) ───────────────────
function getFinanceLinks(): array {
    return array_values(array_filter([
        ['href'=>'/finance/dashboard.php',   'icon'=>'<i class="fas fa-home"></i>',                       'label'=>'Dashboard',     'key'=>'dashboard'],
        hasPermission('fee_collection') ? ['href'=>'/finance/collection.php', 'icon'=>'<i class="fas fa-hand-holding-usd"></i>',    'label'=>'Fee Collection',  'key'=>'collection']  : null,
        hasPermission('fee_collection') ? ['href'=>'/finance/kuickpay.php',   'icon'=>'<i class="fas fa-file-upload"></i>',          'label'=>'Kuickpay Import', 'key'=>'kuickpay']    : null,
        hasPermission('fee_monthly')    ? ['href'=>'/finance/monthly.php',    'icon'=>'<i class="fas fa-calendar-alt"></i>',        'label'=>'Monthly Report',  'key'=>'monthly']     : null,
        hasPermission('fee_records')    ? ['href'=>'/finance/records.php',    'icon'=>'<i class="fas fa-file-invoice-dollar"></i>', 'label'=>'Fee Records',     'key'=>'records']     : null,
        hasPermission('fee_defaulters') ? ['href'=>'/finance/defaulters.php', 'icon'=>'<i class="fas fa-exclamation-triangle"></i>','label'=>'Defaulters',      'key'=>'defaulters']  : null,
        hasPermission('fee_reports')    ? ['href'=>'/finance/reports.php',    'icon'=>'<i class="fas fa-chart-pie"></i>',           'label'=>'Reports',         'key'=>'reports']     : null,
    ]));
}

// ── ILC sidebar links (permission-filtered) ───────────────────────
function getIlcLinks(): array {
    return array_values(array_filter([
        ['href'=>'/ilc/dashboard.php',         'icon'=>'<i class="fas fa-home"></i>',               'label'=>'Dashboard',          'key'=>'dashboard'],
        hasPermission('ilc_students')     ? ['href'=>'/ilc/students.php',           'icon'=>'<i class="fas fa-user-graduate"></i>',      'label'=>'ILC Students',       'key'=>'students']     : null,
        hasPermission('ilc_teachers')     ? ['href'=>'/ilc/teachers.php',           'icon'=>'<i class="fas fa-chalkboard-teacher"></i>', 'label'=>'ILC Teachers',       'key'=>'teachers']     : null,
        hasPermission('ilc_disabilities') ? ['href'=>'/ilc/disabilities.php',       'icon'=>'<i class="fas fa-heartbeat"></i>',          'label'=>'Disability Records', 'key'=>'disabilities'] : null,
        hasPermission('ilc_admissions')   ? ['href'=>'/ilc/admission-requests.php', 'icon'=>'<i class="fas fa-file-medical-alt"></i>',   'label'=>'Admission Requests', 'key'=>'admissions']   : null,
        hasPermission('ilc_records')      ? ['href'=>'/ilc/records.php',            'icon'=>'<i class="fas fa-folder-open"></i>',        'label'=>'Session Records',    'key'=>'records']      : null,
        hasPermission('ilc_attendance')   ? ['href'=>'/ilc/attendance.php',         'icon'=>'<i class="fas fa-calendar-check"></i>',     'label'=>'ILC Attendance',     'key'=>'attendance']   : null,
        hasPermission('ilc_results')      ? ['href'=>'/ilc/results.php',            'icon'=>'<i class="fas fa-chart-bar"></i>',          'label'=>'ILC Results',        'key'=>'results']      : null,
        hasPermission('ilc_timetable')    ? ['href'=>'/ilc/timetable.php',          'icon'=>'<i class="fas fa-table"></i>',              'label'=>'ILC Timetable',      'key'=>'timetable']    : null,
        hasPermission('ilc_viewas')       ? ['href'=>'/ilc/view-as.php',            'icon'=>'<i class="fas fa-eye"></i>',                'label'=>'View As User',       'key'=>'viewas']       : null,
    ]));
}

// ── Student Affairs sidebar links (permission-filtered) ───────────
function getStudentAffairsLinks(): array {
    return array_values(array_filter([
        ['href'=>'/student-affairs/dashboard.php',       'icon'=>'<i class="fas fa-home"></i>',             'label'=>'Dashboard',          'key'=>'dashboard'],
        hasPermission('sa_students')   ? ['href'=>'/student-affairs/students.php',        'icon'=>'<i class="fas fa-user-graduate"></i>',    'label'=>'Students',           'key'=>'students']   : null,
        hasPermission('sa_admissions') ? ['href'=>'/student-affairs/admissions.php',      'icon'=>'<i class="fas fa-file-medical-alt"></i>', 'label'=>'Admission Requests', 'key'=>'admissions'] : null,
        hasPermission('sa_medical')    ? ['href'=>'/student-affairs/medical-records.php', 'icon'=>'<i class="fas fa-notes-medical"></i>',    'label'=>'Medical Records',    'key'=>'medical']    : null,
        hasPermission('sa_calendar')   ? ['href'=>'/admin/academic-calendar.php',         'icon'=>'<i class="fas fa-calendar-week"></i>',    'label'=>'Academic Calendar',  'key'=>'calendar']   : null,
    ]));
}

// ── VP sidebar links (permission-filtered) ────────────────────────
function getVpLinks(): array {
    return array_values(array_filter([
        ['href'=>'/vp/dashboard.php',  'icon'=>'<i class="fas fa-home"></i>',               'label'=>'Dashboard',            'key'=>'dashboard'],
        hasPermission('vp_teachers')     ? ['href'=>'/vp/teachers.php',               'icon'=>'<i class="fas fa-chalkboard-teacher"></i>', 'label'=>'Teachers',             'key'=>'teachers']     : null,
        hasPermission('vp_students')     ? ['href'=>'/vp/students.php',               'icon'=>'<i class="fas fa-user-graduate"></i>',      'label'=>'Students',             'key'=>'students']     : null,
        hasPermission('vp_attendance')   ? ['href'=>'/vp/attendance.php',             'icon'=>'<i class="fas fa-calendar-check"></i>',     'label'=>'Attendance',           'key'=>'attendance']   : null,
        hasPermission('vp_att_requests') ? ['href'=>'/vp/attendance-requests.php',    'icon'=>'<i class="fas fa-edit"></i>',               'label'=>'Attendance Requests',  'key'=>'att_requests'] : null,
        hasPermission('vp_results')      ? ['href'=>'/vp/results.php',                'icon'=>'<i class="fas fa-chart-bar"></i>',          'label'=>'Results',              'key'=>'results']      : null,
        hasPermission('vp_timetable')    ? ['href'=>'/vp/timetable.php',              'icon'=>'<i class="fas fa-table"></i>',              'label'=>'Timetable',            'key'=>'timetable']    : null,
        hasPermission('vp_calendar')     ? ['href'=>'/admin/academic-calendar.php',   'icon'=>'<i class="fas fa-calendar-week"></i>',      'label'=>'Academic Calendar',    'key'=>'calendar']     : null,
        hasPermission('vp_viewas')       ? ['href'=>'/vp/view-as.php',                'icon'=>'<i class="fas fa-eye"></i>',                'label'=>'View As User',         'key'=>'viewas']       : null,
    ]));
}

// ── Wing Head sidebar links (permission-filtered) ─────────────────
function getWingHeadLinks(): array {
    return array_values(array_filter([
        ['href'=>'/wing-head/dashboard.php', 'icon'=>'<i class="fas fa-home"></i>',          'label'=>'Dashboard', 'key'=>'dashboard'],
        hasPermission('wh_students') ? ['href'=>'/wing-head/students.php', 'icon'=>'<i class="fas fa-user-graduate"></i>', 'label'=>'Students', 'key'=>'students'] : null,
        hasPermission('wh_classes')  ? ['href'=>'/wing-head/classes.php',  'icon'=>'<i class="fas fa-chalkboard"></i>',    'label'=>'Classes',  'key'=>'classes']  : null,
    ]));
}

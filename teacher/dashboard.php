<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user    = requireAuth('teacher');
$teacher = getTeacherByUserId($user['id']);

if (!$teacher) {
    setFlash('danger', 'Teacher profile not found. Please contact admin.');
    redirect('/index.php');
}

$db = getDB();

// ── Stats ────────────────────────────────────────────────────────
// Classes assigned (distinct classes this teacher teaches)
$stClasses = $db->prepare(
    'SELECT COUNT(DISTINCT class_id) AS cnt FROM class_subjects WHERE teacher_id = ?'
);
$stClasses->execute([$teacher['id']]);
$classCount = (int)$stClasses->fetchColumn();

// Total students across all those classes
$stStudents = $db->prepare(
    'SELECT COUNT(DISTINCT s.id) AS cnt
     FROM students s
     WHERE s.class_id IN (
         SELECT DISTINCT class_id FROM class_subjects WHERE teacher_id = ?
     )'
);
$stStudents->execute([$teacher['id']]);
$studentCount = (int)$stStudents->fetchColumn();

// Assessments created
$stAssess = $db->prepare('SELECT COUNT(*) FROM assessments WHERE teacher_id = ?');
$stAssess->execute([$teacher['id']]);
$assessCount = (int)$stAssess->fetchColumn();

// Attendance sessions (distinct date+subject combos this teacher recorded)
$stAtt = $db->prepare(
    'SELECT COUNT(DISTINCT CONCAT(date, "-", subject_id)) AS cnt
     FROM attendance WHERE teacher_id = ?'
);
$stAtt->execute([$teacher['id']]);
$attSessions = (int)$stAtt->fetchColumn();

// ── Classes this teacher teaches ─────────────────────────────────
$stMyClasses = $db->prepare(
    'SELECT DISTINCT c.id, c.name, c.grade, c.section,
            s.name AS subject_name, s.code AS subject_code
     FROM class_subjects cs
     JOIN classes c ON cs.class_id = c.id
     JOIN subjects s ON cs.subject_id = s.id
     WHERE cs.teacher_id = ?
     ORDER BY c.grade, c.section'
);
$stMyClasses->execute([$teacher['id']]);
$myClasses = $stMyClasses->fetchAll();

// ── Recent activity ──────────────────────────────────────────────
$stLog = $db->prepare(
    'SELECT action, details, created_at FROM activity_log
     WHERE user_id = ? ORDER BY created_at DESC LIMIT 8'
);
$stLog->execute([$user['id']]);
$recentActivity = $stLog->fetchAll();

// ── Pending marks (assessments where some students have no marks) ─
$stPending = $db->prepare(
    'SELECT a.id, a.title, a.type, a.date, a.max_marks,
            c.name AS class_name, s.name AS subject_name,
            (SELECT COUNT(*) FROM students WHERE class_id = a.class_id) AS total_students,
            (SELECT COUNT(*) FROM marks m WHERE m.assessment_id = a.id) AS marked_students
     FROM assessments a
     JOIN classes c ON a.class_id = c.id
     JOIN subjects s ON a.subject_id = s.id
     WHERE a.teacher_id = ?
     HAVING marked_students < total_students
     ORDER BY a.date DESC
     LIMIT 5'
);
$stPending->execute([$teacher['id']]);
$pendingMarks = $stPending->fetchAll();

// ── Notices ──────────────────────────────────────────────────────
$notices = array_slice(getNoticesForPortal('teacher'), 0, 3);

// ── Sidebar setup ────────────────────────────────────────────────
$links = [
    ['href' => '/teacher/dashboard.php',  'icon' => '<i class="fas fa-home"></i>',           'label' => 'Dashboard',  'key' => 'dashboard'],
    ['href' => '/teacher/marks.php',      'icon' => '<i class="fas fa-pen-alt"></i>',         'label' => 'Marks',      'key' => 'marks'],
    ['href' => '/teacher/attendance.php', 'icon' => '<i class="fas fa-calendar-check"></i>', 'label' => 'Attendance', 'key' => 'attendance'],
    ['href' => '/teacher/timetable.php',  'icon' => '<i class="fas fa-table"></i>',           'label' => 'Timetable',  'key' => 'timetable'],
    ['href' => '/teacher/notices.php',    'icon' => '<i class="fas fa-bell"></i>',            'label' => 'Notices',    'key' => 'notices'],
    ['href' => '/teacher/profile.php',    'icon' => '<i class="fas fa-user"></i>',            'label' => 'Profile',    'key' => 'profile'],
];

pageHead('Dashboard', 'teacher');
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('teacher', 'dashboard', $links); ?>
<div class="main-area">
<?php topbar('Dashboard', $user); ?>
<div class="page-content content">

<?= flashHtml() ?>

<!-- Welcome Banner -->
<div class="portal-banner mb-4" style="background: linear-gradient(135deg, #059669, #047857);">
    <div class="d-flex align-items-center gap-3">
        <div style="font-size:2.4rem; line-height:1;">👨‍🏫</div>
        <div>
            <h4 class="mb-1 text-white fw-bold">Welcome, <?= h($user['name']) ?>!</h4>
            <div class="text-white opacity-75" style="font-size:13px;">
                <?= h($teacher['subject_name'] ?? 'Teacher') ?> &mdash; <?= h($teacher['emp_id'] ?? $user['user_id']) ?>
                &nbsp;|&nbsp; Session <?= SESSION_YEAR ?>
            </div>
        </div>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7; color:#059669;">
                <i class="fas fa-chalkboard"></i>
            </div>
            <div class="stat-val"><?= $classCount ?></div>
            <div class="stat-lbl">Classes Assigned</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe; color:#1d4ed8;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-val"><?= $studentCount ?></div>
            <div class="stat-lbl">Total Students</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f3e8ff; color:#7c3aed;">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-val"><?= $assessCount ?></div>
            <div class="stat-lbl">Assessments Created</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef9c3; color:#d97706;">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-val"><?= $attSessions ?></div>
            <div class="stat-lbl">Attendance Sessions</div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="sec-card">
            <div class="sec-head">
                <h5><i class="fas fa-bolt me-2" style="color:#059669;"></i>Quick Actions</h5>
            </div>
            <div class="sec-body d-flex flex-wrap gap-2">
                <a href="/teacher/attendance.php" class="btn btn-success btn-sm">
                    <i class="fas fa-calendar-check me-1"></i>Take Attendance
                </a>
                <a href="/teacher/marks.php?tab=marks" class="btn btn-primary btn-sm">
                    <i class="fas fa-pen-alt me-1"></i>Enter Marks
                </a>
                <a href="/teacher/marks.php?tab=assessments" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-plus me-1"></i>New Assessment
                </a>
                <a href="/teacher/timetable.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-table me-1"></i>View Timetable
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- My Classes -->
    <div class="col-lg-6">
        <div class="sec-card h-100">
            <div class="sec-head">
                <h5><i class="fas fa-chalkboard me-2" style="color:#059669;"></i>My Classes</h5>
            </div>
            <div class="sec-body p-0">
                <?php if (empty($myClasses)): ?>
                    <div class="p-3 text-muted" style="font-size:13px;">No classes assigned yet.</div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myClasses as $cls): ?>
                            <tr>
                                <td>
                                    <span class="fw-semibold"><?= h($cls['name']) ?></span>
                                    <small class="text-muted d-block">Grade <?= h($cls['grade']) ?> &ndash; <?= h($cls['section']) ?></small>
                                </td>
                                <td>
                                    <span class="badge" style="background:#dcfce7; color:#065f46; font-size:11px;">
                                        <?= h($cls['subject_code']) ?>
                                    </span>
                                    <small class="d-block mt-1" style="font-size:11.5px; color:var(--t2);"><?= h($cls['subject_name']) ?></small>
                                </td>
                                <td>
                                    <a href="/teacher/attendance.php?class_id=<?= $cls['id'] ?>" class="btn btn-xs btn-outline-success me-1" title="Take Attendance">
                                        <i class="fas fa-calendar-check"></i>
                                    </a>
                                    <a href="/teacher/marks.php?tab=marks" class="btn btn-xs btn-outline-primary" title="Enter Marks">
                                        <i class="fas fa-pen-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pending Marks -->
    <div class="col-lg-6">
        <div class="sec-card h-100">
            <div class="sec-head">
                <h5><i class="fas fa-exclamation-circle me-2" style="color:#d97706;"></i>Pending Marks Entries</h5>
                <a href="/teacher/marks.php" class="btn btn-xs btn-outline-secondary">View All</a>
            </div>
            <div class="sec-body p-0">
                <?php if (empty($pendingMarks)): ?>
                    <div class="p-3 text-success" style="font-size:13px;">
                        <i class="fas fa-check-circle me-1"></i>All marks are up to date!
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Assessment</th>
                                <th>Class</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingMarks as $p): ?>
                            <tr>
                                <td>
                                    <a href="/teacher/marks.php?tab=marks&assessment_id=<?= $p['id'] ?>" class="fw-semibold text-decoration-none" style="color:var(--t1);">
                                        <?= h($p['title']) ?>
                                    </a>
                                    <small class="text-muted d-block"><?= h($p['type']) ?> &mdash; <?= fDate($p['date']) ?></small>
                                </td>
                                <td class="text-muted" style="font-size:12px;"><?= h($p['class_name']) ?></td>
                                <td>
                                    <small class="text-muted"><?= $p['marked_students'] ?>/<?= $p['total_students'] ?></small>
                                    <div class="att-bar-wrap mt-1" style="width:80px;">
                                        <?php $pct = $p['total_students'] > 0 ? ($p['marked_students'] / $p['total_students']) * 100 : 0; ?>
                                        <div class="att-bar-fill" style="width:<?= round($pct) ?>%; background:#059669;"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Recent Activity -->
    <div class="col-lg-6">
        <div class="sec-card">
            <div class="sec-head">
                <h5><i class="fas fa-history me-2" style="color:#059669;"></i>Recent Activity</h5>
            </div>
            <div class="sec-body p-0">
                <?php if (empty($recentActivity)): ?>
                    <div class="p-3 text-muted" style="font-size:13px;">No recent activity.</div>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($recentActivity as $log): ?>
                        <li class="d-flex align-items-start gap-2 px-3 py-2" style="border-bottom:1px solid #edf1f7;">
                            <div class="mt-1" style="width:8px; height:8px; border-radius:50%; background:#059669; flex-shrink:0;"></div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-size:12.5px; font-weight:600;"><?= h(ucfirst($log['action'])) ?></div>
                                <?php if ($log['details']): ?>
                                    <div class="text-muted" style="font-size:11.5px;"><?= h($log['details']) ?></div>
                                <?php endif; ?>
                                <div style="font-size:11px; color:var(--t3);"><?= fDate($log['created_at']) ?></div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Notices -->
    <div class="col-lg-6">
        <div class="sec-card">
            <div class="sec-head">
                <h5><i class="fas fa-bell me-2" style="color:#059669;"></i>Notices</h5>
                <a href="/teacher/notices.php" class="btn btn-xs btn-outline-secondary">All Notices</a>
            </div>
            <div class="sec-body p-0">
                <?php if (empty($notices)): ?>
                    <div class="p-3 text-muted" style="font-size:13px;">No active notices.</div>
                <?php else: ?>
                    <?php foreach ($notices as $n): ?>
                    <div class="px-3 py-2" style="border-bottom:1px solid #edf1f7;">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <?php if ($n['pinned']): ?>
                                <i class="fas fa-thumbtack" style="color:#d97706; font-size:11px;"></i>
                            <?php endif; ?>
                            <span class="fw-semibold" style="font-size:13px;"><?= h($n['title']) ?></span>
                            <?php if ($n['priority'] === 'Important' || $n['priority'] === 'Urgent'): ?>
                                <span class="badge bg-danger ms-auto" style="font-size:10px;"><?= h($n['priority']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted" style="font-size:11.5px;">
                            <?= h($n['category'] ?? '') ?>
                            &mdash; <?= fDate($n['created_at']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</div><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /portal-wrap -->

<div class="overlay" id="overlay" onclick="document.getElementById('sidebar').classList.remove('open'); this.classList.remove('show');"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.querySelector('.topbar-toggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('overlay').classList.toggle('show');
        });
    }
});
</script>
</body>
</html>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('admin', 'student_affairs');
$isAdmin = $user['role'] === 'admin';
$db      = getDB();

// ── Auto-migration: widen columns, ensure wing/montessori/ilc flags ───────────
try { $db->exec("ALTER TABLE classes MODIFY name    VARCHAR(60) NOT NULL"); }          catch (Exception $e) {}
try { $db->exec("ALTER TABLE classes MODIFY section VARCHAR(30) NOT NULL DEFAULT ''"); } catch (Exception $e) {}

$_clsCols = array_flip($db->query("SHOW COLUMNS FROM classes")->fetchAll(PDO::FETCH_COLUMN));
if (!isset($_clsCols['is_montessori'])) {
    try { $db->exec("ALTER TABLE classes ADD COLUMN is_montessori TINYINT(1) NOT NULL DEFAULT 0 AFTER section"); } catch (Exception $e) {}
}
if (!isset($_clsCols['is_ilc'])) {
    try { $db->exec("ALTER TABLE classes ADD COLUMN is_ilc TINYINT(1) NOT NULL DEFAULT 0 AFTER is_montessori"); } catch (Exception $e) {}
}
if (!isset($_clsCols['wing'])) {
    try {
        $db->exec("ALTER TABLE classes ADD COLUMN wing ENUM('main','montessori','ilc') NOT NULL DEFAULT 'main' AFTER is_ilc");
        $db->exec("UPDATE classes SET wing='ilc'        WHERE is_ilc=1");
        $db->exec("UPDATE classes SET wing='montessori' WHERE is_montessori=1");
    } catch (Exception $e) {}
}

// ── Auto-seed Excel-sourced classes (INSERT IGNORE = safe to run every load) ──
$_seed = $db->prepare(
    'INSERT IGNORE INTO classes (name, grade, section, is_montessori, is_ilc, wing)
     VALUES (?,?,?,?,?,?)'
);
foreach ([
    // ── Main wing (numbered, no section) ────────────────────────────────────
    ['2',  2, '',  0, 0, 'main'], ['3',  3, '',  0, 0, 'main'], ['4',  4, '',  0, 0, 'main'],
    ['5',  5, '',  0, 0, 'main'], ['6',  6, '',  0, 0, 'main'], ['7',  7, '',  0, 0, 'main'],
    ['8',  8, '',  0, 0, 'main'], ['9',  9, '',  0, 0, 'main'], ['10',10, '',  0, 0, 'main'],
    ['11',11, '',  0, 0, 'main'], ['12',12, '',  0, 0, 'main'],
    // ── Montessori wing ──────────────────────────────────────────────────────
    ['BEGINNERS(ROSE)',      0, 'ROSE',      1, 0, 'montessori'],
    ['BEGINNERS(SUNFLOWER)', 0, 'SUNFLOWER', 1, 0, 'montessori'],
    ['PREP(BLUBELL)',        0, 'BLUBELL',   1, 0, 'montessori'],
    ['PREP(DAFFODIL)',       0, 'DAFFODIL',  1, 0, 'montessori'],
    ['ONE (JASMINE)',        1, 'JASMINE',   1, 0, 'montessori'],
    ['ONE (MARIGOLD)',       1, 'MARIGOLD',  1, 0, 'montessori'],
    ['ADVANCE(DAISY)',       2, 'DAISY',     1, 0, 'montessori'],
    ['ADVANCE(LILLY)',       2, 'LILLY',     1, 0, 'montessori'],
    // ── ILC wing ────────────────────────────────────────────────────────────
    ['ILC', 0, '', 0, 1, 'ilc'],
] as $_r) {
    try { $_seed->execute($_r); } catch (Exception $e) {}
}

// ── POST handlers ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ─── Add class (admin only) ────────────────────────────────────────────
    if ($action === 'add_class' && $isAdmin) {
        $wing      = in_array($_POST['wing'] ?? '', ['main','montessori','ilc']) ? $_POST['wing'] : 'main';
        $customName = trim($_POST['custom_name'] ?? '');

        if ($customName !== '') {
            // Named class (Montessori, ILC, or special)
            $name    = $customName;
            $grade   = (int)($_POST['custom_grade'] ?? 0);
            $section = trim($_POST['custom_section'] ?? '');
        } else {
            // Standard main: grade-section
            $grade   = (int)$_POST['grade'];
            $section = strtoupper(trim($_POST['section'] ?? ''));
            $name    = "$grade-$section";
            if ($grade < 1 || $grade > 14 || $section === '') {
                setFlash('danger', 'Grade (1–14) and section are required for standard classes.');
                redirect('/portal/admin/classes.php');
            }
        }

        if ($name === '') {
            setFlash('danger', 'Class name is required.');
        } else {
            $check = $db->prepare('SELECT id FROM classes WHERE name = ?');
            $check->execute([$name]);
            if ($check->fetch()) {
                setFlash('danger', "Class '$name' already exists.");
            } else {
                $isMontessori = ($wing === 'montessori') ? 1 : 0;
                $isIlc        = ($wing === 'ilc') ? 1 : 0;
                try {
                    $db->prepare('INSERT INTO classes (name, grade, section, is_montessori, is_ilc, wing) VALUES (?,?,?,?,?,?)')
                       ->execute([$name, $grade, $section, $isMontessori, $isIlc, $wing]);
                } catch (Exception $e) {
                    $db->prepare('INSERT INTO classes (name, grade, section) VALUES (?,?,?)')
                       ->execute([$name, $grade, $section]);
                }
                setFlash('success', "Class '$name' created.");
            }
        }
        redirect('/portal/admin/classes.php');
    }

    // ─── Delete class (admin only) ─────────────────────────────────────────
    if ($action === 'delete_class' && $isAdmin) {
        $id = (int)$_POST['id'];
        $sc = $db->prepare('SELECT COUNT(*) FROM students WHERE class_id = ?');
        $sc->execute([$id]);
        if ($sc->fetchColumn() > 0) {
            setFlash('danger', 'Cannot delete a class that has students enrolled.');
        } else {
            try { $db->prepare('DELETE FROM timetable WHERE class_id = ?')->execute([$id]); } catch (Exception $e) {}
            try { $db->prepare('DELETE FROM class_subjects WHERE class_id = ?')->execute([$id]); } catch (Exception $e) {}
            $db->prepare('DELETE FROM classes WHERE id = ?')->execute([$id]);
            setFlash('success', 'Class deleted.');
        }
        redirect('/portal/admin/classes.php');
    }

    // ─── Assign subject + teacher ──────────────────────────────────────────
    if ($action === 'assign_subject') {
        $classId   = (int)$_POST['class_id'];
        $subjectId = (int)$_POST['subject_id'];
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        if ($classId && $subjectId) {
            $db->prepare('INSERT INTO class_subjects (class_id, subject_id, teacher_id) VALUES (?,?,?)
                          ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)')
               ->execute([$classId, $subjectId, $teacherId ?: null]);
            setFlash('success', 'Subject / teacher assigned.');
        }
        redirect('/portal/admin/classes.php?view=' . $classId);
    }

    // ─── Remove subject ────────────────────────────────────────────────────
    if ($action === 'remove_subject') {
        $csId    = (int)$_POST['cs_id'];
        $backId  = (int)($_POST['view_class'] ?? 0);
        $db->prepare('DELETE FROM class_subjects WHERE id = ?')->execute([$csId]);
        setFlash('success', 'Subject removed.');
        redirect('/portal/admin/classes.php?view=' . $backId);
    }
}

$viewClassId = (int)($_GET['view'] ?? 0);
$wingFilter  = $_GET['wing'] ?? '';

// ── Stats ─────────────────────────────────────────────────────────────────────
$totalStudents = (int)$db->query('SELECT COUNT(*) FROM students')->fetchColumn();
$totalClasses  = (int)$db->query('SELECT COUNT(*) FROM classes')->fetchColumn();
$totalSubjects = (int)$db->query('SELECT COUNT(*) FROM subjects')->fetchColumn();

// ── Classes list (with wing + student count) ──────────────────────────────────
$classesWhere  = '';
$classesParams = [];
if ($wingFilter !== '') {
    $classesWhere  = 'WHERE c.wing = ?';
    $classesParams = [$wingFilter];
}
try {
    $classesSt = $db->prepare(
        "SELECT c.id, c.name, c.grade, c.section,
                COALESCE(c.is_montessori,0) AS is_montessori,
                COALESCE(c.is_ilc,0)        AS is_ilc,
                COALESCE(c.wing,'main')      AS wing,
                COUNT(s.id) AS student_count
         FROM classes c
         LEFT JOIN students s ON c.id = s.class_id
         $classesWhere
         GROUP BY c.id
         ORDER BY c.wing, c.grade, c.name"
    );
    $classesSt->execute($classesParams);
    $classes = $classesSt->fetchAll();
} catch (PDOException $e) {
    // Fallback without wing columns
    $classesSt = $db->prepare(
        "SELECT c.id, c.name, c.grade, c.section,
                0 AS is_montessori, 0 AS is_ilc, 'main' AS wing,
                COUNT(s.id) AS student_count
         FROM classes c LEFT JOIN students s ON c.id = s.class_id
         GROUP BY c.id ORDER BY c.grade, c.name"
    );
    $classesSt->execute();
    $classes = $classesSt->fetchAll();
}

// ── Subject overview ──────────────────────────────────────────────────────────
try {
    $subjectsSt = $db->query(
        'SELECT s.id, s.name, s.code,
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.grade, c.section SEPARATOR ", ") AS class_list,
                COUNT(DISTINCT cs.class_id)  AS class_count,
                COUNT(DISTINCT cs.teacher_id) AS teacher_count
         FROM subjects s
         LEFT JOIN class_subjects cs ON cs.subject_id = s.id
         LEFT JOIN classes c ON cs.class_id = c.id
         GROUP BY s.id, s.name, s.code
         ORDER BY s.name'
    );
    $subjectsWithClasses = $subjectsSt->fetchAll();
} catch (PDOException $e) { $subjectsWithClasses = []; }

$subjects = getAllSubjects();

// ── Teachers list ─────────────────────────────────────────────────────────────
try {
    $teachersSt = $db->query(
        'SELECT t.id, u.name, sb.name AS subject
         FROM teachers t
         JOIN users u ON t.user_id = u.id
         LEFT JOIN subjects sb ON t.subject_id = sb.id
         ORDER BY u.name'
    );
    $teachers = $teachersSt->fetchAll();
} catch (PDOException $e) { $teachers = []; }

// ── Class detail (subjects assigned) ─────────────────────────────────────────
$classSubjects = [];
$viewClass     = null;
if ($viewClassId) {
    foreach ($classes as $c) {
        if ($c['id'] === $viewClassId) { $viewClass = $c; break; }
    }
    try {
        $cssSt = $db->prepare(
            'SELECT cs.id, s.name AS subject_name, s.code, u.name AS teacher_name
             FROM class_subjects cs
             JOIN subjects s ON cs.subject_id = s.id
             LEFT JOIN teachers t ON cs.teacher_id = t.id
             LEFT JOIN users u ON t.user_id = u.id
             WHERE cs.class_id = ?
             ORDER BY s.name'
        );
        $cssSt->execute([$viewClassId]);
        $classSubjects = $cssSt->fetchAll();
    } catch (PDOException $e) {}
}

// Wing badge helper
function wingBadgeClass(string $wing): string {
    return match($wing) {
        'montessori' => '<span class="badge bg-warning text-dark" style="font-size:.7rem">Montessori</span>',
        'ilc'        => '<span class="badge bg-purple text-white" style="font-size:.7rem;background:#7c3aed!important">ILC</span>',
        default      => '<span class="badge bg-primary" style="font-size:.7rem">Main</span>',
    };
}

$pageTitle = $isAdmin ? 'Class Management' : 'Classes & Teacher Assignment';
pageHead($pageTitle, $user['role'] === 'student_affairs' ? 'student_affairs' : 'admin');
$links = $user['role'] === 'student_affairs' ? getStudentAffairsLinks() : getAdminLinks();
$activeKey = 'classes';
?>
<div class="portal-wrap">
<?php sidebar($user['role'] === 'student_affairs' ? 'student_affairs' : 'admin', $activeKey, $links, $user); ?>
<div class="main-area">
<?php topbar($pageTitle, $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
      <div class="stat-val"><?= $totalStudents ?></div>
      <div class="stat-lbl">Total Students</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-chalkboard"></i></div>
      <div class="stat-val"><?= $totalClasses ?></div>
      <div class="stat-lbl">Total Classes</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-book"></i></div>
      <div class="stat-val"><?= $totalSubjects ?></div>
      <div class="stat-lbl">Total Subjects</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Left: add form + class list -->
  <div class="col-lg-5">

    <?php if ($isAdmin): ?>
    <!-- Add Class Form (Admin only) -->
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-plus me-2"></i>Add New Class</div>
      <div style="padding:14px">
        <!-- Wing tabs -->
        <ul class="nav nav-tabs nav-fill mb-3" style="font-size:.82rem">
          <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabMain">Main Wing</a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabMontessori">Montessori</a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabIlc">ILC</a></li>
        </ul>
        <div class="tab-content">

          <!-- Main Wing tab -->
          <div class="tab-pane fade show active" id="tabMain">
            <form method="POST" class="d-flex gap-2 align-items-end flex-wrap">
              <input type="hidden" name="action" value="add_class">
              <input type="hidden" name="wing"   value="main">
              <div>
                <label class="form-label fw-semibold" style="font-size:.82rem">Grade</label>
                <input type="number" name="grade" class="form-control form-control-sm" style="width:75px" min="1" max="14" placeholder="8" required>
              </div>
              <div>
                <label class="form-label fw-semibold" style="font-size:.82rem">Section</label>
                <input type="text" name="section" class="form-control form-control-sm" style="width:65px" maxlength="3" placeholder="A">
              </div>
              <div class="flex-grow-1">
                <label class="form-label fw-semibold" style="font-size:.82rem">Or custom name</label>
                <input type="text" name="custom_name" class="form-control form-control-sm" placeholder="e.g. 2 (no section)">
              </div>
              <button type="submit" class="btn btn-sm btn-success">Add</button>
            </form>
            <div style="font-size:.76rem;color:var(--t3);margin-top:6px">Leave custom name blank to auto-generate e.g. <em>8-A</em>. Or enter just a number (e.g. <em>2</em>) for single-section grades.</div>
          </div>

          <!-- Montessori tab -->
          <div class="tab-pane fade" id="tabMontessori">
            <form method="POST" class="d-flex gap-2 align-items-end flex-wrap">
              <input type="hidden" name="action"      value="add_class">
              <input type="hidden" name="wing"        value="montessori">
              <div class="flex-grow-1">
                <label class="form-label fw-semibold" style="font-size:.82rem">Class Name <span class="text-danger">*</span></label>
                <input type="text" name="custom_name" class="form-control form-control-sm" placeholder="e.g. ADVANCE(ROSE)" required>
              </div>
              <div>
                <label class="form-label fw-semibold" style="font-size:.82rem">Grade <small class="text-muted">(optional)</small></label>
                <input type="number" name="custom_grade" class="form-control form-control-sm" style="width:75px" min="0" max="5" placeholder="0">
              </div>
              <button type="submit" class="btn btn-sm btn-warning">Add</button>
            </form>
            <div style="font-size:.76rem;color:var(--t3);margin-top:6px">Grade 0 = playgroup, 1 = Level 1, etc. Class name will appear exactly as entered.</div>
          </div>

          <!-- ILC tab -->
          <div class="tab-pane fade" id="tabIlc">
            <form method="POST" class="d-flex gap-2 align-items-end flex-wrap">
              <input type="hidden" name="action"      value="add_class">
              <input type="hidden" name="wing"        value="ilc">
              <div class="flex-grow-1">
                <label class="form-label fw-semibold" style="font-size:.82rem">Class Name <span class="text-danger">*</span></label>
                <input type="text" name="custom_name" class="form-control form-control-sm" placeholder="e.g. ILC-C" required>
              </div>
              <div>
                <label class="form-label fw-semibold" style="font-size:.82rem">Grade <small class="text-muted">(opt)</small></label>
                <input type="number" name="custom_grade" class="form-control form-control-sm" style="width:75px" min="0" max="14" placeholder="0">
              </div>
              <button type="submit" class="btn btn-sm btn-info">Add</button>
            </form>
          </div>

        </div><!-- /tab-content -->
      </div>
    </div>
    <?php endif; ?>

    <!-- Wing filter -->
    <div class="d-flex gap-1 mb-2">
      <a href="?wing="           class="btn btn-sm <?= !$wingFilter?'btn-primary':'btn-outline-secondary' ?>" style="font-size:.78rem">All (<?= count($classes) ?>)</a>
      <?php
      $wingCounts = ['main'=>0,'montessori'=>0,'ilc'=>0];
      foreach ($classes as $c) $wingCounts[$c['wing']]++;
      ?>
      <a href="?wing=main"        class="btn btn-sm <?= $wingFilter==='main'       ?'btn-primary':'btn-outline-secondary' ?>" style="font-size:.78rem">Main (<?= $wingCounts['main'] ?>)</a>
      <a href="?wing=montessori"  class="btn btn-sm <?= $wingFilter==='montessori' ?'btn-warning':'btn-outline-secondary' ?>" style="font-size:.78rem">Montessori (<?= $wingCounts['montessori'] ?>)</a>
      <a href="?wing=ilc"         class="btn btn-sm <?= $wingFilter==='ilc'        ?'btn-info':'btn-outline-secondary' ?>" style="font-size:.78rem">ILC (<?= $wingCounts['ilc'] ?>)</a>
    </div>

    <!-- Classes list -->
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-chalkboard me-2"></i>All Classes</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.83rem">
          <thead class="table-light">
            <tr><th>Class</th><th>Wing</th><th>Students</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($classes as $c): ?>
            <tr class="<?= $viewClassId===$c['id']?'table-active':'' ?>">
              <td class="fw-semibold"><?= h($c['name']) ?></td>
              <td><?= wingBadgeClass($c['wing']) ?></td>
              <td><?= $c['student_count'] ?></td>
              <td class="d-flex gap-1">
                <a href="?view=<?= $c['id'] ?><?= $wingFilter?"&wing=".urlencode($wingFilter):'' ?>"
                   class="btn btn-xs btn-outline-primary" style="font-size:.73rem;padding:2px 7px">
                  <i class="fas fa-users-cog"></i> Assign
                </a>
                <?php if ($isAdmin): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete class <?= h(addslashes($c['name'])) ?>?')">
                  <input type="hidden" name="action" value="delete_class">
                  <input type="hidden" name="id"     value="<?= $c['id'] ?>">
                  <button class="btn btn-xs btn-outline-danger" style="font-size:.73rem;padding:2px 6px"
                          title="Delete class"><i class="fas fa-trash"></i></button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($classes)): ?>
            <tr><td colspan="4" class="text-center text-muted py-3">No classes found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /left col -->

  <!-- Right: subject/teacher assignment -->
  <div class="col-lg-7">
    <?php if ($viewClass): ?>
    <div class="sec-card mb-3">
      <div class="sec-card-header d-flex align-items-center gap-2">
        <i class="fas fa-chalkboard-teacher me-1"></i>
        Subjects &amp; Teachers —
        <strong><?= h($viewClass['name']) ?></strong>
        <?= wingBadgeClass($viewClass['wing']) ?>
      </div>
      <div style="padding:14px">
        <!-- Assign form -->
        <form method="POST" class="d-flex gap-2 flex-wrap align-items-end mb-3">
          <input type="hidden" name="action"     value="assign_subject">
          <input type="hidden" name="class_id"   value="<?= $viewClassId ?>">
          <div>
            <label class="form-label fw-semibold" style="font-size:.82rem">Subject <span class="text-danger">*</span></label>
            <select name="subject_id" class="form-select form-select-sm" required style="min-width:160px">
              <option value="">— Select subject —</option>
              <?php foreach ($subjects as $s): ?>
              <option value="<?= $s['id'] ?>"><?= h($s['name']) ?><?= $s['code'] ? ' ('.$s['code'].')' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label fw-semibold" style="font-size:.82rem">Teacher <small class="text-muted">(optional)</small></label>
            <select name="teacher_id" class="form-select form-select-sm" style="min-width:160px">
              <option value="">— No teacher —</option>
              <?php foreach ($teachers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= h($t['name']) ?><?= $t['subject'] ? ' · '.$t['subject'] : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-sm btn-success">
            <i class="fas fa-plus me-1"></i>Assign
          </button>
        </form>

        <!-- Assigned subjects table -->
        <table class="table table-sm mb-0" style="font-size:.84rem">
          <thead class="table-light">
            <tr><th>Subject</th><th>Code</th><th>Teacher</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($classSubjects as $cs): ?>
            <tr>
              <td><?= h($cs['subject_name']) ?></td>
              <td><span class="badge bg-secondary"><?= h($cs['code'] ?: '—') ?></span></td>
              <td><?= h($cs['teacher_name'] ?: '—') ?></td>
              <td>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action"     value="remove_subject">
                  <input type="hidden" name="cs_id"      value="<?= $cs['id'] ?>">
                  <input type="hidden" name="view_class" value="<?= $viewClassId ?>">
                  <button class="btn btn-xs btn-outline-danger" style="font-size:.73rem;padding:2px 6px" title="Remove">
                    <i class="fas fa-times"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($classSubjects)): ?>
            <tr><td colspan="4" class="text-center text-muted" style="font-size:.82rem">
              No subjects assigned yet — use the form above.
            </td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php else: ?>
    <div class="sec-card" style="padding:48px;text-align:center;color:var(--t2)">
      <i class="fas fa-chalkboard-teacher fa-2x mb-3 d-block opacity-25"></i>
      <p class="mb-0">Click <strong>Assign</strong> next to a class to manage its subjects and teacher assignments.</p>
    </div>
    <?php endif; ?>
  </div>

</div><!-- /row -->

<!-- Subject overview -->
<div class="sec-card mt-3">
  <div class="sec-card-header"><i class="fas fa-book me-2"></i>Subject Overview</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr><th>Subject</th><th>Code</th><th>Assigned Classes</th><th>Teachers</th></tr>
      </thead>
      <tbody>
        <?php foreach ($subjectsWithClasses as $sv): ?>
        <tr>
          <td class="fw-semibold"><?= h($sv['name']) ?></td>
          <td><span class="badge bg-secondary"><?= h($sv['code'] ?: '—') ?></span></td>
          <td style="font-size:.8rem"><?= h($sv['class_list'] ?: '—') ?></td>
          <td><?= $sv['teacher_count'] ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($subjectsWithClasses)): ?>
        <tr><td colspan="4" class="text-center text-muted py-3">No subjects found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /portal-wrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

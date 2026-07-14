<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_class') {
        $grade   = (int)$_POST['grade'];
        $section = strtoupper(trim($_POST['section'] ?? ''));
        $name    = "$grade-$section";
        if ($grade >= 1 && $grade <= 14 && $section) {
            $check = $db->prepare('SELECT id FROM classes WHERE name = ?');
            $check->execute([$name]);
            if ($check->fetch()) {
                setFlash('danger','Class already exists.');
            } else {
                $db->prepare('INSERT INTO classes (name, grade, section) VALUES (?,?,?)')->execute([$name, $grade, $section]);
                setFlash('success',"Class $name created.");
            }
        }
    }

    if ($action === 'delete_class') {
        $id = (int)$_POST['id'];
        // Check no students
        $sc = $db->prepare('SELECT COUNT(*) FROM students WHERE class_id = ?');
        $sc->execute([$id]);
        if ($sc->fetchColumn() > 0) {
            setFlash('danger','Cannot delete class with students.');
        } else {
            $db->prepare('DELETE FROM timetable WHERE class_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM class_subjects WHERE class_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM classes WHERE id = ?')->execute([$id]);
            setFlash('success','Class deleted.');
        }
    }

    if ($action === 'assign_subject') {
        $classId   = (int)$_POST['class_id'];
        $subjectId = (int)$_POST['subject_id'];
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        if ($classId && $subjectId) {
            $db->prepare('INSERT INTO class_subjects (class_id, subject_id, teacher_id) VALUES (?,?,?)
                          ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)')
               ->execute([$classId, $subjectId, $teacherId ?: null]);
            setFlash('success','Subject assigned.');
        }
    }

    if ($action === 'remove_subject') {
        $id = (int)$_POST['cs_id'];
        $db->prepare('DELETE FROM class_subjects WHERE id = ?')->execute([$id]);
        setFlash('success','Subject removed.');
    }

    redirect('/admin/classes.php' . (isset($_POST['view_class']) ? '?view='.$_POST['view_class'] : ''));
}

$viewClassId = (int)($_GET['view'] ?? 0);

// Stat counts
$totalStudents = (int)$db->query('SELECT COUNT(*) FROM students')->fetchColumn();
$totalClasses  = (int)$db->query('SELECT COUNT(*) FROM classes')->fetchColumn();
$totalSubjects = (int)$db->query('SELECT COUNT(*) FROM subjects')->fetchColumn();

// Subject overview (with assigned classes and teacher counts)
$subjectsSt = $db->query(
    'SELECT s.id, s.name, s.code,
            GROUP_CONCAT(DISTINCT c.name ORDER BY c.grade, c.section SEPARATOR ", ") AS class_list,
            COUNT(DISTINCT cs.class_id) AS class_count,
            COUNT(DISTINCT cs.teacher_id) AS teacher_count
     FROM subjects s
     LEFT JOIN class_subjects cs ON cs.subject_id = s.id
     LEFT JOIN classes c ON cs.class_id = c.id
     GROUP BY s.id, s.name, s.code
     ORDER BY s.name'
);
$subjectsWithClasses = $subjectsSt->fetchAll();

// Get all classes with student count
$classesSt = $db->query(
    'SELECT c.*, COUNT(s.id) AS student_count
     FROM classes c LEFT JOIN students s ON c.id = s.class_id
     GROUP BY c.id ORDER BY c.grade, c.section'
);
$classes  = $classesSt->fetchAll();
$subjects = getAllSubjects();

// Get all teachers
$teachersSt = $db->query('SELECT t.id, u.name, sb.name AS subject FROM teachers t JOIN users u ON t.user_id=u.id LEFT JOIN subjects sb ON t.subject_id=sb.id ORDER BY u.name');
$teachers   = $teachersSt->fetchAll();

// Class subjects if viewing
$classSubjects = [];
if ($viewClassId) {
    $cssSt = $db->prepare(
        'SELECT cs.*, s.name AS subject_name, s.code, u.name AS teacher_name
         FROM class_subjects cs
         JOIN subjects s ON cs.subject_id = s.id
         LEFT JOIN teachers t ON cs.teacher_id = t.id
         LEFT JOIN users u ON t.user_id = u.id
         WHERE cs.class_id = ?'
    );
    $cssSt->execute([$viewClassId]);
    $classSubjects = $cssSt->fetchAll();
}

pageHead('Classes', 'admin');
$links = getAdminLinks();
?>
<div class="portal-wrap">
<?php sidebar('admin', 'classes', $links, $user); ?>
<div class="main-area">
<?php topbar('Class Management', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
      <div class="stat-val"><?= $totalStudents ?></div>
      <div class="stat-label">Total Students</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-chalkboard"></i></div>
      <div class="stat-val"><?= $totalClasses ?></div>
      <div class="stat-label">Total Classes</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-book"></i></div>
      <div class="stat-val"><?= $totalSubjects ?></div>
      <div class="stat-label">Total Subjects</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-5">
    <!-- Add class form -->
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-plus me-2"></i>Add New Class</div>
      <div style="padding:14px">
        <form method="POST" class="d-flex gap-2 align-items-end">
          <input type="hidden" name="action" value="add_class">
          <div>
            <label class="form-label fw-semibold" style="font-size:.82rem">Grade</label>
            <input type="number" name="grade" class="form-control form-control-sm" style="width:80px" min="1" max="14" required>
          </div>
          <div>
            <label class="form-label fw-semibold" style="font-size:.82rem">Section</label>
            <input type="text" name="section" class="form-control form-control-sm" style="width:70px" maxlength="3" placeholder="A" required>
          </div>
          <button type="submit" class="btn btn-sm btn-success">Add</button>
        </form>
      </div>
    </div>

    <!-- Classes list -->
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-chalkboard me-2"></i>All Classes</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.85rem">
          <thead class="table-light"><tr><th>Class</th><th>Students</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($classes as $c): ?>
            <tr class="<?= $viewClassId===$c['id']?'table-active':'' ?>">
              <td class="fw-semibold"><?= h($c['name']) ?></td>
              <td><?= $c['student_count'] ?></td>
              <td>
                <a href="?view=<?= $c['id'] ?>" class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 7px">Manage</a>
                <a href="<?= url('/admin/users.php?role=student') ?>" class="btn btn-xs btn-outline-info" style="font-size:.74rem;padding:2px 7px">Students</a>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete class?')">
                  <input type="hidden" name="action" value="delete_class">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button class="btn btn-xs btn-outline-danger" style="font-size:.74rem;padding:2px 7px">Del</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-7">
    <?php if ($viewClassId): ?>
    <!-- Assign subjects -->
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-book me-2"></i>Subjects for <?php foreach($classes as $c) if($c['id']==$viewClassId) echo h($c['name']); ?></div>
      <div style="padding:14px">
        <form method="POST" class="d-flex gap-2 flex-wrap align-items-end mb-3">
          <input type="hidden" name="action" value="assign_subject">
          <input type="hidden" name="class_id" value="<?= $viewClassId ?>">
          <input type="hidden" name="view_class" value="<?= $viewClassId ?>">
          <div>
            <label class="form-label fw-semibold" style="font-size:.82rem">Subject</label>
            <select name="subject_id" class="form-select form-select-sm" required>
              <?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label fw-semibold" style="font-size:.82rem">Teacher</label>
            <select name="teacher_id" class="form-select form-select-sm">
              <option value="">None</option>
              <?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= h($t['name']) ?> (<?= h($t['subject']) ?>)</option><?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-sm btn-success">Assign</button>
        </form>

        <table class="table table-sm mb-0" style="font-size:.84rem">
          <thead class="table-light"><tr><th>Subject</th><th>Code</th><th>Teacher</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($classSubjects as $cs): ?>
            <tr>
              <td><?= h($cs['subject_name']) ?></td>
              <td><?= h($cs['code']) ?></td>
              <td><?= h($cs['teacher_name'] ?: '—') ?></td>
              <td>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="remove_subject">
                  <input type="hidden" name="cs_id" value="<?= $cs['id'] ?>">
                  <input type="hidden" name="view_class" value="<?= $viewClassId ?>">
                  <button class="btn btn-xs btn-outline-danger" style="font-size:.74rem;padding:2px 6px">Remove</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($classSubjects)): ?><tr><td colspan="4" class="text-muted text-center">No subjects assigned.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php else: ?>
    <div class="sec-card p-4 text-center text-muted">Select a class to manage its subjects.</div>
    <?php endif; ?>
  </div>
</div>

<div class="sec-card mt-3">
  <div class="sec-card-header"><i class="fas fa-book me-2"></i>Subject Overview</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.85rem">
      <thead class="table-light">
        <tr><th>Subject</th><th>Code</th><th>Assigned Classes</th><th>Teachers</th></tr>
      </thead>
      <tbody>
        <?php foreach ($subjectsWithClasses as $sv): ?>
        <tr>
          <td class="fw-semibold"><?= h($sv['name']) ?></td>
          <td><span class="badge bg-secondary"><?= h($sv['code']) ?></span></td>
          <td><?= h($sv['class_list'] ?: '—') ?></td>
          <td><?= $sv['teacher_count'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

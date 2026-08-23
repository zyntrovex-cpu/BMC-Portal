<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

// Auth: allow both admin and teacher
$user = requireAuth('admin', 'teacher');
if ($user['role'] === 'teacher') requirePermission('warnings');

$db = getDB();

// ── POST Handlers ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { $db->query('SELECT 1 FROM student_warnings LIMIT 0'); } catch (PDOException $e) {
        setFlash('danger', 'student_warnings table missing — run warnings-migration.sql first.');
        redirect('/admin/warnings.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $severity  = $_POST['severity'] ?? 'medium';
        $reason    = trim($_POST['reason'] ?? '');

        if (!$studentId || !$reason) {
            setFlash('danger', 'Student and reason are required.');
        } elseif (!in_array($severity, ['low', 'medium', 'high'])) {
            setFlash('danger', 'Invalid severity level.');
        } else {
            $db->prepare(
                'INSERT INTO student_warnings (student_id, given_by, reason, severity) VALUES (?,?,?,?)'
            )->execute([$studentId, $user['id'], $reason, $severity]);
            logActivity($user['id'], 'warning_add', "Added $severity warning to student #$studentId");
            setFlash('success', 'Warning added successfully.');
        }
        redirect('/admin/warnings.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            // Admins can delete any; teachers can only delete their own
            if ($user['role'] === 'admin') {
                $db->prepare('DELETE FROM student_warnings WHERE id = ?')->execute([$id]);
            } else {
                $db->prepare('DELETE FROM student_warnings WHERE id = ? AND given_by = ?')
                   ->execute([$id, $user['id']]);
            }
            logActivity($user['id'], 'warning_delete', "Deleted warning #$id");
            setFlash('success', 'Warning removed.');
        }
        redirect('/admin/warnings.php');
    }
}

// ── Filters ───────────────────────────────────────────────────────
$search   = trim($_GET['q']        ?? '');
$filterSev = $_GET['severity']     ?? '';
$filterCls = (int)($_GET['class_id'] ?? 0);

// ── Fetch all students for the Add Warning dropdown ───────────────
$allStudents = $db->query(
    'SELECT s.id, u.name, s.roll_no, c.name AS class_name
     FROM students s
     JOIN users u ON s.user_id = u.id
     LEFT JOIN classes c ON s.class_id = c.id
     ORDER BY c.name, u.name'
)->fetchAll();

// ── Fetch warnings with filters ───────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(u.name LIKE ? OR s.roll_no LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterSev !== '' && in_array($filterSev, ['low','medium','high'])) {
    $where[]  = 'w.severity = ?';
    $params[] = $filterSev;
}
if ($filterCls > 0) {
    $where[]  = 's.class_id = ?';
    $params[] = $filterCls;
}

$sql = 'SELECT w.*, u.name AS student_name, s.roll_no, c.name AS class_name,
               gb.name AS given_by_name
        FROM student_warnings w
        JOIN students s ON w.student_id = s.id
        JOIN users u ON s.user_id = u.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN users gb ON w.given_by = gb.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY w.created_at DESC';

$warnings      = [];
$tablesMissing = false;
try {
    $stWarn = $db->prepare($sql);
    $stWarn->execute($params);
    $warnings = $stWarn->fetchAll();
} catch (PDOException $e) {
    $tablesMissing = true;
}

$classes = getAllClasses();

pageHead('Student Warnings', 'admin');
$links = $user['role'] === 'admin' ? getAdminLinks() : getTeacherLinks();
$portal = $user['role'] === 'admin' ? 'admin' : 'teacher';
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar($portal, 'warnings', $links, $user); ?>
<div class="main-area">
<?php topbar('Student Warnings', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if ($tablesMissing): ?>
<div class="alert alert-warning d-flex gap-3 align-items-start" style="border-radius:8px">
  <i class="fas fa-database fa-lg mt-1"></i>
  <div>
    <strong>Database table missing.</strong>
    The <code>student_warnings</code> table does not exist yet. Run the SQL below in phpMyAdmin
    (<strong>SQL</strong> tab) then refresh.
    <pre class="mt-2 mb-0 p-2" style="background:#f8fafc;border-radius:6px;font-size:.8rem;border:1px solid #e5e7eb">USE bmc_portal;

CREATE TABLE IF NOT EXISTS student_warnings (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT NOT NULL,
  given_by   INT NOT NULL,
  reason     TEXT NOT NULL,
  severity   ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (given_by)   REFERENCES users(id)
) ENGINE=InnoDB;</pre>
    <div class="mt-2" style="font-size:.82rem">Or import <strong>database/warnings-migration.sql</strong> from the project folder.</div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">

  <!-- Add Warning Form -->
  <div class="col-lg-4">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-plus me-2"></i>Add Warning</div>
      <div style="padding:16px">
        <form method="POST">
          <input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Student <span class="text-danger">*</span></label>
            <select name="student_id" class="form-select form-select-sm" required>
              <option value="">— Select student —</option>
              <?php foreach ($allStudents as $s): ?>
              <option value="<?= $s['id'] ?>">
                <?= h($s['name']) ?> (<?= h($s['roll_no']) ?>) — <?= h($s['class_name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Severity <span class="text-danger">*</span></label>
            <select name="severity" class="form-select form-select-sm" required>
              <option value="low">Low — Minor infraction</option>
              <option value="medium" selected>Medium — Moderate issue</option>
              <option value="high">High — Serious concern</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">Reason <span class="text-danger">*</span></label>
            <textarea name="reason" class="form-control form-control-sm" rows="3"
                      placeholder="Describe the reason for this warning..." required></textarea>
          </div>
          <button type="submit" class="btn btn-warning btn-sm w-100">
            <i class="fas fa-exclamation-triangle me-1"></i>Issue Warning
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Warnings List -->
  <div class="col-lg-8">
    <div class="sec-card">
      <div class="sec-card-header">
        <span><i class="fas fa-list me-2"></i>Warning Records</span>
        <span class="badge bg-secondary"><?= count($warnings) ?></span>
      </div>

      <!-- Filters -->
      <form method="GET" style="padding:10px 16px;border-bottom:1px solid var(--border);background:#f7f9fb">
        <div class="row g-2 align-items-end">
          <div class="col-sm-5">
            <input type="text" name="q" class="form-control form-control-sm"
                   placeholder="Search by name or roll no..." value="<?= h($search) ?>">
          </div>
          <div class="col-sm-3">
            <select name="severity" class="form-select form-select-sm">
              <option value="">All Severities</option>
              <option value="low"    <?= $filterSev==='low'    ? 'selected':'' ?>>Low</option>
              <option value="medium" <?= $filterSev==='medium' ? 'selected':'' ?>>Medium</option>
              <option value="high"   <?= $filterSev==='high'   ? 'selected':'' ?>>High</option>
            </select>
          </div>
          <div class="col-sm-3">
            <select name="class_id" class="form-select form-select-sm">
              <option value="">All Classes</option>
              <?php foreach ($classes as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $filterCls==$c['id'] ? 'selected':'' ?>>
                <?= h($c['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-1">
            <button type="submit" class="btn btn-sm btn-primary w-100">
              <i class="fas fa-search"></i>
            </button>
          </div>
        </div>
      </form>

      <?php if (empty($warnings)): ?>
        <div class="p-4 text-center text-muted">
          <i class="fas fa-check-circle fa-2x mb-2 d-block text-success opacity-50"></i>
          No warnings found<?= $search || $filterSev || $filterCls ? ' for the selected filters' : '' ?>.
        </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Student</th>
              <th>Class</th>
              <th style="width:80px">Severity</th>
              <th>Reason</th>
              <th style="width:110px">Date</th>
              <th style="width:90px">Given By</th>
              <th style="width:60px"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($warnings as $w):
              $sevClass = match($w['severity']) {
                'high'   => 'danger',
                'medium' => 'warning',
                default  => 'info',
              };
              $rowClass = match($w['severity']) {
                'high'   => 'warning-row-high',
                'medium' => 'warning-row-medium',
                default  => '',
              };
            ?>
            <tr class="<?= $rowClass ?>">
              <td>
                <span class="student-name-warned"><?= h($w['student_name']) ?></span>
                <div style="font-size:.75rem;color:var(--t3)"><?= h($w['roll_no']) ?></div>
              </td>
              <td><span class="badge bg-light text-dark border"><?= h($w['class_name'] ?? '—') ?></span></td>
              <td>
                <span class="badge bg-<?= $sevClass ?> text-<?= $w['severity']==='medium'?'dark':'white' ?>">
                  <?= ucfirst(h($w['severity'])) ?>
                </span>
              </td>
              <td style="max-width:220px">
                <div style="font-size:.84rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                     title="<?= h($w['reason']) ?>">
                  <?= h($w['reason']) ?>
                </div>
              </td>
              <td style="font-size:.8rem;color:var(--t2)"><?= fDate($w['created_at']) ?></td>
              <td style="font-size:.8rem;color:var(--t2)"><?= h($w['given_by_name'] ?? '—') ?></td>
              <td>
                <?php if ($user['role']==='admin' || $w['given_by']==$user['id']): ?>
                <form method="POST" class="d-inline"
                      onsubmit="return confirm('Remove this warning?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $w['id'] ?>">
                  <button type="submit" class="btn btn-xs btn-outline-danger">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /row -->
</div><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /portal-wrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

// ── Download CSV Template ─────────────────────────────────────────
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="student_import_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['name','user_id','email','class_name','roll_no','dob','phone','parent_name','parent_phone','house_name']);
    $samples = [
        ['Ali Hassan',      'STU101', 'ali101@bmc.edu.pk',     '8-A',  'STU101', '2010-03-15', '03001111111', 'Hassan Ali',      '03001111110', 'Iqbal'],
        ['Fatima Malik',    'STU102', 'fatima102@bmc.edu.pk',  '8-A',  'STU102', '2010-07-22', '03002222222', 'Malik Anwar',     '03002222220', 'Jinnah'],
        ['Usman Tariq',     'STU103', 'usman103@bmc.edu.pk',   '9-A',  'STU103', '2009-01-10', '03003333333', 'Tariq Mahmood',   '03003333330', 'Liaqat'],
        ['Zainab Noor',     'STU104', 'zainab104@bmc.edu.pk',  '9-A',  'STU104', '2009-11-05', '03004444444', 'Noor Ahmed',      '03004444440', 'Fatima'],
        ['Hamza Sheikh',    'STU105', 'hamza105@bmc.edu.pk',   '10-A', 'STU105', '2008-09-18', '03005555555', 'Sheikh Imran',    '03005555550', 'Iqbal'],
        ['Ayesha Qureshi',  'STU106', 'ayesha106@bmc.edu.pk',  '10-A', 'STU106', '2008-05-30', '03006666666', 'Qureshi Saeed',   '03006666660', 'Jinnah'],
        ['Bilal Ahmed',     'STU107', 'bilal107@bmc.edu.pk',   '8-A',  'STU107', '2010-08-14', '03007777777', 'Ahmed Bilal Sr',  '03007777770', 'Liaqat'],
        ['Sana Iqbal',      'STU108', 'sana108@bmc.edu.pk',    '9-A',  'STU108', '2009-02-28', '03008888888', 'Iqbal Rashid',    '03008888880', 'Fatima'],
        ['Omer Farooq',     'STU109', 'omer109@bmc.edu.pk',    '10-A', 'STU109', '2008-12-01', '03009999999', 'Farooq Javed',    '03009999990', 'Iqbal'],
        ['Hira Baig',       'STU110', 'hira110@bmc.edu.pk',    '8-A',  'STU110', '2010-06-17', '03010101010', 'Baig Khalid',     '03010101000', 'Jinnah'],
    ];
    foreach ($samples as $row) fputcsv($out, $row);
    fclose($out);
    exit;
}

$importResults = null;

// ── Handle CSV Upload & Import ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        setFlash('danger', 'Upload error. Please try again.');
        redirect('/admin/import-students.php');
    }
    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        setFlash('danger', 'Only CSV files are accepted.');
        redirect('/admin/import-students.php');
    }

    $handle = fopen($file['tmp_name'], 'r');
    $header = fgetcsv($handle); // skip header row

    // Normalise header names
    $expectedCols = ['name','user_id','email','class_name','roll_no','dob','phone','parent_name','parent_phone','house_name'];

    $imported = 0;
    $skipped  = [];
    $rowNum   = 1;

    $db->beginTransaction();
    try {
        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count($row) < 5) {
                $skipped[] = "Row $rowNum: Too few columns.";
                continue;
            }
            // Map by position (matches template order — no password column)
            [$name, $userId, $email, $className,
             $rollNo, $dob, $phone, $parentName, $parentPhone, $houseName]
                = array_pad($row, 10, '');

            $name      = trim($name);
            $userId    = trim($userId);
            $email     = trim($email);
            $className = trim($className);
            $rollNo    = trim($rollNo);
            $houseName = trim($houseName);

            if (!$name || !$userId) {
                $skipped[] = "Row $rowNum ($userId): Name and user_id are required.";
                continue;
            }

            // Check duplicate user_id
            $dupCheck = $db->prepare('SELECT id FROM users WHERE user_id = ?');
            $dupCheck->execute([$userId]);
            if ($dupCheck->fetch()) {
                $skipped[] = "Row $rowNum ($userId): Duplicate user_id — skipped.";
                continue;
            }

            // Resolve class
            $classId = null;
            if ($className) {
                $clSt = $db->prepare('SELECT id FROM classes WHERE name = ?');
                $clSt->execute([$className]);
                $cls = $clSt->fetch();
                if ($cls) $classId = $cls['id'];
                else {
                    $skipped[] = "Row $rowNum ($userId): Class '$className' not found — skipped.";
                    continue;
                }
            }

            // Resolve house (optional)
            $houseId = null;
            if ($houseName) {
                $hSt = $db->prepare('SELECT id FROM houses WHERE name = ?');
                $hSt->execute([$houseName]);
                $house = $hSt->fetch();
                if ($house) $houseId = $house['id'];
                // If not found, just leave null — not fatal
            }

            // Generate random temp password — student sets their own via forgot-password flow
            $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

            // Insert user
            $db->prepare(
                'INSERT INTO users (user_id, name, email, password, role, status) VALUES (?,?,?,?,?,?)'
            )->execute([$userId, $name, $email ?: null, $hash, 'student', 'active']);
            $newUserId = (int)$db->lastInsertId();

            // Insert student
            $db->prepare(
                'INSERT INTO students (user_id, roll_no, class_id, house_id, admission_date, dob, phone, parent_name, parent_phone)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([
                $newUserId,
                $rollNo ?: $userId,
                $classId,
                $houseId,
                date('Y-m-d'),
                $dob ?: null,
                $phone ?: null,
                $parentName ?: null,
                $parentPhone ?: null,
            ]);

            $imported++;
        }
        $db->commit();
        logActivity($user['id'], 'student_import', "Imported $imported student(s) via CSV");
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('danger', 'Import failed: ' . $e->getMessage());
        redirect('/admin/import-students.php');
    }
    fclose($handle);

    $importResults = ['imported' => $imported, 'skipped' => $skipped];
}

pageHead('Import Students', 'admin');
$links = getAdminLinks();
?>
</head>
<body>
<div class="portal-wrap">
<?php sidebar('admin', 'import', $links, $user); ?>
<div class="main-area">
<?php topbar('Import Students', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if ($importResults !== null): ?>
<!-- Import Results -->
<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-check-circle me-2"></i>Import Results</div>
      <div style="padding:20px">
        <div class="row g-3 mb-4">
          <div class="col-6">
            <div class="stat-card text-center">
              <div class="stat-icon mx-auto" style="background:#d1fae5;color:#059669">
                <i class="fas fa-user-plus"></i>
              </div>
              <div class="stat-val text-success"><?= $importResults['imported'] ?></div>
              <div class="stat-lbl">Students Imported</div>
            </div>
          </div>
          <div class="col-6">
            <div class="stat-card text-center">
              <div class="stat-icon mx-auto" style="background:#fee2e2;color:#dc2626">
                <i class="fas fa-times-circle"></i>
              </div>
              <div class="stat-val text-danger"><?= count($importResults['skipped']) ?></div>
              <div class="stat-lbl">Rows Skipped</div>
            </div>
          </div>
        </div>
        <?php if (!empty($importResults['skipped'])): ?>
        <div class="alert alert-warning" style="font-size:.84rem">
          <strong>Skipped rows:</strong>
          <ul class="mb-0 mt-1">
            <?php foreach ($importResults['skipped'] as $s): ?>
            <li><?= h($s) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
        <div class="d-flex gap-2 mt-3">
          <a href="/admin/import-students.php" class="btn btn-primary btn-sm">
            <i class="fas fa-upload me-1"></i>Import More
          </a>
          <a href="/admin/users.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-users me-1"></i>View Users
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<div class="row justify-content-center">
  <div class="col-lg-7">

    <!-- Download Template -->
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-download me-2"></i>Step 1 — Download Template</div>
      <div style="padding:16px">
        <p class="text-muted mb-3" style="font-size:.88rem">
          Download the CSV template with the required columns and 10 sample Pakistani student rows.
          Fill in your data and upload it in Step 2.
        </p>
        <a href="/admin/import-students.php?download_template=1" class="btn btn-outline-success btn-sm">
          <i class="fas fa-file-csv me-1"></i>Download CSV Template
        </a>
        <div class="mt-3" style="font-size:.8rem;color:var(--t2)">
          <strong>Required columns:</strong>
          <code>name, user_id, email, class_name, roll_no, dob, phone, parent_name, parent_phone, house_name</code>
          <div class="alert alert-info mt-2 mb-0" style="font-size:.8rem;padding:8px 12px">
            <i class="fas fa-info-circle me-1"></i> No password column — students set their own password using Forgot Password → enter User ID → email link.
          </div>
          <ul class="mt-2 mb-0">
            <li><code>class_name</code> must exactly match an existing class (e.g. 8-A, 9-A, 10-A)</li>
            <li><code>house_name</code> is optional; must match an existing house name if provided</li>
            <li><code>dob</code> format: YYYY-MM-DD</li>
            <li>Rows with duplicate <code>user_id</code> will be skipped</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Upload CSV -->
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-upload me-2"></i>Step 2 — Upload & Import</div>
      <div style="padding:16px">
        <form method="POST" enctype="multipart/form-data">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">
              Select CSV File <span class="text-danger">*</span>
            </label>
            <input type="file" name="csv_file" class="form-control form-control-sm" accept=".csv" required>
            <div class="form-text">Maximum file size: <?= ini_get('upload_max_filesize') ?>. Only .csv files accepted.</div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-file-import me-1"></i>Import Students
          </button>
        </form>
      </div>
    </div>

  </div>
</div>
<?php endif; ?>

</div><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /portal-wrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

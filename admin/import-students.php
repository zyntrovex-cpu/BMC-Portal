<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

// ─── Auto-migration: add new biodata + houses columns if absent ──────────────
function ensureBiodataColumns(PDO $db): void {
    $existing = array_flip(
        $db->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN)
    );

    // Plain text / numeric columns — safe to add unconditionally
    $cols = [
        'student_category'   => "ENUM('civilian','cpo','sailor') DEFAULT NULL COMMENT 'Montessori wing category'",
        'gr_no'              => "VARCHAR(30)   DEFAULT NULL COMMENT 'General Register Number'",
        'kuickpay_id'        => "VARCHAR(30)   DEFAULT NULL COMMENT 'Kuickpay student ID'",
        'category'           => "VARCHAR(30)   DEFAULT NULL COMMENT 'Admission category (AOB 1, AOG 2, etc.)'",
        'academic_group'     => "VARCHAR(50)   DEFAULT NULL COMMENT 'Academic stream (Science, Arts, Pre-Medical, Commerce)'",
        'child_order'        => "TINYINT       DEFAULT NULL COMMENT 'Birth order in family'",
        'domicile'           => "VARCHAR(100)  DEFAULT NULL COMMENT 'Domicile province/district'",
        'permanent_address'  => "TEXT          DEFAULT NULL COMMENT 'Permanent home address'",
        'emergency_phone'    => "VARCHAR(20)   DEFAULT NULL COMMENT 'Emergency contact number'",
        'whatsapp_no'        => "VARCHAR(20)   DEFAULT NULL COMMENT 'WhatsApp number'",
        'religion'           => "VARCHAR(50)   DEFAULT NULL COMMENT 'Religion'",
        'sect'               => "VARCHAR(50)   DEFAULT NULL COMMENT 'Sect / denomination'",
        'blood_group'        => "VARCHAR(5)    DEFAULT NULL COMMENT 'Blood group (A+, A-, B+, B-, O+, O-, AB+, AB-)'",
        'last_school'        => "VARCHAR(200)  DEFAULT NULL COMMENT 'Last school attended'",
        'nationality'        => "VARCHAR(100)  DEFAULT NULL COMMENT 'Nationality'",
        'father_occupation'  => "VARCHAR(150)  DEFAULT NULL COMMENT 'Father/guardian occupation'",
        'documents_submitted'=> "TEXT          DEFAULT NULL COMMENT 'Documents submitted at admission'",
        'medical_info'       => "TEXT          DEFAULT NULL COMMENT 'Medical history: conditions, medications, immunizations'",
        'skills'             => "TEXT          DEFAULT NULL COMMENT 'Student skills'",
        'sports'             => "TEXT          DEFAULT NULL COMMENT 'Sports / co-curricular activities'",
        'awards'             => "TEXT          DEFAULT NULL COMMENT 'Awards, certificates, achievements'",
    ];
    foreach ($cols as $col => $def) {
        if (!isset($existing[$col])) {
            try { $db->exec("ALTER TABLE `students` ADD COLUMN `$col` $def"); } catch (Exception $e) {}
        }
    }

    // house_id — requires houses table; try with FK first, fallback to plain column
    if (!isset($existing['house_id'])) {
        try {
            // Ensure houses table exists
            $db->exec("CREATE TABLE IF NOT EXISTS `houses` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `color` VARCHAR(20) NOT NULL DEFAULT '#3b82f6',
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $db->exec("ALTER TABLE `students`
                ADD COLUMN `house_id` INT DEFAULT NULL,
                ADD CONSTRAINT `fk_student_house`
                    FOREIGN KEY (`house_id`) REFERENCES `houses`(`id`) ON DELETE SET NULL");
        } catch (Exception $e) {
            // FK failed (e.g. constraint already exists) — add plain column without FK
            try { $db->exec("ALTER TABLE `students` ADD COLUMN `house_id` INT DEFAULT NULL"); } catch (Exception $e2) {}
        }
    }
}
ensureBiodataColumns($db);

// ─── Helpers ─────────────────────────────────────────────────────────────────

/** Normalise any common date format to YYYY-MM-DD. */
function parseDateToSql(string $raw): ?string {
    if ($raw === '' || strtolower($raw) === 'dob') return null;
    $formats = [
        'Y-m-d', 'd/m/Y', 'm/d/Y', 'n/j/Y', 'j/n/Y',
        'd-m-Y', 'm-d-Y', 'j-n-Y', 'd.m.Y', 'Y/m/d',
    ];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $raw);
        if ($dt && $dt->format($fmt) === $raw) return $dt->format('Y-m-d');
    }
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d', $ts) : null;
}

/** Convert Excel column letters (A, B, ... Z, AA, AB ...) to 0-based index. */
function xlColToIdx(string $col): int {
    $col = strtoupper(trim($col));
    $n   = 0;
    for ($i = 0; $i < strlen($col); $i++) {
        $n = $n * 26 + (ord($col[$i]) - 64);
    }
    return $n - 1; // 0-based
}

/**
 * Parse an XLSX file and return rows as arrays.
 * Returns [headers_row, data_row, ...] where each row is a flat 0-indexed array.
 */
function parseXlsxRows(string $path): array {
    $zip = new ZipArchive;
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Cannot open xlsx file.');
    }

    // Shared strings
    $sharedStrings = [];
    $ss = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss) {
        $dom = new DOMDocument;
        @$dom->loadXML($ss, LIBXML_NOERROR | LIBXML_COMPACT);
        foreach ($dom->getElementsByTagName('si') as $si) {
            $sharedStrings[] = $si->textContent;
        }
    }

    // Find first worksheet
    $sheetXml = null;
    foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/Sheet1.xml'] as $try) {
        $sheetXml = $zip->getFromName($try);
        if ($sheetXml !== false) break;
    }
    // Fallback: look up the first sheet in workbook.xml.rels
    if ($sheetXml === false) {
        $wb = $zip->getFromName('xl/workbook.xml');
        if ($wb) {
            preg_match('/<sheet[^>]+r:id="(rId1)"/', $wb, $m);
            if ($m) {
                $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
                preg_match('/Id="rId1"[^>]+Target="([^"]+)"/', (string)$rels, $rm);
                if ($rm) $sheetXml = $zip->getFromName('xl/' . $rm[1]);
            }
        }
    }
    $zip->close();

    if (!$sheetXml) throw new RuntimeException('No worksheet found in xlsx file.');

    $dom = new DOMDocument;
    @$dom->loadXML($sheetXml, LIBXML_NOERROR | LIBXML_COMPACT);

    $rows = [];
    foreach ($dom->getElementsByTagName('row') as $row) {
        $rowNum  = (int)$row->getAttribute('r') - 1; // 0-based
        $rowData = [];
        foreach ($row->getElementsByTagName('c') as $c) {
            $ref = $c->getAttribute('r');
            preg_match('/^([A-Z]+)/i', $ref, $m);
            $colIdx = xlColToIdx($m[1]);

            $t      = $c->getAttribute('t');
            $vNodes = $c->getElementsByTagName('v');
            if ($vNodes->length === 0) {
                $val = '';
            } elseif ($t === 's') {
                $val = $sharedStrings[(int)$vNodes->item(0)->textContent] ?? '';
            } elseif ($t === 'inlineStr') {
                $isN = $c->getElementsByTagName('is');
                $val = $isN->length ? $isN->item(0)->textContent : '';
            } else {
                $val = $vNodes->item(0)->textContent;
            }
            $rowData[$colIdx] = $val;
        }

        if (!empty($rowData)) {
            $maxIdx  = max(array_keys($rowData));
            $fullRow = [];
            for ($i = 0; $i <= $maxIdx; $i++) {
                $fullRow[] = isset($rowData[$i]) ? trim((string)$rowData[$i]) : '';
            }
            $rows[$rowNum] = $fullRow;
        }
    }

    ksort($rows);
    return array_values($rows);
}

// ─── Expected column order (matches template header row) ─────────────────────
const IMPORT_COLS = [
    'name', 'user_id', 'email', 'class_name', 'roll_no',
    'gr_no', 'kuickpay_id', 'category', 'academic_group', 'house_name',
    'dob', 'gender', 'cnic', 'child_order', 'nationality',
    'domicile', 'religion', 'sect', 'blood_group',
    'phone', 'whatsapp_no', 'emergency_phone', 'present_address', 'permanent_address',
    'parent_name', 'father_name', 'father_occupation', 'parent_phone', 'parent_email',
    'last_school', 'documents_submitted', 'medical_info', 'skills', 'sports', 'awards',
];

// ─── Download XLSX Template ───────────────────────────────────────────────────
if (isset($_GET['download_template'])) {
    $tplPath = __DIR__ . '/../database/student_import_template.xlsx';
    if (file_exists($tplPath)) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="BMC_Student_Import_Template.xlsx"');
        header('Content-Length: ' . filesize($tplPath));
        header('Cache-Control: no-cache');
        readfile($tplPath);
        exit;
    }
    // Fallback to CSV if xlsx missing
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="student_import_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, IMPORT_COLS);
    fclose($out);
    exit;
}

$importResults = null;

// ─── Handle Upload & Import ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        setFlash('danger', 'Upload error. Please try again.');
        redirect('/admin/import-students.php');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx'])) {
        setFlash('danger', 'Only .csv and .xlsx files are accepted.');
        redirect('/admin/import-students.php');
    }

    // ── Parse rows into a uniform array ──────────────────────────────────────
    $allRows = [];
    try {
        if ($ext === 'xlsx') {
            $allRows = parseXlsxRows($file['tmp_name']);
        } else {
            $handle = fopen($file['tmp_name'], 'r');
            while (($row = fgetcsv($handle)) !== false) {
                $allRows[] = array_map('trim', $row);
            }
            fclose($handle);
        }
    } catch (Exception $e) {
        setFlash('danger', 'Could not read file: ' . $e->getMessage());
        redirect('/admin/import-students.php');
    }

    if (empty($allRows)) {
        setFlash('danger', 'The uploaded file is empty.');
        redirect('/admin/import-students.php');
    }

    // Skip header row (row 0 = headers)
    $headerRow  = array_shift($allRows);
    $imported   = 0;
    $skipped    = [];
    $rowNum     = 1;

    $db->beginTransaction();
    try {
        foreach ($allRows as $row) {
            $rowNum++;

            // Pad row to full width so array positions always exist
            $row = array_pad($row, count(IMPORT_COLS), '');

            // Map by position — matches IMPORT_COLS order
            $map = array_combine(IMPORT_COLS, array_slice($row, 0, count(IMPORT_COLS)));

            $name   = trim($map['name']    ?? '');
            $userId = trim($map['user_id'] ?? '');

            // Skip entirely empty rows (common in XLSX trailing rows)
            if ($name === '' && $userId === '') continue;

            if (!$name || !$userId) {
                $skipped[] = "Row $rowNum: 'name' and 'user_id' are required — skipped.";
                continue;
            }

            // Duplicate user_id check
            $ck = $db->prepare('SELECT id FROM users WHERE user_id = ?');
            $ck->execute([$userId]);
            if ($ck->fetch()) {
                $skipped[] = "Row $rowNum ($userId): Duplicate user_id — skipped.";
                continue;
            }

            // Resolve class (required-ish — warn but allow null)
            $classId   = null;
            $className = trim($map['class_name'] ?? '');
            if ($className !== '') {
                $clSt = $db->prepare('SELECT id FROM classes WHERE name = ?');
                $clSt->execute([$className]);
                $cls = $clSt->fetch();
                if ($cls) {
                    $classId = $cls['id'];
                } else {
                    $skipped[] = "Row $rowNum ($userId): Class '$className' not found — skipped.";
                    continue;
                }
            }

            // Resolve house (optional)
            $houseId   = null;
            $houseName = trim($map['house_name'] ?? '');
            if ($houseName !== '') {
                $hSt = $db->prepare('SELECT id FROM houses WHERE name = ?');
                $hSt->execute([$houseName]);
                $house = $hSt->fetch();
                if ($house) $houseId = $house['id'];
                // Not fatal if house name not found
            }

            // Parse & sanitise values
            $email           = $map['email']             !== '' ? $map['email']             : null;
            $rollNo          = $map['roll_no']           !== '' ? $map['roll_no']           : $userId;
            $dobSql          = parseDateToSql($map['dob'] ?? '');
            $gender          = in_array(strtolower($map['gender'] ?? ''), ['male','female','other'])
                                 ? strtolower($map['gender']) : null;
            $cnic            = $map['cnic']              !== '' ? $map['cnic']              : null;
            $phone           = $map['phone']             !== '' ? $map['phone']             : null;
            $parentName      = $map['parent_name']       !== '' ? $map['parent_name']       : null;
            $parentPhone     = $map['parent_phone']      !== '' ? $map['parent_phone']      : null;
            $parentEmail     = $map['parent_email']      !== '' ? $map['parent_email']      : null;
            $fatherName      = $map['father_name']       !== '' ? $map['father_name']       : null;
            $address         = $map['present_address']   !== '' ? $map['present_address']   : null;

            // New biodata fields
            $grNo            = $map['gr_no']             !== '' ? $map['gr_no']             : null;
            $kuickpayId      = $map['kuickpay_id']       !== '' ? $map['kuickpay_id']       : null;
            $category        = $map['category']          !== '' ? $map['category']          : null;
            $academicGroup   = $map['academic_group']    !== '' ? $map['academic_group']    : null;
            $childOrder      = $map['child_order']       !== '' ? (int)$map['child_order']  : null;
            $domicile        = $map['domicile']          !== '' ? $map['domicile']          : null;
            $permAddress     = $map['permanent_address'] !== '' ? $map['permanent_address'] : null;
            $emergencyPhone  = $map['emergency_phone']   !== '' ? $map['emergency_phone']   : null;
            $whatsappNo      = $map['whatsapp_no']       !== '' ? $map['whatsapp_no']       : null;
            $religion        = $map['religion']          !== '' ? $map['religion']          : null;
            $sect            = $map['sect']              !== '' ? $map['sect']              : null;
            $bloodGroup      = $map['blood_group']       !== '' ? strtoupper($map['blood_group']) : null;
            $lastSchool      = $map['last_school']       !== '' ? $map['last_school']       : null;
            $nationality     = $map['nationality']       !== '' ? $map['nationality']       : null;
            $fatherOccupation= $map['father_occupation'] !== '' ? $map['father_occupation'] : null;
            $docSubmitted    = $map['documents_submitted']!== ''? $map['documents_submitted']: null;
            $medicalInfo     = $map['medical_info']      !== '' ? $map['medical_info']      : null;
            $skills          = $map['skills']            !== '' ? $map['skills']            : null;
            $sports          = $map['sports']            !== '' ? $map['sports']            : null;
            $awards          = $map['awards']            !== '' ? $map['awards']            : null;

            // Blood group normalisation: accept b+, b- etc.
            if ($bloodGroup !== null) {
                $validBG = ['A+','A-','B+','B-','O+','O-','AB+','AB-'];
                $bloodGroup = in_array($bloodGroup, $validBG) ? $bloodGroup : null;
            }

            // Insert user
            $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
            $db->prepare(
                'INSERT INTO users (user_id, name, email, password, role, status)
                 VALUES (?,?,?,?,?,?)'
            )->execute([$userId, $name, $email, $hash, 'student', 'active']);
            $newUserId = (int)$db->lastInsertId();

            // Insert student (all columns)
            $db->prepare(
                'INSERT INTO students
                 (user_id, roll_no, class_id, house_id, admission_date,
                  dob, gender, cnic, phone, address, parent_phone, parent_email,
                  parent_name, father_name,
                  gr_no, kuickpay_id, category, academic_group, child_order,
                  domicile, permanent_address, emergency_phone, whatsapp_no,
                  religion, sect, blood_group, last_school, nationality,
                  father_occupation, documents_submitted, medical_info,
                  skills, sports, awards)
                 VALUES
                 (?,?,?,?,CURDATE(),
                  ?,?,?,?,?,?,?,
                  ?,?,
                  ?,?,?,?,?,
                  ?,?,?,?,
                  ?,?,?,?,?,
                  ?,?,?,
                  ?,?,?)'
            )->execute([
                $newUserId, $rollNo, $classId, $houseId,
                $dobSql, $gender, $cnic, $phone, $address, $parentPhone, $parentEmail,
                $parentName, $fatherName,
                $grNo, $kuickpayId, $category, $academicGroup, $childOrder,
                $domicile, $permAddress, $emergencyPhone, $whatsappNo,
                $religion, $sect, $bloodGroup, $lastSchool, $nationality,
                $fatherOccupation, $docSubmitted, $medicalInfo,
                $skills, $sports, $awards,
            ]);

            $imported++;
        }
        $db->commit();
        logActivity($user['id'], 'student_import', "Imported $imported student(s) from $ext file");
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('danger', 'Import failed: ' . $e->getMessage());
        redirect('/admin/import-students.php');
    }

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
<!-- ── Import Results ──────────────────────────────────────────────────────── -->
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
          <a href="<?= url('/admin/import-students.php') ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-upload me-1"></i>Import More
          </a>
          <a href="<?= url('/admin/users.php?role=student') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-users me-1"></i>View Students
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ── Upload form ─────────────────────────────────────────────────────────── -->
<div class="row justify-content-center">
  <div class="col-lg-7">

    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-download me-2"></i>Step 1 — Download Template</div>
      <div style="padding:16px">
        <p class="text-muted mb-3" style="font-size:.88rem">
          Download the XLSX import template. It contains 35 columns covering all admission-form fields,
          a Field Guide sheet with descriptions, and 5 sample rows. Delete the samples before uploading.
        </p>
        <a href="<?= url('/admin/import-students.php?download_template=1') ?>"
           class="btn btn-outline-success btn-sm">
          <i class="fas fa-file-excel me-1"></i>Download XLSX Template
        </a>
        <div class="mt-3" style="font-size:.8rem;color:var(--t2)">
          <strong>Required columns:</strong>
          <code>name</code>, <code>user_id</code> — all others are optional.<br>
          <div class="alert alert-info mt-2 mb-0" style="font-size:.8rem;padding:8px 12px">
            <i class="fas fa-info-circle me-1"></i>
            No password column — students set their own password via Forgot Password (User ID → email link).
          </div>
          <ul class="mt-2 mb-0">
            <li><code>class_name</code> must exactly match an existing class (e.g. 8-A, 10-B, ILC-A, ILC-B, Beginner, Advance, Prep, Class-1)</li>
            <li><code>house_name</code> optional; must match an existing house if provided</li>
            <li><code>dob</code> format: YYYY-MM-DD (other formats also accepted)</li>
            <li><code>gender</code>: male / female / other</li>
            <li><code>blood_group</code>: A+, A-, B+, B-, O+, O-, AB+, AB-</li>
            <li>Rows with duplicate <code>user_id</code> are skipped</li>
            <li>Both <code>.xlsx</code> and <code>.csv</code> files are accepted</li>
          </ul>
        </div>
      </div>
    </div>

    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-upload me-2"></i>Step 2 — Upload & Import</div>
      <div style="padding:16px">
        <form method="POST" enctype="multipart/form-data">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">
              Select File <span class="text-danger">*</span>
              <small class="text-muted fw-normal">.xlsx or .csv</small>
            </label>
            <input type="file" name="import_file" class="form-control form-control-sm"
                   accept=".xlsx,.csv" required>
            <div class="form-text">Max size: <?= ini_get('upload_max_filesize') ?>. Use the template column order.</div>
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

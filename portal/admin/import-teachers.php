<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/db.php';

$user = requireAuth('admin');
$db   = getDB();

// ─── Excel parser (same approach as import-students.php) ──────────────────────

function xlColToIdxT(string $col): int {
    $col = strtoupper(trim($col));
    $n   = 0;
    for ($i = 0; $i < strlen($col); $i++) {
        $n = $n * 26 + (ord($col[$i]) - 64);
    }
    return $n - 1;
}

function parseXlsxRowsT(string $path): array {
    $zip = new ZipArchive;
    if ($zip->open($path) !== true) throw new RuntimeException('Cannot open xlsx file.');

    $sharedStrings = [];
    $ss = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss) {
        $dom = new DOMDocument;
        @$dom->loadXML($ss, LIBXML_NOERROR | LIBXML_COMPACT);
        foreach ($dom->getElementsByTagName('si') as $si) {
            $sharedStrings[] = $si->textContent;
        }
    }

    $sheetXml = null;
    foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/Sheet1.xml'] as $try) {
        $sheetXml = $zip->getFromName($try);
        if ($sheetXml !== false) break;
    }
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
        $rowNum  = (int)$row->getAttribute('r') - 1;
        $rowData = [];
        foreach ($row->getElementsByTagName('c') as $c) {
            $ref = $c->getAttribute('r');
            preg_match('/^([A-Z]+)/i', $ref, $m);
            $colIdx = xlColToIdxT($m[1]);
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

// ─── Role detection ───────────────────────────────────────────────────────────

/**
 * Given a combined role string (may span multiple rows for same teacher),
 * return the most elevated portal role.
 */
function detectPortalRole(string $combinedRole): string {
    $r = strtolower($combinedRole);
    // Fix known typos
    $r = str_replace(['montessoriessori', 'fianance'], ['montessori', 'finance'], $r);

    $checks = [
        'admin'                       => ['principal', 'admin'],
        'finance'                     => ['finance'],
        'student_affairs'             => ['student affairs'],
        'vp_montessori'               => ['vp montessori', 'montessori wing', 'montessoriessori'],
        'ilc_vp'                      => ['vp ilc', 'ilc vp'],
        'vp_main'                     => ['vp main', 'vice principal main'],
        'higher_secondary_wing_head'  => ['higher secondary wing head'],
        'secondary_wing_head'         => ['secondary wing head'],
        'primary_wing_head'           => ['primary wing head'],
        'examination_head'            => ['examination head', 'exam head'],
        'wing_head'                   => ['coordinator'],
        'montessori_teacher'          => ['teacher montessori', 'montessori teacher', 'montessori wing'],
        'ilc_teacher'                 => ['teacher ilc', 'ilc teacher'],
        'teacher'                     => ['teacher main', 'main campus', 'teacher'],
    ];
    foreach ($checks as $role => $keywords) {
        foreach ($keywords as $kw) {
            if (str_contains($r, $kw)) return $role;
        }
    }
    return 'teacher';
}

/** Derive wing from portal role. */
function roleToWing(string $role): string {
    if (in_array($role, ['montessori_teacher', 'vp_montessori', 'wing_head'])) return 'montessori';
    if (in_array($role, ['ilc_teacher', 'ilc_vp']))                            return 'ilc';
    return 'main';
}

// ─── Subject typo map ─────────────────────────────────────────────────────────

const SUBJECT_TYPOS = [
    'mathemaics'   => 'Mathematics',
    'mathematics'  => 'Mathematics',
    'english'      => 'English',
    'urdu'         => 'Urdu',
    'physics'      => 'Physics',
    'chemistry'    => 'Chemistry',
    'biology'      => 'Biology',
    'computer'     => 'Computer',
    'islamiat'     => 'Islamiat',
    'pak studies'  => 'Pakistan Studies',
    'pst'          => 'Pakistan Studies',
    'history'      => 'History',
    'geography'    => 'Geography',
    'civics'       => 'Civics',
    'economics'    => 'Economics',
    'accounting'   => 'Accounting',
    'commerce'     => 'Commerce',
    'statistics'   => 'Statistics',
];

function normalizeSubjectName(string $name): string {
    $lower = strtolower(trim($name));
    return SUBJECT_TYPOS[$lower] ?? ucwords($lower);
}

// ─── Subject lookup ───────────────────────────────────────────────────────────

function findSubjectId(PDO $db, string $rawName): ?int {
    $name = normalizeSubjectName($rawName);
    // Exact (case-insensitive)
    $st = $db->prepare('SELECT id FROM subjects WHERE LOWER(name)=LOWER(?) LIMIT 1');
    $st->execute([$name]);
    $id = $st->fetchColumn();
    if ($id) return (int)$id;
    // LIKE partial
    $st = $db->prepare('SELECT id FROM subjects WHERE LOWER(name) LIKE LOWER(?) LIMIT 1');
    $st->execute(['%' . $name . '%']);
    $id = $st->fetchColumn();
    return $id ? (int)$id : null;
}

// ─── Class lookup for subject assignments (Main Campus by grade) ──────────────

function findClassByGrade(PDO $db, int $grade): ?int {
    // Main Campus, non-ILC, non-Montessori classes with this grade
    $st = $db->prepare(
        "SELECT id FROM classes
         WHERE COALESCE(wing,'main')='main'
           AND COALESCE(is_montessori,0)=0
           AND COALESCE(is_ilc,0)=0
           AND grade=?
         ORDER BY name LIMIT 1"
    );
    $st->execute([$grade]);
    $id = $st->fetchColumn();
    return $id ? (int)$id : null;
}

// ─── Class lookup for "Class Teacher KEYWORD" patterns ───────────────────────

function findClassByTeacherKeyword(PDO $db, string $keyword): ?array {
    // Returns ['id' => int, 'name' => string] or null
    $kw  = trim($keyword);
    $kwL = strtolower($kw);

    // Numeric: "2" or "3" → Main Campus Class 2/3
    if (in_array($kwL, ['2', '3'])) {
        $grade = (int)$kw;
        $st = $db->prepare(
            "SELECT id, name FROM classes
             WHERE COALESCE(wing,'main')='main'
               AND COALESCE(is_montessori,0)=0
               AND COALESCE(is_ilc,0)=0
               AND grade=?
             ORDER BY name LIMIT 1"
        );
        $st->execute([$grade]);
        $row = $st->fetch();
        return $row ? ['id' => (int)$row['id'], 'name' => $row['name']] : null;
    }

    // "PREP" → Montessori PREP
    if ($kwL === 'prep') {
        $st = $db->prepare(
            "SELECT id, name FROM classes
             WHERE is_montessori=1 AND (LOWER(name) LIKE '%prep%' OR grade=0)
             ORDER BY name LIMIT 1"
        );
        $st->execute();
        $row = $st->fetch();
        return $row ? ['id' => (int)$row['id'], 'name' => $row['name']] : null;
    }

    // "1" or "ONE" → Montessori grade 1
    if (in_array($kwL, ['1', 'one'])) {
        $st = $db->prepare(
            "SELECT id, name FROM classes
             WHERE is_montessori=1 AND grade=1
             ORDER BY name LIMIT 1"
        );
        $st->execute();
        $row = $st->fetch();
        return $row ? ['id' => (int)$row['id'], 'name' => $row['name']] : null;
    }

    // "Beginners (SunFlower)", "ADVANCE LILLY", etc. — multi-word, possibly with ()
    // Try exact LOWER match first
    $st = $db->prepare('SELECT id, name FROM classes WHERE LOWER(name)=LOWER(?) LIMIT 1');
    $st->execute([$kw]);
    $row = $st->fetch();
    if ($row) return ['id' => (int)$row['id'], 'name' => $row['name']];

    // Try LIKE with every significant word
    // Strip parentheses, split by space
    $plain = preg_replace('/[()\/\\\\]/', ' ', $kwL);
    $words = array_filter(explode(' ', $plain), fn($w) => strlen($w) > 2);
    if (!empty($words)) {
        $sql   = "SELECT id, name FROM classes WHERE ";
        $parts = [];
        $vals  = [];
        foreach ($words as $w) {
            $parts[] = 'LOWER(name) LIKE ?';
            $vals[]  = '%' . $w . '%';
        }
        $sql .= implode(' AND ', $parts) . ' ORDER BY name LIMIT 1';
        $st = $db->prepare($sql);
        $st->execute($vals);
        $row = $st->fetch();
        if ($row) return ['id' => (int)$row['id'], 'name' => $row['name']];
    }

    // Broad single-keyword fallback (first word only)
    $firstWord = $words ? reset($words) : $kwL;
    if (strlen($firstWord) > 2) {
        $st = $db->prepare('SELECT id, name FROM classes WHERE LOWER(name) LIKE ? ORDER BY name LIMIT 1');
        $st->execute(['%' . $firstWord . '%']);
        $row = $st->fetch();
        if ($row) return ['id' => (int)$row['id'], 'name' => $row['name']];
    }

    return null;
}

// ─── Parse one TOTAL SUBJECTS cell into assignment tokens ────────────────────

/**
 * One cell may contain multiple assignments separated by newlines (Alt+Enter in Excel).
 * Returns array of ['type'=>'subject'|'class_teacher'|'unknown', ...fields].
 */
function parseAssignmentCell(string $raw): array {
    $lines  = preg_split('/[\r\n]+/', $raw);
    $result = [];
    // Re-assemble "Class Teacher\nKEYWORD" across line breaks
    $buffer = '';
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        // If previous line started "class teacher" but had no keyword yet
        if ($buffer !== '' && !preg_match('/\S/', $buffer)) {
            $buffer = '';
        }
        if ($buffer !== '') {
            $combined = $buffer . ' ' . $line;
            $buffer   = '';
            $tok      = parseSingleAssignment($combined);
            $result[] = $tok;
            continue;
        }

        $lc = strtolower($line);
        // "class teacher" with nothing after → buffer
        if (preg_match('/^class\s+teacher\s*$/i', $line)) {
            $buffer = $line;
            continue;
        }

        $result[] = parseSingleAssignment($line);
    }
    if ($buffer !== '') {
        $result[] = ['type' => 'unknown', 'raw' => $buffer];
    }
    return $result;
}

function parseSingleAssignment(string $raw): array {
    $raw = trim(preg_replace('/\s+/', ' ', $raw));
    if ($raw === '') return ['type' => 'empty'];

    // "Class Teacher KEYWORD"
    if (preg_match('/^class\s+teacher\s+(.+)$/i', $raw, $m)) {
        return ['type' => 'class_teacher', 'keyword' => trim($m[1])];
    }

    // "SubjectName GradeNumber" — e.g. "Biology 9", "Maths 10"
    // Grade must be 1–13; capture trailing digits
    if (preg_match('/^(.+?)\s+(\d{1,2})$/i', $raw, $m)) {
        $grade = (int)$m[2];
        if ($grade >= 1 && $grade <= 13) {
            return ['type' => 'subject', 'subject' => trim($m[1]), 'grade' => $grade];
        }
    }

    return ['type' => 'unknown', 'raw' => $raw];
}

// ─── Generate default password from emp_id ───────────────────────────────────

function defaultPasswordForEmpId(string $empId): string {
    // empId like "BMC/Emp-8001" → "BMC@8001"; fallback to "Teacher@123"
    if (preg_match('/(\d{4,})/', $empId, $m)) {
        return 'BMC@' . $m[1];
    }
    return 'Teacher@123';
}

// ─── Handle import POST ───────────────────────────────────────────────────────

$importResults = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        setFlash('danger', 'Upload error. Please try again.');
        redirect('/portal/admin/import-teachers.php');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'xlsx') {
        setFlash('danger', 'Only .xlsx files are accepted for teacher import.');
        redirect('/portal/admin/import-teachers.php');
    }

    try {
        $allRows = parseXlsxRowsT($file['tmp_name']);
    } catch (Exception $e) {
        setFlash('danger', 'Could not read file: ' . $e->getMessage());
        redirect('/portal/admin/import-teachers.php');
    }

    if (empty($allRows)) {
        setFlash('danger', 'The uploaded file is empty.');
        redirect('/portal/admin/import-teachers.php');
    }

    // ── Detect header row ─────────────────────────────────────────────────────
    // Strategy 1: score-based keyword scan (≥2 header keywords = header row)
    $hdrKeywords = ['full name','user id','total subjects','qualification','email',
                    's.no','s no','role','appointment','subject','employee'];
    $headerIdx = null;
    foreach ($allRows as $i => $row) {
        $joined = strtolower(implode(' ', $row));
        $score  = 0;
        foreach ($hdrKeywords as $kw) {
            if (str_contains($joined, $kw)) $score++;
        }
        if ($score >= 2) { $headerIdx = $i; break; }
    }
    // Strategy 2: row with most non-numeric text cells (typical of a header row)
    if ($headerIdx === null) {
        $bestScore = 0;
        foreach ($allRows as $i => $row) {
            $textCount = count(array_filter($row, fn($c) => $c !== '' && !is_numeric($c)));
            if ($textCount > $bestScore) { $bestScore = $textCount; $headerIdx = $i; }
        }
    }
    if ($headerIdx === null) $headerIdx = 0;

    $headerRow = $allRows[$headerIdx];
    $dataRows  = array_slice($allRows, $headerIdx + 1);

    // ── Build column index map from header ────────────────────────────────────
    $colMap = []; // canonical name → 0-based index
    $hdrAliases = [
        // S.NO variants
        's.no'             => 'sno', 's no'            => 'sno',
        'sno'              => 'sno', 'serial'          => 'sno',   'sr'      => 'sno',
        // Name variants
        'full name'        => 'name', 'name'           => 'name',
        'teacher name'     => 'name', 'staff name'     => 'name',
        // Employee ID variants
        'user id'          => 'emp_id', 'userid'       => 'emp_id',
        'employee id'      => 'emp_id', 'emp id'       => 'emp_id',
        'emp no'           => 'emp_id', 'id'           => 'emp_id',
        'staff id'         => 'emp_id',
        // Role variants
        'role'             => 'role', 'appointment'    => 'role',
        'additional duties'=> 'role', 'designation'   => 'role',
        // Email
        'email'            => 'email', 'e mail'        => 'email',
        // Subject
        'specific subject' => 'subject', 'subject'     => 'subject',
        'main subject'     => 'subject',
        // Qualification
        'qualification'    => 'qualification', 'qualif' => 'qualification',
        // Total subjects / class assignments
        'total subjects'   => 'total_subjects',
        'class assignment' => 'total_subjects',
        'subjects'         => 'total_subjects',
    ];
    foreach ($headerRow as $ci => $hdr) {
        $norm = strtolower(trim(preg_replace('/[^a-z0-9 ]/i', ' ', $hdr)));
        $norm = trim(preg_replace('/\s+/', ' ', $norm));
        if ($norm === '') continue;
        $canonical = $hdrAliases[$norm] ?? null;
        if (!$canonical) {
            // Partial: header contains alias keyword
            foreach ($hdrAliases as $kw => $can) {
                if (str_contains($norm, $kw)) { $canonical = $can; break; }
            }
        }
        if ($canonical && !isset($colMap[$canonical])) {
            $colMap[$canonical] = $ci;
        }
    }

    // ── Positional fallback ───────────────────────────────────────────────────
    $positional = !isset($colMap['name']) && !isset($colMap['emp_id']);
    if ($positional) {
        // Auto-detect column offset:
        // If the first data row's col0 is a small sequential number and col1 is non-numeric text
        // → S.NO is at col0, no leading unnamed/index column.
        // Otherwise assume a leading unnamed column before S.NO.
        $firstDataRow = $dataRows[0] ?? [];
        $c0 = trim($firstDataRow[0] ?? '');
        $c1 = trim($firstDataRow[1] ?? '');
        if ($c0 !== '' && is_numeric($c0) && (int)$c0 <= 50 && $c1 !== '' && !is_numeric($c1)) {
            // No leading unnamed column: S.NO=0, NAME=1, USER_ID=2 …
            $colMap = ['sno'=>0,'name'=>1,'emp_id'=>2,'role'=>3,'email'=>4,'subject'=>5,'qualification'=>6,'total_subjects'=>7];
        } else {
            // Leading unnamed column present: S.NO=1, NAME=2, USER_ID=3 …
            $colMap = ['sno'=>1,'name'=>2,'emp_id'=>3,'role'=>4,'email'=>5,'subject'=>6,'qualification'=>7,'total_subjects'=>8];
        }
    }

    $gc = fn(array $row, string $col) => isset($colMap[$col]) ? trim($row[$colMap[$col]] ?? '') : '';

    // ── Group rows by emp_id (same teacher → multiple assignment rows) ────────
    $teacherMap  = []; // emp_id → teacher data array
    $blankSkipped = 0;
    foreach ($dataRows as $row) {
        $sno     = $gc($row, 'sno');
        $name    = $gc($row, 'name');
        $empId   = $gc($row, 'emp_id');
        $role    = $gc($row, 'role');
        $email   = $gc($row, 'email');
        $subject = $gc($row, 'subject');
        $qual    = $gc($row, 'qualification');
        $total   = $gc($row, 'total_subjects');

        // Skip blank rows
        if ($name === '' && $empId === '') { $blankSkipped++; continue; }

        $key = $empId !== '' ? $empId : ('__name__' . strtolower($name));

        if (!isset($teacherMap[$key])) {
            $teacherMap[$key] = [
                'sno'           => $sno,
                'name'          => $name,
                'emp_id'        => $empId,
                'roles'         => [],
                'email'         => $email,
                'subject'       => $subject,
                'qualification' => $qual,
                'assignments'   => [],
            ];
        }

        // Accumulate roles (each row can repeat/add a role)
        if ($role !== '') $teacherMap[$key]['roles'][] = $role;

        // Fill empty fields from later rows
        if ($teacherMap[$key]['email'] === '' && $email !== '') $teacherMap[$key]['email'] = $email;
        if ($teacherMap[$key]['subject'] === '' && $subject !== '') $teacherMap[$key]['subject'] = $subject;
        if ($teacherMap[$key]['qualification'] === '' && $qual !== '') $teacherMap[$key]['qualification'] = $qual;
        if ($teacherMap[$key]['name'] === '' && $name !== '') $teacherMap[$key]['name'] = $name;

        // Collect assignment tokens from this row
        if ($total !== '') {
            $tokens = parseAssignmentCell($total);
            foreach ($tokens as $tok) {
                if ($tok['type'] !== 'empty') $teacherMap[$key]['assignments'][] = $tok;
            }
        }
    }

    // ── Process each teacher ──────────────────────────────────────────────────
    $created      = 0;
    $updated      = 0;
    $skipped      = [];
    $assignStats  = ['subjects' => 0, 'class_teachers' => 0];
    $warnings     = [];
    $unmatched    = [];
    $details      = []; // per-teacher summary

    $db->beginTransaction();
    try {
        foreach ($teacherMap as $key => $t) {
            $name   = $t['name'];
            $empId  = $t['emp_id'];
            $email  = $t['email'] !== '' ? $t['email'] : null;
            $qual   = $t['qualification'];

            // Validate minimum fields
            if ($name === '' || $empId === '') {
                $skipped[] = "Row (S.NO {$t['sno']}): Missing name or User ID — skipped.";
                continue;
            }

            // Detect role
            $combinedRole = implode(' / ', array_unique($t['roles']));
            $portalRole   = detectPortalRole($combinedRole);
            $wing         = roleToWing($portalRole);
            $isIlc        = $wing === 'ilc' ? 1 : 0;

            // Look up or create user account
            $uSt = $db->prepare('SELECT id FROM users WHERE user_id=?');
            $uSt->execute([$empId]);
            $existingUserId = $uSt->fetchColumn();

            $isNew = false;
            if ($existingUserId) {
                // Update existing user
                $db->prepare('UPDATE users SET name=?, role=?, email=? WHERE id=?')
                   ->execute([$name, $portalRole, $email, (int)$existingUserId]);
                $userId = (int)$existingUserId;
            } else {
                // Create user with default password derived from emp_id
                $pwd  = defaultPasswordForEmpId($empId);
                $hash = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare(
                    'INSERT INTO users (user_id, name, email, password, role, status) VALUES (?,?,?,?,?,?)'
                )->execute([$empId, $name, $email, $hash, $portalRole, 'active']);
                $userId = (int)$db->lastInsertId();
                $isNew  = true;
            }

            // Look up specialist subject (SPECIFIC SUBJECT column)
            $specSubjectId = null;
            if ($t['subject'] !== '') {
                $specSubjectId = findSubjectId($db, $t['subject']);
            }

            // Look up or create teacher record
            $tSt = $db->prepare('SELECT id FROM teachers WHERE user_id=?');
            $tSt->execute([$userId]);
            $existingTeacherId = $tSt->fetchColumn();

            if ($existingTeacherId) {
                $db->prepare(
                    'UPDATE teachers SET emp_id=?, subject_id=?, qualification=?, is_ilc=?, wing=? WHERE id=?'
                )->execute([$empId, $specSubjectId, ($qual ?: null), $isIlc, $wing, (int)$existingTeacherId]);
                $teacherId = (int)$existingTeacherId;
                $updated++;
            } else {
                $db->prepare(
                    'INSERT INTO teachers (user_id, emp_id, subject_id, qualification, is_ilc, wing)
                     VALUES (?,?,?,?,?,?)'
                )->execute([$userId, $empId, $specSubjectId, ($qual ?: null), $isIlc, $wing]);
                $teacherId = (int)$db->lastInsertId();
                if (!$isNew) $updated++; else $created++;
            }

            // ── Process assignments ───────────────────────────────────────────
            $myAssignments = [];
            foreach ($t['assignments'] as $tok) {
                if ($tok['type'] === 'subject') {
                    // Main Campus subject + class by grade
                    $subId   = findSubjectId($db, $tok['subject']);
                    $classId = findClassByGrade($db, $tok['grade']);

                    if (!$subId) {
                        $unmatched[] = "$name ({$empId}): subject '{$tok['subject']}' not found in DB.";
                        continue;
                    }
                    if (!$classId) {
                        $unmatched[] = "$name ({$empId}): no Main Campus class found for grade {$tok['grade']}.";
                        continue;
                    }

                    // Insert or update class_subjects
                    try {
                        $db->prepare(
                            'INSERT INTO class_subjects (class_id, subject_id, teacher_id)
                             VALUES (?,?,?)
                             ON DUPLICATE KEY UPDATE teacher_id=VALUES(teacher_id)'
                        )->execute([$classId, $subId, $teacherId]);
                        $assignStats['subjects']++;
                        $myAssignments[] = normalizeSubjectName($tok['subject']) . ' Gr.' . $tok['grade'];
                    } catch (Exception $eA) {
                        $warnings[] = "$name: assignment '{$tok['subject']} {$tok['grade']}' failed: " . $eA->getMessage();
                    }

                } elseif ($tok['type'] === 'class_teacher') {
                    $found = findClassByTeacherKeyword($db, $tok['keyword']);
                    if (!$found) {
                        $unmatched[] = "$name ({$empId}): Class Teacher keyword '{$tok['keyword']}' matched no class in DB.";
                        continue;
                    }

                    try {
                        $db->prepare(
                            'INSERT INTO class_teacher_assignments (class_id, teacher_id)
                             VALUES (?,?)
                             ON DUPLICATE KEY UPDATE teacher_id=VALUES(teacher_id)'
                        )->execute([$found['id'], $teacherId]);
                        $assignStats['class_teachers']++;
                        $myAssignments[] = 'Class Teacher: ' . $found['name'];
                    } catch (Exception $eA) {
                        $warnings[] = "$name: class teacher assignment '{$tok['keyword']}' failed: " . $eA->getMessage();
                    }

                } else {
                    if ($tok['type'] === 'unknown') {
                        $raw = $tok['raw'] ?? '';
                        if ($raw !== '') {
                            $unmatched[] = "$name ({$empId}): unrecognised assignment value: '$raw'.";
                        }
                    }
                }
            }

            $details[] = [
                'name'        => $name,
                'emp_id'      => $empId,
                'role'        => $portalRole,
                'is_new'      => $isNew,
                'assignments' => $myAssignments,
            ];
        }

        $db->commit();
        logActivity($user['id'], 'teacher_import',
            "Imported $created new, updated $updated teachers from xlsx");
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('danger', 'Import failed: ' . $e->getMessage());
        redirect('/portal/admin/import-teachers.php');
    }

    $importResults = [
        'created'          => $created,
        'updated'          => $updated,
        'skipped'          => $skipped,
        'warnings'         => $warnings,
        'unmatched'        => $unmatched,
        'assign_stats'     => $assignStats,
        'details'          => $details,
        'header_based'     => !$positional,
        'detected_cols'    => array_keys($colMap),
        'col_map'          => $colMap,
        'total_data_rows'  => count($dataRows),
        'blank_skipped'    => $blankSkipped,
        'header_row_preview'=> array_slice($headerRow, 0, 10),
        'first_data_preview'=> array_slice($dataRows[0] ?? [], 0, 10),
        'header_idx'       => $headerIdx,
    ];
}

// ─── Page render ──────────────────────────────────────────────────────────────

pageHead('Import Teachers', 'admin');
$links = getAdminLinks();
?>
<div class="portal-wrap">
<?php sidebar('admin', 'import-teachers', $links, $user); ?>
<div class="main-area">
<?php topbar('Import Teachers', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Section switcher -->
<div class="d-flex gap-2 mb-3 flex-wrap">
  <a href="<?= url('/portal/admin/import-students.php') ?>"
     class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-user-graduate me-1"></i>Import Students
  </a>
  <span class="btn btn-sm btn-primary" style="cursor:default">
    <i class="fas fa-chalkboard-teacher me-1"></i>Import Teachers
  </span>
</div>

<?php if ($importResults !== null): ?>
<!-- ── Results ──────────────────────────────────────────────────────────────── -->
<div class="row justify-content-center">
  <div class="col-lg-9">
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-check-circle me-2"></i>Import Results</div>
      <div style="padding:20px">

        <!-- Stats -->
        <div class="row g-3 mb-4">
          <div class="col-6 col-lg-3">
            <div class="stat-card text-center">
              <div class="stat-icon mx-auto" style="background:#d1fae5;color:#059669"><i class="fas fa-user-plus"></i></div>
              <div class="stat-val text-success"><?= $importResults['created'] ?></div>
              <div class="stat-lbl">Created</div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="stat-card text-center">
              <div class="stat-icon mx-auto" style="background:#dbeafe;color:#1d4ed8"><i class="fas fa-sync-alt"></i></div>
              <div class="stat-val" style="color:#1d4ed8"><?= $importResults['updated'] ?></div>
              <div class="stat-lbl">Updated</div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="stat-card text-center">
              <div class="stat-icon mx-auto" style="background:#d1fae5;color:#059669"><i class="fas fa-chalkboard"></i></div>
              <div class="stat-val text-success"><?= $importResults['assign_stats']['subjects'] ?></div>
              <div class="stat-lbl">Subject Assignments</div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="stat-card text-center">
              <div class="stat-icon mx-auto" style="background:#fdf4ff;color:#7c3aed"><i class="fas fa-school"></i></div>
              <div class="stat-val" style="color:#7c3aed"><?= $importResults['assign_stats']['class_teachers'] ?></div>
              <div class="stat-lbl">Class Teacher Assignments</div>
            </div>
          </div>
        </div>

        <!-- Column detection -->
        <div class="alert alert-<?= $importResults['header_based'] ? 'info' : 'warning' ?> mb-3" style="font-size:.82rem;padding:8px 12px">
          <i class="fas fa-<?= $importResults['header_based'] ? 'columns' : 'exclamation-triangle' ?> me-1"></i>
          <?php if ($importResults['header_based']): ?>
            <strong>Header-based mapping</strong> — detected <?= count($importResults['detected_cols']) ?> column(s):
            <?= implode(', ', array_map('h', $importResults['detected_cols'])) ?>
          <?php else: ?>
            <strong>Positional mapping used</strong>
            (column headers were not recognised — fell back to fixed column positions).
            Mapped as: <?= implode(', ', array_map(fn($k,$v)=>"<code>col$v=$k</code>", array_keys($importResults['col_map']), $importResults['col_map'])) ?>
          <?php endif; ?>
          &nbsp;·&nbsp; <strong><?= $importResults['total_data_rows'] ?></strong> data row(s) found
          (<?= $importResults['blank_skipped'] ?> blank, header at row <?= $importResults['header_idx'] ?>).
        </div>

        <?php if (($importResults['created'] + $importResults['updated']) === 0 && $importResults['total_data_rows'] > 0): ?>
        <!-- Diagnostic: show header + first data row to help debug column mismatch -->
        <div class="alert alert-danger mb-3" style="font-size:.82rem;padding:10px 14px">
          <i class="fas fa-bug me-1"></i>
          <strong>0 teachers were processed — column mapping may be wrong.</strong>
          <br>Below is what the importer read from your file. Check that the column positions match your data.
          <div class="table-responsive mt-2">
            <table class="table table-sm table-bordered mb-1" style="font-size:.75rem">
              <thead><tr><th>Col #</th>
                <?php for ($ci=0;$ci<count($importResults['header_row_preview']);$ci++): ?><th><?= $ci ?></th><?php endfor; ?>
              </tr></thead>
              <tbody>
                <tr><td class="fw-bold">Header row</td>
                  <?php foreach ($importResults['header_row_preview'] as $c): ?><td><?= h($c) ?></td><?php endforeach; ?>
                </tr>
                <tr><td class="fw-bold">First data row</td>
                  <?php foreach ($importResults['first_data_preview'] as $c): ?><td><?= h($c) ?></td><?php endforeach; ?>
                </tr>
              </tbody>
            </table>
          </div>
          <strong>Mapped to:</strong>
          <?php foreach ($importResults['col_map'] as $field => $colIdx): ?>
            <code><?= h($field) ?>=col<?= $colIdx ?>
              (<?= h($importResults['first_data_preview'][$colIdx] ?? 'n/a') ?>)</code>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Per-teacher detail table -->
        <?php if (!empty($importResults['details'])): ?>
        <div class="table-responsive mb-3">
          <table class="table table-sm table-hover" style="font-size:.8rem">
            <thead style="background:#f8fafc">
              <tr>
                <th style="padding:7px 10px">Name</th>
                <th style="padding:7px 10px">Emp ID</th>
                <th style="padding:7px 10px">Role</th>
                <th style="padding:7px 10px">Status</th>
                <th style="padding:7px 10px">Assignments</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($importResults['details'] as $d): ?>
              <tr>
                <td style="padding:7px 10px;font-weight:600"><?= h($d['name']) ?></td>
                <td style="padding:7px 10px;color:#475569"><?= h($d['emp_id']) ?></td>
                <td style="padding:7px 10px">
                  <span class="badge bg-secondary" style="font-size:.7rem"><?= h($d['role']) ?></span>
                </td>
                <td style="padding:7px 10px">
                  <span class="badge <?= $d['is_new'] ? 'bg-success' : 'bg-primary' ?>" style="font-size:.7rem">
                    <?= $d['is_new'] ? 'Created' : 'Updated' ?>
                  </span>
                </td>
                <td style="padding:7px 10px;color:#475569;font-size:.76rem">
                  <?= !empty($d['assignments']) ? implode(', ', array_map('h', $d['assignments'])) : '<span class="text-muted">—</span>' ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <!-- Unmatched assignments -->
        <?php if (!empty($importResults['unmatched'])): ?>
        <div class="alert alert-warning mb-3" style="font-size:.83rem;padding:10px 14px">
          <i class="fas fa-exclamation-triangle me-1"></i>
          <strong><?= count($importResults['unmatched']) ?> assignment(s) could not be matched</strong>
          — the teacher account was still created, but these assignments need to be set up manually
          in <a href="<?= url('/portal/admin/classes.php') ?>">Classes &amp; Subjects</a>:
          <ul class="mb-0 mt-1">
            <?php foreach ($importResults['unmatched'] as $u): ?>
            <li><?= h($u) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <!-- Skipped rows -->
        <?php if (!empty($importResults['skipped'])): ?>
        <div class="alert alert-danger mb-3" style="font-size:.83rem;padding:10px 14px">
          <i class="fas fa-times-circle me-1"></i>
          <strong>Skipped rows:</strong>
          <ul class="mb-0 mt-1">
            <?php foreach ($importResults['skipped'] as $s): ?>
            <li><?= h($s) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <!-- Warnings -->
        <?php if (!empty($importResults['warnings'])): ?>
        <div class="alert alert-secondary mb-3" style="font-size:.83rem;padding:10px 14px">
          <i class="fas fa-info-circle me-1"></i>
          <strong>Warnings:</strong>
          <ul class="mb-0 mt-1">
            <?php foreach ($importResults['warnings'] as $w): ?>
            <li><?= h($w) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <div class="d-flex gap-2 mt-3">
          <a href="<?= url('/portal/admin/import-teachers.php') ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-upload me-1"></i>Import More
          </a>
          <a href="<?= url('/portal/admin/users.php?role=teacher') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-chalkboard-teacher me-1"></i>View Teachers
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ── Upload form ──────────────────────────────────────────────────────────── -->
<div class="row justify-content-center">
  <div class="col-lg-7">

    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-info-circle me-2"></i>Excel File Format</div>
      <div style="padding:16px">
        <p class="text-muted mb-2" style="font-size:.88rem">
          Upload the teacher Excel file exported from your records. The importer automatically
          detects column headers and groups multiple rows for the same teacher.
        </p>
        <div class="alert alert-info mb-2" style="font-size:.8rem;padding:8px 12px">
          <i class="fas fa-columns me-1"></i>
          <strong>Expected columns (header row required):</strong>
          <code>S.NO</code>, <code>FULL NAME</code>, <code>USER ID</code>,
          <code>ROLE</code>, <code>EMAIL</code>,
          <code>SPECIFIC SUBJECT</code>, <code>QUALIFICATION</code>,
          <code>TOTAL SUBJECTS</code>
        </div>
        <div style="font-size:.8rem;color:var(--t2)">
          <ul class="mb-0">
            <li><code>USER ID</code> is used as the teacher's unique login (e.g. <code>BMC/Emp-8001</code>).
                Default password: <strong>BMC@<em>XXXX</em></strong> (last 4+ digits of the ID).</li>
            <li><code>ROLE</code> maps automatically: <em>Teacher Main Campus</em> → <code>teacher</code>,
                <em>Teacher Montessori Wing</em> → <code>montessori_teacher</code>,
                <em>VP Main Campus</em> → <code>vp_main</code>, etc.</li>
            <li><code>TOTAL SUBJECTS</code> — two formats:
              <ul>
                <li><strong>Subject + Grade:</strong> <code>Biology 9</code>, <code>Mathematics 10</code>
                    → assigns teacher to that subject in the matching Main Campus class.</li>
                <li><strong>Class Teacher:</strong> <code>Class Teacher PREP</code>,
                    <code>Class Teacher Beginners (SunFlower)</code>, <code>Class Teacher 2</code>
                    → assigns as class teacher (Montessori/ILC or Main Campus Class 2/3).</li>
              </ul>
            </li>
            <li>One teacher may span <strong>multiple rows</strong> (one row per subject/assignment) — all rows with the same <code>USER ID</code> are merged automatically.</li>
            <li>Re-importing an existing <code>USER ID</code> <strong>updates</strong> the record (no duplicates).</li>
            <li>Only <code>.xlsx</code> files are accepted.</li>
          </ul>
        </div>
      </div>
    </div>

    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-upload me-2"></i>Upload Teacher Excel File</div>
      <div style="padding:16px">
        <form method="POST" enctype="multipart/form-data">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem">
              Select .xlsx File <span class="text-danger">*</span>
            </label>
            <input type="file" name="import_file" class="form-control form-control-sm"
                   accept=".xlsx" required>
            <div class="form-text">Max size: <?= ini_get('upload_max_filesize') ?>.</div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-file-import me-1"></i>Import Teachers
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

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp');
$db   = getDB();

$tableExists = false;
try { $db->query('SELECT 1 FROM ilc_academic_results2 LIMIT 0'); $tableExists = true; } catch (Exception $e) {}

// ── Constants ─────────────────────────────────────────────────────────────────
$FIXED_SUBJECTS = ['English','Urdu','Math','Islamiat','G.K.','Computer'];
$EXTRA_ROWS     = 4;   // blank rows for additional subjects
$TOTAL_ROWS     = count($FIXED_SUBJECTS) + $EXTRA_ROWS;

$TERMS = ['First Term','Mid Term','Final Term','Annual Exam'];
$GRADES = ['A++','A+','A','B++','B+','B','C','D','E','U','N.A.'];

$SKILLS_PAIRS = [
    ['motor_skills','Motor skills',                'eye_hand','Eye & hand coordination'],
    ['psycho_cognitive','Psycho cognitive',         'attention_span','Attention span / concentration'],
    ['behavior','Behavior',                         'auditory_skills','Auditory skills'],
    ['speech_improvement','Speech improvement',     'vegetative_skills','Vegetative skills'],
    ['appearance','Appearance',                     'object_use','Object use'],
    ['health_hygiene','Health & hygiene',           'linguistic_skills','Linguistic skills'],
    ['class_activity','Participation in class activity','imitation_skills','Imitation skills'],
];

$ASSESSMENT_LEVELS = [
    'exceeding' => 'Exceeding',
    'expected'  => 'Expected',
    'emerging'  => 'Emerging',
    'not_yet'   => 'Not yet',
];

// Grade from percentage — IBCC scale
function ar2Grade(float $pct): string {
    if ($pct >= 95) return 'A++';
    if ($pct >= 90) return 'A+';
    if ($pct >= 85) return 'A';
    if ($pct >= 80) return 'B++';
    if ($pct >= 75) return 'B+';
    if ($pct >= 70) return 'B';
    if ($pct >= 60) return 'C';
    if ($pct >= 50) return 'D';
    if ($pct >= 40) return 'E';
    return 'U';
}

function ar2GrSel(string $nm, string $val, array $grades): string {
    $h = '<select name="'.h($nm).'" class="ar2-gs"><option value="">—</option>';
    foreach ($grades as $g) {
        $h .= '<option value="'.$g.'"'.($val===$g?' selected':'').'>'.$g.'</option>';
    }
    return $h.'</select>';
}

$ar2AttachDir = __DIR__ . '/../assets/uploads/ilc-attachments/';
if (!is_dir($ar2AttachDir)) @mkdir($ar2AttachDir, 0755, true);

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action    = $_POST['action']     ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);

    if ($action === 'delete_attachment') {
        $id = (int)($_POST['result_id'] ?? 0);
        if ($id) {
            $row = $db->prepare('SELECT form_data FROM ilc_academic_results2 WHERE id=?');
            $row->execute([$id]);
            $row = $row->fetch();
            if ($row) {
                $rfd = json_decode($row['form_data'], true) ?? [];
                if (!empty($rfd['attachment_file'])) {
                    @unlink($ar2AttachDir . $rfd['attachment_file']);
                    unset($rfd['attachment_file']);
                    $db->prepare('UPDATE ilc_academic_results2 SET form_data=? WHERE id=?')
                       ->execute([json_encode($rfd, JSON_UNESCAPED_UNICODE), $id]);
                    logActivity($user['id'], 'ar2_attach_delete', "Deleted AR2 attachment for result #$id");
                    setFlash('success', 'Attachment deleted.');
                }
            }
        }
        redirect('/portal/ilc/academic-result2.php' . ($studentId ? "?student_id=$studentId" : ''));
    }

    if (in_array($action, ['create','update']) && $studentId) {
        // Basic info
        $basic = [
            'gr_no'        => trim($_POST['basic_gr_no']       ?? ''),
            'teacher_name' => trim($_POST['basic_teacher_name']?? ''),
            'attendance'   => trim($_POST['basic_attendance']  ?? ''),
            'category'     => trim($_POST['basic_category']    ?? ''),
            'term'         => trim($_POST['basic_term']        ?? 'First Term'),
            'session'      => trim($_POST['basic_session']     ?? ''),
        ];

        // Subjects
        $subjects = [];
        $names = (array)($_POST['subj_name'] ?? []);
        foreach ($names as $i => $name) {
            $name = trim($name);
            $max  = trim($_POST['subj_max'][$i] ?? '');
            $obt  = trim($_POST['subj_obt'][$i] ?? '');
            if ($name === '' && $max === '' && $obt === '') continue;
            $maxF  = $max !== '' ? (float)$max : null;
            $obtF  = $obt !== '' ? (float)$obt : null;
            $pct   = ($maxF && $maxF > 0 && $obtF !== null) ? round(($obtF / $maxF) * 100) : null;
            $grade = ($pct !== null) ? ar2Grade($pct) : '';
            $subjects[] = [
                'subject'    => $name,
                'max_marks'  => $max,
                'obtained'   => $obt,
                'percentage' => $pct !== null ? (string)$pct : '',
                'grade'      => $grade,
            ];
        }

        // Skills
        $skills = [];
        foreach ($SKILLS_PAIRS as [$k1,,$k2,]) {
            $skills[$k1] = trim($_POST["skill_{$k1}"] ?? '');
            $skills[$k2] = trim($_POST["skill_{$k2}"] ?? '');
        }

        // Assessment levels
        $levels = [];
        foreach (array_keys($ASSESSMENT_LEVELS) as $lk) {
            $levels[$lk] = trim($_POST["level_{$lk}"] ?? '');
        }

        // Remarks
        $remarks = trim($_POST['remarks'] ?? '');

        // Extra custom skill pairs
        $skills_extra = [];
        $sel1 = (array)($_POST['skill_extra_label1'] ?? []);
        $seg1 = (array)($_POST['skill_extra_grade1'] ?? []);
        $sel2 = (array)($_POST['skill_extra_label2'] ?? []);
        $seg2 = (array)($_POST['skill_extra_grade2'] ?? []);
        foreach ($sel1 as $i => $l1) {
            $l1 = trim($l1); $l2 = trim($sel2[$i] ?? '');
            $g1 = trim($seg1[$i] ?? ''); $g2 = trim($seg2[$i] ?? '');
            if ($l1 !== '' || $l2 !== '') {
                $skills_extra[] = ['label1'=>$l1,'grade1'=>$g1,'label2'=>$l2,'grade2'=>$g2];
            }
        }
        // Extra custom assessment levels
        $levels_extra = [];
        $lel = (array)($_POST['level_extra_label'] ?? []);
        $leg = (array)($_POST['level_extra_grade']  ?? []);
        foreach ($lel as $i => $ll) {
            $ll = trim($ll);
            if ($ll !== '') $levels_extra[] = ['label'=>$ll, 'grade'=>trim($leg[$i]??'')];
        }

        $fd   = compact('basic','subjects','skills','levels','remarks','skills_extra','levels_extra');

        // File attachment (PDF/JPG/PNG/DOCX, max 5MB)
        if (!empty($_FILES['attachment']['tmp_name'])) {
            $allowedMimes = [
                'application/pdf'                                                    => 'pdf',
                'image/jpeg'                                                         => 'jpg',
                'image/png'                                                          => 'png',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/msword'                                                 => 'doc',
            ];
            $mime = mime_content_type($_FILES['attachment']['tmp_name']);
            if (isset($allowedMimes[$mime]) && $_FILES['attachment']['size'] <= 5 * 1024 * 1024) {
                if ($action === 'update') {
                    $oldId2 = (int)($_POST['result_id'] ?? 0);
                    $oldRow2 = $db->prepare('SELECT form_data FROM ilc_academic_results2 WHERE id=?');
                    $oldRow2->execute([$oldId2]);
                    $oldRow2 = $oldRow2->fetch();
                    if ($oldRow2) {
                        $oldFd2 = json_decode($oldRow2['form_data'], true) ?? [];
                        if (!empty($oldFd2['attachment_file'])) @unlink($ar2AttachDir . $oldFd2['attachment_file']);
                    }
                }
                $ext2 = $allowedMimes[$mime];
                $attFilename2 = 'ar2att_' . bin2hex(random_bytes(8)) . '.' . $ext2;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $ar2AttachDir . $attFilename2)) {
                    $fd['attachment_file'] = $attFilename2;
                }
            } else {
                setFlash('warning', 'Attachment skipped: only PDF/JPG/PNG/DOCX up to 5MB allowed.');
            }
        }

        $json = json_encode($fd, JSON_UNESCAPED_UNICODE);
        $term = $basic['term']; $session = $basic['session'];

        if ($action === 'update') {
            $id = (int)($_POST['result_id'] ?? 0);
            $db->prepare('UPDATE ilc_academic_results2 SET term=?,session=?,form_data=?,recorded_by=? WHERE id=?')
               ->execute([$term,$session,$json,$user['id'],$id]);
            logActivity($user['id'],'ar2_update',"Updated Academic Result 2 #$id");
            setFlash('success','Academic Result 2 updated.');
        } else {
            $db->prepare('INSERT INTO ilc_academic_results2 (student_id,term,session,form_data,recorded_by) VALUES (?,?,?,?,?)')
               ->execute([$studentId,$term,$session,$json,$user['id']]);
            logActivity($user['id'],'ar2_create',"Saved Academic Result 2 for student #$studentId");
            setFlash('success','Academic Result 2 saved.');
        }
    }
    if ($action === 'delete') {
        $id = (int)($_POST['result_id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM ilc_academic_results2 WHERE id=?')->execute([$id]);
            logActivity($user['id'],'ar2_delete',"Deleted Academic Result 2 #$id");
            setFlash('success','Academic Result 2 deleted.');
        }
    }
    redirect('/portal/ilc/academic-result2.php' . ($studentId ? "?student_id=$studentId" : ''));
}

// ── Fetch ─────────────────────────────────────────────────────────────────────
$studentId  = (int)($_GET['student_id'] ?? 0);
$editId     = (int)($_GET['edit']       ?? 0);

try {
    $students = $db->query(
        'SELECT s.id, u.name, s.roll_no, c.name AS class_name
         FROM students s JOIN users u ON u.id=s.user_id JOIN classes c ON c.id=s.class_id
         WHERE c.is_ilc=1 ORDER BY c.name, s.roll_no'
    )->fetchAll();
} catch (Exception $e) { $students = []; }

$results = []; $editResult = null;
if ($studentId && $tableExists) {
    $st = $db->prepare(
        'SELECT r.*, u.name AS recorder_name
         FROM ilc_academic_results2 r JOIN users u ON u.id=r.recorded_by
         WHERE r.student_id=? ORDER BY r.created_at DESC'
    );
    $st->execute([$studentId]);
    $results = $st->fetchAll();
    if ($editId) {
        foreach ($results as $r) { if ($r['id']==$editId) { $editResult=$r; break; } }
    }
}

$curStudent = null;
foreach ($students as $s) { if ((int)$s['id']===$studentId) { $curStudent=$s; break; } }

$fd = [];
if ($editResult && !empty($editResult['form_data'])) $fd = json_decode($editResult['form_data'],true) ?? [];
$fdBasic  = $fd['basic']    ?? ['gr_no'=>'','teacher_name'=>'','attendance'=>'','category'=>'','term'=>'First Term','session'=>''];
$fdSubj   = $fd['subjects'] ?? [];
$fdSkills      = $fd['skills']        ?? [];
$fdLevels      = $fd['levels']        ?? [];
$fdRem         = $fd['remarks']       ?? '';
$fdSkillExtra  = $fd['skills_extra']  ?? [];
$fdLevelExtra  = $fd['levels_extra']  ?? [];

// Build subject rows for form (FIXED_SUBJECTS pre-filled, then blanks)
$formSubjRows = [];
if (!empty($fdSubj)) {
    // edit mode: use stored subjects, pad to TOTAL_ROWS
    $formSubjRows = array_pad($fdSubj, $TOTAL_ROWS, ['subject'=>'','max_marks'=>'','obtained'=>'','percentage'=>'','grade'=>'']);
} else {
    // new form: pre-fill fixed subjects
    foreach ($FIXED_SUBJECTS as $s) {
        $formSubjRows[] = ['subject'=>$s,'max_marks'=>'','obtained'=>'','percentage'=>'','grade'=>''];
    }
    for ($i = 0; $i < $EXTRA_ROWS; $i++) {
        $formSubjRows[] = ['subject'=>'','max_marks'=>'','obtained'=>'','percentage'=>'','grade'=>''];
    }
}

pageHead('Academic Result 2','ilc_vp');
$links = getIlcLinks();
?>
<div class="portal-wrap">
<?php sidebar('ilc_vp','academic-result2',$links,$user); ?>
<div class="main-area">
<?php topbar('Academic Result 2',$user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Branding strip -->
<div class="d-flex align-items-center gap-3 mb-3 p-3"
     style="background:linear-gradient(90deg,#f0fdf4,#dcfce7);border-radius:10px;border:1px solid #86efac">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC"
       style="width:40px;height:40px;object-fit:contain;flex-shrink:0" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#14532d;letter-spacing:.8px;text-transform:uppercase">
      Academic Result 2 — Term Progress Report
    </div>
    <div style="font-size:.78rem;color:#475569">Inclusive Learning Centre · Bahria Model College Bin Qasim</div>
  </div>
</div>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  <i class="fas fa-spinner fa-spin me-2"></i><strong>Database table initialising.</strong> Please refresh the page in a moment.
</div>
<?php else: ?>

<div class="row g-3">
  <!-- ── Left: Student selector + form ── -->
  <div class="col-xl-6">
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-user-graduate me-2"></i>Select Student</div>
      <div style="padding:12px 16px">
        <form method="GET" class="d-flex gap-2">
          <select name="student_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">— Select student —</option>
            <?php foreach ($students as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $studentId===(int)$s['id']?'selected':'' ?>>
              <?= h($s['name']) ?> (<?= h($s['roll_no']) ?>) — <?= h($s['class_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <?php if ($studentId): ?>
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center"
           data-bs-toggle="collapse" data-bs-target="#ar2FormBody" style="cursor:pointer">
        <span>
          <i class="fas fa-<?= $editResult?'edit':'plus' ?> me-2"></i>
          <?= $editResult
              ? 'Edit Result — '.h($editResult['term']).' '.h($editResult['session'])
              : 'New Progress Report' ?>
        </span>
        <i class="fas fa-chevron-down"></i>
      </div>
      <div id="ar2FormBody" class="collapse show">
        <div style="padding:16px 18px 20px">

          <div class="text-center mb-3 pb-2" style="border-bottom:2px solid #15803d">
            <div style="font-size:.66rem;font-weight:700;letter-spacing:1px;color:#14532d;text-transform:uppercase">
              Inclusive Learning Centre (ILC) · Bahria Model College
            </div>
            <div style="font-size:.96rem;font-weight:700;color:#0f172a;margin:.2rem 0 .1rem">
              Term Progress Report — Special Children's Wing
            </div>
          </div>

          <form method="POST" id="ar2Form" enctype="multipart/form-data">
            <input type="hidden" name="action"     value="<?= $editResult?'update':'create' ?>">
            <input type="hidden" name="student_id" value="<?= $studentId ?>">
            <?php if ($editResult): ?>
            <input type="hidden" name="result_id"  value="<?= $editResult['id'] ?>">
            <?php endif; ?>

            <!-- §1 Basic Info -->
            <div class="fba-sec mb-2">
              <div class="fba-sh" style="background:#14532d">1. Basic Information</div>
              <div class="fba-sb">
                <div class="row g-2">
                  <div class="col-md-6">
                    <label class="fba-lbl">Student Name</label>
                    <input type="text" class="form-control form-control-sm bg-light"
                           value="<?= h($curStudent['name']??'') ?>" readonly>
                  </div>
                  <div class="col-md-3">
                    <label class="fba-lbl">GR No #</label>
                    <input type="text" name="basic_gr_no" class="form-control form-control-sm"
                           placeholder="e.g. 009" value="<?= h($fdBasic['gr_no']) ?>">
                  </div>
                  <div class="col-md-3">
                    <label class="fba-lbl">Attendance</label>
                    <input type="number" name="basic_attendance" class="form-control form-control-sm"
                           placeholder="Days" min="0" value="<?= h($fdBasic['attendance']) ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="fba-lbl">Teacher Name(s)</label>
                    <input type="text" name="basic_teacher_name" class="form-control form-control-sm"
                           placeholder="e.g. Ms Razia / Ms Narmeen" value="<?= h($fdBasic['teacher_name']) ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="fba-lbl">Category / Disability</label>
                    <input type="text" name="basic_category" class="form-control form-control-sm"
                           placeholder="e.g. Speech Development / Hearing Impaired"
                           value="<?= h($fdBasic['category']) ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="fba-lbl">Term <span class="text-danger">*</span></label>
                    <select name="basic_term" class="form-select form-select-sm" required>
                      <?php foreach ($TERMS as $t): ?>
                      <option value="<?= $t ?>" <?= ($fdBasic['term']===$t)?'selected':'' ?>><?= $t ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="fba-lbl">Session</label>
                    <input type="text" name="basic_session" class="form-control form-control-sm"
                           placeholder="e.g. 2024-2025" value="<?= h($fdBasic['session']) ?>">
                  </div>
                </div>
              </div>
            </div>

            <!-- §2 Academic Subjects -->
            <div class="fba-sec mb-2">
              <div class="fba-sh" style="background:#065f46">2. Academic Subjects</div>
              <div class="fba-sb" style="padding:0">
                <div class="table-responsive">
                  <table class="table table-sm table-bordered mb-0" id="subjTable" style="font-size:.8rem">
                    <thead>
                      <tr style="background:#d1fae5;text-align:center">
                        <th style="text-align:left;width:28%">Subject</th>
                        <th style="width:13%">Max Marks</th>
                        <th style="width:13%">Obtained</th>
                        <th style="width:13%">Percentage</th>
                        <th style="width:10%">Grade</th>
                        <th style="width:5%"></th>
                      </tr>
                    </thead>
                    <tbody id="subjBody">
                    <?php foreach ($formSubjRows as $i => $sr): ?>
                    <tr class="subj-row">
                      <td style="padding:3px 6px">
                        <input type="text" name="subj_name[]" class="form-control form-control-sm border-0 p-0 subj-name"
                               value="<?= h($sr['subject']) ?>" placeholder="Subject name">
                      </td>
                      <td style="padding:3px 6px">
                        <input type="number" name="subj_max[]" step="0.5" min="0"
                               class="form-control form-control-sm border-0 p-0 subj-max text-center"
                               value="<?= h($sr['max_marks']) ?>" oninput="calcRow(this)">
                      </td>
                      <td style="padding:3px 6px">
                        <input type="number" name="subj_obt[]" step="0.5" min="0"
                               class="form-control form-control-sm border-0 p-0 subj-obt text-center"
                               value="<?= h($sr['obtained']) ?>" oninput="calcRow(this)">
                      </td>
                      <td class="text-center align-middle subj-pct-cell" style="font-weight:600;color:#0f172a">
                        <?= $sr['percentage']!==''?h($sr['percentage']).'%':'—' ?>
                      </td>
                      <td class="text-center align-middle subj-grade-cell">
                        <?php if ($sr['grade']!==''): ?>
                        <span class="ar2-grade-badge" data-grade="<?= h($sr['grade']) ?>">
                          <?= h($sr['grade']) ?>
                        </span>
                        <?php else: ?>
                        <span class="ar2-grade-badge">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-center align-middle" style="width:30px">
                        <button type="button" class="btn btn-xs btn-outline-danger remove-subj-row"
                                style="padding:0 4px;font-size:.8rem" title="Remove row">×</button>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                      <tr style="background:#f0fdf4;font-weight:700">
                        <td style="padding:4px 8px">Total</td>
                        <td class="text-center" id="totalMax">—</td>
                        <td class="text-center" id="totalObt">—</td>
                        <td colspan="3"></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
                <div class="d-flex align-items-center gap-2 px-2 py-1" style="background:#f0fdf4;border-top:1px solid #bbf7d0">
                  <button type="button" id="addSubjRowBtn" class="btn btn-xs btn-outline-success" style="font-size:.71rem;padding:1px 8px">
                    <i class="fas fa-plus me-1"></i>Add Row
                  </button>
                  <span style="font-size:.72rem;color:var(--t2)">
                    <i class="fas fa-info-circle me-1"></i>IBCC: A++(95%+) · A+(90%+) · A(85%+) · B++(80%+) · B+(75%+) · B(70%+) · C(60%+) · D(50%+) · E(40%+) · U(&lt;40%)
                  </span>
                </div>
              </div>
            </div>

            <!-- §3 Skills Assessment -->
            <div class="fba-sec mb-2">
              <div class="fba-sh" style="background:#1e3a5f">3. Skills Assessment</div>
              <div class="fba-sb" style="padding:0">
                <table class="table table-sm table-bordered mb-0" style="font-size:.8rem">
                  <thead><tr style="background:#e8edf5">
                    <th style="width:38%">Skill</th>
                    <th class="text-center" style="width:10%">Grade</th>
                    <th style="width:38%">Skill</th>
                    <th class="text-center" style="width:10%">Grade</th>
                  </tr></thead>
                  <tbody>
                  <?php foreach ($SKILLS_PAIRS as [$k1,$l1,$k2,$l2]): ?>
                  <tr>
                    <td class="align-middle"><?= h($l1) ?></td>
                    <td class="text-center align-middle">
                      <?= ar2GrSel("skill_{$k1}", $fdSkills[$k1]??'', $GRADES) ?>
                    </td>
                    <td class="align-middle"><?= h($l2) ?></td>
                    <td class="text-center align-middle">
                      <?= ar2GrSel("skill_{$k2}", $fdSkills[$k2]??'', $GRADES) ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
                <!-- Extra custom skill pairs -->
                <table class="table table-sm table-bordered mb-0 ar2-skill-extra-tbl<?= empty($fdSkillExtra)?' d-none':'' ?>"
                       style="font-size:.79rem;background:#fffbeb" id="skillExtraTable">
                  <tbody id="skillExtraTbody">
                  <?php foreach ($fdSkillExtra as $se): ?>
                  <tr class="skill-extra-row">
                    <td class="align-middle">
                      <input type="text" name="skill_extra_label1[]" class="form-control form-control-sm border-0 p-0"
                             value="<?= h($se['label1']??'') ?>" placeholder="Skill 1 label">
                    </td>
                    <td class="text-center align-middle" style="width:74px">
                      <?= ar2GrSel('skill_extra_grade1[]', $se['grade1']??'', $GRADES) ?>
                    </td>
                    <td class="align-middle">
                      <input type="text" name="skill_extra_label2[]" class="form-control form-control-sm border-0 p-0"
                             value="<?= h($se['label2']??'') ?>" placeholder="Skill 2 label (optional)">
                    </td>
                    <td class="text-center align-middle" style="width:74px">
                      <?= ar2GrSel('skill_extra_grade2[]', $se['grade2']??'', $GRADES) ?>
                    </td>
                    <td style="width:34px;text-align:center;vertical-align:middle">
                      <button type="button" class="btn btn-xs btn-outline-danger remove-skill-extra"
                              style="padding:0 4px;font-size:.8rem">×</button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <div style="padding:4px 10px 5px;background:#f8f9fa;border-top:1px solid #e2e8f0">
                <button type="button" id="addSkillExtraBtn" class="btn btn-xs btn-outline-secondary" style="font-size:.71rem;padding:1px 8px">
                  <i class="fas fa-plus me-1"></i>Add custom skill
                </button>
              </div>
            </div>

            <!-- §4 Assessment Levels -->
            <div class="fba-sec mb-2">
              <div class="fba-sh" style="background:#7c3aed">4. Assessment Levels</div>
              <div class="fba-sb" style="padding:0">
                <table class="table table-sm table-bordered mb-0" style="font-size:.8rem">
                  <thead><tr style="background:#ede9fe">
                    <th>Level</th>
                    <th class="text-center" style="width:90px">Grade</th>
                  </tr></thead>
                  <tbody>
                  <?php foreach ($ASSESSMENT_LEVELS as $lk => $ll): ?>
                  <tr>
                    <td class="align-middle fw-semibold"><?= h($ll) ?></td>
                    <td class="text-center align-middle">
                      <?= ar2GrSel("level_{$lk}", $fdLevels[$lk]??'', ['A+','A','B','C','D']) ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
                <!-- Extra custom levels -->
                <table class="table table-sm table-bordered mb-0 ar2-level-extra-tbl<?= empty($fdLevelExtra)?' d-none':'' ?>"
                       style="font-size:.79rem;background:#f5f3ff" id="levelExtraTable">
                  <tbody id="levelExtraTbody">
                  <?php foreach ($fdLevelExtra as $le): ?>
                  <tr class="level-extra-row">
                    <td class="align-middle">
                      <input type="text" name="level_extra_label[]" class="form-control form-control-sm border-0 p-0"
                             value="<?= h($le['label']??'') ?>" placeholder="Level label">
                    </td>
                    <td class="text-center align-middle" style="width:74px">
                      <?= ar2GrSel('level_extra_grade[]', $le['grade']??'', ['A+','A','B','C','D']) ?>
                    </td>
                    <td style="width:34px;text-align:center;vertical-align:middle">
                      <button type="button" class="btn btn-xs btn-outline-danger remove-level-extra"
                              style="padding:0 4px;font-size:.8rem">×</button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <div style="padding:4px 10px 5px;background:#f8f9fa;border-top:1px solid #e2e8f0">
                <button type="button" id="addLevelExtraBtn" class="btn btn-xs btn-outline-secondary" style="font-size:.71rem;padding:1px 8px">
                  <i class="fas fa-plus me-1"></i>Add custom level
                </button>
              </div>
            </div>

            <!-- §5 Teacher's Remarks -->
            <div class="fba-sec mb-3">
              <div class="fba-sh" style="background:#374151">5. Teacher's Remarks</div>
              <div class="fba-sb">
                <textarea name="remarks" class="form-control form-control-sm" rows="4"
                          placeholder="Enter teacher's remarks, observations, and recommendations…"><?= h($fdRem) ?></textarea>
              </div>
            </div>

            <!-- File Attachment -->
            <div class="fba-sec mb-3">
              <div class="fba-sh" style="background:#475569">Attachment (optional)</div>
              <div class="fba-sb">
                <?php
                  $curAttach2 = $fd['attachment_file'] ?? ($editResult ? (json_decode($editResult['form_data'],true)['attachment_file'] ?? '') : '');
                ?>
                <?php if ($curAttach2): ?>
                <div class="mb-2 p-2" style="background:#f0fdf4;border-radius:6px;border:1px solid #86efac;font-size:.8rem">
                  <i class="fas fa-paperclip me-1 text-success"></i>
                  <a href="<?= url('/assets/uploads/ilc-attachments/'.h($curAttach2)) ?>" target="_blank" class="text-success fw-semibold">View current attachment</a>
                  <span class="text-muted ms-2">(upload a new file to replace it)</span>
                </div>
                <?php endif; ?>
                <input type="file" name="attachment" class="form-control form-control-sm"
                       accept=".pdf,.jpg,.jpeg,.png,.docx,.doc">
                <div class="form-text" style="font-size:.75rem;color:#64748b">PDF, JPG, PNG or DOCX · max 5 MB</div>
              </div>
            </div>

            <div class="d-flex gap-2 pt-1">
              <button type="submit" class="btn btn-success btn-sm">
                <i class="fas fa-save me-1"></i><?= $editResult?'Update Report':'Save Report' ?>
              </button>
              <?php if ($editResult): ?>
              <a href="?student_id=<?= $studentId ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Right: Results list ── -->
  <div class="col-xl-6">
    <?php if ($studentId && $curStudent): ?>
    <div class="sec-card">
      <div class="sec-card-header">
        <i class="fas fa-chart-bar me-2"></i>Progress Reports — <?= h($curStudent['name']) ?>
        <span class="badge bg-secondary ms-2"><?= count($results) ?></span>
      </div>
      <?php if (empty($results)): ?>
      <div style="padding:40px;text-align:center;color:var(--t2);font-size:.85rem">
        No reports yet. Use the form to add the first Progress Report.
      </div>
      <?php else: ?>
      <div style="padding:12px 16px">
        <?php foreach ($results as $r):
          $rfd  = json_decode($r['form_data'],true) ?? [];
          $rSubj= $rfd['subjects'] ?? [];
          $totMax = array_sum(array_column($rSubj,'max_marks'));
          $totObt = array_sum(array_column($rSubj,'obtained'));
        ?>
        <div class="mb-3 p-3" style="background:#f7f9fb;border-radius:8px;border:1px solid var(--border)">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <span class="badge" style="background:#14532d;font-size:.75rem">
                <?= h($r['term']) ?> <?= h($r['session']) ?>
              </span>
              <span style="font-size:.74rem;color:var(--t2);margin-left:8px">
                <?= fDate($r['created_at']) ?> · <?= h($r['recorder_name']) ?>
              </span>
            </div>
            <div class="d-flex gap-1 flex-wrap">
              <a href="<?= url('/portal/ilc/academic-result2-pdf.php?id='.$r['id']) ?>"
                 target="_blank" class="btn btn-xs btn-outline-success">
                <i class="fas fa-file-pdf me-1"></i>PDF
              </a>
              <a href="?student_id=<?= $studentId ?>&edit=<?= $r['id'] ?>"
                 class="btn btn-xs btn-outline-primary"><i class="fas fa-edit"></i></a>
              <?php if (!empty($rfd['attachment_file'])): ?>
              <a href="<?= url('/assets/uploads/ilc-attachments/'.h($rfd['attachment_file'])) ?>"
                 target="_blank" class="btn btn-xs btn-outline-info" title="View Attachment">
                <i class="fas fa-paperclip me-1"></i>File
              </a>
              <form method="POST" class="d-inline" onsubmit="return confirm('Delete this attachment?')">
                <input type="hidden" name="action"     value="delete_attachment">
                <input type="hidden" name="student_id" value="<?= $studentId ?>">
                <input type="hidden" name="result_id"  value="<?= $r['id'] ?>">
                <button class="btn btn-xs btn-outline-warning" title="Delete Attachment">
                  <i class="fas fa-paperclip"></i><i class="fas fa-times ms-1" style="font-size:.65rem"></i>
                </button>
              </form>
              <?php endif; ?>
              <form method="POST" class="d-inline" onsubmit="return confirm('Delete this report?')">
                <input type="hidden" name="action"     value="delete">
                <input type="hidden" name="student_id" value="<?= $studentId ?>">
                <input type="hidden" name="result_id"  value="<?= $r['id'] ?>">
                <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </div>
          <?php if (!empty($rfd['basic']['category'])): ?>
          <div style="font-size:.76rem;color:#14532d;margin-bottom:3px">
            <i class="fas fa-tag me-1"></i><?= h($rfd['basic']['category']) ?>
          </div>
          <?php endif; ?>
          <?php if ($totMax > 0): ?>
          <div style="font-size:.76rem;color:var(--t2)">
            <i class="fas fa-calculator me-1"></i>
            Total: <?= number_format($totObt,1) ?> / <?= number_format($totMax,1) ?>
            <span class="ms-2 badge" style="background:#dbeafe;color:#1e40af;font-size:.68rem">
              <?= $totMax>0?round(($totObt/$totMax)*100).'%':'' ?>
            </span>
          </div>
          <?php endif; ?>
          <?php if (!empty($rfd['remarks'])): ?>
          <div style="font-size:.74rem;margin-top:4px;color:var(--t2)">
            <i class="fas fa-comment-alt me-1"></i>
            <?= h(mb_strimwidth($rfd['remarks'],0,100,'…')) ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="sec-card">
      <div style="padding:60px;text-align:center;color:var(--t2)">
        <i class="fas fa-chart-bar fa-2x mb-3 d-block" style="opacity:.15"></i>
        Select a student to view or add Progress Reports.
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
</div></div></div>

<style>
.fba-sec{border:1px solid #e2e8f0;border-radius:7px;overflow:hidden}
.fba-sh{font-size:.78rem;font-weight:700;color:#fff;padding:5px 12px;letter-spacing:.4px;text-transform:uppercase;display:flex;justify-content:space-between;align-items:center}
.fba-sb{padding:10px 12px}
.fba-lbl{font-size:.78rem;font-weight:600;margin-bottom:2px;display:block}
.ar2-gs{width:68px;font-size:.78rem;padding:2px 4px;border-radius:4px;border:1px solid #d1d5db;font-weight:600}
.ar2-grade-badge{display:inline-block;min-width:32px;text-align:center;font-weight:700;font-size:.78rem;padding:1px 6px;border-radius:4px}
.ar2-skill-extra-tbl td,.ar2-level-extra-tbl td{padding:3px 6px}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const AR2_GRADES      = <?= json_encode($GRADES) ?>;
const AR2_LEVEL_GRADES= ['A+','A','B','C','D'];

const GRADE_COLORS = {
  'A++':['#14532d','#dcfce7'],'A+':['#166534','#d1fae5'],'A':['#15803d','#bbf7d0'],
  'B++':['#1e40af','#dbeafe'],'B+':['#1d4ed8','#bfdbfe'],'B':['#0369a1','#e0f2fe'],
  'C':['#92400e','#fef3c7'],'D':['#7c2d12','#ffedd5'],'E':['#9a3412','#fee2e2'],'U':['#991b1b','#fecaca'],
  'N.A.':['#64748b','#f1f5f9']
};

function gradeFromPct(pct) {
  if (pct >= 95) return 'A++';
  if (pct >= 90) return 'A+';
  if (pct >= 85) return 'A';
  if (pct >= 80) return 'B++';
  if (pct >= 75) return 'B+';
  if (pct >= 70) return 'B';
  if (pct >= 60) return 'C';
  if (pct >= 50) return 'D';
  if (pct >= 40) return 'E';
  return 'U';
}

function setBadge(cell, grade) {
  var el = cell.querySelector('.ar2-grade-badge');
  if (!el) { el = document.createElement('span'); el.className = 'ar2-grade-badge'; cell.innerHTML = ''; cell.appendChild(el); }
  if (!grade) { el.textContent = '—'; el.style.background=''; el.style.color=''; return; }
  var c = GRADE_COLORS[grade] || ['#374151','#f9fafb'];
  el.style.color = c[0]; el.style.background = c[1];
  el.textContent = grade;
}

function calcRow(inp) {
  var row  = inp.closest('tr');
  var max  = parseFloat(row.querySelector('.subj-max').value);
  var obt  = parseFloat(row.querySelector('.subj-obt').value);
  var pctC = row.querySelector('.subj-pct-cell');
  var graC = row.querySelector('.subj-grade-cell');

  if (!isNaN(max) && max > 0 && !isNaN(obt)) {
    var pct   = Math.round((obt / max) * 100);
    var grade = gradeFromPct(pct);
    pctC.textContent = pct + '%';
    pctC.style.color = '#0f172a';
    setBadge(graC, grade);
  } else {
    pctC.textContent = '—';
    setBadge(graC, '');
  }
  updateTotals();
}

function updateTotals() {
  var rows = document.querySelectorAll('#subjBody .subj-row');
  var totMax = 0, totObt = 0, hasAny = false;
  rows.forEach(function(row) {
    var m = parseFloat(row.querySelector('.subj-max').value);
    var o = parseFloat(row.querySelector('.subj-obt').value);
    if (!isNaN(m)) { totMax += m; hasAny = true; }
    if (!isNaN(o)) { totObt += o; hasAny = true; }
  });
  document.getElementById('totalMax').textContent = hasAny ? totMax : '—';
  document.getElementById('totalObt').textContent = hasAny ? totObt : '—';
}

// Apply colors to pre-filled badges on load, then update totals
document.querySelectorAll('.ar2-grade-badge[data-grade]').forEach(function(el) {
  var g = el.getAttribute('data-grade');
  if (g && GRADE_COLORS[g]) {
    el.style.color      = GRADE_COLORS[g][0];
    el.style.background = GRADE_COLORS[g][1];
  }
});
updateTotals();

// ── Subject rows: add / remove ─────────────────────────────────────────────
function newSubjRowHtml() {
  return '<tr class="subj-row">'
    +'<td style="padding:3px 6px"><input type="text" name="subj_name[]"'
    +' class="form-control form-control-sm border-0 p-0 subj-name" placeholder="Subject name"></td>'
    +'<td style="padding:3px 6px"><input type="number" name="subj_max[]" step="0.5" min="0"'
    +' class="form-control form-control-sm border-0 p-0 subj-max text-center" oninput="calcRow(this)"></td>'
    +'<td style="padding:3px 6px"><input type="number" name="subj_obt[]" step="0.5" min="0"'
    +' class="form-control form-control-sm border-0 p-0 subj-obt text-center" oninput="calcRow(this)"></td>'
    +'<td class="text-center align-middle subj-pct-cell" style="font-weight:600;color:#0f172a">—</td>'
    +'<td class="text-center align-middle subj-grade-cell"><span class="ar2-grade-badge">—</span></td>'
    +'<td class="text-center align-middle" style="width:30px">'
    +'<button type="button" class="btn btn-xs btn-outline-danger remove-subj-row"'
    +' style="padding:0 4px;font-size:.8rem" title="Remove">×</button></td></tr>';
}
document.getElementById('addSubjRowBtn').addEventListener('click', function() {
  var tbody = document.getElementById('subjBody');
  var div = document.createElement('tbody');
  div.innerHTML = newSubjRowHtml();
  tbody.appendChild(div.firstChild);
  updateTotals();
});
document.addEventListener('click', function(e) {
  if (e.target.closest('.remove-subj-row')) {
    e.target.closest('.subj-row').remove();
    updateTotals();
  }
});

// ── Extra skills rows ──────────────────────────────────────────────────────
function newSkillExtraRow(l1, g1, l2, g2) {
  var makeOpts = function(g, grades) {
    return grades.map(function(x) {
      return '<option value="'+x+'"'+(x===g?' selected':'')+'>'+x+'</option>';
    }).join('');
  };
  return '<tr class="skill-extra-row">'
    +'<td class="align-middle"><input type="text" name="skill_extra_label1[]"'
    +' class="form-control form-control-sm border-0 p-0" value="'+(l1||'')+'" placeholder="Skill 1 label"></td>'
    +'<td class="text-center align-middle" style="width:74px">'
    +'<select name="skill_extra_grade1[]" class="ar2-gs"><option value="">—</option>'
    +makeOpts(g1||'',AR2_GRADES)+'</select></td>'
    +'<td class="align-middle"><input type="text" name="skill_extra_label2[]"'
    +' class="form-control form-control-sm border-0 p-0" value="'+(l2||'')+'" placeholder="Skill 2 (optional)"></td>'
    +'<td class="text-center align-middle" style="width:74px">'
    +'<select name="skill_extra_grade2[]" class="ar2-gs"><option value="">—</option>'
    +makeOpts(g2||'',AR2_GRADES)+'</select></td>'
    +'<td style="width:34px;text-align:center;vertical-align:middle">'
    +'<button type="button" class="btn btn-xs btn-outline-danger remove-skill-extra"'
    +' style="padding:0 4px;font-size:.8rem">×</button></td></tr>';
}
document.getElementById('addSkillExtraBtn').addEventListener('click', function() {
  var tbody = document.getElementById('skillExtraTbody');
  var table = document.getElementById('skillExtraTable');
  table.classList.remove('d-none');
  var div = document.createElement('tbody');
  div.innerHTML = newSkillExtraRow('','','','');
  tbody.appendChild(div.firstChild);
});
document.addEventListener('click', function(e) {
  if (e.target.closest('.remove-skill-extra')) {
    var row = e.target.closest('.skill-extra-row');
    var tbody = row.parentElement;
    row.remove();
    if (!tbody.children.length) document.getElementById('skillExtraTable').classList.add('d-none');
  }
});

// ── Extra level rows ───────────────────────────────────────────────────────
function newLevelExtraRow(label, grade) {
  var opts = AR2_LEVEL_GRADES.map(function(g) {
    return '<option value="'+g+'"'+(g===grade?' selected':'')+'>'+g+'</option>';
  }).join('');
  return '<tr class="level-extra-row">'
    +'<td class="align-middle"><input type="text" name="level_extra_label[]"'
    +' class="form-control form-control-sm border-0 p-0" value="'+(label||'')+'" placeholder="Level label"></td>'
    +'<td class="text-center align-middle" style="width:74px">'
    +'<select name="level_extra_grade[]" class="ar2-gs"><option value="">—</option>'
    +opts+'</select></td>'
    +'<td style="width:34px;text-align:center;vertical-align:middle">'
    +'<button type="button" class="btn btn-xs btn-outline-danger remove-level-extra"'
    +' style="padding:0 4px;font-size:.8rem">×</button></td></tr>';
}
document.getElementById('addLevelExtraBtn').addEventListener('click', function() {
  var tbody = document.getElementById('levelExtraTbody');
  var table = document.getElementById('levelExtraTable');
  table.classList.remove('d-none');
  var div = document.createElement('tbody');
  div.innerHTML = newLevelExtraRow('','');
  tbody.appendChild(div.firstChild);
});
document.addEventListener('click', function(e) {
  if (e.target.closest('.remove-level-extra')) {
    var row = e.target.closest('.level-extra-row');
    var tbody = row.parentElement;
    row.remove();
    if (!tbody.children.length) document.getElementById('levelExtraTable').classList.add('d-none');
  }
});
</script>
</body></html>

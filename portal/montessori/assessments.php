<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/db.php';

$user    = requireAuth('montessori_teacher', 'wing_head', 'vp_montessori');
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);

// ── Helpers ──────────────────────────────────────────────────────────────────
function monteDefaultCriteria(string $subject): array {
    $s = mb_strtolower(trim($subject));
    if (str_contains($s,'english'))                               return ['Reading','Writing','Participation'];
    if (str_contains($s,'math')||str_contains($s,'maths'))        return ['Number Concepts','Application','Participation'];
    if (str_contains($s,'science'))                               return ['Observation','Understanding','Participation'];
    if (str_contains($s,'urdu'))                                  return ['Reading','Vocabulary','Participation'];
    if (str_contains($s,'islamic')||str_contains($s,'islamiat'))  return ['Comprehension','Recitation','Participation'];
    if (str_contains($s,'art')||str_contains($s,'craft'))         return ['Creativity','Technique','Participation'];
    if (str_contains($s,'general')||str_contains($s,'knowledge')) return ['Knowledge','Expression','Participation'];
    if (str_contains($s,'physical')||str_contains($s,'p.e'))      return ['Motor Skills','Coordination','Participation'];
    return ['Concept','Practice','Participation'];
}

function monteSubjectMeta(string $name): array {
    $n = mb_strtolower($name);
    if (str_contains($n,'english'))  return ['icon'=>'fa-book',        'bg'=>'#dbeafe','ic'=>'#1d4ed8','border'=>'#bfdbfe'];
    if (str_contains($n,'math'))     return ['icon'=>'fa-calculator',  'bg'=>'#dcfce7','ic'=>'#15803d','border'=>'#bbf7d0'];
    if (str_contains($n,'science'))  return ['icon'=>'fa-flask',       'bg'=>'#ede9fe','ic'=>'#7c3aed','border'=>'#ddd6fe'];
    if (str_contains($n,'urdu'))     return ['icon'=>'fa-language',    'bg'=>'#fef3c7','ic'=>'#b45309','border'=>'#fde68a'];
    if (str_contains($n,'islamic'))  return ['icon'=>'fa-mosque',      'bg'=>'#ccfbf1','ic'=>'#0d9488','border'=>'#99f6e4'];
    if (str_contains($n,'art')||str_contains($n,'craft'))
                                     return ['icon'=>'fa-paint-brush', 'bg'=>'#fce7f3','ic'=>'#be185d','border'=>'#fbcfe8'];
    if (str_contains($n,'general'))  return ['icon'=>'fa-globe',       'bg'=>'#e0f2fe','ic'=>'#0369a1','border'=>'#bae6fd'];
    if (str_contains($n,'physical')) return ['icon'=>'fa-running',     'bg'=>'#ffedd5','ic'=>'#c2410c','border'=>'#fed7aa'];
    return ['icon'=>'fa-book-open','bg'=>'#f3f4f6','ic'=>'#4b5563','border'=>'#e5e7eb'];
}

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_assessment') {
        $classId   = (int)($_POST['class_id']   ?? 0);
        $studentId = (int)($_POST['student_id'] ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $topic     = substr(trim($_POST['topic'] ?? ''), 0, 200);
        $date      = $_POST['assessment_date']  ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

        $criteria = array_values(array_filter(array_map('trim', (array)($_POST['criteria'] ?? []))));
        if (empty($criteria)) $criteria = ['Participation'];

        if (!$classId || !$studentId || !$subjectId) {
            setFlash('danger','Class, student, and subject are required.');
            redirect('/portal/montessori/assessments.php?class_id='.$classId.'&student_id='.$studentId.'&date='.urlencode($date));
        }

        // Verify teacher assignment
        if ($teacher) {
            $chk = $db->prepare(
                'SELECT 1 FROM class_subjects cs JOIN classes c ON cs.class_id=c.id
                 WHERE cs.teacher_id=? AND cs.class_id=? AND cs.subject_id=? AND c.is_montessori=1 LIMIT 1'
            );
            $chk->execute([$teacher['id'],$classId,$subjectId]);
            if (!$chk->fetchColumn()) {
                setFlash('danger','You are not assigned to this class/subject.');
                redirect('/portal/montessori/assessments.php?class_id='.$classId.'&student_id='.$studentId.'&date='.urlencode($date));
            }
        }

        // Verify student belongs to class
        $stuChk = $db->prepare('SELECT id FROM students WHERE id=? AND class_id=? AND deleted_at IS NULL LIMIT 1');
        $stuChk->execute([$studentId,$classId]);
        if (!$stuChk->fetchColumn()) {
            setFlash('danger','Student not found in this class.');
            redirect('/portal/montessori/assessments.php?class_id='.$classId.'&student_id='.$studentId.'&date='.urlencode($date));
        }

        // Insert new assessment record
        $db->prepare(
            'INSERT INTO montessori_daily_assessments
             (class_id,student_id,subject_id,topic,assessment_date,criteria,teacher_id)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$classId,$studentId,$subjectId,$topic?:null,$date,json_encode($criteria),$teacher?$teacher['id']:1]);
        $assessmentId = (int)$db->lastInsertId();

        // Insert entry for this assessment
        $rawRatings = (array)($_POST['ratings'] ?? []);
        $overall    = trim($_POST['overall']    ?? '');
        $remark     = substr(trim($_POST['remarks'] ?? ''), 0, 500);
        $clean = [];
        foreach ($criteria as $crit) {
            $v = $rawRatings[$crit] ?? '';
            $clean[$crit] = in_array($v,['AD','ED','EMD'],true) ? $v : '';
        }
        $db->prepare(
            'INSERT INTO montessori_daily_assessment_entries
             (assessment_id,student_id,ratings,overall,remarks)
             VALUES (?,?,?,?,?)'
        )->execute([
            $assessmentId,$studentId,json_encode($clean),
            in_array($overall,['AD','ED','EMD'],true)?$overall:null,
            $remark?:null
        ]);

        logActivity($user['id'],'montessori_assessment_save',
            "Formative assessment: class #$classId, student #$studentId, subject #$subjectId, $date");
        setFlash('success','Assessment saved successfully.');
        redirect('/portal/montessori/assessments.php?class_id='.$classId.'&student_id='.$studentId.'&subject_id='.$subjectId);
    }

    if ($action === 'update_assessment') {
        $assessmentId = (int)($_POST['assessment_id'] ?? 0);
        $classId      = (int)($_POST['class_id']      ?? 0);
        $studentId    = (int)($_POST['student_id']    ?? 0);
        $subjectId    = (int)($_POST['subject_id']    ?? 0);
        $topic        = substr(trim($_POST['topic'] ?? ''), 0, 200);
        $date         = $_POST['assessment_date']  ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
        $criteria     = array_values(array_filter(array_map('trim', (array)($_POST['criteria'] ?? []))));
        if (empty($criteria)) $criteria = ['Participation'];

        if (!$assessmentId || !$classId || !$studentId || !$subjectId) {
            setFlash('danger','Missing required fields.');
            redirect('/portal/montessori/assessments.php?class_id='.$classId.'&student_id='.$studentId.'&subject_id='.$subjectId);
        }

        if ($teacher) {
            $chk = $db->prepare('SELECT id FROM montessori_daily_assessments WHERE id=? AND teacher_id=?');
            $chk->execute([$assessmentId,$teacher['id']]);
            if (!$chk->fetch()) {
                setFlash('danger','Not authorized to edit this assessment.');
                redirect('/portal/montessori/assessments.php?class_id='.$classId.'&student_id='.$studentId.'&subject_id='.$subjectId);
            }
        }

        $db->prepare(
            'UPDATE montessori_daily_assessments
             SET topic=?,assessment_date=?,criteria=?,teacher_id=?,updated_at=NOW()
             WHERE id=? AND student_id=?'
        )->execute([$topic?:null,$date,json_encode($criteria),$teacher?$teacher['id']:1,$assessmentId,$studentId]);

        $rawRatings = (array)($_POST['ratings'] ?? []);
        $overall    = trim($_POST['overall']    ?? '');
        $remark     = substr(trim($_POST['remarks'] ?? ''), 0, 500);
        $clean = [];
        foreach ($criteria as $crit) {
            $v = $rawRatings[$crit] ?? '';
            $clean[$crit] = in_array($v,['AD','ED','EMD'],true) ? $v : '';
        }
        $db->prepare(
            'INSERT INTO montessori_daily_assessment_entries (assessment_id,student_id,ratings,overall,remarks)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE ratings=VALUES(ratings),overall=VALUES(overall),remarks=VALUES(remarks)'
        )->execute([
            $assessmentId,$studentId,json_encode($clean),
            in_array($overall,['AD','ED','EMD'],true)?$overall:null,
            $remark?:null
        ]);

        logActivity($user['id'],'montessori_assessment_update',
            "Updated assessment #$assessmentId for student #$studentId, subject #$subjectId");
        setFlash('success','Assessment updated successfully.');
        redirect('/portal/montessori/assessments.php?class_id='.$classId.'&student_id='.$studentId.'&subject_id='.$subjectId);
    }

    if ($action === 'delete_assessment') {
        $aId       = (int)($_POST['assessment_id'] ?? 0);
        $classId   = (int)($_POST['class_id']      ?? 0);
        $studentId = (int)($_POST['student_id']    ?? 0);
        $subjectId = (int)($_POST['subject_id']    ?? 0);
        if ($teacher) {
            $chk = $db->prepare('SELECT id FROM montessori_daily_assessments WHERE id=? AND teacher_id=?');
            $chk->execute([$aId,$teacher['id']]);
            if ($chk->fetch()) {
                $db->prepare('DELETE FROM montessori_daily_assessments WHERE id=?')->execute([$aId]);
                logActivity($user['id'],'montessori_assessment_delete',"Deleted assessment #$aId");
                setFlash('success','Assessment deleted.');
            } else {
                setFlash('danger','Not authorized to delete this assessment.');
            }
        }
        redirect('/portal/montessori/assessments.php?class_id='.$classId.'&student_id='.$studentId.($subjectId?'&subject_id='.$subjectId:''));
    }
}

// ── GET: load page data ───────────────────────────────────────────────────────
$selClassId   = (int)($_GET['class_id']   ?? $_SESSION['monte_assess_cls'] ?? 0);
if (array_key_exists('student_id', $_GET)) {
    $selStudentId = (int)$_GET['student_id'];
    if (!$selStudentId) unset($_SESSION['monte_assess_stu']);
} else {
    $selStudentId = (int)($_SESSION['monte_assess_stu'] ?? 0);
}
$selSubjectId = (int)($_GET['subject_id'] ?? 0);
$selDate      = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selDate)) $selDate = date('Y-m-d');
$selMode  = $_GET['mode']    ?? '';   // 'new' = blank new assessment form
$editId   = (int)($_GET['edit_id'] ?? 0);

// Teacher's montessori classes
if ($teacher) {
    $cSt = $db->prepare(
        'SELECT DISTINCT c.id,c.name,c.grade FROM class_subjects cs
         JOIN classes c ON cs.class_id=c.id
         WHERE cs.teacher_id=? AND c.is_montessori=1 ORDER BY c.grade,c.section'
    );
    $cSt->execute([$teacher['id']]);
} else {
    $cSt = $db->prepare('SELECT id,name,grade FROM classes WHERE is_montessori=1 ORDER BY grade,section');
    $cSt->execute([]);
}
$assignedClasses = $cSt->fetchAll();

if (!$selClassId && !empty($assignedClasses)) $selClassId = (int)$assignedClasses[0]['id'];
if ($selClassId) $_SESSION['monte_assess_cls'] = $selClassId;

// Class info
$classInfo = null;
if ($selClassId) {
    $cI = $db->prepare('SELECT name FROM classes WHERE id=?');
    $cI->execute([$selClassId]);
    $classInfo = $cI->fetch();
}

// Students in selected class
$students = $selClassId ? getClassStudents($selClassId) : [];

// Validate selected student
$selStudent = null;
foreach ($students as $s) { if ((int)$s['id']===$selStudentId) { $selStudent=$s; break; } }
if (!$selStudent) $selStudentId = 0;
if ($selStudentId) $_SESSION['monte_assess_stu'] = $selStudentId;

// Subjects for selected class+teacher
$subjects = [];
if ($selClassId) {
    if ($teacher) {
        $sSt = $db->prepare(
            'SELECT DISTINCT s.id,s.name FROM class_subjects cs
             JOIN subjects s ON cs.subject_id=s.id
             WHERE cs.teacher_id=? AND cs.class_id=? ORDER BY s.name'
        );
        $sSt->execute([$teacher['id'],$selClassId]);
    } else {
        $sSt = $db->prepare(
            'SELECT DISTINCT s.id,s.name FROM class_subjects cs
             JOIN subjects s ON cs.subject_id=s.id
             WHERE cs.class_id=? ORDER BY s.name'
        );
        $sSt->execute([$selClassId]);
    }
    $subjects = $sSt->fetchAll();
}

// Validate selected subject
$selSubject = null;
foreach ($subjects as $s) { if ((int)$s['id']===$selSubjectId) { $selSubject=$s; break; } }
if (!$selSubject) $selSubjectId = 0;

// Load all assessments for student+subject (all dates)
$assessmentList = [];
$assessment     = null;
$entry          = null;
$criteria       = [];

if ($selStudentId && $selSubjectId) {
    try {
        $alSt = $db->prepare(
            'SELECT mda.*, s.name AS subject_name
             FROM montessori_daily_assessments mda
             JOIN subjects s ON mda.subject_id=s.id
             WHERE mda.student_id=? AND mda.subject_id=?
             ORDER BY mda.assessment_date DESC, mda.created_at DESC'
        );
        $alSt->execute([$selStudentId,$selSubjectId]);
        $rawList = $alSt->fetchAll();
        foreach ($rawList as $a) {
            $a['criteria_arr'] = json_decode($a['criteria'],true) ?? [];
            $assessmentList[]  = $a;
        }
        // If editing a specific record, load it and its entry
        if ($editId) {
            foreach ($assessmentList as $a) {
                if ((int)$a['id'] === $editId) { $assessment = $a; break; }
            }
            if ($assessment) {
                $criteria = $assessment['criteria_arr'];
                $selDate  = $assessment['assessment_date'];
                $eSt = $db->prepare(
                    'SELECT * FROM montessori_daily_assessment_entries WHERE assessment_id=? AND student_id=?'
                );
                $eSt->execute([$assessment['id'],$selStudentId]);
                $row = $eSt->fetch();
                if ($row) { $row['ratings_arr']=json_decode($row['ratings'],true)??[]; $entry=$row; }
            }
        }
    } catch (Exception $e) {}
}
if (empty($criteria) && $selSubject) $criteria = monteDefaultCriteria($selSubject['name']);

// Subject status: has any assessment been done for this student+subject (any date)
$subjectStatusMap = [];
if ($selStudentId && $selClassId) {
    try {
        $ssSt = $db->prepare(
            'SELECT DISTINCT subject_id FROM montessori_daily_assessments
             WHERE class_id=? AND student_id=?'
        );
        $ssSt->execute([$selClassId,$selStudentId]);
        foreach ($ssSt->fetchAll() as $r) $subjectStatusMap[(int)$r['subject_id']] = true;
    } catch (Exception $e) {}
}

// Assessed subjects count per student (distinct subjects, any date)
$studentCountToday = [];
if ($selClassId && !empty($students)) {
    try {
        $stIds = array_column($students,'id');
        $ph    = implode(',',array_fill(0,count($stIds),'?'));
        $cntSt = $db->prepare(
            "SELECT student_id, COUNT(DISTINCT subject_id) AS cnt FROM montessori_daily_assessments
             WHERE class_id=? AND student_id IN ($ph) GROUP BY student_id"
        );
        $cntSt->execute(array_merge([$selClassId],$stIds));
        foreach ($cntSt->fetchAll() as $r) $studentCountToday[(int)$r['student_id']]=(int)$r['cnt'];
    } catch (Exception $e) {}
}
$totalSubjects = count($subjects);

$portalRole = ($user['role']==='wing_head') ? 'wing_head' : 'montessori_teacher';
pageHead('Formative Assessment',$portalRole);
$links = ($user['role']==='wing_head') ? getWingHeadLinks() : getMonteTeacherLinks();
?>
<style>
/* ─── Student Picker Grid ───────────────────────────────────────── */
.student-picker-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding:14px 16px}
@media(max-width:900px){.student-picker-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:480px){.student-picker-grid{grid-template-columns:1fr}}
.stu-pick-card{display:flex;flex-direction:column;align-items:center;gap:5px;padding:10px 8px;border:2px solid #e2e8f0;border-radius:10px;background:#fff;text-decoration:none;color:#374151;transition:.15s;text-align:center}
.stu-pick-card:hover{border-color:#93c5fd;background:#eff6ff;text-decoration:none;color:#1d4ed8;transform:translateY(-1px);box-shadow:0 3px 10px rgba(0,0,0,.07)}
.stu-pick-card.selected{border-color:#2563eb;background:#eff6ff;color:#1d4ed8;box-shadow:0 0 0 3px rgba(37,99,235,.15)}
.stu-avatar{width:38px;height:38px;border-radius:50%;background:#e2e8f0;color:#64748b;font-size:.95rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.stu-pick-card.selected .stu-avatar{background:#dbeafe;color:#1d4ed8}
.stu-pick-name{font-size:.79rem;font-weight:600;line-height:1.2;word-break:break-word}
.stu-pick-roll{font-size:.69rem;color:#94a3b8}
.stu-pick-badge{font-size:.63rem;font-weight:700;padding:2px 7px;border-radius:10px;margin-top:2px;white-space:nowrap}
.stu-pick-badge.all-done{background:#dcfce7;color:#166534}
.stu-pick-badge.partial{background:#fef9c3;color:#854d0e}
.stu-pick-badge.none{background:#f1f5f9;color:#9ca3af}

/* ─── Subject Navigation Pills ─────────────────────────────────── */
.subj-nav-wrap{display:flex;flex-wrap:wrap;gap:8px}
.subj-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;font-size:.82rem;font-weight:600;text-decoration:none;border:2px solid #e2e8f0;transition:.15s;white-space:nowrap;background:#f1f5f9;color:#64748b}
.subj-pill:hover{transform:translateY(-1px);box-shadow:0 3px 10px rgba(0,0,0,.1);text-decoration:none;color:inherit}
.subj-pill .pill-check{width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.6rem;flex-shrink:0}
.subj-pill .pill-check.done{background:#16a34a;color:#fff}
.subj-pill .pill-check.none{background:#d1d5db;color:#6b7280}

/* ─── Combined Criteria + Rating rows ──────────────────────────── */
.crit-rate-list{display:flex;flex-direction:column;gap:6px;margin-top:10px}
.crit-rate-row{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:7px;background:#fafafa;border:1px solid #f1f5f9;flex-wrap:wrap}
.crit-rate-inp{display:flex;align-items:center;gap:6px;flex:1;min-width:140px}
.crit-rate-inp input[type=text]{flex:1;min-width:0;font-size:.82rem}
.crit-drag-icon{color:#cbd5e1;font-size:.72rem;cursor:grab;flex-shrink:0}
.btn-crit-remove{background:none;border:1px solid #fca5a5;border-radius:4px;color:#ef4444;padding:2px 7px;font-size:.68rem;cursor:pointer;flex-shrink:0;transition:.1s}
.btn-crit-remove:hover{background:#fee2e2}
.crit-fixed-label{font-size:.82rem;font-weight:600;white-space:nowrap;min-width:80px}
.crit-rate-overall{background:#eff6ff!important;border-color:#bfdbfe!important}
.crit-rate-remarks{background:#f8fafc!important;border-color:#e5e7eb!important}
.crit-rate-remarks input[name=remarks]{flex:1;min-width:120px;font-size:.82rem}

/* ─── Rating Buttons ────────────────────────────────────────────── */
.rtg-group{display:inline-flex;gap:3px;align-items:center}
.rtg-btn{padding:4px 10px;border:1.5px solid #d1d5db;border-radius:5px;cursor:pointer;font-size:.72rem;font-weight:700;background:#f9fafb;transition:.12s;white-space:nowrap;line-height:1.5}
.rtg-btn:hover:not(:disabled){border-color:#9ca3af;background:#f3f4f6}
.rtg-btn:disabled{cursor:default;opacity:.55}
.rtg-btn.sel-AD{background:#16a34a!important;color:#fff!important;border-color:#16a34a!important}
.rtg-btn.sel-ED{background:#d97706!important;color:#fff!important;border-color:#d97706!important}
.rtg-btn.sel-EMD{background:#dc2626!important;color:#fff!important;border-color:#dc2626!important}

/* ─── Legend Bar ────────────────────────────────────────────────── */
.fa-legend-bar{background:#f8fafc;border-top:1px solid #e2e8f0;padding:10px 16px;display:flex;align-items:center;flex-wrap:wrap;gap:8px;justify-content:space-between}
.legend-chip{display:inline-flex;align-items:center;gap:5px;font-size:.73rem;font-weight:600;padding:3px 10px;border-radius:12px}
.legend-chip.ad{background:#dcfce7;color:#166534}
.legend-chip.ed{background:#fef9c3;color:#854d0e}
.legend-chip.emd{background:#fee2e2;color:#991b1b}
.fa-save-btn{font-size:.82rem;padding:6px 18px}

/* ─── Sidebar progress ──────────────────────────────────────────── */
.summary-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;text-decoration:none;color:inherit;border-radius:4px;transition:.1s}
.summary-row:last-child{border-bottom:none}
.summary-row:hover{background:#f8fafc;margin:0 -8px;padding:8px 8px;text-decoration:none;color:inherit}
.subj-icon-sm{width:26px;height:26px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.72rem;flex-shrink:0}
.summary-subj-name{font-size:.82rem;font-weight:600;color:#374151}
.summary-dot{width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.58rem;flex-shrink:0}
.summary-dot.done{background:#16a34a;color:#fff}
.summary-dot.none{background:#e5e7eb;color:#9ca3af}

/* ─── Breadcrumb ─────────────────────────────────────────────────── */
.fa-breadcrumb{display:flex;align-items:center;flex-wrap:wrap;gap:4px;font-size:.77rem;color:#94a3b8;margin-top:8px}
.fa-breadcrumb .bc-item{color:#374151;font-weight:600}
.fa-breadcrumb .bc-sep{color:#d1d5db}
</style>

<div class="portal-wrap">
<?php sidebar($portalRole,'monte-assessments',$links,$user); ?>
<div class="main-area">
<?php topbar('Formative Assessment',$user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- ── Context Selector Bar ─────────────────────────────────────── -->
<div class="sec-card mb-3" style="padding:14px 18px">
  <form method="GET" id="ctxForm">
    <?php if ($selStudentId): ?><input type="hidden" name="student_id" value="<?= $selStudentId ?>"><?php endif; ?>
    <?php if ($selSubjectId): ?><input type="hidden" name="subject_id" value="<?= $selSubjectId ?>"><?php endif; ?>
    <div class="d-flex align-items-end flex-wrap gap-3">
      <div>
        <label class="form-label fw-semibold mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.4px;color:var(--t3)">Class</label>
        <?php if (empty($assignedClasses)): ?>
        <select class="form-select form-select-sm" disabled style="min-width:160px"><option>No classes assigned</option></select>
        <?php else: ?>
        <select name="class_id" class="form-select form-select-sm" style="min-width:160px"
                onchange="clearStudentAndSubmitCtx()">
          <?php foreach ($assignedClasses as $cl): ?>
          <option value="<?= $cl['id'] ?>" <?= (int)$cl['id']===$selClassId?'selected':'' ?>><?= h($cl['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
      </div>
      <div>
        <label class="form-label fw-semibold mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.4px;color:var(--t3)">Date</label>
        <input type="date" name="date" class="form-control form-control-sm" value="<?= h($selDate) ?>"
               onchange="document.getElementById('ctxForm').submit()" style="flex:1 1 auto;min-width:140px;max-width:180px">
      </div>
      <button type="submit" class="btn btn-primary btn-sm" style="font-size:.8rem">
        <i class="fas fa-sync-alt me-1"></i>Refresh
      </button>
    </div>
    <!-- Breadcrumb -->
    <div class="fa-breadcrumb">
      <span class="bc-item"><?= h($classInfo['name'] ?? ($assignedClasses[0]['name'] ?? '—')) ?></span>
      <?php if ($selStudent): ?>
        <span class="bc-sep">›</span>
        <span class="bc-item"><?= h($selStudent['name']) ?></span>
        <?php if ($selSubject): ?>
          <span class="bc-sep">›</span>
          <span class="bc-item"><?= h($selSubject['name']) ?></span>
          <span class="bc-sep">·</span>
          <span><?= date('d M Y',strtotime($selDate)) ?></span>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php if (!$selClassId || empty($assignedClasses)): ?>
<div class="sec-card">
  <div class="text-center py-5" style="color:var(--t2)">
    <i class="fas fa-clipboard-check fa-2x mb-3" style="opacity:.25"></i>
    <p class="mb-0 fw-semibold" style="font-size:.9rem">No Montessori classes assigned.</p>
    <p class="text-muted mb-0" style="font-size:.8rem;margin-top:4px">Contact the administrator to assign Montessori classes.</p>
  </div>
</div>
<?php else: ?>

<div class="row g-3">

  <!-- ── MAIN COLUMN ──────────────────────────────────────────────── -->
  <div class="col-xl-8 col-lg-12">

    <!-- STEP 1: Select Student ─────────────────────────────────── -->
    <div class="sec-card mb-3">
      <div class="sec-card-header d-flex align-items-center justify-content-between">
        <span><i class="fas fa-user-graduate me-2"></i>Step 1 &mdash; Select a Student</span>
        <span class="badge bg-secondary" style="font-size:.71rem"><?= count($students) ?> students</span>
      </div>
      <?php if (empty($students)): ?>
      <div class="text-center py-4 text-muted" style="font-size:.84rem">
        <i class="fas fa-user-slash mb-2 d-block" style="opacity:.3"></i>
        No active students found in this class.
      </div>
      <?php else: ?>
      <div style="padding:14px 18px">
        <div class="row g-3">
          <div class="col-sm-5">
            <label class="form-label fw-semibold mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.4px;color:var(--t3)">
              <i class="fas fa-search me-1"></i>Search by Name / GR
            </label>
            <input type="text" id="stuSearch" class="form-control form-control-sm"
                   placeholder="Type name or GR number…" autocomplete="off">
          </div>
          <div class="col-sm-7">
            <label class="form-label fw-semibold mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.4px;color:var(--t3)">
              <i class="fas fa-user-graduate me-1"></i>Select from Class
            </label>
            <div class="input-group input-group-sm">
              <select id="stuDropdown" class="form-select">
                <option value="">— Choose a student —</option>
                <?php foreach ($students as $stu):
                  $rno = $stu['roll_no'] ?: ($stu['roll_no_login'] ?? '');
                  $cnt = $studentCountToday[(int)$stu['id']] ?? 0;
                ?>
                <option value="<?= $stu['id'] ?>"
                        data-name="<?= h(mb_strtolower($stu['name'])) ?>"
                        data-roll="<?= h(mb_strtolower($rno)) ?>"
                        <?= (int)$stu['id']===$selStudentId ? 'selected' : '' ?>>
                  <?= h($stu['name']) ?><?= $rno ? ' — '.h($rno) : '' ?><?= $cnt ? " ($cnt/$totalSubjects)" : '' ?>
                </option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="btn btn-primary" onclick="navigateToStudent()" title="Select this student">
                <i class="fas fa-arrow-right"></i>
              </button>
            </div>
          </div>
        </div>
        <?php if ($selStudent): ?>
        <div class="mt-3 d-flex align-items-center gap-2 flex-wrap"
             style="font-size:.81rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:7px;padding:8px 12px">
          <i class="fas fa-check-circle text-success"></i>
          <span>Selected: <strong><?= h($selStudent['name']) ?></strong><?php
            $rno=$selStudent['roll_no']?:($selStudent['roll_no_login']??'');
            if($rno): ?> &mdash; <?= h($rno) ?><?php endif; ?></span>
          <a href="?class_id=<?= $selClassId ?>&student_id=0&date=<?= urlencode($selDate) ?>"
             class="ms-auto text-danger" style="font-size:.75rem;text-decoration:none;white-space:nowrap">
            <i class="fas fa-times-circle me-1"></i>Clear
          </a>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($selStudentId && $selStudent): ?>

    <!-- STEP 2: Select Subject ─────────────────────────────────── -->
    <?php if (empty($subjects)): ?>
    <div class="sec-card mb-3">
      <div class="text-center py-4 text-muted" style="font-size:.84rem">
        <i class="fas fa-book d-block mb-2" style="opacity:.3"></i>
        No subjects assigned for this class.
      </div>
    </div>
    <?php else: ?>
    <div class="sec-card mb-3" style="padding:14px 18px">
      <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--t3);margin-bottom:10px">
        <i class="fas fa-book-open me-1"></i>Step 2 — Select a Subject
      </div>
      <div class="subj-nav-wrap">
        <?php foreach ($subjects as $subj):
          $smid   = (int)$subj['id'];
          $meta   = monteSubjectMeta($subj['name']);
          $done   = isset($subjectStatusMap[$smid]);
          $isAct  = $smid===$selSubjectId;
        ?>
        <a href="?class_id=<?= $selClassId ?>&student_id=<?= $selStudentId ?>&subject_id=<?= $smid ?>&date=<?= urlencode($selDate) ?>"
           class="subj-pill <?= $isAct?'active':'' ?>"
           style="background:<?= ($done||$isAct)?$meta['bg']:'#f1f5f9' ?>;color:<?= ($done||$isAct)?$meta['ic']:'#64748b' ?>;border-color:<?= ($done||$isAct)?$meta['border']:'#e2e8f0' ?><?= $isAct?';box-shadow:0 0 0 3px rgba(30,64,175,.2)':'' ?>">
          <i class="fas <?= $meta['icon'] ?>"></i>
          <?= h($subj['name']) ?>
          <span class="pill-check <?= $done?'done':'none' ?>"><?= $done?'✓':'○' ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($selSubjectId && $selSubject):
      $meta       = monteSubjectMeta($selSubject['name']);
      $showForm   = ($selMode === 'new' || $editId);
      $aId        = $assessment ? $assessment['id'] : 0;
      $topicVal   = $assessment ? ($assessment['topic'] ?? '') : '';
      $ratings    = $entry ? ($entry['ratings_arr'] ?? []) : [];
      $overall    = $entry ? ($entry['overall'] ?? '') : '';
      $remark     = $entry ? ($entry['remarks'] ?? '') : '';
      $formAction = $editId ? 'update_assessment' : 'save_assessment';
      $formDate   = $editId ? h($selDate) : date('Y-m-d');
    ?>

    <!-- Assessment Header ─────────────────────────────────────── -->
    <div class="sec-card mb-3" style="overflow:hidden">
      <div style="background:linear-gradient(90deg,<?= $meta['ic'] ?>,<?= $meta['ic'] ?>cc);color:#fff;padding:12px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <div class="d-flex align-items-center gap-3">
          <div style="width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0">
            <i class="fas <?= $meta['icon'] ?>"></i>
          </div>
          <div>
            <div style="font-weight:700;font-size:.96rem;line-height:1.2"><?= h($selSubject['name']) ?> — Formative Assessment</div>
            <div style="font-size:.74rem;opacity:.88;margin-top:2px">
              <?= h($selStudent['name']) ?> &nbsp;·&nbsp; <?= h($classInfo['name'] ?? '') ?>
              &nbsp;·&nbsp; <?= count($assessmentList) ?> record<?= count($assessmentList)!=1?'s':'' ?>
            </div>
          </div>
        </div>
        <?php if ($showForm): ?>
        <a href="?class_id=<?= $selClassId ?>&student_id=<?= $selStudentId ?>&subject_id=<?= $selSubjectId ?>"
           style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.35);color:#fff;border-radius:7px;padding:5px 13px;font-size:.76rem;font-weight:600;text-decoration:none;white-space:nowrap">
          <i class="fas fa-arrow-left me-1"></i>Back to Records
        </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!$showForm): ?>
    <!-- Assessment Records List ────────────────────────────────── -->
    <div class="sec-card mb-3">
      <div class="sec-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span><i class="fas fa-list me-2"></i>Assessment Records (<?= count($assessmentList) ?>)</span>
        <?php if ($teacher): ?>
        <a href="?class_id=<?= $selClassId ?>&student_id=<?= $selStudentId ?>&subject_id=<?= $selSubjectId ?>&mode=new"
           class="btn btn-sm btn-success" style="font-size:.8rem">
          <i class="fas fa-plus me-1"></i>New Formative Assessment
        </a>
        <?php endif; ?>
      </div>
      <?php if (empty($assessmentList)): ?>
      <div class="text-center py-5" style="color:var(--t2)">
        <i class="fas fa-clipboard fa-2x mb-2 d-block" style="opacity:.25"></i>
        <p class="mb-0 fw-semibold" style="font-size:.85rem">No assessments yet for this subject</p>
        <?php if ($teacher): ?>
        <p class="mb-0 text-muted" style="font-size:.78rem;margin-top:4px">
          Click <strong>New Formative Assessment</strong> to create the first one.
        </p>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <?php
        $overallColors = ['AD'=>['bg'=>'#dcfce7','c'=>'#166534'],'ED'=>['bg'=>'#fef9c3','c'=>'#854d0e'],'EMD'=>['bg'=>'#fee2e2','c'=>'#991b1b']];
        foreach ($assessmentList as $aRec):
          $aEntry = null;
          try {
              $aeSt = $db->prepare('SELECT * FROM montessori_daily_assessment_entries WHERE assessment_id=? AND student_id=?');
              $aeSt->execute([$aRec['id'],$selStudentId]);
              $aRow = $aeSt->fetch();
              if ($aRow) { $aRow['ratings_arr']=json_decode($aRow['ratings'],true)??[]; $aEntry=$aRow; }
          } catch(Exception $e) {}
          $aOverall = $aEntry ? ($aEntry['overall'] ?? '') : '';
          $aRatings = $aEntry ? ($aEntry['ratings_arr'] ?? []) : [];
          $oc = $overallColors[$aOverall] ?? ['bg'=>'#f3f4f6','c'=>'#6b7280'];
      ?>
      <div style="padding:14px 16px;border-bottom:1px solid var(--border)">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div style="flex:1;min-width:0">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
              <span class="fw-semibold" style="font-size:.88rem"><?= date('d M Y',strtotime($aRec['assessment_date'])) ?></span>
              <?php if ($aOverall): ?>
              <span style="background:<?=$oc['bg']?>;color:<?=$oc['c']?>;font-size:.7rem;font-weight:700;padding:2px 9px;border-radius:10px"><?=$aOverall?></span>
              <?php endif; ?>
              <?php if ($aRec['topic']): ?>
              <span style="font-size:.77rem;color:var(--t2)"><?=h($aRec['topic'])?></span>
              <?php endif; ?>
            </div>
            <?php if (!empty($aRatings)): ?>
            <div class="d-flex flex-wrap gap-1 mt-1">
              <?php foreach ($aRatings as $crit => $val): if (!$val) continue;
                $rc = $overallColors[$val] ?? ['bg'=>'#f3f4f6','c'=>'#6b7280'];
              ?>
              <span style="background:<?=$rc['bg']?>;color:<?=$rc['c']?>;font-size:.67rem;font-weight:600;padding:1px 7px;border-radius:8px"><?=h($crit)?>: <?=$val?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ($aEntry && $aEntry['remarks']): ?>
            <div style="font-size:.77rem;color:var(--t2);margin-top:4px"><i class="fas fa-comment-alt me-1" style="opacity:.5"></i><?=h($aEntry['remarks'])?></div>
            <?php endif; ?>
          </div>
          <div class="d-flex gap-1 flex-shrink-0">
            <?php if ($teacher): ?>
            <a href="?class_id=<?=$selClassId?>&student_id=<?=$selStudentId?>&subject_id=<?=$selSubjectId?>&edit_id=<?=$aRec['id']?>"
               class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:3px 9px" title="Edit">
              <i class="fas fa-edit me-1"></i>Edit
            </a>
            <form method="POST" class="d-inline m-0"
                  onsubmit="return confirm('Delete this assessment from <?= h(addslashes(date('d M Y',strtotime($aRec['assessment_date'])))) ?>?')">
              <input type="hidden" name="action" value="delete_assessment">
              <input type="hidden" name="assessment_id" value="<?=$aRec['id']?>">
              <input type="hidden" name="class_id" value="<?=$selClassId?>">
              <input type="hidden" name="student_id" value="<?=$selStudentId?>">
              <input type="hidden" name="subject_id" value="<?=$selSubjectId?>">
              <button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:.74rem;padding:3px 9px" title="Delete">
                <i class="fas fa-trash-alt"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- STEP 3: Assessment Form (New or Edit) ───────────────────── -->
    <form method="POST" id="assessment-form">
      <input type="hidden" name="action" value="<?= $formAction ?>">
      <?php if ($editId): ?><input type="hidden" name="assessment_id" value="<?= $aId ?>"><?php endif; ?>
      <input type="hidden" name="class_id" value="<?= $selClassId ?>">
      <input type="hidden" name="student_id" value="<?= $selStudentId ?>">
      <input type="hidden" name="subject_id" value="<?= $selSubjectId ?>">

      <div class="sec-card mb-0">
        <div class="sec-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
          <span><i class="fas fa-tasks me-2"></i><?= $editId ? 'Edit Assessment — '.date('d M Y',strtotime($selDate)) : 'New Formative Assessment' ?></span>
          <?php if ($teacher): ?>
          <button type="button" onclick="addCrit()"
                  class="btn btn-sm btn-outline-primary" style="font-size:.72rem;padding:3px 10px">
            <i class="fas fa-plus me-1"></i>Add Criterion
          </button>
          <?php endif; ?>
        </div>
        <div style="padding:16px 18px">

          <!-- Date -->
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.8rem">Assessment Date</label>
            <input type="date" name="assessment_date" class="form-control form-control-sm"
                   value="<?= $formDate ?>" max="<?= date('Y-m-d') ?>"
                   <?= $teacher?'':'readonly' ?>>
          </div>

          <!-- Topic -->
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.8rem">
              Topic / Activity <span class="text-muted fw-normal">(optional)</span>
            </label>
            <input type="text" name="topic" class="form-control form-control-sm"
                   placeholder="e.g. Counting, Letter Sounds, Shapes…"
                   value="<?= h($topicVal) ?>"
                   <?= $teacher?'':'readonly' ?>>
          </div>

          <!-- Column headers -->
          <div class="d-flex align-items-center gap-2 mb-1" style="padding:0 10px">
            <span style="flex:1;min-width:140px;font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px">Criterion</span>
            <span style="font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px">Rating</span>
          </div>

          <!-- Combined criteria + ratings list -->
          <div id="crit-rate-list" class="crit-rate-list">
            <?php foreach ($criteria as $i => $crit): ?>
            <div class="crit-rate-row" id="crRow-<?= $i ?>">
              <div class="crit-rate-inp">
                <i class="fas fa-grip-vertical crit-drag-icon"></i>
                <input type="text" name="criteria[]" class="form-control form-control-sm"
                       value="<?= h($crit) ?>" placeholder="Criterion name"
                       oninput="syncHidden(this)"
                       <?= $teacher?'':'readonly' ?>>
                <?php if ($teacher): ?>
                <button type="button" class="btn-crit-remove" onclick="removeCrit(this)" title="Remove"><i class="fas fa-times"></i></button>
                <?php endif; ?>
              </div>
              <div class="rtg-group" data-crit="<?= h($crit) ?>">
                <input type="hidden" name="ratings[<?= h($crit) ?>]" value="<?= h($ratings[$crit]??'') ?>">
                <?php foreach (['AD','ED','EMD'] as $rv): ?>
                <button type="button" class="rtg-btn <?= ($ratings[$crit]??'')===$rv?'sel-'.$rv:'' ?>"
                        data-val="<?= $rv ?>" <?= $teacher?'':'disabled' ?>><?= $rv ?></button>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>

            <!-- Overall (fixed) -->
            <div class="crit-rate-row crit-rate-overall">
              <div class="crit-rate-inp" style="flex:0 0 auto;min-width:140px">
                <span class="crit-fixed-label" style="color:#1d4ed8"><i class="fas fa-star me-1" style="font-size:.72rem"></i>Overall</span>
              </div>
              <div class="rtg-group">
                <input type="hidden" name="overall" value="<?= h($overall) ?>">
                <?php foreach (['AD','ED','EMD'] as $rv): ?>
                <button type="button" class="rtg-btn <?= $overall===$rv?'sel-'.$rv:'' ?>"
                        data-val="<?= $rv ?>" <?= $teacher?'':'disabled' ?>><?= $rv ?></button>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Remarks (fixed) -->
            <div class="crit-rate-row crit-rate-remarks">
              <div class="crit-rate-inp" style="flex:0 0 auto;min-width:140px">
                <span class="crit-fixed-label" style="color:#64748b"><i class="fas fa-comment-alt me-1" style="font-size:.72rem"></i>Remarks</span>
              </div>
              <input type="text" name="remarks" class="form-control form-control-sm"
                     style="flex:1;min-width:120px"
                     value="<?= h($remark) ?>"
                     placeholder="Optional note about this student…"
                     <?= $teacher?'':'readonly' ?>>
            </div>
          </div>
        </div>

        <!-- Legend + Save -->
        <div class="fa-legend-bar">
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <span style="font-size:.74rem;font-weight:700;color:#374151">Performance Level:</span>
            <span class="legend-chip ad"><span style="font-weight:800">AD</span> — Advanced Development</span>
            <span class="legend-chip ed"><span style="font-weight:800">ED</span> — Expected Development</span>
            <span class="legend-chip emd"><span style="font-weight:800">EMD</span> — Emerging Development</span>
          </div>
          <?php if ($teacher): ?>
          <button type="submit" class="btn btn-primary fa-save-btn">
            <i class="fas fa-save me-1"></i><?= $editId ? 'Update Assessment' : 'Save New Assessment' ?>
          </button>
          <?php else: ?>
          <span class="text-muted" style="font-size:.76rem"><i class="fas fa-lock me-1"></i>View only</span>
          <?php endif; ?>
        </div>
      </div><!-- /.sec-card -->
    </form>
    <?php endif; // $showForm ?>

    <?php else: ?>
    <!-- Subject not yet selected -->
    <div class="sec-card mb-3">
      <div class="text-center py-4" style="color:var(--t2)">
        <i class="fas fa-hand-point-up fa-lg mb-2 d-block" style="opacity:.25"></i>
        <p class="mb-0 fw-semibold" style="font-size:.85rem">Select a subject above</p>
        <p class="mb-0 text-muted" style="font-size:.78rem">The assessment form will appear here.</p>
      </div>
    </div>
    <?php endif; ?>

    <?php endif; // !empty($subjects) ?>

    <?php else: ?>
    <!-- Student not yet selected -->
    <div class="sec-card mb-3">
      <div class="text-center py-5" style="color:var(--t2)">
        <i class="fas fa-arrow-up fa-lg mb-2 d-block" style="opacity:.25"></i>
        <p class="mb-0 fw-semibold" style="font-size:.85rem">Select a student above to begin</p>
        <p class="mb-0 text-muted" style="font-size:.78rem">Then choose a subject and record the Formative Assessment.</p>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /col-xl-8 -->

  <!-- ── RIGHT SIDEBAR ──────────────────────────────────────────── -->
  <div class="col-xl-4 col-lg-12">

    <?php if ($selStudentId && $selStudent): ?>
    <!-- Selected Student Card -->
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-user me-2"></i>Selected Student</div>
      <div style="padding:14px 16px">
        <div class="d-flex align-items-center gap-3">
          <div class="stu-avatar" style="width:44px;height:44px;font-size:1.1rem;background:#dbeafe;color:#1d4ed8">
            <?= h(mb_strtoupper(mb_substr($selStudent['name'],0,1))) ?>
          </div>
          <div>
            <div class="fw-semibold" style="font-size:.9rem;color:#1e293b"><?= h($selStudent['name']) ?></div>
            <?php $rno=$selStudent['roll_no']?:($selStudent['roll_no_login']??''); if ($rno): ?>
            <div style="font-size:.74rem;color:#94a3b8">Roll: <?= h($rno) ?></div>
            <?php endif; ?>
            <div style="font-size:.72rem;color:#64748b"><?= h($classInfo['name'] ?? '') ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Subject Assessment Progress for this student -->
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-chart-pie me-2"></i>Subject Progress</div>
      <div style="padding:10px 14px">
        <?php $completedToday=count($subjectStatusMap); foreach ($subjects as $subj):
          $smid=$subj['id']; $meta=monteSubjectMeta($subj['name']); $done=isset($subjectStatusMap[$smid]);
        ?>
        <a href="?class_id=<?=$selClassId?>&student_id=<?=$selStudentId?>&subject_id=<?=$smid?>&date=<?=urlencode($selDate)?>"
           class="summary-row">
          <div class="d-flex align-items-center gap-2">
            <div class="subj-icon-sm" style="background:<?=$meta['bg']?>;color:<?=$meta['ic']?>"><i class="fas <?=$meta['icon']?>"></i></div>
            <span class="summary-subj-name"><?=h($subj['name'])?></span>
          </div>
          <div class="summary-dot <?=$done?'done':'none'?>"><i class="fas <?=$done?'fa-check':'fa-minus'?>"></i></div>
        </a>
        <?php endforeach; ?>
        <div style="font-size:.73rem;color:var(--t3);padding-top:8px;border-top:1px solid #f1f5f9;margin-top:2px">
          <i class="fas fa-info-circle me-1"></i><?=$completedToday?> of <?=count($subjects)?> subjects assessed
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-bolt me-2"></i>Quick Actions</div>
      <div style="padding:12px;display:flex;flex-direction:column;gap:8px">
        <a href="<?= url('/portal/montessori/assessment-history.php') . ($selClassId ? '?class_id='.$selClassId.($selStudentId ? '&student_id='.$selStudentId : '') : '') ?>"
           class="btn btn-outline-secondary w-100" style="font-size:.82rem;text-align:left">
          <i class="fas fa-history me-2"></i>Assessment History
        </a>
        <a href="<?= url('/portal/progress-report/form.php') ?>"
           class="btn btn-outline-secondary w-100" style="font-size:.82rem;text-align:left">
          <i class="fas fa-file-alt me-2"></i>Progress Report
        </a>
      </div>
    </div>

    <!-- About -->
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-clipboard-check me-2"></i>About Formative Assessment</div>
      <div style="padding:14px">
        <p style="font-size:.79rem;color:var(--t2);margin-bottom:10px">
          Track each student's daily progress independently across every subject.
        </p>
        <div style="font-size:.75rem;color:var(--t3)">
          <div style="margin-bottom:6px"><i class="fas fa-check-circle text-success me-1"></i>Each student's record is fully independent</div>
          <div style="margin-bottom:6px"><i class="fas fa-check-circle text-success me-1"></i>Results visible to students after saving</div>
          <div><i class="fas fa-check-circle text-success me-1"></i>Full history available at any time</div>
        </div>
      </div>
    </div>

  </div><!-- /col-xl-4 -->

</div><!-- /row -->
<?php endif; ?>
</div></div></div>

<script>
// ── Rating Button Toggle ──────────────────────────────────────────
document.addEventListener('click', function(e) {
  var btn = e.target.closest('.rtg-btn');
  if (!btn || btn.disabled) return;
  var grp    = btn.closest('.rtg-group');
  var hidden = grp ? grp.querySelector('input[type=hidden]') : null;
  var val    = btn.dataset.val;
  var cur    = hidden ? hidden.value : '';
  grp.querySelectorAll('.rtg-btn').forEach(function(b) {
    b.classList.remove('sel-AD','sel-ED','sel-EMD');
  });
  if (cur === val) {
    if (hidden) hidden.value = '';
  } else {
    btn.classList.add('sel-' + val);
    if (hidden) hidden.value = val;
  }
});

// ── Add Criterion ─────────────────────────────────────────────────
function addCrit() {
  var list    = document.getElementById('crit-rate-list');
  var rows    = list.querySelectorAll('.crit-rate-row:not(.crit-rate-overall):not(.crit-rate-remarks)');
  var idx     = rows.length;
  var label   = 'Criterion ' + (idx + 1);
  var overall = list.querySelector('.crit-rate-overall');
  var row     = document.createElement('div');
  row.className = 'crit-rate-row';
  row.id = 'crRow-' + idx;
  row.innerHTML =
    '<div class="crit-rate-inp">' +
    '<i class="fas fa-grip-vertical crit-drag-icon"></i>' +
    '<input type="text" name="criteria[]" class="form-control form-control-sm"' +
    ' value="' + label + '" placeholder="Criterion name" oninput="syncHidden(this)">' +
    '<button type="button" class="btn-crit-remove" onclick="removeCrit(this)" title="Remove">' +
    '<i class="fas fa-times"></i></button>' +
    '</div>' +
    '<div class="rtg-group" data-crit="' + label + '">' +
    '<input type="hidden" name="ratings[' + label + ']" value="">' +
    '<button type="button" class="rtg-btn" data-val="AD">AD</button>' +
    '<button type="button" class="rtg-btn" data-val="ED">ED</button>' +
    '<button type="button" class="rtg-btn" data-val="EMD">EMD</button>' +
    '</div>';
  list.insertBefore(row, overall);
}

// ── Remove Criterion ──────────────────────────────────────────────
function removeCrit(btn) {
  var list = document.getElementById('crit-rate-list');
  var rows = list.querySelectorAll('.crit-rate-row:not(.crit-rate-overall):not(.crit-rate-remarks)');
  if (rows.length <= 1) { alert('At least one criterion is required.'); return; }
  btn.closest('.crit-rate-row').remove();
}

// ── Sync hidden input name when criterion text changes ────────────
function syncHidden(inp) {
  var row    = inp.closest('.crit-rate-row');
  var grp    = row ? row.querySelector('.rtg-group') : null;
  var hidden = grp ? grp.querySelector('input[type=hidden]') : null;
  var label  = inp.value.trim();
  if (!label) return;
  if (grp) grp.dataset.crit = label;
  if (hidden) hidden.name = 'ratings[' + label + ']';
}

// ── Clear student+subject when class changes, then submit ─────────
function clearStudentAndSubmitCtx() {
  var f = document.getElementById('ctxForm');
  ['student_id','subject_id'].forEach(function(n) {
    var h = f.querySelector('input[name=' + n + ']');
    if (h) h.remove();
  });
  f.submit();
}

// ── Navigate to selected student (preserving date) ────────────────
function navigateToStudent() {
  var dd = document.getElementById('stuDropdown');
  if (!dd || !dd.value) { if (dd) dd.focus(); return; }
  var dateEl = document.querySelector('#ctxForm input[name=date]');
  var date   = dateEl ? dateEl.value : '<?= h($selDate) ?>';
  window.location.href = '?class_id=<?= $selClassId ?>'
    + '&student_id=' + encodeURIComponent(dd.value)
    + '&date='       + encodeURIComponent(date);
}

// ── Student search: live-filters the dropdown ─────────────────────
(function() {
  var dd = document.getElementById('stuDropdown');
  var sr = document.getElementById('stuSearch');
  if (!dd || !sr) return;

  function filterDd(q) {
    q = (q || '').trim().toLowerCase();
    var first = null;
    for (var i = 1; i < dd.options.length; i++) {
      var o = dd.options[i];
      var match = !q
        || (o.dataset.name || '').indexOf(q) !== -1
        || (o.dataset.roll || '').indexOf(q) !== -1;
      o.hidden = !match;
      if (match && !first) first = o;
    }
    // If selected option is now hidden, reset to placeholder
    if (dd.value && dd.options[dd.selectedIndex] && dd.options[dd.selectedIndex].hidden) {
      dd.value = '';
    }
  }

  sr.addEventListener('input', function() { filterDd(this.value); });

  sr.addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    var q = this.value.trim().toLowerCase();
    var visible = [];
    for (var i = 1; i < dd.options.length; i++) {
      if (!dd.options[i].hidden) visible.push(dd.options[i]);
    }
    if (visible.length === 1) {
      dd.value = visible[0].value;
      navigateToStudent();
    } else if (dd.value) {
      navigateToStudent();
    }
  });

  // Sync: when dropdown changes, clear the search text
  dd.addEventListener('change', function() {
    if (this.value) { sr.value = ''; filterDd(''); }
  });
})();
</script>
<?php pageFooter(); ?>

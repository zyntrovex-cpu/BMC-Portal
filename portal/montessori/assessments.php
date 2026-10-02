<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/db.php';

$user    = requireAuth('montessori_teacher', 'wing_head');
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);

// ── Helpers ───────────────────────────────────────────────────────────────────
function monteDefaultCriteria(string $subject): array {
    $s = mb_strtolower(trim($subject));
    if (str_contains($s, 'english'))                              return ['Reading', 'Writing', 'Participation'];
    if (str_contains($s, 'math') || str_contains($s, 'maths'))   return ['Number Concepts', 'Application', 'Participation'];
    if (str_contains($s, 'science'))                              return ['Observation', 'Understanding', 'Participation'];
    if (str_contains($s, 'urdu'))                                 return ['Reading', 'Vocabulary', 'Participation'];
    if (str_contains($s, 'islamic') || str_contains($s, 'islamiat')) return ['Comprehension', 'Recitation', 'Participation'];
    if (str_contains($s, 'art') || str_contains($s, 'craft'))    return ['Creativity', 'Technique', 'Participation'];
    if (str_contains($s, 'general') || str_contains($s, 'knowledge')) return ['Knowledge', 'Expression', 'Participation'];
    if (str_contains($s, 'physical') || str_contains($s, 'p.e')) return ['Motor Skills', 'Coordination', 'Participation'];
    return ['Concept', 'Practice', 'Participation'];
}

function monteSubjectMeta(string $name): array {
    $n = mb_strtolower($name);
    if (str_contains($n, 'english'))  return ['icon'=>'fa-book',        'bg'=>'#dbeafe','ic'=>'#1d4ed8','tile'=>'#eff6ff','border'=>'#bfdbfe'];
    if (str_contains($n, 'math'))     return ['icon'=>'fa-calculator',  'bg'=>'#dcfce7','ic'=>'#15803d','tile'=>'#f0fdf4','border'=>'#bbf7d0'];
    if (str_contains($n, 'science'))  return ['icon'=>'fa-flask',       'bg'=>'#ede9fe','ic'=>'#7c3aed','tile'=>'#f5f3ff','border'=>'#ddd6fe'];
    if (str_contains($n, 'urdu'))     return ['icon'=>'fa-language',    'bg'=>'#fef3c7','ic'=>'#b45309','tile'=>'#fffbeb','border'=>'#fde68a'];
    if (str_contains($n, 'islamic'))  return ['icon'=>'fa-mosque',      'bg'=>'#ccfbf1','ic'=>'#0d9488','tile'=>'#f0fdfa','border'=>'#99f6e4'];
    if (str_contains($n, 'art') || str_contains($n, 'craft'))
                                      return ['icon'=>'fa-paint-brush', 'bg'=>'#fce7f3','ic'=>'#be185d','tile'=>'#fdf2f8','border'=>'#fbcfe8'];
    if (str_contains($n, 'general'))  return ['icon'=>'fa-globe',       'bg'=>'#e0f2fe','ic'=>'#0369a1','tile'=>'#f0f9ff','border'=>'#bae6fd'];
    if (str_contains($n, 'physical')) return ['icon'=>'fa-running',     'bg'=>'#ffedd5','ic'=>'#c2410c','tile'=>'#fff7ed','border'=>'#fed7aa'];
    return ['icon'=>'fa-book-open','bg'=>'#f3f4f6','ic'=>'#4b5563','tile'=>'#f9fafb','border'=>'#e5e7eb'];
}

function monteRatingBadge(string $v): string {
    if ($v === 'AD')  return '<span class="mda-badge ad">AD</span>';
    if ($v === 'ED')  return '<span class="mda-badge ed">ED</span>';
    if ($v === 'EMD') return '<span class="mda-badge emd">EMD</span>';
    return '<span class="mda-badge none">—</span>';
}

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_assessment') {
        $classId   = (int)($_POST['class_id']       ?? 0);
        $subjectId = (int)($_POST['subject_id']     ?? 0);
        $topic     = substr(trim($_POST['topic']    ?? ''), 0, 200);
        $date      = $_POST['assessment_date']      ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

        $criteria = array_values(array_filter(array_map('trim', (array)($_POST['criteria'] ?? []))));
        if (empty($criteria)) $criteria = ['Participation'];

        if (!$classId || !$subjectId) {
            setFlash('danger', 'Class and subject are required.');
            redirect('/portal/montessori/assessments.php?class_id='.$classId.'&date='.urlencode($date));
        }

        // Verify assignment
        if ($teacher) {
            $chk = $db->prepare(
                'SELECT 1 FROM class_subjects cs JOIN classes c ON cs.class_id=c.id
                 WHERE cs.teacher_id=? AND cs.class_id=? AND cs.subject_id=? AND c.is_montessori=1 LIMIT 1'
            );
            $chk->execute([$teacher['id'], $classId, $subjectId]);
            if (!$chk->fetchColumn()) {
                setFlash('danger', 'You are not assigned to this class/subject.');
                redirect('/portal/montessori/assessments.php?class_id='.$classId.'&date='.urlencode($date));
            }
        }

        // Upsert header
        $db->prepare(
            'INSERT INTO montessori_daily_assessments
             (class_id,subject_id,topic,assessment_date,criteria,teacher_id)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE topic=VALUES(topic),criteria=VALUES(criteria),teacher_id=VALUES(teacher_id),updated_at=NOW()'
        )->execute([$classId,$subjectId,$topic?:null,$date,json_encode($criteria),$teacher?$teacher['id']:1]);

        $assessmentId = (int)$db->lastInsertId();
        if (!$assessmentId) {
            $f = $db->prepare('SELECT id FROM montessori_daily_assessments WHERE class_id=? AND subject_id=? AND assessment_date=?');
            $f->execute([$classId,$subjectId,$date]);
            $assessmentId = (int)$f->fetchColumn();
        }

        // Upsert entries
        $rawRatings = $_POST['ratings'] ?? [];
        $rawOverall = $_POST['overall'] ?? [];
        $rawRemarks = $_POST['remarks'] ?? [];
        foreach (getClassStudents($classId) as $stu) {
            $sid    = $stu['id'];
            $raw    = $rawRatings[$sid] ?? [];
            $overall= trim($rawOverall[$sid] ?? '');
            $remark = substr(trim($rawRemarks[$sid] ?? ''), 0, 500);
            $clean  = [];
            foreach ($criteria as $crit) {
                $v = $raw[$crit] ?? '';
                $clean[$crit] = in_array($v, ['AD','ED','EMD'], true) ? $v : '';
            }
            $db->prepare(
                'INSERT INTO montessori_daily_assessment_entries
                 (assessment_id,student_id,ratings,overall,remarks)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE ratings=VALUES(ratings),overall=VALUES(overall),remarks=VALUES(remarks)'
            )->execute([
                $assessmentId,$sid,json_encode($clean),
                in_array($overall,['AD','ED','EMD'],true)?$overall:null,
                $remark?:null
            ]);
        }

        logActivity($user['id'],'montessori_assessment_save',
            "Daily assessment: class #$classId, subject #$subjectId, $date");
        setFlash('success','Assessment saved successfully.');
        redirect('/portal/montessori/assessments.php?class_id='.$classId.'&date='.urlencode($date).'&show_subject='.$subjectId);
    }

    if ($action === 'delete_assessment') {
        $aId     = (int)($_POST['assessment_id'] ?? 0);
        $classId = (int)($_POST['class_id']      ?? 0);
        $date    = $_POST['date'] ?? date('Y-m-d');
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
        redirect('/portal/montessori/assessments.php?class_id='.$classId.'&date='.urlencode($date));
    }
}

// ── GET: load page data ───────────────────────────────────────────────────────
$selClassId    = (int)($_GET['class_id']     ?? $_SESSION['monte_assess_cls'] ?? 0);
$selDate       = $_GET['date']               ?? date('Y-m-d');
$showSubjectId = (int)($_GET['show_subject'] ?? 0);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selDate)) $selDate = date('Y-m-d');

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

// Today's assessments keyed by subject_id
$assessmentsToday = [];
if ($selClassId) {
    try {
        $aSt = $db->prepare(
            'SELECT mda.*,s.name AS subject_name
             FROM montessori_daily_assessments mda
             JOIN subjects s ON mda.subject_id=s.id
             WHERE mda.class_id=? AND mda.assessment_date=?'
        );
        $aSt->execute([$selClassId,$selDate]);
        foreach ($aSt->fetchAll() as $a) {
            $a['criteria_arr'] = json_decode($a['criteria'],true) ?? [];
            $assessmentsToday[(int)$a['subject_id']] = $a;
        }
    } catch (Exception $e) {}
}

// Students
$students      = $selClassId ? getClassStudents($selClassId) : [];
$totalStudents = count($students);

// Entries for each assessment
$entriesMap       = [];   // assessment_id => [student_id => entry]
$assessedCountMap = [];   // subject_id    => count

if ($assessmentsToday) {
    try {
        $aIds = array_column(array_values($assessmentsToday),'id');
        $ph   = implode(',',array_fill(0,count($aIds),'?'));
        $eSt  = $db->prepare("SELECT * FROM montessori_daily_assessment_entries WHERE assessment_id IN ($ph)");
        $eSt->execute($aIds);
        foreach ($eSt->fetchAll() as $e) {
            $e['ratings_arr'] = json_decode($e['ratings'],true) ?? [];
            $entriesMap[$e['assessment_id']][$e['student_id']] = $e;
        }
        foreach ($assessmentsToday as $sid => $a) {
            $assessedCountMap[$sid] = count($entriesMap[$a['id']] ?? []);
        }
    } catch (Exception $e) {}
}

// Page rendering
$portalRole = ($user['role'] === 'wing_head') ? 'wing_head' : 'montessori_teacher';
pageHead('Daily Assessment',$portalRole);
$links = ($user['role'] === 'wing_head') ? getWingHeadLinks() : getMonteTeacherLinks();
?>
<style>
/* Subject tiles */
.subject-tile{border-radius:12px;padding:14px 12px;cursor:pointer;transition:.18s;border:2px solid transparent;position:relative;user-select:none}
.subject-tile:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,.1)}
.subject-tile.active-tile{border-color:#1e40af!important;box-shadow:0 4px 14px rgba(30,64,175,.22)}
.tile-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;margin-bottom:8px}
.tile-check{position:absolute;top:9px;right:9px;width:21px;height:21px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.65rem}
.tile-check.done{background:#16a34a;color:#fff}
.tile-check.undone{background:#e5e7eb;color:#9ca3af}

/* Rating badges (read-only) */
.mda-badge{display:inline-block;padding:2px 9px;border-radius:6px;font-size:.71rem;font-weight:700;letter-spacing:.3px}
.mda-badge.ad{background:#dcfce7;color:#166534}
.mda-badge.ed{background:#fef9c3;color:#854d0e}
.mda-badge.emd{background:#fee2e2;color:#991b1b}
.mda-badge.none{background:#f3f4f6;color:#9ca3af}

/* Rating toggle buttons (entry mode) */
.rtg-group{display:flex;gap:2px;justify-content:center;align-items:center}
.rtg-btn{padding:2px 7px;border:1px solid #d1d5db;border-radius:4px;cursor:pointer;font-size:.7rem;font-weight:700;background:#f9fafb;transition:.1s;white-space:nowrap}
.rtg-btn:hover{border-color:#9ca3af;background:#f3f4f6}
.rtg-btn.sel-AD{background:#16a34a!important;color:#fff!important;border-color:#16a34a!important}
.rtg-btn.sel-ED{background:#d97706!important;color:#fff!important;border-color:#d97706!important}
.rtg-btn.sel-EMD{background:#dc2626!important;color:#fff!important;border-color:#dc2626!important}

/* Assessment section */
.assessment-section{display:none}
.sec-bar{background:linear-gradient(90deg,#0f2456,#1e40af);color:#fff;padding:10px 16px;border-radius:8px 8px 0 0;display:flex;align-items:center;justify-content:space-between}
.mda-table th{font-size:.75rem;font-weight:600;background:#f8fafc;padding:6px 8px;vertical-align:middle;text-align:center;white-space:nowrap;border-bottom:2px solid #e2e8f0}
.mda-table td{font-size:.79rem;padding:5px 7px;vertical-align:middle;text-align:center}
.mda-table th:nth-child(1),.mda-table th:nth-child(2),.mda-table th:nth-child(3){text-align:left}
.mda-table td:nth-child(2){text-align:left;font-weight:600}
.mda-table td:nth-child(3){text-align:left;color:#6b7280;font-size:.75rem}

/* Right sidebar */
.rs-subject-row{display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid #f1f5f9;cursor:pointer;transition:.1s}
.rs-subject-row:last-child{border-bottom:none}
.rs-subject-row:hover{background:#fafafa;margin:0 -14px;padding:7px 14px}
.rs-check{width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.62rem;flex-shrink:0}
.rs-check.done{background:#16a34a;color:#fff}
.rs-check.partial{background:#d97706;color:#fff}
.rs-check.none{background:#e5e7eb;color:#6b7280}

/* Criteria row in form */
.crit-row{display:inline-flex;align-items:center;gap:4px;margin:2px}

.legend-bar{background:#f8fafc;border-top:1px solid #e2e8f0;padding:8px 14px;display:flex;gap:14px;flex-wrap:wrap;font-size:.73rem;align-items:center}
</style>

<div class="portal-wrap">
<?php sidebar($portalRole,'monte-assessments',$links,$user); ?>
<div class="main-area">
<?php topbar('Daily Assessment',$user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Date & Class Filter -->
<div class="sec-card mb-3" style="padding:12px 16px">
  <form method="GET" class="d-flex align-items-end gap-2 flex-wrap">
    <div>
      <label class="form-label fw-semibold mb-1" style="font-size:.77rem">Class</label>
      <?php if (empty($assignedClasses)): ?>
        <select class="form-select form-select-sm" disabled><option>No classes assigned</option></select>
      <?php else: ?>
        <select name="class_id" class="form-select form-select-sm" style="min-width:150px" onchange="this.form.submit()">
          <?php foreach ($assignedClasses as $cl): ?>
          <option value="<?= $cl['id'] ?>" <?= (int)$cl['id']===$selClassId?'selected':'' ?>><?= h($cl['name']) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
    </div>
    <div>
      <label class="form-label fw-semibold mb-1" style="font-size:.77rem">Date</label>
      <input type="date" name="date" class="form-control form-control-sm" value="<?= h($selDate) ?>"
             onchange="this.form.submit()" style="width:145px">
    </div>
    <button type="submit" class="btn btn-sm btn-outline-primary" style="font-size:.78rem">
      <i class="fas fa-sync-alt me-1"></i>Go
    </button>
  </form>
</div>

<?php if (!$selClassId || empty($subjects)): ?>
<div class="alert alert-info" style="font-size:.83rem">
  <i class="fas fa-info-circle me-2"></i>
  <?= !$selClassId ? 'No Montessori classes found. Ask the administrator to assign classes.' : 'No subjects assigned to you for this class.' ?>
</div>
<?php else: ?>

<div class="row g-3">
  <!-- ── LEFT: Tiles + assessment forms ──────────────────────────── -->
  <div class="col-lg-8">

    <p class="mb-2" style="font-size:.82rem;color:var(--t2)">
      Here is your class assessment summary for
      <strong><?= date('l, d M Y', strtotime($selDate)) ?></strong>
      <?php if ($classInfo): ?> &mdash; <strong><?= h($classInfo['name']) ?></strong><?php endif; ?>
    </p>

    <!-- Subject Tiles -->
    <div class="sec-card mb-3">
      <div class="sec-card-header">
        <i class="fas fa-clipboard-check me-2"></i>Today's Assessments
        <small class="fw-normal ms-2" style="font-size:.74rem;opacity:.8">Select subject to add / view assessment details.</small>
      </div>
      <div style="padding:16px">
        <div class="row g-2">
          <?php foreach ($subjects as $subj):
            $meta     = monteSubjectMeta($subj['name']);
            $assessed = isset($assessmentsToday[(int)$subj['id']]);
            $topic    = $assessed ? ($assessmentsToday[(int)$subj['id']]['topic'] ?? '') : '';
          ?>
          <div class="col-6 col-md-4 col-lg-3">
            <div class="subject-tile" id="tile-<?= $subj['id'] ?>"
                 style="background:<?= $meta['tile'] ?>;border-color:<?= $meta['border'] ?>"
                 onclick="toggleSection(<?= $subj['id'] ?>)" title="<?= h($subj['name']) ?>">
              <div class="tile-check <?= $assessed ? 'done' : 'undone' ?>">
                <i class="fas <?= $assessed ? 'fa-check' : 'fa-plus' ?>"></i>
              </div>
              <div class="tile-icon" style="background:<?= $meta['bg'] ?>;color:<?= $meta['ic'] ?>">
                <i class="fas <?= $meta['icon'] ?>"></i>
              </div>
              <div style="font-size:.84rem;font-weight:700;color:#1e293b"><?= h($subj['name']) ?></div>
              <div style="font-size:.72rem;font-weight:600;margin-top:3px;color:<?= $assessed ? '#16a34a' : '#9ca3af' ?>">
                <?= $assessed ? 'Assessment Taken' : 'Not Assessed' ?>
              </div>
              <?php if ($topic): ?>
              <div style="font-size:.69rem;color:#6b7280;margin-top:1px">(<?= h($topic) ?>)</div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Per-Subject Assessment Forms -->
    <?php foreach ($subjects as $subj):
      $sid        = (int)$subj['id'];
      $meta       = monteSubjectMeta($subj['name']);
      $assessed   = isset($assessmentsToday[$sid]);
      $assessment = $assessed ? $assessmentsToday[$sid] : null;
      $criteria   = $assessment ? $assessment['criteria_arr'] : monteDefaultCriteria($subj['name']);
      $entries    = $assessment ? ($entriesMap[$assessment['id']] ?? []) : [];
      $aId        = $assessment ? $assessment['id'] : 0;
    ?>
    <div class="assessment-section" id="section-<?= $sid ?>">
      <div class="sec-card mb-3">
        <!-- Section Header -->
        <div class="sec-bar">
          <div>
            <i class="fas <?= $meta['icon'] ?> me-2"></i>
            <strong><?= h($subj['name']) ?> Assessment</strong>
            <?php if ($assessment && $assessment['topic']): ?>
            <span style="font-size:.78rem;opacity:.82;margin-left:6px">(<?= h($assessment['topic']) ?>)</span>
            <?php endif; ?>
          </div>
          <div class="d-flex gap-2">
            <?php if ($assessed && $teacher): ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this assessment and all student entries?')">
              <input type="hidden" name="action" value="delete_assessment">
              <input type="hidden" name="assessment_id" value="<?= $aId ?>">
              <input type="hidden" name="class_id" value="<?= $selClassId ?>">
              <input type="hidden" name="date" value="<?= h($selDate) ?>">
              <button type="submit" class="btn btn-sm" style="font-size:.72rem;background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.35)">
                <i class="fas fa-trash-alt"></i>
              </button>
            </form>
            <?php endif; ?>
            <button type="button" onclick="toggleSection(<?= $sid ?>)"
                    class="btn btn-sm" style="font-size:.72rem;background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3)">
              <i class="fas fa-times"></i>
            </button>
          </div>
        </div>

        <!-- Assessment Form -->
        <form method="POST" id="form-<?= $sid ?>">
          <input type="hidden" name="action" value="save_assessment">
          <input type="hidden" name="class_id" value="<?= $selClassId ?>">
          <input type="hidden" name="subject_id" value="<?= $sid ?>">
          <input type="hidden" name="assessment_date" value="<?= h($selDate) ?>">

          <!-- Topic & Criteria -->
          <div style="padding:12px 14px;border-bottom:1px solid #e2e8f0;background:#fafbfc">
            <div class="row g-2 align-items-start">
              <div class="col-sm-4">
                <label class="form-label fw-semibold" style="font-size:.76rem">Topic / Focus Area</label>
                <input type="text" name="topic" class="form-control form-control-sm" style="font-size:.8rem"
                       placeholder="e.g. Reading &amp; Writing"
                       value="<?= h($assessment ? ($assessment['topic'] ?? '') : '') ?>">
              </div>
              <div class="col-sm-8">
                <label class="form-label fw-semibold" style="font-size:.76rem">
                  Assessment Criteria
                  <button type="button" onclick="addCriteria(<?= $sid ?>)"
                          class="btn btn-xs btn-outline-primary ms-1" style="font-size:.66rem;padding:1px 6px">
                    <i class="fas fa-plus"></i>
                  </button>
                </label>
                <div id="crit-list-<?= $sid ?>" class="d-flex flex-wrap align-items-center">
                  <?php foreach ($criteria as $i => $crit): ?>
                  <div class="crit-row" id="crow-<?= $sid ?>-<?= $i ?>">
                    <input type="text" name="criteria[]" class="form-control form-control-sm crit-inp"
                           value="<?= h($crit) ?>" placeholder="Criterion" style="width:120px;font-size:.77rem"
                           data-subj="<?= $sid ?>" onchange="syncHeaders(<?= $sid ?>)">
                    <button type="button" class="btn btn-xs btn-outline-danger" style="font-size:.64rem;padding:1px 5px"
                            onclick="removeCrit(<?= $sid ?>,this)"><i class="fas fa-times"></i></button>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Student Table -->
          <div class="table-responsive">
            <table class="table table-hover mb-0 mda-table" id="mtable-<?= $sid ?>">
              <thead>
                <tr>
                  <th style="width:38px">Sr.</th>
                  <th>Student Name</th>
                  <th style="width:72px">Roll No.</th>
                  <?php foreach ($criteria as $crit): ?>
                  <th class="crit-hdr" data-subj="<?= $sid ?>"><?= h($crit) ?></th>
                  <?php endforeach; ?>
                  <th>Overall<br>Performance</th>
                  <th style="min-width:90px">Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($students as $idx => $stu):
                  $entry   = $entries[$stu['id']] ?? null;
                  $ratings = $entry ? ($entry['ratings_arr'] ?? []) : [];
                  $overall = $entry ? ($entry['overall']     ?? '') : '';
                  $remark  = $entry ? ($entry['remarks']     ?? '') : '';
                ?>
                <tr>
                  <td class="text-muted" style="font-size:.76rem"><?= $idx+1 ?></td>
                  <td><?= h($stu['name']) ?></td>
                  <td><?= h($stu['roll_no'] ?: ($stu['roll_no_login'] ?? '—')) ?></td>
                  <?php foreach ($criteria as $crit): ?>
                  <td>
                    <div class="rtg-group" data-crit="<?= h($crit) ?>">
                      <input type="hidden" name="ratings[<?= $stu['id'] ?>][<?= h($crit) ?>]"
                             value="<?= h($ratings[$crit] ?? '') ?>">
                      <?php foreach (['AD','ED','EMD'] as $rv): ?>
                      <button type="button" class="rtg-btn <?= ($ratings[$crit]??'')===$rv?'sel-'.$rv:'' ?>"
                              data-val="<?= $rv ?>"><?= $rv ?></button>
                      <?php endforeach; ?>
                    </div>
                  </td>
                  <?php endforeach; ?>
                  <td>
                    <div class="rtg-group rtg-overall">
                      <input type="hidden" name="overall[<?= $stu['id'] ?>]" value="<?= h($overall) ?>">
                      <?php foreach (['AD','ED','EMD'] as $rv): ?>
                      <button type="button" class="rtg-btn <?= $overall===$rv?'sel-'.$rv:'' ?>"
                              data-val="<?= $rv ?>"><?= $rv ?></button>
                      <?php endforeach; ?>
                    </div>
                  </td>
                  <td>
                    <input type="text" name="remarks[<?= $stu['id'] ?>]" class="form-control form-control-sm"
                           value="<?= h($remark) ?>" placeholder="Optional…" style="font-size:.73rem;min-width:88px">
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Legend + Save -->
          <div class="legend-bar">
            <span style="font-weight:600">Performance Level:</span>
            <span><span class="mda-badge ad">AD</span> = Advanced Development</span>
            <span><span class="mda-badge ed">ED</span> = Expected Development</span>
            <span><span class="mda-badge emd">EMD</span> = Emerging Development</span>
            <?php if ($teacher): ?>
            <button type="submit" class="btn btn-primary btn-sm ms-auto" style="font-size:.79rem">
              <i class="fas fa-save me-1"></i><?= $assessed ? 'Update' : 'Save Assessment' ?>
            </button>
            <?php else: ?>
            <span class="text-muted ms-auto" style="font-size:.75rem"><i class="fas fa-lock me-1"></i>View only</span>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
    <?php endforeach; ?>

  </div><!-- /col-lg-8 -->

  <!-- ── RIGHT SIDEBAR ─────────────────────────────────────────────── -->
  <div class="col-lg-4">

    <!-- Subject Summary -->
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-chart-pie me-2"></i>Subject Summary</div>
      <div style="padding:10px 14px">
        <?php foreach ($subjects as $subj):
          $sid   = (int)$subj['id'];
          $meta  = monteSubjectMeta($subj['name']);
          $cnt   = $assessedCountMap[$sid] ?? 0;
          $done  = isset($assessmentsToday[$sid]);
          $rcls  = $done ? ($cnt>=$totalStudents?'done':'partial') : 'none';
          $rico  = $done ? ($cnt>=$totalStudents?'fa-check':'fa-circle') : 'fa-minus';
        ?>
        <div class="rs-subject-row" onclick="toggleSection(<?= $sid ?>)">
          <div class="d-flex align-items-center gap-2">
            <div style="width:26px;height:26px;border-radius:7px;background:<?= $meta['bg'] ?>;color:<?= $meta['ic'] ?>;display:flex;align-items:center;justify-content:center;font-size:.72rem;flex-shrink:0">
              <i class="fas <?= $meta['icon'] ?>"></i>
            </div>
            <span style="font-size:.82rem;font-weight:600"><?= h($subj['name']) ?></span>
          </div>
          <div class="d-flex align-items-center gap-1">
            <span style="font-size:.74rem;color:<?= $done ? '#6b7280' : '#9ca3af' ?>">
              <?= $done ? "$cnt / $totalStudents assessed" : 'Not assessed' ?>
            </span>
            <div class="rs-check <?= $rcls ?>"><i class="fas <?= $rico ?>"></i></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-bolt me-2"></i>Quick Actions</div>
      <div style="padding:12px">
        <?php $unassessed = array_filter($subjects, fn($s)=>!isset($assessmentsToday[(int)$s['id']])); ?>
        <?php if ($teacher && !empty($unassessed)): ?>
        <div class="dropdown mb-2">
          <button class="btn btn-primary w-100" style="font-size:.82rem" type="button" data-bs-toggle="dropdown">
            <i class="fas fa-plus me-1"></i>Add Assessment
          </button>
          <ul class="dropdown-menu w-100" style="font-size:.82rem">
            <?php foreach ($unassessed as $s): ?>
            <li><a class="dropdown-item" href="#" onclick="toggleSection(<?= $s['id'] ?>);return false">
              <i class="fas <?= monteSubjectMeta($s['name'])['icon'] ?> me-2"></i><?= h($s['name']) ?>
            </a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
        <a href="/portal/montessori/assessment-history.php?class_id=<?= $selClassId ?>"
           class="btn btn-outline-secondary w-100 mb-2" style="font-size:.82rem">
          <i class="fas fa-history me-1"></i>View History
        </a>
        <a href="/portal/progress-report/form.php"
           class="btn btn-outline-secondary w-100" style="font-size:.82rem">
          <i class="fas fa-file-alt me-1"></i>Progress Report
        </a>
      </div>
    </div>

    <!-- Student Progress info -->
    <div class="sec-card">
      <div class="sec-card-header">
        <i class="fas fa-users me-2"></i>Student Progress
        <small class="fw-normal opacity-75 ms-1" style="font-size:.71rem">(Parents View)</small>
      </div>
      <div style="padding:14px">
        <div class="d-flex gap-3 align-items-start mb-3">
          <div style="font-size:2rem;color:#cbd5e1"><i class="fas fa-user-friends"></i></div>
          <p style="font-size:.8rem;color:var(--t2);margin:0">
            Progress is shared with parents through the Student Portal after saving the assessment.
          </p>
        </div>
        <div style="font-size:.75rem;color:var(--t3);border-top:1px solid #f1f5f9;padding-top:8px">
          <div class="mb-1"><i class="fas fa-check-circle text-success me-1"></i>Parents can view subject-wise results</div>
          <div class="mb-1"><i class="fas fa-check-circle text-success me-1"></i>See overall performance level</div>
          <div><i class="fas fa-check-circle text-success me-1"></i>Track progress over time</div>
        </div>
      </div>
    </div>

  </div><!-- /col-lg-4 -->
</div><!-- /row -->

<?php endif; ?>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle assessment section
function toggleSection(sid) {
  var target  = document.getElementById('section-' + sid);
  var tile    = document.getElementById('tile-' + sid);
  var wasOpen = target && target.style.display === 'block';
  document.querySelectorAll('.assessment-section').forEach(function(s){s.style.display='none';});
  document.querySelectorAll('.subject-tile').forEach(function(t){t.classList.remove('active-tile');});
  if (!wasOpen && target) {
    target.style.display = 'block';
    if (tile) tile.classList.add('active-tile');
    setTimeout(function(){ target.scrollIntoView({behavior:'smooth',block:'nearest'}); }, 50);
  }
}

// Rating button toggle
document.addEventListener('click', function(e) {
  var btn = e.target.closest('.rtg-btn');
  if (!btn) return;
  var grp    = btn.closest('.rtg-group');
  var hidden = grp ? grp.querySelector('input[type=hidden]') : null;
  var val    = btn.dataset.val;
  grp.querySelectorAll('.rtg-btn').forEach(function(b){b.classList.remove('sel-AD','sel-ED','sel-EMD');});
  if (hidden && hidden.value === val) {
    if (hidden) hidden.value = '';  // deselect
  } else {
    btn.classList.add('sel-' + val);
    if (hidden) hidden.value = val;
  }
});

// Add criterion
function addCriteria(sid) {
  var list  = document.getElementById('crit-list-' + sid);
  var table = document.getElementById('mtable-' + sid);
  var idx   = list.querySelectorAll('.crit-row').length;
  var label = 'Criterion ' + (idx + 1);

  // Add input row
  var row = document.createElement('div');
  row.className = 'crit-row';
  row.id = 'crow-' + sid + '-' + idx;
  row.innerHTML =
    '<input type="text" name="criteria[]" class="form-control form-control-sm crit-inp"' +
    ' value="' + label + '" placeholder="Criterion" style="width:120px;font-size:.77rem"' +
    ' data-subj="' + sid + '" onchange="syncHeaders(' + sid + ')">' +
    '<button type="button" class="btn btn-xs btn-outline-danger" style="font-size:.64rem;padding:1px 5px"' +
    ' onclick="removeCrit(' + sid + ',this)"><i class="fas fa-times"></i></button>';
  list.appendChild(row);

  // Add header column before Overall
  var thead = table.querySelector('thead tr');
  var ths   = thead.querySelectorAll('th');
  var overallTh = ths[ths.length - 2];
  var newTh = document.createElement('th');
  newTh.className = 'crit-hdr';
  newTh.dataset.subj = sid;
  newTh.textContent = label;
  thead.insertBefore(newTh, overallTh);

  // Add cell to each body row
  table.querySelectorAll('tbody tr').forEach(function(tr) {
    var tds = tr.querySelectorAll('td');
    var overallTd = tds[tds.length - 2];
    var hidden = overallTd.querySelector('input[type=hidden]');
    var sidM   = hidden ? hidden.name.match(/overall\[(\d+)\]/) : null;
    var stuId  = sidM ? sidM[1] : 0;
    var newTd  = document.createElement('td');
    newTd.innerHTML =
      '<div class="rtg-group" data-crit="' + label + '">' +
      '<input type="hidden" name="ratings[' + stuId + '][' + label + ']" value="">' +
      '<button type="button" class="rtg-btn" data-val="AD">AD</button>' +
      '<button type="button" class="rtg-btn" data-val="ED">ED</button>' +
      '<button type="button" class="rtg-btn" data-val="EMD">EMD</button>' +
      '</div>';
    tr.insertBefore(newTd, overallTd);
  });
}

// Remove criterion
function removeCrit(sid, btn) {
  var list = document.getElementById('crit-list-' + sid);
  if (list.querySelectorAll('.crit-row').length <= 1) return;
  var row = btn.closest('.crit-row');
  var idx = Array.from(list.querySelectorAll('.crit-row')).indexOf(row);
  row.remove();
  var table = document.getElementById('mtable-' + sid);
  var thead = table.querySelector('thead tr');
  var hdrs  = thead.querySelectorAll('.crit-hdr');
  if (hdrs[idx]) hdrs[idx].remove();
  table.querySelectorAll('tbody tr').forEach(function(tr) {
    var critCells = [];
    tr.querySelectorAll('td').forEach(function(td, i) {
      if (i >= 3 && i <= 3 + hdrs.length - 1) critCells.push(td);
    });
    if (critCells[idx]) critCells[idx].remove();
  });
}

// Sync table headers & hidden input names when criterion label changes
function syncHeaders(sid) {
  var list   = document.getElementById('crit-list-' + sid);
  var table  = document.getElementById('mtable-' + sid);
  var inputs = list.querySelectorAll('input.crit-inp[data-subj="' + sid + '"]');
  var hdrs   = table.querySelectorAll('thead .crit-hdr[data-subj="' + sid + '"]');
  inputs.forEach(function(inp, i) {
    var label = inp.value.trim() || ('Criterion ' + (i+1));
    if (hdrs[i]) hdrs[i].textContent = label;
    // Update hidden input names in column (idx 3+i in tbody)
    table.querySelectorAll('tbody tr').forEach(function(tr) {
      var tds = tr.querySelectorAll('td');
      var td  = tds[3 + i];
      if (!td) return;
      var hidden = td.querySelector('input[type=hidden]');
      if (!hidden) return;
      var m = hidden.name.match(/ratings\[(\d+)\]/);
      if (m) hidden.name = 'ratings[' + m[1] + '][' + label + ']';
      var grp = td.querySelector('.rtg-group');
      if (grp) grp.dataset.crit = label;
    });
  });
}

<?php if ($showSubjectId): ?>
document.addEventListener('DOMContentLoaded', function(){
  toggleSection(<?= (int)$showSubjectId ?>);
});
<?php endif; ?>
</script>
</body></html>

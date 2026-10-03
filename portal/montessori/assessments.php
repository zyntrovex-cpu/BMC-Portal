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
            "Formative assessment: class #$classId, subject #$subjectId, $date");
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

// Assessments for the selected date keyed by subject_id
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
$entriesMap       = [];
$assessedCountMap = [];

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

$portalRole = ($user['role'] === 'wing_head') ? 'wing_head' : 'montessori_teacher';
pageHead('Formative Assessment', $portalRole);
$links = ($user['role'] === 'wing_head') ? getWingHeadLinks() : getMonteTeacherLinks();
?>
<style>
/* ─── Subject Navigation Pills ─────────────────────────────────── */
.subj-nav-wrap{display:flex;flex-wrap:wrap;gap:8px}
.subj-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;font-size:.82rem;font-weight:600;cursor:pointer;border:2px solid transparent;transition:.15s;white-space:nowrap;background:#f1f5f9;color:#64748b;border-color:#e2e8f0}
.subj-pill:hover{transform:translateY(-1px);box-shadow:0 3px 10px rgba(0,0,0,.1)}
.subj-pill.active{box-shadow:0 3px 12px rgba(0,0,0,.15)}
.subj-pill .pill-check{width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.6rem;flex-shrink:0}
.subj-pill .pill-check.done{background:#16a34a;color:#fff}
.subj-pill .pill-check.none{background:#d1d5db;color:#6b7280}

/* ─── Form Section ──────────────────────────────────────────────── */
.fa-section{display:none}

/* ─── Criteria List ─────────────────────────────────────────────── */
.criteria-list{display:flex;flex-direction:column;gap:6px;margin-top:4px}
.crit-item{display:flex;align-items:center;gap:8px;padding:5px 8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px}
.crit-drag-icon{color:#cbd5e1;font-size:.72rem;cursor:grab;flex-shrink:0}
.crit-item input{flex:1;font-size:.82rem;min-width:0}
.btn-crit-remove{background:none;border:1px solid #fca5a5;border-radius:4px;color:#ef4444;padding:2px 7px;font-size:.68rem;cursor:pointer;flex-shrink:0;transition:.1s}
.btn-crit-remove:hover{background:#fee2e2}

/* ─── Assessment Table ──────────────────────────────────────────── */
.fa-table{width:100%;border-collapse:collapse;font-size:.82rem}
.fa-table thead tr{background:#f8fafc}
.fa-table th{padding:9px 10px;font-size:.74rem;font-weight:700;text-transform:uppercase;letter-spacing:.3px;border-bottom:2px solid #e2e8f0;white-space:nowrap;color:#374151}
.fa-table th.col-name,.fa-table th.col-sr{text-align:left}
.fa-table th.col-overall,.fa-table th.crit-hdr,.fa-table th.col-remarks{text-align:center}
.fa-table tbody tr{border-bottom:1px solid #f1f5f9}
.fa-table tbody tr:hover{background:#fafbfc}
.fa-table td{padding:8px 10px;vertical-align:middle}
.fa-table td.col-sr{color:#94a3b8;font-size:.74rem;text-align:left;width:32px}
.fa-table td.col-name{text-align:left}
.stu-name{font-weight:600;font-size:.84rem;color:#1e293b;display:block}
.stu-roll{font-size:.7rem;color:#94a3b8;display:block}
.fa-table td.col-overall,.fa-table td.crit-col{text-align:center}
.fa-table td.col-remarks{text-align:center;min-width:100px}
.fa-remark-inp{font-size:.76rem!important;min-width:90px}

/* ─── Rating Buttons ────────────────────────────────────────────── */
.rtg-group{display:inline-flex;gap:3px;align-items:center}
.rtg-btn{padding:3px 9px;border:1.5px solid #d1d5db;border-radius:5px;cursor:pointer;font-size:.72rem;font-weight:700;background:#f9fafb;transition:.12s;white-space:nowrap;line-height:1.5}
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

/* ─── Summary Sidebar ───────────────────────────────────────────── */
.summary-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;cursor:pointer;transition:.1s;border-radius:4px}
.summary-row:last-child{border-bottom:none}
.summary-row:hover{background:#f8fafc;margin:0 -8px;padding:8px 8px}
.subj-icon-sm{width:26px;height:26px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.72rem;flex-shrink:0}
.summary-subj-name{font-size:.82rem;font-weight:600;color:#374151}
.summary-count{font-size:.73rem;font-weight:600}
.summary-count.done{color:#16a34a}
.summary-count.partial{color:#d97706}
.summary-count.none{color:#9ca3af}
.summary-dot{width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.58rem;flex-shrink:0}
.summary-dot.done{background:#16a34a;color:#fff}
.summary-dot.partial{background:#d97706;color:#fff}
.summary-dot.none{background:#e5e7eb;color:#9ca3af}

/* ─── Context bar date display ──────────────────────────────────── */
.ctx-info-chip{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:12px;padding:3px 12px;font-size:.76rem;font-weight:600}
.ctx-assessed-chip{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;border-radius:12px;padding:3px 12px;font-size:.76rem;font-weight:600}
</style>

<div class="portal-wrap">
<?php sidebar($portalRole,'monte-assessments',$links,$user); ?>
<div class="main-area">
<?php topbar('Formative Assessment',$user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- ── Context Selector ─────────────────────────────────────────── -->
<div class="sec-card mb-3" style="padding:14px 18px">
  <form method="GET" id="contextForm">
    <div class="d-flex align-items-end flex-wrap gap-3">
      <div>
        <label class="form-label fw-semibold mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.4px;color:var(--t3)">Class</label>
        <?php if (empty($assignedClasses)): ?>
          <select class="form-select form-select-sm" disabled style="min-width:160px"><option>No classes assigned</option></select>
        <?php else: ?>
          <select name="class_id" class="form-select form-select-sm" style="min-width:160px"
                  onchange="document.getElementById('contextForm').submit()">
            <?php foreach ($assignedClasses as $cl): ?>
            <option value="<?= $cl['id'] ?>" <?= (int)$cl['id']===$selClassId?'selected':'' ?>><?= h($cl['name']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </div>
      <div>
        <label class="form-label fw-semibold mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.4px;color:var(--t3)">Date</label>
        <input type="date" name="date" class="form-control form-control-sm" value="<?= h($selDate) ?>"
               onchange="document.getElementById('contextForm').submit()" style="width:155px">
      </div>
      <button type="submit" class="btn btn-primary btn-sm" style="font-size:.8rem">
        <i class="fas fa-sync-alt me-1"></i>Refresh
      </button>
      <?php if ($selClassId && !empty($subjects)): ?>
      <div class="d-flex gap-2 align-items-center flex-wrap ms-auto">
        <span class="ctx-info-chip">
          <i class="fas fa-calendar-day me-1"></i><?= date('d M Y', strtotime($selDate)) ?>
        </span>
        <?php
          $assessedToday = count(array_filter($subjects, fn($s)=>isset($assessmentsToday[(int)$s['id']])));
          $totalSubj     = count($subjects);
        ?>
        <span class="<?= $assessedToday===$totalSubj ? 'ctx-assessed-chip' : 'ctx-info-chip' ?>">
          <i class="fas fa-clipboard-check me-1"></i><?= $assessedToday ?>/<?= $totalSubj ?> subjects assessed
        </span>
      </div>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php if (!$selClassId || empty($subjects)): ?>
<div class="sec-card">
  <div class="text-center py-5" style="color:var(--t2)">
    <i class="fas fa-clipboard-check fa-2x mb-3" style="opacity:.25"></i>
    <p class="mb-0 fw-semibold" style="font-size:.9rem">
      <?= !$selClassId ? 'No Montessori classes assigned.' : 'No subjects are assigned for this class.' ?>
    </p>
    <p class="text-muted mb-0" style="font-size:.8rem;margin-top:4px">
      <?= !$selClassId ? 'Contact the administrator to assign Montessori classes.' : 'Ask the administrator to configure subject assignments.' ?>
    </p>
  </div>
</div>

<?php else: ?>

<div class="row g-3">

  <!-- ── LEFT: Subject Nav + Forms ──────────────────────────────── -->
  <div class="col-xl-8 col-lg-7">

    <!-- Subject Selector -->
    <div class="sec-card mb-3" style="padding:14px 18px">
      <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--t3);margin-bottom:10px">
        <i class="fas fa-book-open me-1"></i>Select a Subject to Assess
      </div>
      <div class="subj-nav-wrap">
        <?php foreach ($subjects as $subj):
          $sid  = (int)$subj['id'];
          $meta = monteSubjectMeta($subj['name']);
          $done = isset($assessmentsToday[$sid]);
        ?>
        <button type="button"
                class="subj-pill <?= $showSubjectId===$sid||(!$showSubjectId&&(int)$subjects[0]['id']===$sid)?'':''; ?>"
                id="pill-<?= $sid ?>"
                onclick="activateSubject(<?= $sid ?>)"
                style="background:<?= $done?$meta['bg']:'#f1f5f9' ?>;color:<?= $done?$meta['ic']:'#64748b' ?>;border-color:<?= $done?$meta['border']:'#e2e8f0' ?>">
          <i class="fas <?= $meta['icon'] ?>"></i>
          <?= h($subj['name']) ?>
          <span class="pill-check <?= $done?'done':'none' ?>">
            <?= $done ? '✓' : '○' ?>
          </span>
        </button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Prompt when no subject active -->
    <div id="noSubjectMsg" class="sec-card mb-3">
      <div class="text-center py-4" style="color:var(--t2)">
        <i class="fas fa-hand-point-up fa-lg mb-2" style="opacity:.25"></i>
        <p class="mb-0" style="font-size:.85rem;font-weight:600">Select a subject above</p>
        <p class="mb-0 text-muted" style="font-size:.78rem">The assessment form for that subject will appear here.</p>
      </div>
    </div>

    <!-- Per-Subject Assessment Forms ───────────────────────────── -->
    <?php foreach ($subjects as $subj):
      $sid        = (int)$subj['id'];
      $meta       = monteSubjectMeta($subj['name']);
      $assessed   = isset($assessmentsToday[$sid]);
      $assessment = $assessed ? $assessmentsToday[$sid] : null;
      $criteria   = $assessment ? $assessment['criteria_arr'] : monteDefaultCriteria($subj['name']);
      $entries    = $assessment ? ($entriesMap[$assessment['id']] ?? []) : [];
      $aId        = $assessment ? $assessment['id'] : 0;
    ?>
    <div class="fa-section" id="fa-section-<?= $sid ?>">

      <!-- Section Header Card -->
      <div class="sec-card mb-3" style="overflow:hidden">
        <div style="background:linear-gradient(90deg,<?= $meta['ic'] ?>,<?= $meta['ic'] ?>cc);color:#fff;padding:12px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
          <div class="d-flex align-items-center gap-3">
            <div style="width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0">
              <i class="fas <?= $meta['icon'] ?>"></i>
            </div>
            <div>
              <div style="font-weight:700;font-size:.96rem;line-height:1.2"><?= h($subj['name']) ?> — Formative Assessment</div>
              <div style="font-size:.74rem;opacity:.88;margin-top:2px">
                <?= date('l, d M Y', strtotime($selDate)) ?> &nbsp;&middot;&nbsp; <?= h($classInfo['name'] ?? '') ?>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <?php if ($assessed): ?>
            <span style="background:rgba(255,255,255,.22);border:1px solid rgba(255,255,255,.35);color:#fff;border-radius:12px;padding:3px 11px;font-size:.72rem;font-weight:600">
              <i class="fas fa-check me-1"></i>Saved
            </span>
            <?php endif; ?>
            <?php if ($assessed && $teacher): ?>
            <form method="POST" class="d-inline m-0" onsubmit="return confirm('Delete this assessment? All student ratings for this subject on this date will be permanently removed.')">
              <input type="hidden" name="action" value="delete_assessment">
              <input type="hidden" name="assessment_id" value="<?= $aId ?>">
              <input type="hidden" name="class_id" value="<?= $selClassId ?>">
              <input type="hidden" name="date" value="<?= h($selDate) ?>">
              <button type="submit" style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:5px;padding:4px 10px;font-size:.72rem;cursor:pointer">
                <i class="fas fa-trash-alt me-1"></i>Delete Assessment
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Assessment Form -->
      <form method="POST" id="form-<?= $sid ?>">
        <input type="hidden" name="action" value="save_assessment">
        <input type="hidden" name="class_id" value="<?= $selClassId ?>">
        <input type="hidden" name="subject_id" value="<?= $sid ?>">
        <input type="hidden" name="assessment_date" value="<?= h($selDate) ?>">

        <!-- Assessment Info: Topic + Criteria -->
        <div class="sec-card mb-3">
          <div class="sec-card-header">
            <i class="fas fa-info-circle me-2"></i>Assessment Details
          </div>
          <div style="padding:18px">
            <div class="row g-4">
              <div class="col-md-5">
                <label class="form-label fw-semibold" style="font-size:.8rem">
                  Topic / Activity <span class="text-muted fw-normal">(optional)</span>
                </label>
                <input type="text" name="topic" class="form-control form-control-sm"
                       placeholder="e.g. Counting, Letter Sounds, Shapes…"
                       value="<?= h($assessment ? ($assessment['topic'] ?? '') : '') ?>">
                <div class="form-text" style="font-size:.73rem">Describe the skill or activity covered today.</div>
              </div>
              <div class="col-md-7">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <label class="form-label fw-semibold mb-0" style="font-size:.8rem">
                    Assessment Criteria
                    <span class="text-muted fw-normal" style="font-size:.73rem">(each becomes a rating column)</span>
                  </label>
                  <?php if ($teacher): ?>
                  <button type="button" onclick="addCrit(<?= $sid ?>)"
                          class="btn btn-sm btn-outline-primary" style="font-size:.72rem;padding:3px 10px">
                    <i class="fas fa-plus me-1"></i>Add Criterion
                  </button>
                  <?php endif; ?>
                </div>
                <div id="crit-list-<?= $sid ?>" class="criteria-list">
                  <?php foreach ($criteria as $i => $crit): ?>
                  <div class="crit-item" id="critItem-<?= $sid ?>-<?= $i ?>">
                    <i class="fas fa-grip-vertical crit-drag-icon"></i>
                    <input type="text" name="criteria[]" class="form-control form-control-sm crit-inp"
                           value="<?= h($crit) ?>" placeholder="Criterion name"
                           data-subj="<?= $sid ?>" onchange="syncHeaders(<?= $sid ?>)"
                           <?= $teacher ? '' : 'readonly' ?>>
                    <?php if ($teacher): ?>
                    <button type="button" class="btn-crit-remove" onclick="removeCrit(<?= $sid ?>,this)" title="Remove this criterion">
                      <i class="fas fa-times"></i>
                    </button>
                    <?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Student Ratings Table -->
        <div class="sec-card mb-3">
          <div class="sec-card-header d-flex align-items-center justify-content-between">
            <span><i class="fas fa-users me-2"></i>Student Performance Ratings</span>
            <span class="badge bg-secondary" style="font-size:.71rem"><?= $totalStudents ?> students</span>
          </div>

          <?php if (empty($students)): ?>
          <div class="text-center py-4 text-muted" style="font-size:.85rem">
            <i class="fas fa-user-slash mb-2" style="opacity:.3"></i>
            <p class="mb-0">No students found in this class.</p>
          </div>
          <?php else: ?>

          <div class="table-responsive">
            <table class="fa-table" id="fa-table-<?= $sid ?>">
              <thead>
                <tr>
                  <th class="col-sr">#</th>
                  <th class="col-name">Student</th>
                  <?php foreach ($criteria as $crit): ?>
                  <th class="crit-hdr" data-subj="<?= $sid ?>"><?= h($crit) ?></th>
                  <?php endforeach; ?>
                  <th class="col-overall">Overall</th>
                  <th class="col-remarks">Remarks</th>
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
                  <td class="col-sr"><?= $idx + 1 ?></td>
                  <td class="col-name">
                    <span class="stu-name"><?= h($stu['name']) ?></span>
                    <?php $rno = $stu['roll_no'] ?: ($stu['roll_no_login'] ?? ''); if ($rno): ?>
                    <span class="stu-roll">Roll: <?= h($rno) ?></span>
                    <?php endif; ?>
                  </td>
                  <?php foreach ($criteria as $crit): ?>
                  <td class="crit-col">
                    <div class="rtg-group" data-crit="<?= h($crit) ?>">
                      <input type="hidden" name="ratings[<?= $stu['id'] ?>][<?= h($crit) ?>]"
                             value="<?= h($ratings[$crit] ?? '') ?>">
                      <?php foreach (['AD','ED','EMD'] as $rv): ?>
                      <button type="button" class="rtg-btn <?= ($ratings[$crit]??'')===$rv?'sel-'.$rv:'' ?>"
                              data-val="<?= $rv ?>"
                              <?= $teacher ? '' : 'disabled' ?>><?= $rv ?></button>
                      <?php endforeach; ?>
                    </div>
                  </td>
                  <?php endforeach; ?>
                  <td class="col-overall">
                    <div class="rtg-group">
                      <input type="hidden" name="overall[<?= $stu['id'] ?>]" value="<?= h($overall) ?>">
                      <?php foreach (['AD','ED','EMD'] as $rv): ?>
                      <button type="button" class="rtg-btn <?= $overall===$rv?'sel-'.$rv:'' ?>"
                              data-val="<?= $rv ?>"
                              <?= $teacher ? '' : 'disabled' ?>><?= $rv ?></button>
                      <?php endforeach; ?>
                    </div>
                  </td>
                  <td class="col-remarks">
                    <input type="text" name="remarks[<?= $stu['id'] ?>]"
                           class="form-control form-control-sm fa-remark-inp"
                           value="<?= h($remark) ?>"
                           placeholder="Optional note…"
                           <?= $teacher ? '' : 'readonly' ?>>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
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
              <i class="fas fa-save me-1"></i><?= $assessed ? 'Update Assessment' : 'Save Assessment' ?>
            </button>
            <?php else: ?>
            <span class="text-muted" style="font-size:.76rem"><i class="fas fa-lock me-1"></i>View only</span>
            <?php endif; ?>
          </div>
          <?php endif; ?>

        </div><!-- /.sec-card students -->
      </form>

    </div><!-- /.fa-section -->
    <?php endforeach; ?>

  </div><!-- /col-xl-8 -->

  <!-- ── RIGHT SIDEBAR ──────────────────────────────────────────── -->
  <div class="col-xl-4 col-lg-5">

    <!-- Today's Progress Summary -->
    <div class="sec-card mb-3">
      <div class="sec-card-header">
        <i class="fas fa-chart-pie me-2"></i>Today's Progress
      </div>
      <div style="padding:10px 14px">
        <?php
          $completedToday = 0;
          foreach ($subjects as $subj):
            $sid  = (int)$subj['id'];
            $meta = monteSubjectMeta($subj['name']);
            $done = isset($assessmentsToday[$sid]);
            $cnt  = $assessedCountMap[$sid] ?? 0;
            if ($done) $completedToday++;
            if ($done) $dotClass = ($cnt>=$totalStudents?'done':'partial');
            else $dotClass = 'none';
        ?>
        <div class="summary-row" onclick="activateSubject(<?= $sid ?>)" title="Open <?= h($subj['name']) ?>">
          <div class="d-flex align-items-center gap-2">
            <div class="subj-icon-sm" style="background:<?= $meta['bg'] ?>;color:<?= $meta['ic'] ?>">
              <i class="fas <?= $meta['icon'] ?>"></i>
            </div>
            <span class="summary-subj-name"><?= h($subj['name']) ?></span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="summary-count <?= $dotClass ?>">
              <?= $done ? "$cnt / $totalStudents" : 'Not assessed' ?>
            </span>
            <div class="summary-dot <?= $dotClass ?>">
              <i class="fas <?= $done?($cnt>=$totalStudents?'fa-check':'fa-adjust'):'fa-minus' ?>"></i>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <div style="font-size:.73rem;color:var(--t3);padding-top:8px;border-top:1px solid #f1f5f9;margin-top:2px">
          <i class="fas fa-info-circle me-1"></i>
          <?= $completedToday ?> of <?= count($subjects) ?> subjects assessed today
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-bolt me-2"></i>Quick Actions</div>
      <div style="padding:12px;display:flex;flex-direction:column;gap:8px">
        <a href="/portal/montessori/assessment-history.php?class_id=<?= $selClassId ?>"
           class="btn btn-outline-secondary w-100" style="font-size:.82rem;text-align:left">
          <i class="fas fa-history me-2"></i>Assessment History
        </a>
        <a href="/portal/progress-report/form.php"
           class="btn btn-outline-secondary w-100" style="font-size:.82rem;text-align:left">
          <i class="fas fa-file-alt me-2"></i>Progress Report
        </a>
      </div>
    </div>

    <!-- About card -->
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-clipboard-check me-2"></i>About Formative Assessment</div>
      <div style="padding:14px">
        <p style="font-size:.79rem;color:var(--t2);margin-bottom:10px">
          Formative assessments track daily student progress in each subject using three performance levels.
        </p>
        <div style="font-size:.75rem;color:var(--t3)">
          <div style="margin-bottom:6px"><i class="fas fa-check-circle text-success me-1"></i>Results are visible to students immediately after saving</div>
          <div style="margin-bottom:6px"><i class="fas fa-check-circle text-success me-1"></i>Parents can track subject-wise progress over time</div>
          <div><i class="fas fa-check-circle text-success me-1"></i>Full history is available for review at any time</div>
        </div>
      </div>
    </div>

  </div><!-- /col-xl-4 -->
</div><!-- /row -->

<?php endif; ?>
</div></div></div>

<script>
// ── Subject Activation ────────────────────────────────────────────
var _activeSubj = 0;

function activateSubject(sid) {
  // Hide all sections
  document.querySelectorAll('.fa-section').forEach(function(el) { el.style.display = 'none'; });
  // Reset all pills
  document.querySelectorAll('.subj-pill').forEach(function(el) { el.classList.remove('active'); });
  // Hide the "no subject" prompt
  var noMsg = document.getElementById('noSubjectMsg');
  if (noMsg) noMsg.style.display = 'none';

  var section = document.getElementById('fa-section-' + sid);
  var pill    = document.getElementById('pill-' + sid);

  if (_activeSubj === sid) {
    // Toggle off: show prompt
    _activeSubj = 0;
    if (noMsg) noMsg.style.display = '';
    return;
  }

  _activeSubj = sid;
  if (section) {
    section.style.display = '';
    setTimeout(function() {
      section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 40);
  }
  if (pill) {
    pill.classList.add('active');
    // Apply active border
    pill.style.boxShadow = '0 0 0 3px rgba(30,64,175,.25)';
  }
}

// ── Rating Button Toggle ──────────────────────────────────────────
document.addEventListener('click', function(e) {
  var btn = e.target.closest('.rtg-btn');
  if (!btn || btn.disabled) return;
  var grp    = btn.closest('.rtg-group');
  var hidden = grp ? grp.querySelector('input[type=hidden]') : null;
  var val    = btn.dataset.val;
  var cur    = hidden ? hidden.value : '';
  // Deselect all in group
  grp.querySelectorAll('.rtg-btn').forEach(function(b) {
    b.classList.remove('sel-AD','sel-ED','sel-EMD');
  });
  if (cur === val) {
    // Toggle off (click same again)
    if (hidden) hidden.value = '';
  } else {
    btn.classList.add('sel-' + val);
    if (hidden) hidden.value = val;
  }
});

// ── Add Criterion ─────────────────────────────────────────────────
function addCrit(sid) {
  var list  = document.getElementById('crit-list-' + sid);
  var table = document.getElementById('fa-table-' + sid);
  var idx   = list.querySelectorAll('.crit-item').length;
  var label = 'Criterion ' + (idx + 1);

  // Add to criteria list
  var item = document.createElement('div');
  item.className = 'crit-item';
  item.id = 'critItem-' + sid + '-' + idx;
  item.innerHTML =
    '<i class="fas fa-grip-vertical crit-drag-icon"></i>' +
    '<input type="text" name="criteria[]" class="form-control form-control-sm crit-inp"' +
    ' value="' + label + '" placeholder="Criterion name" data-subj="' + sid + '"' +
    ' onchange="syncHeaders(' + sid + ')">' +
    '<button type="button" class="btn-crit-remove" onclick="removeCrit(' + sid + ',this)" title="Remove">' +
    '<i class="fas fa-times"></i></button>';
  list.appendChild(item);

  // Add column to table header (before Overall column)
  var thead  = table.querySelector('thead tr');
  var ths    = thead.querySelectorAll('th');
  var lastTh = ths[ths.length - 1]; // Remarks
  var overTh = ths[ths.length - 2]; // Overall
  var newTh  = document.createElement('th');
  newTh.className = 'crit-hdr';
  newTh.dataset.subj = sid;
  newTh.textContent  = label;
  thead.insertBefore(newTh, overTh);

  // Add cell to each body row (before Overall cell)
  table.querySelectorAll('tbody tr').forEach(function(tr) {
    var tds     = tr.querySelectorAll('td');
    var overTd  = tds[tds.length - 2]; // Overall
    var hiddenO = overTd.querySelector('input[type=hidden]');
    var stuMatch = hiddenO ? hiddenO.name.match(/overall\[(\d+)\]/) : null;
    var stuId   = stuMatch ? stuMatch[1] : 0;
    var newTd   = document.createElement('td');
    newTd.className = 'crit-col';
    newTd.innerHTML =
      '<div class="rtg-group" data-crit="' + label + '">' +
      '<input type="hidden" name="ratings[' + stuId + '][' + label + ']" value="">' +
      '<button type="button" class="rtg-btn" data-val="AD">AD</button>' +
      '<button type="button" class="rtg-btn" data-val="ED">ED</button>' +
      '<button type="button" class="rtg-btn" data-val="EMD">EMD</button>' +
      '</div>';
    tr.insertBefore(newTd, overTd);
  });
}

// ── Remove Criterion ──────────────────────────────────────────────
function removeCrit(sid, btn) {
  var list = document.getElementById('crit-list-' + sid);
  if (list.querySelectorAll('.crit-item').length <= 1) {
    alert('At least one criterion is required.');
    return;
  }
  var item  = btn.closest('.crit-item');
  var items = Array.from(list.querySelectorAll('.crit-item'));
  var idx   = items.indexOf(item);
  item.remove();

  var table = document.getElementById('fa-table-' + sid);
  var hdrs  = table.querySelectorAll('thead .crit-hdr');
  if (hdrs[idx]) hdrs[idx].remove();

  table.querySelectorAll('tbody tr').forEach(function(tr) {
    var critTds = [];
    tr.querySelectorAll('td').forEach(function(td, i) {
      if (i >= 2 && i <= 2 + hdrs.length) critTds.push(td);
    });
    if (critTds[idx]) critTds[idx].remove();
  });
}

// ── Sync Headers when Criterion Name Changes ──────────────────────
function syncHeaders(sid) {
  var list  = document.getElementById('crit-list-' + sid);
  var table = document.getElementById('fa-table-' + sid);
  var inps  = list.querySelectorAll('input.crit-inp[data-subj="' + sid + '"]');
  var hdrs  = table.querySelectorAll('thead .crit-hdr[data-subj="' + sid + '"]');
  inps.forEach(function(inp, i) {
    var label = inp.value.trim() || ('Criterion ' + (i + 1));
    if (hdrs[i]) hdrs[i].textContent = label;
    // Rename hidden input fields in that column
    table.querySelectorAll('tbody tr').forEach(function(tr) {
      var tds = tr.querySelectorAll('td');
      var td  = tds[2 + i];
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

// ── Auto-open subject on page load ───────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  <?php if ($showSubjectId): ?>
  activateSubject(<?= (int)$showSubjectId ?>);
  <?php elseif (!empty($subjects)): ?>
  // Auto-open first subject
  activateSubject(<?= (int)$subjects[0]['id'] ?>);
  <?php endif; ?>
});
</script>
<?php pageFooter(); ?>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/db.php';

$user    = requireAuth('montessori_teacher', 'wing_head', 'vp_montessori');
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);

$FOCUS_OPTIONS = [
    'General Observation','English','Mathematics','Science','Urdu',
    'Islamic Studies','Art & Craft','General Knowledge','Physical Education',
    'Classroom Behaviour','Social Skills','Other',
];

// ── POST handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action']    ?? '';
    $classId   = (int)($_POST['class_id']   ?? 0);
    $studentId = (int)($_POST['student_id'] ?? 0);
    $recordId  = (int)($_POST['record_id']  ?? 0);

    if ($action === 'save_anecdotal' && $classId && $studentId) {
        // Verify teacher is assigned to this montessori class
        $authOk = false;
        if ($teacher) {
            $authSt = $db->prepare(
                'SELECT 1 FROM class_subjects cs JOIN classes c ON cs.class_id=c.id
                 WHERE cs.teacher_id=? AND cs.class_id=? AND c.is_montessori=1 LIMIT 1'
            );
            $authSt->execute([$teacher['id'], $classId]);
            $authOk = (bool)$authSt->fetchColumn();
        } else {
            // wing_head — verify class is montessori
            $authSt = $db->prepare('SELECT 1 FROM classes WHERE id=? AND is_montessori=1 LIMIT 1');
            $authSt->execute([$classId]);
            $authOk = (bool)$authSt->fetchColumn();
        }

        // Verify student belongs to class
        if ($authOk) {
            $stuSt = $db->prepare('SELECT 1 FROM students WHERE id=? AND class_id=? AND deleted_at IS NULL LIMIT 1');
            $stuSt->execute([$studentId, $classId]);
            $authOk = (bool)$stuSt->fetchColumn();
        }

        if ($authOk) {
            $subjectFocus = substr(trim($_POST['subject_focus'] ?? 'General Observation'), 0, 200);
            $recordDate   = $_POST['record_date'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $recordDate)) $recordDate = date('Y-m-d');
            $topic       = substr(trim($_POST['topic']       ?? ''), 0, 300) ?: null;
            $observation = substr(trim($_POST['observation'] ?? ''), 0, 5000);
            if (!$observation) { setFlash('danger', 'Observation cannot be empty.'); redirect("/portal/montessori/anecdotal-records.php?class_id=$classId&student_id=$studentId"); }

            $teacherId = $teacher ? $teacher['id'] : (function() use ($db,$user) {
                $t = $db->prepare('SELECT id FROM teachers WHERE user_id=? LIMIT 1');
                $t->execute([$user['id']]); $r = $t->fetch(); return $r ? $r['id'] : 1;
            })();

            if ($recordId) {
                // Update existing — verify ownership or wing_head
                $ownSt = $db->prepare('SELECT teacher_id FROM montessori_anecdotal_records WHERE id=?');
                $ownSt->execute([$recordId]);
                $own = $ownSt->fetch();
                if ($own && (in_array($user['role'],['wing_head','vp_montessori']) || (int)$own['teacher_id']===$teacherId)) {
                    $db->prepare(
                        'UPDATE montessori_anecdotal_records
                         SET subject_focus=?,record_date=?,topic=?,observation=?,updated_at=NOW()
                         WHERE id=?'
                    )->execute([$subjectFocus,$recordDate,$topic,$observation,$recordId]);
                    setFlash('success', 'Anecdotal record updated.');
                } else {
                    setFlash('danger', 'Not authorised to edit this record.');
                }
            } else {
                $db->prepare(
                    'INSERT INTO montessori_anecdotal_records
                     (class_id,student_id,teacher_id,subject_focus,record_date,topic,observation)
                     VALUES (?,?,?,?,?,?,?)'
                )->execute([$classId,$studentId,$teacherId,$subjectFocus,$recordDate,$topic,$observation]);
                setFlash('success', 'Anecdotal record saved.');
            }
        } else {
            setFlash('danger', 'Not authorised for this class or student.');
        }
        redirect("/portal/montessori/anecdotal-records.php?class_id=$classId&student_id=$studentId");
    }

    if ($action === 'delete_anecdotal' && $recordId) {
        $ownSt = $db->prepare('SELECT teacher_id,class_id,student_id FROM montessori_anecdotal_records WHERE id=?');
        $ownSt->execute([$recordId]);
        $own = $ownSt->fetch();
        if ($own) {
            $tid = $teacher ? $teacher['id'] : 0;
            if (in_array($user['role'],['wing_head','vp_montessori']) || (int)$own['teacher_id']===$tid) {
                $db->prepare('DELETE FROM montessori_anecdotal_records WHERE id=?')->execute([$recordId]);
                setFlash('success', 'Record deleted.');
            } else {
                setFlash('danger', 'Not authorised.');
            }
            redirect("/portal/montessori/anecdotal-records.php?class_id={$own['class_id']}&student_id={$own['student_id']}");
        }
        redirect('/portal/montessori/anecdotal-records.php');
    }
}

// ── GET: load state ──────────────────────────────────────────────────────────
$selClassId   = (int)($_GET['class_id']   ?? $_SESSION['mar_cls'] ?? 0);
$selStudentId = (int)($_GET['student_id'] ?? $_SESSION['mar_stu'] ?? 0);
$editRecordId = (int)($_GET['edit']       ?? 0);

// Teacher's montessori classes
if ($teacher) {
    $cSt = $db->prepare(
        'SELECT DISTINCT c.id,c.name,c.grade,c.section FROM class_subjects cs
         JOIN classes c ON cs.class_id=c.id
         WHERE cs.teacher_id=? AND c.is_montessori=1 ORDER BY c.grade,c.section'
    );
    $cSt->execute([$teacher['id']]);
} else {
    $cSt = $db->prepare('SELECT id,name,grade,section FROM classes WHERE is_montessori=1 ORDER BY grade,section');
    $cSt->execute([]);
}
$assignedClasses = $cSt->fetchAll();

if (!$selClassId && !empty($assignedClasses)) $selClassId = (int)$assignedClasses[0]['id'];
if ($selClassId) $_SESSION['mar_cls'] = $selClassId;
if ($selStudentId) $_SESSION['mar_stu'] = $selStudentId;

// Class info
$classInfo = null;
if ($selClassId) {
    $ci = $db->prepare('SELECT name,grade,section FROM classes WHERE id=?');
    $ci->execute([$selClassId]); $classInfo = $ci->fetch();
}

// Students in selected class
$classStudents = [];
if ($selClassId) {
    $sSt = $db->prepare(
        'SELECT st.id,u.name,st.roll_no FROM students st
         JOIN users u ON st.user_id=u.id
         WHERE st.class_id=? AND st.deleted_at IS NULL ORDER BY u.name'
    );
    $sSt->execute([$selClassId]);
    $classStudents = $sSt->fetchAll();
}

// Selected student info
$studentInfo = null;
if ($selStudentId && $selClassId) {
    $siSt = $db->prepare(
        'SELECT st.id,u.name,st.roll_no FROM students st
         JOIN users u ON st.user_id=u.id
         WHERE st.id=? AND st.class_id=? AND st.deleted_at IS NULL LIMIT 1'
    );
    $siSt->execute([$selStudentId,$selClassId]);
    $studentInfo = $siSt->fetch();
    if (!$studentInfo) { $selStudentId = 0; unset($_SESSION['mar_stu']); }
}

// Count today's records per student (for student picker badges)
$todayCountMap = [];
if ($selClassId) {
    try {
        $tcSt = $db->prepare(
            'SELECT student_id,COUNT(*) AS cnt FROM montessori_anecdotal_records
             WHERE class_id=? AND record_date=CURDATE() GROUP BY student_id'
        );
        $tcSt->execute([$selClassId]);
        foreach ($tcSt->fetchAll() as $r) $todayCountMap[(int)$r['student_id']] = (int)$r['cnt'];
    } catch (Exception $e) {}
}

// Recent records for selected student (last 10, for history sidebar)
$recentRecords = [];
if ($selStudentId) {
    try {
        $rrSt = $db->prepare(
            'SELECT mar.id,mar.subject_focus,mar.record_date,mar.topic,
                    LEFT(mar.observation,120) AS obs_preview
             FROM montessori_anecdotal_records mar
             WHERE mar.student_id=? ORDER BY mar.record_date DESC,mar.id DESC LIMIT 10'
        );
        $rrSt->execute([$selStudentId]);
        $recentRecords = $rrSt->fetchAll();
    } catch (Exception $e) {}
}

// Load record for editing
$editRecord = null;
if ($editRecordId && $selStudentId) {
    try {
        $erSt = $db->prepare(
            'SELECT * FROM montessori_anecdotal_records WHERE id=? AND student_id=? LIMIT 1'
        );
        $erSt->execute([$editRecordId, $selStudentId]);
        $editRecord = $erSt->fetch();
    } catch (Exception $e) {}
}

$portalRole = in_array($user['role'], ['wing_head','vp_montessori']) ? $user['role'] : 'montessori_teacher';
pageHead('Anecdotal Records', $portalRole);
$links = $user['role'] === 'vp_montessori' ? getVpMontessoriLinks() : ($user['role'] === 'wing_head' ? getWingHeadLinks() : getMonteTeacherLinks());
?>
<style>
/* Student picker grid */
.stu-pick-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding:14px 16px}
@media(max-width:900px){.stu-pick-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:480px){.stu-pick-grid{grid-template-columns:1fr}}
.stu-pick-card{display:flex;flex-direction:column;align-items:center;gap:4px;padding:10px 8px;border:2px solid #e2e8f0;border-radius:10px;background:#fff;text-decoration:none;color:#374151;transition:.15s;text-align:center;cursor:pointer}
.stu-pick-card:hover{border-color:#93c5fd;background:#f0f9ff;color:#1d4ed8}
.stu-pick-card.selected{border-color:#2563eb;background:#eff6ff;color:#1d4ed8;box-shadow:0 0 0 3px rgba(37,99,235,.15)}
.stu-pick-avatar{width:36px;height:36px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:700;color:#64748b;flex-shrink:0}
.stu-pick-avatar.sel{background:#dbeafe;color:#1d4ed8}
.stu-pick-name{font-size:.76rem;font-weight:600;line-height:1.2}
.stu-pick-badge{font-size:.65rem;padding:1px 7px;border-radius:10px;font-weight:600}
.stu-pick-badge.has-rec{background:#dcfce7;color:#166534}
.stu-pick-badge.no-rec{background:#f1f5f9;color:#9ca3af}
/* Form */
.ar-form-card{background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden}
.ar-form-header{background:linear-gradient(135deg,#1e3a5f,#2e6da4);color:#fff;padding:16px 20px}
.ar-form-body{padding:20px}
.ar-label{font-size:.78rem;font-weight:600;color:#374151;margin-bottom:4px;display:block}
.ar-input,.ar-textarea,.ar-select{width:100%;border:1px solid #d1d5db;border-radius:7px;padding:8px 11px;font-size:.84rem;color:#1e293b;background:#fff;transition:.15s;font-family:inherit}
.ar-input:focus,.ar-textarea:focus,.ar-select:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1)}
.ar-textarea{resize:vertical;min-height:120px;line-height:1.5}
.ar-field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
@media(max-width:600px){.ar-field-row{grid-template-columns:1fr}}
/* History list */
.rec-item{border:1px solid #e2e8f0;border-radius:8px;background:#fff;padding:10px 13px;margin-bottom:8px;transition:.15s}
.rec-item:hover{border-color:#93c5fd;background:#f8fafc}
.rec-item-date{font-size:.72rem;font-weight:700;color:#1e3a5f}
.rec-item-focus{font-size:.69rem;background:#e0f2fe;color:#0369a1;padding:1px 8px;border-radius:10px;font-weight:600}
.rec-item-topic{font-size:.75rem;color:#374151;margin-top:3px;font-weight:600}
.rec-item-obs{font-size:.73rem;color:#64748b;margin-top:2px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
</style>

<div class="portal-wrap">
<?php sidebar($portalRole,'anecdotal-records',$links,$user); ?>
<div class="main-area">
<?php topbar('Anecdotal Records',$user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Breadcrumb / context bar -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <nav style="font-size:.8rem;color:#64748b;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
    <i class="fas fa-sticky-note text-primary"></i>
    <?php if ($classInfo): ?>
    <a href="?class_id=<?= $selClassId ?>" style="color:#1d4ed8;text-decoration:none;font-weight:600"><?= h($classInfo['name']) ?></a>
    <?php if ($selStudentId && $studentInfo): ?>
    <span style="color:#cbd5e1">›</span>
    <span style="color:#1e293b;font-weight:600"><?= h($studentInfo['name']) ?></span>
    <?php if ($editRecord): ?>
    <span style="color:#cbd5e1">›</span>
    <span style="color:#7c3aed">Editing Record</span>
    <?php else: ?>
    <span style="color:#cbd5e1">›</span>
    <span style="color:#15803d">New Record</span>
    <?php endif; ?>
    <?php endif; ?>
    <?php else: ?>
    <span>Select a class</span>
    <?php endif; ?>
  </nav>
  <div class="d-flex gap-2">
    <?php if ($selStudentId): ?>
    <a href="<?= url('/portal/montessori/anecdotal-history.php') ?>?class_id=<?= $selClassId ?>&student_id=<?= $selStudentId ?>"
       class="btn btn-sm btn-outline-primary" style="font-size:.78rem">
      <i class="fas fa-history me-1"></i>Full History
    </a>
    <?php endif; ?>
    <?php if ($selClassId): ?>
    <a href="<?= url('/portal/montessori/anecdotal-history.php') ?>?class_id=<?= $selClassId ?>"
       class="btn btn-sm btn-outline-secondary" style="font-size:.78rem">
      <i class="fas fa-list me-1"></i>Class History
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Class selector tabs -->
<?php if (count($assignedClasses) > 1): ?>
<div class="d-flex gap-2 flex-wrap mb-3">
  <?php foreach ($assignedClasses as $cls): ?>
  <a href="?class_id=<?= $cls['id'] ?>" class="btn btn-sm <?= (int)$cls['id']===$selClassId?'btn-primary':'btn-outline-secondary' ?>" style="font-size:.78rem">
    <?= h($cls['name']) ?>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$selClassId): ?>
<div class="alert alert-info" style="font-size:.83rem"><i class="fas fa-info-circle me-2"></i>No Montessori classes found.</div>
<?php else: ?>

<div class="row g-3">
  <!-- Left: Student picker + form -->
  <div class="col-lg-8">

    <!-- Student picker -->
    <div class="sec-card mb-3">
      <div class="sec-card-header d-flex align-items-center justify-content-between">
        <span><i class="fas fa-users me-2"></i>Select Student<?php if ($classInfo): ?> <span class="text-muted fw-normal ms-1" style="font-size:.8rem">&mdash; <?= h($classInfo['name']) ?></span><?php endif; ?></span>
        <span class="badge bg-secondary" style="font-size:.71rem"><?= count($classStudents) ?> students</span>
      </div>
      <?php if (empty($classStudents)): ?>
      <div class="p-3 text-muted" style="font-size:.82rem"><i class="fas fa-info-circle me-1"></i>No students in this class.</div>
      <?php else: ?>
      <div style="padding:14px 18px">
        <div class="row g-3">
          <div class="col-sm-5">
            <label class="form-label fw-semibold mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.4px;color:#94a3b8">
              <i class="fas fa-search me-1"></i>Search by Name / GR
            </label>
            <input type="text" id="marStuSearch" class="form-control form-control-sm"
                   placeholder="Type name or GR number…" autocomplete="off">
          </div>
          <div class="col-sm-7">
            <label class="form-label fw-semibold mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.4px;color:#94a3b8">
              <i class="fas fa-user me-1"></i>Select from Class
            </label>
            <div class="input-group input-group-sm">
              <select id="marStuDropdown" class="form-select">
                <option value="">— Choose a student —</option>
                <?php foreach ($classStudents as $stu):
                  $rno = $stu['roll_no'] ?? '';
                  $cnt = $todayCountMap[(int)$stu['id']] ?? 0;
                ?>
                <option value="<?= $stu['id'] ?>"
                        data-name="<?= h(mb_strtolower($stu['name'])) ?>"
                        data-roll="<?= h(mb_strtolower($rno)) ?>"
                        <?= (int)$stu['id']===$selStudentId ? 'selected' : '' ?>>
                  <?= h($stu['name']) ?><?= $rno ? ' — '.h($rno) : '' ?><?= $cnt ? " ($cnt today)" : '' ?>
                </option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="btn btn-primary" onclick="marNavigateToStudent()" title="Select this student">
                <i class="fas fa-arrow-right"></i>
              </button>
            </div>
          </div>
        </div>
        <?php if ($selStudentId && $studentInfo): ?>
        <div class="mt-3 d-flex align-items-center gap-2 flex-wrap"
             style="font-size:.81rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:7px;padding:8px 12px">
          <i class="fas fa-check-circle text-success"></i>
          <span>Selected: <strong><?= h($studentInfo['name']) ?></strong><?= $studentInfo['roll_no'] ? ' &mdash; Roll '.h($studentInfo['roll_no']) : '' ?></span>
          <a href="?class_id=<?= $selClassId ?>"
             class="ms-auto text-danger" style="font-size:.75rem;text-decoration:none;white-space:nowrap">
            <i class="fas fa-times-circle me-1"></i>Clear
          </a>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($selStudentId && $studentInfo): ?>
    <!-- Anecdotal Record Form -->
    <div class="ar-form-card">
      <div class="ar-form-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <div style="font-size:.75rem;opacity:.75;letter-spacing:.3px;text-transform:uppercase">Anecdotal Record</div>
            <div style="font-size:1rem;font-weight:700;margin-top:1px"><?= h($studentInfo['name']) ?></div>
            <div style="font-size:.78rem;opacity:.8"><?= h($classInfo['name'] ?? '') ?><?= $studentInfo['roll_no'] ? ' &middot; Roll: '.h($studentInfo['roll_no']) : '' ?></div>
          </div>
          <?php if ($editRecord): ?>
          <div>
            <span style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.35);border-radius:20px;padding:4px 12px;font-size:.75rem">
              <i class="fas fa-edit me-1"></i>Editing #<?= $editRecord['id'] ?>
            </span>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="ar-form-body">
        <form method="POST">
          <input type="hidden" name="action" value="save_anecdotal">
          <input type="hidden" name="class_id" value="<?= $selClassId ?>">
          <input type="hidden" name="student_id" value="<?= $selStudentId ?>">
          <?php if ($editRecord): ?>
          <input type="hidden" name="record_id" value="<?= $editRecord['id'] ?>">
          <?php endif; ?>

          <div class="ar-field-row">
            <div>
              <label class="ar-label" for="sf"><i class="fas fa-tag me-1 text-primary opacity-75"></i>Subject / Focus</label>
              <select name="subject_focus" id="sf" class="ar-select" onchange="toggleCustomFocus(this)">
                <?php $curFocus = $editRecord ? $editRecord['subject_focus'] : 'General Observation'; ?>
                <?php foreach ($FOCUS_OPTIONS as $fo): ?>
                <option value="<?= h($fo) ?>" <?= $curFocus===$fo?'selected':'' ?>><?= h($fo) ?></option>
                <?php endforeach; ?>
                <?php if ($curFocus && !in_array($curFocus,$FOCUS_OPTIONS,true)): ?>
                <option value="<?= h($curFocus) ?>" selected><?= h($curFocus) ?></option>
                <?php endif; ?>
              </select>
              <input type="text" id="sf-custom" name="subject_focus_custom" placeholder="Enter custom focus…"
                     class="ar-input mt-2" style="display:none" value="">
            </div>
            <div>
              <label class="ar-label" for="rdate"><i class="fas fa-calendar me-1 text-primary opacity-75"></i>Record Date</label>
              <input type="date" name="record_date" id="rdate" class="ar-input"
                     value="<?= h($editRecord ? $editRecord['record_date'] : date('Y-m-d')) ?>" required>
            </div>
          </div>

          <div style="margin-bottom:14px">
            <label class="ar-label" for="rtopic"><i class="fas fa-map-marker-alt me-1 text-primary opacity-75"></i>Topic / Area of Focus / Context <span style="color:#9ca3af;font-weight:400">(optional)</span></label>
            <input type="text" name="topic" id="rtopic" class="ar-input"
                   placeholder="e.g. Recitation – Number, Activity – Shapes, Classroom Behaviour…"
                   value="<?= h($editRecord ? ($editRecord['topic']??'') : '') ?>">
          </div>

          <div style="margin-bottom:20px">
            <label class="ar-label" for="robs">
              <i class="fas fa-pen-alt me-1 text-primary opacity-75"></i>Observation
              <span style="color:#dc2626">*</span>
            </label>
            <textarea name="observation" id="robs" class="ar-textarea"
                      placeholder="Write your observation about the student's behaviour, performance, or activity…"
                      required><?= h($editRecord ? $editRecord['observation'] : '') ?></textarea>
          </div>

          <div class="d-flex gap-2 flex-wrap align-items-center">
            <button type="submit" class="btn btn-primary" style="font-size:.84rem;padding:8px 20px">
              <i class="fas fa-save me-1"></i><?= $editRecord ? 'Update Record' : 'Save Record' ?>
            </button>
            <?php if ($editRecord): ?>
            <a href="?class_id=<?= $selClassId ?>&student_id=<?= $selStudentId ?>"
               class="btn btn-outline-secondary" style="font-size:.84rem;padding:8px 16px">
              <i class="fas fa-plus me-1"></i>New Record Instead
            </a>
            <?php endif; ?>
            <?php if ($editRecord): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this record permanently?')">
              <input type="hidden" name="action" value="delete_anecdotal">
              <input type="hidden" name="record_id" value="<?= $editRecord['id'] ?>">
              <button type="submit" class="btn btn-outline-danger" style="font-size:.84rem;padding:8px 16px">
                <i class="fas fa-trash me-1"></i>Delete
              </button>
            </form>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right: Sidebar info & recent records -->
  <div class="col-lg-4">
    <?php if ($selStudentId && $studentInfo): ?>
    <!-- Student card -->
    <div class="sec-card mb-3 p-0" style="overflow:hidden">
      <div style="background:linear-gradient(135deg,#1e3a5f,#2e6da4);color:#fff;padding:14px 16px">
        <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.4px;opacity:.75">Selected Student</div>
        <div style="font-size:.95rem;font-weight:700;margin-top:2px"><?= h($studentInfo['name']) ?></div>
        <div style="font-size:.75rem;opacity:.8"><?= h($classInfo['name']??'') ?><?= $studentInfo['roll_no'] ? ' &middot; Roll '.h($studentInfo['roll_no']) : '' ?></div>
      </div>
      <div style="padding:10px 16px;font-size:.78rem;color:#475569">
        <div><i class="fas fa-sticky-note me-2 text-primary"></i><?= count($recentRecords) ?> recent record<?= count($recentRecords)!==1?'s':'' ?></div>
        <div style="margin-top:4px"><i class="fas fa-calendar-day me-2 text-success"></i><?= $todayCountMap[$selStudentId]??0 ?> record<?= ($todayCountMap[$selStudentId]??0)!==1?'s':'' ?> today</div>
      </div>
    </div>

    <!-- Recent records -->
    <?php if (!empty($recentRecords)): ?>
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-history me-2"></i>Recent Records</span>
        <a href="<?= url('/portal/montessori/anecdotal-history.php') ?>?class_id=<?= $selClassId ?>&student_id=<?= $selStudentId ?>"
           style="font-size:.72rem;color:#2563eb;text-decoration:none">View All</a>
      </div>
      <div style="padding:10px 14px">
        <?php foreach ($recentRecords as $rec): ?>
        <div class="rec-item">
          <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <span class="rec-item-date"><?= date('d M Y', strtotime($rec['record_date'])) ?></span>
            <span class="rec-item-focus"><?= h($rec['subject_focus']) ?></span>
          </div>
          <?php if ($rec['topic']): ?>
          <div class="rec-item-topic"><?= h($rec['topic']) ?></div>
          <?php endif; ?>
          <div class="rec-item-obs"><?= h($rec['obs_preview']) ?><?= strlen($rec['obs_preview'])>=120?'…':'' ?></div>
          <div class="d-flex gap-2 mt-2">
            <a href="?class_id=<?= $selClassId ?>&student_id=<?= $selStudentId ?>&edit=<?= $rec['id'] ?>"
               class="btn btn-sm btn-outline-primary" style="font-size:.7rem;padding:2px 8px">
              <i class="fas fa-edit me-1"></i>Edit
            </a>
            <a href="<?= url('/portal/montessori/anecdotal-print.php') ?>?id=<?= $rec['id'] ?>" target="_blank"
               class="btn btn-sm btn-outline-success" style="font-size:.7rem;padding:2px 8px">
              <i class="fas fa-print me-1"></i>Print
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <!-- Tip card when no student selected -->
    <div class="sec-card p-4 text-center" style="color:#64748b">
      <i class="fas fa-hand-point-left fa-2x mb-2 opacity-50"></i>
      <div style="font-size:.83rem;font-weight:600">Select a Student</div>
      <div style="font-size:.75rem;margin-top:4px">Click a student card on the left to open their Anecdotal Record form.</div>
    </div>
    <?php endif; ?>

    <!-- About anecdotal records -->
    <div class="sec-card mt-3" style="background:#f0fdf4;border-color:#bbf7d0">
      <div style="padding:12px 14px;font-size:.76rem;color:#15803d">
        <div style="font-weight:700;margin-bottom:4px"><i class="fas fa-info-circle me-1"></i>About Anecdotal Records</div>
        <ul style="padding-left:16px;margin:0;line-height:1.8">
          <li>Each record is a brief factual observation about a student.</li>
          <li>Multiple records per student are fully supported.</li>
          <li>Records are teacher-only and never visible to students.</li>
          <li>Use the History page to view and manage all records.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleCustomFocus(sel) {
    var custom = document.getElementById('sf-custom');
    if (sel.value === 'Other') {
        custom.style.display = 'block';
        custom.required = true;
        sel.name = '_subject_focus_sel';
        custom.name = 'subject_focus';
    } else {
        custom.style.display = 'none';
        custom.required = false;
        sel.name = 'subject_focus';
        custom.name = 'subject_focus_custom';
    }
}
document.addEventListener('DOMContentLoaded', function(){
    var sel = document.getElementById('sf');
    if (sel) toggleCustomFocus(sel);
});

// ── Navigate to selected student (Anecdotal Records) ──────────────
function marNavigateToStudent() {
    var dd = document.getElementById('marStuDropdown');
    if (!dd || !dd.value) { if (dd) dd.focus(); return; }
    window.location.href = '?class_id=<?= $selClassId ?>'
        + '&student_id=' + encodeURIComponent(dd.value);
}

// ── Student search: live-filters the dropdown ─────────────────────
(function() {
    var dd = document.getElementById('marStuDropdown');
    var sr = document.getElementById('marStuSearch');
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
            marNavigateToStudent();
        } else if (dd.value) {
            marNavigateToStudent();
        }
    });

    dd.addEventListener('change', function() {
        if (this.value) { sr.value = ''; filterDd(''); }
    });
})();
</script>
</body></html>

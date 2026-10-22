<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('teacher', 'ilc_teacher', 'montessori_teacher',
    'secondary_wing_head', 'higher_secondary_wing_head', 'primary_wing_head',
    'vp_main', 'ilc_vp', 'wing_head', 'vp_montessori', 'examination_head');
if ($user['role'] === 'montessori_teacher') {
    redirect('/portal/montessori/assessments.php');
}
$staffTeachRoles = ['vp_main', 'ilc_vp', 'wing_head', 'vp_montessori', 'examination_head'];
if (in_array($user['role'], $staffTeachRoles)) {
    if (!hasSubjectTeacherAssignments($user['id'])) {
        setFlash('danger', 'You have no Main Campus subject assignments. Contact admin to assign a class and subject.');
        redirect('/portal/index.php');
    }
} else {
    requirePermission('marks');
}
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);
if (!$teacher) { setFlash('danger','Teacher record not found.'); redirect('/portal/index.php'); }

$tab          = $_GET['tab'] ?? 'assessments';
$assessmentId = (int)($_GET['assessment_id'] ?? 0);

// ── Grade helper — delegates to central rubric in config/config.php ────────
function _gradeInfo(float $pct): array {
    return getGrade($pct);
}

// ── Determine teacher wing ─────────────────────────────────────────────────
$teacherWing = $teacher['wing'] ?? null;
if (!$teacherWing || $teacherWing === '') {
    if ($user['role'] === 'montessori_teacher') $teacherWing = 'montessori';
    elseif ($user['role'] === 'ilc_teacher')    $teacherWing = 'ilc';
    else                                         $teacherWing = 'main';
}

// ── POST handlers ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Create assessment ──────────────────────────────────────────────────
    if ($action === 'create_assessment') {
        $classId   = (int)$_POST['class_id'];
        $subjectId = (int)($_POST['subject_id'] ?? $teacher['subject_id'] ?? 0);
        $title     = trim($_POST['title'] ?? '');
        $type      = trim($_POST['type']  ?? '');
        if ($type === '') $type = 'Quiz';
        if (strlen($type) > 100) $type = substr($type, 0, 100);
        $maxMarks  = (float)$_POST['max_marks'];
        $weight    = (float)$_POST['weight'];
        $date      = $_POST['date'] ?? date('Y-m-d');
        if ($classId && $subjectId && $title && $maxMarks) {
            // Main campus: create as draft (requires VP approval before students can see)
            // ILC/Montessori: bypass workflow, publish immediately
            $publishStatus = ($teacherWing === 'main') ? 'draft' : 'published';
            // Centralised assessment-identity key so this assessment is grouped
            // with any other teacher's assessment that refers to the same exam
            // (e.g. "First Midterm" ≡ "1st Midterm" ≡ "FIRST MIDTERM").
            $nameKey = normalizeAssessmentName($title);
            try {
                $db->prepare(
                    'INSERT INTO assessments (class_id, subject_id, teacher_id, title, name, type, max_marks, weight, date, publish_status, name_key)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([$classId, $subjectId, $teacher['id'], $title, $title, $type, $maxMarks, $weight, $date, $publishStatus, $nameKey !== '' ? $nameKey : null]);
            } catch (Exception $e) {
                // name_key or publish_status column may not exist yet — try progressive fallbacks
                try {
                    $db->prepare(
                        'INSERT INTO assessments (class_id, subject_id, teacher_id, title, name, type, max_marks, weight, date, publish_status)
                         VALUES (?,?,?,?,?,?,?,?,?,?)'
                    )->execute([$classId, $subjectId, $teacher['id'], $title, $title, $type, $maxMarks, $weight, $date, $publishStatus]);
                } catch (Exception $e2) {
                    $db->prepare(
                        'INSERT INTO assessments (class_id, subject_id, teacher_id, title, name, type, max_marks, weight, date)
                         VALUES (?,?,?,?,?,?,?,?,?)'
                    )->execute([$classId, $subjectId, $teacher['id'], $title, $title, $type, $maxMarks, $weight, $date]);
                }
            }
            logActivity($user['id'], 'assessment_create', "Created: $title (key=$nameKey)");
            if ($teacherWing === 'main') {
                setFlash('success', 'Assessment created (Draft). Request marks entry permission, enter marks, then submit for VP review to publish.');
            } else {
                setFlash('success', 'Assessment created. Request marks entry permission below to start entering marks.');
            }
        } else {
            setFlash('danger', 'All required fields must be filled.');
        }
        redirect('/portal/teacher/marks.php?tab=assessments');
    }

    // ── Submit assessment for VP review ───────────────────────────────────
    if ($action === 'submit_for_review') {
        $aId = (int)$_POST['assessment_id'];
        $chk = $db->prepare("SELECT id, publish_status, title, name FROM assessments WHERE id=? AND teacher_id=?");
        $chk->execute([$aId, $teacher['id']]);
        $asmRow = $chk->fetch();
        if ($asmRow && in_array($asmRow['publish_status'] ?? 'published', ['draft', 'changes_requested'])) {
            $db->prepare(
                "UPDATE assessments SET publish_status='pending_review', submitted_at=NOW(), review_note=NULL WHERE id=?"
            )->execute([$aId]);
            logActivity($user['id'], 'result_submit_review', "Submitted for review: assessment #$aId");
            // Notify approvers
            try {
                $asmTitle = $asmRow['title'] ?: $asmRow['name'];
                $infoSt = $db->prepare(
                    'SELECT c.name AS class_name, s.name AS subject_name
                     FROM assessments a JOIN classes c ON a.class_id=c.id JOIN subjects s ON a.subject_id=s.id
                     WHERE a.id=?'
                );
                $infoSt->execute([$aId]);
                $info = $infoSt->fetch();
                $msg  = "Result submitted for review by {$user['name']}: \"" . h($asmTitle) . '"'
                      . ($info ? ' — ' . h($info['class_name']) . ' / ' . h($info['subject_name']) : '');
                foreach (getApproversForWing('main') as $approverId) {
                    createNotification($approverId, 'result_submitted', $msg, $aId, 'assessment');
                }
                createNotification($user['id'], 'result_submitted_confirm',
                    "Your result for \"" . h($asmTitle) . "\" has been submitted for VP review.", $aId, 'assessment');
            } catch (Exception $e) {}
            setFlash('success', 'Result submitted for VP review. You will be notified once it is reviewed.');
        } else {
            setFlash('info', 'This assessment cannot be submitted for review in its current state.');
        }
        redirect('/portal/teacher/marks.php?tab=assessments');
    }

    // ── Delete assessment ──────────────────────────────────────────────────
    if ($action === 'delete_assessment') {
        $id = (int)$_POST['assessment_id'];
        $chk = $db->prepare('SELECT id FROM assessments WHERE id=? AND teacher_id=?');
        $chk->execute([$id, $teacher['id']]);
        if ($chk->fetch()) {
            $db->prepare('DELETE FROM marks WHERE assessment_id=?')->execute([$id]);
            try { $db->prepare('DELETE FROM marks_permission_requests WHERE assessment_id=?')->execute([$id]); } catch (Exception $e) {}
            $db->prepare('DELETE FROM assessments WHERE id=?')->execute([$id]);
            logActivity($user['id'], 'assessment_delete', "Deleted assessment #$id");
            setFlash('success', 'Assessment deleted.');
        }
        redirect('/portal/teacher/marks.php?tab=assessments');
    }

    // ── Request / re-request marks entry permission ────────────────────────
    if ($action === 'request_permission') {
        $aId    = (int)$_POST['assessment_id'];
        $reason = trim($_POST['reason'] ?? '');

        // Verify teacher owns this assessment
        $chk = $db->prepare('SELECT a.id FROM assessments a WHERE a.id=? AND a.teacher_id=?');
        $chk->execute([$aId, $teacher['id']]);
        if (!$chk->fetch()) {
            setFlash('danger', 'Assessment not found.');
            redirect('/portal/teacher/marks.php?tab=marks&assessment_id=' . $aId);
        }

        $existing = getMarksPermission($teacher['id'], $aId);
        $academicYear = getSetting('session_year', date('Y'));

        if ($existing && $existing['status'] === 'approved') {
            setFlash('info', 'Permission is already approved for this assessment.');
        } elseif ($existing && $existing['status'] === 'pending') {
            setFlash('info', 'A request is already pending approval.');
        } else {
            if ($existing) {
                // Re-request after rejection
                $db->prepare(
                    'UPDATE marks_permission_requests
                     SET request_reason=?, status="pending", reviewed_by=NULL,
                         reviewed_at=NULL, rejection_reason=NULL, academic_year=?, created_at=NOW()
                     WHERE id=?'
                )->execute([$reason, $academicYear, $existing['id']]);
                logActivity($user['id'], 'marks_permission_rerequest', "Re-requested permission for assessment #$aId");
            } else {
                $db->prepare(
                    'INSERT INTO marks_permission_requests (teacher_id, assessment_id, request_reason, academic_year)
                     VALUES (?,?,?,?)'
                )->execute([$teacher['id'], $aId, $reason, $academicYear]);
                logActivity($user['id'], 'marks_permission_request', "Requested permission for assessment #$aId");
            }

            // Notify approvers
            try {
                $asmInfo = $db->prepare(
                    'SELECT a.title, c.name AS class_name, s.name AS subject_name
                     FROM assessments a
                     JOIN classes c ON a.class_id=c.id
                     JOIN subjects s ON a.subject_id=s.id
                     WHERE a.id=?'
                );
                $asmInfo->execute([$aId]);
                $aInfo = $asmInfo->fetch();
                $msg = "Marks permission requested by {$user['name']} for "
                     . h($aInfo['subject_name'] ?? '') . " / "
                     . h($aInfo['class_name'] ?? '') . " — "
                     . h($aInfo['title'] ?? '');
                foreach (getApproversForWing($teacherWing) as $approverId) {
                    createNotification($approverId, 'marks_permission_request', $msg, $aId, 'assessment');
                }
                // Notify the teacher themselves (confirmation)
                createNotification($user['id'], 'marks_permission_submitted',
                    "Your marks entry permission request has been submitted and is awaiting review.", $aId, 'assessment');
            } catch (Exception $e) {}

            setFlash('success', 'Permission request submitted. You will be notified once it is reviewed.');
        }
        redirect('/portal/teacher/marks.php?tab=marks&assessment_id=' . $aId);
    }

    // ── Save marks ─────────────────────────────────────────────────────────
    if ($action === 'save_marks') {
        $aId = (int)$_POST['assessment_id'];

        // Backend permission check
        $permChk = null;
        try {
            $ps = $db->prepare(
                "SELECT id FROM marks_permission_requests
                 WHERE teacher_id=? AND assessment_id=? AND status='approved'"
            );
            $ps->execute([$teacher['id'], $aId]);
            $permChk = $ps->fetch();
        } catch (Exception $e) { $permChk = true; } // table not yet created — allow

        if (!$permChk) {
            setFlash('danger', 'Marks entry permission not approved for this assessment. Please request permission first.');
            redirect('/portal/teacher/marks.php?tab=marks&assessment_id=' . $aId);
        }

        $marksInput = $_POST['marks'] ?? [];
        $saved = 0;
        foreach ($marksInput as $studentId => $row) {
            $obtained = ($row['marks_obtained'] !== '') ? (float)$row['marks_obtained'] : null;
            if ($obtained === null) continue;
            $db->prepare(
                'INSERT INTO marks (assessment_id, student_id, marks_obtained, remarks)
                 VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE marks_obtained=VALUES(marks_obtained), remarks=VALUES(remarks)'
            )->execute([$aId, (int)$studentId, $obtained, trim($row['remarks'] ?? '')]);
            $saved++;
        }
        logActivity($user['id'], 'marks_save', "Saved marks for assessment #$aId ($saved students)");

        // Main campus: if this assessment was already published, editing marks
        // resets it to draft so it requires re-submission and VP re-approval.
        $resetToReview = false;
        if ($teacherWing === 'main') {
            try {
                $pubSt = $db->prepare("SELECT publish_status FROM assessments WHERE id=?");
                $pubSt->execute([$aId]);
                $pubRow = $pubSt->fetch();
                if ($pubRow && $pubRow['publish_status'] === 'published') {
                    $db->prepare(
                        "UPDATE assessments SET publish_status='draft', published_by=NULL, published_at=NULL WHERE id=?"
                    )->execute([$aId]);
                    $resetToReview = true;
                }
            } catch (Exception $e) {}
        }

        if ($resetToReview) {
            setFlash('warning', "Marks saved for $saved student(s). This result was published, so it has been moved back to Draft — please re-submit for VP review and re-publishing.");
        } else {
            setFlash('success', "Marks saved for $saved student(s).");
        }
        redirect('/portal/teacher/marks.php?tab=marks&assessment_id=' . $aId);
    }
}

// ── Data loading ───────────────────────────────────────────────────────────
$assessmentsSt = $db->prepare(
    'SELECT a.*, c.name AS class_name, s.name AS subject_name,
            (SELECT COUNT(*) FROM marks m WHERE m.assessment_id = a.id) AS marks_entered
     FROM assessments a
     JOIN classes c ON a.class_id = c.id
     JOIN subjects s ON a.subject_id = s.id
     WHERE a.teacher_id = ?
     ORDER BY a.date DESC'
);
$assessmentsSt->execute([$teacher['id']]);
$assessments = $assessmentsSt->fetchAll();

// Fetch permission status for all assessments at once
$permMap = [];
try {
    if (!empty($assessments)) {
        $aIds = array_column($assessments, 'id');
        $ph   = implode(',', array_fill(0, count($aIds), '?'));
        $ps   = $db->prepare("SELECT * FROM marks_permission_requests WHERE teacher_id=? AND assessment_id IN ($ph)");
        $ps->execute(array_merge([$teacher['id']], $aIds));
        foreach ($ps->fetchAll() as $pr) {
            $permMap[$pr['assessment_id']] = $pr;
        }
    }
} catch (Exception $e) {}

// Load current assessment + students for marks tab
$currentAssessment = null;
$students          = [];
$existingMarks     = [];
$currentPerm       = null;

if ($tab === 'marks' && $assessmentId) {
    $aSt = $db->prepare(
        'SELECT a.*, c.name AS class_name, s.name AS subject_name
         FROM assessments a
         JOIN classes c ON a.class_id=c.id
         JOIN subjects s ON a.subject_id=s.id
         WHERE a.id=? AND a.teacher_id=?'
    );
    $aSt->execute([$assessmentId, $teacher['id']]);
    $currentAssessment = $aSt->fetch();

    if ($currentAssessment) {
        $currentPerm = $permMap[$assessmentId] ?? getMarksPermission($teacher['id'], $assessmentId);

        if ($currentPerm && $currentPerm['status'] === 'approved') {
            $students = getClassStudents((int)$currentAssessment['class_id']);
            $mSt = $db->prepare('SELECT student_id, marks_obtained, remarks FROM marks WHERE assessment_id=?');
            $mSt->execute([$assessmentId]);
            foreach ($mSt->fetchAll() as $m) {
                $existingMarks[$m['student_id']] = $m;
            }
        }
    }
}

// Classes & subjects assigned to this teacher
if ($user['role'] === 'montessori_teacher') {
    $cSt = $db->prepare(
        'SELECT DISTINCT c.id, c.name FROM class_subjects cs
         JOIN classes c ON cs.class_id=c.id
         WHERE cs.teacher_id=? AND c.is_montessori=1 AND COALESCE(c.grade,0)>=2
         ORDER BY c.grade, c.section'
    );
} else {
    $cSt = $db->prepare(
        'SELECT DISTINCT c.id, c.name FROM class_subjects cs
         JOIN classes c ON cs.class_id=c.id
         WHERE cs.teacher_id=?
         ORDER BY c.grade, c.section'
    );
}
$cSt->execute([$teacher['id']]);
$assignedClasses = $cSt->fetchAll();

$sSt = $db->prepare(
    'SELECT DISTINCT s.id, s.name FROM class_subjects cs
     JOIN subjects s ON cs.subject_id=s.id
     WHERE cs.teacher_id=? ORDER BY s.name'
);
$sSt->execute([$teacher['id']]);
$assignedSubjects = $sSt->fetchAll();
if (empty($assignedSubjects) && !empty($teacher['subject_id'])) {
    $assignedSubjects = [['id'=>$teacher['subject_id'], 'name'=>$teacher['subject_name'] ?? '']];
}

// Load existing canonical assessment names for the teacher's assigned
// classes. The create form uses this as a <datalist> so teachers can
// pick an existing name (e.g. "First Midterm") instead of inventing a
// new variant ("1st Midterm") — fewer duplicates to consolidate later.
$existingAssessmentNames = [];
try {
    $classIds = array_values(array_filter(array_column($assignedClasses, 'id')));
    if (!empty($classIds)) {
        $place = implode(',', array_fill(0, count($classIds), '?'));
        $hasNameKey = false;
        try { $db->query('SELECT name_key FROM assessments LIMIT 0'); $hasNameKey = true; } catch (Exception $e) {}
        if ($hasNameKey) {
            // One row per canonical identity, longest title wins the display label.
            $ast = $db->prepare(
                "SELECT SUBSTRING_INDEX(
                            GROUP_CONCAT(COALESCE(NULLIF(title,''), name)
                                ORDER BY CHAR_LENGTH(COALESCE(NULLIF(title,''), name)) DESC
                                SEPARATOR '\\n'),
                            '\\n', 1
                        ) AS label
                 FROM assessments
                 WHERE class_id IN ($place) AND name_key IS NOT NULL AND name_key <> ''
                 GROUP BY name_key
                 ORDER BY label"
            );
        } else {
            $ast = $db->prepare(
                "SELECT DISTINCT COALESCE(NULLIF(title,''), name) AS label
                   FROM assessments
                  WHERE class_id IN ($place)
                  ORDER BY label"
            );
        }
        $ast->execute($classIds);
        $existingAssessmentNames = array_values(array_filter(
            $ast->fetchAll(PDO::FETCH_COLUMN),
            fn($v) => $v !== null && $v !== ''
        ));
    }
} catch (Exception $e) { $existingAssessmentNames = []; }

pageHead('Marks', $user['role']);
$links = match($user['role']) {
    'montessori_teacher'           => getMonteTeacherLinks(),
    'ilc_teacher'                  => getIlcTeacherLinks(),
    'secondary_wing_head'          => getSecondaryWingHeadLinks(),
    'higher_secondary_wing_head'   => getHigherSecondaryWingHeadLinks(),
    'primary_wing_head'            => getPrimaryWingHeadLinks(),
    'vp_main'                      => getVpLinks(),
    'ilc_vp'                       => getIlcLinks(),
    'wing_head'                    => getWingHeadLinks(),
    'vp_montessori'                => getVpMontessoriLinks(),
    'examination_head'             => getExamHeadLinks(),
    default                        => getTeacherLinks(),
};
?>
<div class="portal-wrap">
<?php sidebar($user['role'], 'marks', $links, $user); ?>
<div class="main-area">
<?php topbar('Marks & Assessments', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link <?= $tab==='assessments'?'active':'' ?>" href="?tab=assessments">Manage Assessments</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab==='marks'?'active':'' ?>" href="?tab=marks">Enter Marks</a></li>
</ul>

<?php if ($tab === 'assessments'): ?>

<!-- Create Assessment -->
<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-plus me-2"></i>Create New Assessment</div>
  <div style="padding:16px">
    <form method="POST">
      <input type="hidden" name="action" value="create_assessment">
      <div class="row g-2">
        <div class="col-md-3">
          <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
          <select name="class_id" class="form-select form-select-sm" required>
            <option value="">Select class</option>
            <?php foreach ($assignedClasses as $c): ?>
              <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold" style="font-size:.82rem">Subject</label>
          <select name="subject_id" class="form-select form-select-sm" required>
            <option value="">Select subject</option>
            <?php foreach ($assignedSubjects as $s): ?>
              <option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold" style="font-size:.82rem">
            Title
            <?php if (!empty($existingAssessmentNames)): ?>
            <span class="text-muted" style="font-size:.7rem;font-weight:400">
              — start typing to reuse an existing name
            </span>
            <?php endif; ?>
          </label>
          <input type="text" name="title" class="form-control form-control-sm"
                 placeholder="e.g. First Midterm" list="rc-assess-names" autocomplete="off" required>
          <?php if (!empty($existingAssessmentNames)): ?>
          <datalist id="rc-assess-names">
            <?php foreach ($existingAssessmentNames as $nm): ?>
            <option value="<?= h($nm) ?>">
            <?php endforeach; ?>
          </datalist>
          <?php endif; ?>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Assessment Type</label>
          <input type="text" name="type" class="form-control form-control-sm" placeholder="e.g. Quiz, Test…" maxlength="100" required>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Max Marks</label>
          <input type="number" name="max_marks" class="form-control form-control-sm" min="1" max="200" step="any" required>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold" style="font-size:.82rem">Weight %</label>
          <input type="number" name="weight" class="form-control form-control-sm" min="0" max="100" step="any" value="10">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold" style="font-size:.82rem">Date</label>
          <input type="date" name="date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-plus me-1"></i>Create Assessment</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Assessments List -->
<div class="sec-card">
  <div class="sec-card-header"><i class="fas fa-list me-2"></i>My Assessments
    <span class="ms-2 text-muted fw-normal" style="font-size:.78rem">— click "Enter Marks" to open the marks entry page for that assessment</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr>
          <th>Title</th><th>Type</th><th>Class</th><th>Subject</th>
          <th>Max</th><th>Weight</th><th>Date</th><th>Marks</th>
          <th>Permission</th>
          <?php if ($teacherWing === 'main'): ?><th>Result Status</th><?php endif; ?>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($assessments as $a):
          $perm = $permMap[$a['id']] ?? null;
          $permBadge = match($perm['status'] ?? '') {
              'approved' => '<span class="badge bg-success" style="font-size:.7rem"><i class="fas fa-check me-1"></i>Approved</span>',
              'pending'  => '<span class="badge bg-warning text-dark" style="font-size:.7rem"><i class="fas fa-clock me-1"></i>Pending</span>',
              'rejected' => '<span class="badge bg-danger" style="font-size:.7rem"><i class="fas fa-times me-1"></i>Rejected</span>',
              default    => '<span class="badge bg-secondary" style="font-size:.7rem"><i class="fas fa-lock me-1"></i>Not Requested</span>',
          };
          $pubStatus = $a['publish_status'] ?? 'published';
          $pubBadge = match($pubStatus) {
              'draft'              => '<span class="badge bg-secondary" style="font-size:.68rem">Draft</span>',
              'pending_review'     => '<span class="badge bg-warning text-dark" style="font-size:.68rem"><i class="fas fa-hourglass-half me-1"></i>Pending Review</span>',
              'changes_requested'  => '<span class="badge bg-danger" style="font-size:.68rem"><i class="fas fa-edit me-1"></i>Changes Requested</span>',
              'approved'           => '<span class="badge bg-success" style="font-size:.68rem"><i class="fas fa-check me-1"></i>Approved</span>',
              'published'          => '<span class="badge bg-primary" style="font-size:.68rem"><i class="fas fa-check-double me-1"></i>Published</span>',
              default              => '',
          };
          $canSubmit = ($teacherWing === 'main' && in_array($pubStatus, ['draft', 'changes_requested']));
          $reviewNote = $a['review_note'] ?? '';
        ?>
        <tr>
          <td class="fw-semibold">
            <?= h($a['title'] ?: $a['name']) ?>
            <?php if ($reviewNote && $pubStatus === 'changes_requested'): ?>
            <div style="margin-top:3px;font-size:.76rem;color:#991b1b;background:#fef2f2;border-radius:3px;padding:3px 6px">
              <strong>VP Note:</strong> <?= h($reviewNote) ?>
            </div>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-secondary" style="font-size:.74rem"><?= h($a['type']) ?></span></td>
          <td><?= h($a['class_name']) ?></td>
          <td><?= h($a['subject_name']) ?></td>
          <td><?= $a['max_marks'] ?></td>
          <td><?= $a['weight'] ?>%</td>
          <td style="white-space:nowrap"><?= fDate($a['date']) ?></td>
          <td><?= $a['marks_entered'] ?> entered</td>
          <td><?= $permBadge ?></td>
          <?php if ($teacherWing === 'main'): ?>
          <td>
            <?= $pubBadge ?>
            <?php if ($canSubmit && $a['marks_entered'] > 0): ?>
            <div style="margin-top:4px">
              <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="submit_for_review">
                <input type="hidden" name="assessment_id" value="<?= $a['id'] ?>">
                <button class="btn btn-xs btn-outline-success" style="font-size:.7rem;padding:2px 7px"
                        onclick="return confirm('Submit this result for VP review?')">
                  <i class="fas fa-paper-plane me-1"></i>Submit for Review
                </button>
              </form>
            </div>
            <?php elseif ($canSubmit && $a['marks_entered'] == 0): ?>
            <div style="font-size:.74rem;color:#9ca3af;margin-top:2px">Enter marks first</div>
            <?php endif; ?>
          </td>
          <?php endif; ?>
          <td style="white-space:nowrap">
            <a href="?tab=marks&assessment_id=<?= $a['id'] ?>" class="btn btn-xs btn-outline-primary" style="font-size:.75rem;padding:2px 8px">Enter Marks</a>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this assessment and all its marks?')">
              <input type="hidden" name="action" value="delete_assessment">
              <input type="hidden" name="assessment_id" value="<?= $a['id'] ?>">
              <button class="btn btn-xs btn-outline-danger" style="font-size:.75rem;padding:2px 8px">Del</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($assessments)): ?>
        <tr><td colspan="<?= $teacherWing === 'main' ? 11 : 10 ?>" class="text-center text-muted py-3">No assessments created yet. Use the form above to create one.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'marks'): ?>

<!-- Select an assessment if none chosen -->
<?php if (!$assessmentId || !$currentAssessment): ?>
<div class="sec-card p-4">
  <p class="text-muted mb-3 fw-semibold">Select an assessment to view its marks entry page:</p>
  <div class="list-group">
    <?php foreach ($assessments as $a):
      $perm = $permMap[$a['id']] ?? null;
      $badge = match($perm['status'] ?? '') {
          'approved' => '<span class="badge bg-success ms-2" style="font-size:.68rem">Approved</span>',
          'pending'  => '<span class="badge bg-warning text-dark ms-2" style="font-size:.68rem">Pending Approval</span>',
          'rejected' => '<span class="badge bg-danger ms-2" style="font-size:.68rem">Rejected</span>',
          default    => '<span class="badge bg-secondary ms-2" style="font-size:.68rem">Permission Required</span>',
      };
    ?>
    <a href="?tab=marks&assessment_id=<?= $a['id'] ?>"
       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <strong><?= h($a['title'] ?: $a['name']) ?></strong><?= $badge ?>
        <span class="text-muted ms-2" style="font-size:.82rem">— <?= h($a['class_name']) ?> / <?= h($a['subject_name']) ?></span>
      </div>
      <span class="text-muted" style="font-size:.8rem"><?= fDate($a['date']) ?> &nbsp;|&nbsp; Max: <?= $a['max_marks'] ?></span>
    </a>
    <?php endforeach; ?>
    <?php if (empty($assessments)): ?>
    <div class="text-center text-muted py-4" style="font-size:.88rem">
      No assessments yet. <a href="?tab=assessments">Create one first</a>.
    </div>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>

<!-- Assessment info card + permission status -->
<div class="sec-card mb-3">
  <div class="sec-card-header d-flex justify-content-between flex-wrap gap-2">
    <span>
      <i class="fas fa-clipboard-list me-2"></i>
      <?= h($currentAssessment['title'] ?: $currentAssessment['name']) ?>
      <span class="text-muted fw-normal ms-2" style="font-size:.82rem">
        <?= h($currentAssessment['class_name']) ?> / <?= h($currentAssessment['subject_name']) ?>
      </span>
    </span>
    <span class="text-muted" style="font-size:.81rem">
      <?= h($currentAssessment['type']) ?> &nbsp;|&nbsp; Max: <?= $currentAssessment['max_marks'] ?>
      &nbsp;|&nbsp; <?= fDate($currentAssessment['date']) ?>
    </span>
  </div>

  <!-- Permission status panel -->
  <?php if (!$currentPerm): ?>
  <div style="padding:20px;background:#fffbeb;border-top:2px solid #fde68a">
    <div class="d-flex align-items-start gap-3">
      <i class="fas fa-lock" style="color:#d97706;font-size:1.6rem;margin-top:2px;flex-shrink:0"></i>
      <div class="flex-grow-1">
        <div class="fw-bold mb-1" style="color:#92400e;font-size:.95rem">
          <span class="badge bg-warning text-dark me-2" style="font-size:.78rem">Permission Required</span>
          Marks Entry Permission Required
        </div>
        <p style="color:#78350f;font-size:.84rem;margin:0 0 12px">
          You need approval from your <?= wingLabel($teacherWing) ?> VP / Wing Head before entering marks for this assessment.
          Submit a request and you will be notified once it is reviewed.
        </p>
        <form method="POST">
          <input type="hidden" name="action" value="request_permission">
          <input type="hidden" name="assessment_id" value="<?= $assessmentId ?>">
          <div class="row g-2">
            <div class="col-md-8">
              <textarea name="reason" class="form-control form-control-sm" rows="2"
                        placeholder="Optional: explain why you need marks entry permission for this assessment…"
                        style="font-size:.84rem"></textarea>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button type="submit" class="btn btn-warning btn-sm w-100">
                <i class="fas fa-paper-plane me-1"></i>Request Permission
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php elseif ($currentPerm['status'] === 'pending'): ?>
  <div style="padding:20px;background:#eff6ff;border-top:2px solid #bfdbfe">
    <div class="d-flex align-items-center gap-3">
      <i class="fas fa-hourglass-half" style="color:#2563eb;font-size:1.5rem;flex-shrink:0"></i>
      <div>
        <div class="fw-bold mb-1" style="color:#1e40af;font-size:.95rem">
          <span class="badge bg-warning text-dark me-2" style="font-size:.78rem">Pending</span>
          Permission Request Under Review
        </div>
        <p style="color:#1d4ed8;font-size:.84rem;margin:0">
          Your request was submitted on <strong><?= fDateTime($currentPerm['created_at']) ?></strong>.
          You will be notified once your <?= wingLabel($teacherWing) ?> VP / Wing Head reviews it.
        </p>
        <?php if ($currentPerm['request_reason']): ?>
        <div style="margin-top:8px;font-size:.82rem;color:#1e40af;background:rgba(219,234,254,.5);border-radius:4px;padding:6px 10px">
          <strong>Your reason:</strong> <?= h($currentPerm['request_reason']) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php elseif ($currentPerm['status'] === 'rejected'): ?>
  <div style="padding:20px;background:#fef2f2;border-top:2px solid #fecaca">
    <div class="d-flex align-items-start gap-3">
      <i class="fas fa-times-circle" style="color:#dc2626;font-size:1.6rem;margin-top:2px;flex-shrink:0"></i>
      <div class="flex-grow-1">
        <div class="fw-bold mb-1" style="color:#991b1b;font-size:.95rem">
          <span class="badge bg-danger me-2" style="font-size:.78rem">Rejected</span>
          Permission Request Rejected
        </div>
        <?php if ($currentPerm['rejection_reason']): ?>
        <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:6px;padding:8px 12px;margin:8px 0;font-size:.84rem;color:#7f1d1d">
          <strong><i class="fas fa-comment-alt me-1"></i>Rejection reason:</strong>
          <?= h($currentPerm['rejection_reason']) ?>
        </div>
        <?php endif; ?>
        <p style="color:#b91c1c;font-size:.84rem;margin:4px 0 12px">
          You may submit a new request with an updated reason.
        </p>
        <form method="POST">
          <input type="hidden" name="action" value="request_permission">
          <input type="hidden" name="assessment_id" value="<?= $assessmentId ?>">
          <div class="row g-2">
            <div class="col-md-8">
              <textarea name="reason" class="form-control form-control-sm" rows="2"
                        placeholder="Updated reason for re-requesting permission…"
                        style="font-size:.84rem"></textarea>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button type="submit" class="btn btn-danger btn-sm w-100">
                <i class="fas fa-redo me-1"></i>Re-submit Request
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php elseif ($currentPerm['status'] === 'approved'): ?>
  <div style="padding:10px 20px;background:#f0fdf4;border-top:2px solid #bbf7d0;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <i class="fas fa-check-circle" style="color:#16a34a;font-size:1.2rem"></i>
    <span class="fw-semibold" style="color:#166534;font-size:.9rem">
      <span class="badge bg-success me-2" style="font-size:.78rem">Marks Entry Enabled</span>
      Permission approved on <?= fDateTime($currentPerm['reviewed_at']) ?>
    </span>
    <span class="ms-auto"><a href="?tab=marks" class="btn btn-xs btn-outline-secondary" style="font-size:.76rem">← Back to list</a></span>
  </div>
  <?php endif; ?>
</div>

<!-- Marks Entry Form (only when approved) -->
<?php if ($currentPerm && $currentPerm['status'] === 'approved'): ?>
<div class="sec-card mb-3">
  <div class="sec-card-header">
    <i class="fas fa-pen-alt me-2"></i>Enter Marks — <?= h($currentAssessment['class_name']) ?>
    <span class="text-muted fw-normal ms-2" style="font-size:.8rem"><?= count($students) ?> student(s)</span>
  </div>
  <form method="POST">
    <input type="hidden" name="action" value="save_marks">
    <input type="hidden" name="assessment_id" value="<?= $assessmentId ?>">
    <div class="table-responsive">
      <table class="table table-hover mb-0" style="font-size:.85rem">
        <thead class="table-light">
          <tr>
            <th>#</th><th>Roll No</th><th>Name</th>
            <th style="width:140px">Marks (/ <?= $currentAssessment['max_marks'] ?>)</th>
            <th style="width:70px">%</th><th style="width:70px">Grade</th>
            <th>Remarks</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $i => $st):
            $existing = $existingMarks[$st['id']] ?? null;
            $obtained = $existing ? $existing['marks_obtained'] : '';
            $remarks  = $existing ? $existing['remarks'] : '';
            $pct      = ($obtained !== '' && $currentAssessment['max_marks'] > 0)
                        ? round((float)$obtained / $currentAssessment['max_marks'] * 100, 2) : '';
            $grade    = ($pct !== '') ? _gradeInfo((float)$pct) : null;
          ?>
          <tr>
            <td class="text-muted"><?= $i+1 ?></td>
            <td><?= h($st['roll_no']) ?></td>
            <td><?= h($st['name']) ?></td>
            <td>
              <input type="number" name="marks[<?= $st['id'] ?>][marks_obtained]"
                     class="form-control form-control-sm marks-input"
                     data-max="<?= $currentAssessment['max_marks'] ?>"
                     value="<?= h($obtained) ?>"
                     min="0" max="<?= $currentAssessment['max_marks'] ?>" step="any"
                     oninput="calcRow(this)">
            </td>
            <td class="pct-cell"><?= $pct !== '' ? $pct.'%' : '—' ?></td>
            <td class="grade-cell"><?= $grade ? '<span class="grade '.$grade['class'].'">'.$grade['label'].'</span>' : '—' ?></td>
            <td>
              <input type="text" name="marks[<?= $st['id'] ?>][remarks]"
                     class="form-control form-control-sm"
                     value="<?= h($remarks) ?>" placeholder="Optional">
            </td>
            <td>
              <a href="<?= url('/portal/student/progress-report.php') ?>?student_id=<?= $st['id'] ?>"
                 class="btn btn-xs btn-outline-info" target="_blank"
                 style="font-size:.72rem;padding:2px 7px;white-space:nowrap"
                 title="Progress Report"><i class="fas fa-chart-line"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($students)): ?>
          <tr><td colspan="8" class="text-center text-muted py-3">No students found in this class.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if (!empty($students)): ?>
    <div style="padding:12px 16px;background:#f9fafb;border-top:1px solid #e5e7eb">
      <button type="submit" class="btn btn-success btn-sm">
        <i class="fas fa-save me-1"></i>Save Marks
      </button>
      <a href="?tab=marks" class="btn btn-outline-secondary btn-sm ms-2">← Back</a>
    </div>
    <?php endif; ?>
  </form>
</div>
<?php endif; // approved ?>

<?php endif; // assessment selected ?>
<?php endif; // marks tab ?>

</div><!-- .page-content -->
</div><!-- .main-area -->
</div><!-- .portal-wrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function calcRow(input) {
    const max = parseFloat(input.dataset.max) || 0;
    const val = parseFloat(input.value);
    const row = input.closest('tr');
    const pctCell   = row.querySelector('.pct-cell');
    const gradeCell = row.querySelector('.grade-cell');
    if (isNaN(val) || max === 0) {
        pctCell.textContent = '—';
        gradeCell.innerHTML = '—';
        return;
    }
    const pct = Math.round(val / max * 1000) / 10;
    pctCell.textContent = pct + '%';
    const g = getGrade(pct);
    gradeCell.innerHTML = '<span class="grade ' + g.cls + '">' + g.label + '</span>';
}
function getGrade(pct) {
    if (pct >= 90) return {label:'A+', cls:'grade-aplus'};
    if (pct >= 80) return {label:'A',  cls:'grade-a'};
    if (pct >= 70) return {label:'B',  cls:'grade-b'};
    if (pct >= 60) return {label:'C',  cls:'grade-c'};
    if (pct >= 50) return {label:'D',  cls:'grade-d'};
    return {label:'F', cls:'grade-f'};
}
</script>
</body></html>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/xlsx_writer.php';

$user = requireAuth('vp_montessori');
requirePermission('monte_result_approvals');
$db = getDB();

// ── AJAX: batch marks preview ─────────────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'marks') {
    $cId = (int)($_GET['class_id']   ?? 0);
    $sId = (int)($_GET['subject_id'] ?? 0);
    $tId = (int)($_GET['teacher_id'] ?? 0);
    $out = ['error' => true];
    if ($cId && $sId && $tId) {
        try {
            $chk = $db->prepare(
                "SELECT c.name AS class_name, s.name AS subject_name
                 FROM classes c JOIN subjects s ON s.id=?
                 WHERE c.id=? AND c.is_montessori=1"
            );
            $chk->execute([$sId, $cId]);
            $info = $chk->fetch();
            if ($info) {
                $aSt = $db->prepare(
                    "SELECT mda.id, mda.assessment_date, mda.topic
                     FROM montessori_daily_assessments mda
                     WHERE mda.class_id=? AND mda.subject_id=? AND mda.teacher_id=?
                     ORDER BY mda.assessment_date"
                );
                $aSt->execute([$cId, $sId, $tId]);
                $assessments = $aSt->fetchAll(PDO::FETCH_ASSOC);
                $rows = [];
                foreach ($assessments as $a) {
                    $eSt = $db->prepare(
                        "SELECT u.name AS student_name, st.roll_no, e.overall, e.remarks
                         FROM montessori_daily_assessment_entries e
                         JOIN students st ON e.student_id=st.id
                         JOIN users u ON st.user_id=u.id
                         WHERE e.assessment_id=?
                         ORDER BY st.roll_no, u.name"
                    );
                    $eSt->execute([$a['id']]);
                    foreach ($eSt->fetchAll(PDO::FETCH_ASSOC) as $e) {
                        $rows[] = [
                            'date'         => $a['assessment_date'],
                            'topic'        => $a['topic'],
                            'student_name' => $e['student_name'],
                            'roll_no'      => $e['roll_no'],
                            'overall'      => $e['overall'],
                            'remarks'      => $e['remarks'],
                        ];
                    }
                }
                $out = [
                    'error'        => false,
                    'class_name'   => $info['class_name'],
                    'subject_name' => $info['subject_name'],
                    'rows'         => $rows,
                ];
            }
        } catch (Exception $e) {}
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

// ── Export: batch XLSX ────────────────────────────────────────────────────────
if (isset($_GET['export']) && isset($_GET['class_id'])) {
    $cId = (int)($_GET['class_id']   ?? 0);
    $sId = (int)($_GET['subject_id'] ?? 0);
    $tId = (int)($_GET['teacher_id'] ?? 0);
    if ($cId && $sId && $tId) {
        try {
            $chk = $db->prepare(
                "SELECT c.name AS class_name, s.name AS subject_name, u.name AS teacher_name
                 FROM classes c
                 JOIN subjects s ON s.id=?
                 JOIN teachers t ON t.id=?
                 JOIN users u ON u.id=t.user_id
                 WHERE c.id=? AND c.is_montessori=1"
            );
            $chk->execute([$sId, $tId, $cId]);
            $info = $chk->fetch();
            if ($info) {
                $aSt = $db->prepare(
                    "SELECT mda.id, mda.assessment_date, mda.topic
                     FROM montessori_daily_assessments mda
                     WHERE mda.class_id=? AND mda.subject_id=? AND mda.teacher_id=?
                     ORDER BY mda.assessment_date"
                );
                $aSt->execute([$cId, $sId, $tId]);
                $assessments = $aSt->fetchAll(PDO::FETCH_ASSOC);
                $xls = new XlsxWriter('FA Marks');
                $xls->setColumnWidths([4, 28, 12, 14, 12, 12, 24]);
                $xls->freezeRows(3);
                $xls->addRow(['Bahria Model College — ' . $info['class_name']], XlsxWriter::S_TITLE);
                $xls->addRow(['Formative Assessments  |  ' . $info['subject_name'] . '  |  Teacher: ' . $info['teacher_name']], XlsxWriter::S_SUBHDR);
                $xls->addRow(['#', 'Student Name', 'Roll No.', 'Assessment Date', 'Topic', 'Overall', 'Remarks'], XlsxWriter::S_HEADER);
                $i = 1;
                foreach ($assessments as $a) {
                    $eSt = $db->prepare(
                        "SELECT u.name AS student_name, st.roll_no, e.overall, e.remarks
                         FROM montessori_daily_assessment_entries e
                         JOIN students st ON e.student_id=st.id
                         JOIN users u ON st.user_id=u.id
                         WHERE e.assessment_id=?
                         ORDER BY st.roll_no, u.name"
                    );
                    $eSt->execute([$a['id']]);
                    foreach ($eSt->fetchAll(PDO::FETCH_ASSOC) as $e) {
                        $xls->addRow([
                            $i++,
                            $e['student_name'],
                            $e['roll_no'] ?? '',
                            $a['assessment_date'],
                            $a['topic'] ?? '',
                            $e['overall'] ?? '',
                            $e['remarks'] ?? '',
                        ], ($i % 2 === 0) ? XlsxWriter::S_ALTROW : XlsxWriter::S_DATA);
                    }
                }
                $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $info['class_name'] . '_' . $info['subject_name'] . '_FA');
                $xls->download($safeName . '.xlsx');
            }
        } catch (Exception $e) {}
    }
    redirect('/portal/vp-montessori/result-approvals.php');
}

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action']     ?? '';
    $classId   = (int)($_POST['class_id']   ?? 0);
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $tab       = $_POST['tab']        ?? 'pending';

    if ($classId && $subjectId && $teacherId) {
        // Verify batch belongs to Montessori
        $chk = $db->prepare(
            "SELECT COUNT(*) FROM montessori_daily_assessments mda
             JOIN classes c ON c.id=mda.class_id
             WHERE mda.class_id=? AND mda.subject_id=? AND mda.teacher_id=? AND c.is_montessori=1"
        );
        $chk->execute([$classId, $subjectId, $teacherId]);
        $batchExists = (int)$chk->fetchColumn() > 0;

        if ($batchExists) {
            if ($action === 'approve') {
                $db->prepare(
                    "UPDATE montessori_daily_assessments
                     SET publish_status='approved', reviewed_by=?
                     WHERE class_id=? AND subject_id=? AND teacher_id=? AND publish_status='pending_review'"
                )->execute([$user['id'], $classId, $subjectId, $teacherId]);

                // Notify teacher
                try {
                    $tInfo = $db->prepare(
                        "SELECT u.id AS uid, u.name, s.name AS sname, c.name AS cname
                         FROM teachers t JOIN users u ON t.user_id=u.id
                         JOIN subjects s ON s.id=?
                         JOIN classes c ON c.id=?
                         WHERE t.id=?"
                    );
                    $tInfo->execute([$subjectId, $classId, $teacherId]);
                    $tr = $tInfo->fetch();
                    if ($tr) {
                        createNotification((int)$tr['uid'], 'fa_approved',
                            "Your Formative Assessments for {$tr['sname']} ({$tr['cname']}) have been approved by VP. They will be published soon.");
                    }
                } catch (Exception $e) {}
                logActivity($user['id'], 'monte_fa_approve', "Approved FA batch class=$classId subj=$subjectId teacher=$teacherId");
                setFlash('success', 'Formative assessments approved. You can now publish them.');

            } elseif ($action === 'request_changes') {
                $note = trim($_POST['review_note'] ?? '');
                if (!$note) {
                    setFlash('danger', 'Please provide a note explaining what changes are needed.');
                    redirect('/portal/vp-montessori/result-approvals.php?tab=' . $tab);
                }
                $db->prepare(
                    "UPDATE montessori_daily_assessments
                     SET publish_status='changes_requested', review_note=?, reviewed_by=?
                     WHERE class_id=? AND subject_id=? AND teacher_id=?
                     AND publish_status IN ('pending_review','approved')"
                )->execute([$note, $user['id'], $classId, $subjectId, $teacherId]);

                // Notify teacher
                try {
                    $tInfo = $db->prepare(
                        "SELECT u.id AS uid, u.name, s.name AS sname, c.name AS cname
                         FROM teachers t JOIN users u ON t.user_id=u.id
                         JOIN subjects s ON s.id=?
                         JOIN classes c ON c.id=?
                         WHERE t.id=?"
                    );
                    $tInfo->execute([$subjectId, $classId, $teacherId]);
                    $tr = $tInfo->fetch();
                    if ($tr) {
                        createNotification((int)$tr['uid'], 'fa_changes_requested',
                            "VP has requested changes to your Formative Assessments for {$tr['sname']} ({$tr['cname']}). Note: " . h($note));
                    }
                } catch (Exception $e) {}
                logActivity($user['id'], 'monte_fa_changes', "Changes requested FA batch class=$classId subj=$subjectId teacher=$teacherId");
                setFlash('info', 'Changes requested from teacher.');

            } elseif ($action === 'publish') {
                $db->prepare(
                    "UPDATE montessori_daily_assessments
                     SET publish_status='published', published_by=?, published_at=NOW()
                     WHERE class_id=? AND subject_id=? AND teacher_id=? AND publish_status='approved'"
                )->execute([$user['id'], $classId, $subjectId, $teacherId]);

                // Notify teacher
                try {
                    $tInfo = $db->prepare(
                        "SELECT u.id AS uid, s.name AS sname, c.name AS cname
                         FROM teachers t JOIN users u ON t.user_id=u.id
                         JOIN subjects s ON s.id=?
                         JOIN classes c ON c.id=?
                         WHERE t.id=?"
                    );
                    $tInfo->execute([$subjectId, $classId, $teacherId]);
                    $tr = $tInfo->fetch();
                    if ($tr) {
                        createNotification((int)$tr['uid'], 'fa_published',
                            "Your Formative Assessments for {$tr['sname']} ({$tr['cname']}) have been published. Students can now view their results.");
                    }
                } catch (Exception $e) {}
                logActivity($user['id'], 'monte_fa_publish', "Published FA batch class=$classId subj=$subjectId teacher=$teacherId");
                setFlash('success', 'Results published. Students can now view them.');

            } elseif ($action === 'unpublish') {
                $db->prepare(
                    "UPDATE montessori_daily_assessments
                     SET publish_status='draft', published_by=NULL, published_at=NULL, review_note=NULL
                     WHERE class_id=? AND subject_id=? AND teacher_id=? AND publish_status='published'"
                )->execute([$classId, $subjectId, $teacherId]);
                logActivity($user['id'], 'monte_fa_unpublish', "Unpublished FA batch class=$classId subj=$subjectId teacher=$teacherId");
                setFlash('warning', 'Results unpublished and moved back to Draft.');
            }
        } else {
            setFlash('error', 'Invalid batch or access denied.');
        }
    }
    redirect('/portal/vp-montessori/result-approvals.php?tab=' . $tab);
}

// ── Filters ───────────────────────────────────────────────────────────────────
$activeTab   = $_GET['tab']      ?? 'pending';
$classFilter = (int)($_GET['class_id'] ?? 0);

// ── Build status condition ─────────────────────────────────────────────────────
$statusMap = [
    'pending'   => "mda.publish_status='pending_review'",
    'approved'  => "mda.publish_status='approved'",
    'published' => "mda.publish_status='published'",
    'changes'   => "mda.publish_status='changes_requested'",
    'draft'     => "mda.publish_status='draft'",
];
$statusCond = $statusMap[$activeTab] ?? "mda.publish_status='pending_review'";

$baseWhere  = "c.is_montessori=1";
$baseParams = [];
if ($classFilter) { $baseWhere .= ' AND mda.class_id=?'; $baseParams[] = $classFilter; }

// ── Load batches (grouped by class+subject+teacher) ───────────────────────────
$batchSt = $db->prepare(
    "SELECT mda.class_id, mda.subject_id, mda.teacher_id,
            c.name AS class_name,
            s.name AS subject_name,
            u.name AS teacher_name,
            COUNT(*) AS record_count,
            MIN(mda.assessment_date) AS first_date,
            MAX(mda.assessment_date) AS last_date,
            MAX(mda.submitted_at)    AS submitted_at,
            MAX(mda.published_at)    AS published_at,
            MAX(mda.review_note)     AS review_note,
            pub.name AS published_by_name,
            rev.name AS reviewed_by_name
     FROM montessori_daily_assessments mda
     JOIN classes c ON c.id=mda.class_id
     JOIN subjects s ON s.id=mda.subject_id
     JOIN teachers t ON t.id=mda.teacher_id
     JOIN users u ON u.id=t.user_id
     LEFT JOIN users pub ON mda.published_by=pub.id
     LEFT JOIN users rev ON mda.reviewed_by=rev.id
     WHERE $baseWhere AND $statusCond
     GROUP BY mda.class_id, mda.subject_id, mda.teacher_id,
              c.name, s.name, u.name, pub.name, rev.name
     ORDER BY MAX(mda.submitted_at) DESC, MAX(mda.assessment_date) DESC"
);
$batchSt->execute($baseParams);
$batches = $batchSt->fetchAll();

// ── Tab counts ────────────────────────────────────────────────────────────────
$counts = [];
foreach ($statusMap as $tabKey => $cond) {
    try {
        $cntSt = $db->prepare(
            "SELECT COUNT(DISTINCT CONCAT(mda.class_id,'-',mda.subject_id,'-',mda.teacher_id))
             FROM montessori_daily_assessments mda
             JOIN classes c ON c.id=mda.class_id
             WHERE c.is_montessori=1 AND $cond"
        );
        $cntSt->execute();
        $counts[$tabKey] = (int)$cntSt->fetchColumn();
    } catch (Exception $e) { $counts[$tabKey] = 0; }
}

// ── Classes for filter dropdown ───────────────────────────────────────────────
try {
    $classesSt = $db->query("SELECT id, name FROM classes WHERE is_montessori=1 ORDER BY grade, section, name");
    $classes   = $classesSt->fetchAll();
} catch (Exception $e) { $classes = []; }

pageHead('FA Approvals', 'vp_montessori');
$links = getVpMontessoriLinks();
?>
<div class="portal-wrap">
<?php sidebar('vp_montessori', 'result-approvals', $links, $user); ?>
<div class="main-area">
<?php topbar('Formative Assessment Approvals', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Branding strip -->
<div class="d-flex align-items-center gap-3 mb-3 p-3"
     style="background:linear-gradient(90deg,#f0fdf4,#dcfce7);border-radius:10px;border:1px solid #86efac">
  <div style="width:40px;height:40px;border-radius:50%;background:#16a34a;color:#fff;font-size:1.1rem;
              display:flex;align-items:center;justify-content:center;flex-shrink:0">
    <i class="fas fa-check-double"></i>
  </div>
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#14532d;letter-spacing:.8px;text-transform:uppercase">
      Formative Assessment Approvals
    </div>
    <div style="font-size:.78rem;color:#475569">Montessori Campus · Bahria Model College Bin Qasim</div>
  </div>
</div>

<!-- Filter bar -->
<div class="sec-card mb-3">
  <div style="padding:12px 16px">
    <form method="GET" class="d-flex align-items-center gap-3 flex-wrap">
      <input type="hidden" name="tab" value="<?= h($activeTab) ?>">
      <label class="fw-semibold" style="font-size:.84rem;white-space:nowrap">Filter by Class:</label>
      <select name="class_id" class="form-select form-select-sm" style="max-width:220px" onchange="this.form.submit()">
        <option value="">All Classes</option>
        <?php foreach ($classes as $cl): ?>
        <option value="<?= $cl['id'] ?>" <?= $classFilter === (int)$cl['id'] ? 'selected' : '' ?>><?= h($cl['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($classFilter): ?>
      <a href="?tab=<?= h($activeTab) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
      <?php endif; ?>
      <div class="ms-auto d-flex align-items-center gap-2">
        <?php if ($activeTab === 'published' && $classFilter): ?>
        <a href="fa-export.php?class_id=<?= $classFilter ?>"
           class="btn btn-success btn-sm">
          <i class="fas fa-file-excel me-1"></i>Export FA Results
        </a>
        <?php endif; ?>
        <span style="font-size:.82rem;color:#6b7280">
          <i class="fas fa-info-circle me-1"></i>Montessori only
        </span>
      </div>
    </form>
  </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3 flex-wrap">
  <?php
  $tabDefs = [
      'pending'   => ['label' => 'Pending Review',    'icon' => 'fa-hourglass-half', 'badge' => 'bg-warning text-dark'],
      'approved'  => ['label' => 'Approved',           'icon' => 'fa-check',          'badge' => 'bg-success'],
      'published' => ['label' => 'Published',           'icon' => 'fa-check-double',   'badge' => 'bg-primary'],
      'changes'   => ['label' => 'Changes Requested',  'icon' => 'fa-edit',            'badge' => 'bg-danger'],
      'draft'     => ['label' => 'Draft',               'icon' => 'fa-file-alt',        'badge' => 'bg-secondary'],
  ];
  foreach ($tabDefs as $tKey => $tDef):
      $cnt = $counts[$tKey] ?? 0;
  ?>
  <li class="nav-item">
    <a class="nav-link <?= $activeTab===$tKey?'active':'' ?>"
       href="?tab=<?= $tKey ?><?= $classFilter ? '&class_id='.$classFilter : '' ?>"
       style="font-size:.84rem">
      <i class="fas <?= $tDef['icon'] ?> me-1"></i>
      <?= $tDef['label'] ?>
      <?php if ($cnt > 0): ?>
      <span class="badge <?= $tDef['badge'] ?> ms-1" style="font-size:.65rem"><?= $cnt ?></span>
      <?php endif; ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<!-- Batch list -->
<?php if (empty($batches)): ?>
<div class="sec-card">
  <div class="text-center text-muted py-5">
    <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
    <div class="fw-semibold">No batches in this category</div>
    <div style="font-size:.84rem;margin-top:4px">
      <?php if ($activeTab === 'pending'): ?>
        When teachers submit their formative assessments for review, they will appear here.
      <?php elseif ($activeTab === 'approved'): ?>
        Approved batches awaiting the Publish action will appear here.
      <?php else: ?>
        No assessment batches found in this status.
      <?php endif; ?>
    </div>
  </div>
</div>
<?php else: ?>

<!-- Request Changes Modal -->
<div class="modal fade" id="changesModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" id="changesForm">
        <input type="hidden" name="action" value="request_changes">
        <input type="hidden" name="class_id"   id="mc_class_id">
        <input type="hidden" name="subject_id" id="mc_subject_id">
        <input type="hidden" name="teacher_id" id="mc_teacher_id">
        <input type="hidden" name="tab" value="<?= h($activeTab) ?>">
        <div class="modal-header">
          <h5 class="modal-title" style="font-size:.95rem"><i class="fas fa-edit me-2"></i>Request Changes</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p style="font-size:.84rem" id="mc_desc"></p>
          <label class="form-label fw-semibold" style="font-size:.82rem">Feedback for Teacher <span class="text-danger">*</span></label>
          <textarea name="review_note" class="form-control" rows="4" placeholder="Explain what needs to be corrected…" required style="font-size:.84rem"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-paper-plane me-1"></i>Send Feedback</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="row g-3">
<?php foreach ($batches as $b): ?>
<div class="col-12">
  <div class="sec-card" style="overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border)">
      <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
        <div>
          <div class="fw-bold" style="font-size:.95rem">
            <i class="fas fa-book me-1 text-secondary opacity-75"></i><?= h($b['subject_name']) ?>
            <span class="text-muted fw-normal" style="font-size:.85rem">— <?= h($b['class_name']) ?></span>
          </div>
          <div style="font-size:.79rem;color:#64748b;margin-top:3px">
            <i class="fas fa-chalkboard-teacher me-1"></i><?= h($b['teacher_name']) ?>
            &nbsp;·&nbsp; <?= $b['record_count'] ?> record<?= $b['record_count']!=1?'s':'' ?>
            &nbsp;·&nbsp; <?= date('d M Y',strtotime($b['first_date'])) ?>
            <?php if ($b['first_date'] !== $b['last_date']): ?>
            – <?= date('d M Y',strtotime($b['last_date'])) ?>
            <?php endif; ?>
          </div>
          <?php if ($b['submitted_at'] && $activeTab === 'pending'): ?>
          <div style="font-size:.76rem;color:#b45309;margin-top:3px">
            <i class="fas fa-clock me-1"></i>Submitted <?= fDate($b['submitted_at']) ?>
          </div>
          <?php endif; ?>
          <?php if ($b['review_note'] && $activeTab !== 'pending'): ?>
          <div style="margin-top:6px;background:#fef2f2;border-radius:6px;padding:5px 10px;font-size:.78rem;color:#991b1b">
            <strong>VP Note:</strong> <?= h($b['review_note']) ?>
          </div>
          <?php endif; ?>
          <?php if ($b['published_at']): ?>
          <div style="margin-top:4px;font-size:.76rem;color:#059669">
            <i class="fas fa-check-double me-1"></i>Published <?= fDate($b['published_at']) ?>
            <?php if ($b['published_by_name']): ?> by <?= h($b['published_by_name']) ?><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center">
          <?php if ($activeTab === 'pending'): ?>
          <!-- Approve -->
          <form method="POST" class="d-inline">
            <input type="hidden" name="action"     value="approve">
            <input type="hidden" name="class_id"   value="<?= $b['class_id'] ?>">
            <input type="hidden" name="subject_id" value="<?= $b['subject_id'] ?>">
            <input type="hidden" name="teacher_id" value="<?= $b['teacher_id'] ?>">
            <input type="hidden" name="tab"        value="<?= h($activeTab) ?>">
            <button class="btn btn-sm btn-success" style="font-size:.8rem"
                    onclick="return confirm('Approve <?= $b['record_count'] ?> FA record(s) for <?= h(addslashes($b['subject_name'])) ?> (<?= h(addslashes($b['class_name'])) ?>)?')">
              <i class="fas fa-check me-1"></i>Approve
            </button>
          </form>
          <!-- Request Changes -->
          <button class="btn btn-sm btn-outline-danger" style="font-size:.8rem"
                  data-bs-toggle="modal" data-bs-target="#changesModal"
                  data-class-id="<?= $b['class_id'] ?>"
                  data-subject-id="<?= $b['subject_id'] ?>"
                  data-teacher-id="<?= $b['teacher_id'] ?>"
                  data-desc="Request changes for <?= h(addslashes($b['subject_name'].' — '.$b['class_name'])) ?> (<?= $b['record_count'] ?> records by <?= h(addslashes($b['teacher_name'])) ?>)">
            <i class="fas fa-edit me-1"></i>Request Changes
          </button>

          <?php elseif ($activeTab === 'approved'): ?>
          <!-- Publish -->
          <form method="POST" class="d-inline">
            <input type="hidden" name="action"     value="publish">
            <input type="hidden" name="class_id"   value="<?= $b['class_id'] ?>">
            <input type="hidden" name="subject_id" value="<?= $b['subject_id'] ?>">
            <input type="hidden" name="teacher_id" value="<?= $b['teacher_id'] ?>">
            <input type="hidden" name="tab"        value="<?= h($activeTab) ?>">
            <button class="btn btn-sm btn-primary" style="font-size:.8rem"
                    onclick="return confirm('Publish <?= $b['record_count'] ?> FA record(s) for <?= h(addslashes($b['subject_name'])) ?> (<?= h(addslashes($b['class_name'])) ?>)?\n\nStudents will be able to view their results.')">
              <i class="fas fa-paper-plane me-1"></i>Publish
            </button>
          </form>
          <!-- Request Changes -->
          <button class="btn btn-sm btn-outline-danger" style="font-size:.8rem"
                  data-bs-toggle="modal" data-bs-target="#changesModal"
                  data-class-id="<?= $b['class_id'] ?>"
                  data-subject-id="<?= $b['subject_id'] ?>"
                  data-teacher-id="<?= $b['teacher_id'] ?>"
                  data-desc="Request changes for <?= h(addslashes($b['subject_name'].' — '.$b['class_name'])) ?> (<?= $b['record_count'] ?> records by <?= h(addslashes($b['teacher_name'])) ?>)">
            <i class="fas fa-edit me-1"></i>Request Changes
          </button>

          <?php elseif ($activeTab === 'published'): ?>
          <!-- Unpublish -->
          <form method="POST" class="d-inline">
            <input type="hidden" name="action"     value="unpublish">
            <input type="hidden" name="class_id"   value="<?= $b['class_id'] ?>">
            <input type="hidden" name="subject_id" value="<?= $b['subject_id'] ?>">
            <input type="hidden" name="teacher_id" value="<?= $b['teacher_id'] ?>">
            <input type="hidden" name="tab"        value="<?= h($activeTab) ?>">
            <button class="btn btn-sm btn-outline-warning" style="font-size:.8rem"
                    onclick="return confirm('Unpublish these results? Students will no longer see them.')">
              <i class="fas fa-eye-slash me-1"></i>Unpublish
            </button>
          </form>

          <?php elseif ($activeTab === 'changes'): ?>
          <span style="font-size:.79rem;color:#991b1b;background:#fef2f2;padding:4px 12px;border-radius:20px">
            <i class="fas fa-hourglass-half me-1"></i>Awaiting Teacher Correction
          </span>

          <?php elseif ($activeTab === 'draft'): ?>
          <span style="font-size:.79rem;color:#6b7280;background:#f3f4f6;padding:4px 12px;border-radius:20px">
            <i class="fas fa-file-alt me-1"></i>Not Submitted
          </span>
          <?php endif; ?>

          <!-- View + Excel (all tabs) -->
          <button class="btn btn-sm btn-outline-info view-fa-btn"
                  data-class-id="<?= $b['class_id'] ?>"
                  data-subject-id="<?= $b['subject_id'] ?>"
                  data-teacher-id="<?= $b['teacher_id'] ?>"
                  data-title="<?= h($b['subject_name']) ?> — <?= h($b['class_name']) ?>"
                  style="font-size:.75rem;padding:3px 9px" title="View FA records">
            <i class="fas fa-eye me-1"></i>View
          </button>
          <a href="?export=1&class_id=<?= $b['class_id'] ?>&subject_id=<?= $b['subject_id'] ?>&teacher_id=<?= $b['teacher_id'] ?>&tab=<?= h($activeTab) ?><?= $classFilter ? '&class_filter='.$classFilter : '' ?>"
             class="btn btn-sm btn-outline-success"
             style="font-size:.75rem;padding:3px 9px" title="Download FA marks as Excel">
            <i class="fas fa-file-excel"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('changesModal').addEventListener('show.bs.modal', function(e) {
    var btn = e.relatedTarget;
    document.getElementById('mc_class_id').value   = btn.dataset.classId;
    document.getElementById('mc_subject_id').value = btn.dataset.subjectId;
    document.getElementById('mc_teacher_id').value = btn.dataset.teacherId;
    document.getElementById('mc_desc').textContent = btn.dataset.desc;
    this.querySelector('textarea[name="review_note"]').value = '';
});
</script>

<!-- View FA Modal -->
<div class="modal fade" id="viewFaModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" style="font-size:.95rem" id="viewFaModalTitle">
          <i class="fas fa-eye me-2"></i>FA Records
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="viewFaModalBody" style="font-size:.84rem">
        <div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
      </div>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.view-fa-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var cId  = this.dataset.classId;
        var sId  = this.dataset.subjectId;
        var tId  = this.dataset.teacherId;
        var title = this.dataset.title;
        document.getElementById('viewFaModalTitle').innerHTML =
            '<i class="fas fa-eye me-2"></i>' + title;
        document.getElementById('viewFaModalBody').innerHTML =
            '<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
        var modal = new bootstrap.Modal(document.getElementById('viewFaModal'));
        modal.show();
        fetch('?ajax=marks&class_id=' + cId + '&subject_id=' + sId + '&teacher_id=' + tId)
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.error || !d.rows || !d.rows.length) {
                    document.getElementById('viewFaModalBody').innerHTML =
                        '<div class="text-center text-muted py-4">No records found.</div>';
                    return;
                }
                var html = '<div class="table-responsive"><table class="table table-sm table-bordered align-middle">' +
                    '<thead class="table-light"><tr>' +
                    '<th>#</th><th>Student</th><th>Roll No.</th>' +
                    '<th>Date</th><th>Topic</th><th>Overall</th><th>Remarks</th>' +
                    '</tr></thead><tbody>';
                d.rows.forEach(function(r, i) {
                    html += '<tr>' +
                        '<td>' + (i+1) + '</td>' +
                        '<td>' + (r.student_name||'') + '</td>' +
                        '<td>' + (r.roll_no||'') + '</td>' +
                        '<td>' + (r.date||'') + '</td>' +
                        '<td>' + (r.topic||'') + '</td>' +
                        '<td class="text-center fw-semibold">' + (r.overall||'') + '</td>' +
                        '<td>' + (r.remarks||'') + '</td>' +
                        '</tr>';
                });
                html += '</tbody></table></div>';
                document.getElementById('viewFaModalBody').innerHTML = html;
            })
            .catch(function() {
                document.getElementById('viewFaModalBody').innerHTML =
                    '<div class="text-danger text-center py-4">Failed to load records.</div>';
            });
    });
});
</script>
</body></html>

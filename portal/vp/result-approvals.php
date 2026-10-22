<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/xlsx_writer.php';

$user = requireAuth('vp_main');
requirePermission('vp_publish_results');
$db = getDB();

// ── AJAX: marks preview ───────────────────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'marks') {
    $aId = (int)($_GET['id'] ?? 0);
    $out = ['error' => true];
    if ($aId) {
        try {
            $aChk = $db->prepare(
                "SELECT a.id, a.title, a.name AS a_name, a.max_marks,
                        c.name AS class_name, s.name AS subject_name
                 FROM assessments a
                 JOIN classes c ON a.class_id=c.id
                 JOIN subjects s ON a.subject_id=s.id
                 WHERE a.id=? AND COALESCE(c.wing,'main')='main'"
            );
            $aChk->execute([$aId]);
            $info = $aChk->fetch();
            if ($info) {
                $mSt = $db->prepare(
                    "SELECT u.name AS student_name, st.roll_no, m.marks_obtained
                     FROM marks m
                     JOIN students st ON m.student_id=st.id
                     JOIN users u ON st.user_id=u.id
                     WHERE m.assessment_id=?
                     ORDER BY st.roll_no, u.name"
                );
                $mSt->execute([$aId]);
                $out = [
                    'error'        => false,
                    'title'        => $info['title'] ?: $info['a_name'],
                    'class_name'   => $info['class_name'],
                    'subject_name' => $info['subject_name'],
                    'max_marks'    => (float)$info['max_marks'],
                    'students'     => $mSt->fetchAll(PDO::FETCH_ASSOC),
                ];
            }
        } catch (Exception $e) {}
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

// ── Export: single assessment XLSX ────────────────────────────────────────────
if (isset($_GET['export']) && ctype_digit((string)($_GET['export'] ?? ''))) {
    $aId = (int)$_GET['export'];
    try {
        $aChk = $db->prepare(
            "SELECT a.id, a.title, a.name AS a_name, a.max_marks, a.date,
                    c.name AS class_name, s.name AS subject_name
             FROM assessments a
             JOIN classes c ON a.class_id=c.id
             JOIN subjects s ON a.subject_id=s.id
             WHERE a.id=? AND COALESCE(c.wing,'main')='main'"
        );
        $aChk->execute([$aId]);
        $info = $aChk->fetch();
        if ($info) {
            $mSt = $db->prepare(
                "SELECT u.name AS student_name, st.roll_no, m.marks_obtained
                 FROM marks m
                 JOIN students st ON m.student_id=st.id
                 JOIN users u ON st.user_id=u.id
                 WHERE m.assessment_id=?
                 ORDER BY st.roll_no, u.name"
            );
            $mSt->execute([$aId]);
            $marks = $mSt->fetchAll(PDO::FETCH_ASSOC);

            $displayTitle = $info['title'] ?: $info['a_name'];
            $xls = new XlsxWriter('Marks');
            $xls->setColumnWidths([4, 30, 12, 14, 10, 12]);
            $xls->freezeRows(3);
            $xls->addRow(['Bahria Model College — ' . $info['class_name']], XlsxWriter::S_TITLE);
            $xls->addRow([$displayTitle . '  |  ' . $info['subject_name'] . '  |  Max: ' . $info['max_marks'] . '  |  Date: ' . ($info['date'] ?: '—')], XlsxWriter::S_SUBHDR);
            $xls->addRow(['#', 'Student Name', 'Roll No.', 'Marks Obtained', 'Max Marks', 'Percentage (%)'], XlsxWriter::S_HEADER);
            $i = 1;
            $total = 0;
            foreach ($marks as $m) {
                $pct = $info['max_marks'] > 0 ? round(($m['marks_obtained'] / $info['max_marks']) * 100, 2) : 0;
                $total += (float)$m['marks_obtained'];
                $xls->addRow([$i++, $m['student_name'], $m['roll_no'] ?? '', $m['marks_obtained'], $info['max_marks'], $pct], ($i % 2 === 0) ? XlsxWriter::S_ALTROW : XlsxWriter::S_DATA);
            }
            if (!empty($marks)) {
                $avg    = round($total / count($marks), 2);
                $avgPct = $info['max_marks'] > 0 ? round(($avg / $info['max_marks']) * 100, 2) : 0;
                $xls->addRow(['', 'Class Average', '', $avg, $info['max_marks'], $avgPct], XlsxWriter::S_FOOTER);
            } else {
                $xls->addRow(['', 'No marks entered yet.', '', '', '', ''], XlsxWriter::S_DATA);
            }
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $info['class_name'] . '_' . $displayTitle);
            $xls->download($safeName . '.xlsx');
        }
    } catch (Exception $e) {}
    redirect('/portal/vp/result-approvals.php');
}

// ── Helper: get teacher user_id for a given assessment ────────────────────────
function _getAssessmentTeacher(PDO $db, int $aId): array {
    try {
        $st = $db->prepare(
            "SELECT t.user_id, u.name AS teacher_name, a.title, a.name AS a_name
             FROM assessments a
             JOIN teachers t ON a.teacher_id=t.id
             JOIN users u ON t.user_id=u.id
             WHERE a.id=?"
        );
        $st->execute([$aId]);
        return $st->fetch() ?: [];
    } catch (Exception $e) { return []; }
}

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $aId    = (int)($_POST['assessment_id'] ?? 0);
    $tab    = $_POST['tab'] ?? 'pending';

    // Verify assessment belongs to main campus
    $chk = $db->prepare(
        "SELECT a.id, a.publish_status, a.title, a.name AS a_name
         FROM assessments a
         JOIN classes c ON a.class_id=c.id
         WHERE a.id=? AND COALESCE(c.wing,'main')='main'"
    );
    $chk->execute([$aId]);
    $asm = $chk->fetch();

    if ($asm) {
        $title = $asm['title'] ?: $asm['a_name'];

        if ($action === 'approve' && $asm['publish_status'] === 'pending_review') {
            $db->prepare(
                "UPDATE assessments SET publish_status='approved', reviewed_by=? WHERE id=?"
            )->execute([$user['id'], $aId]);
            logActivity($user['id'], 'result_approve', "Approved assessment #$aId: $title");
            // Notify teacher
            try {
                $t = _getAssessmentTeacher($db, $aId);
                if (!empty($t['user_id'])) {
                    createNotification((int)$t['user_id'], 'result_approved',
                        "Your assessment result \"" . h($title) . "\" has been approved by VP. It will be published soon.", $aId, 'assessment');
                }
            } catch (Exception $e) {}
            setFlash('success', "Assessment \"" . h($title) . "\" approved. You can now publish it.");

        } elseif ($action === 'request_changes') {
            $note = trim($_POST['review_note'] ?? '');
            if (!$note) {
                setFlash('danger', 'Please provide a note explaining what changes are needed.');
                redirect('/portal/vp/result-approvals.php?tab=' . $tab);
            }
            $db->prepare(
                "UPDATE assessments SET publish_status='changes_requested', review_note=?, reviewed_by=? WHERE id=?"
            )->execute([$note, $user['id'], $aId]);
            logActivity($user['id'], 'result_changes_requested', "Changes requested for assessment #$aId");
            // Notify teacher
            try {
                $t = _getAssessmentTeacher($db, $aId);
                if (!empty($t['user_id'])) {
                    createNotification((int)$t['user_id'], 'result_changes_requested',
                        "VP has requested changes to your assessment result \"" . h($title) . "\". Note: " . h($note), $aId, 'assessment');
                }
            } catch (Exception $e) {}
            setFlash('info', "Changes requested from teacher for \"" . h($title) . "\".");

        } elseif ($action === 'publish' && $asm['publish_status'] === 'approved') {
            $db->prepare(
                "UPDATE assessments SET publish_status='published', published_by=?, published_at=NOW() WHERE id=?"
            )->execute([$user['id'], $aId]);
            logActivity($user['id'], 'result_publish', "Published assessment #$aId: $title");
            // Notify teacher
            try {
                $t = _getAssessmentTeacher($db, $aId);
                if (!empty($t['user_id'])) {
                    createNotification((int)$t['user_id'], 'result_published',
                        "Your assessment result \"" . h($title) . "\" has been published. Students can now see their results.", $aId, 'assessment');
                }
            } catch (Exception $e) {}
            setFlash('success', "Results published for \"" . h($title) . "\". Students can now view them.");

        } elseif ($action === 'unpublish') {
            $db->prepare(
                "UPDATE assessments SET publish_status='draft', published_by=NULL, published_at=NULL, review_note=NULL WHERE id=?"
            )->execute([$aId]);
            logActivity($user['id'], 'result_unpublish', "Unpublished assessment #$aId: $title");
            setFlash('warning', "Results unpublished for \"" . h($title) . "\" — moved back to Draft.");

        } elseif ($action === 'save_vp_remarks') {
            $vpRem = trim((string)($_POST['vp_remarks'] ?? ''));
            try {
                $db->prepare("UPDATE assessments SET vp_remarks=? WHERE id=?")
                   ->execute([$vpRem !== '' ? $vpRem : null, $aId]);
                logActivity($user['id'], 'vp_remarks_save', "Saved VP remarks for assessment #$aId");
                setFlash('success', $vpRem !== ''
                    ? "VP remarks saved for \"" . h($title) . "\" — will appear on the report card."
                    : "VP remarks cleared for \"" . h($title) . "\".");
            } catch (Exception $e) {
                setFlash('danger', 'Could not save remarks: ' . $e->getMessage() . ' (Run migrations/report_card_vp_remarks.sql)');
            }
        }
    }
    $cfPost = (int)($_POST['class_filter'] ?? 0);
    redirect('/portal/vp/result-approvals.php?tab=' . $tab . ($cfPost ? '&class_id=' . $cfPost : ''));
}

// ── Filters ───────────────────────────────────────────────────────────────────
$activeTab   = $_GET['tab']      ?? 'pending';
$classFilter = (int)($_GET['class_id'] ?? 0);

// ── Load data per tab ─────────────────────────────────────────────────────────
$baseWhere  = "COALESCE(c.wing,'main')='main'";
$baseParams = [];
if ($classFilter) { $baseWhere .= ' AND a.class_id=?'; $baseParams[] = $classFilter; }

$statusMap = [
    'pending'  => "a.publish_status='pending_review'",
    'approved' => "a.publish_status='approved'",
    'published'=> "a.publish_status='published'",
    'changes'  => "a.publish_status='changes_requested'",
    'draft'    => "a.publish_status='draft'",
];
$statusCond = $statusMap[$activeTab] ?? "a.publish_status='pending_review'";

// Does the vp_remarks column exist? (migration guard)
$hasVpRemarks = false;
try {
    $db->query("SELECT vp_remarks FROM assessments LIMIT 0");
    $hasVpRemarks = true;
} catch (Exception $e) {}
$vpRemCol = $hasVpRemarks ? 'a.vp_remarks' : 'NULL AS vp_remarks';

$st = $db->prepare(
    "SELECT a.id, a.class_id, a.title, a.name AS a_name, a.type, a.max_marks, a.weight, a.date,
            a.publish_status, a.submitted_at, a.review_note, a.published_at, $vpRemCol,
            c.name AS class_name, s.name AS subject_name,
            u.name AS teacher_name,
            pub.name AS published_by_name,
            rev.name AS reviewed_by_name,
            (SELECT COUNT(*) FROM marks m WHERE m.assessment_id=a.id) AS marks_count
     FROM assessments a
     JOIN classes c ON a.class_id=c.id
     JOIN subjects s ON a.subject_id=s.id
     JOIN teachers t ON a.teacher_id=t.id
     JOIN users u ON t.user_id=u.id
     LEFT JOIN users pub ON a.published_by=pub.id
     LEFT JOIN users rev ON a.reviewed_by=rev.id
     WHERE $baseWhere AND $statusCond
     ORDER BY a.submitted_at DESC, a.date DESC, a.id DESC"
);
$st->execute($baseParams);
$assessments = $st->fetchAll();

// Tab counts
$counts = [];
foreach ($statusMap as $tabKey => $cond) {
    try {
        $cntSt = $db->prepare(
            "SELECT COUNT(*) FROM assessments a JOIN classes c ON a.class_id=c.id
             WHERE COALESCE(c.wing,'main')='main' AND $cond"
        );
        $cntSt->execute();
        $counts[$tabKey] = (int)$cntSt->fetchColumn();
    } catch (Exception $e) { $counts[$tabKey] = 0; }
}

// Classes for filter dropdown
try {
    $classesSt = $db->query("SELECT id, name FROM classes WHERE COALESCE(wing,'main')='main' ORDER BY grade, section, name");
    $classes   = $classesSt->fetchAll();
} catch (Exception $e) { $classes = []; }

// ── Page render ───────────────────────────────────────────────────────────────
pageHead('Result Approvals', 'vp_main');
$links = getVpLinks();
?>
<div class="portal-wrap">
<?php sidebar('vp_main', 'result-approvals', $links, $user); ?>
<div class="main-area">
<?php topbar('Result Approvals & Publishing', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Filter bar -->
<div class="sec-card mb-3">
  <div style="padding:12px 16px">
    <form method="GET" class="d-flex align-items-center gap-3 flex-wrap">
      <input type="hidden" name="tab" value="<?= h($activeTab) ?>">
      <label class="fw-semibold" style="font-size:.84rem;white-space:nowrap">Filter by Class:</label>
      <select name="class_id" class="form-select form-select-sm" style="max-width:220px" onchange="this.form.submit()">
        <option value="">All Classes</option>
        <?php foreach ($classes as $cl): ?>
        <option value="<?= $cl['id'] ?>" <?= $classFilter === $cl['id'] ? 'selected' : '' ?>><?= h($cl['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($classFilter): ?>
      <a href="?tab=<?= h($activeTab) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
      <?php endif; ?>
      <div class="ms-auto d-flex align-items-center gap-2">
        <?php if ($activeTab === 'published' && $classFilter): ?>
        <a href="result-print.php?class_id=<?= $classFilter ?>" target="_blank"
           class="btn btn-outline-primary btn-sm">
          <i class="fas fa-print me-1"></i>Print / PDF
        </a>
        <a href="result-export.php?class_id=<?= $classFilter ?>"
           class="btn btn-success btn-sm">
          <i class="fas fa-file-excel me-1"></i>Excel
        </a>
        <a href="result-word.php?class_id=<?= $classFilter ?>"
           class="btn btn-info text-white btn-sm">
          <i class="fas fa-file-word me-1"></i>Word
        </a>
        <?php elseif ($activeTab === 'published'): ?>
        <a href="result-export.php" class="btn btn-success btn-sm">
          <i class="fas fa-file-excel me-1"></i>Download Excel
        </a>
        <?php endif; ?>
        <span style="font-size:.82rem;color:#6b7280">
          <i class="fas fa-info-circle me-1"></i>Main Campus only
        </span>
      </div>
    </form>
  </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3">
  <?php
  $tabDefs = [
      'pending'   => ['label' => 'Pending Review', 'icon' => 'fa-hourglass-half',   'badge' => 'bg-warning text-dark'],
      'approved'  => ['label' => 'Approved',        'icon' => 'fa-check',            'badge' => 'bg-success'],
      'published' => ['label' => 'Published',        'icon' => 'fa-check-double',     'badge' => 'bg-primary'],
      'changes'   => ['label' => 'Changes Requested','icon' => 'fa-edit',            'badge' => 'bg-danger'],
      'draft'     => ['label' => 'Draft',            'icon' => 'fa-file-alt',         'badge' => 'bg-secondary'],
  ];
  foreach ($tabDefs as $tKey => $tDef):
      $cnt = $counts[$tKey] ?? 0;
  ?>
  <li class="nav-item">
    <a class="nav-link <?= $activeTab===$tKey?'active':'' ?>"
       href="?tab=<?= $tKey ?><?= $classFilter ? '&class_id='.$classFilter : '' ?>">
      <i class="fas <?= $tDef['icon'] ?> me-1"></i>
      <?= $tDef['label'] ?>
      <?php if ($cnt > 0): ?>
      <span class="badge <?= $tDef['badge'] ?> ms-1" style="font-size:.65rem"><?= $cnt ?></span>
      <?php endif; ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<!-- Assessments list -->
<?php if (empty($assessments)): ?>
<div class="sec-card">
  <div class="sec-body text-center text-muted py-5">
    <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
    <div class="fw-semibold">No assessments in this category</div>
    <div style="font-size:.84rem;margin-top:4px">
      <?php if ($activeTab === 'pending'): ?>
        When teachers submit their results for review, they will appear here.
      <?php elseif ($activeTab === 'approved'): ?>
        Approved assessments awaiting the Publish action will appear here.
      <?php else: ?>
        No assessments found in this status.
      <?php endif; ?>
    </div>
  </div>
</div>
<?php else: ?>

<div class="sec-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr>
          <th>Assessment</th>
          <th>Class</th>
          <th>Subject</th>
          <th>Teacher</th>
          <th class="text-center">Max</th>
          <th class="text-center">Marks</th>
          <th>Date</th>
          <th>Submitted</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($assessments as $a):
          $displayTitle = $a['title'] ?: $a['a_name'];
        ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= h($displayTitle) ?></div>
            <span class="badge bg-secondary" style="font-size:.7rem"><?= h($a['type']) ?></span>
            <?php if ($a['review_note']): ?>
            <div style="margin-top:4px;background:#fef2f2;border-radius:4px;padding:4px 8px;font-size:.78rem;color:#991b1b">
              <strong>VP Note:</strong> <?= h($a['review_note']) ?>
            </div>
            <?php endif; ?>
            <?php if ($a['published_at']): ?>
            <div style="margin-top:4px;font-size:.76rem;color:#059669">
              <i class="fas fa-check-double me-1"></i>Published <?= fDate($a['published_at']) ?>
              <?php if ($a['published_by_name']): ?> by <?= h($a['published_by_name']) ?><?php endif; ?>
            </div>
            <?php endif; ?>
          </td>
          <td><?= h($a['class_name']) ?></td>
          <td><?= h($a['subject_name']) ?></td>
          <td style="font-size:.82rem"><?= h($a['teacher_name']) ?></td>
          <td class="text-center"><?= $a['max_marks'] ?></td>
          <td class="text-center">
            <span class="badge <?= $a['marks_count'] > 0 ? 'bg-info text-dark' : 'bg-light text-muted' ?>" style="font-size:.72rem">
              <?= $a['marks_count'] ?> entered
            </span>
          </td>
          <td style="white-space:nowrap;font-size:.82rem"><?= fDate($a['date']) ?></td>
          <td style="white-space:nowrap;font-size:.8rem;color:#6b7280">
            <?= $a['submitted_at'] ? fDate($a['submitted_at']) : '—' ?>
          </td>
          <td style="white-space:nowrap">

            <?php if ($a['publish_status'] === 'pending_review'): ?>
            <!-- Approve -->
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="assessment_id" value="<?= $a['id'] ?>">
              <input type="hidden" name="tab" value="<?= h($activeTab) ?>">
              <button class="btn btn-xs btn-success" style="font-size:.75rem;padding:3px 9px"
                      onclick="return confirm('Approve this assessment result?')">
                <i class="fas fa-check me-1"></i>Approve
              </button>
            </form>
            <!-- Request Changes -->
            <button class="btn btn-xs btn-warning" style="font-size:.75rem;padding:3px 9px"
                    data-bs-toggle="modal" data-bs-target="#changeModal<?= $a['id'] ?>">
              <i class="fas fa-edit me-1"></i>Request Changes
            </button>
            <?php endif; ?>

            <?php if ($a['publish_status'] === 'approved'): ?>
            <!-- Publish -->
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="publish">
              <input type="hidden" name="assessment_id" value="<?= $a['id'] ?>">
              <input type="hidden" name="tab" value="<?= h($activeTab) ?>">
              <button class="btn btn-xs btn-primary" style="font-size:.75rem;padding:3px 9px"
                      onclick="return confirm('Publish this assessment? Students will be able to see their results.')">
                <i class="fas fa-globe me-1"></i>Publish
              </button>
            </form>
            <!-- Request Changes from approved state -->
            <button class="btn btn-xs btn-outline-warning" style="font-size:.75rem;padding:3px 9px"
                    data-bs-toggle="modal" data-bs-target="#changeModal<?= $a['id'] ?>">
              <i class="fas fa-edit"></i>
            </button>
            <?php endif; ?>

            <?php if ($a['publish_status'] === 'published'): ?>
            <a href="result-print.php?class_id=<?= (int)$a['class_id'] ?>" target="_blank"
               class="btn btn-xs btn-outline-primary" style="font-size:.75rem;padding:3px 9px"
               title="Print / Save as PDF">
              <i class="fas fa-print"></i>
            </a>
            <a href="result-export.php?class_id=<?= (int)$a['class_id'] ?>"
               class="btn btn-xs btn-success" style="font-size:.75rem;padding:3px 9px"
               title="Download Excel">
              <i class="fas fa-file-excel me-1"></i>Excel
            </a>
            <a href="result-word.php?class_id=<?= (int)$a['class_id'] ?>"
               class="btn btn-xs btn-info text-white" style="font-size:.75rem;padding:3px 9px"
               title="Download Word">
              <i class="fas fa-file-word"></i>
            </a>
            <!-- Unpublish (emergency rollback) -->
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="unpublish">
              <input type="hidden" name="assessment_id" value="<?= $a['id'] ?>">
              <input type="hidden" name="tab" value="<?= h($activeTab) ?>">
              <button class="btn btn-xs btn-outline-danger" style="font-size:.75rem;padding:3px 9px"
                      onclick="return confirm('Unpublish this result? Students will no longer be able to see it.')">
                <i class="fas fa-eye-slash me-1"></i>Unpublish
              </button>
            </form>
            <?php endif; ?>

            <!-- View Marks -->
            <button class="btn btn-xs btn-outline-info view-marks-btn"
                    data-id="<?= $a['id'] ?>"
                    data-title="<?= h($displayTitle) ?> — <?= h($a['class_name']) ?> / <?= h($a['subject_name']) ?>"
                    style="font-size:.75rem;padding:3px 9px" title="View student marks">
              <i class="fas fa-eye me-1"></i>View
            </button>
            <a href="?export=<?= $a['id'] ?>&tab=<?= h($activeTab) ?><?= $classFilter ? '&class_id='.$classFilter : '' ?>"
               class="btn btn-xs btn-outline-success"
               style="font-size:.75rem;padding:3px 9px" title="Download marks as Excel">
              <i class="fas fa-file-excel"></i>
            </a>
            <!-- VP Remarks -->
            <button class="btn btn-xs <?= !empty($a['vp_remarks']) ? 'btn-primary' : 'btn-outline-primary' ?>"
                    style="font-size:.75rem;padding:3px 9px"
                    title="<?= !empty($a['vp_remarks']) ? 'Edit VP remarks (set)' : 'Add VP remarks for report card' ?>"
                    data-bs-toggle="modal" data-bs-target="#vpRemModal<?= $a['id'] ?>">
              <i class="fas fa-stamp"></i>
            </button>

          </td>
        </tr>

        <!-- VP Remarks Modal -->
        <div class="modal fade" id="vpRemModal<?= $a['id'] ?>" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <form method="POST">
                <input type="hidden" name="action" value="save_vp_remarks">
                <input type="hidden" name="assessment_id" value="<?= $a['id'] ?>">
                <input type="hidden" name="tab" value="<?= h($activeTab) ?>">
                <div class="modal-header">
                  <h5 class="modal-title" style="font-size:.95rem">
                    <i class="fas fa-stamp me-2"></i>Vice Principal Remarks
                  </h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <p style="font-size:.85rem;color:#374151">
                    <strong><?= h($displayTitle) ?></strong> — <?= h($a['class_name']) ?> / <?= h($a['subject_name']) ?>
                    <span class="text-muted" style="font-size:.78rem">· type: <?= h($a['type']) ?></span>
                  </p>
                  <label class="form-label fw-semibold" style="font-size:.84rem">Remarks (will appear on student report cards for this assessment type)</label>
                  <textarea name="vp_remarks" class="form-control form-control-sm" rows="4"
                            placeholder="e.g. Overall improvement observed. Keep up the good work."
                            style="font-size:.84rem"><?= h($a['vp_remarks'] ?? '') ?></textarea>
                  <div class="form-text" style="font-size:.75rem">Leave empty and save to clear.</div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-save me-1"></i>Save Remarks
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Request Changes Modal -->
        <div class="modal fade" id="changeModal<?= $a['id'] ?>" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <form method="POST">
                <input type="hidden" name="action" value="request_changes">
                <input type="hidden" name="assessment_id" value="<?= $a['id'] ?>">
                <input type="hidden" name="tab" value="<?= h($activeTab) ?>">
                <div class="modal-header">
                  <h5 class="modal-title" style="font-size:.95rem">
                    <i class="fas fa-edit me-2"></i>Request Changes
                  </h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <p style="font-size:.85rem;color:#374151">
                    <strong><?= h($displayTitle) ?></strong> — <?= h($a['class_name']) ?> / <?= h($a['subject_name']) ?>
                  </p>
                  <label class="form-label fw-semibold" style="font-size:.84rem">Note to Teacher <span class="text-danger">*</span></label>
                  <textarea name="review_note" class="form-control form-control-sm" rows="3"
                            placeholder="Describe what needs to be corrected…" required style="font-size:.84rem"></textarea>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-sm btn-warning">
                    <i class="fas fa-paper-plane me-1"></i>Send to Teacher
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

<!-- Info card -->
<div class="sec-card mt-3" style="border:1px solid #e0f2fe;background:#f0f9ff">
  <div style="padding:14px 16px">
    <h6 class="fw-semibold mb-2" style="font-size:.85rem;color:#0369a1">
      <i class="fas fa-info-circle me-2"></i>Result Publishing Workflow
    </h6>
    <div style="font-size:.82rem;color:#0c4a6e;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px">
      <div><span class="badge bg-secondary me-1">Draft</span>Teacher created, not submitted</div>
      <div><span class="badge bg-warning text-dark me-1">Pending Review</span>Teacher submitted for VP review</div>
      <div><span class="badge bg-success me-1">Approved</span>VP approved — ready to publish</div>
      <div><span class="badge bg-danger me-1">Changes Requested</span>Teacher must edit and re-submit</div>
      <div><span class="badge bg-primary me-1">Published</span>Visible to students and parents</div>
    </div>
  </div>
</div>

</div><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /portal-wrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- View Marks Modal -->
<div class="modal fade" id="viewMarksModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-semibold" id="viewMarksTitle" style="font-size:.92rem"></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" id="viewMarksBody">
        <div class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary"></div></div>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  function esc(t){return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
  document.querySelectorAll('.view-marks-btn').forEach(function(btn){
    btn.addEventListener('click',function(){
      document.getElementById('viewMarksTitle').textContent = btn.dataset.title;
      document.getElementById('viewMarksBody').innerHTML =
        '<div class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
      new bootstrap.Modal(document.getElementById('viewMarksModal')).show();
      fetch('?ajax=marks&id='+btn.dataset.id)
        .then(function(r){return r.json();})
        .then(function(d){
          if(d.error){
            document.getElementById('viewMarksBody').innerHTML='<p class="text-center text-danger py-4">Failed to load marks.</p>';
            return;
          }
          var rows='', total=0;
          (d.students||[]).forEach(function(s,i){
            var pct=d.max_marks>0?((s.marks_obtained/d.max_marks)*100).toFixed(2)+'%':'—';
            total+=parseFloat(s.marks_obtained)||0;
            rows+='<tr><td class="text-muted ps-3" style="font-size:.78rem">'+(i+1)+'</td>'
              +'<td>'+esc(s.student_name)+'</td>'
              +'<td class="text-muted" style="font-size:.82rem">'+(s.roll_no||'—')+'</td>'
              +'<td class="text-center fw-semibold">'+s.marks_obtained+'</td>'
              +'<td class="text-center text-muted">'+d.max_marks+'</td>'
              +'<td class="text-center">'+pct+'</td></tr>';
          });
          var n=d.students.length, avg=n?(total/n).toFixed(2):'—';
          var avgPct=d.max_marks>0&&n?((total/n/d.max_marks)*100).toFixed(2)+'%':'—';
          var html='<div class="px-3 pt-3 pb-1 border-bottom" style="font-size:.82rem">'
            +'<strong>'+esc(d.class_name)+'</strong> &nbsp;·&nbsp; '+esc(d.subject_name)
            +' &nbsp;·&nbsp; Max: <strong>'+d.max_marks+'</strong>'
            +' &nbsp;·&nbsp; Students with marks: <strong>'+n+'</strong></div>';
          if(!n){
            html+='<p class="text-center text-muted py-4">No marks entered yet.</p>';
          } else {
            html+='<div class="table-responsive"><table class="table table-sm table-hover mb-0" style="font-size:.83rem">'
              +'<thead class="table-light"><tr><th class="ps-3" style="width:36px">#</th>'
              +'<th>Student</th><th>Roll No.</th>'
              +'<th class="text-center">Obtained</th><th class="text-center">Max</th><th class="text-center">%</th>'
              +'</tr></thead><tbody>'+rows
              +'</tbody><tfoot class="table-secondary fw-semibold">'
              +'<tr><td colspan="3" class="text-end pe-3" style="font-size:.79rem">Class Average</td>'
              +'<td class="text-center">'+avg+'</td>'
              +'<td class="text-center">'+d.max_marks+'</td>'
              +'<td class="text-center">'+avgPct+'</td>'
              +'</tr></tfoot></table></div>';
          }
          document.getElementById('viewMarksBody').innerHTML=html;
        })
        .catch(function(){
          document.getElementById('viewMarksBody').innerHTML='<p class="text-center text-danger py-4">Network error.</p>';
        });
    });
  });
})();
</script>
</body></html>

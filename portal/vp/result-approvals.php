<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

$user = requireAuth('vp_main');
requirePermission('vp_publish_results');
$db = getDB();

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

$st = $db->prepare(
    "SELECT a.id, a.class_id, a.title, a.name AS a_name, a.type, a.max_marks, a.weight, a.date,
            a.publish_status, a.submitted_at, a.review_note, a.published_at,
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
        <?php if ($activeTab === 'published'): ?>
        <a href="result-export.php<?= $classFilter ? '?class_id='.$classFilter : '' ?>"
           class="btn btn-success btn-sm">
          <i class="fas fa-file-excel me-1"></i>Download Excel<?= $classFilter ? ' (this class)' : '' ?>
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
            <!-- Download Excel for this class -->
            <a href="result-export.php?class_id=<?= (int)$a['class_id'] ?>"
               class="btn btn-xs btn-success" style="font-size:.75rem;padding:3px 9px"
               title="Download full class results as Excel">
              <i class="fas fa-file-excel me-1"></i>Excel
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

          </td>
        </tr>

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
</body></html>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('student_affairs');
requirePermission('sa_admissions');
$db   = getDB();

// Column availability checks
$migrationApplied = false;
try { $db->query('SELECT assessment_data FROM admission_requests LIMIT 0'); $migrationApplied = true; } catch (Exception $e) {}
$enrollColExists = false;
try { $db->query('SELECT enrolled_student_id FROM admission_requests LIMIT 0'); $enrollColExists = true; } catch (Exception $e) {}

// Q labels for read-only view
$q1Items = [
    'dyslexia'   => 'Dyslexia',
    'add_adhd'   => 'ADD / ADHD',
    'asd'        => 'Autism Spectrum Disorder (ASD)',
    'dyspraxia'  => 'Dyspraxia / DCD',
    'speech'     => 'Speech / Language Disorder',
    'other'      => 'Other (specify)',
];
$q2Items = [
    'long_term_medical'   => ['label' => 'Long-term medical condition',                       'specify' => true],
    'physical_disability' => ['label' => 'Physical disability',                                'specify' => true],
    'bereavement'         => ['label' => 'Recent bereavement / personal trauma',               'specify' => false],
    'other_health'        => ['label' => 'Other significant health / behavioural condition',   'specify' => true],
];
$q3Items = [
    'edu_psychologist'   => ['label' => 'Assessment by an Educational Psychologist',            'specify' => false],
    'other_professional' => ['label' => 'Assessment by another professional',                   'specify' => true],
    'anxiety_depression' => ['label' => 'History of anxiety / depression',                      'specify' => false],
    'counseling'         => ['label' => 'Currently receiving counseling / psychotherapy',       'specify' => false],
    'self_harm'          => ['label' => 'History of self-harm',                                  'specify' => false],
    'medication'         => ['label' => 'Currently on prescribed medication',                    'specify' => true],
];
$q4Labels = [
    'standardized_testing' => 'Scored highly on standardized tests',
    'gifted_program'       => 'Previously enrolled in a Gifted Education Programme',
    'edu_psychologist'     => 'Identified by an Educational Psychologist as Gifted',
];

// ── POST: update status ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $id          = (int)($_POST['id'] ?? 0);
    $newStatus   = $_POST['status'] ?? '';
    $reviewNotes = trim($_POST['review_notes'] ?? '');

    if (!$id || !in_array($newStatus, ['reviewed', 'approved', 'rejected'])) {
        setFlash('danger', 'Invalid request.');
        redirect('/portal/student-affairs/admissions.php');
    }

    // Fetch the request
    $reqRow = $db->prepare('SELECT * FROM admission_requests WHERE id = ?');
    $reqRow->execute([$id]);
    $req = $reqRow->fetch();

    if (!$req) {
        setFlash('danger', 'Request not found.');
        redirect('/portal/student-affairs/admissions.php');
    }

    $enrolledGrNo = null;
    $enrolledStudentId = null;

    // Auto-enroll on approval of ILC requests
    if ($newStatus === 'approved' && ($req['wing'] ?? '') === 'ilc') {
        $alreadyEnrolled = $enrollColExists && !empty($req['enrolled_student_id']);
        if (!$alreadyEnrolled) {
            try {
                $db->beginTransaction();

                // Generate sequential GR number
                $yr   = date('Y');
                $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE roll_no LIKE ?");
                $stmt->execute(["ILC-$yr-%"]);
                $seq  = (int)$stmt->fetchColumn() + 1;
                $grNo = 'ILC-' . $yr . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);

                // Ensure uniqueness (race-condition guard)
                while (true) {
                    $chk = $db->prepare("SELECT id FROM students WHERE roll_no = ?");
                    $chk->execute([$grNo]);
                    if (!$chk->fetchColumn()) break;
                    $seq++;
                    $grNo = 'ILC-' . $yr . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
                }

                // Create portal user (role=student, password = GR number by default)
                $tempPass = password_hash($grNo, PASSWORD_DEFAULT);
                $db->prepare(
                    "INSERT INTO users (user_id, name, email, password, role) VALUES (?,?,?,?,?)"
                )->execute([
                    $grNo,
                    $req['student_name'],
                    null,
                    $tempPass,
                    'student',
                ]);
                $newUserId = (int)$db->lastInsertId();

                // Create student record
                $db->prepare(
                    "INSERT INTO students (user_id, roll_no, father_name, dob, parent_phone) VALUES (?,?,?,?,?)"
                )->execute([
                    $newUserId,
                    $grNo,
                    $req['parent_name'] ?: null,
                    $req['dob']         ?: null,
                    $req['parent_phone'] ?: null,
                ]);
                $newStudentId = (int)$db->lastInsertId();

                // Persist enrollment back onto the request
                if ($enrollColExists) {
                    $db->prepare(
                        "UPDATE admission_requests SET enrolled_student_id=?, enrolled_gr_no=? WHERE id=?"
                    )->execute([$newStudentId, $grNo, $id]);
                }

                $db->commit();

                $enrolledGrNo      = $grNo;
                $enrolledStudentId = $newStudentId;
                logActivity($user['id'], 'ilc_enrollment',
                    "Auto-enrolled student \"" . $req['student_name'] . "\" as $grNo (admission request #$id)");
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('warning', 'Request approved but auto-enrollment failed: ' . $e->getMessage());
                $db->prepare(
                    'UPDATE admission_requests SET status=?, reviewed_by=?, review_notes=?, reviewed_at=NOW() WHERE id=?'
                )->execute(['approved', $user['id'], $reviewNotes ?: null, $id]);
                logActivity($user['id'], 'admission_review', "Approved admission request #$id (enrollment failed)");
                redirect('/portal/student-affairs/admissions.php');
            }
        }
    }

    // Update status
    $db->prepare(
        'UPDATE admission_requests SET status=?, reviewed_by=?, review_notes=?, reviewed_at=NOW() WHERE id=?'
    )->execute([$newStatus, $user['id'], $reviewNotes ?: null, $id]);
    logActivity($user['id'], 'admission_review', "Set admission request #$id to $newStatus");

    if ($enrolledGrNo) {
        setFlash('success', "Request #$id approved. Student enrolled with GR number <strong>$enrolledGrNo</strong>. Default login password: <code>$enrolledGrNo</code>");
    } else {
        setFlash('success', "Request #$id marked as $newStatus.");
    }
    redirect('/portal/student-affairs/admissions.php');
}

// ── List ──────────────────────────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? '';
$focusId      = (int)($_GET['id'] ?? 0);

$sql    = 'SELECT ar.*, ru.name AS requested_by_name, rv.name AS reviewed_by_name
           FROM admission_requests ar
           JOIN users ru ON ru.id = ar.requested_by
           LEFT JOIN users rv ON rv.id = ar.reviewed_by
           WHERE 1';
$params = [];
if ($statusFilter) { $sql .= ' AND ar.status = ?'; $params[] = $statusFilter; }
$sql .= ' ORDER BY FIELD(ar.status,"pending","reviewed","approved","rejected"), ar.created_at DESC';
$st = $db->prepare($sql); $st->execute($params);
$requests = $st->fetchAll();

// ── Render helpers ────────────────────────────────────────────────────────────
function saYesNo(bool $yes): string {
    return $yes
        ? '<span class="badge" style="background:#166534;font-size:.71rem">Yes</span>'
        : '<span class="badge bg-secondary" style="font-size:.71rem">No</span>';
}
function saDocsIcon(bool $yes): string {
    return $yes ? '<i class="fas fa-check-circle" style="color:#16a34a"></i>' : '<span class="text-muted">—</span>';
}

function renderSAAssessmentView(array $ad, array $req,
    array $q1Items, array $q2Items, array $q3Items, array $q4Labels): string
{
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $h = '';

    // Student info
    $h .= '<div class="mb-3 p-2 rounded" style="background:#f8fafc;border:1px solid #e2e8f0">';
    $h .= '<div class="fw-bold mb-1" style="font-size:.74rem;text-transform:uppercase;color:#0f2456;letter-spacing:.5px">Student Information</div>';
    $h .= '<div class="row g-1" style="font-size:.79rem">';
    foreach ([
        'Name'    => $req['student_name'],
        'Parent'  => $req['parent_name'] ?? null,
        'Phone'   => $req['parent_phone'] ?? null,
        'DOB'     => (!empty($req['dob']) ? date('d M Y', strtotime($req['dob'])) : null),
        'Class'   => $req['requested_class'] ?? null,
        'Category'=> !empty($req['student_category']) ? strtoupper($req['student_category']) : null,
    ] as $lbl => $val) {
        if (!$val) continue;
        $h .= '<div class="col-md-4"><span class="text-muted">'.$lbl.':</span> <strong>'.$e($val).'</strong></div>';
    }
    $h .= '</div>';
    if (!empty($req['disability_notes'])) {
        $h .= '<div class="mt-1" style="font-size:.76rem;color:#64748b"><i class="fas fa-sticky-note me-1"></i>'.$e($req['disability_notes']).'</div>';
    }
    $h .= '</div>';

    // Q1
    $h .= '<div class="mb-3"><div class="fw-bold mb-1" style="font-size:.74rem;color:#0f2456;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e2e8f0;padding-bottom:2px">Q1 — Diagnosed Conditions</div>';
    $h .= '<table class="table table-sm table-bordered mb-0" style="font-size:.78rem"><thead class="table-light"><tr><th>Condition</th><th class="text-center" style="width:60px">Answer</th><th class="text-center" style="width:80px">Docs</th></tr></thead><tbody>';
    foreach ($q1Items as $k => $lbl) {
        $v   = $ad['q1'][$k] ?? [];
        $yes = ($v['ans'] ?? 'no') === 'yes';
        $h  .= '<tr><td>'.$e($lbl).(!empty($v['specify']) ? ' <em class="text-muted">('.$e($v['specify']).')</em>' : '').'</td>';
        $h  .= '<td class="text-center">'.saYesNo($yes).'</td><td class="text-center">'.saDocsIcon(!empty($v['docs'])).'</td></tr>';
    }
    $h .= '</tbody></table></div>';

    // Q2
    $h .= '<div class="mb-3"><div class="fw-bold mb-1" style="font-size:.74rem;color:#0f2456;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e2e8f0;padding-bottom:2px">Q2 — Current Circumstances / Experiences</div>';
    $h .= '<table class="table table-sm table-bordered mb-0" style="font-size:.78rem"><thead class="table-light"><tr><th>Circumstance</th><th class="text-center" style="width:60px">Answer</th><th>Details</th></tr></thead><tbody>';
    foreach ($q2Items as $k => $meta) {
        $v   = $ad['q2'][$k] ?? [];
        $yes = ($v['ans'] ?? 'no') === 'yes';
        $spec = $v['specify'] ?? '';
        $h .= '<tr><td>'.$e($meta['label']).'</td><td class="text-center">'.saYesNo($yes).'</td>';
        $h .= '<td>'.($spec ? $e($spec) : '<span class="text-muted">—</span>').'</td></tr>';
    }
    $h .= '</tbody></table></div>';

    // Q3
    $h .= '<div class="mb-3"><div class="fw-bold mb-1" style="font-size:.74rem;color:#0f2456;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e2e8f0;padding-bottom:2px">Q3 — Professional Assessment &amp; Support History</div>';
    $h .= '<table class="table table-sm table-bordered mb-0" style="font-size:.78rem"><thead class="table-light"><tr><th>Assessment / Support</th><th class="text-center" style="width:60px">Answer</th><th>Details</th></tr></thead><tbody>';
    foreach ($q3Items as $k => $meta) {
        $v   = $ad['q3'][$k] ?? [];
        $yes = ($v['ans'] ?? 'no') === 'yes';
        $spec = $v['specify'] ?? '';
        $h .= '<tr><td>'.$e($meta['label']).'</td><td class="text-center">'.saYesNo($yes).'</td>';
        $h .= '<td>'.($spec ? $e($spec) : '<span class="text-muted">—</span>').'</td></tr>';
    }
    $h .= '</tbody></table></div>';

    // Q4
    $h .= '<div class="mb-1"><div class="fw-bold mb-1" style="font-size:.74rem;color:#0f2456;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e2e8f0;padding-bottom:2px">Q4 — High Academic Ability / Gifted</div>';
    $h .= '<table class="table table-sm table-bordered mb-0" style="font-size:.78rem"><thead class="table-light"><tr><th>Indicator</th><th class="text-center" style="width:60px">Answer</th></tr></thead><tbody>';
    foreach ($q4Labels as $k => $lbl) {
        $yes = (($ad['q4'][$k]['ans'] ?? 'no') === 'yes');
        $h .= '<tr><td>'.$e($lbl).'</td><td class="text-center">'.saYesNo($yes).'</td></tr>';
    }
    $h .= '</tbody></table></div>';

    return $h;
}

pageHead('Admission Requests — Student Affairs', 'student_affairs');
$links = getStudentAffairsLinks();
?>
<div class="portal-wrap">
<?php sidebar('student_affairs', 'admissions', $links, $user); ?>
<div class="main-area">
<?php topbar('Admission Requests', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Main Campus branding strip -->
<div class="d-flex align-items-center gap-3 mb-4 p-3"
     style="background:linear-gradient(90deg,#eff6ff,#dbeafe);border-radius:10px;border:1px solid #bfdbfe;">
  <img src="<?= url('/assets/bmc-logo.png') ?>" alt="BMC"
       style="width:48px;height:48px;object-fit:contain;flex-shrink:0;" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#1e40af;letter-spacing:.8px;text-transform:uppercase">Student Affairs Office</div>
    <div style="font-size:.8rem;color:#475569"><?= h(getSetting('school_name', 'BMC Bin Qasim')) ?> &mdash; Main Campus</div>
  </div>
</div>

<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-file-medical-alt me-2"></i>All Admission Requests (<?= count($requests) ?>)</span>
    <div class="d-flex gap-1 flex-wrap">
      <?php foreach ([''=>'All','pending'=>'Pending','reviewed'=>'Reviewed','approved'=>'Approved','rejected'=>'Rejected'] as $v=>$lbl): ?>
      <a href="?status=<?= $v ?>" class="btn btn-xs <?= $statusFilter===$v?'btn-primary':'btn-outline-secondary' ?>"
         style="font-size:.74rem;padding:2px 8px"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (empty($requests)): ?>
  <div style="padding:40px;text-align:center;color:var(--t2);font-size:.85rem">No requests found.</div>
  <?php else: ?>
  <?php foreach ($requests as $r):
    $sc        = match($r['status']) {'pending'=>'warning','reviewed'=>'info','approved'=>'success','rejected'=>'danger',default=>'secondary'};
    $highlight = $focusId == $r['id'] ? 'background:#fffbeb;' : '';
    $isIlc     = ($r['wing'] ?? '') === 'ilc';
    $hasForm   = $migrationApplied && !empty($r['assessment_data']);
  ?>
  <div style="padding:14px 16px;border-bottom:1px solid var(--border);<?= $highlight ?>">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <div class="fw-bold d-flex align-items-center gap-1 flex-wrap">
          <?= h($r['student_name']) ?>
          <span class="badge bg-<?= $sc ?>"><?= ucfirst($r['status']) ?></span>
          <?php if ($isIlc): ?>
          <span class="badge" style="background:#0891b2;font-size:.68rem">ILC</span>
          <?php endif; ?>
          <?php if ($enrollColExists && !empty($r['enrolled_gr_no'])): ?>
          <span class="badge" style="background:#166534;font-size:.68rem">GR: <?= h($r['enrolled_gr_no']) ?></span>
          <?php endif; ?>
        </div>
        <div style="font-size:.79rem;color:var(--t2)">
          Requested by <strong><?= h($r['requested_by_name']) ?></strong> · <?= fDate($r['created_at']) ?>
          <?php if ($r['requested_class']): ?> · Class: <strong><?= h($r['requested_class']) ?></strong><?php endif; ?>
          <?php if (!empty($r['student_category'])): ?>
          · <span class="badge bg-info text-dark" style="font-size:.69rem"><?= strtoupper(h($r['student_category'])) ?></span>
          <?php endif; ?>
          <?php if (!empty($r['wing'])): ?> · Wing: <strong><?= ucfirst(h($r['wing'])) ?></strong><?php endif; ?>
        </div>
        <div class="row g-2 mt-1" style="font-size:.81rem">
          <?php if ($r['parent_name']): ?><div class="col-auto">Parent: <?= h($r['parent_name']) ?></div><?php endif; ?>
          <?php if ($r['parent_phone']): ?><div class="col-auto">Ph: <?= h($r['parent_phone']) ?></div><?php endif; ?>
          <?php if ($r['dob']): ?><div class="col-auto">DOB: <?= date('d M Y', strtotime($r['dob'])) ?></div><?php endif; ?>
        </div>
        <?php if ($r['disability_notes']): ?>
        <div class="mt-1" style="font-size:.79rem;background:#f0f9ff;border-radius:6px;padding:5px 10px;border:1px solid #bae6fd">
          <i class="fas fa-sticky-note me-1 text-muted"></i><?= h($r['disability_notes']) ?>
        </div>
        <?php endif; ?>
        <?php if ($r['reviewed_by_name']): ?>
        <div style="font-size:.77rem;color:var(--t2);margin-top:4px">
          Reviewed by <?= h($r['reviewed_by_name']) ?> on <?= $r['reviewed_at'] ? fDate($r['reviewed_at']) : '—' ?>
          <?php if ($r['review_notes']): ?> — <em><?= h($r['review_notes']) ?></em><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="d-flex gap-1 flex-wrap align-items-start">
        <?php if ($hasForm): ?>
        <button class="btn btn-sm btn-outline-info" style="font-size:.77rem"
                onclick="viewForm(<?= $r['id'] ?>)">
          <i class="fas fa-eye me-1"></i>View Form
        </button>
        <?php endif; ?>
        <?php if ($r['status'] === 'pending' || $r['status'] === 'reviewed'): ?>
        <button class="btn btn-sm btn-outline-primary" style="font-size:.77rem"
                onclick="toggleReview(<?= $r['id'] ?>)">
          <i class="fas fa-edit me-1"></i>Review
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Inline review form -->
    <div id="review-<?= $r['id'] ?>" class="mt-2" style="display:none">
      <form method="POST">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" value="<?= $r['id'] ?>">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.77rem">Update Status</label>
            <select name="status" class="form-select form-select-sm">
              <option value="reviewed"  <?= $r['status']==='reviewed' ?'selected':'' ?>>Reviewed</option>
              <option value="approved"  <?= $r['status']==='approved' ?'selected':'' ?>>Approved
                <?= $isIlc ? '(+ Auto-Enroll)' : '' ?></option>
              <option value="rejected"  <?= $r['status']==='rejected' ?'selected':'' ?>>Rejected</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold" style="font-size:.77rem">Review Notes / Remarks</label>
            <input type="text" name="review_notes" class="form-control form-control-sm"
                   placeholder="Optional remarks…" value="<?= h($r['review_notes'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <button type="submit" class="btn btn-sm btn-success w-100">
              <i class="fas fa-save me-1"></i>Save
            </button>
          </div>
          <?php if ($isIlc): ?>
          <div class="col-12">
            <div style="font-size:.74rem;color:#0891b2;background:#ecfeff;border-radius:4px;padding:4px 8px;border:1px solid #a5f3fc;">
              <i class="fas fa-info-circle me-1"></i>
              Setting status to <strong>Approved</strong> will automatically create a student account and generate an ILC GR number.
            </div>
          </div>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- ── Assessment-view modals ────────────────────────────────────────────── -->
<?php foreach ($requests as $r):
  if (empty($r['assessment_data'])) continue;
  $ad = json_decode($r['assessment_data'], true) ?? [];
  $sc = match($r['status']) {'approved'=>'success','rejected'=>'danger','reviewed'=>'info',default=>'warning'};
?>
<div class="modal fade" id="form-modal-<?= $r['id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(90deg,#0f2456,#1e40af);color:#fff">
        <h5 class="modal-title" style="font-size:.88rem">
          <i class="fas fa-file-medical me-2"></i>ILC Assessment — <?= h($r['student_name']) ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="font-size:.82rem">
        <?= renderSAAssessmentView($ad, $r, $q1Items, $q2Items, $q3Items, $q4Labels) ?>
      </div>
      <div class="modal-footer" style="font-size:.8rem">
        <span class="badge bg-<?= $sc ?>"><?= ucfirst($r['status']) ?></span>
        <?php if (!empty($r['review_notes'])): ?>
        <span class="text-muted ms-1"><?= h($r['review_notes']) ?></span>
        <?php endif; ?>
        <?php if ($enrollColExists && !empty($r['enrolled_gr_no'])): ?>
        <span class="badge ms-1" style="background:#166534">GR: <?= h($r['enrolled_gr_no']) ?></span>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-secondary ms-auto" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleReview(id) {
  var el = document.getElementById('review-' + id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function viewForm(id) {
  new bootstrap.Modal(document.getElementById('form-modal-' + id)).show();
}
<?php if ($focusId): ?>
document.addEventListener('DOMContentLoaded', function(){ toggleReview(<?= $focusId ?>); });
<?php endif; ?>
</script>
</body></html>

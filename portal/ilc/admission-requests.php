<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp');
requirePermission('ilc_admissions');
$db   = getDB();

// Check whether migration has been applied
$migrationApplied = false;
try { $db->query('SELECT assessment_data FROM admission_requests LIMIT 0'); $migrationApplied = true; } catch (Exception $e) {}

// Question definitions
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
$q4Items = [
    'standardized_testing' => 'Scored highly on standardized tests',
    'gifted_program'       => 'Previously enrolled in a Gifted Education Programme',
    'edu_psychologist'     => 'Identified by an Educational Psychologist as Gifted',
];

// ── POST: create ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $studentName = trim($_POST['student_name']   ?? '');
    $parentName  = trim($_POST['parent_name']    ?? '');
    $parentPhone = trim($_POST['parent_phone']   ?? '');
    $dob         = $_POST['dob']                 ?? '';
    $reqClass    = trim($_POST['requested_class'] ?? '');
    $stuCat      = in_array($_POST['student_category'] ?? '', ['civilian','cpo','sailor']) ? $_POST['student_category'] : null;
    $disNotes    = trim($_POST['disability_notes'] ?? '');

    if (!$studentName) {
        setFlash('danger', 'Student name is required.');
        redirect('/portal/ilc/admission-requests.php');
    }

    // Build Q1–Q4 JSON
    $q1 = [];
    foreach (array_keys($q1Items) as $k) {
        $q1[$k] = ['ans' => (($_POST["q1_$k"] ?? '') === 'yes') ? 'yes' : 'no', 'docs' => !empty($_POST["q1_{$k}_docs"])];
        if ($k === 'other') $q1[$k]['specify'] = trim($_POST['q1_other_specify'] ?? '');
    }
    $q2 = [];
    foreach ($q2Items as $k => $meta) {
        $q2[$k] = ['ans' => (($_POST["q2_$k"] ?? '') === 'yes') ? 'yes' : 'no'];
        if ($meta['specify']) $q2[$k]['specify'] = trim($_POST["q2_{$k}_specify"] ?? '');
    }
    $q3 = [];
    foreach ($q3Items as $k => $meta) {
        $q3[$k] = ['ans' => (($_POST["q3_$k"] ?? '') === 'yes') ? 'yes' : 'no'];
        if ($meta['specify']) $q3[$k]['specify'] = trim($_POST["q3_{$k}_specify"] ?? '');
    }
    $q4 = [];
    foreach (array_keys($q4Items) as $k) {
        $q4[$k] = ['ans' => (($_POST["q4_$k"] ?? '') === 'yes') ? 'yes' : 'no'];
    }
    $json = json_encode(['q1' => $q1, 'q2' => $q2, 'q3' => $q3, 'q4' => $q4]);

    if ($migrationApplied) {
        $db->prepare(
            'INSERT INTO admission_requests
             (student_name,parent_name,parent_phone,dob,requested_class,student_category,wing,disability_notes,assessment_data,requested_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([$studentName, $parentName ?: null, $parentPhone ?: null, $dob ?: null,
                    $reqClass ?: null, $stuCat, 'ilc', $disNotes ?: null, $json, $user['id']]);
    } else {
        $db->prepare(
            'INSERT INTO admission_requests
             (student_name,parent_name,parent_phone,dob,requested_class,student_category,wing,disability_notes,requested_by)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([$studentName, $parentName ?: null, $parentPhone ?: null, $dob ?: null,
                    $reqClass ?: null, $stuCat, 'ilc', $disNotes ?: null, $user['id']]);
    }
    logActivity($user['id'], 'admission_request_create', "ILC assessment submitted for $studentName");
    setFlash('success', "Assessment form for \"$studentName\" submitted to Student Affairs.");
    redirect('/portal/ilc/admission-requests.php');
}

// ── List ──────────────────────────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? '';
$sql    = "SELECT ar.*, u.name AS reviewed_by_name
           FROM admission_requests ar
           LEFT JOIN users u ON u.id = ar.reviewed_by
           WHERE ar.requested_by = ? AND ar.wing = 'ilc'";
$params = [$user['id']];
if ($statusFilter) { $sql .= ' AND ar.status = ?'; $params[] = $statusFilter; }
$sql .= ' ORDER BY ar.created_at DESC';
$st = $db->prepare($sql); $st->execute($params);
$requests = $st->fetchAll();

$enrollColExists = false;
try { $db->query('SELECT enrolled_gr_no FROM admission_requests LIMIT 0'); $enrollColExists = true; } catch (Exception $e) {}

// ── Render helpers ────────────────────────────────────────────────────────────
function ilcYesNo(bool $yes): string {
    return $yes
        ? '<span class="badge" style="background:#166534;font-size:.72rem">Yes</span>'
        : '<span class="badge bg-secondary" style="font-size:.72rem">No</span>';
}
function ilcDocsIcon(bool $provided): string {
    return $provided
        ? '<i class="fas fa-check-circle" style="color:#16a34a"></i>'
        : '<span class="text-muted">—</span>';
}
function renderAssessmentView(array $ad, array $r, array $q1Items, array $q2Items, array $q3Items, array $q4Items): string {
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $h = '';

    // Student info block
    $h .= '<div class="mb-3 p-2 rounded" style="background:#f8fafc;border:1px solid #e2e8f0">';
    $h .= '<div class="fw-bold mb-1" style="font-size:.75rem;text-transform:uppercase;color:#0f2456;letter-spacing:.5px">Student Information</div>';
    $h .= '<div class="row g-1" style="font-size:.8rem">';
    foreach ([
        'Name'    => $r['student_name'],
        'Parent'  => $r['parent_name'] ?? null,
        'Phone'   => $r['parent_phone'] ?? null,
        'DOB'     => (!empty($r['dob']) ? date('d M Y', strtotime($r['dob'])) : null),
        'Class'   => $r['requested_class'] ?? null,
        'Category'=> !empty($r['student_category']) ? strtoupper($r['student_category']) : null,
    ] as $lbl => $val) {
        if (!$val) continue;
        $h .= '<div class="col-md-4"><span class="text-muted">'.$lbl.':</span> <strong>'.$e($val).'</strong></div>';
    }
    $h .= '</div>';
    if (!empty($r['disability_notes'])) {
        $h .= '<div class="mt-1" style="font-size:.77rem;color:#64748b"><i class="fas fa-sticky-note me-1"></i>'.$e($r['disability_notes']).'</div>';
    }
    $h .= '</div>';

    // Q1
    $h .= '<div class="mb-3"><div class="fw-bold mb-1" style="font-size:.76rem;color:#0f2456;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e2e8f0;padding-bottom:3px">Q1 — Diagnosed Conditions</div>';
    $h .= '<table class="table table-sm table-bordered mb-0" style="font-size:.79rem"><thead class="table-light"><tr><th>Condition</th><th class="text-center" style="width:60px">Answer</th><th class="text-center" style="width:80px">Docs</th></tr></thead><tbody>';
    foreach ($q1Items as $k => $lbl) {
        $v = $ad['q1'][$k] ?? [];
        $yes = ($v['ans'] ?? 'no') === 'yes';
        $h .= '<tr><td>'.$e($lbl).(!empty($v['specify']) ? ' <span class="text-muted">('.$e($v['specify']).')</span>' : '').'</td>';
        $h .= '<td class="text-center">'.ilcYesNo($yes).'</td>';
        $h .= '<td class="text-center">'.ilcDocsIcon(!empty($v['docs'])).'</td></tr>';
    }
    $h .= '</tbody></table></div>';

    // Q2
    $h .= '<div class="mb-3"><div class="fw-bold mb-1" style="font-size:.76rem;color:#0f2456;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e2e8f0;padding-bottom:3px">Q2 — Current Circumstances / Experiences</div>';
    $h .= '<table class="table table-sm table-bordered mb-0" style="font-size:.79rem"><thead class="table-light"><tr><th>Circumstance</th><th class="text-center" style="width:60px">Answer</th><th>Details</th></tr></thead><tbody>';
    foreach ($q2Items as $k => $meta) {
        $v = $ad['q2'][$k] ?? [];
        $yes = ($v['ans'] ?? 'no') === 'yes';
        $spec = $v['specify'] ?? '';
        $h .= '<tr><td>'.$e($meta['label']).'</td><td class="text-center">'.ilcYesNo($yes).'</td>';
        $h .= '<td>'.($spec ? $e($spec) : '<span class="text-muted">—</span>').'</td></tr>';
    }
    $h .= '</tbody></table></div>';

    // Q3
    $h .= '<div class="mb-3"><div class="fw-bold mb-1" style="font-size:.76rem;color:#0f2456;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e2e8f0;padding-bottom:3px">Q3 — Professional Assessment &amp; Support History</div>';
    $h .= '<table class="table table-sm table-bordered mb-0" style="font-size:.79rem"><thead class="table-light"><tr><th>Assessment / Support</th><th class="text-center" style="width:60px">Answer</th><th>Details</th></tr></thead><tbody>';
    foreach ($q3Items as $k => $meta) {
        $v = $ad['q3'][$k] ?? [];
        $yes = ($v['ans'] ?? 'no') === 'yes';
        $spec = $v['specify'] ?? '';
        $h .= '<tr><td>'.$e($meta['label']).'</td><td class="text-center">'.ilcYesNo($yes).'</td>';
        $h .= '<td>'.($spec ? $e($spec) : '<span class="text-muted">—</span>').'</td></tr>';
    }
    $h .= '</tbody></table></div>';

    // Q4
    $q4Labels = ['standardized_testing'=>'Scored highly on standardized tests','gifted_program'=>'Previously enrolled in a Gifted Education Programme','edu_psychologist'=>'Identified by an Educational Psychologist as Gifted'];
    $h .= '<div class="mb-2"><div class="fw-bold mb-1" style="font-size:.76rem;color:#0f2456;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e2e8f0;padding-bottom:3px">Q4 — High Academic Ability / Gifted</div>';
    $h .= '<table class="table table-sm table-bordered mb-0" style="font-size:.79rem"><thead class="table-light"><tr><th>Indicator</th><th class="text-center" style="width:60px">Answer</th></tr></thead><tbody>';
    foreach ($q4Labels as $k => $lbl) {
        $yes = (($ad['q4'][$k]['ans'] ?? 'no') === 'yes');
        $h .= '<tr><td>'.$e($lbl).'</td><td class="text-center">'.ilcYesNo($yes).'</td></tr>';
    }
    $h .= '</tbody></table></div>';

    return $h;
}

pageHead('ILC Admission Requests', 'ilc_vp');
$links = getIlcLinks();
?>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'admissions', $links, $user); ?>
<div class="main-area">
<?php topbar('ILC Admission Requests', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$migrationApplied): ?>
<div class="alert alert-warning d-flex gap-2 align-items-start" style="font-size:.83rem">
  <i class="fas fa-exclamation-triangle mt-1 flex-shrink-0"></i>
  <div><strong>Migration not applied.</strong> Run <code>database/migrations/ilc_assessment_form.sql</code> to enable full assessment data storage. Basic submission is still available.</div>
</div>
<?php endif; ?>

<!-- ILC branding strip -->
<div class="d-flex align-items-center gap-3 mb-4 p-3" style="background:linear-gradient(90deg,#ecfeff,#f0fdf4);border-radius:10px;border:1px solid #a5f3fc;">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#0891b2;letter-spacing:.8px;text-transform:uppercase">Inclusive Learning Centre</div>
    <div style="font-size:.8rem;color:#475569">Bahria Model School &amp; College · Bin Qasim</div>
  </div>
</div>

<!-- ── New assessment form (collapsible) ────────────────────────────────── -->
<div class="sec-card mb-3">
  <div class="sec-card-header d-flex justify-content-between align-items-center"
       data-bs-toggle="collapse" data-bs-target="#newReqForm" style="cursor:pointer">
    <span><i class="fas fa-plus me-2"></i>New ILC Assessment Form</span>
    <i class="fas fa-chevron-down"></i>
  </div>
  <div id="newReqForm" class="collapse">
    <div style="padding:20px 20px 24px">

      <!-- Official header -->
      <div class="text-center mb-4 pb-3" style="border-bottom:2px solid #0891b2">
        <div style="font-size:.68rem;font-weight:700;letter-spacing:1.2px;color:#0891b2;text-transform:uppercase">
          Bahria Model School &amp; College · Bin Qasim
        </div>
        <div style="font-size:1.1rem;font-weight:700;color:#0f172a;margin:.35rem 0 .2rem">INCLUSIVE LEARNING CENTRE</div>
        <div style="font-size:.78rem;color:#475569;letter-spacing:.4px">Assessment Form — <em>Confidential</em></div>
      </div>

      <form method="POST">
        <input type="hidden" name="action" value="create">

        <!-- Student Information -->
        <div class="mb-4">
          <div class="fw-bold mb-2" style="font-size:.8rem;color:#0f2456;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e2e8f0;padding-bottom:4px">
            Student Information
          </div>
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label fw-semibold" style="font-size:.79rem">Student Name <span class="text-danger">*</span></label>
              <input type="text" name="student_name" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold" style="font-size:.79rem">Parent / Guardian Name</label>
              <input type="text" name="parent_name" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold" style="font-size:.79rem">Contact Number</label>
              <input type="tel" name="parent_phone" class="form-control form-control-sm" placeholder="03xx-xxxxxxx">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold" style="font-size:.79rem">Date of Birth</label>
              <input type="date" name="dob" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold" style="font-size:.79rem">Requested Class / Grade</label>
              <input type="text" name="requested_class" class="form-control form-control-sm" placeholder="e.g. Grade 5">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold" style="font-size:.79rem">Student Category</label>
              <select name="student_category" class="form-select form-select-sm">
                <option value="">— Select —</option>
                <option value="civilian">Civilian</option>
                <option value="cpo">CPO</option>
                <option value="sailor">Sailor</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold" style="font-size:.79rem">General Notes / Background</label>
              <textarea name="disability_notes" class="form-control form-control-sm" rows="2"
                        placeholder="Any additional background information about the student…"></textarea>
            </div>
          </div>
        </div>

        <!-- Q1: Diagnosed Conditions -->
        <div class="mb-4">
          <div class="fw-bold mb-1" style="font-size:.8rem;color:#0f2456;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e2e8f0;padding-bottom:4px">
            Q1 — Diagnosed Conditions
          </div>
          <p style="font-size:.77rem;color:#64748b;margin-bottom:8px">
            Does the student have a formal diagnosis for any of the following? Indicate Yes or No, and whether supporting documentation has been provided.
          </p>
          <div class="table-responsive">
            <table class="table table-sm table-bordered" style="font-size:.8rem">
              <thead class="table-light">
                <tr>
                  <th>Condition</th>
                  <th class="text-center" style="width:76px">Yes</th>
                  <th class="text-center" style="width:76px">No</th>
                  <th class="text-center" style="width:170px">Supporting Docs Provided</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($q1Items as $key => $label): ?>
                <tr>
                  <td class="align-middle">
                    <?= h($label) ?>
                    <?php if ($key === 'other'): ?>
                    <input type="text" name="q1_other_specify" class="form-control form-control-sm mt-1"
                           placeholder="Specify condition…" style="max-width:240px">
                    <?php endif; ?>
                  </td>
                  <td class="text-center align-middle">
                    <input type="radio" name="q1_<?= $key ?>" value="yes" class="form-check-input">
                  </td>
                  <td class="text-center align-middle">
                    <input type="radio" name="q1_<?= $key ?>" value="no" class="form-check-input" checked>
                  </td>
                  <td class="text-center align-middle">
                    <input type="checkbox" name="q1_<?= $key ?>_docs" value="1" class="form-check-input">
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Q2: Current Circumstances -->
        <div class="mb-4">
          <div class="fw-bold mb-1" style="font-size:.8rem;color:#0f2456;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e2e8f0;padding-bottom:4px">
            Q2 — Current Circumstances / Experiences
          </div>
          <p style="font-size:.77rem;color:#64748b;margin-bottom:8px">
            Is the student currently experiencing any of the following? Answer Yes or No and provide details where applicable.
          </p>
          <div class="table-responsive">
            <table class="table table-sm table-bordered" style="font-size:.8rem">
              <thead class="table-light">
                <tr>
                  <th style="width:42%">Circumstance</th>
                  <th class="text-center" style="width:70px">Yes</th>
                  <th class="text-center" style="width:70px">No</th>
                  <th>Please Specify (if Yes)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($q2Items as $key => $meta): ?>
                <tr>
                  <td class="align-middle"><?= h($meta['label']) ?></td>
                  <td class="text-center align-middle">
                    <input type="radio" name="q2_<?= $key ?>" value="yes" class="form-check-input">
                  </td>
                  <td class="text-center align-middle">
                    <input type="radio" name="q2_<?= $key ?>" value="no" class="form-check-input" checked>
                  </td>
                  <td class="align-middle">
                    <?php if ($meta['specify']): ?>
                    <input type="text" name="q2_<?= $key ?>_specify" class="form-control form-control-sm"
                           placeholder="Details…">
                    <?php else: ?>
                    <span class="text-muted" style="font-size:.75rem">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Q3: Professional History -->
        <div class="mb-4">
          <div class="fw-bold mb-1" style="font-size:.8rem;color:#0f2456;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e2e8f0;padding-bottom:4px">
            Q3 — Professional Assessment &amp; Support History
          </div>
          <p style="font-size:.77rem;color:#64748b;margin-bottom:8px">
            Has the student received any of the following professional assessments or support? Answer Yes or No and provide details where applicable.
          </p>
          <div class="table-responsive">
            <table class="table table-sm table-bordered" style="font-size:.8rem">
              <thead class="table-light">
                <tr>
                  <th style="width:42%">Assessment / Support</th>
                  <th class="text-center" style="width:70px">Yes</th>
                  <th class="text-center" style="width:70px">No</th>
                  <th>Please Specify (if Yes)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($q3Items as $key => $meta): ?>
                <tr>
                  <td class="align-middle"><?= h($meta['label']) ?></td>
                  <td class="text-center align-middle">
                    <input type="radio" name="q3_<?= $key ?>" value="yes" class="form-check-input">
                  </td>
                  <td class="text-center align-middle">
                    <input type="radio" name="q3_<?= $key ?>" value="no" class="form-check-input" checked>
                  </td>
                  <td class="align-middle">
                    <?php if ($meta['specify']): ?>
                    <input type="text" name="q3_<?= $key ?>_specify" class="form-control form-control-sm"
                           placeholder="Details…">
                    <?php else: ?>
                    <span class="text-muted" style="font-size:.75rem">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Q4: High Academic Ability -->
        <div class="mb-4">
          <div class="fw-bold mb-1" style="font-size:.8rem;color:#0f2456;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e2e8f0;padding-bottom:4px">
            Q4 — High Academic Ability / Gifted
          </div>
          <p style="font-size:.77rem;color:#64748b;margin-bottom:8px">
            Has the student demonstrated evidence of high academic ability or been identified as gifted?
          </p>
          <div class="table-responsive">
            <table class="table table-sm table-bordered" style="font-size:.8rem">
              <thead class="table-light">
                <tr>
                  <th style="width:76%">Indicator</th>
                  <th class="text-center" style="width:76px">Yes</th>
                  <th class="text-center" style="width:76px">No</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($q4Items as $key => $label): ?>
                <tr>
                  <td class="align-middle"><?= h($label) ?></td>
                  <td class="text-center align-middle">
                    <input type="radio" name="q4_<?= $key ?>" value="yes" class="form-check-input">
                  </td>
                  <td class="text-center align-middle">
                    <input type="radio" name="q4_<?= $key ?>" value="no" class="form-check-input" checked>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- VP ILC note -->
        <div class="p-3 mb-4" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px">
          <div style="font-size:.76rem;font-weight:700;color:#166534;letter-spacing:.3px">VP ILC APPROVAL</div>
          <div style="font-size:.75rem;color:#4b5563;margin-top:3px">
            This form is submitted by the ILC Teacher. Enrollment is contingent on review and approval by the Student Affairs Office.
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-paper-plane me-1"></i>Submit Assessment to Student Affairs
          </button>
          <button type="reset" class="btn btn-outline-secondary">
            <i class="fas fa-undo me-1"></i>Reset
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── My Requests list ──────────────────────────────────────────────────── -->
<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-list me-2"></i>My Submitted Requests (<?= count($requests) ?>)</span>
    <div class="d-flex gap-1 flex-wrap">
      <?php foreach ([''=>'All','pending'=>'Pending','reviewed'=>'Reviewed','approved'=>'Approved','rejected'=>'Rejected'] as $v=>$lbl): ?>
      <a href="?status=<?= $v ?>" class="btn btn-xs <?= $statusFilter===$v?'btn-primary':'btn-outline-secondary' ?>"
         style="font-size:.73rem;padding:2px 8px"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (empty($requests)): ?>
  <div style="padding:32px;text-align:center;color:var(--t2);font-size:.85rem">No requests found.</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.82rem">
      <thead class="table-light">
        <tr>
          <th>#</th><th>Student Name</th><th>Parent</th><th>Phone</th>
          <th>DOB</th><th>Req. Class</th><th>Status</th>
          <th>Reviewed By</th>
          <?php if ($enrollColExists): ?><th>GR No.</th><?php endif; ?>
          <th>Submitted</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $r):
          $sc = match($r['status']) {'pending'=>'warning','reviewed'=>'info','approved'=>'success','rejected'=>'danger',default=>'secondary'};
        ?>
        <tr>
          <td class="text-muted"><?= $r['id'] ?></td>
          <td class="fw-semibold"><?= h($r['student_name']) ?></td>
          <td><?= h($r['parent_name'] ?: '—') ?></td>
          <td><?= h($r['parent_phone'] ?: '—') ?></td>
          <td><?= $r['dob'] ? date('d M Y', strtotime($r['dob'])) : '—' ?></td>
          <td><?= h($r['requested_class'] ?: '—') ?></td>
          <td><span class="badge bg-<?= $sc ?>"><?= ucfirst($r['status']) ?></span></td>
          <td>
            <?= h($r['reviewed_by_name'] ?: '—') ?>
            <?php if (!empty($r['review_notes'])): ?>
            <br><small class="text-muted"><?= h($r['review_notes']) ?></small>
            <?php endif; ?>
          </td>
          <?php if ($enrollColExists): ?>
          <td>
            <?php if (!empty($r['enrolled_gr_no'])): ?>
            <span class="badge" style="background:#166534;font-size:.72rem"><?= h($r['enrolled_gr_no']) ?></span>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <?php endif; ?>
          <td style="white-space:nowrap;font-size:.76rem"><?= fDate($r['created_at']) ?></td>
          <td>
            <?php if (!empty($r['assessment_data'])): ?>
            <button class="btn btn-xs btn-outline-info" style="font-size:.72rem"
                    onclick="viewForm(<?= $r['id'] ?>)">
              <i class="fas fa-eye me-1"></i>View
            </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
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
        <?= renderAssessmentView($ad, $r, $q1Items, $q2Items, $q3Items, $q4Items) ?>
      </div>
      <div class="modal-footer" style="font-size:.8rem">
        <span class="badge bg-<?= $sc ?>"><?= ucfirst($r['status']) ?></span>
        <?php if (!empty($r['review_notes'])): ?>
        <span class="text-muted ms-1"><?= h($r['review_notes']) ?></span>
        <?php endif; ?>
        <?php if (!empty($r['enrolled_gr_no'])): ?>
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
function viewForm(id) {
  new bootstrap.Modal(document.getElementById('form-modal-' + id)).show();
}
</script>
</body></html>

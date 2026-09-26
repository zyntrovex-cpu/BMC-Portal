<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp');
$db   = getDB();

// Table / column availability
$tableExists  = false;
$assessColExists = false;
try { $db->query('SELECT 1 FROM speech_therapy_reports LIMIT 1'); $tableExists = true; } catch (Exception $e) {}
if ($tableExists) {
    try { $db->query('SELECT assessment_data FROM speech_therapy_reports LIMIT 0'); $assessColExists = true; } catch (Exception $e) {}
}

// ── POST handler ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action    = $_POST['action']     ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);

    if (in_array($action, ['create', 'update']) && $studentId) {
        // Build assessment JSON from all 9 sections
        $s3Areas = [
            'receptive_language','expressive_language','vocabulary','following_instructions',
            'functional_communication','articulation_speech','oral_motor','fluency',
            'voice_vocal','pragmatic_social','eye_contact','imitation_turn',
        ];
        $s3 = [];
        foreach ($s3Areas as $k) {
            $s3[$k] = [
                'rating'  => $_POST['s3_'.$k.'_rating']   ?? '',
                'remarks' => trim($_POST['s3_'.$k.'_remarks'] ?? ''),
            ];
        }

        $s4Rows = ['initial','medial','final','blends','other'];
        $s4 = [];
        foreach ($s4Rows as $k) {
            $s4[$k] = [
                'sound_word' => trim($_POST['s4_'.$k.'_sound']    ?? ''),
                'correct'    => trim($_POST['s4_'.$k.'_correct']  ?? ''),
                'incorrect'  => trim($_POST['s4_'.$k.'_incorrect']?? ''),
                'notes'      => trim($_POST['s4_'.$k.'_notes']    ?? ''),
            ];
        }

        $s5Keys = ['lip_closure','tongue_movement','jaw_stability','breath_control','imitation_oral'];
        $s5 = [];
        foreach ($s5Keys as $k) { $s5[$k] = $_POST['s5_'.$k] ?? ''; }

        $s6Keys = ['requesting_needs','answering_questions','initiating_communication','maintaining_interaction','gestures_aac'];
        $s6 = [];
        foreach ($s6Keys as $k) { $s6[$k] = $_POST['s6_'.$k] ?? ''; }

        $stGoals = [];
        for ($i = 1; $i <= 4; $i++) { $stGoals[] = trim($_POST['s7_stgoal_'.$i] ?? ''); }

        $ad = [
            's1' => [
                'dob_age'         => trim($_POST['s1_dob_age']         ?? ''),
                'gender'          => $_POST['s1_gender']               ?? '',
                'assessment_date' => $_POST['s1_assessment_date']      ?? '',
                'referral_reason' => trim($_POST['s1_referral_reason'] ?? ''),
            ],
            's2' => [
                'presenting_concern' => trim($_POST['s2_presenting_concern'] ?? ''),
                'medical_history'    => trim($_POST['s2_medical_history']    ?? ''),
                'previous_therapy'   => trim($_POST['s2_previous_therapy']   ?? ''),
                'hearing_vision'     => trim($_POST['s2_hearing_vision']     ?? ''),
                'home_languages'     => trim($_POST['s2_home_languages']     ?? ''),
                'other_info'         => trim($_POST['s2_other_info']         ?? ''),
            ],
            's3' => $s3,
            's4' => $s4,
            's5' => $s5,
            's6' => $s6,
            's7' => [
                'strengths'        => trim($_POST['s7_strengths']        ?? ''),
                'primary_areas'    => trim($_POST['s7_primary_areas']    ?? ''),
                'long_term_goal'   => trim($_POST['s7_long_term_goal']   ?? ''),
                'short_term_goals' => $stGoals,
                'frequency'        => $_POST['s7_frequency']             ?? '',
                'frequency_other'  => trim($_POST['s7_frequency_other']  ?? ''),
            ],
            's8' => ['recommendations' => trim($_POST['s8_recommendations'] ?? '')],
            's9' => [
                'next_review_date' => $_POST['s9_next_review_date'] ?? '',
                'parent_guidance'  => $_POST['s9_parent_guidance']  ?? '',
                'therapist_remarks'=> trim($_POST['s9_therapist_remarks'] ?? ''),
            ],
        ];
        $assessJson = json_encode($ad, JSON_UNESCAPED_UNICODE);

        // Legacy month field — use assessment date or today
        $assessDate = $ad['s1']['assessment_date'] ?: date('Y-m-d');
        $monthDate  = date('Y-m-01', strtotime($assessDate));

        // Map legacy fields
        $notes    = $ad['s2']['presenting_concern'];
        $progress = $ad['s7']['strengths'];
        $goals    = implode('; ', array_filter($ad['s7']['short_term_goals']));

        if ($action === 'update') {
            $id = (int)($_POST['report_id'] ?? 0);
            if ($assessColExists) {
                $db->prepare(
                    'UPDATE speech_therapy_reports
                     SET month=?,therapist_notes=?,progress_summary=?,goals_next_month=?,assessment_data=?,recorded_by=?
                     WHERE id=?'
                )->execute([$monthDate, $notes ?: null, $progress ?: null, $goals ?: null, $assessJson, $user['id'], $id]);
            } else {
                $db->prepare(
                    'UPDATE speech_therapy_reports SET month=?,therapist_notes=?,progress_summary=?,goals_next_month=?,recorded_by=? WHERE id=?'
                )->execute([$monthDate, $notes ?: null, $progress ?: null, $goals ?: null, $user['id'], $id]);
            }
            logActivity($user['id'], 'stherapy_update', "Updated speech therapy assessment #$id");
            setFlash('success', 'Assessment updated.');
        } else {
            if ($assessColExists) {
                $db->prepare(
                    'INSERT INTO speech_therapy_reports (student_id,month,therapist_notes,progress_summary,goals_next_month,assessment_data,recorded_by)
                     VALUES (?,?,?,?,?,?,?)'
                )->execute([$studentId, $monthDate, $notes ?: null, $progress ?: null, $goals ?: null, $assessJson, $user['id']]);
            } else {
                $db->prepare(
                    'INSERT INTO speech_therapy_reports (student_id,month,therapist_notes,progress_summary,goals_next_month,recorded_by)
                     VALUES (?,?,?,?,?,?)'
                )->execute([$studentId, $monthDate, $notes ?: null, $progress ?: null, $goals ?: null, $user['id']]);
            }
            logActivity($user['id'], 'stherapy_create', "Saved speech therapy assessment for student #$studentId");
            setFlash('success', 'Assessment saved.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['report_id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM speech_therapy_reports WHERE id=?')->execute([$id]);
            logActivity($user['id'], 'stherapy_delete', "Deleted speech therapy assessment #$id");
            setFlash('success', 'Assessment deleted.');
        }
    }

    $redir = '/portal/ilc/speech-therapy.php' . ($studentId ? "?student_id=$studentId" : '');
    redirect($redir);
}

// ── Fetch ILC students ────────────────────────────────────────────
$studentId  = (int)($_GET['student_id'] ?? 0);
$editId     = (int)($_GET['edit']       ?? 0);
$newForm    = isset($_GET['new']);
$students   = $db->query(
    'SELECT s.id, u.name, s.roll_no, c.name AS class_name
     FROM students s JOIN users u ON u.id = s.user_id JOIN classes c ON c.id = s.class_id
     WHERE c.is_ilc = 1 ORDER BY c.name, s.roll_no'
)->fetchAll();

$reports    = [];
$editReport = null;
$curStudent = null;

if ($studentId && $tableExists) {
    $st = $db->prepare(
        'SELECT r.*, u.name AS recorder_name
         FROM speech_therapy_reports r JOIN users u ON u.id = r.recorded_by
         WHERE r.student_id = ? ORDER BY r.month DESC'
    );
    $st->execute([$studentId]);
    $reports = $st->fetchAll();
    if ($editId) {
        foreach ($reports as $r) {
            if ($r['id'] == $editId) { $editReport = $r; break; }
        }
    }
}
if ($studentId) {
    foreach ($students as $s) { if ($s['id'] === $studentId) { $curStudent = $s; break; } }
}

$showForm = $studentId && ($newForm || $editReport !== null);
$ed = $editReport ? (json_decode($editReport['assessment_data'] ?? '{}', true) ?? []) : [];

// Rating helpers
$s3Labels = [
    'receptive_language'     => 'Receptive Language',
    'expressive_language'    => 'Expressive Language',
    'vocabulary'             => 'Vocabulary',
    'following_instructions' => 'Following Instructions',
    'functional_communication'=> 'Functional Communication',
    'articulation_speech'    => 'Articulation / Speech Sounds',
    'oral_motor'             => 'Oral-Motor Skills',
    'fluency'                => 'Fluency',
    'voice_vocal'            => 'Voice / Vocal Quality',
    'pragmatic_social'       => 'Pragmatic / Social Communication',
    'eye_contact'            => 'Eye Contact / Joint Attention',
    'imitation_turn'         => 'Imitation / Turn Taking',
];
$s5Labels = [
    'lip_closure'    => 'Lip Closure / Movement',
    'tongue_movement'=> 'Tongue Movement',
    'jaw_stability'  => 'Jaw Stability / Movement',
    'breath_control' => 'Breath Control / Blowing',
    'imitation_oral' => 'Imitation of Oral Movements',
];
$s6Labels = [
    'requesting_needs'          => 'Requesting Needs',
    'answering_questions'       => 'Answering Questions',
    'initiating_communication'  => 'Initiating Communication',
    'maintaining_interaction'   => 'Maintaining Interaction',
    'gestures_aac'              => 'Use of Gestures / AAC (if applicable)',
];

pageHead('Speech Therapy — ILC', 'ilc_vp');
$links = getIlcLinks();
?>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'speech-therapy', $links, $user); ?>
<div class="main-area">
<?php topbar('Speech Therapy — Assessment', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  <strong>Migration not applied.</strong> Run <code>database/migrations/ilc_features.sql</code> first.
</div>
<?php else: ?>
<?php if (!$assessColExists): ?>
<div class="alert alert-info" style="font-size:.83rem">
  <i class="fas fa-info-circle me-1"></i>
  Run <code>database/migrations/speech_therapy_assessment.sql</code> to enable full assessment form storage.
</div>
<?php endif; ?>

<!-- ILC strip -->
<div class="d-flex align-items-center gap-3 mb-3 p-3"
     style="background:linear-gradient(90deg,#ecfeff,#f0fdf4);border-radius:10px;border:1px solid #a5f3fc;">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC"
       style="width:40px;height:40px;object-fit:contain;flex-shrink:0;" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#0891b2;letter-spacing:.8px;text-transform:uppercase">
      Speech &amp; Language Therapy Assessment
    </div>
    <div style="font-size:.78rem;color:#475569">Inclusive Learning Centre · Bahria Model College Bin Qasim</div>
  </div>
</div>

<!-- Student selector -->
<div class="sec-card mb-3">
  <div class="sec-card-header"><i class="fas fa-user-graduate me-2"></i>Select Student</div>
  <div style="padding:14px 16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <form method="GET" class="d-flex gap-2 flex-grow-1">
      <select name="student_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">— Select student —</option>
        <?php foreach ($students as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $studentId===$s['id']?'selected':'' ?>>
          <?= h($s['name']) ?> (<?= h($s['roll_no']) ?>) — <?= h($s['class_name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php if ($studentId && !$showForm): ?>
    <a href="?student_id=<?= $studentId ?>&new=1" class="btn btn-sm btn-success" style="white-space:nowrap">
      <i class="fas fa-plus me-1"></i>New Assessment
    </a>
    <?php endif; ?>
  </div>
</div>

<?php if ($showForm): ?>
<!-- ═══════════════ FULL 9-SECTION ASSESSMENT FORM ═══════════════ -->
<div class="sec-card mb-3">
  <div class="sec-card-header" style="background:#0891b2;color:#fff">
    <i class="fas fa-<?= $editReport ? 'edit' : 'plus' ?> me-2"></i>
    <?= $editReport
        ? 'Edit Assessment — ' . date('d M Y', strtotime($editReport['month']))
        : 'New Speech &amp; Language Therapy Assessment' ?>
    <?php if ($curStudent): ?>
    &nbsp;·&nbsp; <?= h($curStudent['name']) ?>
    <?php endif; ?>
    <a href="?student_id=<?= $studentId ?>" class="btn btn-sm btn-light ms-auto" style="font-size:.76rem">
      <i class="fas fa-times me-1"></i>Cancel
    </a>
  </div>
  <div style="padding:20px">
  <form method="POST">
    <input type="hidden" name="action" value="<?= $editReport ? 'update' : 'create' ?>">
    <input type="hidden" name="student_id" value="<?= $studentId ?>">
    <?php if ($editReport): ?>
    <input type="hidden" name="report_id" value="<?= $editReport['id'] ?>">
    <?php endif; ?>

<?php
$secStyle = 'background:#0891b2;color:#fff;padding:7px 14px;border-radius:6px;font-size:.82rem;font-weight:700;letter-spacing:.5px;margin-bottom:14px;margin-top:18px';
$fld = fn(string $sec, string $k, string $sub='') =>
    ($sec && isset($ed[$sec][$k]))
        ? ($sub ? ($ed[$sec][$k][$sub] ?? '') : ($ed[$sec][$k] ?? ''))
        : '';
$chk = fn(string $sec, string $k, string $val) =>
    (isset($ed[$sec][$k]) && $ed[$sec][$k] === $val) ? 'checked' : '';
?>

<!-- §1 Child / Client Information -->
<div style="<?= $secStyle ?>">1. CHILD / CLIENT INFORMATION</div>
<div class="row g-2 mb-2">
  <div class="col-md-6">
    <label class="form-label fw-semibold" style="font-size:.8rem">Child/Client Name</label>
    <input type="text" class="form-control form-control-sm" value="<?= h($curStudent['name'] ?? '') ?>" disabled
           style="background:#f0f9ff;color:#374151">
  </div>
  <div class="col-md-3">
    <label class="form-label fw-semibold" style="font-size:.8rem">Date of Birth / Age</label>
    <input type="text" name="s1_dob_age" class="form-control form-control-sm"
           value="<?= h($fld('s1','dob_age')) ?>" placeholder="e.g. 12 Mar 2015 / 9 yrs">
  </div>
  <div class="col-md-3">
    <label class="form-label fw-semibold" style="font-size:.8rem">Gender</label>
    <div class="d-flex gap-3 mt-1">
      <?php foreach (['Male','Female','Other'] as $g): ?>
      <label class="d-flex align-items-center gap-1" style="font-size:.82rem;cursor:pointer">
        <input type="radio" name="s1_gender" value="<?= $g ?>" <?= $chk('s1','gender',$g) ?>>
        <?= $g ?>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<div class="row g-2 mb-2">
  <div class="col-md-3">
    <label class="form-label fw-semibold" style="font-size:.8rem">Date of Assessment</label>
    <input type="date" name="s1_assessment_date" class="form-control form-control-sm"
           value="<?= h($fld('s1','assessment_date') ?: date('Y-m-d')) ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label fw-semibold" style="font-size:.8rem">Class / Grade</label>
    <input type="text" class="form-control form-control-sm" value="<?= h($curStudent['class_name'] ?? '') ?>" disabled
           style="background:#f0f9ff;color:#374151">
  </div>
  <div class="col-md-6">
    <label class="form-label fw-semibold" style="font-size:.8rem">Referral Reason</label>
    <input type="text" name="s1_referral_reason" class="form-control form-control-sm"
           value="<?= h($fld('s1','referral_reason')) ?>" placeholder="Reason for referral...">
  </div>
</div>

<!-- §2 Case History -->
<div style="<?= $secStyle ?>">2. CASE HISTORY</div>
<?php
$s2Fields = [
    's2_presenting_concern' => 'Presenting Concern',
    's2_medical_history'    => 'Medical / Developmental History',
    's2_previous_therapy'   => 'Previous Therapy / Intervention',
    's2_hearing_vision'     => 'Hearing / Vision Status',
    's2_home_languages'     => 'Home Language(s)',
    's2_other_info'         => 'Other Relevant Information',
];
$s2Keys = [
    's2_presenting_concern' => 'presenting_concern',
    's2_medical_history'    => 'medical_history',
    's2_previous_therapy'   => 'previous_therapy',
    's2_hearing_vision'     => 'hearing_vision',
    's2_home_languages'     => 'home_languages',
    's2_other_info'         => 'other_info',
];
?>
<div class="row g-2 mb-2">
<?php foreach ($s2Fields as $fname => $flabel):
  $fkey = $s2Keys[$fname]; ?>
  <div class="col-md-6">
    <label class="form-label fw-semibold" style="font-size:.8rem"><?= $flabel ?></label>
    <textarea name="<?= $fname ?>" class="form-control form-control-sm" rows="2"
              placeholder="<?= $flabel ?>..."><?= h($fld('s2',$fkey)) ?></textarea>
  </div>
<?php endforeach; ?>
</div>

<!-- §3 Speech & Language Assessment -->
<div style="<?= $secStyle ?>">3. SPEECH &amp; LANGUAGE ASSESSMENT</div>
<div style="overflow-x:auto;margin-bottom:4px">
<table class="table table-sm table-bordered mb-0" style="font-size:.8rem;min-width:600px">
  <thead style="background:#e0f2fe">
    <tr>
      <th style="width:34%">Area</th>
      <th style="text-align:center;width:14%">Emerging</th>
      <th style="text-align:center;width:14%">Needs Support</th>
      <th style="text-align:center;width:14%">Age Appropriate</th>
      <th>Remarks / Findings</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($s3Labels as $k => $label):
    $curRating  = $ed['s3'][$k]['rating']  ?? '';
    $curRemarks = $ed['s3'][$k]['remarks'] ?? '';
  ?>
  <tr>
    <td style="font-weight:500"><?= h($label) ?></td>
    <?php foreach (['Emerging','Needs Support','Age Appropriate'] as $rv): ?>
    <td style="text-align:center;vertical-align:middle">
      <input type="radio" name="s3_<?= $k ?>_rating" value="<?= $rv ?>"
             <?= $curRating === $rv ? 'checked' : '' ?>>
    </td>
    <?php endforeach; ?>
    <td><input type="text" name="s3_<?= $k ?>_remarks" class="form-control form-control-sm border-0"
               style="font-size:.78rem" value="<?= h($curRemarks) ?>" placeholder="Remarks..."></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- §4 Articulation / Speech Sound Screening -->
<div style="<?= $secStyle ?>">4. ARTICULATION / SPEECH SOUND SCREENING</div>
<div style="overflow-x:auto;margin-bottom:4px">
<table class="table table-sm table-bordered mb-0" style="font-size:.8rem;min-width:540px">
  <thead style="background:#e0f2fe">
    <tr>
      <th style="width:16%">Position</th>
      <th style="width:26%">Sound / Word</th>
      <th style="width:15%">Correct</th>
      <th style="width:15%">Incorrect</th>
      <th>Notes</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach (['initial'=>'Initial','medial'=>'Medial','final'=>'Final','blends'=>'Blends','other'=>'Other'] as $k => $label):
    $row = $ed['s4'][$k] ?? [];
  ?>
  <tr>
    <td style="font-weight:500;vertical-align:middle"><?= $label ?></td>
    <td><input type="text" name="s4_<?= $k ?>_sound" class="form-control form-control-sm border-0"
               value="<?= h($row['sound_word'] ?? '') ?>" placeholder="Sound / word..."></td>
    <td><input type="text" name="s4_<?= $k ?>_correct" class="form-control form-control-sm border-0"
               value="<?= h($row['correct'] ?? '') ?>" placeholder="Correct..."></td>
    <td><input type="text" name="s4_<?= $k ?>_incorrect" class="form-control form-control-sm border-0"
               value="<?= h($row['incorrect'] ?? '') ?>" placeholder="Incorrect..."></td>
    <td><input type="text" name="s4_<?= $k ?>_notes" class="form-control form-control-sm border-0"
               value="<?= h($row['notes'] ?? '') ?>" placeholder="Notes..."></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- §5 Oral-Motor & Breathing Observation -->
<div style="<?= $secStyle ?>">5. ORAL-MOTOR &amp; BREATHING OBSERVATION</div>
<div class="mb-2">
<?php foreach ($s5Labels as $k => $label):
  $cur = $ed['s5'][$k] ?? '';
?>
<div class="row g-0 align-items-center mb-2 p-2" style="background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0">
  <div class="col-md-4" style="font-size:.82rem;font-weight:500"><?= h($label) ?></div>
  <div class="col-md-8 d-flex gap-4 flex-wrap">
    <?php foreach (['Adequate','Emerging','Needs Support'] as $rv): ?>
    <label class="d-flex align-items-center gap-1" style="font-size:.82rem;cursor:pointer">
      <input type="radio" name="s5_<?= $k ?>" value="<?= $rv ?>" <?= $cur === $rv ? 'checked' : '' ?>>
      <?= $rv ?>
    </label>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- §6 Functional Communication -->
<div style="<?= $secStyle ?>">6. FUNCTIONAL COMMUNICATION</div>
<div class="mb-2">
<?php foreach ($s6Labels as $k => $label):
  $cur = $ed['s6'][$k] ?? '';
?>
<div class="row g-0 align-items-center mb-2 p-2" style="background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0">
  <div class="col-md-4" style="font-size:.82rem;font-weight:500"><?= h($label) ?></div>
  <div class="col-md-8 d-flex gap-4 flex-wrap">
    <?php foreach (['Independent','Prompted','Not Yet'] as $rv): ?>
    <label class="d-flex align-items-center gap-1" style="font-size:.82rem;cursor:pointer">
      <input type="radio" name="s6_<?= $k ?>" value="<?= $rv ?>" <?= $cur === $rv ? 'checked' : '' ?>>
      <?= $rv ?>
    </label>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- §7 Assessment Summary & Therapy Plan -->
<div style="<?= $secStyle ?>">7. ASSESSMENT SUMMARY &amp; THERAPY PLAN</div>
<div class="row g-2 mb-2">
  <div class="col-md-6">
    <label class="form-label fw-semibold" style="font-size:.8rem">Strengths</label>
    <textarea name="s7_strengths" class="form-control form-control-sm" rows="3"
              placeholder="Client strengths..."><?= h($ed['s7']['strengths'] ?? '') ?></textarea>
  </div>
  <div class="col-md-6">
    <label class="form-label fw-semibold" style="font-size:.8rem">Primary Areas of Need</label>
    <textarea name="s7_primary_areas" class="form-control form-control-sm" rows="3"
              placeholder="Areas requiring therapy..."><?= h($ed['s7']['primary_areas'] ?? '') ?></textarea>
  </div>
  <div class="col-md-12">
    <label class="form-label fw-semibold" style="font-size:.8rem">Long-Term Goal</label>
    <textarea name="s7_long_term_goal" class="form-control form-control-sm" rows="2"
              placeholder="Long-term therapy goal..."><?= h($ed['s7']['long_term_goal'] ?? '') ?></textarea>
  </div>
</div>
<div class="mb-2">
  <label class="form-label fw-semibold" style="font-size:.8rem">Short-Term Goals</label>
  <?php for ($i = 1; $i <= 4; $i++): ?>
  <div class="d-flex align-items-center gap-2 mb-1">
    <span style="font-size:.8rem;color:#64748b;width:14px"><?= $i ?>.</span>
    <input type="text" name="s7_stgoal_<?= $i ?>" class="form-control form-control-sm"
           value="<?= h($ed['s7']['short_term_goals'][$i-1] ?? '') ?>"
           placeholder="Short-term goal <?= $i ?>...">
  </div>
  <?php endfor; ?>
</div>
<div class="mb-2">
  <label class="form-label fw-semibold" style="font-size:.8rem">Recommended Session Frequency</label>
  <div class="d-flex gap-3 flex-wrap mt-1">
    <?php
    $freqOpts = ['1 session/week','2 sessions/week','3 sessions/week'];
    $curFreq  = $ed['s7']['frequency'] ?? '';
    ?>
    <?php foreach ($freqOpts as $fv): ?>
    <label class="d-flex align-items-center gap-1" style="font-size:.82rem;cursor:pointer">
      <input type="radio" name="s7_frequency" value="<?= $fv ?>" <?= $curFreq === $fv ? 'checked' : '' ?>>
      <?= $fv ?>
    </label>
    <?php endforeach; ?>
    <label class="d-flex align-items-center gap-1" style="font-size:.82rem;cursor:pointer">
      <input type="radio" name="s7_frequency" value="Other" <?= (!in_array($curFreq,$freqOpts) && $curFreq) ? 'checked' : '' ?>>
      Other:
      <input type="text" name="s7_frequency_other" class="form-control form-control-sm" style="width:130px"
             value="<?= h($ed['s7']['frequency_other'] ?? '') ?>" placeholder="specify...">
    </label>
  </div>
</div>

<!-- §8 Therapist Recommendations -->
<div style="<?= $secStyle ?>">8. RECOMMENDATIONS</div>
<div class="mb-2">
  <textarea name="s8_recommendations" class="form-control form-control-sm" rows="4"
            placeholder="Therapist recommendations..."><?= h($ed['s8']['recommendations'] ?? '') ?></textarea>
</div>

<!-- §9 Follow-Up / Review -->
<div style="<?= $secStyle ?>">9. FOLLOW-UP / REVIEW</div>
<div class="row g-2 mb-3">
  <div class="col-md-3">
    <label class="form-label fw-semibold" style="font-size:.8rem">Next Review Date</label>
    <input type="date" name="s9_next_review_date" class="form-control form-control-sm"
           value="<?= h($ed['s9']['next_review_date'] ?? '') ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label fw-semibold" style="font-size:.8rem">Parent Guidance Provided</label>
    <div class="d-flex gap-3 mt-1">
      <?php foreach (['Yes','No'] as $pv): ?>
      <label class="d-flex align-items-center gap-1" style="font-size:.82rem;cursor:pointer">
        <input type="radio" name="s9_parent_guidance" value="<?= $pv ?>"
               <?= ($ed['s9']['parent_guidance'] ?? '') === $pv ? 'checked' : '' ?>>
        <?= $pv ?>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-md-6">
    <label class="form-label fw-semibold" style="font-size:.8rem">Therapist Remarks</label>
    <textarea name="s9_therapist_remarks" class="form-control form-control-sm" rows="2"
              placeholder="Follow-up remarks..."><?= h($ed['s9']['therapist_remarks'] ?? '') ?></textarea>
  </div>
</div>

<div class="d-flex gap-2">
  <button type="submit" class="btn btn-success">
    <i class="fas fa-save me-1"></i><?= $editReport ? 'Update Assessment' : 'Save Assessment' ?>
  </button>
  <a href="?student_id=<?= $studentId ?>" class="btn btn-outline-secondary">Cancel</a>
</div>
  </form>
  </div><!-- /padding -->
</div>
<!-- ═══════════════ END FORM ═══════════════ -->

<?php endif; // showForm ?>

<!-- Assessments list -->
<?php if ($studentId && $curStudent && !$showForm): ?>
<div class="sec-card">
  <div class="sec-card-header">
    <i class="fas fa-comment-medical me-2"></i>Assessments — <?= h($curStudent['name']) ?>
    <span class="badge bg-secondary ms-2"><?= count($reports) ?></span>
  </div>
  <?php if (empty($reports)): ?>
  <div style="padding:50px;text-align:center;color:var(--t2);font-size:.85rem">
    <i class="fas fa-comment-medical fa-2x mb-3 d-block opacity-20"></i>
    No assessments yet. Use <strong>New Assessment</strong> above to add the first one.
  </div>
  <?php else: ?>
  <div style="padding:14px 16px">
    <?php foreach ($reports as $r):
      $ad2   = (!empty($r['assessment_data'])) ? (json_decode($r['assessment_data'],true) ?? []) : [];
      $isNew = !empty($r['assessment_data']);
    ?>
    <div class="mb-3 p-3" style="background:#f7f9fb;border-radius:8px;border:1px solid var(--border)">
      <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
        <div>
          <span class="badge" style="background:#0891b2;font-size:.75rem">
            <?= date('d M Y', strtotime($r['month'])) ?>
          </span>
          <?php if ($isNew): ?>
          <span class="badge bg-success ms-1" style="font-size:.7rem">Full Assessment</span>
          <?php endif; ?>
          <?php if (!empty($ad2['s1']['assessment_date'])): ?>
          <span style="font-size:.74rem;color:var(--t2);margin-left:6px">
            Assessed: <?= date('d M Y', strtotime($ad2['s1']['assessment_date'])) ?>
          </span>
          <?php endif; ?>
          <span style="font-size:.74rem;color:var(--t2);margin-left:6px">
            · <?= h($r['recorder_name']) ?>
          </span>
        </div>
        <div class="d-flex gap-1">
          <?php if ($isNew): ?>
          <a href="<?= url('/portal/ilc/speech-therapy-pdf.php?id=' . $r['id']) ?>" target="_blank"
             class="btn btn-sm btn-success" style="font-size:.76rem">
            <i class="fas fa-file-pdf me-1"></i>PDF
          </a>
          <?php endif; ?>
          <a href="?student_id=<?= $studentId ?>&edit=<?= $r['id'] ?>"
             class="btn btn-sm btn-outline-primary" style="font-size:.76rem">
            <i class="fas fa-edit"></i>
          </a>
          <form method="POST" class="d-inline" onsubmit="return confirm('Delete this assessment?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="student_id" value="<?= $studentId ?>">
            <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" style="font-size:.76rem"><i class="fas fa-trash"></i></button>
          </form>
        </div>
      </div>

      <?php if ($isNew): ?>
      <div class="row g-2" style="font-size:.8rem">
        <?php if (!empty($ad2['s2']['presenting_concern'])): ?>
        <div class="col-md-6">
          <span class="text-muted" style="font-size:.73rem;text-transform:uppercase;letter-spacing:.3px">Presenting Concern</span><br>
          <?= nl2br(h($ad2['s2']['presenting_concern'])) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($ad2['s7']['strengths'])): ?>
        <div class="col-md-6">
          <span class="text-muted" style="font-size:.73rem;text-transform:uppercase;letter-spacing:.3px">Strengths</span><br>
          <?= nl2br(h($ad2['s7']['strengths'])) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($ad2['s8']['recommendations'])): ?>
        <div class="col-12">
          <span class="text-muted" style="font-size:.73rem;text-transform:uppercase;letter-spacing:.3px">Therapist Recommendations</span><br>
          <?= nl2br(h($ad2['s8']['recommendations'])) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($ad2['s9']['next_review_date'])): ?>
        <div class="col-md-4">
          <span class="text-muted" style="font-size:.73rem">Next Review:</span>
          <strong><?= date('d M Y', strtotime($ad2['s9']['next_review_date'])) ?></strong>
        </div>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <?php if ($r['therapist_notes']): ?>
      <div style="font-size:.82rem;margin-bottom:4px"><strong>Notes:</strong> <?= nl2br(h($r['therapist_notes'])) ?></div>
      <?php endif; ?>
      <?php if ($r['progress_summary']): ?>
      <div style="font-size:.82rem;margin-bottom:4px"><strong>Progress:</strong> <?= nl2br(h($r['progress_summary'])) ?></div>
      <?php endif; ?>
      <?php if ($r['goals_next_month']): ?>
      <div style="font-size:.82rem;color:#0369a1"><strong>Next month goals:</strong> <?= nl2br(h($r['goals_next_month'])) ?></div>
      <?php endif; ?>
      <div style="font-size:.74rem;color:var(--t2);margin-top:4px;font-style:italic">Legacy report — detailed assessment data not available.</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php elseif (!$studentId): ?>
<div class="sec-card">
  <div style="padding:60px;text-align:center;color:var(--t2)">
    <i class="fas fa-comment-medical fa-2x mb-3 d-block opacity-20"></i>
    Select a student above to view or add assessments.
  </div>
</div>
<?php endif; ?>

<?php endif; // tableExists ?>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

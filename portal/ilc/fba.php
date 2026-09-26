<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp');
$db   = getDB();

// Table availability
$tableExists = false;
try { $db->query('SELECT 1 FROM fba_plans LIMIT 1'); $tableExists = true; } catch (Exception $e) {}

// Pre-defined strategies (editable in form, pre-filled with template content)
$defaultStrategies = [
    'self_awareness'        => 'Maintain a daily mood/behaviour log; identify recurring thoughts, triggers, and patterns.',
    'pause_reacting'        => 'Use a 10–30 second pause, slow breathing, or temporary withdrawal from the situation before responding.',
    'cognitive_restr'       => 'Identify negative or extreme thoughts and replace them with balanced, evidence-based alternatives.',
    'emotional_reg'         => 'Use breathing exercises, grounding, mindfulness, exercise, or another personally effective calming strategy.',
    'communication'         => 'Active listening, respectful tone, and specific requests instead of blame or criticism.',
    'anger_management'      => 'Recognize early warning signs, rate anger from 0–10 and use a planned coping response before reaching a high level.',
    'problem_solving'       => 'Define the problem, list options, consider consequences, choose one solution, and review the outcome.',
    'boundary_setting'      => 'Respect personal boundaries and communication limits clearly without threats, manipulation, or aggression.',
    'flexibility'           => 'Practice accepting differences of opinion and tolerate reasonable changes in plans.',
    'positive_reinforcement'=> 'Track successful use of healthy responses and reinforce progress rather than focusing only on mistakes.',
    'social_skills'         => 'Practice empathy, turn-taking, perspective-taking, apology, and repair after interpersonal conflict.',
    'lifestyle_support'     => 'Maintain adequate sleep, regular physical activity, balanced meals, and structured daily routines.',
];
$strategyLabels = [
    'self_awareness'         => 'Self-awareness',
    'pause_reacting'         => 'Pause before reacting',
    'cognitive_restr'        => 'Cognitive restructuring',
    'emotional_reg'          => 'Emotional regulation',
    'communication'          => 'Communication',
    'anger_management'       => 'Anger management',
    'problem_solving'        => 'Problem solving',
    'boundary_setting'       => 'Boundary setting',
    'flexibility'            => 'Flexibility',
    'positive_reinforcement' => 'Positive reinforcement',
    'social_skills'          => 'Social skills',
    'lifestyle_support'      => 'Lifestyle support',
];
$areasOfConcernList = [
    'anger'               => 'Frequent anger / irritability',
    'negative_thinking'   => 'Negative thinking / pessimism',
    'blaming'             => 'Blaming others',
    'difficulty_criticism'=> 'Difficulty accepting criticism',
    'argumentative'       => 'Argumentative behaviour',
    'impulsivity'         => 'Impulsivity',
    'jealousy'            => 'Jealousy / insecurity',
    'controlling'         => 'Controlling behaviour',
    'social_withdrawal'   => 'Social withdrawal',
    'low_frustration'     => 'Low frustration tolerance',
    'poor_communication'  => 'Poor communication',
    'difficulty_emotions' => 'Difficulty managing emotions',
];
$progressAreas = [
    'emotional_regulation' => 'Emotional regulation',
    'anger_control'        => 'Anger control',
    'negative_thinking'    => 'Negative thinking',
    'communication'        => 'Communication',
    'impulse_control'      => 'Impulse control',
    'interpersonal'        => 'Interpersonal relationships',
    'problem_solving'      => 'Problem solving',
    'flexibility'          => 'Flexibility',
];

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action    = $_POST['action']    ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);

    if (in_array($action, ['create', 'update']) && $studentId) {
        // Section 1: Basic Information
        $basic = [
            'age'          => trim($_POST['basic_age']        ?? ''),
            'date'         => trim($_POST['basic_date']       ?? ''),
            'gender'       => trim($_POST['basic_gender']     ?? ''),
            'occupation'   => trim($_POST['basic_occupation'] ?? ''),
            'session_no'   => trim($_POST['basic_session_no'] ?? ''),
            'facilitator'  => $user['name'],
            'review_date'  => trim($_POST['basic_review_date']?? ''),
        ];

        // Section 2: Areas of Concern
        $areasChecked = array_keys(array_intersect_key(
            $_POST['areas'] ?? [],
            $areasOfConcernList
        ));

        // Section 3: FBA rows (skip blank rows)
        $fbaRows = [];
        foreach ((array)($_POST['fba_trigger'] ?? []) as $i => $trigger) {
            $behaviour = trim($_POST['fba_behaviour'][$i] ?? '');
            if (trim($trigger) === '' && $behaviour === '') continue;
            $fbaRows[] = [
                'trigger'       => trim($trigger),
                'thought'       => trim($_POST['fba_thought'][$i]      ?? ''),
                'behaviour'     => $behaviour,
                'consequence'   => trim($_POST['fba_consequence'][$i]  ?? ''),
                'healthy_alt'   => trim($_POST['fba_healthy_alt'][$i]  ?? ''),
            ];
        }

        // Section 4: Strategies (editable)
        $strategies = [];
        foreach (array_keys($defaultStrategies) as $k) {
            $strategies[$k] = trim($_POST["strategy_$k"] ?? $defaultStrategies[$k]);
        }

        // Section 5: SMART Goals (skip blank)
        $smartGoals = [];
        foreach ((array)($_POST['goal_goal'] ?? []) as $i => $goal) {
            $baseline = trim($_POST['goal_baseline'][$i] ?? '');
            if (trim($goal) === '' && $baseline === '') continue;
            $smartGoals[] = [
                'goal'     => trim($goal),
                'baseline' => $baseline,
                'target'   => trim($_POST['goal_target'][$i]   ?? ''),
                'strategy' => trim($_POST['goal_strategy'][$i] ?? ''),
                'outcome'  => trim($_POST['goal_outcome'][$i]  ?? ''),
            ];
        }

        // Section 6: Daily Self-Monitoring (skip blank)
        $dailyMonitoring = [];
        foreach ((array)($_POST['dm_date'] ?? []) as $i => $date) {
            $trigger = trim($_POST['dm_trigger'][$i] ?? '');
            if (trim($date) === '' && $trigger === '') continue;
            $dailyMonitoring[] = [
                'date'            => trim($date),
                'trigger'         => $trigger,
                'emotion'         => trim($_POST['dm_emotion'][$i]          ?? ''),
                'response'        => trim($_POST['dm_response'][$i]         ?? ''),
                'healthy_strategy'=> trim($_POST['dm_healthy_strategy'][$i] ?? ''),
                'result'          => trim($_POST['dm_result'][$i]           ?? ''),
            ];
        }

        // Section 7: Progress Rating
        $progressRating = [];
        foreach (array_keys($progressAreas) as $area) {
            $progressRating[$area] = [
                'initial' => trim($_POST["pr_{$area}_initial"] ?? ''),
                'review'  => trim($_POST["pr_{$area}_review"]  ?? ''),
            ];
        }

        // Section 8: Reviews
        $reviews = [];
        foreach ((array)($_POST['rv_date'] ?? []) as $i => $rvDate) {
            $rvNote = trim($_POST['rv_note'][$i] ?? '');
            if (trim($rvDate) === '' && $rvNote === '') continue;
            $reviews[] = [
                'date'       => trim($rvDate),
                'note'       => $rvNote,
                'outcome'    => trim($_POST['rv_outcome'][$i]    ?? ''),
                'next_steps' => trim($_POST['rv_next_steps'][$i] ?? ''),
            ];
        }

        // Section 9: Notes
        $notes = trim($_POST['notes'] ?? '');

        $formData = json_encode([
            'basic'            => $basic,
            'areas_of_concern' => $areasChecked,
            'fba_rows'         => $fbaRows,
            'strategies'       => $strategies,
            'smart_goals'      => $smartGoals,
            'daily_monitoring' => $dailyMonitoring,
            'progress_rating'  => $progressRating,
            'reviews'          => $reviews,
            'notes'            => $notes,
        ], JSON_UNESCAPED_UNICODE);

        $sessionNo  = $basic['session_no']  ?: null;
        $sessionDate= $basic['date']        ?: null;
        $reviewDate = $basic['review_date'] ?: null;

        if ($action === 'update') {
            $id = (int)($_POST['plan_id'] ?? 0);
            $db->prepare(
                'UPDATE fba_plans SET session_no=?,session_date=?,review_date=?,form_data=?,recorded_by=? WHERE id=?'
            )->execute([$sessionNo, $sessionDate, $reviewDate, $formData, $user['id'], $id]);
            logActivity($user['id'], 'fba_update', "Updated FBA plan #$id");
            setFlash('success', 'FBA Behavior Management Plan updated.');
        } else {
            $db->prepare(
                'INSERT INTO fba_plans (student_id,session_no,session_date,review_date,form_data,recorded_by)
                 VALUES (?,?,?,?,?,?)'
            )->execute([$studentId, $sessionNo, $sessionDate, $reviewDate, $formData, $user['id']]);
            logActivity($user['id'], 'fba_create', "Created FBA plan for student #$studentId");
            setFlash('success', 'FBA Behavior Management Plan saved.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['plan_id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM fba_plans WHERE id=?')->execute([$id]);
            logActivity($user['id'], 'fba_delete', "Deleted FBA plan #$id");
            setFlash('success', 'FBA plan deleted.');
        }
    }

    $redir = '/portal/ilc/fba.php' . ($studentId ? "?student_id=$studentId" : '');
    redirect($redir);
}

// ── Fetch ─────────────────────────────────────────────────────────────────────
$studentId  = (int)($_GET['student_id'] ?? 0);
$editId     = (int)($_GET['edit']       ?? 0);

try {
    $students = $db->query(
        'SELECT s.id, u.name, s.roll_no, c.name AS class_name
         FROM students s
         JOIN users u ON u.id = s.user_id
         JOIN classes c ON c.id = s.class_id
         WHERE c.is_ilc = 1
         ORDER BY c.name, s.roll_no'
    )->fetchAll();
} catch (Exception $e) { $students = []; }

$plans = [];
$editPlan = null;
if ($studentId && $tableExists) {
    $st = $db->prepare(
        'SELECT p.*, u.name AS recorder_name
         FROM fba_plans p
         JOIN users u ON u.id = p.recorded_by
         WHERE p.student_id = ?
         ORDER BY p.created_at DESC'
    );
    $st->execute([$studentId]);
    $plans = $st->fetchAll();

    if ($editId) {
        foreach ($plans as $p) {
            if ($p['id'] == $editId) { $editPlan = $p; break; }
        }
    }
}

$curStudent = null;
foreach ($students as $s) {
    if ((int)$s['id'] === $studentId) { $curStudent = $s; break; }
}

// Pre-fill data for edit
$fd = [];
if ($editPlan && !empty($editPlan['form_data'])) {
    $fd = json_decode($editPlan['form_data'], true) ?? [];
}
$fdBasic   = $fd['basic']            ?? [];
$fdAreas   = $fd['areas_of_concern'] ?? [];
$fdFbaRows = $fd['fba_rows']         ?? array_fill(0, 5, ['trigger'=>'','thought'=>'','behaviour'=>'','consequence'=>'','healthy_alt'=>'']);
$fdStrat   = $fd['strategies']       ?? $defaultStrategies;
$fdGoals   = $fd['smart_goals']      ?? array_fill(0, 5, ['goal'=>'','baseline'=>'','target'=>'','strategy'=>'','outcome'=>'']);
$fdDM      = $fd['daily_monitoring'] ?? array_fill(0, 5, ['date'=>'','trigger'=>'','emotion'=>'','response'=>'','healthy_strategy'=>'','result'=>'']);
$fdPR      = $fd['progress_rating']  ?? [];
$fdNotes   = $fd['notes']            ?? '';
$fdReviews = $fd['reviews'] ?? [
    ['date'=>'', 'note'=>'Review progress weekly or according to the treatment schedule.',           'outcome'=>'', 'next_steps'=>''],
    ['date'=>'', 'note'=>'Identify strategies that are effective and continue them consistently.',   'outcome'=>'', 'next_steps'=>''],
    ['date'=>'', 'note'=>'Modify goals when progress is stable or when new concerns emerge.',        'outcome'=>'', 'next_steps'=>''],
    ['date'=>'', 'note'=>"Use objective behavioural observations rather than labels such as 'bad' or 'difficult'.", 'outcome'=>'', 'next_steps'=>''],
    ['date'=>'', 'note'=>'If behaviour includes threats, violence, severe impairment, self-harm, or risk to others, seek assessment from a qualified mental-health professional promptly.', 'outcome'=>'', 'next_steps'=>''],
];

while (count($fdFbaRows) < 5) $fdFbaRows[] = ['trigger'=>'','thought'=>'','behaviour'=>'','consequence'=>'','healthy_alt'=>''];
while (count($fdGoals)   < 5) $fdGoals[]   = ['goal'=>'','baseline'=>'','target'=>'','strategy'=>'','outcome'=>''];
while (count($fdDM)      < 5) $fdDM[]      = ['date'=>'','trigger'=>'','emotion'=>'','response'=>'','healthy_strategy'=>'','result'=>''];

pageHead('FBA — Behavior Management Plan', 'ilc_vp');
$links = getIlcLinks();
?>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'fba', $links, $user); ?>
<div class="main-area">
<?php topbar('FBA — Behavior Management Plan', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  <strong>Migration not applied.</strong> Run <code>database/migrations/fba_plans.sql</code> to create the FBA table. ⚠️ Back up your DB first!
</div>
<?php else: ?>

<!-- ILC branding strip -->
<div class="d-flex align-items-center gap-3 mb-3 p-3"
     style="background:linear-gradient(90deg,#fefce8,#fef9c3);border-radius:10px;border:1px solid #fde68a;">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC"
       style="width:40px;height:40px;object-fit:contain;flex-shrink:0;" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#92400e;letter-spacing:.8px;text-transform:uppercase">
      Functional Behaviour Assessment (FBA)
    </div>
    <div style="font-size:.78rem;color:#475569">Inclusive Learning Centre · Bahria Model College Bin Qasim</div>
  </div>
</div>

<div class="row g-3">
  <!-- Left: Student selector + form -->
  <div class="col-xl-6">

    <!-- Student selector -->
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-user-graduate me-2"></i>Select Student</div>
      <div style="padding:12px 16px">
        <form method="GET" class="d-flex gap-2">
          <select name="student_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">— Select student —</option>
            <?php foreach ($students as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $studentId===(int)$s['id']?'selected':'' ?>>
              <?= h($s['name']) ?> (<?= h($s['roll_no']) ?>) — <?= h($s['class_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <?php if ($studentId): ?>
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center"
           data-bs-toggle="collapse" data-bs-target="#fbaFormBody" style="cursor:pointer">
        <span>
          <i class="fas fa-<?= $editPlan ? 'edit' : 'plus' ?> me-2"></i>
          <?= $editPlan
              ? 'Edit FBA Plan — ' . fDate($editPlan['created_at'])
              : 'New Behavior Management Plan' ?>
        </span>
        <i class="fas fa-chevron-down"></i>
      </div>
      <div id="fbaFormBody" class="collapse show">
        <div style="padding:16px 18px 20px">

          <!-- Official header -->
          <div class="text-center mb-4 pb-2" style="border-bottom:2px solid #92400e">
            <div style="font-size:.66rem;font-weight:700;letter-spacing:1.1px;color:#92400e;text-transform:uppercase">
              Inclusive Learning Centre (ILC) · Bahria Model College
            </div>
            <div style="font-size:1rem;font-weight:700;color:#0f172a;margin:.3rem 0 .15rem">
              Behavior Management Plan
            </div>
            <div style="font-size:.74rem;color:#64748b;font-style:italic">
              Purpose: To identify challenging personality patterns, understand their triggers, and develop healthier
              emotional, interpersonal, and behavioural responses.
            </div>
          </div>

          <form method="POST" id="fbaForm">
            <input type="hidden" name="action"     value="<?= $editPlan ? 'update' : 'create' ?>">
            <input type="hidden" name="student_id" value="<?= $studentId ?>">
            <?php if ($editPlan): ?>
            <input type="hidden" name="plan_id"    value="<?= $editPlan['id'] ?>">
            <?php endif; ?>

            <!-- ── §1 Basic Information ── -->
            <div class="fba-sec mb-3">
              <div class="fba-sh">1. Basic Information</div>
              <div class="fba-sb">
                <div class="row g-2">
                  <div class="col-md-6">
                    <label class="fba-lbl">Name (Student)</label>
                    <input type="text" class="form-control form-control-sm bg-light"
                           value="<?= h($curStudent['name'] ?? '') ?>" readonly>
                  </div>
                  <div class="col-md-3">
                    <label class="fba-lbl">Age</label>
                    <input type="text" name="basic_age" class="form-control form-control-sm"
                           placeholder="e.g. 15" value="<?= h($fdBasic['age'] ?? '') ?>">
                  </div>
                  <div class="col-md-3">
                    <label class="fba-lbl">Date</label>
                    <input type="date" name="basic_date" class="form-control form-control-sm"
                           value="<?= h($fdBasic['date'] ?? date('Y-m-d')) ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="fba-lbl">Gender</label>
                    <select name="basic_gender" class="form-select form-select-sm">
                      <option value="">— Select —</option>
                      <?php foreach (['Male','Female','Other'] as $g): ?>
                      <option value="<?= $g ?>" <?= ($fdBasic['gender']??'')===$g?'selected':'' ?>><?= $g ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="fba-lbl">Occupation / Grade</label>
                    <input type="text" name="basic_occupation" class="form-control form-control-sm"
                           placeholder="e.g. Student, Grade 9"
                           value="<?= h($fdBasic['occupation'] ?? '') ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="fba-lbl">Session No.</label>
                    <input type="text" name="basic_session_no" class="form-control form-control-sm"
                           placeholder="e.g. 1, 2A…"
                           value="<?= h($fdBasic['session_no'] ?? '') ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="fba-lbl">Facilitator / Therapist</label>
                    <input type="text" class="form-control form-control-sm bg-light"
                           value="<?= h($user['name']) ?>" readonly>
                  </div>
                  <div class="col-md-6">
                    <label class="fba-lbl">Review Date</label>
                    <input type="date" name="basic_review_date" class="form-control form-control-sm"
                           value="<?= h($fdBasic['review_date'] ?? '') ?>">
                  </div>
                </div>
              </div>
            </div>

            <!-- ── §2 Areas of Concern ── -->
            <div class="fba-sec mb-3">
              <div class="fba-sh">2. Areas of Concern</div>
              <div class="fba-sb">
                <div class="row g-1">
                  <?php
                  $aocKeys = array_keys($areasOfConcernList);
                  $half    = (int)ceil(count($aocKeys) / 2);
                  $leftCol = array_slice($aocKeys, 0, $half);
                  $rightCol= array_slice($aocKeys, $half);
                  ?>
                  <div class="col-md-6">
                    <?php foreach ($leftCol as $k): ?>
                    <div class="form-check mb-1">
                      <input class="form-check-input" type="checkbox" name="areas[<?= $k ?>]"
                             id="area_<?= $k ?>" value="1"
                             <?= in_array($k, $fdAreas) ? 'checked' : '' ?>>
                      <label class="form-check-label" for="area_<?= $k ?>" style="font-size:.82rem">
                        <?= h($areasOfConcernList[$k]) ?>
                      </label>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <div class="col-md-6">
                    <?php foreach ($rightCol as $k): ?>
                    <div class="form-check mb-1">
                      <input class="form-check-input" type="checkbox" name="areas[<?= $k ?>]"
                             id="area_<?= $k ?>" value="1"
                             <?= in_array($k, $fdAreas) ? 'checked' : '' ?>>
                      <label class="form-check-label" for="area_<?= $k ?>" style="font-size:.82rem">
                        <?= h($areasOfConcernList[$k]) ?>
                      </label>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- ── §3 Functional Behaviour Assessment ── -->
            <div class="fba-sec mb-3">
              <div class="fba-sh d-flex justify-content-between align-items-center">
                <span>3. Functional Behaviour Assessment</span>
                <button type="button" class="btn btn-xs btn-outline-primary"
                        onclick="addFbaRow()" style="font-size:.72rem">+ Row</button>
              </div>
              <div class="fba-sb p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-bordered mb-0" style="font-size:.77rem" id="fbaTable">
                    <thead class="table-light">
                      <tr>
                        <th>Situation / Trigger</th>
                        <th>Thought or Feeling</th>
                        <th>Behaviour</th>
                        <th>Immediate Consequence</th>
                        <th>Healthy Alternative</th>
                        <th style="width:28px"></th>
                      </tr>
                    </thead>
                    <tbody id="fbaBody">
                      <?php foreach ($fdFbaRows as $fi => $row): ?>
                      <tr class="fba-row">
                        <td><textarea name="fba_trigger[]"      class="form-control form-control-sm border-0 p-0" rows="2" style="resize:vertical"><?= h($row['trigger'])     ?></textarea></td>
                        <td><textarea name="fba_thought[]"      class="form-control form-control-sm border-0 p-0" rows="2" style="resize:vertical"><?= h($row['thought'])     ?></textarea></td>
                        <td><textarea name="fba_behaviour[]"    class="form-control form-control-sm border-0 p-0" rows="2" style="resize:vertical"><?= h($row['behaviour'])   ?></textarea></td>
                        <td><textarea name="fba_consequence[]"  class="form-control form-control-sm border-0 p-0" rows="2" style="resize:vertical"><?= h($row['consequence']) ?></textarea></td>
                        <td><textarea name="fba_healthy_alt[]"  class="form-control form-control-sm border-0 p-0" rows="2" style="resize:vertical"><?= h($row['healthy_alt']) ?></textarea></td>
                        <td class="text-center align-top pt-2">
                          <?php if ($fi >= 3): ?>
                          <button type="button" class="btn btn-xs btn-outline-danger border-0"
                                  onclick="this.closest('tr').remove()">×</button>
                          <?php endif; ?>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- ── §4 Strategies Plan ── -->
            <div class="fba-sec mb-3">
              <div class="fba-sh">4. Strategies Plan</div>
              <div class="fba-sb p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-bordered mb-0" style="font-size:.78rem">
                    <thead class="table-light">
                      <tr>
                        <th style="width:28%">Target Area</th>
                        <th>Recommended Strategy</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($strategyLabels as $k => $label): ?>
                      <tr>
                        <td class="align-middle fw-semibold" style="background:#fafafa"><?= h($label) ?></td>
                        <td>
                          <textarea name="strategy_<?= $k ?>" class="form-control form-control-sm border-0 p-0"
                                    rows="2" style="resize:vertical"><?= h($fdStrat[$k] ?? $defaultStrategies[$k]) ?></textarea>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- ── §5 Weekly SMART Goals ── -->
            <div class="fba-sec mb-3">
              <div class="fba-sh d-flex justify-content-between align-items-center">
                <span>5. Weekly SMART Goals</span>
                <button type="button" class="btn btn-xs btn-outline-primary"
                        onclick="addGoalRow()" style="font-size:.72rem">+ Row</button>
              </div>
              <div class="fba-sb p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-bordered mb-0" style="font-size:.77rem" id="goalTable">
                    <thead class="table-light">
                      <tr>
                        <th>Goal</th><th>Baseline</th><th>Target</th><th>Strategy</th><th>Outcome</th>
                        <th style="width:28px"></th>
                      </tr>
                    </thead>
                    <tbody id="goalBody">
                      <?php foreach ($fdGoals as $gi => $g): ?>
                      <tr class="goal-row">
                        <td><input type="text" name="goal_goal[]"     class="form-control form-control-sm border-0 p-0" value="<?= h($g['goal'])     ?>"></td>
                        <td><input type="text" name="goal_baseline[]" class="form-control form-control-sm border-0 p-0" value="<?= h($g['baseline']) ?>"></td>
                        <td><input type="text" name="goal_target[]"   class="form-control form-control-sm border-0 p-0" value="<?= h($g['target'])   ?>"></td>
                        <td><input type="text" name="goal_strategy[]" class="form-control form-control-sm border-0 p-0" value="<?= h($g['strategy']) ?>"></td>
                        <td><input type="text" name="goal_outcome[]"  class="form-control form-control-sm border-0 p-0" value="<?= h($g['outcome'])  ?>"></td>
                        <td class="text-center">
                          <?php if ($gi >= 3): ?>
                          <button type="button" class="btn btn-xs btn-outline-danger border-0"
                                  onclick="this.closest('tr').remove()">×</button>
                          <?php endif; ?>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- ── §6 Daily Self-Monitoring ── -->
            <div class="fba-sec mb-3">
              <div class="fba-sh d-flex justify-content-between align-items-center">
                <span>6. Daily Self-Monitoring</span>
                <button type="button" class="btn btn-xs btn-outline-primary"
                        onclick="addDmRow()" style="font-size:.72rem">+ Row</button>
              </div>
              <div class="fba-sb p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-bordered mb-0" style="font-size:.77rem" id="dmTable">
                    <thead class="table-light">
                      <tr>
                        <th style="width:90px">Date</th><th>Trigger</th>
                        <th style="width:80px">Emotion (0–10)</th>
                        <th>Response</th><th>Healthy Strategy Used</th><th>Result</th>
                        <th style="width:28px"></th>
                      </tr>
                    </thead>
                    <tbody id="dmBody">
                      <?php foreach ($fdDM as $di => $dm): ?>
                      <tr class="dm-row">
                        <td><input type="date"  name="dm_date[]"             class="form-control form-control-sm border-0 p-0" style="width:90px" value="<?= h($dm['date'])             ?>"></td>
                        <td><input type="text"  name="dm_trigger[]"          class="form-control form-control-sm border-0 p-0" value="<?= h($dm['trigger'])          ?>"></td>
                        <td><input type="text"  name="dm_emotion[]"          class="form-control form-control-sm border-0 p-0" placeholder="0–10" value="<?= h($dm['emotion'])          ?>"></td>
                        <td><input type="text"  name="dm_response[]"         class="form-control form-control-sm border-0 p-0" value="<?= h($dm['response'])         ?>"></td>
                        <td><input type="text"  name="dm_healthy_strategy[]" class="form-control form-control-sm border-0 p-0" value="<?= h($dm['healthy_strategy']) ?>"></td>
                        <td><input type="text"  name="dm_result[]"           class="form-control form-control-sm border-0 p-0" value="<?= h($dm['result'])           ?>"></td>
                        <td class="text-center">
                          <?php if ($di >= 3): ?>
                          <button type="button" class="btn btn-xs btn-outline-danger border-0"
                                  onclick="this.closest('tr').remove()">×</button>
                          <?php endif; ?>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- ── §7 Progress Rating ── -->
            <div class="fba-sec mb-3">
              <div class="fba-sh">7. Progress Rating</div>
              <div class="fba-sb">
                <p style="font-size:.75rem;color:#64748b;margin-bottom:8px">
                  Rate each area from 0–4:
                  <strong>0</strong> = Not observed &nbsp;
                  <strong>1</strong> = Severe difficulty &nbsp;
                  <strong>2</strong> = Moderate difficulty &nbsp;
                  <strong>3</strong> = Mild difficulty &nbsp;
                  <strong>4</strong> = Good control
                </p>
                <div class="table-responsive">
                  <table class="table table-sm table-bordered mb-0" style="font-size:.8rem">
                    <thead class="table-light">
                      <tr>
                        <th style="width:50%">Area</th>
                        <th class="text-center" style="width:120px">Initial Rating (0–4)</th>
                        <th class="text-center" style="width:120px">Review Rating (0–4)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($progressAreas as $k => $label): ?>
                      <tr>
                        <td class="align-middle"><?= h($label) ?></td>
                        <td class="text-center">
                          <input type="number" name="pr_<?= $k ?>_initial" min="0" max="4"
                                 class="form-control form-control-sm text-center"
                                 style="width:70px;margin:0 auto"
                                 value="<?= h($fdPR[$k]['initial'] ?? '') ?>">
                        </td>
                        <td class="text-center">
                          <input type="number" name="pr_<?= $k ?>_review" min="0" max="4"
                                 class="form-control form-control-sm text-center"
                                 style="width:70px;margin:0 auto"
                                 value="<?= h($fdPR[$k]['review'] ?? '') ?>">
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- ── §8 Review & Follow-Up (editable table) ── -->
            <div class="fba-sec mb-3">
              <div class="fba-sh d-flex justify-content-between align-items-center">
                <span>8. Review &amp; Follow-Up</span>
                <button type="button" onclick="addReviewRow()"
                        class="btn btn-xs"
                        style="background:rgba(255,255,255,.22);color:#fff;font-size:.72rem;padding:2px 9px;border:1px solid rgba(255,255,255,.35)">
                  <i class="fas fa-plus me-1"></i>Add Review
                </button>
              </div>
              <div class="fba-sb" style="padding:0">
                <div class="table-responsive">
                  <table class="table table-sm mb-0" style="font-size:.8rem">
                    <thead style="background:#fef9c3">
                      <tr>
                        <th style="width:100px;padding:6px 8px">Date</th>
                        <th style="padding:6px 8px">Review Note / Action Taken</th>
                        <th style="width:22%;padding:6px 8px">Outcome / Observation</th>
                        <th style="width:22%;padding:6px 8px">Next Steps</th>
                        <th style="width:36px;padding:6px 8px"></th>
                      </tr>
                    </thead>
                    <tbody id="reviewBody">
                      <?php foreach ($fdReviews as $rv): ?>
                      <tr class="rv-row">
                        <td style="padding:4px 6px;vertical-align:top">
                          <input type="date" name="rv_date[]"
                                 class="form-control form-control-sm border-0 p-0"
                                 style="min-width:88px"
                                 value="<?= h($rv['date']) ?>">
                        </td>
                        <td style="padding:4px 6px;vertical-align:top">
                          <textarea name="rv_note[]"
                                    class="form-control form-control-sm border-0 p-0"
                                    rows="2" style="resize:vertical"><?= h($rv['note']) ?></textarea>
                        </td>
                        <td style="padding:4px 6px;vertical-align:top">
                          <textarea name="rv_outcome[]"
                                    class="form-control form-control-sm border-0 p-0"
                                    rows="2" style="resize:vertical"><?= h($rv['outcome']) ?></textarea>
                        </td>
                        <td style="padding:4px 6px;vertical-align:top">
                          <textarea name="rv_next_steps[]"
                                    class="form-control form-control-sm border-0 p-0"
                                    rows="2" style="resize:vertical"><?= h($rv['next_steps']) ?></textarea>
                        </td>
                        <td class="text-center" style="padding:4px 6px;vertical-align:top">
                          <button type="button" class="btn btn-xs btn-outline-danger border-0"
                                  onclick="this.closest('tr').remove()">×</button>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- ── §9 Notes ── -->
            <div class="fba-sec mb-3">
              <div class="fba-sh">9. Notes</div>
              <div class="fba-sb">
                <textarea name="notes" class="form-control form-control-sm" rows="5"
                          placeholder="Additional notes, observations, or follow-up comments…"><?= h($fdNotes) ?></textarea>
              </div>
            </div>

            <div class="d-flex gap-2 pt-1">
              <button type="submit" class="btn btn-success btn-sm">
                <i class="fas fa-save me-1"></i><?= $editPlan ? 'Update Plan' : 'Save FBA Plan' ?>
              </button>
              <?php if ($editPlan): ?>
              <a href="?student_id=<?= $studentId ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right: FBA plans list -->
  <div class="col-xl-6">
    <?php if ($studentId && $curStudent): ?>
    <div class="sec-card">
      <div class="sec-card-header">
        <i class="fas fa-clipboard-check me-2"></i>FBA Plans — <?= h($curStudent['name']) ?>
        <span class="badge bg-secondary ms-2"><?= count($plans) ?></span>
      </div>

      <?php if (empty($plans)): ?>
      <div style="padding:36px;text-align:center;color:var(--t2);font-size:.85rem">
        No FBA plans yet. Use the form to create the first plan.
      </div>
      <?php else: ?>
      <div style="padding:12px 16px">
        <?php foreach ($plans as $p):
          $pfd    = json_decode($p['form_data'], true) ?? [];
          $areas  = $pfd['areas_of_concern'] ?? [];
          $aLabels= array_map(fn($k) => $areasOfConcernList[$k] ?? $k, $areas);
          $notes  = $pfd['notes'] ?? '';
          $session= $p['session_no'] ? 'Session ' . $p['session_no'] : null;
        ?>
        <div class="mb-3 p-3" style="background:#f7f9fb;border-radius:8px;border:1px solid var(--border)">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <span class="badge" style="background:#92400e;font-size:.75rem">
                <?= $p['session_date'] ? date('d M Y', strtotime($p['session_date'])) : fDate($p['created_at']) ?>
              </span>
              <?php if ($session): ?>
              <span class="badge bg-warning text-dark ms-1" style="font-size:.7rem"><?= h($session) ?></span>
              <?php endif; ?>
              <span style="font-size:.74rem;color:var(--t2);margin-left:8px">
                <?= h($p['recorder_name']) ?>
              </span>
            </div>
            <div class="d-flex gap-1">
              <a href="<?= url('/portal/ilc/fba-pdf.php?id=' . $p['id']) ?>"
                 target="_blank" class="btn btn-xs btn-outline-success" title="View / Print PDF">
                <i class="fas fa-file-pdf me-1"></i>PDF
              </a>
              <a href="?student_id=<?= $studentId ?>&edit=<?= $p['id'] ?>"
                 class="btn btn-xs btn-outline-primary" title="Edit">
                <i class="fas fa-edit"></i>
              </a>
              <form method="POST" class="d-inline" onsubmit="return confirm('Delete this FBA plan?')">
                <input type="hidden" name="action"     value="delete">
                <input type="hidden" name="student_id" value="<?= $studentId ?>">
                <input type="hidden" name="plan_id"    value="<?= $p['id'] ?>">
                <button class="btn btn-xs btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </div>

          <?php if ($aLabels): ?>
          <div style="font-size:.78rem;margin-bottom:5px">
            <span class="text-muted">Areas of concern:</span>
            <?php foreach ($aLabels as $al): ?>
            <span class="badge" style="background:#1e40af;font-size:.68rem;margin:1px"><?= h($al) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if ($p['review_date']): ?>
          <div style="font-size:.76rem;color:#0369a1">
            <i class="fas fa-calendar-check me-1"></i>Review: <?= date('d M Y', strtotime($p['review_date'])) ?>
          </div>
          <?php endif; ?>

          <?php if ($notes): ?>
          <div style="font-size:.76rem;margin-top:4px;color:var(--t2)">
            <i class="fas fa-sticky-note me-1"></i><?= h(mb_strimwidth($notes, 0, 120, '…')) ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php elseif (!$studentId): ?>
    <div class="sec-card">
      <div style="padding:60px;text-align:center;color:var(--t2)">
        <i class="fas fa-clipboard-check fa-2x mb-3 d-block" style="opacity:.15"></i>
        Select a student to view or add FBA Behavior Management Plans.
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>

</div></div></div>

<style>
.fba-sec { border:1px solid #e2e8f0; border-radius:7px; overflow:hidden; }
.fba-sh  { font-size:.78rem; font-weight:700; color:#fff; background:#92400e;
           padding:5px 12px; letter-spacing:.4px; text-transform:uppercase; }
.fba-sb  { padding:10px 12px; }
.fba-lbl { font-size:.78rem; font-weight:600; margin-bottom:2px; display:block; }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function addFbaRow() {
  var body = document.getElementById('fbaBody');
  var tr   = document.createElement('tr');
  tr.className = 'fba-row';
  tr.innerHTML =
    '<td><textarea name="fba_trigger[]"     class="form-control form-control-sm border-0 p-0" rows="2" style="resize:vertical"></textarea></td>' +
    '<td><textarea name="fba_thought[]"     class="form-control form-control-sm border-0 p-0" rows="2" style="resize:vertical"></textarea></td>' +
    '<td><textarea name="fba_behaviour[]"   class="form-control form-control-sm border-0 p-0" rows="2" style="resize:vertical"></textarea></td>' +
    '<td><textarea name="fba_consequence[]" class="form-control form-control-sm border-0 p-0" rows="2" style="resize:vertical"></textarea></td>' +
    '<td><textarea name="fba_healthy_alt[]" class="form-control form-control-sm border-0 p-0" rows="2" style="resize:vertical"></textarea></td>' +
    '<td class="text-center align-top pt-2"><button type="button" class="btn btn-xs btn-outline-danger border-0" onclick="this.closest(\'tr\').remove()">×</button></td>';
  body.appendChild(tr);
}
function addGoalRow() {
  var body = document.getElementById('goalBody');
  var tr   = document.createElement('tr');
  tr.className = 'goal-row';
  tr.innerHTML =
    '<td><input type="text" name="goal_goal[]"     class="form-control form-control-sm border-0 p-0"></td>' +
    '<td><input type="text" name="goal_baseline[]" class="form-control form-control-sm border-0 p-0"></td>' +
    '<td><input type="text" name="goal_target[]"   class="form-control form-control-sm border-0 p-0"></td>' +
    '<td><input type="text" name="goal_strategy[]" class="form-control form-control-sm border-0 p-0"></td>' +
    '<td><input type="text" name="goal_outcome[]"  class="form-control form-control-sm border-0 p-0"></td>' +
    '<td class="text-center"><button type="button" class="btn btn-xs btn-outline-danger border-0" onclick="this.closest(\'tr\').remove()">×</button></td>';
  body.appendChild(tr);
}
function addDmRow() {
  var body = document.getElementById('dmBody');
  var tr   = document.createElement('tr');
  tr.className = 'dm-row';
  tr.innerHTML =
    '<td><input type="date" name="dm_date[]"             class="form-control form-control-sm border-0 p-0" style="width:90px"></td>' +
    '<td><input type="text" name="dm_trigger[]"          class="form-control form-control-sm border-0 p-0"></td>' +
    '<td><input type="text" name="dm_emotion[]"          class="form-control form-control-sm border-0 p-0" placeholder="0–10"></td>' +
    '<td><input type="text" name="dm_response[]"         class="form-control form-control-sm border-0 p-0"></td>' +
    '<td><input type="text" name="dm_healthy_strategy[]" class="form-control form-control-sm border-0 p-0"></td>' +
    '<td><input type="text" name="dm_result[]"           class="form-control form-control-sm border-0 p-0"></td>' +
    '<td class="text-center"><button type="button" class="btn btn-xs btn-outline-danger border-0" onclick="this.closest(\'tr\').remove()">×</button></td>';
  body.appendChild(tr);
}
function addReviewRow() {
  var body = document.getElementById('reviewBody');
  var tr   = document.createElement('tr');
  tr.className = 'rv-row';
  tr.innerHTML =
    '<td style="padding:4px 6px;vertical-align:top"><input type="date" name="rv_date[]" class="form-control form-control-sm border-0 p-0" style="min-width:88px"></td>' +
    '<td style="padding:4px 6px;vertical-align:top"><textarea name="rv_note[]"       class="form-control form-control-sm border-0 p-0" rows="2" style="resize:vertical"></textarea></td>' +
    '<td style="padding:4px 6px;vertical-align:top"><textarea name="rv_outcome[]"    class="form-control form-control-sm border-0 p-0" rows="2" style="resize:vertical"></textarea></td>' +
    '<td style="padding:4px 6px;vertical-align:top"><textarea name="rv_next_steps[]" class="form-control form-control-sm border-0 p-0" rows="2" style="resize:vertical"></textarea></td>' +
    '<td class="text-center" style="padding:4px 6px;vertical-align:top"><button type="button" class="btn btn-xs btn-outline-danger border-0" onclick="this.closest(\'tr\').remove()">×</button></td>';
  body.appendChild(tr);
}
</script>
</body></html>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp');
$db   = getDB();

// Column / table availability
$tableExists = false;
$abaColExists = false;
try { $db->query('SELECT 1 FROM behaviour_therapy_reports LIMIT 1'); $tableExists = true; } catch (Exception $e) {}
if ($tableExists) {
    try { $db->query('SELECT aba_data FROM behaviour_therapy_reports LIMIT 0'); $abaColExists = true; } catch (Exception $e) {}
}

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action    = $_POST['action']     ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);
    $month     = $_POST['month']      ?? '';
    $monthDate = $month ? $month . '-01' : null;

    if (in_array($action, ['create', 'update']) && $studentId && $monthDate) {

        // ── Build ABA JSON ────────────────────────────────────────
        $client = [
            'age'       => trim($_POST['client_age']       ?? ''),
            'diagnosis' => trim($_POST['client_diagnosis'] ?? ''),
        ];
        $assessment = [
            'skill_level'        => trim($_POST['assess_skill_level']   ?? ''),
            'behavior_concerns'  => trim($_POST['assess_behavior']      ?? ''),
            'strengths'          => trim($_POST['assess_strengths']     ?? ''),
            'barriers'           => trim($_POST['assess_barriers']      ?? ''),
        ];
        $targetBehaviors = array_values(array_filter(
            array_map('trim', (array)($_POST['target_behavior'] ?? []))
        ));
        $goals = array_values(array_filter(
            array_map('trim', (array)($_POST['goal'] ?? []))
        ));
        $teaching = [
            'dtt'                => !empty($_POST['teaching_dtt']),
            'net'                => !empty($_POST['teaching_net']),
            'prompting_strategy' => trim($_POST['teaching_prompting'] ?? ''),
            'reinforcement_type' => trim($_POST['teaching_reinforcement'] ?? ''),
        ];
        // Build trials (skip fully-empty rows)
        $trials = [];
        foreach ((array)($_POST['trial_target'] ?? []) as $i => $target) {
            $response = trim($_POST['trial_response'][$i] ?? '');
            if (trim($target) === '' && $response === '') continue;
            $trials[] = [
                'target'        => trim($target),
                'prompt_level'  => trim($_POST['trial_prompt'][$i]    ?? ''),
                'response'      => $response,
                'reinforcement' => trim($_POST['trial_reinf'][$i]     ?? ''),
                'comments'      => trim($_POST['trial_comments'][$i]  ?? ''),
            ];
        }
        $progressNotes = [
            'session_summary' => trim($_POST['prog_session_summary'] ?? ''),
            'next_steps'      => trim($_POST['prog_next_steps']      ?? ''),
        ];

        $abaJson = json_encode([
            'client'           => $client,
            'assessment'       => $assessment,
            'target_behaviors' => $targetBehaviors,
            'goals'            => $goals,
            'teaching'         => $teaching,
            'trials'           => $trials,
            'progress_notes'   => $progressNotes,
        ], JSON_UNESCAPED_UNICODE);

        // Simple fields used for quick-view listing
        $therapistNotes  = $progressNotes['session_summary'] ?: null;
        $progressSummary = $assessment['skill_level']        ?: null;
        $goalsNextMonth  = $progressNotes['next_steps']      ?: null;

        if ($action === 'update') {
            $id = (int)($_POST['report_id'] ?? 0);
            if ($abaColExists) {
                $db->prepare(
                    'UPDATE behaviour_therapy_reports
                     SET therapist_notes=?,progress_summary=?,goals_next_month=?,aba_data=?,recorded_by=?
                     WHERE id=?'
                )->execute([$therapistNotes, $progressSummary, $goalsNextMonth, $abaJson, $user['id'], $id]);
            } else {
                $db->prepare(
                    'UPDATE behaviour_therapy_reports
                     SET therapist_notes=?,progress_summary=?,goals_next_month=?,recorded_by=?
                     WHERE id=?'
                )->execute([$therapistNotes, $progressSummary, $goalsNextMonth, $user['id'], $id]);
            }
            logActivity($user['id'], 'btherapy_update', "Updated ABA report #$id");
            setFlash('success', 'ABA report updated successfully.');
        } else {
            if ($abaColExists) {
                $db->prepare(
                    'INSERT INTO behaviour_therapy_reports
                     (student_id,month,therapist_notes,progress_summary,goals_next_month,aba_data,recorded_by)
                     VALUES (?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                       therapist_notes=VALUES(therapist_notes),
                       progress_summary=VALUES(progress_summary),
                       goals_next_month=VALUES(goals_next_month),
                       aba_data=VALUES(aba_data),
                       recorded_by=VALUES(recorded_by)'
                )->execute([$studentId, $monthDate, $therapistNotes, $progressSummary,
                             $goalsNextMonth, $abaJson, $user['id']]);
            } else {
                $db->prepare(
                    'INSERT INTO behaviour_therapy_reports
                     (student_id,month,therapist_notes,progress_summary,goals_next_month,recorded_by)
                     VALUES (?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                       therapist_notes=VALUES(therapist_notes),
                       progress_summary=VALUES(progress_summary),
                       goals_next_month=VALUES(goals_next_month),
                       recorded_by=VALUES(recorded_by)'
                )->execute([$studentId, $monthDate, $therapistNotes, $progressSummary,
                             $goalsNextMonth, $user['id']]);
            }
            logActivity($user['id'], 'btherapy_create', "Saved ABA report for student #$studentId ($month)");
            setFlash('success', 'ABA report saved successfully.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['report_id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM behaviour_therapy_reports WHERE id=?')->execute([$id]);
            logActivity($user['id'], 'btherapy_delete', "Deleted ABA report #$id");
            setFlash('success', 'Report deleted.');
        }
    }

    $redir = '/portal/ilc/behaviour-therapy.php' . ($studentId ? "?student_id=$studentId" : '');
    redirect($redir);
}

// ── Fetch ILC students ────────────────────────────────────────────────────────
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
} catch (Exception $e) {
    $students = [];
}

$reports    = [];
$editReport = null;
if ($studentId && $tableExists) {
    $cols = $abaColExists ? 'r.*, u.name AS recorder_name' : 'r.*, u.name AS recorder_name';
    $st = $db->prepare(
        "SELECT $cols
         FROM behaviour_therapy_reports r
         JOIN users u ON u.id = r.recorded_by
         WHERE r.student_id = ?
         ORDER BY r.month DESC"
    );
    $st->execute([$studentId]);
    $reports = $st->fetchAll();

    if ($editId) {
        foreach ($reports as $r) {
            if ($r['id'] == $editId) { $editReport = $r; break; }
        }
    }
}

$curStudent = null;
if ($studentId) {
    foreach ($students as $s) {
        if ((int)$s['id'] === $studentId) { $curStudent = $s; break; }
    }
}

// Pre-fill ABA data for edit
$aba = [];
if ($editReport && !empty($editReport['aba_data'])) {
    $aba = json_decode($editReport['aba_data'], true) ?? [];
}
// Ensure sub-arrays exist with defaults
$abaClient   = $aba['client']           ?? ['age'=>'','diagnosis'=>''];
$abaAssess   = $aba['assessment']       ?? ['skill_level'=>'','behavior_concerns'=>'','strengths'=>'','barriers'=>''];
$abaBehaviors= $aba['target_behaviors'] ?? ['',''];
$abaGoals    = $aba['goals']            ?? ['','',''];
$abaTeach    = $aba['teaching']         ?? ['dtt'=>true,'net'=>true,'prompting_strategy'=>'','reinforcement_type'=>''];
$abaTrials   = $aba['trials']           ?? array_fill(0, 4, ['target'=>'','prompt_level'=>'','response'=>'','reinforcement'=>'','comments'=>'']);
$abaProg     = $aba['progress_notes']   ?? ['session_summary'=>'','next_steps'=>''];

// Ensure at least minimum rows
while (count($abaBehaviors) < 2) $abaBehaviors[] = '';
while (count($abaGoals)     < 3) $abaGoals[]     = '';
while (count($abaTrials)    < 4) $abaTrials[]    = ['target'=>'','prompt_level'=>'','response'=>'','reinforcement'=>'','comments'=>''];

pageHead('Behaviour Therapy — ABA Reports', 'ilc_vp');
$links = getIlcLinks();
?>
<div class="portal-wrap">
<?php sidebar('ilc_vp', 'behaviour-therapy', $links, $user); ?>
<div class="main-area">
<?php topbar('Behaviour Therapy — ABA Reports', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  <strong>Migration not applied.</strong> Run <code>database/migrations/ilc_features.sql</code> first.
</div>
<?php else: ?>

<?php if (!$abaColExists): ?>
<div class="alert alert-info" style="font-size:.82rem">
  <i class="fas fa-info-circle me-1"></i>
  For full ABA data storage, run <code>database/migrations/aba_therapy.sql</code>.
  Basic monthly reports still work without it.
</div>
<?php endif; ?>

<!-- ILC branding strip -->
<div class="d-flex align-items-center gap-3 mb-3 p-3"
     style="background:linear-gradient(90deg,#ecfeff,#f0fdf4);border-radius:10px;border:1px solid #a5f3fc;">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC"
       style="width:40px;height:40px;object-fit:contain;flex-shrink:0;" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#0891b2;letter-spacing:.8px;text-transform:uppercase">
      Behaviour Therapy — ABA Reports
    </div>
    <div style="font-size:.78rem;color:#475569">Inclusive Learning Centre · Bahria Model College Bin Qasim</div>
  </div>
</div>

<div class="row g-3">
  <!-- Left: Student selector + ABA form -->
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
    <!-- ABA form card -->
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center"
           data-bs-toggle="collapse" data-bs-target="#abaFormBody" style="cursor:pointer">
        <span>
          <i class="fas fa-<?= $editReport ? 'edit' : 'plus' ?> me-2"></i>
          <?= $editReport
              ? 'Edit ABA Report — ' . date('F Y', strtotime($editReport['month']))
              : 'New ABA Therapy Program Report' ?>
        </span>
        <i class="fas fa-chevron-down"></i>
      </div>
      <div id="abaFormBody" class="collapse <?= $editReport ? 'show' : 'show' ?>">
        <div style="padding:16px 18px 20px">

          <!-- Official form header -->
          <div class="text-center mb-3 pb-2" style="border-bottom:2px solid #0891b2">
            <div style="font-size:.65rem;font-weight:700;letter-spacing:1.2px;color:#0891b2;text-transform:uppercase">
              Bahria Model School &amp; College · Bin Qasim · Inclusive Learning Centre
            </div>
            <div style="font-size:1rem;font-weight:700;color:#0f172a;margin:.3rem 0 .15rem">ABA THERAPY PROGRAM FORMAT</div>
            <div style="font-size:.74rem;color:#64748b">Applied Behaviour Analysis — Monthly Report</div>
          </div>

          <form method="POST" id="abaForm">
            <input type="hidden" name="action"     value="<?= $editReport ? 'update' : 'create' ?>">
            <input type="hidden" name="student_id" value="<?= $studentId ?>">
            <?php if ($editReport): ?>
            <input type="hidden" name="report_id" value="<?= $editReport['id'] ?>">
            <input type="hidden" name="month"      value="<?= substr($editReport['month'], 0, 7) ?>">
            <?php endif; ?>

            <!-- ── Section 1: Client Information ── -->
            <div class="aba-section mb-3">
              <div class="aba-sec-head">1. Client Information</div>
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="aba-lbl">Student Name</label>
                  <input type="text" class="form-control form-control-sm bg-light"
                         value="<?= h($curStudent['name'] ?? '') ?>" readonly>
                </div>
                <?php if (!$editReport): ?>
                <div class="col-md-6">
                  <label class="aba-lbl">Report Month <span class="text-danger">*</span></label>
                  <input type="month" name="month" class="form-control form-control-sm"
                         value="<?= date('Y-m') ?>" required>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                  <label class="aba-lbl">Age</label>
                  <input type="text" name="client_age" class="form-control form-control-sm"
                         placeholder="e.g. 10" value="<?= h($abaClient['age']) ?>">
                </div>
                <div class="col-md-<?= $editReport ? '9' : '9' ?>">
                  <label class="aba-lbl">Diagnosis</label>
                  <input type="text" name="client_diagnosis" class="form-control form-control-sm"
                         placeholder="e.g. ASD, ADHD…" value="<?= h($abaClient['diagnosis']) ?>">
                </div>
                <div class="col-md-6">
                  <label class="aba-lbl">Therapist / Recorded By</label>
                  <input type="text" class="form-control form-control-sm bg-light"
                         value="<?= h($user['name']) ?>" readonly>
                </div>
                <div class="col-md-6">
                  <label class="aba-lbl">Date</label>
                  <input type="text" class="form-control form-control-sm bg-light"
                         value="<?= date('d F Y') ?>" readonly>
                </div>
              </div>
            </div>

            <!-- ── Section 2: Assessment Summary ── -->
            <div class="aba-section mb-3">
              <div class="aba-sec-head">2. Assessment Summary</div>
              <div class="row g-2">
                <div class="col-12">
                  <label class="aba-lbl">Current Skill Level</label>
                  <textarea name="assess_skill_level" class="form-control form-control-sm" rows="2"
                            placeholder="Describe current skill level…"><?= h($abaAssess['skill_level']) ?></textarea>
                </div>
                <div class="col-12">
                  <label class="aba-lbl">Behavior Concerns</label>
                  <textarea name="assess_behavior" class="form-control form-control-sm" rows="2"
                            placeholder="List key behavior concerns…"><?= h($abaAssess['behavior_concerns']) ?></textarea>
                </div>
                <div class="col-md-6">
                  <label class="aba-lbl">Strengths</label>
                  <textarea name="assess_strengths" class="form-control form-control-sm" rows="2"
                            placeholder="Student's strengths…"><?= h($abaAssess['strengths']) ?></textarea>
                </div>
                <div class="col-md-6">
                  <label class="aba-lbl">Barriers to Learning</label>
                  <textarea name="assess_barriers" class="form-control form-control-sm" rows="2"
                            placeholder="Barriers observed…"><?= h($abaAssess['barriers']) ?></textarea>
                </div>
              </div>
            </div>

            <!-- ── Section 3: Target Behaviors ── -->
            <div class="aba-section mb-3">
              <div class="aba-sec-head d-flex justify-content-between align-items-center">
                <span>3. Target Behaviors</span>
                <button type="button" class="btn btn-xs btn-outline-primary" onclick="addBehavior()"
                        style="font-size:.72rem">+ Add</button>
              </div>
              <div id="behaviors-wrap">
                <?php foreach ($abaBehaviors as $i => $b): ?>
                <div class="d-flex gap-1 mb-1 behavior-row">
                  <span class="aba-bullet">•</span>
                  <input type="text" name="target_behavior[]" class="form-control form-control-sm"
                         placeholder="Behavior <?= $i + 1 ?>…" value="<?= h($b) ?>">
                  <?php if ($i >= 2): ?>
                  <button type="button" class="btn btn-xs btn-outline-danger" onclick="this.closest('.behavior-row').remove()">×</button>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- ── Section 4: SMART Goals ── -->
            <div class="aba-section mb-3">
              <div class="aba-sec-head d-flex justify-content-between align-items-center">
                <span>4. ABA Goals (SMART Goals)</span>
                <button type="button" class="btn btn-xs btn-outline-primary" onclick="addGoal()"
                        style="font-size:.72rem">+ Add</button>
              </div>
              <div id="goals-wrap">
                <?php foreach ($abaGoals as $i => $g): ?>
                <div class="d-flex gap-1 mb-1 goal-row">
                  <span class="aba-num"><?= $i + 1 ?>.</span>
                  <input type="text" name="goal[]" class="form-control form-control-sm"
                         placeholder="SMART goal…" value="<?= h($g) ?>">
                  <?php if ($i >= 3): ?>
                  <button type="button" class="btn btn-xs btn-outline-danger" onclick="this.closest('.goal-row').remove()">×</button>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- ── Section 5: Teaching Procedures ── -->
            <div class="aba-section mb-3">
              <div class="aba-sec-head">5. Teaching Procedures</div>
              <div class="row g-2">
                <div class="col-auto">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="teaching_dtt" value="1"
                           id="chk_dtt" <?= $abaTeach['dtt'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="chk_dtt" style="font-size:.8rem">
                      Discrete Trial Training (DTT)
                    </label>
                  </div>
                </div>
                <div class="col-auto">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="teaching_net" value="1"
                           id="chk_net" <?= $abaTeach['net'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="chk_net" style="font-size:.8rem">
                      Natural Environment Teaching (NET)
                    </label>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="aba-lbl">Prompting Strategy</label>
                  <input type="text" name="teaching_prompting" class="form-control form-control-sm"
                         placeholder="e.g. Verbal + Physical" value="<?= h($abaTeach['prompting_strategy']) ?>">
                </div>
                <div class="col-md-6">
                  <label class="aba-lbl">Reinforcement Type</label>
                  <input type="text" name="teaching_reinforcement" class="form-control form-control-sm"
                         placeholder="e.g. Token economy, Praise" value="<?= h($abaTeach['reinforcement_type']) ?>">
                </div>
              </div>
            </div>

            <!-- ── Section 6 & 7: Data Collection ── -->
            <div class="aba-section mb-3">
              <div class="aba-sec-head d-flex justify-content-between align-items-center">
                <span>6 &amp; 7. ABA Data Collection Table</span>
                <button type="button" class="btn btn-xs btn-outline-primary" onclick="addTrialRow()"
                        style="font-size:.72rem">+ Row</button>
              </div>
              <p style="font-size:.75rem;color:#64748b;margin-bottom:8px">
                Daily data to record accuracy, prompts, and mastery.
              </p>
              <div class="table-responsive">
                <table class="table table-sm table-bordered" style="font-size:.78rem" id="trialsTable">
                  <thead class="table-light">
                    <tr>
                      <th style="width:40px">#</th>
                      <th>Target</th>
                      <th style="width:110px">Prompt Level</th>
                      <th style="width:110px">Response</th>
                      <th style="width:110px">Reinforcement</th>
                      <th>Comments</th>
                      <th style="width:32px"></th>
                    </tr>
                  </thead>
                  <tbody id="trialsBody">
                    <?php foreach ($abaTrials as $ti => $trial): ?>
                    <tr class="trial-row">
                      <td class="text-center align-middle trial-num" style="font-weight:600"><?= $ti + 1 ?></td>
                      <td><input type="text" name="trial_target[]"   class="form-control form-control-sm border-0 p-0" value="<?= h($trial['target'])        ?>"></td>
                      <td><input type="text" name="trial_prompt[]"   class="form-control form-control-sm border-0 p-0" placeholder="e.g. VP, PP, FP" value="<?= h($trial['prompt_level']) ?>"></td>
                      <td><input type="text" name="trial_response[]" class="form-control form-control-sm border-0 p-0" placeholder="+/−/NR" value="<?= h($trial['response'])      ?>"></td>
                      <td><input type="text" name="trial_reinf[]"    class="form-control form-control-sm border-0 p-0" value="<?= h($trial['reinforcement']) ?>"></td>
                      <td><input type="text" name="trial_comments[]" class="form-control form-control-sm border-0 p-0" value="<?= h($trial['comments'])      ?>"></td>
                      <td class="text-center">
                        <?php if ($ti >= 4): ?>
                        <button type="button" class="btn btn-xs btn-outline-danger border-0 p-0" onclick="removeTrialRow(this)" title="Remove">×</button>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- ── Section 8: Progress Notes ── -->
            <div class="aba-section mb-3">
              <div class="aba-sec-head">8. Progress Notes</div>
              <div class="row g-2">
                <div class="col-12">
                  <label class="aba-lbl">Session Summary</label>
                  <textarea name="prog_session_summary" class="form-control form-control-sm" rows="3"
                            placeholder="Summary of the session, key observations…"><?= h($abaProg['session_summary']) ?></textarea>
                </div>
                <div class="col-12">
                  <label class="aba-lbl">Next Steps</label>
                  <textarea name="prog_next_steps" class="form-control form-control-sm" rows="2"
                            placeholder="Goals and targets for the next session…"><?= h($abaProg['next_steps']) ?></textarea>
                </div>
              </div>
            </div>

            <div class="d-flex gap-2 pt-1">
              <button type="submit" class="btn btn-success btn-sm">
                <i class="fas fa-save me-1"></i><?= $editReport ? 'Update ABA Report' : 'Save ABA Report' ?>
              </button>
              <?php if ($editReport): ?>
              <a href="?student_id=<?= $studentId ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right: Reports list -->
  <div class="col-xl-6">
    <?php if ($studentId && $curStudent): ?>
    <div class="sec-card">
      <div class="sec-card-header">
        <i class="fas fa-brain me-2"></i>ABA Reports — <?= h($curStudent['name']) ?>
        <span class="badge bg-secondary ms-2"><?= count($reports) ?></span>
      </div>

      <?php if (empty($reports)): ?>
      <div style="padding:36px;text-align:center;color:var(--t2);font-size:.85rem">
        No ABA reports yet for this student. Use the form to create the first report.
      </div>
      <?php else: ?>
      <div style="padding:12px 16px">
        <?php foreach ($reports as $r): ?>
        <div class="mb-3 p-3" style="background:#f7f9fb;border-radius:8px;border:1px solid var(--border)">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <span class="badge" style="background:#0891b2;font-size:.75rem">
                <?= date('F Y', strtotime($r['month'])) ?>
              </span>
              <?php if ($abaColExists && !empty($r['aba_data'])): ?>
              <span class="badge bg-success ms-1" style="font-size:.7rem">ABA</span>
              <?php endif; ?>
              <span style="font-size:.74rem;color:var(--t2);margin-left:8px">
                <?= h($r['recorder_name']) ?> · <?= fDate($r['created_at']) ?>
              </span>
            </div>
            <div class="d-flex gap-1 flex-wrap">
              <?php if ($abaColExists && !empty($r['aba_data'])): ?>
              <a href="<?= url('/portal/ilc/aba-report-pdf.php?id=' . $r['id']) ?>"
                 target="_blank" class="btn btn-xs btn-outline-success" title="View / Print PDF">
                <i class="fas fa-file-pdf me-1"></i>PDF
              </a>
              <?php endif; ?>
              <a href="?student_id=<?= $studentId ?>&edit=<?= $r['id'] ?>"
                 class="btn btn-xs btn-outline-primary" title="Edit">
                <i class="fas fa-edit"></i>
              </a>
              <form method="POST" class="d-inline" onsubmit="return confirm('Delete this ABA report?')">
                <input type="hidden" name="action"     value="delete">
                <input type="hidden" name="student_id" value="<?= $studentId ?>">
                <input type="hidden" name="report_id"  value="<?= $r['id'] ?>">
                <button class="btn btn-xs btn-outline-danger" title="Delete">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </div>
          </div>

          <?php
          $ad = (!empty($r['aba_data'])) ? (json_decode($r['aba_data'], true) ?? []) : [];
          $diag = $ad['client']['diagnosis'] ?? '';
          $skills = $ad['assessment']['skill_level'] ?? $r['progress_summary'] ?? '';
          $summary = $ad['progress_notes']['session_summary'] ?? $r['therapist_notes'] ?? '';
          $nextSteps = $ad['progress_notes']['next_steps'] ?? $r['goals_next_month'] ?? '';
          $behaviors = $ad['target_behaviors'] ?? [];
          ?>
          <?php if ($diag): ?>
          <div style="font-size:.79rem;margin-bottom:4px">
            <span class="text-muted">Diagnosis:</span> <strong><?= h($diag) ?></strong>
          </div>
          <?php endif; ?>
          <?php if (!empty($behaviors)): ?>
          <div style="font-size:.79rem;margin-bottom:4px">
            <span class="text-muted">Target behaviors:</span>
            <?= h(implode(', ', array_filter($behaviors))) ?>
          </div>
          <?php endif; ?>
          <?php if ($summary): ?>
          <div style="font-size:.79rem;margin-bottom:4px">
            <span class="text-muted">Session summary:</span> <?= nl2br(h($summary)) ?>
          </div>
          <?php endif; ?>
          <?php if ($nextSteps): ?>
          <div style="font-size:.79rem;color:#0369a1">
            <span class="text-muted">Next steps:</span> <?= nl2br(h($nextSteps)) ?>
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
        <i class="fas fa-brain fa-2x mb-3 d-block" style="opacity:.15"></i>
        Select a student on the left to view or add ABA therapy reports.
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>

</div></div></div>

<style>
.aba-section { border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; }
.aba-sec-head { font-size:.78rem; font-weight:700; color:#0f2456; text-transform:uppercase;
                letter-spacing:.5px; margin-bottom:10px; padding-bottom:6px;
                border-bottom:1px solid #e2e8f0; }
.aba-lbl { font-size:.78rem; font-weight:600; margin-bottom:2px; display:block; }
.aba-bullet { font-size:1rem; color:#0891b2; line-height:2; flex-shrink:0; }
.aba-num { font-size:.82rem; font-weight:700; color:#0891b2; line-height:2.2; width:20px;
           flex-shrink:0; text-align:right; }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
var behaviorCount = <?= count($abaBehaviors) ?>;
var goalCount     = <?= count($abaGoals) ?>;
var trialCount    = <?= count($abaTrials) ?>;

function addBehavior() {
  behaviorCount++;
  var wrap = document.getElementById('behaviors-wrap');
  var row  = document.createElement('div');
  row.className = 'd-flex gap-1 mb-1 behavior-row';
  row.innerHTML =
    '<span class="aba-bullet">•</span>' +
    '<input type="text" name="target_behavior[]" class="form-control form-control-sm" placeholder="Behavior ' + behaviorCount + '…">' +
    '<button type="button" class="btn btn-xs btn-outline-danger" onclick="this.closest(\'.behavior-row\').remove()">×</button>';
  wrap.appendChild(row);
}

function addGoal() {
  goalCount++;
  var wrap = document.getElementById('goals-wrap');
  var row  = document.createElement('div');
  row.className = 'd-flex gap-1 mb-1 goal-row';
  row.innerHTML =
    '<span class="aba-num">' + goalCount + '.</span>' +
    '<input type="text" name="goal[]" class="form-control form-control-sm" placeholder="SMART goal…">' +
    '<button type="button" class="btn btn-xs btn-outline-danger" onclick="this.closest(\'.goal-row\').remove()">×</button>';
  wrap.appendChild(row);
}

function addTrialRow() {
  trialCount++;
  var body = document.getElementById('trialsBody');
  var row  = document.createElement('tr');
  row.className = 'trial-row';
  row.innerHTML =
    '<td class="text-center align-middle trial-num" style="font-weight:600">' + trialCount + '</td>' +
    '<td><input type="text" name="trial_target[]"   class="form-control form-control-sm border-0 p-0"></td>' +
    '<td><input type="text" name="trial_prompt[]"   class="form-control form-control-sm border-0 p-0" placeholder="VP/PP/FP"></td>' +
    '<td><input type="text" name="trial_response[]" class="form-control form-control-sm border-0 p-0" placeholder="+/−/NR"></td>' +
    '<td><input type="text" name="trial_reinf[]"    class="form-control form-control-sm border-0 p-0"></td>' +
    '<td><input type="text" name="trial_comments[]" class="form-control form-control-sm border-0 p-0"></td>' +
    '<td class="text-center"><button type="button" class="btn btn-xs btn-outline-danger border-0 p-0" onclick="removeTrialRow(this)">×</button></td>';
  body.appendChild(row);
  renumberTrials();
}

function removeTrialRow(btn) {
  btn.closest('tr').remove();
  renumberTrials();
}

function renumberTrials() {
  document.querySelectorAll('#trialsBody .trial-num').forEach(function(el, i) {
    el.textContent = i + 1;
  });
}
</script>
</body></html>

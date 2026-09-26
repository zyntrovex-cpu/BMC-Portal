<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('ilc_vp');
$db   = getDB();

$tableExists = false;
try { $db->query('SELECT 1 FROM ilc_academic_results LIMIT 0'); $tableExists = true; } catch (Exception $e) {}

// ── Section & grade definitions ───────────────────────────────────────────────
$SECS = [
    'personal_social' => ['title'=>'Personal & Social Development','hdr'=>'#1e3a5f','pairs'=>true,'fields'=>[
        'takes_pride'          => 'Takes pride in own achievement',
        'adapts_new_tasks'     => 'Adapts to new tasks',
        'follows_rules'        => 'Follows rules in group games',
        'takes_turns'          => 'Takes turns',
        'contributes_class'    => 'Contributes to class stories or daily news',
        'takes_care_belongings'=> 'Takes care of belongings',
        'shares_ideas'         => 'Shares ideas & materials',
        'takes_care_hygiene'   => 'Takes care of personal hygiene',
        'ability_express'      => 'Ability to express',
        'positive_relationship'=> 'Displays positive relationship with peers',
        'positive_self_image'  => 'Displays positive self image',
        'shows_self_control'   => 'Shows self control',
    ]],
    'english'    =>['title'=>'English',    'hdr'=>'#065f46','fields'=>[
        'reads_phonetically'   => 'Reads phonetically',
        'forms_letters'        => 'Forms letters correctly',
        'carries_conversation' => 'Carries on conversation',
        'participates_roleplay'=> 'Participates in role-play',
    ]],
    'urdu'       =>['title'=>'Urdu',       'hdr'=>'#1a4731','fields'=>[
        'speaks_clearly'       => 'Speaks clearly',
        'listens_attentively'  => 'Listens attentively to stories',
        'written_presentation' => 'Presentation of written work',
    ]],
    'islamiat'   =>['title'=>'Islamiat',   'hdr'=>'#1d4ed8','fields'=>[
        'level_interest'       => 'Level of interest',
        'retention_duas'       => 'Retention of duas/surahs',
        'comprehension'        => 'Comprehension',
        'recitation'           => 'Recitation',
    ]],
    'mathematics'=>['title'=>'Mathematics','hdr'=>'#92400e','fields'=>[
        'counts_objects'       => 'Counts objects up to',
        'recites_numbers'      => 'Recites number names up to',
        'recognizes_patterns'  => 'Recognizes & repeats patterns',
        'one_more_less'        => 'Knows one more & one less',
    ]],
    'physical_ed'=>['title'=>'Physical Education','hdr'=>'#166534','fields'=>[
        'individual_play'      => 'Participates in individual play',
        'shows_interest'       => 'Shows interest',
        'team_games'           => 'Participates in team games',
        'displays_agility'     => 'Displays agility',
    ]],
    'pbl'        =>['title'=>'Project Based Learning','hdr'=>'#1e40af','fields'=>[
        'participation'        => 'Participation',
        'content_knowledge'    => 'Content knowledge',
        'interest'             => 'Interest',
        'presentation_skills'  => 'Presentation skills',
    ]],
    'story_time' =>['title'=>'Story Time', 'hdr'=>'#6b21a8','fields'=>[
        'concentration_span'   => 'Concentration span',
        'oral_discussion'      => 'Participates in oral discussion',
        'recalls_sequence'     => 'Recalls the story sequence',
        'describes_pictures'   => 'Describes pictures',
    ]],
    'ict'        =>['title'=>'ICT',        'hdr'=>'#0e7490','fields'=>[
        'mouse_control'        => 'Mouse control skills',
        'uses_cursor'          => 'Uses cursor appropriately',
        'follows_instructions' => 'Follows instructions',
    ]],
    'world_around_us'=>['title'=>'Knowledge & Understanding of the World Around Us','hdr'=>'#065f46','fields'=>[
        'explores_environment' => 'Explores environment',
        'exhibits_curiosity'   => 'Exhibits curiosity',
        'observe_investigates' => 'Observe and investigates',
        'asks_question'        => 'Asks relevant question',
    ]],
    'art'        =>['title'=>'Art',        'hdr'=>'#9f1239','fields'=>[
        'imagination'          => 'Imagination',
        'drawing_coloring'     => 'Drawing/coloring',
        'creativity'           => 'Creativity',
        'hand_craftwork'       => 'Hand & craftwork',
    ]],
    'music'      =>['title'=>'Music',      'hdr'=>'#5b21b6','fields'=>[
        'shows_interest'       => 'Show interest',
        'coordinates_music'    => 'Coordinates with music',
    ]],
];
$GRADES = ['A+','A','B','C','D','N.A.'];
$TERMS  = ['Mid Term','Final Term','Annual Exam'];

function arGrSel(string $nm, string $val, array $grades): string {
    $h = '<select name="'.htmlspecialchars($nm).'" class="ar-gs"><option value="">—</option>';
    foreach ($grades as $g) {
        $h .= '<option value="'.$g.'"'.($val===$g?' selected':'').'>'.$g.'</option>';
    }
    return $h.'</select>';
}

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action    = $_POST['action']     ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);

    if (in_array($action, ['create','update']) && $studentId) {
        $fd = ['basic'=>[
            'term'    => trim($_POST['basic_term']    ?? 'Final Term'),
            'session' => trim($_POST['basic_session'] ?? ''),
        ]];
        foreach ($SECS as $sk => $sec) {
            $fd[$sk] = [];
            foreach ($sec['fields'] as $fk => $_) {
                $fd[$sk][$fk] = trim($_POST["{$sk}_{$fk}"] ?? '');
            }
        }
        $fd['attendance'] = [
            'class_avg_age' => trim($_POST['att_class_avg_age'] ?? ''),
            'student_age'   => trim($_POST['att_student_age']   ?? ''),
            'working_days'  => trim($_POST['att_working_days']  ?? ''),
            'days_present'  => trim($_POST['att_days_present']  ?? ''),
            'days_absent'   => trim($_POST['att_days_absent']   ?? ''),
            'punctuality'   => trim($_POST['att_punctuality']   ?? ''),
        ];
        $json    = json_encode($fd, JSON_UNESCAPED_UNICODE);
        $term    = $fd['basic']['term'];
        $session = $fd['basic']['session'];

        if ($action === 'update') {
            $id = (int)($_POST['result_id'] ?? 0);
            $db->prepare('UPDATE ilc_academic_results SET term=?,session=?,form_data=?,recorded_by=? WHERE id=?')
               ->execute([$term, $session, $json, $user['id'], $id]);
            logActivity($user['id'], 'ar1_update', "Updated Academic Result #$id");
            setFlash('success', 'Academic Result updated.');
        } else {
            $db->prepare('INSERT INTO ilc_academic_results (student_id,term,session,form_data,recorded_by) VALUES (?,?,?,?,?)')
               ->execute([$studentId, $term, $session, $json, $user['id']]);
            logActivity($user['id'], 'ar1_create', "Saved Academic Result for student #$studentId");
            setFlash('success', 'Academic Result saved.');
        }
    }
    if ($action === 'delete') {
        $id = (int)($_POST['result_id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM ilc_academic_results WHERE id=?')->execute([$id]);
            logActivity($user['id'], 'ar1_delete', "Deleted Academic Result #$id");
            setFlash('success', 'Academic Result deleted.');
        }
    }
    redirect('/portal/ilc/academic-result1.php' . ($studentId ? "?student_id=$studentId" : ''));
}

// ── Fetch ─────────────────────────────────────────────────────────────────────
$studentId  = (int)($_GET['student_id'] ?? 0);
$editId     = (int)($_GET['edit']       ?? 0);

try {
    $students = $db->query(
        'SELECT s.id, u.name, s.roll_no, c.name AS class_name
         FROM students s JOIN users u ON u.id=s.user_id JOIN classes c ON c.id=s.class_id
         WHERE c.is_ilc=1 ORDER BY c.name, s.roll_no'
    )->fetchAll();
} catch (Exception $e) { $students = []; }

$results = []; $editResult = null;
if ($studentId && $tableExists) {
    $st = $db->prepare(
        'SELECT r.*, u.name AS recorder_name
         FROM ilc_academic_results r JOIN users u ON u.id=r.recorded_by
         WHERE r.student_id=? ORDER BY r.created_at DESC'
    );
    $st->execute([$studentId]);
    $results = $st->fetchAll();
    if ($editId) {
        foreach ($results as $r) { if ($r['id'] == $editId) { $editResult = $r; break; } }
    }
}

$curStudent = null;
foreach ($students as $s) { if ((int)$s['id']===$studentId) { $curStudent=$s; break; } }

$fd = [];
if ($editResult && !empty($editResult['form_data'])) $fd = json_decode($editResult['form_data'],true) ?? [];
$fdBasic = $fd['basic'] ?? ['term'=>'Final Term','session'=>''];
$fdAtt   = $fd['attendance'] ?? [];

pageHead('Academic Result 1', 'ilc_vp');
$links = getIlcLinks();
?>
<div class="portal-wrap">
<?php sidebar('ilc_vp','academic-result1',$links,$user); ?>
<div class="main-area">
<?php topbar('Academic Result 1',$user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Branding strip -->
<div class="d-flex align-items-center gap-3 mb-3 p-3"
     style="background:linear-gradient(90deg,#eff6ff,#dbeafe);border-radius:10px;border:1px solid #93c5fd">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC"
       style="width:40px;height:40px;object-fit:contain;flex-shrink:0" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#1e3a8a;letter-spacing:.8px;text-transform:uppercase">
      Academic Result 1 — Special Children's Wing
    </div>
    <div style="font-size:.78rem;color:#475569">Inclusive Learning Centre · Bahria Model College Bin Qasim</div>
  </div>
</div>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  <strong>Migration not applied.</strong> Run <code>database/migrations/ilc_academic_results.sql</code> to create the Academic Results table. ⚠️ Back up your DB first!
</div>
<?php else: ?>

<div class="row g-3">
  <!-- ── Left: Student selector + form ── -->
  <div class="col-xl-6">
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
           data-bs-toggle="collapse" data-bs-target="#arFormBody" style="cursor:pointer">
        <span>
          <i class="fas fa-<?= $editResult?'edit':'plus' ?> me-2"></i>
          <?= $editResult
              ? 'Edit Result — '.h($editResult['term']).' '.h($editResult['session'])
              : 'New Academic Result' ?>
        </span>
        <i class="fas fa-chevron-down"></i>
      </div>
      <div id="arFormBody" class="collapse show">
        <div style="padding:16px 18px 20px">

          <!-- Official header -->
          <div class="text-center mb-3 pb-2" style="border-bottom:2px solid #1e3a5f">
            <div style="font-size:.66rem;font-weight:700;letter-spacing:1px;color:#1e3a5f;text-transform:uppercase">
              Inclusive Learning Centre (ILC) · Bahria Model College
            </div>
            <div style="font-size:.96rem;font-weight:700;color:#0f172a;margin:.25rem 0 .1rem">
              Academic Result — Special Children's Wing
            </div>
          </div>

          <form method="POST" id="arForm">
            <input type="hidden" name="action"     value="<?= $editResult?'update':'create' ?>">
            <input type="hidden" name="student_id" value="<?= $studentId ?>">
            <?php if ($editResult): ?>
            <input type="hidden" name="result_id"  value="<?= $editResult['id'] ?>">
            <?php endif; ?>

            <!-- Basic Info -->
            <div class="fba-sec mb-3">
              <div class="fba-sh" style="background:#1e3a5f">1. Basic Information</div>
              <div class="fba-sb">
                <div class="row g-2">
                  <div class="col-md-6">
                    <label class="fba-lbl">Student</label>
                    <input type="text" class="form-control form-control-sm bg-light"
                           value="<?= h($curStudent['name']??'') ?>" readonly>
                  </div>
                  <div class="col-md-3">
                    <label class="fba-lbl">Term <span class="text-danger">*</span></label>
                    <select name="basic_term" class="form-select form-select-sm" required>
                      <?php foreach ($TERMS as $t): ?>
                      <option value="<?= $t ?>" <?= ($fdBasic['term']===$t)?'selected':'' ?>><?= $t ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label class="fba-lbl">Session</label>
                    <input type="text" name="basic_session" class="form-control form-control-sm"
                           placeholder="e.g. 2023-2024" value="<?= h($fdBasic['session']) ?>">
                  </div>
                </div>
              </div>
            </div>

            <?php
            $secNum = 2;
            foreach ($SECS as $sk => $sec):
                $isPairs = !empty($sec['pairs']);
                $secFd   = $fd[$sk] ?? [];
            ?>
            <!-- Section: <?= h($sec['title']) ?> -->
            <div class="fba-sec mb-2">
              <div class="fba-sh" style="background:<?= $sec['hdr'] ?>"><?= $secNum ?>. <?= h($sec['title']) ?></div>
              <div class="fba-sb" style="padding:0">
                <?php if ($isPairs):
                    $fieldKeys  = array_keys($sec['fields']);
                    $fieldLabels= array_values($sec['fields']);
                    $pairs      = array_chunk($fieldKeys, 2);
                ?>
                <table class="table table-sm table-bordered mb-0" style="font-size:.8rem">
                  <thead><tr style="background:#e8edf5">
                    <th style="width:40%">Skill / Competency</th>
                    <th class="text-center" style="width:10%">Grade</th>
                    <th style="width:40%">Skill / Competency</th>
                    <th class="text-center" style="width:10%">Grade</th>
                  </tr></thead>
                  <tbody>
                    <?php foreach ($pairs as $pair):
                      [$k1, $k2] = array_pad($pair, 2, null); ?>
                    <tr>
                      <td class="align-middle"><?= h($sec['fields'][$k1]) ?></td>
                      <td class="text-center align-middle">
                        <?= arGrSel("{$sk}_{$k1}", $secFd[$k1]??'', $GRADES) ?>
                      </td>
                      <td class="align-middle"><?= $k2 ? h($sec['fields'][$k2]) : '' ?></td>
                      <td class="text-center align-middle">
                        <?= $k2 ? arGrSel("{$sk}_{$k2}", $secFd[$k2]??'', $GRADES) : '' ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>

                <?php else: ?>
                <table class="table table-sm table-bordered mb-0" style="font-size:.8rem">
                  <thead><tr style="background:#f8fafc">
                    <th>Skill / Competency</th>
                    <th class="text-center" style="width:90px">Grade</th>
                  </tr></thead>
                  <tbody>
                    <?php foreach ($sec['fields'] as $fk => $flabel): ?>
                    <tr>
                      <td class="align-middle"><?= h($flabel) ?></td>
                      <td class="text-center align-middle">
                        <?= arGrSel("{$sk}_{$fk}", $secFd[$fk]??'', $GRADES) ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <?php endif; ?>
              </div>
            </div>
            <?php $secNum++; endforeach; ?>

            <!-- Attendance -->
            <div class="fba-sec mb-3">
              <div class="fba-sh" style="background:#374151"><?= $secNum ?>. Attendance</div>
              <div class="fba-sb">
                <div class="row g-2">
                  <div class="col-6 col-md-4">
                    <label class="fba-lbl">Class Average Age</label>
                    <input type="text" name="att_class_avg_age" class="form-control form-control-sm"
                           placeholder="e.g. 8 years" value="<?= h($fdAtt['class_avg_age']??'') ?>">
                  </div>
                  <div class="col-6 col-md-4">
                    <label class="fba-lbl">Student's Age</label>
                    <input type="text" name="att_student_age" class="form-control form-control-sm"
                           placeholder="e.g. 7 years" value="<?= h($fdAtt['student_age']??'') ?>">
                  </div>
                  <div class="col-6 col-md-4">
                    <label class="fba-lbl">Total Working Days</label>
                    <input type="number" name="att_working_days" class="form-control form-control-sm"
                           placeholder="e.g. 95" min="0" value="<?= h($fdAtt['working_days']??'') ?>">
                  </div>
                  <div class="col-6 col-md-4">
                    <label class="fba-lbl">Days Present</label>
                    <input type="number" name="att_days_present" class="form-control form-control-sm"
                           placeholder="e.g. 73" min="0" value="<?= h($fdAtt['days_present']??'') ?>">
                  </div>
                  <div class="col-6 col-md-4">
                    <label class="fba-lbl">Days Absent</label>
                    <input type="number" name="att_days_absent" class="form-control form-control-sm"
                           placeholder="e.g. 22" min="0" value="<?= h($fdAtt['days_absent']??'') ?>">
                  </div>
                  <div class="col-6 col-md-4">
                    <label class="fba-lbl">Punctuality</label>
                    <input type="text" name="att_punctuality" class="form-control form-control-sm"
                           placeholder="Good / Satisfactory / —" value="<?= h($fdAtt['punctuality']??'') ?>">
                  </div>
                </div>
              </div>
            </div>

            <div class="d-flex gap-2 pt-1">
              <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-save me-1"></i><?= $editResult?'Update Result':'Save Result' ?>
              </button>
              <?php if ($editResult): ?>
              <a href="?student_id=<?= $studentId ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Right: Results list ── -->
  <div class="col-xl-6">
    <?php if ($studentId && $curStudent): ?>
    <div class="sec-card">
      <div class="sec-card-header">
        <i class="fas fa-graduation-cap me-2"></i>Results — <?= h($curStudent['name']) ?>
        <span class="badge bg-secondary ms-2"><?= count($results) ?></span>
      </div>

      <?php if (empty($results)): ?>
      <div style="padding:40px;text-align:center;color:var(--t2);font-size:.85rem">
        No results yet. Use the form to add the first Academic Result.
      </div>
      <?php else: ?>
      <div style="padding:12px 16px">
        <?php foreach ($results as $r):
          $rfd  = json_decode($r['form_data'],true) ?? [];
          $rAtt = $rfd['attendance'] ?? [];
        ?>
        <div class="mb-3 p-3" style="background:#f7f9fb;border-radius:8px;border:1px solid var(--border)">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <span class="badge" style="background:#1e3a5f;font-size:.75rem">
                <?= h($r['term']) ?> <?= h($r['session']) ?>
              </span>
              <span style="font-size:.74rem;color:var(--t2);margin-left:8px">
                <?= fDate($r['created_at']) ?> · <?= h($r['recorder_name']) ?>
              </span>
            </div>
            <div class="d-flex gap-1">
              <a href="<?= url('/portal/ilc/academic-result1-pdf.php?id='.$r['id']) ?>"
                 target="_blank" class="btn btn-xs btn-outline-success" title="View / Print PDF">
                <i class="fas fa-file-pdf me-1"></i>PDF
              </a>
              <a href="?student_id=<?= $studentId ?>&edit=<?= $r['id'] ?>"
                 class="btn btn-xs btn-outline-primary" title="Edit">
                <i class="fas fa-edit"></i>
              </a>
              <form method="POST" class="d-inline" onsubmit="return confirm('Delete this result?')">
                <input type="hidden" name="action"     value="delete">
                <input type="hidden" name="student_id" value="<?= $studentId ?>">
                <input type="hidden" name="result_id"  value="<?= $r['id'] ?>">
                <button class="btn btn-xs btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </div>
          <?php if (!empty($rAtt['working_days'])): ?>
          <div style="font-size:.76rem;color:var(--t2)">
            <i class="fas fa-calendar-check me-1"></i>
            Working Days: <?= h($rAtt['working_days']) ?> &nbsp;·&nbsp;
            Present: <?= h($rAtt['days_present']??'—') ?> &nbsp;·&nbsp;
            Absent: <?= h($rAtt['days_absent']??'—') ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <div class="sec-card">
      <div style="padding:60px;text-align:center;color:var(--t2)">
        <i class="fas fa-graduation-cap fa-2x mb-3 d-block" style="opacity:.15"></i>
        Select a student to view or add Academic Results.
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Grade legend -->
<div class="mt-3 p-2 d-flex flex-wrap gap-2 align-items-center" style="font-size:.75rem;color:var(--t2)">
  <span class="fw-semibold">Grade Key:</span>
  <?php
  $gradeLegend = ['A+'=>['#166534','#dcfce7'],'A'=>['#15803d','#f0fdf4'],'B'=>['#1d4ed8','#dbeafe'],
                  'C'=>['#b45309','#fef3c7'],'D'=>['#dc2626','#fee2e2'],'N.A.'=>['#6b7280','#f3f4f6']];
  $gradeNames  = ['A+'=>'Outstanding','A'=>'Excellent','B'=>'Good','C'=>'Satisfactory','D'=>'Needs Improvement','N.A.'=>'Not Assessed'];
  foreach ($gradeLegend as $g => [$fg,$bg]):
  ?>
  <span style="background:<?= $bg ?>;color:<?= $fg ?>;padding:2px 8px;border-radius:4px;font-weight:700">
    <?= $g ?> — <?= $gradeNames[$g] ?>
  </span>
  <?php endforeach; ?>
</div>

<?php endif; ?>
</div></div></div>

<style>
.fba-sec{border:1px solid #e2e8f0;border-radius:7px;overflow:hidden}
.fba-sh{font-size:.78rem;font-weight:700;color:#fff;padding:5px 12px;letter-spacing:.4px;text-transform:uppercase}
.fba-sb{padding:10px 12px}
.fba-lbl{font-size:.78rem;font-weight:600;margin-bottom:2px;display:block}
.ar-gs{width:68px;font-size:.78rem;padding:2px 4px;border-radius:4px;border:1px solid #d1d5db;font-weight:600}
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

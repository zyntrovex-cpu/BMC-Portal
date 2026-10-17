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
$GRADES = ['A++','A+','A','B++','B+','B','C','D','E','U','N.A.'];
$TERMS  = ['Mid Term','Final Term','Annual Exam'];

function arGrSel(string $nm, string $val, array $grades): string {
    $h = '<select name="'.htmlspecialchars($nm).'" class="ar-gs"><option value="">—</option>';
    foreach ($grades as $g) {
        $h .= '<option value="'.$g.'"'.($val===$g?' selected':'').'>'.$g.'</option>';
    }
    return $h.'</select>';
}

$ar1AttachDir = __DIR__ . '/../assets/uploads/ilc-attachments/';
if (!is_dir($ar1AttachDir)) @mkdir($ar1AttachDir, 0755, true);

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action    = $_POST['action']     ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);

    if ($action === 'delete_attachment') {
        $id = (int)($_POST['result_id'] ?? 0);
        if ($id) {
            $row = $db->prepare('SELECT form_data FROM ilc_academic_results WHERE id=?');
            $row->execute([$id]);
            $row = $row->fetch();
            if ($row) {
                $rfd = json_decode($row['form_data'], true) ?? [];
                if (!empty($rfd['attachment_file'])) {
                    @unlink($ar1AttachDir . $rfd['attachment_file']);
                    unset($rfd['attachment_file']);
                    $db->prepare('UPDATE ilc_academic_results SET form_data=? WHERE id=?')
                       ->execute([json_encode($rfd, JSON_UNESCAPED_UNICODE), $id]);
                    logActivity($user['id'], 'ar1_attach_delete', "Deleted AR1 attachment for result #$id");
                    setFlash('success', 'Attachment deleted.');
                }
            }
        }
        redirect('/portal/ilc/academic-result1.php' . ($studentId ? "?student_id=$studentId" : ''));
    }

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
        // Per-section extra custom fields
        foreach (array_keys($SECS) as $sk) {
            $exL = (array)($_POST['sec_extra_label'][$sk] ?? []);
            $exG = (array)($_POST['sec_extra_grade'][$sk] ?? []);
            $extras = [];
            foreach ($exL as $ei => $el) {
                $el = trim($el);
                if ($el !== '') $extras[] = ['label'=>$el, 'grade'=>trim($exG[$ei]??'')];
            }
            if (!empty($extras)) $fd[$sk]['_extra'] = $extras;
        }
        // Extra custom sections (JSON-encoded by JS before submit)
        $rawXS = trim($_POST['extra_sections_json'] ?? '');
        if ($rawXS) {
            $parsedXS = json_decode($rawXS, true);
            if (is_array($parsedXS)) {
                $cleanXS = [];
                foreach ($parsedXS as $xs) {
                    $xt = trim($xs['title'] ?? '');
                    $xf = [];
                    foreach ((array)($xs['fields'] ?? []) as $xfield) {
                        $xl = trim($xfield['label'] ?? '');
                        if ($xl !== '') $xf[] = ['label'=>$xl, 'grade'=>trim($xfield['grade']??'')];
                    }
                    if ($xt !== '' || !empty($xf)) $cleanXS[] = ['title'=>$xt, 'fields'=>$xf];
                }
                if (!empty($cleanXS)) $fd['extra_sections'] = $cleanXS;
            }
        }
        // File attachment (PDF/JPG/PNG/DOCX, max 5MB)
        if (!empty($_FILES['attachment']['tmp_name'])) {
            $allowedMimes = [
                'application/pdf'                                                    => 'pdf',
                'image/jpeg'                                                         => 'jpg',
                'image/png'                                                          => 'png',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/msword'                                                 => 'doc',
            ];
            $mime = mime_content_type($_FILES['attachment']['tmp_name']);
            if (isset($allowedMimes[$mime]) && $_FILES['attachment']['size'] <= 5 * 1024 * 1024) {
                // Delete old attachment if updating
                if ($action === 'update') {
                    $oldId = (int)($_POST['result_id'] ?? 0);
                    $oldRow = $db->prepare('SELECT form_data FROM ilc_academic_results WHERE id=?');
                    $oldRow->execute([$oldId]);
                    $oldRow = $oldRow->fetch();
                    if ($oldRow) {
                        $oldFd = json_decode($oldRow['form_data'], true) ?? [];
                        if (!empty($oldFd['attachment_file'])) @unlink($ar1AttachDir . $oldFd['attachment_file']);
                    }
                }
                $ext = $allowedMimes[$mime];
                $attFilename = 'ar1att_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $ar1AttachDir . $attFilename)) {
                    $fd['attachment_file'] = $attFilename;
                }
            } else {
                setFlash('warning', 'Attachment skipped: only PDF/JPG/PNG/DOCX up to 5MB allowed.');
            }
        }

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
$fdBasic      = $fd['basic']          ?? ['term'=>'Final Term','session'=>''];
$fdAtt        = $fd['attendance']     ?? [];
$fdExtraSecs  = $fd['extra_sections'] ?? [];
$fdExtraSecsJson = json_encode($fdExtraSecs, JSON_HEX_TAG|JSON_UNESCAPED_UNICODE);

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
  <i class="fas fa-spinner fa-spin me-2"></i><strong>Database table initialising.</strong> Please refresh the page in a moment.
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

          <form method="POST" id="arForm" enctype="multipart/form-data">
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
                $isPairs    = !empty($sec['pairs']);
                $secFd      = $fd[$sk] ?? [];
                $secFdExtra = $secFd['_extra'] ?? [];
            ?>
            <!-- Section: <?= h($sec['title']) ?> -->
            <div class="fba-sec mb-2">
              <div class="fba-sh" style="background:<?= $sec['hdr'] ?>"><?= $secNum ?>. <?= h($sec['title']) ?></div>
              <div class="fba-sb" style="padding:0">
                <?php if ($isPairs):
                    $pairs = array_chunk(array_keys($sec['fields']), 2);
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

                <!-- Custom extra fields for this section -->
                <table class="table table-sm table-bordered mb-0 ar-extra-tbl<?= empty($secFdExtra)?' d-none':'' ?>"
                       style="font-size:.79rem;background:#fffbeb" id="extras-table-<?= $sk ?>">
                  <tbody id="extras-<?= $sk ?>">
                  <?php foreach ($secFdExtra as $ef): ?>
                  <tr class="extra-row">
                    <td class="align-middle">
                      <input type="text" name="sec_extra_label[<?= h($sk) ?>][]"
                             class="form-control form-control-sm border-0 p-0"
                             value="<?= h($ef['label']) ?>" placeholder="Custom field label">
                    </td>
                    <td class="text-center align-middle" style="width:82px">
                      <?= arGrSel("sec_extra_grade[{$sk}][]", $ef['grade']??'', $GRADES) ?>
                    </td>
                    <td style="width:34px;text-align:center;vertical-align:middle">
                      <button type="button" class="btn btn-xs btn-outline-danger remove-extra-row"
                              style="padding:0 5px;font-size:.8rem" title="Remove">×</button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <div style="padding:4px 10px 5px;background:#f8f9fa;border-top:1px solid #e2e8f0">
                <button type="button" class="btn btn-xs btn-outline-secondary add-extra-btn"
                        data-sk="<?= h($sk) ?>" style="font-size:.71rem;padding:1px 8px">
                  <i class="fas fa-plus me-1"></i>Add custom field
                </button>
              </div>
            </div>
            <?php $secNum++; endforeach; ?>

            <!-- Custom Extra Sections -->
            <div class="fba-sec mb-2" id="customSecsWrap">
              <div class="fba-sh" style="background:#4b5563;display:flex;justify-content:space-between;align-items:center">
                <span><?= $secNum ?>. Custom Sections</span>
                <button type="button" id="addCustomSecBtn"
                        style="background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.3);font-size:.7rem;padding:1px 9px;border-radius:4px;cursor:pointer">
                  <i class="fas fa-plus me-1"></i>Add Section
                </button>
              </div>
              <div id="customSecsList">
                <div id="noCustomSecs" class="fba-sb" style="font-size:.78rem;color:var(--t2)">
                  No custom sections. Click "Add Section" to create one.
                </div>
              </div>
            </div>
            <input type="hidden" name="extra_sections_json" id="extraSectionsJson" value="">
            <?php $secNum++; ?>

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

            <!-- File Attachment -->
            <div class="fba-sec mb-3">
              <div class="fba-sh" style="background:#475569">Attachment (optional)</div>
              <div class="fba-sb">
                <?php
                  $curAttach = $fd['attachment_file'] ?? ($editResult ? (json_decode($editResult['form_data'],true)['attachment_file'] ?? '') : '');
                ?>
                <?php if ($curAttach): ?>
                <div class="mb-2 p-2" style="background:#f0fdf4;border-radius:6px;border:1px solid #86efac;font-size:.8rem">
                  <i class="fas fa-paperclip me-1 text-success"></i>
                  <a href="<?= url('/assets/uploads/ilc-attachments/'.h($curAttach)) ?>" target="_blank" class="text-success fw-semibold">View current attachment</a>
                  <span class="text-muted ms-2">(upload a new file to replace it)</span>
                </div>
                <?php endif; ?>
                <input type="file" name="attachment" class="form-control form-control-sm"
                       accept=".pdf,.jpg,.jpeg,.png,.docx,.doc">
                <div class="form-text" style="font-size:.75rem;color:#64748b">PDF, JPG, PNG or DOCX · max 5 MB</div>
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
            <div class="d-flex gap-1 flex-wrap">
              <a href="<?= url('/portal/ilc/academic-result1-pdf.php?id='.$r['id']) ?>"
                 target="_blank" class="btn btn-xs btn-outline-success" title="View / Print PDF">
                <i class="fas fa-file-pdf me-1"></i>PDF
              </a>
              <a href="?student_id=<?= $studentId ?>&edit=<?= $r['id'] ?>"
                 class="btn btn-xs btn-outline-primary" title="Edit">
                <i class="fas fa-edit"></i>
              </a>
              <?php if (!empty($rfd['attachment_file'])): ?>
              <a href="<?= url('/assets/uploads/ilc-attachments/'.h($rfd['attachment_file'])) ?>"
                 target="_blank" class="btn btn-xs btn-outline-info" title="View Attachment">
                <i class="fas fa-paperclip me-1"></i>File
              </a>
              <form method="POST" class="d-inline" onsubmit="return confirm('Delete this attachment?')">
                <input type="hidden" name="action"     value="delete_attachment">
                <input type="hidden" name="student_id" value="<?= $studentId ?>">
                <input type="hidden" name="result_id"  value="<?= $r['id'] ?>">
                <button class="btn btn-xs btn-outline-warning" title="Delete Attachment">
                  <i class="fas fa-paperclip"></i><i class="fas fa-times ms-1" style="font-size:.65rem"></i>
                </button>
              </form>
              <?php endif; ?>
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
  $gradeLegend = [
    'A++'=>['#14532d','#dcfce7'],'A+'=>['#166534','#d1fae5'],'A'=>['#15803d','#bbf7d0'],
    'B++'=>['#1e40af','#dbeafe'],'B+'=>['#1d4ed8','#bfdbfe'],'B'=>['#0369a1','#e0f2fe'],
    'C'=>['#92400e','#fef3c7'],'D'=>['#7c2d12','#ffedd5'],
    'E'=>['#9a3412','#fee2e2'],'U'=>['#991b1b','#fecaca'],'N.A.'=>['#6b7280','#f3f4f6'],
  ];
  $gradeNames  = [
    'A++'=>'Outstanding','A+'=>'Excellent','A'=>'Very Good',
    'B++'=>'Good','B+'=>'Above Average','B'=>'Average',
    'C'=>'Satisfactory','D'=>'Pass','E'=>'Below Average','U'=>'Ungraded','N.A.'=>'Not Assessed',
  ];
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
.ar-extra-tbl td{padding:3px 7px}
.custom-sec-block{border:1px dashed #94a3b8;border-radius:6px;margin:8px 12px}
.csf-row{padding:3px 0}
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const AR1_GRADES = <?= json_encode($GRADES) ?>;

// ── Per-section extra field rows (event delegation) ────────────────────────
document.addEventListener('click', function(e) {
    // Add extra field to existing section
    if (e.target.closest('.add-extra-btn')) {
        const btn = e.target.closest('.add-extra-btn');
        const sk = btn.dataset.sk;
        const tbody = document.getElementById('extras-' + sk);
        const table = document.getElementById('extras-table-' + sk);
        if (!tbody || !table) return;
        table.classList.remove('d-none');
        const tr = document.createElement('tr');
        tr.className = 'extra-row';
        tr.innerHTML =
          '<td class="align-middle"><input type="text" name="sec_extra_label['+sk+'][]"'
          +' class="form-control form-control-sm border-0 p-0" placeholder="Custom field label"></td>'
          +'<td class="text-center align-middle" style="width:82px">'
          +'<select name="sec_extra_grade['+sk+'][]" class="ar-gs"><option value="">—</option>'
          +AR1_GRADES.map(g=>'<option value="'+g+'">'+g+'</option>').join('')
          +'</select></td>'
          +'<td style="width:34px;text-align:center;vertical-align:middle">'
          +'<button type="button" class="btn btn-xs btn-outline-danger remove-extra-row"'
          +' style="padding:0 5px;font-size:.8rem" title="Remove">×</button></td>';
        tbody.appendChild(tr);
        return;
    }
    // Remove extra field row
    if (e.target.closest('.remove-extra-row')) {
        const row = e.target.closest('.extra-row');
        const tbody = row.parentElement;
        const table = tbody.parentElement;
        row.remove();
        if (tbody.children.length === 0) table.classList.add('d-none');
        return;
    }
    // Remove custom section block
    if (e.target.closest('.remove-custom-sec')) {
        e.target.closest('.custom-sec-block').remove();
        updateNoSecsMsg();
        return;
    }
    // Add field to custom section
    if (e.target.closest('.add-csf-btn')) {
        const fieldsDiv = e.target.closest('.custom-sec-block').querySelector('.cs-fields-list');
        addCsfRow(fieldsDiv, '', '');
        return;
    }
    // Remove field from custom section
    if (e.target.closest('.remove-csf')) {
        e.target.closest('.csf-row').remove();
        return;
    }
});

// ── Custom Sections ────────────────────────────────────────────────────────
function updateNoSecsMsg() {
    const list   = document.getElementById('customSecsList');
    const noMsg  = document.getElementById('noCustomSecs');
    const hasSecs= list && list.querySelectorAll('.custom-sec-block').length > 0;
    if (noMsg) noMsg.style.display = hasSecs ? 'none' : '';
}

function addCsfRow(container, label, grade) {
    const row = document.createElement('div');
    row.className = 'csf-row d-flex gap-1 align-items-center';
    row.innerHTML =
      '<input type="text" class="csf-label form-control form-control-sm" placeholder="Field label"'
      +' value="'+label.replace(/"/g,'&quot;')+'" style="flex:1">'
      +'<select class="csf-grade ar-gs"><option value="">—</option>'
      +AR1_GRADES.map(g=>'<option value="'+g+'"'+(g===grade?' selected':'')+'>'+g+'</option>').join('')
      +'</select>'
      +'<button type="button" class="btn btn-xs btn-outline-danger remove-csf"'
      +' style="padding:0 5px;font-size:.8rem" title="Remove">×</button>';
    container.appendChild(row);
}

function addCustomSectionBlock(title, fields) {
    const list  = document.getElementById('customSecsList');
    const noMsg = document.getElementById('noCustomSecs');
    if (noMsg) noMsg.style.display = 'none';
    const block = document.createElement('div');
    block.className = 'custom-sec-block';
    block.innerHTML =
      '<div class="d-flex align-items-center gap-2 px-2 py-1" style="background:#f1f5f9;border-bottom:1px solid #e2e8f0">'
      +'<input type="text" class="cs-title form-control form-control-sm"'
      +' value="'+( (title||'').replace(/"/g,'&quot;') )+'"'
      +' placeholder="Section title (e.g. Handwriting, Science)" style="flex:1;font-weight:600">'
      +'<button type="button" class="btn btn-xs btn-outline-danger remove-custom-sec"'
      +' style="padding:1px 6px" title="Remove section"><i class="fas fa-trash"></i></button>'
      +'</div>'
      +'<div class="cs-fields-list px-2 pt-1 pb-1"></div>'
      +'<div style="padding:3px 10px 6px">'
      +'<button type="button" class="add-csf-btn btn btn-xs btn-outline-secondary" style="font-size:.7rem">'
      +'<i class="fas fa-plus me-1"></i>Add field</button></div>';
    list.appendChild(block);
    const fieldsDiv = block.querySelector('.cs-fields-list');
    (fields||[]).forEach(function(f) { addCsfRow(fieldsDiv, f.label||'', f.grade||''); });
}

document.getElementById('addCustomSecBtn').addEventListener('click', function() {
    addCustomSectionBlock('', []);
});

// Serialize custom sections to JSON before form submit
document.getElementById('arForm').addEventListener('submit', function() {
    var sections = [];
    document.querySelectorAll('.custom-sec-block').forEach(function(block) {
        var title = block.querySelector('.cs-title').value.trim();
        var fields = [];
        block.querySelectorAll('.csf-row').forEach(function(row) {
            var label = row.querySelector('.csf-label').value.trim();
            var grade = row.querySelector('.csf-grade').value;
            if (label) fields.push({label:label, grade:grade});
        });
        sections.push({title:title, fields:fields});
    });
    document.getElementById('extraSectionsJson').value = JSON.stringify(sections);
});

// Restore existing extra sections from server data
var _initExtraSecs = <?= $fdExtraSecsJson ?>;
if (_initExtraSecs && _initExtraSecs.length) {
    _initExtraSecs.forEach(function(sec) { addCustomSectionBlock(sec.title||'', sec.fields||[]); });
} else {
    updateNoSecsMsg();
}
</script>
</body></html>

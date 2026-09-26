<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('student');
$student = getStudentByUserId($user['id']);
$db      = getDB();

if (!$student) { setFlash('danger','Student profile not found.'); redirect('/portal/logout.php'); }

$tableExists = false;
try { $db->query('SELECT 1 FROM ilc_academic_results LIMIT 0'); $tableExists = true; } catch (Exception $e) {}

$results = [];
if ($tableExists) {
    $st = $db->prepare(
        'SELECT r.*, u.name AS recorder_name
         FROM ilc_academic_results r
         JOIN users u ON u.id = r.recorded_by
         WHERE r.student_id = ?
         ORDER BY r.created_at DESC'
    );
    $st->execute([$student['id']]);
    $results = $st->fetchAll();
}

$SECS = [
    'personal_social'=>['title'=>'Personal & Social Development','pairs'=>true,'fields'=>[
        'takes_pride'          =>'Takes pride in own achievement',
        'adapts_new_tasks'     =>'Adapts to new tasks',
        'follows_rules'        =>'Follows rules in group games',
        'takes_turns'          =>'Takes turns',
        'contributes_class'    =>'Contributes to class stories or daily news',
        'takes_care_belongings'=>'Takes care of belongings',
        'shares_ideas'         =>'Shares ideas & materials',
        'takes_care_hygiene'   =>'Takes care of personal hygiene',
        'ability_express'      =>'Ability to express',
        'positive_relationship'=>'Displays positive relationship with peers',
        'positive_self_image'  =>'Displays positive self image',
        'shows_self_control'   =>'Shows self control',
    ]],
    'english'    =>['title'=>'English',    'fields'=>['reads_phonetically'=>'Reads phonetically','forms_letters'=>'Forms letters correctly','carries_conversation'=>'Carries on conversation','participates_roleplay'=>'Participates in role-play']],
    'urdu'       =>['title'=>'Urdu',       'fields'=>['speaks_clearly'=>'Speaks clearly','listens_attentively'=>'Listens attentively to stories','written_presentation'=>'Presentation of written work']],
    'islamiat'   =>['title'=>'Islamiat',   'fields'=>['level_interest'=>'Level of interest','retention_duas'=>'Retention of duas/surahs','comprehension'=>'Comprehension','recitation'=>'Recitation']],
    'mathematics'=>['title'=>'Mathematics','fields'=>['counts_objects'=>'Counts objects up to','recites_numbers'=>'Recites number names up to','recognizes_patterns'=>'Recognizes & repeats patterns','one_more_less'=>'Knows one more & one less']],
    'physical_ed'=>['title'=>'Physical Education','fields'=>['individual_play'=>'Participates in individual play','shows_interest'=>'Shows interest','team_games'=>'Participates in team games','displays_agility'=>'Displays agility']],
    'pbl'        =>['title'=>'Project Based Learning','fields'=>['participation'=>'Participation','content_knowledge'=>'Content knowledge','interest'=>'Interest','presentation_skills'=>'Presentation skills']],
    'story_time' =>['title'=>'Story Time', 'fields'=>['concentration_span'=>'Concentration span','oral_discussion'=>'Participates in oral discussion','recalls_sequence'=>'Recalls the story sequence','describes_pictures'=>'Describes pictures']],
    'ict'        =>['title'=>'ICT',        'fields'=>['mouse_control'=>'Mouse control skills','uses_cursor'=>'Uses cursor appropriately','follows_instructions'=>'Follows instructions']],
    'world_around_us'=>['title'=>'Knowledge & Understanding of the World Around Us','fields'=>['explores_environment'=>'Explores environment','exhibits_curiosity'=>'Exhibits curiosity','observe_investigates'=>'Observe and investigates','asks_question'=>'Asks relevant question']],
    'art'        =>['title'=>'Art',        'fields'=>['imagination'=>'Imagination','drawing_coloring'=>'Drawing/coloring','creativity'=>'Creativity','hand_craftwork'=>'Hand & craftwork']],
    'music'      =>['title'=>'Music',      'fields'=>['shows_interest'=>'Show interest','coordinates_music'=>'Coordinates with music']],
];

function arBadge(string $g): string {
    if ($g === '') return '<span style="color:#94a3b8;font-size:.78rem">—</span>';
    $map = ['A+'=>['#166534','#dcfce7'],'A'=>['#15803d','#f0fdf4'],'B'=>['#1d4ed8','#dbeafe'],
            'C'=>['#b45309','#fef3c7'],'D'=>['#dc2626','#fee2e2'],'N.A.'=>['#6b7280','#f3f4f6']];
    [$fg,$bg] = $map[$g] ?? ['#374151','#f9fafb'];
    return "<span style='background:$bg;color:$fg;border-radius:4px;padding:2px 8px;font-weight:700;font-size:.78rem;display:inline-block;min-width:36px;text-align:center'>$g</span>";
}

pageHead('Academic Result 1','student');
$links = getStudentLinks();
?>
<div class="portal-wrap">
<?php sidebar('student','academic-result1',$links,$user); ?>
<div class="main-area">
<?php topbar('Academic Result 1',$user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Branding strip -->
<div class="d-flex align-items-center gap-3 mb-4 p-3"
     style="background:linear-gradient(90deg,#eff6ff,#dbeafe);border-radius:10px;border:1px solid #93c5fd">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC"
       style="width:44px;height:44px;object-fit:contain;flex-shrink:0" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#1e3a8a;letter-spacing:.8px;text-transform:uppercase">
      My Academic Results
    </div>
    <div style="font-size:.78rem;color:#475569">Inclusive Learning Centre · Bahria Model College Bin Qasim</div>
  </div>
</div>

<?php if (!$tableExists): ?>
<div class="sec-card">
  <div style="padding:60px;text-align:center;color:var(--t2)">
    <i class="fas fa-graduation-cap fa-2x mb-3 d-block" style="opacity:.15"></i>
    <div style="font-size:.88rem">Academic Results are not yet set up.</div>
    <div style="font-size:.78rem;margin-top:6px">Contact your ILC administrator.</div>
  </div>
</div>

<?php elseif (empty($results)): ?>
<div class="sec-card">
  <div style="padding:60px;text-align:center;color:var(--t2)">
    <i class="fas fa-graduation-cap fa-2x mb-3 d-block" style="opacity:.15"></i>
    <div style="font-size:.88rem">No Academic Results have been submitted for your profile yet.</div>
    <div style="font-size:.78rem;margin-top:6px;color:var(--t2)">
      Results are entered by your ILC teacher. Check back later.
    </div>
  </div>
</div>

<?php else: ?>
<?php foreach ($results as $rec):
  $rfd  = json_decode($rec['form_data'],true) ?? [];
  $rAtt = $rfd['attendance'] ?? [];
  $pdfUrl = url('/portal/ilc/academic-result1-pdf.php?id='.$rec['id']);
?>
<div class="sec-card mb-4">
  <div class="sec-card-header d-flex justify-content-between align-items-center"
       style="background:linear-gradient(90deg,#1e3a5f,#1e40af);color:#fff">
    <div>
      <i class="fas fa-graduation-cap me-2"></i>
      <?= h($rec['term']) ?><?= $rec['session']?' — '.h($rec['session']):'' ?>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span style="font-size:.74rem;opacity:.8"><?= fDate($rec['created_at']) ?></span>
      <a href="<?= $pdfUrl ?>" target="_blank"
         class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;font-size:.78rem;border:1px solid rgba(255,255,255,.35)">
        <i class="fas fa-file-pdf me-1"></i>Download PDF
      </a>
    </div>
  </div>

  <!-- Result content (collapsible) -->
  <div id="ar-body-<?= $rec['id'] ?>">

    <!-- Grade key -->
    <div class="d-flex flex-wrap gap-2 align-items-center p-2 pb-0" style="font-size:.74rem">
      <span class="text-muted fw-semibold">Grade Key:</span>
      <?php
      $gl = ['A+'=>['#166534','#dcfce7'],'A'=>['#15803d','#f0fdf4'],'B'=>['#1d4ed8','#dbeafe'],
             'C'=>['#b45309','#fef3c7'],'D'=>['#dc2626','#fee2e2'],'N.A.'=>['#6b7280','#f3f4f6']];
      $gn = ['A+'=>'Outstanding','A'=>'Excellent','B'=>'Good','C'=>'Satisfactory','D'=>'Needs Improvement','N.A.'=>'Not Assessed'];
      foreach ($gl as $g=>[$fg,$bg]):
      ?>
      <span style="background:<?=$bg?>;color:<?=$fg?>;padding:1px 7px;border-radius:4px;font-weight:700">
        <?=$g?> <span style="font-weight:400;opacity:.8">— <?=$gn[$g]?></span>
      </span>
      <?php endforeach; ?>
    </div>

    <!-- All sections -->
    <?php foreach ($SECS as $sk => $sec):
      $secFd  = $rfd[$sk] ?? [];
      $isPairs= !empty($sec['pairs']);
    ?>
    <div class="px-3 pt-2">
      <div style="font-size:.74rem;font-weight:700;color:#fff;background:#1e3a5f;
                  padding:3px 10px;border-radius:4px 4px 0 0;text-transform:uppercase;letter-spacing:.4px">
        <?= h($sec['title']) ?>
      </div>
      <?php if ($isPairs):
        $pairs = array_chunk(array_keys($sec['fields']), 2);
      ?>
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0" style="font-size:.8rem;border-top:none">
          <thead><tr style="background:#e8edf5">
            <th style="width:40%">Skill / Competency</th>
            <th class="text-center" style="width:10%">Grade</th>
            <th style="width:40%">Skill / Competency</th>
            <th class="text-center" style="width:10%">Grade</th>
          </tr></thead>
          <tbody>
          <?php foreach ($pairs as $pair):
            [$k1,$k2] = array_pad($pair,2,null); ?>
          <tr>
            <td><?= h($sec['fields'][$k1]) ?></td>
            <td class="text-center"><?= arBadge($secFd[$k1]??'') ?></td>
            <td><?= $k2 ? h($sec['fields'][$k2]) : '' ?></td>
            <td class="text-center"><?= $k2 ? arBadge($secFd[$k2]??'') : '' ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0" style="font-size:.8rem;border-top:none">
          <thead><tr style="background:#f8fafc">
            <th>Skill / Competency</th>
            <th class="text-center" style="width:90px">Grade</th>
          </tr></thead>
          <tbody>
          <?php foreach ($sec['fields'] as $fk => $flabel): ?>
          <tr>
            <td><?= h($flabel) ?></td>
            <td class="text-center"><?= arBadge($secFd[$fk]??'') ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- Attendance -->
    <?php if (array_filter($rAtt)): ?>
    <div class="px-3 pt-2 pb-3">
      <div style="font-size:.74rem;font-weight:700;color:#fff;background:#374151;
                  padding:3px 10px;border-radius:4px 4px 0 0;text-transform:uppercase;letter-spacing:.4px">
        Attendance
      </div>
      <table class="table table-sm table-bordered mb-0" style="font-size:.82rem;border-top:none">
        <tbody>
        <tr>
          <?php if (!empty($rAtt['class_avg_age'])): ?>
          <td><span class="text-muted">Class Avg Age:</span> <strong><?= h($rAtt['class_avg_age']) ?></strong></td>
          <?php endif; ?>
          <?php if (!empty($rAtt['student_age'])): ?>
          <td><span class="text-muted">Student's Age:</span> <strong><?= h($rAtt['student_age']) ?></strong></td>
          <?php endif; ?>
          <?php if (!empty($rAtt['working_days'])): ?>
          <td><span class="text-muted">Working Days:</span> <strong><?= h($rAtt['working_days']) ?></strong></td>
          <?php endif; ?>
        </tr>
        <tr>
          <?php if (!empty($rAtt['days_present'])): ?>
          <td><span class="text-muted">Days Present:</span> <strong style="color:#15803d"><?= h($rAtt['days_present']) ?></strong></td>
          <?php endif; ?>
          <?php if (!empty($rAtt['days_absent'])): ?>
          <td><span class="text-muted">Days Absent:</span> <strong style="color:#dc2626"><?= h($rAtt['days_absent']) ?></strong></td>
          <?php endif; ?>
          <?php if (isset($rAtt['punctuality']) && $rAtt['punctuality'] !== ''): ?>
          <td><span class="text-muted">Punctuality:</span> <strong><?= h($rAtt['punctuality']) ?></strong></td>
          <?php endif; ?>
        </tr>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="pb-2"></div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

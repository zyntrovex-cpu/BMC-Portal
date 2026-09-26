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
try { $db->query('SELECT 1 FROM ilc_academic_results2 LIMIT 0'); $tableExists = true; } catch (Exception $e) {}

$results = [];
if ($tableExists) {
    $st = $db->prepare(
        'SELECT r.*, u.name AS recorder_name
         FROM ilc_academic_results2 r
         JOIN users u ON u.id = r.recorded_by
         WHERE r.student_id = ?
         ORDER BY r.created_at DESC'
    );
    $st->execute([$student['id']]);
    $results = $st->fetchAll();
}

$SKILLS_PAIRS = [
    ['motor_skills','Motor skills',                'eye_hand','Eye & hand coordination'],
    ['psycho_cognitive','Psycho cognitive',         'attention_span','Attention span / concentration'],
    ['behavior','Behavior',                         'auditory_skills','Auditory skills'],
    ['speech_improvement','Speech improvement',     'vegetative_skills','Vegetative skills'],
    ['appearance','Appearance',                     'object_use','Object use'],
    ['health_hygiene','Health & hygiene',           'linguistic_skills','Linguistic skills'],
    ['class_activity','Participation in class activity','imitation_skills','Imitation skills'],
];

$ASSESSMENT_LEVELS = [
    'exceeding' => 'Exceeding',
    'expected'  => 'Expected',
    'emerging'  => 'Emerging',
    'not_yet'   => 'Not yet',
];

function ar2Badge(string $g): string {
    if ($g === '') return '<span style="color:#94a3b8;font-size:.78rem">—</span>';
    $map = ['A+'=>['#166534','#dcfce7'],'A'=>['#15803d','#f0fdf4'],'B'=>['#1d4ed8','#dbeafe'],
            'C'=>['#b45309','#fef3c7'],'D'=>['#dc2626','#fee2e2'],'N.A.'=>['#6b7280','#f3f4f6']];
    [$fg,$bg] = $map[$g] ?? ['#374151','#f9fafb'];
    return "<span style='background:$bg;color:$fg;border-radius:4px;padding:2px 8px;font-weight:700;font-size:.78rem;display:inline-block;min-width:36px;text-align:center'>$g</span>";
}

pageHead('Academic Result 2','student');
$links = getStudentLinks();
?>
<div class="portal-wrap">
<?php sidebar('student','academic-result2',$links,$user); ?>
<div class="main-area">
<?php topbar('Academic Result 2',$user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Branding strip -->
<div class="d-flex align-items-center gap-3 mb-4 p-3"
     style="background:linear-gradient(90deg,#f0fdf4,#dcfce7);border-radius:10px;border:1px solid #86efac">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC"
       style="width:44px;height:44px;object-fit:contain;flex-shrink:0" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#14532d;letter-spacing:.8px;text-transform:uppercase">
      My Progress Reports
    </div>
    <div style="font-size:.78rem;color:#475569">Inclusive Learning Centre · Bahria Model College Bin Qasim</div>
  </div>
</div>

<?php if (!$tableExists): ?>
<div class="sec-card">
  <div style="padding:60px;text-align:center;color:var(--t2)">
    <i class="fas fa-chart-bar fa-2x mb-3 d-block" style="opacity:.15"></i>
    <div style="font-size:.88rem">Progress Reports are not yet set up.</div>
    <div style="font-size:.78rem;margin-top:6px">Contact your ILC administrator.</div>
  </div>
</div>

<?php elseif (empty($results)): ?>
<div class="sec-card">
  <div style="padding:60px;text-align:center;color:var(--t2)">
    <i class="fas fa-chart-bar fa-2x mb-3 d-block" style="opacity:.15"></i>
    <div style="font-size:.88rem">No Progress Reports have been submitted for your profile yet.</div>
    <div style="font-size:.78rem;margin-top:6px;color:var(--t2)">
      Reports are entered by your ILC teacher. Check back later.
    </div>
  </div>
</div>

<?php else: ?>
<?php foreach ($results as $rec):
  $rfd  = json_decode($rec['form_data'],true) ?? [];
  $rBasic= $rfd['basic']    ?? [];
  $rSubj = $rfd['subjects'] ?? [];
  $rSkill= $rfd['skills']   ?? [];
  $rLevel= $rfd['levels']   ?? [];
  $rRem  = $rfd['remarks']  ?? '';
  $totMax= array_sum(array_column($rSubj,'max_marks'));
  $totObt= array_sum(array_column($rSubj,'obtained'));
  $pdfUrl = url('/portal/ilc/academic-result2-pdf.php?id='.$rec['id']);
?>
<div class="sec-card mb-4">
  <div class="sec-card-header d-flex justify-content-between align-items-center"
       style="background:linear-gradient(90deg,#14532d,#15803d);color:#fff">
    <div>
      <i class="fas fa-chart-bar me-2"></i>
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

  <div id="ar2-body-<?= $rec['id'] ?>">

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

    <!-- Basic Info strip -->
    <?php if (array_filter($rBasic)): ?>
    <div class="d-flex flex-wrap gap-3 px-3 pt-2 pb-1" style="font-size:.8rem">
      <?php if (!empty($rBasic['teacher_name'])): ?>
      <span><span class="text-muted">Teacher:</span> <strong><?= h($rBasic['teacher_name']) ?></strong></span>
      <?php endif; ?>
      <?php if (!empty($rBasic['attendance'])): ?>
      <span><span class="text-muted">Attendance:</span> <strong><?= h($rBasic['attendance']) ?> days</strong></span>
      <?php endif; ?>
      <?php if (!empty($rBasic['category'])): ?>
      <span><span class="text-muted">Category:</span> <strong><?= h($rBasic['category']) ?></strong></span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Academic Subjects -->
    <?php if (!empty($rSubj)): ?>
    <div class="px-3 pt-2">
      <div style="font-size:.74rem;font-weight:700;color:#fff;background:#065f46;
                  padding:3px 10px;border-radius:4px 4px 0 0;text-transform:uppercase;letter-spacing:.4px">
        Academic Subjects
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0" style="font-size:.8rem;border-top:none">
          <thead><tr style="background:#d1fae5;text-align:center">
            <th style="text-align:left;width:34%">Subject</th>
            <th style="width:14%">Max Marks</th>
            <th style="width:14%">Obtained</th>
            <th style="width:14%">Percentage</th>
            <th style="width:10%">Grade</th>
          </tr></thead>
          <tbody>
          <?php foreach ($rSubj as $row):
            if ($row['subject']==='' && $row['max_marks']==='' && $row['obtained']==='') continue; ?>
          <tr>
            <td><?= h($row['subject']) ?></td>
            <td class="text-center"><?= h($row['max_marks']!=='' ? $row['max_marks'] : '—') ?></td>
            <td class="text-center"><?= h($row['obtained']!=='' ? $row['obtained'] : '—') ?></td>
            <td class="text-center fw-semibold"><?= $row['percentage']!=='' ? h($row['percentage']).'%' : '—' ?></td>
            <td class="text-center"><?= ar2Badge($row['grade']??'') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if ($totMax > 0): ?>
          <tr style="background:#f0fdf4;font-weight:700">
            <td>Total</td>
            <td class="text-center"><?= number_format($totMax, 1) ?></td>
            <td class="text-center"><?= number_format($totObt, 1) ?></td>
            <td class="text-center">
              <?php if ($totMax > 0): ?>
              <span style="background:#dbeafe;color:#1e40af;border-radius:4px;padding:1px 7px;font-size:.78rem;font-weight:700">
                <?= round(($totObt/$totMax)*100) ?>%
              </span>
              <?php endif; ?>
            </td>
            <td></td>
          </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Skills Assessment -->
    <div class="px-3 pt-2">
      <div style="font-size:.74rem;font-weight:700;color:#fff;background:#1e3a5f;
                  padding:3px 10px;border-radius:4px 4px 0 0;text-transform:uppercase;letter-spacing:.4px">
        Skills Assessment
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0" style="font-size:.8rem;border-top:none">
          <thead><tr style="background:#e8edf5">
            <th style="width:38%">Skill</th>
            <th class="text-center" style="width:10%">Grade</th>
            <th style="width:38%">Skill</th>
            <th class="text-center" style="width:10%">Grade</th>
          </tr></thead>
          <tbody>
          <?php foreach ($SKILLS_PAIRS as [$k1,$l1,$k2,$l2]): ?>
          <tr>
            <td><?= h($l1) ?></td>
            <td class="text-center"><?= ar2Badge($rSkill[$k1]??'') ?></td>
            <td><?= h($l2) ?></td>
            <td class="text-center"><?= ar2Badge($rSkill[$k2]??'') ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Assessment Levels -->
    <div class="px-3 pt-2">
      <div style="font-size:.74rem;font-weight:700;color:#fff;background:#7c3aed;
                  padding:3px 10px;border-radius:4px 4px 0 0;text-transform:uppercase;letter-spacing:.4px">
        Assessment Levels
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0" style="font-size:.8rem;border-top:none">
          <thead><tr style="background:#f5f3ff">
            <th>Level</th>
            <th class="text-center" style="width:90px">Grade</th>
          </tr></thead>
          <tbody>
          <?php foreach ($ASSESSMENT_LEVELS as $lk => $ll): ?>
          <tr>
            <td class="fw-semibold"><?= h($ll) ?></td>
            <td class="text-center"><?= ar2Badge($rLevel[$lk]??'') ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Teacher's Remarks -->
    <?php if ($rRem !== ''): ?>
    <div class="px-3 pt-2 pb-3">
      <div style="font-size:.74rem;font-weight:700;color:#fff;background:#374151;
                  padding:3px 10px;border-radius:4px 4px 0 0;text-transform:uppercase;letter-spacing:.4px">
        Teacher's Remarks
      </div>
      <div style="border:1px solid #e2e8f0;border-top:none;padding:8px 12px;font-size:.82rem;background:#fafafa">
        <?= nl2br(h($rRem)) ?>
      </div>
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

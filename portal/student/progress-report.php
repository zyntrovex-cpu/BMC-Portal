<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('student');
$student = getStudentByUserId($user['id']);
$db      = getDB();

if (!$student) { setFlash('danger', 'Student profile not found.'); redirect('/portal/logout.php'); }

$tableExists = false;
try { $db->query('SELECT 1 FROM progress_reports LIMIT 0'); $tableExists = true; } catch (Exception $e) {}

$results = [];
if ($tableExists) {
    $st = $db->prepare(
        'SELECT r.*, u.name AS reporter_name FROM progress_reports r
         JOIN users u ON u.id=r.reported_by WHERE r.student_id=? ORDER BY r.created_at DESC'
    );
    $st->execute([$student['id']]);
    $results = $st->fetchAll();
}

// Migrate old {english:{...}, mathematics:{...}} format to new {subjects:[...]} format
function prMigrateFormData(array $fd): array {
    if (isset($fd['subjects'])) return $fd;
    $OLD = [
        'english' => ['title'=>'ENGLISH','color'=>'#1e3a5f','areas'=>[
            ['title'=>'Communication Skills (Listening & Speaking)','keys'=>['comm_listens'=>'Listens and follows instructions','comm_converses'=>'Converses by using sufficient vocabulary','comm_articulates'=>'Articulates on different topics']],
            ['title'=>'Comprehension (Reading & Thinking Skills)','keys'=>['comp_reads'=>'Reads sentences with accuracy in pronunciation','comp_comprehends'=>'Comprehends paragraphs & responds to questions','comp_narrates'=>'Narrates & retells the gist of text']],
            ['title'=>'Language Concepts','keys'=>['lang_punct'=>'Recognizes and uses punctuation in sentences','lang_pos'=>'Familiar with use of different parts of speech']],
            ['title'=>'Vocabulary & Writing Skills','keys'=>['vocab_syllables'=>'Recognizes and makes two-syllable words','vocab_constructs'=>'Infers meanings and constructs sentences independently','vocab_paragraphs'=>'Writes paragraphs and describes pictures']],
        ]],
        'mathematics' => ['title'=>'MATHEMATICS','color'=>'#065f46','areas'=>[
            ['title'=>'Numbers and Operations','keys'=>['num_place_value'=>'Demonstrates knowledge of place value','num_operations'=>'Demonstrates understanding of basic mathematical operations','num_fractions'=>'Recognizes and names unit fractions']],
            ['title'=>'Geometry & Measurements','keys'=>['geo_time'=>'Reads & writes time','geo_measurements'=>'Measures & compares objects using length and weight','geo_shapes'=>'Names and describes 2D & 3D shapes']],
        ]],
    ];
    $subjects = [];
    foreach ($OLD as $sk => $sec) {
        $subj = ['title'=>$sec['title'],'color'=>$sec['color'],'areas'=>[]];
        $oldData = $fd[$sk] ?? [];
        foreach ($sec['areas'] as $area) {
            $inds = [];
            foreach ($area['keys'] as $key => $label)
                $inds[] = ['label'=>$label,'value'=>$oldData[$key]??''];
            $subj['areas'][] = ['title'=>$area['title'],'indicators'=>$inds];
        }
        $subjects[] = $subj;
    }
    $fd['subjects'] = $subjects;
    return $fd;
}

function prBadge(string $v): string {
    if ($v === '') return '<span style="color:#94a3b8;font-size:.78rem">—</span>';
    $map = ['AD'=>['#1e3a5f','#dbeafe'],'ED'=>['#065f46','#d1fae5'],'EMD'=>['#92400e','#fef3c7']];
    [$fg, $bg] = $map[$v] ?? ['#374151','#f3f4f6'];
    return "<span style='background:$bg;color:$fg;border-radius:4px;padding:2px 9px;font-weight:700;"
         . "font-size:.78rem;display:inline-block;min-width:42px;text-align:center'>$v</span>";
}

pageHead('Progress Report', 'student');
$links = getStudentLinks();
?>
<div class="portal-wrap">
<?php sidebar('student', 'progress-report', $links, $user); ?>
<div class="main-area">
<?php topbar('My Progress Reports', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="d-flex align-items-center gap-3 mb-4 p-3"
     style="background:linear-gradient(90deg,#eef2ff,#e0e7ff);border-radius:10px;border:1px solid #a5b4fc">
  <img src="<?= url('/assets/bmc-logo.png') ?>" alt="BMC"
       style="width:44px;height:44px;object-fit:contain;flex-shrink:0" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#3730a3;letter-spacing:.8px;text-transform:uppercase">
      My Progress Reports
    </div>
    <div style="font-size:.78rem;color:#475569">Bahria College — Pakistan Navy Educational Trust</div>
  </div>
</div>

<?php if (!$tableExists): ?>
<div class="sec-card">
  <div style="padding:60px;text-align:center;color:var(--t2)">
    <i class="fas fa-file-alt fa-2x mb-3 d-block" style="opacity:.15"></i>
    <div style="font-size:.88rem">Progress Reports are not yet set up.</div>
    <div style="font-size:.78rem;margin-top:6px">Contact your administrator.</div>
  </div>
</div>

<?php elseif (empty($results)): ?>
<div class="sec-card">
  <div style="padding:60px;text-align:center;color:var(--t2)">
    <i class="fas fa-file-alt fa-2x mb-3 d-block" style="opacity:.15"></i>
    <div style="font-size:.88rem">No Progress Reports have been submitted for your profile yet.</div>
    <div style="font-size:.78rem;margin-top:6px;color:var(--t2)">
      Reports are issued by your teacher. Check back after term exams.
    </div>
  </div>
</div>

<?php else: ?>
<?php foreach ($results as $rec):
  $rfd      = prMigrateFormData(json_decode($rec['form_data'], true) ?? []);
  $rBasic   = $rfd['basic']    ?? [];
  $subjects = $rfd['subjects'] ?? [];
  $rRem     = $rfd['remarks']  ?? '';
  $pdfUrl   = url('/portal/progress-report/pdf.php?id='.$rec['id']);
?>
<div class="sec-card mb-4">
  <div class="sec-card-header d-flex justify-content-between align-items-center"
       style="background:linear-gradient(90deg,#3730a3,#4338ca);color:#fff">
    <div>
      <i class="fas fa-file-alt me-2"></i>
      <?= h($rec['term']) ?><?= $rec['session'] ? ' — '.h($rec['session']) : '' ?>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span style="font-size:.74rem;opacity:.8"><?= fDate($rec['created_at']) ?></span>
      <a href="<?= $pdfUrl ?>" target="_blank"
         class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;font-size:.78rem;border:1px solid rgba(255,255,255,.35)">
        <i class="fas fa-file-pdf me-1"></i>Download PDF
      </a>
    </div>
  </div>

  <div>
    <!-- Key legend -->
    <div class="d-flex flex-wrap gap-2 align-items-center p-2 pb-0" style="font-size:.74rem">
      <span class="text-muted fw-semibold">Key:</span>
      <?php foreach (['AD'=>['#1e3a5f','#dbeafe','Advanced Development'],'ED'=>['#065f46','#d1fae5','Expected Development'],'EMD'=>['#92400e','#fef3c7','Emerging Development']] as $code=>[$fg,$bg,$desc]): ?>
      <span style="background:<?=$bg?>;color:<?=$fg?>;padding:1px 7px;border-radius:4px;font-weight:700">
        <?=$code?> <span style="font-weight:400;opacity:.8">— <?=$desc?></span>
      </span>
      <?php endforeach; ?>
    </div>

    <?php if (array_filter($rBasic)): ?>
    <div class="d-flex flex-wrap gap-3 px-3 pt-2 pb-1" style="font-size:.8rem">
      <?php if (!empty($rBasic['attendance'])): ?>
      <span><span class="text-muted">Attendance:</span> <strong><?= h($rBasic['attendance']) ?></strong></span>
      <?php endif; ?>
      <?php if (!empty($rBasic['date_of_issue'])): ?>
      <span><span class="text-muted">Date of Issue:</span>
        <strong><?= h(date('d M Y', strtotime($rBasic['date_of_issue']))) ?></strong></span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Subject sections -->
    <?php foreach ($subjects as $subj):
      $color = $subj['color'] ?? '#374151';
    ?>
    <div class="px-3 pt-2">
      <div style="font-size:.74rem;font-weight:700;color:#fff;background:<?= h($color) ?>;
                  padding:3px 10px;border-radius:4px 4px 0 0;text-transform:uppercase;letter-spacing:.4px">
        <?= h($subj['title'] ?? '') ?>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0" style="font-size:.8rem;border-top:none">
          <thead><tr style="background:<?= h($color) ?>22">
            <th colspan="2">Learning Area</th>
            <th class="text-center" style="width:80px">Performance Indicator</th>
          </tr></thead>
          <tbody>
          <?php foreach ($subj['areas'] ?? [] as $area): ?>
          <tr style="background:<?= h($color) ?>15">
            <td colspan="3" class="fw-semibold" style="font-size:.76rem;color:<?= h($color) ?>;padding:3px 10px">
              <?= h($area['title'] ?? '') ?>
            </td>
          </tr>
          <?php foreach ($area['indicators'] ?? [] as $ind): ?>
          <tr>
            <td colspan="2"><?= h($ind['label'] ?? '') ?></td>
            <td class="text-center"><?= prBadge($ind['value'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Remarks -->
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

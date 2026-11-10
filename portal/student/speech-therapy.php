<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('student');
$db   = getDB();

$myStudent = $db->prepare('SELECT s.*, c.name AS class_name FROM students s LEFT JOIN classes c ON c.id = s.class_id WHERE s.user_id = ?');
$myStudent->execute([$user['id']]);
$studentRow = $myStudent->fetch();

$reports = [];
$tableExists = false;
if ($studentRow) {
    try { $db->query('SELECT 1 FROM speech_therapy_reports LIMIT 1'); $tableExists = true; } catch (Exception $e) {}
    if ($tableExists) {
        $st = $db->prepare(
            'SELECT r.*, u.name AS recorder_name
             FROM speech_therapy_reports r JOIN users u ON u.id = r.recorded_by
             WHERE r.student_id = ? ORDER BY r.month DESC'
        );
        $st->execute([$studentRow['id']]);
        $reports = $st->fetchAll();
    }
}

pageHead('Speech Therapy — Assessments', 'student');
$links = getStudentLinks();
?>
<div class="portal-wrap">
<?php sidebar('student', 'speech-therapy', $links, $user); ?>
<div class="main-area">
<?php topbar('Speech Therapy — Assessments', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- ILC branding strip -->
<div class="d-flex align-items-center gap-3 mb-4 p-3"
     style="background:linear-gradient(90deg,#ecfeff,#f0fdf4);border-radius:10px;border:1px solid #a5f3fc;">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC"
       style="width:44px;height:44px;object-fit:contain;flex-shrink:0;" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#0891b2;letter-spacing:.8px;text-transform:uppercase">
      Speech &amp; Language Therapy Assessments
    </div>
    <div style="font-size:.78rem;color:#475569">Inclusive Learning Centre · Bahria Model College Bin Qasim</div>
  </div>
</div>

<?php if (!$studentRow): ?>
<div class="alert alert-info">Student record not found for your account.</div>
<?php elseif (!$tableExists || empty($reports)): ?>
<div class="sec-card">
  <div style="padding:60px;text-align:center;color:var(--t2)">
    <i class="fas fa-comment-medical fa-2x mb-3 d-block" style="opacity:.15"></i>
    <div style="font-size:.88rem">No speech therapy assessments have been added for your profile yet.</div>
    <div style="font-size:.78rem;margin-top:6px;color:var(--t2)">
      Assessments are created by your ILC therapist. Check back later.
    </div>
  </div>
</div>

<?php else: ?>
<div class="sec-card">
  <div class="sec-card-header">
    <i class="fas fa-comment-medical me-2"></i>My Speech &amp; Language Therapy Assessments
    <span class="badge bg-secondary ms-2"><?= count($reports) ?></span>
  </div>
  <div style="padding:16px">
    <?php foreach ($reports as $r):
      $ad     = (!empty($r['assessment_data'])) ? (json_decode($r['assessment_data'],true) ?? []) : [];
      $isNew  = !empty($r['assessment_data']);
      $s1 = $ad['s1'] ?? []; $s2 = $ad['s2'] ?? []; $s7 = $ad['s7'] ?? []; $s8 = $ad['s8'] ?? []; $s9 = $ad['s9'] ?? [];
    ?>
    <div class="mb-3 p-3" style="background:#f7f9fb;border-radius:10px;border:1px solid var(--border)">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div>
          <span class="badge" style="background:#0891b2;font-size:.76rem">
            <?= date('d M Y', strtotime($r['month'])) ?>
          </span>
          <?php if ($isNew): ?>
          <span class="badge bg-success ms-1" style="font-size:.7rem">Full Assessment</span>
          <?php endif; ?>
          <?php if (!empty($s1['assessment_date'])): ?>
          <span style="font-size:.74rem;color:var(--t2);margin-left:8px">
            Assessed: <?= date('d M Y', strtotime($s1['assessment_date'])) ?>
          </span>
          <?php endif; ?>
          <span style="font-size:.74rem;color:var(--t2);margin-left:8px">
            Therapist: <strong><?= h($r['recorder_name']) ?></strong>
          </span>
        </div>
        <?php if ($isNew): ?>
        <a href="<?= url('/portal/ilc/speech-therapy-pdf.php?id=' . $r['id']) ?>" target="_blank"
           class="btn btn-sm btn-success" style="font-size:.78rem">
          <i class="fas fa-file-pdf me-1"></i>View &amp; Download PDF
        </a>
        <?php endif; ?>
      </div>

      <?php if ($isNew): ?>

      <?php if (!empty($s2['presenting_concern'])): ?>
      <div style="font-size:.8rem;margin-bottom:6px">
        <span class="text-muted" style="font-size:.73rem;text-transform:uppercase;letter-spacing:.3px">Presenting Concern</span><br>
        <?= nl2br(h($s2['presenting_concern'])) ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($s7['long_term_goal'])): ?>
      <div style="font-size:.8rem;margin-bottom:6px;background:#f0f9ff;border-radius:5px;padding:6px 10px;border:1px solid #bae6fd">
        <span class="text-muted" style="font-size:.73rem;font-weight:700;text-transform:uppercase;letter-spacing:.3px">Long-Term Goal</span><br>
        <?= nl2br(h($s7['long_term_goal'])) ?>
      </div>
      <?php endif; ?>

      <?php
      $stGoals = array_filter($s7['short_term_goals'] ?? []);
      if ($stGoals): ?>
      <div style="font-size:.8rem;margin-bottom:6px">
        <span class="text-muted" style="font-size:.73rem;text-transform:uppercase;letter-spacing:.3px">Short-Term Goals</span>
        <ol style="margin:4px 0 0 16px;padding:0">
          <?php foreach ($stGoals as $g): ?>
          <li style="font-size:.8rem"><?= h($g) ?></li>
          <?php endforeach; ?>
        </ol>
      </div>
      <?php endif; ?>

      <?php if (!empty($s8['recommendations'])): ?>
      <div style="font-size:.8rem;margin-bottom:6px;background:#f0fdf4;border-radius:5px;padding:6px 10px;border:1px solid #bbf7d0">
        <span class="text-muted" style="font-size:.73rem;font-weight:700;text-transform:uppercase;letter-spacing:.3px;color:#166534">Therapist Recommendations</span><br>
        <?= nl2br(h($s8['recommendations'])) ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($s9['next_review_date'])): ?>
      <div style="font-size:.78rem;color:var(--t2);margin-top:4px">
        <i class="fas fa-calendar-alt me-1"></i>Next Review:
        <strong><?= date('d M Y', strtotime($s9['next_review_date'])) ?></strong>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <?php if ($r['therapist_notes']): ?>
      <div style="font-size:.8rem;margin-bottom:4px"><strong>Notes:</strong> <?= nl2br(h($r['therapist_notes'])) ?></div>
      <?php endif; ?>
      <?php if ($r['progress_summary']): ?>
      <div style="font-size:.8rem;margin-bottom:4px"><strong>Progress:</strong> <?= nl2br(h($r['progress_summary'])) ?></div>
      <?php endif; ?>
      <?php if ($r['goals_next_month']): ?>
      <div style="font-size:.8rem;color:#0369a1"><strong>Goals:</strong> <?= nl2br(h($r['goals_next_month'])) ?></div>
      <?php endif; ?>
      <div style="font-size:.75rem;color:var(--t2);margin-top:6px;font-style:italic">
        Legacy report — detailed assessment data not available.
      </div>
      <?php endif; ?>

    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

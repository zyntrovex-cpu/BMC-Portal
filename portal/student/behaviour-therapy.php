<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('student');
$db   = getDB();

// Get this student's record
$myStudent = $db->prepare('SELECT s.*, c.name AS class_name FROM students s LEFT JOIN classes c ON c.id = s.class_id WHERE s.user_id = ?');
$myStudent->execute([$user['id']]);
$studentRow = $myStudent->fetch();

$reports = [];
if ($studentRow) {
    $tableExists = false;
    $abaColExists = false;
    try { $db->query('SELECT 1 FROM behaviour_therapy_reports LIMIT 1'); $tableExists = true; } catch (Exception $e) {}
    if ($tableExists) {
        try { $db->query('SELECT aba_data FROM behaviour_therapy_reports LIMIT 0'); $abaColExists = true; } catch (Exception $e) {}
    }

    if ($tableExists) {
        $st = $db->prepare(
            'SELECT r.*, u.name AS recorder_name
             FROM behaviour_therapy_reports r
             JOIN users u ON u.id = r.recorded_by
             WHERE r.student_id = ?
             ORDER BY r.month DESC'
        );
        $st->execute([$studentRow['id']]);
        $reports = $st->fetchAll();
    }
}

pageHead('Behaviour Therapy — ABA Reports', 'student');
$links = getStudentLinks();
?>
<div class="portal-wrap">
<?php sidebar('student', 'behaviour-therapy', $links, $user); ?>
<div class="main-area">
<?php topbar('Behaviour Therapy — ABA Reports', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- ILC branding strip -->
<div class="d-flex align-items-center gap-3 mb-4 p-3"
     style="background:linear-gradient(90deg,#ecfeff,#f0fdf4);border-radius:10px;border:1px solid #a5f3fc;">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC"
       style="width:44px;height:44px;object-fit:contain;flex-shrink:0;" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#0891b2;letter-spacing:.8px;text-transform:uppercase">
      Behaviour Therapy — ABA Reports
    </div>
    <div style="font-size:.78rem;color:#475569">Inclusive Learning Centre · Bahria Model College Bin Qasim</div>
  </div>
</div>

<?php if (!$studentRow): ?>
<div class="alert alert-info">Student record not found for your account.</div>
<?php elseif (empty($reports)): ?>
<div class="sec-card">
  <div style="padding:60px;text-align:center;color:var(--t2)">
    <i class="fas fa-brain fa-2x mb-3 d-block" style="opacity:.15"></i>
    <div style="font-size:.88rem">No ABA therapy reports have been added for your profile yet.</div>
    <div style="font-size:.78rem;margin-top:6px;color:var(--t2)">
      Reports are created by your ILC therapist. Check back later.
    </div>
  </div>
</div>

<?php else: ?>
<div class="sec-card">
  <div class="sec-card-header">
    <i class="fas fa-brain me-2"></i>My ABA Therapy Reports
    <span class="badge bg-secondary ms-2"><?= count($reports) ?></span>
  </div>
  <div style="padding:16px">
    <?php foreach ($reports as $r):
      $aba      = (!empty($r['aba_data'])) ? (json_decode($r['aba_data'], true) ?? []) : [];
      $diag     = $aba['client']['diagnosis']          ?? '';
      $behaviors= array_filter($aba['target_behaviors'] ?? []);
      $goals    = array_filter($aba['goals']           ?? []);
      $summary  = $aba['progress_notes']['session_summary'] ?? $r['therapist_notes'] ?? '';
      $nextSteps= $aba['progress_notes']['next_steps']      ?? $r['goals_next_month'] ?? '';
      $isAba    = !empty($r['aba_data']);
    ?>
    <div class="mb-3 p-3" style="background:#f7f9fb;border-radius:10px;border:1px solid var(--border)">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div>
          <span class="badge" style="background:#0891b2;font-size:.76rem">
            <?= date('F Y', strtotime($r['month'])) ?>
          </span>
          <?php if ($isAba): ?>
          <span class="badge bg-success ms-1" style="font-size:.7rem">ABA Report</span>
          <?php endif; ?>
          <span style="font-size:.74rem;color:var(--t2);margin-left:8px">
            Therapist: <strong><?= h($r['recorder_name']) ?></strong>
          </span>
        </div>
        <?php if ($isAba): ?>
        <a href="<?= url('/portal/ilc/aba-report-pdf.php?id=' . $r['id']) ?>" target="_blank"
           class="btn btn-sm btn-success" style="font-size:.78rem">
          <i class="fas fa-file-pdf me-1"></i>View &amp; Download PDF
        </a>
        <?php endif; ?>
      </div>

      <?php if ($diag): ?>
      <div style="font-size:.8rem;margin-bottom:6px">
        <span class="text-muted" style="font-size:.74rem;text-transform:uppercase;letter-spacing:.3px">Diagnosis:</span>
        <strong> <?= h($diag) ?></strong>
      </div>
      <?php endif; ?>

      <?php if ($behaviors): ?>
      <div style="font-size:.8rem;margin-bottom:6px">
        <span class="text-muted" style="font-size:.74rem;text-transform:uppercase;letter-spacing:.3px">Target Behaviors:</span>
        <ul style="margin:4px 0 0 16px;padding:0">
          <?php foreach ($behaviors as $b): ?>
          <li style="font-size:.8rem"><?= h($b) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php if ($goals): ?>
      <div style="font-size:.8rem;margin-bottom:6px">
        <span class="text-muted" style="font-size:.74rem;text-transform:uppercase;letter-spacing:.3px">SMART Goals:</span>
        <ol style="margin:4px 0 0 16px;padding:0">
          <?php foreach ($goals as $g): ?>
          <li style="font-size:.8rem"><?= h($g) ?></li>
          <?php endforeach; ?>
        </ol>
      </div>
      <?php endif; ?>

      <?php if ($summary): ?>
      <div style="font-size:.8rem;margin-bottom:4px;background:#f0f9ff;border-radius:5px;padding:6px 10px;border:1px solid #bae6fd">
        <span class="text-muted" style="font-size:.73rem;font-weight:700;text-transform:uppercase;letter-spacing:.3px">Session Summary</span><br>
        <?= nl2br(h($summary)) ?>
      </div>
      <?php endif; ?>

      <?php if ($nextSteps): ?>
      <div style="font-size:.8rem;background:#f0fdf4;border-radius:5px;padding:6px 10px;border:1px solid #bbf7d0">
        <span class="text-muted" style="font-size:.73rem;font-weight:700;text-transform:uppercase;letter-spacing:.3px;color:#166534">Next Steps</span><br>
        <?= nl2br(h($nextSteps)) ?>
      </div>
      <?php endif; ?>

      <?php if (!$isAba): ?>
      <div style="font-size:.75rem;color:var(--t2);margin-top:6px;font-style:italic">
        Legacy report — detailed ABA data not available.
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

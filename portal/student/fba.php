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

$plans = [];
$tableExists = false;
if ($studentRow) {
    try { $db->query('SELECT 1 FROM fba_plans LIMIT 1'); $tableExists = true; } catch (Exception $e) {}
    if ($tableExists) {
        $st = $db->prepare(
            'SELECT f.*, u.name AS recorder_name
             FROM fba_plans f
             JOIN users u ON u.id = f.recorded_by
             WHERE f.student_id = ?
             ORDER BY f.created_at DESC'
        );
        $st->execute([$studentRow['id']]);
        $plans = $st->fetchAll();
    }
}

$areasOfConcernList = [
    'anger'               => 'Frequent anger / irritability',
    'negative_thinking'   => 'Negative thinking / pessimism',
    'blaming'             => 'Blaming others',
    'difficulty_criticism'=> 'Difficulty accepting criticism',
    'argumentative'       => 'Argumentative behaviour',
    'impulsivity'         => 'Impulsivity',
    'jealousy'            => 'Jealousy / insecurity',
    'low_self_esteem'     => 'Low self-esteem',
    'poor_boundaries'     => 'Poor personal boundaries',
    'withdrawal'          => 'Social withdrawal',
    'avoidance'           => 'Avoidance behaviour',
    'other'               => 'Other',
];

pageHead('FBA — Behavior Management Plan', 'student');
$links = getStudentLinks();
?>
<div class="portal-wrap">
<?php sidebar('student', 'fba', $links, $user); ?>
<div class="main-area">
<?php topbar('FBA — Behavior Management Plan', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- ILC branding strip -->
<div class="d-flex align-items-center gap-3 mb-4 p-3"
     style="background:linear-gradient(90deg,#fff7ed,#fef9c3);border-radius:10px;border:1px solid #fbbf24;">
  <img src="<?= url('/assets/ilc-logo.png') ?>" alt="ILC"
       style="width:44px;height:44px;object-fit:contain;flex-shrink:0;" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#92400e;letter-spacing:.8px;text-transform:uppercase">
      Functional Behaviour Assessment — Behavior Management Plan
    </div>
    <div style="font-size:.78rem;color:#475569">Inclusive Learning Centre · Bahria Model College Bin Qasim</div>
  </div>
</div>

<?php if (!$studentRow): ?>
<div class="alert alert-info">Student record not found for your account.</div>
<?php elseif (!$tableExists || empty($plans)): ?>
<div class="sec-card">
  <div style="padding:60px;text-align:center;color:var(--t2)">
    <i class="fas fa-clipboard-check fa-2x mb-3 d-block" style="opacity:.15"></i>
    <div style="font-size:.88rem">No FBA / Behavior Management Plans have been created for your profile yet.</div>
    <div style="font-size:.78rem;margin-top:6px;color:var(--t2)">
      Plans are created by your ILC facilitator. Check back later.
    </div>
  </div>
</div>

<?php else: ?>
<div class="sec-card">
  <div class="sec-card-header">
    <i class="fas fa-clipboard-check me-2"></i>My FBA / Behavior Management Plans
    <span class="badge bg-secondary ms-2"><?= count($plans) ?></span>
  </div>
  <div style="padding:16px">
    <?php foreach ($plans as $p):
      $fd = (!empty($p['form_data'])) ? (json_decode($p['form_data'], true) ?? []) : [];
      $basic  = $fd['basic']            ?? [];
      $aoc    = $fd['areas_of_concern'] ?? [];
      $notes  = $fd['notes']            ?? '';
    ?>
    <div class="mb-3 p-3" style="background:#f7f9fb;border-radius:10px;border:1px solid var(--border)">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div>
          <?php if (!empty($basic['session_no'])): ?>
          <span class="badge" style="background:#92400e;font-size:.76rem">
            Session <?= h($basic['session_no']) ?>
          </span>
          <?php endif; ?>
          <?php if (!empty($basic['date'])): ?>
          <span class="badge" style="background:#1e3a5f;font-size:.76rem;margin-left:4px">
            <?= date('d M Y', strtotime($basic['date'])) ?>
          </span>
          <?php endif; ?>
          <span style="font-size:.74rem;color:var(--t2);margin-left:8px">
            Facilitator: <strong><?= h($p['recorder_name']) ?></strong>
          </span>
        </div>
        <a href="<?= url('/portal/ilc/fba-pdf.php?id=' . $p['id']) ?>" target="_blank"
           class="btn btn-sm" style="font-size:.78rem;background:#92400e;color:#fff;border:none">
          <i class="fas fa-file-pdf me-1"></i>View &amp; Download PDF
        </a>
      </div>

      <?php if (!empty($aoc)): ?>
      <div style="font-size:.8rem;margin-bottom:6px">
        <span class="text-muted" style="font-size:.73rem;text-transform:uppercase;letter-spacing:.3px">Areas of Concern:</span>
        <div style="margin-top:4px;display:flex;flex-wrap:wrap;gap:4px">
          <?php foreach ($aoc as $aKey):
            $aLabel = $areasOfConcernList[$aKey] ?? ucwords(str_replace('_',' ',$aKey));
          ?>
          <span class="badge" style="background:#1e3a5f;font-size:.72rem;font-weight:500"><?= h($aLabel) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($basic['review_date'])): ?>
      <div style="font-size:.78rem;color:var(--t2);margin-top:4px">
        <i class="fas fa-calendar-alt me-1"></i>Review Date:
        <strong><?= date('d M Y', strtotime($basic['review_date'])) ?></strong>
      </div>
      <?php endif; ?>

      <?php if ($notes): ?>
      <div style="font-size:.8rem;margin-top:6px;background:#fffbeb;border-radius:5px;padding:6px 10px;border:1px solid #fde68a">
        <span class="text-muted" style="font-size:.73rem;font-weight:700;text-transform:uppercase;letter-spacing:.3px;color:#92400e">Notes</span><br>
        <?= nl2br(h($notes)) ?>
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

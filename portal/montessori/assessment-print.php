<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../config/db.php';

$user    = requireAuth('montessori_teacher', 'wing_head');
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);

$assessmentId = (int)($_GET['id'] ?? 0);
if (!$assessmentId) { header('Location: /portal/montessori/assessment-history.php'); exit; }

// Load the assessment
$st = $db->prepare(
    "SELECT mda.*, s.name AS subject_name, c.name AS class_name, c.grade AS class_grade,
            u.name AS teacher_name
     FROM montessori_daily_assessments mda
     JOIN subjects s ON mda.subject_id = s.id
     JOIN classes c ON mda.class_id = c.id
     JOIN teachers t ON mda.teacher_id = t.id
     JOIN users u ON t.user_id = u.id
     WHERE mda.id = ?"
);
$st->execute([$assessmentId]);
$assessment = $st->fetch();

if (!$assessment) { header('Location: /portal/montessori/assessment-history.php'); exit; }

// For teacher role, restrict to their own classes
if ($user['role'] === 'montessori_teacher' && $teacher) {
    $chk = $db->prepare('SELECT id FROM montessori_daily_assessments WHERE id=? AND teacher_id=?');
    $chk->execute([$assessmentId, $teacher['id']]);
    if (!$chk->fetch()) { header('Location: /portal/montessori/assessment-history.php'); exit; }
}

// Load criteria
$criteria = [];
if (!empty($assessment['criteria'])) {
    $decoded = json_decode($assessment['criteria'], true);
    if (is_array($decoded)) $criteria = $decoded;
}

// Load student entries
$entries = [];
$eSt = $db->prepare(
    "SELECT e.student_id, e.ratings, e.overall, e.remarks,
            u2.name AS student_name, st.roll_no AS roll_number
     FROM montessori_daily_assessment_entries e
     JOIN students st ON e.student_id = st.id
     JOIN users u2 ON st.user_id = u2.id
     WHERE e.assessment_id = ?
     ORDER BY u2.name"
);
$eSt->execute([$assessmentId]);
$entries = $eSt->fetchAll();

$ratingLabel = ['AD' => 'AD — As Desired', 'ED' => 'ED — Emerging Desired', 'EMD' => 'EMD — Expected More Desired'];
$ratingColors = ['AD' => '#16a34a', 'ED' => '#d97706', 'EMD' => '#dc2626'];
$ratingBg    = ['AD' => '#dcfce7', 'ED' => '#fef3c7', 'EMD' => '#fee2e2'];

$schoolName  = 'Beaconhouse Margalla Campus';
$printDate   = date('d M Y');
$assessDate  = date('d M Y', strtotime($assessment['assessment_date']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assessment Report — <?= htmlspecialchars($assessment['class_name']) ?> &middot; <?= htmlspecialchars($assessment['subject_name']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,Helvetica,sans-serif;font-size:11pt;color:#1a1a1a;background:#f5f5f5}
.page-wrap{max-width:800px;margin:24px auto;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.12);overflow:hidden}
/* Header */
.report-header{background:linear-gradient(135deg,#1e3a5f,#2e7d9a);color:#fff;padding:20px 28px 18px}
.school-name{font-size:16pt;font-weight:700;letter-spacing:.4px;margin-bottom:2px}
.report-subtitle{font-size:9.5pt;opacity:.85;letter-spacing:.2px}
.report-badge{display:inline-block;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);border-radius:20px;padding:3px 12px;font-size:8.5pt;margin-top:6px}
/* Meta strip */
.meta-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:0;border-bottom:2px solid #1e3a5f}
.meta-cell{padding:10px 14px;border-right:1px solid #e5e7eb}
.meta-cell:last-child{border-right:none}
.meta-label{font-size:7.5pt;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;font-weight:600;margin-bottom:2px}
.meta-value{font-size:10.5pt;font-weight:700;color:#1e3a5f}
/* Table */
.table-wrap{padding:0 24px 20px}
.section-title{font-size:10pt;font-weight:700;color:#1e3a5f;padding:14px 0 6px;border-bottom:1px solid #e2e8f0;margin-bottom:10px;text-transform:uppercase;letter-spacing:.4px}
table{width:100%;border-collapse:collapse;font-size:9.5pt}
thead tr{background:#1e3a5f;color:#fff}
thead th{padding:7px 10px;font-weight:600;font-size:8.5pt;text-align:left;letter-spacing:.2px}
thead th.center{text-align:center}
tbody tr{border-bottom:1px solid #f1f5f9}
tbody tr:nth-child(even){background:#f8fafc}
tbody td{padding:6px 10px;vertical-align:middle}
tbody td.center{text-align:center}
.student-name{font-weight:600;font-size:9.5pt}
.roll-no{font-size:8pt;color:#9ca3af}
.rating-chip{display:inline-block;border-radius:3px;padding:2px 7px;font-size:8pt;font-weight:700;letter-spacing:.4px}
.rating-AD  {background:#dcfce7;color:#15803d;border:1px solid #86efac}
.rating-ED  {background:#fef3c7;color:#b45309;border:1px solid #fcd34d}
.rating-EMD {background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5}
.overall-chip{display:inline-block;border-radius:20px;padding:2px 10px;font-size:8.5pt;font-weight:700}
.remarks-text{font-size:8.5pt;color:#374151;max-width:160px}
/* Legend */
.legend{display:flex;gap:16px;padding:10px 24px 14px;background:#f8fafc;border-top:1px solid #e5e7eb}
.legend-item{display:flex;align-items:center;gap:5px;font-size:8pt;color:#4b5563}
.legend-dot{width:24px;height:16px;border-radius:3px;font-size:7.5pt;font-weight:700;display:flex;align-items:center;justify-content:center}
/* Topic box */
.topic-box{padding:10px 24px;background:#eff6ff;border-bottom:1px solid #bfdbfe;font-size:9.5pt}
.topic-box .label{font-size:8pt;text-transform:uppercase;color:#6b7280;font-weight:600;letter-spacing:.4px;margin-bottom:2px}
.topic-box .value{font-weight:600;color:#1e3a5f}
/* Footer */
.report-footer{border-top:1px solid #e5e7eb;padding:10px 24px;display:flex;justify-content:space-between;align-items:center;font-size:8pt;color:#9ca3af;background:#f9fafb}
/* Criteria summary row */
.criteria-labels{display:flex;gap:6px;flex-wrap:wrap;margin-top:2px}
.criteria-pill{background:#e0f2fe;color:#0369a1;border-radius:12px;padding:1px 8px;font-size:7.5pt;font-weight:600}
/* Print & screen controls */
.no-print{padding:16px 28px;background:#fff;border-bottom:1px solid #e5e7eb;display:flex;gap:10px;align-items:center}
.no-print .back-link{color:#1e3a5f;font-size:.88rem;text-decoration:none;display:flex;align-items:center;gap:5px}
.no-print .back-link:hover{text-decoration:underline}
.btn-print,.btn-dl{border:none;border-radius:5px;padding:7px 16px;font-size:.84rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px}
.btn-print{background:#1e3a5f;color:#fff}
.btn-dl{background:#0891b2;color:#fff}
@media print{
  body{background:#fff}
  .no-print{display:none!important}
  .page-wrap{box-shadow:none;border-radius:0;margin:0;max-width:100%}
  table{page-break-inside:auto}
  tr{page-break-inside:avoid;page-break-after:auto}
  @page{margin:1.5cm;size:A4}
}
</style>
</head>
<body>
<div class="page-wrap">

  <div class="no-print">
    <a href="/portal/montessori/assessment-history.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to History</a>
    <div style="flex:1"></div>
    <button class="btn-dl" onclick="window.print()"><i class="fas fa-file-pdf"></i> Download / Save PDF</button>
    <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
  </div>

  <div class="report-header">
    <div class="school-name"><i class="fas fa-school" style="font-size:13pt;margin-right:6px;opacity:.85"></i><?= htmlspecialchars($schoolName) ?></div>
    <div class="report-subtitle">Montessori Wing &mdash; Formative Assessment Report</div>
    <span class="report-badge"><i class="fas fa-calendar-day" style="font-size:8pt;margin-right:4px"></i><?= $assessDate ?></span>
  </div>

  <div class="meta-strip">
    <div class="meta-cell">
      <div class="meta-label">Class</div>
      <div class="meta-value"><?= htmlspecialchars($assessment['class_name']) ?></div>
    </div>
    <div class="meta-cell">
      <div class="meta-label">Subject</div>
      <div class="meta-value"><?= htmlspecialchars($assessment['subject_name']) ?></div>
    </div>
    <div class="meta-cell">
      <div class="meta-label">Teacher</div>
      <div class="meta-value"><?= htmlspecialchars($assessment['teacher_name']) ?></div>
    </div>
    <div class="meta-cell">
      <div class="meta-label">Students Assessed</div>
      <div class="meta-value"><?= count($entries) ?></div>
    </div>
  </div>

  <?php if (!empty($assessment['topic'])): ?>
  <div class="topic-box">
    <div class="label">Topic / Activity</div>
    <div class="value"><?= htmlspecialchars($assessment['topic']) ?></div>
    <?php if (!empty($criteria)): ?>
    <div class="criteria-labels" style="margin-top:5px">
      <?php foreach ($criteria as $c): ?>
      <span class="criteria-pill"><?= htmlspecialchars($c) ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="table-wrap">
    <div class="section-title"><i class="fas fa-users" style="margin-right:5px"></i>Student Assessment Results</div>

    <?php if (empty($entries)): ?>
    <p style="color:#9ca3af;font-size:.88rem;padding:16px 0">No student entries recorded for this assessment.</p>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th style="width:28px">#</th>
          <th>Student Name</th>
          <th class="center">Overall</th>
          <?php foreach ($criteria as $c): ?>
          <th class="center"><?= htmlspecialchars($c) ?></th>
          <?php endforeach; ?>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($entries as $i => $entry):
          $ratings = [];
          if (!empty($entry['ratings'])) {
              $dec = json_decode($entry['ratings'], true);
              if (is_array($dec)) $ratings = $dec;
          }
          $overall = $entry['overall'] ?? '';
        ?>
        <tr>
          <td style="color:#9ca3af;font-size:8.5pt"><?= $i + 1 ?></td>
          <td>
            <div class="student-name"><?= htmlspecialchars($entry['student_name']) ?></div>
            <?php if ($entry['roll_number']): ?><div class="roll-no">Roll: <?= htmlspecialchars($entry['roll_number']) ?></div><?php endif; ?>
          </td>
          <td class="center">
            <?php if ($overall): ?>
            <span class="rating-chip rating-<?= htmlspecialchars($overall) ?>"><?= htmlspecialchars($overall) ?></span>
            <?php else: ?>
            <span style="color:#d1d5db;font-size:8pt">—</span>
            <?php endif; ?>
          </td>
          <?php foreach ($criteria as $c):
            $rv = $ratings[$c] ?? '';
          ?>
          <td class="center">
            <?php if ($rv): ?>
            <span class="rating-chip rating-<?= htmlspecialchars($rv) ?>"><?= htmlspecialchars($rv) ?></span>
            <?php else: ?>
            <span style="color:#d1d5db;font-size:8pt">—</span>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
          <td><span class="remarks-text"><?= htmlspecialchars($entry['remarks'] ?? '') ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <div class="legend">
    <span style="font-size:8pt;color:#6b7280;font-weight:600;margin-right:4px">Rating Scale:</span>
    <div class="legend-item"><span class="legend-dot" style="background:#dcfce7;color:#15803d;border:1px solid #86efac">AD</span> As Desired</div>
    <div class="legend-item"><span class="legend-dot" style="background:#fef3c7;color:#b45309;border:1px solid #fcd34d">ED</span> Emerging Desired</div>
    <div class="legend-item"><span class="legend-dot" style="background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5">EMD</span> Expected More Desired</div>
  </div>

  <div class="report-footer">
    <span><i class="fas fa-print" style="margin-right:4px"></i>Printed: <?= $printDate ?></span>
    <span style="font-style:italic">Montessori Formative Assessment System &mdash; <?= htmlspecialchars($schoolName) ?></span>
    <span>ID #<?= $assessmentId ?></span>
  </div>

</div>
</body>
</html>

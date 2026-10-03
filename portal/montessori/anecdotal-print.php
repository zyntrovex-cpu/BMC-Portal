<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../config/db.php';

$user    = requireAuth('montessori_teacher', 'wing_head');
$db      = getDB();
$teacher = getTeacherByUserId($user['id']);

$recordId = (int)($_GET['id'] ?? 0);

// Load the record
$rec = null;
if ($recordId) {
    try {
        $st = $db->prepare(
            "SELECT mar.*, u.name AS student_name, st.roll_no,
                    c.name AS class_name, tu.name AS teacher_name
             FROM montessori_anecdotal_records mar
             JOIN students st ON mar.student_id=st.id
             JOIN users u ON st.user_id=u.id
             JOIN classes c ON mar.class_id=c.id
             JOIN teachers t ON mar.teacher_id=t.id
             JOIN users tu ON t.user_id=tu.id
             WHERE mar.id=?"
        );
        $st->execute([$recordId]);
        $rec = $st->fetch();
    } catch (Exception $e) {}
}

if (!$rec) {
    header('Location: /portal/montessori/anecdotal-history.php');
    exit;
}

// Security: montessori_teacher can only print their own records
if ($user['role'] === 'montessori_teacher' && $teacher) {
    if ((int)$rec['teacher_id'] !== (int)$teacher['id']) {
        header('Location: /portal/montessori/anecdotal-history.php');
        exit;
    }
}

$recordDate = date('d/m/Y', strtotime($rec['record_date']));
$printDate  = date('d M Y');
$schoolName = 'Beaconhouse Margalla Campus';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Anecdotal Record &mdash; <?= htmlspecialchars($rec['student_name']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,Helvetica,sans-serif;font-size:11pt;color:#1a1a1a;background:#f5f5f5}
.page-wrap{max-width:800px;margin:24px auto;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.12);overflow:hidden}
/* No-print bar */
.no-print{padding:14px 24px;background:#fff;border-bottom:1px solid #e5e7eb;display:flex;gap:10px;align-items:center}
.back-link{color:#1e3a5f;font-size:.88rem;text-decoration:none;display:flex;align-items:center;gap:5px}
.back-link:hover{text-decoration:underline}
.btn-act{border:none;border-radius:5px;padding:7px 16px;font-size:.84rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px}
.btn-print{background:#1e3a5f;color:#fff}
/* Header */
.rpt-header{background:linear-gradient(135deg,#1e3a5f,#2e7d9a);color:#fff;padding:22px 28px 18px}
.school-name{font-size:15pt;font-weight:700;letter-spacing:.3px;margin-bottom:2px}
.rpt-subtitle{font-size:9.5pt;opacity:.85}
.rpt-badge{display:inline-block;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);border-radius:20px;padding:3px 13px;font-size:8.5pt;margin-top:7px}
/* Meta strip */
.meta-strip{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));border-bottom:2px solid #1e3a5f}
.meta-cell{padding:10px 14px;border-right:1px solid #e5e7eb}
.meta-cell:last-child{border-right:none}
.meta-label{font-size:7.5pt;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;font-weight:600;margin-bottom:2px}
.meta-value{font-size:10.5pt;font-weight:700;color:#1e3a5f;word-break:break-word}
.meta-sub{font-size:8pt;color:#6b7280;margin-top:1px}
/* Body */
.rpt-body{padding:22px 28px}
.section-label{font-size:8pt;text-transform:uppercase;letter-spacing:.5px;font-weight:700;color:#6b7280;margin-bottom:5px}
.focus-pill{display:inline-block;background:#e0f2fe;color:#0369a1;border-radius:20px;padding:3px 13px;font-size:9.5pt;font-weight:700;margin-bottom:14px}
.topic-box{background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:10px 14px;margin-bottom:18px}
.topic-box .tl{font-size:8pt;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;font-weight:600;margin-bottom:3px}
.topic-box .tv{font-size:10.5pt;font-weight:600;color:#1e3a5f}
.obs-box{border:1px solid #e5e7eb;border-radius:6px;padding:14px 16px;background:#fafafa;min-height:120px;line-height:1.7;font-size:10.5pt;color:#1e293b;white-space:pre-wrap;word-break:break-word}
/* Signature row */
.sig-row{display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-top:30px;padding-top:16px;border-top:1px solid #e5e7eb}
.sig-box{text-align:center}
.sig-line{border-top:1px solid #9ca3af;margin:0 20px;margin-top:40px}
.sig-name{font-size:8.5pt;color:#6b7280;margin-top:4px}
/* Footer */
.rpt-footer{border-top:1px solid #e5e7eb;padding:10px 28px;display:flex;justify-content:space-between;align-items:center;font-size:8pt;color:#9ca3af;background:#f9fafb}
@media print{
  body{background:#fff}
  .no-print{display:none!important}
  .page-wrap{box-shadow:none;border-radius:0;margin:0;max-width:100%}
  @page{margin:1.5cm;size:A4}
}
</style>
</head>
<body>
<div class="page-wrap">

  <div class="no-print">
    <a href="/portal/montessori/anecdotal-history.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to History</a>
    <a href="/portal/montessori/anecdotal-records.php?class_id=<?= $rec['class_id'] ?>&student_id=<?= $rec['student_id'] ?>&edit=<?= $rec['id'] ?>"
       class="back-link" style="margin-left:12px"><i class="fas fa-edit"></i> Edit Record</a>
    <div style="flex:1"></div>
    <button class="btn-act" style="background:#0891b2;color:#fff" onclick="window.print()"><i class="fas fa-file-pdf"></i> Save as PDF</button>
    <button class="btn-act btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
  </div>

  <div class="rpt-header">
    <div class="school-name"><i class="fas fa-school" style="font-size:12pt;margin-right:7px;opacity:.85"></i><?= htmlspecialchars($schoolName) ?></div>
    <div class="rpt-subtitle">Montessori Wing &mdash; Student Anecdotal Record</div>
    <span class="rpt-badge"><i class="fas fa-calendar-day" style="font-size:8pt;margin-right:4px"></i><?= $recordDate ?></span>
  </div>

  <div class="meta-strip">
    <div class="meta-cell">
      <div class="meta-label">Student</div>
      <div class="meta-value"><?= htmlspecialchars($rec['student_name']) ?></div>
      <?php if ($rec['roll_no']): ?><div class="meta-sub">Roll No: <?= htmlspecialchars($rec['roll_no']) ?></div><?php endif; ?>
    </div>
    <div class="meta-cell">
      <div class="meta-label">Class</div>
      <div class="meta-value"><?= htmlspecialchars($rec['class_name']) ?></div>
    </div>
    <div class="meta-cell">
      <div class="meta-label">Record Date</div>
      <div class="meta-value"><?= $recordDate ?></div>
      <div class="meta-sub"><?= date('l', strtotime($rec['record_date'])) ?></div>
    </div>
    <div class="meta-cell">
      <div class="meta-label">Teacher</div>
      <div class="meta-value"><?= htmlspecialchars($rec['teacher_name']) ?></div>
    </div>
  </div>

  <div class="rpt-body">

    <div class="section-label">Subject / Focus Area</div>
    <div class="focus-pill"><?= htmlspecialchars($rec['subject_focus']) ?></div>

    <?php if ($rec['topic']): ?>
    <div class="topic-box">
      <div class="tl">Topic / Area of Focus / Context</div>
      <div class="tv"><?= htmlspecialchars($rec['topic']) ?></div>
    </div>
    <?php endif; ?>

    <div class="section-label">Observation</div>
    <div class="obs-box"><?= htmlspecialchars($rec['observation']) ?></div>

    <div class="sig-row">
      <div class="sig-box">
        <div class="sig-line"></div>
        <div class="sig-name">Teacher's Signature &amp; Date</div>
      </div>
      <div class="sig-box">
        <div class="sig-line"></div>
        <div class="sig-name">Head of Section / Reviewed By</div>
      </div>
    </div>

  </div>

  <div class="rpt-footer">
    <span><i class="fas fa-print" style="margin-right:4px"></i>Printed: <?= $printDate ?></span>
    <span style="font-style:italic">Montessori Anecdotal Record &mdash; <?= htmlspecialchars($schoolName) ?></span>
    <span>Record #<?= $rec['id'] ?></span>
  </div>

</div>
</body>
</html>

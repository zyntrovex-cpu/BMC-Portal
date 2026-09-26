<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('teacher', 'vp_main', 'wing_head', 'admin', 'student_affairs', 'student');
$db   = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/progress-report/form.php'); exit; }

$st = $db->prepare(
    'SELECT r.*, u.name AS student_name, u2.name AS reporter_name,
            s.roll_no, s.id AS sid, s.father_name, s.dob,
            c.name AS class_name
     FROM progress_reports r
     JOIN students s  ON s.id  = r.student_id
     JOIN users u     ON u.id  = s.user_id
     JOIN users u2    ON u2.id = r.reported_by
     LEFT JOIN classes c ON c.id = s.class_id
     WHERE r.id = ?'
);
$st->execute([$id]);
$rec = $st->fetch();

if (!$rec) { http_response_code(404); echo '<p style="font-family:sans-serif;padding:40px">Report not found.</p>'; exit; }

// Students can only view their own
if ($user['role'] === 'student') {
    $myRow = $db->prepare('SELECT id FROM students WHERE user_id=?');
    $myRow->execute([$user['id']]);
    $mine = $myRow->fetch();
    if (!$mine || (int)$mine['id'] !== (int)$rec['sid']) {
        http_response_code(403); echo '<p style="font-family:sans-serif;padding:40px">Access denied.</p>'; exit;
    }
}

// Teachers can only view reports for students they teach
if ($user['role'] === 'teacher') {
    $teacher = getTeacherByUserId($user['id']);
    if ($teacher) {
        $chk = $db->prepare(
            'SELECT 1 FROM class_subjects cs
             JOIN students st ON st.class_id = cs.class_id
             WHERE cs.teacher_id = ? AND st.id = ? LIMIT 1'
        );
        $chk->execute([$teacher['id'], $rec['sid']]);
        if (!$chk->fetch()) {
            http_response_code(403); echo '<p style="font-family:sans-serif;padding:40px">Access denied.</p>'; exit;
        }
    }
}

$fd      = json_decode($rec['form_data'], true) ?? [];
$fdBasic = $fd['basic']       ?? [];
$fdEng   = $fd['english']     ?? [];
$fdMath  = $fd['mathematics'] ?? [];
$fdRem   = $fd['remarks']     ?? '';

$SECTIONS = [
    'english' => [
        'title' => 'ENGLISH',
        'hdr'   => '#1e3a5f',
        'sub'   => '#dbeafe',
        'subhdr'=> '#eff6ff',
        'subhdr_txt' => '#1e3a5f',
        'sub_sections' => [
            'Communication Skills (Listening & Speaking)' => [
                'comm_listens'     => 'Listens and follows instructions',
                'comm_converses'   => 'Converses by using sufficient vocabulary',
                'comm_articulates' => 'Articulates on different topics',
            ],
            'Comprehension (Reading & Thinking Skills)' => [
                'comp_reads'       => 'Reads sentences with accuracy in pronunciation',
                'comp_comprehends' => 'Comprehends paragraphs & responds to questions',
                'comp_narrates'    => 'Narrates & retells the gist of text',
            ],
            'Language Concepts' => [
                'lang_punct'       => 'Recognizes and uses punctuation in sentences',
                'lang_pos'         => 'Familiar with use of different parts of speech',
            ],
            'Vocabulary & Writing Skills' => [
                'vocab_syllables'  => 'Recognizes and makes two-syllable words',
                'vocab_constructs' => 'Infers meanings and constructs sentences independently',
                'vocab_paragraphs' => 'Writes paragraphs and describes pictures',
            ],
        ],
        'data' => $fdEng,
    ],
    'mathematics' => [
        'title' => 'MATHEMATICS',
        'hdr'   => '#065f46',
        'sub'   => '#d1fae5',
        'subhdr'=> '#ecfdf5',
        'subhdr_txt' => '#065f46',
        'sub_sections' => [
            'Numbers and Operations' => [
                'num_place_value'  => 'Demonstrates knowledge of place value',
                'num_operations'   => 'Demonstrates understanding of basic mathematical operations',
                'num_fractions'    => 'Recognizes and names unit fractions',
            ],
            'Geometry & Measurements' => [
                'geo_time'         => 'Reads & writes time',
                'geo_measurements' => 'Measures & compares objects using length and weight',
                'geo_shapes'       => 'Names and describes 2D & 3D shapes',
            ],
        ],
        'data' => $fdMath,
    ],
];

function pdfIndicator(string $v): string {
    if ($v === '') return '<span style="color:#94a3b8">—</span>';
    $map = ['AD' => '#1e3a5f', 'ED' => '#065f46', 'EMD' => '#92400e'];
    $c = $map[$v] ?? '#374151';
    return "<strong style='color:$c'>{$v}</strong>";
}

$logoBase = defined('BASE_URL') ? BASE_URL : '';
$dobFmt   = !empty($rec['dob'])  ? date('d-m-y', strtotime($rec['dob']))  : '—';
$issueFmt = !empty($fdBasic['date_of_issue']) ? date('d-m-Y', strtotime($fdBasic['date_of_issue'])) : '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Progress Report — <?= h($rec['student_name']) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI',Arial,sans-serif; font-size:10pt; color:#111; background:#fff; }
@page { size:A4; margin:10mm 12mm; }
@media print {
  body { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
  .no-print { display:none; }
}
.hdr-wrap { display:flex; align-items:center; justify-content:space-between;
            border-bottom:2px solid #3730a3; padding-bottom:6px; margin-bottom:8px; }
.hdr-logos img { width:46px; height:46px; object-fit:contain; }
.hdr-center { flex:1; text-align:center; }
.hdr-school  { font-size:12.5pt; font-weight:800; color:#3730a3; letter-spacing:.4px; }
.hdr-section { font-size:9pt; font-weight:700; color:#374151; letter-spacing:.3px; margin-top:1px; }
.hdr-rep     { font-size:8.5pt; color:#475569; margin-top:2px; }
.stu-grid    { display:grid; grid-template-columns:1fr 1fr; gap:3px 16px;
               background:#eef2ff; padding:5px 10px; border-radius:5px; margin-bottom:8px; font-size:8.5pt; }
.stu-row     { display:flex; gap:4px; }
.stu-row span.lbl { color:#64748b; min-width:85px; }
.sec-title   { font-size:8pt; font-weight:700; text-transform:uppercase; letter-spacing:.5px;
               padding:3px 8px; border-radius:3px 3px 0 0; color:#fff; }
table { width:100%; border-collapse:collapse; margin-bottom:7px; font-size:8.5pt; }
table.pr-tbl { border:1px solid #cbd5e1; }
table.pr-tbl td, table.pr-tbl th { border:1px solid #cbd5e1; padding:3px 7px; vertical-align:middle; }
table.pr-tbl th { font-weight:600; }
.ind-cell { text-align:center; width:52px; }
.key-row  { display:flex; gap:14px; margin-bottom:8px; font-size:7.5pt; color:#374151; }
.remarks-box { border:1px solid #cbd5e1; border-top:none; padding:7px 10px; font-size:8.5pt;
               min-height:38px; background:#fafafa; }
.sig-row  { display:flex; justify-content:space-between; margin-top:16px;
            padding-top:10px; border-top:1px solid #cbd5e1; }
.sig-box  { text-align:center; min-width:100px; }
.sig-line { border-bottom:1px solid #374151; width:85px; margin:18px auto 3px; }
.sig-lbl  { font-size:7.5pt; color:#475569; }
.print-btn { position:fixed; top:14px; right:14px; background:#3730a3; color:#fff; border:none;
             padding:7px 16px; border-radius:6px; cursor:pointer; font-size:12px; z-index:999; }
</style>
</head>
<body>
<button class="print-btn no-print" onclick="window.print()">Print / Save PDF</button>

<!-- Header -->
<div class="hdr-wrap">
  <div class="hdr-logos">
    <img src="<?= $logoBase ?>/assets/bmc-logo.png" alt="" onerror="this.style.display='none'">
  </div>
  <div class="hdr-center">
    <div class="hdr-school">BAHRIA COLLEGE — PAKISTAN NAVY EDUCATIONAL TRUST</div>
    <div class="hdr-section">PRIMARY SECTION — STUDENT PROGRESS REPORT</div>
    <div class="hdr-rep">
      <?= h($rec['term']) ?><?= $rec['session'] ? ' — SESSION ' . h($rec['session']) : '' ?>
    </div>
  </div>
  <div class="hdr-logos">
    <img src="<?= $logoBase ?>/assets/bmc-logo.png" alt="" onerror="this.style.display='none'">
  </div>
</div>

<!-- Student Info Grid -->
<div class="stu-grid">
  <div class="stu-row">
    <span class="lbl">Name:</span>
    <strong><?= h($rec['student_name']) ?></strong>
  </div>
  <div class="stu-row">
    <span class="lbl">Father's Name:</span>
    <strong><?= h($rec['father_name'] ?: '—') ?></strong>
  </div>
  <div class="stu-row">
    <span class="lbl">Class &amp; Section:</span>
    <strong><?= h($rec['class_name'] ?? '—') ?></strong>
  </div>
  <div class="stu-row">
    <span class="lbl">Attendance:</span>
    <strong><?= h($fdBasic['attendance'] ?: '—') ?></strong>
  </div>
  <div class="stu-row">
    <span class="lbl">GR No:</span>
    <strong><?= h($rec['roll_no'] ?: '—') ?></strong>
  </div>
  <div class="stu-row">
    <span class="lbl">Date of Birth:</span>
    <strong><?= h($dobFmt) ?></strong>
  </div>
  <div class="stu-row">
    <span class="lbl">Term:</span>
    <strong><?= h($rec['term']) ?></strong>
  </div>
  <div class="stu-row">
    <span class="lbl">Date of Issue:</span>
    <strong><?= h($issueFmt) ?></strong>
  </div>
</div>

<!-- Grade Key -->
<div class="key-row">
  <strong>KEY:</strong>
  <span><strong style="color:#1e3a5f">AD</strong> — Advanced Development (Ahead of expected level &amp; often independently)</span>
  <span><strong style="color:#065f46">ED</strong> — Expected Development (Consistently meets the expected level)</span>
  <span><strong style="color:#92400e">EMD</strong> — Emerging Development (Struggles to meet the expected level)</span>
</div>

<?php foreach ($SECTIONS as $sk => $sec): ?>
<div class="sec-title" style="background:<?= $sec['hdr'] ?>"><?= h($sec['title']) ?></div>
<table class="pr-tbl">
  <thead><tr style="background:<?= $sec['sub'] ?>">
    <th colspan="2">Learning Area</th>
    <th class="ind-cell">Performance Indicator</th>
  </tr></thead>
  <tbody>
  <?php foreach ($sec['sub_sections'] as $subTitle => $fields): ?>
  <tr style="background:<?= $sec['subhdr'] ?>">
    <td colspan="3" style="font-weight:600;font-size:8pt;color:<?= $sec['subhdr_txt'] ?>;padding:2px 8px">
      <?= h($subTitle) ?>
    </td>
  </tr>
  <?php foreach ($fields as $fk => $label): ?>
  <tr>
    <td colspan="2"><?= h($label) ?></td>
    <td class="ind-cell"><?= pdfIndicator($sec['data'][$fk] ?? '') ?></td>
  </tr>
  <?php endforeach; ?>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endforeach; ?>

<!-- Remarks -->
<div class="sec-title" style="background:#374151">TEACHER'S REMARKS</div>
<div class="remarks-box"><?= nl2br(h($fdRem)) ?: '<span style="color:#94a3b8">—</span>' ?></div>

<!-- Signatures -->
<div class="sig-row">
  <div class="sig-box"><div class="sig-line"></div><div class="sig-lbl">Class Teacher's Signature</div></div>
  <div class="sig-box"><div class="sig-line"></div><div class="sig-lbl">Wing Head's Signature</div></div>
  <div class="sig-box"><div class="sig-line"></div><div class="sig-lbl">Parent's Signature</div></div>
  <div class="sig-box"><div class="sig-line"></div><div class="sig-lbl">Principal's Signature</div></div>
</div>

<script>window.addEventListener('load', function(){ window.print(); });</script>
</body></html>

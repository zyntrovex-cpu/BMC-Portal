<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('teacher', 'vp_main', 'wing_head');
$db   = getDB();
$role = $user['role'];

$teacher = null;
if ($role === 'teacher') {
    $teacher = getTeacherByUserId($user['id']);
    if (!$teacher) { setFlash('danger', 'Teacher record not found.'); redirect('/portal/index.php'); }
}

$tableExists = false;
try { $db->query('SELECT 1 FROM progress_reports LIMIT 0'); $tableExists = true; } catch (Exception $e) {}

$TERMS = ['First Term', 'Mid Term', 'Final Term', 'Annual Exam'];

// Convert old-format {english:{...}, mathematics:{...}} to new {subjects:[...]} format
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

function prCountFilled(array $rfd): array {
    if (isset($rfd['subjects'])) {
        $total = $filled = 0;
        foreach ($rfd['subjects'] as $s)
            foreach ($s['areas'] ?? [] as $a)
                foreach ($a['indicators'] ?? [] as $i) {
                    $total++;
                    if (!empty($i['value'])) $filled++;
                }
        return [$filled, $total];
    }
    $filled = count(array_filter($rfd['english']??[])) + count(array_filter($rfd['mathematics']??[]));
    return [$filled, 17];
}

// Student list
$students = [];
try {
    if ($role === 'teacher') {
        $st = $db->prepare(
            'SELECT DISTINCT st.id, u.name AS student_name, st.roll_no, c.name AS class_name
             FROM class_subjects cs JOIN classes c ON cs.class_id=c.id
             JOIN students st ON st.class_id=cs.class_id JOIN users u ON st.user_id=u.id
             WHERE cs.teacher_id=? AND c.is_ilc=0 ORDER BY c.name, st.roll_no'
        );
        $st->execute([$teacher['id']]);
    } else {
        $st = $db->prepare(
            'SELECT st.id, u.name AS student_name, st.roll_no, c.name AS class_name
             FROM students st JOIN users u ON st.user_id=u.id JOIN classes c ON c.id=st.class_id
             WHERE c.is_ilc=0 ORDER BY c.name, st.roll_no'
        );
        $st->execute([]);
    }
    $students = $st->fetchAll();
} catch (Exception $e) {}

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action    = $_POST['action']     ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);

    if (in_array($action, ['create','update']) && $studentId) {
        $allowed = array_column($students, 'id');
        if (!in_array($studentId, $allowed)) {
            setFlash('danger', 'You are not authorized to create reports for that student.');
            redirect('/portal/progress-report/form.php');
        }
        $basic = [
            'term'          => trim($_POST['basic_term']          ?? 'Final Term'),
            'session'       => trim($_POST['basic_session']       ?? ''),
            'attendance'    => trim($_POST['basic_attendance']    ?? ''),
            'date_of_issue' => trim($_POST['basic_date_of_issue'] ?? ''),
        ];
        $subjects = json_decode($_POST['form_json'] ?? '[]', true);
        if (!is_array($subjects)) $subjects = [];
        $remarks = trim($_POST['remarks'] ?? '');
        $fd   = ['basic'=>$basic,'subjects'=>$subjects,'remarks'=>$remarks];
        $json = json_encode($fd, JSON_UNESCAPED_UNICODE);
        $term = $basic['term']; $session = $basic['session'];

        if ($action === 'update') {
            $id = (int)($_POST['result_id'] ?? 0);
            $db->prepare('UPDATE progress_reports SET term=?,session=?,form_data=?,reported_by=? WHERE id=?')
               ->execute([$term,$session,$json,$user['id'],$id]);
            logActivity($user['id'],'pr_update',"Updated Progress Report #$id");
            setFlash('success','Progress Report updated.');
        } else {
            $db->prepare('INSERT INTO progress_reports (student_id,term,session,form_data,reported_by) VALUES (?,?,?,?,?)')
               ->execute([$studentId,$term,$session,$json,$user['id']]);
            logActivity($user['id'],'pr_create',"Created Progress Report for student #$studentId");
            setFlash('success','Progress Report saved.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['result_id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM progress_reports WHERE id=?')->execute([$id]);
            logActivity($user['id'],'pr_delete',"Deleted Progress Report #$id");
            setFlash('success','Progress Report deleted.');
        }
    }
    redirect('/portal/progress-report/form.php'.($studentId?"?student_id=$studentId":''));
}

// Fetch existing reports
$studentId = (int)($_GET['student_id'] ?? 0);
$editId    = (int)($_GET['edit']       ?? 0);
$results   = []; $editResult = null;
if ($studentId && $tableExists) {
    $st = $db->prepare('SELECT r.*,u.name AS reporter_name FROM progress_reports r JOIN users u ON u.id=r.reported_by WHERE r.student_id=? ORDER BY r.created_at DESC');
    $st->execute([$studentId]);
    $results = $st->fetchAll();
    if ($editId) foreach ($results as $r) { if ($r['id'] == $editId) { $editResult = $r; break; } }
}

$curStudent = null;
foreach ($students as $s) { if ((int)$s['id']===$studentId) { $curStudent=$s; break; } }

$fd = []; $initialSubjectsJson = 'null';
if ($editResult && !empty($editResult['form_data'])) {
    $fd = json_decode($editResult['form_data'], true) ?? [];
    $fd = prMigrateFormData($fd);
    $initialSubjectsJson = json_encode($fd['subjects'] ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
}
$fdBasic = $fd['basic'] ?? ['term'=>'Final Term','session'=>'','attendance'=>'','date_of_issue'=>''];
$fdRem   = $fd['remarks'] ?? '';

$links = match($role) {
    'teacher'  => getTeacherLinks(),
    'vp_main'  => getVpLinks(),
    'wing_head'=> getWingHeadLinks(),
    default    => getTeacherLinks(),
};

pageHead('Progress Report', $role);
?>
<div class="portal-wrap">
<?php sidebar($role, 'progress-report', $links, $user); ?>
<div class="main-area">
<?php topbar('Generate Progress Report', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="d-flex align-items-center gap-3 mb-3 p-3"
     style="background:linear-gradient(90deg,#eef2ff,#e0e7ff);border-radius:10px;border:1px solid #a5b4fc">
  <img src="<?= url('/assets/bmc-logo.png') ?>" alt="BMC"
       style="width:40px;height:40px;object-fit:contain;flex-shrink:0" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#3730a3;letter-spacing:.8px;text-transform:uppercase">Student Progress Report</div>
    <div style="font-size:.78rem;color:#475569">Bahria College — Pakistan Navy Educational Trust</div>
  </div>
</div>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-2"></i>
  <strong>Migration not applied.</strong> Run <code>database/migrations/progress_reports.sql</code> first.
</div>
<?php else: ?>

<div class="row g-3">
  <div class="col-xl-6">
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-user-graduate me-2"></i>Select Student</div>
      <div style="padding:12px 16px">
        <?php if (empty($students)): ?>
        <div class="text-muted" style="font-size:.84rem">
          <?= $role==='teacher' ? 'No students found. Ensure you are assigned to subjects in at least one class.' : 'No students found.' ?>
        </div>
        <?php else: ?>
        <form method="GET" class="d-flex gap-2">
          <select name="student_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">— Select student —</option>
            <?php foreach ($students as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $studentId===(int)$s['id']?'selected':'' ?>>
              <?= h($s['student_name']) ?> (<?= h($s['roll_no']) ?>) — <?= h($s['class_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($studentId && $curStudent): ?>
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center"
           data-bs-toggle="collapse" data-bs-target="#prFormBody"
           style="cursor:pointer;background:linear-gradient(90deg,#3730a3,#4338ca);color:#fff">
        <span>
          <i class="fas fa-<?= $editResult?'edit':'plus' ?> me-2"></i>
          <?= $editResult ? 'Edit — '.h($editResult['term']).' '.h($editResult['session']) : 'New Progress Report — '.h($curStudent['student_name']) ?>
        </span>
        <i class="fas fa-chevron-down"></i>
      </div>
      <div id="prFormBody" class="collapse show">
        <div style="padding:16px 18px 20px">
          <div class="text-center mb-3 pb-2" style="border-bottom:2px solid #4338ca">
            <div style="font-size:.64rem;font-weight:700;letter-spacing:1px;color:#3730a3;text-transform:uppercase">
              Bahria College — Pakistan Navy Educational Trust
            </div>
            <div style="font-size:.9rem;font-weight:700;color:#0f172a;margin:.2rem 0 .1rem">Student Progress Report</div>
            <div style="font-size:.72rem;color:#64748b">PRIMARY SECTION</div>
          </div>

          <form method="POST" id="prForm">
            <input type="hidden" name="action"     value="<?= $editResult?'update':'create' ?>">
            <input type="hidden" name="student_id" value="<?= $studentId ?>">
            <input type="hidden" id="form_json"    name="form_json" value="">
            <?php if ($editResult): ?>
            <input type="hidden" name="result_id"  value="<?= $editResult['id'] ?>">
            <?php endif; ?>

            <!-- Basic info -->
            <div class="fba-sec mb-2">
              <div class="fba-sh" style="background:#3730a3">1. Student Information</div>
              <div class="fba-sb">
                <div class="row g-2">
                  <div class="col-md-6">
                    <label class="fba-lbl">Student Name</label>
                    <input type="text" class="form-control form-control-sm bg-light" value="<?= h($curStudent['student_name']) ?>" readonly>
                  </div>
                  <div class="col-md-3">
                    <label class="fba-lbl">Class &amp; Section</label>
                    <input type="text" class="form-control form-control-sm bg-light" value="<?= h($curStudent['class_name']) ?>" readonly>
                  </div>
                  <div class="col-md-3">
                    <label class="fba-lbl">GR / Roll No</label>
                    <input type="text" class="form-control form-control-sm bg-light" value="<?= h($curStudent['roll_no']) ?>" readonly>
                  </div>
                  <div class="col-md-4">
                    <label class="fba-lbl">Term <span class="text-danger">*</span></label>
                    <select name="basic_term" class="form-select form-select-sm" required>
                      <?php foreach ($TERMS as $t): ?>
                      <option value="<?= $t ?>" <?= ($fdBasic['term']===$t)?'selected':'' ?>><?= $t ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="fba-lbl">Session</label>
                    <input type="text" name="basic_session" class="form-control form-control-sm"
                           placeholder="e.g. 2025-2026" value="<?= h($fdBasic['session']) ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="fba-lbl">Attendance</label>
                    <input type="text" name="basic_attendance" class="form-control form-control-sm"
                           placeholder="e.g. 155 / 165" value="<?= h($fdBasic['attendance']) ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="fba-lbl">Date of Issue</label>
                    <input type="date" name="basic_date_of_issue" class="form-control form-control-sm"
                           value="<?= h($fdBasic['date_of_issue']) ?>">
                  </div>
                </div>
              </div>
            </div>

            <!-- Performance key info -->
            <div class="mb-2 p-2 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;font-size:.77rem">
              <span class="fw-bold me-1">KEY:</span>
              <span class="badge me-1" style="background:#1e3a5f;font-size:.7rem">AD</span> Advanced Development &nbsp;|&nbsp;
              <span class="badge me-1" style="background:#065f46;font-size:.7rem">ED</span> Expected Development &nbsp;|&nbsp;
              <span class="badge me-1" style="background:#92400e;font-size:.7rem">EMD</span> Emerging Development
            </div>

            <!-- Dynamic subjects -->
            <div id="pr-subjects-wrap"></div>

            <div class="d-flex justify-content-end mb-2">
              <button type="button" id="addSubjectBtn" class="btn btn-sm"
                      style="background:#6366f1;color:#fff;font-size:.76rem">
                <i class="fas fa-plus-circle me-1"></i>Add Subject
              </button>
            </div>

            <!-- Remarks -->
            <div class="fba-sec mb-3">
              <div class="fba-sh" style="background:#374151">Teacher's Remarks</div>
              <div class="fba-sb">
                <textarea name="remarks" class="form-control form-control-sm" rows="3"
                          placeholder="Overall remarks, strengths, areas for improvement…"><?= h($fdRem) ?></textarea>
              </div>
            </div>

            <div class="d-flex gap-2 pt-1">
              <button type="submit" class="btn btn-sm" style="background:#3730a3;color:#fff">
                <i class="fas fa-save me-1"></i><?= $editResult?'Update Report':'Save Report' ?>
              </button>
              <?php if ($editResult): ?>
              <a href="?student_id=<?= $studentId ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php elseif ($studentId && !$curStudent): ?>
    <div class="alert alert-danger">Student not found or not within your authorized scope.</div>
    <?php endif; ?>
  </div>

  <!-- Right: existing reports -->
  <div class="col-xl-6">
    <?php if ($studentId && $curStudent): ?>
    <div class="sec-card">
      <div class="sec-card-header">
        <i class="fas fa-file-alt me-2"></i>Progress Reports — <?= h($curStudent['student_name']) ?>
        <span class="badge bg-secondary ms-2"><?= count($results) ?></span>
      </div>
      <?php if (empty($results)): ?>
      <div style="padding:40px;text-align:center;color:var(--t2);font-size:.85rem">
        No reports yet. Use the form to add the first Progress Report.
      </div>
      <?php else: ?>
      <div style="padding:12px 16px">
        <?php foreach ($results as $r):
          $rfd = json_decode($r['form_data'], true) ?? [];
          $rBasic = $rfd['basic'] ?? [];
          [$filled, $total] = prCountFilled($rfd);
          $subjCount = isset($rfd['subjects']) ? count($rfd['subjects']) : 2;
        ?>
        <div class="mb-3 p-3" style="background:#f7f9fb;border-radius:8px;border:1px solid var(--border)">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <div>
              <span class="badge" style="background:#3730a3;font-size:.75rem">
                <?= h($r['term']) ?><?= $r['session']?' · '.h($r['session']):'' ?>
              </span>
              <span style="font-size:.74rem;color:var(--t2);margin-left:8px">
                <?= fDate($r['created_at']) ?> · <?= h($r['reporter_name']) ?>
              </span>
            </div>
            <div class="d-flex gap-1">
              <a href="<?= url('/portal/progress-report/pdf.php?id='.$r['id']) ?>"
                 target="_blank" class="btn btn-xs btn-outline-primary">
                <i class="fas fa-file-pdf me-1"></i>PDF
              </a>
              <a href="?student_id=<?= $studentId ?>&edit=<?= $r['id'] ?>"
                 class="btn btn-xs btn-outline-secondary"><i class="fas fa-edit"></i></a>
              <form method="POST" class="d-inline" onsubmit="return confirm('Delete this report?')">
                <input type="hidden" name="action"     value="delete">
                <input type="hidden" name="student_id" value="<?= $studentId ?>">
                <input type="hidden" name="result_id"  value="<?= $r['id'] ?>">
                <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </div>
          <div style="font-size:.76rem;color:var(--t2)">
            <?php if (!empty($rBasic['attendance'])): ?>
            <i class="fas fa-calendar-check me-1"></i>Attendance: <?= h($rBasic['attendance']) ?> &nbsp;
            <?php endif; ?>
            <i class="fas fa-check-circle me-1" style="color:#3730a3"></i>
            <?= $filled ?>/<?= $total ?> indicators &nbsp;·&nbsp; <?= $subjCount ?> subject<?= $subjCount!==1?'s':'' ?>
          </div>
          <?php if (!empty($rfd['remarks'])): ?>
          <div style="font-size:.74rem;margin-top:4px;color:var(--t2)">
            <i class="fas fa-comment-alt me-1"></i><?= h(mb_strimwidth($rfd['remarks'],0,120,'…')) ?>
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
        <i class="fas fa-file-alt fa-2x mb-3 d-block" style="opacity:.15"></i>
        Select a student to view or create Progress Reports.
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
</div></div></div>

<style>
.fba-sec{border:1px solid #e2e8f0;border-radius:7px;overflow:hidden}
.fba-sh{font-size:.78rem;font-weight:700;color:#fff;padding:5px 12px;letter-spacing:.4px;text-transform:uppercase}
.fba-sb{padding:10px 12px}
.fba-lbl{font-size:.78rem;font-weight:600;margin-bottom:2px;display:block}
.pr-sel{width:78px;font-size:.78rem;padding:2px 4px;border-radius:4px;border:1px solid #d1d5db;font-weight:700}
.btn-xs{padding:2px 8px;font-size:.75rem}
.pr-subj{border:1px solid #e2e8f0;border-radius:7px;overflow:hidden}
.pr-subj-hdr{padding:5px 10px;letter-spacing:.4px}
.pr-subj-title-inp::placeholder{color:rgba(255,255,255,.5)!important}
.pr-area-title-inp{width:100%}
</style>

<script>
const SUBJECT_COLORS = ['#1e3a5f','#065f46','#5b21b6','#b45309','#be185d','#0369a1','#7f1d1d','#0e7490'];

const DEFAULT_SUBJECTS = [
  {title:'ENGLISH',color:'#1e3a5f',areas:[
    {title:'Communication Skills (Listening & Speaking)',indicators:[
      {label:'Listens and follows instructions',value:''},
      {label:'Converses by using sufficient vocabulary',value:''},
      {label:'Articulates on different topics',value:''}
    ]},
    {title:'Comprehension (Reading & Thinking Skills)',indicators:[
      {label:'Reads sentences with accuracy in pronunciation',value:''},
      {label:'Comprehends paragraphs & responds to questions',value:''},
      {label:'Narrates & retells the gist of text',value:''}
    ]},
    {title:'Language Concepts',indicators:[
      {label:'Recognizes and uses punctuation in sentences',value:''},
      {label:'Familiar with use of different parts of speech',value:''}
    ]},
    {title:'Vocabulary & Writing Skills',indicators:[
      {label:'Recognizes and makes two-syllable words',value:''},
      {label:'Infers meanings and constructs sentences independently',value:''},
      {label:'Writes paragraphs and describes pictures',value:''}
    ]}
  ]},
  {title:'MATHEMATICS',color:'#065f46',areas:[
    {title:'Numbers and Operations',indicators:[
      {label:'Demonstrates knowledge of place value',value:''},
      {label:'Demonstrates understanding of basic mathematical operations',value:''},
      {label:'Recognizes and names unit fractions',value:''}
    ]},
    {title:'Geometry & Measurements',indicators:[
      {label:'Reads & writes time',value:''},
      {label:'Measures & compares objects using length and weight',value:''},
      {label:'Names and describes 2D & 3D shapes',value:''}
    ]}
  ]}
];

let prSubjects = <?= $initialSubjectsJson ?>;
if (!prSubjects) prSubjects = JSON.parse(JSON.stringify(DEFAULT_SUBJECTS));

function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function h2r(hex, a) {
  hex = (hex||'#000000').replace('#','');
  if (hex.length===3) hex=hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
  const r=parseInt(hex.slice(0,2),16), g=parseInt(hex.slice(2,4),16), b=parseInt(hex.slice(4,6),16);
  return `rgba(${r},${g},${b},${a})`;
}

function selOpts(val) {
  return ['','AD','ED','EMD'].map(v=>`<option value="${v}"${val===v?' selected':''}>${v||'—'}</option>`).join('');
}

function renderSubjects() {
  let html = '';
  prSubjects.forEach((subj, si) => {
    const c = subj.color || '#374151';
    const theadBg = h2r(c, 0.15);
    const areaBg  = h2r(c, 0.08);
    const delSubjBtn = prSubjects.length > 1
      ? `<button type="button" data-si="${si}" class="btn pr-del-subj-btn"
           style="background:rgba(220,38,38,.55);color:#fff;font-size:.7rem;padding:2px 7px;border:none" title="Remove subject">
           <i class="fas fa-times"></i></button>`
      : '';
    html += `<div class="pr-subj mb-2" data-si-root="${si}">
  <div class="fba-sh d-flex justify-content-between align-items-center pr-subj-hdr" style="background:${c}">
    <div class="d-flex align-items-center gap-2 flex-grow-1">
      <input type="color" data-si="${si}" data-field="subj-color" value="${esc(c)}"
        class="pr-color-pick" title="Change colour"
        style="width:22px;height:22px;padding:1px;border:2px solid rgba(255,255,255,.45);border-radius:3px;cursor:pointer;background:none;flex-shrink:0">
      <input type="text" data-si="${si}" data-field="subj-title" value="${esc(subj.title)}"
        class="pr-subj-title-inp" placeholder="Subject Title"
        style="background:transparent;color:#fff;border:1px solid rgba(255,255,255,.3);font-size:.78rem;
               font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:2px 8px;border-radius:3px;width:170px">
    </div>
    <div class="d-flex gap-1 flex-shrink-0">
      <button type="button" data-si="${si}" class="btn pr-add-area-btn"
        style="background:rgba(255,255,255,.18);color:#fff;font-size:.7rem;padding:2px 8px;border:1px solid rgba(255,255,255,.3)">
        <i class="fas fa-plus me-1"></i>Add Area
      </button>
      ${delSubjBtn}
    </div>
  </div>
  <div>
    <table class="table table-sm table-bordered mb-0" style="font-size:.8rem;border-top:none">
      <thead><tr class="pr-subj-thead" style="background:${theadBg}">
        <th colspan="2">Learning Area / Performance Indicator</th>
        <th class="text-center" style="width:82px">Performance</th>
        <th style="width:56px"></th>
      </tr></thead>
      <tbody>`;
    subj.areas.forEach((area, ai) => {
      const delAreaBtn = subj.areas.length > 1
        ? `<button type="button" data-si="${si}" data-ai="${ai}" class="btn pr-del-area-btn"
             style="background:#ef444420;color:#dc2626;font-size:.68rem;padding:1px 5px;border:1px solid #ef444440" title="Remove area">
             <i class="fas fa-times"></i></button>`
        : '';
      html += `<tr class="pr-area-hdr" style="background:${areaBg}">
        <td colspan="2" style="padding:2px 8px">
          <input type="text" data-si="${si}" data-ai="${ai}" data-field="area-title"
            value="${esc(area.title)}" class="pr-area-title-inp form-control form-control-sm" placeholder="Learning Area"
            style="background:transparent;color:${c};font-size:.76rem;font-weight:600;
                   border:1px solid ${h2r(c,.25)};padding:2px 6px">
        </td>
        <td></td>
        <td style="padding:2px 4px;text-align:center;vertical-align:middle">
          <div class="d-flex gap-1 justify-content-center">
            <button type="button" data-si="${si}" data-ai="${ai}" class="btn pr-add-ind-btn"
              style="background:#3b82f620;color:#1d4ed8;font-size:.68rem;padding:1px 6px;border:1px solid #3b82f640" title="Add indicator">
              <i class="fas fa-plus"></i>
            </button>
            ${delAreaBtn}
          </div>
        </td>
      </tr>`;
      area.indicators.forEach((ind, ii) => {
        const delIndBtn = area.indicators.length > 1
          ? `<button type="button" data-si="${si}" data-ai="${ai}" data-ii="${ii}" class="btn pr-del-ind-btn"
               style="background:#ef444420;color:#dc2626;font-size:.68rem;padding:1px 5px;border:1px solid #ef444440" title="Remove">
               <i class="fas fa-times"></i></button>`
          : `<span style="color:#cbd5e1;font-size:.68rem">—</span>`;
        html += `<tr>
          <td colspan="2" style="padding:2px 8px">
            <input type="text" data-si="${si}" data-ai="${ai}" data-ii="${ii}" data-field="ind-label"
              value="${esc(ind.label)}" class="form-control form-control-sm pr-ind-inp"
              placeholder="Performance indicator description"
              style="font-size:.78rem;border:1px solid #e2e8f0">
          </td>
          <td class="text-center" style="padding:2px 4px;vertical-align:middle">
            <select data-si="${si}" data-ai="${ai}" data-ii="${ii}" data-field="ind-value" class="pr-sel">
              ${selOpts(ind.value)}
            </select>
          </td>
          <td style="text-align:center;padding:2px 4px;vertical-align:middle">${delIndBtn}</td>
        </tr>`;
      });
    });
    html += `      </tbody></table></div></div>`;
  });
  document.getElementById('pr-subjects-wrap').innerHTML = html;
}

// Event delegation — input changes
document.getElementById('pr-subjects-wrap').addEventListener('input', function(e) {
  const el = e.target;
  const field = el.dataset.field;
  if (!field) return;
  const si = el.dataset.si !== undefined ? parseInt(el.dataset.si) : NaN;
  const ai = el.dataset.ai !== undefined ? parseInt(el.dataset.ai) : NaN;
  const ii = el.dataset.ii !== undefined ? parseInt(el.dataset.ii) : NaN;
  if (field === 'subj-title' && !isNaN(si)) {
    prSubjects[si].title = el.value;
  } else if (field === 'subj-color' && !isNaN(si)) {
    prSubjects[si].color = el.value;
    // Live-update colors without full re-render
    const root = document.querySelector(`[data-si-root="${si}"]`);
    if (root) {
      root.querySelector('.pr-subj-hdr').style.background = el.value;
      const thead = root.querySelector('.pr-subj-thead');
      if (thead) thead.style.background = h2r(el.value, 0.15);
      root.querySelectorAll('.pr-area-hdr').forEach(r => r.style.background = h2r(el.value, 0.08));
      root.querySelectorAll('.pr-area-title-inp').forEach(inp => {
        inp.style.color = el.value;
        inp.style.borderColor = h2r(el.value, 0.25);
      });
    }
  } else if (field === 'area-title' && !isNaN(si) && !isNaN(ai)) {
    prSubjects[si].areas[ai].title = el.value;
  } else if (field === 'ind-label' && !isNaN(si) && !isNaN(ai) && !isNaN(ii)) {
    prSubjects[si].areas[ai].indicators[ii].label = el.value;
  } else if (field === 'ind-value' && !isNaN(si) && !isNaN(ai) && !isNaN(ii)) {
    prSubjects[si].areas[ai].indicators[ii].value = el.value;
  }
});

// Event delegation — button clicks (structural changes)
document.getElementById('pr-subjects-wrap').addEventListener('click', function(e) {
  const btn = e.target.closest('button');
  if (!btn) return;
  const si = btn.dataset.si !== undefined ? parseInt(btn.dataset.si) : NaN;
  const ai = btn.dataset.ai !== undefined ? parseInt(btn.dataset.ai) : NaN;
  const ii = btn.dataset.ii !== undefined ? parseInt(btn.dataset.ii) : NaN;

  if (btn.classList.contains('pr-del-subj-btn')) {
    if (prSubjects.length <= 1) { alert('At least one subject section is required.'); return; }
    if (!confirm('Remove this subject section?')) return;
    prSubjects.splice(si, 1); renderSubjects();
  } else if (btn.classList.contains('pr-add-area-btn')) {
    prSubjects[si].areas.push({title:'New Learning Area',indicators:[{label:'',value:''}]});
    renderSubjects();
  } else if (btn.classList.contains('pr-del-area-btn')) {
    if (prSubjects[si].areas.length <= 1) { alert('At least one learning area is required.'); return; }
    if (!confirm('Remove this learning area?')) return;
    prSubjects[si].areas.splice(ai, 1); renderSubjects();
  } else if (btn.classList.contains('pr-add-ind-btn')) {
    prSubjects[si].areas[ai].indicators.push({label:'',value:''});
    renderSubjects();
  } else if (btn.classList.contains('pr-del-ind-btn')) {
    if (prSubjects[si].areas[ai].indicators.length <= 1) { alert('At least one indicator is required.'); return; }
    prSubjects[si].areas[ai].indicators.splice(ii, 1); renderSubjects();
  }
});

// Add Subject button
document.getElementById('addSubjectBtn').addEventListener('click', function() {
  const usedColors = prSubjects.map(s => s.color);
  const nextColor = SUBJECT_COLORS.find(c => !usedColors.includes(c)) || SUBJECT_COLORS[prSubjects.length % SUBJECT_COLORS.length];
  prSubjects.push({title:'NEW SUBJECT',color:nextColor,areas:[{title:'Learning Area',indicators:[{label:'',value:''}]}]});
  renderSubjects();
});

// Form submit — serialize subjects to hidden input
document.getElementById('prForm').addEventListener('submit', function(e) {
  if (prSubjects.length === 0) { e.preventDefault(); alert('Add at least one subject section.'); return; }
  document.getElementById('form_json').value = JSON.stringify(prSubjects);
});

// Initialize
renderSubjects();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

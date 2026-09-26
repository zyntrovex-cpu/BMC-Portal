<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

// Allow teacher, vp_main, and wing_head
$user = requireAuth('teacher', 'vp_main', 'wing_head');
$db   = getDB();
$role = $user['role'];

// Role-specific setup
$teacher = null;
if ($role === 'teacher') {
    $teacher = getTeacherByUserId($user['id']);
    if (!$teacher) { setFlash('danger', 'Teacher record not found.'); redirect('/portal/index.php'); }
}

$tableExists = false;
try { $db->query('SELECT 1 FROM progress_reports LIMIT 0'); $tableExists = true; } catch (Exception $e) {}

// ── Report sections definition ────────────────────────────────────────────────
$INDICATORS = ['AD', 'ED', 'EMD'];

$SECTIONS = [
    'english' => [
        'title' => 'ENGLISH',
        'color' => '#1e3a5f',
        'sub'   => [
            'Communication Skills (Listening &amp; Speaking)' => [
                'comm_listens'      => 'Listens and follows instructions',
                'comm_converses'    => 'Converses by using sufficient vocabulary',
                'comm_articulates'  => 'Articulates on different topics',
            ],
            'Comprehension (Reading &amp; Thinking Skills)' => [
                'comp_reads'        => 'Reads sentences with accuracy in pronunciation',
                'comp_comprehends'  => 'Comprehends paragraphs &amp; responds to questions',
                'comp_narrates'     => 'Narrates &amp; retells the gist of text',
            ],
            'Language Concepts' => [
                'lang_punct'        => 'Recognizes and uses punctuation in sentences',
                'lang_pos'          => 'Familiar with use of different parts of speech',
            ],
            'Vocabulary &amp; Writing Skills' => [
                'vocab_syllables'   => 'Recognizes and makes two-syllable words',
                'vocab_constructs'  => 'Infers meanings and constructs sentences independently',
                'vocab_paragraphs'  => 'Writes paragraphs and describes pictures',
            ],
        ],
    ],
    'mathematics' => [
        'title' => 'MATHEMATICS',
        'color' => '#065f46',
        'sub'   => [
            'Numbers and Operations' => [
                'num_place_value'   => 'Demonstrates knowledge of place value',
                'num_operations'    => 'Demonstrates understanding of basic mathematical operations',
                'num_fractions'     => 'Recognizes and names unit fractions',
            ],
            'Geometry &amp; Measurements' => [
                'geo_time'          => 'Reads &amp; writes time',
                'geo_measurements'  => 'Measures &amp; compares objects using length and weight',
                'geo_shapes'        => 'Names and describes 2D &amp; 3D shapes',
            ],
        ],
    ],
];

$TERMS = ['First Term', 'Mid Term', 'Final Term', 'Annual Exam'];

// ── Student list (filtered by role) ──────────────────────────────────────────
$students = [];
try {
    if ($role === 'teacher') {
        $st = $db->prepare(
            'SELECT DISTINCT st.id, u.name AS student_name, st.roll_no, c.name AS class_name
             FROM class_subjects cs
             JOIN classes c  ON cs.class_id  = c.id
             JOIN students st ON st.class_id = cs.class_id
             JOIN users u    ON st.user_id   = u.id
             WHERE cs.teacher_id = ? AND c.is_ilc = 0
             ORDER BY c.name, st.roll_no'
        );
        $st->execute([$teacher['id']]);
    } else {
        // vp_main and wing_head see all non-ILC students
        $st = $db->prepare(
            'SELECT st.id, u.name AS student_name, st.roll_no, c.name AS class_name
             FROM students st
             JOIN users u  ON st.user_id  = u.id
             JOIN classes c ON c.id = st.class_id
             WHERE c.is_ilc = 0
             ORDER BY c.name, st.roll_no'
        );
        $st->execute([]);
    }
    $students = $st->fetchAll();
} catch (Exception $e) {}

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action    = $_POST['action']     ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);

    if (in_array($action, ['create', 'update']) && $studentId) {
        // Verify student is in the allowed list
        $allowed = array_column($students, 'id');
        if (!in_array($studentId, $allowed)) {
            setFlash('danger', 'You are not authorized to create reports for that student.');
            redirect('/portal/progress-report/form.php');
        }

        $basic = [
            'term'          => trim($_POST['basic_term']          ?? 'Final'),
            'session'       => trim($_POST['basic_session']       ?? ''),
            'attendance'    => trim($_POST['basic_attendance']    ?? ''),
            'date_of_issue' => trim($_POST['basic_date_of_issue'] ?? ''),
        ];

        $engData = [];
        $mathData = [];
        foreach ($SECTIONS['english']['sub'] as $fields) {
            foreach (array_keys($fields) as $fk) {
                $engData[$fk] = trim($_POST["eng_{$fk}"] ?? '');
            }
        }
        foreach ($SECTIONS['mathematics']['sub'] as $fields) {
            foreach (array_keys($fields) as $fk) {
                $mathData[$fk] = trim($_POST["math_{$fk}"] ?? '');
            }
        }

        $remarks = trim($_POST['remarks'] ?? '');
        $fd      = ['basic' => $basic, 'english' => $engData, 'mathematics' => $mathData, 'remarks' => $remarks];
        $json    = json_encode($fd, JSON_UNESCAPED_UNICODE);
        $term    = $basic['term'];
        $session = $basic['session'];

        if ($action === 'update') {
            $id = (int)($_POST['result_id'] ?? 0);
            $db->prepare('UPDATE progress_reports SET term=?,session=?,form_data=?,reported_by=? WHERE id=?')
               ->execute([$term, $session, $json, $user['id'], $id]);
            logActivity($user['id'], 'pr_update', "Updated Progress Report #$id");
            setFlash('success', 'Progress Report updated.');
        } else {
            $db->prepare('INSERT INTO progress_reports (student_id,term,session,form_data,reported_by) VALUES (?,?,?,?,?)')
               ->execute([$studentId, $term, $session, $json, $user['id']]);
            logActivity($user['id'], 'pr_create', "Created Progress Report for student #$studentId");
            setFlash('success', 'Progress Report saved.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['result_id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM progress_reports WHERE id=?')->execute([$id]);
            logActivity($user['id'], 'pr_delete', "Deleted Progress Report #$id");
            setFlash('success', 'Progress Report deleted.');
        }
    }
    redirect('/portal/progress-report/form.php' . ($studentId ? "?student_id=$studentId" : ''));
}

// ── Fetch for display ─────────────────────────────────────────────────────────
$studentId  = (int)($_GET['student_id'] ?? 0);
$editId     = (int)($_GET['edit']       ?? 0);

$results = []; $editResult = null;
if ($studentId && $tableExists) {
    $st = $db->prepare(
        'SELECT r.*, u.name AS reporter_name
         FROM progress_reports r JOIN users u ON u.id=r.reported_by
         WHERE r.student_id=? ORDER BY r.created_at DESC'
    );
    $st->execute([$studentId]);
    $results = $st->fetchAll();
    if ($editId) {
        foreach ($results as $r) { if ($r['id'] == $editId) { $editResult = $r; break; } }
    }
}

$curStudent = null;
foreach ($students as $s) { if ((int)$s['id'] === $studentId) { $curStudent = $s; break; } }

$fd = [];
if ($editResult && !empty($editResult['form_data'])) {
    $fd = json_decode($editResult['form_data'], true) ?? [];
}
$fdBasic = $fd['basic'] ?? ['term' => 'Final', 'session' => '', 'attendance' => '', 'date_of_issue' => ''];
$fdEng   = $fd['english']     ?? [];
$fdMath  = $fd['mathematics'] ?? [];
$fdRem   = $fd['remarks']     ?? '';

// ── Role-specific layout vars ─────────────────────────────────────────────────
$roleLabel  = match($role) {
    'teacher'    => 'teacher',
    'vp_main'    => 'vp_main',
    'wing_head'  => 'wing_head',
    default      => 'teacher',
};
$sidebarKey = match($role) {
    'teacher'   => 'progress-report',
    'vp_main'   => 'progress-report',
    'wing_head' => 'progress-report',
    default     => 'progress-report',
};
$links = match($role) {
    'teacher'   => getTeacherLinks(),
    'vp_main'   => getVpLinks(),
    'wing_head' => getWingHeadLinks(),
    default     => getTeacherLinks(),
};

// Helper: indicator select
function prSel(string $nm, string $val, array $opts): string {
    $h = '<select name="'.h($nm).'" class="pr-sel"><option value="">—</option>';
    foreach ($opts as $o) {
        $h .= '<option value="'.$o.'"'.($val===$o?' selected':'').'>'.$o.'</option>';
    }
    return $h.'</select>';
}

pageHead('Progress Report', $roleLabel);
?>
<div class="portal-wrap">
<?php sidebar($roleLabel, $sidebarKey, $links, $user); ?>
<div class="main-area">
<?php topbar('Generate Progress Report', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Branding strip -->
<div class="d-flex align-items-center gap-3 mb-3 p-3"
     style="background:linear-gradient(90deg,#eef2ff,#e0e7ff);border-radius:10px;border:1px solid #a5b4fc">
  <img src="<?= url('/assets/bmc-logo.png') ?>" alt="BMC"
       style="width:40px;height:40px;object-fit:contain;flex-shrink:0" onerror="this.style.display='none'">
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#3730a3;letter-spacing:.8px;text-transform:uppercase">
      Student Progress Report
    </div>
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
  <!-- ── Left: Student selector + form ─────────────────────────────── -->
  <div class="col-xl-6">
    <div class="sec-card mb-3">
      <div class="sec-card-header"><i class="fas fa-user-graduate me-2"></i>Select Student</div>
      <div style="padding:12px 16px">
        <?php if (empty($students)): ?>
        <div class="text-muted" style="font-size:.84rem">
          <?= $role === 'teacher'
              ? 'No students found. Ensure you are assigned to subjects in at least one class.'
              : 'No students found in the system.' ?>
        </div>
        <?php else: ?>
        <form method="GET" class="d-flex gap-2">
          <select name="student_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">— Select student —</option>
            <?php foreach ($students as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $studentId === (int)$s['id'] ? 'selected' : '' ?>>
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
           data-bs-toggle="collapse" data-bs-target="#prFormBody" style="cursor:pointer;background:linear-gradient(90deg,#3730a3,#4338ca);color:#fff">
        <span>
          <i class="fas fa-<?= $editResult ? 'edit' : 'plus' ?> me-2"></i>
          <?= $editResult
              ? 'Edit — ' . h($editResult['term']) . ' ' . h($editResult['session'])
              : 'New Progress Report — ' . h($curStudent['student_name']) ?>
        </span>
        <i class="fas fa-chevron-down"></i>
      </div>
      <div id="prFormBody" class="collapse show">
        <div style="padding:16px 18px 20px">

          <!-- Document header preview -->
          <div class="text-center mb-3 pb-2" style="border-bottom:2px solid #4338ca">
            <div style="font-size:.64rem;font-weight:700;letter-spacing:1px;color:#3730a3;text-transform:uppercase">
              Bahria College — Pakistan Navy Educational Trust
            </div>
            <div style="font-size:.9rem;font-weight:700;color:#0f172a;margin:.2rem 0 .1rem">
              Student Progress Report
            </div>
            <div style="font-size:.72rem;color:#64748b">PRIMARY SECTION</div>
          </div>

          <form method="POST" id="prForm">
            <input type="hidden" name="action"     value="<?= $editResult ? 'update' : 'create' ?>">
            <input type="hidden" name="student_id" value="<?= $studentId ?>">
            <?php if ($editResult): ?>
            <input type="hidden" name="result_id"  value="<?= $editResult['id'] ?>">
            <?php endif; ?>

            <!-- §1 Student / Basic Info -->
            <div class="fba-sec mb-2">
              <div class="fba-sh" style="background:#3730a3">1. Student Information</div>
              <div class="fba-sb">
                <div class="row g-2">
                  <div class="col-md-6">
                    <label class="fba-lbl">Student Name</label>
                    <input type="text" class="form-control form-control-sm bg-light"
                           value="<?= h($curStudent['student_name']) ?>" readonly>
                  </div>
                  <div class="col-md-3">
                    <label class="fba-lbl">Class &amp; Section</label>
                    <input type="text" class="form-control form-control-sm bg-light"
                           value="<?= h($curStudent['class_name']) ?>" readonly>
                  </div>
                  <div class="col-md-3">
                    <label class="fba-lbl">GR / Roll No</label>
                    <input type="text" class="form-control form-control-sm bg-light"
                           value="<?= h($curStudent['roll_no']) ?>" readonly>
                  </div>
                  <div class="col-md-4">
                    <label class="fba-lbl">Term <span class="text-danger">*</span></label>
                    <select name="basic_term" class="form-select form-select-sm" required>
                      <?php foreach ($TERMS as $t): ?>
                      <option value="<?= $t ?>" <?= ($fdBasic['term'] === $t) ? 'selected' : '' ?>><?= $t ?></option>
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

            <!-- Performance Key (info box) -->
            <div class="mb-2 p-2 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;font-size:.77rem">
              <span class="fw-bold me-2">KEY:</span>
              <span class="badge me-1" style="background:#1e3a5f;font-size:.72rem">AD</span> Advanced Development — Ahead of expected level &amp; often independently &nbsp;|&nbsp;
              <span class="badge me-1" style="background:#065f46;font-size:.72rem">ED</span> Expected Development — Consistently meets the expected level &nbsp;|&nbsp;
              <span class="badge me-1" style="background:#92400e;font-size:.72rem">EMD</span> Emerging Development — Struggles to meet the expected level
            </div>

            <!-- §2 English -->
            <div class="fba-sec mb-2">
              <div class="fba-sh" style="background:#1e3a5f">2. <?= $SECTIONS['english']['title'] ?></div>
              <div class="fba-sb" style="padding:0">
                <table class="table table-sm table-bordered mb-0" style="font-size:.8rem">
                  <thead><tr style="background:#dbeafe">
                    <th colspan="2">Learning Area</th>
                    <th class="text-center" style="width:70px">Indicator</th>
                  </tr></thead>
                  <tbody>
                  <?php foreach ($SECTIONS['english']['sub'] as $subTitle => $fields): ?>
                  <tr style="background:#eff6ff">
                    <td colspan="3" class="fw-semibold" style="font-size:.75rem;color:#1e3a5f;padding:3px 8px">
                      <?= $subTitle ?>
                    </td>
                  </tr>
                  <?php foreach ($fields as $fk => $label): ?>
                  <tr>
                    <td colspan="2" style="padding:3px 10px"><?= $label ?></td>
                    <td class="text-center" style="padding:3px 6px">
                      <?= prSel("eng_{$fk}", $fdEng[$fk] ?? '', $INDICATORS) ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- §3 Mathematics -->
            <div class="fba-sec mb-2">
              <div class="fba-sh" style="background:#065f46">3. <?= $SECTIONS['mathematics']['title'] ?></div>
              <div class="fba-sb" style="padding:0">
                <table class="table table-sm table-bordered mb-0" style="font-size:.8rem">
                  <thead><tr style="background:#d1fae5">
                    <th colspan="2">Learning Area</th>
                    <th class="text-center" style="width:70px">Indicator</th>
                  </tr></thead>
                  <tbody>
                  <?php foreach ($SECTIONS['mathematics']['sub'] as $subTitle => $fields): ?>
                  <tr style="background:#ecfdf5">
                    <td colspan="3" class="fw-semibold" style="font-size:.75rem;color:#065f46;padding:3px 8px">
                      <?= $subTitle ?>
                    </td>
                  </tr>
                  <?php foreach ($fields as $fk => $label): ?>
                  <tr>
                    <td colspan="2" style="padding:3px 10px"><?= $label ?></td>
                    <td class="text-center" style="padding:3px 6px">
                      <?= prSel("math_{$fk}", $fdMath[$fk] ?? '', $INDICATORS) ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- §4 Remarks -->
            <div class="fba-sec mb-3">
              <div class="fba-sh" style="background:#374151">4. Teacher's Remarks</div>
              <div class="fba-sb">
                <textarea name="remarks" class="form-control form-control-sm" rows="3"
                          placeholder="Enter overall remarks, strengths, areas for improvement…"><?= h($fdRem) ?></textarea>
              </div>
            </div>

            <div class="d-flex gap-2 pt-1">
              <button type="submit" class="btn btn-sm" style="background:#3730a3;color:#fff">
                <i class="fas fa-save me-1"></i><?= $editResult ? 'Update Report' : 'Save Report' ?>
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

  <!-- ── Right: Existing reports ───────────────────────────────────── -->
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
          $engFilled  = count(array_filter($rfd['english']     ?? []));
          $mathFilled = count(array_filter($rfd['mathematics'] ?? []));
        ?>
        <div class="mb-3 p-3" style="background:#f7f9fb;border-radius:8px;border:1px solid var(--border)">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <div>
              <span class="badge" style="background:#3730a3;font-size:.75rem">
                <?= h($r['term']) ?><?= $r['session'] ? ' · ' . h($r['session']) : '' ?>
              </span>
              <span style="font-size:.74rem;color:var(--t2);margin-left:8px">
                <?= fDate($r['created_at']) ?> · <?= h($r['reporter_name']) ?>
              </span>
            </div>
            <div class="d-flex gap-1">
              <a href="<?= url('/portal/progress-report/pdf.php?id=' . $r['id']) ?>"
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
            <i class="fas fa-check-circle me-1" style="color:#1e3a5f"></i>English: <?= $engFilled ?>/11
            &nbsp;<i class="fas fa-check-circle me-1" style="color:#065f46"></i>Math: <?= $mathFilled ?>/6
          </div>
          <?php if (!empty($rfd['remarks'])): ?>
          <div style="font-size:.74rem;margin-top:4px;color:var(--t2)">
            <i class="fas fa-comment-alt me-1"></i><?= h(mb_strimwidth($rfd['remarks'], 0, 120, '…')) ?>
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
.pr-sel{width:72px;font-size:.78rem;padding:2px 4px;border-radius:4px;border:1px solid #d1d5db;font-weight:700}
.btn-xs{padding:2px 8px;font-size:.75rem}
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

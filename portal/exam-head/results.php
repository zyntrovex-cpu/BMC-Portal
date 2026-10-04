<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/db.php';

$user = requireAuth('examination_head');
requirePermission('eh_results');
$db = getDB();

// ── Inputs ────────────────────────────────────────────────────────────────────
$classId  = (int)($_GET['class_id'] ?? 0);
$rcSearch = trim($_GET['rc_q'] ?? '');

// ── Main-campus class list (no ILC, no Montessori) ───────────────────────────
$classes = $db->query(
    'SELECT id, name, grade, section
     FROM classes
     WHERE COALESCE(is_ilc,0)=0 AND COALESCE(is_montessori,0)=0
     ORDER BY grade, section'
)->fetchAll();

// ── Results grid for selected class ───────────────────────────────────────────
$results = [];
$selectedClass = null;
if ($classId) {
    // Verify class is main campus (backend check — not just UI)
    foreach ($classes as $c) {
        if ((int)$c['id'] === $classId) { $selectedClass = $c; break; }
    }
    if (!$selectedClass) {
        // Attempted access to ILC/Montessori class — silently reset
        $classId = 0;
    } else {
        $st = $db->prepare(
            'SELECT s.id AS student_id, u.name, s.roll_no,
                    COALESCE(SUM(m.marks_obtained), 0) AS total_obtained,
                    COALESCE(SUM(a.max_marks), 0)      AS total_possible,
                    COUNT(DISTINCT m.assessment_id)    AS assessments
             FROM students s
             JOIN users u ON u.id = s.user_id
             LEFT JOIN marks m ON m.student_id = s.id
             LEFT JOIN assessments a ON a.id = m.assessment_id AND a.class_id = s.class_id
             WHERE s.class_id = ? AND s.deleted_at IS NULL
             GROUP BY s.id, u.id
             ORDER BY u.name'
        );
        $st->execute([$classId]);
        $results = $st->fetchAll();
    }
}

// ── Report-card student search (main campus only) ─────────────────────────────
$rcStudents = [];
if ($rcSearch !== '') {
    $like = '%' . $rcSearch . '%';
    $st = $db->prepare(
        "SELECT s.id AS student_id, u.name, u.user_id AS gr_no, s.roll_no,
                c.name AS class_name, c.grade
         FROM students s
         JOIN users u ON s.user_id = u.id
         LEFT JOIN classes c ON c.id = s.class_id
         WHERE (u.name LIKE ? OR u.user_id LIKE ? OR s.roll_no LIKE ?)
           AND COALESCE(c.is_ilc,0)=0
           AND COALESCE(c.is_montessori,0)=0
           AND s.deleted_at IS NULL
         ORDER BY c.grade, u.name
         LIMIT 50"
    );
    $st->execute([$like, $like, $like]);
    $rcStudents = $st->fetchAll();
}

// ── Grade helper ──────────────────────────────────────────────────────────────
function ehGrade(float $pct): string {
    if ($pct >= 90) return 'A+';
    if ($pct >= 80) return 'A';
    if ($pct >= 70) return 'B+';
    if ($pct >= 60) return 'B';
    if ($pct >= 50) return 'C';
    if ($pct >= 40) return 'D';
    return 'F';
}
function ehGradeBadge(float $pct): string {
    if ($pct >= 80) return 'bg-success';
    if ($pct >= 50) return 'bg-warning text-dark';
    return 'bg-danger';
}

pageHead('Results', 'examination_head');
$links = getExamHeadLinks();
?>
<div class="portal-wrap">
<?php sidebar('examination_head', 'results', $links, $user); ?>
<div class="main-area">
<?php topbar('Results', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- ── Page header ─────────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center gap-2 mb-3">
  <div style="width:38px;height:38px;background:#eff6ff;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
    <i class="fas fa-chart-bar" style="color:#2563eb;font-size:1rem"></i>
  </div>
  <div>
    <div style="font-size:1.05rem;font-weight:800;color:#1e293b;line-height:1.2">Results</div>
    <div style="font-size:.76rem;color:#64748b">Main Campus (Class 1–12) — Academic Results &amp; Report Cards</div>
  </div>
</div>

<!-- ── Section 1: Report Card Search ─────────────────────────────────────── -->
<div class="sec-card mb-3" style="border-left:4px solid #1d4ed8">
  <div class="sec-card-header d-flex align-items-center gap-2">
    <i class="fas fa-file-alt text-primary"></i>
    <span class="fw-semibold">Student Report Cards — View &amp; Download</span>
  </div>
  <div style="padding:14px 16px">
    <form method="GET" class="row g-2 align-items-end mb-3">
      <?php if ($classId): ?><input type="hidden" name="class_id" value="<?= $classId ?>"><?php endif; ?>
      <div class="col-md-5">
        <label class="form-label fw-semibold" style="font-size:.82rem">
          Search Student (Name / GR No / Roll No)
        </label>
        <input type="text" name="rc_q" value="<?= h($rcSearch) ?>"
               class="form-control form-control-sm"
               placeholder="e.g. Ahmed, GR-001, 15…"
               autocomplete="off">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="fas fa-search me-1"></i>Search
        </button>
        <?php if ($rcSearch): ?>
        <a href="?class_id=<?= $classId ?>" class="btn btn-sm btn-outline-secondary ms-1">
          <i class="fas fa-times me-1"></i>Clear
        </a>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($rcSearch !== '' && empty($rcStudents)): ?>
    <div style="font-size:.83rem;color:#64748b;padding:8px 0">
      <i class="fas fa-info-circle me-1"></i>No main-campus students found for
      "<strong><?= h($rcSearch) ?></strong>".
    </div>

    <?php elseif (!empty($rcStudents)): ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0" style="font-size:.82rem">
        <thead class="table-light">
          <tr>
            <th>Student Name</th>
            <th>GR No</th>
            <th>Roll No</th>
            <th>Class</th>
            <th class="text-center">Report Card</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rcStudents as $rs): ?>
          <tr>
            <td class="fw-semibold"><?= h($rs['name']) ?></td>
            <td><?= h($rs['gr_no'] ?: '—') ?></td>
            <td><?= h($rs['roll_no'] ?: '—') ?></td>
            <td>
              <?php if ($rs['class_name']): ?>
              <span class="badge bg-secondary"><?= h($rs['class_name']) ?></span>
              <?php else: ?>
              <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <a href="<?= url('/portal/report-card.php?student_id=' . $rs['student_id']) ?>"
                 class="btn btn-sm btn-primary" style="font-size:.76rem;padding:3px 12px"
                 target="_blank" rel="noopener">
                <i class="fas fa-file-alt me-1"></i>View Report Card
              </a>
              <a href="<?= url('/portal/report-card.php?student_id=' . $rs['student_id'] . '&export=word') ?>"
                 class="btn btn-sm btn-outline-secondary ms-1" style="font-size:.76rem;padding:3px 10px"
                 title="Download as Word document">
                <i class="fas fa-download me-1"></i>Download
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php else: ?>
    <div style="font-size:.8rem;color:#94a3b8">
      <i class="fas fa-search me-1"></i>Enter a student name, GR number, or roll number to search.
      Only Main Campus (Class 1–12) students are accessible.
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Section 2: Class Results Grid ──────────────────────────────────────── -->
<div class="sec-card mb-3">
  <div class="sec-card-header d-flex align-items-center gap-2">
    <i class="fas fa-filter"></i>
    <span class="fw-semibold">Results by Class</span>
  </div>
  <div style="padding:14px 16px">
    <form method="GET" class="row g-2 align-items-end">
      <?php if ($rcSearch): ?><input type="hidden" name="rc_q" value="<?= h($rcSearch) ?>"><?php endif; ?>
      <div class="col-sm-auto">
        <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
        <select name="class_id" class="form-select form-select-sm" style="min-width:160px">
          <option value="0">— Select class —</option>
          <?php
          $curGrade = null;
          foreach ($classes as $c):
              if ($curGrade !== null && $curGrade !== (int)$c['grade']) echo '<option disabled>──────────</option>';
              $curGrade = (int)$c['grade'];
          ?>
          <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>>
            <?= h($c['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="fas fa-eye me-1"></i>View Results
        </button>
        <?php if ($classId): ?>
        <a href="?" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if ($classId && $selectedClass): ?>

<?php if (!empty($results)): ?>
<!-- Summary stats for selected class -->
<?php
$totalStudents  = count($results);
$passCount      = 0;
$totalPct       = 0;
foreach ($results as $r) {
    $p = $r['total_possible'] > 0 ? ($r['total_obtained'] / $r['total_possible'] * 100) : 0;
    if ($p >= 40) $passCount++;
    $totalPct += $p;
}
$avgPct = $totalStudents > 0 ? round($totalPct / $totalStudents, 1) : 0;
$passRate = $totalStudents > 0 ? round($passCount / $totalStudents * 100, 0) : 0;
?>
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="stat-card" style="border-left:3px solid #2563eb;padding:12px 14px">
      <div class="stat-icon" style="background:#eff6ff;color:#2563eb;width:34px;height:34px;font-size:.9rem">
        <i class="fas fa-users"></i>
      </div>
      <div class="stat-body">
        <div class="stat-num" style="font-size:1.4rem"><?= $totalStudents ?></div>
        <div class="stat-label">Students</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="border-left:3px solid #059669;padding:12px 14px">
      <div class="stat-icon" style="background:#f0fdf4;color:#059669;width:34px;height:34px;font-size:.9rem">
        <i class="fas fa-check-circle"></i>
      </div>
      <div class="stat-body">
        <div class="stat-num" style="font-size:1.4rem"><?= $passCount ?></div>
        <div class="stat-label">Passed (≥40%)</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="border-left:3px solid #d97706;padding:12px 14px">
      <div class="stat-icon" style="background:#fffbeb;color:#d97706;width:34px;height:34px;font-size:.9rem">
        <i class="fas fa-percentage"></i>
      </div>
      <div class="stat-body">
        <div class="stat-num" style="font-size:1.4rem"><?= $avgPct ?>%</div>
        <div class="stat-label">Class Average</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="border-left:3px solid #7c3aed;padding:12px 14px">
      <div class="stat-icon" style="background:#f5f3ff;color:#7c3aed;width:34px;height:34px;font-size:.9rem">
        <i class="fas fa-award"></i>
      </div>
      <div class="stat-body">
        <div class="stat-num" style="font-size:1.4rem"><?= $passRate ?>%</div>
        <div class="stat-label">Pass Rate</div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="sec-card">
  <div class="sec-card-header d-flex align-items-center justify-content-between">
    <span>
      <i class="fas fa-chart-bar me-2"></i>
      Results — <strong><?= h($selectedClass['name']) ?></strong>
    </span>
    <?php if (!empty($results)): ?>
    <span style="font-size:.75rem;color:#64748b"><?= $totalStudents ?> student<?= $totalStudents !== 1 ? 's' : '' ?></span>
    <?php endif; ?>
  </div>

  <?php if (!empty($results)): ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.83rem">
      <thead class="table-light">
        <tr>
          <th style="width:40px">#</th>
          <th>Student Name</th>
          <th>Roll No</th>
          <th class="text-center">Assessments</th>
          <th class="text-end">Max Marks</th>
          <th class="text-end">Obtained</th>
          <th class="text-center">%</th>
          <th class="text-center">Grade</th>
          <th class="text-center">Report Card</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($results as $i => $r):
          $pct   = $r['total_possible'] > 0
                   ? round($r['total_obtained'] / $r['total_possible'] * 100, 1)
                   : 0;
          $grade = ehGrade((float)$pct);
          $badge = ehGradeBadge((float)$pct);
        ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <td class="fw-semibold"><?= h($r['name']) ?></td>
          <td><?= h($r['roll_no'] ?: '—') ?></td>
          <td class="text-center"><?= (int)$r['assessments'] ?></td>
          <td class="text-end"><?= $r['total_possible'] > 0 ? number_format((float)$r['total_possible'], 0) : '—' ?></td>
          <td class="text-end"><?= $r['total_possible'] > 0 ? number_format((float)$r['total_obtained'], 1) : '—' ?></td>
          <td class="text-center">
            <?php if ($r['total_possible'] > 0): ?>
            <span class="badge <?= $badge ?>"><?= $pct ?>%</span>
            <?php else: ?>
            <span class="text-muted" style="font-size:.75rem">No marks</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <?php if ($r['total_possible'] > 0): ?>
            <span class="badge <?= $badge ?>"><?= $grade ?></span>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <a href="<?= url('/portal/report-card.php?student_id=' . $r['student_id']) ?>"
               class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 9px"
               target="_blank" rel="noopener" title="View Report Card">
              <i class="fas fa-file-alt me-1"></i>View
            </a>
            <a href="<?= url('/portal/report-card.php?student_id=' . $r['student_id'] . '&export=word') ?>"
               class="btn btn-xs btn-outline-secondary ms-1" style="font-size:.74rem;padding:2px 7px"
               title="Download as Word">
              <i class="fas fa-download"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php else: ?>
  <div style="padding:28px 20px;text-align:center;color:#94a3b8">
    <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.4"></i>
    <div style="font-size:.85rem">No results recorded for <strong><?= h($selectedClass['name']) ?></strong> yet.</div>
    <div style="font-size:.76rem;margin-top:4px">Marks must be entered via Assessments &amp; Marks before results appear here.</div>
  </div>
  <?php endif; ?>
</div>

<?php elseif ($classId && !$selectedClass): ?>
<div class="alert alert-danger" style="font-size:.86rem">
  <i class="fas fa-ban me-1"></i>Access denied. That class is outside your authorized scope.
</div>
<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

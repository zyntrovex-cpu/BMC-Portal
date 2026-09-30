<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('admin');
$db      = getDB();
$yearNow = (int)date('Y');

$allowedWings = ['main', 'montessori', 'ilc', 'all'];
$termOptions  = ['Mid-Term', 'Final-Term', 'Unit Test 1', 'Unit Test 2', 'Annual', 'Mock Exam', 'General'];

// ── Load all classes with wing/grade info ─────────────────────────────
$classes = getAllClasses();

// Build class→subjects JSON for JS subject loader
$classSubjectsMap = [];
foreach ($classes as $c) {
    $subs = getClassSubjects((int)$c['id']);
    if ($subs) {
        $classSubjectsMap[(int)$c['id']] = array_column($subs, 'name');
    }
}

// ── POST handlers ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_ds') {
        $title = trim($_POST['title'] ?? '');
        $term  = trim($_POST['term']  ?? 'General');
        $wing  = $_POST['wing']        ?? 'all';
        $year  = trim($_POST['academic_year'] ?? "$yearNow-" . ($yearNow + 1));
        $notes = trim($_POST['notes'] ?? '');
        if (!in_array($wing, $allowedWings, true)) $wing = 'all';
        if (!in_array($term, $termOptions, true))  $term = 'General';
        if ($title) {
            try {
                $db->prepare(
                    'INSERT INTO exam_date_sheets (title,term,wing,academic_year,notes,created_by)
                     VALUES (?,?,?,?,?,?)'
                )->execute([$title, $term, $wing, $year, $notes, $user['id']]);
            } catch (\PDOException $e) {
                // term column missing on old schema — insert without it
                $db->prepare(
                    'INSERT INTO exam_date_sheets (title,wing,academic_year,notes,created_by)
                     VALUES (?,?,?,?,?)'
                )->execute([$title, $wing, $year, $notes, $user['id']]);
            }
            $newId = (int)$db->lastInsertId();
            logActivity($user['id'], 'exam_ds_create', "Created date sheet: \"$title\" ($wing, $year)");
            setFlash('success', "Date sheet \"$title\" created. Add exam entries below.");
            redirect('/portal/admin/exam-datesheet.php?ds=' . $newId);
        }
        setFlash('danger', 'Title is required.');
        redirect('/portal/admin/exam-datesheet.php?create=1');
    }

    if ($action === 'update_ds') {
        $dsId  = (int)($_POST['ds_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $term  = trim($_POST['term']  ?? 'General');
        $wing  = $_POST['wing']        ?? 'all';
        $year  = trim($_POST['academic_year'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if (!in_array($wing, $allowedWings, true)) $wing = 'all';
        if (!in_array($term, $termOptions, true))  $term = 'General';
        if ($dsId && $title) {
            try {
                $db->prepare(
                    'UPDATE exam_date_sheets SET title=?,term=?,wing=?,academic_year=?,notes=? WHERE id=?'
                )->execute([$title, $term, $wing, $year, $notes, $dsId]);
            } catch (\PDOException $e) {
                $db->prepare(
                    'UPDATE exam_date_sheets SET title=?,wing=?,academic_year=?,notes=? WHERE id=?'
                )->execute([$title, $wing, $year, $notes, $dsId]);
            }
            logActivity($user['id'], 'exam_ds_update', "Updated date sheet #$dsId: \"$title\"");
            setFlash('success', 'Date sheet updated.');
        }
        redirect('/portal/admin/exam-datesheet.php?ds=' . $dsId);
    }

    if ($action === 'toggle_status') {
        $dsId = (int)($_POST['ds_id'] ?? 0);
        if ($dsId) {
            $cur = $db->prepare('SELECT status, title FROM exam_date_sheets WHERE id=?');
            $cur->execute([$dsId]);
            $cur = $cur->fetch();
            $newStatus = ($cur && $cur['status'] === 'published') ? 'draft' : 'published';
            $db->prepare('UPDATE exam_date_sheets SET status=? WHERE id=?')->execute([$newStatus, $dsId]);
            logActivity($user['id'], 'exam_ds_publish', "Set \"" . ($cur['title'] ?? '') . "\" to $newStatus");
            setFlash('success', $newStatus === 'published'
                ? 'Date sheet published — now visible to staff and students.'
                : 'Date sheet reverted to draft — hidden from students.');
            redirect('/portal/admin/exam-datesheet.php?ds=' . $dsId);
        }
    }

    if ($action === 'delete_ds') {
        $dsId = (int)($_POST['ds_id'] ?? 0);
        if ($dsId) {
            $r = $db->prepare('SELECT title FROM exam_date_sheets WHERE id=?');
            $r->execute([$dsId]);
            $r = $r->fetch();
            $db->prepare('DELETE FROM exam_date_sheets WHERE id=?')->execute([$dsId]);
            logActivity($user['id'], 'exam_ds_delete', "Deleted: \"" . ($r['title'] ?? '') . '"');
            setFlash('success', 'Date sheet deleted.');
            redirect('/portal/admin/exam-datesheet.php');
        }
    }

    if ($action === 'add_entry' || $action === 'update_entry') {
        $dsId      = (int)($_POST['ds_id']     ?? 0);
        $entryId   = (int)($_POST['entry_id']  ?? 0);
        $classId   = (int)($_POST['class_id']  ?? 0) ?: null;
        $subject   = trim($_POST['subject']    ?? '');
        $examDate  = trim($_POST['exam_date']  ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime   = trim($_POST['end_time']   ?? '');
        $venue     = trim($_POST['venue']      ?? '');
        $eNotes    = trim($_POST['entry_notes']?? '');
        $sortOrder = (int)($_POST['sort_order']?? 0);

        $errors = [];
        if (!$dsId)    $errors[] = 'Invalid date sheet.';
        if (!$subject) $errors[] = 'Subject is required.';
        if (!$examDate)  $errors[] = 'Exam date is required.';
        if (!$startTime) $errors[] = 'Start time is required.';
        if (!$endTime)   $errors[] = 'End time is required.';
        if ($startTime && $endTime && $startTime >= $endTime)
            $errors[] = 'End time must be after start time.';

        // Montessori ≤ Class 1 cannot have exams
        if ($classId) {
            $gc = $db->prepare('SELECT COALESCE(is_montessori,0) AS im, COALESCE(grade,-1) AS g FROM classes WHERE id=?');
            $gc->execute([$classId]);
            $gcRow = $gc->fetch();
            if ($gcRow && $gcRow['im'] && (int)$gcRow['g'] < 2) {
                $errors[] = 'Montessori Beginner / Advance / Prep / Class 1 classes do not have formal exams. Use Progress Reports for these classes.';
            }
        }

        // Duplicate check
        if (!$errors && $action === 'add_entry') {
            $dup = $db->prepare(
                'SELECT 1 FROM exam_date_sheet_entries
                 WHERE date_sheet_id=? AND subject=? AND exam_date=?
                   AND (class_id=? OR (class_id IS NULL AND ? IS NULL))
                 LIMIT 1'
            );
            $dup->execute([$dsId, $subject, $examDate, $classId, $classId]);
            if ($dup->fetchColumn()) {
                $errors[] = "Duplicate: $subject is already scheduled on " . date('d M Y', strtotime($examDate)) . " for this class in this date sheet.";
            }
        }

        if ($errors) {
            setFlash('danger', implode('<br>', $errors));
            redirect('/portal/admin/exam-datesheet.php?ds=' . $dsId . '&add=1' . ($entryId ? '&edit=' . $entryId : ''));
        }

        if ($action === 'add_entry') {
            $db->prepare(
                'INSERT INTO exam_date_sheet_entries
                 (date_sheet_id,class_id,subject,exam_date,start_time,end_time,venue,notes,sort_order)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([$dsId, $classId, $subject, $examDate, $startTime, $endTime, $venue, $eNotes, $sortOrder]);
            setFlash('success', 'Exam entry added.');
        } else {
            $db->prepare(
                'UPDATE exam_date_sheet_entries
                 SET class_id=?,subject=?,exam_date=?,start_time=?,end_time=?,venue=?,notes=?,sort_order=?
                 WHERE id=?'
            )->execute([$classId, $subject, $examDate, $startTime, $endTime, $venue, $eNotes, $sortOrder, $entryId]);
            setFlash('success', 'Exam entry updated.');
        }
        redirect('/portal/admin/exam-datesheet.php?ds=' . $dsId);
    }

    if ($action === 'delete_entry') {
        $dsId    = (int)($_POST['ds_id']    ?? 0);
        $entryId = (int)($_POST['entry_id'] ?? 0);
        if ($entryId) {
            $db->prepare('DELETE FROM exam_date_sheet_entries WHERE id=?')->execute([$entryId]);
            setFlash('success', 'Entry deleted.');
            redirect('/portal/admin/exam-datesheet.php?ds=' . $dsId);
        }
    }

    redirect('/portal/admin/exam-datesheet.php');
}

// ── Determine view ────────────────────────────────────────────────────
$dsId       = (int)($_GET['ds']     ?? 0);
$showAdd    = (bool)($_GET['add']   ?? 0);
$editEntId  = (int)($_GET['edit']   ?? 0);
$showCreate = (bool)($_GET['create']?? 0);
$filterWing = $_GET['wing'] ?? '';
$filterYear = trim($_GET['year'] ?? '');
$filterStatus = $_GET['status'] ?? '';
if (!in_array($filterWing, $allowedWings, true)) $filterWing = '';

$ds = null; $entries = []; $editEntry = null;

if ($dsId) {
    $r = $db->prepare(
        'SELECT ds.*, u.name AS creator_name
         FROM exam_date_sheets ds JOIN users u ON ds.created_by=u.id
         WHERE ds.id=?'
    );
    $r->execute([$dsId]);
    $ds = $r->fetch();
    if (!$ds) { setFlash('danger', 'Date sheet not found.'); redirect('/portal/admin/exam-datesheet.php'); }

    $eSt = $db->prepare(
        'SELECT e.*, c.name AS class_name, c.grade AS class_grade,
                COALESCE(c.is_montessori,0) AS class_is_monte
         FROM exam_date_sheet_entries e LEFT JOIN classes c ON c.id=e.class_id
         WHERE e.date_sheet_id=?
         ORDER BY e.sort_order, e.exam_date, e.start_time'
    );
    $eSt->execute([$dsId]);
    $entries = $eSt->fetchAll();

    if ($editEntId) {
        foreach ($entries as $e) {
            if ((int)$e['id'] === $editEntId) { $editEntry = $e; $showAdd = true; break; }
        }
    }
}

// List query with filters
$listConds = []; $listParams = [];
if ($filterWing)   { $listConds[] = 'ds.wing=?';   $listParams[] = $filterWing; }
if ($filterYear)   { $listConds[] = 'ds.academic_year=?'; $listParams[] = $filterYear; }
if ($filterStatus) { $listConds[] = 'ds.status=?';  $listParams[] = $filterStatus; }
$listWhere = $listConds ? ('WHERE ' . implode(' AND ', $listConds)) : '';

$listSt = $db->prepare(
    "SELECT ds.*, u.name AS creator_name,
            (SELECT COUNT(*) FROM exam_date_sheet_entries e WHERE e.date_sheet_id=ds.id) AS entry_count
     FROM exam_date_sheets ds JOIN users u ON ds.created_by=u.id
     $listWhere
     ORDER BY ds.academic_year DESC, ds.created_at DESC"
);
$listSt->execute($listParams);
$dateSheets = $listSt->fetchAll();

// Distinct academic years for filter
$yearsSt = $db->query('SELECT DISTINCT academic_year FROM exam_date_sheets ORDER BY academic_year DESC');
$allYears = $yearsSt ? $yearsSt->fetchAll(PDO::FETCH_COLUMN) : [];

// Check for ?print=1
$isPrint = (bool)($_GET['print'] ?? 0);
if ($isPrint && $dsId && $ds) {
    // Print-friendly page — minimal layout
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($ds['title']) ?> — Exam Date Sheet</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;font-size:13px;color:#111;background:#fff;padding:24px}
.print-header{text-align:center;margin-bottom:20px;border-bottom:2px solid #1e3a8a;padding-bottom:14px}
.print-header h1{font-size:18px;color:#1e3a8a;font-weight:700}
.print-header p{font-size:12px;color:#555;margin-top:4px}
.meta-row{display:flex;gap:24px;flex-wrap:wrap;margin-bottom:18px;font-size:12px}
.meta-item{display:flex;gap:6px}.meta-item b{color:#1e3a8a}
table{width:100%;border-collapse:collapse;margin-bottom:20px}
th{background:#1e3a8a;color:#fff;padding:8px 10px;text-align:left;font-size:12px}
td{border:1px solid #ddd;padding:7px 10px;vertical-align:middle}
tr:nth-child(even) td{background:#f8f9fa}
.subject{font-weight:700;color:#1e3a8a}
.footer{text-align:center;font-size:11px;color:#888;margin-top:16px;border-top:1px solid #ddd;padding-top:10px}
@media print{
  body{padding:10px}
  .no-print{display:none}
  button{display:none}
}
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:16px">
  <button onclick="window.print()" style="background:#1e3a8a;color:#fff;border:none;padding:8px 20px;border-radius:5px;cursor:pointer;font-size:13px">
    &#128424; Print / Save as PDF
  </button>
  <a href="?ds=<?= $dsId ?>" style="margin-left:12px;color:#1e3a8a;font-size:13px">← Back to Manage</a>
</div>

<div class="print-header">
  <h1><?= h(SCHOOL_NAME) ?></h1>
  <p><?= h($ds['title']) ?></p>
</div>

<div class="meta-row">
  <div class="meta-item"><b>Term:</b> <?= h($ds['term'] ?? 'General') ?></div>
  <div class="meta-item"><b>Wing:</b> <?= h(ucfirst($ds['wing'])) ?></div>
  <div class="meta-item"><b>Academic Year:</b> <?= h($ds['academic_year']) ?></div>
  <div class="meta-item"><b>Status:</b> <?= ucfirst($ds['status']) ?></div>
  <?php if ($ds['notes']): ?><div class="meta-item"><b>Note:</b> <?= h($ds['notes']) ?></div><?php endif; ?>
</div>

<table>
  <thead>
    <tr>
      <th>#</th><th>Date</th><th>Day</th><th>Class</th><th>Subject</th>
      <th>Start Time</th><th>End Time</th><th>Venue</th><th>Notes</th>
    </tr>
  </thead>
  <tbody>
    <?php $n = 1; foreach ($entries as $e): ?>
    <tr>
      <td><?= $n++ ?></td>
      <td style="white-space:nowrap"><?= date('d M Y', strtotime($e['exam_date'])) ?></td>
      <td><?= date('l', strtotime($e['exam_date'])) ?></td>
      <td><?= $e['class_name'] ? h($e['class_name']) : '<i style="color:#888">All Classes</i>' ?></td>
      <td class="subject"><?= h($e['subject']) ?></td>
      <td style="white-space:nowrap"><?= date('h:i A', strtotime($e['start_time'])) ?></td>
      <td style="white-space:nowrap"><?= date('h:i A', strtotime($e['end_time'])) ?></td>
      <td><?= h($e['venue'] ?: '—') ?></td>
      <td style="font-size:11px"><?= h($e['notes'] ?: '—') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($entries)): ?>
    <tr><td colspan="9" style="text-align:center;color:#888;padding:20px">No exam entries found.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<div class="footer">Printed on <?= date('d M Y') ?> &mdash; <?= h(SCHOOL_NAME) ?> Examination System</div>
</body></html>
<?php exit; }

pageHead('Exam Date Sheets', 'admin');
$links = getAdminLinks();
?>
<div class="portal-wrap">
<?php sidebar('admin', 'exam-datesheet', $links, $user); ?>
<div class="main-area">
<?php topbar('Exam Date Sheets', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if ($dsId && $ds): /* ── MANAGE VIEW ── */ ?>

<div class="d-flex align-items-center mb-3 gap-2 flex-wrap">
  <a href="/portal/admin/exam-datesheet.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i>All Date Sheets
  </a>
  <span class="text-muted" style="font-size:.84rem">&rsaquo; <?= h($ds['title']) ?></span>
  <?php
    $sBadge = $ds['status'] === 'published'
        ? '<span class="badge bg-success ms-1">Published</span>'
        : '<span class="badge bg-warning text-dark ms-1">Draft</span>';
    echo $sBadge;
  ?>
  <a href="?ds=<?= $dsId ?>&print=1" target="_blank" class="btn btn-sm btn-outline-secondary ms-auto">
    <i class="fas fa-print me-1"></i>Print / PDF
  </a>
</div>

<?php if ($ds['status'] === 'draft'): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3" style="font-size:.85rem">
  <i class="fas fa-eye-slash"></i>
  <span>This date sheet is in <strong>Draft</strong> status — it is <strong>not visible</strong> to students or staff yet. Use <em>Publish Date Sheet</em> on the left to make it live.</span>
</div>
<?php endif; ?>

<div class="row g-3">
  <!-- Left: header info -->
  <div class="col-lg-4">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-info-circle me-2"></i>Date Sheet Info</div>
      <div style="padding:16px">
        <form method="POST">
          <input type="hidden" name="action" value="update_ds">
          <input type="hidden" name="ds_id" value="<?= $ds['id'] ?>">
          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Title / Exam Name *</label>
            <input type="text" name="title" class="form-control form-control-sm" value="<?= h($ds['title']) ?>" required>
          </div>
          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Examination / Term</label>
            <select name="term" class="form-select form-select-sm">
              <?php foreach ($termOptions as $t): ?>
              <option value="<?= h($t) ?>" <?= ($ds['term'] ?? 'General') === $t ? 'selected' : '' ?>><?= h($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Campus / Wing</label>
            <select name="wing" class="form-select form-select-sm">
              <?php foreach (['all' => 'All Campuses', 'main' => 'Main Wing', 'montessori' => 'Montessori', 'ilc' => 'ILC'] as $wv => $wl): ?>
              <option value="<?= $wv ?>" <?= $ds['wing'] === $wv ? 'selected' : '' ?>><?= $wl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Academic Year</label>
            <input type="text" name="academic_year" class="form-control form-control-sm"
                   value="<?= h($ds['academic_year']) ?>" placeholder="e.g. 2025-2026">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Notes</label>
            <textarea name="notes" class="form-control form-control-sm" rows="2"><?= h($ds['notes'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-save me-1"></i>Save Changes</button>
        </form>
        <hr>
        <form method="POST" onsubmit="return confirm('Change publish status?')">
          <input type="hidden" name="action" value="toggle_status">
          <input type="hidden" name="ds_id" value="<?= $ds['id'] ?>">
          <?php if ($ds['status'] === 'published'): ?>
          <button type="submit" class="btn btn-sm btn-outline-warning w-100">
            <i class="fas fa-eye-slash me-1"></i>Revert to Draft
          </button>
          <?php else: ?>
          <button type="submit" class="btn btn-sm btn-success w-100">
            <i class="fas fa-globe me-1"></i>Publish Date Sheet
          </button>
          <?php endif; ?>
        </form>
        <p class="mt-2 text-muted" style="font-size:.75rem">
          Created by <?= h($ds['creator_name']) ?> &mdash; <?= fDate($ds['created_at']) ?>
        </p>
        <hr>
        <form method="POST" onsubmit="return confirm('Delete this entire date sheet and all its entries? This cannot be undone.')">
          <input type="hidden" name="action" value="delete_ds">
          <input type="hidden" name="ds_id" value="<?= $ds['id'] ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger w-100">
            <i class="fas fa-trash me-1"></i>Delete Date Sheet
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Right: entries -->
  <div class="col-lg-8">
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>Exam Schedule
          <span class="text-muted fw-normal">(<?= count($entries) ?> entries)</span>
        </span>
        <a href="?ds=<?= $dsId ?>&add=1" class="btn btn-sm btn-success">
          <i class="fas fa-plus me-1"></i>Add Entry
        </a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.82rem">
          <thead class="table-light">
            <tr>
              <th>#</th><th>Date</th><th>Day</th><th>Class</th>
              <th>Subject</th><th>Start</th><th>End</th><th>Venue</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php $n = 1; foreach ($entries as $e): ?>
            <tr class="<?= (int)$e['id'] === $editEntId ? 'table-warning' : '' ?>">
              <td class="text-muted"><?= $n++ ?></td>
              <td style="white-space:nowrap"><?= date('d M Y', strtotime($e['exam_date'])) ?></td>
              <td><?= date('D', strtotime($e['exam_date'])) ?></td>
              <td>
                <?= $e['class_name']
                    ? h($e['class_name'])
                    : '<span class="text-muted" style="font-size:.75rem">All</span>' ?>
              </td>
              <td class="fw-semibold"><?= h($e['subject']) ?></td>
              <td style="white-space:nowrap"><?= date('h:i A', strtotime($e['start_time'])) ?></td>
              <td style="white-space:nowrap"><?= date('h:i A', strtotime($e['end_time'])) ?></td>
              <td style="font-size:.78rem"><?= h($e['venue'] ?: '—') ?></td>
              <td style="white-space:nowrap">
                <a href="?ds=<?= $dsId ?>&edit=<?= $e['id'] ?>&add=1"
                   class="btn btn-xs btn-outline-primary me-1" style="font-size:.7rem;padding:1px 6px">Edit</a>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this entry?')">
                  <input type="hidden" name="action" value="delete_entry">
                  <input type="hidden" name="ds_id" value="<?= $dsId ?>">
                  <input type="hidden" name="entry_id" value="<?= $e['id'] ?>">
                  <button class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:1px 6px">Del</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($entries)): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">
              No entries yet — click <strong>Add Entry</strong> to begin building the schedule.
            </td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($showAdd): ?>
    <div class="sec-card mt-3" id="entry-form">
      <div class="sec-card-header">
        <i class="fas fa-<?= $editEntry ? 'edit' : 'plus' ?> me-2"></i>
        <?= $editEntry ? 'Edit' : 'Add' ?> Exam Entry
      </div>
      <div style="padding:18px">
        <form method="POST" id="entryForm">
          <input type="hidden" name="action" value="<?= $editEntry ? 'update_entry' : 'add_entry' ?>">
          <input type="hidden" name="ds_id" value="<?= $dsId ?>">
          <?php if ($editEntry): ?>
          <input type="hidden" name="entry_id" value="<?= $editEntry['id'] ?>">
          <?php endif; ?>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label fw-semibold" style="font-size:.82rem">
                Class <span class="text-muted fw-normal">(leave blank = all classes in this sheet)</span>
              </label>
              <select name="class_id" id="classSelect" class="form-select form-select-sm">
                <option value="">— All Classes —</option>
                <?php
                // Group classes by wing
                $grouped = [];
                foreach ($classes as $c) {
                    $w = $c['is_montessori'] ? 'Montessori' : ($c['is_ilc'] ? 'ILC' : 'Main Wing');
                    $grouped[$w][] = $c;
                }
                foreach ($grouped as $wLabel => $wClasses):
                ?>
                <optgroup label="<?= h($wLabel) ?>">
                  <?php foreach ($wClasses as $c):
                    // Mark Montessori ≤ Class 1 so user understands they won't work
                    $noExam = $c['is_montessori'] && (int)($c['grade'] ?? -1) < 2;
                  ?>
                  <option value="<?= $c['id'] ?>"
                    <?= ($editEntry && (int)$editEntry['class_id'] === (int)$c['id']) ? 'selected' : '' ?>
                    <?= $noExam ? 'data-no-exam="1"' : '' ?>>
                    <?= h($c['name']) ?><?= $noExam ? ' (no formal exams)' : '' ?>
                  </option>
                  <?php endforeach; ?>
                </optgroup>
                <?php endforeach; ?>
              </select>
              <div id="noExamWarning" class="text-warning mt-1" style="font-size:.78rem;display:none">
                <i class="fas fa-exclamation-triangle me-1"></i>
                This class does not have formal exams. Entry will be rejected.
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold" style="font-size:.82rem">Subject *</label>
              <div class="input-group input-group-sm">
                <select name="subject_select" id="subjectSelect" class="form-select form-select-sm">
                  <option value="">— Select or type below —</option>
                </select>
              </div>
              <input type="text" name="subject" id="subjectInput" class="form-control form-control-sm mt-1" required
                     placeholder="Type subject name (or select above)"
                     value="<?= $editEntry ? h($editEntry['subject']) : '' ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold" style="font-size:.82rem">Exam Date *</label>
              <input type="date" name="exam_date" class="form-control form-control-sm" required
                     value="<?= $editEntry ? h($editEntry['exam_date']) : '' ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold" style="font-size:.82rem">Start Time *</label>
              <input type="time" name="start_time" class="form-control form-control-sm" required
                     value="<?= $editEntry ? h($editEntry['start_time']) : '' ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold" style="font-size:.82rem">End Time *</label>
              <input type="time" name="end_time" class="form-control form-control-sm" required
                     value="<?= $editEntry ? h($editEntry['end_time']) : '' ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold" style="font-size:.82rem">Venue / Room</label>
              <input type="text" name="venue" class="form-control form-control-sm"
                     value="<?= $editEntry ? h($editEntry['venue'] ?? '') : '' ?>"
                     placeholder="e.g. Exam Hall A">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold" style="font-size:.82rem">Entry Notes</label>
              <input type="text" name="entry_notes" class="form-control form-control-sm"
                     value="<?= $editEntry ? h($editEntry['notes'] ?? '') : '' ?>"
                     placeholder="Optional note for this exam slot">
            </div>
            <div class="col-12 d-flex gap-2 mt-1">
              <button type="submit" class="btn btn-sm btn-success">
                <i class="fas fa-check me-1"></i><?= $editEntry ? 'Update Entry' : 'Add Entry' ?>
              </button>
              <a href="?ds=<?= $dsId ?>" class="btn btn-sm btn-outline-secondary">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php else: /* ── LIST VIEW ── */ ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
    <select name="wing" class="form-select form-select-sm" style="width:150px">
      <option value="">All Wings</option>
      <?php foreach (['all' => 'All Campuses', 'main' => 'Main Wing', 'montessori' => 'Montessori', 'ilc' => 'ILC'] as $wv => $wl): ?>
      <option value="<?= $wv ?>" <?= $filterWing === $wv ? 'selected' : '' ?>><?= $wl ?></option>
      <?php endforeach; ?>
    </select>
    <select name="year" class="form-select form-select-sm" style="width:130px">
      <option value="">All Years</option>
      <?php foreach ($allYears as $yr): ?>
      <option value="<?= h($yr) ?>" <?= $filterYear === $yr ? 'selected' : '' ?>><?= h($yr) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="form-select form-select-sm" style="width:120px">
      <option value="">All Status</option>
      <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
      <option value="published" <?= $filterStatus === 'published' ? 'selected' : '' ?>>Published</option>
    </select>
    <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
    <?php if ($filterWing || $filterYear || $filterStatus): ?>
    <a href="/portal/admin/exam-datesheet.php" class="btn btn-sm btn-outline-secondary">Clear</a>
    <?php endif; ?>
  </form>
  <button class="btn btn-sm btn-success" data-bs-toggle="collapse" data-bs-target="#createForm">
    <i class="fas fa-plus me-1"></i>Create New Date Sheet
  </button>
</div>

<div class="collapse <?= $showCreate ? 'show' : '' ?>" id="createForm">
  <div class="sec-card mb-3">
    <div class="sec-card-header"><i class="fas fa-plus me-2"></i>New Exam Date Sheet</div>
    <div style="padding:16px">
      <form method="POST">
        <input type="hidden" name="action" value="create_ds">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Title / Exam Name *</label>
            <input type="text" name="title" class="form-control form-control-sm" required
                   placeholder="e.g. Mid-Term Exams 2025-2026">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Examination / Term</label>
            <select name="term" class="form-select form-select-sm">
              <?php foreach ($termOptions as $t): ?>
              <option value="<?= h($t) ?>"><?= h($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Campus / Wing</label>
            <select name="wing" class="form-select form-select-sm">
              <option value="all">All Campuses</option>
              <option value="main">Main Wing</option>
              <option value="montessori">Montessori</option>
              <option value="ilc">ILC</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Academic Year</label>
            <input type="text" name="academic_year" class="form-control form-control-sm"
                   value="<?= $yearNow ?>-<?= $yearNow + 1 ?>" placeholder="2025-2026">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Notes (optional)</label>
            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Brief note">
          </div>
          <div class="col-md-1">
            <button type="submit" class="btn btn-sm btn-success w-100">
              <i class="fas fa-check"></i>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="sec-card">
  <div class="sec-card-header">
    <i class="fas fa-calendar-day me-2"></i>Exam Date Sheets
    <span class="text-muted fw-normal">(<?= count($dateSheets) ?>)</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr>
          <th>Title</th><th>Term</th><th>Wing</th><th>Year</th>
          <th>Status</th><th>Entries</th><th>Created By</th><th>Created</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($dateSheets as $row):
          $wColor = match($row['wing']) {
              'montessori' => '#7c3aed', 'ilc' => '#0891b2', 'main' => '#2563eb', default => '#6b7280'
          };
          $wLabel = match($row['wing']) {
              'montessori' => 'Montessori', 'ilc' => 'ILC', 'main' => 'Main', default => 'All'
          };
        ?>
        <tr>
          <td class="fw-semibold">
            <?= h($row['title']) ?>
            <?php if ($row['notes']): ?>
            <div style="font-size:.75rem;color:#9ca3af"><?= h($row['notes']) ?></div>
            <?php endif; ?>
          </td>
          <td style="font-size:.8rem"><?= h($row['term'] ?? 'General') ?></td>
          <td>
            <span style="font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:20px;
                         background:<?= $wColor ?>;color:#fff">
              <?= $wLabel ?>
            </span>
          </td>
          <td><?= h($row['academic_year']) ?></td>
          <td><?= $row['status'] === 'published'
              ? '<span class="badge bg-success">Published</span>'
              : '<span class="badge bg-warning text-dark">Draft</span>' ?></td>
          <td><span class="badge bg-secondary"><?= $row['entry_count'] ?></span></td>
          <td style="font-size:.8rem"><?= h($row['creator_name']) ?></td>
          <td style="font-size:.78rem;color:#6b7280"><?= fDate($row['created_at']) ?></td>
          <td style="white-space:nowrap">
            <a href="/portal/admin/exam-datesheet.php?ds=<?= $row['id'] ?>"
               class="btn btn-xs btn-primary me-1" style="font-size:.72rem;padding:2px 7px">
              <i class="fas fa-edit"></i> Manage
            </a>
            <a href="/portal/admin/exam-datesheet.php?ds=<?= $row['id'] ?>&print=1"
               target="_blank" class="btn btn-xs btn-outline-secondary me-1" style="font-size:.72rem;padding:2px 7px">
              <i class="fas fa-print"></i>
            </a>
            <form method="POST" style="display:inline"
                  onsubmit="return confirm('Delete this date sheet and all its entries?')">
              <input type="hidden" name="action" value="delete_ds">
              <input type="hidden" name="ds_id" value="<?= $row['id'] ?>">
              <button class="btn btn-xs btn-outline-danger" style="font-size:.72rem;padding:2px 7px">
                <i class="fas fa-trash"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($dateSheets)): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">
          No date sheets yet. Click <strong>Create New Date Sheet</strong> to get started.
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

</div></div></div>
<?php pageFooter(); ?>

<script>
// Class → Subjects mapping from PHP
const classSubjects = <?= json_encode($classSubjectsMap, JSON_UNESCAPED_UNICODE) ?>;

const classSelect   = document.getElementById('classSelect');
const subjectSelect = document.getElementById('subjectSelect');
const subjectInput  = document.getElementById('subjectInput');
const noExamWarn    = document.getElementById('noExamWarning');

function updateSubjects() {
    if (!classSelect || !subjectSelect) return;
    const cid     = classSelect.value;
    const subs    = cid ? (classSubjects[cid] || []) : [];
    const selOpt  = classSelect.options[classSelect.selectedIndex];
    const noExam  = selOpt && selOpt.dataset.noExam === '1';

    // Show no-exam warning
    if (noExamWarn) noExamWarn.style.display = noExam ? 'block' : 'none';

    // Repopulate subject select
    subjectSelect.innerHTML = '<option value="">— Select subject —</option>';
    subs.forEach(s => {
        const o = document.createElement('option');
        o.value = o.textContent = s;
        subjectSelect.appendChild(o);
    });
    subjectSelect.style.display = subs.length ? 'block' : 'none';
}

if (classSelect) {
    classSelect.addEventListener('change', updateSubjects);
    updateSubjects(); // run on load for edit mode
}

if (subjectSelect) {
    subjectSelect.addEventListener('change', function () {
        if (this.value && subjectInput) subjectInput.value = this.value;
    });
}

// Auto-scroll to entry form if it exists
const ef = document.getElementById('entry-form');
if (ef) ef.scrollIntoView({behavior: 'smooth', block: 'start'});
</script>

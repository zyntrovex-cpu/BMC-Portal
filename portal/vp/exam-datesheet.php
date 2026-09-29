<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('vp_main');
$db      = getDB();
$classes = getAllClasses();
$yearNow = (int)date('Y');

// VP Main can only manage 'main' wing date sheets
$managerWing = 'main';

// ── POST handlers ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_ds') {
        $title = trim($_POST['title'] ?? '');
        $year  = trim($_POST['academic_year'] ?? $yearNow);
        $notes = trim($_POST['notes'] ?? '');
        if ($title) {
            $db->prepare('INSERT INTO exam_date_sheets (title,wing,academic_year,notes,created_by) VALUES (?,?,?,?,?)')
               ->execute([$title, $managerWing, $year, $notes, $user['id']]);
            $newId = (int)$db->lastInsertId();
            logActivity($user['id'], 'exam_ds_create', "Created date sheet: \"$title\" (main, $year)");
            setFlash('success', "Date sheet \"$title\" created. Add exam entries below.");
            redirect(url('/portal/vp/exam-datesheet.php') . '?ds=' . $newId);
        }
        setFlash('danger', 'Title is required.');
        redirect(url('/portal/vp/exam-datesheet.php') . '?create=1');
    }

    if ($action === 'update_ds') {
        $dsId  = (int)($_POST['ds_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $year  = trim($_POST['academic_year'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        // Verify ownership (must be main wing)
        $chk = $db->prepare('SELECT id FROM exam_date_sheets WHERE id=? AND wing=?');
        $chk->execute([$dsId, $managerWing]);
        if ($dsId && $title && $chk->fetch()) {
            $db->prepare('UPDATE exam_date_sheets SET title=?,academic_year=?,notes=? WHERE id=?')
               ->execute([$title, $year, $notes, $dsId]);
            logActivity($user['id'], 'exam_ds_update', "Updated date sheet #$dsId: \"$title\"");
            setFlash('success', 'Date sheet updated.');
        }
        redirect(url('/portal/vp/exam-datesheet.php') . '?ds=' . (int)($_POST['ds_id'] ?? 0));
    }

    if ($action === 'toggle_status') {
        $dsId = (int)($_POST['ds_id'] ?? 0);
        $chk  = $db->prepare('SELECT status, title FROM exam_date_sheets WHERE id=? AND wing=?');
        $chk->execute([$dsId, $managerWing]);
        $cur  = $chk->fetch();
        if ($dsId && $cur) {
            $newStatus = ($cur['status'] === 'published') ? 'draft' : 'published';
            $db->prepare('UPDATE exam_date_sheets SET status=? WHERE id=?')->execute([$newStatus, $dsId]);
            logActivity($user['id'], 'exam_ds_publish', "Set \"" . $cur['title'] . "\" to $newStatus");
            setFlash('success', $newStatus === 'published'
                ? 'Date sheet published — now visible to all staff and students.'
                : 'Date sheet reverted to draft.');
            redirect(url('/portal/vp/exam-datesheet.php') . '?ds=' . $dsId);
        }
    }

    if ($action === 'delete_ds') {
        $dsId = (int)($_POST['ds_id'] ?? 0);
        $chk  = $db->prepare('SELECT title FROM exam_date_sheets WHERE id=? AND wing=?');
        $chk->execute([$dsId, $managerWing]);
        $cur  = $chk->fetch();
        if ($dsId && $cur) {
            $db->prepare('DELETE FROM exam_date_sheets WHERE id=?')->execute([$dsId]);
            logActivity($user['id'], 'exam_ds_delete', "Deleted: \"" . $cur['title'] . '"');
            setFlash('success', 'Date sheet deleted.');
            redirect(url('/portal/vp/exam-datesheet.php'));
        }
    }

    if ($action === 'add_entry' || $action === 'update_entry') {
        $dsId      = (int)($_POST['ds_id']     ?? 0);
        $entryId   = (int)($_POST['entry_id']  ?? 0);
        $classId   = (int)($_POST['class_id']  ?? 0) ?: null;
        $subject   = trim($_POST['subject']    ?? '');
        $examDate  = $_POST['exam_date']        ?? '';
        $startTime = $_POST['start_time']       ?? '';
        $endTime   = $_POST['end_time']         ?? '';
        $venue     = trim($_POST['venue']       ?? '');
        $eNotes    = trim($_POST['notes']       ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        // Verify date sheet belongs to main wing
        $chk = $db->prepare('SELECT id FROM exam_date_sheets WHERE id=? AND wing=?');
        $chk->execute([$dsId, $managerWing]);

        if ($dsId && $subject && $examDate && $startTime && $endTime && $chk->fetch()) {
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
            redirect(url('/portal/vp/exam-datesheet.php') . '?ds=' . $dsId);
        }
        setFlash('danger', 'Subject, date, and times are required.');
        redirect(url('/portal/vp/exam-datesheet.php') . '?ds=' . $dsId . '&add=1');
    }

    if ($action === 'delete_entry') {
        $dsId    = (int)($_POST['ds_id']    ?? 0);
        $entryId = (int)($_POST['entry_id'] ?? 0);
        if ($entryId) {
            $db->prepare('DELETE FROM exam_date_sheet_entries WHERE id=?')->execute([$entryId]);
            setFlash('success', 'Entry deleted.');
            redirect(url('/portal/vp/exam-datesheet.php') . '?ds=' . $dsId);
        }
    }

    redirect(url('/portal/vp/exam-datesheet.php'));
}

// ── Determine view ────────────────────────────────────────────────────
$dsId       = (int)($_GET['ds']     ?? 0);
$showAdd    = (bool)($_GET['add']   ?? 0);
$editEntId  = (int)($_GET['edit']   ?? 0);
$showCreate = (bool)($_GET['create']?? 0);

$ds = null; $entries = []; $editEntry = null;

if ($dsId) {
    $r = $db->prepare(
        'SELECT ds.*, u.name AS creator_name
         FROM exam_date_sheets ds JOIN users u ON ds.created_by=u.id
         WHERE ds.id=? AND ds.wing=?'
    );
    $r->execute([$dsId, $managerWing]);
    $ds = $r->fetch();
    if (!$ds) { setFlash('danger','Date sheet not found.'); redirect(url('/portal/vp/exam-datesheet.php')); }

    $eSt = $db->prepare(
        'SELECT e.*, c.name AS class_name
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

$listSt = $db->prepare(
    "SELECT ds.*, u.name AS creator_name,
            (SELECT COUNT(*) FROM exam_date_sheet_entries e WHERE e.date_sheet_id=ds.id) AS entry_count
     FROM exam_date_sheets ds JOIN users u ON ds.created_by=u.id
     WHERE ds.wing=?
     ORDER BY ds.created_at DESC"
);
$listSt->execute([$managerWing]);
$dateSheets = $listSt->fetchAll();

// Only main wing classes for entry selector
$mainClasses = array_filter($classes, fn($c) => !$c['is_ilc'] && !$c['is_montessori']);

pageHead('Exam Date Sheets', 'vp_main');
$links = getVpLinks();
?>
<div class="portal-wrap">
<?php sidebar('vp_main', 'exam-datesheet', $links, $user); ?>
<div class="main-area">
<?php topbar('Exam Date Sheets', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if ($dsId && $ds): ?>

<div class="d-flex align-items-center mb-3 gap-2 flex-wrap">
  <a href="<?= url('/portal/vp/exam-datesheet.php') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i>All Date Sheets
  </a>
  <span class="text-muted" style="font-size:.84rem">&rsaquo; <?= h($ds['title']) ?></span>
  <?= $ds['status']==='published'
    ? '<span class="badge bg-success ms-1">Published</span>'
    : '<span class="badge bg-warning text-dark ms-1">Draft</span>' ?>
</div>

<div class="row g-3">
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
            <label class="form-label fw-semibold" style="font-size:.82rem">Campus / Wing</label>
            <input type="text" class="form-control form-control-sm" value="Main Wing" readonly>
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
          <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-save me-1"></i>Save Header</button>
        </form>
        <hr>
        <form method="POST" onsubmit="return confirm('Change publish status?')">
          <input type="hidden" name="action" value="toggle_status">
          <input type="hidden" name="ds_id" value="<?= $ds['id'] ?>">
          <?php if ($ds['status']==='published'): ?>
          <button type="submit" class="btn btn-sm btn-outline-warning w-100"><i class="fas fa-eye-slash me-1"></i>Revert to Draft</button>
          <?php else: ?>
          <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-globe me-1"></i>Publish Date Sheet</button>
          <?php endif; ?>
        </form>
        <div class="mt-2 text-muted" style="font-size:.75rem">
          Created by <?= h($ds['creator_name']) ?> &mdash; <?= fDate($ds['created_at']) ?>
        </div>
        <hr>
        <form method="POST" onsubmit="return confirm('Delete entire date sheet and all its entries?')">
          <input type="hidden" name="action" value="delete_ds">
          <input type="hidden" name="ds_id" value="<?= $ds['id'] ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-trash me-1"></i>Delete Date Sheet</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="sec-card">
      <div class="sec-card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>Exam Schedule <span class="text-muted fw-normal">(<?= count($entries) ?> entries)</span></span>
        <a href="<?= url('/portal/vp/exam-datesheet.php').'?ds='.$dsId.'&add=1' ?>"
           class="btn btn-sm btn-success"><i class="fas fa-plus me-1"></i>Add Entry</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.82rem">
          <thead class="table-light">
            <tr><th>Date</th><th>Day</th><th>Class</th><th>Subject</th><th>Start</th><th>End</th><th>Venue</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($entries as $e): ?>
            <tr class="<?= $e['id']==$editEntId?'table-warning':'' ?>">
              <td style="white-space:nowrap"><?= date('d M Y', strtotime($e['exam_date'])) ?></td>
              <td><?= date('D', strtotime($e['exam_date'])) ?></td>
              <td><?= $e['class_name'] ? h($e['class_name']) : '<span class="text-muted" style="font-size:.75rem">All</span>' ?></td>
              <td class="fw-semibold"><?= h($e['subject']) ?></td>
              <td><?= date('h:i A', strtotime($e['start_time'])) ?></td>
              <td><?= date('h:i A', strtotime($e['end_time'])) ?></td>
              <td style="font-size:.78rem"><?= h($e['venue'] ?: '—') ?></td>
              <td style="white-space:nowrap">
                <a href="<?= url('/portal/vp/exam-datesheet.php').'?ds='.$dsId.'&edit='.$e['id'].'&add=1' ?>"
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
            <tr><td colspan="8" class="text-center text-muted py-4">No entries yet — click "Add Entry" to begin.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($showAdd): ?>
    <div class="sec-card mt-3">
      <div class="sec-card-header">
        <i class="fas fa-<?= $editEntry?'edit':'plus' ?> me-2"></i><?= $editEntry?'Edit':'Add' ?> Exam Entry
      </div>
      <div style="padding:18px">
        <form method="POST">
          <input type="hidden" name="action" value="<?= $editEntry?'update_entry':'add_entry' ?>">
          <input type="hidden" name="ds_id" value="<?= $dsId ?>">
          <?php if ($editEntry): ?>
          <input type="hidden" name="entry_id" value="<?= $editEntry['id'] ?>">
          <?php endif; ?>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label fw-semibold" style="font-size:.82rem">Class <span class="text-muted fw-normal">(blank = all classes)</span></label>
              <select name="class_id" class="form-select form-select-sm">
                <option value="">— All Classes —</option>
                <?php foreach ($mainClasses as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($editEntry && (int)$editEntry['class_id']===$c['id'])?'selected':'' ?>>
                  <?= h($c['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold" style="font-size:.82rem">Subject *</label>
              <input type="text" name="subject" class="form-control form-control-sm" required
                     value="<?= $editEntry?h($editEntry['subject']):'' ?>" placeholder="e.g. Mathematics">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold" style="font-size:.82rem">Exam Date *</label>
              <input type="date" name="exam_date" class="form-control form-control-sm" required
                     value="<?= $editEntry?h($editEntry['exam_date']):'' ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold" style="font-size:.82rem">Start Time *</label>
              <input type="time" name="start_time" class="form-control form-control-sm" required
                     value="<?= $editEntry?h($editEntry['start_time']):'' ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold" style="font-size:.82rem">End Time *</label>
              <input type="time" name="end_time" class="form-control form-control-sm" required
                     value="<?= $editEntry?h($editEntry['end_time']):'' ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold" style="font-size:.82rem">Venue / Room</label>
              <input type="text" name="venue" class="form-control form-control-sm"
                     value="<?= $editEntry?h($editEntry['venue']??''):'' ?>" placeholder="e.g. Exam Hall A">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold" style="font-size:.82rem">Notes</label>
              <input type="text" name="notes" class="form-control form-control-sm"
                     value="<?= $editEntry?h($editEntry['notes']??''):'' ?>" placeholder="Optional note">
            </div>
            <div class="col-12 d-flex gap-2 mt-1">
              <button type="submit" class="btn btn-sm btn-success">
                <i class="fas fa-check me-1"></i><?= $editEntry?'Update Entry':'Add Entry' ?>
              </button>
              <a href="<?= url('/portal/vp/exam-datesheet.php').'?ds='.$dsId ?>" class="btn btn-sm btn-outline-secondary">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>

<div class="d-flex justify-content-end mb-3">
  <button class="btn btn-sm btn-success" data-bs-toggle="collapse" data-bs-target="#createForm">
    <i class="fas fa-plus me-1"></i>Create New Date Sheet
  </button>
</div>

<div class="collapse <?= $showCreate?'show':'' ?>" id="createForm">
  <div class="sec-card mb-3">
    <div class="sec-card-header"><i class="fas fa-plus me-2"></i>New Exam Date Sheet (Main Wing)</div>
    <div style="padding:16px">
      <form method="POST">
        <input type="hidden" name="action" value="create_ds">
        <div class="row g-2 align-items-end">
          <div class="col-md-5">
            <label class="form-label fw-semibold" style="font-size:.82rem">Title / Exam Name *</label>
            <input type="text" name="title" class="form-control form-control-sm" required
                   placeholder="e.g. Mid-Term Exams 2025-2026">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Academic Year</label>
            <input type="text" name="academic_year" class="form-control form-control-sm"
                   value="<?= $yearNow ?>-<?= $yearNow+1 ?>" placeholder="2025-2026">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Notes (optional)</label>
            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Brief note">
          </div>
          <div class="col-md-1">
            <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-check"></i></button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="sec-card">
  <div class="sec-card-header"><span><i class="fas fa-calendar-day me-2"></i>Main Wing Exam Date Sheets (<?= count($dateSheets) ?>)</span></div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light">
        <tr><th>Title</th><th>Year</th><th>Status</th><th>Entries</th><th>Created By</th><th>Created</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($dateSheets as $row): ?>
        <tr>
          <td class="fw-semibold">
            <?= h($row['title']) ?>
            <?php if ($row['notes']): ?><div style="font-size:.75rem;color:#9ca3af"><?= h($row['notes']) ?></div><?php endif; ?>
          </td>
          <td><?= h($row['academic_year']) ?></td>
          <td><?= $row['status']==='published'
              ? '<span class="badge bg-success">Published</span>'
              : '<span class="badge bg-warning text-dark">Draft</span>' ?></td>
          <td><span class="badge bg-secondary"><?= $row['entry_count'] ?></span></td>
          <td style="font-size:.8rem"><?= h($row['creator_name']) ?></td>
          <td style="font-size:.78rem;color:#6b7280"><?= fDate($row['created_at']) ?></td>
          <td style="white-space:nowrap">
            <a href="<?= url('/portal/vp/exam-datesheet.php').'?ds='.$row['id'] ?>"
               class="btn btn-xs btn-primary me-1" style="font-size:.72rem;padding:2px 7px">
              <i class="fas fa-edit"></i> Manage
            </a>
            <form method="POST" style="display:inline"
                  onsubmit="return confirm('Delete this date sheet and all its entries?')">
              <input type="hidden" name="action" value="delete_ds">
              <input type="hidden" name="ds_id" value="<?= $row['id'] ?>">
              <button class="btn btn-xs btn-outline-danger" style="font-size:.72rem;padding:2px 7px">Del</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($dateSheets)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No date sheets yet for Main Wing.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

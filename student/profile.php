<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user    = requireAuth('student');
$db      = getDB();
$student = getStudentByUserId($user['id']);
if (!$student) { setFlash('danger','Student record not found.'); redirect('/index.php'); }

// Handle profile change request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $field    = $_POST['field_name']  ?? '';
    $newValue = trim($_POST['new_value'] ?? '');
    $allowed  = ['phone','address','parent_name','parent_phone'];

    if (in_array($field, $allowed) && $newValue) {
        // Check no pending request for same field
        $check = $db->prepare("SELECT id FROM profile_change_requests WHERE student_id = ? AND field = ? AND status = 'pending'");
        $check->execute([$student['id'], $field]);
        if ($check->fetch()) {
            setFlash('warning','A pending request for this field already exists.');
        } else {
            $old = $student[$field] ?? '';
            $db->prepare('INSERT INTO profile_change_requests (student_id, field, old_value, new_value, status) VALUES (?,?,?,?,?)')
               ->execute([$student['id'], $field, $old, $newValue, 'pending']);
            logActivity($user['id'], 'profile_change_request', "Requested change: $field");
            setFlash('success','Change request submitted. Pending admin approval.');
        }
    } else {
        setFlash('danger','Invalid request.');
    }
    redirect('/student/profile.php');
}

// Pending requests
$pendingSt = $db->prepare("SELECT * FROM profile_change_requests WHERE student_id = ? ORDER BY created_at DESC");
$pendingSt->execute([$student['id']]);
$pending = $pendingSt->fetchAll();
$pendingFields = [];
foreach ($pending as $r) {
    if ($r['status'] === 'pending') $pendingFields[] = $r['field_name'];
}

pageHead('My Profile', 'student');
$links = [
    ['href'=>'/student/dashboard.php','icon'=>'<i class="fas fa-home"></i>','label'=>'Dashboard','key'=>'dashboard'],
    ['href'=>'/student/results.php','icon'=>'<i class="fas fa-chart-bar"></i>','label'=>'My Results','key'=>'results'],
    ['href'=>'/student/attendance.php','icon'=>'<i class="fas fa-calendar-check"></i>','label'=>'Attendance','key'=>'attendance'],
    ['href'=>'/student/timetable.php','icon'=>'<i class="fas fa-table"></i>','label'=>'My Timetable','key'=>'timetable'],
    ['href'=>'/student/notices.php','icon'=>'<i class="fas fa-bell"></i>','label'=>'Notices','key'=>'notices'],
];
?>
<div class="portal-wrap">
<?php sidebar('student', 'profile', $links, $user); ?>
<div class="main-area">
<?php topbar('My Profile', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<div class="row g-3">
  <!-- Read-only info -->
  <div class="col-md-5">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-id-card me-2"></i>Student Information</div>
      <div style="padding:16px">
        <table class="table table-sm mb-0" style="font-size:.88rem">
          <tr><th style="width:40%;color:#6b7280">Name</th><td><?= h($student['name']) ?></td></tr>
          <tr><th style="color:#6b7280">Roll No</th><td><?= h($student['roll_no']) ?></td></tr>
          <tr><th style="color:#6b7280">Class</th><td><?= h($student['class_name']) ?></td></tr>
          <tr><th style="color:#6b7280">Email</th><td><?= h($student['email'] ?: '—') ?></td></tr>
          <tr><th style="color:#6b7280">DOB</th><td><?= fDate($student['dob'] ?? '') ?></td></tr>
          <tr><th style="color:#6b7280">Admission</th><td><?= fDate($student['admission_date'] ?? '') ?></td></tr>
        </table>
      </div>
    </div>

    <?php if (!empty($pending)): ?>
    <div class="sec-card mt-3">
      <div class="sec-card-header"><i class="fas fa-clock me-2"></i>Change Requests</div>
      <div style="padding:12px 16px">
        <?php foreach ($pending as $r):
          $statusClass = match($r['status']) { 'approved'=>'success','rejected'=>'danger',default=>'warning' };
        ?>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="font-size:.84rem">
          <div>
            <strong><?= h(ucwords(str_replace('_',' ',$r['field_name']))) ?></strong>
            <div style="color:#6b7280;font-size:.78rem"><?= h($r['new_value']) ?></div>
          </div>
          <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($r['status']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Editable fields -->
  <div class="col-md-7">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-edit me-2"></i>Edit Profile
        <small class="ms-2 opacity-75" style="font-weight:400">(changes require admin approval)</small>
      </div>
      <div style="padding:16px">
        <?php
        $editFields = [
            'phone'        => ['label'=>'Phone','type'=>'tel'],
            'address'      => ['label'=>'Address','type'=>'text'],
            'parent_name'  => ['label'=>'Parent/Guardian Name','type'=>'text'],
            'parent_phone' => ['label'=>'Parent Phone','type'=>'tel'],
        ];
        foreach ($editFields as $field => $meta):
            $isPending = in_array($field, $pendingFields);
        ?>
        <form method="POST" class="mb-3">
          <input type="hidden" name="field_name" value="<?= h($field) ?>">
          <label class="form-label fw-semibold" style="font-size:.85rem">
            <?= h($meta['label']) ?>
            <?php if ($isPending): ?>
              <span class="badge bg-warning text-dark ms-1" style="font-size:.7rem">Pending</span>
            <?php endif; ?>
          </label>
          <div class="input-group input-group-sm">
            <input type="<?= $meta['type'] ?>" name="new_value" class="form-control"
                   placeholder="Current: <?= h(($student[$field] ?? '') ?: 'Not set') ?>"
                   <?= $isPending ? 'disabled' : '' ?>>
            <?php if (!$isPending): ?>
              <button type="submit" class="btn btn-outline-primary">Request Change</button>
            <?php else: ?>
              <span class="input-group-text text-warning"><i class="fas fa-clock"></i></span>
            <?php endif; ?>
          </div>
          <?php if (!$isPending): ?>
            <div style="font-size:.75rem;color:#9ca3af;margin-top:3px">Current: <?= h(($student[$field] ?? '') ?: 'Not set') ?></div>
          <?php endif; ?>
        </form>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

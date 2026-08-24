<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user    = requireAuth('student');
$db      = getDB();
$student = getStudentByUserId($user['id']);
if (!$student) { setFlash('danger','Student record not found.'); redirect('/portal/index.php'); }

// Handle profile change request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'field_request';

    // Direct email update (no approval needed)
    if ($action === 'update_email') {
        $newEmail = trim($_POST['new_email'] ?? '');
        if (!$newEmail || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            setFlash('danger', 'Please enter a valid email address.');
        } else {
            $ck = $db->prepare('SELECT id FROM users WHERE email=? AND id != ?');
            $ck->execute([$newEmail, $user['id']]);
            if ($ck->fetch()) {
                setFlash('danger', 'That email is already used by another account.');
            } else {
                $db->prepare('UPDATE users SET email=? WHERE id=?')->execute([$newEmail, $user['id']]);
                $_SESSION['user']['email'] = $newEmail;
                logActivity($user['id'], 'email_update', 'Student updated their email');
                setFlash('success', 'Email updated successfully.');
            }
        }
        redirect('/portal/student/profile.php');
    }

    $field    = $_POST['field_name']  ?? '';
    $newValue = trim($_POST['new_value'] ?? '');
    $allowed  = ['phone','address','permanent_address','parent_name','parent_phone','whatsapp_no','emergency_phone'];

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
    redirect('/portal/student/profile.php');
}

// Pending requests
$pendingSt = $db->prepare("SELECT * FROM profile_change_requests WHERE student_id = ? ORDER BY created_at DESC");
$pendingSt->execute([$student['id']]);
$pending = $pendingSt->fetchAll();
$pendingFields = [];
foreach ($pending as $r) {
    if ($r['status'] === 'pending') $pendingFields[] = $r['field'];
}

// Fetch house info (houses table may not exist on older installations)
$houseName  = null;
$houseColor = '#6b7280';
try {
    $houseSt = $db->prepare(
        'SELECT h.name AS house_name, h.color AS house_color
         FROM students s
         LEFT JOIN houses h ON s.house_id = h.id
         WHERE s.id = ?'
    );
    $houseSt->execute([$student['id']]);
    $houseRow   = $houseSt->fetch();
    $houseName  = $houseRow['house_name']  ?? null;
    $houseColor = $houseRow['house_color'] ?? '#6b7280';
} catch (Exception $e) {}

// Fetch warnings (student_warnings table may not exist on older installations)
$warnings    = [];
$hasWarnings = false;
try {
    $warnSt = $db->prepare(
        'SELECT w.severity, w.reason, w.created_at, u.name AS given_by_name
         FROM student_warnings w
         LEFT JOIN users u ON w.given_by = u.id
         WHERE w.student_id = ?
         ORDER BY w.created_at DESC'
    );
    $warnSt->execute([$student['id']]);
    $warnings    = $warnSt->fetchAll();
    $hasWarnings = !empty($warnings);
} catch (Exception $e) {}

// Ordinal helper (1st, 2nd, 3rd …)
function ordinal(int $n): string {
    $s = ['th','st','nd','rd'];
    $v = $n % 100;
    return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
}

pageHead('My Profile', 'student');
$links = getStudentLinks();
?>
<div class="portal-wrap">
<?php sidebar('student', 'profile', $links, $user); ?>
<div class="main-area">
<?php topbar('My Profile', $user); ?>
<div class="page-content">
<?= flashHtml() ?>
<style>
.bio-section-label {
  font-size:.72rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;
  color:var(--accent);background:var(--sidebar-bg,#f8fafc);
  padding:5px 12px;margin:0 -0px 4px;border-left:3px solid var(--accent);
}
</style>

<div class="row g-3">
  <!-- Read-only info -->
  <div class="col-md-6">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-id-card me-2"></i>Student Information</div>
      <div style="padding:16px">
        <?php if ($hasWarnings): ?>
        <div class="mb-2 d-flex align-items-center gap-2">
          <i class="fas fa-exclamation-triangle text-danger"></i>
          <span class="student-name-warned"><?= h($student['name']) ?></span>
          <span class="badge bg-danger" style="font-size:.7rem"><?= count($warnings) ?> warning<?= count($warnings)>1?'s':'' ?></span>
        </div>
        <?php endif; ?>
        <?php if ($houseName): ?>
        <div class="mb-2">
          <span class="badge-house" style="background:<?= h($houseColor) ?>">
            <i class="fas fa-shield-alt"></i> <?= h($houseName) ?> House
          </span>
        </div>
        <?php endif; ?>
        <?php
        // Helper: display a bio row only if value is set
        function bioRow(string $label, $value, string $extra = ''): void {
            if ($value === null || $value === '') return;
            echo '<tr>';
            echo '<th style="width:42%;color:#6b7280;font-weight:500;vertical-align:top">' . htmlspecialchars($label) . '</th>';
            echo '<td style="word-break:break-word">' . ($extra ?: htmlspecialchars($value)) . '</td>';
            echo '</tr>';
        }
        ?>

        <!-- ── PERSONAL INFORMATION ───────────────────────── -->
        <div class="bio-section-label">Personal Information</div>
        <table class="table table-sm mb-2" style="font-size:.86rem">
          <tr><th style="width:42%;color:#6b7280;font-weight:500">Name</th>
              <td class="<?= $hasWarnings ? 'student-name-warned' : '' ?>"><?= h($student['name']) ?></td></tr>
          <?php bioRow('Father Name', $student['father_name'] ?? null); ?>
          <?php bioRow('Roll No', $student['roll_no'] ?? null); ?>
          <?php bioRow('GR No', $student['gr_no'] ?? null); ?>
          <?php bioRow('CNIC', $student['cnic'] ?? null); ?>
          <?php bioRow('Date of Birth', fDate($student['dob'] ?? '')); ?>
          <?php bioRow('Gender', isset($student['gender']) ? ucfirst($student['gender']) : null); ?>
          <?php if (!empty($student['blood_group'])): ?>
          <tr><th style="color:#6b7280;font-weight:500">Blood Group</th>
              <td><span class="badge bg-danger"><?= h($student['blood_group']) ?></span></td></tr>
          <?php endif; ?>
          <?php bioRow('Nationality', $student['nationality'] ?? null); ?>
          <?php if (!empty($student['religion'])): ?>
          <?php bioRow('Religion', $student['religion'] . (!empty($student['sect']) ? ' / '.$student['sect'] : '')); ?>
          <?php endif; ?>
          <?php bioRow('Domicile', $student['domicile'] ?? null); ?>
          <?php bioRow('Child Order', isset($student['child_order']) && $student['child_order'] !== null ? ordinal((int)$student['child_order']).' child' : null); ?>
        </table>

        <!-- ── ACADEMIC INFORMATION ───────────────────────── -->
        <div class="bio-section-label">Academic Information</div>
        <table class="table table-sm mb-2" style="font-size:.86rem">
          <tr><th style="width:42%;color:#6b7280;font-weight:500">Class</th><td><?= h($student['class_name']) ?></td></tr>
          <?php bioRow('Academic Group', $student['academic_group'] ?? null); ?>
          <?php bioRow('Category', $student['category'] ?? null); ?>
          <?php if ($houseName): ?>
          <tr><th style="color:#6b7280;font-weight:500">House</th>
              <td><span class="badge-house" style="background:<?= h($houseColor) ?>;font-size:.75rem">
                <i class="fas fa-shield-alt"></i> <?= h($houseName) ?>
              </span></td></tr>
          <?php endif; ?>
          <?php bioRow('KuickPay ID', $student['kuickpay_id'] ?? null); ?>
          <?php bioRow('Last School', $student['last_school'] ?? null); ?>
          <?php bioRow('Admission Date', fDate($student['admission_date'] ?? '')); ?>
        </table>

        <!-- ── CONTACT INFORMATION ────────────────────────── -->
        <div class="bio-section-label">Contact Information</div>
        <table class="table table-sm mb-2" style="font-size:.86rem">
          <tr><th style="width:42%;color:#6b7280;font-weight:500">Email</th>
              <td>
                <?= h($student['email'] ?: '—') ?>
                <button class="btn btn-xs btn-outline-secondary ms-1"
                        style="font-size:.65rem;padding:1px 5px"
                        data-bs-toggle="modal" data-bs-target="#emailModal"
                        title="Change email"><i class="fas fa-edit"></i></button>
              </td></tr>
          <?php bioRow('Phone', $student['phone'] ?? null); ?>
          <?php bioRow('WhatsApp', $student['whatsapp_no'] ?? null); ?>
          <?php bioRow('Emergency Phone', $student['emergency_phone'] ?? null); ?>
          <?php bioRow('Present Address', $student['address'] ?? null); ?>
          <?php bioRow('Permanent Address', $student['permanent_address'] ?? null); ?>
        </table>

        <!-- ── PARENT / GUARDIAN ──────────────────────────── -->
        <div class="bio-section-label">Parent / Guardian</div>
        <table class="table table-sm mb-2" style="font-size:.86rem">
          <?php bioRow('Parent Name', $student['parent_name'] ?? null); ?>
          <?php bioRow('Father Occupation', $student['father_occupation'] ?? null); ?>
          <?php bioRow('Parent Phone', $student['parent_phone'] ?? null); ?>
          <?php bioRow('Parent Email', $student['parent_email'] ?? null); ?>
        </table>

        <!-- ── SKILLS / SPORTS / AWARDS ───────────────────── -->
        <?php if (!empty($student['skills']) || !empty($student['sports']) || !empty($student['awards'])): ?>
        <div class="bio-section-label">Extracurricular</div>
        <div class="px-1 pb-2" style="font-size:.83rem">
          <?php if (!empty($student['skills'])): ?>
          <div class="mb-1"><span class="text-muted me-1">Skills:</span>
            <?php foreach (explode(',', $student['skills']) as $sk): ?>
              <span class="badge bg-secondary me-1"><?= h(trim($sk)) ?></span>
            <?php endforeach; ?></div>
          <?php endif; ?>
          <?php if (!empty($student['sports'])): ?>
          <div class="mb-1"><span class="text-muted me-1">Sports:</span>
            <?php foreach (explode(',', $student['sports']) as $sp): ?>
              <span class="badge bg-info text-dark me-1"><?= h(trim($sp)) ?></span>
            <?php endforeach; ?></div>
          <?php endif; ?>
          <?php if (!empty($student['awards'])): ?>
          <div><span class="text-muted me-1">Awards:</span> <?= h($student['awards']) ?></div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ── MEDICAL ─────────────────────────────────────── -->
        <?php if (!empty($student['medical_info'])): ?>
        <div class="bio-section-label">Medical Information</div>
        <div class="px-1 pb-2" style="font-size:.83rem;color:#374151"><?= nl2br(h($student['medical_info'])) ?></div>
        <?php endif; ?>
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
            <strong><?= h(ucwords(str_replace('_',' ',$r['field']))) ?></strong>
            <div style="color:#6b7280;font-size:.78rem"><?= h($r['new_value']) ?></div>
          </div>
          <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($r['status']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($hasWarnings): ?>
    <div class="sec-card mt-3">
      <div class="sec-card-header" style="color:#dc2626">
        <span><i class="fas fa-exclamation-triangle me-2"></i>Active Warnings</span>
        <span class="badge bg-danger"><?= count($warnings) ?></span>
      </div>
      <div style="padding:0">
        <?php foreach ($warnings as $w):
          $sevClass = match($w['severity']) {
            'high'   => 'danger',
            'medium' => 'warning',
            default  => 'info',
          };
          $rowBg = match($w['severity']) {
            'high'   => '#fff5f5',
            'medium' => '#fffbeb',
            default  => '#f0f9ff',
          };
        ?>
        <div class="px-3 py-2 border-bottom d-flex gap-2 align-items-start"
             style="background:<?= $rowBg ?>;font-size:.84rem">
          <span class="badge bg-<?= $sevClass ?> text-<?= $w['severity']==='medium'?'dark':'white' ?> flex-shrink-0 mt-1">
            <?= ucfirst(h($w['severity'])) ?>
          </span>
          <div style="flex:1;min-width:0">
            <div><?= h($w['reason']) ?></div>
            <div style="font-size:.75rem;color:var(--t3);margin-top:2px">
              By <?= h($w['given_by_name'] ?? 'Staff') ?> &bull; <?= fDate($w['created_at']) ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- Editable fields -->
  <div class="col-md-6">
    <div class="sec-card">
      <div class="sec-card-header"><i class="fas fa-edit me-2"></i>Edit Profile
        <small class="ms-2 opacity-75" style="font-weight:400">(changes require admin approval)</small>
      </div>
      <div style="padding:16px">
        <?php
        $editFields = [
            'phone'             => ['label'=>'Phone',              'type'=>'tel'],
            'whatsapp_no'       => ['label'=>'WhatsApp',           'type'=>'tel'],
            'emergency_phone'   => ['label'=>'Emergency Phone',    'type'=>'tel'],
            'address'           => ['label'=>'Present Address',    'type'=>'text'],
            'permanent_address' => ['label'=>'Permanent Address',  'type'=>'text'],
            'parent_phone'      => ['label'=>'Parent Phone',       'type'=>'tel'],
        ];
        // Only show fields that exist as columns in this installation
        $stuKeys = array_keys($student);
        $editFields = array_filter($editFields, fn($k) => in_array($k, $stuKeys), ARRAY_FILTER_USE_KEY);
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

<!-- Email Change Modal -->
<div class="modal fade" id="emailModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title"><i class="fas fa-envelope me-2"></i>Change Email Address</h6>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="update_email">
        <div class="modal-body">
          <p style="font-size:.85rem;color:#6b7280">Current email: <strong><?= h($student['email'] ?: 'Not set') ?></strong></p>
          <label class="form-label fw-semibold" style="font-size:.85rem">New Email Address</label>
          <input type="email" name="new_email" class="form-control" placeholder="your@email.com" required>
          <div class="form-text mt-1">This change takes effect immediately.</div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i>Update Email</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>

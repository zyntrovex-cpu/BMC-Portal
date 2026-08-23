<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/config.php';

$user = requireAuth('admin');
$db   = getDB();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $name   = trim($_POST['name']    ?? '');
        $userId = trim($_POST['user_id'] ?? '');
        $email  = trim($_POST['email']   ?? '');
        $role   = $_POST['role'] ?? '';

        if ($name && $userId && in_array($role, ['student','teacher','admin','finance','ilc_vp','student_affairs','vp_main','wing_head'])) {
            $check = $db->prepare('SELECT id FROM users WHERE user_id = ?');
            $check->execute([$userId]);
            if ($check->fetch()) {
                setFlash('danger', 'User ID already exists.');
            } else {
                // Generate a random secure temp password — user sets their own via email link
                $tempPass = bin2hex(random_bytes(16));
                $hash     = password_hash($tempPass, PASSWORD_BCRYPT);

                $db->prepare('INSERT INTO users (user_id,name,email,password,role,status) VALUES (?,?,?,?,?,?)')
                   ->execute([$userId, $name, $email ?: null, $hash, $role, 'active']);
                $newId = (int)$db->lastInsertId();

                if ($role === 'student') {
                    $db->prepare('INSERT INTO students (user_id, roll_no, class_id, admission_date) VALUES (?,?,?,CURDATE())')
                       ->execute([$newId, $userId, $_POST['class_id'] ?: null]);
                } elseif ($role === 'teacher') {
                    $phone    = trim($_POST['phone'] ?? '');
                    $joinDate = $_POST['join_date'] ?? date('Y-m-d');
                    $db->prepare('INSERT INTO teachers (user_id, emp_id, subject_id, qualification, phone, join_date) VALUES (?,?,?,?,?,?)')
                       ->execute([$newId, $userId, $_POST['subject_id'] ?: null, trim($_POST['qualification'] ?? ''), $phone ?: null, $joinDate]);
                }

                // Generate set-password link and send welcome email
                $token   = bin2hex(random_bytes(32));
                $expires = gmdate('Y-m-d H:i:s', time() + 86400); // 24 hours — stored as UTC
                $db->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')
                   ->execute([$token, $expires, $newId]);

                $appUrl = getSetting('app_url', '');
                if (!$appUrl) {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $appUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
                }
                $setPassLink = rtrim($appUrl, '/') . '/reset-password.php?token=' . $token;

                $sent = false;
                if ($email) {
                    require_once __DIR__ . '/../includes/mailer.php';
                    $roleLabel = ucfirst($role);
                    $body = mailTemplate('Welcome to BMC Portal — Set Your Password',
                        "<p>Hi <strong>" . h($name) . "</strong>,</p>
                        <p>Your BMC Portal account has been created. Here are your login details:</p>
                        <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:.9rem'>
                          <tr><td style='padding:8px;border:1px solid #e5e7eb;color:#6b7280'>Portal</td><td style='padding:8px;border:1px solid #e5e7eb'><strong>$roleLabel Portal</strong></td></tr>
                          <tr><td style='padding:8px;border:1px solid #e5e7eb;color:#6b7280'>User ID</td><td style='padding:8px;border:1px solid #e5e7eb'><strong>$userId</strong></td></tr>
                        </table>
                        <p>Click the button below to set your password. This link expires in <strong>24 hours</strong>.</p>
                        <p><a class='btn' href='$setPassLink'>Set My Password</a></p>
                        <p style='color:#9ca3af;font-size:.85rem'>If you did not expect this email, please ignore it.</p>"
                    );
                    $sent = sendMail($email, $name, 'Welcome to BMC Portal — Set Your Password', $body);
                }

                // Seed default permissions (all granted) for roles that have them
                $rolePermsNew = getRolePermissions($role);
                if ($rolePermsNew) {
                    try {
                        $insP = $db->prepare('INSERT IGNORE INTO user_permissions (user_id, permission, granted) VALUES (?,?,1)');
                        foreach ($rolePermsNew as $perm => $def) { $insP->execute([$newId, $perm]); }
                    } catch (Exception $e) {}
                }

                logActivity($user['id'], 'user_create', "Created $userId ($role)");

                if ($sent) {
                    setFlash('success', "User $userId created. A set-password email was sent to $email.");
                } else {
                    // Store link in session to show on page
                    $_SESSION['new_user_link'] = $setPassLink;
                    $_SESSION['new_user_id']   = $userId;
                    setFlash('info', "User $userId created. No email sent — copy the set-password link below.");
                }
            }
        } else {
            setFlash('danger', 'Name, User ID, and Role are required.');
        }
    }

    if ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id = ?")->execute([$id]);
        setFlash('success', 'Status updated.');
    }

    if ($action === 'delete_user') {
        $id = (int)$_POST['id'];
        if ($id !== $user['id']) {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            logActivity($user['id'], 'user_delete', "Deleted user #$id");
            setFlash('success', 'User deleted.');
        }
    }

    if ($action === 'edit_user') {
        $id      = (int)$_POST['id'];
        $name    = trim($_POST['name']  ?? '');
        $email   = trim($_POST['email'] ?? '');
        $uRole   = $_POST['role']       ?? '';

        if ($id && $name) {
            $db->prepare('UPDATE users SET name=?, email=? WHERE id=?')
               ->execute([$name, $email ?: null, $id]);

            if ($uRole === 'student' && isset($_POST['class_id'])) {
                $cId = (int)$_POST['class_id'] ?: null;
                $db->prepare('UPDATE students SET class_id=? WHERE user_id=?')->execute([$cId, $id]);
            }

            // Save permissions if role has them
            $rolePerms = getRolePermissions($uRole);
            if ($rolePerms) {
                try {
                    $db->prepare('DELETE FROM user_permissions WHERE user_id=?')->execute([$id]);
                    $ins        = $db->prepare('INSERT INTO user_permissions (user_id, permission, granted) VALUES (?,?,?)');
                    $submitted  = $_POST['perms'] ?? [];
                    foreach ($rolePerms as $perm => $def) {
                        $ins->execute([$id, $perm, isset($submitted[$perm]) ? 1 : 0]);
                    }
                    // Flush session cache for this user if they are logged in right now
                    if (isset($_SESSION['_perms_uid']) && (int)$_SESSION['_perms_uid'] === $id) {
                        unset($_SESSION['user_perms'], $_SESSION['_perms_uid']);
                    }
                } catch (Exception $e) { /* permissions table may not exist yet */ }
            }

            logActivity($user['id'], 'user_edit', "Edited user #$id ($uRole)");
            setFlash('success', 'User updated successfully.');
        } else {
            setFlash('danger', 'Name is required.');
        }
    }

    if ($action === 'approve_request') {
        $reqId = (int)$_POST['request_id'];
        $req = $db->prepare('SELECT * FROM profile_change_requests WHERE id = ?');
        $req->execute([$reqId]);
        $r = $req->fetch();
        if ($r && in_array($r['field'], ['phone','address','parent_name','parent_phone'])) {
            $db->prepare("UPDATE students SET {$r['field']} = ? WHERE id = ?")->execute([$r['new_value'], $r['student_id']]);
            $db->prepare("UPDATE profile_change_requests SET status='approved' WHERE id = ?")->execute([$reqId]);
            setFlash('success','Profile change approved.');
        }
    }

    if ($action === 'reject_request') {
        $reqId = (int)$_POST['request_id'];
        $db->prepare("UPDATE profile_change_requests SET status='rejected' WHERE id = ?")->execute([$reqId]);
        setFlash('success','Request rejected.');
    }

    if ($action === 'send_reset_link') {
        $id = (int)$_POST['id'];
        $uSt = $db->prepare('SELECT id, name, email, user_id FROM users WHERE id = ?');
        $uSt->execute([$id]);
        $u = $uSt->fetch();
        if ($u) {
            $token   = bin2hex(random_bytes(32));
            $expires = gmdate('Y-m-d H:i:s', time() + 1800); // stored as UTC
            $db->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')
               ->execute([$token, $expires, $id]);

            $appUrl = getSetting('app_url', '');
            if (!$appUrl) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $appUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
            }
            $resetLink = rtrim($appUrl, '/') . '/reset-password.php?token=' . $token;

            $sent = false;
            if ($u['email']) {
                require_once __DIR__ . '/../includes/mailer.php';
                $body = mailTemplate('Reset Your Password',
                    "<p>Hi <strong>" . h($u['name']) . "</strong>,</p>
                    <p>A password reset was requested for your BMC Portal account.</p>
                    <p><a class='btn' href='$resetLink'>Reset My Password</a></p>
                    <p style='color:#9ca3af;font-size:.85rem'>This link expires in 30 minutes.</p>"
                );
                $sent = sendMail($u['email'], $u['name'], 'Reset Your BMC Portal Password', $body);
            }

            if ($sent) {
                setFlash('success', 'Password reset link sent to ' . $u['email']);
            } else {
                $_SESSION['new_user_link'] = $resetLink;
                $_SESSION['new_user_id']   = $u['user_id'];
                setFlash('info', 'No email on file — copy the reset link below.');
            }
        }
    }

    $allowedRoles = ['student','teacher','admin','finance','ilc_vp','student_affairs','vp_main','wing_head'];
    $backRole = in_array($_POST['role_filter'] ?? '', $allowedRoles) ? $_POST['role_filter'] : '';
    redirect('/admin/users.php' . ($backRole ? '?role=' . $backRole : ''));
}

$roleFilter = $_GET['role'] ?? '';
$wingFilter = $_GET['wing'] ?? '';
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 20;
$offset     = ($page - 1) * $perPage;

// Detect whether wing columns exist (wing-migration.sql may not have been run yet)
$hasWingCol = false;
try { $db->query('SELECT wing FROM classes LIMIT 0'); $hasWingCol = true; } catch (Exception $e) {}

// Detect is_ilc / is_montessori as fallback
$hasIlcCol  = false;
$hasMonCol  = false;
if (!$hasWingCol) {
    try { $db->query('SELECT is_ilc FROM classes LIMIT 0');       $hasIlcCol = true; } catch (Exception $e) {}
    try { $db->query('SELECT is_montessori FROM classes LIMIT 0'); $hasMonCol = true; } catch (Exception $e) {}
}

// Wing expression used in SELECT and WHERE
if ($hasWingCol) {
    $wingExpr = "CASE WHEN u.role='student' THEN COALESCE(c.wing,'main')
                      WHEN u.role='teacher' THEN COALESCE(t.wing,'main')
                      ELSE 'main' END";
} elseif ($hasIlcCol) {
    $ilcPart  = "WHEN COALESCE(c.is_ilc,0)=1 THEN 'ilc'" . ($hasMonCol ? " WHEN COALESCE(c.is_montessori,0)=1 THEN 'montessori'" : '');
    $wingExpr = "CASE WHEN u.role='student' THEN (CASE $ilcPart ELSE 'main' END)
                      WHEN u.role='teacher' THEN (CASE WHEN COALESCE(t.is_ilc,0)=1 THEN 'ilc' ELSE 'main' END)
                      ELSE 'main' END";
} else {
    $wingExpr  = "'main'";
    $wingFilter = ''; // can't filter by wing with no column data
}

$whereParts = [];
$params     = [];
if ($roleFilter) {
    $whereParts[] = 'u.role = ?';
    $params[]     = $roleFilter;
}
if ($wingFilter) {
    $whereParts[] = "($wingExpr) = ?";
    $params[]     = $wingFilter;
}
$whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

$countSt = $db->prepare(
    "SELECT COUNT(*) FROM users u
     LEFT JOIN students s ON s.user_id = u.id AND u.role = 'student'
     LEFT JOIN classes c  ON c.id = s.class_id
     LEFT JOIN teachers t ON t.user_id = u.id AND u.role = 'teacher'
     $whereSQL"
);
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();
$pages = (int)ceil($total / $perPage);

$usersSt = $db->prepare(
    "SELECT u.*, c.name AS class_name,
            ($wingExpr) AS user_wing
     FROM users u
     LEFT JOIN students s ON s.user_id = u.id AND u.role = 'student'
     LEFT JOIN classes c  ON c.id = s.class_id
     LEFT JOIN teachers t ON t.user_id = u.id AND u.role = 'teacher'
     $whereSQL ORDER BY u.role, u.name LIMIT $perPage OFFSET $offset"
);
$usersSt->execute($params);
$users = $usersSt->fetchAll();

// Pending profile requests
$pendingSt = $db->prepare(
    'SELECT pcr.*, u.name AS student_name, u.user_id AS student_roll
     FROM profile_change_requests pcr
     JOIN students s ON pcr.student_id = s.id
     JOIN users u ON s.user_id = u.id
     WHERE pcr.status = "pending"
     ORDER BY pcr.created_at DESC'
);
$pendingSt->execute();
$pendingRequests = $pendingSt->fetchAll();

$classes  = getAllClasses();
$subjects = getAllSubjects();

pageHead('Users', 'admin');
$links = getAdminLinks();
?>
<div class="portal-wrap">
<?php sidebar('admin', 'users', $links, $user); ?>
<div class="main-area">
<?php topbar('User Management', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<?php if (!empty($_SESSION['new_user_link'])): ?>
<div class="alert alert-info d-flex align-items-start gap-3" style="border-radius:8px">
  <i class="fas fa-link mt-1"></i>
  <div class="flex-grow-1">
    <strong>Set-password link for <?= h($_SESSION['new_user_id'] ?? '') ?></strong> — copy and send to the user:
    <div class="d-flex gap-2 mt-2">
      <input type="text" id="newUserLink" class="form-control form-control-sm" value="<?= h($_SESSION['new_user_link']) ?>" readonly onclick="this.select()" style="font-size:.78rem">
      <button class="btn btn-sm btn-primary" onclick="var i=document.getElementById('newUserLink');i.select();navigator.clipboard.writeText(i.value)" style="white-space:nowrap">Copy</button>
    </div>
    <div style="font-size:.75rem;color:#6b7280;margin-top:4px">Expires in 24 hours. User clicks this to set their own password.</div>
  </div>
</div>
<?php unset($_SESSION['new_user_link'], $_SESSION['new_user_id']); ?>
<?php endif; ?>

<!-- Pending profile requests -->
<?php if (!empty($pendingRequests)): ?>
<div class="sec-card mb-3 border-warning">
  <div class="sec-card-header text-warning"><i class="fas fa-clock me-2"></i>Pending Profile Change Requests (<?= count($pendingRequests) ?>)</div>
  <div class="table-responsive">
    <table class="table table-sm mb-0" style="font-size:.84rem">
      <thead class="table-light"><tr><th>Student</th><th>Field</th><th>Old Value</th><th>New Value</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($pendingRequests as $r): ?>
        <tr>
          <td><?= h($r['student_name']) ?> <span class="text-muted">(<?= h($r['student_roll']) ?>)</span></td>
          <td><?= h(ucwords(str_replace('_',' ',$r['field']))) ?></td>
          <td><?= h($r['old_value'] ?: '—') ?></td>
          <td class="fw-semibold"><?= h($r['new_value']) ?></td>
          <td><?= fDate($r['created_at']) ?></td>
          <td>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="approve_request">
              <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
              <button class="btn btn-xs btn-success" style="font-size:.75rem;padding:2px 8px">Approve</button>
            </form>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="reject_request">
              <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
              <button class="btn btn-xs btn-danger" style="font-size:.75rem;padding:2px 8px">Reject</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Add user -->
<div class="sec-card mb-3">
  <div class="sec-card-header" data-bs-toggle="collapse" data-bs-target="#addUserForm" style="cursor:pointer">
    <i class="fas fa-user-plus me-2"></i>Add New User <i class="fas fa-chevron-down ms-auto"></i>
  </div>
  <div id="addUserForm" class="collapse">
    <div style="padding:16px">
      <form method="POST" id="addUserFormEl">
        <input type="hidden" name="action" value="add_user">
        <div class="row g-2">
          <div class="col-md-3"><label class="form-label fw-semibold" style="font-size:.82rem">Full Name*</label><input type="text" name="name" class="form-control form-control-sm" required></div>
          <div class="col-md-2"><label class="form-label fw-semibold" style="font-size:.82rem">User ID*</label><input type="text" name="user_id" class="form-control form-control-sm" required></div>
          <div class="col-md-2"><label class="form-label fw-semibold" style="font-size:.82rem">Role*</label>
            <select name="role" id="roleSelect" class="form-select form-select-sm" required onchange="toggleRoleFields(this.value)">
              <option value="">Select</option>
              <option value="student">Student</option>
              <option value="teacher">Teacher</option>
              <option value="admin">Admin</option>
              <option value="finance">Finance</option>
              <option value="ilc_vp">ILC VP</option>
              <option value="student_affairs">Student Affairs</option>
              <option value="vp_main">VP — Main &amp; Montessori</option>
              <option value="wing_head">Wing Head (Montessori)</option>
            </select>
          </div>
          <div class="col-md-3"><label class="form-label fw-semibold" style="font-size:.82rem">Email <small class="text-muted">(for set-password link)</small></label><input type="email" name="email" class="form-control form-control-sm"></div>
          <div id="studentFields" class="col-md-3 d-none">
            <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
            <select name="class_id" class="form-select form-select-sm">
              <option value="">Select class</option>
              <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div id="teacherFields" class="col-md-3 d-none">
            <label class="form-label fw-semibold" style="font-size:.82rem">Subject</label>
            <select name="subject_id" class="form-select form-select-sm">
              <option value="">Select subject</option>
              <?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div id="qualFields" class="col-md-3 d-none">
            <label class="form-label fw-semibold" style="font-size:.82rem">Qualification</label>
            <input type="text" name="qualification" class="form-control form-control-sm">
          </div>
          <div id="teacherPhoneField" class="col-md-2 d-none">
            <label class="form-label fw-semibold" style="font-size:.82rem">Phone</label>
            <input type="tel" name="phone" class="form-control form-control-sm" placeholder="+92...">
          </div>
          <div id="teacherJoinDateField" class="col-md-2 d-none">
            <label class="form-label fw-semibold" style="font-size:.82rem">Join Date</label>
            <input type="date" name="join_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-sm btn-success w-100">Create User</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Users table -->
<div class="sec-card">
  <div class="sec-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-users me-2"></i>All Users (<?= $total ?>)</span>
    <div class="d-flex flex-wrap gap-1">
      <span class="text-muted" style="font-size:.72rem;padding:2px 4px;align-self:center">Role:</span>
      <?php foreach ([''=>'All','student'=>'Student','teacher'=>'Teacher','admin'=>'Admin','finance'=>'Finance','ilc_vp'=>'ILC VP','student_affairs'=>'Stu. Affairs','vp_main'=>'VP Main','wing_head'=>'Wing Head'] as $r => $lbl): ?>
        <a href="?role=<?= $r ?>&wing=<?= urlencode($wingFilter) ?>" class="btn btn-xs <?= $roleFilter===$r?'btn-primary':'btn-outline-secondary' ?>" style="font-size:.72rem;padding:2px 7px"><?= $lbl ?></a>
      <?php endforeach; ?>
      <span class="text-muted ms-2" style="font-size:.72rem;padding:2px 4px;align-self:center">Wing:</span>
      <?php foreach ([''=>'All','main'=>'Main','montessori'=>'Montessori','ilc'=>'ILC'] as $w => $wlbl): ?>
        <a href="?role=<?= urlencode($roleFilter) ?>&wing=<?= $w ?>" class="btn btn-xs <?= $wingFilter===$w?'btn-info':'btn-outline-secondary' ?>" style="font-size:.72rem;padding:2px 7px"><?= $wlbl ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Role</th><th>Wing</th><th>Class</th><th>Email</th><th>Status</th><th>Last Login</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($users as $u):
          $roleBadge = match($u['role']) {
              'student'         => 'primary',
              'teacher'         => 'success',
              'admin'           => 'purple',
              'finance'         => 'warning',
              'ilc_vp'          => 'info',
              'student_affairs' => 'danger',
              'vp_main'         => 'dark',
              'wing_head'       => 'warning',
              default           => 'secondary'
          };
        ?>
        <tr>
          <td class="fw-semibold"><?= h($u['user_id']) ?></td>
          <td><?= h($u['name']) ?></td>
          <td><span class="badge bg-<?= $roleBadge === 'purple' ? 'secondary' : $roleBadge ?>" style="<?= $roleBadge==='purple'?'background:#7c3aed!important':'' ?>"><?= $u['role'] ?></span></td>
          <td><?php
            $wingColors = ['ilc'=>['bg'=>'#ecfeff','color'=>'#0e7490'],'montessori'=>['bg'=>'#f0fdf4','color'=>'#166534'],'main'=>['bg'=>'#f8fafc','color'=>'#475569']];
            $wc = $wingColors[$u['user_wing'] ?? 'main'] ?? $wingColors['main'];
            if (in_array($u['role'], ['student','teacher'])):
          ?><span class="badge" style="background:<?= $wc['bg'] ?>;color:<?= $wc['color'] ?>;border:1px solid <?= $wc['bg'] ?>"><?= h($u['user_wing'] ?? 'main') ?></span><?php
            else: ?><span class="text-muted" style="font-size:.78rem">—</span><?php endif; ?></td>
          <td><?= !empty($u['class_name']) ? '<span class="badge bg-secondary">'.h($u['class_name']).'</span>' : '<span class="text-muted" style="font-size:.78rem">—</span>' ?></td>
          <td><?= h($u['email'] ?: '—') ?></td>
          <td><span class="badge <?= $u['status']==='active'?'bg-success':'bg-danger' ?>"><?= $u['status'] ?></span></td>
          <td style="font-size:.78rem"><?= $u['last_login'] ? fDate($u['last_login']) : '—' ?></td>
          <td>
            <!-- Edit (name/email/class + permissions) -->
            <button class="btn btn-xs btn-outline-secondary" style="font-size:.74rem;padding:2px 7px"
                    data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['id'] ?>" title="Edit user &amp; permissions">
              <i class="fas fa-edit"></i>
            </button>
            <!-- View Portal -->
            <?php if ($u['role'] !== 'admin' && $u['status']==='active'): ?>
            <form method="POST" action="<?= url('/admin/view-as.php') ?>" class="d-inline">
              <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
              <button class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 7px" title="View their portal">
                <i class="fas fa-eye"></i>
              </button>
            </form>
            <?php endif; ?>
            <!-- Toggle status -->
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn-xs btn-outline-<?= $u['status']==='active'?'warning':'success' ?>" style="font-size:.74rem;padding:2px 7px"
                      title="<?= $u['status']==='active'?'Deactivate':'Activate' ?>"><?= $u['status']==='active'?'Off':'On' ?></button>
            </form>
            <?php if ($u['id'] !== $user['id']): ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('Send password reset link to this user?')">
              <input type="hidden" name="action" value="send_reset_link">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn-xs btn-outline-info" style="font-size:.74rem;padding:2px 7px" title="Reset password">
                <i class="fas fa-key"></i>
              </button>
            </form>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete user permanently?')">
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn-xs btn-outline-danger" style="font-size:.74rem;padding:2px 7px" title="Delete">
                <i class="fas fa-trash"></i>
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>

        <!-- ── Edit + Permissions Modal ────────────────────────────── -->
        <div class="modal fade" id="editUserModal<?= $u['id'] ?>" tabindex="-1">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header py-2" style="background:#f8fafc">
                <h6 class="modal-title fw-semibold">
                  <i class="fas fa-user-edit me-2 text-primary"></i>Edit — <?= h($u['name']) ?>
                  <span class="badge bg-secondary ms-1" style="font-size:.7rem"><?= h($u['role']) ?></span>
                </h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
              </div>
              <form method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="id"     value="<?= $u['id'] ?>">
                <input type="hidden" name="role"   value="<?= h($u['role']) ?>">
                <div class="modal-body">

                  <!-- Basic info -->
                  <p class="mb-2 fw-semibold text-muted" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.5px">Basic Information</p>
                  <div class="row g-2 mb-3">
                    <div class="col-md-5">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Full Name <span class="text-danger">*</span></label>
                      <input type="text" name="name" class="form-control form-control-sm"
                             value="<?= h($u['name']) ?>" required>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Email</label>
                      <input type="email" name="email" class="form-control form-control-sm"
                             value="<?= h($u['email'] ?? '') ?>">
                    </div>
                    <?php if ($u['role'] === 'student'): ?>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" style="font-size:.82rem">Class</label>
                      <select name="class_id" class="form-select form-select-sm">
                        <option value="">— None —</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($u['class_name'] && $c['name']===$u['class_name'])?'selected':'' ?>><?= h($c['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <?php endif; ?>
                  </div>

                  <!-- Permissions (only for roles that have them) -->
                  <?php
                    $rolePermsModal = getRolePermissions($u['role']);
                    if ($rolePermsModal):
                      $userPermsModal = [];
                      try {
                          $mst = $db->prepare('SELECT permission, granted FROM user_permissions WHERE user_id=?');
                          $mst->execute([$u['id']]);
                          $userPermsModal = $mst->fetchAll(PDO::FETCH_KEY_PAIR);
                      } catch (Exception $e) {}
                  ?>
                  <hr class="my-2">
                  <p class="mb-2 fw-semibold text-muted" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.5px">
                    <i class="fas fa-lock me-1"></i>Access Permissions
                  </p>
                  <div class="row g-2">
                    <?php foreach ($rolePermsModal as $pKey => $pDef): ?>
                    <div class="col-md-4 col-6">
                      <div class="form-check" style="padding:6px 8px;border-radius:6px;background:#f8fafc;border:1px solid #e5e7eb">
                        <input class="form-check-input" type="checkbox"
                               name="perms[<?= $pKey ?>]" id="perm_<?= $u['id'] ?>_<?= $pKey ?>"
                               value="1" <?= (bool)($userPermsModal[$pKey] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="perm_<?= $u['id'] ?>_<?= $pKey ?>" style="font-size:.82rem;cursor:pointer">
                          <i class="fas <?= $pDef['icon'] ?> me-1" style="font-size:.72rem;color:#9ca3af"></i><?= h($pDef['label']) ?>
                        </label>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <div class="mt-2" style="font-size:.74rem;color:#9ca3af">
                    <i class="fas fa-info-circle me-1"></i>Unchecked items are hidden from the user's sidebar and blocked at the page level.
                  </div>
                  <?php endif; ?>

                </div>
                <div class="modal-footer py-2">
                  <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-save me-1"></i>Save Changes
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="d-flex justify-content-center p-2 gap-1">
    <?php for ($i=1;$i<=$pages;$i++): ?>
      <a href="?role=<?= urlencode($roleFilter) ?>&wing=<?= urlencode($wingFilter) ?>&page=<?= $i ?>" class="btn btn-xs <?= $i===$page?'btn-primary':'btn-outline-secondary' ?>" style="font-size:.78rem;padding:2px 8px"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleRoleFields(role) {
    document.getElementById('studentFields').classList.toggle('d-none', role !== 'student');
    document.getElementById('teacherFields').classList.toggle('d-none', role !== 'teacher');
    document.getElementById('qualFields').classList.toggle('d-none', role !== 'teacher');
    document.getElementById('teacherPhoneField').classList.toggle('d-none', role !== 'teacher');
    document.getElementById('teacherJoinDateField').classList.toggle('d-none', role !== 'teacher');
}
</script>
</body></html>

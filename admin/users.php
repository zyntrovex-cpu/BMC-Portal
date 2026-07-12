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

    redirect('/admin/users.php' . (isset($_POST['role_filter']) ? '?role='.$_POST['role_filter'] : ''));
}

$roleFilter = $_GET['role'] ?? '';
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 20;
$offset     = ($page - 1) * $perPage;

$where  = $roleFilter ? 'WHERE role = ?' : '';
$params = $roleFilter ? [$roleFilter] : [];

$countSt = $db->prepare("SELECT COUNT(*) FROM users $where");
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();
$pages = (int)ceil($total / $perPage);

$usersSt = $db->prepare(
    "SELECT u.*, c.name AS class_name
     FROM users u
     LEFT JOIN students s ON s.user_id = u.id AND u.role = 'student'
     LEFT JOIN classes c  ON c.id = s.class_id
     $where ORDER BY u.role, u.name LIMIT $perPage OFFSET $offset"
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
  <div class="sec-card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-users me-2"></i>All Users (<?= $total ?>)</span>
    <div class="d-flex gap-1">
      <?php foreach (['','student','teacher','admin','finance'] as $r): ?>
        <a href="?role=<?= $r ?>" class="btn btn-xs <?= $roleFilter===$r?'btn-primary':'btn-outline-secondary' ?>" style="font-size:.75rem;padding:2px 8px"><?= $r ?: 'All' ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.84rem">
      <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Role</th><th>Class</th><th>Email</th><th>Status</th><th>Last Login</th><th></th></tr></thead>
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
          <td><?= !empty($u['class_name']) ? '<span class="badge bg-secondary">'.h($u['class_name']).'</span>' : '<span class="text-muted" style="font-size:.78rem">—</span>' ?></td>
          <td><?= h($u['email'] ?: '—') ?></td>
          <td><span class="badge <?= $u['status']==='active'?'bg-success':'bg-danger' ?>"><?= $u['status'] ?></span></td>
          <td style="font-size:.78rem"><?= $u['last_login'] ? fDate($u['last_login']) : '—' ?></td>
          <td>
            <?php if (in_array($u['role'], ['student','teacher']) && $u['status']==='active'): ?>
            <form method="POST" action="/admin/view-as.php" class="d-inline">
              <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
              <button class="btn btn-xs btn-outline-primary" style="font-size:.74rem;padding:2px 7px" title="View their portal">
                <i class="fas fa-eye"></i>
              </button>
            </form>
            <?php endif; ?>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn-xs btn-outline-<?= $u['status']==='active'?'warning':'success' ?>" style="font-size:.74rem;padding:2px 7px"><?= $u['status']==='active'?'Deactivate':'Activate' ?></button>
            </form>
            <?php if ($u['id'] !== $user['id']): ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('Send password reset link to this user?')">
              <input type="hidden" name="action" value="send_reset_link">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn-xs btn-outline-info" style="font-size:.74rem;padding:2px 7px">Reset Pwd</button>
            </form>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete user?')">
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn-xs btn-outline-danger" style="font-size:.74rem;padding:2px 7px">Del</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="d-flex justify-content-center p-2 gap-1">
    <?php for ($i=1;$i<=$pages;$i++): ?>
      <a href="?role=<?= $roleFilter ?>&page=<?= $i ?>" class="btn btn-xs <?= $i===$page?'btn-primary':'btn-outline-secondary' ?>" style="font-size:.78rem;padding:2px 8px"><?= $i ?></a>
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

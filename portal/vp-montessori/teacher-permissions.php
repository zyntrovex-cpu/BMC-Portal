<?php
/**
 * VP Montessori — Teacher Permissions Management
 * Manage portal permissions + attendance class assignments for montessori_teacher users.
 * Access: vp_montessori (monte_teacher_perms) | admin
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../config/config.php';

$user = requireAuth('vp_montessori', 'admin');
if ($user['role'] === 'vp_montessori') requirePermission('monte_teacher_perms');
$db = getDB();

// ── POST: save permissions + attendance class assignments ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacherUserId = (int)($_POST['teacher_user_id'] ?? 0);
    if ($teacherUserId) {
        // Verify this is a Montessori teacher
        $check = $db->prepare(
            "SELECT u.id FROM users u
             LEFT JOIN teachers t ON t.user_id = u.id
             WHERE u.id = ? AND u.role = 'montessori_teacher'"
        );
        $check->execute([$teacherUserId]);
        if ($check->fetch()) {
            $rolePerms = getRolePermissions('montessori_teacher');
            $db->prepare('DELETE FROM user_permissions WHERE user_id=?')->execute([$teacherUserId]);
            $ins = $db->prepare(
                'INSERT INTO user_permissions (user_id, permission, granted) VALUES (?,?,?)'
            );
            $submitted = $_POST['perms'] ?? [];
            foreach ($rolePerms as $perm => $def) {
                $ins->execute([$teacherUserId, $perm, isset($submitted[$perm]) ? 1 : 0]);
            }

            // Save attendance class permissions
            try {
                $tStAcp = $db->prepare('SELECT id FROM teachers WHERE user_id=?');
                $tStAcp->execute([$teacherUserId]);
                $tRowAcp = $tStAcp->fetch();
                if ($tRowAcp) {
                    $tIdAcp = (int)$tRowAcp['id'];
                    $db->prepare('DELETE FROM attendance_class_permissions WHERE teacher_id=?')->execute([$tIdAcp]);
                    if (!empty($submitted['attendance']) && !empty($_POST['acp_classes'])) {
                        $acpIns = $db->prepare('INSERT IGNORE INTO attendance_class_permissions (teacher_id, class_id, granted_by) VALUES (?,?,?)');
                        foreach ($_POST['acp_classes'] as $cid) {
                            if ((int)$cid > 0) $acpIns->execute([$tIdAcp, (int)$cid, $user['id']]);
                        }
                    }
                }
            } catch (Exception $e) {}

            if (isset($_SESSION['_perms_uid']) && (int)$_SESSION['_perms_uid'] === $teacherUserId) {
                unset($_SESSION['user_perms'], $_SESSION['_perms_uid']);
            }
            logActivity($user['id'], 'vpm_set_teacher_perms', "Updated permissions for user #$teacherUserId");
            setFlash('success', 'Permissions saved.');
        } else {
            setFlash('error', 'Invalid teacher or access denied.');
        }
    }
    redirect('/portal/vp-montessori/teacher-permissions.php' . ($teacherUserId ? "?uid=$teacherUserId" : ''));
}

// ── Load Montessori teachers ──────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$sql = "SELECT u.id, u.name, u.email, u.status,
               t.emp_id, t.designation,
               sub.name AS subject_name
        FROM users u
        LEFT JOIN teachers t ON t.user_id = u.id
        LEFT JOIN subjects sub ON sub.id = t.subject_id
        WHERE u.role = 'montessori_teacher'";
$params = [];
if ($search) {
    $sql   .= ' AND (u.name LIKE ? OR t.emp_id LIKE ?)';
    $like   = "%$search%";
    $params = [$like, $like];
}
$sql .= ' ORDER BY u.name';
$st = $db->prepare($sql);
$st->execute($params);
$teachers = $st->fetchAll(PDO::FETCH_ASSOC);

// ── Selected teacher ──────────────────────────────────────────────────────────
$selUid       = (int)($_GET['uid'] ?? 0);
$selTeacher   = null;
$permsRow     = [];
$rolePerms    = getRolePermissions('montessori_teacher');
$acpClassIds  = [];
$campusClasses = [];

if ($selUid) {
    foreach ($teachers as $t) {
        if ((int)$t['id'] === $selUid) { $selTeacher = $t; break; }
    }
    if ($selTeacher) {
        $pSt = $db->prepare('SELECT permission, granted FROM user_permissions WHERE user_id = ?');
        $pSt->execute([$selUid]);
        foreach ($pSt->fetchAll() as $r) {
            $permsRow[$r['permission']] = (bool)$r['granted'];
        }

        // Load Montessori campus classes for ACP picker
        try {
            $campusClasses = $db->query(
                "SELECT id, name, grade, section FROM classes
                 WHERE COALESCE(wing,'main')='montessori' OR COALESCE(is_montessori,0)=1
                 ORDER BY grade, section, name"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $campusClasses = []; }

        // Load existing ACP for this teacher
        try {
            $tSt2 = $db->prepare('SELECT id FROM teachers WHERE user_id=?');
            $tSt2->execute([$selUid]);
            $tRow2 = $tSt2->fetch();
            if ($tRow2) {
                $acpSt = $db->prepare('SELECT class_id FROM attendance_class_permissions WHERE teacher_id=?');
                $acpSt->execute([$tRow2['id']]);
                $acpClassIds = array_column($acpSt->fetchAll(PDO::FETCH_ASSOC), 'class_id');
            }
        } catch (Exception $e) {}
    }
}

pageHead('Teacher Permissions', 'vp_montessori');
$links = ($user['role'] === 'admin') ? getAdminLinks() : getVpMontessoriLinks();
?>
<div class="portal-wrap">
<?php sidebar($user['role'], 'teacher-permissions', $links, $user); ?>
<div class="main-area">
<?php topbar('Teacher Permissions', $user); ?>
<div class="page-content">
<?= flashHtml() ?>

<!-- Branding strip -->
<div class="d-flex align-items-center gap-3 mb-3 p-3"
     style="background:linear-gradient(90deg,#f0fdf4,#dcfce7);border-radius:10px;border:1px solid #86efac">
  <div style="width:40px;height:40px;border-radius:50%;background:#16a34a;color:#fff;font-size:1.1rem;
              display:flex;align-items:center;justify-content:center;flex-shrink:0">
    <i class="fas fa-user-shield"></i>
  </div>
  <div>
    <div style="font-size:.72rem;font-weight:700;color:#14532d;letter-spacing:.8px;text-transform:uppercase">
      Teacher Portal Permissions
    </div>
    <div style="font-size:.78rem;color:#475569">Montessori Campus · Bahria Model College Bin Qasim</div>
  </div>
</div>

<div class="row g-3">
  <!-- ── Left: teacher list ── -->
  <div class="col-lg-4">
    <div class="sec-card">
      <div class="sec-card-header" style="background:linear-gradient(90deg,#16a34a,#22c55e);color:#fff">
        <i class="fas fa-chalkboard-teacher me-2"></i>Montessori Teachers
        <span class="badge bg-light text-dark ms-2" style="font-size:.72rem"><?= count($teachers) ?></span>
      </div>
      <div style="padding:10px 12px;border-bottom:1px solid var(--border)">
        <form method="GET" class="d-flex gap-2">
          <?php if ($selUid): ?>
          <input type="hidden" name="uid" value="<?= $selUid ?>">
          <?php endif; ?>
          <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search teachers…"
                 class="form-control form-control-sm">
          <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
        </form>
      </div>
      <div style="max-height:520px;overflow-y:auto">
        <?php foreach ($teachers as $t): ?>
        <a href="?uid=<?= $t['id'] ?><?= $search ? '&q='.urlencode($search) : '' ?>"
           class="d-flex align-items-center gap-2 px-3 py-2"
           style="text-decoration:none;border-bottom:1px solid #f1f5f9;
                  background:<?= $selUid === (int)$t['id'] ? '#f0fdf4' : 'transparent' ?>">
          <div style="width:34px;height:34px;border-radius:50%;background:#16a34a;color:#fff;font-size:.85rem;
                      display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <?= strtoupper(substr($t['name'],0,1)) ?>
          </div>
          <div class="flex-grow-1 overflow-hidden">
            <div class="fw-semibold" style="font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              <?= h($t['name']) ?>
            </div>
            <div style="font-size:.74rem;color:var(--t2)">
              <?= $t['emp_id'] ? h($t['emp_id']).' · ' : '' ?><?= h($t['subject_name'] ?: $t['designation'] ?: 'Montessori Teacher') ?>
            </div>
          </div>
          <?php if ($t['status'] !== 'active'): ?>
          <span class="badge bg-secondary" style="font-size:.65rem"><?= h($t['status']) ?></span>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
        <?php if (empty($teachers)): ?>
        <div class="text-center text-muted py-4" style="font-size:.84rem">No montessori teachers found.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Right: permissions form ── -->
  <div class="col-lg-8">
    <?php if ($selTeacher): ?>
    <div class="sec-card">
      <div class="sec-card-header d-flex align-items-center gap-2"
           style="background:linear-gradient(90deg,#16a34a,#22c55e);color:#fff">
        <i class="fas fa-user-shield me-1"></i>
        Permissions — <strong><?= h($selTeacher['name']) ?></strong>
        <?php if ($selTeacher['emp_id']): ?>
        <span style="font-size:.8rem;opacity:.85">(<?= h($selTeacher['emp_id']) ?>)</span>
        <?php endif; ?>
      </div>
      <div style="padding:18px">
        <div class="alert py-2 px-3 mb-4" style="font-size:.82rem;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534">
          <i class="fas fa-info-circle me-1"></i>
          These permissions control what <strong><?= h($selTeacher['name']) ?></strong> can access in their teacher portal.
          Unticked features are hidden from their navigation.
        </div>

        <form method="POST">
          <input type="hidden" name="teacher_user_id" value="<?= $selTeacher['id'] ?>">
          <div class="row g-3 mb-3">
            <?php foreach ($rolePerms as $perm => $def): ?>
            <?php $granted = isset($permsRow[$perm]) ? $permsRow[$perm] : true; ?>
            <div class="col-sm-6">
              <div class="form-check p-3 rounded" style="border:1px solid <?= $granted ? '#bbf7d0' : '#e2e8f0' ?>;background:<?= $granted ? '#f0fdf4' : '#f8fafc' ?>">
                <input class="form-check-input" type="checkbox" name="perms[<?= h($perm) ?>]"
                       id="perm_<?= h($perm) ?>" <?= $granted ? 'checked' : '' ?>
                       <?= $perm === 'attendance' ? 'data-acp-toggle="acpSection"' : '' ?>>
                <label class="form-check-label" for="perm_<?= h($perm) ?>" style="font-size:.85rem;cursor:pointer">
                  <i class="fas <?= h($def['icon']) ?> me-1 text-secondary"></i>
                  <strong><?= h($def['label']) ?></strong>
                </label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Attendance class assignment panel -->
          <?php $attGranted = isset($permsRow['attendance']) ? $permsRow['attendance'] : true; ?>
          <div id="acpSection" style="display:<?= $attGranted ? 'block' : 'none' ?>;margin-bottom:16px;border:1px solid #bbf7d0;border-radius:8px;padding:14px;background:#f0fdf4">
            <div style="font-weight:600;font-size:.85rem;color:#166534;margin-bottom:8px">
              <i class="fas fa-school me-1"></i>Attendance — Assigned Classes
            </div>
            <p style="font-size:.78rem;color:#555;margin-bottom:10px">
              This teacher can only mark attendance for checked classes. Uncheck all to revoke class access.
            </p>
            <?php if ($campusClasses): ?>
            <div class="row g-2">
              <?php foreach ($campusClasses as $cls): ?>
              <div class="col-sm-6 col-md-4">
                <div class="form-check p-2 rounded" style="border:1px solid #d1fae5;background:#fff">
                  <input class="form-check-input" type="checkbox" name="acp_classes[]"
                         value="<?= $cls['id'] ?>" id="acp_<?= $cls['id'] ?>"
                         <?= in_array((string)$cls['id'], array_map('strval', $acpClassIds)) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="acp_<?= $cls['id'] ?>" style="font-size:.82rem;cursor:pointer">
                    <?= h($cls['name']) ?>
                  </label>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted" style="font-size:.82rem;margin:0">No classes found for Montessori campus.</p>
            <?php endif; ?>
          </div>

          <button type="submit" class="btn btn-success btn-sm">
            <i class="fas fa-save me-1"></i>Save Permissions
          </button>
          <a href="?uid=<?= $selTeacher['id'] ?>" class="btn btn-outline-secondary btn-sm ms-2">
            <i class="fas fa-undo me-1"></i>Reset
          </a>
        </form>
      </div>
    </div>
    <?php else: ?>
    <div class="sec-card" style="padding:60px;text-align:center;color:var(--t2)">
      <i class="fas fa-user-shield fa-2x mb-3 d-block opacity-25"></i>
      <p style="font-size:.88rem">Select a teacher from the list to view and edit their portal permissions.</p>
    </div>
    <?php endif; ?>
  </div>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  var attChk = document.getElementById('perm_attendance');
  var acp    = document.getElementById('acpSection');
  if (attChk && acp) {
    attChk.addEventListener('change', function(){ acp.style.display = this.checked ? '' : 'none'; });
  }
})();
</script>
</body></html>

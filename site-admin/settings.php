<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = requireSiteAdmin();
$db    = siteDB();
$msg = $err = '';
$tab = trim($_GET['tab'] ?? 'general');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $fields = $_POST['settings'] ?? [];
        try {
            $stmt = $db->prepare('INSERT INTO site_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
            foreach ($fields as $key => $value) {
                $stmt->execute([trim($key), trim($value)]);
            }
            if (!empty($_POST['new_password'])) {
                $np = $_POST['new_password'];
                if (strlen($np) >= 8) {
                    $db->prepare('UPDATE site_admins SET password=? WHERE id=?')
                       ->execute([password_hash($np, PASSWORD_BCRYPT), $admin['id']]);
                }
            }
            $msg = 'Settings saved.';
        } catch (Exception $e) { $err = $e->getMessage(); }
    }

    if ($action === 'add_admin') {
        $aName  = trim($_POST['admin_name'] ?? '');
        $aEmail = trim($_POST['admin_email'] ?? '');
        $aPass  = $_POST['admin_password'] ?? '';
        $aRole  = trim($_POST['admin_role'] ?? 'editor');
        if (!$aName || !$aEmail || strlen($aPass) < 8) {
            $err = 'Name, email, and password (min 8 chars) are required.';
        } else {
            try {
                $db->prepare('INSERT INTO site_admins (name,email,password,role) VALUES (?,?,?,?)')
                   ->execute([$aName,$aEmail,password_hash($aPass,PASSWORD_BCRYPT),$aRole]);
                $msg = 'Admin account created.';
            } catch (Exception $e) { $err = 'Could not create admin: ' . $e->getMessage(); }
        }
    }

    if ($action === 'delete_admin') {
        $id = (int)($_POST['admin_id'] ?? 0);
        if ($id === (int)$admin['id']) { $err = 'Cannot delete your own account.'; }
        else { $db->prepare('DELETE FROM site_admins WHERE id=?')->execute([$id]); $msg = 'Admin deleted.'; }
    }
}

try {
    $rows = $db->query('SELECT `key`,`value` FROM site_settings')->fetchAll();
    $settings = array_column($rows, 'value', 'key');
} catch (Exception $e) { $settings = []; }

try { $admins = $db->query('SELECT id,name,email,role,created_at FROM site_admins ORDER BY id')->fetchAll(); }
catch (Exception $e) { $admins = []; }

function sv(array $s, string $k, string $d=''): string { return htmlspecialchars($s[$k] ?? $d, ENT_QUOTES); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Settings — BMC Admin</title>
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/bmc-logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="admin-main">
    <header class="admin-topbar">
      <div><span style="font-weight:700;color:var(--primary)">Settings</span></div>
      <div style="font-size:.85rem;color:var(--text-2)">Welcome, <?= sh($admin['name']) ?></div>
    </header>
    <div class="admin-content">
      <?php if ($msg): ?><div class="alert alert-success auto-dismiss"><?= sh($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-danger"><?= sh($err) ?></div><?php endif; ?>

      <ul class="nav nav-tabs mb-4">
        <?php foreach (['general'=>'General','contact'=>'Contact & Map','social'=>'Social Media','principal'=>'Principal','admissions'=>'Admissions','admins'=>'Admin Accounts','account'=>'My Account'] as $k=>$l): ?>
        <li class="nav-item"><a class="nav-link <?= $tab===$k?'active':'' ?>" href="?tab=<?= $k ?>"><?= $l ?></a></li>
        <?php endforeach; ?>
      </ul>

      <?php if ($tab === 'general'): ?>
      <div class="admin-card">
        <div class="admin-card-header"><i class="fas fa-cog me-2"></i>General</div>
        <div class="admin-card-body">
          <form method="POST" class="row g-3">
            <input type="hidden" name="action" value="save_settings">
            <div class="col-md-6"><label class="form-label">Site Name</label>
              <input type="text" name="settings[site_name]" class="form-control" value="<?= sv($settings,'site_name','Bahria Model College') ?>"></div>
            <div class="col-md-6"><label class="form-label">Tagline</label>
              <input type="text" name="settings[site_tagline]" class="form-control" value="<?= sv($settings,'site_tagline','Excellence in Education') ?>"></div>
            <div class="col-md-4"><label class="form-label">Established Year</label>
              <input type="text" name="settings[established_year]" class="form-control" value="<?= sv($settings,'established_year','1995') ?>"></div>
            <div class="col-md-4"><label class="form-label">Session Year</label>
              <input type="text" name="settings[session_year]" class="form-control" value="<?= sv($settings,'session_year','2025-26') ?>"></div>
            <div class="col-md-4"><label class="form-label">Board / Affiliation</label>
              <input type="text" name="settings[board_name]" class="form-control" value="<?= sv($settings,'board_name','BIEK Karachi') ?>"></div>
            <div class="col-12"><label class="form-label">Footer Text</label>
              <textarea name="settings[footer_text]" class="form-control" rows="2"><?= sv($settings,'footer_text') ?></textarea></div>
            <div class="col-12"><button type="submit" class="btn btn-primary">Save</button></div>
          </form>
        </div>
      </div>

      <?php elseif ($tab === 'contact'): ?>
      <div class="admin-card">
        <div class="admin-card-header"><i class="fas fa-map-marker-alt me-2"></i>Contact & Map</div>
        <div class="admin-card-body">
          <form method="POST" class="row g-3">
            <input type="hidden" name="action" value="save_settings">
            <div class="col-12"><label class="form-label">Full Address</label>
              <textarea name="settings[site_address]" class="form-control" rows="2"><?= sv($settings,'site_address','Bahria Model College, Bin Qasim, Karachi') ?></textarea></div>
            <div class="col-md-6"><label class="form-label">Phone</label>
              <input type="text" name="settings[site_phone]" class="form-control" value="<?= sv($settings,'site_phone','+92-21-34703000') ?>"></div>
            <div class="col-md-6"><label class="form-label">Email</label>
              <input type="email" name="settings[site_email]" class="form-control" value="<?= sv($settings,'site_email','info@bmc.edu.pk') ?>"></div>
            <div class="col-12"><label class="form-label">Google Maps Embed src URL</label>
              <input type="text" name="settings[site_map_embed]" class="form-control" value="<?= sv($settings,'site_map_embed') ?>" placeholder="Paste the src=&quot;…&quot; URL from Google Maps embed code">
              <div class="form-text">Google Maps → Share → Embed a map → copy only the <code>src="…"</code> value.</div></div>
            <div class="col-12"><button type="submit" class="btn btn-primary">Save</button></div>
          </form>
        </div>
      </div>

      <?php elseif ($tab === 'social'): ?>
      <div class="admin-card">
        <div class="admin-card-header"><i class="fas fa-share-alt me-2"></i>Social Media</div>
        <div class="admin-card-body">
          <form method="POST" class="row g-3">
            <input type="hidden" name="action" value="save_settings">
            <?php foreach (['social_facebook'=>'Facebook','social_twitter'=>'X / Twitter','social_instagram'=>'Instagram','social_youtube'=>'YouTube','social_linkedin'=>'LinkedIn'] as $k=>$l): ?>
            <div class="col-md-6"><label class="form-label"><?= $l ?></label>
              <input type="url" name="settings[<?= $k ?>]" class="form-control" value="<?= sv($settings,$k) ?>" placeholder="https://…"></div>
            <?php endforeach; ?>
            <div class="col-12"><button type="submit" class="btn btn-primary">Save</button></div>
          </form>
        </div>
      </div>

      <?php elseif ($tab === 'principal'): ?>
      <div class="admin-card">
        <div class="admin-card-header"><i class="fas fa-user-tie me-2"></i>Principal</div>
        <div class="admin-card-body">
          <form method="POST" class="row g-3">
            <input type="hidden" name="action" value="save_settings">
            <div class="col-md-6"><label class="form-label">Name</label>
              <input type="text" name="settings[principal_name]" class="form-control" value="<?= sv($settings,'principal_name','Dr. Amjad Hussain') ?>"></div>
            <div class="col-md-6"><label class="form-label">Designation</label>
              <input type="text" name="settings[principal_designation]" class="form-control" value="<?= sv($settings,'principal_designation','Principal, BMC') ?>"></div>
            <div class="col-md-6"><label class="form-label">Qualification</label>
              <input type="text" name="settings[principal_qualification]" class="form-control" value="<?= sv($settings,'principal_qualification','PhD Education, MEd') ?>"></div>
            <div class="col-md-6"><label class="form-label">Experience</label>
              <input type="text" name="settings[principal_experience]" class="form-control" value="<?= sv($settings,'principal_experience','28+ Years in Education') ?>"></div>
            <div class="col-12"><label class="form-label">Welcome Message</label>
              <textarea name="settings[principal_message]" class="form-control" rows="6"><?= sv($settings,'principal_message') ?></textarea></div>
            <div class="col-12"><button type="submit" class="btn btn-primary">Save</button></div>
          </form>
        </div>
      </div>

      <?php elseif ($tab === 'admissions'): ?>
      <div class="admin-card">
        <div class="admin-card-header"><i class="fas fa-graduation-cap me-2"></i>Admissions</div>
        <div class="admin-card-body">
          <form method="POST" class="row g-3">
            <input type="hidden" name="action" value="save_settings">
            <div class="col-md-4"><label class="form-label">Status</label>
              <select name="settings[admission_open]" class="form-select">
                <option value="1" <?= ($settings['admission_open']??'1')==='1'?'selected':'' ?>>Open</option>
                <option value="0" <?= ($settings['admission_open']??'1')==='0'?'selected':'' ?>>Closed</option>
              </select></div>
            <div class="col-md-4"><label class="form-label">Academic Year</label>
              <input type="text" name="settings[admission_year]" class="form-control" value="<?= sv($settings,'admission_year','2025-26') ?>"></div>
            <div class="col-md-4"><label class="form-label">Last Date</label>
              <input type="date" name="settings[admission_last_date]" class="form-control" value="<?= sv($settings,'admission_last_date') ?>"></div>
            <div class="col-12"><label class="form-label">Announcement Text</label>
              <textarea name="settings[admission_notice]" class="form-control" rows="3"><?= sv($settings,'admission_notice') ?></textarea></div>
            <div class="col-12"><button type="submit" class="btn btn-primary">Save</button></div>
          </form>
        </div>
      </div>

      <?php elseif ($tab === 'admins'): ?>
      <div class="admin-card mb-4">
        <div class="admin-card-header"><i class="fas fa-plus me-2"></i>Add Admin</div>
        <div class="admin-card-body">
          <form method="POST" class="row g-3">
            <input type="hidden" name="action" value="add_admin">
            <div class="col-md-4"><label class="form-label">Name *</label><input type="text" name="admin_name" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Email *</label><input type="email" name="admin_email" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Role</label>
              <select name="admin_role" class="form-select"><option value="editor">Editor</option><option value="admin">Admin</option></select></div>
            <div class="col-md-2"><label class="form-label">Password *</label><input type="password" name="admin_password" class="form-control" required minlength="8"></div>
            <div class="col-12"><button type="submit" class="btn btn-primary">Create</button></div>
          </form>
        </div>
      </div>
      <div class="admin-card">
        <div class="admin-card-header"><i class="fas fa-users-cog me-2"></i>Admin Accounts (<?= count($admins) ?>)</div>
        <div class="admin-card-body p-0">
          <table class="admin-table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($admins as $a): ?>
              <tr>
                <td><strong><?= sh($a['name']) ?></strong><?= $a['id']==$admin['id']?' <span class="badge bg-primary">You</span>':'' ?></td>
                <td><?= sh($a['email']) ?></td>
                <td><span class="badge bg-secondary"><?= sh($a['role']) ?></span></td>
                <td style="font-size:.8rem"><?= date('d M Y', strtotime($a['created_at'])) ?></td>
                <td><?php if ($a['id'] != $admin['id']): ?>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')">
                    <input type="hidden" name="action" value="delete_admin">
                    <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                  </form><?php endif; ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php elseif ($tab === 'account'): ?>
      <div class="admin-card">
        <div class="admin-card-header"><i class="fas fa-user-circle me-2"></i>My Account</div>
        <div class="admin-card-body">
          <form method="POST" class="row g-3">
            <input type="hidden" name="action" value="save_settings">
            <div class="col-md-6"><label class="form-label">Name</label>
              <input type="text" class="form-control" value="<?= sh($admin['name']) ?>" disabled></div>
            <div class="col-md-6"><label class="form-label">Email</label>
              <input type="email" class="form-control" value="<?= sh($admin['email']) ?>" disabled></div>
            <div class="col-12"><hr></div>
            <div class="col-md-5"><label class="form-label">New Password</label>
              <input type="password" name="new_password" class="form-control" minlength="8" placeholder="Leave blank to keep current"></div>
            <div class="col-12"><button type="submit" class="btn btn-primary">Update Password</button></div>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>setTimeout(()=>{document.querySelectorAll('.auto-dismiss').forEach(e=>{e.style.opacity='0';setTimeout(()=>e.remove(),400)})},4000)</script>
</body></html>

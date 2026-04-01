<?php
require_once 'auth.php';
require_once 'conn.php';

$is_admin = ($_SESSION['user_role'] === 'admin');
$users    = array();
$stats    = array();
$msg      = '';
$msg_type = '';

// ── Handle actions ──────────────────────────────────────────────
// Delete user
if ($is_admin && isset($_GET['delete_user'])) {
    $del_id = (int)$_GET['delete_user'];
    if ($del_id !== (int)$_SESSION['user_id']) {
        $c = get_connection();
        sqlsrv_query($c, "DELETE FROM Users WHERE UserID = ?", array($del_id));
        sqlsrv_close($c);
        $msg = "User deleted successfully."; $msg_type = 'success';
    }
}

// Promote / demote user
if ($is_admin && isset($_GET['promote'])) {
    $pid  = (int)$_GET['promote'];
    $role = $_GET['role'] === 'admin' ? 'admin' : 'user';
    if ($pid !== (int)$_SESSION['user_id']) {
        $c = get_connection();
        sqlsrv_query($c, "UPDATE Users SET Role = ? WHERE UserID = ?", array($role, $pid));
        sqlsrv_close($c);
        $msg = "User role updated."; $msg_type = 'success';
    }
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $old = $_POST['old_password'];
    $new = $_POST['new_password'];
    $cnf = $_POST['confirm_password'];

    if (strlen($new) < 8 || !preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new)) {
        $msg = "New password must be 8+ chars with 1 uppercase and 1 number."; $msg_type = 'error';
    } elseif ($new !== $cnf) {
        $msg = "New passwords do not match."; $msg_type = 'error';
    } else {
        $c   = get_connection();
        $res = sqlsrv_query($c, "SELECT Password FROM Users WHERE UserID = ?", array($_SESSION['user_id']));
        $row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
        if ($row && password_verify($old, $row['Password'])) {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            sqlsrv_query($c, "UPDATE Users SET Password = ? WHERE UserID = ?", array($hash, $_SESSION['user_id']));
            $msg = "Password changed successfully!"; $msg_type = 'success';
        } else {
            $msg = "Current password is incorrect."; $msg_type = 'error';
        }
        sqlsrv_close($c);
    }
}

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $fn   = trim($_POST['fullname']);
    $city = trim($_POST['city']);
    $uni  = trim($_POST['university']);
    if ($fn && $city && $uni) {
        $c = get_connection();
        sqlsrv_query($c, "UPDATE Users SET FullName=?, City=?, University=? WHERE UserID=?",
            array($fn, $city, $uni, $_SESSION['user_id']));
        sqlsrv_close($c);
        $_SESSION['user_name'] = $fn;
        $msg = "Profile updated!"; $msg_type = 'success';
    } else {
        $msg = "All fields required."; $msg_type = 'error';
    }
}

// Load data
$c = get_connection();

// Current user profile
$res     = sqlsrv_query($c, "SELECT * FROM Users WHERE UserID = ?", array($_SESSION['user_id']));
$profile = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);

// Admin stats
if ($is_admin) {
    $r = sqlsrv_query($c, "SELECT COUNT(*) AS cnt FROM EMP");
    $stats['employees'] = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)['cnt'];

    $r = sqlsrv_query($c, "SELECT COUNT(*) AS cnt FROM DEPT");
    $stats['departments'] = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)['cnt'];

    $r = sqlsrv_query($c, "SELECT COUNT(*) AS cnt FROM Project");
    $stats['projects'] = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)['cnt'];

    $r = sqlsrv_query($c, "SELECT COUNT(*) AS cnt FROM Users");
    $stats['users'] = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)['cnt'];

    $r = sqlsrv_query($c, "SELECT AVG(SAL) AS avg FROM EMP");
    $stats['avg_sal'] = round(sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)['avg'], 2);

    $res2 = sqlsrv_query($c, "SELECT UserID,FullName,Email,City,University,Role,CreatedAt FROM Users ORDER BY CreatedAt DESC");
    while ($row = sqlsrv_fetch_array($res2, SQLSRV_FETCH_ASSOC)) { $users[] = $row; }
}
sqlsrv_close($c);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — EmpDB Manager</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0f1117;color:#e5e7eb;font-family:'Segoe UI',sans-serif;min-height:100vh}
nav{background:#1a1d27;border-bottom:1px solid #2a2d3e;padding:0 2rem;display:flex;align-items:center;justify-content:space-between;height:60px;position:sticky;top:0;z-index:100}
.nav-brand{font-size:1.2rem;font-weight:700;color:#fff}.nav-brand span{color:#6366f1}
.nav-right{display:flex;align-items:center;gap:1rem;flex-wrap:wrap}
.nav-user{font-size:.875rem;color:#9ca3af}.nav-user strong{color:#fff}
.badge{display:inline-block;padding:.15rem .5rem;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.badge-admin{background:rgba(99,102,241,.2);color:#a5b4fc;border:1px solid rgba(99,102,241,.3)}
.badge-user{background:rgba(34,197,94,.15);color:#4ade80;border:1px solid rgba(34,197,94,.3)}
.btn-logout{padding:.4rem 1rem;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;border-radius:6px;font-size:.875rem;font-weight:600;text-decoration:none}
.btn-logout:hover{background:rgba(239,68,68,.2)}
.tab-bar{background:#1a1d27;border-bottom:1px solid #2a2d3e;display:flex;gap:0;padding:0 2rem}
.tab{padding:.85rem 1.25rem;font-size:.875rem;font-weight:600;color:#6b7280;cursor:pointer;border-bottom:2px solid transparent;transition:all .2s;background:none;border-top:none;border-left:none;border-right:none}
.tab:hover{color:#e5e7eb}.tab.active{color:#6366f1;border-bottom-color:#6366f1}
.main{max-width:1100px;margin:0 auto;padding:2rem 1.5rem}
.tab-content{display:none}.tab-content.active{display:block}
.welcome-card{background:linear-gradient(135deg,#1e1b4b,#1a1d27);border:1px solid #3730a3;border-radius:16px;padding:2rem;margin-bottom:2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem}
.welcome-card h2{font-size:1.4rem;font-weight:700;color:#fff}
.welcome-card p{color:#a5b4fc;font-size:.9rem;margin-top:.3rem}
.btn-primary{padding:.75rem 1.5rem;background:#6366f1;color:#fff;border-radius:8px;font-size:.95rem;font-weight:600;text-decoration:none;transition:background .2s;display:inline-block;border:none;cursor:pointer}
.btn-primary:hover{background:#4f46e5}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;margin-bottom:2rem}
.stat-card{background:#1a1d27;border:1px solid #2a2d3e;border-radius:12px;padding:1.25rem;text-align:center}
.stat-icon{font-size:1.8rem;margin-bottom:.5rem}
.stat-value{font-size:1.8rem;font-weight:800;color:#fff}
.stat-label{font-size:.75rem;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-top:.25rem}
.info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;margin-bottom:2rem}
.info-card{background:#1a1d27;border:1px solid #2a2d3e;border-radius:12px;padding:1.25rem}
.info-card .label{font-size:.75rem;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.4rem}
.info-card .value{font-size:1rem;color:#fff;font-weight:600}
.section-title{font-size:1.1rem;font-weight:700;color:#fff;margin-bottom:1rem;padding-bottom:.5rem;border-bottom:1px solid #2a2d3e;margin-top:1.5rem}
.table-wrap{overflow-x:auto;border-radius:12px;border:1px solid #2a2d3e}
table{width:100%;border-collapse:collapse;font-size:.875rem}
th{background:#12141e;color:#9ca3af;font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;padding:.75rem 1rem;text-align:left}
td{padding:.85rem 1rem;border-bottom:1px solid #1f2232;color:#d1d5db}
tr:last-child td{border-bottom:none}
tr:hover td{background:#1f2232}
.form-card{background:#1a1d27;border:1px solid #2a2d3e;border-radius:16px;padding:2rem;max-width:480px}
.form-group{margin-bottom:1.2rem}
label{display:block;font-size:.8rem;font-weight:600;color:#9ca3af;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.5px}
input[type=text],input[type=email],input[type=password]{width:100%;padding:.75rem 1rem;background:#0f1117;border:1px solid #2a2d3e;border-radius:8px;color:#fff;font-size:.95rem;outline:none;transition:border-color .2s,box-shadow .2s}
input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.15)}
input::placeholder{color:#4b5563}
.alert{padding:.85rem 1rem;border-radius:8px;font-size:.875rem;margin-bottom:1.5rem}
.alert-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#4ade80}
.alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171}
.btn-sm{padding:.3rem .75rem;border-radius:6px;font-size:.75rem;font-weight:600;text-decoration:none;cursor:pointer;border:none;display:inline-block}
.btn-delete{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171}
.btn-delete:hover{background:rgba(239,68,68,.2)}
.btn-promote{background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.3);color:#a5b4fc}
.btn-promote:hover{background:rgba(99,102,241,.25)}
.btn-demote{background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3);color:#fbbf24}
.btn-demote:hover{background:rgba(251,191,36,.2)}
.self-tag{color:#6b7280;font-size:.75rem;font-style:italic}
.action-group{display:flex;gap:.4rem;flex-wrap:wrap}
</style>
</head>
<body>

<nav>
  <div class="nav-brand">Emp<span>DB</span> Manager</div>
  <div class="nav-right">
    <div class="nav-user">
      Welcome, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>
      <span class="badge <?= $is_admin ? 'badge-admin' : 'badge-user' ?>"><?= $is_admin ? 'Admin' : 'User' ?></span>
    </div>
    <a href="logout.php" class="btn-logout">Logout</a>
  </div>
</nav>

<div class="tab-bar">
  <button class="tab active" onclick="switchTab('home',this)">🏠 Home</button>
  <?php if($is_admin): ?>
  <button class="tab" onclick="switchTab('stats',this)">📊 Stats</button>
  <button class="tab" onclick="switchTab('users',this)">👥 Users</button>
  <?php endif; ?>
  <button class="tab" onclick="switchTab('profile',this)">👤 Profile</button>
  <button class="tab" onclick="switchTab('password',this)">🔒 Password</button>
</div>

<div class="main">

  <?php if($msg): ?>
  <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- HOME -->
  <div id="tab-home" class="tab-content active">
    <div class="welcome-card">
      <div>
        <h2>👋 Welcome back, <?= htmlspecialchars(explode(' ',$_SESSION['user_name'])[0]) ?>!</h2>
        <p>Logged in as <strong><?= htmlspecialchars($_SESSION['user_email']) ?></strong></p>
      </div>
      <a href="dbmanager.php" class="btn-primary">Open DB Manager →</a>
    </div>
    <div class="info-grid">
      <div class="info-card"><div class="label">Full Name</div><div class="value"><?= htmlspecialchars($_SESSION['user_name']) ?></div></div>
      <div class="info-card"><div class="label">Email</div><div class="value"><?= htmlspecialchars($_SESSION['user_email']) ?></div></div>
      <div class="info-card"><div class="label">Role</div><div class="value"><?= ucfirst($_SESSION['user_role']) ?></div></div>
      <?php if($profile): ?>
      <div class="info-card"><div class="label">City</div><div class="value"><?= htmlspecialchars($profile['City']) ?></div></div>
      <div class="info-card"><div class="label">University</div><div class="value"><?= htmlspecialchars($profile['University']) ?></div></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- STATS -->
  <?php if($is_admin): ?>
  <div id="tab-stats" class="tab-content">
    <div class="stats-grid">
      <div class="stat-card"><div class="stat-icon">👨‍💼</div><div class="stat-value"><?= $stats['employees'] ?></div><div class="stat-label">Employees</div></div>
      <div class="stat-card"><div class="stat-icon">🏢</div><div class="stat-value"><?= $stats['departments'] ?></div><div class="stat-label">Departments</div></div>
      <div class="stat-card"><div class="stat-icon">📁</div><div class="stat-value"><?= $stats['projects'] ?></div><div class="stat-label">Projects</div></div>
      <div class="stat-card"><div class="stat-icon">👤</div><div class="stat-value"><?= $stats['users'] ?></div><div class="stat-label">Registered Users</div></div>
      <div class="stat-card"><div class="stat-icon">💰</div><div class="stat-value">$<?= number_format($stats['avg_sal'],0) ?></div><div class="stat-label">Avg Salary</div></div>
    </div>
  </div>

  <!-- USERS -->
  <div id="tab-users" class="tab-content">
    <div class="section-title" style="margin-top:0">All Registered Users</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Name</th><th>Email</th><th>City</th><th>University</th><th>Role</th><th>Registered</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($users as $i => $u): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><?= htmlspecialchars($u['FullName']) ?></td>
            <td><?= htmlspecialchars($u['Email']) ?></td>
            <td><?= htmlspecialchars($u['City']) ?></td>
            <td><?= htmlspecialchars($u['University']) ?></td>
            <td><span class="badge <?= $u['Role']==='admin'?'badge-admin':'badge-user' ?>"><?= ucfirst($u['Role']) ?></span></td>
            <td><?php $d=$u['CreatedAt']; echo ($d instanceof DateTime)?$d->format('Y-m-d'):htmlspecialchars((string)$d); ?></td>
            <td>
              <?php if($u['UserID'] != $_SESSION['user_id']): ?>
              <div class="action-group">
                <?php if($u['Role']==='user'): ?>
                  <a href="?promote=<?= $u['UserID'] ?>&role=admin" class="btn-sm btn-promote" onclick="return confirm('Promote to Admin?')">▲ Admin</a>
                <?php else: ?>
                  <a href="?promote=<?= $u['UserID'] ?>&role=user" class="btn-sm btn-demote" onclick="return confirm('Demote to User?')">▼ User</a>
                <?php endif; ?>
                <a href="?delete_user=<?= $u['UserID'] ?>" class="btn-sm btn-delete" onclick="return confirm('Delete <?= htmlspecialchars($u['FullName']) ?>?')">Delete</a>
              </div>
              <?php else: ?><span class="self-tag">You</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- PROFILE -->
  <div id="tab-profile" class="tab-content">
    <div class="section-title" style="margin-top:0">Edit Profile</div>
    <div class="form-card">
      <form method="POST">
        <input type="hidden" name="action" value="update_profile">
        <div class="form-group"><label>Full Name</label><input type="text" name="fullname" value="<?= htmlspecialchars($profile['FullName'] ?? '') ?>" required></div>
        <div class="form-group"><label>Email (cannot change)</label><input type="email" value="<?= htmlspecialchars($profile['Email'] ?? '') ?>" disabled style="opacity:.5;cursor:not-allowed"></div>
        <div class="form-group"><label>City</label><input type="text" name="city" value="<?= htmlspecialchars($profile['City'] ?? '') ?>" required></div>
        <div class="form-group"><label>University / Company</label><input type="text" name="university" value="<?= htmlspecialchars($profile['University'] ?? '') ?>" required></div>
        <button type="submit" class="btn-primary">Save Changes</button>
      </form>
    </div>
  </div>

  <!-- PASSWORD -->
  <div id="tab-password" class="tab-content">
    <div class="section-title" style="margin-top:0">Change Password</div>
    <div class="form-card">
      <form method="POST">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group"><label>Current Password</label><input type="password" name="old_password" placeholder="••••••••" required></div>
        <div class="form-group"><label>New Password</label><input type="password" name="new_password" placeholder="Min 8 chars, 1 uppercase, 1 number" required></div>
        <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" placeholder="Repeat new password" required></div>
        <button type="submit" class="btn-primary">Change Password</button>
      </form>
    </div>
  </div>

</div>

<script>
function switchTab(name, el) {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  el.classList.add('active');
}
</script>
</body>
</html>
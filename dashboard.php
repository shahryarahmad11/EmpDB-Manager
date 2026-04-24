<?php
/*
 * dashboard.php
 * User & admin dashboard for EmpDB Manager.
 * Handles: profile, password change, user management (admin), activity log (admin).
 *
 * Project by Shahryar Ahmad
 */
require_once 'auth.php';
require_once 'conn.php';
require_once 'Functions.php';

$is_admin = ($_SESSION['user_role'] === 'admin');
$users    = array();
$stats    = array();
$msg      = '';
$msg_type = '';

/* -----------------------------------------------
   ADMIN: Delete user
----------------------------------------------- */
if ($is_admin && isset($_GET['delete_user'])) {
    $del_id = (int)$_GET['delete_user'];
    if ($del_id !== (int)$_SESSION['user_id']) {
        // Grab name before deleting for the log
        $c   = get_connection();
        $res = sqlsrv_query($c, "SELECT FullName, Email FROM Users WHERE UserID = ?", array($del_id));
        $du  = $res ? sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC) : null;

        sqlsrv_query($c, "DELETE FROM Users WHERE UserID = ?", array($del_id));
        sqlsrv_close($c);

        if ($du) {
            write_activity_log('DELETE_USER',
                "Deleted user: {$du['FullName']} (Email: {$du['Email']}, UserID: $del_id)"
            );
        }
        $msg      = "User deleted successfully.";
        $msg_type = 'success';
    }
}

/* -----------------------------------------------
   ADMIN: Promote / demote user
----------------------------------------------- */
if ($is_admin && isset($_GET['promote'])) {
    $pid  = (int)$_GET['promote'];
    $role = (isset($_GET['role']) && $_GET['role'] === 'admin') ? 'admin' : 'user';

    if ($pid !== (int)$_SESSION['user_id']) {
        $c   = get_connection();
        $res = sqlsrv_query($c, "SELECT FullName FROM Users WHERE UserID = ?", array($pid));
        $pu  = $res ? sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC) : null;

        sqlsrv_query($c, "UPDATE Users SET Role = ? WHERE UserID = ?", array($role, $pid));
        sqlsrv_close($c);

        $label = ($role === 'admin') ? 'Promoted to Admin' : 'Demoted to User';
        write_activity_log('UPDATE_ROLE',
            "$label: " . ($pu ? $pu['FullName'] : "UserID=$pid")
        );
        $msg      = "User role updated.";
        $msg_type = 'success';
    }
}

/* -----------------------------------------------
   ADMIN: Block / unblock user
----------------------------------------------- */
if ($is_admin && isset($_GET['toggle_block'])) {
    $bid = (int)$_GET['toggle_block'];
    if ($bid !== (int)$_SESSION['user_id']) {
        $c   = get_connection();
        $res = sqlsrv_query($c, "SELECT FullName, IsBlocked FROM Users WHERE UserID = ?", array($bid));
        $bu  = $res ? sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC) : null;

        if ($bu) {
            $new_block = empty($bu['IsBlocked']) ? 1 : 0;
            sqlsrv_query($c, "UPDATE Users SET IsBlocked = ? WHERE UserID = ?", array($new_block, $bid));
            $action_label = $new_block ? 'BLOCK_USER' : 'UNBLOCK_USER';
            $detail_label = $new_block ? 'Blocked' : 'Unblocked';
            write_activity_log($action_label, "$detail_label user: {$bu['FullName']} (UserID=$bid)");
            $msg      = "User " . strtolower($detail_label) . " successfully.";
            $msg_type = 'success';
        }
        sqlsrv_close($c);
    }
}

/* -----------------------------------------------
   Change password
----------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $old = $_POST['old_password']     ?? '';
    $new = $_POST['new_password']     ?? '';
    $cnf = $_POST['confirm_password'] ?? '';

    if (
        strlen($new) < 8 ||
        !preg_match('/[A-Z]/', $new) ||
        !preg_match('/[0-9]/', $new)
    ) {
        $msg      = "New password must be 8+ chars with 1 uppercase and 1 number.";
        $msg_type = 'error';
    } elseif ($new !== $cnf) {
        $msg      = "New passwords do not match.";
        $msg_type = 'error';
    } else {
        $c   = get_connection();
        $res = sqlsrv_query($c, "SELECT Password FROM Users WHERE UserID = ?", array($_SESSION['user_id']));
        $row = $res ? sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC) : null;

        if ($row && password_verify($old, $row['Password'])) {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            sqlsrv_query($c, "UPDATE Users SET Password = ? WHERE UserID = ?", array($hash, $_SESSION['user_id']));
            write_activity_log('CHANGE_PASSWORD', 'User changed their account password.');
            $msg      = "Password changed successfully!";
            $msg_type = 'success';
        } else {
            $msg      = "Current password is incorrect.";
            $msg_type = 'error';
        }
        sqlsrv_close($c);
    }
}

/* -----------------------------------------------
   Update profile
----------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $fn   = trim($_POST['fullname']   ?? '');
    $city = trim($_POST['city']       ?? '');
    $uni  = trim($_POST['university'] ?? '');

    if ($fn && $city && $uni) {
        $c = get_connection();
        sqlsrv_query($c,
            "UPDATE Users SET FullName = ?, City = ?, University = ? WHERE UserID = ?",
            array($fn, $city, $uni, $_SESSION['user_id'])
        );
        sqlsrv_close($c);

        $_SESSION['user_name'] = $fn;
        write_activity_log('UPDATE_PROFILE',
            "Updated profile — FullName: $fn, City: $city, University: $uni"
        );
        $msg      = "Profile updated!";
        $msg_type = 'success';
    } else {
        $msg      = "All fields are required.";
        $msg_type = 'error';
    }
}

/* -----------------------------------------------
   Load data
----------------------------------------------- */
$c       = get_connection();
$res     = sqlsrv_query($c, "SELECT * FROM Users WHERE UserID = ?", array($_SESSION['user_id']));
$profile = $res ? sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC) : null;

if ($is_admin) {
    $r = sqlsrv_query($c, "SELECT COUNT(*) AS cnt FROM EMP");
    $stats['employees']   = $r ? sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)['cnt'] : 0;

    $r = sqlsrv_query($c, "SELECT COUNT(*) AS cnt FROM DEPT");
    $stats['departments'] = $r ? sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)['cnt'] : 0;

    $r = sqlsrv_query($c, "SELECT COUNT(*) AS cnt FROM Project");
    $stats['projects']    = $r ? sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)['cnt'] : 0;

    $r = sqlsrv_query($c, "SELECT COUNT(*) AS cnt FROM Users");
    $stats['users']       = $r ? sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)['cnt'] : 0;

    $r = sqlsrv_query($c, "SELECT AVG(SAL) AS avg FROM EMP");
    $stats['avg_sal']     = $r ? round(sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)['avg'], 2) : 0;

    $res2 = sqlsrv_query($c,
        "SELECT UserID, FullName, Email, City, University, Role, IsBlocked, CreatedAt
         FROM Users ORDER BY CreatedAt DESC"
    );
    while ($row = sqlsrv_fetch_array($res2, SQLSRV_FETCH_ASSOC)) {
        $users[] = $row;
    }

    // Activity log (latest 100)
    $activity_rows = array();
    $act_res = sqlsrv_query($c,
        "SELECT TOP 100 UserName, Action, Details, IPAddress, LogTime
         FROM ActivityLog ORDER BY LogTime DESC"
    );
    if ($act_res) {
        while ($ar = sqlsrv_fetch_array($act_res, SQLSRV_FETCH_ASSOC)) {
            $activity_rows[] = $ar;
        }
    }
}

sqlsrv_close($c);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EmpDB Manager</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:#0f1117;color:#e5e7eb;font-family:Segoe UI,sans-serif;min-height:100vh}
        nav{background:#1a1d27;border-bottom:1px solid #2a2d3e;padding:0 2rem;display:flex;align-items:center;justify-content:space-between;height:60px;position:sticky;top:0;z-index:100}
        .nav-brand{font-size:1.2rem;font-weight:700;color:#fff}.nav-brand span{color:#6366f1}
        .nav-right{display:flex;align-items:center;gap:1rem;flex-wrap:wrap}
        .nav-user{font-size:.875rem;color:#9ca3af}.nav-user strong{color:#fff}
        .badge{display:inline-block;padding:.15rem .5rem;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
        .badge-admin{background:rgba(99,102,241,.2);color:#a5b4fc;border:1px solid rgba(99,102,241,.3)}
        .badge-user{background:rgba(34,197,94,.15);color:#4ade80;border:1px solid rgba(34,197,94,.3)}
        .btn-logout{padding:.4rem 1rem;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;border-radius:6px;font-size:.875rem;font-weight:600;text-decoration:none}
        .btn-logout:hover{background:rgba(239,68,68,.2)}
        .tab-bar{background:#1a1d27;border-bottom:1px solid #2a2d3e;display:flex;gap:0;padding:0 2rem;overflow-x:auto}
        .tab{padding:.85rem 1.25rem;font-size:.875rem;font-weight:600;color:#6b7280;cursor:pointer;border-bottom:2px solid transparent;transition:all .2s;background:none;border-top:none;border-left:none;border-right:none;white-space:nowrap}
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
        thead tr{background:#12141e}
        th{color:#9ca3af;font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;padding:.75rem 1rem;text-align:left}
        td{padding:.85rem 1rem;border-bottom:1px solid #1f2232;color:#d1d5db}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:#1f2232}
        .form-card{background:#1a1d27;border:1px solid #2a2d3e;border-radius:16px;padding:2rem;max-width:480px}
        .form-group{margin-bottom:1.2rem}
        .form-group label{display:block;font-size:.8rem;font-weight:600;color:#9ca3af;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.5px}
        .form-group input[type=text],.form-group input[type=email],.form-group input[type=password]{width:100%;padding:.75rem 1rem;background:#0f1117;border:1px solid #2a2d3e;border-radius:8px;color:#fff;font-size:.95rem;outline:none;transition:border-color .2s,box-shadow .2s}
        .form-group input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.15)}
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
        .btn-block{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171}
        .btn-block:hover{background:rgba(239,68,68,.2)}
        .btn-unblock{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#4ade80}
        .btn-unblock:hover{background:rgba(34,197,94,.2)}
        .self-tag{color:#6b7280;font-size:.75rem;font-style:italic}
        .action-group{display:flex;gap:.4rem;flex-wrap:wrap}
        /* Activity log styles */
        .log-badge{display:inline-block;padding:.15rem .55rem;border-radius:20px;font-size:.7rem;font-weight:700;letter-spacing:.4px;text-transform:uppercase}
        .log-LOGIN,.log-AUTO_LOGIN,.log-SESSION_RESTORED{background:rgba(34,197,94,.12);color:#4ade80;border:1px solid rgba(34,197,94,.25)}
        .log-LOGOUT{background:rgba(156,163,175,.1);color:#9ca3af;border:1px solid rgba(156,163,175,.2)}
        .log-SIGNUP{background:rgba(99,102,241,.15);color:#a5b4fc;border:1px solid rgba(99,102,241,.3)}
        .log-INSERT_EMP,.log-INSERT_DEPT{background:rgba(16,185,129,.12);color:#6ee7b7;border:1px solid rgba(16,185,129,.25)}
        .log-UPDATE_EMP,.log-UPDATE_PROFILE,.log-UPDATE_ROLE{background:rgba(245,158,11,.1);color:#fcd34d;border:1px solid rgba(245,158,11,.25)}
        .log-DELETE_EMP,.log-DELETE_USER{background:rgba(239,68,68,.1);color:#f87171;border:1px solid rgba(239,68,68,.25)}
        .log-CHANGE_PASSWORD{background:rgba(139,92,246,.1);color:#c4b5fd;border:1px solid rgba(139,92,246,.25)}
        .log-BLOCK_USER{background:rgba(239,68,68,.12);color:#fca5a5;border:1px solid rgba(239,68,68,.3)}
        .log-UNBLOCK_USER{background:rgba(34,197,94,.1);color:#86efac;border:1px solid rgba(34,197,94,.2)}
        .log-SEARCH_EMP,.log-VIEW_TABLE,.log-FULL_JOIN_VIEW{background:rgba(59,130,246,.1);color:#93c5fd;border:1px solid rgba(59,130,246,.2)}
        .log-EXPORT_CSV{background:rgba(20,184,166,.1);color:#5eead4;border:1px solid rgba(20,184,166,.2)}
        .log-LOGIN_FAILED,.log-LOGIN_BLOCKED,.log-SIGNUP_FAILED,.log-SESSION_RESTORE_BLOCKED,.log-AUTO_LOGIN_BLOCKED{background:rgba(239,68,68,.15);color:#fca5a5;border:1px solid rgba(239,68,68,.3)}
        .log-default{background:rgba(107,114,128,.1);color:#9ca3af;border:1px solid rgba(107,114,128,.2)}
        .log-filter-bar{display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem;align-items:center}
        .log-filter-bar input,.log-filter-bar select{background:#0f1117;border:1px solid #2a2d3e;border-radius:6px;color:#e5e7eb;padding:.5rem .85rem;font-size:.85rem;outline:none}
        .log-filter-bar input:focus,.log-filter-bar select:focus{border-color:#6366f1}
        .log-filter-bar button{padding:.5rem 1rem;background:#6366f1;color:#fff;border:none;border-radius:6px;font-size:.85rem;font-weight:600;cursor:pointer}
        .log-filter-bar button:hover{background:#4f46e5}
        .blocked-badge{display:inline-block;padding:.1rem .45rem;border-radius:10px;font-size:.65rem;font-weight:700;text-transform:uppercase;background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3);margin-left:.3rem}
    </style>
</head>
<body>

<!-- NAV -->
<nav>
    <div class="nav-brand">Emp<span>DB</span> Manager</div>
    <div class="nav-right">
        <div class="nav-user">
            Welcome, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>
            <span class="badge <?php echo $is_admin ? 'badge-admin' : 'badge-user'; ?>">
                <?php echo $is_admin ? 'Admin' : 'User'; ?>
            </span>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<!-- TABS -->
<div class="tab-bar">
    <button class="tab active" onclick="switchTab('home',this)">Home</button>
    <?php if ($is_admin): ?>
    <button class="tab" onclick="switchTab('stats',this)">Stats</button>
    <button class="tab" onclick="switchTab('users',this)">Users</button>
    <button class="tab" onclick="switchTab('activity',this)">Activity Log</button>
    <?php endif; ?>
    <button class="tab" onclick="switchTab('profile',this)">Profile</button>
    <button class="tab" onclick="switchTab('password',this)">Password</button>
</div>

<div class="main">

<?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg_type; ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<!-- HOME -->
<div id="tab-home" class="tab-content active">
    <div class="welcome-card">
        <div>
            <h2>👋 Welcome back, <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?>!</h2>
            <p>Logged in as <strong><?php echo htmlspecialchars($_SESSION['user_email']); ?></strong></p>
        </div>
        <a href="dbmanager.php" class="btn-primary">Open DB Manager</a>
    </div>

    <div class="info-grid">
        <div class="info-card">
            <div class="label">Full Name</div>
            <div class="value"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
        </div>
        <div class="info-card">
            <div class="label">Email</div>
            <div class="value"><?php echo htmlspecialchars($_SESSION['user_email']); ?></div>
        </div>
        <div class="info-card">
            <div class="label">Role</div>
            <div class="value"><?php echo ucfirst($_SESSION['user_role']); ?></div>
        </div>
        <?php if ($profile): ?>
        <div class="info-card">
            <div class="label">City</div>
            <div class="value"><?php echo htmlspecialchars($profile['City']); ?></div>
        </div>
        <div class="info-card">
            <div class="label">University</div>
            <div class="value"><?php echo htmlspecialchars($profile['University']); ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- STATS (admin only) -->
<?php if ($is_admin): ?>
<div id="tab-stats" class="tab-content">
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon">👥</div><div class="stat-value"><?php echo $stats['employees']; ?></div><div class="stat-label">Employees</div></div>
        <div class="stat-card"><div class="stat-icon">🏢</div><div class="stat-value"><?php echo $stats['departments']; ?></div><div class="stat-label">Departments</div></div>
        <div class="stat-card"><div class="stat-icon">📁</div><div class="stat-value"><?php echo $stats['projects']; ?></div><div class="stat-label">Projects</div></div>
        <div class="stat-card"><div class="stat-icon">🧑‍💻</div><div class="stat-value"><?php echo $stats['users']; ?></div><div class="stat-label">Registered Users</div></div>
        <div class="stat-card"><div class="stat-icon">💰</div><div class="stat-value">$<?php echo number_format($stats['avg_sal'], 0); ?></div><div class="stat-label">Avg Salary</div></div>
    </div>
</div>

<!-- USERS (admin only) -->
<div id="tab-users" class="tab-content">
    <div class="section-title" style="margin-top:0">All Registered Users</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Name</th><th>Email</th><th>City</th>
                    <th>University</th><th>Role</th><th>Status</th><th>Registered</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $i => $u): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td>
                        <?php echo htmlspecialchars($u['FullName']); ?>
                        <?php if (!empty($u['IsBlocked'])): ?>
                            <span class="blocked-badge">Blocked</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($u['Email']); ?></td>
                    <td><?php echo htmlspecialchars($u['City']); ?></td>
                    <td><?php echo htmlspecialchars($u['University']); ?></td>
                    <td>
                        <span class="badge <?php echo $u['Role'] === 'admin' ? 'badge-admin' : 'badge-user'; ?>">
                            <?php echo ucfirst($u['Role']); ?>
                        </span>
                    </td>
                    <td><?php echo empty($u['IsBlocked']) ? '<span style="color:#4ade80">Active</span>' : '<span style="color:#f87171">Blocked</span>'; ?></td>
                    <td>
                        <?php
                        $d = $u['CreatedAt'];
                        echo $d instanceof DateTime
                            ? $d->format('Y-m-d')
                            : htmlspecialchars((string)$d);
                        ?>
                    </td>
                    <td>
                        <?php if ($u['UserID'] !== $_SESSION['user_id']): ?>
                        <div class="action-group">
                            <?php if ($u['Role'] === 'user'): ?>
                                <a href="?promote=<?php echo $u['UserID']; ?>&role=admin"
                                   class="btn-sm btn-promote"
                                   onclick="return confirm('Promote <?php echo htmlspecialchars($u['FullName']); ?> to Admin?')">Admin</a>
                            <?php else: ?>
                                <a href="?promote=<?php echo $u['UserID']; ?>&role=user"
                                   class="btn-sm btn-demote"
                                   onclick="return confirm('Demote <?php echo htmlspecialchars($u['FullName']); ?> to User?')">User</a>
                            <?php endif; ?>

                            <?php if (empty($u['IsBlocked'])): ?>
                                <a href="?toggle_block=<?php echo $u['UserID']; ?>"
                                   class="btn-sm btn-block"
                                   onclick="return confirm('Block <?php echo htmlspecialchars($u['FullName']); ?>?')">Block</a>
                            <?php else: ?>
                                <a href="?toggle_block=<?php echo $u['UserID']; ?>"
                                   class="btn-sm btn-unblock"
                                   onclick="return confirm('Unblock <?php echo htmlspecialchars($u['FullName']); ?>?')">Unblock</a>
                            <?php endif; ?>

                            <a href="?delete_user=<?php echo $u['UserID']; ?>"
                               class="btn-sm btn-delete"
                               onclick="return confirm('Delete <?php echo htmlspecialchars($u['FullName']); ?>? This cannot be undone.')">Delete</a>
                        </div>
                        <?php else: ?>
                            <span class="self-tag">You</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ACTIVITY LOG (admin only) -->
<div id="tab-activity" class="tab-content">
    <div class="section-title" style="margin-top:0">Activity Log</div>

    <div class="log-filter-bar">
        <input type="text" id="log-search" placeholder="Search user or details..." oninput="filterLog()">
        <select id="log-action-filter" onchange="filterLog()">
            <option value="">All Actions</option>
            <option value="LOGIN">LOGIN</option>
            <option value="AUTO_LOGIN">AUTO_LOGIN</option>
            <option value="SESSION_RESTORED">SESSION_RESTORED</option>
            <option value="LOGOUT">LOGOUT</option>
            <option value="SIGNUP">SIGNUP</option>
            <option value="INSERT_EMP">INSERT_EMP</option>
            <option value="UPDATE_EMP">UPDATE_EMP</option>
            <option value="DELETE_EMP">DELETE_EMP</option>
            <option value="INSERT_DEPT">INSERT_DEPT</option>
            <option value="DELETE_USER">DELETE_USER</option>
            <option value="UPDATE_ROLE">UPDATE_ROLE</option>
            <option value="BLOCK_USER">BLOCK_USER</option>
            <option value="UNBLOCK_USER">UNBLOCK_USER</option>
            <option value="UPDATE_PROFILE">UPDATE_PROFILE</option>
            <option value="CHANGE_PASSWORD">CHANGE_PASSWORD</option>
            <option value="SEARCH_EMP">SEARCH_EMP</option>
            <option value="VIEW_TABLE">VIEW_TABLE</option>
            <option value="FULL_JOIN_VIEW">FULL_JOIN_VIEW</option>
            <option value="EXPORT_CSV">EXPORT_CSV</option>
            <option value="LOGIN_FAILED">LOGIN_FAILED</option>
            <option value="LOGIN_BLOCKED">LOGIN_BLOCKED</option>
            <option value="SIGNUP_FAILED">SIGNUP_FAILED</option>
        </select>
        <button onclick="clearLogFilter()">Clear</button>
    </div>

    <?php if (empty($activity_rows)): ?>
        <p style="color:#6b7280;font-style:italic;padding:1rem 0">No activity recorded yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table id="log-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($activity_rows as $ac => $row): ?>
                <tr>
                    <td><?php echo $ac + 1; ?></td>
                    <td><?php echo htmlspecialchars($row['UserName']); ?></td>
                    <td>
                        <?php
                        $action_key = htmlspecialchars($row['Action']);
                        echo "<span class='log-badge log-$action_key'>$action_key</span>";
                        ?>
                    </td>
                    <td style="max-width:340px;word-break:break-word"><?php echo htmlspecialchars($row['Details']); ?></td>
                    <td style="font-family:monospace;font-size:.8rem"><?php echo htmlspecialchars($row['IPAddress']); ?></td>
                    <td style="white-space:nowrap;font-size:.8rem">
                        <?php
                        $t = $row['LogTime'];
                        echo $t instanceof DateTime
                            ? $t->format('Y-m-d H:i:s')
                            : htmlspecialchars((string)$t);
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- PROFILE -->
<div id="tab-profile" class="tab-content">
    <div class="section-title" style="margin-top:0">Edit Profile</div>
    <div class="form-card">
        <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="fullname"
                       value="<?php echo htmlspecialchars($profile['FullName'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label>Email (cannot change)</label>
                <input type="email" value="<?php echo htmlspecialchars($profile['Email'] ?? ''); ?>"
                       disabled style="opacity:.5;cursor:not-allowed">
            </div>
            <div class="form-group">
                <label>City</label>
                <input type="text" name="city"
                       value="<?php echo htmlspecialchars($profile['City'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label>University / Company</label>
                <input type="text" name="university"
                       value="<?php echo htmlspecialchars($profile['University'] ?? ''); ?>" required>
            </div>
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
            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="old_password" required>
            </div>
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password"
                       placeholder="Min 8 chars, 1 uppercase, 1 number" required>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password"
                       placeholder="Repeat new password" required>
            </div>
            <button type="submit" class="btn-primary">Change Password</button>
        </form>
    </div>
</div>

</div><!-- end .main -->

<script>
function switchTab(name, el) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    el.classList.add('active');
}

function filterLog() {
    const search = document.getElementById('log-search').value.toLowerCase();
    const action = document.getElementById('log-action-filter').value.toLowerCase();
    const rows   = document.querySelectorAll('#log-table tbody tr');

    rows.forEach(row => {
        const user    = row.cells[1].textContent.toLowerCase();
        const act     = row.cells[2].textContent.toLowerCase();
        const details = row.cells[3].textContent.toLowerCase();

        const matchSearch = !search || user.includes(search) || details.includes(search);
        const matchAction = !action || act.includes(action);

        row.style.display = (matchSearch && matchAction) ? '' : 'none';
    });
}

function clearLogFilter() {
    document.getElementById('log-search').value = '';
    document.getElementById('log-action-filter').value = '';
    filterLog();
}
</script>
</body>
</html>
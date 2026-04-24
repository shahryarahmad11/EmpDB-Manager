<?php
/*
 * dashboard.php
 * User & admin dashboard for EmpDB Manager.
 * Updated: Open DB Manager button, Activity Log tab fix, Block/Unblock users.
 */
require_once 'auth.php';
require_once 'conn.php';
require_once 'functions.php';

$is_admin = $_SESSION['user_role'] === 'admin';
$users    = array();
$stats    = array();
$msg      = '';
$msgtype  = '';

/* ── Handle actions ── */

// Delete user (admin only)
if ($is_admin && isset($_GET['delete_user'])) {
    $delid = (int)$_GET['delete_user'];
    if ($delid !== (int)$_SESSION['user_id']) {
        $c = get_connection();
        sqlsrv_query($c, "DELETE FROM Users WHERE UserID = ?", array($delid));
        sqlsrv_close($c);
        log_activity('DELETE_USER', "Deleted user UserID=$delid");
        $msg = "User deleted successfully.";
        $msgtype = 'success';
    }
}

// Promote / demote user (admin only)
if ($is_admin && isset($_GET['promote'])) {
    $pid  = (int)$_GET['promote'];
    $role = ($_GET['role'] ?? '') === 'admin' ? 'admin' : 'user';
    if ($pid !== (int)$_SESSION['user_id']) {
        $c = get_connection();
        sqlsrv_query($c, "UPDATE Users SET Role = ? WHERE UserID = ?", array($role, $pid));
        sqlsrv_close($c);
        log_activity('CHANGE_ROLE', "Changed UserID=$pid to role=$role");
        $msg = "User role updated.";
        $msgtype = 'success';
    }
}

// Block user (admin only)
if ($is_admin && isset($_GET['block_user'])) {
    $bid = (int)$_GET['block_user'];
    if ($bid !== (int)$_SESSION['user_id']) {
        $c = get_connection();
        sqlsrv_query(
            $c,
            "UPDATE Users SET IsBlocked = 1, BlockReason = ?, BlockedAt = GETDATE(), BlockedBy = ? WHERE UserID = ?",
            array('Blocked by admin', $_SESSION['user_id'], $bid)
        );
        sqlsrv_close($c);
        log_activity('BLOCK_USER', "Blocked UserID=$bid");
        $msg = "User blocked successfully.";
        $msgtype = 'success';
    }
}

// Unblock user (admin only)
if ($is_admin && isset($_GET['unblock_user'])) {
    $uid = (int)$_GET['unblock_user'];
    if ($uid !== (int)$_SESSION['user_id']) {
        $c = get_connection();
        sqlsrv_query($c, "UPDATE Users SET IsBlocked = 0, BlockReason = NULL, BlockedAt = NULL, BlockedBy = NULL WHERE UserID = ?", array($uid));
        sqlsrv_close($c);
        log_activity('UNBLOCK_USER', "Unblocked UserID=$uid");
        $msg = "User unblocked successfully.";
        $msgtype = 'success';
    }
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $old = $_POST['old_password'];
    $new = $_POST['new_password'];
    $cnf = $_POST['confirm_password'];

    if (strlen($new) < 8 || !preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new)) {
        $msg = "New password must be 8+ chars with 1 uppercase and 1 number.";
        $msgtype = 'error';
    } elseif ($new !== $cnf) {
        $msg = "New passwords do not match.";
        $msgtype = 'error';
    } else {
        $c   = get_connection();
        $res = sqlsrv_query($c, "SELECT Password FROM Users WHERE UserID = ?", array($_SESSION['user_id']));
        $row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
        if ($row && password_verify($old, $row['Password'])) {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            sqlsrv_query($c, "UPDATE Users SET Password = ? WHERE UserID = ?", array($hash, $_SESSION['user_id']));
            log_activity('CHANGE_PASSWORD', 'User changed their password');
            $msg = "Password changed successfully!";
            $msgtype = 'success';
        } else {
            $msg = "Current password is incorrect.";
            $msgtype = 'error';
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
        sqlsrv_query($c, "UPDATE Users SET FullName=?, City=?, University=? WHERE UserID=?", array($fn, $city, $uni, $_SESSION['user_id']));
        sqlsrv_close($c);
        $_SESSION['user_name'] = $fn;
        log_activity('UPDATE_PROFILE', "Profile updated: FullName=$fn");
        $msg = "Profile updated!";
        $msgtype = 'success';
    } else {
        $msg = "All fields required.";
        $msgtype = 'error';
    }
}

/* ── Load data ── */
$c       = get_connection();
$res     = sqlsrv_query($c, "SELECT * FROM Users WHERE UserID = ?", array($_SESSION['user_id']));
$profile = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);

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
    $stats['avg_sal'] = round(sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)['avg'] ?? 0, 2);

    $res2 = sqlsrv_query($c, "SELECT UserID, FullName, Email, City, University, Role, IsBlocked, CreatedAt FROM Users ORDER BY CreatedAt DESC");
    while ($row = sqlsrv_fetch_array($res2, SQLSRV_FETCH_ASSOC)) {
        $users[] = $row;
    }
}
sqlsrv_close($c);

// Activity log
$log_filter_action = isset($_GET['log_action']) ? $_GET['log_action'] : '';
$log_filter_user   = isset($_GET['log_user']) ? $_GET['log_user'] : '';
$activity_log      = get_activity_log(200, $log_filter_action, $log_filter_user);

// Greeting
$first_name = explode(' ', $_SESSION['user_name'])[0];
$hour = (int)date('H');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 17) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

// Determine initial tab
$initial_tab = isset($_GET['tab']) ? $_GET['tab'] : 'home';
$allowed_tabs = $is_admin
    ? array('home', 'stats', 'users', 'log', 'profile', 'password')
    : array('home', 'log', 'profile', 'password');
if (!in_array($initial_tab, $allowed_tabs)) {
    $initial_tab = 'home';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — EmpDB Manager</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=IBM+Plex+Sans:wght@300;400;600;700&display=swap');

        :root {
            --bg: #0f1117;
            --surface: #1a1d27;
            --surface2: #222639;
            --border: #2e3350;
            --accent: #6366f1;
            --accent2: #7c5cfc;
            --success: #3ecf8e;
            --danger: #f46060;
            --warning: #f5a623;
            --text: #e2e8f0;
            --dim: #8892a4;
            --mono: 'IBM Plex Mono', monospace;
            --sans: 'IBM Plex Sans', sans-serif;
        }

        * , *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--sans);
            font-size: 14px;
            min-height: 100vh;
        }

        nav {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-brand {
            font-size: 1.2rem;
            font-weight: 700;
            color: #fff;
        }

        .nav-brand span {
            color: var(--accent);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .nav-user {
            font-size: .875rem;
            color: var(--dim);
        }

        .nav-user strong {
            color: #fff;
        }

        .badge {
            display: inline-block;
            padding: .15rem .5rem;
            border-radius: 20px;
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .badge-admin {
            background: rgba(99,102,241,.2);
            color: #a5b4fc;
            border: 1px solid rgba(99,102,241,.3);
        }

        .badge-user {
            background: rgba(34,197,94,.15);
            color: #4ade80;
            border: 1px solid rgba(34,197,94,.3);
        }

        .badge-blocked {
            background: rgba(239,68,68,.15);
            color: #f87171;
            border: 1px solid rgba(239,68,68,.3);
        }

        .btn-logout {
            padding: .4rem 1rem;
            background: rgba(239,68,68,.1);
            border: 1px solid rgba(239,68,68,.3);
            color: #f87171;
            border-radius: 6px;
            font-size: .875rem;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-logout:hover {
            background: rgba(239,68,68,.2);
        }

        .tab-bar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 0;
            padding: 0 2rem;
        }

        .tab {
            padding: .85rem 1.25rem;
            font-size: .875rem;
            font-weight: 600;
            color: #6b7280;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all .2s;
            background: none;
            border-top: none;
            border-left: none;
            border-right: none;
        }

        .tab:hover {
            color: var(--text);
        }

        .tab.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }

        .main {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .welcome-card {
            background: linear-gradient(135deg, #1e1b4b, #1a1d27);
            border: 1px solid #3730a3;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .welcome-card h2 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #fff;
        }

        .welcome-card p {
            color: #a5b4fc;
            font-size: .9rem;
            margin-top: .3rem;
        }

        .btn-actions {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
        }

        .btn-primary {
            padding: .75rem 1.5rem;
            background: var(--accent);
            color: #fff;
            border-radius: 8px;
            font-size: .95rem;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s;
            display: inline-block;
            border: none;
            cursor: pointer;
        }

        .btn-primary:hover {
            background: #4f46e5;
        }

        .btn-secondary {
            padding: .75rem 1.5rem;
            background: var(--surface2);
            color: var(--text);
            border-radius: 8px;
            font-size: .95rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--border);
            display: inline-block;
            transition: background .2s;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: var(--border);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
        }

        .stat-icon {
            font-size: 1.8rem;
            margin-bottom: .5rem;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: #fff;
        }

        .stat-label {
            font-size: .75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-top: .25rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
        }

        .info-card .label {
            font-size: .75rem;
            color: var(--dim);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: .4rem;
        }

        .info-card .value {
            font-size: 1rem;
            color: #fff;
            font-weight: 600;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 1rem;
            padding-bottom: .5rem;
            border-bottom: 1px solid var(--border);
            margin-top: 1.5rem;
        }

        .table-wrap {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: .875rem;
        }

        thead tr {
            background: var(--surface2);
        }

        th {
            padding: .75rem 1rem;
            text-align: left;
            font-family: var(--mono);
            font-size: .75rem;
            font-weight: 600;
            color: var(--dim);
            text-transform: uppercase;
            letter-spacing: .5px;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: .85rem 1rem;
            border-bottom: 1px solid var(--border);
            color: #d1d5db;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: var(--surface2);
        }

        .form-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            max-width: 480px;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: var(--dim);
            margin-bottom: .4rem;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: .75rem 1rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: #fff;
            font-size: .95rem;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(99,102,241,.15);
        }

        input::placeholder {
            color: #4b5563;
        }

        input:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .alert {
            padding: .85rem 1rem;
            border-radius: 8px;
            font-size: .875rem;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: rgba(34,197,94,.1);
            border: 1px solid rgba(34,197,94,.3);
            color: #4ade80;
        }

        .alert-error {
            background: rgba(239,68,68,.1);
            border: 1px solid rgba(239,68,68,.3);
            color: #f87171;
        }

        .btn-sm {
            padding: .3rem .75rem;
            border-radius: 6px;
            font-size: .75rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            display: inline-block;
        }

        .btn-delete {
            background: rgba(239,68,68,.1);
            border: 1px solid rgba(239,68,68,.3);
            color: #f87171;
        }

        .btn-delete:hover {
            background: rgba(239,68,68,.2);
        }

        .btn-promote {
            background: rgba(99,102,241,.15);
            border: 1px solid rgba(99,102,241,.3);
            color: #a5b4fc;
        }

        .btn-promote:hover {
            background: rgba(99,102,241,.25);
        }

        .btn-demote {
            background: rgba(251,191,36,.1);
            border: 1px solid rgba(251,191,36,.3);
            color: #fbbf24;
        }

        .btn-demote:hover {
            background: rgba(251,191,36,.2);
        }

        .btn-warning {
            background: rgba(245,166,35,.12);
            border: 1px solid rgba(245,166,35,.3);
            color: #fbbf24;
        }

        .btn-warning:hover {
            background: rgba(245,166,35,.2);
        }

        .btn-success {
            background: rgba(34,197,94,.12);
            border: 1px solid rgba(34,197,94,.3);
            color: #4ade80;
        }

        .btn-success:hover {
            background: rgba(34,197,94,.2);
        }

        .self-tag {
            color: #6b7280;
            font-size: .75rem;
            font-style: italic;
        }

        .action-group {
            display: flex;
            gap: .4rem;
            flex-wrap: wrap;
        }

        .log-filters {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 1rem;
        }

        .log-filters select,
        .log-filters input[type="text"] {
            max-width: 180px;
            padding: .5rem .75rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            font-size: .875rem;
            outline: none;
        }

        .log-filters select:focus,
        .log-filters input:focus {
            border-color: var(--accent);
        }

        .action-badge {
            display: inline-block;
            padding: .15rem .5rem;
            border-radius: 4px;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .5px;
            font-family: var(--mono);
        }

        .action-INSERT_EMP      { background: rgba(62,207,142,.12); color: var(--success); }
        .action-INSERT_DEPT     { background: rgba(62,207,142,.12); color: var(--success); }
        .action-UPDATE_EMP      { background: rgba(245,166,35,.12); color: var(--warning); }
        .action-UPDATE_PROFILE  { background: rgba(79,142,247,.12); color: #4f8ef7; }
        .action-CHANGE_PASSWORD { background: rgba(124,92,252,.12); color: var(--accent2); }
        .action-DELETE_EMP      { background: rgba(244,96,96,.12); color: var(--danger); }
        .action-DELETE_USER     { background: rgba(244,96,96,.12); color: var(--danger); }
        .action-CHANGE_ROLE     { background: rgba(245,166,35,.12); color: var(--warning); }
        .action-BLOCK_USER      { background: rgba(244,96,96,.12); color: var(--danger); }
        .action-UNBLOCK_USER    { background: rgba(62,207,142,.12); color: var(--success); }
        .action-default         { background: rgba(136,146,164,.12); color: var(--dim); }
    </style>
</head>
<body>

<nav>
    <div class="nav-brand">Emp<span>DB</span> Manager</div>
    <div class="nav-right">
        <div class="nav-user">
            <?php echo htmlspecialchars($greeting); ?>, <strong><?php echo htmlspecialchars($first_name); ?></strong>
            <span class="badge <?php echo $is_admin ? 'badge-admin' : 'badge-user'; ?>">
                <?php echo $is_admin ? 'Admin' : 'User'; ?>
            </span>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="tab-bar">
    <button class="tab" data-tab="home" onclick="switchTab('home',this)">Home</button>
    <?php if ($is_admin): ?>
        <button class="tab" data-tab="stats" onclick="switchTab('stats',this)">Stats</button>
        <button class="tab" data-tab="users" onclick="switchTab('users',this)">Users</button>
    <?php endif; ?>
    <button class="tab" data-tab="log" onclick="switchTab('log',this)">Activity Log</button>
    <button class="tab" data-tab="profile" onclick="switchTab('profile',this)">Profile</button>
    <button class="tab" data-tab="password" onclick="switchTab('password',this)">Password</button>
</div>

<div class="main">
    <?php if ($msg): ?>
        <div class="alert alert-<?php echo $msgtype; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <div id="tab-home" class="tab-content">
        <div class="welcome-card">
            <div>
                <h2>👋 <?php echo htmlspecialchars($greeting); ?>, <?php echo htmlspecialchars($first_name); ?>!</h2>
                <p>Logged in as <strong><?php echo htmlspecialchars($_SESSION['user_email']); ?></strong></p>
            </div>
            <div class="btn-actions">
                <a href="dbmanager.php" class="btn-primary">Open DB Manager</a>
                <a href="#" class="btn-secondary" onclick="switchTab('log', document.querySelector('[data-tab=log]')); return false;">Activity Log</a>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-card"><div class="label">Full Name</div><div class="value"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div></div>
            <div class="info-card"><div class="label">Email</div><div class="value"><?php echo htmlspecialchars($_SESSION['user_email']); ?></div></div>
            <div class="info-card"><div class="label">Role</div><div class="value"><?php echo ucfirst($_SESSION['user_role']); ?></div></div>
            <?php if ($profile): ?>
                <div class="info-card"><div class="label">City</div><div class="value"><?php echo htmlspecialchars($profile['City'] ?? ''); ?></div></div>
                <div class="info-card"><div class="label">University</div><div class="value"><?php echo htmlspecialchars($profile['University'] ?? ''); ?></div></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_admin): ?>
    <div id="tab-stats" class="tab-content">
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon">👥</div><div class="stat-value"><?php echo $stats['employees']; ?></div><div class="stat-label">Employees</div></div>
            <div class="stat-card"><div class="stat-icon">🏢</div><div class="stat-value"><?php echo $stats['departments']; ?></div><div class="stat-label">Departments</div></div>
            <div class="stat-card"><div class="stat-icon">📁</div><div class="stat-value"><?php echo $stats['projects']; ?></div><div class="stat-label">Projects</div></div>
            <div class="stat-card"><div class="stat-icon">🔐</div><div class="stat-value"><?php echo $stats['users']; ?></div><div class="stat-label">Registered Users</div></div>
            <div class="stat-card"><div class="stat-icon">💰</div><div class="stat-value">$<?php echo number_format($stats['avg_sal'], 0); ?></div><div class="stat-label">Avg Salary</div></div>
        </div>
    </div>

    <div id="tab-users" class="tab-content">
        <div class="section-title" style="margin-top:0">All Registered Users</div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>City</th>
                        <th>University</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $u): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars($u['FullName']); ?></td>
                        <td><?php echo htmlspecialchars($u['Email']); ?></td>
                        <td><?php echo htmlspecialchars($u['City'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($u['University'] ?? ''); ?></td>
                        <td>
                            <?php if (!empty($u['IsBlocked'])): ?>
                                <span class="badge badge-blocked">Blocked</span>
                            <?php else: ?>
                                <span class="badge <?php echo $u['Role'] === 'admin' ? 'badge-admin' : 'badge-user'; ?>"><?php echo ucfirst($u['Role']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $d = $u['CreatedAt'];
                            echo $d instanceof DateTime ? $d->format('Y-m-d') : htmlspecialchars((string)$d);
                            ?>
                        </td>
                        <td>
                            <?php if ($u['UserID'] !== $_SESSION['user_id']): ?>
                                <div class="action-group">
                                    <?php if ($u['Role'] === 'user'): ?>
                                        <a href="?promote=<?php echo $u['UserID']; ?>&role=admin&tab=users" class="btn-sm btn-promote" onclick="return confirm('Promote to Admin?')">Admin</a>
                                    <?php else: ?>
                                        <a href="?promote=<?php echo $u['UserID']; ?>&role=user&tab=users" class="btn-sm btn-demote" onclick="return confirm('Demote to User?')">User</a>
                                    <?php endif; ?>

                                    <?php if (!empty($u['IsBlocked'])): ?>
                                        <a href="?unblock_user=<?php echo $u['UserID']; ?>&tab=users" class="btn-sm btn-success" onclick="return confirm('Unblock this user?')">Unblock</a>
                                    <?php else: ?>
                                        <a href="?block_user=<?php echo $u['UserID']; ?>&tab=users" class="btn-sm btn-warning" onclick="return confirm('Block this user?')">Block</a>
                                    <?php endif; ?>

                                    <a href="?delete_user=<?php echo $u['UserID']; ?>&tab=users" class="btn-sm btn-delete" onclick="return confirm('Delete <?php echo htmlspecialchars($u['FullName']); ?>?')">Delete</a>
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
    <?php endif; ?>

    <div id="tab-log" class="tab-content">
        <div class="section-title" style="margin-top:0">
            Activity Log <?php echo $is_admin ? '(All Users)' : '(Your Actions)'; ?>
        </div>

        <?php if ($is_admin): ?>
        <div class="log-filters">
            <div>
                <label style="font-size:.7rem;margin-bottom:.2rem;display:block;color:var(--dim)">ACTION</label>
                <select id="filter-action">
                    <option value="">All Actions</option>
                    <?php foreach (array('INSERT_EMP','INSERT_DEPT','UPDATE_EMP','DELETE_EMP','DELETE_USER','CHANGE_ROLE','BLOCK_USER','UNBLOCK_USER','UPDATE_PROFILE','CHANGE_PASSWORD') as $a): ?>
                        <option value="<?php echo $a; ?>" <?php echo $log_filter_action === $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:.7rem;margin-bottom:.2rem;display:block;color:var(--dim)">USER NAME</label>
                <input type="text" id="filter-user" placeholder="Search name..." value="<?php echo htmlspecialchars($log_filter_user); ?>">
            </div>
            <button class="btn-primary" style="padding:.5rem 1rem;font-size:.85rem" onclick="applyLogFilter()">Filter</button>
            <button class="btn-secondary" style="padding:.5rem 1rem;font-size:.85rem" onclick="resetLogFilter()">Reset</button>
        </div>
        <?php endif; ?>

        <?php if (empty($activity_log)): ?>
            <p style="color:var(--dim);font-style:italic">No activity recorded yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <?php if ($is_admin): ?><th>User</th><?php endif; ?>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activity_log as $i => $entry): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <?php if ($is_admin): ?><td><?php echo htmlspecialchars($entry['UserName']); ?></td><?php endif; ?>
                            <td>
                                <?php
                                $ac = htmlspecialchars($entry['Action']);
                                $cls = 'action-' . $ac;
                                echo "<span class='action-badge $cls'>$ac</span>";
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($entry['Details'] ?? ''); ?></td>
                            <td style="font-family:var(--mono);font-size:.75rem;color:var(--dim)"><?php echo htmlspecialchars($entry['IPAddress'] ?? ''); ?></td>
                            <td style="font-family:var(--mono);font-size:.75rem;color:var(--dim)">
                                <?php
                                $t = $entry['CreatedAt'];
                                echo $t instanceof DateTime ? $t->format('Y-m-d H:i:s') : htmlspecialchars((string)$t);
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div id="tab-profile" class="tab-content">
        <div class="section-title" style="margin-top:0">Edit Profile</div>
        <div class="form-card">
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group"><label>Full Name</label><input type="text" name="fullname" value="<?php echo htmlspecialchars($profile['FullName'] ?? ''); ?>" required></div>
                <div class="form-group"><label>Email (cannot change)</label><input type="email" value="<?php echo htmlspecialchars($profile['Email'] ?? ''); ?>" disabled></div>
                <div class="form-group"><label>City</label><input type="text" name="city" value="<?php echo htmlspecialchars($profile['City'] ?? ''); ?>" required></div>
                <div class="form-group"><label>University / Company</label><input type="text" name="university" value="<?php echo htmlspecialchars($profile['University'] ?? ''); ?>" required></div>
                <button type="submit" class="btn-primary">Save Changes</button>
            </form>
        </div>
    </div>

    <div id="tab-password" class="tab-content">
        <div class="section-title" style="margin-top:0">Change Password</div>
        <div class="form-card">
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group"><label>Current Password</label><input type="password" name="old_password" required placeholder="••••••••"></div>
                <div class="form-group"><label>New Password</label><input type="password" name="new_password" required placeholder="Min 8 chars, 1 uppercase, 1 number"></div>
                <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" required placeholder="Repeat new password"></div>
                <button type="submit" class="btn-primary">Change Password</button>
            </form>
        </div>
    </div>
</div>

<script>
function switchTab(name, el) {
    document.querySelectorAll('.tab-content').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('.tab').forEach(function(t) { t.classList.remove('active'); });
    document.getElementById('tab-' + name).classList.add('active');
    if (el) el.classList.add('active');
}

(function () {
    var initial = '<?php echo $initial_tab; ?>';
    var btn = document.querySelector('[data-tab="' + initial + '"]');
    switchTab(initial, btn);
})();

function applyLogFilter() {
    var action = document.getElementById('filter-action').value;
    var user = document.getElementById('filter-user').value;
    var url = new URL(window.location.href);
    url.searchParams.set('tab', 'log');
    if (action) url.searchParams.set('log_action', action);
    else url.searchParams.delete('log_action');
    if (user) url.searchParams.set('log_user', user);
    else url.searchParams.delete('log_user');
    window.location.href = url.toString();
}

function resetLogFilter() {
    var url = new URL(window.location.href);
    url.searchParams.set('tab', 'log');
    url.searchParams.delete('log_action');
    url.searchParams.delete('log_user');
    window.location.href = url.toString();
}
</script>

</body>
</html>
<?php
require_once 'auth.php';
require_once 'conn.php';

$is_admin = ($_SESSION['user_role'] === 'admin');
$users    = array();

// Handle delete (admin only)
if ($is_admin && isset($_GET['delete_user'])) {
    $del_id = (int) $_GET['delete_user'];
    if ($del_id !== (int)$_SESSION['user_id']) {
        $conn = get_connection();
        sqlsrv_query($conn, "DELETE FROM Users WHERE UserID = ?", array($del_id));
        sqlsrv_close($conn);
    }
    header("Location: dashboard.php");
    exit();
}

if ($is_admin) {
    $conn   = get_connection();
    $result = sqlsrv_query($conn,
        "SELECT UserID, FullName, Email, City, University, Role, CreatedAt
         FROM Users ORDER BY CreatedAt DESC");
    while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
        $users[] = $row;
    }
    sqlsrv_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — EmpDB Manager</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #0f1117; color: #e5e7eb; font-family: 'Segoe UI', sans-serif; min-height: 100vh; }

  nav {
    background: #1a1d27;
    border-bottom: 1px solid #2a2d3e;
    padding: 0 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 60px;
    position: sticky;
    top: 0;
    z-index: 100;
  }
  .nav-brand { font-size: 1.2rem; font-weight: 700; color: #fff; }
  .nav-brand span { color: #6366f1; }
  .nav-right { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
  .nav-user { font-size: 0.875rem; color: #9ca3af; }
  .nav-user strong { color: #fff; }
  .badge {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .badge-admin { background: rgba(99,102,241,0.2); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.3); }
  .badge-user  { background: rgba(34,197,94,0.15); color: #4ade80; border: 1px solid rgba(34,197,94,0.3); }
  .btn-logout {
    padding: 0.4rem 1rem;
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.3);
    color: #f87171;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.2s;
  }
  .btn-logout:hover { background: rgba(239,68,68,0.2); }

  .main { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem; }

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
  .welcome-card h2 { font-size: 1.4rem; font-weight: 700; color: #fff; }
  .welcome-card p  { color: #a5b4fc; font-size: 0.9rem; margin-top: 0.3rem; }

  .btn-primary {
    padding: 0.75rem 1.5rem;
    background: #6366f1;
    color: #fff;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.2s;
    display: inline-block;
  }
  .btn-primary:hover { background: #4f46e5; }

  .info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
  }
  .info-card {
    background: #1a1d27;
    border: 1px solid #2a2d3e;
    border-radius: 12px;
    padding: 1.25rem;
  }
  .info-card .label { font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.4rem; }
  .info-card .value { font-size: 1rem; color: #fff; font-weight: 600; }

  .section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #2a2d3e;
  }

  .table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid #2a2d3e; }
  table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
  th {
    background: #12141e;
    color: #9ca3af;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.75rem 1rem;
    text-align: left;
  }
  td { padding: 0.85rem 1rem; border-bottom: 1px solid #1f2232; color: #d1d5db; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: #1f2232; }

  .btn-delete {
    padding: 0.3rem 0.75rem;
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.3);
    color: #f87171;
    border-radius: 6px;
    font-size: 0.75rem;
    text-decoration: none;
    font-weight: 600;
  }
  .btn-delete:hover { background: rgba(239,68,68,0.2); }
  .self-tag { color: #6b7280; font-size: 0.75rem; font-style: italic; }
</style>
</head>
<body>

<nav>
  <div class="nav-brand">Emp<span>DB</span> Manager</div>
  <div class="nav-right">
    <div class="nav-user">
      Welcome, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>
      <span class="badge <?= $is_admin ? 'badge-admin' : 'badge-user' ?>">
        <?= $is_admin ? 'Admin' : 'User' ?>
      </span>
    </div>
    <a href="logout.php" class="btn-logout">Logout</a>
  </div>
</nav>

<div class="main">

  <div class="welcome-card">
    <div>
      <h2>👋 Welcome back, <?= htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]) ?>!</h2>
      <p>Logged in as <strong><?= htmlspecialchars($_SESSION['user_email']) ?></strong></p>
    </div>
    <a href="Index.php" class="btn-primary">Open DB Manager →</a>
  </div>

  <div class="info-grid">
    <div class="info-card">
      <div class="label">Full Name</div>
      <div class="value"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
    </div>
    <div class="info-card">
      <div class="label">Email</div>
      <div class="value"><?= htmlspecialchars($_SESSION['user_email']) ?></div>
    </div>
    <div class="info-card">
      <div class="label">Role</div>
      <div class="value"><?= ucfirst($_SESSION['user_role']) ?></div>
    </div>
  </div>

  <?php if ($is_admin): ?>
  <div class="section-title">👥 All Registered Users</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Full Name</th>
          <th>Email</th>
          <th>City</th>
          <th>University / Company</th>
          <th>Role</th>
          <th>Registered</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $i => $u): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($u['FullName']) ?></td>
          <td><?= htmlspecialchars($u['Email']) ?></td>
          <td><?= htmlspecialchars($u['City']) ?></td>
          <td><?= htmlspecialchars($u['University']) ?></td>
          <td>
            <span class="badge <?= $u['Role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>">
              <?= ucfirst($u['Role']) ?>
            </span>
          </td>
          <td>
            <?php
              $date = $u['CreatedAt'];
              echo ($date instanceof DateTime) ? $date->format('Y-m-d') : htmlspecialchars((string)$date);
            ?>
          </td>
          <td>
            <?php if ($u['UserID'] != $_SESSION['user_id']): ?>
              <a href="dashboard.php?delete_user=<?= $u['UserID'] ?>"
                 class="btn-delete"
                 onclick="return confirm('Delete <?= htmlspecialchars($u['FullName']) ?>?')">
                Delete
              </a>
            <?php else: ?>
              <span class="self-tag">You</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
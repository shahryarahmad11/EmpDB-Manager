<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 0,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode'  => true,
    ]);
}

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

if (isset($_COOKIE['remember_token'])) {
    require_once 'conn.php';
    $c   = get_connection();
    $res = sqlsrv_query($c,
        "SELECT UserID, FullName, Email, Role FROM Users WHERE RememberToken = ?",
        array($_COOKIE['remember_token'])
    );
    if ($res) {
        $u = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
        if ($u) {
            $_SESSION['user_id']    = $u['UserID'];
            $_SESSION['user_name']  = $u['FullName'];
            $_SESSION['user_email'] = $u['Email'];
            $_SESSION['user_role']  = $u['Role'];
            sqlsrv_close($c);
            header("Location: dashboard.php");
            exit();
        }
    }
    sqlsrv_close($c);
}

require_once 'conn.php';
$error     = '';
$role_hint = (isset($_GET['role']) && $_GET['role'] === 'admin') ? 'admin' : 'user';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember_me']);

    if (empty($email) || empty($password)) {
        $error = "Please enter your email and password.";
    } else {
        $c    = get_connection();
        $res  = sqlsrv_query($c, "SELECT * FROM Users WHERE Email = ?", array($email));
        $user = $res ? sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC) : null;

        if ($user && password_verify($password, $user['Password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']         = $user['UserID'];
            $_SESSION['user_name']       = $user['FullName'];
            $_SESSION['user_role']       = $user['Role'];
            $_SESSION['user_email']      = $user['Email'];
            $_SESSION['last_regenerated']= time();

            if ($remember) {
                $token = bin2hex(random_bytes(32));
                sqlsrv_query($c,
                    "UPDATE Users SET RememberToken = ? WHERE UserID = ?",
                    array($token, $user['UserID'])
                );
                setcookie('remember_token', $token, [
                    'expires'  => time() + (30 * 24 * 3600),
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Strict',
                ]);
            } else {
                sqlsrv_query($c,
                    "UPDATE Users SET RememberToken = NULL WHERE UserID = ?",
                    array($user['UserID'])
                );
                if (isset($_COOKIE['remember_token'])) {
                    setcookie('remember_token', '', time() - 3600, '/');
                }
            }
            sqlsrv_close($c);
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid email or password.";
        }
        sqlsrv_close($c);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — EmpDB Manager</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    min-height: 100vh;
    background-color: #0f1117;
    background-image: url('assets/bg.jpg');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Segoe UI', sans-serif;
    padding: 1rem;
}

body::before {
    content: '';
    position: fixed;
    inset: 0;
    background: rgba(10, 11, 18, 0.80);
    backdrop-filter: blur(2px);
    pointer-events: none;
    z-index: 0;
}
.card {
    position: relative;
    z-index: 1;
    background: rgba(26, 29, 39, 0.85); /* slightly transparent */
    backdrop-filter: blur(12px);
    border: 1px solid rgba(99,102,241,0.2);
    border-radius: 16px;
    padding: 2.5rem;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.6);
}
.logo { text-align: center; margin-bottom: 1.5rem; }
.logo h1 { font-size: 1.8rem; font-weight: 700; color: #fff; }
.logo h1 span { color: #6366f1; }
.role-badge {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .35rem .9rem; border-radius: 20px;
    font-size: .8rem; font-weight: 600; margin-top: .6rem;
}
.role-badge.admin { background: rgba(99,102,241,.15); color: #a5b4fc; border: 1px solid rgba(99,102,241,.3); }
.role-badge.user  { background: rgba(34,197,94,.12);  color: #4ade80; border: 1px solid rgba(34,197,94,.3); }
.form-group { margin-bottom: 1.2rem; }
label {
    display: block; font-size: .8rem; font-weight: 600;
    color: #9ca3af; margin-bottom: .4rem;
    text-transform: uppercase; letter-spacing: .5px;
}
input[type=email], input[type=password] {
    width: 100%; padding: .75rem 1rem; background: #0f1117;
    border: 1px solid #2a2d3e; border-radius: 8px;
    color: #fff; font-size: .95rem; outline: none;
    transition: border-color .2s, box-shadow .2s;
}
input[type=email]:focus,
input[type=password]:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
input::placeholder { color: #4b5563; }
.remember-row {
    display: flex; align-items: center; gap: .6rem;
    margin-bottom: 1.2rem;
}
.remember-row input[type=checkbox] {
    width: 16px; height: 16px; accent-color: #6366f1; cursor: pointer;
    flex-shrink: 0;
}
.remember-row label {
    font-size: .875rem; color: #9ca3af; text-transform: none;
    letter-spacing: 0; margin: 0; cursor: pointer; font-weight: 400;
}
.btn {
    width: 100%; padding: .85rem; background: #6366f1;
    color: #fff; border: none; border-radius: 8px;
    font-size: 1rem; font-weight: 600; cursor: pointer;
    transition: background .2s;
}
.btn:hover { background: #4f46e5; }
.alert-error {
    padding: .85rem 1rem; border-radius: 8px; font-size: .875rem;
    margin-bottom: 1.2rem; background: rgba(239,68,68,.1);
    border: 1px solid rgba(239,68,68,.3); color: #f87171;
}
.footer-links {
    text-align: center; margin-top: 1.5rem;
    font-size: .875rem; color: #6b7280;
    display: flex; flex-direction: column; gap: .5rem;
}
.footer-links a { color: #6366f1; text-decoration: none; font-weight: 600; }
.footer-links a:hover { text-decoration: underline; }
.back-link { color: #4b5563 !important; font-size: .8rem; font-weight: 400 !important; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <h1>Emp<span>DB</span> Manager</h1>
    <div>
      <span class="role-badge <?= $role_hint ?>">
        <?= $role_hint === 'admin' ? '🛡️ Signing in as Administrator' : '👤 Signing in as User' ?>
      </span>
    </div>
  </div>

  <?php if ($error): ?>
  <div class="alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="on">
    <div class="form-group">
      <label>Email Address</label>
      <input type="email" name="email"
             placeholder="you@example.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             autocomplete="username"
             required autofocus>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password"
             placeholder="••••••••"
             autocomplete="current-password"
             required>
    </div>

    <div class="remember-row">
      <input type="checkbox" name="remember_me" id="remember_me">
      <label for="remember_me">Remember me for 30 days</label>
    </div>

    <button type="submit" class="btn">Sign In →</button>
  </form>

  <div class="footer-links">
    <span>Don't have an account? <a href="signup.php">Sign Up</a></span>
    <a href="index.php" class="back-link">← Back to home</a>
  </div>
</div>
</body>
</html>
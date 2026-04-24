<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

if (!isset($_SESSION['last_regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['last_regenerated'] = time();
} elseif (time() - $_SESSION['last_regenerated'] > 900) {
    session_regenerate_id(true);
    $_SESSION['last_regenerated'] = time();
}

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

if (isset($_COOKIE['remember_token'])) {
    require_once 'conn.php';
    $c = get_connection();
    $res = sqlsrv_query(
        $c,
        "SELECT UserID, FullName, Email, Role, IsBlocked FROM Users WHERE RememberToken = ?",
        array($_COOKIE['remember_token'])
    );

    if ($res) {
        $u = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
        if ($u) {
            if (!empty($u['IsBlocked'])) {
                sqlsrv_query($c, "UPDATE Users SET RememberToken = NULL WHERE UserID = ?", array($u['UserID']));
                setcookie('remember_token', '', time() - 3600, '/');
            } else {
                $_SESSION['user_id'] = $u['UserID'];
                $_SESSION['user_name'] = $u['FullName'];
                $_SESSION['user_email'] = $u['Email'];
                $_SESSION['user_role'] = $u['Role'];
                sqlsrv_close($c);
                header("Location: dashboard.php");
                exit();
            }
        }
    }
    sqlsrv_close($c);
}

require_once 'conn.php';
$error = '';
$role_hint = (isset($_GET['role']) && $_GET['role'] === 'admin') ? 'admin' : 'user';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember_me']);

    if (empty($email) || empty($password)) {
        $error = "Please enter your email and password.";
    } else {
        $c = get_connection();
        $res = sqlsrv_query($c, "SELECT * FROM Users WHERE Email = ?", array($email));
        $user = $res ? sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC) : null;

        if ($user && !empty($user['IsBlocked'])) {
            if (!empty($user['RememberToken'])) {
                sqlsrv_query($c, "UPDATE Users SET RememberToken = NULL WHERE UserID = ?", array($user['UserID']));
            }
            if (isset($_COOKIE['remember_token'])) {
                setcookie('remember_token', '', time() - 3600, '/');
            }
            $error = "Your account has been blocked by the administrator.";
        } elseif ($user && password_verify($password, $user['Password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['UserID'];
            $_SESSION['user_name'] = $user['FullName'];
            $_SESSION['user_role'] = $user['Role'];
            $_SESSION['user_email'] = $user['Email'];
            $_SESSION['last_regenerated'] = time();

            if ($remember) {
                $token = bin2hex(random_bytes(32));
                sqlsrv_query($c, "UPDATE Users SET RememberToken = ? WHERE UserID = ?", array($token, $user['UserID']));
                setcookie('remember_token', $token, [
                    'expires' => time() + (30 * 24 * 3600),
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Strict',
                ]);
            } else {
                sqlsrv_query($c, "UPDATE Users SET RememberToken = NULL WHERE UserID = ?", array($user['UserID']));
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
  <title>Login | EmpDB Manager</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap');

    :root {
      --bg: #0a0c11;
      --panel: rgba(20, 24, 33, 0.92);
      --panel-2: rgba(25, 30, 41, 0.96);
      --border: rgba(255,255,255,0.08);
      --accent: #5b8cff;
      --accent-2: #7c5cff;
      --text: #ecf2ff;
      --text-dim: #9aa8c7;
      --danger: #ff6b6b;
      --success: #3ecf8e;
      --shadow: 0 20px 60px rgba(0,0,0,0.45);
      --mono: 'IBM Plex Mono', monospace;
      --sans: 'IBM Plex Sans', sans-serif;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: var(--sans);
      min-height: 100vh;
      background:
        radial-gradient(circle at 20% 20%, rgba(91,140,255,0.18), transparent 32%),
        radial-gradient(circle at 80% 30%, rgba(124,92,252,0.14), transparent 28%),
        linear-gradient(160deg, #090b10 0%, #0d1118 45%, #0b0f15 100%);
      color: var(--text);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 30px 18px;
    }

    .shell {
      width: 100%;
      max-width: 1150px;
      min-height: 680px;
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      border: 1px solid var(--border);
      border-radius: 28px;
      overflow: hidden;
      background: rgba(11, 14, 20, 0.76);
      backdrop-filter: blur(16px);
      box-shadow: var(--shadow);
    }

    .hero {
      position: relative;
      padding: 54px 48px;
      background:
        linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0)),
        linear-gradient(135deg, rgba(91,140,255,0.12), rgba(124,92,252,0.08));
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      border-right: 1px solid var(--border);
    }

    .hero::after {
      content: '';
      position: absolute;
      inset: 0;
      background-image:
        linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
      background-size: 34px 34px;
      pointer-events: none;
      mask-image: linear-gradient(to bottom, rgba(0,0,0,0.9), rgba(0,0,0,0.25));
    }

    .brand {
      position: relative;
      z-index: 1;
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .brand-mark {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      display: grid;
      place-items: center;
      box-shadow: 0 10px 30px rgba(91,140,255,0.35);
      font-weight: 700;
      font-family: var(--mono);
    }

    .brand h1 {
      font-size: 1.1rem;
      font-weight: 700;
      letter-spacing: 0.3px;
    }

    .brand p {
      font-size: 0.84rem;
      color: var(--text-dim);
      margin-top: 4px;
    }

    .hero-copy {
      position: relative;
      z-index: 1;
      max-width: 480px;
    }

    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-family: var(--mono);
      font-size: 0.76rem;
      color: #b7c3e1;
      padding: 8px 12px;
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 999px;
      background: rgba(255,255,255,0.03);
      margin-bottom: 22px;
    }

    .hero-copy h2 {
      font-size: clamp(2rem, 3vw, 3.3rem);
      line-height: 1.05;
      letter-spacing: -1.2px;
      margin-bottom: 18px;
    }

    .hero-copy h2 span {
      background: linear-gradient(135deg, #ffffff, #98b5ff);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .hero-copy p {
      color: var(--text-dim);
      font-size: 1rem;
      line-height: 1.75;
    }

    .info-grid {
      position: relative;
      z-index: 1;
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 14px;
      margin-top: 38px;
    }

    .info-card {
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 18px;
      padding: 18px;
    }

    .info-card h3 {
      font-size: 1.1rem;
      margin-bottom: 6px;
    }

    .info-card p {
      font-size: 0.82rem;
      color: var(--text-dim);
      line-height: 1.55;
    }

    .panel {
      padding: 44px 36px;
      background: linear-gradient(180deg, rgba(17,20,28,0.96), rgba(13,16,24,0.98));
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .login-card {
      width: 100%;
      max-width: 430px;
    }

    .panel-top {
      margin-bottom: 28px;
    }

    .panel-top .mini {
      font-family: var(--mono);
      font-size: 0.76rem;
      color: #a9b7da;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 10px;
    }

    .panel-top h3 {
      font-size: 2rem;
      margin-bottom: 10px;
    }

    .panel-top p {
      color: var(--text-dim);
      line-height: 1.7;
      font-size: 0.95rem;
    }

    .role-switch {
      display: inline-flex;
      background: var(--panel-2);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 5px;
      margin-bottom: 24px;
      gap: 6px;
    }

    .role-switch a {
      text-decoration: none;
      color: var(--text-dim);
      font-size: 0.86rem;
      font-weight: 600;
      padding: 10px 16px;
      border-radius: 10px;
      transition: 0.2s ease;
    }

    .role-switch a.active {
      background: linear-gradient(135deg, rgba(91,140,255,0.18), rgba(124,92,252,0.16));
      color: #fff;
      border: 1px solid rgba(91,140,255,0.28);
    }

    .error-box {
      background: rgba(255,107,107,0.09);
      border: 1px solid rgba(255,107,107,0.24);
      color: #ff9d9d;
      padding: 14px 16px;
      border-radius: 14px;
      margin-bottom: 18px;
      font-size: 0.92rem;
      line-height: 1.5;
    }

    form {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .field {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .field label {
      font-size: 0.82rem;
      color: #b9c4de;
      font-weight: 600;
      letter-spacing: 0.2px;
    }

    .field input {
      width: 100%;
      border: 1px solid rgba(255,255,255,0.08);
      background: rgba(255,255,255,0.03);
      color: var(--text);
      border-radius: 14px;
      padding: 14px 15px;
      font-size: 0.96rem;
      outline: none;
      transition: 0.2s ease;
    }

    .field input:focus {
      border-color: rgba(91,140,255,0.65);
      box-shadow: 0 0 0 4px rgba(91,140,255,0.12);
      background: rgba(255,255,255,0.05);
    }

    .row-between {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
    }

    .remember {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: var(--text-dim);
      font-size: 0.9rem;
    }

    .remember input {
      accent-color: var(--accent);
    }

    .forgot {
      color: #9bb6ff;
      text-decoration: none;
      font-size: 0.88rem;
    }

    .forgot:hover {
      text-decoration: underline;
    }

    .submit-btn {
      width: 100%;
      border: none;
      border-radius: 16px;
      padding: 15px 18px;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      color: white;
      font-size: 0.98rem;
      font-weight: 700;
      cursor: pointer;
      transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
      box-shadow: 0 18px 32px rgba(91,140,255,0.24);
    }

    .submit-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 22px 38px rgba(91,140,255,0.32);
    }

    .submit-btn:active {
      transform: translateY(0);
    }

    .footer-note {
      margin-top: 20px;
      color: var(--text-dim);
      font-size: 0.9rem;
      text-align: center;
      line-height: 1.7;
    }

    .footer-note a {
      color: #a9c0ff;
      text-decoration: none;
      font-weight: 600;
    }

    .footer-note a:hover {
      text-decoration: underline;
    }

    @media (max-width: 980px) {
      .shell {
        grid-template-columns: 1fr;
        max-width: 720px;
      }

      .hero {
        border-right: none;
        border-bottom: 1px solid var(--border);
        padding: 34px 28px;
      }

      .panel {
        padding: 34px 24px;
      }

      .info-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 560px) {
      body { padding: 14px; }
      .hero, .panel { padding: 24px 18px; }
      .panel-top h3 { font-size: 1.7rem; }
      .hero-copy h2 { font-size: 2rem; }
      .row-between { align-items: flex-start; }
      .role-switch { width: 100%; justify-content: space-between; }
      .role-switch a { flex: 1; text-align: center; }
    }
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <div class="brand">
        <div class="brand-mark">ED</div>
        <div>
          <h1>EmpDB Manager</h1>
          <p>Secure employee-department management platform</p>
        </div>
      </div>

      <div class="hero-copy">
        <div class="eyebrow">Secure Access • SQL Server • Admin Control</div>
        <h2>Manage your workforce data with <span>clarity and control</span>.</h2>
        <p>
          Access employee records, department data, project assignments, and administrative controls
          through a clean and secure management portal built for your database lab system.
        </p>

        <div class="info-grid">
          <div class="info-card">
            <h3>Role-Based</h3>
            <p>Separate admin and user access with protected dashboard controls.</p>
          </div>
          <div class="info-card">
            <h3>Secure Login</h3>
            <p>Password hashing, session hardening, and remember-me token support.</p>
          </div>
          <div class="info-card">
            <h3>Live Control</h3>
            <p>Admins can manage users, review logs, and now block suspended accounts.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="panel">
      <div class="login-card">
        <div class="panel-top">
          <div class="mini">Welcome back</div>
          <h3>Sign in</h3>
          <p>Choose the correct portal and enter your credentials to continue.</p>
        </div>

        <div class="role-switch">
          <a href="?role=user" class="<?php echo $role_hint === 'user' ? 'active' : ''; ?>">User Login</a>
          <a href="?role=admin" class="<?php echo $role_hint === 'admin' ? 'active' : ''; ?>">Admin Login</a>
        </div>

        <?php if ($error): ?>
          <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
          <div class="field">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" placeholder="Enter your email" required>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
          </div>

          <div class="row-between">
            <label class="remember">
              <input type="checkbox" name="remember_me">
              <span>Remember me</span>
            </label>
            <a href="#" class="forgot" onclick="return false;">Forgot password?</a>
          </div>

          <button type="submit" class="submit-btn">Login to Dashboard</button>
        </form>

        <div class="footer-note">
          Don’t have an account? <a href="signup.php">Create one here</a>
        </div>
      </div>
    </section>
  </div>
</body>
</html>
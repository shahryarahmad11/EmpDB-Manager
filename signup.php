<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 0,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
    ]);
}
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
require_once 'conn.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname   = trim($_POST['fullname']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];
    $confirm    = $_POST['confirm_password'];
    $city       = trim($_POST['city']);
    $university = trim($_POST['university']);

    if (empty($fullname) || empty($email) || empty($password) || empty($city) || empty($university)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = "Password must be at least 8 characters with 1 uppercase and 1 number.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $c   = get_connection();
        $chk = sqlsrv_query($c, "SELECT UserID FROM Users WHERE Email = ?", array($email));
        if ($chk && sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC)) {
            $error = "An account with this email already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ok   = sqlsrv_query($c,
                "INSERT INTO Users (FullName, Email, Password, City, University, Role)
                 VALUES (?, ?, ?, ?, ?, 'user')",
                array($fullname, $email, $hash, $city, $university)
            );
            if ($ok) {
                $success = "Account created successfully! You can now sign in.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
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
<title>Sign Up — EmpDB Manager</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    min-height: 100vh; background: #0f1117;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Segoe UI', sans-serif; padding: 1.5rem 1rem;
}
.card {
    background: #1a1d27; border: 1px solid #2a2d3e;
    border-radius: 16px; padding: 2.5rem;
    width: 100%; max-width: 460px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
}
.logo { text-align: center; margin-bottom: 2rem; }
.logo h1 { font-size: 1.8rem; font-weight: 700; color: #fff; }
.logo h1 span { color: #6366f1; }
.logo p { color: #6b7280; font-size: .875rem; margin-top: .3rem; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-group { margin-bottom: 1.1rem; }
label {
    display: block; font-size: .8rem; font-weight: 600;
    color: #9ca3af; margin-bottom: .4rem;
    text-transform: uppercase; letter-spacing: .5px;
}
input {
    width: 100%; padding: .75rem 1rem; background: #0f1117;
    border: 1px solid #2a2d3e; border-radius: 8px;
    color: #fff; font-size: .95rem; outline: none;
    transition: border-color .2s, box-shadow .2s;
}
input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
input::placeholder { color: #4b5563; }
.hint { font-size: .75rem; color: #4b5563; margin-top: .3rem; }
.btn {
    width: 100%; padding: .85rem; background: #6366f1;
    color: #fff; border: none; border-radius: 8px;
    font-size: 1rem; font-weight: 600; cursor: pointer;
    transition: background .2s; margin-top: .4rem;
}
.btn:hover { background: #4f46e5; }
.alert { padding: .85rem 1rem; border-radius: 8px; font-size: .875rem; margin-bottom: 1.2rem; }
.alert-error   { background: rgba(239,68,68,.1);  border: 1px solid rgba(239,68,68,.3);  color: #f87171; }
.alert-success { background: rgba(34,197,94,.1);  border: 1px solid rgba(34,197,94,.3);  color: #4ade80; }
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
    <p>Create your account</p>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
  <div class="alert alert-success">
    <?= htmlspecialchars($success) ?>
    <a href="login.php" style="color:#4ade80;font-weight:700;margin-left:.5rem">Login →</a>
  </div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <form method="POST" autocomplete="off">
    <!-- Hidden fake fields trick to stop browser autofill -->
    <input type="text"     style="display:none" aria-hidden="true">
    <input type="password" style="display:none" aria-hidden="true">

    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="fullname"
             placeholder="John Doe"
             autocomplete="off"
             required autofocus>
    </div>

    <div class="form-group">
      <label>Email Address</label>
      <input type="email" name="email"
             placeholder="you@example.com"
             autocomplete="off"
             required>
    </div>

    <div class="row-2">
      <div class="form-group">
        <label>City</label>
        <input type="text" name="city"
               placeholder="Lahore"
               autocomplete="off"
               required>
      </div>
      <div class="form-group">
        <label>University</label>
        <input type="text" name="university"
               placeholder="FAST NUCES"
               autocomplete="off"
               required>
      </div>
    </div>

    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password"
             placeholder="Min 8 chars, 1 uppercase, 1 number"
             autocomplete="new-password"
             required>
      <div class="hint">Min. 8 characters with at least 1 uppercase and 1 number</div>
    </div>

    <div class="form-group">
      <label>Confirm Password</label>
      <input type="password" name="confirm_password"
             placeholder="Repeat password"
             autocomplete="new-password"
             required>
    </div>

    <button type="submit" class="btn">Create Account</button>
  </form>
  <?php endif; ?>

  <div class="footer-links">
    <span>Already have an account? <a href="login.php">Sign In</a></span>
    <a href="index.php" class="back-link">← Back to home</a>
  </div>
</div>
</body>
</html>
<?php
session_start();
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
        $error = "Invalid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least one uppercase letter and one number.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $conn  = get_connection();
        $check = sqlsrv_query($conn, "SELECT UserID FROM Users WHERE Email = ?", array($email));
        if (sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC)) {
            $error = "An account with this email already exists.";
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $sql    = "INSERT INTO Users (FullName, Email, Password, City, University, Role)
                       VALUES (?, ?, ?, ?, ?, 'user')";
            $ok     = sqlsrv_query($conn, $sql, array($fullname, $email, $hashed, $city, $university));
            if ($ok) {
                $success = "Account created! You can now log in.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
        sqlsrv_close($conn);
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
    min-height: 100vh;
    background: #0f1117;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Segoe UI', sans-serif;
    padding: 2rem 1rem;
  }
  .card {
    background: #1a1d27;
    border: 1px solid #2a2d3e;
    border-radius: 16px;
    padding: 2.5rem;
    width: 100%;
    max-width: 480px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
  }
  .logo { text-align: center; margin-bottom: 2rem; }
  .logo h1 { font-size: 1.6rem; font-weight: 700; color: #fff; }
  .logo h1 span { color: #6366f1; }
  .logo p { color: #6b7280; font-size: 0.875rem; margin-top: 0.3rem; }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  .form-group { margin-bottom: 1.2rem; }
  label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: #9ca3af;
    margin-bottom: 0.4rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  input {
    width: 100%;
    padding: 0.75rem 1rem;
    background: #0f1117;
    border: 1px solid #2a2d3e;
    border-radius: 8px;
    color: #fff;
    font-size: 0.95rem;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
  }
  input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
  }
  input::placeholder { color: #4b5563; }
  .btn {
    width: 100%;
    padding: 0.85rem;
    background: #6366f1;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
    margin-top: 0.5rem;
  }
  .btn:hover { background: #4f46e5; }
  .alert { padding: 0.85rem 1rem; border-radius: 8px; font-size: 0.875rem; margin-bottom: 1.2rem; }
  .alert-error   { background: rgba(239,68,68,0.1);  border: 1px solid rgba(239,68,68,0.3);  color: #f87171; }
  .alert-success { background: rgba(34,197,94,0.1);  border: 1px solid rgba(34,197,94,0.3);  color: #4ade80; }
  .footer-link { text-align: center; margin-top: 1.5rem; font-size: 0.875rem; color: #6b7280; }
  .footer-link a { color: #6366f1; text-decoration: none; font-weight: 600; }
  .password-hint { font-size: 0.75rem; color: #6b7280; margin-top: 0.3rem; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <h1>Emp<span>DB</span> Manager</h1>
    <p>Create your account</p>
  </div>

  <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success">
      <?= htmlspecialchars($success) ?> <a href="login.php" style="color:#4ade80;font-weight:700;">Login →</a>
    </div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <form method="POST" autocomplete="off">
    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="fullname" placeholder="Shahryar Ahmad"
             value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label>Email Address</label>
      <input type="email" name="email" placeholder="you@example.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>City</label>
        <input type="text" name="city" placeholder="Lahore"
               value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>University / Company</label>
        <input type="text" name="university" placeholder="FAST NUCES"
               value="<?= htmlspecialchars($_POST['university'] ?? '') ?>" required>
      </div>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" placeholder="Min 8 chars, 1 uppercase, 1 number" required>
      <p class="password-hint">Min. 8 characters with at least 1 uppercase and 1 number</p>
    </div>
    <div class="form-group">
      <label>Confirm Password</label>
      <input type="password" name="confirm_password" placeholder="Repeat your password" required>
    </div>
    <button type="submit" class="btn">Create Account</button>
  </form>
  <?php endif; ?>

  <div class="footer-link">Already have an account? <a href="login.php">Sign In</a></div>
</div>
</body>
</html>
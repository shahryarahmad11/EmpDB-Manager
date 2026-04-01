<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
require_once 'conn.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please enter your email and password.";
    } else {
        $conn   = get_connection();
        $result = sqlsrv_query($conn, "SELECT * FROM Users WHERE Email = ?", array($email));
        $user   = $result ? sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC) : null;

        if ($user && password_verify($password, $user['Password'])) {
            $_SESSION['user_id']    = $user['UserID'];
            $_SESSION['user_name']  = $user['FullName'];
            $_SESSION['user_role']  = $user['Role'];
            $_SESSION['user_email'] = $user['Email'];
            sqlsrv_close($conn);
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid email or password.";
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
<title>Login — EmpDB Manager</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    min-height: 100vh;
    background: #0f1117;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Segoe UI', sans-serif;
    padding: 1rem;
  }
  .card {
    background: #1a1d27;
    border: 1px solid #2a2d3e;
    border-radius: 16px;
    padding: 2.5rem;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
  }
  .logo { text-align: center; margin-bottom: 2rem; }
  .logo h1 { font-size: 1.8rem; font-weight: 700; color: #fff; }
  .logo h1 span { color: #6366f1; }
  .logo p { color: #6b7280; font-size: 0.875rem; margin-top: 0.3rem; }
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
  .alert-error {
    padding: 0.85rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    margin-bottom: 1.2rem;
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.3);
    color: #f87171;
  }
  .footer-link {
    text-align: center;
    margin-top: 1.5rem;
    font-size: 0.875rem;
    color: #6b7280;
  }
  .footer-link a { color: #6366f1; text-decoration: none; font-weight: 600; }
  .footer-link a:hover { text-decoration: underline; }
  .demo-hint {
    background: rgba(99,102,241,0.08);
    border: 1px solid rgba(99,102,241,0.2);
    border-radius: 8px;
    padding: 0.75rem 1rem;
    font-size: 0.8rem;
    color: #a5b4fc;
    margin-bottom: 1.5rem;
    text-align: center;
  }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <h1>Emp<span>DB</span> Manager</h1>
    <p>Sign in to your account</p>
  </div>

  <div class="demo-hint">
    🔐 Admin: <strong>admin@empdb.com</strong> / <strong>Admin@1234</strong>
  </div>

  <?php if ($error): ?>
  <div class="alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>Email Address</label>
      <input type="email" name="email" placeholder="you@example.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn">Sign In →</button>
  </form>

  <div class="footer-link">Don't have an account? <a href="signup.php">Sign Up</a></div>
</div>
</body>
</html>
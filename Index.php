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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EmpDB Manager — Welcome</title>
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
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-family: 'Segoe UI', sans-serif;
    padding: 2rem 1rem;
}

/* Dark overlay so text stays readable */
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background: rgba(10, 11, 18, 0.75);
    backdrop-filter: blur(1px);
    pointer-events: none;
    z-index: 0;
}

.wrapper {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
}

/* LOGO */
.logo {
    text-align: center;
    margin-bottom: 1rem;
}
.logo h1 {
    font-size: 2.4rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -1px;
}
.logo h1 span { color: #6366f1; }
.logo p {
    color: #6b7280;
    margin-top: .5rem;
    font-size: .95rem;
}

/* divider line */
.divider-line {
    width: 60px; height: 2px;
    background: linear-gradient(90deg, transparent, #6366f1, transparent);
    margin: 1.5rem auto 2.5rem;
    border-radius: 2px;
}

.question {
    font-size: 1rem;
    color: #6b7280;
    text-align: center;
    margin-bottom: 2rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-size: .8rem;
}

/* CARDS */
.cards {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    justify-content: center;
    width: 100%;
    max-width: 660px;
}

.role-card {
    background: rgba(26, 29, 39, 0.80);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.06);
    padding: 2.5rem 2rem;
    width: 280px;
    text-align: center;
    cursor: pointer;
    text-decoration: none;
    transition: transform .25s, border-color .25s, box-shadow .25s;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
}

/* glow top border on hover */
.role-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    opacity: 0;
    transition: opacity .25s;
    border-radius: 20px 20px 0 0;
}
.role-card.admin::before { background: linear-gradient(90deg, #6366f1, #818cf8); }
.role-card.user::before  { background: linear-gradient(90deg, #22c55e, #4ade80); }

.role-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 24px 48px rgba(0,0,0,0.5);
}
.role-card.admin:hover { border-color: rgba(99,102,241,.5); }
.role-card.user:hover  { border-color: rgba(34,197,94,.4); }
.role-card:hover::before { opacity: 1; }

.role-icon {
    width: 72px; height: 72px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem;
    transition: transform .25s;
}
.role-card:hover .role-icon { transform: scale(1.1); }
.admin .role-icon {
    background: rgba(99,102,241,.12);
    border: 1px solid rgba(99,102,241,.25);
}
.user .role-icon {
    background: rgba(34,197,94,.1);
    border: 1px solid rgba(34,197,94,.25);
}

.role-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #fff;
}
.role-desc {
    font-size: .85rem;
    color: #6b7280;
    line-height: 1.6;
}

.role-btn {
    margin-top: .25rem;
    padding: .6rem 1.6rem;
    border-radius: 8px;
    font-size: .875rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: background .2s, transform .2s;
}
.role-card:hover .role-btn { transform: scale(1.04); }
.admin .role-btn {
    background: #6366f1;
    color: #fff;
}
.admin .role-btn:hover { background: #4f46e5; }
.user .role-btn {
    background: #16a34a;
    color: #fff;
}
.user .role-btn:hover { background: #15803d; }

/* OR separator */
.or-sep {
    display: flex;
    align-items: center;
    color: #2a2d3e;
    font-size: .8rem;
    font-weight: 700;
    letter-spacing: 1px;
    align-self: center;
    flex-shrink: 0;
}

/* FOOTER */
.footer-note {
    margin-top: 3rem;
    font-size: .8rem;
    color: #374151;
    text-align: center;
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
    justify-content: center;
}
.footer-note a {
    color: #6366f1;
    text-decoration: none;
    font-weight: 600;
}
.footer-note a:hover { text-decoration: underline; }
.dot { color: #2a2d3e; }

/* version tag */
.version {
    position: fixed;
    bottom: 1rem; right: 1rem;
    font-size: .7rem;
    color: #2a2d3e;
    z-index: 1;
}
</style>
</head>
<body>
<div class="wrapper">

  <div class="logo">
    <h1>Emp<span>DB</span> Manager</h1>
    <p>Employee &amp; Department Management System</p>
  </div>

  <div class="divider-line"></div>

  <p class="question">Who are you?</p>

  <div class="cards">

    <a href="login.php?role=admin" class="role-card admin">
      <div class="role-icon">🛡️</div>
      <div class="role-title">Administrator</div>
      <div class="role-desc">
        Full access — manage employees, departments, projects and all system users.
      </div>
      <span class="role-btn">Sign in as Admin</span>
    </a>

    <div class="or-sep">OR</div>

    <a href="login.php?role=user" class="role-card user">
      <div class="role-icon">👤</div>
      <div class="role-title">Regular User</div>
      <div class="role-desc">
        View employee data, search records and manage your own account.
      </div>
      <span class="role-btn">Sign in as User</span>
    </a>

  </div>

  <div class="footer-note">
    <span>New here? <a href="signup.php">Create an account</a></span>
    <span class="dot">·</span>
    <span>Not sure? <a href="login.php">Just login</a></span>
  </div>

</div>

<div class="version">EmpDB Manager v1.0</div>

</body>
</html>
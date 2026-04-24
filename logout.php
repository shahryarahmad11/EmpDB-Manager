<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/Functions.php';

if (isset($_SESSION['user_id'])) {
    $logout_name = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Unknown User';

    write_activity_log_as($logout_name, 'LOGOUT', 'User logged out.');

    $c = get_connection();
    sqlsrv_query($c, "UPDATE Users SET RememberToken = NULL WHERE UserID = ?", array($_SESSION['user_id']));
    sqlsrv_close($c);
}

if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $p["path"],
        $p["domain"],
        $p["secure"],
        $p["httponly"]
    );
}

session_destroy();
header("Location: login.php");
exit();
?>
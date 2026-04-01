<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict']);
}

require_once __DIR__ . '/conn.php';

// Clear remember token from DB
if (isset($_SESSION['user_id'])) {
    $c = get_connection();
    sqlsrv_query($c, "UPDATE Users SET RememberToken = NULL WHERE UserID = ?",
        array($_SESSION['user_id']));
    sqlsrv_close($c);
}

// Delete remember cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

// Destroy session completely
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p["path"], $p["domain"], $p["secure"], $p["httponly"]
    );
}
session_destroy();
header("Location: login.php");
exit();
?>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime'      => 0,          // session cookie dies when browser closes
        'path'          => '/',
        'secure'        => isset($_SERVER['HTTPS']),
        'httponly'      => true,        // JS cannot steal the cookie
        'samesite'      => 'Strict',    // CSRF protection
        'use_strict_mode' => true,
    ]);
    session_start();
}

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/Functions.php';

// ── Remember Me: restore session from cookie if not already logged in ──
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $c     = get_connection();

    $res = sqlsrv_query(
        $c,
        "SELECT UserID, FullName, Email, Role, IsBlocked FROM Users WHERE RememberToken = ?",
        array($token)
    );

    if ($res) {
        $u = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);

        if ($u) {
            if (!empty($u['IsBlocked'])) {
                // Blocked user — clear token and do not restore session
                sqlsrv_query($c,
                    "UPDATE Users SET RememberToken = NULL WHERE UserID = ?",
                    array($u['UserID'])
                );
                setcookie('remember_token', '', time() - 3600, '/');
                write_activity_log_as($u['FullName'], 'SESSION_RESTORE_BLOCKED',
                    'Blocked user attempted session restore via remember-me token.'
                );
            } else {
                // Valid user — restore session
                $_SESSION['user_id']    = $u['UserID'];
                $_SESSION['user_name']  = $u['FullName'];
                $_SESSION['user_email'] = $u['Email'];
                $_SESSION['user_role']  = $u['Role'];

                // Rotate token on each use (security best practice)
                $new_token = bin2hex(random_bytes(32));
                sqlsrv_query($c,
                    "UPDATE Users SET RememberToken = ? WHERE UserID = ?",
                    array($new_token, $u['UserID'])
                );
                setcookie('remember_token', $new_token, [
                    'expires'  => time() + (30 * 24 * 3600),
                    'path'     => '/',
                    'secure'   => isset($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Strict',
                ]);

                write_activity_log(
                    'SESSION_RESTORED',
                    'Session restored from remember-me token (token rotated).'
                );
            }
        }
    }

    sqlsrv_close($c);
}

// ── Not logged in → send to login ──
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// ── Session fixation protection: regenerate ID periodically ──
if (!isset($_SESSION['last_regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['last_regenerated'] = time();
} elseif (time() - $_SESSION['last_regenerated'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regenerated'] = time();
}
?>
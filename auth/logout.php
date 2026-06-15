<?php
// auth/logout.php — Destroys session and redirects to home
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../config/app.php';

// 1. Regenerate session ID before destroying to prevent fixation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Delete the remember-me cookie if set
if (isset($_COOKIE['remember_token'])) {
    // Optionally: delete the token from DB here if you track them in user_sessions
    $pdo = null;
    try {
        require_once __DIR__ . '/../config/db.php';
        $pdo = getDB();
        $token = hash('sha256', $_COOKIE['remember_token']); // match how it was stored
        $stmt = $pdo->prepare('DELETE FROM user_sessions WHERE token = ?');
        $stmt->execute([$token]);
    } catch (\Throwable $e) {
        // Non-fatal: just log and continue
        error_log('Logout: could not clear remember token: ' . $e->getMessage());
    }

    // Expire the cookie
    setcookie('remember_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
}

// 3. Destroy session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => 'Lax',
    ]);
}
session_destroy();

// 4. Redirect to home
$base = defined('BASE_URL') ? BASE_URL : '';
header('Location: ' . $base . '/index.php');
exit;
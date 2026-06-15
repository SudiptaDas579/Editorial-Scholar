<?php
// includes/auth_helpers.php — shared auth utilities
// Include ONCE at top of every auth/dashboard page (after session_start)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── CSRF ───────────────────────────────────────────────
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf(string $token): bool
{
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// ── FLASH MESSAGES ────────────────────────────────────
function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function render_flash(): string
{
    $f = get_flash();
    if (!$f) return '';
    $cls = match($f['type']) {
        'error'   => 'alert-error',
        'success' => 'alert-success',
        default   => 'alert-info',
    };
    $icon = match($f['type']) {
        'error'   => 'ri-error-warning-line',
        'success' => 'ri-checkbox-circle-line',
        default   => 'ri-information-line',
    };
    $msg = htmlspecialchars($f['message']);
    return <<<HTML
    <div class="{$cls} flex items-start gap-2 mb-4">
      <i class="{$icon} text-lg mt-0.5 flex-shrink-0"></i>
      <span>{$msg}</span>
    </div>
    HTML;
}

// ── REDIRECT HELPER ───────────────────────────────────
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ── AUTH CHECKS ───────────────────────────────────────
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function require_login(string $redirect = '/auth/signin.php'): void
{
    if (!is_logged_in()) {
        flash('info', 'Please sign in to continue.');
        redirect($redirect);
    }
}

function require_role(string|array $roles, string $redirect = '/auth/signin.php'): void
{
    require_login($redirect);
    $allowed = (array) $roles;
    if (!in_array($_SESSION['role'] ?? '', $allowed, true)) {
        flash('error', 'You do not have permission to access that page.');
        redirect('/auth/signin.php');
    }
}

// ── SET SESSION AFTER LOGIN ───────────────────────────
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];
}

function logout_user(): void
{
    // Clear remember-me cookie if present
    if (isset($_COOKIE['remember_token'])) {
        // Attempt to remove the token from DB (non-fatal if it fails)
        try {
            require_once __DIR__ . '/../config/db.php';
            $pdo   = getDB();
            $token = hash('sha256', $_COOKIE['remember_token']);
            $pdo->prepare('DELETE FROM user_sessions WHERE token = ?')->execute([$token]);
        } catch (\Throwable $e) {
            error_log('logout_user: could not clear remember token: ' . $e->getMessage());
        }

        setcookie('remember_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
    }

    // Destroy the session
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

// ── SANITIZE INPUTS ───────────────────────────────────
function sanitize(string $val): string
{
    return htmlspecialchars(strip_tags(trim($val)));
}

function validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_password(string $pw): bool
{
    // min 8 chars, at least one upper, one lower, one digit
    return strlen($pw) >= 8
        && preg_match('/[A-Z]/', $pw)
        && preg_match('/[a-z]/', $pw)
        && preg_match('/[0-9]/', $pw);
}
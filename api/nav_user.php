<?php
/**
 * api/nav_user.php
 *
 * Returns the current user's nav state as JSON.
 * Used by any page that wants to dynamically update the nav
 * without a full PHP render (e.g. after an AJAX action).
 *
 * Response shape:
 * {
 *   "loggedIn": true,
 *   "name": "John Doe",
 *   "initials": "JD",
 *   "role": "user",
 *   "dashboardUrl": "/dashboard/user.php"
 * }
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

if (!is_logged_in()) {
    echo json_encode([
        'loggedIn'     => false,
        'name'         => '',
        'initials'     => '',
        'role'         => '',
        'dashboardUrl' => BASE_URL . '/auth/signIn.php',
    ]);
    exit;
}

$name = $_SESSION['full_name'] ?? '';
$role = $_SESSION['role'] ?? 'user';

// Build initials
$parts = array_filter(explode(' ', trim($name)));
if (count($parts) >= 2) {
    $initials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
} elseif (count($parts) === 1) {
    $initials = strtoupper(substr($parts[0], 0, 2));
} else {
    $initials = '?';
}

echo json_encode([
    'loggedIn'     => true,
    'name'         => $name,
    'initials'     => $initials,
    'role'         => $role,
    'dashboardUrl' => BASE_URL . '/dashboard/' . $role . '.php',
    'logoutUrl'    => BASE_URL . '/auth/logout.php',
]);
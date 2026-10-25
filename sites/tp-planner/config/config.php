<?php
/**
 * TP Planner - Application Configuration
 */
define('APP_NAME', 'TP Planner');
define('ROOT_PATH', dirname(__DIR__));

// Base path for links (e.g. '' or '/TP PLANNER' if in subfolder)
$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
if (preg_match('#/pages?/#', $script)) {
    $base = preg_replace('#/pages?/.*#', '', $script);
} else {
    $base = dirname($script);
}
define('BASE_PATH', ($base === '/' || $base === '\\') ? '' : $base);
define('APP_URL', BASE_PATH);

/**
 * Cookie path for session + ui_lang. PHP 8+ rejects paths with spaces (e.g. "/TP PLANNER/").
 * Using "/" is valid for all routes on this host and avoids fatal errors on Windows/XAMPP.
 */
define('HTTP_COOKIE_PATH', '/');

$__https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => HTTP_COOKIE_PATH,
        'secure' => $__https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
} else {
    session_set_cookie_params(0, HTTP_COOKIE_PATH, '', $__https, true);
}
session_start();

// Timezone
date_default_timezone_set('Europe/Paris');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database: users = admins | students = trainee teachers (class_id required)
define('USER_LOGIN_COLUMN', 'email');

/**
 * Development only: allow storing and verifying passwords in plain text.
 * When true, login accepts either plain match or password_verify (bcrypt).
 * Set to false before production.
 */
define('DEV_PLAIN_PASSWORD', true);

// Require database
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/i18n.php';
init_ui_lang();

if (isLoggedIn() && isset($_SESSION['role'])) {
    $_SESSION['role'] = normalize_user_role((string) $_SESSION['role']);
}

if (isLoggedIn() && empty($_SESSION['user_email']) && !empty($_SESSION['user_id'])) {
    try {
        $dbc = getDB();
        $uid = (int) $_SESSION['user_id'];
        $src = $_SESSION['account_source'] ?? 'users';
        if ($src === 'students' && students_table_ready($dbc)) {
            $st = $dbc->prepare('SELECT email FROM students WHERE id = ? LIMIT 1');
            $st->bind_param('i', $uid);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
        } elseif (db_table_has_column($dbc, 'users', 'email')) {
            $rq = $dbc->query('SELECT email FROM users WHERE id = ' . $uid . ' LIMIT 1');
            $row = $rq ? $rq->fetch_assoc() : null;
        } else {
            $row = null;
        }
        if (!empty($row['email'])) {
            $_SESSION['user_email'] = trim((string) $row['email']);
        }
    } catch (Throwable $e) {
        /* ignore */
    }
}

if (isLoggedIn() && empty($_SESSION['account_source'])) {
    $_SESSION['account_source'] = is_staff() ? 'users' : 'students';
}
?>

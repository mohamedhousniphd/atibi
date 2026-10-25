<?php
require_once dirname(__DIR__) . '/config/config.php';
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    $path = (defined('HTTP_COOKIE_PATH') && HTTP_COOKIE_PATH !== '') ? HTTP_COOKIE_PATH : ($p['path'] ?? '/');
    setcookie(session_name(), '', time() - 42000, $path, $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
}
session_destroy();
header('Location: ' . APP_URL . '/');
exit;

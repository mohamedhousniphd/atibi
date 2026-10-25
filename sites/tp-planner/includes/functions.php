<?php
/**
 * TP Planner - Helper Functions
 */

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/pages/login.php');
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function staff_roles(): array {
    return ['admin'];
}

function lab_member_roles(): array {
    return ['trainee_teacher'];
}

/** Session account source: users (admin) or students (trainee). */
function account_source(): string {
    return $_SESSION['account_source'] ?? 'users';
}

function is_trainee_account(): bool {
    return account_source() === 'students';
}

/**
 * Map legacy / alias role values to admin | trainee_teacher.
 */
function normalize_user_role(string $role): string {
    if (in_array($role, ['professor', 'teacher', 'admin'], true)) {
        return 'admin';
    }
    if (in_array($role, ['trainee_teacher', 'teacher_trainee', 'doctoral', 'researcher', 'member'], true)) {
        return 'trainee_teacher';
    }
    return 'trainee_teacher';
}

function is_staff(): bool {
    $r = $_SESSION['role'] ?? '';
    return in_array($r, staff_roles(), true);
}

function is_lab_member(): bool {
    $r = $_SESSION['role'] ?? '';
    return in_array($r, lab_member_roles(), true);
}

/** @deprecated Use is_staff() */
function isTeacher() {
    return is_staff();
}

function require_staff(): void {
    requireLogin();
    if (!is_staff()) {
        header('Location: ' . APP_URL . '/pages/member_dashboard.php');
        exit;
    }
}

function require_lab_member(): void {
    requireLogin();
    if (!is_lab_member()) {
        header('Location: ' . APP_URL . '/pages/dashboard.php');
        exit;
    }
}

function redirect_after_login(): void {
    if (is_staff()) {
        redirect(APP_URL . '/pages/dashboard.php');
    }
    redirect(APP_URL . '/pages/member_dashboard.php');
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        $dest = is_lab_member() ? (APP_URL . '/pages/member_dashboard.php') : (APP_URL . '/pages/dashboard.php');
        header('Location: ' . $dest);
        exit;
    }
}

function requireTeacher() {
    require_staff();
}

function db_table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '$t'");
    return $r && $r->num_rows > 0;
}

/** @return list<string> */
function users_table_columns(mysqli $conn): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    try {
        $res = $conn->query('SHOW COLUMNS FROM users');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cache[] = $row['Field'];
            }
        }
    } catch (Throwable $e) {
        $cache = [];
    }
    return $cache;
}

function db_table_has_column(mysqli $conn, string $table, string $column): bool {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return false;
    }
    $c = $conn->real_escape_string($column);
    $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$c'");
    return $r && $r->num_rows > 0;
}

/** Trainee accounts live in `students` with name, email, password, class_id. */
function students_table_ready(mysqli $conn): bool {
    if (!db_table_exists($conn, 'students')) {
        return false;
    }
    foreach (['name', 'email', 'password', 'class_id'] as $col) {
        if (!db_table_has_column($conn, 'students', $col)) {
            return false;
        }
    }
    return true;
}

/** Label stored in quiz_answers.student_name for the logged-in trainee. */
function trainee_quiz_label(): string {
    $name = trim((string) ($_SESSION['username'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    return trim((string) ($_SESSION['user_email'] ?? ''));
}

/**
 * @return array<string, mixed>|null
 */
function member_fetch_student_row(mysqli $conn, int $studentId, ?string $userEmail): ?array {
    unset($userEmail);
    if ($studentId <= 0 || !students_table_ready($conn)) {
        return null;
    }
    $st = $conn->prepare('SELECT s.id AS sid, s.class_id, c.name AS class_name FROM students s LEFT JOIN classes c ON c.id = s.class_id WHERE s.id = ? LIMIT 1');
    if (!$st) {
        return null;
    }
    $st->bind_param('i', $studentId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    return $row ?: null;
}

/**
 * Authenticate by email: admins in users, trainees in students.
 *
 * @return array{source: string, id: int, name: string, email: string, role: string}|null
 */
function authenticate_by_email(mysqli $conn, string $email, string $password): ?array {
    $email = trim($email);
    if ($email === '' || $password === '') {
        return null;
    }

    if (db_table_has_column($conn, 'users', 'email')) {
        $st = $conn->prepare('SELECT id, name, email, password, role FROM users WHERE LOWER(TRIM(email)) = LOWER(?) LIMIT 1');
        if ($st) {
            $st->bind_param('s', $email);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            if ($row) {
                if (!verify_user_password((string) $row['password'], $password)) {
                    return null;
                }
                return [
                    'source' => 'users',
                    'id' => (int) $row['id'],
                    'name' => (string) ($row['name'] ?? ''),
                    'email' => (string) $row['email'],
                    'role' => 'admin',
                ];
            }
        }
    }

    if (students_table_ready($conn)) {
        $st = $conn->prepare('SELECT id, name, email, password FROM students WHERE LOWER(TRIM(email)) = LOWER(?) LIMIT 1');
        if ($st) {
            $st->bind_param('s', $email);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            if ($row && verify_user_password((string) $row['password'], $password)) {
                return [
                    'source' => 'students',
                    'id' => (int) $row['id'],
                    'name' => (string) ($row['name'] ?? ''),
                    'email' => (string) $row['email'],
                    'role' => 'trainee_teacher',
                ];
            }
        }
    }

    return null;
}

/** Email unique across users and students (optional exclude). */
function email_exists_any(mysqli $conn, string $email, ?string $excludeSource = null, int $excludeId = 0): bool {
    $email = trim($email);
    if ($email === '') {
        return false;
    }
    if (db_table_has_column($conn, 'users', 'email')) {
        if ($excludeSource === 'users' && $excludeId > 0) {
            $st = $conn->prepare('SELECT id FROM users WHERE LOWER(TRIM(email)) = LOWER(?) AND id <> ? LIMIT 1');
            $st->bind_param('si', $email, $excludeId);
        } else {
            $st = $conn->prepare('SELECT id FROM users WHERE LOWER(TRIM(email)) = LOWER(?) LIMIT 1');
            $st->bind_param('s', $email);
        }
        $st->execute();
        if ($st->get_result()->fetch_assoc()) {
            return true;
        }
    }
    if (students_table_ready($conn)) {
        if ($excludeSource === 'students' && $excludeId > 0) {
            $st = $conn->prepare('SELECT id FROM students WHERE LOWER(TRIM(email)) = LOWER(?) AND id <> ? LIMIT 1');
            $st->bind_param('si', $email, $excludeId);
        } else {
            $st = $conn->prepare('SELECT id FROM students WHERE LOWER(TRIM(email)) = LOWER(?) LIMIT 1');
            $st->bind_param('s', $email);
        }
        $st->execute();
        if ($st->get_result()->fetch_assoc()) {
            return true;
        }
    }
    return false;
}

function establish_session_from_account(array $account): void {
    $_SESSION['user_id'] = (int) $account['id'];
    $_SESSION['account_source'] = $account['source'];
    $_SESSION['role'] = $account['role'];
    $_SESSION['username'] = $account['name'] !== '' ? $account['name'] : $account['email'];
    $_SESSION['user_email'] = $account['email'];
}

/**
 * @return array<string, string|null>
 */
function site_settings_get(mysqli $conn, array $keys): array {
    if (!db_table_exists($conn, 'site_settings') || $keys === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $types = str_repeat('s', count($keys));
    $st = $conn->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ($placeholders)");
    if (!$st) {
        return [];
    }
    $st->bind_param($types, ...$keys);
    $st->execute();
    $res = $st->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[$row['setting_key']] = $row['setting_value'];
    }
    return $out;
}

function user_has_tp_access(mysqli $conn, int $tpId): bool {
    if (is_staff()) {
        return true;
    }
    if (!is_lab_member() || !is_trainee_account() || !students_table_ready($conn)) {
        return false;
    }
    $tpId = (int) $tpId;
    $row = $conn->query("SELECT class_id FROM tp_sessions WHERE id = $tpId")->fetch_assoc();
    if (!$row || empty($row['class_id'])) {
        return false;
    }
    $cid = (int) $row['class_id'];
    $sid = (int) ($_SESSION['user_id'] ?? 0);
    $st = $conn->prepare('SELECT 1 FROM students WHERE id = ? AND class_id = ? LIMIT 1');
    if (!$st) {
        return false;
    }
    $st->bind_param('ii', $sid, $cid);
    $st->execute();
    return (bool) $st->get_result()->fetch_row();
}

/** Text for a quiz option letter (A–D). */
function quiz_option_label(array $quiz, string $letter): string {
    $letter = strtolower($letter);
    $key = 'option_' . $letter;
    return trim((string) ($quiz[$key] ?? ''));
}

function escape($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function app_request_relative_path(): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = '/';
    }
    $path = str_replace('\\', '/', rawurldecode($path));
    $base = defined('BASE_PATH') ? (string) BASE_PATH : '';
    if ($base !== '' && $base !== '/' && strpos($path, $base) === 0) {
        $rel = substr($path, strlen($base));
        if ($rel === '' || $rel[0] !== '/') {
            $rel = '/' . ltrim($rel, '/');
        }
        return $rel;
    }
    return ($path !== '' && $path[0] === '/') ? $path : '/' . $path;
}

function sanitize_lang_redirect(string $return): string {
    $return = trim($return);
    if ($return === '') {
        return APP_URL . '/';
    }
    if (preg_match('#^(https?:)?//#i', $return)) {
        return APP_URL . '/';
    }
    $path = $return;
    $parsed = parse_url($path, PHP_URL_PATH);
    if (is_string($parsed) && $parsed !== '') {
        $path = str_replace('\\', '/', rawurldecode($parsed));
    } else {
        $path = str_replace('\\', '/', rawurldecode($path));
    }
    if ($path === '' || ($path[0] !== '/' && $path[0] !== '\\')) {
        $path = '/' . ltrim($path, '/');
    }
    $base = defined('BASE_PATH') ? (string) BASE_PATH : '';
    if ($base !== '' && $base !== '/' && strpos($path, $base) === 0) {
        $path = substr($path, strlen($base)) ?: '/';
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
    }
    if (strpos($path, '..') !== false) {
        return APP_URL . '/';
    }
    return APP_URL . ($path === '/' ? '/' : $path);
}

function tp_allowed_html_tags(): string {
    return '<p><br><br/><strong><b><em><i><u><ul><ol><li><h1><h2><h3><h4><blockquote><sup><sub><div><span><a>';
}

function tp_sanitize_rich_text(string $html): string {
    return strip_tags($html ?? '', tp_allowed_html_tags());
}

function redirect($url, $code = 302) {
    header('Location: ' . $url, true, $code);
    exit;
}

function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function old($key, $default = '') {
    return $_SESSION['old'][$key] ?? $default;
}

function csrf_field() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . escape($_SESSION['csrf_token']) . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

define('ALLOWED_LOGIN_COLUMNS', ['username', 'email', 'login', 'name', 'user_name', 'identifiant', 'user_login']);

function hash_user_password(string $plain): string {
    if (defined('DEV_PLAIN_PASSWORD') && DEV_PLAIN_PASSWORD) {
        return $plain;
    }
    return password_hash($plain, PASSWORD_DEFAULT);
}

function password_stored_is_hashed(string $stored): bool {
    if ($stored === '' || $stored[0] !== '$') {
        return false;
    }
    $info = password_get_info($stored);
    return ($info['algo'] ?? 0) !== 0;
}

function verify_user_password(string $stored, string $input): bool {
    $stored = (string) $stored;
    $input = (string) $input;
    if (password_stored_is_hashed($stored)) {
        return password_verify($input, $stored);
    }
    return hash_equals($stored, $input);
}

function getUsersLoginColumn() {
    try {
        $conn = getDB();
        $columns = users_table_columns($conn);
        if (defined('USER_LOGIN_COLUMN') && in_array(USER_LOGIN_COLUMN, ALLOWED_LOGIN_COLUMNS, true)) {
            if (in_array(USER_LOGIN_COLUMN, $columns, true)) {
                return USER_LOGIN_COLUMN;
            }
        }
        $priority = ['email', 'login', 'username', 'user_name', 'identifiant', 'user_login', 'name'];
        foreach ($priority as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }
    } catch (Exception $e) { /* ignore */ }
    return 'email';
}
?>

<?php
/**
 * TP Planner - Database Connection
 * Uses mysqli with prepared statements for security
 */

define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'tp_planner');
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_PORT', getenv('MYSQLPORT') ?: 3306);
define('DB_CHARSET', 'utf8mb4');

$db = null;

function getDB() {
    global $db;
    if ($db === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
            $db->set_charset(DB_CHARSET);
        } catch (mysqli_sql_exception $e) {
            error_log('DB Connection failed: ' . $e->getMessage());
            if (php_sapi_name() === 'cli') {
                throw $e;
            }
            die('Database connection failed. Please check config.');
        }
    }
    return $db;
}

function dbQuery($sql, $types = '', $params = []) {
    $conn = getDB();
    if (empty($params)) {
        return $conn->query($sql);
    }
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

function dbExecute($sql, $types = '', $params = []) {
    $conn = getDB();
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $conn->insert_id ?: $stmt->affected_rows;
}
?>

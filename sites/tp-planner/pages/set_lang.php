<?php
/**
 * Set UI language (session + cookie). POST preferred; optional JSON response.
 */
require_once dirname(__DIR__) . '/config/config.php';

$lang = $_POST['lang'] ?? $_GET['lang'] ?? '';
$return = $_POST['return'] ?? $_GET['return'] ?? '';

if (!in_array($lang, I18N_ALLOWED, true)) {
    $lang = 'fr';
}

$_SESSION['ui_lang'] = $lang;
i18n_set_lang_cookie($lang);

$return = sanitize_lang_redirect(is_string($return) ? $return : '');

$wantsJson = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_POST['format']) && $_POST['format'] === 'json');

if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'lang' => $lang]);
    exit;
}

header('Location: ' . $return, true, 302);
exit;

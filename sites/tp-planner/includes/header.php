<?php
if (!defined('ROOT_PATH')) require_once dirname(__DIR__) . '/config/config.php';
$pageTitle = $pageTitle ?? APP_NAME;
$uiLang = get_ui_lang();
$uiDir = $uiLang === 'ar' ? 'rtl' : 'ltr';
$bootstrapCss = $uiLang === 'ar'
    ? 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css'
    : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css';
?>
<!DOCTYPE html>
<html lang="<?= escape($uiLang) ?>" dir="<?= escape($uiDir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle) ?></title>
    <link href="<?= escape($bootstrapCss) ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <?php if (isset($extraStyles)) echo $extraStyles; ?>
</head>
<body class="d-flex flex-column min-vh-100">

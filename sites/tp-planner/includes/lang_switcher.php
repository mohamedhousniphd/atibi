<?php
/** @var string $switcherVariant landing|app */
$variant = $switcherVariant ?? 'app';
$cur = get_ui_lang();
$returnPath = function_exists('app_request_relative_path') ? app_request_relative_path() : (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
?>
<div class="dropdown lang-switcher lang-switcher--<?= escape($variant) ?>">
    <button class="btn btn-sm <?= $variant === 'landing' ? 'btn-outline-light' : 'btn-outline-secondary' ?> dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Language">
        <i class="bi bi-translate"></i>
        <span class="d-none d-sm-inline"><?= escape(strtoupper($cur)) ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <?php foreach (I18N_ALLOWED as $code): ?>
            <li>
                <form method="post" action="<?= APP_URL ?>/pages/set_lang.php" class="m-0">
                    <input type="hidden" name="lang" value="<?= escape($code) ?>">
                    <input type="hidden" name="return" value="<?= escape($returnPath) ?>">
                    <button type="submit" class="dropdown-item<?= $cur === $code ? ' active' : '' ?>">
                        <?= escape(t('lang.' . $code)) ?>
                    </button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

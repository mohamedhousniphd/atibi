<?php
require_once __DIR__ . '/config/config.php';
if (isLoggedIn()) {
    redirect_after_login();
} else {
    require_once __DIR__ . '/pages/landing.php';
}
exit;
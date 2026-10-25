<?php if (isLoggedIn()): ?>
<?php if (is_staff()): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= APP_URL ?>/pages/dashboard.php">
            <i class="bi bi-flask2 me-2"></i><?= escape(APP_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/pages/dashboard.php"><i class="bi bi-grid-1x2 me-1"></i> <?= escape(t('nav.dashboard')) ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/pages/classes.php"><i class="bi bi-people me-1"></i> <?= escape(t('nav.classes')) ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/pages/tp_sessions.php"><i class="bi bi-journal-text me-1"></i> <?= escape(t('nav.tp_sessions')) ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/pages/stagiaires.php"><i class="bi bi-mortarboard me-1"></i> <?= escape(t('nav.stagiaires')) ?></a>
                </li>
            </ul>
            <ul class="navbar-nav align-items-lg-center gap-lg-2">
                <li class="nav-item">
                    <?php $switcherVariant = 'app'; require __DIR__ . '/lang_switcher.php'; ?>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i> <?= escape($_SESSION['username'] ?? 'User') ?>
                        <span class="badge bg-light text-dark ms-1"><?= format_role_label($_SESSION['role'] ?? null) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/logout.php"><i class="bi bi-box-arrow-right me-2"></i> <?= escape(t('nav.logout')) ?></a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php else: ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= APP_URL ?>/pages/member_dashboard.php">
            <i class="bi bi-droplet-half me-2"></i><?= escape(APP_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMember">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMember">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/pages/member_dashboard.php"><i class="bi bi-house-door me-1"></i> <?= escape(t('nav.member_lab')) ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/pages/quiz_results.php"><i class="bi bi-clipboard-data me-1"></i> <?= escape(t('quiz_results.title')) ?></a>
                </li>
            </ul>
            <ul class="navbar-nav align-items-lg-center gap-lg-2">
                <li class="nav-item">
                    <?php $switcherVariant = 'app'; require __DIR__ . '/lang_switcher.php'; ?>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i> <?= escape($_SESSION['username'] ?? 'User') ?>
                        <span class="badge bg-light text-dark ms-1"><?= format_role_label($_SESSION['role'] ?? null) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/logout.php"><i class="bi bi-box-arrow-right me-2"></i> <?= escape(t('nav.logout')) ?></a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>
<?php endif; ?>

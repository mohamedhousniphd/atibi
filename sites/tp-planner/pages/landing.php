<?php
require_once dirname(__DIR__) . '/config/config.php';

$landingContact = [
    'email' => t('landing.contact_line1'),
    'address' => t('landing.contact_line2'),
    'phone' => t('landing.contact_line3'),
];
try {
    $dbc = getDB();
    $stg = site_settings_get($dbc, ['contact_email', 'contact_address', 'contact_phone']);
    $fromSetting = static function (?string $v, string $fallback): string {
        if ($v === null) {
            return $fallback;
        }
        $trim = trim($v);
        return $trim !== '' ? $trim : $fallback;
    };
    $landingContact['email'] = $fromSetting($stg['contact_email'] ?? null, $landingContact['email']);
    $landingContact['address'] = $fromSetting($stg['contact_address'] ?? null, $landingContact['address']);
    $landingContact['phone'] = $fromSetting($stg['contact_phone'] ?? null, $landingContact['phone']);
} catch (Throwable $e) {
    /* keep translation fallbacks */
}

$heroImgMicroscope = APP_URL . '/assets/img/lab-microscope.jpg';
$heroImgFlasks = APP_URL . '/assets/img/lab-flasks.jpg';
$heroImgRoom = APP_URL . '/assets/img/lab-room.jpg';

$pageTitle = APP_NAME . ' — ' . t('landing.hero_pill');
$extraStyles = '<link href="' . APP_URL . '/assets/css/landing.css" rel="stylesheet">';
$extraScripts = '<script>window.APP_BASE=' . json_encode(APP_URL) . ';window.LANDING_I18N=' . json_encode(i18n_export_all(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) . ';window.LANDING_ALERT_SENT=' . json_encode(t('landing.alert_sent'), JSON_UNESCAPED_UNICODE) . ';window.LANDING_INITIAL_LANG=' . json_encode(get_ui_lang()) . ';</script>';
$extraScripts .= '<script src="' . APP_URL . '/assets/js/landing.js"></script>';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="landing-page">
    <div class="chem-bg" aria-hidden="true">
        <div class="grid-overlay"></div>
        <div class="mol-ring"></div>
        <div class="mol-ring"></div>
        <div class="mol-ring"></div>
        <div class="mol-ring"></div>
        <div class="mol-ring"></div>
    </div>
    <div id="particles" class="particles-layer" aria-hidden="true"></div>

    <header class="landing-navbar">
        <div class="container d-flex align-items-center justify-content-between py-3 flex-wrap gap-2">
            <a href="<?= APP_URL ?>/" class="brand-link">
                <div class="brand-flask-icon">
                    <svg viewBox="0 0 68 68" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M24 8h20v24l12 24H12L24 32V8Z" fill="url(#navFlaskGrad)" opacity=".9"/>
                        <path d="M24 8h20v24l12 24H12L24 32V8Z" stroke="url(#navFlaskStroke)" stroke-width="1.5"/>
                        <circle cx="28" cy="46" r="3" fill="rgba(182,255,78,.7)"/>
                        <circle cx="40" cy="50" r="2" fill="rgba(0,229,195,.6)"/>
                        <circle cx="33" cy="43" r="1.5" fill="rgba(5,193,122,.8)"/>
                        <defs>
                            <linearGradient id="navFlaskGrad" x1="12" y1="8" x2="56" y2="56" gradientUnits="userSpaceOnUse">
                                <stop offset="0%" stop-color="#00e5c3" stop-opacity=".25"/>
                                <stop offset="100%" stop-color="#05c17a" stop-opacity=".15"/>
                            </linearGradient>
                            <linearGradient id="navFlaskStroke" x1="12" y1="8" x2="56" y2="56" gradientUnits="userSpaceOnUse">
                                <stop offset="0%" stop-color="#00e5c3"/>
                                <stop offset="100%" stop-color="#b6ff4e"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
                <span><?= escape(APP_NAME) ?></span>
            </a>
            <div class="d-flex align-items-center gap-2 ms-auto order-md-2">
                <div class="btn-group btn-group-sm landing-lang" role="group" aria-label="Language">
                    <?php foreach (I18N_ALLOWED as $code): ?>
                        <button type="button" class="btn btn-outline-light lang-switch px-2<?= get_ui_lang() === $code ? ' active' : '' ?>" data-lang="<?= escape($code) ?>"><?= strtoupper(escape($code)) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <nav class="d-none d-md-flex align-items-center gap-3 order-md-1 flex-grow-1 justify-content-center">
                <a class="nav-link-landing" href="#home" data-i18n="landing.nav_home"><?= escape(t('landing.nav_home')) ?></a>
                <a class="nav-link-landing" href="#about" data-i18n="landing.nav_about"><?= escape(t('landing.nav_about')) ?></a>
                <a class="nav-link-landing" href="#contact" data-i18n="landing.nav_contact"><?= escape(t('landing.nav_contact')) ?></a>
                <a class="btn btn-outline-light btn-sm px-3" href="<?= APP_URL ?>/login.php" data-i18n="landing.nav_login"><?= escape(t('landing.nav_login')) ?></a>
                <a class="btn btn-primary btn-sm px-3" href="<?= APP_URL ?>/register.php" data-i18n="landing.nav_signup"><?= escape(t('landing.nav_signup')) ?></a>
            </nav>
            <button class="btn btn-sm btn-outline-light d-md-none order-3" id="mobileMenuBtn" type="button" aria-label="Menu">
                <i class="bi bi-list"></i>
            </button>
        </div>
        <div id="mobileMenu" class="mobile-menu container d-md-none">
            <a class="mobile-link" href="#home" data-i18n="landing.nav_home"><?= escape(t('landing.nav_home')) ?></a>
            <a class="mobile-link" href="#about" data-i18n="landing.nav_about"><?= escape(t('landing.nav_about')) ?></a>
            <a class="mobile-link" href="#contact" data-i18n="landing.nav_contact"><?= escape(t('landing.nav_contact')) ?></a>
            <a class="mobile-link" href="<?= APP_URL ?>/login.php" data-i18n="landing.nav_login"><?= escape(t('landing.nav_login')) ?></a>
            <a class="mobile-link mobile-link-cta" href="<?= APP_URL ?>/register.php" data-i18n="landing.nav_signup"><?= escape(t('landing.nav_signup')) ?></a>
        </div>
    </header>

    <main>
        <section id="home" class="hero-section">
            <div class="container py-5">
                <div class="row align-items-center gy-5">
                    <div class="col-lg-7">
                        <div class="hero-content-glass">
                            <span class="hero-pill" data-i18n="landing.hero_pill"><?= escape(t('landing.hero_pill')) ?></span>
                            <h1 class="hero-title mt-3" data-i18n="landing.hero_title"><?= escape(t('landing.hero_title')) ?></h1>
                            <p class="hero-subtitle mt-3" data-i18n="landing.hero_subtitle"><?= escape(t('landing.hero_subtitle')) ?></p>
                            <div class="d-flex flex-wrap gap-3 mt-4">
                                <a class="btn btn-primary btn-lg hero-btn" href="<?= APP_URL ?>/login.php">
                                    <i class="bi bi-box-arrow-in-right me-2"></i><span data-i18n="landing.cta_login"><?= escape(t('landing.cta_login')) ?></span>
                                </a>
                                <a class="btn btn-outline-light btn-lg hero-btn" href="<?= APP_URL ?>/register.php">
                                    <i class="bi bi-person-plus me-2"></i><span data-i18n="landing.cta_signup"><?= escape(t('landing.cta_signup')) ?></span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="hero-visual-stack">
                            <div class="hero-orbit hero-orbit--one"></div>
                            <div class="hero-orbit hero-orbit--two"></div>
                            <div class="lab-deco lab-deco--a" aria-hidden="true" style="background-image:url('<?= escape($heroImgMicroscope) ?>')"></div>
                            <div class="lab-deco lab-deco--b" aria-hidden="true" style="background-image:url('<?= escape($heroImgFlasks) ?>')"></div>
                            <img class="hero-photo hero-photo--main" src="<?= escape($heroImgMicroscope) ?>" width="320" height="400" alt="" loading="lazy">
                            <img class="hero-photo hero-photo--side" src="<?= escape($heroImgFlasks) ?>" width="200" height="200" alt="" loading="lazy">
                        </div>
                        <div class="glass-panel mt-4 mt-lg-5">
                            <h5 class="mb-3" data-i18n="landing.glass_title"><?= escape(t('landing.glass_title')) ?></h5>
                            <div class="glass-item">
                                <i class="bi bi-journal-richtext"></i>
                                <div>
                                    <strong data-i18n="landing.glass_1t"><?= escape(t('landing.glass_1t')) ?></strong>
                                    <p data-i18n="landing.glass_1d"><?= escape(t('landing.glass_1d')) ?></p>
                                </div>
                            </div>
                            <div class="glass-item">
                                <i class="bi bi-clipboard2-check"></i>
                                <div>
                                    <strong data-i18n="landing.glass_2t"><?= escape(t('landing.glass_2t')) ?></strong>
                                    <p data-i18n="landing.glass_2d"><?= escape(t('landing.glass_2d')) ?></p>
                                </div>
                            </div>
                            <div class="glass-item">
                                <i class="bi bi-graph-up-arrow"></i>
                                <div>
                                    <strong data-i18n="landing.glass_3t"><?= escape(t('landing.glass_3t')) ?></strong>
                                    <p data-i18n="landing.glass_3d"><?= escape(t('landing.glass_3d')) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="about" class="about-section py-5 section-reveal">
            <div class="container">
                <div class="section-header text-center">
                    <h2 data-i18n="landing.about_title"><?= escape(t('landing.about_title')) ?></h2>
                    <p data-i18n="landing.about_sub"><?= escape(t('landing.about_sub')) ?></p>
                </div>
                <div class="row gy-4 mt-2">
                    <div class="col-md-4">
                        <div class="feature-blurb">
                            <i class="bi bi-droplet"></i>
                            <h5 data-i18n="landing.feat_1t"><?= escape(t('landing.feat_1t')) ?></h5>
                            <p data-i18n="landing.feat_1d"><?= escape(t('landing.feat_1d')) ?></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="feature-blurb">
                            <i class="bi bi-microscope"></i>
                            <h5 data-i18n="landing.feat_2t"><?= escape(t('landing.feat_2t')) ?></h5>
                            <p data-i18n="landing.feat_2d"><?= escape(t('landing.feat_2d')) ?></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="feature-blurb">
                            <i class="bi bi-shield-lock"></i>
                            <h5 data-i18n="landing.feat_3t"><?= escape(t('landing.feat_3t')) ?></h5>
                            <p data-i18n="landing.feat_3d"><?= escape(t('landing.feat_3d')) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="features-section py-5 section-reveal">
            <div class="container">
                <div class="section-header text-center">
                    <h2 data-i18n="landing.core_title"><?= escape(t('landing.core_title')) ?></h2>
                    <p data-i18n="landing.core_sub"><?= escape(t('landing.core_sub')) ?></p>
                </div>
                <div class="row g-4 mt-2">
                    <div class="col-sm-6 col-lg-3">
                        <article class="feature-card">
                            <div class="icon-wrap"><i class="bi bi-journal-text"></i></div>
                            <h5 data-i18n="landing.core_1t"><?= escape(t('landing.core_1t')) ?></h5>
                            <p data-i18n="landing.core_1d"><?= escape(t('landing.core_1d')) ?></p>
                        </article>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <article class="feature-card">
                            <div class="icon-wrap"><i class="bi bi-box-seam"></i></div>
                            <h5 data-i18n="landing.core_2t"><?= escape(t('landing.core_2t')) ?></h5>
                            <p data-i18n="landing.core_2d"><?= escape(t('landing.core_2d')) ?></p>
                        </article>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <article class="feature-card">
                            <div class="icon-wrap"><i class="bi bi-list-check"></i></div>
                            <h5 data-i18n="landing.core_3t"><?= escape(t('landing.core_3t')) ?></h5>
                            <p data-i18n="landing.core_3d"><?= escape(t('landing.core_3d')) ?></p>
                        </article>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <article class="feature-card">
                            <div class="icon-wrap"><i class="bi bi-bar-chart-line"></i></div>
                            <h5 data-i18n="landing.core_4t"><?= escape(t('landing.core_4t')) ?></h5>
                            <p data-i18n="landing.core_4d"><?= escape(t('landing.core_4d')) ?></p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section id="contact" class="contact-section py-5 section-reveal">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="contact-card">
                            <h2 data-i18n="landing.contact_title"><?= escape(t('landing.contact_title')) ?></h2>
                            <p data-i18n="landing.contact_intro"><?= escape(t('landing.contact_intro')) ?></p>
                            <form id="contactForm" class="mt-3">
                                <div class="mb-3">
                                    <label class="form-label" data-i18n="landing.contact_name"><?= escape(t('landing.contact_name')) ?></label>
                                    <input type="text" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" data-i18n="landing.contact_email"><?= escape(t('landing.contact_email')) ?></label>
                                    <input type="email" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" data-i18n="landing.contact_msg"><?= escape(t('landing.contact_msg')) ?></label>
                                    <textarea class="form-control" rows="4" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary px-4" data-i18n="landing.contact_send"><?= escape(t('landing.contact_send')) ?></button>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="contact-card h-100">
                            <h3 data-i18n="landing.connect_title"><?= escape(t('landing.connect_title')) ?></h3>
                            <p class="mb-4" data-i18n="landing.connect_text"><?= escape(t('landing.connect_text')) ?></p>
                            <ul class="contact-list">
                                <li><i class="bi bi-envelope"></i> <?= escape($landingContact['email']) ?></li>
                                <li><i class="bi bi-geo-alt"></i> <?= escape($landingContact['address']) ?></li>
                                <li><i class="bi bi-telephone"></i> <?= escape($landingContact['phone']) ?></li>
                            </ul>
                            <div class="social-links mt-3">
                                <a href="#" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                                <a href="#" aria-label="Github"><i class="bi bi-github"></i></a>
                                <a href="#" aria-label="X"><i class="bi bi-twitter-x"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="landing-footer py-4">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <div class="text-center text-md-start">
                <div class="fw-semibold" data-i18n="landing.footer_copy"><?= escape(t('landing.footer_copy')) ?></div>
                <div class="small text-white-50" style="font-size: 18px !important; font-weight: bold !important;" data-i18n="landing.footer_rights"><?= escape(t('landing.footer_rights')) ?></div>
            </div>
            <div class="d-flex gap-3 flex-wrap justify-content-center">
                <a href="#home" data-i18n="landing.nav_home"><?= escape(t('landing.nav_home')) ?></a>
                <a href="#about" data-i18n="landing.nav_about"><?= escape(t('landing.nav_about')) ?></a>
                <a href="#contact" data-i18n="landing.nav_contact"><?= escape(t('landing.nav_contact')) ?></a>
            </div>
        </div>
    </footer>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
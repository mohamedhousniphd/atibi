document.addEventListener('DOMContentLoaded', function () {
    var mobileMenuBtn = document.getElementById('mobileMenuBtn');
    var mobileMenu = document.getElementById('mobileMenu');
    var contactForm = document.getElementById('contactForm');
    var particlesContainer = document.getElementById('particles');
    var i18n = window.LANDING_I18N || {};
    var base = window.APP_BASE || '';

    function applyLandingLang(lang) {
        if (!i18n[lang]) return;
        var dict = i18n[lang];
        document.querySelectorAll('[data-i18n]').forEach(function (el) {
            var key = el.getAttribute('data-i18n');
            if (key && dict[key] !== undefined) {
                el.textContent = dict[key];
            }
        });
        document.documentElement.setAttribute('lang', lang);
        document.documentElement.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');
        localStorage.setItem('ui_lang', lang);
        document.querySelectorAll('.landing-lang .lang-switch').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-lang') === lang);
        });
        var fd = new FormData();
        fd.append('lang', lang);
        fd.append('format', 'json');
        var pathname = window.location.pathname || '/';
        var base = (window.APP_BASE || '').replace(/\/$/, '');
        var retPath = pathname;
        if (base && pathname.indexOf(base) === 0) {
            retPath = pathname.slice(base.length) || '/';
            if (retPath.charAt(0) !== '/') {
                retPath = '/' + retPath;
            }
        }
        fd.append('return', retPath);
        fetch(base + '/pages/set_lang.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
            credentials: 'same-origin'
        }).catch(function () { /* offline */ });
    }

    var stored = localStorage.getItem('ui_lang');
    var initial = (stored && i18n[stored]) ? stored : (window.LANDING_INITIAL_LANG || 'fr');
    if (initial && i18n[initial]) {
        applyLandingLang(initial);
    }

    document.querySelectorAll('.landing-lang .lang-switch').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var lang = btn.getAttribute('data-lang');
            if (lang) applyLandingLang(lang);
        });
    });

    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', function () {
            mobileMenu.classList.toggle('open');
        });
    }

    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (event) {
            var targetId = this.getAttribute('href');
            var target = document.querySelector(targetId);
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (mobileMenu) mobileMenu.classList.remove('open');
        });
    });

    var revealElements = document.querySelectorAll('.section-reveal');
    if (revealElements.length) {
        var observer = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        revealElements.forEach(function (el) {
            observer.observe(el);
        });
    }

    if (particlesContainer) {
        var colors = ['#00e5c3','#05c17a','#b6ff4e','#00bfa5','#7fffd4'];
        for (var i = 0; i < 22; i++) {
            var particle = document.createElement('div');
            particle.className = 'particle';
            var size = Math.random() * 55 + 20;
            var color = colors[Math.floor(Math.random() * colors.length)];
            particle.style.cssText = [
                'width:'  + size + 'px',
                'height:' + size + 'px',
                'left:'   + (Math.random()*100) + '%',
                'bottom:' + (Math.random()*15-5) + '%',
                'background: radial-gradient(circle at 35% 35%, rgba(255,255,255,.35), ' + color + '22 55%, transparent 80%)',
                'border: 1.5px solid ' + color + '55',
                'box-shadow: 0 0 ' + (size*0.4) + 'px ' + color + '33, inset 0 0 ' + (size*0.3) + 'px rgba(255,255,255,.08)',
                'opacity:' + (Math.random()*.55+.3),
                'animation-duration:' + (Math.random()*16+10) + 's',
                'animation-delay:' + (Math.random()*12) + 's'
            ].join(';');
            particlesContainer.appendChild(particle);
        }
    }

    if (contactForm) {
        contactForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var lang = document.documentElement.getAttribute('lang') || 'fr';
            var dict = i18n[lang] || {};
            var msg = dict['landing.alert_sent'] || window.LANDING_ALERT_SENT || 'Thanks.';
            alert(msg);
            contactForm.reset();
        });
    }
});
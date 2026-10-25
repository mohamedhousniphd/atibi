<?php
require_once dirname(__DIR__) . '/config/config.php';

if (isLoggedIn()) {
    redirect_after_login();
}

$error = '';
$errorType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = trim($_POST['username'] ?? '');
    if (!verify_csrf()) {
        $error = t('login.err_invalid');
        $errorType = 'invalid_request';
    } else {
        $password = $_POST['password'] ?? '';
        if ($loginInput === '' || $password === '') {
            $error = t('login.err_empty');
            $errorType = 'empty';
        } else {
            try {
                $conn = getDB();
                $account = authenticate_by_email($conn, $loginInput, $password);
                if ($account) {
                    establish_session_from_account($account);
                    redirect_after_login();
                }
                $error = t('login.err_no_account');
                $errorType = 'no_account';
            } catch (mysqli_sql_exception $e) {
                $error = t('login.err_save');
                $errorType = 'invalid_request';
            }
        }
    }
    if ($error) {
        $_SESSION['old'] = ['username' => $loginInput];
    }
}

$pageTitle = 'Login - ' . APP_NAME;
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
  @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap');

  :root {
    --teal:    #00e5c3;
    --green:   #05c17a;
    --acid:    #b6ff4e;
    --dark:    #060d12;
    --surface: #0c1a22;
    --glass:   rgba(0,229,195,0.06);
    --border:  rgba(0,229,195,0.18);
    --text:    #d4f0eb;
    --muted:   #5e8f84;
  }

  body {
    background: var(--dark);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
    overflow-x: hidden;
  }

  /* ── Animated background ──────────────────────── */
  .chem-bg {
    position: fixed; inset: 0; z-index: 0; overflow: hidden;
    background:
      radial-gradient(ellipse 80% 60% at 80% 10%, #00251e 0%, transparent 60%),
      radial-gradient(ellipse 60% 80% at 20% 90%, #0a1f0e 0%, transparent 60%),
      var(--dark);
  }

  .mol-ring {
    position: absolute; border-radius: 50%; border: 1px solid;
    animation: spin-slow linear infinite;
  }
  .mol-ring:nth-child(1) { width:380px; height:380px; top:-100px; left:-80px;  border-color:rgba(0,229,195,.12); animation-duration:30s; }
  .mol-ring:nth-child(2) { width:240px; height:240px; top:-50px;  left:-20px;  border-color:rgba(5,193,122,.18); animation-duration:20s; animation-direction:reverse; }
  .mol-ring:nth-child(3) { width:160px; height:160px; top:  20px; left: 50px;  border-color:rgba(182,255,78,.14); animation-duration:13s; }
  .mol-ring:nth-child(4) { width:480px; height:480px; bottom:-180px; right:-130px; border-color:rgba(0,229,195,.08); animation-duration:38s; animation-direction:reverse; }
  .mol-ring:nth-child(5) { width:280px; height:280px; bottom:-60px;  right:-40px;  border-color:rgba(5,193,122,.13); animation-duration:24s; }

  @keyframes spin-slow { to { transform: rotate(360deg); } }

  .particle {
    position: absolute; border-radius: 50%; pointer-events: none;
    animation: float-up linear infinite;
  }
  @keyframes float-up {
    0%   { transform: translateY(0) scale(1);   opacity: 0; }
    20%  { opacity: .7; }
    80%  { opacity: .4; }
    100% { transform: translateY(-100vh) scale(.3); opacity: 0; }
  }

  .grid-overlay {
    position: absolute; inset: 0;
    background-image:
      linear-gradient(rgba(0,229,195,.03) 1px, transparent 1px),
      linear-gradient(90deg, rgba(0,229,195,.03) 1px, transparent 1px);
    background-size: 60px 60px;
  }

  /* ── Layout ───────────────────────────────────── */
  .login-wrapper {
    position: relative; z-index: 1;
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
    padding: 2rem 1rem;
  }

  /* ── Card ─────────────────────────────────────── */
  .login-card {
    width: 100%; max-width: 440px;
    background: var(--glass);
    border: 1px solid var(--border);
    border-radius: 24px;
    backdrop-filter: blur(20px);
    padding: 2.8rem 2.4rem;
    box-shadow:
      0 0 0 1px rgba(0,229,195,.06),
      0 24px 64px rgba(0,0,0,.6),
      0 0 80px rgba(0,229,195,.05) inset;
    animation: card-in .7s cubic-bezier(.22,1,.36,1) both;
    transition: transform .4s ease, box-shadow .4s ease;
  }
  .login-card:hover {
    box-shadow:
      0 0 0 1px rgba(0,229,195,.12),
      0 32px 80px rgba(0,0,0,.7),
      0 0 120px rgba(0,229,195,.08) inset;
  }
  @keyframes card-in {
    from { opacity:0; transform: translateY(40px) scale(.97); }
    to   { opacity:1; transform: translateY(0) scale(1); }
  }

  /* ── Header ───────────────────────────────────── */
  .card-head { text-align: center; margin-bottom: 2rem; }

  .flask-icon {
    width: 68px; height: 68px; margin: 0 auto 1rem;
    position: relative; display: flex; align-items: center; justify-content: center;
  }
  .flask-icon svg { width: 100%; height: 100%; }
  .flask-icon::before {
    content: '';
    position: absolute; inset: -6px; border-radius: 50%;
    background: conic-gradient(var(--teal), var(--green), var(--acid), var(--teal));
    animation: spin-slow 4s linear infinite;
    -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #fff calc(100% - 2px));
    mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #fff calc(100% - 2px));
  }

  .card-head h2 {
    font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.65rem;
    color: #fff; letter-spacing: -.02em; margin: 0 0 .3rem;
  }
  .card-head p { color: var(--muted); font-size: .875rem; margin: 0; }
  .badge-lab {
    display: inline-flex; align-items: center; gap: .35rem;
    background: rgba(0,229,195,.1); border: 1px solid rgba(0,229,195,.2);
    color: var(--teal); font-size: .72rem; padding: .25rem .7rem;
    border-radius: 99px; margin-bottom: .75rem;
    font-family: 'Syne', sans-serif; font-weight: 600;
    letter-spacing: .06em; text-transform: uppercase;
  }

  /* ── Alerts ───────────────────────────────────── */
  .alert-chem {
    border-radius: 12px; padding: .75rem 1rem;
    font-size: .875rem; margin-bottom: 1.5rem;
    display: flex; align-items: flex-start; gap: .5rem;
    animation: alert-in .35s ease both;
  }
  @keyframes alert-in {
    from { opacity:0; transform: translateY(-8px); }
    to   { opacity:1; transform: translateY(0); }
  }
  .alert-chem.danger {
    background: rgba(255,60,60,.08);
    border: 1px solid rgba(255,60,60,.25);
    color: #ff8080;
  }
  .alert-chem.warning {
    background: rgba(255,190,50,.07);
    border: 1px solid rgba(255,190,50,.22);
    color: #ffd080;
  }
  .alert-chem.success-msg {
    background: rgba(0,229,195,.08);
    border: 1px solid rgba(0,229,195,.22);
    color: var(--teal);
  }

  /* ── Form fields ──────────────────────────────── */
  .field-group { margin-bottom: 1.1rem; }
  .field-group label {
    display: block; font-size: .78rem; font-weight: 500;
    color: var(--teal); letter-spacing: .05em; text-transform: uppercase;
    margin-bottom: .4rem; font-family: 'Syne', sans-serif;
  }
  .field-wrap { position: relative; display: flex; align-items: center; }
  .field-wrap .field-icon {
    position: absolute; left: .9rem; color: var(--muted);
    pointer-events: none; font-size: 1rem; transition: color .25s;
  }
  .field-wrap input {
    width: 100%;
    background: rgba(0,229,195,.04);
    border: 1px solid rgba(0,229,195,.15);
    border-radius: 12px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif; font-size: .9rem;
    padding: .72rem 1rem .72rem 2.6rem;
    outline: none;
    transition: border-color .25s, background .25s, box-shadow .25s;
  }
  .field-wrap input::placeholder { color: var(--muted); }
  .field-wrap input:focus {
    border-color: var(--teal);
    background: rgba(0,229,195,.07);
    box-shadow: 0 0 0 3px rgba(0,229,195,.12);
  }
  .field-wrap:focus-within .field-icon { color: var(--teal); }
  .field-glow {
    position: absolute; inset: -1px; border-radius: 13px;
    box-shadow: 0 0 16px rgba(0,229,195,.15);
    opacity: 0; pointer-events: none; transition: opacity .25s;
  }
  .field-wrap input:focus ~ .field-glow { opacity: 1; }

  /* ── Submit ───────────────────────────────────── */
  .btn-login {
    width: 100%; margin-top: 1.6rem;
    padding: .85rem;
    border: none; border-radius: 14px; cursor: pointer;
    font-family: 'Syne', sans-serif; font-weight: 700; font-size: .95rem;
    letter-spacing: .04em;
    background: linear-gradient(135deg, var(--teal) 0%, var(--green) 60%, var(--acid) 100%);
    color: #050f0c;
    position: relative; overflow: hidden;
    transition: transform .2s, box-shadow .2s, filter .2s;
    box-shadow: 0 4px 24px rgba(0,229,195,.25);
  }
  .btn-login::before {
    content: '';
    position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.2), transparent);
    transition: left .4s;
  }
  .btn-login:hover::before { left: 100%; }
  .btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 32px rgba(0,229,195,.4);
    filter: brightness(1.08);
  }
  .btn-login:active { transform: translateY(0); }

  /* ── Footer ───────────────────────────────────── */
  .login-footer { text-align: center; margin-top: 1.4rem; font-size: .85rem; color: var(--muted); }
  .login-footer a { color: var(--teal); text-decoration: none; font-weight: 500; transition: color .2s; }
  .login-footer a:hover { color: var(--acid); }

  .divider {
    display: flex; align-items: center; gap: .75rem;
    margin: 1.4rem 0 1.2rem; color: var(--muted); font-size: .78rem;
  }
  .divider::before, .divider::after {
    content: ''; flex: 1; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(0,229,195,.15), transparent);
  }

  .lang-top { position: fixed; top: 1rem; right: 1rem; z-index: 10; }
</style>

<!-- Background -->
<div class="chem-bg">
  <div class="grid-overlay"></div>
  <div class="mol-ring"></div>
  <div class="mol-ring"></div>
  <div class="mol-ring"></div>
  <div class="mol-ring"></div>
  <div class="mol-ring"></div>
  <div id="particles"></div>
</div>

<!-- Lang switcher -->
<div class="lang-top">
  <?php $switcherVariant = 'app'; require dirname(__DIR__) . '/includes/lang_switcher.php'; ?>
</div>

<!-- Main -->
<div class="login-wrapper">
  <div class="login-card" id="loginCard">

    <!-- Header -->
    <div class="card-head">
      <div class="flask-icon">
        <svg viewBox="0 0 68 68" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M24 8h20v24l12 24H12L24 32V8Z" fill="url(#flaskGrad)" opacity=".9"/>
          <path d="M24 8h20v24l12 24H12L24 32V8Z" stroke="url(#flaskStroke)" stroke-width="1.5"/>
          <circle cx="28" cy="46" r="3" fill="rgba(182,255,78,.7)"/>
          <circle cx="40" cy="50" r="2" fill="rgba(0,229,195,.6)"/>
          <circle cx="33" cy="43" r="1.5" fill="rgba(5,193,122,.8)"/>
          <defs>
            <linearGradient id="flaskGrad" x1="12" y1="8" x2="56" y2="56" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#00e5c3" stop-opacity=".25"/>
              <stop offset="100%" stop-color="#05c17a" stop-opacity=".15"/>
            </linearGradient>
            <linearGradient id="flaskStroke" x1="12" y1="8" x2="56" y2="56" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#00e5c3"/>
              <stop offset="100%" stop-color="#b6ff4e"/>
            </linearGradient>
          </defs>
        </svg>
      </div>
      <span class="badge-lab">⚗ TP Planner Lab</span>
      <h2><?= escape(APP_NAME) ?></h2>
      <p><?= escape(t('login.title')) ?></p>
    </div>

    <!-- Flash success -->
    <?php if ($msg = flash('success')): ?>
      <div class="alert-chem success-msg">
        <span>✔</span><span><?= escape($msg) ?></span>
      </div>
    <?php endif; ?>

    <!-- Error -->
    <?php if ($error): ?>
      <div id="loginAlert" class="alert-chem <?= $errorType === 'no_account' ? 'warning' : 'danger' ?>">
        <span><?= $errorType === 'no_account' ? '👤' : '⚠' ?></span>
        <span><?= escape($error) ?></span>
      </div>
    <?php endif; ?>

    <!-- Form — NO logic changed -->
    <form method="post" action="">
      <?= csrf_field() ?>

      <div class="field-group">
        <label><?= escape(t('login.email')) ?></label>
        <div class="field-wrap">
          <span class="field-icon">✉</span>
          <input type="email" name="username"
                 value="<?= escape(old('username')) ?>"
                 autocomplete="email" required autofocus
                 placeholder="votre@email.com">
          <div class="field-glow"></div>
        </div>
      </div>

      <div class="divider">sécurité</div>

      <div class="field-group">
        <label><?= escape(t('login.password')) ?></label>
        <div class="field-wrap">
          <span class="field-icon">🔒</span>
          <input type="password" name="password"
                 autocomplete="current-password" required
                 placeholder="••••••••">
          <div class="field-glow"></div>
        </div>
      </div>

      <button type="submit" class="btn-login">
        <?= escape(t('login.submit')) ?> →
      </button>
    </form>

    <p class="login-footer">
      <a href="<?= APP_URL ?>/register.php"><?= escape(t('login.register_link')) ?></a>
    </p>

  </div>
</div>

<script>
/* ── Bubble generator ───────────────────────── */
(function() {
  const container = document.getElementById('particles');
  const colors = ['#00e5c3','#05c17a','#b6ff4e','#00bfa5','#7fffd4'];
  for (let i = 0; i < 22; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    const size = Math.random() * 55 + 20;
    const color = colors[Math.floor(Math.random() * colors.length)];
    p.style.cssText = [
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
    container.appendChild(p);
  }
})();

/* ── 3-D card tilt ───────────────────────────── */
(function() {
  const card = document.getElementById('loginCard');
  if (!card) return;
  card.addEventListener('mousemove', (e) => {
    const r = card.getBoundingClientRect();
    const x = (e.clientX - r.left) / r.width  - .5;
    const y = (e.clientY - r.top)  / r.height - .5;
    card.style.transform = `perspective(900px) rotateX(${-y*6}deg) rotateY(${x*8}deg) translateY(-4px)`;
  });
  card.addEventListener('mouseleave', () => {
    card.style.transform = '';
  });
})();

/* ── Scroll alert into view ──────────────────── */
(function() {
  var alertEl = document.getElementById('loginAlert');
  if (alertEl) alertEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
})();
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
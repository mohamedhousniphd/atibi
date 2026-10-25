<?php
require_once dirname(__DIR__) . '/config/config.php';

if (isLoggedIn()) {
    redirect_after_login();
}

$error = '';
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;

$conn = getDB();
$classes = [];
if ($conn) {
    $cq = $conn->query('SELECT id, name FROM classes ORDER BY name');
    if ($cq) {
        $classes = $cq->fetch_all(MYSQLI_ASSOC);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = t('register.err_invalid');
    } else {
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        if ($name === '') {
            $error = t('register.err_name');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = t('register.err_email');
        } elseif (strlen($password) < 8) {
            $error = t('register.err_password_len');
        } elseif ($password !== $password2) {
            $error = t('register.err_match');
        } elseif (!students_table_ready($conn)) {
            $error = t('register.err_schema');
        } elseif (empty($classes)) {
            $error = t('register.err_no_classes');
        } elseif ($class_id <= 0) {
            $error = t('register.err_class');
        } else {
            $validClass = false;
            foreach ($classes as $c) {
                if ((int) $c['id'] === $class_id) {
                    $validClass = true;
                    break;
                }
            }
            if (!$validClass) {
                $error = t('register.err_class');
            } else {
                try {
                    if (email_exists_any($conn, $email)) {
                        $error = t('register.err_exists');
                    }

                    if ($error === '') {
                        $storedPass = hash_user_password($password);
                        $stmt = $conn->prepare('INSERT INTO students (name, email, password, class_id) VALUES (?, ?, ?, ?)');
                        $stmt->bind_param('sssi', $name, $email, $storedPass, $class_id);
                        $stmt->execute();
                        flash('success', t('register.success'));
                        unset($_SESSION['old']);
                        redirect(APP_URL . '/pages/login.php');
                    }
                } catch (mysqli_sql_exception $e) {
                    if ((int) $e->getCode() === 1062) {
                        $error = t('register.err_exists');
                    } else {
                        $error = t('register.err_save');
                    }
                } catch (Exception $e) {
                    $error = t('register.err_save');
                }
            }
        }
    }
    if ($error) {
        $_SESSION['old'] = ['name' => $name, 'email' => $email, 'class_id' => $class_id];
    }
} else {
    $name = old('name');
    $email = old('email');
    $class_id = (int) old('class_id', 0);
    unset($_SESSION['old']);
}

$pageTitle = 'Register - ' . APP_NAME;
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

  /* ── Reset & base ─────────────────────────────── */
  body { background: var(--dark); color: var(--text); font-family: 'DM Sans', sans-serif; min-height: 100vh; overflow-x: hidden; }

  /* ── Animated background ──────────────────────── */
  .chem-bg {
    position: fixed; inset: 0; z-index: 0; overflow: hidden;
    background: radial-gradient(ellipse 80% 60% at 20% 10%, #00251e 0%, transparent 60%),
                radial-gradient(ellipse 60% 80% at 80% 90%, #0a1f0e 0%, transparent 60%),
                var(--dark);
  }

  /* Floating molecule rings */
  .mol-ring {
    position: absolute; border-radius: 50%; border: 1px solid;
    animation: spin-slow linear infinite;
  }
  .mol-ring:nth-child(1) { width:420px; height:420px; top:-120px; right:-80px; border-color:rgba(0,229,195,.12); animation-duration:28s; }
  .mol-ring:nth-child(2) { width:260px; height:260px; top:-60px; right:-20px; border-color:rgba(5,193,122,.18); animation-duration:18s; animation-direction: reverse; }
  .mol-ring:nth-child(3) { width:180px; height:180px; top: 10px; right:60px; border-color:rgba(182,255,78,.14); animation-duration:12s; }
  .mol-ring:nth-child(4) { width:520px; height:520px; bottom:-200px; left:-140px; border-color:rgba(0,229,195,.08); animation-duration:35s; animation-direction:reverse; }
  .mol-ring:nth-child(5) { width:300px; height:300px; bottom:-80px; left:-50px; border-color:rgba(5,193,122,.13); animation-duration:22s; }

  @keyframes spin-slow { to { transform: rotate(360deg); } }

  /* Particles */
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

  /* Grid lines */
  .grid-overlay {
    position: absolute; inset: 0;
    background-image:
      linear-gradient(rgba(0,229,195,.03) 1px, transparent 1px),
      linear-gradient(90deg, rgba(0,229,195,.03) 1px, transparent 1px);
    background-size: 60px 60px;
  }

  /* ── Layout ───────────────────────────────────── */
  .register-wrapper {
    position: relative; z-index: 1;
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
    padding: 2rem 1rem;
  }

  /* ── Card ─────────────────────────────────────── */
  .register-card {
    width: 100%; max-width: 480px;
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
    transform-style: preserve-3d;
    transition: transform .4s ease, box-shadow .4s ease;
  }
  .register-card:hover {
    transform: translateY(-4px) rotateX(1deg);
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
    position: absolute; inset: -6px;
    border-radius: 50%;
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
  .card-head .badge-lab {
    display: inline-flex; align-items: center; gap: .35rem;
    background: rgba(0,229,195,.1); border: 1px solid rgba(0,229,195,.2);
    color: var(--teal); font-size: .72rem; padding: .25rem .7rem;
    border-radius: 99px; margin-bottom: .75rem; font-family: 'Syne', sans-serif; font-weight: 600;
    letter-spacing: .06em; text-transform: uppercase;
  }

  /* ── Error alert ──────────────────────────────── */
  .alert-chem {
    background: rgba(255,60,60,.08);
    border: 1px solid rgba(255,60,60,.25);
    border-radius: 12px; padding: .75rem 1rem;
    color: #ff8080; font-size: .875rem; margin-bottom: 1.5rem;
    display: flex; align-items: flex-start; gap: .5rem;
  }

  /* ── Form fields ──────────────────────────────── */
  .field-group { margin-bottom: 1.1rem; }

  .field-group label {
    display: block; font-size: .78rem; font-weight: 500;
    color: var(--teal); letter-spacing: .05em; text-transform: uppercase;
    margin-bottom: .4rem; font-family: 'Syne', sans-serif;
  }

  .field-wrap {
    position: relative;
    display: flex; align-items: center;
  }
  .field-wrap .field-icon {
    position: absolute; left: .9rem; color: var(--muted);
    pointer-events: none; font-size: 1rem;
    transition: color .25s;
  }

  .field-wrap input,
  .field-wrap select {
    width: 100%;
    background: rgba(0,229,195,.04);
    border: 1px solid rgba(0,229,195,.15);
    border-radius: 12px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif; font-size: .9rem;
    padding: .72rem 1rem .72rem 2.6rem;
    outline: none;
    transition: border-color .25s, background .25s, box-shadow .25s;
    -webkit-appearance: none;
  }
  .field-wrap select option { background: #0c1e26; color: var(--text); }

  .field-wrap input::placeholder { color: var(--muted); }

  .field-wrap input:focus,
  .field-wrap select:focus {
    border-color: var(--teal);
    background: rgba(0,229,195,.07);
    box-shadow: 0 0 0 3px rgba(0,229,195,.12);
  }
  .field-wrap input:focus + .field-glow,
  .field-wrap select:focus + .field-glow { opacity: 1; }
  .field-wrap:focus-within .field-icon { color: var(--teal); }

  .field-glow {
    position: absolute; inset: -1px; border-radius: 13px;
    background: transparent;
    box-shadow: 0 0 16px rgba(0,229,195,.15);
    opacity: 0; pointer-events: none;
    transition: opacity .25s;
  }

  /* ── Submit button ────────────────────────────── */
  .btn-register {
    width: 100%; margin-top: 1.4rem;
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
  .btn-register::before {
    content: '';
    position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.2), transparent);
    transition: left .4s;
  }
  .btn-register:hover::before { left: 100%; }
  .btn-register:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 32px rgba(0,229,195,.4);
    filter: brightness(1.08);
  }
  .btn-register:active { transform: translateY(0); }
  .btn-register:disabled {
    opacity: .4; cursor: not-allowed;
    background: #1a2e2b;
    box-shadow: none; color: var(--muted);
  }

  /* ── Footer link ──────────────────────────────── */
  .register-footer { text-align: center; margin-top: 1.4rem; font-size: .85rem; color: var(--muted); }
  .register-footer a { color: var(--teal); text-decoration: none; font-weight: 500; transition: color .2s; }
  .register-footer a:hover { color: var(--acid); }

  /* ── Divider ──────────────────────────────────── */
  .divider {
    display: flex; align-items: center; gap: .75rem;
    margin: 1.5rem 0 1.2rem; color: var(--muted); font-size: .78rem;
  }
  .divider::before, .divider::after {
    content: ''; flex: 1; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(0,229,195,.15), transparent);
  }

  /* ── Lang switcher override ───────────────────── */
  .lang-top {
    position: fixed; top: 1rem; right: 1rem; z-index: 10;
  }
</style>

<!-- Animated background -->
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
<div class="register-wrapper">
  <div class="register-card" id="regCard">

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
      <p><?= escape(t('register.title')) ?> — <?= escape(t('register.subtitle_trainee')) ?></p>
    </div>

    <!-- Error -->
    <?php if ($error): ?>
      <div class="alert-chem">
        <span>⚠</span>
        <span><?= escape($error) ?></span>
      </div>
    <?php endif; ?>

    <!-- Form — NO logic changed -->
    <form method="post" action="">
      <?= csrf_field() ?>

      <div class="field-group">
        <label><?= escape(t('register.name')) ?></label>
        <div class="field-wrap">
          <span class="field-icon">👤</span>
          <input type="text" name="name" value="<?= escape($name) ?>" required autocomplete="name" placeholder="Votre nom complet">
          <div class="field-glow"></div>
        </div>
      </div>

      <div class="field-group">
        <label><?= escape(t('register.email')) ?></label>
        <div class="field-wrap">
          <span class="field-icon">✉</span>
          <input type="email" name="email" value="<?= escape($email) ?>" required autocomplete="email" placeholder="adresse@email.com">
          <div class="field-glow"></div>
        </div>
      </div>

      <div class="field-group">
        <label><?= escape(t('register.class_label')) ?></label>
        <div class="field-wrap">
          <span class="field-icon">🎓</span>
          <select name="class_id" required <?= empty($classes) ? 'disabled' : '' ?>>
            <option value=""><?= escape(t('register.class_placeholder')) ?></option>
            <?php foreach ($classes as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= $class_id === (int) $c['id'] ? 'selected' : '' ?>>
                <?= escape($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="field-glow"></div>
        </div>
      </div>

      <div class="divider">sécurité</div>

      <div class="field-group">
        <label><?= escape(t('register.password')) ?></label>
        <div class="field-wrap">
          <span class="field-icon">🔒</span>
          <input type="password" name="password" required autocomplete="new-password" minlength="8" placeholder="Minimum 8 caractères">
          <div class="field-glow"></div>
        </div>
      </div>

      <div class="field-group">
        <label><?= escape(t('register.password2')) ?></label>
        <div class="field-wrap">
          <span class="field-icon">🔑</span>
          <input type="password" name="password2" required autocomplete="new-password" minlength="8" placeholder="Confirmez le mot de passe">
          <div class="field-glow"></div>
        </div>
      </div>

      <button type="submit" class="btn-register" <?= empty($classes) ? 'disabled' : '' ?>>
        <?= escape(t('register.submit')) ?> →
      </button>
    </form>

    <p class="register-footer">
      <?= escape(t('register.have_account')) ?>
      <a href="<?= APP_URL ?>/pages/login.php"><?= escape(t('register.login_link')) ?></a>
    </p>

  </div><!-- /card -->
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

/* ── 3-D card tilt on mouse move ─────────────── */
(function() {
  const card = document.getElementById('regCard');
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
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
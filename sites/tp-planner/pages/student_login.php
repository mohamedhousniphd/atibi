<?php
require_once dirname(__DIR__) . '/config/config.php';

if (isStudentLoggedIn()) {
    redirect(APP_URL . '/pages/student_dashboard.php');
}
if (isTeacherLoggedIn()) {
    redirect(APP_URL . '/pages/dashboard.php');
}

$error = '';
$errorType = ''; // 'no_account' | 'wrong_password' | 'empty' | 'invalid_request' | 'missing_table'

function studentsTableExists() {
    try {
        $conn = getDB();
        $r = $conn->query("SHOW TABLES LIKE 'students'");
        return $r && $r->num_rows > 0;
    } catch (Exception $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid request.';
        $errorType = 'invalid_request';
    } else {
        $loginInput = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($loginInput === '' || $password === '') {
            $error = 'Veuillez saisir l\'email et le mot de passe.';
            $errorType = 'empty';
        } elseif (!studentsTableExists()) {
            $error = 'La table students n’existe pas encore. Exécutez le script SQL fourni (database/new_tables.sql).';
            $errorType = 'missing_table';
        } else {
            $conn = getDB();
            $stmt = $conn->prepare('SELECT id, name, email, password, class_id FROM students WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $loginInput);
            $stmt->execute();
            $student = $stmt->get_result()->fetch_assoc();

            if ($student && $password === (string)$student['password']) {
                $_SESSION['auth_type'] = 'student';
                $_SESSION['student_id'] = (int) $student['id'];
                $_SESSION['username'] = $student['name'] ?? $student['email'];
                $_SESSION['role'] = 'student';
                $_SESSION['class_id'] = (int) ($student['class_id'] ?? 0);
                redirect(APP_URL . '/pages/student_dashboard.php');
            }

            if (!$student) {
                $error = "Cet utilisateur n'a pas de compte.";
                $errorType = 'no_account';
            } else {
                $error = 'Invalid email or password.';
                $errorType = 'wrong_password';
            }
        }
    }
}

$pageTitle = 'Student Login - ' . APP_NAME;
require_once dirname(__DIR__) . '/includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow border-0 rounded-3">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-mortarboard text-primary" style="font-size: 3rem;"></i>
                        <h4 class="mt-2 fw-bold">Étudiant</h4>
                        <p class="text-muted small mb-0">Connectez-vous à votre espace</p>
                    </div>
                    <?php if ($error): ?>
                        <div id="loginAlert" class="alert alert-dismissible fade show <?= $errorType === 'no_account' ? 'alert-warning' : 'alert-danger' ?> py-3" role="alert">
                            <strong><?= escape($error) ?></strong>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= escape(old('email')) ?>"
                                   autocomplete="email" required autofocus placeholder="votre@email.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mot de passe</label>
                            <input type="password" name="password" class="form-control" autocomplete="current-password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Se connecter</button>
                        <a href="<?= APP_URL ?>/index.php" class="btn btn-link w-100 mt-2">Retour</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    var alertEl = document.getElementById('loginAlert');
    if (alertEl) {
        alertEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        alertEl.classList.add('login-alert-show');
    }
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>


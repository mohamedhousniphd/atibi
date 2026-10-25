<?php
/**
 * Admin: manage accounts — trainees in `students`, admins in `users`.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireTeacher();

$conn = getDB();
$schemaOk = students_table_ready($conn);
$error = '';
if (!$schemaOk) {
    $error = t('register.err_schema');
}

$classes = [];
$cq = $conn->query('SELECT id, name FROM classes ORDER BY name');
if ($cq) {
    $classes = $cq->fetch_all(MYSQLI_ASSOC);
}

// Delete account
if ($schemaOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'], $_POST['account_type']) && verify_csrf()) {
    $did = (int) $_POST['delete_id'];
    $type = $_POST['account_type'] === 'users' ? 'users' : 'students';
    try {
        if ($type === 'users') {
            $st = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
            $st->bind_param('i', $did);
        } else {
            $st = $conn->prepare('DELETE FROM students WHERE id = ?');
            $st->bind_param('i', $did);
        }
        $st->execute();
        flash('success', t('stagiaires.flash_deleted'));
        redirect(APP_URL . '/pages/stagiaires.php');
    } catch (Throwable $e) {
        $error = t('stagiaires.err_delete');
    }
}

// Save edit (optional role change moves row between tables)
if ($schemaOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_id'], $_POST['account_type']) && verify_csrf()) {
    $sid = (int) $_POST['save_id'];
    $oldType = $_POST['account_type'] === 'users' ? 'users' : 'students';
    $name = trim($_POST['edit_name'] ?? '');
    $email = trim($_POST['edit_email'] ?? '');
    $newRole = $_POST['edit_role'] ?? 'trainee_teacher';
    $newRole = $newRole === 'admin' ? 'admin' : 'trainee_teacher';
    $class_id = !empty($_POST['edit_class_id']) ? (int) $_POST['edit_class_id'] : 0;
    $newPass = trim($_POST['edit_password'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('stagiaires.err_invalid');
    } elseif ($newRole === 'trainee_teacher' && $class_id <= 0) {
        $error = t('stagiaires.err_add_class');
    } elseif (email_exists_any($conn, $email, $oldType, $sid)) {
        $error = t('stagiaires.err_exists');
    } else {
        try {
            $conn->begin_transaction();
            $hash = $newPass !== '' ? hash_user_password($newPass) : null;

            $newType = $newRole === 'admin' ? 'users' : 'students';
            if ($oldType === $newType) {
                if ($oldType === 'users') {
                    $u = $conn->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
                    $u->bind_param('ssi', $name, $email, $sid);
                    $u->execute();
                    if ($hash !== null) {
                        $p = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                        $p->bind_param('si', $hash, $sid);
                        $p->execute();
                    }
                } else {
                    $u = $conn->prepare('UPDATE students SET name = ?, email = ?, class_id = ? WHERE id = ?');
                    $u->bind_param('ssii', $name, $email, $class_id, $sid);
                    $u->execute();
                    if ($hash !== null) {
                        $p = $conn->prepare('UPDATE students SET password = ? WHERE id = ?');
                        $p->bind_param('si', $hash, $sid);
                        $p->execute();
                    }
                }
            } else {
                // Role change: load old row, delete, insert in other table
                if ($oldType === 'users') {
                    $g = $conn->prepare('SELECT name, email, password FROM users WHERE id = ?');
                    $g->bind_param('i', $sid);
                    $g->execute();
                    $old = $g->get_result()->fetch_assoc();
                    $pw = $hash ?? ($old['password'] ?? '');
                    $conn->query('DELETE FROM users WHERE id = ' . $sid);
                    $ins = $conn->prepare('INSERT INTO students (name, email, password, class_id) VALUES (?, ?, ?, ?)');
                    $ins->bind_param('sssi', $name, $email, $pw, $class_id);
                    $ins->execute();
                } else {
                    $g = $conn->prepare('SELECT name, email, password FROM students WHERE id = ?');
                    $g->bind_param('i', $sid);
                    $g->execute();
                    $old = $g->get_result()->fetch_assoc();
                    $pw = $hash ?? ($old['password'] ?? '');
                    $conn->query('DELETE FROM students WHERE id = ' . $sid);
                    $ins = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
                    $ins->bind_param('sss', $name, $email, $pw);
                    $ins->execute();
                }
            }
            $conn->commit();
            flash('success', t('stagiaires.flash_updated'));
            redirect(APP_URL . '/pages/stagiaires.php');
        } catch (Throwable $e) {
            try {
                $conn->rollback();
            } catch (Throwable $e2) {
            }
            $error = t('stagiaires.err_save');
        }
    }
}

// Add account
if ($schemaOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_account']) && verify_csrf()) {
    $name = trim($_POST['add_name'] ?? '');
    $email = trim($_POST['add_email'] ?? '');
    $password = $_POST['add_password'] ?? '';
    $role = ($_POST['add_role'] ?? '') === 'admin' ? 'admin' : 'trainee_teacher';
    $class_id = isset($_POST['add_class_id']) ? (int) $_POST['add_class_id'] : 0;

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $error = t('stagiaires.err_add_invalid');
    } elseif ($role === 'trainee_teacher' && $class_id <= 0) {
        $error = t('stagiaires.err_add_class');
    } elseif (email_exists_any($conn, $email)) {
        $error = t('stagiaires.err_exists');
    } else {
        try {
            $hash = hash_user_password($password);
            if ($role === 'admin') {
                $ins = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
                $ins->bind_param('sss', $name, $email, $hash);
            } else {
                $ins = $conn->prepare('INSERT INTO students (name, email, password, class_id) VALUES (?, ?, ?, ?)');
                $ins->bind_param('sssi', $name, $email, $hash, $class_id);
            }
            $ins->execute();
            flash('success', t('stagiaires.flash_created'));
            redirect(APP_URL . '/pages/stagiaires.php');
        } catch (Throwable $e) {
            $error = t('stagiaires.err_save');
        }
    }
}

$trainees = [];
$admins = [];
if ($schemaOk) {
    $q = $conn->query("
        SELECT s.id, s.name, s.email, s.class_id, c.name AS class_name, 'students' AS account_type
        FROM students s
        LEFT JOIN classes c ON c.id = s.class_id
        ORDER BY s.name
    ");
    if ($q) {
        $trainees = $q->fetch_all(MYSQLI_ASSOC);
    }
    $q2 = $conn->query("
        SELECT u.id, u.name, u.email, 'users' AS account_type
        FROM users u
        WHERE u.role = 'admin'
        ORDER BY u.name
    ");
    if ($q2) {
        $admins = $q2->fetch_all(MYSQLI_ASSOC);
    }
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editType = ($_GET['type'] ?? '') === 'users' ? 'users' : 'students';
$editRow = null;
if ($editId > 0) {
    $list = $editType === 'users' ? $admins : $trainees;
    foreach ($list as $r) {
        if ((int) ($r['id'] ?? 0) === $editId) {
            $editRow = $r;
            $editRow['account_type'] = $editType;
            $editRow['edit_role'] = $editType === 'users' ? 'admin' : 'trainee_teacher';
            break;
        }
    }
}

$pageTitle = t('stagiaires.title') . ' - ' . APP_NAME;
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="page-title mb-0"><?= escape(t('stagiaires.title')) ?></h1>
        <a href="<?= APP_URL ?>/pages/dashboard.php" class="btn btn-outline-secondary"><?= escape(t('stagiaires.back_dashboard')) ?></a>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= escape($error) ?></div>
    <?php endif; ?>

    <?php if ($editRow): ?>
        <div class="card mb-4 border-primary">
            <div class="card-header bg-white"><strong><?= escape(t('stagiaires.edit_title')) ?></strong></div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="save_id" value="<?= (int) $editRow['id'] ?>">
                    <input type="hidden" name="account_type" value="<?= escape($editRow['account_type']) ?>">
                    <div class="col-md-3">
                        <label class="form-label"><?= escape(t('stagiaires.role_label')) ?></label>
                        <select name="edit_role" class="form-select" id="editRoleSelect">
                            <option value="trainee_teacher" <?= ($editRow['edit_role'] ?? '') === 'trainee_teacher' ? 'selected' : '' ?>><?= escape(t('role.trainee_teacher')) ?></option>
                            <option value="admin" <?= ($editRow['edit_role'] ?? '') === 'admin' ? 'selected' : '' ?>><?= escape(t('role.admin')) ?></option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><?= escape(t('register.name')) ?></label>
                        <input type="text" name="edit_name" class="form-control" value="<?= escape($editRow['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><?= escape(t('register.email')) ?></label>
                        <input type="email" name="edit_email" class="form-control" value="<?= escape($editRow['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3 edit-class-wrap">
                        <label class="form-label"><?= escape(t('register.class_label')) ?></label>
                        <select name="edit_class_id" class="form-select">
                            <option value=""><?= escape(t('register.class_placeholder')) ?></option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= (int) ($editRow['class_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= escape($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= escape(t('stagiaires.new_password_optional')) ?></label>
                        <input type="password" name="edit_password" class="form-control" autocomplete="new-password" minlength="8" placeholder="<?= escape(t('stagiaires.password_placeholder')) ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><?= escape(t('stagiaires.save')) ?></button>
                        <a href="<?= APP_URL ?>/pages/stagiaires.php" class="btn btn-outline-secondary"><?= escape(t('stagiaires.cancel')) ?></a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-white"><strong><?= escape(t('stagiaires.add_title')) ?></strong></div>
        <div class="card-body">
            <form method="post" class="row g-3" id="addAccountForm">
                <?= csrf_field() ?>
                <input type="hidden" name="add_account" value="1">
                <div class="col-md-2">
                    <label class="form-label"><?= escape(t('stagiaires.role_label')) ?></label>
                    <select name="add_role" class="form-select" id="addRoleSelect" <?= $schemaOk ? '' : 'disabled' ?>>
                        <option value="trainee_teacher"><?= escape(t('role.trainee_teacher')) ?></option>
                        <option value="admin"><?= escape(t('role.admin')) ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= escape(t('register.name')) ?></label>
                    <input type="text" name="add_name" class="form-control" required <?= $schemaOk ? '' : 'disabled' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= escape(t('register.email')) ?></label>
                    <input type="email" name="add_email" class="form-control" required <?= $schemaOk ? '' : 'disabled' ?>>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= escape(t('register.password')) ?></label>
                    <input type="password" name="add_password" class="form-control" required minlength="8" autocomplete="new-password" <?= $schemaOk ? '' : 'disabled' ?>>
                </div>
                <div class="col-md-3 add-class-wrap">
                    <label class="form-label"><?= escape(t('register.class_label')) ?></label>
                    <select name="add_class_id" class="form-select" <?= (empty($classes) || !$schemaOk) ? 'disabled' : '' ?>>
                        <option value=""><?= escape(t('register.class_placeholder')) ?></option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= escape($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-success" <?= !$schemaOk ? 'disabled' : '' ?>><?= escape(t('stagiaires.add_submit')) ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-white"><strong><?= escape(t('stagiaires.list_trainees')) ?></strong></div>
                <div class="card-body p-0">
                    <?php if (!$schemaOk): ?>
                        <p class="p-3 text-muted mb-0"><?= escape(t('register.err_schema')) ?></p>
                    <?php elseif (empty($trainees)): ?>
                        <p class="p-3 text-muted mb-0"><?= escape(t('stagiaires.empty')) ?></p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><?= escape(t('register.name')) ?></th>
                                        <th><?= escape(t('register.email')) ?></th>
                                        <th><?= escape(t('register.class_label')) ?></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trainees as $r): ?>
                                        <tr>
                                            <td><?= escape($r['name'] ?? '') ?></td>
                                            <td><?= escape($r['email'] ?? '') ?></td>
                                            <td><?= escape($r['class_name'] ?? '—') ?></td>
                                            <td class="text-end text-nowrap">
                                                <a class="btn btn-sm btn-outline-primary" href="<?= APP_URL ?>/pages/stagiaires.php?edit=<?= (int) $r['id'] ?>&type=students"><?= escape(t('stagiaires.btn_edit')) ?></a>
                                                <form method="post" class="d-inline" onsubmit="return confirm('<?= escape(t('stagiaires.confirm_delete')) ?>');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="delete_id" value="<?= (int) $r['id'] ?>">
                                                    <input type="hidden" name="account_type" value="students">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><?= escape(t('stagiaires.btn_delete')) ?></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white"><strong><?= escape(t('stagiaires.list_admins')) ?></strong></div>
                <div class="card-body p-0">
                    <?php if (empty($admins)): ?>
                        <p class="p-3 text-muted mb-0"><?= escape(t('stagiaires.empty_admins')) ?></p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><?= escape(t('register.name')) ?></th>
                                        <th><?= escape(t('register.email')) ?></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admins as $r): ?>
                                        <tr>
                                            <td><?= escape($r['name'] ?? '') ?></td>
                                            <td><?= escape($r['email'] ?? '') ?></td>
                                            <td class="text-end text-nowrap">
                                                <a class="btn btn-sm btn-outline-primary" href="<?= APP_URL ?>/pages/stagiaires.php?edit=<?= (int) $r['id'] ?>&type=users"><?= escape(t('stagiaires.btn_edit')) ?></a>
                                                <form method="post" class="d-inline" onsubmit="return confirm('<?= escape(t('stagiaires.confirm_delete')) ?>');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="delete_id" value="<?= (int) $r['id'] ?>">
                                                    <input type="hidden" name="account_type" value="users">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><?= escape(t('stagiaires.btn_delete')) ?></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
(function() {
    function toggleClass(selectId, wrapSelector) {
        var sel = document.getElementById(selectId);
        var wrap = document.querySelector(wrapSelector);
        if (!sel || !wrap) return;
        function upd() {
            var admin = sel.value === 'admin';
            wrap.style.display = admin ? 'none' : '';
            var inp = wrap.querySelector('select');
            if (inp) inp.required = !admin;
        }
        sel.addEventListener('change', upd);
        upd();
    }
    toggleClass('addRoleSelect', '.add-class-wrap');
    toggleClass('editRoleSelect', '.edit-class-wrap');
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

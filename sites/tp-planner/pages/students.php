<?php
require_once dirname(__DIR__) . '/config/config.php';
requireTeacher();

$conn = getDB();
$teacherId = currentTeacherId();
$isAdmin = isAdmin();

// Ensure students table exists (soft check)
$studentsTableOk = true;
try {
    $r = $conn->query("SHOW TABLES LIKE 'students'");
    $studentsTableOk = ($r && $r->num_rows > 0);
} catch (Exception $e) { $studentsTableOk = false; }

$error = '';

// Create student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_student']) && verify_csrf()) {
    if (!$studentsTableOk) {
        $error = 'La table students n’existe pas encore. Exécutez le script SQL (database/new_tables.sql).';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $classId = (int)($_POST['class_id'] ?? 0);

        if ($name === '' || $email === '' || $password === '' || $classId <= 0) {
            $error = 'Veuillez remplir tous les champs requis.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email invalide.';
        } else {
            try {
                if (!$isAdmin) {
                    $chk = $conn->prepare('SELECT id FROM classes WHERE id = ? AND teacher_id = ? LIMIT 1');
                    $chk->bind_param('ii', $classId, $teacherId);
                    $chk->execute();
                    if ($chk->get_result()->num_rows === 0) throw new Exception('Forbidden');
                }

                // Store password in plain text (simple comparison at login)
                $stmt = $conn->prepare('INSERT INTO students (name, email, password, class_id) VALUES (?,?,?,?)');
                $stmt->bind_param('sssi', $name, $email, $password, $classId);
                $stmt->execute();
                flash('success', 'Étudiant ajouté.');
                redirect(APP_URL . '/pages/students.php');
            } catch (mysqli_sql_exception $e) {
                if (stripos($e->getMessage(), 'Duplicate') !== false) {
                    $error = 'Cet email existe déjà.';
                } else {
                    $error = 'Enregistrement impossible.';
                }
            } catch (Exception $e) {
                $error = 'Enregistrement impossible.';
            }
        }
    }
}

// Delete student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student_id']) && verify_csrf()) {
    $sid = (int)($_POST['delete_student_id'] ?? 0);
    if ($sid > 0 && $studentsTableOk) {
        try {
            if ($isAdmin) {
                $conn->query("DELETE FROM students WHERE id = $sid");
            } else {
                $stmt = $conn->prepare('DELETE s FROM students s JOIN classes c ON c.id = s.class_id WHERE s.id = ? AND c.teacher_id = ?');
                $stmt->bind_param('ii', $sid, $teacherId);
                $stmt->execute();
                if ($stmt->affected_rows === 0) throw new Exception('Forbidden');
            }
            flash('success', 'Étudiant supprimé.');
            redirect(APP_URL . '/pages/students.php');
        } catch (Exception $e) {
            $error = 'Suppression impossible.';
        }
    }
}

// Classes list
$classes = [];
if ($isAdmin) {
    $classes = $conn->query('SELECT id, name FROM classes ORDER BY name')->fetch_all(MYSQLI_ASSOC);
} else {
    $stmt = $conn->prepare('SELECT id, name FROM classes WHERE teacher_id = ? ORDER BY name');
    $stmt->bind_param('i', $teacherId);
    $stmt->execute();
    $classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Students list (filtered)
$students = [];
if ($studentsTableOk) {
    if ($isAdmin) {
        $students = $conn->query('SELECT s.id, s.name, s.email, s.class_id, c.name AS class_name, s.created_at
                                  FROM students s LEFT JOIN classes c ON c.id = s.class_id
                                  ORDER BY s.id DESC')->fetch_all(MYSQLI_ASSOC);
    } else {
        $stmt = $conn->prepare('SELECT s.id, s.name, s.email, s.class_id, c.name AS class_name, s.created_at
                                FROM students s JOIN classes c ON c.id = s.class_id
                                WHERE c.teacher_id = ?
                                ORDER BY s.id DESC');
        $stmt->bind_param('i', $teacherId);
        $stmt->execute();
        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

$pageTitle = 'Students - ' . APP_NAME;
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="page-title mb-0">Students</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal">
            <i class="bi bi-plus-lg me-1"></i> Add student
        </button>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= escape($error) ?></div>
    <?php endif; ?>

    <?php if (!$studentsTableOk): ?>
        <div class="alert alert-warning">
            La table <code>students</code> n’existe pas. Exécutez le script <code>database/new_tables.sql</code>.
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <i class="bi bi-mortarboard"></i>
                    <p class="mb-0">No students yet</p>
                    <button type="button" class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#studentModal">Add student</button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Class</th>
                                <th>Created</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td><strong><?= escape($s['name']) ?></strong></td>
                                <td><?= escape($s['email']) ?></td>
                                <td><?= escape($s['class_name'] ?? '—') ?></td>
                                <td><?= !empty($s['created_at']) ? escape(date('d/m/Y', strtotime($s['created_at']))) : '—' ?></td>
                                <td>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Supprimer cet étudiant ?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="delete_student_id" value="<?= (int)$s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
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
</main>

<!-- Student modal -->
<div class="modal fade" id="studentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="" id="studentForm" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="create_student" value="1">
                <div class="modal-header">
                    <h5 class="modal-title">Add student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control js-required" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control js-required" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <div class="input-group">
                            <input type="text" name="password" class="form-control js-required" required id="studentPwd">
                            <button class="btn btn-outline-secondary" type="button" id="genPwd">Generate</button>
                        </div>
                        <div class="form-text">Le mot de passe sera stocké en clair (usage local / pédagogique).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Class *</label>
                        <select name="class_id" class="form-select js-required" required>
                            <option value="">— Select —</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= escape($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    function randomPwd(len) {
        len = len || 10;
        var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        var out = '';
        for (var i=0;i<len;i++) out += chars[Math.floor(Math.random()*chars.length)];
        return out;
    }
    document.getElementById('genPwd')?.addEventListener('click', function() {
        document.getElementById('studentPwd').value = randomPwd(10);
        document.getElementById('studentPwd').dispatchEvent(new Event('input'));
    });

    var form = document.getElementById('studentForm');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        var invalid = [];
        form.querySelectorAll('.js-required').forEach(function(el) {
            var v = (el.value || '').trim();
            if (!v) invalid.push(el);
        });
        invalid.forEach(function(el) { el.classList.add('is-invalid'); });
        if (invalid.length) {
            e.preventDefault();
            invalid[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
    form.querySelectorAll('.js-required').forEach(function(el) {
        el.addEventListener('input', function() { this.classList.remove('is-invalid'); });
        el.addEventListener('change', function() { this.classList.remove('is-invalid'); });
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>


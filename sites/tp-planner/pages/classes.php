<?php
require_once dirname(__DIR__) . '/config/config.php';
requireTeacher();

$conn = getDB();
$message = '';
$error = '';

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && verify_csrf()) {
    $id = (int) $_POST['delete_id'];
    try {
        $conn->query("DELETE FROM classes WHERE id = $id");
        flash('success', t('classes.flash_deleted'));
        redirect(APP_URL . '/pages/classes.php');
    } catch (Exception $e) {
        $error = t('classes.err_delete');
    }
}

// Create/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $name = trim($_POST['name'] ?? '');
    $teacher_id = !empty($_POST['teacher_id']) ? (int) $_POST['teacher_id'] : null;
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($name === '') {
        $error = t('classes.err_name');
    } else {
        try {
            if ($id) {
                if ($teacher_id === null) {
                    $stmt = $conn->prepare('UPDATE classes SET name = ?, teacher_id = NULL WHERE id = ?');
                    $stmt->bind_param('si', $name, $id);
                } else {
                    $stmt = $conn->prepare('UPDATE classes SET name = ?, teacher_id = ? WHERE id = ?');
                    $stmt->bind_param('sii', $name, $teacher_id, $id);
                }
                $stmt->execute();
                flash('success', t('classes.flash_updated'));
            } else {
                if ($teacher_id === null) {
                    $stmt = $conn->prepare('INSERT INTO classes (name, teacher_id) VALUES (?, NULL)');
                    $stmt->bind_param('s', $name);
                } else {
                    $stmt = $conn->prepare('INSERT INTO classes (name, teacher_id) VALUES (?, ?)');
                    $stmt->bind_param('si', $name, $teacher_id);
                }
                $stmt->execute();
                flash('success', t('classes.flash_created'));
            }
            redirect(APP_URL . '/pages/classes.php');
        } catch (Exception $e) {
            $error = t('classes.err_save');
        }
    }
}

// Teachers for dropdown
$teachers = $conn->query("SELECT id, name FROM users WHERE role = 'admin' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// List with search
$search = trim($_GET['search'] ?? '');
$sql = 'SELECT c.id, c.name, c.teacher_id,
        (SELECT COUNT(*) FROM tp_sessions WHERE class_id = c.id) AS session_count
        FROM classes c WHERE 1=1';
$params = [];
$types = '';
if ($search !== '') {
    $sql .= ' AND c.name LIKE ?';
    $params[] = "%$search%";
    $types = 's';
}
$sql .= ' ORDER BY c.name';
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($classes as &$c) {
    $c['teacher_name'] = null;
    if (!empty($c['teacher_id'])) {
        foreach ($teachers as $t) {
            if ((int)$t['id'] === (int)$c['teacher_id']) { $c['teacher_name'] = $t['name']; break; }
        }
    }
}
unset($c);

// Edit one
$edit = null;
if (isset($_GET['id']) && !isset($_GET['delete'])) {
    $editId = (int) $_GET['id'];
    foreach ($classes as $c) {
        if ((int)$c['id'] === $editId) { $edit = $c; break; }
    }
    if (!$edit) {
        $r = $conn->query("SELECT id, name, teacher_id FROM classes WHERE id = $editId");
        if ($r && $row = $r->fetch_assoc()) $edit = $row;
    }
}

$pageTitle = 'Classes - ' . APP_NAME;
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="page-title mb-0"><?= escape(t('classes.title')) ?></h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#classModal" data-action="create">
            <i class="bi bi-plus-lg me-1"></i> <?= escape(t('classes.add')) ?>
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

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-2">
                <div class="col-md-8">
                    <input type="text" name="search" class="form-control" placeholder="<?= escape(t('classes.search_ph')) ?>" value="<?= escape($search) ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-outline-primary me-2"><?= escape(t('classes.search')) ?></button>
                    <a href="<?= APP_URL ?>/pages/classes.php" class="btn btn-outline-secondary"><?= escape(t('classes.reset')) ?></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($classes)): ?>
                <div class="empty-state">
                    <i class="bi bi-people"></i>
                    <p class="mb-0"><?= escape(t('classes.empty')) ?></p>
                    <button type="button" class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#classModal"><?= escape(t('classes.add')) ?></button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?= escape(t('classes.col_name')) ?></th>
                                <th><?= escape(t('classes.col_teacher')) ?></th>
                                <th><?= escape(t('classes.col_sessions')) ?></th>
                                <th width="140"><?= escape(t('classes.col_actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $c): ?>
                                <tr>
                                    <td><strong><?= escape($c['name']) ?></strong></td>
                                    <td><?= escape($c['teacher_name'] ?? '—') ?></td>
                                    <td><span class="badge bg-secondary"><?= (int)($c['session_count'] ?? 0) ?></span></td>
                                    <td>
                                        <a href="<?= APP_URL ?>/pages/tp_sessions.php?class_id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary"><?= escape(t('classes.btn_sessions')) ?></a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-class" data-id="<?= (int)$c['id'] ?>" data-name="<?= escape($c['name']) ?>" data-teacher="<?= (int)($c['teacher_id'] ?? 0) ?>"><?= escape(t('classes.btn_edit')) ?></button>
                                        <form method="post" class="d-inline" data-confirm="<?= escape(t('classes.confirm_delete')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="delete_id" value="<?= (int)$c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><?= escape(t('classes.btn_delete')) ?></button>
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

<!-- Class modal -->
<div class="modal fade" id="classModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="classId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="classModalTitle"><?= escape(t('classes.modal_add')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= escape(t('classes.label_name')) ?></label>
                        <input type="text" name="name" id="className" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= escape(t('classes.label_teacher')) ?></label>
                        <select name="teacher_id" id="classTeacher" class="form-select">
                            <option value=""><?= escape(t('classes.teacher_none')) ?></option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= (int)$t['id'] ?>"><?= escape($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= escape(t('classes.cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><?= escape(t('classes.save')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.__CLASSES_I18N = {
    modalAdd: <?= json_encode(t('classes.modal_add'), JSON_UNESCAPED_UNICODE) ?>,
    modalEdit: <?= json_encode(t('classes.modal_edit'), JSON_UNESCAPED_UNICODE) ?>
};
document.querySelectorAll('.btn-edit-class').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('classId').value = this.dataset.id;
        document.getElementById('className').value = this.dataset.name;
        document.getElementById('classTeacher').value = this.dataset.teacher || '';
        document.getElementById('classModalTitle').textContent = window.__CLASSES_I18N.modalEdit;
        new bootstrap.Modal(document.getElementById('classModal')).show();
    });
});
document.getElementById('classModal').addEventListener('show.bs.modal', function(e) {
    if (e.relatedTarget && e.relatedTarget.dataset.action === 'create') {
        document.getElementById('classId').value = '';
        document.getElementById('className').value = '';
        document.getElementById('classTeacher').value = '';
        document.getElementById('classModalTitle').textContent = window.__CLASSES_I18N.modalAdd;
    }
});
</script>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

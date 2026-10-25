<?php
require_once dirname(__DIR__) . '/config/config.php';
requireTeacher();

$conn = getDB();
$error = '';

// Delete (schema: tp_id for child tables, quiz_answers.quiz_id)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && verify_csrf()) {
    $id = (int) $_POST['delete_id'];
    try {
        $conn->query("DELETE FROM quiz_answers WHERE quiz_id IN (SELECT id FROM tp_quizzes WHERE tp_id = $id)");
        $conn->query("DELETE FROM tp_materials WHERE tp_id = $id");
        $conn->query("DELETE FROM tp_checklists WHERE tp_id = $id");
        $conn->query("DELETE FROM tp_steps WHERE tp_id = $id");
        $conn->query("DELETE FROM tp_quizzes WHERE tp_id = $id");
        $conn->query("DELETE FROM tp_sessions WHERE id = $id");
        flash('success', t('tp_sessions.flash_deleted'));
        redirect(APP_URL . '/pages/tp_sessions.php');
    } catch (Exception $e) {
        $error = t('tp_sessions.err_delete');
    }
}

$search = trim($_GET['search'] ?? '');
$classFilter = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
$sort = $_GET['sort'] ?? 'created';
$order = strtoupper($_GET['order'] ?? 'DESC');
if (!in_array($order, ['ASC', 'DESC'])) $order = 'DESC';

$sql = 'SELECT s.id, s.title, s.objectives, s.skills, s.duration, s.created_at, s.class_id, c.name AS class_name
        FROM tp_sessions s
        LEFT JOIN classes c ON c.id = s.class_id
        WHERE 1=1';
$params = [];
$types = '';
if ($search !== '') {
    $sql .= ' AND (s.title LIKE ? OR s.objectives LIKE ? OR s.skills LIKE ?)';
    $p = "%$search%";
    $params = [$p, $p, $p];
    $types = 'sss';
}
if ($classFilter > 0) {
    $sql .= ' AND s.class_id = ?';
    $params[] = $classFilter;
    $types .= 'i';
}
$sortCol = ['created' => 's.created_at', 'title' => 's.title', 'duration' => 's.duration'][$sort] ?? 's.created_at';
$sql .= " ORDER BY $sortCol $order";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$classesList = $conn->query('SELECT id, name FROM classes ORDER BY name')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'TP Sessions - ' . APP_NAME;
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="page-title mb-0"><?= escape(t('tp_sessions.title')) ?></h1>
        <a href="<?= APP_URL ?>/pages/tp_edit.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> <?= escape(t('tp_sessions.new')) ?></a>
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
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="<?= escape(t('tp_sessions.search_ph')) ?>" value="<?= escape($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="class_id" class="form-select">
                        <option value=""><?= escape(t('tp_sessions.all_classes')) ?></option>
                        <?php foreach ($classesList as $cl): ?>
                            <option value="<?= (int)$cl['id'] ?>" <?= $classFilter === (int)$cl['id'] ? 'selected' : '' ?>><?= escape($cl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="sort" class="form-select">
                        <option value="created" <?= $sort === 'created' ? 'selected' : '' ?>><?= escape(t('tp_sessions.sort_created')) ?></option>
                        <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>><?= escape(t('tp_sessions.sort_title')) ?></option>
                        <option value="duration" <?= $sort === 'duration' ? 'selected' : '' ?>><?= escape(t('tp_sessions.sort_duration')) ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="order" class="form-select">
                        <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>><?= escape(t('tp_sessions.order_desc')) ?></option>
                        <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>><?= escape(t('tp_sessions.order_asc')) ?></option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100"><?= escape(t('tp_sessions.filter')) ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <i class="bi bi-journal-text"></i>
                    <p class="mb-0"><?= escape(t('tp_sessions.empty')) ?></p>
                    <a href="<?= APP_URL ?>/pages/tp_edit.php" class="btn btn-primary btn-sm mt-2"><?= escape(t('tp_sessions.new')) ?></a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?= escape(t('tp_sessions.col_title')) ?></th>
                                <th><?= escape(t('tp_sessions.col_class')) ?></th>
                                <th><?= escape(t('tp_sessions.col_duration')) ?></th>
                                <th><?= escape(t('tp_sessions.col_created')) ?></th>
                                <th width="200"><?= escape(t('tp_sessions.col_actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $s): ?>
                                <tr>
                                    <td><strong><?= escape($s['title']) ?></strong></td>
                                    <td><?= escape($s['class_name'] ?? '-') ?></td>
                                    <td><?= (int)($s['duration'] ?? 0) ?> min</td>
                                    <td><?= !empty($s['created_at']) ? escape(date('d/m/Y', strtotime($s['created_at']))) : '—' ?></td>
                                    <td>
                                        <a href="<?= APP_URL ?>/pages/tp_view.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-primary"><?= escape(t('tp_sessions.view')) ?></a>
                                        <a href="<?= APP_URL ?>/pages/tp_edit.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-secondary"><?= escape(t('tp_sessions.edit')) ?></a>
                                        <form method="post" class="d-inline" data-confirm="<?= escape(t('tp_sessions.confirm_delete')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="delete_id" value="<?= (int)$s['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><?= escape(t('tp_sessions.delete')) ?></button>
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
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

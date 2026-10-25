<?php
require_once dirname(__DIR__) . '/config/config.php';

requireLogin();
if (is_staff()) {
    redirect(APP_URL . '/pages/dashboard.php');
}

$conn = getDB();
$uid = (int) ($_SESSION['user_id'] ?? 0);
$userEmail = trim((string) ($_SESSION['user_email'] ?? ''));
$studentRow = member_fetch_student_row($conn, $uid, $userEmail !== '' ? $userEmail : null);
$sessions = [];

if ($studentRow && !empty($studentRow['class_id'])) {
    $cid = (int) $studentRow['class_id'];
    $q = $conn->query("SELECT id, title, duration, created_at FROM tp_sessions WHERE class_id = $cid ORDER BY id DESC LIMIT 25");
    if ($q) {
        $sessions = $q->fetch_all(MYSQLI_ASSOC);
    }
}

$pageTitle = t('member.title') . ' - ' . APP_NAME;
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<main class="container py-4">
    <h1 class="page-title mb-3"><?= escape(t('member.title')) ?></h1>
    <p class="text-muted mb-4"><?= escape(t('member.intro')) ?></p>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($err = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($err) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!students_table_ready($conn)): ?>
        <div class="alert alert-warning"><?= escape(t('register.err_schema')) ?></div>
    <?php elseif (!$studentRow): ?>
        <div class="alert alert-warning"><?= escape(t('member.profile_incomplete')) ?></div>
    <?php elseif (empty($studentRow['class_id'])): ?>
        <div class="alert alert-warning"><?= escape(t('member.no_group')) ?></div>
    <?php else: ?>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-people me-2"></i><?= escape(t('member.my_group')) ?></h5>
                <p class="mb-0 fs-5"><?= escape($studentRow['class_name'] ?? ('#' . (int) $studentRow['class_id'])) ?></p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-1"><i class="bi bi-clipboard-data me-2"></i><?= escape(t('member.quiz_results_link')) ?></h5>
                    <p class="text-muted small mb-0"><?= escape(t('member.quiz_results_hint')) ?></p>
                </div>
                <a href="<?= APP_URL ?>/pages/quiz_results.php" class="btn btn-primary"><?= escape(t('member.view_quiz_results')) ?></a>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><?= escape(t('member.sessions_for_group')) ?></h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($sessions)): ?>
                    <p class="text-muted p-4 mb-0"><?= escape(t('member.no_sessions')) ?></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th><?= escape(t('tp_sessions.col_title')) ?></th>
                                    <th><?= escape(t('tp_sessions.col_duration')) ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $s): ?>
                                    <tr>
                                        <td><?= escape($s['title']) ?></td>
                                        <td><?= (int) ($s['duration'] ?? 0) ?> min</td>
                                        <td class="text-nowrap">
                                            <a class="btn btn-sm btn-outline-primary" href="<?= APP_URL ?>/pages/tp_view.php?id=<?= (int) $s['id'] ?>"><?= escape(t('member.open_tp')) ?></a>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= APP_URL ?>/pages/quiz_results.php?tp_id=<?= (int) $s['id'] ?>"><?= escape(t('member.quiz_for_tp')) ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

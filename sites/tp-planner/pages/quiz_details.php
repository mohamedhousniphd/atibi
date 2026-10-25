<?php
require_once dirname(__DIR__) . '/config/config.php';
requireTeacher();

$conn = getDB();
$teacherId = currentTeacherId();
$isAdmin = isAdmin();

$tpId = isset($_GET['tp_id']) ? (int)$_GET['tp_id'] : 0;
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
if ($tpId <= 0 || $studentId <= 0) {
    flash('error', 'Invalid request.');
    redirect(APP_URL . '/pages/tp_sessions.php');
}

// Restrict access to teacher's classes
if (!$isAdmin) {
    $chk = $conn->query("SELECT s.id FROM tp_sessions s JOIN classes c ON c.id = s.class_id WHERE s.id = $tpId AND c.teacher_id = $teacherId LIMIT 1");
    if (!$chk || $chk->num_rows === 0) {
        flash('error', 'Access denied.');
        redirect(APP_URL . '/pages/tp_sessions.php');
    }
}

// Ensure tables exist
$r = $conn->query("SHOW TABLES LIKE 'quiz_question_scores'");
$r2 = $conn->query("SHOW TABLES LIKE 'quiz_attempts'");
if (!$r || !$r->num_rows || !$r2 || !$r2->num_rows) {
    flash('error', 'Quiz detailed scores not available. Run database/new_tables.sql.');
    redirect(APP_URL . '/pages/tp_scores.php?tp_id=' . $tpId);
}

// Load student
$student = null;
try {
    $stmt = $conn->prepare('SELECT id, name, email FROM students WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) { }

// Load TP
$tp = $conn->query("SELECT s.title, c.name AS class_name FROM tp_sessions s LEFT JOIN classes c ON c.id = s.class_id WHERE s.id = $tpId")->fetch_assoc();

// Attempt summary
$attempt = null;
$stmt = $conn->prepare('SELECT total_points, max_points, percentage, submitted_at FROM quiz_attempts WHERE student_id = ? AND tp_id = ? LIMIT 1');
$stmt->bind_param('ii', $studentId, $tpId);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();

// Details
$details = [];
$stmt = $conn->prepare('
    SELECT qqs.question_id, qqs.selected_option, qqs.is_correct, qqs.score,
           q.question, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option
    FROM quiz_question_scores qqs
    JOIN tp_quizzes q ON q.id = qqs.question_id
    WHERE qqs.student_id = ? AND qqs.tp_id = ?
    ORDER BY q.id
');
$stmt->bind_param('ii', $studentId, $tpId);
$stmt->execute();
$details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Quiz details - ' . APP_NAME;
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/tp_sessions.php">TP Sessions</a></li>
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/tp_view.php?id=<?= (int)$tpId ?>"><?= escape($tp['title'] ?? 'TP') ?></a></li>
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/tp_scores.php?tp_id=<?= (int)$tpId ?>">Scores</a></li>
                    <li class="breadcrumb-item active">Quiz details</li>
                </ol>
            </nav>
            <h1 class="page-title mb-1">Quiz details</h1>
            <div class="text-muted small">
                Étudiant: <strong><?= escape($student['name'] ?? '—') ?></strong>
                <?php if (!empty($student['email'])): ?> (<?= escape($student['email']) ?>)<?php endif; ?>
                · TP: <strong><?= escape($tp['title'] ?? '') ?></strong>
                · Classe: <strong><?= escape($tp['class_name'] ?? '—') ?></strong>
            </div>
        </div>
        <?php if ($attempt): ?>
            <div class="text-end">
                <div class="fw-semibold"><?= (int)$attempt['total_points'] ?> / <?= (int)$attempt['max_points'] ?></div>
                <div class="text-muted small"><?= escape((float)$attempt['percentage']) ?>%</div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Per-question scoring</h5>
            <a class="btn btn-sm btn-outline-secondary" href="<?= APP_URL ?>/pages/tp_scores.php?tp_id=<?= (int)$tpId ?>">Back</a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($details)): ?>
                <div class="empty-state">
                    <i class="bi bi-question-circle"></i>
                    <p class="mb-0">No quiz submission yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Question</th>
                                <th width="120">Selected</th>
                                <th width="120">Correct</th>
                                <th width="90">Points</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($details as $d): ?>
                            <tr>
                                <td><?= escape($d['question'] ?? '') ?></td>
                                <td><?= escape($d['selected_option'] ?: '—') ?></td>
                                <td><?= escape($d['correct_option'] ?? '') ?></td>
                                <td>
                                    <span class="badge <?= !empty($d['is_correct']) ? 'bg-success' : 'bg-danger' ?>">
                                        <?= (int)($d['score'] ?? 0) ?>
                                    </span>
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


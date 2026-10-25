<?php
require_once dirname(__DIR__) . '/config/config.php';
requireStudent();

$conn = getDB();
$studentId = currentStudentId();
$classId = (int)($_SESSION['class_id'] ?? 0);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    flash('error', 'Invalid session.');
    redirect(APP_URL . '/pages/student_dashboard.php');
}

// Load TP session, restricted to student's class
$stmt = $conn->prepare('SELECT s.*, c.name AS class_name
                        FROM tp_sessions s
                        LEFT JOIN classes c ON c.id = s.class_id
                        WHERE s.id = ? AND s.class_id = ? LIMIT 1');
$stmt->bind_param('ii', $id, $classId);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
if (!$session) {
    flash('error', 'Accès refusé.');
    redirect(APP_URL . '/pages/student_dashboard.php');
}

$steps = $conn->query("SELECT * FROM tp_steps WHERE tp_id = $id ORDER BY step_number")->fetch_all(MYSQLI_ASSOC);
$materials = $conn->query("SELECT * FROM tp_materials WHERE tp_id = $id ORDER BY type, name")->fetch_all(MYSQLI_ASSOC);
$quizzes = $conn->query("SELECT * FROM tp_quizzes WHERE tp_id = $id ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Load detailed quiz scores (new tables) if available
$quizDetails = [];
$attemptSummary = null;
try {
    $r = $conn->query("SHOW TABLES LIKE 'quiz_question_scores'");
    $r2 = $conn->query("SHOW TABLES LIKE 'quiz_attempts'");
    if ($r && $r->num_rows && $r2 && $r2->num_rows) {
        $stmt = $conn->prepare('
            SELECT qqs.question_id, qqs.selected_option, qqs.is_correct, qqs.score,
                   q.question, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option
            FROM quiz_question_scores qqs
            JOIN tp_quizzes q ON q.id = qqs.question_id
            WHERE qqs.student_id = ? AND qqs.tp_id = ?
            ORDER BY q.id
        ');
        $stmt->bind_param('ii', $studentId, $id);
        $stmt->execute();
        $quizDetails = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $conn->prepare('SELECT total_points, max_points, percentage, submitted_at FROM quiz_attempts WHERE student_id = ? AND tp_id = ? LIMIT 1');
        $stmt->bind_param('ii', $studentId, $id);
        $stmt->execute();
        $attemptSummary = $stmt->get_result()->fetch_assoc();
    }
} catch (Exception $e) { }

// Progress update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'Invalid request.');
        redirect(APP_URL . '/pages/student_tp.php?id=' . $id);
    }

    // Update status
    if (isset($_POST['set_status'])) {
        $status = $_POST['status'] ?? 'not_started';
        if (!in_array($status, ['not_started', 'in_progress', 'done'], true)) $status = 'not_started';
        try {
            $stmt = $conn->prepare('INSERT INTO student_tp_progress (student_id, tp_id, status) VALUES (?,?,?)
                                    ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = CURRENT_TIMESTAMP');
            $stmt->bind_param('iis', $studentId, $id, $status);
            $stmt->execute();
            flash('success', 'Statut mis à jour.');
        } catch (Exception $e) {
            flash('error', 'Impossible de mettre à jour le statut.');
        }
        redirect(APP_URL . '/pages/student_tp.php?id=' . $id);
    }

    // Submit quiz answers (one attempt per TP for this student: overwrite by deleting old rows)
    if (isset($_POST['submit_quiz'])) {
        $studentName = (string)($_SESSION['username'] ?? '');
        if ($studentName === '') {
            flash('error', 'Profil étudiant invalide.');
            redirect(APP_URL . '/pages/student_tp.php?id=' . $id . '#quiz');
        }
        if (empty($quizzes)) {
            flash('error', 'Aucune question.');
            redirect(APP_URL . '/pages/student_tp.php?id=' . $id . '#quiz');
        }

        try {
            $conn->begin_transaction();

            // Keep legacy table updated for existing analytics (1/0 scoring)
            $conn->query("DELETE qa FROM quiz_answers qa
                          JOIN tp_quizzes tq ON tq.id = qa.quiz_id
                          WHERE tq.tp_id = $id AND qa.student_name = '" . $conn->real_escape_string($studentName) . "'");

            $legacyInsert = $conn->prepare('INSERT INTO quiz_answers (quiz_id, student_name, selected_option, score) VALUES (?, ?, ?, ?)');

            // New structured scoring (4 points per question)
            $qqsExists = $conn->query("SHOW TABLES LIKE 'quiz_question_scores'");
            $attemptExists = $conn->query("SHOW TABLES LIKE 'quiz_attempts'");
            $useNew = ($qqsExists && $qqsExists->num_rows && $attemptExists && $attemptExists->num_rows);

            if ($useNew) {
                $stmtDel = $conn->prepare('DELETE FROM quiz_question_scores WHERE student_id = ? AND tp_id = ?');
                $stmtDel->bind_param('ii', $studentId, $id);
                $stmtDel->execute();

                $newInsert = $conn->prepare('INSERT INTO quiz_question_scores (student_id, tp_id, question_id, selected_option, is_correct, score) VALUES (?,?,?,?,?,?)');
            }

            $totalPoints = 0;
            $maxPoints = count($quizzes) * 4;

            foreach ($quizzes as $q) {
                $qid = (int)$q['id'];
                $answer = strtoupper(trim($_POST['answer_' . $qid] ?? ''));
                if (!in_array($answer, ['A', 'B', 'C', 'D'], true)) $answer = '';
                $correct = strtoupper(trim($q['correct_option'] ?? 'A'));
                $isCorrect = ($answer !== '' && $answer === $correct) ? 1 : 0;

                // Legacy row (score 1/0)
                $legacyScore = $isCorrect ? 1 : 0;
                $legacyInsert->bind_param('issi', $qid, $studentName, $answer, $legacyScore);
                $legacyInsert->execute();

                if ($useNew) {
                    $points = $isCorrect ? 4 : 0;
                    $totalPoints += $points;
                    $newInsert->bind_param('iiisii', $studentId, $id, $qid, $answer, $isCorrect, $points);
                    $newInsert->execute();
                }
            }

            if ($useNew) {
                $pct = $maxPoints > 0 ? round(100 * $totalPoints / $maxPoints, 2) : 0.0;
                $stmt = $conn->prepare('INSERT INTO quiz_attempts (student_id, tp_id, total_points, max_points, percentage) VALUES (?,?,?,?,?)
                                        ON DUPLICATE KEY UPDATE total_points=VALUES(total_points), max_points=VALUES(max_points), percentage=VALUES(percentage), submitted_at=CURRENT_TIMESTAMP');
                $stmt->bind_param('iiiid', $studentId, $id, $totalPoints, $maxPoints, $pct);
                $stmt->execute();
            }

            $conn->commit();
            flash('success', 'Quiz enregistré.');
        } catch (Exception $e) {
            $conn->rollback();
            flash('error', 'Enregistrement du quiz impossible.');
        }
        redirect(APP_URL . '/pages/student_tp.php?id=' . $id . '#quiz');
    }
}

// Load current status
$status = 'not_started';
try {
    $stmt = $conn->prepare('SELECT status FROM student_tp_progress WHERE student_id = ? AND tp_id = ? LIMIT 1');
    $stmt->bind_param('ii', $studentId, $id);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) $status = $row['status'] ?: 'not_started';
} catch (Exception $e) { }

// Compute quiz score (prefer new attempt summary)
$quizScore = null;
if ($attemptSummary && isset($attemptSummary['percentage'])) {
    $quizScore = (int) round((float)$attemptSummary['percentage']);
} else {
    try {
        $studentName = (string)($_SESSION['username'] ?? '');
        $r = $conn->query("SELECT SUM(qa.score) AS correct, COUNT(*) AS total
                           FROM quiz_answers qa
                           JOIN tp_quizzes tq ON tq.id = qa.quiz_id
                           WHERE tq.tp_id = $id AND qa.student_name = '" . $conn->real_escape_string($studentName) . "'");
        if ($r && $row = $r->fetch_assoc()) {
            $total = (int)$row['total'];
            $correct = (int)$row['correct'];
            if ($total > 0) $quizScore = (int) round(100 * $correct / $total);
        }
    } catch (Exception $e) { }
}

$pageTitle = escape($session['title']) . ' - ' . APP_NAME;
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/student_dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active"><?= escape($session['title']) ?></li>
                </ol>
            </nav>
            <h1 class="page-title mb-1"><?= escape($session['title']) ?></h1>
            <p class="text-muted small mb-0">
                <?= escape($session['class_name'] ?? '—') ?>
                <?php
                $dur = 60;
                if (isset($session['duration']) && $session['duration'] !== null) $dur = (int)$session['duration'];
                ?>
                · <?= $dur ?> min
                <?php if ($quizScore !== null): ?>
                    · Quiz: <strong><?= (int)$quizScore ?>%</strong>
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <form method="post" class="d-flex gap-2 align-items-center">
                <?= csrf_field() ?>
                <input type="hidden" name="set_status" value="1">
                <select name="status" class="form-select form-select-sm">
                    <option value="not_started" <?= $status === 'not_started' ? 'selected' : '' ?>>Non commencé</option>
                    <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>En cours</option>
                    <option value="done" <?= $status === 'done' ? 'selected' : '' ?>>Terminé</option>
                </select>
                <button class="btn btn-sm btn-primary" type="submit">OK</button>
            </form>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-4" id="tpTabs" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#overview">Overview</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#quiz">Mini-Quiz</a></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="overview">
            <div class="row">
                <div class="col-lg-8">
                    <?php if (!empty($session['objectives'])): ?>
                        <div class="card mb-3">
                            <div class="card-header bg-white"><strong>Objectives</strong></div>
                            <div class="card-body"><?= nl2br(escape($session['objectives'])) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($session['skills'])): ?>
                        <div class="card mb-3">
                            <div class="card-header bg-white"><strong>Skills</strong></div>
                            <div class="card-body"><?= nl2br(escape($session['skills'])) ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="card mb-3">
                        <div class="card-header bg-white"><strong>Steps</strong></div>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($steps as $i => $st): ?>
                                <li class="list-group-item">
                                    <strong>Étape <?= $i + 1 ?>:</strong> <?= escape($st['description'] ?? '') ?>
                                </li>
                            <?php endforeach; ?>
                            <?php if (empty($steps)): ?>
                                <li class="list-group-item text-muted">No steps defined.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-white"><strong>Materials</strong></div>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($materials as $m): ?>
                                <li class="list-group-item">
                                    <?= escape($m['name']) ?> <span class="badge bg-secondary"><?= escape($m['type']) ?></span>
                                </li>
                            <?php endforeach; ?>
                            <?php if (empty($materials)): ?>
                                <li class="list-group-item text-muted">No materials.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="quiz">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Mini-Quiz</strong>
                    <?php if ($quizScore !== null): ?>
                        <span class="badge <?= $quizScore >= 70 ? 'bg-success' : ($quizScore >= 50 ? 'bg-warning text-dark' : 'bg-danger') ?>"><?= (int)$quizScore ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!empty($attemptSummary)): ?>
                        <div class="alert alert-info">
                            Score: <strong><?= (int)$attemptSummary['total_points'] ?></strong> / <strong><?= (int)$attemptSummary['max_points'] ?></strong>
                            · <?= escape((float)$attemptSummary['percentage']) ?>%
                        </div>
                    <?php endif; ?>
                    <?php if (empty($quizzes)): ?>
                        <p class="text-muted mb-0">Aucune question.</p>
                    <?php else: ?>
                        <?php if (!empty($quizDetails)): ?>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Question</th>
                                            <th>Votre réponse</th>
                                            <th>Correcte</th>
                                            <th width="90">Points</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($quizDetails as $qd): ?>
                                        <tr>
                                            <td><?= escape($qd['question'] ?? '') ?></td>
                                            <td><?= escape($qd['selected_option'] ?? '—') ?></td>
                                            <td><?= escape($qd['correct_option'] ?? '') ?></td>
                                            <td>
                                                <span class="badge <?= !empty($qd['is_correct']) ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= (int)($qd['score'] ?? 0) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        <form method="post" action="" id="quizForm">
                            <?= csrf_field() ?>
                            <input type="hidden" name="submit_quiz" value="1">
                            <?php foreach ($quizzes as $q): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><?= escape($q['question'] ?? '') ?></label>
                                    <div class="d-flex gap-3 flex-wrap">
                                        <label><input required type="radio" name="answer_<?= (int)$q['id'] ?>" value="A"> <?= escape($q['option_a']) ?></label>
                                        <label><input required type="radio" name="answer_<?= (int)$q['id'] ?>" value="B"> <?= escape($q['option_b']) ?></label>
                                        <label><input required type="radio" name="answer_<?= (int)$q['id'] ?>" value="C"> <?= escape($q['option_c']) ?></label>
                                        <label><input required type="radio" name="answer_<?= (int)$q['id'] ?>" value="D"> <?= escape($q['option_d']) ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary">Envoyer</button>
                            <div class="text-muted small mt-2">Une nouvelle soumission remplacera la précédente pour ce TP.</div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.getElementById('quizForm')?.addEventListener('submit', function(e) {
    var ok = true;
    this.querySelectorAll('[name^="answer_"]').forEach(function(input) {
        // group validation is handled by required on radios, but we highlight if needed
    });
    if (!ok) e.preventDefault();
});
</script>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>


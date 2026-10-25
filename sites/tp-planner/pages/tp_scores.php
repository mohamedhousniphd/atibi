<?php
require_once dirname(__DIR__) . '/config/config.php';
requireTeacher();

$conn = getDB();
$teacherId = currentTeacherId();
$isAdmin = isAdmin();

$tpId = isset($_GET['tp_id']) ? (int)$_GET['tp_id'] : 0;
if ($tpId <= 0) {
    flash('error', 'Invalid session.');
    redirect(APP_URL . '/pages/tp_sessions.php');
}

// Load TP + class, restricted
if ($isAdmin) {
    $tp = $conn->query("SELECT s.*, c.name AS class_name FROM tp_sessions s LEFT JOIN classes c ON c.id = s.class_id WHERE s.id = $tpId")->fetch_assoc();
} else {
    $tp = $conn->query("SELECT s.*, c.name AS class_name FROM tp_sessions s JOIN classes c ON c.id = s.class_id WHERE s.id = $tpId AND c.teacher_id = $teacherId")->fetch_assoc();
}
if (!$tp) {
    flash('error', 'Access denied.');
    redirect(APP_URL . '/pages/tp_sessions.php');
}

$classId = (int)($tp['class_id'] ?? 0);

// Save scores
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_scores']) && verify_csrf()) {
    try {
        $stmt = $conn->prepare('INSERT INTO student_tp_scores (student_id, tp_id, score) VALUES (?,?,?)
                                ON DUPLICATE KEY UPDATE score = VALUES(score), updated_at = CURRENT_TIMESTAMP');
        foreach (($_POST['score'] ?? []) as $studentIdStr => $scoreStr) {
            $sid = (int)$studentIdStr;
            $score = trim((string)$scoreStr);
            if ($score === '') continue;
            $val = (float)str_replace(',', '.', $score);
            if ($val < 0 || $val > 100) continue;

            if (!$isAdmin) {
                $chk = $conn->prepare('SELECT s.id FROM students s JOIN classes c ON c.id = s.class_id WHERE s.id = ? AND s.class_id = ? AND c.teacher_id = ? LIMIT 1');
                $chk->bind_param('iii', $sid, $classId, $teacherId);
                $chk->execute();
                if ($chk->get_result()->num_rows === 0) continue;
            }

            $stmt->bind_param('iid', $sid, $tpId, $val);
            $stmt->execute();
        }
        flash('success', 'Scores enregistrés.');
        redirect(APP_URL . '/pages/tp_scores.php?tp_id=' . $tpId);
    } catch (Exception $e) {
        $error = 'Enregistrement impossible.';
    }
}

// Students in class
$students = [];
try {
    $stmt = $conn->prepare('SELECT id, name, email FROM students WHERE class_id = ? ORDER BY name');
    $stmt->bind_param('i', $classId);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) { $students = []; }

// Existing TP scores
$scores = [];
try {
    $stmt = $conn->prepare('SELECT student_id, score FROM student_tp_scores WHERE tp_id = ?');
    $stmt->bind_param('i', $tpId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $scores[(int)$r['student_id']] = (float)$r['score'];
} catch (Exception $e) { }

// Quiz % per student for this TP (prefer quiz_attempts; fallback to quiz_answers by name)
$quizPctByStudentId = [];
$quizPctByName = [];
try {
    $r = $conn->query("SHOW TABLES LIKE 'quiz_attempts'");
    if ($r && $r->num_rows) {
        $stmt = $conn->prepare('SELECT student_id, percentage FROM quiz_attempts WHERE tp_id = ?');
        $stmt->bind_param('i', $tpId);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $quizPctByStudentId[(int)$row['student_id']] = (int)round((float)$row['percentage']);
        }
    } else {
        $res = $conn->query("
            SELECT qa.student_name, SUM(qa.score) AS correct, COUNT(*) AS total
            FROM quiz_answers qa
            JOIN tp_quizzes tq ON tq.id = qa.quiz_id
            WHERE tq.tp_id = $tpId
            GROUP BY qa.student_name
        ");
        while ($res && ($row = $res->fetch_assoc())) {
            $total = (int)$row['total'];
            $correct = (int)$row['correct'];
            $quizPctByName[$row['student_name']] = $total > 0 ? (int)round(100 * $correct / $total) : 0;
        }
    }
} catch (Exception $e) { }

// Class average (TP scores only)
$classAvg = null;
if (!empty($scores)) {
    $classAvg = round(array_sum($scores) / count($scores), 1);
}

$pageTitle = 'Scores - ' . APP_NAME;
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/tp_sessions.php">TP Sessions</a></li>
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/tp_view.php?id=<?= (int)$tpId ?>"><?= escape($tp['title'] ?? '') ?></a></li>
                    <li class="breadcrumb-item active">Scores</li>
                </ol>
            </nav>
            <h1 class="page-title mb-1">Scores</h1>
            <div class="text-muted small">
                TP: <strong><?= escape($tp['title'] ?? '') ?></strong>
                · Classe: <strong><?= escape($tp['class_name'] ?? '—') ?></strong>
                <?php if ($classAvg !== null): ?>
                    · Moyenne classe: <strong><?= escape($classAvg) ?>%</strong>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= escape($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Notes par étudiant</h5>
            <a class="btn btn-sm btn-outline-secondary" href="<?= APP_URL ?>/pages/students.php">Gérer étudiants</a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <i class="bi bi-mortarboard"></i>
                    <p class="mb-0">Aucun étudiant dans cette classe.</p>
                </div>
            <?php else: ?>
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="save_scores" value="1">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th width="140">Score TP (0-100)</th>
                                    <th width="160">Quiz</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $st): ?>
                                    <?php
                                    $sid = (int)$st['id'];
                                    $val = isset($scores[$sid]) ? $scores[$sid] : '';
                                    $q = $quizPctByStudentId[$sid] ?? ($quizPctByName[$st['name']] ?? null);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= escape($st['name']) ?></div>
                                            <div class="text-muted small"><?= escape($st['email']) ?></div>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" max="100"
                                                   name="score[<?= $sid ?>]"
                                                   class="form-control form-control-sm"
                                                   value="<?= escape($val) ?>"
                                                   placeholder="—">
                                        </td>
                                        <td>
                                            <?php if ($q === null): ?>
                                                <span class="text-muted">—</span>
                                            <?php else: ?>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge <?= $q >= 70 ? 'bg-success' : ($q >= 50 ? 'bg-warning text-dark' : 'bg-danger') ?>"><?= (int)$q ?>%</span>
                                                    <a class="btn btn-sm btn-outline-secondary" href="<?= APP_URL ?>/pages/quiz_details.php?tp_id=<?= (int)$tpId ?>&student_id=<?= (int)$sid ?>">Détails</a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="<?= APP_URL ?>/pages/tp_view.php?id=<?= (int)$tpId ?>">Retour</a>
                        <button class="btn btn-primary" type="submit">Save</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>


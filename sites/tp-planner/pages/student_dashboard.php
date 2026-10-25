<?php
require_once dirname(__DIR__) . '/config/config.php';
requireStudent();

$conn = getDB();
$studentId = currentStudentId();

// Load student + class
$student = null;
$class = null;
try {
    $stmt = $conn->prepare('SELECT s.id, s.name, s.email, s.class_id, c.name AS class_name
                            FROM students s
                            LEFT JOIN classes c ON c.id = s.class_id
                            WHERE s.id = ? LIMIT 1');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    if ($student) {
        $_SESSION['class_id'] = (int) ($student['class_id'] ?? 0);
    }
} catch (Exception $e) { }

$classId = (int) ($_SESSION['class_id'] ?? 0);
if ($classId <= 0) {
    $pageTitle = 'Dashboard - ' . APP_NAME;
    require_once dirname(__DIR__) . '/includes/header.php';
    require_once dirname(__DIR__) . '/includes/navbar.php';
    ?>
    <main class="container py-4">
        <h1 class="page-title mb-3">Dashboard Étudiant</h1>
        <div class="alert alert-warning">
            Votre compte n’est pas encore rattaché à une classe. Merci de contacter votre enseignant.
        </div>
    </main>
    <?php
    require_once dirname(__DIR__) . '/includes/footer.php';
    exit;
}

// Sessions for this class
$sessions = [];
$stmt = $conn->prepare('SELECT id, title, objectives, skills, duration, created_at
                        FROM tp_sessions
                        WHERE class_id = ?
                        ORDER BY created_at DESC, id DESC');
$stmt->bind_param('i', $classId);
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Progress statuses
$statusByTp = [];
try {
    $r = $conn->prepare('SELECT tp_id, status FROM student_tp_progress WHERE student_id = ?');
    $r->bind_param('i', $studentId);
    $r->execute();
    $rows = $r->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $row) $statusByTp[(int)$row['tp_id']] = $row['status'];
} catch (Exception $e) { }

// Quiz score per TP (percentage) - prefer new attempt table if available
$quizPctByTp = [];
try {
    $r = $conn->query("SHOW TABLES LIKE 'quiz_attempts'");
    if ($r && $r->num_rows) {
        $stmt = $conn->prepare('
            SELECT qa.tp_id, qa.percentage
            FROM quiz_attempts qa
            JOIN tp_sessions ts ON ts.id = qa.tp_id
            WHERE qa.student_id = ? AND ts.class_id = ?
        ');
        $stmt->bind_param('ii', $studentId, $classId);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $quizPctByTp[(int)$row['tp_id']] = (int) round((float)$row['percentage']);
        }
    } else {
        $stmt = $conn->prepare("
            SELECT tq.tp_id,
                   SUM(CASE WHEN qa.score IS NULL THEN 0 ELSE qa.score END) AS correct,
                   COUNT(*) AS total
            FROM quiz_answers qa
            JOIN tp_quizzes tq ON tq.id = qa.quiz_id
            WHERE qa.student_name = ?
              AND tq.tp_id IN (SELECT id FROM tp_sessions WHERE class_id = ?)
            GROUP BY tq.tp_id
        ");
        $studentName = (string)($_SESSION['username'] ?? '');
        $stmt->bind_param('si', $studentName, $classId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as $row) {
            $tpId = (int)$row['tp_id'];
            $total = (int)$row['total'];
            $correct = (int)$row['correct'];
            $quizPctByTp[$tpId] = $total > 0 ? (int) round(100 * $correct / $total) : 0;
        }
    }
} catch (Exception $e) { }

// TP numeric score (0-100) if teacher recorded it
$tpScoreByTp = [];
try {
    $stmt = $conn->prepare('SELECT tp_id, score FROM student_tp_scores WHERE student_id = ?');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $row) $tpScoreByTp[(int)$row['tp_id']] = (float)$row['score'];
} catch (Exception $e) { }

// Student average (simple average across available tp scores, fallback to quiz pct if no tp score)
$avgValues = [];
foreach ($sessions as $s) {
    $tpId = (int)$s['id'];
    if (isset($tpScoreByTp[$tpId])) $avgValues[] = (float)$tpScoreByTp[$tpId];
    elseif (isset($quizPctByTp[$tpId])) $avgValues[] = (float)$quizPctByTp[$tpId];
}
$studentAvg = !empty($avgValues) ? round(array_sum($avgValues) / count($avgValues), 1) : null;

$pageTitle = 'Student Dashboard - ' . APP_NAME;
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="page-title mb-1">Dashboard Étudiant</h1>
            <div class="text-muted small">
                Classe: <strong><?= escape($student['class_name'] ?? '—') ?></strong>
                <?php if ($studentAvg !== null): ?>
                    · Moyenne: <strong><?= escape($studentAvg) ?>%</strong>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0">TP disponibles</h5>
            <span class="badge bg-secondary"><?= count($sessions) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <i class="bi bi-journal-text"></i>
                    <p class="mb-0">Aucun TP pour votre classe.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>TP</th>
                                <th>Status</th>
                                <th>Quiz</th>
                                <th>Score TP</th>
                                <th width="160"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($sessions as $s): ?>
                            <?php
                            $tpId = (int)$s['id'];
                            $status = $statusByTp[$tpId] ?? 'not_started';
                            $statusLabel = ['not_started' => 'Non commencé', 'in_progress' => 'En cours', 'done' => 'Terminé'][$status] ?? 'Non commencé';
                            $statusBadge = ['not_started' => 'bg-secondary', 'in_progress' => 'bg-primary', 'done' => 'bg-success'][$status] ?? 'bg-secondary';
                            $quizPct = $quizPctByTp[$tpId] ?? null;
                            $tpScore = $tpScoreByTp[$tpId] ?? null;
                            ?>
                            <tr>
                                <td><strong><?= escape($s['title']) ?></strong></td>
                                <td><span class="badge <?= $statusBadge ?>"><?= escape($statusLabel) ?></span></td>
                                <td>
                                    <?php if ($quizPct === null): ?>
                                        <span class="text-muted">—</span>
                                    <?php else: ?>
                                        <span class="badge <?= $quizPct >= 70 ? 'bg-success' : ($quizPct >= 50 ? 'bg-warning text-dark' : 'bg-danger') ?>"><?= (int)$quizPct ?>%</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tpScore === null): ?>
                                        <span class="text-muted">—</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark"><?= escape($tpScore) ?>%</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= APP_URL ?>/pages/student_tp.php?id=<?= $tpId ?>">Ouvrir</a>
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


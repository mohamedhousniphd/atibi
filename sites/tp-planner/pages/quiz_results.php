<?php
/**
 * Trainee dashboard: detailed quiz results (score, questions, correct answers).
 */
require_once dirname(__DIR__) . '/config/config.php';
requireLogin();
require_lab_member();

$conn = getDB();
$uid = (int) ($_SESSION['user_id'] ?? 0);
$label = trainee_quiz_label();
$studentRow = member_fetch_student_row($conn, $uid, null);

$sessions = [];
if ($studentRow && !empty($studentRow['class_id'])) {
    $cid = (int) $studentRow['class_id'];
    $q = $conn->query("SELECT id, title FROM tp_sessions WHERE class_id = $cid ORDER BY title");
    if ($q) {
        $sessions = $q->fetch_all(MYSQLI_ASSOC);
    }
}

$tpId = isset($_GET['tp_id']) ? (int) $_GET['tp_id'] : 0;
$detail = [];
$totalScore = 0;
$totalQuestions = 0;

if ($tpId > 0 && $label !== '' && user_has_tp_access($conn, $tpId)) {
    $quizzes = $conn->query("SELECT * FROM tp_quizzes WHERE tp_id = $tpId ORDER BY id")->fetch_all(MYSQLI_ASSOC);
    $st = $conn->prepare("
        SELECT qa.quiz_id, qa.selected_option, qa.score
        FROM quiz_answers qa
        JOIN tp_quizzes tq ON tq.id = qa.quiz_id
        WHERE tq.tp_id = ? AND LOWER(TRIM(qa.student_name)) = LOWER(?)
        ORDER BY qa.id DESC
    ");
    $st->bind_param('is', $tpId, $label);
    $st->execute();
    $answersRes = $st->get_result();
    $latestByQuiz = [];
    while ($row = $answersRes->fetch_assoc()) {
        $qid = (int) $row['quiz_id'];
        if (!isset($latestByQuiz[$qid])) {
            $latestByQuiz[$qid] = $row;
        }
    }
    foreach ($quizzes as $quiz) {
        $qid = (int) $quiz['id'];
        $ans = $latestByQuiz[$qid] ?? null;
        $correct = strtoupper(trim((string) ($quiz['correct_option'] ?? 'A')));
        $selected = $ans ? strtoupper(trim((string) $ans['selected_option'])) : '';
        $score = $ans ? (int) $ans['score'] : 0;
        $totalQuestions++;
        $totalScore += $score;
        $detail[] = [
            'question' => $quiz['question'] ?? '',
            'selected' => $selected,
            'correct' => $correct,
            'selected_text' => $selected !== '' ? quiz_option_label($quiz, $selected) : '—',
            'correct_text' => quiz_option_label($quiz, $correct),
            'is_correct' => $selected !== '' && $selected === $correct,
            'score' => $score,
        ];
    }
}

$pageTitle = t('quiz_results.title') . ' - ' . APP_NAME;
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<main class="container py-4">
    <h1 class="page-title mb-3"><?= escape(t('quiz_results.title')) ?></h1>
    <p class="text-muted mb-4"><?= escape(t('quiz_results.intro')) ?></p>

    <?php if (!students_table_ready($conn)): ?>
        <div class="alert alert-warning"><?= escape(t('register.err_schema')) ?></div>
    <?php elseif (!$studentRow || empty($studentRow['class_id'])): ?>
        <div class="alert alert-warning"><?= escape(t('member.no_group')) ?></div>
    <?php else: ?>
        <form method="get" class="row g-3 mb-4">
            <div class="col-md-8">
                <label class="form-label"><?= escape(t('quiz_results.select_tp')) ?></label>
                <select name="tp_id" class="form-select" onchange="this.form.submit()">
                    <option value=""><?= escape(t('quiz_results.choose_tp')) ?></option>
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= $tpId === (int) $s['id'] ? 'selected' : '' ?>><?= escape($s['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($tpId > 0): ?>
            <?php if (empty($detail)): ?>
                <p class="text-muted"><?= escape(t('quiz_results.no_attempt')) ?></p>
            <?php else: ?>
                <div class="card mb-4 border-primary">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <h5 class="mb-1"><?= escape(t('quiz_results.your_score')) ?></h5>
                            <p class="mb-0 fs-3 fw-bold"><?= (int) $totalScore ?> / <?= (int) $totalQuestions ?></p>
                        </div>
                        <?php
                        $pct = $totalQuestions > 0 ? round(100 * $totalScore / $totalQuestions) : 0;
                        ?>
                        <span class="badge fs-6 <?= $pct >= 70 ? 'bg-success' : ($pct >= 50 ? 'bg-warning text-dark' : 'bg-danger') ?>"><?= $pct ?>%</span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white"><strong><?= escape(t('quiz_results.detail_title')) ?></strong></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th><?= escape(t('quiz_results.col_question')) ?></th>
                                        <th><?= escape(t('quiz_results.col_your_answer')) ?></th>
                                        <th><?= escape(t('quiz_results.col_correct')) ?></th>
                                        <th><?= escape(t('quiz_results.col_result')) ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detail as $i => $d): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td><?= escape($d['question']) ?></td>
                                            <td>
                                                <?php if ($d['selected'] !== ''): ?>
                                                    <strong><?= escape($d['selected']) ?>)</strong> <?= escape($d['selected_text']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?= escape($d['correct']) ?>)</strong> <?= escape($d['correct_text']) ?></td>
                                            <td>
                                                <?php if ($d['selected'] === ''): ?>
                                                    <span class="badge bg-secondary"><?= escape(t('quiz_results.not_answered')) ?></span>
                                                <?php elseif ($d['is_correct']): ?>
                                                    <span class="badge bg-success"><?= escape(t('quiz_results.correct')) ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger"><?= escape(t('quiz_results.incorrect')) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

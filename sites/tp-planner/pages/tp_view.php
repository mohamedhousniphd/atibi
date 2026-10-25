<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/tp_sections.php';
requireLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$conn = getDB();

if (!$id) {
    flash('error', t('tp_view.err_invalid'));
    header('Location: ' . APP_URL . (is_staff() ? '/pages/tp_sessions.php' : '/pages/member_dashboard.php'));
    exit;
}

$session = $conn->query("SELECT s.*, c.name AS class_name FROM tp_sessions s LEFT JOIN classes c ON c.id = s.class_id WHERE s.id = $id")->fetch_assoc();
if (!$session) {
    flash('error', t('tp_view.err_not_found'));
    header('Location: ' . APP_URL . (is_staff() ? '/pages/tp_sessions.php' : '/pages/member_dashboard.php'));
    exit;
}

if (!user_has_tp_access($conn, $id)) {
    flash('error', t('tp_view.access_denied'));
    header('Location: ' . APP_URL . (is_staff() ? '/pages/tp_sessions.php' : '/pages/member_dashboard.php'));
    exit;
}

$materials = $conn->query("SELECT * FROM tp_materials WHERE tp_id = $id ORDER BY type, name")->fetch_all(MYSQLI_ASSOC);
$checklists = $conn->query("SELECT * FROM tp_checklists WHERE tp_id = $id ORDER BY phase, id")->fetch_all(MYSQLI_ASSOC);
$quizzes = $conn->query("SELECT * FROM tp_quizzes WHERE tp_id = $id ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Toggle checklist item
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['check_id']) && verify_csrf()) {
        $cid = (int) $_POST['check_id'];
        $done = (int) ($_POST['is_done'] ?? 0);
        $conn->query("UPDATE tp_checklists SET is_done = $done WHERE id = $cid AND tp_id = $id");
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        redirect(APP_URL . '/pages/tp_view.php?id=' . $id . '#checklist');
    }
    // Record quiz answers
    if (isset($_POST['record_quiz']) && verify_csrf()) {
        $studentName = trim($_POST['student_name'] ?? '');
        if (!is_staff()) {
            $studentName = trim((string) ($_SESSION['username'] ?? ''));
        }
        if ($studentName !== '') {
            $correctCol = 'correct_option'; // column name in tp_quizzes
            foreach ($quizzes as $q) {
                $qid = (int) $q['id'];
                $answer = strtoupper(trim($_POST['answer_' . $qid] ?? ''));
                $correct = strtoupper(trim($q['correct_option'] ?? 'A'));
                $score = ($answer === $correct) ? 1 : 0;
                $stmt = $conn->prepare('INSERT INTO quiz_answers (quiz_id, student_name, selected_option, score) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('issi', $qid, $studentName, $answer, $score);
                $stmt->execute();
            }
            flash('success', sprintf(t('tp_view.answers_recorded'), $studentName));
            redirect(APP_URL . '/pages/tp_view.php?id=' . $id . '#quiz');
        }
    }
}

// Quiz answers for this session (Azzeddine ATIBI / admin: all stagiaires; stagiaire: own rows only)
$answersByStudent = [];
$myQuizLabel = trainee_quiz_label();
$myQuizDetail = [];
try {
    if (is_staff()) {
        $res = $conn->query("SELECT qa.student_name, qa.selected_option, qa.score FROM quiz_answers qa JOIN tp_quizzes tq ON tq.id = qa.quiz_id WHERE tq.tp_id = $id ORDER BY qa.student_name");
    } else {
        $st = $conn->prepare("SELECT qa.student_name, qa.selected_option, qa.score FROM quiz_answers qa JOIN tp_quizzes tq ON tq.id = qa.quiz_id WHERE tq.tp_id = ? AND LOWER(TRIM(qa.student_name)) = LOWER(?) ORDER BY qa.id");
        $st->bind_param('is', $id, $myQuizLabel);
        $st->execute();
        $res = $st->get_result();
    }
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $sn = $row['student_name'];
            if (!isset($answersByStudent[$sn])) {
                $answersByStudent[$sn] = ['total' => 0, 'correct' => 0];
            }
            $answersByStudent[$sn]['total']++;
            $answersByStudent[$sn]['correct'] += (int) $row['score'];
        }
    }
} catch (Exception $e) {
    $answersByStudent = [];
}

if (!is_staff() && $myQuizLabel !== '' && !empty($quizzes)) {
    $st = $conn->prepare("
        SELECT qa.quiz_id, qa.selected_option, qa.score
        FROM quiz_answers qa
        JOIN tp_quizzes tq ON tq.id = qa.quiz_id
        WHERE tq.tp_id = ? AND LOWER(TRIM(qa.student_name)) = LOWER(?)
        ORDER BY qa.id DESC
    ");
    $st->bind_param('is', $id, $myQuizLabel);
    $st->execute();
    $latestByQuiz = [];
    $resDetail = $st->get_result();
    while ($row = $resDetail->fetch_assoc()) {
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
        $myQuizDetail[] = [
            'question' => $quiz['question'] ?? '',
            'selected' => $selected,
            'correct' => $correct,
            'selected_text' => $selected !== '' ? quiz_option_label($quiz, $selected) : '—',
            'correct_text' => quiz_option_label($quiz, $correct),
            'is_correct' => $selected !== '' && $selected === $correct,
        ];
    }
}

$pageTitle = escape($session['title']) . ' - ' . APP_NAME;
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<!-- MathJax — renders \( … \) and \[ … \] produits par l'importateur DOCX/PDF -->
<script>
window.MathJax = {
    tex: { inlineMath: [['\\(','\\)'], ['$','$']], displayMath: [['\\[','\\]'], ['$$','$$']] },
    options: { skipHtmlTags: ['script','noscript','style','textarea','pre'] }
};
</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" id="MathJax-script" async></script>

<main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= APP_URL . (is_staff() ? '/pages/tp_sessions.php' : '/pages/member_dashboard.php') ?>"><?= escape(is_staff() ? t('tp_view.breadcrumb') : t('member.breadcrumb_home')) ?></a></li>
                    <li class="breadcrumb-item active"><?= escape($session['title']) ?></li>
                </ol>
            </nav>
            <h1 class="page-title mb-1"><?= escape($session['title']) ?></h1>
            <p class="text-muted small mb-0">
                <?php if (!empty($session['fiche_number'])): ?>
                    <?= escape($session['fiche_number']) ?> ·
                <?php endif; ?>
                <?php if (!empty($session['unit'])): ?>
                    <?= escape($session['unit']) ?> ·
                <?php endif; ?>
                <?= escape($session['class_name'] ?? '—') ?>
                · <?= (int)($session['duration'] ?? 0) ?> min
            </p>
        </div>
        <div class="d-flex gap-2">
            <?php if (is_staff()): ?>
            <a href="<?= APP_URL ?>/pages/tp_edit.php?id=<?= $id ?>" class="btn btn-outline-primary"><?= escape(t('tp_view.edit')) ?></a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/pages/tp_pdf.php?id=<?= $id ?>" class="btn btn-danger" target="_blank"><i class="bi bi-file-pdf me-1"></i> <?= escape(t('tp_view.export_pdf')) ?></a>
        </div>
    </div>

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

    <ul class="nav nav-tabs mb-4" id="tpTabs" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#overview"><?= escape(t('tp_view.tab_overview')) ?></a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#quiz"><?= escape(t('tp_view.tab_quiz')) ?></a></li>
    </ul>

    <div class="tab-content tp-fiche">
        <div class="tab-pane fade show active" id="overview">
            <div class="tp-fiche__body">

                <?php if (!empty($session['safety'])): ?>
                    <?php tp_section_open('Consignes de sécurité', 'border-warning'); tp_section_body_open(); ?>
                    <?= tp_sanitize_rich_text($session['safety']) ?>
                    <?php tp_section_body_close(); tp_section_close(); ?>
                <?php endif; ?>

                <?php if (!empty($session['objectives'])): ?>
                    <?php tp_section_open(t('tp_view.objectives')); tp_section_body_open(); ?>
                    <?= tp_sanitize_rich_text($session['objectives']) ?>
                    <?php tp_section_body_close(); tp_section_close(); ?>
                <?php endif; ?>

                <?php if (!empty($session['skills'])): ?>
                    <?php tp_section_open(t('tp_view.skills')); tp_section_body_open(); ?>
                    <?= tp_sanitize_rich_text($session['skills']) ?>
                    <?php tp_section_body_close(); tp_section_close(); ?>
                <?php endif; ?>

                <?php if (!empty($session['imported_content'])): ?>
                    <?php tp_section_open('Mode opératoire — Étapes et résultats'); tp_section_body_open(); ?>
                    <?= tp_sanitize_rich_text($session['imported_content']) ?>
                    <?php tp_section_body_close(); tp_section_close(); ?>
                <?php endif; ?>

                <?php if (!empty($session['schema_image'])): ?>
                    <?php tp_section_open('Image expérimentale'); ?>
                    <div class="card-body text-center">
                        <img src="<?= escape(APP_URL . '/' . $session['schema_image']) ?>" alt="Schéma" class="img-fluid rounded border tp-schema-img">
                    </div>
                    <?php tp_section_close(); ?>
                <?php endif; ?>

                <?php tp_render_checklist_block($id, $checklists); ?>
                <?php tp_render_quiz_preview($quizzes); ?>

            </div>
        </div>
        <div class="tab-pane fade" id="quiz">
            <div class="row">
                <?php if (is_staff()): ?>
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header bg-white"><strong><?= escape(t('tp_view.record_answers')) ?></strong></div>
                        <div class="card-body">
                            <form method="post" action="">
                                <?= csrf_field() ?>
                                <input type="hidden" name="record_quiz" value="1">
                                <div class="mb-3">
                                    <label class="form-label"><?= escape(t('tp_view.trainee_name')) ?></label>
                                    <input type="text" name="student_name" class="form-control" required placeholder="<?= escape(t('tp_view.trainee_placeholder')) ?>">
                                </div>
                                <?php foreach ($quizzes as $q): ?>
                                    <div class="mb-3">
                                        <label class="form-label"><?= escape($q['question'] ?? '') ?></label>
                                        <div class="d-flex gap-3 flex-wrap">
                                            <label><input type="radio" name="answer_<?= (int)$q['id'] ?>" value="A"> <?= escape($q['option_a']) ?></label>
                                            <label><input type="radio" name="answer_<?= (int)$q['id'] ?>" value="B"> <?= escape($q['option_b']) ?></label>
                                            <label><input type="radio" name="answer_<?= (int)$q['id'] ?>" value="C"> <?= escape($q['option_c']) ?></label>
                                            <label><input type="radio" name="answer_<?= (int)$q['id'] ?>" value="D"> <?= escape($q['option_d']) ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($quizzes)): ?>
                                    <p class="text-muted"><?= escape(t('tp_view.no_quiz')) ?> <a href="<?= APP_URL ?>/pages/tp_edit.php?id=<?= $id ?>"><?= escape(t('tp_view.edit_to_add')) ?></a> <?= escape(t('tp_view.to_add_some')) ?></p>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-primary"><?= escape(t('tp_view.save_answers')) ?></button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header bg-white"><strong><?= escape(t('tp_view.my_quiz')) ?></strong></div>
                        <div class="card-body">
                            <form method="post" action="">
                                <?= csrf_field() ?>
                                <input type="hidden" name="record_quiz" value="1">
                                <input type="hidden" name="student_name" value="<?= escape($myQuizLabel) ?>">
                                <p class="text-muted small mb-3"><?= escape(t('tp_view.my_quiz_intro')) ?></p>
                                <?php foreach ($quizzes as $q): ?>
                                    <div class="mb-3">
                                        <label class="form-label"><?= escape($q['question'] ?? '') ?></label>
                                        <div class="d-flex gap-3 flex-wrap">
                                            <label><input type="radio" name="answer_<?= (int)$q['id'] ?>" value="A"> <?= escape($q['option_a']) ?></label>
                                            <label><input type="radio" name="answer_<?= (int)$q['id'] ?>" value="B"> <?= escape($q['option_b']) ?></label>
                                            <label><input type="radio" name="answer_<?= (int)$q['id'] ?>" value="C"> <?= escape($q['option_c']) ?></label>
                                            <label><input type="radio" name="answer_<?= (int)$q['id'] ?>" value="D"> <?= escape($q['option_d']) ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($quizzes)): ?>
                                    <p class="text-muted"><?= escape(t('tp_view.no_quiz')) ?></p>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-primary"><?= escape(t('tp_view.save_answers')) ?></button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-white"><strong><?= escape(is_staff() ? t('tp_view.performance') : t('tp_view.my_results')) ?></strong></div>
                        <div class="card-body p-0">
                            <?php if (is_staff()): ?>
                                <?php if (empty($answersByStudent)): ?>
                                    <p class="p-3 text-muted mb-0"><?= escape(t('tp_view.no_answers')) ?></p>
                                <?php else: ?>
                                    <table class="table table-sm mb-0">
                                        <thead><tr><th><?= escape(t('tp_view.col_name')) ?></th><th><?= escape(t('tp_view.col_score')) ?></th><th><?= escape(t('tp_view.col_pct')) ?></th></tr></thead>
                                        <tbody>
                                            <?php
                                            $totalQ = count($quizzes);
                                            foreach ($answersByStudent as $name => $data):
                                                $pct = $totalQ > 0 ? round(100 * $data['correct'] / $totalQ) : 0;
                                            ?>
                                                <tr>
                                                    <td><?= escape($name) ?></td>
                                                    <td><?= (int)$data['correct'] ?> / <?= (int)$data['total'] ?></td>
                                                    <td><span class="badge <?= $pct >= 70 ? 'bg-success' : ($pct >= 50 ? 'bg-warning text-dark' : 'bg-danger') ?>"><?= $pct ?>%</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            <?php elseif (empty($myQuizDetail)): ?>
                                <p class="p-3 text-muted mb-0"><?= escape(t('tp_view.no_answers')) ?></p>
                            <?php else: ?>
                                <?php
                                $myCorrect = 0;
                                foreach ($myQuizDetail as $d) {
                                    if ($d['is_correct']) {
                                        $myCorrect++;
                                    }
                                }
                                $myTotal = count($myQuizDetail);
                                $myPct = $myTotal > 0 ? round(100 * $myCorrect / $myTotal) : 0;
                                ?>
                                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold"><?= (int) $myCorrect ?> / <?= (int) $myTotal ?></span>
                                    <span class="badge <?= $myPct >= 70 ? 'bg-success' : ($myPct >= 50 ? 'bg-warning text-dark' : 'bg-danger') ?>"><?= $myPct ?>%</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th><?= escape(t('quiz_results.col_question')) ?></th>
                                                <th><?= escape(t('tp_view.col_your_answer')) ?></th>
                                                <th><?= escape(t('tp_view.col_correct_answer')) ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($myQuizDetail as $i => $d): ?>
                                                <tr>
                                                    <td><?= $i + 1 ?></td>
                                                    <td><?= escape($d['question']) ?></td>
                                                    <td>
                                                        <?php if ($d['selected'] !== ''): ?>
                                                            <strong><?= escape($d['selected']) ?>)</strong> <?= escape($d['selected_text']) ?>
                                                        <?php else: ?>—<?php endif; ?>
                                                    </td>
                                                    <td><strong><?= escape($d['correct']) ?>)</strong> <?= escape($d['correct_text']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="p-3 border-top">
                                    <a href="<?= APP_URL ?>/pages/quiz_results.php?tp_id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><?= escape(t('tp_view.view_full_results')) ?></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
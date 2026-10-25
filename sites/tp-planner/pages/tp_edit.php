<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/tp_document.php';
requireTeacher();

$conn = getDB();
tp_ensure_sessions_schema($conn);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$session = null;
$materials = [];
$checklists = [];
$quizzes = [];

if ($id) {
    $r = $conn->prepare('SELECT * FROM tp_sessions WHERE id = ?');
    $r->bind_param('i', $id);
    $r->execute();
    $session = $r->get_result()->fetch_assoc();
    if (!$session) {
        flash('error', 'Session not found.');
        redirect(APP_URL . '/pages/tp_sessions.php');
    }
    $materials  = $conn->query("SELECT * FROM tp_materials WHERE tp_id = $id ORDER BY type, name")->fetch_all(MYSQLI_ASSOC);
    $checklists = $conn->query("SELECT * FROM tp_checklists WHERE tp_id = $id ORDER BY phase, id")->fetch_all(MYSQLI_ASSOC);
    $quizzes    = $conn->query("SELECT * FROM tp_quizzes WHERE tp_id = $id ORDER BY id")->fetch_all(MYSQLI_ASSOC);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $title        = trim($_POST['title'] ?? '');
    $fiche_number = trim($_POST['fiche_number'] ?? '');
    $unit         = trim($_POST['unit'] ?? '');
    $objectives   = trim($_POST['objectives'] ?? '');
    $skills       = trim($_POST['skills'] ?? '');
    $safety       = trim($_POST['safety'] ?? '');
    $duration     = (int) ($_POST['duration'] ?? 60);
    $class_id     = !empty($_POST['class_id']) ? (int) $_POST['class_id'] : null;

    if ($title === '') {
        $error = 'Le titre est requis.';
    } else {
        try {
            // Quill already outputs HTML, so no conversion needed
            // but we sanitize to be safe — strip dangerous tags while keeping formatting
            $objectives = strip_tags($objectives, '<p><br><ul><ol><li><strong><em><u>');
            $skills     = strip_tags($skills,     '<p><br><ul><ol><li><strong><em><u>');
            $safety     = strip_tags($safety,     '<p><br><ul><ol><li><strong><em><u>');

            $schema_image = $session['schema_image'] ?? null;
            if (!empty($_FILES['schema_image']['tmp_name'])) {
                $paths = tp_upload_paths('tp_schemas');
                $ext   = strtolower(pathinfo($_FILES['schema_image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                if (!in_array($ext, $allowed, true)) {
                    throw new RuntimeException('Format d\'image non autorisé.');
                }
                $filename = 'schema_' . ($id ?: 'new') . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['schema_image']['tmp_name'], $paths['dir'] . $filename);
                $schema_image = $paths['web_prefix'] . $filename;
            }
            if (!empty($_POST['clear_schema_image'])) {
                $schema_image = null;
            }

            $imported_file    = $session['imported_file'] ?? null;
            $imported_content = $session['imported_content'] ?? '';
            $imported_type    = $session['imported_type'] ?? 'none';

            if (!empty($_POST['clear_imported_document'])) {
                $imported_file = null;
                $imported_content = '';
                $imported_type = 'none';
            } elseif (!empty($_FILES['tp_document']['tmp_name'])) {
                $doc = tp_process_document_upload($_FILES['tp_document'], $session);
                $imported_file    = $doc['path'];
                $imported_content = $doc['content'];
                $imported_type    = $doc['type'];
            }

            if ($id) {
                if ($class_id === null) {
                    $stmt = $conn->prepare('UPDATE tp_sessions SET title=?, fiche_number=?, unit=?, objectives=?, skills=?, safety=?, schema_image=?, imported_file=?, imported_content=?, imported_type=?, duration=?, class_id=NULL WHERE id=?');
                    $stmt->bind_param('ssssssssssii', $title, $fiche_number, $unit, $objectives, $skills, $safety, $schema_image, $imported_file, $imported_content, $imported_type, $duration, $id);
                } else {
                    $stmt = $conn->prepare('UPDATE tp_sessions SET title=?, fiche_number=?, unit=?, objectives=?, skills=?, safety=?, schema_image=?, imported_file=?, imported_content=?, imported_type=?, duration=?, class_id=? WHERE id=?');
                    $stmt->bind_param('ssssssssssiii', $title, $fiche_number, $unit, $objectives, $skills, $safety, $schema_image, $imported_file, $imported_content, $imported_type, $duration, $class_id, $id);
                }
                $stmt->execute();
                $sid = $id;
            } else {
                if ($class_id === null) {
                    $stmt = $conn->prepare('INSERT INTO tp_sessions (title, fiche_number, unit, objectives, skills, safety, schema_image, imported_file, imported_content, imported_type, duration, class_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL)');
                    $stmt->bind_param('ssssssssssi', $title, $fiche_number, $unit, $objectives, $skills, $safety, $schema_image, $imported_file, $imported_content, $imported_type, $duration);
                } else {
                    $stmt = $conn->prepare('INSERT INTO tp_sessions (title, fiche_number, unit, objectives, skills, safety, schema_image, imported_file, imported_content, imported_type, duration, class_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                    $stmt->bind_param('ssssssssssii', $title, $fiche_number, $unit, $objectives, $skills, $safety, $schema_image, $imported_file, $imported_content, $imported_type, $duration, $class_id);
                }
                $stmt->execute();
                $sid = (int) $conn->insert_id;
            }

            $conn->query("DELETE FROM tp_materials WHERE tp_id = $sid");
            if (!empty($_POST['mat_name'])) {
                $mt = $conn->prepare('INSERT INTO tp_materials (tp_id, name, type) VALUES (?,?,?)');
                foreach ($_POST['mat_name'] as $i => $name) {
                    $name = trim($name);
                    if ($name === '') {
                        continue;
                    }
                    $type = $_POST['mat_type'][$i] ?? 'reagent';
                    $mt->bind_param('iss', $sid, $name, $type);
                    $mt->execute();
                }
            }

            $conn->query("DELETE FROM tp_checklists WHERE tp_id = $sid");
            if (!empty($_POST['check_phase'])) {
                $ct = $conn->prepare('INSERT INTO tp_checklists (tp_id, phase, item, is_done) VALUES (?,?,?,0)');
                foreach ($_POST['check_phase'] as $i => $phase) {
                    $text = trim($_POST['check_text'][$i] ?? '');
                    if ($text === '') {
                        continue;
                    }
                    $ct->bind_param('iss', $sid, $phase, $text);
                    $ct->execute();
                }
            }

            $conn->query("DELETE FROM tp_quizzes WHERE tp_id = $sid");
            if (!empty($_POST['quiz_question'])) {
                $qt = $conn->prepare('INSERT INTO tp_quizzes (tp_id, question, option_a, option_b, option_c, option_d, correct_option) VALUES (?,?,?,?,?,?,?)');
                $corrects = $_POST['quiz_correct'] ?? [];
                foreach ($_POST['quiz_question'] as $i => $q) {
                    $q = trim($q);
                    if ($q === '') {
                        continue;
                    }
                    $cor = $corrects[$i] ?? 'A';
                    $qt->bind_param('issssss', $sid, $q, trim($_POST['quiz_a'][$i] ?? ''), trim($_POST['quiz_b'][$i] ?? ''), trim($_POST['quiz_c'][$i] ?? ''), trim($_POST['quiz_d'][$i] ?? ''), $cor);
                    $qt->execute();
                }
            }

            flash('success', $id ? 'Session TP mise à jour.' : 'Session TP créée.');
            redirect(APP_URL . '/pages/tp_view.php?id=' . $sid);
        } catch (Throwable $e) {
            $error = 'Enregistrement impossible : ' . $e->getMessage();
        }
    }
}

$classesList = $conn->query('SELECT id, name FROM classes ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$pageTitle   = ($id ? 'Modifier' : 'Nouvelle') . ' session TP - ' . APP_NAME;

function tp_edit_plain_field(?string $html): string
{
    if ($html === null || $html === '') {
        return '';
    }
    // If it looks like HTML already (from Quill), return as-is
    if (strpos($html, '<') !== false) {
        return $html;
    }
    $text = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", $html));
    return trim(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
}

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<!-- Quill CSS -->
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">

<!-- MathJax — renders \( … \) and \[ … \] produced by the DOCX/PDF importer -->
<script>
window.MathJax = {
    tex: { inlineMath: [['\\(','\\)'], ['$','$']], displayMath: [['\\[','\\]'], ['$$','$$']] },
    options: { skipHtmlTags: ['script','noscript','style','textarea','pre'] }
};
</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" id="MathJax-script" async></script>
<style>
    .ql-container { font-size: 0.95rem; }
    .ql-editor { min-height: 80px; }
    .ql-toolbar { border-radius: 6px 6px 0 0; background: #f8f9fa; }
    .ql-container { border-radius: 0 0 6px 6px; }
</style>

<main class="container py-4">
    <h1 class="page-title mb-2"><?= $id ? 'Modifier' : 'Nouvelle' ?> session TP</h1>
    <p class="text-muted mb-4">Renseignez les champs essentiels, puis importez votre fiche complète (Word ou PDF) avec le mode opératoire et le contenu détaillé.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= escape($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="tp-form">
        <?= csrf_field() ?>

        <div class="card mb-4">
            <div class="card-header bg-white"><h5 class="mb-0">Identification</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">N° de fiche</label>
                        <input type="text" name="fiche_number" class="form-control" placeholder="ex. FT-04"
                               value="<?= escape($session['fiche_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Titre *</label>
                        <input type="text" name="title" class="form-control" required
                               value="<?= escape($session['title'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Unité / matière</label>
                        <input type="text" name="unit" class="form-control" placeholder="ex. Chimie organique"
                               value="<?= escape($session['unit'] ?? '') ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Classe</label>
                        <select name="class_id" class="form-select">
                            <option value="">— Aucune —</option>
                            <?php foreach ($classesList as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"
                                    <?= isset($session['class_id']) && (int) $session['class_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                                    <?= escape($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Durée (min)</label>
                        <input type="number" name="duration" class="form-control" min="1"
                               value="<?= (int) ($session['duration'] ?? 60) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning bg-opacity-10"><h5 class="mb-0">⚠️ Consignes de sécurité</h5></div>
            <div class="card-body">
                <input type="hidden" name="safety" id="safety-input">
                <div id="safety-editor"><?= tp_edit_plain_field($session['safety'] ?? '') ?></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white"><h5 class="mb-0">Objectifs pédagogiques</h5></div>
            <div class="card-body">
                <input type="hidden" name="objectives" id="objectives-input">
                <div id="objectives-editor"><?= tp_edit_plain_field($session['objectives'] ?? '') ?></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white"><h5 class="mb-0">Compétences visées</h5></div>
            <div class="card-body">
                <input type="hidden" name="skills" id="skills-input">
                <div id="skills-editor"><?= tp_edit_plain_field($session['skills'] ?? '') ?></div>
            </div>
        </div>

        <div class="card mb-4 border-primary">
            <div class="card-header bg-primary bg-opacity-10">
                <h5 class="mb-0">Mode opératoire — Importer (Word / PDF)</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Document complet : étapes, résultats, tableaux. Ce contenu alimente uniquement la section « Mode opératoire ».</p>
                <?php if (!empty($session['imported_file'])): ?>
                    <div class="alert alert-info py-2 small mb-3">
                        Document actuel :
                        <strong><?= escape(basename($session['imported_file'])) ?></strong>
                        (<?= escape($session['imported_type'] ?? 'none') ?>)
                        <?php if (!empty($session['imported_content'])): ?>
                            — aperçu disponible à l'export et à la consultation.
                        <?php endif; ?>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="clear_imported_document" value="1" id="clearDoc">
                        <label class="form-check-label text-danger" for="clearDoc">Supprimer le document importé</label>
                    </div>
                <?php endif; ?>
                <input type="file" name="tp_document" class="form-control" accept=".docx,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                <div class="form-text">Formats : .docx (Word) ou .pdf — max. 10 Mo recommandé.</div>
            </div>
        </div>

        <?php if (!empty($session['imported_content'])): ?>
        <div class="card mb-4 border-secondary">
            <div class="card-header bg-secondary bg-opacity-10 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Aperçu — Mode opératoire importé</h5>
                <span class="badge bg-secondary"><?= escape($session['imported_type'] ?? '') ?></span>
            </div>
            <div class="card-body" style="max-height:420px;overflow-y:auto;font-size:.93rem;line-height:1.65;">
                <?= $session['imported_content'] /* already sanitised on save */ ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header bg-white"><h5 class="mb-0">Image expérimentale</h5></div>
            <div class="card-body">
                <?php if (!empty($session['schema_image'])): ?>
                    <img src="<?= escape(APP_URL . '/' . $session['schema_image']) ?>" alt="Schéma" class="img-fluid rounded border mb-2" style="max-height:200px;">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="clear_schema_image" value="1" id="clearSchema">
                        <label class="form-check-label text-danger small" for="clearSchema">Supprimer l'image</label>
                    </div>
                <?php endif; ?>
                <input type="file" name="schema_image" class="form-control" accept="image/*">
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Checklist</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addCheck">+ Ajouter</button>
            </div>
            <div class="card-body" id="checkContainer">
                <?php
                $checks = $checklists ?: [['phase' => 'before', 'item' => '']];
                foreach ($checks as $ch):
                ?>
                <div class="check-row row g-2 mb-2">
                    <div class="col-md-2">
                        <select name="check_phase[]" class="form-select form-select-sm">
                            <option value="before" <?= ($ch['phase'] ?? '') === 'before' ? 'selected' : '' ?>>Avant</option>
                            <option value="during" <?= ($ch['phase'] ?? '') === 'during' ? 'selected' : '' ?>>Pendant</option>
                            <option value="after"  <?= ($ch['phase'] ?? '') === 'after'  ? 'selected' : '' ?>>Après</option>
                        </select>
                    </div>
                    <div class="col-md-9">
                        <input type="text" name="check_text[]" class="form-control form-control-sm"
                               value="<?= escape($ch['item'] ?? '') ?>" placeholder="Élément à vérifier">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-check w-100">×</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Mini-quiz</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addQuiz">+ Question</button>
            </div>
            <div class="card-body" id="quizContainer">
                <?php
                if (empty($quizzes)) {
                    $quizzes = [['question' => '', 'option_a' => '', 'option_b' => '', 'option_c' => '', 'option_d' => '', 'correct_option' => 'A']];
                }
                foreach ($quizzes as $qz):
                    $co = $qz['correct_option'] ?? 'A';
                ?>
                <div class="quiz-row card mb-3">
                    <div class="card-body">
                        <input type="text" name="quiz_question[]" class="form-control mb-2" placeholder="Question"
                               value="<?= escape($qz['question'] ?? '') ?>">
                        <div class="row g-2">
                            <div class="col-6"><input type="text" name="quiz_a[]" class="form-control form-control-sm" placeholder="A" value="<?= escape($qz['option_a'] ?? '') ?>"></div>
                            <div class="col-6"><input type="text" name="quiz_b[]" class="form-control form-control-sm" placeholder="B" value="<?= escape($qz['option_b'] ?? '') ?>"></div>
                            <div class="col-6"><input type="text" name="quiz_c[]" class="form-control form-control-sm" placeholder="C" value="<?= escape($qz['option_c'] ?? '') ?>"></div>
                            <div class="col-6"><input type="text" name="quiz_d[]" class="form-control form-control-sm" placeholder="D" value="<?= escape($qz['option_d'] ?? '') ?>"></div>
                        </div>
                        <div class="mt-2 small">
                            <span class="me-2">Bonne réponse :</span>
                            <?php foreach (['A', 'B', 'C', 'D'] as $opt): ?>
                                <label class="me-2"><input type="radio" name="quiz_correct[]" value="<?= $opt ?>" <?= $co === $opt ? 'checked' : '' ?>> <?= $opt ?></label>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger mt-2 remove-quiz">Supprimer</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Matériel</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addMat">+ Ajouter</button>
            </div>
            <div class="card-body" id="matsContainer">
                <?php
                $mats = $materials ?: [['type' => 'reagent', 'name' => '']];
                foreach ($mats as $m):
                ?>
                <div class="mat-row row g-2 mb-2">
                    <div class="col-md-2">
                        <select name="mat_type[]" class="form-select form-select-sm">
                            <option value="reagent" <?= ($m['type'] ?? '') === 'reagent' ? 'selected' : '' ?>>Réactif</option>
                            <option value="equipment" <?= ($m['type'] ?? '') === 'equipment' ? 'selected' : '' ?>>Équipement</option>
                        </select>
                    </div>
                    <div class="col-md-9">
                        <input type="text" name="mat_name[]" class="form-control form-control-sm"
                               placeholder="Nom / formule" value="<?= escape($m['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-mat w-100">×</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mb-5">
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a href="<?= APP_URL ?>/pages/tp_sessions.php" class="btn btn-secondary">Annuler</a>
        </div>
    </form>
</main>

<!-- Quill JS -->
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
(function () {
    const toolbar = [
        ['bold', 'italic', 'underline'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['clean']
    ];

    function makeQuill(editorId, inputId, placeholder) {
        const q = new Quill('#' + editorId, {
            theme: 'snow',
            placeholder: placeholder,
            modules: { toolbar: toolbar }
        });
        // Sync hidden input on submit
        document.getElementById('tp-form').addEventListener('submit', function () {
            document.getElementById(inputId).value = q.root.innerHTML;
        });
        return q;
    }

    makeQuill('safety-editor',     'safety-input',     'EPI, pictogrammes, gestes interdits…');
    makeQuill('objectives-editor', 'objectives-input', 'Un objectif par ligne ou paragraphe');
    makeQuill('skills-editor',     'skills-input',     'Compétences évaluées');

    // --- Dynamic rows ---
    document.getElementById('addMat').addEventListener('click', function () {
        document.getElementById('matsContainer').insertAdjacentHTML('beforeend',
            '<div class="mat-row row g-2 mb-2"><div class="col-md-2"><select name="mat_type[]" class="form-select form-select-sm"><option value="reagent">Réactif</option><option value="equipment">Équipement</option></select></div><div class="col-md-9"><input type="text" name="mat_name[]" class="form-control form-control-sm" placeholder="Nom"></div><div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger remove-mat w-100">×</button></div></div>');
    });
    document.getElementById('addCheck').addEventListener('click', function () {
        document.getElementById('checkContainer').insertAdjacentHTML('beforeend',
            '<div class="check-row row g-2 mb-2"><div class="col-md-2"><select name="check_phase[]" class="form-select form-select-sm"><option value="before">Avant</option><option value="during">Pendant</option><option value="after">Après</option></select></div><div class="col-md-9"><input type="text" name="check_text[]" class="form-control form-control-sm" placeholder="Élément"></div><div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger remove-check w-100">×</button></div></div>');
    });
    document.getElementById('addQuiz').addEventListener('click', function () {
        document.getElementById('quizContainer').insertAdjacentHTML('beforeend',
            '<div class="quiz-row card mb-3"><div class="card-body"><input type="text" name="quiz_question[]" class="form-control mb-2" placeholder="Question"><div class="row g-2"><div class="col-6"><input name="quiz_a[]" class="form-control form-control-sm" placeholder="A"></div><div class="col-6"><input name="quiz_b[]" class="form-control form-control-sm" placeholder="B"></div><div class="col-6"><input name="quiz_c[]" class="form-control form-control-sm" placeholder="C"></div><div class="col-6"><input name="quiz_d[]" class="form-control form-control-sm" placeholder="D"></div></div><div class="mt-2 small"><label class="me-2"><input type="radio" name="quiz_correct[]" value="A" checked> A</label><label class="me-2"><input type="radio" name="quiz_correct[]" value="B"> B</label><label class="me-2"><input type="radio" name="quiz_correct[]" value="C"> C</label><label class="me-2"><input type="radio" name="quiz_correct[]" value="D"> D</label></div><button type="button" class="btn btn-sm btn-outline-danger mt-2 remove-quiz">Supprimer</button></div></div>');
    });
    document.body.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-mat'))   e.target.closest('.mat-row').remove();
        if (e.target.classList.contains('remove-check')) e.target.closest('.check-row').remove();
        if (e.target.classList.contains('remove-quiz'))  e.target.closest('.quiz-row').remove();
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
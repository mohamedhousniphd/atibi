<?php
/**
 * Blocs HTML structurés pour fiche TP (vue — ordre strict).
 */

function tp_section_open(string $title, string $extraClass = ''): void
{
    $class = 'tp-section card mb-4' . ($extraClass !== '' ? ' ' . $extraClass : '');
    echo '<section class="' . escape($class) . '">';
    echo '<div class="card-header tp-section__header"><strong>' . escape($title) . '</strong></div>';
}

function tp_section_close(): void
{
    echo '</section>';
}

function tp_section_body_open(): void
{
    echo '<div class="card-body tp-rich-content">';
}

function tp_section_body_close(): void
{
    echo '</div>';
}

/** @param array<int, array<string, mixed>> $checklists */
function tp_render_checklist_block(int $tpId, array $checklists): void
{
    tp_section_open('Checklist de préparation');
    echo '<div class="card-body">';
    if (empty($checklists)) {
        echo '<p class="text-muted mb-0">' . escape(t('tp_view.no_checklist')) . ' ';
        echo '<a href="' . escape(APP_URL) . '/pages/tp_edit.php?id=' . $tpId . '">' . escape(t('tp_view.edit_to_add')) . '</a>';
        echo ' ' . escape(t('tp_view.to_add_some')) . '</p>';
        tp_section_close();
        return;
    }

    $byPhase = ['before' => [], 'during' => [], 'after' => []];
    foreach ($checklists as $ch) {
        $byPhase[$ch['phase']][] = $ch;
    }
    $phaseLabel = [
        'before' => t('tp_view.phase_before'),
        'during' => t('tp_view.phase_during'),
        'after'  => t('tp_view.phase_after'),
    ];
    $phaseBadge = ['before' => 'badge-before', 'during' => 'badge-during', 'after' => 'badge-after'];

    foreach ($byPhase as $phase => $items) {
        if (empty($items)) {
            continue;
        }
        echo '<h6 class="mt-2 mb-2"><span class="badge ' . escape($phaseBadge[$phase]) . '">' . escape($phaseLabel[$phase]) . '</span></h6>';
        echo '<ul class="list-group list-group-flush mb-3">';
        foreach ($items as $ch) {
            $done = !empty($ch['is_done']);
            echo '<li class="list-group-item checklist-item' . ($done ? ' done' : '') . ' d-flex justify-content-between align-items-center">';
            echo '<span class="checklist-text">' . escape($ch['item'] ?? '') . '</span>';
            echo '<form method="post" class="d-inline ms-2">';
            echo csrf_field();
            echo '<input type="hidden" name="check_id" value="' . (int) $ch['id'] . '">';
            echo '<input type="hidden" name="is_done" value="' . ($done ? '0' : '1') . '">';
            echo '<button type="submit" class="btn btn-sm ' . ($done ? 'btn-success' : 'btn-outline-secondary') . '">';
            echo $done ? '<i class="bi bi-check-lg"></i> ' . escape(t('tp_view.done_undo')) : escape(t('tp_view.mark_done'));
            echo '</button></form></li>';
        }
        echo '</ul>';
    }
    echo '</div>';
    tp_section_close();
}

/** @param array<int, array<string, mixed>> $quizzes */
function tp_render_quiz_preview(array $quizzes): void
{
    tp_section_open('Mini-quiz');
    echo '<div class="card-body">';
    if (empty($quizzes)) {
        echo '<p class="text-muted mb-0">' . escape(t('tp_view.no_quiz')) . '</p>';
        tp_section_close();
        return;
    }
    foreach ($quizzes as $i => $q) {
        echo '<div class="tp-quiz-preview mb-3 pb-3 border-bottom">';
        echo '<p class="fw-semibold mb-2">' . ($i + 1) . '. ' . escape($q['question'] ?? '') . '</p>';
        echo '<ul class="list-unstyled mb-0 small">';
        foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $col) {
            $val = trim((string) ($q[$col] ?? ''));
            if ($val === '') {
                continue;
            }
            echo '<li class="mb-1"><span class="text-muted">' . $letter . ')</span> ' . escape($val) . '</li>';
        }
        echo '</ul></div>';
    }
    echo '<p class="small text-muted mb-0">Répondez via l’onglet « ' . escape(t('tp_view.tab_quiz')) . ' ».</p>';
    echo '</div>';
    tp_section_close();
}

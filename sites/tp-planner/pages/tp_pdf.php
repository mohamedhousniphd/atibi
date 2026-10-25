<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/tp_document.php';
requireLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
    header('Location: ' . APP_URL . (is_staff() ? '/pages/tp_sessions.php' : '/pages/member_dashboard.php'));
    exit;
}

$conn = getDB();
tp_ensure_sessions_schema($conn);
$stmt    = $conn->prepare("SELECT s.*, c.name AS class_name FROM tp_sessions s LEFT JOIN classes c ON c.id = s.class_id WHERE s.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$session || !user_has_tp_access($conn, $id)) {
    header('Location: ' . APP_URL . (is_staff() ? '/pages/tp_sessions.php' : '/pages/member_dashboard.php'));
    exit;
}

$checklists = $conn->query("SELECT * FROM tp_checklists WHERE tp_id = $id ORDER BY phase, id")->fetch_all(MYSQLI_ASSOC);
$quizzes    = $conn->query("SELECT * FROM tp_quizzes    WHERE tp_id = $id ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$vendorAutoload = ROOT_PATH . '/vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    die('Please run: composer install (TCPDF is required for PDF export).');
}
require_once $vendorAutoload;
require_once ROOT_PATH . '/vendor/propa/tcpdi/tcpdi.php';

// ─── Custom TCPDF subclass for header / footer ────────────────────────────────
class TP_PDF extends TCPDI {

    public string $docTitle    = '';
    public string $docClass    = '';
    public string $docDuration = '';

    public function Header(): void {
        // Top accent bar
        $this->SetFillColor(30, 58, 138);   // deep blue
        $this->Rect(0, 0, $this->getPageWidth(), 6, 'F');

        $this->SetY(10);
        $this->SetFont('dejavusans', 'B', 9);
        $this->SetTextColor(30, 58, 138);
        $this->Cell(0, 5, mb_strtoupper($this->docTitle, 'UTF-8'), 0, 0, 'L');
        $this->SetFont('dejavusans', '', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 5, defined('APP_NAME') ? APP_NAME : 'TP Session', 0, 0, 'R');
        $this->SetY(18);
        // Separator line
        $this->SetDrawColor(210, 220, 240);
        $this->SetLineWidth(0.3);
        $this->Line($this->getMargins()['left'], $this->GetY(), $this->getPageWidth() - $this->getMargins()['right'], $this->GetY());
        $this->Ln(3);
    }

    public function Footer(): void {
        $this->SetY(-14);
        $this->SetDrawColor(210, 220, 240);
        $this->SetLineWidth(0.3);
        $this->Line($this->getMargins()['left'], $this->GetY(), $this->getPageWidth() - $this->getMargins()['right'], $this->GetY());
        $this->Ln(1);
        $this->SetFont('dejavusans', '', 7.5);
        $this->SetTextColor(150, 150, 150);
        $date = date('d/m/Y');
        $this->Cell(0, 5, "Généré le $date  ·  Classe : {$this->docClass}  ·  Durée : {$this->docDuration} min  ·  Présenté par : Azzeddine ATIBI", 0, 0, 'L');
        $this->Cell(0, 5, 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

// ─── Instantiate PDF ──────────────────────────────────────────────────────────
$pdf = new TP_PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator(defined('APP_NAME') ? APP_NAME : 'TP Platform');
$pdf->SetAuthor(defined('APP_NAME') ? APP_NAME : 'TP Platform');
$pdf->SetTitle($session['title']);
$pdf->SetSubject('Fiche de travaux pratiques');

$pdf->docTitle    = $session['title'];
$pdf->docClass    = $session['class_name'] ?? '-';
$pdf->docDuration = (string)(int)($session['duration'] ?? 0);

$pdf->SetMargins(18, 28, 18);
$pdf->SetHeaderMargin(6);
$pdf->SetFooterMargin(12);
$pdf->SetAutoPageBreak(true, 20);
$pdf->setCellHeightRatio(1.4);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 10);

// ─── Helper: styled section heading ──────────────────────────────────────────
function pdf_section_heading(TP_PDF $pdf, string $text): void {
    $pdf->Ln(3);
    $x = $pdf->getMargins()['left'];
    $y = $pdf->GetY();
    $w = $pdf->getPageWidth() - $x - $pdf->getMargins()['right'];
    $pdf->SetFillColor(237, 242, 255);
    $pdf->Rect($x, $y, $w, 8, 'F');
    $pdf->SetFillColor(30, 58, 138);
    $pdf->Rect($x, $y, 2.5, 8, 'F');
    $pdf->SetXY($x + 5, $y + 1);
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->SetTextColor(30, 58, 138);
    $pdf->Cell($w - 5, 6, mb_strtoupper($text, 'UTF-8'), 0, 1, 'L');
    $pdf->SetTextColor(50, 50, 50);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Ln(3);
}

// ─── Helper: convert a LaTeX math string to readable HTML (sub/sup + symbols) ─
/**
 * Converts LaTeX math notation to HTML that TCPDF can render.
 *
 * Handles:
 *   - Subscripts:    _{x}  or _x   → <sub>x</sub>
 *   - Superscripts:  ^{x}  or ^x   → <sup>x</sup>
 *   - Arrows:        \rightarrow \to \leftarrow \leftrightarrow → Unicode arrows
 *   - Greek letters: \alpha \beta \gamma … → Unicode characters
 *   - Common chem/math: \cdot \times \pm \infty …
 *   - \frac{a}{b}   → a/b
 *   - \sqrt{x}      → √x
 *   - Remaining \commands and braces are stripped gracefully
 */
function latex_to_html(string $latex): string
{
    // --- 1. Arrow & relation symbols ----------------------------------------
    $symbols = [
        '\rightarrow'     => '→',  '\to'            => '→',
        '\leftarrow'      => '←',  '\gets'          => '←',
        '\leftrightarrow' => '↔',  '\Rightarrow'    => '⇒',
        '\Leftarrow'      => '⇐',  '\Leftrightarrow'=> '⇔',
        '\longrightarrow' => '⟶',  '\longleftarrow' => '⟵',
        // Greek lower
        '\alpha'  => 'α', '\beta'   => 'β', '\gamma'  => 'γ', '\delta'  => 'δ',
        '\epsilon'=> 'ε', '\zeta'   => 'ζ', '\eta'    => 'η', '\theta'  => 'θ',
        '\iota'   => 'ι', '\kappa'  => 'κ', '\lambda' => 'λ', '\mu'     => 'μ',
        '\nu'     => 'ν', '\xi'     => 'ξ', '\pi'     => 'π', '\rho'    => 'ρ',
        '\sigma'  => 'σ', '\tau'    => 'τ', '\upsilon'=> 'υ', '\phi'    => 'φ',
        '\chi'    => 'χ', '\psi'    => 'ψ', '\omega'  => 'ω',
        // Greek upper
        '\Gamma'  => 'Γ', '\Delta'  => 'Δ', '\Theta'  => 'Θ', '\Lambda' => 'Λ',
        '\Xi'     => 'Ξ', '\Pi'     => 'Π', '\Sigma'  => 'Σ', '\Upsilon'=> 'Υ',
        '\Phi'    => 'Φ', '\Psi'    => 'Ψ', '\Omega'  => 'Ω',
        // Math operators / misc
        '\cdot'   => '·', '\times'  => '×', '\div'    => '÷', '\pm'     => '±',
        '\mp'     => '∓', '\leq'    => '≤', '\geq'    => '≥', '\neq'    => '≠',
        '\approx' => '≈', '\equiv'  => '≡', '\infty'  => '∞', '\partial'=> '∂',
        '\nabla'  => '∇', '\forall' => '∀', '\exists' => '∃', '\in'     => '∈',
        '\notin'  => '∉', '\subset' => '⊂', '\supset' => '⊃', '\cup'    => '∪',
        '\cap'    => '∩', '\circ'   => '∘', '\bullet' => '•',
        // Chem-specific
        '\Delta H'=> 'ΔH', '\Delta G'=> 'ΔG', '\Delta S'=> 'ΔS',
        '\degree' => '°',  '^\circ'  => '°',
        // Spaces & misc
        '\,'      => ' ',  '\;'      => ' ',  '\:'      => ' ',  '\!'      => '',
        '\quad'   => ' ',  '\qquad'  => '  ', '\ '      => ' ',
        '\left('  => '(',  '\right)' => ')',
        '\left['  => '[',  '\right]' => ']',
        '\left\{' => '{',  '\right\}'=> '}',
        '\{'      => '{',  '\}'      => '}',
    ];

    // Sort by length descending so longer tokens are replaced first (e.g. \Delta H before \Delta)
    uksort($symbols, fn($a, $b) => strlen($b) - strlen($a));
    foreach ($symbols as $latex_token => $unicode) {
        $latex = str_replace($latex_token, $unicode, $latex);
    }

    // --- 2. \frac{num}{den} → num/den ----------------------------------------
    $latex = preg_replace_callback(
        '/\\\\frac\{([^}]*)\}\{([^}]*)\}/',
        fn($m) => '(' . $m[1] . '/' . $m[2] . ')',
        $latex
    );
    // Also handle \frac without braces on short tokens: \frac ab → a/b
    $latex = preg_replace('/\\\\frac\s+(\S)\s+(\S)/', '($1/$2)', $latex);

    // --- 3. \sqrt{x} → √x -------------------------------------------------------
    $latex = preg_replace_callback(
        '/\\\\sqrt\{([^}]*)\}/',
        fn($m) => '√(' . $m[1] . ')',
        $latex
    );
    $latex = preg_replace('/\\\\sqrt\s+(\S)/', '√$1', $latex);

    // --- 4. Subscripts: _{abc} or _x  → <sub>abc</sub> -----------------------
    // Multi-char: _{...}
    $latex = preg_replace_callback(
        '/_\{([^}]*)\}/',
        fn($m) => '<sub>' . $m[1] . '</sub>',
        $latex
    );
    // Single-char: _x (not followed by {)
    $latex = preg_replace('/_([^{}\s<>])/', '<sub>$1</sub>', $latex);

    // --- 5. Superscripts: ^{abc} or ^x → <sup>abc</sup> ----------------------
    $latex = preg_replace_callback(
        '/\^\{([^}]*)\}/',
        fn($m) => '<sup>' . $m[1] . '</sup>',
        $latex
    );
    $latex = preg_replace('/\^([^{}\s<>])/', '<sup>$1</sup>', $latex);

    // --- 6. Strip remaining \commands (e.g. \text{...} → content) ------------
    $latex = preg_replace('/\\\\text\{([^}]*)\}/', '$1', $latex);
    $latex = preg_replace('/\\\\mathrm\{([^}]*)\}/', '$1', $latex);
    $latex = preg_replace('/\\\\mathbf\{([^}]*)\}/', '<strong>$1</strong>', $latex);
    $latex = preg_replace('/\\\\[a-zA-Z]+\{([^}]*)\}/', '$1', $latex); // generic \cmd{x} → x
    $latex = preg_replace('/\\\\[a-zA-Z]+/', '', $latex);               // lone \cmd → removed

    // --- 7. Strip bare braces left over --------------------------------------
    $latex = str_replace(['{', '}'], '', $latex);

    return trim($latex);
}

// ─── Helper: strip rich-text for meta fields, keep safe HTML for body ────────
function pdf_sanitize(string $html): string
{
    $html = preg_replace('/<p[^>]*>\s*<\/p>/i', '<br>', $html);
    $html = preg_replace('/<p[^>]*>(.*?)<\/p>/is', '$1<br>', $html);
    $html = preg_replace('/<div[^>]*>(.*?)<\/div>/is', '$1<br>', $html);

    // ── Convert LaTeX equations to readable HTML (TCPDF cannot render MathJax) ──
    //
    // Case 1 – display math:  \[ ... \]  → new line, styled equation block
    $html = preg_replace_callback(
        '/\x5C\[(.+?)\x5C\]/s',
        function ($m) {
            $rendered = latex_to_html(trim($m[1]));
            return '<br><i>' . $rendered . '</i><br>';
        },
        $html
    );

    // Case 2 – inline math:   \( ... \)  → inline italic
    $html = preg_replace_callback(
        '/\x5C\((.+?)\x5C\)/s',
        function ($m) {
            $rendered = latex_to_html(trim($m[1]));
            return '<i>' . $rendered . '</i>';
        },
        $html
    );

    // Case 3 – bare LaTeX-like patterns not wrapped in delimiters
    //   Detect sequences that look like chemical/math expressions: contain _{ or ^{ or \letter
    //   Only process text nodes (outside HTML tags) to avoid mangling attributes.
    $html = preg_replace_callback(
        '/(?<=>|^|[\s\(])([^<]*(?:_\{|_[A-Za-z0-9]|\^\{|\^[A-Za-z0-9]|\\\\[a-zA-Z])[^<]*)(?=<|\s*$)/m',
        function ($m) {
            // Only process if it actually looks like LaTeX (has _ ^ or \)
            $raw = $m[1];
            if (preg_match('/[_^][\{A-Za-z0-9]|\\\\[a-zA-Z]/', $raw)) {
                return latex_to_html($raw);
            }
            return $raw;
        },
        $html
    );

    $allowed = '<strong><b><em><i><u><ul><ol><li><br><h1><h2><h3><h4><sub><sup><span><table><tr><td><th>';
    $html = strip_tags($html, $allowed);
    $html = preg_replace('/<h[1-4][^>]*>(.*?)<\/h[1-4]>/is', '<br><strong>$1</strong><br>', $html);
    return trim($html);
}

function pdf_write_html_block(TP_PDF $pdf, string $html): void {
    if (trim($html) === '') {
        return;
    }
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->writeHTML(pdf_sanitize($html), true, false, true, false, '');
    $pdf->Ln(4);
}

/** Place image and advance Y (prevents overlap with following sections). */
function pdf_place_image(TP_PDF $pdf, string $path, float $x, float $maxW): void {
    $y = $pdf->GetY();
    $pdf->Image($path, $x, $y, $maxW, 0, '', '', '', false, 300);
    $pdf->SetY((float) $pdf->getImageRBY() + 6);
}

// ══════════════════════════════════════════════════════════════════════════════
// COVER BLOCK — Title card
// ══════════════════════════════════════════════════════════════════════════════
$pageW    = $pdf->getPageWidth();
$pageH    = $pdf->getPageHeight();
$mL       = $pdf->getMargins()['left'];
$mR       = $pdf->getMargins()['right'];
$contentW = $pageW - $mL - $mR;

// Blue title block
$pdf->SetFillColor(30, 58, 138);
$pdf->Rect($mL, $pdf->GetY(), $contentW, 28, 'F');
$pdf->SetXY($mL + 6, $pdf->GetY() + 4);
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->SetTextColor(255, 255, 255);
$pdf->MultiCell($contentW - 12, 9, $session['title'], 0, 'L', false, 1);

// Meta row
$pdf->SetFillColor(245, 247, 255);
$metaY = $pdf->GetY();
$pdf->Rect($mL, $metaY, $contentW, 10, 'F');
$pdf->SetXY($mL + 4, $metaY + 2);
$pdf->SetFont('dejavusans', '', 9);
$pdf->SetTextColor(60, 60, 100);

$metaItems = [];
if (!empty($session['fiche_number'])) {
    $metaItems[] = 'N° fiche : ' . $session['fiche_number'];
}
if (!empty($session['unit'])) {
    $metaItems[] = 'Unité : ' . $session['unit'];
}
if (!empty($session['class_name'])) {
    $metaItems[] = 'Classe : ' . $session['class_name'];
}

$pdf->MultiCell($contentW, 5, implode('  |  ', $metaItems), 0, 'L');
$pdf->Ln(6);
$pdf->SetTextColor(50, 50, 50);

// ── 4. Consignes de sécurité ────────────────────────────────────────────────
if (!empty($session['safety'])) {
    pdf_section_heading($pdf, 'Consignes de sécurité');
    $pdf->SetFillColor(255, 251, 235);
    pdf_write_html_block($pdf, (string) $session['safety']);
}

// ── 2. Objectifs pédagogiques ───────────────────────────────────────────────
if (!empty($session['objectives'])) {
    pdf_section_heading($pdf, 'Objectifs pédagogiques');
    pdf_write_html_block($pdf, (string) $session['objectives']);
}

// ── 3. Compétences (sous les objectifs) ─────────────────────────────────────
if (!empty($session['skills'])) {
    pdf_section_heading($pdf, 'Compétences visées');
    pdf_write_html_block($pdf, (string) $session['skills']);
}

// ── 5. Mode opératoire (document importé uniquement) ────────────────────────
$importedHtml = trim((string) ($session['imported_content'] ?? ''));
if ($importedHtml !== '') {
    pdf_section_heading($pdf, 'Mode opératoire — Étapes et résultats');
    pdf_write_html_block($pdf, $importedHtml);
}

// ── 6. Image expérimentale ────────────────────────────────────────────────────
if (!empty($session['schema_image'])) {
    $schemaPath = ROOT_PATH . '/' . ltrim((string) $session['schema_image'], '/');
    if (is_file($schemaPath)) {
        pdf_section_heading($pdf, 'Image expérimentale');
        pdf_place_image($pdf, $schemaPath, $mL, min($contentW, 170));
    }
}

// ── 7. Checklist ──────────────────────────────────────────────────────────────
// ══════════════════════════════════════════════════════════════════════════════
if (!empty($checklists)) {
    pdf_section_heading($pdf, 'Checklist de préparation');

    $phases = [
        'before' => ['label' => 'Avant le TP',   'color' => [22, 163, 74]],
        'during' => ['label' => 'Pendant le TP', 'color' => [234, 88, 12]],
        'after'  => ['label' => 'Après le TP',   'color' => [109, 40, 217]],
    ];

    foreach ($phases as $phase => $cfg) {
        $items = array_filter($checklists, fn($c) => $c['phase'] === $phase);
        if (empty($items)) continue;

        // Phase label
        $pdf->SetFillColor(...$cfg['color']);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('dejavusans', 'B', 8.5);
        $pdf->Cell($contentW, 6, '  ' . mb_strtoupper($cfg['label'], 'UTF-8'), 0, 1, 'L', true);
        $pdf->SetTextColor(50, 50, 50);

        foreach ($items as $ch) {
            $pdf->SetFont('dejavusans', '', 9.5);
            $pdf->SetX($mL + 3);
            // Checkbox square
            $pdf->SetDrawColor(160, 160, 190);
            $pdf->Rect($mL + 3, $pdf->GetY() + 1.5, 4, 4);
            $pdf->SetX($mL + 9);
            $pdf->Cell($contentW - 9, 7, htmlspecialchars($ch['item'] ?? ''), 0, 1, 'L');
        }
        $pdf->Ln(1);
    }
    $pdf->Ln(2);
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 5 — MINI-QUIZ
// ══════════════════════════════════════════════════════════════════════════════
if (!empty($quizzes)) {
    pdf_section_heading($pdf, "Mini-Quiz d'évaluation");

    foreach ($quizzes as $i => $q) {
        $qNum = $i + 1;

        // Question header — light blue background
        $pdf->SetFillColor(237, 242, 255);
        $pdf->SetDrawColor(200, 210, 240);
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->SetTextColor(30, 58, 138);
        $pdf->Cell($contentW, 7, "Q$qNum : " . htmlspecialchars($q['question'] ?? ''), 'LTR', 1, 'L', true);

        // Options in 2×2 grid — explicit X positioning avoids drift
        $options = [
            'A' => $q['option_a'] ?? '',
            'B' => $q['option_b'] ?? '',
            'C' => $q['option_c'] ?? '',
            'D' => $q['option_d'] ?? '',
        ];
        $halfW  = (int) floor($contentW / 2);
        $optArr = array_keys($options);

        $pdf->SetFont('dejavusans', '', 9.5);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetFillColor(252, 252, 255);

        for ($row = 0; $row < 2; $row++) {
            $rowY = $pdf->GetY();
            for ($col = 0; $col < 2; $col++) {
                $key   = $optArr[$row * 2 + $col];
                $val   = htmlspecialchars($options[$key], ENT_QUOTES, 'UTF-8');
                $label = $key . ')  ' . $val;
                $cellX = $mL + $col * $halfW;
                // last column takes remaining width to avoid float gap
                $cellW = ($col === 1) ? ($contentW - $halfW) : $halfW;
                $pdf->SetXY($cellX, $rowY);
                $pdf->Cell($cellW, 7, $label, 'LTRB', 0, 'L', true);
            }
            $pdf->Ln(7); // advance one row height
        }

        // Reset draw/fill colors for next iteration
        $pdf->SetDrawColor(210, 220, 240);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Ln(4);
    }
}

// ─── Output ───────────────────────────────────────────────────────────────────
$safeName = preg_replace('/[^a-z0-9]/i', '_', $session['title']);
$pdf->Output('TP_' . $safeName . '.pdf', 'I');
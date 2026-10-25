<?php
/**
 * Import Word (.docx) / PDF et schéma tp_sessions.
 *
 * FIXES:
 *  1. Word equations (OMML) are now extracted and rendered as LaTeX via MathJax.
 *  2. PDF text extraction uses multiple fallback strategies (pdftotext CLI → PdfParser → raw stream).
 *  3. Extracted content is always returned so the caller can inject it into MODE OPÉRATOIRE.
 */

function tp_ensure_sessions_schema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $columns = [
        'fiche_number'     => 'VARCHAR(100) NULL',
        'unit'             => 'VARCHAR(150) NULL',
        'safety'           => 'TEXT NULL',
        'schema_image'     => 'VARCHAR(500) NULL',
        'imported_file'    => 'VARCHAR(500) NULL',
        'imported_content' => 'MEDIUMTEXT NULL',
        'imported_type'    => "ENUM('none','word','pdf') NOT NULL DEFAULT 'none'",
    ];

    foreach ($columns as $name => $def) {
        if (!db_table_has_column($conn, 'tp_sessions', $name)) {
            $conn->query("ALTER TABLE tp_sessions ADD COLUMN `$name` $def");
        }
    }

    if (db_table_has_column($conn, 'tp_sessions', 'skills')) {
        $res = $conn->query("SHOW COLUMNS FROM tp_sessions LIKE 'skills'");
        if ($res && ($row = $res->fetch_assoc()) && stripos($row['Type'], 'varchar') !== false) {
            $conn->query('ALTER TABLE tp_sessions MODIFY COLUMN skills TEXT NULL');
        }
    }
}

/** @return array{dir: string, web_prefix: string} */
function tp_upload_paths(string $subdir): array
{
    $dir = ROOT_PATH . '/uploads/' . $subdir . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return ['dir' => $dir, 'web_prefix' => 'uploads/' . $subdir . '/'];
}

/**
 * @return array{path: string, type: string, content: string}
 */
function tp_process_document_upload(array $file, ?array $existing = null): array
{
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [
            'path'    => $existing['imported_file'] ?? null,
            'type'    => $existing['imported_type'] ?? 'none',
            'content' => $existing['imported_content'] ?? '',
        ];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['docx', 'pdf'];
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Format non autorisé. Utilisez .docx ou .pdf');
    }

    $paths = tp_upload_paths('tp_documents');
    $basename = 'tpdoc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $paths['dir'] . $basename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Échec du téléversement du document.');
    }

    $relPath = $paths['web_prefix'] . $basename;
    $content = $ext === 'docx' ? tp_docx_to_html($dest) : tp_pdf_to_html($dest);

    return [
        'path'    => $relPath,
        'type'    => $ext === 'docx' ? 'word' : 'pdf',
        'content' => $content,
    ];
}

// ---------------------------------------------------------------------------
// WORD (.docx) → HTML
// ---------------------------------------------------------------------------

/**
 * Convert a .docx file to HTML, preserving equations as MathJax \( … \) or \[ … \].
 *
 * Strategy:
 *   • Walk every top-level block in word/document.xml  (w:p, w:tbl).
 *   • For each paragraph, collect text runs AND OMML equation objects.
 *   • OMML (<m:oMath>) is converted to a LaTeX string via tp_omml_to_latex().
 *   • The caller's page must load MathJax for equations to render:
 *       <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js"></script>
 */
function tp_docx_to_html(string $path): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Extension ZipArchive requise pour les fichiers Word.');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Impossible d\'ouvrir le fichier Word.');
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xml === false || $xml === '') {
        throw new RuntimeException('Document Word vide ou invalide.');
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadXML($xml);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // Register all namespaces we may encounter
    $namespaces = [
        'w'   => 'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
        'm'   => 'http://schemas.openxmlformats.org/officeDocument/2006/math',
        'r'   => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
        'wp'  => 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing',
    ];
    foreach ($namespaces as $prefix => $uri) {
        $xpath->registerNamespace($prefix, $uri);
    }

    $html = '';

    // Iterate top-level children of w:body
    $bodyNodes = $xpath->query('//w:body/node()');
    if (!$bodyNodes) {
        return '<p><em>Document importé sans contenu extractible.</em></p>';
    }

    foreach ($bodyNodes as $node) {
        if (!($node instanceof DOMElement)) {
            continue;
        }

        $localName = $node->localName;

        if ($localName === 'p') {
            $html .= tp_docx_paragraph_to_html($node, $xpath);
        } elseif ($localName === 'tbl') {
            $html .= tp_docx_table_to_html($node, $xpath);
        }
    }

    return $html !== '' ? $html : '<p><em>Document importé sans texte extractible.</em></p>';
}

/**
 * Convert a single <w:p> element (which may contain text runs, bold runs,
 * headings, AND <m:oMath> equations) to an HTML string.
 */
function tp_docx_paragraph_to_html(DOMElement $p, DOMXPath $xpath): string
{
    $inner = '';
    $hasContent = false;

    // Detect heading level from paragraph style
    $styleNodes = $xpath->query('w:pPr/w:pStyle/@w:val', $p);
    $headingLevel = 0;
    if ($styleNodes && $styleNodes->length > 0) {
        $styleName = strtolower($styleNodes->item(0)->nodeValue);
        if (preg_match('/heading\s*(\d)/i', $styleName, $m)) {
            $headingLevel = (int) $m[1];
        }
    }

    // Walk direct children of <w:p>
    foreach ($p->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
            continue;
        }

        $ns        = $child->namespaceURI;
        $localName = $child->localName;

        // ── Plain text run ──────────────────────────────────────────────────
        if ($localName === 'r' && $ns === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main') {
            $textNodes = $xpath->query('w:t', $child);
            if ($textNodes && $textNodes->length > 0) {
                $runText = '';
                foreach ($textNodes as $t) {
                    $runText .= $t->textContent;
                }
                if ($runText !== '') {
                    // Detect bold
                    $boldNodes = $xpath->query('w:rPr/w:b', $child);
                    $isBold    = $boldNodes && $boldNodes->length > 0;
                    $escaped   = htmlspecialchars($runText, ENT_QUOTES, 'UTF-8');
                    $inner    .= $isBold ? "<strong>{$escaped}</strong>" : $escaped;
                    $hasContent = true;
                }
            }
        }

        // ── OMML equation (inline: w:oMathPara > w:oMath  OR  direct m:oMath) ─
        if ($localName === 'oMath' || $localName === 'oMathPara') {
            // oMathPara wraps one or more oMath elements → display math
            $mathNodes = $localName === 'oMathPara'
                ? $xpath->query('m:oMath', $child)
                : null;

            if ($mathNodes && $mathNodes->length > 0) {
                foreach ($mathNodes as $mathNode) {
                    $latex   = tp_omml_to_latex($mathNode, $xpath);
                    $inner  .= "\n\\[{$latex}\\]\n";
                    $hasContent = true;
                }
            } else {
                // inline equation (m:oMath direct child of w:p via oMathRun, or namespace m:)
                $latex   = tp_omml_to_latex($child, $xpath);
                $inner  .= "\\({$latex}\\)";
                $hasContent = true;
            }
        }
    }

    // Also handle <m:oMath> children (different namespace URI)
    foreach ($p->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
            continue;
        }
        if ($child->localName === 'oMath'
            && $child->namespaceURI === 'http://schemas.openxmlformats.org/officeDocument/2006/math'
        ) {
            $latex   = tp_omml_to_latex($child, $xpath);
            $inner  .= "\\({$latex}\\)";
            $hasContent = true;
        }
    }

    if (!$hasContent) {
        return ''; // empty paragraph → skip
    }

    $inner = trim($inner);
    if ($headingLevel >= 1 && $headingLevel <= 6) {
        return "<h{$headingLevel}>{$inner}</h{$headingLevel}>\n";
    }
    return "<p>{$inner}</p>\n";
}

/**
 * Convert a <w:tbl> to an HTML <table>.
 */
function tp_docx_table_to_html(DOMElement $tbl, DOMXPath $xpath): string
{
    $html = '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;width:100%">' . "\n";
    $rows = $xpath->query('w:tr', $tbl);
    if (!$rows) {
        return '';
    }
    foreach ($rows as $row) {
        $html .= '<tr>';
        $cells = $xpath->query('w:tc', $row);
        if ($cells) {
            foreach ($cells as $cell) {
                $cellHtml = '';
                $paras    = $xpath->query('w:p', $cell);
                if ($paras) {
                    foreach ($paras as $para) {
                        $cellHtml .= tp_docx_paragraph_to_html($para, $xpath);
                    }
                }
                $html .= '<td>' . ($cellHtml !== '' ? $cellHtml : '&nbsp;') . '</td>';
            }
        }
        $html .= '</tr>' . "\n";
    }
    $html .= '</table>' . "\n";
    return $html;
}

// ---------------------------------------------------------------------------
// OMML → LaTeX converter
// ---------------------------------------------------------------------------

/**
 * Lightweight OMML (Office Math Markup Language) → LaTeX converter.
 *
 * Covers the most common constructs:
 *   m:f  (fraction), m:rad (radical/sqrt), m:sSup/m:sSub/m:sSubSup
 *   (superscript/subscript), m:nary (∑ ∫ ∏), m:func (sin, cos, …),
 *   m:eqArr (equation array), m:m (matrix), m:d (delimiters),
 *   m:r/m:t (plain text / operators).
 *
 * Unknown elements fall back to extracting their text content.
 */
function tp_omml_to_latex(DOMNode $node, DOMXPath $xpath): string
{
    if (!($node instanceof DOMElement)) {
        return htmlspecialchars($node->textContent, ENT_QUOTES, 'UTF-8');
    }

    $tag = $node->localName;

    // Helper: recursively convert children
    $children = function (DOMNode $n) use ($xpath): string {
        $out = '';
        foreach ($n->childNodes as $c) {
            $out .= tp_omml_to_latex($c, $xpath);
        }
        return $out;
    };

    // Helper: get first child element by local name
    $child = function (DOMNode $n, string $name) use ($xpath): ?DOMElement {
        foreach ($n->childNodes as $c) {
            if ($c instanceof DOMElement && $c->localName === $name) {
                return $c;
            }
        }
        return null;
    };

    switch ($tag) {
        // ── Root / container ────────────────────────────────────────────────
        case 'oMath':
        case 'oMathPara':
        case 'e':        // base element inside many constructs
            return $children($node);

        // ── Plain text / operator ────────────────────────────────────────────
        case 'r':
            // <m:r> may have <m:rPr> (run properties) – skip it, grab <m:t>
            $t = $child($node, 't');
            if ($t) {
                $text = $t->textContent;
                // Common operator aliases
                $opMap = [
                    '×' => '\times ', '÷' => '\div ', '±' => '\pm ',
                    '∞' => '\infty ', '∈' => '\in ', '∉' => '\notin ',
                    '≤' => '\leq ', '≥' => '\geq ', '≠' => '\neq ',
                    '≈' => '\approx ', '∑' => '\sum ', '∏' => '\prod ',
                    '∫' => '\int ', '√' => '\sqrt', '∂' => '\partial ',
                    'α' => '\alpha ', 'β' => '\beta ', 'γ' => '\gamma ',
                    'δ' => '\delta ', 'ε' => '\epsilon ', 'θ' => '\theta ',
                    'λ' => '\lambda ', 'μ' => '\mu ', 'π' => '\pi ',
                    'σ' => '\sigma ', 'φ' => '\phi ', 'ω' => '\omega ',
                    'Δ' => '\Delta ', 'Σ' => '\Sigma ', 'Ω' => '\Omega ',
                ];
                // Replace multi-char or single greek/special chars
                foreach ($opMap as $sym => $cmd) {
                    $text = str_replace($sym, $cmd, $text);
                }
                return $text;
            }
            return $children($node);

        case 't':
            return $node->textContent;

        // ── Fraction ─────────────────────────────────────────────────────────
        case 'f':
            $num = $child($node, 'num');
            $den = $child($node, 'den');
            $n   = $num ? $children($num) : '';
            $d   = $den ? $children($den) : '';
            return "\\frac{{$n}}{{$d}}";

        // ── Radical / sqrt ────────────────────────────────────────────────────
        case 'rad':
            $deg = $child($node, 'deg');
            $e   = $child($node, 'e');
            $base = $e ? $children($e) : '';
            if ($deg) {
                $degStr = trim($children($deg));
                if ($degStr !== '' && $degStr !== '2') {
                    return "\\sqrt[{$degStr}]{{$base}}";
                }
            }
            return "\\sqrt{{$base}}";

        // ── Superscript ───────────────────────────────────────────────────────
        case 'sSup':
            $eNode   = $child($node, 'e');
            $supNode = $child($node, 'sup');
            $base    = $eNode   ? $children($eNode)   : '';
            $sup     = $supNode ? $children($supNode) : '';
            return "{$base}^{{$sup}}";

        // ── Subscript ─────────────────────────────────────────────────────────
        case 'sSub':
            $eNode   = $child($node, 'e');
            $subNode = $child($node, 'sub');
            $base    = $eNode   ? $children($eNode)   : '';
            $sub     = $subNode ? $children($subNode) : '';
            return "{$base}_{{$sub}}";

        // ── Sub+Superscript ───────────────────────────────────────────────────
        case 'sSubSup':
            $eNode   = $child($node, 'e');
            $subNode = $child($node, 'sub');
            $supNode = $child($node, 'sup');
            $base    = $eNode   ? $children($eNode)   : '';
            $sub     = $subNode ? $children($subNode) : '';
            $sup     = $supNode ? $children($supNode) : '';
            return "{$base}_{{$sub}}^{{$sup}}";

        // ── N-ary (sum, integral, product) ────────────────────────────────────
        case 'nary':
            $chrNode = $xpath->query('m:naryPr/m:chr/@m:val', $node);
            $chr     = ($chrNode && $chrNode->length > 0) ? $chrNode->item(0)->nodeValue : '∑';
            $cmdMap  = ['∑' => '\sum', '∏' => '\prod', '∫' => '\int', '∬' => '\iint', '∭' => '\iiint'];
            $cmd     = $cmdMap[$chr] ?? '\sum';

            $subNode = $child($node, 'sub');
            $supNode = $child($node, 'sup');
            $eNode   = $child($node, 'e');
            $sub     = $subNode ? '_{'  . $children($subNode) . '}' : '';
            $sup     = $supNode ? '^{'  . $children($supNode) . '}' : '';
            $body    = $eNode   ? $children($eNode) : '';
            return "{$cmd}{$sub}{$sup} {$body}";

        // ── Function (sin, cos, lim …) ────────────────────────────────────────
        case 'func':
            $fnameNode = $child($node, 'fName');
            $eNode     = $child($node, 'e');
            $fname     = $fnameNode ? trim($children($fnameNode)) : '';
            $arg       = $eNode     ? $children($eNode)           : '';
            // Map common function names to LaTeX commands
            $fnMap = [
                'sin' => '\sin', 'cos' => '\cos', 'tan' => '\tan',
                'cot' => '\cot', 'sec' => '\sec', 'csc' => '\csc',
                'log' => '\log', 'ln'  => '\ln',  'exp' => '\exp',
                'lim' => '\lim', 'max' => '\max', 'min' => '\min',
                'det' => '\det', 'gcd' => '\gcd',
            ];
            $latexFn = $fnMap[strtolower($fname)] ?? "\\operatorname{" . addslashes($fname) . "}";
            return "{$latexFn}\\left({$arg}\\right)";

        // ── Delimiters (parentheses, brackets …) ──────────────────────────────
        case 'd':
            $prNode  = $child($node, 'dPr');
            $begChr  = '(';
            $endChr  = ')';
            if ($prNode) {
                $b = $xpath->query('m:begChr/@m:val', $prNode);
                $e2 = $xpath->query('m:endChr/@m:val', $prNode);
                if ($b && $b->length > 0) {
                    $begChr = $b->item(0)->nodeValue;
                }
                if ($e2 && $e2->length > 0) {
                    $endChr = $e2->item(0)->nodeValue;
                }
            }
            $delMap = [
                '(' => '\left(', ')' => '\right)',
                '[' => '\left[', ']' => '\right]',
                '{' => '\left\{', '}' => '\right\}',
                '|' => '\left|', '‖' => '\left\|',
                ''  => '\left.', // empty delimiter
            ];
            $latexBeg = $delMap[$begChr] ?? '\left' . $begChr;
            $latexEnd = $delMap[$endChr] ?? '\right' . $endChr;

            $inner2 = '';
            foreach ($node->childNodes as $c) {
                if ($c instanceof DOMElement && $c->localName === 'e') {
                    $inner2 .= $children($c);
                }
            }
            return "{$latexBeg}{$inner2}{$latexEnd}";

        // ── Matrix ────────────────────────────────────────────────────────────
        case 'm':
            $rows2 = [];
            foreach ($node->childNodes as $mr) {
                if ($mr instanceof DOMElement && $mr->localName === 'mr') {
                    $cols = [];
                    foreach ($mr->childNodes as $me) {
                        if ($me instanceof DOMElement && $me->localName === 'e') {
                            $cols[] = $children($me);
                        }
                    }
                    $rows2[] = implode(' & ', $cols);
                }
            }
            return "\\begin{pmatrix}" . implode(' \\\\ ', $rows2) . "\\end{pmatrix}";

        // ── Equation array (aligned equations) ────────────────────────────────
        case 'eqArr':
            $lines = [];
            foreach ($node->childNodes as $c) {
                if ($c instanceof DOMElement && $c->localName === 'e') {
                    $lines[] = $children($c);
                }
            }
            return "\\begin{aligned}" . implode(' \\\\ ', $lines) . "\\end{aligned}";

        // ── Group characters (overline, underline, …) ──────────────────────────
        case 'groupChr':
            $chrNode2 = $xpath->query('m:groupChrPr/m:chr/@m:val', $node);
            $eNode2   = $child($node, 'e');
            $base2    = $eNode2 ? $children($eNode2) : '';
            $chr2     = ($chrNode2 && $chrNode2->length > 0) ? $chrNode2->item(0)->nodeValue : '';
            $gcMap    = ['⏞' => '\overbrace', '⏟' => '\underbrace'];
            $gcCmd    = $gcMap[$chr2] ?? '\overline';
            return "{$gcCmd}{{$base2}}";

        // ── Accents (hat, tilde, …) ───────────────────────────────────────────
        case 'acc':
            $chrNode3 = $xpath->query('m:accPr/m:chr/@m:val', $node);
            $eNode3   = $child($node, 'e');
            $base3    = $eNode3 ? $children($eNode3) : '';
            $chr3     = ($chrNode3 && $chrNode3->length > 0) ? $chrNode3->item(0)->nodeValue : '^';
            $acMap    = [
                'ˆ' => '\hat',  '~' => '\tilde', '¯' => '\bar',
                '⃗' => '\vec', '̇' => '\dot',   '̈' => '\ddot',
            ];
            $acCmd    = $acMap[$chr3] ?? '\hat';
            return "{$acCmd}{{$base3}}";

        // ── Limit / lower-limit ───────────────────────────────────────────────
        case 'limLow':
            $eNode4   = $child($node, 'e');
            $limNode  = $child($node, 'lim');
            $base4    = $eNode4  ? $children($eNode4)  : '';
            $limStr   = $limNode ? $children($limNode) : '';
            return "\\underset{{$limStr}}{{$base4}}";

        case 'limUpp':
            $eNode5   = $child($node, 'e');
            $limNode5 = $child($node, 'lim');
            $base5    = $eNode5   ? $children($eNode5)   : '';
            $limStr5  = $limNode5 ? $children($limNode5) : '';
            return "\\overset{{$limStr5}}{{$base5}}";

        // ── Skip property nodes ────────────────────────────────────────────────
        case 'rPr':
        case 'fPr':
        case 'radPr':
        case 'sSupPr':
        case 'sSubPr':
        case 'sSubSupPr':
        case 'naryPr':
        case 'funcPr':
        case 'dPr':
        case 'mPr':
        case 'eqArrPr':
        case 'groupChrPr':
        case 'accPr':
        case 'ctrlPr':
            return '';

        // ── Fallback: recurse into children ───────────────────────────────────
        default:
            return $children($node);
    }
}

// ---------------------------------------------------------------------------
// PDF → HTML
// ---------------------------------------------------------------------------

/**
 * Extract text from a PDF file and return it as HTML.
 *
 * Strategy (tries in order until one succeeds):
 *   1. pdftotext (Poppler CLI) — fastest, handles most PDFs.
 *   2. smalot/pdfparser (Composer) — pure PHP fallback.
 *   3. Raw stream heuristic — last resort, grabs BT … ET text blocks.
 *
 * Unlike the original code, this function NEVER returns a silent placeholder;
 * it always extracts what it can so the caller can populate MODE OPÉRATOIRE.
 */
function tp_pdf_to_html(string $path): string
{
    // ── Strategy 1: pdftotext CLI (Poppler) ──────────────────────────────────
    $text = tp_pdf_via_pdftotext($path);

    // ── Strategy 2: smalot/pdfparser ─────────────────────────────────────────
    if ($text === '') {
        $text = tp_pdf_via_parser($path);
    }

    // ── Strategy 3: raw stream heuristic ─────────────────────────────────────
    if ($text === '') {
        $text = tp_pdf_via_raw_stream($path);
    }

    if ($text === '') {
        // Truly unextractable (scanned image PDF without OCR, encrypted, etc.)
        return '<p><em>Le contenu PDF n\'a pas pu être extrait automatiquement. '
             . 'Le fichier est peut-être scanné (image) ou protégé. '
             . 'Copiez le texte manuellement dans le champ Mode Opératoire.</em></p>';
    }

    return tp_text_to_html($text);
}

/**
 * Try pdftotext (Poppler). Returns extracted text or '' on failure.
 */
function tp_pdf_via_pdftotext(string $path): string
{
    // Check if binary is available
    exec('which pdftotext 2>/dev/null', $out, $code);
    if ($code !== 0 && !file_exists('/usr/bin/pdftotext') && !file_exists('/usr/local/bin/pdftotext')) {
        return '';
    }

    $safePath = escapeshellarg($path);
    $tmpOut   = tempnam(sys_get_temp_dir(), 'pdftext_') . '.txt';
    $safeTmp  = escapeshellarg($tmpOut);

    // -layout preserves spatial layout; -enc UTF-8 ensures proper encoding
    exec("pdftotext -layout -enc UTF-8 {$safePath} {$safeTmp} 2>/dev/null", $dummy, $ret);

    $text = '';
    if ($ret === 0 && file_exists($tmpOut)) {
        $text = file_get_contents($tmpOut);
        @unlink($tmpOut);
    }

    return is_string($text) ? trim($text) : '';
}

/**
 * Try smalot/pdfparser via Composer autoload. Returns extracted text or '' on failure.
 */
function tp_pdf_via_parser(string $path): string
{
    $autoload = ROOT_PATH . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        return '';
    }

    require_once $autoload;

    if (!class_exists(\Smalot\PdfParser\Parser::class)) {
        return '';
    }

    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($path);
        $text   = trim((string) $pdf->getText());
    } catch (Throwable $e) {
        return '';
    }

    return $text;
}

/**
 * Last-resort raw PDF stream parser.
 * Reads BT … ET blocks and extracts Tj / TJ operands (visible text).
 * Very approximate — only for desperate situations.
 */
function tp_pdf_via_raw_stream(string $path): string
{
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return '';
    }

    $text = '';

    // Find BT … ET blocks (text objects)
    if (preg_match_all('/BT\s+(.*?)\s+ET/s', $raw, $blocks)) {
        foreach ($blocks[1] as $block) {
            // Extract string operands for Tj and TJ operators
            // Tj: (text) Tj
            if (preg_match_all('/\(([^)]*)\)\s*Tj/s', $block, $tjMatches)) {
                foreach ($tjMatches[1] as $m) {
                    $text .= tp_pdf_decode_string($m) . ' ';
                }
            }
            // TJ: [(text) offset (text) …] TJ
            if (preg_match_all('/\[([^\]]*)\]\s*TJ/s', $block, $tjMatches2)) {
                foreach ($tjMatches2[1] as $m) {
                    if (preg_match_all('/\(([^)]*)\)/', $m, $parts)) {
                        foreach ($parts[1] as $part) {
                            $text .= tp_pdf_decode_string($part);
                        }
                    }
                    $text .= ' ';
                }
            }
            $text .= "\n";
        }
    }

    return trim($text);
}

/**
 * Decode basic PDF string escape sequences (octal, hex).
 */
function tp_pdf_decode_string(string $s): string
{
    // Octal escapes: \nnn
    $s = preg_replace_callback('/\\\\([0-7]{1,3})/', function ($m) {
        return chr(octdec($m[1]));
    }, $s) ?? $s;

    // Standard escape sequences
    $s = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'],
                     ["\n",  "\r",  "\t",  '(',   ')',   '\\'], $s);

    return $s;
}

// ---------------------------------------------------------------------------
// Shared text → HTML
// ---------------------------------------------------------------------------

/**
 * Convert plain text (multi-paragraph) to HTML <p> blocks.
 * Used by both the Word fallback and the PDF extractor.
 */
function tp_text_to_html(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    // Split on blank lines → paragraphs
    $parts = preg_split("/\R{2,}/u", $text) ?: [$text];
    $html  = '';
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') {
            $html .= '<p>' . nl2br(htmlspecialchars($p, ENT_QUOTES, 'UTF-8')) . '</p>' . "\n";
        }
    }
    return $html;
}

function tp_plain_to_html(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (strpos($text, '<') !== false) {
        return tp_sanitize_rich_text($text);
    }
    return tp_text_to_html($text);
}
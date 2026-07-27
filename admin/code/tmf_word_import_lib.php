<?php

/**
 * Safe, self-contained DOCX parser for the TMFCBT question template.
 *
 * This is an original implementation. It only reads OOXML parts from DOCX
 * archives and never executes macros, embedded objects or external content.
 */

class TmfWordImportException extends Exception {}

const TMF_WORD_IMPORT_PREVIEW_TTL = 86_400;

function F_tmf_word_import_is_batch_id(string $batch_id): bool
{
    return preg_match('/^[a-f0-9]{32}$/', $batch_id) === 1;
}

/**
 * Remove a preview and its extracted media. Confirmed imports must use
 * $remove_media=false because their question HTML references these files.
 */
function F_tmf_word_import_cleanup_batch(string $cache_directory, string $batch_id, bool $remove_media = true): bool
{
    if (!F_tmf_word_import_is_batch_id($batch_id)) {
        return false;
    }

    $cache_directory = rtrim($cache_directory, '/\\');
    $preview_file = $cache_directory . '/wordimport-preview/' . $batch_id . '.php';
    if (is_file($preview_file) || is_link($preview_file)) {
        unlink($preview_file);
    }

    if ($remove_media) {
        F_tmf_word_import_remove_directory($cache_directory . '/wordimport/' . $batch_id);
    }

    return true;
}

/**
 * Remove abandoned previews and only the media directories tied to them.
 */
function F_tmf_word_import_cleanup_stale(
    string $cache_directory,
    int $maximum_age = TMF_WORD_IMPORT_PREVIEW_TTL,
    ?int $now = null,
): int {
    $preview_directory = rtrim($cache_directory, '/\\') . '/wordimport-preview';
    if (!is_dir($preview_directory)) {
        return 0;
    }

    $now ??= time();
    $removed = 0;
    $entries = scandir($preview_directory);
    if ($entries === false) {
        return 0;
    }

    foreach ($entries as $entry) {
        $matches = [];
        if (preg_match('/^([a-f0-9]{32})\.php$/', $entry, $matches) !== 1 || !isset($matches[1])) {
            continue;
        }
        $preview_file = $preview_directory . '/' . $entry;
        $modified = filemtime($preview_file);
        if ($modified !== false && ($now - $modified) > $maximum_age) {
            F_tmf_word_import_cleanup_batch($cache_directory, $matches[1]);
            ++$removed;
        }
    }

    return $removed;
}

function F_tmf_word_import_remove_directory(string $directory): void
{
    if (is_link($directory)) {
        unlink($directory);
        return;
    }
    if (!is_dir($directory)) {
        return;
    }

    $entries = scandir($directory);
    if ($entries === false) {
        return;
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $directory . '/' . $entry;
        if (is_link($path) || is_file($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            F_tmf_word_import_remove_directory($path);
        }
    }
    rmdir($directory);
}

class TmfWordImporter
{
    private string $filename;
    private ZipArchive $zip;
    private DOMDocument $document;
    private DOMXPath $xpath;
    /** @var array<string, array{target: string, external: bool, type: string}> */
    private array $relationships = [];
    private string $media_directory;
    private string $media_url;
    /** @var array<string, string> */
    private array $extracted_media = [];
    /** @var list<string> */
    private array $warnings = [];

    const MAX_ENTRIES = 5000;
    const MAX_UNCOMPRESSED_BYTES = 104857600;
    const MAX_DOCUMENT_BYTES = 20971520;

    public function __construct(string $filename, string $media_directory = '', string $media_url = '')
    {
        $this->filename = $filename;
        $this->media_directory = rtrim($media_directory, '/');
        $this->media_url = rtrim($media_url, '/');
    }

    public function parse(): array
    {
        $this->openArchive();
        try {
            $this->loadRelationships();
            $this->loadDocument();

            $blocks = $this->readBodyBlocks();
            $result = $this->parseTemplateBlocks($blocks);
            $result['warnings'] = array_values(array_unique($this->warnings));
            $result['statistics'] = array(
                'blocks' => count($blocks),
                'questions' => count($result['questions']),
                'images' => count($this->extracted_media),
            );
            return $result;
        } finally {
            $this->zip->close();
        }
    }

    private function openArchive(): void
    {
        if (!class_exists(ZipArchive::class) || !class_exists(DOMDocument::class)) {
            throw new TmfWordImportException('DOCX import requires the PHP zip and dom extensions.');
        }
        if (!is_file($this->filename) || !is_readable($this->filename)) {
            throw new TmfWordImportException('DOCX file is not readable.');
        }
        if (filesize($this->filename) > self::MAX_DOCUMENT_BYTES) {
            throw new TmfWordImportException('DOCX file is larger than 20 MB.');
        }
        $signature = file_get_contents($this->filename, false, null, 0, 4);
        if (substr($signature, 0, 2) !== 'PK') {
            throw new TmfWordImportException('The uploaded file is not a DOCX/ZIP archive.');
        }

        $this->zip = new ZipArchive();
        if ($this->zip->open($this->filename) !== true) {
            throw new TmfWordImportException('Unable to open the DOCX archive.');
        }
        if ($this->zip->numFiles > self::MAX_ENTRIES) {
            throw new TmfWordImportException('DOCX contains too many archive entries.');
        }

        $total_size = 0;
        for ($i = 0; $i < $this->zip->numFiles; ++$i) {
            $stat = $this->zip->statIndex($i);
            $name = str_replace('\\', '/', $stat['name']);
            if ($name === '' || $name[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $name)) {
                throw new TmfWordImportException('DOCX contains an unsafe archive path.');
            }
            $total_size += (int) $stat['size'];
            if ($total_size > self::MAX_UNCOMPRESSED_BYTES) {
                throw new TmfWordImportException('DOCX expands beyond the 100 MB safety limit.');
            }
        }
        if ($this->zip->locateName('word/document.xml') === false) {
            throw new TmfWordImportException('word/document.xml is missing.');
        }
    }

    private function loadRelationships(): void
    {
        $xml = $this->zip->getFromName('word/_rels/document.xml.rels');
        if ($xml === false) {
            return;
        }
        $dom = $this->loadXml($xml, 'document relationships');
        foreach ($dom->documentElement->childNodes as $node) {
            if (!$node instanceof DOMElement || $node->localName !== 'Relationship') {
                continue;
            }
            $id = $node->getAttribute('Id');
            $target = str_replace('\\', '/', $node->getAttribute('Target'));
            $mode = $node->getAttribute('TargetMode');
            $type = $node->getAttribute('Type');
            if ($mode === 'External' && strpos($type, '/hyperlink') === false) {
                $this->warnings[] = 'External non-hyperlink relationship was ignored.';
                continue;
            }
            $this->relationships[$id] = array(
                'target' => $target,
                'external' => $mode === 'External',
                'type' => $type,
            );
        }
    }

    private function loadDocument(): void
    {
        $xml = $this->zip->getFromName('word/document.xml');
        if ($xml === false) {
            throw new TmfWordImportException('word/document.xml is missing.');
        }
        $this->document = $this->loadXml($xml, 'Word document');
        $this->xpath = new DOMXPath($this->document);
        $this->xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $this->xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $this->xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $this->xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/officeDocument/2006/math');
        $this->xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');
    }

    private function loadXml(string $xml, string $label): DOMDocument
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok) {
            throw new TmfWordImportException('Invalid XML in ' . $label . '.');
        }
        return $dom;
    }

    private function readBodyBlocks(): array
    {
        $body = $this->xpath->query('/w:document/w:body')->item(0);
        if (!$body) {
            throw new TmfWordImportException('DOCX body is missing.');
        }
        $blocks = array();
        foreach ($body->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            if ($child->localName === 'p') {
                $blocks[] = $this->paragraphBlock($child);
            } elseif ($child->localName === 'tbl') {
                if ($this->nodeContainsTemplateMarkers($child)) {
                    $this->flattenMarkerTable($child, $blocks);
                } else {
                    $blocks[] = $this->tableBlock($child);
                }
            }
        }
        return $blocks;
    }

    private function nodeContainsTemplateMarkers(DOMElement $node): bool
    {
        $text = $this->plainText($node);
        return (bool) preg_match('/(^|[\r\n])\s*(?:MODULE:=|TOPIC:=|Q:\s*\d+\)|[A-Z]\s*:\)|RIGHT:)/iu', $text);
    }

    private function flattenMarkerTable(DOMElement $table, array &$blocks): void
    {
        foreach ($this->xpath->query('./w:tr', $table) as $row) {
            foreach ($this->xpath->query('./w:tc', $row) as $cell) {
                if ($this->isVerticalMergeContinuation($cell)) {
                    continue;
                }
                foreach ($cell->childNodes as $child) {
                    if (!$child instanceof DOMElement) {
                        continue;
                    }
                    if ($child->localName === 'p') {
                        $blocks[] = $this->paragraphBlock($child);
                    } elseif ($child->localName === 'tbl') {
                        if ($this->nodeContainsTemplateMarkers($child)) {
                            $this->flattenMarkerTable($child, $blocks);
                        } else {
                            $blocks[] = $this->tableBlock($child);
                        }
                    }
                }
            }
        }
    }

    private function paragraphBlock(DOMElement $paragraph): array
    {
        $plain = $this->plainText($paragraph);
        $style = array();
        $alignment = $this->xpath->query('./w:pPr/w:jc', $paragraph)->item(0);
        if ($alignment instanceof DOMElement) {
            $value = $this->wordAttribute($alignment, 'val');
            if (in_array($value, array('left', 'right', 'center', 'justify'), true)) {
                $style[] = 'text-align:' . $value;
            }
        }
        $direction = $this->xpath->query('./w:pPr/w:bidi', $paragraph)->item(0);
        if ($direction) {
            $style[] = 'direction:rtl';
        }

        $inner = '';
        foreach ($paragraph->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            if ($child->localName === 'r') {
                $inner .= $this->runHtml($child);
            } elseif ($child->localName === 'hyperlink') {
                $inner .= $this->hyperlinkHtml($child);
            } elseif (
                $child->namespaceURI === 'http://schemas.openxmlformats.org/officeDocument/2006/math'
                && in_array($child->localName, array('oMath', 'oMathPara'), true)
            ) {
                $inner .= $this->mathHtml($child);
            }
        }
        $style_attribute = empty($style)
            ? ''
            : ' style="' . htmlspecialchars(implode(';', $style), ENT_QUOTES, 'UTF-8') . '"';
        return array(
            'plain' => trim($plain),
            'html' => '<div' . $style_attribute . '>' . $inner . '</div>',
            'kind' => 'paragraph',
        );
    }

    private function hyperlinkHtml(DOMElement $hyperlink): string
    {
        $inner = '';
        foreach ($this->xpath->query('./w:r', $hyperlink) as $run) {
            $inner .= $this->runHtml($run);
        }
        $id = $this->relationshipId($hyperlink);
        if (!$id || !isset($this->relationships[$id])) {
            return $inner;
        }
        $rel = $this->relationships[$id];
        if (!$rel['external'] || !preg_match('#^https?://#i', $rel['target'])) {
            return $inner;
        }
        return (
            '<a href="'
            . htmlspecialchars($rel['target'], ENT_QUOTES, 'UTF-8')
            . '" rel="noopener noreferrer">'
            . $inner
            . '</a>'
        );
    }

    private function runHtml(DOMElement $run): string
    {
        $content = '';
        foreach ($run->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            if ($child->localName === 't' || $child->localName === 'instrText') {
                $content .= $this->escapeText($child->textContent);
            } elseif ($child->localName === 'tab') {
                $content .= '&emsp;';
            } elseif (in_array($child->localName, array('br', 'cr'), true)) {
                $content .= '<br />';
            } elseif ($child->localName === 'drawing' || $child->localName === 'pict') {
                $content .= $this->imageHtml($child);
            } elseif (
                $child->namespaceURI === 'http://schemas.openxmlformats.org/officeDocument/2006/math'
                && in_array($child->localName, array('oMath', 'oMathPara'), true)
            ) {
                $content .= $this->mathHtml($child);
            }
        }
        if ($content === '') {
            return '';
        }

        $properties = $this->xpath->query('./w:rPr', $run)->item(0);
        if (!$properties) {
            return $content;
        }
        if ($this->xpath->query('./w:b[not(@w:val="0") and not(@w:val="false")]', $properties)->length) {
            $content = '<strong>' . $content . '</strong>';
        }
        if ($this->xpath->query('./w:i[not(@w:val="0") and not(@w:val="false")]', $properties)->length) {
            $content = '<em>' . $content . '</em>';
        }
        if ($this->xpath->query('./w:u[not(@w:val="none")]', $properties)->length) {
            $content = '<u>' . $content . '</u>';
        }
        $styles = array();
        $color = $this->xpath->query('./w:color', $properties)->item(0);
        if ($color instanceof DOMElement) {
            $value = $this->wordAttribute($color, 'val');
            if (preg_match('/^[0-9A-F]{6}$/i', $value)) {
                $styles[] = 'color:#' . $value;
            }
        }
        $highlight = $this->xpath->query('./w:highlight', $properties)->item(0);
        if ($highlight instanceof DOMElement) {
            $value = $this->wordAttribute($highlight, 'val');
            $map = array(
                'yellow' => '#ffff00',
                'green' => '#00ff00',
                'cyan' => '#00ffff',
                'magenta' => '#ff00ff',
                'blue' => '#0000ff',
                'red' => '#ff0000',
                'darkBlue' => '#000080',
                'darkRed' => '#800000',
                'darkGreen' => '#008000',
                'lightGray' => '#d3d3d3',
                'darkGray' => '#808080',
            );
            if (isset($map[$value])) {
                $styles[] = 'background-color:' . $map[$value];
            }
        }
        $shade = $this->xpath->query('./w:shd', $properties)->item(0);
        if ($shade instanceof DOMElement) {
            $fill = $this->wordAttribute($shade, 'fill');
            if (preg_match('/^[0-9A-F]{6}$/i', $fill)) {
                $styles[] = 'background-color:#' . $fill;
            }
        }
        if (!empty($styles)) {
            $content =
                '<span style="'
                . htmlspecialchars(implode(';', $styles), ENT_QUOTES, 'UTF-8')
                . '">'
                . $content
                . '</span>';
        }
        return $content;
    }

    private function imageHtml(DOMElement $container): string
    {
        $blip = $this->xpath->query('.//a:blip', $container)->item(0);
        if (!$blip instanceof DOMElement) {
            return '';
        }
        $id = $this->relationshipId($blip);
        if (!$id || !isset($this->relationships[$id])) {
            return '';
        }
        $rel = $this->relationships[$id];
        if ($rel['external']) {
            $this->warnings[] = 'An externally linked image was ignored.';
            return '';
        }
        if (isset($this->extracted_media[$id])) {
            return (
                '<img src="'
                . htmlspecialchars($this->extracted_media[$id], ENT_QUOTES, 'UTF-8')
                . '" alt="" style="max-width:100%;height:auto" />'
            );
        }
        if ($this->media_directory === '' || $this->media_url === '') {
            $this->warnings[] = 'An embedded image was found but no media output directory was configured.';
            return '';
        }

        $target = ltrim(preg_replace('#^(\.\./)+#', '', $rel['target']), '/');
        $entry = strpos($target, 'word/') === 0 ? $target : 'word/' . $target;
        if (strpos($entry, 'word/media/') !== 0) {
            $this->warnings[] = 'An image relationship outside word/media was ignored.';
            return '';
        }
        $bytes = $this->zip->getFromName($entry);
        if ($bytes === false) {
            $this->warnings[] = 'An embedded image could not be extracted.';
            return '';
        }
        set_error_handler(static fn(): bool => true);
        try {
            $image_info = getimagesizefromstring($bytes);
        } finally {
            restore_error_handler();
        }
        $allowed = array(IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif');
        if (!$image_info || !isset($allowed[$image_info[2]])) {
            $this->warnings[] = 'A non-JPEG/PNG/GIF image was ignored for safety.';
            return '';
        }
        if (
            !is_dir($this->media_directory)
            && !mkdir($this->media_directory, 0750, true)
            && !is_dir($this->media_directory)
        ) {
            throw new TmfWordImportException('Unable to create the Word import media directory.');
        }
        $basename = hash('sha256', $bytes) . '.' . $allowed[$image_info[2]];
        $destination = $this->media_directory . '/' . $basename;
        if (!is_file($destination) && file_put_contents($destination, $bytes, LOCK_EX) === false) {
            throw new TmfWordImportException('Unable to save an embedded image.');
        }
        $url = $this->media_url . '/' . $basename;
        $this->extracted_media[$id] = $url;
        return (
            '<img src="'
            . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            . '" alt="" style="max-width:100%;height:auto" />'
        );
    }

    private function tableBlock(DOMElement $table): array
    {
        $rows = array();
        foreach ($this->xpath->query('./w:tr', $table) as $row) {
            $cells = array();
            $column = 0;
            foreach ($this->xpath->query('./w:tc', $row) as $cell) {
                $span = $this->cellGridSpan($cell);
                $cells[] = array(
                    'node' => $cell,
                    'column' => $column,
                    'span' => $span,
                    'continue' => $this->isVerticalMergeContinuation($cell),
                    'restart' => $this->isVerticalMergeRestart($cell),
                );
                $column += $span;
            }
            $rows[] = $cells;
        }

        $html = '<table style="border-collapse:collapse;max-width:100%"><tbody>';
        foreach ($rows as $row_index => $cells) {
            $html .= '<tr>';
            foreach ($cells as $cell) {
                if ($cell['continue']) {
                    continue;
                }
                $attributes = '';
                if ($cell['span'] > 1) {
                    $attributes .= ' colspan="' . $cell['span'] . '"';
                }
                if ($cell['restart']) {
                    $rowspan = $this->verticalRowspan($rows, $row_index, $cell['column']);
                    if ($rowspan > 1) {
                        $attributes .= ' rowspan="' . $rowspan . '"';
                    }
                }
                $html .= '<td' . $attributes . ' style="border:1px solid #777;padding:4px;vertical-align:top">';
                foreach ($cell['node']->childNodes as $child) {
                    if (!$child instanceof DOMElement) {
                        continue;
                    }
                    if ($child->localName === 'p') {
                        $block = $this->paragraphBlock($child);
                        $html .= $block['html'];
                    } elseif ($child->localName === 'tbl') {
                        $html .= $this->tableBlock($child)['html'];
                    }
                }
                $html .= '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return array(
            'plain' => trim($this->plainText($table)),
            'html' => $html,
            'kind' => 'table',
        );
    }

    private function cellGridSpan(DOMElement $cell): int
    {
        $span = $this->xpath->query('./w:tcPr/w:gridSpan', $cell)->item(0);
        if ($span instanceof DOMElement) {
            return max(1, (int) $this->wordAttribute($span, 'val'));
        }
        return 1;
    }

    private function isVerticalMergeContinuation(DOMElement $cell): bool
    {
        $merge = $this->xpath->query('./w:tcPr/w:vMerge', $cell)->item(0);
        if (!$merge instanceof DOMElement) {
            return false;
        }
        $value = $this->wordAttribute($merge, 'val');
        return $value === '' || $value === 'continue';
    }

    private function isVerticalMergeRestart(DOMElement $cell): bool
    {
        $merge = $this->xpath->query('./w:tcPr/w:vMerge', $cell)->item(0);
        return $merge instanceof DOMElement && $this->wordAttribute($merge, 'val') === 'restart';
    }

    private function verticalRowspan(array $rows, int $start_row, int $column): int
    {
        $span = 1;
        for ($r = $start_row + 1; $r < count($rows); ++$r) {
            $found = false;
            foreach ($rows[$r] as $cell) {
                if ($cell['column'] === $column && $cell['continue']) {
                    ++$span;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                break;
            }
        }
        return $span;
    }

    private function mathHtml(DOMElement $math): string
    {
        return '<math xmlns="http://www.w3.org/1998/Math/MathML">' . $this->mathNode($math) . '</math>';
    }

    private function mathNode(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return $this->escapeText($node->nodeValue);
        }
        if (!$node instanceof DOMElement) {
            return '';
        }
        $children = '';
        foreach ($node->childNodes as $child) {
            $children .= $this->mathNode($child);
        }
        switch ($node->localName) {
            case 't':
                $text = trim($node->textContent);
                if ($text === '') {
                    return '';
                }
                if (preg_match('/^[0-9.,]+$/u', $text)) {
                    return '<mn>' . $this->escapeText($text) . '</mn>';
                }
                if (preg_match('/^[+\-*\/=<>≤≥±×÷()]+$/u', $text)) {
                    return '<mo>' . $this->escapeText($text) . '</mo>';
                }
                return '<mi>' . $this->escapeText($text) . '</mi>';
            case 'f':
                $num = $this->firstMathChild($node, 'num');
                $den = $this->firstMathChild($node, 'den');
                return '<mfrac>' . $this->mathNode($num) . $this->mathNode($den) . '</mfrac>';
            case 'sSup':
                return (
                    '<msup>'
                    . $this->mathNode($this->firstMathChild($node, 'e'))
                    . $this->mathNode($this->firstMathChild($node, 'sup'))
                    . '</msup>'
                );
            case 'sSub':
                return (
                    '<msub>'
                    . $this->mathNode($this->firstMathChild($node, 'e'))
                    . $this->mathNode($this->firstMathChild($node, 'sub'))
                    . '</msub>'
                );
            case 'sSubSup':
                return (
                    '<msubsup>'
                    . $this->mathNode($this->firstMathChild($node, 'e'))
                    . $this->mathNode($this->firstMathChild($node, 'sub'))
                    . $this->mathNode($this->firstMathChild($node, 'sup'))
                    . '</msubsup>'
                );
            case 'rad':
                return '<msqrt>' . $this->mathNode($this->firstMathChild($node, 'e')) . '</msqrt>';
            case 'd':
                return '<mfenced>' . $this->mathNode($this->firstMathChild($node, 'e')) . '</mfenced>';
            case 'oMathPara':
            case 'oMath':
                return '<mrow>' . $children . '</mrow>';
            case 'r':
            case 'e':
            case 'num':
            case 'den':
            case 'sup':
            case 'sub':
                return '<mrow>' . $children . '</mrow>';
            default:
                return $children;
        }
    }

    private function firstMathChild(DOMElement $node, string $local_name): DOMElement
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $local_name) {
                return $child;
            }
        }
        return $node;
    }

    private function parseTemplateBlocks(array $blocks): array
    {
        $result = array(
            'module' => '',
            'topic' => '',
            'questions' => array(),
        );
        $question = null;
        $active_answer = null;

        foreach ($blocks as $block) {
            $plain = trim($block['plain']);
            if ($plain === '') {
                continue;
            }
            if (preg_match('/^\s*MODULE:=\s*(.+)$/iu', $plain, $match)) {
                $result['module'] = trim($match[1]);
                continue;
            }
            if (preg_match('/^\s*TOPIC:=\s*(.+)$/iu', $plain, $match)) {
                $result['topic'] = trim($match[1]);
                continue;
            }
            if (preg_match('/^\s*Q:\s*(\d+)\)\s*/iu', $plain, $match)) {
                if ($question !== null) {
                    $this->finalizeQuestion($question);
                    $result['questions'][] = $question;
                }
                $prefix = $match[0];
                $question = array(
                    'source_number' => (int) $match[1],
                    'description' => $this->stripHtmlTextPrefix($block['html'], mb_strlen($prefix, 'UTF-8')),
                    'answers' => array(),
                    'right_keys' => array(),
                    'difficulty' => 1,
                    'timer' => 0,
                    'auto_next' => 0,
                    'fullscreen' => 0,
                    'inline_answers' => 0,
                    'mcma_checkbox' => false,
                    'mcma_header' => array(),
                    'max_sel' => 0,
                    'short_answer' => false,
                );
                $active_answer = null;
                continue;
            }
            if ($question === null) {
                continue;
            }
            if (preg_match('/^\s*([A-Z])\s*:\)\s*/u', $plain, $match)) {
                $key = strtoupper($match[1]);
                $prefix = $match[0];
                $answer = array(
                    'key' => $key,
                    'description' => $this->stripHtmlTextPrefix($block['html'], mb_strlen($prefix, 'UTF-8')),
                    'weight' => null,
                );
                $question['answers'][$key] = $answer;
                $active_answer = $key;
                continue;
            }
            if (preg_match('/^\s*RIGHT:\s*(.*)$/iu', $plain, $match)) {
                $keys = preg_split('/[\s,;]+/', strtoupper(trim($match[1])), -1, PREG_SPLIT_NO_EMPTY);
                $question['right_keys'] = array_values(array_unique($keys));
                $active_answer = null;
                continue;
            }
            if ($active_answer !== null) {
                $question['answers'][$active_answer]['description'] .= $block['html'];
            } else {
                $question['description'] .= $block['html'];
            }
        }
        if ($question !== null) {
            $this->finalizeQuestion($question);
            $result['questions'][] = $question;
        }

        if ($result['module'] === '') {
            throw new TmfWordImportException('MODULE:= marker was not found.');
        }
        if ($result['topic'] === '') {
            throw new TmfWordImportException('TOPIC:= marker was not found.');
        }
        if (empty($result['questions'])) {
            throw new TmfWordImportException('No Q:n) question markers were found.');
        }
        return $result;
    }

    private function finalizeQuestion(array &$question): void
    {
        $plain = $this->htmlPlainText($question['description']);
        if (preg_match('/\[\[TMF_CHECKBOX\]\]/iu', $plain)) {
            $question['mcma_checkbox'] = true;
            $question['description'] = $this->removeHtmlMarker($question['description'], '[[TMF_CHECKBOX]]');
        }
        if (preg_match('/\[\[SHORT_ANSWER\]\]/iu', $plain)) {
            $question['short_answer'] = true;
            $question['description'] = $this->removeHtmlMarker($question['description'], '[[SHORT_ANSWER]]');
        }
        if (preg_match('/\[\[MCMA_HEADER:=([^\]]+)\]\]/iu', $plain, $match)) {
            $question['mcma_header'] = array_map('trim', explode(',', $match[1]));
            $question['description'] = $this->removeHtmlMarker($question['description'], $match[0]);
        }
        if (preg_match('/\[\[MAX_SEL=(\d+)\]\]/iu', $plain, $match)) {
            $question['max_sel'] = max(0, (int) $match[1]);
            if ($question['max_sel'] > 0) {
                $question['mcma_checkbox'] = true;
            }
            $question['description'] = $this->removeHtmlMarker($question['description'], $match[0]);
        }
        if (preg_match('/\[\[DIFFICULTY=(\d+)\]\]/iu', $plain, $match)) {
            $question['difficulty'] = max(1, min(5, (int) $match[1]));
            $question['description'] = $this->removeHtmlMarker($question['description'], $match[0]);
        }
        if (preg_match('/\[\[TIMER=(\d+)\]\]/iu', $plain, $match)) {
            $question['timer'] = max(0, min(32767, (int) $match[1]));
            $question['description'] = $this->removeHtmlMarker($question['description'], $match[0]);
        }
        if (preg_match('/\[\[AUTO_NEXT(?:=1)?\]\]/iu', $plain, $match)) {
            $question['auto_next'] = 1;
            $question['description'] = $this->removeHtmlMarker($question['description'], $match[0]);
        }

        foreach ($question['answers'] as &$answer) {
            $answer_plain = $this->htmlPlainText($answer['description']);
            if (preg_match('/\[\[WEIGHT=(-?\d+)\]\]/iu', $answer_plain, $match)) {
                // Keep imported data portable across all supported databases.
                $answer['weight'] = max(0, min(100, (int) $match[1]));
                $answer['description'] = $this->removeHtmlMarker($answer['description'], $match[0]);
            }
        }
        unset($answer);

        $answer_count = count($question['answers']);
        $right_count = count($question['right_keys']);
        if ($answer_count === 0) {
            $question['type'] = 3;
        } elseif ($question['short_answer'] || $answer_count === 1) {
            $question['type'] = 3;
        } elseif ($right_count === 0) {
            $question['type'] = 4;
        } elseif ($right_count > 1 || $question['mcma_checkbox']) {
            $question['type'] = 2;
        } else {
            $question['type'] = 1;
        }

        if ($question['type'] === 3 && $answer_count > 0) {
            foreach ($question['answers'] as &$short_answer) {
                // TCExam compares short-answer keys directly with the submitted
                // text, so keys must not contain paragraph/span HTML.
                $short_answer['description'] = trim($this->htmlPlainText($short_answer['description']));
            }
            unset($short_answer);
        }

        $metadata = '';
        if ($question['mcma_checkbox']) {
            $metadata .= '<!--TMF_CHECKBOX-->';
        }
        if (!empty($question['mcma_header'])) {
            $metadata .=
                '<!--TMF_MCMA_HEADER:'
                . htmlspecialchars(json_encode($question['mcma_header']), ENT_QUOTES, 'UTF-8')
                . '-->';
        }
        if ($question['max_sel'] > 0) {
            $metadata .= '<!--TMF_MAX_SEL:' . $question['max_sel'] . '-->';
        }
        $question['description'] = $metadata . trim($question['description']);
        $question['answers'] = array_values($question['answers']);
    }

    private function stripHtmlTextPrefix(string $html, int $characters): string
    {
        if ($characters <= 0) {
            return $html;
        }
        $dom = $this->loadHtmlFragment($html);
        $xpath = new DOMXPath($dom);
        $remaining = $characters;
        foreach ($xpath->query('//body/div//text()') as $text_node) {
            if ($remaining <= 0) {
                break;
            }
            $length = mb_strlen($text_node->nodeValue, 'UTF-8');
            if ($length <= $remaining) {
                $remaining -= $length;
                $text_node->nodeValue = '';
            } else {
                $text_node->nodeValue = mb_substr($text_node->nodeValue, $remaining, null, 'UTF-8');
                $remaining = 0;
            }
        }
        return $this->bodyInnerHtml($dom);
    }

    private function removeHtmlMarker(string $html, string $marker): string
    {
        $dom = $this->loadHtmlFragment($html);
        $xpath = new DOMXPath($dom);
        $text_nodes = $xpath->query('//body//text()');
        foreach ($text_nodes as $node) {
            if (stripos($node->nodeValue, $marker) !== false) {
                $node->nodeValue = str_ireplace($marker, '', $node->nodeValue);
                return $this->bodyInnerHtml($dom);
            }
        }
        // The marker can be split across Word runs. In that case, removing it
        // from the plain representation is safer than leaving a visible token.
        return preg_replace('/' . preg_quote($marker, '/') . '/iu', '', $html);
    }

    private function loadHtmlFragment(string $html): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>',
            LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $dom;
    }

    private function bodyInnerHtml(DOMDocument $dom): string
    {
        $body = $dom->getElementsByTagName('body')->item(0);
        $html = '';
        if ($body) {
            foreach ($body->childNodes as $child) {
                $html .= $dom->saveHTML($child);
            }
        }
        return $html;
    }

    private function htmlPlainText(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function plainText(DOMElement $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text .= $child->nodeValue;
            } elseif ($child instanceof DOMElement) {
                if ($child->namespaceURI === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main') {
                    if (in_array($child->localName, array('t', 'instrText'), true)) {
                        $text .= $child->textContent;
                        continue;
                    }
                    if ($child->localName === 'tab') {
                        $text .= "\t";
                        continue;
                    }
                    if (in_array($child->localName, array('br', 'cr'), true)) {
                        $text .= "\n";
                        continue;
                    }
                }
                $text .= $this->plainText($child);
                if (in_array($child->localName, array('p', 'tr', 'tc'), true)) {
                    $text .= "\n";
                }
            }
        }
        return $text;
    }

    private function escapeText(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = preg_replace_callback(
            '/ {2,}/',
            function ($match) {
                return str_repeat('&nbsp;', strlen($match[0]) - 1) . ' ';
            },
            $escaped,
        );
        return $escaped;
    }

    private function wordAttribute(DOMElement $element, string $name): string
    {
        return $element->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', $name);
    }

    private function relationshipId(DOMElement $element): string
    {
        return (
            $element->getAttributeNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                'id',
            ) ?: $element->getAttributeNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                'embed',
            )
        );
    }
}

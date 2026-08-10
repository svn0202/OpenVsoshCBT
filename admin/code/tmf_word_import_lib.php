<?php

/**
 * Safe, self-contained DOCX parser for the TMFCBT question template.
 *
 * This is an original implementation. It only reads OOXML parts from DOCX
 * archives and never executes macros, embedded objects or external content.
 */

require_once __DIR__ . '/TmfWordImportException.php';

const TMF_WORD_IMPORT_PREVIEW_TTL = 86_400;

/**
 * Build the canonical Word-import template offered by the admin interface.
 *
 * @throws TmfWordImportException
 */
function f_tmf_word_import_template(): string
{
    if (!class_exists(ZipArchive::class)) {
        throw new TmfWordImportException('Для создания шаблона требуется расширение ZIP.');
    }
    $temporary = tempnam(sys_get_temp_dir(), 'openvsosh-word-template-');
    if ($temporary === false) {
        throw new TmfWordImportException('Не удалось создать временный файл шаблона.');
    }

    $paragraphs = [
        'MODULE:=Тестовый модуль',
        'TOPIC:=Тестовая тема',
        'Q:1) Одиночный выбор [[DIFFICULTY=2]]',
        'A:) Правильный вариант',
        'B:) Неправильный вариант',
        'RIGHT:A',
        'Q:2) Множественный выбор [[TMF_CHECKBOX]] [[MAX_SEL=2]]',
        'A:) Первый правильный вариант',
        'B:) Неправильный вариант',
        'C:) Второй правильный вариант',
        'RIGHT:A,C',
        'Q:3) [[SHORT_ANSWER]] [[SIMILARITY=85]] Краткий ответ',
        'A:) Екатеринбург',
        'B:) Свердловск [[WEIGHT=50]]',
        'RIGHT:A,B',
        'Q:4) Расставьте по порядку',
        'A:) Первый',
        'B:) Второй',
        'C:) Третий',
        'Q:5) [[MATCHING]] Установите соответствие',
        'A:) Первая строка',
        'B:) Вторая строка',
        'C:) Третья строка',
        'Q:6) Развёрнутый ответ [[TIMER=300]]',
    ];
    $body = '';
    foreach ($paragraphs as $paragraph) {
        $body .= '<w:p><w:r><w:t xml:space="preserve">'
            . htmlspecialchars($paragraph, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            . '</w:t></w:r></w:p>';
    }

    $zip = new ZipArchive();
    try {
        if ($zip->open($temporary, ZipArchive::OVERWRITE) !== true) {
            throw new TmfWordImportException('Не удалось открыть архив шаблона.');
        }
        $zip->addFromString(
            '[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" '
            . 'ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '</Types>',
        );
        $zip->addFromString(
            '_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" '
            . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" '
            . 'Target="word/document.xml"/></Relationships>',
        );
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>' . $body . '<w:sectPr/></w:body></w:document>',
        );
        $zip->close();
        $bytes = file_get_contents($temporary);
        if ($bytes === false) {
            throw new TmfWordImportException('Не удалось прочитать созданный шаблон.');
        }
        return $bytes;
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}

function f_tmf_word_import_is_batch_id(string $batch_id): bool
{
    return preg_match('/^[a-f0-9]{32}$/', $batch_id) === 1;
}

/**
 * Remove a preview and its extracted media. Confirmed imports must use
 * $remove_media=false because their question HTML references these files.
 */
function f_tmf_word_import_cleanup_batch(string $cache_directory, string $batch_id, bool $remove_media = true): bool
{
    if (!f_tmf_word_import_is_batch_id($batch_id)) {
        return false;
    }

    $cache_directory = rtrim($cache_directory, '/\\');
    $preview_file = $cache_directory . '/wordimport-preview/' . $batch_id . '.php';
    if (is_file($preview_file) || is_link($preview_file)) {
        unlink($preview_file);
    }

    if ($remove_media) {
        f_tmf_word_import_remove_directory($cache_directory . '/wordimport/' . $batch_id);
    }

    return true;
}

/**
 * Remove abandoned previews and only the media directories tied to them.
 */
function f_tmf_word_import_cleanup_stale(
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
            f_tmf_word_import_cleanup_batch($cache_directory, $matches[1]);
            ++$removed;
        }
    }

    return $removed;
}

function f_tmf_word_import_remove_directory(string $directory): void
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
            f_tmf_word_import_remove_directory($path);
        }
    }
    rmdir($directory);
}

// @mago-expect lint:file-name -- the legacy library path is required directly by application integrations
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
    const MAX_UNCOMPRESSED_BYTES = 104_857_600;
    const MAX_DOCUMENT_BYTES = 20_971_520;

    public function __construct(string $filename, string $media_directory = '', string $media_url = '')
    {
        $this->filename = $filename;
        $this->media_directory = rtrim($media_directory, '/');
        $this->media_url = rtrim($media_url, '/');
    }

    /**
     * @return array{
     *     module: string,
     *     topic: string,
     *     questions: list<array{
     *         source_number: int,
     *         description: string,
     *         answers: list<array{key: string, description: string, weight: int|null}>,
     *         right_keys: list<string>,
     *         difficulty: int,
     *         timer: int,
     *         auto_next: int,
     *         fullscreen: int,
     *         inline_answers: int,
     *         mcma_checkbox: bool,
     *         mcma_header: list<string>,
     *         max_sel: int,
     *         similarity_threshold: int,
     *         audio_play_limit: int,
     *         short_answer: bool,
     *         matching: bool,
     *         type: int
     *     }>,
     *     warnings: list<string>,
     *     statistics: array{blocks: int, questions: int, images: int}
     * }
     * @throws TmfWordImportException
     */
    public function parse(): array
    {
        $this->openArchive();
        try {
            $this->loadRelationships();
            $this->loadDocument();

            $blocks = $this->readBodyBlocks();
            $result = $this->parseTemplateBlocks($blocks);
            $result['warnings'] = array_values(array_unique($this->warnings));
            $result['statistics'] = [
                'blocks' => count($blocks),
                'questions' => count($result['questions']),
                'images' => count($this->extracted_media),
            ];
            return $result;
        } finally {
            $this->zip->close();
        }
    }

    /** @throws TmfWordImportException */
    private function openArchive(): void
    {
        if (!class_exists(ZipArchive::class) || !class_exists(DOMDocument::class)) {
            throw new TmfWordImportException('DOCX import requires the PHP zip and dom extensions.');
        }
        if (!is_file($this->filename) || !is_readable($this->filename)) {
            throw new TmfWordImportException('DOCX file is not readable.');
        }
        $file_size = filesize($this->filename);
        if ($file_size !== false && $file_size > self::MAX_DOCUMENT_BYTES) {
            throw new TmfWordImportException('DOCX file is larger than 20 MB.');
        }
        $signature = file_get_contents($this->filename, false, null, 0, 4);
        if ($signature === false || substr($signature, 0, 2) !== 'PK') {
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
            if ($stat === false) {
                throw new TmfWordImportException('Unable to read a DOCX archive entry.');
            }
            /** @var array{name:string,size:int} $stat */
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

    /** @throws TmfWordImportException */
    private function loadRelationships(): void
    {
        $xml = $this->zip->getFromName('word/_rels/document.xml.rels');
        if ($xml === false) {
            return;
        }
        $dom = $this->loadXml($xml, 'document relationships');
        if ($dom->documentElement === null) {
            $this->warnings[] = 'Document relationships root element is missing.';
            return;
        }
        foreach ($dom->documentElement->childNodes as $node) {
            if (!$node instanceof DOMElement || $node->localName !== 'Relationship') {
                continue;
            }
            $id = $node->getAttribute('Id');
            $target = str_replace('\\', '/', $node->getAttribute('Target'));
            $mode = $node->getAttribute('TargetMode');
            $type = $node->getAttribute('Type');
            if ($mode === 'External' && !str_contains($type, '/hyperlink')) {
                $this->warnings[] = 'External non-hyperlink relationship was ignored.';
                continue;
            }
            $this->relationships[$id] = [
                'target' => $target,
                'external' => $mode === 'External',
                'type' => $type,
            ];
        }
    }

    /** @throws TmfWordImportException */
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

    /** @throws TmfWordImportException */
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

    /**
     * @return list<array{plain:string,html:string,kind:string}>
     * @throws TmfWordImportException
     */
    private function readBodyBlocks(): array
    {
        $body = $this->queryFirstElement('/w:document/w:body');
        if (!$body instanceof DOMElement) {
            throw new TmfWordImportException('DOCX body is missing.');
        }
        $blocks = [];
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

    /**
     * @param list<array{plain:string,html:string,kind:string}> $blocks
     * @throws TmfWordImportException
     */
    private function flattenMarkerTable(DOMElement $table, array &$blocks): void
    {
        foreach ($this->queryElements('./w:tr', $table) as $row) {
            foreach ($this->queryElements('./w:tc', $row) as $cell) {
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

    /**
     * @return array{plain:string,html:string,kind:string}
     * @throws TmfWordImportException
     */
    private function paragraphBlock(DOMElement $paragraph): array
    {
        $plain = $this->plainText($paragraph);
        $style = [];
        $alignment = $this->queryFirstElement('./w:pPr/w:jc', $paragraph);
        if ($alignment instanceof DOMElement) {
            $value = $this->wordAttribute($alignment, 'val');
            if (in_array($value, ['left', 'right', 'center', 'justify'], true)) {
                $style[] = 'text-align:' . $value;
            }
        }
        $direction = $this->queryFirstElement('./w:pPr/w:bidi', $paragraph);
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
                && in_array($child->localName, ['oMath', 'oMathPara'], true)
            ) {
                $inner .= $this->mathHtml($child);
            }
        }
        $style_attribute = empty($style)
            ? ''
            : ' style="' . htmlspecialchars(implode(';', $style), ENT_QUOTES, 'UTF-8') . '"';
        return [
            'plain' => trim($plain),
            'html' => '<div' . $style_attribute . '>' . $inner . '</div>',
            'kind' => 'paragraph',
        ];
    }

    /** @throws TmfWordImportException */
    private function hyperlinkHtml(DOMElement $hyperlink): string
    {
        $inner = '';
        foreach ($this->queryElements('./w:r', $hyperlink) as $run) {
            $inner .= $this->runHtml($run);
        }
        $id = $this->relationshipId($hyperlink);
        if (!$id) {
            return $inner;
        }
        $rel = $this->relationships[$id] ?? null;
        if ($rel === null) {
            return $inner;
        }
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

    /** @throws TmfWordImportException */
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
            } elseif (in_array($child->localName, ['br', 'cr'], true)) {
                $content .= '<br />';
            } elseif ($child->localName === 'drawing' || $child->localName === 'pict') {
                $content .= $this->imageHtml($child);
            } elseif (
                $child->namespaceURI === 'http://schemas.openxmlformats.org/officeDocument/2006/math'
                && in_array($child->localName, ['oMath', 'oMathPara'], true)
            ) {
                $content .= $this->mathHtml($child);
            }
        }
        if ($content === '') {
            return '';
        }

        $properties = $this->queryFirstElement('./w:rPr', $run);
        if (!$properties) {
            return $content;
        }
        if ($this->queryElements('./w:b[not(@w:val="0") and not(@w:val="false")]', $properties) !== []) {
            $content = '<strong>' . $content . '</strong>';
        }
        if ($this->queryElements('./w:i[not(@w:val="0") and not(@w:val="false")]', $properties) !== []) {
            $content = '<em>' . $content . '</em>';
        }
        if ($this->queryElements('./w:u[not(@w:val="none")]', $properties) !== []) {
            $content = '<u>' . $content . '</u>';
        }
        $styles = [];
        $color = $this->queryFirstElement('./w:color', $properties);
        if ($color instanceof DOMElement) {
            $value = $this->wordAttribute($color, 'val');
            if (preg_match('/^[0-9A-F]{6}$/i', $value)) {
                $styles[] = 'color:#' . $value;
            }
        }
        $highlight = $this->queryFirstElement('./w:highlight', $properties);
        if ($highlight instanceof DOMElement) {
            $value = $this->wordAttribute($highlight, 'val');
            $map = [
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
            ];
            if (isset($map[$value])) {
                $styles[] = 'background-color:' . $map[$value];
            }
        }
        $shade = $this->queryFirstElement('./w:shd', $properties);
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

    /** @throws TmfWordImportException */
    private function imageHtml(DOMElement $container): string
    {
        $blip = $this->queryFirstElement('.//a:blip', $container);
        if (!$blip instanceof DOMElement) {
            return '';
        }
        $id = $this->relationshipId($blip);
        if (!$id) {
            return '';
        }
        $rel = $this->relationships[$id] ?? null;
        if ($rel === null) {
            return '';
        }
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

        $target = ltrim(preg_replace('#^(\.\./)+#', '', $rel['target']) ?? $rel['target'], '/');
        $entry = str_starts_with($target, 'word/') ? $target : 'word/' . $target;
        if (!str_starts_with($entry, 'word/media/')) {
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
        $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'];
        $image_type = $image_info === false ? 0 : (int) $image_info[2];
        $extension = $allowed[$image_type] ?? null;
        if ($extension === null) {
            $this->warnings[] = 'A non-JPEG/PNG/GIF image was ignored for safety.';
            return '';
        }
        if (
            !is_dir($this->media_directory)
            && !mkdir($this->media_directory, 0o750, true)
            && !is_dir($this->media_directory)
        ) {
            throw new TmfWordImportException('Unable to create the Word import media directory.');
        }
        $basename = hash('sha256', $bytes) . '.' . $extension;
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

    /**
     * @return array{plain:string,html:string,kind:string}
     * @throws TmfWordImportException
     */
    private function tableBlock(DOMElement $table): array
    {
        $rows = [];
        foreach ($this->queryElements('./w:tr', $table) as $row) {
            $cells = [];
            $column = 0;
            foreach ($this->queryElements('./w:tc', $row) as $cell) {
                $span = $this->cellGridSpan($cell);
                $cells[] = [
                    'node' => $cell,
                    'column' => $column,
                    'span' => $span,
                    'continue' => $this->isVerticalMergeContinuation($cell),
                    'restart' => $this->isVerticalMergeRestart($cell),
                ];
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
        return [
            'plain' => trim($this->plainText($table)),
            'html' => $html,
            'kind' => 'table',
        ];
    }

    private function cellGridSpan(DOMElement $cell): int
    {
        $span = $this->queryFirstElement('./w:tcPr/w:gridSpan', $cell);
        if ($span instanceof DOMElement) {
            return max(1, (int) $this->wordAttribute($span, 'val'));
        }
        return 1;
    }

    private function isVerticalMergeContinuation(DOMElement $cell): bool
    {
        $merge = $this->queryFirstElement('./w:tcPr/w:vMerge', $cell);
        if (!$merge instanceof DOMElement) {
            return false;
        }
        $value = $this->wordAttribute($merge, 'val');
        return $value === '' || $value === 'continue';
    }

    private function isVerticalMergeRestart(DOMElement $cell): bool
    {
        $merge = $this->queryFirstElement('./w:tcPr/w:vMerge', $cell);
        return $merge instanceof DOMElement && $this->wordAttribute($merge, 'val') === 'restart';
    }

    /**
     * @param list<list<array{node:DOMElement,column:int,span:int,continue:bool,restart:bool}>> $rows
     */
    private function verticalRowspan(array $rows, int $start_row, int $column): int
    {
        $span = 1;
        for ($r = $start_row + 1; $r < count($rows); ++$r) {
            $found = false;
            $row = $rows[$r] ?? null;
            if ($row === null) {
                break;
            }
            foreach ($row as $cell) {
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
            return $this->escapeText($node->nodeValue ?? '');
        }
        if (!$node instanceof DOMElement) {
            return '';
        }
        $children = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMNode) {
                $children .= $this->mathNode($child);
            }
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

    /** @return list<DOMElement> */
    private function queryElements(string $expression, ?DOMNode $context = null): array
    {
        /** @var DOMNodeList|false $nodes */
        $nodes = $this->xpath->query($expression, $context);
        if (!$nodes instanceof DOMNodeList) {
            return [];
        }

        $elements = [];
        for ($index = 0; $index < $nodes->length; ++$index) {
            $node = $nodes->item($index);
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }
        return $elements;
    }

    private function queryFirstElement(string $expression, ?DOMNode $context = null): ?DOMElement
    {
        $elements = $this->queryElements($expression, $context);
        return $elements[0] ?? null;
    }

    /** @return list<DOMText> */
    private function queryTextNodes(DOMXPath $xpath, string $expression): array
    {
        /** @var DOMNodeList|false $nodes */
        $nodes = $xpath->query($expression);
        if (!$nodes instanceof DOMNodeList) {
            return [];
        }

        $text_nodes = [];
        for ($index = 0; $index < $nodes->length; ++$index) {
            $node = $nodes->item($index);
            if ($node instanceof DOMText) {
                $text_nodes[] = $node;
            }
        }
        return $text_nodes;
    }

    /**
     * @return array{
     *     source_number:int,description:string,
     *     answers:array<array-key,array{key:string,description:string,weight:int|null}>,
     *     right_keys:list<string>,difficulty:int,timer:int,auto_next:int,fullscreen:int,inline_answers:int,
     *     mcma_checkbox:bool,mcma_header:list<string>,max_sel:int,similarity_threshold:int,audio_play_limit:int,
     *     short_answer:bool,matching:bool,type:int
     * }
     */
    private function newQuestion(int $source_number, string $description): array
    {
        return [
            'source_number' => $source_number,
            'description' => $description,
            'answers' => [],
            'right_keys' => [],
            'difficulty' => 1,
            'timer' => 0,
            'auto_next' => 0,
            'fullscreen' => 0,
            'inline_answers' => 0,
            'mcma_checkbox' => false,
            'mcma_header' => [],
            'max_sel' => 0,
            'similarity_threshold' => 0,
            'audio_play_limit' => 0,
            'short_answer' => false,
            'matching' => false,
            'type' => 0,
        ];
    }

    /**
     * @return array{
     *     source_number:int,description:string,
     *     answers:array<array-key,array{key:string,description:string,weight:int|null}>,
     *     right_keys:list<string>,difficulty:int,timer:int,auto_next:int,fullscreen:int,inline_answers:int,
     *     mcma_checkbox:bool,mcma_header:list<string>,max_sel:int,similarity_threshold:int,audio_play_limit:int,
     *     short_answer:bool,matching:bool,type:int
     * }
     */
    private function typedQuestion(mixed $question): array
    {
        /**
         * @var array{
         *     source_number:int,description:string,
         *     answers:array<array-key,array{key:string,description:string,weight:int|null}>,
         *     right_keys:list<string>,difficulty:int,timer:int,auto_next:int,fullscreen:int,inline_answers:int,
         *     mcma_checkbox:bool,mcma_header:list<string>,max_sel:int,similarity_threshold:int,audio_play_limit:int,
         *     short_answer:bool,matching:bool,type:int
         * } $question
         */
        return $question;
    }

    /**
     * @return array{
     *     module: string,
     *     topic: string,
     *     questions: list<array{
     *         source_number: int,
     *         description: string,
     *         answers: list<array{key: string, description: string, weight: int|null}>,
     *         right_keys: list<string>,
     *         difficulty: int,
     *         timer: int,
     *         auto_next: int,
     *         fullscreen: int,
     *         inline_answers: int,
     *         mcma_checkbox: bool,
     *         mcma_header: list<string>,
     *         max_sel: int,
     *         similarity_threshold: int,
     *         audio_play_limit: int,
     *         short_answer: bool,
     *         matching: bool,
     *         type: int
     *     }>
     * }
     * @param list<array{plain:string,html:string,kind:string}> $blocks
     * @throws TmfWordImportException
     */
    private function parseTemplateBlocks(array $blocks): array
    {
        $result = [
            'module' => '',
            'topic' => '',
            'questions' => [],
        ];
        $question = null;
        $active_answer = null;

        foreach ($blocks as $block) {
            $plain = trim($block['plain']);
            if ($plain === '') {
                continue;
            }
            $match = [];
            if (preg_match('/^\s*MODULE:=\s*(.+)$/iu', $plain, $match) && isset($match[1])) {
                $result['module'] = trim($match[1]);
                continue;
            }
            if (preg_match('/^\s*TOPIC:=\s*(.+)$/iu', $plain, $match) && isset($match[1])) {
                $result['topic'] = trim($match[1]);
                continue;
            }
            if (preg_match('/^\s*Q:\s*(\d+)\)\s*/iu', $plain, $match) && isset($match[0], $match[1])) {
                if ($question !== null) {
                    /**
                     * @var array{
                     *     source_number:int,description:string,
                     *     answers:array<array-key,array{key:string,description:string,weight:int|null}>,
                     *     right_keys:list<string>,difficulty:int,timer:int,auto_next:int,fullscreen:int,
                     *     inline_answers:int,mcma_checkbox:bool,mcma_header:list<string>,max_sel:int,
                     *     similarity_threshold:int,audio_play_limit:int,short_answer:bool,matching:bool,type:int
                     * } $question
                     */
                    $question = $this->finalizeQuestion($this->typedQuestion($question));
                    $result['questions'][] = $question;
                }
                $prefix = $match[0];
                $question = $this->newQuestion(
                    (int) $match[1],
                    $this->stripHtmlTextPrefix($block['html'], mb_strlen($prefix, 'UTF-8')),
                );
                $active_answer = null;
                continue;
            }
            if ($question === null) {
                continue;
            }
            /**
             * @var array{
             *     source_number:int,description:string,
             *     answers:array<array-key,array{key:string,description:string,weight:int|null}>,
             *     right_keys:list<string>,difficulty:int,timer:int,auto_next:int,fullscreen:int,inline_answers:int,
             *     mcma_checkbox:bool,mcma_header:list<string>,max_sel:int,similarity_threshold:int,
             *     audio_play_limit:int,short_answer:bool,matching:bool,type:int
             * } $question
             */
            if (preg_match('/^\s*([A-Z])\s*:\)\s*/u', $plain, $match) && isset($match[0], $match[1])) {
                $key = strtoupper($match[1]);
                $prefix = $match[0];
                $answer = [
                    'key' => $key,
                    'description' => $this->stripHtmlTextPrefix($block['html'], mb_strlen($prefix, 'UTF-8')),
                    'weight' => null,
                ];
                $question['answers'][$key] = $answer;
                $active_answer = $key;
                continue;
            }
            if (preg_match('/^\s*RIGHT:\s*(.*)$/iu', $plain, $match) && isset($match[1])) {
                $keys = preg_split('/[\s,;]+/', strtoupper(trim($match[1])), -1, PREG_SPLIT_NO_EMPTY);
                if ($keys === false) {
                    $keys = [];
                }
                $question['right_keys'] = array_values(array_unique($keys));
                $active_answer = null;
                continue;
            }
            if ($active_answer !== null && isset($question['answers'][$active_answer])) {
                $question['answers'][$active_answer]['description'] .= $block['html'];
            } else {
                $question['description'] .= $block['html'];
            }
        }
        if ($question !== null) {
            /**
             * @var array{
             *     source_number:int,description:string,
             *     answers:array<array-key,array{key:string,description:string,weight:int|null}>,
             *     right_keys:list<string>,difficulty:int,timer:int,auto_next:int,fullscreen:int,inline_answers:int,
             *     mcma_checkbox:bool,mcma_header:list<string>,max_sel:int,similarity_threshold:int,
             *     audio_play_limit:int,short_answer:bool,matching:bool,type:int
             * } $question
             */
            $question = $this->finalizeQuestion($this->typedQuestion($question));
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
        /**
         * @var array{
         *     module: string,
         *     topic: string,
         *     questions: list<array{
         *         source_number: int,
         *         description: string,
         *         answers: list<array{key: string, description: string, weight: int|null}>,
         *         right_keys: list<string>,
         *         difficulty: int,
         *         timer: int,
         *         auto_next: int,
         *         fullscreen: int,
         *         inline_answers: int,
         *         mcma_checkbox: bool,
         *         mcma_header: list<string>,
         *         max_sel: int,
         *         similarity_threshold: int,
         *         audio_play_limit: int,
         *         short_answer: bool,
         *         matching: bool,
         *         type: int
         *     }>
         * } $result
         */
        return $result;
    }

    /**
     * @param array{
     *     source_number:int,description:string,
     *     answers:array<array-key,array{key:string,description:string,weight:int|null}>,
     *     right_keys:list<string>,difficulty:int,timer:int,auto_next:int,fullscreen:int,inline_answers:int,
     *     mcma_checkbox:bool,mcma_header:list<string>,max_sel:int,similarity_threshold:int,audio_play_limit:int,
     *     short_answer:bool,matching:bool,type:int
     * } $question
     * @return array{
     *     source_number:int,description:string,
     *     answers:list<array{key:string,description:string,weight:int|null}>,
     *     right_keys:list<string>,difficulty:int,timer:int,auto_next:int,fullscreen:int,inline_answers:int,
     *     mcma_checkbox:bool,mcma_header:list<string>,max_sel:int,similarity_threshold:int,audio_play_limit:int,
     *     short_answer:bool,matching:bool,type:int
     * }
     */
    private function finalizeQuestion(array $question): array
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
        if (preg_match('/\[\[MATCHING\]\]/iu', $plain)) {
            $question['matching'] = true;
            $question['description'] = $this->removeHtmlMarker($question['description'], '[[MATCHING]]');
        }
        $match = [];
        if (preg_match('/\[\[MCMA_HEADER:=([^\]]+)\]\]/iu', $plain, $match) && isset($match[0], $match[1])) {
            $question['mcma_header'] = array_map('trim', explode(',', $match[1]));
            $question['description'] = $this->removeHtmlMarker($question['description'], $match[0]);
        }
        if (preg_match('/\[\[MAX_SEL=(\d+)\]\]/iu', $plain, $match) && isset($match[0], $match[1])) {
            $question['max_sel'] = max(0, (int) $match[1]);
            if ($question['max_sel'] > 0) {
                $question['mcma_checkbox'] = true;
            }
            $question['description'] = $this->removeHtmlMarker($question['description'], $match[0]);
        }
        if (preg_match('/\[\[SIMILARITY=(\d{1,3})\]\]/iu', $plain, $match) && isset($match[0], $match[1])) {
            $question['similarity_threshold'] = max(0, min(99, (int) $match[1]));
            $question['description'] = $this->removeHtmlMarker($question['description'], $match[0]);
        }
        if (preg_match('/\[\[AUDIO_PLAYS=(\d{1,2})\]\]/iu', $plain, $match) && isset($match[0], $match[1])) {
            $question['audio_play_limit'] = max(0, min(99, (int) $match[1]));
            $question['description'] = $this->removeHtmlMarker($question['description'], $match[0]);
        }
        if (preg_match('/\[\[DIFFICULTY=(\d+)\]\]/iu', $plain, $match) && isset($match[0], $match[1])) {
            $question['difficulty'] = max(1, min(5, (int) $match[1]));
            $question['description'] = $this->removeHtmlMarker($question['description'], $match[0]);
        }
        if (preg_match('/\[\[TIMER=(\d+)\]\]/iu', $plain, $match) && isset($match[0], $match[1])) {
            $question['timer'] = max(0, min(32_767, (int) $match[1]));
            $question['description'] = $this->removeHtmlMarker($question['description'], $match[0]);
        }
        if (preg_match('/\[\[AUTO_NEXT(?:=1)?\]\]/iu', $plain, $match) && isset($match[0])) {
            $question['auto_next'] = 1;
            $question['description'] = $this->removeHtmlMarker($question['description'], $match[0]);
        }

        foreach ($question['answers'] as &$answer) {
            $answer_plain = $this->htmlPlainText($answer['description']);
            if (preg_match('/\[\[WEIGHT=(-?\d+)\]\]/iu', $answer_plain, $match) && isset($match[0], $match[1])) {
                // Keep imported data portable across all supported databases.
                $answer['weight'] = max(0, min(100, (int) $match[1]));
                $answer['description'] = $this->removeHtmlMarker($answer['description'], $match[0]);
            }
        }
        unset($answer);

        $answer_count = count($question['answers']);
        $right_count = count($question['right_keys']);
        if ($question['matching'] && $answer_count > 1) {
            $question['type'] = 5;
        } elseif ($answer_count === 0) {
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
            $encoded_header = json_encode($question['mcma_header']);
            $metadata .=
                '<!--TMF_MCMA_HEADER:'
                . htmlspecialchars($encoded_header === false ? '' : $encoded_header, ENT_QUOTES, 'UTF-8')
                . '-->';
        }
        if ($question['max_sel'] > 0) {
            $metadata .= '<!--TMF_MAX_SEL:' . $question['max_sel'] . '-->';
        }
        if ($question['similarity_threshold'] > 0) {
            $metadata .= '<!--TMF_SIMILARITY:' . $question['similarity_threshold'] . '-->';
        }
        if ($question['audio_play_limit'] > 0) {
            $metadata .= '<!--TMF_AUDIO_PLAYS:' . $question['audio_play_limit'] . '-->';
        }
        if ($question['type'] === 5) {
            $metadata .= '<!--TMF_MATCH_POSITIONS:' . $answer_count . '-->';
        }
        $question['description'] = $metadata . trim($question['description']);
        $question['answers'] = array_values($question['answers']);
        return $question;
    }

    private function stripHtmlTextPrefix(string $html, int $characters): string
    {
        if ($characters <= 0) {
            return $html;
        }
        $dom = $this->loadHtmlFragment($html);
        $xpath = new DOMXPath($dom);
        $remaining = $characters;
        foreach ($this->queryTextNodes($xpath, '//body/div//text()') as $text_node) {
            if ($remaining <= 0) {
                break;
            }
            $node_value = $text_node->nodeValue ?? '';
            $length = mb_strlen($node_value, 'UTF-8');
            if ($length <= $remaining) {
                $remaining -= $length;
                $text_node->nodeValue = '';
            } else {
                $text_node->nodeValue = mb_substr($node_value, $remaining, null, 'UTF-8');
                $remaining = 0;
            }
        }
        return $this->bodyInnerHtml($dom);
    }

    private function removeHtmlMarker(string $html, string $marker): string
    {
        $dom = $this->loadHtmlFragment($html);
        $xpath = new DOMXPath($dom);
        foreach ($this->queryTextNodes($xpath, '//body//text()') as $node) {
            $node_value = $node->nodeValue ?? '';
            if (stripos($node_value, $marker) !== false) {
                $node->nodeValue = str_ireplace($marker, '', $node_value);
                return $this->bodyInnerHtml($dom);
            }
        }
        // The marker can be split across Word runs. In that case, removing it
        // from the plain representation is safer than leaving a visible token.
        return preg_replace('/' . preg_quote($marker, '/') . '/iu', '', $html) ?? $html;
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
                if ($child instanceof DOMNode) {
                    $fragment = $dom->saveHTML($child);
                    $html .= $fragment === false ? '' : $fragment;
                }
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
                $text .= $child->nodeValue ?? '';
            } elseif ($child instanceof DOMElement) {
                if ($child->namespaceURI === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main') {
                    if (in_array($child->localName, ['t', 'instrText'], true)) {
                        $text .= $child->textContent;
                        continue;
                    }
                    if ($child->localName === 'tab') {
                        $text .= "\t";
                        continue;
                    }
                    if (in_array($child->localName, ['br', 'cr'], true)) {
                        $text .= "\n";
                        continue;
                    }
                }
                $text .= $this->plainText($child);
                if (in_array($child->localName, ['p', 'tr', 'tc'], true)) {
                    $text .= "\n";
                }
            }
        }
        return $text;
    }

    private function escapeText(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return preg_replace_callback(
            '/ {2,}/',
            self::preserveSpaces(...),
            $escaped,
        ) ?? $escaped;
    }

    /** @param array<array-key,string> $match */
    private static function preserveSpaces(array $match): string
    {
        return str_repeat('&nbsp;', max(0, strlen($match[0] ?? '') - 1)) . ' ';
    }

    private function wordAttribute(DOMElement $element, string $name): string
    {
        return $element->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', $name);
    }

    private function relationshipId(DOMElement $element): string
    {
        $id = $element->getAttributeNS(
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
            'id',
        );
        return $id !== '' ? $id : $element->getAttributeNS(
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
            'embed',
        );
    }
}

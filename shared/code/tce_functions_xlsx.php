<?php

/**
 * Small dependency-free OOXML reader/writer for native XLSX interchange.
 */

const TMF_XLSX_MAX_BYTES = 10_485_760;
const TMF_XLSX_MAX_ENTRIES = 200;
const TMF_XLSX_MAX_UNCOMPRESSED_BYTES = 52_428_800;
const TMF_XLSX_MAX_ROWS = 10_000;
const TMF_XLSX_MAX_COLUMNS = 50;

function f_tmf_xlsx_xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function f_tmf_xlsx_column_name(int $index): string
{
    $name = '';
    for ($value = $index + 1; $value > 0; $value = intdiv($value - 1, 26)) {
        $name = chr(65 + (($value - 1) % 26)) . $name;
    }
    return $name;
}

function f_tmf_xlsx_safe_sheet_name(string $name, int $fallback): string
{
    $name = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]+/u', ' ', trim($name));
    $name = mb_substr((string) $name, 0, 31);
    return $name === '' ? 'Sheet ' . $fallback : $name;
}

/**
 * @param array<int,array{name?:string,rows:array,widths?:array<int,float>}> $sheets
 * @throws RuntimeException
 */
function f_tmf_xlsx_build(array $sheets): string
{
    if ($sheets === [] || !class_exists(ZipArchive::class)) {
        throw new RuntimeException('XLSX support requires ZipArchive and at least one sheet.');
    }
    $temporary = tempnam(sys_get_temp_dir(), 'openvsosh-xlsx-');
    if ($temporary === false) {
        throw new RuntimeException('Unable to create a temporary XLSX file.');
    }
    $zip = new ZipArchive();
    if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        if (is_file($temporary)) {
            unlink($temporary);
        }
        throw new RuntimeException('Unable to create the XLSX archive.');
    }
    try {
        $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" '
            . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" '
            . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $workbook_sheets = '';
        $workbook_relationships = '';
        foreach (array_values($sheets) as $index => $sheet) {
            $sheet_number = $index + 1;
            $name = F_tmf_xlsx_safe_sheet_name($sheet['name'] ?? '', $sheet_number);
            $content_types .= '<Override PartName="/xl/worksheets/sheet' . $sheet_number . '.xml" '
                . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $workbook_sheets .= '<sheet name="' . F_tmf_xlsx_xml($name) . '" sheetId="' . $sheet_number
                . '" r:id="rId' . $sheet_number . '"/>';
            $workbook_relationships .= '<Relationship Id="rId' . $sheet_number
                . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
                . 'Target="worksheets/sheet' . $sheet_number . '.xml"/>';
            $zip->addFromString(
                'xl/worksheets/sheet' . $sheet_number . '.xml',
                F_tmf_xlsx_sheet_xml($sheet['rows'], $sheet['widths'] ?? []),
            );
        }
        $style_relationship_id = count($sheets) + 1;
        $content_types .= '</Types>';
        $workbook_relationships .= '<Relationship Id="rId' . $style_relationship_id
            . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" '
            . 'Target="styles.xml"/>';
        $zip->addFromString('[Content_Types].xml', $content_types);
        $zip->addFromString(
            '_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" '
            . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" '
            . 'Target="xl/workbook.xml"/></Relationships>',
        );
        $zip->addFromString(
            'xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $workbook_sheets . '</sheets></workbook>',
        );
        $zip->addFromString(
            'xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $workbook_relationships . '</Relationships>',
        );
        $zip->addFromString('xl/styles.xml', F_tmf_xlsx_styles_xml());
    } finally {
        $zip->close();
    }
    $bytes = file_get_contents($temporary);
    if (is_file($temporary)) {
        unlink($temporary);
    }
    if (!is_string($bytes)) {
        throw new RuntimeException('Unable to read the XLSX archive.');
    }
    return $bytes;
}

function f_tmf_xlsx_styles_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Arial"/></font>'
        . '<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Arial"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF245F9C"/>'
        . '<bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="2"><border/><border><bottom style="thin"><color rgb="FFD5DEE8"/>'
        . '</bottom></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="4">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1">'
        . '<alignment vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1"/>'
        . '<xf numFmtId="14" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1"/>'
        . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/>'
        . '</cellStyles></styleSheet>';
}

/**
 * @param array<array-key,mixed> $rows
 * @param array<array-key,mixed> $widths
 */
function f_tmf_xlsx_sheet_xml(array $rows, array $widths): string
{
    /** @var array<int,array<int,string|int|float|bool|null|array{value?:mixed,type?:mixed}>> $rows */
    /** @var array<int,int|float|string> $widths */
    $max_columns = 1;
    foreach ($rows as $row) {
        $max_columns = max($max_columns, count($row));
    }
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0" showGridLines="0">'
        . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
        . '</sheetView></sheetViews><cols>';
    for ($column = 0; $column < $max_columns; ++$column) {
        $width = isset($widths[$column]) ? max(6, min(60, (float) $widths[$column])) : 18;
        $xml .= '<col min="' . ($column + 1) . '" max="' . ($column + 1)
            . '" width="' . $width . '" customWidth="1"/>';
    }
    $xml .= '</cols><sheetData>';
    foreach (array_values($rows) as $row_index => $row) {
        $row_number = $row_index + 1;
        $xml .= '<row r="' . $row_number . '"' . ($row_number === 1 ? ' ht="24" customHeight="1"' : '') . '>';
        foreach (array_values($row) as $column_index => $cell) {
            /** @var string|int|float|bool|null|array{value?:mixed,type?:mixed} $cell */
            $reference = F_tmf_xlsx_column_name($column_index) . $row_number;
            $type = null;
            $value = $cell;
            if (is_array($cell) && array_key_exists('value', $cell)) {
                /** @var string|int|float|bool|null $cell_value */
                $cell_value = $cell['value'];
                $value = $cell_value;
                /** @var string|null $cell_type */
                $cell_type = $cell['type'] ?? null;
                $type = $cell_type;
            }
            /** @var string|int|float|bool|null $value */
            $style = $row_number === 1 ? 1 : 0;
            if ($type === 'date') {
                $timestamp = is_int($value) ? $value : strtotime((string) $value);
                $serial = $timestamp === false ? 0 : ($timestamp / 86_400) + 25_569;
                $xml .= '<c r="' . $reference . '" s="3"><v>'
                    . number_format($serial, 8, '.', '') . '</v></c>';
            } elseif ($type === 'number' || is_int($value) || is_float($value)) {
                $xml .= '<c r="' . $reference . '" s="' . ($row_number === 1 ? 1 : 2) . '"><v>'
                    . F_tmf_xlsx_xml((string) $value) . '</v></c>';
            } else {
                $text = (string) ($value ?? '');
                $space = $text !== trim($text) ? ' xml:space="preserve"' : '';
                $xml .= '<c r="' . $reference . '" t="inlineStr" s="' . $style . '"><is><t'
                    . $space . '>' . F_tmf_xlsx_xml($text) . '</t></is></c>';
            }
        }
        $xml .= '</row>';
    }
    $last = F_tmf_xlsx_column_name($max_columns - 1) . max(1, count($rows));
    return $xml . '</sheetData><autoFilter ref="A1:' . $last
        . '"/><pageMargins left="0.4" right="0.4" top="0.6" bottom="0.6" header="0.2" footer="0.2"/>'
        . '</worksheet>';
}

/**
 * Read the first worksheet as rows and reject formulas or oversized archives.
 *
 * @return array<int,array<int,string>>
 * @throws RuntimeException
 */
function f_tmf_xlsx_read(string $filename): array
{
    if (
        !is_file($filename)
        || filesize($filename) === false
        || (int) filesize($filename) > TMF_XLSX_MAX_BYTES
        || !class_exists(ZipArchive::class)
    ) {
        throw new RuntimeException('Invalid or oversized XLSX file.');
    }
    $zip = new ZipArchive();
    if ($zip->open($filename) !== true || $zip->numFiles > TMF_XLSX_MAX_ENTRIES) {
        throw new RuntimeException('Invalid XLSX archive.');
    }
    try {
        $uncompressed = 0;
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $stat = $zip->statIndex($index);
            $name = (string) ($stat['name'] ?? '');
            if (
                str_contains($name, '../')
                || str_starts_with($name, '/')
                || str_contains($name, "\0")
            ) {
                throw new RuntimeException('Unsafe XLSX archive path.');
            }
            $uncompressed += (int) ($stat['size'] ?? 0);
        }
        if ($uncompressed > TMF_XLSX_MAX_UNCOMPRESSED_BYTES) {
            throw new RuntimeException('Oversized XLSX archive contents.');
        }
        /** @var list<string> $shared */
        $shared = [];
        $shared_xml = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($shared_xml)) {
            $document = F_tmf_xlsx_dom($shared_xml);
            $xpath = new DOMXPath($document);
            /** @var DOMNodeList $shared_items */
            $shared_items = $xpath->query('//*[local-name()="si"]');
            foreach ($shared_items as $item) {
                /** @var DOMNode $item */
                $text = '';
                /** @var DOMNodeList $text_nodes */
                $text_nodes = $xpath->query('.//*[local-name()="t"]', $item);
                foreach ($text_nodes as $text_node) {
                    /** @var DOMNode $text_node */
                    $text .= $text_node->textContent;
                }
                $shared[] = $text;
            }
        }
        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!is_string($sheet_xml)) {
            throw new RuntimeException('The first XLSX worksheet is missing.');
        }
        $document = F_tmf_xlsx_dom($sheet_xml);
        $xpath = new DOMXPath($document);
        /** @var array<int,array<int,string>> $rows */
        $rows = [];
        /** @var DOMNodeList $row_nodes */
        $row_nodes = $xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]');
        foreach ($row_nodes as $row_node) {
            /** @var DOMElement $row_node */
            if (count($rows) >= TMF_XLSX_MAX_ROWS) {
                throw new RuntimeException('The XLSX row limit was exceeded.');
            }
            /** @var array<int,string> $row */
            $row = [];
            /** @var DOMNodeList $cells */
            $cells = $xpath->query('./*[local-name()="c"]', $row_node);
            foreach ($cells as $cell) {
                /** @var DOMElement $cell */
                /** @var DOMNodeList $formula_nodes */
                $formula_nodes = $xpath->query('./*[local-name()="f"]', $cell);
                if ($formula_nodes->length > 0) {
                    throw new RuntimeException('Formulas are not accepted in user imports.');
                }
                $reference = $cell->getAttribute('r');
                $match = [];
                if (preg_match('/^([A-Z]+)[0-9]+$/', $reference, $match) !== 1) {
                    continue;
                }
                /** @var array{0:string,1:string} $match */
                $column = 0;
                foreach (str_split($match[1]) as $letter) {
                    $column = ($column * 26) + (ord($letter) - 64);
                }
                --$column;
                if ($column >= TMF_XLSX_MAX_COLUMNS) {
                    throw new RuntimeException('The XLSX column limit was exceeded.');
                }
                $type = $cell->getAttribute('t');
                if ($type === 'inlineStr') {
                    /** @var DOMNodeList $nodes */
                    $nodes = $xpath->query('.//*[local-name()="t"]', $cell);
                    $value = '';
                    foreach ($nodes as $node) {
                        /** @var DOMNode $node */
                        $value .= $node->textContent;
                    }
                } else {
                    /** @var DOMNodeList $value_nodes */
                    $value_nodes = $xpath->query('./*[local-name()="v"]', $cell);
                    /** @var DOMNode|null $value_node */
                    $value_node = $value_nodes->item(0);
                    $value = $value_node ? $value_node->textContent : '';
                    if ($type === 's') {
                        $value = $shared[(int) $value] ?? '';
                    }
                }
                $row[$column] = $value;
            }
            if ($row !== []) {
                ksort($row);
                /** @var non-negative-int $max */
                $max = max(array_keys($row));
                $rows[] = array_replace(array_fill(0, $max + 1, ''), $row);
            }
        }
        return $rows;
    } finally {
        $zip->close();
    }
}

/** @throws RuntimeException */
function f_tmf_xlsx_dom(string $xml): DOMDocument
{
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try {
        if (!$document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Invalid XML in XLSX archive.');
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    return $document;
}

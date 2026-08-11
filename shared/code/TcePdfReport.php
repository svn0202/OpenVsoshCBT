<?php

//============================================================+
// File name   : TcePdfReport.php
// Begin       : 2026-06-22
// Author      : Nicola Asuni - Tecnick.com LTD - tecnick.com - info@tecnick.com
//
// Description : TCExam PDF report base class built on tc-lib-pdf (replaces the
//               legacy TCPDF-based tcpdfex.php for the report/export documents).
//               Reports are rendered by building HTML and letting the tc-lib-pdf
//               HTML/CSS engine handle layout, wrapping and page breaks.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * TCExam PDF report base class built on tc-lib-pdf.
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2026-06-22
 */

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * @class TcePdfReport
 * tc-lib-pdf subclass that renders TCExam report PDFs from HTML.
 * Provides an automatic page header/footer (via defaultPageContent) and a
 * vertical content cursor so successive HTML blocks flow down the page.
 */
class TcePdfReport extends \Com\Tecnick\Pdf\Tcpdf
{
    /** Left content margin in mm. */
    protected float $contentX = PDF_MARGIN_LEFT;

    /** Width of the content area in mm. */
    protected float $contentW = 0.0;

    /** Y position (mm) where page content starts, below the header band. */
    protected float $contentTop = PDF_MARGIN_TOP;

    /** Current vertical cursor (mm) for the next content block. */
    protected float $cursorY = 0.0;

    /** @var array<string,mixed>|null Cached default content font handle. */
    protected ?array $contentFont = null;

    /** Header title (left, bold). */
    protected string $headerTitle = '';

    /** Header descriptive string (below the title). */
    protected string $headerString = '';

    /** Optional header logo file name (resolved under K_PATH_IMAGES). */
    protected string $headerLogo = '';

    /** Header logo width in mm. */
    protected float $headerLogoWidth = 0.0;

    /** Optional URL printed as a QR-Code in the header (back-link to TCExam). */
    protected string $tcexam_backlink = '';

    /** When false, defaultPageContent() draws nothing (used for clean OMR scan pages). */
    protected bool $renderDecoration = true;

    /**
     * Constructor: A4 portrait, millimetres, unicode, compressed.
     * @throws \Throwable When the PDF engine cannot initialize the document.
     */
    public function __construct()
    {
        // All engine options are configurable via TCExam config (shared/config tce_pdf.php),
        // falling back to sensible defaults when a constant is not defined.
        $unit = self::stringValue(self::configValue('PDF_UNIT', 'mm'));
        $unicode = self::boolValue(self::configValue('K_PDF_UNICODE', true));
        $subsetfont = self::boolValue(self::configValue('K_PDF_SUBSET_FONT', false));
        $compress = self::boolValue(self::configValue('K_PDF_COMPRESS', true));
        $mode = self::stringValue(self::configValue('K_PDF_MODE', ''));
        parent::__construct($unit, $unicode, $subsetfont, $compress, $mode, null, self::buildFileOptions());
        // A4 portrait default; refined from the real page in addReportPage().
        $this->contentW = 210.0 - PDF_MARGIN_LEFT - PDF_MARGIN_RIGHT;
        $this->cursorY = $this->contentTop;
        $this->enableDefaultPageContent();
        // Sign the document when enabled in configuration (the engine signs at output time).
        $this->applyDigitalSignature();
    }

    /**
     * Apply a digital signature to the document when enabled in configuration.
     *
     * Reads the K_DIGSIG_* constants (shared/config tce_pdf.php) and maps them onto the
     * tc-lib-pdf setSignature() contract. A no-op unless K_DIGSIG_ENABLE is true and a signing
     * certificate is configured; the actual signing is performed by the engine at output time.
     * @throws \Throwable When the PDF engine rejects the signing configuration.
     */
    protected function applyDigitalSignature(): void
    {
        if (!self::boolValue(self::configValue('K_DIGSIG_ENABLE', false))) {
            return;
        }
        $signcert = self::stringValue(self::configValue('K_DIGSIG_CERTIFICATE', ''));
        if ($signcert === '') {
            return;
        }
        $data = [
            'signcert' => $signcert,
            'privkey' => self::stringValue(self::configValue('K_DIGSIG_PRIVATE_KEY', $signcert)),
            'password' => self::stringValue(self::configValue('K_DIGSIG_PASSWORD', '')),
            'cert_type' => self::intValue(self::configValue('K_DIGSIG_CERT_TYPE', 2)),
            'info' => [
                'Name' => self::stringValue(self::configValue('K_DIGSIG_NAME', '')),
                'Location' => self::stringValue(self::configValue('K_DIGSIG_LOCATION', '')),
                'Reason' => self::stringValue(self::configValue('K_DIGSIG_REASON', '')),
                'ContactInfo' => self::stringValue(self::configValue('K_DIGSIG_CONTACT', '')),
            ],
        ];
        // Optional bundle of extra certificates (only pass when configured).
        $extraCerts = self::stringValue(self::configValue('K_DIGSIG_EXTRA_CERTS', ''));
        if ($extraCerts !== '') {
            $data['extracerts'] = $extraCerts;
        }
        // @mago-expect analysis:possibly-invalid-argument -- setSignature merges this valid partial configuration with its defaults
        $this->setSignature($data);
    }

    /** @return list<string>|null */
    private static function serializedStringList(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        return array_values(array_filter(array_map('strval', $value)));
    }

    /** @return list<string>|null */
    private static function parseSerializedStringList(string $serialized): ?array
    {
        set_error_handler(static fn(): bool => true);
        try {
            return self::serializedStringList(unserialize($serialized, ['allowed_classes' => false]));
        } catch (Throwable) {
            return null;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Build the tc-lib-file security options from TCExam configuration constants.
     * Mirrors the tc-lib-pdf fileOptions contract so the allowed local paths, remote
     * hosts and download size limit are configurable (see shared/config tce_pdf.php).
     * Returns null when no constants are defined, so the library defaults apply.
     *
     * @return array{allowedPaths?:list<string>,allowedHosts?:list<string>,maxRemoteSize?:int}|null
     */
    protected static function buildFileOptions(): ?array
    {
        $opts = [];
        if (defined('K_PDF_ALLOWED_PATHS')) {
            $paths = self::parseSerializedStringList((string) K_PDF_ALLOWED_PATHS);
            if ($paths !== null && $paths !== []) {
                $opts['allowedPaths'] = $paths;
            }
        }
        if (defined('K_PDF_ALLOWED_HOSTS')) {
            $hosts = self::parseSerializedStringList((string) K_PDF_ALLOWED_HOSTS);
            if ($hosts !== null) {
                // Empty list keeps remote loading disabled (SSRF-safe default).
                $opts['allowedHosts'] = $hosts;
            }
        }
        if (defined('K_PDF_MAX_REMOTE_SIZE')) {
            $opts['maxRemoteSize'] = (int) K_PDF_MAX_REMOTE_SIZE;
        }
        return $opts === [] ? null : $opts;
    }

    /**
     * Set the page header content.
     *
     * @param string $title     Header title (left, bold).
     * @param string $string    Header descriptive text.
     * @param string $logo      Logo file name (under K_PATH_IMAGES), or empty.
     * @param float  $logowidth Logo width in mm.
     */
    public function setReportHeader(string $title, string $string = '', string $logo = '', float $logowidth = 0.0): void
    {
        $this->headerTitle = $title;
        $this->headerString = $string;
        $this->headerLogo = $logo;
        $this->headerLogoWidth = $logowidth;
    }

    /**
     * Set a URL printed as a QR-Code in the page header (back-link to TCExam).
     *
     * @param string $link URL link.
     */
    public function setTCExamBackLink(string $link): void
    {
        $this->tcexam_backlink = $link;
    }

    /**
     * Add a new content page and reset the content cursor below the header.
     * @throws \Throwable When the PDF engine cannot create the page.
     */
    public function addReportPage(): void
    {
        $data = [];
        if (self::hasConfig('PDF_PAGE_FORMAT')) {
            $data['format'] = self::stringValue(self::configValue('PDF_PAGE_FORMAT', ''));
        }
        if (self::hasConfig('PDF_PAGE_ORIENTATION')) {
            $data['orientation'] = self::stringValue(self::configValue('PDF_PAGE_ORIENTATION', ''));
        }
        // Reserve the header/footer bands as page margins so the HTML engine's
        // writable region (and therefore the automatic page-break resume position)
        // starts below the header and ends above the footer. Without this the page
        // region defaults to the full page (RY=0) and content that overflows onto a
        // new page resumes at the very top, overprinting the header band.
        $data['margin'] = [
            'PL' => (float) PDF_MARGIN_LEFT,
            'PR' => (float) PDF_MARGIN_RIGHT,
            'PT' => (float) PDF_MARGIN_TOP,
            'PB' => (float) PDF_MARGIN_BOTTOM,
        ];
        $this->addPage($data);
        $page = $this->page->getPage($this->page->getPageId());
        $this->contentW = $page['width'] - PDF_MARGIN_LEFT - PDF_MARGIN_RIGHT;
        $this->cursorY = $this->contentTop;
    }

    /**
     * Enable or disable the automatic page header/footer (turn off for clean OMR scan pages).
     *
     * @param bool $on True to draw the header/footer on subsequent pages.
     */
    public function enablePageDecoration(bool $on): void
    {
        $this->renderDecoration = $on;
    }

    /**
     * Ensure a default content font is active (required before HTML/text output).
     * @throws \Throwable When the PDF engine cannot load or activate the font.
     */
    protected function ensureContentFont(): void
    {
        if ($this->contentFont === null) {
            $this->contentFont = $this->font->insert($this->pon, PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA);
        }
        $this->page->addContent(self::stringValue($this->contentFont['out'] ?? ''));
    }

    /**
     * Render an HTML block at the current cursor and advance the cursor.
     * The tc-lib-pdf HTML engine handles wrapping and internal page breaks.
     *
     * @param string $html HTML content to render.
     * @throws \Throwable When the PDF engine cannot render the content.
     */
    public function writeReportHTML(string $html): void
    {
        if (trim($html) === '') {
            return;
        }
        if ($this->page->getPageId() < 0) {
            $this->addReportPage();
        }
        // If too little vertical room remains in the writable region to start a new
        // block, move to a fresh page first. The HTML engine paginates table rows
        // but not a tall inline figure, so a block that begins near the page bottom
        // can overrun the footer; bumping the whole block keeps it intact on the
        // next page. A fresh page always has the full region height, so this cannot
        // loop. See defaultPageContent() for the reserved header/footer bands.
        $region = $this->page->getRegion();
        $regionTop = $region['RY'];
        $regionBottom = $regionTop + $region['RH'];
        $minBlockRoom = 0.15 * $region['RH'];
        if ($this->cursorY > ($regionTop + 0.1) && ($regionBottom - $this->cursorY) < $minBlockRoom) {
            $this->addReportPage();
        }
        $this->ensureContentFont();
        $html = $this->resolveHtmlImagePaths($html);
        $this->addHTMLCell(html: $html, posx: $this->contentX, posy: $this->cursorY, width: $this->contentW);
        $bbox = $this->getLastBBox();
        /** @var array{y:int|float,h:int|float} $bbox */
        $this->cursorY = (float) ($bbox['y'] + $bbox['h']) + 1.5;
    }

    /**
     * Rewrite cache image URLs to absolute filesystem paths for the PDF engine.
     *
     * Question/answer content and LaTeX formulas are decoded with F_decode_tcecode(), which
     * emits <img src="..."> using the URL-cache path (K_PATH_URL_CACHE). The browser resolves
     * that URL, but tc-lib-pdf's image loader needs a local filesystem path; an unresolved URL
     * is silently dropped (image / rendered-LaTeX missing). Rewrite the cache URL prefix to the
     * on-disk cache directory (K_PATH_CACHE) so the assets embed.
     *
     * @param string $html HTML content to render.
     *
     * @return string HTML with cache image sources rewritten to filesystem paths.
     */
    protected function resolveHtmlImagePaths(string $html): string
    {
        if (!defined('K_PATH_URL_CACHE') || !defined('K_PATH_CACHE') || K_PATH_URL_CACHE === '') {
            return $html;
        }

        return str_replace(
            ['src="' . K_PATH_URL_CACHE, "src='" . K_PATH_URL_CACHE],
            ['src="' . K_PATH_CACHE, "src='" . K_PATH_CACHE],
            $html,
        );
    }

    /**
     * Output the report to the browser as a downloadable PDF.
     *
     * @param string $filename Suggested download file name.
     * @throws \Throwable When the PDF engine cannot build or send the document.
     */
    public function outputReport(string $filename = 'tcexam_report.pdf'): void
    {
        $this->setPDFFilename($filename);
        $raw = $this->getOutPDFString();
        $this->downloadPDF($raw);
    }

    /**
     * Generate the repeating page header (and an empty footer) for every page.
     * Invoked automatically by addPage() when enableDefaultPageContent() is on.
     *
     * @param int $pid Page index.
     *
     * @return string Raw PDF content stream prepended to the page.
     * @throws \Throwable When the PDF engine cannot render the decoration.
     */
    public function defaultPageContent(int $pid = -1): string
    {
        if (!$this->renderDecoration) {
            return '';
        }
        if ($pid < 0) {
            $pid = $this->page->getPageId();
        }
        $page = $this->page->getPage($pid);
        // The page-decoration callback runs from addPage() *before* it sets the graph page
        // dimensions, so barcode/rect helpers (which resolve Y against $this->graph->pageh) would
        // otherwise position against a stale/zero page height and draw the QR back-link off-page.
        // Set the current page geometry on the graph now so the QR lands inside the header.
        $this->graph->setPageWidth($page['width']);
        $this->graph->setPageHeight($page['height']);
        $pw = $page['width'];
        $lm = (float) PDF_MARGIN_LEFT;
        $rm = $pw - (float) PDF_MARGIN_RIGHT;
        $tw = $rm - $lm;

        $out = $this->graph->getStartTransform();

        // Resolve the optional header logo (configured via PDF_HEADER_LOGO / setReportHeader).
        $logoPath = '';
        $logoW = 0.0;
        $logoH = 0.0;
        if ($this->headerLogo !== '' && defined('K_PATH_MAIN')) {
            $candidate = K_PATH_MAIN . 'images/' . $this->headerLogo;
            if (is_file($candidate)) {
                $logoPath = $candidate;
                $logoW = $this->headerLogoWidth > 0 ? $this->headerLogoWidth : 20.0;
                // @mago-expect lint:no-error-control-operator -- an invalid optional logo falls back to the configured square size
                $size = @getimagesize($logoPath);
                $logoH = is_array($size) && (float) $size[0] > 0
                    ? $logoW * ((float) $size[1] / (float) $size[0])
                    : $logoW;
            }
        }

        // Header layout: [logo] [title / description] .................. [QR].
        // The logo sits at the left margin; the title block flows to its right and
        // shrinks to clear the QR back-link cluster in the top-right corner.
        $gap = 3.0;
        $qrSize = 18.0;
        $qrPresent = $this->tcexam_backlink !== '';

        // Header logo: top-left corner, with the title/description to its right.
        $textX = $lm;
        if ($logoPath !== '') {
            try {
                $iid = $this->addMarkupImage($logoPath);
                $out .= $this->image->getSetImage(
                    $iid,
                    $lm,
                    (float) PDF_MARGIN_HEADER,
                    $logoW,
                    $logoH,
                    $page['height'],
                );
                $textX = $lm + $logoW + $gap;
            } catch (\Throwable) {
                // Keep the title aligned when an optional logo is missing or unsupported.
                $logoW = 0.0;
                $logoH = 0.0;
                $textX = $lm;
            }
        }

        // Title block width: from the right edge of the logo to the left edge of the QR.
        $titleRight = $qrPresent ? $rm - $qrSize - $gap : $rm;
        $titleW = max(0.0, $titleRight - $textX);

        // Header title (bold) and descriptive string.
        $titleFont = $this->font->insert($this->pon, PDF_FONT_NAME_MAIN, 'B', PDF_FONT_SIZE_MAIN + 1);
        $out .= $titleFont['out'];
        $out .= $this->color->getPdfColor('#000000');
        $out .= $this->getTextCell(
            txt: $this->headerTitle,
            posx: $textX,
            posy: (float) PDF_MARGIN_HEADER,
            width: $titleW,
            height: 6.0,
            offset: 0,
            linespace: 0,
            valign: 'T',
            halign: 'L',
        );
        if ($this->headerString !== '') {
            $strFont = $this->font->insert($this->pon, PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN);
            $out .= $strFont['out'];
            $out .= $this->getTextCell(
                txt: $this->headerString,
                posx: $textX,
                posy: (float) PDF_MARGIN_HEADER + 6.0,
                width: $titleW,
                height: 0,
                offset: 0,
                linespace: 0,
                valign: 'T',
                halign: 'L',
            );
        }

        // QR-Code back-link, top-right corner.
        if ($qrPresent) {
            $out .= $this->getBarcode(
                type: 'QRCODE,L',
                code: $this->tcexam_backlink,
                posx: $rm - $qrSize,
                posy: (float) PDF_MARGIN_HEADER,
                width: (int) $qrSize,
                height: (int) $qrSize,
                style: ['fillColor' => '#000000'], // bars must be black; do not inherit current fill colour
            );
        }

        // Divider line below the header band.
        $liney = $this->contentTop - 2.0;
        $out .= $this->graph->getLine($lm, $liney, $rm, $liney, ['lineWidth' => 0.3, 'lineColor' => '#000000']);

        // Footer: separator line, branding (left) and page number (right).
        $footerMargin = defined('PDF_MARGIN_FOOTER') ? (float) PDF_MARGIN_FOOTER : 10.0;
        $footerY = $page['height'] - $footerMargin;
        $ffont = $this->font->insert($this->pon, PDF_FONT_NAME_DATA, '', max(5, PDF_FONT_SIZE_DATA - 1));
        $out .= $ffont['out'];
        $out .= $this->graph->getLine($lm, $footerY - 1.0, $rm, $footerY - 1.0, [
            'lineWidth' => 0.2,
            'lineColor' => '#999999',
        ]);
        $out .= $this->color->getPdfColor('#7f7f7f');
        $out .= $this->getTextCell(
            txt: 'Powered by TCExam (www.tcexam.org)',
            posx: $lm,
            posy: $footerY,
            width: $tw,
            height: 5.0,
            offset: 0,
            linespace: 0,
            valign: 'C',
            halign: 'L',
        );
        $out .= $this->getTextCell(
            txt: (string) ($pid + 1),
            posx: $lm,
            posy: $footerY,
            width: $tw,
            height: 5.0,
            offset: 0,
            linespace: 0,
            valign: 'C',
            halign: 'R',
        );
        $out .= $this->color->getPdfColor('#000000');
        $out .= $this->graph->getStopTransform();

        return $out;
    }

    /**
     * Print question statistics as an HTML table.
     * @param array<array-key,mixed> $stats Data to print.
     * @param int   $display_mode 2 = module; 3 = + subject; 4 = + question; 5 = + answer.
     * @throws \Throwable When the PDF engine cannot render the statistics.
     */
    public function printQuestionStats(array $stats, int $display_mode = 2): void
    {
        if ($display_mode < 2 || empty($stats)) {
            return;
        }
        global $l;
        /**
         * @var array{
         *     w_all:string,w_answer:string,w_answer_time:string,w_answers_right_th:string,w_answers_wrong_th:string,
         *     w_module:string,w_question:string,w_questions_unanswered_th:string,w_questions_undisplayed_th:string,
         *     w_questions_unrated_th:string,w_recurrence:string,w_score:string,w_statistics:string,w_subject:string
         * } $l
         */

        $title = $l['w_statistics'] . ' [' . $l['w_all'] . ' + ' . $l['w_module'];
        if ($display_mode > 2) {
            $title .= ' + ' . $l['w_subject'];
            if ($display_mode > 3) {
                $title .= ' + ' . $l['w_question'];
                if ($display_mode > 4) {
                    $title .= ' + ' . $l['w_answer'];
                }
            }
        }
        $title .= ']';

        $cols = [
            $l['w_recurrence'],
            $l['w_score'],
            $l['w_answer_time'],
            $l['w_answers_right_th'],
            $l['w_answers_wrong_th'],
            $l['w_questions_unanswered_th'],
            $l['w_questions_undisplayed_th'],
            $l['w_questions_unrated_th'],
        ];

        $html = '<table border="0.5" cellpadding="2" style="font-size:7pt;">';
        $html .=
            '<tr><td colspan="9" style="text-align:center;font-weight:bold;border-bottom:0.5px solid #000;">'
            . htmlspecialchars($title)
            . '</td></tr>';
        $html .= '<tr style="background-color:#cccccc;font-weight:bold;text-align:center;">';
        $html .= '<td>#</td>';
        foreach ($cols as $c) {
            $html .= '<td>' . htmlspecialchars($c) . '</td>';
        }
        $html .= '</tr>';

        // overall "all" row
        $html .= $this->statsRow('#ffeeee', $l['w_all'], $stats, true);

        $num_module = 0;
        foreach (self::rows($stats['module'] ?? []) as $module) {
            ++$num_module;
            $mcode = 'M' . $num_module;
            $html .= $this->statsRow('#ddeeff', $mcode, $module, true);
            $html .= $this->statsNameRow(
                '#ddeeff',
                self::stringValue(F_decode_tcecode(self::stringValue($module['name'] ?? ''))),
            );

            if ($display_mode > 2) {
                $num_subject = 0;
                foreach (self::rows($module['subject'] ?? []) as $subject) {
                    ++$num_subject;
                    $scode = $mcode . 'S' . $num_subject;
                    $html .= $this->statsRow('#ddffdd', $scode, $subject, true);
                    $html .= $this->statsNameRow(
                        '#ddffdd',
                        self::stringValue(F_decode_tcecode(self::stringValue($subject['name'] ?? ''))),
                    );

                    if ($display_mode > 3) {
                        $num_question = 0;
                        foreach (self::rows($subject['question'] ?? []) as $question) {
                            ++$num_question;
                            $qcode = $scode . 'Q' . $num_question;
                            $html .= $this->statsRow('#fffacd', $qcode, $question, true);
                            $html .= $this->statsNameRow(
                                '#fffacd',
                                self::stringValue(
                                    F_decode_tcecode(self::stringValue($question['description'] ?? '')),
                                ),
                            );

                            if ($display_mode > 4) {
                                $num_answer = 0;
                                foreach (self::rows($question['answer'] ?? []) as $answer) {
                                    ++$num_answer;
                                    $acode = $qcode . 'A' . $num_answer;
                                    $html .= $this->statsRow('#ffffff', $acode, $answer, false);
                                    $html .= $this->statsNameRow(
                                        '#ffffff',
                                        self::stringValue(
                                            F_decode_tcecode(self::stringValue($answer['description'] ?? '')),
                                        ),
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }
        $html .= '</table>';

        $this->writeReportHTML($html);
    }

    /**
     * Build a <colgroup> with explicit per-column percentage widths.
     * Used to override the HTML engine's content-based auto-layout where the
     * default sizing would over-widen short columns and starve others.
     *
     * @param array<int,float> $widths Column widths in percent of the table width.
     */
    protected function colGroup(array $widths): string
    {
        $out = '<colgroup>';
        foreach ($widths as $w) {
            $out .= '<col style="width:' . $w . '%;"/>';
        }
        return $out . '</colgroup>';
    }

    /**
     * Print the test results statistics table.
     * @param array<array-key,mixed> $data Test statistics.
     * @param bool  $pubmode If true, filter for the public interface (hide user column).
     * @param int   $stats   2 = full stats; 1 = user stats; 0 = disabled.
     * @throws \Throwable When the PDF engine cannot render the statistics.
     */
    public function printTestResultStat(array $data, bool $pubmode = false, int $stats = 2): void
    {
        global $l;
        /**
         * @var array{
         *     a_meta_dir:string,w_answers_right_th:string,w_answers_wrong_th:string,w_firstname:string,w_kurtosi:string,
         *     w_lastname:string,w_mean:string,w_median:string,w_mode:string,w_passed:string,
         *     w_questions_unanswered_th:string,w_questions_undisplayed_th:string,w_questions_unrated_th:string,
         *     w_results:string,w_score:string,w_skewness:string,w_standard_deviation:string,w_test:string,
         *     w_time:string,w_time_begin:string,w_user:string
         * } $l
         */
        $this->setBookmark($l['w_results']);
        $rtl = $l['a_meta_dir'] === 'rtl';

        $headers = ['#', $l['w_time_begin'], $l['w_time'], $l['w_test']];
        if (!$pubmode) {
            $headers[] = $l['w_user'] . ' - ' . $l['w_lastname'] . ', ' . $l['w_firstname'];
        }
        $headers[] = $l['w_score'];
        if ($stats > 0) {
            $headers = array_merge($headers, [
                $l['w_answers_right_th'],
                $l['w_answers_wrong_th'],
                $l['w_questions_unanswered_th'],
                $l['w_questions_undisplayed_th'],
                $l['w_questions_unrated_th'],
            ]);
        }

        // Explicit per-column widths (percent of the content width). Without them the
        // HTML engine auto-sizes columns from the short first-row body text, which
        // over-widens the date/time/score columns and starves the long statistics
        // headers (they overlap). The leading #/begin/time/score widths are fixed to
        // fit their content; the test (and user) column takes the remaining space.
        $hasStats = $stats > 0;
        $wNum = 4.0;
        $wBegin = 16.0;
        $wTime = 8.0;
        $wScore = 14.0;
        // The admin report adds a User column, so trim the stat columns a touch there
        // to leave the two text columns (test/user) a usable share of the width.
        $wStat = $pubmode ? 9.0 : 8.0;
        $flexCols = $pubmode ? 1 : 2; // test (and user when not in public mode)
        $fixed = $wNum + $wBegin + $wTime + $wScore + ($hasStats ? 5 * $wStat : 0.0);
        $wFlex = max(1.0, (100.0 - $fixed) / $flexCols);

        $mainCols = [$wNum, $wBegin, $wTime, $wFlex];
        if (!$pubmode) {
            $mainCols[] = $wFlex;
        }
        $mainCols[] = $wScore;
        if ($hasStats) {
            $mainCols = array_merge($mainCols, array_fill(0, 5, $wStat));
        }

        $html =
            '<table border="0.5" cellpadding="2" style="font-size:8pt;">'
            . $this->colGroup($mainCols)
            . '<thead><tr style="background-color:#cccccc;font-weight:bold;text-align:center;">';
        foreach ($headers as $h) {
            $html .= '<th>' . htmlspecialchars($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach (self::rows($data['testuser'] ?? []) as $tu) {
            $bg = self::boolValue($tu['passmsg'] ?? false) ? '#ddffdd' : '#ffeeee';
            $test = self::row($tu['test'] ?? []);
            $html .= '<tr style="background-color:' . $bg . ';">';
            $html .= '<td style="text-align:right;">'
                . htmlspecialchars(self::stringValue($tu['num'] ?? '')) . '</td>';
            $html .=
                '<td style="text-align:right;">'
                . htmlspecialchars(self::stringValue($tu['testuser_creation_time'] ?? ''))
                . '</td>';
            $html .= '<td style="text-align:right;">'
                . htmlspecialchars(self::stringValue($tu['time_diff'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars(self::stringValue($test['test_name'] ?? '')) . '</td>';
            if (!$pubmode) {
                $html .=
                    '<td>'
                    . htmlspecialchars(
                        self::stringValue($tu['user_name'] ?? '')
                        . ' - '
                        . self::stringValue($tu['user_lastname'] ?? '')
                        . ', '
                        . self::stringValue($tu['user_firstname'] ?? ''),
                    )
                    . '</td>';
            }
            // Monospace + fixed 3-decimal score + space-padded percentage so the numbers
            // line up vertically (F_formatPdfPercentage already pads via sprintf('% 3d')).
            $html .=
                '<td style="text-align:right;font-weight:bold;font-family:courier;">'
                . htmlspecialchars(
                    f_format_float(self::floatValue($tu['total_score'] ?? 0)) . ' '
                        . f_format_pdf_percentage(self::floatValue($tu['total_score_perc'] ?? 0), false),
                )
                . '</td>';
            if ($stats > 0) {
                foreach (['right', 'wrong', 'unanswered', 'undisplayed', 'unrated'] as $k) {
                    $html .=
                        '<td style="text-align:right;">'
                        . htmlspecialchars(
                            self::stringValue($tu[$k] ?? '')
                            . ' '
                            . f_format_pdf_percentage(self::floatValue($tu[$k . '_perc'] ?? 0), false),
                        )
                        . '</td>';
                }
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        // passed summary
        $bg = self::floatValue($data['passed_perc'] ?? 0) > 50 ? '#ddffdd' : '#ffeeee';
        $html .=
            '<table border="0.5" cellpadding="2" style="font-size:8pt;"><tr style="background-color:'
            . $bg
            . ';font-weight:bold;"><td>'
            . htmlspecialchars(
                $l['w_passed'] . ': ' . self::stringValue($data['passed'] ?? '') . ' '
                    . f_format_pdf_percentage(self::floatValue($data['passed_perc'] ?? 0), false),
            )
            . '</td></tr></table>';

        // distribution statistics
        $printstat = ['mean', 'median', 'mode', 'standard_deviation', 'skewness', 'kurtosi'];
        $noperc = ['skewness', 'kurtosi'];
        $srows = '';
        foreach (self::rows($data['statistics'] ?? []) as $row => $col) {
            $row = self::stringValue($row);
            if (!in_array($row, $printstat, true)) {
                continue;
            }
            $cells = [];
            $keys = ['score_perc', 'right_perc', 'wrong_perc', 'unanswered_perc', 'undisplayed_perc', 'unrated_perc'];
            foreach ($keys as $i => $k) {
                if ($i > 0 && $stats <= 0) {
                    break;
                }
                $value = self::floatValue($col[$k] ?? 0);
                $cells[] = in_array($row, $noperc, true) ? f_format_float($value) : round($value) . '%';
            }
            $srows .=
                '<tr><td style="font-weight:bold;text-align:right;">'
                . htmlspecialchars(self::stringValue($l['w_' . $row] ?? ''))
                . '</td>';
            foreach ($cells as $c) {
                $srows .=
                    '<td style="text-align:right;font-family:courier;">' . htmlspecialchars($c) . '</td>';
            }
            $srows .= '</tr>';
        }
        if ($srows !== '') {
            // Mirror the main-table score/stat widths so this table lines up beneath it;
            // the label column spans the leading #/begin/time/test(/user) columns.
            $statCols = array_merge(
                [$wNum + $wBegin + $wTime + ($wFlex * $flexCols), $wScore],
                $hasStats ? array_fill(0, 5, $wStat) : [],
            );
            $html .=
                '<table border="0.5" cellpadding="2" style="font-size:8pt;">'
                . $this->colGroup($statCols)
                . $srows
                . '</table>';
        }

        if ($rtl) {
            $html = '<div dir="rtl">' . $html . '</div>';
        }
        $this->writeReportHTML($html);
    }

    /**
     * Print the test/user info box followed by the per-question details.
     * @param array<array-key,mixed> $data Testuser data.
     * @param bool  $onlytext If true, print only free-text questions.
     * @throws \Throwable When the PDF engine cannot render the user report.
     */
    public function printTestUserInfo(array $data, bool $onlytext = false): void
    {
        global $l;
        /**
         * @var array{
         *     a_meta_dir:string,w_answers_right:string,w_answers_wrong:string,w_comment:string,w_firstname:string,
         *     w_lastname:string,w_not_passed:string,w_passed:string,w_questions_unanswered:string,
         *     w_questions_undisplayed:string,w_questions_unrated:string,w_score:string,w_test:string,
         *     w_test_score_threshold:string,w_time:string,w_time_begin:string,w_time_end:string,w_user:string
         * } $l
         */
        $test = self::row($data['test'] ?? []);
        $rtl = $l['a_meta_dir'] === 'rtl';

        $this->setBookmark(
            self::stringValue($data['user_lastname'] ?? '')
                . ' '
                . self::stringValue($data['user_firstname'] ?? '')
                . ' ('
                . self::stringValue($data['user_name'] ?? '')
                . '), '
                . self::stringValue($data['total_score'] ?? 0)
                . ' '
                . f_format_pdf_percentage(self::floatValue($data['total_score_perc'] ?? 0), false),
        );

        $test_end_time = self::stringValue($test['user_test_end_time'] ?? '');
        $test_start_time = self::stringValue($test['user_test_start_time'] ?? '');
        $test_end_timestamp = (int) strtotime($test_end_time);
        $test_start_timestamp = (int) strtotime($test_start_time);
        if (
            self::isNonPositiveString($test_end_time)
            || $test_end_timestamp < $test_start_timestamp
        ) {
            $time_diff = self::intValue($test['test_duration_time'] ?? 0) * 60;
        } else {
            $time_diff = $test_end_timestamp - $test_start_timestamp;
        }

        $rec = self::stringValue($data['recurrence'] ?? '');
        $info = [
            $l['w_lastname'] => self::stringValue($data['user_lastname'] ?? ''),
            $l['w_firstname'] => self::stringValue($data['user_firstname'] ?? ''),
            $l['w_user'] => self::stringValue($data['user_name'] ?? ''),
            $l['w_time_begin'] => $test_start_time,
            $l['w_time_end'] => $test_end_time,
            $l['w_time'] => gmdate('H:i:s', $time_diff),
        ];

        $passmsg = '';
        $score_threshold = self::floatValue($test['test_score_threshold'] ?? 0);
        if ($score_threshold > 0) {
            $info[$l['w_test_score_threshold']] = self::stringValue($test['test_score_threshold'] ?? '');
            $passmsg = self::floatValue($data['total_score'] ?? 0) >= $score_threshold
                ? ' - ' . $l['w_passed']
                : ' - ' . $l['w_not_passed'];
        }

        foreach ([
            'right' => 'w_answers_right',
            'wrong' => 'w_answers_wrong',
            'unanswered' => 'w_questions_unanswered',
            'undisplayed' => 'w_questions_undisplayed',
            'unrated' => 'w_questions_unrated',
        ] as $k => $label) {
            $info[self::stringValue($l[$label] ?? '')] =
                self::stringValue($data[$k] ?? '')
                . ' / '
                . $rec
                . ' '
                . f_format_pdf_percentage(self::floatValue($data[$k . '_perc'] ?? 0), false);
        }

        $html = '<table border="0.5" cellpadding="3" style="font-size:8pt;">';
        $html .=
            '<tr style="background-color:#cccccc;font-weight:bold;"><td colspan="2">'
            . htmlspecialchars($l['w_test'] . ': ' . self::stringValue($test['test_name'] ?? ''))
            . '</td></tr>';
        foreach ($info as $k => $v) {
            $html .=
                '<tr><td style="font-weight:bold;width:35%;">'
                . htmlspecialchars($k)
                . '</td><td>'
                . htmlspecialchars($v)
                . '</td></tr>';
        }
        $html .=
            '<tr style="font-weight:bold;"><td>'
            . htmlspecialchars($l['w_score'])
            . '</td><td>'
            . htmlspecialchars(
                self::stringValue($data['total_score'] ?? '')
                . ' / '
                . self::stringValue($test['test_max_score'] ?? '')
                . ' '
                . f_format_pdf_percentage(self::floatValue($data['total_score_perc'] ?? 0), false)
                . $passmsg,
            )
            . '</td></tr>';
        $html .= '</table>';

        if (self::boolValue($test['test_description'] ?? '')) {
            $html .= '<div style="font-size:8pt;">'
                . self::stringValue($test['test_description'] ?? '') . '</div>';
        }
        if (self::boolValue($test['user_comment'] ?? '')) {
            $html .=
                '<div style="font-size:8pt;"><b>'
                . htmlspecialchars($l['w_comment'])
                . '</b>: '
                . self::stringValue($test['user_comment'] ?? '')
                . '</div>';
        }

        if ($rtl) {
            $html = '<div dir="rtl">' . $html . '</div>';
        }
        $this->writeReportHTML($html);

        $this->printUserTestDetails($data, $onlytext);
    }

    /**
     * Print the per-question test details for the selected user.
     * @param array<array-key,mixed> $data Testuser data.
     * @param bool  $onlytext If true, print only free-text questions.
     * @throws \Throwable When the PDF engine cannot render the test details.
     */
    public function printUserTestDetails(array $data, bool $onlytext = false): void
    {
        global $db, $l;
        /** @var mixed $db */
        /** @var array{w_end:string,w_explanation:string,w_ip:string,w_reaction:string,w_score:string,w_start:string,w_time:string} $l */
        $testuser_id = (int) ($data['id'] ?? 0);
        $qtype = ['S', 'M', 'T', 'O', 'C'];

        $sql =
            'SELECT * FROM '
            . K_TABLE_QUESTIONS
            . ', '
            . K_TABLE_TESTS_LOGS
            . ', '
            . K_TABLE_SUBJECTS
            . ', '
            . K_TABLE_MODULES
            . '
			WHERE question_id=testlog_question_id
				AND testlog_testuser_id='
            . $testuser_id
            . '
				AND question_subject_id=subject_id
				AND subject_module_id=module_id';
        if ($onlytext) {
            $sql .= ' AND question_type=3';
        }
        $sql .= ' ORDER BY testlog_id';

        $r = self::queryResult(F_db_query($sql, $db));
        if ($r) {
            $itemcount = 1;
            while (($m = self::questionLogRow(F_db_fetch_array($r))) !== null) {
                $question_type = (int) $m['question_type'];
                $display_value = self::stringValue($m['testlog_display_time']);
                $change_value = self::stringValue($m['testlog_change_time']);
                $reaction_value = self::stringValue($m['testlog_reaction_time']);
                $display_time = strlen($display_value) > 0
                    ? substr($display_value, 11, 8)
                    : '--:--:--';
                $change_time = strlen($change_value) > 0
                    ? substr($change_value, 11, 8)
                    : '--:--:--';
                $diff_time = $m['testlog_display_time'] !== null && $m['testlog_change_time'] !== null
                    ? date('i:s', (int) strtotime($change_value) - (int) strtotime($display_value))
                    : '--:--';
                $reaction_time = strlen($reaction_value) > 0
                    ? self::floatValue($m['testlog_reaction_time']) / 1000
                    : '';

                $html = '<table border="0.5" cellpadding="2" style="font-size:8pt;"><tr style="background-color:#cccccc;font-weight:bold;text-align:center;">';
                foreach ([
                    '#',
                    $l['w_score'],
                    $l['w_ip'],
                    $l['w_start'],
                    $l['w_end'],
                    $l['w_time'],
                    $l['w_reaction'] . ' [sec]',
                ] as $h) {
                    $html .= '<td>' . htmlspecialchars($h) . '</td>';
                }
                $html .= '</tr><tr style="text-align:center;">';
                foreach ([
                    $itemcount . ' ' . ($qtype[$question_type - 1] ?? ''),
                    $m['testlog_score'],
                    get_ip_as_string($m['testlog_user_ip']),
                    $display_time,
                    $change_time,
                    $diff_time,
                    $reaction_time,
                ] as $c) {
                    $html .= '<td>' . htmlspecialchars(self::stringValue($c)) . '</td>';
                }
                $html .= '</tr></table>';

                $html .= '<div style="font-size:8pt;">'
                    . self::stringValue(F_decode_tcecode($m['question_description'])) . '</div>';
                if (self::boolValue(K_ENABLE_QUESTION_EXPLANATION) && $m['question_explanation'] !== '') {
                    $html .=
                        '<div style="font-size:8pt;border:0.5px solid #000000;"><b><i><u>'
                        . htmlspecialchars($l['w_explanation'])
                        . '</u></i></b><br/>'
                        . self::stringValue(F_decode_tcecode($m['question_explanation']))
                        . '</div>';
                }

                if ($question_type === 3) {
                    // free-text answer
                    $html .=
                        '<div style="font-size:8pt;border:0.5px solid #000000;">'
                        . self::stringValue(F_decode_tcecode($m['testlog_answer_text']))
                        . '</div>';
                    require_once __DIR__ . '/tce_functions_attachments.php';
                    $attachments = self::attachmentRows(F_tmf_attachment_list((int) $m['testlog_id']));
                    if ($attachments !== []) {
                        $html .= '<div style="font-size:8pt;"><b>Вложения:</b><ul>';
                        foreach ($attachments as $attachment) {
                            $html .= '<li>' . htmlspecialchars($attachment['attachment_original_name'])
                                . ' — ' . htmlspecialchars($attachment['attachment_mime'])
                                . ', ' . number_format((int) $attachment['attachment_size'] / 1024, 1, '.', ' ')
                                . ' КБ</li>';
                            $path = self::stringValue(F_tmf_attachment_path($attachment));
                            if (
                                str_starts_with($attachment['attachment_mime'], 'image/')
                                && $path !== ''
                                && is_file($path)
                            ) {
                                $html .= '<img src="' . htmlspecialchars($path, ENT_QUOTES)
                                    . '" style="max-width:120mm;max-height:80mm;" alt="" />';
                            }
                        }
                        $html .= '</ul></div>';
                    }
                } else {
                    $sqla =
                        'SELECT * FROM '
                        . K_TABLE_LOG_ANSWER
                        . ', '
                        . K_TABLE_ANSWERS
                        . ' WHERE logansw_answer_id=answer_id AND logansw_testlog_id='
                        . self::stringValue($m['testlog_id'])
                        . ' ORDER BY logansw_order';
                    $ra = self::queryResult(F_db_query($sqla, $db));
                    if ($ra) {
                        // width:100% so the answer rows span the full content width,
                        // visually consistent with the full-width stats/info tables.
                        $html .= '<table border="0.5" cellpadding="2" style="width:100%;font-size:8pt;">';
                        $idx = 0;
                        while (($ma = self::answerLogRow(F_db_fetch_array($ra))) !== null) {
                            ++$idx;
                            [$marker, $markfill, $index, $idxfill] = $this->answerMarker(
                                $question_type,
                                $ma,
                                $idx,
                            );
                            $mbg = $markfill ? ' background-color:#cccccc;' : '';
                            $ibg = $idxfill ? ' background-color:#cccccc;' : '';
                            $html .= '<tr>';
                            $html .=
                                '<td style="width:6%;text-align:center;'
                                . $mbg
                                . '">'
                                . htmlspecialchars($marker)
                                . '</td>';
                            $html .=
                                '<td style="width:6%;text-align:center;'
                                . $ibg
                                . '">'
                                . htmlspecialchars($index)
                                . '</td>';
                            // Explicit width so the three columns sum to 100%: an auto column would
                            // otherwise default to availableWidth/cols and leave the table ~45% wide.
                            $html .= '<td style="width:88%;">'
                                . self::stringValue(F_decode_tcecode($ma['answer_description'])) . '</td>';
                            $html .= '</tr>';
                            if (self::boolValue(K_ENABLE_ANSWER_EXPLANATION) && $ma['answer_explanation'] !== '') {
                                $html .=
                                    '<tr><td colspan="3" style="font-size:7pt;"><b><i><u>'
                                    . htmlspecialchars($l['w_explanation'])
                                    . '</u></i></b><br/>'
                                    . self::stringValue(F_decode_tcecode($ma['answer_explanation']))
                                    . '</td></tr>';
                            }
                        }
                        $html .= '</table>';
                    } else {
                        F_display_db_error();
                    }
                }

                if (strlen(self::stringValue($m['testlog_comment'])) > 0) {
                    $html .=
                        '<div style="font-size:8pt;color:#ff0000;border:0.5px solid #000000;">'
                        . self::stringValue(F_decode_tcecode(self::stringValue($m['testlog_comment'])))
                        . '</div>';
                }

                $this->writeReportHTML($html);
                ++$itemcount;
            }
        } else {
            F_display_db_error();
        }

        $test = self::row($data['test'] ?? []);
        $stats = self::row(
            f_get_test_stat(
                self::intValue($test['test_id'] ?? 0),
                0,
                self::intValue($data['user_id'] ?? 0),
                0,
                0,
                self::intValue($data['id'] ?? 0),
            ),
        );
        $this->printQuestionStats(self::row($stats['qstats'] ?? []), 1);
    }

    /**
     * Compute the answer marker symbol/fill and index symbol/fill for an answer row.
     * Mirrors the legacy selected/right/position logic.
     *
     * @param int   $qtype Question type (1=MCSA, 2=MCMA, 4=ordering, 5=matching).
     * @param array<string,int|string|bool> $ma Answer log record.
     * @param int   $idx   1-based answer index.
     *
     * @return array{0:string,1:bool,2:string,3:bool} [marker, markerFilled, index, indexFilled]
     */
    protected function answerMarker(int $qtype, array $ma, int $idx): array
    {
        $marker = ' ';
        $markfill = false;
        $right = f_get_boolean($ma['answer_isright'] ?? false);

        if (in_array($qtype, [4, 5], true)) {
            /** @var int|numeric-string $raw_log_position */
            $raw_log_position = $ma['logansw_position'] ?? 0;
            /** @var int|numeric-string $raw_answer_position */
            $raw_answer_position = $ma['answer_position'] ?? 0;
            $log_position = (int) $raw_log_position;
            $answer_position = (int) $raw_answer_position;
            $marker = $log_position > 0 ? (string) $log_position : ' ';
            $markfill = $log_position > 0 && $log_position === $answer_position;
            $index = (string) $answer_position;
            $idxfill = $markfill;
            return [$marker, $markfill, $index, $idxfill];
        }

        /** @var int|numeric-string $raw_selected */
        $raw_selected = $ma['logansw_selected'] ?? 0;
        $selected = (int) $raw_selected;
        if ($selected > 0) {
            $marker = $right ? '+' : '-';
            $markfill = true;
        } elseif ($qtype === 1) {
            $marker = ' ';
        } elseif ($selected === 0) {
            $marker = $right ? '-' : '+';
        }

        $index = (string) $idx;
        $idxfill = $right;
        return [$marker, $markfill, $index, $idxfill];
    }

    /**
     * Print an SVG statistics graph with a coloured legend.
     * @param string $svgdata SVG graph data (legacy f_get_svg_graph_code input).
     * @throws \Throwable When the PDF engine cannot render the legend.
     */
    public function printSVGStatsGraph(string $svgdata): void
    {
        global $l;
        /** @var array{w_answers_right:string,w_score:string,w_tests:string} $l */
        $match = [];
        if ((int) preg_match_all('/[x]/', $svgdata, $match) <= 1) {
            return;
        }
        $legend =
            '<div style="text-align:center;font-size:8pt;">'
            . '<span style="background-color:#ff0000;color:#ffffff;">&nbsp;'
            . htmlspecialchars($l['w_score'])
            . '&nbsp;</span> '
            . '<span style="background-color:#0000ff;color:#ffffff;">&nbsp;'
            . htmlspecialchars($l['w_answers_right'])
            . '&nbsp;</span> / '
            . '<span style="background-color:#dddddd;color:#000000;">&nbsp;'
            . htmlspecialchars($l['w_tests'])
            . '&nbsp;</span></div>';
        $this->writeReportHTML($legend);

        // f_get_svg_graph_code() lives in a standalone helper that the PDF report endpoints
        // do not otherwise include; load it on demand so the graph is not silently skipped
        // (the legend above would still print, leaving a confusing legend-without-graph).
        if (!function_exists('f_get_svg_graph_code')) {
            $svgfn = __DIR__ . '/tce_functions_svg_graph.php';
            if (is_file($svgfn)) {
                require_once $svgfn;
            }
        }
        if (!function_exists('f_get_svg_graph_code')) {
            return;
        }
        // Render the SVG at a high native resolution (as the web view does) so its
        // fixed-size axis labels stay small relative to the viewport. Generating it at
        // the content width (~180 units) would map roughly 1 unit -> 1 mm, blowing the
        // labels up to ~30pt and overlapping them.
        $svg = self::stringValue(f_get_svg_graph_code(substr($svgdata, 1), 800, 450));
        if ($svg === '' || $svg[0] !== '<') {
            return;
        }
        // Placement box: full content width, height following the SVG's native aspect
        // so the graph is not distorted regardless of the number of plotted points.
        $natW = 800.0;
        $natH = 450.0;
        $mm = [];
        if (
            preg_match('/<svg\b[^>]*?\bwidth="([0-9.]+)"[^>]*?\bheight="([0-9.]+)"/', $svg, $mm) === 1
            && isset($mm[1], $mm[2])
            && self::floatValue($mm[1]) > 0.0
        ) {
            $natW = self::floatValue($mm[1]);
            $natH = self::floatValue($mm[2]);
        }
        $w = $this->contentW;
        $h = ($w * $natH) / $natW;
        try {
            // Move to a fresh page if the fixed-height graph would overrun the
            // writable region (footer band) — addSVG draws a single block the
            // engine does not paginate.
            $region = $this->page->getRegion();
            $regionTop = $region['RY'];
            $regionBottom = $regionTop + $region['RH'];
            if ($this->cursorY > ($regionTop + 0.1) && ($regionBottom - $this->cursorY) < ($h + 2.0)) {
                $this->addReportPage();
            }
            $this->ensureContentFont();
            $page = $this->page->getPage($this->page->getPageId());
            $pageHeight = $page['height'];
            // Pass the SVG inline via the '@' prefix (image-from-string) rather than a
            // temp file: a file path must live in a writable dir that is also inside the
            // engine's allowed-paths, which fails under Apache (PrivateTmp / restricted
            // sys_get_temp_dir), silently dropping the graph. addSVG only builds the SVG
            // object; its content must then be flushed to the page via getSetSVG().
            $soid = $this->addSVG('@' . $svg, $this->contentX, $this->cursorY, $w, $h, $pageHeight);
            $this->page->addContent($this->getSetSVG($soid));
            $this->cursorY += $h + 2.0;
        } catch (\Throwable $e) {
            // The graph is supplementary, but record the failure for operator diagnostics.
            error_log('OpenVsoshCBT: supplementary PDF graph rendering failed: ' . $e->getMessage());
        }
    }

    /**
     * Build a statistics data row (9 columns).
     *
     * @param string $bgcolor Row background colour.
     * @param string $code    Row label (#, M1, M1S1, ...).
     * @param array<array-key,mixed> $d Stats record.
     * @param bool   $full    If false, score/time/undisplayed/unrated are blank (answer rows).
     */
    protected function statsRow(string $bgcolor, string $code, array $d, bool $full): string
    {
        $cells = [
            self::stringValue($d['recurrence'] ?? '') . $this->pctOf($d, 'recurrence_perc'),
            $full
                ? number_format(self::floatValue($d['average_score'] ?? 0), 3, '.', '')
                    . $this->pctOf($d, 'average_score_perc')
                : '',
            $full ? date('i:s', self::intValue($d['average_time'] ?? 0)) : '',
            self::stringValue($d['right'] ?? '') . $this->pctOf($d, 'right_perc'),
            self::stringValue($d['wrong'] ?? '') . $this->pctOf($d, 'wrong_perc'),
            self::stringValue($d['unanswered'] ?? '') . $this->pctOf($d, 'unanswered_perc'),
            $full ? self::stringValue($d['undisplayed'] ?? '') . $this->pctOf($d, 'undisplayed_perc') : '',
            $full ? self::stringValue($d['unrated'] ?? '') . $this->pctOf($d, 'unrated_perc') : '',
        ];
        $row = '<tr style="background-color:' . $bgcolor . ';">';
        $row .= '<td style="font-family:courier;font-weight:bold;">' . htmlspecialchars($code) . '</td>';
        foreach ($cells as $c) {
            $row .= '<td style="text-align:right;font-family:courier;">' . htmlspecialchars($c) . '</td>';
        }
        return $row . '</tr>';
    }

    /**
     * Build a full-width name/description row (decoded TCEcode HTML).
     */
    protected function statsNameRow(string $bgcolor, string $htmlname): string
    {
        return '<tr style="background-color:' . $bgcolor . ';"><td colspan="9">' . $htmlname . '</td></tr>';
    }

    /**
     * Format the percentage suffix for a given key, or '' when absent.
     * @param array<array-key,mixed> $d
     */
    protected function pctOf(array $d, string $key): string
    {
        return isset($d[$key]) ? ' ' . f_format_pdf_percentage(self::floatValue($d[$key]), false) : '';
    }

    private static function stringValue(mixed $value): string
    {
        return is_array($value) ? 'Array' : (string) $value;
    }

    private static function hasConfig(string $name): bool
    {
        return defined($name);
    }

    private static function configValue(string $name, mixed $default): mixed
    {
        return defined($name) ? constant($name) : $default;
    }

    private static function floatValue(mixed $value): float
    {
        if (is_int($value) || is_float($value) || is_string($value) || is_bool($value)) {
            return (float) $value;
        }
        if (is_array($value)) {
            return $value === [] ? 0.0 : 1.0;
        }
        if (is_object($value)) {
            return 1.0;
        }
        if (is_resource($value)) {
            return (float) (int) $value;
        }
        return 0.0;
    }

    private static function intValue(mixed $value): int
    {
        if (is_int($value) || is_float($value) || is_string($value) || is_bool($value)) {
            return (int) $value;
        }
        if (is_array($value)) {
            return $value === [] ? 0 : 1;
        }
        if (is_object($value)) {
            return 1;
        }
        if (is_resource($value)) {
            return (int) $value;
        }
        return 0;
    }

    private static function isNonPositiveString(string $value): bool
    {
        return $value <= 0;
    }

    private static function boolValue(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }
        if (is_object($value) || is_resource($value)) {
            return true;
        }
        return is_bool($value) || is_int($value) || is_float($value) || is_string($value)
            ? (bool) $value
            : false;
    }

    /** @return array<string,mixed> */
    private static function row(mixed $value): array
    {
        /** @var array<string,mixed> $value */
        return $value;
    }

    /** @return object|resource|bool */
    private static function queryResult(mixed $value): mixed
    {
        /** @var object|resource|bool $value */
        return $value;
    }

    /**
     * @return array{
     *     question_type:int|string,
     *     testlog_display_time:string|null,
     *     testlog_change_time:string|null,
     *     testlog_reaction_time:int|float|string|null,
     *     testlog_score:int|float|string|null,
     *     testlog_user_ip:mixed,
     *     question_description:string,
     *     question_explanation:string,
     *     testlog_answer_text:string,
     *     testlog_id:int|string,
     *     testlog_comment:string|null
     * }|null
     */
    private static function questionLogRow(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        /**
         * @var array{
         *     question_type:int|string,
         *     testlog_display_time:string|null,
         *     testlog_change_time:string|null,
         *     testlog_reaction_time:int|float|string|null,
         *     testlog_score:int|float|string|null,
         *     testlog_user_ip:mixed,
         *     question_description:string,
         *     question_explanation:string,
         *     testlog_answer_text:string,
         *     testlog_id:int|string,
         *     testlog_comment:string|null
         * } $value
         */
        return $value;
    }

    /**
     * @return array<array-key,array{
     *     attachment_original_name:string,
     *     attachment_mime:string,
     *     attachment_size:int|string
     * }>
     */
    private static function attachmentRows(mixed $value): array
    {
        /**
         * @var array<array-key,array{
         *     attachment_original_name:string,
         *     attachment_mime:string,
         *     attachment_size:int|string
         * }> $value
         */
        return $value;
    }

    /**
     * @return array{
     *     answer_isright:int|string|bool,
     *     logansw_position:int|string,
     *     answer_position:int|string,
     *     logansw_selected:int|string|bool,
     *     answer_description:string,
     *     answer_explanation:string
     * }|null
     */
    private static function answerLogRow(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        /**
         * @var array{
         *     answer_isright:int|string|bool,
         *     logansw_position:int|string,
         *     answer_position:int|string,
         *     logansw_selected:int|string|bool,
         *     answer_description:string,
         *     answer_explanation:string
         * } $value
         */
        return $value;
    }

    /** @return array<array-key,array<string,mixed>> */
    private static function rows(mixed $value): array
    {
        /** @var array<array-key,array<string,mixed>> $value */
        return $value;
    }
}

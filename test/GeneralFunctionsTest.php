<?php

//============================================================+
// File name   : GeneralFunctionsTest.php
// Begin       : 2026-06-22
//
// Description : Unit tests for shared/code/tce_functions_general.php
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

namespace Test;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * @file
 * Tests for the pure general/utility/rating helpers.
 * @package com.tecnick.tcexam.test
 */
final class GeneralFunctionsTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testRequiredFieldMarkerUsesConfiguredLabels(): void
    {
        // @mago-expect lint:no-global -- the legacy helper reads its translations from global $l
        $GLOBALS['l'] = ['w_required' => 'Required', 'w_not_required' => 'Optional'];

        self::assertSame(' <abbr class="requiredonbox" title="Required">+</abbr>', \showRequiredField(2));
        self::assertSame(' <abbr class="requiredoffbox" title="Optional">-</abbr>', \showRequiredField(1));
    }

    public function testBootstrapFileExistsShim(): void
    {
        self::assertTrue(\F_file_exists(__FILE__));
        self::assertFalse(\F_file_exists(__DIR__ . '/missing-file-for-bootstrap-test'));
    }

    public function testGetBoolean(): void
    {
        $this->assertTrue(\F_getBoolean(true));
        $this->assertFalse(\F_getBoolean(false));
        $this->assertTrue(\F_getBoolean('t'));    // PostgreSQL boolean text
        $this->assertTrue(\F_getBoolean('true'));
        $this->assertTrue(\F_getBoolean('1'));
        $this->assertFalse(\F_getBoolean('f'));
        $this->assertFalse(\F_getBoolean('0'));
        $this->assertTrue(\F_getBoolean(1));
        $this->assertFalse(\F_getBoolean(0));
    }

    public function testPositiveRequestIntegerRejectsStructuredInput(): void
    {
        self::assertSame(7, \f_positive_request_int('7'));
        self::assertSame(0, \f_positive_request_int(0));
        self::assertSame(0, \f_positive_request_int(['7']));
    }

    public function testNormalizeMatchingPositions(): void
    {
        $this->assertSame(
            [10 => 2, 20 => 1, 30 => 0, 40 => 0],
            \f_normalize_matching_positions([10 => '2', 20 => '1', 30 => '2', 40 => '0']),
        );
        $this->assertSame(
            [10 => 2, 20 => 1, 30 => 2, 40 => 0],
            \f_normalize_matching_positions([10 => '2', 20 => '1', 30 => '2', 40 => '0'], true),
        );
        $this->assertSame([], \f_normalize_matching_positions([]));
    }

    public function testUnhtmlentities(): void
    {
        $this->assertSame('&', \unhtmlentities('&amp;'));
        $this->assertSame('<b>', \unhtmlentities('&lt;b&gt;'));
        // with $preserve_tagsign the angular-bracket entities stay literal
        $this->assertSame('&lt;x&gt;', \unhtmlentities('&lt;x&gt;', true));
    }

    public function testCompactString(): void
    {
        $this->assertSame('a b c d', \F_compact_string("a\tb\nc\rd"));
        $this->assertSame('say &quot;hi&quot;', \F_compact_string('say "hi"', true));
    }

    public function testReplaceAngulars(): void
    {
        $this->assertSame('&lt;a href&gt;', \f_replace_angulars('<a href>'));
    }

    public function testTextXmlRoundTrip(): void
    {
        $text = 'a<b> & c';
        $xml = \F_text_to_xml($text);
        $this->assertSame('a&lt;b&gt; &amp; c', $xml);
        $this->assertSame($text, \f_xml_to_text($xml));
        $this->assertSame('', \F_text_to_xml(''));
    }

    public function testTextTsvRoundTrip(): void
    {
        $text = "col1\tcol2\nrow2";
        $tsv = \f_text_to_tsv($text);
        $this->assertSame('col1\tcol2\nrow2', $tsv); // tab/newline escaped to literal sequences
        $this->assertSame($text, \f_tsv_to_text($tsv));
    }

    public function testFormatFloat(): void
    {
        $this->assertSame('1.235', \f_format_float(1.23456));
        $this->assertSame('2.000', \f_format_float(2));
        $this->assertSame('0.000', \f_format_float(null));
    }

    public function testFormatPercentage(): void
    {
        $this->assertSame('(&nbsp;50%)', \F_formatPercentage(0.5));       // ratio 0..1, space-padded
        $this->assertSame('(&nbsp;50%)', \F_formatPercentage(50, false)); // percentage 0..100
        $this->assertSame('(100%)', \F_formatPercentage(1.0));            // 3 digits => no padding
    }

    public function testFormatPdfAndXmlPercentage(): void
    {
        $this->assertSame('( 50%)', \f_format_pdf_percentage(0.5));
        $this->assertSame(' 50', \f_format_xml_percentage(0.5));
    }

    public function testUtcOffsets(): void
    {
        self::assertSame(0, \f_get_utc_offset('UTC'));
        self::assertSame('+00:00', \f_db_get_utc_offset('UTC'));
    }

    public function testXmlAndTsvDataSerialization(): void
    {
        $data = ['Name' => 'A&B', 'meta' => ['score' => 10]];

        self::assertSame(
            "\t<name>A&amp;B</name>\n\t<meta>\n\t\t<score>10</score>\n</meta>\n",
            \get_data_xml($data),
        );
        self::assertSame("\t<item_private>x</item_private>\n", \get_data_xml(['_private' => 'x']));
        self::assertSame("\tName\tmeta_score", \get_data_tsv_header($data));
        self::assertSame("\tA&B\t10", \get_data_tsv($data));
    }

    public function testHtmlToTsvPreservesTableContent(): void
    {
        $html = '<table><tr><th>A</th><th>B</th></tr><tr><td>$1</td><td>A&amp;B<br>next</td></tr></table>';

        self::assertSame("\tA\tB\n\t$1\tA&B next", \f_html_to_tsv($html));
    }

    public function testGetContrastColor(): void
    {
        $this->assertSame('ffffff', \get_contrast_color('000000')); // dark background -> white
        $this->assertSame('000000', \get_contrast_color('ffffff')); // light background -> black
    }

    public function testIsUrl(): void
    {
        $this->assertTrue(\f_is_url('https://example.com/path'));
        $this->assertTrue(\f_is_url('ftp://host/file'));
        $this->assertFalse(\f_is_url('just text'));
        $this->assertFalse(\f_is_url('/relative/path'));
    }

    public function testNormalizedIpInvariantsAndValidation(): void
    {
        // localhost forms collapse to the same normalized value
        $this->assertSame(\getNormalizedIP('127.0.0.1'), \getNormalizedIP('::1'));
        $this->assertSame(
            \getNormalizedIP('127.0.0.1'),
            \getNormalizedIP('0000:0000:0000:0000:0000:0000:0000:0001'),
        );
        // an already-expanded IPv6 address normalizes to itself
        $ipv6 = '2001:0db8:0000:0000:0000:0000:0000:0001';
        $this->assertSame($ipv6, \getNormalizedIP($ipv6));
        // invalid inputs
        $this->assertFalse(\getNormalizedIP('not-an-ip'));
        $this->assertFalse(\getNormalizedIP('256.0.0.1'));
    }

    public function testIpAsBytes(): void
    {
        // always packs to the 16-byte IPv6 form, for both IPv4 and IPv6 input
        $this->assertSame(16, \strlen((string) \getIpAsBytes('192.168.1.1')));
        $this->assertSame(16, \strlen((string) \getIpAsBytes('2001:db8::1')));
        // case-insensitive: upper- and lower-case hex pack to identical bytes
        $this->assertSame(\getIpAsBytes('2001:DB8::1'), \getIpAsBytes('2001:db8::1'));
        // equivalent localhost forms pack identically
        $this->assertSame(\getIpAsBytes('127.0.0.1'), \getIpAsBytes('::1'));
        // ordering matches numeric value (byte-wise strcmp)
        $this->assertLessThan(0, \strcmp((string) \getIpAsBytes('::9'), (string) \getIpAsBytes('::a')));
        // invalid input
        $this->assertFalse(\getIpAsBytes('not-an-ip'));
    }

    public function testIpAsStringUsesCompactReadableNotation(): void
    {
        self::assertSame('127.0.0.1', \getIpAsString('127.0.0.1'));
        self::assertSame('2001:db8::1', \getIpAsString('2001:0db8:0000:0000:0000:0000:0000:0001'));
        self::assertSame('', \getIpAsString('not-an-ip'));
    }

    public function testSubstrUtf8(): void
    {
        $this->assertSame('hel', \f_substr_utf8('hello', 0, 3));
        $this->assertSame('caf', \f_substr_utf8('café', 0, 3));
    }

    public function testUtf8NormalizerModes(): void
    {
        $decomposed = "e\u{0301}";

        self::assertSame('plain', \f_utf8_normalizer('plain'));
        self::assertSame('plain', \f_utf8_normalizer('plain', 'UNKNOWN'));
        self::assertSame('plain', \f_utf8_normalizer('plain', 'CUSTOM'));
        self::assertSame('é', \f_utf8_normalizer($decomposed, 'C'));
        self::assertSame($decomposed, \f_utf8_normalizer('é', 'D'));
        self::assertSame('fi', \f_utf8_normalizer('ﬁ', 'KC'));
        self::assertSame('fi', \f_utf8_normalizer('ﬁ', 'KD'));
    }

    public function testDecimalToHexConversion(): void
    {
        self::assertSame('0', \bcdechex(0));
        self::assertSame('F', \bcdechex(15));
        self::assertSame('10', \bcdechex(16));
        self::assertSame('FF', \bcdechex(255));
        self::assertSame('FFFFFFFFFFFFFFFF', \bcdechex('18446744073709551615'));
    }

    public function testUtrim(): void
    {
        $this->assertSame('hi there', \utrim('   hi there   '));
        $this->assertSame('', \utrim(''));
    }

    public function testBcdechex(): void
    {
        if (! \extension_loaded('bcmath')) {
            $this->markTestSkipped('bcmath extension not available');
        }

        $this->assertSame('FF', \bcdechex('255'));
        $this->assertSame('10', \bcdechex('16'));
        $this->assertSame('0', \bcdechex('0'));
    }
}

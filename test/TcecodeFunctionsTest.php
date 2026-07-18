<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class TcecodeFunctionsTest extends TestCase
{
    public function testDetectsImportedHtmlWithoutMistakingComparisonForMarkup(): void
    {
        $this->assertTrue(\F_has_html_markup('<p><strong>Question</strong></p>'));
        $this->assertFalse(\F_has_html_markup('Find x when x < y.'));
    }

    public function testSanitizesImportedHtmlAndPreservesFormatting(): void
    {
        $html = '<p style="text-align:justify;color:red" onclick="alert(1)">'
            . '<strong>Read</strong>&nbsp;<em>carefully</em><script>alert(2)</script></p>';

        $this->assertSame(
            '<p style="text-align: justify"><strong>Read</strong>' . "\u{00A0}" . '<em>carefully</em></p>',
            \F_sanitize_html_content($html),
        );
    }

    public function testRejectsExecutableUrls(): void
    {
        $this->assertSame('<p><a>unsafe</a><img></p>', \F_sanitize_html_content(
            '<p><a href="javascript:alert(1)" target="popup">unsafe</a>'
            . '<img src="data:image/svg+xml,evil" onerror="alert(2)"></p>',
        ));
    }
}

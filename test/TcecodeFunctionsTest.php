<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class TcecodeFunctionsTest extends TestCase
{
    public function testStringTransformersPreserveTcecodeRendering(): void
    {
        $this->assertSame(
            "[li]One[/li][object]image.png[/object:20:10]",
            \F_bbcode_to_tcecode("[*]One\n[img=20x10]image.png[/img]"),
        );
        $this->assertSame(
            '<a class="tcecode" href="https://example.com" rel="noopener noreferrer" target="_blank">Example</a>',
            \F_tcecode_url('[url=https://example.com]Example[/url]'),
        );
        $this->assertSame('<strong class="tcecode">Bold</strong>', \F_tcecode_tag('[b]Bold[/b]'));
        $this->assertSame(
            '<span style="text-align:center;">Centered</span>',
            \F_tcecode_tag_arg('[align=center]Centered[/align]'),
        );
    }

    public function testRendererProcessDoesNotInterpretShellMetacharacters(): void
    {
        $argument = 'literal;$(touch should-not-exist)';
        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', 'echo $argv[1];', $argument],
            sys_get_temp_dir(),
        );

        $this->assertSame(0, $status);
        $this->assertSame($argument, $output);
    }

    public function testLatexRendererExplicitlyDisablesShellEscape(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/shared/code/tce_functions_tcecode.php');

        $this->assertStringContainsString("'-no-shell-escape'", $source);
        $this->assertStringNotContainsString("exec(\$cmd", $source);
    }

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

    public function testMathmlRemovesEventHandlersAndForeignMarkup(): void
    {
        $rendered = \F_mathml_callback([
            '',
            '<math onclick="alert(1)"><mtext mathvariant="bold" onmouseover="alert(2)">Safe</mtext>'
                . '<img src="x" onerror="alert(3)"></math>',
        ]);

        $this->assertSame('<math><mtext mathvariant="bold">Safe</mtext></math>', $rendered);
        $this->assertStringNotContainsStringIgnoringCase('onmouseover', $rendered);
        $this->assertStringNotContainsStringIgnoringCase('<img', $rendered);
    }

    public function testMathmlRejectsExecutableAttributeValues(): void
    {
        $rendered = \F_mathml_callback([
            '',
            '<math><mtext mathcolor="url(javascript:alert(1))">Safe</mtext></math>',
        ]);

        $this->assertSame('<math><mtext>Safe</mtext></math>', $rendered);
    }
}

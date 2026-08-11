<?php

namespace Test;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class TcecodeFunctionsTest extends TestCase
{
    public function testMissingImageSizeReturnsFalseWithoutLeakingWarnings(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require_once "../config/tce_config.php"; require_once "tce_functions_tcecode.php"; '
                    . '$warnings = []; set_error_handler(static function ($severity, $message) use (&$warnings) {'
                    . '$warnings[] = [$severity, $message]; return true; }); '
                    . '$size = f_tcecode_get_image_size(K_PATH_CACHE . "missing-image-size.png"); '
                    . 'restore_error_handler(); echo json_encode([$size, $warnings]);',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame('[false,[]]', $output);
    }

    public function testMissingTemporaryFileCleanupReturnsFalseWithoutLeakingWarnings(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require_once "../config/tce_config.php"; require_once "tce_functions_tcecode.php"; '
                    . '$warnings = []; set_error_handler(static function ($severity, $message) use (&$warnings) {'
                    . '$warnings[] = [$severity, $message]; return true; }); '
                    . '$removed = f_tcecode_unlink_silently(K_PATH_CACHE . "missing-renderer-temporary-file"); '
                    . 'restore_error_handler(); echo json_encode([$removed, $warnings]);',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame('[false,[]]', $output);
    }

    public function testDecoderReturnsEmptyStringForEmptyInput(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require_once "../config/tce_config.php"; require_once "tce_functions_tcecode.php"; '
                    . 'echo json_encode(F_decode_tcecode(""));',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status);
        self::assertSame('""', $output);
    }

    public function testTitleConverterReturnsEscapedPlainText(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require_once "../config/tce_config.php"; require_once "tce_functions_general.php"; '
                    . 'require_once "tce_functions_tcecode.php"; '
                    . '$GLOBALS["l"] = ["a_meta_charset" => "UTF-8"]; '
                    . 'echo json_encode(f_tcecode_to_title("[b]A & B[/b]"));',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status);
        self::assertSame('"A &amp; B"', $output);
    }

    public function testObjectReplacementReturnsImageMarkup(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require_once "../config/tce_config.php"; require_once "tce_functions_tcecode.php"; '
                    . '$name = "tce-object-test-" . hrtime(true); $path = K_PATH_CACHE . $name . ".png"; '
                    . 'file_put_contents($path, base64_decode('
                    . '"iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z8ZkAAAAASUVORK5CYII=")); '
                    . '$maxWidth = 0; $maxHeight = 0; try { '
                    . '$markup = F_objects_replacement($name, "png", 10, 20, "", $maxWidth, $maxHeight); '
                    . 'echo json_encode([str_replace($name, "{name}", $markup), $maxWidth, $maxHeight]); '
                    . '} finally { unlink($path); }',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status);
        self::assertSame(
            '["<img src=\"\\/cache\\/{name}.png\" alt=\"image:{name}.png\" width=\"10\" height=\"20\" '
                . 'class=\"tcecode\" \\/>",1,1]',
            $output,
        );
    }

    public function testMissingObjectImageUsesRequestedDimensionsWithoutLeakingWarnings(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require_once "../config/tce_config.php"; require_once "tce_functions_tcecode.php"; '
                    . '$warnings = []; set_error_handler(static function ($severity, $message) use (&$warnings) {'
                    . '$warnings[] = [$severity, $message]; return true; }); '
                    . '$maxWidth = 0; $maxHeight = 0; '
                    . '$markup = F_objects_replacement("missing-object-image", "png", 10, 20, "", '
                    . '$maxWidth, $maxHeight); restore_error_handler(); '
                    . 'echo json_encode([$markup, $maxWidth, $maxHeight, $warnings]);',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame(
            '["<img src=\"\\/cache\\/missing-object-image.png\" alt=\"image:missing-object-image.png\" '
                . 'width=\"10\" height=\"20\" class=\"tcecode\" \\/>",0,0,[]]',
            $output,
        );
    }

    public function testObjectCallbackReturnsImageMarkup(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require_once "../config/tce_config.php"; require_once "tce_functions_tcecode.php"; '
                    . '$name = "tce-object-callback-test-" . hrtime(true); $path = K_PATH_CACHE . $name . ".png"; '
                    . 'file_put_contents($path, base64_decode('
                    . '"iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z8ZkAAAAASUVORK5CYII=")); '
                    . 'try { $markup = F_objects_callback(["", $name, "png", "10", "20"]); '
                    . 'echo json_encode(str_replace($name, "{name}", $markup)); } finally { unlink($path); }',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status);
        self::assertSame(
            '"<img src=\"\\/cache\\/{name}.png\" alt=\"image:{name}.png\" width=\"10\" height=\"20\" '
                . 'class=\"tcecode\" \\/>"',
            $output,
        );
    }

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

    public function testRemoveTcecodePreservesVisiblePlaceholders(): void
    {
        self::assertSame(
            'Bold [OBJ] [IMG] [TEX]',
            \f_remove_tcecode('[b]Bold[/b] [object]file[/object] [img]file[/img] [tex]x[/tex]'),
        );
    }

    #[RunInSeparateProcess]
    public function testTcecodeToLineCompactsImportedHtml(): void
    {
        define('K_QUESTION_LINE_MAX_LENGTH', 100);

        self::assertSame('Hello &amp; world', \f_tcecode_to_line('<strong>Hello &amp; world</strong>'));
    }

    public function testPreviewInputPreservesLiteralPlusAndRejectsArrays(): void
    {
        $this->assertSame('A+B C', \f_tcecode_preview_input('A+B%20C'));
        $this->assertSame('', \f_tcecode_preview_input(['A+B']));
    }

    public function testHtmlSubstringStopsAfterClosingTag(): void
    {
        $this->assertSame(
            '<strong>Hello world</strong>',
            \f_substr_html('<strong>Hello world</strong> again', 8, 2),
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

    #[RunInSeparateProcess]
    public function testLatexRendererRejectsInputCommandBeforeExecution(): void
    {
        $workingDirectory = getcwd();
        self::assertIsString($workingDirectory);
        chdir(dirname(__DIR__) . '/admin/code');

        try {
            self::assertSame('[LaTeX error]', \f_latex_callback(['', '\\input{secret}']));
        } finally {
            chdir($workingDirectory);
        }
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
        $rendered = \f_mathml_callback([
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
        $rendered = \f_mathml_callback([
            '',
            '<math><mtext mathcolor="url(javascript:alert(1))">Safe</mtext></math>',
        ]);

        $this->assertSame('<math><mtext>Safe</mtext></math>', $rendered);
    }

    public function testDecoderWiresMathmlCallback(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/shared/code/tce_functions_tcecode.php');

        self::assertStringContainsString("'f_mathml_callback'", $source);
    }

    public function testDecoderWiresLatexCallback(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/shared/code/tce_functions_tcecode.php');

        self::assertStringContainsString("'f_latex_callback'", $source);
    }
}

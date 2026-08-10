<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../admin/code/tce_functions_tcecode_editor.php';

final class TcecodeEditorTest extends TestCase
{
    public function testImageButtonMarkup(): void
    {
        self::assertSame(
            '<button type="button" class="tcecodebtn" onclick="undo()" title="Undo [z]" accesskey="z">'
                . '<img src="undo.gif" alt="Undo [z]" class="button" width="23" height="22" /></button>',
            \get_image_button('Undo', '', 'undo.gif', 'undo()', 'z'),
        );
    }

    public function testEditorToolbarPreservesControlsFontsAndSanitizedTargets(): void
    {
        $fonts = var_export(serialize(['Arial' => 'Arial', 'Mono' => 'Courier New']), true);
        $configSource = '<?php define("K_PATH_IMAGES", "/images/"); define("K_NEWLINE", "\\n"); '
            . 'define("K_AVAILABLE_FONTS", ' . $fonts . '); '
            . '$l = ["w_undo" => "Undo", "w_redo" => "Redo", '
            . '"w_font_size" => "Font size", "w_font" => "Font"];';
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$root = sys_get_temp_dir() . "/openvsosh-tcecode-editor-" . uniqid(); '
                    . 'mkdir($root . "/admin/code", 0700, true); mkdir($root . "/admin/config", 0700); '
                    . 'copy($argv[1], $root . "/admin/code/tce_functions_tcecode_editor.php"); '
                    . 'file_put_contents($root . "/admin/config/tce_config.php", base64_decode($argv[2], true)); '
                    . 'chdir($root . "/admin/code"); require "tce_functions_tcecode_editor.php"; '
                    . '$toolbar = tcecode_editor_tag_buttons("form-1", "field-2"); '
                    . 'unlink($root . "/admin/code/tce_functions_tcecode_editor.php"); '
                    . 'unlink($root . "/admin/config/tce_config.php"); '
                    . 'rmdir($root . "/admin/code"); rmdir($root . "/admin/config"); rmdir($root . "/admin"); '
                    . 'rmdir($root); echo $toolbar;',
                dirname(__DIR__) . '/admin/code/tce_functions_tcecode_editor.php',
                base64_encode($configSource),
            ],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        self::assertStringContainsString("FJ_undo(document.getElementById('form1').field2)", $output);
        self::assertStringContainsString('title="Undo [z]" accesskey="z"', $output);
        self::assertStringContainsString("FJ_redo(document.getElementById('form1').field2)", $output);
        self::assertSame(2, substr_count($output, 'class="tcecodecolorwrap"'));
        self::assertStringContainsString('name="font_size_field2" id="font_size_field2"', $output);
        self::assertStringContainsString('<option value="[size=400%]">400%</option>', $output);
        self::assertStringContainsString('name="font_field2" id="font_field2"', $output);
        self::assertStringContainsString('<option value="[font=Arial]">Arial</option>', $output);
        self::assertStringContainsString('<option value="[font=Courier New]">Mono</option>', $output);
        self::assertStringContainsString('tce_select_mediafile.php?frm=form1&amp;fld=field2', $output);
        self::assertStringContainsString('/images/buttons/mathml.gif', $output);
    }
}

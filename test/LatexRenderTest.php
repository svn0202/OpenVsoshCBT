<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class LatexRenderTest extends TestCase
{
    public function testCachedFormulaAndValidationResultsRemainUnchanged(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'define("K_LATEX_TMP_DIR", sys_get_temp_dir() . DIRECTORY_SEPARATOR); '
                    . 'define("K_LATEX_PATH_LATEX", "/usr/bin/latex"); '
                    . 'define("K_LATEX_PATH_DVIPS", "/usr/bin/dvips"); '
                    . 'define("K_LATEX_PATH_IDENTIFY", "/usr/bin/identify"); '
                    . 'require_once "LatexRender.php"; '
                    . '$cache = sys_get_temp_dir() . "/latex-render-test-" . hrtime(true) . "/"; mkdir($cache); '
                    . '$renderer = new \\LatexRender(); '
                    . '$renderer->setPathToPicturesDir($cache); '
                    . '$renderer->setPathToPicturesDirHttpd("/formula/"); '
                    . '$renderer->setFilenamePrefix("eq_"); $renderer->setImageFormat("svg"); '
                    . '$formula = "x&gt;y"; $file = "eq_" . md5("x>y") . ".svg"; touch($cache . $file); '
                    . '$cached = $renderer->getFormulaURL($formula); '
                    . '$renderer->setMaxLength(3); $tooLong = $renderer->getFormulaURL("abcd"); '
                    . '$tooLongCode = $renderer->getErrorCode(); '
                    . '$renderer->setMaxLength(500); $renderer->setLatexBlackList(["input"]); '
                    . '$blocked = $renderer->getFormulaURL("\\\\input{secret}"); '
                    . 'echo json_encode([$cached, $tooLong, $tooLongCode, $blocked, '
                    . '$renderer->getErrorCode(), $renderer->getImageWidth(), $renderer->getImageHeight()]); '
                    . 'unlink($cache . $file); rmdir($cache);',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame('["\\/formula\\/eq_' . md5('x>y') . '.svg",false,1,false,2,0,0]', $output);
    }
}

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
}

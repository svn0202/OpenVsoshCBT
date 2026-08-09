<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_html2txt.php';

final class HtmlToTextTest extends TestCase
{
    public function testConvertsMarkupAndEntitiesToPlainText(): void
    {
        self::assertSame('Hello & world', $this->convert('<p>Hello &amp; <strong>world</strong></p>'));
    }

    public function testCanExposeLinkTargets(): void
    {
        self::assertSame(
            'Example [LINK: https://example.test]',
            $this->convert('<a href="https://example.test">Example</a>', false, true),
        );
    }

    private function convert(string $html, bool $preserveNewlines = false, bool $displayLinks = false): string
    {
        $workingDirectory = getcwd();
        if ($workingDirectory === false) {
            self::fail('The current working directory is unavailable.');
        }
        chdir(__DIR__ . '/../public/code');
        try {
            return \F_html_to_text($html, $preserveNewlines, $displayLinks);
        } finally {
            chdir($workingDirectory);
        }
    }
}
